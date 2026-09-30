---
description: Open a browsable preview of one course, so it can be read as a web page instead of raw markdown
argument-hint: [course-slug]
allowed-tools: Read, Glob, Grep, Bash(python scripts/review_site.py:*), Bash(python scripts/course_stage.py:*), Bash(python scripts/check_learner_view.py:*), Bash(git rev-parse:*), Bash(git status:*), Bash(git log:*), Bash(git ls-remote:*), Bash(gh pr list:*)
---

You are opening a **course review site** — the course's markdown rendered as a real,
browsable site, so an SME, an internal reviewer or a pilot learner can read it as a web
page rather than as a raw diff on GitHub.

Requested course: `$ARGUMENTS`

You do **not** author or edit content here. This command only previews. If the course
needs changes, that is the stage's own agent's job.

## 1. Work out which course

Same three-way resolution as [`/next-step`](next-step.md):

- **A slug was given:** use it.
- **No slug, and this session is on a `course/<slug>` branch:** that's the course
  (`git rev-parse --abbrev-ref HEAD`, then `python scripts/course_stage.py --resolve <branch>`).
- **No slug and not on a course branch:** run `python scripts/course_stage.py --all`, show
  the table, and ask which one. Then stop.

## 2. Choose the view — this matters

| View | Who it's for | What it holds back |
|---|---|---|
| `reviewer` (default) | SME (stage 5), internal reviewer (stage 6) | nothing |
| `learner` | **pilot learner (stage 7)**, or "what will this feel like?" | design doc, mentor guide, video scripts, quiz answer key |

**Never send a pilot learner the reviewer view** — it contains the answer key and the
mentor guide's scoring notes. If the person asks for something to give a learner, or
mentions the pilot, use `--view learner`.

If unsure which they want, ask — it is one short question and the wrong answer spoils an
assessment.

## 3. Start it

```bash
python scripts/review_site.py --slug <slug> [--view learner] [--open]
```

It serves on <http://127.0.0.1:8001/> and reloads as the files are edited. Ctrl-C stops it.

By default it renders **the files as they are on disk right now**, including uncommitted
edits. To see a course as it exists on its branch instead, add `--ref course/<slug>`.

## 4. Say what git did, in one plain sentence

- **No `--ref`:** *"This is showing your own files exactly as they are right now,
  including anything you haven't committed."*
- **With `--ref`:** *"I made a temporary copy of the course as it exists on
  `course/<slug>`, off to one side. Your own files have not moved and have not changed —
  the copy disappears when you stop the preview."*

If `git status --short -- modules/<slug>` shows uncommitted changes **and** they are about
to share this with someone, say so plainly: *"the preview includes edits nobody else can
see yet — commit and push them first, or the reviewer will read an older version."*

## 5. Output

Use exactly this shape:

```
🔎 <slug> — <view> view

📄 Source: <your working tree | course/<slug> (<short-sha>)>
📍 Stage:  <stage name>
💻 Local:  http://127.0.0.1:8001/   (Ctrl-C to stop)

▶ Next:  <the one thing to do — e.g. "Read lessons 1-5 and leave comments on PR #41.">

⚠ <only if: uncommitted changes, or the learner/reviewer view choice looks wrong>
```

Keep the "Next" line copy-pasteable.

## Notes

- The whole site is generated; **nothing is written into `modules/`**. `--ref` uses a
  throwaway git worktree in the system temp folder, removed when the preview stops.
- If a build fails, the error names the file. Course markdown is hand-authored, so the
  usual cause is a malformed link or table — fix it in the course, not in the generator.
- Before handing anyone the learner view, the safety check is
  `python scripts/check_learner_view.py --built <dir> --course <slug>` after a
  `--build --view learner`. CI runs this too; it fails on any answer-key leak.
