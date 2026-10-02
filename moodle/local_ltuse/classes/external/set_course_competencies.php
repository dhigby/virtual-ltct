<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_ltuse\util;
use moodle_exception;

/**
 * Record which competencies a published course aims at (spec 004, R15).
 *
 * The per-competency report counts courses, enrolments and completions by competency. A
 * course custom field holds the same names for people to read, but a report cannot join on
 * a text field without parsing it, so the publisher also writes the course's README
 * frontmatter `competencies:` here, into local_ltuse_course_comp. The write is one-way, from
 * the repo: republishing replaces the course's set.
 *
 * FAILS CLOSED. Every name is resolved against the live, non-retired rows of
 * local_ltuse_competency (written by site_config.py apply from competencies.yaml) before
 * anything is written. One unknown name fails the whole call and leaves the map as it was,
 * because a silently dropped name is a coverage count that is quietly wrong. Names are
 * compared exactly, in PHP, so a case-insensitive database collation cannot match
 * "paratext" to "Paratext".
 *
 * The writes run in one delegated transaction. `competencies` is read back from the table
 * after the commit, so the publisher compares what is stored, not what was meant.
 *
 * No user data is read or written.
 */
class set_course_competencies extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'competencies' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Competency name, verbatim from competencies.yaml'),
                'Every competency the course aims at', VALUE_DEFAULT, []),
        ]);
    }

    public static function execute(int $courseid, array $competencies = []): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'competencies' => $competencies,
        ]);

        $course = $DB->get_record('course', ['id' => $params['courseid']], 'id, idnumber',
            MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);

        // Only a publisher-owned course has a declared set to mirror.
        if (strpos((string)$course->idnumber, util::IDNUMBER_PREFIX) !== 0) {
            throw new moodle_exception('error:notltctcourse', 'local_ltuse', '',
                (string)$course->idnumber);
        }

        // Resolve every name first. Duplicates collapse; order is the caller's.
        $wantednames = array_values(array_unique($params['competencies']));
        $byname = [];
        if ($wantednames) {
            [$insql, $inparams] = $DB->get_in_or_equal($wantednames, SQL_PARAMS_NAMED);
            $live = $DB->get_records_select('local_ltuse_competency',
                "name {$insql} AND retired = 0", $inparams, '', 'id, name');
            foreach ($live as $row) {
                $byname[$row->name] = (int)$row->id;
            }
        }
        foreach ($wantednames as $name) {
            if (!isset($byname[$name])) {
                throw new invalid_parameter_exception(
                    "competency '{$name}' is not on this site: run site_config.py apply");
            }
        }
        $wanted = [];
        foreach ($wantednames as $name) {
            $wanted[$byname[$name]] = $name;
        }

        $current = $DB->get_records('local_ltuse_course_comp', ['courseid' => $course->id],
            '', 'id, competencyid');
        $currentids = [];
        foreach ($current as $row) {
            $currentids[(int)$row->competencyid] = (int)$row->id;
        }

        $addids = array_diff_key($wanted, $currentids);
        $removeids = array_diff_key($currentids, $wanted);

        // Names for what goes, which may be retired rows: the report keeps a retired
        // competency's map rows until each course's next publish, and this is that publish.
        $removed = [];
        if ($removeids) {
            $names = $DB->get_records_list('local_ltuse_competency', 'id',
                array_keys($removeids), '', 'id, name');
            foreach (array_keys($removeids) as $id) {
                $removed[] = isset($names[$id]) ? $names[$id]->name : '#' . $id;
            }
        }

        $transaction = $DB->start_delegated_transaction();
        if ($removeids) {
            $DB->delete_records_list('local_ltuse_course_comp', 'id', array_values($removeids));
        }
        $now = time();
        foreach (array_keys($addids) as $competencyid) {
            $DB->insert_record('local_ltuse_course_comp', (object)[
                'courseid' => $course->id,
                'competencyid' => $competencyid,
                'timemodified' => $now,
            ]);
        }
        $transaction->allow_commit();

        $stored = $DB->get_fieldset_sql(
            "SELECT c.name
               FROM {local_ltuse_course_comp} cc
               JOIN {local_ltuse_competency} c ON c.id = cc.competencyid
              WHERE cc.courseid = :courseid
           ORDER BY c.sortorder, c.name", ['courseid' => $course->id]);

        sort($removed);
        return [
            'added' => array_values($addids),
            'removed' => $removed,
            'competencies' => array_values($stored),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'added' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Competency name added to the course')),
            'removed' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Competency name removed from the course')),
            'competencies' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Competency name the course now aims at, read back')),
        ]);
    }
}
