<?php
// Harness for moodle/local_ltuse/classes/siteconfig/dashboard_plan.php (spec 007,
// contracts/dashboard-declaration.md): what apply must change on the default Dashboard page to
// match dashboard.yaml. Needs no Moodle; nothing here reads a database.
//
// Run locally:   php tests/dashboard_plan_harness.php
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/siteconfig/dashboard_plan.php';
if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\siteconfig\dashboard_plan') ||
        !method_exists('local_ltuse\siteconfig\dashboard_plan', 'plan')) {
    echo "FAIL: local_ltuse\\siteconfig\\dashboard_plan::plan() not found\n";
    echo "FAILURES: 1\n";
    exit(1);
}

use local_ltuse\siteconfig\dashboard_plan as plan;

// The tracked declaration's shape: weight only where it is declared.
$declared = [
    ['block' => 'ltuse', 'region' => 'content', 'weight' => 0],
    ['block' => 'myoverview', 'region' => 'content', 'weight' => 1],
    ['block' => 'calendar_upcoming', 'region' => 'side-pre'],
];
function live(int $id, string $block, string $region, int $weight): array {
    return ['id' => $id, 'block' => $block, 'region' => $region, 'weight' => $weight];
}
/** The live set as it stands once every add in $plan has been made, ids from 100. */
function after_adds(array $live, array $plan): array {
    $id = 100;
    foreach ($plan['add'] as $add) {
        $live[] = live($id++, $add['block'], $add['region'], $add['weight']);
    }
    return $live;
}
function nothing(array $p): bool {
    return $p['add'] === [] && $p['delete'] === [] && $p['reweight'] === [] && $p['reset'] === false
        && $p['refusereset'] === false;
}
$exact = [live(1, 'ltuse', 'content', 0), live(2, 'myoverview', 'content', 1), live(3, 'calendar_upcoming', 'side-pre', 0)];

// The shape every call returns.
$p = plan::plan($declared, $exact, true, 'keep', true, 0);
check(array_keys($p) === ['add', 'delete', 'reweight', 'reset', 'refusereset'],
    'plan() returns add, delete, reweight, reset and refusereset');

// (a) Nothing live: three adds, each at its declared weight (0 when undeclared). Applying them
// leaves nothing to do.
$p = plan::plan($declared, [], true, 'keep', true, 0);
check($p['add'] === [['block' => 'ltuse', 'region' => 'content', 'weight' => 0],
    ['block' => 'myoverview', 'region' => 'content', 'weight' => 1],
    ['block' => 'calendar_upcoming', 'region' => 'side-pre', 'weight' => 0]],
    '(a) nothing live adds the three blocks at their declared weights');
check($p['delete'] === [] && $p['reweight'] === [], '(a) nothing to delete or reweight');
check(nothing(plan::plan($declared, after_adds([], $p), true, 'keep', true, 0)),
    '(a) a second plan over what the adds made is empty');

// (b) Live equal to declared: nothing to do (idempotent).
check(nothing(plan::plan($declared, $exact, true, 'keep', true, 0)), '(b) live as declared plans nothing');
check(nothing(plan::plan($declared, $exact, false, 'keep', true, 0)), '(b) ... with complete off too');

// (c) complete=false: an undeclared block stays (011's additive rule).
$p = plan::plan($declared, array_merge($exact, [live(4, 'timeline', 'content', 2)]), false, 'keep', true, 0);
check($p['delete'] === [], '(c) without complete, timeline is never deleted');

// (d) complete=true: every undeclared block on the default page goes, in any region.
$live = array_merge($exact, [live(4, 'timeline', 'content', 2), live(5, 'calendar_month', 'side-post', 0),
    live(6, 'recentlyaccesseditems', 'side-post', 1)]);
$p = plan::plan($declared, $live, true, 'keep', true, 0);
check($p['delete'] === [['id' => 4, 'block' => 'timeline'], ['id' => 5, 'block' => 'calendar_month'],
    ['id' => 6, 'block' => 'recentlyaccesseditems']], '(d) complete deletes timeline, calendar_month and recentlyaccesseditems');
check($p['add'] === [] && $p['reweight'] === [], '(d) and adds or reweights nothing');

// (e) Two live myoverview with complete=true: the lower weight is kept, then the lower id.
$p = plan::plan($declared, [live(1, 'ltuse', 'content', 0), live(7, 'myoverview', 'content', 1),
    live(2, 'myoverview', 'content', 3), live(3, 'calendar_upcoming', 'side-pre', 0)], true, 'keep', true, 0);
check($p['delete'] === [['id' => 2, 'block' => 'myoverview']], '(e) the duplicate at the higher weight is deleted');
check($p['reweight'] === [], '(e) the one kept is already at its weight');
$p = plan::plan($declared, [live(1, 'ltuse', 'content', 0), live(9, 'myoverview', 'content', 1),
    live(8, 'myoverview', 'content', 1), live(3, 'calendar_upcoming', 'side-pre', 0)], true, 'keep', true, 0);
