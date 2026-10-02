<?php
// Harness for local_ltuse's pure recognition pieces (spec 013): the badge text renderer, the
// wording backstop and the certificate's availability. Needs no Moodle. Run from the repo
// root: php tests/recognition_harness.php
//
// The deny patterns are read from scripts/cbc_wording.py, the only definition of the rule,
// through Python. That is the cross-language risk this harness exists for: a pattern Python
// accepts that PHP's PCRE does not, or reads differently, would let a word through the
// plugin's backstop.
define('MOODLE_INTERNAL', 1);

$plugin = __DIR__ . '/../moodle/local_ltuse/classes';
require $plugin . '/recognition/renderer.php';
require $plugin . '/recognition/wording.php';
require $plugin . '/recognition/certificate.php';

use local_ltuse\recognition\certificate;
use local_ltuse\recognition\renderer;
use local_ltuse\recognition\wording;

$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

// --- the deny patterns, from Python ---------------------------------------------------------
$deny = null;
// Single quotes only inside: escapeshellarg() on Windows turns a double quote into a space.
$script = "import json,sys; sys.path.insert(0, 'scripts'); import cbc_wording; "
    . "print(json.dumps([{'pattern': p, 'why': w} for p, w in cbc_wording.DENY_PATTERNS]))";
foreach (['python3', 'python'] as $python) {
    $out = shell_exec($python . ' -c ' . escapeshellarg($script) . ' 2>&1');
    $decoded = is_string($out) ? json_decode(trim($out), true) : null;
    if (is_array($decoded) && $decoded) {
        $deny = $decoded;
        break;
    }
}
check(is_array($deny) && count($deny) >= 5, 'deny patterns read from scripts/cbc_wording.py');
$deny = $deny ?: [];
foreach ($deny as $rule) {
    check(@preg_match('~' . str_replace('~', '\~', $rule['pattern']) . '~iu', '') !== false,
        "PCRE compiles {$rule['pattern']}");
}

// --- wording::problems() ---------------------------------------------------------------------
$refused = [
    'Certified in Paratext' => 'certified',
    'Paratext certification' => 'certification',
    'Certificate of competency' => 'certificate of competency',
    'Accredited trainer course' => 'accredited',
    'Level 3 reached' => 'level by number',
    'Practitioner badge' => 'retired level name',
    'CERTIFIES you' => 'case-insensitive',
];
foreach ($refused as $text => $why) {
    check(count(wording::problems(['name' => $text], $deny)) > 0, "refuses '$text' ($why)");
}
$allowed = [
    'Paratext basics: training completed',
    'Certificate of training completed',
    'Designed to support progress towards 2 - With Assistance',
];
foreach ($allowed as $text) {
    check(wording::problems(['name' => $text], $deny) === [], "allows '$text'");
}
check(wording::problems(['name' => 'Paratext: training completed'], []) !== [],
    'no stored patterns is a refusal, not a pass');
check(wording::problems(['name' => 'x'], [['pattern' => '(unclosed', 'why' => 'w']]) !== [],
    'a pattern that does not compile is a refusal');
$problems = wording::problems(['name' => 'ok: training completed', 'description' => 'Certified'], $deny);
check(count($problems) === 1 && strpos($problems[0], 'description:') === 0, 'names the field that failed');

// --- renderer --------------------------------------------------------------------------------
check(renderer::competencies('[Paratext] [Keyboards & Fonts]') === 'Paratext, Keyboards & Fonts',
    'competencies: [A] [B] is A, B');
check(renderer::competencies('') === '', 'competencies: empty field is empty');
check(renderer::competencies('[Only]') === 'Only', 'competencies: one name');

$template = [
    'name' => '{course}: training completed',
    'description' => 'The course addresses {competencies}, and is designed to support progress towards {target_level}.',
    'imagecaption' => 'Completion badge',
    'message_subject' => 'Training completed: %badgename%',
    'message' => 'Hello %username%, see %badgelink%',
];
$texts = renderer::render($template, 'Paratext basics', '[Paratext] [Fonts]', '2 - With Assistance');
check($texts['name'] === 'Paratext basics: training completed', 'render: {course}');
check($texts['description'] === 'The course addresses Paratext, Fonts, and is designed to support progress towards 2 - With Assistance.',
    'render: {competencies} and {target_level}');
check($texts['message'] === 'Hello %username%, see %badgelink%', 'render: core\'s %placeholders% are left for core');
check(array_keys($texts) === renderer::FIELDS, 'render: every field, in order');
$texts = renderer::render($template, 'A {target_level} course', '', '4 - Expert');
check($texts['name'] === 'A {target_level} course: training completed', 'render: a placeholder in a title is not substituted again');

check(renderer::issuerurl('https://learn.example.org/moodle') === 'https://learn.example.org', 'issuerurl: scheme and host only');
check(renderer::issuerurl('http://localhost:8080') === 'http://localhost:8080', 'issuerurl: keeps a port');
check(renderer::message_html('a <b> & "c"') === '<p>a &lt;b&gt; &amp; &quot;c&quot;</p>', 'message_html: escaped, one paragraph');

// --- the certificate's availability (R8) ------------------------------------------------------
$json = certificate::availability_json();
check($json === '{"op":"&","c":[{"type":"coursecompleted","id":"1"}],"showc":[true]}',
    'availability: course completed, shown while locked');
$decoded = json_decode($json, true);
check(count($decoded['c']) === 1 && count($decoded['showc']) === 1, 'availability: one condition, one showc');

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit($fails ? 1 : 0);
