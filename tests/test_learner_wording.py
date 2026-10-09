"""Spec 007 learner strings keep a CBC level and certification off the learner home block.

Every string in block_ltuse's English lang file must pass
cbc_wording.report_label_problems(strict=True), the rule spec 004 applies to its report labels:
no string names a level as a learner's, says anyone reached, achieved or attained one, or
mentions certification (FR-011). Each must also pass cbc_wording.check_recognition(), the
badge and certificate rule, so no string says a learner is at or holds a level, by number or
by CBC label (Principle V). And every string the block asks for, in its PHP, its templates
and its app handler, must be one the lang file defines, so no learner sees a [[missing]] id
(FR-012). The same two rules hold the "Spec 007: learner experience" block of local_ltuse's
lang file, which the next-lesson button on a lesson page reads.
Run: python -m pytest tests/test_learner_wording.py
"""
import pathlib
import re
import sys

import pytest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import cbc_wording as w  # noqa: E402

BLOCK = REPO / "moodle" / "block_ltuse"
LANG = BLOCK / "lang" / "en" / "block_ltuse.php"
MOBILE = BLOCK / "db" / "mobile.php"
# $string['id'] = 'text'; with PHP single-quote escapes (\' and \\).
STRING = re.compile(r"""\$string\['([^']+)'\]\s*=\s*'((?:[^'\\]|\\.)*)'\s*;""")
# get_string('id', 'block_ltuse'...) in PHP, either quote.
GET_STRING = re.compile(r"""get_string\(\s*['"]([^'"]+)['"]\s*,\s*['"]block_ltuse['"]""")
# {{#str}}id, block_ltuse{{/str}} on the web, <%#str%>id, block_ltuse<%/str%> in the app.
TEMPLATE_STR = re.compile(r"""(?:\{\{#str\}\}|<%#str%>)\s*([^,\s{}<>]+)\s*,\s*block_ltuse\b""")
# ['id', 'block_ltuse'] in db/mobile.php's lang list.
MOBILE_LANG = re.compile(r"""\[\s*['"]([^'"]+)['"]\s*,\s*['"]block_ltuse['"]\s*\]""")


def _strings():
    text = LANG.read_text(encoding="utf-8")
    found = [(sid, re.sub(r"\\(.)", r"\1", value)) for sid, value in STRING.findall(text)]
    # Every $string line must be one the pattern reads, or a string escapes the check.
    assert len(found) == text.count("$string["), "a string in %s was not parsed" % LANG
    return found


STRINGS = _strings()
DEFINED = {sid for sid, _ in STRINGS}


def test_lang_file_has_the_plugin_strings():
    assert {"pluginname", "ltuse:addinstance", "ltuse:myaddinstance", "privacy:metadata"} <= DEFINED


@pytest.mark.parametrize("sid,text", STRINGS, ids=[sid for sid, _ in STRINGS])
def test_string_passes_strict_report_rule(sid, text):
    assert w.report_label_problems(text, strict=True) == [], sid
    assert w.check_recognition(text) == [], sid


def test_the_rule_would_catch_a_bad_string():
    # The check has teeth: wording the block must never use is refused.
    assert w.report_label_problems("You are certified", strict=True)
    assert w.report_label_problems("You reached level 2", strict=True)
    # A level held, which only the recognition rule refuses.
    assert w.check_recognition("You are now at level 2")
    assert w.check_recognition("You hold 2 - With Assistance")


def _used():
    """(where, id) for every block_ltuse string the plugin asks for."""
    used = []
    for path in sorted(BLOCK.rglob("*.php")):
        if path == LANG:
            continue
        for sid in GET_STRING.findall(path.read_text(encoding="utf-8")):
            used.append((path.relative_to(REPO).as_posix(), sid))
    for path in sorted(BLOCK.rglob("*.mustache")):
        for sid in TEMPLATE_STR.findall(path.read_text(encoding="utf-8")):
            used.append((path.relative_to(REPO).as_posix(), sid))
    if MOBILE.exists():
        for sid in MOBILE_LANG.findall(MOBILE.read_text(encoding="utf-8")):
            used.append((MOBILE.relative_to(REPO).as_posix(), sid))
    return used


