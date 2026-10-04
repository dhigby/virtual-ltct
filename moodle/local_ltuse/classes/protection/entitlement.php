<?php
namespace local_ltuse\protection;

defined('MOODLE_INTERNAL') || die();

// local_ltuse_managed_organisation_keys() and local_ltuse_organisation_member_keys() live in
// lib.php, which core does not load in a task or a web service.
global $CFG;
require_once($CFG->dirroot . '/local/ltuse/lib.php');

use context_course;
use context_system;
use context_user;

/**
 * Who may see a protected user's real identity, and who may change their protection
 * (spec 016, research R7; FR-006, FR-008).
 *
 * Every surface asks this class: the profile node, the Mentoring page and its app handler,
 * "People I support", the granting pages and both web services. None checks a capability
 * alone, because the organisation-manager path is not a capability.
 *
 * can_view_identity(V, P) holds when V is P, or when any of these holds:
 *
 *   1  site team       local/ltuse:viewidentity at system context (manager archetype)
 *   2  mentor          local/ltuse:viewidentity in P's user context: the declared mentor role,
 *                      assigned there by spec 003, so it ends with the assignment
 *   3  own-org manager V is in ltct:org:<key>:managers, P's ltct_org is <key>, P is in
 *                      ltct:org:<key> (organisation\access::is_org_member_of_manager), and the
 *                      organisation has not withheld identity from its managers (R12)
 *   4  course mentor   P is actively enrolled in an ltct:<slug> course, never ltct:officehours,
 *                      where V holds local/ltuse:viewidentity: the declared teacher role
 *                      ("Course mentor"). Ends with V's role or P's enrolment.
 *
 * can_manage_protection(V, P): local/ltuse:manageprotection in P's user context (the site
 * team), or path 3 without the withholding check: a manager manages their own people even when
 * the organisation withholds identity from them, and then sees only the protected display.
 *
 * Read only. Decisions are cached for the request; a changed role, enrolment or cohort shows on
 * the next page load.
 */
class entitlement {

    /** @var array<string, bool> can_view_identity results for this request, "viewer:user" */
    protected static $cache = [];

    /**
     * May the viewer see this user's real identity and the Protected marker?
     *
     * @param int $viewerid
     * @param int $userid
     * @return bool
     */
    public static function can_view_identity(int $viewerid, int $userid): bool {
        if ($viewerid <= 0 || $userid <= 0) {
            return false;
        }
        if ($viewerid === $userid) {
            return true;
        }
        $key = "{$viewerid}:{$userid}";
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = self::decide_view($viewerid, $userid);
        }
        return self::$cache[$key];
    }

    /**
     * May the viewer grant, change or remove this user's protection? Never their own.
     *
     * @param int $viewerid
     * @param int $userid
     * @return bool
     */
    public static function can_manage_protection(int $viewerid, int $userid): bool {
        if ($viewerid <= 0 || $userid <= 0 || $viewerid === $userid) {
            return false;
        }
        $context = context_user::instance($userid, IGNORE_MISSING);
        if ($context && has_capability('local/ltuse:manageprotection', $context, $viewerid)) {
            return true;
        }
        return self::manages_organisation_of($viewerid, $userid) !== null;
    }

    /**
     * May the viewer set organisation minimums? The site team only (R12).
     *
     * @param int $viewerid
     * @return bool
     */
    public static function can_manage_organisations(int $viewerid): bool {
        return $viewerid > 0 && has_capability('local/ltuse:manageorgprotection', context_system::instance(), $viewerid);
    }

    /**
     * The Protected marker, as HTML, or '' when the user is not protected or the viewer is not
     * entitled. A non-entitled viewer never sees it, because the marker itself says the person
     * is at risk (R7).
     *
     * @param int $viewerid
     * @param int $userid
     * @return string
     */
    public static function marker(int $viewerid, int $userid): string {
        if (!service::is_protected($userid) || !self::can_view_identity($viewerid, $userid)) {
            return '';
        }
        return \html_writer::span(get_string('protection:marker', 'local_ltuse'),
            'badge bg-warning text-dark local-ltuse-protected');
    }

    /**
     * Forget the cached decisions, for a long-running task or a test.
     */
    public static function reset_cache(): void {
        self::$cache = [];
    }

    /**
     * Paths 1 to 4, cheapest first.
     *
     * @param int $viewerid
     * @param int $userid
     * @return bool
     */
    protected static function decide_view(int $viewerid, int $userid): bool {
        if (has_capability('local/ltuse:viewidentity', context_system::instance(), $viewerid)) {
            return true; // 1: the site team.
        }
        $context = context_user::instance($userid, IGNORE_MISSING);
        if ($context && has_capability('local/ltuse:viewidentity', $context, $viewerid)) {
            return true; // 2: an assigned mentor.
        }
        $orgkey = self::manages_organisation_of($viewerid, $userid);
        if ($orgkey !== null && service::managers_see_identity($orgkey)) {
            return true; // 3: a manager of their own organisation, which does not withhold it.
        }
        return self::is_course_mentor_of($viewerid, $userid); // 4.
    }

    /**
     * The organisation key through which the viewer manages this user, or null. The same
     * predicate as the organisation page and the profile hook (spec 002 R10): the user's
     * ltct_org is a key whose managers cohort the viewer is in, and the user is in that key's
     * member cohort.
     *
     * @param int $viewerid
     * @param int $userid
     * @return string|null
     */
    public static function manages_organisation_of(int $viewerid, int $userid): ?string {
        $managed = local_ltuse_managed_organisation_keys($viewerid);
        if (!$managed) {
            return null;
        }
        $org = service::user_org($userid);
        $ok = \local_ltuse\organisation\access::is_org_member_of_manager($viewerid, $managed, [
            'id' => $userid,
            'ltct_org' => $org,
            'org_cohorts' => local_ltuse_organisation_member_keys($userid),
        ]);
        return $ok ? $org : null;
    }

    /**
     * Path 4: is the viewer a course mentor in a course this user is actively enrolled in?
     *
     * @param int $viewerid
     * @param int $userid
     * @return bool
     */
    public static function is_course_mentor_of(int $viewerid, int $userid): bool {
        foreach (enrol_get_all_users_courses($userid, true, 'idnumber') as $course) {
            if (!levels::course_counts((string)$course->idnumber)) {
                continue;
            }
            if (has_capability('local/ltuse:viewidentity', context_course::instance((int)$course->id), $viewerid)) {
                return true;
            }
        }
        return false;
    }
}
