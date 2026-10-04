<?php
// Harness for the pure parts of moodle/local_ltuse/classes/booking_notice.php (spec 011,
// FR-007, research R20): which booking change sends which message, and to whom in which
// voice. Needs no Moodle; nothing here reads a database or sends a message.
//
// Run locally:   php tests/booking_notice_harness.php
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/booking_notice.php';
if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\booking_notice')) {
    echo "FAIL: local_ltuse\\booking_notice not found\nFAILURES: 1\n";
    exit(1);
}

use local_ltuse\booking_notice as bn;

function event(array $fields = []): stdClass {
    return (object)array_merge(['id' => 300, 'modulename' => 'scheduler', 'instance' => 5,
        'eventtype' => 'SSstu:77', 'userid' => 21, 'timestart' => 1800000000, 'timeduration' => 1800], $fields);
}
function row(array $fields = []): stdClass {
    return (object)array_merge(['id' => 1, 'eventid' => 300, 'slotid' => 77, 'learnerid' => 21,
        'mentorid' => 9, 'timestart' => 1800000000, 'timeduration' => 1800], $fields);
}

// slot_id() and is_office_hours().
check(bn::slot_id('SSstu:77') === 77, 'SSstu:77 is slot 77');
check(bn::slot_id('SSsup:77') === null, 'the mentor\'s own event is not a learner booking');
check(bn::slot_id('SSstu:') === null && bn::slot_id('SSstu:x1') === null, 'a malformed eventtype is nothing');
check(bn::slot_id('course') === null, 'a course event is nothing');
check(bn::is_office_hours(event(), 5), 'a learner booking in the office-hours scheduler');
check(!bn::is_office_hours(event(), 6), 'another scheduler instance: not office hours');
check(!bn::is_office_hours(event(), 0), 'no office-hours scheduler yet: nothing');
check(!bn::is_office_hours(event(['modulename' => 'assign']), 5), 'another module: not office hours');
check(!bn::is_office_hours(event(['eventtype' => 'SSsup:77']), 5), 'the mentor\'s event: not counted twice');

// decide(): what each change announces.
check(bn::decide('created', null, event()) === 'booked', 'a new booking: booked');
check(bn::decide('created', row(), event()) === null, 'a created event already recorded: nothing');
check(bn::decide('updated', row(), event()) === null, 'an update at the same time (a note edit, a 016 re-save): nothing');
check(bn::decide('updated', row(), event(['timestart' => 1800003600])) === 'changed', 'moved to a new time: changed');
check(bn::decide('updated', row(), event(['timeduration' => 3600])) === 'changed', 'a new length: changed');
check(bn::decide('updated', null, event()) === null, 'an update with no record: recorded quietly, nothing sent');
check(bn::decide('deleted', row(), event()) === 'cancelled', 'a learner cancels, or the mentor removes them: cancelled');
check(bn::decide('deleted', null, event()) === 'cancelled', 'a delete with no record still says cancelled');

// message_string(): a confirmation to whoever acted, a notice to the other side.
check(bn::message_string('booked', 21, 21) === 'bookingnotice:booked:you', 'the mentee who booked: confirmation');
check(bn::message_string('booked', 9, 21) === 'bookingnotice:booked:notice', 'the mentor: notice');
check(bn::message_string('changed', 9, 9) === 'bookingnotice:changed:you', 'the mentor who moved it: confirmation');
check(bn::message_string('changed', 21, 9) === 'bookingnotice:changed:notice', 'the mentee: notice');
check(bn::message_string('cancelled', 21, 21) === 'bookingnotice:cancelled:you', 'the mentee who cancelled: confirmation');
check(bn::message_string('cancelled', 9, 21) === 'bookingnotice:cancelled:notice', 'the mentor: notice');
check(bn::message_string('cancelled', 21, 9, true) === 'bookingnotice:slotdeleted:notice',
    'a slot the mentor deleted: the mentee is told it was cancelled by their mentor');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
