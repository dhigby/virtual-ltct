<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

use context_course;
use context_module;
use core_courseformat\formatactions;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\util;
use moodle_exception;

/**
 * Retire the modules a course no longer has: hide them, in a section of their own.
 *
 * When a lesson is renamed, renumbered or removed in the repo, its old module is still in
 * Moodle under an idnumber the publisher no longer produces. Left alone it stays visible
 * beside its replacement. The publisher works out which idnumbers those are, from
 * get_course_manifest, and sends them here.
 *
 * HIDDEN, NOT DELETED. A learner's completed attempt and grade live on the module; deleting
 * it would take them away, and a mistaken publish could not be undone. A hidden module is
 * out of learners' view and still readable by a teacher. If the repo brings the file back,
 * the next publish finds the module by its idnumber, moves it home and shows it again
 * (util::upsert_module).
 *
 * MOVED TO THE RETIRED SECTION. Hidden in place, an old copy still sits beside its
 * replacement, and a teacher walking the course with Next activity lands on it between
 * lessons (Next activity offers whatever the viewer can open, hidden or not). So each one
 * is moved into one hidden section at the end of the course, util::RETIRED_SECTION_NAME,
 * created on first need. The publisher sets the course's hidden sections to "Hide
 * completely", so learners never see even its name.
 *
 * The hide is what core's own hide action does (core_course_external::edit_module,
 * 'hide'): set_coursemodule_visible(), then the course_module_updated event it leaves to
 * the caller. The move is cmactions::move_end_section(), core's 5.2 replacement for the
 * deprecated moveto_module(). Every idnumber is checked before anything changes, so a bad
 * request changes nothing.
 */
class hide_modules extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
            'idnumbers' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Course-module idnumber to retire'),
                'Modules to retire', VALUE_DEFAULT, []),
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
        require_capability('moodle/course:manageactivities', $context);
        require_capability('moodle/course:update', $context);   // may create the section

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
            // The certificate is never retired: retiring is a step towards deleting it, and
            // deleting it deletes every issued code (spec 013 R14).
            if ($idnumber === $course->idnumber . \local_ltuse\siteconfig\certtemplate::IDNUMBER_SUFFIX) {
                throw new moodle_exception('error:nohidecertificate', 'local_ltuse', '', $idnumber);
            }
        }

        $results = [];
        $retired = null;
        foreach ($params['idnumbers'] as $idnumber) {
            $m = $owned[$idnumber];
            $retired = $retired ?? util::ensure_retired_section($course);
            $inplace = $m['section'] === (int)$retired->section;
            if ($inplace && !$m['visible']) {
                $results[] = ['idnumber' => $idnumber, 'cmid' => $m['cmid'],
                              'outcome' => 'alreadyretired'];
                continue;
            }
            if (!$inplace) {
                formatactions::cm($course)->move_end_section($m['cmid'], (int)$retired->id);
            }
            // Moving into a hidden section already hides a visible module. Say so
            // explicitly anyway: a teacher may have shown the Retired section.
            set_coursemodule_visible($m['cmid'], 0);
            $cm = get_coursemodule_from_id('', $m['cmid'], $course->id, false, MUST_EXIST);
            \core\event\course_module_updated::create_from_cm(
                $cm, context_module::instance($m['cmid']))->trigger();
            $results[] = ['idnumber' => $idnumber, 'cmid' => $m['cmid'],
                          'outcome' => 'retired'];
        }
        return ['modules' => $results];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'modules' => new external_multiple_structure(
                new external_single_structure([
                    'idnumber' => new external_value(PARAM_RAW, 'Course-module idnumber'),
                    'cmid' => new external_value(PARAM_INT, 'Course-module id'),
                    'outcome' => new external_value(PARAM_ALPHA, 'retired or alreadyretired'),
                ])
            ),
        ]);
    }
}
