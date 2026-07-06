# Contributing

The team builds courses with **Claude Code** in this repo. The full method — the 8-stage
pipeline, the per-stage agents, and the roles — lives in
[`process/PROCESS.md`](process/PROCESS.md). New to the team? Start with
[`ONBOARDING.md`](ONBOARDING.md).

## The one thing to remember

Open the repo in Claude Code and run:

```
/next-step <course-slug>
```

It tells you exactly where a course is in the pipeline and the single next thing to do.
Run `/next-step` with no argument to see every in-flight course.

## How a course gets built (the short version)

A **course** is one folder under `modules/<slug>/`; a **lesson** is one numbered file
inside it (`01-*.md`, …), capped at 90 minutes. Every course moves through eight stages —
each with a one-page how-to under [`process/stages/`](process/stages/):

**Design → approve → draft → alignment check → SME fact-check → internal review → pilot →
record & publish to Cypher.**

Each course has one **Course production tracker** issue (open one from the
[issue template](.github/ISSUE_TEMPLATE/course-production.yml)); its checkboxes are the
per-course to-do list. Don't start drafting before the design is approved.

## Adding a brand-new course

1. Copy [`modules/_template/`](modules/_template/) to `modules/<your-slug>/` and fill in
   the `README.md` frontmatter (title, slug, competencies, outcome level).
2. Open a **Course production tracker** issue (Issues → New issue → *Course production
   tracker*). A maintainer adds it to the board at status `Design`.
3. Run `/next-step <slug>` and follow it from stage 1.

To propose an idea without starting production yet, use the **Propose a course** issue
template instead.

## The course package

Every content course ends up with this set of files — copy them from
[`modules/_template/`](modules/_template/):

| File | Purpose |
| --- | --- |
| `00-design.md` | Course design doc — objectives, module breakdown, assessment plan. **Approved before content drafting.** |
| `README.md` | Frontmatter + learner-facing intro and table of contents. |
| `01-*.md`, `02-*.md`, … | Numbered lesson content (each ≤ 90 minutes, opening with `**Estimated time:** X minutes`). |
| `NN-scenario-bank.md` | Applied practice scenarios, foundational → complex. |
| `NN-mentor-guide.md` | Facilitator notes and answer guidance for the scenario bank. |
| `NN-quiz.md` | Assessment questions with a pass threshold and answer key in the body. |
| `NN-video-script.md` | Script for the video-recording step before upload to Cypher. |

Sub-files don't carry their own frontmatter — only `README.md` does. See
`modules/_template/README.md` for the full explanation and a frontmatter example.

## Editing the frontmatter (the part between the `---` lines)

Most of the time you only edit content below the frontmatter. If you change which
competencies a course teaches, edit the `competencies:` list — each name **must match
exactly** one in [`competencies.yaml`](competencies.yaml), or it won't count toward
coverage (and CI fails). Don't edit `status`/`priority` here — those live on the Project
board.

## What not to commit

- Large video files. Link to Vimeo or Google Drive instead (put the URL under
  `external_links:` in the frontmatter). Small images are fine under the course's `assets/`
  folder.
- `COVERAGE.md` — it's generated automatically; don't hand-edit it.

## Backfilling legacy courses

Many older courses were delivered from Cypher for Business and imported here only as stubs
or rough README files. Making this repo their true source of truth is the
[backfill workstream](process/backfill.md), tracked in [`BACKFILL.md`](BACKFILL.md).

## Small fixes without Claude Code (appendix)

For a typo or a one-line change you don't need Claude Code:

- **Browser:** open the file on github.com, click the pencil (✏️), edit below the
  frontmatter, and choose "Create a new branch and start a pull request."
- **GitHub Desktop:** clone `dhigby/virtual-ltct`, edit in any text editor, commit, push.

Full course authoring, though, runs through the pipeline above.

## Questions

Open an issue, or ask in the team channel.
