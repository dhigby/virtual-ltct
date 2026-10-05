<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Hourly: bring organisation contacts and old-organisation enrolments into step (spec 002
 * amendment 2026-10-02, research R10 and R12). The cohort observers do this as membership
 * changes; this repairs what fires no event (a tool_dynamic_cohorts bulk rule, a cohort
 * unmanaged with releasemembers, a contact row deleted by hand), both ways: missing
 * manager-member contacts are made, contacts this plugin made that nothing keeps are removed,
 * and organisation-enrolment enrolments in an ltct:org:<key> course of anyone no longer in
 * that organisation are suspended. It logs counts only (constitution III).
 */
class reconcile_org_contacts extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task:reconcileorgcontacts', 'local_ltuse');
    }

    public function execute() {
        $counts = \local_ltuse\organisation\contacts::reconcile();
        $parts = [];
        foreach ($counts as $what => $n) {
            $parts[] = "{$what} {$n}";
        }
        mtrace('local_ltuse: organisation contacts reconciled: ' . implode(', ', $parts));
    }
}
