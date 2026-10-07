<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Keeps each learner's course mentors enrolled in the courses they take, and removes them as
 * soon as the reason ends (spec 008, US5; research R10, data-model sections 3 and 4).
 *
 * Who is whose course mentor is course_mentor_rules::target(), which is pure. This class
 * gathers its facts and applies course_mentor_rules::diff() through core APIs:
 *
 *   enrolment  one enrol_self instance per course, customchar1 = 'ltct:coursementor',
 *              customint6 = 0 (no new self-enrolments), made on first need. Mentors are
 *              enrolled with enrol_user($instance, $userid, null), with NO role
 *   role       Teacher (shortname teacher, spec 012's "Course mentor") given separately with
 *              role_assign(..., 'local_ltuse', $instance->id). enrol_self's roles are not
 *              protected, so a role given through enrol_user() would have no component and
 *              unenrol_user() would leave it; and a mentor also enrolled another way would keep
 *              Teacher, and with it spec 016's identity entitlement
 *   removal    role_unassign(..., 'local_ltuse', $instance->id) first, then unenrol_user()
 *   groups     one per course mentor, idnumber ltct:mentorgroup:<mentor user id>, named
 *              "Mentor group <n>" and never after a person or an organisation (spec 002
 *              FR-019), holding the mentor and the learners they assess there. Members are
 *              added with component local_ltuse, which local_ltuse_allow_group_member_remove()
 *              protects in the interface. Groups are only ever created
 *
 * Spec 005 adds, in delivery (ltct:<slug>) courses only, the only courses this class syncs:
 *
 *   digests        every run, override 0 (each post by email) on every forum of the course for
 *                  each course mentor and each of their synced mentees, through
 *                  digest_overrides::set_for_course() (set() for the whole course in one read),
 *                  which keeps a person's own choice. In the removerole
 *                  step, before role_unassign() (the write needs mod/forum:viewdiscussion), a
 *                  removed mentor's overrides go, and so do those of anyone actively enrolled who
 *                  is no longer anyone's mentee here. Only overrides sync recorded are removed; a
 *                  person whose enrolment was suspended keeps theirs (data-model "Mail state")
 *   subscriptions  when a mentor is added, or gains a mentee, mentor_subscriptions subscribes them
 *                  to the discussions their mentees started or posted in (research R10)
 *
 * Enrolment, role and group outcomes are exactly spec 008's. The spec 005 raw reads are this
 * plugin's own local_ltuse_digest_override, by forum, and those digest_overrides lists. A spec
 * 005 step that fails is counted (overridefailures, subscribefailures) and skipped; it never
 * stops the removals.
 *
 * Not the manual method: enrol_manual_enrol_users takes the first manual instance, so a mentor
 * instance could capture the pilot coordinator's enrolments, and spec 004 counts manual as
 * pilot.
 *
 * sync_course() takes a per-course lock (\core\lock), so an observer and the reconcile never
 * interleave on one course, and every write is idempotent. Everything here does nothing while
 * local_ltuse/coursementorsync is 0 (plan decision 11; moodle/site/settings/admin.yaml).
 * Nothing here checks a capability: the external functions check theirs, and the observers and
 * the task run as core does.
 *
 * Raw reads, listed in README.md (constitution XI): role_assignments joined to context for a
 * learner's default mentors (the read spec 003's Mentoring page makes); role_assignments by
 * contextid, roleid and component for the Teacher assignments this plugin gave; groups and
 * groups_members by course, idnumber and component; course by idnumber; and this plugin's own
 * table. Logs and task output carry counts only (constitution III).
 */
class course_mentor_sync {

    /** customchar1 of the course-mentor enrolment instance. */
    const MARKER = 'ltct:coursementor';

    /** The instance's name on the course's enrolment methods page. */
    const ENROL_NAME = 'Course mentors';

    /** The enrol plugin of the course-mentor instance. */
    const ENROL_PLUGIN = 'self';

    /** Group idnumber prefix; the mentor's user id follows. Distinct from office hours'. */
    const GROUP_PREFIX = 'ltct:mentorgroup:';

    /** A mentor group's name; {n} is the next free number in the course. */
    const GROUP_NAME = 'Mentor group {n}';

    /** The component that owns the Teacher assignments and group memberships. */
    const COMPONENT = 'local_ltuse';

