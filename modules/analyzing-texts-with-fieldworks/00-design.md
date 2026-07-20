# Course Design Document

> **Before proceeding:** Copy this file into your new course folder as `00-design.md`. Content drafting must not begin until this document is approved by a human reviewer.

## Course overview

| Item | Description |
| --- | --- |
| **Title** | Analyzing Texts with FieldWorks |
| **Competencies addressed** | Grammar Tools |
| **Target outcome level** | With Assistance |
| **SME(s) consulted** | Jenni Beadle (SIL) — authors learner-facing tutorials; adapting Kent's facilitator-style teaching-plan source material (`lingtran.net/FLEx-8`) into this repo's self-paced tutorial convention |
| **Design status** | Approved by Jenni Beadle on 2026-07-16 |

## Learning objectives

One row per objective. The `Source` column names the specific criterion or activity ladder level from the matching `competencies/<slug>.md` descriptor that this objective draws from.

| # | Objective | Source | Assessed by |
| --- | --- | --- | --- |
| 1 | Learner can explain how FLEx's work areas relate to grammatical analysis and set up a project with an appropriate vernacular/analysis writing-system pair | Grammar Tools, Grammar Description — Advanced Beginner ("identify and describe grammatical components") | Quiz + Module 1 activity |
| 2 | Learner can interlinearize a text in FLEx, creating and approving lexical entries as needed | Grammar Tools, Interlinear Tools — Advanced Beginner/Practitioner ("able to use interlinear tools"; "test interlinear tools in a language pair you understand") | Quiz + Module 2 activity |
| 3 | Learner can use FLEx's parser to generate and correct morphological analyses for a text | Grammar Tools, Automated Parsing — Advanced Beginner ("apply parsing methods to separate words into morphemes") | Quiz + Module 3 activity |
| 4 | Learner can use the Word Analyses / Concordance views to review, approve, and correct analyses across a text | Grammar Tools, Automated Parsing — Practitioner | Quiz + Module 3 activity |
| 5 | Learner can generate a text chart to examine discourse-level patterns in an interlinearized text | Grammar Tools, Grammar Description / Interlinear Tools crossover (Text Charting) | Quiz + Module 3 activity |

## Module breakdown

One row per planned numbered lesson file. Each row stays ≤ 90 minutes. The total must sum to the "Assessment plan" estimate below.

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-module-1.md` | FLEx Fundamentals & Project Setup (areas of FLEx, opening/closing, writing systems, vernacular vs. analysis) | 1 | 45 |
| `02-module-2.md` | Interlinearizing Texts (baseline, Gloss/Analyze tabs, creating lexical entries on the fly) | 2 | 60 |
| `03-module-3.md` | Parsing, Concordance & Text Charting | 3, 4, 5 | 60 |
| `04-quiz.md` | Assessment | 1–5 | 15 |
| **Total learner seat time** | | | **180** |

No separate scenario-bank/mentor-guide file is planned — following the pattern from `coretech-os-basics`, scenario-style activities are embedded directly in each module file. Revisit if the SME interview surfaces material substantial enough to warrant its own file.

## Assessment plan

A ~15-question quiz (80% = 12/15 to pass) covering all 5 objectives, weighted toward Modules 2 and 3 (interlinearization and parsing/charting) since those carry the most content. Hands-on practice comes from activities embedded in each module (interlinearizing a real text, running the parser, generating a chart) rather than a separate scenario bank.

## SME knowledge notes

**Source material:** `https://lingtran.net/FLEx-8` — a 15-lesson FieldWorks course. The 8 lexicon/dictionary-building lessons (Lexicon Edit 1–3, Browse, Import, Bulk Edit, Word Collection, Publication, Styles) are **out of scope** for this course (they belong to the `Lexical Tools` competency and the separate `dictionary-making-lexicography` stub). This course draws only on the grammar/interlinear/parsing lessons: Introduction, Project Management, Interlinearization, Parsing & Concordance, and Text Charting.

**⚠️ Version currency — needs SME verification before content is drafted:** the source lessons are written for **FLEx 8** ("This material is based upon version 8 of FLEx" — stated explicitly in the Introduction lesson). The version has already moved twice just during this design process — from 8, to the SME's installed 9.2.11, to a 9.3.x update FLEx itself is now prompting to install. Given how fast this moves, **lesson content should not hard-code a specific version number** (avoid "in FLEx 9.2.11, click...") — describe procedures generically ("in FLEx, click...") and have the SME verify each procedure against whichever version is actually installed at content-drafting time, not against this design doc or the FLEx 8 source. **Update:** the SME has since installed the newly-released **FLEx 9.3.9** (full install). No visual/layout changes observed so far, but the SME has been told a few commands have moved to different menus.

