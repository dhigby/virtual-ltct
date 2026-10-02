<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use core_reportbuilder\datasource;
use core_reportbuilder\local\audiences\base as audience_base;
use core_reportbuilder\local\helpers\aggregation;
use core_reportbuilder\local\helpers\audience as audience_helper;
use core_reportbuilder\local\helpers\report as rbreport;
use core_reportbuilder\local\helpers\schedule as schedule_helper;
use core_reportbuilder\local\models\column as column_model;
use core_reportbuilder\local\models\filter as filter_model;
use core_reportbuilder\local\models\report as report_model;
use core_reportbuilder\local\models\schedule as schedule_model;
use core_reportbuilder\manager;
use DateTimeImmutable;
use DateTimeZone;
use stdClass;

/**
 * Checks and applies the declared custom reports: their columns, sorting, conditions, filters,
 * audiences and schedule (spec 004, research R7, R9, R10, R12, R15; FR-005, FR-007, FR-013).
 *
 * The payload's `reports` array arrives already expanded by scripts/site_config.py
 * (specs/004-progress-reporting/data-model.md "Rendered payload additions" and "Report"):
 *
 *   reports [{area, name, source, uniquerows,
 *             columns    [{column, heading?, aggregation?}],
 *             sorting?   [{column, direction: asc|desc}],
 *             conditions [{condition, values: {operator, value, ...}}],
 *             filters    [<entity:name>],
 *             audiences  [{type: cohortmember, cohort: <idnumber>} | {type: systemrole, role: <shortname>}],
 *             schedule?  {name, recurrence, format, userviewas, start,
 *                         configdata: {subject, message: {text, format}, reportempty}}}]
 *
 * `{org}` is already replaced. A condition's `operator` is a word (`equal`, `not_equal`,
 * `contains`, ...) or core's integer; `value` is stored as given, except that `role:name`
 * names a role by shortname and is stored as that role's id, because the role filter's options
 * are keyed by id (R10). An absent `sorting` means no column is sorted.
 *
 * IDENTITY. A report is found by `component = local_ltuse` and `area`, never by id or name
 * (R9). Nothing makes that pair unique and the UI's Duplicate copies it, so more than one match
 * is `ambiguous` and that report is left alone. A `local_ltuse` report nobody declares is
 * `extra` and kept (extra()). An audience is matched by its type and changed in place with
 * update_configdata(), because schedules hold audience ids. A schedule has no identity of its
 * own: the declared one is the report's first `message` schedule by id. Any other schedule on
 * a declared report is disabled, never deleted; a disabled one is ignored.
 *
 * WHAT BLOCKS. An `entity:name` the datasource does not offer, or an aggregation the column
 * does not allow, is `unknown` and blocks the run, as an unknown setting does in spec 001;
 * except an identifier this run can still create (a `course:customfield_` or
 * `user:profilefield_` one, whose field is applied first) and anything on a `local_ltuse`
 * datasource (whose plugin upgrade comes first), which block only their report. A cohort or
 * role an audience names that is not there is `missing` and blocks only its report
 * (report::BLOCKS_REPORT). So does a condition whose filter produces no SQL: select filters
 * drop a value that is not among their options without a word (MDL-84213), and the report
 * would then show every participant, so each declared condition is built on the server and
 * `get_sql_filter()` must return SQL (R10, the fail-open guard). Core caches a select filter's
 * options for the rest of the request, so a report for an organisation whose `ltct_org` option
 * this same run added fails closed, and passes on the next run.
 *
 * APIs, confirmed on MOODLE_502_STABLE `public/reportbuilder/classes/` (constitution XI):
 * local/helpers/report.php create_report($data, false), add/delete/reorder_report_column,
 * toggle_report_column_sorting, reorder_report_column_sorting, add/delete/reorder_report_condition
 * and _filter; local/report/base.php set_condition_values(), get_column(), get_condition(),
 * get_filters(); local/helpers/aggregation.php get_column_aggregations(); local/audiences/base.php
 * create(), update_configdata(); local/helpers/audience.php get_base_records(),
 * delete_report_audience(); local/schedules/base.php create() through
 * reportbuilder/schedule/message.php (not the deprecated helpers\schedule::create_schedule(),
 * MDL-86066); local/helpers/schedule.php update_schedule(), toggle_schedule(). The persistents
 * are read directly: report, column, filter and schedule. A datasource is always built fresh
 * here, never through manager::get_report_from_id(), whose per-request cache would outlive a
 * change of source. The raw reads are `role` by shortname or id and `cohort` by idnumber or id.
 *
 * LEARNER DATA IS NEVER TOUCHED. No report is ever run: nothing here builds a report table,
 * counts rows or reads a report's data, and no item names a user, prints a row or a count
 * (constitution III). get_sql_filter() only builds an SQL fragment. A cohort is reported by
 * idnumber only, never its members. Subjects are `report <area>` and
 * `report <area>: <detail>`.
 */
class reports {

    /** Item type in results. */
    const TYPE = 'report';

    /** The component every declared report carries. */
    const COMPONENT = 'local_ltuse';

    /** Declared operator words to each filter class's constants. */
    const OPERATORS = [
        'select' => ['any' => 0, 'equal' => 1, 'not_equal' => 2],
        'text' => ['any' => 0, 'contains' => 1, 'does_not_contain' => 2, 'equal' => 3, 'not_equal' => 4,
            'starts_with' => 5, 'ends_with' => 6, 'empty' => 7, 'not_empty' => 8],
    ];

    /** Declared audience types to core's audience classes. */
    const AUDIENCE_CLASSES = [
        'cohortmember' => 'core_cohort\reportbuilder\audience\cohortmember',
        'systemrole' => 'core_reportbuilder\reportbuilder\audience\systemrole',
    ];

    /** The configdata key each audience class keeps its ids in. */
    const AUDIENCE_KEYS = [
        'cohortmember' => 'cohorts',
        'systemrole' => 'roles',
    ];

    /** The schedule type every declared schedule is. */
    const SCHEDULE_CLASS = 'core_reportbuilder\reportbuilder\schedule\message';

    /** Recurrence values, for display (models/schedule.php). */
    const RECURRENCES = [0 => 'none', 1 => 'daily', 2 => 'weekdays', 3 => 'weekly', 4 => 'monthly',
        5 => 'annually', 6 => 'hourly'];

    /** The condition whose value is a role, declared by shortname and stored by id (R10). */
    const ROLE_CONDITION = 'role:name';

    /** The parts of a report, in the order a difference names them. */
    const PARTS = ['name', 'source', 'uniquerows', 'columns', 'sorting', 'conditions', 'filters',
        'audiences', 'schedule'];

    /** @var array[] the declared reports */
    protected $reports;

    /**
     * @param array $reports the payload's `reports` array
     */
    public function __construct(array $reports) {
        $this->reports = array_values(array_map(function($entry) {
            return self::as_array($entry);
        }, $reports));
    }

    /**
     * The report subject for an area, or for one detail of it.
     *
     * @param string $area
     * @param string $detail e.g. `user:fullname` or `audience cohort ltct:org:x:managers`
     * @return string `report <area>` or `report <area>: <detail>`
     */
    public static function subject(string $area, string $detail = ''): string {
        return 'report ' . $area . ($detail !== '' ? ': ' . $detail : '');
    }

    /**
     * The declared areas, as a set.
     *
     * @return array<string, true>
     */
    public function declared_areas(): array {
        $areas = [];
        foreach ($this->reports as $decl) {
            $areas[(string)($decl['area'] ?? '')] = true;
        }
        return $areas;
    }

