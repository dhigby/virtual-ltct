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
     * Add "Pathways" for every signed-in user (spec 006, R10; FR-009): the dashboard is the
     * landing page and carries the primary navigation. Add "Assign pathways" only for someone
     * who may assign a pathway to some cohort (contracts/pages.md "Navigation").
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        global $USER;
        if (!isloggedin() || isguestuser() || during_initial_install()) {
            return;
        }
        $primary = $hook->get_primaryview();
        if (mentoring::has_relationship((int)$USER->id)) {
            $primary->add(get_string('mentoring', 'local_ltuse'),
                new moodle_url('/local/ltuse/mentoring.php'), navigation_node::TYPE_CUSTOM, null,
                'local_ltuse_mentoring');
        }
        $primary->add(get_string('pathways', 'local_ltuse'),
            new moodle_url('/local/ltuse/pathways.php'), navigation_node::TYPE_CUSTOM, null,
            'local_ltuse_pathways');
        if (self::may_assign_pathways((int)$USER->id)) {
            $primary->add(get_string('pathway:manage', 'local_ltuse'),
                new moodle_url('/local/ltuse/pathways_manage.php'), navigation_node::TYPE_CUSTOM, null,
                'local_ltuse_pathways_manage');
        }
    }

    /**
     * Whether the user may assign a pathway to at least one cohort: the cheap form of
     * pathway\assignments::may_assign() over every cohort, read on every page. The site team
     * holds moodle/cohort:assign at system context; an organisation manager manages at least
     * one organisation (spec 002), whose member cohort they may assign to. The Assign page
     * itself still checks may_assign() for each cohort.
     *
     * @param int $userid
     * @return bool
     */
    private static function may_assign_pathways(int $userid): bool {
        global $CFG;
        if (has_capability('moodle/cohort:assign', \context_system::instance(), $userid)) {
            return true;
        }
        require_once($CFG->dirroot . '/local/ltuse/lib.php');
        return (bool)\local_ltuse_managed_organisation_keys($userid);
    }

    /**
     * Add "My organisation" to the user menu for a member of any ltct:org:<key>:managers
     * cohort (spec 002 amendment 2026-10-02, research R10). Nobody else sees it. The hook is
     * dispatched from user_get_user_navigation_info() (user/lib.php:970 on MOODLE_502_STABLE);
     * an item is a stdClass with itemtype 'link', url, title and titleidentifier, as core's own
     * menu items are, or add_navitem() drops it (user/classes/hook/extend_user_menu.php).
     *
     * @param \core_user\hook\extend_user_menu $hook
     */
    public static function user_menu(\core_user\hook\extend_user_menu $hook): void {
        global $CFG, $USER;
        if (!isloggedin() || isguestuser() || during_initial_install()) {
            return;
        }
        require_once($CFG->dirroot . '/local/ltuse/lib.php');
        if (!local_ltuse_managed_organisation_keys((int)$USER->id)) {
            return;
        }
        $hook->add_navitem((object)[
            'itemtype' => 'link',
            'url' => new moodle_url('/local/ltuse/organisation.php'),
            'title' => get_string('organisation', 'local_ltuse'),
            'titleidentifier' => 'organisation,local_ltuse',
        ]);
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
