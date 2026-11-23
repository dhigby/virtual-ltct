"""A Vimeo link with a #t= start time keeps it in Moodle.

Moodle's media_vimeo player rebuilds the embed from the video id alone, so the publisher
writes the player itself for these links (moodle_payload.vimeo_start_embeds).
Run: python -m pytest tests/
"""
import pathlib
import sys
import unittest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import moodle_payload as mp  # noqa: E402


def page(md):
    return mp.vimeo_start_embeds(mp.render(md))


class VimeoStart(unittest.TestCase):
    def test_start_time_reaches_the_player(self):
        html = page("**Watch the video:** [Part 1, from 2:00](https://vimeo.com/1222691505#t=2m0s)")
        self.assertIn('<iframe src="https://player.vimeo.com/video/1222691505#t=2m0s"', html)
        self.assertIn('title="Part 1, from 2:00"', html)

    def test_link_kept_and_filter_told_to_leave_it(self):
        html = page("[Part 1](https://vimeo.com/1222691505#t=4m38s)")
        self.assertIn('<a class="nomediaplugin" href="https://vimeo.com/1222691505#t=4m38s">'
                      'Part 1</a>', html)
        self.assertEqual(html.count("<iframe"), 1)

    def test_unlisted_hash_goes_on_as_h(self):
        html = page("[x](https://vimeo.com/123/abc123#t=90)")
        self.assertIn('src="https://player.vimeo.com/video/123?h=abc123#t=90"', html)

    def test_hours_and_seconds_only(self):
        self.assertIn("#t=1h2m3s", page("[x](https://vimeo.com/1#t=1h2m3s)"))
        self.assertIn("video/1#t=45s", page("[x](https://vimeo.com/1#t=45s)"))

    def test_link_without_start_is_left_to_moodle(self):
        md = "[x](https://vimeo.com/1222691505)"
        self.assertEqual(page(md), mp.render(md))

    def test_quote_in_title_cannot_break_the_attribute(self):
        html = page('[say "hi"](https://vimeo.com/1#t=5s)')
        title = html.split('title="', 1)[1].split('" style=', 1)[0]
        self.assertNotIn('"', title)

    def test_course_pages_carry_the_player(self):
        folder = mp.MODULES / "Paratext 9 advanced support"
        if not folder.exists():
            self.skipTest("course not present")
        _, pages, _ = mp.Payload(folder, "learner").build()
        expected = {"02-project-history-accuracy": "1222691505#t=2m0s",
                    "03-user-roles-and-edit-access": "1222691505#t=4m38s",
                    "05-snapshotting-to-a-reference-project": "1222710442#t=6m56s"}
        for stem, tail in expected.items():
            self.assertIn('<iframe src="https://player.vimeo.com/video/%s"' % tail,
                          pages["pages/%s.html" % stem])


if __name__ == "__main__":
    unittest.main()
