<?php
// Report builder entity: how many courses aim at a competency, and their delivery use (spec 004 R15).

namespace local_ltuse\reportbuilder\local\entities;

defined('MOODLE_INTERNAL') || die();

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use lang_string;
use local_ltuse\organisation\access;

/**
 * Five counts per competency, each one correlated subquery on competencyid = {c}.id.
 *
 * This entity sits on the datasource's main table, local_ltuse_competency, and joins
 * nothing: every count is a subquery in the column itself. A subquery returns 0 when
 * nothing matches, so a competency no course aims at is still a row, with 0 in every count.
 * A join with a report condition would drop exactly those rows, and they are the point of
 * the table.
 *
 * WHAT COUNTS AS DELIVERY is fixed here, in our SQL, not in a report condition, so nobody
 * can widen it by editing the report:
 *  - a course counts only if it exists and its idnumber starts with ltct: (the publisher's);
 *  - delivery is an enabled (status 0) instance whose role is the one with shortname
 *    'student', looked up by shortname and never by id, and that is either cohort sync
 *    (enrol = 'cohort') or the course's organisation-enrolment instance, through which a
 *    manager enrols their own learners (enrol = 'self' and customchar1 =
 *    access::ENROL_MARKER, spec 002 R10). That leaves out each organisation's managers
 *    cohort, synced as orgmanager (002 R2), any other self-enrolment, and every manual
 *    enrolment, which is how pilots are enrolled (R10, FR-012);
 *  - enrolments are active (ue.status 0) user enrolments on those instances, of users not
 *    deleted. They are enrolments, not people: one learner in two cohorts, or in two
 *    courses aiming at one competency, counts twice. Learners counts the same rows by
 *    distinct user;
 *  - completions are course_completions rows with timecompleted set, in a mapped course,
 *    for a user holding a delivery enrolment in that course in any status. EXISTS, not a
 *    join, so a learner in two cohorts still counts once.
 *
 * No column shows a level or names a person or a course (Principle V, FR-013). No column
 * can be aggregated: each is already a count, and grouping would separate the subquery
 * from the competency id it depends on.
 */
class coverage extends base {

    /**
     * Database tables that this entity uses. The datasource sets this alias to its main table's.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'local_ltuse_competency',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:coverage', 'local_ltuse');
    }

    /**
     * The FROM and WHERE every count shares: this competency's mapped ltct: courses.
     *
     * @param string $c the competency table alias
     * @param string $m the map table alias
     * @param string $co the course table alias
     * @return array [string $from, string $where, array $params]
     */
    private static function mapped_courses(string $c, string $m, string $co): array {
        global $DB;
        $pidnumber = database::generate_param_name();
        $from = "{local_ltuse_course_comp} {$m}
                 JOIN {course} {$co} ON {$co}.id = {$m}.courseid";
        $where = "{$m}.competencyid = {$c}.id
                  AND " . $DB->sql_like("{$co}.idnumber", ":{$pidnumber}");
        return [$from, $where, [$pidnumber => 'ltct:%']];
    }

