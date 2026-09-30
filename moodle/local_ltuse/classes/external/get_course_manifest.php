<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\util;

/**
 * Return the repo-to-Moodle map for one course.
 *
 * This is the function the user's original three did not include, and the publish is not
 * idempotent without it: core_course_get_contents does not reliably return a module's
 * idnumber, so there is otherwise no way to ask Moodle "which of these modules did I
 * publish, and what are they now?". With it, a republish is a diff -- fetch the manifest,
 * compare against the payload, create what is new, update what changed, hide what the
 * course no longer has -- rather than a blind re-create that duplicates everything.
 *
 * Cheap: two indexed queries.
 */
class get_course_manifest extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'idnumber' => new external_value(PARAM_RAW,
                'The course idnumber, e.g. ltct:coretech-computer-hardware'),
        ]);
    }

    public static function execute(string $idnumber): array {
        global $DB;

        ['idnumber' => $idnumber] = self::validate_parameters(
            self::execute_parameters(), ['idnumber' => $idnumber]);

        $course = util::course_by_idnumber($idnumber);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);

        $sections = [];
        foreach ($DB->get_records('course_sections', ['course' => $course->id],
                                  'section ASC') as $s) {
            $sections[] = [
                'number' => (int)$s->section,
                'name' => (string)($s->name ?? ''),
                'visible' => (int)$s->visible,
            ];
        }

        $modules = [];
        foreach (util::owned_modules((int)$course->id) as $mid => $m) {
            $modules[] = [
                'idnumber' => $mid,
                'cmid' => $m['cmid'],
                'modname' => $m['modname'],
                'section' => $m['section'],
                'instance' => $m['instance'],
                'visible' => $m['visible'],
            ];
        }

        return [
            'courseid' => (int)$course->id,
            'shortname' => $course->shortname,
            'fullname' => $course->fullname,
            'idnumber' => $course->idnumber,
            'sections' => $sections,
            'modules' => $modules,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'shortname' => new external_value(PARAM_RAW, 'Course short name'),
            'fullname' => new external_value(PARAM_RAW, 'Course full name'),
            'idnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'number' => new external_value(PARAM_INT, 'Section number'),
                    'name' => new external_value(PARAM_RAW, 'Section name'),
                    'visible' => new external_value(PARAM_INT, 'Whether it is visible'),
                ])
            ),
            'modules' => new external_multiple_structure(
                new external_single_structure([
                    'idnumber' => new external_value(PARAM_RAW, 'Course-module idnumber'),
                    'cmid' => new external_value(PARAM_INT, 'Course-module id'),
                    'modname' => new external_value(PARAM_PLUGIN, 'Activity module name'),
                    'section' => new external_value(PARAM_INT, 'Section number'),
                    'instance' => new external_value(PARAM_INT, 'Activity instance id'),
                    'visible' => new external_value(PARAM_INT, 'Whether it is visible'),
                ])
            ),
        ]);
    }
}
