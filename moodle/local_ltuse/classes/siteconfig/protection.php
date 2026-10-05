<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\protection\levels;
use local_ltuse\protection\service;

/**
 * Stores protection.yaml (spec 016, contracts/declaration.md "Applier").
 *
 * The declaration arrives as the payload's `protection`, already checked by
 * scripts/site_config.py:
 *
 *   protection {levels, withhold {email, firstname, pseudonym}, neutral_surname,
 *               reconcile_minutes}
 *
 * It is kept in the plugin's config, `local_ltuse/protection`, as JSON, where the service reads
 * it. Apply never reads or writes any user's protection: that is Moodle data
 * (constitution III). A change of the withhold lists reaches protected accounts at
 * the next reconcile run, not here.
 *
 * Items:
 *   protection config   missing (never applied), changed or ok; blocking when the plugin's
 *                       tables are missing (run the upgrade first)
 *   protection accounts drift only, a count only: accounts that differ from their protected
 *                       state (a reconcile backlog). Never a name or an id (FR-013).
 */
class protection {

    /** Item type. */
    const TYPE = 'protection';

    /** Subjects. */
    const SUBJECT = 'protection config';
    const ACCOUNTS = 'protection accounts';

    /** @var array the payload's protection */
    protected $declared;

    /**
     * @param array $declared
     */
    public function __construct(array $declared) {
        $this->declared = $declared;
    }

    /**
     * The declared config in its stored form, with keys sorted so a comparison is stable.
     *
     * @return array
     */
    public function storable(): array {
        $out = [
            'levels' => array_values(array_map('strval', (array)($this->declared['levels'] ?? []))),
            'withhold' => [],
            'neutral_surname' => (string)($this->declared['neutral_surname'] ?? ''),
            'reconcile_minutes' => (int)($this->declared['reconcile_minutes'] ?? 60),
        ];
        foreach ((array)($this->declared['withhold'] ?? []) as $level => $fields) {
            $out['withhold'][(string)$level] = array_values(array_map('strval', (array)$fields));
        }
        ksort($out['withhold']);
        ksort($out);
        return $out;
    }

    /**
     * The config item. WRITES NOTHING.
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        if (!service::table_exists()) {
            return [self::result(self::SUBJECT, 'unknown', null, null,
                'the installed local_ltuse has no ' . service::TABLE . ' table; run the plugin upgrade first', true)];
        }
        $declared = $this->storable();
        $summary = self::summary($declared);
        $stored = service::config();
        if ($stored === null) {
            return [self::result(self::SUBJECT, 'missing', $summary, null, 'never applied')];
        }
        ksort($stored);
        $differs = [];
        foreach ($declared as $key => $value) {
            if (($stored[$key] ?? null) != $value) {
                $differs[] = $key;
            }
        }
        if ($differs) {
            return [self::result(self::SUBJECT, 'changed', $summary, self::summary($stored),
                'differs: ' . implode(', ', $differs))];
        }
        return [self::result(self::SUBJECT, 'ok', $summary, $summary)];
    }

    /**
     * Store the config when it differs.
     *
     * @param report $report
     */
    public function apply(report $report): void {
        $item = $this->check()[0];
        if ($item['result'] === 'ok' || !empty($item['blocking'])) {
            $report->add_result($item);
            return;
        }
        set_config(service::CONFIG, json_encode($this->storable()), 'local_ltuse');
        $after = $this->check()[0];
        if ($after['result'] !== 'ok') {
            $report->add_result($after, 'fail', 'written, but the server still differs');
        } else {
            $report->add_result($item, 'changed', $item['result'] === 'missing' ? 'created' : null);
        }
    }

    /**
     * Drift's count: accounts waiting for the reconcile run. A count only. WRITES NOTHING.
     * Usernames are not counted: the service gives a neutral one whenever it applies First
     * name only or Pseudonym (Doug, 2026-10-05 (scope review), change 15).
     *
     * @return array[] item results; empty when there is nothing to report
     */
    public static function extras(): array {
        global $DB;
        if (!service::table_exists() || service::config() === null) {
            return [];
        }
        $backlog = 0;
        foreach ($DB->get_records_select(service::TABLE, 'effectivelevel <> :none', ['none' => levels::NONE]) as $row) {
            if (service::drifted((int)$row->userid, $row)) {
                $backlog++;
            }
        }
        if (!$backlog) {
            return [];
        }
        // A warning ([skip]): apply cannot fix it; the reconcile task does.
        return [['warning' => true] + self::result(self::ACCOUNTS, 'extra', null, "{$backlog} to repair",
            "{$backlog} protected accounts differ from their protected state (the next reconcile run repairs them)")];
    }

    /**
     * @param array $config
     * @return string
     */
    protected static function summary(array $config): string {
        $counts = [];
        foreach ((array)($config['withhold'] ?? []) as $level => $fields) {
            $counts[] = "{$level} " . count((array)$fields);
        }
        return 'withholds ' . implode(', ', $counts);
    }

    /**
     * @param string $item
     * @param string $result
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $item, string $result, $declared = null, $live = null,
            string $message = '', bool $blocking = false): array {
        return ['type' => self::TYPE, 'item' => $item, 'result' => $result, 'declared' => $declared,
            'live' => $live, 'message' => $message, 'secret' => false, 'blocking' => $blocking];
    }
}
