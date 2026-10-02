<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Checks and applies the competency list in the plugin's own table (spec 004, R15).
 *
 * The declaration arrives as the payload's `competencies` array, rendered by
 * scripts/site_config.py from the repo-root competencies.yaml, with the `Meta` category left
 * out (specs/004-progress-reporting/data-model.md "Competency list"):
 *
 *   competencies [{name, category, sortorder}]
 *
 * Identity: a row of `local_ltuse_competency` by its `name`, compared case-sensitively in PHP,
 * because a database collation may fold case. A renamed competency is therefore a new row and
 * the old one is retired. The table is the plugin's own (db/install.xml), so it is read and
 * written with $DB directly; there is no core API for it.
 *
 * Lifecycle (subject `competency <name>`):
 *   absent                        inserted; reported changed/missing 'created' (report::KINDS
 *                                 has no `created`, as profilefields.php reports a creation);
 *   category or sortorder differs set back, reported `changed`;
 *   declared but retired          un-retired, reported `changed`;
 *   live but not declared         `extra`: kept, and set to retired = 1 by apply, so it leaves
 *                                 the per-competency report. Its course map rows are kept.
 * A row is never deleted. A retired row that is not declared is at rest and is not reported.
 *
 * NOTHING HERE READS local_ltuse_course_comp, and no item carries a count, a course or a
 * user: an item names a competency, its category and its sortorder only (constitution III).
 */
class competencies {

    /** The plugin table that holds the list. */
    const TABLE = 'local_ltuse_competency';

    /** Item type and subject prefix (contracts/declaration.md "Output additions"). */
    const TYPE = 'competency';

    /** The longest name or category the table's char(255) columns hold. */
    const MAX_LENGTH = 255;

    /** @var array[] the declared competencies */
    protected $declared;

    /** @var stdClass[]|null the live rows, keyed by id; read once per check or apply step */
    protected $live = null;

    /**
     * @param array $competencies the payload's `competencies` array
     */
    public function __construct(array $competencies) {
        $this->declared = array_values(array_map(function($competency) {
            return (array)$competency;
        }, $competencies));
    }

    /**
     * The subject of a competency's item.
     *
     * @param string $name
     * @return string
     */
    public static function subject(string $name): string {
        return self::TYPE . " {$name}";
    }

    // --- checking (read only) ------------------------------------------------------------

    /**
     * One item per declared competency, in declaration order. WRITES NOTHING.
     *
     * When the plugin table does not exist (local_ltuse not yet upgraded), one blocking item
     * says so instead.
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        if (!$this->table_exists()) {
            return [self::table_missing()];
        }
        $this->live = null;
        $items = [];
        foreach ($this->declared as $index => $competency) {
            $items[] = $this->check_competency($competency, $index);
        }
        return $items;
    }

    /**
     * One `extra` item per live (not retired) row that the declaration does not name, in
     * sortorder. WRITES NOTHING. For drift's undeclared-items pass.
     *
     * @return array[] item results in inspector's shape
     */
    public function extras(): array {
        if (!$this->table_exists()) {
            return [];
        }
        $this->live = null;
        $items = [];
        foreach ($this->undeclared_live() as $row) {
            $items[] = self::result(self::subject((string)$row->name), 'extra', null, self::row_summary($row),
                'no longer declared in competencies.yaml; apply retires it, and keeps it and its course links');
        }
        return $items;
    }

    /**
     * Compare one declared competency with the live row.
     *
     * @param array $competency {name, category, sortorder}
     * @param int|null $index its position in the declaration, for the duplicate check
     * @return array item result
     */
    public function check_competency(array $competency, ?int $index = null): array {
        $name = (string)($competency['name'] ?? '');
        $subject = self::subject($name);

        $problem = self::invalid($competency);
        if ($problem === null && $index !== null && $this->declared_twice($name, $index)) {
            $problem = 'declared more than once';
        }
        if ($problem !== null) {
            return self::result($subject, 'unknown', self::summary($competency), null, $problem, true);
        }

        $rows = $this->live_rows();
        $row = self::find_exact($rows, $name);
        if ($row === null) {
            $folded = self::find_folded($rows, $name);
            if ($folded !== null) {
                return self::result($subject, 'ambiguous', self::summary($competency), self::row_summary($folded),
                    "a competency named '{$folded->name}' differs only in case, and the table's unique name"
                    . ' index may refuse a second; rename it by hand', true);
            }
            return self::result($subject, 'missing', self::summary($competency), null, 'apply will create it');
        }

        $differences = self::differences($competency, $row);
        if ($differences) {
            return self::result($subject, 'changed', self::summary($competency), self::row_summary($row),
                'differs: ' . implode(', ', $differences));
        }
        return self::result($subject, 'ok', self::summary($competency), self::row_summary($row));
    }

