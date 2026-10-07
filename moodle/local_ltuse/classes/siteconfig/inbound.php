<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

/**
 * Switches the forum's incoming-mail handler on and keeps it as declared (spec 005,
 * specs/005-community-space/contracts/inbound-mail.md "Apply" and "Drift").
 *
 * The declaration arrives as the payload's `inbound_handlers`, already checked by
 * scripts/site_config.py:
 *
 *   inbound_handlers [{classname, enabled, defaultexpiration, validateaddress}]
 *
 * classname carries the leading backslash, as record_from_handler() stores it. Core keeps a
 * handler in the messageinbound_handlers table, not in config, so apply does what core's own
 * edit page does (admin/tool/messageinbound/index.php): get_handler(), record_from_handler(),
 * then each field written only where the handler's can_change_*() allows it, then
 * $DB->update_record(). That direct write is listed in README.md (constitution XI); core has no
 * API for it. Core creates the rows on install and upgrade; apply never creates or deletes one.
 *
 * Items, one per handler field, subject `inbound:<classname>:<field>`:
 *   ok        the live row holds the declared value
 *   changed   it differs, and apply writes it
 *   blocked   it differs, and the handler does not allow that field to change: a warning
 *             ([skip]), never written, never blocking the run
 * and one item `inbound:<classname>`, `missing`, when the row is gone (the class or its
 * component is not installed). That fails the run but does not block it.
 *
 * APIs, confirmed on MOODLE_502_STABLE (research.md R18): \core\message\inbound\manager::
 * get_handler() (lib/classes/message/inbound/manager.php L228-239), record_from_handler()
 * (L170-182), and the handler's can_change_enabled(), can_change_defaultexpiration(),
 * can_change_validateaddress() (lib/classes/message/inbound/handler.php L129-164).
 */
class inbound {

    /** Item type. */
    const TYPE = 'inbound';

    /** The result for a field core will not let us change. */
    const RESULT_BLOCKED = 'blocked';

    /** Each declared field, and the handler method that says whether it may change. */
    const FIELDS = [
        'enabled' => 'can_change_enabled',
        'defaultexpiration' => 'can_change_defaultexpiration',
        'validateaddress' => 'can_change_validateaddress',
    ];

    /** The core table the handlers live in. */
    const TABLE = 'messageinbound_handlers';

    /** @var array[] the payload's inbound_handlers */
    protected $handlers;

    /**
     * @param array[] $handlers
     */
    public function __construct(array $handlers) {
        $this->handlers = $handlers;
    }

    /**
     * Every declared handler's items. WRITES NOTHING.
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        $items = [];
        foreach ($this->handlers as $declared) {
            $items = array_merge($items, $this->check_handler((array)$declared));
        }
        return $items;
    }

    /**
     * One handler's items. WRITES NOTHING.
     *
     * @param array $declared {classname, enabled, defaultexpiration, validateaddress}
     * @return array[]
     */
    public function check_handler(array $declared): array {
        $classname = self::classname((string)($declared['classname'] ?? ''));
        $handler = \core\message\inbound\manager::get_handler($classname);
        if (!$handler) {
            return [self::result("inbound:{$classname}", 'missing', 'present', null,
                'no messageinbound_handlers row for this class; is its component installed?')];
        }
        $record = \core\message\inbound\manager::record_from_handler($handler);
        $items = [];
        foreach (self::FIELDS as $field => $canchange) {
            if (!array_key_exists($field, $declared)) {
                continue;
            }
            $want = (int)$declared[$field];
            $live = (int)$record->$field;
            $subject = "inbound:{$classname}:{$field}";
            if ($want === $live) {
                $items[] = self::result($subject, 'ok', $want, $live);
            } else if (!$handler->$canchange()) {
                $items[] = ['warning' => true] + self::result($subject, self::RESULT_BLOCKED, $want, $live,
                    "the handler does not allow {$field} to change ({$canchange}() is false); not written");
            } else {
                $items[] = self::result($subject, 'changed', $want, $live);
            }
        }
        return $items;
    }

    /**
     * Write every field that differs and may change, one update per handler, then check again.
     *
     * @param report $report
     */
    public function apply(report $report): void {
        global $DB;
        foreach ($this->handlers as $declared) {
            $declared = (array)$declared;
            $items = $this->check_handler($declared);
            $tochange = array_filter($items, function($item) {
                return $item['result'] === 'changed';
            });
            if (!$tochange) {
                foreach ($items as $item) {
                    $report->add_result($item);
                }
                continue;
            }
            $classname = self::classname((string)$declared['classname']);
            $handler = \core\message\inbound\manager::get_handler($classname);
            $record = \core\message\inbound\manager::record_from_handler($handler);
            foreach (self::FIELDS as $field => $canchange) {
                // check_handler() reports a field `changed` only where can_change_*() is true;
                // asking again here keeps the write rule next to the write.
                if (array_key_exists($field, $declared) && $handler->$canchange()) {
                    $record->$field = (int)$declared[$field];
                }
            }
            $DB->update_record(self::TABLE, $record);

            $after = [];
            foreach ($this->check_handler($declared) as $item) {
                $after[$item['item']] = $item;
            }
            foreach ($items as $item) {
                if ($item['result'] !== 'changed') {
                    $report->add_result($item);
                } else if (($after[$item['item']]['result'] ?? '') === 'ok') {
                    $report->add_result($item, 'changed');
                } else {
                    $report->add_result($after[$item['item']] ?? $item, 'fail', 'written, but the server still differs');
                }
            }
        }
    }

    /**
     * A classname as core stores it: with exactly one leading backslash.
     *
     * @param string $classname
     * @return string
     */
    public static function classname(string $classname): string {
        return '\\' . ltrim($classname, '\\');
    }

    /**
     * @param string $item
     * @param string $result
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @return array
     */
    protected static function result(string $item, string $result, $declared = null, $live = null,
            string $message = ''): array {
        return ['type' => self::TYPE, 'item' => $item, 'result' => $result, 'declared' => $declared,
            'live' => $live, 'message' => $message, 'secret' => false, 'blocking' => false];
    }
}
