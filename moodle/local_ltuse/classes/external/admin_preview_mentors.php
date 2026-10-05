<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\membership_service;

/**
 * Preview mentor relationships made in bulk, or all of one mentor's ended (spec 008, US5;
 * research R9).
 *
 * `ltct_admin.py mentors assign FILE` sends the file's rows: per row would_change, unchanged
 * (the mentor already mentors the learner) or rejected with a reason (no account, two accounts,
 * a mentor not in ltct:mentors, a learner named as their own mentor).
 *
 * `ltct_admin.py mentors end --mentor E` sends endmentoremail instead: one row per learner the
 * mentor has now, each would_change and carrying a reference the apply sends back, never a
 * database id.
 *
 * The mentor role and ltct:mentors must exist, or the whole command is refused naming them.
 * Read-only. People come back masked unless showpeople is set.
 */
class admin_preview_mentors extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'rows' => new external_multiple_structure(self::row_structure(), 'The file\'s rows, in order',
                VALUE_DEFAULT, []),
            'endmentoremail' => new external_value(PARAM_RAW_TRIMMED,
                'Or: end all of this mentor\'s relationships', VALUE_DEFAULT, ''),
            'showpeople' => new external_value(PARAM_BOOL, 'Return emails unmasked', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * One mentors-file row (data-model section 1). Shared with apply_mentors, where an end-all
     * sends only the row number.
     *
     * @return external_single_structure
     */
    public static function row_structure(): external_single_structure {
        return new external_single_structure([
            'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
            'learneremail' => new external_value(PARAM_RAW_TRIMMED, 'The learner\'s email', VALUE_DEFAULT, ''),
            'mentoremail' => new external_value(PARAM_RAW_TRIMMED, 'The mentor\'s email', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(array $rows = [], string $endmentoremail = '', bool $showpeople = false): array {
        ['rows' => $rows, 'endmentoremail' => $endmentoremail, 'showpeople' => $showpeople] =
            self::validate_parameters(self::execute_parameters(),
                ['rows' => $rows, 'endmentoremail' => $endmentoremail, 'showpeople' => $showpeople]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        require_capability('moodle/role:assign', $context);

        if (($endmentoremail === '') === !$rows) {
            return ['refusal' => get_string('admin:refusal:mentorsmode', 'local_ltuse'), 'rows' => []];
        }
        $setup = membership_service::mentor_setup();
        if ($setup['refusal'] !== '') {
            return ['refusal' => $setup['refusal'], 'rows' => []];
        }
        if ($endmentoremail !== '') {
            return membership_service::preview_end($endmentoremail, $setup, $showpeople);
        }
        return membership_service::preview_mentors($rows, $setup, $showpeople);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'refusal' => new external_value(PARAM_RAW,
                'Why the whole command is refused before any row; empty when it is not'),
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'row' => new external_value(PARAM_INT, 'Row number in the file, or in the end-all list'),
                    'key' => new external_value(PARAM_RAW, 'The learner\'s masked email, or their email with showpeople'),
                    'outcome' => new external_value(PARAM_ALPHAEXT, 'would_change, unchanged or rejected'),
                    'reason' => new external_value(PARAM_RAW, 'Why, or a note for a row that proceeds'),
                    'changes' => new external_multiple_structure(
                        new external_value(PARAM_RAW, 'One change applying the row makes'),
                        'What applying the row will do'),
                    'ref' => new external_value(PARAM_ALPHANUM,
                        'An end-all row\'s reference to its learner, sent back by the apply; empty for a file row'),
                ])
            ),
        ]);
    }
}
