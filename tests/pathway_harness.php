<?php
// Harness for moodle/local_ltuse/classes/pathway/builder.php and viewer.php (spec 006, R6, R8;
// FR-003, FR-004, FR-008, FR-011, FR-012). Needs no Moodle: both classes are pure. Course and
// competency names are fixture-*, never real course content.
//
// Run locally:        php tests/pathway_harness.php
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$root = __DIR__ . '/../moodle/local_ltuse/classes';
foreach (['/organisation/access.php', '/pathway/builder.php', '/pathway/viewer.php'] as $file) {
    if (is_file($root . $file)) {
        require $root . $file;
    }
}
foreach (['local_ltuse\pathway\builder', 'local_ltuse\pathway\viewer', 'local_ltuse\organisation\access'] as $class) {
    if (!class_exists($class)) {
        echo "FAIL: $class not found\n";
        echo "FAILURES: 1\n";
        exit(1);
    }
}

use local_ltuse\pathway\builder;
use local_ltuse\pathway\viewer;

const LEVELS = [1 => '1 - Has Knowledge', 2 => '2 - With Assistance', 3 => '3 - Independent',
    4 => '4 - Expert'];
const COMPURL = 'https://example.org/competencies/fixture-cat/fixture-comp/';

function comp(string $slug = 'fixture-comp'): array {
    return ['key' => "competency:$slug", 'name' => "Fixture $slug", 'url' => COMPURL];
}
function course(int $id, string $name, int $level): array {
    return ['courseid' => $id, 'fullname' => $name, 'url' => "https://example.org/course/view.php?id=$id",
        'level' => $level];
}
/** @return string[] full names in a row, in order */
function names(array $row): array {
    return array_map(function($c) { return $c['fullname']; }, $row['courses']);
}
/** @return int[] courseids marked next anywhere in a competency context */
function nextids(array $view): array {
    $ids = [];
    foreach ($view['levels'] as $row) {
        foreach ($row['courses'] as $c) {
            if ($c['next']) {
                $ids[] = $c['courseid'];
            }
        }
    }
    return $ids;
}
/** Every key, at any depth, of a context. */
function allkeys(array $a): array {
    $keys = [];
    foreach ($a as $k => $v) {
        $keys[] = (string)$k;
        if (is_array($v)) {
            $keys = array_merge($keys, allkeys($v));
        }
    }
    return $keys;
}

// --- competency pathway: level layout ---------------------------------------------------
$courses = [course(1, 'Fixture Beta', 1), course(2, 'fixture alpha', 1), course(3, 'Fixture 10', 3),
    course(4, 'Fixture 9', 3)];
$v = builder::competency(comp(), LEVELS, $courses, []);
check($v['kind'] === 'competency', 'kind is competency');
check($v['key'] === 'competency:fixture-comp' && $v['title'] === 'Fixture fixture-comp', 'key and title');
check(array_column($v['levels'], 'level') === [1, 2, 3, 4], 'four level rows, 1 to 4 in order');
check(array_column($v['levels'], 'label') === array_values(LEVELS), 'row labels verbatim from the level labels');
check(names($v['levels'][0]) === ['fixture alpha', 'Fixture Beta'], 'two courses at one level, by full name, case-insensitive');
check(names($v['levels'][2]) === ['Fixture 9', 'Fixture 10'], 'full names sort naturally');
check($v['levels'][0]['courses'][0]['url'] === 'https://example.org/course/view.php?id=2', 'each course links to its course page');
$tie = builder::competency(comp(), LEVELS, [course(8, 'Fixture Same', 2), course(5, 'Fixture Same', 2)], []);
check(array_column($tie['levels'][1]['courses'], 'courseid') === [5, 8], 'same full name: by course id');
$r = builder::competency(comp(), LEVELS, [course(1, 'Fixture A', 0), course(2, 'Fixture B', 5),
    course(3, 'Fixture C', 2)], []);
check($r['total'] === 1 && names($r['levels'][1]) === ['Fixture C'], 'a course aiming at no level 1-4 is left out');
$r = builder::competency(comp(), LEVELS, [course(1, 'Fixture A', 2), course(1, 'Fixture A', 3)], []);
check($r['total'] === 1 && names($r['levels'][1]) === ['Fixture A'] && !$r['levels'][2]['courses'],
    'a repeated course id is listed once, at its first level');

// --- no-course rows -----------------------------------------------------------------------
check(!$v['levels'][0]['nocourseyet'] && $v['levels'][0]['competencyurl'] === null,
    'a row with courses: not nocourseyet, no competency link');
check($v['levels'][1]['nocourseyet'] && $v['levels'][1]['courses'] === [], 'a row with no course: nocourseyet');
check($v['levels'][1]['competencyurl'] === COMPURL && $v['levels'][3]['competencyurl'] === COMPURL,
    'a row with no course links to the competency page');
$empty = builder::competency(comp(), LEVELS, [], []);
check(array_column($empty['levels'], 'nocourseyet') === [true, true, true, true], 'no courses: all four rows say no course yet');
check($empty['nextcourse'] === null && $empty['done'] === false && $empty['total'] === 0,
    'no courses: no next course, and not done');

