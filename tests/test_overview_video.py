"""The overview video lives on the course description, not in lesson 1 (Doug, 2026-10-26).

A README carries it above its first `##`; a course drafted before then carries it in lesson
1, and that must keep passing the checks and still reach the Moodle course description.
Run: python -m pytest tests/

Builds a throwaway course in a temp dir; the real modules/ tree is never touched.
"""
import pathlib, sys
import pytest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import check_course_package as ccp
import check_moodle_payload as cmp
import moodle_payload as mp

SLUG = "fixture-course"
DESIGN = "# Design\n\n| **Design status** | Draft |\n|---|---|\n"
VIDEO = "**Watch the video:** [Course overview](https://vimeo.com/123)"
PENDING = "**Watch the video:** _To be recorded at stage 8._"
SHOT = "![The Run basic checks dialog with Quotations ticked.](assets/ss-01-dialog.png)"


def lesson(intro="", body="x"):
    return ("# Lesson one\n\n**Estimated time:** 30 minutes\n\n" + intro +
            "\n\n## Connect\n\n" + body +
            "\n\n## Content\n\nx\n\n## Challenge\n\nx\n\n## Change\n\nx\n")


def readme(intro=""):
    return ("---\ntitle: Fixture course\nslug: fixture-course\ncompetencies:\n"
            "  - Translation Tools\ncontent_type: content\n---\n\n# Fixture course\n\n"
            "A fixture.\n\n" + intro + "\n\n## About This Course\n\nMore.\n")


@pytest.fixture
def course(tmp_path, monkeypatch):
    modules = tmp_path / "modules"
    folder = modules / SLUG
    (folder / "assets").mkdir(parents=True)
    (folder / "assets" / "ss-01-dialog.png").write_bytes(b"png")
    (folder / "00-design.md").write_text(DESIGN, encoding="utf-8")
    monkeypatch.setattr(cmp, "MODULES", modules)

    def make(readme_text, lesson_text):
        (folder / "README.md").write_text(readme_text, encoding="utf-8")
        (folder / "01-lesson.md").write_text(lesson_text, encoding="utf-8")
        return folder
    return make


def summary(folder):
    manifest, _, _ = mp.Payload(folder, "learner").build()
    return manifest["summary_html"]


# --- the checks ---------------------------------------------------------------------------

def test_readme_video_with_a_lesson_1_visual_passes(course):
    folder = course(readme(VIDEO), lesson(body=SHOT))
    assert ccp.lesson_visuals(folder) == ([], [])


def test_readme_video_does_not_count_as_lesson_1s_visual(course):
    folder = course(readme(VIDEO), lesson())
    assert ccp.lesson_visuals(folder) == (["01-lesson.md"], [])


def test_a_video_below_the_readme_intro_is_not_the_overview(course):
    folder = course(readme() + "\n" + VIDEO + "\n", lesson(body=SHOT))
    assert ccp.lesson_visuals(folder) == ([], ["README.md"])


def test_a_course_with_the_video_in_lesson_1_still_passes(course):
    # Drafted before 2026-10-26: the video is the overview and lesson 1's visual.
    folder = course(readme(), lesson(intro=PENDING))
    assert ccp.lesson_visuals(folder) == ([], [])


def test_a_bare_lesson_1_is_not_reported_twice_while_the_video_is_missing(course):
    folder = course(readme(), lesson())
    assert ccp.lesson_visuals(folder) == ([], ["README.md"])


def test_no_overview_video_anywhere_is_reported_against_the_readme(course):
    folder = course(readme(), lesson(body=SHOT))
    _, warnings = ccp.check_visuals(folder, SLUG)
    assert any("README.md: the course needs its overview video" in w for w in warnings)


# --- the course description ---------------------------------------------------------------

def test_readme_video_reaches_the_course_description(course):
    html = summary(course(readme(VIDEO), lesson()))
    assert 'href="https://vimeo.com/123"' in html
    assert "More." not in html


def test_lesson_1_video_is_copied_to_the_course_description(course):
    folder = course(readme(), lesson(intro=VIDEO))
    assert 'href="https://vimeo.com/123"' in summary(folder)
    assert VIDEO in (folder / "01-lesson.md").read_text(encoding="utf-8")


def test_a_lesson_video_below_lesson_1s_intro_is_not_copied(course):
    html = summary(course(readme(), lesson(body=VIDEO)))
    assert "vimeo" not in html


def test_a_video_still_to_be_recorded_stays_off_the_description(course):
    assert "recorded" not in summary(course(readme(PENDING), lesson()))
    assert "recorded" not in summary(course(readme(), lesson(intro=PENDING)))
