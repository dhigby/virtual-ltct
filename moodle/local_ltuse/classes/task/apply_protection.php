<?php
namespace local_ltuse\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Apply one user's effective protection level and ltct_certname (spec 016, research R2, R10).
 *
 * Queued by the user_created observer, which fires before the upload tool saves profile data,
 * so this runs once ltct_org is known; by an observer whose user was already being written; and
 * by set_org_protection for every member. Custom data: {userid}.
 */
class apply_protection extends \core\task\adhoc_task {

    /**
     * Queue one user, once: a second request for the same user is folded into the first.
     *
     * @param int $userid
     */
    public static function queue(int $userid): void {
        $task = new self();
        $task->set_component('local_ltuse');
        $task->set_custom_data(['userid' => $userid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task:applyprotection', 'local_ltuse');
    }

    /**
     * Run it. A refusal (the user's lock is busy) throws, so the task is retried.
     */
    public function execute(): void {
        $userid = (int)($this->get_custom_data()->userid ?? 0);
        if ($userid > 0) {
            \local_ltuse\protection\service::apply($userid);
        }
    }
}
