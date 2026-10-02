"""The two CBC wording rules in scripts/cbc_wording.py. Run: python -m pytest tests/

report_label_problems() is spec 004's FR-010 rule, moved here unchanged; site_config.py's own
tests still cover it through _check_aim_label(). check_recognition() is spec 013's FR-004 and
FR-005 rule. The two disagree on "certificate" on purpose, and the tests pin that down.
"""
import pathlib
import re
import sys

import pytest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import cbc_wording as w  # noqa: E402

LABELS = w.cbc_labels()


def ok(text, **kw):
    return w.check_recognition(text, labels=LABELS, **kw) == []


def test_labels_are_outcome_levels_verbatim():
    assert LABELS[0] == "0 - No Competency" and LABELS[-1] == "4 - Expert"
    assert len(LABELS) == 5


@pytest.mark.parametrize("text", [
    "Certified in Paratext", "Paratext certification", "This certifies that",
    "Two certifications", "Certificate of competency", "certificate of competence",
    "An accredited course", "Accreditation", "Practitioner level", "Advanced Beginner",
    "Trainer badge", "Proficient user", "Level 3 - Independent achieved",
    "She reached 2 - With Assistance", "holds level 2",
])
def test_recognition_refuses(text):
    assert not ok(text)


@pytest.mark.parametrize("text", [
    "Paratext basics: training completed",
    "Certificate of training completed",
    "has completed the course",
    "The course is designed to support progress towards 2 - With Assistance.",
    "Awarded for completing the course Paratext basics.",
])
def test_recognition_allows(text):
    assert ok(text)


def test_certificate_divides_the_two_rules():
    # FR-003 names the document a certificate; a report label still may not say it.
    assert ok("Certificate of training completed")
    assert w.report_label_problems("Certificate of training completed")


def test_certified_is_refused_by_both_rules():
    assert not ok("Certified")
    assert w.report_label_problems("Certified")


def test_required_phrase():
    assert not ok("Paratext basics", require_completed=True)
    assert ok("Paratext basics: training completed", require_completed=True)
    assert ok("Paratext: you completed the course", require_completed=True)


def test_a_level_appears_only_as_its_label_after_the_aim_phrase():
    assert not ok("Progress towards 2 - With Assistance")
    assert not ok("designed to support progress towards 2 - with assistance")   # not exact
    assert ok("designed to support progress towards 4 - Expert")


def test_deny_patterns_compile_and_match_what_check_recognition_refuses():
    for pattern, why in w.DENY_PATTERNS:
        re.compile(pattern, re.I)
        assert why
    denied = lambda t: any(re.search(p, t, re.I) for p, _ in w.DENY_PATTERNS)  # noqa: E731
    assert denied("Certified") and denied("Practitioner") and denied("Level 3")
    assert not denied("Certificate of training completed")


def test_report_rule_unchanged():
    assert w.report_label_problems("Learner level") != []
    assert w.report_label_problems("Target level") == []
    assert w.report_label_problems("Achieved", strict=True) != []
    assert w.report_label_problems("Achieved") == []
