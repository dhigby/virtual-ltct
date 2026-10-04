<?php
// PHPUnit tests for spec 008's administration services: intake (US1) and cohort enrolment
// (US2). The pure rules are tested by tests/admin_harness.php; these cover what needs Moodle.
//
// Synthetic data only, in the PHPUnit database: every address is @example.org, every
// organisation key fixture-*, every name a fixture value. Where this runs is spec 004's
// plan.md "Testing".

namespace local_ltuse;

use local_ltuse\admin\cohort_enrolment;
use local_ltuse\admin\intake_service;

/**
 * intake_service creates accounts as research R2 requires and never writes an existing
 * account's names; cohort_enrolment adds or re-enables one instance per (cohort, course) and
 * disables rather than deletes.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\admin\intake_service
 * @covers     \local_ltuse\admin\cohort_enrolment
 */
final class admin_test extends \advanced_testcase {

    /** @var \stdClass ltct:org:fixture-a */
    private $cohort;

    /** @var \stdClass a shared course, ltct:fixture-course in ltct:published */
    private $course;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $generator->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'ltct_org',
            'name' => 'Organisation', 'param1' => "fixture-a\nfixture-b"]);
        $this->cohort = $generator->create_cohort(['idnumber' => 'ltct:org:fixture-a', 'name' => 'Fixture A']);
        $generator->create_cohort(['idnumber' => 'ltct:org:fixture-b', 'name' => 'Fixture B']);
        $published = $generator->create_category(['idnumber' => 'ltct:published', 'name' => 'Published']);
        $this->course = $generator->create_course(['idnumber' => 'ltct:fixture-course',
            'category' => $published->id, 'fullname' => 'Fixture course']);
    }

    /**
     * One intake row, as ltct_admin.py sends it.
     *
     * @param array $over
     * @return array
     */
    private function row(array $over = []): array {
        return array_merge(['row' => 2, 'email' => 'fixture-learner@example.org', 'firstname' => 'Fixture',
            'lastname' => 'Learner', 'organisation' => 'fixture-a', 'country' => '', 'protection' => '',
            'pseudonym' => '', 'courses' => []], $over);
    }

    /**
     * @param string $email
     * @return \stdClass[] live accounts with this email, case-insensitively
     */
    private function accounts(string $email): array {
        global $DB;
        return $DB->get_records_select('user', 'deleted = 0 AND ' . $DB->sql_equal('email', ':email', false),
            ['email' => $email]);
    }

    // --- intake (T030) ------------------------------------------------------------------------

    public function test_a_created_account_is_set_up_as_research_r2_says(): void {
        global $CFG;
        $sink = $this->redirectEmails();
        $preview = intake_service::preview([$this->row()], false);
        $this->assertSame('', $preview['refusal']);
        $this->assertSame('new', $preview['rows'][0]['outcome']);
        $this->assertSame('f***@example.org', $preview['rows'][0]['key']);

        $answer = intake_service::apply_row($this->row(), 'new');
        $this->assertSame('done', $answer['status']);

        $accounts = $this->accounts('fixture-learner@example.org');
        $this->assertCount(1, $accounts);
        $user = reset($accounts);
        $this->assertSame(1, (int)$user->confirmed);
        $this->assertSame((int)$CFG->mnet_localhost_id, (int)$user->mnethostid);
        $this->assertSame('manual', $user->auth);
        $this->assertMatchesRegularExpression('/^ltc-[a-z2-7]{8}$/', $user->username);
        $this->assertSame('1', (string)get_user_preferences('auth_forcepasswordchange', null, $user->id));
        $this->assertSame('fixture-a', profile_user_record($user->id, false)->ltct_org);
        $this->assertNotSame('', (string)\core_user::get_user($user->id)->password, 'Moodle set a password');
        $this->assertSame(1, $sink->count(), 'Moodle\'s own password email');
        $sink->close();
    }

    public function test_the_organisation_is_set_after_the_account_is_created(): void {
        $this->redirectEmails();
        $events = $this->redirectEvents();
        intake_service::apply_row($this->row(), 'new');
        $names = array_map(function($e) {
            return $e->eventname;
        }, $events->get_events());
        $created = array_search('\core\event\user_created', $names, true);
        $updated = array_search('\core\event\user_updated', $names, true);
        $this->assertNotFalse($created);
        $this->assertNotFalse($updated);
        $this->assertLessThan($updated, $created, 'created with no organisation; ltct_org follows');
        $events->close();
    }

    public function test_a_protected_row_waits_and_creates_nothing_without_spec_016(): void {
        if (class_exists('\local_ltuse\protection\service')) {
            $this->markTestSkipped('spec 016 is installed; quickstart V5 covers this');
        }
        $row = $this->row(['protection' => 'email']);
        $this->assertSame('waits', intake_service::preview([$row], false)['rows'][0]['outcome']);
        $this->assertSame('refused', intake_service::apply_row($row, 'new')['status']);
        $this->assertCount(0, $this->accounts('fixture-learner@example.org'));
    }

    public function test_applying_one_email_twice_makes_one_account(): void {
        $this->redirectEmails();
        $first = intake_service::apply_row($this->row(), 'new');
        // A retry of the same row, as after a lost response, and a second run with the email
        // in another case: both find the account inside the lock.
        $second = intake_service::apply_row($this->row(), 'new');
        $third = intake_service::apply_row($this->row(['email' => 'Fixture-Learner@Example.org']), 'new');
        $this->assertSame('done', $first['status']);
        $this->assertSame('already_done', $second['status']);
        $this->assertSame('already_done', $third['status']);
        $this->assertCount(1, $this->accounts('fixture-learner@example.org'));
    }

    public function test_an_existing_accounts_names_are_never_written(): void {
        $this->redirectEmails();
        $user = $this->getDataGenerator()->create_user(['email' => 'fixture-existing@example.org',
            'firstname' => 'Fixturekept', 'lastname' => 'Asgiven']);
        $row = $this->row(['email' => 'fixture-existing@example.org', 'firstname' => 'Other', 'lastname' => 'Name',
            'country' => 'KE']);
        $this->assertSame('will_set_org', intake_service::preview([$row], false)['rows'][0]['outcome']);
        $this->assertSame('done', intake_service::apply_row($row, 'will_set_org')['status']);

        $after = \core_user::get_user($user->id);
        $this->assertSame('Fixturekept', $after->firstname);
        $this->assertSame('Asgiven', $after->lastname);
        $this->assertSame($user->country, $after->country);
        $this->assertSame($user->username, $after->username);
        $this->assertSame('fixture-a', profile_user_record($user->id, false)->ltct_org);
        $this->assertSame('unchanged', intake_service::preview([$row], false)['rows'][0]['outcome']);
    }

    public function test_another_organisation_is_flagged_and_left_alone(): void {
        $user = $this->getDataGenerator()->create_user(['email' => 'fixture-other@example.org']);
        profile_save_data((object)['id' => $user->id, 'profile_field_ltct_org' => 'fixture-b']);
        $row = $this->row(['email' => 'fixture-other@example.org']);
        $this->assertSame('flagged_other_org', intake_service::preview([$row], false)['rows'][0]['outcome']);
        $this->assertSame('refused', intake_service::apply_row($row, 'new')['status']);
        $this->assertSame('fixture-b', profile_user_record($user->id, false)->ltct_org);
    }

    public function test_a_missing_course_refuses_the_whole_file(): void {
        $preview = intake_service::preview([$this->row(), $this->row(['row' => 3,
            'email' => 'fixture-two@example.org', 'courses' => ['ltct:fixture-missing']])], false);
        $this->assertStringContainsString('ltct:fixture-missing', $preview['refusal']);
        $this->assertSame([], $preview['rows']);
    }

    public function test_per_row_courses_go_through_the_organisation_enrolment(): void {
        $this->redirectEmails();
        $row = $this->row(['courses' => ['ltct:fixture-course']]);
        $this->assertSame('done', intake_service::apply_row($row, 'new')['status']);
        $accounts = $this->accounts('fixture-learner@example.org');
        $user = reset($accounts);
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $user->id, '', true));
        $instance = organisation\actions::org_enrol_instance($this->course);
        $this->assertSame(organisation\access::ENROL_MARKER, $instance->customchar1);
    }

    // --- cohort enrolment (T040) --------------------------------------------------------------

    /**
     * @return \stdClass[] the cohort-sync instances for the fixture cohort in the course
     */
    private function cohort_instances(): array {
        return array_values(array_filter(enrol_get_instances($this->course->id, false), function($i) {
            return $i->enrol === 'cohort' && (int)$i->customint1 === (int)$this->cohort->id;
        }));
    }

    public function test_ensure_twice_makes_one_marked_instance(): void {
        $this->assertSame('added', cohort_enrolment::ensure((int)$this->cohort->id, (int)$this->course->id));
        $this->assertSame('already', cohort_enrolment::ensure((int)$this->cohort->id, (int)$this->course->id));
        $instances = $this->cohort_instances();
        $this->assertCount(1, $instances);
        $this->assertSame('ltct:008', $instances[0]->customchar1);
        $this->assertSame(0, (int)$instances[0]->customint2);
        $this->assertSame(ENROL_INSTANCE_ENABLED, (int)$instances[0]->status);
    }

    public function test_ensure_re_enables_and_reuses_a_hand_made_instance(): void {
        global $DB;
        $student = (int)$DB->get_field('role', 'id', ['shortname' => 'student']);
        $id = enrol_get_plugin('cohort')->add_instance($this->course, ['customint1' => $this->cohort->id,
            'roleid' => $student, 'customint2' => 0, 'status' => ENROL_INSTANCE_DISABLED]);
        $this->assertSame('would_enable',
            cohort_enrolment::preview((int)$this->cohort->id, (int)$this->course->id, 'ensure')['outcome']);
        $this->assertSame('enabled', cohort_enrolment::ensure((int)$this->cohort->id, (int)$this->course->id));
        $instances = $this->cohort_instances();
        $this->assertCount(1, $instances);
        $this->assertSame((int)$id, (int)$instances[0]->id);
        $this->assertSame(ENROL_INSTANCE_ENABLED, (int)$instances[0]->status);
    }

    public function test_remove_disables_never_deletes_and_keeps_history(): void {
        global $DB;
        $learner = $this->getDataGenerator()->create_user(['email' => 'fixture-member@example.org']);
        cohort_add_member($this->cohort->id, $learner->id);
        cohort_enrolment::ensure((int)$this->cohort->id, (int)$this->course->id);
        $instance = $this->cohort_instances()[0];
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $learner->id]));

        $item = $this->getDataGenerator()->create_grade_item(['courseid' => $this->course->id]);
        \grade_item::fetch(['id' => $item->id])->update_final_grade($learner->id, 80);
        $DB->insert_record('course_completions', ['userid' => $learner->id, 'course' => $this->course->id,
            'timeenrolled' => time(), 'timestarted' => time(), 'timecompleted' => time(), 'reaggregate' => 0]);

        $this->assertSame('disabled', cohort_enrolment::remove((int)$this->cohort->id, (int)$this->course->id));
        $this->assertSame('already', cohort_enrolment::remove((int)$this->cohort->id, (int)$this->course->id));

        $instances = $this->cohort_instances();
        $this->assertCount(1, $instances, 'the instance is kept');
        $this->assertSame(ENROL_INSTANCE_DISABLED, (int)$instances[0]->status);
        $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $learner->id]));
        $this->assertTrue($DB->record_exists('grade_grades', ['itemid' => $item->id, 'userid' => $learner->id]));
        $this->assertTrue($DB->record_exists('course_completions', ['userid' => $learner->id,
            'course' => $this->course->id]));
    }

    public function test_the_rules_refuse_another_organisations_course(): void {
        $generator = $this->getDataGenerator();
        $own = $generator->create_category(['idnumber' => 'ltct:org:fixture-b', 'name' => 'Fixture B only']);
        $course = $generator->create_course(['idnumber' => 'ltct:fixture-b-course', 'category' => $own->id]);
        $preview = cohort_enrolment::preview((int)$this->cohort->id, (int)$course->id, 'ensure');
        $this->assertSame('refused', $preview['outcome']);
        $this->assertSame('refused', cohort_enrolment::ensure((int)$this->cohort->id, (int)$course->id));
        $this->assertSame([], array_filter(enrol_get_instances($course->id, false), function($i) {
            return $i->enrol === 'cohort';
        }));
    }
}
