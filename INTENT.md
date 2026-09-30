# Intent: the LTC curriculum repository

**Author:** Doug Higby (maintainer) · **Status:** current — drafted with Claude and corrected by Doug, 2026-09-17. Lines marked _(assumption)_ are still unconfirmed.

> **What this file is.** The _why_ behind this repo — the problem it exists to solve, who it serves, what constrains it, and what is still undecided. `README.md` says what's here and `CLAUDE.md` says how to work here; this says why both are shaped the way they are. When a rule doesn't cover the case in front of you — a new feature, a script, a process change — decide from this file rather than guessing. If a decision contradicts something here, the contradiction is the thing to raise, not to route around. This is intent for **the repository and its tooling**, not for any one course; a course's own intent lives in its `00-design.md`.

## Problem

We are building training for language technology consultants, and we need it somewhere people can take it from anywhere in the world, working alongside a mentor who helps them succeed. Several things were blocking that:

- **Everyone built courses differently.** Each person creating content worked in their own way — different structure, different depth, different notion of what "finished" meant. Nothing made two courses resemble each other, and nothing made a course reviewable by someone who hadn't written it.
- **Everyone was reaching for AI, and nobody was getting consistent results from it.** The problem was never access to AI. It was that each author improvised their own way of using it, so output quality swung wildly from person to person and course to course. Nothing captured how to use it well for _this_ kind of content, repeatably.
- **Coverage was invisible.** The CBC framework defines 42 competencies an LTC is certified against. Nothing could answer "which of those do we actually train?" — so gaps surfaced anecdotally rather than systematically, and there was no basis for deciding what to build next. (At migration: 21 covered, 22 uncovered, with the entire Education category empty.)
- **There was no delivery platform we could live with.** Cypher for Business was the only option, and it charges per learner: every person taking a course must either pay to enroll or have us pay on their behalf. For a consultant body spread worldwide, that is not workable. _(Resolved 2026-09-22 — see Moodle under **Affected users and systems**.)_
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
- **Moodle (self-hosted)** — the delivery platform, decided 2026-09-22. Courses are published there from this repo by `/publish-to-moodle`, one-way: the repo stays the source of truth and anything edited in Moodle is overwritten by the next publish. It costs nothing per learner, which is the constraint that ruled out Cypher, and its Android app lets a consultant take a course offline in the field. A temporary instance comes first, then a host that can serve many learners at once; the `idnumber` scheme in [`moodle/local_ltuse/`](moodle/local_ltuse/README.md) is what makes that migration a re-publish rather than a data move.
- **Cypher for Business** — where the ~23 legacy courses were delivered, and now winding down. Nothing new is published there. Those courses keep their `cypher:` link and stay `Online`; bringing their content home is the [backfill](process/backfill.md) workstream.
- **Claude Code** — the working environment: the commands, session hooks and seven agents.
- **Notion** — retired as of 2026-06-18. Not a live dependency.

## Constraints

Hard, in roughly descending order of "breaking this breaks the point of the repo":

- **Delivery must not cost per learner.** Any platform that requires each person to be enrolled at a price, or paid for individually, is unworkable for a worldwide consultant body. This is the specific reason Cypher for Business cannot be the long-term answer.
- **Content must stay portable.** Moodle is the delivery platform now, but nothing may bind course content to its format: the markdown in this repo is the asset, and any renderer or LMS is replaceable. Prefer the reversible choice. In practice this is what the publisher's split at a platform-neutral payload is for — `moodle_payload.py` knows courses and nothing about Moodle's API; `moodle_client.py`, `moodle_xml.py` and the plugin know Moodle and nothing about pedagogy. Replacing the platform means replacing the lower half only.
- **The CBC framework is not ours to change.** The 42 competencies and the five outcome levels are external givens. Competency names are matched verbatim; a mismatch is a silent coverage miss, which is why it's a hard CI failure rather than a warning.
- **CBC vocabulary only.** The legacy level names, and the off-by-one level offset they came with, caused real confusion once. They stay retired.
- **Published competency URLs are citations, not implementation detail.** The CBC program points people at `/<category>/<competency>/` on the published site, so a page that moves breaks a reference someone else is relying on. That URL is built from the category key in `competencies.yaml` and the descriptor's filename — so renaming a file, moving a competency between categories, or renaming a category key all move pages, and the last moves every page beneath it at once. Moving a page is allowed; moving one silently is not. Record the old path in `redirect_maps` in `mkdocs.yml` in the same change.
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
- **Not an application** — with one named exception. There is no runtime here, no users-at-scale, no uptime obligation; the scripts serve authors and CI, and that is the whole job. The exception is [`moodle/local_ltuse/`](moodle/local_ltuse/README.md), a small PHP plugin that runs on the Moodle server, added 2026-09-22. It is here rather than in its own repo because it and the publisher are two halves of one contract — a change to the page shape or the quiz model touches both, and one pull request should carry both. It is deliberately thin: four functions wrapping APIs the Moodle web UI already calls, existing only because Moodle has no core web service that writes a quiz. Anything larger than that does not belong in this repo.
- **Not a place to re-derive stage state by hand.** `scripts/course_stage.py` is the single implementation; a second one is a bug, not a feature.

Note what is deliberately **not** on this list. Whether training is eventually _delivered_ from this repo is an open question, not a closed one — see below. And the published competency site is meant to be the go-to public reference for what an LTC competency is and where to go learn it, so "that's documentation rather than curriculum" is not a reason to leave something off it.

## Open questions

- ~~**Which delivery platform, and when?**~~ **Resolved 2026-09-22: self-hosted Moodle.** It meets the per-learner-cost constraint, and its Android app reaches consultants working offline. The portability constraint above still binds — this is a decision about where courses are delivered, not about what they are written in. Stage 7 pilots and stage 8 publishing both go to Moodle; the reviewer site keeps serving stages 5–6, so reviewers need no Moodle account.
- **When does the `/learn/` view retire?** It stays through the transition as a fallback and as the reference implementation of the disclosure boundary. Retire it once a pilot has run cleanly on Moodle end to end — not before, because it is the thing to fall back to if the Moodle publish turns out to have a gap.
- **Does the Cypher wind-down make backfill urgent?** Yes, and more so now there is somewhere to put the content. **16 of the 31 courses have no lesson files at all** — their whole body is a single run-on README paragraph imported from Notion, with hotlinked Google images that will rot. `publish_moodle.py` refuses them by design rather than shipping that under a real course name. Until they are backfilled they cannot move to Moodle, and when Cypher ends they have no home.
- **Who operates the Moodle server?** The first instance is temporary, on Doug's hardware, for building against. The production host, who administers it, and what the backup story is are not yet decided. Nothing in the repo depends on the answer — the `idnumber` scheme means a move is a re-publish — but somebody has to own it.
- **How do ten contributors and "one course at a time" coexist?** The queue rule was written for a much smaller working group. With department staff and Seed Company collaborators both active, it may need to become one course per person or per pair rather than one course repo-wide.
- **How do we rank the remaining 22 gap competencies?** `coverage-strategist` recommends a next course, but the ranking principle — learner demand, certification bottleneck, ease of authoring — isn't written down anywhere.
- **What does Seed Company collaboration look like mechanically?** Reviewing is straightforward; authoring from an outside organisation raises questions about repo access, review independence, and who approves their designs.

## What delay costs

There is no external deadline. But every month without published courses makes it harder to train consultants who are already in the field and already being assessed against these competencies — the CBC program points them at a competency site whose gaps are plainly visible. The pressure is real even though the date isn't.
