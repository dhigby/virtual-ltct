<?php
namespace local_ltuse\protection;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\task\apply_protection;

/**
 * Event observers for identity protection (spec 016, research R2), registered in
 * db/events.php. They catch the writers the before_user_updated hook misses.
 *
 *   user_created           queue apply_protection: it runs after the upload tool has saved
 *                          profile data, then sets ltct_certname and any organisation minimum
 *   user_updated           a protected user who drifted is re-applied; anyone else has
 *                          ltct_certname kept in step with their name (R10)
 *   cohort_member_added    only for ltct:org:<key> of a declared key, never a managers cohort:
 *                          the organisation's minimum is applied at once, before any course
 *                          listing shows the new member's real name (US3-3)
 *   cohort_member_removed  the same match: their level is kept as organisation-kept (R13)
 *   user_deleted           the shared deletion routine (FR-013)
 *
 * Every observer returns early for a user the service is writing, and none throws into core:
 * a failure is reported with debugging() and the reconcile task repairs it within the hour.
 * All are internal (the default), so they run while the service's bypass set is still held.
 */
class observer {

    /**
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event): void {
        self::guard((int)$event->objectid, function(int $userid) {
            apply_protection::queue($userid);
        });
    }

    /**
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event): void {
        self::guard((int)$event->objectid, function(int $userid) {
            $row = service::row($userid);
            if ($row && $row->effectivelevel !== levels::NONE) {
                if (service::drifted($userid, $row)) {
                    service::apply($userid);
                }
                return;
            }
            service::sync_certname($userid, $row);
        });
    }

    /**
     * @param \core\event\cohort_member_added $event
     */
    public static function cohort_member_added(\core\event\cohort_member_added $event): void {
        self::cohort_changed($event);
    }

    /**
     * @param \core\event\cohort_member_removed $event
     */
    public static function cohort_member_removed(\core\event\cohort_member_removed $event): void {
        self::cohort_changed($event);
    }

    /**
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        try {
            service::delete_user_data((int)$event->objectid);
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not delete protection data: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * A member joined or left an organisation's member cohort. apply() raises a joiner to the
     * minimum and never lowers a leaver (R13).
     *
     * @param \core\event\base $event cohort_member_added or cohort_member_removed
     */
    protected static function cohort_changed(\core\event\base $event): void {
        try {
            $cohort = $event->get_record_snapshot('cohort', $event->objectid);
            if (!$cohort || levels::member_cohort_key((string)$cohort->idnumber, service::declared_org_keys()) === null) {
                return;
            }
        } catch (\Throwable $e) {
            debugging('local_ltuse: could not read the cohort: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return;
        }
        self::guard((int)$event->relateduserid, function(int $userid) {
            try {
                service::apply($userid);
            } catch (\moodle_exception $e) {
                // Busy (another write holds the user's lock): never nested, done by the task.
                apply_protection::queue($userid);
            }
        });
    }

    /**
     * Run $work for a user unless the service is writing them; never throw into core.
     *
     * @param int $userid
     * @param callable $work
     */
    protected static function guard(int $userid, callable $work): void {
        if ($userid <= 0 || service::in_bypass($userid)) {
            return;
        }
        try {
            if (!service::table_exists()) {
                return;
            }
            $work($userid);
        } catch (\Throwable $e) {
            debugging('local_ltuse: protection observer failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
