<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

use context_user;
use moodle_url;

/**
 * What the Mentoring page and the app handler show (spec 003, research R3).
 *
 * A mentor relationship is the declared `mentor` role held in a learner's user context. This
 * class turns those assignments into one list for one user, used by both the browser page
 * (mentoring.php) and the Moodle app handler (output\mobile), so they cannot differ:
 *
 *   learners  the people the user mentors, each with their courses and completion
 *   mentors   the people who mentor the user (FR-010)
 *
 * Scope (FR-005): the role_assignments query only finds candidates. Each learner is shown only
 * if the user holds local/ltuse:viewmenteeprogress in that learner's own user context, checked
 * on every call, so an ended relationship disappears on the next page load (FR-009).
 *
 * What it never reads: quiz attempts or answers, submissions, logs, grades (core's Grades
 * overview is linked, not copied), or any profile field. Nothing here writes.
 *
 * progress_status() and sort_courses() are pure, and tests/mentoring_harness.php tests them
 * without Moodle. for_user() gathers their inputs through public APIs, plus two raw reads of
 * stable core tables by indexed userid (listed in the plugin README, constitution XI):
 * role_assignments joined to context (as block_mentees does), and course_completions.
 */
class mentoring {

    /** The declared role's shortname (moodle/site/roles.yaml). */
    const ROLE = 'mentor';

    /** Course states, in the order a mentor reads them. */
    const IN_PROGRESS = 'inprogress';
    const NOT_STARTED = 'notstarted';
    const NOT_TRACKED = 'nottracked';
    const COMPLETED = 'completed';

    /** Enrolment states shown beside a course. */
    const ENROLMENT_ACTIVE = 'active';
    const ENROLMENT_SUSPENDED = 'suspended';
    const ENROLMENT_REMOVED = 'removed';

    /**
     * One course's status for one learner. Pure.
     *
     * A completion date wins over everything: a course finished months ago stays finished
     * even if its enrolment or its completion tracking has since gone (FR-004). Otherwise an
     * untracked course says so, no progress is "not started", and any progress short of
     * completion shows as 1-99%, never 100, which would read as finished.
     *
     * @param bool $tracked completion tracking is on for the course
     * @param int|null $timecompleted course_completions.timecompleted, null when not complete
     * @param float|null $percentage from learner_percentage()
     * @return array ['state' => string, 'percent' => int|null, 'timecompleted' => int|null]
     */
    public static function progress_status(bool $tracked, ?int $timecompleted, ?float $percentage): array {
        if ($timecompleted) {
            return ['state' => self::COMPLETED, 'percent' => null, 'timecompleted' => $timecompleted];
        }
        if (!$tracked) {
            return ['state' => self::NOT_TRACKED, 'percent' => null, 'timecompleted' => null];
        }
        if ($percentage === null || $percentage <= 0) {
            return ['state' => self::NOT_STARTED, 'percent' => null, 'timecompleted' => null];
        }
        $percent = (int)max(1, min(99, floor($percentage)));
        return ['state' => self::IN_PROGRESS, 'percent' => $percent, 'timecompleted' => null];
    }

    /**
     * Order a learner's courses: in progress, not started, not tracked, then completed with
     * the most recent first. Names break ties. Pure.
     *
     * @param array[] $rows each with 'fullname', 'state' and 'timecompleted'
     * @return array[] the same rows, reordered and reindexed
     */
    public static function sort_courses(array $rows): array {
        $rank = [self::IN_PROGRESS => 0, self::NOT_STARTED => 1, self::NOT_TRACKED => 2, self::COMPLETED => 3];
        usort($rows, function(array $a, array $b) use ($rank): int {
            $byrank = ($rank[$a['state']] ?? 9) <=> ($rank[$b['state']] ?? 9);
            if ($byrank !== 0) {
                return $byrank;
            }
            if ($a['state'] === self::COMPLETED) {
                $bydate = (int)$b['timecompleted'] <=> (int)$a['timecompleted'];
                if ($bydate !== 0) {
                    return $bydate;
                }
            }
            return strcasecmp((string)$a['fullname'], (string)$b['fullname']);
        });
        return array_values($rows);
    }

    /**
     * The mentor role's id, or 0 when it is not on this server yet.
     *
     * @return int
     */
    public static function role_id(): int {
        global $DB;
        static $id = null;
        if ($id === null) {
            $id = (int)$DB->get_field('role', 'id', ['shortname' => self::ROLE]);
        }
        return $id;
    }

