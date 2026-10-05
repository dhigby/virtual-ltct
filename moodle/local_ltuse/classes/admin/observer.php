<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for the administration tooling (spec 008), registered in db/events.php in
 * the block headed "Spec 008". Kept apart from \local_ltuse\observer so each spec's observers
 * are found in one place.
 *
 * The course-mentor observers (research R10, contracts/admin-service.md) keep course mentors
 * in step in the same request as the change that ends or starts their reason, because a
 * course-mentor enrolment that outlives its reason would keep showing a protected learner's
 * real identity (spec 016 FR-006). The hourly task\course_mentor_reconcile is the backstop,
 * not the mechanism. Each one:
 *
 *   - does nothing while local_ltuse/coursementorsync is 0;
 *   - ignores events about the course-mentor instance itself, so the sync never triggers
 *     itself, and never waits on its own course lock;
 *   - filters on the course and the instance, never on the user's role at event time: core
 *     assigns the role after user_enrolment_created and removes it before
 *     user_enrolment_deleted;
 *   - never throws into core: a failure is reported with debugging(), the change stands, and
 *     the reconcile repairs it within the hour.
 */
class observer {

    /**
     * Spec 006: courses joined or left a pathway (research R11). Every cohort holding the
     * pathway with enrol = 1 is enrolled in each added course, under enrolment_rules. A
     * removed course unenrols nobody; the summary reports it.
     *
     * The event's other is {pathwaykey: string, added: int[], removed: int[]}, fired after
     * commit. Type-hinted as the base class, so this file loads before spec 006 is installed.
     *
     * @param \core\event\base $event \local_ltuse\event\pathway_courses_changed
     */
    public static function pathway_courses_changed(\core\event\base $event): void {
        $other = $event->other ?? [];
        $key = (string)($other['pathwaykey'] ?? '');
        $added = array_values(array_filter(array_map('intval', (array)($other['added'] ?? []))));
        if ($key === '' || !$added) {
            return;
        }
        cohort_enrolment::pathway_courses_added($key, $added);
    }

    // --- course mentors (research R10) -------------------------------------------------------

    /**
     * A mentor relationship started or ended (spec 003): the mentor role in a learner's user
     * context. The learner owns the context; the mentor is the user who holds the role.
     *
     * @param \core\event\base $event \core\event\role_assigned or role_unassigned
     */
    public static function mentor_role_changed(\core\event\base $event): void {
        try {
            if (!course_mentor_sync::enabled()) {
                return;
            }
            $context = $event->get_context();
            if (!$context || (int)$context->contextlevel !== CONTEXT_USER) {
                return;
            }
            $mentorroleid = (int)(course_mentor_sync::role_ids()[course_mentor_sync::ROLE_MENTOR] ?? 0);
            if (!$mentorroleid || (int)$event->objectid !== $mentorroleid) {
                return;
            }
            course_mentor_sync::sync_learner((int)$context->instanceid);
            course_mentor_sync::sync_mentor((int)$event->relateduserid);
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not sync course mentors: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * A user enrolment was made, changed (suspended, reactivated, dates) or removed in an
     * ltct: course, through any method but the course-mentor instance.
     *
     * @param \core\event\base $event \core\event\user_enrolment_created, _updated or _deleted
     */
    public static function user_enrolment_changed(\core\event\base $event): void {
        global $DB;
        try {
            if (!course_mentor_sync::enabled() || !self::is_ltct_course((int)$event->courseid)) {
                return;
            }
            if ($event instanceof \core\event\user_enrolment_deleted) {
                $enrolid = (int)($event->other['userenrolment']['enrolid'] ?? 0);
            } else {
                $enrolid = (int)$DB->get_field('user_enrolments', 'enrolid', ['id' => $event->objectid]);
            }
            if ($enrolid && course_mentor_sync::is_own_instance(
                    $DB->get_record('enrol', ['id' => $enrolid], 'id, enrol, customchar1') ?: null)) {
                return;
            }
            course_mentor_sync::sync_course((int)$event->courseid);
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not sync course mentors: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * An enrolment instance in an ltct: course was changed or deleted. Disabling an instance
     * fires no per-user event, so this is the only signal that its learners stopped counting.
     *
     * @param \core\event\base $event \core\event\enrol_instance_updated or enrol_instance_deleted
     */
    public static function enrol_instance_changed(\core\event\base $event): void {
        try {
            if (!course_mentor_sync::enabled() || !self::is_ltct_course((int)$event->courseid)) {
                return;
            }
            // create_from_record() attaches the record, so this works after a delete too.
            $instance = $event->get_record_snapshot('enrol', $event->objectid);
            if (course_mentor_sync::is_own_instance($instance ?: null)) {
                return;
            }
            course_mentor_sync::sync_course((int)$event->courseid);
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not sync course mentors: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Any account was updated. Suspending an account ends every enrolment's reason without
     * touching an enrolment, and the event does not say which fields changed, so every update
     * syncs that person's courses: cheap, and idempotent.
     *
     * @param \core\event\base $event \core\event\user_updated
     */
    public static function user_updated(\core\event\base $event): void {
        try {
            if (!course_mentor_sync::enabled()) {
                return;
            }
            course_mentor_sync::sync_learner((int)($event->relateduserid ?: $event->objectid));
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not sync course mentors: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * @param int $courseid
     * @return bool the course is one the course-mentor sync looks after
     */
    protected static function is_ltct_course(int $courseid): bool {
        global $DB;
        if ($courseid <= 0) {
            return false;
        }
        return course_mentor_sync::is_ltct_course((string)$DB->get_field('course', 'idnumber', ['id' => $courseid]));
    }
}
