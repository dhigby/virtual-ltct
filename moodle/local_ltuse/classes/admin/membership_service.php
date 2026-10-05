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
 * The mentor part (US5, research R9): spec 003's mentor relationship, a manual assignment of
 * the mentor role in the learner's user context, component '', made with role_assign() (which
 * is idempotent) and ended with role_unassign(). Manual, so core_role_unassign_roles and 003's
 * own page can end it too (003 R1). The mentor must be in ltct:mentors, as 003's mentors.php
 * requires. 003's role_assigned / role_unassigned observer makes and removes the message
 * contacts, and spec 008's observer the course-mentor enrolments, so bulk assignment gets both
 * for free. Ending all of one mentor's relationships is the same as cli/mentor_contacts.php
 * --end-all (003 R6), previewed and applied one learner per call.
 *
 * An end-all row names a learner the operator never typed, so it carries a reference instead
 * of an id: the first 16 hex of SHA-256 over the site identifier, the mentor's and the
 * learner's user ids. Stable across runs on one site, meaningless anywhere else, and never a
 * database id the operator must type back (contracts/admin-service.md).
 *
 * The external functions check local/ltuse:administer, then moodle/cohort:assign in each
 * cohort's context, or moodle/role:assign for a mentor relationship. Nothing here checks a
 * capability.
 */
class membership_service {

    /** The actions a managers file may name. */
    const ACTIONS = ['add', 'remove'];

    /** Length of an end-all row's reference, in hex characters. */
    const REF_LENGTH = 16;

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

    // --- mentor relationships (US5, task T061) -----------------------------------------------

