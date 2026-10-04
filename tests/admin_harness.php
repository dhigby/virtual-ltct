<?php
// Harness for local_ltuse's pure administration classes (spec 008, research R4):
// moodle/local_ltuse/classes/admin/*_rules.php and masking.php. Needs no Moodle: the classes
// are pure. Every address is @example.org and every organisation key fixture-*.
//
// Run from the repo root:  php tests/admin_harness.php
//
// The masking case reads scripts/admin_files.py's mask_email() through Python, so the CLI and
// the server can never mask one person two ways.
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$dir = __DIR__ . '/../moodle/local_ltuse/classes/admin';
$classes = ['intake_rules', 'enrolment_rules', 'move_rules', 'course_mentor_rules', 'masking'];
foreach ($classes as $name) {
    $path = "$dir/$name.php";
    if (is_file($path)) {
        require $path;
    }
    check(class_exists("local_ltuse\\admin\\$name"), "local_ltuse\\admin\\$name loads without Moodle");
}
if ($fails) {
    echo "FAILURES: $fails\n";
    exit(1);
}

use local_ltuse\admin\masking;

// --- masking (T019) ----------------------------------------------------------------------------
check(masking::mask_email('alice@example.org') === 'a***@example.org', 'masks to the first character');
check(masking::mask_email('  Alice@Example.ORG ') === 'a***@example.org', 'trims and lowercases');
check(masking::mask_email('not-an-address') === '***', 'not an address');
check(masking::mask_email('') === '***', 'empty');
check(masking::mask_email('@example.org') === '***', 'no local part');
check(masking::mask_email('alice@') === '***', 'no domain');

// The same five addresses through both, compared character for character.
$addresses = ['alice@example.org', 'B.Fixture@Example.org', ' c@example.org ', 'd+tag@sub.example.org',
    'é-fixture@example.org'];
$python = null;
// No double quote may reach the shell: escapeshellarg() on Windows turns one into a space.
// So the script uses single quotes only, and the addresses go across as hex-encoded JSON.
$script = "import json,sys; sys.path.insert(0, 'scripts'); import admin_files; "
    . "print(json.dumps([admin_files.mask_email(a) for a in "
    . "json.loads(bytes.fromhex(sys.argv[1]).decode('utf-8'))]))";
foreach (['python3', 'python'] as $exe) {
    $out = shell_exec($exe . ' -c ' . escapeshellarg($script) . ' '
        . bin2hex(json_encode($addresses, JSON_UNESCAPED_UNICODE)) . ' 2>&1');
    $decoded = is_string($out) ? json_decode(trim($out), true) : null;
    if (is_array($decoded) && count($decoded) === count($addresses)) {
        $python = $decoded;
        break;
    }
}
check(is_array($python), 'mask_email read from scripts/admin_files.py');
foreach ($addresses as $i => $address) {
    $php = masking::mask_email($address);
    check(is_array($python) && $python[$i] === $php, "PHP and Python mask address $i alike ($php)");
}

use local_ltuse\admin\intake_rules;
use local_ltuse\admin\enrolment_rules;

// --- intake_rules::classify (T026) --------------------------------------------------------
/** Facts for a row of fixture-a whose email matches no account, with spec 016 absent. */
function intake(array $over = []): array {
    return array_merge(['accounts' => 0, 'org' => '', 'suspended' => false, 'effective' => 'none',
        'settled' => true, 'protection' => false, 'available' => [], 'roworg' => 'fixture-a',
        'asked' => 'none', 'orgminimum' => 'none', 'courses' => [], 'active' => [], 'allowed' => []], $over);
}
/** The same, with spec 016 installed and every level available. */
function intake016(array $over = []): array {
    return intake(array_merge(['protection' => true,
        'available' => ['none', 'email', 'firstname', 'pseudonym']], $over));
}
function outcome(array $facts): string {
    return intake_rules::classify($facts)['outcome'];
}
$existing = ['accounts' => 1, 'org' => 'fixture-a'];
$course = ['courses' => ['ltct:fixture-course'], 'allowed' => ['ltct:fixture-course']];

check(outcome(intake()) === 'new', 'new: no live account has the email');
$r = intake_rules::classify(intake($course));
check($r['changes'] === ['create', 'set_org:fixture-a', 'enrol:ltct:fixture-course'],
    'new: create, then organisation, then the course');
$r = intake_rules::classify(intake016(['asked' => 'email']));
check($r['outcome'] === 'new' && $r['changes'] === ['create', 'set_protection:email', 'set_org:fixture-a'],
    'new with protection: protection before the organisation');
