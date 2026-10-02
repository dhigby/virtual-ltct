<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\util;

/**
 * Create a course's sections and name them.
 *
 * This was originally going to be local_wsmanagesections' job -- it covers section
 * create/move/update over REST and the plan deliberately avoided duplicating it. In the
 * event it is not installable here, so this is the fallback the plan anticipated, and it
 * turns out to be better: the publisher now has no third-party plugin on its critical
 * path, and there is one less thing to re-verify on every Moodle upgrade.
 *
 * Core has no equivalent web service. core_course_edit_section only performs the
 * hide/show/highlight actions, and core_courseformat_update_course is the course editor's
 * internal AJAX contract rather than something to build on. But the underlying PHP is
 * core and stable: course_create_sections_if_missing() and course_update_section() are
 * exactly what the web UI calls.
 *
 * Idempotent. Sections already present are left alone and simply renamed, so a republish
 * never appends a second set.
 */
class update_sections extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
            'numsections' => new external_value(PARAM_INT,
                'Ensure the course has at least this many numbered sections'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'number' => new external_value(PARAM_INT, 'Section number'),
                    'name' => new external_value(PARAM_TEXT, 'Section name'),
                ]),
                'Sections to name', VALUE_DEFAULT, []
            ),
        ]);
    }

    public static function execute(string $courseidnumber, int $numsections,
                                   array $sections = []): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
            'numsections' => $numsections,
            'sections' => $sections,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);
        require_capability('moodle/course:update', $context);

        $before = (int)$DB->get_field_sql(
            "SELECT COALESCE(MAX(section), 0) FROM {course_sections} WHERE course = ?",
            [$course->id]);

        // A course that gained a lesson may now need the Retired section's number: move
        // that section past the lessons first, so it is never named as one.
        util::keep_retired_last($course, $params['numsections']);

        // Section 0 is the course's General area and always exists; range() from 0 keeps
        // the numbering aligned with the payload, where lesson N is section N.
        if ($params['numsections'] > $before) {
            course_create_sections_if_missing($course, range(0, $params['numsections']));
        }

        $renamed = 0;
        foreach ($params['sections'] as $s) {
            if ($s['name'] === '') {
                continue;
            }
            $record = $DB->get_record('course_sections',
                ['course' => $course->id, 'section' => $s['number']]);
            if (!$record) {
                continue;
            }
            course_update_section($course, $record, (object)['name' => $s['name']]);
            $renamed++;
        }

        $after = (int)$DB->get_field_sql(
            "SELECT COALESCE(MAX(section), 0) FROM {course_sections} WHERE course = ?",
            [$course->id]);

        return [
            'courseid' => (int)$course->id,
            'sectionsbefore' => $before,
            'sectionsafter' => $after,
            'renamed' => $renamed,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionsbefore' => new external_value(PARAM_INT, 'Highest section number before'),
            'sectionsafter' => new external_value(PARAM_INT, 'Highest section number after'),
            'renamed' => new external_value(PARAM_INT, 'How many sections were named'),
        ]);
    }
}
