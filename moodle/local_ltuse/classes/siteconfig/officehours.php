<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\officehours as sync;
use local_ltuse\util;
use moodle_exception;

/**
 * The office-hours course and its scheduler activity (spec 011, research R16, R19;
 * specs/011-events-calendar/contracts/declaration.md "Payload arrays").
 *
 * The declaration arrives as the payload's `officehours`, rendered by scripts/site_config.py
 * from moodle/site/office-hours.yaml:
 *
 *   officehours {course {idnumber, fullname, shortname, category_idnumber, summary, groupmode,
 *                        groupmodeforce},
 *                scheduler {idnumber, name, intro, groupmode, maxbookings, schedulermode,
 *                           guardtime (seconds), allownotifications, defaultslotduration,
 *                           usebookingform, grade},
 *                groups {name_template}}
 *
 * Items:
 *   officehours course      missing, changed or ok
 *   officehours activity    missing, changed or ok (the scheduler instance)
 *   officehours enrolment   missing or ok (the course's one manual instance)
 *   officehours groups      changed when the stored group name template differs
 *   officehours members     changed when memberships or enrolments are out of step with the
 *                           mentor relationships, as counts only (constitution III)
 *
 * Writes: create_course(), update_course(), util::upsert_module() (add_moduleinfo()) for a new
 * activity, set_coursemodule_name() and set_coursemodule_groupmode() for an existing one, and
 * enrol_manual's add_instance(). The scheduler's other declared columns on an existing activity
 * are written with one update_record() on `scheduler`: scheduler_update_instance() needs the
 * activity form and cannot be called without one. That write, and the read of the same row,
 * are a Principle XI exception listed in the README, re-checked by quickstart V1 on every
 * re-pin. Apply never deletes the course, the activity, a group, a slot or an appointment.
 */
class officehours {

    /** Item type. */
    const TYPE = 'officehours';

    /** The scheduler columns apply keeps declared, beside the name. */
    const COLUMNS = ['maxbookings', 'schedulermode', 'guardtime', 'allownotifications',
        'defaultslotduration', 'usebookingform', 'scale'];

    /** @var array the payload's `officehours` */
    protected $declared;

    /**
     * @param array $declared
     */
    public function __construct(array $declared) {
        $this->declared = $declared;
    }

    // --- checking (read only) ------------------------------------------------------------

    /**
     * @return array[] item results in inspector's shape. WRITES NOTHING.
     */
    public function check(): array {
        $items = [$this->check_course()];
        $course = sync::course();
        if (!$course) {
            return $items;
        }
        $items[] = $this->check_activity($course);
        $items[] = $this->check_enrolment($course);
        $items[] = $this->check_template();
        $items[] = $this->check_members();
        return $items;
    }

    /**
     * @return array the course's item
     */
    public function check_course(): array {
        $declared = (array)$this->declared['course'];
        $summary = $declared['fullname'];
        $course = sync::course();
        if (!$course) {
            return self::result('course', 'missing', $summary, null, 'apply creates it');
        }
        $differs = array_keys($this->course_changes($course));
        if ($differs) {
            return self::result('course', 'changed', $summary, (string)$course->fullname,
                'differs: ' . implode(', ', $differs));
        }
        return self::result('course', 'ok', $summary, $summary);
    }

    /**
     * @param \stdClass $course
     * @return array the scheduler's item
     */
    public function check_activity(\stdClass $course): array {
        $declared = (array)$this->declared['scheduler'];
        $cm = util::cm_by_idnumber((int)$course->id, $declared['idnumber']);
        if (!$cm) {
            return self::result('activity', 'missing', $declared['name'], null, 'apply creates it');
        }
        if ($cm->modname !== 'scheduler') {
            return self::result('activity', 'ambiguous', $declared['name'], $cm->modname,
                "{$declared['idnumber']} is a {$cm->modname}, not a scheduler; remove it in Moodle", true);
        }
        $differs = array_keys($this->activity_changes($cm));
        if ($differs) {
            return self::result('activity', 'changed', $declared['name'], 'differs', 'differs: ' . implode(', ', $differs));
        }
        return self::result('activity', 'ok', $declared['name'], $declared['name']);
    }

    /**
     * @param \stdClass $course
     * @return array the enrolment instance's item
     */
    public function check_enrolment(\stdClass $course): array {
        if (!sync::enrol_instance((int)$course->id)) {
            return self::result('enrolment', 'missing', 'manual: ' . sync::ENROL_NAME, null, 'apply adds it');
        }
        return self::result('enrolment', 'ok', 'manual: ' . sync::ENROL_NAME, 'manual: ' . sync::ENROL_NAME);
    }

