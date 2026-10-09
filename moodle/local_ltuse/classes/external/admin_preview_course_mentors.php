<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\course_mentor_records;

/**
 * Preview recording, or removing, one-course and cohort mentors (spec 008, US5; research R10).
 *
 * `ltct_admin.py course-mentors FILE [--remove]` calls this first. Every course and cohort the
 * file names, and ltct:mentors, must exist, or the whole file is refused naming them. Per row:
 * would_change, unchanged, or rejected with a reason (not an ltct: course, no account, a mentor
 * not in ltct:mentors, both or neither of learner and cohort). A cohort row for a cohort not
 * enrolled in the course proceeds with a note.
 *
 * Read-only. People come back masked unless showpeople is set.
 */
class admin_preview_course_mentors extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'rows' => new external_multiple_structure(self::row_structure(), 'The file\'s rows, in order'),
            'remove' => new external_value(PARAM_BOOL, 'Remove the records rather than add them', VALUE_DEFAULT, false),
            'showpeople' => new external_value(PARAM_BOOL, 'Return emails unmasked', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * One course-mentors-file row (data-model section 1). Shared with apply_course_mentors.
     *
     * @return external_single_structure
     */
    public static function row_structure(): external_single_structure {
        return new external_single_structure([
            'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
            'courseidnumber' => new external_value(PARAM_RAW_TRIMMED, 'The course, ltct:<slug>'),
            'mentoremail' => new external_value(PARAM_RAW_TRIMMED, 'The course mentor\'s email'),
            'learneremail' => new external_value(PARAM_RAW_TRIMMED,
                'A one-course mentor\'s learner; empty for a cohort row', VALUE_DEFAULT, ''),
            'cohortidnumber' => new external_value(PARAM_RAW_TRIMMED,
                'The cohort whose mentors these are; empty for a one-course row', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(array $rows, bool $remove = false, bool $showpeople = false): array {
        ['rows' => $rows, 'remove' => $remove, 'showpeople' => $showpeople] = self::validate_parameters(
            self::execute_parameters(), ['rows' => $rows, 'remove' => $remove, 'showpeople' => $showpeople]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);

        $found = course_mentor_records::resolve($rows);
        if ($found['refusal'] !== '') {
            return ['refusal' => $found['refusal'], 'rows' => []];
        }
        self::require_course_capabilities($found['courses']);
        return course_mentor_records::preview($rows, $found, $remove, $showpeople);
    }

    /**
     * The sync enrols, assigns Teacher and groups in each course a row names; the caller must
     * be allowed to, there.
     *
     * @param \stdClass[] $courses resolve()'s courses
     */
    public static function require_course_capabilities(array $courses): void {
        foreach ($courses as $course) {
            $coursecontext = context_course::instance((int)$course->id);
            require_capability('moodle/role:assign', $coursecontext);
            require_capability('moodle/course:managegroups', $coursecontext);
        }
    }

    public static function execute_returns(): external_single_structure {
        return admin_preview_suspension::rows_returns('would_change, unchanged or rejected');
    }
}
