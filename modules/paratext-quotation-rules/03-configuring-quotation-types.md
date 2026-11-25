# Lesson 3 — Configuring Quotation Types

**Estimated time:** 60 minutes

> This lesson uses the `TAMBA` practice project. See the
> [course README](README.md#the-practice-projects) for its quotation conventions.

**Purpose:** The Quote marks tab from Lesson 2 tells Paratext *which* characters are quote
marks; it does not say *when* marks should appear. On a real project a language may mark
narrator citations one way and self-quotes another. This lesson gives the check its second
input — the rules that decide, per kind of speech, whether marks are expected — so that what it
reports follows the language's conventions rather than Paratext's defaults, and what is left is
a list worth taking to the translation team.

## Learning objectives

By the end of this lesson you will be able to:

- Explain what the Quotation types tab controls and why it is separate from the Quote marks
  tab.
- Configure each of the seven quotation type settings for a given language's conventions.
- Distinguish between recommended settings and custom settings.

## Connect

You have told Paratext which characters are quote marks. But knowing the characters is not the
same as knowing *when they should be there*.

**✏️ Reflection.** Think about the language you support (or Scripture in a language you know
well):

- When the narrator quotes the Old Testament, does that citation get dialogue quote marks, or
  is it left as plain text?
- When the text reports speech indirectly — "He said that the road was long" — should quote
  marks appear at all?
- If a checker flagged *every* indirect-speech verse as "missing quotation mark," would that be
  the translation's fault or the check's setup?

Those are exactly the distinctions the Quotation types tab exists to encode. Hold your answers —
you'll make these same calls for Tamba in Exercise 3.2, and meet them again in the Change
self-assessment.

## Content

*This is the second half of configuring the check for the **Translation Tools** competency.
Lesson 2 was about characters; this lesson is about expectations. Matching the type settings to
the language's conventions removes the results that come from a setting that doesn't fit. It
doesn't empty the list: Exercise 3.2 shows you what is left and what to do with it.*

The **Quotation types** tab controls whether Paratext *expects* quotation marks for each
semantic category of speech. This is independent of *which characters* are used (that is the
Quote marks tab's job). The Quotation types tab answers: for this type of speech, should marks
always appear, never appear, or is either acceptable?

Navigate to: ☰ > Project settings > Quotation Rules > **Quotation types** tab.

![The Quotation types tab of the Quotation Rules dialog for the TAMBA project. The Enable the Quotation types check in Run basic checks checkbox is not ticked. Use recommended settings is selected and Custom settings is not. The seven type rows, each with a count in brackets and a dimmed drop-down, read: Normal (2007), Use quote marks; Quotation from another source (253), Use quote marks; Self quote (0), Use quote marks; Continued quotation (251), Never use quote marks; Potential (245), Quote marks are optional; Indirect (406), Quote marks are optional; Hypothetical (66), Never use quote marks. The help pane at the bottom begins: Only a project administrator can enable this tab.](assets/ss-03-quotation-types-tab.png)

### Enabling the check (administrator only)

At the top of the tab is the checkbox **Enable the Quotation types check in Run basic
checks**. Only a project administrator can check this box — the yellow help pane at the bottom
of the dialog confirms: "Only a project administrator can enable this tab." Configuring the
rest of the tab is an administrator's job too: Paratext's help says configuring quotation types
"can only be done by a project administrator." Until the box is ticked, the two radio buttons
and the seven drop-downs below it are dimmed and can't be changed — you can read the settings,
but clicking **Custom settings** does nothing. You are an administrator of every practice
project in this course, so you tick it yourself in Exercise 3.2.

**WARNING** The Quotation types check only checks first-level quotes in non-Deuterocanonical
books.

### The seven quotation types

The tab lists seven types. Each type has a **drop-down** with three active options (plus the
default):

- **Use quote marks** — Paratext expects marks to be present; missing marks are flagged as
  errors.
- **Never use quote marks** — Paratext expects no marks; unexpected marks are flagged as
  errors.
- **Quote marks are optional** — either is acceptable; the check does not flag errors for this
  type.

A count in parentheses appears next to each type name showing how many occurrences of that type
are in the books in your project scope — useful for gauging how much a given setting will
affect your results. If the project scope has no books in it, the count is the number of
occurrences in all the books of the Bible.

**NOTE** Even if all types are set to Optional, quotes that do not correspond to any recognized
quotation type will still be reported.

| Type | Meaning |
| --- | --- |
| Normal | Direct speech between people in the narrative |
| Quotation from another source | A narrator or speaker quotes scripture, another text, or a source outside the narrative |
| Self quote | A speaker quotes their own earlier words |
| Continued quotation | A speech that continues across a paragraph break using a continuation convention |
| Potential | Paratext identifies this as a possible quotation but cannot determine the type |
| Indirect | Reported speech: "He said that the road was long" (no direct marks in the source) |
| Hypothetical | Speech in a conditional or hypothetical frame: "If I were king, I would say…" |

### Recommended settings

When you first open the Quotation types tab, Paratext offers **Recommended settings**. These
are sensible defaults for most translations:

| Type | Recommended setting |
| --- | --- |
| Normal | Use quote marks |
| Quotation from another source | Use quote marks |
| Self quote | Use quote marks |
| Continued quotation | Never use quote marks |
| Potential | Quote marks are optional |
| Indirect | Quote marks are optional |
| Hypothetical | Never use quote marks |

If your language follows these conventions, select **Use recommended settings** and you are
done. If your language diverges, select **Custom settings** and adjust each type's drop-down
individually.

**NOTE** The values Paratext pre-fills under "Use recommended settings" are set by Paratext —
verify what appears in your version before relying on the table above as the exact recommended
defaults. (The table above reflects Paratext 9.5 defaults confirmed on a built `TAMBA` project.
Don't assume Quotation from another source and Indirect default to "never": the first defaults
to marks expected and the second to optional. That is why Exercise 3.2 below changes Quotation
from another source for Tamba and leaves Indirect as it is.)

**TIP** Complete the settings on both the Quote marks tab and the Quotation types tab before
clicking **OK** — the OK button saves all changes from both tabs at once.

If another project in your organization uses the same quotation type conventions, click **Copy
quotation type settings...** at the bottom of the dialog to import that project's settings
rather than configuring each drop-down manually.

**NOTE** A dividing line in the dialog separates the first three types (Normal, Quotation from
another source, Self quote) from the lower four (Continued quotation, Potential, Indirect,
Hypothetical). The upper group covers standard direct speech; the lower group covers special
speech categories.

**Key takeaways**

- The Quotation types tab controls whether Paratext *expects* marks for each category of
  speech — separate from, and complementary to, the character configuration on the Quote marks
  tab.
- Use recommended settings as a starting point; switch to Custom settings only where a
  default doesn't match the language's conventions.
- Matching the types to the conventions removes the results that come from a setting that
  doesn't fit. It doesn't promise an empty list: what remains is a list to review with the
  translation team.
- Only a project administrator can enable the check and configure its settings; the settings
  stay dimmed until the check is enabled.

## Challenge

You are configuring the `TAMBA` project's quotation types after a working session with the
translation team. Each exercise produces settings a mentor can check against Tamba's conventions.

### Exercise 3.1 — Read Tamba's current quotation types

1. Open the `TAMBA` project.
2. Go to ☰ > Project settings > Quotation Rules, and click the **Quotation types** tab.
3. **✏️** Before changing anything, record the current setting for each type in this table:

| Type | Current setting |
| --- | --- |
| Normal | ? |
| Quotation from another source | ? |
| Self quote | ? |
| Continued quotation | ? |
| Potential | ? |
| Indirect | ? |
| Hypothetical | ? |

Tamba's settings should match the recommended defaults above. Confirm this before moving on.

### Exercise 3.2 — Customize quotation types for Tamba

After reviewing Tamba's text with the translation team, you have determined the following five
things:

1. Tamba marks all direct speech with quotation marks.
2. Tamba does not mark narrator scripture citations with quote marks — these appear as plain
   narrative text.
3. Tamba's First level uses a Quote Continuer at new paragraph: a long First level speech
   repeats the opening mark `“` at the head of each continued paragraph rather than closing and
   reopening. (Second and Third level have no continuer. The Quotation types check only checks
   First level quotes anyway — see the WARNING above.)
4. When a speaker quotes their own earlier words (a self-quote), Tamba treats it the same as
   any other direct speech: it **must** be marked with quotation marks.
5. Tamba does not mark hypothetical speech with quotation marks.

**Step 1** — Compare two of the recommended settings with items 2 and 3 above. Neither one fits
Tamba:

| Setting | Recommended setting (now) | Tamba needs | Why |
| --- | --- | --- | --- |
| Quotation from another source | Use quote marks | **Quote marks are optional** | Tamba doesn't put quote marks around Scripture the narrator cites (item 2). With *Use quote marks*, the check reports a missing quote mark for each of these citations. |
| Continued quotation | Never use quote marks | **Use quote marks** | Tamba repeats `“` at the start of each continued paragraph (item 3). With *Never use quote marks*, the check reports each of those marks as an error. |

Don't change anything yet. You make these two changes in Step 4.

**Step 2** — See what the recommended settings report before you change them.

1. Tick **Enable the Quotation types check in Run basic checks** at the top of the tab. Do this
   first: until it is ticked, the radio buttons and drop-downs are dimmed and can't be changed.
2. Leave **Use recommended settings** selected.
3. Click **OK**.
4. Open **☰ > Tools > Run basic checks...**.
5. Choose all books.
6. Tick both **Quotations** and **Quotation types**.
    - Now that the check is enabled, a **Quotation types** box appears in this dialog. It
      wasn't there in Lessons 1 and 2.
    - It is a separate check from **Quotations**, and its results are reported only when that
      box is ticked.
7. Run the check.

The check reports **25 results**, of two kinds:

- "Quote mark found where it possibly should not occur [Continued quotation]: “" — at Matthew
  5:3, 5:13, 11:21 and 13:18, for example.
- "Quote mark missing where it possibly should occur [Quotation from another source]" — at
  Matthew 9:36, for example.

Compare them with Step 1: the type named in square brackets is one of the two whose recommended
setting doesn't match Tamba's conventions. All 25 come from the Quotation types check; the
Quotations check has nothing to report on `TAMBA` once the Quote marks tab is set as in Lesson
2. If your count is different, compare your Quote marks tab with Exercise 2.1 before going on.

**Step 3** — Check the recommended setting for **Self quote** against item 4.

1. Open the Quotation types tab again.
2. Look at **Self quote**. It already reads **Use quote marks**, which is what item 4 asks for,
   so it needs no change.
3. Read the count in brackets beside it (the Content section above explains these counts).
   With the check enabled, `TAMBA` shows **Self quote (0)**. Paratext finds no self-quotes in
   Tamba's books, so this setting has nothing to act on in this project, whatever it is set to.

The habit still matters: check every default against the convention, leave it when it already
fits, and use the counts to see how much text each setting can touch.

**Step 4** — Make the two changes, with the dialog still open:

1. Click **Custom settings** at the top of the tab. This switches all drop-downs to editable
   mode.
2. Change **Quotation from another source** from *Use quote marks* to **Quote marks are
   optional**.
3. Change **Continued quotation** from *Never use quote marks* to **Use quote marks**.
4. Leave **Self quote** at *Use quote marks* and **Indirect** at *Quote marks are optional*.
5. Click **OK**.

The final settings for Tamba:

| Type | Correct setting for Tamba | Changed from default? | Reason |
| --- | --- | --- | --- |
| Normal | Use quote marks | No | Tamba's convention is to mark all direct speech |
| Quotation from another source | **Quote marks are optional** | Yes — default is Use quote marks | Narrator scripture citations are not marked |
| Self quote | Use quote marks | No — already the default | Tamba treats self-quotes the same as Normal speech (the count for `TAMBA` is 0, so nothing is affected) |
| Continued quotation | **Use quote marks** | Yes — default is Never use quote marks | Tamba's First level continuer means a mark is expected at every continued paragraph |
| Potential | Quote marks are optional | No | Uncertain cases should not generate errors |
| Indirect | Quote marks are optional | No | Tamba's text marks some reported speech and not others, so neither Use nor Never fits; that is what Optional is for |
| Hypothetical | Never use quote marks | No | Tamba's convention is not to mark hypothetical speech |

**Step 5** — Run the check again.

1. Confirm that **Enable the Quotation types check in Run basic checks**, which you ticked at
   the start of Step 2, is still ticked. While it is unticked, these settings don't affect the
   results.
2. Close the results panel.
3. Open **☰ > Tools > Run basic checks...**.
4. Choose all books.
5. Tick both **Quotations** and **Quotation types** again. The **Quotation types** box is a
   separate check from **Quotations**, and its results are reported only when that box is
   ticked.
6. Run the check.

**Check your work:**

1. The check now reports **12 results**, where the recommended settings gave 25.
2. Confirm that the two kinds of result from Step 2 are gone:
    - nothing reads "Quote mark found where it possibly should not occur [Continued
      quotation]"
    - nothing reads "Quote mark missing where it possibly should occur [Quotation from another
      source]"

    In Matthew 5, for example, 5:3 and 5:13 are no longer listed.
