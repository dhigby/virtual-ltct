<?php
// Harness for moodle/local_ltuse/classes/profile_access.php (spec 002, R9). Needs no Moodle:
// it stubs core_user's three profile constants with core's values.
//
// Run locally:        php tests/profile_access_harness.php
// Run on a host with no checkout (nothing is written there): set $LTCT_PROFILE_ACCESS_SRC
// to the class source and send it ahead of this file, minus its first line, on one stream:
//   { printf '<?php $LTCT_PROFILE_ACCESS_SRC = base64_decode("%s");\n' \
//       "$(tr -d '\r' < moodle/local_ltuse/classes/profile_access.php | base64 -w0)";
//     tail -n +2 tests/profile_access_harness.php | tr -d '\r'; } | ssh ltuse php
define('MOODLE_INTERNAL', 1);
class core_user {
    const VIEWPROFILE_PREVENT = -1;
    const VIEWPROFILE_DO_NOT_PREVENT = 0;
    const VIEWPROFILE_FORCE_ALLOW = 1;
}
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/profile_access.php';
if (isset($LTCT_PROFILE_ACCESS_SRC)) {
    eval(preg_replace('/^\s*<\?php/', '', $LTCT_PROFILE_ACCESS_SRC));
} else if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\profile_access') ||
        !method_exists('local_ltuse\profile_access', 'decide')) {
    echo "FAIL: local_ltuse\\profile_access::decide() not found\n";
    echo "FAILURES: 1\n";
    exit(1);
}

use local_ltuse\profile_access;
const PREVENT = core_user::VIEWPROFILE_PREVENT;
const ALLOW = core_user::VIEWPROFILE_DO_NOT_PREVENT;
// decide(bool $isself, array $managedkeys, string $viewedorg, bool $viewedisstaff,
//        bool $viewerhasviewalldetails, bool $viewerismentor = false): int
$a = ['fixture-a'];
$ab = ['fixture-a', 'fixture-b'];

// Viewing yourself is never prevented.
check(profile_access::decide(true, $a, 'fixture-b', false, false) === ALLOW,
    'self, even as a manager of another organisation');
check(profile_access::decide(true, $a, '', false, false) === ALLOW, 'self with an empty organisation');

// A viewer who manages no organisation is never prevented.
check(profile_access::decide(false, [], 'fixture-b', false, false) === ALLOW,
    'no managed organisation, other learner');
check(profile_access::decide(false, [], '', false, false) === ALLOW,
    'no managed organisation, learner with no organisation');

// A manager viewing their own organisation's learner is not prevented.
check(profile_access::decide(false, $a, 'fixture-a', false, false) === ALLOW, 'own organisation');
check(profile_access::decide(false, $ab, 'fixture-b', false, false) === ALLOW,
    'manager of two organisations, the second one');

// A manager viewing another organisation's learner is prevented.
check(profile_access::decide(false, $a, 'fixture-b', false, false) === PREVENT,
    'another organisation is prevented');
check(profile_access::decide(false, $a, 'fixture-ab', false, false) === PREVENT,
    'a key that only starts with a managed key is prevented');
check(profile_access::decide(false, $a, 'Fixture-A', false, false) === PREVENT,
    'keys compare exactly, case included');
// The staff exemption is for an empty organisation only (data-model "Profile access decision").
check(profile_access::decide(false, $a, 'fixture-b', true, false) === PREVENT,
    'staff with another organisation is still prevented');

// Empty ltct_org: prevented unless the viewed person is staff.
check(profile_access::decide(false, $a, '', false, false) === PREVENT,
    'empty organisation, not staff, is prevented');
check(profile_access::decide(false, $a, '', true, false) === ALLOW,
    'empty organisation, staff (course contact or site team), is not prevented');
check(profile_access::decide(false, $a, '   ', false, false) === PREVENT,
    'an organisation of only spaces counts as empty');
check(profile_access::decide(false, $a, '   ', true, false) === ALLOW,
    'an organisation of only spaces, staff, is not prevented');

// A viewer with moodle/user:viewalldetails is never prevented.
check(profile_access::decide(false, $a, 'fixture-b', false, true) === ALLOW,
    'viewalldetails, another organisation');
check(profile_access::decide(false, $a, '', false, true) === ALLOW,
    'viewalldetails, empty organisation');

// Spec 003 (R9): a mentor of the viewed learner is never prevented, even when the mentor
// also manages a different organisation. Without the mentor flag the same view is prevented.
check(profile_access::decide(false, $a, 'fixture-b', false, false, true) === ALLOW,
    'manager of A, mentor of a learner in B');
check(profile_access::decide(false, $a, 'fixture-b', false, false, false) === PREVENT,
    'manager of A, not a mentor, learner in B');
check(profile_access::decide(false, $a, '', false, false, true) === ALLOW,
    'manager of A, mentor of a learner with no organisation');

// The outcome is never VIEWPROFILE_FORCE_ALLOW, for any combination of inputs.
$seen = [];
foreach ([true, false] as $isself) {
    foreach ([[], $a, $ab] as $keys) {
        foreach (['', 'fixture-a', 'fixture-b', 'fixture-c'] as $org) {
            foreach ([true, false] as $staff) {
                foreach ([true, false] as $all) {
                    foreach ([true, false] as $mentor) {
                        $r = profile_access::decide($isself, $keys, $org, $staff, $all, $mentor);
                        $seen[var_export($r, true)] = true;
                        if (!is_int($r) || !in_array($r, [PREVENT, ALLOW], true)) {
                            check(false, 'outcome ' . var_export($r, true) . ' is not PREVENT or DO_NOT_PREVENT');
                        }
                    }
                }
            }
        }
    }
}
check(!isset($seen[var_export(core_user::VIEWPROFILE_FORCE_ALLOW, true)]), 'never FORCE_ALLOW');
check(count($seen) === 2, 'only PREVENT and DO_NOT_PREVENT occur, both of them');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