    /**
     * Whether a user has any mentor or any learner. Cheap: two indexed existence reads,
     * cached for the request. Decides whether navigation offers the page at all.
     *
     * @param int $userid
     * @return bool
     */
    public static function has_relationship(int $userid): bool {
        global $DB;
        static $cache = [];
        $roleid = self::role_id();
        if (!$roleid || $userid <= 0 || isguestuser($userid)) {
            return false;
        }
        if (!array_key_exists($userid, $cache)) {
            $context = context_user::instance($userid, IGNORE_MISSING);
            $ismentee = $context && $DB->record_exists_select('role_assignments',
                'roleid = :roleid AND contextid = :contextid AND userid <> :self',
                ['roleid' => $roleid, 'contextid' => $context->id, 'self' => $userid]);
            [$sql, $params] = self::candidate_sql($userid);
            $cache[$userid] = $ismentee || $DB->record_exists_sql($sql, $params);
        }
        return $cache[$userid];
    }

    /**
     * Everything the page shows for one user.
     *
     * @param int $userid the viewer
     * @return array template context: learners, mentors, haslearners, hasmentors, empty
     */
    public static function for_user(int $userid): array {
        $learners = [];
        $mentors = [];
        if (self::role_id() && $userid > 0) {
            foreach (self::candidate_learner_ids($userid) as $learnerid) {
                $context = context_user::instance($learnerid, IGNORE_MISSING);
                if (!$context || !has_capability('local/ltuse:viewmenteeprogress', $context, $userid)) {
                    continue; // FR-005: the capability decides, not the query.
                }
                $learner = \core_user::get_user($learnerid);
                if (!$learner || $learner->deleted) {
                    continue;
                }
                $learners[] = self::learner($learner);
            }
            $mentors = self::mentors($userid);
        }
        usort($learners, [self::class, 'compare_people']);
        return [
            'learners' => $learners,
            'haslearners' => !empty($learners),
            'mentors' => $mentors,
            'hasmentors' => !empty($mentors),
            'empty' => !$learners && !$mentors,
        ];
    }

