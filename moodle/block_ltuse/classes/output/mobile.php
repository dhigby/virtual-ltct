<?php
namespace block_ltuse\output;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\learner_home;

/**
 * Moodle app content for block_ltuse (db/mobile.php), served by tool_mobile_get_content.
 *
 * The app's Home tab lists the Dashboard's blocks through core_block_get_dashboard_blocks, which
 * leaves out a block whose web content is empty, and then asks this class for each one it shows.
 * It answers for the signed-in app user only ($USER, the token's user), never for the userid the
 * app sends among its args. It renders \block_ltuse\output\home::context() from
 * local_ltuse\learner_home::state(), as block_ltuse::get_content() does, so the app and the web
 * show the same mode, the same lesson and the same words (R7). Only the template differs: Ionic
 * markup, plus the two offline hints the web does not need (R8).
 */
class mobile {

    /**
     * The learner home block on the app's Home tab, in Ionic markup.
     *
     * @param array $args from the app: contextlevel, instanceid, blockid and the app's defaults;
     *     none is read
     * @return array content response
     */
    public static function mobile_block_view(array $args): array {
        global $OUTPUT, $USER;
        $html = '';
        if (!isguestuser() && learner_home::applies((int)$USER->id)) {
            $html = $OUTPUT->render_from_template('block_ltuse/mobile_block',
                home::context(learner_home::state((int)$USER->id)) + ['app' => true]);
        }
        return [
            'templates' => [[
                'id' => 'main',
                'html' => $html,
            ]],
            'javascript' => '',
            'otherdata' => '',
        ];
    }
}
