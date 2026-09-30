#!/usr/bin/env python3
r"""Parse a course quiz markdown file into structured questions.

The repo's quizzes are written for humans to read raw (INTENT.md: "Content is markdown a
human can read raw"), so this reads the shape authors actually write rather than asking
them to adopt a machine format. It is the input to the Moodle publisher, which turns the
result into Moodle XML for local_ltuse_import_questions.

THE SHAPE, as it occurs across the corpus:

    ### Section 1: Components (Questions 1-3)      <- optional grouping, any heading level
    **Question 1:** Which component ...            <- stem, may wrap over lines
    - A) The RAM modules                           <- options, may wrap, letters A..Z
    - B) The cooling system
    ...
    ## Answer key                                  <- canonical marker (see disclosure.py)

    1. B | 2. C | 3. A,D | 4. A, B *(select all)*  <- pipes may be escaped: \|

    **Rationales**                                 <- optional, per question
    1. B - Lesson 1: the check needs both tabs.

Real variance this must handle, all of it present in modules/ today:

  * multi-answer keyed compactly (`6. C,E`) or spaced with a note (`1. A, B *(select all)*`)
  * pipes escaped as `\|` because the line can sit in table context
  * True/False written as a two-option multiple choice (`- A) True`)
  * a type hint in the stem: `**Question 1 (select all that apply):**`
  * option letters past D (one quiz keys `16. E`)
  * TWO quizzes in one file, each with its own qualified `## Answer key (Section N)`

That last one sets the association rule: **questions belong to the answer key that
follows them.** It falls out of how the files are written and needs no heading analysis.

STRICT, NOT LENIENT. Every question must be keyed, every key entry must have a question,
and every keyed letter must exist among that question's options. Anything else raises.
A guessed answer key is worse than no quiz -- it would be marked as correct in front of a
learner who has no way to check it.

Usage:
  python scripts/quiz_parse.py --check-all            # every quiz in modules/; CI gate
  python scripts/quiz_parse.py --course <slug>
  python scripts/quiz_parse.py --file <path> [--json]
"""
import argparse
import json
import pathlib
import re
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from disclosure import CANONICAL_KEY_RE  # noqa: E402
from course_stage import NOT_A_COURSE  # noqa: E402

REPO = pathlib.Path(__file__).resolve().parent.parent
MODULES = REPO / "modules"

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

QUESTION_RE = re.compile(r"^\*\*Question\s+(\d+)\s*(\(([^)]*)\))?\s*:?\*\*:?\s*(.*)$")
OPTION_RE = re.compile(r"^-\s+([A-Za-z])\)\s*(.*)$")
HEADING_RE = re.compile(r"^\s{0,3}(#{1,6})\s+(.*)$")
SECTION_RE = re.compile(r"^\s*Section\b[^:]*:?\s*(.*?)\s*(\(Questions[^)]*\))?\s*$", re.I)
THRESHOLD_RE = re.compile(r"(\d{1,3})\s*%")

# One entry of the compact key line: "12. C", "6. C,E", "1. A, B *(select all - ...)*".
KEY_ENTRY_RE = re.compile(
    r"^\s*(\d+)\.\s*([A-Za-z](?:\s*,\s*[A-Za-z])*)\s*(?:\*\(([^)]*)\)\*)?\s*$")
# A rationale line: "3. B (False) - Lesson 1 uses the whole NT once, deliberately, ..."
RATIONALE_RE = re.compile(
    r"^(\d+)\.\s*([A-Za-z](?:\s*,\s*[A-Za-z])*)\s*(?:\([^)]*\))?\s*[—–-]\s+(.+)$")
# The compact key line as a whole: starts "N. <letter>" and carries pipe separators.
# Deliberately looser than check_course_package.py's ANSWER_KEY_RE, which requires each
# entry to be a single non-space run and so cannot match a spaced multi-answer such as
# "1. A, B, C *(select all)*" -- a form two backfilled quizzes use. Those courses have no
# 00-design.md, so that check never ran on them and the gap went unnoticed. Detect the
# line here, then let KEY_ENTRY_RE do the strict reading of each entry.
KEY_LINE_RE = re.compile(r"^\s*\d+\.\s*[A-Za-z]\b.*\|")

