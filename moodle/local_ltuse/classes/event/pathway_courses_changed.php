<?php
namespace local_ltuse\event;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\pathway\catalogue;

/**
 * The courses on one pathway changed (spec 006, contracts/pathway-api.md "Events").
 *
 * Fired after the change is committed: by local_ltuse_set_course_pathway, once per key a
 * published course joined or left, and by site_config.py apply, once per role whose
 * competency set changed or that was retired or declared again. Not fired when a course is
 * hidden or shown in Moodle, nor when a competency is retired.
 *
 * Shape, pinned for spec 008:
 *   crud 'u', edulevel LEVEL_OTHER, no objecttable or objectid, system context,
 *   userid the web service or apply caller,
 *   other {pathwaykey: string, added: int[], removed: int[]} course ids.
 *
 * Carries keys and ids only: no name, and no count of learners. Course ids must be PHP ints:
 * a caller holding ids read from the database casts them, or validate_data() refuses the event.
 */
class pathway_courses_changed extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->context = \context_system::instance();
    }

    public static function get_name() {
        return get_string('eventpathwaycourseschanged', 'local_ltuse');
    }

    public function get_description() {
        $other = $this->other;
        return "The user with id '{$this->userid}' changed the courses on the pathway '{$other['pathwaykey']}': "
            . "added course ids [" . implode(', ', $other['added']) . "], "
            . "removed course ids [" . implode(', ', $other['removed']) . "].";
    }

    protected function validate_data() {
        parent::validate_data();
        $other = $this->other;
        if (!is_array($other)) {
            throw new \coding_exception('The \'other\' property must be set.');
        }
        if (!isset($other['pathwaykey']) || !is_string($other['pathwaykey'])
                || catalogue::parse_key($other['pathwaykey']) === null) {
            throw new \coding_exception('The \'pathwaykey\' value must be set in other, as a pathway key.');
        }
        foreach (['added', 'removed'] as $list) {
            if (!array_key_exists($list, $other) || !is_array($other[$list])) {
                throw new \coding_exception("The '{$list}' value must be set in other, as a list of course ids.");
            }
            foreach ($other[$list] as $courseid) {
                if (!is_int($courseid) || $courseid <= 0) {
                    throw new \coding_exception("The '{$list}' value in other must hold course ids as integers.");
                }
            }
        }
    }

    public static function get_objectid_mapping() {
        return self::NOT_MAPPED;
    }

    public static function get_other_mapping() {
        // Course ids on a pathway are site-wide and not restored with a course's logs.
        return ['pathwaykey' => self::NOT_MAPPED, 'added' => self::NOT_MAPPED, 'removed' => self::NOT_MAPPED];
    }
}
