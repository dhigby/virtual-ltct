<?php
// PHPUnit tests for a manager's actions on their own people, and for what a change of
// organisation membership does (spec 002 amendment 2026-10-02, research R10 and R12; data-model
// "Organisation access decision" and "Organisation contact record").
//
// Synthetic data only, in the PHPUnit database: every organisation key is fixture-*, and no
// user, course or cohort here is a real one or an instance-test account (constitution III).
// Where this runs is spec 004's plan.md "Testing". Ending a suspended learner's open sessions
// needs a session handler PHPUnit does not have; T081 checks it on the instance.

namespace local_ltuse;

use local_ltuse\organisation\access;
use local_ltuse\organisation\actions;
use local_ltuse\organisation\contacts;

/**
 * organisation\actions refuses everyone but the manager's own learners and keeps to its
 * per-action rules; organisation\contacts makes and ends manager-member contacts and suspends
 * a leaver's organisation-only enrolments.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\organisation\actions
 * @covers     \local_ltuse\organisation\contacts
 * @covers     \local_ltuse\organisation\people
 * @covers     \local_ltuse\observer
 */
final class organisation_test extends \advanced_testcase {

    /** @var \stdClass[] cohorts by idnumber */
    private $cohorts = [];

    /** @var \stdClass[] categories by idnumber */
    private $categories = [];

    /** @var \stdClass manager of fixture-a */
    private $manager;

    /** @var \stdClass a plain learner of fixture-a */
    private $learner;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->dirroot . '/local/ltuse/lib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();

