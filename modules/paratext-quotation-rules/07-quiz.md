# Assessment Quiz — Configuring Quotation Rules in Paratext

## Assessment quiz (20 questions)

This quiz covers all five lessons of the course, including the scenario bank. You need
**80% (16/20)** to pass. Questions are a mix of recall, scenario-based application, and
true/false. Everything asked here is drawn directly from the lessons — work them first.

---

### Section 1: What the Quotation Check Does (Questions 1-3)

**Question 1:** Which two inputs must be configured before the Quotations check can produce
trustworthy results?
- A) Language Settings and its Other Characters tab
- B) The book scope and the Quotations tick box in Run basic checks
- C) The Quote marks tab and the Quotation types tab
- D) The Example preview and the Copy quote mark settings button

**Question 2:** `TAMBA` is still at Paratext's default Quotation Rules. The Quotations check on
all books reports five results in John 17, each "Opening quote mark found without matching
closing quote mark: “". The team lead asks whether they should add the closing marks. What do
you tell them?
- A) Yes; five unclosed speeches in one chapter are real errors in the text
- B) Not yet; these are still Paratext's settings, so correct those first
- C) Yes, but only after the Quotation types check has been enabled too
- D) No; delete the five opening marks instead, so no speech is left open

**Question 3:** True or False: On a project still at Paratext's default Quotation Rules, the
Quotations check can report a handful of plausible results, a flood of results, or "No errors
found", and none of these can be trusted until the settings match the team's conventions.
- A) True
- B) False

---

### Section 2: Setting Up Quote Marks (Questions 4-8)

**Question 4:** Runda's conventions have no Third level, but its Quote marks tab still holds
Paratext's defaults at Third level (`“` `“` `”`). What do you do with the Third level cells?
- A) Leave the defaults; an unused level produces no results
- B) Change them to `«` `«` `»`, so they match Runda's First level
- C) Change them to `‘` `‘` `’`, so they match Runda's Second level
- D) Set all three Third level cells, Continuer included, to *none*

**Question 5:** Correcting `TAMBA`'s defaults, you add `pi/pi` to the **Continuer required at**
list first. The five John 17 results clear and the check reports "No errors found". The Second
and Third level continuer cells still hold the defaults `‘` and `“`, and the list still lacks
`m/q1` and `s1/pi`. What do you do?
- A) Set both continuers to *none* and type Tamba's full list into the field
- B) Leave them; "No errors found" shows the remaining defaults suit Tamba
- C) Leave them until newly drafted text produces results that point to them
- D) Set the Second and Third level continuers to `“`, to match First level

**Question 6:** On the Quote marks tab you have changed every cell that differed from the
team's conventions. Before clicking OK, what is the recommended way to confirm you picked
the right characters?
- A) Hover each column label and read the character named in the status bar
- B) Compare the shapes in the grid with the marks in the convention table
- C) Check the sample text in the Example section at the bottom of the dialog
- D) Click OK, then run the check on the whole NT and compare the counts

**Question 7:** On `RUNDA` you correct the First level cells to `«` `«` `»` but leave the
**Continuer required at** list at the default `p p/q1 b/q1`. The check on Matthew reports
"Expected continuers [«] are missing" at Matthew 2:6 and 4:6, both poetry quoted inside
speech. What does this point to, and what is the fix?
- A) The list is too short: add `q1` as well, then run the check again
- B) The list is too long: replace it with just `p`, then run the check again
- C) The continuer cell is at fault: clear First level's continuer, then run again
- D) The text is missing continuers: add `«` before both poems, then run again

**Question 8:** Runda uses `’` (U+2019) as its Second level closing mark and also as an
apostrophe inside words such as *don’t*, and the check flags those apostrophes as word-medial
closing quotes. You add `’` to ☰ > Project settings > Language Settings > Other Characters >
**Word-medial punctuation**, click OK, close the results panel and run the check again. What
happens?
- A) The word-medial apostrophe results are gone from the new run
- B) Paratext won't save the setting, because `’` is already a quote mark
- C) Those results clear, but every Second level `’` is now flagged instead
- D) Every word-medial apostrophe result is still reported, as before

---

### Section 3: Configuring Quotation Types (Questions 9-11)

**Question 9:** Tamba repeats its First level opening mark `“` (U+201C) at the head of each
paragraph when a speech runs on. The recommended setting for **Continued quotation** is
*Never use quote marks*. With the Quotation types check enabled, what should you do?
- A) Choose Custom settings and set Continued quotation to Use quote marks
- B) Leave it; Continued quotation applies only to Second and Third level speech
- C) Leave it, and clear the First level continuer cell on the Quote marks tab
- D) Leave it; the continuer cell on the Quote marks tab overrides this setting

