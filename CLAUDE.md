# CLAUDE.md

Guidance for AI assistants working in this repo. This is a **content repository** for
the Language Technology Consultant (LTC) training curriculum — markdown training modules,
not an application. There is no build/test/run loop; the "checks" are content + coverage.

**Read [`INTENT.md`](INTENT.md) before building or changing anything in this repo** — tooling,
scripts, process, the sites. It states the problem this repo exists to solve, its hard
constraints, and what is deliberately out of scope. This file gives you the rules; `INTENT.md`
gives you the *why*, so you can decide the cases the rules don't cover. A proposed change that
contradicts it should be raised, not routed around.

## How a session in this repo runs (read this first)

**One course per session.** Contributors here work on a single course at a time, and most
are not comfortable with git. So before doing any course work:

1. **Establish which one course** this session is for. Don't guess, and don't offer to work
   on several at once. `python scripts/course_stage.py --all` lists what's in the pipeline.
2. **Run [`/work-on <slug>`](.claude/commands/work-on.md).** It puts the session on that
   course's branch (`course/<slug>`, always derived from the slug — never invented, which is
   what stops duplicate branches for one course), then reports the stage and next action.
3. **Never edit `modules/` while on `main`.** A hook refuses it; `/work-on` is the fix.
4. **Do the current stage only.** The eight stages are *gates* — no drafting before the
   design is approved, no publishing before the pilot.
5. **Route authoring through the stage's agent** (`course-designer`, `module-author`,
   `quiz-writer`, `video-script-writer`, `alignment-reviewer`) and the matching
   `process/stages/<NN>-*.md` how-to, rather than free-typing content.

**You do the git; you explain the git.** After any branch operation, say in one plain
sentence what changed and where their files are now — "where did my files go?" after a
branch switch is the single most common confusion here.

[`scripts/course_stage.py`](scripts/course_stage.py) is the **only** implementation of stage
detection. `/next-step`, `/work-on`, the session hooks and the status line all read it. Don't
re-derive a course's stage by hand.

## What this repo is

- One folder per module under `modules/<slug>/`, each with a `README.md` (some modules
  split content across numbered files like `01-…md`, `02-…md` alongside the `README.md`).
- A fixed competency framework in [`competencies.yaml`](competencies.yaml) — 42
  competencies across 6 categories — is the **source of truth** for coverage.
- Migrated from a Notion database (2026-06-18); content now lives here as markdown.

