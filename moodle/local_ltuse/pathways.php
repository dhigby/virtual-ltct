<?php
// This file is part of local_ltuse.

/**
 * Pathways: a learner's pathways, one pathway in full, or every pathway to browse (spec 006,
 * contracts/pages.md; FR-003, FR-004, FR-008, FR-009, FR-011, FR-012).
 *
 *   (none)               the viewer's own pathways, each as a summary, then "Browse all"
 *   key=<key>            one pathway in full for the viewer
 *   browse=1             every pathway, competencies by category, then roles
 *   userid=<id>[&key=]   as above, for that learner, when viewer::may_view() allows it
 *
 * Anyone signed in may open it. With another learner's userid the viewer must be their mentor
 * (003), their organisation's manager (002) or the site team; otherwise it throws before
 * anything of the learner is read for display, not even their name (R8). Names are shown
 * through fullname() only. Read only.
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ltuse/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

use local_ltuse\pathway\catalogue;
use local_ltuse\pathway\view;
use local_ltuse\pathway\viewer;
use local_ltuse\pathway\assignments;

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$key = optional_param('key', '', PARAM_RAW);
$browse = optional_param('browse', false, PARAM_BOOL);
$userid = optional_param('userid', 0, PARAM_INT);

$viewerid = (int)$USER->id;
if ($userid <= 0) {
    $userid = $viewerid;
}
$self = $userid === $viewerid;

$urlparams = [];
if (!$self) {
    $urlparams['userid'] = $userid;
}
if ($key !== '') {
    $urlparams['key'] = $key;
} else if ($browse) {
    $urlparams['browse'] = 1;
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/ltuse/pathways.php', $urlparams));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pathways', 'local_ltuse'));
$PAGE->set_heading(get_string('pathways', 'local_ltuse'));

$learnername = null;
if (!$self) {
    // A missing, deleted or guest account is refused exactly as a learner the viewer may not
    // see, so the page says nothing about whether an id exists.
    $learner = core_user::get_user($userid, '*', IGNORE_MISSING);
    $usercontext = $learner && empty($learner->deleted) && !isguestuser($learner)
        ? context_user::instance($userid, IGNORE_MISSING) : false;
    $allowed = false;
    if ($usercontext) {
        $managedkeys = local_ltuse_managed_organisation_keys($viewerid);
        $person = ['id' => $userid, 'ltct_org' => '', 'org_cohorts' => []];
        if ($managedkeys) {
            // 002's facts, gathered as local_ltuse_control_view_profile() gathers them: the
            // stored organisation key and the member cohorts must agree.
            $fields = profile_user_record($userid);
            $person['ltct_org'] = isset($fields->ltct_org) ? trim((string)$fields->ltct_org) : '';
            $person['org_cohorts'] = local_ltuse_organisation_member_keys($userid);
        }
        $allowed = viewer::may_view($viewerid, $userid, [
            'siteconfig' => has_capability('moodle/site:config', context_system::instance()),
            'mentor' => has_capability('local/ltuse:viewmenteeprogress', $usercontext, null, false),
            'managedkeys' => $managedkeys,
            'person' => $person,
        ]);
    }
    if (!$allowed) {
        // Always the system context: a user context would make the error page's Continue link
        // the learner's profile, which tells a live id from a missing one.
        throw new required_capability_exception(context_system::instance(),
            'local/ltuse:viewmenteeprogress', 'nopermissions', '');
    }
    $learnername = fullname($learner);
    $PAGE->navbar->add($learnername);
}

$base = $self ? [] : ['userid' => $userid];
$listurl = new moodle_url('/local/ltuse/pathways.php', $base);
$browseurl = new moodle_url('/local/ltuse/pathways.php', $base + ['browse' => 1]);

echo $OUTPUT->header();

if (view::levels() === null) {
    // The level labels come only from the applied site configuration; never guess one.
    echo $OUTPUT->notification(get_string('pathway:nolevels', 'local_ltuse'), \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->footer();
    exit;
}

if ($key !== '') {
    $context = catalogue::parse_key($key) === null ? null : view::for_learner($key, $userid);
    if ($context === null) {
        echo $OUTPUT->notification(get_string('pathway:unknown', 'local_ltuse'), \core\output\notification::NOTIFY_WARNING);
        echo html_writer::link($browseurl, get_string('pathway:browse', 'local_ltuse'));
    } else {
        $data = view::display($context);
        $data['learnername'] = $learnername;
        $data['listurl'] = $listurl->out(false);
        $data['browseurl'] = $browseurl->out(false);
        echo $OUTPUT->render_from_template('local_ltuse/pathway', $data);
    }
    echo $OUTPUT->footer();
    exit;
}

$data = [
    'learnername' => $learnername,
    'listurl' => $listurl->out(false),
    'browseurl' => $browseurl->out(false),
    'browse' => (bool)$browse,
];

if ($browse) {
    // Every pathway, as links: competencies grouped by their framework category, in
    // framework order, then roles in declared order. No progress here, so no per-course read.
    $groups = [];
    $rolelinks = [];
    foreach (view::browse() as $each) {
        $link = ['title' => $each['title'], 'url' => view::url($each['key'], $userid)->out(false)];
        if ($each['kind'] === catalogue::KIND_ROLE) {
            $rolelinks[] = $link;
            continue;
        }
        if (!isset($groups[$each['category']])) {
            $groups[$each['category']] = ['name' => $each['category'], 'pathways' => []];
        }
        $groups[$each['category']]['pathways'][] = $link;
    }
    $data['groups'] = array_values($groups);
    $data['hasroles'] = (bool)$rolelinks;
    $data['roles'] = $rolelinks;
} else {
    $mine = view::summaries($userid, assignments::pathways_for_user($userid));
    $data['hasmine'] = (bool)$mine;
    $data['mine'] = $mine;
    $data['none'] = !$mine;
}

echo $OUTPUT->render_from_template('local_ltuse/pathways', $data);
echo $OUTPUT->footer();
