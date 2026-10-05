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
            // "Pathways" (spec 006, R10; FR-015): the signed-in user's own pathways, from the
            // same view as /local/ltuse/pathways.php. Never disabled for a signed-in user, and
            // takes no userid: in the app, a person sees only their own pathways.
            'pathways' => [
                'delegate' => 'CoreMainMenuDelegate',
                'method' => 'pathways_view',
                'init' => 'pathways_init',
                'displaydata' => [
                    'title' => 'pathways',
                    'icon' => 'fa-route',
                ],
                'priority' => 490,
            ],
        ],
        'lang' => [
            ['mentoring', 'local_ltuse'],
            ['nomentoring', 'local_ltuse'],
            ['pathways', 'local_ltuse'],
            ['pathway:nocourseyet', 'local_ltuse'],
            ['pathway:done', 'local_ltuse'],
        ],
    ],
];
