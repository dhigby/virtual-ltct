<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\intake_service;

/**
 * Preview an intake file: what applying each row would do (spec 008, US1; research R2-R5).
 *
 * `ltct_admin.py intake FILE` sends every row of the operator's file, after its own offline
 * checks, and prints the counts, the masked rows that will not proceed and a confirmation
 * code. First, every course and organisation cohort the file names must exist; if one does
 * not, the result is a file-level refusal naming it and no row result (data-model section 1).
 *
 * Read-only: it creates, changes and emails nothing. People come back masked
 * (a***@example.org) unless showpeople is set, and never with a name or a database id.
 */
class admin_preview_intake extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'rows' => new external_multiple_structure(self::row_structure(), 'The file\'s rows, in order'),
            'showpeople' => new external_value(PARAM_BOOL, 'Return emails unmasked', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * One intake row, as the file has it (data-model section 1). Shared with apply_intake_row.
     *
     * @return external_single_structure
     */
    public static function row_structure(): external_single_structure {
        return new external_single_structure([
            'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
            'email' => new external_value(PARAM_RAW_TRIMMED, 'Email address: the match key'),
            'firstname' => new external_value(PARAM_TEXT, 'First name, used only for a new account'),
            'lastname' => new external_value(PARAM_TEXT, 'Last name, used only for a new account'),
            'organisation' => new external_value(PARAM_ALPHANUMEXT, 'Organisation key'),
            'country' => new external_value(PARAM_ALPHA, 'Two-letter country code', VALUE_DEFAULT, ''),
            'protection' => new external_value(PARAM_ALPHA, 'none, email, firstname or pseudonym',
                VALUE_DEFAULT, ''),
            'pseudonym' => new external_value(PARAM_TEXT, 'Pseudonym, with protection pseudonym only',
                VALUE_DEFAULT, ''),
            'courses' => new external_multiple_structure(
                new external_value(PARAM_RAW_TRIMMED, 'Course idnumber, ltct:<slug>'),
                'Courses to enrol in through the Organisation enrolment', VALUE_DEFAULT, []),
        ]);
    }

    public static function execute(array $rows, bool $showpeople = false): array {
        ['rows' => $rows, 'showpeople' => $showpeople] = self::validate_parameters(
            self::execute_parameters(), ['rows' => $rows, 'showpeople' => $showpeople]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        require_capability('moodle/user:create', $context);

        return intake_service::preview($rows, $showpeople);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'refusal' => new external_value(PARAM_RAW,
                'Why the whole file is refused before any row; empty when it is not'),
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
                    'key' => new external_value(PARAM_RAW, 'The masked email, or the email with showpeople'),
                    'outcome' => new external_value(PARAM_ALPHAEXT, 'new, unchanged, will_set_org, '
                        . 'will_enrol, flagged_other_org, flagged_suspended, flagged_protection, waits '
                        . 'or rejected'),
                    'reason' => new external_value(PARAM_RAW, 'Why, for an outcome that does not proceed'),
                    'changes' => new external_multiple_structure(
                        new external_value(PARAM_RAW, 'create, set_protection:<level>, set_org:<key> '
                            . 'or enrol:<course idnumber>'),
                        'What applying the row will do'),
                ])
            ),
        ]);
    }
}
