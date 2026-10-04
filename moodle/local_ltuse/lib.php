<?php
// This file is part of local_ltuse. Core finds the callbacks here by name.
//
// It holds local_ltuse_control_view_profile(), the profile hook of spec 002 (research R9,
// amended 2026-10-02). It only gathers inputs. The decision is
// local_ltuse\profile_access::decide(), a pure function tested without Moodle by
// tests/profile_access_harness.php, and whether the viewer manages the viewed person is
// local_ltuse\organisation\access::is_org_member_of_manager(), tested by
// tests/org_access_harness.php. And, from spec 003, local_ltuse_myprofile_navigation(), which
// links the Mentoring page from profiles.

defined('MOODLE_INTERNAL') || die();

/**
 * Scope an organisation manager's profile access to their own organisation's people.
 *
 * Called by core's user_process_profile_callbacks() from user_can_view_profile(), which
 * user/profile.php, user/view.php and the profile web services go through. It runs only
 * while forceloginforprofiles is on, which moodle/site/settings/groups.yaml declares.
 *
 * Shared courses are open across organisations, so a manager is not enrolled with their
 * people. It returns core_user::VIEWPROFILE_FORCE_ALLOW in one case only: the viewer manages
 * the viewed person's organisation and the person is in that organisation's cohort, whoever
 * they are. Core lets any plugin's PREVENT win over it. It returns
 * VIEWPROFILE_DO_NOT_PREVENT for the site team, a mentor, or a viewer who shares a course with
 * the person in a role other than orgmanager, and VIEWPROFILE_PREVENT for anyone else a
 * manager would reach only as a manager. It writes nothing.
 *
 * The cheap cases come first, viewing yourself and managing no organisation, so most
 * viewers never reach the profile, cohort or capability reads; the participant path's course
 * reads run only when nothing earlier has decided.
 *
 * @param stdClass $user the user whose profile is being checked
 * @param stdClass|null $course the course, when the check is for one course
 * @param context|null $usercontext the viewed user's context; several core callers pass null
 * @return int a core_user::VIEWPROFILE_* constant
 */
function local_ltuse_control_view_profile($user, $course = null, $usercontext = null): int {
    global $USER, $CFG;

    $viewerid = isset($USER->id) ? (int)$USER->id : 0;
    $isself = $viewerid === (int)$user->id;
    if ($isself) {
        return core_user::VIEWPROFILE_DO_NOT_PREVENT;
    }
    $managedkeys = local_ltuse_managed_organisation_keys($viewerid);
    if (!$managedkeys) {
        return core_user::VIEWPROFILE_DO_NOT_PREVENT;
    }

    // The viewed user's organisation, as stored: the key, never a display name.
    require_once($CFG->dirroot . '/user/profile/lib.php');
    $fields = profile_user_record((int)$user->id);
    $viewedorg = isset($fields->ltct_org) ? trim((string)$fields->ltct_org) : '';

    // The field and the cohort must agree (R10): the same predicate the organisation page uses.
    $managesviewed = \local_ltuse\organisation\access::is_org_member_of_manager($viewerid, $managedkeys, [
        'id' => (int)$user->id,
        'ltct_org' => $viewedorg,
        'org_cohorts' => local_ltuse_organisation_member_keys((int)$user->id),
    ]);

    // Staff have no organisation, and a manager may see them: a course contact (a
    // course's teacher), or the site team, who hold viewalldetails at system context.
    $system = context_system::instance();
    $viewedisstaff = has_coursecontact_role((int)$user->id)
        || has_capability('moodle/user:viewalldetails', $system, (int)$user->id);

    // The site team, and the viewed person's mentor (spec 003, R9), are never refused. A
    // user context can be missing only in a broken state; the system context then stands
    // in, which still finds the site team and finds no mentor.
    $context = $usercontext ?? context_user::instance((int)$user->id, IGNORE_MISSING);
    if (!$context) {
        $context = $system;
    }
    $viewerhasviewalldetails = has_capability('moodle/user:viewalldetails', $context);
    $viewerismentor = $context->contextlevel == CONTEXT_USER
        && has_capability('local/ltuse:viewmenteeprogress', $context, null, false);

    // A course both are actively enrolled in where the viewer holds any role but orgmanager:
    // a manager who is also a learner sees classmates from every organisation (R9).
    $participantpath = function () use ($viewerid, $user): bool {
        foreach (enrol_get_shared_courses($viewerid, (int)$user->id, true) as $shared) {
            $roles = get_user_roles(context_course::instance((int)$shared->id), $viewerid, true);
            foreach ($roles as $role) {
                if ($role->shortname !== 'orgmanager') {
                    return true;
                }
            }
        }
        return false;
    };

    return \local_ltuse\profile_access::decide($isself, $managedkeys, $viewedorg, $managesviewed,
        $viewedisstaff, $viewerhasviewalldetails, $viewerismentor, $participantpath);
}

