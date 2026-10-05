# Tasks: Completion badges and certificates

**Input**: Design documents from `specs/013-certificates-badges/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Included because spec.md defines Independent Test per user story with acceptance scenarios, plan.md §Testing requires pytest + PHPUnit + harness + instance checks V1–V14, and research.md marks Verify steps as blocking their stories. Instance checks (V1–V14) use test accounts only; evidence stays outside the repo (quickstart.md).

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- Publisher/scripts: `scripts/`
- Repo tests: `tests/`
- Site declarations: `moodle/site/`
- Plugin: `moodle/local_ltuse/`
- Process docs: `process/stages/`
- CI: `.github/workflows/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Declaration scaffolding, placeholder assets, and harness skeletons

- [x] T001 Create badge/certificate declaration scaffolding in `moodle/site/badges.yaml`, `moodle/site/settings/badges.yaml`, and `moodle/site/certificate/template.yaml`
- [x] T002 [P] Add placeholder badge artwork at `moodle/site/badges/completion.png` (square PNG, >=512px, <=256KB per data-model.md)
- [x] T003 [P] Add placeholder programme logo at `moodle/site/certificate/logo.png` (total template images <=100KB per data-model.md)
- [x] T004 Create wording module skeleton in `scripts/cbc_wording.py` (two named functions: 004 report rule + 013 `check_recognition`, never merged)
- [x] T005 [P] Create recognition test skeletons in `tests/test_cbc_wording.py`, `tests/test_payload_recognition.py`, and `tests/recognition_harness.php`
- [x] T006 Pin `mod_customcert` 5.2.9 and `availability_coursecompleted` v5.5.3 in `moodle/site/site.yaml` with sha256, marketplace URL, and `why` citing row #23

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Wording gate, validation/rendering, plugin table, and payload contract that ALL stories depend on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T007 Implement `check_recognition()` in `scripts/cbc_wording.py` (refuse `certif(y|ied|ies|ication|ications)`, `certificate of competenc`, `accredit`, retired level names, level-as-held phrases; require "training completed" or "completed the course"; allow "certificate"; `{target_level}` only after "designed to support progress towards")
- [x] T008 Move 004's FR-010 report rule into `scripts/cbc_wording.py` unchanged and import it from `scripts/site_config.py`
- [x] T009 [P] Extend `validate` in `scripts/site_config.py` for `moodle/site/badges.yaml` (placeholders only `{course}`/`{competencies}`/`{target_level}`/`{programme}`, image rules, rendered against every course title in `modules/*/README.md` + longest competency list)
- [x] T010 [P] Extend `validate` in `scripts/site_config.py` for `moodle/site/certificate/template.yaml` (element types limited to `text`/`studentname`/`coursename`/`date`/`code`/`qrcode`/`image`/`bgimage`, `date` must be `completion` mapping to `DATE_COMPLETION = -2`, exactly one `studentname`/`coursename`/`date`/`code`, font one of `freesans`/`freeserif`/`dejavusans`, images exist and total <=100KB)
- [x] T011 Add `recognition` payload block in `scripts/moodle_payload.py` (`{"recognition": {"delivery": true, "certificate": {"idnumber": "ltct:<slug>:certificate"}}}`, `delivery` from `course_stage.stage_for()`, `certificate` present only when `delivery` is true)
- [x] T012 Extend `scripts/check_moodle_payload.py` with the four recognition assertions (course start date not in future; rendered badge title passes `check_recognition()`; certificate idnumber fits 100 chars; no module in `sections` uses the certificate idnumber)
- [x] T013 Create `local_ltuse_course_badge` table via `moodle/local_ltuse/db/install.xml` and `moodle/local_ltuse/db/upgrade.php` (`courseid` unique, `badgeid` unique, `timecreated`; privacy provider stays `null_provider`)
- [x] T014 Update `moodle/site/ignore.yaml` (drop `badges_defaultissuername`/`badges_defaultissuercontact`, add `badges_badgesalt`) and `moodle/site/roles.yaml` (`user`: `moodle/badges:viewotherbadges: inherit`; `ltcpublisher`: badge `createbadge`/`configuredetails`/`configurecriteria`/`configuremessages` + `mod/customcert:manage`)

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Learner earns a completion badge (Priority: P1) ⭐ MVP

