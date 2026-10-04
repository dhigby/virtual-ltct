<?php
// This file is part of local_ltuse.

/**
 * Organisation minimums (spec 016, research R12; FR-001a). The site team only.
 *
 * An organisation's minimum is Moodle data, set here or through local_ltuse_set_org_protection,
 * and never declared in the repo: declaring it would publish which partners are at risk. The
 * page lists the declared organisations with their current setting and member counts, never a
 * member's name. Each organisation is edited on its own (?orgkey=), and the form starts from
 * its stored values, so changing the minimum never silently resets "managers see identities".
 */

require(__DIR__ . '/../../config.php');

use local_ltuse\protection\entitlement;
use local_ltuse\protection\levels;
use local_ltuse\protection\service;

require_login(null, false);
$context = context_system::instance();
$url = new moodle_url('/local/ltuse/orgprotection.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('protection:orgtitle', 'local_ltuse'));
$PAGE->set_heading(get_string('protection:orgtitle', 'local_ltuse'));
if (!entitlement::can_manage_organisations((int)$USER->id)) {
    throw new required_capability_exception($context, 'local/ltuse:manageorgprotection', 'nopermissions', '');
}

$config = service::config();
$names = $DB->get_records_select_menu('course_categories', $DB->sql_like('idnumber', ':prefix'),
    ['prefix' => 'ltct:org:%'], '', 'idnumber, name');
$orgs = [];
foreach (service::declared_org_keys() as $key) {
    $name = $names['ltct:org:' . $key] ?? $key;
    $orgs[$key] = format_string($name, true, ['context' => $context]) . " ({$key})";
}
$rows = service::org_rows();
$orgkey = optional_param('orgkey', '', PARAM_ALPHANUMEXT);
if ($orgkey !== '' && !isset($orgs[$orgkey])) {
    throw new moodle_exception('protection:err:noorg', 'local_ltuse');
}

$error = null;
$form = null;
if ($orgkey !== '') {
    $available = [];
    foreach (levels::ORDER as $level) {
        $available[$level] = service::level_available($level);
    }
    $form = new \local_ltuse\form\org_protection_form(new moodle_url($url, ['orgkey' => $orgkey]), [
        'orgkey' => $orgkey, 'orgs' => $orgs, 'available' => $available,
        'maxlevel' => (string)($config['org_minimum_max'] ?? levels::ORG_MAX)]);
    $stored = $rows[$orgkey] ?? null;
    $form->set_data(['orgkey' => $orgkey,
        'minlevel' => $stored ? (string)$stored->minlevel : levels::NONE,
        'managers_see_identity' => $stored ? (int)$stored->managers_see_identity : 1]);
    if ($form->is_cancelled()) {
        redirect($url);
    }
    if ($data = $form->get_data()) {
        try {
            $result = service::set_org_protection($orgkey, (string)$data->minlevel,
                !empty($data->managers_see_identity), !empty($data->acknowledgehistory), (int)$USER->id);
            redirect($url, get_string('protection:orgsaved', 'local_ltuse', (object)$result), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        }
    }
}

echo $OUTPUT->header();
if ($config === null) {
    echo $OUTPUT->notification(get_string('protection:err:noconfig', 'local_ltuse'), 'warning');
}
if ($error !== null) {
    echo $OUTPUT->notification(s($error), 'error');
}
if ($form) {
    $form->display();
}
$table = new html_table();
$table->head = [get_string('protection:organisation', 'local_ltuse'), get_string('protection:orgminimum', 'local_ltuse'),
    get_string('protection:managersseeidentity', 'local_ltuse'), get_string('protection:members', 'local_ltuse'), ''];
foreach ($orgs as $key => $label) {
    $row = $rows[$key] ?? null;
    $table->data[] = [
        s($label),
        get_string('protection:level:' . ($row ? $row->minlevel : levels::NONE), 'local_ltuse'),
        ($row && !(int)$row->managers_see_identity) ? get_string('no') : get_string('yes'),
        count(service::org_members($key)),
        html_writer::link(new moodle_url($url, ['orgkey' => $key]), get_string('edit')),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
