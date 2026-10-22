# Lesson 2 — Setting Up Quote Marks

**Estimated time:** 90 minutes

> This lesson uses the `TAMBA` and `RUNDA` practice projects. See the
> [course README](README.md#the-practice-projects) for their quotation conventions.

**Purpose:** Every language marks speech differently — curly quotes, guillemets, a character
that doubles as an apostrophe. On a real project your job is to translate those conventions
into Paratext's Quote marks grid correctly, replacing Paratext's defaults, so the Quotation
check finally looks for what the team actually writes. This lesson gives the check the first of
its two inputs.

## Learning objectives

By the end of this lesson you will be able to:

- Navigate to the Quote marks tab, compare Paratext's default characters with a language's
  conventions, and correct every cell that differs.
- Configure the Quote Continuer at new paragraph for languages that use continuation marks,
  and fill the **Continuer required at** list that tells the check *where* to expect it.
- Recognize the word-medial punctuation conflict when the same character serves as both a
  closing mark and an apostrophe, and explain why it cannot be fully resolved through Paratext
  settings when that character is also configured as a quote mark.

## Connect

In [Lesson 1](01-what-the-quotation-check-does.md) you saw that a check running on Paratext's
defaults can't be trusted. Now you give it the first thing it needs: the actual characters your
language uses, in place of the ones Paratext chose.

**✏️ Reflection.** Picture the language project you work with (or one you expect to support):

- What characters does it use to open and close direct speech? Are they curly quotes (`“ ”`),
  guillemets (`« »`), straight quotes, or something else?
- When speech is quoted *inside* other speech, does the language use a different mark for that
  inner level?
- Have you ever seen a checker flag an apostrophe *inside* a word — a contraction or a glottal
  stop — as a broken quotation? What did you do about it?

Hold those in mind. Two of the three exercises in this lesson are exactly these situations.

## Content

*Configuring the Quote marks tab is the first half of the **Translation Tools** competency's
"use and troubleshoot" skill for this check. Get the characters right here and the
default-settings results from Lesson 1 disappear.*

The **Quote marks** tab tells Paratext which characters your language uses to open and close
quotations at each nesting level, and which character (if any) continues a speech across a
paragraph break. Navigate to:

project menu **☰ > Project settings > Quotation Rules**, then click the **Quote marks** tab.

![The project menu open showing Project settings highlighted, and Quotation Rules selected in the submenu.](assets/ss-02-project-menu-quotation-rules.png)

The tab has a grid with three rows and three columns. (If the project's **Allow whitespace and
invisible characters** option is on, you will see a fourth column, "After Opening/Continuer and
before Closing". This course doesn't cover it.)

**Rows — nesting levels:**
- **Quotes (First level)** — primary speech
- **Quotes within Quotes (Second level)** — speech embedded within a First level quotation
- **Quotes within Quotes within Quotes (Third level)** — speech embedded within a Second level
  quotation

**Columns:**
- **Opening** — the character that starts a quotation at that level
- **Quote Continuer at new paragraph** — the character repeated at the beginning of a new
  paragraph when a quotation continues (many languages set this to *none*)
- **Closing** — the character that ends a quotation at that level

**The grid is never empty.** As you saw in Lesson 1, a new project opens with Paratext's
defaults: `“ “ ”` at First level, `‘ ‘ ’` at Second level, `“ “ ”` at Third level, and
`p p/q1 b/q1` in the Continuer required at field. Your job is not to fill in a blank grid. It is
to compare every cell with the team's conventions and change each one that differs, including
cells that must be set to *none*. A cell you didn't look at is still Paratext's choice, not the
team's.

**NOTE** Each cell has a dropdown (▼) listing the characters you can choose. The dropdown shows
only the character itself. The character names and Unicode codes given in this course's steps,
such as "Left double quotation mark, U+201C", are for your reference; you won't see them in the
list. Look-alike characters can't be told apart by their shape, so to confirm which character a
cell actually holds, click in the cell and press **Alt+X** to show its Unicode code, then press
**Alt+X** again to switch it back. Don't skip the second press: if you click OK while the cell
shows the code, Paratext saves that text as the quote mark. Paratext also won't let you set the
Second level until the First level is filled in.

Below the grid the tab has a few additional settings — checkboxes such as **Closing quotes
close** and **List all quote marks...**, and one text field, **Continuer required at**, which
matters far more than its size suggests. Hover over any label to see its description in the
status bar at the bottom of the dialog.

### Continuer required at — the half of the continuer people miss

The continuer cell in the grid tells Paratext *which character* continues a speech. It does
**not** tell Paratext *where* to expect that character. Without that second piece, the check
has no way to tell a continuer `“` at the head of a paragraph from a brand-new opening `“`, so
it reads every one as a fresh quotation that is never closed. A long speech that runs across
many paragraphs — the Sermon on the Mount, for instance — then produces an unclosed-quote
result at every paragraph, even though the continuer cell is filled in correctly. (Confirmed
against Paratext 9.5.)

The **Continuer required at** field supplies the *where*. It is a space-separated list of
paragraph-marker contexts. Each entry takes one of two shapes:

- **A bare marker**, such as `p` — a continuer is required at the start of every `\p`
  paragraph that falls inside an open quotation.
- **A previous-marker / marker pair**, such as `p/q1` — a continuer is required at a `\q1`
  line only when it directly follows a `\p` paragraph. This is how you cover a speech that moves
  from prose into quoted poetry (`p/q1`, `b/q1`, `m/q1`), or through indented paragraphs
  (`s1/pi`, `pi/pi`), without demanding a continuer on every poetry line.

**Where does the list come from?** From the text, not from a catalogue. You do not need to know
every USFM paragraph marker — only the ones *this project* uses at points where a speech can
run on. Two ways to find them:

1. Open a long speech (the Sermon on the Mount in Matthew 5–7 is ideal) and note every
   paragraph marker that appears between its opening and closing marks. Each is a candidate
   entry; each change of marker inside the speech is a candidate pair. Then check the text at
   each candidate: does the continuer character actually appear there? A marker the text does
   *not* carry a continuer at must stay off the list, even if it sits inside the speech.
2. Use Paratext's **Markers inventory** (☰ > Tools > Checking Inventories) to list every
   marker the project uses at all, with a count for each. Select a paragraph marker in the
   upper pane and the lower pane lists every verse where it occurs, so you can double-click into
   a few and see whether they fall inside speech. Keep the paragraph markers that do. Section
   headings (`\s1`) never carry a continuer themselves, but a paragraph that *follows* a heading
   inside a running speech may, which is what a pair like `s1/pi` expresses.

![The Markers inventory window for the TAMBA project with All books selected. The upper pane lists markers with their counts and style names, among them p 1888, pi 11, q 14, q1 209 and q2 185. The marker p is selected, and the lower pane lists the first verses where it occurs: MAT 1:0, 1:16, 1:17 and 1:19.](assets/ss-02-markers-inventory.png)

Most New Testament projects end up with a list of four to six entries. A project with heavy
poetry, lists, or indented material has a longer list, but it is built the same way. Both
exercises below give you the finished list for the project so you can concentrate on the
mechanics; the **Take it to your context** task at the end asks you to derive one yourself.

At the bottom of the dialog:
- **Example** — a live text preview showing how your configured marks look in a sample
  passage. Use it to check that the marks sit together in the order you expect. It shows only
  the shapes, so it can't tell you which of two look-alike characters a cell holds; use
  **Alt+X** in the cell for that.
- **Copy quote mark settings...** button, at the bottom left — imports the quote marks from
  another project (useful when a related project uses the same conventions). It does **not**
  copy the **Continuer required at** list. Type that by hand after copying.

![The Quote marks tab for a project still at Paratext 9.5's defaults. The three-row, three-column grid shows “ “ ” at First level, ‘ ‘ ’ at Second level and “ “ ” at Third level (Opening, Quote Continuer at new paragraph, Closing). Below the grid are the checkboxes and the Continuer required at field reading p p/q1 b/q1; the Example section and the Copy quote mark settings... button are at the bottom.](assets/ss-02-quote-marks-tab-defaults.png)

**One more setting lives elsewhere, but it has a real limit.** Some languages use the same
character for two purposes: as the **closing quotation mark** at the single-quote level *and*
as an **apostrophe** within words. Paratext's Language Settings has a field for exactly this
kind of ambiguous character — **☰ > Project settings > Language Settings > Other Characters**
tab, **Word-medial punctuation** — and its own help text describes it as telling the checker to
treat a listed character as part of a word rather than punctuation when it sits between two
letters.

**Verified on Paratext 9.5, this does *not* work for a character that is also a configured
quote mark.** Adding `’` to Word-medial punctuation has no effect on the "Closing quote [ ’ U+2019 ]
found as a word medial character" result when `’` is also set as a Second (or Third) level closing mark
— the check keeps flagging every genuine apostrophe, with or without the setting. Once a
character is claimed as a quote mark in the Quotation Rules dialog, that classification appears
to take priority over the Word-medial punctuation exception list. The setting genuinely works
for punctuation that *isn't* also a quote character; it just doesn't rescue this specific
collision.

**What this means in practice:** if a language's real orthography reuses a quote-mark character
as an apostrophe, there is no configuration that makes the check stop flagging it. The
practical options are (a) recognize each such result during triage as a known, expected
false positive — a real apostrophe, not a translation error — and move past it rather than
hunting for a setting to clear it, or (b) raise with the translation team whether to use a
different, unique character for the apostrophe so the two roles don't collide in the first
place. That is an orthography decision for the team and the language community, not for you,
and it can come up late in a project. You'll see this firsthand in the third exercise below.

**Key takeaways**

- The Quote marks tab grid has three rows (First, Second, Third level) and three columns
  (Opening, Quote Continuer at new paragraph, Closing).
- A new project's grid holds Paratext's defaults, not the team's conventions. Compare every
  cell, including the continuers and the Continuer required at list, and change each one that
  differs.
- Always check the marks you set in the **Example** section at the bottom of the dialog, but
  remember it shows only shapes. Look-alike characters are easy to confuse, so confirm the
  character in a cell with **Alt+X** (and **Alt+X** again to switch it back) and compare the
  code with the conventions table.
- The Quote Continuer at new paragraph is optional; set it to *none* at any level where the
  language doesn't repeat a mark at the head of a continued paragraph.
- If you fill the continuer cell, you **must** also fill **Continuer required at** with the
  paragraph markers (and marker transitions like `p/q1`) where the continuer is expected. The
  character alone does nothing; with the list empty, every continuer is reported as an unclosed
  opening mark.
- When a closing-quote character doubles as an apostrophe, Word-medial punctuation in Language
  Settings does **not** suppress the resulting check result (confirmed against real Paratext
  9.5 behavior) — treat every such flag as an expected false positive to verify and set aside
  during triage, not a configuration problem to fix.

## Challenge

You will correct Paratext's defaults on two practice projects and then untangle the apostrophe
conflict. Each exercise produces a corrected tab a mentor can inspect against the language's
convention table in the README.

### Exercise 2.1 — Correct the defaults for Tamba

Open the `TAMBA` project's Quotation Rules dialog (☰ > Project settings > Quotation Rules) and
click the **Quote marks** tab.

`TAMBA` is in Phase A: the Quote marks tab still holds Paratext's defaults, the settings behind
the five John 17 results you saw in Lesson 1. Compare them with Tamba's conventions:

| Level | Opening | Quote Continuer at new paragraph | Closing |
| --- | --- | --- | --- |
| First level | `“` (U+201C) — default matches | `“` (U+201C) — default matches | `”` (U+201D) — default matches |
| Second level | `‘` (U+2018) — default matches | *none* — default is `‘`; **change it** | `’` (U+2019) — default matches |
| Third level | `“` (U+201C) — default matches | *none* — default is `“`; **change it** (step 2 does this for you) | `”` (U+201D) — default matches |

**Continuer required at:** Tamba needs `p p/q1 m/q1 s1/pi pi/pi b/q1`. The default is
`p p/q1 b/q1`; change it.

So the First level already matches, and two continuer cells and the list do not.

**Steps:**
1. Confirm the First level row: Opening `“` (Left double quotation mark, U+201C), Continuer `“`
   (U+201C), Closing `”` (Right double quotation mark, U+201D). These match Tamba; leave them.
2. Click the dropdown (▼) on the **Quote Continuer at new paragraph** cell for Second level.
   Select *none*. Paratext sets the Third level continuer to *none* at the same time, so don't
   be surprised when that cell changes by itself.
3. Look at the **Quote Continuer at new paragraph** cell for Third level. It should now read
   *none*; there is nothing to set there separately.
4. Confirm the rest of the Second level (Opening `‘` U+2018, Closing `’` U+2019) and Third level
   (Opening `“` U+201C, Closing `”` U+201D). These match Tamba; leave them.
5. Check the **Example** section at the bottom of the dialog. The sample text should show
   `“…‘…’…”` — curly double quotes at the outer level and curly single quotes for embedded
   speech.
6. In the **Continuer required at** field below the grid, replace `p p/q1 b/q1` with exactly:

   ```
   p p/q1 m/q1 s1/pi pi/pi b/q1
   ```

   Type it with single spaces between entries. Do not skip this step — see the table after
   the steps for what each entry is doing.
7. Click **OK**.

![The Quote marks tab for Tamba after entry: “ in the First level Opening and Continuer cells and ” in its Closing cell, ‘ and ’ at Second level with the Continuer cell at *none*, “ and ” at Third level with the Continuer cell also at *none*, and the Continuer required at field below the grid reading p p/q1 m/q1 s1/pi pi/pi b/q1. The Example section shows “ repeated at the head of each continued \p paragraph.](assets/ss-02-tamba-quote-marks.png)

Tamba uses English-style curly quotes at all three levels. First level speech that spans a
paragraph break repeats the opening mark `“` (U+201C) as a Quote Continuer at the head of each
new paragraph; the closing mark `”` (U+201D) appears only once, at the very end of the whole
speech. Second and Third level have no continuer, so nothing is repeated at the head of a new
paragraph at those levels. A Second level quotation can still run across paragraph breaks
unbroken: in Peter's Psalm 16 citation in Acts 2:25–28, an opening phrase is followed by a `\q1`
line that opens both First and Second level (`“ ‘`), and the Second level closes with `’` in
2:28, with no Second level mark repeated at the paragraph breaks in between. Tamba's text passes
the check that way.

**Why that list?** Tamba's text uses ordinary `\p` paragraphs for most speech, quoted poetry
introduced from prose or after a blank line, and indented `\pi` paragraphs after section
headings. Each entry covers one of those situations:

| Entry | Continuer required… | Where Tamba does this |
| --- | --- | --- |
| `p` | at the start of every `\p` paragraph inside an open speech | Every verse of the Sermon on the Mount (Matthew 5:4–7:27) |
| `p/q1` | at a `\q1` poetry line that follows a `\p` paragraph | A speech that quotes Scripture as poetry mid-paragraph |
| `m/q1` | at a `\q1` line that follows a `\m` (no-indent) paragraph | Same, after a continuation paragraph |
| `b/q1` | at a `\q1` line that follows a `\b` blank line | A second stanza of quoted poetry |
| `s1/pi` | at an indented `\pi` paragraph that follows a `\s1` heading | A speech that continues past a section heading |
| `pi/pi` | at a `\pi` paragraph that follows another `\pi` | Consecutive indented paragraphs within one speech |

If you were building this list yourself you would read Matthew 5–7, John 17 and Acts 2 and note
which markers appear inside the speeches. You will do exactly that for a real project in the
**Take it to your context** task.

**Check it worked.** Run the Quotation check (☰ > Tools > Run basic checks, Quotations only)
on all of Tamba's books (**Choose Books**, all NT books). In Lesson 1 this gave five results in
John 17. With your corrections it should be **"No errors found"** — Phase A's text is clean.
Two other outcomes tell you what went wrong:

- *The same five John 17 results* — your changes didn't save. The dialog was closed with Cancel
  or the window's X instead of **OK**. Reopen it, make the changes again, click OK.
- A run of unclosed-quote results at every verse from Matthew 5:4 onward — the **Continuer
  required at** field is empty or has a typo. That field is by far the most common cause of
  this pattern.

**A clean result is not the finish line.** Of all your changes, adding `pi/pi` to the list is
the only one the check needed. That entry alone clears all five John 17 results. The default Second and Third level continuers produce no results in Tamba's text at all. So the
check would have said "No errors found" even with those two cells still wrong and the list
still missing `m/q1` and `s1/pi`. The check can only report what the current text happens to
trip over. The team's conventions say Second and Third level have no continuer, and the list is
the six entries above, so that is what you set. When the team drafts a new book, settings that
match their conventions are what keep the check honest. Set what the conventions say; don't
stop when the result list empties.

(This is only the Quotations check. The separate Quotation types check, which Lesson 3
configures, is not enabled yet and adds its own results once it is.)

**TIP** Hover over any column or row label ("Opening", "Closing", "Quotes (First level)",
etc.) to see a description of that field in the status bar at the bottom of the dialog.

### Exercise 2.2 — Correct the defaults for Runda

**First, see what the defaults do.** Open the `RUNDA` project. Like every practice project, it
starts with Paratext's defaults. Run the Quotation check (☰ > Tools > Run basic checks,
Quotations only) on all books with **Choose Books**. You get **1110 results**. Scroll through
them and read the messages, not just the count. Most of them, 767 of the 1110, read "Closing
quote [ ’ U+2019 ] found as a word medial character. Sometimes caused when a closing quote is
incorrectly used as an apostrophe". Others read "Second-level quote mark found without a preceding first-level quote:
‘" (Matthew 2:6, 4:4, 4:6, for example), and there is a "Closing quote [ ’ U+2019 ] found
without matching opening…" at Matthew 5:10.

![The Quotations check results panel for RUNDA, all books, with the project still at Paratext 9.5's default Quotation Rules. The count reads 1110 results. The visible rows include "Closing quote … found as a word medial character" (naming ’ U+2019 in square brackets) results and "Second-level quote mark found without a preceding first-level quote: ‘" at Matthew 2:6 and 4:4.](assets/ss-02-runda-default-results.png)

Two different things are behind these results. The flood of word-medial messages comes from
the text using the same character, `’`, for more than one job: it is the Second level closing
mark, and Runda's text also uses it as an apostrophe, in contractions and possibly for a glottal
stop. Each of those is reported as a closing quote in the middle of a word, which is why most of
the 1110 results are that one message. The rest come from the settings: Runda's First level
marks are guillemets, and guillemets aren't in Paratext's defaults. Close the results panel.

**Now correct the defaults.** Navigate to ☰ > Project settings > Quotation Rules > Quote marks
tab and compare each cell with Runda's conventions:

| Level | Opening | Quote Continuer at new paragraph | Closing |
| --- | --- | --- | --- |
| First level | `«` (U+00AB) — default is `“` | `«` (U+00AB) — default is `“` | `»` (U+00BB) — default is `”` |
| Second level | `‘` (U+2018) — default matches | *none* — default is `‘` | `’` (U+2019) — default matches |
| Third level | *none* — default is `“` | *none* — default is `“` | *none* — default is `”` |

**Continuer required at:** Runda needs just `p`. The default is `p p/q1 b/q1`.

Runda uses French-style guillemets at the first level, no continuation mark at the second
level, and no Third level at all. Five cells and the list differ from the defaults.

**Steps:**
1. Click the dropdown arrow (▼) on the **Opening** cell for First level. Select « from the
   list.
2. Click the dropdown arrow on the **Quote Continuer at new paragraph** cell for First level.
   Select «.
3. Click the dropdown arrow on the **Closing** cell for First level. Select ».
4. Confirm the Second level Opening is ‘ (U+2018) and Closing is ’ (U+2019). Click the dropdown
   arrow on the Second level **Quote Continuer at new paragraph** cell and select *none*. As in
   Exercise 2.1, Paratext sets the Third level continuer to *none* at the same time.
5. Set the Third level Opening and Closing cells to *none*, and check that its continuer cell
   already reads *none*.
6. Check the **Example** section at the bottom of the dialog. You should see «...» for First
   level speech and ‘...’ for embedded speech.
7. In the **Continuer required at** field, replace `p p/q1 b/q1` with just:

   ```
   p
   ```

   Runda's text does use `\q1`/`\q2` poetry inside speech — Matthew 2:6 and 4:6 quote Scripture
   that way — but look at how it is written: the poetry line opens straight into the Second
   level `‘` with no `«` in front of it. Runda repeats its continuer only at `\p` paragraphs, so
   `p` is the whole list. Leaving the default `p/q1` here would make the check demand a `«` the
   text never carries, and you would get "Expected continuers [«] are missing" at every quoted
   poem.
8. Click **OK**.

**Check it worked.** Run the Quotation check on all books again. The count drops from 1110 to
**847**. Then run it on Matthew alone (**Current Book**, with Matthew open): **252 results**.

1110 to 847 looks like your changes barely helped. Read the messages, not the count. The
"Second-level quote mark found without a preceding first-level quote" results are gone: the
check now recognizes Runda's guillemets, so it sees the First level speech around each `‘`. In
Matthew you should also see **no** "Expected continuers" or "Quote opened; see following
message" results at Matthew 2:5–6 or 4:6. Almost everything left is one message, repeated:
"Closing quote [ ’ U+2019 ] found as a word medial character" — one for almost every apostrophe in the
book. There is also a pair of results at **5:3 and 5:10** that look alarming: "Opening quote
mark found without matching closing quote mark: «" at 5:3 and "Closing quote [ ’ U+2019 ] found
without matching opening" at 5:10. Leave all of these alone for now. Exercise 2.3 is about exactly these
results, including why 5:3 is reported when nothing at 5:3 is wrong.

![The Quotation Rules dialog for RUNDA on the Quote marks tab after Exercise 2.2. First level reads « « » (Opening, Continuer, Closing); Second level reads ‘, none, ’, with its continuer cleared from the default; Third level reads none in all three cells; Continuer required at reads p. The Example section shows the marks in nested order.](assets/ss-02-runda-quote-marks.png)

**✏️ Compare.** Runda and Tamba both fill the Quote Continuer cell at First level — Runda with
`«`, Tamba with `“` — because both languages repeat the opening mark at the start of each
continued paragraph rather than closing and reopening. Now compare Second level: both set its
continuer to *none*, because neither language's conventions repeat a Second level mark at the
head of a new paragraph. (In Tamba a Second level quotation can run across a paragraph break
unbroken, as Acts 2:25–28 does.) In both projects that meant changing a default. The convention
table drives the configuration — never the defaults, and never the other way around. Don't
assume one language's pattern applies to another, or that every nesting level within the same
language behaves the same way.

Now compare the two **Continuer required at** lists. Both languages use a continuer, but Tamba's
list has six entries and Runda's has one, because the list describes what the *text does*, not
what the language's punctuation convention says. Both projects quote poetry inside speech;
Tamba's text repeats the continuer at the head of the poetry, Runda's does not. Get the list
wrong in either direction and the check tells you, with a different message each way:

| List is… | What you see | Example |
| --- | --- | --- |
| Too short (a marker the text *does* continue at is missing) | Unclosed-quote results inside a long speech, such as "Quote opened…" or "Opening quote mark found without matching closing quote mark: “" | Tamba at the defaults, which lack `pi/pi`: five results in John 17. Tamba with an empty field: the whole Sermon on the Mount |
| Too long (a marker the text does *not* continue at is listed) | "Expected continuers [«] are missing" at each place the listed marker occurs inside speech | Runda with the default `p/q1` left in: Matthew 2:6, 4:6 |

Either way the fix is in the field, not the text. The text is the authority; the list is
your description of it.

### Exercise 2.3 — The word-medial punctuation conflict (and its limit)

Recall from the Content section: some languages use the same character as a **closing mark**
at the single-quote level *and* as an **apostrophe** within words. Paratext has a field that
looks designed for exactly this — but verified against real Paratext 9.5 behavior, it does not
actually resolve the conflict when that character is also a quote mark. This exercise walks you
through the setting so you can see that limitation firsthand, rather than assuming it works
because the field exists.

**☰ > Project settings > Language Settings**, then click the **Other Characters** tab. This tab
has a **Word-medial punctuation** field. Its own help text says any character listed there is
treated as part of a word when it appears between two alphabetic characters, so the checker
should not misread it as a closing mark.

![The Language Settings dialog for RUNDA on the Other Characters tab, with ’ (U+2019) entered in the Word-medial punctuation field, a yellow warning triangle at the right end of that field, a Quotation Rules... link above it, and the hover message "The following character(s) are also quote mark(s) in the Quotation rules dialog: ’ U+2019 (in square brackets). Unique characters are recommended." visible.](assets/ss-02-language-settings-other-chars.png)

**Where this genuinely helps:** punctuation characters that are *not* also configured as a
quote mark — a hyphen used word-medially, for instance. If you enter a character here that's
also a quote mark in Quotation Rules, Paratext doesn't open a pop-up. A yellow warning
triangle (⚠) appears at the right end of the field instead, and its message shows only when
you hover over it: "The following character(s) are also quote mark(s) in the Quotation rules
dialog: [ ’ U+2019 ]. Unique characters are recommended." It's easy to miss, but it is a real
signal, not just caution — it means the setting won't do what you're about to try to use it
for.

**Do it (Runda):** Runda uses `’` (U+2019) as its Second level closing mark — and its text also
uses `’` as an apostrophe in *don’t*, *can’t*, *righteousness’ sake*. A genuine collision, and
you already saw its footprint in the results at the end of Exercise 2.2.

1. Navigate to ☰ > Project settings > Language Settings > Other Characters tab.
2. In the **Word-medial punctuation** field, enter `’` (U+2019). A yellow warning triangle (⚠)
   appears at the right end of the field; hover over it to read its message. There is no
   dialog to confirm. Click **OK** anyway; the warning doesn't block it.
3. Open Quotation Rules (☰ > Project settings > Quotation Rules) and go to the **Quote marks** tab. The Second level Closing cell holding `’` now carries a matching ⚠.
   Hover over it: "This is also a word-medial character in the Language settings dialog:
   [ ’ U+2019 ]. Unique characters are recommended." A **Language Settings...** link appears
   above the Closing column. The two dialogs are warning about the same collision, each from
   its own side. Close the dialog without changing anything.

   ![The Quotation Rules dialog for RUNDA on the Quote marks tab with Exercise 2.2's settings (First level « « », Second level ‘ none ’, Third level none in all three cells, Continuer required at reading p), a yellow warning triangle in the Second level Closing cell, a Language Settings... link above the Closing column, and the hover message "This is also a word-medial character in the Language settings dialog: ’ U+2019 (in square brackets). Unique characters are recommended." visible.](assets/ss-02-apostrophe-warning.png)

4. Re-run the quotation check (a full re-run, not just "Rerun" on an already-open results
   panel) on a chapter that has both apostrophes and single-quote speech.
5. **Observe that the apostrophes are still flagged.** The check keeps reporting "Closing quote
   [ ’ U+2019 ] found as a word medial character" for every genuine apostrophe, exactly as before you added
   the setting. This is the expected, confirmed outcome — not a sign you configured something
   wrong.
6. **Now look at Matthew 5:3 and 5:10.** The same collision has a second, nastier face. In
   *don’t* the `’` sits between two letters, so the check at least recognizes it as
   word-medial and says so. In `righteousness’ sake` (5:10) the `’` is at the **end** of the word
   — a plural possessive — so the check simply sees a Second level closing mark with no opening
   and reports "Closing quote [ ’ U+2019 ] found without matching opening". Worse, that stray close
   knocks the check off the First level speech it was tracking, so it also reports "Opening
   quote mark found without matching closing quote mark: «" back at **5:3**, where the Sermon
   on the Mount begins. Nothing is wrong at 5:3: the speech closes correctly with `»` at 7:27.
   One apostrophe, two results, and the scarier one points at the wrong verse. Word-medial
   punctuation cannot help here either; it never applied to word-final characters in the first
   place. Runda's Matthew has a handful more of these (*kings’* 11:8, *Moses’* 23:2, *widows’*
   23:13, *Jesus’* 27:57–58), so expect the pattern to repeat.

**What to do instead, in real triage:** treat each of these results as a known false positive.
Open the verse, confirm the flagged character really is an apostrophe (word-medial or a
word-final possessive, not an actual unclosed quotation), and move on — there is no setting that
will make the result disappear. When a word-final apostrophe drags in a second result at the
start of the enclosing speech, check that the speech really does close where it should, then
set both results aside together.
Document this for whoever inherits the project, so a future checker doesn't waste time hunting
for a fix that doesn't exist.

**The real fix belongs to the team:** there is one way to make the collision go away —
the ⚠ Paratext shows in both dialogs when `’` is in Word-medial punctuation ("Unique characters
are recommended") is pointing at it. A different, unique character for the apostrophe (or, less
commonly, for the closing mark) means the two roles never collide. Choosing that character is
an orthography decision, and it belongs to the translation team and the language community —
you can raise it, but you don't make it, and it isn't something you configure your way around.
Orthography changes are sometimes made right up to typesetting, so it is worth raising even
late in a project: it's the only path that actually eliminates the false positives rather than
just documenting them.

A good concrete recommendation: **`ʼ` (U+02BC MODIFIER LETTER APOSTROPHE)**. It's the character
the Unicode Standard itself recommends for an apostrophe functioning as a letter — marking a
glottal stop or similar — as distinct from `’` (U+2019), which is meant for punctuation
(closing a quotation, or a generic typographic apostrophe in running prose). Visually it's a
small raised mark close in shape to `’`, so the orthography doesn't change much for readers, but
it's a completely different code point, so Paratext never confuses it with a configured quote
mark. For example, a word written `Kalaʼu` (U+02BC) would never generate a quotation result no
matter what the Second level closing mark is configured to — compare that to `Kala’u` (U+2019),
which collides the moment `’` is also a quote mark, exactly like Runda above.

**Tamba scenario:** Tamba's Second level closing mark is `’` (U+2019). Tamba's Phase A text has
no contractions or apostrophes, so this conflict never comes up there — but if Tamba's real
orthography later needed apostrophes written with `’`, this is the same unresolvable collision
you just saw in Runda, not something a setting change would fix.

**✏️ Produce this (a mentor will review it).** After all three exercises, jot 3–4 sentences: which
project(s) and level(s) needed a Quote Continuer and why, what the **Continuer required at**
list adds that the continuer cell alone does not, and what you observed when you tried the
Word-medial punctuation fix for the apostrophe conflict — including that it did not suppress
the check result. With them, submit a screenshot of the **Quote marks** tab for `TAMBA` and
one for `RUNDA`, with the Continuer required at field visible. Your mentor can't see your
settings any other way, because your projects stay on your computer. A mentor will check those
tabs, including the Continuer required at field, against the README convention tables.

## Change

**Self-assessment — can you explain it to a colleague?**

1. A language uses `««` (U+00AB U+00AB) and `»»` (U+00BB U+00BB) for First level speech and `«`
   / `»` for Second level speech. Where do you enter these characters in PT 9.5?
2. What is the Quote Continuer at new paragraph column for? Give an example of when you would
   set it to *none*. And if you *do* fill it, what else must you fill, and what happens if you
   don't?
3. Your Second level closing mark is `’` (U+2019). The quotation check is flagging apostrophes
   inside words as unclosed quotations. Can you make this result disappear through
   configuration, and if not, what should you actually do about it?

*You should be able to say:* (1) In the **Quote marks tab** of the Quotation Rules dialog (☰ >
Project settings > Quotation Rules) — `««` in the First level Opening cell, `»»` in the First
level Closing cell, `«` in the Second level Opening cell, and `»` in the Second level Closing
cell. (2) It is the character repeated at the start of each new paragraph when one speech spans
multiple paragraphs; set it to *none* when the language doesn't repeat a mark at the head of a
continued paragraph (Tamba and Runda at Second level — remember the defaults fill it, so you
have to change it). If you fill it, you must also fill
**Continuer required at** with the paragraph markers and marker transitions (e.g. `p p/q1`)
where the continuer is expected; otherwise the check reads every continuer as a new opening
mark that is never closed and floods a long speech with unclosed-quote results. (3) No — adding `’` to ☰ > Project
settings > Language Settings > Other Characters tab > Word-medial punctuation does not suppress
this result when `’` is also a configured quote mark (confirmed against real Paratext 9.5
behavior). Treat each flagged instance as an expected false positive: open the verse, confirm
it's a genuine apostrophe, and move past it during triage rather than searching for a setting
that will clear it.

**✏️ Take it to your context.** For one real language you support, write the three-row Quote
marks table (First/Second/Third level, Opening / Continuer / Closing) as you believe it should
be configured, then open the project's Quote marks tab and mark every cell that still differs —
on many projects that means cells still at Paratext's defaults. If any level has a continuer, open the longest speech in the project's text,
list every paragraph marker that appears inside it, and draft the **Continuer required at**
list from that — bare markers for paragraphs, pairs for the transitions. Note any character
that doubles as an apostrophe — that's your word-medial punctuation candidate.

**Next step.** The Quote marks tab tells Paratext *which characters* are quote marks. In
[Lesson 3](03-configuring-quotation-types.md) you give it the second input — the **Quotation
types** tab, which tells the check *when* marks are expected for each kind of speech.

---

Previous: [Lesson 1 — What the Quotation Check Does](01-what-the-quotation-check-does.md) · Next: [Lesson 3 — Configuring Quotation Types](03-configuring-quotation-types.md)
