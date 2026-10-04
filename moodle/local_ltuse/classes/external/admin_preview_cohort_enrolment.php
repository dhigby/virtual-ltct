<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\cohort_enrolment;
use local_ltuse\admin\enrolment_rules;
use local_ltuse\admin\intake_service;

/**
 * Preview enrolling a cohort into a course or a pathway, or taking it out of a course (spec
 * 008, US2; research R7, R11), or enrolling an organisation's cohort in every shared course
 * another organisation's cohort is in (US3, research R8).
 *
 * `ltct_admin.py enrol course`, `enrol pathway`, `enrol mirror` and `unenrol` call this first. Per course it
 * returns would_add, would_enable, would_disable, already or refused with a reason, and the
 * cohort's member count. For a pathway it also says whether the cohort already holds the
 * pathway with enrol = 1. A cohort or course that does not exist, or a pathway that cannot be
 * used, is a refusal of the whole command, with no course result.
 *
 * Read-only. Returns idnumbers and a count; never a person or a database id.
 */
class admin_preview_cohort_enrolment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cohortidnumber' => new external_value(PARAM_RAW_TRIMMED, 'The cohort, by idnumber'),
            'courseidnumber' => new external_value(PARAM_RAW_TRIMMED, 'One course, by idnumber',
                VALUE_DEFAULT, ''),
            'pathwaykey' => new external_value(PARAM_RAW_TRIMMED, 'Or every course of this pathway',
                VALUE_DEFAULT, ''),
            'action' => new external_value(PARAM_ALPHA, 'ensure or remove', VALUE_DEFAULT, 'ensure'),
            'mirrorfrom' => new external_value(PARAM_RAW_TRIMMED,
                'Or every shared course the cohort of this organisation key is enrolled in', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $cohortidnumber, string $courseidnumber = '', string $pathwaykey = '',
            string $action = 'ensure', string $mirrorfrom = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cohortidnumber' => $cohortidnumber,
            'courseidnumber' => $courseidnumber, 'pathwaykey' => $pathwaykey, 'action' => $action,
            'mirrorfrom' => $mirrorfrom]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);

        $empty = ['refusal' => '', 'members' => 0, 'assignment' => '', 'courses' => []];
        $refusal = self::target_refusal($params['courseidnumber'], $params['pathwaykey'], $params['action'],
            $params['mirrorfrom'], $params['cohortidnumber']);
        if ($refusal !== '') {
            return array_merge($empty, ['refusal' => $refusal]);
        }
        $found = cohort_enrolment::resolve($params['cohortidnumber'], $params['courseidnumber']);
        if ($found['refusal'] !== '') {
            return array_merge($empty, ['refusal' => $found['refusal']]);
        }
        $cohortid = (int)$found['cohort']->id;

        if ($params['mirrorfrom'] !== '') {
            $from = cohort_enrolment::resolve(enrolment_rules::ORG_PREFIX . $params['mirrorfrom']);
            if ($from['refusal'] !== '') {
                return array_merge($empty, ['refusal' => $from['refusal']]);
            }
            $courses = cohort_enrolment::preview_mirror($cohortid, (int)$from['cohort']->id);
            $assignment = '';
        } else if ($params['pathwaykey'] !== '') {
            $preview = cohort_enrolment::preview_pathway($cohortid, $params['pathwaykey']);
            if ($preview['refusal'] !== '') {
                return array_merge($empty, ['refusal' => $preview['refusal']]);
            }
            $courses = $preview['courses'];
            $assignment = $preview['assignment'];
        } else {
            $courses = [cohort_enrolment::preview($cohortid, (int)$found['course']->id, $params['action'])];
            $assignment = '';
        }

        $results = [];
        foreach ($courses as $course) {
            require_capability('enrol/cohort:config', context_course::instance($course['courseid']));
            $results[] = [
                'course' => $course['course'],
                'outcome' => $course['outcome'],
                'reason' => intake_service::reason($course['reason']),
            ];
        }
        return [
            'refusal' => '',
            'members' => cohort_enrolment::member_count($cohortid),
            'assignment' => $assignment,
            'courses' => $results,
        ];
    }

    /**
     * Exactly one of a course, a pathway or a mirror; a pathway or a mirror only to enrol, never
     * to remove (taking a pathway away unenrols nobody, research R11). A mirror goes from one
     * organisation's cohort to another organisation's, never to itself.
     *
     * @param string $courseidnumber
     * @param string $pathwaykey
     * @param string $action
     * @param string $mirrorfrom an organisation key, or ''
     * @param string $cohortidnumber
     * @return string a refusal, or ''
     */
    public static function target_refusal(string $courseidnumber, string $pathwaykey, string $action,
            string $mirrorfrom = '', string $cohortidnumber = ''): string {
        $targets = (int)($courseidnumber !== '') + (int)($pathwaykey !== '') + (int)($mirrorfrom !== '');
        if (!in_array($action, ['ensure', 'remove'], true) || $targets !== 1
                || ($courseidnumber === '' && $action !== 'ensure')) {
            return get_string('admin:refusal:target', 'local_ltuse');
        }
        if ($mirrorfrom !== '') {
            [$kind, $key] = enrolment_rules::cohort_kind($cohortidnumber);
            if ($kind !== 'org' || $key === $mirrorfrom || !preg_match(enrolment_rules::KEY_PATTERN, $mirrorfrom)) {
                return get_string('admin:refusal:mirror', 'local_ltuse');
            }
        }
        return '';
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'refusal' => new external_value(PARAM_RAW, 'Why the whole command is refused; empty when it is not'),
            'members' => new external_value(PARAM_INT, 'How many people the cohort holds'),
            'assignment' => new external_value(PARAM_ALPHAEXT,
                'For a pathway: would_add or already (the cohort holds it with enrol = 1); empty otherwise'),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'course' => new external_value(PARAM_RAW, 'The course idnumber'),
                    'outcome' => new external_value(PARAM_ALPHAEXT,
                        'would_add, would_enable, would_disable, already or refused'),
                    'reason' => new external_value(PARAM_RAW, 'Why, when refused'),
                ])
            ),
        ]);
    }
}
