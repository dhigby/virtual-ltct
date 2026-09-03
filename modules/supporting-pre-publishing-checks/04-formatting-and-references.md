# Lesson 4: Formatting and References

**Estimated time:** 80 minutes

**Purpose:** Support a team through the structural formatting checks — marker pairs,
headings, book titles, references, footnotes, and the Punctuation Inventory — working
structural-first so downstream errors don't hide behind upstream ones, and coach the
team to a genuinely zero-error result rather than a fast one.

## Learning objectives

- You will be able to diagnose formatting-check failures — unclosed marker pairs, ghost
  markers, wrong markers, and errors in book titles, section headings, and references —
  by working structural-first, and coach a team to a zero-error result without touching
  their keyboard.
- You will be able to review and use Paratext's Punctuation Inventory (Checks >
  Inventories) ahead of typesetting — including selecting "Show sequences" in its
  Inventory menu so that the "Punctuation (sequences)" Basic Check is meaningful —
  confirm it was genuinely reviewed rather than assumed already handled, and recognize
  the common settings issues that turn it into a time-sink for a typesetter.

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
4. **Section headings.** Wrong marker (e.g. a major section head styled as a minor one),
   missing headings, or headings that don't match the text they introduce.
5. **Book titles.** Inconsistent or incorrect book-name and book-title markup, especially
   after a book has been renamed or reorganized mid-project.
6. **References** (`\r` cross-references) and the **table of contents.** Missed
   book-name checks, foreign-language `\r` abbreviations left unadjusted (a common source:
   bulk-copying `\r` lines from another NT project as a starting point, which carries over
   that project's abbreviations instead of the current project's own), and a table of
   contents that doesn't match the book titles actually in the text.
7. **Footnotes.** Beyond the marker-pair check in step 1, confirm footnote content and
   placement are sound — a footnote that survives the marker check can still be attached
   to the wrong verse or duplicated. Note that an unclosed `\f` footnote marker (opened
   without its matching `\f*`) has a much narrower effect than the missing-`\p` example
   above: if it's unclosed at the end of a verse, it has no effect at all; if it's
   unclosed partway through a verse, the only consequence is that the following verse
   text gets swallowed into the footnote's own content — it does not cascade into
   headings, references, or other markers elsewhere in the chapter.

Diagnosing structural-first doesn't mean the team fixes things in that exact order line
by line — it means *you* look for the highest-leverage cause first, so you're not
sending a team to manually correct fifty symptoms of one unclosed marker.

> **WARNING — watch for a false-clean result here too:** A Basic Checks run showing zero
> formatting errors can mean the project is genuinely clean — or it can mean the checks
> were run once early in the project and never re-run after later revisions introduced
> new markup. Confirm the checks were re-run at this stage, the same "we already did
> that" trap from earlier lessons, now applied to formatting.

**Key takeaways**
- Work structural-first: marker pairs and ghost markers before headings, titles, and
  references, since upstream breakage can masquerade as unrelated downstream errors.
- Your job is to diagnose the cause and coach the team to fix it — you never touch their
  keyboard.
- A zero-error result only counts if the checks were actually re-run at this stage, not
  carried forward from an earlier, since-outdated pass.

### The Punctuation Inventory, ahead of typesetting

Paratext's **Punctuation Inventory** (Checks > Inventories) is a separate tool from the
marker-pair and Basic Checks work above — and separate from PTXprint or anything the
typesetter runs on their own end. It's Paratext's own inventory of every punctuation
character used across the project, and it belongs in your formatting review because it
surfaces problems that are cheap to fix now and expensive to fix once a project reaches
a typesetter.

One consultant reported spending significant time working alongside a typesetter,
after the fact, going through the Punctuation Inventory's settings to sort out issues
that should have been caught earlier — a real time-sink that fell on the typesetter
instead of getting resolved during the project's own formatting review.

Three things to do with this inventory:

- **Actually review it, don't assume it's already handled.** Just like the marker-pair
  and Basic Checks work above, it's easy for a team (or a previous consultant) to assume
  the Punctuation Inventory "must be fine by now" because the project is far along. That
  assumption is exactly the false-clean trap this course keeps coming back to — confirm
  someone has genuinely opened the inventory and reviewed it at this stage, rather than
  taking its cleanliness on faith.
