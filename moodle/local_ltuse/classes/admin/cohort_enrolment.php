<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

use stdClass;

/**
 * Enrol a cohort into a course by cohort sync, under spec 002's organisation rules; take it
 * out by disabling, never deleting (spec 008, US2; research R7, R11).
 *
 * One enrol_cohort instance per (cohort, course). ensure() finds any existing instance for the
 * cohort, enabled or not, including one the site team made by hand, because add_instance()
 * does not check for duplicates (enrol/cohort/lib.php, only edit_instance_validation() does).
 * A new instance carries the marker customchar1 = 'ltct:008', customint2 = 0 (no group), the
 * role enrolment_rules gives, and customchar2 = 'pathway' when made through a pathway, so the
 * summary can report a pathway-made enrolment whose course left the cohort's pathways.
 *
 * remove() sets the instance to ENROL_INSTANCE_DISABLED. Deleting would unenrol every member,
 * and Moodle clears a learner's grades and completion when their last enrolment in a course
 * goes (spec 002 R7); disabling keeps everything and re-enabling brings it back. Disabling
 * fires only enrol_instance_updated, with no per-user event, so both ensure() and remove()
 * call the course-mentor sync themselves (research R10).
 *
 * Pathways (spec 006, research R11): the cohort is checked against every course of the
 * pathway before 006's table is touched, because 006 never lowers enrol = 1. A course joining
 * a pathway later is enrolled by the observer on pathway_courses_changed; a course leaving one
 * unenrols nobody. Until 006 is installed the pathway calls refuse with "pathways are not
 * installed" and do nothing.
 *
 * Nothing here checks a capability: the external functions do.
 */
class cohort_enrolment {

    /** customchar1 of every instance this tool creates. */
    const MARKER = 'ltct:008';

    /** customchar2 of an instance made through a pathway. */
    const PATHWAY_MARKER = 'pathway';

    /** The enrol plugin: core's cohort sync. */
    const PLUGIN = 'cohort';

    /** Spec 006's classes (research R11), called only when installed. */
    const CATALOGUE = '\local_ltuse\pathway\catalogue';
    const ASSIGNMENTS = '\local_ltuse\pathway\assignments';

    /**
     * Find a cohort and a course by idnumber. Either missing is a refusal naming it.
     *
     * @param string $cohortidnumber
     * @param string $courseidnumber '' to resolve the cohort only
     * @return array ['refusal' => string, 'cohort' => stdClass|null, 'course' => stdClass|null]
     */
    public static function resolve(string $cohortidnumber, string $courseidnumber = ''): array {
        global $DB;
        $missing = [];
        $cohort = $DB->get_record('cohort', ['idnumber' => $cohortidnumber], 'id, idnumber');
        if (!$cohort) {
            $missing[] = $cohortidnumber;
        }
        $course = null;
        if ($courseidnumber !== '') {
            $course = self::course_by_idnumber($courseidnumber);
            if (!$course) {
                $missing[] = $courseidnumber;
            }
        }
        return [
            'refusal' => $missing ? get_string('admin:refusal:missing', 'local_ltuse', implode(', ', $missing)) : '',
            'cohort' => $cohort ?: null,
            'course' => $course,
        ];
    }

    /**
     * What ensure() or remove() would do for one pair. Changes nothing.
     *
     * @param int $cohortid
     * @param int $courseid
     * @param string $action ensure or remove
     * @return array ['outcome' => would_add|would_enable|would_disable|already|refused,
     *               'reason' => string key, 'role' => role shortname or '', 'course' => idnumber,
     *               'courseid' => int]
     */
    public static function preview(int $cohortid, int $courseid, string $action): array {
        $facts = self::pair_facts($cohortid, $courseid);
        $instance = self::find_instance($cohortid, $courseid);
        $result = ['outcome' => 'already', 'reason' => '', 'role' => '', 'course' => $facts['courseidnumber'],
            'courseid' => $courseid];

        if ($action === 'remove') {
            // Disabling is always allowed, whoever made the instance and whatever the rules
            // say now: it keeps every enrolment, grade and completion.
            if ($instance && (int)$instance->status === ENROL_INSTANCE_ENABLED) {
                $result['outcome'] = 'would_disable';
            }
            return $result;
        }

        $decision = enrolment_rules::decide($facts['cohortidnumber'], $facts['courseidnumber'], $facts['categoryidnumber']);
        if ($decision['role'] === null) {
            return array_merge($result, ['outcome' => 'refused', 'reason' => $decision['reason']]);
        }
        $result['role'] = $decision['role'];
        if (!$instance) {
            $result['outcome'] = 'would_add';
        } else if ((int)$instance->roleid !== self::role_id($decision['role'])) {
            // A hand-made instance with another role: the site team decides, not this tool.
            return array_merge($result, ['outcome' => 'refused', 'reason' => 'role_mismatch']);
        } else if ((int)$instance->status !== ENROL_INSTANCE_ENABLED) {
            $result['outcome'] = 'would_enable';
        }
        return $result;
    }

