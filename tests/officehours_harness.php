<?php
// Harness for moodle/local_ltuse/classes/officehours_plan.php (spec 011, research R16): the
// changes the office-hours course needs to match the mentor relationships. Needs no Moodle.
//
// Run locally:   php tests/officehours_harness.php
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/officehours_plan.php';
if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\officehours_plan')) {
    echo "FAIL: local_ltuse\\officehours_plan not found\nFAILURES: 1\n";
    exit(1);
}

use local_ltuse\officehours_plan as plan;

$t = plan::MENTOR_ROLE;
$s = plan::LEARNER_ROLE;
$active = function(array $roles) { return ['active' => true, 'roles' => $roles]; };

// Nothing yet: a new pair needs a group, two members and two enrolments.
$d = plan::diff([[9, 21]], [], [], []);
check($d['creategroups'] === [9], 'a new mentor gets a group');
check($d['add'] === [[9, 9], [9, 21]], 'the mentor and the learner join it');
check($d['enrol'] === [[9, $t], [21, $s]], 'the mentor is enrolled as teacher, the learner as student');
check(!$d['remove'] && !$d['suspend'], 'nothing is removed or suspended');

// Everything in place: no change.
$d = plan::diff([[9, 21]], [9], [9 => [9, 21]], [9 => $active([$t]), 21 => $active([$s])]);
check(array_sum(plan::counts($d)) === 0, 'in step: no change');

// The relationship ends: the learner leaves the group and is suspended; so is the mentor.
$d = plan::diff([], [9], [9 => [9, 21]], [9 => $active([$t]), 21 => $active([$s])]);
check($d['remove'] === [[9, 9], [9, 21]], 'both leave the group');
check($d['suspend'] === [9, 21], 'both are suspended, never unenrolled');
check(!$d['creategroups'], 'the group is never deleted or recreated');

// One of two mentees leaves: only they go; the mentor stays.
$d = plan::diff([[9, 21]], [9], [9 => [9, 21, 22]], [9 => $active([$t]), 21 => $active([$s]), 22 => $active([$s])]);
check($d['remove'] === [[9, 22]] && $d['suspend'] === [22], 'the leaving mentee alone is removed and suspended');

// A learner with two mentors is in two groups.
$d = plan::diff([[9, 21], [10, 21]], [9], [9 => [9, 21]], [9 => $active([$t]), 21 => $active([$s])]);
check($d['creategroups'] === [10], 'the second mentor gets a group');
check($d['add'] === [[10, 10], [10, 21]], 'the learner joins the second group too');
check($d['enrol'] === [[10, $t]], 'only the new mentor is enrolled');

// A suspended person who comes back is reactivated.
$d = plan::diff([[9, 21]], [9], [9 => [9]], [9 => $active([$t]), 21 => ['active' => false, 'roles' => [$s]]]);
check($d['reactivate'] === [21] && $d['add'] === [[9, 21]], 'a returning learner is reactivated and re-added');

// Someone who is both mentor and mentee holds both roles.
$d = plan::diff([[9, 21], [21, 30]], [9, 21], [9 => [9, 21], 21 => [21, 30]],
    [9 => $active([$t]), 21 => $active([$s]), 30 => $active([$s])]);
check($d['addrole'] === [[21, $t]], 'a mentee who also mentors gains the teacher role');
$d = plan::diff([[9, 21]], [9, 21], [9 => [9, 21]], [9 => $active([$t]), 21 => $active([$s, $t])]);
check($d['removerole'] === [[21, $t]], 'and loses it when they no longer mentor');

// A hand-removed member is put back; a stray local_ltuse member is taken out.
$d = plan::diff([[9, 21]], [9], [9 => [9]], [9 => $active([$t]), 21 => $active([$s])]);
check($d['add'] === [[9, 21]], 'a hand-removed member is re-added');
$d = plan::diff([[9, 21]], [9], [9 => [9, 21, 40]], [9 => $active([$t]), 21 => $active([$s])]);
check($d['remove'] === [[9, 40]], 'a member no relationship explains is removed');

// Malformed pairs are ignored.
$d = plan::diff([[9, 9], [0, 21], [9, 0]], [], [], []);
check(array_sum(plan::counts($d)) === 0, 'self, zero and missing ids change nothing');

// counts() holds numbers only.
$c = plan::counts(plan::diff([[9, 21]], [], [], []));
check($c['enrol'] === 2 && $c['add'] === 2 && is_int($c['creategroups']), 'counts are numbers, never ids');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
