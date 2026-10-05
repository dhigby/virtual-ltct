<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_coursecat;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_ltuse\organisation\access;
use local_ltuse\util;
use moodle_exception;

/**
 * Put a published course in the category it belongs to, by idnumber (spec 002 amendment
 * 2026-10-02, research R11).
 *
 * The publisher calls this on every publish of an organisation-only course (declared in
 * moodle/site/org-courses.yaml), with the course's ltct:<slug> and its organisation's
 * ltct:org:<key>, so its placement is re-asserted each time. Core's own route needs more than
 * the publisher should hold: core_course_get_categories by idnumber needs
 * moodle/category:manage at system context, and core_course_update_courses checks
 * moodle/course:changecategory in the course only, never the target (course/externallib.php).
 * So this checks the target itself: only ltct:published, ltct:pilots or ltct:org:<key>
 * (access::is_placement_category()), resolved by course_categories.idnumber (not indexed in
 * core; the plugin README lists it), and local/ltuse:publish in both the course and the target.
 *
 * Moves only when the course is elsewhere, with move_courses() (course/lib.php:1548), which
 * fires course_updated and hides the course if the target category is hidden. No user data is
 * read or written.
 */
class place_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber, ltct:<slug>'),
            'categoryidnumber' => new external_value(PARAM_RAW,
                'Target category idnumber: ltct:org:<key>, ltct:pilots or ltct:published'),
        ]);
    }

    public static function execute(string $courseidnumber, string $categoryidnumber): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
            'categoryidnumber' => $categoryidnumber,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);
        if (strpos((string)$course->idnumber, util::IDNUMBER_PREFIX) !== 0) {
            throw new moodle_exception('error:notltctcourse', 'local_ltuse', '', (string)$course->idnumber);
        }

        if (!access::is_placement_category($params['categoryidnumber'])) {
            throw new invalid_parameter_exception(
                "category '{$params['categoryidnumber']}' is not one a course may be placed in");
        }
        // An idnumber should name one category; refuse to guess if it names several.
        $categories = $DB->get_records('course_categories', ['idnumber' => $params['categoryidnumber']], 'id', 'id');
        if (count($categories) !== 1) {
            throw new moodle_exception('error:nocategoryidnumber', 'local_ltuse', '', $params['categoryidnumber']);
        }
        $categoryid = (int)reset($categories)->id;
        require_capability('local/ltuse:publish', context_coursecat::instance($categoryid));

        if ((int)$course->category === $categoryid) {
            return ['moved' => false];
        }
        if (!move_courses([(int)$course->id], $categoryid)) {
            throw new moodle_exception('error:nocategoryidnumber', 'local_ltuse', '', $params['categoryidnumber']);
        }
        return ['moved' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'moved' => new external_value(PARAM_BOOL, 'Whether the course was moved'),
        ]);
    }
}
