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
     * @param \core_user\hook\before_user_updated $hook
     */
    public static function before_user_updated(\core_user\hook\before_user_updated $hook): void {
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
    }
}
