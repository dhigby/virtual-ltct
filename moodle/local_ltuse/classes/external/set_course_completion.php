<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// completion_info, completion_aggregation, completion_completion and the COMPLETION_*
// constants (completionlib require_once()s the aggregation, criteria and completion classes).
require_once($CFG->libdir . '/completionlib.php');
// Not autoloaded, and completionlib does not include it: course/completion.php includes it
// itself, as this file must (MOODLE_502_STABLE, course/completion.php:34).
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');

use completion_aggregation;
use completion_completion;
use completion_criteria_activity;
use completion_info;
use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\criteria_diff;
use local_ltuse\util;
use moodle_exception;

/**
 * Keep a course's completion criteria in step with the modules it publishes (spec 004, R3).
 *
 * A course is complete when every visible lesson and quiz it publishes is complete. So its
 * activity criteria are exactly the visible, tracked modules with an `ltct:<slug>:`
 * idnumber (an untracked one can never be complete; see the wanted set below), and
 * this function moves the criteria to that set one row at a time.
 *
 * WHY NOT CORE'S ROUTE. Moodle has no API for changing course completion criteria. Its only
 * implementation, the course completion settings form (course/completion.php), starts by
 * clearing every criterion, and that deletes every learner's course completion in the
 * course, completed ones included. A republish that added one lesson would erase every
 * recorded completion. This function never takes that route, and tests/criteria_harness.php
 * fails CI if this file ever names either of core's wipe functions.
 *
 * What it does, in order (contracts/publish.md):
 *   1. requires completion on for the site and the course;
 *   2. computes wanted and present, and their diff (local_ltuse\criteria_diff);
 *   3. inserts each added criterion and deletes each removed one, through
 *      completion_criteria_activity, the data object core's own form writes;
 *   4. sets overall and activity aggregation to ALL;
 *   5. if a criterion was removed, flags every incomplete course completion for
 *      re-aggregation, so a learner who has now done everything is marked complete by the
 *      next completion_regular_task (every minute). A completed row is never touched;
 *   6. if anything changed, fires course_completion_updated, as the form does.
 *
 * PRESENT is read from course_completion_criteria directly. completion_info::get_criteria()
 * drops rows whose module is gone, so they would never be removed, and
 * completion_criteria_activity::fetch() throws on the duplicate rows the table allows.
 *
 * OTHER CRITERIA (date, role, self, grade, ...) are left alone, and reported through
 * `othercriteria` so a hand-added one is visible to the publisher, never deleted.
 *
 * Returns no user data: `reaggregated` is a count.
 */
