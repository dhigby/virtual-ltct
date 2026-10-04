<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

use core_message\api;

/**
 * Message contacts for mentor relationships (spec 003, research R5).
 *
 * Core decides who may message whom without ever checking a capability in the recipient's
 * user context, so the mentor role alone cannot let a mentor reach a learner who shares no
 * course with them, or who accepts messages only from contacts. Contacts can. So:
 *
 *   role_assigned    mentor role, in a learner's user context: make the two contacts, unless
 *                    they already are, and record that this plugin made the contact
 *   role_unassigned  when no mentor assignment links the pair any more, remove the contact,
 *                    but only one this plugin made
 *   user_deleted     forget that user's records and the contacts they stood for. Deleting a
 *                    learner removes their user-context assignments without role_unassigned
 *
 * A learner's block of one person is never touched: core checks it before contacts, so it
 * still wins. A contact a learner removes is not made again. Nothing here throws into core:
 * a failure is reported with debugging() and the role change stands.
 *
 * Public APIs only: \core_message\api::is_contact(), add_contact(), remove_contact(), and
 * user_has_role_assignment(). The only table written is this plugin's own.
 *
 * Spec 011 adds:
 *
 *   role events      also keep the mentor's office-hours group in step (officehours, R16)
 *   calendar events  an office-hours booking goes to booking_notice (R20); any other change
 *                    or cancellation is buffered per series by calendar_notify, and one
 *                    shutdown callback queues one task\event_change_notice per key (R15)
 *   slot_deleted     mod_scheduler's own event, the only signal that a booked slot was deleted
 */
class observer {

    /** This plugin's record of the contacts it made. */
    const TABLE = 'local_ltuse_mentor_contact';

