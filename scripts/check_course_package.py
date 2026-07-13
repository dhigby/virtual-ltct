#!/usr/bin/env python3
"""Validate a course package's completeness and format.

A course "opts in" to the pipeline by having a `00-design.md`. Legacy courses (garbled
Notion imports, stubs) have none, so they are ignored entirely — this check never breaks CI
for them. See process/PROCESS.md for the pipeline this enforces.

Two severities:
  ERROR (exit 1) — a hard format violation in an opted-in course:
    * a lesson file or scenario bank missing `**Estimated time:** N minutes`, or N > 90
    * a lesson file missing one of the four Learning That Lasts phase headings
      (## Connect, ## Content, ## Challenge, ## Change), or having them out of order
    * a quiz file present but missing a pass threshold or a pipe-separated answer key
    * a malformed `**Design status**` line in 00-design.md

  Retro-fit / backfilled courses (a `00-design.md` whose design status is a retro-fit note)
  are faithful imports of already-delivered content and are grandfathered out of the 4Cs
  phase-heading requirement — see process/backfill.md. They are still checked for duration
  headers and quiz format.

  WARNING (exit 0) — package files not yet present. Mid-pipeline courses are legitimately
    incomplete; full-package presence is enforced by humans at internal review, surfaced by
    /next-step. So missing files are reported, not failed.

Usage:
  python scripts/check_course_package.py            # all opted-in courses
  python scripts/check_course_package.py --course <slug>
"""
import re
import sys
import pathlib

REPO = pathlib.Path(__file__).resolve().parent.parent
MODULES = REPO / "modules"

TIME_RE = re.compile(r"^\*\*Estimated time:\*\*\s*(\d+)\s*minutes", re.MULTILINE)
DESIGN_STATUS_RE = re.compile(r"^\|\s*\*\*Design status\*\*\s*\|(.+)\|", re.MULTILINE)
ANSWER_KEY_RE = re.compile(r"^\s*1\.\s*\S+(\s*\\?\|\s*\d+\.\s*\S+)+", re.MULTILINE)
THRESHOLD_RE = re.compile(r"\b\d{1,3}\s*%|\bto pass\b", re.IGNORECASE)
MAX_MINUTES = 90

# The Learning That Lasts four-phase lesson structure, as H2s in this order.
PHASES = ("Connect", "Content", "Challenge", "Change")
PHASE_RES = {p: re.compile(rf"^##\s+{p}\b", re.MULTILINE) for p in PHASES}


def is_lesson(name):
    # 01-*.md, 02-*.md, ... but not the package's own non-lesson files
    if not re.match(r"^\d{2}-", name):
        return False
    if re.fullmatch(r"\d{2}-design\.md", name):
        return False
    return not name.endswith(("-scenario-bank.md", "-mentor-guide.md",
                              "-quiz.md", "-video-script.md"))


