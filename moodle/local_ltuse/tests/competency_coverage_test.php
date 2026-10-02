<?php
// PHPUnit tests for the per-competency report (spec 004 R15, FR-013).
//
// Synthetic data only, in the PHPUnit database: every name here is a fixture-* or Fixture
// value, never a real learner, course or organisation. This file is never run on a server
// that holds other people's sites (spec 002's rule); where it runs is plan.md "Testing".

namespace local_ltuse;

use core_reportbuilder_generator;
use core_reportbuilder\manager;
use core_reportbuilder\tests\core_reportbuilder_testcase;
use local_ltuse\reportbuilder\datasource\competency_coverage;
use stdClass;

/**
 * The competency_coverage datasource counts what courses aim at, and delivery use, per competency.
 *
 * The fixture is one delivery course and one pilot-only course that aim at "Fixture Alpha",
 * plus a course outside the publisher's ltct: idnumbers. Expected counts for Alpha:
 *  - courses 2: the delivery course and the pilot-only course, not the outside one;
 *  - in delivery 1: only the delivery course has a cohort-sync instance with the student role;
 *  - enrolments 2: one learner in two student cohorts. A suspended enrolment, an orgmanager
 *    cohort enrolment and a manual (pilot) enrolment are not counted;
 *  - learners 1: the same learner, counted once;
 *  - completions 1: that learner's one course completion. The pilot's and the orgmanager's
 *    completions are not counted, nor an incomplete row.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\reportbuilder\datasource\competency_coverage
 * @covers     \local_ltuse\reportbuilder\local\entities\competency
 * @covers     \local_ltuse\reportbuilder\local\entities\coverage
 */
final class competency_coverage_test extends core_reportbuilder_testcase {

    /** Default columns, in order: the competency ones only (T059). */
    private const DEFAULT_COLUMNS = [
        'competency:category',
        'competency:name',
    ];

    /** The five counts, in the order the declared report lists them. */
    private const COVERAGE_COLUMNS = [
        'coverage:courses',
        'coverage:indelivery',
        'coverage:enrolments',
        'coverage:learners',
        'coverage:completions',
    ];

    /**
     * Insert one competency row, as site_config.py apply would.
     *
     * @param string $name
     * @param string $category
     * @param int $sortorder
     * @param int $retired
     * @return int the row id
     */
    private function add_competency(string $name, string $category, int $sortorder, int $retired = 0): int {
        global $DB;
        return (int) $DB->insert_record('local_ltuse_competency', (object) [
            'name' => $name,
            'category' => $category,
            'sortorder' => $sortorder,
            'retired' => $retired,
            'timemodified' => time(),
        ]);
    }

    /**
     * Map a course to a competency, as local_ltuse_set_course_competencies would.
     *
     * @param int $courseid
     * @param int $competencyid
     */
    private function map(int $courseid, int $competencyid): void {
        global $DB;
        $DB->insert_record('local_ltuse_course_comp', (object) [
            'courseid' => $courseid,
            'competencyid' => $competencyid,
            'timemodified' => time(),
        ]);
    }

