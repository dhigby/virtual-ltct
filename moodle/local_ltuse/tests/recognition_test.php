<?php
// PHPUnit tests for the completion badge service (spec 013, R1, R4, R5, R15).
//
// Synthetic data only, in the PHPUnit database: every name here is a fixture value, never a
// real learner, course or organisation. Where this runs is spec 004's plan.md "Testing".

namespace local_ltuse;

use core_badges\badge;
use local_ltuse\recognition\badges;
use local_ltuse\siteconfig\badgetemplate;
use local_ltuse\siteconfig\report;
use moodle_exception;

/**
 * badges::sync() creates one badge per course, rewords it in place, activates it only on a
 * delivery publish, and never deactivates it.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\recognition\badges
 * @covers     \local_ltuse\siteconfig\badgetemplate
 */
final class recognition_test extends \advanced_testcase {

    /** A deny pattern, as cbc_wording.DENY_PATTERNS sends it. Fixture data, not the rule. */
    private const DENY = [['pattern' => '\bcertif(?:y|ied|ies|ication|ications)\b', 'why' => 'says certified']];

    /** @var \stdClass the fixture course */
    private $course;

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/badgeslib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('badges_defaultissuername', 'Fixture programme');
        set_config('badges_defaultissuercontact', 'fixture@example.invalid');
        $this->course = $this->getDataGenerator()->create_course(['idnumber' => 'ltct:fixture',
            'fullname' => 'Fixture course', 'startdate' => time() - DAYSECS, 'enablecompletion' => 1]);
        $this->apply_template('Awarded for completing {course}.');
    }

    /**
     * Store a template as site_config.py apply would, and return what apply reported.
     *
     * @param string $description
     * @return array[] the report's items
     */
    private function apply_template(string $description): array {
        $image = imagecreatetruecolor(512, 512);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        $declared = [
            'name' => '{course}: training completed',
            'description' => $description,
            'imagecaption' => 'Fixture caption',
            'message_subject' => 'Training completed: %badgename%',
            'message' => 'See %badgelink%',
            'version' => '1',
            'language' => 'en',
            'image' => ['filename' => 'completion.png', 'sha256' => hash('sha256', $png),
                'content' => base64_encode($png)],
            'deny' => self::DENY,
        ];
        $report = new report('apply', true, function() {
        });
        (new badgetemplate($declared))->apply($report);
        return $report->items();
    }

    /**
     * @return badge the course's mapped badge
     */
    private function badge(): badge {
        global $DB;
        $map = $DB->get_record(badges::TABLE, ['courseid' => $this->course->id], '*', MUST_EXIST);
        return new badge((int)$map->badgeid);
    }

    public function test_a_pilot_publish_creates_one_inactive_badge(): void {
        $result = badges::sync($this->course, false, badgetemplate::stored());
        $this->assertSame('created', $result['badge']);
        $this->assertSame('inactive', $result['status']);
        $this->assertSame([], $result['warnings']);

        $badge = $this->badge();
        $this->assertSame('Fixture course: training completed', $badge->name);
        $this->assertEquals(BADGE_STATUS_INACTIVE, $badge->status);
        $this->assertEquals(BADGE_TYPE_COURSE, $badge->type);
        $this->assertEquals(0, $badge->attachment);
        $this->assertEquals(BADGE_MESSAGE_NEVER, $badge->notification);
        $this->assertSame('Fixture programme', $badge->issuername);
        // An overall criterion and the course criterion, nothing else (R1).
        $this->assertEqualsCanonicalizing([BADGE_CRITERIA_TYPE_OVERALL, BADGE_CRITERIA_TYPE_COURSE],
            array_keys($badge->criteria));

        $again = badges::sync($this->course, false, badgetemplate::stored());
        $this->assertSame('unchanged', $again['badge']);
    }

    public function test_delivery_activates_and_a_pilot_publish_never_deactivates(): void {
        badges::sync($this->course, false, badgetemplate::stored());
        $result = badges::sync($this->course, true, badgetemplate::stored());
        $this->assertSame('activated', $result['status']);
        $this->assertTrue($this->badge()->is_active());

        $result = badges::sync($this->course, true, badgetemplate::stored());
        $this->assertSame('active', $result['status']);

        // Back at stage 7 by mistake: left active, and said so (R5).
        $result = badges::sync($this->course, false, badgetemplate::stored());
        $this->assertSame('active', $result['status']);
        $this->assertSame(['active-not-delivery'], array_column($result['warnings'], 'code'));
        $this->assertTrue($this->badge()->is_active());
    }

    public function test_apply_rewords_in_place_and_keeps_it_active(): void {
        badges::sync($this->course, true, badgetemplate::stored());
        $before = $this->badge();

        $items = $this->apply_template('Awarded for completing {course}, reworded.');
        $after = $this->badge();
        $this->assertSame((int)$before->id, (int)$after->id);
        $this->assertSame('Awarded for completing Fixture course, reworded.', $after->description);
        $this->assertTrue($after->is_active());
        $subjects = array_column(array_filter($items, function($i) {
            return $i['status'] === 'changed';
        }), 'item');
        $this->assertContains('badge ltct:fixture', $subjects);
    }

    public function test_a_title_that_breaks_the_wording_rule_writes_nothing(): void {
        global $DB;
        $this->course->fullname = 'Certified fixture';
        try {
            badges::sync($this->course, true, badgetemplate::stored());
            $this->fail('expected a wording refusal');
        } catch (moodle_exception $e) {
            $this->assertSame('error:badgewording', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists(badges::TABLE, ['courseid' => $this->course->id]));
        $this->assertFalse($DB->record_exists('badge', ['courseid' => $this->course->id]));
    }

    public function test_an_unmapped_badge_in_the_course_is_reported_never_adopted(): void {
        $other = badge::create_badge((object)['name' => 'Fixture copy', 'version' => '1', 'language' => 'en',
            'description' => 'x', 'imagecaption' => '', 'issuername' => 'x', 'issuerurl' => 'https://example.invalid',
            'issuercontact' => '', 'expiry' => 0, 'tags' => []], (int)$this->course->id);
        $result = badges::sync($this->course, false, badgetemplate::stored());
        $this->assertSame('created', $result['badge']);
        $this->assertSame(['badge-extra'], array_column($result['warnings'], 'code'));
        $this->assertNotEquals((int)$other->id, (int)$this->badge()->id);
    }
}
