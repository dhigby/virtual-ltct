<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Move learners between organisations, after a counted dry run (spec 008, US3; research R8,
 * R15; spec 002 FR-017).
 *
 * preview() gathers, per learner, the courses they are actively enrolled in and how (through
 * the old organisation's cohort sync, or some other way), and the courses the new
 * organisation's cohort is enrolled in, and asks move_rules for the outcome. It changes nothing.
 *
 * apply_row() moves one learner, under the same per-email lock intake uses: it classifies
 * again, and writes the new ltct_org only when nothing would be lost and the learner's
 * protection already meets the new organisation's minimum. The write is the organisation field
 * and nothing else (FR-019), followed by user_updated, so tool_dynamic_cohorts moves the person
 * between cohorts in the same request and core suspends the old cohort-sync enrolments with
 * their history kept (enrol_cohort/unenrolaction = 3).
 *
 * Protection (spec 016, research R8): the new organisation's minimum is read with
 * service::org_minimum() and compared with service::effective_level(). A learner below it is
 * flagged_protection and is not moved; raising protection on an account that may have activity
 * can need an acknowledgement (016 R13), which is 016's page, never a bulk side effect. So this
 * class NEVER calls set_protection(). After the move it confirms is_settled(), and if not
 * settled asks the service to apply what is already set, once. Without spec 016 every minimum
 * counts as none.
 *
 * The external functions check local/ltuse:administer and moodle/user:update first. Nothing
 * here checks a capability.
 */
class move_service {

    /** Spec 016's protection service, called only when installed (research R5). */
    const PROTECTION_SERVICE = '\local_ltuse\protection\service';

    /**
     * Preview a whole move file. Changes nothing.
     *
     * @param array $rows each [row, email, organisation]
     * @param bool $showpeople
     * @return array ['refusal' => string, 'rows' => [[row, key, outcome, reason, changes[],
     *               courses[[course, outcome]]]]]; a non-empty refusal comes with no rows
     */
    public static function preview(array $rows, bool $showpeople): array {
        $context = self::resolve($rows);
        if ($context['refusal'] !== '') {
            return ['refusal' => $context['refusal'], 'rows' => []];
        }
        $results = [];
        foreach ($rows as $row) {
            $key = $showpeople ? trim((string)$row['email']) : masking::mask_email((string)$row['email']);
            if (!intake_service::valid_email($row)) {
                $results[] = ['row' => (int)$row['row'], 'key' => $key, 'outcome' => 'rejected',
                    'reason' => intake_service::reason('bad_email'), 'changes' => [], 'courses' => []];
                continue;
            }
            $facts = self::facts($row, $context);
            $decision = move_rules::classify($facts);
            $results[] = [
                'row' => (int)$row['row'],
                'key' => $key,
                'outcome' => $decision['outcome'],
                'reason' => self::reason($decision, $facts),
                'changes' => $decision['changes'],
                'courses' => $decision['courses'],
            ];
        }
        return ['refusal' => '', 'rows' => $results];
    }

    /**
     * Move one learner, if they are still as the preview said, or already moved.
     *
     * @param array $row [row, email, organisation]
     * @param string $expectedoutcome
     * @param array $expectedcourses [[course, outcome]] from the preview
     * @return array [row, outcome, status: done|already_done|refused, reason]
     */
    public static function apply_row(array $row, string $expectedoutcome, array $expectedcourses): array {
        if (!intake_service::valid_email($row)) {
            return self::answer($row, 'rejected', 'refused', intake_service::reason('bad_email'));
        }
        $email = intake_service::normalise_email((string)$row['email']);
        // The intake lock's factory, keyed on the same hash, so an intake and a move of one
        // person never interleave.
        $lock = \core\lock\lock_config::get_lock_factory(intake_service::LOCK_TYPE)
            ->get_lock('intake:' . sha1($email), intake_service::LOCK_WAIT);
        if (!$lock) {
            return self::answer($row, $expectedoutcome, 'refused', intake_service::reason('busy'));
        }
        try {
            $context = self::resolve([$row]);
            if ($context['refusal'] !== '') {
                return self::answer($row, $expectedoutcome, 'refused', $context['refusal']);
            }
            $facts = self::facts($row, $context);
            $decision = move_rules::classify($facts);
            $step = move_rules::progress($expectedoutcome, $decision['outcome'], $expectedcourses,
                $decision['courses']);
            if ($step === intake_rules::REFUSED) {
                $reason = in_array($decision['outcome'], ['would_move', 'moved'], true)
                    ? intake_service::reason('changed') : self::reason($decision, $facts);
                return self::answer($row, $decision['outcome'], 'refused', $reason);
            }
            if ($step === intake_rules::FINISH) {
                return self::answer($row, $decision['outcome'], 'already_done', '');
            }
            $neworg = trim((string)$row['organisation']);
            if (!intake_service::set_organisation((int)$facts['userid'], $neworg)) {
                return self::answer($row, $decision['outcome'], 'refused', intake_service::reason('org_not_saved'));
            }
            return self::answer($row, $decision['outcome'], 'done',
                self::settled((int)$facts['userid']) ? '' : intake_service::reason('moved_unsettled'));
        } finally {
            $lock->release();
        }
    }

    // --- gathering facts ----------------------------------------------------------------------

    /**
     * Resolve each new organisation's cohort (any missing is a file-level refusal naming it),
     * the courses it is enrolled in, and, with spec 016, its minimum.
     *
     * @param array $rows
     * @return array ['refusal' => string, 'orgs' => [key => ['cohortid' => int, 'courses' =>
     *               int[] enabled cohort-sync course ids, 'minimum' => level]], 'protection' => bool]
     */
    protected static function resolve(array $rows): array {
        $orgs = [];
        $missing = [];
        $protection = class_exists(self::PROTECTION_SERVICE);
        foreach ($rows as $row) {
            $key = trim((string)($row['organisation'] ?? ''));
            if (array_key_exists($key, $orgs) || in_array(enrolment_rules::ORG_PREFIX . $key, $missing, true)) {
                continue;
            }
            $org = self::org($key);
            if ($org === null) {
                $missing[] = enrolment_rules::ORG_PREFIX . $key;
                continue;
            }
            if ($protection) {
                $service = self::PROTECTION_SERVICE;
                $org['minimum'] = (string)$service::org_minimum($key);
            }
            $orgs[$key] = $org;
        }
        return [
            'refusal' => $missing ? get_string('admin:refusal:missing', 'local_ltuse', implode(', ', $missing)) : '',
            'orgs' => $orgs,
            'protection' => $protection,
        ];
    }

    /**
     * An organisation's cohort and the courses it has an enabled cohort-sync instance in.
     *
     * @param string $key
     * @return array|null ['cohortid' => int, 'courses' => int[], 'minimum' => 'none']
     */
    protected static function org(string $key): ?array {
        global $DB;
        if (!preg_match(enrolment_rules::KEY_PATTERN, $key)) {
            return null;
        }
        $cohortid = $DB->get_field('cohort', 'id', ['idnumber' => enrolment_rules::ORG_PREFIX . $key]);
        if (!$cohortid) {
            return null;
        }
        return ['cohortid' => (int)$cohortid, 'courses' => cohort_enrolment::enabled_courses((int)$cohortid),
            'minimum' => 'none'];
    }

    /**
     * The facts move_rules::classify() needs about one row (see its class comment), plus the
     * matched account's id as 'userid'.
     *
     * @param array $row
     * @param array $context resolve()'s result
     * @return array
     */
    protected static function facts(array $row, array $context): array {
        $neworg = trim((string)$row['organisation']);
        $new = $context['orgs'][$neworg];
        $accounts = intake_service::match_accounts(intake_service::normalise_email((string)$row['email']));
        $userid = count($accounts) === 1 ? (int)reset($accounts)->id : null;
        $facts = [
            'userid' => $userid,
            'accounts' => count($accounts),
            'org' => $userid ? intake_service::organisation_of($userid) : '',
            'neworg' => $neworg,
            'effective' => 'none',
            'newminimum' => $new['minimum'],
            'courses' => [],
            'gained' => [],
        ];
        if (!$userid) {
            return $facts;
        }
        if ($context['protection']) {
            $service = self::PROTECTION_SERVICE;
            $facts['effective'] = (string)$service::effective_level($userid);
        }
        $old = $facts['org'] !== '' && $facts['org'] !== $neworg ? self::org($facts['org']) : null;
        $oldcohortid = $old ? $old['cohortid'] : 0;

        $active = [];
        foreach (enrol_get_all_users_courses($userid, true) as $course) {
            $courseid = (int)$course->id;
            $active[] = $courseid;
            $viaold = false;
            $other = false;
            $instances = enrol_get_instances($courseid, false);
            foreach (enrol_get_course_users($courseid, true, [$userid]) as $enrolment) {
                $instance = $instances[$enrolment->ueenrolid] ?? null;
                if ($instance && $instance->enrol === cohort_enrolment::PLUGIN
                        && (int)$instance->customint1 === $oldcohortid && $oldcohortid) {
                    $viaold = true;
                } else {
                    $other = true;
                }
            }
            $facts['courses'][] = [
                'course' => (string)$course->idnumber,
                'category' => self::category_idnumber((int)$course->category),
                'viaold' => $viaold,
                'other' => $other,
                'newcohort' => in_array($courseid, $new['courses'], true),
            ];
        }
        foreach (array_diff($new['courses'], $active) as $courseid) {
            $facts['gained'][] = (string)get_course($courseid)->idnumber;
        }
        return $facts;
    }

    /**
     * @param int $categoryid
     * @return string the category's idnumber, '' when it has none
     */
    protected static function category_idnumber(int $categoryid): string {
        global $DB;
        static $cache = [];
        if (!array_key_exists($categoryid, $cache)) {
            $cache[$categoryid] = (string)$DB->get_field('course_categories', 'idnumber', ['id' => $categoryid]);
        }
        return $cache[$categoryid];
    }

    /**
     * After a move, protection is settled (spec 016), asking the service to apply what is
     * already set at most once. Never sets a level. True without spec 016.
     *
     * @param int $userid
     * @return bool
     */
    protected static function settled(int $userid): bool {
        if (!class_exists(self::PROTECTION_SERVICE)) {
            return true;
        }
        $service = self::PROTECTION_SERVICE;
        if (!$service::is_settled($userid)) {
            $service::apply($userid);
        }
        return (bool)$service::is_settled($userid);
    }

    /**
     * The plain sentence for a decision's reason.
     *
     * @param array $decision move_rules::classify()'s result
     * @param array $facts
     * @return string
     */
    protected static function reason(array $decision, array $facts): string {
        switch ($decision['reason']) {
            case '':
                return '';
            case 'lost':
                return get_string('admin:reason:lost', 'local_ltuse', implode(', ', $decision['lost']));
            case 'protection_below_new':
                return get_string('admin:reason:protection_below_new', 'local_ltuse', $facts['neworg']);
            default:
                return intake_service::reason($decision['reason']);
        }
    }

    /**
     * @param array $row
     * @param string $outcome
     * @param string $status
     * @param string $reason
     * @return array
     */
    protected static function answer(array $row, string $outcome, string $status, string $reason): array {
        return ['row' => (int)$row['row'], 'outcome' => $outcome, 'status' => $status, 'reason' => $reason];
    }
}
