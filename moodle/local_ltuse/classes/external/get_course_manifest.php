<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_module;
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
 * FILES. Each page also lists the files in its mod_page/content area with their
 * contenthash (the SHA-1 of the bytes). The publisher compares that with the SHA-1 of each
 * image it is about to send, and uploads only the ones that differ (spec 009, FR-016):
 * re-sending an unchanged image gives it a new timemodified, which makes the Moodle app
 * download it again on a learner's metered connection.
 *
 * Cheap: two indexed queries, plus one file-area read per page. Read-only.
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

        $fs = get_file_storage();
        $retired = util::retired_section($course);
        $modules = [];
        foreach (util::owned_modules((int)$course->id) as $mid => $m) {
            $files = [];
            if ($m['modname'] === 'page') {
                $ctx = context_module::instance($m['cmid']);
                foreach ($fs->get_area_files($ctx->id, 'mod_page', 'content', 0, 'filename', false)
                         as $f) {
                    $files[] = [
                        'filename' => $f->get_filename(),
                        'contenthash' => $f->get_contenthash(),
                        'filesize' => (int)$f->get_filesize(),
                    ];
                }
            }
            $modules[] = [
                'idnumber' => $mid,
                'cmid' => $m['cmid'],
                'modname' => $m['modname'],
                'section' => $m['section'],
                'instance' => $m['instance'],
                'visible' => $m['visible'],
                'retired' => $retired !== null && $m['section'] === (int)$retired->section,
                'files' => $files,
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
                    'retired' => new external_value(PARAM_BOOL,
                        'In the Retired section: hidden because the repo no longer has it',
                        VALUE_DEFAULT, false),
                    'files' => new external_multiple_structure(
                        new external_single_structure([
                            'filename' => new external_value(PARAM_FILE, 'File name'),
                            'contenthash' => new external_value(PARAM_ALPHANUM,
                                'SHA-1 of the file content'),
                            'filesize' => new external_value(PARAM_INT, 'Size in bytes'),
                        ]),
                        'Files in the content area of a page; empty for any other module',
                        VALUE_DEFAULT, []
                    ),
                ])
            ),
        ]);
    }
}
