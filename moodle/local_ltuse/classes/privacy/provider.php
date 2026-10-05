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
 * Spec 002 (amendment 2026-10-02, R12) adds local_ltuse_org_contact: which message contacts it
 * made between an organisation's manager and a person in it. Like the mentor contacts, each
 * row belongs to both people and is reported in each one's user context, and deleting it
 * removes the contact it stands for. Organisation membership itself is core's cohorts'.
 *
 * Spec 006 adds local_ltuse_pathway_cohort: each pathway given to a cohort, with the user who
 * made the link (usermodified). The link belongs to the cohort, so a deletion request keeps it
 * and sets usermodified to 0. Cohort membership is core_cohort's to export.
 *
 * Spec 016 adds two: local_ltuse_protection (a protected user's level, pseudonym and real
 * values) and local_ltuse_protection_log (every change, with who made it). A user's own rows
 * are exported in full; changes they made to someone else are exported as a count and dates
 * only, never naming that person. Deletion is the one routine the
 * user_deleted observer also runs: the user's own rows go, and wherever they acted their id is
 * set to 0. The protectionchanged notification is core messaging's.
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

    /** Spec 016: identity protection. */
    const PROTECTION = 'local_ltuse_protection';
    const PROTECTIONLOG = 'local_ltuse_protection_log';

    /** Spec 002: the organisation contacts it made (research R12). */
    const ORGCONTACT = 'local_ltuse_org_contact';
    /** Spec 006: pathways given to cohorts, and who gave them (research R9). */
    const PATHWAY_COHORT = 'local_ltuse_pathway_cohort';

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
        $collection->add_database_table(self::PROTECTION, [
            'userid' => 'privacy:metadata:protection:userid',
            'ownlevel' => 'privacy:metadata:protection:ownlevel',
            'effectivelevel' => 'privacy:metadata:protection:effectivelevel',
            'pseudonym' => 'privacy:metadata:protection:pseudonym',
            'realfirstname' => 'privacy:metadata:protection:realfirstname',
            'reallastname' => 'privacy:metadata:protection:reallastname',
            'realfields' => 'privacy:metadata:protection:realfields',
            'usermodified' => 'privacy:metadata:protection:usermodified',
        ], 'privacy:metadata:protection');
        $collection->add_database_table(self::PROTECTIONLOG, [
            'userid' => 'privacy:metadata:protectionlog:userid',
            'actorid' => 'privacy:metadata:protectionlog:actorid',
            'fromlevel' => 'privacy:metadata:protectionlog:fromlevel',
            'tolevel' => 'privacy:metadata:protectionlog:tolevel',
            'requested' => 'privacy:metadata:protectionlog:requested',
            'emailchecked' => 'privacy:metadata:protectionlog:emailchecked',
            'timecreated' => 'privacy:metadata:protectionlog:timecreated',
        ], 'privacy:metadata:protectionlog');
        $collection->add_message_provider('protectionchanged', 'privacy:metadata:protectionchanged');
        $collection->add_database_table(self::ORGCONTACT, [
            'managerid' => 'privacy:metadata:org_contact:managerid',
            'memberid' => 'privacy:metadata:org_contact:memberid',
            'contactid' => 'privacy:metadata:org_contact:contactid',
            'timecreated' => 'privacy:metadata:org_contact:timecreated',
        ], 'privacy:metadata:org_contact');
        $collection->add_database_table(self::PATHWAY_COHORT, [
            'pathwaykey' => 'privacy:metadata:local_ltuse_pathway_cohort:pathwaykey',
            'cohortid' => 'privacy:metadata:local_ltuse_pathway_cohort:cohortid',
            'usermodified' => 'privacy:metadata:local_ltuse_pathway_cohort:usermodified',
            'timecreated' => 'privacy:metadata:local_ltuse_pathway_cohort:timecreated',
            'timemodified' => 'privacy:metadata:local_ltuse_pathway_cohort:timemodified',
        ], 'privacy:metadata:local_ltuse_pathway_cohort');
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
                                    WHERE b.mentorid = :bmentorid OR b.learnerid = :blearnerid)
                        OR EXISTS (SELECT 1 FROM {" . self::ORGCONTACT . "} oc
                                    WHERE oc.managerid = :omanagerid OR oc.memberid = :omemberid)
                        OR EXISTS (SELECT 1 FROM {" . self::PATHWAY_COHORT . "} pc
                                    WHERE pc.usermodified = :pcuserid)
                        OR EXISTS (SELECT 1 FROM {" . self::PROTECTION . "} p
                                    WHERE p.userid = :puserid OR p.usermodified = :pmodified)
                        OR EXISTS (SELECT 1 FROM {" . self::PROTECTIONLOG . "} pl
                                    WHERE pl.userid = :pluserid OR pl.actorid = :plactor))";
        $contextlist->add_from_sql($sql, ['level' => CONTEXT_USER, 'userid' => $userid,
            'mentorid' => $userid, 'learnerid' => $userid, 'bmentorid' => $userid, 'blearnerid' => $userid,
            'omanagerid' => $userid, 'omemberid' => $userid, 'pcuserid' => $userid,
            'puserid' => $userid, 'pmodified' => $userid, 'pluserid' => $userid, 'plactor' => $userid]);
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
            self::export_org_contacts($context, $userid);
            self::export_pathway_links($context, $userid);
            self::export_protection($context, $userid);
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
            || $DB->record_exists_select(self::BOOKING, 'mentorid = :mentorid OR learnerid = :learnerid', $params)
            || $DB->record_exists_select(self::ORGCONTACT, 'managerid = :managerid OR memberid = :memberid',
                ['managerid' => $userid, 'memberid' => $userid])
            || $DB->record_exists(self::PATHWAY_COHORT, ['usermodified' => $userid])
            || self::has_protection_rows($userid);
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
     * Spec 016: whether the user has protection data, as the subject or as an actor.
     *
     * @param int $userid
     * @return bool
     */
    protected static function has_protection_rows(int $userid): bool {
        global $DB;
        if (!$DB->get_manager()->table_exists(self::PROTECTION)) {
            return false;
        }
        return $DB->record_exists_select(self::PROTECTION, 'userid = :a OR usermodified = :b', ['a' => $userid, 'b' => $userid])
            || $DB->record_exists_select(self::PROTECTIONLOG, 'userid = :a OR actorid = :b', ['a' => $userid, 'b' => $userid]);
    }

    /**
     * Spec 016 (FR-013): the user's own protection, every change to it, and the changes they
     * made to others as a count and dates only, never naming those people.
     *
     * @param context_user $context
     * @param int $userid
     */
    protected static function export_protection(context_user $context, int $userid): void {
        global $DB;
        if (!$DB->get_manager()->table_exists(self::PROTECTION)) {
            return;
        }
        $data = (object)[];
        if ($row = $DB->get_record(self::PROTECTION, ['userid' => $userid])) {
            $data->protection = (object)[
                'ownlevel' => $row->ownlevel,
                'effectivelevel' => $row->effectivelevel,
                'pseudonym' => $row->pseudonym,
                'realfirstname' => $row->realfirstname,
                'reallastname' => $row->reallastname,
                'realfields' => $row->realfields ? json_decode($row->realfields, true) : [],
                'timecreated' => transform::datetime($row->timecreated),
                'timemodified' => transform::datetime($row->timemodified),
            ];
        }
        $changes = [];
        foreach ($DB->get_records(self::PROTECTIONLOG, ['userid' => $userid], 'timecreated, id') as $log) {
            $changes[] = (object)['from' => $log->fromlevel, 'to' => $log->tolevel, 'source' => $log->source,
                'requested' => transform::yesno((int)$log->requested === 1),
                'emailchecked' => transform::yesno((int)$log->emailchecked === 1),
                'bysomeoneelse' => transform::yesno((int)$log->actorid !== $userid && (int)$log->actorid !== 0),
                'time' => transform::datetime($log->timecreated)];
        }
        if ($changes) {
            $data->changes = $changes;
        }
        $made = $DB->get_fieldset_select(self::PROTECTIONLOG, 'timecreated',
            'actorid = :actor AND userid <> :self', ['actor' => $userid, 'self' => $userid]);
        if ($made) {
            $data->changesmadetoothers = (object)['count' => count($made),
                'dates' => array_map([transform::class, 'datetime'], $made)];
        }
        if ((array)$data) {
            writer::with_context($context)->export_data([get_string('privacy:path:protection', 'local_ltuse')], $data);
        }
    }

    /**
     * Export the pathway links the user made or last changed (spec 006).
     *
     * @param context_user $context
     * @param int $userid
     */
    protected static function export_pathway_links(context_user $context, int $userid): void {
        global $DB;
        $rows = $DB->get_records(self::PATHWAY_COHORT, ['usermodified' => $userid], 'timecreated, id');
        if (!$rows) {
            return;
        }
        $links = [];
        foreach ($rows as $row) {
            $links[] = (object)[
                'pathwaykey' => (string)$row->pathwaykey,
                'cohortid' => (int)$row->cohortid,
                'timecreated' => transform::datetime($row->timecreated),
                'timemodified' => transform::datetime($row->timemodified),
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('privacy:path:pathways', 'local_ltuse')], (object)['links' => $links]);
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
        // A pathway link belongs to its cohort: keep it, forget who made it (spec 006).
        $DB->set_field(self::PATHWAY_COHORT, 'usermodified', 0, ['usermodified' => $userid]);
        // Spec 002: the same for organisation contacts, through contacts::user_deleted(), which
        // removes each contact only if it is still the one this plugin made.
        \local_ltuse\organisation\contacts::user_deleted($userid);
        \local_ltuse\protection\service::delete_user_data($userid);   // Spec 016: the shared routine.
    }

    /**
     * Export the user's organisation contact records, as manager or as member (spec 002, R12).
     *
     * @param context_user $context
     * @param int $userid
     */
    protected static function export_org_contacts(context_user $context, int $userid): void {
        global $DB;
        $rows = $DB->get_records_select(self::ORGCONTACT, 'managerid = :managerid OR memberid = :memberid',
            ['managerid' => $userid, 'memberid' => $userid], 'timecreated, id');
        if (!$rows) {
            return;
        }
        $contacts = [];
        foreach ($rows as $row) {
            $contacts[] = (object)[
                'role' => ((int)$row->managerid === $userid) ? 'manager' : 'member',
                'managerid' => (int)$row->managerid,
                'memberid' => (int)$row->memberid,
                'contactid' => (int)$row->contactid,
                'timecreated' => transform::datetime($row->timecreated),
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('privacy:path:orgcontacts', 'local_ltuse')], (object)['contacts' => $contacts]);
    }
}
