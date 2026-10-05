<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\course_mentor_records;

/**
 * Record or remove ONE one-course or cohort mentor, as previewed, then bring the course's
 * course mentors into step (spec 008, US5; research R10, R15).
 *
 * Get-or-create (a duplicate-key error from a racing retry counts as success) or delete on
 * local_ltuse_course_mentor, then course_mentor_sync::sync_course(), which does nothing while
 * local_ltuse/coursementorsync is 0. The row is classified again: would_change is applied;
 * unchanged, after a would_change preview, is already_done; anything else is refused as changed
 * since the preview.
 */
class admin_apply_course_mentors extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'row' => admin_preview_course_mentors::row_structure(),
            'expectedoutcome' => new external_value(PARAM_ALPHAEXT, 'The row\'s outcome in the preview'),
            'remove' => new external_value(PARAM_BOOL, 'Remove the record rather than add it', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(array $row, string $expectedoutcome, bool $remove = false): array {
        ['row' => $row, 'expectedoutcome' => $expectedoutcome, 'remove' => $remove] = self::validate_parameters(
            self::execute_parameters(), ['row' => $row, 'expectedoutcome' => $expectedoutcome, 'remove' => $remove]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);

        $found = course_mentor_records::resolve([$row]);
        if ($found['refusal'] !== '') {
            return ['row' => (int)$row['row'], 'outcome' => '', 'status' => 'refused', 'reason' => $found['refusal']];
        }
        admin_preview_course_mentors::require_course_capabilities($found['courses']);
        return course_mentor_records::apply_row($row, $found, $remove, $expectedoutcome);
    }

    public static function execute_returns(): external_single_structure {
        return admin_apply_suspension::row_answer();
    }
}
