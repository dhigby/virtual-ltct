<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

use core_user;

/**
 * Whether an organisation manager may open someone's profile (spec 002, research R9).
 *
 * Groups alone cannot tell a learner who has left an organisation from one who is still in
 * it, because core keeps a suspended enrolment and its group membership. The organisation
 * profile field can, since it is the record of membership. So a member of any
 * ltct:org:<key>:managers cohort is refused the profile of anyone whose ltct_org is not one
 * of the organisations they manage.
 *
 * This is the decision only. It is a pure function of its five inputs, so
 * tests/profile_access_harness.php can test it without Moodle. lib.php gathers the inputs
 * (local_ltuse_control_view_profile). The outcome only ever takes access away: it is
 * VIEWPROFILE_PREVENT or VIEWPROFILE_DO_NOT_PREVENT, never VIEWPROFILE_FORCE_ALLOW, so
 * core's own checks still decide everything this does not refuse.
 */
class profile_access {

    /**
     * Decide one profile view.
     *
     * @param bool $isself the viewer is the person being viewed
     * @param array $managedkeys organisation keys of the managers cohorts the viewer is in
     * @param string $viewedorg the viewed person's ltct_org value, '' when empty
     * @param bool $viewedisstaff the viewed person is a course contact or the site team
     * @param bool $viewerhasviewalldetails the viewer has moodle/user:viewalldetails in the
     *        viewed person's context
     * @return int core_user::VIEWPROFILE_PREVENT or core_user::VIEWPROFILE_DO_NOT_PREVENT
     */
    public static function decide(bool $isself, array $managedkeys, string $viewedorg,
            bool $viewedisstaff, bool $viewerhasviewalldetails): int {
        // Not a manager, viewing yourself, or the site team (or a spec 003 mentor): this
        // hook has nothing to say.
        if ($isself || !$managedkeys || $viewerhasviewalldetails) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }
        $viewedorg = trim($viewedorg);
        if ($viewedorg === '') {
            // No organisation. Course teachers and the site team have none, and a manager
            // may see them. Anyone else with no organisation belongs to no manager.
            return $viewedisstaff ? core_user::VIEWPROFILE_DO_NOT_PREVENT
                                  : core_user::VIEWPROFILE_PREVENT;
        }
        // Keys compare exactly: they are the stored menu values, never display names.
        $managed = array_map('strval', $managedkeys);
        return in_array($viewedorg, $managed, true) ? core_user::VIEWPROFILE_DO_NOT_PREVENT
                                                    : core_user::VIEWPROFILE_PREVENT;
    }
}
