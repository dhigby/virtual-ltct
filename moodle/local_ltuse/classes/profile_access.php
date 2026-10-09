<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

use core_user;

/**
 * Whether an organisation manager may open someone's profile (spec 002, research R9,
 * amended 2026-10-02).
 *
 * Shared courses are open across organisations, so a manager is no longer enrolled with their
 * people and groups no longer say who belongs to whom. The organisation profile field and the
 * cohort it fills do, so this decides from them:
 *
 *   1. viewing yourself, or managing no organisation: DO_NOT_PREVENT;
 *   2. the viewer manages the viewed person's organisation and the person is in its cohort
 *      (organisation\access::is_org_member_of_manager): FORCE_ALLOW, whatever their role.
 *      The one place this hook grants access, so a manager reaches their own people while
 *      enrolled in nothing. Core lets any PREVENT win over it;
 *   3. the viewer has moodle/user:viewalldetails, is the person's mentor, or shares a course
 *      with them holding a role other than orgmanager: DO_NOT_PREVENT, so core decides, and
 *      a manager who is also a learner sees classmates from every organisation;
 *   4. otherwise PREVENT, except for staff with an empty ltct_org (a course teacher or the
 *      site team), whom core decides.
 *
 * This is the decision only: a pure function of its inputs, in the data model's order, so
 * tests/profile_access_harness.php tests it without Moodle. lib.php gathers the inputs
 * (local_ltuse_control_view_profile). The participant path may be passed as a callable, so
 * its course reads run only when cases 1 and 2 have not decided.
 */
class profile_access {

    /**
     * Decide one profile view.
     *
     * @param bool $isself the viewer is the person being viewed
     * @param array $managedkeys organisation keys of the managers cohorts the viewer is in
     * @param string $viewedorg the viewed person's ltct_org value, '' when empty
     * @param bool $managesviewed organisation\access::is_org_member_of_manager(viewer, viewed)
     * @param bool $viewedisstaff the viewed person is a course contact or the site team
     * @param bool $viewerhasviewalldetails the viewer has moodle/user:viewalldetails in the
     *        viewed person's context
     * @param bool $viewerismentor the viewer is the viewed person's mentor (spec 003, R9)
     * @param bool|callable $participantpath the viewer shares an active course with the viewed
     *        person in which the viewer holds a role other than orgmanager; a callable
     *        returning that is called only if needed
     * @return int a core_user::VIEWPROFILE_* constant
     */
    public static function decide(bool $isself, array $managedkeys, string $viewedorg,
            bool $managesviewed, bool $viewedisstaff, bool $viewerhasviewalldetails,
            bool $viewerismentor, $participantpath): int {
        if ($isself || !$managedkeys) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }
        if ($managesviewed) {
            return core_user::VIEWPROFILE_FORCE_ALLOW;
        }
        if ($viewerhasviewalldetails || $viewerismentor) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }
        if (is_callable($participantpath) ? (bool)$participantpath() : (bool)$participantpath) {
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }
        if (trim($viewedorg) === '' && $viewedisstaff) {
            // Course teachers and the site team have no organisation; a manager may see them.
            return core_user::VIEWPROFILE_DO_NOT_PREVENT;
        }
        return core_user::VIEWPROFILE_PREVENT;
    }
}