    // --- checking (read only) ------------------------------------------------------------

    /**
     * Every declared report's item results, in apply order. WRITES NOTHING, and runs nothing.
     * Undeclared reports are not here: see extra().
     *
     * @return array[] item results in inspector's shape
     */
    public function check(): array {
        $items = [];
        foreach ($this->reports as $decl) {
            $items = array_merge($items, $this->check_report($decl));
        }
        return $items;
    }

    /**
     * One report's item results: the report itself first (ok, missing, changed or ambiguous),
     * then one item per problem (unknown identifiers, missing audience cohorts or roles, a
     * condition that produces no SQL, an unusable schedule). WRITES NOTHING.
     *
     * @param array $decl one entry of the payload's `reports`
     * @return array[]
     */
    public function check_report(array $decl): array {
        $area = (string)($decl['area'] ?? '');
        $subject = self::subject($area);
        $source = ltrim((string)($decl['source'] ?? ''), '\\');
        if ($area === '' || $source === '' || trim((string)($decl['name'] ?? '')) === '') {
            return [self::result($area, $subject, inspector::RESULT_UNKNOWN, null, null,
                'a report needs an area, a name and a source; re-render the payload', report::BLOCKS_RUN)];
        }

        $instance = self::datasource_instance($source, null, $decl, $error);
        if ($instance === null) {
            return [self::result($area, self::subject($area, 'source'), inspector::RESULT_UNKNOWN, $source, null,
                $error, self::unknown_scope($source))];
        }

        $problems = [];
        $resolved = $this->resolve($decl, $instance, $source, $problems);

        $records = self::find($area);
        if (count($records) > 1) {
            $ids = array_map(function($record) {
                return $record->get('id');
            }, $records);
            array_unshift($problems, self::result($area, $subject, 'ambiguous', (string)$decl['name'], null,
                'more than one local_ltuse report has this area (ids ' . implode(', ', $ids)
                    . '); delete or re-area all but one by hand', report::BLOCKS_REPORT));
            return $problems;
        }

        $declared = $this->declared_state($decl, $resolved);
        if (!$records) {
            $main = self::result($area, $subject, inspector::RESULT_MISSING, self::state_summary($declared), null,
                'apply will create it');
        } else {
            $live = $this->live_state(reset($records), $decl);
            $differences = self::differences($declared, $live);
            if ($differences) {
                $main = self::result($area, $subject, inspector::RESULT_CHANGED,
                    self::difference_text($differences, 0), self::difference_text($differences, 1),
                    'differs: ' . implode(', ', array_keys($differences)));
            } else {
                $main = self::result($area, $subject, inspector::RESULT_OK, (string)$decl['name'],
                    (string)$decl['name']);
            }
        }
        return array_merge([$main], $problems);
    }

    /**
     * The `local_ltuse` reports nobody declares, each as an `extra` result. Kept, never
     * deleted. Reads the report table only: never the report's data.
     *
     * In drift, report each with $report->add_result($item); in apply, apply() reports them
     * as `[skip]` itself.
     *
     * @return array[]
     */
    public function extra(): array {
        $declared = $this->declared_areas();
        $items = [];
        $records = report_model::get_records(['component' => self::COMPONENT,
            'type' => datasource::TYPE_CUSTOM_REPORT], 'id');
        foreach ($records as $record) {
            $area = (string)$record->get('area');
            if (isset($declared[$area])) {
                continue;
            }
            $items[] = self::result($area, self::subject($area), 'extra', null, (string)$record->get('name'),
                'no longer declared in reports.yaml; kept, never deleted');
        }
        return $items;
    }

    // --- applying ------------------------------------------------------------------------

    /**
     * Bring every declared report to its declaration, writing only what differs, then report
     * the undeclared ones as `[skip]`.
     *
     * Each report is checked again first. A report with a blocking result of its own (run or
     * report scope) is reported and not written; the others go ahead. Each report's writes
     * run in one delegated transaction, so a write Moodle refuses leaves that report as it was.
     *
     * @param report $report
     * @return void
     */
    public function apply(report $report): void {
        foreach ($this->reports as $decl) {
            $this->apply_report($decl, $report);
        }
        foreach ($this->extra() as $extra) {
            $report->add_result($extra, 'skip');
        }
    }

    /**
     * @param array $decl
     * @param report $report
     * @return void
     */
    protected function apply_report(array $decl, report $report): void {
        global $DB;
        $area = (string)($decl['area'] ?? '');
        $items = $this->check_report($decl);
        if (report::has_blocking($items, $area)) {
            foreach ($items as $item) {
                if (report::blocking_scope($item) !== null || $item['result'] === inspector::RESULT_OK) {
                    $report->add_result($item);
                } else {
                    $report->add_result($item, null, trim($item['message'] . '; not written: this report is blocked '
                        . 'by the items that follow', '; '));
                }
            }
            return;
        }
        $main = $items[0];
        if ($main['result'] === inspector::RESULT_OK) {
            $report->add_result($main);
            return;
        }

        $source = ltrim((string)$decl['source'], '\\');
        $instance = self::datasource_instance($source, null, $decl, $error);
        $problems = [];
        $resolved = $this->resolve($decl, $instance, $source, $problems);

        $transaction = $DB->start_delegated_transaction();
        try {
            $records = self::find($area);
            if (!$records) {
                $persistent = rbreport::create_report((object)[
                    'name' => (string)$decl['name'],
                    'source' => $source,
                    'component' => self::COMPONENT,
                    'area' => $area,
                    'itemid' => 0,
                    'uniquerows' => (int)!empty($decl['uniquerows']),
                ], false);
            } else {
                $persistent = reset($records);
            }
            $this->write_report((int)$persistent->get('id'), $decl, $resolved);
            $transaction->allow_commit();
        } catch (\Exception $e) {
            try {
                $transaction->rollback($e);
            } catch (\Exception $rethrown) {
                // rollback() rethrows $e once the writes are undone; it is reported below.
                unset($rethrown);
            }
            $detail = $e->getMessage();
            if ($e instanceof \moodle_exception && !empty($e->debuginfo)) {
                $detail .= ' (' . $e->debuginfo . ')';
            }
            $report->add_result($main, 'fail', 'Moodle refused the change, so nothing was written: ' . $detail);
            return;
        }

        $after = $this->check_report($decl)[0];
        if ($after['result'] !== inspector::RESULT_OK) {
            $report->add_result($after, 'fail', 'written, but the server still differs');
        } else if ($main['result'] === inspector::RESULT_MISSING) {
            $report->add('changed', 'missing', $main['item'], $main['declared'], null, 'created');
        } else {
            $report->add_result($main, 'changed');
        }
    }

    /**
     * Write one report's parts: name, source and uniquerows, then columns, sorting, conditions
     * and their values, filters, audiences and schedule. Each part is written only if it
     * differs. The caller holds the transaction.
     *
     * @param int $reportid
     * @param array $decl
     * @param array $resolved from resolve()
     * @return void
     */
    protected function write_report(int $reportid, array $decl, array $resolved): void {
        $source = ltrim((string)$decl['source'], '\\');

        $persistent = new report_model($reportid);
        $name = trim((string)$decl['name']);
        $uniquerows = !empty($decl['uniquerows']);
        if ((string)$persistent->get('name') !== $name || ltrim((string)$persistent->get('source'), '\\') !== $source
                || (bool)$persistent->get('uniquerows') !== $uniquerows) {
            $persistent->set_many(['name' => $name, 'source' => $source, 'uniquerows' => $uniquerows])->update();
        }

        $this->write_columns($reportid, self::declared_columns($decl));
        $this->write_sorting($reportid, self::declared_sorting($decl));
        $this->write_elements($reportid, array_keys($resolved['conditions']), true);
        $instance = self::datasource_instance($source, new report_model($reportid), $decl, $error);
        if ($instance === null) {
            throw new \moodle_exception('generalexceptionmessage', 'error', '', $error);
        }
        $values = self::condition_store($resolved['conditions']);
        if (self::normalise_map($instance->get_condition_values()) !== self::normalise_map($values)) {
            $instance->set_condition_values($values);
        }
        $this->write_elements($reportid, self::declared_filters($decl), false);
        $audienceids = $this->write_audiences($reportid, $resolved['audiences']);
        $this->write_schedule($reportid, $decl, $audienceids);
    }

