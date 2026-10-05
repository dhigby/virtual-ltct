<?php
// This file is part of local_ltuse.

/**
 * My organisation: the people of each organisation the viewer manages, with their courses and
 * completion, and, for their learners, the actions a manager may take (spec 002 amendment
 * 2026-10-02, research R10; FR-006).
 *
 * Only for members of an ltct:org:<key>:managers cohort, read on every request, so leaving the
 * cohort ends access at once. The list comes from organisation\people; every write goes
 * through organisation\actions, which re-checks access::may_manage_account() and its own rule
 * for the person and course named in the request, so an edited request reaches no one else.
 *
 * A GET with an action shows a confirmation saying what the action does; only the confirmed
 * POST, with a sesskey (single_button adds it), writes. The page then redirects back with the
 * outcome. Linked from the user menu for managers only (hook_callbacks::user_menu()).
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_ltuse\organisation\actions;
use local_ltuse\organisation\people;

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/local/ltuse/organisation.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('organisation', 'local_ltuse'));
$PAGE->set_heading(get_string('organisation', 'local_ltuse'));

if (!local_ltuse_managed_organisation_keys((int)$USER->id, true)) {
    throw new moodle_exception('organisation:notmanager', 'local_ltuse');
}

if ($action !== '') {
    if (!in_array($action, actions::ACTIONS, true) || !$userid) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    // Refuse before asking, so a confirmation is never shown for someone the viewer cannot
    // manage. The action itself checks again.
    $facts = actions::require_manageable($userid);
    $person = core_user::get_user($userid, '*', MUST_EXIST);
    // The course rule runs before the course is read for its name, so the confirmation never
    // names a course the action would refuse (a pilot, another organisation's).
    $course = null;
    if ($action === 'enrol') {
        $course = get_course(actions::enrol_target($facts, $courseid)->id);
    } else if ($action === 'unenrol') {
        actions::unenrol_instance($userid, $courseid);
        $course = get_course($courseid);
    }

    if ($confirm) {
        require_sesskey();
        // Only the confirmation's POST writes: a GET carrying a sesskey is refused.
        if (!data_submitted()) {
            throw new moodle_exception('invalidparameter', 'debug');
        }
        $type = \core\output\notification::NOTIFY_SUCCESS;
        switch ($action) {
            case 'enrol':
                actions::enrol($userid, $courseid);
                $message = get_string('organisation:done:enrol', 'local_ltuse');
                break;
            case 'unenrol':
                actions::unenrol($userid, $courseid);
                $message = get_string('organisation:done:unenrol', 'local_ltuse');
                break;
            case 'reset':
                $outcome = actions::send_reset($userid);
                $message = get_string($outcome, 'local_ltuse');
                if ($outcome !== 'organisation:reset:sent') {
                    $type = \core\output\notification::NOTIFY_WARNING;
                }
                break;
            case 'suspend':
                actions::suspend($userid);
                $message = get_string('organisation:done:suspend', 'local_ltuse');
                break;
            case 'reactivate':
                actions::reactivate($userid);
                $message = get_string('organisation:done:reactivate', 'local_ltuse');
                break;
        }
        redirect(new moodle_url($url, [], 'person-' . $userid), $message, null, $type);
    }

    $a = (object)[
        // confirm() prints the message as HTML, so the name is escaped here.
        'person' => s(fullname($person)),
        'course' => $course ? format_string($course->fullname, true, ['context' => context_course::instance($course->id)]) : '',
    ];
    $params = ['action' => $action, 'userid' => $userid, 'confirm' => 1];
    if ($course) {
        $params['courseid'] = $course->id;
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('organisation:confirm:' . $action, 'local_ltuse', $a),
        new moodle_url($url, $params), new moodle_url($url, [], 'person-' . $userid),
        ['continuestr' => get_string('organisation:action:' . $action, 'local_ltuse')]);
    echo $OUTPUT->footer();
    exit;
}

$data = people::for_manager((int)$USER->id);
foreach ($data['organisations'] as &$organisation) {
    foreach ($organisation['people'] as &$row) {
        foreach (['reset', 'suspend', 'reactivate'] as $name) {
            $row[$name . 'url'] = (new moodle_url($url, ['action' => $name, 'userid' => $row['id']]))->out(false);
        }
        foreach ($row['courses'] as &$courserow) {
            $courserow['unenrolurl'] = (new moodle_url($url, ['action' => 'unenrol', 'userid' => $row['id'],
                'courseid' => $courserow['id']]))->out(false);
        }
        unset($courserow);
    }
    unset($row);
}
unset($organisation);
$data['actionurl'] = $url->out(false);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_ltuse/organisation', $data);
echo $OUTPUT->footer();
