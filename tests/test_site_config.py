"""Unit tests for scripts/site_config.py (specs 001 and 002). Run: python -m pytest tests/"""
import contextlib, io, json, pathlib, shutil, sys, tempfile, textwrap, unittest
import yaml
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

# Spec 002 fixtures. Organisation keys are fixture-* (plus the required `independent`),
# never the instance-test keys, so T038's leak check for those keys stays meaningful.
# `independent` is declared before `fixture-north` so declaration order differs from sorted.
ORGS = """\
rows: [8, 15]
purpose: fixture organisations
categories:
  - key: published
    name: LTC Published
    why: shared curriculum
  - key: pilots
    name: LTC Pilots
    why: pilots
  - key: organisations
    name: Partner organisations
    why: parent of every organisation category
organisations:
  - key: independent
    name: Independent
  - key: fixture-north
    name: Fixture North
"""
with open(REPO / "competencies.yaml", encoding="utf-8") as _fh:
    AREAS = [a for a in yaml.safe_load(_fh) if a != "Meta"]
EXP_FIELD = """\
  - shortname: ltct_exp_{n}
    datatype: checkbox
    name: {area}
    visible: all
    locked: 0
    required: 0
    area: {area}
    why: expertise
"""
PROFILE = """\
rows: [8, 18]
purpose: fixture profile fields
category:
  name: About your work
fields:
  - shortname: ltct_org
    datatype: menu
    name: Organisation
    visible: all
    locked: 1
    required: 0
    options_from: organisations
    why: cohort rules
  - shortname: ltct_role
    datatype: menu
    name: Role in the work
    visible: teachers
    locked: 0
    required: 0
    options: [Fixture role one, Fixture role two]
    why: profiles
""" + "".join(EXP_FIELD.format(n=i, area=json.dumps(a)) for i, a in enumerate(AREAS))
# A self-contained roles.yaml for the orgmanager cases, so they do not depend on whether the
# tracked roles.yaml declares orgmanager yet (T024).
ORGMANAGER_ROLES = """\
roles:
  - shortname: orgmanager
    name: Organisation manager
    description: fixture
    archetype: ""
    contextlevels: [course]
    capabilities:
      moodle/course:viewparticipants: allow
      moodle/user:viewdetails: allow
      moodle/site:viewuseridentity: allow
    why: fixture
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
        self.write("organisations.yaml", ORGS)
        self.write("profile-fields.yaml", PROFILE)

    def tearDown(self):
        shutil.rmtree(self.dir)

    def reset(self):
        self.tearDown()
        self.setUp()

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

    # Spec 002 cases assert only whether validate passes, never how an error is worded.
    def assertRejected(self):
        self.assertTrue(self.errors(), "validate passed but should have failed")

    def assertAccepted(self):
        self.assertEqual(self.errors(), [])


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


class Organisations(Base):
    """organisations.yaml (spec 002 contracts/declaration.md, data-model.md)."""
    NORTH = "  - key: fixture-north\n    name: Fixture North\n"

    def add_org(self, key, name):
        self.edit("organisations.yaml", self.NORTH,
                  self.NORTH + "  - key: %s\n    name: %s\n" % (json.dumps(key), json.dumps(name)))

    def add_category(self, text):
        self.edit("organisations.yaml", "organisations:\n  - key: independent",
                  text + "organisations:\n  - key: independent")

    def test_baseline_valid(self):
        self.assertAccepted()

    def test_key_pattern(self):
        for bad in ("Fixture-North", "fixture_north", "1fixture", "fixture:north", "-fixture",
                    "fixture north"):
            with self.subTest(key=bad):
                self.reset()
                self.edit("organisations.yaml", "key: fixture-north", "key: %s" % json.dumps(bad))
                self.assertRejected()

    def test_key_of_30_characters(self):
        self.edit("organisations.yaml", "key: fixture-north", "key: fixture-" + "n" * 22)
        self.assertAccepted()

    def test_key_of_31_characters(self):
        self.edit("organisations.yaml", "key: fixture-north", "key: fixture-" + "n" * 23)
        self.assertRejected()

    def test_duplicate_organisation_key(self):
        self.add_org("fixture-north", "Fixture Other")
        self.assertRejected()

    def test_duplicate_organisation_name(self):
        self.add_org("fixture-south", "Fixture North")
        self.assertRejected()

    def test_duplicate_category_key(self):
        self.add_category("  - key: pilots\n    name: Other pilots\n    why: w\n")
        self.assertRejected()

    def test_independent_required(self):
        self.edit("organisations.yaml", "  - key: independent\n    name: Independent\n", "")
        self.assertRejected()

    def test_shared_categories_required(self):
        blocks = {
            "published": "  - key: published\n    name: LTC Published\n    why: shared curriculum\n",
            "pilots": "  - key: pilots\n    name: LTC Pilots\n    why: pilots\n",
            "organisations": "  - key: organisations\n    name: Partner organisations\n"
                             "    why: parent of every organisation category\n",
        }
        for key, block in blocks.items():
            with self.subTest(category=key):
                self.reset()
                self.edit("organisations.yaml", block, "")
                self.assertRejected()

    def test_shared_key_org(self):
        self.add_category("  - key: org\n    name: Org\n    why: w\n")
        self.assertRejected()

    def test_parent_declared_earlier(self):
        self.add_category("  - key: archive\n    name: Archive\n    parent: published\n    why: w\n")
        self.assertAccepted()

    def test_parent_unknown(self):
        self.add_category("  - key: archive\n    name: Archive\n    parent: nowhere\n    why: w\n")
        self.assertRejected()

    def test_unknown_key_on_organisation(self):
        self.edit("organisations.yaml", self.NORTH, self.NORTH + "    email: someone@example.org\n")
        self.assertRejected()

    def test_why_on_organisation(self):
        self.edit("organisations.yaml", self.NORTH, self.NORTH + "    why: a partner\n")
        self.assertRejected()

    def test_unknown_row(self):
        self.edit("organisations.yaml", "rows: [8, 15]", "rows: [8, 999]")
        self.assertRejected()

    def test_options_from_without_organisations_file(self):
        (self.dir / "organisations.yaml").unlink()
        self.assertRejected()

    def test_options_from_with_no_organisations(self):
        self.edit("organisations.yaml",
                  "organisations:\n  - key: independent\n    name: Independent\n" + self.NORTH,
                  "organisations: []\n")
        self.assertRejected()


class ProfileFields(Base):
    """profile-fields.yaml (spec 002 contracts/declaration.md, data-model.md)."""
    ROLE_OPTIONS = "    options: [Fixture role one, Fixture role two]\n"
    ORG_BLOCK = ("  - shortname: ltct_org\n    datatype: menu\n    name: Organisation\n"
                 "    visible: all\n    locked: 1\n    required: 0\n"
                 "    options_from: organisations\n    why: cohort rules\n")

    def add_field(self, text):
        self.write("profile-fields.yaml", PROFILE + text)

    def exp(self, n, area):
        return EXP_FIELD.format(n=n, area=json.dumps(area))

    def test_baseline_valid(self):
        self.assertAccepted()

    def test_shortname_needs_prefix(self):
        self.edit("profile-fields.yaml", "shortname: ltct_role", "shortname: role")
        self.assertRejected()

    def test_duplicate_shortname(self):
        self.edit("profile-fields.yaml", "shortname: ltct_role", "shortname: ltct_org")
        self.assertRejected()

    def test_org_field_required(self):
        self.edit("profile-fields.yaml", self.ORG_BLOCK, "")
        self.assertRejected()

    def test_org_field_locked(self):
        self.edit("profile-fields.yaml", "    locked: 1\n", "    locked: 0\n")
        self.assertRejected()

    def test_locked_yaml_boolean(self):
        self.edit("profile-fields.yaml", "    locked: 1\n", "    locked: true\n")
        self.assertRejected()

    def test_org_field_options_instead_of_options_from(self):
        self.edit("profile-fields.yaml", "    options_from: organisations\n",
                  "    options: [independent, fixture-north]\n")
        self.assertRejected()

    def test_options_and_options_from_together(self):
        self.edit("profile-fields.yaml", "    options_from: organisations\n",
                  "    options_from: organisations\n    options: [independent, fixture-north]\n")
        self.assertRejected()

    def test_options_from_on_a_second_field(self):
        self.edit("profile-fields.yaml", self.ROLE_OPTIONS, "    options_from: organisations\n")
        self.assertRejected()

    def test_options_from_unknown_source(self):
        self.edit("profile-fields.yaml", "options_from: organisations", "options_from: countries")
        self.assertRejected()

    def test_menu_without_options(self):
        self.edit("profile-fields.yaml", self.ROLE_OPTIONS, "")
        self.assertRejected()

    def test_duplicate_option(self):
        self.edit("profile-fields.yaml", self.ROLE_OPTIONS,
                  "    options: [Fixture role one, Fixture role one]\n")
        self.assertRejected()

    def test_empty_option(self):
        self.edit("profile-fields.yaml", self.ROLE_OPTIONS, "    options: [Fixture role one, \"\"]\n")
        self.assertRejected()

    def test_area_not_verbatim(self):
        self.edit("profile-fields.yaml", "area: %s" % json.dumps(AREAS[0]),
                  "area: %s" % json.dumps(AREAS[0].lower()))
        self.assertRejected()

    def test_area_meta(self):
        self.add_field(self.exp("meta", "Meta"))
        self.assertRejected()

    def test_area_missing(self):
        self.edit("profile-fields.yaml", self.exp(len(AREAS) - 1, AREAS[-1]), "")
        self.assertRejected()

    def test_area_twice(self):
        self.add_field(self.exp("again", AREAS[0]))
        self.assertRejected()

    def test_description_on_field(self):
        self.edit("profile-fields.yaml", self.ROLE_OPTIONS, self.ROLE_OPTIONS + "    description: x\n")
        self.assertRejected()

    def test_default_on_field(self):
        self.edit("profile-fields.yaml", self.ROLE_OPTIONS,
                  self.ROLE_OPTIONS + "    default: Fixture role one\n")
        self.assertRejected()

    def test_required_one(self):
        self.edit("profile-fields.yaml", "    locked: 0\n    required: 0\n    options:",
                  "    locked: 0\n    required: 1\n    options:")
        self.assertRejected()

    def test_unknown_datatype(self):
        self.edit("profile-fields.yaml", "    datatype: menu\n    name: Role", "    datatype: text\n    name: Role")
        self.assertRejected()

    def test_unknown_visibility(self):
        self.edit("profile-fields.yaml", "visible: teachers", "visible: everyone")
        self.assertRejected()

    def test_category_required(self):
        self.edit("profile-fields.yaml", "category:\n  name: About your work\n", "")
        self.assertRejected()


# The mentor role. Every roles.yaml must declare it once local_ltuse defines its capability
# (spec 003 FR-001), so the self-contained role fixtures carry it too.
MENTOR_ENTRY = """\
  - shortname: mentor
    name: Mentor
    description: fixture
    archetype: ""
    contextlevels: [user]
    capabilities:
      moodle/user:viewdetails: allow
      moodle/user:viewuseractivitiesreport: allow
      local/ltuse:viewmenteeprogress: allow
    why: fixture
