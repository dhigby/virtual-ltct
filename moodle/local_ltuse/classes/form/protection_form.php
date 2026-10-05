<?php
namespace local_ltuse\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_ltuse\protection\levels;

/**
 * The granting page's form (spec 016, research R12): one person's level, pseudonym, the two
 * facts a raise records, corrections to the real name, and the history acknowledgement.
 *
 * A raise records that the person asked (`requested`, scope review change 13) and that the
 * granter checked the account's email address identifies neither the person nor their
 * organisation (`emailchecked`, change 2); the page shows a warning where the address looks
 * as if it does. There is no username field: the service replaces a username that holds the
 * real name by itself (change 15). Corrections and the acknowledgement are offered only to the
 * site team: a manager grants at intake, for someone with no activity (change 14).
 *
 * Custom data:
 *   userid          the person
 *   current         the level applied now
 *   siteteam        the viewer is the site team (entitlement::is_site_team)
 *   seeidentity     the viewer may see (and so correct) the real identity
 *   hasactivity     the person has activity, so a change needs the acknowledgement (R13)
 *   emailwarnings   service::email_warnings(): name, organisation
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
            $options[$level] = get_string('protection:level:' . $level, 'local_ltuse');
        }
        $mform->addElement('select', 'level', get_string('protection:level', 'local_ltuse'), $options);
        $mform->addHelpButton('level', 'protection:level', 'local_ltuse');

        $mform->addElement('text', 'pseudonym', get_string('protection:pseudonym', 'local_ltuse'), ['maxlength' => 100]);
        $mform->setType('pseudonym', PARAM_TEXT);
        $mform->hideIf('pseudonym', 'level', 'neq', levels::PSEUDONYM);

        $mform->addElement('advcheckbox', 'requested', '', get_string('protection:requested', 'local_ltuse'));
        foreach ((array)($data['emailwarnings'] ?? []) as $code) {
            $mform->addElement('static', 'emailwarn_' . $code, '',
                \html_writer::span(get_string('protection:emailwarn:' . $code, 'local_ltuse'), 'text-danger'));
        }
        $mform->addElement('advcheckbox', 'emailchecked', '', get_string('protection:emailchecked', 'local_ltuse'));

        if (!empty($data['seeidentity']) && !empty($data['siteteam'])) {
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

        if (!empty($data['hasactivity']) && !empty($data['siteteam'])) {
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
        $level = $data['level'] ?? '';
        if (!levels::is_level($level)) {
            $errors['level'] = get_string('protection:err:level', 'local_ltuse');
            return $errors;
        }
        if ($level === levels::PSEUDONYM && trim((string)($data['pseudonym'] ?? '')) === '') {
            $errors['pseudonym'] = get_string('required');
        }
        if (levels::is_raise((string)$this->_customdata['current'], $level)) {
            if (empty($data['requested'])) {
                $errors['requested'] = get_string('protection:err:notrequested', 'local_ltuse');
            }
            if (empty($data['emailchecked'])) {
                $errors['emailchecked'] = get_string('protection:err:emailnotchecked', 'local_ltuse');
            }
        }
        return $errors;
    }
}
