<?php
// Harness for moodle/local_ltuse/classes/siteconfig/report.php (spec 001). Needs no Moodle:
// it stubs the admin classes. Run: php tests/report_harness.php < moodle/local_ltuse/classes/siteconfig/report.php
define('MOODLE_INTERNAL', 1);
class coding_exception extends Exception {}
class admin_setting {}
class admin_setting_configtext extends admin_setting {}
class admin_setting_configpasswordunmask extends admin_setting_configtext {}
class admin_setting_custompw extends admin_setting_configpasswordunmask {}
$src = file_get_contents('php://stdin');
$src = preg_replace('/^<\?php/', '', $src);
eval($src);
use local_ltuse\siteconfig\report;
$fails = 0;
function check($c, $m) { global $fails; if (!$c) { $fails++; echo "FAIL: $m\n"; } else { echo "ok: $m\n"; } }

// Line output, apply, streaming order and exit 0 with changes.
$buf = ''; $r = new report('apply', false, function($t) use (&$buf) { $buf .= $t; });
$r->mark_secrets([['name' => 'smtppass', 'secret' => true], ['name' => 'noreplyaddress', 'secret' => false]]);
$r->start('https://example.test', '5.2.3+');
check(strpos($buf, 'Target: https://example.test (Moodle 5.2.3+)') === 0, 'target line first');
$r->add('changed', 'changed', 'smtppass', 'hunter2xyz', '', 'write failed for hunter2xyz');
$r->add('ok', '', 'noreplyaddress', 'noreply@x', 'noreply@x');
$r->add('changed', 'changed', 'mail/pw', 'p4ssw0rd', 'old', '', new admin_setting_custompw());
$r->add('ok', '', 'enabletrusttext', 1, '1');
check(strpos($buf, 'hunter2xyz') === false, 'declared secret value never printed (incl. message)');
check(strpos($buf, 'p4ssw0rd') === false && strpos($buf, "'old'") === false, 'password subclass redacted though undeclared');
check(strpos($buf, "noreply@x") !== false, 'non-secret env value shown');
check($r->exit_code() === 0, 'apply with changes exits 0');
$code = $r->finish();
check($code === 0 && strpos($buf, '2 changed, 2 ok, 0 failed.') !== false, 'apply summary line');
echo $buf;

// Apply nothing changed.
$buf = ''; $r = new report('apply', false, function($t) use (&$buf) { $buf .= $t; });
$r->start('u', 'r'); $r->add('ok', '', 'a', 1, 1);
check($r->finish() === 0 && strpos($buf, 'Nothing changed') !== false, 'apply clean says nothing changed');

// Apply failure exits 1.
$r = new report('apply', false, function($t) {});
$r->add('fail', 'env-missing', 'smtppass', null, null, 'MOODLE_SMTPPASS is not set');
check($r->finish() === 1, 'apply fail exits 1');

// Drift: difference exits 1, clean exits 0.
$buf = ''; $r = new report('drift', false, function($t) use (&$buf) { $buf .= $t; });
$r->start('u', 'r');
$r->add('changed', 'unmanaged', 'foo', 'x', '5');
check(strpos($buf, "declared") === false, 'unmanaged carries no declared value');
$r->add('changed', 'missing', 'mod_x', '2026', 'y');
check(strpos($buf, "live 'y'") === false, 'missing carries no live value');
check($r->finish() === 1 && strpos($buf, '2 differences') !== false, 'drift with differences exits 1');
$r = new report('drift', false, function($t) {}); $r->add('ok', '', 'a', 1, 1);
check($r->finish() === 0, 'drift clean exits 0');

// Usage error exits 2.
$buf = ''; $r = new report('drift', false, function($t) use (&$buf) { $buf .= $t; });
$r->usage_error('MOODLE_URL does not match wwwroot');
check($r->finish() === 2 && strpos($buf, 'Nothing was changed') !== false, 'usage error exits 2');

// JSON shape.
$buf = ''; $r = new report('drift', true, function($t) use (&$buf) { $buf .= $t; });
$r->mark_secret('smtppass');
$r->start('https://example.test', '5.2.3+');
check($buf === '', 'json buffers until finish');
$r->add('changed', 'changed', 'smtppass', null, '');
$r->add('changed', 'forced', 'debug', '0', '32767');
$r->finish();
$doc = json_decode($buf, true);
check(is_array($doc) && array_keys($doc) === ['target','release','mode','items','summary'], 'json top-level keys');
check($doc['summary'] === ['changed' => 2, 'failed' => 0, 'differences' => 2], 'json summary');
check($doc['items'][0]['live'] === '<secret>' && !isset($doc['items'][0]['declared']), 'json secret redacted, empty-live still secret');
check(array_keys($doc['items'][1]) === ['status','kind','item','declared','live','message'], 'json item keys');

// Bad inputs.
try { new report('bogus'); check(false, 'bad mode rejected'); } catch (coding_exception $e) { check(true, 'bad mode rejected'); }
try { $r->add('nope', '', 'x'); check(false, 'bad status rejected'); } catch (coding_exception $e) { check(true, 'bad status rejected'); }
try { $r->add('fail', '', 'x'); check(false, 'fail without kind rejected'); } catch (coding_exception $e) { check(true, 'fail without kind rejected'); }

// Spec 002 kinds: adopted is a change, the other three are failures.
$buf = ''; $r = new report('apply', false, function($t) use (&$buf) { $buf .= $t; });
$r->start('u', 'r');
$r->add('changed', 'adopted', 'category:ltct:published', 'LTC Published', null, 'adopted');
check(strpos($buf, '[changed] category:ltct:published (adopted)') !== false, 'adopted reported as changed');
check($r->finish() === 0, 'apply with an adoption exits 0');
$r = new report('drift', false, function($t) {});
$r->add_result(['item' => 'cohort:ltct:org:fixture-a', 'result' => 'wrong-context', 'declared' => 'system',
    'live' => 'category 5', 'message' => '', 'secret' => false, 'blocking' => true]);
$r->add_result(['item' => 'category:ltct:pilots', 'result' => 'ambiguous', 'declared' => 'LTC Pilots',
    'live' => null, 'message' => 'candidates 2, 7', 'secret' => false, 'blocking' => true]);
$r->add_result(['item' => 'profilefield:ltct_org', 'result' => 'wrong-datatype', 'declared' => 'menu',
    'live' => 'text', 'message' => '', 'secret' => false, 'blocking' => true]);
check($r->has_failures() && $r->summary()['failed'] === 3, 'new blocking kinds are failures');
check($r->finish() === 1, 'drift with a new blocking kind exits 1');
try { $r->add('fail', 'adopted', 'category:x'); check(false, 'adopted as fail rejected'); }
catch (coding_exception $e) { check(true, 'adopted as fail rejected'); }
try { $r->add('changed', 'ambiguous', 'category:x'); check(false, 'ambiguous as changed rejected'); }
catch (coding_exception $e) { check(true, 'ambiguous as changed rejected'); }

echo $fails ? "FAILURES: $fails\n" : "ALL PASSED\n";
exit($fails ? 1 : 0);
