#!/usr/bin/env python3
"""Claude Code hook entry points for the LTC course production pipeline.

Wired up in .claude/settings.json. Each subcommand reads the hook payload on stdin
and prints hook JSON on stdout:

  session-start     SessionStart      -- orient the session: the course menu + protocol
  prompt-banner     UserPromptSubmit  -- restate course/stage/protocol on every prompt
  guard-modules     PreToolUse        -- refuse modules/ edits made on main
  statusline        statusLine        -- one line: branch, course, stage

EVERY subcommand fails open. If anything raises -- no git, no python deps, a
half-written course folder -- it prints nothing and exits 0, so a broken hook can
never be the thing that stops a contributor working. Never make these blocking on
their own correctness.

Stage detection is not duplicated here; it comes from course_stage.py.
"""
import datetime
import json
import os
import sys
import pathlib

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))

try:
    import course_stage as cs
except Exception:  # pragma: no cover - fail open
    cs = None

# Branch prefixes that legitimately carry modules/ changes.
WORK_PREFIXES = ("course/", "backfill/")

PROTOCOL = """\
LTC session protocol (this repo builds one course at a time):
1. ONE course per session. Before any course work, confirm which single course
   this is for, then run /work-on <slug> -- it puts you on that course's branch.
2. Never edit modules/ while on main. /work-on handles the branch for the user;
   they should not have to know git.
3. Do the CURRENT stage only (see below). The stages are gates: no drafting
   before the design is approved, no publishing before the pilot.
4. Route authoring through that stage's agent (course-designer, module-author,
   quiz-writer, video-script-writer, alignment-reviewer) rather than free-typing
   content, and follow process/stages/<NN>-*.md for the stage's how-to.
5. COVERAGE.md is generated -- never hand-edit it; run scripts/gen_coverage.py.
   Competency names must match competencies.yaml verbatim or CI fails."""


def emit(event, context, system_message=None):
    """Print a hook result for `event`.

    Two audiences, two channels -- keep them straight:

    * `context` -> `additionalContext`. MODEL-facing. Injected into Claude's context
      and invisible on screen. Cheap to repeat.
    * `system_message` -> `systemMessage`. HUMAN-facing, rendered in the UI. This is
      the only way a contributor actually sees any of this. Use it sparingly:
      anything printed on every single turn stops being read.

    `suppressOutput` stays on throughout -- it hides this JSON itself from the
    transcript. It does not hide `systemMessage`, which has its own display path.
    """
    if not context and not system_message:
        return
    out = {"suppressOutput": True}
    if context:
        out["hookSpecificOutput"] = {
            "hookEventName": event,
            "additionalContext": context,
        }
    if system_message:
        out["systemMessage"] = system_message
    print(json.dumps(out))


def now_path():
    return cs.REPO / ".claude" / "NOW.md"


def write_now_file(slug=None):
    """Rewrite .claude/NOW.md -- the tab contributors keep pinned in VS Code.

    The status line is a terminal feature and does not show in the VS Code sidebar,
    so this file is the always-visible "where am I" indicator. Gitignored: it is
    per-person session state, not repo content.
    """
    stamp = datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
    lines = []

    if slug:
        info = brief_for(slug)
        gs = info["git"]
        lines += [
            "# Now — " + slug,
            "",
            "| | |",
            "| --- | --- |",
            "| **Course** | `" + slug + "` |",
            "| **Branch** | `" + str(gs.get("current_branch")) + "` |",
            "| **Stage** | " + info["stage_key"] + " of 8 — " + info["stage_name"] + " |",
            "",
            "## ▶ Do this next",
            "",
            info["next_action"],
            "",
            "How-to: [`" + info["stage_doc"] + "`](../" + info["stage_doc"] + ")",
            "",
        ]
        if info["done"]:
            lines += ["## ✅ Already done", ""]
            lines += ["- " + d for d in info["done"]]
            lines += [""]
        if gs.get("dirty_modules"):
            lines += ["> **Uncommitted changes** exist under `modules/`.", ""]
        if info["notes"]:
            lines += ["## Notes", ""]
            lines += ["- " + n for n in info["notes"]]
            lines += [""]
    else:
        lines += [
            "# Now — no course selected",
            "",
            "Ask Claude for a course, or run **`/work-on <slug>`** to start. This repo "
            "builds **one course at a time**.",
            "",
        ]
        rows = [cs.stage_for(f, use_gh=False) for f in cs.course_folders()]
        if rows:
            lines += ["| Course | Stage | Needs next |", "| --- | --- | --- |"]
            for r in sorted(rows, key=lambda r: (-r["stage"], r["slug"])):
                lines.append("| `" + r["slug"] + "` | " + r["stage_key"] + " | "
                             + r["stage_name"] + " |")
            lines += [""]
        else:
            lines += ["No courses are in the pipeline yet — see `ROADMAP.md`.", ""]

    lines += [
        "---",
        "",
        "## The five rules",
        "",
        "1. **One course per session.** `/work-on <slug>` puts you on its branch.",
        "2. **Never edit `modules/` on `main`.** Claude will refuse; `/work-on` fixes it.",
        "3. **Do the current stage only.** The eight stages are gates.",
        "4. **Use the stage's agent** and its `process/stages/<NN>-*.md` how-to.",
        "5. **Never hand-edit `COVERAGE.md`**; competency names must match "
        "`competencies.yaml` verbatim.",
        "",
        "_Auto-generated " + stamp + " by `scripts/session_hooks.py`. Not tracked by "
        "git. Keep this tab pinned._",
        "",
    ]

    p = now_path()
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text("\n".join(lines), encoding="utf-8")


