<?php
// Report builder entity for the LTC competency framework (spec 004 R15).

namespace local_ltuse\reportbuilder\local\entities;

defined('MOODLE_INTERNAL') || die();

use context_system;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;

/**
 * One competency of the framework: its name and category, from local_ltuse_competency.
 *
 * The table is filled by site_config.py apply from the repo's competencies.yaml, so it is
 * curriculum data and names nobody. The class name is the entity name report builder uses
 * in identifiers (competency:name), because get_default_entity_name() is private in 5.2.
 *
 * NAME SORTS IN FRAMEWORK ORDER. Sorting competency:name sorts on sortorder, the 1-based
 * position across competencies.yaml, so the report reads in the framework's own order
 * rather than alphabetically.
 *
 * NO AGGREGATION. Every row is one competency, and the coverage counts beside it are
 * correlated subqueries on its id. Grouping these columns would have to group those
 * subqueries away from the id they depend on, which Postgres refuses and which would
 * mean nothing anyway, so aggregation is disabled here as it is on the counts.
 */
class competency extends base {

    /**
     * Database tables that this entity uses.
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
        return new lang_string('entity:competency', 'local_ltuse');
    }

    /**
     * The category and name columns.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $c = $this->get_table_alias('local_ltuse_competency');
        $formatname = static function(?string $value): string {
            return format_string((string) $value, true, ['context' => context_system::instance()]);
        };

        $columns[] = (new column(
            'category',
            new lang_string('column:competency_category', 'local_ltuse'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$c}.category")
            ->set_is_sortable(true)
            ->set_disabled_aggregation_all()
            ->add_callback($formatname);

        $columns[] = (new column(
            'name',
            new lang_string('column:competency_name', 'local_ltuse'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$c}.name")
            ->add_field("{$c}.sortorder")
            ->set_is_sortable(true, ["{$c}.sortorder"])
            ->set_disabled_aggregation_all()
            ->add_callback($formatname);

        return $columns;
    }

    /**
     * The category and name filters.
     *
     * @return filter[]
     */
    protected function get_available_filters(): array {
        $c = $this->get_table_alias('local_ltuse_competency');

        $filters[] = (new filter(
            select::class,
            'category',
            new lang_string('filter:competency_category', 'local_ltuse'),
            $this->get_entity_name(),
            "{$c}.category"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function(): array {
                global $DB;
                $categories = $DB->get_fieldset_sql(
                    'SELECT DISTINCT category FROM {local_ltuse_competency} WHERE retired = 0 ORDER BY category');
                $options = [];
                foreach ($categories as $category) {
                    $options[$category] = format_string($category, true, ['context' => context_system::instance()]);
                }
                return $options;
            });

        $filters[] = (new filter(
            text::class,
            'name',
            new lang_string('filter:competency_name', 'local_ltuse'),
            $this->get_entity_name(),
            "{$c}.name"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }

    /**
     * The same two, as conditions. A condition on the competency itself narrows which
     * competencies are listed; it cannot drop a competency for having zero counts.
     *
     * @return filter[]
     */
    protected function get_available_conditions(): array {
        return $this->get_available_filters();
    }
}
