"""Unit tests for scripts/site_config.py (specs 001, 002, 004, 006 and 013). Run: python -m pytest tests/"""
import contextlib, io, json, pathlib, shutil, sys, tempfile, textwrap, unittest
from unittest import mock
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
mentors:
  name: Mentors
  why: fixture mentor candidates
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
        self.assertEqual(len(p["cohorts"]), 2 * len(keys) + 1)   # + ltct:mentors
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


# The orgmanager deny list as of 2026-10-01. The amendment gives managers their actions through
# local_ltuse's pages, never through the role, so this list must not change (R2, R10).
ORGMANAGER_DENY_2026_10_01 = frozenset({"moodle/site:accessallgroups", "moodle/user:viewalldetails",
                                        "moodle/course:managegroups",
                                        "moodle/course:viewsuspendedusers"})

GROUPS = """\
rows: [8, 15]
purpose: fixture group defaults
settings:
  - name: moodlecourse/groupmode
    value: 0
    why: open courses
"""
MENTORS = "mentors:\n  name: Mentors\n  why: fixture mentor candidates\n"


class OpenCourses(Base):
    """Spec 002 amendment 2026-10-02: shared courses are open across organisations (R3, R14)."""

    def payload(self):
        rc, out, err = self.run_main("render")
        self.assertEqual(rc, 0, err)
        return json.loads(out)

    def test_groupmode_zero_accepted(self):
        self.write("settings/groups.yaml", GROUPS)
        self.assertAccepted()

    def test_groupmode_other_than_zero_rejected(self):
        for value in (1, 2):
            with self.subTest(groupmode=value):
                self.reset()
                self.write("settings/groups.yaml", GROUPS.replace("value: 0", "value: %d" % value))
                self.assertRejected()

    def test_course_discussions_file_is_retired(self):
        self.write("course-discussions.yaml", "rows: [10]\nshared: []\n")
        self.assertRejected()
        self.assertFalse(hasattr(sc, "load_discussions"))

    def test_payload_has_no_discussions_block(self):
        self.assertNotIn("discussions", self.payload())

    def test_mentors_cohort_generated(self):
        p = self.payload()
        cohorts = {c["idnumber"]: c for c in p["cohorts"]}
        self.assertEqual(cohorts["ltct:mentors"]["name"], "Mentors")
        self.assertEqual(cohorts["ltct:mentors"]["visible"], 0)
        self.assertEqual([r for r in p["cohort_rules"] if r["cohort_idnumber"] == "ltct:mentors"], [])

    def test_mentors_name_follows_declaration(self):
        self.edit("organisations.yaml", "  name: Mentors", "  name: Fixture mentors")
        cohorts = {c["idnumber"]: c for c in self.payload()["cohorts"]}
        self.assertEqual(cohorts["ltct:mentors"]["name"], "Fixture mentors")

    def test_mentors_required(self):
        self.edit("organisations.yaml", MENTORS, "")
        self.assertRejected()

    def test_mentors_needs_name_and_why(self):
        for old, new in (("  name: Mentors\n", ""), ("  why: fixture mentor candidates\n", ""),
                         ("  name: Mentors", "  name: ''"),
                         ("  why: fixture mentor candidates", "  why: ''")):
            with self.subTest(change=old.strip()):
                self.reset()
                self.edit("organisations.yaml", old, new)
                self.assertRejected()

    def test_mentors_unknown_key_rejected(self):
        self.edit("organisations.yaml", MENTORS, MENTORS + "  idnumber: ltct:fixture\n")
        self.assertRejected()

    def test_orgmanager_deny_list_unchanged(self):
        self.assertEqual(sc.ORGMANAGER_DENY, ORGMANAGER_DENY_2026_10_01)

    def test_tracked_orgmanager_keeps_viewuseridentity(self):
        roles = yaml.safe_load(ROLES)["roles"]
        om = [r for r in roles if r["shortname"] == "orgmanager"]
        self.assertEqual(len(om), 1)
        self.assertEqual(om[0]["capabilities"].get("moodle/site:viewuseridentity"), "allow")


ORG_COURSE_ENTRY = """\
  - slug: coretech-computer-hardware
    organisation: fixture-north
    why: approved by the maintainer, fixture issue
"""
ORG_COURSES = "rows: [8, 15]\norg_only:\n" + ORG_COURSE_ENTRY


class OrgCourses(Base):
    """org-courses.yaml (spec 002 R11, contracts/declaration.md, data-model.md)."""

    def setUp(self):
        super().setUp()
        self.write("org-courses.yaml", ORG_COURSES)

    def payload(self):
        rc, out, err = self.run_main("render")
        self.assertEqual(rc, 0, err)
        return json.loads(out)

    def test_valid(self):
        self.assertAccepted()
        rc, out, _ = self.run_main("validate")
        self.assertEqual(rc, 0, out)

    def test_absent_file_is_valid(self):
        (self.dir / "org-courses.yaml").unlink()
        self.assertAccepted()
        courses, problems = sc.load_org_courses(self.dir)
        self.assertEqual(courses, [])
        self.assertFalse(problems)

    def test_empty_list_valid(self):
        for text in ("rows: [8, 15]\norg_only: []\n", "rows: [8, 15]\norg_only:\n"):
            with self.subTest(text=text):
                self.write("org-courses.yaml", text)
                self.assertAccepted()
                self.assertEqual(sc.load_org_courses(self.dir)[0], [])

    def test_load_returns_entries(self):
        courses, problems = sc.load_org_courses(self.dir)
        self.assertFalse(problems, problems.items)
        self.assertEqual(courses, [{"slug": "coretech-computer-hardware",
                                    "organisation": "fixture-north",
                                    "why": "approved by the maintainer, fixture issue"}])

    def test_each_key_required(self):
        for entry in ("  - organisation: fixture-north\n    why: fixture\n",
                      "  - slug: coretech-computer-hardware\n    why: fixture\n",
                      "  - slug: coretech-computer-hardware\n    organisation: fixture-north\n"):
            with self.subTest(entry=entry):
                self.write("org-courses.yaml", "rows: [8, 15]\norg_only:\n" + entry)
                self.assertRejected()

    def test_blank_why(self):
        self.edit("org-courses.yaml", "why: approved by the maintainer, fixture issue", "why: ''")
        self.assertRejected()

    def test_unknown_key_in_entry(self):
        self.edit("org-courses.yaml", "    why:", "    hidden: true\n    why:")
        self.assertRejected()

    def test_unknown_top_key(self):
        self.write("org-courses.yaml", ORG_COURSES + "shared: []\n")
        self.assertRejected()

    def test_rows_required_and_known(self):
        self.write("org-courses.yaml", ORG_COURSES.replace("rows: [8, 15]\n", ""))
        self.assertRejected()
        self.write("org-courses.yaml", ORG_COURSES.replace("rows: [8, 15]", "rows: [999]"))
        self.assertRejected()

    def test_org_only_required(self):
        self.write("org-courses.yaml", "rows: [8, 15]\n")
        self.assertRejected()

    def test_unknown_slug(self):
        self.edit("org-courses.yaml", "slug: coretech-computer-hardware", "slug: no-such-course")
        self.assertRejected()

    def test_slug_uses_branch_slug(self):
        self.edit("org-courses.yaml", "slug: coretech-computer-hardware",
                  "slug: paratext-9-advanced-support")
        self.assertAccepted()
        self.edit("org-courses.yaml", "slug: paratext-9-advanced-support",
                  "slug: Paratext 9 advanced support")
        self.assertInvalid("paratext-9-advanced-support")

    def test_template_is_not_a_course(self):
        self.edit("org-courses.yaml", "slug: coretech-computer-hardware", "slug: template")
        self.assertRejected()

    def test_duplicate_slug(self):
        self.write("org-courses.yaml", ORG_COURSES + ORG_COURSE_ENTRY.replace(
            "fixture-north", "independent"))
        self.assertRejected()

    def test_organisation_must_be_declared(self):
        self.edit("org-courses.yaml", "organisation: fixture-north", "organisation: fixture-south")
        self.assertRejected()
        self.assertEqual(sc.load_org_courses(self.dir)[0], [])

    def test_organisation_needs_organisations_file(self):
        (self.dir / "organisations.yaml").unlink()
        (self.dir / "profile-fields.yaml").unlink()
        self.assertRejected()

    def test_payload_carries_category_not_reason(self):
        p = self.payload()
        self.assertEqual(p["org_courses"], [{
            "slug": "coretech-computer-hardware",
            "course_idnumber": "ltct:coretech-computer-hardware",
            "category_idnumber": "ltct:org:fixture-north"}])
        self.assertNotIn("fixture issue", json.dumps(p))

    def test_payload_empty_when_absent(self):
        (self.dir / "org-courses.yaml").unlink()
        self.assertEqual(self.payload()["org_courses"], [])

    def test_tracked_declaration_loads(self):
        # The committed file is what moodle_payload.py and publish_moodle.py use.
        courses, problems = sc.load_org_courses()
        self.assertFalse(problems, problems.items)


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