**Terminology:** a **course** is one folder under `modules/<slug>/`; a **lesson** is one
numbered file inside it (`01-*.md`, …), capped at 90 minutes. (The board field "Module
Status" predates this terminology — read "Module" there as *course*.)

## Who an LTC is — and what that changes about the content

Three facts about the job shape almost every authoring decision here. They are easy to
violate by accident, because the natural way to write a training example breaks all three.

- **An LTC does not speak the languages they support.** They consult on the *technology* —
  Paratext, keyboards, fonts, FieldWorks, Bloom, backups — for teams translating into
  languages the consultant cannot read. So never write a lesson, scenario or quiz item
  whose answer depends on the consultant judging whether the text itself is right. The
  competence being taught is diagnosing the tool, the data and the workflow, and knowing
  which questions belong back with the translation team.

- **The work is often the first translation ever into that language.** Assume nothing is
  already in place: no settled orthography, no spell-check dictionary, no existing digital
  corpus, no font or keyboard someone else already made, no Wikipedia article to check
  against. Examples that quietly assume any of that exists describe a world the learner
  doesn't work in.

- **AI cannot read these languages — and the examples are real.** Training here uses real
  data from real projects, not invented languages; that realism is the point and must not
  be swapped for a made-up example. But these are minority languages, largely absent from
  model training data, so **never assume you can look at local language data and tell what
  it means, whether it is correct, or what the fix is.** Don't gloss, translate, judge or
  silently normalise it, and don't recommend a solution derived from reading it — a
  plausible-looking answer about text you cannot actually read lands in front of a learner
  with no way to check it. Real examples come from a human: the author, the project, or
  the SME at stage 5. When a draft needs one you don't have, leave a marked placeholder
  saying what the example must show rather than filling it in. The same holds for stating
  facts about a named language's script, tone marking or character set: get it from a
  human, or don't state it.

## Competency levels (CBC) — and the offset that trips everyone up

This curriculum exists to advance people through the **Competency-Based Certification (CBC)**
program, so it uses the CBC scale and nothing else. It is defined once, in
[`outcome-levels.yaml`](outcome-levels.yaml):

`0 - No Competency · 1 - Has Knowledge · 2 - With Assistance · 3 - Independent · 4 - Expert`

**A level names where a learner *is*. The activities listed against that level are what they
do to reach the *next* one.** In a descriptor's ladder, the row labelled `1 - Has Knowledge`
holds the activities that carry a learner to `2 - With Assistance` — which is why every ladder
table now carries a `Reaches` column. Design objectives for level N from the row labelled
N-1.

A course's `target_outcome_level` is **where the learner lands**, so it is `1`–`4`; never `0`,
since no course leaves someone at No Competency.

The legacy vocabulary (`Learner · Advanced Beginner · Practitioner · Trainer/Proficient ·
Expert`, and the two-value `Has knowledge`/`With Assistance`) is retired — do not reintroduce
it. It came from a spreadsheet whose header had *two* rows, one naming the destination and one
naming the performer; the import kept only the second, which is what shifted every rung by one.

## The production process

New content courses are built through an 8-stage pipeline — Design → approve → draft →
alignment check → SME fact-check → internal review → pilot → publish — documented in
[`process/PROCESS.md`](process/PROCESS.md) with a one-page how-to per stage under
[`process/stages/`](process/stages/). Each course has one **Course production tracker**
issue on the board; its checkboxes are the per-course to-do list. The
[`/next-step`](.claude/commands/next-step.md) command tells a contributor exactly where a
course is and what to run next. Seven per-stage subagents live in
[`.claude/agents/`](.claude/agents/).

**Board "Module Status" values** (the pipeline vocabulary):
`Not started · Design · Drafting · SME Check · Internal Review · Pilot · Publishing · Online`.

**Backfill** of legacy Cypher-delivered courses (making this repo the source of truth) is a
separate faithful-import workstream — see [`process/backfill.md`](process/backfill.md) and
[`BACKFILL.md`](BACKFILL.md); backfilled courses are grandfathered and skip the full package.

## Conventions you MUST follow (these are easy to get wrong)

1. **Source-of-truth split.** A module's `competencies`, `target_outcome_level`,
   `external_links`, etc. live in its markdown **frontmatter**. Its *workflow* state —
   status, priority, who's working on it — lives on the **GitHub Project board**, NOT in
   the markdown. Do not add/edit `status` or `priority` in frontmatter.

2. **Never hand-edit [`COVERAGE.md`](COVERAGE.md).** It is auto-generated by
   `scripts/gen_coverage.py` (and by CI on every change). To change coverage, change a
   module's frontmatter `competencies:` list, then regenerate (see below).

3. **Competency names must match `competencies.yaml` EXACTLY.** A frontmatter competency
   not found in `competencies.yaml` is a silent coverage miss and a hard CI failure
   (`gen_coverage.py` exits 1). Copy names verbatim, including `&` and capitalization.

4. **Module states:** `content_type: content` = teaching material authored here;
   `content_type: stub` = still points to external material (Google Sites / PDF / Vimeo).
   Stubs carry a banner; the goal is to replace stubs with authored content over time.

5. **Don't commit large video files.** Link to Vimeo/Google Drive under `external_links:`
   in frontmatter instead.

6. **Screenshots live in `modules/<slug>/assets/`**, named
   `ss-<lesson number>-<what-it-shows>.png` (lowercase, hyphens, no spaces), and are
   **committed, never hotlinked** — a remote image rots and takes the published page's
   picture with it. An agent writes the image link and its alt text where the shot
   belongs; a human captures the file (stage **3e**). The alt text must describe the
   exact state shown, because it is the screen-reader text *and* the brief for whoever
   takes the shot — `![alt text](…)` is a defect. `check_course_package.py` enforces all
   of this, and `/next-step` reports any shot still outstanding.

7. **Lesson duration header.** Every numbered lesson file and the scenario bank opens, right
   under the H1, with `**Estimated time:** X minutes` — no lesson exceeds 90 minutes. This is
   verified by the alignment-reviewer agent and by `scripts/check_course_package.py`.

## Module frontmatter shape

```yaml
---
title: Bloom
slug: bloom
target_outcome_level: "2 - With Assistance" # CBC level, not workflow status
competencies:
  - Literacy Tools                          # must match competencies.yaml exactly
content_type: stub                          # stub | content
external_links:
  materials: https://…
last_exported: 2026-06-18
---
```

## Regenerating coverage

```bash
python scripts/gen_coverage.py    # rewrites COVERAGE.md; exits 1 on unknown competency names
```
Requires `pyyaml`. Run this after any frontmatter `competencies:` change and commit the
result together with the content change.

## Competency descriptors (`competencies/`)

`competencies.yaml` is only the canonical *name list*. The richer, teachable detail for
each competency — rationale, target statement, and either a per-level activity ladder or
sub-competencies with observable criteria — lives in **hand-authored** descriptor files
under [`competencies/`](competencies/), one per framework competency. This repo is the
source of truth for that content, and it is published to GitHub Pages (see below).

- **Edit `competencies/*.md` directly.** A frontmatter `name:` MUST match
  `competencies.yaml` exactly (copy verbatim, incl. `&`/capitalization), or CI fails.
- **`resources:` entries are `{title, url}` mappings**, not bare URLs — `gen_site.py`
  renders them as each page's **Further Information** section, and the descriptor check
  rejects any other shape. They are hand-maintained (no upstream sync); use `[]` for none.
- The files were first seeded from `import-seeds/` (`Lang Tech Competencies.xlsx` +
  `CBC Guide for Non-technical Competencies…md`) via
  `import-seeds/import_competency_descriptors.py`. That importer is retained for
  provenance only; it refuses to run without `--force` because a re-seed OVERWRITES all
  descriptors, discarding hand edits. Don't run it as part of normal edits.
- `Meta: Uncategorized` intentionally has no descriptor (no source content); it is exempt
  in the sync check.

```bash
python scripts/check_competency_descriptors.py    # exits 1 if descriptors ⇄ framework drift
```
The check (every framework name has a descriptor and vice versa, and required frontmatter
keys are present) runs in CI on any change to `competencies/**`, `competencies.yaml`, or
the competency scripts.

## Publishing (GitHub Pages)

The competency content is published as a MkDocs Material site. On push to `main`,
[`.github/workflows/pages.yml`](.github/workflows/pages.yml) builds it (nav + pages are
generated at build time by [`scripts/gen_site.py`](scripts/gen_site.py) straight from
`competencies.yaml`, `outcome-levels.yaml`, `competencies/` and `COVERAGE.md` — nothing is
duplicated in git) and deploys to the `gh-pages` branch. Preview locally with
`pip install -r docs-requirements.txt && mkdocs serve`.

The build is `strict: true`, so a broken internal link fails it rather than shipping.
Beyond one page per competency, `gen_site.py` generates the landing page, a per-category
overview page, `all-competencies` (one filterable table), `how-to-read-levels`, and the
coverage page. A competency page is *not* a copy of its descriptor: the descriptor's level
tables are re-rendered as a stepped ladder component. **To change how the site looks,
edit `gen_site.py` (structure) and [`docs/stylesheets/extra.css`](docs/stylesheets/extra.css)
(presentation, `cx-`-prefixed classes, brand colours in one block at the top) — not the
descriptors.** Anything in `docs/` is published, so keep repo reference material out of it
or add it to `exclude_docs` in `mkdocs.yml`.

## Course review sites

The same deploy also publishes each in-flight course as a browsable site, so reviewers and
pilot learners never have to read a raw markdown diff. **Two views, and the difference
matters:**

| URL | View | Who | Holds back |
|---|---|---|---|
| `…/review/<slug>/` | reviewer | SME (stage 5), internal reviewer (stage 6) | nothing |
| `…/learn/<slug>/` | learner | **pilot learner (stage 7)** | design doc, mentor guide, video script, quiz answer key |

**Never send a pilot learner the `/review/` URL** — it contains the answer key. Get the URL
from `course_stage.review_url()` (it is in `--json` and in `/next-step` from stage 4 on);
never hand-build it, since a legacy folder like `Paratext 9 advanced support` slugs to
`paratext-9-advanced-support`.

Which git ref a course builds from is decided by **merge-base**, not by an open PR: the
branch while it has unmerged commits, `main` once it merges. That is what makes one URL
work across stages 5, 6 and 7, including across the stage-6 merge.

- Preview one course locally: `/review-site <slug>` (or
  `python scripts/review_site.py --slug <slug> [--view learner]`). It renders your working
  tree, uncommitted edits included.
- [`scripts/gen_course_site.py`](scripts/gen_course_site.py) renders a course;
  [`mkdocs-review.yml`](mkdocs-review.yml) is its config. The docs root is a **flat mirror
  of the course folder**, which is why the relative links authors already write just work.
- **Quiz answer keys use one marker:** a `## Answer key` H2, optionally qualified
  (`## Answer key (Section 1)`) and repeatable. `check_course_package.py` fails CI on any
  other form. It is standardised precisely so the learner view never has to guess where a
  key starts.
- The learner view is a disclosure boundary and fails closed: a quiz whose answer key
  can't be cleanly separated is withheld, not partially stripped — enforcement removes the
  guessing, not the verification.
  [`scripts/check_learner_view.py`](scripts/check_learner_view.py) is the gate; it **fails
  the deploy** on a leak, and [`review-site.yml`](.github/workflows/review-site.yml) runs
  it on every PR touching a course, so a bad marker is caught by its author.
- On a PR, a course that fails to build **blocks the merge**
  (`build_review_sites.py --local --fail-on-error`); on deploy it gets a placeholder page
  and the publish carries on. Deliberate: a course that can't render can't be reviewed,
  but one broken draft must not take the published site down.
- These pages are `noindex` and unlinked from the competency site's nav, and
  `docs/robots.txt` disallows both paths. They are *unlisted, not secret* — the repo is
  public, so anyone with the link can read them.

> **Never run `mkdocs gh-deploy` locally.** It force-pushes the whole `gh-pages` branch and
> would delete every course review site until the next CI run. Deploying is CI's job.

## Maintainer scripts (`scripts/`)

- `gen_coverage.py` — regenerates `COVERAGE.md` (also run by CI).
- `check_course_package.py` — validates a course package's completeness/format (run by CI).
  Only checks courses that have opted into the pipeline (those with a `00-design.md`); all
  legacy courses are untouched. `--course <slug>` runs it for one course.
- `review_site.py` — builds/serves one course's review site locally (`/review-site`).
- `build_review_sites.py` — builds every in-flight course into the deploy tree (CI).
- `gen_course_site.py` — `mkdocs-gen-files` hook for `mkdocs-review.yml`; renders one course.
- `check_learner_view.py` — CI gate: proves the learner view leaks no answer key.
- `gen_site.py` — `mkdocs-gen-files` build hook; generates the site pages + nav from
  `competencies.yaml` and `competencies/*.md`. Not run by hand; invoked by `mkdocs`.
- `check_competency_descriptors.py` — validates descriptors stay in sync with the
  framework (run by CI). Requires `pyyaml`.
- `export_from_notion.py` — idempotent export from the old Notion DB; won't overwrite
  content authored here.
- `bootstrap_github.py` — creates labels, issues, and Project fields from the export.
- `import-seeds/import_competency_descriptors.py` — one-time seed importer (see above);
  requires `pyyaml` + `openpyxl` and `--force`.

## Authoring style

This curriculum follows the **Learning That Lasts** adult-learner framework: every lesson
body is structured as four `##` phases in order — **Connect → Content → Challenge →
Change** — budgeted by the lesson's `**Estimated time:**` header (roughly 10 / 25–30 /
15–20 / 5–10 minutes for a 60-minute lesson). The `training-content` skill, committed at
[`.claude/skills/training-content/`](.claude/skills/training-content/SKILL.md), defines
the methodology — prefer it when drafting or revising module content. The alignment
reviewer (stage 4) verifies the four phases are present in each lesson.

Before inventing an example, a scenario or a quiz item that involves a language, re-read
[Who an LTC is](#who-an-ltc-is--and-what-that-changes-about-the-content) above — it
constrains all four phases, and the Challenge phase most of all.

See [README.md](README.md) and [CONTRIBUTING.md](CONTRIBUTING.md) for the human-facing
contributor workflow (browser editing, GitHub Desktop, adding modules via issue template).
