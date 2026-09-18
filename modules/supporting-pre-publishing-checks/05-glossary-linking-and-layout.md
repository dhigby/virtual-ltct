# Lesson 5: Glossary Linking and Layout

**Estimated time:** 75 minutes

**Purpose:** Support a team through the glossary-linking operation, layout and
hyphenation decisions for print, and the final PTXprint draft-PDF read-through — the
last checks before a team's files go to the typesetter.

## Learning objectives

- You will be able to:
  - Diagnose over-linked glossary marking (every occurrence instead of
    first-per-section).
  - Coach a team to unlink and relink at the correct scope.
- You will be able to:
  - Advise a team on a single- versus two-column layout decision based on reader and
    community expectation — not just word length.
  - Use the Wordlist's Show hyphenation view to approve correct guesses and correct
    wrong ones — individually and in batches — so long words break correctly in a
    two-column layout.
- You will be able to:
  - Lead a team through the final PTXprint draft-PDF read-through.
  - Resolve or triage what it surfaces, deferring true typesetting composition to the
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

Running the glossary-link operation doesn't produce a pass/fail status, and it doesn't
leave behind a durable log either: the linked-occurrences report it shows you is
**transient** — it gets replaced the moment you link the next item or group, and
glossary linking is commonly done in batches because it's a complicated operation. So
you can't tell a team "go check the report" after the fact — by then it's gone. Row
count wouldn't be a reliable signal anyway, since some glossary terms are naturally far
more frequent in the text than others; there's no universal "too many rows" threshold to
apply.

The durable, re-checkable way to diagnose over-linking is Paratext's **Find** feature.
Search for the glossary term (e.g., "angel") across the project — Find gives you a live
results list you can re-run any time, unlike the transient link report. Glossary links
are marked directly in the text with `\w` and `\w*` around the linked span, in the form
`\w <surface text>|<rendering>\w*` — for example `\w angel messenja guy\w*`, or, where
the surface form in the text differs from the glossary entry's canonical rendering,
`\w angel guy|Angel messenja guy\w*`. Look at each Find result: if `\w...\w*` markup
wraps **every single occurrence** of the term, that's over-linking; if it wraps only the
**first occurrence per section**, the scope is correct. To fix over-linking: **unlink**
the over-applied marks and **relink at "first occurrence in every section,"** not "all
occurrences." This is squarely something you can drive yourself in the tool — it's a
scope setting, not a content judgment — but the same "never touch the team's actual
translation" boundary still applies to any text changes; the linking operation itself is
markup, not translated content.

> **WARNING — Find will fool you if you skim it:** Find matches the search term both in
> genuine occurrences in the running text *and* inside the rendering portion of an
> existing `\w...\w*` marker (since the marker's canonical rendering contains the same
> words). A high hit count on its own tells you nothing — you have to open each result
> and check whether it's a fresh occurrence wrapped in its own `\w...\w*` markup, or text
> that already sits inside another marker's rendering. Don't trust the count; read the
> markup.

There's a further layer to this same check: a Find hit landing inside an existing
`\w...\w*` marker isn't automatically "already linked, fine" — you also need to check
**whose rendering it actually is**. Glossary terms can overlap when one is a phrase
containing another as a single word — for example "Holy Spirit" (a phrase term) contains
"Spirit" (also a standalone single-word term). If you're searching for "Spirit" and a hit
sits inside a marker whose rendering is "Holy Spirit," that's not a stale or over-linked
occurrence of "Spirit" at all — it's correctly linked to the broader phrase term, and it
should **not** also be separately linked as "Spirit." The correct workflow is to link
phrase terms **before** their component single words, precisely so the single word never
gets wrongly linked inside phrase territory. So when a hit falls inside an existing
marker, read the rendering, not just the presence of markup: a broader phrase term there
is expected and correct, not something to flag.

