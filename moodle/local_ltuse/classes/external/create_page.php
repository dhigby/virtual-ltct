<?php
namespace local_ltuse\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltuse\util;

/**
 * Create or update one mod_page, addressed by its course-module idnumber.
 *
 * Idempotent by construction: the idnumber is derived from the source filename in the
 * repo, so republishing a course updates the pages that exist and creates only the ones
 * that do not. Nothing is duplicated and no state is kept outside Moodle.
 *
 * FILES. Screenshots are uploaded to the caller's draft area first (via
 * /webservice/upload.php, which returns an itemid), and that itemid is passed here as
 * `contentitemid`. mod_page's own add/update handler then moves the files out of the
 * draft area into the module's file area, which is what makes @@PLUGINFILE@@ links in
 * the HTML resolve -- and what makes images render offline in the Moodle Android app.
 * Hotlinking them instead would look identical in a browser and be blank in the field.
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
        ]);
    }

    public static function execute(string $courseidnumber, string $idnumber, string $name,
                                   string $content, int $section = 0, int $contentitemid = 0,
                                   int $visible = 1, string $intro = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseidnumber' => $courseidnumber,
            'idnumber' => $idnumber,
            'name' => $name,
            'content' => $content,
            'section' => $section,
            'contentitemid' => $contentitemid,
            'visible' => $visible,
            'intro' => $intro,
        ]);

        $course = util::course_by_idnumber($params['courseidnumber']);
        $context = context_course::instance($course->id);
        self::validate_context($context);
        require_capability('local/ltuse:publish', $context);

        $result = util::upsert_module($course, 'page', $params['idnumber'],
            $params['section'], [
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
                    // Zero means "no files for this page"; mod_page handles that fine.
                    'itemid' => $params['contentitemid'],
                ],
                // Match what the web UI's own defaults produce, so a page created here is
                // indistinguishable from one a person made.
                'display' => 5,              // RESOURCELIB_DISPLAY_OPEN
                'printheading' => 1,
                'printintro' => 0,
                'printlastmodified' => 1,
            ]);

        return [
            'cmid' => $result['cmid'],
            'instance' => $result['instance'],
            'created' => $result['created'],
            'idnumber' => $params['idnumber'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course-module id'),
            'instance' => new external_value(PARAM_INT, 'Page instance id'),
            'created' => new external_value(PARAM_BOOL, 'True if created, false if updated'),
            'idnumber' => new external_value(PARAM_RAW, 'Course-module idnumber'),
        ]);
    }
}
