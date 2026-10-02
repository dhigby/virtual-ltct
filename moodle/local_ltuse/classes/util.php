<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/questionlib.php');
// completion_info and the COMPLETION_* constants. completionlib require_once()s
// completion/criteria/completion_criteria.php, which defines COMPLETION_CRITERIA_TYPE_ACTIVITY.
require_once($CFG->libdir . '/completionlib.php');

use context_course;
use context_module;
use moodle_exception;
use stdClass;

/**
 * Shared machinery for the publish endpoints.
 *
 * Everything here is a thin wrapper over the APIs the web UI itself calls. Nothing writes
 * to Moodle tables directly except where a read is genuinely just a lookup, because
 * bypassing add_moduleinfo()/update_moduleinfo() would skip grade items, completion,
 * events and the file API, and leave a course that looks right until something needs one
 * of those.
 *
 * IDENTITY. Every module the publisher creates carries a course-module idnumber of the
 * form "ltct:<slug>:<file number>", and every course an idnumber of "ltct:<slug>".
 * That is the whole idempotency story: republishing looks a module up by idnumber and
 * updates it rather than creating a second one. The state lives in Moodle, so there is no
 * repo file to keep honest and it survives someone else republishing.
 */
class util {

    /** Prefix that marks a course or module as owned by the publisher. */
    const IDNUMBER_PREFIX = 'ltct:';

    /**
     * Look up a course by its idnumber.
     *
     * @param string $idnumber
     * @return stdClass the course record
     * @throws moodle_exception if there is no such course
     */
    public static function course_by_idnumber(string $idnumber): stdClass {
        global $DB;
        $course = $DB->get_record('course', ['idnumber' => $idnumber]);
        if (!$course) {
            throw new moodle_exception('error:nocourse', 'local_ltuse', '', $idnumber);
        }
        return $course;
    }

    /**
     * The course module with this idnumber in this course, or null.
     *
     * course_modules.idnumber is the field the web UI exposes as "ID number" on every
     * activity, and it is indexed, so this is the cheap lookup the whole design rests on.
     *
     * @param int $courseid
     * @param string $idnumber
     * @return stdClass|null a course_modules record with `modname` attached
     */
    public static function cm_by_idnumber(int $courseid, string $idnumber) {
        global $DB;
        $sql = "SELECT cm.*, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid AND cm.idnumber = :idnumber";
        $record = $DB->get_record_sql($sql, ['courseid' => $courseid, 'idnumber' => $idnumber]);
        return $record ?: null;
    }

    /**
     * Every publisher-owned module in a course, keyed by idnumber.
     *
     * @param int $courseid
     * @return array idnumber => ['cmid' => int, 'modname' => string, 'section' => int,
     *                            'instance' => int, 'name' => string]
     */
    public static function owned_modules(int $courseid): array {
        global $DB;
        $sql = "SELECT cm.id AS cmid, cm.idnumber, cm.instance, cm.visible,
                       m.name AS modname, cs.section AS sectionnum
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {course_sections} cs ON cs.id = cm.section
                 WHERE cm.course = :courseid
                   AND " . $DB->sql_like('cm.idnumber', ':prefix') . "
              ORDER BY cs.section, cm.id";
        $params = [
            'courseid' => $courseid,
            'prefix' => $DB->sql_like_escape(self::IDNUMBER_PREFIX) . '%',
        ];
        $out = [];
        foreach ($DB->get_records_sql($sql, $params) as $r) {
            $out[$r->idnumber] = [
                'cmid' => (int)$r->cmid,
                'modname' => $r->modname,
                'section' => (int)$r->sectionnum,
                'instance' => (int)$r->instance,
                'visible' => (int)$r->visible,
            ];
        }
        return $out;
    }

    /**
     * Assert that an activity module is installed and enabled.
     *
     * @param string $modname
     * @return int the modules.id
     */
    public static function module_id(string $modname): int {
        global $DB;
        $id = $DB->get_field('modules', 'id', ['name' => $modname, 'visible' => 1]);
        if (!$id) {
            throw new moodle_exception('error:modulemissing', 'local_ltuse', '', $modname);
        }
        return (int)$id;
    }

    /**
     * Assert the course has this section, and return its record.
     *
     * Sections are created over REST by local_wsmanagesections, so this only checks. A
     * clear error here is better than add_moduleinfo() failing obscurely later.
     *
     * @param stdClass $course
     * @param int $sectionnum
     * @return stdClass
     */
    public static function require_section(stdClass $course, int $sectionnum): stdClass {
        global $DB;
        $section = $DB->get_record('course_sections',
            ['course' => $course->id, 'section' => $sectionnum]);
        if (!$section) {
            throw new moodle_exception('error:nosection', 'local_ltuse', '', $sectionnum);
        }
        return $section;
    }

