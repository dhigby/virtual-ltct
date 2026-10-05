<?php
// Harness for moodle/local_ltuse/classes/protection/levels.php (spec 016). Needs no Moodle: the
// class is pure. Every name here is an obviously fake fixture, never a real person's
// (constitution III, FR-013).
//
// Run locally:  php tests/protection_harness.php
define('MOODLE_INTERNAL', 1);
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

$path = __DIR__ . '/../moodle/local_ltuse/classes/protection/levels.php';
if (is_file($path)) {
    require $path;
}
if (!class_exists('local_ltuse\protection\levels')) {
    echo "FAIL: local_ltuse\\protection\\levels not found\nFAILURES: 1\n";
    exit(1);
}

use local_ltuse\protection\levels as L;

// --- the levels ---------------------------------------------------------------------------
check(L::ORDER === ['none', 'email', 'firstname', 'pseudonym'], 'four levels, loosest first');
check(L::is_level('email') && !L::is_level('Email') && !L::is_level('') && !L::is_level(null), 'is_level is exact');
check(L::rank('none') === 0 && L::rank('pseudonym') === 3 && L::rank('bogus') === -1, 'rank');

// --- raises, and what a manager may do (scope review changes 13 and 14) ----------------------
check(!method_exists(L::class, 'available') && !defined(L::class . '::NEED_ORGSCOPE'),
    'no level waits for the organisation to be hidden (decision 2, option a)');
check(L::is_raise('none', 'email') && L::is_raise('email', 'pseudonym'), 'a raise');
check(!L::is_raise('email', 'email') && !L::is_raise('firstname', 'none'), 'no change and a lowering are not raises');
check(L::manager_may('none', 'firstname', false, false), 'a manager grants at intake');
check(!L::manager_may('none', 'firstname', true, false), 'not after activity');
check(!L::manager_may('email', 'none', false, false) && !L::manager_may('email', 'email', false, false),
    'never a lowering, a removal or a same-level change');
check(!L::manager_may('none', 'email', false, true), 'never a correction');

// --- withheld fields (R6) ---------------------------------------------------------------------
$withhold = [
    'email' => ['maildisplay'],
    'firstname' => ['maildisplay', 'lastname', 'firstnamephonetic', 'lastnamephonetic', 'middlename',
        'alternatename', 'picture', 'country', 'city', 'ltct_role', 'ltct_exp_core'],
    'pseudonym' => ['firstname'],
];
check(L::withheld($withhold, 'none') === [], 'none withholds nothing');
check(L::withheld($withhold, 'email') === ['maildisplay'], 'email withholds the address only');
$fn = L::withheld($withhold, 'firstname');
check(in_array('lastname', $fn, true) && in_array('ltct_role', $fn, true) && !in_array('firstname', $fn, true),
    'firstname withholds the surname and ltct_role but not the first name');
$ps = L::withheld($withhold, 'pseudonym');
check(in_array('firstname', $ps, true) && in_array('maildisplay', $ps, true) && in_array('city', $ps, true),
    'pseudonym includes everything below it');
check(count($ps) === count(array_unique($ps)), 'no field listed twice');
check(L::withheld(['email' => ['ltct_org', 'description', 'interests', 'maildisplay']], 'email') === ['maildisplay'],
    'ltct_org, description and interests are never withheld');
check(L::withheld(['email' => ['maildisplay', 'username', 'password']], 'email') === ['maildisplay'],
    'a field outside the fixed set is ignored');
check(L::withheld(['email' => ['maildisplay']], 'pseudonym') === ['maildisplay'],
    'a level missing from the config withholds what the levels below it do');

$split = L::split($ps);
check($split['names'] && $split['firstname'] && $split['maildisplay'] && $split['picture'], 'split finds the special fields');
check(in_array('city', $split['columns'], true) && in_array('middlename', $split['columns'], true), 'split: user columns');
check($split['profile'] === ['ltct_role', 'ltct_exp_core'], 'split: our profile fields');

// --- display (R1, R4) ---------------------------------------------------------------------------
check(L::display('email', 'Fixfirst', 'Fixlast', 'Pseudo', '') === ['Fixfirst', 'Fixlast'], 'email keeps the name');
check(L::display('firstname', 'Fixfirst', 'Fixlast', 'Pseudo', '') === ['Fixfirst', ''], 'firstname: neutral surname');
check(L::display('pseudonym', 'Fixfirst', 'Fixlast', 'Pseudo', '·') === ['Pseudo', '·'], 'pseudonym with the placeholder');

