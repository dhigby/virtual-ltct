<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * What the administration tool relies on, read before it changes anything (spec 008, R12, R13).
 *
 * `ltct_admin.py check` calls this first and refuses to run when a setting is wrong, because
 * a wrong enrol_cohort/unenrolaction turns "remove" into data loss and realtime off leaves a
 * new learner outside their organisation's cohort. It returns:
 *
 *   - the settings research R13 lists as read by check, each with its value, and whether it
 *     is set at all;
 *   - the capabilities of the declared ltctadmin role (research R12) the caller lacks at
 *     system context, by name. A capability this server does not define yet (spec 016's
 *     local/ltuse:manageprotection before 016 is installed) is left out, not reported missing;
 *   - whether spec 006's pathways and spec 016's protection classes are installed, and, when
 *     016 is, service::level_available() for each protection level.
 *
 * Read-only. Returns no person and no database id.
 */
class admin_check extends external_api {

    /** Settings the tool depends on (research R13): [plugin, name], plugin null for core. */
    const SETTINGS = [
        ['enrol_cohort', 'unenrolaction'],
        ['tool_dynamic_cohorts', 'realtime'],
        [null, 'allowaccountssameemail'],
        ['local_ltuse', 'coursementorsync'],
    ];

    /** The ltctadmin role's capabilities (research R12; moodle/site/roles.yaml). */
    const CAPABILITIES = [
        'local/ltuse:administer',
        'webservice/rest:use',
        'moodle/user:create',
        'moodle/user:update',
        'moodle/user:viewdetails',
        'moodle/user:viewhiddendetails',
        'moodle/site:viewuseridentity',
        'moodle/cohort:view',
        'moodle/cohort:assign',
        'moodle/course:enrolconfig',
        'enrol/cohort:config',
        'enrol/self:config',
        'enrol/self:manage',
        'moodle/role:assign',
        'moodle/course:managegroups',
        'local/ltuse:manageprotection',
    ];

    /** Spec 016's protection level keys, lowest first. */
    const LEVELS = ['none', 'email', 'firstname', 'pseudonym'];

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        self::validate_parameters(self::execute_parameters(), []);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);

        $settings = [];
        foreach (self::SETTINGS as [$plugin, $name]) {
            $value = get_config($plugin ?? 'core', $name);
            $settings[] = [
                'name' => $plugin ? "{$plugin}/{$name}" : $name,
                'value' => $value === false ? '' : (string)$value,
                'isset' => $value !== false,
            ];
        }

        $missing = [];
        foreach (self::CAPABILITIES as $capability) {
            if (get_capability_info($capability, false) === null) {
                continue;   // Not defined on this server yet; nothing to grant.
            }
            if (!has_capability($capability, $context)) {
                $missing[] = $capability;
            }
        }

        $protection = class_exists('\local_ltuse\protection\service');
        $levels = [];
        if ($protection) {
            foreach (self::LEVELS as $level) {
                $levels[] = [
                    'level' => $level,
                    'available' => (bool)\local_ltuse\protection\service::level_available($level),
                ];
            }
        }

        return [
            'settings' => $settings,
            'missingcapabilities' => $missing,
            'pathways' => class_exists('\local_ltuse\pathway\catalogue')
                && class_exists('\local_ltuse\pathway\assignments'),
            'protection' => $protection,
            'levels' => $levels,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'settings' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_RAW, 'Setting, plugin/name or name for core'),
                    'value' => new external_value(PARAM_RAW, 'Its value; empty when not set'),
                    'isset' => new external_value(PARAM_BOOL, 'Whether it is set at all'),
                ])
            ),
            'missingcapabilities' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'A capability the caller lacks at system context')
            ),
            'pathways' => new external_value(PARAM_BOOL, 'Spec 006 learning pathways are installed'),
            'protection' => new external_value(PARAM_BOOL, 'Spec 016 identity protection is installed'),
            'levels' => new external_multiple_structure(
                new external_single_structure([
                    'level' => new external_value(PARAM_ALPHA, 'Protection level key'),
                    'available' => new external_value(PARAM_BOOL, 'Whether it may be set now'),
                ]),
                'Per protection level, when spec 016 is installed; empty otherwise'
            ),
        ]);
    }
}
