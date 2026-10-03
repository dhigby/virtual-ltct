<?php
// PHPUnit tests for the office-hours sync (spec 011, research R16; data-model "Office-hours
// membership").
//
// Synthetic data only, in the PHPUnit database: every name here is a fixture value, never a
// real learner, mentor or course. The scheduler activity is not needed: the sync acts on the
// course, its manual enrolment instance and its groups. Where this runs is spec 004's plan.md
// "Testing".

namespace local_ltuse;

/**
 * officehours::sync_pair(), sync_user() and reconcile() keep one group per mentor in step with
 * the user-context mentor relationships, enrol through the course's one manual instance, and
 * suspend rather than unenrol.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\officehours
 * @covers     \local_ltuse\officehours_plan
 */
final class officehours_test extends \advanced_testcase {

    /** @var \stdClass the office-hours course */
    private $course;

    /** @var \stdClass its manual enrolment instance */
    private $instance;

    /** @var int the user-context mentor role */
    private $mentorrole;

    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->dirroot . '/group/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->mentorrole = create_role('Mentor', 'mentor', 'fixture', '');
        set_role_contextlevels($this->mentorrole, [CONTEXT_USER]);
        $this->course = $this->getDataGenerator()->create_course(['idnumber' => officehours::COURSE,
            'fullname' => 'Fixture office hours', 'groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $plugin = enrol_get_plugin('manual');
        $plugin->add_instance($this->course, ['name' => officehours::ENROL_NAME, 'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => (int)$DB->get_field('role', 'id', ['shortname' => 'student'])]);
        $this->instance = officehours::enrol_instance((int)$this->course->id);
        set_config(officehours::TEMPLATE_CONFIG, 'Office hours {n}', 'local_ltuse');
    }

    /**
     * Make $mentor the mentor of $learner, as the site team's page does; the observer syncs.
     *
     * @param \stdClass $mentor
     * @param \stdClass $learner
     */
    private function relate(\stdClass $mentor, \stdClass $learner): void {
        role_assign($this->mentorrole, $mentor->id, \context_user::instance($learner->id)->id);
    }

    /**
     * @param \stdClass $mentor
     * @param \stdClass $learner
     */
    private function unrelate(\stdClass $mentor, \stdClass $learner): void {
        role_unassign($this->mentorrole, $mentor->id, \context_user::instance($learner->id)->id);
    }

    /**
     * @param int $userid
     * @return int|null ENROL_USER_ACTIVE, ENROL_USER_SUSPENDED or null when not enrolled
     */
    private function status(int $userid): ?int {
        global $DB;
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $this->instance->id, 'userid' => $userid]);
        return $ue ? (int)$ue->status : null;
    }

    /**
     * @param int $mentorid
     * @return \stdClass|false the mentor's group
     */
    private function group(int $mentorid) {
        return groups_get_group_by_idnumber((int)$this->course->id, officehours::GROUP_PREFIX . $mentorid);
    }

    public function test_a_new_relationship_enrols_both_and_makes_the_group(): void {
        global $DB;
        $mentor = $this->getDataGenerator()->create_user(['firstname' => 'Fixturementor', 'lastname' => 'One']);
        $learner = $this->getDataGenerator()->create_user();
        $this->relate($mentor, $learner);

        $this->assertSame(ENROL_USER_ACTIVE, $this->status((int)$mentor->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->status((int)$learner->id));
        $context = \context_course::instance($this->course->id);
        $teacher = (int)$DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $student = (int)$DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->assertTrue(user_has_role_assignment($mentor->id, $teacher, $context->id));
        $this->assertTrue(user_has_role_assignment($learner->id, $student, $context->id));

        $group = $this->group((int)$mentor->id);
        $this->assertNotEmpty($group);
        $this->assertSame(GROUPS_VISIBILITY_OWN, (int)$group->visibility);   // never NONE (R16)
        $this->assertStringNotContainsString('Fixturementor', $group->name);  // no names (spec 016)
        $this->assertStringNotContainsString('One', $group->name);
        $this->assertSame('Office hours 1', $group->name);
        foreach ([$mentor, $learner] as $user) {
            $member = $DB->get_record('groups_members', ['groupid' => $group->id, 'userid' => $user->id]);
            $this->assertSame('local_ltuse', $member->component);
            $this->assertSame((int)$mentor->id, (int)$member->itemid);
        }
    }

    public function test_ending_it_removes_and_suspends_but_never_unenrols(): void {
        $mentor = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $this->relate($mentor, $learner);
        $this->unrelate($mentor, $learner);

        $group = $this->group((int)$mentor->id);
        $this->assertNotEmpty($group, 'the group is kept, so its slots keep their history');
        $this->assertFalse(groups_is_member($group->id, $learner->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->status((int)$learner->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->status((int)$mentor->id));
    }

    public function test_one_of_two_mentees_leaving_touches_only_them(): void {
        $mentor = $this->getDataGenerator()->create_user();
        $stay = $this->getDataGenerator()->create_user();
        $leave = $this->getDataGenerator()->create_user();
        $this->relate($mentor, $stay);
        $this->relate($mentor, $leave);
        $this->unrelate($mentor, $leave);

        $group = $this->group((int)$mentor->id);
        $this->assertTrue(groups_is_member($group->id, $stay->id));
        $this->assertFalse(groups_is_member($group->id, $leave->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->status((int)$stay->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->status((int)$mentor->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->status((int)$leave->id));
    }

    public function test_a_learner_with_two_mentors_is_in_two_groups(): void {
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $this->relate($first, $learner);
        $this->relate($second, $learner);

        $this->assertTrue(groups_is_member($this->group((int)$first->id)->id, $learner->id));
        $this->assertTrue(groups_is_member($this->group((int)$second->id)->id, $learner->id));
        $this->assertSame('Office hours 2', $this->group((int)$second->id)->name);
    }

    public function test_reconcile_repairs_a_hand_change_and_reports_counts_only(): void {
        $mentor = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $this->relate($mentor, $learner);
        $group = $this->group((int)$mentor->id);
        groups_remove_member($group->id, $learner->id);   // As a forced removal would.

        $counts = officehours::reconcile();
        $this->assertTrue(groups_is_member($group->id, $learner->id));
        $this->assertSame(1, $counts['add']);
        foreach ($counts as $value) {
            $this->assertIsInt($value, 'reconcile reports counts, never ids or names');
        }
        $again = officehours::reconcile();
        $this->assertSame(0, array_sum(array_diff_key($again, ['orphanbookings' => 0])), 'a second run changes nothing');
    }

    public function test_a_hand_removal_is_refused_in_the_interface(): void {
        $mentor = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $this->relate($mentor, $learner);
        $this->assertFalse(groups_remove_member_allowed($this->group((int)$mentor->id)->id, $learner->id));
    }

    public function test_no_course_yet_means_nothing_happens(): void {
        delete_course($this->course, false);
        $mentor = $this->getDataGenerator()->create_user();
        $learner = $this->getDataGenerator()->create_user();
        $this->relate($mentor, $learner);   // Must not throw.
        $this->assertSame([], officehours::reconcile());
    }
}
