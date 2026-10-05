<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\event\pathway_courses_changed;
use local_ltuse\pathway\catalogue;
use stdClass;

/**
 * Checks and applies role pathways in the plugin's own tables (spec 006, FR-007, R5).
 *
 * The declaration arrives as the payload's `role_pathways` array, rendered by
 * scripts/site_config.py from moodle/site/pathways.yaml
 * (specs/006-learning-pathways/contracts/declaration.md "Payload arrays"):
 *
 *   role_pathways [{key, name, description, sortorder, competencies: [name]}]
 *
 * `sortorder` is the role's position in the file, from 0; the table stores it 1-based
 * (data-model `local_ltuse_role_pathway`), so a role's stored sortorder is the payload's + 1.
 *
 * Identity: a row of `local_ltuse_role_pathway` by its `rolekey`, compared case-sensitively in
 * PHP. Its competencies are rows of `local_ltuse_role_pathway_comp`, each naming a
 * `local_ltuse_competency` row resolved by exact name in PHP among non-retired rows, as
 * set_course_competencies does. Both tables are the plugin's own, read and written with $DB.
 *
 * Lifecycle (subject `role pathway <key>`):
 *   absent                       inserted with its competencies; changed/missing 'created';
 *   name, description, sortorder, competencies or retired differs
 *                                set back, reported `changed`, the message naming which;
 *                                the competency rows are replaced as a set, in order;
 *   live but not declared        `extra`: set to retired = 1 by apply, never deleted, so its
 *                                cohort assignments still point at something. Its competency
 *                                rows are kept.
 * A retired row that is not declared is at rest and is not reported.
 *
 * A role competency that is not a live row and that this run's competency list does not
 * declare is `unknown` (`role pathway <key>: <name>`) and blocks the run: nothing is written.
 * One the competency list declares is applied first by the same run (the applier runs
 * competencies before role pathways), so it is a plain `changed`, not a block.
 *
 * When apply changes the courses on a role that existed before the run (its competency set,
 * a retirement, or a role declared again), it fires pathway_courses_changed for `role:<key>`
 * with the course ids that joined and left. A name, description or order change moves no
 * course and fires nothing; neither does creating a role, which no cohort can be assigned yet.
 *
 * When the installed local_ltuse is older than 2026100600, one `unknown` item, `pathway
 * tables`, says so. It blocks the pathway items, which are not checked or written, and not the
 * run (contracts/declaration.md "Output additions").
 *
 * An item names a role and competencies only. It never names a cohort, a course or a user, and
 * carries no count of any of them (constitution III). The event carries course ids only.
 */
class rolepathways {

    /** The role table. */
    const TABLE = 'local_ltuse_role_pathway';

    /** The role competency table. */
    const COMP_TABLE = 'local_ltuse_role_pathway_comp';

    /** The competency list (spec 004). */
    const COMPETENCY_TABLE = 'local_ltuse_competency';

    /** Item type and subject prefix. */
    const TYPE = 'role pathway';

    /** The subject of the item that says the plugin has not been upgraded. */
    const TABLES_SUBJECT = 'pathway tables';

    /** The tables, and the competency column, that 2026100600 adds. */
    const REQUIRED_TABLES = ['local_ltuse_role_pathway', 'local_ltuse_role_pathway_comp',
        'local_ltuse_course_pathway', 'local_ltuse_pathway_cohort'];

    /** A role key: `role:<key>` must fit the 100-character pathway key (R3). */
    const KEY_PATTERN = '/^[a-z][a-z0-9-]*$/';

    /** The longest role key. */
    const KEY_MAX_LENGTH = 95;

    /** The longest role name or competency name the char(255) columns hold. */
    const MAX_LENGTH = 255;

    /** @var array[] the declared roles */
    protected $declared;

    /** @var array<string, true> competency names this run's competency list declares */
    protected $declaredcompetencies;

    /** @var stdClass[]|null the role rows, keyed by id; read once per check or apply step */
    protected $live = null;

    /** @var bool|null whether the plugin has the pathway tables; read once */
    protected $tables = null;

