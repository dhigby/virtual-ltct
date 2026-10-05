<?php
namespace local_ltuse\task;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\protection\levels;
use local_ltuse\protection\service;

/**
 * Repair what the hook and the observer miss (spec 016, research R2): a picture uploaded from
 * the app, profile data saved directly. Hourly.
 *
 * Reads only the protection rows above none, and returns at once when there are none, so a
 * site with nobody protected does nothing (Doug, 2026-10-05 (scope review)). Each drifted
 * account is re-applied at its own level, and the course-log block is brought into line with
 * the courses of those who asked for it (service::sync_log_blocks()). It writes no log rows;
 * the task log gets counts only, never a name (FR-013).
 *
 * When any account could not be repaired it throws after the loop, so core records the run as
 * failed (lib/classes/cron.php run_inner_scheduled_task() calls
 * \core\task\manager::scheduled_task_failed(), on MOODLE_502_STABLE): the task's fail delay
 * shows under Scheduled tasks, and core messages the admins once the delay reaches its 24-hour
 * maximum. The site team acts on that, not on a count in the log.
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
     *
     * @throws \moodle_exception when any protected account could not be repaired
     */
    public function execute(): void {
        global $DB;
        if (!service::table_exists() || !service::config()) {
            mtrace('local_ltuse: protection is not configured yet; run site_config.py apply.');
            return;
        }
        $users = array_map('intval', $DB->get_fieldset_select(service::TABLE, 'userid',
            'effectivelevel <> :none', ['none' => levels::NONE]));
        if (!$users) {
            return;
        }
        $repaired = 0;
        $failed = 0;
        foreach ($users as $userid) {
            try {
                if (!service::is_settled($userid)) {
                    service::apply($userid);
                    $repaired++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }
        // The course-log block (R14): follows those who asked into courses they joined since.
        try {
            service::sync_log_blocks();
        } catch (\Throwable $e) {
            $failed++;
        }
        $checked = count($users);
        mtrace("local_ltuse: protection reconcile checked {$checked}, repaired {$repaired}, failed {$failed}.");
        if ($failed) {
            throw new \moodle_exception('protection:err:reconcile', 'local_ltuse', '', $failed);
        }
    }
}