    /**
     * SQL matching a delivery enrol instance: the student role, and either cohort sync or the
     * organisation-enrolment instance. Never manual, so never a pilot.
     *
     * @param string $e the enrol table alias
     * @param bool $enabledonly whether the instance must also be enabled
     * @return array [string $sql, array $params]
     */
    private static function delivery_instance(string $e, bool $enabledonly): array {
        $r = database::generate_alias();
        [$pcohort, $pself, $pmarker, $pstudent, $penabled] = database::generate_param_names(5);
        $sql = "({$e}.enrol = :{$pcohort}
                 OR ({$e}.enrol = :{$pself} AND {$e}.customchar1 = :{$pmarker}))
                AND {$e}.roleid IN (SELECT {$r}.id FROM {role} {$r} WHERE {$r}.shortname = :{$pstudent})";
        $params = [$pcohort => 'cohort', $pself => access::ENROL_PLUGIN, $pmarker => access::ENROL_MARKER,
            $pstudent => 'student'];
        if ($enabledonly) {
            $sql .= " AND {$e}.status = :{$penabled}";
            $params[$penabled] = ENROL_INSTANCE_ENABLED;
        }
        return [$sql, $params];
    }

    /**
     * A count column: integer, sortable, never aggregated, one subquery with an explicit alias.
     *
     * @param string $name column name, also its field alias
     * @param string $sql the subquery, without surrounding brackets
     * @param array $params its rbparamN parameters
     * @return column
     */
    private function count_column(string $name, string $sql, array $params): column {
        return (new column(
            $name,
            new lang_string("column:coverage_{$name}", 'local_ltuse'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("({$sql})", $name, $params)
            ->set_disabled_aggregation_all()
            ->set_is_sortable(true);
    }

    /**
     * The five count columns.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $c = $this->get_table_alias('local_ltuse_competency');
        $columns = [];

        // Courses that aim at it.
        [$m, $co] = database::generate_aliases(2);
        [$from, $where, $params] = self::mapped_courses($c, $m, $co);
        $columns[] = $this->count_column('courses', "SELECT COUNT(1) FROM {$from} WHERE {$where}", $params);

        // Of which in delivery: an enabled delivery instance with the student role.
        [$m, $co, $e] = database::generate_aliases(3);
        [$from, $where, $params] = self::mapped_courses($c, $m, $co);
        [$inst, $instparams] = self::delivery_instance($e, true);
        $columns[] = $this->count_column('indelivery',
            "SELECT COUNT(1) FROM {$from}
              WHERE {$where}
                AND EXISTS (SELECT 1 FROM {enrol} {$e} WHERE {$e}.courseid = {$co}.id AND {$inst})",
            $params + $instparams);

        // Delivery enrolments, then the same rows counted by distinct user.
        foreach (['enrolments' => 'COUNT(1)', 'learners' => 'COUNT(DISTINCT %s.userid)'] as $name => $count) {
            [$m, $co, $e, $ue, $u] = database::generate_aliases(5);
            [$from, $where, $params] = self::mapped_courses($c, $m, $co);
            [$inst, $instparams] = self::delivery_instance($e, true);
            [$pactive, $pnotdeleted] = database::generate_param_names(2);
            $select = sprintf($count, $ue);
            $columns[] = $this->count_column($name,
                "SELECT {$select} FROM {$from}
                   JOIN {enrol} {$e} ON {$e}.courseid = {$co}.id
                   JOIN {user_enrolments} {$ue} ON {$ue}.enrolid = {$e}.id
                   JOIN {user} {$u} ON {$u}.id = {$ue}.userid
                  WHERE {$where}
                    AND {$inst}
                    AND {$ue}.status = :{$pactive}
                    AND {$u}.deleted = :{$pnotdeleted}",
                $params + $instparams + [$pactive => ENROL_USER_ACTIVE, $pnotdeleted => 0]);
        }

        // Delivery course completions: in any enrolment status, counted once per row.
        [$m, $co, $cc, $e, $ue] = database::generate_aliases(5);
        [$from, $where, $params] = self::mapped_courses($c, $m, $co);
        [$inst, $instparams] = self::delivery_instance($e, false);
        $columns[] = $this->count_column('completions',
            "SELECT COUNT(1) FROM {$from}
               JOIN {course_completions} {$cc} ON {$cc}.course = {$co}.id
              WHERE {$where}
                AND {$cc}.timecompleted IS NOT NULL
                AND EXISTS (SELECT 1 FROM {enrol} {$e}
                              JOIN {user_enrolments} {$ue} ON {$ue}.enrolid = {$e}.id
                             WHERE {$e}.courseid = {$co}.id AND {$ue}.userid = {$cc}.userid AND {$inst})",
            $params + $instparams);

        return $columns;
    }

    /**
     * No filters: a filter on a count could only hide competencies by their counts.
     *
     * @return array
     */
    protected function get_available_filters(): array {
        return [];
    }

    /**
     * No conditions, for the same reason: the zero rows are the point of the table.
     *
     * @return array
     */
    protected function get_available_conditions(): array {
        return [];
    }
}