def read_payload():
    try:
        raw = sys.stdin.read()
    except Exception:
        return {}
    if not raw.strip():
        return {}
    try:
        return json.loads(raw)
    except Exception:
        return {}


def current_branch():
    return cs.git("rev-parse", "--abbrev-ref", "HEAD") if cs else None


def course_from_branch(branch):
    """The course slug a work branch refers to, or None if this isn't a work branch."""
    if not branch or not branch.startswith(WORK_PREFIXES):
        return None
    return cs.resolve(branch)


def brief_for(slug, probe_remote=False):
    """One-line-per-fact summary of where `slug` stands. Cheap: no network, no gh."""
    info = cs.stage_for(cs.MODULES / slug, use_gh=False)
    info["git"] = cs.git_state(slug, info["branch"], probe_remote=probe_remote)
    return info


def cmd_session_start():
    branch = current_branch()
    slug = course_from_branch(branch)
    lines = [PROTOCOL, ""]

    if slug:
        info = brief_for(slug)
        lines += [
            "This session is resuming on branch " + branch + ", which is course '"
            + slug + "'.",
            "  Current stage: " + info["stage_key"] + " -- " + info["stage_name"],
            "  Next action:   " + info["next_action"],
            "  Stage how-to:  " + info["stage_doc"],
        ]
        if info["git"].get("dirty_modules"):
            lines.append("  Uncommitted changes exist under modules/ -- mention them to "
                         "the user before starting new work.")
        for n in info["notes"]:
            lines.append("  Note: " + n)
        lines += ["", "Confirm with the user that this is still the course they want "
                      "before working. If not, run /work-on <slug> to switch."]
        seen = [
            "📦 " + slug + "  ·  stage " + info["stage_key"] + "/8  ·  "
            + info["stage_name"],
            "▶ Next: " + info["next_action"],
            "",
            "Full detail in .claude/NOW.md — keep that tab pinned.",
        ]
    else:
        rows = [cs.stage_for(f, use_gh=False) for f in cs.course_folders()]
        lines.append("No course branch is checked out (currently on '"
                     + str(branch) + "').")
        lines.append("Your FIRST job this session is to establish which single course "
                     "the user is working on, then run /work-on <slug>. Do not edit "
                     "modules/ until that has happened.")
        if rows:
            lines.append("")
            lines.append("Courses currently in the pipeline:")
            width = max(len(r["slug"]) for r in rows)
            for r in sorted(rows, key=lambda r: (-r["stage"], r["slug"])):
                lines.append("  " + r["slug"].ljust(width) + "  stage "
                             + r["stage_key"].ljust(4) + "  " + r["stage_name"])
            lines.append("")
            lines.append("Ask which of these they want (or whether it is a new course). "
                         "Do not guess, and do not offer to work several at once.")
            seen = ["No course selected. Pick one, then run /work-on <slug>:", ""]
            for r in sorted(rows, key=lambda r: (-r["stage"], r["slug"])):
                seen.append("  " + r["slug"].ljust(width) + "  stage "
                            + r["stage_key"].ljust(4) + "  " + r["stage_name"])
            seen += ["", "Full detail in .claude/NOW.md — keep that tab pinned."]
        else:
            lines.append("No courses are in the pipeline yet -- see ROADMAP.md or run "
                         "the coverage-strategist agent to pick one.")
            seen = ["No courses are in the pipeline yet — see ROADMAP.md."]

    write_now_file(slug)
    emit("SessionStart", "\n".join(lines), system_message="\n".join(seen))


