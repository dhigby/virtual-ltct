# Intent: the LTC curriculum repository

**Author:** Doug Higby (maintainer) · **Status:** current — drafted with Claude and corrected by Doug, 2026-09-17. Lines marked _(assumption)_ are still unconfirmed.

> **What this file is.** The _why_ behind this repo — the problem it exists to solve, who it serves, what constrains it, and what is still undecided. `README.md` says what's here and `CLAUDE.md` says how to work here; this says why both are shaped the way they are. When a rule doesn't cover the case in front of you — a new feature, a script, a process change — decide from this file rather than guessing. If a decision contradicts something here, the contradiction is the thing to raise, not to route around. This is intent for **the repository and its tooling**, not for any one course; a course's own intent lives in its `00-design.md`.

## Problem

We are building training for language technology consultants, and we need it somewhere people can take it from anywhere in the world, working alongside a mentor who helps them succeed. Several things were blocking that:

- **Everyone built courses differently.** Each person creating content worked in their own way — different structure, different depth, different notion of what "finished" meant. Nothing made two courses resemble each other, and nothing made a course reviewable by someone who hadn't written it.
- **Everyone was reaching for AI, and nobody was getting consistent results from it.** The problem was never access to AI. It was that each author improvised their own way of using it, so output quality swung wildly from person to person and course to course. Nothing captured how to use it well for _this_ kind of content, repeatably.
- **Coverage was invisible.** The CBC framework defines 42 competencies an LTC is certified against. Nothing could answer "which of those do we actually train?" — so gaps surfaced anecdotally rather than systematically, and there was no basis for deciding what to build next. (At migration: 21 covered, 22 uncovered, with the entire Education category empty.)
- **There is no delivery platform we can live with.** Cypher for Business is the only option we have today, and it charges per learner: every person taking a course must either pay to enroll or have us pay on their behalf. For a consultant body spread worldwide, that is not workable.
- **Content locked in an LMS or a PDF can't be improved.** It can't be diffed, reviewed or incrementally corrected. Fixing a factual error meant finding whoever owned the file.
- **The people best placed to write courses are not software people.** The subject-matter expertise lives with practising consultants. Any process that demands fluency in git, branches or pull requests excludes exactly the people it needs.

## Proposed outcome

A single repository that is the **source of truth** for the curriculum, where:

1. **Every course has the same shape.** One package, one set of stages, one definition of done — so quality comes from the process rather than from who happened to write it, and anyone on the team can pick up anyone else's course.
2. **AI-assisted authoring produces consistent results.** The agents, the stage how-tos and the `training-content` skill exist to make good AI output repeatable instead of personal. This repo is the harness that turns "everyone is using AI somehow" into "everyone gets the same standard of draft."
3. **Coverage is a fact, not a claim — and it drives prioritisation.** Every course declares its competencies in frontmatter; `COVERAGE.md` is generated from that, never asserted by hand. It is what tells us which competencies remain gaps and therefore what to build next, until there is a course for every competency in `competencies.yaml`.
4. **The published competency site is the reference for the CBC program.** Students being evaluated on a competency are pointed here from the CBC modules themselves, to see what the competency means, what moving up a level takes, and where they can go to learn it. This is a primary purpose of the site, not a by-product of authoring.
5. **Content stays portable.** Courses are plain markdown that can be published anywhere. Whatever platform we end up delivering on, the content does not have to be rewritten to move.
6. **Learners are supported by a mentor, not left alone with a video.** The mentor guide and scenario bank are part of every package for that reason.
7. **A non-technical contributor can do the whole job.** Claude Code drives the pipeline; `/work-on` handles branches so no contributor types a git command or wonders where their files went. Anything that pushes git mechanics onto a contributor is a defect.
8. **Legacy content comes home.** Courses delivered in Cypher are backfilled into the repo so that the repo — not the LMS — is where content lives and improves.

## Affected users and systems

**People** — around ten in total, across our department and one partner organisation. Roles are hats, not headcount:

| Who | What they need from this repo |
| --- | --- |
| **Department staff (language technology use)** | To author, revise and test courses in a range of roles, without learning git. Practising consultants, not developers. |
| **Seed Company (external partner)** | To review existing courses, and possibly to author their own content here. An outside organisation working in the same repo. |
| **Design approver, internal reviewer, pilot coordinator, publisher** | An unambiguous "it's your turn, here's what to check." |
| **Mentors** | Material that supports guiding a learner, not just presenting content. |
| **Pilot learners** | To read a course as a readable page — without the answer key. |
| **CBC students** | A public competency site they are pointed to from the CBC modules, showing what each competency means and where to go learn it. |
| **Language technology consultants worldwide** | Courses that move them up the CBC ladder — whether their work advances Bible translation or linguistics and literacy in minority languages. The reason the whole thing exists. |
| **Maintainer (currently Doug)** | Board admin, merge rights, and tooling that doesn't need babysitting. |

**Systems:**