    /**
     * Create or update one activity, addressed by course-module idnumber.
     *
     * @param stdClass $course
     * @param string $modname e.g. 'page', 'quiz', 'qbank'
     * @param string $idnumber the ltct: identity
     * @param int $sectionnum
     * @param array $fields module-specific moduleinfo fields
     * @param string $completion the payload rule (view, submit or pass), or '' to leave
     *                           completion alone, which is what an older publisher sends
     * @return array ['cmid' => int, 'instance' => int, 'created' => bool,
     *                'completion' => 'set'|'unchanged'|'differs'|'']
     */
    public static function upsert_module(stdClass $course, string $modname, string $idnumber,
                                         int $sectionnum, array $fields,
                                         string $completion = ''): array {
        $moduleid = self::module_id($modname);
        self::require_section($course, $sectionnum);

        $existing = self::cm_by_idnumber((int)$course->id, $idnumber);
        // Decided before anything is written, so a refusal (completion off, unknown rule)
        // leaves the module as it was. Only 'set' writes completion fields; on an existing
        // module that takes completionunlocked, which update_moduleinfo() requires.
        $outcome = self::completion_outcome($course, $existing, $completion);
        if ($outcome === 'set') {
            $fields = array_merge($fields, self::completion_fields($completion));
            if ($existing) {
                $fields['completionunlocked'] = 1;
            }
        }

        $moduleinfo = (object)array_merge([
            'modulename'        => $modname,
            // add_moduleinfo() writes course_modules.module straight from this, and the
            // column is NOT NULL -- so `modulename` alone is not enough. The web UI
            // supplies it from a hidden form field, which is why it is easy to miss.
            'module'            => $moduleid,
            'course'            => $course->id,
            'section'           => $sectionnum,
            'visible'           => 1,
            'visibleoncoursepage' => 1,
            // course_modules.idnumber. add_moduleinfo() reads it from `cmidnumber`, not
            // `idnumber` -- a mismatch here silently produces modules with no identity,
            // and every republish would then create duplicates.
            'cmidnumber'        => $idnumber,
            'groupmode'         => 0,
            'groupingid'        => 0,
            // No completion defaults: set_moduleinfo_defaults() supplies "not tracked" when
            // the caller sends no rule, and a rule arrives through $fields above.
        ], $fields);

        if ($existing) {
            if ($existing->modname !== $modname) {
                throw new moodle_exception(
                    'Module ' . $idnumber . ' exists as a ' . $existing->modname .
                    ', not a ' . $modname . '. Remove it in Moodle and republish.');
            }
            // get_moduleinfo_data() returns FIVE values -- [$cm, $context, $module,
            // $data, $cw] -- and it is $data that carries the editable module settings.
            // Destructuring only the first two silently hands back the context object
            // instead, which then fails much later with an empty module name.
            [$cm, , , $data, ] = get_moduleinfo_data(
                get_coursemodule_from_id($modname, $existing->id, 0, false, MUST_EXIST),
                $course);
            foreach ($fields as $k => $v) {
                $data->$k = $v;
            }
            $data->cmidnumber = $idnumber;
            $moduleinfo = update_moduleinfo($cm, $data, $course)[1];
            return ['cmid' => (int)$existing->id, 'instance' => (int)$moduleinfo->instance,
                    'created' => false, 'completion' => $outcome];
        }

        $moduleinfo = add_moduleinfo($moduleinfo, $course);
        return ['cmid' => (int)$moduleinfo->coursemodule,
                'instance' => (int)$moduleinfo->instance, 'created' => true,
                'completion' => $outcome];
    }

    /**
     * What upsert_module() will do about completion for this module (spec 004, R2).
     *
     * Read-only. The table is data-model.md "Completion state per module":
     *
     *   - no rule sent: '' -- completion is not touched;
     *   - new module: 'set' -- add_moduleinfo() writes the rule;
     *   - already carries the rule: 'unchanged';
     *   - not tracked, and not a course completion criterion: 'set' -- the update unlocks
     *     completion and writes the rule;
     *   - anything else: 'differs', and nothing about completion is written.
     *
     * WHY THE CRITERION TEST. Writing completion on an existing module goes through
     * update_moduleinfo()'s `completionunlocked`, which calls reset_all_state(). That
     * deletes the module's learner state and, if the module is an activity criterion of
     * its course, every course_completions row in the course, completed ones included
     * (delete_all_state(), lib/completionlib.php, MOODLE_502_STABLE). An untracked module
     * has no learner state, so the reset costs nothing -- unless someone turned tracking
     * off by hand while it was still a criterion. That case is reported, never unlocked.
     *
     * COMPLETION OFF. Core writes cm completion fields only when completion is enabled for
     * the site and the course (completion_info::is_enabled(), checked by both
     * add_moduleinfo() and update_moduleinfo()), and drops them silently otherwise. So a
     * rule sent while it is off is refused: reporting 'set' would claim a write that never
     * happened.
     *
     * @param stdClass $course a course record read after the publisher's course update
     * @param stdClass|null $cm the existing course_modules record, or null for a new module
     * @param string $rule view, submit, pass or ''
     * @return string 'set', 'unchanged', 'differs' or ''
     * @throws moodle_exception error:completionoff
     */
    public static function completion_outcome(stdClass $course, ?stdClass $cm,
                                              string $rule): string {
        global $DB;
        if ($rule === '') {
            return '';
        }
        completion_rule::fields($rule);
        if (!(new \completion_info($course))->is_enabled()) {
            throw new moodle_exception('error:completionoff', 'local_ltuse', '',
                $course->idnumber);
        }
        if (!$cm) {
            return 'set';
        }
        if (completion_rule::matches($cm, $rule)) {
            return 'unchanged';
        }
        if ((int)$cm->completion === COMPLETION_TRACKING_NONE
                && !$DB->record_exists('course_completion_criteria', [
                    'course' => $course->id,
                    'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
                    'moduleinstance' => $cm->id,
                ])) {
            return 'set';
        }
        return 'differs';
    }

