<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

/**
 * The time zone notice on the office-hours booking pages (spec 011, research R14; plan
 * decision 2).
 *
 * A learner sets their zone on their profile, and nothing prompts them to. A booking is where
 * a wrong zone costs a missed meeting, so the office-hours scheduler's pages say which zone
 * their times are in, with a link to change it. hook_callbacks::top_of_body() adds the notice
 * through core's before_standard_top_of_body_html_generation hook.
 *
 * This class is pure: no Moodle calls, so tests/timezone_notice_harness.php tests it without
 * Moodle. The callback gathers the inputs and turns describe()'s result into strings.
 */
class timezone_notice {

    /** Course-module idnumber of the office-hours scheduler (moodle/site/office-hours.yaml). */
    const SCHEDULER_IDNUMBER = 'ltct:officehours:scheduler';

    /** Page types the scheduler's own pages carry (mod/scheduler/*.php). */
    const PAGETYPE_PREFIX = 'mod-scheduler-';

    /** A stored user.timezone of 99 means "the site default" (core_date::get_user_timezone()). */
    const SITE_DEFAULT = '99';

    /**
     * Whether the notice belongs on this page: one of the office-hours scheduler's pages.
     *
     * @param string $pagetype $PAGE->pagetype
     * @param string|null $cmidnumber the page's course-module idnumber, or null for no module
     * @return bool
     */
    public static function applies(string $pagetype, ?string $cmidnumber): bool {
        return strpos($pagetype, self::PAGETYPE_PREFIX) === 0 && $cmidnumber === self::SCHEDULER_IDNUMBER;
    }

    /**
     * Which sentence the notice shows, and the zone it names.
     *
     * @param string $stored the user's stored timezone (user.timezone), '99' when never chosen
     * @param string $resolved the zone their times are shown in (core_date::get_user_timezone())
     * @return array ['string' => lang string id, 'zone' => zone identifier]
     */
    public static function describe(string $stored, string $resolved): array {
        if (trim($stored) === '' || $stored === self::SITE_DEFAULT) {
            return ['string' => 'tznotice:default', 'zone' => $resolved];
        }
        return ['string' => 'tznotice', 'zone' => $resolved];
    }
}
