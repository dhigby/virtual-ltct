<?php
// This file is part of local_ltuse.

/**
 * Identity protection for one person: the granting page (spec 016, research R12; US3).
 *
 * Only someone who may manage this person's protection reaches it: the site team, or a manager
 * of the person's own organisation (entitlement::can_manage_protection). Anyone else is
 * refused, the person themselves included: their own level and preview are on their profile.
 * A manager whose organisation withholds identity sees and changes only the protected display.
 *
 * Writes go through local_ltuse\protection\service::set_protection(), the same code as the
 * web service, so the refusals (looser than the organisation, not ready, pseudonym, username,
 * acknowledgement) are the same everywhere.
 */

require(__DIR__ . '/../../config.php');

use local_ltuse\protection\entitlement;
use local_ltuse\protection\levels;
use local_ltuse\protection\service;

$userid = required_param('id', PARAM_INT);
require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
$user = core_user::get_user($userid, '*', MUST_EXIST);
if ($user->deleted) {
    throw new moodle_exception('userdeleted');
}
$usercontext = context_user::instance($userid);
$url = new moodle_url('/local/ltuse/protection.php', ['id' => $userid]);
$PAGE->set_context($usercontext);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('protection:title', 'local_ltuse'));
$PAGE->set_heading(fullname($user));

if (!entitlement::can_manage_protection((int)$USER->id, $userid)) {
    throw new required_capability_exception($usercontext, 'local/ltuse:manageprotection', 'nopermissions', '');
}
$seeidentity = entitlement::can_view_identity((int)$USER->id, $userid);
$configured = service::config() !== null;
$row = $configured ? service::row($userid) : null;
$real = $seeidentity ? service::real_identity($userid) : null;
$orgminimum = $configured ? service::user_org_minimum($userid) : levels::NONE;
$ready = service::orgscope_ready();
$available = [];
foreach (levels::ORDER as $level) {
    $available[$level] = $configured && levels::available($level, $ready);
}
$realfirst = $real['firstname'] ?? (string)$user->firstname;
$reallast = $real['lastname'] ?? (string)$user->lastname;
$held = [];
if ($seeidentity && $row && $row->realfields) {
    foreach ((array)json_decode($row->realfields, true) as $field => $entry) {
        if ($field !== levels::MAILDISPLAY) {
            $held[$field] = (string)($entry['value'] ?? '');
        }
    }
}

$form = new \local_ltuse\form\protection_form($url, [
    'userid' => $userid,
    'seeidentity' => $seeidentity,
    'available' => $available,
    'orgminimum' => $orgminimum,
    'hasactivity' => service::has_activity($userid),
    // From the real names on the server, whoever is looking: a manager who may not see them
    // still needs the field, and its note then says nothing about why (FR-006).
    'needsusername' => levels::username_reveals((string)$user->username,
        $row ? (string)$row->realfirstname : (string)$user->firstname,
        $row ? (string)$row->reallastname : (string)$user->lastname),
    'suggested' => service::suggest_username(),
    'held' => $held,
]);
$form->set_data([
    'level' => $row ? (string)$row->ownlevel : levels::NONE,
    'pseudonym' => $row ? (string)$row->pseudonym : '',
    'realfirstname' => $seeidentity ? $realfirst : '',
    'reallastname' => $seeidentity ? $reallast : '',
]);

$profileurl = new moodle_url('/user/profile.php', ['id' => $userid]);
if ($form->is_cancelled()) {
    redirect($profileurl);
}
$error = null;
if ($data = $form->get_data()) {
    // The suggested username is only for First name only and Pseudonym; at a looser level the
    // hidden field still submits it, and the account must not be renamed for nothing.
    $renames = levels::rank((string)$data->level) >= levels::rank(levels::FIRSTNAME);
    $options = [
        'pseudonym' => (string)($data->pseudonym ?? ''),
        'newusername' => $renames ? (string)($data->newusername ?? '') : '',
        'acknowledgehistory' => !empty($data->acknowledgehistory),
    ];
    if ($seeidentity) {
        // Only what was changed counts as a correction (FR-008 records every change).
        if (trim((string)$data->realfirstname) !== $realfirst) {
            $options['realfirstname'] = (string)$data->realfirstname;
        }
        if (trim((string)$data->reallastname) !== $reallast) {
            $options['reallastname'] = (string)$data->reallastname;
        }
        $fields = [];
        foreach ($held as $field => $value) {
            $new = trim((string)($data->{'held_' . $field} ?? ''));
            if ($new !== '' && $new !== $value) {
                $fields[$field] = $new;
            }
        }
        $options['realfields'] = $fields;
    }
    try {
        $result = service::set_protection($userid, (string)$data->level, $options, (int)$USER->id);
        $message = get_string('protection:saved', 'local_ltuse',
            get_string('protection:level:' . $result['effectivelevel'], 'local_ltuse'));
        if ($result['warnings']) {
            $message .= ' ' . implode(' ', $result['warnings']);
        }
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('protection:title', 'local_ltuse'));
if (!$configured) {
    echo $OUTPUT->notification(get_string('protection:err:noconfig', 'local_ltuse'), 'warning');
}
if ($error !== null) {
    echo $OUTPUT->notification(s($error), 'error');
}
$current = $row ? (string)$row->effectivelevel : levels::NONE;
$summary = get_string('protection:current', 'local_ltuse', get_string('protection:level:' . $current, 'local_ltuse'));
if ($row && $row->source !== levels::SOURCE_OWN) {
    $summary .= ' ' . get_string('protection:source:' . $row->source, 'local_ltuse');
}
echo html_writer::tag('p', $summary . ' ' . entitlement::marker((int)$USER->id, $userid));
if ($real) {
    echo html_writer::tag('p', get_string('protection:realname', 'local_ltuse', s(trim($realfirst . ' ' . $reallast))));
} else if (!$seeidentity && $current !== levels::NONE) {
    echo html_writer::tag('p', get_string('protection:withheldfromyou', 'local_ltuse'));
}
if (!$ready) {
    echo $OUTPUT->notification(get_string('protection:notready', 'local_ltuse'), 'info');
}
$form->display();
echo $OUTPUT->footer();
