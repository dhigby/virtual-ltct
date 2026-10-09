<?php
// Harness for moodle/local_ltuse/classes/calendar_notify.php (spec 011, FR-006, research
// R15): which calendar changes are announced, the key a series shares, and how a request's
// events fold into one notice. Needs no Moodle.
//
// Run locally:   php tests/calendar_notify_harness.php
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/calendar_notify.php';
if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\calendar_notify')) {
    echo "FAIL: local_ltuse\\calendar_notify not found\nFAILURES: 1\n";
    exit(1);
}

use local_ltuse\calendar_notify as cn;

function ev(array $fields = []): stdClass {
    return (object)array_merge(['id' => 41, 'name' => 'Kick-off call', 'eventtype' => 'course',
        'courseid' => 7, 'groupid' => 0, 'repeatid' => 0, 'modulename' => '', 'component' => null,
        'subscriptionid' => null, 'visible' => 1, 'timestart' => 1800000000], $fields);
}
$other = ['repeatid' => 0, 'name' => 'Kick-off call', 'timestart' => 1800000000];

// decide(): what is announced.
check(cn::decide(ev(), $other, false, false), 'a course event edited: announced');
check(cn::decide(ev(), $other, true, false), 'a course event deleted: announced');
check(cn::decide(ev(['eventtype' => 'site', 'courseid' => 1]), $other, false, false), 'a site event: announced');
check(cn::decide(ev(['eventtype' => 'group', 'groupid' => 3]), $other, false, false), 'a group event: announced');
check(!cn::decide(null, $other, false, false), 'no snapshot: not announced');
check(!cn::decide(ev(['modulename' => 'scheduler', 'eventtype' => 'SSstu:9']), $other, false, false),
    'a scheduler booking: not announced (booking_notice handles it)');
check(!cn::decide(ev(['modulename' => 'assign', 'eventtype' => 'due']), $other, false, false), 'an assignment due date: not announced');
check(!cn::decide(ev(['component' => 'mod_bigbluebuttonbn']), $other, false, false), 'a component event: not announced');
check(!cn::decide(ev(['subscriptionid' => 5]), $other, false, false), 'a subscription event: not announced');
check(!cn::decide(ev(['eventtype' => 'user']), $other, false, false), 'a user event: not announced');
check(!cn::decide(ev(['eventtype' => 'category']), $other, false, false), 'a category event: not announced');
check(!cn::decide(ev(['repeatid' => 50]), ['repeatid' => 41] + $other, false, false),
    'parent promotion after deleting a series\' first event: not announced');
check(!cn::decide(ev(['visible' => 0]), $other, false, false), 'an update that leaves it hidden: not announced');
check(cn::decide(ev(['visible' => 0]), $other, true, false), 'deleting a hidden event: announced');
check(!cn::decide(ev(), $other, false, true), 'the second save of an event created this request: not announced');

// key(): a series shares one key.
check(cn::key(ev(['repeatid' => 50])) === 'r50', 'a series is keyed by its repeatid');
check(cn::key(ev()) === 'e41', 'a single event is keyed by its id');

// merge(): one notice per key per request.
$first = cn::merge(null, false, ev(['repeatid' => 50, 'timestart' => 1800000000]), 12);
check($first['action'] === 'changed' && $first['scope'] === 'occurrence', 'first change: one occurrence');
check($first['firststart'] === 1800000000 && $first['actorid'] === 12, 'it keeps the first start and the actor');
check($first['key'] === 'r50' && $first['courseid'] === 7, 'it carries the key and the reach');
$second = cn::merge($first, false, ev(['repeatid' => 50, 'timestart' => 1800604800]), 12);
check($second['scope'] === 'series', 'a second occurrence in the request: the series');
check($second['firststart'] === 1800000000, 'the first start is kept');
$cancel = cn::merge($second, true, ev(['repeatid' => 50]), 12);
check($cancel['action'] === 'cancelled', 'a cancellation outranks a change');
$again = cn::merge($cancel, false, ev(['repeatid' => 50]), 12);
check($again['action'] === 'cancelled', 'a later change never revives a cancelled key');
check(!array_key_exists('timestart', $first), 'no per-occurrence time in the notice');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