    // --- applying ------------------------------------------------------------------------

    /**
     * Bring the table to the declaration: insert, set back, un-retire, then retire every live
     * row that is no longer declared. Never deletes a row.
     *
     * The caller runs the preflight first: apply writes nothing while anything blocks. A
     * blocking item that reaches here is still reported and never written.
     *
     * @param report $report
     * @return void
     */
    public function apply(report $report): void {
        if (!$this->table_exists()) {
            $report->add_result(self::table_missing());
            return;
        }
        $this->live = null;
        foreach ($this->declared as $index => $competency) {
            $this->apply_competency($competency, $index, $report);
        }
        $this->retire_undeclared($report);
    }

    /**
     * @param array $competency
     * @param int $index
     * @param report $report
     * @return void
     */
    protected function apply_competency(array $competency, int $index, report $report): void {
        global $DB;
        $item = $this->check_competency($competency, $index);
        if ($item['result'] !== 'missing' && $item['result'] !== 'changed') {
            $report->add_result($item);
            return;
        }

        $name = (string)$competency['name'];
        $row = self::find_exact($this->live_rows(), $name);
        $record = self::save_data($competency, $row, time());
        if ($row === null) {
            $DB->insert_record(self::TABLE, $record);
        } else {
            $DB->update_record(self::TABLE, $record);
        }
        $this->live = null;

        $after = $this->check_competency($competency, $index);
        if ($after['result'] !== 'ok') {
            $report->add_result($after, 'fail', 'written, but the server still differs');
        } else if ($item['result'] === 'missing') {
            $report->add('changed', 'missing', $item['item'], $item['declared'], null, 'created');
        } else {
            $report->add_result($item, 'changed');
        }
    }

    /**
     * Retire every live row the declaration does not name, keeping it and its map rows.
     *
     * @param report $report
     * @return void
     */
    protected function retire_undeclared(report $report): void {
        global $DB;
        foreach ($this->undeclared_live() as $row) {
            $subject = self::subject((string)$row->name);
            $DB->update_record(self::TABLE, (object)['id' => $row->id, 'retired' => 1, 'timemodified' => time()]);
            $retired = $DB->get_field(self::TABLE, 'retired', ['id' => $row->id]);
            if ((int)$retired === 1) {
                $report->add('changed', 'extra', $subject, null, self::row_summary($row),
                    'no longer declared in competencies.yaml; retired, kept with its course links');
            } else {
                $report->add('fail', 'extra', $subject, null, self::row_summary($row),
                    'no longer declared in competencies.yaml; written, but it is still not retired');
            }
        }
        $this->live = null;
    }

    /**
     * The record to insert or update: the declared category and sortorder, not retired.
     *
     * @param array $competency the declared competency
     * @param stdClass|null $row the live row, or null to insert
     * @param int $now
     * @return stdClass
     */
    public static function save_data(array $competency, ?stdClass $row, int $now): stdClass {
        $record = new stdClass();
        if ($row !== null) {
            $record->id = (int)$row->id;
        }
        $record->name = (string)$competency['name'];
        $record->category = (string)$competency['category'];
        $record->sortorder = (int)$competency['sortorder'];
        $record->retired = 0;
        $record->timemodified = $now;
        return $record;
    }

    // --- pure comparisons (no database) --------------------------------------------------

    /**
     * Why a declared competency cannot be applied, or null when it can. site_config.py
     * validate refuses these first; this is the plugin's own guard.
     *
     * @param array $competency
     * @return string|null
     */
    public static function invalid(array $competency): ?string {
        $name = $competency['name'] ?? null;
        $category = $competency['category'] ?? null;
        $sortorder = $competency['sortorder'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            return 'a competency needs a name';
        }
        if (\core_text::strlen($name) > self::MAX_LENGTH) {
            return 'name is longer than ' . self::MAX_LENGTH . ' characters';
        }
        if (preg_match('/[\x00-\x1F\x7F\[\]]/', $name)) {
            return 'name holds a control character, [ or ]';
        }
        if (!is_string($category) || trim($category) === '') {
            return 'a competency needs a category';
        }
        if (\core_text::strlen($category) > self::MAX_LENGTH) {
            return 'category is longer than ' . self::MAX_LENGTH . ' characters';
        }
        if (!is_int($sortorder) && !(is_string($sortorder) && ctype_digit($sortorder))) {
            return 'sortorder must be a positive whole number';
        }
        if ((int)$sortorder < 1) {
            return 'sortorder must be a positive whole number';
        }
        return null;
    }

