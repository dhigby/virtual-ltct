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

**Format:** Each module presents a goal, a starting state, a set of discovery prompts (questions to answer before you act), and then the expected configuration you can compare against.

---

## Contents

- [Prerequisites](#prerequisites)
- [The Fictional Project](#the-fictional-project)
- [Module 1 — What the Quotation Check Does](#module-1--what-the-quotation-check-does)
  - [Exercise 1.1 — Observe an unconfigured check](#exercise-11--observe-an-unconfigured-check)
- [Module 2 — Setting Up Quote Marks](#module-2--setting-up-quote-marks)
  - [Exercise 2.1 — Entering Quote Marks for Tamba](#exercise-21--entering-quote-marks-for-tamba)
  - [Exercise 2.2 — Entering Quote Marks for Runda](#exercise-22--entering-quote-marks-for-runda)
  - [Exercise 2.3 — Handling Word-Medial Punctuation (the Apostrophe Conflict)](#exercise-23--handling-word-medial-punctuation-the-apostrophe-conflict)
- [Module 3 — Configuring Quotation Types](#module-3--configuring-quotation-types)
  - [Exercise 3.1 — Reading Tamba's Quotation Types](#exercise-31--reading-tambas-quotation-types)
  - [Exercise 3.2 — Customizing Quotation Types for Tamba](#exercise-32--customizing-quotation-types-for-tamba)
- [Module 4 — Interpreting and Clearing the Check](#module-4--interpreting-and-clearing-the-check)
  - [Exercise 4.1 — Triage a dirty result set](#exercise-41--triage-a-dirty-result-set)
  - [Exercise 4.2 — Reach zero actionable errors](#exercise-42--reach-zero-actionable-errors)
- [Module 5 — Language Scenario Practice](#module-5--language-scenario-practice)
  - [Scenario A — Guillemet (French style)](#scenario-a--guillemet-french-style)
  - [Scenario B — Angle-bracket guillemets (French/German style)](#scenario-b--angle-bracket-guillemets-frenchgerman-style-with-nesting-reversal)
  - [Scenario C — Non-standard marks with continuation](#scenario-c--non-standard-marks-with-continuation)
- [Where to Go from Here](#where-to-go-from-here)
- [Quick Reference](#quick-reference--unicode-characters-used-in-this-course)
- [Glossary](#glossary)
- [Facilitator Notes](#facilitator-notes)

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

**Fictional projects**
- The `tamba` project must be installed before starting Modules 1–4 (see Facilitator Notes)
- The `runda`, `menda`, and `waku` projects must be installed before Module 5

**Estimated time**
- Modules 1–4: 3–4 hours
- Module 5 (all three scenarios): 1–2 hours additional

---

## The Fictional Project

All exercises use a single fictional project called **Tamba New Testament** (`tamba`). The Tamba language is fictional and uses the following quotation conventions:

| Level | Name | Opening mark | Unicode | Closing mark | Unicode |
|-------|------|-------------|---------|-------------|----------|
| First level | Primary speech | `“` | U+201C | `”` | U+201D |
| Second level | Embedded speech | `‘` | U+2018 | `’` | U+2019 |
| Third level | Tertiary (rare) | `“` | U+201C | `”` | U+201D |

**Additional facts about Tamba quotation style:**
- Quotation marks restart at the beginning of each new paragraph of continued speech (no mid-paragraph continuation marks).
- Third level is used only in Matthew, John, and Revelation where a character quotes scripture inside dialogue.
- Poetry lines (`\q`, `\q2`, `\q3`) are never marked with quotation characters; only prose speech uses marks.

Keep this reference table open as you work through each module.

---

## Module 1 — What the Quotation Check Does

**Learning objectives:** By the end of this module you will be able to (1) explain why the Quotation check cannot produce useful results before it is configured, and (2) describe the two things Paratext needs — inventory and rules — before the check is meaningful.

### Concept

Paratext's **Quotation check** (☰ > Tools > Run basic checks... > Quotations) scans your translation for unmatched, unopened, or incorrectly nested quotation marks. Before it can do this it needs two things:

1. **Quote marks tab** — which characters in your text are quotation marks, and at what level (Opening, Quote Continuer at new paragraph, Closing for each level).
2. **Quotation types tab** — for each semantic category of speech, whether marks are required, forbidden, or optional.

If neither is configured, the check either fires on every verse or fires on nothing — both are useless. The exercises in this course move you from that unconfigured state to a clean, meaningful check result.

### Exercise 1.1 — Observe an unconfigured check

**Goal:** See what the check reports when nothing is configured, so you understand why configuration matters.

**Steps:**
1. Open the `tamba` project.
2. Click **☰ > Tools > Run basic checks...**
3. In the dialog, tick **Quotations** only (leave "Quotation types" unticked — it is a separate check).
4. Click **Choose Books** and select all NT books available in the `tamba` project, then click **OK**. This runs the check across the whole project so you can see the full scope of the problem.

> **Note:** A yellow information bar at the bottom of the dialog reads *“The accuracy of the results of these checks is dependent upon the information you have provided in the inventories and quotation rules for your project.”* This is expected — the point of Exercise 1.1 is that the check cannot give accurate results until it is configured.

> **[SCREENSHOT]** The Run basic checks dialog with Quotations ticked, Choose Books selected, and all NT books included.

5. Scroll through the results list.

> **[SCREENSHOT]** The Quotations check results panel showing a large number of results spread across many books, with no configuration applied.

> **Scope tip:** For all configuration work in Modules 2–4, use **Current Book** in the Run basic checks dialog and keep Matthew open — it has heavy, representative dialogue and keeps results manageable. Switch to Choose Books and expand to the full NT only after Matthew is clean.

**Discovery prompts:**
- How many results are listed? Are they spread across many books, or concentrated in one?
- Pick three results at random. Open the verse. Do you see what you would call a "real" quotation problem, or does it look like the text is fine?
- What do you think is causing all these results?

**What you should observe:** The check either reports hundreds of false positives (if it defaults to expecting straight double-quote characters that Tamba does not use) or reports nothing at all. Either way the results are not trustworthy until you configure the Quote marks tab.

**Module 1 summary**
- The Quotation check needs both the Quote marks tab (which characters are quote marks) and the Quotation types tab (when marks are expected) configured before it gives trustworthy results.
- An unconfigured check is not a starting baseline — it is noise. Don't try to interpret or fix results until configuration is complete.

**Assessment questions**

1. The Quotations check runs on an unconfigured project and returns zero results. Does this mean the translation has no quotation errors? Explain your answer.
2. What are the two things Paratext needs before the Quotations check gives meaningful results?
3. Why start with Matthew when working through check results during configuration, rather than running the check on the whole NT at once?

**Answer key**

1. No. Zero results on an unconfigured project means Paratext does not know which characters to look for — it is not finding marks rather than confirming their absence. The check is reporting silence, not correctness.
2. (1) The Quote marks tab — which characters in the text are quote marks and at what level. (2) The Quotation types tab — whether each semantic category of speech requires, forbids, or optionally allows marks.
3. Matthew has heavy, representative dialogue with clear nesting. One book keeps results manageable and lets you confirm each fix before expanding scope.

---

## Module 2 — Setting Up Quote Marks

**Learning objectives:** By the end of this module you will be able to (1) navigate to the Quote marks tab and enter the correct characters for each nesting level, (2) configure the Quote Continuer at new paragraph for languages that use continuation marks, and (3) resolve the word-medial punctuation conflict when the same character serves as both a closing mark and apostrophe.

### Concept

The **Quote marks** tab tells Paratext which characters your language uses to open and close quotations at each nesting level, and which character (if any) continues a speech across a paragraph break. Navigate to:

project menu **☰ > Project settings > Quotation Rules**, then click the **Quote marks** tab.

> **[SCREENSHOT]** The project menu open showing Project settings highlighted, and Quotation Rules selected in the submenu.

The tab has a grid with three rows and three columns.

**Rows — nesting levels:**
- **Quotes (First level)** — primary speech
- **Quotes within Quotes (Second level)** — speech embedded within a First level quotation
- **Quotes within Quotes within Quotes (Third level)** — speech embedded within a Second level quotation

**Columns:**
- **Opening** — the character that starts a quotation at that level
- **Quote Continuer at new paragraph** — the character repeated at the beginning of a new paragraph when a quotation continues (many languages leave this blank)
- **Closing** — the character that ends a quotation at that level

Below the grid the tab has several additional checkboxes (such as **Closing quotes close**, **List all quote marks...**, and **Continuer required at...**). Hover over any label to see its description in the status bar at the bottom of the dialog.

At the bottom of the dialog:
- **Example** — a live text preview showing how your configured marks look in a sample passage. Use this to visually confirm that you have selected the correct characters.
- **Copy quote mark settings...** button — imports character settings from another project (useful when a related project uses the same conventions).

> **[SCREENSHOT]** The Quote marks tab with the three-row, three-column grid visible, showing the additional settings below the grid.

---

### Exercise 2.1 — Entering Quote Marks for Tamba

Open the Tamba project’s Quotation Rules dialog (☰ > Project settings > Quotation Rules) and click the **Quote marks** tab.

The Tamba project is in Phase A: the Quote marks tab is blank. Enter the following settings using the dropdown arrow (▼) on each cell:

| Level | Opening | Quote Continuer at new paragraph | Closing |
| --- | --- | --- | --- |
| First level | `“` (U+201C) | *(leave blank)* | `”` (U+201D) |
| Second level | `‘` (U+2018) | *(leave blank)* | `’` (U+2019) |
| Third level | `“` (U+201C) | *(leave blank)* | `”` (U+201D) |

**Steps:**
1. Click the dropdown (▼) on the **Opening** cell for First level. Select `“` (Left double quotation mark, U+201C).
2. Leave the **Quote Continuer at new paragraph** cell for First level blank.
3. Click the dropdown on the **Closing** cell for First level. Select `”` (Right double quotation mark, U+201D).
4. Repeat for Second level: Opening = `‘` (U+2018), Continuer = blank, Closing = `’` (U+2019).
5. Repeat for Third level: Opening = `“` (U+201C), Continuer = blank, Closing = `”` (U+201D).
6. Check the **Example** section at the bottom of the dialog. The sample text should show `“…‘…’…”` — curly double quotes at the outer level and curly single quotes for embedded speech.
7. Click **OK**.

Tamba uses English-style curly quotes at all three levels with no Quote Continuer — Tamba closes and reopens quotation marks at each paragraph break rather than using a continuation character.

> **Tip:** Hover over any column or row label (“Opening”, “Closing”, “Quotes (First level)”, etc.) to see a description of that field in the status bar at the bottom of the dialog.

---

### Exercise 2.2 — Entering Quote Marks for Runda

Open the Runda project and navigate to ☰ > Project settings > Quotation Rules > Quote marks tab.

Runda is a new project with no quote marks configured. Enter the following settings:

| Level | Opening | Quote Continuer at new paragraph | Closing |
| --- | --- | --- | --- |
| First level | `«` (U+00AB) | `«` (U+00AB) | `»` (U+00BB) |
| Second level | `‘` (U+2018) | *(leave blank)* | `’` (U+2019) |
| Third level | *(leave blank)* | *(leave blank)* | *(leave blank)* |

Runda uses French-style guillemets at the first level with no continuation mark at the second level.

**Steps:**
1. Click the dropdown arrow (▼) on the **Opening** cell for First level. Select « from the list.
2. Click the dropdown arrow on the **Quote Continuer at new paragraph** cell for First level. Select «.
3. Click the dropdown arrow on the **Closing** cell for First level. Select ».
4. Click the dropdown arrow on the **Opening** cell for Second level. Select ‘ (U+2018).
5. Click the dropdown arrow on the **Closing** cell for Second level. Select ’ (U+2019).
6. Leave all Third level cells at **\*none\***.
7. Check the **Example** section at the bottom of the dialog. You should see «...» for First level speech and ‘...’ for embedded speech.
8. Click **OK**.

> **[SCREENSHOT]** The Quote marks tab for Runda after entry, showing « and » in First level cells and the Second level Opening/Closing filled.

---

### Exercise 2.3 — Handling Word-Medial Punctuation (the Apostrophe Conflict)

Some languages use the same character for two distinct purposes: as the **closing quotation mark** at the single-quote level, and as an **apostrophe** within words. When Paratext sees that character inside a word it cannot know whether it is ending a quotation or marking a contraction or possessive.

In Paratext 9.5 this conflict is resolved **not** in the Quotation Rules dialog, but in Language Settings:

**☰ > Project settings > Language Settings**

In the Language Settings dialog, click the **Other Characters** tab. This tab has a **Word-medial punctuation** field. Any character listed there is treated as part of a word when it appears between two alphabetic characters, so the quotation checker will not misread it as a closing mark.

> **[SCREENSHOT]** The Language Settings dialog open on the Other Characters tab, showing the Word-medial punctuation field with a right single quotation mark entered.

**When to use this:**
- Your Second or Third level closing mark is `’` (U+2019), AND
- The same character also appears as an apostrophe inside words (contractions, possessives, glottal stops written with that character)

If both conditions apply, add `’` to the Word-medial punctuation field in Language Settings. The quotation checker will then treat `’` as part of a word when it is flanked by letters, and will only read it as a closing mark when it appears at the end of a quotation.

**Tamba scenario:** Tamba’s Second level closing mark is `’` (U+2019). Tamba does not use contractions with apostrophes, so no word-medial punctuation setting is needed. But if Tamba began using `’` as an apostrophe, you would add it to the Word-medial punctuation field.

**Exercise steps (Runda):**
Runda also uses `’` (U+2019) as its Second level closing mark. Suppose Runda does use `’` as an apostrophe.

1. Navigate to ☰ > Project settings > Language Settings > Other Characters tab.
2. In the **Word-medial punctuation** field, enter `’` (U+2019).
3. Click **OK**.
4. Re-run the quotation check on a chapter that has both apostrophes and single-quote speech. Verify that apostrophes inside words no longer generate false quotation errors.

**Assessment questions**

1. A language uses `««` (U+00AB U+00AB) and `»»` (U+00BB U+00BB) for First level speech and `«` / `»` for Second level speech. Where do you enter these characters in PT 9.5?
2. What is the Quote Continuer at new paragraph column for? Give an example of when you would leave it blank.
3. Your Second level closing mark is `’` (U+2019). The quotation check is flagging apostrophes inside words as unclosed quotations. Where in PT 9.5 do you resolve this, and what do you enter?

**Answer key**

1. In the **Quote marks tab** of the Quotation Rules dialog (☰ > Project settings > Quotation Rules). Enter `««` in the Opening cell for First level, `»»` in the Closing cell for First level, `«` in the Opening cell for Second level, and `»` in the Closing cell for Second level.
2. The Quote Continuer at new paragraph character is repeated at the beginning of each new paragraph when a single speech spans multiple paragraphs. Leave it blank if your language closes and reopens the quotation marks at each paragraph break rather than using a continuation mark (most Western European languages do this).
3. Navigate to ☰ > Project settings > Language Settings > Other Characters tab. Enter `’` (U+2019) in the **Word-medial punctuation** field. This tells the checker to treat that character as part of a word when it appears between two alphabetic characters.

**Module 2 summary**
- The Quote marks tab grid has three rows (First, Second, Third level) and three columns (Opening, Quote Continuer at new paragraph, Closing).
- Verify every character you enter by checking the Example section at the bottom of the dialog.
- The Quote Continuer at new paragraph is optional; leave it blank if your language does not use one.
- When a closing-quote character doubles as an apostrophe, resolve the conflict in ☰ > Project settings > Language Settings > Other Characters tab > Word-medial punctuation.

---

## Module 3 — Configuring Quotation Types

**Learning objectives:** By the end of this module you will be able to (1) explain what the Quotation types tab controls and why it is separate from the Quote marks tab, (2) configure each of the seven quotation type settings for a given language’s conventions, and (3) distinguish between recommended settings and custom settings.

### Concept

The **Quotation types** tab controls whether Paratext *expects* quotation marks for each semantic category of speech. This is independent of *which characters* are used (that is the Quote marks tab’s job). The Quotation types tab answers: for this type of speech, should marks always appear, never appear, or is either acceptable?

Navigate to: ☰ > Project settings > Quotation Rules > **Quotation types** tab.

> **[SCREENSHOT]** The Quotation types tab showing the seven type rows, each with a drop-down selector, and the Recommended/Custom settings controls at the top.

#### Enabling the check (administrator only)

At the top of the tab is the checkbox **Enable the Quotation types check in Run basic checks**. Only a project administrator can check this box — the status bar confirms: "Only a project administrator can enable this tab." Any user can configure the radio buttons and drop-downs below; it is only the enable checkbox that requires administrator access. If you are not an administrator, configure the settings in this module, then ask your project administrator to check the enable box.

> **Limitation:** The Quotation types check only checks first-level quotes in non-Deuterocanonical books.

#### The seven quotation types

The tab lists seven types. Each type has a **drop-down** with three active options (plus the default):

- **Use quote marks** — Paratext expects marks to be present; missing marks are flagged as errors.
- **Never use quote marks** — Paratext expects no marks; unexpected marks are flagged as errors.
- **Quote marks are optional** — either is acceptable; the check does not flag errors for this type.

A count in parentheses appears next to each type name showing how many occurrences of that type are in your project scope — useful for gauging how much a given setting will affect your results.

> **Note:** Even if all types are set to Optional, quotes that do not correspond to any recognized quotation type will still be reported.

| Type | Meaning |
| --- | --- |
| Normal | Direct speech between characters in the narrative |
| Quotation from another source | A narrator or character quotes scripture, another text, or a source outside the narrative |
| Self quote | A character quotes their own earlier words |
| Continued quotation | A speech that continues across a paragraph break using a continuation convention |
| Potential | Paratext identifies this as a possible quotation but cannot determine the type |
| Indirect | Reported speech: “He said that the road was long” (no direct marks in the source) |
| Hypothetical | Speech in a conditional or hypothetical frame: “If I were king, I would say…” |

#### Recommended settings

When you first open the Quotation types tab, Paratext offers **Recommended settings**. These are sensible defaults for most translations:

| Type | Recommended setting |
| --- | --- |
| Normal | Use quote marks |
| Quotation from another source | Quote marks are optional |
| Self quote | Quote marks are optional |
| Continued quotation | Never use quote marks |
| Potential | Quote marks are optional |
| Indirect | Never use quote marks |
| Hypothetical | Never use quote marks |

If your language follows these conventions, select **Use recommended settings** and you are done. If your language diverges, select **Custom settings** and adjust each type's drop-down individually.

> **Note:** The values Paratext pre-fills under "Use recommended settings" are set by Paratext — verify what appears in your version before relying on the table above as the exact recommended defaults.

> **Tip:** Complete the settings on both the Quote marks tab and the Quotation types tab before clicking **OK** — the OK button saves all changes from both tabs at once.

If another project in your organization uses the same quotation type conventions, click **Copy quotation type settings...** at the bottom of the dialog to import that project's settings rather than configuring each drop-down manually.

> **Visual note:** A dividing line in the dialog separates the first three types (Normal, Quotation from another source, Self quote) from the lower four (Continued quotation, Potential, Indirect, Hypothetical). The upper group covers standard direct speech; the lower group covers special speech categories.

---

### Exercise 3.1 — Reading Tamba’s Quotation Types

Open the Tamba project and navigate to ☰ > Project settings > Quotation Rules > Quotation types tab.

Before changing anything, record the current setting for each type:

| Type | Current setting |
| --- | --- |
| Normal | ? |
| Quotation from another source | ? |
| Self quote | ? |
| Continued quotation | ? |
| Potential | ? |
| Indirect | ? |
| Hypothetical | ? |

Tamba’s settings should match the recommended defaults above. Confirm this before moving on.

---

### Exercise 3.2 — Customizing Quotation Types for Tamba

After reviewing Tamba's text with the translation team, you have determined the following three things:

1. Tamba does not mark narrator scripture citations with quote marks — these appear as plain narrative text.
2. Tamba does not use continuation marks; each paragraph of a long speech closes and reopens.
3. When a character quotes their own earlier words (a self-quote), Tamba treats it the same as any other direct speech: it **must** be marked with quotation marks.

**Step 1** — Confirm that the current recommended settings handle items 1 and 2 correctly:
- Quotation from another source = Quote marks are optional (narrator citations not flagged) ✓
- Continued quotation = Never use quote marks (no continuation marks expected) ✓

**Step 2** — For item 3, check the current recommended setting for **Self quote**. The recommended default is **Quote marks are optional**. Tamba requires marks for self-quotes, so this must change.

Click **Custom settings** at the top of the tab. This switches all drop-downs to editable mode. Change **Self quote** from *Quote marks are optional* to **Use quote marks**.

The correct final settings for Tamba:

| Type | Correct setting for Tamba | Reason |
| --- | --- | --- |
| Normal | Use quote marks | Tamba marks all direct speech |
| Quotation from another source | Quote marks are optional | Narrator scripture citations are not marked |
| Self quote | **Use quote marks** | Tamba treats self-quotes the same as Normal speech |
| Continued quotation | Never use quote marks | Tamba closes and reopens at each paragraph |
| Potential | Quote marks are optional | Uncertain cases should not generate errors |
| Indirect | Never use quote marks | Reported speech is not marked |
| Hypothetical | Never use quote marks | Hypothetical speech is not marked |

**Check your work:**
- Save and re-run the check. A verse with a self-quote that lacks marks should now be flagged as an error.
- Confirm that Luke 4:18 (narrator Isaiah citation) is not flagged — Quotation from another source = Optional means no marks are required.
- Check a verse with indirect speech. With Indirect = Never use quote marks, it should not be flagged.

**Module 3 summary**
- The Quotation types tab controls whether Paratext *expects* marks for each semantic category of speech — it is separate from and complementary to the character configuration on the Quote marks tab.
- Use recommended settings as a starting point; switch to Custom settings only when the defaults produce incorrect results.
- Configuring quotation types correctly reduces triage work in Module 4 by eliminating whole categories of expected exceptions before the check runs.
- Only a project administrator can enable the check in the Run Basic Checks dialog — if you are not an administrator, configure the type settings and ask an administrator to enable the check.

**Assessment questions**

1. A language never uses dialogue quote marks for indirect speech (“He said that the road was long”). Which quotation type covers this, and what setting is correct?
2. The recommended settings have “Continued quotation” set to “Never use quote marks”. Your language uses a Quote Continuer at new paragraph — an opening mark repeated at the start of each continued paragraph. Does this conflict with the recommended setting?
3. A check result appears for a narrator OT citation verse. You have “Quotation from another source” set to “Use quote marks”. Is this a real error or a configuration issue? What is the correct action?

**Answer key**

1. Indirect speech. Set it to **Never use quote marks** — the check will report an error if marks appear unexpectedly in indirect speech verses, and will not report an error when they are absent.
2. Yes, it conflicts. “Never use quote marks” for Continued quotation means the check expects no marks at the start of a continued paragraph. If your language repeats the opening mark there, the check will flag those marks as errors. Change “Continued quotation” to **Quote marks are optional** or **Use quote marks** depending on how consistently the continuation mark is used.
3. Configuration issue. If Tamba does not mark narrator scripture citations, change “Quotation from another source” to **Quote marks are optional** and re-run — the result will disappear. Fix the configuration rather than editing correct text to silence the check.

---

## Module 4 — Interpreting and Clearing the Check

**Learning objectives:** By the end of this module you will be able to (1) classify a check result as a real error or configuration problem, (2) take the correct corrective action for each type, and (3) work through a result set systematically to reach zero actionable errors.

### Concept

After configuration you will typically have two kinds of results:

1. **Real errors** — a mark is genuinely missing, extra, or at the wrong level. Fix these in the text.
2. **Configuration problems** — the check flags something that reveals a gap in your inventory or rules. Fix these by refining the configuration, not by editing the text.

> **[SCREENSHOT]** The Quotations check results panel after full configuration, showing a manageable list of results. One result is highlighted with the verse open alongside it, demonstrating how to read a result entry (location, message, and the text in context).

### Exercise 4.1 — Triage a dirty result set

The `tamba` project has been seeded with the following issues. **Before reading further, fill in the Your prediction column for all five rows — write 1 (Real error) or 2 (Configuration problem).** Only after you have written a prediction for every row should you read the discovery prompts and open the verses.

| # | Location | Check message | Your prediction | Actual type |
|---|----------|--------------|----------------|------------|
| 1 | Matthew 5:3 | Missing closing quotation mark | ? | ? |
| 2 | Luke 4:18 | Unexpected opening quotation mark | ? | ? |
| 3 | John 3:16 | Invalid Second level quotation mark | ? | ? |
| 4 | Acts 2:25 | Unclosed quotation mark at paragraph break | ? | ? |
| 5 | Romans 1:1 | Unexpected closing quotation mark | ? | ? |

**Discovery prompts for each item:**
- Matthew 5:3 opens a speech that runs through verse 5:12. What closing mark should appear at 5:12, and what does the check report when it is absent?
- Luke 4:18 contains an Isaiah citation. Does Tamba use dialogue marks for narrator scripture citations? If not, what should you do with a stray opening `“` before the citation?
- John 3:16 is inside Jesus's speech to Nicodemus. The inner quotation at this verse is Second level. What character should the Second level opening mark be in Tamba? If you see a straight `"` (U+0022) instead, is that a valid Tamba Second level mark?
- Acts 2:25–28: Peter cites Psalm 16 in Second level marks as one continuous span across several paragraph breaks. Recall the Tamba conventions in The Fictional Project table: what does Tamba do with quotation marks at each new paragraph of continued speech? Does this text follow that convention?
- Romans 1:1 has no dialogue. How could `’` (U+2019) inside a word cause the check to report a quotation problem? What is the correct fix?

**Expected resolution (answer key):**

| # | Actual type | Action |
|---|-------------|--------|
| 1 | Real error | Add the missing `”` (U+201D) at the end of Matthew 5:12. The First level speech opened with `“` (U+201C) at verse 5:3; the closing mark was deleted. |
| 2 | Real error | Delete the stray `“` (U+201C) before the Isaiah citation in Luke 4:18. Tamba does not mark narrator scripture citations; the mark was added by mistake. |
| 3 | Real error | The Second level opening mark in John 3:16 is a straight `"` (U+0022) rather than `‘` (U+2018). Replace it with `‘` (U+2018) and confirm the closing `’` (U+2019) is also present. |
| 4 | Real error | Tamba restarts quotation marks at every paragraph break, but the Psalm 16 citation runs from 2:25 to 2:28 as one unbroken Second level span. Edit the text: close with `’` (U+2019) at the end of each paragraph and reopen with `‘` (U+2018) at the start of the next, so every paragraph carries a complete pair. |
| 5 | Configuration problem | The `’` (U+2019) in Romans 1:1 is an apostrophe inside a word, which Paratext reads as the Second level closing mark with no matching opener. Navigate to ☰ > Project settings > Language Settings > Other Characters tab and add `’` (U+2019) to the Word-medial punctuation field. |

### Exercise 4.2 — Reach zero actionable errors

**Goal:** Work through the full result list for the `tamba` project until every result has been cleared by fixing the text or adjusting the configuration.

**Steps:**
1. Limit the scope to Matthew first. Work that book to zero results before expanding.
2. Work through the results top-to-bottom.
3. For each result: open the verse, classify it, take the appropriate action.
   - **Real error:** edit the verse text to fix the mark, then re-run.
   - **Configuration problem:** adjust the Quote marks tab or Quotation types tab, then re-run. Do not edit the text to make marks disappear — fix the configuration instead.
4. After fixing a batch of real errors, re-run the check to confirm the count drops.
5. Once Matthew is clean, expand the scope one book at a time through the NT.
6. When unsure whether a result is a real error or a configuration problem, look at how often the same message appears: the same message repeated across many verses usually points to a configuration gap; a one-off result usually points to a real error in that verse.

**Completion criteria:**
- The Quotations check shows 0 results for the current scope.

> **[SCREENSHOT]** The Quotations check results panel showing zero results, confirming the check is clean.

**Module 4 summary**
- Every result is one of two types: real error (fix the text) or configuration problem (fix the Quote marks tab or Quotation types tab).
- Work book by book — Matthew first, then expand. A full-NT result list is overwhelming; a single-book list is actionable.

**Assessment questions**

1. A result in Luke 22:35 says "Missing closing quotation mark." You open the verse: Jesus is speaking and the speech continues through verse 38, where it closes correctly with the final `”`. What type of result is this, and what should you do?
2. You have cleared all real errors in Matthew but 6 results remain. You have checked the text carefully — the translation is correct. What should you do next?
3. The check shows an unexpected Second level quotation mark in Romans 8:1, a verse with no dialogue. What is the most likely cause and correct action?

**Answer key**

1. Configuration problem: check whether there is a paragraph marker (\p) between verses 35 and 38. If so, the check expects either a closing mark at each paragraph break or a Quote Continuer at new paragraph character for First level. Configure the First level Quote Continuer if Tamba carries speech across paragraph boundaries without re-opening marks, or verify the USFM structure has no unexpected paragraph breaks within this speech.
2. These are configuration problems, not text errors. Review each result to identify what rule or setting is missing — for example, a Quotation types setting that does not match how the language uses marks in that context. Adjust the configuration and re-run until those 6 results clear.
3. A stray quotation character has been inserted into the verse text — likely copied accidentally from a source text. Open the verse, locate the stray mark, and delete it. This is a real error (text correction), not a configuration problem.

---

## Module 5 — Language Scenario Practice

**Learning objectives:** By the end of this module you will be able to apply the complete inventory + rules + check + triage workflow independently to an unfamiliar language scenario, including edge cases not covered in Modules 1–4.

These exercises use three new fictional projects. Each has a different quotation style. For each scenario follow the same standard workflow:

1. Read the language conventions table.
2. Answer the discovery prompts before configuring anything.
3. Open the Quote marks tab; the fields will be empty.
4. Enter the quote mark characters on the Quote marks tab.
5. Configure the Quotation types tab.
6. Run the check (start with one book).
7. Triage the results to zero actionable errors.
8. Compare your configuration against the expected answer key.

---

### Scenario A — Guillemet (French style)

**Project:** Runda New Testament (`runda`)

**Runda quotation conventions:**

| Level | Opening | Unicode | Closing | Unicode |
|-------|---------|---------|---------|----------|
| First level | `«` | U+00AB | `»` | U+00BB |
| Second level | `‘` | U+2018 | `’` | U+2019 |

No Third level. First level opening mark `«` also appears at the start of continued paragraphs.

**Discovery prompts:**
- Runda uses `’` (U+2019) as both its Second level closing mark and as an apostrophe. How does the Word-medial punctuation setting resolve this conflict?
- How would you verify that the text contains `«` (U+00AB) rather than `<<` (two less-than signs)?

**Expected configuration:**

| Level | Opening | Quote Continuer at new paragraph | Closing |
|-------|---------|----------------------------------|----------|
| First level | `«` | `«` | `»` |
| Second level | `‘` | *(blank)* | `’` |

Word-medial punctuation: add `’` (U+2019) in ☰ > Project settings > Language Settings > Other Characters tab > Word-medial punctuation.

**Check your work:**
- After configuration, run the check on Luke. Verify that `«...»` speech boundaries are recognized correctly and narrative passages generate no results.
- Verify that apostrophes inside words (contractions, possessives) do not appear in the results. If they do, the Word-medial punctuation setting for U+2019 is missing.
- Confirm the First level Quote Continuer (`«`) at the start of continued paragraphs is not flagged as an unexpected mark.

---

### Scenario B — Angle-bracket guillemets (French/German style with nesting reversal)

**Project:** Menda New Testament (`menda`)

**Menda quotation conventions:**

| Level | Opening | Unicode | Closing | Unicode |
|-------|---------|---------|---------|---------|
| First level | `«` | U+00AB | `»` | U+00BB |
| Second level | `›` | U+203A | `‹` | U+2039 |

Note: At Second level Menda uses **single guillemets in reversed order** — the `›` (U+203A, right-pointing) opens the embedded speech and `‹` (U+2039, left-pointing) closes it. This is unusual and easy to get wrong.

> **[SCREENSHOT]** The Quote marks tab for the Menda project, with Second level Opening set to › and Second level Closing set to ‹. The Example section at the bottom shows the marks in correct nested order.

**Discovery prompts:**
- Why might it be confusing that the opening mark for Second level is the right-pointing single guillemet? What would happen if you entered it in the Closing field by mistake?
- How would you verify which direction the single guillemet in the text actually points? (Hint: use the dropdown list on each field — hover each character option to read its name in the list.)
- If a verse shows `«Il a dit ‹oui›»` and the check flags Second level as having incorrect marks, what is the most likely cause?

**Expected configuration:**

| Level | Opening | Quote Continuer at new paragraph | Closing |
|-------|---------|----------------------------------|--------|
| First level | `«` | *(blank)* | `»` |
| Second level | `›` | *(blank)* | `‹` |

Continuation: none. Apostrophe handling: not needed — Menda's Quote marks tab does not use U+2019, so no Word-medial punctuation setting is required.

**Check your work:**
- Run the check on John (contains clear nested dialogue). Verify that `«...›...‹...»` structures pass without errors.
- If the check fires on every Second level opening mark, the Opening and Closing fields for Second level are likely reversed. Confirm which character is right-pointing (`›`) and which is left-pointing (`‹`).
- After entering Second level, check the Example section at the bottom. If › opens and ‹ closes in the example, you have the correct order. The visual difference between `›` and `‹` is easy to miss — use the Example to confirm before clicking OK.

---

### Scenario C — Non-standard marks with continuation

**Project:** Waku New Testament (`waku`)

**Waku quotation conventions:**

| Level | Opening | Unicode | Closing | Unicode |
|-------|---------|---------|---------|---------|
| First level | `—` | U+2014 | `—` | U+2014 |
| Second level | `“` | U+201C | `”` | U+201D |

Waku uses an **em dash** (U+2014) for both the opening and closing mark at First level. Multi-paragraph speech does *not* restart the em dash — it uses a **continuation mark** of `—` (another em dash) at the start of each continued paragraph.

**Discovery prompts:**
- When the same character is used for both opening and closing, how does Paratext know which is which? What rule does it rely on?
- Waku uses the em dash as a continuation mark. Should the continuation mark be a different character from the opening mark, or can it be the same character? What happens if they are the same?
- In a language where em dash is also used for parenthetical remarks (not speech), how would you expect the check to behave? Is there a way to address this?

**Expected configuration:**

| Level | Opening | Quote Continuer at new paragraph | Closing |
|-------|---------|----------------------------------|--------|
| First level | `—` | `—` | `—` |
| Second level | `“` | *(blank)* | `”` |

Third level: leave blank (Waku uses only two levels). Continuation: `—` (em dash, same character).

**Note on same-character open/close:** When the same character serves as both opener and closer, Paratext relies on structural context (paragraph markers, verse boundaries, and surrounding text flow) to determine which role the em dash plays. Because this context-based interpretation may not resolve every case unambiguously, human review of flagged em-dash results is always required for Waku.

**Check your work:**
- Run the check on Acts (extended speeches, heavy use of em-dash dialogue). Verify that multi-paragraph speeches with continuation marks do not generate "unclosed quote" errors.
- Check a verse where em dash is used for a parenthetical aside (not speech). Does the check flag it? What is the correct response — fix the text (replace the em dash with different punctuation for the parenthetical), reconfigure if possible, or document in a Project Note for the consultant?
- This scenario will likely leave some results that cannot be eliminated by configuration alone — because Paratext cannot distinguish an em dash used as speech from one used as a parenthetical dash. For these, consider whether the verses can be reworded to use different punctuation for the parenthetical. Where rewording is not feasible, add a Project Note (☰ > Insert > Project note...) to explain the em dash is a parenthetical, not dialogue, so the consultant can verify during review.

**Module 5 summary**
- The same four-step workflow (inventory → rules → check → triage) applies to every language, however unusual its quotation conventions.
- Always verify entered characters using the Example section at the bottom of the Quote marks tab — on unfamiliar languages it is easy to confuse visually similar characters.
- Some languages (like Waku) will always require human review of residual results; zero configuration errors does not always mean zero results.

**Assessment questions**

1. You enter Second level Opening as `‹` and Second level Closing as `›` for the Menda project, then run the check and find that every Second level opening mark generates a result. The Example section in the Quote marks tab shows the marks in reversed order from what you expect. What went wrong, and what is the fix?
2. In Scenario B you enter Second level as Opening: U+2039, Closing: U+203A (reversed from the correct order). What will the check report?
3. After completing Scenario C (Waku), 8 results remain in the Basic Checks panel — all em dashes used as parenthetical remarks, not speech. Configuration cannot eliminate them. What are your options?

**Answer key**

1. The characters were entered in the wrong fields — the left-pointing guillemet (`‹`, U+2039) is in the Opening field when it should be in the Closing field, and vice versa. Swap them: Opening should be `›` (right-pointing, U+203A), Closing should be `‹` (left-pointing, U+2039). Confirm by checking the Example section — the correct order shows `›` as the inner opening and `‹` as the inner closing.
2. The check will fire on every Second level opening mark (treating the right-pointing guillemet as an unexpected closing mark) and on every Second level closing mark (treating the left-pointing guillemet as an unexpected opening mark). The entire Second level configuration will appear as errors.
3. Two options: (1) Rewrite the affected verses to use different punctuation for parenthetical remarks (e.g. brackets or a non-speech dash character), which removes the ambiguity and clears the results. (2) Where rewording is not feasible, add a Project Note (☰ > Insert > Project note...) to each verse explaining the em dash is a parenthetical, not dialogue — so the consultant can verify during review. The Basic Checks panel results will remain; documenting them prevents them from being mistaken for unreviewed errors.

---

## Where to Go from Here

### How the Quotation check fits in the Paratext checking workflow

The Quotation check is one of the **Basic Checks** in Paratext 9.5 (run via **☰ > Tools > Run basic checks...**). Basic Checks are typically run and cleared before a book moves to Consultant Check (CC). A consultant reviewer will re-run the checks during review, so the goal is a configuration that genuinely models the language — not a result list silenced by editing correct text.

The recommended sequence for a book heading toward CC:

1. Run all Basic Checks (Quotations, Characters, Markers, Footnotes, and others).
2. Clear each check to zero results by fixing text errors and refining the configuration.
3. Where a residual result cannot be cleared by configuration (see Scenario C), document it with a Project Note so the consultant can verify it quickly.

The Quotation check does not need to be perfect before work continues, but it must be clean before CC begins.

### Maintaining the configuration over time

Quotation Rules are set once per project and persist across sessions, but they need attention when:

- **New text is added.** Re-run the check after each significant drafting session; new text may introduce new errors.
- **A new translator joins.** Check whether they are typing the correct Unicode characters. Open the Quote marks tab and use the dropdown on each field to confirm the selected character, or check the Example section — if the marks look wrong in the example, a find-and-replace on the new chapters may be needed.
- **A new book is started.** Some books (like Psalms, Proverbs, or Revelation) have different poetry/prose ratios. Re-run the check after completing the first draft to confirm that poetry lines (`\q`, `\q2`) are not generating false quotation results.

### Copying Quotation Rules to another project

If your language has more than one translation project (for example, a study Bible alongside the main translation), you do not need to reconfigure the Quote marks tab from scratch. Paratext 9.5 can copy settings from a base project:

Open **☰ > Project settings > Quotation Rules** on the destination project. At the bottom of the dialog, click **Copy quote mark settings...** and select the already-configured project as the source. Review every field after copying — differences in verse structure or text conventions between the two projects may require adjustments.

### What a well-configured project looks like

A project with correctly configured Quotation Rules will:

- Return zero results in purely narrative passages with no speech.
- Return zero results on apostrophes and possessives.
- Flag only genuine mismatches — missing marks, extra marks, wrong-level marks — in speech sections.
- Have any residual results (where the same character serves two linguistic purposes and configuration cannot distinguish them) documented in Project Notes (☰ > Insert > Project note...) so a consultant reviewer can verify them without asking for clarification.

Reaching that state is what this course has prepared you to do.

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

---

## Facilitator Notes

### Overview

This section describes how to create and distribute the fictional training projects, how to stage the `tamba` project for each phase of the course, and what text content is required. Read it completely before the first session.

---

### Project Setup: `tamba`

#### Step 1 — Create the project in Paratext 9.5

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
4. Leave all quotation settings at their defaults for now — learners will configure them in Modules 2–3.

#### Step 2 — Add the minimum required text

The project must contain text in at least the following passages. All other books and chapters can be empty or contain placeholder text.

| Book | Passage | Required because |
|------|---------|------------------|
| Matthew | 5:1–12 (Beatitudes) | Exercise 4.1 seed #1 — multi-verse speech |
| Matthew | 26:1–75 (arrest and trial) | Module 3 check verification — heavy dialogue |
| Luke | 4:14–21 | Exercise 4.1 seed #2 — Isaiah citation |
| John | 3:14–17 | Exercise 4.1 seed #3 — embedded Second level quote |
| Acts | 2:22–28 (Peter's Pentecost speech) | Exercise 4.1 seed #4 — Psalm 16 citation |
| Romans | 1:1–7 | Exercise 4.1 seed #5 — apostrophe-conflict scenario |

For all other books, inserting one or two placeholder verses is sufficient. The unconfigured check in Exercise 1.1 needs enough text to produce a realistic flood of results; five or more books with at least a few verses each will achieve this.

#### Step 3 — Apply correct Tamba quotation marks throughout

All dialogue in the required passages must use the correct Tamba quotation characters:

| Level | Opening | Closing |
|-------|---------|----------|
| First level (primary speech) | `“` U+201C | `”` U+201D |
| Second level (embedded speech) | `‘` U+2018 | `’` U+2019 |
| Third level (tertiary, rare) | `“` U+201C | `”` U+201D |

**Apostrophes:** Phase A text should contain **no contractions or possessives** — Tamba’s fictional conventions do not include apostrophes in Phase A, consistent with Exercise 2.3 (which uses Runda as the apostrophe-conflict example, not Tamba). For Phase B, in the text near Romans 1:1 include a word with `’` (U+2019) as an apostrophe — the same character as Tamba’s Second level closing mark. This seeds the configuration-problem scenario in Exercise 4.1, item 5.

**Paragraph-spanning speech:** Matthew 5:3–12 is the key example. Each verse is its own `\p` paragraph. The speech opens with `“` (U+201C) at verse 5:3 and closes with `”` (U+201D) at verse 5:12. No continuation mark appears at the start of verses 5:4–5:12 — Tamba restarts a full open/close pair at each paragraph boundary. This is what the learner configures in Exercise 3.1.

Do **not** configure the Quote marks tab or Rules at this stage. The project should arrive at learners with a blank quotation configuration.

#### Step 4 — Stage two versions of the project

The course requires two states of the `tamba` project:

**Phase A — Modules 1–3 (blank configuration)**
- Quote marks tab: empty
- Quotation Rules: default (unconfigured)
- Text: correct marks, no deliberate errors

Distribute Phase A before learners begin Module 1. Learners configure the inventory and rules themselves during Modules 2–3.

**Phase B — Module 4 (configured + seeded errors)**
- Quote marks tab: fully configured per the Tamba settings above (Exercise 2.1 values)
- Quotation types tab: recommended defaults with one customization — Self quote = **Use quote marks** (the Exercise 3.2 result)
- Text: same as Phase A, plus the five deliberate errors from the Module 4 Seeding table below

Distribute Phase B (or push an update) before Exercise 4.1. For a group session, the simplest approach is to distribute Phase A, let learners work through Modules 1–3 themselves, then have the facilitator push the five seed edits to the shared project before Module 4 begins.

#### Step 5 — Distribute the project

Choose whichever distribution method suits your setup:

| Method | How |
|--------|-----|
| Paratext Send/Receive (shared server) | Create the project on a shared Paratext server. Learners receive it via Send/Receive. Push the Phase B edits to the server before Module 4. |
| USB / local file share | Back up the project (**Paratext menu > Advanced > Backup project to file...**), distribute the `.bak` file, and learners restore it (**Paratext menu > Advanced > Restore project from file...**). Provide two `.bak` files: one for Phase A and one for Phase B. |
| Paratext Registry (internet) | Register `TAMBA` as a private project on the Paratext Registry. Learners receive it via Send/Receive using an invited user account. |

For self-paced learners working alone, USB or file share is simplest. For instructor-led groups, a shared Paratext server is recommended so the facilitator can push Phase B edits centrally.

---

### Project Setup: `runda`, `menda`, and `waku` (Module 5)

Each Module 5 project needs enough text to produce meaningful check results, but no deliberate errors need to be seeded — learners configure these projects from scratch.

| Project | Quotation style | Minimum books suggested |
|---------|----------------|-------------------------|
| `runda` | Guillemet outer (`«` / `»`), curly single inner (`‘` / `’`) — Guillemet style (Scenario A). Include words with `’` (U+2019) as apostrophes so learners encounter the apostrophe conflict. | Matthew, Luke, John (dialogue-heavy) |
| `menda` | Double guillemets outer, reversed single guillemets inner: `«...›...‹...»` (Scenario B) | Matthew, Luke |
| `waku` | Em dash as both opener and closer: `—...—`, with em dash continuation mark (Scenario C) | Matthew, Luke (stretch exercise) |

Apply the correct quotation characters for each language throughout the text. Leave all Quote marks tab and Rules settings blank — learners configure them as part of the scenario.

---

### Module 4 Seeding

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

### General Facilitation Notes

- **Discovery-first ordering:** Each module shows the answer key *after* the discovery prompts, not before. Encourage learners to write down their prediction before scrolling to the expected configuration.
- **Scenario C (Waku):** The em-dash scenario is the hardest. It is appropriate as a stretch exercise or for learners who have completed Modules 1–4 confidently and want a challenge.

Add quotation rules course content
Update Menda configuration — add Third level
