<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * The course mentors a learner should have in one course (spec 008, research R4, R10).
 *
 * First match wins: one-course mentors recorded for (learner, course); else the mentors
 * recorded for each cohort that enrols the learner there; else the learner's default mentors
 * (the mentor role in their user context, spec 003). Only learners with an active Student
 * enrolment through cohort sync or the Organisation enrolment count (data-model section 4).
 * Precedence 1 and 2 replace the default for that course, never add to it (plan decision 2).
 *
 * "Active" is all of: the user enrolment active, its instance enabled, now within
 * timestart/timeend, and the account not suspended. "Student" is the instance's roleid, never
 * the user's role assignments at the moment of an event, because core removes the role before
 * it fires user_enrolment_deleted. A pilot learner (manual enrolment) never counts: pilots are
 * assessed by the pilot coordinator (stage 7).
 *
 * diff() turns the target into the writes course_mentor_sync makes: enrol, reactivate, give
 * Teacher, make groups, add and remove group members, take Teacher away, unenrol.
 *
 * PURE: no Moodle call, no database read, so tests/admin_harness.php tests every case
 * without Moodle. The caller turns Moodle's constants into the booleans below. Facts that are
 * missing or malformed give an empty target, which removes course mentors rather than keeping
 * one without a reason: a course-mentor enrolment entitles its holder to a protected learner's
 * real identity (spec 016 FR-006), so this fails towards removal.
 */
class course_mentor_rules {

    /** The enrolment methods a counted learner may come through. */
    const METHOD_COHORT = 'cohort';
    const METHOD_ORGENROL = 'orgenrol';

    /** Where a learner's course mentors came from, first match wins. */
    const SOURCE_ONECOURSE = 'onecourse';
    const SOURCE_COHORT = 'cohort';
    const SOURCE_DEFAULT = 'default';

    /**
     * Who should be a course mentor in one course, and of whom.
     *
     * @param array $facts [
     *     'now' => int,
     *     'studentroleid' => int,
     *     'enrolments' => [[userid, method (cohort|orgenrol|manual|other), cohortid (the
     *         instance's cohort, 0 for none), roleid (the instance's), active (the user
     *         enrolment's status is active), enabled (the instance is enabled), timestart,
     *         timeend, suspended (the account)], ...],
     *     'onecourse' => [[learnerid, mentorid], ...]   recorded for this course,
     *     'cohortmentors' => [[cohortid, mentorid], ...] recorded for this course,
     *     'defaults' => [learnerid => [mentorid, ...]]   the mentor role in each user context,
     * ]
     * @return array ['learners' => [learnerid => source], 'mentors' => [mentorid => [learnerid, ...]]],
     *               both sorted by id
     */
    public static function target(array $facts): array {
        $empty = ['learners' => [], 'mentors' => []];
        foreach (['now', 'studentroleid', 'enrolments', 'onecourse', 'cohortmentors', 'defaults'] as $key) {
            if (!array_key_exists($key, $facts)) {
                return $empty;
            }
        }
        if (!is_int($facts['now']) || !is_int($facts['studentroleid']) || $facts['studentroleid'] <= 0
                || !is_array($facts['enrolments']) || !is_array($facts['onecourse'])
                || !is_array($facts['cohortmentors']) || !is_array($facts['defaults'])) {
            return $empty;
        }

        // Each counted learner, with the cohorts that actively enrol them as a Student.
        $counted = [];
        foreach ($facts['enrolments'] as $e) {
            if (!self::complete($e)) {
                return $empty;
            }
            if (!self::is_active_student($e, $facts['now'], $facts['studentroleid'])) {
                continue;
            }
            $userid = (int)$e['userid'];
            $counted[$userid] = $counted[$userid] ?? [];
            if ($e['method'] === self::METHOD_COHORT && (int)$e['cohortid'] > 0) {
                $counted[$userid][(int)$e['cohortid']] = true;
            }
        }

        $onecourse = self::group_pairs($facts['onecourse']);
        $bycohort = self::group_pairs($facts['cohortmentors']);

        $learners = [];
        $mentors = [];
        foreach ($counted as $learnerid => $cohorts) {
            if (!empty($onecourse[$learnerid])) {
                $chosen = $onecourse[$learnerid];
                $source = self::SOURCE_ONECOURSE;
            } else {
                $chosen = [];
                foreach (array_keys($cohorts) as $cohortid) {
                    foreach ($bycohort[$cohortid] ?? [] as $mentorid) {
                        $chosen[$mentorid] = $mentorid;
                    }
                }
                $source = self::SOURCE_COHORT;
                if (!$chosen) {
                    foreach ((array)($facts['defaults'][$learnerid] ?? []) as $mentorid) {
                        $chosen[(int)$mentorid] = (int)$mentorid;
                    }
                    $source = self::SOURCE_DEFAULT;
                }
            }
            unset($chosen[$learnerid]);     // Nobody is their own course mentor.
            $chosen = array_filter($chosen, function($id) {
                return $id > 0;
            });
            if (!$chosen) {
                continue;
            }
            $learners[$learnerid] = $source;
            foreach ($chosen as $mentorid) {
                $mentors[$mentorid][] = $learnerid;
            }
        }
        ksort($learners);
        ksort($mentors);
        foreach ($mentors as $mentorid => $ids) {
            $ids = array_values(array_unique($ids));
            sort($ids);
            $mentors[$mentorid] = $ids;
        }
        return ['learners' => $learners, 'mentors' => $mentors];
    }

