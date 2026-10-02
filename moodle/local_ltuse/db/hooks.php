<?php
// Hook callbacks for local_ltuse (Moodle hooks API).

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    // Spec 003: a "Mentoring" item in the primary navigation, only for someone who has a
    // mentor or a learner (research, Source results T001).
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => \local_ltuse\hook_callbacks::class . '::primary_extend',
    ],
];
