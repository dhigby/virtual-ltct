<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Hourly: bring the office-hours course into step with the mentor relationships (spec 011,
 * research R16). The observers keep it in step as relationships change; this repairs anything
 * they missed, a membership removed by hand, or a role changed in the interface, and clears
 * booking records whose calendar event is gone. It logs counts only (constitution III).
 */
class officehours_reconcile extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task:officehoursreconcile', 'local_ltuse');
    }

    public function execute() {
        $counts = \local_ltuse\officehours::reconcile();
        if (!$counts) {
            mtrace('local_ltuse: no office-hours course yet; nothing to reconcile');
            return;
        }
        $parts = [];
        foreach ($counts as $what => $n) {
            $parts[] = "{$what} {$n}";
        }
        mtrace('local_ltuse: office hours reconciled: ' . implode(', ', $parts));
    }
}
