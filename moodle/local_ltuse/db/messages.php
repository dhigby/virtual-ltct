<?php
// Message providers for local_ltuse.

defined('MOODLE_INTERNAL') || die();

// Spec 011. Both arrive by email, in the browser and as an app push (row #25) unless the
// person turns a route off in their notification preferences (FR-006, "their chosen
// notification route").
$messageproviders = [
    // A calendar event they can see changed or was cancelled (R15). New events are not
    // announced (plan decision 5).
    'eventchange' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    // An office-hours booking, a change of its time or its cancellation, to both the mentee
    // and the mentor (R20, plan decision 5).
    'bookingnotice' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    // Spec 016 (US3-1): to the learner only, when their protection level changes. It names the
    // level and what others now see, never a real name or who made the change. Forced on in
    // the browser, so a learner always learns that their identity is shown differently.
    'protectionchanged' => [
        'defaults' => [
            'popup' => MESSAGE_FORCED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