/**
 * The organisation keys whose member cohort, ltct:org:<key>, a user is in. Cached for the
 * request.
 *
 * One read of {cohort} joined to {cohort_members}, as local_ltuse_managed_organisation_keys()
 * does and for the same reason: every organisation cohort is hidden, which
 * cohort_get_user_cohorts() skips. Managers cohorts (ltct:org:<key>:managers) are left out.
 * cohort.idnumber is not indexed in core; moodle/local_ltuse/README.md lists this read.
 *
 * @param int $userid
 * @return string[] organisation keys, empty when the user is in none
 */
function local_ltuse_organisation_member_keys(int $userid): array {
    global $DB;
    static $cache = [];

    if ($userid <= 0) {
        return [];
    }
    if (array_key_exists($userid, $cache)) {
        return $cache[$userid];
    }

    $prefix = 'ltct:org:';
    $sql = "SELECT c.idnumber
              FROM {cohort} c
              JOIN {cohort_members} cm ON cm.cohortid = c.id
             WHERE cm.userid = :userid
               AND c.contextid = :contextid
               AND " . $DB->sql_like('c.idnumber', ':pattern');
    $params = [
        'userid' => $userid,
        'contextid' => context_system::instance()->id,
        'pattern' => $DB->sql_like_escape($prefix) . '%',
    ];

    $keys = [];
    foreach ($DB->get_fieldset_sql($sql, $params) as $idnumber) {
        $key = substr((string)$idnumber, strlen($prefix));
        if ($key === '' || strpos($key, ':') !== false) {
            continue; // A managers cohort, or ltct:org: naming no organisation.
        }
        $keys[] = $key;
    }
    $keys = array_values(array_unique($keys));
    $cache[$userid] = $keys;
    return $keys;
}

/**
 * The organisation keys a user manages: one for each ltct:org:<key>:managers cohort they
 * are a member of. Cached for the request.
 *
 * One read of {cohort} joined to {cohort_members} by indexed columns. Not
 * cohort_get_user_cohorts(), which skips hidden cohorts, and every managers cohort is
 * hidden (spec 002, R1). moodle/local_ltuse/README.md lists this direct read.
 *
 * @param int $userid
 * @return string[] organisation keys, empty when the user manages none
 */
function local_ltuse_managed_organisation_keys(int $userid): array {
    global $DB;
    static $cache = [];

    if ($userid <= 0) {
        return [];
    }
    if (array_key_exists($userid, $cache)) {
        return $cache[$userid];
    }

    $prefix = 'ltct:org:';
    $suffix = ':managers';
    $sql = "SELECT c.idnumber
              FROM {cohort} c
              JOIN {cohort_members} cm ON cm.cohortid = c.id
             WHERE cm.userid = :userid
               AND c.contextid = :contextid
               AND " . $DB->sql_like('c.idnumber', ':pattern');
    $params = [
        'userid' => $userid,
        'contextid' => context_system::instance()->id,
        'pattern' => $DB->sql_like_escape($prefix) . '%' . $DB->sql_like_escape($suffix),
    ];

    $keys = [];
    foreach ($DB->get_fieldset_sql($sql, $params) as $idnumber) {
        $idnumber = (string)$idnumber;
        if (strlen($idnumber) <= strlen($prefix) + strlen($suffix)) {
            continue; // ltct:org::managers names no organisation.
        }
        $keys[] = substr($idnumber, strlen($prefix), -strlen($suffix));
    }
    $keys = array_values(array_unique($keys));
    $cache[$userid] = $keys;
    return $keys;
}

/**
 * Link the Mentoring page from profiles (spec 003, research R3; FR-010).
 *
 * On your own profile, when you have a mentor or a learner. On a learner's profile, for their
 * mentor only, straight to that learner's entry. It adds links and grants nothing: the page
 * checks local/ltuse:viewmenteeprogress itself.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user the profile's owner
 * @param bool $iscurrentuser
 * @param stdClass|null $course
 */
