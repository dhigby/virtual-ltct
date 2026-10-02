<?php
namespace local_ltuse\output;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\mentoring;

/**
 * Moodle app content for local_ltuse (db/mobile.php), served by tool_mobile_get_content.
 *
 * Each method answers for the signed-in app user only, and reads through
 * local_ltuse\mentoring::for_user(), the same function as the browser page, so the app shows
 * exactly what the page shows (spec 003, research R4).
 */
class mobile {

    /**
     * Hide the menu item for anyone with no mentor and no learner.
     *
     * @param array $args from the app
     * @return array content response
     */
    public static function mentoring_init(array $args): array {
        global $USER;
        return [
            'templates' => [],
            'javascript' => '',
            'otherdata' => '',
            'disabled' => !mentoring::has_relationship((int)$USER->id),
        ];
    }

    /**
     * The Mentoring page, in Ionic markup.
     *
     * @param array $args from the app
     * @return array content response
     */
    public static function mentoring_view(array $args): array {
        global $OUTPUT, $USER;
        $data = mentoring::for_user((int)$USER->id);
        return [
            'templates' => [[
                'id' => 'main',
                'html' => $OUTPUT->render_from_template('local_ltuse/mobile_mentoring', $data),
            ]],
            'javascript' => '',
            'otherdata' => '',
        ];
    }
}
