<?php
namespace local_ltuse\organisation;

defined('MOODLE_INTERNAL') || die();

/**
 * What an organisation manager may do for one person (spec 002 amendment 2026-10-02, R10).
 *
 * Two predicates and the per-action rules (data-model "Organisation access decision"):
 *
 *   is_org_member_of_manager(V, P)  V is not P, P's ltct_org is one of V's managed keys, and P
 *                                   is in that key's ltct:org:<key> cohort, so the field and
 *                                   the cohort agree. Says nothing about P's role. The profile
 *                                   hook's one FORCE_ALLOW and the organisation page's list.
 *   may_manage_account(V, P)        the first, and P is a learner: not deleted, not a site
 *                                   admin, not a course contact, no system or category role,
 *                                   in no managers cohort, not in ltct:mentors. Every
 *                                   management action: enrol, unenrol, reset link, suspend,
 *                                   reactivate, mentors.
 *
 * PURE: no Moodle call, no database read. The caller gathers the facts (lib.php, the
 * organisation pages), so tests/org_access_harness.php tests every case without Moodle. A
 * fact the caller did not supply counts against the action: a missing key fails closed.
 *
 * $person is an array of facts about P:
 *
 *   id           int     P's user id
 *   ltct_org     string  P's ltct_org profile value, '' when empty
 *   org_cohorts  array   the keys of the ltct:org:<key> member cohorts P is in
 *   deleted      bool    the user record is deleted
 *   siteadmin    bool    P is a site admin
 *   coursecontact bool   P is staff: holds any role but student in any course
 *   highrole     bool    P holds a role assignment at system or any category context
 *   managers     bool    P is in any ltct:org:<key>:managers cohort
 *   mentor       bool    P is in ltct:mentors
 *
 * $managedkeys are the organisation keys of the managers cohorts V is in, read on every
 * request (local_ltuse_managed_organisation_keys()), so leaving one ends everything at once.
 */
class access {

    /** customchar1 of the organisation-enrolment instance: how our code finds it (R10). */
    const ENROL_MARKER = 'ltct:orgenrol';

    /** The enrol plugin that instance belongs to: core's enrol_self, several per course. */
    const ENROL_PLUGIN = 'self';

    /** Course idnumbers this repo publishes start with this (local_ltuse\util). */
    const COURSE_PREFIX = 'ltct:';

    /** The shared category a manager may enrol their learners into (ltct:published). */
    const PUBLISHED_CATEGORY = 'ltct:published';

    /** Prefix of an organisation's own category idnumber, ltct:org:<key>. */
    const ORG_CATEGORY_PREFIX = 'ltct:org:';

    /** The pilots category: the publisher may place a course there, a manager never enrols into it. */
    const PILOTS_CATEGORY = 'ltct:pilots';

    /**
     * Does viewer V manage the organisation person P belongs to?
     *
     * @param int $viewerid V's user id
     * @param array $managedkeys V's managed organisation keys
     * @param array $person facts about P (class comment); uses id, ltct_org, org_cohorts
     * @return bool
     */
    public static function is_org_member_of_manager(int $viewerid, array $managedkeys, array $person): bool {
        if (!isset($person['id']) || (int)$person['id'] === $viewerid) {
            return false;
        }
        $org = trim((string)($person['ltct_org'] ?? ''));
        if ($org === '') {
            return false;
        }
        // Keys compare exactly: they are stored menu values, never display names.
        if (!in_array($org, array_map('strval', $managedkeys), true)) {
            return false;
        }
        // The field alone is not enough: a learner whose field is set but who has not yet
        // joined the cohort is not managed until they have (R10).
        return in_array($org, array_map('strval', $person['org_cohorts'] ?? []), true);
    }

    /**
     * May viewer V manage person P's account and enrolments?
     *
     * @param int $viewerid V's user id
     * @param array $managedkeys V's managed organisation keys
     * @param array $person facts about P (class comment); every key is used
     * @return bool
     */
    public static function may_manage_account(int $viewerid, array $managedkeys, array $person): bool {
        if (!self::is_org_member_of_manager($viewerid, $managedkeys, $person)) {
            return false;
        }
        // Staff, other managers and mentors are the site team's to manage. Missing means yes.
        foreach (['deleted', 'siteadmin', 'coursecontact', 'highrole', 'managers', 'mentor'] as $key) {
            if (!array_key_exists($key, $person) || $person[$key]) {
                return false;
            }
        }
        return true;
    }

    /**
     * May a manager enrol P into this course? Assumes may_manage_account() already held.
     *
     * Only a course this repo publishes, in the shared published category or in P's own
     * organisation's category. Never a pilot course (stage 7 is the pilot coordinator's),
     * and never another organisation's category, even one the same manager also manages.
     *
     * @param array $person facts about P; uses ltct_org
     * @param string $courseidnumber the course's idnumber
     * @param string $categoryidnumber the idnumber of the category the course sits in
     * @return bool
     */
    public static function may_enrol_into(array $person, string $courseidnumber, string $categoryidnumber): bool {
        $slug = substr($courseidnumber, strlen(self::COURSE_PREFIX));
        if (strpos($courseidnumber, self::COURSE_PREFIX) !== 0 || $slug === '' || strpos($slug, ':') !== false) {
            return false;
        }
        if ($categoryidnumber === self::PUBLISHED_CATEGORY) {
            return true;
        }
        $org = trim((string)($person['ltct_org'] ?? ''));
        return $org !== '' && $categoryidnumber === self::ORG_CATEGORY_PREFIX . $org;
    }

    /**
     * May the publisher place a course in this category (local_ltuse_place_course, R11)? Only
     * ltct:published, ltct:pilots or one organisation's ltct:org:<key>: never the parent
     * ltct:organisations, and never a category this repo does not own. Not a manager rule; it
     * lives here because it reads the same category idnumbers, and is tested with them.
     *
     * @param string $categoryidnumber
     * @return bool
     */
    public static function is_placement_category(string $categoryidnumber): bool {
        if ($categoryidnumber === self::PUBLISHED_CATEGORY || $categoryidnumber === self::PILOTS_CATEGORY) {
            return true;
        }
        return (bool)preg_match('/^ltct:org:[^:]+$/', $categoryidnumber);
    }

    /**
     * May a manager end an enrolment through this instance? Assumes may_manage_account() held.
     *
     * Only the organisation-enrolment instance. A cohort-sync enrolment is the site team's,
     * and a manual (pilot) enrolment is never touched.
     *
     * @param string $enrolplugin the instance's enrol column
     * @param string|null $marker the instance's customchar1
     * @return bool
     */
    public static function may_unenrol_from(string $enrolplugin, ?string $marker): bool {
        return $enrolplugin === self::ENROL_PLUGIN && $marker === self::ENROL_MARKER;
    }
}