class AimLabel(unittest.TestCase):
    """Spec 004 FR-010: a label never shows a learner at a CBC level (T007)."""

    def refused(self, label, strict=False):
        problems = sc.Problems()
        sc._check_aim_label("fixture", label, problems, strict=strict)
        return bool(problems)

    def assertPasses(self, *labels, strict=False):
        for label in labels:
            self.assertFalse(self.refused(label, strict), label)

    def assertRefused(self, *labels, strict=False):
        for label in labels:
            self.assertTrue(self.refused(label, strict), label)

    def test_plain_labels_pass(self):
        self.assertPasses("Learner", "{org}: learner progress", "Course aims at",
                          "fixture-north: completions", "Target level (aim)")
        self.assertPasses("Learner", "{org}: learner progress", "Course aims at", strict=True)

    def test_certif_any_case(self):
        self.assertPasses("Completed courses")
        self.assertRefused("Certified", "CERTIFICATE", "Pre-certification", "uncertifiable")

    def test_retired_vocabulary_as_a_word(self):
        self.assertPasses("Beginner course", "Advanced settings", "Practice quiz")
        self.assertRefused("Advanced Beginner", "advanced  beginner progress",
                           "Practitioner", "fixture-north practitioner count",
                           "Trainer", "Proficient learners")

    def test_learner_near_level(self):
        self.assertPasses("Learner", "Learner progress by course with target level aims")
        self.assertRefused("Learner level", "Level of learner", "learner at CBC level",
                           "Learner: aimed level", "Learners by level", "Learner levels")

    def test_reached_near_level(self):
        self.assertPasses("Reached the end of the course", "Lessons achieved",
                          "Attained by, course aims at level")
        self.assertRefused("Level reached", "Achieved level", "attained CBC target level",
                           "reached: level 2", "Levels reached")

    def test_strict_only(self):
        for label in ("Reached the end of the course", "Lessons achieved", "Competent users"):
            self.assertFalse(self.refused(label), label)
            self.assertTrue(self.refused(label, strict=True), label)
        self.assertPasses("Competency coverage", "Competencies aimed at", strict=True)
        self.assertRefused("attained", "COMPETENT", strict=True)

    def test_appends_to_problems(self):
        problems = sc.Problems()
        sc._check_aim_label("fixture", "Learner level", problems)
        sc._check_aim_label("fixture", "Certified", problems)
        self.assertEqual(len(problems.items), 2)
        self.assertTrue(all(i.startswith("fixture: ") for i in problems.items))


# Spec 004 fixtures. Organisation keys are fixture-*, never instance-test or real keys.
COURSE_FIELDS = """\
rows: [7]
category: LTC curriculum
fields:
  - shortname: ltct_competencies
    name: Competencies this course aims at
    type: text
    locked: 1
    visibility: everyone
    why: fixture
  - shortname: ltct_target_level
    name: Level this course aims at
    type: text
    locked: 1
    visibility: everyone
    why: fixture
"""
PROGRESS = """\
  - key: progress
    per: organisation
    name: "{org}: learner progress"
    source: core_course\\reportbuilder\\datasource\\participants
    uniquerows: 1
    columns:
      - {column: user:fullnamewithlink, heading: Learner}
      - {column: user:profilefield_ltct_org, heading: Organisation}
      - {column: course:coursefullnamewithlink, heading: Course}
      - {column: completion:progresspercent, heading: Progress}
      - {column: completion:timecompleted, heading: Completed}
    conditions:
      - {condition: user:profilefield_ltct_org, values: {operator: equal, value: "{org}"}}
      - {condition: role:name, values: {operator: equal, value: student}}
      - {condition: enrol:plugin, values: {operator: not_equal, value: manual}}
    filters: [course:fullname, user:fullname]
    audiences:
      - {type: cohortmember, cohort: "ltct:org:{org}:managers"}
    schedule:
      recurrence: weekly
      format: excel
      viewas: recipient
      send_when_empty: 0
      start: "monday 07:00"
      subject: "{org}: weekly learner progress"
      message: "Your organisation's learner progress this week."
    why: fixture
"""
PROGRAMME = """\
  - key: programme
    name: "Programme: completions per course"
    source: core_course\\reportbuilder\\datasource\\participants
    uniquerows: 1
    columns:
      - {column: course:coursefullnamewithlink, heading: Course}
      - {column: course:customfield_ltct_competencies, heading: Competencies this course aims at}
      - {column: course:customfield_ltct_target_level, heading: Level this course aims at}
      - {column: user:username, heading: Enrolled, aggregation: countdistinct}
      - {column: completion:timecompleted, heading: Completed, aggregation: count}
    conditions:
      - {condition: role:name, values: {operator: equal, value: student}}
      - {condition: enrol:plugin, values: {operator: not_equal, value: manual}}
    filters: [user:profilefield_ltct_org, completion:timecompleted]
    audiences:
      - {type: systemrole, role: manager}
    why: fixture
"""
COVERAGE = """\
  - key: competency-coverage
    name: "Competencies published courses aim at: courses and delivery use"
    source: local_ltuse\\reportbuilder\\datasource\\competency_coverage
    uniquerows: 0
    columns:
      - {column: competency:category, heading: Category}
      - {column: competency:name, heading: Competency courses aim at}
      - {column: coverage:courses, heading: Courses that aim at it}
      - {column: coverage:learners, heading: Delivery learners}
    sorting:
      - {column: competency:name, direction: asc}
    conditions: []
    filters: [competency:category, competency:name]
    audiences:
      - {type: systemrole, role: manager}
    why: fixture
"""
PILOTS = """\
  - key: pilots
    name: "Pilots: learner progress"
    source: core_course\\reportbuilder\\datasource\\participants
    uniquerows: 1
    columns:
      - {column: user:fullnamewithlink, heading: Learner}
      - {column: course:coursefullnamewithlink, heading: Course}
      - {column: completion:progresspercent, heading: Progress}
    conditions:
      - {condition: role:name, values: {operator: equal, value: student}}
      - {condition: enrol:plugin, values: {operator: equal, value: manual}}
    filters: [course:fullname]
    audiences:
      - {type: systemrole, role: manager}
    why: fixture
"""
REPORTS_HEAD = "rows: [7, 16]\npurpose: fixture reports\nreports:\n"
REPORTS = REPORTS_HEAD + PROGRESS + PROGRAMME + COVERAGE + PILOTS
TWO_ORGS = ORGS.replace("  - key: fixture-north\n    name: Fixture North\n",
                        "  - key: fixture-a\n    name: Fixture A\n"
                        "  - key: fixture-b\n    name: Fixture B\n")
SCHEDULE = PROGRESS[PROGRESS.index("    schedule:"):PROGRESS.index("    why:")]


class ReportsBase(Base):
    def setUp(self):
        super().setUp()
        self.write("organisations.yaml", TWO_ORGS)
        self.write("course-fields.yaml", COURSE_FIELDS)
        self.write("reports.yaml", REPORTS)

    def payload(self):
        rc, out, err = self.run_main("render")
        self.assertEqual(rc, 0, err + out)
        return json.loads(out)

    def reports(self):
        return {r["area"]: r for r in self.payload()["reports"]}

    def only(self, *blocks):
        self.write("reports.yaml", REPORTS_HEAD + "".join(blocks))


