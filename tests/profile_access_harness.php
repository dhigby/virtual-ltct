<?php
// Harness for moodle/local_ltuse/classes/profile_access.php (spec 002, R9, amended 2026-10-02).
// Needs no Moodle:
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
const DNP = core_user::VIEWPROFILE_DO_NOT_PREVENT;
const FORCE = core_user::VIEWPROFILE_FORCE_ALLOW;
// decide(bool $isself, array $managedkeys, string $viewedorg, bool $managesviewed,
//        bool $viewedisstaff, bool $viewerhasviewalldetails, bool $viewerismentor,
//        bool|callable $participantpath): int
// $managesviewed is organisation\access::is_org_member_of_manager(V, P), gathered by lib.php.
$a = ['fixture-a'];
$ab = ['fixture-a', 'fixture-b'];
/** decide() with named overrides, so each case reads as what differs from a plain one. */
function d(array $o = []): int {
    $o += ['self' => false, 'keys' => ['fixture-a'], 'org' => 'fixture-b', 'manages' => false,
           'staff' => false, 'all' => false, 'mentor' => false, 'path' => false];
    return profile_access::decide($o['self'], $o['keys'], $o['org'], $o['manages'], $o['staff'],
        $o['all'], $o['mentor'], $o['path']);
}

// Case 1: viewing yourself, or managing no organisation.
check(d(['self' => true]) === DNP, 'self, even as a manager of another organisation');
check(d(['self' => true, 'org' => '', 'manages' => true]) === DNP, 'self is never force-allowed');
check(d(['keys' => []]) === DNP, 'no managed organisation, other learner');
check(d(['keys' => [], 'org' => '']) === DNP, 'no managed organisation, no organisation');

// Case 2: an own-organisation member is force-allowed, whatever their role.
check(d(['org' => 'fixture-a', 'manages' => true]) === FORCE, 'own-organisation learner');
check(d(['org' => 'fixture-a', 'manages' => true, 'mentor' => true]) === FORCE,
    'own-organisation member who is also a mentor');
check(d(['org' => 'fixture-a', 'manages' => true, 'staff' => true]) === FORCE,
    'own-organisation member who is staff or another manager');
check(d(['keys' => $ab, 'org' => 'fixture-b', 'manages' => true]) === FORCE,
    'manager of two organisations, the second one');
// Field set but not yet in the cohort: lib.php passes manages=false, so no force.
check(d(['org' => 'fixture-a', 'manages' => false]) === PREVENT,
    'field set but not in the cohort, no other path, is prevented');

// Case 3: viewalldetails, mentor, or a participant path lets core decide.
check(d(['path' => true]) === DNP, "another organisation's person with a participant path");
check(d(['all' => true]) === DNP, 'viewalldetails, another organisation');
check(d(['all' => true, 'org' => '']) === DNP, 'viewalldetails, empty organisation');
check(d(['mentor' => true]) === DNP, 'manager of A, mentor of a learner in B');
check(d(['mentor' => true, 'org' => '']) === DNP, 'mentor of a learner with no organisation');
check(d(['path' => function () { return true; }]) === DNP, 'participant path given lazily');

// Case 4: otherwise prevented.
check(d() === PREVENT, "another organisation's person reached only as a manager");
check(d(['path' => function () { return false; }]) === PREVENT, 'lazy participant path that fails');
check(d(['org' => '']) === PREVENT, 'empty organisation, not staff');
check(d(['org' => '   ']) === PREVENT, 'an organisation of only spaces counts as empty');
check(d(['org' => '', 'staff' => true]) === DNP,
    'empty organisation, staff (course contact or site team), is not prevented');
check(d(['staff' => true]) === PREVENT, 'staff with another organisation is still prevented');

// The participant path is not computed when cases 1 and 2 decide.
$called = false;
$spy = function () use (&$called) { $called = true; return true; };
d(['self' => true, 'path' => $spy]);
d(['keys' => [], 'path' => $spy]);
d(['org' => 'fixture-a', 'manages' => true, 'path' => $spy]);
check(!$called, 'participant path not computed when cases 1 and 2 decide');

// FORCE_ALLOW only when the viewed person is an own-organisation member (case 2).
$seen = [];
foreach ([true, false] as $isself) {
    foreach ([[], $a, $ab] as $keys) {
        foreach (['', 'fixture-a', 'fixture-b', 'fixture-c'] as $org) {
            foreach ([true, false] as $manages) {
                foreach ([true, false] as $staff) {
                    foreach ([true, false] as $all) {
                        foreach ([true, false] as $mentor) {
                            foreach ([true, false] as $path) {
                                $r = profile_access::decide($isself, $keys, $org, $manages, $staff,
                                    $all, $mentor, $path);
                                $seen[$r] = true;
                                if (!in_array($r, [PREVENT, DNP, FORCE], true)) {
                                    check(false, 'outcome ' . var_export($r, true) . ' is not a VIEWPROFILE value');
                                }
                                if ($r === FORCE && ($isself || !$keys || !$manages)) {
                                    check(false, "FORCE_ALLOW outside case 2: self=$isself org=$org manages=$manages");
                                }
                                if (!$isself && $keys && $manages && $r !== FORCE) {
                                    check(false, "case 2 not FORCE_ALLOW: org=$org");
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
check(count($seen) === 3, 'all three outcomes occur');
check(true, 'FORCE_ALLOW only when the viewed person is an own-organisation member');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
