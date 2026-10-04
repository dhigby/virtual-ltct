<?php
namespace local_ltuse\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_ltuse.
 *
 * The plugin keeps one table of personal data, local_ltuse_mentor_contact (spec 003): which
 * mentor-learner message contacts it made. Each row belongs to both people, so it is reported
 * in each one's user context. The mentor relationship itself (core role_assignments) and the
 * message contact (core message_contacts) are core's to export and delete.
 *
 * Spec 011 adds local_ltuse_booking: each office-hours booking's last notified time, which
 * belongs to the learner and the mentor alike and is reported in each one's user context. The
 * appointment itself is mod_scheduler's to export, the calendar events core's, and the
 * eventchange and bookingnotice notifications core messaging's.
 *
 * Its other tables hold nothing about a person: the competency framework, which courses aim
 * at which competency (spec 004), and which badge is each course's (spec 013). The
 * per-competency report counts enrolments and completions from core's tables at query time
 * and stores none of it.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /** The table. */
    const TABLE = 'local_ltuse_mentor_contact';

    /** Spec 011: each office-hours booking's last notified time (research R20). */
    const BOOKING = 'local_ltuse_booking';

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(self::TABLE, [
            'mentorid' => 'privacy:metadata:mentor_contact:mentorid',
            'learnerid' => 'privacy:metadata:mentor_contact:learnerid',
            'contactid' => 'privacy:metadata:mentor_contact:contactid',
            'timecreated' => 'privacy:metadata:mentor_contact:timecreated',
        ], 'privacy:metadata:mentor_contact');
        $collection->add_database_table(self::BOOKING, [
            'eventid' => 'privacy:metadata:booking:eventid',
            'slotid' => 'privacy:metadata:booking:slotid',
            'learnerid' => 'privacy:metadata:booking:learnerid',
            'mentorid' => 'privacy:metadata:booking:mentorid',
            'timestart' => 'privacy:metadata:booking:timestart',
            'timeduration' => 'privacy:metadata:booking:timeduration',
            'timecreated' => 'privacy:metadata:booking:timecreated',
        ], 'privacy:metadata:booking');
        // The plugin also writes into core messaging: it makes mentor and learner contacts.
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist the user's own context, when they have a row
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :level
                   AND ctx.instanceid = :userid
                   AND (EXISTS (SELECT 1 FROM {" . self::TABLE . "} mc
                                 WHERE mc.mentorid = :mentorid OR mc.learnerid = :learnerid)
                        OR EXISTS (SELECT 1 FROM {" . self::BOOKING . "} b
                                    WHERE b.mentorid = :bmentorid OR b.learnerid = :blearnerid))";
        $contextlist->add_from_sql($sql, ['level' => CONTEXT_USER, 'userid' => $userid,
            'mentorid' => $userid, 'learnerid' => $userid, 'bmentorid' => $userid, 'blearnerid' => $userid]);
        return $contextlist;
    }

    /**
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        $userid = (int)$context->instanceid;
        if (self::has_rows($userid)) {
            $userlist->add_user($userid);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int)$context->instanceid !== $userid) {
                continue;
            }
            self::export_bookings($context, $userid);
            $rows = $DB->get_records_select(self::TABLE, 'mentorid = :mentorid OR learnerid = :learnerid',
                ['mentorid' => $userid, 'learnerid' => $userid], 'timecreated, id');
            if (!$rows) {
                continue;
            }
            $contacts = [];
            foreach ($rows as $row) {
                $contacts[] = (object)[
                    'role' => ((int)$row->mentorid === $userid) ? 'mentor' : 'learner',
                    'mentorid' => (int)$row->mentorid,
                    'learnerid' => (int)$row->learnerid,
                    'contactid' => (int)$row->contactid,
                    'timecreated' => transform::datetime($row->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:mentorcontacts', 'local_ltuse')],
                (object)['contacts' => $contacts]);
        }
    }

    /**
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        if ($context instanceof context_user) {
            self::delete_rows((int)$context->instanceid);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && (int)$context->instanceid === $userid) {
                self::delete_rows($userid);
            }
        }
    }

    /**
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }
        if (in_array((int)$context->instanceid, array_map('intval', $userlist->get_userids()), true)) {
            self::delete_rows((int)$context->instanceid);
        }
    }

    /**
     * @param int $userid
     * @return bool
     */
    protected static function has_rows(int $userid): bool {
        global $DB;
        $params = ['mentorid' => $userid, 'learnerid' => $userid];
        return $DB->record_exists_select(self::TABLE, 'mentorid = :mentorid OR learnerid = :learnerid', $params)
            || $DB->record_exists_select(self::BOOKING, 'mentorid = :mentorid OR learnerid = :learnerid', $params);
    }

    /**
     * Export the user's office-hours booking records, as learner or as mentor (spec 011).
     * The appointments themselves are mod_scheduler's to export.
     *
     * @param context_user $context
     * @param int $userid
     */
    protected static function export_bookings(context_user $context, int $userid): void {
        global $DB;
        $rows = $DB->get_records_select(self::BOOKING, 'mentorid = :mentorid OR learnerid = :learnerid',
            ['mentorid' => $userid, 'learnerid' => $userid], 'timestart, id');
        if (!$rows) {
            return;
        }
        $bookings = [];
        foreach ($rows as $row) {
            $bookings[] = (object)[
                'role' => ((int)$row->mentorid === $userid) ? 'mentor' : 'learner',
                'slotid' => (int)$row->slotid,
                'learnerid' => (int)$row->learnerid,
                'mentorid' => (int)$row->mentorid,
                'timestart' => transform::datetime($row->timestart),
                'minutes' => (int)round($row->timeduration / MINSECS),
                'timecreated' => transform::datetime($row->timecreated),
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('privacy:path:bookings', 'local_ltuse')], (object)['bookings' => $bookings]);
    }

    /**
     * Delete the user's rows, and the message contacts they stand for: a contact whose record
     * is gone could never be removed when the relationship ends (research R5).
     *
     * @param int $userid
     */
    protected static function delete_rows(int $userid): void {
        global $DB;
        $rows = $DB->get_records_select(self::TABLE, 'mentorid = :mentorid OR learnerid = :learnerid',
            ['mentorid' => $userid, 'learnerid' => $userid]);
        foreach ($rows as $row) {
            \local_ltuse\observer::remove_own_contact($row);
            $DB->delete_records(self::TABLE, ['id' => $row->id]);
        }
        $DB->delete_records_select(self::BOOKING, 'mentorid = :mentorid OR learnerid = :learnerid',
            ['mentorid' => $userid, 'learnerid' => $userid]);
    }
}
