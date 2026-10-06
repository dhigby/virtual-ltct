<?php
// PHPUnit tests for the section names and summaries service (spec 007 R10,
// contracts/update-sections.md).
//
// Synthetic data only, in the PHPUnit database: every name here is a fixture value, never a
// real learner, course or organisation. Where this runs is spec 004's plan.md "Testing".

namespace local_ltuse;

use core_external\external_api;
use local_ltuse\external\update_sections;

/**
 * update_sections writes a name or a summary only when it differs from what is stored, so an
 * unchanged republish writes no section; an absent summary is left alone, an empty one clears.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\external\update_sections
 */
final class update_sections_test extends \advanced_testcase {

    /** A summary as the publisher sends it: the lesson's Estimated time line, rendered. */
    private const TIME = '<p><strong>Estimated time:</strong> 30 minutes</p>';

    /** @var \stdClass ltct:fixture-a, with sections 1 to 3 as create_course() leaves them */
    private $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course(['idnumber' => 'ltct:fixture-a',
            'fullname' => 'Fixture course', 'numsections' => 3]);
    }

    /**
     * Call the service as the publisher does, and clean the reply as the web service layer would.
     *
     * @param array[] $sections
     * @return array
     */
    private function call(array $sections): array {
        return external_api::clean_returnvalue(update_sections::execute_returns(),
            update_sections::execute('ltct:fixture-a', 3, $sections));
    }

    /**
     * Each lesson section named and given its Estimated time line, as a first publish sends it.
     *
     * @return array[]
     */
    private function first_publish(): array {
        $sections = [];
        foreach ([1, 2, 3] as $n) {
            $sections[] = ['number' => $n, 'name' => 'Fixture lesson ' . $n, 'summary' => self::TIME];
        }
        return $sections;
    }

    /**
     * One section's stored row.
     *
     * @param int $n
     * @return \stdClass
     */
    private function section(int $n): \stdClass {
        global $DB;
        return $DB->get_record('course_sections', ['course' => $this->course->id, 'section' => $n],
            '*', MUST_EXIST);
    }

    /**
     * Write a section's stored fields directly, as a hand edit or an older plugin left them.
     *
     * @param int $n
     * @param array $fields
     */
    private function store(int $n, array $fields): void {
        global $DB;
        foreach ($fields as $field => $value) {
            $DB->set_field('course_sections', $field, $value,
                ['course' => $this->course->id, 'section' => $n]);
        }
        rebuild_course_cache($this->course->id, true);
    }

    /**
     * How many course_section_updated events a call raised: one per section written.
     *
     * @param \core\test\phpunit\event_sink $sink
     * @return int
     */
    private function sections_written(\core\test\phpunit\event_sink $sink): int {
        $events = array_filter($sink->get_events(),
            fn($e) => $e instanceof \core\event\course_section_updated);
        $sink->clear();
        return count($events);
    }

    public function test_first_call_writes_every_name_and_summary(): void {
        $result = $this->call($this->first_publish());

        $this->assertSame(3, $result['renamed']);
        $this->assertSame(3, $result['summaries']);
        $this->assertSame(3, $result['sectionsafter']);
        foreach ([1, 2, 3] as $n) {
            $record = $this->section($n);
            $this->assertSame('Fixture lesson ' . $n, $record->name);
            $this->assertSame(self::TIME, $record->summary);
            $this->assertEquals(FORMAT_HTML, $record->summaryformat);
        }
    }

    public function test_unchanged_republish_writes_no_section(): void {
        $this->call($this->first_publish());
        $sink = $this->redirectEvents();

        $result = $this->call($this->first_publish());

        $this->assertSame(0, $result['renamed']);
        $this->assertSame(0, $result['summaries']);
        $this->assertSame(0, $this->sections_written($sink));
        $sink->close();
    }

    public function test_absent_summary_leaves_the_stored_one(): void {
        $this->call($this->first_publish());

        $result = $this->call([['number' => 1, 'name' => 'Fixture lesson 1 renamed']]);

        $this->assertSame(1, $result['renamed']);
        $this->assertSame(0, $result['summaries']);
        $record = $this->section(1);
        $this->assertSame('Fixture lesson 1 renamed', $record->name);
        $this->assertSame(self::TIME, $record->summary);
    }

    public function test_empty_summary_clears_the_stored_one(): void {
        $this->call($this->first_publish());

        $result = $this->call([['number' => 2, 'name' => 'Fixture lesson 2', 'summary' => '']]);

        $this->assertSame(0, $result['renamed']);
        $this->assertSame(1, $result['summaries']);
        $this->assertSame('', $this->section(2)->summary);
        $this->assertSame(self::TIME, $this->section(1)->summary);
    }

    public function test_empty_name_still_writes_the_summary(): void {
        $this->call($this->first_publish());
        $summary = '<p><strong>Estimated time:</strong> 45 minutes</p>';

        $result = $this->call([['number' => 3, 'name' => '', 'summary' => $summary]]);

        $this->assertSame(0, $result['renamed']);
        $this->assertSame(1, $result['summaries']);
        $record = $this->section(3);
        $this->assertSame('Fixture lesson 3', $record->name);
        $this->assertSame($summary, $record->summary);
    }

    public function test_other_summaryformat_is_rewritten(): void {
        $this->call($this->first_publish());
        $this->store(1, ['summaryformat' => FORMAT_MOODLE]);

        $result = $this->call($this->first_publish());

        $this->assertSame(0, $result['renamed']);
        $this->assertSame(1, $result['summaries']);
        $record = $this->section(1);
        $this->assertSame(self::TIME, $record->summary);
        $this->assertEquals(FORMAT_HTML, $record->summaryformat);
    }

    public function test_null_name_sent_empty_writes_nothing(): void {
        // create_course() leaves a new section's name NULL; a NULL summary must equal '' too.
        $this->assertNull($this->section(2)->name);
        $this->store(3, ['summary' => null]);
        $this->assertNull($this->section(3)->summary);
        $sink = $this->redirectEvents();

        $result = $this->call([
            ['number' => 2, 'name' => ''],
            ['number' => 3, 'name' => '', 'summary' => ''],
        ]);

        $this->assertSame(0, $result['renamed']);
        $this->assertSame(0, $result['summaries']);
        $this->assertSame(0, $this->sections_written($sink));
        $sink->close();
        $this->assertNull($this->section(2)->name);
        $this->assertNull($this->section(3)->summary);
    }
}
