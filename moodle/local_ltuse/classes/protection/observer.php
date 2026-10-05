<?php
namespace local_ltuse\protection;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for identity protection (spec 016, research R2), registered in
 * db/events.php. They catch the writers the before_user_updated hook misses.
 *
 *   user_updated   returns at once for anyone with no protection row, so an ordinary account
 *                  change costs one indexed read. A protected user whose account drifted is
 *                  re-applied; at a level that withholds no name, a changed name is copied
 *                  into their row
 *   user_deleted   the shared deletion routine (FR-013)
 *
 * Nothing observes account creation or cohort changes: protection is per person and set when
 * they ask, never by an organisation (Doug, 2026-10-05 (scope review)).
 *
 * Every observer returns early for a user the service is writing, and none throws into core:
 * a failure is reported with debugging() and the reconcile task repairs it within the hour.
 * Both are internal (the default), so they run while the service's bypass set is still held.
 */
class observer {

    /**
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event): void {
        $userid = (int)$event->objectid;
        if ($userid <= 0 || service::in_bypass($userid)) {
            return;
        }
        try {
            if (!service::table_exists()) {
                return;
            }
            $row = service::row($userid);
            if (!$row || $row->effectivelevel === levels::NONE) {
                return;
            }
            if (service::drifted($userid, $row)) {
                // Inside someone else's transaction, or busy: the task does it instead (R2).
                service::apply_or_queue($userid);
                return;
            }
            service::refresh_real_names($userid);   // A name edited where names are not withheld.
        } catch (\Throwable $e) {
            debugging('local_ltuse: protection observer failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
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
}
