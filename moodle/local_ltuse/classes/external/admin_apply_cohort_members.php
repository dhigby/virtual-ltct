<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\membership_service;

/**
 * Add ONE person to, or remove them from, a managers cohort or ltct:mentors, as previewed (spec
 * 008, US3; research R9, R15).
 *
 * cohort_add_member() or, after checking membership, cohort_remove_member(). The row is
 * classified again: would_change is applied; unchanged, after a would_change preview, is
 * already_done; anything else is refused as changed since the preview.
 */
class admin_apply_cohort_members extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'row' => admin_preview_cohort_members::row_structure(),
            'expectedoutcome' => new external_value(PARAM_ALPHAEXT, 'The row\'s outcome in the preview'),
        ]);
    }

    public static function execute(array $row, string $expectedoutcome): array {
        ['row' => $row, 'expectedoutcome' => $expectedoutcome] = self::validate_parameters(
            self::execute_parameters(), ['row' => $row, 'expectedoutcome' => $expectedoutcome]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);

        $found = membership_service::resolve([$row]);
        if ($found['refusal'] !== '') {
            return ['row' => (int)$row['row'], 'outcome' => '', 'status' => 'refused', 'reason' => $found['refusal']];
        }
        foreach ($found['cohorts'] as $cohort) {
            require_capability('moodle/cohort:assign', context::instance_by_id($cohort->contextid));
        }
        return membership_service::apply_row($row, $found['cohorts'], $expectedoutcome);
    }

    public static function execute_returns(): external_single_structure {
        return admin_apply_suspension::row_answer();
    }
}
