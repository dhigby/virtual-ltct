<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

use context_course;
use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\util;
use moodle_exception;
use stdClass;

/**
 * Make sure a course has its one discussion forum, open to the whole course (spec 012, FR-015).
 *
 * Every published course gets one `general` forum, idnumber ltct:<slug>:discussion, in
 * section 0. It is created here if it is absent, with the name and intro the publisher
 * sends. After that, the name and intro belong to whoever runs the course: a republish
 * never touches them.
 *
 * GROUP MODE is the only thing set on an existing forum: NOGROUPS, always with
 * groupingid = 0. Shared courses are open across organisations (spec 002 R3, amended
 * 2026-10-02), so every learner in the course reads every post. A forum left in separate
 * groups once the organisation groups are gone would stop learners in no group posting,
 * which is why this and the course's own group mode change together (R3, R14). A post
 * written earlier with a groupid stays visible: under NOGROUPS the forum filters nothing
 * (mod/forum/lib.php, forum_user_can_see_group_discussion()).
 *
 * POSTS ARE NEVER WRITTEN, and no table is written directly (constitution XI):
 *
 *   group mode  \core_courseformat\formatactions::cm()->set_groupmode(), the 5.2
 *               replacement for the deprecated set_coursemodule_groupmode(). The usual path.
 *   groupingid  only when someone set a grouping by hand: update_moduleinfo(), core's only
 *               setter for it. That calls forum_update_instance(), which recalculates
 *               rating grades and so reads the forum's ratings. A deliberate trade
 *               (maintainer's choice, 2026-10-02): a public API over a raw write.
 *
 * A course whose groupmodeforce is on overrides every activity's group mode
 * (groups_get_activity_groupmode()), and a forced mode would wall the forum. The forum's own
 * setting is still written, so it is right the day the force is lifted, and `courseforced`
 * tells the publisher to say so.
 *
 * site_config.php's applier calls apply_groupmode() too, so `apply` and a publish correct a
 * hand change the same way (contracts/site-declaration.md).
 *
 * All APIs confirmed on MOODLE_502_STABLE: add_moduleinfo() (course/modlib.php, through
 * util::upsert_module), get_moduleinfo_data() and update_moduleinfo() (course/modlib.php;
 * update_moduleinfo() triggers course_module_updated itself), forum_add_instance()
 * (mod/forum/lib.php), cmactions::set_groupmode() (course/format/classes/local/cmactions.php),
 * groups_get_activity_groupmode() (lib/grouplib.php).
 */
class ensure_discussion extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber, ltct:<slug>'),
            'idnumber' => new external_value(PARAM_RAW,
                'Course-module idnumber of the forum, ltct:<slug>:discussion'),
            'name' => new external_value(PARAM_TEXT, 'Forum name; used only when creating it'),
            'intro' => new external_value(PARAM_RAW, 'Forum intro HTML; used only when creating it'),
        ]);
    }

    public static function execute(string $courseidnumber, string $idnumber, string $name,
                                   string $intro): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
            'idnumber' => $idnumber,
            'name' => $name,
            'intro' => $intro,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);

        if (strpos($params['idnumber'], $course->idnumber . ':') !== 0) {
            throw new moodle_exception('The discussion idnumber ' . $params['idnumber']
                . ' does not belong to course ' . $course->idnumber . '.');
        }

        $groupmode = self::wanted_groupmode();
        $existing = util::cm_by_idnumber((int)$course->id, $params['idnumber']);

        if (!$existing) {
            require_capability('moodle/course:manageactivities', $context);
            $result = util::upsert_module($course, 'forum', $params['idnumber'], 0, [
                'name' => $params['name'],
                'introeditor' => ['text' => $params['intro'], 'format' => FORMAT_HTML, 'itemid' => 0],
                'showdescription' => 0,
                'type' => 'general',
                // The defaults mod/forum's own test generator uses, so the forum is the one
                // the "Add an activity" form would make with nothing changed.
                'assessed' => 0,
                'scale' => 0,
                'grade_forum' => 0,
                'forcesubscribe' => FORUM_CHOOSESUBSCRIBE,
                'trackingtype' => FORUM_TRACKING_OPTIONAL,
                'groupmode' => $groupmode,
                'groupingid' => 0,
            ]);
            $cmid = $result['cmid'];
            $created = true;
            // add_moduleinfo() drops the group mode when the course forces one; set the
            // forum's own value anyway, so it is right once the force is lifted.
            self::apply_groupmode($course, $cmid, $groupmode);
        } else {
            if ($existing->modname !== 'forum') {
                throw new moodle_exception('Module ' . $params['idnumber'] . ' exists as a '
                    . $existing->modname . ', not a forum. Remove it in Moodle and republish.');
            }
            $cmid = (int)$existing->id;
            $created = false;
            require_capability('moodle/course:manageactivities', context_module::instance($cmid));
            self::apply_groupmode($course, $cmid, $groupmode);
        }

        return [
            'cmid' => $cmid,
            'created' => $created,
            'groupmode' => $groupmode,
            'courseforced' => !empty($course->groupmodeforce),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course-module id of the forum'),
            'created' => new external_value(PARAM_BOOL, 'True if created by this call'),
            'groupmode' => new external_value(PARAM_INT,
                'The group mode set on the forum: always 0, no groups'),
            'courseforced' => new external_value(PARAM_BOOL,
                'True when the course forces its own group mode over the forum\'s'),
        ]);
    }

    /**
     * The group mode every course discussion has: no groups (spec 002 R3, R14).
     *
     * @return int NOGROUPS
     */
    public static function wanted_groupmode(): int {
        return NOGROUPS;
    }

    /**
     * Set a discussion forum's group mode and clear its grouping. Touches nothing else.
     *
     * The one write path for a discussion's group mode, shared by this web service and the
     * site_config applier. Writes no discussion or post. Only the rare grouping fix reads
     * ratings, through update_moduleinfo() (see the class comment).
     *
     * @param stdClass $course
     * @param int $cmid
     * @param int $groupmode NOGROUPS, from wanted_groupmode()
     * @return bool whether anything changed
     */
    public static function apply_groupmode(stdClass $course, int $cmid, int $groupmode): bool {
        $full = get_coursemodule_from_id('forum', $cmid, $course->id, false, MUST_EXIST);
        if ((int)$full->groupingid !== 0) {
            // Core's only setter for groupingid. It also sets the group mode, and triggers
            // course_module_updated itself (course/modlib.php). The cost is that
            // forum_update_instance() re-reads the forum's ratings; accepted, since a
            // grouping on this forum is a hand change that should be rare.
            $fullcourse = get_course($course->id);
            [$cm, , , $data, ] = get_moduleinfo_data($full, $fullcourse);
            $data->groupmode = $groupmode;
            $data->groupingid = 0;
            update_moduleinfo($cm, $data, $fullcourse);
            return true;
        }

        // By id, not record: format\base::instance() reads ->format from a record, and the
        // applier's course rows carry only id, idnumber, groupmode and groupmodeforce.
        $changed = \core_courseformat\formatactions::cm((int)$course->id)->set_groupmode($cmid, $groupmode);
        if ($changed) {
            \core\event\course_module_updated::create_from_cm($full, context_module::instance($cmid))->trigger();
        }
        return $changed;
    }
}
