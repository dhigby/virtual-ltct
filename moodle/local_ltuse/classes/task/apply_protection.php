<?php
namespace local_ltuse\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Re-apply one protected user's level (spec 016, research R2).
 *
 * Queued by service::apply_or_queue() when the user_updated observer finds a drifted account
 * inside someone else's transaction, or the user's lock busy. Custom data: {userid}.
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
