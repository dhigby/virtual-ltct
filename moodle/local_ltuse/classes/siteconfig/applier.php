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
 *   roles     create_role(), set_role_contextlevels(), assign_capability(), unassign_capability(),
 *             then core_role_set_assign_allowed() for each declared allow-assign pair (spec 003)
 *   discussions  ensure_discussion::apply_groupmode(), the publisher's own path (spec 012)
 *
 * After settings come spec 002's four arrays, each handed to its own class, in the order the
 * contract fixes (specs/002-org-structure-cohorts/contracts/declaration.md "Output additions"):
 *
 *   categories      categories::apply(), parents first, as the payload lists them
 *   cohorts         cohorts::apply()
 *   profile_fields  profilefields::apply(): the field category first, then the fields
 *   cohort_rules    cohortrules::apply(), last, because a rule needs its cohort and its field
 *
 * Then each ltct: course's group mode, set to 0 through update_course() (spec 002 R3,
 * amended 2026-10-02), then each ltct: course's discussion forum (spec 012), set to no
 * groups, then each ltct: course's "Show activity reports", turned off again through
 * update_course() where someone turned it on (spec 003).
 *
 * Then spec 004's, last, in the order its contract fixes (specs/004-progress-reporting/contracts/
 * declaration.md "Output additions"):
 *
 *   course_field_category, course_fields  coursefields::apply(): the category, then the fields
 *   competencies                          competencies::apply(): retires, never deletes;
 *                                         from spec 006, sets each one's slug and url too
 *   levels                                pathwaylevels::apply(): config pathwaylevel1-4 (spec 006)
 *   role_pathways                         rolepathways::apply(), after competencies, whose rows
 *                                         it names; retires, never deletes (spec 006)
 *   reports                               reports::apply(), last, because a report's columns and
 *                                         audiences need the fields and cohorts made above
 *
 * Then spec 013's, after reports (specs/013-certificates-badges/contracts/declaration.md):
 *
 *   badge_template        badgetemplate::apply(): store it, then reword every mapped badge
 *   certificate_template  certtemplate::apply(): the site template, then every activity's copy
 *
 * Then spec 011's, last (specs/011-events-calendar/contracts/declaration.md):
 *
 *   officehours  officehours::apply(): the course, the scheduler, the enrolment instance, the
 *                group name template, then a reconcile of memberships
 *   dashboard    dashboard::apply(): every declared block missing from the default dashboard
 *
 * The preflight stops on a run-wide block only (report::has_blocking()). A report-scoped
 * block, such as an audience cohort that does not exist, leaves just that report unwritten:
 * reports::apply() checks each report again and skips the blocked one.
 *
 * It never installs, upgrades or downgrades plugin code, never resets or deletes a role,
 * never deletes a category, cohort, field, rule, competency or report (FR-004), and never
 * creates a course's discussion forum or writes a post.
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
        // After every role exists, so a pair naming a role created in this run can be added.
        foreach ($this->declaration['roles'] ?? [] as $role) {
            $this->apply_allowassign($role);
        }
        foreach ($this->declaration['settings'] ?? [] as $setting) {
            $this->apply_setting($setting);
        }
        $this->apply_structure();
        // The course's group mode before its forum's, so a course never forces separate
        // groups on a forum this run has just opened (spec 002 R3).
        $this->apply_course_flags('check_course_groupmodes', 'groupmode', NOGROUPS);
        foreach ($this->inspector->discussion_targets() as $target) {
            $this->apply_discussion($target);
        }
        $this->apply_course_flags('check_course_reports', 'showreports', 0);
        // Spec 016: store protection.yaml, after structure (the profile fields the service
        // writes exist by now) and before reporting. Never touches a user or an organisation.
        if ($this->inspector->protection()) {
            $this->inspector->protection()->apply($this->report);
        }
        $this->apply_reporting();
        $this->apply_recognition();
        $this->apply_events();
    }

    /**
     * Apply spec 011's two arrays, last: the office-hours course, its activity, enrolment
     * instance and group name template, then a reconcile of its memberships; then the default
     * dashboard's blocks. Never deletes a course, an activity, a group or a block.
     */
    protected function apply_events(): void {
        if ($this->inspector->officehours()) {
            $this->inspector->officehours()->apply($this->report);
        }
        if ($this->inspector->dashboard()) {
            $this->inspector->dashboard()->apply($this->report);
        }
    }

    /**
     * Set one course setting back in each ltct: course an inspector check lists, through core's
     * update_course(), as the course settings form does: activity reports off (spec 003,
     * research R2) and group mode 0 (spec 002 R3, amended 2026-10-02).
     *
     * @param string $check the inspector method listing the courses, each item with courseid
     * @param string $field the course column to write
     * @param int $value the value to write
     */
    protected function apply_course_flags(string $check, string $field, int $value): void {
        global $CFG;
        $items = $this->inspector->$check();
        if (!$items) {
            return;
        }
        require_once($CFG->dirroot . '/course/lib.php');
        foreach ($items as $item) {
            try {
                update_course((object)['id' => $item['courseid'], $field => $value]);
            } catch (\Throwable $e) {
                $this->report->add_result($item, 'fail', 'Moodle refused the change: ' . $e->getMessage());
                continue;
            }
            $still = false;
            foreach ($this->inspector->$check() as $recheck) {
                if ($recheck['item'] === $item['item']) {
                    $still = true;
                }
            }
            if ($still) {
                $this->report->add_result($item, 'fail', 'written, but the server still differs');
            } else {
                $this->report->add_result($item, 'changed');
            }
        }
    }

    /**
     * Apply spec 013's two templates, after reports: store the badge template and reword every
     * published badge from it, then build the certificate site template and copy it into every
     * certificate activity. Never deactivates a badge or deletes an activity or a template.
     */
    protected function apply_recognition(): void {
        if ($this->inspector->badgetemplate()) {
            $this->inspector->badgetemplate()->apply($this->report);
        }
        if ($this->inspector->certtemplate()) {
            $this->inspector->certtemplate()->apply($this->report);
        }
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
     * the competency list, then spec 006's level labels and role pathways, then reports. Each
     * is skipped when the payload does not declare it, by the same gates the preflight used
     * (inspector::declares_*()), so apply never writes what the preflight did not check.
     */
    protected function apply_reporting(): void {
        if ($this->inspector->declares_course_fields()) {
            $this->inspector->coursefields()->apply($this->report);
        }
        if ($this->inspector->declares_competencies()) {
            $this->inspector->competencies()->apply($this->report);
        }
        if ($this->inspector->declares_levels()) {
            $this->inspector->pathwaylevels()->apply($this->report);
        }
        if ($this->inspector->declares_role_pathways()) {
            $this->inspector->rolepathways()->apply($this->report);
        }
        $this->inspector->reports()->apply($this->report);
    }

    /**
     * Restore one course discussion's group mode (spec 012, contracts/site-declaration.md).
     *
     * `differs` is corrected through ensure_discussion::apply_groupmode(), the same code path
     * a publish takes, which writes only the forum's group mode and grouping. A `missing`
     * forum is never created here: that is the publisher's job, with the course's own name
     * and intro, so it is reported as [skip]. The forced warning cannot be fixed by a write
     * and is reported as [skip] too. Nothing here writes a post.
     *
     * @param array $target one entry of inspector::discussion_targets()
     */
    protected function apply_discussion(array $target): void {
        foreach ($this->inspector->check_discussion($target) as $item) {
            if ($item['result'] === inspector::RESULT_MISSING) {
                $this->report->add_result($item, 'skip');
                continue;
            }
            if ($item['result'] !== inspector::RESULT_DIFFERS) {
                $this->report->add_result($item);
                continue;
            }
            \local_ltuse\external\ensure_discussion::apply_groupmode($target['course'],
                (int)$target['cm']->id, (int)$target['groupmode']);
            $target['cm'] = \local_ltuse\util::cm_by_idnumber((int)$target['course']->id,
                $target['course']->idnumber . inspector::DISCUSSION_SUFFIX);
            $after = null;
            foreach ($this->inspector->check_discussion($target) as $recheck) {
                if ($recheck['item'] === $item['item']) {
                    $after = $recheck;
                }
            }
            $this->report_write($after ?? $item, $item);
        }
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
     * Add each declared allow-assign pair that is missing (spec 003, R6). Never removes one.
     *
     * @param array $role the role declaration
     */
    protected function apply_allowassign(array $role): void {
        global $DB;
        foreach ($this->inspector->check_allowassign($role) as $item) {
            if ($item['result'] !== inspector::RESULT_CHANGED) {
                $this->report->add_result($item);
                continue;
            }
            [, $from, , $to] = explode(':', $item['item'], 4);
            core_role_set_assign_allowed($DB->get_field('role', 'id', ['shortname' => $from], MUST_EXIST),
                $DB->get_field('role', 'id', ['shortname' => $to], MUST_EXIST));
            $after = null;
            foreach ($this->inspector->check_allowassign($role) as $recheck) {
                if ($recheck['item'] === $item['item']) {
                    $after = $recheck;
                }
            }
            $this->report_write($after ?? $item, $item);
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
