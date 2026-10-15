<?php
namespace local_ltuse\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The pilot page's one field: the exact email address of the person to enrol as a trial
 * student (2026-10-15). There is deliberately no search and no autocomplete, so the page
 * never shows an account the author did not already know about (pilot\actions).
 *
 * Custom data:
 *   courseid   the pilot course
 */
class pilot_enrol_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid', (int)$this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'email', get_string('pilot:email', 'local_ltuse'),
            ['size' => 40, 'autocomplete' => 'off']);
        $mform->setType('email', PARAM_RAW_TRIMMED);
        $mform->addRule('email', null, 'required', null, 'client');
        $mform->addRule('email', null, 'email', null, 'client');
        $mform->addHelpButton('email', 'pilot:email', 'local_ltuse');

        $this->add_action_buttons(false, get_string('pilot:enrol', 'local_ltuse'));
    }
}
