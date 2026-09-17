# Contributing

The team builds courses with **Claude Code** in this repo. The full method — the 8-stage
pipeline, the per-stage agents, and the roles — lives in
[`process/PROCESS.md`](process/PROCESS.md). New to the team? Start with
[`ONBOARDING.md`](ONBOARDING.md).

## The one thing to remember

Open the repo in Claude Code and run:

```
/work-on <course-slug>
```

That's how every session starts. It pins the session to **one** course, puts you on that
course's branch, and tells you the single next thing to do — no git knowledge needed. Run
`/next-step` (read-only) to check where a course stands without starting on it, or bare to
list the courses in the pipeline and pick one.

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
3. Run `/work-on <slug>` and follow it from stage 1. It creates the course's branch
   (`course/<slug>`) for you.

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

## Reviewing a course without reading markdown

Every course in the pipeline is published as an ordinary website, so a reviewer or a pilot
learner never has to open a `.md` file or read a diff:

| You are… | Open |
|---|---|
| an **SME** (stage 5) or **internal reviewer** (stage 6) | `https://competencies.languagetechnology.org/review/<course-slug>/` |
| giving it to a **pilot learner** (stage 7) | `https://competencies.languagetechnology.org/learn/<course-slug>/` |

The `/learn/` version leaves out the design document, the mentor guide and the quiz answer
key — so **send a pilot learner that one**, not `/review/`.

You don't need to install anything to read either. They rebuild a couple of minutes after
each push, from the course's own branch until it merges and from `main` afterwards, so the
link keeps working all the way through. Leave feedback as PR or tracker-issue comments, as
before. `/next-step <slug>` prints the right link from stage 4 on, and `/review-site <slug>`
serves it on your own machine if you're editing.

> Don't run `mkdocs gh-deploy` yourself — it republishes the whole site and would wipe
> every course preview until the next automatic build.

## Small fixes without Claude Code (appendix)

For a typo or a one-line change you don't need Claude Code:

- **Browser:** open the file on github.com, click the pencil (✏️), edit below the
  frontmatter, and choose "Create a new branch and start a pull request."
- **GitHub Desktop:** clone `dhigby/virtual-ltct`, edit in any text editor, commit, push.

Full course authoring, though, runs through the pipeline above.

## Questions

Open an issue, or ask in the team channel.
