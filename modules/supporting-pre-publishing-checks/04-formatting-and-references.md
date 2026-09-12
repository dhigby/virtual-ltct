# Lesson 4: Formatting and References

**Estimated time:** 85 minutes

**Purpose:** Support a team through the structural formatting checks — marker pairs,
headings, book titles, references, footnotes, and the Punctuation Inventory and
Unmatched Pairs of Punctuation — working structural-first so downstream errors don't
hide behind upstream ones, and coach the team to a genuinely zero-error result rather
than a fast one.

## Learning objectives

- You will be able to:
  - Diagnose formatting-check failures — unclosed marker pairs, ghost markers (a marker
    left with no content attached, usually debris from an incompletely deleted footnote
    or cross-reference), wrong markers, and errors in book titles, section headings, and
    references — by working structural-first.
  - Coach a team to a zero-error result without touching their keyboard.
- You will be able to:
  - Review and use Paratext's Punctuation Inventory (Tools > Checking Inventories >
    Punctuation Inventory) ahead of typesetting.
  - Select "Show sequences" in its Inventory menu so that Run Basic Checks' punctuation
    checkbox relabels itself "Punctuation (sequences)" and catches multi-character
    punctuation sequences, rather than staying a plain "Punctuation" check.
  - Use the complementary Unmatched Pairs of Punctuation inventory (Tools > Checking
    Inventories) to directly catch single unmatched bracket/parenthesis-type pairs.
  - Confirm both inventories were genuinely reviewed rather than assumed already
    handled.
  - Recognize the common settings issues that turn these into a time-sink for a
    typesetter.

## Connect

**✏️ Reflection:** Think about the last time you opened a project's Basic Checks and
saw a long list of formatting errors. Where did you start? Did you fix the first error
in the list, or did you look for a pattern that might be causing several of the errors
at once? Keep that instinct in mind — this lesson is about finding the *structural*
cause before chasing individual flags.

## Content

### Why structural-first order matters

Formatting errors cascade — but only through a real mechanism, not a vague "everything
after it breaks" effect. A missing `\p` (paragraph marker) after a `\s1` section heading
is a common, real example: without it, everything from right after the heading text up
until the next `\p` marker gets swallowed into and rendered *as part of* the section
heading — in the heading's bold/heading style — including verse numbers and verse text
that should be ordinary body text. That single missing marker can plausibly produce a
whole cluster of downstream-looking symptoms: an oversized or wrong-looking heading,
verse numbers that appear to have vanished from the normal flow, and references that
miscount because the checker is reading swallowed verse content as part of the heading.
If you start by fixing the errors at the bottom of a long list, you may be fixing
symptoms of a single cause higher up. Work in this order instead:

1. **Marker-pair census.** Confirm every paired marker (`\f...\f*`, `\x...\x*`, character
   styles, etc.) actually closes. An unclosed pair is often the single cause behind a
   cluster of downstream-looking errors.
2. **Ghost markers.** Look for markers left behind with no content attached — often the
   debris of a deleted footnote or cross-reference where the marker itself wasn't
   removed. These can silently corrupt export and typesetting even when nothing visibly
   looks wrong on screen.
3. **Long/short verses.** Verses that run unexpectedly long or short compared to the
   source can indicate a misplaced verse marker, a merged verse, or dropped text — worth
   ruling out before treating everything else in the chapter as a heading or reference
   problem.
4. **Section headings.** The marker itself is rarely the problem — most projects use
   `\s1` almost exclusively, with `\s2` only occasionally, so a wrong-level marker is
   uncommon. The real issues to check for are missing headings, or headings that don't
   match the text they introduce.
5. **Book titles.** Inconsistent or incorrect book-name and book-title markup, especially
   after a book has been renamed or reorganized mid-project.
6. **References** (`\r` shows parallel passages; `\xt` is the actual cross-reference
   marker) and the **table of contents.** Missed book-name checks, foreign-language `\r`
   abbreviations left unadjusted (a common source:
   bulk-copying `\r` lines from another NT project as a starting point, which carries over
   that project's abbreviations instead of the current project's own), and a table of
   contents that doesn't match the book titles actually in the text.
7. **Footnotes.** Beyond the marker-pair check in step 1, confirm footnote content and
   placement are sound — a footnote that survives the marker check can still be attached
   to the wrong verse or duplicated. Note that an unclosed `\f` footnote marker (opened
   without its matching `\f*`) has a much narrower effect than the missing-`\p` example
   above: if it's unclosed at the end of a verse, it has no effect at all; if it's
   unclosed partway through a verse, the only consequence is that the footnote text
   displays as part of the verse itself — it has no other flow-on effect, and does not
   cascade into headings, references, or other markers elsewhere in the chapter.

Diagnosing structural-first doesn't mean the team fixes things in that exact order line
by line — it means *you* look for the highest-leverage cause first, so you're not
sending a team to manually correct fifty symptoms of one unclosed marker.

