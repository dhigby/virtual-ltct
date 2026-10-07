<?php
/**
 * Read tracking on for existing accounts (spec 005, Q18 and round 2).
 *
 *   (no option)   switch read tracking on for every non-deleted, non-guest account that has
 *                 it off, through user_update_user(); see classes/trackforums.php
 *
 * New accounts take the site default (defaultpreference_trackforums: 1, applied by
 * scripts/site_config.py); this is the one-off for the accounts made before it. Run once on
 * the server after the deploy that carries it. Idempotent: a second run reports changed 0.
 *
 * Output is "seen N, changed M" and nothing else, never a name; it writes no file
 * (constitution III).
 *
 * Exit codes: 0 done, 1 failed, 2 usage error.
 */

define('CLI_SCRIPT', true);

// Same path as site_config.php: public/config.php, the shim that loads the real one.
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['help' => false], ['h' => 'help']);

if ($options['help'] || $unrecognised) {
    cli_writeln("usage: trackforums_existing.php\n");
    exit(2);
}

\core\session\manager::set_user(get_admin());
$counts = \local_ltuse\trackforums::enable_existing();
cli_writeln("seen {$counts['seen']}, changed {$counts['changed']}");
exit(0);
