<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\membership_service;

/**
 * Make ONE mentor relationship, or end ONE of a mentor's relationships, as previewed (spec
 * 008, US5; research R9, R15).
 *
 * A file row: role_assign() of spec 003's mentor role in the learner's user context, component
 * ''. An end-all row (endmentoremail and ref): role_unassign() for the learner the reference
 * names. The row is classified again: would_change is applied; unchanged, after a would_change
 * preview, is already_done; anything else is refused as changed since the preview. Spec 003's
 * observer then makes or removes the message contacts, and spec 008's the course-mentor
 * enrolments.
 */
class admin_apply_mentors extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'row' => admin_preview_mentors::row_structure(),
            'expectedoutcome' => new external_value(PARAM_ALPHAEXT, 'The row\'s outcome in the preview'),
            'endmentoremail' => new external_value(PARAM_RAW_TRIMMED,
                'For an end-all row: the mentor whose relationship ends', VALUE_DEFAULT, ''),
            'ref' => new external_value(PARAM_ALPHANUM, 'For an end-all row: the preview\'s reference',
                VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(array $row, string $expectedoutcome, string $endmentoremail = '',
            string $ref = ''): array {
        ['row' => $row, 'expectedoutcome' => $expectedoutcome, 'endmentoremail' => $endmentoremail, 'ref' => $ref] =
            self::validate_parameters(self::execute_parameters(), ['row' => $row,
                'expectedoutcome' => $expectedoutcome, 'endmentoremail' => $endmentoremail, 'ref' => $ref]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        require_capability('moodle/role:assign', $context);

        $setup = membership_service::mentor_setup();
        if ($setup['refusal'] !== '') {
            return ['row' => (int)$row['row'], 'outcome' => '', 'status' => 'refused', 'reason' => $setup['refusal']];
        }
        if ($endmentoremail !== '') {
            if ($ref === '') {
                return ['row' => (int)$row['row'], 'outcome' => '', 'status' => 'refused',
                    'reason' => get_string('admin:refusal:mentorsmode', 'local_ltuse')];
            }
            return membership_service::apply_end_row((int)$row['row'], $endmentoremail, $ref, $setup,
                $expectedoutcome);
        }
        return membership_service::apply_mentor_row($row, $setup, $expectedoutcome);
    }

    public static function execute_returns(): external_single_structure {
        return admin_apply_suspension::row_answer();
    }
}
