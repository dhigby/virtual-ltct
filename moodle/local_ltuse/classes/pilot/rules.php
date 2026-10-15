<?php
namespace local_ltuse\pilot;

defined('MOODLE_INTERNAL') || die();

/**
 * Who a course author may enrol as a trial student, and where (2026-10-15).
 *
 * Pure functions over facts the caller reads, so they are tested without a database, in the
 * style of admin\enrolment_rules. Each returns null when the action is allowed, or the
 * local_ltuse lang string that says why not. actions applies them; the page is a convenience,
 * never the authority.
 *
 * The boundary: a pilot is a published ltct:<slug> course in the LTC Pilots category, and a
 * trial student comes in only through the course's manual method, because manual enrolment is
 * what keeps pilot results out of every delivery report (moodle/site/reports.yaml, spec 004
 * R10). So nobody already enrolled another way can be made a trial student, and nothing but a
 * manual enrolment is ever removed here.
 */
class rules {

    /** The category every stage-7 pilot sits in (scripts/moodle_payload.py PILOTS_CATEGORY). */
    const PILOTS_CATEGORY = 'ltct:pilots';

    /** The enrolment method a pilot learner comes through (process/stages/07-pilot.md). */
    const ENROL_PLUGIN = 'manual';

    /**
     * Is this course a pilot this page may act on?
     *
     * @param string $courseidnumber the course's idnumber
     * @param string $categoryidnumber its category's idnumber
     * @return string|null null, or the reason it is refused
     */
    public static function course(string $courseidnumber, string $categoryidnumber): ?string {
        // ltct:<slug> only: ltct:<slug>:<n> is a module, and ltct:officehours is not a course
        // the publisher made.
        if (!preg_match('/^ltct:[a-z0-9-]+$/', $courseidnumber) || $courseidnumber === 'ltct:officehours') {
            return 'pilot:notpilot';
        }
        if ($categoryidnumber !== self::PILOTS_CATEGORY) {
            return 'pilot:notpilot';
        }
        return null;
    }

    /**
     * May this person be enrolled as a trial student?
     *
     * @param array $person {deleted, suspended, confirmed, siteadmin, self}
     * @param string[] $methods the enrolment methods they are already enrolled through in the
     *        course (enrol.enrol of each user_enrolments row), any status
     * @return string|null null, or the reason it is refused
     */
    public static function enrol(array $person, array $methods): ?string {
        if (!empty($person['deleted']) || !empty($person['suspended']) || empty($person['confirmed'])) {
            // The same answer as no account at all: the page never says more about an address.
            return 'pilot:noaccount';
        }
        if (!empty($person['siteadmin']) || !empty($person['self'])) {
            return 'pilot:notallowed';
        }
        foreach ($methods as $method) {
            if ($method !== self::ENROL_PLUGIN) {
                return 'pilot:otherenrolment';
            }
        }
        if ($methods) {
            return 'pilot:alreadyenrolled';
        }
        return null;
    }

    /**
     * The one account an address names, from the candidates an exact match found.
     *
     * @param array $matches the accounts whose email equals the typed address, ignoring case
     * @return \stdClass|null the account, or null when there is none or more than one
     */
    public static function single(array $matches): ?\stdClass {
        return count($matches) === 1 ? reset($matches) : null;
    }
}
