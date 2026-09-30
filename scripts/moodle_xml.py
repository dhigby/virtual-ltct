#!/usr/bin/env python3
"""Serialise parsed quiz questions to Moodle XML.

This is the one place in the Python half that is Moodle-shaped, and it is deliberately
small and at the bottom of the stack: scripts/moodle_payload.py emits neutral question
data, and this turns it into the interchange format local_ltuse_import_questions feeds to
qformat_xml. Replacing Moodle means replacing this file and scripts/moodle_client.py, and
nothing above them.

WHY XML rather than building question records through the API field by field:
qformat_xml is stable, well tested and understands every question type Moodle has.
Reimplementing it would mean re-fixing it on every Moodle release.

FRACTIONS ARE NOT FREE-FORM. Moodle accepts only a fixed set of grade fractions, and
local_ltuse imports with matchgrades='error', so a value outside the set fails the whole
import rather than rounding quietly. GRADE_FOR maps a count of correct answers to the
exact string Moodle expects. Every quiz in the repo keys at most four correct answers, so
the table covers the corpus with room to spare; a fifth beyond it raises rather than
guessing.

Multi-answer marking follows Moodle's own convention: each correct option is worth
100/n, each incorrect option -100/n. Selecting everything therefore scores nothing, which
is the behaviour a "select all that apply" question needs to be worth asking.
"""
import html
import re

# Moodle's permitted fractions, as the exact decimal strings its own XML export writes.
# From question_bank::fraction_options(); anything else is rejected under matchgrades
# 'error'.
GRADE_FOR = {
    1: "100",
    2: "50",
    3: "33.33333",
    4: "25",
    5: "20",
    6: "16.66667",
    7: "14.28571",
    8: "12.5",
}

CDATA_SPLIT_RE = re.compile(r"\]\]>")


def cdata(text):
    """Wrap text in CDATA, splitting any literal ]]> so it cannot close the section early."""
    safe = CDATA_SPLIT_RE.sub("]]]]><![CDATA[>", text or "")
    return "<![CDATA[%s]]>" % safe


def _el(tag, text, fmt="html", indent=6):
    pad = " " * indent
    attr = ' format="%s"' % fmt if fmt else ""
    return ('%s<%s%s>\n%s  <text>%s</text>\n%s</%s>\n'
            % (pad, tag, attr, pad, cdata(text), pad, tag))


def question_xml(q, category_hint=""):
    """One parsed question (see scripts/quiz_parse.py) as a Moodle <question> element."""
    correct = [a for a in q["answers"] if a["correct"]]
    n = len(correct)
    if n == 0:
        raise ValueError("question %s has no correct answer" % q.get("name"))
    if n not in GRADE_FOR:
        raise ValueError("question %s keys %d correct answers; Moodle has no exact "
                         "fraction for that. Add it to GRADE_FOR only if Moodle accepts "
                         "the value." % (q.get("name"), n))
    grade = GRADE_FOR[n]
    single = "true" if q["single"] else "false"

    out = ['    <question type="multichoice">\n']
    out.append(_el("name", q["name"], fmt=None))
    out.append(_el("questiontext", q["text_html"]))
    if q.get("feedback_html"):
        out.append(_el("generalfeedback", q["feedback_html"]))
    out.append("      <defaultgrade>1</defaultgrade>\n")
    out.append("      <penalty>0</penalty>\n")
    out.append("      <hidden>0</hidden>\n")
    out.append("      <single>%s</single>\n" % single)
    # Shuffling is safe because nothing in this corpus says "all of the above" or refers
    # to an option by letter -- quiz_parse.py reads the letters, the learner never needs
    # them. It also makes the answer key useless to anyone who saw a previous attempt.
    out.append("      <shuffleanswers>true</shuffleanswers>\n")
    out.append("      <answernumbering>abc</answernumbering>\n")
    if category_hint:
        out.append("      <idnumber>%s</idnumber>\n" % html.escape(category_hint))

    for a in q["answers"]:
        if a["correct"]:
            fraction = grade
        elif q["single"]:
            fraction = "0"
        else:
            # Negative marking, so selecting every option scores nothing.
            fraction = "-" + grade
        out.append('      <answer fraction="%s" format="html">\n' % fraction)
        out.append("        <text>%s</text>\n" % cdata(a["text_html"]))
        out.append('        <feedback format="html">\n          <text></text>\n'
                   "        </feedback>\n")
        out.append("      </answer>\n")

    out.append("    </question>\n")
    return "".join(out)


def quiz_xml(quiz):
    """A whole parsed quiz as a Moodle XML document ready for import_questions.

    No <question type="category"> element is emitted: local_ltuse imports with
    catfromfile=false so that the destination is decided by the caller, never by the
    file. A payload must not be able to redirect its own questions somewhere else.
    """
    parts = ['<?xml version="1.0" encoding="UTF-8"?>\n', "<quiz>\n"]
    for q in quiz["questions"]:
        parts.append(question_xml(q, q["idnumber"]))
    parts.append("</quiz>\n")
    return "".join(parts)


if __name__ == "__main__":
    import argparse
    import json
    import pathlib
    import sys

    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")   # these courses are full of en dashes

    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--manifest", required=True, help="a payload manifest.json")
    ap.add_argument("--index", type=int, default=0, help="which quiz in it")
    args = ap.parse_args()

    manifest = json.loads(pathlib.Path(args.manifest).read_text(encoding="utf-8"))
    if not manifest["quizzes"]:
        sys.exit("that payload has no quizzes")
    sys.stdout.write(quiz_xml(manifest["quizzes"][args.index]))