    /**
     * Match the declared columns to the live ones by identifier, in order; delete the rest, add
     * the missing ones, set heading and aggregation, then put them in the declared order.
     *
     * @param int $reportid
     * @param array[] $declared [{column, heading, aggregation}] from declared_columns()
     * @return void
     */
    protected function write_columns(int $reportid, array $declared): void {
        $live = column_model::get_records(['reportid' => $reportid], 'columnorder');
        $used = [];
        $matched = [];
        foreach ($declared as $i => $column) {
            foreach ($live as $record) {
                $id = (int)$record->get('id');
                if (!isset($used[$id]) && (string)$record->get('uniqueidentifier') === $column['column']) {
                    $matched[$i] = $id;
                    $used[$id] = true;
                    break;
                }
            }
        }
        foreach ($live as $record) {
            if (!isset($used[(int)$record->get('id')])) {
                rbreport::delete_report_column($reportid, (int)$record->get('id'));
            }
        }
        foreach ($declared as $i => $column) {
            if (!isset($matched[$i])) {
                $matched[$i] = (int)rbreport::add_report_column($reportid, $column['column'])->get('id');
            }
            // Re-read: a delete renumbers columnorder with raw SQL, and update() writes every field.
            $record = new column_model($matched[$i]);
            $heading = $column['heading'] === '' ? null : $column['heading'];
            $aggregation = $column['aggregation'] === '' ? null : $column['aggregation'];
            if ($record->get('heading') !== $heading || $record->get('aggregation') !== $aggregation) {
                $record->set_many(['heading' => $heading, 'aggregation' => $aggregation])->update();
            }
        }
        $order = array_values(array_map(function($record) {
            return (int)$record->get('id');
        }, column_model::get_records(['reportid' => $reportid], 'columnorder')));
        ksort($matched);
        $want = array_values($matched);
        if ($order !== $want) {
            foreach ($want as $position => $columnid) {
                rbreport::reorder_report_column($reportid, $columnid, $position + 1);
            }
        }
    }

    /**
     * Enable sorting on the declared columns with their direction, disable it on every other,
     * then set their precedence (T061). A column declared twice is sorted by its first copy.
     *
     * @param int $reportid
     * @param array[] $declared [{column, direction}] from declared_sorting()
     * @return void
     */
    protected function write_sorting(int $reportid, array $declared): void {
        $want = [];
        foreach ($declared as $sort) {
            $want[$sort['column']] = $sort['direction'] === 'desc' ? SORT_DESC : SORT_ASC;
        }
        $first = [];
        foreach (column_model::get_records(['reportid' => $reportid], 'columnorder') as $record) {
            $identifier = (string)$record->get('uniqueidentifier');
            $id = (int)$record->get('id');
            $sorted = isset($want[$identifier]) && !isset($first[$identifier]);
            if ($sorted) {
                $first[$identifier] = $id;
            }
            $enabled = (bool)$record->get('sortenabled');
            if ($sorted && (!$enabled || (int)$record->get('sortdirection') !== $want[$identifier])) {
                rbreport::toggle_report_column_sorting($reportid, $id, true, $want[$identifier]);
            } else if (!$sorted && $enabled) {
                rbreport::toggle_report_column_sorting($reportid, $id, false, (int)$record->get('sortdirection'));
            }
        }
        if (self::live_sorting($reportid) !== self::sorting_text($declared)) {
            $position = 1;
            foreach ($declared as $sort) {
                if (isset($first[$sort['column']])) {
                    rbreport::reorder_report_column_sorting($reportid, $first[$sort['column']], $position++);
                }
            }
        }
    }

    /**
     * Bring the report's conditions or filters to the declared identifiers, in order: delete the
     * undeclared ones (a condition added by hand included), add the missing ones, reorder.
     *
     * @param int $reportid
     * @param string[] $declared identifiers
     * @param bool $conditions true for conditions, false for filters
     * @return void
     */
    protected function write_elements(int $reportid, array $declared, bool $conditions): void {
        $records = $conditions ? filter_model::get_condition_records($reportid, 'filterorder')
            : filter_model::get_filter_records($reportid, 'filterorder');
        $ids = [];
        foreach ($records as $record) {
            $identifier = (string)$record->get('uniqueidentifier');
            if (!in_array($identifier, $declared, true) || isset($ids[$identifier])) {
                if ($conditions) {
                    rbreport::delete_report_condition($reportid, (int)$record->get('id'));
                } else {
                    rbreport::delete_report_filter($reportid, (int)$record->get('id'));
                }
                continue;
            }
            $ids[$identifier] = (int)$record->get('id');
        }
        foreach ($declared as $identifier) {
            if (!isset($ids[$identifier])) {
                $ids[$identifier] = (int)($conditions ? rbreport::add_report_condition($reportid, $identifier)
                    : rbreport::add_report_filter($reportid, $identifier))->get('id');
            }
        }
        $live = $conditions ? filter_model::get_condition_records($reportid, 'filterorder')
            : filter_model::get_filter_records($reportid, 'filterorder');
        $order = array_values(array_map(function($record) {
            return (string)$record->get('uniqueidentifier');
        }, $live));
        if ($order !== array_values($declared)) {
            foreach (array_values($declared) as $position => $identifier) {
                if ($conditions) {
                    rbreport::reorder_report_condition($reportid, $ids[$identifier], $position + 1);
                } else {
                    rbreport::reorder_report_filter($reportid, $ids[$identifier], $position + 1);
                }
            }
        }
    }

    /**
     * Bring the audiences to the declaration. An audience that already matches is kept; one of
     * the same type that differs is changed in place with update_configdata(), keeping the id a
     * schedule holds; a missing one is created; any other is deleted, since an undeclared
     * audience (an `allusers` one added by hand, say) would widen who sees the report.
     *
     * @param int $reportid
     * @param array[] $declared resolved audiences [{type, class, key, ids, text}]
     * @return int[] the audience ids, in declaration order
     */
    protected function write_audiences(int $reportid, array $declared): array {
        $live = audience_helper::get_base_records($reportid);
        $byid = [];
        foreach ($live as $instance) {
            $byid[(int)$instance->get_persistent()->get('id')] = $instance;
        }
        $result = [];
        $pending = [];
        foreach ($declared as $i => $audience) {
            foreach ($byid as $id => $instance) {
                if (self::audience_matches($instance, $audience)) {
                    $result[$i] = $id;
                    unset($byid[$id]);
                    continue 2;
                }
            }
            $pending[] = $i;
        }
        foreach ($pending as $i) {
            $audience = $declared[$i];
            $configdata = [$audience['key'] => $audience['ids']];
            foreach ($byid as $id => $instance) {
                if (ltrim(get_class($instance), '\\') === $audience['class']) {
                    $instance->update_configdata($configdata);
                    $result[$i] = $id;
                    unset($byid[$id]);
                    continue 2;
                }
            }
            $class = $audience['class'];
            $result[$i] = (int)$class::create($reportid, $configdata)->get_persistent()->get('id');
        }
        foreach (array_keys($byid) as $id) {
            if (!audience_helper::delete_report_audience($reportid, $id)) {
                throw new \moodle_exception('generalexceptionmessage', 'error', '',
                    "could not delete undeclared audience {$id}: the applying user may not edit it");
            }
        }
        ksort($result);
        return array_values($result);
    }

