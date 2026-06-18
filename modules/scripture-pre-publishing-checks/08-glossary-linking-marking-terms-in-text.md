# Glossary linking: marking terms in text

# Glossary linking: marking terms in text

---
## title: "Glossary Linking: Marking Terms in the Text"<br>course: "Finalizing Your Translation for Publication"<br>module_number: 7<br>duration: 60<br>level: Experienced Translator<br>goal: Mark glossary terms in the text using the Biblical Terms tool to help readers access definitions and explanations<br>prerequisites: "Glossary created; familiar with Biblical Terms tool; editing permissions for all books"
# Glossary Linking: Marking Terms in the Text
**Duration:** 60 minutes
**Level:** Experienced Translator
**Goal:** Mark glossary terms in the text using the Biblical Terms tool to help readers access definitions and explanations
---
## Introduction
**Time:** 5 minutes
You've created a helpful glossary defining difficult terms—"atonement," "covenant," "Pharisee," "righteousness." But how do readers know which words have glossary entries? How do they find the definition when they encounter an unfamiliar term?
In printed Bibles, glossary words are often marked with an asterisk (\*) before the term. In digital versions, they become clickable links. This module teaches you to systematically mark these glossary terms in your text.
**Example:** "Jesus taught in the \*synagogue" (asterisk before the term points to the glossary)
**Why wait until the end?** Throughout translation, you've made spelling corrections and terminology adjustments. If you marked glossary words early, spelling changes would have broken the links. Now that the text is finalized, you can confidently link glossary terms without fear of missing occurrences due to spelling variations.
### What This Module Covers
- Using the Biblical Terms tool to automate glossary linking
- Choosing appropriate linking frequency (first occurrence, all occurrences, etc.)
- Handling glossary entries not in the Biblical Terms list
- Working with phrases and special cases
- Verifying and correcting linking results
- Unlinking when needed
### Prerequisites
Before starting this module, ensure:
- Your glossary content is complete and finalized
- You have editing permissions for all books being published
- You're familiar with the Biblical Terms tool
- Your text is substantially finalized (minimal spelling changes expected)
- You understand \\w ... \\w\* markers (word list markers in USFM)
---
## Procedure Walkthrough
**Time:** 45 minutes
### Understanding Glossary Linking
**What happens when you link glossary words:**
In the text, Paratext wraps glossary words with markers:
- <span discussion-urls="discussion://2f7598a5-fd40-805e-aeb9-f689d0390ac5/32c598a5-fd40-807c-9825-e5d86dde7068/37a598a5-fd40-806e-8a53-001c4456fb29">`\w word\w*`</span><span discussion-urls="discussion://2f7598a5-fd40-805e-aeb9-f689d0390ac5/32c598a5-fd40-807c-9825-e5d86dde7068/37a598a5-fd40-806e-8a53-001c4456fb29"> for single words</span>
- `\w phrase with multiple words\w*` for phrases
**In printed publications:**
- The typesetter uses these markers to add an asterisk before the term
- Example: "Jesus taught in the \*synagogue"
**In digital publications:**
- The markers become clickable links
- Readers tap/click to see the glossary definition
- Links can connect directly to the relevant entry
**Linking frequency choices:**
- **First occurrence in every section:** Mark the term only the first time it appears in each section (most common)
- **First occurrence in every chapter:** Mark only the first use in each chapter
- **First occurrence in book:** Mark only once per book (minimal)
- **All occurrences:** Mark every instance (can clutter the text)
**Strategic principle:** Balance helping readers find definitions with not overwhelming the text with markers. "First occurrence in every section" usually provides good coverage without clutter.
---
### Part 1: Link Terms Already in Biblical Terms List
Most glossary terms are likely already in your Biblical Terms list (theological concepts, proper names, key terms). These are easiest to link.
### Step 1: Verify Editing Permissions
1. Ensure you have editing permission for all books
	- Go to **Menu \> Project settings \> User permissions**
	- If you have limited permissions, contact your project administrator
	- You need edit access to insert the \\w ... \\w\* markers