    /**
     * Enrol the cohort in the course: add the instance, or re-enable the one there is.
     *
     * enrol_cohort's add_instance() and update_status() run enrol_cohort_sync() for the course
     * at once, so current members are enrolled in this same call. The rules are checked here
     * too, because the pathway observer calls this directly.
     *
     * @param int $cohortid
     * @param int $courseid
     * @param bool $viapathway mark a new instance as made through a pathway
     * @return string added, enabled, already, or refused (the rules or a role mismatch)
     */
    public static function ensure(int $cohortid, int $courseid, bool $viapathway = false): string {
        $preview = self::preview($cohortid, $courseid, 'ensure');
        $plugin = enrol_get_plugin(self::PLUGIN);
        switch ($preview['outcome']) {
            case 'would_add':
                $fields = [
                    'customint1' => $cohortid,
                    'roleid' => self::role_id($preview['role']),
                    'customint2' => 0,                  // COHORT_NOGROUP: groups are spec 002's.
                    'customchar1' => self::MARKER,
                    'status' => ENROL_INSTANCE_ENABLED,
                ];
                if ($viapathway) {
                    $fields['customchar2'] = self::PATHWAY_MARKER;
                }
                $plugin->add_instance(get_course($courseid), $fields);
                $status = 'added';
                break;
            case 'would_enable':
                $plugin->update_status(self::find_instance($cohortid, $courseid), ENROL_INSTANCE_ENABLED);
                $status = 'enabled';
                break;
            case 'already':
                return 'already';
            default:
                return 'refused';
        }
        self::sync_mentors($courseid);
        return $status;
    }

    /**
     * Take the cohort out of the course by disabling its instance. Never deletes it.
     *
     * @param int $cohortid
     * @param int $courseid
     * @return string disabled or already
     */
    public static function remove(int $cohortid, int $courseid): string {
        $instance = self::find_instance($cohortid, $courseid);
        if (!$instance || (int)$instance->status !== ENROL_INSTANCE_ENABLED) {
            return 'already';
        }
        enrol_get_plugin(self::PLUGIN)->update_status($instance, ENROL_INSTANCE_DISABLED);
        self::sync_mentors($courseid);
        return 'disabled';
    }

    /**
     * The cohort-sync instance for this cohort in this course, enabled or not, or null. The
     * oldest one when the site team made several by hand.
     *
     * @param int $cohortid
     * @param int $courseid
     * @return stdClass|null
     */
    public static function find_instance(int $cohortid, int $courseid): ?stdClass {
        foreach (enrol_get_instances($courseid, false) as $instance) {
            if ($instance->enrol === self::PLUGIN && (int)$instance->customint1 === $cohortid) {
                return $instance;
            }
        }
        return null;
    }

    /**
     * How many people the cohort holds, for the preview.
     *
     * @param int $cohortid
     * @return int
     */
    public static function member_count(int $cohortid): int {
        global $DB;
        return $DB->count_records('cohort_members', ['cohortid' => $cohortid]);
    }

    /**
     * The courses this cohort has an enabled cohort-sync instance in, whoever made it.
     *
     * A read of enrol by enrol and customint1 (listed in README.md): enrol_get_instances()
     * reads one course at a time, and the question here is "which courses".
     *
     * @param int $cohortid
     * @return int[] course ids, ascending
     */
    public static function enabled_courses(int $cohortid): array {
        global $DB;
        $ids = $DB->get_fieldset_select('enrol', 'DISTINCT courseid',
            'enrol = :enrol AND customint1 = :cohortid AND status = :status',
            ['enrol' => self::PLUGIN, 'cohortid' => $cohortid, 'status' => ENROL_INSTANCE_ENABLED]);
        $ids = array_map('intval', $ids);
        sort($ids);
        return $ids;
    }

