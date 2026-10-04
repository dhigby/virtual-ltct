<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * May a cohort be enrolled into a course, and with which role? (spec 008, research R4, R7)
 *
 * The table of research R7, from spec 002 R8, R10, R11 and FR-019: an organisation's cohort
 * as Student in a shared (ltct:published) course or in its own organisation-only course; its
 * managers cohort as orgmanager only in its own organisation-only course; ltct:mentors and
 * every other cohort refused, as is every pilot, office-hours or non-ltct: course.
 *
 * PURE: no Moodle call, no database read, so tests/admin_harness.php tests every cell
 * without Moodle. decide() lands with user story 2 (task T041).
 */
class enrolment_rules {
}
