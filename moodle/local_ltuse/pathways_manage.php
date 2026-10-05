<?php
// This file is part of local_ltuse.

/**
 * Assign pathways: give a pathway to a cohort, take it away, and follow the cohort's progress
 * (spec 006, contracts/pages.md; FR-012, FR-013, US5).
 *
 *   (none)                                   the cohorts the viewer may assign to
 *   cohortid=<id>                            one cohort: its pathways, the assign form, progress
 *   cohortid=<id>&action=assign|unassign&key=<key>&sesskey=
 *
 * The site team may assign to any cohort; an organisation manager to their own organisation's
 * cohorts only (assignments::may_assign()). A cohort outside that throws before anything about
 * it is read. Assigning changes what the cohort's members see and enrols nobody: enrolment is
 * spec 008's (contracts/pathway-api.md). The progress table lists each member through
 * fullname() only, and to a manager only the members viewer::may_view() lets them see, so a
 * sub-cohort holding someone from another organisation never shows that person.
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/ltuse/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

use local_ltuse\pathway\assignments;
use local_ltuse\pathway\catalogue;
use local_ltuse\pathway\view;
use local_ltuse\pathway\viewer;

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}

$cohortid = optional_param('cohortid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$key = optional_param('key', '', PARAM_RAW);
$page = optional_param('page', 0, PARAM_INT);

/** Members shown per page of the progress table: each row reads its pathways' courses. */
const LOCAL_LTUSE_PATHWAY_MEMBERS_PER_PAGE = 50;

$viewerid = (int)$USER->id;
$systemcontext = context_system::instance();
$siteteam = has_capability('moodle/cohort:assign', $systemcontext);

$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/local/ltuse/pathways_manage.php', $cohortid ? ['cohortid' => $cohortid] : []));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pathway:manage', 'local_ltuse'));
$PAGE->set_heading(get_string('pathway:manage', 'local_ltuse'));

if ($cohortid && !assignments::may_assign($viewerid, $cohortid)) {
    throw new required_capability_exception($systemcontext, 'moodle/cohort:assign', 'nopermissions', '');
}

$manageurl = new moodle_url('/local/ltuse/pathways_manage.php');

if ($cohortid && ($action === 'assign' || $action === 'unassign')) {
    require_sesskey();
    $back = new moodle_url($manageurl, ['cohortid' => $cohortid]);
    if (catalogue::parse_key($key) === null) {
        redirect($back, get_string('pathway:unknown', 'local_ltuse'), null, \core\output\notification::NOTIFY_WARNING);
    }
    if ($action === 'assign') {
        if (!catalogue::is_assignable($key)) {
            redirect($back, get_string('pathway:unknown', 'local_ltuse'), null, \core\output\notification::NOTIFY_WARNING);
        }
        assignments::assign($key, $cohortid);   // Never with $enrol: only spec 008 sets it.
    } else {
        assignments::unassign($key, $cohortid);
    }
    redirect($back);
}

echo $OUTPUT->header();

if (!$cohortid) {
    $data = ['cohorts' => array_values(array_map(function(stdClass $cohort) use ($manageurl): array {
        return [
            'name' => format_string($cohort->name),
            'url' => (new moodle_url($manageurl, ['cohortid' => $cohort->id]))->out(false),
            'pathways' => array_map(function(array $row): array {
                return ['title' => local_ltuse_pathway_title($row['pathwaykey'])];
            }, assignments::for_cohort((int)$cohort->id)),
        ];
    }, local_ltuse_pathway_cohorts($viewerid, $siteteam)))];
    $data['hascohorts'] = !empty($data['cohorts']);
    echo $OUTPUT->render_from_template('local_ltuse/pathways_manage', $data);
    echo $OUTPUT->footer();
    exit;
}

$cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, name', MUST_EXIST);
$assigned = array_column(assignments::for_cohort($cohortid), 'pathwaykey');

$pathways = [];
foreach ($assigned as $each) {
    $pathways[] = [
        'title' => local_ltuse_pathway_title($each),
        'key' => $each,
        'unassignurl' => (new moodle_url($manageurl, ['cohortid' => $cohortid, 'action' => 'unassign',
            'key' => $each, 'sesskey' => sesskey()]))->out(false),
    ];
}
$options = [];
foreach (catalogue::all() as $each) {
    if (!in_array($each, $assigned, true)) {
        $options[] = ['key' => $each, 'title' => local_ltuse_pathway_title($each)];
    }
}

