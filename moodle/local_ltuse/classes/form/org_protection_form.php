<?php
namespace local_ltuse\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_ltuse\protection\levels;

/**
 * The organisation page's form (spec 016, research R12): one organisation's minimum and
 * whether its own managers see real identities. The site team only.
 *
 * Custom data:
 *   orgs       key => display name, the declared organisations
 *   maxlevel   the strictest minimum allowed (protection.yaml org_minimum_max)
 *   available  level => bool
 */
class org_protection_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('select', 'orgkey', get_string('protection:organisation', 'local_ltuse'), $data['orgs']);
        $options = [];
        foreach (levels::ORDER as $level) {
            if (levels::rank($level) > levels::rank((string)$data['maxlevel'])) {
                break;
            }
            $label = get_string('protection:level:' . $level, 'local_ltuse');
            if (empty($data['available'][$level])) {
                $label .= ' ' . get_string('protection:notyet', 'local_ltuse');
            }
            $options[$level] = $label;
        }
        $mform->addElement('select', 'minlevel', get_string('protection:orgminimum', 'local_ltuse'), $options);
        $mform->addElement('advcheckbox', 'managers_see_identity', '',
            get_string('protection:managersseeidentity', 'local_ltuse'));
        $mform->setDefault('managers_see_identity', 1);
        $mform->addElement('advcheckbox', 'acknowledgehistory', '', get_string('protection:orgacknowledge', 'local_ltuse'));
        $mform->addElement('static', 'orgnote', '', get_string('protection:orgnote', 'local_ltuse'));
        $this->add_action_buttons(false, get_string('protection:save', 'local_ltuse'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($this->_customdata['available'][$data['minlevel'] ?? ''])) {
            $errors['minlevel'] = get_string('protection:err:notready', 'local_ltuse');
        }
        return $errors;
    }
}
