<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\cohort_enrolment;
use local_ltuse\admin\enrolment_rules;
use local_ltuse\admin\intake_rules;
use local_ltuse\admin\intake_service;

/**
 * Enrol ONE cohort into ONE course, or take it out, as previewed (spec 008, US2; research R7,
 * R15).
 *
 * ensure adds the cohort-sync instance or re-enables the one there is; remove disables it and
 * never deletes it, so every enrolment, grade and completion is kept. The pair is previewed
 * again: the same outcome is applied; already, after a would_* preview, is already_done;
 * anything else is refused as changed since the preview.
 *
 * With pathwaykey (the CLI's `enrol pathway`, after one apply_pathway_assignment), the course
 * must still be on that pathway, and a new instance is marked as made through a pathway.
 */
class admin_apply_cohort_enrolment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cohortidnumber' => new external_value(PARAM_RAW_TRIMMED, 'The cohort, by idnumber'),
            'courseidnumber' => new external_value(PARAM_RAW_TRIMMED, 'The course, by idnumber'),
            'action' => new external_value(PARAM_ALPHA, 'ensure or remove'),
            'expectedoutcome' => new external_value(PARAM_ALPHAEXT, 'The pair\'s outcome in the preview'),
            'pathwaykey' => new external_value(PARAM_RAW_TRIMMED, 'The pathway this enrolment is for',
                VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $cohortidnumber, string $courseidnumber, string $action,
            string $expectedoutcome, string $pathwaykey = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cohortidnumber' => $cohortidnumber,
            'courseidnumber' => $courseidnumber, 'action' => $action, 'expectedoutcome' => $expectedoutcome,
            'pathwaykey' => $pathwaykey]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);

        if (!in_array($params['action'], ['ensure', 'remove'], true)
                || ($params['pathwaykey'] !== '' && $params['action'] !== 'ensure')) {
            return self::answer('', 'refused', get_string('admin:refusal:target', 'local_ltuse'));
        }
        $found = cohort_enrolment::resolve($params['cohortidnumber'], $params['courseidnumber']);
        if ($found['refusal'] !== '') {
            return self::answer('', 'refused', $found['refusal']);
        }
        $cohortid = (int)$found['cohort']->id;
        $courseid = (int)$found['course']->id;
        require_capability('enrol/cohort:config', context_course::instance($courseid));

        if ($params['pathwaykey'] !== '' && !cohort_enrolment::on_pathway($params['pathwaykey'], $courseid)) {
            return self::answer('', 'refused', intake_service::reason('not_in_pathway'));
        }
        $preview = cohort_enrolment::preview($cohortid, $courseid, $params['action']);
        $step = enrolment_rules::progress($params['expectedoutcome'], $preview['outcome']);
        if ($step === intake_rules::REFUSED) {
            $reason = $preview['outcome'] === 'refused' ? $preview['reason'] : 'changed';
            return self::answer($preview['outcome'], 'refused', intake_service::reason($reason));
        }
        if ($preview['outcome'] === 'already') {
            return self::answer('already', 'already_done', '');
        }
        $status = $params['action'] === 'remove'
            ? cohort_enrolment::remove($cohortid, $courseid)
            : cohort_enrolment::ensure($cohortid, $courseid, $params['pathwaykey'] !== '');
        if (in_array($status, ['added', 'enabled', 'disabled'], true)) {
            return self::answer($preview['outcome'], 'done', '');
        }
        if ($status === 'already') {
            return self::answer('already', 'already_done', '');
        }
        return self::answer($preview['outcome'], 'refused', intake_service::reason('changed'));
    }

    /**
     * @param string $outcome
     * @param string $status
     * @param string $reason
     * @return array
     */
    protected static function answer(string $outcome, string $status, string $reason): array {
        return ['outcome' => $outcome, 'status' => $status, 'reason' => $reason];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'outcome' => new external_value(PARAM_ALPHAEXT, 'The pair\'s outcome when it was applied'),
            'status' => new external_value(PARAM_ALPHAEXT, 'done, already_done or refused'),
            'reason' => new external_value(PARAM_RAW, 'Why, when refused'),
        ]);
    }
}
