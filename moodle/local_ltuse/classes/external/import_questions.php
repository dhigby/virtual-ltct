<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\util;
use moodle_exception;
use qformat_xml;

/**
 * Import Moodle XML questions into a category in the course question bank.
 *
 * WHY MOODLE XML rather than building questions field by field: qformat_xml is stable,
 * well-tested, and understands every question type Moodle has. Constructing question
 * records directly would mean reimplementing that, badly, and re-fixing it on every
 * Moodle release. The publisher serialises the repo's parsed quizzes to XML and hands it
 * over; this function's whole job is to put it in the right place.
 *
 * THE MOODLE 5.x SEQUENCE (see util::ensure_qbank for the full note):
 *   1. resolve-or-create a mod_qbank instance in the course,
 *   2. take its module context,
 *   3. find-or-create the category inside that context,
 *   4. import.
 * A pre-5.0 importer took a course id and dropped into the course category; that path no
 * longer exists.
 *
 * REPLACE, NOT APPEND. A republish must not leave the previous run's questions behind, or
 * a course that lost a question would keep asking it. The category is emptied first.
 */
class import_questions extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
            'category' => new external_value(PARAM_TEXT,
                'Question category name inside the course question bank'),
            'xml' => new external_value(PARAM_RAW, 'Moodle XML question data'),
            'replace' => new external_value(PARAM_BOOL,
                'Delete the category\'s existing questions first', VALUE_DEFAULT, true),
        ]);
    }

    public static function execute(string $courseidnumber, string $category, string $xml,
                                   bool $replace = true): array {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
            'category' => $category,
            'xml' => $xml,
            'replace' => $replace,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $coursecontext = context_course::instance($course->id);
        self::validate_context($coursecontext);
        require_capability('local/ltuse:publish', $coursecontext);
        require_capability('moodle/question:add', $coursecontext);

        $bank = util::ensure_qbank($course);
        $qcategory = util::ensure_category($bank['context'], $params['category'],
            'Published from the LTC curriculum repository. Edits here are overwritten '
            . 'on the next publish; change the markdown instead.');

        if ($params['replace']) {
            $existing = util::questions_in_category((int)$qcategory->id);
            if ($existing) {
                // question_delete_question() refuses to remove a question still in use by
                // an attempt, and hides it instead. That is the behaviour we want: a
                // learner's completed attempt must stay readable.
                foreach ($existing as $qid) {
                    question_delete_question($qid);
                }
            }
        }

        // qformat_xml reads from a file, so the XML goes to a temp file the request owns.
        $tmpdir = make_request_directory();
        $tmpfile = $tmpdir . '/questions.xml';
        file_put_contents($tmpfile, $params['xml']);

        $qformat = new qformat_xml();
        $qformat->setCategory($qcategory);
        $qformat->setContexts([$bank['context']]);
        $qformat->setCourse($course);
        $qformat->setFilename($tmpfile);
        $qformat->setRealfilename('questions.xml');
        $qformat->setMatchgrades('error');
        // The category and context come from the arguments above, never from inside the
        // file: a payload must not be able to redirect its own questions somewhere else.
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);

        // The importer writes progress to output. Swallow it: this is a web service, and
        // stray HTML in the response body breaks the JSON the caller is parsing.
        ob_start();
        try {
            $ok = $qformat->importpreprocess()
                && $qformat->importprocess()
                && $qformat->importpostprocess();
            $noise = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (!$ok) {
            throw new moodle_exception('error:badxml', 'local_ltuse', '',
                trim(strip_tags((string)$noise)) ?: 'qformat_xml rejected the file');
        }

        $imported = util::questions_in_category((int)$qcategory->id);
        if (!$imported) {
            throw new moodle_exception('error:noquestions', 'local_ltuse');
        }

        return [
            'categoryid' => (int)$qcategory->id,
            'contextid' => (int)$bank['context']->id,
            'qbankcmid' => $bank['cmid'],
            'count' => count($imported),
            'questionids' => $imported,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'categoryid' => new external_value(PARAM_INT, 'Question category id'),
            'contextid' => new external_value(PARAM_INT, 'Question bank context id'),
            'qbankcmid' => new external_value(PARAM_INT, 'Course-module id of the qbank'),
            'count' => new external_value(PARAM_INT, 'Number of questions now in the category'),
            'questionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Question id')),
        ]);
    }
}
