<?php
namespace local_ltuse\organisation;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;

/**
 * What an organisation manager may do for one of their own learners (spec 002 amendment
 * 2026-10-02, research R10; data-model "Organisation access decision", per-action rules).
 *
 * Every method acts as the signed-in user, never as a user id it is handed, and starts by
 * re-checking access::may_manage_account() on facts read afresh (people::facts($id, true)),
 * then its own per-action rule. None of the core calls below checks a capability itself, so
 * this re-check is the only gate: the page's list and buttons are a convenience, never the
 * authority. A refusal throws `organisation:notyours` or the per-action error and writes
 * nothing.
 *
 *   enrol()       the course's organisation-enrolment instance (an enrol_self instance found
 *                 by customchar1 = access::ENROL_MARKER, made on first use), as Student,
 *                 through enrol_plugin::enrol_user() (lib/enrollib.php:2112); only into a
 *                 course access::may_enrol_into() allows
 *   unenrol()     enrol_plugin::unenrol_user() (lib/enrollib.php:2294), only from that instance
 *                 (access::may_unenrol_from()); cohort-sync and manual enrolments are never
 *                 touched
 *   send_reset()  core_login_process_password_reset($username, '') (login/lib.php:84), which
 *                 applies core's own guards, prints nothing and emails only the person's own
 *                 address; its status is mapped by reset_outcome()
 *   suspend()     \core\session\manager::destroy_user_sessions() (lib/classes/session/
 *                 manager.php:985) then user_update_user() (user/lib.php:156) with a minimal
 *                 {id, suspended}, in the order and with the guards of admin/user.php:127-138
 *   reactivate()  user_update_user() with a minimal {id, suspended: 0}
 *
 * Every write fires core's own event with the manager as the actor (user_enrolment_created,
 * user_enrolment_deleted, user_updated), so the standard log records who did what. There is
 * no event of our own. All APIs confirmed on MOODLE_502_STABLE (research R10).
 *
 * 5.3 note: user_update_user() is deprecated on main for 5.3 (MDL-82650) in favour of
 * \core\user::update_user(). Both calls are in write_suspended(), moved with spec 016's hook.
 */
class actions {

    /** The page's actions, as its `action` parameter names them. */
    const ACTIONS = ['enrol', 'unenrol', 'reset', 'suspend', 'reactivate'];

    /** The role every organisation enrolment gives (R10: always Student). */
    const STUDENT_ROLE = 'student';