        $gen->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'ltct_org',
            'name' => 'Fixture organisation']);
        foreach (['ltct:published', 'ltct:pilots', 'ltct:org:fixture-a', 'ltct:org:fixture-b'] as $idnumber) {
            $this->categories[$idnumber] = $gen->create_category(['idnumber' => $idnumber]);
        }
        foreach (['ltct:org:fixture-a', 'ltct:org:fixture-a:managers', 'ltct:org:fixture-b',
                'ltct:org:fixture-b:managers', 'ltct:mentors'] as $idnumber) {
            $this->cohorts[$idnumber] = $gen->create_cohort(['idnumber' => $idnumber, 'visible' => 0]);
        }

        $this->manager = $gen->create_user();
        cohort_add_member($this->cohorts['ltct:org:fixture-a:managers']->id, $this->manager->id);
        $this->learner = $this->person('fixture-a');
    }

    /**
     * A user whose ltct_org is $key and who is in that organisation's member cohort.
     *
     * @param string $key
     * @param bool $join add them to the member cohort
     * @return \stdClass
     */
    private function person(string $key, bool $join = true): \stdClass {
        $user = $this->getDataGenerator()->create_user(['profile_field_ltct_org' => $key]);
        if ($join) {
            cohort_add_member($this->cohorts['ltct:org:' . $key]->id, $user->id);
        }
        return $user;
    }

    /**
     * A published course, ltct:<slug>, in the category with this idnumber.
     *
     * @param string $categoryidnumber
     * @return \stdClass
     */
    private function course(string $categoryidnumber): \stdClass {
        static $n = 0;
        $n++;
        return $this->getDataGenerator()->create_course(['idnumber' => 'ltct:fixture-course-' . $n,
            'category' => $this->categories[$categoryidnumber]->id]);
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @return int|null the status of the user's organisation-enrolment enrolment, or null
     */
    private function org_status(int $courseid, int $userid): ?int {
        global $DB;
        $instance = actions::org_instance((object)['id' => $courseid], false);
        if (!$instance) {
            return null;
        }
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        return $ue ? (int)$ue->status : null;
    }

    public function test_enrol_makes_the_instance_once_and_enrols_as_student(): void {
        global $DB;
        $course = $this->course('ltct:published');
        $second = $this->person('fixture-a');
        $this->setUser($this->manager);

        actions::enrol((int)$this->learner->id, (int)$course->id);
        actions::enrol((int)$second->id, (int)$course->id);

        $instances = $DB->get_records('enrol', ['courseid' => $course->id, 'enrol' => 'self',
            'customchar1' => access::ENROL_MARKER]);
        $this->assertCount(1, $instances);
        $instance = reset($instances);
        $this->assertSame(0, (int)$instance->customint6, 'no learner self-enrolment');
        $this->assertSame(0, (int)$instance->customint4, 'no welcome message');
        $this->assertSame(0, (int)$instance->customint2, 'never unenrolled for inactivity');
        $this->assertNotEmpty($instance->password, 'a random key as a second barrier');
        $this->assertSame(ENROL_INSTANCE_ENABLED, (int)$instance->status);

        $context = \context_course::instance($course->id);
        $student = actions::student_role_id();
        foreach ([$this->learner, $second] as $user) {
            $this->assertTrue(is_enrolled($context, $user, '', true));
            $this->assertTrue(user_has_role_assignment($user->id, $student, $context->id));
        }
    }

    public function test_enrol_keeps_to_the_course_rule(): void {
        $this->setUser($this->manager);
        $own = $this->course('ltct:org:fixture-a');
        actions::enrol((int)$this->learner->id, (int)$own->id);
        $this->assertSame(ENROL_USER_ACTIVE, $this->org_status((int)$own->id, (int)$this->learner->id),
            'their own organisation\'s course');

        foreach (['ltct:pilots', 'ltct:org:fixture-b'] as $idnumber) {
            try {
                actions::enrol((int)$this->learner->id, (int)$this->course($idnumber)->id);
                $this->fail("enrolled into a course in $idnumber");
            } catch (\moodle_exception $e) {
                $this->assertSame('organisation:notthiscourse', $e->errorcode);
            }
        }
    }

    public function test_a_manager_of_two_cannot_cross_organisations(): void {
        cohort_add_member($this->cohorts['ltct:org:fixture-b:managers']->id, $this->manager->id);
        $this->setUser($this->manager);
        $this->expectException(\moodle_exception::class);
        actions::enrol((int)$this->learner->id, (int)$this->course('ltct:org:fixture-b')->id);
    }

    public function test_unenrol_only_from_the_organisation_instance(): void {
        global $DB;
        $course = $this->course('ltct:published');
        $this->getDataGenerator()->enrol_user($this->learner->id, $course->id, 'student', 'manual');
        enrol_get_plugin('cohort')->add_instance($course, ['customint1' => $this->cohorts['ltct:org:fixture-a']->id,
            'roleid' => actions::student_role_id(), 'customint2' => 0]);
        $this->setUser($this->manager);

        try {
            actions::unenrol((int)$this->learner->id, (int)$course->id);
            $this->fail('unenrolled with no organisation enrolment');
        } catch (\moodle_exception $e) {
            $this->assertSame('organisation:notorgenrolment', $e->errorcode);
        }

        actions::enrol((int)$this->learner->id, (int)$course->id);
        actions::unenrol((int)$this->learner->id, (int)$course->id);
        $this->assertNull($this->org_status((int)$course->id, (int)$this->learner->id));
        foreach (['manual', 'cohort'] as $plugin) {
            $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => $plugin], '*', MUST_EXIST);
            $this->assertTrue($DB->record_exists('user_enrolments', ['enrolid' => $instance->id,
                'userid' => $this->learner->id]), "the $plugin enrolment is untouched");
        }
    }

    public function test_suspend_writes_only_the_flag_and_reactivate_undoes_it(): void {
        global $DB;
        $this->setUser($this->manager);
        $sink = $this->redirectEvents();
        $this->assertTrue(actions::suspend((int)$this->learner->id));
        $this->assertFalse(actions::suspend((int)$this->learner->id), 'already suspended');
        $events = array_filter($sink->get_events(), function($e) {
            return $e instanceof \core\event\user_updated;
        });
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertSame((int)$this->manager->id, (int)reset($events)->userid, 'the manager is the actor');

        $after = $DB->get_record('user', ['id' => $this->learner->id]);
        $this->assertSame(1, (int)$after->suspended);
        foreach (['firstname', 'lastname', 'email', 'auth', 'password'] as $field) {
            $this->assertSame((string)$this->learner->$field, (string)$after->$field, "$field unchanged");
        }

        $this->assertTrue(actions::reactivate((int)$this->learner->id));
        $this->assertSame(0, (int)$DB->get_field('user', 'suspended', ['id' => $this->learner->id]));
    }

    public function test_the_unchecked_cores_ask_no_manager_but_keep_their_rules(): void {
        // Spec 008 research R6: the site team act on people no manager may, such as a mentor.
        $siteteam = $this->getDataGenerator()->create_user();
        $mentor = $this->person('fixture-a');
        cohort_add_member($this->cohorts['ltct:mentors']->id, $mentor->id);
        $this->setUser($siteteam);

        try {
            actions::suspend((int)$mentor->id);
            $this->fail('the manager wrapper let a non-manager act');
        } catch (\moodle_exception $e) {
            $this->assertSame('organisation:notyours', $e->errorcode);
        }

        $shared = $this->course('ltct:published');
        actions::do_enrol((int)$mentor->id, (int)$shared->id);
        $this->assertSame(ENROL_USER_ACTIVE, $this->org_status((int)$shared->id, (int)$mentor->id));
        foreach (['ltct:pilots', 'ltct:org:fixture-b'] as $idnumber) {
            try {
                actions::do_enrol((int)$mentor->id, (int)$this->course($idnumber)->id);
                $this->fail("enrolled into a course in $idnumber");
            } catch (\moodle_exception $e) {
                $this->assertSame('organisation:notthiscourse', $e->errorcode);
            }
        }
        actions::do_unenrol((int)$mentor->id, (int)$shared->id);
        $this->assertNull($this->org_status((int)$shared->id, (int)$mentor->id));

        $this->assertTrue(actions::do_suspend((int)$mentor->id));
        $this->assertFalse(actions::do_suspend((int)$mentor->id), 'already suspended');
        $this->assertTrue(actions::do_reactivate((int)$mentor->id));

        $deleted = $this->person('fixture-a');
        delete_user($deleted);
        $refused = ['the site administrator' => [get_admin()->id, 'do_suspend'],
            'the acting user' => [$siteteam->id, 'do_suspend'],
            'the acting user, enrolling' => [$siteteam->id, 'do_enrol'],
            'a deleted account' => [$deleted->id, 'do_enrol']];
        foreach ($refused as $who => [$userid, $method]) {
            try {
                $method === 'do_enrol' ? actions::do_enrol((int)$userid, (int)$shared->id)
                    : actions::do_suspend((int)$userid);
                $this->fail("$method acted on $who");
            } catch (\moodle_exception $e) {
                $this->assertSame('organisation:notyours', $e->errorcode, $who);
            }
        }
    }

    public function test_reset_returns_a_mapped_status(): void {
        $this->setUser($this->manager);
        $sink = $this->redirectEmails();
        $outcome = actions::send_reset((int)$this->learner->id);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertStringStartsWith('organisation:reset:', $outcome);
        foreach ($messages as $message) {
            $this->assertSame($this->learner->email, $message->to, 'only to their own address');
        }
    }

    /**
     * Everyone the manager may see but not manage, and people they may not see at all.
     *
     * @return array[]
     */
    public static function refused_provider(): array {
        return [
            'another organisation\'s learner' => ['other'],
            'a mentor of their organisation' => ['mentor'],
            'a manager of their organisation' => ['manager'],
            'a course teacher with their ltct_org' => ['teacher'],
            'the site admin' => ['admin'],
            'their field set, not yet in the cohort' => ['notjoined'],
            'themselves' => ['self'],
        ];
    }

    /**
     * @param string $who
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refused_provider')]
    public function test_every_action_refuses_who_they_may_not_manage(string $who): void {
        global $DB;
        $gen = $this->getDataGenerator();
        switch ($who) {
            case 'other':
                $target = $this->person('fixture-b');
                break;
            case 'mentor':
                $target = $this->person('fixture-a');
                cohort_add_member($this->cohorts['ltct:mentors']->id, $target->id);
                break;
            case 'manager':
                $target = $this->person('fixture-a');
                cohort_add_member($this->cohorts['ltct:org:fixture-a:managers']->id, $target->id);
                break;
            case 'teacher':
                $target = $this->person('fixture-a');
                $gen->enrol_user($target->id, $this->course('ltct:published')->id, 'editingteacher');
                break;
            case 'admin':
                $target = get_admin();
                $DB->insert_record('user_info_data', (object)['userid' => $target->id, 'data' => 'fixture-a',
                    'fieldid' => $DB->get_field('user_info_field', 'id', ['shortname' => 'ltct_org'])]);
                cohort_add_member($this->cohorts['ltct:org:fixture-a']->id, $target->id);
                break;
            case 'notjoined':
                $target = $this->person('fixture-a', false);
                break;
            default:
                $target = $this->manager;
        }
        $course = $this->course('ltct:published');
        $this->setUser($this->manager);

        $calls = [
            'enrol' => function() use ($target, $course) {
                actions::enrol((int)$target->id, (int)$course->id);
            },
            'unenrol' => function() use ($target, $course) {
                actions::unenrol((int)$target->id, (int)$course->id);
            },
            'reset' => function() use ($target) {
                actions::send_reset((int)$target->id);
            },
            'suspend' => function() use ($target) {
                actions::suspend((int)$target->id);
            },
            'reactivate' => function() use ($target) {
                actions::reactivate((int)$target->id);
            },
        ];
        foreach ($calls as $name => $call) {
            try {
                $call();
                $this->fail("$name was allowed for $who");
            } catch (\moodle_exception $e) {
                $this->assertSame('organisation:notyours', $e->errorcode, "$name for $who");
            }
        }
        $this->assertSame(0, (int)$DB->get_field('user', 'suspended', ['id' => $target->id]));
        $this->assertNull(actions::org_instance($course, false), 'nothing was made');
    }

    public function test_a_learner_who_moves_keeps_shared_and_loses_org_only(): void {
        $shared = $this->course('ltct:published');
        $orgonly = $this->course('ltct:org:fixture-a');
        $this->setUser($this->manager);
        actions::enrol((int)$this->learner->id, (int)$shared->id);
        actions::enrol((int)$this->learner->id, (int)$orgonly->id);
        $this->setAdminUser();

        cohort_remove_member($this->cohorts['ltct:org:fixture-a']->id, $this->learner->id);

        $this->assertSame(ENROL_USER_ACTIVE, $this->org_status((int)$shared->id, (int)$this->learner->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->org_status((int)$orgonly->id, (int)$this->learner->id));
    }

    public function test_reconcile_suspends_what_no_event_reached(): void {
        global $DB;
        $orgonly = $this->course('ltct:org:fixture-a');
        $this->setUser($this->manager);
        actions::enrol((int)$this->learner->id, (int)$orgonly->id);
        $this->setAdminUser();
        // A bulk rule writes cohort_members directly and fires nothing (R12).
        $DB->delete_records('cohort_members', ['cohortid' => $this->cohorts['ltct:org:fixture-a']->id,
            'userid' => $this->learner->id]);

        $counts = contacts::reconcile();
        $this->assertSame(1, $counts['suspended']);
        $this->assertSame(1, $counts['removed']);
        $this->assertSame(ENROL_USER_SUSPENDED, $this->org_status((int)$orgonly->id, (int)$this->learner->id));
        $this->assertSame(['created' => 0, 'removed' => 0, 'suspended' => 0], contacts::reconcile(), 'idempotent');
    }

    public function test_contacts_follow_membership(): void {
        global $DB;
        $this->assertTrue(\core_message\api::is_contact($this->manager->id, $this->learner->id),
            'joining the cohort made the contact');
        $this->assertTrue($DB->record_exists(contacts::TABLE, ['managerid' => $this->manager->id,
            'memberid' => $this->learner->id]));

        // A new manager becomes a contact of each member.
        $second = $this->getDataGenerator()->create_user();
        cohort_add_member($this->cohorts['ltct:org:fixture-a:managers']->id, $second->id);
        $this->assertTrue(\core_message\api::is_contact($second->id, $this->learner->id));

        cohort_remove_member($this->cohorts['ltct:org:fixture-a']->id, $this->learner->id);
        $this->assertFalse(\core_message\api::is_contact($this->manager->id, $this->learner->id));
        $this->assertFalse(\core_message\api::is_contact($second->id, $this->learner->id));
        $this->assertFalse($DB->record_exists(contacts::TABLE, ['memberid' => $this->learner->id]));

        cohort_add_member($this->cohorts['ltct:org:fixture-a']->id, $this->learner->id);
        cohort_remove_member($this->cohorts['ltct:org:fixture-a:managers']->id, $second->id);
        $this->assertFalse(\core_message\api::is_contact($second->id, $this->learner->id), 'a manager who leaves');
        $this->assertTrue(\core_message\api::is_contact($this->manager->id, $this->learner->id), 'the other stays');
    }

    public function test_a_contact_they_made_themselves_survives(): void {
        global $DB;
        $member = $this->person('fixture-a', false);
        \core_message\api::add_contact($this->manager->id, $member->id);
        cohort_add_member($this->cohorts['ltct:org:fixture-a']->id, $member->id);
        $this->assertFalse($DB->record_exists(contacts::TABLE, ['memberid' => $member->id]), 'not ours');

        cohort_remove_member($this->cohorts['ltct:org:fixture-a']->id, $member->id);
        $this->assertTrue(\core_message\api::is_contact($this->manager->id, $member->id));
    }

    public function test_a_contact_still_linked_by_another_organisation_stays(): void {
        // The manager manages both; the member is in both member cohorts.
        cohort_add_member($this->cohorts['ltct:org:fixture-b:managers']->id, $this->manager->id);
        cohort_add_member($this->cohorts['ltct:org:fixture-b']->id, $this->learner->id);
        cohort_remove_member($this->cohorts['ltct:org:fixture-a']->id, $this->learner->id);
        $this->assertTrue(\core_message\api::is_contact($this->manager->id, $this->learner->id));
    }
}
