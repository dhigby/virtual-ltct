"""Every course payload carries one discussion block (spec 012, US4, FR-015), and a placement
(spec 002 R11).

contracts/payload.md: `discussion` = {idnumber, name, intro_html}. Shared courses are open
across organisations (spec 002 R3, R14), so the block carries no sharing flag and
moodle/site/course-discussions.yaml is retired. Backfilled courses get one too.
Run: python -m pytest tests/
"""
import json
import pathlib
import shutil
import sys
import tempfile
import unittest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import moodle_payload as mp  # noqa: E402
import site_config as sc  # noqa: E402
import check_moodle_payload as cmp  # noqa: E402

DESIGNED = "coretech-computer-hardware"    # opted into the pipeline (has 00-design.md)
BACKFILLED = "coretech-malware"            # legacy import: lessons, no 00-design.md


def build(slug, view="learner", site_dir=None):
    folder = mp.MODULES / slug
    manifest, _, _ = mp.Payload(folder, view, site_dir=site_dir).build()
    return manifest


class DiscussionBlock(unittest.TestCase):
    def setUp(self):
        self.site = pathlib.Path(tempfile.mkdtemp())

    def tearDown(self):
        shutil.rmtree(self.site)

    def test_designed_course_has_block(self):
        d = build(DESIGNED, site_dir=self.site)["discussion"]
        self.assertEqual(d["idnumber"], "ltct:%s:discussion" % DESIGNED)
        self.assertTrue(d["name"].strip())
        self.assertTrue(d["intro_html"].strip())

    def test_backfilled_course_has_block(self):
        self.assertFalse((mp.MODULES / BACKFILLED / "00-design.md").exists(),
                         "fixture assumption: %s is a backfilled course" % BACKFILLED)
        d = build(BACKFILLED, site_dir=self.site)["discussion"]
        self.assertEqual(d["idnumber"], "ltct:%s:discussion" % BACKFILLED)

    def test_reviewer_view_has_block(self):
        self.assertIn("discussion", build(DESIGNED, view="reviewer", site_dir=self.site))

    def test_block_carries_no_sharing_flag(self):
        # Spec 002 R14: every shared course is open, so there is nothing to separate.
        d = build(DESIGNED, site_dir=self.site)["discussion"]
        self.assertEqual(set(d), {"idnumber", "name", "intro_html"})

    def test_legacy_folder_uses_branch_slug(self):
        d = build("Paratext 9 advanced support", site_dir=self.site)["discussion"]
        self.assertEqual(d["idnumber"], "ltct:paratext-9-advanced-support:discussion")

    def test_every_course_has_block(self):
        for folder in sorted(p for p in mp.MODULES.iterdir() if p.is_dir()):
            if folder.name.startswith(("_", ".")):
                continue
            with self.subTest(course=folder.name):
                try:
                    d = build(folder.name, site_dir=self.site)["discussion"]
                except SystemExit as e:
                    # The payload refuses a course it cannot publish at all (for example
                    # two files sharing one identity). That course is not this test's.
                    self.skipTest(str(e))
                self.assertEqual(d["idnumber"],
                                 "ltct:%s:discussion" % sc.branch_slug(folder.name))


# Spec 002 R11: the course's placement, from moodle/site/org-courses.yaml through
# site_config.load_org_courses(), the loader validate uses, so the two cannot disagree.
ORGS = "organisations:\n  - key: fixture-north\n    name: Fixture North\n"
SHARED = {"org_only": False, "category_idnumber": None}