    /** One-course and cohort mentors (data-model section 3). */
    const TABLE = 'local_ltuse_course_mentor';

    /** Role shortnames: the course-mentor role, the learners' role and spec 003's. */
    const ROLE_TEACHER = 'teacher';
    const ROLE_STUDENT = 'student';
    const ROLE_MENTOR = 'mentor';

    /** Lock type and wait, for the per-course lock. */
    const LOCK_TYPE = 'local_ltuse';
    const LOCK_WAIT = 10;

    /** The switch (local_ltuse/coursementorsync). */
    const SETTING = 'coursementorsync';

    /** Ids per IN (...) list, so a large course never builds one huge query. */
    const CHUNK = 500;

    /**
     * @return bool the sync is switched on
     */
    public static function enabled(): bool {
        return (bool)get_config('local_ltuse', self::SETTING);
    }

    /**
     * A course this sync looks after: ltct:<slug>, not ltct:officehours.
     *
     * @param string $idnumber
     * @return bool
     */
    public static function is_ltct_course(string $idnumber): bool {
        return $idnumber !== enrolment_rules::OFFICEHOURS_COURSE
            && (bool)preg_match(enrolment_rules::COURSE_PATTERN, $idnumber);
    }

    /**
     * Whether an enrolment instance is a course-mentor instance. The observers ignore its
     * events, so the sync never triggers itself.
     *
     * @param stdClass|null $instance an enrol record
     * @return bool
     */
    public static function is_own_instance(?stdClass $instance): bool {
        return $instance && $instance->enrol === self::ENROL_PLUGIN
            && (string)($instance->customchar1 ?? '') === self::MARKER;
    }

    // --- syncing -----------------------------------------------------------------------------

    /**
     * Bring one course's course mentors into step.
     *
     * @param int $courseid
     * @return array counts of what changed; [] when switched off or not an ltct: course;
     *               ['busy' => 1] when another run holds the course (the reconcile catches up)
     */
    public static function sync_course(int $courseid): array {
        global $DB;
        if (!self::enabled()) {
            return [];
        }
        $course = $DB->get_record('course', ['id' => $courseid], 'id, idnumber');
        if (!$course || !self::is_ltct_course((string)$course->idnumber)) {
            return [];
        }
        $lock = \core\lock\lock_config::get_lock_factory(self::LOCK_TYPE)
            ->get_lock('coursementor:' . $courseid, self::LOCK_WAIT);
        if (!$lock) {
            return ['busy' => 1];
        }
        try {
            return self::apply_course($course);
        } finally {
            $lock->release();
        }
    }

    /**
     * Every ltct: course the learner is enrolled in, by any method and in any state, and every
     * course a one-course mentor is recorded for them in.
     *
     * @param int $userid
     * @return array counts, summed over the courses
     */
    public static function sync_learner(int $userid): array {
        global $DB;
        if (!self::enabled() || $userid <= 0) {
            return [];
        }
        $courseids = array_keys(enrol_get_all_users_courses($userid, false));
        $courseids = array_merge($courseids, $DB->get_fieldset_select(self::TABLE, 'DISTINCT courseid',
            'learnerid = :learnerid', ['learnerid' => $userid]));
        return self::sync_courses($courseids);
    }

    /**
     * Every course where the mentor is, or may need to become, a course mentor: the courses
     * they are enrolled in, the courses recorded for them, and the courses of every learner
     * they are the default mentor of.
     *
     * @param int $mentorid
     * @return array counts, summed over the courses
     */
    public static function sync_mentor(int $mentorid): array {
        global $DB;
        if (!self::enabled() || $mentorid <= 0) {
            return [];
        }
        $courseids = array_keys(enrol_get_all_users_courses($mentorid, false));
        $courseids = array_merge($courseids, $DB->get_fieldset_select(self::TABLE, 'DISTINCT courseid',
            'mentorid = :mentorid', ['mentorid' => $mentorid]));
        $roleid = self::role_ids()[self::ROLE_MENTOR] ?? 0;
        if ($roleid) {
            $sql = "SELECT DISTINCT ctx.instanceid
                      FROM {role_assignments} ra
                      JOIN {context} ctx ON ctx.id = ra.contextid
                     WHERE ra.userid = :mentorid AND ra.roleid = :roleid AND ctx.contextlevel = :level";
            $learners = $DB->get_fieldset_sql($sql, ['mentorid' => $mentorid, 'roleid' => $roleid,
                'level' => CONTEXT_USER]);
            foreach ($learners as $learnerid) {
                $courseids = array_merge($courseids, array_keys(enrol_get_all_users_courses((int)$learnerid, false)));
            }
        }
        return self::sync_courses($courseids);
    }

