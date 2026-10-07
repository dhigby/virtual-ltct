<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\admin\course_mentor_sync;

/**
 * Subscribes a course mentor to each discussion their mentees start or post in, in delivery
 * (ltct:<slug>) courses, without subscribing them to the whole forum (spec 005, research R10;
 * Q10 as changed in round 2 to "start or post in").
 *
 *   for_post             one post: each course mentor of its author in that course is
 *                        subscribed to the post's discussion. Called by the observer on
 *                        \mod_forum\event\post_created
 *   for_discussion       for_post() on a new discussion's first post; the observer on
 *                        \mod_forum\event\discussion_created
 *   existing_for_mentor  when sync adds a mentor, or the mentor gains a mentee: every existing
 *                        discussion in the course their mentees started or posted in
 *
 * A mentor of the author is read from course-mentor sync's own state: the mentor groups
 * (ltct:mentorgroup:<mentor id>) that hold the author as a local_ltuse member, whose mentor still
 * holds the Teacher role local_ltuse gave in that course. Sync keeps those groups to exactly the
 * learners each mentor assesses there (spec 008), from its records and the default mentors.
 *
 * Race fix (R10): subscribe_user_to_discussion() stores preference = time(), and the forum cron
 * drops any post created before that time (mod/forum/classes/task/cron_task.php L465-471). A
 * discussion's first post gets its `created` before its attachments are saved and before the
 * event fires, so the opening post would often be lost. So where the stored preference is later
 * than the first post's `created`, it is set to that `created` by a direct write to
 * forum_discussion_subs, then the subscriptions cache is reset (research R18).
 *
 * A mentor who has opted out of one discussion of a forum they are subscribed to (core's
 * preference -1 row) is left opted out: their own choice, like a person's own digest setting
 * (round 2). Core keeps no such row for someone not subscribed to the forum: unsubscribing
 * deletes their discussion row, so a later post by their mentee subscribes them again. A mentor
 * already subscribed to the discussion or to the whole forum gets no new row.
 *
 * Never in a space: any course whose idnumber starts ltct:site: returns at once (teaching spaces
 * subscribe the mentor to whole forums by Auto; Area spaces follow each person's own setting).
 * Nothing is done while course-mentor sync is switched off.
 *
 * API: \mod_forum\subscriptions::subscribe_user_to_discussion() and reset_discussion_cache()
 * (mod/forum/classes/subscriptions.php L741-791, L589). Raw reads, listed in README.md
 * (constitution XI): forum_posts and forum_discussions by id, discussion and author, and a
 * discussion's firstpost by id; course by id; groups and groups_members by course, idnumber
 * prefix, user and component; role_assignments by context, role, user and component;
 * forum_discussion_subs by user and discussion. The one
 * direct write is forum_discussion_subs.preference. Nothing here logs or returns a person.
 */
class mentor_subscriptions {

    /** The idnumber prefix of every space course, where this never runs. */
    const SPACE_PREFIX = 'ltct:site:';

    /** Ids per IN (...) list. */
    const CHUNK = 500;