class Reports(ReportsBase):
    """reports.yaml (spec 004 data-model "Report", contracts/declaration.md): T032."""

    def test_baseline_valid(self):
        self.assertAccepted()

    def test_reports_file_optional(self):
        (self.dir / "reports.yaml").unlink()
        self.assertAccepted()
        self.assertEqual(self.payload()["reports"], [])

    def test_expansion_one_per_organisation(self):
        reports = self.reports()
        for key, name in (("fixture-a", "Fixture A"), ("fixture-b", "Fixture B")):
            with self.subTest(organisation=key):
                r = reports["org_%s_progress" % key.replace("-", "_")]
                self.assertEqual(r["name"], "%s: learner progress" % name)
                conds = {c["condition"]: c["values"] for c in r["conditions"]}
                self.assertEqual(conds["user:profilefield_ltct_org"],
                                 {"operator": "equal", "value": key})
                self.assertEqual(r["audiences"], [{"type": "cohortmember",
                                                   "cohort": "ltct:org:%s:managers" % key}])
        progress = sorted(a for a in reports if a.startswith("org_"))
        self.assertEqual(progress, ["org_fixture_a_progress", "org_fixture_b_progress",
                                    "org_independent_progress"])
        self.assertIn("competency_coverage", reports)
        self.assertIn("programme", reports)

    def test_no_placeholder_left(self):
        self.assertNotIn("{org}", json.dumps(self.payload()["reports"]))

    def test_scope_conditions_required(self):
        for line in ('      - {condition: user:profilefield_ltct_org, values: {operator: equal, '
                     'value: "{org}"}}\n',
                     "      - {condition: role:name, values: {operator: equal, value: student}}\n",
                     "      - {condition: enrol:plugin, values: {operator: not_equal, value: manual}}\n"):
            with self.subTest(removed=line.strip()):
                self.assertIn(line, PROGRESS)
                self.only(PROGRESS.replace(line, ""))
                self.assertRejected()

    def test_scope_condition_values_verbatim(self):
        for old, new in (('value: "{org}"}}', "value: fixture-a}}"),
                         ("value: student}}", "value: editingteacher}}"),
                         # Spec 002 R10: delivery is any enrolment but a pilot's, so the
                         # 2026-10-01 form (cohort sync only) is refused, as is any other.
                         ("{operator: not_equal, value: manual}", "{operator: equal, value: cohort}"),
                         ("{operator: not_equal, value: manual}", "{operator: equal, value: manual}"),
                         ("{operator: not_equal, value: manual}", "{operator: not_equal, value: guest}"),
                         ("{operator: equal, value: student}", "{operator: notequal, value: student}"),
                         ("{operator: equal, value: student}", "{operator: not_equal, value: student}"),
                         ("{operator: not_equal, value: manual}", "{value: manual}")):
            with self.subTest(old=old, new=new):
                self.assertIn(old, PROGRESS)
                self.only(PROGRESS.replace(old, new, 1))
                self.assertRejected()

    def test_condition_values_shape(self):
        for old, new in (("{operator: equal, value: student}", "{operator: 1, value: student}"),
                         ("{operator: not_equal, value: manual}", "{operator: not_equal, value: manual, x: 1}"),
                         ("{operator: not_equal, value: manual}", "{operator: notequal, value: manual}"),
                         ("{operator: not_equal, value: manual}", "manual")):
            with self.subTest(new=new):
                self.only(PROGRAMME.replace(old, new))
                self.assertRejected()

    def test_condition_role_unknown(self):
        self.only(PROGRAMME.replace("value: student}}", "value: fixture-nobody}}"))
        self.assertRejected()

    def test_second_audience(self):
        line = '      - {type: cohortmember, cohort: "ltct:org:{org}:managers"}\n'
        self.only(PROGRESS.replace(line, line + "      - {type: systemrole, role: manager}\n"))
        self.assertRejected()

    def test_wrong_cohort(self):
        self.only(PROGRESS.replace("ltct:org:{org}:managers", "ltct:org:{org}"))
        self.assertRejected()

    def test_allusers_audience(self):
        line = "      - {type: systemrole, role: manager}\n"
        for block in (PROGRESS.replace('{type: cohortmember, cohort: "ltct:org:{org}:managers"}',
                                       "{type: allusers}"),
                      PROGRAMME.replace("{type: systemrole, role: manager}", "{type: allusers}"),
                      PROGRAMME.replace(line, line + "      - {type: allusers}\n")):
            with self.subTest():
                self.only(block)
                self.assertRejected()

    def test_unknown_role_audience(self):
        self.only(PROGRAMME.replace("role: manager", "role: fixture-nobody"))
        self.assertRejected()

    def test_area_over_100(self):
        self.only(PROGRAMME.replace("key: programme", "key: p" + "a" * 100))
        self.assertRejected()

    def test_area_of_100(self):
        self.only(PROGRAMME.replace("key: programme", "key: p" + "a" * 99))
        self.assertAccepted()

    def test_per_org_area_too_long(self):
        # org_ + a 30-character key + _ + a 66-character template key is 101 once encoded.
        self.write("organisations.yaml",
                   TWO_ORGS.replace("key: fixture-a", "key: fixture-" + "a" * 22))
        self.only(PROGRESS.replace("key: progress", "key: p" + "r" * 65))
        self.assertRejected()

    def test_duplicate_key(self):
        self.only(PROGRAMME, PROGRAMME)
        self.assertRejected()

    def test_duplicate_area_after_encoding(self):
        # org fixture-b-c with template d, and org fixture-b with template c-d: both
        # encode to org_fixture_b_c_d.
        self.write("organisations.yaml", TWO_ORGS.replace(
            "  - key: fixture-b\n    name: Fixture B\n",
            "  - key: fixture-b\n    name: Fixture B\n  - key: fixture-b-c\n    name: Fixture BC\n"))
        self.only(PROGRESS.replace("key: progress", "key: d"),
                  PROGRESS.replace("key: progress", "key: c-d"))
        self.assertRejected()

    def test_key_pattern(self):
        for bad in ("Programme", "programme_x", "1programme", "pro gramme", "programme-",
                    "pro--gramme"):
            with self.subTest(key=bad):
                self.only(PROGRAMME.replace("key: programme", "key: %s" % json.dumps(bad)))
                self.assertRejected()

    def test_missing_why(self):
        self.only(PROGRAMME.replace("    why: fixture\n", ""))
        self.assertRejected()

    def test_missing_source(self):
        line = "    source: core_course\\reportbuilder\\datasource\\participants\n"
        self.assertIn(line, PROGRAMME)
        self.only(PROGRAMME.replace(line, ""))
        self.assertRejected()

    def test_unknown_key(self):
        self.only(PROGRAMME.replace("    why: fixture\n", "    why: fixture\n    colour: blue\n"))
        self.assertRejected()

    def test_label_name(self):
        self.only(PROGRAMME.replace('"Programme: completions per course"',
                                    '"Programme: certified learners"'))
        self.assertRejected()

    def test_label_heading(self):
        self.only(PROGRAMME.replace("heading: Completed,", "heading: Level reached,"))
        self.assertRejected()

    def test_label_expanded_name(self):
        self.write("organisations.yaml", TWO_ORGS.replace("name: Fixture A", "name: Fixture Trainers"))
        self.assertAccepted()   # "Trainers" is not the retired word "Trainer"
        self.write("organisations.yaml", TWO_ORGS.replace("name: Fixture A", "name: Fixture Trainer"))
        self.assertRejected()

    def test_aggregation_unknown(self):
        self.only(PROGRAMME.replace("aggregation: countdistinct", "aggregation: median"))
        self.assertRejected()

    def test_aggregation_column_allows(self):
        # contracts/declaration.md "Aggregation": groupconcatdistinct takes no timestamp,
        # sum no text, and our coverage columns disable every aggregation.
        for old, new in (("heading: Completed, aggregation: count",
                          "heading: Completed, aggregation: groupconcatdistinct"),
                         ("heading: Enrolled, aggregation: countdistinct",
                          "heading: Enrolled, aggregation: sum"),
                         ("heading: Completed, aggregation: count",
                          "heading: Completed, aggregation: avg")):
            with self.subTest(new=new):
                self.only(PROGRAMME.replace(old, new))
                self.assertRejected()
        for old, new in (("heading: Completed, aggregation: count",
                          "heading: Completed, aggregation: max"),
                         ("heading: Enrolled, aggregation: countdistinct",
                          "heading: Enrolled, aggregation: groupconcatdistinct")):
            with self.subTest(new=new):
                self.only(PROGRAMME.replace(old, new))
                self.assertAccepted()

    def test_aggregation_column_type_unrecorded(self):
        # An aggregation on a column whose type validate does not know is refused, not
        # passed to the server to fail the whole apply.
        self.only(PROGRAMME.replace("{column: course:coursefullnamewithlink, heading: Course}",
                                    "{column: course:shortname, heading: Course, "
                                    "aggregation: count}"))
        self.assertRejected()

    def test_identifier_shape(self):
        self.only(PROGRAMME.replace("column: user:username", "column: username"))
        self.assertRejected()

    def test_duplicate_column(self):
        line = "      - {column: course:coursefullnamewithlink, heading: Course}\n"
        self.only(PROGRAMME.replace(line, line + line))
        self.assertRejected()

    def test_customfield_column_needs_course_field(self):
        (self.dir / "course-fields.yaml").unlink()
        self.only(PROGRAMME)
        self.assertRejected()

    def test_per_unknown(self):
        self.only(PROGRAMME.replace('    name: "Programme', '    per: country\n    name: "Programme'))
        self.assertRejected()

    def test_org_placeholder_without_per(self):
        self.only(PROGRAMME.replace('"Programme: completions per course"', '"{org}: completions"'))
        self.assertRejected()

    def test_uniquerows(self):
        self.only(PROGRAMME.replace("uniquerows: 1", "uniquerows: 2"))
        self.assertRejected()

    def test_per_organisation_without_organisations(self):
        self.write("organisations.yaml", TWO_ORGS.replace(
            "  - key: fixture-a\n    name: Fixture A\n  - key: fixture-b\n    name: Fixture B\n", ""))
        self.assertAccepted()   # independent remains, so the template still expands once
        self.assertEqual(sorted(a for a in self.reports() if a.startswith("org_")),
                         ["org_independent_progress"])

    def test_payload_shape(self):
        reports = self.reports()
        r = reports["org_fixture_a_progress"]
        self.assertEqual(set(r), {"area", "name", "source", "uniquerows", "columns", "conditions",
                                  "filters", "sorting", "audiences", "schedule"})
        self.assertEqual(r["source"], "core_course\\reportbuilder\\datasource\\participants")
        self.assertEqual(r["uniquerows"], 1)
        self.assertEqual(r["columns"][0], {"column": "user:fullnamewithlink", "heading": "Learner",
                                           "aggregation": None})
        self.assertEqual([c["condition"] for c in r["conditions"]],
                         ["user:profilefield_ltct_org", "role:name", "enrol:plugin"])
        self.assertEqual(r["conditions"][1], {"condition": "role:name",
                                              "values": {"operator": "equal", "value": "student"}})
        self.assertEqual(r["filters"], ["course:fullname", "user:fullname"])
        self.assertEqual(r["sorting"], [])
        prog = reports["programme"]
        self.assertIsNone(prog["schedule"])
        self.assertEqual(prog["audiences"], [{"type": "systemrole", "role": "manager"}])
        self.assertEqual(prog["columns"][3]["aggregation"], "countdistinct")
        delivery = {"condition": "enrol:plugin",
                    "values": {"operator": "not_equal", "value": "manual"}}
        self.assertEqual(r["conditions"][2], delivery)
        self.assertIn(delivery, prog["conditions"])
        # Spec 004's arrays in apply order, then spec 013's two after reports, then spec 011's.
        # Spec 006 puts levels and role_pathways between competencies and reports.
        self.assertEqual(list(self.payload())[-10:],
                         ["course_field_category", "course_fields", "competencies", "levels",
                          "role_pathways", "reports",
                          "badge_template", "certificate_template", "officehours", "dashboard"])

    def test_tracked_delivery_condition(self):
        # Spec 002 R10: a manager's enrolment (the organisation-enrolment instance, enrol_self)
        # is delivery, so delivery is any enrolment but a pilot's manual one. report builder's
        # select condition holds one value (filters\select EQUAL_TO 1, NOT_EQUAL_TO 2).
        tracked = yaml.safe_load((REPO / "moodle" / "site" / "reports.yaml")
                                 .read_text(encoding="utf-8"))["reports"]
        enrol = {r["key"]: [c["values"] for c in r["conditions"]
                            if c["condition"] == "enrol:plugin"] for r in tracked}
        for key in ("progress", "programme"):
            self.assertEqual(enrol[key], [{"operator": "not_equal", "value": "manual"}], key)
        self.assertEqual(enrol["pilots"], [{"operator": "equal", "value": "manual"}])
        self.assertEqual(sc.SCOPE_CONDITIONS[2],
                         ("enrol:plugin", {"operator": "not_equal", "value": "manual"}))

    def test_summary_counts_reports(self):
        rc, out, _ = self.run_main("validate")
        self.assertEqual(rc, 0, out)
        self.assertIn("6 reports", out)


