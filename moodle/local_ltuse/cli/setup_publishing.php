<?php
/**
 * One-command setup of the publishing account, role, service authorisation and token.
 *
 * Doing this through the Moodle web UI takes four screens and is easy to get subtly
 * wrong -- particularly the role's context levels, and the `webservice/rest:use`
 * capability, whose absence produces an "access control exception" that looks like a
 * token problem rather than a permissions one. This script is also the reason the
 * production server will be a repeat of a known-good setup rather than a second
 * ten-minute clickthrough.
 *
 * IDEMPOTENT. Run it as often as you like: an existing role, user or token is reused,
 * never duplicated.
 *
 * THE TOKEN IS NEVER PRINTED. It is written to --token-file with mode 600, because a
 * token that can rewrite every course should not end up in a terminal scrollback, a CI
 * log, or a chat transcript. Read it from that file when you need it.
 *
 * Usage (as the web server user, from the Moodle root):
 *   php public/local/ltuse/cli/setup_publishing.php --token-file=/home/ltuse/.ltuse-token
 *
 * Options:
 *   --token-file=PATH   where to write the token (required)
 *   --username=NAME     publishing account username (default: ltcpublisher)
 *   --email=ADDRESS     required when the account is created
 *   --rotate            replace any existing token with a new one
 *   --help
 */

define('CLI_SCRIPT', true);

// Three levels up is public/config.php, the loader shim that pulls in the real config.php
// from the Moodle root. Going that way rather than four levels to the root directly keeps
// this working under both the pre-5.1 and post-5.1 directory layouts.
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->dirroot . '/user/lib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'token-file' => null,
    'username' => 'ltcpublisher',
    'email' => null,
    'rotate' => false,
], ['h' => 'help']);

if ($options['help'] || empty($options['token-file'])) {
    cli_writeln(preg_replace('/^.*?\/\*\*|\*\/.*$/s', '', file_get_contents(__FILE__)));
    exit($options['help'] ? 0 : 1);
}

$serviceshortname = 'ltuse_publish';
$roleshortname = 'ltcpublisher';
$username = clean_param($options['username'], PARAM_USERNAME);

// The capabilities the publisher actually exercises. Kept explicit rather than cloning an
// archetype: this account should be able to do exactly this job and nothing else.
//
//   webservice/rest:use          -- without it EVERY call fails as an access control
//                                   exception that reads like a bad token. The single
//                                   most common cause of a "working" token that doesn't.
//   moodle/course:create/update  -- publish_moodle.py creates a course on first publish.
//   moodle/course:manageactivities, activityvisibility
//                                -- create_page and create_quiz add and hide modules.
//   moodle/question:*            -- import_questions writes into the course question bank.
//   moodle/course:viewhiddencourses
//                                -- courses are created hidden, and the publisher has to
//                                   be able to see its own work to update it.
$capabilities = [
    'local/ltuse:publish',
    'webservice/rest:use',
    'moodle/course:create',
    'moodle/course:update',
    'moodle/course:view',
    'moodle/course:viewhiddencourses',
    'moodle/course:visibility',
    'moodle/course:manageactivities',
    'moodle/course:activityvisibility',
    'moodle/course:managefiles',
    'moodle/course:changesummary',
    'moodle/question:add',
    'moodle/question:editall',
    'moodle/question:managecategory',
    'moodle/question:viewall',
];

$syscontext = context_system::instance();

// --- role ---------------------------------------------------------------------------
$role = $DB->get_record('role', ['shortname' => $roleshortname]);
if (!$role) {
    $roleid = create_role(
        'LTC curriculum publisher',
        $roleshortname,
        'Publishes courses from the LTC curriculum repository over the web service. '
            . 'Created by local_ltuse/cli/setup_publishing.php -- edit that script rather '
            . 'than this role, so the production server gets the same thing.'
    );
    $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
    cli_writeln("  role       created '{$roleshortname}' (id {$role->id})");
} else {
    cli_writeln("  role       reusing '{$roleshortname}' (id {$role->id})");
}

