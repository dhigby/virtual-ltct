#!/usr/bin/env python3
"""Report where a course stands in the 8-stage production pipeline.

This is the ONE implementation of stage detection. `/next-step`, `/work-on`, the
session hooks and the status line all read it, so every contributor gets the same
answer for the same course. See process/PROCESS.md for the pipeline itself.

Repo evidence is the source of truth for stages 1-3 and 8 (files either exist or
they don't). Stages 4-7 leave no trace in the repo, so they are inferred from PR
state where available and otherwise reported as indeterminate -- the tracker issue
is authoritative for those.

Usage:
  python scripts/course_stage.py --slug <slug>        # one course, human-readable
  python scripts/course_stage.py --slug <slug> --json
  python scripts/course_stage.py --all                # the chooser menu
  python scripts/course_stage.py --all --json
  python scripts/course_stage.py --branch-for <slug>  # canonical branch name
  python scripts/course_stage.py --resolve <text>     # slug from a folder name/branch

Flags:
  --no-gh   skip the PR lookup via the gh CLI (offline, unauthenticated, or speed)
"""
import argparse
import json
import re
import subprocess
import sys
import pathlib

REPO = pathlib.Path(__file__).resolve().parent.parent
MODULES = REPO / "modules"

TIME_RE = re.compile(r"^\*\*Estimated time:\*\*\s*(\d+)\s*minutes", re.MULTILINE)
DESIGN_STATUS_RE = re.compile(r"^\|\s*\*\*Design status\*\*\s*\|(.+)\|", re.MULTILINE)
APPROVED_RE = re.compile(r"^Approved by (.+) on (\d{4}-\d{2}-\d{2})$")
CYPHER_RE = re.compile(r"^\s*cypher:\s*\S+", re.MULTILINE)

# The package skeleton, not a course. check_course_package.py excludes it for the
# same reason; so must anything that enumerates courses.
NOT_A_COURSE = {"_template"}


def branch_slug(slug):
    """Canonical branch-safe form of a course folder name.

    Folder names are usually already slugs, but a few legacy ones carry spaces and
    capitals (e.g. "Paratext 9 advanced support"). The branch name is always
    DERIVED, never invented -- that is what makes duplicate branches impossible.
    """
    s = re.sub(r"[^A-Za-z0-9]+", "-", slug.strip().lower())
    return s.strip("-")


def branch_for(slug):
    return "course/" + branch_slug(slug)


def is_lesson(name):
    """01-*.md, 02-*.md, ... but not the package's own non-lesson files."""
    if not re.match(r"^\d{2}-", name):
        return False
    if re.fullmatch(r"\d{2}-design\.md", name):
        return False
    return not name.endswith(("-scenario-bank.md", "-mentor-guide.md",
                              "-quiz.md", "-video-script.md"))


def git(*args):
    """Run a read-only git command; return stripped stdout, or None on failure."""
    try:
        out = subprocess.run(("git",) + args, cwd=REPO, capture_output=True,
                             text=True, timeout=15)
    except (OSError, subprocess.SubprocessError):
        return None
    if out.returncode != 0:
        return None
    return out.stdout.strip()


def open_pr_for(branch):
    """Return the PR dict for `branch` (open first, else most recent merged), or None.

    Best-effort: any failure of the gh CLI (missing, unauthenticated, offline)
    returns None rather than raising, so stage detection still works without it.
    """
    try:
        out = subprocess.run(
            ("gh", "pr", "list", "--head", branch, "--state", "all",
             "--json", "number,title,url,state,mergedAt", "--limit", "5"),
            cwd=REPO, capture_output=True, text=True, timeout=20)
    except (OSError, subprocess.SubprocessError):
        return None
    if out.returncode != 0:
        return None
    try:
        prs = json.loads(out.stdout or "[]")
    except json.JSONDecodeError:
        return None
    for pr in prs:
        if pr.get("state") == "OPEN":
            return pr
    for pr in prs:
        if pr.get("mergedAt"):
            return pr
    return None


def course_folders():
    """Every course opted into the pipeline (has a 00-design.md), template excluded."""
    out = []
    for f in sorted(MODULES.glob("*/")):
        if f.name in NOT_A_COURSE:
            continue
        if not (f / "00-design.md").exists():
            continue  # legacy course, not opted into the pipeline
        out.append(f)
    return out


def resolve(text):
    """Best-effort slug from a folder name, a branch name, or loose user text."""
    if not text:
        return None
    text = text.strip()
    candidates = [f.name for f in sorted(MODULES.glob("*/")) if f.name not in NOT_A_COURSE]
    if text in candidates:
        return text
    probe = text.split("/", 1)[-1]  # accept "course/bloom" as well as "bloom"
    by_branch = {branch_slug(c): c for c in candidates}
    key = branch_slug(probe)
    if key in by_branch:
        return by_branch[key]
    partial = [c for c in candidates if key and key in branch_slug(c)]
    if len(partial) == 1:
        return partial[0]
    return None