    /**
     * Bring the schedules to the declaration (T042). The declared schedule is created with
     * message::create(), owned by the user apply runs as (R12), or its fields are set back with
     * update_schedule(). Any other enabled schedule on the report is disabled, never deleted.
     * The start time is used only on create: drift ignores timescheduled, timenextsend and
     * timelastsent.
     *
     * @param int $reportid
     * @param array $decl
     * @param int[] $audienceids the report's audience ids
     * @return void
     */
    protected function write_schedule(int $reportid, array $decl, array $audienceids): void {
        $declared = self::declared_schedule_fields($decl);
        $ours = $declared === null ? null : self::our_schedule($reportid);
        if ($declared !== null) {
            $fields = $declared;
            $fields['audiences'] = json_encode(array_values(array_map('intval', $audienceids)));
            if ($ours === null) {
                $start = self::start_time((string)($decl['schedule']['start'] ?? ''),
                    \core_date::get_server_timezone_object(), time());
                if ($start === null) {
                    throw new \moodle_exception('generalexceptionmessage', 'error', '',
                        'the schedule start is not a day and a time');
                }
                $record = (object)($fields + ['reportid' => $reportid, 'timescheduled' => $start]);
                $class = self::SCHEDULE_CLASS;
                $ours = $class::create($record)->get_persistent();
            } else if (self::schedule_fields($ours) !== self::compare_fields($fields)
                    || self::id_list((string)$ours->get('audiences')) !== self::id_list($fields['audiences'])) {
                $ours = schedule_helper::update_schedule((object)($fields + ['id' => (int)$ours->get('id'),
                    'reportid' => $reportid]));
            }
        }
        foreach (schedule_model::get_records(['reportid' => $reportid], 'id') as $schedule) {
            if ($ours !== null && (int)$schedule->get('id') === (int)$ours->get('id')) {
                continue;
            }
            if ($schedule->get('enabled')) {
                schedule_helper::toggle_schedule($reportid, (int)$schedule->get('id'), false);
            }
        }
    }

    // --- declared and live state ---------------------------------------------------------

    /**
     * Resolve what the declaration names on this server: each condition's stored values, and
     * each audience's ids. Every problem found is appended to $problems as an item result.
     * Reads only.
     *
     * @param array $decl
     * @param \core_reportbuilder\local\report\base|null $instance a fresh datasource instance
     * @param string $source
     * @param array[] $problems
     * @return array{conditions: array<string, array>, audiences: array[]}
     */
    protected function resolve(array $decl, $instance, string $source, array &$problems): array {
        $area = (string)$decl['area'];
        $resolved = ['conditions' => [], 'audiences' => []];
        if ($instance === null) {
            return $resolved;
        }

        $available = $instance->get_columns();
        foreach (self::declared_columns($decl) as $column) {
            $identifier = $column['column'];
            if (!isset($available[$identifier])) {
                $problems[] = self::unknown($area, $source, $identifier, 'the datasource has no such column');
                continue;
            }
            if ($column['aggregation'] !== '') {
                $instancecolumn = $available[$identifier];
                $allowed = aggregation::get_column_aggregations($instancecolumn->get_type(),
                    $instancecolumn->get_disabled_aggregation());
                if (!isset($allowed[$column['aggregation']])) {
                    $problems[] = self::unknown($area, $source, $identifier,
                        "this column does not allow aggregation '{$column['aggregation']}'; it allows "
                        . (implode(', ', array_keys($allowed)) ?: 'none'));
                }
            }
        }
        $declaredcolumns = array_column(self::declared_columns($decl), 'column');
        foreach (self::declared_sorting($decl) as $sort) {
            if (!in_array($sort['column'], $declaredcolumns, true)) {
                $problems[] = self::result($area, self::subject($area, 'sorting ' . $sort['column']),
                    inspector::RESULT_UNKNOWN, $sort['column'], null,
                    'a sorted column must be one of the report\'s columns; re-render the payload', report::BLOCKS_RUN);
            }
        }

        $availablefilters = $instance->get_filters();
        foreach (self::declared_filters($decl) as $identifier) {
            if (!isset($availablefilters[$identifier])) {
                $problems[] = self::unknown($area, $source, $identifier, 'the datasource has no such filter');
            }
        }

        foreach (self::entries($decl['conditions'] ?? []) as $condition) {
            $identifier = (string)($condition['condition'] ?? '');
            $filter = $instance->get_condition($identifier);
            if ($filter === null || !$filter->get_is_available()) {
                $problems[] = self::unknown($area, $source, $identifier, 'the datasource has no such condition');
                continue;
            }
            $values = self::as_array($condition['values'] ?? []);
            $display = $values['value'] ?? null;
            if ($identifier === self::ROLE_CONDITION && isset($values['value'])) {
                $roleid = self::role_id((string)$values['value']);
                if ($roleid === null) {
                    $problems[] = self::result($area, self::subject($area, 'condition ' . $identifier),
                        inspector::RESULT_MISSING, (string)$values['value'], null,
                        "no role has shortname '{$values['value']}', so this condition would select nothing",
                        report::BLOCKS_REPORT);
                    continue;
                }
                $values['value'] = (string)$roleid;
            }
            $stored = self::condition_values($identifier, self::filter_kind($filter->get_filter_class()), $values, $err);
            if ($stored === null) {
                $problems[] = self::result($area, self::subject($area, 'condition ' . $identifier),
                    inspector::RESULT_UNKNOWN, self::display($condition['values'] ?? null), null, $err, report::BLOCKS_RUN);
                continue;
            }
            $resolved['conditions'][$identifier] = $stored;

            // The fail-open guard (R10): the condition must produce SQL, or core skips it.
            $filterclass = $filter->get_filter_class();
            [$sql] = $filterclass::create($filter)->get_sql_filter($stored);
            if ($sql === '') {
                $problems[] = self::result($area, self::subject($area, 'condition ' . $identifier),
                    inspector::RESULT_UNKNOWN, self::display($display), null,
                    self::empty_sql_reason($filter, $stored, $identifier), report::BLOCKS_REPORT);
            }
        }

        foreach (self::entries($decl['audiences'] ?? []) as $audience) {
            $type = (string)($audience['type'] ?? '');
            if (!isset(self::AUDIENCE_CLASSES[$type])) {
                $problems[] = self::result($area, self::subject($area, 'audience ' . $type), inspector::RESULT_UNKNOWN,
                    $type, null, 'an audience type must be ' . implode(' or ', array_keys(self::AUDIENCE_CLASSES)),
                    report::BLOCKS_RUN);
                continue;
            }
            $name = (string)($type === 'cohortmember' ? ($audience['cohort'] ?? '') : ($audience['role'] ?? ''));
            $text = self::audience_text($type, $name);
            $ids = [];
            if ($type === 'cohortmember') {
                $cohorts = self::cohorts_by_idnumber($name);
                if (count($cohorts) > 1) {
                    $problems[] = self::result($area, self::subject($area, $text), 'ambiguous', $name, null,
                        'more than one cohort has this idnumber', report::BLOCKS_REPORT);
                } else if (!$cohorts) {
                    $problems[] = self::result($area, self::subject($area, $text), inspector::RESULT_MISSING, $name,
                        null, 'the cohort does not exist yet; this report is not written until it does',
                        report::BLOCKS_REPORT);
                } else {
                    $ids = [(int)array_key_first($cohorts)];
                }
            } else {
                $roleid = self::role_id($name);
                if ($roleid === null) {
                    $problems[] = self::result($area, self::subject($area, $text), inspector::RESULT_MISSING, $name,
                        null, 'the role does not exist; this report is not written until it does', report::BLOCKS_REPORT);
                } else {
                    $ids = [$roleid];
                }
            }
            $resolved['audiences'][] = ['type' => $type, 'class' => self::AUDIENCE_CLASSES[$type],
                'key' => self::AUDIENCE_KEYS[$type], 'ids' => $ids, 'text' => $text];
        }

        $schedule = self::declared_schedule_fields($decl);
        if ($schedule !== null) {
            $problem = self::schedule_problem($decl, $schedule);
            if ($problem !== null) {
                $problems[] = self::result($area, self::subject($area, 'schedule'), inspector::RESULT_UNKNOWN,
                    self::display($decl['schedule']), null, $problem[0], $problem[1]);
            }
        }
        return $resolved;
    }

