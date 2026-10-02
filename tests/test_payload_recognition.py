"""The payload's recognition block and the gate's recognition check (spec 013, US1 and US2).
Run: python -m pytest tests/

The block says only whether a publish is a delivery, and on delivery the certificate's
identity (Principle II). Whether it is a delivery is course_stage.py's to say, so these tests
replace stage_for() rather than build a stage-8 course. The gate's check runs on hand-written
manifests and a throwaway site directory.
"""
import pathlib
import sys

import pytest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import check_moodle_payload as cmp  # noqa: E402
import moodle_payload as mp  # noqa: E402

SLUG = "fixture-course"


def stage(n):
    return lambda folder, use_gh=True: {"stage": n}


@pytest.mark.parametrize("n,delivery", [(7, False), (4, False), (8, True)])
def test_delivery_is_stage_8(tmp_path, monkeypatch, n, delivery):
    monkeypatch.setattr(mp, "stage_for", stage(n))
    block = mp.recognition_for(tmp_path, SLUG)
    assert block["delivery"] is delivery
    if delivery:
        assert block["certificate"] == {"idnumber": "ltct:%s:certificate" % SLUG}
    else:
        assert "certificate" not in block


def test_a_real_course_short_of_stage_8_is_no_delivery(tmp_path):
    folder = tmp_path / SLUG
    folder.mkdir()
    (folder / "00-design.md").write_text("# Design\n", encoding="utf-8")
    assert mp.recognition_for(folder, SLUG) == {"delivery": False}


def manifest(**over):
    m = {"slug": SLUG, "title": "Fixture course", "competencies": ["Paratext"],
         "target_outcome_level": "2 - With Assistance", "recognition": {"delivery": False},
         "sections": [{"modules": [{"idnumber": "ltct:%s:01" % SLUG, "source": "01-a.md"}]}]}
    m.update(over)
    return m


DELIVERY = {"delivery": True, "certificate": {"idnumber": "ltct:%s:certificate" % SLUG}}


@pytest.fixture
def site(tmp_path):
    """A site directory with the repo's real badge template and settings."""
    d = tmp_path / "site"
    (d / "settings").mkdir(parents=True)
    for rel in ("badges.yaml", "settings/badges.yaml"):
        (d / rel).write_text((REPO / "moodle" / "site" / rel).read_text(encoding="utf-8"),
                             encoding="utf-8")
    return d


def test_a_clean_pilot_and_a_clean_delivery_pass(site):
    assert cmp.check_recognition(SLUG, manifest(), site) == []
    assert cmp.check_recognition(SLUG, manifest(recognition=DELIVERY), site) == []


def test_recognition_block_is_required(site):
    for bad in (None, {}, {"delivery": "yes"}):
        m = manifest(recognition=bad)
        assert cmp.check_recognition(SLUG, m, site), bad


def test_a_title_that_says_certification_is_refused(site):
    problems = cmp.check_recognition(SLUG, manifest(title="Certification prep"), site)
    assert any("badge name" in p and "certified" in p for p in problems)


def test_delivery_without_a_certificate_and_pilot_with_one_are_refused(site):
    assert cmp.check_recognition(SLUG, manifest(recognition={"delivery": True}), site)
    assert cmp.check_recognition(
        SLUG, manifest(recognition={"delivery": False, "certificate": DELIVERY["certificate"]}),
        site)


def test_the_certificate_idnumber_must_be_its_own(site):
    wrong = {"delivery": True, "certificate": {"idnumber": "ltct:other:certificate"}}
    assert cmp.check_recognition(SLUG, manifest(recognition=wrong), site)
    clash = manifest(recognition=DELIVERY, sections=[{"modules": [
        {"idnumber": "ltct:%s:certificate" % SLUG, "source": "certificate.md"}]}])
    assert any("uses the certificate's idnumber" in p
               for p in cmp.check_recognition(SLUG, clash, site))


def test_an_overlong_certificate_idnumber_is_refused(site):
    slug = "x" * 90
    block = {"delivery": True, "certificate": {"idnumber": "ltct:%s:certificate" % slug}}
    problems = cmp.check_recognition(slug, manifest(slug=slug, recognition=block), site)
    assert any("characters" in p for p in problems)


def test_a_site_with_no_badges_checks_only_the_block(tmp_path):
    assert cmp.check_recognition(SLUG, manifest(title="Certification prep"), tmp_path) == []


def test_a_delivery_needs_a_cbc_target(site):
    for target in ("", "Has knowledge", "2 - with assistance"):
        m = manifest(recognition=DELIVERY, target_outcome_level=target)
        assert cmp.check_recognition(SLUG, m, site), target
    # A pilot is not held to it: its badge issues nothing.
    assert cmp.check_recognition(SLUG, manifest(target_outcome_level=""), site) == []
