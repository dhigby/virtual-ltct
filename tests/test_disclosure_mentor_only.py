"""Tests for the mentor-only marker in scripts/disclosure.py (spec 012, design D3).

Written before the code (T003); T007 adds MENTOR_ONLY_RE, strip_restricted() and
restricted_blocks(). Each new-API test looks the name up inside the test, so until T007
lands those tests fail one by one while the strip_answer_keys() regression tests pass.

API shape assumed (design D3, contracts/assignment-file.md):
  * MENTOR_ONLY_RE      -- compiled regex, ^## Mentor only\\b.*$ (qualified, repeatable).
  * strip_restricted(md) -> (text, ok), the same shape as strip_answer_keys(); ok=False
    means withhold the page whole.
  * restricted_blocks(md) -> list of line lists, the same shape as answer_key_blocks():
    every answer-key and mentor-only block in document order, each starting with its
    marker line and stopping before the next H1/H2.

All fixture text is invented. Run: python -m pytest tests/
"""
import pathlib, sys, unittest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import disclosure  # noqa: E402

FIXTURES = REPO / "tests" / "fixtures" / "mentor-only"
ASSIGNMENT = (FIXTURES / "assignment.md").read_text(encoding="utf-8")
LESSON = (FIXTURES / "lesson-mixed.md").read_text(encoding="utf-8")

LEARNER_LINES = ("LEARNER-BRIEF-LINE", "LEARNER-SUBMIT-LINE", "LEARNER-CRITERION-ONE",
                 "LEARNER-CRITERION-TWO", "LEARNER-BEFORE-LINE")
MENTOR_LINES = ("MENTOR-NOTES-ONE", "MENTOR-NOTES-TWO", "MENTOR-MODEL-LINE",
                "MENTOR-MODEL-DEEP", "MENTOR-EXTRA-LINE")


def api(name):
    """The new disclosure API, looked up per test so each one fails on its own."""
    return getattr(disclosure, name)


class MentorOnlyMarker(unittest.TestCase):
    def test_matches_bare_and_qualified(self):
        rx = api("MENTOR_ONLY_RE")
        for line in ("## Mentor only", "## Mentor only: grading notes",
                     "## Mentor only: model answer", "## Mentor only (facilitation tip)"):
            self.assertTrue(rx.match(line), line)

    def test_rejects_non_markers(self):
        rx = api("MENTOR_ONLY_RE")
        for line in ("### Mentor only", "# Mentor only", "## Mentor onlyish",
                     "## Mentors only", "Mentor only", "## Answer key"):
            self.assertFalse(rx.match(line), line)