    /**
     * The moduleinfo fields that write a rule, over a neutral base.
     *
     * The base matters on an update: get_moduleinfo_data() pre-fills completionusegrade and
     * completiongradeitemnumber from the stored row, so a leftover grade item number on an
     * untracked module would otherwise ride along into a `view` rule. With the base,
     * set_moduleinfo_defaults() derives the grade item number from completionusegrade
     * alone: 0 for submit and pass, null for view (course/modlib.php).
     *
     * @param string $rule view, submit or pass
     * @return array
     */
    private static function completion_fields(string $rule): array {
        return array_merge([
            'completionview' => 0,
            'completionusegrade' => 0,
            'completionpassgrade' => 0,
            'completiongradeitemnumber' => null,
        ], completion_rule::fields($rule));
    }

    /**
     * Resolve-or-create the course's question bank, and return its module context.
     *
     * THIS IS THE MOODLE 5.x CHANGE, and the most version-sensitive code in the plugin.
     * Before 5.0 questions lived in a category in the COURSE context, so an importer
     * could take a course id and drop questions straight in. In 5.0 the question bank
     * became its own activity module (mod_qbank) with its own module context, so the
     * sequence is now: find-or-create a qbank instance in the course, take its context,
     * find-or-create the category inside that context, then import. Quiz slots then hold
     * references into that bank rather than owning the questions.
     *
     * VERIFY ON THE TARGET INSTANCE before first use: that 'qbank' appears in the
     * {modules} table, and that a bank created this way is the one the course's
     * "Question bank" page shows. Site administration > Plugins > Activity modules.
     *
     * @param stdClass $course
     * @return array ['cmid' => int, 'instance' => int, 'context' => context_module]
     */
    public static function ensure_qbank(stdClass $course): array {
        $idnumber = self::IDNUMBER_PREFIX . 'qbank';
        $result = self::upsert_module($course, 'qbank', $idnumber, 0, [
            'name' => get_string('pluginname', 'local_ltuse'),
            'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'showdescription' => 0,
        ]);
        $result['context'] = context_module::instance($result['cmid']);
        return $result;
    }

    /**
     * Find-or-create a question category by name inside a context.
     *
     * @param \context $context the qbank module context
     * @param string $name
     * @param string $info
     * @return stdClass the question_categories record
     */
    public static function ensure_category(\context $context, string $name,
                                           string $info = ''): stdClass {
        global $DB;

        // question_get_top_category() creates the hidden top-level category for a context
        // when asked to. Every real category is a child of it.
        $top = question_get_top_category($context->id, true);

        $existing = $DB->get_record('question_categories',
            ['contextid' => $context->id, 'name' => $name, 'parent' => $top->id]);
        if ($existing) {
            return $existing;
        }

        $category = new stdClass();
        $category->parent = $top->id;
        $category->contextid = $context->id;
        $category->name = $name;
        $category->info = $info;
        $category->infoformat = FORMAT_HTML;
        $category->sortorder = 999;
        $category->stamp = make_unique_id_code();
        $category->id = $DB->insert_record('question_categories', $category);

        if (!$category->id) {
            throw new moodle_exception('error:nocategory', 'local_ltuse', '', $name);
        }
        return $category;
    }

    /**
     * Every LIVE question id in a category.
     *
     * "Live" means status = ready, and the distinction matters more than it looks.
     * question_delete_question() will not delete a question that a quiz slot still
     * references -- it marks it hidden instead, so that a learner's completed attempt
     * stays readable. So after a republish the category holds both the retired copies
     * and the freshly imported ones. Filtering on "not draft" counted both, and the
     * quiz came out with exactly twice the questions it should have.
     *
     * Hidden rows therefore accumulate by one generation per republish. That is Moodle's
     * own behaviour for in-use questions and is left alone deliberately: discarding them
     * would take attempt history with it.
     *
     * @param int $categoryid
     * @return int[] question ids, in the category's own order
     */
    public static function questions_in_category(int $categoryid): array {
        global $DB;
        $sql = "SELECT q.id
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qbe.questioncategoryid = :categoryid
                   AND qv.version = (SELECT MAX(v.version)
                                       FROM {question_versions} v
                                      WHERE v.questionbankentryid = qbe.id)
                   AND qv.status = :ready
              ORDER BY qbe.id, q.id";
        $records = $DB->get_records_sql($sql, [
            'categoryid' => $categoryid,
            'ready' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        ]);
        return array_map('intval', array_keys($records));
    }
}