**Goal**: Finishing a published course automatically issues a course badge naming the course, visible on profile and in app

**Independent Test**: On the temporary instance, complete a published course with a test account and confirm the badge is issued automatically, is visible on the profile and in the app, and its wording passes FR-004 (quickstart V4; R3 Verify blocks this story)

### Tests for User Story 1

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [x] T015 [P] [US1] Add PHPUnit badge-service tests in `moodle/local_ltuse/tests/recognition_test.php` (create/reword/activate on synthetic data; never-deactivate invariant)
- [x] T016 [P] [US1] Extend pure-piece harness in `tests/recognition_harness.php` (template+course-fields rendering, deny-list check, availability JSON)

### Implementation for User Story 1

- [x] T017 [US1] Implement badge text renderer in `moodle/local_ltuse/classes/recognition/renderer.php` (template + course full name + `ltct_competencies`/`ltct_target_level` fields; `[A] [B]` → `A, B`; `issuerurl` from `$CFG->wwwroot` scheme+host, never a literal host)
- [x] T018 [US1] Implement badge-side wording guard in `moodle/local_ltuse/classes/recognition/wording.php` (apply deny patterns stored at apply; refusal writes nothing for that badge)
- [x] T019 [US1] Implement badge lifecycle in `moodle/local_ltuse/classes/recognition/badges.php` (create via `badge::create_badge` + `award_criteria` overall ALL + course criterion `course_<id>`; reword in place via `save()`/`update_message()`; `attachment = 0`, `notification = BADGE_MESSAGE_NEVER`; activate via `set_status(ACTIVE)` on delivery only; never deactivate/archive/delete)
- [x] T020 [US1] Implement badge template store + re-render in `moodle/local_ltuse/classes/siteconfig/badgetemplate.php` (config + file area at system context; `apply` re-renders every mapped badge, reports `changed` per course idnumber)
- [x] T021 [US1] Wire badge publish path in `scripts/publish_moodle.py` (call `local_ltuse_set_course_recognition` after `set_course_completion`; print `recognition  badge …` line incl. pilot line; warnings → `problems` + exit 1; `certificate` idnumber in `this_run`; `--dry-run` sends nothing)
- [ ] T022 [US1] Run instance checks V3 (pilot issues nothing), V4 (delivery + auto-issue incl. suspended-pilot-learner + R4 `get_enrolled_sql(onlyactive)` confirm), V5 (partial completion) from `specs/013-certificates-badges/quickstart.md` and record results in the PR

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Learner downloads a certificate (Priority: P2)

**Goal**: A completed learner downloads a "training completed" certificate with name, course, completion date, programme, and verification code

**Independent Test**: Complete a course with a test account, download the certificate on a phone over a slow connection, check wording + identity fields (quickstart V6/V7/V8; R7/R8/R9 Verify block this story)

### Tests for User Story 2

- [x] T023 [P] [US2] Add certificate payload tests in `tests/test_payload_recognition.py` (delivery flag, certificate idnumber block, the four `check_moodle_payload` assertions)
- [x] T024 [P] [US2] Add publisher recognition-call tests in `tests/test_publish_moodle.py` (call order after completion, warnings → exit 1, dry run, stale exclusion of certificate idnumber)

### Implementation for User Story 2

