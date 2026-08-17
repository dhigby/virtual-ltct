# Mentor Guide — Facilitator Notes

> Facilitator-facing. Read this completely before the first session. It describes how to
> create and distribute the fictional training projects, how to stage the `tamba` project for
> each phase of the course, and what text content is required. The learner-facing lessons are
> [`01`](01-what-the-quotation-check-does.md)–[`05`](05-scenario-bank.md); the graded
> assessment is the [quiz](07-quiz.md).

## Overview

This course teaches configuration of Paratext 9.5's Quotation check using five fictional
projects (`tamba`, `runda`, `velna`, `menda`, `waku`). Learners never touch a real project. The
`tamba` project is used in two staged versions (Phase A blank, Phase B seeded) across Lessons
1–4. `runda` is a second project configured hands-on in Lesson 2, alongside `tamba`, to practice
a different character set and the apostrophe conflict. The remaining three (`velna`, `menda`,
`waku`) are configured from scratch, independently, in the [scenario bank](05-scenario-bank.md)
— `velna` deliberately reuses Runda's guillemet-and-apostrophe convention under a new name and
project, so Scenario A tests the same skill on a project the learner has not already configured.

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
| Matthew | 5:1–7:29 (the Sermon on the Mount) | Exercise 4.1 seed #1 — multi-verse speech. This is one continuous First level quotation from 5:3 to 7:28 (Jesus's teaching does not stop at the Beatitudes; the whole sermon must be built and correctly continuer-marked through chapter 7, since that is where the check will trace an unclosed quote to) |
| Matthew | 26:1–75 (arrest and trial) | Lesson 3 check verification — heavy dialogue |
| Luke | 4:14–21 | Exercise 4.1 seed #2 — Isaiah citation |
| John | 3:14–17 | Exercise 4.1 seed #3 — embedded Second level quote |
| Acts | 2:22–28 (Peter's Pentecost speech) | Exercise 4.1 seed #4 — Psalm 16 citation |
| Romans | 1:1–7 | Exercise 4.1 seed #5 — apostrophe-conflict scenario |

For all other books, inserting one or two placeholder verses is sufficient. The unconfigured check in Exercise 1.1 needs enough text to produce a realistic flood of results; five or more books with at least a few verses each will achieve this.

### Step 3 — Apply correct Tamba quotation marks throughout

All dialogue in the required passages must use the correct Tamba quotation characters:

| Level | Opening | Quote Continuer at new paragraph | Closing |
|-------|---------|-----------------------------------|----------|
| First level (primary speech) | `“` U+201C | `“` U+201C (same as opening) | `”` U+201D |
| Second level (embedded speech) | `‘` U+2018 | *(blank — no continuer)* | `’` U+2019 |
| Third level (tertiary, rare) | `“` U+201C | *(blank — no continuer)* | `”` U+201D |

**Apostrophes:** Phase A text should contain **no contractions or possessives** — Tamba’s fictional conventions do not include apostrophes in Phase A, consistent with Exercise 2.3 (which uses Runda as the apostrophe-conflict example, not Tamba). For Phase B, in the text near Romans 1:1 include a word with `’` (U+2019) as an apostrophe — the same character as Tamba’s Second level closing mark. This seeds the configuration-problem scenario in Exercise 4.1, item 5.

**Paragraph-spanning speech:** Matthew 5:3–7:28 (the whole Sermon on the Mount) is the key example — not just the Beatitudes. Each verse is its own `\p` paragraph. The speech opens with `“` (U+201C) at verse 5:3. Because Tamba's First level uses a Quote Continuer, every following paragraph through 7:27 also opens with `“` (U+201C) — the same character, repeated to signal the speech continues from the previous paragraph. Only verse 7:28, the narrator's aside ("when Jesus had finished saying these things...") that ends the sermon, also carries the closing mark `”` (U+201D) at its end. This is what the learner configures in Exercise 2.1 (Quote marks tab — the continuer character) and Exercise 3.2 (Quotation types tab — Continued quotation = Use quote marks). Lesson 3's discovery exercise (Exercise 3.2, Step "Check your work") only asks the learner to confirm 5:4–5:11 aren't falsely flagged — it does not require the whole sermon to be built yet — but the full 5:3–7:28 span must exist correctly-marked in the Phase A/B baseline before Lesson 4, since Exercise 4.1 seed #1 depends on 7:28 being the true close.

**SME verification — confirmed:** a Paratext 9.5 run on a built `tamba` project confirmed the Quotation types "Continued quotation" setting does govern whether Paratext expects a configured continuer character at a paragraph break, independent of Second/Third level's close-and-reopen behavior. It also surfaced two behaviors the original design hadn't accounted for, both now reflected in the Lesson 4 Seeding table and Exercise 4.1 below: (1) an unclosed First level quotation is reported at its opening verse with wording that traces forward to wherever the next real closing mark happens to fall, rather than a fixed nearby verse; (2) a single corrupted Second level mark can cascade into multiple linked results on either side of the break rather than one isolated result. Re-verify both if the seeded text is rebuilt from scratch, since exact locations depend on the specific project build.

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

## Project Setup: `runda` (Lesson 2)

`runda` is a second fictional project, alongside `tamba`, used hands-on in Lesson 2
(Exercises 2.2–2.3) to practice a different character set (guillemets) and the word-medial
apostrophe conflict. It must be installed before learners start Lesson 2, not just before the
scenario bank.

| Field | Value |
|-------|-------|
| Quotation style | Guillemet outer (`«` / `»`), curly single inner (`‘` / `’`) |
| Minimum books suggested | Matthew, Luke, John (dialogue-heavy) |

Include words using `’` (U+2019) as an apostrophe somewhere in the text, so Exercise 2.3's
word-medial punctuation conflict has real examples to resolve. Leave the Quote marks tab and
Quotation Rules blank — learners configure both during Lesson 2. A prebuilt backup accompanies
this course as `Runda.zip`.

---

## Project Setup: `velna`, `menda`, and `waku` (Scenario bank)

Each scenario-bank project needs enough text to produce meaningful check results, but no deliberate errors need to be seeded — learners configure these projects from scratch, independently, having never seen them before.

| Project | Quotation style | Minimum books suggested |
|---------|----------------|-------------------------|
| `velna` | Guillemet outer (`«` / `»`), curly single inner (`‘` / `’`) — Guillemet style (Scenario A). Include words with `’` (U+2019) as apostrophes so learners encounter the apostrophe conflict. | Matthew, Luke, John (dialogue-heavy) |
| `menda` | Double guillemets outer, reversed single guillemets inner: `«...›...‹...»`; Third level returns to `«...»` — include at least one third-level quote (John 19:21) (Scenario B) | John (dialogue-heavy, contains the 19:21 third-level example) |
| `waku` | Em dash as both opener and closer: `—...—`, with em dash continuation mark (Scenario C) | Matthew, Luke, Acts (stretch exercise; Acts is required — the scenario's check steps use its extended multi-paragraph speeches) |

Apply the correct quotation characters for each language throughout the text. Leave all Quote marks tab and Rules settings blank — learners configure them as part of the scenario.

**Asset gap:** unlike `tamba`, `runda`, and `menda`, no prebuilt backup exists yet for `velna` or
`waku` — a facilitator must build both from the tables above before the scenario bank can be
distributed. `velna` is new as of this revision (it replaces `runda`'s prior role in Scenario A,
so the two must be distinct projects — do not reuse the `runda` backup for `velna`).

---

## Lesson 4 Seeding

The five underlying issues in Exercise 4.1 must be manually introduced into the Phase B `tamba` project text. Seed each error as follows. Note that seed #3 produces **three** separate check results (at 3:10, 3:16, and 3:21) from one broken mark — confirmed in a Paratext 9.5 run — so the project will show **seven** total results from these five seeds, not five.

| # | Location | What to do in the text |
|---|----------|------------------------|
| 1 | Matthew 5:3–7:28 | Delete the closing `”` (U+201D) at the end of verse 7:28 only — leave the First level continuer `“` (U+201C) in place at the head of every paragraph from 5:4 through 7:27. The speech runs from 5:3 through 7:28 (the whole Sermon on the Mount), linked paragraph to paragraph by the continuer; removing only the final closing mark creates an unclosed-quote result reported back at Matthew 5:3. In practice the check reports this as "Closing quote mark is possibly missing before verse [X]," where `[X]` is wherever the next real closing mark in the project happens to fall — confirmed in a Paratext 9.5 run to trace all the way to 7:28. |
| 2 | Luke 4:18 | Insert a stray `“` (U+201C) immediately before the first word of the Isaiah citation. Tamba does not mark narrator scripture citations; the stray mark mimics a translator adding a dialogue opener by mistake. |
| 3 | John 3:16 | Replace the Second level opening mark `‘` (U+2018) with a straight `"` (U+0022) at the start of Jesus's embedded statement within his speech to Nicodemus. This one corrupted character breaks quotation tracking on both sides of it: confirmed in a Paratext 9.5 run to produce three linked results — "Quote opened; see following message for error" at 3:10 (the quotation's true start), "Expected continuers [“] are missing OR quote not closed; see preceding message" at 3:16 (the corrupted mark itself), and "Closing quote mark [”] found without matching opening" at 3:21 (the orphaned close). Fixing the single character at 3:16 and re-running clears all three. |
| 4 | Acts 2:25–28 | Mark Peter's Psalm 16 citation as a single continuous Second level block: add `‘` (U+2018) at the start of verse 2:25 and `’` (U+2019) at the end of verse 2:28. Do **not** close and reopen at the intermediate paragraph breaks (make sure the text has at least one \p break inside 2:25–28 — the seed depends on it). Tamba restarts marks at every paragraph, so the check reports the span as unclosed; learners identify it as a real error and add the close/reopen pairs. |
| 5 | Romans 1:1 | In a possessive or contraction in the verse text (e.g., *God’s word*), use `’` (U+2019) as the apostrophe. Phase A text has no apostrophes; this one creates the conflict between the Second level closing mark (U+2019) and a word-medial apostrophe, generating a spurious quotation result. |

After seeding, run the Quotation check and confirm all seven results (five seeds, with #3 producing three) appear before distributing the project to learners. The Exercise 4.1 table already reflects verified Paratext 9.5 message wording for this build; if you rebuild the project from scratch and the wording or locations differ, update that table to match before distributing the course.

---

## General Facilitation Notes

- **Discovery-first ordering:** Each lesson shows the answer key *after* the discovery prompts, not before. Encourage learners to write down their prediction before scrolling to the expected configuration.
- **Scenario C (Waku):** The em-dash scenario is the hardest. It is appropriate as a stretch exercise or for learners who have completed Lessons 1–4 confidently and want a challenge.
