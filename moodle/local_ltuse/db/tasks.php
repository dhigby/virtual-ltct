<?php
// Scheduled tasks for local_ltuse.

defined('MOODLE_INTERNAL') || die();

// Spec 011 (research R16): keep the office-hours course in step with the mentor
// relationships, hourly, at a minute Moodle picks per site.
$tasks = [
    [
        'classname' => '\local_ltuse\task\officehours_reconcile',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],

    // Spec 008: administration. Hourly, keep every ltct: course's course mentors in step
    // (research R10; nothing while local_ltuse/coursementorsync is 0) and every pathway's
    // cohort enrolments (research R11; nothing until spec 006 is installed).
    [
        'classname' => '\local_ltuse\task\course_mentor_reconcile',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