    /**
     * What every mentor preview and apply needs: spec 003's mentor role and the mentors cohort.
     * Either missing is a file-level refusal naming it.
     *
     * @return array ['refusal' => string, 'roleid' => int, 'cohortid' => int]
     */
    public static function mentor_setup(): array {
        global $DB;
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => \local_ltuse\mentoring::ROLE]);
        $cohortid = (int)$DB->get_field('cohort', 'id', ['idnumber' => enrolment_rules::MENTORS_COHORT]);
        $missing = [];
        if (!$roleid) {
            $missing[] = 'role ' . \local_ltuse\mentoring::ROLE;
        }
        if (!$cohortid) {
            $missing[] = enrolment_rules::MENTORS_COHORT;
        }
        return [
            'refusal' => $missing ? get_string('admin:refusal:missing', 'local_ltuse', implode(', ', $missing)) : '',
            'roleid' => $roleid,
            'cohortid' => $cohortid,
        ];
    }

    /**
     * Preview a whole mentors file: each row makes one mentor relationship. Changes nothing.
     *
     * @param array $rows each [row, learneremail, mentoremail]
     * @param array $setup mentor_setup()
     * @param bool $showpeople
     * @return array ['refusal' => '', 'rows' => [[row, key (the learner), outcome, reason, changes[], ref '']]]
     */
    public static function preview_mentors(array $rows, array $setup, bool $showpeople): array {
        $results = [];
        foreach ($rows as $row) {
            $state = self::classify_mentor($row, $setup);
            $email = (string)($row['learneremail'] ?? '');
            $results[] = [
                'row' => (int)$row['row'],
                'key' => $showpeople ? trim($email) : masking::mask_email($email),
                'outcome' => $state['outcome'],
                'reason' => intake_service::reason($state['reason']),
                'changes' => $state['outcome'] === 'would_change' ? ['assign:mentor'] : [],
                'ref' => '',
            ];
        }
        return ['refusal' => '', 'rows' => $results];
    }

    /**
     * Make one mentor relationship, if it is still what the preview said or already made.
     *
     * @param array $row [row, learneremail, mentoremail]
     * @param array $setup mentor_setup()
     * @param string $expectedoutcome
     * @return array [row, outcome, status: done|already_done|refused, reason]
     */
    public static function apply_mentor_row(array $row, array $setup, string $expectedoutcome): array {
        $state = self::classify_mentor($row, $setup);
        $step = intake_rules::change_progress($expectedoutcome, $state['outcome']);
        if ($step === intake_rules::REFUSED) {
            $reason = $state['outcome'] === 'rejected' ? $state['reason'] : 'changed';
            return self::answer($row, $state['outcome'], 'refused', intake_service::reason($reason));
        }
        if ($step === intake_rules::FINISH) {
            return self::answer($row, $state['outcome'], 'already_done', '');
        }
        // Component '': a manual assignment, as spec 003 requires (003 R1). Idempotent.
        role_assign($setup['roleid'], $state['mentorid'], \context_user::instance($state['learnerid'])->id);
        return self::answer($row, $state['outcome'], 'done', '');
    }

    /**
     * Where one mentors-file row stands now.
     *
     * @param array $row
     * @param array $setup
     * @return array ['outcome' => would_change|unchanged|rejected, 'reason' => key,
     *               'learnerid' => int|null, 'mentorid' => int|null]
     */
    protected static function classify_mentor(array $row, array $setup): array {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $state = ['outcome' => 'rejected', 'reason' => '', 'learnerid' => null, 'mentorid' => null];
        $learneremail = trim((string)($row['learneremail'] ?? ''));
        $mentoremail = trim((string)($row['mentoremail'] ?? ''));
        if (!validate_email($learneremail) || !validate_email($mentoremail)) {
            return array_merge($state, ['reason' => 'bad_email']);
        }
        $learners = intake_service::match_accounts(intake_service::normalise_email($learneremail));
        if (count($learners) !== 1) {
            return array_merge($state, ['reason' => $learners ? 'duplicate_accounts' : 'no_account']);
        }
        $mentors = intake_service::match_accounts(intake_service::normalise_email($mentoremail));
        if (count($mentors) !== 1) {
            return array_merge($state, ['reason' => $mentors ? 'mentor_duplicate_accounts' : 'mentor_no_account']);
        }
        $learnerid = (int)reset($learners)->id;
        $mentorid = (int)reset($mentors)->id;
        if ($learnerid === $mentorid) {
            return array_merge($state, ['reason' => 'own_mentor']);
        }
        if (!cohort_is_member((int)$setup['cohortid'], $mentorid)) {
            return array_merge($state, ['reason' => 'not_a_mentor']);
        }
        $context = \context_user::instance($learnerid, IGNORE_MISSING);
        if (!$context) {
            return array_merge($state, ['reason' => 'facts']);
        }
        $has = user_has_role_assignment($mentorid, (int)$setup['roleid'], $context->id);
        return ['outcome' => $has ? 'unchanged' : 'would_change', 'reason' => '', 'learnerid' => $learnerid,
            'mentorid' => $mentorid];
    }

    /**
     * The mentor an end-all names, and the learners they mentor now, by reference.
     *
     * @param string $email the mentor's email
     * @param array $setup mentor_setup()
     * @return array ['refusal' => string, 'mentorid' => int, 'learners' => [ref => learnerid]],
     *               the learners in reference order
     */
    public static function mentor_learners(string $email, array $setup): array {
        global $DB;
        $email = trim($email);
        $accounts = validate_email($email) ? intake_service::match_accounts(intake_service::normalise_email($email)) : [];
        if (count($accounts) !== 1) {
            return ['refusal' => get_string($accounts ? 'admin:refusal:mentorduplicate' : 'admin:refusal:mentornone',
                'local_ltuse'), 'mentorid' => 0, 'learners' => []];
        }
        $mentorid = (int)reset($accounts)->id;
        // Manual assignments only (component ''): the ones spec 003 makes and core can end.
        $sql = "SELECT ra.id, ctx.instanceid AS learnerid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :mentorid AND ra.roleid = :roleid AND ctx.contextlevel = :level
                       AND ra.component = :component";
        $learners = [];
        foreach ($DB->get_records_sql($sql, ['mentorid' => $mentorid, 'roleid' => (int)$setup['roleid'],
                'level' => CONTEXT_USER, 'component' => '']) as $r) {
            $learners[self::learner_ref($mentorid, (int)$r->learnerid)] = (int)$r->learnerid;
        }
        // SORT_STRING: an all-digit reference becomes an integer key in PHP.
        ksort($learners, SORT_STRING);
        return ['refusal' => '', 'mentorid' => $mentorid, 'learners' => $learners];
    }

    /**
     * Preview ending all of one mentor's relationships: one row per learner. Changes nothing.
     *
     * @param string $email the mentor's email
     * @param array $setup mentor_setup()
     * @param bool $showpeople
     * @return array ['refusal' => string, 'rows' => [[row, key, outcome, reason, changes[], ref]]]
     */
    public static function preview_end(string $email, array $setup, bool $showpeople): array {
        global $DB;
        $found = self::mentor_learners($email, $setup);
        if ($found['refusal'] !== '') {
            return ['refusal' => $found['refusal'], 'rows' => []];
        }
        $results = [];
        $n = 0;
        foreach ($found['learners'] as $ref => $learnerid) {
            $learneremail = (string)$DB->get_field('user', 'email', ['id' => $learnerid]);
            $results[] = [
                'row' => ++$n,
                'key' => $showpeople ? $learneremail : masking::mask_email($learneremail),
                'outcome' => 'would_change',
                'reason' => '',
                'changes' => ['end:mentor'],
                'ref' => (string)$ref,
            ];
        }
        return ['refusal' => '', 'rows' => $results];
    }

    /**
     * End one of the mentor's relationships, named by its reference. A reference no longer
     * among the mentor's learners was ended already (by an earlier run or another route).
     *
     * @param int $rownumber the row number the preview gave it
     * @param string $email the mentor's email
     * @param string $ref
     * @param array $setup mentor_setup()
     * @param string $expectedoutcome
     * @return array [row, outcome, status: done|already_done|refused, reason]
     */
    public static function apply_end_row(int $rownumber, string $email, string $ref, array $setup,
            string $expectedoutcome): array {
        $row = ['row' => $rownumber];
        $found = self::mentor_learners($email, $setup);
        if ($found['refusal'] !== '') {
            return self::answer($row, 'rejected', 'refused', $found['refusal']);
        }
        $learnerid = $found['learners'][$ref] ?? null;
        $step = intake_rules::change_progress($expectedoutcome, $learnerid ? 'would_change' : 'unchanged');
        if ($step === intake_rules::REFUSED) {
            return self::answer($row, $learnerid ? 'would_change' : 'unchanged', 'refused',
                intake_service::reason('changed'));
        }
        if ($step === intake_rules::FINISH) {
            return self::answer($row, 'unchanged', 'already_done', '');
        }
        role_unassign((int)$setup['roleid'], $found['mentorid'], \context_user::instance($learnerid)->id);
        return self::answer($row, 'would_change', 'done', '');
    }

    /**
     * An end-all row's reference to one learner of one mentor.
     *
     * @param int $mentorid
     * @param int $learnerid
     * @return string
     */
    public static function learner_ref(int $mentorid, int $learnerid): string {
        return substr(hash('sha256', get_site_identifier() . ':' . $mentorid . ':' . $learnerid), 0, self::REF_LENGTH);
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
