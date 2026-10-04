<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\suspension_service;

/**
 * Preview suspending or reactivating accounts (spec 008, US3; research R6).
 *
 * `ltct_admin.py suspend|reactivate FILE` (or `--email E`) calls this first. Per row:
 * would_change, unchanged (already suspended, or already active) or rejected with a reason (no
 * account, two accounts, a site administrator, your own account).
 *
 * Read-only. People come back masked unless showpeople is set, and never with a name or a
 * database id.
 */
class admin_preview_suspension extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'rows' => new external_multiple_structure(self::row_structure(), 'The file\'s rows, in order'),
            'suspend' => new external_value(PARAM_BOOL, 'True to suspend, false to reactivate'),
            'showpeople' => new external_value(PARAM_BOOL, 'Return emails unmasked', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * One suspension row (data-model section 1). Shared with apply_suspension.
     *
     * @return external_single_structure
     */
    public static function row_structure(): external_single_structure {
        return new external_single_structure([
            'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
            'email' => new external_value(PARAM_RAW_TRIMMED, 'Email address: the match key'),
        ]);
    }

    public static function execute(array $rows, bool $suspend, bool $showpeople = false): array {
        ['rows' => $rows, 'suspend' => $suspend, 'showpeople' => $showpeople] = self::validate_parameters(
            self::execute_parameters(), ['rows' => $rows, 'suspend' => $suspend, 'showpeople' => $showpeople]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        require_capability('moodle/user:update', $context);

        return suspension_service::preview($rows, $suspend, $showpeople);
    }

    public static function execute_returns(): external_single_structure {
        return self::rows_returns('would_change, unchanged or rejected');
    }

    /**
     * The shape every per-row preview returns: a file-level refusal, or one result per row.
     * Shared with the membership preview.
     *
     * @param string $outcomes the outcomes this preview gives, for the description
     * @return external_single_structure
     */
    public static function rows_returns(string $outcomes): external_single_structure {
        return new external_single_structure([
            'refusal' => new external_value(PARAM_RAW,
                'Why the whole file is refused before any row; empty when it is not'),
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
                    'key' => new external_value(PARAM_RAW, 'The masked email, or the email with showpeople'),
                    'outcome' => new external_value(PARAM_ALPHAEXT, $outcomes),
                    'reason' => new external_value(PARAM_RAW, 'Why, or a note for a row that proceeds'),
                    'changes' => new external_multiple_structure(
                        new external_value(PARAM_RAW, 'One change applying the row makes'),
                        'What applying the row will do'),
                ])
            ),
        ]);
    }
}
