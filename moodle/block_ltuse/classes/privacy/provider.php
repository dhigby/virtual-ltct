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

namespace block_ltuse\privacy;

/**
 * Privacy provider for block_ltuse.
 *
 * The block has no table and no setting. It renders what local_ltuse\learner_home reads from
 * core and local_ltuse at the moment it is shown, and stores none of it, so it has nothing of
 * its own to export or delete (Principle III).
 *
 * @package    block_ltuse
 * @copyright  2026 SIL Global
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Why the block stores no personal data.
     *
     * @return string the lang string that says why the block stores no personal data
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
