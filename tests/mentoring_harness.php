<?php
// Harness for the pure parts of moodle/local_ltuse/classes/mentoring.php (spec 003, R3):
// progress_status() and sort_courses(). Needs no Moodle; nothing here reads a database.
//
// Run locally:        php tests/mentoring_harness.php
// Run on a host with no checkout (nothing is written there), as profile_access_harness.php:
//   { printf '<?php $LTCT_MENTORING_SRC = base64_decode("%s");\n' \
//       "$(tr -d '\r' < moodle/local_ltuse/classes/mentoring.php | base64 -w0)";
//     tail -n +2 tests/mentoring_harness.php | tr -d '\r'; } | ssh ltuse php
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/mentoring.php';
if (isset($LTCT_MENTORING_SRC)) {
    eval(preg_replace('/^\s*<\?php/', '', $LTCT_MENTORING_SRC));
} else if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\mentoring') ||
        !method_exists('local_ltuse\mentoring', 'progress_status') ||
        !method_exists('local_ltuse\mentoring', 'sort_courses')) {
    echo "FAIL: local_ltuse\\mentoring::progress_status() or sort_courses() not found\n";
    echo "FAILURES: 1\n";
    exit(1);
}

use local_ltuse\mentoring;
// progress_status(bool $tracked, ?int $timecompleted, ?float $percentage): array
//   ['state' => nottracked|completed|notstarted|inprogress, 'percent' => ?int, 'timecompleted' => ?int]

$s = mentoring::progress_status(false, null, null);
check($s['state'] === mentoring::NOT_TRACKED, 'completion not enabled: not tracked');
check($s['percent'] === null, 'not tracked has no percentage');

$s = mentoring::progress_status(true, 1700000000, 100.0);
check($s['state'] === mentoring::COMPLETED, 'complete: completed');
check($s['timecompleted'] === 1700000000, 'completed carries its date');

// A completion row outlives its enrolment and the course's tracking (FR-004).
$s = mentoring::progress_status(false, 1700000000, null);
check($s['state'] === mentoring::COMPLETED, 'completed row with tracking now off: still completed');

check(mentoring::progress_status(true, null, null)['state'] === mentoring::NOT_STARTED,
    'tracked, no percentage: not started');
check(mentoring::progress_status(true, null, 0.0)['state'] === mentoring::NOT_STARTED,
    'tracked, 0%: not started');

$s = mentoring::progress_status(true, null, 42.4);
check($s['state'] === mentoring::IN_PROGRESS, '42.4%: in progress');
check($s['percent'] === 42, 'percentage is rounded down to a whole number');
$s = mentoring::progress_status(true, null, 99.9);
check($s['state'] === mentoring::IN_PROGRESS && $s['percent'] === 99,
    '99.9% is in progress at 99, never shown as 100 before completion');
$s = mentoring::progress_status(true, null, 100.0);
check($s['state'] === mentoring::IN_PROGRESS && $s['percent'] === 99,
    '100% of activities but course not complete: in progress at 99');
$s = mentoring::progress_status(true, null, 0.4);
check($s['state'] === mentoring::IN_PROGRESS && $s['percent'] === 1,
    'any progress shows at least 1%');

// Ordering: in progress, not started, not tracked, then completed newest first; name breaks ties.
$rows = [
    ['fullname' => 'Done old', 'state' => mentoring::COMPLETED, 'timecompleted' => 100],
    ['fullname' => 'Untracked', 'state' => mentoring::NOT_TRACKED, 'timecompleted' => null],
    ['fullname' => 'Beta', 'state' => mentoring::IN_PROGRESS, 'timecompleted' => null],
    ['fullname' => 'Done new', 'state' => mentoring::COMPLETED, 'timecompleted' => 200],
    ['fullname' => 'Waiting', 'state' => mentoring::NOT_STARTED, 'timecompleted' => null],
    ['fullname' => 'Alpha', 'state' => mentoring::IN_PROGRESS, 'timecompleted' => null],
];
$order = array_column(mentoring::sort_courses($rows), 'fullname');
check($order === ['Alpha', 'Beta', 'Waiting', 'Untracked', 'Done new', 'Done old'],
    'courses ordered: ' . implode(', ', $order));
check(mentoring::sort_courses([]) === [], 'no courses sorts to no courses');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
