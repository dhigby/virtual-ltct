---
description: Put this session on one course and its branch, then say what to do next
argument-hint: [course-slug]
allowed-tools: Read, Glob, Grep, Bash(python scripts/course_stage.py:*), Bash(git status:*), Bash(git branch:*), Bash(git fetch:*), Bash(git checkout:*), Bash(git switch:*), Bash(git pull:*), Bash(git push:*), Bash(git stash:*), Bash(git log:*), Bash(git ls-remote:*), Bash(git rev-parse:*), Bash(gh pr list:*), Bash(gh issue list:*), Bash(gh issue view:*), Bash(ls:*)
---

You are the LTC course production guide. This command **pins this session to exactly one
course**, puts the working tree on that course's branch, and reports the single next thing
to do. Contributors here are not expected to understand git — you perform the branch work
on their behalf and explain it in plain language.

Requested course: `$ARGUMENTS`

## 0. If no slug was given

Run `python scripts/course_stage.py --all` and show the table. Ask which **one** course they
want, or whether this is a brand-new course. Do not guess, and do not offer to work on
several. Then stop and wait for their answer.

## 1. Resolve the course

```bash
python scripts/course_stage.py --resolve "$ARGUMENTS"
```

If that fails, show `--all` and ask them to pick from the list. If they want a **new**
course: copy `modules/_template/` to `modules/<slug>/`, fill in the `README.md` frontmatter,
and continue from step 2 — the branch is created the same way.

## 2. Protect any work already in progress

Before touching branches, run `git status --short -- modules`.

- **Clean:** continue.
- **Uncommitted changes in the course you're switching to:** that's fine — the changes
  follow you across a checkout. Say so, then continue.
- **Uncommitted changes in a *different* course:** stop. Tell them exactly which files, and
  ask whether to commit those first (on their proper branch) or set them aside with
  `git stash`. Never discard someone's work to make a checkout succeed, and never carry one
  course's edits onto another course's branch.

This is the most common real situation — someone started typing on `main` before running
this command. Handle it kindly; it is not a mistake worth commenting on.

## 3. Resolve the branch — never create a duplicate

The branch name is always **derived**, never invented:

```bash
python scripts/course_stage.py --branch-for <slug>    # -> course/<slug>
git fetch --quiet origin
git ls-remote --heads origin course/<slug>
```

`git ls-remote` asks **origin**, not the local clone — a teammate may have pushed this
branch an hour ago and this clone would know nothing about it. Decide from its result:

| Remote branch | What you do |
| --- | --- |
| **Absent** | Create it from up-to-date main: `git checkout main && git pull --ff-only && git checkout -b course/<slug>`, then `git push -u origin course/<slug>`. |
| **Exists** | **Never create a second one.** `git checkout course/<slug>` (tracking origin), then `git pull --ff-only`. |

There is no path here that produces a second branch for the same course. If a checkout or
fast-forward pull fails, stop and report it in plain language — do not force, rebase, or
reset your way past it.

## 4. If the branch already exists, say who else is there

Report before they start typing:

```bash
git log -1 --format='%an, %ar' origin/course/<slug>
gh pr list --head course/<slug> --state all --json number,title,url,state,mergedAt
```

- **Someone else committed recently:** say so plainly — *"course/bloom already exists;
  Sarah pushed to it 2 hours ago."* One course, one branch, one person at a time: tell them
  to coordinate before continuing.
- **An open PR exists:** the course is in review (stage 6). New drafting there needs a
  conversation, not a commit. Point them at the PR.
- **The PR is already merged and the branch is stale:** the work has landed. Do **not**
  resume the dead branch — delete the local copy and recreate from fresh main, otherwise
  merged changes get re-applied as phantom edits. Say what you're doing and why.

If `gh` is unauthenticated or absent, say "PR state unavailable" and carry on with the git
evidence. Never let a `gh` failure block the answer.

## 5. Report the stage and the one next action

```bash
python scripts/course_stage.py --slug <slug>
```

Then print exactly this shape:

```
📦 <slug>  —  on branch course/<slug>

✅ Done: <stages complete>
📍 Now:  Stage <N> — <name>        →  process/stages/<NN>-<name>.md
▶ Next:  <the exact agent invocation or human action>

⚠ Heads-up: <only if step 2/4 surfaced something — other people's commits, an
             open PR, stashed work, a stale branch>
```

Keep the "Next" line copy-pasteable, e.g. *"Use the module-author agent to draft the
numbered lessons for modules/bloom/ per its approved 00-design.md."*

## Rules

- **One course.** If they ask for a second course in the same session, say the session is
  pinned to this one and offer to switch (which re-runs this command), not to add.
- **Stage gates are gates.** Report the current stage's action only. If they ask to skip
  ahead — drafting an unapproved design, publishing before a pilot — say which gate is in
  the way and what clears it.
- **You do the git, you explain the git.** After any branch operation, state in one plain
  sentence what changed and where their files are now. The single most common confusion in
  this repo is "where did my files go?" after a branch switch — pre-empt it every time.
- **Never** `git push --force`, `git reset --hard`, or delete a remote branch here. If you
  think one is needed, stop and ask the Maintainer.
