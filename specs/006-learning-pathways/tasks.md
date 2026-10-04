# Tasks: Learning pathways mapped to CBC

**Input**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: asked for by the plan and constitution X: pytest for the Python side, PHP harnesses
for the pure PHP classes. Instance checks are quickstart V1–V12, run by a human with test
accounts, and are not tasks here.

Paths are from the repository root. `local_ltuse/` means `moodle/local_ltuse/`.

## Phase 1: Setup

**Wave 1 — independent (different files):**

- [ ] **T001** [P] Confirm every core API in research R11 in upstream `MOODLE_502_STABLE` source (event base, `cohort_deleted`, `primary_extend`, `cohort_is_member`, `completion_info`, XMLDB `add_field`/`field_exists`, `CoreMainMenuDelegate`), and record file and line under a "Confirmed in source" heading · specs/006-learning-pathways/research.md
- [ ] **T002** [P] Bump `local_ltuse` to `2026100600`, release `0.11.0`, and pin it in `site.yaml` · local_ltuse/version.php, moodle/site/site.yaml

## Phase 2: Foundational (blocks every story)

**Wave 1 — independent (different files):**

- [ ] **T003** [P] Four tables (`local_ltuse_course_pathway` with `pathwaykeys`, `local_ltuse_role_pathway`, `local_ltuse_role_pathway_comp`, `local_ltuse_pathway_cohort`) and `slug`, `url` on `local_ltuse_competency`, in install.xml and in one `upgrade.php` step at `2026100600` · local_ltuse/db/install.xml, local_ltuse/db/upgrade.php
- [ ] **T004** [P] `pathway\catalogue`: key constants and pattern, `parse_key()`, `exists()`, `is_assignable()`, `courses()`, `pathways_for_course()`, `all()`, and the membership SQL of data-model "Pathway membership" (FR-001, FR-002, FR-005, FR-014) · local_ltuse/classes/pathway/catalogue.php
- [ ] **T005** [P] `pathway\builder`, pure: competency pathway (four level rows, courses by full name, `nocourseyet` with the competency url, `next`, `done`) and role pathway (competencies in order, distinct-course totals), from facts handed in (FR-003, FR-004, FR-008, FR-011, R6) · local_ltuse/classes/pathway/builder.php
- [ ] **T006** [P] `pathway\viewer::may_view()`, pure, fails closed on a missing fact (FR-012, R8) · local_ltuse/classes/pathway/viewer.php
- [ ] **T007** [P] `pathway\progress`: for a learner and a set of course ids, enrolment and `course_completions.timecompleted`, mapped through `mentoring::progress_status()` (FR-010, R7) · local_ltuse/classes/pathway/progress.php
- [ ] **T008** [P] Events `pathway_courses_changed`, `pathway_assigned` and `pathway_unassigned`, shapes as contracts/pathway-api.md "Events" · local_ltuse/classes/event/pathway_courses_changed.php, local_ltuse/classes/event/pathway_assigned.php, local_ltuse/classes/event/pathway_unassigned.php
- [ ] **T009** [P] The "Spec 006: pathways" string block from contracts/pages.md, plus `privacy:metadata:local_ltuse_pathway_cohort*` strings and the event names · local_ltuse/lang/en/local_ltuse.php
- [ ] **T010** [P] `site_config.py`: `slug` and `url` on each competency (descriptor slug, `mkdocs.yml` site_url, category slug), the `levels` array, and `pathways.yaml` as an optional top file with its validation and `role_pathways` payload (contracts/declaration.md) · scripts/site_config.py
- [ ] **T011** [P] `moodle/site/pathways.yaml` with `rows: [12]`, `purpose` and `roles: []`, and the commented shape · moodle/site/pathways.yaml

**⟶ Wait for Wave 1 to finish, then:**

**Wave 2 — independent (different files):**