// --- next --------------------------------------------------------------------------------
check($v['nextcourse']['courseid'] === 2 && nextids($v) === [2], 'nothing started: next is the first course in level then name order');
$s = [2 => 'completed', 1 => 'completed'];
$n = builder::competency(comp(), LEVELS, $courses, $s);
check($n['nextcourse']['courseid'] === 4 && nextids($n) === [4], 'level 1 done: next is the first course of the next level with courses');
$n = builder::competency(comp(), LEVELS, $courses, [2 => 'completed', 1 => 'inprogress', 4 => 'completed']);
check($n['nextcourse']['courseid'] === 1 && nextids($n) === [1], 'next is the first not completed, in progress or not');
$n = builder::competency(comp(), LEVELS, $courses, [4 => 'completed']);
check($n['nextcourse']['courseid'] === 2, 'a completed later course does not move next past an earlier one');
check($n['nextcourse']['next'] === true, 'nextcourse carries next = true');
check(count(nextids($n)) === 1, 'exactly one course is next');

// --- statuses ----------------------------------------------------------------------------
$st = builder::competency(comp(), LEVELS, $courses, [1 => 'inprogress', 2 => 'completed', 3 => 'bogus']);
$bystatus = [];
foreach ($st['levels'] as $row) {
    foreach ($row['courses'] as $c) {
        $bystatus[$c['courseid']] = $c['status'];
    }
}
check($bystatus === [2 => 'completed', 1 => 'inprogress', 4 => 'notstarted', 3 => 'notstarted'],
    'statuses carried; missing or unknown status is notstarted');
check($st['completed'] === 1 && $st['total'] === 4, 'completed and total counts');
$st = builder::competency(comp(), LEVELS, [course(1, 'Fixture A', 1)], [1 => 'COMPLETED']);
check($st['completed'] === 0 && $st['done'] === false, 'status compares exactly: COMPLETED is not completed');

// --- done --------------------------------------------------------------------------------
$all = [1 => 'completed', 2 => 'completed', 3 => 'completed', 4 => 'completed'];
$d = builder::competency(comp(), LEVELS, $courses, $all);
check($d['done'] === true && $d['nextcourse'] === null && nextids($d) === [], 'every course completed: done, no next');
check($d['completed'] === 4 && $d['total'] === 4, 'done: 4 of 4');
$d = builder::competency(comp(), LEVELS, $courses, [1 => 'completed', 2 => 'completed', 3 => 'completed']);
check($d['done'] === false, 'one course left: not done');
// FR-011 / R13: no level for the learner anywhere in the context.
$keys = array_unique(array_merge(allkeys($d), allkeys(builder::competency(comp(), LEVELS, $courses, $all))));
check(!array_intersect($keys, ['learnerlevel', 'userlevel', 'currentlevel', 'reachedlevel', 'achievedlevel']),
    'the context holds no level for the learner');
check(!isset($d['level']) && !isset($d['nextcourse']['level']), 'no top-level level, and the next course names none');