MULTI_HINTS = ("select all", "choose all", "all that apply")


class QuizError(Exception):
    """A quiz this parser will not guess at. Always names the file and the question."""


def _norm(s):
    return re.sub(r"\s+", " ", s).strip()


def _split_key_line(line):
    r"""The compact key line into its entries. Pipes may be escaped as \|."""
    return [p for p in re.split(r"\s*\\?\|\s*", line.strip()) if p.strip()]


def parse_quiz_file(path):
    """One markdown file -> a list of quizzes. Raises QuizError on anything ambiguous."""
    path = pathlib.Path(path)
    text = path.read_text(encoding="utf-8", errors="replace")
    name = path.name
    lines = text.split("\n")

    quizzes, pending = [], []          # pending: questions not yet claimed by a key
    section, title, threshold = None, None, None
    state = {"q": None, "opt": None}
    i = 0

    def close_question():
        q = state["q"]
        state["q"], state["opt"] = None, None
        if q is None:
            return
        q["text"] = _norm(q["text"])
        for o in q["options"]:
            o["text"] = _norm(o["text"])
        if len(q["options"]) < 2:
            raise QuizError("%s: question %s has %d option(s); at least 2 are needed"
                            % (name, q["number"], len(q["options"])))
        pending.append(q)

    while i < len(lines):
        line = lines[i]

        # --- an answer key claims every question since the last one --------------------
        if CANONICAL_KEY_RE.match(line):
            close_question()
            block, i = [], i + 1
            while i < len(lines):
                h = HEADING_RE.match(lines[i])
                if h and len(h.group(1)) <= 2:
                    break
                block.append(lines[i])
                i += 1
            if not pending:
                raise QuizError("%s: '%s' has no questions before it"
                                % (name, line.strip()))
            quizzes.append(_build_quiz(name, line, block, pending, title, threshold))
            pending = []
            continue

        h = HEADING_RE.match(line)
        if h:
            close_question()
            heading = _norm(h.group(2))
            if len(h.group(1)) == 1 and title is None:
                title = heading
            m = SECTION_RE.match(heading)
            section = (_norm(m.group(1)) or heading) if m else None
            i += 1
            continue

        mq = QUESTION_RE.match(line)
        if mq:
            close_question()
            state["q"] = {"number": int(mq.group(1)), "qualifier": _norm(mq.group(3) or ""),
                          "text": mq.group(4), "options": [], "section": section}
            i += 1
            continue

        if state["q"] is not None:
            mo = OPTION_RE.match(line)
            if mo:
                state["opt"] = {"letter": mo.group(1).upper(), "text": mo.group(2),
                                "correct": False}
                state["q"]["options"].append(state["opt"])
                i += 1
                continue
            if line.strip():
                # A wrapped continuation of whichever part we are inside.
                if state["opt"] is not None:
                    state["opt"]["text"] += " " + line.strip()
                elif not state["q"]["options"]:
                    state["q"]["text"] += " " + line.strip()
            elif state["q"]["options"]:
                close_question()          # a blank line after the options ends it
            i += 1
            continue

        if threshold is None:
            mt = THRESHOLD_RE.search(line)
            if mt:
                threshold = int(mt.group(1))
        i += 1

    close_question()
    if pending:
        raise QuizError("%s: %d question(s) after the last '## Answer key' -- every "
                        "question must be keyed" % (name, len(pending)))
    if not quizzes:
        raise QuizError("%s: no '## Answer key' block found" % name)
    return quizzes


