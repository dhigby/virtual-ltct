<?php
// PHPUnit tests for a course author's Pilot learners page (2026-10-15): pilot\rules and
// pilot\actions.
//
// Synthetic data only, in the PHPUnit database: every course is ltct:fixture-*, every address
// is @example.com, and no user, course or category here is a real one or an instance-test
// account (constitution III). Where this runs is spec 004's plan.md "Testing".

namespace local_ltuse;

use local_ltuse\pilot\actions;
use local_ltuse\pilot\rules;

/**
 * pilot\actions enrols a trial student by exact email, only into a pilot, only through the
 * manual method, and only for a holder of local/ltuse:pilotenrol; it removes only that
 * enrolment, and shows or hides the course.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\pilot\rules
 * @covers     \local_ltuse\pilot\actions
 */
final class pilot_test extends \advanced_testcase {

    /** @var \stdClass[] categories by idnumber */
    private $categories = [];

    /** @var \stdClass a pilot, in ltct:pilots, hidden as the publisher creates it */
    private $pilot;

    /** @var \stdClass a delivered course, in ltct:published */
    private $published;

    /** @var \stdClass the course author, holding ltcauthor on ltct:pilots */
    private $author;

    /** @var \stdClass someone with an account, to be enrolled */
    private $learner;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();

        foreach (['ltct:published', 'ltct:pilots'] as $idnumber) {
            $this->categories[$idnumber] = $gen->create_category(['idnumber' => $idnumber]);
        }
        $this->pilot = $gen->create_course(['idnumber' => 'ltct:fixture-pilot', 'visible' => 0,
            'category' => $this->categories['ltct:pilots']->id]);
        $this->published = $gen->create_course(['idnumber' => 'ltct:fixture-published',
            'category' => $this->categories['ltct:published']->id]);

        // The declared role, reduced to what these actions check (moodle/site/roles.yaml).
        $roleid = create_role('LTC course author', 'ltcauthor', '', '');
        set_role_contextlevels($roleid, [CONTEXT_COURSECAT]);
        $system = \context_system::instance();
        foreach (['local/ltuse:pilotenrol', 'moodle/course:visibility', 'moodle/course:view',
                'moodle/course:viewhiddencourses'] as $cap) {
            assign_capability($cap, CAP_ALLOW, $roleid, $system->id);
        }
        $this->author = $gen->create_user();
        role_assign($roleid, $this->author->id, \context_coursecat::instance($this->categories['ltct:pilots']->id));
        role_assign($roleid, $this->author->id, \context_coursecat::instance($this->categories['ltct:published']->id));

