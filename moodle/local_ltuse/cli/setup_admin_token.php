<?php
/**
 * Give one site-team member their own 'LTC administration' token (spec 008, research R12).
 *
 * The person's account must already exist and hold local/ltuse:administer, which comes from
 * the ltctadmin role declared in moodle/site/roles.yaml: run `scripts/site_config.py apply`,
 * then assign that role to them at system level. This script refuses an account without the
 * capability rather than granting it, so who administers stays a deliberate, visible step.
 *
 * ONE TOKEN PER PERSON. Each site-team member runs scripts/ltct_admin.py with a token on their
 * own account, so Moodle's logs show who made each change (plan decision 7). Never share one.
 *
 * CORE APIS ONLY (constitution XI). The service authorisation goes through
 * webservice::add_ws_authorised_user(), the token through \core_external\util::generate_token(),
 * and a rotation through webservice::delete_user_ws_token() -- not the direct $DB writes that
 * setup_publishing.php still makes.
 *
 * THE TOKEN IS NEVER PRINTED. It is written to --token-file with mode 600, outside any
 * repository, and the person sets MOODLE_ADMIN_TOKEN from that file in their own terminal.
 * A token already issued cannot be read back, so without --rotate an existing token is left
 * alone and nothing is written.
 *
 * Usage (as the web server user, from the Moodle root):
 *   php public/local/ltuse/cli/setup_admin_token.php --username=<u> --token-file=/home/<u>/.ltct-admin-token
 *
 * Options:
 *   --username=NAME     the site-team member's own account (required)
 *   --token-file=PATH   where to write the token (required)
 *   --rotate            revoke their existing administration token(s) and issue a new one
 *   --help
 */

define('CLI_SCRIPT', true);

// Three levels up is public/config.php, as in setup_publishing.php.
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'username' => null,
    'token-file' => null,
    'rotate' => false,
], ['h' => 'help']);

if ($options['help'] || empty($options['username']) || empty($options['token-file'])) {
    cli_writeln(preg_replace('/^.*?\/\*\*|\*\/.*$/s', '', file_get_contents(__FILE__)));
    exit($options['help'] ? 0 : 1);
}

$serviceshortname = 'ltuse_admin';
$username = clean_param($options['username'], PARAM_USERNAME);
$syscontext = context_system::instance();
$webservice = new webservice();

// --- user ---------------------------------------------------------------------------
$user = core_user::get_user_by_username($username);
if (!$user || $user->deleted) {
    cli_error("No account '{$username}'. Create the person's own account first.");
}
if ($user->suspended) {
    cli_error("Account '{$username}' is suspended.");
}
if (!has_capability('local/ltuse:administer', $syscontext, $user)) {
    cli_error("Account '{$username}' does not hold local/ltuse:administer. Assign it the "
        . "ltctadmin role at system level (declared in moodle/site/roles.yaml) and run again.");
}
cli_writeln("  user       '{$username}' (id {$user->id}) holds local/ltuse:administer");

// --- service authorisation -----------------------------------------------------------
$service = $webservice->get_external_service_by_shortname($serviceshortname, MUST_EXIST);

// restrictedusers = 1 (db/services.php): holding the capability is not enough, the account
// must also be on the service's authorised list.
if (!$webservice->get_ws_authorised_user($service->id, $user->id)) {
    $webservice->add_ws_authorised_user((object)[
        'externalserviceid' => $service->id,
        'userid' => $user->id,
    ]);
    cli_writeln("  service    authorised on '{$serviceshortname}'");
} else {
    cli_writeln("  service    already authorised on '{$serviceshortname}'");
}

// --- token ---------------------------------------------------------------------------
$existing = array_filter($webservice->get_user_ws_tokens($user->id),
    fn($t) => (int)$t->wsid === (int)$service->id);

if ($existing && !$options['rotate']) {
    cli_writeln("  token      '{$username}' already has an administration token; nothing written.");
    cli_writeln("             Pass --rotate to revoke it and write a new one.");
    exit(0);
}

// Make the file private before anything is revoked or issued, so a file that cannot be
// prepared costs nothing: a new file is created empty under a 077 umask, an existing one is
// narrowed to 600 first (a umask does not change a file that is already there).
$tokenfile = $options['token-file'];
$old = umask(0077);
$prepared = touch($tokenfile) && chmod($tokenfile, 0600);
umask($old);
if (!$prepared) {
    cli_error("Could not create {$tokenfile} with mode 600; nothing was revoked or issued.");
}

foreach ($existing as $token) {
    $webservice->delete_user_ws_token($token->id);
}
if ($existing) {
    cli_writeln("  token      previous token(s) revoked (--rotate)");
}

$token = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, (int)$user->id,
    $syscontext, 0, '', 'ltct_admin.py');

if (file_put_contents($tokenfile, $token . "\n") === false) {
    cli_error("Could not write the token to {$tokenfile}. Run again with --rotate.");
}

cli_writeln("");
cli_writeln("Token written to {$tokenfile} (mode 600). It is deliberately not printed here.");
cli_writeln("Give it to '{$username}' only; they set MOODLE_ADMIN_TOKEN from it.");
cli_writeln("Site: {$CFG->wwwroot}");
