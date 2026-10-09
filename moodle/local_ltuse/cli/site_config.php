<?php
/**
 * Apply the repo's site declaration to this Moodle, or report how this Moodle differs from it.
 *
 * Never run this by hand. scripts/site_config.py renders the YAML under moodle/site/ into
 * JSON and streams it here on stdin, over ssh or locally. That is how a secret reaches this
 * script without touching disk, argv or a log (specs/001-site-config-as-code, research R3).
 *
 *   --mode=apply   check every blocking condition first and write nothing if any fails;
 *                  then change only what differs, and summarise what changed
 *   --mode=drift   write nothing; report every difference and exit 1 if there is one
 *   --json         one JSON report instead of lines
 *
 * The payload names the site it was meant for (MOODLE_URL). A payload aimed at another site
 * is refused with exit 2, so a mistyped ssh alias cannot configure the wrong server.
 *
 * Exit codes: 0 applied or no differences, 1 blocked, failed or drift found, 2 usage error.
 */

define('CLI_SCRIPT', true);

// Same path as setup_publishing.php: public/config.php, the shim that loads the real one.
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');

use local_ltuse\siteconfig\applier;
use local_ltuse\siteconfig\drift;
use local_ltuse\siteconfig\inspector;
use local_ltuse\siteconfig\report;

[$options, $unrecognised] = cli_get_params(['mode' => null, 'json' => false, 'help' => false], ['h' => 'help']);

$mode = $options['mode'];
$report = new report(in_array($mode, ['apply', 'drift'], true) ? $mode : 'drift', (bool)$options['json']);

if ($options['help'] || $unrecognised || !in_array($mode, ['apply', 'drift'], true)) {
    $report->usage_error('usage: site_config.php --mode=apply|drift [--json], with the declaration as JSON on stdin');
    exit($report->finish());
}

$payload = json_decode(stream_get_contents(STDIN), true);
if (!is_array($payload)) {
    $report->usage_error('stdin is not a JSON declaration; run this through scripts/site_config.py');
    exit($report->finish());
}
if (($payload['mode'] ?? null) !== $mode) {
    $report->usage_error("the payload was rendered for mode '" . ($payload['mode'] ?? '') . "', not '{$mode}'");
    exit($report->finish());
}

// Name the server before anything else, then make sure it is the one the operator meant.
$report->start($CFG->wwwroot, $CFG->release);
$target = rtrim((string)($payload['target_url'] ?? ''), '/');
if ($target !== rtrim($CFG->wwwroot, '/')) {
    $report->usage_error("MOODLE_URL is '{$target}' but this server is '{$CFG->wwwroot}'");
    exit($report->finish());
}

\core\session\manager::set_user(get_admin());
$report->mark_secrets($payload['settings'] ?? []);

$inspector = new inspector($payload);
$runner = $mode === 'apply' ? new applier($inspector, $report) : new drift($inspector, $report);
$runner->run();

exit($report->finish());
