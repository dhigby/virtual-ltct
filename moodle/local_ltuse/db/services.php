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

    // Spec 016 (R12): not in the publishing service. Each checks
    // local_ltuse\protection\entitlement itself; the capability listed is the site team's.
    'local_ltuse_set_protection' => [
        'classname'    => 'local_ltuse\external\set_protection',
        'description'  => 'Grant, change or remove one person\'s identity protection. Never '
                        . 'returns a real identity.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:manageprotection',
    ],
    'local_ltuse_set_org_protection' => [
        'classname'    => 'local_ltuse\external\set_org_protection',
        'description'  => 'Set an organisation\'s minimum identity protection level. Site team '
                        . 'only.',
        'type'         => 'write',
        'ajax'         => false,
        'capabilities' => 'local/ltuse:manageorgprotection',
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
];
