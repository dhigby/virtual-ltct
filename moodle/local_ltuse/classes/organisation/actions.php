<?php
namespace local_ltuse\organisation;

defined('MOODLE_INTERNAL') || die();

use context_coursecat;
use context_system;
use core_user;
use moodle_exception;
use stdClass;

/**
 * The management actions on one person: suspend, reactivate, enrol, unenrol (spec 002 R10,
 * as amended for spec 008 research R6).
 *
 * TWO LAYERS. Each action is split in two:
 *
 *   do_<action>()   the write, keeping the action's own rules: never a site admin, never the
 *                   acting user, and access::may_enrol_into() for enrolment. It does not ask
 *                   who manages the person. Spec 008's administration service calls it, after
 *                   require_capability('local/ltuse:administer') and its own rules, because
 *                   the site team manage people no manager may: staff, mentors, managers and
 *                   the holding-entry learners.
 *   <action>()      the manager wrapper: access::may_manage_account() for the acting user,
 *                   then do_<action>(). Spec 002's organisation page calls only these.
 *
 * None of the core functions called here checks a capability itself, so the caller's checks
 * and these rules are the only gate. Every write fires core's own event with the acting user
 * as actor; there is no event of our own.
 *
 * SHARED SEAM. This file is spec 002's (its task T071) and was built by spec 008 to that
 * contract first. 002's send_reset() and its PHPUnit cases (002 T064) are added here when 002
 * builds the organisation page; 008 never writes a second suspend.
 */
class actions {

    /** The organisation-enrolment instance's name on a course's enrolment methods page. */
    const ENROL_NAME = 'Organisation enrolment';

    // --- unchecked cores ----------------------------------------------------------------------