    // --- enrol mirror (research R8) -----------------------------------------------------------

    /**
     * What `enrol mirror --from K1 --to K2` would do: for every course in ltct:published where
     * K1's cohort has an enabled cohort-sync instance, preview ensure() for K2's cohort. This is
     * spec 002's rule that each Area's cohort is enrolled in every shared course the sil cohort
     * is before anyone moves.
     *
     * Organisation-only courses are left out: they are K1's own, and a mover's enrolment there
     * is suspended by rule (spec 002 FR-017).
     *
     * @param int $tocohortid K2's cohort
     * @param int $fromcohortid K1's cohort
     * @return array preview()[] per course, in course id order
     */
    public static function preview_mirror(int $tocohortid, int $fromcohortid): array {
        $courses = [];
        foreach (self::enabled_courses($fromcohortid) as $courseid) {
            $facts = self::pair_facts($fromcohortid, $courseid);
            if ($facts['categoryidnumber'] !== enrolment_rules::PUBLISHED_CATEGORY
                    || !preg_match(enrolment_rules::COURSE_PATTERN, $facts['courseidnumber'])) {
                continue;
            }
            $courses[] = self::preview($tocohortid, $courseid, 'ensure');
        }
        return $courses;
    }

    // --- pathways (spec 006) ------------------------------------------------------------------

    /**
     * @return bool spec 006's catalogue and assignments are installed
     */
    public static function pathways_installed(): bool {
        return class_exists(self::CATALOGUE) && class_exists(self::ASSIGNMENTS);
    }

    /**
     * What enrolling a cohort through a pathway would do, course by course (research R11).
     *
     * @param int $cohortid
     * @param string $key the pathway key, competency:<slug> or role:<key>
     * @return array ['refusal' => string, 'assignment' => would_add|already, 'courses' => preview()[]]
     */
    public static function preview_pathway(int $cohortid, string $key): array {
        $refusal = self::pathway_refusal($key);
        if ($refusal !== '') {
            return ['refusal' => $refusal, 'assignment' => '', 'courses' => []];
        }
        $catalogue = self::CATALOGUE;
        $assignments = self::ASSIGNMENTS;
        $courses = [];
        foreach ($catalogue::courses($key) as $courseid) {
            $courses[] = self::preview($cohortid, (int)$courseid, 'ensure');
        }
        $assigned = in_array($cohortid, array_map('intval', $assignments::cohorts_for($key, true)), true);
        return ['refusal' => '', 'assignment' => $assigned ? 'already' : 'would_add', 'courses' => $courses];
    }

    /**
     * Give the cohort the pathway with enrol = 1, but only if the rules allow the cohort in
     * every course on it now. 006's table is not touched otherwise (research R11).
     *
     * @param int $cohortid
     * @param string $key
     * @return string done, already, or a reason key for the refusal
     */
    public static function assign_pathway(int $cohortid, string $key): string {
        $preview = self::preview_pathway($cohortid, $key);
        if ($preview['refusal'] !== '') {
            return 'pathway_unavailable';
        }
        foreach ($preview['courses'] as $course) {
            if ($course['outcome'] === 'refused') {
                return 'pathway_course_refused';
            }
        }
        if ($preview['assignment'] === 'already') {
            return 'already';
        }
        $assignments = self::ASSIGNMENTS;
        $assignments::assign($key, $cohortid, true);
        return 'done';
    }

    /**
     * Whether a course is on the pathway now (catalogue::courses()).
     *
     * @param string $key
     * @param int $courseid
     * @return bool
     */
    public static function on_pathway(string $key, int $courseid): bool {
        if (self::pathway_refusal($key) !== '') {
            return false;
        }
        $catalogue = self::CATALOGUE;
        return in_array($courseid, array_map('intval', $catalogue::courses($key)), true);
    }

