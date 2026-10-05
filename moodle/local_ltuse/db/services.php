<?php
// External function and service declarations for local_ltuse.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_ltuse_get_course_manifest' => [
        'classname'    => 'local_ltuse\external\get_course_manifest',
        'description'  => 'Return the repo-to-Moodle map for one course: its sections and '
                        . 'every course module carrying an ltct: idnumber.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish',
    ],
    'local_ltuse_update_sections' => [
        'classname'    => 'local_ltuse\external\update_sections',
        'description'  => 'Create the sections of a course and name them. Replaces the '
                        . 'local_wsmanagesections dependency.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish',
    ],
    'local_ltuse_create_page' => [
        'classname'    => 'local_ltuse\external\create_page',
        'description'  => 'Create or update a mod_page in a course, identified by its '
                        . 'course-module idnumber. Idempotent.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish',
    ],
    'local_ltuse_import_questions' => [
        'classname'    => 'local_ltuse\external\import_questions',
        'description'  => 'Import Moodle XML questions into a category inside the course '
                        . 'question bank, creating the qbank instance and category if '
                        . 'they do not exist.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish',
    ],
    'local_ltuse_hide_modules' => [
        'classname'    => 'local_ltuse\external\hide_modules',
        'description'  => 'Retire modules a course no longer has, identified by their '
                        . 'course-module idnumbers: hide them and move them into a hidden '
                        . 'Retired section at the end. Never deletes.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish, moodle/course:activityvisibility, '
                        . 'moodle/course:manageactivities, moodle/course:update',
    ],
    'local_ltuse_create_quiz' => [
        'classname'    => 'local_ltuse\external\create_quiz',
        'description'  => 'Create or update a mod_quiz and populate its slots from a '
                        . 'question bank category. Idempotent.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish',
    ],
    'local_ltuse_set_course_completion' => [
        'classname'    => 'local_ltuse\external\set_course_completion',
        'description'  => 'Make a course\'s activity completion criteria exactly its visible '
                        . 'published modules, one criterion at a time. Never clears '
                        . 'learners\' course completions. Idempotent.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish, moodle/course:update',
    ],
    'local_ltuse_set_course_competencies' => [
        'classname'    => 'local_ltuse\external\set_course_competencies',
        'description'  => 'Replace the competencies a published course aims at, by name. '
                        . 'Fails closed on an unknown name. Idempotent.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish',
    ],
    'local_ltuse_ensure_discussion' => [
        'classname'    => 'local_ltuse\external\ensure_discussion',
        'description'  => 'Create the discussion forum of a course if it is absent, and set only its '
                        . 'group mode (separate or visible groups, no grouping). Never writes '
                        . 'a discussion or post.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish, moodle/course:manageactivities',
    ],
    'local_ltuse_set_course_recognition' => [
        'classname'    => 'local_ltuse\external\set_course_recognition',
        'description'  => 'Create or reword a published course\'s completion badge, activate it '
                        . 'on a delivery publish, and on delivery make its certificate activity. '
                        . 'Never deactivates a badge or deletes a certificate. Idempotent.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish, moodle/badges:createbadge, '
                        . 'moodle/badges:configurecriteria, moodle/badges:configuredetails, '
                        . 'moodle/badges:configuremessages, mod/customcert:addinstance',
    ],

    // Spec 016 (R12): not in the publishing service. It checks
    // local_ltuse\protection\entitlement itself; the capability listed is the site team's.
    'local_ltuse_set_protection' => [
        'classname'    => 'local_ltuse\external\set_protection',
        'description'  => 'Grant, change or remove one person\'s identity protection. Never '
                        . 'returns a real identity.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:manageprotection',
    ],
    'local_ltuse_place_course' => [
        'classname'    => 'local_ltuse\external\place_course',
        'description'  => 'Move a published course into its category by idnumber (ltct:org:<key>, '
                        . 'ltct:pilots or ltct:published), only when it is elsewhere. Spec 002 '
                        . 'organisation-only courses. Idempotent.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish',
    ],
    // Spec 006: pathways.
    'local_ltuse_set_course_pathway' => [
        'classname'    => 'local_ltuse\external\set_course_pathway',
        'description'  => 'Record whether a published course is delivered and the level it aims '
                        . 'at, and announce each pathway it joined or left. Never enrols '
                        . 'anyone. Idempotent.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:publish',
    ],

    // Spec 008: administration. The site team's functions, called by scripts/ltct_admin.py
    // through the 'LTC administration' service below and no other. Each checks
    // local/ltuse:administer, then the core capability for the write it makes. Entries are
    // added as each function lands (specs/008-admin-tooling/contracts/admin-service.md).
    'local_ltuse_admin_check' => [
        'classname'    => 'local_ltuse\external\admin_check',
        'description'  => 'Report what the administration tool relies on: the settings it '
                        . 'needs, the capabilities the caller lacks, and whether learning '
                        . 'pathways and identity protection are installed. Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer',
    ],
    'local_ltuse_admin_list' => [
        'classname'    => 'local_ltuse\external\admin_list',
        'description'  => 'List the ltct: cohorts or ltct: courses the site team may name, '
                        . 'optionally for one organisation. Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/cohort:view',
    ],
    'local_ltuse_admin_preview_intake' => [
        'classname'    => 'local_ltuse\external\admin_preview_intake',
        'description'  => 'Preview an intake file: what applying each row would do, with '
                        . 'people masked unless asked. Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/user:create',
    ],
    'local_ltuse_admin_apply_intake_row' => [
        'classname'    => 'local_ltuse\external\admin_apply_intake_row',
        'description'  => 'Apply one intake row as previewed: create the account, protect it, '
                        . 'set its organisation and enrol it in the row\'s courses.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/user:create',
    ],
    'local_ltuse_admin_preview_cohort_enrolment' => [
        'classname'    => 'local_ltuse\external\admin_preview_cohort_enrolment',
        'description'  => 'Preview enrolling a cohort into a course or a pathway by cohort '
                        . 'sync, or disabling that enrolment. Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, enrol/cohort:config',
    ],
    'local_ltuse_admin_apply_cohort_enrolment' => [
        'classname'    => 'local_ltuse\external\admin_apply_cohort_enrolment',
        'description'  => 'Enrol one cohort into one course by cohort sync, or disable that '
                        . 'enrolment, as previewed. Never deletes an enrolment method.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, enrol/cohort:config',
    ],
    'local_ltuse_admin_apply_pathway_assignment' => [
        'classname'    => 'local_ltuse\external\admin_apply_pathway_assignment',
        'description'  => 'Give a cohort a learning pathway that enrols, if the cohort may be '
                        . 'enrolled into every course on it.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/cohort:assign',
    ],
    'local_ltuse_admin_preview_suspension' => [
        'classname'    => 'local_ltuse\external\admin_preview_suspension',
        'description'  => 'Preview suspending or reactivating accounts, with people masked '
                        . 'unless asked. Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/user:update',
    ],
    'local_ltuse_admin_apply_suspension' => [
        'classname'    => 'local_ltuse\external\admin_apply_suspension',
        'description'  => 'Suspend or reactivate one account as previewed. Sessions end on '
                        . 'suspension; enrolments, grades and completion are kept.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/user:update',
    ],
    'local_ltuse_admin_preview_move' => [
        'classname'    => 'local_ltuse\external\admin_preview_move',
        'description'  => 'The counted dry run before moving learners between organisations: '
                        . 'per learner and course, what is kept, gained, suspended or lost. '
                        . 'Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/user:update',
    ],
    'local_ltuse_admin_apply_move' => [
        'classname'    => 'local_ltuse\external\admin_apply_move',
        'description'  => 'Move one learner to another organisation as previewed, by setting '
                        . 'their organisation field only. Refused if any course would be lost.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/user:update',
    ],
    'local_ltuse_admin_preview_cohort_members' => [
        'classname'    => 'local_ltuse\external\admin_preview_cohort_members',
        'description'  => 'Preview adding people to, or removing them from, managers cohorts '
                        . 'and the mentors cohort. Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/cohort:assign',
    ],
    'local_ltuse_admin_apply_cohort_members' => [
        'classname'    => 'local_ltuse\external\admin_apply_cohort_members',
        'description'  => 'Add one person to, or remove them from, a managers cohort or the '
                        . 'mentors cohort, as previewed.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/cohort:assign',
    ],
    'local_ltuse_admin_summary' => [
        'classname'    => 'local_ltuse\external\admin_summary',
        'description'  => 'One organisation\'s cohort membership and cohort enrolments: counts, '
                        . 'and people masked unless asked. Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/cohort:view',
    ],
    'local_ltuse_admin_preview_mentors' => [
        'classname'    => 'local_ltuse\external\admin_preview_mentors',
        'description'  => 'Preview mentor relationships made in bulk, or all of one mentor\'s '
                        . 'relationships ended, with people masked unless asked. Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/role:assign',
    ],
    'local_ltuse_admin_apply_mentors' => [
        'classname'    => 'local_ltuse\external\admin_apply_mentors',
        'description'  => 'Make one mentor relationship, or end one of a mentor\'s '
                        . 'relationships, as previewed.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/role:assign',
    ],
    'local_ltuse_admin_preview_course_mentors' => [
        'classname'    => 'local_ltuse\external\admin_preview_course_mentors',
        'description'  => 'Preview recording or removing one-course and cohort mentors. '
                        . 'Changes nothing.',
        'type'         => 'read',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/role:assign, moodle/course:managegroups',
    ],
    'local_ltuse_admin_apply_course_mentors' => [
        'classname'    => 'local_ltuse\external\admin_apply_course_mentors',
        'description'  => 'Record or remove one one-course or cohort mentor as previewed, then '
                        . 'bring the course\'s course mentors into step.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:administer, moodle/role:assign, moodle/course:managegroups',
    ],
];

