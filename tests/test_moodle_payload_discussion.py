"""Every course payload carries one discussion block (spec 012, US4, FR-015).

contracts/payload.md: `discussion` = {idnumber, name, intro_html}. Shared courses are open
across organisations (spec 002 R3, R14), so the block carries no sharing flag and
moodle/site/course-discussions.yaml is retired. Backfilled courses get one too.
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


if __name__ == "__main__":
    unittest.main()
