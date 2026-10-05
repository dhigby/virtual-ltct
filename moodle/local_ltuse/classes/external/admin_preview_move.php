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
 * The counted dry run before moving learners between organisations (spec 008, US3; research
 * R8; spec 002 FR-017).
 *
 * `ltct_admin.py move FILE` calls this first. Every new organisation's cohort must exist, or
 * the whole file is refused naming it. Per learner: would_move, moved, lost or rejected, and per
 * course kept, gained, suspended_by_rule or lost. A learner with any lost course will not be
 * moved.
 *
 * Read-only. People come back masked unless showpeople is set; courses by idnumber.
 */
class admin_preview_move extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'rows' => new external_multiple_structure(self::row_structure(), 'The file\'s rows, in order'),
            'showpeople' => new external_value(PARAM_BOOL, 'Return emails unmasked', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * One move-file row (data-model section 1). Shared with apply_move.
     *
     * @return external_single_structure
     */
    public static function row_structure(): external_single_structure {
        return new external_single_structure([
            'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
            'email' => new external_value(PARAM_RAW_TRIMMED, 'Email address: the match key'),
            'organisation' => new external_value(PARAM_ALPHANUMEXT, 'The new organisation key'),
        ]);
    }

    /**
     * One course of a learner's move. Shared with apply_move's expectedcourses.
     *
     * @return external_single_structure
     */
    public static function course_structure(): external_single_structure {
        return new external_single_structure([
            'course' => new external_value(PARAM_RAW, 'The course idnumber'),
            'outcome' => new external_value(PARAM_ALPHAEXT, 'kept, gained, suspended_by_rule or lost'),
        ]);
    }

    public static function execute(array $rows, bool $showpeople = false): array {
        ['rows' => $rows, 'showpeople' => $showpeople] = self::validate_parameters(
            self::execute_parameters(), ['rows' => $rows, 'showpeople' => $showpeople]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        require_capability('moodle/user:update', $context);

        return move_service::preview($rows, $showpeople);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'refusal' => new external_value(PARAM_RAW,
                'Why the whole file is refused before any row; empty when it is not'),
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
                    'key' => new external_value(PARAM_RAW, 'The masked email, or the email with showpeople'),
                    'outcome' => new external_value(PARAM_ALPHAEXT,
                        'would_move, moved, lost or rejected'),
                    'reason' => new external_value(PARAM_RAW, 'Why, for an outcome that does not proceed'),
                    'changes' => new external_multiple_structure(
                        new external_value(PARAM_RAW, 'set_org:<key>, suspend:<course> or enrol:<course>'),
                        'What applying the row will do'),
                    'courses' => new external_multiple_structure(self::course_structure(),
                        'Per course, what the move does'),
                ])
            ),
        ]);
    }
}
