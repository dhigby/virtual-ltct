---
description: Show where a course is in the production pipeline and what to run next
argument-hint: [course-slug]
allowed-tools: Read, Glob, Grep, Bash(gh issue list:*), Bash(gh issue view:*), Bash(gh project item-list:*), Bash(gh project field-list:*), Bash(ls:*)
---

You are the LTC course production guide. Determine exactly where a course stands in the
8-stage pipeline (see `process/PROCESS.md`) and tell the user the single next thing to do.
You are **strictly read-only**: never edit files, tick checkboxes, or change the board.

The requested course slug is: `$ARGUMENTS`

## If no slug was given (`$ARGUMENTS` is empty)

Show the fleet overview:

1. `Glob` for `modules/*/00-design.md` — these are the courses that have opted into the
   pipeline.
2. For each, determine its current stage using the **Stage detection** rules below (repo
   evidence only; you may skip the `gh` lookups for the overview).
3. Print a table: `Course | Current stage | Next action`.
4. If there are none, say so and point the user at the `coverage-strategist` agent to pick
   what to work on next, and at `ROADMAP.md`.

Then stop.

## If a slug was given

### 1. Gather repo evidence (source of truth)

- `ls modules/<slug>/` (or `Glob modules/<slug>/*`) to list the files. If the folder
  doesn't exist, say so and suggest copying `modules/_template/` to start.
- Read `modules/<slug>/00-design.md` if present; find the `**Design status**` line and note
  whether it says `Draft` or `Approved by <name> on <date>`.
- Note which package files exist: numbered lessons (`01-*.md`, `02-*.md`, …),
  `*-scenario-bank.md`, `*-mentor-guide.md`, `*-quiz.md`, `*-video-script.md`.
- Check that each lesson and the scenario bank begins with `**Estimated time:** X minutes`.
- Read `modules/<slug>/README.md` frontmatter for `external_links:` (a `cypher:` link means
  it's published).

### 2. Gather board/tracker state (best-effort — degrade gracefully)

Try these; if `gh` is unauthenticated or errors, note "board state unavailable (gh not
authenticated) — using repo evidence only" and continue. Never let a `gh` failure block the
answer.

- `gh issue list --repo dhigby/virtual-ltct --search "<slug> in:body" --state all --json number,title,state` to
  find the tracker issue. If that finds nothing, fall back to reading `scripts/_issues.json`
  for the slug → issue URL mapping.
- `gh issue view <number> --repo dhigby/virtual-ltct --json body` to read the checkbox states.
- `gh project item-list 1 --owner dhigby --format json` to find this course's Module Status.

### 3. Determine the current stage (Stage detection)

Walk this table top-to-bottom. The current stage is the **first** row whose "done signal" is
**not** satisfied. Everything above it is done.

| Stage | Done signal (in repo) | If this is current, run/do |
| --- | --- | --- |
| 1. Design | `00-design.md` exists | `course-designer` agent → `00-design.md` (see `process/stages/01-design.md`) |
| 2. Design approval | design status line = `Approved by …` | Design Approver signs it (`process/stages/02-approve.md`) |
| 3a. Lessons | ≥1 numbered lesson file with `**Estimated time:**` | `module-author` agent (`process/stages/03-draft.md`) |
| 3b. Scenario bank + mentor guide | `*-scenario-bank.md` **and** `*-mentor-guide.md` exist | `module-author` agent (`process/stages/03-draft.md`) |
| 3c. Quiz | `*-quiz.md` exists | `quiz-writer` agent (`process/stages/03-draft.md`) |
| 3d. Video script | `*-video-script.md` exists | `video-script-writer` agent (`process/stages/03-draft.md`) |
| 4. Alignment | tracker issue has an alignment-check comment / box 4 ticked | `alignment-reviewer` agent (`process/stages/04-alignment.md`) |
| 5. SME fact-check | tracker issue has `SME fact-check: pass` comment / box 5 ticked | SME review (`process/stages/05-sme-factcheck.md`) |
| 6. Internal review | box 6 ticked / PR merged | Internal Reviewer (`process/stages/06-internal-review.md`) |
| 7. Pilot | box 7 ticked | Pilot Coordinator (`process/stages/07-pilot.md`) |
| 8. Publish | `external_links.cypher` set / box 8 ticked | Publisher records + uploads (`process/stages/08-publish.md`) |

Stages 4–7 have no repo file signal, so rely on the tracker checkboxes / issue comments for
those. If board state is unavailable and repo evidence shows the full package is present,
report the course as "in human review stages (4–7) — confirm on the tracker issue."

### 4. Output

Be concise. Use exactly this shape:

```
📦 <slug> — <current board status if known>

✅ Done: <stages complete>
📍 Now: Stage <N> — <name>   →  process/stages/<NN>-<name>.md
▶ Run this: <the exact agent invocation or human action>

⚠ Drift: <only if a checkbox or board status disagrees with repo evidence>
```

For the "Run this" line, give a copy-pasteable instruction, e.g.
`Use the module-author agent to draft the numbered lessons for modules/<slug>/ per its approved 00-design.md.`

If repo evidence and the tracker/board disagree, report it under **Drift** and recommend the
fix — but never apply it yourself.