    /**
     * The declared state, in the comparable shape live_state() also returns.
     *
     * @param array $decl
     * @param array $resolved
     * @return array<string, mixed> keyed by PARTS
     */
    protected function declared_state(array $decl, array $resolved): array {
        $conditions = [];
        foreach (self::entries($decl['conditions'] ?? []) as $condition) {
            $identifier = (string)($condition['condition'] ?? '');
            $conditions[] = $identifier;
        }
        $values = [];
        foreach ($resolved['conditions'] as $stored) {
            $values += $stored;
        }
        $audiences = array_column($resolved['audiences'], 'text');
        sort($audiences);
        $schedule = self::declared_schedule_fields($decl);
        return [
            'name' => trim((string)$decl['name']),
            'source' => ltrim((string)$decl['source'], '\\'),
            'uniquerows' => (int)!empty($decl['uniquerows']),
            'columns' => self::columns_text(self::declared_columns($decl)),
            'sorting' => self::sorting_text(self::declared_sorting($decl)),
            'conditions' => ['identifiers' => $conditions, 'values' => self::display_values(self::normalise_map($values))],
            'filters' => self::declared_filters($decl),
            'audiences' => $audiences,
            'schedule' => [
                'declared' => $schedule === null ? null : self::compare_fields($schedule) + ['audiences' => $audiences],
                'other enabled' => 0,
            ],
        ];
    }

    /**
     * A live report's state, in the declared shape. Reads the report's definition only.
     *
     * @param report_model $persistent
     * @param array $decl the declaration it is compared with
     * @return array<string, mixed>
     */
    protected function live_state(report_model $persistent, array $decl): array {
        $reportid = (int)$persistent->get('id');
        $columns = [];
        foreach (column_model::get_records(['reportid' => $reportid], 'columnorder') as $record) {
            $columns[] = ['column' => (string)$record->get('uniqueidentifier'),
                'heading' => trim((string)$record->get('heading')), 'aggregation' => (string)$record->get('aggregation')];
        }
        $conditions = array_values(array_map(function($record) {
            return (string)$record->get('uniqueidentifier');
        }, filter_model::get_condition_records($reportid, 'filterorder')));
        $filters = array_values(array_map(function($record) {
            return (string)$record->get('uniqueidentifier');
        }, filter_model::get_filter_records($reportid, 'filterorder')));
        $declaredids = [];
        foreach (self::entries($decl['conditions'] ?? []) as $condition) {
            $declaredids[] = (string)($condition['condition'] ?? '');
        }
        $values = self::visible_values(
            self::normalise_map((array)json_decode((string)$persistent->get('conditiondata'), true)), $declaredids);

        $audiencetexts = [];
        $byid = [];
        foreach (audience_helper::get_base_records($reportid) as $instance) {
            $text = self::live_audience_text($instance);
            $byid[(int)$instance->get_persistent()->get('id')] = $text;
            $audiencetexts[] = $text;
        }
        sort($audiencetexts);

        return [
            'name' => (string)$persistent->get('name'),
            'source' => ltrim((string)$persistent->get('source'), '\\'),
            'uniquerows' => (int)(bool)$persistent->get('uniquerows'),
            'columns' => self::columns_text($columns),
            'sorting' => self::live_sorting($reportid),
            'conditions' => ['identifiers' => $conditions, 'values' => self::display_values($values)],
            'filters' => $filters,
            'audiences' => $audiencetexts,
            'schedule' => self::live_schedule($reportid, $byid, self::declared_schedule_fields($decl) !== null),
        ];
    }

    /**
     * The live schedule state: the report's first message schedule (when one is declared)
     * and how many other schedules are enabled.
     *
     * @param int $reportid
     * @param array<int, string> $audiences live audience text by id
     * @param bool $declared whether the declaration has a schedule
     * @return array{declared: array|null, other enabled: int}
     */
    protected static function live_schedule(int $reportid, array $audiences, bool $declared): array {
        $ours = $declared ? self::our_schedule($reportid) : null;
        $fields = null;
        if ($ours !== null) {
            $texts = [];
            foreach ((array)json_decode((string)$ours->get('audiences'), true) as $id) {
                $texts[] = $audiences[(int)$id] ?? "audience {$id}, which is not on this report";
            }
            sort($texts);
            $fields = self::schedule_fields($ours) + ['audiences' => $texts];
        }
        $others = 0;
        foreach (schedule_model::get_records(['reportid' => $reportid], 'id') as $schedule) {
            if (($ours === null || (int)$schedule->get('id') !== (int)$ours->get('id')) && $schedule->get('enabled')) {
                $others++;
            }
        }
        return ['declared' => $fields, 'other enabled' => $others];
    }

    // --- pure comparisons (no database) --------------------------------------------------

    /**
     * The parts that differ, each as [declared, live] display text, in PARTS order.
     *
     * @param array $declared
     * @param array $live
     * @return array<string, string[]>
     */
    public static function differences(array $declared, array $live): array {
        $diff = [];
        foreach (self::PARTS as $part) {
            $want = self::display($declared[$part] ?? null) ?? '';
            $have = self::display($live[$part] ?? null) ?? '';
            if ($want !== $have) {
                $diff[$part] = [$want, $have];
            }
        }
        return $diff;
    }

    /**
     * One side of a set of differences as text: `columns [...]; filters [...]`.
     *
     * @param array<string, string[]> $differences
     * @param int $side 0 for declared, 1 for live
     * @return string
     */
    public static function difference_text(array $differences, int $side): string {
        $parts = [];
        foreach ($differences as $part => $values) {
            $parts[] = "{$part} {$values[$side]}";
        }
        return implode('; ', $parts);
    }

    /**
     * A declared report as one line, for a missing item: its name and how much it holds.
     *
     * @param array $state from declared_state()
     * @return string
     */
    public static function state_summary(array $state): string {
        return "\"{$state['name']}\", " . count($state['columns']) . ' columns, '
            . count($state['conditions']['identifiers']) . ' conditions, ' . count($state['filters']) . ' filters, '
            . 'audiences ' . (implode(' | ', $state['audiences']) ?: 'none')
            . ($state['schedule']['declared'] !== null ? ', scheduled' : '');
    }

