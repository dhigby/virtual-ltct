<?php
// PHPUnit tests for spec 008's administration services: intake (US1), cohort enrolment (US2)
// and the course-mentor sync (US5). The pure rules are tested by tests/admin_harness.php;
// these cover what needs Moodle.
//
// Synthetic data only, in the PHPUnit database: every address is @example.org, every
// organisation key fixture-*, every name a fixture value. Where this runs is spec 004's
// plan.md "Testing".

namespace local_ltuse;

use local_ltuse\admin\cohort_enrolment;
use local_ltuse\admin\course_mentor_sync;
use local_ltuse\admin\intake_service;

/**
 * intake_service creates accounts as research R2 requires and never writes an existing
 * account's names; cohort_enrolment adds or re-enables one instance per (cohort, course) and
 * disables rather than deletes; course mentors come and go with their reason, in the same
 * request.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\admin\intake_service
 * @covers     \local_ltuse\admin\cohort_enrolment
 * @covers     \local_ltuse\admin\course_mentor_sync
 * @covers     \local_ltuse\admin\observer
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
        // The generator defaults a menu field to 'No'; the applier gives ltct_org no default.
        $generator->create_custom_profile_field(['datatype' => 'menu', 'shortname' => 'ltct_org',
            'name' => 'Organisation', 'param1' => "fixture-a\nfixture-b", 'defaultdata' => '']);
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
        $this->assertSame('fixture-learner@example.org', $user->username, 'the email is the username');
        $this->assertSame('1', (string)get_user_preferences('auth_forcepasswordchange', null, $user->id));
        $this->assertSame('fixture-a', profile_user_record($user->id, false)->ltct_org);
        $this->assertNotSame('', (string)\core_user::get_user($user->id)->password, 'Moodle set a password');
        $this->assertSame(1, $sink->count(), 'Moodle\'s own password email');
        $sink->close();
    }

    public function test_the_username_is_the_email_lowercased(): void {
        $this->redirectEmails();
        $row = $this->row(['email' => ' Fixture-Mixed@Example.ORG ']);
        $this->assertSame('done', intake_service::apply_row($row, 'new')['status']);
        $accounts = $this->accounts('fixture-mixed@example.org');
        $this->assertCount(1, $accounts);
        $this->assertSame('fixture-mixed@example.org', reset($accounts)->username);
    }

    public function test_an_email_that_is_another_accounts_username_is_rejected(): void {
        // Someone else's username is this address (their own email has since changed). Sign-in
        // looks a username up before an email, so the new person could never sign in with it.
        $this->getDataGenerator()->create_user(['username' => 'fixture-learner@example.org',
            'email' => 'fixture-changed@example.org']);
        $row = $this->row();
        $preview = intake_service::preview([$row], false)['rows'][0];
        $this->assertSame('rejected', $preview['outcome']);
        $this->assertSame(get_string('admin:reason:login_clash', 'local_ltuse'), $preview['reason']);
        $this->assertSame('refused', intake_service::apply_row($row, 'new')['status']);
        $this->assertCount(0, $this->accounts('fixture-learner@example.org'), 'no account was made');
    }

    public function test_choose_username_keeps_name_protected_rows_neutral_and_falls_back(): void {
        global $DB;
        $neutral = '/^ltc-[a-z2-7]{8}$/';
        $mail = 'fixture-choose@example.org';
        $this->assertSame($mail, intake_service::choose_username('Fixture-Choose@Example.org', 'none'));
        $this->assertSame($mail, intake_service::choose_username($mail, 'email'));
        // 016 refuses firstname or pseudonym for a username holding the real name (016 R13).
        $this->assertMatchesRegularExpression($neutral, intake_service::choose_username($mail, 'firstname'));
        $this->assertMatchesRegularExpression($neutral, intake_service::choose_username($mail, 'pseudonym'));

        // PARAM_USERNAME drops '+' unless extendedusernamechars is on.
        set_config('extendedusernamechars', 0);
        $this->assertMatchesRegularExpression($neutral, intake_service::choose_username('fixture+tag@example.org', 'none'));
        set_config('extendedusernamechars', 1);
        $this->assertSame('fixture+tag@example.org', intake_service::choose_username('fixture+tag@example.org', 'none'));

        // Longer than user.username's 100 characters.
        $this->assertMatchesRegularExpression($neutral,
            intake_service::choose_username(str_repeat('a', 89) . '@example.org', 'none'));

        // A deleted account still holds its row in the (mnethostid, username) unique index.
        $gone = $this->getDataGenerator()->create_user(['username' => $mail, 'email' => 'fixture-gone@example.org']);
        $DB->set_field('user', 'deleted', 1, ['id' => $gone->id]);
        $this->assertMatchesRegularExpression($neutral, intake_service::choose_username($mail, 'none'));
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

    public function test_a_row_asking_for_no_protection_never_waits(): void {
        // Protection is per person and opt-in, and an organisation has no minimum (Doug,
        // 2026-10-05, scope review): a row with the column blank is brought on whatever 016 can
        // set, installed or not.
        $this->redirectEmails();
        $row = $this->row(['protection' => '']);
        $this->assertSame('new', intake_service::preview([$row], false)['rows'][0]['outcome']);
        $this->assertSame('done', intake_service::apply_row($row, 'new')['status']);
        $this->assertCount(1, $this->accounts('fixture-learner@example.org'));
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
        $instance = organisation\actions::org_instance($this->course, true);
        $this->assertSame(organisation\access::ENROL_MARKER, $instance->customchar1);
    }

    public function test_a_suspended_course_enrolment_is_made_active_again(): void {
        $this->redirectEmails();
        $row = $this->row(['courses' => ['ltct:fixture-course']]);
        intake_service::apply_row($row, 'new');
        $accounts = $this->accounts('fixture-learner@example.org');
        $user = reset($accounts);
        $instance = organisation\actions::org_instance($this->course, true);
        enrol_get_plugin('self')->update_user_enrol($instance, $user->id, ENROL_USER_SUSPENDED);
        $this->assertSame('will_enrol', intake_service::preview([$row], false)['rows'][0]['outcome']);

        $this->assertSame('done', intake_service::apply_row($row, 'will_enrol')['status']);
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $user->id, '', true));
        $this->assertSame('unchanged', intake_service::preview([$row], false)['rows'][0]['outcome']);
    }

    public function test_a_malformed_email_is_rejected_by_the_server_too(): void {
        $row = $this->row(['email' => 'not-an-address']);
        $preview = intake_service::preview([$row], false);
        $this->assertSame('rejected', $preview['rows'][0]['outcome']);
        $this->assertSame('***', $preview['rows'][0]['key']);
        $this->assertSame('refused', intake_service::apply_row($row, 'new')['status']);
        $this->assertCount(0, $this->accounts('not-an-address'));
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

    // --- course mentors (T058) ----------------------------------------------------------------

    /**
     * A learner in ltct:org:fixture-a, enrolled in the shared course through cohort sync, and a
     * mentor; spec 003's mentor role exists and the sync is switched on unless told otherwise.
     *
     * @param bool $on local_ltuse/coursementorsync
     * @return array [learner, mentor, mentor role id]
     */
    private function mentoring(bool $on = true): array {
        $generator = $this->getDataGenerator();
        set_config('coursementorsync', $on ? 1 : 0, 'local_ltuse');
        $roleid = $generator->create_role(['shortname' => 'mentor', 'name' => 'Mentor']);
        $learner = $generator->create_user(['email' => 'fixture-learner@example.org']);
        $mentor = $generator->create_user(['email' => 'fixture-mentor@example.org']);
        cohort_add_member($this->cohort->id, $learner->id);
        $this->assertSame('added', cohort_enrolment::ensure((int)$this->cohort->id, (int)$this->course->id));
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $learner->id, '', true));
        return [$learner, $mentor, (int)$roleid];
    }

    /**
     * What a person holds in the shared course as a course mentor.
     *
     * @param int $userid
     * @return array ['enrolled' => through ltct:coursementor, 'teacher' => Teacher given by
     *               local_ltuse, 'grouped' => in their mentor group, 'anyteacher' => Teacher at all]
     */
    private function course_mentor_state(int $userid): array {
        global $DB;
        $context = \context_course::instance($this->course->id);
        $teacherid = (int)$DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        $instance = null;
        foreach (enrol_get_instances($this->course->id, false) as $i) {
            if (course_mentor_sync::is_own_instance($i)) {
                $instance = $i;
            }
        }
        $grouped = $DB->record_exists_sql("SELECT 1
                                             FROM {groups_members} gm
                                             JOIN {groups} g ON g.id = gm.groupid
                                            WHERE g.courseid = :courseid AND g.idnumber = :idnumber
                                                  AND gm.userid = :userid",
            ['courseid' => $this->course->id, 'idnumber' => course_mentor_sync::GROUP_PREFIX . $userid,
                'userid' => $userid]);
        return [
            'enrolled' => $instance && $DB->record_exists('user_enrolments',
                ['enrolid' => $instance->id, 'userid' => $userid]),
            'teacher' => $DB->record_exists('role_assignments', ['contextid' => $context->id, 'roleid' => $teacherid,
                'userid' => $userid, 'component' => course_mentor_sync::COMPONENT]),
            'grouped' => $grouped,
            'anyteacher' => user_has_role_assignment($userid, $teacherid, $context->id),
        ];
    }

    /** @var array a course mentor in step */
    private const MENTORING = ['enrolled' => true, 'teacher' => true, 'grouped' => true, 'anyteacher' => true];

    /** @var array nobody's course mentor */
    private const NOT_MENTORING = ['enrolled' => false, 'teacher' => false, 'grouped' => false, 'anyteacher' => false];

    public function test_a_default_mentor_is_enrolled_and_removed_with_the_relationship(): void {
        global $DB;
        [$learner, $mentor, $roleid] = $this->mentoring();
        $learnercontext = \context_user::instance($learner->id);

        role_assign($roleid, $mentor->id, $learnercontext->id);
        $this->assertSame(self::MENTORING, $this->course_mentor_state((int)$mentor->id));
        $this->assertTrue($this->in_group_of((int)$mentor->id, (int)$learner->id), 'the learner is in the group');
        $this->assertTrue($this->group_named_neutrally((int)$mentor->id));
        $this->assertEquals(1, $DB->get_field('groups', 'participation', ['courseid' => $this->course->id,
            'idnumber' => course_mentor_sync::GROUP_PREFIX . $mentor->id]),
            'separate-groups activities can use the mentor group (plan decision 3)');

        // Same request: no task, no cron.
        role_unassign($roleid, $mentor->id, $learnercontext->id);
        $this->assertSame(self::NOT_MENTORING, $this->course_mentor_state((int)$mentor->id));
        $this->assertFalse($this->in_group_of((int)$mentor->id, (int)$learner->id));
    }

    public function test_disabling_the_cohort_enrolment_removes_that_cohorts_course_mentors(): void {
        global $DB;
        [$learner, $mentor] = $this->mentoring();
        $DB->insert_record(course_mentor_sync::TABLE, ['courseid' => $this->course->id, 'mentorid' => $mentor->id,
            'learnerid' => 0, 'cohortid' => $this->cohort->id, 'usermodified' => 0, 'timecreated' => time(),
            'timemodified' => time()]);
        course_mentor_sync::sync_course((int)$this->course->id);
        $this->assertSame(self::MENTORING, $this->course_mentor_state((int)$mentor->id));

        $this->assertSame('disabled', cohort_enrolment::remove((int)$this->cohort->id, (int)$this->course->id));
        $this->assertSame(self::NOT_MENTORING, $this->course_mentor_state((int)$mentor->id));
    }

    public function test_disabling_the_instance_directly_is_seen_by_the_observer(): void {
        global $DB;
        [$learner, $mentor] = $this->mentoring();
        $DB->insert_record(course_mentor_sync::TABLE, ['courseid' => $this->course->id, 'mentorid' => $mentor->id,
            'learnerid' => 0, 'cohortid' => $this->cohort->id, 'usermodified' => 0, 'timecreated' => time(),
            'timemodified' => time()]);
        course_mentor_sync::sync_course((int)$this->course->id);
        $instance = cohort_enrolment::find_instance((int)$this->cohort->id, (int)$this->course->id);
        enrol_get_plugin('cohort')->update_status($instance, ENROL_INSTANCE_DISABLED);
        $this->assertSame(self::NOT_MENTORING, $this->course_mentor_state((int)$mentor->id));
    }

    public function test_suspending_the_learners_account_removes_their_course_mentor(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');
        [$learner, $mentor, $roleid] = $this->mentoring();
        role_assign($roleid, $mentor->id, \context_user::instance($learner->id)->id);
        $this->assertSame(self::MENTORING, $this->course_mentor_state((int)$mentor->id));

        user_update_user((object)['id' => $learner->id, 'suspended' => 1], false);
        $this->assertSame(self::NOT_MENTORING, $this->course_mentor_state((int)$mentor->id));
    }

    public function test_a_mentor_enrolled_another_way_still_loses_teacher(): void {
        [$learner, $mentor, $roleid] = $this->mentoring();
        $this->getDataGenerator()->enrol_user($mentor->id, $this->course->id, 'student', 'manual');
        $learnercontext = \context_user::instance($learner->id);
        role_assign($roleid, $mentor->id, $learnercontext->id);
        $this->assertSame(self::MENTORING, $this->course_mentor_state((int)$mentor->id));

        role_unassign($roleid, $mentor->id, $learnercontext->id);
        $this->assertSame(self::NOT_MENTORING, $this->course_mentor_state((int)$mentor->id));
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $mentor->id),
            'their own enrolment stays');
    }

    public function test_a_one_course_mentor_replaces_the_default_mentor_in_that_course(): void {
        global $DB;
        [$learner, $mentor, $roleid] = $this->mentoring();
        $other = $this->getDataGenerator()->create_user(['email' => 'fixture-mentor2@example.org']);
        role_assign($roleid, $mentor->id, \context_user::instance($learner->id)->id);
        $DB->insert_record(course_mentor_sync::TABLE, ['courseid' => $this->course->id, 'mentorid' => $other->id,
            'learnerid' => $learner->id, 'cohortid' => 0, 'usermodified' => 0, 'timecreated' => time(),
            'timemodified' => time()]);
        course_mentor_sync::sync_course((int)$this->course->id);
        $this->assertSame(self::MENTORING, $this->course_mentor_state((int)$other->id));
        $this->assertSame(self::NOT_MENTORING, $this->course_mentor_state((int)$mentor->id));
    }

    public function test_a_pilot_learner_gets_no_course_mentor(): void {
        [$learner, $mentor, $roleid] = $this->mentoring();
        $pilot = $this->getDataGenerator()->create_user(['email' => 'fixture-pilot@example.org']);
        $this->getDataGenerator()->enrol_user($pilot->id, $this->course->id, 'student', 'manual');
        role_assign($roleid, $mentor->id, \context_user::instance($pilot->id)->id);
        $this->assertSame(self::NOT_MENTORING, $this->course_mentor_state((int)$mentor->id));
    }

    public function test_nothing_happens_while_the_sync_is_off(): void {
        global $DB;
        [$learner, $mentor, $roleid] = $this->mentoring(false);
        role_assign($roleid, $mentor->id, \context_user::instance($learner->id)->id);
        $this->assertSame(self::NOT_MENTORING, $this->course_mentor_state((int)$mentor->id));
        $this->assertSame([], course_mentor_sync::sync_course((int)$this->course->id));
        $this->assertSame([], course_mentor_sync::reconcile());
        $this->assertFalse($DB->record_exists('enrol', ['courseid' => $this->course->id,
            'customchar1' => course_mentor_sync::MARKER]), 'no course-mentor instance is made');
    }

    /**
     * @param int $mentorid
     * @param int $userid
     * @return bool the user is in the mentor's group in the shared course
     */
    private function in_group_of(int $mentorid, int $userid): bool {
        global $DB;
        $group = $DB->get_record('groups', ['courseid' => $this->course->id,
            'idnumber' => course_mentor_sync::GROUP_PREFIX . $mentorid]);
        return $group && groups_is_member($group->id, $userid);
    }

    /**
     * @param int $mentorid
     * @return bool the mentor's group is "Mentor group <n>", never a person's name
     */
    private function group_named_neutrally(int $mentorid): bool {
        global $DB;
        $name = (string)$DB->get_field('groups', 'name', ['courseid' => $this->course->id,
            'idnumber' => course_mentor_sync::GROUP_PREFIX . $mentorid]);
        return (bool)preg_match('/^Mentor group \d+$/', $name);
    }
}
