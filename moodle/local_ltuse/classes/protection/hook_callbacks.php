<?php
namespace local_ltuse\protection;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for identity protection (spec 016, research R2), registered in db/hooks.php.
 */
class hook_callbacks {

    /**
     * Re-apply a protected user's protected values on every user_update_user() call: the
     * profile and admin edit forms, core_user_update_users, the upload tool and the auth sync
     * path all go through it. Skips a user the service itself is writing.
     *
     * user_update_user() dispatches this hook with the object it is about to store, and keeps
     * using that object afterwards (user/lib.php on MOODLE_502_STABLE). The property is
     * readonly, the object it holds is not, so setting its fields changes what is stored.
     * That reliance is a Principle XI exception, listed in the plugin README and guarded by
     * tests/protection_test.php and quickstart V7.
     *
     * Never throws: core's hook manager calls each callback with no catch
     * (lib/classes/hook/manager.php dispatch() on MOODLE_502_STABLE), so an exception here
     * would abort every account save on the site, not only a protected one. A failure goes to
     * debugging(), and the reconcile task repairs the account within the hour.
     *
     * @param \core_user\hook\before_user_updated $hook
     */
    public static function before_user_updated(\core_user\hook\before_user_updated $hook): void {
        try {
            $userid = (int)($hook->user->id ?? 0);
            if ($userid <= 0 || service::in_bypass($userid) || !service::table_exists()) {
                return;
            }
            $row = service::row($userid);
            if (!$row || $row->effectivelevel === levels::NONE) {
                return;
            }
            foreach (service::protected_columns($row) as $column => $value) {
                $hook->user->$column = $value;
            }
            // The login guard (R3): no outside login for a protected account.
            if (isset($hook->user->auth) && !in_array((string)$hook->user->auth, service::SAFE_AUTH, true)) {
                $hook->user->auth = service::AUTH;
            }
        } catch (\Throwable $e) {
            debugging('local_ltuse: protection hook failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
