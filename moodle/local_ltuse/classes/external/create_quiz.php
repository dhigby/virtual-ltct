<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\util;
use moodle_exception;

/**
 * Create or update a mod_quiz and fill its slots from a question bank category.
 *
 * In Moodle 4.0+ a quiz slot does not own its question: it holds a REFERENCE to a
 * question bank entry, and the bank lives in the mod_qbank instance import_questions
 * created. So this function never touches question records -- it points slots at them.
 * That is also what makes a re-import safe: the questions are replaced in the bank and
 * the slots are rebuilt to match.
 *
 * VERIFY ON THE TARGET INSTANCE: quiz_add_quiz_question() is the supported helper for
 * adding a slot and is what the web UI calls. Moodle 5.x has been moving quiz structure
 * code behind \mod_quiz\structure; if this stops resolving after an upgrade, that class
 * is where the replacement lives. The pin in version.php exists to make that failure
 * loud at install time rather than silent at publish time.
 *
 * OFFLINE. Every quiz is created with allowofflineattempts = 1, or the Moodle app will not
 * download it and a consultant in the field cannot take it (spec 009, research.md R4). The
 * field has no admin default -- it belongs to quizaccess_offlineattempts, which has no
 * settings page -- and its four constraints are enforced only by that rule's form
 * validation, which add_moduleinfo() never runs. So they are asserted here instead: a quiz
 * that cannot go offline is refused, never silently created.
 *
 * GRADING. The repo's quizzes carry a pass threshold in prose ("80% (22/27) to pass"),
 * which scripts/quiz_parse.py extracts. It is applied as the activity's gradepass, so the
 * Moodle gradebook agrees with what the quiz text tells the learner.
 */
