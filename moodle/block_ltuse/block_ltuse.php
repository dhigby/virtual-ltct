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

use block_ltuse\output\home;
use local_ltuse\learner_home;

/**
 * The learner home block (spec 007, contracts/learner-ui.md).
 *
 * One instance, on the Dashboard only, with no header: the content is its own heading. It is
 * placed by the declaration (moodle/site/dashboard.yaml), never added by a learner or a
 * teacher (R4), which is why both of its capabilities are granted to manager alone.
 *
 * @package    block_ltuse
 * @copyright  2026 SIL Global
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ltuse extends block_base {
    /**
     * Set the block's title, its name in the block list.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_ltuse');
    }

    /**
     * The Dashboard only.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['all' => false, 'my' => true];
    }

    /**
     * One instance: the declaration places exactly one.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * No header: the content is its own heading.
     *
     * @return bool
     */
    public function hide_header() {
        return true;
    }

    /**
     * No admin settings.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * The block's one mode for the viewing user, rendered from local_ltuse\learner_home.
     *
     * Empty for a guest and for the site team (learner_home::applies(), R12), so the block
     * shows nothing to them. Every visible word comes from get_string(), through
     * \block_ltuse\output\home::context(), which the app handler renders too.
     *
     * @return stdClass
     */
    public function get_content() {
        global $OUTPUT, $USER;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';
        if (!isloggedin() || isguestuser() || !learner_home::applies((int)$USER->id)) {
            return $this->content;
        }
        $this->content->text = $OUTPUT->render_from_template(
            'block_ltuse/block',
            home::context(learner_home::state((int)$USER->id))
        );
        return $this->content;
    }
}
