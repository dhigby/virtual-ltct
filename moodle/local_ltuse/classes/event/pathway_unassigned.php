<?php
namespace local_ltuse\event;

defined('MOODLE_INTERNAL') || die();

/**
 * A pathway's assignment to a cohort was removed (spec 006, contracts/pathway-api.md "Events").
 *
 * Fired by \local_ltuse\pathway\assignments::unassign(), the only way a row of
 * local_ltuse_pathway_cohort is deleted, including for a deleted cohort. Nobody is unenrolled.
 *
 * Shape, pinned for spec 008:
 *   crud 'd', edulevel LEVEL_OTHER, objecttable local_ltuse_pathway_cohort, objectid the
 *   deleted row's id, context the cohort's context (the caller passes it), userid who
 *   unassigned, other {pathwaykey: string, cohortid: int, enrol: int} with the row's last enrol.
 *
 * Carries keys and ids only: no name, and no count of learners.
 */
class pathway_unassigned extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_ltuse_pathway_cohort';
    }

    public static function get_name() {
        return get_string('eventpathwayunassigned', 'local_ltuse');
    }

    public function get_description() {
        $other = $this->other;
        return "The user with id '{$this->userid}' removed the pathway '{$other['pathwaykey']}' "
            . "from the cohort with id '{$other['cohortid']}'; its last enrol was {$other['enrol']}.";
    }

    protected function validate_data() {
        parent::validate_data();
        pathway_assigned::validate_assignment($this->objectid, $this->other);
    }

    public static function get_objectid_mapping() {
        return self::NOT_MAPPED;
    }

    public static function get_other_mapping() {
        // Cohorts and pathway assignments are site-wide and not restored with a course's logs.
        return ['pathwaykey' => self::NOT_MAPPED, 'cohortid' => self::NOT_MAPPED, 'enrol' => self::NOT_MAPPED];
    }
}