class Schedules(ReportsBase):
    """A report's schedule (spec 004 US4): T040."""

    def schedule(self):
        return self.reports()["org_fixture_a_progress"]["schedule"]

    def test_render(self):
        s = self.schedule()
        self.assertEqual(set(s), {"name", "recurrence", "format", "userviewas", "start",
                                  "configdata"})
        self.assertEqual(s["recurrence"], 3)
        self.assertEqual(s["format"], "excel")
        self.assertEqual(s["userviewas"], -1)
        self.assertEqual(s["start"], "monday 07:00")
        self.assertEqual(s["name"], "Fixture A: weekly learner progress")
        self.assertEqual(s["configdata"], {
            "subject": "Fixture A: weekly learner progress",
            "message": {"text": "Your organisation's learner progress this week.", "format": 1},
            "reportempty": 2})

    def test_viewas_creator(self):
        self.only(PROGRESS.replace("viewas: recipient", "viewas: creator"))
        self.assertRejected()

    def test_send_when_empty(self):
        for bad in ("yes", "1", "2"):
            with self.subTest(value=bad):
                self.only(PROGRESS.replace("send_when_empty: 0", "send_when_empty: %s" % bad))
                self.assertRejected()

    def test_missing_subject(self):
        self.only(PROGRESS.replace('      subject: "{org}: weekly learner progress"\n', ""))
        self.assertRejected()

    def test_subject_label(self):
        self.only(PROGRESS.replace('"{org}: weekly learner progress"',
                                   '"{org}: weekly certificates"'))
        self.assertRejected()

    def test_missing_message(self):
        self.only(PROGRESS.replace("      message: \"Your organisation's learner progress this week.\"\n",
                                   ""))
        self.assertRejected()

    def test_missing_start(self):
        self.only(PROGRESS.replace('      start: "monday 07:00"\n', ""))
        self.assertRejected()

    def test_bad_start(self):
        for bad in ("monday", "07:00", "funday 07:00", "monday 25:00", "monday 7:00"):
            with self.subTest(start=bad):
                self.only(PROGRESS.replace('"monday 07:00"', json.dumps(bad)))
                self.assertRejected()

    def test_bad_recurrence_and_format(self):
        for old, new in (("recurrence: weekly", "recurrence: fortnightly"),
                         ("format: excel", "format: docx")):
            with self.subTest(new=new):
                self.only(PROGRESS.replace(old, new))
                self.assertRejected()

    def test_unknown_schedule_key(self):
        self.only(PROGRESS.replace("      format: excel\n", "      format: excel\n      owner: admin\n"))
        self.assertRejected()

    def test_schedule_without_audience(self):
        self.only(PROGRAMME.replace("    audiences:\n      - {type: systemrole, role: manager}\n",
                                    "    audiences: []\n" + SCHEDULE.replace("{org}: ", "")))
        self.assertRejected()

    def test_schedule_message_label(self):
        # FR-010 covers the weekly email's body as well as its subject.
        for bad in ("Learners who reached level 2 this week.",
                    "Your organisation's practitioner progress this week."):
            with self.subTest(bad=bad):
                self.only(PROGRESS.replace("Your organisation's learner progress this week.",
                                           bad))
                self.assertRejected()

    def test_schedule_on_systemrole_report(self):
        self.only(PROGRAMME.replace("    why: fixture\n",
                                    SCHEDULE.replace("{org}: ", "") + "    why: fixture\n"))
        self.assertAccepted()