class StripRestricted(unittest.TestCase):
    def test_assignment_strips_every_mentor_block(self):
        text, ok = api("strip_restricted")(ASSIGNMENT)
        self.assertTrue(ok)
        for marker in MENTOR_LINES:
            self.assertNotIn(marker, text)
        self.assertNotIn("Mentor only", text)

    def test_block_runs_to_next_h1_or_h2(self):
        text, _ = api("strip_restricted")(ASSIGNMENT)
        for marker in LEARNER_LINES:
            self.assertIn(marker, text)
        # the H2 that ends a block survives; the H3/H4 inside it do not
        self.assertIn("## Before you start", text)
        self.assertNotIn("A deeper heading stays inside the block", text)
        self.assertEqual(text.count("### Locates the backup"), 1)  # criterion, not its notes
        md = "## Mentor only\nsecret-a\n# Next part\nkept-a\n"
        text, ok = api("strip_restricted")(md)
        self.assertTrue(ok)
        self.assertNotIn("secret-a", text)
        self.assertIn("# Next part", text)
        self.assertIn("kept-a", text)

    def test_removes_both_marker_kinds(self):
        text, ok = api("strip_restricted")(LESSON)
        self.assertTrue(ok)
        for gone in ("MENTOR-ASIDE-LINE", "KEY-LINE-ONE", "KEY-LINE-TWO", "Answer key"):
            self.assertNotIn(gone, text)
        for kept in ("LESSON-CONNECT-LINE", "LESSON-CONTENT-LINE", "LESSON-CHALLENGE-LINE"):
            self.assertIn(kept, text)

    def test_stray_restricted_heading_withholds(self):
        for heading in ("grading notes", "model answer", "marking notes", "mentor notes"):
            for form in (f"## {heading.capitalize()}", f"### {heading.title()}",
                         f"#### {heading}"):
                md = f"# Task\n\n## Brief\n\nDo the thing.\n\n{form}\n\nleaked-text\n"
                _, ok = api("strip_restricted")(md)
                self.assertFalse(ok, form)

    def test_restricted_heading_inside_block_is_fine(self):
        md = ("## Brief\n\nDo the thing.\n\n## Mentor only\n\n### Mentor notes\n\nx\n\n"
              "### Model answer\n\ny\n")
        text, ok = api("strip_restricted")(md)
        self.assertTrue(ok)
        self.assertNotIn("Model answer", text)

    def test_answer_key_residue_withholds(self):
        for leak in ("### Answer key", "**Answer key**", "- Option b (correct)"):
            md = f"## Brief\n\nDo the thing.\n\n{leak}\n\nb\n"
            _, ok = api("strip_restricted")(md)
            self.assertFalse(ok, leak)

    def test_plain_lesson_unchanged_and_ok(self):
        md = "# Lesson\n\n**Estimated time:** 20 minutes\n\n## Connect\n\nhello\n"
        text, ok = api("strip_restricted")(md)
        self.assertTrue(ok)
        self.assertEqual(text, md)


class RestrictedBlocks(unittest.TestCase):
    def test_assignment_blocks(self):
        blocks = api("restricted_blocks")(ASSIGNMENT)
        self.assertEqual([b[0] for b in blocks], [
            "## Mentor only: grading notes", "## Mentor only: model answer", "## Mentor only"])
        joined = ["\n".join(b) for b in blocks]
        self.assertIn("MENTOR-NOTES-TWO", joined[0])
        self.assertIn("MENTOR-MODEL-DEEP", joined[1])
        self.assertNotIn("Before you start", joined[1])
        self.assertIn("MENTOR-EXTRA-LINE", joined[2])

    def test_both_kinds_in_document_order(self):
        blocks = api("restricted_blocks")(LESSON)
        self.assertEqual([b[0] for b in blocks], [
            "## Mentor only (facilitation tip)", "## Answer key (Section 1)",
            "## Answer key (Section 2)"])
        self.assertNotIn("## Content", blocks[0])
        self.assertIn("KEY-LINE-TWO: 1. c", blocks[2])

    def test_none(self):
        self.assertEqual(api("restricted_blocks")("# Lesson\n\n## Connect\n\nhi\n"), [])


class StripAnswerKeysUnchanged(unittest.TestCase):
    """strip_answer_keys() stays the quiz-only name and behaves as before (D3)."""

    def test_strips_qualified_repeated_keys(self):
        text, ok = disclosure.strip_answer_keys(LESSON)
        self.assertTrue(ok)
        self.assertNotIn("KEY-LINE-ONE", text)
        self.assertNotIn("KEY-LINE-TWO", text)
        self.assertIn("LESSON-CHALLENGE-LINE", text)

    def test_leaves_mentor_only_alone(self):
        text, ok = disclosure.strip_answer_keys(LESSON)
        self.assertTrue(ok)
        self.assertIn("MENTOR-ASIDE-LINE", text)

    def test_withholds_on_non_canonical_key(self):
        _, ok = disclosure.strip_answer_keys("## Quiz\n\n### Answer key\n\n1. b\n")
        self.assertFalse(ok)

    def test_answer_key_blocks_only_keys(self):
        blocks = disclosure.answer_key_blocks(LESSON)
        self.assertEqual([b[0] for b in blocks],
                         ["## Answer key (Section 1)", "## Answer key (Section 2)"])


if __name__ == "__main__":
    unittest.main()