def git_state(slug, branch, probe_remote=True):
    """Local/remote branch presence, and where the working tree currently sits.

    probe_remote=True asks origin directly (`ls-remote`) -- authoritative, and the
    check that makes duplicate branches impossible, but it is a network round-trip.
    Pass False in hooks and the status line, where the locally cached remote ref is
    good enough and latency matters.
    """
    current = git("rev-parse", "--abbrev-ref", "HEAD")
    local = git("rev-parse", "--verify", "--quiet", "refs/heads/" + branch) is not None
    if probe_remote:
        remote = bool(git("ls-remote", "--heads", "origin", branch))
    else:
        remote = git("rev-parse", "--verify", "--quiet",
                     "refs/remotes/origin/" + branch) is not None
    state = {
        "current_branch": current,
        "course_branch": branch,
        "on_course_branch": current == branch,
        "local_exists": local,
        "remote_exists": remote,
        "last_commit": None,
    }
    if remote or local:
        ref = "origin/" + branch if remote else branch
        who = git("log", "-1", "--format=%an|%ar", ref)
        if who and "|" in who:
            author, when = who.split("|", 1)
            state["last_commit"] = {"author": author, "when": when}
    # Uncommitted work under modules/ is what strands people on the wrong branch.
    dirty = git("status", "--porcelain", "--", "modules")
    state["dirty_modules"] = bool(dirty)
    state["dirty_this_course"] = bool(dirty and ("modules/" + slug) in dirty)
    return state


def stage_for(folder, use_gh=True):
    """Determine the current stage of one course. Returns a dict (see module docstring)."""
    slug = folder.name
    files = {p.name: p for p in folder.glob("*.md")}
    notes = []
    done = []

    design = files.get("00-design.md")
    if not design:
        return {
            "slug": slug, "folder": "modules/" + slug, "branch": branch_for(slug),
            "stage": 1, "stage_key": "1", "stage_name": "Design",
            "stage_doc": "process/stages/01-design.md",
            "next_action": ("Use the course-designer agent to write modules/"
                            + slug + "/00-design.md."),
            "done": [], "design_status": None, "published": False,
            "notes": ["Not yet opted into the pipeline (no 00-design.md)."],
        }
    done.append("1. Design")

    design_text = design.read_text(encoding="utf-8")
    m = DESIGN_STATUS_RE.search(design_text)
    design_status = m.group(1).strip() if m else None
    approved = bool(design_status and APPROVED_RE.match(design_status))
    if design_status and "retro-fit" in design_status.lower():
        notes.append("Retro-fit/backfilled course -- grandfathered out of the full "
                     "package (see process/backfill.md).")

    readme = folder / "README.md"
    published = bool(readme.exists()
                     and CYPHER_RE.search(readme.read_text(encoding="utf-8")))

    def result(stage, key, name, doc, action):
        return {
            "slug": slug, "folder": "modules/" + slug, "branch": branch_for(slug),
            "stage": stage, "stage_key": key, "stage_name": name,
            "stage_doc": doc, "next_action": action, "done": done,
            "design_status": design_status, "published": published, "notes": notes,
        }

    # --- Stage 2: design approved by someone other than the author ---
    if not approved:
        return result(2, "2", "Design approval", "process/stages/02-approve.md",
                      "A Design Approver (not the Author) signs the design status line "
                      "in modules/" + slug + "/00-design.md, then merges the design PR.")
    done.append("2. Design approval")

    # --- Stage 3a-3d: the draft package ---
    lessons = [p for name, p in files.items() if is_lesson(name)]
    if not [p for p in lessons if TIME_RE.search(p.read_text(encoding="utf-8"))]:
        return result(3, "3a", "Draft -- lessons", "process/stages/03-draft.md",
                      "Use the module-author agent to draft the numbered lessons for "
                      "modules/" + slug + "/ per its approved 00-design.md.")
    done.append("3a. Lessons")

    has_bank = any(n.endswith("-scenario-bank.md") for n in files)
    has_mentor = any(n.endswith("-mentor-guide.md") for n in files)
    if not (has_bank and has_mentor):
        missing = " and ".join(x for x, ok in (("scenario bank", has_bank),
                                               ("mentor guide", has_mentor)) if not ok)
        return result(3, "3b", "Draft -- scenario bank + mentor guide",
                      "process/stages/03-draft.md",
                      "Use the module-author agent to write the " + missing
                      + " for modules/" + slug + "/.")
    done.append("3b. Scenario bank + mentor guide")

    if not any(n.endswith("-quiz.md") for n in files):
        return result(3, "3c", "Draft -- quiz", "process/stages/03-draft.md",
                      "Use the quiz-writer agent to write the quiz for modules/"
                      + slug + "/.")
    done.append("3c. Quiz")

    if not any(n.endswith("-video-script.md") for n in files):
        return result(3, "3d", "Draft -- video script", "process/stages/03-draft.md",
                      "Use the video-script-writer agent to write the video script for "
                      "modules/" + slug + "/.")
    done.append("3d. Video script")

    # --- Stage 8: published (the only late stage with a repo signal) ---
    if published:
        done.append("4-7. Alignment, SME, internal review, pilot")
        return result(8, "8", "Online (published)", "process/stages/08-publish.md",
                      "Nothing -- this course is Online. Its Cypher link is recorded in "
                      "modules/" + slug + "/README.md.")

    # --- Stages 4-7: no repo signal. PR state is the best available hint. ---
    pr = open_pr_for(branch_for(slug)) if use_gh else None
    if pr and pr.get("state") == "OPEN":
        notes.append("Open PR #" + str(pr["number"]) + ": " + pr["url"])
        return result(6, "6", "Internal review", "process/stages/06-internal-review.md",
                      "An Internal Reviewer (not the Author) reviews PR #"
                      + str(pr["number"]) + " and merges it.")

    notes.append("The full package is present. Stages 4-7 (alignment, SME fact-check, "
                 "internal review, pilot) leave no repo trace -- confirm on the tracker "
                 "issue.")
    return result(4, "4-7", "Alignment / SME / internal review / pilot",
                  "process/stages/04-alignment.md",
                  "Start with the alignment-reviewer agent (stage 4) if it hasn't run; "
                  "otherwise check the tracker issue for which of stages 4-7 is open.")


