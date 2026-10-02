<?php
// Harness for moodle/local_ltuse/classes/completion_rule.php and criteria_diff.php (spec 004,
// R1 and R3). Needs no Moodle: both classes are pure, and the one exception they throw is
// stubbed here. Run: php tests/criteria_harness.php
//
// It also reads the source of set_course_completion.php and fails if that file names
// either of core's wipe functions. They delete every learner's course completion, which is
// the one thing that function exists to avoid, so a refactor that reaches for them must
// fail CI rather than ship.
define('MOODLE_INTERNAL', 1);
class invalid_parameter_exception extends Exception {}

$plugin = __DIR__ . '/../moodle/local_ltuse/classes';
require $plugin . '/completion_rule.php';
require $plugin . '/criteria_diff.php';

use local_ltuse\completion_rule;
use local_ltuse\criteria_diff;

$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

// completion_rule::fields(): exactly data-model.md's "Completion rule" table, and nothing else.
// assertSame-style: keys, values and types, order-insensitive.
function same_fields(array $got, array $want): bool {
    ksort($got); ksort($want);
    return $got === $want;
}
check(same_fields(completion_rule::fields('view'),
    ['completion' => 2, 'completionview' => 1]), 'view: completion 2, completionview 1');
check(same_fields(completion_rule::fields('submit'),
    ['completion' => 2, 'completionusegrade' => 1, 'completionpassgrade' => 0]),
    'submit: completion 2, completionusegrade 1, completionpassgrade 0');
check(same_fields(completion_rule::fields('pass'),
    ['completion' => 2, 'completionusegrade' => 1, 'completionpassgrade' => 1]),
    'pass: completion 2, completionusegrade 1, completionpassgrade 1');

// An unknown value is an error, never a default. '' is unknown too: "no rule" is the
// caller's decision, not a value this class maps.
foreach (['done', '', 'VIEW', 'all', 'manual'] as $bad) {
    try {
        completion_rule::fields($bad);
        check(false, "unknown rule '$bad' throws");
    } catch (invalid_parameter_exception $e) {
        check(true, "unknown rule '$bad' throws");
    }
}

// completion_rule::matches(): "equals the rule", read from a stored cm row (strings, null item number).
function cmrow($completion, $view, $itemnumber, $pass) {
    return (object)['completion' => $completion, 'completionview' => $view,
                    'completiongradeitemnumber' => $itemnumber, 'completionpassgrade' => $pass];
}
check(completion_rule::matches(cmrow('2', '1', null, '0'), 'view'), 'matches: view row is view');
check(!completion_rule::matches(cmrow('2', '1', '0', '0'), 'view'), 'matches: view plus a grade rule is not view');
check(completion_rule::matches(cmrow('2', '0', '0', '0'), 'submit'), 'matches: grade, no pass is submit');
check(!completion_rule::matches(cmrow('2', '0', '0', '1'), 'submit'), 'matches: pass row is not submit');
check(completion_rule::matches(cmrow('2', '0', '0', '1'), 'pass'), 'matches: grade with pass is pass');
check(!completion_rule::matches(cmrow('2', '0', null, '1'), 'pass'), 'matches: no grade item is not pass');
check(!completion_rule::matches(cmrow('1', '1', null, '0'), 'view'), 'matches: manual tracking matches nothing');
check(!completion_rule::matches(cmrow('0', '0', null, '0'), 'submit'), 'matches: no tracking matches nothing');
try {
    completion_rule::matches(cmrow('2', '1', null, '0'), 'done');
    check(false, 'matches: unknown rule throws');
} catch (invalid_parameter_exception $e) {
    check(true, 'matches: unknown rule throws');
}

// criteria_diff::diff().
check(criteria_diff::diff([3, 1, 2], [1]) === ['add' => [2, 3], 'remove' => []], 'add only');
check(criteria_diff::diff([1], [1, 5, 4]) === ['add' => [], 'remove' => [4, 5]], 'remove only');
check(criteria_diff::diff([1, 2, 7], [2, 9]) === ['add' => [1, 7], 'remove' => [9]], 'add and remove');
check(criteria_diff::diff([4, 2], [2, 4]) === ['add' => [], 'remove' => []], 'equal sets: nothing to do');
check(criteria_diff::diff([], []) === ['add' => [], 'remove' => []], 'both empty');
check(criteria_diff::diff([30, 10, 20], []) === ['add' => [10, 20, 30], 'remove' => []],
    'unsorted input gives sorted output');
// The table has no unique index on (course, moduleinstance), so present can repeat a cmid.
check(criteria_diff::diff([5, 5, 6], [6, 6, 8, 8]) === ['add' => [5], 'remove' => [8]],
    'duplicated input is de-duplicated');
// DB values arrive as strings; the result is ints either way.
check(criteria_diff::diff(['12', '3'], ['3', '40']) === ['add' => [12], 'remove' => [40]],
    'string ids compared and returned as ints, sorted numerically');
$d = criteria_diff::diff([2, 1], [9, 3]);
check(array_keys($d) === ['add', 'remove'] && array_is_list($d['add']) && array_is_list($d['remove']),
    'result is [add, remove], each a list');

// set_course_completion must never call core's wipe functions.
$src = @file_get_contents($plugin . '/external/set_course_completion.php');
check($src !== false, 'set_course_completion.php exists');
check($src !== false && stripos($src, 'clear_criteria') === false,
    'set_course_completion.php does not contain clear_criteria');
check($src !== false && stripos($src, 'delete_course_completion_data') === false,
    'set_course_completion.php does not contain delete_course_completion_data');

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit($fails ? 1 : 0);