"""


class OrgManager(Base):
    """The orgmanager role's deny list and the one-role-for-every-partner rule (SC-005)."""
    LAST_CAP = "      moodle/site:viewuseridentity: allow\n"

    def setUp(self):
        super().setUp()
        self.write("roles.yaml", ORGMANAGER_ROLES + MENTOR_ENTRY)

    def test_allowed_capabilities(self):
        self.assertAccepted()

    def test_denied_capabilities(self):
        for cap in ("moodle/user:viewalldetails", "moodle/site:accessallgroups", "moodle/user:create",
                    "moodle/user:update", "moodle/cohort:assign", "moodle/cohort:manage",
                    "enrol/manual:enrol", "enrol/cohort:config", "moodle/role:assign"):
            with self.subTest(capability=cap):
                self.reset()
                self.edit("roles.yaml", self.LAST_CAP, self.LAST_CAP + "      %s: allow\n" % cap)
                self.assertRejected()

    def test_prohibit(self):
        self.edit("roles.yaml", "moodle/user:viewdetails: allow", "moodle/user:viewdetails: prohibit")
        self.assertRejected()

    def test_contextlevels_course_only(self):
        self.edit("roles.yaml", "contextlevels: [course]", "contextlevels: [system, course]")
        self.assertRejected()

    def test_role_named_for_an_organisation(self):
        self.write("roles.yaml", ORGMANAGER_ROLES + MENTOR_ENTRY + "  - shortname: independent_viewer\n"
                   "    name: Independent viewer\n    archetype: \"\"\n    contextlevels: [course]\n"
                   "    why: fixture\n")
        self.assertRejected()