// One service, so a single token grants exactly these functions and nothing else.
// restrictedusers = 1 means an administrator must link the publishing account to it from
// Site administration > Server > Web services > External services > Authorised users:
// this token can rewrite course content, so it should belong to one known account, not
// to anyone who happens to have the capability.
$services = [
    'LTC curriculum publishing' => [
        'functions' => [
            'local_ltuse_get_course_manifest',
            'local_ltuse_update_sections',
            'local_ltuse_create_page',
            'local_ltuse_import_questions',
            'local_ltuse_create_quiz',
            'local_ltuse_hide_modules',
            'local_ltuse_set_course_completion',
            'local_ltuse_set_course_competencies',
            'local_ltuse_ensure_discussion',
            'local_ltuse_set_course_recognition',
            'local_ltuse_place_course',
            // Spec 006: pathways.
            'local_ltuse_set_course_pathway',
            // Core functions the publisher also needs. Listed here so one token covers
            // the whole publish rather than the operator wiring up several services.
            'core_course_create_courses',
            'core_course_update_courses',
            'core_course_get_courses_by_field',
            'core_course_get_contents',
            'core_webservice_get_site_info',
        ],
        'requiredcapability' => 'local/ltuse:publish',
        'restrictedusers'    => 1,
        'enabled'            => 1,
        'shortname'          => 'ltuse_publish',
        'downloadfiles'      => 0,
        // Screenshots and diagrams are uploaded to a draft area first, then attached by
        // create_page. Without this the images never reach the course and the Android
        // app shows a lesson full of broken pictures.
        'uploadfiles'        => 1,
    ],

    // Spec 008: administration. A second service, so the site team's tool has a credential
    // distinct from the publisher's, holding only administration's functions (FR-009,
    // research R12). Each site-team member gets their own token on their own account
    // (cli/setup_admin_token.php), so Moodle's logs show who made each change. Removing this
    // entry deletes every token issued for it on the next upgrade.
    'LTC administration' => [
        'functions' => [
            'local_ltuse_admin_check',
            'local_ltuse_admin_list',
            'local_ltuse_admin_preview_intake',
            'local_ltuse_admin_apply_intake_row',
            'local_ltuse_admin_preview_cohort_enrolment',
            'local_ltuse_admin_apply_cohort_enrolment',
            'local_ltuse_admin_apply_pathway_assignment',
            'local_ltuse_admin_preview_suspension',
            'local_ltuse_admin_apply_suspension',
            'local_ltuse_admin_preview_move',
            'local_ltuse_admin_apply_move',
            'local_ltuse_admin_preview_cohort_members',
            'local_ltuse_admin_apply_cohort_members',
            'local_ltuse_admin_summary',
            'local_ltuse_admin_preview_mentors',
            'local_ltuse_admin_apply_mentors',
            'local_ltuse_admin_preview_course_mentors',
            'local_ltuse_admin_apply_course_mentors',
            'core_webservice_get_site_info',
        ],
        'requiredcapability' => 'local/ltuse:administer',
        'restrictedusers'    => 1,
        'enabled'            => 1,
        'shortname'          => 'ltuse_admin',
        'downloadfiles'      => 0,
        'uploadfiles'        => 0,
    ],
];