// --- folding and pseudonyms (data-model) ---------------------------------------------------------
check(L::fold("  Ana   B ") === 'ana b', 'fold trims, collapses and lowercases');
if (class_exists('\Normalizer')) {   // Moodle requires intl; a bare CI PHP may lack it.
    check(L::fold("e\u{0301}") === L::fold("\u{00E9}"), 'fold normalises to NFC');
} else {
    echo "skip: fold normalises to NFC (no intl extension here)\n";
}
check(L::fold('ÉLAN') === 'élan', 'fold lowercases beyond ASCII');
check(L::pseudonym_problems('', 'Fixfirst', 'Fixlast', []) === ['empty'], 'an empty pseudonym');
check(L::pseudonym_problems('   ', 'Fixfirst', 'Fixlast', []) === ['empty'], 'a blank pseudonym');
check(in_array('toolong', L::pseudonym_problems(str_repeat('x', 101), 'a', 'b', []), true), 'over 100 characters');
check(L::pseudonym_problems(str_repeat('x', 100), 'a', 'b', []) === [], '100 characters is fine');
check(in_array('isrealname', L::pseudonym_problems('fixFIRST', 'Fixfirst', 'Fixlast', []), true), 'equal to the real first name, any case');
check(in_array('hasrealname', L::pseudonym_problems('The Fixlast One', 'Fixfirst', 'Fixlast', []), true), 'contains the real surname');
check(!in_array('hasrealname', L::pseudonym_problems('Boxer', 'Fixfirst', 'Ox', []), true), 'a two-letter surname is not searched for');
check(in_array('taken', L::pseudonym_problems('Kestrel', 'a', 'b', ['kestrel']), true), 'taken by another protected user');
if (class_exists('\Normalizer')) {
    check(in_array('taken', L::pseudonym_problems("Cafe\u{0301}", 'a', 'b', ["caf\u{00E9}"]), true), 'taken after NFC');
}
check(L::pseudonym_problems('Kestrel', 'Fixfirst', 'Fixlast', ['heron']) === [], 'a good pseudonym');

// --- usernames (R13) -------------------------------------------------------------------------------
check(L::username_reveals('fixfirst.fixlast', 'Fixfirst', 'Fixlast'), 'a name-based username');
check(L::username_reveals('jfixlast2', 'Jo', 'Fixlast'), 'surname inside a token');
check(L::username_reveals('jo.k', 'Jo', 'Kim'), 'a short first name as a whole token');
check(!L::username_reveals('joker99', 'Jo', 'Li'), 'a short name inside another word does not count');
check(!L::username_reveals('ltct-u4821', 'Fixfirst', 'Fixlast'), 'a neutral username');
check(L::username_reveals('van.fixlast', 'Ann', "van Fixlast"), 'each part of a multi-part name');
check(!L::username_reveals('', 'Fixfirst', 'Fixlast'), 'an empty username reveals nothing');

// --- email addresses (scope review change 2) --------------------------------------------------------
check(L::email_reveals('fixfirst.fixlast@example.com', 'Fixfirst', 'Fixlast', '') === ['name'], 'the name before the @');
check(L::email_reveals('kestrel77@fixture-org.example', 'Fixfirst', 'Fixlast', 'fixture-org') === ['organisation'],
    'the organisation key in the domain');
check(L::email_reveals('kestrel77@example.com', 'Fixfirst', 'Fixlast', 'fixture-org') === [], 'a neutral address');
check(L::email_reveals('kestrel77@ab.example', 'Fixfirst', 'Fixlast', 'ab') === [], 'a key part under three characters is not looked for');
check(L::email_reveals('fixlast@fixture.example', 'Fixfirst', 'Fixlast', 'fixture-org') === ['name', 'organisation'],
    'both, in order');

// --- acknowledgement (R13) ---------------------------------------------------------------------------
check(L::needs_acknowledgement('none', 'pseudonym', true), 'a raise with activity');
check(L::needs_acknowledgement('pseudonym', 'none', true), 'a lowering with activity');
check(!L::needs_acknowledgement('none', 'pseudonym', false), 'no activity, no question');
check(!L::needs_acknowledgement('email', 'email', true), 'no change, no question');

// --- course mentors (R7 path 4) ----------------------------------------------------------------------
check(L::course_counts('ltct:fixture-course'), 'a published course counts');
check(!L::course_counts('ltct:officehours'), 'the office-hours course never counts');
check(!L::course_counts('ltct:fixture-course:extra') && !L::course_counts('fixture-course') && !L::course_counts(''),
    'anything else fails closed');

// --- preview (FR-012) ---------------------------------------------------------------------------------
check(L::preview('none') === ['name' => 'full', 'email' => 'shown', 'details' => 'shown', 'picture' => 'shown'], 'preview none');
check(L::preview('email')['email'] === 'hidden' && L::preview('email')['picture'] === 'shown', 'preview email');
check(L::preview('firstname')['name'] === 'firstname' && L::preview('firstname')['details'] === 'hidden', 'preview firstname');
check(L::preview('pseudonym')['name'] === 'pseudonym', 'preview pseudonym');

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
