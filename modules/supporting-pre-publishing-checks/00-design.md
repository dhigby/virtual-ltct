# Course Design Document

> **Before proceeding:** Copy this file into your new course folder as `00-design.md`. Content drafting must not begin until this document is approved by a human reviewer. This is stage 1 of the [production pipeline](../../process/PROCESS.md) — see [`process/stages/01-design.md`](../../process/stages/01-design.md) and [`02-approve.md`](../../process/stages/02-approve.md).

## Course overview

| Item | Description |
| --- | --- |
| **Title** | Supporting Scripture Pre-publishing Checks |
| **Competencies addressed** | Translation Tools; Digital and Print Publishing |
| **Target outcome level** | 3 - Independent — confirmed at approval; the mentor-reviewed scenario bank is mandatory (see "Outcome-level open question" below) |
| **SME(s) consulted** | Jenni Beadle — design interview conducted 2026-08-24/25 (recorded verbatim in "SME knowledge notes" below). Doug Higby — course author, ruled (2026-08-25 meeting) that the course must map to an honest CBC rung rather than blend levels. |
| **Design status** | Approved by Kevin Nicholas on 2026-08-27 |

> **Amendment, confirmed by Kevin Nicholas on 2026-08-28:** Kevin raised a new field case
> post-approval, on issue #40 — significant time spent with a typesetter working through
> Paratext's **Punctuation Inventory** (Checks > Inventories) settings ahead of typesetting.
> Jenni placed this as a new subsection of `04-formatting-and-references.md` (see that
> row in "Module breakdown" and field case 7 in "SME knowledge notes" below). Kevin confirmed
> (PR #43) the placement and time budget are correctly scoped. `module-author` may proceed
> with drafting `04-formatting-and-references.md`.

## Outcome-level open question (resolve before approval)

The `README.md` frontmatter and issue #40 both target `3 - Independent`, delivered self-paced
with "a self-check rubric rather than a mentor in the loop." That conflicts with this repo's
mapping policy (`process/notes/cbc-level-mapping.md`): **a self-study module, on its own,
delivers `1 - Has Knowledge`; higher rungs are earned through mentor-supported practice, not by
finishing the module.**

The SME interview softened but did not resolve this: "Independent" here means independent at
the *support/diagnose/coach* role — a real doing-with-a-team competency, not a solo technical
skill — which is a more defensible claim than most self-paced `3 - Independent` courses. Doug's
ruling was to **stay within the CBC as defined** rather than merge or reinterpret levels, and
Jenni's recommendation was either (a) re-level the course to `1 - Has Knowledge`, or (b) keep
`3 - Independent` but **build in genuine mentored practice** — a real supporting session, or at
minimum a mentor-reviewed scenario bank, not just reading plus a quiz.

**This design proceeds on option (b)**, since it best matches the course's purpose (preparing a
consultant to actually support a team, not just recognize the checks) and the SME's field
material is rich enough to support mentor-reviewed scenarios. The module breakdown below
therefore treats the scenario bank as **mentor-reviewed**, not self-checked, and the mentor guide
is a required structural file, not optional. **The Design Approver must confirm this resolution
(or direct a re-level to `1 - Has Knowledge`) before this document is approved** — it is the one
open decision left from the SME interview.

> **Resolved at approval (2026-08-27):** the Design Approver (Kevin Nicholas) confirmed option
> (b) — the course keeps `3 - Independent`, and the mentor-reviewed scenario bank plus required
> mentor guide are what earn that claim. They are not optional components.

## Scope

