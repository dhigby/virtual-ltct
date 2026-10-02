<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

use core_plugin_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Reports how this site differs from the declaration, and changes nothing (US2).
 *
 * Four passes:
 *   1. every declared item, through the inspector (changed, missing, unknown, forced, ...);
 *   2. installed plugins that are not standard and not declared (extra);
 *   3. undeclared settings whose value differs from Moodle's default and that ignore.yaml
 *      does not list (unmanaged, research R5);
 *   4. items this site owns that are no longer declared (extra, spec 002): an `ltct:`
 *      category or cohort, an `ltct_` profile field, and an `ltct: `-named cohort rule;
 *      and, from spec 004, an `ltct_` course field, a live competency row, and a
 *      `local_ltuse` custom report.
 *   5. the discussion forum of every ltct: course against course-discussions.yaml (spec 012):
 *      differs, missing, and the warnings forced and allparticipants. allparticipants is a
 *      count only, never a subject, a post or a name (constitution III).
 *
 * A setting forced in config.php is skipped in pass 3: config.php belongs to provisioning,
 * not to this declaration (R6). Any difference makes the run exit 1 (FR-008); a warning is
 * printed as [skip] and counts as a difference too, because someone should look at it.
 *
 * Pass 4 only reports. Nothing undeclared is ever deleted (FR-004), and none of its items
 * blocks apply. A cohort is reported by idnumber and name only: no member is read, listed or
 * counted (constitution III). Its raw reads are by indexed prefix on core tables:
 * `course_categories.idnumber`, `cohort.idnumber` and `user_info_field.shortname`. Rules are
 * read only through cohortrules::owned_rules(), which uses the plugin's own persistent class
 * and returns nothing when tool_dynamic_cohorts is not installed, so that scan is skipped.
 *
 * Spec 004's undeclared items come from their own classes' extras()/extra(), not from raw
 * reads here: coursefields (an `ltct_` course field, never its course values), competencies
 * (a live row the list leaves out, only when a list is declared, so drift says only what apply
 * would do; never the course map) and reports (a `local_ltuse` report by area and name; no
 * report is ever run, and no row or count is read), and from spec 013 an unmapped badge in an
 * `ltct:` course (badgetemplate::extras(), by course idnumber and badge id; never an award).
 * A second certificate site template with the declared name is reported by the inspector as
 * `ambiguous`, which also blocks apply.
 */
class drift {

    /** The idnumber prefix of every category and cohort this site owns. */
    const OWNED_IDNUMBER_PREFIX = 'ltct:';

    /** The shortname prefix of every profile field this site owns. */
    const OWNED_FIELD_PREFIX = 'ltct_';

    /** @var inspector */
    protected $inspector;

    /** @var report */
    protected $report;

    /**
     * @param inspector $inspector
     * @param report $report
     */
    public function __construct(inspector $inspector, report $report) {
        $this->inspector = $inspector;
        $this->report = $report;
    }

    /**
     * Run the comparison. The exit code comes from the report.
     */
    public function run(): void {
        foreach ($this->inspector->inspect() as $item) {
            $this->report->add_result($item);
        }
        $declaration = $this->inspector->declaration();
        $this->report_extra_plugins($declaration);
        $this->report_unmanaged_settings($declaration);
        $this->report_extra_owned($declaration);
        $this->report_extra_reporting();
        $this->report_discussions();
        // Pass 6 (spec 003, R2): a published course with activity reports turned on.
        foreach ($this->inspector->check_course_reports() as $item) {
            $this->report->add_result($item);
        }
    }

    /**
     * Pass 4, spec 004: undeclared course fields, competencies and reports, as `extra`.
     */
    protected function report_extra_reporting(): void {
        foreach ($this->inspector->coursefields()->extras() as $item) {
            $this->report->add_result($item);
        }
        if ($this->inspector->declares_competencies()) {
            foreach ($this->inspector->competencies()->extras() as $item) {
                $this->report->add_result($item);
            }
        }
        foreach ($this->inspector->reports()->extra() as $item) {
            $this->report->add_result($item);
        }
        // Spec 013: a badge in an ltct: course that is not the published one. Drift never
        // judges whether a badge should be active; only the publisher knows a course's stage.
        if ($this->inspector->badgetemplate()) {
            foreach (badgetemplate::extras() as $item) {
                $this->report->add_result($item);
            }
        }
    }

    /**
     * Pass 5: each published course's discussion forum (spec 012, R5).
     */
    protected function report_discussions(): void {
        foreach ($this->inspector->discussion_targets() as $target) {
            foreach ($this->inspector->check_discussion($target) as $item) {
                $this->report->add_result($item);
            }
        }
    }