    /**
     * Declared columns, normalised: [{column, heading, aggregation}] with '' for none.
     *
     * @param array $decl
     * @return array[]
     */
    public static function declared_columns(array $decl): array {
        return array_map(function($column) {
            return ['column' => (string)($column['column'] ?? ''), 'heading' => trim((string)($column['heading'] ?? '')),
                'aggregation' => (string)($column['aggregation'] ?? '')];
        }, self::entries($decl['columns'] ?? []));
    }

    /**
     * Declared sorting, normalised: [{column, direction asc|desc}]. Absent means none.
     *
     * @param array $decl
     * @return array[]
     */
    public static function declared_sorting(array $decl): array {
        return array_map(function($sort) {
            return ['column' => (string)($sort['column'] ?? ''),
                'direction' => strtolower((string)($sort['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc'];
        }, self::entries($decl['sorting'] ?? []));
    }

    /**
     * Declared filter identifiers.
     *
     * @param array $decl
     * @return string[]
     */
    public static function declared_filters(array $decl): array {
        $filters = $decl['filters'] ?? [];
        return is_array($filters) ? array_values(array_map('strval', $filters)) : [];
    }

    /**
     * Columns as comparable text: `user:fullname "Learner" (countdistinct)`.
     *
     * @param array[] $columns [{column, heading, aggregation}]
     * @return string[]
     */
    public static function columns_text(array $columns): array {
        return array_map(function($column) {
            return $column['column'] . ($column['heading'] !== '' ? " \"{$column['heading']}\"" : '')
                . ($column['aggregation'] !== '' ? " ({$column['aggregation']})" : '');
        }, array_values($columns));
    }

    /**
     * Sorting as comparable text, in precedence order: `competency:name asc`.
     *
     * @param array[] $sorting [{column, direction}]
     * @return string[]
     */
    public static function sorting_text(array $sorting): array {
        return array_map(function($sort) {
            return "{$sort['column']} {$sort['direction']}";
        }, array_values($sorting));
    }

    /**
     * The stored conditiondata keys for one condition: `<id>_operator` (an int) and
     * `<id>_value`, plus `<id>_<key>` for any other key a filter class takes.
     *
     * @param string $identifier
     * @param string $kind 'select', 'text' or another filter class's short name
     * @param array $values {operator, value, ...}
     * @param string|null $error set when it cannot be stored
     * @return array|null
     */
    public static function condition_values(string $identifier, string $kind, array $values, ?string &$error = null): ?array {
        $error = null;
        $operator = $values['operator'] ?? null;
        if (is_int($operator) || (is_string($operator) && ctype_digit($operator))) {
            $code = (int)$operator;
        } else if (is_string($operator) && isset(self::OPERATORS[$kind][$operator])) {
            $code = self::OPERATORS[$kind][$operator];
        } else {
            $words = isset(self::OPERATORS[$kind]) ? implode(', ', array_keys(self::OPERATORS[$kind])) : 'an integer';
            $error = "operator " . self::display($operator) . " is not one a {$kind} condition takes ({$words})";
            return null;
        }
        if ($code === 0) {
            $error = "operator 'any' would make this condition select nothing";
            return null;
        }
        $stored = ["{$identifier}_operator" => $code];
        foreach ($values as $key => $value) {
            if ($key === 'operator') {
                continue;
            }
            if (!is_scalar($value) && $value !== null) {
                $error = "condition value '{$key}' must be a single value";
                return null;
            }
            $stored["{$identifier}_{$key}"] = $key === 'value' ? (string)$value : $value;
        }
        return $stored;
    }

    /**
     * All resolved conditions' stored keys, as one conditiondata map.
     *
     * @param array<string, array> $conditions
     * @return array
     */
    public static function condition_store(array $conditions): array {
        $values = [];
        foreach ($conditions as $stored) {
            $values += $stored;
        }
        return $values;
    }

    /**
     * A conditiondata map with every value as a string, sorted by key, for comparing.
     *
     * @param array $values
     * @return array<string, string>
     */
    public static function normalise_map(array $values): array {
        $out = [];
        foreach ($values as $key => $value) {
            $out[(string)$key] = is_scalar($value) || $value === null ? (string)$value : json_encode($value);
        }
        ksort($out);
        return $out;
    }

    /**
     * The short name of a filter class: 'select', 'text', 'date', ...
     *
     * @param string $class
     * @return string
     */
    public static function filter_kind(string $class): string {
        $class = ltrim($class, '\\');
        $pos = strrpos($class, '\\');
        return $pos === false ? $class : substr($class, $pos + 1);
    }

    /**
     * How far an unknown identifier blocks. Run-wide, except what this run can still create or
     * a plugin upgrade adds: a course custom field or user profile field column, filter or
     * condition, and anything on a local_ltuse datasource.
     *
     * @param string $source
     * @param string $identifier
     * @return string report::BLOCKS_RUN or report::BLOCKS_REPORT
     */
    public static function unknown_scope(string $source, string $identifier = ''): string {
        if (strpos(ltrim($source, '\\'), self::COMPONENT . '\\') === 0
                || preg_match('/^(course:customfield_|user:profilefield_)/', $identifier)) {
            return report::BLOCKS_REPORT;
        }
        return report::BLOCKS_RUN;
    }

    /**
     * The first time a schedule is due: the next given weekday at the given time, in site time.
     * Today counts if the time has not passed; core moves a past start on by the recurrence.
     *
     * @param string $start e.g. `monday 07:00`
     * @param DateTimeZone $tz
     * @param int $now
     * @return int|null null when it is not a weekday and a 24-hour time
     */
    public static function start_time(string $start, DateTimeZone $tz, int $now): ?int {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        if (!preg_match('/^\s*([a-z]+)\s+([01]?\d|2[0-3]):([0-5]\d)\s*$/i', $start, $m)
                || !in_array(strtolower($m[1]), $days, true)) {
            return null;
        }
        $time = (new DateTimeImmutable('@' . $now))->setTimezone($tz)->modify(strtolower($m[1]))
            ->setTime((int)$m[2], (int)$m[3]);
        if ($time->getTimestamp() <= $now) {
            $time = $time->modify('+1 week');
        }
        return $time->getTimestamp();
    }

    /**
     * The declared schedule's persistent fields, or null when none is declared.
     *
     * @param array $decl
     * @return array|null {name, enabled, classname, format, userviewas, recurrence, configdata}
     */
    public static function declared_schedule_fields(array $decl): ?array {
        if (!isset($decl['schedule']) || $decl['schedule'] === null || $decl['schedule'] === []) {
            return null;
        }
        $schedule = self::as_array($decl['schedule']);
        $configdata = self::as_array($schedule['configdata'] ?? []);
        $message = self::as_array($configdata['message'] ?? []);
        $configdata = [
            'subject' => (string)($configdata['subject'] ?? ''),
            'message' => ['text' => (string)($message['text'] ?? ''), 'format' => (int)($message['format'] ?? FORMAT_HTML)],
            'reportempty' => (int)($configdata['reportempty'] ?? -1),
        ];
        return [
            'name' => trim((string)($schedule['name'] ?? $configdata['subject'])),
            'enabled' => 1,
            'classname' => self::SCHEDULE_CLASS,
            'format' => (string)($schedule['format'] ?? ''),
            'userviewas' => (int)($schedule['userviewas'] ?? 0),
            'recurrence' => (int)($schedule['recurrence'] ?? -1),
            'configdata' => json_encode($configdata),
        ];
    }

    /**
     * Schedule fields in the shape compared by drift. Never the times.
     *
     * @param array $fields from declared_schedule_fields()
     * @return array
     */
    public static function compare_fields(array $fields): array {
        $configdata = json_decode((string)$fields['configdata'], true) ?: [];
        return [
            'name' => (string)$fields['name'],
            'enabled' => (int)(bool)$fields['enabled'],
            'type' => self::filter_kind((string)$fields['classname']),
            'format' => (string)$fields['format'],
            'userviewas' => self::viewas_text((int)$fields['userviewas']),
            'recurrence' => self::RECURRENCES[(int)$fields['recurrence']] ?? (string)$fields['recurrence'],
            'subject' => (string)($configdata['subject'] ?? ''),
            'message' => (string)($configdata['message']['text'] ?? ''),
            'messageformat' => (int)($configdata['message']['format'] ?? FORMAT_HTML),
            'reportempty' => self::reportempty_text($configdata['reportempty'] ?? null),
        ];
    }

    /**
     * A JSON list of ids, as sorted ints.
     *
     * @param string $json
     * @return int[]
     */
    public static function id_list(string $json): array {
        $ids = array_map('intval', (array)json_decode($json, true));
        sort($ids);
        return $ids;
    }

    /**
     * @param int $viewas
     * @return string
     */
    protected static function viewas_text(int $viewas): string {
        return [-1 => 'recipient', 0 => 'creator'][$viewas] ?? "user {$viewas}";
    }

    /**
     * @param mixed $reportempty
     * @return string
     */
    protected static function reportempty_text($reportempty): string {
        if ($reportempty === null) {
            return 'send empty (default)';
        }
        return [0 => 'send empty', 1 => 'send without file', 2 => 'do not send'][(int)$reportempty]
            ?? (string)$reportempty;
    }

    /**
     * Why a declared schedule cannot be written, and how far that blocks; or null.
     *
     * @param array $decl
     * @param array $fields from declared_schedule_fields()
     * @return array|null [message, scope]
     */
    protected static function schedule_problem(array $decl, array $fields): ?array {
        if ($fields['userviewas'] !== -1) {
            return ['a schedule must view the report as each recipient (userviewas -1), never as its creator',
                report::BLOCKS_RUN];
        }
        if (!isset(self::RECURRENCES[$fields['recurrence']]) || $fields['name'] === '') {
            return ['a schedule needs a name and a recurrence; re-render the payload', report::BLOCKS_RUN];
        }
        $configdata = json_decode($fields['configdata'], true);
        if (!in_array($configdata['reportempty'], [0, 1, 2], true) || $configdata['subject'] === '') {
            return ['a schedule needs a subject and reportempty 0, 1 or 2; re-render the payload', report::BLOCKS_RUN];
        }
        if (self::entries($decl['audiences'] ?? []) === []) {
            return ['a schedule is sent to the report\'s audiences, and this report has none', report::BLOCKS_RUN];
        }
        if (self::start_time((string)($decl['schedule']['start'] ?? ''), new DateTimeZone('UTC'), 0) === null) {
            return ["the start must be a weekday and a time, such as 'monday 07:00'", report::BLOCKS_RUN];
        }
        $enabled = \core\plugininfo\dataformat::get_enabled_plugins();
        if (!isset($enabled[$fields['format']])) {
            return ["dataformat '{$fields['format']}' is not enabled on this server", report::BLOCKS_REPORT];
        }
        return null;
    }

    // --- reads ---------------------------------------------------------------------------

    /**
     * Every custom report with this area and component local_ltuse.
     *
     * @param string $area
     * @return report_model[]
     */
    protected static function find(string $area): array {
        $records = report_model::get_records(['component' => self::COMPONENT, 'area' => $area,
            'type' => datasource::TYPE_CUSTOM_REPORT], 'id');
        return array_values(array_filter($records, function($record) use ($area) {
            return (string)$record->get('area') === $area;
        }));
    }

    /**
     * A fresh datasource instance, never manager's per-request cached one. With no persistent,
     * an unsaved one carrying the declared source is used: enough to list what the datasource
     * offers and to build its filters, and nothing is saved.
     *
     * @param string $source
     * @param report_model|null $persistent
     * @param array $decl
     * @param string|null $error why there is none
     * @return \core_reportbuilder\local\report\base|null
     */
    protected static function datasource_instance(string $source, ?report_model $persistent, array $decl,
            ?string &$error = null) {
        $error = null;
        if (!manager::report_source_exists($source, datasource::class)) {
            $error = 'no report builder datasource has this class on this server';
            return null;
        }
        if (!manager::report_source_available($source)) {
            $error = 'this datasource is not available on this server';
            return null;
        }
        if ($persistent === null) {
            $persistent = new report_model(0, (object)[
                'name' => (string)($decl['name'] ?? ''),
                'source' => $source,
                'type' => datasource::TYPE_CUSTOM_REPORT,
                'component' => self::COMPONENT,
                'area' => (string)($decl['area'] ?? ''),
                'uniquerows' => !empty($decl['uniquerows']),
            ]);
        }
        try {
            return new $source($persistent);
        } catch (\Exception $e) {
            $error = 'the datasource could not be built: ' . $e->getMessage();
            return null;
        }
    }

    /**
     * The report's declared schedule's live persistent: its first message schedule by id.
     *
     * @param int $reportid
     * @return schedule_model|null
     */
    protected static function our_schedule(int $reportid): ?schedule_model {
        foreach (schedule_model::get_records(['reportid' => $reportid], 'id') as $schedule) {
            if (ltrim((string)$schedule->get('classname'), '\\') === self::SCHEDULE_CLASS) {
                return $schedule;
            }
        }
        return null;
    }

    /**
     * A live schedule's compared fields.
     *
     * @param schedule_model $schedule
     * @return array
     */
    protected static function schedule_fields(schedule_model $schedule): array {
        return self::compare_fields([
            'name' => (string)$schedule->get('name'),
            'enabled' => (int)(bool)$schedule->get('enabled'),
            'classname' => (string)$schedule->get('classname'),
            'format' => (string)$schedule->get('format'),
            'userviewas' => (int)$schedule->get('userviewas'),
            'recurrence' => (int)$schedule->get('recurrence'),
            'configdata' => (string)$schedule->get('configdata'),
        ]);
    }

    /**
     * Why a condition's filter gave no SQL, for a human.
     *
     * @param \core_reportbuilder\local\report\filter $filter
     * @param array $stored
     * @param string $identifier
     * @return string
     */
    protected static function empty_sql_reason($filter, array $stored, string $identifier): string {
        $message = 'this condition produces no SQL, so Moodle would skip it and the report would widen';
        if (self::filter_kind($filter->get_filter_class()) !== 'select') {
            return $message;
        }
        $options = (array)$filter->get_options();
        if (count($options) !== count($options, COUNT_RECURSIVE)) {
            $options = array_merge(...array_values($options));
        }
        $value = (string)($stored["{$identifier}_value"] ?? '');
        if (array_key_exists($value, $options)) {
            return $message . '; the value is among the options now, but Moodle cached them earlier in this run, '
                . 'before they changed: run apply again';
        }
        if ($identifier === 'enrol:plugin') {
            return $message . "; enrol plugin '{$value}' is not enabled";
        }
        return $message . '; the value is not among its options';
    }

    /**
     * Whether a live audience already is the declared one.
     *
     * @param audience_base $instance
     * @param array $audience resolved
     * @return bool
     */
    protected static function audience_matches(audience_base $instance, array $audience): bool {
        if (ltrim(get_class($instance), '\\') !== $audience['class']) {
            return false;
        }
        $ids = array_map('intval', (array)($instance->get_configdata()[$audience['key']] ?? []));
        sort($ids);
        $want = $audience['ids'];
        sort($want);
        return $ids === $want;
    }

    /**
     * A declared audience as text.
     *
     * @param string $type
     * @param string $name a cohort idnumber or a role shortname
     * @return string
     */
    protected static function audience_text(string $type, string $name): string {
        return ($type === 'cohortmember' ? 'audience cohort ' : 'audience role ') . $name;
    }

    /**
     * A live audience as the same text: ids mapped back to cohort idnumbers or role shortnames.
     * Never a member.
     *
     * @param audience_base $instance
     * @return string
     */
    protected static function live_audience_text(audience_base $instance): string {
        global $DB;
        $class = ltrim(get_class($instance), '\\');
        $type = array_search($class, self::AUDIENCE_CLASSES, true);
        $configdata = (array)$instance->get_configdata();
        if ($type === false) {
            return self::unknown_audience_text(self::filter_kind($class), $configdata);
        }
        $ids = array_map('intval', (array)($configdata[self::AUDIENCE_KEYS[$type]] ?? []));
        $table = $type === 'cohortmember' ? 'cohort' : 'role';
        $field = $type === 'cohortmember' ? 'idnumber' : 'shortname';
        $names = [];
        $records = $ids ? $DB->get_records_list($table, 'id', $ids, '', "id, {$field}") : [];
        foreach ($ids as $id) {
            $names[] = isset($records[$id]) && (string)$records[$id]->$field !== '' ? (string)$records[$id]->$field
                : "id {$id}";
        }
        sort($names);
        return self::audience_text($type, implode(', ', $names));
    }

    /**
     * An audience of a type the declaration cannot hold, as text: its kind and how many
     * values it is configured with. Never the configdata itself, which can be user ids
     * (core's 'Manually added users' audience stores `users => [userid, ...]`).
     *
     * @param string $kind
     * @param array $configdata
     * @return string
     */
    public static function unknown_audience_text(string $kind, array $configdata): string {
        $count = 0;
        array_walk_recursive($configdata, function() use (&$count) {
            $count++;
        });
        return "audience {$kind} ({$count} " . ($count === 1 ? 'value' : 'values') . ', not shown)';
    }

    /**
     * Live condition values limited to the declared conditions' keys (`<identifier>_...`).
     * Any other stored value (a hand-added condition, or one core left behind after its
     * condition was deleted) can be a learner's name or username, so only how many there
     * are is shown, under one key, which is enough to make it a difference.
     *
     * @param array<string, string> $values from normalise_map()
     * @param string[] $identifiers the declared condition identifiers
     * @return array<string, string>
     */
    public static function visible_values(array $values, array $identifiers): array {
        $visible = [];
        $hidden = 0;
        foreach ($values as $key => $value) {
            $declared = false;
            foreach ($identifiers as $identifier) {
                if ($identifier !== '' && strpos((string)$key, $identifier . '_') === 0) {
                    $declared = true;
                    break;
                }
            }
            if ($declared) {
                $visible[$key] = $value;
            } else {
                $hidden++;
            }
        }
        if ($hidden > 0) {
            $visible['(undeclared)'] = "{$hidden} " . ($hidden === 1 ? 'value' : 'values') . ', not shown';
        }
        return $visible;
    }

    /**
     * Cohorts with exactly this idnumber, keyed by id. Only id and idnumber are read.
     *
     * @param string $idnumber
     * @return stdClass[]
     */
    protected static function cohorts_by_idnumber(string $idnumber): array {
        global $DB;
        $records = $DB->get_records('cohort', ['idnumber' => $idnumber], 'id', 'id, idnumber');
        return array_filter($records, function($record) use ($idnumber) {
            return (string)$record->idnumber === $idnumber;
        });
    }

    /**
     * A role's id by exact shortname, or null.
     *
     * @param string $shortname
     * @return int|null
     */
    protected static function role_id(string $shortname): ?int {
        global $DB;
        foreach ($DB->get_records('role', ['shortname' => $shortname], 'id', 'id, shortname') as $record) {
            if ((string)$record->shortname === $shortname) {
                return (int)$record->id;
            }
        }
        return null;
    }

    /**
     * Stored condition values for display: a role:name value shown as its role's shortname.
     *
     * @param array<string, string> $values
     * @return array<string, string>
     */
    protected static function display_values(array $values): array {
        global $DB;
        $key = self::ROLE_CONDITION . '_value';
        if (isset($values[$key]) && ctype_digit($values[$key])) {
            $shortname = $DB->get_field('role', 'shortname', ['id' => (int)$values[$key]]);
            $values[$key] = $shortname === false ? "role id {$values[$key]}" : (string)$shortname;
        }
        return $values;
    }

    /**
     * The live sorting of a report, as sorting_text() gives it: sorted columns by precedence.
     *
     * @param int $reportid
     * @return string[]
     */
    protected static function live_sorting(int $reportid): array {
        $sorted = [];
        foreach (column_model::get_records(['reportid' => $reportid, 'sortenabled' => 1], 'id') as $record) {
            $sorted[] = $record;
        }
        usort($sorted, function($a, $b) {
            $x = $a->get('sortorder');
            $y = $b->get('sortorder');
            if ($x === $y) {
                return (int)$a->get('id') <=> (int)$b->get('id');
            }
            if ($x === null || $y === null) {
                return $x === null ? 1 : -1;
            }
            return (int)$x <=> (int)$y;
        });
        return self::sorting_text(array_map(function($record) {
            return ['column' => (string)$record->get('uniqueidentifier'),
                'direction' => (int)$record->get('sortdirection') === SORT_DESC ? 'desc' : 'asc'];
        }, $sorted));
    }

    // --- results -------------------------------------------------------------------------

    /**
     * An `unknown` identifier result.
     *
     * @param string $area
     * @param string $source
     * @param string $identifier
     * @param string $message
     * @return array
     */
    protected static function unknown(string $area, string $source, string $identifier, string $message): array {
        $scope = self::unknown_scope($source, $identifier);
        if ($scope === report::BLOCKS_REPORT && strpos(ltrim($source, '\\'), self::COMPONENT . '\\') === 0) {
            $message .= '; upgrade local_ltuse first';
        }
        return self::result($area, self::subject($area, $identifier), inspector::RESULT_UNKNOWN, $identifier, null,
            $message, $scope);
    }

    /**
     * Build one item result in the inspector's shape, plus the report's area.
     *
     * @param string $area
     * @param string $subject
     * @param string $result ok, changed, missing, extra, unknown or ambiguous
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool|string $blocking false, report::BLOCKS_RUN or report::BLOCKS_REPORT
     * @return array
     */
    protected static function result(string $area, string $subject, string $result, $declared = null, $live = null,
            string $message = '', $blocking = false): array {
        return [
            'type' => self::TYPE,
            'item' => $subject,
            'report' => $area,
            'result' => $result,
            'declared' => $declared,
            'live' => $live,
            'message' => $message,
            'secret' => false,
            'blocking' => $blocking,
        ];
    }

    /**
     * A payload list as a list of associative arrays.
     *
     * @param mixed $entries
     * @return array[]
     */
    protected static function entries($entries): array {
        if (!is_array($entries)) {
            return [];
        }
        return array_values(array_map(function($entry) {
            return self::as_array($entry);
        }, $entries));
    }

    /**
     * An object or array, recursively, as an associative array; anything else as it is.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function as_array($value) {
        if (is_object($value)) {
            $value = (array)$value;
        }
        if (is_array($value)) {
            return array_map(function($item) {
                return is_object($item) || is_array($item) ? self::as_array($item) : $item;
            }, $value);
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    protected static function display($value): ?string {
        if ($value === null) {
            return null;
        }
        return is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
