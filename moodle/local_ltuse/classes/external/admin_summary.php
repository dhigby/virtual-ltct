<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\admin\cohort_enrolment;
use local_ltuse\admin\enrolment_rules;
use local_ltuse\admin\masking;

/**
 * One organisation's cohorts and enrolments, for the site team's screen (spec 008, US3;
 * FR-007; research R14).
 *
 * `ltct_admin.py summary --org K [--out PATH]` prints this. For ltct:org:<K> and, when it
 * exists, ltct:org:<K>:managers: how many members each holds and how many of them are
 * suspended; each member, masked unless showpeople; and every cohort-sync instance of each
 * cohort, enabled or not, with its course, its active enrolment count, and whether it was made
 * through a pathway. A pathway-made instance whose course is no longer on any pathway the
 * cohort holds with enrol = 1 is flagged, because a course leaving a pathway unenrols nobody
 * (research R11): the site team decides whether to unenrol.
 *
 * Read-only. Never a name: with showpeople an email, otherwise a masked one. Nothing is
 * stored; the CLI writes a file only when asked, and never inside a git tree.
 *
 * Raw reads (listed in README.md): cohort_members joined to user for one cohort, enrol by
 * enrol and customint1, and a count of user_enrolments by enrolid. Core's
 * core_cohort_get_cohort_members returns user ids only (research R18).
 */
class admin_summary extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'orgkey' => new external_value(PARAM_ALPHANUMEXT, 'The organisation key'),
            'showpeople' => new external_value(PARAM_BOOL, 'Return emails unmasked', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(string $orgkey, bool $showpeople = false): array {
        global $DB;

        ['orgkey' => $orgkey, 'showpeople' => $showpeople] = self::validate_parameters(
            self::execute_parameters(), ['orgkey' => $orgkey, 'showpeople' => $showpeople]);
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/ltuse:administer', $context);
        require_capability('moodle/cohort:view', $context);
        if ($showpeople) {
            require_capability('moodle/site:viewuseridentity', $context);
        }

        $result = ['refusal' => '', 'pathways' => cohort_enrolment::pathways_installed(), 'cohorts' => [],
            'people' => [], 'courses' => []];
        $orgidnumber = enrolment_rules::ORG_PREFIX . $orgkey;
        $found = cohort_enrolment::resolve($orgidnumber);
        if ($found['refusal'] !== '') {
            return array_merge($result, ['refusal' => $found['refusal']]);
        }
        $cohorts = [$orgidnumber => (int)$found['cohort']->id];
        $managers = $DB->get_field('cohort', 'id', ['idnumber' => $orgidnumber . enrolment_rules::MANAGERS_SUFFIX]);
        if ($managers) {
            $cohorts[$orgidnumber . enrolment_rules::MANAGERS_SUFFIX] = (int)$managers;
        }

        foreach ($cohorts as $idnumber => $cohortid) {
            $members = $DB->get_records_sql(
                "SELECT u.id, u.email, u.suspended
                   FROM {cohort_members} cm
                   JOIN {user} u ON u.id = cm.userid
                  WHERE cm.cohortid = :cohortid AND u.deleted = 0
               ORDER BY u.id ASC", ['cohortid' => $cohortid]);
            $suspended = 0;
            foreach ($members as $member) {
                $suspended += (int)$member->suspended;
                $result['people'][] = [
                    'cohort' => $idnumber,
                    'key' => $showpeople ? (string)$member->email : masking::mask_email((string)$member->email),
                    'suspended' => (bool)$member->suspended,
                ];
            }
            $result['cohorts'][] = ['idnumber' => $idnumber, 'members' => count($members), 'suspended' => $suspended];

            $onpathways = self::pathway_courses($cohortid);
            $instances = $DB->get_records('enrol', ['enrol' => cohort_enrolment::PLUGIN, 'customint1' => $cohortid],
                'courseid ASC', 'id, courseid, status, customchar2');
            foreach ($instances as $instance) {
                $course = $DB->get_record('course', ['id' => $instance->courseid], 'id, idnumber, category');
                if (!$course) {
                    continue;
                }
                $viapathway = (string)$instance->customchar2 === cohort_enrolment::PATHWAY_MARKER;
                $result['courses'][] = [
                    'cohort' => $idnumber,
                    'course' => (string)$course->idnumber,
                    'category' => (string)$DB->get_field('course_categories', 'idnumber', ['id' => $course->category]),
                    'enabled' => (int)$instance->status === ENROL_INSTANCE_ENABLED,
                    'enrolled' => $DB->count_records('user_enrolments',
                        ['enrolid' => $instance->id, 'status' => ENROL_USER_ACTIVE]),
                    'viapathway' => $viapathway,
                    'notonpathway' => $viapathway && $onpathways !== null
                        && !in_array((int)$course->id, $onpathways, true),
                ];
            }
        }
        return $result;
    }

    /**
     * Every course on any pathway the cohort holds with enrol = 1 (spec 006), or null when
     * pathways are not installed, so nothing is flagged that cannot be checked.
     *
     * @param int $cohortid
     * @return int[]|null
     */
    protected static function pathway_courses(int $cohortid): ?array {
        if (!cohort_enrolment::pathways_installed()) {
            return null;
        }
        $catalogue = cohort_enrolment::CATALOGUE;
        $assignments = cohort_enrolment::ASSIGNMENTS;
        $courses = [];
        foreach ($catalogue::all() as $key) {
            if (in_array($cohortid, array_map('intval', $assignments::cohorts_for($key, true)), true)) {
                $courses = array_merge($courses, array_map('intval', $catalogue::courses($key)));
            }
        }
        return array_values(array_unique($courses));
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'refusal' => new external_value(PARAM_RAW, 'Why nothing is shown; empty when it is not'),
            'pathways' => new external_value(PARAM_BOOL, 'Whether learning pathways (spec 006) are installed'),
            'cohorts' => new external_multiple_structure(
                new external_single_structure([
                    'idnumber' => new external_value(PARAM_RAW, 'The cohort idnumber'),
                    'members' => new external_value(PARAM_INT, 'How many people it holds'),
                    'suspended' => new external_value(PARAM_INT, 'How many of them are suspended'),
                ])
            ),
            'people' => new external_multiple_structure(
                new external_single_structure([
                    'cohort' => new external_value(PARAM_RAW, 'The cohort idnumber'),
                    'key' => new external_value(PARAM_RAW, 'The masked email, or the email with showpeople'),
                    'suspended' => new external_value(PARAM_BOOL, 'The account is suspended'),
                ])
            ),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'cohort' => new external_value(PARAM_RAW, 'The cohort idnumber'),
                    'course' => new external_value(PARAM_RAW, 'The course idnumber'),
                    'category' => new external_value(PARAM_RAW, 'The course\'s category idnumber'),
                    'enabled' => new external_value(PARAM_BOOL, 'The cohort-sync instance is enabled'),
                    'enrolled' => new external_value(PARAM_INT, 'Active enrolments through it'),
                    'viapathway' => new external_value(PARAM_BOOL, 'Made through a pathway'),
                    'notonpathway' => new external_value(PARAM_BOOL,
                        'Made through a pathway, and the course is on none the cohort holds with enrol = 1'),
                ])
            ),
        ]);
    }
}
