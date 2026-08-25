# LTC Course Production Process

This is the one place that answers **"what do I do next?"** for any course. If you only
read one section, read [The 30-second answer](#the-30-second-answer).

## Terminology (used everywhere in this repo)

| Term | Meaning |
| --- | --- |
| **Course** | One folder under `modules/<slug>/`. Addresses one or more competencies; holds the whole content package. |
| **Lesson** | One numbered file inside a course (`01-*.md`, `02-*.md`, …), capped at **90 minutes** of learner seat time. |
| **Package** | The full set of files a finished content course has (design, lessons, scenario bank, mentor guide, quiz, video script). See [`modules/_template/`](../modules/_template/). |
| **Online** | The course is delivered — its video is recorded and uploaded to Cypher for Business. This is the *done* state. (We do **not** say "Delivered"; the board option is `Online`.) |

> The GitHub Project board is titled "LTC Training Modules" and its field is called
> "Module Status" — for historical reasons. Read "Module" there as **course**.

## The 30-second answer

Open the repo in Claude Code and run:

```
/work-on <course-slug>
```

It pins the session to that one course, puts you on its branch, reads the course folder
and its tracker issue, tells you exactly which stage the course is at, and gives you the
one command to run next.

`/next-step <course-slug>` is the read-only version — same answer, changes nothing. Run
`/next-step` bare to list the courses in the pipeline so you can pick one. That list is a
**chooser, not a to-do list**: this repo builds one course at a time.

## The pipeline

Every content course moves through eight stages. Each has a one-page how-to under
[`process/stages/`](stages/).

| # | Stage | Who | You run / do | Board status |
| --- | --- | --- | --- | --- |
| 1 | [Design](stages/01-design.md) | Author | `course-designer` agent → `00-design.md` | Design |
| 2 | [Design approval](stages/02-approve.md) | Design Approver | Review + sign the design doc | Design |
| 3 | [Draft](stages/03-draft.md) | Author | `module-author`, `quiz-writer`, `video-script-writer` agents | Drafting |
| 4 | [Alignment check](stages/04-alignment.md) | Author | `alignment-reviewer` agent | Drafting |
| 5 | [SME fact-check](stages/05-sme-factcheck.md) | SME | Verify tools/facts/field detail | SME Check |
| 6 | [Internal review](stages/06-internal-review.md) | Internal Reviewer | Review PR + merge | Internal Review |
| 7 | [Pilot](stages/07-pilot.md) | Pilot Coordinator | Run with one learner | Pilot |
| 8 | [Record & publish](stages/08-publish.md) | Publisher | Record video, upload to Cypher | Publishing → Online |

The stages are gates, not suggestions: don't start drafting before the design is
approved, and don't publish before the pilot. `/work-on` and `/next-step` tell you which
gate you're at, so you don't have to track it yourself.

## Board status vocabulary

The "Module Status" field on the board has these options (in order):

```
Not started · Design · Drafting · SME Check · Internal Review · Pilot · Publishing · Online
```

If you are looking at a board that still shows the old four values
(`Not started · In progress · Internal Review · Online`), the one-time expansion in
[`board-admin.md`](board-admin.md) hasn't been applied yet — tell the maintainer.

| Old value | New value |
| --- | --- |
| Not started | Not started (unchanged) |
| In progress | **Drafting** (renamed) |
| — | Design (new) |
| — | SME Check (new) |
| — | Pilot (new) |
| — | Publishing (new) |
| Internal Review | Internal Review (unchanged) |
| Online | Online (unchanged) |

## Roles

Roles are **hats, not headcount** — one person can wear several, but a course needs at
least two humans so that review is independent.

| Role | Responsibility | Independence rule |
| --- | --- | --- |
| **Author** | Runs the authoring agents, drafts and revises the package. | — |
| **Design Approver** | Signs off `00-design.md` before drafting begins. | Must not be the Author. |
| **SME** | Verifies technical/field accuracy at stage 5. | May be the Author only if the Internal Reviewer is someone else. |
| **Internal Reviewer** | Reviews the finished package for pedagogy and learner-readiness; merges the PR. | Must not be the Author. |
| **Pilot Coordinator** | Recruits one learner, gathers pilot feedback, confirms fixes. | — |
| **Publisher** | Records the video and uploads it to Cypher; sets the course Online. | — |
| **Maintainer** | Board admin (fields, statuses), merge rights, label management. Currently Doug. | — |

Fill in your team's actual people here:

| Person | Default hats |
| --- | --- |
| _(name)_ | _(e.g. Author, SME)_ |
| _(name)_ | _(e.g. Internal Reviewer, Design Approver)_ |

## One issue per course

Each course has exactly **one** tracking issue on the board — the "Course production
tracker." Its checklist mirrors the eight stages (with the draft stage split into its
four artifacts). Checking a box records progress; the [`/next-step`](../.claude/commands/next-step.md)
command treats the actual files in the repo as the source of truth and will flag any
checkbox that disagrees with reality.

