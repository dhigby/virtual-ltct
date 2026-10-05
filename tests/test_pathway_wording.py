"""Spec 006 pathway strings keep a CBC level off every pathway screen (R13, SC-004).

Every string in the "Spec 006: pathways" block of local_ltuse's English lang file must pass
cbc_wording.report_label_problems(strict=True), the rule spec 004 applies to its report labels:
no string names a level as a learner's, says anyone reached, achieved or attained one, or
mentions certification. Run: python -m pytest tests/test_pathway_wording.py
"""
import pathlib
import re
import sys

import pytest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import cbc_wording as w  # noqa: E402

LANG = REPO / "moodle" / "local_ltuse" / "lang" / "en" / "local_ltuse.php"
BLOCK_START = "// Spec 006: pathways."
BLOCK_END = "// End of the spec 006 pathways block."
# $string['id'] = 'text'; with PHP single-quote escapes (\' and \\).
STRING = re.compile(r"""\$string\['([^']+)'\]\s*=\s*'((?:[^'\\]|\\.)*)'\s*;""")


def _block():
    text = LANG.read_text(encoding="utf-8")
    start = text.find(BLOCK_START)
    assert start != -1, "the 'Spec 006: pathways' block is missing from %s" % LANG
    end = text.find(BLOCK_END, start)
    assert end != -1, "the spec 006 pathways block has no end marker"
    return text[start:end]


def _strings():
    block = _block()
    found = [(sid, re.sub(r"\\(.)", r"\1", value)) for sid, value in STRING.findall(block)]
    # Every $string line in the block must be one the pattern reads, or a string escapes the check.
    assert len(found) == block.count("$string["), "a string in the block was not parsed"
    return found


STRINGS = _strings()


def test_block_has_the_pathway_strings():
    ids = {sid for sid, _ in STRINGS}
    assert {"pathways", "pathway:aimsat", "pathway:done", "pathway:roletotal"} <= ids


@pytest.mark.parametrize("sid,text", STRINGS, ids=[sid for sid, _ in STRINGS])
def test_string_passes_strict_report_rule(sid, text):
    assert w.report_label_problems(text, strict=True) == [], sid


def test_the_rule_would_catch_a_bad_string():
    # The check has teeth: wording the block must never use is refused.
    assert w.report_label_problems("You reached level 2", strict=True)
    assert w.report_label_problems("Achieved", strict=True)
    assert w.report_label_problems("Certified LTC", strict=True)