    /**
     * Every ltct: course, then any Teacher assignment local_ltuse gave in a course that is no
     * longer one, then records whose course, cohort or people are gone, and spec 005's digest
     * override records whose forum or person is gone. For the hourly task.
     *
     * @return array counts; [] when switched off
     */
    public static function reconcile(): array {
        global $DB;
        if (!self::enabled()) {
            return [];
        }
        $courseids = $DB->get_fieldset_select('course', 'id', $DB->sql_like('idnumber', ':prefix'),
            ['prefix' => $DB->sql_like_escape('ltct:') . '%']);
        $counts = self::sync_courses($courseids);
        $counts['courses'] = count($courseids);
        $counts['strayroles'] = self::remove_stray_roles();
        $counts['orphanrecords'] = self::remove_orphan_records();
        $counts['orphanoverrides'] = digest_overrides::remove_orphans();   // Spec 005.
        return $counts;
    }

    // --- one course --------------------------------------------------------------------------

    /**
     * Sync each course once and sum the counts.
     *
     * @param array $courseids
     * @return array
     */
    protected static function sync_courses(array $courseids): array {
        $total = [];
        foreach (array_unique(array_map('intval', $courseids)) as $courseid) {
            foreach (self::sync_course($courseid) as $what => $n) {
                $total[$what] = ($total[$what] ?? 0) + $n;
            }
        }
        return $total;
    }

    /**
     * Compute the course's target and apply the diff. Runs under the course's lock.
     *
     * @param stdClass $course id, idnumber
     * @return array counts
     */
    protected static function apply_course(stdClass $course): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $courseid = (int)$course->id;
        $plugin = enrol_get_plugin(self::ENROL_PLUGIN);
        $roles = self::role_ids();
        $teacherid = (int)($roles[self::ROLE_TEACHER] ?? 0);
        if (!$plugin || !$teacherid || empty($roles[self::ROLE_STUDENT])) {
            return [];
        }
        $context = \context_course::instance($courseid);
        $instances = enrol_get_instances($courseid, false);
        $instance = self::find_instance($instances);
        $users = enrol_get_course_users($courseid, false);

        $target = course_mentor_rules::target(self::facts($courseid, $instances, $users, $roles));
        [$state, $groups, $stray] = self::state($courseid, $context, $instance, $users, $teacherid);
        $diff = course_mentor_rules::diff($target, $state);

        if ($target['mentors']) {
            if (!$instance) {
                $instance = self::create_instance($courseid, $teacherid);
            } else if ((int)$instance->status !== ENROL_INSTANCE_ENABLED) {
                $plugin->update_status($instance, ENROL_INSTANCE_ENABLED);
                $instance->status = ENROL_INSTANCE_ENABLED;
            }
        }