function local_ltuse_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $USER;
    if (!isloggedin() || isguestuser() || !empty($course)) {
        return;
    }
    local_ltuse_protection_profile_nodes($tree, $user, (bool)$iscurrentuser);
    $url = new moodle_url('/local/ltuse/mentoring.php');
    if ($iscurrentuser) {
        if (\local_ltuse\mentoring::has_relationship((int)$USER->id)) {
            $tree->add_node(new \core_user\output\myprofile\node('miscellaneous', 'local_ltuse_mentoring',
                get_string('mentoring', 'local_ltuse'), null, $url));
        }
        return;
    }
    $context = context_user::instance((int)$user->id, IGNORE_MISSING);
    if ($context && has_capability('local/ltuse:viewmenteeprogress', $context, null, false)) {
        $url->set_anchor('learner-' . (int)$user->id);
        $tree->add_node(new \core_user\output\myprofile\node('miscellaneous', 'local_ltuse_mentoring_learner',
            get_string('mentoring:thislearner', 'local_ltuse'), null, $url));
    }
}

/**
 * Identity protection on profiles (spec 016, research R7 surface 1; FR-007, FR-008, FR-012).
 * Web only; in the app, the Mentoring handler is the entitled surface.
 *
 * On your own profile: your level, a preview of what others see, who sees your real identity,
 * how to ask, and "People I support" when you support anyone protected. On someone else's: the
 * real identity and the Protected marker, only for an entitled viewer, and a link to the
 * granting page for someone who may change it. A non-entitled viewer gets nothing, not even the
 * category, so the profile never says whether a person is protected.
 *
 * @param \core_user\output\myprofile\tree $tree
 * @param stdClass $user the profile's owner
 * @param bool $iscurrentuser
 */
function local_ltuse_protection_profile_nodes(\core_user\output\myprofile\tree $tree, $user, bool $iscurrentuser): void {
    global $USER;
    if (!\local_ltuse\protection\service::table_exists()) {
        return;
    }
    $viewerid = (int)$USER->id;
    $userid = (int)$user->id;
    $nodes = [];
    if ($iscurrentuser) {
        $nodes[] = new \core_user\output\myprofile\node('local_ltuse_protection', 'local_ltuse_protection_own',
            get_string('protection:yourprotection', 'local_ltuse'), null, null,
            \local_ltuse\protection\surfaces::own_summary($userid));
        if (\local_ltuse\protection\surfaces::supports_anyone($viewerid)) {
            $nodes[] = new \core_user\output\myprofile\node('local_ltuse_protection', 'local_ltuse_protection_supported',
                get_string('protection:supported', 'local_ltuse'), null, new moodle_url('/local/ltuse/protected.php'));
        }
    } else {
        $summary = \local_ltuse\protection\surfaces::entitled_summary($viewerid, $userid);
        if ($summary !== '') {
            $nodes[] = new \core_user\output\myprofile\node('local_ltuse_protection', 'local_ltuse_protection_identity',
                get_string('protection:identity', 'local_ltuse'), null, null, $summary);
        }
        if (\local_ltuse\protection\entitlement::can_manage_protection($viewerid, $userid)) {
            $nodes[] = new \core_user\output\myprofile\node('local_ltuse_protection', 'local_ltuse_protection_manage',
                get_string('protection:manage', 'local_ltuse'), null,
                \local_ltuse\protection\surfaces::granting_url($userid));
        }
    }
    if (!$nodes) {
        return;
    }
    $tree->add_category(new \core_user\output\myprofile\category('local_ltuse_protection',
        get_string('protection:category', 'local_ltuse'), 'contact'));
    foreach ($nodes as $node) {
        $tree->add_node($node);
    }
}

/**
 * Refuse removing an office-hours group member by hand (spec 011, research R16).
 *
 * Members of a mentor's group in the office-hours course are added with component local_ltuse
 * and kept in step with the mentor relationship by local_ltuse\officehours; core's group pages
 * call this through groups_remove_member_allowed() (group/lib.php). End the relationship on the
 * learner's profile instead, and the sync removes them. Guards the interface only:
 * groups_remove_member() itself, which the sync uses, is unconditional.
 *
 * @param int $itemid the mentor's user id
 * @param int $groupid
 * @param int $userid
 * @return bool false: never removed by hand
 */
function local_ltuse_allow_group_member_remove($itemid, $groupid, $userid) {
    return false;
}
