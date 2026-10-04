<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\membership_service;

/**
 * Preview adding people to, and removing them from, managers cohorts and ltct:mentors (spec
 * 008, US3; research R9).
 *
 * `ltct_admin.py managers FILE` calls this first. Every cohort the file names must exist, or
 * the whole file is refused naming it. Per row: would_change, unchanged, or rejected with a
 * reason (an organisation's own cohort, a cohort a plugin owns, any other cohort, no account).
 * A row adding a member of organisation K to K's managers cohort proceeds with a note.
 *
 * Read-only. People come back masked unless showpeople is set.
 */
class admin_preview_cohort_members extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'rows' => new external_multiple_structure(self::row_structure(), 'The file\'s rows, in order'),
            'showpeople' => new external_value(PARAM_BOOL, 'Return emails unmasked', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * One managers-file row (data-model section 1). Shared with apply_cohort_members.
     *
     * @return external_single_structure
     */
    public static function row_structure(): external_single_structure {
        return new external_single_structure([
            'row' => new external_value(PARAM_INT, 'Row number in the operator\'s file'),
            'email' => new external_value(PARAM_RAW_TRIMMED, 'Email address: the match key'),
            'cohortidnumber' => new external_value(PARAM_RAW_TRIMMED,
                'ltct:org:<key>:managers or ltct:mentors'),
            'action' => new external_value(PARAM_ALPHA, 'add or remove'),
        ]);
    }

    public static function execute(array $rows, bool $showpeople = false): array {
        ['rows' => $rows, 'showpeople' => $showpeople] = self::validate_parameters(
            self::execute_parameters(), ['rows' => $rows, 'showpeople' => $showpeople]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);

        $found = membership_service::resolve($rows);
        if ($found['refusal'] !== '') {
            return ['refusal' => $found['refusal'], 'rows' => []];
        }
        foreach ($found['cohorts'] as $cohort) {
            require_capability('moodle/cohort:assign', context::instance_by_id($cohort->contextid));
        }
        return membership_service::preview($rows, $found['cohorts'], $showpeople);
    }

    public static function execute_returns(): external_single_structure {
        return admin_preview_suspension::rows_returns('would_change, unchanged or rejected');
    }
}