check(intake_rules::classify(intake016(['orgminimum' => 'firstname']))['target'] === 'firstname',
    'target is the organisation minimum when it is stricter than the row');
check(intake_rules::classify(intake016(['asked' => 'pseudonym', 'orgminimum' => 'email']))['target'] === 'pseudonym',
    'target is the row level when it is stricter than the minimum');

check(outcome(intake($existing)) === 'unchanged', 'unchanged: same organisation, no courses');
check(outcome(intake(array_merge($existing, $course, ['active' => ['ltct:fixture-course']]))) === 'unchanged',
    'unchanged: every listed course already active');
$r = intake_rules::classify(intake(array_merge($existing, $course)));
check($r['outcome'] === 'will_enrol' && $r['changes'] === ['enrol:ltct:fixture-course'],
    'will_enrol: same organisation, a listed course not yet active');
check(outcome(intake016(array_merge($existing, $course, ['asked' => 'email', 'effective' => 'firstname']))) === 'will_enrol',
    'will_enrol: protection above the target is enough');

check(outcome(intake(['accounts' => 1])) === 'will_set_org', 'will_set_org: account with no organisation');
$r = intake_rules::classify(intake016(['accounts' => 1, 'asked' => 'email', 'effective' => 'none', 'settled' => true]));
check($r['outcome'] === 'will_set_org' && $r['changes'] === ['set_protection:email', 'set_org:fixture-a'],
    'will_set_org: created, protection never set: protect first');
$r = intake_rules::classify(intake016(['accounts' => 1, 'asked' => 'email', 'effective' => 'email', 'settled' => false]));
check($r['outcome'] === 'will_set_org' && in_array('set_protection:email', $r['changes'], true),
    'will_set_org: created, protection set but not settled');
$r = intake_rules::classify(intake016(['accounts' => 1, 'asked' => 'email', 'effective' => 'email', 'settled' => true]));
check($r['outcome'] === 'will_set_org' && $r['changes'] === ['set_org:fixture-a'],
    'will_set_org: protection settled: only the organisation remains');

check(outcome(intake(['accounts' => 1, 'org' => 'fixture-b'])) === 'flagged_other_org',
    'flagged_other_org: already under another organisation');
check(outcome(intake(['accounts' => 1, 'org' => 'fixture-a', 'suspended' => true])) === 'flagged_suspended',
    'flagged_suspended: the account is suspended');
check(outcome(intake(['accounts' => 1, 'suspended' => true])) === 'flagged_suspended',
    'flagged_suspended: even with no organisation yet');
check(outcome(intake016(array_merge($existing, ['asked' => 'firstname', 'effective' => 'email']))) === 'flagged_protection',
    'flagged_protection: existing account below the row target');
check(outcome(intake016(array_merge($existing, ['orgminimum' => 'email', 'effective' => 'none']))) === 'flagged_protection',
    'flagged_protection: existing account below the organisation minimum');
check(outcome(intake016(array_merge($existing, ['asked' => 'pseudonym', 'effective' => 'email',
    'available' => ['none', 'email']]))) === 'flagged_protection',
    'flagged_protection: an existing account is flagged, not made to wait');

check(outcome(intake(['asked' => 'email'])) === 'waits', 'waits: protection asked, spec 016 absent');
check(outcome(intake(array_merge($existing, ['asked' => 'email']))) === 'waits',
    'waits: protection asked, 016 absent, even for an existing account');
check(outcome(intake016(['asked' => 'firstname', 'available' => ['none', 'email']])) === 'waits',
    'waits: level_available false for the target');
check(outcome(intake016(['orgminimum' => 'pseudonym', 'available' => ['none', 'email']])) === 'waits',
    'waits: the organisation minimum is not available yet');
check(intake_rules::classify(intake(['asked' => 'email']))['changes'] === [], 'waits: no change at all');
check(outcome(intake016(['accounts' => 1, 'asked' => 'firstname', 'available' => ['none', 'email']])) === 'waits',
    'waits: an interrupted account still needing protection');

check(outcome(intake(['accounts' => 2])) === 'rejected', 'rejected: two live accounts share the email');
check(outcome(intake(['courses' => ['ltct:fixture-course'], 'allowed' => []])) === 'rejected',
    'rejected: a course the organisation may not be enrolled into');
check(intake_rules::classify(intake(['courses' => ['ltct:fixture-course']]))['reason'] === 'course_not_allowed',
    'rejected: names its reason');
