<?php
// Harness for moodle/local_ltuse/classes/organisation/access.php (spec 002 amendment, R10).
// Needs no Moodle: the class is pure. Keys are fixture-*, never an instance-test key.
//
// Run locally:        php tests/org_access_harness.php
// Run on a host with no checkout, as profile_access_harness.php does, with
// $LTCT_ORG_ACCESS_SRC holding the class source.
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/organisation/access.php';
if (isset($LTCT_ORG_ACCESS_SRC)) {
    eval(preg_replace('/^\s*<\?php/', '', $LTCT_ORG_ACCESS_SRC));
} else if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\organisation\access')) {
    echo "FAIL: local_ltuse\\organisation\\access not found\n";
    echo "FAILURES: 1\n";
    exit(1);
}

use local_ltuse\organisation\access;
const V = 10;
$a = ['fixture-a'];
$ab = ['fixture-a', 'fixture-b'];

/** A learner of fixture-a who is in its cohort and is nobody special. */
function learner(array $over = []): array {
    return array_merge(['id' => 20, 'ltct_org' => 'fixture-a', 'org_cohorts' => ['fixture-a'],
        'deleted' => false, 'siteadmin' => false, 'coursecontact' => false, 'highrole' => false,
        'managers' => false, 'mentor' => false], $over);
}

// --- is_org_member_of_manager ------------------------------------------------------------
check(access::is_org_member_of_manager(V, $a, learner()), 'own organisation, in the cohort');
check(!access::is_org_member_of_manager(V, $a, learner(['id' => V])), 'never oneself');
check(!access::is_org_member_of_manager(V, [], learner()), 'managing no organisation');
check(!access::is_org_member_of_manager(V, $a, learner(['ltct_org' => 'fixture-b',
    'org_cohorts' => ['fixture-b']])), 'another organisation');
check(access::is_org_member_of_manager(V, $ab, learner(['ltct_org' => 'fixture-b',
    'org_cohorts' => ['fixture-b']])), 'manager of two organisations, the second');
check(!access::is_org_member_of_manager(V, $a, learner(['org_cohorts' => []])),
    'field set but not yet in the cohort');
check(!access::is_org_member_of_manager(V, $a, learner(['org_cohorts' => ['fixture-b']])),
    'field says A, cohort says B: they must agree');
check(!access::is_org_member_of_manager(V, $a, learner(['ltct_org' => '', 'org_cohorts' => ['fixture-a']])),
    'empty field');
check(!access::is_org_member_of_manager(V, $a, learner(['ltct_org' => '  '])), 'field of only spaces');
check(!access::is_org_member_of_manager(V, $a, learner(['ltct_org' => 'Fixture-A',
    'org_cohorts' => ['Fixture-A']])), 'keys compare exactly, case included');
check(!access::is_org_member_of_manager(V, $a, learner(['ltct_org' => 'fixture-ab',
    'org_cohorts' => ['fixture-ab']])), 'a key that only starts with a managed key');
foreach (['coursecontact', 'highrole', 'managers', 'mentor', 'siteadmin'] as $k) {
    check(access::is_org_member_of_manager(V, $a, learner([$k => true])),
        "member predicate ignores role: $k");
}
check(!access::is_org_member_of_manager(V, $a, ['ltct_org' => 'fixture-a', 'org_cohorts' => ['fixture-a']]),
    'no id: fails closed');

// --- may_manage_account ------------------------------------------------------------------
check(access::may_manage_account(V, $a, learner()), 'a plain learner of their organisation');
check(!access::may_manage_account(V, $a, learner(['org_cohorts' => []])),
    'field set but not yet in the cohort');
check(!access::may_manage_account(V, $a, learner(['ltct_org' => 'fixture-b', 'org_cohorts' => ['fixture-b']])),
    'another organisation');
foreach (['deleted' => 'a deleted user', 'siteadmin' => 'a site admin',
          'coursecontact' => 'a course contact (teacher)', 'highrole' => 'a system or category role',
          'managers' => 'a managers-cohort member', 'mentor' => 'an ltct:mentors member'] as $k => $label) {
    check(!access::may_manage_account(V, $a, learner([$k => true])), "refused: $label");
    $missing = learner();
    unset($missing[$k]);
    check(!access::may_manage_account(V, $a, $missing), "fact '$k' not supplied: fails closed");
}
check(!access::may_manage_account(V, $a, learner(['id' => V])), 'never oneself');

// --- per-action rules --------------------------------------------------------------------
$p = learner();
check(access::may_enrol_into($p, 'ltct:fixture-course', 'ltct:published'), 'enrol: published course');
check(access::may_enrol_into($p, 'ltct:fixture-course', 'ltct:org:fixture-a'),
    "enrol: the learner's own organisation's course");
check(!access::may_enrol_into($p, 'ltct:fixture-course', 'ltct:pilots'), 'enrol: never a pilot course');
check(!access::may_enrol_into($p, 'ltct:fixture-course', 'ltct:org:fixture-b'),
    "enrol: never another organisation's category, even one the manager also manages");
check(!access::may_enrol_into($p, 'ltct:fixture-course', 'ltct:organisations'), 'enrol: not the parent category');
check(!access::may_enrol_into($p, 'fixture-course', 'ltct:published'), 'enrol: only a course this repo publishes');
check(!access::may_enrol_into($p, 'ltct:', 'ltct:published'), 'enrol: an empty slug is not a course');
check(!access::may_enrol_into($p, 'ltct:fixture-course:03', 'ltct:published'),
    'enrol: a module idnumber is not a course');
check(!access::may_enrol_into(learner(['ltct_org' => '']), 'ltct:fixture-course', 'ltct:org:'),
    'enrol: empty organisation never matches ltct:org:');
check(!access::may_enrol_into($p, 'ltct:fixture-course', ''), 'enrol: a category with no idnumber');

check(access::may_unenrol_from('self', access::ENROL_MARKER), 'unenrol: the organisation-enrolment instance');
check(!access::may_unenrol_from('self', null), 'unenrol: another self-enrolment instance');
check(!access::may_unenrol_from('self', ''), 'unenrol: a self instance with an empty marker');
check(!access::may_unenrol_from('cohort', access::ENROL_MARKER), 'unenrol: never a cohort-sync enrolment');
check(!access::may_unenrol_from('manual', access::ENROL_MARKER), 'unenrol: never a manual (pilot) enrolment');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
