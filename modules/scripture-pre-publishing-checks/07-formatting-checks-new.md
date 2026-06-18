# Formatting Checks (New)

# Formatting Checks (New)

# Module 6: Formatting Checks
**Course:** Finalizing Your Translation for Publication
**Module Number:** 6
**Duration:** 60 minutes
**Level:** Experienced Translator
**Goal:** Complete comprehensive formatting verification using Paratext checklists to ensure publication-ready text.
---
## Introduction
*Time: 5 minutes*
Formatting errors are often invisible during the creative phase of translation—until they are printed in thousands of copies. A missing section heading, a broken cross-reference, or an inconsistent book title undermines professionalism and distracts readers from the message.
This module provides the final quality control steps. You will use Paratext’s systematic checklist tools to verify that every formatting element is correct and consistent before the text is "frozen" for the typesetter.
### What This Module Covers
- Running the foundational Basic Checks.
- Using the Markers checklist to infer errors in pairs.
- Auditing verse length and content anomalies.
- Verifying navigation elements (headings and titles).
- Validating cross-references and footnote syntax.
---
## Prerequisites
- Completion of the initial **FC (Formatting Checks)** module.
- Familiarity with **USFM markers**.
- Editing permissions for all books in the project.
- Access to the project's official **Style Guide**.
---
## Procedure Walkthrough
*Time: 45 minutes*
### Step 1: Structural Foundation (Basic Checks)
`≡ Tab > Tools > Basic Checks`
Before checking how the text *looks*, you must verify the structural "anchors" of the database. If these are broken, other checklists will give false or confusing results.
- **Chapter/Verse Sequence:** Ensures no numbers are skipped or out of order.
- **Duplicate Verses:** Flags if a verse number appears twice.
	> 💡 **Note:** If a chapter marker (`\\c`) is missing, Paratext will report that the verses in the following section are **duplicated** because it thinks they still belong to the previous chapter. Resolve these first.
- **Marker Pairs:** Use this to catch unclosed character styles (like `\\nd`...`\\nd*`).
### Step 2: Marker Count & Pair Inference
`≡ Tab > Tools > Checklists > Markers`
The Markers checklist provides a "census" of every USFM marker used. It does not flag errors for you; you must **infer** them from the totals.
- **Manual Pair Audit:** For markers that require an opener and a closer (like `\\f` and `\\f*`), the counts **must match exactly**.
	- *Example:* If the list shows 412 instances of `\\f` but only 410 instances of `\\f*`, you have two unclosed footnotes that will break the layout.
- **Anomalies:** Look for "typo" markers. If the list shows `\\p` used 500 times but `\\q` used only once, check if that single `\\q` was a mistake.
### Step 3: Long and Short Verses
`≡ Tab > Tools > Checklists > Long/short verses`
Once the sequence is solid, use this to find content anomalies.
- **Flagged as too long:** Check for missing verse breaks or accidental text duplication.
- **Flagged as too short:** Check for accidental deletions or missing content.
- **Action:** There is no "Correct" flag to click. You must manually investigate each flagged verse. If the verse is legitimately long or short in the original, move to the next item.
### Step 4: Word or Phrase Checklist
`≡ Tab > Tools > Checklists > Word or phrase`
This is a powerful "Search and Destroy" tool for final verification.
- **Consistency Check:** Find all instances of a specific word to ensure you haven't used multiple spellings.
- **Specific Term Audit:** While the **Biblical Terms Tool** is the primary way to check key terms, this checklist is a fast way to see every occurrence of a specific phrase in context to ensure late-stage edits didn't introduce inconsistencies.
### Step 5: Navigation (Headings and Titles)
`≡ Tab > Tools > Checklists > Section headings`
`≡ Tab > Tools > Checklists > Book titles`
- **Headings:** Check for "Heading Deserts"—long stretches of text without a break. Ensure `\\s1` and `\\s2` follow your project's hierarchy.
- **Titles:** Verify the Header (`\\h`) and Main Title (`\\mt1`) against the official approved list. **Typing errors here are high-visibility mistakes.**
### Step 6: References and Footnotes
`≡ Tab > Tools > Checklists > References`
`≡ Tab > Tools > Checklists > Footnotes`
- **Reference Validity (Red Icons):** If your markers are correct, Paratext will display a **red icon** next to the reference if the book abbreviation is wrong or the chapter/verse number doesn't exist in the project.
- **Footnote Callers:** Best practice is to use the `\\f +` marker. This allows the publishing software to automatically generate consistent, sequential callers (a, b, c...).
- **Footnote Origin (****`\\fr`****):** These are usually inserted automatically, but if a translator manually edited a footnote, the `\\fr` might point to the wrong verse. Use the checklist to verify the footnote content matches the verse location.
---
## Troubleshooting & Tips
<table header-row="true">
<tr>
<td>Problem</td>
<td>Solution</td>
</tr>
<tr>
<td>**Red icon in references**</td>
<td>Check for typos in book abbreviations (e.g., `Mat` vs `Matt`) or ensure the reference actually exists in the Bible.</td>
</tr>
<tr>
<td>**Marker counts don't match**</td>
<td>Use the **Markers** checklist to find the book/chapter with the discrepancy and find the missing `*` closer.</td>
</tr>
<tr>
<td>**Missing headings**</td>
<td>Check your project's Style Guide. Some projects require a heading at the start of every chapter; others only at major story breaks.</td>
</tr>
</table>
---
## Summary and Next Steps
*Time: 2 minutes*
**Key Takeaways**
- **Basic Checks** (Sequence and Duplicates) must be clean before running other tools.
- **Marker Checklist** requires manual counting to ensure all openers have closers.
- **Red Icons** in references indicate data validation errors that must be fixed.
- **Expert Judgment** is required for long/short verses; the software flags anomalies, not necessarily errors.
**What Comes Next**<br>After completing formatting checks, the final module covers **Glossary Linking**. You will learn how to mark key terms in the text to connect them to your project's glossary.

