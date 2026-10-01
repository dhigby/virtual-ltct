"""Unit tests for scripts/site_config.py (spec 001). Run: python -m pytest tests/"""
import contextlib, io, json, pathlib, shutil, sys, tempfile, textwrap, unittest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import site_config as sc

SITE = """\
moodle:
  requires: 2026042003.03
  release: "5.2.3+ (Build: 20260928)"
plugins:
  - component: local_ltuse
    version: {ver}
    source: {{path: moodle/local_ltuse}}
    why: publisher
  - component: mod_scorm
    enabled: 1
    why: "#3"
  - component: filter_displayh5p
    enabled: on
    why: "#3"
  - component: message_airnotifier
    enabled: 0
    why: "#25"
  - component: mod_fancy
    version: 2025010100
    source: {{url: "https://example.org/mod_fancy.zip", sha256: "{sha}"}}
    why: test
"""
ROLES = (REPO / "moodle" / "site" / "roles.yaml").read_text()
SETTINGS = """\
rows: [3, 4]
purpose: test file
settings:
  - name: enabletrusttext
    value: 1
    why: embeds
  - name: scorm/scormstandard
    value: 0
    why: SCORM 1.2 rules
  - name: mobilecssurl
    value: env:MOODLE_URL/local/ltuse/styles.css
    why: mobile
  - name: smtppass
    value: env:SMTP_PASS
    secret: true
    why: mail
  - name: noreplyaddress
    value: env:MOODLE_NOREPLY
    why: mail
  - name: tool_dataprivacy/dporoles
    value: [manager]
    why: "#16"
  - name: tool_mobile/disabledfeatures
    value: ""
    why: "#4"
"""
IGNORE = """\
ignore:
  - setting: timezone
    reason: per install
"""

VER = sc._php_stamp(sc.LTUSE_VERSION, "version")


class Base(unittest.TestCase):
    def setUp(self):
        self.dir = pathlib.Path(tempfile.mkdtemp())
        (self.dir / "settings").mkdir()
        self.write("site.yaml", SITE.format(ver=VER, sha="a" * 64))
        self.write("roles.yaml", ROLES)
        self.write("ignore.yaml", IGNORE)
        self.write("settings/test.yaml", SETTINGS)

    def tearDown(self):
        shutil.rmtree(self.dir)

    def write(self, name, text):
        (self.dir / name).write_text(text, encoding="utf-8")

    def edit(self, name, old, new):
        p = self.dir / name
        t = p.read_text()
        assert old in t, old
        p.write_text(t.replace(old, new))

    def run_main(self, *argv, environ=None):
        out, err = io.StringIO(), io.StringIO()
        with contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
            rc = sc.main(list(argv) + ["--site-dir", str(self.dir)], environ or {})
        return rc, out.getvalue(), err.getvalue()

    def errors(self):
        return sc.validate(self.dir)[1].items

    def assertInvalid(self, needle):
        errs = self.errors()
        self.assertTrue(any(needle in e for e in errs), "%r not in %s" % (needle, errs))


