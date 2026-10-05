<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

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
 * Give a cohort a pathway with enrol = 1 (spec 008, US2; research R11).
 *
 * `ltct_admin.py enrol pathway` calls this once, then apply_cohort_enrolment for each course.
 * Refused unless enrolment_rules allows the cohort in every course on the pathway now, because
 * spec 006 never lowers enrol: a refused cohort left at enrol = 1 would stay there, and the
 * observer would keep trying to enrol it in every course the pathway gains.
 *
 * Until spec 006 is installed this refuses with "pathways are not installed".
 */
class admin_apply_pathway_assignment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cohortidnumber' => new external_value(PARAM_RAW_TRIMMED, 'The cohort, by idnumber'),
            'pathwaykey' => new external_value(PARAM_RAW_TRIMMED, 'The pathway key'),
            'expectedoutcome' => new external_value(PARAM_ALPHAEXT, 'would_add or already, from the preview'),
        ]);
    }

    public static function execute(string $cohortidnumber, string $pathwaykey, string $expectedoutcome): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cohortidnumber' => $cohortidnumber,
            'pathwaykey' => $pathwaykey, 'expectedoutcome' => $expectedoutcome]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        // Spec 006's may_assign(): moodle/cohort:assign at system context assigns any cohort.
        require_capability('moodle/cohort:assign', $context);

        $found = cohort_enrolment::resolve($params['cohortidnumber']);
        if ($found['refusal'] !== '') {
            return self::answer('', 'refused', $found['refusal']);
        }
        $cohortid = (int)$found['cohort']->id;
        $preview = cohort_enrolment::preview_pathway($cohortid, $params['pathwaykey']);
        if ($preview['refusal'] !== '') {
            return self::answer('', 'refused', $preview['refusal']);
        }
        foreach ($preview['courses'] as $course) {
            if ($course['outcome'] === 'refused') {
                return self::answer($preview['assignment'], 'refused',
                    intake_service::reason('pathway_course_refused', $course['course']));
            }
        }
        $step = enrolment_rules::progress($params['expectedoutcome'], $preview['assignment']);
        if ($step === intake_rules::REFUSED) {
            return self::answer($preview['assignment'], 'refused', intake_service::reason('changed'));
        }
        $status = cohort_enrolment::assign_pathway($cohortid, $params['pathwaykey']);
        if ($status === 'done') {
            return self::answer($preview['assignment'], 'done', '');
        }
        if ($status === 'already') {
            return self::answer('already', 'already_done', '');
        }
        return self::answer($preview['assignment'], 'refused', intake_service::reason($status));
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
            'outcome' => new external_value(PARAM_ALPHAEXT, 'The assignment\'s outcome when it was applied'),
            'status' => new external_value(PARAM_ALPHAEXT, 'done, already_done or refused'),
            'reason' => new external_value(PARAM_RAW, 'Why, when refused'),
        ]);
    }
}
