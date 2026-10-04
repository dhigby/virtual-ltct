<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps the office-hours course in step with spec 003's mentor relationships (spec 011, D6,
 * research R16).
 *
 * mod_scheduler shows a learner only the slots of a teacher who shares a group with them. A
 * mentor relationship is a user-context role, independent of any course, so this class turns
 * each one into membership of the mentor's group in ltct:officehours:
 *
 *   enrolment   one manual instance named ltct:officehours, mentors as teacher, mentees as
 *               student; suspended (never unenrolled) when their last relationship ends, so
 *               their slots and appointments keep their history
 *   groups      ltct:mentor:<mentor user id>, visibility GROUPS_VISIBILITY_OWN, so mentees of
 *               one mentor never see one another, and a name from the template that carries no
 *               person's name (a mentor's name may be a protected identity, spec 016). Never
 *               GROUPS_VISIBILITY_NONE: a student's group list would then omit it and the
 *               scheduler would show them no slots at all
 *   members     added with component local_ltuse and itemid = the mentor's id, which
 *               local_ltuse_allow_group_member_remove() protects in the interface
 *
 * sync_pair() runs from 003's role observers; reconcile() runs from apply and from the hourly
 * task\officehours_reconcile, and repairs anything changed by hand. The decision is
 * officehours_plan::diff(), which is pure. Every write goes through a core API: enrol_user(),
 * update_user_enrol(), role_assign(), role_unassign(), groups_create_group(),
 * groups_add_member(), groups_remove_member(). The reads of role_assignments, user_enrolments
 * and groups_members are by indexed columns of stable core tables. Logs and reports carry
 * counts only (constitution III).
 */
class officehours {

    /** Course idnumber (moodle/site/office-hours.yaml). */
    const COURSE = 'ltct:officehours';

    /** The scheduler's course-module idnumber. */
    const SCHEDULER = 'ltct:officehours:scheduler';

    /** The manual enrolment instance's name. */
    const ENROL_NAME = 'ltct:officehours';

    /** Group idnumber prefix; the mentor's user id follows. */
    const GROUP_PREFIX = 'ltct:mentor:';

    /** Config key holding the group name template, stored by siteconfig\officehours. */
    const TEMPLATE_CONFIG = 'officehours_group_template';

    /** Used until apply has stored a template. */
    const DEFAULT_TEMPLATE = 'Office hours {n}';

    /**
     * @return \stdClass|null the office-hours course, or null before apply has made it
     */
    public static function course(): ?\stdClass {
        global $DB;
        return $DB->get_record('course', ['idnumber' => self::COURSE]) ?: null;
    }

    /**
     * @return \stdClass|null the scheduler's course_modules record (with modname), or null
     */
    public static function scheduler_cm(): ?\stdClass {
        $course = self::course();
        return $course ? util::cm_by_idnumber((int)$course->id, self::SCHEDULER) : null;
    }

    /**
     * The scheduler's instance id, cached for the request, or 0 when there is none.
     *
     * @return int
     */
    public static function scheduler_instance(): int {
        static $instance = null;
        if ($instance === null || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $cm = self::scheduler_cm();
            $instance = ($cm && $cm->modname === 'scheduler') ? (int)$cm->instance : 0;
        }
        return $instance;
    }

    /**
     * The course's manual enrolment instance, or null.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function enrol_instance(int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual', 'name' => self::ENROL_NAME]) ?: null;
    }

    // --- syncing -------------------------------------------------------------------------

    /**
     * Bring one mentor and one learner into step, after their relationship starts or ends.
     *
     * @param int $mentorid
     * @param int $learnerid
     * @return array counts of what changed
     */
    public static function sync_pair(int $mentorid, int $learnerid): array {
        return self::sync_users([$mentorid, $learnerid]);
    }

    /**
     * Bring one user into step, after their account is deleted or for any other reason.
     *
     * @param int $userid
     * @return array counts of what changed
     */
    public static function sync_user(int $userid): array {
        global $DB;
        $counts = self::sync_users([$userid]);
        // A deleted user's booking records go too (data-model "Booking record").
        if ($DB->get_manager()->table_exists(booking_notice::TABLE)) {
            $DB->delete_records_select(booking_notice::TABLE, 'learnerid = :l OR mentorid = :m',
                ['l' => $userid, 'm' => $userid]);
        }
        return $counts;
    }

    /**
     * Bring the whole course into step with every mentor relationship (the reconcile task,
     * and apply after it creates the course). Also clears booking records whose calendar
     * event is gone, which sends no message.
     *
     * @return array counts of what changed, or [] when the course does not exist yet
     */
    public static function reconcile(): array {
        global $DB;
        $course = self::course();
        if (!$course) {
            return [];
        }
        $counts = self::apply_diff($course, self::pairs(), null);
        if ($DB->get_manager()->table_exists(booking_notice::TABLE)) {
            $sql = "SELECT b.id FROM {" . booking_notice::TABLE . "} b
                 LEFT JOIN {event} e ON e.id = b.eventid
                     WHERE e.id IS NULL";
            $orphans = $DB->get_fieldset_sql($sql);
            if ($orphans) {
                $DB->delete_records_list(booking_notice::TABLE, 'id', $orphans);
            }
            $counts['orphanbookings'] = count($orphans);
        }
        return $counts;
    }

