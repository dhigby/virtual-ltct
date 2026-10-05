<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * May a cohort be enrolled into a course, and with which role? (spec 008, research R4, R7)
 *
 * The table of research R7, from spec 002 R8, R10, R11 and FR-019:
 *
 *   cohort                  course in ltct:published   course in ltct:org:<K>    anywhere else
 *   ltct:org:<K>            Student                    Student, own K only       refused
 *   ltct:org:<K>:managers   refused                    orgmanager, own K only    refused
 *   ltct:mentors            refused                    refused                   refused
 *   any other cohort        refused (plan decision 8)  refused                   refused
 *
 * "Anywhere else" is every other category (ltct:pilots among them: pilot enrolment is the
 * coordinator's, by the manual method, at stage 7), ltct:officehours, and any course whose
 * idnumber is not ltct:<slug>. Keys compare exactly, so ltct:org:fixture-a is never allowed
 * into ltct:org:fixture-ab's course.
 *
 * PURE: no Moodle call, no database read, so tests/admin_harness.php tests every cell
 * without Moodle. The caller reads the three idnumbers.
 */
class enrolment_rules {

    /** The shared category every organisation's cohort may be enrolled into (spec 002). */
    const PUBLISHED_CATEGORY = 'ltct:published';

    /** Prefix of an organisation's cohort and of its own category: ltct:org:<key>. */
    const ORG_PREFIX = 'ltct:org:';

    /** Suffix of an organisation's managers cohort: ltct:org:<key>:managers. */
    const MANAGERS_SUFFIX = ':managers';

    /** The site team's mentors cohort (spec 002), which belongs to no organisation. */
    const MENTORS_COHORT = 'ltct:mentors';

    /** The office-hours course (spec 011, officehours::COURSE): never a cohort-sync target. */
    const OFFICEHOURS_COURSE = 'ltct:officehours';

    /** An organisation key, as site_config.py validates them. */
    const KEY_PATTERN = '/^[a-z][a-z0-9-]*$/D';

    /** A course this repo publishes: ltct:<slug>, not a module or an organisation idnumber. */
    const COURSE_PATTERN = '/^ltct:[^:\s]+$/D';

    /** The roles an allowed pair is enrolled with, by role shortname. */
    const ROLE_STUDENT = 'student';
    const ROLE_ORGMANAGER = 'orgmanager';

    /**
     * Decide one (cohort, course) pair.
     *
     * @param string $cohortidnumber the cohort's idnumber
     * @param string $courseidnumber the course's idnumber
     * @param string $categoryidnumber the idnumber of the category the course sits in
     * @return array ['role' => role shortname or null, 'reason' => string]. A null role is a
     *               refusal, and its reason is the <key> of an 'admin:reason:<key>' string.
     */
    public static function decide(string $cohortidnumber, string $courseidnumber, string $categoryidnumber): array {
        if (!preg_match(self::COURSE_PATTERN, $courseidnumber) || $courseidnumber === self::OFFICEHOURS_COURSE) {
            return self::refused('course_not_ltct');
        }
        $shared = $categoryidnumber === self::PUBLISHED_CATEGORY;
        $categorykey = self::org_key_of_category($categoryidnumber);
        if (!$shared && $categorykey === null) {
            return self::refused('course_category');     // A pilot, or no category of ours.
        }

        [$kind, $key] = self::cohort_kind($cohortidnumber);
        switch ($kind) {
            case 'org':
                if ($shared || $categorykey === $key) {
                    return ['role' => self::ROLE_STUDENT, 'reason' => ''];
                }
                return self::refused('other_org_course');
            case 'managers':
                if ($shared) {
                    return self::refused('managers_shared');   // Spec 002 R2: the inspector fails on it.
                }
                if ($categorykey === $key) {
                    return ['role' => self::ROLE_ORGMANAGER, 'reason' => ''];
                }
                return self::refused('other_org_course');
            case 'mentors':
                return self::refused('mentors_cohort');
            default:
                return self::refused('cohort_kind');       // Plan decision 8.
        }
    }

    /**
     * What an apply does when a (cohort, course) pair's outcome now is $current and its preview
     * said $expected (research R15). Outcomes: would_add, would_enable, would_disable, already,
     * refused. The only path is "would change -> already".
     *
     * @param string $expected the outcome the preview reported (the call's expectedoutcome)
     * @param string $current the outcome now
     * @return string intake_rules::APPLY, FINISH or REFUSED
     */
    public static function progress(string $expected, string $current): string {
        $changes = ['would_add', 'would_enable', 'would_disable'];
        if (!in_array($expected, $changes, true) && $expected !== 'already') {
            return intake_rules::REFUSED;
        }
        if ($expected === $current) {
            return intake_rules::APPLY;
        }
        if ($current === 'already' && in_array($expected, $changes, true)) {
            return intake_rules::FINISH;
        }
        return intake_rules::REFUSED;
    }

    /**
     * What kind of cohort an idnumber names.
     *
     * @param string $idnumber
     * @return array [kind, organisation key or null]; kind is org, managers, mentors or other
     */
    public static function cohort_kind(string $idnumber): array {
        if ($idnumber === self::MENTORS_COHORT) {
            return ['mentors', null];
        }
        if (strpos($idnumber, self::ORG_PREFIX) !== 0) {
            return ['other', null];
        }
        $key = substr($idnumber, strlen(self::ORG_PREFIX));
        $managers = strlen($key) > strlen(self::MANAGERS_SUFFIX)
            && substr($key, -strlen(self::MANAGERS_SUFFIX)) === self::MANAGERS_SUFFIX;
        if ($managers) {
            $key = substr($key, 0, -strlen(self::MANAGERS_SUFFIX));
        }
        if (!preg_match(self::KEY_PATTERN, $key)) {
            return ['other', null];
        }
        return [$managers ? 'managers' : 'org', $key];
    }

    /**
     * The organisation key of an organisation's own category, or null for any other category.
     *
     * @param string $categoryidnumber
     * @return string|null
     */
    protected static function org_key_of_category(string $categoryidnumber): ?string {
        if (strpos($categoryidnumber, self::ORG_PREFIX) !== 0) {
            return null;
        }
        $key = substr($categoryidnumber, strlen(self::ORG_PREFIX));
        return preg_match(self::KEY_PATTERN, $key) ? $key : null;
    }

    /**
     * @param string $reason
     * @return array
     */
    protected static function refused(string $reason): array {
        return ['role' => null, 'reason' => $reason];
    }
}
