<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
// page_update_instance() calls page_get_editor_options() whenever it saves a draft, but
// mod/page/lib.php never loads the file that defines it: the edit form does. Called from a
// web service there is no form, so without this every page update carrying files dies with
// "Call to undefined function" (MOODLE_502_STABLE, mod/page/lib.php:168).
require_once($CFG->dirroot . '/mod/page/locallib.php');

use context_course;
use context_module;
use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use file_storage;
use local_ltuse\completion_rule;
use local_ltuse\util;
use moodle_exception;
use stdClass;

/**
 * Create or update one mod_page, addressed by its course-module idnumber.
 *
 * Idempotent by construction: the idnumber is derived from the source file's number in
 * the repo, so republishing a course updates the pages that exist and creates only the ones
 * that do not. Nothing is duplicated and no state is kept outside Moodle.
 *
 * FILES. Screenshots are uploaded to the caller's draft area first (via
 * /webservice/upload.php, which returns an itemid), and that itemid is passed here as
 * `contentitemid`. mod_page's own add/update handler then moves the files out of the
 * draft area into the module's file area, which is what makes @@PLUGINFILE@@ links in
 * the HTML resolve -- and what makes images render offline in the Moodle Android app.
 * Hotlinking them instead would look identical in a browser and be blank in the field.
 *
 * REPUBLISHING TOUCHES ONLY WHAT CHANGED (spec 009, FR-016). Saving a page bumps its
 * revision, which is in every image URL on it, and re-uploading an image gives it a new
 * timemodified. Either one makes the Moodle app download that page's images again on a
 * learner's metered connection. So:
 *
 *   - If nothing differs (name, section, visibility, intro, content) and no files are to
 *     change, nothing is written at all, and the outcome is `unchanged`.
 *   - With `syncfiles`, the page's file area becomes exactly the uploaded draft plus the
 *     `keepfiles`. Kept files are copied into the draft from the page's own area, carrying
 *     the same "original" source record core's file_prepare_draft_area() writes. That is
 *     what makes file_save_draft_area_files() keep their stored record and timemodified
 *     instead of deleting and re-creating them (lib/filelib.php, MOODLE_502_STABLE).
 *     A stored file left out of both is deleted by the save.
 *
 * A caller that sends neither new parameter (an older publisher) gets the old behaviour.
 *
 * COMPLETION (spec 004, R2). `completion` is the payload's rule for this page, normally
 * `view`. util::upsert_module() decides what to do with it and reports `set`, `unchanged`
 * or `differs`; empty leaves completion alone and reports ''. The no-write short-circuit
 * above still applies, except when the rule would be `set`: an untracked page with
 * unchanged content must still be saved once, or a course published before this spec
 * would never become tracked.
 */
