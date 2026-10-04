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
    // Spec 016 (R2): repair protected accounts the hook and observers missed, every
    // reconcile_minutes (protection.yaml, 60), at a minute Moodle picks per site.
    [
        'classname' => '\local_ltuse\task\reconcile_protection',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
