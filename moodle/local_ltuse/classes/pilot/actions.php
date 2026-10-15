<?php
namespace local_ltuse\pilot;

defined('MOODLE_INTERNAL') || die();

use context_course;
use moodle_exception;

/**
 * A course author's actions in their stage-7 pilot (2026-10-15): enrol a trial student by
 * exact email address, remove one, and show or hide the course.
 *
 * Every method acts as the signed-in user and starts with require_pilot(): the course must
 * pass rules::course() and the user must hold local/ltuse:pilotenrol in it. None of the core
 * calls below checks a capability itself, so these checks are the only gate. A refusal throws
 * with the rules' lang string and writes nothing.
 *
 *   enrol()          enrol_plugin::enrol_user() (lib/enrollib.php:2112) through the course's
 *                    one manual instance (enrol_manual allows one: enrol/manual/lib.php:158),
 *                    made with add_default_instance() if it is missing, as Student
 *   remove()         enrol_plugin::unenrol_user() (lib/enrollib.php:2294), from that instance
 *                    only; a cohort, self or organisation enrolment is never touched
 *   set_visible()    course_change_visibility() (course/lib.php:2657), after
 *                    require_capability('moodle/course:visibility'), which it does not check
 *
 * Core's own events (user_enrolment_created, user_enrolment_deleted, course_updated) record the
 * author as the actor. There is no event of our own and nothing is stored. The address is
 * matched exactly, ignoring case, and the page never lists or suggests accounts: core's enrol
 * screen, which searches every user on the site, is what INTENT.md rules out for anyone but the
 * site team. All APIs confirmed on MOODLE_502_STABLE.
 */
class actions {

    /** The role every trial student is given. */
    const STUDENT_ROLE = 'student';

    /** The capability the page and every action require, in the course's context. */
    const CAPABILITY = 'local/ltuse:pilotenrol';

    /**
     * The course, once it is a pilot the signed-in user may act on. Throws otherwise.
     *
     * @param int $courseid
     * @return \stdClass the course record
     */
    public static function require_pilot(int $courseid): \stdClass {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $categoryidnumber = (string)$DB->get_field('course_categories', 'idnumber', ['id' => $course->category]);
        if ($reason = rules::course((string)$course->idnumber, $categoryidnumber)) {
            throw new moodle_exception($reason, 'local_ltuse');
        }
        require_capability(self::CAPABILITY, context_course::instance($course->id));
        return $course;
    }

    /**
     * Enrol the account with this exact address as a trial student.
     *
     * @param int $courseid
     * @param string $email as typed
     * @return \stdClass the account enrolled: id and the name fields
     */
    public static function enrol(int $courseid, string $email): \stdClass {
        global $CFG, $DB, $USER;
        $course = self::require_pilot($courseid);
        $email = trim($email);
        $matches = $email === '' ? [] : $DB->get_records_select('user',
            $DB->sql_equal('email', ':email', false, false) . ' AND mnethostid = :host',
            ['email' => $email, 'host' => $CFG->mnet_localhost_id],
            'id', 'id, deleted, suspended, confirmed, ' . implode(', ', \core_user\fields::get_name_fields()));
        $user = rules::single($matches);
        if (!$user) {
            throw new moodle_exception('pilot:noaccount', 'local_ltuse');
        }
        $person = [
            'deleted' => (int)$user->deleted,
            'suspended' => (int)$user->suspended,
            'confirmed' => (int)$user->confirmed,
            'siteadmin' => is_siteadmin($user->id),
            'self' => (int)$user->id === (int)$USER->id,
        ];
        if ($reason = rules::enrol($person, self::methods($course->id, (int)$user->id))) {
            throw new moodle_exception($reason, 'local_ltuse');
        }
        $instance = self::manual_instance($course, true);
        if ((int)$instance->status !== ENROL_INSTANCE_ENABLED || !enrol_is_enabled(rules::ENROL_PLUGIN)) {
            throw new moodle_exception('pilot:manualdisabled', 'local_ltuse');
        }
        enrol_get_plugin(rules::ENROL_PLUGIN)->enrol_user($instance, (int)$user->id, self::student_role_id(),
            0, 0, ENROL_USER_ACTIVE);
        return $user;
    }

    /**
     * End a trial student's manual enrolment. Throws if they have none here.
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function remove(int $courseid, int $userid): void {
        $instance = self::removable($courseid, $userid);
        enrol_get_plugin(rules::ENROL_PLUGIN)->unenrol_user($instance, $userid);
    }

    /**
     * The manual instance a trial student is enrolled through, or a throw. The page calls it
     * before showing a confirmation, so it never names someone it would refuse.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass the enrol instance
     */
    public static function removable(int $courseid, int $userid): \stdClass {
        global $DB;
        $course = self::require_pilot($courseid);
        $instance = self::manual_instance($course, false);
        if (!$instance || !$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
            throw new moodle_exception('pilot:notenrolled', 'local_ltuse');
        }
        return $instance;
    }

    /**
     * Show or hide the course.
     *
     * @param int $courseid
     * @param bool $visible
     */
    public static function set_visible(int $courseid, bool $visible): void {
        global $CFG;
        $course = self::require_pilot($courseid);
        require_capability('moodle/course:visibility', context_course::instance($course->id));
        require_once($CFG->dirroot . '/course/lib.php');
        course_change_visibility($course->id, $visible);
    }

    /**
     * The trial students: everyone enrolled through the course's manual instance, by name.
     *
     * @param int $courseid
     * @return array[] [{id, name}], ordered by name
     */
    public static function learners(int $courseid): array {
        global $DB;
        $course = self::require_pilot($courseid);
        $instance = self::manual_instance($course, false);
        if (!$instance) {
            return [];
        }
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql("SELECT u.id, $fields
                                         FROM {user_enrolments} ue
                                         JOIN {user} u ON u.id = ue.userid
                                        WHERE ue.enrolid = :enrolid AND u.deleted = 0",
            ['enrolid' => $instance->id]);
        $rows = [];
        foreach ($users as $user) {
            $rows[] = ['id' => (int)$user->id, 'name' => fullname($user)];
        }
        usort($rows, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $rows;
    }

    /**
     * The enrolment methods a person is enrolled through in a course, any status.
     *
     * @param int $courseid
     * @param int $userid
     * @return string[]
     */
    protected static function methods(int $courseid, int $userid): array {
        global $DB;
        return array_values($DB->get_fieldset_sql("SELECT e.enrol
                                                     FROM {user_enrolments} ue
                                                     JOIN {enrol} e ON e.id = ue.enrolid
                                                    WHERE e.courseid = :courseid AND ue.userid = :userid",
            ['courseid' => $courseid, 'userid' => $userid]));
    }

    /**
     * The course's manual enrolment instance. enrol_manual allows only one.
     *
     * @param \stdClass $course
     * @param bool $create make it with the site's defaults if it is missing
     * @return \stdClass|null
     */
    public static function manual_instance(\stdClass $course, bool $create): ?\stdClass {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => rules::ENROL_PLUGIN]);
        if (!$instance && $create) {
            $id = enrol_get_plugin(rules::ENROL_PLUGIN)->add_default_instance($course);
            $instance = $id ? $DB->get_record('enrol', ['id' => $id]) : null;
        }
        if (!$instance && $create) {
            throw new moodle_exception('pilot:manualdisabled', 'local_ltuse');
        }
        return $instance ?: null;
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
