<?php
// Harness for moodle/local_ltuse/classes/learner_home_rules.php (spec 007): the pure rules the
// learner home block and the next-lesson hook share. Needs no Moodle; nothing here reads a
// database.
//
// Run locally:   php tests/learner_home_harness.php
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/learner_home_rules.php';
if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\learner_home_rules')) {
    echo "FAIL: local_ltuse\\learner_home_rules not found\n";
    echo "FAILURES: 1\n";
    exit(1);
}
$missing = 0;
foreach (['is_published_course', 'order_candidates', 'first_incomplete', 'choose', 'mode', 'next_cm', 'applies_next',
        'onward'] as $method) {
    if (!method_exists('local_ltuse\learner_home_rules', $method)) {
        echo "FAIL: local_ltuse\\learner_home_rules::$method() not found\n";
        $missing++;
    }
}
if ($missing) {
    echo "FAILURES: $missing\n";
    exit(1);
}

use local_ltuse\learner_home_rules as rules;

// is_published_course(string $idnumber): bool. A published course is ltct:<slug>, and not the
// office-hours course (contracts/learner-ui.md). Both the block's continue choice and the
// next-lesson hook ask this one question.
check(rules::is_published_course('ltct:fixture-a') === true, 'ltct:fixture-a is a published course');
check(rules::is_published_course('ltct:officehours') === false, 'the office-hours course is not');
check(rules::is_published_course('ltct:fixture-a:03') === false, 'a module idnumber is not a course');
check(rules::is_published_course('') === false, 'no idnumber is not a published course');
check(rules::is_published_course('LTCT:x') === false, 'the prefix is case-sensitive');
check(rules::is_published_course('ltct:') === false, 'ltct: with no slug is not a published course');
check(rules::is_published_course('other:x') === false, 'another prefix is not a published course');

// The continue rule (R3, data-model §2). Courses are [{id, lastaccess, enroltime, complete}];
// cms are [{id, name, url, uservisible, stealth, hasurl, tracked, complete}] in course order.
function course(int $id, int $lastaccess, int $enroltime = 0, bool $complete = false): array {
    return ['id' => $id, 'lastaccess' => $lastaccess, 'enroltime' => $enroltime, 'complete' => $complete];
}
function cm(int $id, bool $complete = false, array $over = []): array {
    return array_merge(['id' => $id, 'name' => "Fixture lesson $id", 'url' => "https://example.org/mod/page/view.php?id=$id",
        'uservisible' => true, 'stealth' => false, 'hasurl' => true, 'tracked' => true, 'complete' => $complete], $over);
}
/** @return int[] the ids of an ordered course list */
function ids(array $courses): array {
    return array_map(function($c) { return $c['id']; }, $courses);
}
/** A $cmsof over fixture arrays that records which courses it was asked for. */
function cmsof(array $bycourse, array &$asked): callable {
    return function(int $courseid) use ($bycourse, &$asked) {
        $asked[] = $courseid;
        return $bycourse[$courseid] ?? [];
    };
}

// order_candidates(): last access, most recent first (0, never accessed, last); then the most
// recent enrolment; then the lower id, so the order never depends on how the rows arrived.
check(ids(rules::order_candidates([course(1, 100), course(2, 300), course(3, 200)])) === [2, 3, 1],
    'courses are ordered by last access, most recent first');
check(ids(rules::order_candidates([course(1, 0, 900), course(2, 100, 10)])) === [2, 1],
    'a course never accessed sorts after one accessed, whatever its enrolment');
check(ids(rules::order_candidates([course(1, 0, 10), course(2, 0, 50), course(3, 0, 30)])) === [2, 3, 1],
    'among courses never accessed, the most recent enrolment comes first');
check(ids(rules::order_candidates([course(5, 100, 10), course(4, 100, 10)])) === [4, 5],
    'a full tie is broken by the lower id');
check(rules::order_candidates([]) === [], 'no courses orders to none');

