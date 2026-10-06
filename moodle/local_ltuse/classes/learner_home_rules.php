<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

/**
 * The pure rules of the learner experience (spec 007, contracts/learner-ui.md).
 *
 * block_ltuse's continue choice (learner_home) and the next-lesson hook (hook_callbacks) each
 * ask these questions, so they cannot answer them differently. Every method is static and
 * pure: no Moodle function, no $DB and no Moodle constant, so tests/learner_home_harness.php
 * tests it without Moodle.
 */
final class learner_home_rules {

    /**
     * The office-hours course's idnumber. A copy of officehours::COURSE, because requiring
     * officehours.php would pull in Moodle; change the two together.
     */
    const OFFICEHOURS_COURSE = 'ltct:officehours';

    /**
     * A published course is one the publisher made: its idnumber is ltct:<slug>, with no
     * further part (ltct:<slug>:<number> is a module), and it is not the office-hours course.
     *
     * @param string $idnumber the course's idnumber
     * @return bool
     */
    public static function is_published_course(string $idnumber): bool {
        return preg_match('/^ltct:[^:]+$/D', $idnumber) === 1 && $idnumber !== self::OFFICEHOURS_COURSE;
    }

    /**
     * The order the continue choice walks the learner's courses in (R3): the one they opened
     * most recently first, a course never opened (lastaccess 0) last; then the most recent
     * enrolment, so a learner who has opened nothing starts the course they were given last;
     * then the lower id, so the order never depends on how the rows arrived.
     *
     * @param array $courses [{id, lastaccess, enroltime, complete}]
     * @return array the same courses, ordered
     */
    public static function order_candidates(array $courses): array {
        usort($courses, function(array $a, array $b): int {
            return [(int)$b['lastaccess'], (int)$b['enroltime'], (int)$a['id']]
                <=> [(int)$a['lastaccess'], (int)$a['enroltime'], (int)$b['id']];
        });
        return $courses;
    }

    /**
     * The lesson to offer in one course: the first cm, in course order, that the learner can
     * open, that is on the course page, that has a page to open, that completion tracks and
     * that they have not completed. A hidden cm is not uservisible and one in the Retired
     * section is stealth, so neither is ever offered (FR-013).
     *
     * @param array $cms [{id, name, url, uservisible, stealth, hasurl, tracked, complete}], in course order
     * @return array|null that cm, as given, or null when there is none
     */
    public static function first_incomplete(array $cms): ?array {
        foreach ($cms as $cm) {
            if ($cm['uservisible'] && !$cm['stealth'] && $cm['hasurl'] && $cm['tracked'] && !$cm['complete']) {
                return $cm;
            }
        }
        return null;
    }

    /**
     * The continue choice (R3): walk the courses in order_candidates() order, pass over a
     * complete one, and stop at the first with a lesson to offer. A course whose every tracked
     * cm is complete, but which has no course-completion record, is passed over too.
     *
     * $cmsof is asked for one course's cms only as the walk reaches it, so a learner on one
     * course costs one read (plan Performance Goals). It keeps this class pure: the harness
     * passes fixtures, learner_home passes a closure that reads Moodle.
     *
     * @param array $courses [{id, lastaccess, enroltime, complete}], completion-on courses only
     * @param callable $cmsof function(int $courseid): array, the first_incomplete() input for that course
     * @return array|null ['course_id' => int, 'cm' => array, 'anycomplete' => bool], or null
     *     when no course has a lesson to offer; anycomplete is whether any tracked cm in that
     *     course is complete
     */
    public static function choose(array $courses, callable $cmsof): ?array {
        foreach (self::order_candidates($courses) as $course) {
            if ($course['complete']) {
                continue;
            }
            $cms = $cmsof((int)$course['id']);
            $cm = self::first_incomplete($cms);
            if ($cm === null) {
                continue;
            }
            $anycomplete = false;
            foreach ($cms as $other) {
                if ($other['tracked'] && $other['complete']) {
                    $anycomplete = true;
                    break;
                }
            }
            return ['course_id' => (int)$course['id'], 'cm' => $cm, 'anycomplete' => $anycomplete];
        }
        return null;
    }

