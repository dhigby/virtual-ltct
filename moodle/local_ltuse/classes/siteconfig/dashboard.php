<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use context_system;

/**
 * The system default dashboard: its blocks, their order, and personal dashboards (spec 011,
 * FR-003, research R18; amended by spec 007, specs/007-learner-experience/contracts/
 * dashboard-declaration.md).
 *
 * The declaration arrives as the payload's `dashboard`, [{block, region, weight?}], from
 * moodle/site/dashboard.yaml, with two siblings: `dashboard_complete` (the list is the whole
 * default page) and `dashboard_personal` ('reset' or 'keep'). The default dashboard is the
 * my_pages row with userid null, name '__default' and private 1 (my/lib.php MY_PAGE_DEFAULT);
 * a block on it has the system context as parent, page type my-index and that row's id as its
 * subpage, as blocks_add_default_system_blocks() places core's own (lib/blocklib.php). Those
 * instances, in any region, are the only ones this class reads or changes. A personal
 * dashboard is a my_pages row with a userid, name '__default' and private 1; the per-user
 * '__courses' rows are not counted.
 *
 * dashboard_plan::plan() decides what changes; check() and apply() each call it afresh, since
 * apply's roles step, which runs before this one, may just have prevented editing.
 *
 * Items: `dashboard <block>` (ok, missing, or extra with `complete`), `dashboard <block> weight`
 * (changed) and `dashboard personal dashboards` (changed, declared reset, live a count). No item
 * names a user. Apply, in this order: adds a missing block at its declared weight; with
 * `complete`, deletes every undeclared instance on the default page through core's
 * blocks_delete_instance(); sets declared weights; with `reset`, and only while
 * moodle/my:manageblocks is prevented for the user role live, resets personal dashboards with
 * core's my_reset_page_for_all_users(). It never touches a block on any other page and never
 * edits a block's configuration.
 *
 * The reads are block_instances, block_positions and my_pages by indexed columns, stable core
 * tables. A weight is written as block_manager::reposition_block() writes a default page's
 * position (lib/blocklib.php, MOODLE_502_STABLE): the instance's defaultweight, and the default
 * page's own block_positions row when there is one. reposition_block() itself needs the page's
 * blocks loaded, which initialises a theme and output in a CLI run, so the write is made
 * directly and listed in the plugin README.
 */
class dashboard {

    /** Item type. */
    const TYPE = 'dashboard';

    /** Page type of the dashboard. */
    const PAGETYPE = 'my-index';

    /** The role whose editing must be prevented before a reset (R4). */
    const USER_ROLE = 'user';

    /** The capability that lets a user edit their own dashboard. */
    const MANAGEBLOCKS = 'moodle/my:manageblocks';

    /** The item name of the personal-dashboard count. */
    const PERSONAL = 'personal dashboards';

    /** @var array[] the payload's `dashboard` */
    protected $declared;

    /** @var bool the payload's `dashboard_complete` */
    protected $complete;

    /** @var string the payload's `dashboard_personal` */
    protected $personal;

    /** @var inspector|null reads the user role's live permissions */
    protected $inspector;

    /**
     * @param array[] $entries the payload's `dashboard`
     * @param bool $complete whether the list is the whole default page
     * @param string $personal 'reset' or 'keep'
     * @param inspector|null $inspector reads the live role permissions; without it a reset is refused
     */
    public function __construct(array $entries, bool $complete = false, string $personal = 'keep',
            ?inspector $inspector = null) {
        $this->declared = $entries;
        $this->complete = $complete;
        $this->personal = $personal;
        $this->inspector = $inspector;
    }

    /**
     * @return array[] item results in inspector's shape. WRITES NOTHING.
     */
    public function check(): array {
        $pageid = self::default_page_id();
        if (!$pageid) {
            $items = [];
            foreach ($this->declared as $entry) {
                $entry = (array)$entry;
                $items[] = self::result((string)$entry['block'], inspector::RESULT_UNKNOWN, $entry['region'], null,
                    'this site has no default dashboard page', true);
            }
            return $items;
        }
        $live = $this->live_blocks($pageid);
        $plan = $this->plan($live);
        $items = [];
        foreach ($this->declared as $entry) {
            $entry = (array)$entry;
            $block = (string)$entry['block'];
            if (self::find($plan['add'], $block)) {
                $items[] = self::result($block, inspector::RESULT_MISSING, $entry['region'], null, 'apply adds it');
            } else if ($move = self::find($plan['reweight'], $block)) {
                $items[] = self::weight_result($move);
            } else {
                $items[] = self::result($block, inspector::RESULT_OK, $entry['region'], $entry['region']);
            }
        }
        foreach ($plan['delete'] as $delete) {
            $items[] = self::extra_result($delete, $live);
        }
        if ($plan['reset'] || $plan['refusereset']) {
            $items[] = $this->personal_result($plan['refusereset']
                ? 'editing is still allowed for the user role; apply refuses the reset'
                : 'apply resets them to the default');
        }
        return $items;
    }

