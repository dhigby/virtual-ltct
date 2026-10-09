<?php
// PHPUnit tests for spec 005's course-mentor sync: the per-forum digest overrides it writes,
// records and removes (round 2; data-model "Mail state"). Spec 008's own sync tests are in
// admin_test.php and are unchanged. US1-US3 add their cases here (T025, T050, T073), so this
// file is edited one task at a time.
//
// Synthetic data only, in the PHPUnit database. Where this runs is spec 004's plan.md
// "Testing".

namespace local_ltuse;

use local_ltuse\admin\cohort_enrolment;
use local_ltuse\admin\course_mentor_sync;
use local_ltuse\admin\digest_overrides;

/**
 * digest_overrides writes an override only where the person has made no choice of their own,
 * records every write, marks a record released when the person sets the forum back to their
 * default, removes only an override that is still what sync wrote, and leaves everything in
 * place for a person who can no longer see the forum.
 *
 * In a delivery course (T025), course_mentor_sync gives each course mentor and their synced
 * mentees override 0 on every forum, and takes away only what it recorded, before Teacher goes.
 * Spec 008's enrolment, role and group outcomes are tested, unchanged, in admin_test.php.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\admin\digest_overrides
 * @covers     \local_ltuse\admin\course_mentor_sync
 */
final class course_mentor_sync_test extends \advanced_testcase {

    /** @var \stdClass a generated course */
    private $course;

    /** @var \stdClass a generated forum in it */
    private $forum;