    /**
     * Courses joined a pathway: ensure each for every cohort holding it with enrol = 1. Never
     * unenrols anyone for a course that left (research R11). Called by the observer.
     *
     * @param string $key
     * @param int[] $added course ids
     * @return int how many instances were added or re-enabled
     */
    public static function pathway_courses_added(string $key, array $added): int {
        if (!self::pathways_installed() || !$added) {
            return 0;
        }
        $assignments = self::ASSIGNMENTS;
        $changed = 0;
        foreach (array_map('intval', $assignments::cohorts_for($key, true)) as $cohortid) {
            foreach (array_map('intval', $added) as $courseid) {
                if (in_array(self::ensure($cohortid, $courseid, true), ['added', 'enabled'], true)) {
                    $changed++;
                }
            }
        }
        return $changed;
    }

    /**
     * Ensure every course of every pathway for every cohort holding it with enrol = 1. For the
     * hourly task (task T065): picks up a course shown again, which fires no 006 event.
     *
     * @return array ['added' => int, 'enabled' => int, 'refused' => int]
     */
    public static function reconcile_pathways(): array {
        $counts = ['added' => 0, 'enabled' => 0, 'refused' => 0];
        if (!self::pathways_installed()) {
            return $counts;
        }
        $catalogue = self::CATALOGUE;
        $assignments = self::ASSIGNMENTS;
        foreach ($catalogue::all() as $key) {
            $cohorts = array_map('intval', $assignments::cohorts_for($key, true));
            if (!$cohorts) {
                continue;
            }
            foreach (array_map('intval', $catalogue::courses($key)) as $courseid) {
                foreach ($cohorts as $cohortid) {
                    $status = self::ensure($cohortid, $courseid, true);
                    if (isset($counts[$status])) {
                        $counts[$status]++;
                    }
                }
            }
        }
        return $counts;
    }

    // --- helpers ------------------------------------------------------------------------------

    /**
     * Why a pathway key cannot be used now, or '' when it can.
     *
     * @param string $key
     * @return string a plain sentence
     */
    protected static function pathway_refusal(string $key): string {
        if (!self::pathways_installed()) {
            return get_string('admin:refusal:nopathways', 'local_ltuse');
        }
        $catalogue = self::CATALOGUE;
        // parse_key() is null for a bad key, including one with a trailing newline.
        if ($catalogue::parse_key($key) === null) {
            return get_string('admin:refusal:pathwaykey', 'local_ltuse', $key);
        }
        // is_assignable() is the test assignments::assign() applies, so a key it refuses never
        // reaches assign() as an exception. It is true for every key exists() is true for,
        // except a retired competency that still has courses, and also for a live competency
        // with no course yet, whose first course arrives through pathway_courses_changed.
        if (!$catalogue::is_assignable($key)) {
            return get_string('admin:refusal:pathwaykey', 'local_ltuse', $key);
        }
        return '';
    }

    /**
     * The idnumbers enrolment_rules needs about one pair.
     *
     * @param int $cohortid
     * @param int $courseid
     * @return array cohortidnumber, courseidnumber, categoryidnumber
     */
    protected static function pair_facts(int $cohortid, int $courseid): array {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, idnumber, category', MUST_EXIST);
        return [
            'cohortidnumber' => (string)$DB->get_field('cohort', 'idnumber', ['id' => $cohortid], MUST_EXIST),
            'courseidnumber' => (string)$course->idnumber,
            'categoryidnumber' => (string)$DB->get_field('course_categories', 'idnumber', ['id' => $course->category]),
        ];
    }

    /**
     * @param string $idnumber
     * @return stdClass|null id, idnumber, category
     */
    protected static function course_by_idnumber(string $idnumber): ?stdClass {
        global $DB;
        if ($idnumber === '') {
            return null;
        }
        return $DB->get_record('course', ['idnumber' => $idnumber], 'id, idnumber, category') ?: null;
    }

    /**
     * A role's id by shortname. Core has no lookup of a role by shortname that is not a read.
     *
     * @param string $shortname
     * @return int
     */
    protected static function role_id(string $shortname): int {
        global $DB;
        return (int)$DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * Keep the course's course mentors in step after an instance changed (research R10), in
     * this request rather than waiting for the enrol_instance_updated observer's turn. The sync
     * does nothing while local_ltuse/coursementorsync is 0.
     *
     * @param int $courseid
     */
    protected static function sync_mentors(int $courseid): void {
        course_mentor_sync::sync_course($courseid);
    }
}