- [x] T025 [US2] Implement certificate site-template builder in `moodle/local_ltuse/classes/siteconfig/certtemplate.php` (find by exact name; `ambiguous` blocks on duplicates; set pages/elements from declaration)
- [x] T026 [US2] Implement certificate activity sync in `moodle/local_ltuse/classes/recognition/certificate.php` (create `customcert` module via `add_moduleinfo()` with cm idnumber `ltct:<slug>:certificate`; `verifyany = 1`; availability `{"op":"&","c":[{"type":"coursecompleted","id":"1"}],"showc":[true]}`; completion `0`; last section; copy via `template_load_service::replace()` when pages differ; never delete)
- [x] T027 [US2] Implement `local_ltuse_set_course_recognition` web service in `moodle/local_ltuse/classes/external/set_course_recognition.php` (params per contracts/publish.md; `recognition-not-applied` refusal; returns `badge`/`status`/`certificate`/`warnings` with codes `startdate-future`/`badge-extra`/`active-not-delivery`/`course-hidden`; never award counts/names/codes) and register it in `moodle/local_ltuse/db/services.php`
- [x] T028 [US2] Exclude certificate idnumber from completion wanted set in `moodle/local_ltuse/classes/external/set_course_completion.php`
- [x] T029 [US2] Declare certificate settings in `moodle/site/settings/badges.yaml` (`enablebadges = 1`, `badges_allowcoursebadges = 1`, `badges_allowexternalbackpack = 1`, `badges_defaultissuername`, `badges_defaultissuercontact: env:MOODLE_BADGE_CONTACT`, `customcert/verifyallcertificates = 1`)
- [ ] T030 [US2] Run instance checks V6 (certificate content incl. completion—not-issue date), V7 (app + offline on 009 V7 device at 256 kbit/s, non-Latin name), V8 (anonymous badge-hash + certificate-code verification, `forcelogin` on/off, hidden course) and record results in the PR

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - Assessor/mentor consults the evidence (Priority: P3)

**Goal**: Mentors see assigned learners' badges as "trained in" evidence, never as a level held; other-org managers cannot

**Independent Test**: With a test mentor account (spec 003) view an assigned learner's badges; confirm competencies shown as course description, no CBC level as reached (quickstart V9; R10 Verify blocks this story; full mentor check waits on spec 003's role)

