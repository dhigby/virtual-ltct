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
 * Version details for block_ltuse, the learner home block of the LTC training system.
 *
 * The block sits on the default Dashboard, placed there by moodle/site/dashboard.yaml, and
 * shows one learner where to go next: continue, the empty state and the onward routes (spec
 * 007, R3 and R5). Everything it shows comes from local_ltuse\learner_home, so the web block
 * and the app handler cannot differ.
 *
 * @package    block_ltuse
 * @copyright  2026 SIL Global
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_ltuse';
$plugin->version   = 2026100600;   // The first version (spec 007): the block's skeleton.
                                   // moodle/site/site.yaml pins block_ltuse to this stamp,
                                   // and validate fails if they differ.

// As local_ltuse: verified on Moodle 5.2 only, so a major upgrade stops at the plugin check
// instead of running unverified code (constitution XI). Widen the range only after
// re-verifying on the new branch (502 = Moodle 5.2).
$plugin->requires  = 2026042000;   // Moodle 5.2.
$plugin->supported = [502, 502];

$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';

// The block renders what local_ltuse\learner_home derives, so it depends on the local_ltuse
// version that adds learner_home (spec 007) and cannot install without it.
$plugin->dependencies = ['local_ltuse' => 2026100901];