class Validate(Base):
    def test_valid(self):
        rc, out, _ = self.run_main("validate")
        self.assertEqual(rc, 0, out)
        self.assertIn("[ok] declaration valid", out)

    def test_json(self):
        rc, out, _ = self.run_main("validate", "--json")
        self.assertEqual(json.loads(out)["valid"], True)

    def test_yaml_bool_value(self):
        self.edit("settings/test.yaml", "value: 1\n    why: embeds", "value: off\n    why: embeds")
        self.assertInvalid("YAML boolean 'off'")

    def test_float_value(self):
        self.edit("settings/test.yaml", "value: 0\n    why: SCORM", "value: 1.2\n    why: SCORM")
        self.assertInvalid("is a float")

    def test_duplicate_across_files(self):
        self.write("settings/other.yaml", "rows: [3]\npurpose: p\nsettings:\n  - name: enabletrusttext\n    value: 1\n    why: w\n")
        self.assertInvalid("already declared")

    def test_duplicate_yaml_key(self):
        self.edit("ignore.yaml", "reason: per install", "reason: a\n    reason: b")
        self.assertInvalid("duplicate key")

    def test_unknown_top_key(self):
        self.edit("site.yaml", "plugins:", "extras: 1\nplugins:")
        self.assertInvalid("unknown key 'extras'")

    def test_unexpected_file(self):
        self.write("plugins.yaml", "x: 1\n")
        self.assertInvalid("unexpected file")

    def test_missing_site(self):
        (self.dir / "site.yaml").unlink()
        self.assertInvalid("site.yaml: missing")

    def test_literal_secret_name(self):
        self.edit("settings/test.yaml", "value: env:SMTP_PASS\n    secret: true", "value: hunter2")
        self.assertInvalid("looks like a secret")

    def test_secret_without_env(self):
        self.edit("settings/test.yaml", "value: env:SMTP_PASS", "value: hunter2")
        self.assertInvalid("secret: true needs an env:")

    def test_secret_env_without_flag(self):
        self.edit("settings/test.yaml", "value: env:SMTP_PASS\n    secret: true", "value: env:SMTP_PASS")
        self.assertInvalid("looks like a secret")

    def test_secret_looking_value(self):
        self.edit("settings/test.yaml", 'value: ""', "value: 9f86d081884c7d659a2feaa0c55ad015")
        self.assertInvalid("looks like a literal secret")

    def test_non_secret_allowlist(self):
        self.write("settings/pw.yaml", "rows: [3]\npurpose: p\nsettings:\n  - name: passwordpolicy\n    value: 1\n    why: w\n")
        self.assertEqual(self.errors(), [])

    def test_hard_coded_host(self):
        self.edit("settings/test.yaml", "env:MOODLE_URL/local/ltuse/styles.css", "https://ltuse.net/local/ltuse/styles.css")
        self.assertInvalid("hard-coded host")

    def test_source_url_allowed(self):
        self.assertEqual(self.errors(), [])

    def test_malformed_env(self):
        self.edit("settings/test.yaml", "env:MOODLE_NOREPLY", "env:moodle_noreply")
        self.assertInvalid("malformed environment reference")

    def test_ignore_declared(self):
        self.edit("ignore.yaml", "setting: timezone", "setting: enabletrusttext")
        self.assertInvalid("cannot also be ignored")

    def test_ignore_no_reason(self):
        self.edit("ignore.yaml", "reason: per install", "reason: ''")
        self.assertInvalid("needs a reason")

    def test_pin_drift(self):
        self.edit("site.yaml", "version: %s" % VER, "version: 2020010100")
        self.assertInvalid("the pin cannot drift")

    def test_core_plugin_pinned(self):
        self.edit("site.yaml", "enabled: 1\n    why", "enabled: 1\n    version: 5\n    why")
        self.assertInvalid("a core plugin takes no version")

    def test_nonstandard_unpinned(self):
        self.edit("site.yaml", "  - component: mod_fancy\n    version: 2025010100\n", "  - component: mod_fancy\n")
        self.assertInvalid("needs a version pin")

    def test_bad_sha(self):
        self.edit("site.yaml", "a" * 64, "abc")
        self.assertInvalid("sha256")

    def test_filter_enabled_numeric(self):
        self.edit("site.yaml", "enabled: on", "enabled: 1")
        self.assertInvalid("a filter's enabled")

    def test_nonfilter_on(self):
        self.edit("site.yaml", "enabled: 1\n", "enabled: on\n")
        self.assertInvalid("enabled is 1 or 0")

    def test_duplicate_plugin(self):
        self.edit("site.yaml", "  - component: mod_fancy", "  - component: mod_scorm\n    why: dup\n  - component: mod_fancy")
        self.assertInvalid("declared twice")

    def test_requires_below_plugin(self):
        self.edit("site.yaml", "requires: 2026042003.03", "requires: 2025010100")
        self.assertInvalid("lower than local_ltuse")

    def test_requires_shape(self):
        self.edit("site.yaml", "requires: 2026042003.03", "requires: '5.2'")
        self.assertInvalid("YYYYMMDDXX.XX")

    def test_unknown_row(self):
        self.edit("settings/test.yaml", "rows: [3, 4]", "rows: [3, 999]")
        self.assertInvalid("row 999")

    def test_bad_filename(self):
        self.write("settings/Bad_Name.yaml", "rows: []\npurpose: p\nsettings: []\n")
        self.assertInvalid("lowercase-hyphenated")

    def test_unknown_role_ref(self):
        self.edit("settings/test.yaml", "[manager]", "[dpo]")
        self.assertInvalid("names role 'dpo'")

    def test_bad_permission(self):
        self.edit("roles.yaml", "moodle/site:trustcontent: inherit", "moodle/site:trustcontent: deny")
        self.assertInvalid("permission 'deny'")

    def test_bad_contextlevel(self):
        self.edit("roles.yaml", "[system, coursecat, course]", "[system, galaxy]")
        self.assertInvalid("contextlevels")

    def test_plugin_setting_undeclared(self):
        self.write("settings/x.yaml", "rows: [3]\npurpose: p\nsettings:\n  - name: mod_nothere/thing\n    value: 1\n    why: w\n")
        self.assertInvalid("neither core nor declared")

    def test_null_value(self):
        self.edit("settings/test.yaml", 'value: ""', "value:")
        self.assertInvalid("no value")


