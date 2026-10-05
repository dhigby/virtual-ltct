<?php
namespace local_ltuse\pathway;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\event\pathway_assigned;
use local_ltuse\event\pathway_unassigned;

/**
 * Who has a pathway: rows of local_ltuse_pathway_cohort (spec 006, FR-013; research R9;
 * contracts/pathway-api.md "assignments").
 *
 * The seam with spec 008. 006 decides which cohorts have a pathway and enrols nobody; 008 owns
 * enrolment, sets the enrol flag through assign($key, $cohortid, true), and observes the events
 * fired here. The signatures below were locked with the 008 session on 2026-10-04.
 *
 * - enrol is never lowered: assign() with $enrol false leaves an existing row's flag as it is,
 *   so a manager re-assigning in 006's page never clears 008's flag. 006 never passes true.
 * - unassign() is the only way a row is deleted, and it always fires pathway_unassigned, so 008
 *   sees every removal, including the cohort_deleted observer's. It unenrols nobody.
 * - assign() and unassign() check no capability: the caller does, with may_assign().
 */
class assignments {

    /** The table this class owns. */
    const TABLE = 'local_ltuse_pathway_cohort';

    /** Prefix of an organisation cohort's idnumber (spec 002). */
    const ORG_PREFIX = 'ltct:org:';
    /** Suffix of an organisation's managers cohort, which no pathway is assigned to by a manager. */
    const MANAGERS_SUFFIX = ':managers';

    /**
     * Links the pathway to the cohort and returns the row id. Idempotent.
     *
     * New row: enrol is (int)$enrol. Existing row: true raises enrol to 1; false leaves it.
     * Fires pathway_assigned when a row is created or its enrol changes, and not otherwise.
     *
     * @param string $key a pathway key catalogue::is_assignable() accepts
     * @param int $cohortid an existing cohort
     * @param bool $enrol set only by spec 008
     * @return int the local_ltuse_pathway_cohort row id
     * @throws \invalid_parameter_exception for a key that is not assignable, or no such cohort
     */
    public static function assign(string $key, int $cohortid, bool $enrol = false): int {
        global $DB, $USER;

        if (!catalogue::is_assignable($key)) {
            throw new \invalid_parameter_exception('Not an assignable pathway key: ' . $key);
        }
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, contextid');
        if (!$cohort) {
            throw new \invalid_parameter_exception('No cohort with id ' . $cohortid);
        }

        $now = time();
        $row = $DB->get_record(self::TABLE, ['pathwaykey' => $key, 'cohortid' => $cohortid]);
        if ($row) {
            if (!$enrol || (int)$row->enrol === 1) {
                return (int)$row->id; // Nothing changes: enrol is never lowered.
            }
            $row->enrol = 1;
            $row->usermodified = (int)$USER->id;
            $row->timemodified = $now;
            $DB->update_record(self::TABLE, $row);
        } else {
            $row = (object)[
                'pathwaykey' => $key,
                'cohortid' => $cohortid,
                'enrol' => $enrol ? 1 : 0,
                'usermodified' => (int)$USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $row->id = $DB->insert_record(self::TABLE, $row);
        }

        pathway_assigned::create([
            'objectid' => (int)$row->id,
            'context' => self::cohort_context($cohort),
            'other' => ['pathwaykey' => $key, 'cohortid' => $cohortid, 'enrol' => (int)$row->enrol],
        ])->trigger();
        return (int)$row->id;
    }

    /**
     * Deletes the assignment if present and fires pathway_unassigned with the row's last enrol.
     *
     * The only way a row is removed. Unenrols nobody. Works for a cohort that has already been
     * deleted: core deletes the cohort row before cohort_deleted fires, so that observer passes
     * the event's context, and the event still carries the cohort's context.
     *
     * @param string $key
     * @param int $cohortid
     * @param \context|null $context the cohort's context, when the cohort row may be gone
     * @return bool true when a row was deleted
     */
    public static function unassign(string $key, int $cohortid, ?\context $context = null): bool {
        global $DB;

        $row = $DB->get_record(self::TABLE, ['pathwaykey' => $key, 'cohortid' => $cohortid]);
        if (!$row) {
            return false;
        }
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, contextid');
        $DB->delete_records(self::TABLE, ['id' => $row->id]);

        pathway_unassigned::create([
            'objectid' => (int)$row->id,
            'context' => $cohort ? self::cohort_context($cohort) : ($context ?? self::cohort_context(null)),
            'other' => ['pathwaykey' => $key, 'cohortid' => $cohortid, 'enrol' => (int)$row->enrol],
        ])->trigger();
        return true;
    }

    /**
     * The cohort's assignments, by key.
     *
     * @param int $cohortid
     * @return array[] list of ['pathwaykey' => string, 'enrol' => int]
     */
    public static function for_cohort(int $cohortid): array {
        global $DB;
        $rows = $DB->get_records(self::TABLE, ['cohortid' => $cohortid], 'pathwaykey, id', 'id, pathwaykey, enrol');
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['pathwaykey' => (string)$row->pathwaykey, 'enrol' => (int)$row->enrol];
        }
        return $out;
    }

