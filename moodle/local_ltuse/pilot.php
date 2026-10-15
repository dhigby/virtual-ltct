<?php
// This file is part of local_ltuse.

/**
 * Pilot learners: a course author's page for one stage-7 pilot (2026-10-15). It lists the
 * trial students (the course's manual enrolments, by name), enrols one more by exact email
 * address, removes one, and shows or hides the course.
 *
 * Only for a course in LTC Pilots, and only for someone holding local/ltuse:pilotenrol there
 * (the declared ltcauthor role, assigned on the category). Every write goes through
 * pilot\actions, which checks both again, so an edited request reaches nothing else.
 *
 * The enrol form posts with its sesskey. Remove asks first, and only the confirmed POST
 * writes. Show and hide are single buttons, which post with a sesskey. Each write redirects
 * back with the outcome. Linked from the course's More menu for holders of the capability
 * (local_ltuse_extend_navigation_course()).
 */

require(__DIR__ . '/../../config.php');

use local_ltuse\form\pilot_enrol_form;
use local_ltuse\pilot\actions;

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$course = get_course($courseid);
require_login($course, false);
$course = actions::require_pilot($courseid);
$context = context_course::instance($course->id);

$url = new moodle_url('/local/ltuse/pilot.php', ['courseid' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pilot', 'local_ltuse'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

if ($action === 'remove') {
    if (!$userid) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    // Refuse before asking, so a confirmation never names someone who is not a trial student.
    actions::removable($course->id, $userid);
    if ($confirm) {
        require_sesskey();
        // Only the confirmation's POST writes: a GET carrying a sesskey is refused.
        if (!data_submitted()) {
            throw new moodle_exception('invalidparameter', 'debug');
        }
        actions::remove($course->id, $userid);
        redirect($url, get_string('pilot:done:remove', 'local_ltuse'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    $person = core_user::get_user($userid, '*', MUST_EXIST);
    $a = (object)[
        // confirm() prints the message as HTML, so the names are escaped here.
        'person' => s(fullname($person)),
        'course' => format_string($course->fullname, true, ['context' => $context]),
    ];
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('pilot:confirm:remove', 'local_ltuse', $a),
        new moodle_url($url, ['action' => 'remove', 'userid' => $userid, 'confirm' => 1]), $url,
        ['continuestr' => get_string('pilot:remove', 'local_ltuse')]);
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'show' || $action === 'hide') {
    require_sesskey();
    if (!data_submitted()) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    actions::set_visible($course->id, $action === 'show');
    redirect($url, get_string('pilot:done:' . $action, 'local_ltuse'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action !== '') {
    throw new moodle_exception('invalidparameter', 'debug');
}

$form = new pilot_enrol_form($url, ['courseid' => $course->id]);
if ($data = $form->get_data()) {
    try {
        $user = actions::enrol($course->id, (string)$data->email);
        redirect($url, get_string('pilot:done:enrol', 'local_ltuse', s(fullname($user))), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        if ($e->module !== 'local_ltuse') {
            throw $e;
        }
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$learners = actions::learners($course->id);
foreach ($learners as &$row) {
    $row['removeurl'] = (new moodle_url($url, ['action' => 'remove', 'userid' => $row['id']]))->out(false);
}
unset($row);

$visible = (bool)$course->visible;
$canvisibility = has_capability('moodle/course:visibility', $context);
$toggle = $canvisibility ? $OUTPUT->render(new single_button(
    new moodle_url($url, ['action' => $visible ? 'hide' : 'show']),
    get_string($visible ? 'pilot:hide' : 'pilot:show', 'local_ltuse'), 'post',
    $visible ? single_button::BUTTON_SECONDARY : single_button::BUTTON_PRIMARY)) : '';

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_ltuse/pilot', [
    'learners' => $learners,
    'haslearners' => !empty($learners),
    'visible' => $visible,
    'visibilitytoggle' => $toggle,
    'form' => $form->render(),
]);
echo $OUTPUT->footer();