// Progress: members in name order, one page at a time; to a manager only those they may see.
// simplified: a manager's page may show fewer than 50 rows when a sub-cohort holds people
// they may not see; if that matters, filter in SQL by the organisation member cohort instead.
$total = $DB->count_records_sql(
    "SELECT COUNT(1) FROM {cohort_members} cm JOIN {user} u ON u.id = cm.userid
      WHERE cm.cohortid = :cohortid AND u.deleted = 0", ['cohortid' => $cohortid]);
$members = $DB->get_records_sql(
    "SELECT u.*
       FROM {cohort_members} cm
       JOIN {user} u ON u.id = cm.userid
      WHERE cm.cohortid = :cohortid AND u.deleted = 0
   ORDER BY u.lastname, u.firstname, u.id", ['cohortid' => $cohortid],
    $page * LOCAL_LTUSE_PATHWAY_MEMBERS_PER_PAGE, LOCAL_LTUSE_PATHWAY_MEMBERS_PER_PAGE);

$managedkeys = $siteteam ? [] : local_ltuse_managed_organisation_keys($viewerid);
$rows = [];
foreach ($members as $member) {
    $memberid = (int)$member->id;
    if (!$siteteam) {
        $fields = profile_user_record($memberid);
        $person = [
            'id' => $memberid,
            'ltct_org' => isset($fields->ltct_org) ? trim((string)$fields->ltct_org) : '',
            'org_cohorts' => local_ltuse_organisation_member_keys($memberid),
        ];
        $mayview = viewer::may_view($viewerid, $memberid, [
            'siteconfig' => false,
            'mentor' => false,
            'managedkeys' => $managedkeys,
            'person' => $person,
        ]);
        if (!$mayview) {
            continue;
        }
    }
    $rows[] = [
        'fullname' => fullname($member),
        'url' => (new moodle_url('/local/ltuse/pathways.php', ['userid' => $memberid]))->out(false),
        'progress' => array_map(function(array $summary): array {
            return ['title' => $summary['title'], 'progresstext' => $summary['progresstext']];
        }, view::summaries($memberid, $assigned)),
    ];
}

$data = [
    'cohortname' => format_string($cohort->name),
    'backurl' => $manageurl->out(false),
    'haspathways' => !empty($pathways),
    'pathways' => $pathways,
    'hasoptions' => !empty($options),
    'options' => $options,
    'assignurl' => $manageurl->out(false),
    'cohortid' => $cohortid,
    'sesskey' => sesskey(),
    'hasmembers' => !empty($rows) && !empty($assigned),
    'members' => $rows,
    'pagingbar' => $OUTPUT->paging_bar($total, $page, LOCAL_LTUSE_PATHWAY_MEMBERS_PER_PAGE,
        new moodle_url($manageurl, ['cohortid' => $cohortid])),
];
echo $OUTPUT->render_from_template('local_ltuse/pathways_manage', $data);
echo $OUTPUT->footer();

/**
 * The cohorts the viewer may assign pathways to, by name: every cohort for the site team, and
 * for a manager each of their organisations' member cohort and its sub-cohorts, never the
 * managers cohort (assignments::may_assign() decides each one).
 *
 * @param int $viewerid
 * @param bool $siteteam
 * @return stdClass[]
 */
function local_ltuse_pathway_cohorts(int $viewerid, bool $siteteam): array {
    global $DB;
    if ($siteteam) {
        return $DB->get_records('cohort', null, 'name, id', 'id, name, idnumber');
    }
    $found = [];
    foreach (local_ltuse_managed_organisation_keys($viewerid) as $orgkey) {
        $base = assignments::ORG_PREFIX . $orgkey;
        $candidates = $DB->get_records_select('cohort',
            'idnumber = :base OR ' . $DB->sql_like('idnumber', ':prefix'),
            ['base' => $base, 'prefix' => $DB->sql_like_escape($base . ':') . '%'], 'name, id', 'id, name, idnumber');
        foreach ($candidates as $cohort) {
            if (assignments::is_org_cohort_of((string)$cohort->idnumber, (string)$orgkey)) {
                $found[$cohort->id] = $cohort;
            }
        }
    }
    core_collator::asort_objects_by_property($found, 'name');
    return array_values($found);
}

/**
 * A pathway key's title: the competency's name or the role's, or the key itself when it no
 * longer resolves (a retired role keeps its assignments).
 *
 * @param string $key
 * @return string
 */
function local_ltuse_pathway_title(string $key): string {
    static $titles = null;
    if ($titles === null) {
        $titles = array_column(view::browse(), 'title', 'key');
    }
    return $titles[$key] ?? $key;
}