def test_every_string_asked_for_is_defined():
    used = _used()
    # block_ltuse.php asks for pluginname at least, so an empty list means the patterns broke.
    assert used, "no get_string(..., 'block_ltuse') found under %s" % BLOCK
    missing = sorted({"%s: %s" % (where, sid) for where, sid in used if sid not in DEFINED})
    assert missing == [], "strings asked for but not in %s: %s" % (LANG.name, missing)


def test_the_usage_check_would_catch_a_missing_id():
    # The patterns read each form the block uses, so an undefined id would be reported.
    assert GET_STRING.findall("get_string('nosuchid', 'block_ltuse', $a)") == ["nosuchid"]
    assert TEMPLATE_STR.findall("{{#str}}nosuchid, block_ltuse{{/str}}") == ["nosuchid"]
    assert TEMPLATE_STR.findall("<%#str%>nosuchid, block_ltuse<%/str%>") == ["nosuchid"]
    assert MOBILE_LANG.findall("['nosuchid', 'block_ltuse'],") == ["nosuchid"]
    assert "nosuchid" not in DEFINED


# Spec 007's local_ltuse strings: the next-lesson button on a lesson page (R6).
LTUSE = REPO / "moodle" / "local_ltuse"
LTUSE_LANG = LTUSE / "lang" / "en" / "local_ltuse.php"
LTUSE_START = "// Spec 007: learner experience."
LTUSE_END = "// End of the spec 007 learner experience block."
# get_string('nextlesson'|'backtocourse', 'local_ltuse'...) in PHP, either quote.
LTUSE_GET_STRING = re.compile(
    r"""get_string\(\s*['"](nextlesson|backtocourse)['"]\s*,\s*['"]local_ltuse['"]""")


def _ltuse_block():
    text = LTUSE_LANG.read_text(encoding="utf-8")
    start = text.find(LTUSE_START)
    assert start != -1, "the 'Spec 007: learner experience' block is missing from %s" % LTUSE_LANG
    end = text.find(LTUSE_END, start)
    assert end != -1, "the spec 007 learner experience block has no end marker"
    return text[start:end]


def _ltuse_strings():
    block = _ltuse_block()
    found = [(sid, re.sub(r"\\(.)", r"\1", value)) for sid, value in STRING.findall(block)]
    # Every $string line in the block must be one the pattern reads, or a string escapes the check.
    assert len(found) == block.count("$string["), "a string in the block was not parsed"
    return found


LTUSE_STRINGS = _ltuse_strings()
LTUSE_DEFINED = {sid for sid, _ in LTUSE_STRINGS}


def test_ltuse_block_has_the_next_lesson_strings():
    assert {"nextlesson", "backtocourse"} <= LTUSE_DEFINED


@pytest.mark.parametrize("sid,text", LTUSE_STRINGS, ids=[sid for sid, _ in LTUSE_STRINGS])
def test_ltuse_string_passes_strict_report_rule(sid, text):
    assert w.report_label_problems(text, strict=True) == [], sid
    assert w.check_recognition(text) == [], sid


def test_every_next_lesson_string_asked_for_is_defined():
    used = []
    for path in sorted(LTUSE.rglob("*.php")):
        if path == LTUSE_LANG:
            continue
        for sid in LTUSE_GET_STRING.findall(path.read_text(encoding="utf-8")):
            used.append((path.relative_to(REPO).as_posix(), sid))
    missing = sorted({"%s: %s" % (where, sid) for where, sid in used if sid not in LTUSE_DEFINED})
    assert missing == [], "strings asked for but not in the spec 007 block: %s" % missing
    # The pattern reads the form the hook uses.
    assert LTUSE_GET_STRING.findall("get_string('nextlesson', 'local_ltuse', $a)") == ["nextlesson"]