    /**
     * Which of the block's modes this learner is in (data-model §2).
     *
     * A published course with completion off is counted in $activecount but is no candidate
     * for choose(), so a learner whose only course has completion off is done. Accepted,
     * because the publisher always enables completion (spec 004).
     *
     * @param int $activecount how many published courses the learner has (learner_home::published_courses())
     * @param array|null $chosen what choose() returned
     * @return string empty, done, continue or start
     */
    public static function mode(int $activecount, ?array $chosen): string {
        if ($activecount === 0) {
            return 'empty';
        }
        if ($chosen === null) {
            return 'done';
        }
        return $chosen['anycomplete'] ? 'continue' : 'start';
    }

    /**
     * The lesson after this one (R6, data-model §4): the first cm after $currentid, in
     * get_cms() order, that the learner can open, that is not stealth and that has a page to
     * open. That is core's own activity_navigation() rule, which Boost's course index hides.
     * A locked certificate (not uservisible) after the quiz is passed over, so the quiz's page
     * offers Back to the course. The current cm itself need not be visible: a teacher looking
     * at a hidden lesson still finds the next.
     *
     * @param array $cms [{id, name, url, uservisible, stealth, hasurl}], in get_cms() order
     * @param int $currentid the cm the page shows
     * @return array|null the next cm, as given; null when there is none, so Back to the course;
     *     ['absent' => true] when $currentid is not in $cms, for which the hook renders nothing
     */
    public static function next_cm(array $cms, int $currentid): ?array {
        $found = false;
        foreach ($cms as $cm) {
            if (!$found) {
                $found = (int)$cm['id'] === $currentid;
                continue;
            }
            if ($cm['uservisible'] && !$cm['stealth'] && $cm['hasurl']) {
                return $cm;
            }
        }
        return $found ? null : ['absent' => true];
    }

    /**
     * Whether a page carries the next-lesson button (contracts/learner-ui.md): a module's own
     * view page (incourse, mod-<name>-view, in a module context) in a published course. Not
     * every incourse page, so there is no Next in a quiz attempt, a quiz review or a forum
     * discussion, and none in the office-hours course or a course somebody else made.
     *
     * @param string $pagelayout $PAGE->pagelayout
     * @param string $pagetype $PAGE->pagetype
     * @param bool $modulecontext whether $PAGE->context is a module context
     * @param string $idnumber the course's idnumber
     * @return bool
     */
    public static function applies_next(string $pagelayout, string $pagetype, bool $modulecontext,
            string $idnumber): bool {
        return $pagelayout === 'incourse'
            && preg_match('/^mod-[a-z0-9]+-view$/D', $pagetype) === 1
            && $modulecontext
            && self::is_published_course($idnumber);
    }

    /**
     * The routes beneath any mode, under "Where next" (FR-008, contracts/learner-ui.md). Each
     * part is absent, never empty, when its source has nothing, and a pathway with no next
     * course is no route. When nothing is left, the whole section is null, so the heading is
     * never shown alone.
     *
     * @param array $pathways [{title, nextcourse: {fullname, url} or null}]
     * @param array $mentors [{fullname, url}]
     * @param array|null $community {name, url}, or null until spec 005 declares one
     * @return array|null ['pathways' => array, 'mentors' => array, 'community' => array], with
     *     each empty part left out, or null when every part is
     */
    public static function onward(array $pathways, array $mentors, ?array $community): ?array {
        $pathways = array_values(array_filter($pathways, function(array $pathway): bool {
            return !empty($pathway['nextcourse']);
        }));
        $onward = array_filter(['pathways' => $pathways, 'mentors' => array_values($mentors),
            'community' => $community]);
        return $onward ?: null;
    }
}