> **For New LTCs:** Check permissions before starting — not after. If you proceed without editing permissions for some books, those books will be skipped during linking and Paratext will warn you. It's much better to resolve this before you start than to discover gaps in your linking afterwards.
### Step 2: Open Biblical Terms Tool
1. Click in your project window
2. **≡ Tab** \> **Tools** \> **Biblical Terms**
> **Note:** If you don't see **Biblical Terms** in the Tools menu, click the down arrow (∨) at the bottom of the menu to show all options. Click the up arrow (∧) to shrink the menu again. Make sure to select **Biblical Terms** (the full tool), not **Biblical Terms Renderings** which opens a smaller panel.
> **For New LTCs:** Each tool in Paratext has its own menu bar. When instructions say **≡ Tab**, this refers to the menu of whichever tool is currently active — in this case, the Biblical Terms tool's own menu, not the main Paratext window menu.
### Step 3: Filter for Glossary Terms and Select Terms to Link
**Filter to show only glossary entries:**
1. In the Biblical Terms window, use the Categories filter
2. Select **Glossary entries**
3. This shows only the terms that have glossary entries — making it much easier to work systematically
**Work in small batches:**
- Don't try to link all glossary terms at once
- Start with 5-10 terms
- Review results before continuing
- This makes it easier to catch and fix problems
**To select terms:**
1. In the filtered list, find the terms you want to link
2. To select a single term, click on it
3. To select multiple consecutive terms, use **Shift+click**
4. To select individual non-consecutive terms, use **Ctrl+click** (Windows) or **Cmd+click** (Mac)
> **For New LTCs:** If you don't select any terms, only the term where your cursor is positioned will be linked. Always make an explicit selection when working in batches.
**Tip:** Start with the most important or frequently occurring terms to learn the process with high-value items.
### Step 4: Link Selected Renderings to Glossary
1. With terms selected: **≡ Tab** \> **Edit** \> **Link selected renderings to glossary...**
2. A dialog box appears with linking options
**In the dialog:**
**Linking frequency option:**
- Choose **"First occurrence in every section"** (recommended)
- Or select another frequency based on your project needs
**Why "first occurrence in every section"?**
- Provides regular reminders without cluttering
- Readers who skip to different sections will still see links
- Balances helpfulness with readability
1. Click **OK**
**What happens next:**
- Paratext searches through all your text
- It finds every occurrence of the selected terms
- It adds \\w ... \\w\* markers according to your frequency choice
- A Results list appears showing which verses were changed
> **For New LTCs:** If Paratext warns you that it doesn't have editing permission for some books, take note of which books are affected. You will need to resolve permissions for those books and run the linking again separately.
### Step 5: Review the Results List Carefully
**Critical step:** The Results list is your primary verification tool. Review it carefully for every batch.
**The Results list shows:**
- Each verse reference where linking occurred
- What was linked and to which glossary entry
- Example: "Linked **Da Baptiza Guy** to the glossary entry for **John Da Baptiza Guy**"
**For each item in the Results list:**
1. **Double-click on the verse** to view it in context
2. **Verify the linking is correct:**
	- Was the right word marked?
	- Does the link make sense in this context?
	- Were unintended words marked?
3. **Watch for problems:**
	- Words marked that shouldn't be (false positives)
	- Context where the term means something different
	- Linking broke across verse boundaries awkwardly
