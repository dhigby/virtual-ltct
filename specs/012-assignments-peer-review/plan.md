# Implementation Plan: Assignments and peer review in courses

**Branch**: `012-assignments-peer-review` | **Date**: 2026-10-01 | **Spec**: [spec.md](spec.md)

> **Parked for re-plan (Doug, 2026-10-05).** Open courses (spec 002, 2026-10-02) and spec 008
> plan decision 3's mentor groups change this spec's basis; Phases B and C, and the design
> approval (tasks T014), wait on the re-plan. What the re-plan must address is listed in
> [spec.md, Re-plan inputs](spec.md#re-plan-inputs). Below, the organisation and cohort group
> invariant and the hand-enrolled Course mentor are marked superseded in place; the rest of
> this plan is unchanged and is the starting point for the re-plan. Phase A shipped, with its
> discussion forum since reworked by spec 002 R14 (no groups; `course-discussions.yaml`
> retired).

## Summary

Courses gain an optional **assignment file**, `NN-<topic>-assignment.md`. It is plain markdown with a short header (`**Completion:**`, `**Review:**`, `**Submit:**`), learner-visible criteria, and mentor-only grading notes and model answer under a `## Mentor only` H2.

The publisher turns each file into either:
- a core **assignment**, assessed by the learner's Course mentor with a core **marking guide**, whose per-criterion "description for markers" holds the grading notes; or
- a core **workshop** for peer review, with reviewers allocated by our own small allocation subplugin: cohort first, then the same organisation, never across. *(Basis withdrawn 2026-10-02: shared courses have no organisation or cohort groups (spec 002). The allocation rule waits on re-plan input 4.)*

The model answer goes to a hidden mentor-notes page. Every published course, backfilled ones included, also gains one **discussion forum**, separated by organisation unless the site declaration shares it. *(Since 2026-10-02 the forum runs with no groups in every course and the declaration is retired (spec 002 R14).)*

Three choices here are not obvious from the existing code:

- **The marking guide, not a rubric**: it is the only core grading form with a per-criterion field learners never receive, provided "always show definition" is off (R1).
- **One more marker in the one disclosure definition**: `## Mentor only` sits beside `## Answer key` in `scripts/disclosure.py`, stripped from every learner page, withheld whole on doubt, and checked positively before publish (design D3).
- **The work ships in three phases, because of the design gate.** Phase A is the gate guard, the design document and the discussion forum, none of which needs the gate (FR-001, FR-015). Phase B (mentor-assessed assignments) and Phase C (peer review) are built only after the maintainer records a decision in [design.md](design.md).

## Technical Context

**Language/Version**: Python 3 (repo scripts; CI runs 3.12, local runs 3.14). PHP 8.2+ for the Moodle plugins.

**Primary Dependencies**:
- Python: `markdown`, `pymdown-extensions`, `pyyaml`, `requests` (existing `publish-requirements.txt`)
- Moodle 5.2.3+ core: `mod_assign`, `gradingform_guide`, `mod_workshop` (`workshopform_comments`), `mod_forum`, `mod_page`
- No third-party plugin

**Storage**: authored data in `modules/` and `moodle/site/`; learner data in Moodle only.

**Testing**:
- `pytest` for the parser, disclosure and payload checks, with fixtures under `tests/fixtures/`
- the existing CI gates
- the quickstart scenarios on the temporary instance with test accounts

**Target Platform**: self-hosted open-source Moodle LMS 5.2.3+, plus the free Moodle Android app.

**Project Type**: repo tooling (CLI scripts) plus Moodle plugins.

**Performance Goals**: none beyond today's publish. A republish adds about 3 web-service calls per assignment and 1 per course.

**Constraints**:
- disclosure fails closed with no override
- one-way publish
- core first
- no vendored edits
- no learner data in the repo
- learner submissions work offline in the app

**Scale/Scope**: about 30 courses, each with 0–3 assignments; cohorts of about 2–30 learners; several organisations on one site.

No NEEDS CLARIFICATION remains. The six behaviours still to be settled on the instance are listed as research tasks at the end of [research.md](research.md), not assumed.

## Constitution Check

*Gate before Phase 0, re-checked after Phase 1 design.*

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. Assignments are authored in `modules/` and published one way, and no script reads a submission, grade or post. The marker joins `disclosure.py`, the single definition. `check_course_package.py` drops its duplicate `is_lesson()` and imports `course_stage`'s. Sharing lives in `moodle/site/`, not frontmatter. |
| II. Portability | PASS. The file is raw-readable markdown. The payload names content by meaning (`marker_notes_html`, `mentor_page`), and only `moodle_client`/`local_ltuse` know it becomes a guide or a hidden page. Identity is `ltct:` idnumbers in Moodle. Roles and sharing are declared and applied by `site_config.py`. Learner work stays exportable through core privacy. |
| III. Public repo (NON-NEGOTIABLE) | PASS. Fixtures are invented text. Drift reports only a *count* of "All participants" discussions, never content or names. No token in any file. |
| IV. Disclosure (NON-NEGOTIABLE) | PASS. Mentor-only material is stripped from every learner page and withheld whole on doubt. Its every distinctive line is asserted absent from every learner-visible payload field (check 5). It may land only in a capability-protected guide field or in a page that is hidden *and* prohibited to `student`. The guide's `alwaysshowdefinition = 0` also shuts the one web-service route (R1). No `--force`. See Complexity Tracking for why a model answer reaches page HTML at all. |
| V. CBC fidelity | PASS. The guide score is course training evidence, never a pass threshold (`gradepass = 0`) and never a CBC level. No badge or wording says "certified". |
| VI. No git, no LMS orientation | PASS. Authors write one markdown file, and the `quiz-writer` agent drafts it. Learners submit from the course page or app with no setup. Mentors allocate with one button on the workshop's own page. Phase switching stays a mentor step, which is the price of core workshop (R4). |
| VII. One shape, gated stages | PASS. Assignments go through the same stages and checks (design D4). No per-course special case beyond the declared sharing list, which FR-015 asks for. Build is gated on the recorded design decision (constitution X). |
| VIII. Language data | PASS. FR-014 is checked by the alignment reviewer. Missing real examples are marked placeholders, which the package check counts. |
| IX. Flat cost, field-ready | PASS. Core plus our own plugin, no paid plan. Text and file submissions, peer assessment and forum posts all work offline in the free app (R6). Briefs state size and format. The free app plan's 2-offline-courses limit is reported to spec 009. |
| X. Traceable and verified | PASS. Rows #22 and #10 are cited and updated in the delivering PR. The content-model change waits on [design.md](design.md)'s decision, enforced by CI (R8). Every API was confirmed in `MOODLE_502_STABLE` source, and six behaviours are open research tasks on the instance. SC-005 needs 2–3 partner learners, at a stage-7 pilot. No new recurring operation: drift already runs under spec 015. |
| XI. Survives an upgrade | PASS. All changes are configuration, `local_ltuse` functions or a new `workshopallocation` subplugin (a supported plugin type). Only public APIs are used: `add_moduleinfo`/`update_moduleinfo`, `get_grading_manager()->get_controller()->update_definition()`, the workshop strategy `save_edit_strategy_form()`, `workshop::add_allocation()`, `role_change_permission()` (guarded by `moodle/role:safeoverride` and `is_safe_capability()`), `set_coursemodule_visible()`, events. There are no direct table writes: the workshop editors get real draft item ids (R4). Both plugins declare `requires` and `supported`. |
| Platform: core first | PASS, with one justified own-code item: the allocator. Core random allocation can't express "cohort first, organisation fallback" (R4). |

Re-checked after Phase 1 design: no change. One cross-spec conflict is raised below, not routed around.

## Project Structure

### Documentation (this feature)

```text
specs/012-assignments-peer-review/
├── spec.md
├── plan.md              # this file
├── research.md          # R1–R8 + instance research tasks
├── design.md            # the US1 design for the maintainer's decision (FR-001–FR-008a)
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── assignment-file.md
│   ├── payload.md
│   ├── moodle-functions.md
│   └── site-declaration.md
└── tasks.md             # /speckit-tasks
```

### Source code

```text
scripts/
├── disclosure.py               # Phase A: + MENTOR_ONLY_RE, strip_restricted(), restricted_blocks()
├── course_stage.py             # Phase B: is_lesson() excludes -assignment.md
├── check_course_package.py     # Phase A: gate guard (R8). Phase B: guard → format check; imports is_lesson
├── assignment_parse.py         # Phase B: new; strict parser + --check-all
├── moodle_payload.py           # Phase A: discussion block. Phase B: assignments + mentor/ pages
├── check_moodle_payload.py     # Phase B: checks 5–7
├── publish_moodle.py           # Phase A: ensure_discussion (hide_modules came from main). Phase B/C: assignments
├── gen_course_site.py          # Phase B: assignment pages; "Mentor only" admonition / strip
├── check_learner_view.py       # Phase B: positive mentor-only assertion
└── site_config.py              # Phase A: course-discussions.yaml validate/drift/apply
moodle/
├── site/course-discussions.yaml        # Phase A: new. Retired 2026-10-02 (spec 002 R14); site_config.py now refuses it
├── site/roles.yaml                     # Phase A: teacher declared; ltcpublisher additions. Phase C: student override
├── site/site.yaml                      # Phase C: lists workshopallocation_orgcohort
├── local_ltuse/
│   ├── classes/external/ensure_discussion.php     # Phase A
│   ├── classes/external/upsert_assignment.php     # Phase B
│   ├── classes/external/upsert_workshop.php       # Phase C
│   ├── classes/external/create_page.php           # Phase B: prohibitstudentview
│   ├── classes/siteconfig/{inspector,drift}.php   # Phase A: discussion drift
│   ├── db/services.php, version.php, README.md    # each phase
└── workshopallocation_orgcohort/               # Phase C: new subplugin (lib.php, classes/observer.php, db/, version.php, README.md)
tests/
├── fixtures/                            # invented course text, planted-leak variants
├── test_disclosure_mentor_only.py       # Phase A (marker) / B (assignment cases)
├── test_assignment_parse.py             # Phase B
└── test_moodle_payload_assignments.py   # Phase B
.claude/agents/quiz-writer.md, alignment-reviewer.md   # Phase B
process/stages/03-draft.md, 04-*.md                    # Phase B
.github/workflows/course-package.yml                   # Phase B: + assignment_parse --check-all
moodle/REQUIREMENTS.md                                 # rows 10 (Phase A), 22 (Phase C)
CLAUDE.md                                              # Phase B: assignment file convention
```

**Structure Decision**:
- The parser, the disclosure rules and the payload stay in `scripts/`, beside their quiz equivalents, because each assignment consumer mirrors a quiz consumer.
- The Moodle half goes in `local_ltuse`, which is already the publisher's only Moodle dependency.
- The allocator is a separate plugin because Moodle's plugin type demands it: allocators live under `mod/workshop/allocation/`.

### Phases and the gate

| Phase | Ships | Gate |
|---|---|---|
| **A** | CI guard (R8, first commit) · `design.md` for decision · `## Mentor only` in `disclosure.py` (lesson pages benefit at once) · discussion forum + sharing declaration + drift · hide-don't-delete (main's `hide_modules`, PR #71) · row #10 in-course part | none: FR-001 exempts FR-015, and the guard and marker are protections, not assignment builds |
| **B** | parser, format check, payload, checks 5–7, review-site views, `upsert_assignment`, hidden mentor page, agents and stage how-tos | **design.md decision recorded as approved** |
| **C** | `upsert_workshop`, `workshopallocation_orgcohort`, student anonymity override, row #22 | Phase B merged; instance research task 1 settled |

## Cross-spec effects

- **Spec 003 (conflict, needs the maintainer)**: 003 FR-006 forbids a mentor changing grades, but assessing with a marking guide writes a grade. Recommendation (R3): restrict 003 FR-006 to the user-context mentor role; assignment feedback is given through the course-level Course mentor role, which can never award a CBC level. ~~Syncing a learner's 003 mentor into the courses they take as Course mentor is deferred to spec 003's or 008's plan. Until then the site team enrols the mentor as Course mentor with the organisation's group (spec 002 decision 2026-10-01; spec 003 research R8).~~ *(Superseded 2026-10-05: spec 008's course-mentor sync enrols each learner's course mentor (008 plan decision 2) as `teacher` in their own "Mentor group `<n>`", idnumber `ltct:mentorgroup:<mentor id>` (008 research R10, plan decision 3), on since 2026-10-05 (`local_ltuse/coursementorsync: 1`, #97). Nobody enrols a Course mentor by hand. Re-plan input 2.)* The FR-006 conflict itself was decided on 2026-10-02 as recommended (tasks T013).
- **Spec 002**: ~~this plan depends on two things from it: the invariant that **every course group is within exactly one organisation**, and group idnumbers `ltct:org:<key>` and `ltct:cohort:<key>`. 002's plan must adopt both or tell this spec.~~ *(Withdrawn by spec 002 on 2026-10-02: shared courses have no organisation groups and stay in group mode 0 (002 FR-011). The re-plan adopts 008's mentor groups as the assessor scope, and needs the cross-organisation peer-review rule 002 left to 012 and Doug. Re-plan inputs 1 and 4.)*
- **Spec 004**: assignment grades and "awaiting feedback" appear in organisation reports. The grade items carry `ltct:` idnumbers (synced from `cmidnumber`, R2), which reports can key on.
- **Spec 009**: the free app plan allows 2 offline courses per device per site (R6, DOC).
- **Spec 015**: no new operation. `site_config.py drift` gains discussion items under its existing schedule.
- **Spec 001**: `roles.yaml` gains `student` and `teacher` entries, and `ltcpublisher` gains three capabilities.

## Complexity Tracking

| Exception | Why needed | Simpler alternative rejected because |
|---|---|---|
| Model answer published as page HTML (a hidden page), next to Principle IV's "answer keys … never in page HTML" | US2-3 requires the mentor to see the model answer beside the submission. No core assign or guide field holds free mentor-only text (R1). | **Keep it out of Moodle** (mentors read the `/review/` site): it splits the mentor's work across two places and sends mentors to a site carrying every quiz's answer key. **Rubric description**: it goes out to learners over the web service. The hidden page has two locks (`visible = 0` and a `student` prohibit), payload check 6 enforces both, and instance research task 3 verifies them. The principle's sentence is about quiz answer keys, but the plan treats a model answer with the same severity. |
| Own code: `workshopallocation_orgcohort` | FR-011's cohort-first, organisation-fallback rule is not in core random allocation (R4). | Core allocation scoped to organisation groups loses cohort-first, which the clarification chose. Manual top-up pushes LMS mechanics onto mentors (Principle VI). |
| `ltcpublisher` gains `moodle/role:safeoverride` | The second lock on mentor-notes pages. | `visible = 0` alone is one capability (`viewhiddenactivities`) away from a leak. A disclosure boundary keeps two locks when the second one is cheap. |