check(outcome(intake(array_merge($existing, ['courses' => ['ltct:fixture-course', 'ltct:fixture-other'],
    'allowed' => ['ltct:fixture-course']]))) === 'rejected', 'rejected: one course of two not allowed');
$noaccounts = intake();
unset($noaccounts['accounts']);
check(outcome($noaccounts) === 'rejected', 'a fact not supplied fails closed');
check(outcome(intake(['asked' => 'secret'])) === 'rejected', 'an unknown protection level fails closed');
check(outcome(intake(['accounts' => 1, 'org' => 'fixture-b', 'suspended' => true])) === 'flagged_suspended',
    'suspension is reported before the other organisation');

// --- intake_rules::progress (T027) --------------------------------------------------------
$apply = intake_rules::APPLY;
$finish = intake_rules::FINISH;
$refused = intake_rules::REFUSED;
foreach (['new', 'will_set_org', 'will_enrol'] as $o) {
    check(intake_rules::progress($o, $o) === $apply, "progress: $o -> $o applies");
}
foreach ([['new', 'will_set_org'], ['new', 'will_enrol'], ['new', 'unchanged'], ['will_set_org', 'will_enrol'],
          ['will_set_org', 'unchanged'], ['will_enrol', 'unchanged']] as [$from, $to]) {
    check(intake_rules::progress($from, $to) === $finish, "progress: $from -> $to finishes or is already done");
}
foreach (['flagged_other_org', 'flagged_suspended', 'flagged_protection', 'rejected', 'waits'] as $to) {
    check(intake_rules::progress('new', $to) === $refused, "progress: new -> $to is refused");
    check(intake_rules::progress('will_enrol', $to) === $refused, "progress: will_enrol -> $to is refused");
}
foreach ([['will_set_org', 'new'], ['unchanged', 'new'], ['will_enrol', 'will_set_org']] as [$from, $to]) {
    check(intake_rules::progress($from, $to) === $refused, "progress: backwards $from -> $to is refused");
}
check(intake_rules::progress('waits', 'waits') === $refused, 'progress: a stopping outcome is never applied');
check(intake_rules::progress('flagged_other_org', 'new') === $refused, 'progress: flagged -> new is refused');
check(intake_rules::change_progress('would_change', 'would_change') === $apply, 'change progress: as previewed applies');
check(intake_rules::change_progress('would_change', 'unchanged') === $finish, 'change progress: unchanged is already done');
check(intake_rules::change_progress('would_change', 'rejected') === $refused, 'change progress: -> rejected is refused');
check(intake_rules::change_progress('rejected', 'rejected') === $refused, 'change progress: rejected is never applied');
check(intake_rules::change_progress('unchanged', 'would_change') === $refused, 'change progress: backwards is refused');

// --- enrolment_rules::decide (T039) -------------------------------------------------------
function role_of(string $cohort, string $course, string $category): ?string {
    return enrolment_rules::decide($cohort, $course, $category)['role'];
}
$c = 'ltct:fixture-course';
check(role_of('ltct:org:fixture-a', $c, 'ltct:published') === 'student', 'org cohort: Student in ltct:published');
check(role_of('ltct:org:fixture-a', $c, 'ltct:org:fixture-a') === 'student', 'org cohort: Student in its own category');
check(role_of('ltct:org:fixture-a', $c, 'ltct:org:fixture-b') === null, 'org cohort: refused in another organisation');
check(role_of('ltct:org:fixture-a', $c, 'ltct:org:fixture-ab') === null, 'org cohort: a key that only starts alike');
check(role_of('ltct:org:fixture-ab', $c, 'ltct:org:fixture-a') === null, 'org cohort: a longer key, a shorter category');
check(role_of('ltct:org:fixture-a:managers', $c, 'ltct:org:fixture-a') === 'orgmanager',
    'managers cohort: orgmanager in its own category');
check(role_of('ltct:org:fixture-a:managers', $c, 'ltct:published') === null, 'managers cohort: refused in ltct:published');
check(enrolment_rules::decide('ltct:org:fixture-a:managers', $c, 'ltct:published')['reason'] === 'managers_shared',
    'managers cohort in ltct:published: names its reason');
check(role_of('ltct:org:fixture-a:managers', $c, 'ltct:org:fixture-b') === null,
    'managers cohort: refused in another organisation');
