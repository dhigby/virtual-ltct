"""Each section's estimated-time line in the payload, and the gate that holds it to that line
alone (spec 007, US2, contracts/update-sections.md). Run: python -m pytest tests/

Builds a throwaway course in a temp dir; the real modules/ tree is never touched. The only
real file read is competencies.yaml, because that is what the gate checks names against.
"""
import json, pathlib, sys
import pytest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import check_moodle_payload as cmp
import moodle_payload as mp

SLUG = "fixture-course"
DESIGN = "# Design\n\n| **Design status** | Draft |\n|---|---|\n"
TIME_LINE = "**Estimated time:** 30 minutes"
TIME_HTML = "<p><strong>Estimated time:</strong> 30 minutes</p>"
LESSON = "# Lesson one\n\n%s\n\n## Connect\n\nHello.\n" % TIME_LINE
# The header with no blank line after it, as some lessons have it: the lesson renders all
# three lines in one paragraph, and time_text must carry only the first.
LESSON_RUN_ON = ("# Lesson two\n\n**Estimated time:** 45 minutes\n"
                 "**Target Audience:** Trainee consultants\n"
                 "**Format:** Asynchronous self-paced learning\n\n## Connect\n\nHello.\n")
SCENARIO_BANK = ("# Scenario bank\n\n**Estimated time:** 15 minutes to read through and "
                 "orient (the scenarios are worked inside lessons 1 and 2)\n\n## One\n\nA.\n")
PLACEHOLDER_TIME = "# Lesson three\n\n**Estimated time:** [X] minutes\n\n## Connect\n\nHi.\n"
QUIZ = """# Quiz

## Assessment quiz (1 question)

You need **0% (0/1)** to pass.

**Question 1:** Which is right?
- A) This
- B) That

## Answer key

1. A
"""
README = """---
title: Fixture course
slug: fixture-course
competencies:
  - Translation Tools
content_type: content
---

# Fixture course

A fixture.
"""


@pytest.fixture
def course(tmp_path, monkeypatch):
    modules = tmp_path / "modules"
    folder = modules / SLUG
    folder.mkdir(parents=True)
    (folder / "00-design.md").write_text(DESIGN, encoding="utf-8")
    (folder / "01-lesson.md").write_text(LESSON, encoding="utf-8")
    (folder / "README.md").write_text(README, encoding="utf-8")
    monkeypatch.setattr(cmp, "MODULES", modules)

    def make(files=None):
        for name, text in (files or {}).items():
            (folder / name).write_text(text, encoding="utf-8")
        manifest, pages, assets = mp.Payload(folder, "learner").build()
        payload = mp.write_payload(manifest, pages, assets, tmp_path / "out")
        return manifest, payload
    return make


def by_source(manifest):
    return {m["source"]: s for s in manifest["sections"] for m in s["modules"]}


def gate(payload, edit=None):
    path = payload / "manifest.json"
    if edit:
        manifest = json.loads(path.read_text(encoding="utf-8"))
        edit(manifest)
        path.write_text(json.dumps(manifest), encoding="utf-8")
    problems, _ = cmp.check(payload)
    return problems


# --- the builder (T039) ------------------------------------------------------------------

def test_a_lesson_carries_its_time_line_rendered_and_not_wrapped(course):
    # (a)
    manifest, _ = course()
    text = by_source(manifest)["01-lesson.md"]["time_text"]
    assert text == TIME_HTML
    assert text == mp.render(TIME_LINE)
    assert mp.PAGE_CLASS not in text


def test_a_run_on_header_gives_only_the_time_line(course):
    # (b)
    manifest, _ = course({"02-lesson.md": LESSON_RUN_ON})
    text = by_source(manifest)["02-lesson.md"]["time_text"]
    assert text == "<p><strong>Estimated time:</strong> 45 minutes</p>"
    assert "Target Audience" not in text


def test_the_scenario_bank_keeps_its_trailing_text(course):
    # (c)
    manifest, _ = course({"05-scenario-bank.md": SCENARIO_BANK})
    section = by_source(manifest)["05-scenario-bank.md"]
    assert section["time_text"] == (
        "<p><strong>Estimated time:</strong> 15 minutes to read through and orient (the "
        "scenarios are worked inside lessons 1 and 2)</p>")
    assert section["minutes"] == 15


@pytest.mark.parametrize("name,text", [("07-quiz.md", QUIZ),
                                       ("02-lesson.md", PLACEHOLDER_TIME)])