class set_course_completion extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
        ]);
    }

    public static function execute(string $courseidnumber): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);
        require_capability('moodle/course:update', $context);

        // Only a publisher-owned course: its idnumber is what scopes the wanted set.
        if (strpos($course->idnumber, util::IDNUMBER_PREFIX) !== 0) {
            throw new moodle_exception('error:notltctcourse', 'local_ltuse', '',
                $course->idnumber);
        }
        // Criteria are inert with completion off, and so is everything a learner would see.
        if (!(new completion_info($course))->is_enabled()) {
            throw new moodle_exception('error:completionoff', 'local_ltuse', '',
                $course->idnumber);
        }

        // Wanted: every visible module of this course's own. Hidden ones are what the
        // publisher hid because the repo dropped them; core has no visibility test in
        // completion, so a hidden criterion would stay required and nobody could finish.
        // The question bank ("ltct:qbank") is plumbing, and never matches the prefix. The
        // certificate ("ltct:<slug>:certificate", spec 013 R8) unlocks on course completion, so
        // as a criterion it would stop the course from ever completing.
        $prefix = $course->idnumber . ':';
        $certificate = $course->idnumber . \local_ltuse\siteconfig\certtemplate::IDNUMBER_SUFFIX;
        $modnames = [];
        foreach (util::owned_modules((int)$course->id) as $idnumber => $m) {
            if (strpos($idnumber, $prefix) === 0 && $m['visible'] && $m['modname'] !== 'qbank'
                    && $idnumber !== $certificate) {
                $modnames[$m['cmid']] = $m['modname'];
            }
        }
        // ...and tracked. An untracked module (set "not tracked" by hand, or created while
        // completion was off) can never be complete, so as a criterion it would block the
        // course for everyone. Worse, it would be a trap: the module form unlocks completion
        // on a module with no learner data, and update_moduleinfo() then calls
        // reset_all_state() -> delete_all_state(), which for an activity criterion deletes
        // every course_completions row in the course, completed ones included
        // (lib/completionlib.php, MOODLE_502_STABLE). Left out of wanted, an existing
        // criterion for it is removed below, which also lets the next publish's
        // create_page/create_quiz set its rule (util::completion_outcome() returns 'set'
        // only for an untracked module that is not a criterion); the publish after that
        // makes it a criterion.
        if ($modnames) {
            $tracking = $DB->get_records_list('course_modules', 'id', array_keys($modnames),
                '', 'id, completion');
            foreach (array_keys($modnames) as $cmid) {
                if (!isset($tracking[$cmid])
                        || (int)$tracking[$cmid]->completion === COMPLETION_TRACKING_NONE) {
                    unset($modnames[$cmid]);
                }
            }
        }

        // Present: read by (course, criteriatype), course being the table's index.
        $rows = $DB->get_records('course_completion_criteria',
            ['course' => $course->id, 'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY]);
        $present = array_map(function($r) {
            return (int)$r->moduleinstance;
        }, $rows);
        $othercriteria = $DB->record_exists_select('course_completion_criteria',
            'course = :course AND criteriatype <> :activity',
            ['course' => $course->id, 'activity' => COMPLETION_CRITERIA_TYPE_ACTIVITY]);

        $diff = criteria_diff::diff(array_keys($modnames), $present);

        foreach ($diff['add'] as $cmid) {
            // What completion_criteria_activity::update_config() writes: `module` is the
            // module's name and `moduleinstance` is the cmid, never cm.instance. No fetch,
            // because there is nothing to find.
            $criterion = new completion_criteria_activity([
                'course' => $course->id,
                'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
                'module' => $modnames[$cmid],
                'moduleinstance' => $cmid,
            ], false);
            $criterion->insert();
        }

        // Every row for a removed cmid goes, duplicates included. delete() removes the
        // criterion row only; its criterion completions are left and are ignored by
        // aggregation, which joins from course_completion_criteria.
        $removeset = array_flip($diff['remove']);
        foreach ($rows as $row) {
            if (isset($removeset[(int)$row->moduleinstance])) {
                (new completion_criteria_activity((array)$row, false))->delete();
            }
        }

        // Overall (criteriatype null) and activity aggregation, as course/completion.php
        // sets them. The constructor loads the existing row (unique on course and type).
        // get_aggregation_method() would say ALL for a missing row, so it is not used.
        $aggregation = 'unchanged';
        foreach ([null, COMPLETION_CRITERIA_TYPE_ACTIVITY] as $type) {
            $agg = new completion_aggregation(['course' => $course->id, 'criteriatype' => $type]);
            if (empty($agg->id) || (int)$agg->method !== COMPLETION_AGGREGATION_ALL) {
                $agg->setMethod(COMPLETION_AGGREGATION_ALL);
                $agg->save();
                $aggregation = 'set';
            }
        }

        // A removed criterion can leave a learner with everything else done, and nothing
        // re-checks them on its own. Core's pattern (completion_daily_task): flag the row,
        // then save it through the data object, which also refreshes the cache. Adding a
        // criterion needs no flag: nobody incomplete can have become complete by it.
        $reaggregated = 0;
        if ($diff['remove']) {
            $now = time();
            $rs = $DB->get_recordset_select('course_completions',
                'course = :course AND timecompleted IS NULL', ['course' => $course->id],
                '', 'id, userid');
            foreach ($rs as $r) {
                $cc = new completion_completion(['userid' => $r->userid, 'course' => $course->id]);
                if (!empty($cc->id) && empty($cc->timecompleted)) {
                    $cc->reaggregate = $now;
                    $cc->mark_enrolled();
                    $reaggregated++;
                }
            }
            $rs->close();
        }

        if ($diff['add'] || $diff['remove'] || $aggregation === 'set') {
            \core\event\course_completion_updated::create([
                'courseid' => $course->id,
                'context' => $context,
            ])->trigger();
        }

        return [
            'added' => $diff['add'],
            'removed' => $diff['remove'],
            'aggregation' => $aggregation,
            'reaggregated' => $reaggregated,
            'othercriteria' => $othercriteria,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'added' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course-module id now a criterion')),
            'removed' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course-module id no longer a criterion')),
            'aggregation' => new external_value(PARAM_ALPHA, 'unchanged or set'),
            'reaggregated' => new external_value(PARAM_INT,
                'Incomplete course completions flagged for re-aggregation (a count)'),
            'othercriteria' => new external_value(PARAM_BOOL,
                'True if the course has criteria other than activities, left untouched'),
        ]);
    }
}