    /**
     * Record a course completion row for a user.
     *
     * @param int $userid
     * @param int $courseid
     * @param int|null $timecompleted null for an incomplete row
     */
    private function completion(int $userid, int $courseid, ?int $timecompleted): void {
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'userid' => $userid,
            'course' => $courseid,
            'timeenrolled' => 0,
            'timestarted' => 0,
            'timecompleted' => $timecompleted,
            'reaggregate' => 0,
        ]);
    }

    /**
     * Add a cohort-sync instance to a course; its sync enrols the cohort's current members.
     *
     * @param stdClass $course
     * @param int $cohortid
     * @param int $roleid
     * @return stdClass the enrol instance
     */
    private function cohort_sync(stdClass $course, int $cohortid, int $roleid): stdClass {
        global $DB;
        enrol_get_plugin('cohort')->add_instance($course, ['customint1' => $cohortid, 'roleid' => $roleid]);
        return $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'cohort', 'customint1' => $cohortid],
            '*', MUST_EXIST);
    }

    /**
     * Build the synthetic fixture described in the class docblock.
     *
     * @return array competency name => id
     */
    private function create_fixture(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $gen = $this->getDataGenerator();
        $this->assertTrue(enrol_is_enabled('cohort'), 'enrol_cohort must be enabled for this test');
        $studentid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $orgmanagerid = (int) $gen->create_role(['shortname' => 'orgmanager']);

        // Framework order (sortorder) is the reverse of alphabetical order on purpose.
        $ids = [
            'Fixture Gamma' => $this->add_competency('Fixture Gamma', 'Fixture Category Two', 1),
            'Fixture Beta' => $this->add_competency('Fixture Beta', 'Fixture Category Two', 2),
            'Fixture Alpha' => $this->add_competency('Fixture Alpha', 'Fixture Category One', 3),
            'Fixture Retired' => $this->add_competency('Fixture Retired', 'Fixture Category One', 4, 1),
        ];

        $delivery = $gen->create_course(['idnumber' => 'ltct:fixture-delivery', 'enablecompletion' => 1]);
        $pilotonly = $gen->create_course(['idnumber' => 'ltct:fixture-pilot', 'enablecompletion' => 1]);
        $outside = $gen->create_course(['idnumber' => 'fixture-not-published', 'enablecompletion' => 1]);

        $this->map((int) $delivery->id, $ids['Fixture Alpha']);
        $this->map((int) $pilotonly->id, $ids['Fixture Alpha']);
        $this->map((int) $outside->id, $ids['Fixture Alpha']);
        $this->map((int) $pilotonly->id, $ids['Fixture Beta']);
        $this->map((int) $delivery->id, $ids['Fixture Retired']);

        $twocohorts = $gen->create_user(['username' => 'fixture-twocohorts']);
        $suspended = $gen->create_user(['username' => 'fixture-suspended']);
        $manager = $gen->create_user(['username' => 'fixture-manager']);
        $pilot = $gen->create_user(['username' => 'fixture-pilot']);
        $outsider = $gen->create_user(['username' => 'fixture-outsider']);

        $cohortone = $gen->create_cohort(['idnumber' => 'ltct:org:fixture-one:learners']);
        $cohorttwo = $gen->create_cohort(['idnumber' => 'ltct:org:fixture-two:learners']);
        $managers = $gen->create_cohort(['idnumber' => 'ltct:org:fixture-one:managers']);
        $outsidecohort = $gen->create_cohort(['idnumber' => 'fixture-outside']);

        cohort_add_member($cohortone->id, $twocohorts->id);
        cohort_add_member($cohorttwo->id, $twocohorts->id);
        cohort_add_member($cohortone->id, $suspended->id);
        cohort_add_member($managers->id, $manager->id);
        cohort_add_member($outsidecohort->id, $outsider->id);

        $instanceone = $this->cohort_sync($delivery, (int) $cohortone->id, $studentid);
        $this->cohort_sync($delivery, (int) $cohorttwo->id, $studentid);
        $this->cohort_sync($delivery, (int) $managers->id, $orgmanagerid);
        $this->cohort_sync($outside, (int) $outsidecohort->id, $studentid);

        // Pilots are manual enrolments, in both mapped courses.
        $gen->enrol_user($pilot->id, $delivery->id, 'student', 'manual');
        $gen->enrol_user($pilot->id, $pilotonly->id, 'student', 'manual');

        // Suspend after every sync has run, so no later sync re-activates it.
        enrol_get_plugin('cohort')->update_user_enrol($instanceone, $suspended->id, ENROL_USER_SUSPENDED);

        // The fixture is as intended before the report is asked anything.
        $this->assertEquals(3, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = ? AND e.enrol = 'cohort' AND e.roleid = ?",
            [$delivery->id, $studentid]));
        $this->assertEquals(1, $DB->count_records('user_enrolments',
            ['enrolid' => $instanceone->id, 'userid' => $suspended->id, 'status' => ENROL_USER_SUSPENDED]));

        $now = time();
        $this->completion((int) $twocohorts->id, (int) $delivery->id, $now);
        $this->completion((int) $suspended->id, (int) $delivery->id, null);
        $this->completion((int) $manager->id, (int) $delivery->id, $now);
        $this->completion((int) $pilot->id, (int) $delivery->id, $now);
        $this->completion((int) $pilot->id, (int) $pilotonly->id, $now);
        $this->completion((int) $outsider->id, (int) $outside->id, $now);

        return $ids;
    }

    /**
     * Create a report on the datasource.
     *
     * @param bool $default whether to add the datasource's default columns, sorting and filters
     * @param bool $counts whether to append the five coverage columns after them
     * @return \core_reportbuilder\local\models\report
     */
    private function create_report(bool $default, bool $counts = false): \core_reportbuilder\local\models\report {
        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report([
            'name' => 'Fixture coverage',
            'source' => competency_coverage::class,
            'default' => (int) $default,
        ]);
        if ($counts) {
            foreach (self::COVERAGE_COLUMNS as $uniqueidentifier) {
                $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => $uniqueidentifier]);
            }
        }
        return $report;
    }

    /**
     * One row per non-retired competency, uncovered ones as 0, with the exclusions applied.
     */
    public function test_default_report_counts(): void {
        $this->resetAfterTest();
        $this->create_fixture();

        $report = $this->create_report(true, true);
        $content = $this->get_custom_report_content($report->get('id'));

        // Three rows: the retired competency is hidden, though a course is mapped to it.
        $this->assertCount(3, $content);
        $rows = array_map('array_values', $content);

        // Default sorting is competency:name ascending, which is framework order.
        [$category, $name, $courses, $indelivery, $enrolments, $learners, $completions] = $rows[0];
        $this->assertEquals('Fixture Category Two', $category);
        $this->assertEquals('Fixture Gamma', $name);
        // An uncovered competency shows 0 in every count, never NULL or an empty cell.
        foreach ([$courses, $indelivery, $enrolments, $learners, $completions] as $count) {
            $this->assertNotNull($count);
            $this->assertNotSame('', $count);
            $this->assertEquals(0, $count);
        }

        // Beta: aimed at by the pilot-only course, which has no delivery.
        $this->assertEquals(['Fixture Category Two', 'Fixture Beta', 1, 0, 0, 0, 0], $rows[1]);

        // Alpha: see the class docblock for each figure.
        $this->assertEquals(['Fixture Category One', 'Fixture Alpha', 2, 1, 2, 1, 1], $rows[2]);
    }

    /**
     * A retired competency is not a row, whatever its map rows say.
     */
    public function test_retired_competency_is_hidden(): void {
        $this->resetAfterTest();
        $this->create_fixture();

        $report = $this->create_report(true, true);
        $names = array_column(array_map('array_values', $this->get_custom_report_content($report->get('id'))), 1);
        $this->assertNotContains('Fixture Retired', $names);
        $this->assertEquals(['Fixture Gamma', 'Fixture Beta', 'Fixture Alpha'], $names);
    }

    /**
     * Each exclusion, on its own: pilot, orgmanager cohort, suspended, two cohorts.
     */
    public function test_exclusions(): void {
        global $DB;
        $this->resetAfterTest();
        $ids = $this->create_fixture();

        $report = $this->create_report(true, true);
        $alpha = array_values($this->get_custom_report_content($report->get('id'))[2]);
        [, , , , $enrolments, $learners, $completions] = $alpha;

        // The pilot's manual enrolment and the manager's orgmanager cohort enrolment are both
        // active, and both have a completion; neither is counted.
        $pilot = $DB->get_record('user', ['username' => 'fixture-pilot'], '*', MUST_EXIST);
        $manager = $DB->get_record('user', ['username' => 'fixture-manager'], '*', MUST_EXIST);
        $this->assertEquals(2, $DB->count_records('course_completions', ['userid' => $pilot->id]));
        $this->assertEquals(1, $DB->count_records('course_completions', ['userid' => $manager->id]));

        // Two enrolments, one learner, one completion: the learner in two cohorts; the
        // suspended enrolment is out of the enrolment count, and its incomplete row is not a
        // completion.
        $this->assertEquals(2, $enrolments);
        $this->assertEquals(1, $learners);
        $this->assertEquals(1, $completions);

        // Unsuspending brings that enrolment, and that learner, into the counts.
        $suspended = $DB->get_record('user', ['username' => 'fixture-suspended'], '*', MUST_EXIST);
        $DB->set_field_select('user_enrolments', 'status', ENROL_USER_ACTIVE, 'userid = ?', [$suspended->id]);
        $alpha = array_values($this->get_custom_report_content($report->get('id'))[2]);
        $this->assertEquals(3, $alpha[4]);
        $this->assertEquals(2, $alpha[5]);
        $this->assertEquals(1, $alpha[6]);

        // Retiring Alpha drops its row, though its map rows stay.
        $DB->set_field('local_ltuse_competency', 'retired', 1, ['id' => $ids['Fixture Alpha']]);
        $this->assertCount(2, $this->get_custom_report_content($report->get('id')));
    }

    /**
     * Sorting on competency:name follows sortorder, not the alphabet, in both directions.
     */
    public function test_name_sorts_on_sortorder(): void {
        $this->resetAfterTest();
        $this->create_fixture();

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $this->create_report(false);
        $column = $generator->create_column([
            'reportid' => $report->get('id'),
            'uniqueidentifier' => 'competency:name',
            'sortenabled' => 1,
            'sortdirection' => SORT_ASC,
        ]);

        $names = array_column(array_map('array_values', $this->get_custom_report_content($report->get('id'))), 0);
        $this->assertEquals(['Fixture Gamma', 'Fixture Beta', 'Fixture Alpha'], $names);

        $column->set('sortdirection', SORT_DESC)->update();
        $names = array_column(array_map('array_values', $this->get_custom_report_content($report->get('id'))), 0);
        $this->assertEquals(['Fixture Alpha', 'Fixture Beta', 'Fixture Gamma'], $names);
    }

    /**
     * The datasource offers competency and coverage elements only: nothing names a person or a course.
     */
    public function test_only_competency_and_coverage_elements(): void {
        $this->resetAfterTest();

        $instance = manager::get_report_from_persistent($this->create_report(false));
        $columns = array_keys($instance->get_columns());
        sort($columns);
        $expected = array_merge(self::DEFAULT_COLUMNS, self::COVERAGE_COLUMNS);
        sort($expected);
        $this->assertEquals($expected, $columns);

        foreach (array_merge(array_keys($instance->get_filters()), array_keys($instance->get_conditions())) as $id) {
            $this->assertStringStartsWith('competency:', $id);
        }

        // No count can be aggregated, so no report can group it away.
        foreach ($instance->get_columns() as $id => $column) {
            $this->assertEquals([], \core_reportbuilder\local\helpers\aggregation::get_column_aggregations(
                $column->get_type(), $column->get_disabled_aggregation()), $id);
        }

        $default = manager::get_report_from_persistent($this->create_report(true));
        $this->assertEquals(self::DEFAULT_COLUMNS, array_map(
            static fn($column): string => $column->get_unique_identifier(),
            array_values($default->get_active_columns())));
        $this->assertEquals(['competency:category', 'competency:name'], array_keys($default->get_active_filters()));
        // No default condition: a condition can only drop rows, and zero rows are the point.
        $this->assertEquals([], array_keys($default->get_active_conditions()));
    }

    /**
     * Every column, sorted, and every condition, works on its own (core's stress helpers).
     */
    public function test_stress_datasource(): void {
        $this->resetAfterTest();
        $this->create_fixture();

        $this->datasource_stress_test_columns(competency_coverage::class);
        $this->datasource_stress_test_columns_aggregation(competency_coverage::class);
        $this->datasource_stress_test_conditions(competency_coverage::class, 'competency:name');
    }

    /**
     * The FR-010 strict label rule, as scripts/site_config.py _check_aim_label(strict=True).
     *
     * @param string $label
     * @return string[] the rules the label breaks; empty when it passes
     */
    private static function aim_label_problems(string $label): array {
        $problems = [];
        if (preg_match('/certif/i', $label)) {
            $problems[] = 'certif';
        }
        if (preg_match('/\b(?:advanced\s+beginner|practitioner|trainer|proficient)\b/i', $label)) {
            $problems[] = 'retired level name';
        }
        preg_match_all('/[A-Za-z]+/', $label, $m);
        $words = array_map('strtolower', $m[0]);
        $levels = array_keys($words, 'level', true);
        foreach ($words as $i => $word) {
            if (in_array($word, ['learner', 'reached', 'achieved', 'attained'], true)) {
                foreach ($levels as $j) {
                    if (abs($i - $j) <= 3) {
                        $problems[] = "{$word} near level";
                        break 2;
                    }
                }
            }
        }
        if (preg_match('/reached|achieved|attained|\bcompetent\b/i', $label)) {
            $problems[] = 'strict';
        }
        return $problems;
    }

    /**
     * The rule itself refuses what it should, so a pass below means something.
     */
    public function test_aim_label_rule(): void {
        $this->assertEquals([], self::aim_label_problems('Competency courses aim at'));
        $this->assertEquals([], self::aim_label_problems('Delivery learners'));
        $this->assertNotEquals([], self::aim_label_problems('Certified'));
        $this->assertNotEquals([], self::aim_label_problems('Practitioner'));
        $this->assertNotEquals([], self::aim_label_problems('Learner level'));
        $this->assertNotEquals([], self::aim_label_problems('Learners who reached it'));
        $this->assertNotEquals([], self::aim_label_problems('Competent learners'));
    }

    /**
     * No datasource, entity, column or filter string holds a word the strict rule refuses.
     */
    public function test_lang_strings_are_aims(): void {
        $strings = get_string_manager()->load_component_strings('local_ltuse', 'en', true);
        $checked = 0;
        foreach ($strings as $key => $text) {
            if (!preg_match('/^(?:datasource|entity|column|filter):/', $key)) {
                continue;
            }
            $checked++;
            $this->assertEquals([], self::aim_label_problems($text), "string '{$key}': '{$text}'");
        }
        // The datasource name, two entity titles, seven columns and two filters.
        $this->assertGreaterThanOrEqual(12, $checked);
    }
}
