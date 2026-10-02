<?php
// Report builder datasource: one row per framework competency (spec 004 R15, FR-013).

namespace local_ltuse\reportbuilder\datasource;

defined('MOODLE_INTERNAL') || die();

use core_reportbuilder\datasource;
use core_reportbuilder\local\helpers\database;
use local_ltuse\reportbuilder\local\entities\competency;
use local_ltuse\reportbuilder\local\entities\coverage;

/**
 * Competency coverage: what published courses aim at, and how they are used in delivery.
 *
 * THE MAIN TABLE IS THE COMPETENCY LIST, local_ltuse_competency, so a competency that no
 * course aims at is still a row and shows 0 in every count. Retired competencies (no longer
 * in competencies.yaml) are left out by a base condition, not a report condition, so editing
 * the report cannot bring them back or remove the rule.
 *
 * ONLY TWO ENTITIES, both on the main table: competency (name, category) and coverage (the
 * counts, as subqueries). There is no user, course or enrolment entity, so a report built
 * on this source can show counts per competency and nothing that names a person or a
 * course. Nothing here reads tool_lp or core_competency, and nothing shows a level
 * (Principle V).
 */
class competency_coverage extends datasource {

    /**
     * The name of the report source, as the custom report UI lists it.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('datasource:competency_coverage', 'local_ltuse');
    }

    /**
     * Main table, base condition and the two entities.
     */
    protected function initialise(): void {
        $competency = new competency();
        $c = $competency->get_table_alias('local_ltuse_competency');
        $this->set_main_table('local_ltuse_competency', $c);

        $pretired = database::generate_param_name();
        $this->add_base_condition_sql("{$c}.retired = :{$pretired}", [$pretired => 0]);

        $this->add_entity($competency);
        $this->add_entity((new coverage())->set_table_alias('local_ltuse_competency', $c));

        $this->add_all_from_entities();
    }

    /**
     * Columns added to a report created with defaults: the competency ones (T059). The counts
     * are added by the declaration (moodle/site/reports.yaml), which site_config.py apply
     * creates with $default = false, or by hand.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
            'competency:category',
            'competency:name',
        ];
    }

    /**
     * Framework order: competency:name sorts on sortorder.
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'competency:name' => SORT_ASC,
        ];
    }

    /**
     * Filters added to a report created with defaults: the competency ones.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [
            'competency:category',
            'competency:name',
        ];
    }

    /**
     * No default conditions. A condition can only drop rows, and the zero rows are the point
     * of the table (contracts/declaration.md, "Validation rules for competency-coverage").
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }
}
