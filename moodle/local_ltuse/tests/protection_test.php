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

/**
 * The service writes the protected display into the account and keeps the real values; the
 * hook and the observers hold it against other writers; entitlement decides who sees what.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\protection\service
 * @covers     \local_ltuse\protection\entitlement
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

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/user/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->configure(true);
    }

    /**
     * Store the config as site_config.py apply would.
     *
     * @param bool $ready orgscope_ready
     */
    private function configure(bool $ready): void {
        set_config(service::CONFIG, json_encode(['levels' => levels::ORDER, 'withhold' => self::WITHHOLD,
            'org_minimum_max' => 'firstname', 'neutral_surname' => '', 'reconcile_minutes' => 60,
            'orgscope_ready' => $ready]), 'local_ltuse');
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
        service::set_protection((int)$user->id, 'pseudonym', ['pseudonym' => 'Kestrel']);
        $live = \core_user::get_user($user->id);
        $this->assertSame('Kestrel', $live->firstname);
        $this->assertSame('', $live->lastname);
        $this->assertSame('', $live->city);
        $this->assertSame('', $live->alternatename);
        $this->assertEquals(0, $live->maildisplay);
        $real = service::real_identity((int)$user->id);
        $this->assertSame(['firstname' => 'Fixfirst', 'lastname' => 'Fixlast', 'level' => 'pseudonym'], $real);
    }

    public function test_lowering_restores_every_held_field_exactly(): void {
        $user = $this->learner();
        service::set_protection((int)$user->id, 'firstname');
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
        service::set_protection((int)$user->id, 'firstname');
        user_update_user((object)['id' => $user->id, 'lastname' => 'Fixlast', 'city' => 'Fixcity',
            'maildisplay' => 1], false, true);
        $live = \core_user::get_user($user->id);
        $this->assertSame('', $live->lastname);
        $this->assertSame('', $live->city);
        $this->assertEquals(0, $live->maildisplay);
    }

    public function test_an_exception_never_leaves_the_bypass_set_open(): void {
        $user = $this->learner();
        try {
            service::set_protection((int)$user->id, 'pseudonym', ['pseudonym' => '']);
        } catch (\moodle_exception $e) {
            $this->assertSame('protection:err:pseudonym', $e->errorcode);
        }
        $this->assertFalse(service::in_bypass((int)$user->id));
    }

    public function test_firstname_waits_for_the_organisation_scope(): void {
        $this->configure(false);
        $user = $this->learner();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('protection:err:notready', 'local_ltuse'));
        service::set_protection((int)$user->id, 'firstname');
    }

    public function test_email_is_available_before_the_organisation_scope(): void {
        $this->configure(false);
        $user = $this->learner();
        $this->assertSame('email', service::set_protection((int)$user->id, 'email')['effectivelevel']);
        $this->assertTrue(service::level_available('email'));
        $this->assertFalse(service::level_available('pseudonym'));
    }

    public function test_a_real_name_username_is_refused(): void {
        $user = $this->getDataGenerator()->create_user(['username' => 'fixfirst.fixlast',
            'firstname' => 'Fixfirst', 'lastname' => 'Fixlast']);
        try {
            service::set_protection((int)$user->id, 'firstname');
            $this->fail('a real-name username was accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('protection:err:username', $e->errorcode);
        }
        service::set_protection((int)$user->id, 'firstname', ['newusername' => 'ltc-100001']);
        $this->assertSame('ltc-100001', \core_user::get_user($user->id)->username);
    }

    public function test_every_change_is_logged_and_a_repair_is_not(): void {
        global $DB;
        $user = $this->learner();
        service::set_protection((int)$user->id, 'firstname');
        $this->assertEquals(1, $DB->count_records(service::LOGTABLE, ['userid' => $user->id]));
        $DB->set_field('user', 'lastname', 'Fixlast', ['id' => $user->id]);   // A writer the hook misses.
        $this->assertFalse(service::is_settled((int)$user->id));
        service::apply((int)$user->id);
        $this->assertTrue(service::is_settled((int)$user->id));
        $this->assertEquals(1, $DB->count_records(service::LOGTABLE, ['userid' => $user->id]));
    }

    public function test_the_site_team_and_a_mentor_are_entitled_and_a_classmate_is_not(): void {
        $user = $this->learner();
        service::set_protection((int)$user->id, 'email');
        $classmate = $this->getDataGenerator()->create_user();
        $mentor = $this->getDataGenerator()->create_user();
        $role = create_role('Mentor', 'mentor', 'fixture', '');
        set_role_contextlevels($role, [CONTEXT_USER]);
        assign_capability('local/ltuse:viewidentity', CAP_ALLOW, $role, \context_system::instance()->id);
        role_assign($role, $mentor->id, \context_user::instance($user->id)->id);
        $admin = get_admin();
        $this->assertTrue(entitlement::can_view_identity((int)$admin->id, (int)$user->id));
        $this->assertTrue(entitlement::can_view_identity((int)$mentor->id, (int)$user->id));
        $this->assertFalse(entitlement::can_view_identity((int)$classmate->id, (int)$user->id));
        $this->assertSame('', entitlement::marker((int)$classmate->id, (int)$user->id));
        $this->assertFalse(entitlement::can_manage_protection((int)$mentor->id, (int)$user->id));
    }

    public function test_a_course_mentor_is_entitled_only_in_an_ltct_course(): void {
        global $DB;
        $user = $this->learner();
        service::set_protection((int)$user->id, 'email');
        $mentor = $this->getDataGenerator()->create_user();
        $teacher = (int)$DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('local/ltuse:viewidentity', CAP_ALLOW, $teacher, \context_system::instance()->id);
        $hours = $this->getDataGenerator()->create_course(['idnumber' => levels::OFFICEHOURS_COURSE]);
        $this->getDataGenerator()->enrol_user($user->id, $hours->id, 'student');
        $this->getDataGenerator()->enrol_user($mentor->id, $hours->id, 'teacher');
        entitlement::reset_cache();
        $this->assertFalse(entitlement::can_view_identity((int)$mentor->id, (int)$user->id), 'office hours never count');
        $course = $this->getDataGenerator()->create_course(['idnumber' => 'ltct:fixture-course']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($mentor->id, $course->id, 'teacher');
        entitlement::reset_cache();
        $this->assertTrue(entitlement::can_view_identity((int)$mentor->id, (int)$user->id));
    }

    /**
     * Review finding 1: changing only the minimum must never re-open identities the site team
     * withheld from the organisation's managers.
     */
    public function test_changing_a_minimum_keeps_withheld_identity_withheld(): void {
        global $DB;
        $field = (object)['shortname' => service::ORGFIELD, 'name' => 'Organisation', 'datatype' => 'menu',
            'param1' => "fixture-a\nfixture-b", 'categoryid' => 1, 'visible' => 2, 'locked' => 1];
        $DB->insert_record('user_info_field', $field);
        service::set_org_protection('fixture-a', 'email', false);
        service::set_org_protection('fixture-a', 'none');
        $this->assertFalse(service::managers_see_identity('fixture-a'));
        $this->assertEquals(2, $DB->count_records(service::LOGTABLE, ['userid' => 0, 'source' => 'orgminimum']));
    }

    public function test_deleting_a_user_removes_their_rows_and_clears_them_as_an_actor(): void {
        global $DB;
        $user = $this->learner();
        $actor = $this->getDataGenerator()->create_user();
        service::set_protection((int)$user->id, 'email', [], (int)$actor->id);
        delete_user($actor);
        $this->assertEquals(0, $DB->count_records(service::LOGTABLE, ['actorid' => $actor->id]));
        delete_user(\core_user::get_user($user->id));
        $this->assertFalse($DB->record_exists(service::TABLE, ['userid' => $user->id]));
    }
}
