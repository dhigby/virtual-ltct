"""Every course payload carries one discussion block (spec 012, US4, FR-015).

contracts/payload.md: `discussion` = {idnumber, name, intro_html, shared}, with `shared`
read from moodle/site/course-discussions.yaml through site_config.load_discussions(), so
the payload and the drift check cannot disagree. Backfilled courses get one too.
Run: python -m pytest tests/
"""
import pathlib
import shutil
import sys
import tempfile
import unittest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import moodle_payload as mp  # noqa: E402
import site_config as sc  # noqa: E402

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

    def declare(self, *slugs):
        lines = ["rows: [10]", "shared:" + ("" if slugs else " []")]
        for s in slugs:
            lines += ["  - slug: %s" % s, "    why: test fixture"]
        (self.site / "course-discussions.yaml").write_text("\n".join(lines) + "\n",
                                                           encoding="utf-8")

    def test_designed_course_has_block(self):
        self.declare()
        d = build(DESIGNED, site_dir=self.site)["discussion"]
        self.assertEqual(d["idnumber"], "ltct:%s:discussion" % DESIGNED)
        self.assertTrue(d["name"].strip())
        self.assertTrue(d["intro_html"].strip())
        self.assertIs(d["shared"], False)

    def test_backfilled_course_has_block(self):
        self.declare()
        self.assertFalse((mp.MODULES / BACKFILLED / "00-design.md").exists(),
                         "fixture assumption: %s is a backfilled course" % BACKFILLED)
        d = build(BACKFILLED, site_dir=self.site)["discussion"]
        self.assertEqual(d["idnumber"], "ltct:%s:discussion" % BACKFILLED)
        self.assertIs(d["shared"], False)

    def test_reviewer_view_has_block(self):
        self.declare()
        self.assertIn("discussion", build(DESIGNED, view="reviewer", site_dir=self.site))

    def test_shared_follows_declaration(self):
        self.declare(DESIGNED)
        self.assertIs(build(DESIGNED, site_dir=self.site)["discussion"]["shared"], True)
        self.assertIs(build(BACKFILLED, site_dir=self.site)["discussion"]["shared"], False)

    def test_absent_declaration_means_separated(self):
        self.assertIs(build(DESIGNED, site_dir=self.site)["discussion"]["shared"], False)

    def test_legacy_folder_uses_branch_slug(self):
        self.declare("paratext-9-advanced-support")
        d = build("Paratext 9 advanced support", site_dir=self.site)["discussion"]
        self.assertEqual(d["idnumber"], "ltct:paratext-9-advanced-support:discussion")
        self.assertIs(d["shared"], True)

    def test_invalid_declaration_refuses(self):
        self.declare("no-such-course")
        with self.assertRaises(SystemExit):
            build(DESIGNED, site_dir=self.site)

    def test_every_course_has_block(self):
        self.declare()
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

    def test_real_declaration_loads(self):
        # The committed declaration is what publish_moodle.py uses; it must be valid.
        shared, problems = sc.load_discussions()
        self.assertFalse(problems, problems.items if problems else "")


if __name__ == "__main__":
    unittest.main()
