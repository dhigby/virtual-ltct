<?php
// This file is part of block_ltuse, the learner home block of the LTC training system.

defined('MOODLE_INTERNAL') || die();

use block_ltuse\output\home;
use local_ltuse\learner_home;

/**
 * The learner home block (spec 007, contracts/learner-ui.md).
 *
 * One instance, on the Dashboard only, with no header: the content is its own heading. It is
 * placed by the declaration (moodle/site/dashboard.yaml), never added by a learner or a
 * teacher (R4), which is why both of its capabilities are granted to manager alone.
 */
class block_ltuse extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_ltuse');
    }

    public function applicable_formats() {
        return ['all' => false, 'my' => true];
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function hide_header() {
        return true;
    }

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
        $this->content->text = $OUTPUT->render_from_template('block_ltuse/block',
            home::context(learner_home::state((int)$USER->id)));
        return $this->content;
    }
}
