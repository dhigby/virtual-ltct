<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * The course mentors a learner should have in one course (spec 008, research R4, R10).
 *
 * First match wins: one-course mentors recorded for (learner, course); else the mentors
 * recorded for each cohort that enrols the learner there; else the learner's default mentors
 * (the mentor role in their user context, spec 003). Only learners with an active Student
 * enrolment through cohort sync or the Organisation enrolment count (data-model section 4).
 *
 * PURE: no Moodle call, no database read, so tests/admin_harness.php tests every case
 * without Moodle. target() lands with user story 5 (task T062).
 */
class course_mentor_rules {
}
