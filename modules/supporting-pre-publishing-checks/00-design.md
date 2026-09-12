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
> Paratext's **Punctuation Inventory** (Tools > Checking Inventories > Punctuation Inventory)
> settings ahead of typesetting.
> Jenni placed this as a new subsection of `04-formatting-and-references.md` (see that
> row in "Module breakdown" and field case 7 in "SME knowledge notes" below). Kevin confirmed
> (PR #43) the placement and time budget are correctly scoped. `module-author` may proceed
> with drafting `04-formatting-and-references.md`.

> **Amendment confirmed by Kevin Nicholas on 2026-09-03 (comment on PR #46) — raised during
> Stage 5 SME fact-check, 2026-09-03:** Kevin raised a further field case, confirmed by Jenni
> Beadle's team on the same date — the Punctuation Inventory's **Inventory menu > "Show
> sequences"** option is what makes the **"Punctuation (sequences)"** checkbox under Run Basic
> Checks actually check punctuation sequences/combinations meaningfully. Jenni had proposed
> this as a new, separate section in `04-formatting-and-references.md`; **Kevin's ruling is to
> fold it into the existing Punctuation Inventory subsection as a "Punctuation Sequences"
> addition instead** (see that row in "Module breakdown" and field case 8 in "SME knowledge
> notes" below), scoped to punctuation sequences only (quotation-mark specifics are explicitly
> out of scope, deferred to a future addition). The lighter treatment revises the lesson's
> time estimate to **80 minutes** (Jenni's separate-section proposal had estimated 85), keeping
> 10 minutes of headroom under the 90-minute cap. `module-author` may revise
> `04-formatting-and-references.md` accordingly.

> **Amendment, Stage 5 SME fact-check pass, 2026-09-06 (Jenni Beadle):** Objective 3's field
> case (the "no-selection rendering error" / Paratext auto-grabbing the first word of the verse)
> is **UNCONFIRMED, pending a reply from support.bible** — Jenni saw it once, in one project, has
> not been able to reproduce it since (including in that same project's current version), and it
> was never a reported Paratext error. A screenshot of that one project instead showed something
> closer to a stale/old rendering left in place alongside a later, correct rendering added without
> deleting the original. Two NEW findings, confirmed from live Paratext screenshots reviewed the
> same day, are added in their place as the primary teaching content for objective 3 and lesson
> `02-wordlist-and-biblical-terms.md`: (1) blank renderings are the tool's normal default starting
> state, coached via the Found column/count rather than eyeballing the list, and (2) stale/
> duplicate renderings can pile up when an old rendering isn't deleted after a correct one is
> added. See field case 4 (revised), field cases 9–10 (new), and the revised objective 3 below.
> These two new findings are already confirmed and do not need to wait on support.bible; only the
> no-selection/auto-grab mechanism itself remains pending.

> **Amendment, Stage 5 SME fact-check pass, 2026-09-08 (Jenni Beadle) — terminology/attribution
> correction, no re-approval needed:** Field case 1 ("denied errors") is a **Basic Checks**
> case — Basic Checks results have their own real accept/deny mechanism. It had been conflated
> into the wordlist/Biblical Terms module (`02-wordlist-and-biblical-terms.md`) alongside field
> case 2 (wordlist blanket-approval), which is wrong: Paratext's spelling/wordlist status has
> only three states — **Correct, Incorrect, Undecided** — there is no "deny" action for a word.
> Corrected: field case 1 is now explicitly flagged as Basic Checks-specific in "SME knowledge
> notes"; the `02-wordlist-and-biblical-terms.md` module-breakdown row no longer implies field
> case 1 lives there and now describes its false-clean content using Correct/Incorrect/Undecided
> terminology; the `04-formatting-and-references.md` row now incorporates field case 1 as its
> concrete Basic Checks false-clean example; and the "Common mistakes" section's Wordlist/spelling
> and Formatting & markup/Basic Checks bullets are corrected accordingly. Objective 1's wording is
> generalized so it no longer implies "denied errors" is a wordlist-specific example. This is a
> terminology/attribution fix only — objective 1 still covers the false-clean thread generally,
> module scope and time estimates are unchanged, and no new approval is required.

> **Amendment, Stage 5 SME fact-check pass, 2026-09-10 (Jenni Beadle) — support.bible query
> resolved, no re-approval needed:** A support.bible programmer replied to Jenni's pending query
> (open since 2026-09-06) with a plausible explanation for the field case 4 rendering behavior:
> the team had likely run Paratext's **Guess Renderings** feature before the project had enough
> translated data for it to work reliably, which caused it to default to the verse's first word as
> a guessed rendering. This is a real, named Paratext feature, not a hypothetical — and critically,
> translators would **not** consciously notice this happening, since it's an automated/background
> action rather than something like forgetting to select text. This is now promoted from
> "unconfirmed, pending" to a credible, attributed third pattern to watch for in Biblical Terms
> renderings — attributed as "likely cause per a support.bible programmer," not stated as certain
> fact — alongside the two already-confirmed patterns (blank renderings, stale/duplicate
> renderings), which remain the primary teaching content for objective 3 and lesson
> `02-wordlist-and-biblical-terms.md`. Jenni also confirmed a coaching technique for spotting it:
> scanning through a term's occurrences using the down arrow to quickly step through verses makes
> it visually obvious when a rendering is just repeating the verse's first word, pattern-matching
> the verse text. See field case 4 (revised again below), the revised objective 3, and the
> "Tool-version specifics" section. `module-author` may revise `02-wordlist-and-biblical-terms.md`
> on this basis.

> **Amendment, 2026-09-11 (Jenni Beadle and Doug Higby) — factual update on Numbers/Measures tool
> maturity, no re-approval needed:** Jenni and Doug have worked together to build a new,
> **consolidated Numbers check that covers numbers, weights, and measures together in one check**,
> replacing the old split Numbers/Measures approach (Measures itself was never released as its own
> separate check, so it is being absorbed into this new consolidated check rather than "replaced"
> in its own right). It is currently **in testing** — Jenni has manually imported it under a
> working/test label for testing purposes only, which is not its eventual real name and is not
> used anywhere in this document; it is described functionally throughout as **"the new
> consolidated Numbers check (covering numbers, weights, and measures)."** Jenni and Doug expect
> it to ship before this course publishes, but it is **not yet confirmed/released** as of this
> writing. Because it is still in testing, and because not every team will be on a Paratext
> version that has it once it does ship, the underlying skill objective 5 teaches — confirm what's
> actually available in a team's specific Paratext version before relying on it — is unchanged and
> remains essential: older or unmigrated projects may continue to show only the old, separate
> Numbers check (with no Measures check at all) for some time after the new consolidated check
> releases. This is a factual/content update reflecting real-world tool development, not a scope
> or objective-count change. See the revised objective 5, the revised `03-parallel-passages-and-
> measures.md` module-breakdown row, the revised Numbers/Measures field case, and the revised
> "Tool-version specifics" section below. `module-author` may draft or revise
> `03-parallel-passages-and-measures.md` on this basis.

> **Amendment, Stage 5 SME fact-check pass, 2026-09-11 (Jenni Beadle) — menu-path correction and a
> second, complementary field-confirmed tool, no re-approval needed:** Two corrections, confirmed
> via live Paratext screenshots. First, **the menu path for the Punctuation Inventory has been
> wrong throughout this document** — it is at **Tools > Checking Inventories > Punctuation
> Inventory**, not "Checks > Inventories"; every occurrence is corrected. Second, a **separate,
> dedicated inventory called "Unmatched Pairs of Punctuation"** exists (also under Tools > Checking
> Inventories), confirmed via a live screenshot: a standalone window listing single unmatched
> punctuation pairs (e.g. an unmatched "}", "[", "(") with a count and a per-row Status column
> (checkmark = approved, X = incorrect, ? = needs review) — the same status pattern used elsewhere
> in this course. This is **distinct from and complementary to** the existing "Show sequences"
> feature within Punctuation Inventory (field case 8): Unmatched Pairs of Punctuation catches
> single-character unmatched bracket/parenthesis-type pairs directly, while "Show sequences"
> catches multi-character punctuation sequences/combinations (e.g. multiple quotation marks
> combined with spacing or other punctuation), confirmed via a second screenshot. Both are real,
> useful checks the LTC should know about — this is an addition, not a replacement of either. The
> separate, more complex dedicated quotation-marks check (handling continuing quote marks across
> paragraph breaks) **remains genuinely out of scope**, confirmed still accurate — the existing
> "quotation marks deferred to a future addition" language is unchanged. See field case 11 (new)
> in "SME knowledge notes," the revised `04-formatting-and-references.md` module-breakdown row, and
> the menu-path correction throughout. **Flag for the Design Approver / module-author: this
> addition may push lesson 04 over its 80-minute estimate (10 minutes under the 90-minute cap) —
> see the module-breakdown row for a trim recommendation if so.** `module-author` may revise
> `04-formatting-and-references.md` accordingly.

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

> **Clarification, agreed by Doug Higby (course author) and Jenni Beadle (SME), 2026-09-08 — not
> a new open question, and no re-approval required.** This reinforces the resolution above rather
> than revisiting it. Doug and Jenni agreed on a clean split between the two components so they
> don't blur together: the five lessons' self-study material — including their Challenge
> sections — is deliberately **self-check/recall** work (write an answer from memory, then verify
> it against the lesson's own Content section), which builds and confirms `1 - Has Knowledge`
> only. The mentor-reviewed, evaluated Scenario Bank (`06-scenario-bank.md`) remains the **sole**
> component that earns the course's `3 - Independent` claim, per Kevin Nicholas's approval ruling
> above. Doug has already reworked the Challenge sections in all five lessons (01–05) to this
> self-check style (e.g. "write X from memory, then check it against the Content section above,"
> rather than "a team tells you X, what would you do") — this is implemented, not proposed.

## Scope

**In scope** — the pre-publishing **text checks**, matching workbook chapters 3–6 and 8 of
[Scripture Pre-publishing Checks](../scripture-pre-publishing-checks/README.md) ("Finalizing Your
Translation for Publication"):
- Parallel passages (ch. 3)
- Proper names and Biblical Terms, including the wordlist/spell-checking check area (ch. 4)
- Numbers and Measures — **anticipated to become one consolidated check** (covering numbers,
  weights, and measures together), currently in testing and expected to ship before this course
  publishes but not yet confirmed/released; until then, and for teams not yet on the newer
  Paratext version once it ships, the old **separate Numbers check** (limited scope, with no
  separate Measures check) remains the fallback case (see "Tool-version specifics" below) (ch. 5)
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
| 1 | Secondary* | Learner can recognize a "false-clean" check result — denied Basic Checks errors, a mass-approved (blanket "Correct") wordlist status, or other blanket-approved statuses — in any check area, and lead the team to reveal what was hidden, reset statuses, and re-run the check honestly. (Denying an error is a Basic Checks–specific mechanism, distinct from wordlist/spelling status, which has only Correct/Incorrect/Undecided — see "SME knowledge notes.") | Translation Tools 2.0, `2 - With Assistance` — "Assist in training others on the use of translation tools" | Quiz + Scenario Bank (spine scenario) |
| 2 | Core | Learner can confirm that a parallel-passage comparison check was actually run and its results reviewed by the team, flag passages the tool surfaces as inconsistent (by the tool's own comparison, not the learner's own linguistic judgment) back to the team for adjudication, and check that the team's own decisions about legitimate variation vs. over-harmonising — not the LTC's — are driving the resolution | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 3 | Secondary | Learner can recognize incomplete Biblical Terms coverage — blank renderings (the tool's default starting state) presented as a finished list — by checking the Found column/count rather than eyeballing the list, recognize stale/duplicate renderings left in place after a correct rendering was added without deleting the original, and coach the team to complete and clean up the list — without taking over their keyboard. Learner can also recognize a third pattern — a rendering that just repeats the verse's first word, likely caused by running **Guess Renderings** before the project had enough translated data for it to work reliably (per a support.bible programmer's reply, 2026-09-10; not something translators would consciously notice, since it's automated/background) — by scanning a term's occurrences with the down arrow to quickly step through verses, which makes the first-word pattern visually obvious. (This third pattern is now confirmed/attributed, not unconfirmed — see "SME knowledge notes," field case 4 revised 2026-09-10 — but remains secondary to the two primary patterns above.) | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 4 | Secondary | Learner can diagnose configuration-caused Send/Receive and performance slowdowns from over-adding terms to the *Project* Biblical Terms list, and advise the team on right-sizing it | Translation Tools 5.0 (Scripture Collaboration), `2 - With Assistance` — "Advise users in best-practices for collaboration and data safety... assist users to configure plans and tasks in a way that helps them" | Quiz + Scenario Bank |
| 5 | Core | Learner can confirm whether the team's Paratext version has the new consolidated Numbers check (covering numbers, weights, and measures) or still only the old separate Numbers check, run whichever is available against the team's *already-agreed and documented* approach (not the LTC's own judgment of what the rendering should be), and refer any gaps or contradictions the check surfaces back to the team to resolve rather than deciding new renderings | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 6 | Core | Learner can diagnose formatting-check failures — unclosed marker pairs, ghost markers, wrong markers, book-title/heading/reference errors — working structural-first, and coach a team to a zero-error result | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 7 | Core | Learner can diagnose over-linked glossary marking (every occurrence vs. first-per-section) and coach the team to unlink and relink at the correct scope | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 8 | Secondary | Learner can advise a team on a single- vs. two-column layout decision based on reader/community expectation (not just word length), and set up a hyphenation file so long words can break in a two-column layout | Digital and Print Publishing 1.0 (Print Publishing), `2 - With Assistance` — "Customize and use appropriate tools to produce publishable output for Scripture and dictionaries" | Quiz + Scenario Bank |
| 9 | Core | Learner can lead a team through the final PTXprint draft-PDF read-through (spreads, orphan words, footnote shifts, heading placement, underfilled pages) and resolve or triage what it surfaces, deferring true typesetting composition to the typesetter | Digital and Print Publishing 1.0, `2 - With Assistance` | Quiz + Scenario Bank |
| 10 | Core | Learner can decide, for any surfaced issue, whether it is theirs to resolve, the team's translation decision, or needs escalation (an LT mentor for tooling, a Translation Consultant for content) | Translation Tools 2.0, `2 - With Assistance` | Quiz + Scenario Bank |

\*\* **Objective 5 / Numbers-vs-Measures — resolved, updated 2026-09-11.** Objective 5 originally
covered the LTC's process/routing role across **two separate checks** (Numbers, released but
limited in scope; Measures, still under development and not yet reliably available), per Jenni's
2026-09-01 correction (see "Tool-version specifics" below). As of 2026-09-11 (Jenni Beadle and
Doug Higby), a new **consolidated Numbers check covering numbers, weights, and measures together**
is in testing and expected to ship before this course publishes, intended to replace the old
separate Numbers check (Measures was never released as its own check, so it is absorbed rather
than separately "replaced"). Objective 5 is worded to cover **either state** — the new consolidated
check if the team's Paratext version has it, or the old separate Numbers check if not — since the
underlying skill (confirm what's actually available before relying on it, then route gaps back to
the team) is unchanged. It is kept as a single objective here for the same reason it originally
was. **Ruled by Kevin Nicholas (Design Approver), 2026-08-31, PR #44: objective 5 stays a single
objective.** That ruling still holds under the 2026-09-11 update — this is a factual update to
reflect real tool development, not a scope change, and does not require re-ruling.

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

> **Clarification, Doug Higby and Jenni Beadle, 2026-09-08:** each of the five lessons below
> (01–05) ends in a Challenge section that is a **self-check/recall exercise** — write an answer
> from memory, then verify it against that lesson's own Content section — not an applied,
> scenario-style task. This keeps the lessons honestly at `1 - Has Knowledge` and leaves the
> applied, evaluated scenario work solely to the mentor-reviewed `06-scenario-bank.md` row below,
> so the two components don't blur together. Already implemented in lessons 01–05.

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-supporting-the-final-turn.md` | The translation process as a 6-stage spiral; Stage 6 as the final turn re-running earlier checks; the cross-cutting spine (false-clean results) introduced as a recurring watch-for, not a standalone topic; the consultant's role (diagnose, coach, never touch the keyboard); when to escalate | 1 (light touch), 10 | 35 |
| `02-wordlist-and-biblical-terms.md` | Biblical Terms list completion and cleanup (secondary, condensed): recognizing blank renderings as the tool's normal default starting state and confirming genuine completion via the Found column/count rather than eyeballing the list; recognizing stale/duplicate renderings left in place after a correct rendering was added without deleting the original; a third pattern — a rendering that just repeats the verse's first word, likely caused by running **Guess Renderings** before the project had enough translated data (per a support.bible programmer's reply, 2026-09-10 — see field case 4, revised) — noted as a real, attributed pattern to watch for (no longer unconfirmed/pending), spotted by scanning a term's occurrences with the **down arrow** to quickly step through verses so the first-word repetition becomes visually obvious; Project Biblical Terms bloat and performance (secondary, condensed); recurring false-clean callout for this check area, resting solely on the confirmed **blanket-approval pattern** — a wordlist mass-marked **Correct** — described using correct spelling-status terminology (**Correct / Incorrect / Undecided**), not "denied" (**reattributed 2026-09-08**: the "denied errors" field case belongs to Basic Checks in `04-formatting-and-references.md`, not here — see "SME knowledge notes," field case 1) | 1 (callout), 3, 4 | 40 |
| `03-parallel-passages-and-measures.md` | Parallel passages: confirming the comparison check was run and routing tool-flagged inconsistencies to the team (core, expanded); Numbers and Measures: confirming whether the team's Paratext version has the **new consolidated Numbers check (covering numbers, weights, and measures)** — in testing as of 2026-09-11, anticipated to be the primary case by the time this course publishes — or still only the **old separate Numbers check** (limited scope, no separate Measures check), which remains a real fallback scenario for teams not yet on the newer version; running whichever is available against the team's already-agreed, documented approach, and routing gaps back to the team (core, expanded); recurring false-clean callout | 1 (callout), 2, 5 | 60 |
| `04-formatting-and-references.md` | Formatting checks in structural-first order: marker-pair census, ghost markers, long/short verses, section headings, book titles, references, footnotes (core); subsection (confirmed by Kevin Nicholas on 2026-08-28): Punctuation Inventory (**Tools > Checking Inventories > Punctuation Inventory** — corrected 2026-09-11, was mis-stated as "Checks > Inventories") ahead of typesetting — reviewing/using the inventory itself (not PTXprint or the typesetter's own tooling), confirming the inventory was actually reviewed rather than assumed already handled earlier in the process (echoes the objective-1 false-clean theme), and common settings issues that turn this into a time-sink for a typesetter; **"Punctuation Sequences" addition folded into that Punctuation Inventory subsection (raised 2026-09-03; confirmed by Kevin Nicholas on 2026-09-03 via PR #46 comment — folded in, not a separate section): "Show sequences" in the Punctuation Inventory's Inventory menu, and its relationship to the "Punctuation (sequences)" Basic Checks option** — reviewing the inventory without "Show sequences" selected can silently miss unmatched punctuation-pair sequences (echoes the false-clean theme again); scoped to punctuation sequences only — quotation-mark-specific complexity is explicitly out of scope here and deferred to a future addition; **"Unmatched Pairs of Punctuation" addition (2026-09-11, Jenni Beadle, confirmed from live Paratext screenshots — field case 11)**: a separate, dedicated inventory under Tools > Checking Inventories that catches single unmatched bracket/parenthesis-type pairs directly (e.g. an unmatched "}", "[", "("), complementing rather than replacing "Show sequences" (which catches multi-character punctuation sequences/combinations instead) — both use the same approved/incorrect/needs-review Status pattern seen elsewhere in the course; quotation-mark-specific complexity remains out of scope for both, deferred to the same future addition; recurring false-clean callout — **incorporating field case 1** (**reattributed 2026-09-08**: a team denying Basic Checks errors they didn't understand, rather than resolving them) as the concrete example of the false-clean pattern in the Basic Checks context specifically, since Basic Checks is where "deny" is an actual, correct Paratext action (unlike wordlist/spelling status, which has no deny — see `02-wordlist-and-biblical-terms.md` and "SME knowledge notes") | 1 (callout), 6 | **85 — FLAGGED 2026-09-11: the "Unmatched Pairs of Punctuation" addition pushes this lesson from 80 to an estimated 85 minutes, only 5 under the 90-minute cap.** If drafting confirms it runs longer than 5 minutes of content, trim the reference/book-title portion first (as already flagged for the prior amendment); if that alone isn't enough, this lesson should be split rather than pushed over 90 minutes |
| `05-glossary-linking-and-layout.md` | Glossary-linking scope (over-linking, core); single- vs. two-column layout and hyphenation decisions (secondary, condensed); the PTXprint draft-PDF read-through (core); recurring false-clean callout | 1 (callout), 7, 8, 9 | 65 |
| `06-scenario-bank.md` | Mentor-reviewed applied scenarios weighted toward core objectives (2, 5, 6, 7, 9, 10 each get a full scenario), secondary objectives (3, 4, 8) folded into one combined scenario, and objective 1 (false-clean) run as a thread inside two of the core scenarios rather than its own case | 1–10 | 60 |
| `07-mentor-guide.md` | Facilitator notes: what to watch for in each scenario response, the "good" markers from the SME interview | — | — |
| `08-quiz.md` | Assessment | 1–10 | — |
| **Total learner seat time** | | | **345** (includes the 2026-09-11 "Unmatched Pairs of Punctuation" addition, +5 minutes, pending drafting confirmation it fits within the 90-minute cap for lesson 04 — was 340, which included the 2026-09-03 "Punctuation Sequences" addition confirmed by Kevin Nicholas as a fold-in, up from 335 as confirmed 2026-08-28) |

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

1. **(Reattributed 2026-09-08 — Stage 5 SME fact-check, Jenni Beadle. Mechanism corrected
   2026-09-13.)** A team **denied Basic Checks errors they didn't understand**, rather than
   resolving them. The consultant re-ran the check to surface the denied errors and explained the
   cause of each one. **This is a Basic Checks case specifically** — Basic Checks results have
   their own, genuine accept/deny mechanism. It is distinct from, and must not be conflated with,
   the wordlist/spelling status mechanism (field case 2 below), which has no "deny" action:
   Paratext's spelling status is one of only three states — **Correct, Incorrect, Undecided**.
   "Denied" belongs to Basic Checks (see `04-formatting-and-references.md`), not to the
   wordlist/Biblical Terms module. **Mechanism, confirmed via screenshot:** a denied error, when
   shown, displays with **strikethrough** text — it is not visually identical to a resolved one.
   The real risk is a **View menu** option, **Denied messages**: with it switched off, denied
   errors are hidden from the list entirely, so a short, clean-looking list may simply have its
   denied items out of view rather than genuinely resolved.
2. A wordlist of thousands of words was **all marked Correct** (blanket-approved). The consultant
   reset every entry to *Undecided* and re-ran the wordlist checks, especially for incorrectly
   split or joined words.
3. Glossary links were applied to **every occurrence** of a term rather than the first occurrence
   per section. The consultant unlinked and relinked at "first occurrence in every section," not
   "all."
4. **(Revised 2026-09-10 — support.bible replied; mechanism now a credible, attributed
   explanation, promoted from UNCONFIRMED.)** Jenni originally reported a team's Biblical Terms
   rendering where the first word of the verse appeared to have been auto-grabbed by Paratext as
   the term's rendering, first framed as the team not having *selected* the correct word/phrase
   before adding it. On the 2026-09-06 Stage 5 fact-check pass, Jenni noted she'd only ever seen
   this once, in one project, could not reproduce it, and it was never a reported Paratext error,
   so she submitted a question to support.bible. **A support.bible programmer replied on
   2026-09-10** with a plausible explanation: the team had likely run Paratext's **Guess
   Renderings** feature before the project had enough translated data for it to work reliably,
   which caused it to default to the verse's first word as a guessed rendering. Guess Renderings
   is a real, named Paratext feature — not a hypothetical — and importantly, this reframes the
   mechanism as an **automated/background action the translators likely would not have
   consciously noticed**, rather than a conscious slip like "forgot to select text." This
   explanation is credible enough to teach, attributed as **"likely cause per a support.bible
   programmer,"** not stated as certain fact. It is presented as a third pattern to watch for in
   Biblical Terms renderings, alongside — but secondary to — the two already-confirmed patterns
   (blank renderings, field case 9; stale/duplicate renderings, field case 10), since the original
   screenshot itself was inconclusive between the two explanations. **Confirmed coaching
   technique (Jenni, 2026-09-10):** to check for this, scan through a term's occurrences using the
   **down arrow** to quickly step through verses — it becomes visually obvious when a rendering is
   just the verse's first word, repeating a pattern that matches the verse text.
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
   (not just selected it for viewing). Per Paratext's own help documentation, "All Biblical
   Terms" lists every Greek, Hebrew, and Aramaic term occurring 500 times or less — potentially
   tens of thousands of entries depending on project scope (the SME observed one whole-Bible
   project with around 20,000 entries in this list) — versus "Major Biblical Terms," a curated
   subset of over 8,500 terms organized into semantic domains. The slowdown came specifically
   from adding the terms to the *project* — bloating the project and slowing Send/Receive — not
   from merely viewing a large list. It was **hard to convince the team of the actual cause**.
   The support skill here is diagnosing a configuration-caused performance problem and advising
   the team to right-size the project's Biblical Terms list.

7. **(Added 2026-08-28, Kevin Nicholas, post-approval comment on issue #40 — confirmed
   by Kevin Nicholas on 2026-08-28, PR #43.)** Kevin spent significant time,
   while working with a typesetter, going through **Paratext's Punctuation Inventory**
   (**Tools > Checking Inventories > Punctuation Inventory** — corrected 2026-09-11, was
   mis-stated as "Checks > Inventories") settings ahead of typesetting — this is Paratext's own inventory,
   not PTXprint or the typesetter's own tooling. He flagged it as a real time-sink worth
   covering. Jenni's placement decision: this belongs alongside the other formatting-check
   areas in `04-formatting-and-references.md`, as its own subsection rather than a passing
   scenario detail, partly framed as "confirm this was actually reviewed, don't just
   assume it's already handled" — echoing the false-clean theme from field cases 1–2 and
   objective 1.

8. **(Added 2026-09-03, Kevin Nicholas, raised during Stage 5 SME fact-check — confirmed
   by Jenni Beadle's team on 2026-09-03; placement confirmed by Kevin on 2026-09-03 — folded
   into the existing Punctuation Inventory subsection of lesson 04, not a separate section.)** Kevin
   thought his **Punctuation Inventory** review was thorough, but the typesetter later found
   a long list of **unmatched punctuation pairs** (mostly involving quotation marks) that
   hadn't surfaced. Traced back to not having selected **"Show sequences"** in the
   Punctuation Inventory's **Inventory menu** — without it, the **"Punctuation (sequences)"**
   checkbox under Run Basic Checks doesn't meaningfully check punctuation
   sequences/combinations, so the review looked complete but wasn't. This is scoped to
   **punctuation only** for now; quotation marks specifically are more complicated and are
   deferred to a **separate, future addition**, not this course.

9. **(Added 2026-09-06, Jenni Beadle, confirmed from live Paratext screenshots reviewed during
   Stage 5 SME fact-check — CONFIRMED, no pending confirmation needed.)** Paratext's Biblical
   Terms tool starts **every term** with an English gloss (or the localization's source language)
   and a **blank rendering**, shown highlighted (e.g. orange) with placeholder text like "Double
   click to enter rendering(s) from project text." The task itself is straightforward — work
   through the list and add the appropriate rendering for each term — and a blank rendering is
   **not a bug or a trap**, just the tool's normal starting state. But a team can still present a
   **partially-completed list as "done"** without genuinely having filled it in, so the relevant
   false-clean-adjacent check here is confirming genuine completion via the **Found column/count**
   on each row (e.g. "2/2" vs. an unfilled row), not eyeballing the list.
10. **(Added 2026-09-06, Jenni Beadle, confirmed from live Paratext screenshots reviewed during
    Stage 5 SME fact-check — CONFIRMED, no pending confirmation needed.)** A term can end up with a
    **long list of multiple candidate renderings**, most of which are not actually found in the
    current text — e.g. an old (possibly wrong) rendering left in place after a correct one was
    added later, without deleting the original. This clutters the renderings list and can make it
    hard to tell which rendering is actually the team's current, intended one. (This is the more
    likely explanation for the screenshot behind field case 4 above.)
11. **(Added 2026-09-11, Jenni Beadle, confirmed from live Paratext screenshots reviewed during
    Stage 5 SME fact-check — CONFIRMED, no pending confirmation needed.)** Paratext has a
    **separate, dedicated inventory called "Unmatched Pairs of Punctuation"** (also under Tools >
    Checking Inventories) — a standalone window listing single unmatched punctuation pairs (e.g.
    an unmatched "}", "[", "(") with a count and a per-row Status column (checkmark = approved, X
    = incorrect, ? = needs review), the same status pattern used elsewhere in this course. This is
    **distinct from and complementary to** field case 8's "Show sequences" feature inside the
    Punctuation Inventory: Unmatched Pairs of Punctuation catches single-character unmatched
    bracket/parenthesis-type pairs directly, while "Show sequences" catches multi-character
    punctuation sequences/combinations (e.g. multiple quotation marks combined with spacing or
    other punctuation like ")"), confirmed via a second screenshot showing rows with Unicode codes,
    counts, and the same Status column. Both are real, useful, complementary checks — this is an
    addition to, not a replacement of, the "Show sequences" content. The separate, more complex
    dedicated quotation-marks check (handling continuing quote marks across paragraph breaks)
    remains genuinely out of scope for this course, as already noted under field case 8.

**Cross-cutting theme across these cases:** *false-clean results* — a team clears a checklist
without genuinely checking. The consultant's job is to spot the fake all-clear, reset statuses,
and re-review — while the **translator does the actual fix** ("never touch their keyboard,"
matching the same rule taught in the team workbook).

### Common mistakes, organized by check area (Jenni's chosen axis — finalized 2026-08-24)

- **Wordlist / spelling** (its own area — a spiral, high-volume check across tens of thousands of
  words): blanket-approving the whole wordlist (mass-marking entries **Correct**) instead of
  genuinely reviewing it. (Spelling status has only three states — Correct, Incorrect,
  Undecided — there is no "deny" action here; see the reattribution note under Formatting &
  markup / Basic Checks below.)
- **Biblical Terms & renderings**: leaving blank renderings in place while presenting the list as
  done (confirm via the Found column/count, not eyeballing); letting stale/duplicate renderings
  pile up when a correct rendering is added but an old, possibly-wrong one isn't deleted; a
  rendering that is just the verse's first word repeated, **likely caused by running Guess
  Renderings before the project had enough translated data** (per a support.bible programmer's
  reply, 2026-09-10 — see field case 4, revised) — spotted by scanning a term's occurrences with
  the down arrow to quickly step through verses.
- **Parallel passages**: over-harmonising — forcing all parallel passages to match exactly and
  erasing legitimate variation. They must be consistent in *meaning*, not necessarily identical in
  *form*.
- **Numbers & Measures** (see "Tool-version specifics" below): a new **consolidated Numbers check
  covering numbers, weights, and measures together** is in testing (as of 2026-09-11) and expected
  to ship before this course publishes, replacing the old separate Numbers check (Measures was
  never released separately, so it is absorbed rather than replaced in its own right); until it
  ships — and for teams not yet on the newer version afterward — the old separate Numbers check
  (limited scope, no separate Measures check) remains the fallback. **Open** — this area is still
  new to the SME; no field case yet for either the old or new check. Do not invent one for scenario
  content; fill from experience if one surfaces before drafting.
- **Formatting & markup / Basic Checks**: wrong markers, unclosed footnote pairs (`\f…\f*`),
  wrong-case characters; **denying Basic Checks errors they didn't understand, rather than
  resolving them** (field case 1 — **reattributed 2026-09-08**, Jenni Beadle's Stage 5
  fact-check: this is a Basic Checks–specific mistake, not a wordlist/spelling one, since
  Basic Checks has a genuine deny mechanism that spelling status does not).
- **References & book names**: missed book-name checks, foreign `\r` abbreviations, inconsistent
  table of contents; unmatched single punctuation pairs (brackets/parentheses) and multi-character
  punctuation sequences going unnoticed when the two complementary Punctuation Inventory tools
  (**Unmatched Pairs of Punctuation**, field case 11; "Show sequences," field case 8) aren't both
  used.
- **Glossary linking**: over-linked (every occurrence, instead of first-per-section).
- **Layout & publishing (read-through)**: single- vs. two-column choice, hyphenation.
- **Cross-cutting spine**: don't trust an old or fake "all clear" — denied errors (Basic Checks'
  own accept/deny mechanism), skipped checks, or mass-approved/blanket statuses (e.g. a wordlist
  mass-marked Correct) can occur in *any* of the areas above, using whichever accept/reject
  mechanism that area actually has, and it's the pattern the consultant must watch for
  everywhere, not just once.

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
  Open Biblical Terms List dialog):** Numbers and Measures were, at that time, **two separate
  checks in Paratext, not a combined "Measures and Money and Numbers" list.** The **Numbers**
  check/list already existed and had been released, but its scope was fairly limited. A
  **Measures** check (money/weights) was still **under development and not yet released**. Both
  appeared as separate entries in Paratext's Open Biblical Terms List dialog alongside other
  unrelated lists (Major Biblical Terms, All Biblical Terms, NT Key Biblical Terms,
  Inclusive/Exclusive Pronouns, Younger/Older Siblings, etc.) — there was no single combined list
  to point learners at.
- **Update, 2026-09-11 (Jenni Beadle and Doug Higby):** since the above, Jenni and Doug have worked
  together to build a **new, consolidated Numbers check that covers numbers, weights, and measures
  together in one check**, intended to replace the old separate Numbers check (Measures itself was
  never released separately, so it is absorbed into the new check rather than "replaced" in its
  own right). It is **currently in testing** — Jenni has manually imported it under a working/test
  label for testing purposes only; that label is **not** its eventual real name and is not used
  anywhere in this design document. It is described here functionally, as **"the new consolidated
  Numbers check (covering numbers, weights, and measures)."** Jenni and Doug expect it to be
  released before this course publishes, but as of this writing it is **not yet confirmed/
  released**. Because of this, and because not every team will immediately be on a Paratext
  version that has it once it does ship, the underlying skill this course teaches — confirm what's
  actually available in a team's specific Paratext version before relying on it, and route gaps
  back to the team — remains essential. Teach the new consolidated check as the **anticipated
  primary case** by the time this course is in use, with the older split-check behavior (separate,
  limited-scope Numbers check; no separate Measures check) as a **real fallback scenario** for
  teams on older or unmigrated Paratext versions.
- **Resolved, 2026-09-10 (Jenni Beadle) — support.bible replied:** the reported first-word
  rendering behavior (open as a pending question since 2026-09-06) now has a credible, attributed
  explanation from a support.bible programmer: the team likely ran Paratext's **Guess Renderings**
  feature before the project had enough translated data for it to work reliably, defaulting the
  rendering to the verse's first word. Guess Renderings is a real, named Paratext feature. Treat
  this as **"likely cause per a support.bible programmer,"** not certain fact, and as a secondary
  pattern alongside the two already-confirmed findings (blank-rendering default state,
  stale/duplicate renderings — field cases 9–10); see field case 4 (revised 2026-09-10) for the
  confirmed coaching technique (scanning occurrences with the down arrow).

---

### Summary and handoff

**10 learning objectives** (6 Core: 2, 5, 6, 7, 9, 10; 4 Secondary: 1, 3, 4, 8 — see the priority
marker added after Jenni's 2026-08-27 review), spanning both Translation Tools and Digital and
Print Publishing, anchored to the `2 - With Assistance` ladder rows that reach `3 - Independent`.
Objectives 2 and 5 were reframed in this review from meaning-judgment to process/consistency-
checking and team-routing, since an LTC does not normally know the project language. **8 planned
files** (5 numbered content lessons + scenario bank + mentor guide + quiz), totaling **345 minutes**
(~5.75 hours) of learner-facing seat time (content lessons + mentor-reviewed scenario bank; mentor
guide and quiz excluded from the total per convention), now weighted toward the core objectives.
The 345-minute total includes the Punctuation Inventory subsection added to
`04-formatting-and-references.md` on 2026-08-28 and **confirmed by Kevin Nicholas on 2026-08-28**
(see the amendment note at the top of this document; previously 320 minutes as approved
2026-08-27), the "Punctuation Sequences" addition of 2026-09-03, **confirmed by Kevin Nicholas on
2026-09-03** as a fold-in to the existing Punctuation Inventory subsection (brought
`04-formatting-and-references.md` to 80 minutes), and the "Unmatched Pairs of Punctuation" addition
of 2026-09-11 (Jenni Beadle, field-confirmed), which brings `04-formatting-and-references.md` to an
estimated **85 minutes — only 5 minutes under the 90-minute cap. Flagged for module-author:** trim
the reference/book-title portion first if drafting runs longer than estimated, or split the lesson
rather than exceed 90 minutes.
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

**Amendment confirmed by Kevin Nicholas on 2026-09-03 (comment on PR #46; raised during Stage 5
SME fact-check, 2026-09-03):** the "Show sequences" material (Punctuation Inventory's Inventory
menu) and its relationship to the "Punctuation (sequences)" Basic Checks option is confirmed
correctly scoped (punctuation sequences only; quotation marks deferred to a future addition).
Kevin's ruling on placement: **fold it into the existing Punctuation Inventory subsection of
`04-formatting-and-references.md` as a "Punctuation Sequences" addition, not a separate section**
(field case 8, module-breakdown row, and revised 340-minute total). The lighter treatment puts
the lesson at 80 minutes, 10 under the cap. `module-author` may revise
`04-formatting-and-references.md`.

**Amendment, Stage 5 SME fact-check pass, 2026-09-06 (Jenni Beadle):** Objective 3 and its
underlying field case (the "no-selection rendering error") are revised — the auto-grab mechanism
is now marked **unconfirmed, pending a reply from support.bible** (Jenni saw it once,
unreproducible since, never a reported Paratext error), rather than removed outright. Objective 3
now primarily teaches two **confirmed** patterns from live Paratext screenshots reviewed the same
day: blank renderings as the tool's default starting state (confirmed via the Found column/count)
and stale/duplicate renderings piling up when an old rendering isn't deleted after a correct one
is added (field cases 9–10). The `02-wordlist-and-biblical-terms.md` module-breakdown row is
revised accordingly; its estimated time (40 minutes) is unchanged. These two new findings do not
need to wait on support.bible; only the auto-grab mechanism itself remains pending, and this
document will be amended again once that reply arrives. `module-author` may draft or revise
`02-wordlist-and-biblical-terms.md` on this basis.

**Amendment, Stage 5 SME fact-check pass, 2026-09-10 (Jenni Beadle) — support.bible query
resolved:** the reply arrived. A support.bible programmer's plausible explanation — the team
likely ran **Guess Renderings** before the project had enough translated data, causing it to
default to the verse's first word — promotes the field case 4 mechanism from unconfirmed/pending
to a credible, attributed third pattern (attributed as "likely cause per a support.bible
programmer," not certain fact), taught alongside but secondary to the two already-confirmed
patterns (blank renderings, stale/duplicate renderings). Jenni also confirmed a coaching
technique: scanning a term's occurrences with the down arrow to quickly step through verses,
which makes the first-word repetition visually obvious. Field case 4, objective 3, the
`02-wordlist-and-biblical-terms.md` module-breakdown row, the "Common mistakes" Biblical Terms
bullet, and the "Tool-version specifics" section are all revised accordingly; estimated time for
`02-wordlist-and-biblical-terms.md` (40 minutes) is unchanged. `module-author` may draft or revise
`02-wordlist-and-biblical-terms.md` on this basis.

**Amendment, 2026-09-11 (Jenni Beadle and Doug Higby) — Numbers/Measures tool development, no
re-approval needed:** a new, consolidated Numbers check that covers numbers, weights, and measures
together in one check is in testing and expected to ship before this course publishes, replacing
the old separate Numbers check (Measures was never released as its own check, so it is absorbed
rather than separately replaced). It is described functionally throughout this document — not by
the working/test label Jenni used for manual testing import, which is not its eventual real name.
Objective 5's wording, the "Scope" section, the `03-parallel-passages-and-measures.md`
module-breakdown row, the "Common mistakes" Numbers & Measures bullet, and the "Tool-version
specifics" section are all revised to teach the new consolidated check as the anticipated primary
case, with the old split-check behavior retained as a real fallback for teams not yet on the newer
Paratext version. This is a factual update reflecting real-world tool development, not a scope or
objective-count change; module and time estimates are unchanged. `module-author` may draft or
revise `03-parallel-passages-and-measures.md` on this basis.

**Amendment, Stage 5 SME fact-check pass, 2026-09-11 (Jenni Beadle) — menu-path correction and a
second, complementary field-confirmed tool, no re-approval needed:** the Punctuation Inventory menu
path is corrected throughout this document to **Tools > Checking Inventories > Punctuation
Inventory** (was wrongly stated as "Checks > Inventories"). A second, dedicated, field-confirmed
tool — **"Unmatched Pairs of Punctuation"** — is added as field case 11, complementing (not
replacing) the existing "Show sequences" content (field case 8): it catches single-character
unmatched bracket/parenthesis-type pairs, while "Show sequences" catches multi-character
punctuation sequences. The dedicated quotation-marks-across-paragraphs check remains out of scope,
confirmed still accurate. **This addition pushes `04-formatting-and-references.md` from 80 to an
estimated 85 minutes (5 minutes under the 90-minute cap) and the course total from 340 to 345
minutes — flagged for the Design Approver and `module-author`; trim the reference/book-title
portion first if drafting runs longer than estimated, or split the lesson rather than exceed 90
minutes.** `module-author` may revise `04-formatting-and-references.md` on this basis.
