<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\protection\entitlement;
use local_ltuse\protection\service;
use moodle_exception;

/**
 * Set an organisation's minimum protection level (spec 016, research R12). The site team only.
 *
 * An organisation's minimum is Moodle data, never a repo declaration: declaring it would
 * publish which partners are at risk. Raising it over members with activity is refused with
 * their count until acknowledged. Lowering lowers no one (R13).
 */
class set_org_protection extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'orgkey' => new external_value(PARAM_ALPHANUMEXT, 'An organisation key from organisations.yaml'),
            'minlevel' => new external_value(PARAM_ALPHA, 'none, email or firstname'),
            'managers_see_identity' => new external_value(PARAM_BOOL,
                'Whether the organisation\'s own managers see real identities', VALUE_DEFAULT, true),
            'acknowledgehistory' => new external_value(PARAM_BOOL,
                'The site team knows members with activity are linked to their new display', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(string $orgkey, string $minlevel, bool $managers_see_identity = true,
            bool $acknowledgehistory = false): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('orgkey', 'minlevel', 'managers_see_identity', 'acknowledgehistory'));
        self::validate_context(context_system::instance());
        if (!entitlement::can_manage_organisations((int)$USER->id)) {
            throw new moodle_exception('protection:err:nopermission', 'local_ltuse');
        }
        return service::set_org_protection($params['orgkey'], $params['minlevel'],
            $params['managers_see_identity'], $params['acknowledgehistory'], (int)$USER->id);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'members' => new external_value(PARAM_INT, 'Members queued for the new minimum'),
            'withactivity' => new external_value(PARAM_INT, 'Of whom, how many already had activity'),
        ]);
    }
}