    /**
     * @param array $roles the payload's `role_pathways` array
     * @param string[] $competencies the names in this run's `competencies` array, or [] when
     *     the payload declares none
     */
    public function __construct(array $roles, array $competencies = []) {
        $this->declared = array_values(array_map(function($role) {
            return (array)$role;
        }, $roles));
        $this->declaredcompetencies = [];
        foreach ($competencies as $name) {
            $this->declaredcompetencies[(string)$name] = true;
        }
    }

    /**
     * The subject of a role's item.
     *
     * @param string $key
     * @return string
     */
    public static function subject(string $key): string {
        return self::TYPE . " {$key}";
    }

    // --- checking (read only) ------------------------------------------------------------

    /**
     * One item per declared role, in declaration order, preceded by an `unknown` item for each
     * competency a role names that cannot be resolved. WRITES NOTHING.
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        if (!$this->tables_exist()) {
            return [self::tables_missing()];
        }
        $this->live = null;
        $items = [];
        foreach ($this->declared as $index => $role) {
            $items = array_merge($items, $this->check_role($role, $index));
        }
        return $items;
    }

    /**
     * One `extra` item per live (not retired) role the declaration does not name. WRITES
     * NOTHING. For drift's undeclared-items pass.
     *
     * @return array[] item results in inspector's shape
     */
    public function extras(): array {
        if (!$this->tables_exist()) {
            return [];
        }
        $this->live = null;
        $items = [];
        foreach ($this->undeclared_live() as $row) {
            $items[] = self::result(self::subject((string)$row->rolekey), 'extra', null, self::row_summary($row),
                'no longer declared in pathways.yaml; apply retires it, and keeps its cohort assignments');
        }
        return $items;
    }

    /**
     * Compare one declared role with its live rows. The role's own item comes last, after an
     * `unknown` item for each competency that cannot be resolved.
     *
     * @param array $role {key, name, description, sortorder, competencies}
     * @param int|null $index its position in the declaration, for the duplicate check
     * @return array[] item results
     */
    public function check_role(array $role, ?int $index = null): array {
        $key = is_string($role['key'] ?? null) ? $role['key'] : '';
        $subject = self::subject($key);

        $problem = self::invalid($role);
        if ($problem === null && $index !== null && $this->declared_twice($key, $index)) {
            $problem = 'declared more than once';
        }
        if ($problem !== null) {
            return [self::result($subject, 'unknown', self::summary($role), null, $problem, true)];
        }

        $items = [];
        $resolved = $this->resolve_competencies($role['competencies']);
        foreach ($resolved['unknown'] as $name) {
            $items[] = self::result("{$subject}: {$name}", 'unknown', $name, null,
                'not a competency on this site; run apply with the competency list first, or correct the name',
                true);
        }
        if ($items) {
            return $items;
        }

        $row = self::find_exact($this->live_rows(), $key);
        if ($row === null) {
            $items[] = self::result($subject, 'missing', self::summary($role), null, 'apply will create it');
            return $items;
        }

        $differences = self::differences($role, $row, $resolved['ids'], $this->live_competency_ids((int)$row->id),
            $resolved['pending']);
        if ($differences) {
            $message = 'differs: ' . implode(', ', $differences);
            if ($resolved['pending']) {
                $message .= '; its competencies ' . implode(', ', $resolved['pending'])
                    . ' are written first by this run\'s competency list';
            }
            $items[] = self::result($subject, 'changed', self::summary($role), self::row_summary($row), $message);
            return $items;
        }
        $items[] = self::result($subject, 'ok', self::summary($role), self::row_summary($row));
        return $items;
    }

    // --- applying ------------------------------------------------------------------------

    /**
     * Bring the role tables to the declaration: insert, set back, un-retire and replace each
     * role's competency rows, then retire every live role that is no longer declared. Never
     * deletes a role. Must run after competencies::apply(), so a competency this run created
     * resolves.
     *
     * @param report $report
     * @return void
     */
    public function apply(report $report): void {
        if (!$this->tables_exist()) {
            $report->add_result(self::tables_missing());
            return;
        }
        $this->live = null;
        foreach ($this->declared as $index => $role) {
            $this->apply_role($role, $index, $report);
        }
        $this->retire_undeclared($report);
    }

