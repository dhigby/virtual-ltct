# Assessment Quiz — Configuring Quotation Rules in Paratext

## Assessment quiz (15 questions)

This quiz covers all five lessons of the course, including the scenario bank. You need
**80% (12/15)** to pass. Questions are a mix of recall, scenario-based application, and
true/false. Everything asked here is drawn directly from the lessons — work them first.

---

### Section 1: What the Quotation Check Does (Questions 1-3)

**Question 1:** Which two inputs must be configured before the Quotation check can produce
trustworthy results?
- A) The Characters inventory and the Markers inventory
- B) The Quote marks tab (which characters are quote marks) and the Quotation types tab
  (when marks are expected)
- C) Language Settings and the Footnotes check
- D) The Copy quote mark settings button and the Example preview

**Question 2:** You run the Quotations check on an unconfigured project and it returns
**zero results**. Does this mean the translation has no quotation errors?
- A) Yes — zero results confirms the marks are all correctly matched
- B) No — with nothing configured, Paratext does not know which characters to look for, so
  it is reporting silence, not correctness
- C) Yes, but only for the first-level quotes
- D) No — zero results always means the check failed to run

**Question 3:** True or False: When first working through check results during
configuration, you should run the check on the whole New Testament at once so you can see
the full scope of the problem before fixing anything.
- A) True
- B) False

---

### Section 2: Setting Up Quote Marks (Questions 4-6)

**Question 4:** On the Quote marks tab, what does the **Quote Continuer at new paragraph**
column configure, and when should it be left blank?
- A) The character that ends every quotation; leave blank only at Third level
- B) The character repeated at the start of a new paragraph when one speech continues
  across the break; leave it blank if the language closes and reopens marks at each
  paragraph instead
- C) The apostrophe used inside words; leave blank if the language has no contractions
- D) The default straight quote character; never leave it blank

**Question 5:** A language uses `’` (U+2019) as both its Second level closing mark **and**
as an apostrophe inside words, and the check is flagging those in-word apostrophes as
unclosed quotations. Where in Paratext 9.5 do you fix this, and what do you enter?
- A) In the Quotation Rules dialog, Quote marks tab — remove `’` from the Closing cell
- B) In the Quotation types tab — set Normal to "Quote marks are optional"
- C) In ☰ > Project settings > Language Settings > Other Characters tab — add `’`
  (U+2019) to the Word-medial punctuation field
- D) In the Run basic checks dialog — untick the Quotations check for that book

**Question 6:** After entering the opening and closing characters for each nesting level on
the Quote marks tab, what is the recommended way to confirm you selected the correct
characters before clicking OK?
- A) Re-run the whole check and count the results
- B) Read the character's Unicode code point in the frontmatter
- C) Check the **Example** section at the bottom of the dialog, which shows a live preview
  of the configured marks in sample text
- D) Ask the project administrator to enable the check

---

### Section 3: Configuring Quotation Types (Questions 7-9)

**Question 7:** What does the **Quotation types** tab control, and how is it different from
the Quote marks tab?
- A) It sets which Unicode characters are used; the Quote marks tab sets the font
- B) It controls whether Paratext *expects* marks for each category of speech (required,
  forbidden, or optional); the Quote marks tab sets *which characters* are used
- C) It is an older version of the Quote marks tab and does the same thing
- D) It controls only Third level quotes; the Quote marks tab controls the first two levels

**Question 8:** In the recommended settings, **Indirect** speech ("He said that the road
was long") is set to which option, and why?
- A) Use quote marks — reported speech should always be marked
- B) Quote marks are optional — reported speech may or may not be marked
- C) Never use quote marks — reported speech carries no direct marks, so the check should
  flag any that appear
- D) It is not one of the seven quotation types

**Question 9:** After reviewing Tamba's text, the team confirms that a self-quote (a
character quoting their own earlier words) **must** be marked exactly like normal direct
speech, but the recommended default for Self quote is "Quote marks are optional." What do
you do?
- A) Leave it — optional already allows marks
- B) Click **Custom settings** and change Self quote to **Use quote marks** so missing
  marks are flagged
- C) Delete the Self quote row from the tab
- D) Change every type to Use quote marks

---

### Section 4: Interpreting and Clearing the Check (Questions 10-12)

**Question 10:** The check flags **Romans 1:1** — a verse with no dialogue — for an
unexpected Second level closing mark, caused by a `’` (U+2019) apostrophe inside a word. Is
this a real error or a configuration problem, and what is the correct action?
- A) Real error — delete the apostrophe from the word
- B) Configuration problem — add `’` (U+2019) to the Word-medial punctuation field in
  Language Settings; do not edit the text
