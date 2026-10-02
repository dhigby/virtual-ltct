<?php
// This file is part of local_ltuse. Core finds the callbacks here by name.
//
// It holds one: local_ltuse_control_view_profile(), the profile hook of spec 002 (research
// R9). It only gathers inputs. The decision is local_ltuse\profile_access::decide(), a pure
// function tested without Moodle by tests/profile_access_harness.php.

defined('MOODLE_INTERNAL') || die();

/**
 * Refuse an organisation manager the profile of anyone outside their organisations.
 *
 * Called by core's user_process_profile_callbacks() from user_can_view_profile(), which
 * user/profile.php, user/view.php and the profile web services go through. It runs only
 * while forceloginforprofiles is on, which moodle/site/settings/groups.yaml declares.
 *
 * It returns core_user::VIEWPROFILE_PREVENT or core_user::VIEWPROFILE_DO_NOT_PREVENT,
 * never VIEWPROFILE_FORCE_ALLOW, so it only ever takes access away and core's own checks
 * decide the rest. It writes nothing.
 *
 * The cheap cases come first, viewing yourself and managing no organisation, so most
 * viewers never reach the profile or capability reads.
 *
 * @param stdClass $user the user whose profile is being checked
 * @param stdClass|null $course the course, when the check is for one course
 * @param context|null $usercontext the viewed user's context; several core callers pass null
 * @return int core_user::VIEWPROFILE_PREVENT or core_user::VIEWPROFILE_DO_NOT_PREVENT
 */
function local_ltuse_control_view_profile($user, $course = null, $usercontext = null): int {
    global $USER, $CFG;

    $isself = isset($USER->id) && (int)$USER->id === (int)$user->id;
    if ($isself) {
        return core_user::VIEWPROFILE_DO_NOT_PREVENT;
    }
    $managedkeys = local_ltuse_managed_organisation_keys(isset($USER->id) ? (int)$USER->id : 0);
    if (!$managedkeys) {
        return core_user::VIEWPROFILE_DO_NOT_PREVENT;
    }

    // The viewed user's organisation, as stored: the key, never a display name.
    require_once($CFG->dirroot . '/user/profile/lib.php');
    $fields = profile_user_record((int)$user->id);
    $viewedorg = isset($fields->ltct_org) ? trim((string)$fields->ltct_org) : '';

    // Staff have no organisation, and a manager may see them: a course contact (a
    // course's teacher), or the site team, who hold viewalldetails at system context.
    $system = context_system::instance();
    $viewedisstaff = has_coursecontact_role((int)$user->id)
        || has_capability('moodle/user:viewalldetails', $system, (int)$user->id);

    // The site team, and a spec 003 mentor in that user's context, are never refused. A
    // user context can be missing only in a broken state; the system context then stands
    // in, which still finds the site team.
    $context = $usercontext ?? context_user::instance((int)$user->id, IGNORE_MISSING);
    if (!$context) {
        $context = $system;
    }
    $viewerhasviewalldetails = has_capability('moodle/user:viewalldetails', $context);

    return \local_ltuse\profile_access::decide($isself, $managedkeys, $viewedorg,
        $viewedisstaff, $viewerhasviewalldetails);
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
