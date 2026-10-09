<?php
// This file is part of local_ltuse.

/**
 * Mentoring: the learners you mentor, with their courses and completion, and your own
 * mentors (spec 003, research R3; FR-003, FR-010).
 *
 * Anyone signed in may open it; it shows only the viewer's own relationships, and each
 * learner only while the viewer holds local/ltuse:viewmenteeprogress in that learner's user
 * context (local_ltuse\mentoring::for_user()). Read only.
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ltuse/mentoring.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mentoring', 'local_ltuse'));
$PAGE->set_heading(get_string('mentoring', 'local_ltuse'));

$data = \local_ltuse\mentoring::for_user((int)$USER->id);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_ltuse/mentoring', $data);
echo $OUTPUT->footer();
