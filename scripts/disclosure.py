#!/usr/bin/env python3
"""The learner-view disclosure boundary, defined once.

A pilot learner must never be handed the design document, the mentor guide, a video
script, or a quiz answer key. Three separate scripts act on that rule, and before this
module existed each carried its own copy of the marker:

  * check_course_package.py -- enforces the canonical marker at authoring time (CI).
  * gen_course_site.py      -- strips the key blocks to build the learner view.
  * check_learner_view.py   -- gates the built output, proving nothing leaked.

Their comments all said the same thing: "if the marker changes, all three change
together." That held while there were three. The Moodle publisher (scripts/moodle_payload.py
and scripts/check_moodle_payload.py) would have made five, which is how a security-critical
regex drifts. So the shapes live here and every consumer imports them.

This module is pure: no I/O, no globals, no view state. `view` is passed in, because the
same course renders differently for a reviewer and for a learner.

The one rule that must survive every future edit, from INTENT.md:

    The learner view is a disclosure boundary and fails closed. A quiz whose answer key
    can't be cleanly separated is withheld entirely, not partially stripped. Never
    optimise this into "strip what we can."

`strip_answer_keys` is what implements that: it re-reads its own output and reports
ok=False rather than returning a page it is not certain about.
"""
import re

# --------------------------------------------------------------------------------------
# The marker
#
# An answer key sits under a "## Answer key" H2, optionally qualified
# ("## Answer key (Section 1)"), and may repeat within one file -- a backfilled course can
# carry more than one quiz. Standardised precisely so that no consumer ever has to guess
# where a key begins.
# --------------------------------------------------------------------------------------
CANONICAL_KEY_RE = re.compile(r"^## Answer key\b.*$")

# Mentor-only material (spec 012, design D3): an assignment's grading notes and model
# answer, or a mentor aside in any lesson. Same shape as the answer key: an H2, optionally
# qualified ("## Mentor only: grading notes"), repeatable, running to the next H1 or H2.
MENTOR_ONLY_RE = re.compile(r"^## Mentor only\b.*$")
RESTRICTED_RE = re.compile(rf"{CANONICAL_KEY_RE.pattern}|{MENTOR_ONLY_RE.pattern}")

# Anything that merely LOOKS like a key marker. check_course_package.py reports a
# non-conforming marker rather than letting it pass silently because a conforming one
# exists elsewhere in the same file.
ANY_KEY_MARKER_RE = re.compile(
    r"^[ ]{0,3}(?:#{1,6}[ \t]*answer[ \t]*key\b.*|\*{1,2}[ \t]*answer[ \t]*key\b.*)$",
    re.IGNORECASE | re.MULTILINE)

HEADING_RE = re.compile(r"^\s{0,3}(#{1,6})\s")

# Anything key-shaped that ISN'T the canonical marker, plus inline per-option answer
# marking. Finding one of these AFTER stripping means the file is not what we assumed,
# and the page is withheld whole.
RESIDUAL_RE = re.compile(
    r"^[ ]{0,3}(?:#{1,6}[ \t]*answer[ \t]*key\b|\*{1,2}[ \t]*answer[ \t]*key\b)"
    r"|\(\s*correct\s*\)"
    r"|^[ ]{0,3}\*{1,2}[ \t]*correct answer",
    re.I | re.M)

# What a mentor-only block holds, or a mis-levelled marker, found OUTSIDE a block. An
# author who writes "## Model answer" without the marker has published it; withhold.
RESTRICTED_RESIDUAL_RE = re.compile(
    r"^[ ]{0,3}#{1,6}[ \t]*(?:grading notes|model answer|marking notes|mentor notes"
    r"|mentor only)\b",
    re.I | re.M)

# --------------------------------------------------------------------------------------
# Excluded companions
#
# Non-markdown companions of the excluded files, plus LMS export artifacts. A real
# disclosure route, not a tidiness question: `09-video-script.pptx` sits beside its
# excluded .md, and the `qti_*.zip` quiz exports carry the correct answers as plain XML
# (<varequal>T</varequal>), so publishing them to a learner hands over the answer key a
# download at a time. Moodle's own backup format (.mbz) is the same hazard by another
# name -- a course backup contains the full question bank, answers included.
# --------------------------------------------------------------------------------------
LMS_EXPORT_RE = re.compile(r"^(qti[_-]|cypher-|moodle-)|\.(imscc|mbz)$", re.I)