    /**
     * Subscribe each course mentor of a post's author to the post's discussion.
     *
     * @param int $postid
     * @return int how many mentors were newly subscribed
     */
    public static function for_post(int $postid): int {
        global $DB;
        if (!course_mentor_sync::enabled()) {
            return 0;
        }
        $post = $DB->get_record('forum_posts', ['id' => $postid], 'id, discussion, userid');
        if (!$post) {
            return 0;
        }
        $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion]);
        if (!$discussion || !self::is_delivery_course((int)$discussion->course)) {
            return 0;
        }
        $subscribed = 0;
        foreach (self::mentors_of((int)$post->userid, (int)$discussion->course) as $mentorid) {
            if (self::subscribe($mentorid, $discussion)) {
                $subscribed++;
            }
        }
        return $subscribed;
    }

    /**
     * Subscribe each course mentor of a new discussion's author to it: for_post() on its first
     * post, read from forum_discussions by id.
     *
     * @param int $discussionid
     * @return int how many mentors were newly subscribed
     */
    public static function for_discussion(int $discussionid): int {
        global $DB;
        $postid = (int)$DB->get_field('forum_discussions', 'firstpost', ['id' => $discussionid]);
        return $postid ? self::for_post($postid) : 0;
    }

    /**
     * Subscribe a mentor to every discussion in the course their mentees started or posted in.
     *
     * @param int $mentorid
     * @param int $courseid
     * @return int how many discussions they were newly subscribed to
     */
    public static function existing_for_mentor(int $mentorid, int $courseid): int {
        global $DB;
        if (!course_mentor_sync::enabled() || !self::is_delivery_course($courseid)) {
            return 0;
        }
        $mentees = array_values(array_diff(self::group_members($mentorid, $courseid), [$mentorid]));
        if (!$mentees) {
            return 0;
        }
        $discussionids = [];
        foreach (array_chunk($mentees, self::CHUNK) as $chunk) {
            [$in, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $params['courseid'] = $courseid;
            $sql = "SELECT DISTINCT d.id
                      FROM {forum_discussions} d
                      JOIN {forum_posts} p ON p.discussion = d.id
                     WHERE d.course = :courseid AND p.userid $in";
            foreach ($DB->get_fieldset_sql($sql, $params) as $id) {
                $discussionids[(int)$id] = (int)$id;
            }
        }
        $subscribed = 0;
        foreach ($discussionids as $id) {
            $discussion = $DB->get_record('forum_discussions', ['id' => $id]);
            if ($discussion && self::subscribe($mentorid, $discussion)) {
                $subscribed++;
            }
        }
        return $subscribed;
    }

    /**
     * Whether a course is one this runs in: ltct:<slug>, never a space, never office hours.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_delivery_course(int $courseid): bool {
        global $DB;
        $idnumber = (string)$DB->get_field('course', 'idnumber', ['id' => $courseid]);
        if (strpos($idnumber, self::SPACE_PREFIX) === 0) {
            return false;
        }
        return course_mentor_sync::is_ltct_course($idnumber);
    }

    /**
     * The course mentors of a learner in one course, from sync's groups and Teacher assignments.
     *
     * @param int $userid
     * @param int $courseid
     * @return int[] mentor user ids, never the learner themselves
     */
    protected static function mentors_of(int $userid, int $courseid): array {
        global $DB;
        $sql = "SELECT DISTINCT g.idnumber
                  FROM {groups_members} gm
                  JOIN {groups} g ON g.id = gm.groupid
                 WHERE g.courseid = :courseid AND gm.userid = :userid AND gm.component = :component
                       AND " . $DB->sql_like('g.idnumber', ':prefix');
        $idnumbers = $DB->get_fieldset_sql($sql, ['courseid' => $courseid, 'userid' => $userid,
            'component' => course_mentor_sync::COMPONENT,
            'prefix' => $DB->sql_like_escape(course_mentor_sync::GROUP_PREFIX) . '%']);
        $teacherid = (int)(course_mentor_sync::role_ids()[course_mentor_sync::ROLE_TEACHER] ?? 0);
        if (!$teacherid) {
            return [];
        }
        $contextid = \context_course::instance($courseid)->id;
        $mentors = [];
        foreach ($idnumbers as $idnumber) {
            $id = substr((string)$idnumber, strlen(course_mentor_sync::GROUP_PREFIX));
            if (!ctype_digit($id) || (int)$id === $userid) {
                continue;
            }
            if ($DB->record_exists('role_assignments', ['contextid' => $contextid, 'roleid' => $teacherid,
                    'userid' => (int)$id, 'component' => course_mentor_sync::COMPONENT])) {
                $mentors[] = (int)$id;
            }
        }
        sort($mentors);
        return $mentors;
    }

    /**
     * The local_ltuse members of a mentor's group in one course: the mentor and their mentees.
     *
     * @param int $mentorid
     * @param int $courseid
     * @return int[]
     */
    protected static function group_members(int $mentorid, int $courseid): array {
        global $DB;
        $sql = "SELECT DISTINCT gm.userid
                  FROM {groups_members} gm
                  JOIN {groups} g ON g.id = gm.groupid
                 WHERE g.courseid = :courseid AND g.idnumber = :idnumber AND gm.component = :component";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['courseid' => $courseid,
            'idnumber' => course_mentor_sync::GROUP_PREFIX . $mentorid, 'component' => course_mentor_sync::COMPONENT]));
    }

    /**
     * Subscribe one mentor to one discussion, then apply the race fix.
     *
     * @param int $mentorid
     * @param \stdClass $discussion a forum_discussions record
     * @return bool a new subscription was made
     */
    protected static function subscribe(int $mentorid, \stdClass $discussion): bool {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $existing = $DB->get_record('forum_discussion_subs', ['userid' => $mentorid, 'discussion' => $discussion->id]);
        if ($existing && (int)$existing->preference === \mod_forum\subscriptions::FORUM_DISCUSSION_UNSUBSCRIBED) {
            return false;   // The mentor's own choice.
        }
        $cm = get_coursemodule_from_instance('forum', $discussion->forum, $discussion->course, false, MUST_EXIST);
        $made = \mod_forum\subscriptions::subscribe_user_to_discussion($mentorid, $discussion,
            \context_module::instance($cm->id));

        $row = $DB->get_record('forum_discussion_subs', ['userid' => $mentorid, 'discussion' => $discussion->id]);
        $created = $DB->get_field('forum_posts', 'created', ['id' => $discussion->firstpost]);
        if ($row && $created !== false && (int)$row->preference > (int)$created) {
            $DB->set_field('forum_discussion_subs', 'preference', (int)$created, ['id' => $row->id]);
            \mod_forum\subscriptions::reset_discussion_cache();
        }
        return $made;
    }
}
