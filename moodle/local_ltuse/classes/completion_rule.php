<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

/**
 * What a payload completion value means to Moodle (spec 004, R1).
 *
 * The payload says, platform-neutrally, what finishes a module: `view` (open it), `submit`
 * (receive a grade) or `pass` (receive a passing grade). This class is the one place those
 * words become Moodle's moduleinfo fields, so moodle_payload.py never learns a Moodle field
 * name and no other plugin class re-derives the mapping.
 *
 * PURE. No Moodle function is called and no core constant is read, so
 * tests/criteria_harness.php can check the table with a bare PHP CLI. The values are
 * written as literals with the constant they stand for beside them (lib/completionlib.php,
 * MOODLE_502_STABLE).
 *
 * `completionusegrade` is a form field, not a course_modules column: set_moduleinfo_defaults()
 * turns it into completiongradeitemnumber = 0 (course/modlib.php). util::upsert_module()
 * decides whether these fields are written at all, because on an existing module writing
 * them resets every learner's state for it.
 *
 * An unknown value is refused. It is never mapped to a default, because a default would
 * be a completion rule nobody declared.
 */
class completion_rule {

    /** The payload values this class knows, in the order data-model.md lists them. */
    const RULES = ['view', 'submit', 'pass'];

    /**
     * The moduleinfo completion fields for one payload value.
     *
     * @param string $rule view, submit or pass
     * @return array field => int
     * @throws \invalid_parameter_exception for any other value
     */
    public static function fields(string $rule): array {
        switch ($rule) {
            case 'view':
                return [
                    'completion' => 2,          // COMPLETION_TRACKING_AUTOMATIC
                    'completionview' => 1,      // COMPLETION_VIEW_REQUIRED
                ];
            case 'submit':
                return [
                    'completion' => 2,          // COMPLETION_TRACKING_AUTOMATIC
                    'completionusegrade' => 1,  // becomes completiongradeitemnumber = 0
                    'completionpassgrade' => 0, // any grade completes it
                ];
            case 'pass':
                return [
                    'completion' => 2,          // COMPLETION_TRACKING_AUTOMATIC
                    'completionusegrade' => 1,  // becomes completiongradeitemnumber = 0
                    'completionpassgrade' => 1, // needs gradepass > 0 on the grade item
                ];
        }
        throw new \invalid_parameter_exception(
            "completion '{$rule}' is not one of " . implode(', ', self::RULES));
    }

    /**
     * True if a course_modules row already carries this rule.
     *
     * Read from the stored row, where every value is a string and completiongradeitemnumber
     * is null when no grade rule is set (data-model.md "Completion state per module").
     *
     * @param \stdClass $cm a course_modules record
     * @param string $rule view, submit or pass
     * @return bool
     */
    public static function matches(\stdClass $cm, string $rule): bool {
        self::fields($rule);
        if ((int)$cm->completion !== 2) {
            return false;
        }
        $usegrade = isset($cm->completiongradeitemnumber);
        if ($rule === 'view') {
            return (int)$cm->completionview === 1 && !$usegrade;
        }
        return $usegrade && (int)$cm->completionpassgrade === ($rule === 'pass' ? 1 : 0);
    }
}