// first_incomplete(): the first cm a learner can open, that completion tracks and that they have
// not completed. A hidden cm is not uservisible, and one in the Retired section is stealth (FR-013).
check(rules::first_incomplete([cm(1, true), cm(2), cm(3)])['id'] === 2, 'the first incomplete cm is chosen');
check(rules::first_incomplete([cm(1, false, ['uservisible' => false]), cm(2)])['id'] === 2,
    'a hidden cm is skipped');
check(rules::first_incomplete([cm(1, false, ['stealth' => true]), cm(2)])['id'] === 2,
    'a stealth cm (the Retired section) is skipped');
check(rules::first_incomplete([cm(1, false, ['hasurl' => false]), cm(2)])['id'] === 2,
    'a cm with no url (a label) is skipped');
check(rules::first_incomplete([cm(1, false, ['tracked' => false]), cm(2)])['id'] === 2,
    'a cm completion does not track is skipped');
check(rules::first_incomplete([cm(1, true), cm(2, true)]) === null, 'every cm complete gives null');
check(rules::first_incomplete([]) === null, 'no cms gives null');
check(rules::first_incomplete([cm(7)]) === cm(7), 'the cm is returned as given');

// choose() and mode().
// (a) Two courses, the most recently accessed one complete: the older one is chosen.
$asked = [];
$chosen = rules::choose([course(1, 200, 0, true), course(2, 100)],
    cmsof([1 => [cm(11, true)], 2 => [cm(21), cm(22)]], $asked));
check($chosen !== null && $chosen['course_id'] === 2 && $chosen['cm']['id'] === 21,
    '(a) a complete course is passed over for the older incomplete one');
check(!in_array(1, $asked, true), '(a) a complete course is never read');

// (b) The most recent course has every tracked cm complete but no course-completion record.
$asked = [];
$chosen = rules::choose([course(1, 200), course(2, 100)],
    cmsof([1 => [cm(11, true), cm(12, true), cm(13, false, ['tracked' => false])], 2 => [cm(21)]], $asked));
check($chosen !== null && $chosen['course_id'] === 2 && $chosen['cm']['id'] === 21,
    '(b) a course with every tracked cm complete is passed over for the next');

// (c) Every course complete gives null, and mode() gives done.
$asked = [];
$chosen = rules::choose([course(1, 200, 0, true), course(2, 100, 0, true)], cmsof([], $asked));
check($chosen === null, '(c) every course complete chooses nothing');
check(rules::mode(2, $chosen) === 'done', '(c) every course complete is done');

// (d) Nothing started gives start.
$asked = [];
$chosen = rules::choose([course(1, 0, 50)], cmsof([1 => [cm(11), cm(12)]], $asked));
check($chosen !== null && $chosen['cm']['id'] === 11 && $chosen['anycomplete'] === false,
    '(d) nothing started offers the first lesson');
check(rules::mode(1, $chosen) === 'start', '(d) nothing started is start');

// (e) One cm complete gives continue.
$asked = [];
$chosen = rules::choose([course(1, 100)], cmsof([1 => [cm(11, true), cm(12), cm(13)]], $asked));
check($chosen !== null && $chosen['cm']['id'] === 12 && $chosen['anycomplete'] === true,
    '(e) one lesson done offers the second');
check(rules::mode(1, $chosen) === 'continue', '(e) one cm complete is continue');
check($chosen !== null && array_keys($chosen) === ['course_id', 'cm', 'anycomplete'],
    'choose() returns {course_id, cm, anycomplete}');

// An untracked cm marked complete does not make a course started.
$asked = [];
$chosen = rules::choose([course(1, 100)], cmsof([1 => [cm(10, true, ['tracked' => false]), cm(11)]], $asked));
check($chosen !== null && $chosen['anycomplete'] === false, 'only a tracked cm counts as started');

