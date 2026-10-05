<?php
namespace local_ltuse\pathway;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\mentoring;

/**
 * One learner's status in each course of a pathway (spec 006, FR-010, research R7).
 *
 * A pathway course is in one of three states, read from core every time and never stored:
 *
 *   completed   course_completions.timecompleted is set for the learner and course
 *   inprogress  enrolled, not completed
 *   notstarted  not enrolled and not completed
 *
 * Completion is core course completion, the same record spec 004 counts and spec 003's
 * Mentoring page shows. The state itself is decided by mentoring::progress_status(), so a
 * pathway and the Mentoring page cannot disagree about what "completed" means: a completion
 * date wins over everything, and a course finished before its enrolment was removed stays
 * completed. Enrolment stands in for begun: a pathway only asks whether the learner has
 * started a course, so any enrolment counts as progress and none counts as not started.
 *
 * state() is pure. for_courses() gathers its inputs with two raw reads of stable core tables
 * by indexed columns (listed in the plugin README, constitution XI): user_enrolments joined to
 * enrol, and course_completions. Nothing here writes, and nothing reads grades, attempts,
 * submissions or logs.
 */
class progress {

    /** The three states a pathway course can show, named as mentoring names them. */
    const COMPLETED = mentoring::COMPLETED;
    const IN_PROGRESS = mentoring::IN_PROGRESS;
    const NOT_STARTED = mentoring::NOT_STARTED;

    /**
     * One course's state for one learner. Pure.
     *
     * An enrolment of any status counts, active or suspended, as on the Mentoring page: a
     * suspended learner has still begun the course.
     *
     * @param bool $enrolled the learner has an enrolment in the course
     * @param int|null $timecompleted course_completions.timecompleted, null when not complete
     * @return string one of COMPLETED, IN_PROGRESS, NOT_STARTED
     */
    public static function state(bool $enrolled, ?int $timecompleted): string {
        // Tracked is always true here: a pathway shows no "not tracked" state, and a course
        // without completion tracking can only ever be in progress for an enrolled learner.
        $status = mentoring::progress_status(true, $timecompleted ?: null, $enrolled ? 100.0 : null);
        return $status['state'];
    }

    /**
     * A learner's state in each of a set of courses.
     *
     * @param int $userid the learner
     * @param int[] $courseids the pathway's course ids
     * @return string[] course id => one of COMPLETED, IN_PROGRESS, NOT_STARTED, for every id
     *                  given, in the order given
     */
    public static function for_courses(int $userid, array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if (!$courseids || $userid <= 0) {
            return array_fill_keys($courseids, self::NOT_STARTED);
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'pc');
        $params['userid'] = $userid;

        $enrolled = $DB->get_fieldset_sql(
            "SELECT DISTINCT e.courseid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.courseid $insql", $params);
        $enrolled = array_flip(array_map('intval', $enrolled));

        $completed = $DB->get_records_select_menu('course_completions',
            "userid = :userid AND course $insql AND timecompleted IS NOT NULL", $params, '',
            'course, timecompleted');

        $states = [];
        foreach ($courseids as $courseid) {
            $states[$courseid] = self::state(isset($enrolled[$courseid]),
                isset($completed[$courseid]) ? (int)$completed[$courseid] : null);
        }
        return $states;
    }
}