**Common issues to catch:**
**Homographs (words spelled the same, different meanings):**
- Example: A name that is also a common word
- Solution: Manually remove markers where inappropriate (see Part 3)
**Partial word matches:**
- Example: If linking "man," it might catch "woman" or "human"
- Solution: Review carefully, remove incorrect links
**Awkward phrase boundaries:**
- Example: Phrase linking might break in unexpected places
- Solution: Adjust manually or unlink and relink differently
> **For New LTCs:** Don't rush the Results list review. This is where you catch problems before they reach the reader. An experienced LTC develops a feel for which terms are likely to have false matches — until you develop that instinct, review every item carefully.
### Step 6: Make Manual Corrections
For any inappropriate links found in the Results list:
1. Navigate to the verse
2. Find the incorrectly marked text
3. Delete the \\w ... \\w\* markers manually in **Standard view**
4. Save your changes
**To remove markers:**
- Simply delete `\w` at the beginning and `\w*` at the end
- Leave the actual word/phrase text intact
---
### Part 2: Link Terms NOT in Biblical Terms List
Some glossary entries may not be in the standard Biblical Terms lists. For these, you need to create custom entries in your project's Biblical Terms list.
### Step 1: Identify Glossary Terms Not in Biblical Terms
Review your glossary list:
- Which terms don't appear in your Biblical Terms tool?
- These need custom entries
**Common examples:**
- Cultural terms specific to your translation
- Theological terms not in standard lists
- Local concepts you've explained in the glossary
### Step 2: Switch to Your Project's Biblical Terms List
Custom terms can only be added to your project's own Biblical Terms list, not the standard lists.
1. **≡ Tab** \> **Open biblical terms list...**
2. In the dialog, select your **project's Biblical Terms list** (shown at the top, e.g. "MP10 Project Biblical Terms")
3. Click **OK**
> **Note:** The project Biblical Terms list is described as "A custom list of biblical terms to which the project administrator can add terms from one or more built-in lists."
**Key Point:** Only the **project administrator** can add new terms to the project Biblical Terms list. The translation team adds renderings for existing terms. If a term needs to be added, contact your project administrator.
> **For New LTCs:** This is an important distinction. Your role is to add renderings for terms that already exist in the list. If a glossary term is missing from the list entirely, you need to ask the project administrator to add it — you cannot do this yourself unless you have administrator permissions.
### Step 3: Add a Custom Biblical Term (Administrator only)
The recommended approach is to search for the term in your reference text first, as this automatically finds the relevant verses.
**From your reference text:**
1. **≡ Tab** \> **Edit** \> **Find** and search for the term
2. A list of verses containing that term is displayed
**From the list of results:**
1. **≡ Tab** \> **Edit** \> **Add to Project Biblical Terms**, then choose your project
2. The **Edit Biblical Term** dialog opens with **Basic** and **Advanced** tabs
3. Edit the gloss if necessary (Basic tab)
4. Click the **Advanced** tab
5. Type in a name for the term
6. Click **OK**
> **Note:** While it is possible to add a term directly from the Biblical Terms tool, the search-first approach is better practice — Paratext automatically finds the relevant verses for you, saving time and reducing the risk of missing occurrences.
**Start with a few:**
- Don't create all custom entries at once
- Add 3-5 glossary terms
- Link them and review results
- Continue with more batches
### Step 4: Link Custom Terms
Once custom Biblical Terms entries have been created:
1. Select the newly created terms in the Biblical Terms tool
2. **≡ Tab** \> **Edit** \> **Link selected renderings to glossary...**
3. Choose linking frequency
4. Review Results list carefully
---
### Part 3: Special Cases and Advanced Tips
### Working with Phrases
**If you have both a phrase and a word in your glossary:**
Example: Both "Holy Spirit" (phrase) and "Spirit" (word)
**Best practice:** Link the longer phrase first
1. Link "Holy Spirit" first
2. Then link "Spirit"
3. This prevents "Spirit" from being linked inside "Holy Spirit" incorrectly
### Renderings with Wildcards (\*)
If you see `*` in a rendering (for example `Ar*`), this is normal and expected. It is a wildcard that matches different word forms such as suffixes, prefixes, or stem variations in your language.
### Terms with Multiple Renderings
**If a term has similar renderings in different contexts:**
Example: "Spirit" might appear as "Holy Spirit" or "evil spirit"
**Two options:**
**Option 1:** Create separate Biblical Terms entries
- One for "Holy Spirit"
- One for "spirit" (general)
- Link each appropriately with its own glossary entry
- Each new entry should contain **only the verses where that specific rendering is used**
**Option 2:** Link selectively
- Use automatic linking for one rendering
- Manually add/remove markers for the other contexts
**If a term has completely different renderings in different contexts:**
Sometimes a term may be rendered in two entirely different ways — not just variations of the same word. In this case, create a separate Biblical Terms entry for each distinct rendering, making sure each new entry contains **only the verses where that specific rendering is used**. Each entry can then be linked to its appropriate glossary entry.
> **For New LTCs:** The Biblical Terms tool already handles many common cases where terms have multiple meanings, with entries curated by knowledgeable translation consultants. Always check whether separate entries already exist before creating new ones.
### Homograph Challenges
**If a Biblical Term rendering is identical to another term:**
Example: A word that is both a person's name and a common noun
**Good news:** The Biblical Terms tool already handles many common homographs by providing separate numbered entries (for example ἅγιος-1 "holy/pure/divine", ἅγιος-2 "sanctuary", ἅγιος-3 "(Most) Holy Place"). Always check whether separate entries already exist before handling homographs manually.
**If you encounter a homograph not already separated in the tool:**
The solution is the same as for multiple renderings — create a separate Biblical Terms entry containing only the relevant verses.
1. Link the term
2. Carefully review ALL results in the Results list
3. Manually remove markers where it carries the wrong meaning
---
### Part 4: Verify and Finalize
### Review Complete Linking
After linking all glossary terms, review the Results list for each batch to confirm:
- Are glossary words appropriately marked?
- Is the frequency appropriate (not too many, not too few)?
- Are there obvious terms that should be linked but aren't?
**Check your glossary list:**
- Have all glossary entries been linked in the text?
- Are there glossary entries without corresponding text links?
- Should any glossary entries be removed (not actually used in the text)?
**Verify markers in Standard view:**
- Use **Standard view** to verify \\w ... \\w\* markers are correctly placed
- Note: **Preview** and **Formatted** views do not display markers — use **Standard** or **Unformatted** view for verification
> **For New LTCs:** Reviewing the Results list after each batch is much more efficient than scanning through the entire text. The Results list shows you exactly what was linked and where — use it as your primary verification tool.
### Unlink if Needed
**If you have many unwanted results from a linking operation:**
1. Select the same terms in the Biblical Terms tool
2. **≡ Tab** \> **Edit** \> **Unlink selected renderings from glossary...**
3. This removes \\w ... \\w\* markers for those selected terms
4. You can then retry with different settings or use a manual approach
**If you need to remove all linking:**
- **≡ Tab** \> **Edit** \> **Unlink all renderings from glossary...**
- Use with caution — this removes all glossary markers for all terms
**This is reversible:** Don't panic if a batch linking goes wrong — just unlink and try again.
> **For New LTCs:** Use "Unlink selected" rather than "Unlink all" whenever possible. "Unlink all" removes everything and you would need to start the entire linking process again.
### Coordinate with Typesetter
Before finalizing, inform your typesetter that glossary linking is complete. Note any special cases they should be aware of, such as terms handled manually or unusual linking decisions.
> **Note:** The typesetter already has access to all glossary terms through the GLO book and understands standard conventions. You do not need to provide a separate list of linked terms unless there are special cases.
---
## Verification Checklist
**Time:** 5 minutes
After completing this module, verify that you have:
**Preparation:**
- [ ] Verified editing permissions for all books (Menu \> Project settings \> User permissions)
- [ ] Glossary content is finalized
- [ ] Identified which terms need linking using the Glossary entries filter
**Linking Process:**
- [ ] Worked through glossary terms in small batches (5-10 at a time)
- [ ] Used "Link selected renderings to glossary..." function
- [ ] Chose appropriate linking frequency (usually "first occurrence in every section")
- [ ] Reviewed Results list carefully for each batch
- [ ] Made manual corrections for inappropriate links
**Special Cases:**
- [ ] Linked longer phrases before shorter words where both exist
- [ ] Checked whether separate Biblical Terms entries already exist for homographs and multiple renderings
- [ ] Created custom Biblical Terms entries (via project administrator) for glossary terms not in standard lists
- [ ] Reviewed and corrected homograph issues
**Quality Verification:**
- [ ] Reviewed Results list to verify appropriate marking
- [ ] Checked that all glossary entries are linked in text
- [ ] Removed inappropriate links (wrong context, wrong meaning)
- [ ] Verified linking frequency is appropriate (not too much/little)
- [ ] Verified markers display correctly in Standard view
**Coordination:**
- [ ] Notified typesetter that glossary linking is complete
- [ ] Documented any special cases
- [ ] Team has reviewed and approved linking approach
**Final Status:**
- [ ] All glossary terms appropriately linked in text
- [ ] No unintended or incorrect links
- [ ] Linking frequency supports reader usability
- [ ] Typesetter has been informed of any special cases
---
## Troubleshooting
**Time:** 3 minutes
### Common Issues and Solutions
**Problem:** "Link selected renderings to glossary... is greyed out/unavailable"
- **Solution:** Ensure: (1) You have terms selected in the Biblical Terms tool, (2) You have editing permissions, (3) Your project is not locked for editing. If still unavailable, contact your mentor or supervisor.
**Problem:** "Linking marked words I don't want linked"
- **Solution:** This is normal with automatic linking. Review the Results list carefully and manually remove unwanted \\w ... \\w\* markers. Consider whether the rendering is too broad or matches unintended words.
**Problem:** "A glossary term isn't being linked anywhere"
- **Solution:** Check: (1) Is the term actually in your text with that exact spelling? (2) Is it in your Biblical Terms list? (3) Does the rendering match exactly? Use Find to search for the term in your text.
**Problem:** "Linking added markers across verse boundaries awkwardly"
- **Solution:** Manually adjust the markers in Standard view. Move the \\w and \\w\* to appropriate boundaries within single verses. This may require breaking a phrase link or reformatting.
**Problem:** "I linked everything and now my text is cluttered with markers"
- **Solution:** You may have chosen "all occurrences" instead of "first occurrence in section." Use "Unlink selected renderings from glossary..." and redo with "first occurrence in every section" for better balance.
**Problem:** "Custom Biblical Terms entry I created isn't working for linking"
- **Solution:** Verify: (1) The rendering exactly matches how it appears in text, (2) The entry is saved properly, (3) You've selected it before using Link Renderings. Try creating the entry again if problems persist. Contact your project administrator if the issue continues.
**Problem:** "Some links work in some books but not others"
- **Solution:** Check: (1) Do you have editing permission for all books? (2) Are there spelling variations between books? (3) Run linking separately for problem books to identify the issue.
**Problem:** "Typesetter says they can't see the glossary markers"
- **Solution:** Ensure you've saved and sent your project. Markers use \\w ... \\w\* which are standard USFM and should be visible in Standard or Unformatted view. If typesetter still can't see them, contact your mentor or supervisor to verify markers are correct.
### When to Ask for Help
**Contact your project administrator if:**
- You need to add new terms to the project Biblical Terms list (administrator permission required)
**Contact your mentor or supervisor if:**
- Link selected renderings to glossary... function isn't working (technical issue)
- Markers aren't appearing in Standard or Unformatted view (display problem)
- Unlink function isn't removing markers (software problem)
**Contact your Translation Consultant if:**
- You're unsure which terms should be in the glossary (content decision)
- You need guidance on linking frequency (publishing standards)
- You're uncertain about handling homographs (translation issue)
> **For New LTCs:** Knowing who to contact for which type of problem is an important skill. Technical tool problems go to your mentor or supervisor; permission issues go to the project administrator; content and translation decisions go to the Translation Consultant. When in doubt, start with your mentor or supervisor.
---
## Summary and Next Steps
**Time:** 2 minutes
### Key Takeaways
- Glossary linking should be done near the end when text is finalized
- The Biblical Terms tool automates linking using "**Link Selected Renderings to Glossary**"
- "First occurrence in every section" provides good balance of helpfulness without clutter
- Always review the Results list carefully after each batch — automatic linking isn't perfect
- Work in small batches (5-10 terms at a time) for easier management
- Longer phrases should be linked before shorter words to avoid conflicts
- Custom Biblical Terms entries can only be added by the project administrator
- The Biblical Terms tool already handles many homographs with separate numbered entries
- Unlink selected (not all) is available if you need to redo linking for specific terms
### What Comes Next
**Congratulations!** You've completed all the finalizing for publication modules. Your next steps:
1. **Final comprehensive review:**
	- Review all modules' verification checklists
	- Confirm all tasks are complete
	- Address any remaining items