- **This GitHub repo** — source of truth for course content and competency descriptors.
- **The "LTC Training Modules" Project board** — source of truth for _workflow state_ only. The split is deliberate: content facts in frontmatter, live state on the board, so the two cannot drift into disagreeing.
- **GitHub Pages** — the competency site (`competencies.languagetechnology.org`) plus the per-course reviewer and learner views.
- **Cypher for Business** — where courses are delivered _today_, and a stopgap only. It is per-learner paid, which we cannot sustain, and a change of direction is expected within about a month (as of 2026-09-17). Treat it as short-lived: do not build anything that assumes it.
- **Claude Code** — the working environment: the commands, session hooks and seven agents.
- **Notion** — retired as of 2026-06-18. Not a live dependency.

## Constraints

Hard, in roughly descending order of "breaking this breaks the point of the repo":

- **Delivery must not cost per learner.** Any platform that requires each person to be enrolled at a price, or paid for individually, is unworkable for a worldwide consultant body. This is the specific reason Cypher for Business cannot be the long-term answer.
- **Content must stay portable.** The delivery platform is genuinely undecided and expected to change soon. Nothing may bind course content to one platform's format: the markdown in this repo is the asset, and any renderer or LMS is replaceable. Prefer the reversible choice.
- **The CBC framework is not ours to change.** The 42 competencies and the five outcome levels are external givens. Competency names are matched verbatim; a mismatch is a silent coverage miss, which is why it's a hard CI failure rather than a warning.
- **CBC vocabulary only.** The legacy level names, and the off-by-one level offset they came with, caused real confusion once. They stay retired.
- **No step may require git knowledge.** This constrains every feature: if a change means a contributor must understand branches, rebases or merge conflicts, the change is wrong even when it is technically better.
- **Standardisation is the point — resist per-course special cases.** A course that needs its own bespoke structure, or its own exception to the process, erodes the very thing this repo exists to provide.
- **Generated artefacts are never hand-edited** (`COVERAGE.md`, the published site). Hand-editing a generated file makes the generator a liar.
- **Review must be independent.** A course needs at least two humans; the approver and internal reviewer cannot be the author.
- **One course at a time.** An ordered queue, not parallel commitments — half-finished courses help nobody. _(assumption: worth revisiting now that up to ten people may be contributing.)_
- **The repo is public.** No credentials, no learner PII. The review sites are _unlisted_, which is not the same as secret, and must never be described as secret.
- **The learner view is a disclosure boundary and fails closed.** A quiz whose answer key can't be cleanly separated is withheld entirely, not partially stripped. Never optimise this into "strip what we can."
- **Content is markdown a human can read raw.** Tooling may render it; nothing may make the source unreadable or require a tool to edit it.
- **No large binaries in git.** Video lives in Vimeo or Drive, linked from frontmatter.

## Out of scope

- **Not a record of certification.** Who has reached which CBC level is assessed and tracked by the CBC program, not here. This repo supplies the training and the competency reference; it does not hold learner records.
- **Not an application.** There is no runtime, no users-at-scale, no uptime obligation. The scripts serve authors and CI, and that is the whole job.
- **Not a place to re-derive stage state by hand.** `scripts/course_stage.py` is the single implementation; a second one is a bug, not a feature.

Note what is deliberately **not** on this list. Whether training is eventually _delivered_ from this repo is an open question, not a closed one — see below. And the published competency site is meant to be the go-to public reference for what an LTC competency is and where to go learn it, so "that's documentation rather than curriculum" is not a reason to leave something off it.

## Open questions

- **Which delivery platform, and when?** Undecided. Cypher for Business is a per-learner-paid stopgap, and a change of direction is expected within about a month of 2026-09-17. The live options are hosting our own content, building something together with the Seed Company, or — less likely, but not ruled out — delivering from this repo. Until this lands, **prefer decisions that keep content portable and platform-agnostic**; that is the working rule this question implies.
- **Does anything ultimately get delivered from here?** The learner view will probably stay a pilot aid, but delivering training from this repo is possible and has not been excluded. Don't build toward it; don't foreclose it either.
- **Does the Cypher wind-down make backfill urgent?** If that dependency ends within a month, the courses most at risk are the ones that exist only as Cypher-delivered content — which would move backfill from a background workstream to a priority.
- **How do ten contributors and "one course at a time" coexist?** The queue rule was written for a much smaller working group. With department staff and Seed Company collaborators both active, it may need to become one course per person or per pair rather than one course repo-wide.
- **How do we rank the remaining 22 gap competencies?** `coverage-strategist` recommends a next course, but the ranking principle — learner demand, certification bottleneck, ease of authoring — isn't written down anywhere.
- **What does Seed Company collaboration look like mechanically?** Reviewing is straightforward; authoring from an outside organisation raises questions about repo access, review independence, and who approves their designs.

## What delay costs

There is no external deadline. But every month without published courses makes it harder to train consultants who are already in the field and already being assessed against these competencies — the CBC program points them at a competency site whose gaps are plainly visible. The pressure is real even though the date isn't.