# A self-contained roles.yaml for the mentor and allowassign cases (spec 003).
MENTOR_ROLES = """\
roles:
  - shortname: manager
    archetype: manager
    allowassign: [mentor]
    why: fixture
""" + MENTOR_ENTRY


class Mentor(Base):
    """The mentor role's allowlist (spec 003 research R2) and the allowassign key (R6)."""
    LAST_CAP = "      local/ltuse:viewmenteeprogress: allow\n"

    def setUp(self):
        super().setUp()
        self.write("roles.yaml", MENTOR_ROLES)

    def test_allowed(self):
        self.assertAccepted()

    def test_tracked_declaration_is_valid(self):
        self.assertEqual(sc.validate()[1].items, [])

    def test_capability_outside_allowlist(self):
        for cap in ("moodle/user:editprofile", "moodle/user:viewalldetails",
                    "moodle/competency:usercompetencyrate", "moodle/grade:viewall",
                    "moodle/user:readuserposts"):
            with self.subTest(capability=cap):
                self.reset()
                self.edit("roles.yaml", self.LAST_CAP, self.LAST_CAP + "      %s: allow\n" % cap)
                self.assertRejected()

    def test_prohibit(self):
        self.edit("roles.yaml", "moodle/user:viewdetails: allow", "moodle/user:viewdetails: prohibit")
        self.assertRejected()

    def test_contextlevels_user_only(self):
        for levels in ("[course, user]", "[system]", "[]"):
            with self.subTest(contextlevels=levels):
                self.reset()
                self.edit("roles.yaml", "contextlevels: [user]", "contextlevels: %s" % levels)
                self.assertRejected()

    def test_archetype_must_be_empty(self):
        self.edit("roles.yaml", '    archetype: ""\n    contextlevels: [user]',
                  "    archetype: teacher\n    contextlevels: [user]")
        self.assertRejected()

    def test_archetype_must_be_declared(self):
        self.edit("roles.yaml", '    archetype: ""\n    contextlevels: [user]',
                  "    contextlevels: [user]")
        self.assertRejected()

    def test_allowassign_unknown_role(self):
        self.edit("roles.yaml", "allowassign: [mentor]", "allowassign: [nosuchrole]")
        self.assertRejected()

    def test_allowassign_core_role(self):
        self.edit("roles.yaml", "allowassign: [mentor]", "allowassign: [mentor, teacher]")
        self.assertAccepted()

    def test_allowassign_duplicate(self):
        self.edit("roles.yaml", "allowassign: [mentor]", "allowassign: [mentor, mentor]")
        self.assertRejected()

    def test_allowassign_not_a_list(self):
        self.edit("roles.yaml", "allowassign: [mentor]", "allowassign: mentor")
        self.assertRejected()

    def test_allowassign_denied_on_orgmanager_and_mentor(self):
        # Asserted by message, so neither passes only because of some other error.
        self.write("roles.yaml", MENTOR_ROLES.replace(
            "    contextlevels: [user]\n", "    contextlevels: [user]\n    allowassign: [mentor]\n"))
        self.assertInvalid("mentor assigns no roles")
        self.write("roles.yaml", ORGMANAGER_ROLES.replace(
            "    contextlevels: [course]\n", "    contextlevels: [course]\n    allowassign: [teacher]\n")
            + MENTOR_ENTRY)
        self.assertInvalid("orgmanager assigns no roles")

    def test_mentor_role_required_once_the_capability_exists(self):
        # FR-001: local_ltuse at this pin defines local/ltuse:viewmenteeprogress.
        self.write("roles.yaml", ORGMANAGER_ROLES)
        self.assertInvalid("the mentor role is missing")

    def test_allowassign_rendered(self):
        rc, out, err = self.run_main("render")
        self.assertEqual(rc, 0, err)
        roles = {r["shortname"]: r for r in json.loads(out)["roles"]}
        self.assertEqual(roles["manager"]["allowassign"], ["mentor"])
        self.assertEqual(roles["mentor"]["allowassign"], [])

    def test_mentoring_settings_file(self):
        self.write("settings/mentoring.yaml",
                   (REPO / "moodle" / "site" / "settings" / "mentoring.yaml").read_text())
        self.assertAccepted()