    /**
     * The users in whose user context this user holds the mentor role. Candidates only.
     *
     * @param int $userid
     * @return int[]
     */
    protected static function candidate_learner_ids(int $userid): array {
        global $DB;
        [$sql, $params] = self::candidate_sql($userid);
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * The candidate query: role_assignments by indexed userid, joined to context.
     *
     * @param int $userid
     * @return array [sql, params]
     */
    protected static function candidate_sql(int $userid): array {
        $sql = "SELECT DISTINCT ctx.instanceid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :userid
                   AND ra.roleid = :roleid
                   AND ctx.contextlevel = :level
                   AND ctx.instanceid <> :self";
        $params = ['userid' => $userid, 'roleid' => self::role_id(), 'level' => CONTEXT_USER,
            'self' => $userid];
        return [$sql, $params];
    }

    /**
     * One learner's entry: name, links and courses.
     *
     * @param \stdClass $learner
     * @return array
     */
    protected static function learner(\stdClass $learner): array {
        $courses = self::courses((int)$learner->id);
        return [
            'id' => (int)$learner->id,
            'fullname' => fullname($learner),
            'firstname' => (string)$learner->firstname,
            'lastname' => (string)$learner->lastname,
            'profileurl' => (new moodle_url('/user/profile.php', ['id' => $learner->id]))->out(false),
            'gradesurl' => (new moodle_url('/grade/report/overview/index.php',
                ['id' => SITEID, 'userid' => $learner->id]))->out(false),
            'messageurl' => (new moodle_url('/message/index.php', ['id' => $learner->id]))->out(false),
            // Spec 006 (US4): the learner's pathways, behind pathway\viewer::may_view().
            'pathwaysurl' => (new moodle_url('/local/ltuse/pathways.php', ['userid' => $learner->id]))->out(false),
            'courses' => $courses,
            'hascourses' => !empty($courses),
        ];
    }

    /**
     * A learner's courses with their status: every course they are enrolled in, active or
     * suspended, and every course they completed even if the enrolment is gone (FR-004).
     *
     * @param int $learnerid
     * @return array[]
     */
    protected static function courses(int $learnerid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $all = enrol_get_all_users_courses($learnerid, false, 'enablecompletion');
        $active = enrol_get_all_users_courses($learnerid, true, 'enablecompletion');
        $completed = $DB->get_records_select_menu('course_completions',
            'userid = :userid AND timecompleted IS NOT NULL', ['userid' => $learnerid], '',
            'course, timecompleted');

        $courses = [];
        foreach ($all as $course) {
            $courses[(int)$course->id] = $course;
        }
        foreach (array_keys($completed) as $courseid) {
            if (!isset($courses[(int)$courseid]) && (int)$courseid !== (int)SITEID &&
                    ($course = $DB->get_record('course', ['id' => $courseid]))) {
                $courses[(int)$courseid] = $course;
            }
        }

        $rows = [];
        foreach ($courses as $id => $course) {
            if ($id === (int)SITEID) {
                continue;
            }
            $info = new \completion_info($course);
            $tracked = (bool)$info->is_enabled();
            $percentage = ($tracked && !isset($completed[$id])) ? self::learner_percentage($course, $info, $learnerid) : null;
            $status = self::progress_status($tracked,
                isset($completed[$id]) ? (int)$completed[$id] : null,
                $percentage === null ? null : (float)$percentage);
            $enrolment = isset($active[$id]) ? self::ENROLMENT_ACTIVE
                : (isset($all[$id]) ? self::ENROLMENT_SUSPENDED : self::ENROLMENT_REMOVED);
            $rows[] = $status + [
                'id' => $id,
                'fullname' => format_string($course->fullname, true,
                    ['context' => \context_course::instance($id)]),
                'hidden' => empty($course->visible),
                'enrolment' => $enrolment,
                'notactive' => $enrolment !== self::ENROLMENT_ACTIVE,
                'enrolmenttext' => $enrolment === self::ENROLMENT_ACTIVE ? ''
                    : get_string('mentoring:enrolment' . $enrolment, 'local_ltuse'),
                'statustext' => self::status_text($status),
                $status['state'] => true,
            ];
        }
        return self::sort_courses($rows);
    }

    /**
     * The share of a course's activities the learner has completed, as the learner sees the
     * course. Core's \core_completion\progress::get_course_progress_percentage() cannot be
     * used here: it builds the course's activity list for the current user, the mentor, who
     * has no role in the course, so quizzes and assignments (whose view capability only
     * enrolled roles hold) and anything hidden or restricted drop out of the count. This is
     * core's own calculation (completion/classes/progress.php and
     * completion_info::get_user_activities_with_completion()), on the learner's modinfo. It
     * also skips core's is_tracked_user() check, which counts active enrolments only, so a
     * learner whose enrolment is suspended keeps the progress they made.
     *
     * @param \stdClass $course
     * @param \completion_info $info for that course
     * @param int $learnerid
     * @return float|null 0-100, or null when no activity the learner can see tracks completion
     */
    protected static function learner_percentage(\stdClass $course, \completion_info $info, int $learnerid): ?float {
        $modinfo = get_fast_modinfo($course, $learnerid);
        $ids = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->completion == COMPLETION_TRACKING_NONE || $cm->deletioninprogress ||
                    !$cm->is_visible_on_course_page()) {
                continue;
            }
            $availability = new \core_availability\info_module($cm);
            if (!$availability->filter_user_list([$learnerid => (object)['id' => $learnerid]])) {
                continue; // A group or grouping restriction excludes this learner.
            }
            $ids[] = (int)$cm->id;
        }
        if (!$ids) {
            return null;
        }
        return $info->count_modules_completed($learnerid, $ids) / count($ids) * 100;
    }

    /**
     * The words for one status. Course completion only: never a CBC level (FR-013).
     *
     * @param array $status from progress_status()
     * @return string
     */
    protected static function status_text(array $status): string {
        switch ($status['state']) {
            case self::COMPLETED:
                return get_string('mentoring:completed', 'local_ltuse',
                    userdate($status['timecompleted'], get_string('strftimedate', 'langconfig')));
            case self::IN_PROGRESS:
                return get_string('mentoring:inprogress', 'local_ltuse', $status['percent']);
            case self::NOT_STARTED:
                return get_string('mentoring:notstarted', 'local_ltuse');
            default:
                return get_string('mentoring:nottracked', 'local_ltuse');
        }
    }

    /**
     * The people who mentor this user (FR-010).
     *
     * @param int $userid
     * @return array[]
     */
    protected static function mentors(int $userid): array {
        $context = context_user::instance($userid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }
        $out = [];
        foreach (get_role_users(self::role_id(), $context) as $mentor) {
            if ((int)$mentor->id === $userid) {
                continue;
            }
            $out[(int)$mentor->id] = [
                'id' => (int)$mentor->id,
                'fullname' => fullname($mentor),
                'firstname' => (string)$mentor->firstname,
                'lastname' => (string)$mentor->lastname,
                'messageurl' => (new moodle_url('/message/index.php', ['id' => $mentor->id]))->out(false),
            ];
        }
        $out = array_values($out);
        usort($out, [self::class, 'compare_people']);
        return $out;
    }

    /**
     * Last name, then first name, ignoring case.
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    protected static function compare_people(array $a, array $b): int {
        return strcasecmp($a['lastname'], $b['lastname']) ?: strcasecmp($a['firstname'], $b['firstname']);
    }
}