- **Check the inventory's own settings**, not just the character list it produces. Loose
  or inconsistent settings are what turn this check into a time-sink once it reaches a
  typesetter — unrecognized or unconfigured punctuation characters left unresolved in the
  inventory's settings can surface late, as a pile of issues the typesetter has to chase
  down one by one instead of the team resolving them upfront.
- **Select "Show sequences" in the inventory's Inventory menu**, so that punctuation
  *sequences* — combinations of punctuation characters, such as unmatched punctuation
  pairs — are actually inventoried, not just the individual characters. Without it, the
  **"Punctuation (sequences)"** checkbox under Run Basic Checks doesn't meaningfully check
  punctuation sequences or combinations, so the review can look complete while a whole
  class of problems goes unseen. This lesson covers punctuation sequences only;
  quotation marks specifically are more complicated and are intentionally out of scope
  here, deferred to a separate, future addition.

That third point comes from a real case. One consultant believed their Punctuation
Inventory review had been thorough — yet the typesetter later came back with a long list
of unmatched punctuation pairs (mostly involving quotation marks) that had never
surfaced. The cause was traced to "Show sequences" not having been selected in the
Inventory menu — and without that setting, the "Punctuation (sequences)" checkbox under
Run Basic Checks doesn't meaningfully check punctuation sequences or combinations. The
review *looked* complete and wasn't — the false-clean pattern again, this time produced
by a single unselected menu option rather than by anyone skipping a step.

> **WARNING — watch for a false-clean result here too:** don't let "we're close to done"
> stand in for "someone reviewed the Punctuation Inventory." Confirming it was actually
> opened and reviewed — not just assumed already handled earlier in the process — is the
> single highest-value thing you can do here before the project reaches a typesetter.

**Key takeaways**
- The Punctuation Inventory lives in Paratext itself (Checks > Inventories) — it is not
  a PTXprint or typesetter-side tool, and reviewing it is part of your own formatting
  check, not something to defer to the typesetter.
- Confirm it was genuinely reviewed at this stage rather than assumed clean from earlier
  in the project.
- Loose or unconfigured settings in the inventory are what create a late time-sink for
  the typesetter — catching them here is cheaper for everyone.
- "Show sequences" must be selected in the inventory's Inventory menu for the
  "Punctuation (sequences)" Basic Check to actually check sequences — without it, the
  review can look complete and still miss unmatched punctuation pairs. (Quotation marks
  specifically are out of scope for this lesson.)

## Challenge

**✏️ Try this:** A team tells you their Basic Checks show a short list of formatting
errors, all in the second half of Mark — a few "wrong marker" flags on section headings,
one heading that seems to be missing, and a reference that looks garbled. They ask you
to help them go through the list and fix each one.

1. Before working the list top to bottom, what would you check first, and why?
2. If you find a missing `\p` marker after a section heading partway through Mark, what
   do you expect that to explain about the other flags on the list — verse text swallowed
   into the heading style — and how would you confirm your suspicion before telling the
   team?
3. Separately: the same team says the Punctuation Inventory "was already checked back
   when we started the project." The project has since been through two more revision
   passes and is headed to a typesetter in three weeks. What would you say to the team,
   and what would you specifically ask them to open and show you? Which inventory
   setting would you confirm was selected before accepting that the review was complete?

Write your answers as if reporting back to the team lead afterward — include exactly
what you'd say and what you'd ask them to do next.

## Change

**✏️ Reflection:** Think of a project you support that's getting close to typesetting.
Has anyone on the team — or you — actually opened the Punctuation Inventory recently, or
is its "clean" status something everyone is assuming? What would it cost to confirm it
now versus finding out from a frustrated typesetter later?

**Next step:** Before your next formatting-check session, write down the structural-first
order from this lesson (marker pairs → ghost markers → long/short verses → headings →
book titles → references → footnotes) somewhere you'll actually have it in front of you,
and add "Punctuation Inventory — genuinely reviewed?" as a standing question for any
project nearing typesetting.

**Coming up:** Lesson 5 turns to glossary linking and the final layout and draft-PDF
read-through — where, like the Punctuation Inventory here, you'll be doing hands-on
technical setup alongside your coaching role.