class CourseFields(ReportsBase):
    """course-fields.yaml (spec 004 data-model "Course fields"): T047."""
    LAST = "    visibility: everyone\n    why: fixture\n"

    def test_render(self):
        p = self.payload()
        self.assertEqual(p["course_field_category"], "LTC curriculum")
        self.assertEqual(p["course_fields"], [
            {"shortname": "ltct_competencies", "name": "Competencies this course aims at",
             "type": "text", "locked": 1, "visibility": 2},
            {"shortname": "ltct_target_level", "name": "Level this course aims at",
             "type": "text", "locked": 1, "visibility": 2}])

    def test_visibility_values(self):
        head = COURSE_FIELDS[:COURSE_FIELDS.rindex(self.LAST)]
        self.only(PROGRESS)   # no report column reads ltct_target_level
        for word, value in (("everyone", 2), ("teachers", 1), ("nobody", 0)):
            with self.subTest(visibility=word):
                self.write("course-fields.yaml",
                           head + "    visibility: %s\n    why: fixture\n" % word)
                fields = {f["shortname"]: f for f in self.payload()["course_fields"]}
                self.assertEqual(fields["ltct_target_level"]["visibility"], value)

    def test_hidden_field_in_a_report_column(self):
        head = COURSE_FIELDS[:COURSE_FIELDS.rindex(self.LAST)]
        self.write("course-fields.yaml", head + "    visibility: nobody\n    why: fixture\n")
        self.assertRejected()   # programme reads course:customfield_ltct_target_level

    def test_bad_shortname(self):
        for bad in ("competencies", "ltct-competencies", "LTCT_X", "ltct_"):
            with self.subTest(shortname=bad):
                self.write("course-fields.yaml", COURSE_FIELDS + "  - shortname: %s\n    name: X\n"
                           "    type: text\n    locked: 1\n    visibility: everyone\n    why: w\n"
                           % json.dumps(bad))
                self.assertRejected()

    def test_bad_type(self):
        self.write("course-fields.yaml", COURSE_FIELDS.replace("type: text", "type: textarea", 1))
        self.assertRejected()

    def test_bad_visibility(self):
        self.write("course-fields.yaml", COURSE_FIELDS.replace("visibility: everyone",
                                                               "visibility: all", 1))
        self.assertRejected()

    def test_required_fields(self):
        self.only(PROGRESS)
        for short in ("ltct_competencies", "ltct_target_level"):
            with self.subTest(missing=short):
                start = COURSE_FIELDS.index("  - shortname: " + short)
                end = COURSE_FIELDS.find("  - shortname:", start + 1)
                self.write("course-fields.yaml",
                           COURSE_FIELDS[:start] + (COURSE_FIELDS[end:] if end > 0 else ""))
                self.assertRejected()

    def test_name_label(self):
        self.write("course-fields.yaml", COURSE_FIELDS.replace("Level this course aims at",
                                                               "Level the learner reached"))
        self.assertRejected()

    def test_unknown_key_and_missing_why(self):
        for new in ("    why: fixture\n    default: x\n", ""):
            with self.subTest(new=new):
                self.write("course-fields.yaml", COURSE_FIELDS.replace("    why: fixture\n", new, 1))
                self.assertRejected()

    def test_duplicate_shortname(self):
        self.write("course-fields.yaml", COURSE_FIELDS.replace("shortname: ltct_target_level",
                                                               "shortname: ltct_competencies"))
        self.assertRejected()

    def test_locked_boolean(self):
        self.write("course-fields.yaml", COURSE_FIELDS.replace("locked: 1", "locked: true", 1))
        self.assertRejected()

    def test_file_optional(self):
        (self.dir / "course-fields.yaml").unlink()
        self.only(PROGRESS)
        self.assertAccepted()
        p = self.payload()
        self.assertIsNone(p["course_field_category"])
        self.assertEqual(p["course_fields"], [])


class CompetencyList(ReportsBase):
    """The competency list rendered from competencies.yaml (data-model "Competency list"): T047."""

    def synthetic(self, mutate):
        with open(REPO / "competencies.yaml", encoding="utf-8") as fh:
            data = yaml.safe_load(fh)
        mutate(data)
        # A subdirectory, so the declaration directory holds no unexpected file.
        (self.dir / "fixture-repo").mkdir(exist_ok=True)
        path = self.dir / "fixture-repo" / "competencies.yaml"
        path.write_text(yaml.safe_dump(data, allow_unicode=True, sort_keys=False),
                        encoding="utf-8")
        return mock.patch.object(sc, "COMPETENCIES", path)

    def test_real_file(self):
        comps = self.payload()["competencies"]
        self.assertEqual(len(comps), 42)
        self.assertNotIn("Meta", {c["category"] for c in comps})
        self.assertNotIn("Uncategorized", {c["name"] for c in comps})
        self.assertEqual([c["sortorder"] for c in comps], list(range(1, 43)))
        with open(REPO / "competencies.yaml", encoding="utf-8") as fh:
            data = yaml.safe_load(fh)
        expected = [(cat, n) for cat, names in data.items() if cat != "Meta" for n in names]
        self.assertEqual([(c["category"], c["name"]) for c in comps], expected)
        self.assertEqual(set(comps[0]), {"name", "category", "sortorder", "slug", "url"})
        self.assertIn("Fonts & Encoding", {c["name"] for c in comps})

    def test_duplicate_name(self):
        with self.synthetic(lambda d: d["Core"].append(d["Core Technical"][0])):
            self.assertRejected()

    def test_long_name(self):
        with self.synthetic(lambda d: d["Core"].append("A" * 256)):
            self.assertRejected()

    def test_brackets_and_control(self):
        for bad in ("Fixture [one]", "Fixture ]", "Fixture\ttab", "Fixture\x07bell"):
            with self.subTest(name=bad):
                with self.synthetic(lambda d: d["Core"].append(bad)):
                    self.assertRejected()

    def test_label(self):
        with self.synthetic(lambda d: d["Core"].append("Certified Fixture")):
            self.assertRejected()

    def test_synthetic_valid(self):
        # Spec 006: a competency needs a descriptor, so the fixture gets one beside the real ones.
        descriptors = self.dir / "fixture-repo" / "competencies"
        shutil.copytree(REPO / "competencies", descriptors)
        (descriptors / "fixture-extra.md").write_text(
            "---\nname: Fixture Extra\ncategory: Core\nslug: fixture-extra\n---\n",
            encoding="utf-8")
        with self.synthetic(lambda d: d["Core"].append("Fixture Extra")), \
                mock.patch.object(sc, "DESCRIPTORS", descriptors):
            self.assertAccepted()
            comps = self.payload()["competencies"]
        self.assertEqual(len(comps), 43)
        self.assertEqual([c["sortorder"] for c in comps], list(range(1, 44)))
        extra = next(c for c in comps if c["name"] == "Fixture Extra")
        self.assertEqual(extra["slug"], "fixture-extra")
        self.assertTrue(extra["url"].endswith("/core/fixture-extra/"))

    def test_rendered_without_reports(self):
        (self.dir / "reports.yaml").unlink()
        self.assertEqual(len(self.payload()["competencies"]), 42)


class CompetencyCoverage(ReportsBase):
    """competency-coverage's own rules (contracts/declaration.md): T047."""

    def test_condition_refused(self):
        self.only(COVERAGE.replace("    conditions: []\n", "    conditions:\n"
                                   "      - {condition: competency:category, values: "
                                   "{operator: equal, value: Core}}\n"))
        self.assertRejected()

    def test_audiences(self):
        line = "      - {type: systemrole, role: manager}\n"
        for new in ('      - {type: cohortmember, cohort: "ltct:org:fixture-a:managers"}\n',
                    "      - {type: systemrole, role: editingteacher}\n",
                    "      - {type: allusers}\n",
                    line + "      - {type: systemrole, role: coursecreator}\n"):
            with self.subTest(audience=new.strip()):
                self.only(COVERAGE.replace(line, new))
                self.assertRejected()

    def test_column_entity(self):
        self.only(COVERAGE.replace("{column: coverage:learners, heading: Delivery learners}",
                                   "{column: user:fullname, heading: Delivery learners}"))
        self.assertRejected()

    def test_strict_labels(self):
        for old, new in (("heading: Delivery learners", "heading: Learners who achieved it"),
                         ("heading: Category", "heading: Competent users"),
                         ('"Competencies published courses aim at: courses and delivery use"',
                          '"Competencies reached"')):
            with self.subTest(new=new):
                self.only(COVERAGE.replace(old, new))
                self.assertRejected()

    def test_strict_only_here(self):
        self.only(PROGRAMME.replace("heading: Completed,", "heading: Reached the end,"))
        self.assertAccepted()

    def test_competency_heading_says_aim_at(self):
        self.only(COVERAGE.replace("heading: Competency courses aim at", "heading: Competency"))
        self.assertRejected()

    def test_per_refused(self):
        self.only(COVERAGE.replace('    name: "Competencies', '    per: organisation\n    name: "Competencies'))
        self.assertRejected()

    def test_render(self):
        r = self.reports()["competency_coverage"]
        self.assertEqual(r["sorting"], [{"column": "competency:name", "direction": "asc"}])
        self.assertEqual(r["conditions"], [])
        self.assertEqual(r["uniquerows"], 0)
        self.assertEqual(r["source"], "local_ltuse\\reportbuilder\\datasource\\competency_coverage")