**Confirmed against the live 9.3.9 UI by the SME (all five checks complete):**
- `File → Project Management → Restore a Project` / `Backup` — **unchanged**.
- `File → Project Management → FieldWorks Project Properties` — the **Writing Systems tab no longer exists there**. Writing-system setup has moved out of Project Properties entirely and split into two separate commands: **"Set up Vernacular Writing Systems..."** and **"Set up Analysis Writing Systems..."**, reachable two ways — via `Tools → Configure` or via `Format → Set up Vernacular/Analysis Writing Systems...`. Two distinct dialogs (one per writing-system role) rather than one shared "Writing Systems" tab with an Add button. Module 1's writing-system setup content must be written against this new two-command structure, not the FLEx 8 "Writing Systems tab" workflow — this is a real, confirmed change, not a guess.
- Parser commands (`Parse menu → Parse Words in Text` / `Try a Word`) — **unchanged**, but confirmed these must be run from the **Texts & Words** area, **Interlinear Texts** view (matches what the FLEx 8 source already said — this is a confirming note, not a change).
- Inserting a new text (`Insert menu → New Text`) — **unchanged**.
- Configure submenu items for displayed columns (Word Analyses view and Interlinear view) — **unchanged**.

Only the writing-systems setup path needs rewriting for 9.3.9; everything else the course draws on for Modules 2 and 3 (interlinearization, parsing, text charting) checks out against the FLEx 8 source. Module 1's Project Management content is the one section needing a real rewrite of the procedure, not just a light copy-edit. The source is also written as an **in-person workshop facilitator script** ("Have the participants finished the rest of sentence...") — Kent's authoring style for teaching plans handed to learners — and every lesson needs rewriting into the direct-to-learner, self-paced tutorial style Jenni writes for this repo, not just reformatting. Before `module-author` drafts final content, the SME should verify: menu paths and dialog names (e.g. File → Project Management → FieldWorks Project Properties; Parse menu → Parse Words in Text / Try a Word) against the current version, whether the "Simple" and "Sena 3" sample projects are still available/appropriate practice data, and whether the MSKLC/Keyman keyboard-setup steps in Project Management still match current FLEx behavior (cross-reference: `coretech-os-basics` Module 2 already covers OS-level input languages generally — this course only needs the FLEx-specific writing-system/keyboard link-up, not a repeat of that material).

**Procedures confirmed from the source (concepts likely stable across FLEx 8→9, pending SME UI check):**
- **Interlinearization:** add text to the Baseline tab; Gloss tab vs. Analyze tab distinction; approving a parser/lexicon suggestion (green checkmark) vs. creating a new lexical entry inline while interlinearizing; splitting a word into its morphemes (e.g. *walked* → *walk* + *-ed*); keyboard shortcuts (Tab, Enter, Shift+Enter, Ctrl+J, Ctrl+S, Ctrl+M) for moving through a text quickly. **Confirmed correction (SME, live 9.3.9):** the source's "right-click an ambiguous word to pick the correct sense" instruction is outdated and produces a confusing menu in current FLEx. The correct procedure is to click the down arrow beside the Lex. Entries line and choose the correct sense from there.
- **Parsing & Concordance:** the parser suggests morpheme breakdowns based on existing lexicon entries; "Try a Word" lets you test the parser against a word without committing it to a text; the Word Analyses view lists every word in a text with its occurrence count and candidate analyses, and lets you approve/reassign analyses across the whole text (a concordance-style correction workflow).

  **Confirmed (SME walkthrough, live 9.3.9): the three related `Texts & Words` list/search views, fully distinguished:**
  - **Word List Concordance** — an unfiltered list of every wordform in the in-scope text(s) alongside its gloss. No search criteria; just a full list. (Already used in Module 2 for reviewing interlinearized work.)
  - **Concordance** — a targeted *search*. Choose which "line" to search (Baseline, Word, Morphemes, Lex. Entry, Lex. Gloss, Lex. Gram. Info., Word Gloss, Word Cat., Free Translation, Literal Translation, Note, or Tagging), a writing system, and the search text, with match options (case/diacritics, anywhere/whole item/at start/at end, or regular expressions). Results list as Ref + Occurrence. Scope (which texts are searched) is controlled by the **Choose Texts** dialog — texts are organized by genre category, and only the currently-open text is checked by default, but multiple or all texts can be checked to search across the whole project at once.
  - **Complex Concordance** — a structured query builder rather than a single-string search: combine multiple criteria (e.g. word form "cat" AND category "N") using Insert-options building blocks (Morph, Word, Tag, OR) to construct compound searches more precise than plain Concordance's single-line-single-string match.