    /**
     * @return array the group name template's item
     */
    public function check_template(): array {
        $declared = (string)$this->declared['groups']['name_template'];
        $stored = get_config('local_ltuse', sync::TEMPLATE_CONFIG);
        if ($stored === false) {
            return self::result('groups', 'missing', $declared, null, 'apply stores it');
        }
        if ((string)$stored !== $declared) {
            return self::result('groups', 'changed', $declared, (string)$stored, 'new groups take the new name');
        }
        return self::result('groups', 'ok', $declared, $declared);
    }

    /**
     * Whether memberships and enrolments match the mentor relationships, as counts only.
     *
     * @return array the members' item
     */
    public function check_members(): array {
        $counts = $this->pending_counts();
        $pending = array_filter($counts);
        if (!$pending) {
            return self::result('members', 'ok', 'in step with mentor relationships', 'in step');
        }
        $parts = [];
        foreach ($pending as $what => $n) {
            $parts[] = "{$what} {$n}";
        }
        return self::result('members', 'changed', 'in step with mentor relationships', implode(', ', $parts),
            'apply, or the hourly reconcile task, brings them into step');
    }

    // --- applying ------------------------------------------------------------------------

    /**
     * Create or update the course, the activity, the enrolment instance and the template, then
     * reconcile memberships.
     *
     * @param report $report
     */
    public function apply(report $report): void {
        try {
            $course = $this->apply_course($report);
            if (!$course) {
                return;
            }
            $this->apply_activity($course, $report);
            $this->apply_enrolment($course, $report);
            $this->apply_template($report);
            $item = $this->check_members();
            if ($item['result'] === 'ok') {
                $report->add_result($item);
            } else {
                sync::reconcile();
                $report->add_result($item, 'changed', 'reconciled: ' . $item['live']);
            }
        } catch (\Throwable $e) {
            $report->add('fail', 'changed', self::subject('course'), $this->declared['course']['fullname'], null,
                'Moodle refused: ' . $e->getMessage());
        }
    }