class Sorting(ReportsBase):
    """sorting on any report (data-model "Report"): T047."""

    def test_column_not_in_columns(self):
        self.only(COVERAGE.replace("{column: competency:name, direction: asc}",
                                   "{column: coverage:enrolments, direction: asc}"))
        self.assertRejected()

    def test_direction(self):
        for bad in ("up", "ASC", "ascending"):
            with self.subTest(direction=bad):
                self.only(COVERAGE.replace("direction: asc", "direction: %s" % bad))
                self.assertRejected()

    def test_desc_and_precedence(self):
        line = "      - {column: competency:name, direction: asc}\n"
        self.only(COVERAGE.replace(line, "      - {column: coverage:courses, direction: desc}\n"
                                   + line))
        self.assertEqual(self.reports()["competency_coverage"]["sorting"],
                         [{"column": "coverage:courses", "direction": "desc"},
                          {"column": "competency:name", "direction": "asc"}])

    def test_duplicate_sort_column(self):
        line = "      - {column: competency:name, direction: asc}\n"
        self.only(COVERAGE.replace(line, line + "      - {column: competency:name, direction: desc}\n"))
        self.assertRejected()

    def test_other_templates_validate(self):
        for block in (PROGRAMME, PILOTS):
            with self.subTest():
                self.only(block)
                self.assertAccepted()


if __name__ == "__main__":
    unittest.main()


RECOGNITION_PINS = """\
  - component: mod_customcert
    version: 2026042014
    source: {url: "https://example.org/customcert.zip", sha256: "%s"}
    why: "#23"
  - component: availability_coursecompleted
    version: 2026070100
    source: {url: "https://example.org/coursecompleted.zip", sha256: "%s"}
    why: "#23"
""" % ("b" * 64, "c" * 64)


class Recognition(Base):
    """badges.yaml and certificate/template.yaml (spec 013 data-model, contracts/declaration.md)."""

    def setUp(self):
        super().setUp()
        self.write("site.yaml", SITE.format(ver=VER, sha="a" * 64) + RECOGNITION_PINS)
        site = REPO / "moodle" / "site"
        shutil.copytree(site / "badges", self.dir / "badges")
        shutil.copytree(site / "certificate", self.dir / "certificate")
        for rel in ("badges.yaml", "settings/badges.yaml"):
            self.write(rel, (site / rel).read_text(encoding="utf-8"))

    def test_the_tracked_declaration_is_accepted_and_rendered(self):
        self.assertAccepted()
        decl = sc.validate(self.dir)[0]
        badge = decl["badge_template"]
        self.assertEqual(badge["name"], "{course}: training completed")
        self.assertIn("LTC training programme", badge["description"])   # {programme} filled
        cert = decl["certificate_template"]
        date = [e for e in cert["pages"][0]["elements"] if e["type"] == "date"][0]
        self.assertEqual(date["dateitem"], -2)   # completion, never the issue date
        payload = sc.build_payload(decl, "apply", {})
        self.assertTrue(payload["badge_template"]["image"]["content"])
        self.assertEqual(len(payload["badge_template"]["deny"]), len(sc.cbc_wording.DENY_PATTERNS))
        self.assertNotIn("path", payload["badge_template"]["image"])

    def test_certified_in_the_badge_name_is_refused(self):
        self.edit("badges.yaml", 'name: "{course}: training completed"', 'name: "Certified: {course}"')
        self.assertInvalid("says certified")

    def test_a_level_held_in_the_description_is_refused(self):
        self.edit("badges.yaml", "records training completed.",
                  "records training completed. Level 3 - Independent achieved.")
        self.assertInvalid("level")

    def test_a_course_title_reaches_the_check(self):
        with mock.patch.object(sc, "_courses", return_value=[("Certification prep", "", "")]):
            self.assertInvalid("Certification prep")

    def test_unknown_placeholder_and_misplaced_target_level(self):
        self.edit("badges.yaml", "imagecaption: Completion badge for an LTC training course",
                  'imagecaption: "{learner} badge for an LTC training course"')
        self.assertInvalid("uses {learner}")
        self.reset(); self.setUp()
        self.edit("badges.yaml", 'name: "{course}: training completed"',
                  'name: "{course} {target_level}: training completed"')
        self.assertInvalid("{target_level} other than")

    def test_badge_image_rules(self):
        (self.dir / "badges" / "completion.png").write_bytes(
            (REPO / "moodle" / "site" / "certificate" / "logo.png").read_bytes())   # 200x80
        self.assertInvalid("must be square")

    def test_certificate_needs_one_of_each_identity_element(self):
        self.edit("certificate/template.yaml", "      - {type: code, x: 148, y: 185, size: 9, align: C}\n", "")
        self.assertInvalid("exactly one code")

    def test_certificate_date_must_be_completion(self):
        self.edit("certificate/template.yaml", "date: completion", "date: issue")
        self.assertInvalid("date must be completion")

    def test_certificate_font_must_embed(self):
        self.edit("certificate/template.yaml", "font: freesans", "font: times")
        self.assertInvalid("font must be one of")

    def test_certificate_wording(self):
        self.edit("certificate/template.yaml", '"Training completed"', '"Certified"')
        self.assertInvalid("says certified")

    def test_the_plugins_and_settings_are_required(self):
        self.write("site.yaml", SITE.format(ver=VER, sha="a" * 64))
        self.assertInvalid("mod_customcert must be pinned")
        self.reset(); self.setUp()
        self.edit("settings/badges.yaml", "  - name: badges_allowexternalbackpack\n", "  - name: x_unused\n")
        self.assertInvalid("badges_allowexternalbackpack must be declared")

    def test_the_salt_is_never_declared(self):
        self.write("settings/salt.yaml", "rows: [23]\npurpose: x\nsettings:\n"
                   "  - name: badges_badgesalt\n    value: abc\n    why: x\n")
        self.assertInvalid("badges_badgesalt is per site")


# --- spec 011: office hours, the dashboard and the calendar ------------------------------
# specs/011-events-calendar/data-model.md "Declared (repo)" and contracts/declaration.md.

SCHEDULER_PIN = """\
  - component: mod_scheduler
    version: 2026080400
    source: {url: "https://example.org/scheduler.zip", sha256: "%s"}
    why: "#21"
""" % ("d" * 64)
MENTORING_CATEGORY = """\
  - key: mentoring
    name: LTC Mentoring
    why: office hours
"""
NO_TIMEZONE_IGNORE = """\
ignore:
  - setting: calendar_exportsalt
    reason: per install
"""


class EventsAbsent(Base):
    """A site with none of spec 011's files is still valid (T007)."""

    def test_without_any_spec_011_file(self):
        self.assertAccepted()
        decl = sc.validate(self.dir)[0]
        self.assertIsNone(decl["officehours"])
        self.assertEqual(decl["dashboard"], [])
        payload = sc.build_payload(decl, "apply", {})
        self.assertIsNone(payload["officehours"])
        self.assertEqual(payload["dashboard"], [])


