---
title: Configuring Quotation Rules in Paratext
slug: paratext-quotation-rules
target_outcome_level: With Assistance
competencies:
  - Translation Tools
content_type: content
---

# Configuring Quotation Rules in Paratext
### An Interactive Course for Intermediate Users

**Version 1.0** | June 2026

---

## About This Course

**Audience:** Translators and translation helpers who are comfortable navigating Paratext but have not yet configured quotation checking for a project.

**Goal:** By the end of this course you will be able to open an unconfigured project, identify the quotation characters a language uses, enter them correctly into the Quote marks tab, configure the checking rules, run the check, and interpret the results.

**Format:** Each lesson presents a goal, a starting state, a set of discovery prompts (questions to answer before you act), and then the expected configuration you can compare against.

**Estimated time:** Lessons 1–4 run 3–4 hours total; the scenario bank adds 1–2 hours. Each individual lesson is capped at 90 minutes — see the duration header at the top of each file.

---

## Course package

This course follows the repo's [content package structure](../../process/PROCESS.md). Work through the numbered files in order:

| File | What it is |
| --- | --- |
| [`01-what-the-quotation-check-does.md`](01-what-the-quotation-check-does.md) | Lesson 1 — why configuration matters (30 min) |
| [`02-setting-up-quote-marks.md`](02-setting-up-quote-marks.md) | Lesson 2 — the Quote marks tab, continuer, apostrophe conflict (75 min) |
| [`03-configuring-quotation-types.md`](03-configuring-quotation-types.md) | Lesson 3 — the Quotation types tab (60 min) |
| [`04-interpreting-and-clearing-the-check.md`](04-interpreting-and-clearing-the-check.md) | Lesson 4 — triage to zero errors (75 min) |
| [`05-scenario-bank.md`](05-scenario-bank.md) | Applied practice: Runda, Menda, Waku (90 min) |
| [`06-mentor-guide.md`](06-mentor-guide.md) | Facilitator notes: project setup, phasing, error seeding |
| [`07-quiz.md`](07-quiz.md) | Assessment quiz |
| [`08-video-script.md`](08-video-script.md) | Recording script for Cypher upload |

---

## Prerequisites

Before starting this course confirm you have the following:

**Software**
- Paratext 9.5 installed and licensed on your machine
- A user account with write access to at least one project

**Skills**
- Able to open a project and navigate the Paratext menu bar
- Able to run a basic check (☰ > Tools > Run basic checks...)
- Familiar with the idea that `\p`, `\q`, `\v` etc. are USFM markers (you do not need to know them in depth — the course explains the relevant ones as they appear)

**Fictional projects** (see the [mentor guide](06-mentor-guide.md) for setup and distribution)
- The `tamba` project must be installed before starting Lessons 1–4
- The `runda`, `menda`, and `waku` projects must be installed before the scenario bank

---

## The Fictional Project

Lessons 1–4 use a single fictional project called **Tamba New Testament** (`tamba`). The Tamba language is fictional and uses the following quotation conventions:

| Level | Name | Opening mark | Unicode | Closing mark | Unicode |
|-------|------|-------------|---------|-------------|----------|
| First level | Primary speech | `“` | U+201C | `”` | U+201D |
| Second level | Embedded speech | `‘` | U+2018 | `’` | U+2019 |
| Third level | Tertiary (rare) | `“` | U+201C | `”` | U+201D |

**Additional facts about Tamba quotation style:**
- Quotation marks restart at the beginning of each new paragraph of continued speech (no mid-paragraph continuation marks).
- Third level is used only in Matthew, John, and Revelation where a character quotes scripture inside dialogue.
- Poetry lines (`\q`, `\q2`, `\q3`) are never marked with quotation characters; only prose speech uses marks.

Keep this reference table open as you work through Lessons 1–4. The scenario bank introduces three further fictional languages, each with its own conventions table.

---

## Quick Reference — Unicode Characters Used in This Course