    /**
     * Sync only what concerns these users: every pair they are in, and their own memberships
     * and enrolment.
     *
     * @param int[] $userids
     * @return array counts
     */
    protected static function sync_users(array $userids): array {
        $course = self::course();
        if (!$course) {
            return []; // apply has not made the course yet; it reconciles when it does.
        }
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        $pairs = array_values(array_filter(self::pairs(), function($pair) use ($userids) {
            return in_array((int)$pair[0], $userids, true) || in_array((int)$pair[1], $userids, true);
        }));
        return self::apply_diff($course, $pairs, $userids);
    }

    /**
     * Compute and apply the diff, limited to $scope users when given.
     *
     * @param \stdClass $course
     * @param array $pairs [[mentorid, learnerid], ...]
     * @param int[]|null $scope only these users' memberships and enrolments, or null for all
     * @return array counts
     */
    protected static function apply_diff(\stdClass $course, array $pairs, ?array $scope): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $plugin = enrol_get_plugin('manual');
        $instance = self::enrol_instance((int)$course->id);
        if (!$plugin || !$instance) {
            return [];
        }
        [$diff, $groups, $roleids, $context] = self::compute($course, $instance, $pairs, $scope);

        foreach ($diff['enrol'] as [$userid, $role]) {
            if (!empty($roleids[$role])) {
                $plugin->enrol_user($instance, $userid, (int)$roleids[$role]);
            }
        }
        foreach ($diff['reactivate'] as $userid) {
            $plugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE);
        }
        foreach ($diff['addrole'] as [$userid, $role]) {
            if (!empty($roleids[$role])) {
                role_assign((int)$roleids[$role], $userid, $context->id);
            }
        }
        foreach ($diff['removerole'] as [$userid, $role]) {
            if (!empty($roleids[$role])) {
                role_unassign((int)$roleids[$role], $userid, $context->id);
            }
        }
        foreach ($diff['creategroups'] as $mentorid) {
            $groups[$mentorid] = self::create_group((int)$course->id, $mentorid);
        }
        foreach ($diff['add'] as [$mentorid, $userid]) {
            if (!empty($groups[$mentorid])) {
                groups_add_member($groups[$mentorid]->id, $userid, 'local_ltuse', $mentorid);
            }
        }
        foreach ($diff['remove'] as [$mentorid, $userid]) {
            if (!empty($groups[$mentorid])) {
                groups_remove_member($groups[$mentorid]->id, $userid);
            }
        }
        foreach ($diff['suspend'] as $userid) {
            $plugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
        }
        return officehours_plan::counts($diff);
    }

    /**
     * What reconcile() would change now, as counts. WRITES NOTHING: siteconfig\officehours
     * reports it in drift.
     *
     * @param \stdClass $course
     * @param \stdClass $instance the course's manual enrolment instance
     * @return array<string, int>
     */
    public static function preview(\stdClass $course, \stdClass $instance): array {
        return officehours_plan::counts(self::compute($course, $instance, self::pairs(), null)[0]);
    }

    /**
     * Read the course's state and work out the diff. WRITES NOTHING.
     *
     * @param \stdClass $course
     * @param \stdClass $instance
     * @param array $pairs
     * @param int[]|null $scope
     * @return array [diff, groups keyed by mentor id, role ids by shortname, course context]
     */
    protected static function compute(\stdClass $course, \stdClass $instance, array $pairs, ?array $scope): array {
        global $DB;
        $context = \context_course::instance((int)$course->id);
        $roleids = $DB->get_records_menu('role', null, '', 'shortname, id');
        $groups = self::groups((int)$course->id);
        $members = self::members(array_map(function($g) {
            return (int)$g->id;
        }, $groups));
        $enrolments = self::enrolments($instance, $context, $roleids);

        if ($scope !== null) {
            // Only the users these pairs touch take part, so a sync for one pair never
            // suspends or removes anyone else. Groups stay whole: they are only ever created.
            $relevant = $scope;
            foreach ($pairs as [$mentorid, $learnerid]) {
                $relevant[] = (int)$mentorid;
                $relevant[] = (int)$learnerid;
            }
            $relevant = array_values(array_unique($relevant));
            $members = array_map(function($users) use ($relevant) {
                return array_values(array_intersect(array_map('intval', $users), $relevant));
            }, $members);
            $enrolments = array_intersect_key($enrolments, array_flip($relevant));
        }

        $diff = officehours_plan::diff($pairs, array_keys($groups), $members, $enrolments);
        return [$diff, $groups, $roleids, $context];
    }

    // --- reads ---------------------------------------------------------------------------

    /**
     * Every mentor relationship: a `mentor` role assignment in a learner's user context. The
     * same raw read spec 003's Mentoring page makes (role_assignments joined to context, by
     * indexed columns), listed in the README.
     *
     * @return array [[mentorid, learnerid], ...]
     */
    public static function pairs(): array {
        global $DB;
        $roleid = mentoring::role_id();
        if (!$roleid) {
            return [];
        }
        $sql = "SELECT ra.id, ra.userid AS mentorid, ctx.instanceid AS learnerid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                  JOIN {user} m ON m.id = ra.userid AND m.deleted = 0
                  JOIN {user} l ON l.id = ctx.instanceid AND l.deleted = 0
                 WHERE ra.roleid = :roleid AND ctx.contextlevel = :level";
        $pairs = [];
        foreach ($DB->get_records_sql($sql, ['roleid' => $roleid, 'level' => CONTEXT_USER]) as $r) {
            $pairs[(int)$r->mentorid . ':' . (int)$r->learnerid] = [(int)$r->mentorid, (int)$r->learnerid];
        }
        return array_values($pairs);
    }

    /**
     * The course's mentor groups, keyed by mentor id.
     *
     * @param int $courseid
     * @return \stdClass[]
     */
    protected static function groups(int $courseid): array {
        global $DB;
        $records = $DB->get_records_select('groups', 'courseid = :courseid AND ' .
            $DB->sql_like('idnumber', ':prefix'), ['courseid' => $courseid,
            'prefix' => $DB->sql_like_escape(self::GROUP_PREFIX) . '%']);
        $out = [];
        foreach ($records as $group) {
            $id = substr((string)$group->idnumber, strlen(self::GROUP_PREFIX));
            if (ctype_digit($id)) {
                $out[(int)$id] = $group;
            }
        }
        return $out;
    }

    /**
     * The local_ltuse memberships of the given groups, keyed by mentor id (the itemid).
     *
     * @param int[] $groupids
     * @return array mentorid => [userid, ...]
     */
    protected static function members(array $groupids): array {
        global $DB;
        if (!$groupids) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
        $params['component'] = 'local_ltuse';
        $out = [];
        foreach ($DB->get_records_select('groups_members', "groupid $in AND component = :component", $params,
                '', 'id, itemid, userid') as $m) {
            $out[(int)$m->itemid][] = (int)$m->userid;
        }
        return $out;
    }

    /**
     * Enrolments in the manual instance, with each user's roles in the course.
     *
     * @param \stdClass $instance
     * @param \context_course $context
     * @param array $roleids shortname => id
     * @return array userid => ['active' => bool, 'roles' => [shortname]]
     */
    protected static function enrolments(\stdClass $instance, \context_course $context, array $roleids): array {
        global $DB;
        $names = array_flip($roleids);
        $out = [];
        foreach ($DB->get_records('user_enrolments', ['enrolid' => $instance->id], '', 'id, userid, status') as $ue) {
            $out[(int)$ue->userid] = ['active' => (int)$ue->status === ENROL_USER_ACTIVE, 'roles' => []];
        }
        if ($out) {
            [$in, $params] = $DB->get_in_or_equal(array_keys($out), SQL_PARAMS_NAMED);
            $params['contextid'] = $context->id;
            foreach ($DB->get_records_select('role_assignments', "contextid = :contextid AND userid $in", $params,
                    '', 'id, userid, roleid') as $ra) {
                if (isset($names[$ra->roleid]) && isset($out[(int)$ra->userid])) {
                    $out[(int)$ra->userid]['roles'][] = $names[$ra->roleid];
                }
            }
        }
        return $out;
    }

    /**
     * Create a mentor's group. Its name is the template with the next free number, and never
     * a person's name.
     *
     * @param int $courseid
     * @param int $mentorid
     * @return \stdClass the group
     */
    protected static function create_group(int $courseid, int $mentorid): \stdClass {
        global $DB;
        $template = (string)(get_config('local_ltuse', self::TEMPLATE_CONFIG) ?: self::DEFAULT_TEMPLATE);
        $n = $DB->count_records_select('groups', 'courseid = :courseid AND ' .
            $DB->sql_like('idnumber', ':prefix'), ['courseid' => $courseid,
            'prefix' => $DB->sql_like_escape(self::GROUP_PREFIX) . '%']) + 1;
        do {
            $name = str_replace('{n}', (string)$n, $template);
            $n++;
        } while ($DB->record_exists('groups', ['courseid' => $courseid, 'name' => $name]));
        $id = groups_create_group((object)['courseid' => $courseid, 'name' => $name,
            'idnumber' => self::GROUP_PREFIX . $mentorid, 'visibility' => GROUPS_VISIBILITY_OWN,
            'participation' => 1, 'enablemessaging' => 0]);
        return $DB->get_record('groups', ['id' => $id], '*', MUST_EXIST);
    }
}
