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


def emit(event, context):
    """Print an additionalContext hook result for `event`."""
    if not context:
        return
    print(json.dumps({
        "hookSpecificOutput": {
            "hookEventName": event,
            "additionalContext": context,
        },
        "suppressOutput": True,
    }))


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
        else:
            lines.append("No courses are in the pipeline yet -- see ROADMAP.md or run "
                         "the coverage-strategist agent to pick one.")

    emit("SessionStart", "\n".join(lines))


def cmd_prompt_banner():
    branch = current_branch()
    slug = course_from_branch(branch)
    if slug:
        try:
            info = brief_for(slug)
            head = ("Active course: " + slug + " (branch " + branch + ") -- stage "
                    + info["stage_key"] + ": " + info["stage_name"]
                    + "\nSanctioned next step: " + info["next_action"]
                    + "\nStage how-to: " + info["stage_doc"])
        except Exception:
            head = "Active course branch: " + str(branch)
    else:
        head = ("No course branch is checked out (on '" + str(branch) + "'). Establish "
                "which ONE course this is for and run /work-on <slug> before editing "
                "anything under modules/.")
    emit("UserPromptSubmit", head + "\n\n" + PROTOCOL)


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


COMMANDS = {
    "session-start": cmd_session_start,
    "prompt-banner": cmd_prompt_banner,
    "guard-modules": cmd_guard_modules,
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