def print_one(info):
    print(info["slug"] + " -- stage " + info["stage_key"] + ": " + info["stage_name"])
    if info["done"]:
        print("  Done:   " + ", ".join(info["done"]))
    print("  Next:   " + info["next_action"])
    print("  How-to: " + info["stage_doc"])
    gs = info.get("git")
    if gs:
        if gs["on_course_branch"]:
            where = "checked out"
        elif gs["remote_exists"]:
            where = "exists on origin"
        elif gs["local_exists"]:
            where = "local only, not pushed"
        else:
            where = "does not exist yet"
        if gs["last_commit"]:
            where += "; last commit by " + gs["last_commit"]["author"] + " " \
                     + gs["last_commit"]["when"]
        print("  Branch: " + info["branch"] + " (" + where + ")")
    for n in info["notes"]:
        print("  Note:   " + n)


def main():
    ap = argparse.ArgumentParser(description="Where does a course stand in the pipeline?")
    g = ap.add_mutually_exclusive_group(required=True)
    g.add_argument("--slug")
    g.add_argument("--all", action="store_true")
    g.add_argument("--branch-for")
    g.add_argument("--resolve")
    ap.add_argument("--json", action="store_true")
    ap.add_argument("--no-gh", action="store_true", help="skip the gh PR lookup")
    args = ap.parse_args()

    if args.branch_for:
        print(branch_for(args.branch_for))
        return 0

    if args.resolve:
        slug = resolve(args.resolve)
        if not slug:
            print("No course matches " + repr(args.resolve), file=sys.stderr)
            return 1
        print(slug)
        return 0

    if args.all:
        rows = [stage_for(f, use_gh=False) for f in course_folders()]
        if args.json:
            print(json.dumps(rows, indent=2))
            return 0
        if not rows:
            print("No courses are in the pipeline yet (none has a 00-design.md).")
            print("Run the coverage-strategist agent or see ROADMAP.md to pick one.")
            return 0
        width = max(len(r["slug"]) for r in rows)
        print("COURSE".ljust(width) + "  STAGE  WHAT IT NEEDS NEXT")
        for r in sorted(rows, key=lambda r: (-r["stage"], r["slug"])):
            print(r["slug"].ljust(width) + "  " + r["stage_key"].ljust(5) + "  "
                  + r["stage_name"])
        return 0

    slug = resolve(args.slug)
    if not slug:
        print("No course folder matches " + repr(args.slug) + ". Options:", file=sys.stderr)
        for f in course_folders():
            print("  " + f.name, file=sys.stderr)
        return 1

    info = stage_for(MODULES / slug, use_gh=not args.no_gh)
    info["git"] = git_state(slug, info["branch"])
    if args.json:
        print(json.dumps(info, indent=2))
        return 0
    print_one(info)
    return 0


if __name__ == "__main__":
    sys.exit(main())
