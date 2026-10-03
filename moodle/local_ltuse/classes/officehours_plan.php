<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

/**
 * What the office-hours course must change to match spec 003's mentor relationships (spec 011,
 * research R16; data-model "Office-hours membership").
 *
 * The invariant: for every mentor relationship (mentor M, learner L), both are actively
 * enrolled in ltct:officehours (M as teacher, L as student), M's group ltct:mentor:<M> exists,
 * and both are members of it; nothing else is tagged local_ltuse there.
 *
 * diff() is pure: it is handed what is and what should be, as plain arrays, and returns the
 * changes. officehours::reconcile() and sync_user() apply them. tests/officehours_harness.php
 * tests it without Moodle.
 */
class officehours_plan {

    /** Role shortnames in the office-hours course. */
    const MENTOR_ROLE = 'teacher';
    const LEARNER_ROLE = 'student';

    /**
     * @param array $pairs [[mentorid, learnerid], ...] every mentor relationship
     * @param int[] $groups mentor ids whose group ltct:mentor:<id> exists
     * @param array $members mentorid => [userid, ...]: the local_ltuse memberships of each group
     * @param array $enrolments userid => ['active' => bool, 'roles' => [shortname, ...]] in the
     *                          course's manual instance
     * @return array creategroups [mentorid], add [[mentorid, userid]], remove [[mentorid, userid]],
     *               enrol [[userid, role]], reactivate [userid], suspend [userid],
     *               addrole [[userid, role]], removerole [[userid, role]]
     */
    public static function diff(array $pairs, array $groups, array $members, array $enrolments): array {
        $wantmembers = [];
        $wantroles = [];
        foreach ($pairs as [$mentorid, $learnerid]) {
            $mentorid = (int)$mentorid;
            $learnerid = (int)$learnerid;
            if ($mentorid <= 0 || $learnerid <= 0 || $mentorid === $learnerid) {
                continue;
            }
            $wantmembers[$mentorid][$mentorid] = true;
            $wantmembers[$mentorid][$learnerid] = true;
            $wantroles[$mentorid][self::MENTOR_ROLE] = true;
            $wantroles[$learnerid][self::LEARNER_ROLE] = true;
        }

        $out = ['creategroups' => [], 'add' => [], 'remove' => [], 'enrol' => [], 'reactivate' => [],
            'suspend' => [], 'addrole' => [], 'removerole' => []];
        $groups = array_map('intval', $groups);

        ksort($wantmembers);
        foreach ($wantmembers as $mentorid => $users) {
            if (!in_array($mentorid, $groups, true)) {
                $out['creategroups'][] = $mentorid;
            }
            $have = array_map('intval', $members[$mentorid] ?? []);
            foreach (array_keys($users) as $userid) {
                if (!in_array($userid, $have, true)) {
                    $out['add'][] = [$mentorid, $userid];
                }
            }
        }
        ksort($members);
        foreach ($members as $mentorid => $users) {
            foreach ($users as $userid) {
                if (empty($wantmembers[(int)$mentorid][(int)$userid])) {
                    $out['remove'][] = [(int)$mentorid, (int)$userid];
                }
            }
        }

        ksort($wantroles);
        foreach ($wantroles as $userid => $roles) {
            $roles = array_keys($roles);
            sort($roles);
            $enrolment = $enrolments[$userid] ?? null;
            if ($enrolment === null) {
                $out['enrol'][] = [$userid, $roles[0]];
                foreach (array_slice($roles, 1) as $role) {
                    $out['addrole'][] = [$userid, $role];
                }
                continue;
            }
            if (empty($enrolment['active'])) {
                $out['reactivate'][] = $userid;
            }
            $have = $enrolment['roles'] ?? [];
            foreach ($roles as $role) {
                if (!in_array($role, $have, true)) {
                    $out['addrole'][] = [$userid, $role];
                }
            }
            foreach ([self::MENTOR_ROLE, self::LEARNER_ROLE] as $role) {
                if (in_array($role, $have, true) && !in_array($role, $roles, true)) {
                    $out['removerole'][] = [$userid, $role];
                }
            }
        }
        ksort($enrolments);
        foreach ($enrolments as $userid => $enrolment) {
            if (!isset($wantroles[(int)$userid]) && !empty($enrolment['active'])) {
                // Suspended, never unenrolled, so their slots and appointments keep their history.
                $out['suspend'][] = (int)$userid;
            }
        }
        return $out;
    }

    /**
     * The counts of a diff, which is all a log or a report may show (constitution III).
     *
     * @param array $diff
     * @return array<string, int>
     */
    public static function counts(array $diff): array {
        return array_map('count', $diff);
    }
}
