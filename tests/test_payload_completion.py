"""The completion rule and the course metadata in the payload, and the gate that refuses
a payload without them (spec 004, US1 and US5). Run: python -m pytest tests/

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
LESSON = "# Lesson one\n\n**Estimated time:** 30 minutes\n\n## Connect\n\nHello.\n"
QUIZ = """# Quiz

## Assessment quiz (2 questions)

%s

**Question 1:** Which is right?
- A) This
- B) That

**Question 2:** And this one?
- A) Here
- B) There

## Answer key

1. A | 2. B
"""
PASS_80 = "You need **80% (2/2)** to pass."
PASS_0 = "You need **0% (0/2)** to pass."
NO_THRESHOLD = "Answer both questions."
# Parses to nothing, so the builder withholds it and publishes the placeholder page.
BROKEN_QUIZ = "# Quiz\n\nNo questions here, and no key.\n"
README = """---
title: Fixture course
slug: fixture-course
%s
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
    monkeypatch.setattr(cmp, "MODULES", modules)

    def make(files=None, frontmatter="competencies:\n  - Translation Tools"):
        for name, text in (files or {}).items():
            (folder / name).write_text(text, encoding="utf-8")
        (folder / "README.md").write_text(README % frontmatter, encoding="utf-8")
        manifest, pages, assets = mp.Payload(folder, "learner").build()
        payload = mp.write_payload(manifest, pages, assets, tmp_path / "out")
        return manifest, payload
    return make


def modules_of(manifest):
    return {m["source"]: m for s in manifest["sections"] for m in s["modules"]}


def gate(payload, edit=None):
    path = payload / "manifest.json"
    if edit:
        manifest = json.loads(path.read_text(encoding="utf-8"))
        edit(manifest)
        path.write_text(json.dumps(manifest), encoding="utf-8")
    problems, _ = cmp.check(payload)
    return problems


# --- the rule (T011) ---------------------------------------------------------------------

def test_completion_for_is_the_one_rule():
    assert mp.completion_for("page", None) == "view"
    assert mp.completion_for("page", 80) == "view"
    assert mp.completion_for("quiz", 80) == "pass"
    assert mp.completion_for("quiz", 1) == "pass"
    assert mp.completion_for("quiz", None) == "submit"
    assert mp.completion_for("quiz", 0) == "submit"


def test_a_lesson_page_is_view(course):
    manifest, _ = course()
    assert modules_of(manifest)["01-lesson.md"]["completion"] == "view"


def test_a_withheld_quiz_placeholder_is_view(course):
    manifest, _ = course({"02-quiz.md": BROKEN_QUIZ})
    assert manifest["withheld"] == ["02-quiz.md"]
    mod = modules_of(manifest)["02-quiz.md"]
    assert mod["kind"] == "page"
    assert mod["completion"] == "view"


def test_a_quiz_with_a_threshold_is_pass(course):
    manifest, _ = course({"02-quiz.md": QUIZ % PASS_80})
    assert manifest["quizzes"][0]["threshold_pct"] == 80
    assert manifest["quizzes"][0]["completion"] == "pass"
    assert modules_of(manifest)["02-quiz.md"]["completion"] == "pass"


@pytest.mark.parametrize("line", [NO_THRESHOLD, PASS_0])
def test_a_quiz_without_a_threshold_is_submit(course, line):
    manifest, _ = course({"02-quiz.md": QUIZ % line})
    assert not manifest["quizzes"][0]["threshold_pct"]
    assert manifest["quizzes"][0]["completion"] == "submit"
    assert modules_of(manifest)["02-quiz.md"]["completion"] == "submit"


def test_the_manifest_completion_is_all(course):
    manifest, payload = course({"02-quiz.md": QUIZ % PASS_80})
    assert manifest["completion"] == "all"
    assert gate(payload) == []


def test_the_rule_names_no_moodle_field(course):
    # Principle II: the payload says what counts, never how Moodle stores it.
    manifest, payload = course({"02-quiz.md": QUIZ % PASS_80})
    text = (payload / "manifest.json").read_text(encoding="utf-8")
    for moodle in ("completionview", "completionusegrade", "completionpassgrade",
                   "enablecompletion"):
        assert moodle not in text


# --- the gate refuses (T011, T015) -------------------------------------------------------

def test_gate_refuses_a_module_without_completion(course):
    _, payload = course()

    def drop(m):
        del m["sections"][0]["modules"][0]["completion"]
    problems = gate(payload, drop)
    assert any("ltct:%s:01" % SLUG in p and "completion" in p for p in problems), problems


def test_gate_refuses_an_unknown_completion_value(course):
    _, payload = course()

    def done(m):
        m["sections"][0]["modules"][0]["completion"] = "done"
    problems = gate(payload, done)
    assert any("'done'" in p for p in problems), problems


def test_gate_refuses_a_quiz_without_completion(course):
    _, payload = course({"02-quiz.md": QUIZ % PASS_80})

    def drop(m):
        del m["quizzes"][0]["completion"]
    assert any("completion" in p for p in gate(payload, drop))


def test_gate_refuses_a_manifest_completion_other_than_all(course):
    _, payload = course()

    def any_(m):
        m["completion"] = "any"
    assert any("'any'" in p for p in gate(payload, any_))

    def missing(m):
        del m["completion"]
    assert any("completion" in p for p in gate(payload, missing))


# --- course metadata (T046) --------------------------------------------------------------

def test_competencies_are_the_frontmatter_list_verbatim_in_order(course):
    manifest, payload = course(frontmatter=(
        "target_outcome_level: \"2 - With Assistance\"\n"
        "competencies:\n  - Translation Tools\n  - Fonts & Encoding\n  - Keyboards"))
    assert manifest["competencies"] == ["Translation Tools", "Fonts & Encoding", "Keyboards"]
    assert manifest["target_outcome_level"] == "2 - With Assistance"
    assert gate(payload) == []


def test_target_level_is_none_when_absent(course):
    manifest, payload = course()
    assert manifest["target_outcome_level"] is None
    written = json.loads((payload / "manifest.json").read_text(encoding="utf-8"))
    assert written["target_outcome_level"] is None


def test_no_competencies_is_an_empty_list(course):
    manifest, payload = course(frontmatter="")
    assert manifest["competencies"] == []
    assert gate(payload) == []


def test_gate_refuses_a_competency_not_in_the_framework(course):
    _, payload = course(frontmatter="competencies:\n  - Translation Tools\n  - fixture-not-a-competency")
    problems = gate(payload)
    assert any("fixture-not-a-competency" in p for p in problems), problems


def test_gate_refuses_a_near_miss_name(course):
    # Names must match exactly, & and capitalisation included.
    _, payload = course(frontmatter="competencies:\n  - Fonts and Encoding")
    assert any("Fonts and Encoding" in p for p in gate(payload))


def test_gate_accepts_meta_uncategorized(course):
    # It is in competencies.yaml (category Meta); the publisher drops it, the gate does
    # not refuse it.
    _, payload = course(frontmatter="competencies:\n  - Uncategorized")
    assert gate(payload) == []
