<?php
// Privacy declaration for local_ltuse.
//
// The plugin's tables hold the competency framework and which courses aim at which
// competency -- curriculum data, nothing about a person. The per-competency report counts
// enrolments and completions by reading core's tables at query time and stores none of it.

namespace local_ltuse\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * local_ltuse stores no personal data.
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * The language string identifier explaining why this plugin stores no personal data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
