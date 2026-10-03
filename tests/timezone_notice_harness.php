<?php
// Harness for moodle/local_ltuse/classes/timezone_notice.php (spec 011, research R14): which
// pages carry the time zone notice, and which sentence it shows. Needs no Moodle.
//
// Run locally:   php tests/timezone_notice_harness.php
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/timezone_notice.php';
if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\timezone_notice')) {
    echo "FAIL: local_ltuse\\timezone_notice not found\nFAILURES: 1\n";
    exit(1);
}

use local_ltuse\timezone_notice as tz;

// applies(): the office-hours scheduler's pages, and no others.
check(tz::applies('mod-scheduler-view', 'ltct:officehours:scheduler'), 'office-hours scheduler view page');
check(tz::applies('mod-scheduler-index', 'ltct:officehours:scheduler'), 'any mod-scheduler- page of that activity');
check(!tz::applies('mod-scheduler-view', 'ltct:other:scheduler'), 'another scheduler instance: no notice');
check(!tz::applies('mod-scheduler-view', null), 'a scheduler page with no idnumber: no notice');
check(!tz::applies('course-view-topics', 'ltct:officehours:scheduler'), 'the course page: no notice');
check(!tz::applies('calendar-view', null), 'the calendar: no notice');
check(!tz::applies('my-index', null), 'the dashboard: no notice');
check(!tz::applies('mod-page-view', 'ltct:officehours:scheduler'), 'another module type: no notice');

// describe(): names the zone, and says when it is only the site default.
$d = tz::describe('Africa/Nairobi', 'Africa/Nairobi');
check($d['string'] === 'tznotice' && $d['zone'] === 'Africa/Nairobi', 'a chosen zone is named');
$d = tz::describe('99', 'UTC');
check($d['string'] === 'tznotice:default' && $d['zone'] === 'UTC', '99: the site default, UTC');
$d = tz::describe('', 'UTC');
check($d['string'] === 'tznotice:default', 'an empty zone is the site default too');
$d = tz::describe('UTC', 'UTC');
check($d['string'] === 'tznotice', 'UTC chosen on purpose is not called the default');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