// (f) Zero published courses gives empty.
$asked = [];
check(rules::mode(0, rules::choose([], cmsof([], $asked))) === 'empty', '(f) no published course is empty');
check(rules::mode(0, ['course_id' => 1, 'cm' => cm(1), 'anycomplete' => true]) === 'empty',
    '(f) no published course is empty, whatever was chosen');

// (g) The only published course has completion off, so it is no candidate: done. Accepted,
// because the publisher always enables completion (spec 004).
$asked = [];
check(rules::mode(1, rules::choose([], cmsof([], $asked))) === 'done',
    '(g) a published course with completion off leaves the learner done');

// (h) When the most recent course has an incomplete cm, $cmsof is called once (plan
// Performance Goals).
$asked = [];
rules::choose([course(3, 50), course(1, 300), course(2, 200)],
    cmsof([1 => [cm(11)], 2 => [cm(21)], 3 => [cm(31)]], $asked));
check($asked === [1], '(h) one course is read when the most recent has an incomplete cm');

// The walk reads courses in order and stops at the first with an incomplete cm.
$asked = [];
$chosen = rules::choose([course(1, 300), course(2, 200), course(3, 100)],
    cmsof([1 => [cm(11, true)], 2 => [cm(21)], 3 => [cm(31)]], $asked));
check($asked === [1, 2] && $chosen !== null && $chosen['course_id'] === 2,
    'the walk stops at the first course with an incomplete cm');

// The next-lesson rule (R6, data-model §4). cms are [{id, name, url, uservisible, stealth, hasurl}]
// in get_cms() order, which is core's own activity_navigation() order.
function ncm(int $id, array $over = []): array {
    return array_merge(['id' => $id, 'name' => "Fixture lesson $id", 'url' => "https://example.org/mod/page/view.php?id=$id",
        'uservisible' => true, 'stealth' => false, 'hasurl' => true], $over);
}

// next_cm(): the first cm after the current one that the learner can open, that is on the course
// page and that has a page to open; null at the end; ['absent' => true] when the current cm is
// not in the list, which the hook renders nothing for.
check(rules::next_cm([ncm(1), ncm(2), ncm(3)], 1) === ncm(2), '(1) the next visible cm is returned');
check(rules::next_cm([ncm(1), ncm(2, ['uservisible' => false]), ncm(3)], 1)['id'] === 3,
    '(2) a hidden cm is skipped');
check(rules::next_cm([ncm(1), ncm(2, ['stealth' => true]), ncm(3)], 1)['id'] === 3,
    '(3) a stealth cm (the Retired section) is skipped');
check(rules::next_cm([ncm(1), ncm(2, ['hasurl' => false]), ncm(3)], 1)['id'] === 3,
    '(4) a cm with no url (a label) is skipped');
check(rules::next_cm([ncm(1), ncm(2), ncm(3)], 3) === null, '(5) the last cm has no next');
check(rules::next_cm([ncm(1), ncm(2, ['uservisible' => false]), ncm(3, ['stealth' => true])], 1) === null,
    '(6) only hidden or locked cms follow: no next');
check(rules::next_cm([ncm(1), ncm(2)], 9) === ['absent' => true], '(7) a current cm not in the list is absent');
check(rules::next_cm([], 1) === ['absent' => true], '(7) no cms at all is absent');
// (8) and (9): certificate::last_lesson_section() places the certificate after the quiz, and it
// is locked (not uservisible) until the course is complete.
$quiz = ncm(20, ['name' => 'Fixture quiz', 'url' => 'https://example.org/mod/quiz/view.php?id=20']);
$certificate = ncm(21, ['name' => 'Fixture certificate', 'url' => 'https://example.org/mod/customcert/view.php?id=21']);
check(rules::next_cm([ncm(1), $quiz, array_merge($certificate, ['uservisible' => false])], 20) === null,
    '(8) a locked certificate after the quiz is no next, so Back to the course');
check(rules::next_cm([ncm(1), $quiz, $certificate], 20) === $certificate,
    '(9) the unlocked certificate after the quiz is the next');
