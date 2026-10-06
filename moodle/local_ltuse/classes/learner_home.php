<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

use context_course;
use context_helper;
use context_module;
use context_system;
use local_ltuse\pathway\assignments;
use local_ltuse\pathway\view;
use moodle_url;

/**
 * What the learner home block shows one user (spec 007, data-model §2).
 *
 * One class for both views: block_ltuse's get_content() on the web and its mobile_block_view
 * in the app read the same state(), so they cannot differ.
 *
 *   mode     empty (no published course), continue, start, or done (every course complete)
 *   course   the course to continue or start, or null
 *   cm       the lesson to open in it, or null
 *   onward   the routes beneath any mode: pathways, mentors and the community space, or null
 *
 * It reads learner data inside Moodle only, to render the block for the user who is viewing,
 * and never stores it (Principle III). Nothing here writes.
 *
 * Which courses count is learner_home_rules::is_published_course(), the same rule the
 * next-lesson hook uses.
 */
final class learner_home {

    /** The modes, one at a time (data-model §2). */
    const MODE_EMPTY = 'empty';
    const MODE_CONTINUE = 'continue';
    const MODE_START = 'start';
    const MODE_DONE = 'done';

    /**
     * The user's published courses: active enrolments in courses they may see.
     *
     * enrol_get_all_users_courses()'s $onlyactive keeps only active enrolments in enabled
     * instances within their dates. It does not drop hidden courses, so they are dropped here,
     * as enrol_get_users_courses() does, unless the user may see hidden courses in that one.
     * The publisher creates a course hidden, so a pilot course not yet opened is never offered.
     *
     * @param int $userid
     * @return \stdClass[] course records by id, with idnumber, enablecompletion and visible
     */
    public static function published_courses(int $userid): array {
        $courses = enrol_get_all_users_courses($userid, true, 'idnumber, enablecompletion, visible');
        foreach ($courses as $id => $course) {
            if (!learner_home_rules::is_published_course((string)$course->idnumber)) {
                unset($courses[$id]);
                continue;
            }
            if (!$course->visible) {
                context_helper::preload_from_record($course);
                if (!has_capability('moodle/course:viewhiddencourses', context_course::instance($id), $userid)) {
                    unset($courses[$id]);
                }
            }
        }
        return $courses;
    }

    /**
     * What the block shows this user (data-model §2).
     *
     * The continue choice is learner_home_rules::choose() over the published courses with
     * completion on (R3): the most recently opened course that is not complete, and in it the
     * first lesson the learner can open and has not completed. mode() says which of the four
     * modes that is. A published course with completion off counts towards "has a course" but
     * is never chosen, so a learner whose only course has completion off is done.
     *
     * Beneath any mode, onward() gives the routes under "Where next", or null when there are
     * none (FR-008).
     *
     * @param int $userid
     * @return array ['mode' => string, 'course' => ?array, 'cm' => ?array, 'onward' => ?array]
     *     course is {id, fullname, url} and cm is {id, name, url}; onward is
     *     learner_home_rules::onward(); names are format_string()ed and urls are strings
     */
    public static function state(int $userid): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $published = self::published_courses($userid);
        $candidates = [];
        if ($published) {
            $lastaccess = self::last_access($userid);
            $enroltimes = self::enrol_times($userid);
            foreach ($published as $id => $course) {
                $completion = new \completion_info($course);
                if (!$completion->is_enabled()) {
                    continue;
                }
                $candidates[] = [
                    'id' => (int)$id,
                    'lastaccess' => $lastaccess[$id] ?? 0,
                    'enroltime' => $enroltimes[$id] ?? 0,
                    'complete' => (bool)$completion->is_course_complete($userid),
                ];
            }
        }
        $chosen = learner_home_rules::choose($candidates, function(int $courseid) use ($published, $userid): array {
            return self::cms($published[$courseid], $userid);
        });