This is the same trap as the Project Plan checkbox from Lesson 1: the Project Plan's
"Check and link glossary entries" task (Stage 6, Final Preparation for Publication) has
its own checkbox, but that box is ticked by a person, not generated from the actual
linking data. A team can tick it as done without the linking having been genuinely
reviewed — so treat it the same way as any other Project Plan status: a starting point,
not confirmation. A Find search over the marked-up text is what actually tells you
whether the scope was right.

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
2. **Do the technical setup** — once two-column layout is the right call, open
   **Tools > Wordlist**, and from the Wordlist's own tab menu choose **View > Show
   hyphenation** (alongside "Show morphology" and "Show spelling status"). This adds a
   hyphenation column showing Paratext's guessed break points for each word, marked with
   equal signs — e.g. `an=ti=no=mi=an=ism` — each `=` a place the word may legitimately
   break across a column.

   Every guessed word carries a tick showing its approval status:
   - A **grey tick** means Paratext *guessed* the breaks — not yet reviewed.
   - A **green tick** means the breaks are **approved**.

   Your job is to work through the guesses:
   - **Correct guess** — click the grey tick; it turns green. Approved.
   - **Wrong guess** — click the word and add or remove `=` marks where the breaks
     should actually fall; the tick turns green automatically once you edit it.
   - **Batch-approve a run of correct words** — select the first word, then
     shift-click to extend a consecutive selection (or Ctrl-click to pick several
     non-consecutive ones), then use the Wordlist's tab menu > **Edit > Approve word
     hyphenation** to approve them all at once. As you approve more words, Paratext's
     guesses get better, so late in the review not every remaining word needs
     individual attention.

   Your approvals save to **`hyphenatedWords.txt`** in the project folder when you close
   the Wordlist. Lines *without* a leading asterisk are still just Paratext's unapproved
   guesses; a leading `*` marks a line the team has approved.

   > **WARNING:** PTXprint's draft-PDF export will use unapproved guesses as well as
   > approved hyphenation, so a draft can look fine even with guesses left unreviewed.
   > Best practice is to approve hyphenation before the files go to print — don't let
   > unapproved guesses ride through to the final publication.

   > **NOTE:** In a Study Bible Publication project, the Wordlist is view-only, so
   > hyphenation can't be approved there directly — watch for this if a team's project
   > is set up that way.

   Don't confuse this with **word break characters** (used for scripts that don't
   separate words with spaces), which is a separate setting under Project > Project
   settings > Language settings > Other Characters tab — a different problem from
   hyphenating long words within a two-column layout.

   > **TIP:** For advanced or unusual cases, `hyphenatedWords.txt` itself can be
   > hand-edited to customize which characters represent hard/soft hyphens and
   > hyphenated markers (`HardHyphen`, `SoftHyphen`, `SoftHyphenOut`,
   > `HyphenatedMarkers`). That's beyond what most teams need — the approve/correct
   > workflow above covers the normal case.

   This is genuine hands-on tooling work, not just advice-giving.

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
  drive the fix yourself, but check via Find (the link report is transient) and inspect
  the `\w...\w*` markup on each result rather than trusting a hit count.
- Layout decisions follow reader expectation, not word length alone; once the layout
  is set, working the Wordlist's Show hyphenation view — approving correct guesses
  (grey tick → green), fixing wrong ones with `=` marks, and batch-approving with
  shift-click/Ctrl-click — is hands-on technical work you do yourself, and it should
  be done before print, since PTXprint's draft PDF will use unapproved guesses too.
- The draft-PDF read-through is your last chance to catch spreads, orphans, footnote
  shifts, heading placement, and underfilled pages — triage what you find rather than
  trying to resolve everything yourself.

## Challenge

**✏️ Try this:** Five short exercises across this lesson's three areas. Write each
answer, then check it against the Content section above.

1. **Write the Find check** that tells over-linking apart from correctly-scoped linking:
   why the linked-occurrences report itself isn't something you can rely on, what you'd
   search for with Find instead, and what you'd look for in the `\w...\w*` markup on
   each result to tell the two scopes apart. State it as a general procedure you could
   run on any project, not a verdict on one. Then add one line on why a high Find hit
   count alone doesn't prove over-linking.
2. **Name the relink scope in the tool's own words** — the exact phrasing this lesson
   uses for the correct scope, and the wrong option it's easy to pick instead. Then
   say in one line why that scope is the right one from the reader's side.
3. **Two criteria, one decision.** Write one line on what word length can legitimately
   tell you about a layout choice, and one line on what actually sets the baseline for
   it. Then draft the single question — one sentence — you'd ask a team to establish
   that baseline, aimed at what the community already reads and uses.
4. **Say where the hands-on half of your role begins, and walk the approve/correct
   loop.** At what point in the layout sequence does opening the Wordlist's Show
   hyphenation view become the right next step, and what has to be settled before it?
   Then, for a word Paratext has guessed wrong, describe the two clicks that take it
   from grey tick to green — and separately, describe how you'd approve ten
   already-correct guesses in one action rather than one at a time.
5. **List the five things to watch for** in the draft-PDF read-through, from memory.
   Then write the general test you'd use to decide whether something the read-through
   surfaces is yours to resolve or is typesetting craft belonging to the typesetter —
   again a test you could apply to any issue, not a ruling on a particular one.
   Finally, check which PTXprint version is installed on the machine you'd actually
   use with a team, and note whether it's new enough for underfilled-page auto-fill —
   if it isn't, update to the latest version.

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