    /**
     * Whether one enrolment counts its user as a learner of the course (data-model section 4).
     *
     * @param array $e one of target()'s enrolments
     * @param int $now
     * @param int $studentroleid
     * @return bool
     */
    public static function is_active_student(array $e, int $now, int $studentroleid): bool {
        if (!self::complete($e)) {
            return false;
        }
        if (!in_array($e['method'], [self::METHOD_COHORT, self::METHOD_ORGENROL], true)) {
            return false;               // A pilot's manual enrolment, or any other method.
        }
        if ((int)$e['roleid'] !== $studentroleid) {
            return false;
        }
        if (!$e['active'] || !$e['enabled'] || $e['suspended']) {
            return false;
        }
        if ((int)$e['timestart'] > $now) {
            return false;
        }
        if ((int)$e['timeend'] !== 0 && (int)$e['timeend'] <= $now) {
            return false;
        }
        return true;
    }

    /**
     * The writes that bring a course from its current state to the target.
     *
     * Each course mentor is enrolled through the course-mentor instance, holds Teacher given by
     * local_ltuse for it, and is in their own group with exactly the learners they assess
     * there. Anyone else holding any of those loses it. Groups are only ever created, never
     * deleted, so an assessor's group history stays.
     *
     * @param array $target target()'s result
     * @param array $state [
     *     'enrolled' => [userid => active bool]   user enrolments in the course-mentor instance,
     *     'roles' => [userid, ...]                 Teacher held through local_ltuse for it,
     *     'groups' => [mentorid, ...]              mentor groups that exist,
     *     'members' => [mentorid => [userid, ...]] their local_ltuse memberships,
     * ]
     * @return array enrol, reactivate, addrole, creategroups, add [[mentorid, userid]],
     *               remove [[mentorid, userid]], removerole, unenrol
     */
    public static function diff(array $target, array $state): array {
        $diff = ['enrol' => [], 'reactivate' => [], 'addrole' => [], 'creategroups' => [], 'add' => [],
            'remove' => [], 'removerole' => [], 'unenrol' => []];
        $mentors = (array)($target['mentors'] ?? []);
        $enrolled = (array)($state['enrolled'] ?? []);
        $roles = array_map('intval', (array)($state['roles'] ?? []));
        $groups = array_map('intval', (array)($state['groups'] ?? []));
        $members = (array)($state['members'] ?? []);

        foreach ($mentors as $mentorid => $learnerids) {
            $mentorid = (int)$mentorid;
            if (!array_key_exists($mentorid, $enrolled)) {
                $diff['enrol'][] = $mentorid;
            } else if (!$enrolled[$mentorid]) {
                $diff['reactivate'][] = $mentorid;
            }
            if (!in_array($mentorid, $roles, true)) {
                $diff['addrole'][] = $mentorid;
            }
            if (!in_array($mentorid, $groups, true)) {
                $diff['creategroups'][] = $mentorid;
            }
            $want = array_merge([$mentorid], array_map('intval', (array)$learnerids));
            $have = array_map('intval', (array)($members[$mentorid] ?? []));
            foreach (array_diff($want, $have) as $userid) {
                $diff['add'][] = [$mentorid, (int)$userid];
            }
            foreach (array_diff($have, $want) as $userid) {
                $diff['remove'][] = [$mentorid, (int)$userid];
            }
        }
        // A mentor group whose mentor has no learner left here is emptied, mentor included.
        foreach ($members as $mentorid => $have) {
            if (isset($mentors[(int)$mentorid])) {
                continue;
            }
            foreach (array_map('intval', (array)$have) as $userid) {
                $diff['remove'][] = [(int)$mentorid, $userid];
            }
        }
        // Teacher goes before the enrolment, so the role never outlives its reason even if the
        // unenrolment then fails.
        foreach ($roles as $userid) {
            if (!isset($mentors[$userid])) {
                $diff['removerole'][] = $userid;
            }
        }
        foreach (array_keys($enrolled) as $userid) {
            if (!isset($mentors[(int)$userid])) {
                $diff['unenrol'][] = (int)$userid;
            }
        }
        foreach (['enrol', 'reactivate', 'addrole', 'creategroups', 'removerole', 'unenrol'] as $key) {
            $list = array_values(array_unique($diff[$key]));
            sort($list);
            $diff[$key] = $list;
        }
        return $diff;
    }

    /**
     * A diff as counts, for the reconcile task's output. Never ids.
     *
     * @param array $diff
     * @return array<string, int>
     */
    public static function counts(array $diff): array {
        $counts = [];
        foreach (['enrol', 'reactivate', 'addrole', 'creategroups', 'add', 'remove', 'removerole', 'unenrol'] as $key) {
            $counts[$key] = count((array)($diff[$key] ?? []));
        }
        return $counts;
    }

    /**
     * @param mixed $e
     * @return bool every field target() reads is present
     */
    protected static function complete($e): bool {
        if (!is_array($e)) {
            return false;
        }
        foreach (['userid', 'method', 'cohortid', 'roleid', 'active', 'enabled', 'timestart', 'timeend',
                'suspended'] as $key) {
            if (!array_key_exists($key, $e)) {
                return false;
            }
        }
        return is_string($e['method']) && (int)$e['userid'] > 0;
    }

    /**
     * [[key, mentorid], ...] as [key => [mentorid => mentorid]].
     *
     * @param array $pairs
     * @return array
     */
    protected static function group_pairs(array $pairs): array {
        $out = [];
        foreach ($pairs as $pair) {
            $pair = array_values((array)$pair);
            if (count($pair) < 2 || (int)$pair[0] <= 0 || (int)$pair[1] <= 0) {
                continue;
            }
            $out[(int)$pair[0]][(int)$pair[1]] = (int)$pair[1];
        }
        return $out;
    }
}