foreach (['ltct:published', 'ltct:org:fixture-a', 'ltct:pilots', ''] as $cat) {
    check(role_of('ltct:mentors', $c, $cat) === null, "ltct:mentors refused in '$cat'");
}
foreach (['fixture-teaching', 'ltct:fixture-teaching', 'ltct:org:', 'ltct:org:Fixture-A', 'ltct:org:fixture-a:extra', ''] as $other) {
    check(role_of($other, $c, 'ltct:published') === null, "other cohort '$other' refused (plan decision 8)");
}
check(role_of('ltct:org:fixture-a', $c, 'ltct:pilots') === null, 'ltct:pilots refused');
check(role_of('ltct:org:fixture-a', 'ltct:officehours', 'ltct:published') === null, 'ltct:officehours refused');
foreach (['fixture-course', 'ltct:fixture-course:03', 'ltct:', 'ltct:org:fixture-a', ''] as $bad) {
    check(role_of('ltct:org:fixture-a', $bad, 'ltct:published') === null, "non-ltct:<slug> course '$bad' refused");
}
check(role_of('ltct:org:fixture-a', $c, 'fixture-category') === null, 'a category of no kind refused');
check(enrolment_rules::cohort_kind('ltct:org:fixture-a:managers') === ['managers', 'fixture-a'], 'cohort_kind: managers');
check(enrolment_rules::cohort_kind('ltct:org:fixture-a') === ['org', 'fixture-a'], 'cohort_kind: organisation');
foreach (['would_add', 'would_enable', 'would_disable'] as $o) {
    check(enrolment_rules::progress($o, $o) === $apply, "cohort progress: $o -> $o applies");
    check(enrolment_rules::progress($o, 'already') === $finish, "cohort progress: $o -> already is already done");
    check(enrolment_rules::progress($o, 'refused') === $refused, "cohort progress: $o -> refused is refused");
}
check(enrolment_rules::progress('would_add', 'would_enable') === $refused, 'cohort progress: add -> enable is refused');
check(enrolment_rules::progress('refused', 'refused') === $refused, 'cohort progress: refused is never applied');

use local_ltuse\admin\move_rules;

// --- move_rules::classify (T048) ----------------------------------------------------------
/** Facts for a fixture-a learner moving to fixture-b, with spec 016 absent. */
function move(array $over = []): array {
    return array_merge(['accounts' => 1, 'org' => 'fixture-a', 'neworg' => 'fixture-b', 'effective' => 'none',
        'newminimum' => 'none', 'courses' => [], 'gained' => []], $over);
}
/** One course the learner is active in: shared, through fixture-a's cohort sync only. */
function active(array $over = []): array {
    return array_merge(['course' => 'ltct:fixture-shared', 'category' => 'ltct:published', 'viaold' => true,
        'other' => false, 'newcohort' => false], $over);
}
function course_outcomes(array $result): array {
    $out = [];
    foreach ($result['courses'] as $c) {
        $out[$c['course']] = $c['outcome'];
    }
    return $out;
}

$r = move_rules::classify(move(['courses' => [active(['newcohort' => true])]]));
check($r['outcome'] === 'would_move' && course_outcomes($r) === ['ltct:fixture-shared' => 'kept'],
    'kept: the new organisation\'s cohort is enabled in the shared course');
$r = move_rules::classify(move(['courses' => [active(['other' => true])]]));
check($r['outcome'] === 'would_move' && course_outcomes($r) === ['ltct:fixture-shared' => 'kept'],
    'kept: another active enrolment in the shared course');
$r = move_rules::classify(move(['courses' => [active(['viaold' => false, 'other' => true])]]));
check(course_outcomes($r) === ['ltct:fixture-shared' => 'kept'], 'kept: an enrolment the move does not touch');
$r = move_rules::classify(move(['gained' => ['ltct:fixture-b-course']]));
check($r['outcome'] === 'would_move' && course_outcomes($r) === ['ltct:fixture-b-course' => 'gained']
    && in_array('enrol:ltct:fixture-b-course', $r['changes'], true), 'gained: a course the new cohort is enrolled in');
$r = move_rules::classify(move(['courses' => [active(['newcohort' => true])], 'gained' => ['ltct:fixture-shared']]));
check(course_outcomes($r) === ['ltct:fixture-shared' => 'kept'], 'gained: never a course the learner is already in');
$r = move_rules::classify(move(['courses' => [active(['course' => 'ltct:fixture-a-only',
    'category' => 'ltct:org:fixture-a'])]]));
check($r['outcome'] === 'would_move' && course_outcomes($r) === ['ltct:fixture-a-only' => 'suspended_by_rule']
    && in_array('suspend:ltct:fixture-a-only', $r['changes'], true),
    'suspended_by_rule: the old organisation\'s own course, allowed');
