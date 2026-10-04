<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Hourly: bring every ltct: course's course mentors into step (spec 008, research R10), and
 * every pathway's cohort enrolments (research R11).
 *
 * The observers keep course mentors in step as their reasons start and end; this repairs
 * anything they missed: a change made while an observer failed, a role or group changed by
 * hand, a course whose idnumber changed. It also takes away any Teacher assignment local_ltuse
 * gave in a course that is no longer an ltct: course, and deletes course-mentor records whose
 * course, cohort or people are gone. The course-mentor part does nothing while
 * local_ltuse/coursementorsync is 0 (plan decision 11).
 *
 * The pathway part runs whatever that setting says, because it is not about course mentors:
 * spec 006 fires no event when a course on a pathway is shown again, so this is how such a
 * course is enrolled for the cohorts holding the pathway. It does nothing until spec 006 is
 * installed.
 *
 * It logs counts only (constitution III).
 */
class course_mentor_reconcile extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task:coursementorreconcile', 'local_ltuse');
    }

    public function execute() {
        $counts = \local_ltuse\admin\course_mentor_sync::reconcile();
        if (!$counts) {
            mtrace('local_ltuse: course-mentor sync is switched off (local_ltuse/coursementorsync); nothing to reconcile');
        } else {
            mtrace('local_ltuse: course mentors reconciled: ' . self::describe($counts));
        }
        $pathways = \local_ltuse\admin\cohort_enrolment::reconcile_pathways();
        mtrace('local_ltuse: pathway enrolments reconciled: ' . self::describe($pathways));
    }

    /**
     * @param array $counts what => n
     * @return string "what n, what n"
     */
    protected static function describe(array $counts): string {
        $parts = [];
        foreach ($counts as $what => $n) {
            $parts[] = "{$what} {$n}";
        }
        return implode(', ', $parts);
    }
}