class Events(Base):
    """The tracked spec 011 declaration, then one broken rule at a time."""

    def setUp(self):
        super().setUp()
        site = REPO / "moodle" / "site"
        self.write("site.yaml", SITE.format(ver=VER, sha="a" * 64) + SCHEDULER_PIN)
        self.write("ignore.yaml", NO_TIMEZONE_IGNORE)
        self.edit("organisations.yaml", "organisations:\n  - key: independent",
                  MENTORING_CATEGORY + "organisations:\n  - key: independent")
        for rel in ("office-hours.yaml", "dashboard.yaml", "settings/calendar.yaml",
                    "settings/scheduler.yaml"):
            self.write(rel, (site / rel).read_text(encoding="utf-8"))

    def test_the_tracked_declaration_is_accepted_and_rendered(self):
        self.assertAccepted()
        decl = sc.validate(self.dir)[0]
        hours = decl["officehours"]
        self.assertEqual(hours["course"]["idnumber"], "ltct:officehours")
        self.assertEqual(hours["course"]["category_idnumber"], "ltct:mentoring")
        self.assertEqual(hours["scheduler"]["guardtime"], 12 * 3600)   # decision 3
        self.assertEqual(hours["scheduler"]["allownotifications"], 0)  # R20
        self.assertEqual(decl["dashboard"], [{"block": "calendar_upcoming", "region": "side-post"}])
        payload = sc.build_payload(decl, "apply", {})
        self.assertEqual(payload["officehours"], hours)
        self.assertEqual(payload["dashboard"], decl["dashboard"])
        self.assertNotIn("guardtime_hours", json.dumps(payload))

    def test_office_hours_rules(self):
        cases = [
            ("idnumber: ltct:officehours\n", "idnumber: ltct:hours\n"),
            ("fullname: Mentor office hours", "fullname: " + "x" * 255),
            ("shortname: ltct-officehours", "shortname: " + "x" * 101),
            ("category: mentoring", "category: nowhere"),
            ("  groupmode: 1        #", "  groupmode: 0        #"),
            ("groupmodeforce: 1", "groupmodeforce: 0"),
            ("idnumber: ltct:officehours:scheduler", "idnumber: ltct:officehours:other"),
            ("  maxbookings: 1", "  maxbookings: 6"),
            ("schedulermode: onetime", "schedulermode: weekly"),
            ("guardtime_hours: 12", "guardtime_hours: 169"),
            ("allownotifications: 0", "allownotifications: 1"),
            ("defaultslotduration: 30", "defaultslotduration: 4"),
            ("defaultslotduration: 30", "defaultslotduration: 241"),
            ("usebookingform: 0", "usebookingform: 1"),
            ("grade: 0 ", "grade: 10 "),
            ('name_template: "Office hours {n}"', 'name_template: "Office hours"'),
            ('name_template: "Office hours {n}"', 'name_template: "{name} {n}"'),
            ('name_template: "Office hours {n}"', 'name_template: "{lastname} hours {n}"'),
            ("  name: Book time with your mentor", "  name: Certified booking"),
        ]
        for old, new in cases:
            with self.subTest(change=new):
                self.reset()
                self.edit("office-hours.yaml", old, new)
                self.assertRejected()

    def test_scheduler_must_be_pinned(self):
        self.write("site.yaml", SITE.format(ver=VER, sha="a" * 64))
        self.assertInvalid("mod_scheduler must be pinned")

    def test_students_never_see_who_booked(self):
        roles = (self.dir / "roles.yaml").read_text()
        line = "      mod/scheduler:seeotherstudentsbooking: inherit"
        self.assertIn(line, roles)
        for replacement in ("      mod/scheduler:seeotherstudentsbooking: allow", ""):
            with self.subTest(replacement=replacement):
                self.reset()
                self.edit("roles.yaml", line, replacement)
                self.assertInvalid("mod/scheduler:seeotherstudentsbooking")

    def test_dashboard_rules(self):
        block = "  - block: calendar_upcoming\n    region: side-post\n    why: >-\n"
        for old, new in [
            ("block: calendar_upcoming", "block: not_a_block"),
            ("region: side-post", "region: footer"),
            (block, block.replace(">-\n", "upcoming\n") + block),   # the same block twice
        ]:
            with self.subTest(change=new):
                self.reset()
                self.edit("dashboard.yaml", old, new)
                self.assertRejected()
        self.reset()
        self.write("dashboard.yaml", "rows: [21]\ndefault_blocks: []\n")
        self.assertRejected()

    def test_time_zone_rules(self):
        for zone, accepted in (("UTC", True), ("Africa/Nairobi", True), ("America/Bogota", True),
                               ("Mars/Base", False), ("Nairobi", False)):
            with self.subTest(zone=zone):
                self.reset()
                self.edit("settings/calendar.yaml", "value: UTC", "value: %s" % zone)
                (self.assertAccepted if accepted else self.assertRejected)()

    def test_forcetimezone_must_be_99(self):
        self.edit("settings/calendar.yaml", "value: 99", "value: Africa/Nairobi")
        self.assertInvalid("forcetimezone must be 99")

    def test_calendar_settings_come_together(self):
        text = (self.dir / "settings" / "calendar.yaml").read_text()
        head, _, rest = text.partition("  - name: calendar_adminseesall\n")
        rest = rest[rest.index("  - name: timezone"):]
        self.write("settings/calendar.yaml", head + rest)
        self.assertInvalid("calendar_adminseesall must be declared")

    def test_the_profile_always_shows_the_zone(self):
        self.write("settings/hidden.yaml", "rows: [21]\npurpose: x\nsettings:\n"
                   "  - name: hiddenuserfields\n    value: \"icqnumber,timezone\"\n    why: x\n")
        self.assertInvalid("hiddenuserfields must not hide timezone")


class OrgManagerCalendar(Base):
    """D3, R17: orgmanager may hold moodle/calendar:manageentries and no other calendar cap."""
    LAST_CAP = "      moodle/site:viewuseridentity: allow\n"

    def setUp(self):
        super().setUp()
        self.write("roles.yaml", ORGMANAGER_ROLES + MENTOR_ENTRY)

    def test_manageentries_is_allowed(self):
        self.edit("roles.yaml", self.LAST_CAP, self.LAST_CAP + "      moodle/calendar:manageentries: allow\n")
        self.assertAccepted()

    def test_no_other_calendar_capability(self):
        for cap in ("moodle/calendar:managegroupentries", "moodle/calendar:manageownentries"):
            with self.subTest(capability=cap):
                self.reset()
                self.edit("roles.yaml", self.LAST_CAP, self.LAST_CAP + "      %s: allow\n" % cap)
                self.assertInvalid("orgmanager holds no calendar capability")


# --- spec 006: learning pathways (T014) ---------------------------------------------------

PATHWAYS_HEAD = """\
rows: [12]
purpose: Role pathways.
roles:
"""


def role(key="fixture-role", name="Fixture support consultant",
         comps=("Translation Tools", "Keyboards"), extra=""):
    """One roles[] entry of pathways.yaml, as YAML text."""
    text = "  - key: %s\n    name: %s\n" % (key, name)
    if comps:
        text += "    competencies:\n" + "".join("      - %s\n" % c for c in comps)
    else:
        text += "    competencies: []\n"
    return text + '    why: "Fixture, 2026-10-04."\n' + extra


class CompetencySlugUrl(Base):
    """Each competency's slug and url (contracts/declaration.md "Competency slug and url")."""

    def comps(self):
        self.assertAccepted()
        return sc.build_payload(sc.validate(self.dir)[0], "apply", {})["competencies"]

    def site_url(self):
        with open(REPO / "mkdocs.yml", encoding="utf-8") as fh:
            for line in fh:
                if line.startswith("site_url:"):
                    return line.split(":", 1)[1].strip().rstrip("/") + "/"
        self.fail("mkdocs.yml has no site_url")

    def test_real_descriptors(self):
        comps = self.comps()
        self.assertEqual(len(comps), 42)
        self.assertEqual(len({c["slug"] for c in comps}), 42, "slugs are not unique")
        self.assertEqual(len({c["url"] for c in comps}), 42, "urls are not unique")
        base = self.site_url()
        self.assertTrue(base.startswith("https://"))
        for c in comps:
            with self.subTest(name=c["name"]):
                self.assertRegex(c["slug"], r"^[a-z0-9][a-z0-9-]*$")
                self.assertLessEqual(len("competency:" + c["slug"]), 100)
                self.assertEqual(c["url"], "%s%s/%s/" % (
                    base, sc.site_slugify(c["category"]), c["slug"]))
        keyboards = next(c for c in comps if c["name"] == "Keyboards")
        self.assertEqual(keyboards["url"], base + "core-technical/" + keyboards["slug"] + "/")

    def test_slug_matches_descriptor(self):
        comps = {c["name"]: c["slug"] for c in self.comps()}
        matched = 0
        for path in sorted((REPO / "competencies").glob("*.md")):
            text = path.read_text(encoding="utf-8")
            if not text.startswith("---"):
                continue        # a README, not a descriptor
            fm = yaml.safe_load(text[3:text.find("\n---", 3)])
            if fm.get("name") in comps:
                matched += 1
                self.assertEqual(comps[fm["name"]], fm.get("slug", path.stem), path.name)
        self.assertEqual(matched, 42)

    def test_slugify_matches_gen_site(self):
        # gen_site builds the site when imported, so its slugify is lifted out of the source.
        import ast, re
        tree = ast.parse((REPO / "scripts" / "gen_site.py").read_text(encoding="utf-8"))
        fn = next(n for n in tree.body if isinstance(n, ast.FunctionDef) and n.name == "slugify")
        ns = {"re": re}
        exec(compile(ast.Module(body=[fn], type_ignores=[]), "gen_site.py", "exec"), ns)
        with open(REPO / "competencies.yaml", encoding="utf-8") as fh:
            categories = list(yaml.safe_load(fh))
        for cat in categories + ["Fonts & Encoding", "A (B) c", "  Odd -- Spacing "]:
            self.assertEqual(sc.site_slugify(cat), ns["slugify"](cat), cat)

    def descriptors(self):
        d = self.dir / "fixture-repo" / "competencies"
        shutil.copytree(REPO / "competencies", d)
        return d

    def set_slug(self, d, name, slug):
        for path in d.glob("*.md"):
            text = path.read_text(encoding="utf-8")
            if not text.startswith("---"):
                continue        # a README, not a descriptor
            end = text.find("\n---", 3)
            fm = yaml.safe_load(text[3:end])
            if fm.get("name") == name:
                lines = [ln for ln in text[:end].split("\n") if not ln.startswith("slug:")]
                lines.append("slug: %s" % (yaml.safe_dump(slug).split("\n")[0],))
                path.write_text("\n".join(lines) + text[end:], encoding="utf-8")
                return
        self.fail("no descriptor named %r" % name)

    def test_fixture_copy_is_valid(self):
        d = self.descriptors()
        self.set_slug(d, "Keyboards", "keyboards-fixture")
        with mock.patch.object(sc, "DESCRIPTORS", d):
            comps = {c["name"]: c for c in self.comps()}
        self.assertEqual(comps["Keyboards"]["slug"], "keyboards-fixture")
        self.assertTrue(comps["Keyboards"]["url"].endswith("/core-technical/keyboards-fixture/"))

    def test_missing_descriptor(self):
        d = self.descriptors()
        for path in d.glob("*.md"):
            if "\nname: Keyboards\n" in path.read_text(encoding="utf-8"):
                path.unlink()
        with mock.patch.object(sc, "DESCRIPTORS", d):
            self.assertInvalid("no descriptor")

    def test_duplicate_slug(self):
        d = self.descriptors()
        keyboards = next(c["slug"] for c in self.comps() if c["name"] == "Keyboards")
        self.set_slug(d, "Malware", keyboards)
        with mock.patch.object(sc, "DESCRIPTORS", d):
            self.assertInvalid("share the slug")

    def test_bad_slug(self):
        for bad in ("Keyboards", "-keyboards", "key_boards", "k" * 95, 7):
            with self.subTest(slug=bad):
                d = self.descriptors()
                self.set_slug(d, "Keyboards", bad)
                with mock.patch.object(sc, "DESCRIPTORS", d):
                    self.assertInvalid("descriptor slug")
                shutil.rmtree(self.dir / "fixture-repo")

    def test_slug_at_limit(self):
        d = self.descriptors()
        self.set_slug(d, "Keyboards", "k" * 94)
        with mock.patch.object(sc, "DESCRIPTORS", d):
            self.assertEqual(len(self.comps()), 42)

    def test_site_url_must_be_https(self):
        (self.dir / "fixture-repo").mkdir()
        path = self.dir / "fixture-repo" / "mkdocs.yml"
        for text in ("site_name: x\n", "site_url: http://example.org/\n",
                     "site_url: https://\n", "site_url: ''\n"):
            with self.subTest(mkdocs=text):
                path.write_text(text, encoding="utf-8")
                with mock.patch.object(sc, "MKDOCS", path):
                    self.assertInvalid("site_url")

    def test_site_url_host_is_read(self):
        (self.dir / "fixture-repo").mkdir()
        path = self.dir / "fixture-repo" / "mkdocs.yml"
        path.write_text("site_url: https://fixture.example.org\nx: !ENV [A, b]\n",
                        encoding="utf-8")
        with mock.patch.object(sc, "MKDOCS", path):
            comps = self.comps()
        for c in comps:
            self.assertTrue(c["url"].startswith("https://fixture.example.org/"), c["url"])
            self.assertNotIn("//", c["url"][len("https://"):])