// --- level labels --------------------------------------------------------------------------
$threw = false;
try {
    builder::competency(comp(), [1 => 'a', 2 => 'b', 3 => 'c'], $courses, []);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
check($threw, 'a missing level label is refused');
$threw = false;
try {
    builder::competency(comp(), [1 => 'a', 2 => '  ', 3 => 'c', 4 => 'd'], $courses, []);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
check($threw, 'an empty level label is refused');

// --- a course in two competencies; role totals counted once ------------------------------
$shared = course(7, 'Fixture Shared', 2);
$ca = builder::competency(comp('fixture-a'), LEVELS, [course(5, 'Fixture A only', 1), $shared],
    [7 => 'completed']);
$cb = builder::competency(comp('fixture-b'), LEVELS, [$shared, course(6, 'Fixture B only', 3)],
    [7 => 'completed']);
check(names($ca['levels'][1]) === ['Fixture Shared'] && names($cb['levels'][1]) === ['Fixture Shared'],
    'a course in two competencies is listed under each');
check($ca['levels'][1]['courses'][0]['status'] === 'completed' && $cb['levels'][1]['courses'][0]['status'] === 'completed',
    'its status is the same in both');
$role = builder::role(['key' => 'role:fixture-role', 'name' => 'Fixture role', 'description' => 'Fixture purpose'],
    [$ca, $cb]);
check($role['kind'] === 'role' && $role['key'] === 'role:fixture-role' && $role['title'] === 'Fixture role'
    && $role['description'] === 'Fixture purpose', 'role key, title and description');
check(array_column($role['competencies'], 'key') === ['competency:fixture-a', 'competency:fixture-b'],
    'role competencies in declared order');
check($role['total'] === 3, 'role total counts a shared course once (3, not 4)');
check($role['completed'] === 1, 'role completed counts a shared course once');
check($role['done'] === false, 'role not done while a course is left');
$rdone = builder::role(['key' => 'role:fixture-role', 'name' => 'Fixture role'], [
    builder::competency(comp('fixture-a'), LEVELS, [course(5, 'Fixture A only', 1), $shared], [5 => 'completed', 7 => 'completed']),
    builder::competency(comp('fixture-b'), LEVELS, [$shared, course(6, 'Fixture B only', 3)], [6 => 'completed', 7 => 'completed']),
]);
check($rdone['done'] === true && $rdone['completed'] === 3 && $rdone['total'] === 3, 'role done when every distinct course is completed');
check($rdone['description'] === '', 'role with no description: empty string');
$rempty = builder::role(['key' => 'role:fixture-empty', 'name' => 'Fixture empty'], []);
check($rempty['total'] === 0 && $rempty['done'] === false && $rempty['competencies'] === [],
    'role with no competencies: 0 courses, not done');
$rnone = builder::role(['key' => 'role:fixture-r', 'name' => 'Fixture r'], [$empty, $empty]);
check($rnone['total'] === 0 && $rnone['done'] === false, 'role whose competencies have no course: not done');

// --- may_view ------------------------------------------------------------------------------
const V = 10;
const L = 20;
function person(array $over = []): array {
    return array_merge(['id' => L, 'ltct_org' => 'fixture-a', 'org_cohorts' => ['fixture-a']], $over);
}
function facts(array $over = []): array {
    return array_merge(['mentor' => false, 'siteconfig' => false, 'managedkeys' => [], 'person' => person()], $over);
}

check(viewer::may_view(L, L, []), 'a learner sees their own pathways, with no facts');
check(viewer::may_view(L, L, facts()), 'a learner sees their own pathways');
check(!viewer::may_view(V, L, facts()), 'someone with no relationship: no');
check(!viewer::may_view(V, L, []), 'no facts at all: no');
check(viewer::may_view(V, L, facts(['mentor' => true])), 'a mentor of the learner: yes');
check(viewer::may_view(V, L, facts(['siteconfig' => true])), 'the site team (site:config): yes');
check(viewer::may_view(V, L, facts(['managedkeys' => ['fixture-a']])), 'manager of the learner\'s organisation: yes');
check(viewer::may_view(V, L, facts(['managedkeys' => ['fixture-b', 'fixture-a']])), 'manager of two organisations, the learner\'s second');
check(!viewer::may_view(V, L, facts(['managedkeys' => ['fixture-b']])), 'manager of another organisation: no');
check(!viewer::may_view(V, L, facts(['managedkeys' => ['fixture-a'], 'person' => person(['org_cohorts' => []])])),
    'organisation field set but not in its cohort: no (never the field alone)');
check(!viewer::may_view(V, L, facts(['managedkeys' => ['fixture-a'],
    'person' => person(['ltct_org' => 'fixture-b'])])), 'field and cohort disagree: no');
check(!viewer::may_view(V, L, facts(['managedkeys' => ['fixture-a'], 'person' => person(['id' => 99])])),
    'person facts about someone else: no');
check(!viewer::may_view(0, L, facts(['siteconfig' => true])), 'viewer id 0 (not logged in): no, even with site:config');
check(!viewer::may_view(-1, L, facts(['mentor' => true])), 'negative viewer id: no');
check(!viewer::may_view(V, 0, facts(['siteconfig' => true])), 'learner id 0: no');
check(!viewer::may_view(0, 0, []), 'nobody viewing nobody: no');

// Missing or ill-typed facts fail closed.
check(!viewer::may_view(V, L, facts(['mentor' => 1])), 'mentor fact 1, not true: no');
check(!viewer::may_view(V, L, facts(['mentor' => 'yes'])), 'mentor fact a string: no');
check(!viewer::may_view(V, L, facts(['siteconfig' => 1])), 'siteconfig fact 1, not true: no');
$f = facts(['managedkeys' => ['fixture-a']]);
unset($f['managedkeys']);
check(!viewer::may_view(V, L, $f), 'missing fact managedkeys: no');
$f = facts(['managedkeys' => ['fixture-a']]);
unset($f['person']);
check(!viewer::may_view(V, L, $f), 'missing fact person: no');
check(!viewer::may_view(V, L, facts(['managedkeys' => 'fixture-a'])), 'managedkeys not an array: no');
check(!viewer::may_view(V, L, facts(['managedkeys' => ['fixture-a'], 'person' => 'L'])), 'person not an array: no');
foreach (['id', 'ltct_org', 'org_cohorts'] as $k) {
    $p = person();
    unset($p[$k]);
    check(!viewer::may_view(V, L, facts(['managedkeys' => ['fixture-a'], 'person' => $p])),
        "missing person fact '$k': no");
}
$f = facts(['siteconfig' => true]);
unset($f['mentor'], $f['managedkeys'], $f['person']);
check(viewer::may_view(V, L, $f), 'site:config alone suffices with other facts missing');
$f = facts(['mentor' => true]);
unset($f['siteconfig'], $f['managedkeys'], $f['person']);
check(viewer::may_view(V, L, $f), 'mentor alone suffices with other facts missing');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
