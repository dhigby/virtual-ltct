<?php
namespace local_ltuse\pathway;

defined('MOODLE_INTERNAL') || die();

/**
 * What is on a pathway (spec 006, contracts/pathway-api.md; data-model "Pathway membership").
 *
 * A pathway is never stored: it is computed here, from the tables the publisher and
 * site_config.py apply fill. Read only, and it reads no user data.
 *
 * A pathway is named by a key, the only identifier spec 008 stores or passes:
 *
 *   competency:<slug>   the competency whose local_ltuse_competency.slug is <slug>
 *   role:<key>          the role whose local_ltuse_role_pathway.rolekey is <key>
 *
 * A course is on competency C's pathway when all hold (membership_sql(), the one place this
 * rule lives; publish.md step 2):
 *
 *   1. course.idnumber is ltct:<slug> with no second colon;
 *   2. course.visible = 1;
 *   3. its local_ltuse_course_pathway row has delivery = 1 and a targetlevel of 1-4;
 *   4. a local_ltuse_course_comp row links it to C;
 *   5. C is not retired.
 *
 * A role pathway's courses are the union over its competencies that are not retired, each
 * course once. A retired role has no courses. FR-014: rule 4 reads the same course_comp rows
 * spec 004's coverage report counts.
 */
class catalogue {

    /** Prefix of a competency pathway key. */
    const COMPETENCY_PREFIX = 'competency:';
    /** Prefix of a role pathway key. */
    const ROLE_PREFIX = 'role:';
    /** A whole pathway key. */
    const KEY_PATTERN = '/^(competency:[a-z0-9][a-z0-9-]*|role:[a-z][a-z0-9-]*)$/';
    /** The longest key, in characters; local_ltuse_pathway_cohort.pathwaykey is char(100). */
    const KEY_MAX_LENGTH = 100;

    /** Kinds parse_key() returns. */
    const KIND_COMPETENCY = 'competency';
    const KIND_ROLE = 'role';

    /**
     * Splits a key into its kind and the variable part, or null when it is not a key.
     *
     * Checks the form only; whether the key resolves is exists() and is_assignable(). Use this,
     * not KEY_PATTERN alone: the pinned pattern ends in `$`, which also matches before a
     * trailing newline, so a key ending in one is refused here.
     *
     * @param string $key
     * @return array|null ['kind' => KIND_COMPETENCY|KIND_ROLE, 'id' => slug or role key]
     */
    public static function parse_key(string $key): ?array {
        if ($key === '' || strlen($key) > self::KEY_MAX_LENGTH || strpos($key, "\n") !== false
                || !preg_match(self::KEY_PATTERN, $key)) {
            return null;
        }
        if (strpos($key, self::COMPETENCY_PREFIX) === 0) {
            return ['kind' => self::KIND_COMPETENCY, 'id' => substr($key, strlen(self::COMPETENCY_PREFIX))];
        }
        return ['kind' => self::KIND_ROLE, 'id' => substr($key, strlen(self::ROLE_PREFIX))];
    }

    /**
     * The key of a competency pathway.
     *
     * @param string $slug
     * @return string
     */
    public static function competency_key(string $slug): string {
        return self::COMPETENCY_PREFIX . $slug;
    }

    /**
     * The key of a role pathway.
     *
     * @param string $rolekey
     * @return string
     */
    public static function role_key(string $rolekey): string {
        return self::ROLE_PREFIX . $rolekey;
    }

    /**
     * The membership rule as SQL: a FROM ... WHERE fragment over every (course, competency)
     * pair where the course is on that competency's pathway now.
     *
     * Aliases: c {course}, cp {local_ltuse_course_pathway}, cc {local_ltuse_course_comp},
     * comp {local_ltuse_competency}. A caller appends "AND ..." conditions and its own SELECT
     * and ORDER BY. Parameters are named, prefixed ltcatm, so they do not collide.
     *
     * @return array [string $fromwhere, array $params]
     */
    public static function membership_sql(): array {
        global $DB;
        $ltct = $DB->sql_like('c.idnumber', ':ltcatmprefix');
        $notmodule = $DB->sql_like('c.idnumber', ':ltcatmmodule', true, true, true);
        $sql = "FROM {course} c
                JOIN {local_ltuse_course_pathway} cp ON cp.courseid = c.id
                JOIN {local_ltuse_course_comp} cc ON cc.courseid = c.id
                JOIN {local_ltuse_competency} comp ON comp.id = cc.competencyid
               WHERE {$ltct}
                 AND {$notmodule}
                 AND c.visible = 1
                 AND cp.delivery = 1
                 AND cp.targetlevel >= 1 AND cp.targetlevel <= 4
                 AND comp.retired = 0
                 AND comp.slug <> ''";
        return [$sql, ['ltcatmprefix' => 'ltct:%', 'ltcatmmodule' => 'ltct:%:%']];
    }

    /**
     * True for a competency pathway with at least one course, and for a live (not retired)
     * role.
     *
     * @param string $key
     * @return bool
     */
    public static function exists(string $key): bool {
        global $DB;
        $parsed = self::parse_key($key);
        if ($parsed === null) {
            return false;
        }
        if ($parsed['kind'] === self::KIND_ROLE) {
            return $DB->record_exists('local_ltuse_role_pathway', ['rolekey' => $parsed['id'], 'retired' => 0]);
        }
        [$fromwhere, $params] = self::membership_sql();
        $params['ltcatslug'] = $parsed['id'];
        return $DB->record_exists_sql("SELECT 1 {$fromwhere} AND comp.slug = :ltcatslug", $params);
    }

