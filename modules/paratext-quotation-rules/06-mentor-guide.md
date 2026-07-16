# Mentor Guide — Facilitator Notes

> Facilitator-facing. Read this completely before the first session. It describes how to
> create and distribute the fictional training projects, how to stage the `tamba` project for
> each phase of the course, and what text content is required. The learner-facing lessons are
> [`01`](01-what-the-quotation-check-does.md)–[`05`](05-scenario-bank.md); the graded
> assessment is the [quiz](07-quiz.md).

## Overview

This course teaches configuration of Paratext 9.5's Quotation check using four fictional
projects (`tamba`, `runda`, `menda`, `waku`). Learners never touch a real project. The
`tamba` project is used in two staged versions (Phase A blank, Phase B seeded); the other
three are configured from scratch in the [scenario bank](05-scenario-bank.md).

---

## Project Setup: `tamba`

### Step 1 — Create the project in Paratext 9.5

1. In Paratext, open the main **Paratext** menu (☰ at the top-left of the application window) and click **New project...**
2. Set the following fields:

   | Field | Value |
   |-------|-------|
   | Full name | Tamba New Testament |
   | Short name | TAMBA |
   | Primary language | Create a new language entry: name `Tamba`, ISO code `qtb` (ISO 639-3 private-use range `qaa`–`qtz`; `tmb` is assigned to Katimba, a real language — using a private-use code avoids conflicts in Paratext's language database) |
   | Versification | Original (or GNT if Original is unavailable) |
   | Project type | Standard |

3. Click **OK**. Paratext creates an empty project.
4. Leave all quotation settings at their defaults for now — learners will configure them in Lessons 2–3.

### Step 2 — Add the minimum required text

The project must contain text in at least the following passages. All other books and chapters can be empty or contain placeholder text.

| Book | Passage | Required because |
|------|---------|------------------|
| Matthew | 5:1–12 (Beatitudes) | Exercise 4.1 seed #1 — multi-verse speech |
| Matthew | 26:1–75 (arrest and trial) | Lesson 3 check verification — heavy dialogue |
| Luke | 4:14–21 | Exercise 4.1 seed #2 — Isaiah citation |
| John | 3:14–17 | Exercise 4.1 seed #3 — embedded Second level quote |
| Acts | 2:22–28 (Peter's Pentecost speech) | Exercise 4.1 seed #4 — Psalm 16 citation |
| Romans | 1:1–7 | Exercise 4.1 seed #5 — apostrophe-conflict scenario |

For all other books, inserting one or two placeholder verses is sufficient. The unconfigured check in Exercise 1.1 needs enough text to produce a realistic flood of results; five or more books with at least a few verses each will achieve this.

### Step 3 — Apply correct Tamba quotation marks throughout

All dialogue in the required passages must use the correct Tamba quotation characters:

| Level | Opening | Closing |
|-------|---------|----------|
| First level (primary speech) | `“` U+201C | `”` U+201D |
| Second level (embedded speech) | `‘` U+2018 | `’` U+2019 |
| Third level (tertiary, rare) | `“` U+201C | `”` U+201D |

**Apostrophes:** Phase A text should contain **no contractions or possessives** — Tamba’s fictional conventions do not include apostrophes in Phase A, consistent with Exercise 2.3 (which uses Runda as the apostrophe-conflict example, not Tamba). For Phase B, in the text near Romans 1:1 include a word with `’` (U+2019) as an apostrophe — the same character as Tamba’s Second level closing mark. This seeds the configuration-problem scenario in Exercise 4.1, item 5.

**Paragraph-spanning speech:** Matthew 5:3–12 is the key example. Each verse is its own `\p` paragraph. The speech opens with `“` (U+201C) at verse 5:3 and closes with `”` (U+201D) at verse 5:12. No continuation mark appears at the start of verses 5:4–5:12 — Tamba restarts a full open/close pair at each paragraph boundary. This is what the learner configures in Exercise 3.1.

Do **not** configure the Quote marks tab or Rules at this stage. The project should arrive at learners with a blank quotation configuration.

### Step 4 — Stage two versions of the project

The course requires two states of the `tamba` project:

**Phase A — Lessons 1–3 (blank configuration)**
- Quote marks tab: empty
- Quotation Rules: default (unconfigured)
- Text: correct marks, no deliberate errors

Distribute Phase A before learners begin Lesson 1. Learners configure the inventory and rules themselves during Lessons 2–3.

**Phase B — Lesson 4 (configured + seeded errors)**
- Quote marks tab: fully configured per the Tamba settings above (Exercise 2.1 values)
- Quotation types tab: recommended defaults with one customization — Self quote = **Use quote marks** (the Exercise 3.2 result)
- Text: same as Phase A, plus the five deliberate errors from the Lesson 4 Seeding table below

Distribute Phase B (or push an update) before Exercise 4.1. For a group session, the simplest approach is to distribute Phase A, let learners work through Lessons 1–3 themselves, then have the facilitator push the five seed edits to the shared project before Lesson 4 begins.

### Step 5 — Distribute the project

Choose whichever distribution method suits your setup. (Prebuilt project backups accompany this course as `Tamba-A.zip`, `Tamba-B.zip`, `Runda.zip`, and `Menda.zip`.)

| Method | How |
|--------|-----|
| Paratext Send/Receive (shared server) | Create the project on a shared Paratext server. Learners receive it via Send/Receive. Push the Phase B edits to the server before Lesson 4. |
| USB / local file share | Back up the project (**Paratext menu > Advanced > Backup project to file...**), distribute the `.bak` file, and learners restore it (**Paratext menu > Advanced > Restore project from file...**). Provide two `.bak` files: one for Phase A and one for Phase B. |
| Paratext Registry (internet) | Register `TAMBA` as a private project on the Paratext Registry. Learners receive it via Send/Receive using an invited user account. |

For self-paced learners working alone, USB or file share is simplest. For instructor-led groups, a shared Paratext server is recommended so the facilitator can push Phase B edits centrally.

---

## Project Setup: `runda`, `menda`, and `waku` (Scenario bank)

Each scenario-bank project needs enough text to produce meaningful check results, but no deliberate errors need to be seeded — learners configure these projects from scratch.

| Project | Quotation style | Minimum books suggested |
|---------|----------------|-------------------------|
| `runda` | Guillemet outer (`«` / `»`), curly single inner (`‘` / `’`) — Guillemet style (Scenario A). Include words with `’` (U+2019) as apostrophes so learners encounter the apostrophe conflict. | Matthew, Luke, John (dialogue-heavy) |
| `menda` | Double guillemets outer, reversed single guillemets inner: `«...›...‹...»`; Third level returns to `«...»` — include at least one third-level quote (John 19:21) (Scenario B) | John (dialogue-heavy, contains the 19:21 third-level example) |
| `waku` | Em dash as both opener and closer: `—...—`, with em dash continuation mark (Scenario C) | Matthew, Luke, Acts (stretch exercise; Acts is required — the scenario's check steps use its extended multi-paragraph speeches) |

Apply the correct quotation characters for each language throughout the text. Leave all Quote marks tab and Rules settings blank — learners configure them as part of the scenario.

---

## Lesson 4 Seeding

The five result items in Exercise 4.1 must be manually introduced into the Phase B `tamba` project text. Seed each error as follows:

| # | Location | What to do in the text |
|---|----------|------------------------|
| 1 | Matthew 5:3–12 | Delete the closing `”` (U+201D) at the end of verse 5:12. The speech runs from 5:3 to 5:12 as a single First level quote; removing the closing mark creates an unclosed-quote result reported at Matthew 5:3. |
| 2 | Luke 4:18 | Insert a stray `“` (U+201C) immediately before the first word of the Isaiah citation. Tamba does not mark narrator scripture citations; the stray mark mimics a translator adding a dialogue opener by mistake. |
| 3 | John 3:16 | Replace the Second level opening mark `‘` (U+2018) with a straight `"` (U+0022) at the start of Jesus's embedded statement within his speech to Nicodemus. |
| 4 | Acts 2:25–28 | Mark Peter's Psalm 16 citation as a single continuous Second level block: add `‘` (U+2018) at the start of verse 2:25 and `’` (U+2019) at the end of verse 2:28. Do **not** close and reopen at the intermediate paragraph breaks (make sure the text has at least one \p break inside 2:25–28 — the seed depends on it). Tamba restarts marks at every paragraph, so the check reports the span as unclosed; learners identify it as a real error and add the close/reopen pairs. |
| 5 | Romans 1:1 | In a possessive or contraction in the verse text (e.g., *God’s word*), use `’` (U+2019) as the apostrophe. Phase A text has no apostrophes; this one creates the conflict between the Second level closing mark (U+2019) and a word-medial apostrophe, generating a spurious quotation result. |

After seeding, run the Quotation check and confirm all five items appear in the results before distributing the project to learners. The check messages in the Exercise 4.1 table are representative wording — note the exact message text Paratext reports for each seed and update that table to match before distributing the course.

---

## General Facilitation Notes

- **Discovery-first ordering:** Each lesson shows the answer key *after* the discovery prompts, not before. Encourage learners to write down their prediction before scrolling to the expected configuration.
- **Scenario C (Waku):** The em-dash scenario is the hardest. It is appropriate as a stretch exercise or for learners who have completed Lessons 1–4 confidently and want a challenge.