    /**
     * Make the default dashboard match the declaration, in the contract's order.
     *
     * @param report $report
     */
    public function apply(report $report): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/blocklib.php');
        require_once($CFG->dirroot . '/my/lib.php');
        $pageid = self::default_page_id();
        if (!$pageid) {
            foreach ($this->check() as $item) {
                $report->add_result($item);
            }
            return;
        }
        // Planned again here, never reused from check(): the roles step ran since.
        $live = $this->live_blocks($pageid);
        $plan = $this->plan($live);

        foreach ($this->declared as $entry) {
            $entry = (array)$entry;
            $block = (string)$entry['block'];
            if (!self::find($plan['add'], $block) && !self::find($plan['reweight'], $block)) {
                $report->add_result(self::result($block, inspector::RESULT_OK, $entry['region'], $entry['region']));
            }
        }

        // 1. Each missing block, at its planned weight.
        foreach ($plan['add'] as $add) {
            $item = self::result($add['block'], inspector::RESULT_MISSING, $add['region'], null, 'apply adds it');
            try {
                $page = new \moodle_page();
                $page->set_context(context_system::instance());
                // A bare page has no layout, so its block manager knows no region until one is
                // added, and add_block() refuses every region as unknown. Core adds a block to
                // the default dashboard the same way (blocks/timeline/db/install.php,
                // MOODLE_502_STABLE).
                $page->blocks->add_region($add['region']);
                $page->blocks->add_block($add['block'], $add['region'], $add['weight'], false,
                    self::PAGETYPE, (string)$pageid);
                $report->add_result($item, 'changed', 'added');
            } catch (\Throwable $e) {
                $report->add_result($item, 'fail', 'Moodle refused: ' . $e->getMessage());
            }
        }

        // 2. With complete, each undeclared instance.
        foreach ($plan['delete'] as $delete) {
            $item = self::extra_result($delete, $live);
            try {
                $instance = $DB->get_record('block_instances', ['id' => $delete['id']], '*', MUST_EXIST);
                blocks_delete_instance($instance);
                $report->add_result($item, 'changed', 'removed');
            } catch (\Throwable $e) {
                $report->add_result($item, 'fail', 'Moodle refused: ' . $e->getMessage());
            }
        }

        // 3. Each declared weight.
        foreach ($plan['reweight'] as $move) {
            $item = self::weight_result($move);
            try {
                self::set_weight($move['id'], $move['to'], $pageid);
                $report->add_result($item, 'changed', 'moved');
            } catch (\Throwable $e) {
                $report->add_result($item, 'fail', 'Moodle refused: ' . $e->getMessage());
            }
        }