check(rules::next_cm([ncm(1), ncm(2, ['uservisible' => false]), ncm(3)], 2)['id'] === 3,
    '(10) a current cm that is itself hidden (a teacher\'s view) still finds the next');

// applies_next(): only a module's own view page, in a published course. No Next in a quiz
// attempt or review, a forum discussion, on the course page, or in the office-hours course.
check(rules::applies_next('incourse', 'mod-page-view', true, 'ltct:fixture-a') === true,
    'a page view in a published course carries Next');
check(rules::applies_next('incourse', 'mod-quiz-view', true, 'ltct:fixture-a') === true,
    'a quiz view page carries Next');
check(rules::applies_next('incourse', 'mod-quiz-attempt', true, 'ltct:fixture-a') === false,
    'a quiz attempt does not');
check(rules::applies_next('incourse', 'mod-quiz-review', true, 'ltct:fixture-a') === false,
    'a quiz review does not');
check(rules::applies_next('incourse', 'mod-forum-discuss', true, 'ltct:fixture-a') === false,
    'a forum discussion does not');
check(rules::applies_next('incourse', 'mod-scheduler-view', true, 'ltct:officehours') === false,
    'the office-hours scheduler view does not');
check(rules::applies_next('course', 'course-view-topics', false, 'ltct:fixture-a') === false,
    'the course page does not');
check(rules::applies_next('incourse', 'course-view-topics', true, 'ltct:fixture-a') === false,
    'a course-view pagetype does not, whatever the layout');
check(rules::applies_next('incourse', 'mod-page-view', true, '') === false,
    'a course with no idnumber does not');
check(rules::applies_next('incourse', 'mod-page-view', false, 'ltct:fixture-a') === false,
    'a page outside a module context does not');
check(rules::applies_next('popup', 'mod-page-view', true, 'ltct:fixture-a') === false,
    'a layout other than incourse does not');
check(rules::applies_next('incourse', 'mod-page-view-extra', true, 'ltct:fixture-a') === false,
    'the pagetype must be the whole mod-<name>-view');

// onward(): the routes beneath any mode, under "Where next" (FR-008, US4). Each part is absent,
// never empty, when its source has nothing; when all three are, so is the whole section, so the
// heading is never shown alone. Pathways are [{title, nextcourse: {fullname, url} or null}],
// mentors [{fullname, url}], and community {name, url} or null.
function pathway(string $title, ?string $next): array {
    return ['title' => $title, 'nextcourse' => $next === null ? null
        : ['fullname' => $next, 'url' => 'https://example.org/course/view.php?id=' . strlen($next)]];
}
$mentor = ['fullname' => 'Fixture Mentor', 'url' => 'https://example.org/message/index.php?id=5'];
$community = ['name' => 'Fixture community', 'url' => 'https://example.org/community'];
check(rules::onward([], [], null) === null, 'nothing onward gives null, so the heading is never shown alone');
check(rules::onward([pathway('Fixture pathway', null)], [], null) === null,
    'a pathway with no next course, and nothing else, gives null');
check(rules::onward([], [$mentor], null) === ['mentors' => [$mentor]], 'only a mentor gives only the mentor');
$onward = rules::onward([pathway('Fixture pathway A', null), pathway('Fixture pathway B', 'Fixture course B')],
    [], null);
check($onward === ['pathways' => [pathway('Fixture pathway B', 'Fixture course B')]],
    'a pathway whose nextcourse is null is dropped; the rest are kept, in order, from 0');
check($onward !== null && !array_key_exists('community', $onward), 'community null is omitted');
check($onward !== null && !array_key_exists('mentors', $onward), 'no mentor is omitted, never an empty list');
check(rules::onward([pathway('Fixture pathway', 'Fixture course')], [$mentor], $community)
    === ['pathways' => [pathway('Fixture pathway', 'Fixture course')], 'mentors' => [$mentor], 'community' => $community],
    'all three present give {pathways, mentors, community}');
check(rules::onward([], [], $community) === ['community' => $community], 'only a community gives only the community');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