    /**
     * @param array $role
     * @param int $index
     * @param report $report
     * @return void
     */
    protected function apply_role(array $role, int $index, report $report): void {
        global $DB;
        $items = $this->check_role($role, $index);
        $item = end($items);
        if (count($items) !== 1 || ($item['result'] !== 'missing' && $item['result'] !== 'changed')) {
            foreach ($items as $one) {
                $report->add_result($one);
            }
            return;
        }

        $key = (string)$role['key'];
        $resolved = $this->resolve_competencies($role['competencies']);
        if ($resolved['unknown'] || $resolved['pending']) {
            // The competency list did not create what it declared; report, write nothing.
            $after = $this->check_role($role, $index);
            foreach ($after as $one) {
                $report->add_result($one, $one['result'] === 'ok' ? null : 'fail',
                    $one['result'] === 'ok' ? null : 'its competencies are not live rows yet; nothing written');
            }
            return;
        }

        $row = self::find_exact($this->live_rows(), $key);
        $existed = $row !== null;
        $before = $existed ? catalogue::courses(catalogue::role_key($key)) : [];

        $transaction = $DB->start_delegated_transaction();
        $record = self::save_data($role, $row, time());
        if ($row === null) {
            $roleid = (int)$DB->insert_record(self::TABLE, $record);
        } else {
            $roleid = (int)$row->id;
            $DB->update_record(self::TABLE, $record);
        }
        if ($resolved['ids'] !== $this->live_competency_ids($roleid)) {
            $DB->delete_records(self::COMP_TABLE, ['roleid' => $roleid]);
            foreach ($resolved['ids'] as $position => $competencyid) {
                $DB->insert_record(self::COMP_TABLE, (object)['roleid' => $roleid,
                    'competencyid' => $competencyid, 'sortorder' => $position + 1]);
            }
        }
        $transaction->allow_commit();
        $this->live = null;

        $after = $this->check_role($role, $index);
        $afteritem = end($after);
        if (count($after) !== 1 || $afteritem['result'] !== 'ok') {
            $report->add_result($afteritem, 'fail', 'written, but the server still differs');
        } else if ($item['result'] === 'missing') {
            $report->add('changed', 'missing', $item['item'], $item['declared'], null, 'created');
        } else {
            $report->add_result($item, 'changed');
        }

        // A new role is announced too ($before is []), so its courses' next publish has nothing
        // left to announce for it.
        self::announce($key, $before, catalogue::courses(catalogue::role_key($key)));
    }

    /**
     * Retire every live role the declaration does not name, keeping it, its competency rows
     * and its cohort assignments.
     *
     * @param report $report
     * @return void
     */
    protected function retire_undeclared(report $report): void {
        global $DB;
        foreach ($this->undeclared_live() as $row) {
            $key = (string)$row->rolekey;
            $subject = self::subject($key);
            $before = catalogue::courses(catalogue::role_key($key));
            $DB->update_record(self::TABLE, (object)['id' => $row->id, 'retired' => 1, 'timemodified' => time()]);
            $retired = $DB->get_field(self::TABLE, 'retired', ['id' => $row->id]);
            if ((int)$retired === 1) {
                $report->add('changed', 'extra', $subject, null, self::row_summary($row),
                    'no longer declared in pathways.yaml; retired, kept with its cohort assignments');
                self::announce($key, $before, []);
            } else {
                $report->add('fail', 'extra', $subject, null, self::row_summary($row),
                    'no longer declared in pathways.yaml; written, but it is still not retired');
            }
        }
        $this->live = null;
    }

    /**
     * Fire pathway_courses_changed for a role whose courses changed, after the write is
     * committed. Nothing is fired when no course joined or left.
     *
     * @param string $key the role key, without `role:`
     * @param int[] $before course ids on the pathway before the write
     * @param int[] $after course ids on it after
     * @return void
     */
    protected static function announce(string $key, array $before, array $after): void {
        [$added, $removed] = self::course_changes($before, $after);
        if (!$added && !$removed) {
            return;
        }
        self::record_announced(catalogue::role_key($key), $added, $removed);
        // No context: the event's init() sets the system context itself.
        pathway_courses_changed::create([
            'other' => [
                'pathwaykey' => catalogue::role_key($key),
                'added' => $added,
                'removed' => $removed,
            ],
        ])->trigger();
    }

