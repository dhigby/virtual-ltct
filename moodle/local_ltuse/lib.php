<?php
// This file is part of local_ltuse. Core finds the callbacks here by name.
//
// It holds local_ltuse_control_view_profile(), the profile hook of spec 002 (research R9,
// amended 2026-10-02). It only gathers inputs. The decision is
// local_ltuse\profile_access::decide(), a pure function tested without Moodle by
// tests/profile_access_harness.php, and whether the viewer manages the viewed person is
// local_ltuse\organisation\access::is_org_member_of_manager(), tested by
// tests/org_access_harness.php. And, from spec 003, local_ltuse_myprofile_navigation(), which
// links the Mentoring page from profiles, and the inputs of the Manage mentors page's
// decision, local_ltuse\mentor_admin::decide(), tested by tests/mentor_admin_harness.php.

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
 * @param bool $reload read afresh and refresh the cache: organisation actions do, before a write
 * @return string[] organisation keys, empty when the user is in none
 */
function local_ltuse_organisation_member_keys(int $userid, bool $reload = false): array {
    global $DB;
    static $cache = [];

    if ($userid <= 0) {
        return [];
    }
    if (!$reload && array_key_exists($userid, $cache)) {
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
 * @param bool $reload read afresh and refresh the cache: organisation actions do, before a write
 * @return string[] organisation keys, empty when the user manages none
 */
function local_ltuse_managed_organisation_keys(int $userid, bool $reload = false): array {
    global $DB;
    static $cache = [];

    if ($userid <= 0) {
        return [];
    }
    if (!$reload && array_key_exists($userid, $cache)) {
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
 * The facts local_ltuse\organisation\access needs about one person (spec 002 research R10).
 *
 * Gathered on every request, through public APIs except the cohort reads this file lists and
 * the one role_assignments read below. Spec 002's organisation pages need exactly these facts
 * for may_manage_account(), so they call this rather than gather their own.
 *
 *   ltct_org       profile_user_record() (public/user/profile/lib.php:812)
 *   org_cohorts    local_ltuse_organisation_member_keys()
 *   siteadmin      is_siteadmin() (public/lib/accesslib.php:702)
 *   coursecontact  staff: any role but student in any course context. A read of
 *                  {role_assignments} by userid, since get_user_roles() takes one context and
 *                  has_coursecontact_role() sees only $CFG->coursecontact (teachers by default)
 *   highrole       get_user_roles($context, $userid, false) (public/lib/accesslib.php:3097)
 *                  at the system context and at each category from
 *                  core_course_category::get_all(['returnhidden' => true])
 *                  (public/course/classes/category.php:370); hidden categories count too
 *   managers       local_ltuse_managed_organisation_keys()
 *   mentor         local_ltuse_is_mentor_candidate()
 *
 * @param stdClass $user a user record with id and deleted
 * @return array facts, keyed as organisation\access documents them
 */
function local_ltuse_organisation_person_facts(stdClass $user): array {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/user/profile/lib.php');

    $userid = (int)$user->id;
    $fields = profile_user_record($userid);

    $highrole = (bool)get_user_roles(context_system::instance(), $userid, false);
    if (!$highrole) {
        foreach (core_course_category::get_all(['returnhidden' => true]) as $category) {
            if (get_user_roles($category->get_context(), $userid, false)) {
                $highrole = true;
                break;
            }
        }
    }

    return [
        'id' => $userid,
        'ltct_org' => isset($fields->ltct_org) ? trim((string)$fields->ltct_org) : '',
        'org_cohorts' => local_ltuse_organisation_member_keys($userid),
        'deleted' => !empty($user->deleted),
        'siteadmin' => is_siteadmin($userid),
        'coursecontact' => $DB->record_exists_sql("SELECT 1 FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :courselevel
            JOIN {role} r ON r.id = ra.roleid AND r.shortname <> :student WHERE ra.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'student' => 'student', 'userid' => $userid]),
        'highrole' => $highrole,
        'managers' => (bool)local_ltuse_managed_organisation_keys($userid),
        'mentor' => local_ltuse_is_mentor_candidate($userid),
    ];
}

/**
 * The id of the hidden ltct:mentors cohort, or 0 before site_config.py has applied it.
 *
 * A read of {cohort} by idnumber in the system context: no cohort API looks a cohort up by
 * idnumber (cohort_get_cohort(), public/cohort/lib.php:386, takes an id). cohort.idnumber is
 * not indexed in core, but the table holds tens of rows; moodle/local_ltuse/README.md lists
 * this read.
 *
 * Not cached, like mentoring::role_id(): a static kept an id from before a PHPUnit reset (008's
 * admin_test makes its own ltct:mentors), so organisation_test then took a mentor for a plain
 * learner, and a manager could enrol them.
 *
 * @return int
 */
function local_ltuse_mentors_cohort_id(): int {
    global $DB;
    return (int)$DB->get_field('cohort', 'id', ['idnumber' => \local_ltuse\mentor_admin::MENTORS_COHORT,
        'contextid' => context_system::instance()->id]);
}

/**
 * Whether a user is in the ltct:mentors cohort, and so may be offered as a mentor (spec 003 R7).
 *
 * cohort_is_member() (public/cohort/lib.php:239), which reads hidden cohorts too.
 *
 * @param int $userid
 * @return bool
 */
function local_ltuse_is_mentor_candidate(int $userid): bool {
    global $CFG;
    require_once($CFG->dirroot . '/cohort/lib.php');
    $cohortid = local_ltuse_mentors_cohort_id();
    return $cohortid && $userid > 0 && cohort_is_member($cohortid, $userid);
}

/**
 * Everyone in the ltct:mentors cohort who can sign in: not deleted, not suspended.
 *
 * One read of {cohort_members} joined to {user}, by the indexed cohortid. Core has no function
 * that lists one cohort's members. Name fields come from \core_user\fields::for_name(), as
 * get_role_users() builds them (public/lib/accesslib.php:4062). moodle/local_ltuse/README.md
 * lists this read.
 *
 * @return stdClass[] user records with id and the name fields fullname() needs, keyed by id
 */
function local_ltuse_mentor_candidates(): array {
    global $DB;
    $cohortid = local_ltuse_mentors_cohort_id();
    if (!$cohortid) {
        return [];
    }
    $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $sql = "SELECT u.id, $names
              FROM {cohort_members} cm
              JOIN {user} u ON u.id = cm.userid
             WHERE cm.cohortid = :cohortid
               AND u.deleted = 0
               AND u.suspended = 0
          ORDER BY u.lastname, u.firstname, u.id";
    return $DB->get_records_sql($sql, ['cohortid' => $cohortid]);
}

/**
 * May the current user assign and end this learner's mentors? (spec 003 R7 Phase B, under
 * spec 002 R10.) Gathers local_ltuse\mentor_admin::decide()'s inputs on every call.
 *
 * The cheap reads come first: a viewer who manages no organisation and cannot assign the role
 * in the learner's context never reaches the person-facts reads.
 *
 * @param stdClass|false|null $learner the learner's user record, as core_user::get_user() gives it
 * @return bool
 */
function local_ltuse_may_manage_mentors($learner): bool {
    global $USER;
    $viewerid = isset($USER->id) ? (int)$USER->id : 0;
    $roleid = \local_ltuse\mentoring::role_id();
    if (!$learner || !empty($learner->deleted) || !$roleid || $viewerid <= 0) {
        return false;
    }
    $context = context_user::instance((int)$learner->id, IGNORE_MISSING);
    if (!$context) {
        return false;
    }
    // The site team: core's own assign permission, with mentor allowed by role_allow_assign
    // (get_assignable_roles(), public/lib/accesslib.php:3252).
    $canassigncore = has_capability('moodle/role:assign', $context)
        && array_key_exists($roleid, get_assignable_roles($context));
    $managedkeys = local_ltuse_managed_organisation_keys($viewerid);
    if (!$canassigncore && !$managedkeys) {
        return false;
    }
    $person = $canassigncore ? ['id' => (int)$learner->id] : local_ltuse_organisation_person_facts($learner);
    return \local_ltuse\mentor_admin::decide($viewerid, true, $canassigncore, $managedkeys, $person);
}

/**
 * Link the Mentoring page from profiles (spec 003, research R3; FR-010).
 *
 * On your own profile, when you have a mentor or a learner. On a learner's profile, for their
 * mentor only, straight to that learner's entry. It adds links and grants nothing: the page
 * checks local/ltuse:viewmenteeprogress itself. And, on someone else's profile, "Manage
 * mentors" when local_ltuse_may_manage_mentors() allows it.
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
    // Manage mentors (spec 003 R7 Phase B): the site team, and the learner's organisation
    // manager. mentors.php recomputes the same decision on every request.
    if (local_ltuse_may_manage_mentors($user)) {
        $tree->add_node(new \core_user\output\myprofile\node('miscellaneous', 'local_ltuse_mentors',
            get_string('mentors:manage', 'local_ltuse'), null,
            new moodle_url('/local/ltuse/mentors.php', ['userid' => (int)$user->id])));
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
