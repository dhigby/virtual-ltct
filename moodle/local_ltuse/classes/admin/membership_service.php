<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Who is in a managers cohort and in the mentors cohort (spec 008, US3; research R9, R15).
 *
 * Only two kinds of cohort are ever written here: an organisation's managers cohort,
 * ltct:org:<key>:managers, and the site team's ltct:mentors. Refused:
 *
 *   - an organisation's own cohort, ltct:org:<key>: its members follow the organisation field
 *     through tool_dynamic_cohorts (research R3), and a hand-made member would be removed on
 *     the next refresh or, worse, kept against the field;
 *   - any cohort with a component: a plugin owns its members;
 *   - every other cohort (plan decision 8).
 *
 * Writes go through core's cohort_add_member() and cohort_remove_member(). A removal checks
 * membership first, because cohort_remove_member() fires cohort_member_removed even for someone
 * who was never a member (cohort/lib.php).
 *
 * An ALTC covering several Areas is one row per Area entry (spec 002 FR-015). Adding a member of
 * organisation K to K's managers cohort is allowed, and the preview notes it: from then on only
 * the site team can manage that person's account (access::may_manage_account() excludes
 * managers, by design).
 *
 * The mentor part (role assignments in the learner's user context) lands with user story 5
 * (task T061).
 *
 * The external functions check local/ltuse:administer, then moodle/cohort:assign in each
 * cohort's context. Nothing here checks a capability.
 */
class membership_service {

    /** The actions a managers file may name. */
    const ACTIONS = ['add', 'remove'];

    /**
     * Find every cohort the rows name. Any missing is a file-level refusal naming it.
     *
     * @param array $rows each with cohortidnumber
     * @return array ['refusal' => string, 'cohorts' => [idnumber => stdClass id, idnumber,
     *               contextid, component]]
     */
    public static function resolve(array $rows): array {
        global $DB;
        $cohorts = [];
        $missing = [];
        foreach ($rows as $row) {
            $idnumber = trim((string)($row['cohortidnumber'] ?? ''));
            if (array_key_exists($idnumber, $cohorts) || in_array($idnumber, $missing, true)) {
                continue;
            }
            $cohort = $idnumber === '' ? false
                : $DB->get_record('cohort', ['idnumber' => $idnumber], 'id, idnumber, contextid, component');
            if ($cohort) {
                $cohorts[$idnumber] = $cohort;
            } else {
                $missing[] = $idnumber === '' ? '(no cohort)' : $idnumber;
            }
        }
        return [
            'refusal' => $missing ? get_string('admin:refusal:missing', 'local_ltuse', implode(', ', $missing)) : '',
            'cohorts' => $cohorts,
        ];
    }

    /**
     * Preview a whole managers file. Changes nothing.
     *
     * @param array $rows each [row, email, cohortidnumber, action]
     * @param array $cohorts resolve()'s cohorts
     * @param bool $showpeople
     * @return array ['refusal' => '', 'rows' => [[row, key, outcome, reason, changes[]]]]
     */
    public static function preview(array $rows, array $cohorts, bool $showpeople): array {
        $results = [];
        foreach ($rows as $row) {
            $state = self::classify($row, $cohorts);
            $idnumber = trim((string)$row['cohortidnumber']);
            $results[] = [
                'row' => (int)$row['row'],
                'key' => $showpeople ? trim((string)$row['email']) : masking::mask_email((string)$row['email']),
                'outcome' => $state['outcome'],
                'reason' => $state['reason'] === '' ? '' : get_string('admin:reason:' . $state['reason'], 'local_ltuse',
                    $state['orgkey']),
                'changes' => $state['outcome'] === 'would_change'
                    ? [strtolower(trim((string)$row['action'])) . ':' . $idnumber] : [],
            ];
        }
        return ['refusal' => '', 'rows' => $results];
    }

    /**
     * Apply one row, if it is still what the preview said or already done.
     *
     * @param array $row [row, email, cohortidnumber, action]
     * @param array $cohorts resolve()'s cohorts for this row
     * @param string $expectedoutcome
     * @return array [row, outcome, status: done|already_done|refused, reason]
     */
    public static function apply_row(array $row, array $cohorts, string $expectedoutcome): array {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $state = self::classify($row, $cohorts);
        $step = intake_rules::change_progress($expectedoutcome, $state['outcome']);
        if ($step === intake_rules::REFUSED) {
            $reason = $state['outcome'] === 'rejected' ? $state['reason'] : 'changed';
            return self::answer($row, $state['outcome'], 'refused',
                get_string('admin:reason:' . $reason, 'local_ltuse', $state['orgkey']));
        }
        if ($step === intake_rules::FINISH) {
            return self::answer($row, $state['outcome'], 'already_done', '');
        }
        $cohortid = (int)$state['cohortid'];
        if ($state['action'] === 'add') {
            cohort_add_member($cohortid, $state['userid']);
        } else if (cohort_is_member($cohortid, $state['userid'])) {
            // Checked again here: cohort_remove_member() announces a removal even for a
            // non-member, and a racing run may have removed the person already.
            cohort_remove_member($cohortid, $state['userid']);
        } else {
            return self::answer($row, 'unchanged', 'already_done', '');
        }
        return self::answer($row, $state['outcome'], 'done', '');
    }

    /**
     * Where one row stands now.
     *
     * @param array $row
     * @param array $cohorts
     * @return array ['outcome' => would_change|unchanged|rejected, 'reason' => key ('' or an
     *               'admin:reason:<key>' string, which may be a note on a row that proceeds),
     *               'orgkey' => string, 'userid' => int|null, 'cohortid' => int|null, 'action' => string]
     */
    protected static function classify(array $row, array $cohorts): array {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $idnumber = trim((string)($row['cohortidnumber'] ?? ''));
        $action = strtolower(trim((string)($row['action'] ?? '')));
        $state = ['outcome' => 'rejected', 'reason' => '', 'orgkey' => '', 'userid' => null, 'cohortid' => null,
            'action' => $action];
        $cohort = $cohorts[$idnumber] ?? null;
        if (!$cohort || !in_array($action, self::ACTIONS, true)) {
            return array_merge($state, ['reason' => 'facts']);
        }
        [$kind, $key] = enrolment_rules::cohort_kind($idnumber);
        if ($kind === 'org') {
            return array_merge($state, ['reason' => 'org_cohort_members']);
        }
        if (!in_array($kind, ['managers', 'mentors'], true)) {
            return array_merge($state, ['reason' => 'cohort_not_managed']);
        }
        if ((string)$cohort->component !== '') {
            return array_merge($state, ['reason' => 'cohort_component']);
        }
        if (!intake_service::valid_email($row)) {
            return array_merge($state, ['reason' => 'bad_email']);
        }
        $accounts = intake_service::match_accounts(intake_service::normalise_email((string)$row['email']));
        if (count($accounts) !== 1) {
            return array_merge($state, ['reason' => $accounts ? 'duplicate_accounts' : 'no_account']);
        }
        $userid = (int)reset($accounts)->id;
        $member = cohort_is_member((int)$cohort->id, $userid);
        $changes = $action === 'add' ? !$member : $member;
        $state = array_merge($state, ['outcome' => $changes ? 'would_change' : 'unchanged', 'userid' => $userid,
            'cohortid' => (int)$cohort->id]);
        if ($changes && $action === 'add' && $kind === 'managers' && intake_service::organisation_of($userid) === $key) {
            // Allowed (spec 002), and worth saying: a manager is never managed by a manager.
            $state['reason'] = 'manages_own';
            $state['orgkey'] = $key;
        }
        return $state;
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