- [x] T031 [US3] Record mentor-role requirement for spec 003 (`moodle/badges:viewotherbadges` granted in learner **user** context) in `moodle/local_ltuse/README.md` and `moodle/site/README.md`
- [x] T032 [US3] Confirm badge description renders competencies/target-level as course description only ("designed to support progress towards `<CBC label>`", CBC vocabulary exact per `outcome-levels.yaml`) in `moodle/local_ltuse/classes/recognition/renderer.php`
- [ ] T033 [US3] Run instance check V9 from `specs/013-certificates-badges/quickstart.md` (learner B1 refused A1's badges via profile + `core_badges_get_user_badges`; manager A refused/sees none; site team sees them; mentor checks deferred to spec 003) and record results in the PR

**Checkpoint**: US3 limited check passes; full mentor-matrix check re-runs once spec 003's role exists (plan cross-spec effects)

---

## Phase 6: User Story 4 - Maintainer rebuilds badges from the repo (Priority: P3)

**Goal**: Fresh instance recreates every badge design, criterion, and certificate template from the repo with no admin-UI clicking; reword applies without duplicates

**Independent Test**: Apply badge + certificate config to an empty test instance and compare against reference (quickstart V1/V13; V2/V10 block this story)

- [x] T034 [US4] Implement `apply`/`drift` arrays in `moodle/local_ltuse/classes/siteconfig/inspector.php`, `moodle/local_ltuse/classes/siteconfig/applier.php`, and `moodle/local_ltuse/classes/siteconfig/drift.php` (badge_template + certificate_template after 004's `reports`; `missing`/`changed`/`extra`/`ambiguous` per contracts/declaration.md; never judge active-ness; never deactivate/delete)
- [x] T035 [US4] Thread badge/certificate image base64 through `scripts/site_config.py` `apply` (temp-file handoff to `badges_process_badge_image()`; GD-absent loud failure) and document `mod_customcert` internal-class calls + why in `moodle/local_ltuse/README.md` (Principle XI)
- [ ] T036 [US4] Run instance checks V1 (apply idempotent + `drift` showing no differences other than the expected ones listed in quickstart.md; 2026-10-05: drift on ltuse.net after apply reported 16 differences, all expected, the test-a/test-b fixtures kept for these checks; V1 itself not yet run, still open), V2 (wording gate fails closed: `Certified`, `Level 3 - Independent achieved`, `Certification prep` title), V10 (reword keeps id + ACTIVE + award, hash page shows new text), V13 (empty-instance rebuild, compare `drift --json`), V14 (baked PNG + zip + privacy export) and record results in the PR
- [ ] T037 [US4] Run edge checks V11 (add lesson + republish: A1 keeps badge + certificate access) and V12 (hide course: hash + code still verify, My-certificates download works) and record results in the PR

**Checkpoint**: All user stories should now be independently functional

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Docs, gates, and acceptance criteria that span stories

- [x] T038 Bump `moodle/local_ltuse/version.php` (`requires`/`supported` at 5.2) and update `moodle/REQUIREMENTS.md` row #23 status on the delivering PR (constitution X)
- [x] T039 Update `process/stages/08-publish.md` (suspend pilot learner's manual enrolment before delivery publish; retire by hiding, never deleting) and `moodle/site/README.md` (badges + certificate; retire-by-hiding rule)
- [x] T040 Extend CI in `.github/workflows/site-config.yml` to run `tests/recognition_harness.php` and `scripts/cbc_wording.py` gates on every `moodle/site/` change
- [x] T041 [P] Add `pytest` coverage in `tests/test_cbc_wording.py` (both rules; "certificate" allowed, "certified" refused) and `tests/test_site_config.py` (badges.yaml + certificate validation)
- [ ] T042 Verify SC-004 (rebuild recreates 100% badges + template identically) and SC-005 (certificate <30s at 256 kbit/s, size recorded) with results in the PR
- [ ] T043 Hold SC-003 open until 2–3 real partner learners find + download the certificate unaided (e.g. stage-7 pilot → delivery) with findings recorded (constitution X); never mark US2 done before this

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User stories can then proceed in parallel (if staffed)
  - Or sequentially in priority order (P1 → P2 → P3)
- **Polish (Final Phase)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - Builds on `set_course_recognition` skeleton from US1; independently testable via V6–V8
- **User Story 3 (P3)**: Can start after Foundational (Phase 2) - Limited check now; full mentor matrix waits on spec 003's role (plan cross-spec effects)
- **User Story 4 (P3)**: Can start after Foundational (Phase 2) - Touches US1/US2 artefacts via `apply` re-render; independently testable via V1/V2/V10/V13

### Within Each User Story

- Tests (harness/PHPUnit/pytest) MUST be written and FAIL before implementation
- Renderer/wording pure pieces before lifecycle/service code
- Core implementation before integration
- Verify steps (R-Verify) block closing their story; results recorded in the PR, never committed
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel
- T009 and T010 (validate halves) can run in parallel within Phase 2
- Once Foundational phase completes, US1–US4 can start in parallel (if team capacity allows)
- T015 + T016 (US1 tests) can run in parallel; T023 + T024 (US2 tests) can run in parallel
- T041 pytest additions can run in parallel with T038–T040 docs/CI tasks

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together:
Task: "Add PHPUnit badge-service tests in moodle/local_ltuse/tests/recognition_test.php"
Task: "Extend pure-piece harness in tests/recognition_harness.php"
```

## Parallel Example: User Story 2

```bash
# Launch all tests for User Story 2 together:
Task: "Add certificate payload tests in tests/test_payload_recognition.py"
Task: "Add publisher recognition-call tests in tests/test_publish_moodle.py"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (badge create/reword/activate + publish path)
4. **STOP and VALIDATE**: Test User Story 1 independently (V3/V4/V5 + R3/R4 Verify)
5. Deploy/demo if ready

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently (V3–V5) → Deploy/Demo (MVP!)
3. Add User Story 2 → Test independently (V6–V8) → Deploy/Demo
4. Add User Story 3 → Limited check (V9) → full matrix after spec 003
5. Add User Story 4 → Rebuild/reword/edge checks (V1/V2/V10–V14)
6. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 (badge lifecycle)
   - Developer B: User Story 2 (certificate + web service)
   - Developer C: User Story 4 (apply/drift rebuild)
3. US3 limited check slots in anywhere after Foundational
4. Stories complete and integrate independently

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story is independently completable and testable via its quickstart checks
- Verify tests fail before implementing; instance evidence stays outside the repo (test-learner names)
- `apply`/`drift`/publisher report badges and activities by course idnumber, never awards, names, or codes (Principle III)
- Pending maintainer decisions (plan decisions 2–6): pilot-learner suspension, badge visibility, issuer details/placeholders (`validate` refuses placeholders on delivery), verify-page date display, baked-image reword limit
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