class Render(Base):
    def test_redacts(self):
        env = {"SMTP_PASS": "s3cr3t-value", "MOODLE_NOREPLY": "noreply@x", "MOODLE_URL": "https://m.example"}
        rc, out, _ = self.run_main("render", environ=env)
        self.assertEqual(rc, 0)
        self.assertNotIn("s3cr3t-value", out)
        self.assertNotIn("noreply@x", out)
        p = json.loads(out)
        s = {x["name"]: x for x in p["settings"]}
        self.assertEqual(s["smtppass"]["value"], "<secret>")
        self.assertEqual(s["noreplyaddress"]["value"], "env:MOODLE_NOREPLY")
        self.assertEqual(s["enabletrusttext"]["value"], "1")
        self.assertEqual(s["tool_dataprivacy/dporoles"]["value"], ["manager"])
        self.assertEqual(p["moodle"]["requires"], 2026042003.03)
        self.assertIn('"requires": 2026042003.03', out)
        self.assertEqual(p["mode"], "apply")
        self.assertNotIn("source", json.dumps(p["plugins"]))
        self.assertNotIn("why", json.dumps(p["roles"]))
        f = {x["component"]: x for x in p["plugins"]}
        self.assertEqual(f["filter_displayh5p"]["enabled"], "on")
        self.assertEqual(f["local_ltuse"]["version"], int(VER))

    def test_invalid_exit2(self):
        self.edit("settings/test.yaml", "value: 1\n    why: embeds", "value: off\n    why: embeds")
        rc, _, err = self.run_main("render")
        self.assertEqual(rc, 2)
        self.assertIn("YAML boolean", err)


class FakeRun:
    def __init__(self, rc=0, exc=None):
        self.rc, self.exc, self.calls = rc, exc, []

    def __call__(self, cmd, input=None, check=False):
        if self.exc:
            raise self.exc
        self.calls.append((cmd, input))
        class R: pass
        r = R(); r.returncode = self.rc
        return r


class Apply(Base):
    ENV = {"MOODLE_URL": "https://m.example/", "MOODLE_DIR": "/home/x/moodle", "SMTP_PASS": "s3cr3t-value",
           "MOODLE_NOREPLY": "noreply@x"}

    def go(self, mode, env, rc=0, as_json=False):
        decl, problems = sc.validate(self.dir)
        self.assertFalse(problems)
        fake = FakeRun(rc)
        err = io.StringIO()
        with contextlib.redirect_stderr(err):
            code = sc.run_remote(mode, decl, env, as_json=as_json, runner=fake)
        return code, fake, err.getvalue()

    def test_apply_local(self):
        code, fake, _ = self.go("apply", self.ENV)
        self.assertEqual(code, 0)
        cmd, data = fake.calls[0]
        self.assertEqual(cmd, ["php", "/home/x/moodle/public/local/ltuse/cli/site_config.php", "--mode=apply"])
        self.assertNotIn("s3cr3t-value", " ".join(cmd))
        p = json.loads(data)
        s = {x["name"]: x for x in p["settings"]}
        self.assertEqual(s["smtppass"]["value"], "s3cr3t-value")
        self.assertEqual(s["mobilecssurl"]["value"], "https://m.example/local/ltuse/styles.css")
        self.assertEqual(p["target_url"], "https://m.example/")
        self.assertEqual(p["failed_env"], [])

    def test_ssh(self):
        env = dict(self.ENV, MOODLE_SSH="ltuse")
        code, fake, _ = self.go("drift", env, as_json=True)
        cmd, data = fake.calls[0]
        self.assertEqual(cmd, ["ssh", "ltuse", "php /home/x/moodle/public/local/ltuse/cli/site_config.php --mode=drift --json"])
        p = json.loads(data)
        s = {x["name"]: x for x in p["settings"]}
        self.assertNotIn("value", s["smtppass"])
        self.assertTrue(s["smtppass"]["secret"])
        self.assertNotIn(b"s3cr3t-value", data)
        self.assertEqual(s["noreplyaddress"]["value"], "noreply@x")

    def test_env_missing(self):
        env = dict(self.ENV); del env["SMTP_PASS"]
        code, fake, _ = self.go("apply", env)
        self.assertEqual(code, 1)
        p = json.loads(fake.calls[0][1])
        self.assertEqual(p["failed_env"], [{"name": "smtppass", "env": "SMTP_PASS"}])
        s = {x["name"]: x for x in p["settings"]}
        self.assertNotIn("value", s["smtppass"])
        self.assertEqual(s["smtppass"]["env_missing"], "SMTP_PASS")

    def test_relays_exit(self):
        self.assertEqual(self.go("drift", self.ENV, rc=1)[0], 1)
        self.assertEqual(self.go("drift", self.ENV, rc=2)[0], 2)
        self.assertEqual(self.go("drift", self.ENV, rc=255)[0], 2)

    def test_missing_url(self):
        env = dict(self.ENV); del env["MOODLE_URL"]
        code, fake, err = self.go("apply", env)
        self.assertEqual(code, 2)
        self.assertEqual(fake.calls, [])
        self.assertIn("MOODLE_URL is not set", err)

    def test_missing_dir(self):
        env = dict(self.ENV); del env["MOODLE_DIR"]
        self.assertEqual(self.go("apply", env)[0], 2)

    def test_no_binary(self):
        decl, _ = sc.validate(self.dir)
        with contextlib.redirect_stderr(io.StringIO()):
            self.assertEqual(sc.run_remote("apply", decl, self.ENV, runner=FakeRun(exc=FileNotFoundError())), 2)

    def test_invalid_decl_exit2(self):
        self.edit("settings/test.yaml", "value: 1\n    why: embeds", "value: yes\n    why: embeds")
        rc, _, _ = self.run_main("apply", environ=self.ENV)
        self.assertEqual(rc, 2)


if __name__ == "__main__":
    unittest.main()
