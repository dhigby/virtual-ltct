<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Classify one intake row against what Moodle holds (spec 008, research R4, R5, R15).
 *
 * Outcomes are the keys of data-model section 2: new, unchanged, will_set_org, will_enrol,
 * flagged_other_org, flagged_suspended, flagged_protection, waits, rejected. The "further
 * along" rule decides what an apply does when the row's state has moved since its preview:
 * the path is new -> will_set_org -> will_enrol -> unchanged.
 *
 * PURE: no Moodle call, no database read. intake_service gathers the facts, so
 * tests/admin_harness.php tests every case without Moodle. classify() and progress() land
 * with user story 1 (tasks T031).
 */
class intake_rules {
}