check($p['delete'] === [['id' => 9, 'block' => 'myoverview']], '(e) at the same weight, the higher id is deleted');

// (f) myoverview live at 0, declared 1: one reweight.
$p = plan::plan($declared, [live(1, 'ltuse', 'content', 0), live(2, 'myoverview', 'content', 0),
    live(3, 'calendar_upcoming', 'side-pre', 0)], true, 'keep', true, 0);
check($p['reweight'] === [['id' => 2, 'block' => 'myoverview', 'from' => 0, 'to' => 1]], '(f) myoverview is reweighted 0 to 1');
check($p['add'] === [] && $p['delete'] === [], '(f) nothing added or deleted');

// (g) A declared block without a weight is never reweighted.
$p = plan::plan($declared, [live(1, 'ltuse', 'content', 0), live(2, 'myoverview', 'content', 1),
    live(3, 'calendar_upcoming', 'side-pre', 7)], true, 'keep', true, 0);
check($p['reweight'] === [], '(g) calendar_upcoming at 7, with no declared weight, is left where it is');

// (h)-(k), (m): personal dashboards.
$p = plan::plan($declared, $exact, true, 'reset', true, 6);
check($p['reset'] === true && $p['refusereset'] === false, '(h) reset, 6 personal dashboards, editing prevented: reset');
$p = plan::plan($declared, $exact, true, 'reset', true, 0);
check($p['reset'] === false && $p['refusereset'] === false, '(i) reset with none to reset plans nothing');
$p = plan::plan($declared, $exact, true, 'reset', false, 6);
check($p['reset'] === false && $p['refusereset'] === true, '(j) reset while editing is allowed is refused');
$p = plan::plan($declared, $exact, true, 'keep', false, 6);
check($p['reset'] === false && $p['refusereset'] === false, '(k) keep never resets or refuses');
$p = plan::plan($declared, $exact, true, 'keep', true, 6);
check($p['reset'] === false && $p['refusereset'] === false, '(k) keep never resets, even when prevented');

// (l) The region of a live instance is not compared (011's present()).
$p = plan::plan($declared, [live(1, 'ltuse', 'content', 0), live(2, 'myoverview', 'side-pre', 1),
    live(3, 'calendar_upcoming', 'side-pre', 0)], true, 'keep', true, 0);
check(nothing($p), '(l) myoverview live in side-pre at its weight: no add, delete or reweight');
// (l2) ... and at another weight: still left alone, since a weight orders a block in its region.
$p = plan::plan($declared, [live(1, 'ltuse', 'content', 0), live(2, 'myoverview', 'side-pre', 3),
    live(3, 'calendar_upcoming', 'side-pre', 0)], true, 'keep', true, 0);
check(nothing($p), '(l2) myoverview live in side-pre at weight 3, declared content/1: not reweighted');

// (m) reset with a count of 0 while editing is allowed: neither reset nor refused.
$p = plan::plan($declared, $exact, true, 'reset', false, 0);
check($p['reset'] === false && $p['refusereset'] === false, '(m) reset, none to reset, not prevented: no line');

// (n) Only myoverview live, at 0: ltuse and calendar_upcoming are added, myoverview moves to 1.
$p = plan::plan($declared, [live(2, 'myoverview', 'content', 0)], true, 'keep', true, 0);
check($p['add'] === [['block' => 'ltuse', 'region' => 'content', 'weight' => 0],
    ['block' => 'calendar_upcoming', 'region' => 'side-pre', 'weight' => 0]], '(n) ltuse and calendar_upcoming are added');
check($p['reweight'] === [['id' => 2, 'block' => 'myoverview', 'from' => 0, 'to' => 1]], '(n) myoverview is reweighted 0 to 1');
check($p['delete'] === [], '(n) nothing is deleted');

// (o) complete=false with two live myoverview: neither is deleted.
$p = plan::plan($declared, [live(1, 'ltuse', 'content', 0), live(2, 'myoverview', 'content', 1),
    live(4, 'myoverview', 'content', 2), live(3, 'calendar_upcoming', 'side-pre', 0)], false, 'keep', true, 0);
check($p['delete'] === [], '(o) without complete, a duplicate is never deleted');

// Live weights may arrive as database strings.
$p = plan::plan($declared, [live(1, 'ltuse', 'content', 0), ['id' => '2', 'block' => 'myoverview', 'region' => 'content',
    'weight' => '1'], live(3, 'calendar_upcoming', 'side-pre', 0)], true, 'keep', true, 0);
check(nothing($p), 'a live weight read as a string compares as a number');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
