<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Suspend or reactivate accounts: preview a whole file, apply one row at a time (spec 008, US3;
 * research R6, R15).
 *
 * The write is spec 002's unchecked core, organisation\actions::do_suspend() / do_reactivate(),
 * so the site team and the organisation managers suspend one way. do_suspend() ends the
 * person's sessions, then writes {id, suspended} and nothing else. A suspended account keeps
 * every enrolment, grade and completion; Moodle refuses its login (FR-006, US3-1).
 *
 * The site team may suspend people no manager may: staff, mentors, managers and learners in
 * the holding entries. The rules do_*() keep still hold here: never a site admin, never the
 * acting user. Both are reported in the preview as rejected, so the apply never trips them.
 *
 * Outcomes per row: would_change, unchanged (already suspended, or already active), rejected.
 *
 * The external functions check local/ltuse:administer and moodle/user:update first. Nothing
 * here checks a capability.
 */
class suspension_service {

    /** Spec 002's management actions. */
    const ACTIONS = '\local_ltuse\organisation\actions';

    /**
     * Preview a whole file. Changes nothing.
     *
     * @param array $rows each [row, email]
     * @param bool $suspend true to suspend, false to reactivate
     * @param bool $showpeople return emails as given rather than masked
     * @return array ['refusal' => string, 'rows' => [[row, key, outcome, reason, changes[]]]]
     */
    public static function preview(array $rows, bool $suspend, bool $showpeople): array {
        $results = [];
        foreach ($rows as $row) {
            $state = self::classify($row, $suspend);
            $results[] = [
                'row' => (int)$row['row'],
                'key' => $showpeople ? trim((string)$row['email']) : masking::mask_email((string)$row['email']),
                'outcome' => $state['outcome'],
                'reason' => intake_service::reason($state['reason']),
                'changes' => $state['outcome'] === 'would_change' ? [$suspend ? 'suspend' : 'reactivate'] : [],
            ];
        }
        return ['refusal' => '', 'rows' => $results];
    }

    /**
     * Apply one row, if it is still what the preview said or already done.
     *
     * @param array $row [row, email]
     * @param bool $suspend
     * @param string $expectedoutcome
     * @return array [row, outcome, status: done|already_done|refused, reason]
     */
    public static function apply_row(array $row, bool $suspend, string $expectedoutcome): array {
        $state = self::classify($row, $suspend);
        $step = intake_rules::change_progress($expectedoutcome, $state['outcome']);
        if ($step === intake_rules::REFUSED) {
            $reason = $state['outcome'] === 'rejected' ? $state['reason'] : 'changed';
            return self::answer($row, $state['outcome'], 'refused', intake_service::reason($reason));
        }
        if ($step === intake_rules::FINISH) {
            return self::answer($row, $state['outcome'], 'already_done', '');
        }
        if (!class_exists(self::ACTIONS)) {
            return self::answer($row, $state['outcome'], 'refused', intake_service::reason('actions_unavailable'));
        }
        $actions = self::ACTIONS;
        try {
            if ($suspend) {
                $actions::do_suspend($state['userid']);
            } else {
                $actions::do_reactivate($state['userid']);
            }
        } catch (\moodle_exception $e) {
            return self::answer($row, $state['outcome'], 'refused', intake_service::reason('action_refused'));
        }
        return self::answer($row, $state['outcome'], 'done', '');
    }

    /**
     * Where one row stands now.
     *
     * @param array $row
     * @param bool $suspend
     * @return array ['outcome' => string, 'reason' => key, 'userid' => int|null]
     */
    protected static function classify(array $row, bool $suspend): array {
        global $USER;
        if (!intake_service::valid_email($row)) {
            return ['outcome' => 'rejected', 'reason' => 'bad_email', 'userid' => null];
        }
        $accounts = intake_service::match_accounts(intake_service::normalise_email((string)$row['email']));
        if (count($accounts) !== 1) {
            return ['outcome' => 'rejected', 'reason' => $accounts ? 'duplicate_accounts' : 'no_account',
                'userid' => null];
        }
        $account = reset($accounts);
        $userid = (int)$account->id;
        if (is_siteadmin($userid)) {
            return ['outcome' => 'rejected', 'reason' => 'site_admin', 'userid' => $userid];
        }
        if ($userid === (int)$USER->id) {
            return ['outcome' => 'rejected', 'reason' => 'own_account', 'userid' => $userid];
        }
        $done = (bool)$account->suspended === $suspend;
        return ['outcome' => $done ? 'unchanged' : 'would_change', 'reason' => '', 'userid' => $userid];
    }

    /**
     * @param array $row
     * @param string $outcome
     * @param string $status
     * @param string $reason
     * @return array
     */
    protected static function answer(array $row, string $outcome, string $status, string $reason): array {
        return ['row' => (int)$row['row'], 'outcome' => $outcome, 'status' => $status, 'reason' => $reason];
    }
}