2. **Consultant approval:**
	- Submit complete project to Translation Consultant
	- Address any final feedback
	- Obtain sign-off for publication
3. **Typesetter coordination:**
	- Provide all necessary files and documentation
	- Communicate any special requirements
	- Review typeset samples
4. **Publication process:**
	- Follow your organization's publication workflow
	- Maintain archival copies
	- Celebrate the completion of this major milestone!
### Timeline Guidance
Glossary linking typically takes:
- **Planning and preparation:** 1 hour
- **Linking terms in batches:** 3-5 hours (depending on number of terms)
- **Review and corrections:** 2-4 hours
- **Creating custom entries:** 1-2 hours (if needed)
- **Final verification:** 1 hour
- **Total:** 8-13 hours spread over several work sessions
**Best approach:** Work in focused sessions
- Link 5-10 terms
- Review Results list immediately
- Make corrections
- Take a break
- Continue with next batch
**Don't rush:** Glossary links are visible to readers. Quality matters more than speed.
---
## Support Resources
### Getting Help
**WhatsApp/Email Support:**
- Post questions in your project team WhatsApp group
- For Biblical Terms tool questions, contact your mentor or supervisor
- For glossary content questions, contact your Translation Consultant
- For permission issues, contact your project administrator
**Related Documentation:**
- Full Paratext manual: [https://manual.paratext.org/](https://manual.paratext.org/)
- Glossary linking: Section 24.10
- Biblical Terms tool: Section 10
### Additional Resources
- Maintain a log of which glossary terms have been linked
- Document any special cases or manual interventions
- Keep notes about what worked well for future publications
### Final Encouragement
Finalizing a translation for publication is detailed, careful work. Each module in this course has guided you through essential quality checks that protect your years of translation effort and serve your readers well.
Take pride in the thoroughness of your preparation. Readers may never know about the hours spent checking parallel passages, verifying biblical terms, or linking glossary entries—but they'll benefit from a clear, accurate, professional publication that helps them engage with God's Word.
Well done!
<empty-block/>