    /**
     * Pass 4: every item this site owns that the declaration no longer lists, as `extra`.
     *
     * @param array $declaration
     */
    protected function report_extra_owned(array $declaration): void {
        $declared = self::declared_values($declaration['categories'] ?? [], 'idnumber');
        foreach (self::owned_records('course_categories', 'idnumber', self::OWNED_IDNUMBER_PREFIX,
                'id, idnumber, name') as $record) {
            if (!isset($declared[(string)$record->idnumber])) {
                $this->report->add('fail', 'extra', categories::subject((string)$record->idnumber), null,
                    (string)$record->name, 'no longer declared in organisations.yaml; kept, never deleted');
            }
        }

        $declared = self::declared_values($declaration['cohorts'] ?? [], 'idnumber');
        foreach (self::owned_records('cohort', 'idnumber', self::OWNED_IDNUMBER_PREFIX,
                'id, idnumber, name') as $record) {
            if (!isset($declared[(string)$record->idnumber])) {
                $this->report->add('fail', 'extra', cohorts::subject((string)$record->idnumber), null,
                    (string)$record->name, 'no longer declared in organisations.yaml; kept, never deleted');
            }
        }

        $declared = self::declared_values($declaration['profile_fields'] ?? [], 'shortname');
        foreach (self::owned_records('user_info_field', 'shortname', self::OWNED_FIELD_PREFIX,
                'id, shortname, name') as $record) {
            if (!isset($declared[(string)$record->shortname])) {
                $this->report->add('fail', 'extra', 'profilefield:' . $record->shortname, null,
                    (string)$record->name, 'no longer declared in profile-fields.yaml; kept, never deleted');
            }
        }

        if (!cohortrules::plugin_installed()) {
            return;
        }
        $declared = self::declared_values($declaration['cohort_rules'] ?? [], 'cohort_idnumber');
        foreach (cohortrules::owned_rules() as $rule) {
            $idnumber = (string)$rule['cohort_idnumber'];
            if ($idnumber !== '' && isset($declared[$idnumber])) {
                continue;
            }
            if ($idnumber === '') {
                // Its cohort is gone, so name the rule by the idnumber its name carries.
                $subject = cohortrules::TYPE . ':' . substr((string)$rule['name'], strlen(cohortrules::NAME_PREFIX));
                $message = 'its cohort no longer exists; kept, never deleted';
            } else {
                $subject = cohortrules::TYPE . ':' . $idnumber;
                $message = 'no longer declared in organisations.yaml; kept, never deleted';
            }
            $this->report->add('fail', 'extra', $subject, null, (string)$rule['name'], $message);
        }
    }

    /**
     * Records of a core table whose key column starts with a prefix, matched case-sensitively.
     * The prefix is checked again in PHP, because a collation may still fold case.
     *
     * @param string $table
     * @param string $column an indexed key column
     * @param string $prefix
     * @param string $fields
     * @return \stdClass[] keyed by id
     */
    protected static function owned_records(string $table, string $column, string $prefix, string $fields): array {
        global $DB;
        $select = $DB->sql_like($column, ':prefix', true, true);
        $params = ['prefix' => $DB->sql_like_escape($prefix) . '%'];
        $records = $DB->get_records_select($table, $select, $params, $column, $fields);
        return array_filter($records, function($record) use ($column, $prefix) {
            return strpos((string)$record->$column, $prefix) === 0;
        });
    }

    /**
     * The values of one key across a payload array, as a set.
     *
     * @param array $entries
     * @param string $key
     * @return array<string, true>
     */
    protected static function declared_values(array $entries, string $key): array {
        $values = [];
        foreach ($entries as $entry) {
            $entry = (array)$entry;
            if (isset($entry[$key])) {
                $values[(string)$entry[$key]] = true;
            }
        }
        return $values;
    }

    /**
     * @param array $declaration
     */
    protected function report_extra_plugins(array $declaration): void {
        $declared = array_column($declaration['plugins'] ?? [], 'component');
        foreach (core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $info) {
                if ($info->is_standard() || $info->versiondb === null
                        || in_array($info->component, $declared, true)) {
                    continue;
                }
                $this->report->add('fail', 'extra', $info->component, null, (string)$info->versiondb,
                    'installed but not declared in site.yaml');
            }
        }
    }

    /**
     * @param array $declaration
     */
    protected function report_unmanaged_settings(array $declaration): void {
        $skip = array_flip(array_merge(
            array_column($declaration['settings'] ?? [], 'name'),
            array_column($declaration['ignore'] ?? [], 'setting')
        ));
        foreach ($this->inspector->all_settings() as $key => $setting) {
            if (isset($skip[$key]) || $setting->is_readonly()) {
                continue;
            }
            $default = $setting->get_defaultsetting();
            $live = $setting->get_setting();
            if ($default === null || $live === null || $live === true) {
                // No default to compare with, never written, or a widget with no stored value
                // (admin_setting_check, the AI provider manager), whose get_setting() is true.
                continue;
            }
            if (inspector::normalise_live($setting, $live) === inspector::normalise_live($setting, $default)) {
                continue;
            }
            $this->report->add('fail', 'unmanaged', $key, null, $live,
                'differs from the Moodle default; declare it or add it to ignore.yaml', $setting);
        }
    }
}
