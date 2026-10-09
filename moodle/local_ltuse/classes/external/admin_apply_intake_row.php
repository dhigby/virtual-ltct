<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\intake_service;

/**
 * Apply ONE intake row (spec 008, US1; research R2, R3, R5, R15).
 *
 * `ltct_admin.py intake FILE --apply --confirm CODE` previews again, then calls this once per
 * row that proceeds, with that row's fresh outcome as expectedoutcome. One row per call, so a
 * refusal or a lost connection affects one row and a retry re-sends exactly one row.
 *
 * Under a per-email lock the row is classified again: the same outcome is applied; an outcome
 * further along new -> will_set_org -> will_enrol -> unchanged finishes what remains, or is
 * already_done; anything else is refused as changed since the preview. A refusal is a result,
 * not an exception, so one bad row never stops the others.
 */
class admin_apply_intake_row extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'row' => admin_preview_intake::row_structure(),
            'expectedoutcome' => new external_value(PARAM_ALPHAEXT, 'The row\'s outcome in the preview'),
        ]);
    }

    public static function execute(array $row, string $expectedoutcome): array {
        ['row' => $row, 'expectedoutcome' => $expectedoutcome] = self::validate_parameters(
            self::execute_parameters(), ['row' => $row, 'expectedoutcome' => $expectedoutcome]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        require_capability('moodle/user:create', $context);

        return intake_service::apply_row($row, $expectedoutcome);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
            'outcome' => new external_value(PARAM_ALPHAEXT, 'The row\'s outcome when it was applied'),
            'status' => new external_value(PARAM_ALPHAEXT, 'done, already_done or refused'),
            'reason' => new external_value(PARAM_RAW, 'Why, when refused'),
        ]);
    }
}
