"""resources.yaml validation (spec 014, FR-002, FR-003). Run: python -m pytest tests/"""
import pathlib
import sys

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import library  # noqa: E402

FRAMEWORK = {"Keyboards", "Fonts & Encoding"}


def entry(**over):
    e = {"title": "Installing a keyboard", "url": "https://help.keyman.com/",
         "description": "How to install a Keyman keyboard.", "type": "guide",
         "competencies": ["Keyboards"], "language": "English"}
    e.update(over)
    return e


def test_valid_entry_passes():
    assert library.validate([entry(), entry(competencies=["Fonts & Encoding"])],
                            FRAMEWORK) == []


def test_unknown_competency_fails():
    errors = library.validate([entry(competencies=["Keyboard"])], FRAMEWORK)
    assert any("'Keyboard' is not in competencies.yaml" in e for e in errors)


def test_missing_field_fails():
    e = entry()
    del e["description"]
    assert any("`description`" in x for x in library.validate([e], FRAMEWORK))
    assert library.validate([entry(language=" ")], FRAMEWORK)
    assert library.validate([entry(competencies=[])], FRAMEWORK)


def test_bad_type_fails():
    assert any("`type`" in e for e in library.validate([entry(type="book")], FRAMEWORK))


def test_non_http_url_fails():
    for url in ("modules/bloom/01-intro.md", "ftp://x.org/a", ""):
        assert library.validate([entry(url=url)], FRAMEWORK), url


def test_not_a_list_fails():
    assert library.validate({"title": "x"}, FRAMEWORK)
    assert library.validate(["just a string"], FRAMEWORK)


def test_repo_file_is_valid():
    framework = library.framework_names(REPO / "competencies.yaml")
    assert library.validate(library.load(REPO / "resources.yaml"), framework) == []