- C) Real error — replace the apostrophe with a straight quote
- D) Configuration problem — set Normal to "Never use quote marks"

**Question 11:** **John 3:16** (inside Jesus's speech to Nicodemus) is flagged for an
invalid Second level mark, and you find a straight `"` (U+0022) where Tamba's Second level
opening mark should be `‘` (U+2018). How do you classify and fix this?
- A) Configuration problem — add U+0022 to the Quote marks tab
- B) Real error — replace the straight `"` (U+0022) in the text with `‘` (U+2018) and
  confirm the closing `’` (U+2019) is present
- C) Configuration problem — the Second level is misconfigured; no text edit is needed
- D) Not an error — straight quotes are always acceptable at Second level

**Question 12:** You have cleared every genuine text error in Matthew, but 6 results
remain and the translation reads correctly. What is the right next step?
- A) Edit the correct text to remove the flagged marks so the count reaches zero
- B) Treat the remaining 6 as configuration problems — identify the missing rule or
  setting, adjust the configuration, and re-run until they clear
- C) Ignore them and expand the scope to the rest of the NT
- D) Disable the Quotations check for Matthew

---

### Section 5: Applying the Workflow to Unfamiliar Languages (Questions 13-15)

**Question 13:** In the **Menda** project, Second level uses single guillemets in reversed
order: `›` (U+203A) opens the embedded speech and `‹` (U+2039) closes it. You accidentally
enter `‹` in the Opening cell and `›` in the Closing cell. What does the check report?
- A) Nothing — the two characters are interchangeable
- B) It fires on every Second level opening and closing mark, because the fields are
  reversed; swap them so `›` is Opening and `‹` is Closing, and confirm via the Example
- C) Only Third level errors
- D) A single warning that resolves itself on re-run

**Question 14:** In the **Waku** project the First level opening **and** closing mark is
the **same character** — an em dash `—` (U+2014) — and after configuration some flagged
em-dash results (parenthetical dashes, not speech) cannot be cleared by configuration.
What is the correct handling for those residual results?
- A) Delete every em dash in the affected verses
- B) Add U+2014 to the Word-medial punctuation field
- C) Rewrite the parenthetical verses to use different punctuation where feasible, and
  where it is not, add a Project Note (☰ > Insert > Project note...) so the consultant can
  verify them — the results will remain but are documented
- D) Set First level to "Never use quote marks"

**Question 15:** True or False: For every language, reaching a correct configuration always
means the Quotations check ends at zero results.
- A) True
- B) False

---

## Answer key

1. B | 2. B | 3. B | 4. B | 5. C | 6. C | 7. B | 8. C | 9. B | 10. B | 11. B | 12. B | 13. B | 14. C | 15. B

**Rationales**

1. B — Lesson 1: the check needs both the Quote marks tab and the Quotation types tab.
2. B — Lesson 1: zero results on an unconfigured project is silence, not confirmed
   correctness; Paratext does not yet know which characters are marks.
3. B (False) — Lesson 1 scope tip: use Current Book (Matthew) during configuration; expand
   to the full NT only after Matthew is clean.
4. B — Lesson 2: the Quote Continuer at new paragraph repeats the mark when one speech
   spans paragraphs; leave it blank if the language closes and reopens at each break.
5. C — Lesson 2 Exercise 2.3: the apostrophe/closing-quote conflict is resolved in Language
   Settings > Other Characters > Word-medial punctuation, not in the Quotation Rules dialog.
6. C — Lesson 2: verify entered characters using the Example preview at the bottom of the
   dialog.
7. B — Lesson 3: the Quotation types tab controls whether marks are expected; the Quote
   marks tab controls which characters are used.
8. C — Lesson 3 recommended settings: Indirect = Never use quote marks; reported speech
   carries no direct marks.
9. B — Lesson 3 Exercise 3.2: switch to Custom settings and set Self quote to Use quote
   marks so missing marks are flagged.
10. B — Lesson 4 Exercise 4.1 item 5: a word-medial apostrophe is a configuration problem
    fixed in Language Settings, not by editing text.
11. B — Lesson 4 Exercise 4.1 item 3: a straight `"` (U+0022) at Second level is a real
    error; replace it with `‘` (U+2018).
12. B — Lesson 4 Exercise 4.2 / check-your-understanding 2: remaining results on correct
    text are configuration problems, not reasons to edit good text.
13. B — Scenario B (Menda): reversed single-guillemet fields fire on every Second level
    mark; swap Opening/Closing and confirm with the Example.
14. C — Scenario C (Waku): residual same-character em-dash results are reworded where
    feasible or documented in a Project Note for the consultant.
15. B (False) — Scenario bank summary: some languages (like Waku) always require human
    review of residual results; zero configuration errors does not always mean zero results.
