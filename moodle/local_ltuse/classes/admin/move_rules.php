<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Per learner and course, what a move between organisations does (spec 008, research R4, R8).
 *
 * kept, gained, suspended_by_rule or lost per course, and flagged_protection per learner when
 * their effective protection is below the new organisation's minimum. A learner with any lost
 * course, or flagged_protection, is refused as a whole (spec 002 FR-017).
 *
 * PURE: no Moodle call, no database read, so tests/admin_harness.php tests every case
 * without Moodle. classify() lands with user story 3 (task T051).
 */
class move_rules {
}
