# Contributing — no command line required

You can build training modules here entirely in your web browser or with GitHub Desktop.
Pick whichever feels easier.

## Option A — Edit in the browser (simplest)

1. Open the module you want to work on, e.g. `modules/bloom/README.md`, on github.com.
2. Click the **pencil (✏️) "Edit this file"** button.
3. Write your content in markdown below the `---` frontmatter block. (Leave the
   frontmatter at the top alone unless you're changing competencies — see below.)
4. Scroll down, add a short note in **"Commit changes"**, and choose
   **"Create a new branch and start a pull request"** (recommended) or commit directly.
5. A maintainer reviews and merges. The coverage report updates automatically.

## Option B — GitHub Desktop (good for bigger edits / many files)

1. Install **GitHub Desktop** and sign in.
2. **File → Clone repository →** `dhigby/virtual-ltct`.
3. Edit files in any text editor (VS Code, Notepad, Obsidian — the files are plain markdown).
4. In GitHub Desktop: write a summary, click **Commit to main** (or create a branch),
   then **Push origin**.

## Adding a brand-new module

Open an issue using the **"Add a training module"** template (Issues → New issue). A
maintainer will create the module folder and add it to the board.

## Module content package

A **course** is one folder under `modules/<slug>/` and may address one or more
competencies. A **module** is one numbered lesson file within a course (`01-*.md`,
`02-*.md`, …), capped at **90 minutes of learner seat time** — each file must state
`**Estimated time:** X minutes` at the top.

Every **content** course should eventually have the same set of files — copy
[`modules/_template/`](modules/_template/) as a starting point:

| File | Purpose |
| --- | --- |
| `00-design.md` | Course design doc — learning objectives, module breakdown, assessment plan. **Must be approved before content drafting.** |
| `README.md` | Frontmatter + learner-facing intro and table of contents. |
| `01-*.md`, `02-*.md`, … | Numbered lesson content (each ≤ 90 minutes, with estimated duration stated). |
| `NN-scenario-bank.md` | Applied practice scenarios, foundational → complex. |
| `NN-mentor-guide.md` | Facilitator notes and answer guidance for the scenario bank. |
| `NN-quiz.md` | Assessment questions with a pass threshold and answer key in the body. |
| `NN-video-script.md` | Script for the video-recording step before upload to Cypher. |

Sub-files don't carry their own frontmatter — only `README.md` does. See
`modules/_template/README.md` for the full explanation and a frontmatter example.

### Workflow

Design → approve → draft (content, quiz, script, guide) → alignment check → SME
fact-check → internal review → pilot with one learner → upload to Cypher.

If a module's board state looks stale after the Notion migration — for example a course
that's fully delivered online but still shows `Not started` — the `migration-reconciler`
agent walks you through auditing the Project board against each module's real state and
updates the board fields for you (after you confirm each change).

## Editing the frontmatter (the part between the `---` lines)

Most of the time you only edit the content below the frontmatter. If you change which
competencies a module teaches, edit the `competencies:` list — and each name **must match
exactly** one in [`competencies.yaml`](competencies.yaml), or it won't count toward
coverage. Don't edit `status`/`priority` here — those live on the Project board.

## What not to commit

- Large video files. Link to Vimeo or Google Drive instead (put the URL under
  `external_links:` in the frontmatter). Small images are fine under the module's
  `assets/` folder.
- `COVERAGE.md` — it's generated automatically; don't hand-edit it.

## Questions

Open an issue, or ask in the team channel.
