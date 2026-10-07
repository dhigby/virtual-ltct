<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

/**
 * Read tracking on for the accounts that existed before the site default changed (spec 005,
 * Q18 and round 2). New accounts take defaultpreference_trackforums: 1 from
 * moodle/site/settings/notifications.yaml; this switches it on once for the rest, so the app
 * and the web mark unread posts for everyone.
 *
 * Each change goes through core's user_update_user() (research R18), so the before_user_updated
 * hook (identity protection) and the user_updated observers run as they do for any profile
 * change. Password untouched. Idempotent: an account already tracking is not written.
 *
 * Raw read, listed in README.md (constitution XI): user by deleted, id and trackforums, for the
 * ids to change and a count. Core has no API that lists accounts by a preference column. It
 * returns counts only, never a person (constitution III).
 */
final class trackforums {

    /**
     * Switch read tracking on for every non-deleted, non-guest account that has it off.
     *
     * @return array ['seen' => accounts considered, 'changed' => accounts switched on]
     */
    public static function enable_existing(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        $where = 'deleted = 0 AND id <> :guest';
        $params = ['guest' => (int)$CFG->siteguest];
        $seen = $DB->count_records_select('user', $where, $params);
        $ids = $DB->get_fieldset_select('user', 'id', $where . ' AND trackforums = 0', $params);
        foreach ($ids as $id) {
            user_update_user((object)['id' => (int)$id, 'trackforums' => 1], false);
        }
        return ['seen' => (int)$seen, 'changed' => count($ids)];
    }
}
