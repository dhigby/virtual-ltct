<?php
namespace local_ltuse\organisation;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_message\api;

/**
 * What a change of organisation membership does (spec 002 amendment 2026-10-02, research R10
 * and R12; data-model "Organisation contact record").
 *
 *   contacts     each manager of an organisation and each person in it become message
 *                contacts, recorded in local_ltuse_org_contact, so a leave removes only the
 *                contacts this plugin made, and only once no remaining relationship (another
 *                organisation both are in, as manager and member, or mentoring) links the pair
 *   enrolments   a person who leaves an organisation's member cohort has their enrolments
 *                through the organisation-enrolment instance in that organisation's
 *                ltct:org:<key> courses suspended, as cohort sync would do, so an
 *                organisation-only course stays its organisation's (SC-002). Their shared-course
 *                enrolments stand: those courses are open
 *
 * Driven by \core\event\cohort_member_added and cohort_member_removed (lib/classes/event/;
 * fired by cohort_add_member() and cohort_remove_member(), cohort/lib.php:189-224, objectid
 * the cohort, relateduserid the person), and repaired both ways by the hourly
 * task\reconcile_org_contacts, because some membership changes fire no event (R12:
 * tool_dynamic_cohorts' bulk rules, releasemembers).
 *
 * Spec 003's pattern (local_ltuse\observer): \core_message\api::is_contact() before
 * add_contact(), which throws on a duplicate (message/classes/api.php:2242, :2343); the
 * message_contacts row we made is remembered by id, so a contact the two people make
 * themselves is never removed. A learner's block still wins: core checks blocks first.
 *
 * Membership is read afresh on every call, never from lib.php's request cache, because the
 * event that calls this has just changed it. Nothing here throws into core: the observer
 * catches and reports with debugging(). Logs and returns counts only (constitution III).
 */
class contacts {

    /** This plugin's record of the organisation contacts it made. */
    const TABLE = 'local_ltuse_org_contact';

    /** A member cohort. */
    const MEMBERS = 'members';

    /** A managers cohort. */
    const MANAGERS = 'managers';

    /**
     * What an organisation cohort idnumber names. Pure: tests/org_access_harness.php tests it.
     *
     * @param string $idnumber a cohort idnumber
     * @return array|null [key, MEMBERS|MANAGERS], or null when it is not an organisation cohort
     */
    public static function parse_cohort(string $idnumber): ?array {
        if (preg_match('/^ltct:org:([^:]+)$/', $idnumber, $m)) {
            return [$m[1], self::MEMBERS];
        }
        if (preg_match('/^ltct:org:([^:]+):managers$/', $idnumber, $m)) {
            return [$m[1], self::MANAGERS];
        }
        return null;
    }

    /**
     * Someone joined a cohort. Only organisation cohorts matter.
     *
     * @param int $cohortid
     * @param int $userid
     * @return int contacts made
     */
    public static function member_added(int $cohortid, int $userid): int {
        $parsed = self::parse_cohort(self::cohort_idnumber($cohortid));
        if (!$parsed) {
            return 0;
        }
        [$key, $kind] = $parsed;
        $orgs = self::organisations();
        if (!isset($orgs[$key])) {
            return 0;
        }
        $made = 0;
        if ($kind === self::MEMBERS) {
            foreach (self::members($orgs[$key][self::MANAGERS]) as $managerid) {
                $made += self::ensure_contact($managerid, $userid) === 'created' ? 1 : 0;
            }
        } else {
            foreach (self::members($orgs[$key][self::MEMBERS]) as $memberid) {
                $made += self::ensure_contact($userid, $memberid) === 'created' ? 1 : 0;
            }
        }
        return $made;
    }

    /**
     * Someone left a cohort: end the contacts nothing else keeps, and, for a member cohort,
     * suspend their organisation-enrolment enrolments in that organisation's own courses.
     *
     * @param int $cohortid
     * @param int $userid
     * @return array counts {contacts, suspended}
     */
    public static function member_removed(int $cohortid, int $userid): array {
        global $DB;
        $counts = ['contacts' => 0, 'suspended' => 0];
        $parsed = self::parse_cohort(self::cohort_idnumber($cohortid));
        if (!$parsed) {
            return $counts;
        }
        [$key, $kind] = $parsed;
        $orgs = self::organisations();
        $column = $kind === self::MEMBERS ? 'memberid' : 'managerid';
        foreach ($DB->get_records(self::TABLE, [$column => $userid]) as $row) {
            $counts['contacts'] += self::end_contact($row, $orgs) ? 1 : 0;
        }
        if ($kind === self::MEMBERS) {
            $counts['suspended'] = self::suspend_org_enrolments($userid, $key);
        }
        return $counts;
    }

