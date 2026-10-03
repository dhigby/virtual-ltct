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

    /**
     * Say which time zone the office-hours booking page's times are in, with a link to change
     * it (spec 011, research R14; plan decision 2). Only on that scheduler's own pages; never a
     * redirect. The hook is dispatched from core_renderer::standard_top_of_body_html()
     * (lib/classes/output/core_renderer.php:308 on MOODLE_502_STABLE).
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function top_of_body(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $USER;
        if (!isloggedin() || isguestuser() || during_initial_install()) {
            return;
        }
        // A plain read: moodle_page has __get but no __isset (lib/pagelib.php), so `??` would
        // always give null. magic_get_cm() returns null on a page with no module.
        $cm = $PAGE->cm;
        if (!timezone_notice::applies((string)$PAGE->pagetype, $cm ? (string)$cm->idnumber : null)) {
            return;
        }
        $notice = timezone_notice::describe((string)($USER->timezone ?? ''), \core_date::get_user_timezone($USER));
        $link = \html_writer::link(new moodle_url('/user/edit.php', ['returnto' => 'profile']),
            get_string('tznotice:change', 'local_ltuse'));
        $hook->add_html($hook->renderer->notification(
            get_string($notice['string'], 'local_ltuse', s($notice['zone'])) . ' ' . $link,
            \core\output\notification::NOTIFY_INFO, false));
    }
}