- **New course:** open a *Course production* issue from the
  [issue template](../.github/ISSUE_TEMPLATE/course-production.yml). A maintainer adds it
  to the board at status `Design`.
- **Existing course (one of the 28 seeded issues):** don't open a second issue. When the
  course enters production, append the checklist from
  [`tracker-checklist.md`](tracker-checklist.md) to its existing issue (edit the issue
  body in the GitHub UI and paste it in below the existing content).

## One branch per course

Each course's work happens on exactly **one** branch, named `course/<slug>` — for example
`course/bloom`. (Backfill imports use `backfill/<slug>`.)

**You never type a branch name.** [`/work-on <slug>`](../.claude/commands/work-on.md)
derives it from the slug, checks `origin` for an existing one, and either checks that out or
creates it. Because the name is always derived and never invented, a second branch for the
same course can't happen — no `bloom-fix`, no `doug-bloom`, no `bloom-v2`.

That also means the branch acts as a soft lock: **one course, one branch, one person at a
time.** If `/work-on` finds the branch already exists, it tells you who last pushed to it
and whether a PR is open, so you coordinate instead of colliding. The next stage's owner
picks the course up after the PR merges.

Why branches at all, when contributors aren't git people:

- Half-finished drafting stays out of everyone else's copy until it's reviewed.
- The content checks in CI (`check_course_package.py`, coverage, descriptor sync) run on
  **pull requests** — so a missing duration header or a misspelled competency name is
  caught *before* it lands on `main`, not after everyone has pulled it.
- Editing `modules/` while on `main` is refused by a session hook, which points you at
  `/work-on`. That refusal is the only hard block in the repo.

## Planning ritual — filling the gaps

The end goal is a course for every competency in [`competencies.yaml`](../competencies.yaml).
[`COVERAGE.md`](../COVERAGE.md) shows the gaps; [`ROADMAP.md`](../ROADMAP.md) is where we
commit to closing them. Once a month (or each quarter):

1. The Maintainer runs the `coverage-strategist` agent for a recommended next course, with
   its reasoning and a couple of runners-up.
2. The team commits to **the one** they'll build next.
3. Open its Course production tracker issue, add it to the board (`Not started`, set
   Priority), and fill in its row in `ROADMAP.md`.

One at a time. `ROADMAP.md` is an ordered queue, not a set of parallel commitments — the
gap list is long, so the discipline is finishing, not claiming. Don't pre-create 22 tracker
issues; empty "Not started" rows are just board noise.

## Backfilling legacy content

Separately from the new-course pipeline, the ~23 legacy courses (garbled Notion imports
and stubs) still have their authoritative content in **Cypher for Business**, not here.
Making this repo the true source of truth is the [backfill workstream](backfill.md),
tracked in [`BACKFILL.md`](../BACKFILL.md). Backfilled courses are a **faithful import**
— they are grandfathered and do **not** need the full package.

## See also

- [`ONBOARDING.md`](../ONBOARDING.md) — start here if you're new to the team.
- [`CONTRIBUTING.md`](../CONTRIBUTING.md) — editing mechanics.
- [`board-admin.md`](board-admin.md) — maintainer-only board field administration.