3. Confirm that the 12 results left are of these four kinds:

    | Message | Results | Where |
    | --- | --- | --- |
    | "Quote mark missing where it possibly should occur [Normal]" | 7 | LUK 4:18-19, LUK 6:40, LUK 13:5, LUK 24:29, JHN 7:24, JHN 13:32, ACT 16:35 |
    | "Quote mark found where it possibly should not occur [Hypothetical]: “" | 3 | ROM 3:8, ROM 9:19, ROM 11:19 |
    | "Quote mark missing where it possibly should occur [Continued quotation]" | 1 | ROM 16:22 |
    | "Quote mark found in a verse where it is not used in other texts: “" | 1 | LUK 1:41 |

4. If your list is different:
    1. Compare your Quotation types tab with the final settings table.
    2. Check that both boxes were ticked.
    3. Compare your Quote marks tab with Exercise 2.1.

**What to do with the 12.** Read the wording: most of these messages say "possibly". Each
result marks a verse where what Paratext expects for that kind of speech differs from what the
text has. It doesn't say which of the two is right, and you can't judge that in a language you
don't read. So you don't edit the text to clear them. Group the results by message type and
take the list to the translation team, with a question for each group; what happens next
depends on their answers. In this course, list them for your mentor. A clean run is not the
goal of this check. A reviewed list is.

