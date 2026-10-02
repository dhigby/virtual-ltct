<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

use admin_setting;
use context_system;
use core_plugin_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Brings this site to the declaration, changing only what differs (US1).
 *
 * Every blocking problem the inspector finds is reported first, and if there is one, nothing
 * is written at all: not the part that would have worked either (data-model "Run report").
 * Then it writes through Moodle's own APIs, so validation, update callbacks and config_log
 * all happen as they would from the admin UI:
 *
 *   settings  admin_setting::write_setting(), then post_write_settings() as admin_write_settings() does
 *   plugins   the plugininfo class's enable_plugin()
 *   roles     create_role(), set_role_contextlevels(), assign_capability(), unassign_capability()
 *
 * After settings come spec 002's four arrays, each handed to its own class, in the order the
 * contract fixes (specs/002-org-structure-cohorts/contracts/declaration.md "Output additions"):
 *
 *   categories      categories::apply(), parents first, as the payload lists them
 *   cohorts         cohorts::apply()
 *   profile_fields  profilefields::apply(): the field category first, then the fields
 *   cohort_rules    cohortrules::apply(), last, because a rule needs its cohort and its field
 *
 * Then spec 004's, in the order its contract fixes (specs/004-progress-reporting/contracts/
 * declaration.md "Output additions"):
 *
 *   course_field_category, course_fields  coursefields::apply(): the category, then the fields
 *   competencies                          competencies::apply(): retires, never deletes
 *   reports                               reports::apply(), last, because a report's columns and
 *                                         audiences need the fields and cohorts made above
 *
 * The preflight stops on a run-wide block only (report::has_blocking()). A report-scoped
 * block, such as an audience cohort that does not exist, leaves just that report unwritten:
 * reports::apply() checks each report again and skips the blocked one.
 *
 * It never installs, upgrades or downgrades plugin code, never resets or deletes a role, and
 * never deletes a category, cohort, field, rule, competency or report (FR-004).
 */
class applier {

    /** @var inspector */
    protected $inspector;

    /** @var report */
    protected $report;

    /** @var array the decoded payload */
    protected $declaration;

    /**
     * @param inspector $inspector
     * @param report $report
     */
    public function __construct(inspector $inspector, report $report) {
        $this->inspector = $inspector;
        $this->report = $report;
        $this->declaration = $inspector->declaration();
    }

    /**
     * Run the apply. The exit code comes from the report.
     */
    public function run(): void {
        $preflight = $this->inspector->inspect();
        if (report::has_blocking($preflight)) {
            foreach ($preflight as $item) {
                $this->report->add_result($item);
            }
            return;
        }

        $this->report->add_result($this->inspector->check_release());
        foreach ($this->declaration['plugins'] ?? [] as $plugin) {
            $this->apply_plugin($plugin);
        }
        foreach ($this->declaration['roles'] ?? [] as $role) {
            $this->apply_role($role);
        }
        foreach ($this->declaration['settings'] ?? [] as $setting) {
            $this->apply_setting($setting);
        }
        $this->apply_structure();
        $this->apply_reporting();
    }

    /**
     * Apply spec 002's four arrays, in the contract's order. One instance of each class serves
     * the whole run, so a category parent created or adopted here is known to its children.
     * The preflight has already passed, so nothing here blocks; each class still reports a
     * blocking result rather than writing over it.
     */
    protected function apply_structure(): void {
        $categories = new categories();
        foreach ($this->declaration['categories'] ?? [] as $category) {
            $categories->apply((array)$category, $this->report);
        }

        $cohorts = new cohorts();
        foreach ($this->declaration['cohorts'] ?? [] as $cohort) {
            $cohorts->apply((array)$cohort, $this->report);
        }

        $fields = $this->declaration['profile_fields'] ?? [];
        if ($fields) {
            (new profilefields($fields))->apply($this->report);
        }

        foreach ($this->declaration['cohort_rules'] ?? [] as $rule) {
            cohortrules::apply((array)$rule, $this->report);
        }
    }

