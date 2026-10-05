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

    // Spec 002 (amendment 2026-10-02, R10, R12): an organisation's managers and people are
    // message contacts, and a person who leaves an organisation is suspended in its own
    // courses. Fired by cohort_add_member() and cohort_remove_member() (cohort/lib.php:189-224).
    [
        'eventname' => '\core\event\cohort_member_added',
        'callback' => '\local_ltuse\observer::cohort_member_added',
    ],
    [
        'eventname' => '\core\event\cohort_member_removed',
        'callback' => '\local_ltuse\observer::cohort_member_removed',
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

    // Spec 006 (contracts/pathway-api.md): a deleted cohort's pathway links go through
    // assignments::unassign(), so spec 008 sees each removal as pathway_unassigned.
    [
        'eventname' => '\core\event\cohort_deleted',
        'callback' => '\local_ltuse\observer::cohort_deleted',
    ],

    // Spec 008: administration. A course joining a learning pathway (spec 006) is enrolled for
    // every cohort that holds the pathway with enrol = 1 (research R11); a course leaving one
    // unenrols nobody. Not internal, so a change that rolls back enrols no one. Spec 006's
    // set_course_pathway fires it.
    [
        'eventname' => '\local_ltuse\event\pathway_courses_changed',
        'callback' => '\local_ltuse\admin\observer::pathway_courses_changed',
        'internal' => false,
    ],

    // Spec 008: administration. Course mentors (research R10). Internal (the default), so a
    // course mentor whose reason ends loses Teacher, their course-mentor enrolment and their
    // group in the same request, as spec 016's identity entitlement requires. All of them do nothing while
    // local_ltuse/coursementorsync is 0; the hourly course_mentor_reconcile is the backstop.
    [
        'eventname' => '\core\event\role_assigned',
        'callback' => '\local_ltuse\admin\observer::mentor_role_changed',
    ],
    [
        'eventname' => '\core\event\role_unassigned',
        'callback' => '\local_ltuse\admin\observer::mentor_role_changed',
    ],
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => '\local_ltuse\admin\observer::user_enrolment_changed',
    ],
    [
        'eventname' => '\core\event\user_enrolment_updated',
        'callback' => '\local_ltuse\admin\observer::user_enrolment_changed',
    ],
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => '\local_ltuse\admin\observer::user_enrolment_changed',
    ],
    [
        'eventname' => '\core\event\enrol_instance_updated',
        'callback' => '\local_ltuse\admin\observer::enrol_instance_changed',
    ],
    [
        'eventname' => '\core\event\enrol_instance_deleted',
        'callback' => '\local_ltuse\admin\observer::enrol_instance_changed',
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback' => '\local_ltuse\admin\observer::user_updated',
    ],
];
