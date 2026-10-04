<?php
namespace local_ltuse\event;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\pathway\catalogue;

/**
 * A pathway was assigned to a cohort, or an assignment's enrol flag changed (spec 006,
 * contracts/pathway-api.md "Events").
 *
 * Fired by \local_ltuse\pathway\assignments::assign() when a row of local_ltuse_pathway_cohort
 * is created or its enrol changes, and by nothing else.
 *
 * Shape, pinned for spec 008:
 *   crud 'c', edulevel LEVEL_OTHER, objecttable local_ltuse_pathway_cohort, objectid the row id,
 *   context the cohort's context (the caller passes it), userid who assigned,
 *   other {pathwaykey: string, cohortid: int, enrol: int}.
 *
 * Carries keys and ids only: no name, and no count of learners.
 */
class pathway_assigned extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_ltuse_pathway_cohort';
    }

    public static function get_name() {
        return get_string('eventpathwayassigned', 'local_ltuse');
    }

    public function get_description() {
        $other = $this->other;
        return "The user with id '{$this->userid}' assigned the pathway '{$other['pathwaykey']}' "
            . "to the cohort with id '{$other['cohortid']}', with enrol {$other['enrol']}.";
    }

    protected function validate_data() {
        parent::validate_data();
        self::validate_assignment($this->objectid, $this->other);
    }

    /**
     * The checks pathway_assigned and pathway_unassigned share: an assignment row id, and
     * {pathwaykey, cohortid, enrol} in other.
     *
     * @param mixed $objectid
     * @param mixed $other
     * @throws \coding_exception
     */
    public static function validate_assignment($objectid, $other): void {
        if (empty($objectid)) {
            throw new \coding_exception('The \'objectid\' must be the local_ltuse_pathway_cohort row id.');
        }
        if (!is_array($other)) {
            throw new \coding_exception('The \'other\' property must be set.');
        }
        if (!isset($other['pathwaykey']) || !is_string($other['pathwaykey'])
                || catalogue::parse_key($other['pathwaykey']) === null) {
            throw new \coding_exception('The \'pathwaykey\' value must be set in other, as a pathway key.');
        }
        if (!isset($other['cohortid']) || !is_int($other['cohortid']) || $other['cohortid'] <= 0) {
            throw new \coding_exception('The \'cohortid\' value must be set in other, as an integer.');
        }
        if (!isset($other['enrol']) || !in_array($other['enrol'], [0, 1], true)) {
            throw new \coding_exception('The \'enrol\' value must be set in other, as 0 or 1.');
        }
    }

    public static function get_objectid_mapping() {
        return self::NOT_MAPPED;
    }

    public static function get_other_mapping() {
        // Cohorts and pathway assignments are site-wide and not restored with a course's logs.
        return ['pathwaykey' => self::NOT_MAPPED, 'cohortid' => self::NOT_MAPPED, 'enrol' => self::NOT_MAPPED];
    }
}
