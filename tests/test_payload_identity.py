"""Moodle identities: a module is addressed by its file's number, and every idnumber
fits Moodle's 100-character column. Run: python -m pytest tests/

Builds throwaway courses in a temp dir; the real modules/ tree is never touched.
"""
import pathlib, sys, tempfile, unittest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import moodle_payload as mp

DESIGN = "# Design\n\n| **Design status** | Draft |\n|---|---|\n"
LESSON = "# %s\n\n**Estimated time:** 30 minutes\n\n## Connect\n\nSee [the next one](%s).\n"
QUIZ = """# Quiz

## Assessment quiz (2 questions)

You need **100% (2/2)** to pass.

**Question 1:** Which is right?
- A) This
- B) That

**Question 2:** And this one?
- A) Here
- B) There

## Answer key

1. A | 2. B
"""


class Identity(unittest.TestCase):

    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.root = pathlib.Path(self._tmp.name) / "modules"

    def tearDown(self):
        self._tmp.cleanup()

    def course(self, slug, files):
        folder = self.root / slug
        folder.mkdir(parents=True)
        (folder / "00-design.md").write_text(DESIGN, encoding="utf-8")
        for name, text in files.items():
            (folder / name).write_text(text, encoding="utf-8")
        return folder

    def build(self, folder):
        return mp.Payload(folder, "learner").build()[0]

    def test_module_key_is_the_file_number(self):
        self.assertEqual(mp.module_key("03-checking-the-wordlist.md"), "03")
        self.assertEqual(mp.module_key("01a-quiz.md"), "01a")
        self.assertEqual(mp.module_key("consultant-triage-card.md"), "consultant-triage-card")

    def test_modules_and_questions_are_addressed_by_number(self):
        m = self.build(self.course("demo", {
            "01-a-long-lesson-title.md": LESSON % ("One", "02-another-lesson.md"),
            "02-another-lesson.md": LESSON % ("Two", "01-a-long-lesson-title.md"),
            "03-quiz.md": QUIZ,
        }))
        ids = sorted(mod["idnumber"] for s in m["sections"] for mod in s["modules"])
        self.assertEqual(ids, ["ltct:demo:01", "ltct:demo:02", "ltct:demo:03"])
        self.assertEqual(m["quizzes"][0]["questions"][0]["idnumber"], "ltct:demo:03:q1.1")

    def test_long_slug_fits_where_filenames_did_not(self):
        # The live failure: this slug plus its lesson filename was 117 characters.
        slug = "software-support-and-troubleshooting-for-translation-teams"
        m = self.build(self.course(slug, {
            "01-software-support-and-troubleshooting.md": LESSON % ("One", "#"),
        }))
        ids = [mod["idnumber"] for s in m["sections"] for mod in s["modules"]]
        self.assertEqual(ids, ["ltct:%s:01" % slug])

    def test_two_files_sharing_a_number_are_refused(self):
        folder = self.course("dup", {
            "01a-module-1a.md": LESSON % ("One", "#"),
            "01a-quiz.md": QUIZ,
        })
        with self.assertRaises(SystemExit) as cm:
            self.build(folder)
        msg = str(cm.exception)
        self.assertIn("01a-module-1a.md", msg)
        self.assertIn("01a-quiz.md", msg)
        self.assertIn("ltct:dup:01a", msg)

    def test_overlong_identity_is_refused_before_anything_is_sent(self):
        slug = "x" * 90
        folder = self.course(slug, {
            "01-lesson.md": LESSON % ("One", "#"),
            "an-unnumbered-page-with-a-long-name.md": LESSON % ("Aid", "#"),
        })
        with self.assertRaises(SystemExit) as cm:
            self.build(folder)
        self.assertIn("at most 100", str(cm.exception))


if __name__ == "__main__":
    unittest.main()
