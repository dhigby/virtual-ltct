<?php
/**
 * Mentor relationships: keep their message contacts in step, or end all of one mentor's.
 *
 *   --sync                           make the contact for every mentor assignment that lacks
 *                                    one. Run once after upgrading to the version that adds
 *                                    the observers; safe to run again at any time.
 *   --end-all --mentor=<username>    end every relationship that mentor holds (a mentor who
 *                                    leaves the program). The learners' records are untouched;
 *                                    the observers remove the contacts. Asks first unless --yes.
 *
 * Output is counts only, never a name, so a pasted log holds no personal data (constitution
 * III). Spec 003, research R5 and R6.
 *
 * Exit codes: 0 done, 1 failed, 2 usage error.
 */

define('CLI_SCRIPT', true);

// Same path as site_config.php: public/config.php, the shim that loads the real one.
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_ltuse\mentoring;
use local_ltuse\observer;

[$options, $unrecognised] = cli_get_params(
    ['sync' => false, 'end-all' => false, 'mentor' => '', 'yes' => false, 'help' => false],
    ['h' => 'help']);

$usage = "usage: mentor_contacts.php --sync\n" .
         "       mentor_contacts.php --end-all --mentor=<username> [--yes]\n";
// Exactly one of --sync and --end-all; --end-all needs --mentor.
if ($options['help'] || $unrecognised || (bool)$options['sync'] === (bool)$options['end-all'] ||
        ($options['end-all'] && $options['mentor'] === '')) {
    cli_writeln($usage);
    exit(2);
}

\core\session\manager::set_user(get_admin());
$roleid = mentoring::role_id();
if (!$roleid) {
    cli_error('the mentor role is not on this server; run scripts/site_config.py apply first', 1);
}

if ($options['sync']) {
    $sql = "SELECT ra.id, ra.userid AS mentorid, ctx.instanceid AS learnerid
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
             WHERE ra.roleid = :roleid AND ctx.contextlevel = :level";
    $counts = ['created' => 0, 'recorded' => 0, 'existing' => 0, 'failed' => 0];
    $rs = $DB->get_recordset_sql($sql, ['roleid' => $roleid, 'level' => CONTEXT_USER]);
    foreach ($rs as $ra) {
        if ((int)$ra->mentorid === (int)$ra->learnerid) {
            continue;
        }
        try {
            $counts[observer::ensure_contact((int)$ra->mentorid, (int)$ra->learnerid)]++;
        } catch (\Throwable $e) {
            $counts['failed']++; // One bad pair must not stop the rest; a rerun retries it.
        }
    }
    $rs->close();
    cli_writeln("contacts created {$counts['created']}, already made {$counts['recorded']}, " .
        "already contacts of their own {$counts['existing']}, failed {$counts['failed']}");
    exit($counts['failed'] ? 1 : 0);
}

$mentor = $DB->get_record('user', ['username' => $options['mentor'], 'deleted' => 0,
    'mnethostid' => $CFG->mnet_localhost_id]);
if (!$mentor) {
    cli_error('no such user', 1);
}
$count = $DB->count_records_sql("SELECT COUNT(1)
                                   FROM {role_assignments} ra
                                   JOIN {context} ctx ON ctx.id = ra.contextid
                                  WHERE ra.userid = :userid AND ra.roleid = :roleid
                                    AND ctx.contextlevel = :level",
    ['userid' => $mentor->id, 'roleid' => $roleid, 'level' => CONTEXT_USER]);
if (!$count) {
    cli_writeln('relationships ended 0');
    exit(0);
}
if (!$options['yes']) {
    $answer = cli_input("End {$count} mentor relationship(s) held by this user? (y/N)", 'n');
    if (strtolower(trim($answer)) !== 'y') {
        cli_writeln('nothing changed');
        exit(0);
    }
}
// Only user-context mentor assignments: the role has no other context level, and role_unassign_all
// fires role_unassigned for each, so the observers remove the contacts.
role_unassign_all(['userid' => (int)$mentor->id, 'roleid' => $roleid]);
cli_writeln("relationships ended {$count}");
exit(0);
