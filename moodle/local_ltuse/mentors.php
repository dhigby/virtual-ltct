<?php
// This file is part of local_ltuse.

/**
 * Manage mentors: one learner's current mentors, each with Remove, and an Add picker drawn only
 * from the hidden ltct:mentors cohort (spec 003, research R7 Phase B; spec 002 research R10 and
 * task T078; FR-008, SC-003).
 *
 * It manages the user-context mentor relationship only: the learner's default mentor. A mentor
 * for one course is spec 008's.
 *
 * Who: the site team (moodle/role:assign in the learner's user context, mentor assignable
 * there) or the learner's organisation manager, for a learner only
 * (organisation\access::may_manage_account()). local_ltuse_may_manage_mentors() gathers the
 * inputs and local_ltuse\mentor_admin::decide() decides, recomputed on every GET and POST, so a
 * forged POST naming another learner is refused like a direct GET. A refusal says the same for
 * a missing learner as for one the viewer may not manage.
 *
 * Writes: role_assign() and role_unassign() of `mentor` in the learner's user context
 * (public/lib/accesslib.php:1579, :1691), only after a confirmation that is a POST carrying the
 * sesskey (require_sesskey(), public/lib/sessionlib.php:83). Neither function checks a
 * capability itself, so the decision above is the only gate. Each fires core's
 * role_assigned or role_unassigned event with the viewer as actor, and the spec 003 observers
 * add or remove the message contacts.
 */

require(__DIR__ . '/../../config.php');
// Core loads a plugin's lib.php only to call its callbacks; this page calls its helpers directly.
require_once(__DIR__ . '/lib.php');

$userid = required_param('userid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$mentorid = optional_param('mentorid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$url = new moodle_url('/local/ltuse/mentors.php', ['userid' => $userid]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

// core_user::get_user() (public/lib/classes/user.php:122) returns false for a missing id.
$learner = core_user::get_user($userid);
if (!local_ltuse_may_manage_mentors($learner)) {
    throw new moodle_exception('mentors:notallowed', 'local_ltuse');
}
// No can_view_identity(V, P) check here: spec 002's ask for one was dropped as moot (Doug,
// 2026-10-05). Everyone this page authorises already passes it: the site team by path 1, an
// own-organisation manager by path 3 (protection\entitlement).

$roleid = \local_ltuse\mentoring::role_id();
$learnerctx = context_user::instance((int)$learner->id);

// The learner's current mentors (get_role_users(), public/lib/accesslib.php:4062).
$mentors = [];
foreach (get_role_users($roleid, $learnerctx) as $mentor) {
    if ((int)$mentor->id !== (int)$learner->id) {
        $mentors[(int)$mentor->id] = $mentor;
    }
}

// Candidates: ltct:mentors members only, never a site-wide search, less the learner and the
// mentors they already have.
$candidates = array_diff_key(local_ltuse_mentor_candidates(), $mentors, [(int)$learner->id => true]);

if ($action === 'add' || $action === 'remove') {
    // The mentor must be one this page would offer: a forged id is refused, not written.
    $pool = $action === 'add' ? $candidates : $mentors;
    if (!isset($pool[$mentorid])) {
        throw new moodle_exception('mentors:invalidmentor', 'local_ltuse', $url);
    }
    $mentor = $pool[$mentorid];
    // Names are escaped once here: confirm() and the redirect notice print the string as HTML.
    $a = (object)['mentor' => s(fullname($mentor)), 'learner' => s(fullname($learner))];

    if ($confirm) {
        require_sesskey();
        if (!data_submitted()) {
            throw new moodle_exception('mentors:invalidmentor', 'local_ltuse', $url);
        }
        if ($action === 'add') {
            role_assign($roleid, $mentorid, $learnerctx->id);
            $message = get_string('mentors:added', 'local_ltuse', $a);
        } else {
            role_unassign($roleid, $mentorid, $learnerctx->id);
            $message = get_string('mentors:removed', 'local_ltuse', $a);
        }
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    $PAGE->set_title(get_string('mentors:manage', 'local_ltuse'));
    $PAGE->set_heading(get_string('mentors:manage', 'local_ltuse'));
    echo $OUTPUT->header();
    $continue = new moodle_url($url, ['action' => $action, 'mentorid' => $mentorid, 'confirm' => 1]);
    echo $OUTPUT->confirm(get_string('mentors:confirm' . $action, 'local_ltuse', $a), $continue, $url, [
        'confirmtitle' => get_string('mentors:' . $action, 'local_ltuse'),
        'continuestr' => get_string('mentors:' . $action, 'local_ltuse'),
    ]);
    echo $OUTPUT->footer();
    exit;
}

$PAGE->set_title(get_string('mentors:manage', 'local_ltuse'));
$PAGE->set_heading(get_string('mentors:manage', 'local_ltuse'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mentors:heading', 'local_ltuse', s(fullname($learner))));
echo html_writer::tag('p', get_string('mentors:intro', 'local_ltuse'));

if ($mentors) {
    $table = new html_table();
    $table->head = [get_string('mentors:mentor', 'local_ltuse'), ''];
    $table->attributes['class'] = 'generaltable';
    foreach ($mentors as $mentor) {
        $remove = $OUTPUT->single_button(new moodle_url($url, ['action' => 'remove', 'mentorid' => $mentor->id]),
            get_string('mentors:remove', 'local_ltuse'), 'get');
        $table->data[] = [s(fullname($mentor)), $remove];
    }
    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('mentors:none', 'local_ltuse'));
}

echo $OUTPUT->heading(get_string('mentors:add', 'local_ltuse'), 3);
if ($candidates) {
    $options = [];
    foreach ($candidates as $candidate) {
        $options[(int)$candidate->id] = fullname($candidate);
    }
    echo $OUTPUT->single_select(new moodle_url($url, ['action' => 'add']), 'mentorid', $options, '',
        ['' => 'choosedots'], null, ['label' => get_string('mentors:choose', 'local_ltuse')]);
} else {
    echo html_writer::tag('p', get_string('mentors:nocandidates', 'local_ltuse'));
}

echo html_writer::tag('p', html_writer::link(new moodle_url('/user/profile.php', ['id' => $learner->id]),
    get_string('mentors:back', 'local_ltuse')));
echo $OUTPUT->footer();
