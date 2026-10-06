<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Moodle app support for block_ltuse (site plugins, free app; spec 007 R7).
 *
 * @package    block_ltuse
 * @copyright  2026 SIL Global
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