**✏️ Produce this (a mentor will review it).** Submit three things:

- One sentence per type explaining *why* Tamba diverges from the recommended default or keeps
  it — two diverge (Quotation from another source, Continued quotation), and two you checked
  and kept (Self quote, Indirect).
- Your list of the 12 remaining results, grouped by message type, with the question you would
  ask the translation team about each group.
- A screenshot of the **Quotation types** tab for `TAMBA`, showing the Enable the Quotation
  types check box and all seven types. Your mentor can't see your settings any other way,
  because your project stays on your computer.

A mentor will compare your final settings to Tamba's conventions, and look at how you have
grouped the results and framed your questions.

## Change

**Self-assessment — can you explain it to a colleague?**

1. A language never uses dialogue quote marks for indirect speech ("He said that the road was
   long"). Which quotation type covers this, and what setting is correct?
2. The recommended settings have "Continued quotation" set to "Never use quote marks". Your
   language uses a Quote Continuer at new paragraph — an opening mark repeated at the start of
   each continued paragraph. Does this conflict with the recommended setting?
3. A check result appears for a narrator OT citation verse. You have "Quotation from another
   source" set to "Use quote marks". Is this a real error or a configuration issue? What is the
   correct action?

*You should be able to say:* (1) Indirect speech — set it to **Never use quote marks**: the
check reports an error if marks appear unexpectedly and stays quiet when they are absent.
(Tamba is not that language: in Exercise 3.2 you left Indirect at *Quote marks are optional*.) (2)
Yes, it conflicts — "Never use quote marks" expects no marks at the start of a continued
paragraph, so a repeated opening mark would be flagged; change Continued quotation to **Use
quote marks**, as you did for Tamba in Exercise 3.2, so the continuer is expected at every
continued paragraph. (3) A configuration issue — if the language doesn't mark narrator citations, change
"Quotation from another source" to **Quote marks are optional** and re-run; fix the
configuration rather than editing correct text.

**✏️ Take it to your context.** For one real language you support, fill in the seven-type
table with the setting you'd choose for each, and flag any type where the language diverges
from Paratext's recommended default.

**Next step.** You have now given the check both of its inputs — characters (Lesson 2) and
expectations (this lesson). In [Lesson 4](04-interpreting-and-clearing-the-check.md) you put a
configured project to work: reading real results, telling real errors from configuration
problems, and working the Quotations check down to zero actionable errors. Lesson 4 runs the
Quotations check on its own, so the Quotation types results you listed here are not part of
it.

---

Previous: [Lesson 2 — Setting Up Quote Marks](02-setting-up-quote-marks.md) · Next: [Lesson 4 — Interpreting and Clearing the Check](04-interpreting-and-clearing-the-check.md)
