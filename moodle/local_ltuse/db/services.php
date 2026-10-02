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