def test_no_time_line_gives_empty(course, name, text):
    # (d) A quiz has no header line; "[X] minutes" is a draft placeholder, not a time.
    manifest, _ = course({name: text})
    assert by_source(manifest)[name]["time_text"] == ""


def test_a_time_line_in_an_answer_key_never_reaches_the_summary(course):
    # The line is read from the header only, so an answer key's text stays out of time_text
    # (Principle IV), and out of minutes with it.
    quiz = QUIZ + "\n**Estimated time:** 5 minutes, answer B\n"
    manifest, payload = course({"07-quiz.md": quiz})
    section = by_source(manifest)["07-quiz.md"]
    assert section["time_text"] == ""
    assert section["minutes"] is None
    assert "answer B" not in (payload / "manifest.json").read_text(encoding="utf-8")


def test_a_time_line_after_the_header_is_not_the_time():
    # Below the first ## heading is the body, never the header.
    assert mp.time_text_of("# Quiz\n\n## Questions\n\n**Estimated time:** 5 minutes\n") == ""
    assert mp.time_text_of("---\ntitle: x\n---\n# L\n\n%s\n\n## Connect\n" % TIME_LINE) == TIME_HTML


def test_every_section_carries_time_text_beside_unchanged_minutes(course):
    # (e)
    manifest, _ = course({"02-lesson.md": LESSON_RUN_ON, "05-scenario-bank.md": SCENARIO_BANK,
                          "07-quiz.md": QUIZ})
    sections = manifest["sections"]
    assert len(sections) == 4
    for s in sections:
        assert isinstance(s["time_text"], str)
    assert [s["minutes"] for s in sections] == [30, 45, 15, None]
    for s in sections:
        assert s["minutes"] == mp.minutes_of(
            (cmp.MODULES / SLUG / s["modules"][0]["source"]).read_text(encoding="utf-8"))


def test_the_manifest_names_no_moodle_summary_field(course):
    # (g) Principle II: a string the payload carries, not Moodle's section summary.
    manifest, payload = course({"07-quiz.md": QUIZ})
    for s in manifest["sections"]:
        assert "time_text" in s
        assert "summary" not in s
        assert "summaryformat" not in s
    text = (payload / "manifest.json").read_text(encoding="utf-8")
    assert '"summaryformat"' not in text
    assert '"summary":' not in text


# --- the gate (T040) ---------------------------------------------------------------------

def test_gate_is_clean(course):
    _, payload = course({"02-lesson.md": LESSON_RUN_ON, "05-scenario-bank.md": SCENARIO_BANK,
                         "07-quiz.md": QUIZ})
    assert gate(payload) == []


@pytest.mark.parametrize("bad", [
    TIME_HTML + "<p>more</p>",
    TIME_HTML + "\n",
    "<p><strong>Estimated time:</strong>\n30 minutes</p>",
    TIME_HTML.replace("</p>", "<script>alert(1)</script></p>"),
    "<script>alert(1)</script>",
    "<p>Hello.</p>",
    "<p><strong>Estimated time:</strong> [X] minutes</p>",
    "<div class=\"local-ltuse-page\">\n%s\n</div>\n" % TIME_HTML,
])
def test_gate_refuses_anything_but_the_time_line(course, bad):
    # (f)
    _, payload = course()

    def edit(m):
        m["sections"][0]["time_text"] = bad
    problems = gate(payload, edit)
    assert "%s: section 1 time_text is not the Estimated time line" % SLUG in problems, problems


def test_gate_refuses_a_section_without_time_text(course):
    # (f) Fail closed: a builder that forgets the key is caught, not given a default.
    _, payload = course({"07-quiz.md": QUIZ})

    def drop(m):
        del m["sections"][1]["time_text"]
    problems = gate(payload, drop)
    assert "%s: section 2 time_text is not the Estimated time line" % SLUG in problems, problems


def test_gate_refuses_a_time_text_that_is_not_a_string(course):
    _, payload = course()

    def edit(m):
        m["sections"][0]["time_text"] = None
    assert any("section 1 time_text" in p for p in gate(payload, edit))


def test_gate_checks_the_reviewer_view_too(course, tmp_path):
    # Structural, so on every view, as completion and placement are.
    folder = cmp.MODULES / SLUG
    manifest, pages, assets = mp.Payload(folder, "reviewer").build()
    payload = mp.write_payload(manifest, pages, assets, tmp_path / "reviewer")

    def edit(m):
        m["sections"][0]["time_text"] = "<p>more</p>"
    assert any("section 1 time_text" in p for p in gate(payload, edit))