        $course = null;
        $cm = null;
        if ($chosen !== null) {
            $id = $chosen['course_id'];
            $course = [
                'id' => $id,
                'fullname' => format_string($published[$id]->fullname, true, ['context' => context_course::instance($id)]),
                'url' => (new moodle_url('/course/view.php', ['id' => $id]))->out(false),
            ];
            $cm = [
                'id' => $chosen['cm']['id'],
                'name' => format_string($chosen['cm']['name'], true, ['context' => context_module::instance($chosen['cm']['id'])]),
                'url' => $chosen['cm']['url'],
            ];
        }
        return [
            'mode' => learner_home_rules::mode(count($published), $chosen),
            'course' => $course,
            'cm' => $cm,
            'onward' => self::onward($userid),
        ];
    }

    /**
     * The routes beneath any mode (FR-008, contracts/learner-ui.md), through
     * learner_home_rules::onward(), so each is absent when its source has nothing.
     *
     *   pathways   the next course of each pathway the learner holds (spec 006), only when the
     *              level labels are applied: pathway\view::levels() is null until they are, and
     *              summaries() then throws
     *   mentors    a message route to each of the learner's own mentors (spec 003). These are
     *              the only other users the block may name
     *   community  the community space (spec 005)
     *
     * @param int $userid
     * @return array|null learner_home_rules::onward()
     */
    private static function onward(int $userid): ?array {
        $pathways = [];
        if (view::levels() !== null) {
            try {
                foreach (view::summaries($userid, assignments::pathways_for_user($userid)) as $summary) {
                    if (empty($summary['nextcourse'])) {
                        continue;
                    }
                    $pathways[] = [
                        'title' => $summary['title'],
                        'nextcourse' => [
                            'fullname' => $summary['nextcourse']['fullname'],
                            'url' => $summary['nextcourse']['url'],
                        ],
                    ];
                }
            } catch (\moodle_exception $e) {
                // Absent: a pathway the page cannot show is no route (FR-008).
                $pathways = [];
            }
        }

        $mentors = [];
        // With no mentor role on the server, get_role_users() would be asked for role 0: every role.
        if (mentoring::role_id()) {
            foreach (mentoring::mentors($userid) as $mentor) {
                $mentors[] = ['fullname' => $mentor['fullname'], 'url' => $mentor['messageurl']];
            }
        }

        // Spec 005 declares the community space; until then this route is absent (FR-008).
        $community = null;

        return learner_home_rules::onward($pathways, $mentors, $community);
    }

    /**
     * One course's cms, in course order, as learner_home_rules::first_incomplete() reads them.
     *
     * uservisible and the url are core's own, computed for $userid by get_fast_modinfo(); a cm
     * in a hidden section (the Retired section) is stealth. Complete is core's: complete, or
     * complete with a pass. A failed quiz is not complete, so it is offered again.
     *
     * @param \stdClass $course
     * @param int $userid
     * @return array [{id, name, url, uservisible, stealth, hasurl, tracked, complete}]
     */
    private static function cms(\stdClass $course, int $userid): array {
        $completion = new \completion_info($course);
        $cms = [];
        foreach (get_fast_modinfo($course, $userid)->get_cms() as $cm) {
            $tracked = $completion->is_enabled($cm) != COMPLETION_TRACKING_NONE;
            $complete = false;
            if ($tracked) {
                // The whole course is read into the completion cache on the first call.
                $state = (int)$completion->get_data($cm, true, $userid)->completionstate;
                $complete = in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
            }
            $url = $cm->url;
            $cms[] = [
                'id' => (int)$cm->id,
                'name' => $cm->name,
                'url' => $url ? $url->out(false) : null,
                'uservisible' => (bool)$cm->uservisible,
                'stealth' => $cm->is_stealth(),
                'hasurl' => $url !== null,
                'tracked' => $tracked,
                'complete' => $complete,
            ];
        }
        return $cms;
    }

    /**
     * When the user last opened each course: core's user_lastaccess, which
     * course_get_recent_courses() also reads. Read here by its userid index rather than through
     * that function, because it keeps visible courses only and published_courses() also keeps
     * a hidden one the user may see.
     *
     * @param int $userid
     * @return int[] timeaccess by course id; a course never opened is absent
     */
    private static function last_access(int $userid): array {
        global $DB;
        return array_map('intval', $DB->get_records_menu('user_lastaccess', ['userid' => $userid], '',
            'courseid, timeaccess'));
    }

    /**
     * When the user was enrolled in each course: over their active enrolments in enabled
     * instances, as enrol_get_all_users_courses($userid, true) counts them, the latest of the
     * enrolment's start, or its creation when it has no start.
     *
     * @param int $userid
     * @return int[] enrolment time by course id
     */
    private static function enrol_times(int $userid): array {
        global $DB;
        $now = \core\di::get(\core\clock::class)->time();
        $sql = "SELECT e.courseid, MAX(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END) AS enroltime
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid AND ue.status = :active AND e.status = :enabled
                       AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)
              GROUP BY e.courseid";
        return array_map('intval', $DB->get_records_sql_menu($sql, ['userid' => $userid,
            'active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED, 'now1' => $now, 'now2' => $now]));
    }

    /**
     * Whether the block shows anything to this user. The site team's dashboard is not a
     * learner's, so a user who can configure the site sees nothing (R12).
     *
     * @param int $userid
     * @return bool
     */
    public static function applies(int $userid): bool {
        return !has_capability('moodle/site:config', context_system::instance(), $userid);
    }
}