There's a second way a Basic Checks result can look clean without being clean: unlike
the wordlist's spelling status (Correct/Incorrect/Undecided only), Basic Checks results
do carry a real accept/deny mechanism for flagged errors. A documented field case from
the SME interview behind this course found a team that had **denied errors they didn't
understand**, rather than resolving them — simply clearing the check's flags instead of
dealing with what was underneath. When a denied error is shown, it appears in the list
with **strikethrough text**, so it isn't hidden or disguised as a genuine fix — but
whether it shows up at all depends on a **View menu** option, **Denied messages**: with
that option off, denied errors disappear from the list completely, so a check that looks
short and clean may simply have its denied items switched out of view. Turn on
**View > Denied messages** to see the full picture before trusting a zero-error result.

> **WARNING — watch for a false-clean result here too:** A Basic Checks run showing zero
> formatting errors can mean the project is genuinely clean — or it can mean the checks
> were run once early in the project and never re-run after later revisions introduced
> new markup, or it can mean errors were denied and are currently hidden from view.
> Confirm the checks were re-run at this stage, and turn on **View > Denied messages** to
> check whether any struck-through, denied errors are sitting out of sight — the same "we
> already did that" trap from earlier lessons, now applied to formatting.

**Key takeaways**
- Work structural-first: marker pairs and ghost markers before headings, titles, and
  references, since upstream breakage can masquerade as unrelated downstream errors.
- Your job is to diagnose the cause and coach the team to fix it — you never touch their
  keyboard.
- A zero-error result only counts if the checks were actually re-run at this stage, not
  carried forward from an earlier, since-outdated pass — and not the result of denied
  errors sitting hidden because **View > Denied messages** is switched off.

### The Punctuation Inventory, ahead of typesetting

Paratext's **Punctuation Inventory** (Tools > Checking Inventories > Punctuation
Inventory) is a separate tool from the marker-pair and Basic Checks work above — and
separate from PTXprint or anything the typesetter runs on their own end. It's Paratext's
own inventory of every punctuation character used across the project, and it belongs in
your formatting review because it surfaces problems that are cheap to fix now and
expensive to fix once a project reaches a typesetter.

Alongside it, under the same **Tools > Checking Inventories** menu, sits a distinct,
complementary tool: **Unmatched Pairs of Punctuation.** It's its own standalone inventory
window, listing single unmatched bracket/parenthesis-type characters — an unmatched "}",
"[", or "(" — each with a count and a per-row **Status** column using the same
checkmark/red-X/blue-"?" pattern you've already seen on the Parallel Passages Status
column (checkmark = approved, red X = incorrect, blue "?" = needs review). Where the
Punctuation Inventory's "Show sequences" option (below) catches multi-character
punctuation *sequences* — combinations like multiple quotation marks paired with spacing
or another character such as ")" — Unmatched Pairs of Punctuation instead goes straight
after single unmatched bracket/parenthesis-type pairs, which is the most direct way to
catch that specific, common problem. Treat the two as complementary: one doesn't replace
the other.

One consultant reported spending significant time working alongside a typesetter,
after the fact, going through the Punctuation Inventory's settings to sort out issues
that should have been caught earlier — a real time-sink that fell on the typesetter
instead of getting resolved during the project's own formatting review.

Four things to do with these inventories:

- **Actually review both, don't assume they're already handled.** Just like the
  marker-pair and Basic Checks work above, it's easy for a team (or a previous
  consultant) to assume the Punctuation Inventory (and its neighbor, Unmatched Pairs of
  Punctuation) "must be fine by now" because the project is far along. That assumption is
  exactly the false-clean trap this course keeps coming back to — confirm someone has
  genuinely opened both inventories and reviewed them at this stage, rather than taking
  their cleanliness on faith.
- **Check the Punctuation Inventory's own settings**, not just the character list it
  produces. Loose or inconsistent settings are what turn this check into a time-sink once
  it reaches a typesetter — unrecognized or unconfigured punctuation characters left
  unresolved in the inventory's settings can surface late, as a pile of issues the
  typesetter has to chase down one by one instead of the team resolving them upfront.
- **Select "Show sequences" in the Punctuation Inventory's Inventory menu**, so that
  punctuation *sequences* — multi-character combinations, such as multiple quotation
  marks paired with spacing or another punctuation character — are actually inventoried,
  not just the individual characters. The checkbox under Run Basic Checks is simply how
  you choose which check to run — it isn't itself tied to whether "Show sequences" is
  selected. What "Show sequences" actually does is change the Run Basic Checks dialog:
  with it selected, the punctuation checkbox is relabelled **"Punctuation (sequences)"**
  and runs the sequence-level check; without it, the same checkbox stays labelled
  **"Punctuation"** and only checks individual characters, so the review can look
  complete while a whole class of problems goes unseen. This lesson covers punctuation
  sequences only; quotation marks specifically are more complicated and are intentionally
  out of scope here, deferred to a separate, future addition.
- **Review Unmatched Pairs of Punctuation directly**, using its Status column to work
  through any single unmatched bracket/parenthesis-type character it lists. Don't treat
  "Show sequences" as covering this — it catches multi-character sequences, not the
  single unmatched pairs this separate inventory is built to surface.