    /**
     * The cohort ids the key is assigned to, by id.
     *
     * @param string $key
     * @param bool|null $enrol true: only enrol = 1; false: only enrol = 0; null: all
     * @return int[]
     */
    public static function cohorts_for(string $key, ?bool $enrol = null): array {
        global $DB;
        $conditions = ['pathwaykey' => $key];
        if ($enrol !== null) {
            $conditions['enrol'] = $enrol ? 1 : 0;
        }
        $rows = $DB->get_records(self::TABLE, $conditions, 'cohortid', 'id, cohortid');
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row->cohortid;
        }
        return $ids;
    }

    /**
     * Every key assigned to any cohort the user belongs to, each once, in
     * catalogue::assignable() order. A key that is no longer assignable (a retired role or
     * competency) is left out; its rows are kept.
     *
     * Cohort membership is read with a join on {cohort_members}, the indexed columns core's
     * cohort_is_member() queries (research R11).
     *
     * @param int $userid
     * @return string[]
     */
    public static function pathways_for_user(int $userid): array {
        global $DB;
        if ($userid <= 0) {
            return [];
        }
        $assigned = $DB->get_fieldset_sql(
            "SELECT DISTINCT pc.pathwaykey
               FROM {" . self::TABLE . "} pc
               JOIN {cohort_members} cm ON cm.cohortid = pc.cohortid
              WHERE cm.userid = :userid", ['userid' => $userid]);
        if (!$assigned) {
            return [];
        }
        $assigned = array_flip(array_map('strval', $assigned));
        $keys = [];
        // assignable(), not all(): a competency given before its first course is delivered
        // still shows, as "No course yet" rows (data-model "A learner's pathways").
        foreach (catalogue::assignable() as $key) {
            if (isset($assigned[$key])) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Whether the user may assign pathways to the cohort.
     *
     * True when the user has moodle/cohort:assign in the system context (any cohort), or when,
     * for some key K in local_ltuse_managed_organisation_keys($userid), the cohort's idnumber
     * is ltct:org:K, or starts ltct:org:K: and is not ltct:org:K:managers. Anything else,
     * including a cohort with no idnumber or no such cohort, is false.
     *
     * @param int $userid
     * @param int $cohortid
     * @return bool
     */
    public static function may_assign(int $userid, int $cohortid): bool {
        global $CFG, $DB;
        if ($userid <= 0) {
            return false;
        }
        $cohort = $DB->get_record('cohort', ['id' => $cohortid], 'id, idnumber');
        if (!$cohort) {
            return false;
        }
        if (has_capability('moodle/cohort:assign', \context_system::instance(), $userid)) {
            return true;
        }
        $idnumber = (string)$cohort->idnumber;
        if ($idnumber === '') {
            return false;
        }
        require_once($CFG->dirroot . '/local/ltuse/lib.php');
        foreach (local_ltuse_managed_organisation_keys($userid) as $orgkey) {
            if (self::is_org_cohort_of($idnumber, (string)$orgkey)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether an idnumber is organisation K's member cohort or one of its sub-cohorts, and not
     * its managers cohort. Pure.
     *
     * @param string $idnumber
     * @param string $orgkey
     * @return bool
     */
    public static function is_org_cohort_of(string $idnumber, string $orgkey): bool {
        if ($orgkey === '') {
            return false;
        }
        $base = self::ORG_PREFIX . $orgkey;
        if ($idnumber === $base) {
            return true;
        }
        return strpos($idnumber, $base . ':') === 0 && $idnumber !== $base . self::MANAGERS_SUFFIX;
    }

    /**
     * The context an assignment event carries: the cohort's, or the system context when the
     * cohort is gone or its context cannot be found.
     *
     * @param \stdClass|null $cohort with contextid
     * @return \context
     */
    private static function cohort_context(?\stdClass $cohort): \context {
        if ($cohort && !empty($cohort->contextid)) {
            $context = \context::instance_by_id((int)$cohort->contextid, IGNORE_MISSING);
            if ($context) {
                return $context;
            }
        }
        return \context_system::instance();
    }
}
