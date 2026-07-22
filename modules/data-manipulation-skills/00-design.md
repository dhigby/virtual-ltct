# Course Design Document

> **Retro-fit note.** This design document was reverse-engineered from content that was
> drafted and delivered (once, at AILTW 2026) before the production pipeline existed. It
> exists to bring the course into the pipeline (opt into CI, document objectives ⇄
> assessment alignment). The content already exists in `01-using-the-regex-pal-user-menu.md`;
> this doc records the design that lesson actually implements. It has **not** yet been
> through SME fact-check (stage 5) or a fresh design approval (stage 2) — see SME knowledge
> notes below for what's actually been verified.

## Course overview

| Item | Description |
| --- | --- |
| **Title** | Data manipulation skills |
| **Competencies addressed** | Data Conversion |
| **Target outcome level** | With Assistance |
| **SME(s) consulted** | Jenni Beadle (SIL) — author of the original lesson; content verified against real RegEx Pal screenshots during this retro-fit pass |
| **Design status** | Draft (retro-fit — see note above) |

## Learning objectives

| # | Objective | Source | Assessed by |
| --- | --- | --- | --- |
| 1 | Use Find operations in the User Menu to safely locate formatting problems without changing any text | Data Conversion, Learner rung ("advanced search and replace in text") | Challenge Step 1 + mentor check-in |
| 2 | Use Count operations to measure how often a pattern appears in a project | Data Conversion, Learner/Advanced Beginner boundary | Challenge Step 2 + mentor check-in |
| 3 | Use Extract operations to pull matching content out for review | Data Conversion, Learner rung | Challenge Step 3 + mentor check-in |
| 4 | Run a Replace operation safely, reviewing each change as it's presented, with mentor support if unsure | Data Conversion, Advanced Beginner rung ("replace strings according to context") | Challenge Step 4 + mentor check-in |

## Module breakdown

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-using-the-regex-pal-user-menu.md` | Using the RegEx Pal User Menu (Find/Count/Extract/Replace) | 1, 2, 3, 4 | 55 |
| **Total learner seat time** | | | **55** |

Single-lesson course — no scenario bank, mentor guide, quiz, or video script planned at this
time (see Assessment plan below for why).

## Assessment plan

This lesson uses **mentor check-ins embedded directly in the lesson** (after each of the four
Challenge steps) rather than a separate quiz file — the skill being taught is procedural and
best assessed by a mentor reviewing the learner's actual results and reasoning, not by
recall questions. No `NN-quiz.md` is planned unless this expands into a multi-lesson course
(see "Possible future expansion" in `README.md`), at which point a proper quiz and/or
scenario bank should be added.

## SME knowledge notes

**Delivery history:** written for **AILTW** (An Indian Language Technology Workshop) 2026, after
earlier attempts over several years to teach regex directly to African colleagues were set
aside as not working. Delivered once; no learner feedback has been gathered yet, so
effectiveness is unconfirmed.

**Confirmed against live RegEx Pal by the SME (this retro-fit pass):**
- "Find close codes preceded by a space" is a real, existing User Menu entry (confirmed via
  screenshot of the full menu list).
- "Replace missing space after `\v`" is a real entry, and its actual behavior is
  **interactive**: it steps through the selected book(s) and stops at each match, showing the
  project, reference, Find pattern (`\\v(\d)`), and Replace pattern (`\\v \1`) in a dialog with
  **Yes / No / Yes To All / Cancel** buttons. This corrected an ambiguous/self-contradicting
  draft instruction in the original content (which read like an unfinished note-to-self:
  "wait, for this Replace the corresponding Find is...").
- There is no separate dedicated *User Menu* "Find" entry paired with "Replace missing space
  after `\v`" — but `Find` and `Replace` are actually two modes on the **Tools** menu
  (`Tools > Find`, Ctrl+F, and `Tools > Replace`, Ctrl+H) that run against the same loaded
  pattern. Selecting the Replace item from the User Menu loads its Find/Replace pattern pair
  and switches to Replace mode; switching to `Tools > Find` first runs that same pattern as a
  preview (no changes made) before switching back to `Tools > Replace` to apply it
  interactively. The lesson now instructs learners to do both: preview via Find, then apply
  via the interactive Replace dialog — not an either/or.
- `Tools > Choose Books` scoping behaves normally for this operation (the SME's test run
  processed "all books" only because all books happened to be selected in Choose Books at
  the time, not because the operation ignores that setting).

**`userMenu.txt` (Phil's extended User Menu file) is now attached to this module folder.**
Installing it (copy into `My Paratext 9 Projects` or `My Paratext 8 Projects`, depending on
installed version) is a prerequisite step in the lesson — without it, none of the lesson's
four named operations (Find close codes preceded by a space, Count all cap words, Extract
all footnotes, Replace missing space after `\v`) would exist in a learner's own User Menu.
`Tools > Edit User Menu...` is a distinct feature (for authoring individual new entries one
at a time) and is not how this file gets installed.

**Known pending items (tracked in `README.md`):**
- Five screenshots originally embedded in the lesson are inaccessible (broken Notion export
  links); new ones are being retaken by the SME.
- The companion video (linked in `README.md`'s `external_links`) has a known, unfixed error
  around 4:36-4:48 — noted as an inline TODO in the lesson file, not exposed to learners.

Do not invent details beyond what's confirmed above — anything about RegEx Pal's actual UI
behavior not covered here should be checked live before being added to the lesson.