EXCLUDED_MD_SUFFIXES = ("-mentor-guide.md", "-video-script.md")
EXCLUDED_STEM_SUFFIXES = ("-mentor-guide", "-video-script", "-quiz")
DESIGN_DOC = "00-design.md"


def excluded_md(name, view):
    """Markdown a pilot learner must not be shown. (A quiz is stripped, not dropped.)"""
    if view != "learner":
        return False
    if name == DESIGN_DOC:
        return True
    return name.endswith(EXCLUDED_MD_SUFFIXES)


def excluded_asset(name, view):
    """Non-markdown a pilot learner must not reach."""
    if view != "learner":
        return False
    # Everything before the FIRST dot, not the last: an asset may carry a delivery suffix
    # (`ss-09-quiz.full.png`, scripts/image_reduce.py), and taking the last dot would
    # leave `ss-09-quiz.full`, which ends in no excluded suffix and would slip through.
    stem = name.split(".", 1)[0].lower()
    if stem == "00-design" or stem.endswith(EXCLUDED_STEM_SUFFIXES):
        return True
    return bool(LMS_EXPORT_RE.search(name))


# --------------------------------------------------------------------------------------
# Stripping
# --------------------------------------------------------------------------------------
def strip_key_blocks(md, marker=CANONICAL_KEY_RE):
    """The source with its `marker` blocks removed -- what may legitimately ship.

    Both markers are H2s, so a block runs to the next H1 or H2.
    """
    lines, out, i = md.split("\n"), [], 0
    while i < len(lines):
        if not marker.match(lines[i]):
            out.append(lines[i])
            i += 1
            continue
        i += 1
        while i < len(lines):
            nxt = HEADING_RE.match(lines[i])
            if nxt and len(nxt.group(1)) <= 2:
                break
            i += 1
    return "\n".join(out)


def strip_answer_keys(md):
    """Remove every answer-key block. Returns (text, ok); ok=False => withhold page.

    It verifies its own output rather than trusting that check_course_package.py was
    green. Two reasons that is not belt-and-braces paranoia: that check only lints
    courses which opted into the pipeline with a 00-design.md, while the renderers are
    pointed at any course folder; and this is a disclosure boundary, where "CI was green
    on the repo" is a weaker guarantee than "this file parsed the way I expected".
    """
    text = strip_key_blocks(md).rstrip() + "\n"
    return text, RESIDUAL_RE.search(text) is None


def strip_restricted(md):
    """Remove every answer-key AND mentor-only block. Returns (text, ok); ok=False =>
    withhold the page whole.

    Every learner page goes through this, not only assignments: a lesson that grows a
    mentor aside is then safe at no cost. Like strip_answer_keys, it re-reads its output.
    """
    text = strip_key_blocks(md, RESTRICTED_RE).rstrip() + "\n"
    ok = RESIDUAL_RE.search(text) is None and RESTRICTED_RESIDUAL_RE.search(text) is None
    return text, ok


def answer_key_blocks(md, marker=CANONICAL_KEY_RE):
    """Every answer-key block in a quiz source, as a list of line lists.

    Used by the gates to assert, positively, that each key's own text is absent from
    whatever is about to ship -- far stricter than grepping the output for the words
    "answer key", because it catches a key published under a heading we failed to
    recognise.
    """
    lines, blocks, i = md.split("\n"), [], 0
    while i < len(lines):
        if not marker.match(lines[i]):
            i += 1
            continue
        h = HEADING_RE.match(lines[i])
        level = len(h.group(1)) if h else None
        block, i = [lines[i]], i + 1
        while i < len(lines):
            nxt = HEADING_RE.match(lines[i])
            if nxt and (level is None or len(nxt.group(1)) <= level):
                break
            block.append(lines[i])
            i += 1
        blocks.append(block)
    return blocks


def restricted_blocks(md):
    """Every answer-key and mentor-only block, in document order, for the positive checks."""
    return answer_key_blocks(md, RESTRICTED_RE)