// Assignable at system level so one assignment covers every course it will create.
set_role_contextlevels($role->id, [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE]);

$granted = 0;
foreach ($capabilities as $cap) {
    if (!get_capability_info($cap)) {
        cli_problem("  WARNING    capability does not exist on this site: {$cap}");
        continue;
    }
    assign_capability($cap, CAP_ALLOW, $role->id, $syscontext->id, true);
    $granted++;
}
cli_writeln("  caps       {$granted} of " . count($capabilities) . " granted at system context");

// --- user ---------------------------------------------------------------------------
$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
if (!$user) {
    if (empty($options['email'])) {
        cli_error("Account '{$username}' does not exist yet -- pass --email=ADDRESS to create it.");
    }
    // auth=webservice means this account cannot log in through the web UI at all. It only
    // ever acts through a token, which is exactly the blast radius we want.
    $new = new stdClass();
    $new->username = $username;
    $new->auth = 'webservice';
    $new->confirmed = 1;
    $new->mnethostid = $CFG->mnet_localhost_id;
    $new->email = clean_param($options['email'], PARAM_EMAIL);
    $new->firstname = 'LTC';
    $new->lastname = 'Publisher';
    $new->policyagreed = 1;
    $new->id = user_create_user($new, false, false);
    $user = $DB->get_record('user', ['id' => $new->id], '*', MUST_EXIST);
    cli_writeln("  user       created '{$username}' (id {$user->id}, auth=webservice)");
} else {
    cli_writeln("  user       reusing '{$username}' (id {$user->id})");
}

if (!user_has_role_assignment($user->id, $role->id, $syscontext->id)) {
    role_assign($role->id, $user->id, $syscontext->id);
    cli_writeln("  assign     role granted at system context");
} else {
    cli_writeln("  assign     role already held at system context");
}

// --- service authorisation -----------------------------------------------------------
$service = $DB->get_record('external_services',
    ['shortname' => $serviceshortname], '*', MUST_EXIST);

// The service is declared restrictedusers=1 in db/services.php, so being able to call the
// functions is not enough -- the account must also be on the service's authorised list.
if (!$DB->record_exists('external_services_users',
        ['externalserviceid' => $service->id, 'userid' => $user->id])) {
    $DB->insert_record('external_services_users', (object)[
        'externalserviceid' => $service->id,
        'userid' => $user->id,
        'timecreated' => time(),
    ]);
    cli_writeln("  service    authorised on '{$serviceshortname}'");
} else {
    cli_writeln("  service    already authorised on '{$serviceshortname}'");
}

// --- token ---------------------------------------------------------------------------
$existing = $DB->get_records('external_tokens', [
    'userid' => $user->id,
    'externalserviceid' => $service->id,
    'tokentype' => EXTERNAL_TOKEN_PERMANENT,
]);

if ($existing && $options['rotate']) {
    $DB->delete_records('external_tokens', [
        'userid' => $user->id,
        'externalserviceid' => $service->id,
        'tokentype' => EXTERNAL_TOKEN_PERMANENT,
    ]);
    $existing = [];
    cli_writeln("  token      previous token(s) revoked (--rotate)");
}

if ($existing) {
    $token = reset($existing)->token;
    cli_writeln("  token      reusing existing token (pass --rotate to replace it)");
} else {
    $token = external_generate_token(
        EXTERNAL_TOKEN_PERMANENT, $service, $user->id, $syscontext);
    cli_writeln("  token      new token generated");
}

$tokenfile = $options['token-file'];
if (file_put_contents($tokenfile, $token . "\n") === false) {
    cli_error("Could not write the token to {$tokenfile}");
}
chmod($tokenfile, 0600);

cli_writeln("");
cli_writeln("Token written to {$tokenfile} (mode 600). It is deliberately not printed here.");
cli_writeln("Site: {$CFG->wwwroot}");
