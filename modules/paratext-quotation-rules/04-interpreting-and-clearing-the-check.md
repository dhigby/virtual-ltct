# Lesson 4 — Interpreting and Clearing the Check

**Estimated time:** 75 minutes

> This lesson uses the `tamba` fictional project in **Phase B** (configured, with five
> seeded errors). See the [mentor guide](06-mentor-guide.md) for how the facilitator stages
> Phase B.

**Purpose:** A configured check still produces results — and on a real project the whole value
of your work is being able to look at each one and decide, correctly and quickly, whether to
fix the *text* or fix the *settings*. This lesson is where configuration becomes
troubleshooting: reading results, classifying them, and clearing a book to zero without
silencing correct Scripture.

## Learning objectives

By the end of this lesson you will be able to:

- Classify a check result as a real error or a configuration problem.
- Take the correct corrective action for each type.
- Work through a result set systematically to reach zero actionable errors.

## Connect

By now the Tamba project is configured (Lessons 2 and 3). Run the check and you will still see
results — but now they *mean* something. The skill this lesson builds is the judgment call you
will make dozens of times on a real project: **fix the text, or fix the settings?**

**✏️ Reflection.** Think back to the flood of results you saw in Lesson 1, and to a project you
support:

- When a check lights up, what is your first instinct — start editing verses, or step back and
  ask whether the settings are right?
- What would go wrong if you "fixed" a check result by deleting a quotation mark from
  correctly translated text?
- How might you tell, at a glance, whether a batch of similar results is one settings gap or
  many separate text mistakes?

## Content

*This closes the **Translation Tools** "use and troubleshoot" loop: you configured the check,
now you interpret it. The core discipline is never editing correct text just to make a result
disappear — that corrupts the translation to quiet a tool.*

After configuration you will typically have two kinds of results:

1. **Real errors** — a mark is genuinely missing, extra, or at the wrong level. Fix these in
   the text.
2. **Configuration problems** — the check flags something that reveals a gap in your inventory
   or rules. Fix these by refining the configuration, not by editing the text.

![The Quotations check results panel after full configuration, showing a manageable list of results. One result is highlighted with the verse open alongside it, demonstrating how to read a result entry (location, message, and the text in context).](ss-L429-results-with-highlight.png)

Read each result as three parts: the **location** (book, chapter, verse), the **message** (what
the check thinks is wrong), and the **text in context** (open the verse and look). A useful
heuristic: the same message repeated across many verses usually points to a **configuration
gap**; a one-off result usually points to a **real error** in that verse.

**Key takeaways**

- Every result is one of two types: real error (fix the text) or configuration problem (fix the
  Quote marks tab or Quotation types tab).
- Never edit correct text to make a result disappear — fix the configuration instead.
- Work book by book — Matthew first, then expand. A full-NT result list is overwhelming; a
  single-book list is actionable.

## Challenge

The `tamba` project is in **Phase B**: fully configured, but seeded with five deliberate
issues. Your job is to triage them correctly, then clear a book to zero. Your filled-in tables
and reasoning are what a mentor reviews.

### Task 4.1 — Triage a dirty result set

The `tamba` project has been seeded with the following issues. **Before reading further, fill
in the Your prediction column for all five rows — write 1 (Real error) or 2 (Configuration
problem).** Only after you have written a prediction for every row should you read the
discovery prompts and open the verses.

| # | Location | Check message | Your prediction | Actual type |
|---|----------|--------------|----------------|------------|
| 1 | Matthew 5:3 | Missing closing quotation mark | ? | ? |
| 2 | Luke 4:18 | Unexpected opening quotation mark | ? | ? |
| 3 | John 3:16 | Invalid Second level quotation mark | ? | ? |
| 4 | Acts 2:25 | Unclosed quotation mark at paragraph break | ? | ? |
| 5 | Romans 1:1 | Unexpected closing quotation mark | ? | ? |

**✏️ Discovery prompts for each item:**
- Matthew 5:3 opens a speech that runs through verse 5:12. What closing mark should appear at
  5:12, and what does the check report when it is absent?
- Luke 4:18 contains an Isaiah citation. Does Tamba use dialogue marks for narrator scripture
  citations? If not, what should you do with a stray opening `“` before the citation?
- John 3:16 is inside Jesus's speech to Nicodemus. The inner quotation at this verse is Second
  level. What character should the Second level opening mark be in Tamba? If you see a straight
  `"` (U+0022) instead, is that a valid Tamba Second level mark?
- Acts 2:25–28: Peter cites Psalm 16 in Second level marks as one continuous span across
  several paragraph breaks. Recall the Tamba conventions in The Fictional Project table: what
  does Tamba do with quotation marks at each new paragraph of continued speech? Does this text
  follow that convention?
