<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_ltuse\recognition\badges;
use local_ltuse\recognition\certificate;
use local_ltuse\siteconfig\badgetemplate;
use local_ltuse\siteconfig\certtemplate;
use local_ltuse\util;
use moodle_exception;

/**
 * A published course's completion badge and, on delivery, its certificate (spec 013,
 * contracts/publish.md).
 *
 * The publisher calls this straight after local_ltuse_set_course_completion, so the badge and
 * the certificate see the final criteria. In order:
 *
 *   1. Badge: created if the map has none for the course, otherwise reworded from the stored
 *      template where it differs (badges::sync()).
 *   2. Wording: the rendered text is re-checked against the deny patterns apply stored. A
 *      match refuses the call and writes nothing for the badge (R15).
 *   3. Activation: on a delivery publish an inactive badge is activated. An active badge is
 *      never deactivated (R4, R5).
 *   4. Certificate: on a delivery publish only, the customcert activity is created if absent,
 *      and its pages are copied from the site template where they differ (R7, R8).
 *
 * PRECONDITION. Both templates must have been applied by site_config.py apply. If either is
 * missing, the call refuses with `recognition-not-applied` and writes nothing.
 *
 * `delivery` is course_stage.py reporting stage 8; the publisher passes it through and this
 * function never works out a stage (Principle I).
 *
 * Returns no user data: no award count, no recipient, no issue code (FR-008).
 */
class set_course_recognition extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber, ltct:<slug>'),
            'delivery' => new external_value(PARAM_BOOL, 'Whether the course is at stage 8'),
            'certificateidnumber' => new external_value(PARAM_RAW,
                'The certificate activity idnumber, ltct:<slug>:certificate; required on delivery',
                VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $courseidnumber, bool $delivery,
            string $certificateidnumber = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
            'delivery' => $delivery,
            'certificateidnumber' => $certificateidnumber,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $context = context_course::instance($course->id);
        // Sets $PAGE->context, which award_criteria::save() raises its events in, and
        // $COURSE, which mod_customcert's image element copies its file into.
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);
        require_capability('moodle/badges:createbadge', $context);
        require_capability('moodle/badges:configurecriteria', $context);
        require_capability('moodle/badges:configuredetails', $context);
        require_capability('moodle/badges:configuremessages', $context);

        if (strpos($course->idnumber, util::IDNUMBER_PREFIX) !== 0) {
            throw new moodle_exception('error:notltctcourse', 'local_ltuse', '', $course->idnumber);
        }
        $certidnumber = $params['certificateidnumber'];
        if ($params['delivery'] && $certidnumber !== $course->idnumber . certtemplate::IDNUMBER_SUFFIX) {
            throw new invalid_parameter_exception('certificateidnumber must be '
                . $course->idnumber . certtemplate::IDNUMBER_SUFFIX . ' on a delivery publish');
        }

        // Nothing is written until everything both steps need is known to be there: a web
        // service call is no transaction, so a refusal after the badge would leave it written.
        $template = badgetemplate::stored();
        if ($template === null) {
            throw new moodle_exception('error:recognitionnotapplied', 'local_ltuse');
        }
        if ($params['delivery']) {
            require_capability('mod/customcert:addinstance', $context);
            certificate::require_ready();
        }

        $result = badges::sync($course, $params['delivery'], $template);
        $result['certificate'] = 'none';
        if ($params['delivery']) {
            $result['certificate'] = certificate::sync($course, $certidnumber);
        }
        return $result;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'badge' => new external_value(PARAM_ALPHA, 'created, updated or unchanged'),
            'status' => new external_value(PARAM_ALPHA, 'inactive, activated or active'),
            'certificate' => new external_value(PARAM_ALPHA, 'created, updated, unchanged or none'),
            'warnings' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHAEXT,
                    'startdate-future, badge-extra, active-not-delivery or course-hidden'),
                'message' => new external_value(PARAM_TEXT, 'What a person should look at'),
            ])),
        ]);
    }
}
