<?php
namespace local_ltuse\organisation;

defined('MOODLE_INTERNAL') || die();

use context_system;
use moodle_url;

/**
 * What the "my organisation" page shows, and the facts every organisation action decides on
 * (spec 002 amendment 2026-10-02, research R10; data-model "Organisation access decision").
 *
 *   for_manager(V)  each organisation V manages, with its people: every member of its
 *                   ltct:org:<key> cohort that access::is_org_member_of_manager() accepts,
 *                   with their email (protected people's too, Doug 2026-10-02), their courses
 *                   and course completion, and whether access::may_manage_account() holds
 *   facts(P)        the facts access.php decides on, gathered through public APIs; actions.php
 *                   calls it again on every write, so a decision is never read from the page
 *
 * The decisions are access.php's, never re-derived here. Nothing here writes.
 *
 * Reads: the managers and members cohorts by idnumber, as lib.php does (cohort.idnumber is not
 * indexed in core; the plugin README lists it), cohort_members by cohortid, the course and
 * course_categories rows of the courses a manager may enrol into (by idnumber prefix, also
 * listed), and user_enrolments joined to enrol for one person's organisation-enrolment rows.
 * Course progress is mentoring::courses(), the same \completion_info reading the Mentoring
 * page uses.
 */
class people {

    /** Prefix of every organisation cohort idnumber: ltct:org:<key> and ltct:org:<key>:managers. */
    const ORG_COHORT_PREFIX = 'ltct:org:';

    /**
     * The facts about one person that access.php decides on (its class comment lists them).
     *
     * Gathered by local_ltuse_organisation_person_facts() in lib.php (spec 003's mentors page
     * uses the same one), so the two pages cannot disagree. With $reload, the cohort reads it
     * makes through lib.php's request cache are refreshed first.
     *
     * @param int $userid P
     * @param bool $reload read the cohorts afresh, not from this request's cache: every write
     *     does, so a decision is made on the database as it is now
     * @return array facts; an unknown user comes back deleted, so every decision fails closed
     */
    public static function facts(int $userid, bool $reload = false): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/ltuse/lib.php');

