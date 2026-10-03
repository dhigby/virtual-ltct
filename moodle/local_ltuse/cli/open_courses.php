<?php
/**
 * Open the courses: remove the organisation groups spec 002 used before 2026-10-02.
 *
 *   (no option) or --dry-run   count what would change, and change nothing
 *   --execute                  make the changes
 *
 * For every course with an ltct: idnumber (spec 002 research R13):
 *   1. each cohort-sync instance's group is set to none, through
 *      enrol_cohort_plugin::update_instance() with customint2 = 0 and the instance's own
 *      roleid, which then runs the cohort sync;
 *   2. each group whose idnumber is ltct:org:<key>, or whose name is an organisation's display
 *      name, is deleted with groups_delete_group(), which also deletes its calendar events.
 * And only for those outside the ltct:org:* categories (courses organisations share):
 *   3. each cohort-sync instance for an ltct:org:<key>:managers cohort is deleted with
 *      enrol_cohort_plugin::delete_instance(), so managers leave shared courses (R2).
 *
 * Group mode, of the course and of its discussion forum, is configuration: run
 * scripts/site_config.py apply after --execute (R3, R14). Groups are learner data that
 * site_config cannot see, which is why this is a CLI and not an apply step. Idempotent: a
 * second run reports zero.
 *
 * Output is counts only, never a course, group, cohort or person name (constitution III).
 * All APIs confirmed on MOODLE_502_STABLE: enrol_cohort_plugin::update_instance()
 * (public/enrol/cohort/lib.php:147), enrol_plugin::delete_instance() (public/lib/enrollib.php),
 * groups_delete_group() (public/group/lib.php:591).
 *
 * Exit codes: 0 done, 1 failed, 2 usage error.
 */

define('CLI_SCRIPT', true);

// Same path as site_config.php: public/config.php, the shim that loads the real one.
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/group/lib.php');

[$options, $unrecognised] = cli_get_params(
    ['dry-run' => false, 'execute' => false, 'help' => false],
    ['h' => 'help']);

$usage = "usage: open_courses.php [--dry-run | --execute]\n";
if ($options['help'] || $unrecognised || ($options['dry-run'] && $options['execute'])) {
    cli_writeln($usage);
    exit(2);
}
$execute = (bool)$options['execute'];

\core\session\manager::set_user(get_admin());

$plugin = enrol_get_plugin('cohort');
if (!$plugin) {
    cli_error('enrol_cohort is not installed on this server', 1);
}

// Organisations, as their member cohorts know them: key => display name. The member cohort's
// name is the organisation's name (site_config's expansion), so a group named for an
// organisation by cohort sync's "create new group" carries that name.
$orgnames = [];
$cohorts = $DB->get_records_select('cohort', $DB->sql_like('idnumber', ':prefix'),
    ['prefix' => $DB->sql_like_escape('ltct:org:') . '%'], '', 'id, idnumber, name');
foreach ($cohorts as $cohort) {
    $key = substr((string)$cohort->idnumber, strlen('ltct:org:'));
    if ($key !== '' && strpos($key, ':') === false) {
        $orgnames[$key] = (string)$cohort->name;
    }
}
$managercohortids = [];
foreach ($cohorts as $cohort) {
    if (preg_match('/^ltct:org:[^:]+:managers$/', (string)$cohort->idnumber)) {
        $managercohortids[(int)$cohort->id] = true;
    }
}

// Course identities only: ltct:<slug>, no further colon.
$courses = [];
foreach ($DB->get_records_select('course', $DB->sql_like('idnumber', ':prefix'),
        ['prefix' => $DB->sql_like_escape('ltct:') . '%'], 'id', 'id, idnumber, category') as $course) {
    $slug = substr((string)$course->idnumber, strlen('ltct:'));
    if ($slug !== '' && strpos($slug, ':') === false) {
        $courses[(int)$course->id] = $course;
    }
}
$orgcategories = [];
foreach ($DB->get_records_select('course_categories', $DB->sql_like('idnumber', ':prefix'),
        ['prefix' => $DB->sql_like_escape('ltct:org:') . '%'], '', 'id') as $category) {
    $orgcategories[(int)$category->id] = true;
}

$counts = ['courses' => count($courses), 'ungrouped' => 0, 'groups' => 0, 'managers' => 0, 'failed' => 0];
$orgnameset = array_flip($orgnames);

foreach ($courses as $courseid => $course) {
    $shared = !isset($orgcategories[(int)$course->category]);
    $instances = $DB->get_records('enrol', ['courseid' => $courseid, 'enrol' => 'cohort'], 'id');

    // Step 3 first, so step 1 does not re-sync an instance that is about to go.
    foreach ($instances as $id => $instance) {
        if ($shared && isset($managercohortids[(int)$instance->customint1])) {
            $counts['managers']++;
            unset($instances[$id]);
            if ($execute) {
                try {
                    $plugin->delete_instance($instance);
                } catch (\Throwable $e) {
                    $counts['failed']++;
                }
            }
        }
    }

    // Step 1.
    foreach ($instances as $instance) {
        if ((int)$instance->customint2 === 0) {
            continue;
        }
        $counts['ungrouped']++;
        if ($execute) {
            try {
                $plugin->update_instance($instance, (object)['customint2' => 0, 'roleid' => $instance->roleid]);
            } catch (\Throwable $e) {
                $counts['failed']++;
            }
        }
    }

    // Step 2.
    foreach (groups_get_all_groups($courseid, 0, 0, 'g.id, g.idnumber, g.name') as $group) {
        $byidnumber = preg_match('/^ltct:org:[^:]+$/', (string)$group->idnumber);
        $byname = isset($orgnameset[(string)$group->name]);
        if (!$byidnumber && !$byname) {
            continue; // A group a teacher made for teaching (FR-011): not ours.
        }
        $counts['groups']++;
        if ($execute) {
            try {
                groups_delete_group($group->id);
            } catch (\Throwable $e) {
                $counts['failed']++;
            }
        }
    }
}

cli_writeln(($execute ? 'done' : 'dry run, nothing changed') . ": {$counts['courses']} ltct: courses; " .
    "cohort syncs set to no group {$counts['ungrouped']}; organisation groups deleted {$counts['groups']}; " .
    "managers cohort syncs removed from shared courses {$counts['managers']}; failed {$counts['failed']}");
if ($execute) {
    cli_writeln('next: scripts/site_config.py apply (course and forum group modes), then drift');
} else if ($counts['ungrouped'] + $counts['groups'] + $counts['managers']) {
    cli_writeln('run again with --execute to make these changes');
}
exit($counts['failed'] ? 1 : 0);
