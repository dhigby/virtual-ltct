"""Spec 016: protection.yaml and what it needs from the rest of the declaration.

Each test copies the tracked moodle/site into a temporary folder, changes one thing, and
checks what validate says (specs/016-identity-protection/contracts/declaration.md). No test
names a person or an organisation at risk (constitution III).
"""
import pathlib, shutil, sys, tempfile, unittest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import site_config as sc


TEACHER_VIEW = ("      local/ltuse:viewidentity: allow          # spec 016 R7 path 4: real identities "
                "of the course's protected learners")
MENTOR_VIEW = "      local/ltuse:viewidentity: allow                # spec 016 R7 path 2: the learner's real identity"


class Protection(unittest.TestCase):

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.dir = pathlib.Path(self.tmp.name) / "site"
        shutil.copytree(REPO / "moodle" / "site", self.dir)

    def tearDown(self):
        self.tmp.cleanup()

    def edit(self, name, old, new, count=1):
        path = self.dir / name
        text = path.read_text(encoding="utf-8")
        self.assertIn(old, text)
        path.write_text(text.replace(old, new, count), encoding="utf-8")

    def problems(self):
        return sc.validate(self.dir)[1].items

    def assertValid(self):
        self.assertEqual(self.problems(), [])

    def assertInvalid(self, fragment):
        items = self.problems()
        self.assertTrue(any(fragment in p for p in items), "%r not in %r" % (fragment, items))

    # --- the tracked declaration ------------------------------------------------------------

    def test_the_tracked_declaration_is_accepted_and_rendered(self):
        self.assertValid()
        decl = sc.validate(self.dir)[0]
        protection = decl["protection"]
        self.assertEqual(protection["levels"], ["none", "email", "firstname", "pseudonym"])
        self.assertNotIn("org_minimum_max", protection)
        self.assertIn("ltct_role", protection["withhold"]["firstname"])
        self.assertNotIn("firstname", protection["withhold"]["firstname"])
        self.assertIn("firstname", protection["withhold"]["pseudonym"])
        payload = sc.build_payload(decl, "apply", {})
        self.assertEqual(payload["protection"], protection)
        self.assertEqual(list(payload)[-1], "protection")

    def test_orgscope_is_not_ready_while_ltct_org_is_visible(self):
        # Decision 2 has not landed: ltct_org is visible and the progress report scopes by it,
        # so the plugin refuses firstname and pseudonym (R11).
        self.assertFalse(sc.validate(self.dir)[0]["protection"]["orgscope_ready"])

    def test_a_private_ltct_org_with_a_report_scoped_by_it_is_refused(self):
        self.edit("profile-fields.yaml", "    name: Organisation\n    visible: all",
                  "    name: Organisation\n    visible: private")
        self.assertInvalid("can be private only once no report scopes by it")

    def test_the_payload_names_no_one(self):
        decl = sc.validate(self.dir)[0]
        text = repr(sc.build_payload(decl, "apply", {})["protection"])
        self.assertNotIn("@", text)
        for key in ("pseudonym\":", "realfirstname", "minlevel", "orgkey"):
            self.assertNotIn(key, text)

    def test_the_summary_counts_levels(self):
        decl = sc.validate(self.dir)[0]
        self.assertIn("4 protection levels", sc._summary(decl))

    # --- protection.yaml ----------------------------------------------------------------------

    def test_levels_are_fixed(self):
        self.edit("protection.yaml", "levels: [none, email, firstname, pseudonym]",
                  "levels: [none, firstname, email, pseudonym]")
        self.assertInvalid("levels must be exactly")

    def test_each_level_includes_the_one_before(self):
        self.edit("protection.yaml", "  pseudonym:\n    - maildisplay\n", "  pseudonym:\n")
        self.assertInvalid("missing maildisplay")

    def test_firstname_must_withhold_ltct_role(self):
        self.edit("protection.yaml", "    - ltct_role                    # names the work", "    # removed")
        self.assertInvalid("must also withhold ltct_role")

    def test_ltct_org_is_never_withheld(self):
        self.edit("protection.yaml", "  email: [maildisplay]", "  email: [maildisplay, ltct_org]")
        self.assertInvalid("ltct_org is never withheld")

    def test_the_learners_own_words_are_never_withheld(self):
        self.edit("protection.yaml", "  email: [maildisplay]", "  email: [maildisplay, description]")
        self.assertInvalid("the learner's own words are theirs")

    def test_an_unknown_field_is_refused(self):
        self.edit("protection.yaml", "  email: [maildisplay]", "  email: [maildisplay, password]")
        self.assertInvalid("not a field the plugin can withhold")

    def test_pseudonym_must_withhold_the_first_name(self):
        self.edit("protection.yaml", "    - firstname                    # the pseudonym replaces it\n", "")
        self.assertInvalid("must withhold firstname")

    def test_no_organisation_minimum_is_declared(self):
        # Organisation minimums are cut (Doug, 2026-10-05 (scope review), change 9).
        self.edit("protection.yaml", "reconcile_minutes: 60", "org_minimum_max: firstname\nreconcile_minutes: 60")
        self.assertInvalid("org_minimum_max")

    def test_manageorgprotection_is_gone(self):
        self.assertNotIn("local/ltuse:manageorgprotection", sc.PROTECTION_MANAGE_CAPS)

    def test_the_neutral_surname_is_one_non_letter(self):
        self.edit("protection.yaml", 'neutral_surname: "·"', 'neutral_surname: "X"')
        self.assertInvalid("neutral_surname is one non-letter character")

    def test_an_empty_neutral_surname_is_refused(self):
        # Names are not locked (scope review, change 3), so core's edit form must accept the
        # protected learner's own surname (R4).
        self.edit("protection.yaml", 'neutral_surname: "·"', 'neutral_surname: ""')
        self.assertInvalid("neutral_surname is one non-letter character")

    def test_reconcile_runs_hourly(self):
        self.edit("protection.yaml", "reconcile_minutes: 60", "reconcile_minutes: 5")
        self.assertInvalid("reconcile_minutes is 60")

    def test_no_address_anywhere(self):
        self.edit("protection.yaml", "  #26 (spec 016, FR-001, FR-014)", "  write to someone@example.org #26")
        self.assertInvalid("holds an @")

    # --- roles (R7, R8, R14) -------------------------------------------------------------------

    def test_viewidentity_only_on_its_three_roles(self):
        self.edit("roles.yaml", "      moodle/course:viewparticipants: allow",
                  "      local/ltuse:viewidentity: allow\n      moodle/course:viewparticipants: allow")
        self.assertInvalid("local/ltuse:viewidentity is held only by")

    def test_manageprotection_only_on_manager(self):
        self.edit("roles.yaml", TEACHER_VIEW, "      local/ltuse:manageprotection: allow\n" + TEACHER_VIEW)
        self.assertInvalid("local/ltuse:manageprotection is the site team's only")

    def test_course_mentors_must_hold_viewidentity(self):
        self.edit("roles.yaml", TEACHER_VIEW + "\n", "")
        self.assertInvalid("roles.yaml teacher: must allow local/ltuse:viewidentity")

    def test_mentors_must_hold_viewidentity(self):
        self.edit("roles.yaml", MENTOR_VIEW + "\n", "")
        self.assertInvalid("roles.yaml mentor: must allow local/ltuse:viewidentity")

    def test_course_leaders_must_not_download_backups(self):
        self.edit("roles.yaml", "      moodle/backup:downloadfile: prohibit     # spec 016 R14: not in "
                  "the archetype; prohibit so no course override grants it\n", "")
        self.assertInvalid("roles.yaml teacher: must prohibit moodle/backup:downloadfile")

    def test_report_editing_stays_with_manager(self):
        self.edit("roles.yaml", "      moodle/course:viewparticipants: allow",
                  "      moodle/reportbuilder:edit: allow\n      moodle/course:viewparticipants: allow")
        self.assertInvalid("moodle/reportbuilder:edit stays with manager")

    # --- settings (R6, R8) ---------------------------------------------------------------------

    def test_the_zero_cost_settings_are_required(self):
        self.edit("settings/identity.yaml", "  - name: enablegravatar\n    value: 0", "  - name: enablegravatar\n    value: 1")
        self.assertInvalid("enablegravatar must be 0")

    def test_email_in_staff_views_is_not_refused(self):
        # Decision 1 is rejected (scope review, change 1): core's defaults are declared.
        decl = sc.validate(self.dir)[0]
        declared = {s["name"]: str(s["value"]) for s in decl["settings"]}
        self.assertEqual(declared["showuseridentity"], "email")
        self.assertIn("email", declared["grade_export_userprofilefields"].split(","))

    def test_missing_identity_file_is_refused(self):
        (self.dir / "settings" / "identity.yaml").unlink()
        self.assertInvalid("enablegravatar must be declared")

    # --- the certificate (R10) -------------------------------------------------------------------

    def test_the_certificate_prints_core_studentname(self):
        # ltct_certname is cut (Doug, 2026-10-05 (scope review), change 8): the certificate
        # prints core's studentname, and the site team issues a real-name one on request.
        decl = sc.validate(self.dir)[0]
        kinds = [e["type"] for page in decl["certificate_template"]["pages"] for e in page["elements"]]
        self.assertIn("studentname", kinds)
        self.assertFalse(any(f["shortname"] == "ltct_certname" for f in decl["profile_fields"]))

    def test_without_protection_yaml_nothing_is_required(self):
        (self.dir / "protection.yaml").unlink()
        (self.dir / "settings" / "identity.yaml").unlink()
        decl, problems = sc.validate(self.dir)
        self.assertEqual(problems.items, [])
        self.assertIsNone(decl["protection"])


if __name__ == "__main__":
    unittest.main()
