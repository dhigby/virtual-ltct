"""The library page and Further Information rendering (spec 014, contracts/site-routes.md).

Run: python -m pytest tests/
"""
import pathlib
import sys

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import library  # noqa: E402

CATS = {"Core Technical": ["Keyboards", "Fonts & Encoding"], "Core": ["Mentoring"]}
PAGES = {"Keyboards": "core-technical/keyboards.md",
         "Fonts & Encoding": "core-technical/fonts-and-encoding.md"}
KEYMAN = {"title": "Keyman help", "url": "https://help.keyman.com/",
          "description": "Install and use Keyman keyboards.", "type": "guide",
          "competencies": ["Keyboards", "Fonts & Encoding"], "language": "English"}
FONTS = {"title": "Font guide", "url": "https://software.sil.org/fonts/",
         "description": "SIL fonts.", "type": "site",
         "competencies": ["Fonts & Encoding"], "language": "French"}


def test_library_groups_by_category_then_competency():
    page = library.library_page([KEYMAN, FONTS], CATS, PAGES)
    cat, keyboards, fonts = (page.index("## Core Technical"), page.index("### Keyboards"),
                             page.index("### Fonts & Encoding"))
    assert cat < keyboards < fonts
    assert "## Core\n" not in page  # a category with no resources is left out


def test_resource_heading_carries_description_type_language_and_links():
    page = library.library_page([FONTS], CATS, PAGES)
    assert "#### Font guide" in page
    assert "SIL fonts." in page
    assert "Site" in page and "French" in page
    assert "](https://software.sil.org/fonts/)" in page
    assert "[Fonts & Encoding](core-technical/fonts-and-encoding.md)" in page


def test_first_occurrence_keeps_the_plain_anchor():
    # The toc extension gives the first heading the plain id and suffixes later ones, so
    # the headings carry no explicit id that would collide.
    page = library.library_page([KEYMAN], CATS, PAGES)
    assert page.count("\n#### Keyman help\n") == 2


def test_further_information_filters_to_one_competency():
    out = library.further_information([KEYMAN, FONTS], "Keyboards")
    assert "## Further Information" in out
    assert "Keyman help" in out and "Install and use Keyman keyboards." in out
    assert "Font guide" not in out


def test_competency_with_no_resources_gets_nothing():
    assert library.further_information([KEYMAN], "Mentoring") == ""


def test_text_only():
    page = library.library_page([KEYMAN, FONTS], CATS, PAGES)
    assert "<img" not in page and "<iframe" not in page and "![" not in page