**In scope** — the pre-publishing **text checks**, matching workbook chapters 3–6 and 8 of
[Scripture Pre-publishing Checks](../scripture-pre-publishing-checks/README.md) ("Finalizing Your
Translation for Publication"):
- Parallel passages (ch. 3)
- Proper names and Biblical Terms, including the wordlist/spell-checking check area (ch. 4)
- Numbers and Measures — **two separate checks in Paratext**, not one combined check (see
  "Tool-version specifics" correction below): the Numbers check exists now (limited scope); the
  Measures check is still under development and not yet reliably available (ch. 5)
- Formatting checks — structural integrity, section headings, book titles, references, footnotes
  (ch. 6)
- The final draft-PDF read-through in PTXprint (ch. 8)
- The glossary-linking **operation and its checks** (marks live in the text) — glossary *content*
  itself is out of scope (see below)

**Out of scope** — publication *furniture*: illustrations, front/back matter, and glossary
*content* (workbook chapters 1, 2, 7). These need a separate, not-yet-built course ("Course B").
This course states "a glossary exists" as a prerequisite where the linking operation is taught.

## Learning objectives

Objectives are anchored to the `2 - With Assistance` row of each ladder component in
`competencies/translation-tools.md` and `competencies/digital-and-print-publishing.md` — the row
whose `Reaches` column is `3 - Independent` — per the ladder-offset rule.

**Priority column (added after SME review, 2026-08-27):** Jenni reviewed the drafted objectives
and flagged that objectives 2 and 5, as originally written, asked the LTC learner to judge
*linguistic meaning* — something an LTC does not normally have the language knowledge to do (they
typically don't speak the project language). Both are **reframed below** so the LTC's job is
process/consistency-checking and routing to the team, not adjudicating meaning themselves. Jenni
also assessed that objectives 1, 3, 4, and 8 describe situations that occur **less frequently in
the field** than 6, 7, 9, 10 (confirmed as core/solid) and the reframed 2 and 5. The table below
marks each objective **Core** or **Secondary** so the module breakdown and scenario bank can weight
lesson depth toward the objectives learners will actually meet most often, without dropping
secondary objectives or their competency coverage.

| # | Priority | Objective | Source | Assessed by |
| --- | --- | --- | --- | --- |
| 1 | Secondary* | Learner can recognize a "false-clean" check result — denied errors, a mass-approved wordlist, blanket-approved statuses — in any check area, and lead the team to reveal what was hidden, reset statuses, and re-run the check honestly | Translation Tools 2.0, `2 - With Assistance` — "Assist in training others on the use of translation tools" | Quiz + Scenario Bank (spine scenario) |
| 2 | Core | Learner can confirm that a parallel-passage comparison check was actually run and its results reviewed by the team, flag passages the tool surfaces as inconsistent (by the tool's own comparison, not the learner's own linguistic judgment) back to the team for adjudication, and check that the team's own decisions about legitimate variation vs. over-harmonising — not the LTC's — are driving the resolution | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 3 | Secondary | Learner can diagnose the root cause of a Biblical Terms rendering error (e.g. an unselected first-word-of-verse rendering auto-grabbed by Paratext) and coach the team to correct it — without taking over their keyboard | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 4 | Secondary | Learner can diagnose configuration-caused Send/Receive and performance slowdowns from over-adding terms to the *Project* Biblical Terms list, and advise the team on right-sizing it | Translation Tools 5.0 (Scripture Collaboration), `2 - With Assistance` — "Advise users in best-practices for collaboration and data safety... assist users to configure plans and tasks in a way that helps them" | Quiz + Scenario Bank |
| 5 | Core | Learner can confirm, for both the Numbers check and the Measures check, whether that check is actually available and mature enough to rely on for the team's Paratext version at the time of support (Numbers exists now but is limited in scope; Measures is still under development and may not yet be available), run whichever check(s) are usable against the team's *already-agreed and documented* approach (not the LTC's own judgment of what the rendering should be), and refer any gaps or contradictions the check(s) surface back to the team to resolve rather than deciding new renderings | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 6 | Core | Learner can diagnose formatting-check failures — unclosed marker pairs, ghost markers, wrong markers, book-title/heading/reference errors — working structural-first, and coach a team to a zero-error result | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 7 | Core | Learner can diagnose over-linked glossary marking (every occurrence vs. first-per-section) and coach the team to unlink and relink at the correct scope | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 8 | Secondary | Learner can advise a team on a single- vs. two-column layout decision based on reader/community expectation (not just word length), and set up a hyphenation file so long words can break in a two-column layout | Digital and Print Publishing 1.0 (Print Publishing), `2 - With Assistance` — "Customize and use appropriate tools to produce publishable output for Scripture and dictionaries" | Quiz + Scenario Bank |
| 9 | Core | Learner can lead a team through the final PTXprint draft-PDF read-through (spreads, orphan words, footnote shifts, heading placement, underfilled pages) and resolve or triage what it surfaces, deferring true typesetting composition to the typesetter | Digital and Print Publishing 1.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 10 | Core | Learner can decide, for any surfaced issue, whether it is theirs to resolve, the team's translation decision, or needs escalation (an LT mentor for tooling, a Translation Consultant for content) | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |

\*\* **Objective 5 / Numbers-vs-Measures — resolved.** Objective 5 now covers the
LTC's process/routing role across **two separate checks** (Numbers, released but limited in
scope; Measures, still under development and not yet reliably available) rather than one combined
check, per Jenni's 2026-09-01 correction (see "Tool-version specifics" below). It is kept as a
single objective here because the LTC's job — confirm which check(s) are actually available and
mature, then route gaps back to the team — is the same underlying skill regardless of which
check(s) exist at a given time. **Ruled by Kevin Nicholas (Design Approver), 2026-08-31, PR #44:
objective 5 stays a single objective.** Assessing Measures separately would mean assessing a
check that is not yet reliably available; revisit a split only if Measures ships and matures
enough during the life of this course to warrant its own assessed item.

\* **Objective 1 / spine tension — flagged, not resolved.** Objective 1 (recognizing false-clean
results) was the SME's original **spine scenario**, framed in the interview as a cross-cutting
thread running through *every* check area across the whole course, not a single stand-alone topic.
Jenni's frequency assessment in this review round rates it lower-frequency in the field than the
core objectives above, which pulls toward giving it lighter, secondary billing. Those two signals
are in tension: downgrading objective 1 to a single lightly-covered section could weaken the
spine/spiral structure that was a deliberate design premise from the earlier interview (see "SME
knowledge notes" below). **This design does not silently resolve that tension** — it keeps
objective 1 recurring in small touches across the core lessons (a short "watch for a false-clean
result here too" callout in each core check-area lesson) rather than either (a) a standalone heavy
module or (b) dropping the spine framing. **The Design Approver should confirm this treatment is
what Jenni intends**, or direct a different balance between "spine" and "secondary."

> **Resolved at approval (2026-08-27):** the Design Approver (Kevin Nicholas) confirmed the
> recurring-callout treatment — a short false-clean watch-for in each core check-area lesson,
> plus the theme woven through two scenario-bank cases, with no heavy standalone module.

## Module breakdown

Weighting after SME review: lesson depth and scenario-bank cases now concentrate on the core
objectives (2, 5, 6, 7, 9, 10). The secondary objectives (1, 3, 4, 8) are still taught and still
assessed, but consolidated into lighter sections rather than each getting standalone billing —
except objective 1, which per the flagged tension above is kept as a short recurring callout inside
each core check-area lesson instead of either a heavy standalone module or being dropped.

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-supporting-the-final-turn.md` | The translation process as a 6-stage spiral; Stage 6 as the final turn re-running earlier checks; the cross-cutting spine (false-clean results) introduced as a recurring watch-for, not a standalone topic; the consultant's role (diagnose, coach, never touch the keyboard); when to escalate | 1 (light touch), 10 | 35 |
| `02-wordlist-and-biblical-terms.md` | Biblical Terms rendering errors including the no-selection rendering error (secondary, condensed); Project Biblical Terms bloat and performance (secondary, condensed); recurring false-clean callout for this check area | 1 (callout), 3, 4 | 40 |
| `03-parallel-passages-and-measures.md` | Parallel passages: confirming the comparison check was run and routing tool-flagged inconsistencies to the team (core, expanded); Numbers and Measures — **two separate checks, not one**: confirming which of the two is actually available and mature for the team's Paratext version (Numbers exists now, limited scope; Measures still under development, not yet reliably available), running whichever is usable against the team's already-agreed, documented approach, and routing gaps back to the team (core, expanded); recurring false-clean callout | 1 (callout), 2, 5 | 60 |
| `04-formatting-and-references.md` | Formatting checks in structural-first order: marker-pair census, ghost markers, long/short verses, section headings, book titles, references, footnotes (core); **new subsection (confirmed by Kevin Nicholas on 2026-08-28): Punctuation Inventory (Checks > Inventories) ahead of typesetting** — reviewing/using the inventory itself (not PTXprint or the typesetter's own tooling), confirming the inventory was actually reviewed rather than assumed already handled earlier in the process (echoes the objective-1 false-clean theme), and common settings issues that turn this into a time-sink for a typesetter; recurring false-clean callout | 1 (callout), 6 | 75 — if drafting runs long, trim the reference/book-title portion first rather than exceed the 90-minute cap |
| `05-glossary-linking-and-layout.md` | Glossary-linking scope (over-linking, core); single- vs. two-column layout and hyphenation decisions (secondary, condensed); the PTXprint draft-PDF read-through (core); recurring false-clean callout | 1 (callout), 7, 8, 9 | 65 |
| `06-scenario-bank.md` | Mentor-reviewed applied scenarios weighted toward core objectives (2, 5, 6, 7, 9, 10 each get a full scenario), secondary objectives (3, 4, 8) folded into one combined scenario, and objective 1 (false-clean) run as a thread inside two of the core scenarios rather than its own case | 1–10 | 60 |
| `07-mentor-guide.md` | Facilitator notes: what to watch for in each scenario response, the "good" markers from the SME interview | — | — |
| `08-quiz.md` | Assessment | 1–10 | — |
| **Total learner seat time** | | | **335** (confirmed by Kevin Nicholas on 2026-08-28; was 320 as approved 2026-08-27) |

## Assessment plan

A 20-question quiz (`08-quiz.md`), 80% (16/20) to pass, drawn from all ten objectives but
question-count weighted toward the core objectives (2, 5, 6, 7, 9, 10 get roughly two questions
each; the secondary objectives 1, 3, 4, 8 share the remainder) — mixed recognition/diagnosis-
reasoning format (e.g. "given this check result, what happened and what do you do next"), since the
quiz alone can only assess recognition, not live coaching. The mentor-reviewed scenario bank
(`06-scenario-bank.md`) is the component that earns the `3 - Independent` claim (see "Outcome-level
open question" above): six scenarios weighted the same way — one full scenario each for the six
core objectives, one combined scenario folding in the three remaining secondary objectives
(3, 4, 8), and objective 1 (false-clean results) woven as a thread inside two of the core scenarios
rather than given its own case, per the flagged spine tension above. Each scenario is built from a
confirmed SME field case or a stated mistake pattern, asking the learner to write out how they
would diagnose the situation, what they would say to the team, and what (if anything) they would
escalate. A mentor scores each against the `07-mentor-guide.md` watch-for list, not just against
a right answer — consistent with `2 - With Assistance`/`3 - Independent` assessment being about
reasoning and coaching quality, not a single correct fix.

## SME knowledge notes

Recorded from Jenni Beadle's SME interview for issue #40, 2026-08-24/25. Not fabricated —
this is the field material actually reported; anything not covered here (notably the
numbers/weights/measures check area) has **no confirmed field case yet** and should not be
invented for scenario content.

**Update, SME review 2026-08-27:** Jenni's review of the drafted objectives confirmed that the
LTC's role in the field cases below was never to judge linguistic meaning herself — the
process/routing framing of objectives 2 and 5 (confirm the check ran, refer the tool's own
flagged inconsistencies to the team for a decision) matches how these situations actually play
out. She also assessed the Biblical Terms no-selection rendering error case (4) and the layout/hyphenation case
(5) as lower-frequency in her own field experience than the wordlist false-clean cases (1, 2) and
the Biblical Terms bloat case (6) — hence objectives 3 and 8 (drawn from cases 4 and 5) being
marked Secondary above, along with objective 4 (case 6) and objective 1 (cases 1–2, under the
flagged spine tension). Objective 7 (glossary over-linking, case 3) remains Core. No new field
cases were added in this review round; the stories below are unchanged.

### Real field cases

1. A team **denied errors they didn't understand**, rather than resolving them. The consultant
   re-ran the check to surface the denied errors and explained the cause of each one.
2. A wordlist of thousands of words was **all marked correct** (blanket-approved). The consultant
   reset every entry to *unknown* and re-ran the wordlist checks, especially for incorrectly
   split or joined words.
3. Glossary links were applied to **every occurrence** of a term rather than the first occurrence
   per section. The consultant unlinked and relinked at "first occurrence in every section," not
   "all."
4. A team added the **first word of the verse** as a term's rendering in the Biblical Terms tool,
   because they hadn't *selected* the correct word/phrase before adding — Paratext grabbed the
   verse's first word by default. The consultant deleted the bad rendering, selected the correct
   text, and added it properly.
5. An expat project admin assumed **single-column** layout (the language has long words and the
   team had never used hyphenation), but the community currently reads/uses Bibles in a
   **Language of Wider Communication (LWC)** — this is a Bible translation project, so the
   community doesn't yet have a Bible of its own in their language — and those **LWC Bibles are
   conventionally published in two columns**, which sets the readers' layout expectation. The
   layout has to match that reader expectation, not just word length. The consultant established
   the reader-expectation requirement with the team, then **built a hyphenation file** so long
   words could break correctly in the two-column layout. This is a concrete case where the
   consultant both advises a publishing-layout decision *and* does the technical setup —
   confirming Digital and Print Publishing is honestly earned by this course.
6. A consultant **added the entire "All Biblical Terms" list into the Project's Biblical Terms**
   (not just selected it for viewing). The slowdown came specifically from adding the terms to
   the *project* — bloating the project and slowing Send/Receive — not from merely viewing a
   large list. It was **hard to convince the team of the actual cause**. The support skill here is
   diagnosing a configuration-caused performance problem and advising the team to right-size the
   project's Biblical Terms list.

7. **(Added 2026-08-28, Kevin Nicholas, post-approval comment on issue #40 — confirmed
   by Kevin Nicholas on 2026-08-28, PR #43.)** Kevin spent significant time,
   while working with a typesetter, going through **Paratext's Punctuation Inventory**
   (Checks > Inventories) settings ahead of typesetting — this is Paratext's own inventory,
   not PTXprint or the typesetter's own tooling. He flagged it as a real time-sink worth
   covering. Jenni's placement decision: this belongs alongside the other formatting-check
   areas in `04-formatting-and-references.md`, as its own subsection rather than a passing
   scenario detail, partly framed as "confirm this was actually reviewed, don't just
   assume it's already handled" — echoing the false-clean theme from field cases 1–2 and
   objective 1.

**Cross-cutting theme across these cases:** *false-clean results* — a team clears a checklist
without genuinely checking. The consultant's job is to spot the fake all-clear, reset statuses,
and re-review — while the **translator does the actual fix** ("never touch their keyboard,"
matching the same rule taught in the team workbook).

### Common mistakes, organized by check area (Jenni's chosen axis — finalized 2026-08-24)

- **Wordlist / spelling** (its own area — a spiral, high-volume check across tens of thousands of
  words): blanket-approving the whole wordlist instead of genuinely reviewing it.
- **Biblical Terms & renderings**: the no-selection rendering error (didn't select the
  correct text before adding, so Paratext auto-grabbed the first word of the verse).
- **Parallel passages**: over-harmonising — forcing all parallel passages to match exactly and
  erasing legitimate variation. They must be consistent in *meaning*, not necessarily identical in
  *form*.
- **Numbers & Measures** (two separate checks, not one combined check — see the 2026-09-01
  correction in "Tool-version specifics" below): Numbers exists now (limited scope); Measures is
  still under development and not yet reliably available. **Open** — this area is still new to the
  SME; no field case yet for either check. Do not invent one for scenario content; fill from
  experience if one surfaces before drafting.
- **Formatting & markup**: wrong markers, unclosed footnote pairs (`\f…\f*`), wrong-case
  characters.
- **References & book names**: missed book-name checks, foreign `\r` abbreviations, inconsistent
  table of contents.
- **Glossary linking**: over-linked (every occurrence, instead of first-per-section).
- **Layout & publishing (read-through)**: single- vs. two-column choice, hyphenation.
- **Cross-cutting spine**: don't trust an old or fake "all clear" — denied errors, skipped checks,
  or mass-approved statuses can occur in *any* of the areas above, and it's the pattern the
  consultant must watch for everywhere, not just once.

**Spiral framing (key design premise, Jenni's language):** the translation process is a
**6-stage spiral**; Stage 6 (pre-publishing) is the **final turn** — re-running checks begun back
in Stage 1, driven this time to zero-error. Long-running checks — especially the wordlist — still
surface errors at Stage 6 even though they've "already been done." *Spine + spiral* together are
the course's central insight: **"we already did that" is the trap** the consultant has to watch
for, at every check area, every turn of the spiral.

### What "good" looks like at the target level (finalized — Jenni approved this list)

Observable markers of a competent supporting consultant:
- (a) **Doesn't trust a clean result** — actively reveals denied or mass-approved items and
  re-checks (the spine).
- (b) **Diagnoses cause, not symptom**, and explains each check in plain terms the team can
  understand.
- (c) **Coaches, doesn't take over the keyboard.**
- (d) **Drives the tools correctly** — chooses the sensible list/option (e.g. link
  first-per-section, not every occurrence) and can undo a bad move.
- (e) **Judges legitimate variation vs. error**, especially in parallel passages.
- (f) **Advises the surfaced decisions** (layout, hyphenation, renderings) and defers to the team
  or another consultant where it's genuinely their call, not the LTC's.
- (g) **Knows when to escalate** — an LT mentor for tooling problems, a Translation Consultant for
  content/translation decisions.
- (h) **Attention to detail**, especially in the formatting checks and the final read-through
  (Jenni's addition to the list).

### Tool-version specifics

- The team workbook's chapter 8 (final draft-PDF read-through) was verified against
  **PTXprint 3.0.38**; PTXprint is updated often, so menu labels may move between versions.
  Underfilled-page auto-fill is available from **PTXprint v3.0.19+**.
- **Correction, SME review 2026-09-01 (Jenni Beadle, confirmed via a screenshot of the actual
  Biblical Terms list panel):** Numbers and Measures are **two separate checks in Paratext, not
  a combined "Measures and Money and Numbers" list.** The **Numbers** check/list already exists
  and has been released, but its scope is fairly limited. A **Measures** check (money/weights) is
  still **under development and not yet released** — Jenni is confident it's coming, but it is not
  a mature, available check at the time of this design. Both appear as separate entries in
  Paratext's Biblical Terms list panel alongside other unrelated lists (Major Biblical Terms, All
  Biblical Terms, NT Key Biblical Terms, Inclusive/Exclusive Pronouns, Younger/Older Siblings,
  etc.) — there is no single combined list to point learners at. The earlier note in this section
  claiming current Paratext versions show "Measures and Money" and "Numbers" as combined lists was
  incorrect and is removed.

---

### Summary and handoff

**10 learning objectives** (6 Core: 2, 5, 6, 7, 9, 10; 4 Secondary: 1, 3, 4, 8 — see the priority
marker added after Jenni's 2026-08-27 review), spanning both Translation Tools and Digital and
Print Publishing, anchored to the `2 - With Assistance` ladder rows that reach `3 - Independent`.
Objectives 2 and 5 were reframed in this review from meaning-judgment to process/consistency-
checking and team-routing, since an LTC does not normally know the project language. **8 planned
files** (5 numbered content lessons + scenario bank + mentor guide + quiz), totaling **335 minutes**
(~5.6 hours) of learner-facing seat time (content lessons + mentor-reviewed scenario bank; mentor
guide and quiz excluded from the total per convention), now weighted toward the core objectives.
The 335-minute total includes the Punctuation Inventory subsection added to
`04-formatting-and-references.md` on 2026-08-28 and **confirmed by Kevin Nicholas on 2026-08-28**
(see the amendment note at the top of this document); the previously approved total was 320 minutes.
Every objective traces to a descriptor component and to both the quiz and the scenario bank. One
open tension is flagged and left for the Design Approver: whether the lighter, recurring-callout
treatment of objective 1 (false-clean results) adequately preserves the "spine" framing from the
original SME interview, or whether it should get heavier standalone treatment despite its lower
field frequency.

**This document is now the contract** for `module-author` and `quiz-writer`: they are to draft
only the modules and objectives specified here, not freelance additional content or competencies.

**Before drafting begins, this design needs human approval — and specifically rulings on two open
points**: (1) the outcome-level open question above (confirm `3 - Independent` with mandatory
mentor-reviewed scenarios, as designed here, or direct a re-level to `1 - Has Knowledge`), and
(2) the objective-1/spine tension flagged in "Learning objectives" (confirm the recurring-callout
treatment, or direct heavier standalone treatment for false-clean recognition). Requesting review
from the Design Approver per stage 2 of the pipeline.

> **Both rulings made at approval (2026-08-27):** `3 - Independent` confirmed with the
> mentor-reviewed scenario bank mandatory, and the recurring-callout spine treatment confirmed.
> See the resolution notes in each section above. Drafting may begin.

**Amendment confirmed by Kevin Nicholas on 2026-08-28 (PR #43):** the Punctuation Inventory
subsection added to `04-formatting-and-references.md` (field case 7, module-breakdown row, and
revised 335-minute total) is confirmed correctly scoped and placed. `module-author` may draft
`04-formatting-and-references.md`.
