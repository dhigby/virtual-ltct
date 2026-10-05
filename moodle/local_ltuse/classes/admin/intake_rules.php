<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Classify one intake row against what Moodle holds (spec 008, research R4, R5, R15).
 *
 * Outcomes are the keys of data-model section 2:
 *
 *   new                 no live account has the email: create, protect, organisation, courses
 *   will_set_org        the account exists with no organisation (an interrupted run, including
 *                       one stopped after creation but before protection settled): protect,
 *                       organisation, courses
 *   will_enrol          same organisation, protection at or above target, a listed course not
 *                       yet active: courses
 *   unchanged           same organisation, every listed course active: nothing
 *   flagged_other_org   the account is under another organisation: use move
 *   flagged_suspended   the account is suspended: reactivate deliberately (plan decision 9)
 *   flagged_protection  the account has an organisation and its effective protection is below
 *                       the row's target: raise it on spec 016's page
 *   waits               the target protection is above none and spec 016 cannot set it yet (not
 *                       installed, or level_available() false): no account is created
 *   rejected            two live accounts share the email, or a listed course is not one the
 *                       organisation may be enrolled into
 *
 * The target protection is the stricter of the row's own level and its organisation's minimum.
 *
 * "Further along" (research R15): the path is new -> will_set_org -> will_enrol -> unchanged.
 * An apply whose row has moved along that path since its preview finishes the rest, or reports
 * already done; any other change is refused.
 *
 * PURE: no Moodle call, no database read. intake_service gathers the facts, so
 * tests/admin_harness.php tests every case without Moodle.
 *
 * $facts:
 *
 *   accounts     int     live (not deleted) accounts whose email matches, case-insensitively
 *   org          string  the matched account's ltct_org, '' when empty or no account
 *   suspended    bool    the matched account is suspended
 *   effective    string  its effective protection level (016's effective_level()); 'none'
 *                        when 016 is not installed or there is no account
 *   settled      bool    016's is_settled(); true when 016 is not installed or no account
 *   protection   bool    spec 016 is installed
 *   available    array   the protection levels 016 may set now (level_available()); empty
 *                        when 016 is not installed
 *   roworg       string  the row's organisation key
 *   asked        string  the row's protection level, 'none' when not given
 *   orgminimum   string  the row organisation's minimum (016's org_minimum()); 'none' without 016
 *   courses      array   the row's course idnumbers
 *   active       array   the course idnumbers the account is already actively enrolled in
 *   allowed      array   the row's course idnumbers the organisation may be enrolled into
 *                        (enrolment_rules, as Student)
 *   loginclash   bool    with no matched account: another live account on this site has the
 *                        row's email as its username, so signing in with that email would reach
 *                        the other account (core looks a username up before an email)
 *
 * A fact not supplied counts against the row: a missing key fails closed.
 */
class intake_rules {

    /** Spec 016's protection levels, least protected first. */
    const LEVELS = ['none', 'email', 'firstname', 'pseudonym'];

    /** Targets whose new account gets the email as its username (username()); others stay neutral. */
    const EMAIL_USERNAME_LEVELS = ['none', 'email'];

    /** user.username is char(100) (lib/db/install.xml). */
    const USERNAME_MAX = 100;

    /** The intake path, in order; an outcome further along has been partly or fully applied. */
    const PATH = ['new', 'will_set_org', 'will_enrol', 'unchanged'];

    /** progress(): apply the previewed outcome as it is. */
    const APPLY = 'apply';
    /** progress(): the row moved along its path; finish what remains or report already done. */
    const FINISH = 'finish_or_already_done';
    /** progress(): the row changed some other way; refuse it. */
    const REFUSED = 'refused';

    /**
     * Classify one row.
     *
     * @param array $facts see the class comment
     * @return array ['outcome' => string, 'reason' => string ('' or a key of an
     *               'admin:reason:<key>' string), 'target' => the target level,
     *               'changes' => string[] what apply will do]
     */
    public static function classify(array $facts): array {
        $accounts = isset($facts['accounts']) ? (int)$facts['accounts'] : -1;
        $org = trim((string)($facts['org'] ?? ''));
        $roworg = trim((string)($facts['roworg'] ?? ''));
        $courses = array_values(array_unique(array_map('strval', $facts['courses'] ?? [])));
        $active = array_map('strval', $facts['active'] ?? []);
        $allowed = array_map('strval', $facts['allowed'] ?? []);
        $available = array_map('strval', $facts['available'] ?? []);
        $protection = !empty($facts['protection']);
        $target = self::stricter((string)($facts['asked'] ?? ''), (string)($facts['orgminimum'] ?? ''));
        $effective = (string)($facts['effective'] ?? '');
        $settled = !empty($facts['settled']);

        if ($accounts < 0 || $roworg === '' || $target === null) {
            return self::result('rejected', 'facts', $target);
        }
        if ($accounts > 1) {
            return self::result('rejected', 'duplicate_accounts', $target);
        }
        $exists = $accounts === 1;
        if (!$exists && !empty($facts['loginclash'])) {
            return self::result('rejected', 'login_clash', $target);
        }
        if ($exists && !array_key_exists('suspended', $facts)) {
            return self::result('rejected', 'facts', $target);
        }
        if ($exists && $facts['suspended']) {
            return self::result('flagged_suspended', 'suspended', $target);
        }
        if ($exists && $org !== '' && $org !== $roworg) {
            return self::result('flagged_other_org', 'other_org', $target);
        }
        if (array_diff($courses, $allowed)) {
            return self::result('rejected', 'course_not_allowed', $target);
        }

        $protects = $target !== 'none';
        $belowtarget = self::rank($effective) === null || self::rank($effective) < self::rank($target);
        // A protection write is needed for a new account, and for an interrupted one whose
        // protection has not reached the target or has not settled.
        $needsprotection = $protects && (!$exists || ($org === '' && ($belowtarget || !$settled)));
        if ($protects && !$protection) {
            return self::result('waits', 'protection_absent', $target);
        }
        if ($needsprotection && !in_array($target, $available, true)) {
            return self::result('waits', 'protection_unavailable', $target);
        }

        $missing = array_values(array_diff($courses, $active));
        $enrol = array_map(function($c) {
            return 'enrol:' . $c;
        }, $missing);
        $protect = $needsprotection ? ['set_protection:' . $target] : [];

        if (!$exists) {
            return self::result('new', '', $target,
                array_merge(['create'], $protect, ['set_org:' . $roworg], $enrol));
        }
        if ($org === '') {
            return self::result('will_set_org', '', $target, array_merge($protect, ['set_org:' . $roworg], $enrol));
        }
        if ($protects && $belowtarget) {
            return self::result('flagged_protection', 'protection_below', $target);
        }
        if ($missing) {
            return self::result('will_enrol', '', $target, $enrol);
        }
        return self::result('unchanged', '', $target);
    }

