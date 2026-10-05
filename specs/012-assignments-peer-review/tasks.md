# Tasks: Assignments and peer review in courses

> **Parked for re-plan (Doug, 2026-10-05).** Open courses (spec 002, 2026-10-02) and spec 008
> plan decision 3's mentor groups change this spec's basis; Phases B and C (Phases 5 and 6
> below) and the design approval (T014) wait on the re-plan. Ticked tasks stay ticked as
> history; where what they built has since been retired, a note says so. See
> [spec.md, Re-plan inputs](spec.md#re-plan-inputs).

**Input**: [spec.md](spec.md), [plan.md](plan.md), [research.md](research.md), [design.md](design.md), [data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: the plan asks for `pytest` over the parser, the disclosure rules and the payload checks. Each test task is written to fail first. Moodle behaviour is verified with the quickstart scenarios on the temporary 5.2.3+ instance, with test accounts only (constitution III, X).

**Phase order follows the design gate, not only priority.** US1 (P1) is the gate. US4 (P3) is outside the gate (FR-001, FR-015) and ships while the maintainer decides. US2 (P1) and US3 (P2) are built only after the decision is recorded as approved. This is the plan's Phase A → B → C.

**Format**: `- [ ] **T###** [P?] [US#] Description · path`

---

## Phase 1: Setup

**Wave 1 — independent (different files):**

- [x] **T001** [P] Add the design-gate guard: fail on any `*-assignment.md` under `modules/` with "assignments wait on the spec 012 design approval" (R8, FR-001, SC-001). This is the first commit of the spec. · scripts/check_course_package.py
- [x] **T002** [P] Add a `python -m pytest -q tests --ignore=tests/test_site_config.py` step (with `pytest` installed and `tests/**` in the trigger paths), so the new tests run in CI. Moved on rebase to main's `publisher-tests.yml`, which has its own job, so a failing course check no longer stops them · .github/workflows/publisher-tests.yml

---

## Phase 2: Foundational (blocks every story)

The `## Mentor only` marker in the one disclosure definition, the hide-don't-delete publish step, the role declarations, and the instance research the stories depend on. None of it is an assignment build, so none of it waits on the gate.

**Wave 1 — independent (different files):**

- [x] **T003** [P] Write failing tests for the mentor-only marker: a block runs to the next H1/H2; qualified and repeated markers; `strip_restricted()` removes both marker kinds and returns `ok = False` on a *grading notes*, *model answer*, *marking notes* or *mentor notes* heading outside a block; `restricted_blocks()` returns blocks of both kinds; `strip_answer_keys()` behaves as before. Invented fixture text only · tests/test_disclosure_mentor_only.py, tests/fixtures/mentor-only/
- [x] **T004** [P] Add `local_ltuse_hide_orphans` (superseded on rebase by main's `local_ltuse_hide_modules`, PR #71): takes `courseidnumber` and `keep`, sets every other `ltct:` module `visible = 0` through `set_coursemodule_visible()`, never deletes, returns the hidden idnumbers (R7, contracts/moodle-functions.md) · moodle/local_ltuse/classes/external/hide_orphans.php
- [x] **T005** [P] Declare `teacher` (archetype defaults, no `accessallgroups`), `student` (`mod/workshop:viewauthornames: inherit`, inert until a workshop exists) and the three `ltcpublisher` capabilities, each with its `why`. Review the `ltcpublisher` additions against least privilege and record the result in the `why` (contracts/site-declaration.md) · moodle/site/roles.yaml
- [ ] **T006** [P] Settle instance research tasks 1, 2, 5 and 6 on the temporary 5.2.3+ instance by hand with test accounts (groups in a separate-groups forum and workshop; guide ids stable after grading; author names hidden under the `student` override; `backup_auto_users` and recycle bin). Record each result under a new "Instance results" heading · specs/012-assignments-peer-review/research.md

**⟶ Wait for Wave 1 to finish, then:**

**Wave 2 — independent (different files):**

- [x] **T007** [P] Add `MENTOR_ONLY_RE`, `strip_restricted(md)` and `restricted_blocks(md)` beside the answer-key rules; extend the residue pattern with the four headings; keep `strip_answer_keys()` as the quiz name (design D3). T003 passes · scripts/disclosure.py
- [x] **T008** [P] Register `local_ltuse_hide_orphans` (superseded, see T004) in the `ltuse_publish` service, bump the version, document it · moodle/local_ltuse/db/services.php, moodle/local_ltuse/version.php, moodle/local_ltuse/README.md
- [ ] **T009** [P] Run `site_config.py validate`, fix what T005 trips, then `apply` and `drift` on the instance · moodle/site/roles.yaml (verification only)

**⟶ Wait for Wave 2 to finish, then:**

- [x] **T010** Call `local_ltuse_hide_orphans` (now main's `hide_modules`) after every publish with the manifest's idnumbers as `keep`, report what was hidden, and add the function to the `--whoami` needed list. This builds the "a module the course no longer has is HIDDEN" behaviour the publisher already documents (R7 gap) · scripts/publish_moodle.py, scripts/moodle_client.py

**Checkpoint**: the marker exists in one place, republishing hides rather than deletes, and the facts the stories rest on are recorded.

---

## Phase 3: User Story 1 — The maintainer approves the design (Priority: P1) 🎯 gate

**Goal**: a recorded decision on [design.md](design.md) before any assignment file or assignment build exists.

**Independent Test**: design.md answers FR-001 to FR-008a, compares options on the four axes, names a recommendation and the rejected alternatives, and carries a dated decision. The CI guard rejects an assignment file meanwhile.

Files: specs/012-assignments-peer-review/design.md, specs/012-assignments-peer-review/checklists/design-gate.md, specs/003-mentor-role/spec.md

**Wave 1 — independent (different files):**

- [x] **T011** [P] [US1] Check design.md against the US1 Independent Test and acceptance scenarios 1–3, one line per FR-001 to FR-008a with where it is answered; fix any gap in design.md · specs/012-assignments-peer-review/checklists/design-gate.md
- [x] **T012** [P] [US1] Run quickstart A1 on a scratch branch: an empty `09-x-assignment.md` makes `check_course_package.py` exit 1 with the gate message (US1-4, SC-001). Delete the file; nothing is committed · (verification only)
- [x] **T013** [P] [US1] **Maintainer**: decide the spec 003 FR-006 conflict (R3, plan "Cross-spec effects"). Recommended: FR-006 covers only the user-context mentor role; assignment feedback goes through the course-level `teacher` role, which never awards a CBC level · specs/003-mentor-role/spec.md

**⟶ Wait for Wave 1 to finish, then:**

- [ ] **T014** [US1] **Maintainer**: fill in the decision record (decision, changes requested, name, date). If rejected, stop here: Phases 5 and 6 are not built, and Phase 4 still ships · specs/012-assignments-peer-review/design.md
  *(2026-10-05: waits on the re-plan (Doug, 2026-10-05); not to be decided against the 2026-10-01 design. Still open.)*

**Checkpoint**: the decision is recorded. Approved → Phase 5 may start. Rejected → the guard stays and the spec delivers US4 only.

---

## Phase 4: User Story 4 — Learners discuss a course inside it (Priority: P3, outside the gate)

**Goal**: every published course, backfilled ones included, has one discussion forum, ~~separated by organisation unless declared shared~~. *(Superseded 2026-10-02: the forum runs with no groups and is open to everyone in the course; there is no sharing to declare (spec 002 R14). Re-plan input 5.)*

**Independent Test**: publish a test course; test learners in one organisation post and reply; a learner in another organisation cannot read the thread (quickstart A3–A5). *(Superseded in part 2026-10-02: a learner in another organisation can read it (spec 002 R14); see T025. Re-plan input 5.)*

Files: ~~moodle/site/course-discussions.yaml~~ (retired 2026-10-02 by spec 002 R14; `scripts/site_config.py` refuses it), scripts/site_config.py, moodle/local_ltuse/classes/external/ensure_discussion.php, moodle/local_ltuse/classes/siteconfig/inspector.php, moodle/local_ltuse/classes/siteconfig/drift.php, moodle/local_ltuse/classes/siteconfig/applier.php, scripts/moodle_payload.py, moodle/REQUIREMENTS.md · handed on from Phase 2: scripts/publish_moodle.py, scripts/moodle_client.py, moodle/local_ltuse/db/services.php, version.php, README.md

### Tests

**Wave 1 — independent (different files):**

- [x] **T015** [P] [US4] Failing tests for `course-discussions.yaml` validation: unknown slug, missing `why`, duplicate slug, empty list valid · tests/test_site_config.py
  *(Superseded 2026-10-02 by spec 002 R14: the tests now assert that the file is refused and `load_discussions` is gone (`test_course_discussions_file_is_retired`). Re-plan input 5.)*
- [x] **T016** [P] [US4] Failing tests that every course's payload, a backfilled one included, carries a `discussion` block with `ltct:<slug>:discussion`, and that `shared` follows the declaration · tests/test_moodle_payload_discussion.py
  *(Superseded 2026-10-02 by spec 002 R14: the `discussion` block test stands, but the payload has no `shared`; `test_block_carries_no_sharing_flag` asserts the block is only `{idnumber, name, intro_html}`. Re-plan input 5.)*

### Implementation

**Wave 1 — independent (different files):**

- [x] **T017** [P] [US4] Create the declaration with its header comment, `rows: [10]` and an empty `shared:` list (FR-015) · moodle/site/course-discussions.yaml
  *(Superseded 2026-10-02: the file no longer exists. Spec 002 R14 retired it when every course forum went to no groups, and `scripts/site_config.py` now refuses it. Re-plan input 5.)*
- [x] **T018** [P] [US4] Add `local_ltuse_ensure_discussion`: create a `general` forum in section 0 if absent (name and intro on create only); otherwise set only its group mode, `groupingid = 0`; read and write no discussion or post; report `courseforced`; return `{cmid, created, groupmode}` (R5) · moodle/local_ltuse/classes/external/ensure_discussion.php
- [x] **T019** [P] [US4] Report discussion drift for every `ltct:` course: `differs`, `missing`, `forced`, and `allparticipants` as a count only, never content or names (R5, Principle III) · moodle/local_ltuse/classes/siteconfig/inspector.php, moodle/local_ltuse/classes/siteconfig/drift.php
  *(Superseded in part 2026-10-02 by spec 002 R14: the `allparticipants` warning and its forum vault count are removed; `differs`, `missing` and `forced` stand. Re-plan input 5.)*

**⟶ Wait for Wave 1 to finish, then:**

**Wave 2 — independent (different files):**

- [x] **T020** [P] [US4] Add `load_discussions()`, the validation rules (slugs checked with `course_stage.branch_slug()`), and carry the declaration into the remote drift/apply payload. T015 passes · scripts/site_config.py
  *(Superseded 2026-10-02: `load_discussions()` and the sharing payload were removed by spec 002 R14; the forum is NOGROUPS everywhere.)*
- [x] **T021** [P] [US4] `apply` corrects `differs` through ensure_discussion's code path and never creates a missing forum · moodle/local_ltuse/classes/siteconfig/applier.php
- [x] **T022** [P] [US4] Register `local_ltuse_ensure_discussion`, bump the version, document it · moodle/local_ltuse/db/services.php, moodle/local_ltuse/version.php, moodle/local_ltuse/README.md

**⟶ Wait for Wave 2 to finish, then:**

**Wave 3 — independent (different files):**

- [x] **T023** [P] [US4] Write the `discussion` block for every course from `site_config.load_discussions()`. T016 passes · scripts/moodle_payload.py
  *(Superseded 2026-10-02 by spec 002 R14: the block is still written for every course, but with no sharing flag and without `load_discussions()`, which no longer exists. Re-plan input 5.)*
- [x] **T024** [P] [US4] Call `local_ltuse_ensure_discussion` on every publish, keep its idnumber out of the hide step, print `created`/`updated`/`courseforced`, and add it to the `--whoami` needed list · scripts/publish_moodle.py, scripts/moodle_client.py

**⟶ Wait for Wave 3 to finish, then:**

**Wave 4 — independent (different files):**

- [ ] **T025** [P] [US4] Run quickstart A3–A5 on the instance: forum created, posts survive republish, org B cannot read org A, a declared share opens it, a hand change shows as `differs` and `apply` restores it (US4-1 to US4-4, SC-004 for discussion) · (verification only)
  *(Superseded in part 2026-10-02: "org B cannot read org A" and "a declared share opens it" no longer apply; the forum is open across organisations (spec 002 R14), and spec 002's own check covers that. Forum created, posts surviving republish, and `differs` plus `apply` still stand. Evidence 2026-10-05: `site_config.py drift` on ltuse.net after `apply` reported 3 discussion forums missing, in courses published before spec 002's open-courses change (coretech-computer-hardware, paratext-quotation-rules, software-support-and-troubleshooting-for-translation-teams); republishing a course creates its forum, and `apply` never does. Still open: no instance check has run.)*
- [x] **T026** [P] [US4] Update row #10's in-course part · moodle/REQUIREMENTS.md

**Checkpoint**: US4 works on its own and can merge before the design decision.

---

## Phase 5: User Story 2 — A learner submits work and gets mentor feedback (Priority: P1)

**Starts only after T014 records an approved decision.**

**Goal**: an assignment authored in markdown publishes as a core assignment with a marking guide, grading notes reach only mentors, and the model answer sits on a hidden, student-prohibited page.

**Independent Test**: publish a test course with one assignment; a test learner submits (once offline in the app); the test mentor grades with the guide; the learner sees feedback; no learner-visible page holds a grading note or the model answer (quickstart B1–B6).

Files: scripts/assignment_parse.py, scripts/course_stage.py, scripts/check_course_package.py, scripts/check_moodle_payload.py, scripts/gen_course_site.py, scripts/check_learner_view.py, moodle/local_ltuse/classes/external/upsert_assignment.php, moodle/local_ltuse/classes/external/create_page.php, .claude/agents/quiz-writer.md, .claude/agents/alignment-reviewer.md, process/stages/03-draft.md, process/stages/04-alignment.md, CLAUDE.md, .github/workflows/course-package.yml · handed on from Phase 4: scripts/moodle_payload.py, scripts/publish_moodle.py, scripts/moodle_client.py, moodle/local_ltuse/db/services.php, version.php, README.md

### Tests

**Wave 1 — independent (different files):**

- [ ] **T027** [P] [US2] Invented fixture course: a README, one lesson, a mentor-review assignment, a peer assignment, and planted-leak variants (a grading-note line in the lesson, the model answer in the brief, a `## Model answer` heading outside the block) · tests/fixtures/assignment-course/
- [ ] **T028** [P] [US2] Failing parser tests against contracts/assignment-file.md: every header key and default, unknown key raises, `file` without size raises, notes heading with no criterion raises, 1–12 unique criteria, `peers` only with `peer`, the parse result shape · tests/test_assignment_parse.py

**⟶ Wait for Wave 1 to finish, then:**

- [ ] **T029** [US2] Failing payload tests: manifest `assignments` shape; mentor pages only under `mentor/` with `visible: 0`; peer criteria carry `marker_notes_html: ""` and the FR-012a line is appended to the brief; a withheld file ships only the placeholder; checks 5–7 fail on each planted leak every time (SC-002) · tests/test_moodle_payload_assignments.py

### Implementation

**Wave 1 — independent (different files):**

- [ ] **T030** [P] [US2] Strict parser with `--check-all`, built on `disclosure.restricted_blocks()`; anything outside the grammar raises. T028 passes · scripts/assignment_parse.py
- [ ] **T031** [P] [US2] `is_lesson()` returns False for `-assignment.md` (design D4) · scripts/course_stage.py
- [ ] **T032** [P] [US2] Add `local_ltuse_upsert_assignment`: `upsert_module` with the contract's settings, `gradepass = 0`, separate groups; completion from `required`, `completionunlocked` only on change; guide `update_definition()` with `alwaysshowdefinition = 0` and ids reused by shortname; `error:criteriachange` unless allowed; never rescale (R1, R2, R7) · moodle/local_ltuse/classes/external/upsert_assignment.php
- [ ] **T033** [P] [US2] Add `prohibitstudentview` to `create_page`: `role_change_permission()` prohibits `mod/page:view` for `student` at the module context, after `require_capability('moodle/role:safeoverride')` and an `is_safe_capability()` check (R1, second lock) · moodle/local_ltuse/classes/external/create_page.php
- [ ] **T034** [P] [US2] Add an assignment section: draft at 3c beside the quiz, header lines, `## Mentor only` placement, no criterion that judges minority-language text, `> **Example needed:**` placeholders (FR-005, FR-014) · .claude/agents/quiz-writer.md
- [ ] **T035** [P] [US2] Add the stage-4 assignment checks: criteria trace to objectives, FR-014, `**Estimated time:**` ≤ 90, mentor-only blocks where D2 puts them · .claude/agents/alignment-reviewer.md
- [ ] **T036** [P] [US2] Add the assignment steps to the draft and alignment how-tos · process/stages/03-draft.md, process/stages/04-alignment.md
- [ ] **T037** [P] [US2] Add the assignment file to convention 6's naming rule (`-assignment.md` last) and to the scripts list · CLAUDE.md

**⟶ Wait for Wave 1 to finish, then:**

**Wave 2 — independent (different files):**

- [ ] **T038** [P] [US2] Replace the T001 guard with the format check through `assignment_parse`; import `is_lesson` from `course_stage` and drop the local copy; warn on missing `**Completion:**` and on `**Submit:** file` alone; count `Example needed` placeholders · scripts/check_course_package.py
- [ ] **T039** [P] [US2] Emit `assignments` per contracts/payload.md with mentor pages under `mentor/`; strip every learner page with `strip_restricted()`; placeholder and `withheld` entry on parse failure or `ok = False`; peer review appends the FR-012a line and routes grading notes to the mentor page. T029's shape tests pass · scripts/moodle_payload.py
- [ ] **T040** [P] [US2] Render assignment pages: reviewer view puts each mentor-only block in a "Mentor only" admonition; learner view uses `strip_restricted()` and withholds on `ok = False` (design D7) · scripts/gen_course_site.py
- [ ] **T041** [P] [US2] Register `local_ltuse_upsert_assignment` and the new `create_page` parameter, bump the version, document both · moodle/local_ltuse/db/services.php, moodle/local_ltuse/version.php, moodle/local_ltuse/README.md
- [ ] **T042** [P] [US2] Add `python scripts/assignment_parse.py --check-all` to the gates, and `tests/test_assignment_parse.py` and `tests/test_moodle_payload_assignments.py` to `publisher-tests.yml` · .github/workflows/course-package.yml, .github/workflows/publisher-tests.yml

**⟶ Wait for Wave 2 to finish, then:**

**Wave 3 — independent (different files):**

- [ ] **T043** [P] [US2] Add checks 5–7 (positive mentor-only absence, placement, withheld), no `--force`. T029 passes in full · scripts/check_moodle_payload.py
- [ ] **T044** [P] [US2] Assert every mentor-only line is absent from the built learner site, as check 5 does · scripts/check_learner_view.py
- [ ] **T045** [P] [US2] Publish each mentor-review assignment through `upsert_assignment` (brief images in a draft area) and its mentor page through `create_page` with `visible = 0` and `prohibitstudentview`; refuse a peer assignment with "peer review arrives with US3" for now; add `--allow-criteria-change`; extend the `--allow-withheld` refusal to assignments; keep all their idnumbers in `keep`; add to the `--whoami` needed list · scripts/publish_moodle.py, scripts/moodle_client.py

**⟶ Wait for Wave 3 to finish, then:**

- [ ] **T046** [US2] Run quickstart B1–B6 on the instance, with research tasks 3 (learner never receives `descriptionmarkers` or the hidden page, web, app and mobile web services) and 4 (`activityeditor` draft area). Record pass/fail in the PR description · (verification only)

**Checkpoint**: mentor-assessed assignments publish, disclose nothing, and survive republish. US2 is independently functional.

---

## Phase 6: User Story 3 — Learners review each other within their cohort (Priority: P2)

**Starts after Phase 5 is merged and research task 1 is settled (T006).**

**Goal**: a `**Review:** peer` assignment publishes as a workshop whose reviewers come from the author's cohort, then organisation, never across, anonymous both ways. *(Basis withdrawn 2026-10-02: shared courses have no organisation or cohort groups (spec 002). The allocation rule waits on re-plan input 4; anonymity stands, with re-plan input 6 for protected learners.)*

**Independent Test**: four test learners across two test organisations; each assesses only peers in their own organisation and receives their comments (quickstart C1–C3). *(2026-10-05: the organisation half waits on re-plan input 4.)*

Files: moodle/workshopallocation_orgcohort/ (version.php, lib.php, db/install.xml, db/events.php, classes/observer.php, lang/en/workshopallocation_orgcohort.php, README.md), moodle/local_ltuse/classes/external/upsert_workshop.php, moodle/site/site.yaml · handed on from Phase 5: scripts/publish_moodle.py, scripts/moodle_client.py, moodle/local_ltuse/db/services.php, version.php, README.md, moodle/REQUIREMENTS.md (from Phase 4)

**Wave 1 — independent (different files):**

- [ ] **T047** [P] [US3] Build the allocator: per submission, `peers` reviewers holding `mod/workshop:peerassess` from the author's `ltct:cohort:` group, then other groups of the same `ltct:org:`, never across; balanced load, no self-review, `workshop::add_allocation()` only; "Allocate within organisation" on the Allocation page; report authors short of reviewers (FR-011, FR-013, R4). Own table `(workshopid, peers, autoallocate)`; `requires` and `supported` declared · moodle/workshopallocation_orgcohort/version.php, lib.php, db/install.xml, lang/en/workshopallocation_orgcohort.php, README.md
  *(2026-10-05: waits on the re-plan. The `ltct:cohort:` and `ltct:org:` groups it reads do not exist in shared courses; re-plan inputs 1 and 4.)*
- [ ] **T048** [P] [US3] Add `local_ltuse_upsert_workshop`: `upsert_module` with `strategy = comments`, separate groups, never `phase`; instructions through fresh `file_get_unused_draft_itemid()` areas; `save_edit_strategy_form()` keeping dimension ids; completion on grade when required; never touch allocations, submissions or assessments (R4) · moodle/local_ltuse/classes/external/upsert_workshop.php

**⟶ Wait for Wave 1 to finish, then:**

**Wave 2 — independent (different files):**

- [ ] **T049** [P] [US3] Observer on `\mod_workshop\event\phase_switched` into `PHASE_ASSESSMENT` runs the allocator when `autoallocate = 1` · moodle/workshopallocation_orgcohort/db/events.php, moodle/workshopallocation_orgcohort/classes/observer.php
- [ ] **T050** [P] [US3] Register `local_ltuse_upsert_workshop`, have it store `peers` and `autoallocate` in the allocator's table through the allocator's own API, bump the version, document it · moodle/local_ltuse/db/services.php, moodle/local_ltuse/version.php, moodle/local_ltuse/README.md
- [ ] **T051** [P] [US3] List `workshopallocation_orgcohort` as our own plugin at its `version.php` version · moodle/site/site.yaml

**⟶ Wait for Wave 2 to finish, then:**

- [ ] **T052** [US3] Replace the peer refusal with `upsert_workshop` (aspects from the learner criteria, instructions to authors carrying the FR-012a line, `peers`, `required`); the mentor page path is unchanged · scripts/publish_moodle.py, scripts/moodle_client.py

**⟶ Wait for T052, then:**

**Wave 3 — independent (different files):**

- [ ] **T053** [P] [US3] Run quickstart C1–C3 on the instance, with research tasks 4 (workshop instruction editors) and 5 (no author name in web or app). Record pass/fail in the PR description (US3-1 to US3-4, SC-004 for allocation) · (verification only)
- [ ] **T054** [P] [US3] Update row #22 · moodle/REQUIREMENTS.md

**Checkpoint**: peer review works within organisations, with mentor fallback. US3 is independently functional.

---

## Phase 7: Polish & cross-cutting

**Wave 1 — independent (different files):**

- [ ] **T055** [P] Document course discussions for the maintainer and mentors: the sharing declaration, that only managers and editing teachers can post to "All participants" and why mentors must not, and what an accidental delete loses (research task 6) · moodle/site/README.md
  *(2026-10-05: the sharing declaration is gone. Spec 002 R14 retired `course-discussions.yaml` on 2026-10-02 (the forum is no groups in every course), and `scripts/site_config.py` refuses the file. The rest of the task, posting to "All participants" and what an accidental delete loses, still stands. Re-plan input 5.)*
- [x] **T056** [P] Raise the cross-spec effects: spec 002 must guarantee "every course group is within exactly one organisation" and the `ltct:org:`/`ltct:cohort:` idnumbers; spec 009 notes the free app plan's 2-offline-courses limit · specs/002-org-structure-cohorts/spec.md, specs/009-low-bandwidth-delivery/spec.md
  *(2026-10-05: spec 002 withdrew the group invariant and idnumbers on 2026-10-02 (002 spec, Dependencies, the 012 entry). Re-plan input 1.)*

**⟶ Wait for Wave 1 to finish, then:**

- [ ] **T057** Validate against the Success Criteria: run every repo gate in quickstart "Repo gates" plus `python -m pytest -q tests`; confirm SC-001 (no assignment file before T014's date, from `git log`), SC-002 (T029, T046), SC-003 (T025, T046), SC-004 (T025, T053). SC-005 needs 2–3 partner learners at a stage-7 pilot and is recorded as pending, not passed · (verification only)

---

## Dependencies & Execution Order

- **Setup → Foundational → US1 → US4 → US2 → US3 → Polish.**
- **US1** needs only Setup's guard. Its maintainer task T014 is the gate.
- **US4** needs Foundational, not US1. It runs while the maintainer decides.
- **US2** needs T014 approved and US4 merged. **US3** needs US2 merged and T006's research task 1.
- **Story phases run one at a time, never fanned out together.** The gate forces that order anyway, and it lets a few registry files pass from phase to phase: `scripts/moodle_payload.py` (US4 → US2), `scripts/publish_moodle.py` and `scripts/moodle_client.py` (Foundational → US4 → US2 → US3), `moodle/local_ltuse/db/services.php`, `version.php`, `README.md` (Foundational → US4 → US2 → US3), `moodle/REQUIREMENTS.md` (US4 → US3), `scripts/check_course_package.py` (Setup → US2), `.github/workflows/course-package.yml` (Setup → US2). Parallelism lives inside each phase's waves.

Waves:
- **Setup**: W1 (T001, T002).
- **Foundational**: W1 (T003–T006) → W2 (T007–T009) → T010.
- **US1**: W1 (T011–T013) → T014.
- **US4**: tests (T015, T016) → W1 (T017–T019) → W2 (T020–T022) → W3 (T023, T024) → W4 (T025, T026).
- **US2**: tests W1 (T027, T028) → T029 → W1 (T030–T037) → W2 (T038–T042) → W3 (T043–T045) → T046.
- **US3**: W1 (T047, T048) → W2 (T049–T051) → T052 → W3 (T053, T054).
- **Polish**: W1 (T055, T056) → T057.