- [ ] **T012** [P] `pathway\assignments`: `assign()`, `unassign()`, `for_cohort()`, `cohorts_for()`, `pathways_for_user()`, `may_assign()`, firing T008's events, signatures exactly as contracts/pathway-api.md (FR-013) · local_ltuse/classes/pathway/assignments.php
- [ ] **T013** [P] Site config in PHP: `competencies.php` stores and compares `slug` and `url`; new `pathwaylevels.php` (config `pathwaylevel1`–`4`) and `rolepathways.php` (roles and their competencies, retire never delete, `pathway_courses_changed` for a role whose set changed); wired into the inspector, applier and drift · local_ltuse/classes/siteconfig/competencies.php, local_ltuse/classes/siteconfig/pathwaylevels.php, local_ltuse/classes/siteconfig/rolepathways.php, local_ltuse/classes/siteconfig/inspector.php, local_ltuse/classes/siteconfig/applier.php, local_ltuse/classes/siteconfig/drift.php
- [ ] **T014** [P] pytest: competency slug and url (42, unique, host from mkdocs.yml), `levels` exactly 1–4 verbatim, `pathways.yaml` (empty ok; bad key, unknown or Meta competency, duplicate, level word in name refused) · tests/test_site_config.py
- [ ] **T015** [P] Harness for `builder` and `viewer`: level layout, no-course rows, next, done, two courses at one level, a course in two competencies, role totals counted once, every `may_view` case and a missing fact · tests/pathway_harness.php
- [ ] **T016** [P] pytest: every string in the "Spec 006: pathways" block passes `cbc_wording.report_label_problems(strict=True)` (R13, SC-004) · tests/test_pathway_wording.py

**⟶ Wait for Wave 2 to finish, then:**

- [ ] **T017** Privacy provider declares `local_ltuse_pathway_cohort.usermodified`; export lists a user's assignments, deletion sets it to 0 · local_ltuse/classes/privacy/provider.php

**Checkpoint**: tables, membership, layout, access and site config exist and are tested.

## Phase 3: User Story 1 - A learner follows a competency pathway (P1) MVP

Files: local_ltuse/pathways.php, local_ltuse/templates/pathways.mustache, local_ltuse/templates/pathway.mustache, local_ltuse/classes/hook_callbacks.php, local_ltuse/db/mobile.php, local_ltuse/classes/output/mobile.php, local_ltuse/templates/mobile_pathways.mustache

**Goal**: a learner sees their pathways, browses every competency pathway, and sees completed, next and remaining courses.

**Independent Test**: quickstart V1–V3, V10, V11.

**Wave 1 — independent (different files):**

- [ ] **T018** [P] [US1] Pathways page: own list, `key`, `browse`, `userid` with `viewer::may_view()` (contracts/pages.md) (FR-009, FR-012) · local_ltuse/pathways.php
- [ ] **T019** [P] [US1] List and pathway templates; next course marked `cx-pathway-next`; levels only as "Aims at" and row headings · local_ltuse/templates/pathways.mustache, local_ltuse/templates/pathway.mustache
- [ ] **T020** [P] [US1] Primary navigation: `local_ltuse_pathways` for every signed-in non-guest, and `local_ltuse_pathways_manage` when `may_assign()` holds for some cohort · local_ltuse/classes/hook_callbacks.php
- [ ] **T021** [P] [US1] App handler `pathways` (`CoreMainMenuDelegate`), `pathways_init`, `pathways_view`, and its template, own pathways only (FR-015) · local_ltuse/db/mobile.php, local_ltuse/classes/output/mobile.php, local_ltuse/templates/mobile_pathways.mustache

**Checkpoint**: with courses mapped and delivered, a learner can follow a pathway in the browser and the app.

## Phase 4: User Story 2 - Pathways update themselves when courses are published (P1)

Files: local_ltuse/classes/external/set_course_pathway.php, local_ltuse/db/services.php, scripts/publish_moodle.py, scripts/check_moodle_payload.py, tests/test_publish_moodle.py

**Goal**: every publish writes the course's delivery state and level, and pathways follow.

**Independent Test**: quickstart V4–V6.

### Tests

- [ ] **T022** [P] [US2] pytest, written first: `ensure_pathway` is called after the competency map with `delivery` and `targetlevel`; dry run sends nothing; a delivered course with no level exits 1; a read-back mismatch exits 1; `check_moodle_payload` refuses a level not in `outcome-levels.yaml` · tests/test_publish_moodle.py

### Implementation

**Wave 1 — independent (different files):**

