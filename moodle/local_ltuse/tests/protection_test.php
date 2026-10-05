<?php
// PHPUnit tests for identity protection (spec 016, research R2, R5, R6, R7, R13).
//
// Synthetic data only, in the PHPUnit database: every name, pseudonym and organisation key is
// an obviously fake fixture value, never a real person's (constitution III, FR-013). Where this
// runs is spec 004's plan.md "Testing".

namespace local_ltuse;

use local_ltuse\protection\entitlement;
use local_ltuse\protection\levels;
use local_ltuse\protection\service;
use local_ltuse\protection\surfaces;

/**
 * The service writes the protected display into the account and keeps the real values; the
 * hook and the observer hold it against other writers; entitlement decides who sees what.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\protection\service
 * @covers     \local_ltuse\protection\entitlement
 * @covers     \local_ltuse\protection\surfaces
 * @covers     \local_ltuse\protection\hook_callbacks
 * @covers     \local_ltuse\protection\observer
 */
final class protection_test extends \advanced_testcase {

    /** The withhold lists, as protection.yaml declares them, less our profile fields. */
    const WITHHOLD = [
        'email' => ['maildisplay'],
        'firstname' => ['maildisplay', 'lastname', 'firstnamephonetic', 'lastnamephonetic', 'middlename',
            'alternatename', 'picture', 'country', 'city', 'url', 'institution', 'department', 'phone1',
            'phone2', 'address', 'idnumber'],
        'pseudonym' => ['maildisplay', 'lastname', 'firstnamephonetic', 'lastnamephonetic', 'middlename',
            'alternatename', 'picture', 'country', 'city', 'url', 'institution', 'department', 'phone1',
            'phone2', 'address', 'idnumber', 'firstname'],
    ];

