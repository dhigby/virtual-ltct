# glossary linking

# glossary linking

This is an excellent, comprehensive module. You have a very clear grasp of the Paratext workflow, especially the nuances of the **Biblical Terms** tool. The "For New LTCs" (Language Technology Consultants) callouts are a fantastic touch—they anticipate the specific anxieties and common errors of someone new to the role.
Since this is the final module in the series, my suggestions focus on tightening the technical accuracy of the USFM markers and improving the visual flow for the learner.
---
## 1. Technical Precision: USFM Markers
In your "Understanding Glossary Linking" section, you used four backslashes (\\\\\\) for the markers. While I see you're likely trying to escape the characters for a specific display format, in a training manual, it’s usually best to show the **actual USFM syntax** as it appears in Paratext Standard View.
- **Correction:** Use single backslashes.
- **Clarification:** Explicitly state that the `\w` marker uses a pipe `|` if it points to a specific glossary headword that differs from the text (though Paratext's automation handles this, manual tweaks often require it).
> **Refined Example:**
	- Single word: `\w synagogue\w*`
	- Phrase: `\w Holy Spirit\w*`
	- *Advanced:* `\w synagogues|synagogue\w*` (Links the plural word to the singular glossary entry).
---
## 2. The "Order of Operations" for Phrases
You correctly mention linking longer phrases (e.g., "Holy Spirit") before shorter words ("Spirit"). This is a **critical** tip. I suggest making this a "Golden Rule" or a highlighted warning box. If they do it in the wrong order, "Holy Spirit" becomes `\w Holy \w Spirit\w*\w*`, which is a mess to fix.
---
## 3. Workflow Optimization: Step 2 & 3
In **Step 2 (Open Biblical Terms)** and **Step 3 (Filter)**, there is a small UI detail that often trips people up:
- **Suggestion:** Remind users to ensure their **Filter** is set to "All books" or "Current book" depending on their goal. Often, users wonder why only a few results show up when they actually have the filter set to "Current Verse" or "Current Chapter" by mistake.
---
## 4. Visual Layout Suggestions
To make the 60-minute module feel less like a wall of text, consider using a comparison table for the frequency choices. It makes the "Strategic principle" much easier to digest.
<table>
<tr>
<td>**Frequency Choice**</td>
<td>**Pros**</td>
<td>**Cons**</td>
</tr>
<tr>
<td>**First in Section**</td>
<td>High visibility; stays helpful as readers skip around.</td>
<td>Minimal clutter.</td>
</tr>
<tr>
<td>**First in Chapter**</td>
<td>Very consistent.</td>
<td>Can be redundant in short chapters.</td>
</tr>
<tr>
<td>**All Occurrences**</td>
<td>Readers never miss a definition.</td>
<td>Text becomes very "busy" and hard to read.</td>
</tr>
</table>
---
## 5. Clarifying the "Administrator" Role
In **Part 2, Step 2**, you mention that only the Administrator can add terms. This is a common point of frustration.
- **Suggestion:** Add a small tip on *how* to identify who their administrator is (Menu \> Project Settings \> Project Properties and Users).
---
## 6. The "Results List" - The Safety Net
You emphasize the Results list well. One minor addition:
- **Tip:** Mention that the Results list can be **saved** or **printed**. If they are working in batches over several days, keeping a record of what they "Approved" in the results list is a great habit for an experienced LTC.
---
## Final Review Polish
- **Duration:** 60 minutes is realistic for the *teaching*, but as your "Timeline Guidance" suggests, the actual task takes 8-13 hours. Ensure the user knows this 60-minute module is the **instructional walk-through**, not the time to complete the whole project.
- **Tone:** The "Final Encouragement" is a lovely way to end the course. It adds a sense of professional dignity to what can be very tedious work.
**Verdict:** With those minor USFM syntax tweaks and a bit of visual formatting (like the table), this module is ready for publication! You’ve done a great job balancing the "how-to" with the "why."
Are you ready to wrap this up, or is there a specific section of the Biblical Terms tool you're still worried about?

