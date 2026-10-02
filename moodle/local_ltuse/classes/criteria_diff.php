<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

/**
 * Which course completion criteria to add and which to remove (spec 004, R3).
 *
 * Moodle's only route for changing criteria (course/completion.php) starts by wiping every
 * learner's course completion. set_course_completion instead changes criteria one at a
 * time, and this is the arithmetic it does that with: wanted is the cmids the course
 * should require, present is the cmids its activity criteria name now.
 *
 * PURE, so tests/criteria_harness.php checks it with a bare PHP CLI. Both inputs may repeat
 * an id -- course_completion_criteria has no unique index on (course, moduleinstance) --
 * and may hold DB strings. The output is de-duplicated, sorted ints.
 */
class criteria_diff {

    /**
     * @param array $wantedcmids cmids that should be activity criteria
     * @param array $presentcmids cmids that are activity criteria now
     * @return array ['add' => int[], 'remove' => int[]], each sorted ascending
     */
    public static function diff(array $wantedcmids, array $presentcmids): array {
        $wanted = self::ids($wantedcmids);
        $present = self::ids($presentcmids);
        return [
            'add' => array_values(array_diff($wanted, $present)),
            'remove' => array_values(array_diff($present, $wanted)),
        ];
    }

    /**
     * @param array $ids
     * @return int[] unique, sorted
     */
    private static function ids(array $ids): array {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
}
