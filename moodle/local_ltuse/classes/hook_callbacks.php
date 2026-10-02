<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use navigation_node;

/**
 * Callbacks registered in db/hooks.php.
 */
class hook_callbacks {

    /**
     * Add "Mentoring" to the primary navigation for anyone with a mentor or a learner (spec
     * 003, research R3). Nobody else sees it, so the site gains no item for most users.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        global $USER;
        if (!isloggedin() || isguestuser() || during_initial_install()) {
            return;
        }
        if (!mentoring::has_relationship((int)$USER->id)) {
            return;
        }
        $hook->get_primaryview()->add(get_string('mentoring', 'local_ltuse'),
            new moodle_url('/local/ltuse/mentoring.php'), navigation_node::TYPE_CUSTOM, null,
            'local_ltuse_mentoring');
    }
}