    /**
     * A deleted user: forget their records and the contacts they stood for.
     *
     * @param int $userid
     */
    public static function user_deleted(int $userid): void {
        global $DB;
        $rows = $DB->get_records_select(self::TABLE, 'managerid = :managerid OR memberid = :memberid',
            ['managerid' => $userid, 'memberid' => $userid]);
        foreach ($rows as $row) {
            self::remove_own_contact($row);
            $DB->delete_records(self::TABLE, ['id' => $row->id]);
        }
    }

    /**
     * Bring everything into step: every manager-member pair a contact, every contact we made
     * that nothing keeps removed, and every organisation-enrolment enrolment in an
     * ltct:org:<key> course of a person no longer in that organisation suspended.
     *
     * @return array counts {created, removed, suspended}
     */
    public static function reconcile(): array {
        global $DB;
        $counts = ['created' => 0, 'removed' => 0, 'suspended' => 0];
        $orgs = self::organisations();

        $wanted = [];
        foreach ($orgs as $org) {
            $members = self::members($org[self::MEMBERS]);
            foreach (self::members($org[self::MANAGERS]) as $managerid) {
                foreach ($members as $memberid) {
                    if ($managerid !== $memberid) {
                        $wanted[$managerid . ':' . $memberid] = [$managerid, $memberid];
                    }
                }
            }
        }
        foreach ($wanted as [$managerid, $memberid]) {
            $counts['created'] += self::ensure_contact($managerid, $memberid) === 'created' ? 1 : 0;
        }
        foreach ($DB->get_records(self::TABLE) as $row) {
            if (!isset($wanted[$row->managerid . ':' . $row->memberid])) {
                $counts['removed'] += self::end_contact($row, $orgs) ? 1 : 0;
            }
        }

        foreach ($orgs as $key => $org) {
            // A missing member cohort is not "nobody": suspending on it would end every
            // organisation enrolment in that organisation's courses.
            if (empty($org[self::MEMBERS])) {
                continue;
            }
            $members = array_flip(self::members($org[self::MEMBERS]));
            foreach (self::org_course_instances($key) as $instance) {
                $active = $DB->get_fieldset_select('user_enrolments', 'userid', 'enrolid = :enrolid AND status = :active',
                    ['enrolid' => $instance->id, 'active' => ENROL_USER_ACTIVE]);
                foreach ($active as $userid) {
                    if (!isset($members[(int)$userid])) {
                        enrol_get_plugin(access::ENROL_PLUGIN)->update_user_enrol($instance, (int)$userid,
                            ENROL_USER_SUSPENDED);
                        $counts['suspended']++;
                    }
                }
            }
        }
        return $counts;
    }

    /**
     * Make the pair contacts if they are not already, and record it. Idempotent.
     *
     * @param int $managerid
     * @param int $memberid
     * @return string 'created', 'recorded' (already a pair we made), 'existing' (theirs) or
     *     'self'
     */
    public static function ensure_contact(int $managerid, int $memberid): string {
        global $DB;
        if ($managerid === $memberid) {
            return 'self';
        }
        if ($DB->record_exists(self::TABLE, ['managerid' => $managerid, 'memberid' => $memberid])) {
            return 'recorded';
        }
        if (api::is_contact($managerid, $memberid)) {
            return 'existing'; // Their own, or spec 003's for mentoring: never ours to remove.
        }
        api::add_contact($managerid, $memberid);
        $contact = api::get_contact($managerid, $memberid);
        $DB->insert_record(self::TABLE, (object)['managerid' => $managerid, 'memberid' => $memberid,
            'contactid' => $contact ? (int)$contact->id : 0, 'timecreated' => time()]);
        return 'created';
    }

    /**
     * Remove a recorded contact, unless a relationship still links the pair. Keeps the record
     * while one does, so the reconcile task removes the contact once nothing does.
     *
     * @param \stdClass $row a local_ltuse_org_contact record
     * @param array $orgs from organisations()
     * @return bool true when a contact was removed
     */
    protected static function end_contact(\stdClass $row, array $orgs): bool {
        global $DB;
        if (self::linked((int)$row->managerid, (int)$row->memberid, $orgs)) {
            return false;
        }
        $removed = self::remove_own_contact($row);
        $DB->delete_records(self::TABLE, ['id' => $row->id]);
        return $removed;
    }

