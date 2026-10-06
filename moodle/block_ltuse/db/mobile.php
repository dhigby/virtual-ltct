<?php
// Moodle app support for block_ltuse (site plugins, free app; spec 007 R7).

defined('MOODLE_INTERNAL') || die();

$addons = [
    'block_ltuse' => [
        'handlers' => [
            // The learner home block on the app's Home tab: the same mode as the web block, from
            // the same data class. With no displaydata type, the app renders it through method
            // (moodleapp v5.2.1 block-handler.ts). class defaults to block_ltuse. title is the
            // app's name for the block; a block rendered by method shows no heading, as on the web.
            'ltuse' => [
                'delegate' => 'CoreBlockDelegate',
                'method' => 'mobile_block_view',
                'displaydata' => [
                    'title' => 'pluginname',
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'block_ltuse'],
            ['continue', 'block_ltuse'],
            ['start', 'block_ltuse'],
            ['coursename', 'block_ltuse'],
            ['empty', 'block_ltuse'],
            ['empty:who', 'block_ltuse'],
            ['contactsupport', 'block_ltuse'],
            ['done', 'block_ltuse'],
            ['offline:course', 'block_ltuse'],
            ['offline:quiz', 'block_ltuse'],
            ['onward', 'block_ltuse'],
            ['onward:pathway', 'block_ltuse'],
            ['onward:mentor', 'block_ltuse'],
            ['onward:community', 'block_ltuse'],
        ],
    ],
];
