# Back matter

# Back matter

This is a solid, well-structured module. You have clearly defined the "why" (reader engagement) alongside the "how" (USFM markers and Paratext books), which is essential for experienced translators who might view back matter as a mere technicality.
Below is a review of the content with some suggestions to sharpen the technical guidance and instructional flow.
---
## Overall Assessment
**Strengths:** \* **Clear Hierarchy:** The use of USFM markers (\\mt1, \\is, \\ip) is practical and immediate.
- **Process-Oriented:** Integrating the typesetter early in the conversation is a "pro move" that saves hours of rework.
- **Tone:** Professional, encouraging, and appropriately leveled for experienced translators.
**Areas for Improvement:**
- **The Map Labeler Section:** Since the plugin steps are pending, consider adding a brief "Geographic Consistency" tip to bridge the gap.
- **USFM Specificity:** A few more common markers for Appendices would be helpful to prevent formatting errors later.
---
## Specific Feedback & Suggestions
### 1. Introduction & Maps
The distinction between the translator's role (providing names) and the typesetter's role (creating the map) is excellent.
- **Suggestion:** In the "Steps to complete" for Maps, add a bullet point about **Consistency Checks**.
	> ◦ "Run a Wordlist check for place names to ensure 'Jerusalem' isn't spelled two different ways before exporting for the Map Labeler."
### 2. Part 2: Drafting the Introduction
The instructions for the `INT` book are clear.
- **Technical Tip:** Mention that the `INT` book should appear **before** the New Testament/Old Testament books in the project sequence.
- **Formatting:** You use `\is` for section headings. You might also mention `\iot` (Introduction Outline Title) and `\io1` (Introduction Outline) if they want to include a "Table of Contents" for that specific book.
### 3. Part 3: Appendices
This is often where projects get "messy" in Paratext.
- **Clarification on Tables:** Many appendices (like Weights and Measures) are best formatted as tables. Briefly mentioning the `\tr` (table row) and `\tc1` (table cell) markers—or suggesting the use of the Table insert tool in Paratext—would be high value for an "Experienced" level course.
- **Glossary Markers:** While you suggested `\p`, the standard USFM marker for glossary entries is often `\k` (key term) or `\li` (list item).
	> **Example:** \> `\li \k Atonement\k* - The act of...`
---
## Suggested Content Tweaks
### Revised Glossary Example
To align better with standard Paratext practices, I'd suggest updating the example in Part 3:
> **Example Glossary Structure:**
	`\h Glossary`
	`\mt1 Glossary of Important Terms`
	`\s A`
	`\p \k Atonement\k* : The act of making amends for sin...`
	`\p \k Baptism\k* : A ceremony using water to show...`
### Added "Pro-Tip" for Coordination
Under "Working with Your Typesetter," add:
> 💡 **Digital vs. Print:** Ask if the publication will also be used in a mobile app (like Scripture App Builder). Some appendices that work in print (like a Map Index) may need different handling for digital hyperlinks.
---
## Technical Formatting Check
- **Markers:** You used `\mt1`, `\is`, and `\ip` correctly.
- **Book Codes:** `INT`, `XXA`, `BAK` are all accurate Paratext/USFM standards.
- **Checklist:** The "Verification Checklist" is comprehensive and serves as a great summary for the learner.
## Final Verdict
**Approved with Minor Edits.** Once the Map Labeler Plugin documentation is finalized and the table formatting tip is added, this module will be a highly effective resource for translation teams approaching the finish line.
Would you like me to draft a specific "Table Creation" mini-guide to include in the Appendix section?

