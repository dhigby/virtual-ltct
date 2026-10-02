<?php
namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

use context_course;
use context_module;
use core_course\section_info;
use core_courseformat\formatactions;
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
     * The hidden section, always last, that holds modules the repo no longer has.
     *
     * Found by this exact name, because a section has no idnumber. It is plain text, not a
     * lang string, so a language pack or a translation can never stop the publisher from
     * recognising it. Rename it in Moodle and the next retirement starts a new one.
     */
    const RETIRED_SECTION_NAME = 'Retired: no longer in the course';

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
     * @return array ['cmid' => int, 'instance' => int, 'created' => bool]
     */
    public static function upsert_module(stdClass $course, string $modname, string $idnumber,
                                         int $sectionnum, array $fields): array {
        $moduleid = self::module_id($modname);
        $target = self::require_section($course, $sectionnum);

        $existing = self::cm_by_idnumber((int)$course->id, $idnumber);

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
            'completion'        => 0,
            'completionview'    => 0,
            'completionexpected' => 0,
        ], $fields);

        if ($existing) {
            if ($existing->modname !== $modname) {
                throw new moodle_exception(
                    'Module ' . $idnumber . ' exists as a ' . $existing->modname .
                    ', not a ' . $modname . '. Remove it in Moodle and republish.');
            }
            // update_moduleinfo() never moves a module between sections. A module whose
            // file came back from retirement sits in the Retired section; move it home
            // first, before its settings are read, so the save below sees it in its
            // lesson section and makes it visible again.
            if ((int)$existing->section !== (int)$target->id) {
                formatactions::cm($course)->move_end_section((int)$existing->id,
                    (int)$target->id);
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
                    'created' => false];
        }

        $moduleinfo = add_moduleinfo($moduleinfo, $course);
        return ['cmid' => (int)$moduleinfo->coursemodule,
                'instance' => (int)$moduleinfo->instance, 'created' => true];
    }

    /**
     * The course's Retired section, or null if it has none yet.
     *
     * @param stdClass $course
     * @return section_info|null
     */
    public static function retired_section(stdClass $course): ?section_info {
        foreach (get_fast_modinfo($course)->get_section_info_all() as $section) {
            if ($section->section != 0 && empty($section->component)
                    && $section->name === self::RETIRED_SECTION_NAME) {
                return $section;
            }
        }
        return null;
    }

    /**
     * The Retired section, created hidden at the end of the course if it is missing.
     *
     * @param stdClass $course
     * @return section_info
     */
    public static function ensure_retired_section(stdClass $course): section_info {
        if ($section = self::retired_section($course)) {
            return $section;
        }
        $record = formatactions::section($course)->create();   // 0: at the end
        $section = get_fast_modinfo($course)->get_section_info_by_id($record->id, MUST_EXIST);
        formatactions::section($course)->update($section,
            ['name' => self::RETIRED_SECTION_NAME, 'visible' => 0]);
        return get_fast_modinfo($course)->get_section_info_by_id($record->id, MUST_EXIST);
    }

    /**
     * Keep the Retired section after every lesson section.
     *
     * Lesson N lives in section N. When a course gains a lesson, the next number may be
     * the Retired section's, and naming it would turn the retired modules into a lesson.
     * So before sections are created or named, a Retired section inside the lesson range
     * is moved to a new last position. Its modules move with it.
     *
     * @param stdClass $course
     * @param int $numsections the number of lesson sections the publish needs
     */
    public static function keep_retired_last(stdClass $course, int $numsections): void {
        $retired = self::retired_section($course);
        if (!$retired || $retired->section > $numsections) {
            return;
        }
        $actions = formatactions::section($course);
        $actions->create_if_missing(range(1, $numsections + 1));
        $last = max(array_keys(get_fast_modinfo($course)->get_section_info_all()));
        if ($retired->section != $last) {
            $actions->move_at(
                get_fast_modinfo($course)->get_section_info_by_id($retired->id, MUST_EXIST),
                $last);
        }
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
