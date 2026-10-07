<?php
// PHPUnit tests for the incoming-mail handler step of site_config apply and drift (spec 005,
// specs/005-community-space/contracts/inbound-mail.md "Apply" and "Drift").
//
// Synthetic data only, in the PHPUnit database: no person is read or written here. Never run on
// the shared host; it runs in plugin CI. Where this runs is spec 004's plan.md "Testing".

namespace local_ltuse;

use local_ltuse\siteconfig\inbound;
use local_ltuse\siteconfig\inspector;
use local_ltuse\siteconfig\report;

/**
 * inbound::apply() switches the forum reply handler on as declared, changes nothing on a second
 * run, and never writes a field core does not allow to change; check() is what drift reports for
 * a hand change and for a missing row.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\siteconfig\inbound
 */
final class siteconfig_inbound_test extends \advanced_testcase {

    /** The handler spec 005 declares, as moodle/site/inbound-mail.yaml has it. */
    private const REPLY = '\mod_forum\message\inbound\reply_handler';

    /** Core's private files handler: its can_change_defaultexpiration() is false. */
    private const FILES = '\core\message\inbound\private_files_handler';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The declared row, as the payload carries it.
     *
     * @return array
     */
    private static function declared(): array {
        return ['classname' => self::REPLY, 'enabled' => 1, 'defaultexpiration' => 604800, 'validateaddress' => 1];
    }

    /**
     * The step as the inspector builds it from a payload.
     *
     * @param array[] $handlers
     * @return inbound
     */
    private static function step(array $handlers): inbound {
        $inbound = (new inspector(['inbound_handlers' => $handlers]))->inbound();
        self::assertNotNull($inbound);
        return $inbound;
    }

    /**
     * Run apply and return what it reported.
     *
     * @param array[] $handlers
     * @return array[] the report's items
     */
    private function apply(array $handlers): array {
        $report = new report('apply', true, function() {
        });
        self::step($handlers)->apply($report);
        return $report->items();
    }

    /**
     * @param string $classname
     * @return \stdClass the live messageinbound_handlers row
     */
    private static function row(string $classname): \stdClass {
        global $DB;
        return $DB->get_record('messageinbound_handlers', ['classname' => $classname], '*', MUST_EXIST);
    }

    /**
     * @param array[] $items
     * @return array item => [status or result, kind]
     */
    private static function by_item(array $items): array {
        $out = [];
        foreach ($items as $item) {
            $out[$item['item']] = $item;
        }
        return $out;
    }

    public function test_apply_enables_the_reply_handler_then_changes_nothing(): void {
        global $DB;
        // Start from a row that differs in every field, so each one is written.
        $DB->update_record('messageinbound_handlers', (object)['id' => self::row(self::REPLY)->id, 'enabled' => 0,
            'defaultexpiration' => 86400, 'validateaddress' => 0]);

        $items = self::by_item($this->apply([self::declared()]));
        foreach (['enabled', 'defaultexpiration', 'validateaddress'] as $field) {
            $this->assertSame('changed', $items['inbound:' . self::REPLY . ':' . $field]['status'], $field);
        }
        $row = self::row(self::REPLY);
        $this->assertSame(1, (int)$row->enabled);
        $this->assertSame(604800, (int)$row->defaultexpiration);
        $this->assertSame(1, (int)$row->validateaddress);

        $again = $this->apply([self::declared()]);
        $this->assertCount(3, $again);
        foreach ($again as $item) {
            $this->assertSame('ok', $item['status'], $item['item']);
        }
    }

    public function test_a_hand_change_of_each_field_is_reported(): void {
        global $DB;
        $this->apply([self::declared()]);
        $changes = ['enabled' => 0, 'defaultexpiration' => 0, 'validateaddress' => 0];
        foreach ($changes as $field => $value) {
            $id = self::row(self::REPLY)->id;
            $before = self::row(self::REPLY)->$field;
            $DB->set_field('messageinbound_handlers', $field, $value, ['id' => $id]);

            $items = self::by_item(self::step([self::declared()])->check());
            foreach (array_keys($changes) as $other) {
                $expected = $other === $field ? 'changed' : 'ok';
                $this->assertSame($expected, $items['inbound:' . self::REPLY . ':' . $other]['result'],
                    "{$field} changed by hand, {$other} reported");
            }
            $DB->set_field('messageinbound_handlers', $field, $before, ['id' => $id]);
        }
    }

    public function test_a_missing_row_is_reported_and_nothing_is_created(): void {
        global $DB;
        $DB->delete_records('messageinbound_handlers', ['classname' => self::REPLY]);

        $items = self::step([self::declared()])->check();
        $this->assertCount(1, $items);
        $this->assertSame('inbound:' . self::REPLY, $items[0]['item']);
        $this->assertSame('missing', $items[0]['result']);
        $this->assertEmpty($items[0]['blocking'], 'a missing row fails the run but does not stop it');

        $reported = $this->apply([self::declared()]);
        $this->assertSame('fail', $reported[0]['status']);
        $this->assertSame('missing', $reported[0]['kind']);
        $this->assertFalse($DB->record_exists('messageinbound_handlers', ['classname' => self::REPLY]));
    }

    public function test_a_field_core_will_not_change_is_blocked_and_not_written(): void {
        $live = self::row(self::FILES);
        $this->assertSame(0, (int)$live->defaultexpiration, 'core declares it never expires');
        $declared = ['classname' => self::FILES, 'enabled' => (int)$live->enabled, 'defaultexpiration' => 604800];

        $items = self::by_item(self::step([$declared])->check());
        $blocked = $items['inbound:' . self::FILES . ':defaultexpiration'];
        $this->assertSame(inbound::RESULT_BLOCKED, $blocked['result']);
        $this->assertTrue($blocked['warning']);
        $this->assertEmpty($blocked['blocking']);
        $this->assertSame('ok', $items['inbound:' . self::FILES . ':enabled']['result']);

        $reported = self::by_item($this->apply([$declared]));
        $this->assertSame('skip', $reported['inbound:' . self::FILES . ':defaultexpiration']['status']);
        $this->assertSame('blocked', $reported['inbound:' . self::FILES . ':defaultexpiration']['kind']);
        $this->assertSame(0, (int)self::row(self::FILES)->defaultexpiration, 'never written');
    }

    public function test_a_classname_without_the_leading_backslash_names_the_same_row(): void {
        $declared = ['classname' => ltrim(self::REPLY, '\\')] + self::declared();
        $items = self::step([$declared])->check();
        $this->assertCount(3, $items);
        $this->assertSame('inbound:' . self::REPLY . ':enabled', $items[0]['item']);
    }
}
