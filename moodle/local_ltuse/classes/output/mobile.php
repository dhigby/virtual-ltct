<?php
namespace local_ltuse\output;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\mentoring;
use local_ltuse\pathway\assignments;
use local_ltuse\pathway\catalogue;
use local_ltuse\pathway\view;

/**
 * Moodle app content for local_ltuse (db/mobile.php), served by tool_mobile_get_content.
 *
 * Each method answers for the signed-in app user only, and reads through
 * local_ltuse\mentoring::for_user(), the same function as the browser page, so the app shows
 * exactly what the page shows (spec 003, research R4). Pathways read through
 * local_ltuse\pathway\view, as /local/ltuse/pathways.php does (spec 006, R10).
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

    /**
     * Pathways is shown to every signed-in user (spec 006, R10; FR-009, FR-015).
     *
     * @param array $args from the app
     * @return array content response
     */
    public static function pathways_init(array $args): array {
        return [
            'templates' => [],
            'javascript' => '',
            'otherdata' => '',
            'disabled' => false,
        ];
    }

    /**
     * The app user's own pathways, or one of them in full, in Ionic markup (contracts/pages.md
     * "Moodle app"). Reads through local_ltuse\pathway\view, as the page does, so the two
     * cannot differ. Takes no userid: in the app a person sees only their own pathways
     * (FR-015). With no key it lists the user's pathways, then every pathway to browse.
     *
     * @param array $args from the app: key, optional
     * @return array content response
     */
    public static function pathways_view(array $args): array {
        global $OUTPUT, $USER;
        $userid = (int)$USER->id;
        $key = isset($args['key']) && is_string($args['key']) ? $args['key'] : '';

        $data = ['nolevels' => false, 'unknown' => false, 'haspathway' => false, 'list' => false];
        if (view::levels() === null) {
            $data['nolevels'] = true;
        } else if ($key !== '') {
            $context = catalogue::parse_key($key) === null ? null : view::for_learner($key, $userid);
            if ($context === null) {
                $data['unknown'] = true;
            } else {
                $data['haspathway'] = true;
                $data['pathway'] = view::display($context);
            }
        } else {
            $mine = view::summaries($userid, assignments::pathways_for_user($userid));
            $data['list'] = true;
            $data['hasmine'] = (bool)$mine;
            $data['mine'] = $mine;
            $data['none'] = !$mine;
            $data['all'] = view::browse();
        }
        return [
            'templates' => [[
                'id' => 'main',
                'html' => $OUTPUT->render_from_template('local_ltuse/mobile_pathways', $data),
            ]],
            'javascript' => '',
            'otherdata' => '',
        ];
    }
}
