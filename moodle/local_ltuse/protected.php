<?php
// This file is part of local_ltuse.

/**
 * People I support (spec 016, research R7, surface 3; FR-007, US2-3).
 *
 * The protected people the viewer is entitled to see (entitlement::can_view_identity), with
 * their level, their real name and the name others see, and a CSV that carries the Protected
 * marker on every row. Core report-builder datasources cannot take our column, which is why
 * this page exists instead of a column on spec 004's reports.
 *
 * Shows nothing to a viewer who is entitled to no one, and says nothing about anyone else, so
 * it never tells a non-entitled viewer who is protected. The CSV is a browser download: nothing
 * is written on the server.
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_ltuse\protection\entitlement;
use local_ltuse\protection\service;

$download = optional_param('download', 0, PARAM_BOOL);
require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
$url = new moodle_url('/local/ltuse/protected.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('protection:supported', 'local_ltuse'));
$PAGE->set_heading(get_string('protection:supported', 'local_ltuse'));

$people = \local_ltuse\protection\surfaces::supported((int)$USER->id);

if ($download) {
    require_sesskey();
    $records = [[get_string('protection:marker', 'local_ltuse'), get_string('protection:realname:col', 'local_ltuse'),
        get_string('protection:display:col', 'local_ltuse'), get_string('protection:level', 'local_ltuse'),
        get_string('protection:organisation', 'local_ltuse')]];
    foreach ($people as $person) {
        $records[] = [get_string('protection:marker', 'local_ltuse'), $person['realname'], $person['display'],
            $person['levelname'], $person['org']];
    }
    csv_export_writer::download_array('people-i-support-' . userdate(time(), '%Y%m%d'), $records);
    exit;
}

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('protection:supportedintro', 'local_ltuse'));
if (!$people) {
    echo html_writer::tag('p', get_string('protection:supportednone', 'local_ltuse'));
} else {
    $table = new html_table();
    $table->head = [get_string('protection:realname:col', 'local_ltuse'), get_string('protection:display:col', 'local_ltuse'),
        get_string('protection:level', 'local_ltuse'), get_string('protection:organisation', 'local_ltuse'), ''];
    foreach ($people as $person) {
        $links = [html_writer::link(new moodle_url('/user/profile.php', ['id' => $person['id']]),
            get_string('mentoring:profile', 'local_ltuse'))];
        if ($person['canmanage']) {
            $links[] = html_writer::link(new moodle_url('/local/ltuse/protection.php', ['id' => $person['id']]),
                get_string('protection:manage', 'local_ltuse'));
        }
        $table->data[] = [s($person['realname']) . ' ' . entitlement::marker((int)$USER->id, $person['id']),
            s($person['display']), s($person['levelname']), s($person['org']), implode(' · ', $links)];
    }
    echo html_writer::table($table);
    echo $OUTPUT->single_button(new moodle_url($url, ['download' => 1, 'sesskey' => sesskey()]),
        get_string('protection:downloadcsv', 'local_ltuse'), 'get');
}
echo $OUTPUT->footer();