| Character | Unicode | Name | How to enter in Paratext |
|-----------|---------|------|--------------------------|
| `«` | U+00AB | Left-pointing double angle quotation mark | Dropdown (▼) in Quote marks tab, or paste from Quick Reference |
| `»` | U+00BB | Right-pointing double angle quotation mark | Dropdown (▼) in Quote marks tab, or paste from Quick Reference |
| `“` | U+201C | Left double quotation mark | Dropdown (▼) in Quote marks tab, or paste from Quick Reference |
| `”` | U+201D | Right double quotation mark | Dropdown (▼) in Quote marks tab, or paste from Quick Reference |
| `‘` | U+2018 | Left single quotation mark | Dropdown (▼) in Quote marks tab, or paste from Quick Reference |
| `’` | U+2019 | Right single quotation mark | Dropdown (▼) in Quote marks tab, or paste from Quick Reference |
| `‹` | U+2039 | Single left-pointing angle quotation mark | Dropdown (▼) in Quote marks tab, or paste from Quick Reference |
| `›` | U+203A | Single right-pointing angle quotation mark | Dropdown (▼) in Quote marks tab, or paste from Quick Reference |
| `—` | U+2014 | Em dash | Dropdown (▼) in Quote marks tab, or Alt+0151 on Windows |
| `"` | U+0022 | Quotation mark (straight) | Standard keyboard — avoid using as a quote mark |
| `'` | U+0027 | Apostrophe (straight) | Standard keyboard — avoid using as a quote mark |

---

## Glossary

**Check scope**
The portion of the project the Quotation check examines — a single book, a chapter range, or the entire project. Limiting scope to one book during configuration makes results manageable.

**Configuration problem**
A check result caused by a gap or error in the Quote marks tab or Quotation types tab rather than by a mistake in the translation text. Fix by adjusting the configuration; do not edit the text.

**First level / Second level / Third level**
The nesting depth of a quotation. First level is primary speech; Second level is speech embedded within a First level quotation; Third level is speech embedded within a Second level quotation. Each level has its own Opening, Quote Continuer at new paragraph, and Closing characters on the Quote marks tab.

**Label tooltip / status bar**
In the Quote marks tab, the status bar at the bottom of the dialog shows a description when you hover over any row or column label (such as “Opening” or “Quotes within Quotes (Second level)”). Use this to understand what a field does. To verify the actual character in a cell, use the dropdown arrow (▼) on that cell to see the character list, or check the Example preview at the bottom of the dialog.

**Opening mark / Closing mark**
The characters that begin and end a quotation at a given level. These are entered per-level in the Quote marks tab.

**Project note**
A note attached to a verse (☰ > Insert > Project note..., or via the notes list at ☰ > Tools > Notes List...). Use Project Notes to document residual check results that configuration cannot eliminate, so a consultant reviewer can see they were examined deliberately.

**Quote Continuer at new paragraph**
A character placed at the start of a new paragraph to signal that a speaker’s speech from the previous paragraph continues. Entered in the middle column of the Quote marks tab grid. Many languages leave this blank and instead close and reopen quotes fully at each paragraph break.

**Quote marks tab**
The first tab of the Quotation Rules dialog (☰ > Project settings > Quotation Rules). Contains a three-row, three-column grid for entering the Opening, Quote Continuer at new paragraph, and Closing characters at each nesting level, plus additional checkboxes below the grid (hover labels for descriptions), an Example preview section showing the configured marks in context, and the **Copy quote mark settings...** button.

**Quotation Rules**
The Paratext dialog (under the project menu **☰ > Project settings > Quotation Rules**) where both the Quote marks tab and the Quotation types tab are configured.

**Quotation types check**
A Paratext check (separate from the basic Quotation check) that verifies whether quotation marks appear or are absent according to the semantic type of speech in each verse. Configured on the Quotation types tab. Must be enabled by a project administrator.

**Quotation types tab**
The second tab of the Quotation Rules dialog. Lists seven semantic speech categories (Normal, Quotation from another source, Self quote, Continued quotation, Potential, Indirect, Hypothetical). For each type you choose Use quote marks, Never use quote marks, or Quote marks are optional.

**Real error**
A check result where the translation text genuinely has a missing, extra, or wrong-level quotation mark. Fix by editing the verse directly.

**Unicode code point**
A unique number assigned to every character in the Unicode standard, written as U+ followed by four to six hex digits (e.g., U+00AB for «). Use the code point — not the visual appearance — to confirm that the correct character is entered in the Quote marks tab.

**USFM marker**
A tagged code in Paratext’s text format (Unified Standard Format Markers) that identifies the role of a paragraph or span — for example \p (body paragraph), \q (poetry line), \v (verse).

**Word-medial punctuation**
A setting in ☰ > Project settings > Language Settings > Other Characters tab. Any character listed here is treated as part of a word when it appears between two alphabetic characters. Use this when a character serves as both a closing quotation mark and an apostrophe (e.g., U+2019 ’), to prevent the quotation checker from misreading apostrophes inside words as unclosed quotation marks.