def _build_quiz(name, marker, block, questions, title, threshold):
    """Match one key block to the questions it follows."""
    key, feedback = {}, {}

    for raw in block:
        line = raw.strip()
        if not line:
            continue
        if KEY_LINE_RE.match(line):
            for entry in _split_key_line(line):
                m = KEY_ENTRY_RE.match(entry)
                if not m:
                    raise QuizError("%s: cannot read answer-key entry %r" % (name, entry))
                n = int(m.group(1))
                if n in key:
                    raise QuizError("%s: question %d is keyed twice" % (name, n))
                key[n] = [c.strip().upper() for c in m.group(2).split(",")]
                if m.group(3):
                    feedback[n] = _norm(m.group(3))
            continue
        mr = RATIONALE_RE.match(line)
        if mr:
            feedback[int(mr.group(1))] = _norm(mr.group(3))

    if not key:
        raise QuizError("%s: '%s' has no readable key line (expected '1. B | 2. C ...')"
                        % (name, marker.strip()))

    numbers = [q["number"] for q in questions]
    if sorted(key) != sorted(numbers):
        missing = sorted(set(numbers) - set(key))
        extra = sorted(set(key) - set(numbers))
        raise QuizError("%s: '%s' keys %s but the questions are %s%s%s"
                        % (name, marker.strip(), sorted(key), numbers,
                           "; unkeyed: %s" % missing if missing else "",
                           "; keyed but absent: %s" % extra if extra else ""))

    out = []
    for qn in questions:
        letters = key[qn["number"]]
        have = {o["letter"] for o in qn["options"]}
        unknown = [c for c in letters if c not in have]
        if unknown:
            raise QuizError("%s: question %d is keyed %s but its options are %s"
                            % (name, qn["number"], letters, sorted(have)))
        for o in qn["options"]:
            o["correct"] = o["letter"] in letters
        hint = qn["qualifier"].lower()
        out.append({
            "number": qn["number"],
            "text": qn["text"],
            "section": qn["section"],
            "qualifier": qn["qualifier"],
            # Moodle's `single` flag. Trust the key over the prose hint: a stem reading
            # "select all that apply" with one keyed letter is still a one-answer question.
            "single": len(letters) == 1,
            "multi_hinted": any(h in hint for h in MULTI_HINTS),
            "options": qn["options"],
            "feedback": feedback.get(qn["number"], ""),
        })

    return {
        "source": name,
        "title": title or name,
        "qualifier": marker.strip()[len("## Answer key"):].strip(),
        "threshold_pct": threshold,
        "questions": out,
    }


def quiz_files(folder):
    """Every quiz markdown file in a course folder."""
    return sorted(p for p in folder.glob("*.md") if "quiz" in p.name.lower())


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    g = ap.add_mutually_exclusive_group(required=True)
    g.add_argument("--check-all", action="store_true",
                   help="parse every quiz under modules/ (CI gate)")
    g.add_argument("--course", help="course slug (folder name under modules/)")
    g.add_argument("--file", help="one quiz markdown file")
    ap.add_argument("--json", action="store_true", help="emit the parsed structure")
    args = ap.parse_args()

    if args.file:
        targets = [pathlib.Path(args.file)]
    elif args.course:
        folder = MODULES / args.course
        if not folder.is_dir():
            sys.exit("no such course: %s" % args.course)
        targets = quiz_files(folder)
    else:
        targets = [p for d in sorted(MODULES.iterdir())
                   if d.is_dir() and d.name not in NOT_A_COURSE
                   for p in quiz_files(d)]

    results, failures = [], []
    for p in targets:
        try:
            for quiz in parse_quiz_file(p):
                results.append(quiz)
                if not args.json:
                    multi = sum(1 for q in quiz["questions"] if not q["single"])
                    fb = sum(1 for q in quiz["questions"] if q["feedback"])
                    label = p.relative_to(MODULES).as_posix()
                    if quiz["qualifier"]:
                        label += " " + quiz["qualifier"]
                    print("  %-56s %2d questions%s%s%s"
                          % (label, len(quiz["questions"]),
                             ", %d multi-answer" % multi if multi else "",
                             ", %d with feedback" % fb if fb else "",
                             ", pass %d%%" % quiz["threshold_pct"]
                             if quiz["threshold_pct"] else ""))
        except QuizError as e:
            failures.append(str(e))
            print("  ERROR %s" % e)

    if args.json:
        print(json.dumps(results, indent=2, ensure_ascii=False))
        return 0

    print("\n%d quiz(zes) in %d file(s): %d failed."
          % (len(results), len(targets), len(failures)))
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
