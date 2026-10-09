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
 *
 * Spec 007 R10 adds each section's summary: the lesson's Estimated time line, sent by the
 * publisher (contracts/update-sections.md). A name or a summary is written only when it
 * differs from what is stored, so an unchanged republish writes no section. An absent
 * summary is left untouched, which keeps callers that predate it working; an empty one
 * clears it.
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
                    'summary' => new external_value(PARAM_RAW,
                        'section summary HTML, FORMAT_HTML; absent leaves it untouched', VALUE_OPTIONAL),
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

        // Name and summary are decided separately, and each is written only when it differs
        // from what is stored. Stored values are compared as strings, so a NULL equals ''.
        // validate_parameters() keeps an absent VALUE_OPTIONAL key absent, so
        // array_key_exists() tells "leave the summary" from "clear it".
        $renamed = 0;
        $summaries = 0;
        foreach ($params['sections'] as $s) {
            $hassummary = array_key_exists('summary', $s);
            if ($s['name'] === '' && !$hassummary) {
                continue;
            }
            $record = $DB->get_record('course_sections',
                ['course' => $course->id, 'section' => $s['number']]);
            if (!$record) {
                continue;
            }
            $data = [];
            if ($s['name'] !== '' && $s['name'] !== (string)$record->name) {
                $data['name'] = $s['name'];
            }
            if ($hassummary && ($s['summary'] !== (string)$record->summary
                    || (int)$record->summaryformat !== (int)FORMAT_HTML)) {
                $data['summary'] = $s['summary'];
                $data['summaryformat'] = FORMAT_HTML;
            }
            if (!$data) {
                continue;
            }
            course_update_section($course, $record, (object)$data);
            $renamed += isset($data['name']) ? 1 : 0;
            $summaries += isset($data['summary']) ? 1 : 0;
        }

        $after = (int)$DB->get_field_sql(
            "SELECT COALESCE(MAX(section), 0) FROM {course_sections} WHERE course = ?",
            [$course->id]);

        return [
            'courseid' => (int)$course->id,
            'sectionsbefore' => $before,
            'sectionsafter' => $after,
            'renamed' => $renamed,
            'summaries' => $summaries,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionsbefore' => new external_value(PARAM_INT, 'Highest section number before'),
            'sectionsafter' => new external_value(PARAM_INT, 'Highest section number after'),
            'renamed' => new external_value(PARAM_INT, 'names actually written'),
            'summaries' => new external_value(PARAM_INT, 'summaries actually written'),
        ]);
    }
}
