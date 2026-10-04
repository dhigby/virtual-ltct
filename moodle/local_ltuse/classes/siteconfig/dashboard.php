<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use context_system;

/**
 * Blocks the system default dashboard carries (spec 011, FR-003, research R18).
 *
 * The declaration arrives as the payload's `dashboard`, [{block, region}], from
 * moodle/site/dashboard.yaml. The default dashboard is the my_pages row with userid null,
 * name '__default' and private 1 (my/lib.php MY_PAGE_DEFAULT); a block on it has page type
 * my-index and that row's id as its subpage, as blocks_add_default_system_blocks() places
 * core's own (lib/blocklib.php). A learner who has made the dashboard their own keeps it.
 *
 * Items: `dashboard <block>`, missing or ok. Additive: apply adds a missing block through
 * block_manager::add_block() and never removes one, and never calls
 * my_reset_page_for_all_users(), which would throw away everyone's own layout. The read is
 * block_instances by indexed columns, a stable core table.
 */
class dashboard {

    /** Item type. */
    const TYPE = 'dashboard';

    /** Page type of the dashboard. */
    const PAGETYPE = 'my-index';

    /** @var array[] the payload's `dashboard` */
    protected $declared;

    /**
     * @param array[] $declared
     */
    public function __construct(array $declared) {
        $this->declared = $declared;
    }

    /**
     * @return array[] item results in inspector's shape. WRITES NOTHING.
     */
    public function check(): array {
        $items = [];
        $pageid = self::default_page_id();
        foreach ($this->declared as $entry) {
            $entry = (array)$entry;
            $block = (string)$entry['block'];
            if (!$pageid) {
                $items[] = self::result($block, 'unknown', $entry['region'], null,
                    'this site has no default dashboard page', true);
            } else if (self::present($block, $pageid)) {
                $items[] = self::result($block, 'ok', $entry['region'], $entry['region']);
            } else {
                $items[] = self::result($block, 'missing', $entry['region'], null, 'apply adds it');
            }
        }
        return $items;
    }

    /**
     * Add every declared block missing from the default dashboard.
     *
     * @param report $report
     */
    public function apply(report $report): void {
        global $CFG;
        require_once($CFG->libdir . '/blocklib.php');
        $pageid = self::default_page_id();
        foreach ($this->check() as $i => $item) {
            if ($item['result'] !== 'missing') {
                $report->add_result($item);
                continue;
            }
            $entry = (array)$this->declared[$i];
            try {
                $page = new \moodle_page();
                $page->set_context(context_system::instance());
                $page->blocks->add_block((string)$entry['block'], (string)$entry['region'], 0, false,
                    self::PAGETYPE, (string)$pageid);
                $report->add_result($item, 'changed', 'added');
            } catch (\Throwable $e) {
                $report->add_result($item, 'fail', 'Moodle refused: ' . $e->getMessage());
            }
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
     * @param string $block
     * @param int $pageid
     * @return bool whether the block is on the default dashboard
     */
    protected static function present(string $block, int $pageid): bool {
        global $DB;
        return $DB->record_exists('block_instances', ['blockname' => $block,
            'parentcontextid' => context_system::instance()->id, 'pagetypepattern' => self::PAGETYPE,
            'subpagepattern' => (string)$pageid]);
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