    /** A raise's two recorded facts (scope review changes 13 and 2). */
    const GRANT = ['requested' => true, 'emailchecked' => true];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/user/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        // Stored as site_config.py apply would.
        set_config(service::CONFIG, json_encode(['levels' => levels::ORDER, 'withhold' => self::WITHHOLD,
            'neutral_surname' => '·', 'reconcile_minutes' => 60]), 'local_ltuse');
    }

    /**
     * @return \stdClass a learner with fixture values in every field a level withholds
     */
    private function learner(): \stdClass {
        return $this->getDataGenerator()->create_user(['username' => 'ltct-test-u1', 'firstname' => 'Fixfirst',
            'lastname' => 'Fixlast', 'city' => 'Fixcity', 'country' => 'NZ', 'institution' => 'Fixinst',
            'maildisplay' => 1, 'alternatename' => 'Fixalt']);
    }

    public function test_pseudonym_rewrites_the_account_and_keeps_the_real_values(): void {
        $user = $this->learner();
        service::set_protection((int)$user->id, 'pseudonym', ['pseudonym' => 'Kestrel'] + self::GRANT);
        $live = \core_user::get_user($user->id);
        $this->assertSame('Kestrel', $live->firstname);
        $this->assertSame('·', $live->lastname);
        $this->assertSame('', $live->city);
        $this->assertSame('', $live->alternatename);
        $this->assertEquals(0, $live->maildisplay);
        $real = service::real_identity((int)$user->id);
        $this->assertSame(['firstname' => 'Fixfirst', 'lastname' => 'Fixlast', 'level' => 'pseudonym'], $real);
    }

    public function test_lowering_restores_every_held_field_exactly(): void {
        $user = $this->learner();
        service::set_protection((int)$user->id, 'firstname', self::GRANT);
        service::set_protection((int)$user->id, 'none', ['acknowledgehistory' => true]);
        $live = \core_user::get_user($user->id);
        $this->assertSame('Fixfirst', $live->firstname);
        $this->assertSame('Fixlast', $live->lastname);
        $this->assertSame('Fixcity', $live->city);
        $this->assertSame('NZ', $live->country);
        $this->assertSame('Fixalt', $live->alternatename);
        $this->assertEquals(1, $live->maildisplay);
        $this->assertNull(service::row((int)$user->id));
    }

    /**
     * The Principle XI guard (R2): user_update_user() must still store what the
     * before_user_updated callback writes into the hook's object. If core stops passing that
     * object on, this fails before any protected name leaks.
     */
    public function test_the_hook_reapplies_the_protected_record(): void {
        $user = $this->learner();
        service::set_protection((int)$user->id, 'firstname', self::GRANT);
        user_update_user((object)['id' => $user->id, 'lastname' => 'Fixlast', 'city' => 'Fixcity',
            'maildisplay' => 1], false, true);
        $live = \core_user::get_user($user->id);
        $this->assertSame('·', $live->lastname);
        $this->assertSame('', $live->city);
        $this->assertEquals(0, $live->maildisplay);
    }

    public function test_an_exception_never_leaves_the_bypass_set_open(): void {
        $user = $this->learner();
        try {
            service::set_protection((int)$user->id, 'pseudonym', ['pseudonym' => ''] + self::GRANT);
        } catch (\moodle_exception $e) {
            $this->assertSame('protection:err:pseudonym', $e->errorcode);
        }
        $this->assertFalse(service::in_bypass((int)$user->id));
    }

    /**
     * Scope review (Doug, 2026-10-05), changes 13 and 2: a raise records that the person asked
     * and that the email was checked, and is refused without either. Every level is available
     * once the config is stored (decision 2, option a).
     */
    public function test_a_raise_needs_the_request_and_the_email_check(): void {
        global $DB;
        $user = $this->learner();
        foreach ([[[], 'protection:err:notrequested'], [['requested' => true], 'protection:err:emailnotchecked'],
                [['emailchecked' => true], 'protection:err:notrequested']] as [$options, $code]) {
            try {
                service::set_protection((int)$user->id, 'firstname', $options);
                $this->fail('a raise without ' . $code . ' was accepted');
            } catch (\moodle_exception $e) {
                $this->assertSame($code, $e->errorcode);
            }
        }
        $this->assertFalse(service::is_protected((int)$user->id));
        $this->assertTrue(service::level_available('pseudonym'));
        service::set_protection((int)$user->id, 'firstname', self::GRANT);
        $log = $DB->get_record(service::LOGTABLE, ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertEquals(1, $log->requested);
        $this->assertEquals(1, $log->emailchecked);
        // A lowering needs neither.
        service::set_protection((int)$user->id, 'email');
        $this->assertSame('email', service::effective_level((int)$user->id));
    }

    /**
     * Change 15: at First name only, a username that holds the real name is replaced by a
     * neutral one in spec 008's format; the granter gives none.
     */
    public function test_a_real_name_username_is_replaced(): void {
        $user = $this->getDataGenerator()->create_user(['username' => 'fixfirst.fixlast',
            'firstname' => 'Fixfirst', 'lastname' => 'Fixlast']);
        service::set_protection((int)$user->id, 'email', self::GRANT);
        $this->assertSame('fixfirst.fixlast', \core_user::get_user($user->id)->username, 'email keeps it');
        service::set_protection((int)$user->id, 'firstname', self::GRANT);
        $this->assertMatchesRegularExpression('/^ltc-[a-z2-7]{8}$/', \core_user::get_user($user->id)->username);
    }

    public function test_email_warnings(): void {
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Fixfirst', 'lastname' => 'Fixlast',
            'email' => 'fixlast77@example.com']);
        $this->assertSame(['name'], service::email_warnings((int)$user->id));
        $other = $this->getDataGenerator()->create_user(['firstname' => 'Fixfirst', 'lastname' => 'Fixlast',
            'email' => 'kestrel77@example.com']);
        $this->assertSame([], service::email_warnings((int)$other->id));
    }

    /**
     * Change 16: activity is having signed in or being enrolled anywhere.
     */
    public function test_activity_is_a_first_access_or_an_enrolment(): void {
        global $DB;
        $user = $this->learner();
        $this->assertFalse(service::has_activity((int)$user->id));
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        service::reset_caches();
        $this->assertTrue(service::has_activity((int)$user->id));
        $other = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'firstaccess', time(), ['id' => $other->id]);
        $this->assertTrue(service::has_activity((int)$other->id));
    }

    /**
     * Change 14: anyone but the site team grants only at intake: a raise, before any activity.
     */
    public function test_only_the_site_team_changes_protection_after_intake(): void {
        $user = $this->learner();
        $manager = $this->getDataGenerator()->create_user();
        $this->assertFalse(entitlement::is_site_team((int)$manager->id, (int)$user->id));
        service::set_protection((int)$user->id, 'email', self::GRANT, (int)$manager->id);
        foreach ([['none', []], ['email', ['realfirstname' => 'Fixother']]] as [$level, $options]) {
            try {
                service::set_protection((int)$user->id, $level, $options, (int)$manager->id);
                $this->fail("a manager's change to {$level} was accepted");
            } catch (\moodle_exception $e) {
                $this->assertSame('protection:err:siteteam', $e->errorcode);
            }
        }
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        service::reset_caches();
        try {
            service::set_protection((int)$user->id, 'firstname', self::GRANT + ['acknowledgehistory' => true],
                (int)$manager->id);
            $this->fail('a raise after activity was accepted from a manager');
        } catch (\moodle_exception $e) {
            $this->assertSame('protection:err:siteteam', $e->errorcode);
        }
        $this->assertTrue(entitlement::is_site_team((int)get_admin()->id, (int)$user->id));
        service::set_protection((int)$user->id, 'firstname', self::GRANT + ['acknowledgehistory' => true]);
        $this->assertSame('firstname', service::effective_level((int)$user->id));
    }

    public function test_every_change_is_logged_and_a_repair_is_not(): void {
        global $DB;
        $user = $this->learner();
        service::set_protection((int)$user->id, 'firstname', self::GRANT);
        $this->assertEquals(1, $DB->count_records(service::LOGTABLE, ['userid' => $user->id]));
        $DB->set_field('user', 'lastname', 'Fixlast', ['id' => $user->id]);   // A writer the hook misses.
        $this->assertFalse(service::is_settled((int)$user->id));
        service::apply((int)$user->id);
        $this->assertTrue(service::is_settled((int)$user->id));
        $this->assertEquals(1, $DB->count_records(service::LOGTABLE, ['userid' => $user->id]));
    }

    public function test_the_site_team_and_a_mentor_are_entitled_and_a_classmate_is_not(): void {
        $user = $this->learner();
        $classmate = $this->getDataGenerator()->create_user();
        $mentor = $this->getDataGenerator()->create_user();
        $role = create_role('Mentor', 'mentor', 'fixture', '');
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability('local/ltuse:viewidentity', CAP_ALLOW, $role, \context_system::instance()->id);
        role_assign($role, $mentor->id, \context_user::instance($user->id)->id);
        $this->assertFalse(surfaces::supports_anyone((int)$mentor->id), 'nobody is protected yet');
        service::set_protection((int)$user->id, 'email', self::GRANT);
        $admin = get_admin();
        $this->assertTrue(entitlement::can_view_identity((int)$admin->id, (int)$user->id));
        $this->assertTrue(entitlement::can_view_identity((int)$mentor->id, (int)$user->id));
        $this->assertFalse(entitlement::can_view_identity((int)$classmate->id, (int)$user->id));
        $this->assertSame('', entitlement::marker((int)$classmate->id, (int)$user->id));
        $this->assertFalse(entitlement::can_manage_protection((int)$mentor->id, (int)$user->id));
        $this->assertTrue(surfaces::supports_anyone((int)$mentor->id));
        $this->assertFalse(entitlement::may_be_entitled((int)$classmate->id));
        $this->assertFalse(surfaces::supports_anyone((int)$classmate->id));
    }

    /**
     * R7 path 4, narrowed (change 22): a course mentor is entitled only to a learner in their own
     * ltct:mentorgroup:<mentor id> group, in an ltct course, never office hours.
     */
    public function test_a_course_mentor_is_entitled_only_in_their_own_mentor_group(): void {
        global $DB;
        $user = $this->learner();
        service::set_protection((int)$user->id, 'email', self::GRANT);
        $mentor = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $teacher = (int)$DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('local/ltuse:viewidentity', CAP_ALLOW, $teacher, \context_system::instance()->id);
        $generator = $this->getDataGenerator();
        $hours = $generator->create_course(['idnumber' => levels::OFFICEHOURS_COURSE]);
        $generator->enrol_user($user->id, $hours->id, 'student');
        $generator->enrol_user($mentor->id, $hours->id, 'teacher');
        $group = $generator->create_group(['courseid' => $hours->id,
            'idnumber' => entitlement::MENTORGROUP_PREFIX . $mentor->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        entitlement::reset_cache();
        $this->assertFalse(entitlement::can_view_identity((int)$mentor->id, (int)$user->id), 'office hours never count');

        $course = $generator->create_course(['idnumber' => 'ltct:fixture-course']);
        $generator->enrol_user($user->id, $course->id, 'student');
        $generator->enrol_user($mentor->id, $course->id, 'teacher');
        $generator->enrol_user($other->id, $course->id, 'teacher');
        entitlement::reset_cache();
        $this->assertFalse(entitlement::can_view_identity((int)$mentor->id, (int)$user->id), 'no mentor group yet');

        $group = $generator->create_group(['courseid' => $course->id,
            'idnumber' => entitlement::MENTORGROUP_PREFIX . $mentor->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        entitlement::reset_cache();
        $this->assertTrue(entitlement::can_view_identity((int)$mentor->id, (int)$user->id));
        $this->assertFalse(entitlement::can_view_identity((int)$other->id, (int)$user->id),
            'another course mentor of the same course');
    }

    /**
     * Scope review (Doug, 2026-10-05), changes 18 and 19: an account change for someone with no
     * protection writes nothing, and the reconcile task does nothing on a site where nobody is
     * protected.
     */
    public function test_an_unprotected_user_is_left_alone(): void {
        global $DB;
        $user = $this->learner();
        user_update_user((object)['id' => $user->id, 'lastname' => 'Fixother'], false, true);
        $this->assertSame('Fixother', \core_user::get_user($user->id)->lastname);
        $this->assertFalse($DB->record_exists(service::TABLE, ['userid' => $user->id]));
        $this->assertTrue(service::is_settled((int)$user->id));
        $this->assertSame('none', service::apply((int)$user->id));
        (new \local_ltuse\task\reconcile_protection())->execute();
        $this->assertEquals(0, $DB->count_records(service::LOGTABLE));
    }

    public function test_deleting_a_user_removes_their_rows_and_clears_them_as_an_actor(): void {
        global $DB;
        $user = $this->learner();
        $actor = $this->getDataGenerator()->create_user();
        service::set_protection((int)$user->id, 'email', self::GRANT, (int)$actor->id);
        delete_user($actor);
        $this->assertEquals(0, $DB->count_records(service::LOGTABLE, ['actorid' => $actor->id]));
        delete_user(\core_user::get_user($user->id));
        $this->assertFalse($DB->record_exists(service::TABLE, ['userid' => $user->id]));
    }
}