    /** @var \stdClass a user enrolled in the course as student */
    private $user;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->forum = $generator->create_module('forum', ['course' => $this->course->id]);
        $this->user = $generator->create_user();
        $generator->enrol_user($this->user->id, $this->course->id, 'student');
    }

    /**
     * @return int|null the user's forum_digests value for the forum, or null with no row
     */
    private function digest(): ?int {
        global $DB;
        $value = $DB->get_field('forum_digests', 'maildigest',
            ['userid' => $this->user->id, 'forum' => $this->forum->id]);
        return ($value === false) ? null : (int)$value;
    }

    /**
     * @return \stdClass|false the override record for the user and the forum
     */
    private function record() {
        global $DB;
        return $DB->get_record(digest_overrides::TABLE, ['userid' => $this->user->id, 'forumid' => $this->forum->id]);
    }

    /**
     * The person's own choice, as the forum's subscription page writes it.
     *
     * @param int $value
     */
    private function choose(int $value): void {
        forum_set_user_maildigest($this->forum->id, $value, \core_user::get_user($this->user->id));
    }

    public function test_a_write_with_no_row_and_no_record_is_made_and_recorded(): void {
        $this->assertSame('written', digest_overrides::set($this->user->id, $this->forum->id, 0));
        $this->assertSame(0, $this->digest());
        $record = $this->record();
        $this->assertNotFalse($record);
        $this->assertSame(0, (int)$record->value);
        $this->assertSame(0, (int)$record->released);
        // A second run finds its own record and writes nothing.
        $this->assertSame('recorded', digest_overrides::set($this->user->id, $this->forum->id, 0));
    }

    public function test_a_persons_own_choice_is_kept_and_never_recorded(): void {
        global $DB;
        $this->choose(2);
        $this->assertSame('kept_personal', digest_overrides::set($this->user->id, $this->forum->id, 0));
        $this->assertSame(2, $this->digest());
        $this->assertFalse($this->record());
        $this->assertSame(0, $DB->count_records(digest_overrides::TABLE));
    }

    public function test_a_reset_to_default_releases_the_record_and_is_never_overwritten(): void {
        digest_overrides::set($this->user->id, $this->forum->id, 0);
        $this->choose(-1);   // The person sets the forum back to their default.
        $this->assertNull($this->digest());

        $this->assertSame('released', digest_overrides::set($this->user->id, $this->forum->id, 0));
        $this->assertSame(1, (int)$this->record()->released);
        $this->assertNull($this->digest());
        foreach ([1, 2] as $run) {
            $this->assertSame('released', digest_overrides::set($this->user->id, $this->forum->id, 0), "run $run");
            $this->assertNull($this->digest(), "run $run");
        }
    }

    public function test_remove_takes_away_only_an_unchanged_override_it_recorded(): void {
        // No record: the person's own row is not sync's to remove.
        $this->choose(0);
        $this->assertSame('none', digest_overrides::remove($this->user->id, $this->forum->id));
        $this->assertSame(0, $this->digest());
        $this->choose(-1);

        digest_overrides::set($this->user->id, $this->forum->id, 0);
        $this->assertSame('removed', digest_overrides::remove($this->user->id, $this->forum->id));
        $this->assertNull($this->digest());
        $this->assertFalse($this->record());
    }

    public function test_a_value_the_person_changed_is_kept_and_only_the_record_goes(): void {
        digest_overrides::set($this->user->id, $this->forum->id, 0);
        $this->choose(2);
        $this->assertSame('kept_personal', digest_overrides::remove($this->user->id, $this->forum->id));
        $this->assertSame(2, $this->digest());
        $this->assertFalse($this->record());
    }

    public function test_a_person_without_the_capability_is_skipped_and_nothing_changes(): void {
        global $DB;
        digest_overrides::set($this->user->id, $this->forum->id, 0);
        $studentid = (int)$DB->get_field('role', 'id', ['shortname' => 'student']);
        role_unassign($studentid, $this->user->id, \context_course::instance($this->course->id)->id);

        $this->assertSame('skipped_nocap', digest_overrides::remove($this->user->id, $this->forum->id));
        $this->assertSame(0, $this->digest());
        $this->assertNotFalse($this->record());

        // A forum they never had an override on: no call, no exception, no record.
        $other = $this->getDataGenerator()->create_module('forum', ['course' => $this->course->id]);
        $this->assertSame('skipped_nocap', digest_overrides::set($this->user->id, $other->id, 0));
        $this->assertFalse($DB->record_exists(digest_overrides::TABLE,
            ['userid' => $this->user->id, 'forumid' => $other->id]));
    }

    // --- delivery courses (T025) --------------------------------------------------------------

    /** @var \stdClass ltct:fixture-a, in ltct:published, with spec 008's cohort enrolment */
    private $delivery;

    /** @var \stdClass ltct:org:fixture-a, whose members the delivery course enrols */
    private $orgcohort;

    /** @var \stdClass a second forum in the delivery course */
    private $second;

    /**
     * A delivery course with two forums (and the news forum core adds), its cohort enrolled
     * through spec 008, course-mentor sync on, and enrol_cohort's unenrolaction at 3, as
     * moodle/site/settings/groups.yaml pins it. Mentees A1-A4 are cohort members; M is a mentor
     * and no member.
     *
     * @return \stdClass[] keyed m, a1, a2, a3, a4
     */
    private function delivery(): array {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $generator = $this->getDataGenerator();
        set_config('coursementorsync', 1, 'local_ltuse');
        set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES, 'enrol_cohort');
        $generator->create_role(['shortname' => 'mentor', 'name' => 'Mentor']);
        $this->orgcohort = $generator->create_cohort(['idnumber' => 'ltct:org:fixture-a', 'name' => 'Fixture A']);
        $published = $generator->create_category(['idnumber' => 'ltct:published', 'name' => 'Published']);
        $this->delivery = $generator->create_course(['idnumber' => 'ltct:fixture-a', 'category' => $published->id,
            'fullname' => 'Fixture delivery course']);
        $generator->create_module('forum', ['course' => $this->delivery->id]);
        $this->second = $generator->create_module('forum', ['course' => $this->delivery->id]);
        $people = [];
        foreach (['m', 'a1', 'a2', 'a3', 'a4'] as $key) {
            $people[$key] = $generator->create_user(['email' => "fixture-{$key}@example.org"]);
        }
        foreach (['a1', 'a2', 'a3', 'a4'] as $key) {
            cohort_add_member($this->orgcohort->id, $people[$key]->id);
        }
        $this->assertSame('added', cohort_enrolment::ensure((int)$this->orgcohort->id, (int)$this->delivery->id));
        return $people;
    }

    /**
     * Record a one-course mentor, as course_mentor_records does, and sync.
     *
     * @param \stdClass $mentor
     * @param \stdClass $learner
     */
    private function record_mentor(\stdClass $mentor, \stdClass $learner): void {
        global $DB;
        $DB->insert_record(course_mentor_sync::TABLE, ['courseid' => $this->delivery->id, 'mentorid' => $mentor->id,
            'learnerid' => $learner->id, 'cohortid' => 0, 'usermodified' => 0, 'timecreated' => time(),
            'timemodified' => time()]);
        $this->sync();
    }

    /**
     * Delete a one-course mentor record, as course_mentor_records does, and sync.
     *
     * @param \stdClass $mentor
     * @param \stdClass|null $learner null for every record of the mentor
     */
    private function forget_mentor(\stdClass $mentor, ?\stdClass $learner = null): void {
        global $DB;
        $where = ['courseid' => $this->delivery->id, 'mentorid' => $mentor->id];
        if ($learner) {
            $where['learnerid'] = $learner->id;
        }
        $DB->delete_records(course_mentor_sync::TABLE, $where);
        $this->sync();
    }

    /**
     * Sync the delivery course; it must not be busy.
     */
    private function sync(): void {
        $this->assertArrayNotHasKey('busy', course_mentor_sync::sync_course((int)$this->delivery->id));
    }

    /**
     * @return int[] every forum of the delivery course, the news forum included
     */
    private function delivery_forums(): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('forum', 'id', 'course = :course',
            ['course' => $this->delivery->id]));
    }

    /**
     * @param \stdClass $user
     * @param int $forumid
     * @return int|null the user's forum_digests value, or null with no row
     */
    private static function digest_of(\stdClass $user, int $forumid): ?int {
        global $DB;
        $value = $DB->get_field('forum_digests', 'maildigest', ['userid' => $user->id, 'forum' => $forumid]);
        return ($value === false) ? null : (int)$value;
    }

    /**
     * @param \stdClass $user
     * @param int $forumid
     * @return \stdClass|false sync's record of the override
     */
    private static function record_of(\stdClass $user, int $forumid) {
        global $DB;
        return $DB->get_record(digest_overrides::TABLE, ['userid' => $user->id, 'forumid' => $forumid]);
    }

    /**
     * Assert a person has sync's override 0, recorded, on every forum of the course.
     *
     * @param \stdClass $user
     * @param string $who
     */
    private function assert_overridden(\stdClass $user, string $who): void {
        foreach ($this->delivery_forums() as $forumid) {
            $this->assertSame(0, self::digest_of($user, $forumid), "{$who}: override on forum {$forumid}");
            $record = self::record_of($user, $forumid);
            $this->assertNotFalse($record, "{$who}: recorded on forum {$forumid}");
            $this->assertSame(0, (int)$record->value);
        }
    }

    /**
     * Assert a person has no override and no record on any forum of the course.
     *
     * @param \stdClass $user
     * @param string $who
     */
    private function assert_untouched(\stdClass $user, string $who): void {
        foreach ($this->delivery_forums() as $forumid) {
            $this->assertNull(self::digest_of($user, $forumid), "{$who}: no override on forum {$forumid}");
            $this->assertFalse(self::record_of($user, $forumid), "{$who}: no record on forum {$forumid}");
        }
    }

    public function test_a_mentor_and_their_mentees_get_override_0_on_every_forum(): void {
        $people = $this->delivery();
        $this->assertGreaterThanOrEqual(2, count($this->delivery_forums()));
        $this->record_mentor($people['m'], $people['a1']);
        $this->record_mentor($people['m'], $people['a2']);

        $this->assert_overridden($people['m'], 'the mentor');
        $this->assert_overridden($people['a1'], 'mentee A1');
        $this->assert_overridden($people['a2'], 'mentee A2');
        // (b) A learner with no mentor in the course has none.
        $this->assert_untouched($people['a4'], 'A4, no mentor');
    }

    public function test_a_mentees_own_digest_setting_is_kept_and_never_recorded(): void {
        $people = $this->delivery();
        // A3 (quickstart step 3a) has chosen their own setting on one forum before any sync.
        forum_set_user_maildigest($this->second->id, 2, \core_user::get_user($people['a3']->id));
        $this->record_mentor($people['m'], $people['a3']);

        $this->assertSame(2, self::digest_of($people['a3'], (int)$this->second->id));
        $this->assertFalse(self::record_of($people['a3'], (int)$this->second->id));

        $this->forget_mentor($people['m']);
        $this->assertSame(2, self::digest_of($people['a3'], (int)$this->second->id), 'kept after the mentor goes');
    }

    public function test_removing_the_mentor_removes_only_recorded_overrides_before_teacher_goes(): void {
        $people = $this->delivery();
        forum_set_user_maildigest($this->second->id, 2, \core_user::get_user($people['a3']->id));
        foreach (['a1', 'a2', 'a3'] as $key) {
            $this->record_mentor($people['m'], $people[$key]);
        }
        $this->assert_overridden($people['m'], 'the mentor');

        $this->forget_mentor($people['m']);
        // Once Teacher is gone the mentor cannot see the forums and core would refuse the reset,
        // so a clean removal shows it ran in the removerole step, before role_unassign().
        $this->assert_untouched($people['m'], 'the removed mentor');
        $this->assert_untouched($people['a1'], 'A1, no mentor left');
        $this->assert_untouched($people['a2'], 'A2, no mentor left');
        $this->assertSame(2, self::digest_of($people['a3'], (int)$this->second->id), 'A3\'s own setting stays');
        $this->assertFalse(is_enrolled(\context_course::instance($this->delivery->id), $people['m']->id),
            'spec 008 still unenrols the mentor');
    }

    public function test_a_mentee_who_loses_their_mentor_but_stays_enrolled_loses_only_the_recorded_override(): void {
        $people = $this->delivery();
        $this->record_mentor($people['m'], $people['a1']);
        $this->record_mentor($people['m'], $people['a2']);
        // A2 then chooses their own setting on one forum, over sync's override.
        forum_set_user_maildigest($this->second->id, 2, \core_user::get_user($people['a2']->id));

        $this->forget_mentor($people['m'], $people['a2']);
        foreach ($this->delivery_forums() as $forumid) {
            if ($forumid === (int)$this->second->id) {
                $this->assertSame(2, self::digest_of($people['a2'], $forumid), 'their own choice stays');
            } else {
                $this->assertNull(self::digest_of($people['a2'], $forumid));
            }
            $this->assertFalse(self::record_of($people['a2'], $forumid));
        }
        $this->assert_overridden($people['a1'], 'A1, still a mentee');
        $this->assert_overridden($people['m'], 'the mentor, still mentoring A1');
    }

    public function test_a_mentee_who_leaves_the_cohort_keeps_the_override_and_rejoining_writes_nothing(): void {
        global $DB;
        $people = $this->delivery();
        $this->record_mentor($people['m'], $people['a1']);
        $this->record_mentor($people['m'], $people['a2']);
        $before = $DB->get_records(digest_overrides::TABLE, ['userid' => $people['a1']->id], 'id', 'id, forumid, value, released');

        // unenrolaction 3: the enrolment is suspended and the roles go, then sync runs.
        cohort_remove_member($this->orgcohort->id, $people['a1']->id);
        $this->sync();
        $this->assertFalse(is_enrolled(\context_course::instance($this->delivery->id), $people['a1']->id, '', true));
        $this->assertSame(array_keys($before), array_keys($DB->get_records(digest_overrides::TABLE,
            ['userid' => $people['a1']->id], 'id', 'id')), 'the records stay');
        foreach ($this->delivery_forums() as $forumid) {
            $this->assertSame(0, self::digest_of($people['a1'], $forumid), 'the override stays');
        }

        cohort_add_member($this->orgcohort->id, $people['a1']->id);
        $this->sync();
        $this->assertEquals($before, $DB->get_records(digest_overrides::TABLE, ['userid' => $people['a1']->id], 'id',
            'id, forumid, value, released'), 'nothing new is written on rejoining');
        $this->assert_overridden($people['a1'], 'A1, back');
    }

    public function test_a_forum_added_after_enrolment_gets_the_override_on_the_next_sync(): void {
        $people = $this->delivery();
        $this->record_mentor($people['m'], $people['a1']);
        $added = $this->getDataGenerator()->create_module('forum', ['course' => $this->delivery->id]);
        $this->assertNull(self::digest_of($people['m'], (int)$added->id));

        $this->sync();
        $this->assert_overridden($people['m'], 'the mentor');
        $this->assert_overridden($people['a1'], 'A1');
    }

    public function test_an_override_the_person_resets_is_released_and_not_written_again(): void {
        $people = $this->delivery();
        $this->record_mentor($people['m'], $people['a1']);
        $forumid = (int)$this->second->id;
        forum_set_user_maildigest($forumid, -1, \core_user::get_user($people['a1']->id));

        $this->sync();
        $this->assertSame(1, (int)self::record_of($people['a1'], $forumid)->released);
        foreach ([1, 2] as $run) {
            $this->sync();
            $this->assertNull(self::digest_of($people['a1'], $forumid), "run {$run}");
            $this->assertSame(1, (int)self::record_of($people['a1'], $forumid)->released, "run {$run}");
        }
    }

    // --- records core's own clean-up would strand (review of T025) ----------------------------

    public function test_a_mentee_unenrolled_for_the_last_time_gets_the_override_again_when_back(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
        $people = $this->delivery();
        $this->record_mentor($people['m'], $people['a1']);
        $this->assert_overridden($people['a1'], 'A1');
        $before = array_keys($DB->get_records(digest_overrides::TABLE, ['userid' => $people['a1']->id], 'id', 'id'));

        // Their only enrolment is deleted: mod_forum's observer deletes their forum_digests rows,
        // and this plugin's observer their records, so nothing reads as "set back to default".
        $instance = $DB->get_record('enrol', ['courseid' => $this->delivery->id, 'enrol' => 'cohort',
            'customint1' => $this->orgcohort->id], '*', MUST_EXIST);
        enrol_get_plugin('cohort')->unenrol_user($instance, $people['a1']->id);
        $this->assertFalse(is_enrolled(\context_course::instance($this->delivery->id), $people['a1']->id));
        $this->assert_untouched($people['a1'], 'A1, unenrolled');

        // Enrolled again by cohort sync, then synced: override 0 again, on new unreleased records.
        enrol_cohort_sync(new \null_progress_trace(), (int)$this->delivery->id);
        $this->sync();
        $this->assert_overridden($people['a1'], 'A1, back');
        $after = $DB->get_records(digest_overrides::TABLE, ['userid' => $people['a1']->id], 'id', 'id, released');
        $this->assertEmpty(array_intersect($before, array_keys($after)), 'new records');
        foreach ($after as $record) {
            $this->assertSame(0, (int)$record->released);
        }
    }

    public function test_a_deleted_account_loses_its_records(): void {
        global $DB;
        $people = $this->delivery();
        $this->record_mentor($people['m'], $people['a1']);
        $this->record_mentor($people['m'], $people['a2']);
        $this->assertGreaterThan(0, $DB->count_records(digest_overrides::TABLE, ['userid' => $people['a1']->id]));

        delete_user(\core_user::get_user($people['a1']->id));
        $this->assertSame(0, $DB->count_records(digest_overrides::TABLE, ['userid' => $people['a1']->id]));
        // M still mentors A2, so both keep theirs.
        $this->assert_overridden($people['m'], 'the mentor');
        $this->assert_overridden($people['a2'], 'A2');
    }

    public function test_the_reconcile_deletes_records_whose_forum_or_person_is_gone(): void {
        global $DB;
        $people = $this->delivery();
        $this->record_mentor($people['m'], $people['a1']);
        $kept = $DB->count_records(digest_overrides::TABLE);
        $goneforum = (int)$DB->get_field_sql('SELECT MAX(id) FROM {forum}') + 1000;
        $goneuser = (int)$DB->get_field_sql('SELECT MAX(id) FROM {user}') + 1000;
        foreach ([[$people['a2']->id, $goneforum], [$goneuser, (int)$this->second->id]] as [$userid, $forumid]) {
            $DB->insert_record(digest_overrides::TABLE, (object)['userid' => $userid, 'forumid' => $forumid,
                'value' => 0, 'released' => 0, 'timecreated' => time()]);
        }

        $counts = course_mentor_sync::reconcile();
        $this->assertSame(2, $counts['orphanoverrides']);
        $this->assertSame($kept, $DB->count_records(digest_overrides::TABLE), 'live records stay');
        $this->assertSame(0, digest_overrides::remove_orphans());
    }
}
