<?php
namespace local_ltuse\pathway;

use local_ltuse\organisation\access;

defined('MOODLE_INTERNAL') || die();

/**
 * Who may see whose pathways (spec 006 R8, FR-012).
 *
 * One predicate, may_view(V, L, facts): true when any of these holds.
 *
 *   V is L                    a learner always sees their own pathways
 *   facts['mentor']           V holds local/ltuse:viewmenteeprogress in L's user context: 003's
 *                             mentor relationship
 *   organisation manager      002's access::is_org_member_of_manager(V, managedkeys, person):
 *                             by managers-cohort membership and L's organisation member cohort,
 *                             never the ltct_org value alone
 *   facts['siteconfig']       V has moodle/site:config: the site team
 *
 * PURE: no Moodle call, no database read. The caller (pathways.php) gathers the facts, so
 * tests/pathway_harness.php tests every case without Moodle. A fact the caller did not supply
 * counts against the viewer: a missing key fails closed, and so does a person whose id is not L.
 *
 * $facts:
 *
 *   mentor       bool   has_capability('local/ltuse:viewmenteeprogress', context_user(L), V)
 *   siteconfig   bool   has_capability('moodle/site:config', context_system(), V)
 *   managedkeys  array  V's managed organisation keys (local_ltuse_managed_organisation_keys())
 *   person       array  002's facts about L: id, ltct_org, org_cohorts (organisation\access)
 *
 * Names are shown through fullname() only; this class decides access, never display.
 */
class viewer {

    /**
     * May viewer V see learner L's pathways and their progress?
     *
     * @param int $viewerid V's user id
     * @param int $learnerid L's user id
     * @param array $facts what the caller knows about V and L (class comment)
     * @return bool
     */
    public static function may_view(int $viewerid, int $learnerid, array $facts): bool {
        // Nobody (not logged in, guest id 0) and no one in particular: no.
        if ($viewerid <= 0 || $learnerid <= 0) {
            return false;
        }
        if ($viewerid === $learnerid) {
            return true;
        }
        if (self::fact_true($facts, 'siteconfig')) {
            return true;
        }
        if (self::fact_true($facts, 'mentor')) {
            return true;
        }
        return self::is_org_manager($viewerid, $learnerid, $facts);
    }

    /**
     * Is a boolean fact supplied and true? Missing or non-boolean is false.
     *
     * @param array $facts
     * @param string $key
     * @return bool
     */
    private static function fact_true(array $facts, string $key): bool {
        return array_key_exists($key, $facts) && $facts[$key] === true;
    }

    /**
     * Does V manage L's organisation (002)? Every fact must be there, and the person facts must
     * be about L, not someone else.
     *
     * @param int $viewerid
     * @param int $learnerid
     * @param array $facts
     * @return bool
     */
    private static function is_org_manager(int $viewerid, int $learnerid, array $facts): bool {
        if (!isset($facts['managedkeys']) || !is_array($facts['managedkeys'])) {
            return false;
        }
        if (!isset($facts['person']) || !is_array($facts['person'])) {
            return false;
        }
        $person = $facts['person'];
        if (!isset($person['id']) || (int)$person['id'] !== $learnerid) {
            return false;
        }
        if (!array_key_exists('ltct_org', $person) || !array_key_exists('org_cohorts', $person)) {
            return false;
        }
        return access::is_org_member_of_manager($viewerid, $facts['managedkeys'], $person);
    }
}