**Question 10:** The Tamba team tells you that reported speech ("He told them that the harvest
was near") is never written with quote marks. The recommended setting for **Indirect** is
*Quote marks are optional*. What do you do?
- A) Leave Indirect at Optional, which already accepts unmarked reported speech
- B) Choose Custom settings and set Indirect to Use quote marks
- C) Choose Custom settings and set Indirect to Never use quote marks
- D) Leave Indirect at Optional and add a Project Note at each reported-speech verse

**Question 11:** You have switched Tamba's Quotation types tab to **Custom settings** to change
three types. The team confirms that a self-quote must be marked just like any other direct
speech. **Self quote** currently shows *Use quote marks*. What do you do with it?
- A) Change it to Quote marks are optional, the safer choice on a custom tab
- B) Leave it at Use quote marks, and go on to the three types that need changing
- C) Change it to Never use quote marks, since Normal already checks direct speech
- D) Change it to Quote marks are optional and note each self-quote for the consultant

---

### Section 4: Interpreting and Clearing the Check (Questions 12-17)

**Question 12:** `TAMBAB` is configured with the Lesson 2–3 settings, including Quotation from
another source = *Quote marks are optional*. The check reports at Luke 4:18: "Closing quote
mark is possibly missing before verse 20". Tamba does not mark narrator scripture citations,
and you find a `“` (U+201C) immediately before the Isaiah citation in 4:18. How do you
classify and handle this result?
- A) Real error: add a closing `”` (U+201D) before verse 20, where the check points
- B) Configuration problem: set Quotation from another source to Use quote marks
- C) Neither: leave the result and document it with a Project Note at 4:18
- D) Real error: delete the stray `“` (U+201C) before the citation in 4:18

**Question 13:** In `TAMBAB`, three results appear together. John 3:10: "Quote opened; see
following message for error". John 3:16: "Expected continuers [“] are missing OR quote not
closed; see preceding message". John 3:21: "Closing quote [” U+201D] found without matching
opening. Caused by a missing opening quote, a missing continuer, or incorrectly defined
continuer rules". Verse 3:16 starts a new `\p` paragraph inside Jesus's speech and begins with
a straight `"` (U+0022). What do you do?
- A) Replace the `"` at 3:16 with `“` (U+201C), close the results panel and run the check again
- B) Replace the `"` at 3:16 with `‘` (U+2018), close the results panel and run the check again
- C) Add `"` (U+0022) as the First level continuer on the Quote marks tab, then run the check again
- D) Replace the `"` at 3:16 with `“` (U+201C), and add an opening `“` before the `”` at 3:21

**Question 14:** You have fixed every real error the check found in Matthew, but six results
remain. All six read "Opening quote mark found without matching closing quote mark: “", each at a
different verse, and the translation team confirms the marks in those verses are written the way
they intend. What is your next step?
- A) Classify them as Neither: leave all six and add a Project Note to each
- B) Remove the flagged marks from those verses so that Matthew reaches zero
- C) Treat them as a configuration problem: find and adjust the setting
- D) Leave Matthew as it is and expand the scope to the rest of the NT

**Question 15:** You start Exercise 4.2 in `TAMBAB`, working the result list toward zero
actionable errors. How do you scope the check?
- A) Run all NT books at once, so no result is missed before you start fixing
- B) Run Matthew alone until it is clean, then add one book at a time
- C) Start with Romans, so the known exception is dealt with first
- D) Start with whichever book shows the most results in a full NT run

**Question 16:** In `TAMBAB`, you add the two missing `”` (U+201D) marks at Matthew 5:12 and
7:27, then click **Rerun** on the results panel that is still open. The Matthew 5:3 result
"Closing quote mark is possibly missing before verse 7:28" is still listed. What do you do next?
- A) Undo the two edits, since the result shows they did not fix the problem
- B) Keep looking for another missing `”` between Matthew 5:3 and 7:28
- C) Check the Continuer required at list, since the text is now correct
- D) Close the results panel and run the check again from Run basic checks

**Question 17:** Expanding `TAMBAB` book by book, every book now shows 0 results except Romans,
which shows one, at 1:1: "Closing quote [’ U+2019] found as a word medial character. Sometimes
caused when a closing quote is incorrectly used as an apostrophe". The verse has no dialogue,
and the flagged `’` sits between two letters of a word. When is Romans finished?
- A) Once the `’` is verified as an apostrophe and the result documented
- B) Once adding `’` to Word-medial punctuation has cleared the result
- C) Once the `’` is taken out of the word so that Romans shows 0 results
- D) Once the translation team has rewritten 1:1 without the apostrophe

---

