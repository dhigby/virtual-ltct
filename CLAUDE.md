# CLAUDE.md

Guidance for AI assistants working in this repo. This is a **content repository** for
the Language Technology Consultant (LTC) training curriculum — markdown training modules,
not an application. There is no build/test/run loop; the "checks" are content + coverage.

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
   in frontmatter instead. Small images are fine in the module folder (e.g. `assets/`).

6. **Lesson duration header.** Every numbered lesson file and the scenario bank opens, right
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
`competencies/` — nothing is duplicated in git) and deploys to the `gh-pages` branch.
Preview locally with `pip install -r docs-requirements.txt && mkdocs serve`.

## Maintainer scripts (`scripts/`)

- `gen_coverage.py` — regenerates `COVERAGE.md` (also run by CI).
- `check_course_package.py` — validates a course package's completeness/format (run by CI).
  Only checks courses that have opted into the pipeline (those with a `00-design.md`); all
  legacy courses are untouched. `--course <slug>` runs it for one course.
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

See [README.md](README.md) and [CONTRIBUTING.md](CONTRIBUTING.md) for the human-facing
contributor workflow (browser editing, GitHub Desktop, adding modules via issue template).