- Romans 1:1 has no dialogue. How could `’` (U+2019) inside a word cause the check to report a
  quotation problem? What is the correct fix?

**Expected resolution (answer key):**

| # | Actual type | Action |
|---|-------------|--------|
| 1 | Real error | Add the missing `”` (U+201D) at the end of Matthew 5:12. The First level speech opened with `“` (U+201C) at verse 5:3; the closing mark was deleted. |
| 2 | Real error | Delete the stray `“` (U+201C) before the Isaiah citation in Luke 4:18. Tamba does not mark narrator scripture citations; the mark was added by mistake. |
| 3 | Real error | The Second level opening mark in John 3:16 is a straight `"` (U+0022) rather than `‘` (U+2018). Replace it with `‘` (U+2018) and confirm the closing `’` (U+2019) is also present. |
| 4 | Real error | Tamba restarts quotation marks at every paragraph break, but the Psalm 16 citation runs from 2:25 to 2:28 as one unbroken Second level span. Edit the text: close with `’` (U+2019) at the end of each paragraph and reopen with `‘` (U+2018) at the start of the next, so every paragraph carries a complete pair. |
| 5 | Configuration problem | The `’` (U+2019) in Romans 1:1 is an apostrophe inside a word, which Paratext reads as the Second level closing mark with no matching opener. Navigate to ☰ > Project settings > Language Settings > Other Characters tab and add `’` (U+2019) to the Word-medial punctuation field. |

### Task 4.2 — Reach zero actionable errors

**Goal:** Work through the full result list for the `tamba` project until every result has been
cleared by fixing the text or adjusting the configuration.

**Steps:**
1. Limit the scope to Matthew first. Work that book to zero results before expanding.
2. Work through the results top-to-bottom.
3. For each result: open the verse, classify it, take the appropriate action.
   - **Real error:** edit the verse text to fix the mark, then re-run.
   - **Configuration problem:** adjust the Quote marks tab or Quotation types tab, then re-run.
     Do not edit the text to make marks disappear — fix the configuration instead.
4. After fixing a batch of real errors, re-run the check to confirm the count drops.
5. Once Matthew is clean, expand the scope one book at a time through the NT.
6. When unsure whether a result is a real error or a configuration problem, look at how often
   the same message appears: the same message repeated across many verses usually points to a
   configuration gap; a one-off result usually points to a real error in that verse.

**Completion criteria:**
- The Quotations check shows 0 results for the current scope.

![The Quotations check results panel showing zero results, confirming the check is clean.](ss-L477-zero-results.png)

**✏️ Produce this (a mentor will review it).** Submit your completed triage table (predictions
+ actual types) and a one-line note for each result on the action you took and why. The point a
mentor is checking is your *reasoning* — that you fixed text only for real errors and settings
only for configuration problems.

## Change

**Self-assessment — can you explain it to a colleague?**

1. A result in Luke 22:35 says "Missing closing quotation mark." You open the verse: Jesus is
   speaking and the speech continues through verse 38, where it closes correctly with the final
   `”`. What type of result is this, and what should you do?
2. You have cleared all real errors in Matthew but 6 results remain. You have checked the text
   carefully — the translation is correct. What should you do next?
3. The check shows an unexpected Second level quotation mark in Romans 8:1, a verse with no
   dialogue. What is the most likely cause and correct action?

*You should be able to say:* (1) A configuration problem — check whether a paragraph marker
(`\p`) sits between verses 35 and 38; if so the check expects either a closing mark at each
paragraph break or a First level Quote Continuer. Configure the First level Quote Continuer if
Tamba carries speech across paragraphs without reopening, or verify the USFM has no unexpected
paragraph breaks in the speech. (2) They are configuration problems, not text errors — review
each to find the missing rule or setting (e.g. a Quotation types setting that doesn't match how
the language uses marks there), adjust, and re-run until they clear. (3) A real error — a stray
quotation character, likely copied from a source text; open the verse, find the stray mark, and
delete it (a text correction, not a configuration change).

**✏️ Take it to your context.** Recall a real check result you (or a translator you support)
have seen. Would you now classify it as a real error or a configuration problem — and what
would you have done differently knowing the two-type distinction?

**Next step.** You have taken one language, Tamba, from an unconfigured check to zero
actionable errors. The [scenario bank](05-scenario-bank.md) puts the full workflow — inventory
→ rules → check → triage — to work on three unfamiliar languages (Runda, Menda, Waku), each
with its own conventions and edge cases.

---

Previous: [Lesson 3 — Configuring Quotation Types](03-configuring-quotation-types.md) · Next: [Scenario Bank — Language Scenario Practice](05-scenario-bank.md)