### Section 5: Applying the Workflow to Unfamiliar Languages (Questions 18-20)

**Question 18:** In the **Menda** project, Second level opens with `›` (U+203A) and closes with
`‹` (U+2039). You accidentally enter `‹` in the Second level Opening cell and `›` in the Closing
cell, then run the check on John. What do you see, and what is the fix?
- A) No results; Paratext pairs single guillemets whichever way they point
- B) Results only at John 19:21, where Third level reuses `«` and `»`
- C) Second level results throughout; swap the two cells, check the Example
- D) Second level results throughout; edit the text so `‹` opens and `›` closes

**Question 19:** **Waku** uses an em dash `—` (U+2014) as its First level opening, continuer
and closing mark. With Waku correctly configured, the check still reports six "found as a word
medial character" results on em dashes that the translation team confirms are parenthetical
asides, not speech. What is the right handling?
- A) Add `—` (U+2014) to Word-medial punctuation so those dashes are read as part of words
- B) Add a Project Note at each verse, and raise other punctuation for asides with the team
- C) Replace the parenthetical em dashes with brackets yourself so the count reaches zero
- D) Set every quotation type to Quote marks are optional so the dashes are no longer flagged

**Question 20:** True or False: Once a project's Quote marks and Quotation types tabs correctly
describe the language, the Quotations check run on every book will return zero results.
- A) True
- B) False

---

## Answer key

1. C | 2. B | 3. A | 4. D | 5. A | 6. C | 7. B | 8. D | 9. A | 10. C | 11. B | 12. D | 13. A | 14. C | 15. B | 16. D | 17. A | 18. C | 19. B | 20. B

**Rationales**