    /**
     * The facts about P, once the signed-in user may manage P's account. Throws otherwise.
     *
     * @param int $userid P
     * @return array facts from people::facts()
     */
    public static function require_manageable(int $userid): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/local/ltuse/lib.php');
        $managerid = isset($USER->id) ? (int)$USER->id : 0;
        $facts = people::facts($userid, true);
        if (!access::may_manage_account($managerid, local_ltuse_managed_organisation_keys($managerid, true), $facts)) {
            throw new moodle_exception('organisation:notyours', 'local_ltuse');
        }
        return $facts;
    }

    /**
     * Enrol P in a course as Student, through the course's organisation-enrolment instance.
     * Re-enrolling someone whose enrolment there was suspended makes it active again.
     *
     * @param int $userid P
     * @param int $courseid
     */
    public static function enrol(int $userid, int $courseid): void {
        $course = self::enrol_target(self::require_manageable($userid), $courseid);
        // An enrolment through a plugin turned off site-wide is inactive (enrol_get_instances()).
        if (!enrol_is_enabled(access::ENROL_PLUGIN)) {
            throw new moodle_exception('organisation:selfdisabled', 'local_ltuse');
        }
        $instance = self::org_instance($course, true);
        enrol_get_plugin(access::ENROL_PLUGIN)->enrol_user($instance, $userid, self::student_role_id(),
            0, 0, ENROL_USER_ACTIVE);
    }

    /**
     * End P's enrolment through the course's organisation-enrolment instance. If it was their
     * last enrolment in the course, core removes their grades and group places; activity and
     * completion records stay (the page says so before confirming).
     *
     * @param int $userid P
     * @param int $courseid
     */
    public static function unenrol(int $userid, int $courseid): void {
        self::require_manageable($userid);
        $instance = self::unenrol_instance($userid, $courseid);
        enrol_get_plugin(access::ENROL_PLUGIN)->unenrol_user($instance, $userid);
    }

    /**
     * The course P may be enrolled into by the per-action rule, or a throw. The page calls it
     * before showing a confirmation, so it never names a course the rule refuses.
     *
     * @param array $facts P's facts, from require_manageable()
     * @param int $courseid
     * @return \stdClass the course: id, idnumber, category
     */
    public static function enrol_target(array $facts, int $courseid): \stdClass {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, idnumber, category');
        $categoryidnumber = $course ? (string)$DB->get_field('course_categories', 'idnumber', ['id' => $course->category]) : '';
        if (!$course || !access::may_enrol_into($facts, (string)$course->idnumber, $categoryidnumber)) {
            throw new moodle_exception('organisation:notthiscourse', 'local_ltuse');
        }
        return $course;
    }

    /**
     * The organisation-enrolment instance P is enrolled through in a course, or a throw. Like
     * enrol_target(), the page calls it before showing a confirmation.
     *
     * @param int $userid P
     * @param int $courseid
     * @return \stdClass the enrol instance
     */
    public static function unenrol_instance(int $userid, int $courseid): \stdClass {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id');
        $instance = $course ? self::org_instance($course, false) : null;
        if (!$instance || !access::may_unenrol_from((string)$instance->enrol, $instance->customchar1)
                || !$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
            throw new moodle_exception('organisation:notorgenrolment', 'local_ltuse');
        }
        return $instance;
    }

    /**
     * Email P a password reset link, at P's own address. The manager never sees the link.
     *
     * @param int $userid P
     * @return string the lang string (local_ltuse) that says what happened
     */
    public static function send_reset(int $userid): string {
        global $CFG, $DB;
        self::require_manageable($userid);
        require_once($CFG->dirroot . '/login/lib.php');
        $user = $DB->get_record('user', ['id' => $userid], 'id, username, suspended', MUST_EXIST);
        if (!empty($user->suspended)) {
            return 'organisation:reset:suspended'; // Core would find no account and say so less plainly.
        }
        try {
            [$status] = core_login_process_password_reset($user->username, '');
        } catch (moodle_exception $e) {
            return 'organisation:reset:failed'; // cannotmailconfirm: the mail could not be sent.
        }
        return self::reset_outcome((string)$status);
    }

    /**
     * What core_login_process_password_reset()'s status means for the manager (login/lib.php:
     * 176-214; research R10, T041). Pure: tests/org_access_harness.php tests it.
     *
     *   emailresetconfirmsent          a link was sent, or, when the account cannot reset its
     *                                  own password, core's "how to change it" email
     *   emailalreadysent               a link was sent and re-sent recently; nothing more sent
     *   emailpasswordconfirmsent       nothing sent: the account is not confirmed
     *   emailpasswordconfirmnoemail    nothing sent: the account has no email address
     *   emailpasswordconfirmnotsent    nothing sent: no active account by that username
     *   emailpasswordconfirmmaybesent  protectusernames is on, so core does not say
     *
     * @param string $status
     * @return string a local_ltuse lang string identifier
     */
    public static function reset_outcome(string $status): string {
        $map = [
            'emailresetconfirmsent' => 'organisation:reset:sent',
            'emailalreadysent' => 'organisation:reset:alreadysent',
            'emailpasswordconfirmsent' => 'organisation:reset:notconfirmed',
            'emailpasswordconfirmnoemail' => 'organisation:reset:noemail',
            'emailpasswordconfirmnotsent' => 'organisation:reset:notfound',
            'emailpasswordconfirmmaybesent' => 'organisation:reset:maybesent',
        ];
        return $map[$status] ?? 'organisation:reset:unknown';
    }

    /**
     * Suspend P's account, site-wide. Their open sessions end first.
     *
     * @param int $userid P
     * @return bool false when P was already suspended
     */
    public static function suspend(int $userid): bool {
        return self::write_suspended($userid, true);
    }

    /**
     * Reactivate P's account.
     *
     * @param int $userid P
     * @return bool false when P was not suspended
     */
    public static function reactivate(int $userid): bool {
        return self::write_suspended($userid, false);
    }

    /**
     * Set P's suspended flag, as admin/user.php:127-138 does, with a minimal object: never a
     * reloaded full record, so a concurrent change to another field cannot be overwritten.
     *
     * @param int $userid P
     * @param bool $suspend
     * @return bool whether anything changed
     */
    protected static function write_suspended(int $userid, bool $suspend): bool {
        global $CFG, $DB, $USER;
        self::require_manageable($userid);
        require_once($CFG->dirroot . '/user/lib.php');
        // access already refuses both; admin/user.php checks them at the write, and so do we.
        if (is_siteadmin($userid) || $userid === (int)$USER->id) {
            throw new moodle_exception('organisation:notyours', 'local_ltuse');
        }
        $user = $DB->get_record('user', ['id' => $userid, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0],
            'id, suspended', MUST_EXIST);
        if ((bool)$user->suspended === $suspend) {
            return false;
        }
        if ($suspend) {
            \core\session\manager::destroy_user_sessions($userid);
        }
        user_update_user((object)['id' => $userid, 'suspended' => $suspend ? 1 : 0], false);
        return true;
    }

    /**
     * A course's organisation-enrolment instance, found by its marker; made when asked to and
     * absent. The instance takes no learner self-enrolments (customint6 = 0), has a random
     * key as a second barrier, sends no welcome message, never unenrols for inactivity, and
     * has no expiry (research R10, data-model "Organisation-enrolment instance").
     *
     * enrol_self allows several instances per course (enrol/self/lib.php:132-140), and its own
     * fields are customint1-6 and customtext1, so customchar1 is free for the marker. Its
     * `name` is shown to learners as the instance title, so it is a plain name, not the marker.
     *
     * @param \stdClass $course with id
     * @param bool $create make it if the course has none
     * @return \stdClass|null the enrol record
     */
    public static function org_instance(\stdClass $course, bool $create): ?\stdClass {
        global $DB;
        $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => access::ENROL_PLUGIN,
            'customchar1' => access::ENROL_MARKER], 'id');
        if ($instances) {
            return reset($instances); // Two made at once by a race: the older one is the instance.
        }
        if (!$create) {
            return null;
        }
        $id = enrol_get_plugin(access::ENROL_PLUGIN)->add_instance($course, [
            'name' => get_string('orgenrol:name', 'local_ltuse'),
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => self::student_role_id(),
            'password' => random_string(20),
            'enrolperiod' => 0,
            'expirynotify' => 0,
            'customint1' => 0,                        // No group enrolment keys.
            'customint2' => 0,                        // Never unenrol for inactivity.
            'customint3' => 0,                        // No limit on enrolments.
            'customint4' => ENROL_DO_NOT_SEND_EMAIL,  // No welcome message.
            'customint5' => 0,                        // No cohort restriction.
            'customint6' => 0,                        // No new self-enrolments.
            'customchar1' => access::ENROL_MARKER,
        ]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The Student role's id.
     *
     * @return int
     */
    public static function student_role_id(): int {
        global $DB;
        return (int)$DB->get_field('role', 'id', ['shortname' => self::STUDENT_ROLE], MUST_EXIST);
    }
}
