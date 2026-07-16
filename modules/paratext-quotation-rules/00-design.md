# Course Design Document

> **Retro-fit note.** This design document was reverse-engineered from content that was
> drafted and Cypher-delivered *before* the production pipeline existed. It exists to bring
> the course into the pipeline (opt into CI, document objectives ⇄ assessment alignment). The
> content already exists in the numbered lesson files; this doc records the design those
> lessons *actually* implement. It has **not** yet been through SME fact-check (stage 5) or
> design approval (stage 2).

## Course overview

| Item | Description |
| --- | --- |
| **Title** | Configuring Quotation Rules in Paratext |
| **Competencies addressed** | Translation Tools |
| **Target outcome level** | With Assistance |
| **SME(s) consulted** | _(pending — retro-fit; SME fact-check not yet run)_ |
| **Design status** | Draft |
<!-- On approval (stage 2), the Design Approver replaces the line above with:
     | **Design status** | Approved by <name> on <YYYY-MM-DD> | -->

Source component: `competencies/translation-tools.md` §2.0 Translation Tools —
*Advanced Beginner: "Can use translation tools and troubleshoot issues that arise."* The
course takes a learner who can navigate Paratext to the point of independently configuring
and troubleshooting one specific check (Quotations), which is squarely "With Assistance."

## Learning objectives

| # | Objective | Source | Assessed by |
| --- | --- | --- | --- |
| 1 | Learner can explain why the Quotation check produces no trustworthy results before it is configured, and name the two inputs it needs (inventory + rules). | Translation Tools §2.0 (use/troubleshoot) | Quiz §1; Lesson 1 exercise 1.1 |
| 2 | Learner can enter the correct opening/closing quote-mark characters for each nesting level on the Quote marks tab and verify them via the Example preview. | Translation Tools §2.0 | Quiz §2; Lesson 2 exercises 2.1–2.2 |
| 3 | Learner can configure the Quote Continuer at new paragraph and resolve the word-medial apostrophe conflict in Language Settings. | Translation Tools §2.0 (troubleshoot) | Quiz §2; Lesson 2 exercise 2.3 |
| 4 | Learner can configure each of the seven Quotation types for a given language's conventions and distinguish recommended vs. custom settings. | Translation Tools §2.0 | Quiz §3; Lesson 3 exercises 3.1–3.2 |
| 5 | Learner can classify a check result as a real error or a configuration problem and take the correct corrective action for each. | Translation Tools §2.0 (troubleshoot) | Quiz §4; Lesson 4 exercise 4.1 |
| 6 | Learner can work a result set to zero actionable errors, book by book, without silencing correct text. | Translation Tools §2.0 | Quiz §4; Lesson 4 exercise 4.2 |
| 7 | Learner can apply the full inventory → rules → check → triage workflow independently to an unfamiliar language, including edge cases (guillemets, reversed nesting, em-dash). | Translation Tools §2.0 | Quiz §5; Scenario bank A/B/C |

## Module breakdown

One row per numbered lesson file. Each stays ≤ 90 minutes.

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-what-the-quotation-check-does.md` | What the check does and why configuration matters | 1 | 30 |
| `02-setting-up-quote-marks.md` | The Quote marks tab; nesting levels; continuer; apostrophe conflict | 2, 3 | 75 |
| `03-configuring-quotation-types.md` | The Quotation types tab; seven types; recommended vs. custom | 4 | 60 |
| `04-interpreting-and-clearing-the-check.md` | Triage: real error vs. config problem; reaching zero | 5, 6 | 75 |
| `05-scenario-bank.md` | Applied practice: three unfamiliar languages (Runda, Menda, Waku) | 7 | 90 |
| `06-mentor-guide.md` | Facilitator notes: project setup, phasing, error seeding | — | — |
| `07-quiz.md` | Assessment | 1–7 | 20 |
| `08-video-script.md` | Recording script for Cypher upload | — | — |
| **Total learner seat time** | | | **~350** (course runs longer than the 270-min guide; each lesson stays under the 90-min cap) |

## Assessment plan

A 15-question quiz (80% = 12/15 to pass) covers objectives 1–7, grouped into five sections
matching the five lessons — a mix of recall, scenario application, and true/false. The
scenario bank (three independent language scenarios, ~30 minutes each) exercises objective 7
as applied practice; Scenario C (Waku em-dash) is an optional stretch. Each lesson also
carries a short set of formative "check your understanding" questions inline; the graded
assessment is the consolidated quiz.

## SME knowledge notes

_Retro-fit — no formal SME interview was conducted for this design. The content encodes the
following field knowledge (to be confirmed at SME fact-check, stage 5):_

- The apostrophe/closing-quote conflict (U+2019 serving as both) is resolved in **Language
  Settings → Other Characters → Word-medial punctuation**, *not* in the Quotation Rules
  dialog. This is the single most common real-world stumbling block.
- The **Quotation types** check is a separate, administrator-enabled check that only checks
  first-level quotes in non-Deuterocanonical books.
- Same-character open/close (em-dash languages like the fictional Waku) cannot always be
  fully resolved by configuration; residual results must be documented in Project Notes for
  the consultant rather than edited away.
- The fictional languages use ISO 639-3 private-use codes (`qaa`–`qtz`) to avoid colliding
  with real languages in Paratext's language database.
- Recommended-settings defaults shown in the course should be re-verified against the
  learner's actual Paratext 9.5 build before being relied on as exact.
