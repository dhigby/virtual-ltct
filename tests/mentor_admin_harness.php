<?php
// Harness for moodle/local_ltuse/classes/mentor_admin.php (spec 003, research R7 Phase B, as
// amended by spec 002 research R10). Needs no Moodle: decide() is pure. It defers its manager
// branch to local_ltuse\organisation\access::may_manage_account(), so both classes are loaded.
// Keys are fixture-*, never an instance-test key.
//
// Run locally:        php tests/mentor_admin_harness.php
// Run on a host with no checkout, as profile_access_harness.php does, with
// $LTCT_ORG_ACCESS_SRC and $LTCT_MENTOR_ADMIN_SRC holding the two class sources.
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$sources = [
    'LTCT_ORG_ACCESS_SRC' => __DIR__ . '/../moodle/local_ltuse/classes/organisation/access.php',
    'LTCT_MENTOR_ADMIN_SRC' => __DIR__ . '/../moodle/local_ltuse/classes/mentor_admin.php',
];
foreach ($sources as $var => $path) {
    if (isset($$var)) {
        eval(preg_replace('/^\s*<\?php/', '', $$var));
    } else if (is_file($path)) {
        require $path;
    }
}
if (!class_exists('local_ltuse\organisation\access') || !class_exists('local_ltuse\mentor_admin') ||
        !method_exists('local_ltuse\mentor_admin', 'decide')) {
    echo "FAIL: local_ltuse\\mentor_admin::decide() or local_ltuse\\organisation\\access not found\n";
    echo "FAILURES: 1\n";
    exit(1);
}

use local_ltuse\mentor_admin;
// decide(int $viewerid, bool $learnerexists, bool $canassigncore, array $managedkeys, array $person): bool
const V = 10;
$none = [];
$a = ['fixture-a'];
$ab = ['fixture-a', 'fixture-b'];

/** A learner of fixture-a who is in its cohort and is nobody special (organisation\access facts). */
function learner(array $over = []): array {
    return array_merge(['id' => 20, 'ltct_org' => 'fixture-a', 'org_cohorts' => ['fixture-a'],
        'deleted' => false, 'siteadmin' => false, 'coursecontact' => false, 'highrole' => false,
        'managers' => false, 'mentor' => false], $over);
}

// --- the site team: moodle/role:assign in the learner's context, mentor assignable there ----
check(mentor_admin::decide(V, true, true, $none, learner()), 'site team: allowed');
check(mentor_admin::decide(V, true, true, $none, learner(['ltct_org' => '', 'org_cohorts' => []])),
    'site team: a learner with no organisation');
check(mentor_admin::decide(V, true, true, $none, learner(['ltct_org' => 'fixture-b',
    'org_cohorts' => ['fixture-b']])), 'site team: any organisation');
check(mentor_admin::decide(V, true, true, $none, learner(['coursecontact' => true, 'mentor' => true])),
    'site team: staff and mentors are theirs to manage');
check(mentor_admin::decide(V, true, true, $a, learner(['ltct_org' => 'fixture-b',
    'org_cohorts' => ['fixture-b']])), 'site team who also manages an organisation: any learner');

// --- an organisation manager: may_manage_account() --------------------------------------------
check(mentor_admin::decide(V, true, false, $a, learner()), "manager of the learner's organisation: allowed");
check(mentor_admin::decide(V, true, false, $ab, learner(['ltct_org' => 'fixture-b',
    'org_cohorts' => ['fixture-b']])), 'manager of two organisations: the second');
check(!mentor_admin::decide(V, true, false, $a, learner(['ltct_org' => 'fixture-b',
    'org_cohorts' => ['fixture-b']])), 'manager: another organisation refused');
check(!mentor_admin::decide(V, true, false, $a, learner(['ltct_org' => '', 'org_cohorts' => []])),
    'manager: an empty ltct_org refused');
check(!mentor_admin::decide(V, true, false, $a, learner(['ltct_org' => '  '])),
    'manager: an ltct_org of only spaces refused');
check(!mentor_admin::decide(V, true, false, $a, learner(['org_cohorts' => []])),
    'manager: field set but not yet in the cohort refused');
foreach (['deleted' => 'a deleted user', 'siteadmin' => 'a site admin',
          'coursecontact' => 'a course contact (teacher)', 'highrole' => 'a system or category role',
          'managers' => 'another manager', 'mentor' => 'an ltct:mentors member'] as $k => $label) {
    check(!mentor_admin::decide(V, true, false, $a, learner([$k => true])), "manager: refused for $label");
    $missing = learner();
    unset($missing[$k]);
    check(!mentor_admin::decide(V, true, false, $a, $missing), "manager: fact '$k' not supplied fails closed");
}

// --- refused for everyone ---------------------------------------------------------------------
check(!mentor_admin::decide(V, true, false, $none, learner()), 'neither site team nor manager: refused');
check(!mentor_admin::decide(V, true, true, $none, learner(['id' => V])), 'self: refused, even the site team');
check(!mentor_admin::decide(V, true, false, $a, learner(['id' => V])), 'self: refused, a manager');
check(!mentor_admin::decide(V, false, true, $none, learner()), 'missing or deleted learner: refused, site team');
check(!mentor_admin::decide(V, false, false, $a, learner()), 'missing or deleted learner: refused, manager');
check(!mentor_admin::decide(V, false, true, $none, []), 'no learner at all: refused');
$noid = learner();
unset($noid['id']);
check(!mentor_admin::decide(V, true, true, $none, $noid), 'no learner id: fails closed, site team');
check(!mentor_admin::decide(V, true, false, $a, $noid), 'no learner id: fails closed, manager');
check(!mentor_admin::decide(0, true, false, $a, learner(['id' => 0])), 'no viewer and no learner id: refused');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