        $this->learner = $gen->create_user(['email' => 'fixture.learner@example.com']);
        $this->setUser($this->author);
    }

    /**
     * The enrolment methods $userid is enrolled through in $courseid.
     *
     * @param int $courseid
     * @param int $userid
     * @return string[]
     */
    private function methods(int $courseid, int $userid): array {
        global $DB;
        return array_values($DB->get_fieldset_sql("SELECT e.enrol FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = ? AND ue.userid = ?", [$courseid, $userid]));
    }

    /**
     * Enrol the learner in the pilot through self enrolment: any method but manual. Uses the
     * course's default self instance if it has one, so the generator finds exactly one.
     */
    private function enrol_by_self(): void {
        global $DB;
        if (!$DB->record_exists('enrol', ['courseid' => $this->pilot->id, 'enrol' => 'self'])) {
            enrol_get_plugin('self')->add_instance($this->pilot, ['status' => ENROL_INSTANCE_ENABLED]);
        }
        $this->getDataGenerator()->enrol_user($this->learner->id, $this->pilot->id, 'student', 'self');
    }

    public function test_rules_course(): void {
        $this->assertNull(rules::course('ltct:fixture-pilot', 'ltct:pilots'));
        $this->assertSame('pilot:notpilot', rules::course('ltct:fixture-pilot', 'ltct:published'));
        $this->assertSame('pilot:notpilot', rules::course('ltct:fixture-pilot', 'ltct:org:fixture-a'));
        $this->assertSame('pilot:notpilot', rules::course('ltct:fixture-pilot:03', 'ltct:pilots'));
        $this->assertSame('pilot:notpilot', rules::course('ltct:officehours', 'ltct:pilots'));
        $this->assertSame('pilot:notpilot', rules::course('', 'ltct:pilots'));
    }

    public function test_rules_enrol(): void {
        $ok = ['deleted' => 0, 'suspended' => 0, 'confirmed' => 1, 'siteadmin' => false, 'self' => false];
        $this->assertNull(rules::enrol($ok, []));
        $this->assertSame('pilot:noaccount', rules::enrol(['suspended' => 1] + $ok, []));
        $this->assertSame('pilot:noaccount', rules::enrol(['deleted' => 1] + $ok, []));
        $this->assertSame('pilot:noaccount', rules::enrol(['confirmed' => 0] + $ok, []));
        $this->assertSame('pilot:notallowed', rules::enrol(['siteadmin' => true] + $ok, []));
        $this->assertSame('pilot:notallowed', rules::enrol(['self' => true] + $ok, []));
        $this->assertSame('pilot:otherenrolment', rules::enrol($ok, ['cohort']));
        $this->assertSame('pilot:otherenrolment', rules::enrol($ok, ['manual', 'self']));
        $this->assertSame('pilot:alreadyenrolled', rules::enrol($ok, ['manual']));
    }

    public function test_enrol_by_exact_email_as_manual_student(): void {
        $user = actions::enrol($this->pilot->id, '  Fixture.Learner@EXAMPLE.com ');
        $this->assertEquals($this->learner->id, $user->id);
        $this->assertSame(['manual'], $this->methods($this->pilot->id, $this->learner->id));
        $context = \context_course::instance($this->pilot->id);
        $this->assertTrue(user_has_role_assignment($this->learner->id, actions::student_role_id(), $context->id));
        $this->assertSame([['id' => (int)$this->learner->id, 'name' => fullname($this->learner)]],
            actions::learners($this->pilot->id));
    }

    public function test_near_miss_address_is_refused(): void {
        foreach (['fixture.learner@example.co', 'fixture.learner', 'learner@example.com', '', '%@example.com'] as $email) {
            try {
                actions::enrol($this->pilot->id, $email);
                $this->fail("enrolled through '$email'");
            } catch (\moodle_exception $e) {
                $this->assertSame('pilot:noaccount', $e->errorcode, $email);
            }
        }
        $this->assertSame([], $this->methods($this->pilot->id, $this->learner->id));
    }

    public function test_suspended_account_is_refused(): void {
        global $DB;
        $DB->set_field('user', 'suspended', 1, ['id' => $this->learner->id]);
        $this->expectExceptionMessage(get_string('pilot:noaccount', 'local_ltuse'));
        actions::enrol($this->pilot->id, 'fixture.learner@example.com');
    }

    public function test_two_accounts_with_one_address_are_refused(): void {
        $this->getDataGenerator()->create_user(['email' => 'FIXTURE.learner@example.com']);
        $this->expectExceptionMessage(get_string('pilot:noaccount', 'local_ltuse'));
        actions::enrol($this->pilot->id, 'fixture.learner@example.com');
    }

    public function test_author_cannot_enrol_themselves(): void {
        global $DB;
        $DB->set_field('user', 'email', 'fixture.author@example.com', ['id' => $this->author->id]);
        $this->expectExceptionMessage(get_string('pilot:notallowed', 'local_ltuse'));
        actions::enrol($this->pilot->id, 'fixture.author@example.com');
    }

    public function test_someone_enrolled_another_way_is_refused(): void {
        $this->enrol_by_self();
        $this->expectExceptionMessage(get_string('pilot:otherenrolment', 'local_ltuse'));
        actions::enrol($this->pilot->id, 'fixture.learner@example.com');
    }

    public function test_course_outside_pilots_is_refused(): void {
        $this->expectExceptionMessage(get_string('pilot:notpilot', 'local_ltuse'));
        actions::enrol($this->published->id, 'fixture.learner@example.com');
    }

    public function test_without_the_capability_is_refused(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        actions::enrol($this->pilot->id, 'fixture.learner@example.com');
    }

    public function test_disabled_manual_instance_is_refused(): void {
        global $DB;
        $instance = actions::manual_instance($this->pilot, true);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instance->id]);
        $this->expectExceptionMessage(get_string('pilot:manualdisabled', 'local_ltuse'));
        actions::enrol($this->pilot->id, 'fixture.learner@example.com');
    }

    public function test_remove_ends_only_the_manual_enrolment(): void {
        actions::enrol($this->pilot->id, 'fixture.learner@example.com');
        actions::remove($this->pilot->id, $this->learner->id);
        $this->assertSame([], $this->methods($this->pilot->id, $this->learner->id));
        $this->assertSame([], actions::learners($this->pilot->id));

        // Someone enrolled another way is not a trial student, so nothing is removed.
        $this->enrol_by_self();
        try {
            actions::remove($this->pilot->id, $this->learner->id);
            $this->fail('removed a self enrolment');
        } catch (\moodle_exception $e) {
            $this->assertSame('pilot:notenrolled', $e->errorcode);
        }
        $this->assertSame(['self'], $this->methods($this->pilot->id, $this->learner->id));
    }

    public function test_show_and_hide(): void {
        global $DB;
        actions::set_visible($this->pilot->id, true);
        $this->assertEquals(1, $DB->get_field('course', 'visible', ['id' => $this->pilot->id]));
        actions::set_visible($this->pilot->id, false);
        $this->assertEquals(0, $DB->get_field('course', 'visible', ['id' => $this->pilot->id]));

        $this->expectExceptionMessage(get_string('pilot:notpilot', 'local_ltuse'));
        actions::set_visible($this->published->id, false);
    }
}
