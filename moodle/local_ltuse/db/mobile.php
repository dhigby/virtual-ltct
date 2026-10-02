<?php
// Moodle app support for local_ltuse (site plugins, free app; spec 003 research R4).

defined('MOODLE_INTERNAL') || die();

$addons = [
    'local_ltuse' => [
        'handlers' => [
            // "Mentoring" under the app's More menu: the same data as /local/ltuse/mentoring.php.
            // mentoring_init hides it for anyone with no mentor and no learner.
            'mentoring' => [
                'delegate' => 'CoreMainMenuDelegate',
                'method' => 'mentoring_view',
                'init' => 'mentoring_init',
                'displaydata' => [
                    'title' => 'mentoring',
                    'icon' => 'fa-users',
                ],
                'priority' => 500,
            ],
        ],
        'lang' => [
            ['mentoring', 'local_ltuse'],
            ['nomentoring', 'local_ltuse'],
        ],
    ],
];
