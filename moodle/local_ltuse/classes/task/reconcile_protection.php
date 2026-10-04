<?php
namespace local_ltuse\task;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\protection\service;

/**
 * Repair what the hook and the observers miss (spec 016, research R2): a picture uploaded from
 * the app, profile data saved directly, a member removed from a cohort with no event. Hourly.
 *
 * Recomputes and re-applies every user with a protection row, every member of every
 * organisation whose minimum is above none, and every user's ltct_certname (R10). It writes no
 * log rows; the task log gets counts only, never a name (FR-013).
 */
class reconcile_protection extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task:reconcileprotection', 'local_ltuse');
    }

    /**
     * Run it.
     */
    public function execute(): void {
        global $DB;
        if (!service::table_exists() || !service::config()) {
            mtrace('local_ltuse: protection is not configured yet; run site_config.py apply.');
            return;
        }
        $users = array_map('intval', $DB->get_fieldset_select(service::TABLE, 'userid', '1 = 1'));
        foreach (service::org_rows() as $org) {
            if ((string)$org->minlevel !== 'none') {
                $users = array_merge($users, service::org_members((string)$org->orgkey));
            }
        }
        $checked = 0;
        $repaired = 0;
        $failed = 0;
        foreach (array_unique($users) as $userid) {
            $checked++;
            try {
                if (!service::is_settled($userid)) {
                    service::apply($userid);
                    $repaired++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }
        $certnames = service::backfill_certnames();
        mtrace("local_ltuse: protection reconcile checked {$checked}, repaired {$repaired}, failed {$failed}; " .
            "certificate names written {$certnames}.");
    }
}
