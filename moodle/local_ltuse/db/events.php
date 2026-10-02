<?php
// Event observers for local_ltuse.

defined('MOODLE_INTERNAL') || die();

// Spec 003 (research R5): a mentor and their learner become message contacts while the
// relationship lasts, so each can reach the other with no course in common.
$observers = [
    [
        'eventname' => '\core\event\role_assigned',
        'callback' => '\local_ltuse\observer::role_assigned',
    ],
    [
        'eventname' => '\core\event\role_unassigned',
        'callback' => '\local_ltuse\observer::role_unassigned',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\local_ltuse\observer::user_deleted',
    ],
];