1. C — Lesson 1: the check reads the Quote marks tab (which characters are quote marks, and at what level) and the Quotation types tab (whether each kind of speech requires, forbids or optionally allows marks), and both must be set to match the team's conventions. Language Settings, the Run basic checks options and the Example/Copy controls are not those two inputs.
2. B — Lesson 1 Exercise 1.1: the five John 17 results come from Paratext's default settings, not from the text, which the course has checked and found correct. Correct the settings first and run the check again; anything that still points at the text then goes back to the translation team. Adding or deleting marks to clear a default-settings result changes correct Scripture to satisfy a setting nobody chose, and enabling the Quotation types check does not change that.
3. A (True) — Lesson 1 Content: at the defaults the check can show a handful of plausible results (Tamba's five in John 17), a flood (Runda's 1110) or a false silence (Menda's "No errors found" on a text full of dialogue, because none of its marks are in the defaults). The defaults are Paratext's, not the team's, so none of these results can be trusted yet.
4. D — Lesson 2 Exercise 2.2 and the scenario workflow: the grid is never empty, so correcting it means changing every cell that differs from the conventions, including unused levels that must be set to *none*. A cell you leave at the default is still Paratext's choice, not the team's: in Runda's current text the Third level defaults happen to add no results (A's reason is true), but as with Q5 a clean check does not prove the settings are right. Runda's First and Second level marks are not its Third level, because Runda has none.
5. A — Lesson 2 Exercise 2.1: `pi/pi` alone clears the John 17 results, and the default Second and Third level continuers produce no results in Tamba's text, so "No errors found" does not prove the settings are right. The check only reports what the current text happens to trip over. Tamba's conventions have no Second or Third level continuer and a six-entry list, so set what the conventions say rather than waiting for new text to expose the gap.
6. C — Lesson 2: confirm the characters in the Example section at the bottom of the dialog before clicking OK. Hovering a label shows a description of the field, not the character in the cell; similar-looking marks can be different code points, so comparing shapes is not enough; and a check run is not a substitute for verifying what you entered.
7. B — Lesson 2 Exercise 2.2 and its table: "Expected continuers [«] are missing" at each place a listed marker occurs inside speech means the list is too long; the default `p/q1` names a marker Runda's text does not continue at. Runda's text carries `«` only at `\p`, so its list is `p` alone. The fix is in the field, not the text: the text is the authority and the list is your description of it. A too-short list shows a different symptom (unclosed-quote results inside a long speech), and clearing the continuer cell would break Runda's real continuers.
8. D — Lesson 2 Exercise 2.3: Word-medial punctuation does not suppress the result once the character is also a configured quote mark, so every genuine apostrophe is still flagged on a fresh run. Paratext warns that unique characters are recommended, but it saves the setting. Treat each result as an expected false positive; the lasting fix is a different, unique apostrophe character, which is an orthography decision for the translation team to make, not you.
9. A — Lesson 3 Exercise 3.2: with a First level continuer, a mark is expected at the head of each continued paragraph, so "Never use quote marks" would flag every expected continuer as an error. Switch to Custom settings and set Continued quotation to Use quote marks. The setting concerns Tamba's First level continuer, the Quote marks tab does not override it, and clearing the continuer cell would break the Quotations check configured in Lesson 2.
10. C — Lesson 3 Exercise 3.2: Optional never flags a stray mark a translator adds to reported speech. Since Tamba never marks it, switch to Custom settings and set Indirect to Never use quote marks so a stray mark gets caught. Use quote marks would flag every correctly unmarked verse, and Project Notes are for residual results configuration cannot clear, not a substitute for the right rule.
11. B — Lesson 3 Exercise 3.2: Self quote already defaults to Use quote marks, which is what Tamba needs, so it stays as it is while you change the other three types. Optional would stop the check flagging a self-quote whose marks are missing, and Never would flag correctly marked ones. Not every recommended default is wrong; check each type against the language's conventions rather than changing all seven.
12. D — Lesson 4 Exercise 4.1 item 2: the `“` before the Isaiah citation is a stray mark, like a translator adding a dialogue opener by mistake, so it is a real error: delete it (on a live project, confirm with the translation team that it doesn't belong, as Lesson 4's self-assessment 3 does). The check treats the stray mark as a legitimate new opening and reports its close as missing wherever the next real closing mark falls, so adding a `”` where it points chases the symptom, not the cause. Optional is the correct Quotation from another source setting for Tamba, and a real error is fixed, not documented.
13. A — Lesson 4 Exercise 4.1 item 3: 3:16 starts a new `\p` paragraph inside Jesus's First level speech, so it should open with Tamba's First level Quote Continuer `“` (U+201C). That one wrong character produces all three results; replace it, close the results panel and run the check again (not Rerun on the open panel), and all three clear together. `‘` is the Second level opening mark, not the continuer; the Quote marks tab describes the language's convention, not a stray character; and there is nothing to fix at 3:10 or 3:21.
14. C — Lesson 4 Content, Exercise 4.2 and self-assessment 2: the same message repeated across many verses usually points to a configuration gap, and once the team has confirmed the text the remaining results are about the settings. Find the rule or setting (for an unclosed-quote message like this one, Lesson 2 points to a Continuer required at list that is too short), adjust it, then close the results panel and run the check again. Editing text to quiet the check is never right; Neither is reserved for the quote-mark/apostrophe collision, which this message is not; and you expand the scope only once Matthew is clean.
15. B — Lesson 4 Exercise 4.2 steps 1 and 5: limit the scope to Matthew and work it to zero results, then expand one book at a time through the NT. A full-NT result list is overwhelming while a single-book list is actionable, and Matthew has no unresolvable cases, so a true zero is the right target there.
16. D — Lesson 4 Exercise 4.2 note: clicking Rerun on the panel that is already open does not pick up your text changes; it keeps showing the old results, so a correct fix looks as if it failed. Close the results panel and run the check again from ☰ > Tools > Run basic checks... before deciding anything about your edits.
17. A — Lesson 4 Exercise 4.2 step 5 and completion criteria: Romans is expected to stop at 1 result, the 1:1 apostrophe, which is neither a real error nor a configuration problem. Adding `’` to Word-medial punctuation does not clear it (Lesson 2 Exercise 2.3, Lesson 4 Exercise 4.1 item 5), and the apostrophe is correct as written, so there is no text edit. Verify it, document it, and Romans is done. Raising a unique apostrophe character with the translation team is worthwhile, but it is their orthography decision and Romans does not wait on it.
18. C — Scenario B (Menda): with the Second level cells reversed, the check reports results in pairs throughout John, "Closing quote [ › U+203A ] found without matching opening…" and "Opening quote mark found without matching closing quote mark: ‹". Swap the cells so `›` opens and `‹` closes, confirm in the Example section, and with Third level also set to Menda's `«` *none* `»` the check reports "No errors found". Paratext does not pair single guillemets whichever way they point, and the text follows Menda's convention, so it is the configuration that changes, not the text; results only at John 19:21 come from Third level cells left at the defaults, not from reversed Second level cells.
19. B — Scenario C (Waku): Paratext cannot tell an em dash used for speech from one used for an aside, and the scenario confirms a correct configuration still ends with these results, so no setting clears them. Add a Project Note (☰ > Insert > Project note...) at each verse the result is reported at, so the consultant can verify it during review. Whether to use different punctuation for asides is the translation team's call: raise it with them rather than editing the text yourself.
20. B (False) — Scenario bank summary and Lesson 4 Exercise 4.2: a correct configuration can still leave results that need human review, such as Waku's seven residual results or the Romans 1:1 apostrophe in Tamba. The target is zero actionable errors, which is not always a zero-length results list.
