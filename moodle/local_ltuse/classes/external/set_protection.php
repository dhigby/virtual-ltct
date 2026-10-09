<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\protection\entitlement;
use local_ltuse\protection\service;
use moodle_exception;

/**
 * Grant, change or remove one person's protection (spec 016, contracts/protection-service.md).
 *
 * The same logic as the granting page: service::set_protection(), after
 * entitlement::can_manage_protection(). Corrections to the real name or the held values are
 * accepted only from someone who may also see them (can_view_identity). The real identity is
 * never returned. Used by the site team's scripts and by spec 008's bulk tooling; it is not in
 * the publishing service.
 *
 * `requested` is required: the caller states whether the person asked, and a raise without it
 * is refused (Doug, 2026-10-05 (scope review), change 13). A raise also needs `emailchecked`
 * (change 2). There is no username parameter: the service picks a neutral one itself where
 * the username holds the real name (change 15).
 */
class set_protection extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'The user to protect'),
            'level' => new external_value(PARAM_ALPHA, 'none, email, firstname or pseudonym'),
            'requested' => new external_value(PARAM_BOOL, 'The person asked for this change; required for a raise'),
            'emailchecked' => new external_value(PARAM_BOOL,
                'The account email identifies neither the person nor their organisation; required for a raise',
                VALUE_DEFAULT, false),
            'pseudonym' => new external_value(PARAM_TEXT, 'Required at pseudonym', VALUE_DEFAULT, ''),
            'realfirstname' => new external_value(PARAM_TEXT, 'Correction to the real first name', VALUE_DEFAULT, ''),
            'reallastname' => new external_value(PARAM_TEXT, 'Correction to the real surname', VALUE_DEFAULT, ''),
            'realfields' => new external_multiple_structure(new external_single_structure([
                'field' => new external_value(PARAM_ALPHANUMEXT, 'A held field'),
                'value' => new external_value(PARAM_TEXT, 'Its corrected value'),
            ]), 'Corrections to held values', VALUE_DEFAULT, []),
            'acknowledgehistory' => new external_value(PARAM_BOOL,
                'The person granting knows earlier activity is linked to the new display', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(int $userid, string $level, bool $requested, bool $emailchecked = false,
            string $pseudonym = '', string $realfirstname = '', string $reallastname = '', array $realfields = [],
            bool $acknowledgehistory = false): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('userid', 'level', 'requested',
            'emailchecked', 'pseudonym', 'realfirstname', 'reallastname', 'realfields', 'acknowledgehistory'));
        $context = context_user::instance($params['userid'], IGNORE_MISSING) ?: context_system::instance();
        self::validate_context($context);
        if (!entitlement::can_manage_protection((int)$USER->id, $params['userid'])) {
            throw new moodle_exception('protection:err:nopermission', 'local_ltuse');
        }
        $corrections = $params['realfirstname'] !== '' || $params['reallastname'] !== '' || $params['realfields'];
        if ($corrections && !entitlement::can_view_identity((int)$USER->id, $params['userid'])) {
            throw new moodle_exception('protection:err:nocorrect', 'local_ltuse');
        }
        $fields = [];
        foreach ($params['realfields'] as $entry) {
            $fields[$entry['field']] = $entry['value'];
        }
        $result = service::set_protection($params['userid'], $params['level'], [
            'requested' => $params['requested'],
            'emailchecked' => $params['emailchecked'],
            'pseudonym' => $params['pseudonym'],
            'realfirstname' => $params['realfirstname'],
            'reallastname' => $params['reallastname'],
            'realfields' => $fields,
            'acknowledgehistory' => $params['acknowledgehistory'],
        ], (int)$USER->id);
        return ['effectivelevel' => $result['effectivelevel'], 'warnings' => $result['warnings']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'effectivelevel' => new external_value(PARAM_ALPHA, 'The level now applied'),
            'warnings' => new external_multiple_structure(new external_value(PARAM_TEXT, 'A warning')),
        ]);
    }
}
