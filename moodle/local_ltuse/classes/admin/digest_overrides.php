<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * The one place that writes and removes the per-forum digest overrides course-mentor sync sets
 * (spec 005, research R4, round 2; data-model "Mail state").
 *
 * An override is a forum_digests row, written through core's forum_set_user_maildigest() with
 * the person's full record (it reads $user->maildigest; research R18). Every override this
 * class writes is recorded in local_ltuse_digest_override, so removal undoes only what sync
 * set and never a personal choice:
 *
 *   set     writes only when the person has no forum_digests row for that forum AND no record
 *           for it. A forum_digests row with no record is the person's own choice, kept and
 *           never recorded. A record with no forum_digests row means the person set the forum
 *           back to their default: the record is marked released and that override is never
 *           written again (deleting the record would let the next run write it again)
 *   remove  sets -1 (which deletes the forum_digests row) only where an unreleased record
 *           exists and the current value still equals the recorded value, then deletes the
 *           record. Otherwise the person has changed it, so their setting stays and only the
 *           record goes
 *
 * forum_set_user_maildigest() requires mod/forum:viewdiscussion in the forum's context. Where
 * core has already taken the role away (cohort sync with unenrolaction 3), both methods return
 * skipped_nocap before touching anything: the override and the record stay, nothing is thrown
 * and nothing is queued for retry.
 *
 * set_for_course() is set() for many people and forums of one course at once, for sync: the
 * records and forum_digests rows are read once per chunk, and the capability is checked and core
 * called only for a pair that needs a write or a release. Its outcomes are set()'s.
 *
 * Core also deletes forum_digests rows itself: on a person's last unenrolment from a course,
 * mod_forum_observer::user_enrolment_deleted deletes every row of theirs for the course's forums
 * (mod/forum/classes/observer.php L37-54). A record left behind would then read as "set back to
 * their default" and stop sync ever writing the override again, so forget_course() deletes the
 * person's records for the same forums on the same event. forget_user() does it on account
 * deletion, and remove_orphans() catches records whose forum or person is gone.
 *
 * Raw reads, listed in README.md (constitution XI): forum_digests by userid and forum (core
 * has no getter for one person's per-forum row that tells "no row" from "default"), forum ids by
 * course (the set core clears), and this plugin's own table. Nothing here returns or logs a
 * person.
 */
final class digest_overrides {

    /** The record of the overrides sync wrote. */
    const TABLE = 'local_ltuse_digest_override';

    /** Ids per IN (...) list, as course_mentor_sync. */
    const CHUNK = 500;

    /**
     * Give a person the override sync wants on one forum, unless they have chosen their own.
     *
     * @param int $userid
     * @param int $forumid
     * @param int $value 0 (each post by email) or 1 (complete digest)
     * @return string written | kept_personal | released | recorded | skipped_nocap
     */
    public static function set(int $userid, int $forumid, int $value): string {
        global $DB;
        if (!self::can_view($userid, $forumid)) {
            return 'skipped_nocap';
        }
        $record = $DB->get_record(self::TABLE, ['userid' => $userid, 'forumid' => $forumid]);
        $digest = self::current($userid, $forumid);
        if ($record) {
            if (!(int)$record->released && $digest === null) {
                // The person set the forum back to their default: never write it again.
                $DB->set_field(self::TABLE, 'released', 1, ['id' => $record->id]);
                return 'released';
            }
            return (int)$record->released ? 'released' : 'recorded';
        }
        if ($digest !== null) {
            return 'kept_personal';
        }
        self::write($userid, $forumid, $value);
        $DB->insert_record(self::TABLE, (object)['userid' => $userid, 'forumid' => $forumid,
            'value' => $value, 'released' => 0, 'timecreated' => time()]);
        return 'written';
    }

    /**
     * set() for every pair of these people and these forums of one course, reading the records
     * and the forum_digests rows once per chunk instead of once per pair.
     *
     * @param int[] $userids
     * @param int[] $forums forum id => course module id, from the course's modinfo
     * @param int $value 0 (each post by email) or 1 (complete digest)
     * @return int how many overrides were written
     */
    public static function set_for_course(array $userids, array $forums, int $value): int {
        global $DB;
        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (!$userids || !$forums) {
            return 0;
        }
        [$fin, $fparams] = $DB->get_in_or_equal(array_keys($forums), SQL_PARAMS_NAMED, 'ldof');
        $written = 0;
        foreach (array_chunk($userids, self::CHUNK) as $chunk) {
            [$uin, $uparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'ldou');
            $params = $fparams + $uparams;
            $records = [];
            foreach ($DB->get_records_select(self::TABLE, "forumid $fin AND userid $uin", $params, '',
                    'id, userid, forumid, released') as $r) {
                $records[(int)$r->userid . ':' . (int)$r->forumid] = $r;
            }
            $digests = [];
            foreach ($DB->get_records_select('forum_digests', "forum $fin AND userid $uin", $params, '',
                    'id, userid, forum') as $d) {
                $digests[(int)$d->userid . ':' . (int)$d->forum] = true;
            }
            foreach ($chunk as $userid) {
                foreach ($forums as $forumid => $cmid) {
                    $key = $userid . ':' . (int)$forumid;
                    $record = $records[$key] ?? null;
                    $hasrow = isset($digests[$key]);
                    if ($hasrow || ($record && (int)$record->released)) {
                        continue;   // released, recorded or kept_personal: nothing to do.
                    }
                    if (!self::can_view_cm($userid, (int)$cmid)) {
                        continue;   // skipped_nocap.
                    }
                    if ($record) {
                        // The person set the forum back to their default: never write it again.
                        $DB->set_field(self::TABLE, 'released', 1, ['id' => $record->id]);
                        continue;
                    }
                    self::write($userid, (int)$forumid, $value);
                    $DB->insert_record(self::TABLE, (object)['userid' => $userid, 'forumid' => (int)$forumid,
                        'value' => $value, 'released' => 0, 'timecreated' => time()]);
                    $written++;
                }
            }
        }
        return $written;
    }

    /**
     * Take away the override sync wrote on one forum, and only that.
     *
     * @param int $userid
     * @param int $forumid
     * @return string removed | kept_personal | none | skipped_nocap
     */
    public static function remove(int $userid, int $forumid): string {
        global $DB;
        if (!self::can_view($userid, $forumid)) {
            return 'skipped_nocap';
        }
        $record = $DB->get_record(self::TABLE, ['userid' => $userid, 'forumid' => $forumid]);
        if (!$record) {
            return 'none';
        }
        $digest = self::current($userid, $forumid);
        $result = 'kept_personal';
        if (!(int)$record->released && $digest !== null && $digest === (int)$record->value) {
            self::write($userid, $forumid, -1);
            $result = 'removed';
        }
        $DB->delete_records(self::TABLE, ['id' => $record->id]);
        return $result;
    }

    /**
     * A person's last enrolment in a course was deleted, so core has deleted their forum_digests
     * rows for its forums: delete this plugin's records for the same forums, so the next sync
     * after they are enrolled again writes the override afresh. Read from {forum} by course, the
     * set core clears.
     *
     * @param int $userid
     * @param int $courseid
     * @return int how many records were deleted
     */
    public static function forget_course(int $userid, int $courseid): int {
        global $DB;
        $forumids = $DB->get_fieldset_select('forum', 'id', 'course = :course', ['course' => $courseid]);
        if (!$forumids || $userid <= 0) {
            return 0;
        }
        [$in, $params] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'ldoc');
        $params['userid'] = $userid;
        $select = "userid = :userid AND forumid $in";
        $count = $DB->count_records_select(self::TABLE, $select, $params);
        if ($count) {
            $DB->delete_records_select(self::TABLE, $select, $params);
        }
        return $count;
    }

    /**
     * An account was deleted: delete every record of theirs.
     *
     * @param int $userid
     */
    public static function forget_user(int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Delete records whose forum is gone, or whose person is deleted or gone. For the hourly
     * reconcile; forget_user() and forget_course() remove most at once.
     *
     * @return int how many were deleted
     */
    public static function remove_orphans(): int {
        global $DB;
        $sql = "SELECT r.id
                  FROM {" . self::TABLE . "} r
             LEFT JOIN {forum} f ON f.id = r.forumid
             LEFT JOIN {user} u ON u.id = r.userid AND u.deleted = 0
                 WHERE f.id IS NULL OR u.id IS NULL";
        $ids = $DB->get_fieldset_sql($sql);
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $DB->delete_records_list(self::TABLE, 'id', $chunk);
        }
        return count($ids);
    }

    /**
     * Whether core will let the person's digest be set on this forum.
     *
     * @param int $userid
     * @param int $forumid
     * @return bool
     */
    private static function can_view(int $userid, int $forumid): bool {
        $cm = get_coursemodule_from_instance('forum', $forumid, 0, false, MUST_EXIST);
        return self::can_view_cm($userid, (int)$cm->id);
    }

    /**
     * can_view() for a course module already known.
     *
     * @param int $userid
     * @param int $cmid
     * @return bool
     */
    private static function can_view_cm(int $userid, int $cmid): bool {
        return has_capability('mod/forum:viewdiscussion', \context_module::instance($cmid), $userid);
    }

    /**
     * The person's own forum_digests value for the forum, or null when they have no row.
     *
     * @param int $userid
     * @param int $forumid
     * @return int|null
     */
    private static function current(int $userid, int $forumid): ?int {
        global $DB;
        $value = $DB->get_field('forum_digests', 'maildigest', ['userid' => $userid, 'forum' => $forumid]);
        return ($value === false) ? null : (int)$value;
    }

    /**
     * Write through core, as the person.
     *
     * @param int $userid
     * @param int $forumid
     * @param int $value -1 deletes the person's row
     */
    private static function write(int $userid, int $forumid, int $value): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        forum_set_user_maildigest($forumid, $value, \core_user::get_user($userid, '*', MUST_EXIST));
    }
}