    /**
     * Suspend an account, site-wide, and end its sessions.
     *
     * Sessions first: user_update_user() does not end them, and a suspended person with a live
     * session could otherwise carry on (spec 008 R6). Then a minimal {id, suspended} object,
     * never a reloaded full record, so a concurrent spec 016 name change cannot be overwritten.
     * Enrolments, grades and completion are all kept.
     *
     * @param int $userid
     */
    public static function do_suspend(int $userid): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        self::refuse_special($userid);
        \core\session\manager::destroy_user_sessions($userid);
        user_update_user((object)['id' => $userid, 'suspended' => 1], false);
    }

    /**
     * Reactivate a suspended account.
     *
     * @param int $userid
     */
    public static function do_reactivate(int $userid): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        self::refuse_special($userid);
        user_update_user((object)['id' => $userid, 'suspended' => 0], false);
    }

    /**
     * Enrol a person as Student through the course's organisation-enrolment instance.
     *
     * Only into a course access::may_enrol_into() allows for that person: a published ltct:
     * course, in ltct:published or in their own organisation's ltct:org:<key> category. Never
     * a pilot. Idempotent: an existing enrolment through that instance is left as it is,
     * except that a suspended one is made active again. Without the explicit status,
     * enrol_user() keeps a suspended enrolment suspended, so the person would stay unable to
     * enter the course while every re-run of an intake reported the row done.
     *
     * @param int $userid
     * @param int $courseid
     */
    public static function do_enrol(int $userid, int $courseid): void {
        global $DB;
        self::refuse_special($userid);
        $course = get_course($courseid);
        $categoryidnumber = (string)$DB->get_field('course_categories', 'idnumber', ['id' => $course->category]);
        $person = ['ltct_org' => self::organisation_of($userid)];
        if (!access::may_enrol_into($person, (string)$course->idnumber, $categoryidnumber)) {
            throw new moodle_exception('error:actionrefused', 'local_ltuse', '', 'enrol: course not allowed');
        }
        $instance = self::org_enrol_instance($course);
        enrol_get_plugin(access::ENROL_PLUGIN)->enrol_user($instance, $userid, $instance->roleid, 0, 0,
            ENROL_USER_ACTIVE);
    }

    /**
     * End an enrolment made through the course's organisation-enrolment instance, and only that.
     *
     * A cohort-sync enrolment is the site team's to disable, a manual (pilot) enrolment is never
     * touched (access::may_unenrol_from()). Unenrolling a last enrolment removes grades and
     * group places, not completion records; the caller's confirmation says so.
     *
     * @param int $userid
     * @param int $courseid
     */
    public static function do_unenrol(int $userid, int $courseid): void {
        self::refuse_special($userid);
        $instance = self::find_org_enrol_instance($courseid);
        if (!$instance || !access::may_unenrol_from($instance->enrol, $instance->customchar1 ?? null)) {
            throw new moodle_exception('error:actionrefused', 'local_ltuse', '', 'unenrol: no organisation enrolment');
        }
        enrol_get_plugin(access::ENROL_PLUGIN)->unenrol_user($instance, $userid);
    }

    // --- manager wrappers -------------------------------------------------------------------

    /** @param int $userid */
    public static function suspend(int $userid): void {
        self::require_manager_of($userid);
        self::do_suspend($userid);
    }

    /** @param int $userid */
    public static function reactivate(int $userid): void {
        self::require_manager_of($userid);
        self::do_reactivate($userid);
    }

    /**
     * @param int $userid
     * @param int $courseid
     */
    public static function enrol(int $userid, int $courseid): void {
        self::require_manager_of($userid);
        self::do_enrol($userid, $courseid);
    }

    /**
     * @param int $userid
     * @param int $courseid
     */
    public static function unenrol(int $userid, int $courseid): void {
        self::require_manager_of($userid);
        self::do_unenrol($userid, $courseid);
    }

    // --- facts and helpers --------------------------------------------------------------------

    /**
     * The facts access::may_manage_account() needs about person P (spec 002 data-model,
     * "Organisation access decision"). Public APIs and the plugin's two cohort reads only.
     *
     * @param int $userid
     * @return array
     */
    public static function person_facts(int $userid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/ltuse/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');

        $user = core_user::get_user($userid, 'id, deleted', MUST_EXIST);
        $highrole = (bool)get_user_roles(context_system::instance(), $userid, false);
        if (!$highrole) {
            foreach (\core_course_category::get_all(['returnhidden' => true]) as $category) {
                if (get_user_roles(context_coursecat::instance($category->id), $userid, false)) {
                    $highrole = true;
                    break;
                }
            }
        }
        $mentorscohort = $DB->get_field('cohort', 'id', ['idnumber' => 'ltct:mentors']);
        return [
            'id' => (int)$user->id,
            'ltct_org' => self::organisation_of($userid),
            'org_cohorts' => local_ltuse_organisation_member_keys($userid),
            'deleted' => (bool)$user->deleted,
            'siteadmin' => is_siteadmin($userid),
            'coursecontact' => has_coursecontact_role($userid),
            'highrole' => $highrole,
            'managers' => (bool)local_ltuse_managed_organisation_keys($userid),
            'mentor' => $mentorscohort ? cohort_is_member($mentorscohort, $userid) : false,
        ];
    }

    /**
     * The course's organisation-enrolment instance, created on first use (spec 002 data-model
     * "Organisation-enrolment instance"): enrol_self, new self-enrolments off, a random key,
     * no welcome message, role Student, marked by access::ENROL_MARKER.
     *
     * @param stdClass $course
     * @return stdClass the enrol instance
     */
    public static function org_enrol_instance(stdClass $course): stdClass {
        global $DB;
        $instance = self::find_org_enrol_instance((int)$course->id);
        if ($instance) {
            return $instance;
        }
        $studentroleid = (int)$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $id = enrol_get_plugin(access::ENROL_PLUGIN)->add_instance($course, [
            'name' => self::ENROL_NAME,
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $studentroleid,
            'customint6' => 0,                  // No new self-enrolments.
            'customint4' => 0,                  // No welcome message.
            'password' => random_string(20),    // A second barrier against self-enrolment.
            'customchar1' => access::ENROL_MARKER,
        ]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * @param int $courseid
     * @return stdClass|null
     */
    protected static function find_org_enrol_instance(int $courseid): ?stdClass {
        foreach (enrol_get_instances($courseid, false) as $instance) {
            if ($instance->enrol === access::ENROL_PLUGIN && $instance->customchar1 === access::ENROL_MARKER) {
                return $instance;
            }
        }
        return null;
    }

    /**
     * The person's ltct_org value, '' when empty.
     *
     * @param int $userid
     * @return string
     */
    protected static function organisation_of(int $userid): string {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        return trim((string)(profile_user_record($userid, false)->ltct_org ?? ''));
    }

    /**
     * Rules every action keeps, whoever calls it: never a site admin, never oneself.
     *
     * @param int $userid
     */
    protected static function refuse_special(int $userid): void {
        global $USER;
        if (is_siteadmin($userid)) {
            throw new moodle_exception('error:actionrefused', 'local_ltuse', '', 'a site administrator');
        }
        if ((int)$USER->id === $userid) {
            throw new moodle_exception('error:actionrefused', 'local_ltuse', '', 'your own account');
        }
    }

    /**
     * The manager layer: the acting user must manage this person (access::may_manage_account()).
     *
     * @param int $userid
     */
    protected static function require_manager_of(int $userid): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/local/ltuse/lib.php');
        $managedkeys = local_ltuse_managed_organisation_keys((int)$USER->id);
        if (!access::may_manage_account((int)$USER->id, $managedkeys, self::person_facts($userid))) {
            throw new moodle_exception('error:actionrefused', 'local_ltuse', '', 'not one of your people');
        }
    }
}