        // 4. With reset, personal dashboards, only while editing is prevented live.
        if ($plan['refusereset']) {
            $report->add_result($this->personal_result(''), 'fail',
                'editing is still allowed for the user role; the reset is refused');
        } else if ($plan['reset']) {
            $item = $this->personal_result('');
            try {
                my_reset_page_for_all_users(MY_PAGE_PRIVATE, self::PAGETYPE);
                $report->add_result($item, 'changed', 'reset to the default');
            } catch (\Throwable $e) {
                $report->add_result($item, 'fail', 'Moodle refused: ' . $e->getMessage());
            }
        }
    }

    /**
     * How many personal dashboards exist: my_pages rows with a userid, name '__default' and
     * private 1. A count only; no user id leaves this method.
     *
     * @return int
     */
    public function personal_count(): int {
        global $DB;
        return $DB->count_records_select('my_pages', 'userid IS NOT NULL AND name = :name AND private = :private',
            ['name' => '__default', 'private' => 1]);
    }

    /**
     * Whether moodle/my:manageblocks is prevented for the user role at system context, read live.
     * False with no inspector or no user role, so a reset is refused.
     *
     * @return bool
     */
    public function editing_prevented(): bool {
        global $DB;
        if (!$this->inspector) {
            return false;
        }
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => self::USER_ROLE]);
        if (!$roleid) {
            return false;
        }
        return ($this->inspector->live_role_capabilities($roleid)[self::MANAGEBLOCKS] ?? null) === CAP_PREVENT;
    }

    /**
     * Every block instance on the default page, in any region, with its effective region and
     * weight: the default page's block_positions row when there is one, otherwise the
     * instance's defaults, as block_manager::load_blocks() resolves them.
     *
     * @param int $pageid the default page's my_pages id
     * @return array[] [{id, block, region, weight}], by id
     */
    public function live_blocks(int $pageid): array {
        global $DB;
        $syscontextid = context_system::instance()->id;
        $sql = "SELECT bi.id, bi.blockname,
                       COALESCE(bp.region, bi.defaultregion) AS region,
                       COALESCE(bp.weight, bi.defaultweight) AS weight
                  FROM {block_instances} bi
             LEFT JOIN {block_positions} bp ON bp.blockinstanceid = bi.id
                       AND bp.contextid = :bpcontextid
                       AND bp.pagetype = :bppagetype
                       AND bp.subpage = :bpsubpage
                 WHERE bi.parentcontextid = :contextid
                       AND bi.pagetypepattern = :pagetype
                       AND bi.subpagepattern = :subpage
              ORDER BY bi.id";
        $rows = $DB->get_records_sql($sql, ['bpcontextid' => $syscontextid, 'bppagetype' => self::PAGETYPE,
            'bpsubpage' => (string)$pageid, 'contextid' => $syscontextid, 'pagetype' => self::PAGETYPE,
            'subpage' => (string)$pageid]);
        $live = [];
        foreach ($rows as $row) {
            $live[] = ['id' => (int)$row->id, 'block' => (string)$row->blockname, 'region' => (string)$row->region,
                'weight' => (int)$row->weight];
        }
        return $live;
    }

    /**
     * @param array[] $live live_blocks() as read just now
     * @return array the plan dashboard_plan::plan() returns for it, with the live permission and count
     */
    protected function plan(array $live): array {
        return dashboard_plan::plan($this->declared, $live, $this->complete, $this->personal,
            $this->editing_prevented(), $this->personal_count());
    }

    /**
     * Set one instance's weight on the default page, as reposition_block() sets a default
     * position: the instance's defaultweight, and the default page's block_positions row when
     * there is one, since that row overrides it.
     *
     * @param int $instanceid
     * @param int $weight
     * @param int $pageid
     */
    protected static function set_weight(int $instanceid, int $weight, int $pageid): void {
        global $DB;
        $DB->update_record('block_instances', (object)['id' => $instanceid, 'defaultweight' => $weight,
            'timemodified' => time()]);
        $position = $DB->get_field('block_positions', 'id', ['blockinstanceid' => $instanceid,
            'contextid' => context_system::instance()->id, 'pagetype' => self::PAGETYPE, 'subpage' => (string)$pageid]);
        if ($position) {
            $DB->update_record('block_positions', (object)['id' => $position, 'weight' => $weight]);
        }
    }

    /**
     * @return int the system default dashboard's my_pages id, or 0
     */
    protected static function default_page_id(): int {
        global $DB;
        return (int)$DB->get_field('my_pages', 'id', ['userid' => null, 'name' => '__default', 'private' => 1],
            IGNORE_MULTIPLE);
    }

    /**
     * @param array[] $planned one list of the plan
     * @param string $block
     * @return array|null the planned entry for the block
     */
    protected static function find(array $planned, string $block): ?array {
        foreach ($planned as $entry) {
            if ($entry['block'] === $block) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @param array $move {id, block, from, to}
     * @return array
     */
    protected static function weight_result(array $move): array {
        $result = self::result($move['block'], inspector::RESULT_CHANGED, (string)$move['to'], (string)$move['from'],
            'apply moves it');
        $result['item'] .= ' weight';
        return $result;
    }

    /**
     * @param array $delete {id, block}
     * @param array[] $live live_blocks(), for the instance's region
     * @return array
     */
    protected static function extra_result(array $delete, array $live): array {
        $region = null;
        foreach ($live as $instance) {
            if ($instance['id'] === $delete['id']) {
                $region = $instance['region'];
            }
        }
        return self::result($delete['block'], inspector::RESULT_EXTRA, null, $region,
            'not declared; apply removes it');
    }

    /**
     * @param string $message
     * @return array
     */
    protected function personal_result(string $message): array {
        return self::result(self::PERSONAL, inspector::RESULT_CHANGED, 'reset', (string)$this->personal_count(),
            $message);
    }

    /**
     * @param string $block
     * @param string $result
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $block, string $result, $declared = null, $live = null,
            string $message = '', bool $blocking = false): array {
        return ['type' => self::TYPE, 'item' => self::TYPE . ' ' . $block, 'result' => $result,
            'declared' => $declared, 'live' => $live, 'message' => $message, 'secret' => false,
            'blocking' => $blocking];
    }
}
