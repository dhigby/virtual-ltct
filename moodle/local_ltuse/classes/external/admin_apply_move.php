<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\move_service;

/**
 * Move ONE learner to another organisation, as previewed (spec 008, US3; research R8, R15).
 *
 * Writes the organisation field and nothing else, then user_updated, so the learner's cohorts
 * follow and core suspends the old cohort-sync enrolments with their history kept. The learner
 * is classified again under the per-email lock: would_move is applied only if it would suspend
 * or lose nothing expectedcourses did not show; moved is already_done; anything else (a lost
 * course, say) is refused. Never reads or sets protection.
 */
class admin_apply_move extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'row' => admin_preview_move::row_structure(),
            'expectedoutcome' => new external_value(PARAM_ALPHAEXT, 'The learner\'s outcome in the preview'),
            'expectedcourses' => new external_multiple_structure(admin_preview_move::course_structure(),
                'The learner\'s courses in the preview', VALUE_DEFAULT, []),
        ]);
    }

    public static function execute(array $row, string $expectedoutcome, array $expectedcourses = []): array {
        $params = self::validate_parameters(self::execute_parameters(), ['row' => $row,
            'expectedoutcome' => $expectedoutcome, 'expectedcourses' => $expectedcourses]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        require_capability('moodle/user:update', $context);

        return move_service::apply_row($params['row'], $params['expectedoutcome'], $params['expectedcourses']);
    }

    public static function execute_returns(): external_single_structure {
        return admin_apply_suspension::row_answer();
    }
}
