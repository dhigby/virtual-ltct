# Lesson 5: Glossary Linking and Layout

**Estimated time:** 65 minutes

**Purpose:** Support a team through the glossary-linking operation, layout and
hyphenation decisions for print, and the final PTXprint draft-PDF read-through — the
last checks before a team's files go to the typesetter.

## Learning objectives

- You will be able to diagnose over-linked glossary marking (every occurrence instead
  of first-per-section) and coach a team to unlink and relink at the correct scope.
- You will be able to advise a team on a single- versus two-column layout decision
  based on reader and community expectation — not just word length — and set up a
  hyphenation file so long words can break correctly in a two-column layout.
- You will be able to lead a team through the final PTXprint draft-PDF read-through and
  resolve or triage what it surfaces, deferring true typesetting composition to the
  typesetter.

## Connect

**✏️ Reflection:** Picture the very last read-through before a team's files leave for
the typesetter — the point where small, easy-to-miss things (an orphan word, a footnote
that shifted pages, a heading sitting awkwardly) are the *last* chance to catch them.
What does your own attention look like at the end of a long review process — sharp, or
starting to coast? This lesson is partly about building habits that don't depend on
which one you're feeling that day.

> **Note on scope:** This course covers the glossary-linking **operation and its
> checks** — the marks that live in the text. Glossary *content* itself (deciding what
> belongs in the glossary, writing entries) is out of scope here; it's covered in a
> separate, not-yet-built course. This lesson assumes a glossary already exists.

## Content

### Glossary linking: scope, not content

The common field mistake here is **over-linking**: glossary links applied to **every
occurrence** of a term throughout the text, rather than the **first occurrence per
section**. Over-linking clutters the reading experience with repeated glossary markers
and defeats the purpose of a glossary link, which is to point a reader to a definition
the first time they'd plausibly need it in a given section — not every time the word
appears.

To diagnose it: check whether a term that appears many times in a section is linked at
every instance, rather than only its first appearance in that section. To fix it: **unlink**
the over-applied marks and **relink at "first occurrence in every section,"** not "all
occurrences." This is squarely something you can drive yourself in the tool — it's a
scope setting, not a content judgment — but the same "never touch the team's actual
translation" boundary still applies to any text changes; the linking operation itself is
markup, not translated content.

> **WARNING — watch for a false-clean result here too:** A glossary-linking pass that
> shows "complete" doesn't tell you *which* scope was used. Spot-check a high-frequency
> term across a couple of sections before accepting that the linking is correctly
> scoped.

### Layout and hyphenation: reader expectation, not just word length

A real field case: an expat project admin assumed a **single-column** layout was
appropriate because the language has long words and the team had never used
hyphenation. But this is a Bible translation project — the community doesn't yet have
a Bible of its own. What they do have is Bibles in a **Language of Wider Communication
(LWC)**, a regional trade language they currently read and use, and those LWC Bibles
are conventionally published in **two columns**. That existing reader expectation is
the baseline, even though the LWC likely doesn't have the long-word problem the target
language does — which is exactly why layout convention, not word length, should drive
the decision.

Your role here has two parts, and both matter for this course to honestly claim
Digital and Print Publishing competency:

1. **Advise** — establish the reader-expectation requirement with the team. Ask what
   layout format the LWC Bibles and other printed materials the community currently
   reads and uses follow, rather than defaulting to whichever layout avoids a technical
   problem for the target language.
2. **Do the technical setup** — once two-column layout is the right call, **build a
   hyphenation file** so long words can break correctly across the column width. This
   is genuine hands-on tooling work, not just advice-giving.

### The final PTXprint draft-PDF read-through

The last check in this course's scope is the full draft-PDF read-through in PTXprint —
the team's last look before the typesetter. Work through it methodically, watching for:

- **Spreads** — how facing pages look together, not just individually.
- **Orphan words** — a single word left alone at the top or bottom of a column or page.
- **Footnote shifts** — footnotes that have moved to an unexpected page relative to
  their reference.
- **Heading placement** — headings sitting awkwardly at a page or column break.
- **Underfilled pages** — pages with noticeably more white space than their neighbors.

This course's team workbook material was verified against **PTXprint 3.0.38**;
PTXprint updates often, so specific menu labels may have moved by the time you're
using it — verify against the version in front of you rather than assuming the exact
path. Underfilled-page auto-fill has been available since **PTXprint v3.0.19+**, so if
you're supporting a team on an older installation, check that the feature exists before
relying on it.

Your job during the read-through is to **resolve what you can, and triage the rest** —
deciding, for each issue, whether it's yours to fix (a linking or markup scope issue),
the team's call (content), or genuinely a **typesetting composition** decision that
belongs to the typesetter, not you. Don't try to solve typesetting problems that are
properly the typesetter's craft.

**Key takeaways**
- Over-linking is a scope problem (every occurrence vs. first-per-section) — you can
  drive the fix yourself, but spot-check before trusting a "complete" status.
- Layout decisions follow reader expectation, not word length alone; once the layout
  is set, building the hyphenation file is hands-on technical work you do yourself.
- The draft-PDF read-through is your last chance to catch spreads, orphans, footnote
  shifts, heading placement, and underfilled pages — triage what you find rather than
  trying to resolve everything yourself.

## Challenge

**✏️ Try this:** Five short exercises across this lesson's three areas. Write each
answer, then check it against the Content section above.

1. **Write the spot-check** that tells over-linking apart from correctly-scoped
   linking: what kind of term you'd pick, how many sections you'd look across, and
   what result would point to each of the two. State it as a general procedure you
   could run on any project, not a verdict on one. Then add one line on what a
   "complete" status on a linking pass does tell you, and what it doesn't.
2. **Name the relink scope in the tool's own words** — the exact phrasing this lesson
   uses for the correct scope, and the wrong option it's easy to pick instead. Then
   say in one line why that scope is the right one from the reader's side.
3. **Two criteria, one decision.** Write one line on what word length can legitimately
   tell you about a layout choice, and one line on what actually sets the baseline for
   it. Then draft the single question — one sentence — you'd ask a team to establish
   that baseline, aimed at what the community already reads and uses.
4. **Say where the hands-on half of your role begins.** At what point in the layout
   sequence does building a hyphenation file become the right next step, and what has
   to be settled before it? One or two lines — and note whether that step is yours or
   somebody else's.
5. **List the five things to watch for** in the draft-PDF read-through, from memory.
   Then write the general test you'd use to decide whether something the read-through
   surfaces is yours to resolve or is typesetting craft belonging to the typesetter —
   again a test you could apply to any issue, not a ruling on a particular one.
   Finally, check which PTXprint version is installed on the machine you'd actually
   use with a team, and note whether it's new enough for underfilled-page auto-fill.

## Change

**✏️ Reflection:** Of the three areas in this lesson — glossary scope, layout/
hyphenation, and the draft-PDF read-through — which is most likely to come up for a
team you support in the near future? What's one thing from this lesson you'd want to
double-check before that session?

**Next step:** Before your next final read-through with a team, write down the five
things this lesson asked you to watch for (spreads, orphans, footnote shifts, heading
placement, underfilled pages) somewhere you'll actually have it open during the review.

**Coming up:** With the check areas covered, the scenario bank puts everything from
this course together in mentor-reviewed, applied scenarios — including the false-clean
thread woven through two of the core cases.
