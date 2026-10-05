<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;

/**
 * The cohorts and courses the site team may name, by idnumber (spec 008, contracts/cli.md).
 *
 * `ltct_admin.py list` prints these so an operator, or the guided command, never invents a
 * name: every command takes idnumbers, never a database id.
 *
 *   cohorts   every cohort whose idnumber starts ltct:; with an organisation key, only that
 *             organisation's member and managers cohorts
 *   courses   every course whose idnumber is ltct:<slug> (a course, not a module), with the
 *             idnumber of its category; with an organisation key, only the courses that
 *             organisation may be enrolled into: ltct:published and its own ltct:org:<key>
 *
 * Read-only. Two reads of core tables by their idnumber columns (cohort.idnumber and
 * course.idnumber), listed in moodle/local_ltuse/README.md (constitution XI). Names only: no
 * member, no count, no database id.
 */
class admin_list extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'what' => new external_value(PARAM_ALPHA, 'cohorts or courses'),
            'orgkey' => new external_value(PARAM_ALPHANUMEXT, 'Only this organisation key',
                VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $what, string $orgkey = ''): array {
        global $DB;

        ['what' => $what, 'orgkey' => $orgkey] = self::validate_parameters(
            self::execute_parameters(), ['what' => $what, 'orgkey' => $orgkey]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);

        $items = [];
        if ($what === 'cohorts') {
            require_capability('moodle/cohort:view', $context);
            $prefix = $orgkey === '' ? 'ltct:' : "ltct:org:{$orgkey}";
            $records = $DB->get_records_select('cohort',
                $DB->sql_like('idnumber', ':prefix'),
                ['prefix' => $DB->sql_like_escape($prefix) . '%'], 'idnumber ASC', 'id, idnumber, name');
            foreach ($records as $cohort) {
                if ($orgkey !== '' && !in_array($cohort->idnumber,
                        ["ltct:org:{$orgkey}", "ltct:org:{$orgkey}:managers"], true)) {
                    continue;   // ltct:org:fixture-a must not list ltct:org:fixture-ab.
                }
                $items[] = ['idnumber' => $cohort->idnumber, 'name' => $cohort->name, 'category' => ''];
            }
        } else if ($what === 'courses') {
            $allowed = $orgkey === '' ? null : ['ltct:published', "ltct:org:{$orgkey}"];
            $sql = "SELECT c.id, c.idnumber, c.fullname, cc.idnumber AS categoryidnumber
                      FROM {course} c
                      JOIN {course_categories} cc ON cc.id = c.category
                     WHERE " . $DB->sql_like('c.idnumber', ':prefix') . "
                  ORDER BY c.idnumber ASC";
            foreach ($DB->get_records_sql($sql, ['prefix' => 'ltct:%']) as $course) {
                if (!preg_match('/^ltct:[^:]+$/D', $course->idnumber)) {
                    continue;   // A module or an organisation idnumber, not a course.
                }
                $category = (string)$course->categoryidnumber;
                if ($allowed !== null && !in_array($category, $allowed, true)) {
                    continue;
                }
                $items[] = ['idnumber' => $course->idnumber, 'name' => $course->fullname,
                    'category' => $category];
            }
        } else {
            throw new invalid_parameter_exception('what must be cohorts or courses');
        }
        return ['items' => $items];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'idnumber' => new external_value(PARAM_RAW, 'The idnumber to name it by'),
                    'name' => new external_value(PARAM_RAW, 'Its display name'),
                    'category' => new external_value(PARAM_RAW,
                        'For a course, its category idnumber; empty for a cohort'),
                ])
            ),
        ]);
    }
}