        $user = \core_user::get_user($userid, 'id, deleted');
        if (!$user) {
            return ['id' => $userid, 'ltct_org' => '', 'org_cohorts' => [], 'deleted' => true];
        }
        if ($reload) {
            local_ltuse_organisation_member_keys($userid, true);
            local_ltuse_managed_organisation_keys($userid, true);
        }
        return local_ltuse_organisation_person_facts($user);
    }

    /**
     * Everything the organisation page lists for one manager.
     *
     * @param int $managerid V
     * @return array template context: organisations [{key, name, people, haspeople}], empty
     */
    public static function for_manager(int $managerid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/ltuse/lib.php');
        $managedkeys = local_ltuse_managed_organisation_keys($managerid);
        $courses = self::enrollable_courses();
        $organisations = [];
        foreach ($managedkeys as $key) {
            $cohort = $DB->get_record('cohort', ['idnumber' => self::ORG_COHORT_PREFIX . $key,
                'contextid' => context_system::instance()->id], 'id, name');
            if (!$cohort) {
                continue; // Declared but not applied yet.
            }
            $rows = [];
            foreach ($DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = :cohortid',
                    ['cohortid' => $cohort->id]) as $userid) {
                $facts = self::facts((int)$userid);
                // The field and the cohort must agree; a person who has moved is the new
                // organisation's, and a manager never lists themselves.
                if (!access::is_org_member_of_manager($managerid, $managedkeys, $facts) || $facts['ltct_org'] !== $key) {
                    continue;
                }
                $rows[] = self::person($facts, access::may_manage_account($managerid, $managedkeys, $facts), $courses);
            }
            usort($rows, function(array $a, array $b): int {
                return strcasecmp($a['lastname'], $b['lastname']) ?: strcasecmp($a['firstname'], $b['firstname']);
            });
            $organisations[] = [
                'key' => $key,
                'name' => format_string($cohort->name, true, ['context' => context_system::instance()]),
                'people' => $rows,
                'haspeople' => !empty($rows),
            ];
        }
        return ['organisations' => $organisations, 'empty' => !$organisations];
    }

    /**
     * One person's row.
     *
     * @param array $facts from facts()
     * @param bool $canmanage access::may_manage_account() for the viewer
     * @param array[] $courses from enrollable_courses()
     * @return array
     */
    protected static function person(array $facts, bool $canmanage, array $courses): array {
        $user = \core_user::get_user($facts['id']);
        $enrolled = enrol_get_all_users_courses($facts['id'], true, 'id');
        $orgenrolled = self::org_enrolled_course_ids($facts['id']);

        $rows = [];
        foreach (\local_ltuse\mentoring::courses($facts['id']) as $row) {
            $row['canunenrol'] = $canmanage && isset($orgenrolled[(int)$row['id']]);
            $rows[] = $row;
        }

        // Courses this person may be enrolled in and is not actively enrolled in already.
        $choices = [];
        if ($canmanage) {
            foreach ($courses as $course) {
                if (!isset($enrolled[$course['id']])
                        && access::may_enrol_into($facts, $course['idnumber'], $course['categoryidnumber'])) {
                    $choices[] = ['id' => $course['id'], 'fullname' => $course['fullname']];
                }
            }
        }

        return [
            'id' => (int)$user->id,
            'fullname' => fullname($user),
            'firstname' => (string)$user->firstname,
            'lastname' => (string)$user->lastname,
            'email' => (string)$user->email,
            'suspended' => !empty($user->suspended),
            'canmanage' => $canmanage,
            'profileurl' => (new moodle_url('/user/profile.php', ['id' => $user->id]))->out(false),
            'mentorsurl' => (new moodle_url('/local/ltuse/mentors.php', ['userid' => $user->id]))->out(false),
            'courses' => $rows,
            'hascourses' => !empty($rows),
            'enrolchoices' => $choices,
            'canenrol' => !empty($choices),
        ];
    }

    /**
     * Every course a manager could enrol anyone into, before the per-person rule: an ltct:
     * course in ltct:published or in an ltct:org:* category. access::may_enrol_into() then
     * decides for each person.
     *
     * @return array[] each {id, idnumber, fullname, categoryidnumber}, by course id
     */
    public static function enrollable_courses(): array {
        global $DB;
        $sql = "SELECT c.id, c.idnumber, c.fullname, cc.idnumber AS categoryidnumber
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE " . $DB->sql_like('c.idnumber', ':course') . "
                   AND (cc.idnumber = :published OR " . $DB->sql_like('cc.idnumber', ':orgcat') . ")
              ORDER BY c.fullname";
        $params = [
            'course' => $DB->sql_like_escape(access::COURSE_PREFIX) . '%',
            'published' => access::PUBLISHED_CATEGORY,
            'orgcat' => $DB->sql_like_escape(access::ORG_CATEGORY_PREFIX) . '%',
        ];
        $courses = [];
        foreach ($DB->get_records_sql($sql, $params) as $course) {
            $courses[(int)$course->id] = [
                'id' => (int)$course->id,
                'idnumber' => (string)$course->idnumber,
                'fullname' => format_string($course->fullname, true,
                    ['context' => \context_course::instance($course->id)]),
                'categoryidnumber' => (string)$course->categoryidnumber,
            ];
        }
        return $courses;
    }

    /**
     * The courses a person is enrolled in through an organisation-enrolment instance.
     *
     * @param int $userid
     * @return array<int, true> course ids
     */
    public static function org_enrolled_course_ids(int $userid): array {
        global $DB;
        $sql = "SELECT DISTINCT e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.enrol = :plugin
                   AND e.customchar1 = :marker";
        $ids = $DB->get_fieldset_sql($sql, ['userid' => $userid, 'plugin' => access::ENROL_PLUGIN,
            'marker' => access::ENROL_MARKER]);
        return array_fill_keys(array_map('intval', $ids), true);
    }
}
