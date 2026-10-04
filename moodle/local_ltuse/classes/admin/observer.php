<?php
namespace local_ltuse\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for the administration tooling (spec 008), registered in db/events.php in
 * the block headed "Spec 008". Kept apart from \local_ltuse\observer so each spec's observers
 * are found in one place.
 */
class observer {

    /**
     * Spec 006: courses joined or left a pathway (research R11). Every cohort holding the
     * pathway with enrol = 1 is enrolled in each added course, under enrolment_rules. A
     * removed course unenrols nobody; the summary reports it.
     *
     * The event's other is {pathwaykey: string, added: int[], removed: int[]}, fired after
     * commit. Type-hinted as the base class, so this file loads before spec 006 is installed.
     *
     * @param \core\event\base $event \local_ltuse\event\pathway_courses_changed
     */
    public static function pathway_courses_changed(\core\event\base $event): void {
        $other = $event->other ?? [];
        $key = (string)($other['pathwaykey'] ?? '');
        $added = array_values(array_filter(array_map('intval', (array)($other['added'] ?? []))));
        if ($key === '' || !$added) {
            return;
        }
        cohort_enrolment::pathway_courses_added($key, $added);
    }
}
