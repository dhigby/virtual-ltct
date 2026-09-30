<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.
//
// Moodle has no core web service that writes a quiz. This plugin adds the few functions
// the publisher needs, each a thin wrapper over the same internal APIs the web UI calls,
// so that scripts/publish_moodle.py can talk plain REST and the contract is defined
// around our content model rather than bent to fit someone else's.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ltuse';
$plugin->version   = 2026092901;

// PIN THIS DELIBERATELY. Moodle 5.0 moved the question bank out of course context and
// into its own activity module (mod_qbank), which changes how import_questions has to
// work -- see classes/util.php. A server upgrade that breaks question import should fail
// loudly at install time rather than mysteriously at publish time.
//
// VERIFY on the target instance: $CFG->version in config, or Site administration >
// Notifications. Set this to that release's version stamp before installing.
$plugin->requires  = 2026042000;   // Moodle 5.2 -- the release this was
                                   // installed and verified against.
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.2.0';

// No third-party dependencies, deliberately. Sections were originally going to be
// local_wsmanagesections' job, but it could not be installed here, so local_ltuse grew an
// update_sections function of its own over core's course_create_sections_if_missing() and
// course_update_section(). That leaves nothing but Moodle core on the publisher's
// critical path, and one less plugin to re-verify on each upgrade.
$plugin->dependencies = [];
