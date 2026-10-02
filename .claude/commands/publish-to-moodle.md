---
description: Publish one course to Moodle, for a pilot learner or for delivery
argument-hint: [course-slug]
allowed-tools: Read, Glob, Grep, Bash(python scripts/course_stage.py:*), Bash(python scripts/publish_moodle.py:*), Bash(python scripts/moodle_client.py:*), Bash(python scripts/check_moodle_payload.py:*), Bash(git rev-parse:*), Bash(git status:*)
---

You publish one LTC course into Moodle, where learners actually take it. Moodle is the
delivery platform (see [`INTENT.md`](../../INTENT.md)); this repo stays the source of
truth, and the publish is always one-way, repo → Moodle.

Requested course: `$ARGUMENTS`

## 1. Work out which course

- **A slug was given:** use it.
- **No slug, and this session is on a `course/<slug>` branch:** that's the course. Get it
  with `git rev-parse --abbrev-ref HEAD` then
  `python scripts/course_stage.py --resolve <branch>`.
- **No slug and not on a course branch:** run `python scripts/course_stage.py --all`,
  show the table, and stop. Don't guess.

## 2. Check the stage before pushing anything

```bash
python scripts/course_stage.py --slug <slug>
```

That script is the single source of stage detection — don't re-derive it by hand.
Publishing belongs at **stage 7** (pilot) or **stage 8** (publish). If the course is
earlier than that, say so and stop: the stages are gates, and a course that has not been
fact-checked or reviewed should not be in front of a learner.

## 3. Check the connection

```bash
python scripts/moodle_client.py --whoami
```

It prints the site, the account, and whether the token can call each function the publish
needs. If anything is missing, the fix is in
[`moodle/local_ltuse/README.md`](../../moodle/local_ltuse/README.md) — don't work around it.

`MOODLE_URL` and `MOODLE_TOKEN` come from the environment. The repo is public, so they are
never written to a file here. In PowerShell:

```powershell
$env:MOODLE_URL = 'https://moodle.example.org'
$env:MOODLE_TOKEN = '<token>'
```

## 4. Dry run first, always

```bash
python scripts/publish_moodle.py --slug <slug> --dry-run
```

This builds the payload, runs the disclosure gate over it, and prints every call it would
make without sending any. Show the output. If the gate fails, **stop** — nothing may be
published, and the message names the file and the leaked text.

Two refusals are normal and are not bugs to route around:

- *"NOT PUBLISHABLE: no lesson files"* — a backfill placeholder whose content is still
  only its README. It needs [`BACKFILL.md`](../../BACKFILL.md) first.
- *"WITHHELD"* — a quiz whose answer key could not be cleanly separated. Fix the
  `## Answer key` marker rather than passing `--allow-withheld`.

## 5. Publish

```bash
python scripts/publish_moodle.py --slug <slug> --category <id>
```

`--category` is the Moodle course category id. Use the pilot category at stage 7 and the
published one at stage 8; ask the user which if you don't know, and don't assume.

The publish is **idempotent** — every module is addressed by an idnumber derived from its
source file's number, so running it again updates rather than duplicates. Re-running after a
content fix is the normal way to work, not something to avoid.

A module the repo no longer has (a lesson renamed, renumbered or removed) is **retired** at
the end of the publish: hidden and moved into a hidden "Retired" section, never deleted,
and listed under `retire`. Report those lines to the user: one they didn't expect usually
means a file was renamed by accident.

The course is created **hidden**. A human makes it visible when they are ready for
learners.

## 6. Output

```
📦 <slug> — published to Moodle

✅ <n> lesson page(s), <n> asset(s), <n> quiz question(s)
🔗 Course:  <the course URL the script printed>
📍 Stage:   <stage name>

▶ Next:  <the one thing to do next>

⚠ <only if: something was withheld, a video is still unrecorded, or the course is hidden>
```

For a **pilot learner at stage 7**, give them the Moodle course URL — not the
`/review/<slug>/` URL, which contains the answer key and the mentor guide's scoring notes.

At **stage 8**, remind the user to record the published course URL in the module's
`README.md` frontmatter under `external_links: moodle:`. That key is what
`course_stage.py` reads to report the course as Online, so without it the pipeline still
thinks the course is unpublished.

That key is also what turns the course's **completion badge** on and adds its
**certificate** (spec 013): a pilot publish issues neither. So at stage 8 walk the user
through [`process/stages/08-publish.md`](../../process/stages/08-publish.md) steps 5-7 in
order: record the `moodle:` link, suspend the pilot learners' **manual** enrolments, make
the course visible, then run this command again as the delivery and check for the line
`recognition  badge ..., activated; certificate created`.

## Rules

- **Verify, then push.** Never publish a payload that has not passed
  `check_moodle_payload.py`. There is no `--force`, deliberately: once a page is on a
  server learners can reach, a disclosure failure has already happened.
- **One-way.** Never copy content from Moodle back into the repo. Anything edited in
  Moodle is overwritten by the next publish; change the markdown instead.
- **Never send a pilot learner a `/review/` URL.**
- **Don't touch learner data.** Enrolment, grades and attempts are Moodle's; this repo
  holds none of it.
