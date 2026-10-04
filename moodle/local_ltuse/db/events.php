<?php
// Event observers for local_ltuse.

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Spec 003 (research R5): a mentor and their learner become message contacts while the
    // relationship lasts, so each can reach the other with no course in common. Spec 011
    // (R16): the same events keep the mentor's office-hours group in step.
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

    // Spec 011 (R15, R20): calendar changes and cancellations, and office-hours bookings.
    // Not internal, so a change inside a transaction that rolls back announces nothing.
    [
        'eventname' => '\core\event\calendar_event_created',
        'callback' => '\local_ltuse\observer::calendar_event_created',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\calendar_event_updated',
        'callback' => '\local_ltuse\observer::calendar_event_updated',
        'internal' => false,
    ],
    [
        'eventname' => '\core\event\calendar_event_deleted',
        'callback' => '\local_ltuse\observer::calendar_event_deleted',
        'internal' => false,
    ],
    // Fires before the slot and its calendar events are deleted. Internal, so it runs at once,
    // while the booking records still say who held the slot.
    [
        'eventname' => '\mod_scheduler\event\slot_deleted',
        'callback' => '\local_ltuse\observer::scheduler_slot_deleted',
    ],

    // Spec 008: administration. A course joining a learning pathway (spec 006) is enrolled for
    // every cohort that holds the pathway with enrol = 1 (research R11); a course leaving one
    // unenrols nobody. Not internal, so a change that rolls back enrols no one. Harmless until
    // spec 006 is installed: the event is never fired.
    [
        'eventname' => '\local_ltuse\event\pathway_courses_changed',
        'callback' => '\local_ltuse\admin\observer::pathway_courses_changed',
        'internal' => false,
    ],
];