- [ ] **T023** [P] [US2] `local_ltuse_set_course_pathway`: validate, upsert, compute keys through `catalogue`, store `pathwaykeys`, fire `pathway_courses_changed` per key joined or left after commit, return the read-back (contracts/publish.md) (FR-002, FR-005, FR-006) · local_ltuse/classes/external/set_course_pathway.php
- [ ] **T024** [P] [US2] Register the function in the `ltuse_publish` service, in a "Spec 006" block · local_ltuse/db/services.php
- [ ] **T025** [P] [US2] Publisher: `ensure_pathway()` after `ensure_competencies()`, print lines and exit-1 cases of contracts/publish.md (SC-005) · scripts/publish_moodle.py
- [ ] **T026** [P] [US2] Payload gate: a present `target_outcome_level` must be a `course_target_levels` label, verbatim · scripts/check_moodle_payload.py

**Checkpoint**: a republish moves a course between pathways with no admin step.

## Phase 5: User Story 3 - A learner follows a role pathway (P2)

Files: none of its own. The role declaration, its apply and its view are built in Phase 2 (T005, T010, T011, T013) and shown by Phase 3's pages. Role pathways ship with `roles: []` (R5).

**Independent Test**: quickstart V12, on a scratch declaration that is never merged.

- [ ] **T027** [US3] Run T014's and T015's role cases and confirm a role key renders through `pathways.php?key=role:<key>` in a local harness run; record the result in the task note. No file changes · (verification only)

## Phase 6: User Story 4 - A mentor sees a learner's pathway progress (P2)

Files: local_ltuse/templates/mentoring.mustache, local_ltuse/templates/mobile_mentoring.mustache

**Independent Test**: quickstart V7.

- [ ] **T028** [P] [US4] Each mentee row links to `/local/ltuse/pathways.php?userid=<id>` in the browser, and names the Pathways menu in the app (FR-012) · local_ltuse/templates/mentoring.mustache, local_ltuse/templates/mobile_mentoring.mustache

## Phase 7: User Story 5 - A manager assigns a pathway and follows their organisation's progress (P3)

Files: local_ltuse/pathways_manage.php, local_ltuse/templates/pathways_manage.mustache, local_ltuse/db/events.php, local_ltuse/classes/observer.php

**Independent Test**: quickstart V8.

**Wave 1 — independent (different files):**

- [ ] **T029** [P] [US5] Assign page: cohorts `may_assign()` allows, assign and unassign with sesskey, progress table of members with `pathway:roletotal` and links (FR-012, FR-013) · local_ltuse/pathways_manage.php
- [ ] **T030** [P] [US5] Its template · local_ltuse/templates/pathways_manage.mustache
- [ ] **T031** [P] [US5] `\core\event\cohort_deleted` observer calling `assignments::unassign()` for each of the cohort's rows · local_ltuse/db/events.php, local_ltuse/classes/observer.php

**Checkpoint**: every story works on its own.

## Phase 8: Polish

**Wave 1 — independent (different files):**

- [ ] **T032** [P] CI runs `tests/pathway_harness.php` and `tests/test_pathway_wording.py` · .github/workflows/site-config.yml
- [ ] **T033** [P] Plugin README: pathways, the 008 seam, the core tables read (`course`, `course_completions`, `cohort`, `cohort_members`, `user_enrolments`, `enrol`) and why (Principle XI) · local_ltuse/README.md
- [ ] **T034** [P] Site README: `pathways.yaml`, adding a role, retiring a course by hiding it · moodle/site/README.md
- [ ] **T035** [P] Row 12: built, with what is still to verify on the instance (quickstart, SC-003) · moodle/REQUIREMENTS.md

**⟶ Wait for Wave 1 to finish, then:**

- [ ] **T036** Validate against the Success Criteria: run `pytest -q tests/`, every `php tests/*_harness.php`, `python scripts/site_config.py validate`, `python scripts/gen_coverage.py` (unchanged) and `php -l` on every changed PHP file · (suite run)

## Dependencies & Execution Order

- Setup (T001–T002) → Foundational → stories → Polish.
- Foundational: Wave 1 (T003–T011) → Wave 2 (T012–T016) → T017.
- US1 (T018–T021) and US2 (T022–T026) depend only on Foundational and own disjoint files, so they can run in parallel. US3 (T027) needs Phase 2 and T018. US4 (T028) needs T018. US5 (T029–T031) needs Phase 2.
- Polish: Wave 1 (T032–T035) → T036.
