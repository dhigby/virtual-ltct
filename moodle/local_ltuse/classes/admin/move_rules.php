<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Per learner and course, what a move between organisations does (spec 008, research R4, R8).
 *
 * Moving a learner sets their ltct_org to the new key. tool_dynamic_cohorts then takes them out
 * of ltct:org:<old> and puts them in ltct:org:<new>, and core suspends the old cohort-sync
 * enrolments (enrol_cohort/unenrolaction = 3), keeping every grade and completion. So, per
 * course the learner is actively enrolled in now:
 *
 *   suspended_by_rule  an organisation-only course of the old organisation (category
 *                      ltct:org:<old>). Reported and allowed: spec 002 FR-017 suspends a mover's
 *                      enrolment there unless the maintainer declares a replacement.
 *   kept               another course where the learner keeps access: they have an active
 *                      enrolment there that is not the old organisation's cohort sync (their
 *                      Organisation enrolment, say), or the new organisation's cohort has an
 *                      enabled cohort-sync instance there.
 *   lost               a course whose only active enrolment is the old organisation's cohort
 *                      sync, with nothing from the new organisation to match it. The learner is
 *                      refused (FR-015, spec 002 FR-017): run `enrol mirror` first.
 *
 * and, per course the new organisation's cohort is enrolled in that the learner is not:
 *
 *   gained             the learner will be enrolled there by cohort sync.
 *
 * Per learner, the outcome is one of:
 *
 *   would_move          every course is kept, gained or suspended_by_rule: set ltct_org
 *   moved               ltct_org already holds the new key: nothing to do
 *   flagged_protection  the learner's effective protection is below the new organisation's
 *                       minimum: raise it on spec 016's page first; a bulk move never does
 *   lost                some course would be lost
 *   rejected            no live account, two accounts share the email, the account has no
 *                       organisation yet (that is intake's work), or a fact is missing
 *
 * "Further along" (research R15): the path is would_move -> moved.
 *
 * PURE: no Moodle call, no database read, so tests/admin_harness.php tests every case
 * without Moodle. move_service gathers the facts.
 *
 * $facts:
 *
 *   accounts    int     live accounts whose email matches, case-insensitively
 *   org         string  the account's ltct_org now, '' when empty
 *   neworg      string  the key the row moves them to
 *   effective   string  their effective protection level (016's effective_level()); 'none'
 *                       without 016
 *   newminimum  string  the new organisation's minimum (016's org_minimum()); 'none' without 016
 *   courses     array   one entry per course the learner is actively enrolled in:
 *                         course     string  the course idnumber
 *                         category   string  its category's idnumber
 *                         viaold     bool    active through the old organisation's cohort sync
 *                         other      bool    active through any other enrolment
 *                         newcohort  bool    the new organisation's cohort has an enabled
 *                                            cohort-sync instance there
 *   gained      array   course idnumbers where the new organisation's cohort has an enabled
 *                       instance and the learner is not actively enrolled
 *
 * A fact not supplied counts against the row: a missing key fails closed.
 */
class move_rules {

    /** The move path, in order (research R15). */
    const PATH = ['would_move', 'moved'];

    /** Prefix of an organisation's own category idnumber: ltct:org:<key>. */
    const ORG_PREFIX = 'ltct:org:';

    /** Per-course outcomes that change what the learner can reach. */
    const STOPPING_COURSES = ['suspended_by_rule', 'lost'];

    /**
     * Classify one learner's move.
     *
     * @param array $facts see the class comment
     * @return array ['outcome' => string, 'reason' => string ('' or the key of an
     *               'admin:reason:<key>' string), 'courses' => [['course' => idnumber,
     *               'outcome' => kept|gained|suspended_by_rule|lost]], 'lost' => idnumbers,
     *               'changes' => string[] what apply will do]
     */
    public static function classify(array $facts): array {
        $accounts = isset($facts['accounts']) ? (int)$facts['accounts'] : -1;
        $org = trim((string)($facts['org'] ?? ''));
        $neworg = trim((string)($facts['neworg'] ?? ''));
        $effective = intake_rules::rank((string)($facts['effective'] ?? ''));
        $minimum = intake_rules::rank((string)($facts['newminimum'] ?? ''));

        if ($accounts < 0 || $neworg === '' || $effective === null || $minimum === null
                || !isset($facts['courses']) || !is_array($facts['courses'])
                || !isset($facts['gained']) || !is_array($facts['gained'])) {
            return self::result('rejected', 'facts');
        }
        if ($accounts === 0) {
            return self::result('rejected', 'no_account');
        }
        if ($accounts > 1) {
            return self::result('rejected', 'duplicate_accounts');
        }
        if ($org === $neworg) {
            return self::result('moved', '');
        }
        if ($org === '') {
            return self::result('rejected', 'no_org');
        }

        $courses = [];
        $lost = [];
        $changes = ['set_org:' . $neworg];
        foreach ($facts['courses'] as $course) {
            $idnumber = (string)($course['course'] ?? '');
            if ($idnumber === '' || !array_key_exists('category', $course) || !array_key_exists('viaold', $course)
                    || !array_key_exists('other', $course) || !array_key_exists('newcohort', $course)) {
                return self::result('rejected', 'facts');
            }
            if ((string)$course['category'] === self::ORG_PREFIX . $org) {
                $outcome = 'suspended_by_rule';
                $changes[] = 'suspend:' . $idnumber;
            } else if ($course['other'] || $course['newcohort'] || !$course['viaold']) {
                $outcome = 'kept';
            } else {
                $outcome = 'lost';
                $lost[] = $idnumber;
            }
            $courses[] = ['course' => $idnumber, 'outcome' => $outcome];
        }
        $active = array_column($courses, 'course');
        foreach (array_map('strval', $facts['gained']) as $idnumber) {
            if ($idnumber !== '' && !in_array($idnumber, $active, true)) {
                $courses[] = ['course' => $idnumber, 'outcome' => 'gained'];
                $changes[] = 'enrol:' . $idnumber;
            }
        }

        if ($effective < $minimum) {
            return self::result('flagged_protection', 'protection_below_new', $courses, $lost);
        }
        if ($lost) {
            return self::result('lost', 'lost', $courses, $lost);
        }
        return self::result('would_move', '', $courses, [], $changes);
    }

    /**
     * What an apply does when the learner's outcome now is $current and its preview said
     * $expected (research R15).
     *
     * The same would_move applies only if the move would suspend or lose nothing the preview
     * did not show: every course now suspended_by_rule or lost must be in $expectedcourses with
     * that outcome. A course kept or gained since is no reason to refuse.
     *
     * @param string $expected the outcome the preview reported (the call's expectedoutcome)
     * @param string $current the outcome classify() gives now
     * @param array $expectedcourses [['course' => idnumber, 'outcome' => string]] from the preview
     * @param array $currentcourses classify()'s courses now
     * @return string intake_rules::APPLY, FINISH or REFUSED
     */
    public static function progress(string $expected, string $current, array $expectedcourses,
            array $currentcourses): string {
        if (!in_array($expected, self::PATH, true)) {
            return intake_rules::REFUSED;       // A stopping outcome is never applied.
        }
        if ($current === 'moved') {
            return intake_rules::FINISH;
        }
        if ($expected !== 'would_move' || $current !== 'would_move') {
            return intake_rules::REFUSED;
        }
        $seen = [];
        foreach ($expectedcourses as $course) {
            $seen[(string)($course['course'] ?? '') . '=' . (string)($course['outcome'] ?? '')] = true;
        }
        foreach ($currentcourses as $course) {
            if (in_array($course['outcome'], self::STOPPING_COURSES, true)
                    && !isset($seen[$course['course'] . '=' . $course['outcome']])) {
                return intake_rules::REFUSED;
            }
        }
        return intake_rules::APPLY;
    }

    /**
     * @param string $outcome
     * @param string $reason
     * @param array $courses
     * @param array $lost
     * @param array $changes
     * @return array
     */
    protected static function result(string $outcome, string $reason, array $courses = [], array $lost = [],
            array $changes = []): array {
        return ['outcome' => $outcome, 'reason' => $reason, 'courses' => $courses, 'lost' => $lost,
            'changes' => $changes];
    }
}
