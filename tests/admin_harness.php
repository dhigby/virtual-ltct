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
$addresses = ['alice@example.org', 'B.Okafor@Example.org', ' c@example.org ', 'd+tag@sub.example.org',
    'élodie@example.org'];
$python = null;
// Single quotes only inside: escapeshellarg() on Windows turns a double quote into a space.
$script = "import json,sys; sys.path.insert(0, 'scripts'); import admin_files; "
    . "print(json.dumps([admin_files.mask_email(a) for a in json.loads(sys.argv[1])]))";
foreach (['python3', 'python'] as $exe) {
    $out = shell_exec($exe . ' -c ' . escapeshellarg($script) . ' '
        . escapeshellarg(json_encode($addresses, JSON_UNESCAPED_UNICODE)) . ' 2>&1');
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

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
