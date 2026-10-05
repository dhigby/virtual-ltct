<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\suspension_service;

/**
 * Suspend or reactivate ONE account, as previewed (spec 008, US3; research R6, R15).
 *
 * Calls spec 002's organisation\actions::do_suspend() or do_reactivate(): sessions end, the
 * account is marked suspended, and every enrolment, grade and completion is kept. The row is
 * classified again: would_change is applied; unchanged, after a would_change preview, is
 * already_done; anything else is refused as changed since the preview.
 */
class admin_apply_suspension extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'row' => admin_preview_suspension::row_structure(),
            'suspend' => new external_value(PARAM_BOOL, 'True to suspend, false to reactivate'),
            'expectedoutcome' => new external_value(PARAM_ALPHAEXT, 'The row\'s outcome in the preview'),
        ]);
    }

    public static function execute(array $row, bool $suspend, string $expectedoutcome): array {
        ['row' => $row, 'suspend' => $suspend, 'expectedoutcome' => $expectedoutcome] = self::validate_parameters(
            self::execute_parameters(), ['row' => $row, 'suspend' => $suspend, 'expectedoutcome' => $expectedoutcome]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        require_capability('moodle/user:update', $context);

        return suspension_service::apply_row($row, $suspend, $expectedoutcome);
    }

    public static function execute_returns(): external_single_structure {
        return self::row_answer();
    }

    /**
     * The answer every one-row apply gives. Shared with apply_move and apply_cohort_members.
     *
     * @return external_single_structure
     */
    public static function row_answer(): external_single_structure {
        return new external_single_structure([
            'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
            'outcome' => new external_value(PARAM_ALPHAEXT, 'The row\'s outcome when it was applied'),
            'status' => new external_value(PARAM_ALPHAEXT, 'done, already_done or refused'),
            'reason' => new external_value(PARAM_RAW, 'Why, when refused; or a note'),
        ]);
    }
}