    /**
     * What an apply does when the row's outcome now is $current and its preview said $expected.
     *
     * @param string $expected the outcome the preview reported (the call's expectedoutcome)
     * @param string $current the outcome classify() gives now
     * @return string APPLY, FINISH or REFUSED
     */
    public static function progress(string $expected, string $current): string {
        $from = array_search($expected, self::PATH, true);
        $to = array_search($current, self::PATH, true);
        if ($from === false) {
            return self::REFUSED;       // A stopping outcome is never applied.
        }
        if ($expected === $current) {
            return self::APPLY;
        }
        if ($to !== false && $to > $from) {
            return self::FINISH;
        }
        return self::REFUSED;
    }

    /**
     * The same rule for a membership, mentor or suspension row, whose path is
     * would_change -> unchanged (research R15).
     *
     * @param string $expected the outcome the preview reported
     * @param string $current the outcome now
     * @return string APPLY, FINISH or REFUSED
     */
    public static function change_progress(string $expected, string $current): string {
        if (!in_array($expected, ['would_change', 'unchanged'], true)) {
            return self::REFUSED;
        }
        if ($current === 'unchanged') {
            return self::FINISH;
        }
        if ($expected === 'would_change' && $current === 'would_change') {
            return self::APPLY;
        }
        return self::REFUSED;
    }

    /**
     * A new account's username (research R2): its email, lowercased, the one thing the person
     * already knows. Null means a neutral generated one instead, when:
     *
     *   - the target is firstname or pseudonym: spec 016 refuses those levels for a username
     *     holding the real name (016 R13), and an email often does;
     *   - Moodle's PARAM_USERNAME cleaning changes the email (a '+' with extendedusernamechars
     *     off), so user_create_user() would refuse it;
     *   - it is longer than user.username holds (counted in bytes, never fewer than characters);
     *   - an account on this site already has it as its username. classify() has already
     *     rejected a row whose email is a live account's username (login_clash), so here that
     *     is a deleted account, which never signs in.
     *
     * Everyone signs in with their email (authloginviaemail), so the fallback costs them nothing.
     *
     * @param string $email the row's email, trimmed and lowercased
     * @param string $target the row's target protection (classify()'s 'target')
     * @param string $cleaned $email through clean_param(PARAM_USERNAME)
     * @param bool $taken an account on this site already has $email as its username
     * @return string|null
     */
    public static function username(string $email, string $target, string $cleaned, bool $taken): ?string {
        if ($email === '' || !in_array($target, self::EMAIL_USERNAME_LEVELS, true)) {
            return null;
        }
        if ($cleaned !== $email || strlen($email) > self::USERNAME_MAX || $taken) {
            return null;
        }
        return $email;
    }

    /**
     * The stricter of two levels; '' counts as none. Null when either is not a level.
     *
     * @param string $a
     * @param string $b
     * @return string|null
     */
    public static function stricter(string $a, string $b): ?string {
        $a = $a === '' ? 'none' : $a;
        $b = $b === '' ? 'none' : $b;
        $ra = self::rank($a);
        $rb = self::rank($b);
        if ($ra === null || $rb === null) {
            return null;
        }
        return $ra >= $rb ? $a : $b;
    }

    /**
     * A level's place in LEVELS, or null when it is not a level.
     *
     * @param string $level
     * @return int|null
     */
    public static function rank(string $level): ?int {
        $rank = array_search($level, self::LEVELS, true);
        return $rank === false ? null : (int)$rank;
    }

    /**
     * @param string $outcome
     * @param string $reason
     * @param string|null $target
     * @param array $changes
     * @return array
     */
    protected static function result(string $outcome, string $reason, ?string $target, array $changes = []): array {
        return ['outcome' => $outcome, 'reason' => $reason, 'target' => $target ?? 'none', 'changes' => $changes];
    }
}
