<?php
// PHPUnit tests for spec 005's mentor discussion subscriptions in delivery courses (research
// R10; Q10 as changed in round 2 to "start or post in").
//
// Synthetic data only, in the PHPUnit database: every address is @example.org, every
// organisation key fixture-*. Never run on the shared host; it runs in plugin CI. Where this
// runs is spec 004's plan.md "Testing".

namespace local_ltuse;

use local_ltuse\admin\cohort_enrolment;
use local_ltuse\admin\course_mentor_sync;

/**
 * A course mentor is subscribed to each discussion a mentee starts or posts in, and to no whole
 * forum; the subscription time is set back so the forum cron still sends the opening post; sync
 * subscribes a newly added mentor to their mentees' existing discussions; nothing happens in a
 * space course; and nothing is duplicated.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\mentor_subscriptions
 * @covers     \local_ltuse\observer
 */
final class mentor_subscriptions_test extends \advanced_testcase {

    /** @var \stdClass ltct:fixture-a, in ltct:published, with spec 008's cohort enrolment */
    private $course;

    /** @var \stdClass its forum, optional subscription as spec 012 makes it */
    private $forum;

    /** @var \stdClass ltct:org:fixture-a */
    private $cohort;

    /** @var \stdClass[] keyed m (the mentor), a1, a2 (mentees), a3 (a classmate, nobody's mentee) */
    private $people = [];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        require_once($CFG->dirroot . '/group/lib.php');
        // mod_forum's subscription caches are static and not reset between tests, and every
        // test makes the same ids, so a stale entry would answer for the next test's forum.
        // Cleared before and after each test, as core's mod/forum/tests/mail_test.php does.
        \mod_forum\subscriptions::reset_forum_cache();
        \mod_forum\subscriptions::reset_discussion_cache();
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        set_config('coursementorsync', 1, 'local_ltuse');
        $generator->create_role(['shortname' => 'mentor', 'name' => 'Mentor']);
        $this->cohort = $generator->create_cohort(['idnumber' => 'ltct:org:fixture-a', 'name' => 'Fixture A']);
        $published = $generator->create_category(['idnumber' => 'ltct:published', 'name' => 'Published']);
        $this->course = $generator->create_course(['idnumber' => 'ltct:fixture-a', 'category' => $published->id,
            'fullname' => 'Fixture delivery course']);
        $this->forum = $generator->create_module('forum', ['course' => $this->course->id,
            'forcesubscribe' => FORUM_CHOOSESUBSCRIBE]);
        foreach (['m', 'a1', 'a2', 'a3'] as $key) {
            $this->people[$key] = $generator->create_user(['email' => "fixture-{$key}@example.org"]);
        }
        foreach (['a1', 'a2', 'a3'] as $key) {
            cohort_add_member($this->cohort->id, $this->people[$key]->id);
        }
        $this->assertSame('added', cohort_enrolment::ensure((int)$this->cohort->id, (int)$this->course->id));
    }

    protected function tearDown(): void {
        \mod_forum\subscriptions::reset_forum_cache();
        \mod_forum\subscriptions::reset_discussion_cache();
        parent::tearDown();
    }

    /**
     * Record M as a one-course mentor of a learner, as course_mentor_records does, and sync.
     *
     * @param string $learner a key of $people
     * @param string $mentor a key of $people
     */
    private function mentor(string $learner, string $mentor = 'm'): void {
        global $DB;
        $DB->insert_record(course_mentor_sync::TABLE, ['courseid' => $this->course->id,
            'mentorid' => $this->people[$mentor]->id, 'learnerid' => $this->people[$learner]->id, 'cohortid' => 0,
            'usermodified' => 0, 'timecreated' => time(), 'timemodified' => time()]);
        course_mentor_sync::sync_course((int)$this->course->id);
    }

    /**
     * Start a discussion as a person, then fire the event the forum's own pages fire (the
     * generator calls forum_add_discussion(), which fires none; research R18).
     *
     * @param string $who a key of $people
     * @param \stdClass|null $forum default the delivery course's forum
     * @param array $extra more discussion fields
     * @return \stdClass the forum_discussions record
     */
    private function start(string $who, ?\stdClass $forum = null, array $extra = []): \stdClass {
        global $DB;
        $forum = $forum ?? $this->forum;
        $made = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion(
            ['course' => $forum->course, 'forum' => $forum->id, 'userid' => $this->people[$who]->id] + $extra);
        $discussion = $DB->get_record('forum_discussions', ['id' => $made->id], '*', MUST_EXIST);
        \mod_forum\event\discussion_created::create(['context' => self::forum_context($forum),
            'objectid' => $discussion->id, 'other' => ['forumid' => $forum->id]])->trigger();
        return $discussion;
    }

    /**
     * Reply in a discussion as a person, then fire post_created.
     *
     * @param string $who
     * @param \stdClass $discussion
     * @return \stdClass the forum_posts record
     */
    private function reply(string $who, \stdClass $discussion): \stdClass {
        global $DB;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_post(['discussion' => $discussion->id,
            'userid' => $this->people[$who]->id, 'parent' => $discussion->firstpost]);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        \mod_forum\event\post_created::create(['context' => self::forum_context($forum), 'objectid' => $post->id,
            'other' => ['discussionid' => $discussion->id, 'forumid' => $forum->id, 'forumtype' => $forum->type]])->trigger();
        return $post;
    }

    /**
     * @param \stdClass $forum
     * @return \context_module
     */
    private static function forum_context(\stdClass $forum): \context_module {
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course, false, MUST_EXIST);
        return \context_module::instance($cm->id);
    }

    /**
     * @param string $who
     * @param \stdClass $discussion
     * @return \stdClass[] the person's forum_discussion_subs rows for the discussion
     */
    private function subs(string $who, \stdClass $discussion): array {
        global $DB;
        return array_values($DB->get_records('forum_discussion_subs', ['userid' => $this->people[$who]->id,
            'discussion' => $discussion->id]));
    }

    public function test_a_mentee_starting_a_discussion_subscribes_their_mentor_to_it_only(): void {
        global $DB;
        $this->mentor('a1');
        $this->mentor('a2');
        $discussion = $this->start('a1');

        $this->assertCount(1, $this->subs('m', $discussion));
        $this->assertFalse($DB->record_exists('forum_subscriptions', ['userid' => $this->people['m']->id,
            'forum' => $this->forum->id]), 'never the whole forum');
        $other = $this->start('a3');
        $this->assertCount(0, $this->subs('m', $other), 'not a discussion no mentee is in');
    }

    public function test_the_subscription_time_is_set_back_so_the_cron_sends_the_opening_post(): void {
        global $DB;
        // subscribe_user_to_discussion() stamps time(), which the test clock does not reach, so the
        // post is made in the past instead: the subscription then lands after the post's created,
        // as it does when attachments are saved before the event fires.
        set_config('maxeditingtime', 60);
        $this->mentor('a1');
        $discussion = $this->start('a1', null, ['timemodified' => time() - 600]);
        $created = (int)$DB->get_field('forum_posts', 'created', ['id' => $discussion->firstpost]);
        $this->assertLessThan(time(), $created);

        $subs = $this->subs('m', $discussion);
        $this->assertCount(1, $subs);
        $this->assertSame($created, (int)$subs[0]->preference, 'set back to the first post\'s created');

        \mod_forum\subscriptions::reset_discussion_cache();
        \mod_forum\subscriptions::reset_forum_cache();
        ob_start();
        (new \mod_forum\task\cron_task())->execute();
        ob_end_clean();
        $queued = [];
        foreach ($DB->get_records_select('task_adhoc', 'userid = :userid AND ' . $DB->sql_like('classname', ':class'),
                ['userid' => $this->people['m']->id, 'class' => '%send_user_notifications']) as $task) {
            $queued = array_merge($queued, array_map('intval', (array)json_decode($task->customdata)));
        }
        $this->assertContains((int)$discussion->firstpost, $queued, 'the opening post is queued for the mentor');
    }

    public function test_a_mentees_reply_in_a_classmates_discussion_subscribes_the_mentor(): void {
        $this->mentor('a1');
        $discussion = $this->start('a3');
        $this->assertCount(0, $this->subs('m', $discussion), 'a non-mentee\'s post subscribes nobody');

        $this->reply('a1', $discussion);
        $this->assertCount(1, $this->subs('m', $discussion));
    }

    public function test_a_non_mentees_posts_subscribe_nobody(): void {
        global $DB;
        $this->mentor('a1');
        $discussion = $this->start('a3');
        $this->reply('a2', $discussion);
        $this->assertSame(0, $DB->count_records('forum_discussion_subs', ['discussion' => $discussion->id]));
    }

    public function test_a_mentor_added_by_sync_is_subscribed_to_existing_mentee_discussions(): void {
        $started = $this->start('a1');
        $postedin = $this->start('a3');
        $this->reply('a1', $postedin);
        $untouched = $this->start('a3');
        $later = $this->start('a2');

        $this->mentor('a1');
        $this->assertCount(1, $this->subs('m', $started), 'a discussion the mentee started');
        $this->assertCount(1, $this->subs('m', $postedin), 'a discussion the mentee posted in');
        $this->assertCount(0, $this->subs('m', $untouched));
        $this->assertCount(0, $this->subs('m', $later), 'A2 is not their mentee yet');

        // A mentor who gains a mentee is subscribed to that mentee's discussions too (quickstart V4).
        $this->mentor('a2');
        $this->assertCount(1, $this->subs('m', $later));
    }

    public function test_nothing_happens_in_a_space_course(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $space = $generator->create_course(['idnumber' => 'ltct:site:cohort:7', 'fullname' => 'Fixture space']);
        $forum = $generator->create_module('forum', ['course' => $space->id, 'forcesubscribe' => FORUM_CHOOSESUBSCRIBE]);
        $generator->enrol_user($this->people['a1']->id, $space->id, 'student');
        $generator->enrol_user($this->people['m']->id, $space->id, 'teacher');
        // Even with sync's markers in place, a space is skipped.
        $teacherid = (int)$DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        role_assign($teacherid, $this->people['m']->id, \context_course::instance($space->id)->id,
            course_mentor_sync::COMPONENT, 0);
        $groupid = groups_create_group((object)['courseid' => $space->id, 'name' => 'Fixture group',
            'idnumber' => course_mentor_sync::GROUP_PREFIX . $this->people['m']->id]);
        groups_add_member($groupid, $this->people['a1']->id, course_mentor_sync::COMPONENT, 0);

        $discussion = $this->start('a1', $forum);
        $this->reply('a1', $discussion);
        $this->assertSame(0, $DB->count_records('forum_discussion_subs', ['discussion' => $discussion->id]));
        $this->assertSame(0, mentor_subscriptions::for_post((int)$discussion->firstpost));
        $this->assertSame(0, mentor_subscriptions::existing_for_mentor((int)$this->people['m']->id, (int)$space->id));
    }

    public function test_an_existing_subscription_is_not_duplicated(): void {
        global $DB;
        $this->mentor('a1');
        $discussion = $this->start('a1');
        $this->reply('a1', $discussion);
        $this->assertSame(0, mentor_subscriptions::for_post((int)$discussion->firstpost));
        $this->assertCount(1, $this->subs('m', $discussion));

        // A mentor subscribed to the whole forum gets no discussion row at all.
        \mod_forum\subscriptions::subscribe_user($this->people['m']->id, $this->forum);
        $next = $this->start('a1');
        $this->assertCount(0, $this->subs('m', $next));
        $this->assertTrue(\mod_forum\subscriptions::is_subscribed($this->people['m']->id, $this->forum, $next->id));
        $this->assertSame(1, $DB->count_records('forum_subscriptions', ['userid' => $this->people['m']->id]));
    }

    public function test_a_mentor_who_unsubscribed_from_a_discussion_stays_unsubscribed(): void {
        // Core keeps an opt-out row (-1) only for someone subscribed to the whole forum; without
        // one, unsubscribing deletes the row and leaves nothing to respect.
        $this->mentor('a1');
        \mod_forum\subscriptions::subscribe_user($this->people['m']->id, $this->forum);
        $discussion = $this->start('a1');
        \mod_forum\subscriptions::unsubscribe_user_from_discussion($this->people['m']->id, $discussion);
        $this->reply('a1', $discussion);
        $subs = $this->subs('m', $discussion);
        $this->assertCount(1, $subs);
        $this->assertSame(\mod_forum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED, (int)$subs[0]->preference);
    }
}
