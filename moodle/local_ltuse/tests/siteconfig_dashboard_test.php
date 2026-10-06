<?php
// PHPUnit tests for the default dashboard step of site_config apply (spec 007,
// specs/007-learner-experience/contracts/dashboard-declaration.md).
//
// Synthetic data only, in the PHPUnit database: every user and course here is a generated
// fixture, never a real learner. Never run on the shared host; it runs in plugin CI. Where this
// runs is spec 004's plan.md "Testing".

namespace local_ltuse;

use context_course;
use context_system;
use context_user;
use local_ltuse\siteconfig\report;

/**
 * dashboard::check() and apply() make the default Dashboard page match the declaration: adding
 * at the declared weight, removing what is undeclared only with `complete`, setting weights, and
 * resetting personal dashboards only while editing is prevented live. Nothing outside the
 * default page is touched.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\siteconfig\dashboard
 */
final class siteconfig_dashboard_test extends \advanced_testcase {

    /** Core blocks only: block_ltuse is not installed in this plugin's CI job. */
    private const ENTRIES = [
        ['block' => 'myoverview', 'region' => 'content', 'weight' => 1],
        ['block' => 'calendar_upcoming', 'region' => 'side-pre', 'weight' => 2],
    ];

    /** @var int the default dashboard's my_pages id */
    private $pageid;

    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        require_once($CFG->libdir . '/blocklib.php');
        require_once($CFG->dirroot . '/my/lib.php');
        $this->resetAfterTest();
        $this->pageid = (int)$DB->get_field('my_pages', 'id', ['userid' => null, 'name' => '__default',
            'private' => 1], MUST_EXIST);
    }

    /**
     * The dashboard step as the inspector builds it, from a payload.
     *
     * @param bool $complete
     * @param string $personal
     * @return siteconfig\dashboard
     */
    private function dashboard(bool $complete, string $personal = 'keep'): siteconfig\dashboard {
        $declaration = ['dashboard' => self::ENTRIES, 'dashboard_complete' => $complete,
            'dashboard_personal' => $personal];
        return new siteconfig\dashboard(self::ENTRIES, $complete, $personal, new siteconfig\inspector($declaration));
    }

    /**
     * Run apply and return what it reported.
     *
     * @param siteconfig\dashboard $dashboard
     * @return array[] the report's items
     */
    private function apply(siteconfig\dashboard $dashboard): array {
        $report = new report('apply', true, function() {
        });
        $dashboard->apply($report);
        return $report->items();
    }

    /**
     * @param array[] $items
     * @param string $status
     * @return string[] the item names reported with that status
     */
    private static function named(array $items, string $status): array {
        return array_values(array_map(function($i) {
            return $i['item'];
        }, array_filter($items, function($i) use ($status) {
            return $i['status'] === $status;
        })));
    }

    /**
     * @return array block name => weight, for every instance on the default page
     */
    private function default_page(): array {
        $out = [];
        foreach ($this->dashboard(false)->live_blocks($this->pageid) as $instance) {
            $out[$instance['block']] = $instance['weight'];
        }
        ksort($out);
        return $out;
    }

    /**
     * A generated user with a dashboard of their own and a __courses row.
     *
     * @return \stdClass the user
     */
    private function user_with_own_dashboard(): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->assertNotFalse(my_copy_page($user->id, MY_PAGE_PRIVATE));
        $DB->insert_record('my_pages', ['userid' => $user->id, 'name' => MY_PAGE_COURSES, 'private' => MY_PAGE_PUBLIC,
            'sortorder' => 0]);
        return $user;
    }

    public function test_without_complete_nothing_is_extra(): void {
        $items = $this->dashboard(false)->check();
        $this->assertSame([], array_values(array_filter($items, function($i) {
            return $i['result'] === 'extra';
        })));
        $this->assertContains('dashboard calendar_upcoming', array_column(array_filter($items, function($i) {
            return $i['result'] === 'missing';
        }), 'item'));
    }

    public function test_complete_clears_only_the_default_page(): void {
        global $DB;
        // A block added to side-post on the default page, one on a course page, and a user's own page.
        $this->getDataGenerator()->create_block('online_users', ['parentcontextid' => context_system::instance()->id,
            'pagetypepattern' => 'my-index', 'subpagepattern' => (string)$this->pageid, 'defaultregion' => 'side-post',
            'defaultweight' => 3]);
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        $this->getDataGenerator()->create_block('online_users', ['parentcontextid' => $coursecontext->id,
            'pagetypepattern' => 'course-view-*']);
        $user = $this->user_with_own_dashboard();
        $usercontext = context_user::instance($user->id);
        $ownblocks = $DB->count_records('block_instances', ['parentcontextid' => $usercontext->id]);
        $this->assertGreaterThan(0, $ownblocks);
        $this->assertArrayHasKey('timeline', $this->default_page());

        $items = $this->apply($this->dashboard(true));
        $this->assertContains('dashboard timeline', self::named($items, 'changed'));
        $this->assertContains('dashboard online_users', self::named($items, 'changed'));
        $this->assertSame(['calendar_upcoming', 'myoverview'], array_keys($this->default_page()));
        $this->assertSame($ownblocks, $DB->count_records('block_instances', ['parentcontextid' => $usercontext->id]));
        $this->assertTrue($DB->record_exists('block_instances', ['parentcontextid' => $coursecontext->id,
            'blockname' => 'online_users']));
    }

    public function test_weights_are_set_and_added_blocks_carry_theirs(): void {
        // Install puts myoverview at 0; the declaration wants 1.
        $this->assertSame(0, $this->default_page()['myoverview']);
        $items = $this->apply($this->dashboard(false));
        $this->assertContains('dashboard myoverview weight', self::named($items, 'changed'));
        $this->assertContains('dashboard calendar_upcoming', self::named($items, 'changed'));
        $page = $this->default_page();
        $this->assertSame(1, $page['myoverview']);
        $this->assertSame(2, $page['calendar_upcoming']);
    }

    public function test_only_private_default_pages_are_counted(): void {
        $this->user_with_own_dashboard();
        $this->assertSame(1, $this->dashboard(false, 'reset')->personal_count());
    }

    public function test_no_reset_while_editing_is_allowed(): void {
        global $DB;
        $user = $this->user_with_own_dashboard();
        $before = $DB->count_records('my_pages', ['userid' => $user->id]);
        $items = $this->apply($this->dashboard(false, 'reset'));
        $this->assertContains('dashboard personal dashboards', self::named($items, 'fail'));
        $this->assertSame($before, $DB->count_records('my_pages', ['userid' => $user->id]));
    }

    public function test_the_reset_runs_once_editing_is_prevented(): void {
        global $DB;
        $user = $this->user_with_own_dashboard();
        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        // Overwrite, because install gives the user archetype an allow at system context.
        assign_capability('moodle/my:manageblocks', CAP_PREVENT, $roleid, context_system::instance()->id, true);

        $items = $this->apply($this->dashboard(true, 'reset'));
        $this->assertContains('dashboard personal dashboards', self::named($items, 'changed'));
        $this->assertFalse($DB->record_exists('my_pages', ['userid' => $user->id, 'name' => MY_PAGE_DEFAULT]));
        $this->assertTrue($DB->record_exists('my_pages', ['userid' => $user->id, 'name' => MY_PAGE_COURSES]));
        $this->assertSame(0, $this->dashboard(true, 'reset')->personal_count());

        // A second apply finds nothing to do.
        $again = $this->apply($this->dashboard(true, 'reset'));
        $this->assertSame([], self::named($again, 'changed'));
        $this->assertSame([], self::named($again, 'fail'));
    }
}