    /**
     * The properties of a live row that differ from the declaration, in a fixed order.
     *
     * @param array $competency
     * @param stdClass $row
     * @return string[] of category, sortorder, retired
     */
    public static function differences(array $competency, stdClass $row): array {
        $diff = [];
        if ((string)$competency['category'] !== (string)$row->category) {
            $diff[] = 'category';
        }
        if ((int)$competency['sortorder'] !== (int)$row->sortorder) {
            $diff[] = 'sortorder';
        }
        if ((int)$row->retired !== 0) {
            $diff[] = 'retired';
        }
        return $diff;
    }

    /**
     * The row with exactly this name, or null.
     *
     * @param stdClass[] $rows
     * @param string $name
     * @return stdClass|null
     */
    public static function find_exact(array $rows, string $name): ?stdClass {
        foreach ($rows as $row) {
            if ((string)$row->name === $name) {
                return $row;
            }
        }
        return null;
    }

    /**
     * A row whose name equals this one only when case is folded, or null.
     *
     * @param stdClass[] $rows
     * @param string $name
     * @return stdClass|null
     */
    public static function find_folded(array $rows, string $name): ?stdClass {
        $folded = \core_text::strtolower($name);
        foreach ($rows as $row) {
            if ((string)$row->name !== $name && \core_text::strtolower((string)$row->name) === $folded) {
                return $row;
            }
        }
        return null;
    }

    /**
     * A declared competency as one line.
     *
     * @param array $competency
     * @return string
     */
    public static function summary(array $competency): string {
        return 'category ' . self::display($competency['category'] ?? null)
            . ', sortorder ' . self::display($competency['sortorder'] ?? null);
    }

    /**
     * A live row as one line.
     *
     * @param stdClass $row
     * @return string
     */
    public static function row_summary(stdClass $row): string {
        $text = "category {$row->category}, sortorder " . (int)$row->sortorder;
        return (int)$row->retired !== 0 ? $text . ', retired' : $text;
    }

    // --- reads ---------------------------------------------------------------------------

    /**
     * Is the declared name at an earlier position in the declaration too?
     *
     * @param string $name
     * @param int $index
     * @return bool
     */
    protected function declared_twice(string $name, int $index): bool {
        foreach ($this->declared as $other => $competency) {
            if ($other !== $index && (string)($competency['name'] ?? '') === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Every row of the list, retired ones included, keyed by id. 42 rows today.
     *
     * @return stdClass[]
     */
    protected function live_rows(): array {
        global $DB;
        if ($this->live === null) {
            $this->live = $DB->get_records(self::TABLE, null, 'sortorder ASC, id ASC',
                'id, name, category, sortorder, retired');
        }
        return $this->live;
    }

    /**
     * Live (not retired) rows whose name the declaration does not hold exactly.
     *
     * @return stdClass[]
     */
    protected function undeclared_live(): array {
        $names = [];
        foreach ($this->declared as $competency) {
            $names[(string)($competency['name'] ?? '')] = true;
        }
        return array_values(array_filter($this->live_rows(), function($row) use ($names) {
            return (int)$row->retired === 0 && !isset($names[(string)$row->name]);
        }));
    }

    /**
     * @return bool whether the plugin's table exists (it does once local_ltuse is upgraded)
     */
    protected function table_exists(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(self::TABLE);
    }

    // --- results -------------------------------------------------------------------------

    /**
     * The blocking item for a plugin that has not been upgraded yet.
     *
     * @return array
     */
    protected static function table_missing(): array {
        return self::result(self::TYPE . ' list', 'unknown', null, null,
            'the installed local_ltuse has no ' . self::TABLE . ' table; run the plugin upgrade first', true);
    }

    /**
     * Build one item result, in inspector's shape.
     *
     * @param string $item the subject
     * @param string $result ok, changed, missing, extra, unknown or ambiguous
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $item, string $result, $declared = null, $live = null,
            string $message = '', bool $blocking = false): array {
        return [
            'type' => self::TYPE,
            'item' => $item,
            'result' => $result,
            'declared' => $declared,
            'live' => $live,
            'message' => $message,
            'secret' => false,
            'blocking' => $blocking,
        ];
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected static function display($value): string {
        if ($value === null) {
            return '(none)';
        }
        return is_scalar($value) ? (string)$value : json_encode($value);
    }
}