class PathwayLevels(Base):
    """The payload's levels array, from outcome-levels.yaml, verbatim."""

    def levels_file(self, mutate):
        with open(REPO / "outcome-levels.yaml", encoding="utf-8") as fh:
            data = yaml.safe_load(fh)
        mutate(data)
        (self.dir / "fixture-repo").mkdir(exist_ok=True)
        path = self.dir / "fixture-repo" / "outcome-levels.yaml"
        path.write_text(yaml.safe_dump(data, allow_unicode=True, sort_keys=False),
                        encoding="utf-8")
        return mock.patch.object(sc, "OUTCOME_LEVELS", path)

    def test_real_file(self):
        self.assertAccepted()
        levels = sc.build_payload(sc.validate(self.dir)[0], "apply", {})["levels"]
        with open(REPO / "outcome-levels.yaml", encoding="utf-8") as fh:
            data = yaml.safe_load(fh)
        labels = {lv["id"]: lv["label"] for lv in data["levels"]}
        self.assertEqual(levels, [{"level": n, "label": labels[n]} for n in (1, 2, 3, 4)])
        self.assertEqual([lv["label"] for lv in levels],
                         ["1 - Has Knowledge", "2 - With Assistance", "3 - Independent",
                          "4 - Expert"])

    def test_targets_exactly_one_to_four(self):
        for targets in ([0, 1, 2, 3, 4], [1, 2, 3], [4, 3, 2, 1], [1, 2, 3, 4, 4], None):
            with self.subTest(targets=targets):
                with self.levels_file(
                        lambda d: d.__setitem__("course_target_levels", targets)):
                    self.assertInvalid("course_target_levels")

    def test_label_missing(self):
        def blank(d):
            for lv in d["levels"]:
                if lv["id"] == 2:
                    lv["label"] = ""
        with self.levels_file(blank):
            self.assertInvalid("no CBC label")


class Pathways(Base):
    """pathways.yaml (contracts/declaration.md "pathways.yaml")."""

    def roles(self):
        self.assertAccepted()
        return sc.build_payload(sc.validate(self.dir)[0], "apply", {})["role_pathways"]

    def pathways(self, *blocks):
        body = "".join(blocks)
        self.write("pathways.yaml", PATHWAYS_HEAD.replace("roles:\n", "roles: []\n")
                   if not body else PATHWAYS_HEAD + body)

    def test_missing_file_is_empty(self):
        self.assertFalse((self.dir / "pathways.yaml").exists())
        self.assertEqual(self.roles(), [])

    def test_tracked_file(self):
        shutil.copy(REPO / "moodle" / "site" / "pathways.yaml", self.dir / "pathways.yaml")
        self.assertEqual(self.roles(), [])

    def test_empty_roles(self):
        self.pathways()
        self.assertEqual(self.roles(), [])

    def test_valid_roles(self):
        self.pathways(role(extra="    description: Supports a team's tools day to day.\n"),
                      role(key="second-role", name="Archive helper",
                           comps=('"Fonts & Encoding"',)))
        out = self.roles()
        self.assertEqual(out, [
            {"key": "fixture-role", "name": "Fixture support consultant",
             "description": "Supports a team's tools day to day.", "sortorder": 0,
             "competencies": ["Translation Tools", "Keyboards"]},
            {"key": "second-role", "name": "Archive helper", "description": "",
             "sortorder": 1, "competencies": ["Fonts & Encoding"]}])

    def test_required_keys(self):
        for text in ("rows: [12]\npurpose: x\n", "purpose: x\nroles: []\n",
                     "rows: [12]\nroles: []\n"):
            with self.subTest(text=text):
                self.write("pathways.yaml", text)
                self.assertRejected()
        self.write("pathways.yaml", "rows: [12]\npurpose: x\nroles: {}\n")
        self.assertInvalid("roles must be a list")

    def test_bad_key(self):
        for key in ("Fixture", "1role", "-role", "role_x", "role.x", "r" * 96, '""'):
            with self.subTest(key=key):
                self.pathways(role(key=key))
                self.assertInvalid("key")

    def test_key_at_limit(self):
        self.pathways(role(key="r" * 95))
        self.assertEqual(len(self.roles()), 1)

    def test_duplicate_key(self):
        self.pathways(role(), role(name="Another name"))
        self.assertInvalid("declared twice")

    def test_unknown_competency(self):
        for comp in ("Fixture Nonesuch", "keyboards", "Fonts and Encoding",
                     "Fonts &  Encoding", "Keyboards "):
            with self.subTest(comp=comp):
                self.pathways(role(comps=("Keyboards", '"%s"' % comp)))
                self.assertInvalid("not in competencies.yaml")

    def test_meta_competency(self):
        self.pathways(role(comps=("Keyboards", "Uncategorized")))
        self.assertInvalid("Meta")

    def test_competency_twice(self):
        self.pathways(role(comps=("Keyboards", "Translation Tools", "Keyboards")))
        self.assertInvalid("twice")

    def test_no_competencies(self):
        self.pathways(role(comps=()))
        self.assertInvalid("non-empty")

    def test_missing_why(self):
        self.pathways(role().replace('    why: "Fixture, 2026-10-04."\n', ""))
        self.assertRejected()

    def test_level_in_name(self):
        for name in ("3 - Independent consultant", '"Consultant, 4 - Expert"',
                     "Level 2 consultant", "Consultant at level3"):
            with self.subTest(name=name):
                self.pathways(role(name=name))
                self.assertRejected()

    def test_level_in_description(self):
        for text in ("Reaches 2 - With Assistance on keyboards.",
                     "Aims at level 4 for fonts."):
            with self.subTest(description=text):
                self.pathways(role(extra="    description: %s\n" % text))
                self.assertRejected()

    def test_long_name(self):
        self.pathways(role(name="N" * 256))
        self.assertRejected()