def cmd_prompt_banner():
    branch = current_branch()
    slug = course_from_branch(branch)
    seen = None
    if slug:
        try:
            info = brief_for(slug)
            head = ("Active course: " + slug + " (branch " + branch + ") -- stage "
                    + info["stage_key"] + ": " + info["stage_name"]
                    + "\nSanctioned next step: " + info["next_action"]
                    + "\nStage how-to: " + info["stage_doc"])
        except Exception:
            head = "Active course branch: " + str(branch)
        # Deliberately no systemMessage here. A banner on every turn is noise the
        # reader learns to skip; .claude/NOW.md carries the same facts, persistently.
    else:
        head = ("No course branch is checked out (on '" + str(branch) + "'). Establish "
                "which ONE course this is for and run /work-on <slug> before editing "
                "anything under modules/.")
        # The one case worth interrupting for: they are about to work with no course
        # selected, which is how content lands on main.
        seen = ("⚠ No course selected (on '" + str(branch) + "') — run /work-on <slug> "
                "before editing anything under modules/.")
    emit("UserPromptSubmit", head + "\n\n" + PROTOCOL, system_message=seen)


def cmd_guard_modules():
    """Refuse a modules/ edit that would land on main; question a cross-course one."""
    payload = read_payload()
    tool_input = payload.get("tool_input") or {}
    path = tool_input.get("file_path") or tool_input.get("path") or ""
    if not path:
        return

    try:
        rel = pathlib.Path(path).resolve().relative_to(cs.REPO)
    except Exception:
        return  # outside the repo, or unresolvable -- not ours to police
    parts = rel.as_posix().split("/")
    if len(parts) < 2 or parts[0] != "modules":
        return  # only modules/ content is branch-sensitive
    target = parts[1]
    if target in cs.NOT_A_COURSE:
        return

    branch = current_branch()
    if branch is None:
        return  # git unavailable -- fail open

    def deny(reason):
        print(json.dumps({"hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "deny",
            "permissionDecisionReason": reason,
        }}))

    def ask(reason):
        print(json.dumps({"hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "ask",
            "permissionDecisionReason": reason,
        }}))

    if not branch.startswith(WORK_PREFIXES):
        deny("Course content must not be edited on '" + branch + "'. Run "
             "/work-on " + target + " first -- it puts this session on the course's "
             "own branch (" + cs.branch_for(target) + ") and carries any work in "
             "progress across. This keeps one teammate's drafting out of everyone "
             "else's pull.")
        return

    on = course_from_branch(branch)
    if on and on != target:
        ask("This session is on branch " + branch + " (course '" + on + "'), but this "
            "edit targets modules/" + target + ". Mixing two courses on one branch puts "
            "unrelated changes in the same PR. Approve only if this cross-course edit "
            "is deliberate; otherwise finish this course first, or run /work-on "
            + target + ".")


def cmd_statusline():
    branch = current_branch()
    slug = course_from_branch(branch)
    if not slug:
        print("no course - run /work-on <slug>" + ("  [" + branch + "]" if branch else ""))
        return
    info = brief_for(slug)
    print(slug + " - stage " + info["stage_key"] + "/8 - " + info["stage_name"]
          + "  [" + str(branch) + "]")


def cmd_refresh_now():
    """Rewrite .claude/NOW.md after a write, so the pinned tab tracks reality.

    Creating a quiz or video script advances the stage; this is what makes that
    progress visible instead of merely true. Prints nothing.
    """
    write_now_file(course_from_branch(current_branch()))


COMMANDS = {
    "session-start": cmd_session_start,
    "prompt-banner": cmd_prompt_banner,
    "guard-modules": cmd_guard_modules,
    "refresh-now": cmd_refresh_now,
    "statusline": cmd_statusline,
}


def main():
    if len(sys.argv) != 2 or sys.argv[1] not in COMMANDS:
        print("usage: session_hooks.py {" + "|".join(COMMANDS) + "}", file=sys.stderr)
        return 2
    if cs is None:
        return 0  # fail open: course_stage.py unavailable
    try:
        COMMANDS[sys.argv[1]]()
    except Exception:
        if os.environ.get("LTC_HOOK_DEBUG"):
            raise
        return 0  # fail open, always
    return 0


if __name__ == "__main__":
    sys.exit(main())