    /**
     * The record to insert or update: the declared name, description and 1-based sortorder,
     * not retired.
     *
     * @param array $role the declared role
     * @param stdClass|null $row the live row, or null to insert
     * @param int $now
     * @return stdClass
     */
    public static function save_data(array $role, ?stdClass $row, int $now): stdClass {
        $record = new stdClass();
        if ($row !== null) {
            $record->id = (int)$row->id;
        }
        $record->rolekey = (string)$role['key'];
        $record->name = (string)$role['name'];
        $record->description = (string)($role['description'] ?? '');
        $record->sortorder = self::stored_sortorder($role);
        $record->retired = 0;
        $record->timemodified = $now;
        return $record;
    }

    // --- pure comparisons (no database) --------------------------------------------------

    /**
     * Why a declared role cannot be applied, or null when it can. site_config.py validate
     * refuses these first, along with the CBC wording of a name; this is the plugin's own guard.
     *
     * @param array $role
     * @return string|null
     */
    public static function invalid(array $role): ?string {
        $key = $role['key'] ?? null;
        $name = $role['name'] ?? null;
        $description = $role['description'] ?? '';
        $sortorder = $role['sortorder'] ?? null;
        $competencies = $role['competencies'] ?? null;
        if (!is_string($key) || !preg_match(self::KEY_PATTERN, $key)) {
            return 'key must match ' . self::KEY_PATTERN;
        }
        if (strlen($key) > self::KEY_MAX_LENGTH) {
            return 'key is longer than ' . self::KEY_MAX_LENGTH . ' characters';
        }
        if (!is_string($name) || trim($name) === '') {
            return 'a role pathway needs a name';
        }
        if (\core_text::strlen($name) > self::MAX_LENGTH) {
            return 'name is longer than ' . self::MAX_LENGTH . ' characters';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $name)) {
            return 'name holds a control character';
        }
        if ($description !== null && !is_string($description)) {
            return 'description must be text';
        }
        if (!is_int($sortorder) || $sortorder < 0) {
            return 'sortorder must be a whole number from 0';
        }
        if (!is_array($competencies) || !$competencies) {
            return 'a role pathway needs at least one competency';
        }
        $seen = [];
        foreach ($competencies as $competency) {
            if (!is_string($competency) || trim($competency) === '') {
                return 'each competency must be a name';
            }
            if (isset($seen[$competency])) {
                return "lists the competency '{$competency}' twice";
            }
            $seen[$competency] = true;
        }
        return null;
    }

    /**
     * The properties of a live role that differ from the declaration, in a fixed order.
     *
     * @param array $role the declared role
     * @param stdClass $row its live row
     * @param int[] $declaredids its declared competencies' ids, in order, the resolved ones
     * @param int[] $liveids its live competency rows' competency ids, in sortorder
     * @param string[] $pending declared competencies this run creates, not resolved yet
     * @return string[] of name, description, sortorder, competencies, retired
     */
    public static function differences(array $role, stdClass $row, array $declaredids, array $liveids,
            array $pending = []): array {
        $diff = [];
        if ((string)$role['name'] !== (string)$row->name) {
            $diff[] = 'name';
        }
        if ((string)($role['description'] ?? '') !== (string)($row->description ?? '')) {
            $diff[] = 'description';
        }
        if (self::stored_sortorder($role) !== (int)$row->sortorder) {
            $diff[] = 'sortorder';
        }
        if ($pending || $declaredids !== $liveids) {
            $diff[] = 'competencies';
        }
        if ((int)$row->retired !== 0) {
            $diff[] = 'retired';
        }
        return $diff;
    }

    /**
     * Note in each course's local_ltuse_course_pathway.pathwaykeys (the set set_course_pathway
     * last announced) that this role key has now been announced as joined or left, so the
     * course's next publish does not announce it again. Only this key changes: any course-level
     * difference not yet announced is left for that publish.
     *
     * @param string $rolekey the full key, role:<key>
     * @param int[] $added
     * @param int[] $removed
     * @return void
     */
    protected static function record_announced(string $rolekey, array $added, array $removed): void {
        global $DB;
        $ids = array_merge($added, $removed);
        $rows = $DB->get_records_list('local_ltuse_course_pathway', 'courseid', $ids, '', 'id, courseid, pathwaykeys');
        foreach ($rows as $row) {
            $keys = json_decode((string)$row->pathwaykeys, true);
            $keys = is_array($keys) ? array_values(array_map('strval', $keys)) : [];
            $without = array_values(array_diff($keys, [$rolekey]));
            $new = in_array((int)$row->courseid, $added, true) ? array_merge($without, [$rolekey]) : $without;
            if ($new !== $keys) {
                $DB->update_record('local_ltuse_course_pathway', (object)['id' => $row->id,
                    'pathwaykeys' => json_encode($new), 'timemodified' => time()]);
            }
        }
    }

    /**
     * The course ids that joined and left a pathway, each list in ascending order, as PHP ints.
     *
     * @param int[] $before
     * @param int[] $after
     * @return array{0: int[], 1: int[]} [added, removed]
     */
    public static function course_changes(array $before, array $after): array {
        $before = array_map('intval', $before);
        $after = array_map('intval', $after);
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));
        sort($added);
        sort($removed);
        return [$added, $removed];
    }

    /**
     * The stored, 1-based sortorder of a declared role (the payload's is 0-based).
     *
     * @param array $role
     * @return int
     */
    public static function stored_sortorder(array $role): int {
        return (int)$role['sortorder'] + 1;
    }

    /**
     * The role row with exactly this key, or null.
     *
     * @param stdClass[] $rows
     * @param string $key
     * @return stdClass|null
     */
    public static function find_exact(array $rows, string $key): ?stdClass {
        foreach ($rows as $row) {
            if ((string)$row->rolekey === $key) {
                return $row;
            }
        }
        return null;
    }

    /**
     * A declared role as one line: its name, position and competencies.
     *
     * @param array $role
     * @return string
     */
    public static function summary(array $role): string {
        $competencies = is_array($role['competencies'] ?? null)
            ? implode('; ', array_map('strval', array_filter($role['competencies'], 'is_scalar'))) : '(none)';
        return 'name ' . self::display($role['name'] ?? null)
            . ', sortorder ' . (is_int($role['sortorder'] ?? null) ? self::stored_sortorder($role) : '(none)')
            . ', competencies ' . $competencies;
    }

    /**
     * A live role as one line.
     *
     * @param stdClass $row
     * @return string
     */
    public static function row_summary(stdClass $row): string {
        $text = "name {$row->name}, sortorder " . (int)$row->sortorder;
        if (isset($row->competencies)) {
            $text .= ', competencies ' . ($row->competencies === '' ? '(none)' : $row->competencies);
        }
        return (int)$row->retired !== 0 ? $text . ', retired' : $text;
    }

    // --- reads ---------------------------------------------------------------------------

    /**
     * Resolve declared competency names against non-retired competency rows, by exact name in
     * PHP, because a database collation may fold case.
     *
     * @param string[] $names
     * @return array{ids: int[], pending: string[], unknown: string[]} ids in declared order,
     *     for the names that resolved; pending, the names this run's competency list will
     *     write; unknown, the rest
     */
    protected function resolve_competencies(array $names): array {
        global $DB;
        $byname = [];
        foreach ($DB->get_records(self::COMPETENCY_TABLE, ['retired' => 0], 'id ASC', 'id, name') as $row) {
            $byname[(string)$row->name] = (int)$row->id;
        }
        $ids = [];
        $pending = [];
        $unknown = [];
        foreach ($names as $name) {
            $name = (string)$name;
            if (isset($byname[$name])) {
                $ids[] = $byname[$name];
            } else if (isset($this->declaredcompetencies[$name])) {
                $pending[] = $name;
            } else {
                $unknown[] = $name;
            }
        }
        return ['ids' => $ids, 'pending' => $pending, 'unknown' => $unknown];
    }

    /**
     * A role's competency ids, in sortorder.
     *
     * @param int $roleid
     * @return int[]
     */
    protected function live_competency_ids(int $roleid): array {
        global $DB;
        $rows = $DB->get_records(self::COMP_TABLE, ['roleid' => $roleid], 'sortorder ASC, id ASC', 'id, competencyid');
        return array_values(array_map(function($row) {
            return (int)$row->competencyid;
        }, $rows));
    }

    /**
     * Is the declared key at another position in the declaration too?
     *
     * @param string $key
     * @param int $index
     * @return bool
     */
    protected function declared_twice(string $key, int $index): bool {
        foreach ($this->declared as $other => $role) {
            if ($other !== $index && ($role['key'] ?? null) === $key) {
                return true;
            }
        }
        return false;
    }

    /**
     * Every role row, retired ones included, keyed by id, each with its competency names in
     * order as `competencies`, for the summary.
     *
     * @return stdClass[]
     */
    protected function live_rows(): array {
        global $DB;
        if ($this->live === null) {
            $this->live = $DB->get_records(self::TABLE, null, 'sortorder ASC, id ASC',
                'id, rolekey, name, description, sortorder, retired');
            if ($this->live) {
                [$insql, $params] = $DB->get_in_or_equal(array_keys($this->live), SQL_PARAMS_NAMED, 'ltrp');
                $names = $DB->get_recordset_sql(
                    "SELECT rpc.id, rpc.roleid, comp.name
                       FROM {" . self::COMP_TABLE . "} rpc
                       JOIN {" . self::COMPETENCY_TABLE . "} comp ON comp.id = rpc.competencyid
                      WHERE rpc.roleid {$insql}
                   ORDER BY rpc.roleid, rpc.sortorder, rpc.id", $params);
                $lists = [];
                foreach ($names as $name) {
                    $lists[(int)$name->roleid][] = (string)$name->name;
                }
                $names->close();
                foreach ($this->live as $id => $row) {
                    $row->competencies = implode('; ', $lists[(int)$id] ?? []);
                }
            }
        }
        return $this->live;
    }

    /**
     * Live (not retired) roles whose key the declaration does not hold exactly.
     *
     * @return stdClass[]
     */
    protected function undeclared_live(): array {
        $keys = [];
        foreach ($this->declared as $role) {
            $keys[(string)($role['key'] ?? '')] = true;
        }
        return array_values(array_filter($this->live_rows(), function($row) use ($keys) {
            return (int)$row->retired === 0 && !isset($keys[(string)$row->rolekey]);
        }));
    }

    /**
     * @return bool whether the installed local_ltuse has the pathway tables and the competency
     *     slug column (2026100600 on)
     */
    public function tables_exist(): bool {
        global $DB;
        if ($this->tables === null) {
            $manager = $DB->get_manager();
            $ok = $manager->table_exists(self::COMPETENCY_TABLE)
                && $manager->field_exists(self::COMPETENCY_TABLE, 'slug');
            foreach (self::REQUIRED_TABLES as $table) {
                $ok = $ok && $manager->table_exists($table);
            }
            $this->tables = $ok;
        }
        return $this->tables;
    }

    // --- results -------------------------------------------------------------------------

    /**
     * The item for a plugin that has not been upgraded yet. It blocks the pathway items, which
     * are neither checked nor written, and not the run.
     *
     * @return array
     */
    public static function tables_missing(): array {
        return self::result(self::TABLES_SUBJECT, 'unknown', null, null,
            'the installed local_ltuse is older than 2026100600; run the plugin upgrade first');
    }

    /**
     * Build one item result, in inspector's shape.
     *
     * @param string $item the subject
     * @param string $result ok, changed, missing, extra or unknown
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $item, string $result, $declared = null, $live = null,
            string $message = '', bool $blocking = false): array {
        return [
            'type' => 'rolepathway',
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
