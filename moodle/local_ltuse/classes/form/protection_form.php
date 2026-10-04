<?php
namespace local_ltuse\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_ltuse\protection\levels;

/**
 * The granting page's form (spec 016, research R12): one person's level, pseudonym, a
 * neutral username, corrections to the real name, and the history acknowledgement.
 *
 * Custom data:
 *   userid          the person
 *   seeidentity     the viewer may see (and so correct) the real identity
 *   available       level => bool, from levels::available()
 *   orgminimum      the organisation's minimum, which no own level may be looser than
 *   hasactivity     the person has activity, so a change needs the acknowledgement (R13)
 *   needsusername   their username gives away their real name (R13)
 *   suggested       a neutral username to offer
 *   held            field => value, the held real values (only when seeidentity)
 */
class protection_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('hidden', 'id', (int)$data['userid']);
        $mform->setType('id', PARAM_INT);

        $options = [];
        foreach (levels::ORDER as $level) {
            $label = get_string('protection:level:' . $level, 'local_ltuse');
            if (empty($data['available'][$level])) {
                $label .= ' ' . get_string('protection:notyet', 'local_ltuse');
            }
            $options[$level] = $label;
        }
        $mform->addElement('select', 'level', get_string('protection:level', 'local_ltuse'), $options);
        $mform->addHelpButton('level', 'protection:level', 'local_ltuse');
        if (($data['orgminimum'] ?? levels::NONE) !== levels::NONE) {
            $mform->addElement('static', 'orgminimum', '', get_string('protection:orgminimumnote', 'local_ltuse',
                get_string('protection:level:' . $data['orgminimum'], 'local_ltuse')));
        }

        $mform->addElement('text', 'pseudonym', get_string('protection:pseudonym', 'local_ltuse'), ['maxlength' => 100]);
        $mform->setType('pseudonym', PARAM_TEXT);
        $mform->hideIf('pseudonym', 'level', 'neq', levels::PSEUDONYM);

        if (!empty($data['needsusername'])) {
            $mform->addElement('text', 'newusername', get_string('protection:newusername', 'local_ltuse'));
            $mform->setType('newusername', PARAM_USERNAME);
            $mform->setDefault('newusername', (string)$data['suggested']);
            $mform->addElement('static', 'usernamenote', '', get_string('protection:usernamenote', 'local_ltuse'));
            $mform->hideIf('newusername', 'level', 'in', [levels::NONE, levels::EMAIL]);
            $mform->hideIf('usernamenote', 'level', 'in', [levels::NONE, levels::EMAIL]);
        }

        if (!empty($data['seeidentity'])) {
            $mform->addElement('header', 'realheader', get_string('protection:realidentity', 'local_ltuse'));
            $mform->addElement('text', 'realfirstname', get_string('protection:realfirstname', 'local_ltuse'));
            $mform->setType('realfirstname', PARAM_TEXT);
            $mform->addElement('text', 'reallastname', get_string('protection:reallastname', 'local_ltuse'));
            $mform->setType('reallastname', PARAM_TEXT);
            foreach ((array)($data['held'] ?? []) as $field => $value) {
                $name = 'held_' . $field;
                $mform->addElement('text', $name, get_string('protection:heldfield', 'local_ltuse', s($field)));
                $mform->setType($name, PARAM_TEXT);
                $mform->setDefault($name, (string)$value);
            }
        }

        if (!empty($data['hasactivity'])) {
            $mform->addElement('header', 'historyheader', get_string('protection:history', 'local_ltuse'));
            $mform->setExpanded('historyheader');
            $mform->addElement('static', 'historynote', '', get_string('protection:historynote', 'local_ltuse'));
            $mform->addElement('advcheckbox', 'acknowledgehistory', '', get_string('protection:acknowledge', 'local_ltuse'));
        }

        $mform->addElement('static', 'ownwords', '', get_string('protection:ownwords', 'local_ltuse'));
        $this->add_action_buttons(true, get_string('protection:save', 'local_ltuse'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!levels::is_level($data['level'] ?? '')) {
            $errors['level'] = get_string('protection:err:level', 'local_ltuse');
        } else if (empty($this->_customdata['available'][$data['level']])) {
            $errors['level'] = get_string('protection:err:notready', 'local_ltuse');
        } else if (!levels::allowed_own($data['level'], (string)($this->_customdata['orgminimum'] ?? levels::NONE))) {
            $errors['level'] = get_string('protection:err:looser', 'local_ltuse',
                get_string('protection:level:' . $this->_customdata['orgminimum'], 'local_ltuse'));
        }
        if (($data['level'] ?? '') === levels::PSEUDONYM && trim((string)($data['pseudonym'] ?? '')) === '') {
            $errors['pseudonym'] = get_string('required');
        }
        return $errors;
    }
}