class Expansion(Base):
    """One organisation entry becomes one category, two cohorts and one rule (FR-002)."""

    def payload(self):
        rc, out, err = self.run_main("render")
        self.assertEqual(rc, 0, err)
        return json.loads(out)

    def test_one_entry_one_shape(self):
        p = self.payload()
        keys = ["independent", "fixture-north"]
        cats = {c["idnumber"]: c for c in p["categories"]}
        order = [c["idnumber"] for c in p["categories"]]
        for key in ("published", "pilots", "organisations"):
            self.assertIn("ltct:" + key, cats)
        cohorts = {c["idnumber"]: c for c in p["cohorts"]}
        for key in keys:
            with self.subTest(organisation=key):
                cat = cats["ltct:org:" + key]
                self.assertEqual(cat["parent_idnumber"], "ltct:organisations")
                self.assertLess(order.index("ltct:organisations"), order.index("ltct:org:" + key))
                for idn in ("ltct:org:" + key, "ltct:org:%s:managers" % key):
                    self.assertIn(idn, cohorts)
                    self.assertEqual(cohorts[idn]["visible"], 0)
                rules = [r for r in p["cohort_rules"] if r["cohort_idnumber"] == "ltct:org:" + key]
                self.assertEqual(len(rules), 1)
                self.assertEqual(rules[0]["field"], "ltct_org")
                self.assertEqual(rules[0]["value"], key)
        self.assertEqual(cohorts["ltct:org:fixture-north"]["name"], "Fixture North")
        self.assertEqual(cohorts["ltct:org:fixture-north:managers"]["name"], "Fixture North managers")
        self.assertEqual(len(p["categories"]), 3 + len(keys))
        self.assertEqual(len(p["cohorts"]), 2 * len(keys))
        self.assertEqual(len(p["cohort_rules"]), len(keys))

    def test_org_field_options_in_declaration_order(self):
        fields = {f["shortname"]: f for f in self.payload()["profile_fields"]}
        options = fields["ltct_org"]["options"]
        if isinstance(options, str):
            options = options.split("\n")
        self.assertEqual(options, ["independent", "fixture-north"])
        self.assertNotIn("options_from", fields["ltct_org"])

    def test_countries_key_rejected(self):
        self.edit("organisations.yaml", "organisations:\n  - key: independent",
                  "countries: [fixture-land]\norganisations:\n  - key: independent")
        self.assertRejected()


