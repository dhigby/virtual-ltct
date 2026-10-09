<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.
//
// Moodle has no core web service that writes a quiz. This plugin adds the few functions
// the publisher needs, each a thin wrapper over the same internal APIs the web UI calls,
// so that scripts/publish_moodle.py can talk plain REST and the contract is defined
// around our content model rather than bent to fit someone else's.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_ltuse';
$plugin->version   = 2026101000;   // Community space (spec 005), first bump. It ships:
                                   // the local_ltuse_digest_override table (savepoint
                                   // 2026101000), admin\digest_overrides, trackforums and
                                   // cli/trackforums_existing.php; the db/events.php
                                   // observers on \mod_forum\event\discussion_created,
                                   // post_created and \core\event\user_enrolment_deleted
                                   // (digest record clean-up); mentor_subscriptions;
                                   // course_mentor_sync's overrides and subscriptions; and
                                   // the siteconfig\inbound kind. Once this stamp is
                                   // deployed, any later 005 change to db/ raises it
                                   // again. Before it, 2026100901:
                                   // simple learner experience (spec 007): the Next button
                                   // (the before_footer_html_generation
                                   // hook), learner_home, the dashboard declaration and
                                   // update_sections' summary parameter. No schema, so no
                                   // savepoint. Before it, identity protection (spec 016,
                                   // 2026100900): two tables
                                   // (local_ltuse_protection, _protection_log), two
                                   // capabilities, the before_user_updated hook, two
                                   // observers, two tasks, one web service and the
                                   // protectionchanged message. db/upgrade.php saves its
                                   // savepoint at 2026100900, after administration's.
                                   // Before it, administration (spec 008, 2026100801): the
                                   // ltuse_admin service, the local/ltuse:administer
                                   // capability and the coursementorsync setting
                                   // (2026100800); then the course-mentor table
                                   // (local_ltuse_course_mentor, savepoint 2026100801), its
                                   // sync, observers and hourly reconcile. Before them,
                                   // managers' own people (spec 002 amendment 2026-10-02,
                                   // 2026100602): the "My organisation" page and its actions,
                                   // the local_ltuse_org_contact table and the hourly
                                   // reconcile_org_contacts task, the cohort observers, and
                                   // the local_ltuse_place_course web service. Before it,
                                   // manage mentors (spec 003 Phase B, 2026100601):
                                   // mentors.php and the profile link, no schema change; and
                                   // learning pathways (spec 006, 2026100600): the pathway
                                   // tables (local_ltuse_course_pathway, _role_pathway,
                                   // _role_pathway_comp, _pathway_cohort), slug and url on
                                   // local_ltuse_competency, set_course_pathway. Before them,
                                   // events and office hours (spec 011, 2026100400): calendar
                                   // change notices, the office-hours sync, booking notices
                                   // and their table (local_ltuse_booking), the time zone
                                   // notice. After open courses (spec 002, 2026100302): the course
                                   // discussion is always open, drift checks each published
                                   // ltct: course's group mode, and a managers cohort synced
                                   // into a shared course blocks apply. Before them: mentor
                                   // relationship (spec 003, 2026100301), badges and
                                   // certificates (spec 013, 2026100300), progress reporting
                                   // (spec 004, 2026100204) and ensure_discussion (spec 012,
                                   // 2026100203). moodle/site/site.yaml pins local_ltuse to
                                   // this stamp, and validate fails if they differ.

// PIN THIS DELIBERATELY. Moodle 5.0 moved the question bank out of course context and
// into its own activity module (mod_qbank), which changes how import_questions has to
// work -- see classes/util.php. A server upgrade that breaks question import should fail
// loudly at install time rather than mysteriously at publish time.
//
// VERIFY on the target instance: $CFG->version in config, or Site administration >
// Notifications. Set this to that release's version stamp before installing.
$plugin->requires  = 2026042000;   // Moodle 5.2 -- the release this was
                                   // installed and verified against.

// Constitution XI: every change to Moodle survives an upgrade. Declaring the branches this
// plugin was verified on makes Moodle refuse to install it on any other, so a major upgrade
// stops at the plugin check instead of running unverified code. Widen the range only after
// re-verifying on the new branch (502 = Moodle 5.2).
$plugin->supported = [502, 502];

$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.14.0';   // Spec 007. 0.13.0 (spec 016) is main's release before it.

// No third-party dependencies, deliberately. Sections were originally going to be
// local_wsmanagesections' job, but it could not be installed here, so local_ltuse grew an
// update_sections function of its own over core's course_create_sections_if_missing() and
// course_update_section(). That leaves nothing but Moodle core on the publisher's
// critical path, and one less plugin to re-verify on each upgrade.
$plugin->dependencies = [];