    /**
     * @param \core\event\role_assigned $event
     */
    public static function role_assigned(\core\event\role_assigned $event): void {
        try {
            [$mentorid, $learnerid] = self::pair($event);
            if ($mentorid) {
                self::ensure_contact($mentorid, $learnerid);
            }
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not make the mentor contact: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        self::sync_office_hours($event);
    }

    /**
     * @param \core\event\role_unassigned $event
     */
    public static function role_unassigned(\core\event\role_unassigned $event): void {
        try {
            [$mentorid, $learnerid] = self::pair($event);
            if ($mentorid) {
                self::end_contact($mentorid, $learnerid);
            }
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not remove the mentor contact: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        self::sync_office_hours($event);
    }

    /**
     * Spec 011 (R16): a mentor relationship that starts or ends changes the mentor's group in
     * the office-hours course. After the contact handling, so a failure here leaves that done.
     *
     * @param \core\event\base $event role_assigned or role_unassigned
     */
    protected static function sync_office_hours(\core\event\base $event): void {
        try {
            [$mentorid, $learnerid] = self::pair($event);
            if ($mentorid) {
                officehours::sync_pair($mentorid, $learnerid);
            }
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not sync office hours: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // --- spec 011: calendar changes and bookings ---------------------------------------------

    /** @var array<int, true> event ids created in this request */
    protected static $created = [];

    /** @var array<string, array> calendar notices buffered in this request, by key */
    protected static $notices = [];

    /** @var bool whether the shutdown callback that queues the notices is registered */
    protected static $registered = false;

    /**
     * Remember the new event's id, so its second save in the same request is not announced
     * (R15), and record a new office-hours booking (R20).
     *
     * @param \core\event\calendar_event_created $event
     */
    public static function calendar_event_created(\core\event\calendar_event_created $event): void {
        self::$created[(int)$event->objectid] = true;
        self::calendar_event_changed($event, booking_notice::CREATED);
    }

    /**
     * @param \core\event\calendar_event_updated $event
     */
    public static function calendar_event_updated(\core\event\calendar_event_updated $event): void {
        self::calendar_event_changed($event, booking_notice::UPDATED);
    }

    /**
     * @param \core\event\calendar_event_deleted $event
     */
    public static function calendar_event_deleted(\core\event\calendar_event_deleted $event): void {
        self::calendar_event_changed($event, booking_notice::DELETED);
    }

    /**
     * Route one calendar change: an office-hours booking to booking_notice, anything else to
     * the change-notice buffer. Never throws into core.
     *
     * @param \core\event\base $event
     * @param string $kind created, updated or deleted
     */
    protected static function calendar_event_changed(\core\event\base $event, string $kind): void {
        try {
            $snapshot = $event->get_record_snapshot('event', $event->objectid) ?: null;
            if (!$snapshot) {
                return;
            }
            if ((string)($snapshot->modulename ?? '') === 'scheduler') {
                booking_notice::from_calendar($kind, $snapshot, (int)$event->userid);
                return;
            }
            if ($kind === booking_notice::CREATED) {
                return; // New events are never announced (plan decision 5).
            }
            $deleted = $kind === booking_notice::DELETED;
            $created = isset(self::$created[(int)$event->objectid]);
            if (!calendar_notify::decide($snapshot, (array)$event->other, $deleted, $created)) {
                return;
            }
            $key = calendar_notify::key($snapshot);
            self::$notices[$key] = calendar_notify::merge(self::$notices[$key] ?? null, $deleted, $snapshot,
                (int)$event->userid);
            if (!self::$registered) {
                self::$registered = true;
                \core\shutdown_manager::register_function([self::class, 'queue_calendar_notices']);
            }
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not note a calendar change: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * At the end of the request: one change notice per key, two minutes ahead, so a quick
     * re-edit folds into the same task (R15).
     */
    public static function queue_calendar_notices(): void {
        foreach (self::$notices as $data) {
            try {
                $task = new task\event_change_notice();
                $task->set_component('local_ltuse');
                $task->set_custom_data($data);
                $task->set_next_run_time(time() + 2 * MINSECS);
                \core\task\manager::reschedule_or_queue_adhoc_task($task);
            } catch (\Throwable $e) {
                debugging('local_ltuse: could not queue a calendar notice: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        self::$notices = [];
        self::$registered = false;
    }

    /**
     * A mentor deleted a whole office-hours slot. The scheduler removes its calendar events
     * with a raw delete that fires nothing, so this is the only signal (R20). Runs before the
     * slot is removed.
     *
     * @param \mod_scheduler\event\slot_deleted $event
     */
    public static function scheduler_slot_deleted(\core\event\base $event): void {
        try {
            if ((int)$event->contextinstanceid !== (int)(officehours::scheduler_cm()->id ?? 0)) {
                return;
            }
            booking_notice::from_slot_deleted((int)$event->objectid, (int)$event->relateduserid, (int)$event->userid);
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not send slot cancellations: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        try {
            $userid = (int)$event->objectid;
            $rows = $DB->get_records_select(self::TABLE, 'mentorid = :mentorid OR learnerid = :learnerid',
                ['mentorid' => $userid, 'learnerid' => $userid]);
            foreach ($rows as $row) {
                self::remove_own_contact($row);
                $DB->delete_records(self::TABLE, ['id' => $row->id]);
            }
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not clear mentor contacts: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        try {
            officehours::sync_user((int)$event->objectid);   // Spec 011: their groups and bookings.
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not sync office hours: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        try {
            // Spec 008: one-course and cohort mentors recorded with them, as mentor or learner,
            // then the courses those records were about (research R10, data-model section 3).
            $userid = (int)$event->objectid;
            $select = 'mentorid = :mentorid OR learnerid = :learnerid';
            $params = ['mentorid' => $userid, 'learnerid' => $userid];
            $table = admin\course_mentor_sync::TABLE;
            $courseids = $DB->get_fieldset_select($table, 'DISTINCT courseid', $select, $params);
            $DB->delete_records_select($table, $select, $params);
            foreach ($courseids as $courseid) {
                admin\course_mentor_sync::sync_course((int)$courseid);
            }
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not clear course mentors: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Make the pair contacts if they are not already, and record it. Idempotent.
     *
     * @param int $mentorid
     * @param int $learnerid
     * @return string 'created', 'recorded' (already a pair we made) or 'existing' (theirs)
     */
    public static function ensure_contact(int $mentorid, int $learnerid): string {
        global $DB;
        if ($DB->record_exists(self::TABLE, ['mentorid' => $mentorid, 'learnerid' => $learnerid])) {
            return 'recorded';
        }
        if (api::is_contact($mentorid, $learnerid)) {
            return 'existing'; // Their own contact: never ours to remove.
        }
        api::add_contact($mentorid, $learnerid);
        // Remember which message_contacts row is ours, so ending the relationship removes that
        // row and never a contact the two make again themselves after removing ours.
        $contact = api::get_contact($mentorid, $learnerid);
        $DB->insert_record(self::TABLE, (object)['mentorid' => $mentorid, 'learnerid' => $learnerid,
            'contactid' => $contact ? (int)$contact->id : 0, 'timecreated' => time()]);
        return 'created';
    }

    /**
     * Remove a contact only if it is still the one this plugin made.
     *
     * @param \stdClass $row a local_ltuse_mentor_contact record
     * @return bool true when a contact was removed
     */
    public static function remove_own_contact(\stdClass $row): bool {
        $contact = api::get_contact((int)$row->mentorid, (int)$row->learnerid);
        if (!$contact || (int)$contact->id !== (int)$row->contactid) {
            return false; // Ours is gone; whatever links them now is theirs.
        }
        api::remove_contact((int)$row->mentorid, (int)$row->learnerid);
        return true;
    }

    /**
     * Remove the contact this plugin made, once no mentor assignment links the pair.
     *
     * @param int $mentorid
     * @param int $learnerid
     * @return bool true when a contact was removed
     */
    public static function end_contact(int $mentorid, int $learnerid): bool {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['mentorid' => $mentorid, 'learnerid' => $learnerid]);
        if (!$row) {
            return false;
        }
        $roleid = mentoring::role_id();
        $context = \context_user::instance($learnerid, IGNORE_MISSING);
        if ($roleid && $context && user_has_role_assignment($mentorid, $roleid, $context->id)) {
            return false; // Still their mentor, through another assignment.
        }
        $removed = self::remove_own_contact($row);
        $DB->delete_records(self::TABLE, ['id' => $row->id]);
        return $removed;
    }

    /**
     * The mentor and learner a role event is about, or [0, 0] when it is not a mentor
     * assignment in a user context.
     *
     * @param \core\event\base $event role_assigned or role_unassigned
     * @return int[] [mentorid, learnerid]
     */
    protected static function pair(\core\event\base $event): array {
        $roleid = mentoring::role_id();
        if (!$roleid || (int)$event->objectid !== $roleid) {
            return [0, 0];
        }
        $context = $event->get_context();
        if (!$context || (int)$context->contextlevel !== CONTEXT_USER) {
            return [0, 0];
        }
        $mentorid = (int)$event->relateduserid;
        $learnerid = (int)$context->instanceid;
        if (!$mentorid || !$learnerid || $mentorid === $learnerid) {
            return [0, 0];
        }
        return [$mentorid, $learnerid];
    }
}