    /**
     * Whether anything still links a manager and a person: an organisation where one manages
     * and the other is a member, or a mentor relationship either way (spec 003).
     *
     * @param int $managerid
     * @param int $memberid
     * @param array $orgs from organisations()
     * @return bool
     */
    protected static function linked(int $managerid, int $memberid, array $orgs): bool {
        foreach ($orgs as $org) {
            if ($org[self::MANAGERS] && $org[self::MEMBERS]
                    && cohort_is_member($org[self::MANAGERS], $managerid)
                    && cohort_is_member($org[self::MEMBERS], $memberid)) {
                return true;
            }
        }
        $roleid = \local_ltuse\mentoring::role_id();
        if ($roleid) {
            foreach ([[$managerid, $memberid], [$memberid, $managerid]] as [$mentorid, $learnerid]) {
                $context = \context_user::instance($learnerid, IGNORE_MISSING);
                if ($context && user_has_role_assignment($mentorid, $roleid, $context->id)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Remove a contact only if it is still the one this plugin made.
     *
     * @param \stdClass $row a local_ltuse_org_contact record
     * @return bool true when a contact was removed
     */
    public static function remove_own_contact(\stdClass $row): bool {
        $contact = api::get_contact((int)$row->managerid, (int)$row->memberid);
        if (!$contact || (int)$contact->id !== (int)$row->contactid) {
            return false; // Ours is gone; whatever links them now is theirs.
        }
        api::remove_contact((int)$row->managerid, (int)$row->memberid);
        return true;
    }

    /**
     * Suspend a person's active organisation-enrolment enrolments in one organisation's own
     * courses, through enrol_plugin::update_user_enrol() (lib/enrollib.php:2214). Never
     * unenrols: their history stays (R7).
     *
     * @param int $userid
     * @param string $key the organisation they left
     * @return int enrolments suspended
     */
    public static function suspend_org_enrolments(int $userid, string $key): int {
        global $DB;
        $count = 0;
        foreach (self::org_course_instances($key) as $instance) {
            if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid,
                    'status' => ENROL_USER_ACTIVE])) {
                enrol_get_plugin(access::ENROL_PLUGIN)->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
                $count++;
            }
        }
        return $count;
    }

    /**
     * The organisation-enrolment instances of the courses in one organisation's category.
     * course_categories.idnumber is not indexed in core; the plugin README lists this read.
     *
     * @param string $key
     * @return \stdClass[] enrol records
     */
    protected static function org_course_instances(string $key): array {
        global $DB;
        $sql = "SELECT e.*
                  FROM {enrol} e
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE cc.idnumber = :category
                   AND e.enrol = :plugin
                   AND e.customchar1 = :marker";
        return $DB->get_records_sql($sql, ['category' => access::ORG_CATEGORY_PREFIX . $key,
            'plugin' => access::ENROL_PLUGIN, 'marker' => access::ENROL_MARKER]);
    }

    /**
     * Every organisation with its two cohorts' ids: one read of the system-context cohorts
     * whose idnumber starts ltct:org: (cohort.idnumber is not indexed; README).
     *
     * @return array key => [MEMBERS => cohort id or 0, MANAGERS => cohort id or 0]
     */
    public static function organisations(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $records = $DB->get_records_select('cohort', 'contextid = :contextid AND ' . $DB->sql_like('idnumber', ':prefix'),
            ['contextid' => context_system::instance()->id, 'prefix' => $DB->sql_like_escape('ltct:org:') . '%'],
            '', 'id, idnumber');
        $orgs = [];
        foreach ($records as $record) {
            $parsed = self::parse_cohort((string)$record->idnumber);
            if ($parsed) {
                [$key, $kind] = $parsed;
                $orgs[$key] = ($orgs[$key] ?? [self::MEMBERS => 0, self::MANAGERS => 0]);
                $orgs[$key][$kind] = (int)$record->id;
            }
        }
        return $orgs;
    }

    /**
     * A cohort's user ids, by the indexed cohortid.
     *
     * @param int $cohortid 0 for none
     * @return int[]
     */
    protected static function members(int $cohortid): array {
        global $DB;
        if (!$cohortid) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = :cohortid',
            ['cohortid' => $cohortid]));
    }

    /**
     * A cohort's idnumber, or '' when it is gone.
     *
     * @param int $cohortid
     * @return string
     */
    protected static function cohort_idnumber(int $cohortid): string {
        global $DB;
        // System context only, as lib.php reads organisation cohorts: a category cohort that
        // borrows an ltct:org: idnumber is not an organisation's.
        return (string)$DB->get_field('cohort', 'idnumber', ['id' => $cohortid,
            'contextid' => \context_system::instance()->id]);
    }
}