    /**
     * @param report $report
     * @return \stdClass|null the course, or null when it could not be made
     */
    protected function apply_course(report $report): ?\stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $item = $this->check_course();
        $declared = (array)$this->declared['course'];
        $categoryid = $DB->get_field('course_categories', 'id', ['idnumber' => $declared['category_idnumber']]);
        if (!$categoryid) {
            $report->add_result($item, 'fail', "its category {$declared['category_idnumber']} does not exist");
            return null;
        }
        $course = sync::course();
        if (!$course) {
            $course = create_course((object)[
                'category' => (int)$categoryid, 'fullname' => $declared['fullname'],
                'shortname' => $declared['shortname'], 'idnumber' => $declared['idnumber'],
                'summary' => $declared['summary'], 'summaryformat' => FORMAT_PLAIN,
                'groupmode' => (int)$declared['groupmode'], 'groupmodeforce' => (int)$declared['groupmodeforce'],
                'showreports' => 0, 'visible' => 1, 'enablecompletion' => 0,
            ]);
            $report->add_result($item, 'changed', 'created');
            return $course;
        }
        $changes = $this->course_changes($course);
        if (!$changes) {
            $report->add_result($item);
            return $course;
        }
        update_course((object)(['id' => $course->id] + $changes));
        $report->add_result($item, 'changed');
        return sync::course();
    }

    /**
     * @param \stdClass $course
     * @param report $report
     */
    protected function apply_activity(\stdClass $course, report $report): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        $item = $this->check_activity($course);
        $declared = (array)$this->declared['scheduler'];
        if ($item['result'] === 'ok' || $item['result'] === 'ambiguous') {
            $report->add_result($item);
            return;
        }
        if ($item['result'] === 'missing') {
            util::upsert_module($course, 'scheduler', $declared['idnumber'], 0, [
                'name' => $declared['name'], 'intro' => $declared['intro'], 'introformat' => FORMAT_PLAIN,
                'groupmode' => (int)$declared['groupmode'], 'staffrolename' => '',
                'maxbookings' => (int)$declared['maxbookings'], 'schedulermode' => $declared['schedulermode'],
                'guardtime' => (int)$declared['guardtime'], 'allownotifications' => (int)$declared['allownotifications'],
                'defaultslotduration' => (int)$declared['defaultslotduration'],
                'usebookingform' => (int)$declared['usebookingform'], 'grade' => (int)$declared['grade'],
                'bookingrouping' => -1, 'usenotes' => 1,
            ]);
            $report->add_result($item, 'changed', 'created');
            return;
        }
        $cm = util::cm_by_idnumber((int)$course->id, $declared['idnumber']);
        $changes = $this->activity_changes($cm);
        if (isset($changes['name'])) {
            set_coursemodule_name((int)$cm->id, $declared['name']);
            unset($changes['name']);
        }
        if (isset($changes['groupmode'])) {
            set_coursemodule_groupmode((int)$cm->id, (int)$declared['groupmode']);
            unset($changes['groupmode']);
        }
        if ($changes) {
            // The XI exception: scheduler_update_instance() needs the activity form.
            $DB->update_record('scheduler', (object)(['id' => (int)$cm->instance, 'timemodified' => time()] + $changes));
            rebuild_course_cache((int)$course->id, true);
        }
        $report->add_result($item, 'changed');
    }

    /**
     * @param \stdClass $course
     * @param report $report
     */
    protected function apply_enrolment(\stdClass $course, report $report): void {
        global $DB;
        $item = $this->check_enrolment($course);
        if ($item['result'] === 'ok') {
            $report->add_result($item);
            return;
        }
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            $report->add_result($item, 'fail', 'manual enrolment is not installed');
            return;
        }
        $plugin->add_instance($course, ['name' => sync::ENROL_NAME, 'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => (int)$DB->get_field('role', 'id', ['shortname' => 'student'])]);
        $report->add_result($item, 'changed', 'added');
    }

    /**
     * @param report $report
     */
    protected function apply_template(report $report): void {
        $item = $this->check_template();
        if ($item['result'] === 'ok') {
            $report->add_result($item);
            return;
        }
        set_config(sync::TEMPLATE_CONFIG, (string)$this->declared['groups']['name_template'], 'local_ltuse');
        $report->add_result($item, 'changed');
    }

    // --- comparing -----------------------------------------------------------------------

    /**
     * The course fields that differ from the declaration, as update_course() takes them.
     *
     * @param \stdClass $course
     * @return array field => declared value
     */
    protected function course_changes(\stdClass $course): array {
        global $DB;
        $declared = (array)$this->declared['course'];
        $want = ['fullname' => $declared['fullname'], 'shortname' => $declared['shortname'],
            'summary' => $declared['summary'], 'groupmode' => (int)$declared['groupmode'],
            'groupmodeforce' => (int)$declared['groupmodeforce']];
        $categoryid = $DB->get_field('course_categories', 'id', ['idnumber' => $declared['category_idnumber']]);
        if ($categoryid) {
            $want['category'] = (int)$categoryid;
        }
        $changes = [];
        foreach ($want as $field => $value) {
            if ((string)$course->$field !== (string)$value) {
                $changes[$field] = $value;
            }
        }
        return $changes;
    }

    /**
     * The scheduler settings that differ from the declaration.
     *
     * @param \stdClass $cm the course module, with instance and groupmode
     * @return array column => declared value; `name` and `groupmode` included
     */
    protected function activity_changes(\stdClass $cm): array {
        global $DB;
        $declared = (array)$this->declared['scheduler'];
        $live = $DB->get_record('scheduler', ['id' => (int)$cm->instance]);   // The XI exception, read side.
        $want = ['maxbookings' => (int)$declared['maxbookings'], 'schedulermode' => $declared['schedulermode'],
            'guardtime' => (int)$declared['guardtime'], 'allownotifications' => (int)$declared['allownotifications'],
            'defaultslotduration' => (int)$declared['defaultslotduration'],
            'usebookingform' => (int)$declared['usebookingform'], 'scale' => (int)$declared['grade']];
        $changes = [];
        if (!$live || (string)$live->name !== (string)$declared['name']) {
            $changes['name'] = $declared['name'];
        }
        if ((int)$cm->groupmode !== (int)$declared['groupmode']) {
            $changes['groupmode'] = (int)$declared['groupmode'];
        }
        foreach ($want as $column => $value) {
            if (!$live || (string)$live->$column !== (string)$value) {
                $changes[$column] = $value;
            }
        }
        return $changes;
    }

    /**
     * What a reconcile would change now, as counts.
     *
     * @return array<string, int>
     */
    protected function pending_counts(): array {
        // A dry run: the same reads and diff as officehours::reconcile(), and no writes.
        $course = sync::course();
        $instance = $course ? sync::enrol_instance((int)$course->id) : null;
        if (!$course || !$instance) {
            return [];
        }
        return sync::preview($course, $instance);
    }

    /**
     * @param string $part
     * @return string
     */
    public static function subject(string $part): string {
        return self::TYPE . ' ' . $part;
    }

    /**
     * @param string $part
     * @param string $result
     * @param mixed $declared
     * @param mixed $live
     * @param string $message
     * @param bool $blocking
     * @return array
     */
    protected static function result(string $part, string $result, $declared = null, $live = null,
            string $message = '', bool $blocking = false): array {
        return ['type' => self::TYPE, 'item' => self::subject($part), 'result' => $result, 'declared' => $declared,
            'live' => $live, 'message' => $message, 'secret' => false, 'blocking' => $blocking];
    }
}