- **Text Charting:** the Text Chart lays out a discourse text with one clause per row and phrases spread across columns, so patterns (e.g. a particle that always appears in a certain clause position) become visible at a glance — this is the discourse-analysis payoff of having interlinearized a text.

  **Confirmed (SME walkthrough, live 9.3.9):** the Text Chart tab (alongside Info/Baseline/Gloss/Analyze/Tagging/Print View) uses a selectable column template — the "Default" template groups columns as **Pre-nuclear** (Outer, Inner), **Nucleus** (Subject, Verb, Object/Complement), **Post-nuclear** (Inner, Outer), plus **Notes**. The first time you chart a text, FLEx shows a warning that you may need to change the column order to fit the text's actual clause pattern. To place a word into a column: **select the word/phrase in the word row, then click that column's insert button** (each column header has a button with an up-arrow, and a tooltip like "Insert selected text into the Subject column") — it is not drag-and-drop.

  **Confirmed (official FLEx help topic, "Text Chart tab overview," pasted in full by the SME):** Text Charting is based on the Stephen H. Levinsohn discourse analysis model and is explicitly designed for people **without extensive morphemic knowledge** — glossing the text (Gloss tab) is enough to start charting; full morpheme-level analysis is not required first. Key cautions to carry into the lesson:
  - **Gloss (or analyze) the text before inserting any words into the chart** — every word needs at least a Word Gloss.
  - **Editing the Baseline tab *after* words are already charted removes those words from the chart** — you'd have to reinsert them. Get the baseline spelling right before charting, not after.
  - **Templates (column names/order) should not be changed once used in a chart** — pick/verify the template before you start, not mid-chart.
  - Keep genuinely separate words unlinked (clitics, individual words in a phrase) when charting — link only true idioms.
  - You can still finish morpheme-level interlinearization later in the Analyze tab without breaking an existing chart, as long as you don't change spellings or phrase links afterward.
  - Undo works in the chart, but only until you leave the tab or refresh.
  - This is a rich feature with many advanced marking options (dependent/speech/song clause marking, preposed/postposed marking, grammatical-information marking) — **out of scope for this With Assistance-level module**; the lesson should cover the basic insert-into-column workflow only, not the full discourse-marking toolkit.

  **Still unconfirmed:** the exact interaction for approving/reassigning a candidate analysis in the **Word Analyses** view (the source's "right-click Analysis Candidate → User Opinion → approve" instruction is unverified, and the sense-disambiguation right-click instruction elsewhere in this same source turned out to be outdated — don't assume this one is still accurate). The SME has only glossed their sample project so far, not reached the analysis/parsing stage, so this couldn't be checked yet. **Lesson content should describe Word Analyses' purpose and value in general terms (viewing/selecting candidate analyses) without asserting a specific unverified click-path**, and the Challenge exercise should treat it as exploratory ("open it and see what's there") rather than a precise numbered procedure.
- **Project Management (foundational only):** a writing system = a specific language + script; the vernacular writing system is the language being researched, the analysis writing system is the language of the glosses/notes; each writing system pairs a language with a font and keyboard configuration.

**Confirmed: the old "Simple" sample project is not good practice data.** The SME downloaded and reviewed it directly. It uses English as *both* the vernacular and analysis writing system, which forces contrived synonym-substitution glosses (dog→canine, cat→feline, giraffe→long-necked animal) to simulate a "foreign" vernacular. Amusing for English speakers, but not representative of real interlinearization work and not always easy to find a suitable substitute gloss for. **Decision: lessons should have the learner bring in their own short passage in a language they actually work with (or a placeholder they define), not depend on the old "Simple"/"Sena 3" sample projects** — confirmed for Module 2 (`02-module-2.md`) and carrying forward as the default for Module 3's sample-data needs too, unless a specific exercise genuinely requires shared/reproducible sample data.

Do not invent details beyond what's above — anything not confirmed here should go back to the SME (or the current FLEx 9.2.11 UI directly) before it's written into lesson content.

---

### How this document works

1. **Read** the competency descriptor(s) under `competencies/` for each competency listed above. Lift your learning objectives from their observable criteria and activity ladders, not from thin air.
2. **Interview the SME** before drafting objectives or module breakdown. Ask: real field cases they've seen, what learners most commonly get wrong, what "good" looks like at your target outcome level, any tool-version specifics. Record answers in the SME knowledge notes section.
3. **Enforce the 90-minute rule.** Each numbered module file (01-, 02-, …) has a documented estimated duration, stated at the top as `**Estimated time:** X minutes`, and must not exceed 90 minutes. If your content can't fit, split it into another file rather than overflowing — update this table and the `module-author` will implement it.
4. **Align objectives with assessment.** Every objective in the table above must map to at least one quiz question or scenario. Every quiz question and scenario must trace back to an objective — no orphan assessments.
5. **Outcome-level verb choice.** "Has knowledge" objectives use recognize/explain language; "With Assistance" objectives use perform/configure language.
6. **Hand off.** Once approved, this document becomes a contract: the `module-author` and `quiz-writer` agents will draft content and assessment to fulfill it, not freelance or add unstated competencies.