    /**
     * True when exists(), or for a competency key whose competency is live but has no course
     * yet. A retired role, a retired competency or an unknown key is false.
     *
     * @param string $key
     * @return bool
     */
    public static function is_assignable(string $key): bool {
        global $DB;
        $parsed = self::parse_key($key);
        if ($parsed === null) {
            return false;
        }
        if ($parsed['kind'] === self::KIND_COMPETENCY) {
            return $DB->record_exists('local_ltuse_competency', ['slug' => $parsed['id'], 'retired' => 0]);
        }
        return self::exists($key);
    }

    /**
     * The course ids on the pathway now, ordered by target level, then course full name.
     *
     * A role key gives the union over its live competencies, each course once. A retired
     * role, an unknown key or a hidden course gives nothing.
     *
     * @param string $key
     * @return int[]
     */
    public static function courses(string $key): array {
        global $DB;
        $parsed = self::parse_key($key);
        if ($parsed === null) {
            return [];
        }
        [$fromwhere, $params] = self::membership_sql();
        if ($parsed['kind'] === self::KIND_COMPETENCY) {
            $params['ltcatslug'] = $parsed['id'];
            $sql = "SELECT c.id, cp.targetlevel, c.fullname
                    {$fromwhere}
                      AND comp.slug = :ltcatslug
                 ORDER BY cp.targetlevel, c.fullname, c.id";
        } else {
            $params['ltcatrole'] = $parsed['id'];
            $sql = "SELECT DISTINCT c.id, cp.targetlevel, c.fullname
                    {$fromwhere}
                      AND comp.id IN (SELECT rpc.competencyid
                                        FROM {local_ltuse_role_pathway_comp} rpc
                                        JOIN {local_ltuse_role_pathway} rp ON rp.id = rpc.roleid
                                       WHERE rp.rolekey = :ltcatrole AND rp.retired = 0)
                 ORDER BY cp.targetlevel, c.fullname, c.id";
        }
        return array_map('intval', array_keys($DB->get_records_sql($sql, $params)));
    }

    /**
     * Every key the course is on now: competency keys in framework order, then role keys in
     * declared order.
     *
     * @param int $courseid
     * @return string[]
     */
    public static function pathways_for_course(int $courseid): array {
        global $DB;
        [$fromwhere, $params] = self::membership_sql();
        $params['ltcatcourseid'] = $courseid;
        $competencies = $DB->get_records_sql(
            "SELECT comp.id, comp.slug, comp.sortorder
             {$fromwhere}
               AND c.id = :ltcatcourseid
          ORDER BY comp.sortorder, comp.id", $params);
        if (!$competencies) {
            return [];
        }
        $keys = [];
        foreach ($competencies as $competency) {
            $keys[] = self::competency_key($competency->slug);
        }
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($competencies), SQL_PARAMS_NAMED, 'ltcatcomp');
        $roles = $DB->get_records_sql(
            "SELECT DISTINCT rp.id, rp.rolekey, rp.sortorder
               FROM {local_ltuse_role_pathway} rp
               JOIN {local_ltuse_role_pathway_comp} rpc ON rpc.roleid = rp.id
              WHERE rp.retired = 0 AND rpc.competencyid {$insql}
           ORDER BY rp.sortorder, rp.id", $inparams);
        foreach ($roles as $role) {
            $keys[] = self::role_key($role->rolekey);
        }
        return $keys;
    }

    /**
     * Every key exists() is true for: competency keys in framework order, then role keys in
     * declared order.
     *
     * @return string[]
     */
    public static function all(): array {
        global $DB;
        [$fromwhere, $params] = self::membership_sql();
        $competencies = $DB->get_records_sql(
            "SELECT DISTINCT comp.id, comp.slug, comp.sortorder
             {$fromwhere}
          ORDER BY comp.sortorder, comp.id", $params);
        $keys = [];
        foreach ($competencies as $competency) {
            $keys[] = self::competency_key($competency->slug);
        }
        $roles = $DB->get_records('local_ltuse_role_pathway', ['retired' => 0], 'sortorder, id', 'id, rolekey');
        foreach ($roles as $role) {
            $keys[] = self::role_key($role->rolekey);
        }
        return $keys;
    }

    /**
     * Every key is_assignable() is true for, in the same order as all(): every live
     * competency, with or without a course yet, then every live role. A learner's own list
     * follows this, so a pathway given to them before its first course is delivered still
     * shows, as "No course yet" rows.
     *
     * @return string[]
     */
    public static function assignable(): array {
        global $DB;
        $keys = [];
        $competencies = $DB->get_records_select('local_ltuse_competency', "retired = 0 AND slug <> ''", [],
            'sortorder, id', 'id, slug');
        foreach ($competencies as $competency) {
            $keys[] = self::competency_key($competency->slug);
        }
        $roles = $DB->get_records('local_ltuse_role_pathway', ['retired' => 0], 'sortorder, id', 'id, rolekey');
        foreach ($roles as $role) {
            $keys[] = self::role_key($role->rolekey);
        }
        return $keys;
    }
}
