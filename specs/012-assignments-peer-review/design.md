# Design: How assignments are authored, disclosed and published

> **Parked for re-plan (Doug, 2026-10-05).** Open courses (spec 002, 2026-10-02) and spec 008
> plan decision 3's mentor groups change this spec's basis; Phases B and C, and the design
> approval (tasks T014), wait on the re-plan. Do not fill in the decision record below against
> this text. D6 (peer review) rests on organisation and cohort groups that shared courses no
> longer have, and is marked in place; D1-D5, D7 and D8 do not depend on groups. See
> [spec.md, Re-plan inputs](spec.md#re-plan-inputs).

**For**: the maintainer's decision under spec 012 User Story 1 and constitution X ("a change to the course content model MUST have an approved design before any build").
**Status**: ~~**Awaiting decision.**~~ **Parked for re-plan** (Doug, 2026-10-05); the decision waits on the re-plan. Until the decision record below is filled in, no `*-assignment.md` may be committed (CI enforces this, [research R8](research.md#r8-where-the-design-gate-is-enforced-fr-001-sc-001)), and nothing in Phase B or C of [plan.md](plan.md) is built.

This document answers FR-001 to FR-008a. Moodle facts are cited by research number ([research.md](research.md)).

---

## D1. Where mentor-only material is written (FR-002, FR-003)

An assignment has four kinds of material: the **brief**, the **criteria** a learner works to, the mentor's **grading notes** for each criterion, and an optional **model answer**. The last two must never reach a learner. Three ways to hold them:

| | **A. One file, marked sections** *(recommended)* | B. Two files, brief + mentor notes | C. Grading notes in the existing mentor guide |
|---|---|---|---|
| Shape | `NN-<topic>-assignment.md`, with mentor-only material under `## Mentor only` H2 headings | `NN-<topic>-assignment.md` (learner) + `NN-<topic>-assignment-mentor-notes.md` (excluded by filename) | the brief in an assignment file, the grading notes and model answer in `NN-mentor-guide.md` |
| Disclosure safety | Strong. It reuses the proven quiz pattern: one canonical marker, a strip that re-reads its own output, the file withheld whole on doubt, and a positive check that every mentor-only line is absent. Its one risk is an author writing notes outside the marker. That is caught because `## Grading notes` and `## Model answer` headings outside the marker count as residue (D3). | Strongest at the file level, because exclusion by filename is the simplest rule there is. But the two files can drift apart, and a renamed file loses its exclusion silently. | Strong, because the mentor guide is already excluded. But it cannot feed the per-criterion marker field (R1): the publisher would have to read a free-form guide and find the notes for one criterion. |
| Raw readability | Best. A reviewer sees each criterion with its notes in one file, which is how SMEs check that the criteria match the notes. | Fair. Two files have to be read side by side. | Poor. The notes for a criterion sit in another file, among setup instructions. |
| Contributor simplicity | One file to write, and one rule ("mentor material goes under `## Mentor only`") that matches the `## Answer key` rule authors already know. | Two files to name exactly. A wrong suffix is a leak. | Two files, and the mentor guide grows a structure it never had. |
| Publisher impact | One parser (`assignment_parse.py`) and one more marker in `disclosure.py`. | One parser reading two files, and a new excluded suffix. | A parser for part of the mentor guide, which is the hardest of the three to make strict. |

**Recommendation: A.**

**Rejected**:
- **B** trades one marker for one filename suffix. It adds a pairing that can break, and it is no safer: a mis-suffixed file leaks exactly as a mis-marked section would, but nothing checks the suffix positively.
- **C** blocks the per-criterion marker field, which is the main reason the marking guide was chosen.

### What is learner-visible and what is mentor-only (FR-003)

| Part | Who sees it | Where it lands in Moodle |
|---|---|---|
| Title, `**Estimated time:**`, brief, "what to submit" | learner, mentor, peers | assignment Description (R1) |
| Each criterion's name, points and learner wording | learner, mentor, peers | the Description, plus the marking guide's *description for students* (R1), or the workshop's comment aspects (R4) |
| Each criterion's grading notes | **mentor only** | the marking guide's *description for markers* (R1); for peer review, the hidden mentor-notes page |
| Model answer | **mentor only** | the hidden mentor-notes page (R1) |

**Default**: anything under `## Mentor only` is mentor-only. Nothing else in the file is. There is no third category.

## D2. The file (FR-002, FR-008a)

The full grammar is in [contracts/assignment-file.md](contracts/assignment-file.md). In short:

```markdown
# Assignment: Diagnose a font fallback report
**Estimated time:** 45 minutes
**Completion:** required          ← optional | required. Default: optional
**Review:** mentor                ← mentor | peer. Default: mentor
**Submit:** text, file (up to 5 MB, .png .jpg .pdf)
**Offline:** yes                  ← yes | no. "no" prints "submit this online" in the brief

## Brief
…
## Criteria
### Identifies the failing component (4 points)
Learner wording…
### Proposes a next step for the team (2 points)
…
## Mentor only: grading notes
### Identifies the failing component
…
## Mentor only: model answer
…
```

- **Default completion is `optional`** (FR-008a). An undeclared assignment can never block a learner's course. The package check warns when `**Completion:**` is missing, so it is a choice and not an accident.
- Header lines follow the existing `**Estimated time:**` convention rather than frontmatter. Lesson files carry no frontmatter today, and the convention is already parsed and checked.
- One assignment per file. The suffix `-assignment.md` is **last** in the name, for the same reason `-video-script.md` is (CLAUDE.md, convention 6).
- A peer-review file is written the same way. Its grading notes still sit under `## Mentor only`, but a workshop has no marker-only field (R4), so they are published to the hidden mentor-notes page with the model answer, never into the peers' assessment form.

## D3. Disclosure (FR-004, FR-016)

- The marker joins the **existing** definition in `scripts/disclosure.py`. There is no second module and no second copy:
  - `MENTOR_ONLY_RE = ^## Mentor only\b.*$` (optionally qualified, repeatable). Its block runs to the next H1 or H2, exactly like `## Answer key`.
  - `strip_restricted(md)` removes both marker kinds and returns `(text, ok)`. It sets `ok = False` if anything restricted-shaped survives: an answer-key-shaped line, or a heading reading *grading notes*, *model answer*, *marking notes* or *mentor notes* outside a mentor-only block.
  - `restricted_blocks(md)` returns every block of either kind, for the positive checks.
  - `strip_answer_keys()` stays, as the quiz-only name every current caller uses.
- **Every learner page is stripped of both marker kinds**, not only assignment files. A lesson that grows a mentor-only aside is then safe too, at no cost.
- **Fails closed**: an assignment whose strip returns `ok = False`, or that fails to parse, is **withheld whole**. The learner sees a "this assignment could not be published, ask your mentor" placeholder, and the publisher refuses the publish unless `--allow-withheld` is passed. That flag is the same one quizzes already use. It never lets mentor-only text through; it only allows publishing a course with a hole in it.
- **Checked positively, before anything leaves the machine** (`check_moodle_payload.py`, no `--force`):
  - Every distinctive line of every mentor-only block must be absent from **every learner-visible field**: page HTML, and the assignment's and workshop's intro, criteria and aspect text in the manifest.
  - Mentor-only text may appear **only** in the guide's marker descriptions and in mentor-notes pages.
  - Every mentor-notes page must be `visible: 0`.
  - A deliberately planted leak fails the check, which is SC-002's test.
- The learner view of the review site (`/learn/`) uses the same strip, and `check_learner_view.py` asserts the same absence.

## D4. Production stages (FR-005)

An assignment is **optional per course** (spec Assumptions). It adds no required stage, and `course_stage.py` gains no new gate. It does count as a lesson-like file:

| Stage | What happens to an assignment |
|---|---|
| 1–2 Design | The design doc may list an assignment against an objective. If it does, that is the commitment. |
| 3 Draft | Written at 3c, beside the quiz, by the `quiz-writer` agent, whose instructions gain an assignment section: it already turns objectives into assessment. The `training-content` skill's language-data rule applies (FR-014). |
| 4 Alignment | `alignment-reviewer` checks that every criterion traces to a design objective, that no criterion asks anyone to judge minority-language text, that `**Estimated time:**` is within 90 minutes, and that the mentor-only blocks are where D2 says. `check_course_package.py` checks the format. |
| 5 SME fact-check, 6 internal review | Read on the **reviewer** site, mentor-only sections included. |
| 7 Pilot, 8 Publish | Published by `/publish-to-moodle`, like any other part of the package. |

`course_stage.is_lesson()` excludes `-assignment.md`, and `check_course_package.py` stops carrying its own copy of `is_lesson()` (it imports it, as constitution I requires for stage logic).

## D5. Identity in Moodle (FR-006)

Following the existing scheme (R7):

- `ltct:<slug>:<file>` for the assign or workshop
- `ltct:<slug>:<file>:mentor-notes` for its hidden page
- `ltct:<slug>:discussion` for the course forum

Guide criteria keep their ids across republishes by matching on shortname. The identity is stored in Moodle, never in a repo state file.

## D6. Peer review (FR-011 to FR-013)

- `**Review:** peer` publishes a `mod_workshop` instead of an assign:
  - the comments strategy, one aspect per learner-visible criterion
  - separate groups
  - `**Peers:** N` (default 2)
  - instructions to authors that include the FR-012a reminder not to put their name in the work
- Allocation is the `workshopallocation_orgcohort` subplugin (R4): cohort first, then the same organisation, never across.
- If fewer than two learners in an organisation submit, it allocates no peers. The mentor assesses through the workshop's own "Assess" (FR-013).
- *(2026-10-05: the two points above key allocation to organisation and cohort groups, which shared courses have not had since 2026-10-02 (spec 002). "Separate groups" would now mean 008's mentor groups (`ltct:mentorgroup:<mentor id>`). The re-plan needs the cross-organisation rule spec 002 left to 012 and Doug. Re-plan inputs 1 and 4.)*
- **Limit stated plainly**: a workshop runs in phases for everyone in it, and its grades reach completion only when the mentor closes it (R4). So a **required** peer-review assignment completes for a cohort when its mentor closes the workshop, not when each learner finishes. A self-paced course that needs per-learner completion should use mentor review.

## D7. Review-site views (FR-007)

- **Reviewer view**: the assignment page shows everything, with each `## Mentor only` block in a "Mentor only" admonition so the SME can see the boundary.
- **Learner view**: the page is stripped by D3, and withheld whole on doubt.

## D8. Republish never touches learner work (FR-008)

A republish updates the brief, the criteria text and the settings in place. It never:

- deletes a module
- passes a workshop `phase` or touches allocations
- rescales grades
- re-keys guide criteria

Structural criteria changes after grading stop and need `--allow-criteria-change`. A removed assignment is hidden. A forum's posts are never read or written. The research tasks in R1–R7 verify each of these, and SC-003 tests them.

## What this design changes, so the decision is informed

`disclosure.py`, a new `assignment_parse.py`, `moodle_payload.py`, `check_moodle_payload.py`, `publish_moodle.py`, `gen_course_site.py`, `check_learner_view.py`, `check_course_package.py`, `course_stage.py` (`is_lesson` only), `local_ltuse` (three web-service functions), a new `workshopallocation_orgcohort` plugin, `moodle/site/roles.yaml`, `moodle/site/site.yaml` (lists the new plugin), a new `moodle/site/course-discussions.yaml` (shipped in Phase A, retired 2026-10-02 by spec 002 R14), the `quiz-writer` and `alignment-reviewer` agents, `process/stages/03-draft.md` and `04-alignment.md`, CLAUDE.md's file-naming convention, and the course-package CI workflow.

---

## Decision record

| | |
|---|---|
| Decision | *(Approved option A / approved with changes / rejected)* |
| Changes requested | |
| Decided by | |
| Date | |

The change that fills this in also removes the CI guard (R8) and replaces it with the format check. Nothing else in Phase B or C is merged before it.