class Placement(unittest.TestCase):
    def setUp(self):
        self.site = pathlib.Path(tempfile.mkdtemp())
        (self.site / "organisations.yaml").write_text(ORGS, encoding="utf-8")

    def tearDown(self):
        shutil.rmtree(self.site)

    def declare(self, *entries):
        lines = ["rows: [8, 15]", "org_only:" + ("" if entries else " []")]
        for slug, org in entries:
            lines += ["  - slug: %s" % slug, "    organisation: %s" % org,
                      "    why: approved by the maintainer, fixture issue"]
        (self.site / "org-courses.yaml").write_text("\n".join(lines) + "\n", encoding="utf-8")

    def test_absent_declaration_means_shared(self):
        self.assertEqual(build(DESIGNED, site_dir=self.site)["placement"], SHARED)

    def test_undeclared_course_is_shared(self):
        self.declare((BACKFILLED, "fixture-north"))
        self.assertEqual(build(DESIGNED, site_dir=self.site)["placement"], SHARED)

    def test_declared_course_is_org_only(self):
        self.declare((DESIGNED, "fixture-north"))
        self.assertEqual(build(DESIGNED, site_dir=self.site)["placement"],
                         {"org_only": True, "category_idnumber": "ltct:org:fixture-north"})

    def test_reviewer_view_has_placement(self):
        self.declare((DESIGNED, "fixture-north"))
        self.assertTrue(build(DESIGNED, view="reviewer", site_dir=self.site)
                        ["placement"]["org_only"])

    def test_legacy_folder_uses_branch_slug(self):
        self.declare(("paratext-9-advanced-support", "fixture-north"))
        self.assertTrue(build("Paratext 9 advanced support", site_dir=self.site)
                        ["placement"]["org_only"])

    def test_invalid_declaration_refuses(self):
        for entry in (("no-such-course", "fixture-north"), (DESIGNED, "fixture-south")):
            with self.subTest(entry=entry):
                self.declare(entry)
                with self.assertRaises(SystemExit):
                    build(DESIGNED, site_dir=self.site)

    def test_no_reason_in_the_payload(self):
        self.declare((DESIGNED, "fixture-north"))
        self.assertNotIn("fixture issue", json.dumps(build(DESIGNED, site_dir=self.site)))


class PlacementCheck(unittest.TestCase):
    """check_moodle_payload.py refuses a malformed placement before anything is sent."""

    def problems(self, placement, drop=False):
        manifest = {} if drop else {"placement": placement}
        return cmp.check_placement("fixture-course", manifest)

    def test_well_formed_pass(self):
        self.assertEqual(self.problems(SHARED), [])
        self.assertEqual(self.problems({"org_only": True,
                                        "category_idnumber": "ltct:org:fixture-north"}), [])

    def test_malformed_refused(self):
        for placement in (
                None, "shared", {}, {"org_only": False},
                {"category_idnumber": None},
                {"org_only": "false", "category_idnumber": None},
                {"org_only": 0, "category_idnumber": None},
                {"org_only": False, "category_idnumber": "ltct:org:fixture-north"},
                {"org_only": True, "category_idnumber": None},
                {"org_only": True, "category_idnumber": "ltct:published"},
                {"org_only": True, "category_idnumber": "ltct:org:"},
                {"org_only": True, "category_idnumber": "ltct:org:Fixture North"},
                {"org_only": True, "category_idnumber": "ltct:org:fixture-north:managers"},
                {"org_only": False, "category_idnumber": None, "extra": 1}):
            with self.subTest(placement=placement):
                self.assertTrue(self.problems(placement))

    def test_missing_refused(self):
        self.assertTrue(self.problems(None, drop=True))

    def test_check_runs_on_every_view(self):
        # Wired into check(), with the other structural checks, before the learner-only ones.
        tmp = pathlib.Path(tempfile.mkdtemp())
        try:
            manifest = build(DESIGNED, view="reviewer", site_dir=tmp)
            manifest["placement"] = {"org_only": True, "category_idnumber": None}
            (tmp / "manifest.json").write_text(json.dumps(manifest), encoding="utf-8")
            problems, _ = cmp.check(tmp)
            self.assertTrue(any("placement" in p for p in problems), problems)
        finally:
            shutil.rmtree(tmp)


if __name__ == "__main__":
    unittest.main()