That third point comes from a real case. One consultant believed their Punctuation
Inventory review had been thorough — yet the typesetter later came back with a long list
of unmatched punctuation pairs (mostly involving quotation marks) that had never
surfaced. The cause was traced to "Show sequences" not having been selected in the
Inventory menu — without it, the Run Basic Checks dialog's punctuation checkbox stayed
labelled plain "Punctuation" and only checked individual characters, rather than being
relabelled "Punctuation (sequences)" to check the combinations. The
review *looked* complete and wasn't — the false-clean pattern again, this time produced
by a single unselected menu option rather than by anyone skipping a step.

> **WARNING — watch for a false-clean result here too:** don't let "we're close to done"
> stand in for "someone reviewed the Punctuation Inventory and Unmatched Pairs of
> Punctuation." Confirming both were actually opened and reviewed — not just assumed
> already handled earlier in the process — is the single highest-value thing you can do
> here before the project reaches a typesetter.

**Key takeaways**
- The Punctuation Inventory and Unmatched Pairs of Punctuation both live in Paratext
  itself (Tools > Checking Inventories) — neither is a PTXprint or typesetter-side tool,
  and reviewing both is part of your own formatting check, not something to defer to the
  typesetter.
- Confirm both were genuinely reviewed at this stage rather than assumed clean from
  earlier in the project.
- Loose or unconfigured settings in the Punctuation Inventory are what create a late
  time-sink for the typesetter — catching them here is cheaper for everyone.
- "Show sequences" must be selected in the Punctuation Inventory's Inventory menu for
  Run Basic Checks' punctuation checkbox to relabel itself "Punctuation (sequences)" and
  actually check multi-character sequences — without it, that same checkbox stays a
  plain "Punctuation" check, and the review can look complete while still missing them.
  (Quotation marks specifically are out of scope for this lesson.)
- Unmatched Pairs of Punctuation is a separate, complementary inventory that directly
  lists single unmatched bracket/parenthesis-type pairs — check it too; it isn't replaced
  by "Show sequences" or vice versa.

## Challenge

**✏️ Try this:** Six exercises on this lesson's own mechanisms. Write your answers
from memory first, then check each one against the Content section above — the point is
to find the gaps here, not halfway through a support session.

1. **Reproduce the structural-first order** — all seven steps, in order, from memory.
   Then, for the first two steps only, write one line each on why that step earns its
   place ahead of headings, titles, and references.
2. **Two unclosed markers, two very different effects.** For a missing `\p` after
   a `\s1` section heading, write what gets swallowed and up to what point. For an
   `\f` opened without its matching `\f*`, write what happens when it's unclosed at
   the end of a verse, and what happens when it's unclosed partway through one. Then
   state which of the two cascades into other markers elsewhere in the chapter and
   which does not — this is the distinction that keeps you from over-reading a small
   error.
3. **A resolved error can disappear from the list two different ways.** Write one line
   on how a denied error actually displays when it's visible (what marks it as denied,
   not resolved), and one line on the View menu setting that controls whether it shows
   up at all. Then say in one line why "deny" is a real, correct action in Basic Checks
   but has no equivalent in the wordlist's spelling status.
4. **Open Tools > Checking Inventories > Punctuation Inventory** in a project you
   already support, then open its **Inventory** menu and confirm whether **"Show
   sequences"** is selected. Write down what you found. Then, in one sentence, say how
   the punctuation checkbox under Run Basic Checks is labelled and what it checks while
   that option is unselected, versus while it's selected. One setting, one look — not a
   full inventory review.
5. **A review can be genuine and still incomplete.** In two lines, say what the
   consultant in this lesson's Punctuation Inventory case had got wrong, given that
   they genuinely *did* review the inventory — and what would have made the same
   review complete. Name the difference between a false-clean result produced by a
   skipped step and one produced by an unselected setting.
6. **Two complementary tools, one gap each.** Open **Tools > Checking Inventories >
   Unmatched Pairs of Punctuation** in the same project. In one line, say what it lists
   that "Show sequences" does not, and one line on what "Show sequences" catches that
   Unmatched Pairs of Punctuation does not.

## Change

**✏️ Reflection:** Think of a project you support that's getting close to typesetting.
Has anyone on the team — or you — actually opened the Punctuation Inventory and
Unmatched Pairs of Punctuation recently, or is their "clean" status something everyone is
assuming? What would it cost to confirm it now versus finding out from a frustrated
typesetter later?

**Next step:** Before your next formatting-check session, write down the structural-first
order from this lesson (marker pairs → ghost markers → long/short verses → headings →
book titles → references → footnotes) somewhere you'll actually have it in front of you,
and add "Punctuation Inventory and Unmatched Pairs of Punctuation — both genuinely
reviewed?" as a standing question for any project nearing typesetting.

**Coming up:** Lesson 5 turns to glossary linking and the final layout and draft-PDF
read-through — where, like the Punctuation Inventory here, you'll be doing hands-on
technical setup alongside your coaching role.
