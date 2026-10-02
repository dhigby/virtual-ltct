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
