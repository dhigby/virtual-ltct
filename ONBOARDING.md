# Onboarding — building LTC courses

Welcome. This repo is the home of the **Language Technology Consultant (LTC) training
curriculum**. We build courses here as markdown, using **Claude Code** to drive an 8-stage
production pipeline, and track progress on a GitHub Project board. The end goal is a course
for every competency in [`competencies.yaml`](competencies.yaml).

**Terminology:** a **course** is one folder under `modules/<slug>/`; a **lesson** is one
numbered file inside it (`01-*.md`, …), capped at 90 minutes; the **package** is the full
set of files a finished course has.

## 1. Set up (once)

1. **Install [Claude Code](https://claude.com/claude-code)** and sign in.
2. **Install the GitHub CLI** (`gh`) and authenticate:
   ```bash
   gh auth login
   gh auth refresh -s project   # lets you read/update the board
   ```
3. **Clone the repo:**
   ```bash
   gh repo clone dhigby/virtual-ltct
   ```
4. **Install the one Python dependency** the scripts use:
   ```bash
   pip install pyyaml
   ```
5. **Ask a maintainer** (currently Doug) to grant you write access to the **LTC Training
   Modules** Project board — otherwise you can see courses but can't move their status.

## 2. The tour

- **[`process/PROCESS.md`](process/PROCESS.md)** — the whole method: the 8 stages, the
  roles, the board statuses. Read this once, top to bottom.
- **The `/work-on` command** — how every working session starts. In Claude Code, run
  `/work-on <course-slug>`. It puts you on that course's own branch and tells you the one
  next thing to do. You never have to create a branch or think about git yourself.
- **The `/next-step` command** — the read-only version: `/next-step <course-slug>` says
  where a course stands without changing anything. Run it bare to list the courses in the
  pipeline and pick one.
- **The board** — the "LTC Training Modules" GitHub Project. Each course has one tracker
  issue with a stage checklist.
- **The seven agents** (in Claude Code, via the Agent tool):
  | Agent | Does |
  | --- | --- |
  | `course-designer` | Writes the design doc (stage 1) |
  | `module-author` | Drafts lessons + scenario bank + mentor guide (stage 3) |
  | `quiz-writer` | Writes the quiz (stage 3) |
  | `video-script-writer` | Writes the video script (stage 3) |
  | `alignment-reviewer` | Checks content vs. design (stage 4) |
  | `coverage-strategist` | Recommends what to work on next |
  | `content-backfiller` | Imports legacy Cypher content (backfill) |

## 3. Your first contribution (about 30 minutes)

The daily loop is the same every time — learn it once on a small step:

1. **Pick one course.** Ask what needs help, or run `/next-step` bare to see the courses in
   the pipeline and what each needs next. You work on **one course at a time** here.
2. **In Claude Code, run** `/work-on <slug>`. It puts you on that course's branch, warns you
   if a teammate is already working it, and tells you the one next thing — say, "run the
   `quiz-writer` agent" or "the design doc needs approval."
3. **Do that one thing.** Follow the linked stage page under
   [`process/stages/`](process/stages/) if you're unsure how.
4. **Tick the checkbox** on the tracker issue and **move the board status** if the stage
   changed.
5. **Let Claude commit, push, and open the PR** for the files you changed. You review the
   PR in the browser; you never have to create one.

That's it. Every course is just this loop repeated eight times. When you finish a course and
want to start something new, run the `coverage-strategist` agent or look at
[`ROADMAP.md`](ROADMAP.md).

> **Why branches?** Each course gets exactly one branch, named `course/<slug>`. It keeps your
> half-finished drafting out of everyone else's copy until it's reviewed, and it's what makes
> the automated content checks run *before* your work lands rather than after. `/work-on`
> handles all of it — if you ever wonder "where did my files go?", run `/work-on <slug>`
> again and it will tell you exactly where you are.

## 4. Two side-tracks

- **Backfilling old courses:** many courses were delivered in Cypher for Business and only
  exist here as stubs. Converting them to real markdown is the
  [backfill workstream](process/backfill.md) — see [`BACKFILL.md`](BACKFILL.md).
- **Small fixes:** for a typo you don't need Claude Code — edit in the browser (see
  [`CONTRIBUTING.md`](CONTRIBUTING.md)).

## 5. Where to ask

Open an issue or ask in the team channel. If `/next-step` ever gives you a confusing answer,
that's a bug worth reporting — it's meant to always know what's next.