        foreach ($diff['enrol'] as $userid) {
            $plugin->enrol_user($instance, $userid, null);
        }
        foreach ($diff['reactivate'] as $userid) {
            $plugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE);
        }
        foreach ($diff['addrole'] as $userid) {
            role_assign($teacherid, $userid, $context->id, self::COMPONENT, (int)$instance->id);
        }
        foreach ($diff['creategroups'] as $mentorid) {
            $groups[$mentorid] = self::create_group($courseid, $mentorid);
        }
        foreach ($diff['add'] as [$mentorid, $userid]) {
            if (!empty($groups[$mentorid])) {
                groups_add_member($groups[$mentorid]->id, $userid, self::COMPONENT, (int)$instance->id);
            }
        }
        foreach ($diff['remove'] as [$mentorid, $userid]) {
            if (!empty($groups[$mentorid])) {
                groups_remove_member($groups[$mentorid]->id, $userid);
            }
        }
        // Spec 005: after the groups, which mentor_subscriptions reads to find a mentor's mentees.
        // Each step is caught on its own, so one failure skips only that step, and is counted in
        // overridefailures or subscribefailures so the reconcile reports it. None stops spec 008's
        // removals below: Teacher must not outlive its reason (spec 016's identity entitlement).
        // A removal that fails is not lost for good: if the person is then unenrolled for the last
        // time, core deletes their forum_digests rows and forget_course() their records.
        $digests = ['overrides' => 0, 'overridesremoved' => 0, 'overridefailures' => 0, 'subscribefailures' => 0];
        $forums = null;
        try {
            $forums = self::forums($courseid);
            $digests['overrides'] = self::set_digests($target, $forums);
        } catch (\Throwable $e) {
            $digests['overridefailures']++;
            debugging('local_ltuse: could not give the course mentors their forum mail: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
        try {
            self::subscribe_added_mentors($diff, $courseid);
        } catch (\Throwable $e) {
            $digests['subscribefailures']++;
            debugging('local_ltuse: could not subscribe the course mentors to discussions: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
        try {
            // The overrides go before Teacher does, while the person can still see the forums;
            // digest_overrides skips anyone who already cannot.
            $forums = $forums ?? self::forums($courseid);
            $digests['overridesremoved'] = self::remove_digests($target, $diff['removerole'], array_keys($forums),
                $context);
        } catch (\Throwable $e) {
            $digests['overridefailures']++;
            debugging('local_ltuse: could not take away the course mentors\' forum mail: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
        // Teacher goes before the enrolment, so it never outlives its reason even if the
        // unenrolment then fails. A Teacher assignment tied to another instance (one deleted
        // and made again) has no reason by definition.
        foreach ($diff['removerole'] as $userid) {
            role_unassign($teacherid, $userid, $context->id, self::COMPONENT, (int)$instance->id);
        }
        foreach ($stray as [$userid, $itemid]) {
            role_unassign($teacherid, $userid, $context->id, self::COMPONENT, $itemid);
        }
        foreach ($diff['unenrol'] as $userid) {
            $plugin->unenrol_user($instance, $userid);
        }
        $counts = course_mentor_rules::counts($diff);
        $counts['removerole'] += count($stray);
        return $counts + $digests;
    }

    // --- spec 005: digest overrides and discussion subscriptions -------------------------------

    /**
     * Every forum of the course, from core's course cache.
     *
     * @param int $courseid
     * @return int[] forum instance id => course module id
     */
    protected static function forums(int $courseid): array {
        $forums = [];
        foreach (get_fast_modinfo($courseid)->get_instances_of('forum') as $cm) {
            $forums[(int)$cm->instance] = (int)$cm->id;
        }
        return $forums;
    }

    /**
     * Override 0 on every forum for each course mentor and each of their synced mentees. A
     * person's own setting, an override they set back to their default, and a person who
     * cannot see the forum are all left alone by digest_overrides::set_for_course(), which reads
     * the course's records and forum_digests rows once rather than once per person and forum.
     *
     * @param array $target course_mentor_rules::target()'s result
     * @param int[] $forums forum id => course module id
     * @return int how many were written this run
     */
    protected static function set_digests(array $target, array $forums): int {
        $people = [];
        foreach ($target['mentors'] as $mentorid => $learnerids) {
            $people[(int)$mentorid] = true;
            foreach ($learnerids as $learnerid) {
                $people[(int)$learnerid] = true;
            }
        }
        return digest_overrides::set_for_course(array_keys($people), $forums, 0);
    }

    /**
     * Take away the overrides sync recorded for each mentor being removed, and for each person
     * still actively enrolled here who is now nobody's mentee and no mentor. Someone whose
     * enrolment is suspended or gone has left: they keep theirs (data-model "Mail state").
     *
     * @param array $target course_mentor_rules::target()'s result
     * @param int[] $removed the mentors losing Teacher this run
     * @param int[] $forumids
     * @param \context_course $context
     * @return int how many overrides were reset
     */
    protected static function remove_digests(array $target, array $removed, array $forumids,
            \context_course $context): int {
        global $DB;
        if (!$forumids) {
            return 0;
        }
        $removed = array_map('intval', $removed);
        $keep = $target['learners'];
        foreach (array_keys($target['mentors']) as $mentorid) {
            $keep[(int)$mentorid] = true;
        }
        // This plugin's own table: who holds a recorded override on these forums.
        [$in, $params] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED);
        $holders = array_map('intval', $DB->get_fieldset_select(digest_overrides::TABLE, 'DISTINCT userid',
            "forumid $in", $params));
        $count = 0;
        foreach ($holders as $userid) {
            if (isset($keep[$userid])) {
                continue;
            }
            if (!in_array($userid, $removed, true) && !is_enrolled($context, $userid, '', true)) {
                continue;
            }
            foreach ($forumids as $forumid) {
                if (digest_overrides::remove($userid, $forumid) === 'removed') {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Subscribe each mentor added this run, and each mentor who gained a mentee, to the
     * discussions their mentees started or posted in. Idempotent.
     *
     * @param array $diff course_mentor_rules::diff()'s result
     * @param int $courseid
     */
    protected static function subscribe_added_mentors(array $diff, int $courseid): void {
        $mentors = array_fill_keys(array_map('intval', $diff['addrole']), true);
        foreach ($diff['add'] as [$mentorid, $userid]) {
            if ((int)$mentorid !== (int)$userid) {
                $mentors[(int)$mentorid] = true;
            }
        }
        foreach (array_keys($mentors) as $mentorid) {
            \local_ltuse\mentor_subscriptions::existing_for_mentor($mentorid, $courseid);
        }
    }

    /**
     * course_mentor_rules::target()'s facts for one course.
     *
     * @param int $courseid
     * @param stdClass[] $instances the course's enrol records, keyed by id
     * @param stdClass[] $users enrol_get_course_users() for the course
     * @param array $roles shortname => id
     * @return array
     */
    protected static function facts(int $courseid, array $instances, array $users, array $roles): array {
        global $DB;
        $enrolments = [];
        $learnerids = [];
        foreach ($users as $ue) {
            $instance = $instances[(int)$ue->ueenrolid] ?? null;
            if (!$instance || self::is_own_instance($instance)) {
                continue;
            }
            $method = self::method($instance);
            $enrolments[] = [
                'userid' => (int)$ue->id,
                'method' => $method,
                'cohortid' => $method === course_mentor_rules::METHOD_COHORT ? (int)$instance->customint1 : 0,
                'roleid' => (int)$instance->roleid,
                'active' => (int)$ue->uestatus === ENROL_USER_ACTIVE,
                'enabled' => (int)$instance->status === ENROL_INSTANCE_ENABLED,
                'timestart' => (int)$ue->uetimestart,
                'timeend' => (int)$ue->uetimeend,
                'suspended' => !empty($ue->suspended) || !empty($ue->deleted),
            ];
            $learnerids[(int)$ue->id] = (int)$ue->id;
        }

        $onecourse = [];
        $cohortmentors = [];
        foreach ($DB->get_records(self::TABLE, ['courseid' => $courseid], 'id', 'id, mentorid, learnerid, cohortid') as $r) {
            if ((int)$r->learnerid > 0) {
                $onecourse[] = [(int)$r->learnerid, (int)$r->mentorid];
            } else if ((int)$r->cohortid > 0) {
                $cohortmentors[] = [(int)$r->cohortid, (int)$r->mentorid];
            }
        }

        return [
            'now' => time(),
            'studentroleid' => (int)$roles[self::ROLE_STUDENT],
            'enrolments' => $enrolments,
            'onecourse' => $onecourse,
            'cohortmentors' => $cohortmentors,
            'defaults' => self::default_mentors(array_values($learnerids), (int)($roles[self::ROLE_MENTOR] ?? 0)),
        ];
    }

    /**
     * What a course has now: course-mentor enrolments, the Teacher assignments local_ltuse
     * gave, and the mentor groups and their local_ltuse members.
     *
     * @param int $courseid
     * @param \context_course $context
     * @param stdClass|null $instance the course-mentor instance
     * @param stdClass[] $users enrol_get_course_users() for the course
     * @param int $teacherid
     * @return array [diff()'s state, groups keyed by mentor id, [[userid, itemid]] Teacher
     *               assignments tied to anything but the current instance]
     */
    protected static function state(int $courseid, \context_course $context, ?stdClass $instance, array $users,
            int $teacherid): array {
        global $DB;
        $enrolled = [];
        if ($instance) {
            foreach ($users as $ue) {
                if ((int)$ue->ueenrolid === (int)$instance->id) {
                    $enrolled[(int)$ue->id] = (int)$ue->uestatus === ENROL_USER_ACTIVE;
                }
            }
        }

        $roles = [];
        $stray = [];
        foreach ($DB->get_records('role_assignments', ['contextid' => $context->id, 'roleid' => $teacherid,
                'component' => self::COMPONENT], '', 'id, userid, itemid') as $ra) {
            if ($instance && (int)$ra->itemid === (int)$instance->id) {
                $roles[] = (int)$ra->userid;
            } else {
                $stray[] = [(int)$ra->userid, (int)$ra->itemid];
            }
        }

        $groups = self::groups($courseid);
        $members = [];
        if ($groups) {
            $bygroup = [];
            foreach ($groups as $mentorid => $group) {
                $bygroup[(int)$group->id] = $mentorid;
            }
            [$in, $params] = $DB->get_in_or_equal(array_keys($bygroup), SQL_PARAMS_NAMED);
            $params['component'] = self::COMPONENT;
            foreach ($DB->get_records_select('groups_members', "groupid $in AND component = :component", $params,
                    '', 'id, groupid, userid') as $m) {
                $members[$bygroup[(int)$m->groupid]][] = (int)$m->userid;
            }
        }

        $state = ['enrolled' => $enrolled, 'roles' => $roles, 'groups' => array_keys($groups), 'members' => $members];
        return [$state, $groups, $stray];
    }

    /**
     * The learners' default mentors: holders of the mentor role in each learner's user context
     * (spec 003). The read spec 003's Mentoring page makes, restricted to these learners.
     *
     * @param int[] $learnerids
     * @param int $mentorroleid
     * @return array learnerid => [mentorid, ...]
     */
    protected static function default_mentors(array $learnerids, int $mentorroleid): array {
        global $DB;
        $out = [];
        if (!$learnerids || !$mentorroleid) {
            return $out;
        }
        foreach (array_chunk($learnerids, self::CHUNK) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $params += ['roleid' => $mentorroleid, 'level' => CONTEXT_USER];
            $sql = "SELECT ra.id, ra.userid AS mentorid, ctx.instanceid AS learnerid
                      FROM {role_assignments} ra
                      JOIN {context} ctx ON ctx.id = ra.contextid
                      JOIN {user} m ON m.id = ra.userid AND m.deleted = 0
                     WHERE ra.roleid = :roleid AND ctx.contextlevel = :level AND ctx.instanceid $in";
            foreach ($DB->get_records_sql($sql, $params) as $r) {
                $out[(int)$r->learnerid][] = (int)$r->mentorid;
            }
        }
        return $out;
    }

    /**
     * The method an enrolment instance counts as, for course_mentor_rules.
     *
     * @param stdClass $instance
     * @return string cohort, orgenrol, manual or other
     */
    protected static function method(stdClass $instance): string {
        if ($instance->enrol === 'cohort') {
            return course_mentor_rules::METHOD_COHORT;
        }
        if ($instance->enrol === \local_ltuse\organisation\access::ENROL_PLUGIN
                && (string)($instance->customchar1 ?? '') === \local_ltuse\organisation\access::ENROL_MARKER) {
            return course_mentor_rules::METHOD_ORGENROL;
        }
        return $instance->enrol === 'manual' ? 'manual' : 'other';
    }

    // --- the instance and the groups ---------------------------------------------------------

    /**
     * The course-mentor instance among a course's instances, or null. The oldest, should
     * there ever be two.
     *
     * @param stdClass[] $instances
     * @return stdClass|null
     */
    protected static function find_instance(array $instances): ?stdClass {
        foreach ($instances as $instance) {
            if (self::is_own_instance($instance)) {
                return $instance;
            }
        }
        return null;
    }

    /**
     * Make the course-mentor instance: enrol_self, closed to self-enrolment, no welcome
     * message, no inactivity unenrolment, a random password as a second barrier.
     *
     * @param int $courseid
     * @param int $teacherid recorded as the instance's role; never used to enrol (see the class)
     * @return stdClass the enrol record
     */
    protected static function create_instance(int $courseid, int $teacherid): stdClass {
        global $DB;
        $id = enrol_get_plugin(self::ENROL_PLUGIN)->add_instance(get_course($courseid), [
            'name' => self::ENROL_NAME,
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $teacherid,
            'customint6' => 0,                  // No new self-enrolments.
            'customint4' => 0,                  // No welcome message.
            'customint2' => 0,                  // Never unenrolled for inactivity.
            'customint3' => 0,                  // No limit.
            'enrolperiod' => 0,
            'expirynotify' => 0,
            'password' => random_string(20),    // A second barrier against self-enrolment.
            'customchar1' => self::MARKER,
        ]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The course's mentor groups, keyed by mentor id.
     *
     * @param int $courseid
     * @return stdClass[]
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
     * Make a mentor's group, named with the next free number and never after a person.
     * Visible to its own members only (GROUPS_VISIBILITY_MEMBERS), with participation on, so
     * spec 012's assessed activities can use it in separate-groups mode (plan decision 3); the
     * course stays in group mode 0 (spec 002 FR-011). Not GROUPS_VISIBILITY_OWN: for OWN and
     * NONE, groups_create_group() forces participation off (group/lib.php), and a group without
     * participation is never offered to an activity in group mode.
     *
     * @param int $courseid
     * @param int $mentorid
     * @return stdClass the group
     */
    protected static function create_group(int $courseid, int $mentorid): stdClass {
        global $DB;
        $n = count(self::groups($courseid)) + 1;
        do {
            $name = str_replace('{n}', (string)$n, self::GROUP_NAME);
            $n++;
        } while ($DB->record_exists('groups', ['courseid' => $courseid, 'name' => $name]));
        $id = groups_create_group((object)['courseid' => $courseid, 'name' => $name,
            'idnumber' => self::GROUP_PREFIX . $mentorid, 'visibility' => GROUPS_VISIBILITY_MEMBERS,
            'participation' => 1, 'enablemessaging' => 0]);
        return $DB->get_record('groups', ['id' => $id], '*', MUST_EXIST);
    }

    // --- the reconcile's clean-up ------------------------------------------------------------

    /**
     * Take away every Teacher assignment local_ltuse gave in a course that is no longer an
     * ltct: course. Inside one, sync_course() already removes any without a reason.
     *
     * @return int how many were removed
     */
    protected static function remove_stray_roles(): int {
        global $DB;
        $teacherid = (int)(self::role_ids()[self::ROLE_TEACHER] ?? 0);
        if (!$teacherid) {
            return 0;
        }
        $sql = "SELECT ra.id, ra.userid, ra.contextid, ra.itemid, c.idnumber
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :level
                  JOIN {course} c ON c.id = ctx.instanceid
                 WHERE ra.roleid = :roleid AND ra.component = :component";
        $removed = 0;
        foreach ($DB->get_records_sql($sql, ['level' => CONTEXT_COURSE, 'roleid' => $teacherid,
                'component' => self::COMPONENT]) as $ra) {
            if (self::is_ltct_course((string)$ra->idnumber)) {
                continue;
            }
            role_unassign($teacherid, (int)$ra->userid, (int)$ra->contextid, self::COMPONENT, (int)$ra->itemid);
            $removed++;
        }
        return $removed;
    }

    /**
     * Delete records whose course or cohort is gone, or whose mentor or learner is deleted.
     * user_deleted removes a person's records at once; this catches the rest.
     *
     * @return int how many were deleted
     */
    protected static function remove_orphan_records(): int {
        global $DB;
        $sql = "SELECT r.id
                  FROM {" . self::TABLE . "} r
             LEFT JOIN {course} c ON c.id = r.courseid
             LEFT JOIN {user} m ON m.id = r.mentorid AND m.deleted = 0
             LEFT JOIN {user} l ON l.id = r.learnerid AND l.deleted = 0
             LEFT JOIN {cohort} h ON h.id = r.cohortid
                 WHERE c.id IS NULL OR m.id IS NULL
                       OR (r.learnerid > 0 AND l.id IS NULL)
                       OR (r.cohortid > 0 AND h.id IS NULL)";
        $ids = $DB->get_fieldset_sql($sql);
        if ($ids) {
            $DB->delete_records_list(self::TABLE, 'id', $ids);
        }
        return count($ids);
    }

    /**
     * Role ids by shortname. Read each time rather than cached, so a role made after the first
     * call (a site declaration applied mid-request, or a test) is seen.
     *
     * @return array shortname => id
     */
    public static function role_ids(): array {
        global $DB;
        [$in, $params] = $DB->get_in_or_equal([self::ROLE_TEACHER, self::ROLE_STUDENT, self::ROLE_MENTOR],
            SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_records_select_menu('role', "shortname $in", $params, '',
            'shortname, id'));
    }
}
