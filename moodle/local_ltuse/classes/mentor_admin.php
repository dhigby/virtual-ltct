<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\organisation\access;

/**
 * Who may assign and end a learner's mentors on the Manage mentors page (spec 003, research R7
 * Phase B, under spec 002's decision of 2026-10-02 and research R10).
 *
 * The page manages the user-context mentor relationship only: the learner's default mentor.
 * A mentor for one course is spec 008's.
 *
 * Allows:
 *   the site team   moodle/role:assign in the learner's user context, with `mentor` among
 *                   get_assignable_roles() there. Any learner, staff included.
 *   a manager       local_ltuse\organisation\access::may_manage_account(): the learner is in
 *                   one of the viewer's organisations (field and cohort agree) and is a
 *                   learner, not staff, a mentor or another manager (spec 002 R10). Those
 *                   stay with the site team.
 * Refuses everyone, the site team included, for oneself, and for a learner who is missing or
 * deleted.
 *
 * PURE: no Moodle call, no database read. mentors.php and lib.php gather the inputs on every
 * request, and tests/mentor_admin_harness.php tests every case without Moodle. A fact the
 * caller did not supply counts against the action.
 */
class mentor_admin {

    /** The hidden system cohort the Add picker draws from (moodle/site/organisations.yaml). */
    const MENTORS_COHORT = 'ltct:mentors';

    /**
     * May viewer V assign and end person P's mentors?
     *
     * @param int $viewerid V's user id
     * @param bool $learnerexists P's user record exists and is not deleted
     * @param bool $canassigncore V holds moodle/role:assign in P's user context and `mentor`
     *     is in get_assignable_roles() there
     * @param array $managedkeys V's managed organisation keys,
     *     from local_ltuse_managed_organisation_keys()
     * @param array $person facts about P, as organisation\access documents them,
     *     from local_ltuse_organisation_person_facts()
     * @return bool
     */
    public static function decide(int $viewerid, bool $learnerexists, bool $canassigncore,
            array $managedkeys, array $person): bool {
        if (!$learnerexists || !isset($person['id'])) {
            return false;
        }
        if ((int)$person['id'] === $viewerid) {
            return false;
        }
        if ($canassigncore) {
            return true;
        }
        return access::may_manage_account($viewerid, $managedkeys, $person);
    }
}