DISCUSSIONS = """\
# test declaration
rows: [10]
shared:
  - slug: coretech-computer-hardware
    why: generic hardware Q&A, partners asked to pool answers
"""


class Discussions(Base):
    """moodle/site/course-discussions.yaml (spec 012, FR-015, contracts/site-declaration.md)."""

    def setUp(self):
        super().setUp()
        self.write("course-discussions.yaml", DISCUSSIONS)

    def test_valid(self):
        self.assertEqual(self.errors(), [])
        rc, out, _ = self.run_main("validate")
        self.assertEqual(rc, 0, out)

    def test_absent_file_is_valid(self):
        (self.dir / "course-discussions.yaml").unlink()
        self.assertEqual(self.errors(), [])
        shared, problems = sc.load_discussions(self.dir)
        self.assertEqual(shared, [])
        self.assertFalse(problems)

    def test_empty_list_valid(self):
        self.write("course-discussions.yaml", "rows: [10]\nshared: []\n")
        self.assertEqual(self.errors(), [])
        self.write("course-discussions.yaml", "rows: [10]\nshared:\n")
        self.assertEqual(self.errors(), [])
        self.assertEqual(sc.load_discussions(self.dir)[0], [])

    def test_unknown_slug(self):
        self.edit("course-discussions.yaml", "slug: coretech-computer-hardware", "slug: no-such-course")
        self.assertInvalid("no-such-course is not a course under modules/")

    def test_slug_uses_branch_slug(self):
        # A legacy folder with spaces is declared by its branch_slug() form, never its folder name.
        self.edit("course-discussions.yaml", "slug: coretech-computer-hardware", "slug: paratext-9-advanced-support")
        self.assertEqual(self.errors(), [])
        self.edit("course-discussions.yaml", "slug: paratext-9-advanced-support", "slug: Paratext 9 advanced support")
        self.assertInvalid("write it as paratext-9-advanced-support")

    def test_template_is_not_a_course(self):
        self.edit("course-discussions.yaml", "slug: coretech-computer-hardware", "slug: template")
        self.assertInvalid("is not a course")

    def test_missing_why(self):
        self.edit("course-discussions.yaml", "    why: generic hardware Q&A, partners asked to pool answers\n", "")
        self.assertInvalid("missing required key 'why'")

    def test_blank_why(self):
        self.edit("course-discussions.yaml", "why: generic hardware Q&A, partners asked to pool answers", "why: ''")
        self.assertInvalid("why must say")

    def test_duplicate_slug(self):
        self.write("course-discussions.yaml", DISCUSSIONS + "  - slug: coretech-computer-hardware\n    why: again\n")
        self.assertInvalid("coretech-computer-hardware is declared twice")

    def test_unknown_key(self):
        self.edit("course-discussions.yaml", "    why:", "    shared: true\n    why:")
        self.assertInvalid("unknown key 'shared'")

    def test_unknown_row(self):
        self.edit("course-discussions.yaml", "rows: [10]", "rows: [999]")
        self.assertInvalid("row 999")

    def test_missing_shared(self):
        self.write("course-discussions.yaml", "rows: [10]\n")
        self.assertInvalid("missing required key 'shared'")

    def test_load_returns_entries(self):
        shared, problems = sc.load_discussions(self.dir)
        self.assertFalse(problems)
        self.assertEqual([e["slug"] for e in shared], ["coretech-computer-hardware"])

    def test_payload_carries_slugs_not_reasons(self):
        rc, out, _ = self.run_main("render", "--mode", "drift", environ={"MOODLE_URL": "https://m.example"})
        self.assertEqual(rc, 0)
        p = json.loads(out)
        self.assertEqual(p["discussions"], {"shared": ["coretech-computer-hardware"]})
        self.assertNotIn("pool answers", out)

    def test_payload_empty_when_absent(self):
        (self.dir / "course-discussions.yaml").unlink()
        rc, out, _ = self.run_main("render")
        self.assertEqual(json.loads(out)["discussions"], {"shared": []})


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