def check_course(folder):
    """Return (errors, warnings) for one opted-in course folder."""
    errors, warnings = [], []
    slug = folder.name
    files = {p.name: p for p in folder.glob("*.md")}

    # --- 00-design.md status line ---
    retrofit = False
    design = files.get("00-design.md")
    if design:
        text = design.read_text(encoding="utf-8")
        m = DESIGN_STATUS_RE.search(text)
        if not m:
            errors.append(f"{slug}/00-design.md: no '**Design status**' line found")
        else:
            status = m.group(1).strip()
            # A retro-fit course records its faithful-import provenance in the status line
            # (e.g. "Draft (retro-fit from delivered content — pending approval)"). Such
            # courses are grandfathered out of the 4Cs phase-heading check below.
            retrofit = "retro-fit" in status.lower() or "retrofit" in status.lower()
            # Accept: "Draft", "Draft (<note>)", or "Approved by <name> on <YYYY-MM-DD>".
            if not re.fullmatch(r"Draft(\s*\(.+\))?", status) and not re.fullmatch(
                    r"Approved by .+ on \d{4}-\d{2}-\d{2}", status):
                errors.append(f"{slug}/00-design.md: malformed design status '{status}' "
                              f"(expected 'Draft', 'Draft (<note>)', or "
                              f"'Approved by <name> on <YYYY-MM-DD>')")

    # --- lessons + scenario bank: duration header ---
    timed = [p for name, p in files.items()
             if is_lesson(name) or name.endswith("-scenario-bank.md")]
    for p in sorted(timed):
        m = TIME_RE.search(p.read_text(encoding="utf-8"))
        if not m:
            errors.append(f"{slug}/{p.name}: missing '**Estimated time:** N minutes' header")
        elif int(m.group(1)) > MAX_MINUTES:
            errors.append(f"{slug}/{p.name}: {m.group(1)} minutes exceeds the {MAX_MINUTES}-minute cap")

    # --- lessons: Learning That Lasts phase headings, in order ---
    # Retro-fit / backfilled courses are faithful imports of delivered content and are
    # grandfathered out of the 4Cs structure (see process/backfill.md).
    for p in [] if retrofit else sorted(pp for name, pp in files.items() if is_lesson(name)):
        text = p.read_text(encoding="utf-8")
        positions = {}
        for phase in PHASES:
            m = PHASE_RES[phase].search(text)
            if m:
                positions[phase] = m.start()
        missing = [phase for phase in PHASES if phase not in positions]
        if missing:
            errors.append(f"{slug}/{p.name}: missing phase heading(s): "
                          + ", ".join(f"## {phase}" for phase in missing))
        elif list(positions) != sorted(positions, key=positions.get):
            found_order = sorted(positions, key=positions.get)
            errors.append(f"{slug}/{p.name}: phase headings out of order "
                          f"(found {' -> '.join(found_order)})")

    # --- quiz format (only if a quiz exists) ---
    quiz = next((p for name, p in files.items() if name.endswith("-quiz.md")), None)
    if quiz:
        qtext = quiz.read_text(encoding="utf-8")
        if not THRESHOLD_RE.search(qtext):
            errors.append(f"{slug}/{quiz.name}: no pass threshold stated in the body")
        if not ANSWER_KEY_RE.search(qtext):
            errors.append(f"{slug}/{quiz.name}: no pipe-separated answer key found")

    # --- completeness (warnings only) ---
    if not any(is_lesson(n) for n in files):
        warnings.append(f"{slug}: no numbered lesson files yet")
    for suffix, label in (("-scenario-bank.md", "scenario bank"),
                          ("-mentor-guide.md", "mentor guide"),
                          ("-quiz.md", "quiz"),
                          ("-video-script.md", "video script")):
        if not any(n.endswith(suffix) for n in files):
            warnings.append(f"{slug}: no {label} yet")

    return errors, warnings


def main():
    only = None
    if len(sys.argv) == 3 and sys.argv[1] == "--course":
        only = sys.argv[2]

    folders = []
    for f in sorted(MODULES.glob("*/")):
        if f.name == "_template":
            continue
        if not (f / "00-design.md").exists():
            continue  # not opted into the pipeline
        if only and f.name != only:
            continue
        folders.append(f)

    if only and not folders:
        target = MODULES / only
        if not target.exists():
            sys.exit(f"No such course: modules/{only}")
        print(f"modules/{only} has no 00-design.md — not opted into the pipeline; nothing to check.")
        return

    all_errors, all_warnings = [], []
    for f in folders:
        e, w = check_course(f)
        all_errors += e
        all_warnings += w

    for w in all_warnings:
        print("WARNING:", w)
    for e in all_errors:
        print("ERROR:", e)

    print(f"\nChecked {len(folders)} opted-in course(s): "
          f"{len(all_errors)} error(s), {len(all_warnings)} warning(s).")
    if all_errors:
        sys.exit(1)


if __name__ == "__main__":
    main()
