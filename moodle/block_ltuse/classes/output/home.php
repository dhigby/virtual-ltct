<?php
namespace block_ltuse\output;

defined('MOODLE_INTERNAL') || die();

use local_ltuse\learner_home;
use moodle_url;

/**
 * The learner home block's template context, built from local_ltuse\learner_home::state().
 *
 * The web block (block_ltuse::get_content()) and the app handler both render this one context,
 * so they show the same mode, the same lesson and the same words. It lives here, autoloaded,
 * because block_ltuse.php is not, and an app request could not reach a method on it.
 *
 * Every visible word is a lang string (FR-012). Names arrive format_string()ed from
 * learner_home, so the strings that carry one (coursename, label) are HTML and the template
 * outputs them unescaped; every other string is plain text and is escaped there. A mentor's
 * name is fullname(), plain text, so it is s()ed here before it joins an onward label, and every
 * onward label is HTML too.
 */
final class home {

    /**
     * @param array $state learner_home::state()
     * @return array ['route' => ?{coursename, label, url}, 'empty' => ?{text, who, supportlabel,
     *     supporturl}, 'done' => ?{text}, 'onward' => ?{heading, pathways: [{label, url}],
     *     mentors: [{label, url}], community: ?{label, url}}]; exactly one of route, empty and
     *     done is set, and onward beneath any of them, or null when learner_home has no route
     */
    public static function context(array $state): array {
        $context = ['route' => null, 'empty' => null, 'done' => null, 'onward' => self::onward($state['onward'])];
        switch ($state['mode']) {
            case learner_home::MODE_CONTINUE:
            case learner_home::MODE_START:
                $context['route'] = [
                    'coursename' => get_string('coursename', 'block_ltuse', $state['course']['fullname']),
                    'label' => $state['mode'] === learner_home::MODE_CONTINUE
                        ? get_string('continue', 'block_ltuse', $state['cm']['name'])
                        : get_string('start', 'block_ltuse', $state['cm']['name']),
                    'url' => $state['cm']['url'],
                ];
                break;
            case learner_home::MODE_EMPTY:
                // No course yet: say who to ask, and link core's Contact site support form (R9).
                $context['empty'] = [
                    'text' => get_string('empty', 'block_ltuse'),
                    'who' => get_string('empty:who', 'block_ltuse'),
                    'supportlabel' => get_string('contactsupport', 'block_ltuse'),
                    'supporturl' => (new moodle_url('/user/contactsitesupport.php'))->out(false),
                ];
                break;
            default:
                $context['done'] = ['text' => get_string('done', 'block_ltuse')];
        }
        return $context;
    }

    /**
     * The "Where next" section (FR-008): one line per pathway, per mentor, and the community
     * line when set. learner_home_rules::onward() has already left out each empty part, and
     * returns null when all are, so the heading is never shown alone.
     *
     * @param array|null $onward learner_home::state()['onward']
     * @return array|null {heading, pathways, mentors, community}, or null
     */
    private static function onward(?array $onward): ?array {
        if ($onward === null) {
            return null;
        }
        $pathways = [];
        foreach ($onward['pathways'] ?? [] as $pathway) {
            $pathways[] = [
                'label' => get_string('onward:pathway', 'block_ltuse', $pathway['nextcourse']['fullname']),
                'url' => $pathway['nextcourse']['url'],
            ];
        }
        $mentors = [];
        foreach ($onward['mentors'] ?? [] as $mentor) {
            $mentors[] = [
                'label' => get_string('onward:mentor', 'block_ltuse', s($mentor['fullname'])),
                'url' => $mentor['url'],
            ];
        }
        $community = null;
        if (!empty($onward['community'])) {
            $community = [
                'label' => get_string('onward:community', 'block_ltuse', s($onward['community']['name'])),
                'url' => $onward['community']['url'],
            ];
        }
        return [
            'heading' => get_string('onward', 'block_ltuse'),
            'pathways' => $pathways,
            'mentors' => $mentors,
            'community' => $community,
        ];
    }
}