class create_quiz extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
            'idnumber' => new external_value(PARAM_RAW, 'Course-module idnumber to upsert'),
            'name' => new external_value(PARAM_TEXT, 'Quiz name'),
            'category' => new external_value(PARAM_TEXT,
                'Question category to draw every question from'),
            'section' => new external_value(PARAM_INT, 'Section number', VALUE_DEFAULT, 0),
            'intro' => new external_value(PARAM_RAW, 'Quiz intro HTML', VALUE_DEFAULT, ''),
            'thresholdpct' => new external_value(PARAM_INT,
                'Pass mark as a percentage, or 0 for none', VALUE_DEFAULT, 0),
            'shufflequestions' => new external_value(PARAM_BOOL,
                'Shuffle question order', VALUE_DEFAULT, false),
            'visible' => new external_value(PARAM_INT, 'Visible', VALUE_DEFAULT, 1),
        ]);
    }

    public static function execute(string $courseidnumber, string $idnumber, string $name,
                                   string $category, int $section = 0, string $intro = '',
                                   int $thresholdpct = 0, bool $shufflequestions = false,
                                   int $visible = 1): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
            'idnumber' => $idnumber,
            'name' => $name,
            'category' => $category,
            'section' => $section,
            'intro' => $intro,
            'thresholdpct' => $thresholdpct,
            'shufflequestions' => $shufflequestions,
            'visible' => $visible,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);

        $bank = util::ensure_qbank($course);
        $qcategory = util::ensure_category($bank['context'], $params['category']);
        $questionids = util::questions_in_category((int)$qcategory->id);
        if (!$questionids) {
            throw new moodle_exception('error:noquestions', 'local_ltuse');
        }

        // One mark per question, so the percentage the course text quotes is the
        // percentage Moodle computes.
        $count = count($questionids);
        $gradepass = $params['thresholdpct']
            ? round($count * $params['thresholdpct'] / 100, 2)
            : 0;

        $settings = self::quiz_defaults([
                'name' => $params['name'],
                'visible' => $params['visible'],
                'introeditor' => [
                    'text' => $params['intro'],
                    'format' => FORMAT_HTML,
                    'itemid' => 0,
                ],
                'showdescription' => 0,
                'grade' => $count,
                'sumgrades' => $count,
                'gradepass' => $gradepass,
                'shufflequestions' => $params['shufflequestions'] ? 1 : 0,
                'shuffleanswers' => 1,
                'questionsperpage' => 1,
                'attempts' => 0,          // unlimited; this is formative training
                'grademethod' => 1,       // QUIZ_GRADEHIGHEST
                'preferredbehaviour' => 'deferredfeedback',
                'allowofflineattempts' => 1,
            ]);
        self::assert_offline_capable($settings);

        $result = util::upsert_module($course, 'quiz', $params['idnumber'],
            $params['section'], $settings);

        $quiz = $DB->get_record('quiz', ['id' => $result['instance']], '*', MUST_EXIST);
        $quiz->cmid = $result['cmid'];

        // Rebuild the slots. Dropping them first is what keeps a republish idempotent:
        // without it, every publish would append the same questions again.
        $removed = self::clear_slots($quiz);

        foreach ($questionids as $qid) {
            quiz_add_quiz_question($qid, $quiz, 0, 1);
        }

        // quiz_update_sumgrades() is gone. Grade calculation moved into
        // mod_quiz\grade_calculator (see mod/quiz/upgrade.txt), reached through the quiz
        // settings object. Without this the quiz shows a maximum grade of zero and every
        // attempt scores 0/0, which looks like a question-import failure rather than a
        // grading one.
        \mod_quiz\quiz_settings::create($quiz->id)
            ->get_grade_calculator()
            ->recompute_quiz_sumgrades();

        return [
            'cmid' => $result['cmid'],
            'instance' => $result['instance'],
            'created' => $result['created'],
            'idnumber' => $params['idnumber'],
            'slots' => count($questionids),
            'slotsremoved' => $removed,
            'gradepass' => (float)$gradepass,
        ];
    }

    /**
     * Fill in every quiz setting the module form would normally supply.
     *
     * quiz_add_instance() writes the record straight from the moduleinfo object, so a
     * field the caller omits is inserted as NULL and overrides the column's own default.
     * The first casualty is `password` -- NOT NULL with a '' default -- which fails with
     * a bare dmlwriteexception naming a column nobody set on purpose.
     *
     * Derived from the live column metadata rather than a hardcoded list, so a column
     * added by a future Moodle release is picked up automatically. Precedence is:
     * caller's value, then the site's mod_quiz admin default, then the column default.
     * Columns with no default are left alone -- `intro` is one, and add_moduleinfo
     * derives it from introeditor.
     *
     * @param array $override the settings this publish actually cares about
     * @return array
     */
    private static function quiz_defaults(array $override): array {
        global $DB;

        $config = (array)get_config('quiz');
        $skip = ['id', 'course', 'timecreated', 'timemodified'];
        $defaults = [];

        foreach ($DB->get_columns('quiz') as $name => $column) {
            if (in_array($name, $skip, true) || array_key_exists($name, $override)) {
                continue;
            }
            if (array_key_exists($name, $config)) {
                $defaults[$name] = $config[$name];
            } else if (!empty($column->has_default)) {
                $defaults[$name] = $column->default_value;
            }
        }

        // mod_quiz's form calls the password field `quizpassword`, because a field named
        // `password` triggers browser autofill. quiz_add_instance() then does
        //     $quiz->password = $quiz->quizpassword;
        // (mod/quiz/lib.php), so setting `password` alone is silently discarded and the
        // insert fails on a NOT NULL column the caller believes it set. Supply the alias.
        $merged = array_merge($defaults, $override);
        if (!array_key_exists('quizpassword', $merged)) {
            $merged['quizpassword'] = $merged['password'] ?? '';
        }
        return $merged;
    }

    /**
     * Refuse settings that would stop the Moodle app taking this quiz offline.
     *
     * The same four conditions quizaccess_offlineattempts::validate_settings_form_fields()
     * checks (mod/quiz/accessrule/offlineattempts/rule.php). A site admin default -- a
     * quiz/timelimit, say -- reaches these settings through quiz_defaults(), and is the
     * likely way one fails.
     *
     * @param array $settings the merged quiz settings about to be saved
     */
    private static function assert_offline_capable(array $settings): void {
        $broken = [];
        if (!empty($settings['timelimit'])) {
            $broken[] = 'timelimit (' . $settings['timelimit'] . ', must be 0)';
        }
        if (($settings['subnet'] ?? '') !== '') {
            $broken[] = 'subnet (must be empty)';
        }
        if (($settings['navmethod'] ?? 'free') === 'sequential') {
            $broken[] = 'navmethod (sequential; must be free)';
        }
        if (!in_array($settings['preferredbehaviour'] ?? '', ['deferredfeedback', 'deferredcbm'], true)) {
            $broken[] = 'preferredbehaviour (' . ($settings['preferredbehaviour'] ?? '') .
                '; must be deferredfeedback or deferredcbm)';
        }
        if ($broken) {
            throw new moodle_exception('error:notoffline', 'local_ltuse', '', implode(', ', $broken));
        }
    }

    /**
     * Remove every slot from a quiz, and the question references that hang off them.
     *
     * Deliberately does not use the quiz UI's slot-removal helper: that renumbers as it
     * goes, which is wasted work when the whole list is about to be rebuilt.
     *
     * @param \stdClass $quiz
     * @return int how many slots were removed
     */
    private static function clear_slots(\stdClass $quiz): int {
        global $DB;
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], '', 'id');
        if (!$slots) {
            return 0;
        }
        foreach (array_keys($slots) as $slotid) {
            $DB->delete_records('question_references', [
                'itemid' => $slotid,
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
            ]);
            $DB->delete_records('question_set_references', [
                'itemid' => $slotid,
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
            ]);
        }
        $DB->delete_records('quiz_slots', ['quizid' => $quiz->id]);
        return count($slots);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course-module id'),
            'instance' => new external_value(PARAM_INT, 'Quiz instance id'),
            'created' => new external_value(PARAM_BOOL, 'True if created, false if updated'),
            'idnumber' => new external_value(PARAM_RAW, 'Course-module idnumber'),
            'slots' => new external_value(PARAM_INT, 'Questions now in the quiz'),
            'slotsremoved' => new external_value(PARAM_INT, 'Slots cleared before rebuilding'),
            'gradepass' => new external_value(PARAM_FLOAT, 'Pass mark in raw marks'),
        ]);
    }
}