    /**
     * Apply spec 004's arrays, after spec 002's: the course field category and course fields,
     * the competency list, then reports. Each is skipped when the payload does not declare it,
     * by the same gates the preflight used (inspector::declares_*()), so apply never writes
     * what the preflight did not check.
     */
    protected function apply_reporting(): void {
        if ($this->inspector->declares_course_fields()) {
            $this->inspector->coursefields()->apply($this->report);
        }
        if ($this->inspector->declares_competencies()) {
            $this->inspector->competencies()->apply($this->report);
        }
        $this->inspector->reports()->apply($this->report);
    }

    /**
     * @param array $plugin {component, enabled?, version?}
     */
    protected function apply_plugin(array $plugin): void {
        $item = $this->inspector->check_plugin($plugin);
        if ($item['result'] !== inspector::RESULT_CHANGED) {
            $this->report->add_result($item);
            return;
        }
        $info = core_plugin_manager::instance()->get_plugin_info($plugin['component']);
        $class = core_plugin_manager::resolve_plugininfo_class($info->type);
        $class::enable_plugin($info->name, inspector::enabled_state($info->type, $plugin['enabled']));
        $this->report_write($this->inspector->check_plugin($plugin), $item);
    }

    /**
     * Create the role if it is missing, then set its context levels and capabilities.
     *
     * @param array $role the role declaration
     */
    protected function apply_role(array $role): void {
        global $DB;
        $shortname = $role['shortname'];
        $record = $DB->get_record('role', ['shortname' => $shortname]);
        if (!$record) {
            $id = create_role($role['name'], $shortname, (string)($role['description'] ?? ''),
                (string)($role['archetype'] ?? ''));
            $record = $DB->get_record('role', ['id' => $id], '*', MUST_EXIST);
            $this->report->add('changed', 'missing', "role:{$shortname}", $shortname, null, 'created');
        }

        $syscontextid = context_system::instance()->id;
        $expected = $this->inspector->expected_role_capabilities($role, $record->archetype);
        foreach ($this->inspector->check_role($role) as $item) {
            if ($item['result'] !== inspector::RESULT_CHANGED) {
                $this->report->add_result($item);
                continue;
            }
            if ($item['item'] === "role:{$shortname}:contextlevels") {
                set_role_contextlevels($record->id, inspector::contextlevel_ids($role['contextlevels']));
            } else {
                $cap = substr($item['item'], strlen($shortname) + 1);
                if (isset($expected[$cap])) {
                    assign_capability($cap, $expected[$cap], $record->id, $syscontextid, true);
                } else {
                    unassign_capability($cap, $record->id, $syscontextid);
                }
            }
            $this->report->add_result($item, 'changed');
        }
    }

    /**
     * @param array $declared {name, value?, secret?, env_missing?}
     */
    protected function apply_setting(array $declared): void {
        $item = $this->inspector->check_setting($declared);
        if ($item['result'] !== inspector::RESULT_CHANGED) {
            $this->report->add_result($item);
            return;
        }
        $setting = $this->inspector->find_setting($declared['name']);
        $original = $setting->get_setting();
        $error = $setting->write_setting($this->inspector->write_data($setting, $declared['value']));
        if ($error !== '') {
            $this->report->add_result($item, 'fail', "Moodle refused the value: {$error}");
            return;
        }
        $setting->post_write_settings($original);
        $this->report_write($this->inspector->check_setting($declared), $item);
    }

    /**
     * Report a write by checking again: a write Moodle accepted but that did not take is a failure.
     *
     * @param array $after the item checked after the write
     * @param array $before the item checked before it
     */
    protected function report_write(array $after, array $before): void {
        if ($after['result'] === inspector::RESULT_OK) {
            $this->report->add_result($before, 'changed');
        } else {
            $this->report->add_result($after, 'fail', 'written, but the server still differs');
        }
    }
}