$r = move_rules::classify(move(['courses' => [active(['course' => 'ltct:fixture-a-only',
    'category' => 'ltct:org:fixture-a', 'other' => true])]]));
check(course_outcomes($r) === ['ltct:fixture-a-only' => 'suspended_by_rule'],
    'suspended_by_rule: even with another enrolment there');
$r = move_rules::classify(move(['courses' => [active()]]));
check($r['outcome'] === 'lost' && course_outcomes($r) === ['ltct:fixture-shared' => 'lost']
    && $r['lost'] === ['ltct:fixture-shared'] && $r['changes'] === [],
    'lost: a shared course with no match refuses the learner');
$r = move_rules::classify(move(['courses' => [active(), active(['course' => 'ltct:fixture-two', 'newcohort' => true])]]));
check($r['outcome'] === 'lost' && $r['lost'] === ['ltct:fixture-shared'], 'lost: one course of two is enough to refuse');
$r = move_rules::classify(move(['effective' => 'none', 'newminimum' => 'email',
    'courses' => [active(['newcohort' => true])]]));
check($r['outcome'] === 'flagged_protection' && $r['reason'] === 'protection_below_new' && $r['changes'] === [],
    'flagged_protection: effective level below the new organisation\'s minimum');
check(move_rules::classify(move(['effective' => 'firstname', 'newminimum' => 'email']))['outcome'] === 'would_move',
    'protection above the new minimum moves');
check(move_rules::classify(move(['effective' => 'email', 'newminimum' => 'email']))['outcome'] === 'would_move',
    'protection equal to the new minimum moves');
check(move_rules::classify(move(['newminimum' => 'firstname', 'courses' => [active()]]))['outcome'] === 'flagged_protection',
    'flagged_protection is reported before lost');
check(move_rules::classify(move(['org' => 'fixture-b']))['outcome'] === 'moved', 'moved: already in the new organisation');
check(move_rules::classify(move(['accounts' => 0]))['outcome'] === 'rejected', 'rejected: no account');
check(move_rules::classify(move(['accounts' => 2]))['reason'] === 'duplicate_accounts', 'rejected: two accounts');
check(move_rules::classify(move(['org' => '']))['reason'] === 'no_org', 'rejected: no organisation yet (intake\'s work)');
$nocourses = move();
unset($nocourses['courses']);
check(move_rules::classify($nocourses)['outcome'] === 'rejected', 'move: a fact not supplied fails closed');
check(move_rules::classify(move(['effective' => 'secret']))['outcome'] === 'rejected', 'move: an unknown level fails closed');
check(move_rules::classify(move(['courses' => [['course' => 'ltct:fixture-shared']]]))['outcome'] === 'rejected',
    'move: a course missing its facts fails closed');
check(move_rules::classify(move(['org' => 'fixture-ab', 'courses' => [active(['category' => 'ltct:org:fixture-a'])]]))['outcome']
    === 'lost', 'move: a key that only starts alike is not the old organisation\'s course');

// --- move_rules::progress ------------------------------------------------------------------
$shown = [['course' => 'ltct:fixture-a-only', 'outcome' => 'suspended_by_rule'],
    ['course' => 'ltct:fixture-shared', 'outcome' => 'kept']];
check(move_rules::progress('would_move', 'would_move', $shown, $shown) === $apply, 'move progress: as previewed applies');
check(move_rules::progress('would_move', 'moved', $shown, []) === $finish, 'move progress: moved is already done');
check(move_rules::progress('would_move', 'would_move', $shown,
    array_merge($shown, [['course' => 'ltct:fixture-b-course', 'outcome' => 'gained']])) === $apply,
    'move progress: a course gained since is no reason to refuse');
check(move_rules::progress('would_move', 'would_move', $shown,
    array_merge($shown, [['course' => 'ltct:fixture-a-two', 'outcome' => 'suspended_by_rule']])) === $refused,
    'move progress: a new suspension since the preview is refused');
check(move_rules::progress('would_move', 'lost', $shown, []) === $refused, 'move progress: would_move -> lost is refused');
check(move_rules::progress('would_move', 'flagged_protection', $shown, []) === $refused,
    'move progress: would_move -> flagged_protection is refused');
check(move_rules::progress('lost', 'lost', [], []) === $refused, 'move progress: a stopping outcome is never applied');
check(move_rules::progress('would_move', 'rejected', [], []) === $refused, 'move progress: would_move -> rejected is refused');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
