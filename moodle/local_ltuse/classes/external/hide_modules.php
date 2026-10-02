<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

use context_course;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\util;
use moodle_exception;

/**
 * Hide the modules a course no longer has.
 *
 * When a lesson is renamed, renumbered or removed in the repo, its old module is still in
 * Moodle under an idnumber the publisher no longer produces. Left alone it stays visible
 * beside its replacement. The publisher works out which idnumbers those are, from
 * get_course_manifest, and sends them here.
 *
 * HIDDEN, NOT DELETED. A learner's completed attempt and grade live on the module; deleting
 * it would take them away, and a mistaken publish could not be undone. A hidden module is
 * out of learners' view and still readable by a teacher. If the repo brings the file back,
 * the next publish finds the module by its idnumber and shows it again.
 *
 * Exactly what core's own hide action does (core_course_external::edit_module, 'hide'):
 * set_coursemodule_visible(), then the course_module_updated event it leaves to the
 * caller. Every idnumber is checked before anything is hidden, so a bad request changes
 * nothing.
 */
class hide_modules extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
            'idnumbers' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Course-module idnumber to hide'),
                'Modules to hide', VALUE_DEFAULT, []),
        ]);
    }

    public static function execute(string $courseidnumber, array $idnumbers = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
            'idnumbers' => $idnumbers,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);
        require_capability('moodle/course:activityvisibility', $context);

        // Resolve every one first: a name that isn't a module of this course is a
        // publisher bug, and must not leave the course half-changed.
        $owned = util::owned_modules((int)$course->id);
        foreach ($params['idnumbers'] as $idnumber) {
            if (!isset($owned[$idnumber])) {
                throw new moodle_exception('error:nomodule', 'local_ltuse', '', $idnumber);
            }
            // The course's question bank is the publisher's own plumbing, not content.
            if ($owned[$idnumber]['modname'] === 'qbank') {
                throw new moodle_exception('error:nohideqbank', 'local_ltuse', '', $idnumber);
            }
        }

        $results = [];
        foreach ($params['idnumbers'] as $idnumber) {
            $m = $owned[$idnumber];
            if (!$m['visible']) {
                $results[] = ['idnumber' => $idnumber, 'cmid' => $m['cmid'],
                              'outcome' => 'alreadyhidden'];
                continue;
            }
            set_coursemodule_visible($m['cmid'], 0);
            $cm = get_coursemodule_from_id('', $m['cmid'], $course->id, false, MUST_EXIST);
            \core\event\course_module_updated::create_from_cm(
                $cm, context_module::instance($m['cmid']))->trigger();
            $results[] = ['idnumber' => $idnumber, 'cmid' => $m['cmid'],
                          'outcome' => 'hidden'];
        }
        return ['modules' => $results];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'modules' => new external_multiple_structure(
                new external_single_structure([
                    'idnumber' => new external_value(PARAM_RAW, 'Course-module idnumber'),
                    'cmid' => new external_value(PARAM_INT, 'Course-module id'),
                    'outcome' => new external_value(PARAM_ALPHA, 'hidden or alreadyhidden'),
                ])
            ),
        ]);
    }
}