class create_page extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseidnumber' => new external_value(PARAM_RAW, 'Course idnumber'),
            'idnumber' => new external_value(PARAM_RAW, 'Course-module idnumber to upsert'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'content' => new external_value(PARAM_RAW, 'Page HTML'),
            'section' => new external_value(PARAM_INT, 'Section number', VALUE_DEFAULT, 0),
            'contentitemid' => new external_value(PARAM_INT,
                'Draft area itemid holding this page\'s files, or 0', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, 'Visible', VALUE_DEFAULT, 1),
            'intro' => new external_value(PARAM_RAW, 'Summary HTML', VALUE_DEFAULT, ''),
            'syncfiles' => new external_value(PARAM_BOOL,
                'Make the file area exactly the draft plus keepfiles', VALUE_DEFAULT, false),
            'keepfiles' => new external_multiple_structure(
                new external_value(PARAM_FILE, 'File name'),
                'Files already in this page to keep unchanged (read only with syncfiles)',
                VALUE_DEFAULT, []),
            'completion' => new external_value(PARAM_ALPHA,
                'Completion rule: view, submit or pass; empty leaves completion untouched',
                VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $courseidnumber, string $idnumber, string $name,
                                   string $content, int $section = 0, int $contentitemid = 0,
                                   int $visible = 1, string $intro = '',
                                   bool $syncfiles = false, array $keepfiles = [],
                                   string $completion = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
            'idnumber' => $idnumber,
            'name' => $name,
            'content' => $content,
            'section' => $section,
            'contentitemid' => $contentitemid,
            'visible' => $visible,
            'intro' => $intro,
            'syncfiles' => $syncfiles,
            'keepfiles' => $keepfiles,
            'completion' => $completion,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);

        // An unknown rule is refused before anything is read or written.
        if ($params['completion'] !== '') {
            completion_rule::fields($params['completion']);
        }

        $existing = util::cm_by_idnumber((int)$course->id, $params['idnumber']);
        $completionoutcome = ($existing && $existing->modname === 'page')
            ? util::completion_outcome($course, $existing, $params['completion'])
            : '';

        // Nothing to write. Not even update_moduleinfo(): it would bump the revision and
        // with it every image URL on the page. A caller that sent files (contentitemid)
        // without syncfiles is an older publisher replacing the area, never "unchanged".
        if ($existing && $existing->modname === 'page' && !$params['syncfiles']
                && !$params['contentitemid'] && $completionoutcome !== 'set'
                && self::is_unchanged($existing, $params)) {
            return [
                'cmid' => (int)$existing->id,
                'instance' => (int)$existing->instance,
                'created' => false,
                'outcome' => 'unchanged',
                'idnumber' => $params['idnumber'],
                'completion' => $completionoutcome,
            ];
        }

        $contentitemid = $params['contentitemid'];
        if ($params['syncfiles'] && $existing) {
            $contentitemid = self::draft_with_kept_files($existing, $contentitemid,
                $params['keepfiles']);
        } else if ($params['keepfiles'] && !$existing) {
            // A new page has no files to keep; asking for one is a publisher bug.
            throw new moodle_exception('error:nokeepfile', 'local_ltuse', '',
                reset($params['keepfiles']));
        }

        $fields = [
            'name' => $params['name'],
            'visible' => $params['visible'],
            'introeditor' => [
                'text' => $params['intro'],
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ],
            'showdescription' => 0,
            'page' => [
                'text' => $params['content'],
                'format' => FORMAT_HTML,
                // Zero means "leave the file area alone"; mod_page handles that fine.
                'itemid' => $contentitemid,
            ],
            // Match what the web UI's own defaults produce, so a page created here is
            // indistinguishable from one a person made.
            'display' => 5,              // RESOURCELIB_DISPLAY_OPEN
            'printheading' => 1,
            'printintro' => 0,
            'printlastmodified' => 1,
        ];
        $result = util::upsert_module($course, 'page', $params['idnumber'],
            $params['section'], $fields, $params['completion']);
        if ($result['created']) {
            // page_add_instance() takes the content and moves the draft's files only when
            // it is handed the edit form ("if ($mform)", mod/page/lib.php:112 and :123), and
            // add_moduleinfo() from a web service has none. So a new page is stored with no
            // content and no files. page_update_instance() reads both from the data with
            // no form, so a second, update save completes the page. The draft is untouched
            // until then. The rule goes again too, so the second save cannot read as a
            // change: the page now carries it, and the call reports 'unchanged'. The result
            // returned is the first call's, which is the one that set it.
            util::upsert_module($course, 'page', $params['idnumber'], $params['section'],
                $fields, $params['completion']);
        }

        return [
            'cmid' => $result['cmid'],
            'instance' => $result['instance'],
            'created' => $result['created'],
            'outcome' => $result['created'] ? 'created' : 'updated',
            'idnumber' => $params['idnumber'],
            'completion' => $result['completion'],
        ];
    }

    /**
     * True if saving these values would change nothing a learner or the app can see.
     *
     * Compares against what is stored. The publisher resolves sibling links before its
     * first send, so an unchanged lesson arrives byte-identical to the stored content.
     *
     * @param stdClass $cm course_modules record with modname
     * @param array $params validated parameters
     * @return bool
     */
    private static function is_unchanged(stdClass $cm, array $params): bool {
        global $DB;
        $page = $DB->get_record('page', ['id' => $cm->instance], 'id, name, intro, content',
            MUST_EXIST);
        $sectionnum = (int)$DB->get_field('course_sections', 'section', ['id' => $cm->section],
            MUST_EXIST);
        return $page->name === $params['name']
            && (string)$page->intro === $params['intro']
            && (string)$page->content === $params['content']
            && (int)$cm->visible === (int)$params['visible']
            && $sectionnum === (int)$params['section'];
    }

    /**
     * Copy the files to keep from the page's own area into the draft the save will use.
     *
     * Every name is checked before anything is copied, so a bad name writes nothing.
     *
     * @param stdClass $cm the page's course_modules record
     * @param int $draftitemid the draft holding uploaded files, or 0 to take a new one
     * @param string[] $keepfiles names to keep
     * @return int the draft itemid to save from
     */
    private static function draft_with_kept_files(stdClass $cm, int $draftitemid,
                                                  array $keepfiles): int {
        global $USER;
        $fs = get_file_storage();
        $cmcontext = context_module::instance($cm->id);

        $stored = [];
        foreach ($fs->get_area_files($cmcontext->id, 'mod_page', 'content', 0, 'filename', false)
                 as $file) {
            $stored[$file->get_filename()] = $file;
        }
        foreach ($keepfiles as $name) {
            if (!isset($stored[$name])) {
                throw new moodle_exception('error:nokeepfile', 'local_ltuse', '', $name);
            }
        }

        // A page that only lost an image has nothing uploaded. The save still needs a
        // draft, and an empty one deletes every stored file not copied into it.
        if (!$draftitemid) {
            $draftitemid = file_get_unused_draft_itemid();
        }

        $usercontext = context_user::instance($USER->id);
        foreach ($keepfiles as $name) {
            $file = $stored[$name];
            // No timemodified in the record: create_file_from_storedfile() copies the
            // stored one, which is the point.
            $draft = $fs->create_file_from_storedfile([
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
            ], $file);
            // Exactly what file_prepare_draft_area() records. Without an "original", the
            // save treats the file as deleted and re-uploaded, and gives it a new time.
            $source = new stdClass();
            $source->source = $file->get_source();
            $original = new stdClass();
            $original->contextid = $cmcontext->id;
            $original->component = 'mod_page';
            $original->filearea = 'content';
            $original->itemid = 0;
            $original->filename = $file->get_filename();
            $original->filepath = $file->get_filepath();
            $source->original = file_storage::pack_reference($original);
            $draft->set_source(serialize($source));
        }
        return $draftitemid;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course-module id'),
            'instance' => new external_value(PARAM_INT, 'Page instance id'),
            'created' => new external_value(PARAM_BOOL, 'True if created, false if updated'),
            'outcome' => new external_value(PARAM_ALPHA, 'created, updated or unchanged'),
            'idnumber' => new external_value(PARAM_RAW, 'Course-module idnumber'),
            'completion' => new external_value(PARAM_ALPHA,
                'set, unchanged or differs; empty if no completion rule was sent'),
        ]);
    }
}
