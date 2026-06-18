# Module 6 - Formatting Check - final

# Module 6 - Formatting Check - final

---
**Module 6: The "Zero-Error" Formatting WorkflowCourse:** Finalizing Your Translation for Publication<br>**Module Number:** 6<br>**Duration:** 60 minutes<br>**Level:** Experienced Translator<br>**Goal:** Execute a systematic, tiered verification of project formatting to achieve "clean" checklist results.
---
**Introduction: Why Order Matters**
*Time: 5 minutes*
In Paratext, formatting checks are hierarchical. If your chapter/verse sequence is broken, your "Long/Short Verse" results will be wrong. If your marker pairs aren't closed, your "Footnote" checks will be messy.
This module moves from Structural Integrity to Manual Audits to Fine-Tuning.
---
**Prerequisites**
- Completion of the initial FC (Formatting Checks) module.
- Familiarity with USFM markers.
- Editing permissions for all books in the project.
- Access to the project's official Style Guide.
---
**The 4-Stage Verification Workflow**
*Time: 45 minutes*
**Stage 1: Structural Integrity (The "Foundation" Check)**
You cannot trust any other checklist until this stage is 100% clean.
1. Run Basic Checks: ≡ Tab \> Tools \> Basic Checks.
2. Sort the results list for "Reference" then "Message":
	- a. The Logic: A missing \\c (Chapter) marker often triggers a "Duplicate Verse" error for the following verses.
	- b. The Fix: Restore missing markers. Do not move to Stage 2 until all errors have gone.
3. Check Marker Pairs: Ensure character styles (like \\nd...\\nd\*) are closed. \[Tools \> Checking Inventory \> Marker inventory\]
---
**Stage 2: The Census (The "Markers" Audit)**
This is a manual sanity check. Paratext provides the data; you provide the inference.
1. Open Checklist: ≡ Tab \> Tools \> Checking Inventories \> Markers.
2. The "Math" Audit: The counts column in the Markers Inventory displays the totals for each marker. Check the totals for opening and closing markers.
	- a. \\f must equal \\f\*
	- b. \\x must equal \\x\*
	- c. \\nd must equal \\nd\*
> 💡 *Example: If the Inventory shows 412 instances of **`\f`** but only 410 instances of **`\f*`**, you have two unclosed footnotes that will break the layout.*
1. The "Ghost" Audit: Look for markers with a count but zero text content. These "empty markers" can cause unexpected spacing issues in printed layouts. Delete or populate them.
---
**Stage 3: Content Anomalies (The "Outlier" Review)**
Now that the structure is solid, look for "human error" in the text volume.
1. Long/Short Verses: ≡ Tab \> Tools \> Checklists \> Long/short verses.
2. Investigation:
	- a. Too Long? Look for merged verses or accidental copy-paste repetitions.
	- b. Too Short? Look for text that was accidentally deleted during an edit.
3. Note: This checklist doesn't have a "Correct" toggle. Investigate, fix if necessary, and move on.
---
**Stage 4: Navigation & Scholarly Markup (The "Reader" View)**
The final stage ensures the reader can actually use the Bible.
1. Book Titles & Headings:
	- a. Verify \\h and \\mt1 against the project's official book list.
	- b. Check Section Headings for consistency. Ensure you aren't missing headings in major narrative shifts. Look for consistency – initial capitals, no punctuation (except "?")
2. References & Footnotes:
	- a. Watch for Red Icons: In the text, a Red Icon indicates an invalid book code or a non-existent chapter/verse number. But the icons are not shown in the checklist, only in the text itself.
	- b. Footnote Callers: Verify you are using \\f + for automated, sequential callers.
	- c. Footnote Origin: Verify that the \\fr (origin reference) matches the verse where the footnote is placed.
---
**Summary Checklist for Publication Readiness**
- [ ] Basic Checks: Zero "Sequence" or "Duplicate" errors.
- [ ] Marker Audit: Opening counts match closing counts exactly.
- [ ] Outliers: All unusually long/short verses have been manually verified as legitimate.
- [ ] Book Titles: Spelling is 100% verified against the Scripture Reference Settings, Book tab.
- [ ] Red Icons: All reference validation icons have been cleared.
- [ ] Style Guide: Section heading levels (\\s1, \\s2) are used consistently.
---
**Troubleshooting Guide**
<table header-row="true">
<tr>
<td>Symptom</td>
<td>Probable Cause</td>
<td>Fix</td>
</tr>
<tr>
<td>"Duplicate Verse" errors everywhere</td>
<td>Missing \\c (Chapter) marker.</td>
<td>Find where the new chapter should start and insert the marker.</td>
</tr>
<tr>
<td>Reference has a Red Icon</td>
<td>Typo in book code (e.g., Mtt vs Mat).</td>
<td>Correct the abbreviation to match the project's Scripture Reference Settings, Book tab.</td>
</tr>
<tr>
<td>Formatting looks "broken" in Print</td>
<td>Unclosed marker pair (e.g., missing \\f\*).</td>
<td>Use the Markers check to find the discrepancy and close the pair.</td>
</tr>
</table>
---
**Next Steps:** With a clean bill of formatting health, you are ready for Module 7: Glossary Linking, where you will link key terms to their definitions.

