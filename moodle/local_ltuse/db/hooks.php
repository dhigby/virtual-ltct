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
    // Spec 011 (R14): the office-hours booking pages say which time zone their times are in.
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => \local_ltuse\hook_callbacks::class . '::top_of_body',
    ],
    // Spec 016 (R2): a protected user's protected values are re-applied on every
    // user_update_user(), whoever calls it.
    [
        'hook' => \core_user\hook\before_user_updated::class,
        'callback' => \local_ltuse\protection\hook_callbacks::class . '::before_user_updated',
    ],
    // Spec 002 (amendment 2026-10-02, R10): a "My organisation" item in the user menu, only for
    // a member of an organisation's managers cohort.
    [
        'hook' => \core_user\hook\extend_user_menu::class,
        'callback' => \local_ltuse\hook_callbacks::class . '::user_menu',
    ],
];
