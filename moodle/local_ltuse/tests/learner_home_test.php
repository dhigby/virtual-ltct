<?php
// PHPUnit tests for the learner home state (spec 007, R3, R12; data-model §2).
//
// Synthetic data only, never run on the shared host: every name here is a fixture value, never
// a real learner or course. Where this runs is spec 004's plan.md "Testing".

namespace local_ltuse;

/**
 * learner_home::state() offers a learner the first lesson they have not completed in a published
 * course they may see, and says empty or done when there is none; applies() keeps the block off
 * the site team's dashboard.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\learner_home
 */
final class learner_home_test extends \advanced_testcase {

    /** @var \stdClass[] the fixture courses by idnumber */
    private $courses = [];

    /** @var int[][] each fixture course's three page cm ids, in course order, by idnumber */
    private $pages = [];

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
        foreach (['ltct:fixture-a', 'ltct:fixture-b', learner_home_rules::OFFICEHOURS_COURSE] as $idnumber) {
            $this->make_course($idnumber);
        }
    }

    /**
     * A course with completion on and three pages, each completing on view.
     *
     * @param string $idnumber
     * @param array $record further course fields
     */
    private function make_course(string $idnumber, array $record = []): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['idnumber' => $idnumber, 'enablecompletion' => 1,
            'fullname' => 'Fixture ' . $idnumber] + $record);
        $this->courses[$idnumber] = $course;
        $this->pages[$idnumber] = [];
        foreach ([1, 2, 3] as $n) {
            $page = $gen->create_module('page', ['course' => $course->id, 'name' => "Fixture page $n",
                'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => COMPLETION_VIEW_REQUIRED]);
            $this->pages[$idnumber][] = (int)$page->cmid;
        }
    }

    /**
     * A learner enrolled as a student in each named course.
     *
     * @param string[] $idnumbers
     * @return \stdClass
     */
    private function learner(array $idnumbers): \stdClass {
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        foreach ($idnumbers as $idnumber) {
            $gen->enrol_user($user->id, $this->courses[$idnumber]->id, 'student');
        }
        return $user;
    }

    /**
     * Mark the $n-th page (1-based) of a course viewed, so it completes.
     *
     * @param string $idnumber
     * @param int $n
     * @param \stdClass $user
     */
    private function view(string $idnumber, int $n, \stdClass $user): void {
        $course = $this->courses[$idnumber];
        $cm = get_fast_modinfo($course, $user->id)->get_cm($this->pages[$idnumber][$n - 1]);
        (new \completion_info($course))->set_module_viewed($cm, $user->id);
    }

    /**
     * The url state() should offer for the $n-th page of a course.
     *
     * @param string $idnumber
     * @param int $n
     * @return string
     */
    private function pageurl(string $idnumber, int $n): string {
        return (new \moodle_url('/mod/page/view.php', ['id' => $this->pages[$idnumber][$n - 1]]))->out(false);
    }

    public function test_no_published_enrolment_is_empty(): void {
        $user = $this->learner([learner_home_rules::OFFICEHOURS_COURSE]);
        $state = learner_home::state((int)$user->id);
        $this->assertSame(learner_home::MODE_EMPTY, $state['mode']);
        $this->assertNull($state['course']);
        $this->assertNull($state['cm']);
    }

    public function test_a_new_learner_is_offered_the_first_lesson(): void {
        $user = $this->learner(['ltct:fixture-a']);
        $state = learner_home::state((int)$user->id);
        $this->assertSame(learner_home::MODE_START, $state['mode']);
        $this->assertSame((int)$this->courses['ltct:fixture-a']->id, $state['course']['id']);
        $this->assertSame('Fixture ltct:fixture-a', $state['course']['fullname']);
        $this->assertSame((new \moodle_url('/course/view.php', ['id' => $this->courses['ltct:fixture-a']->id]))->out(false),
            $state['course']['url']);
        $this->assertSame($this->pages['ltct:fixture-a'][0], $state['cm']['id']);
        $this->assertSame('Fixture page 1', $state['cm']['name']);
        $this->assertSame($this->pageurl('ltct:fixture-a', 1), $state['cm']['url']);
    }

    public function test_after_two_lessons_the_third_is_offered(): void {
        $user = $this->learner(['ltct:fixture-a']);
        $this->view('ltct:fixture-a', 1, $user);
        $this->view('ltct:fixture-a', 2, $user);
        $state = learner_home::state((int)$user->id);
        $this->assertSame(learner_home::MODE_CONTINUE, $state['mode']);
        $this->assertSame($this->pages['ltct:fixture-a'][2], $state['cm']['id']);
        $this->assertSame($this->pageurl('ltct:fixture-a', 3), $state['cm']['url']);
    }

    public function test_a_finished_learner_is_done(): void {
        $user = $this->learner(['ltct:fixture-a']);
        foreach ([1, 2, 3] as $n) {
            $this->view('ltct:fixture-a', $n, $user);
        }
        $state = learner_home::state((int)$user->id);
        $this->assertSame(learner_home::MODE_DONE, $state['mode']);
        $this->assertNull($state['cm']);
    }

    public function test_a_hidden_activity_is_never_offered(): void {
        $user = $this->learner(['ltct:fixture-a']);
        set_coursemodule_visible($this->pages['ltct:fixture-a'][0], 0);
        $state = learner_home::state((int)$user->id);
        $this->assertSame(learner_home::MODE_START, $state['mode']);
        $this->assertSame($this->pages['ltct:fixture-a'][1], $state['cm']['id']);
    }

    public function test_a_hidden_course_is_never_offered(): void {
        global $DB;
        $DB->set_field('course', 'visible', 0, ['id' => $this->courses['ltct:fixture-b']->id]);
        $both = $this->learner(['ltct:fixture-a', 'ltct:fixture-b']);
        // The hidden course was opened last, so only its visibility keeps it from being chosen.
        $DB->insert_record('user_lastaccess', ['userid' => $both->id,
            'courseid' => $this->courses['ltct:fixture-b']->id, 'timeaccess' => time()]);
        $DB->insert_record('user_lastaccess', ['userid' => $both->id,
            'courseid' => $this->courses['ltct:fixture-a']->id, 'timeaccess' => time() - DAYSECS]);
        $state = learner_home::state((int)$both->id);
        $this->assertSame(learner_home::MODE_START, $state['mode']);
        $this->assertSame((int)$this->courses['ltct:fixture-a']->id, $state['course']['id']);

        $hiddenonly = $this->learner(['ltct:fixture-b']);
        $this->assertSame(learner_home::MODE_EMPTY, learner_home::state((int)$hiddenonly->id)['mode']);
    }

    /**
     * Spec 003's mentor role, held by a new fixture mentor in the learner's user context. Made
     * here as admin_test::mentoring() makes it: local_ltuse has no data generator.
     *
     * @param \stdClass $learner
     * @return \stdClass the mentor
     */
    private function mentor_for(\stdClass $learner): \stdClass {
        $gen = $this->getDataGenerator();
        $roleid = mentoring::role_id() ?: (int)$gen->create_role(['shortname' => 'mentor', 'name' => 'Mentor']);
        $mentor = $gen->create_user(['firstname' => 'Fixture', 'lastname' => 'Mentor']);
        role_assign($roleid, $mentor->id, \context_user::instance($learner->id)->id);
        return $mentor;
    }

    public function test_a_mentor_is_offered_as_a_message_route(): void {
        $learner = $this->learner(['ltct:fixture-a']);
        $mentor = $this->mentor_for($learner);
        $onward = learner_home::state((int)$learner->id)['onward'];
        $this->assertNotNull($onward);
        $this->assertCount(1, $onward['mentors']);
        $this->assertSame(fullname($mentor), $onward['mentors'][0]['fullname']);
        $this->assertSame((new \moodle_url('/message/index.php', ['id' => $mentor->id]))->out(false),
            $onward['mentors'][0]['url']);
    }

    public function test_pathway_lines_are_absent_when_levels_are_not_applied(): void {
        foreach ([1, 2, 3, 4] as $level) {
            unset_config('pathwaylevel' . $level, 'local_ltuse');
        }
        $this->assertNull(pathway\view::levels());
        $learner = $this->learner(['ltct:fixture-a']);
        // Nothing onward at all: no section, so no "Where next" heading on its own.
        $this->assertNull(learner_home::state((int)$learner->id)['onward']);
        // With a mentor the section is there, still with no pathway entry.
        $this->mentor_for($learner);
        $onward = learner_home::state((int)$learner->id)['onward'];
        $this->assertNotNull($onward);
        $this->assertArrayNotHasKey('pathways', $onward);
        $this->assertArrayHasKey('mentors', $onward);
    }

    public function test_no_community_line_without_spec_005(): void {
        $learner = $this->learner(['ltct:fixture-a']);
        $this->mentor_for($learner);
        $onward = learner_home::state((int)$learner->id)['onward'];
        $this->assertNotNull($onward);
        $this->assertArrayNotHasKey('community', $onward);
    }

    public function test_a_site_admin_is_not_shown_the_block(): void {
        $learner = $this->learner(['ltct:fixture-a']);
        $this->assertTrue(learner_home::applies((int)$learner->id));
        $this->assertFalse(learner_home::applies((int)get_admin()->id));
    }
}
