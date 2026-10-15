"""Image links in check_course_package.py: brackets in alt text, and failing closed (#114).

Run: python -m pytest tests/

Builds a throwaway course in a temp dir; the real modules/ tree is never touched.
"""
import pathlib, sys, tempfile, unittest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import check_course_package as ccp
import moodle_payload as mp

SLUG = "test-course"
DESIGN = "# Design\n\n| **Design status** | Draft |\n|---|---|\n"
BRACKETED = "Closing quote [ ’ U+2019 ] found as a word medial character"


def lesson(body):
    return ("# Lesson\n\n**Estimated time:** 30 minutes\n\n## Connect\n\n" + body +
            "\n\n## Content\n\nx\n\n## Challenge\n\nx\n\n## Change\n\nx\n")


class ImageLinks(unittest.TestCase):
    """The parser on its own."""

    def test_bracketed_alt_is_parsed(self):
        links, unparsed = ccp.image_links(f"![{BRACKETED}](assets/ss-02-a.png)")
        self.assertEqual(links, [(BRACKETED, "assets/ss-02-a.png", 1)])
        self.assertEqual(unparsed, [])

    def test_nested_brackets_are_parsed(self):
        links, _ = ccp.image_links("![a [b [c] d] e](assets/x.png)")
        self.assertEqual(links[0][:2], ("a [b [c] d] e", "assets/x.png"))

    def test_alt_may_span_lines(self):
        links, _ = ccp.image_links("![The dialog\nwith Regex ticked](assets/x.png)")
        self.assertEqual(links[0][1], "assets/x.png")

    def test_escaped_brackets_do_not_count(self):
        links, unparsed = ccp.image_links(r"![a \[ b](assets/x.png)")
        self.assertEqual(links[0][1], "assets/x.png")
        self.assertEqual(unparsed, [])

    def test_unbalanced_brackets_are_reported_with_their_line(self):
        for alt in ("open [ only", "close ] only"):
            with self.subTest(alt=alt):
                links, unparsed = ccp.image_links(f"intro\n\n![{alt}](assets/x.png)")
                self.assertEqual(links, [])
                self.assertEqual(unparsed, [3])

    def test_a_title_is_reported_not_skipped(self):
        _, unparsed = ccp.image_links('![alt](assets/x.png "title")')
        self.assertEqual(unparsed, [1])

    def test_walk_stops_at_a_blank_line(self):
        # The unbalanced `[` must not pair with a later link's `]` in the next paragraph.
        links, unparsed = ccp.image_links("![open [ alt\n\nsee [this](assets/y.png)")
        self.assertEqual(links, [])
        self.assertEqual(unparsed, [1])

    def test_code_is_not_an_image(self):
        text = ccp.strip_code("`![x](y)` and\n```\n![x [\n```\n![ok](assets/z.png)\n")
        links, unparsed = ccp.image_links(text)
        self.assertEqual(unparsed, [])
        self.assertEqual(links, [("ok", "assets/z.png", 5)])

    def test_the_check_and_the_renderer_agree(self):
        # What the check accepts, Python-Markdown renders as an image; what it rejects,
        # the renderer does not. That is what makes rejecting the rest safe.
        for alt in (BRACKETED, "a [b [c] d] e", "open [ only", "close ] only"):
            with self.subTest(alt=alt):
                src = f"![{alt}](assets/x.png)"
                parsed = bool(ccp.image_links(src)[0])
                self.assertEqual(parsed, "<img" in mp.render(src))


class CheckCourse(unittest.TestCase):
    """The three checks the issue lists, on a real (temporary) course folder."""

    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.folder = pathlib.Path(self._tmp.name) / SLUG
        (self.folder / "assets").mkdir(parents=True)
        (self.folder / "00-design.md").write_text(DESIGN, encoding="utf-8")
        (self.folder / "01-overview.md").write_text(
            lesson("**Watch the video:** _To be recorded at stage 8._"), encoding="utf-8")

    def tearDown(self):
        self._tmp.cleanup()

    def write_lesson(self, body):
        (self.folder / "02-lesson.md").write_text(lesson(body), encoding="utf-8")

    def test_missing_file_behind_bracketed_alt_is_a_warning(self):
        self.write_lesson(f"![{BRACKETED}](assets/ss-02-missing.png)")
        errors, warnings = ccp.check_course(self.folder)
        self.assertEqual([e for e in errors if "image" in e], [])
        self.assertTrue(any("not captured yet" in w and "ss-02-missing.png" in w
                            for w in warnings), warnings)

    def test_bracketed_alt_counts_as_a_reference_and_a_visual(self):
        (self.folder / "assets" / "ss-02-here.png").write_bytes(b"png")
        self.write_lesson(f"![{BRACKETED}](assets/ss-02-here.png)")
        errors, warnings = ccp.check_course(self.folder)
        self.assertFalse(any("not referenced" in e for e in errors), errors)
        self.assertFalse(any("no visual yet" in w for w in warnings), warnings)

    def test_unparseable_link_is_an_error(self):
        (self.folder / "assets" / "ss-02-here.png").write_bytes(b"png")
        self.write_lesson("![open [ bracket](assets/ss-02-here.png)")
        errors, _ = ccp.check_course(self.folder)
        self.assertTrue(any("02-lesson.md:7: image link could not be parsed" in e
                            for e in errors), errors)


if __name__ == "__main__":
    unittest.main()
