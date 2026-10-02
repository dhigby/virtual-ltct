# Tasks: Mentor relationship and visibility

**Input**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/declaration.md](contracts/declaration.md), [contracts/local-ltuse.md](contracts/local-ltuse.md), [quickstart.md](quickstart.md)

**Tests**: the plan asks for:
- `pytest` cases for `site_config.py`'s new validation;
- PHP harnesses for the three pure decisions, run without Moodle like `tests/profile_access_harness.php`.

Each test task is written to fail first. Moodle behaviour is verified with the quickstart scenarios on the temporary 5.2.3+ instance (`ssh ltuse`, Moodle at `/home/ltuse/moodle`), with test accounts only (constitution III, X). That host carries other people's live sites:
- no task touches anything outside `/home/ltuse`;
- nothing runs PHPUnit there;
- `local_ltuse` is installed by copying the plugin and running `admin/cli/upgrade.php`, as `moodle/local_ltuse/README.md` "Install" describes.

**Keep test data and evidence out of the repo tree.** GitDoc commits and pushes any edit in the repo about five minutes after it is made.
- **Test accounts.** They are named `ltct-test-*`, and T040 deletes every one at the end.
- **Test organisations.** Anything that needs them runs against a copy of `moodle/site/` in the session scratchpad (`site_config.py ... --site-dir <copy>`).
- **Evidence.** Apply and drift output, CLI output and checklist notes are saved outside the repo. They hold no names, and the CLI prints counts only.

**Before any Moodle API is used, look it up** (constitution XI). Query Context7 `/websites/moodledev_io_5_2_apis` first. Then confirm the signature in upstream source on `MOODLE_502_STABLE` (files under `public/`). The research names each API to use. Do not write one from memory.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: US1–US4, from [spec.md](spec.md)

---

## Phase 1: Setup (source research the build depends on)

These three can be settled from source alone, and every later task relies on their answers. Record each answer in [research.md](research.md) under a new heading, "Source results", citing the file and line.

- [X] T001 [P] Confirm the per-user primary-navigation extension point on `MOODLE_502_STABLE`:
  - whether `\core\hook\navigation\primary_extend` exists, its namespace, and how a plugin registers a callback (`db/hooks.php`);
  - how the callback adds a node (`add()` on `primary_extend::get_primaryview()` or similar).

  If the hook does not exist, record the fallback, `local_ltuse_extend_navigation()` with a user-menu item, and its signature (instance research task 3) · specs/003-mentor-role/research.md
- [X] T002 [P] Confirm the Moodle app site-plugin contract for a main-menu handler, from moodleapp `main` source and the Moodle mobile developer docs:
  - the `db/mobile.php` array shape;
  - the `CoreMainMenuDelegate` `displaydata` keys (`title`, `icon`);
  - how a handler is hidden per user (`restrict`, `init` JS, or a `disabled` flag);
  - the return shape of a mobile output method (`templates`, `javascript`, `otherdata`).

  Correct [contracts/local-ltuse.md](contracts/local-ltuse.md) "Moodle app" if it differs (research R4) · specs/003-mentor-role/research.md
- [X] T003 [P] Confirm in `MOODLE_502_STABLE` source:
  - the config name and plugin of the course default "Show activity reports" (expected `moodlecourse | showreports`, `admin/settings/courses.php`);
  - the signatures of `core_role_set_assign_allowed()`, `\core_message\api::add_contact()`, `remove_contact()`, `is_contact()`, `\core_completion\progress::get_course_progress_percentage()` and `enrol_get_all_users_courses()`.

  Correct [contracts/declaration.md](contracts/declaration.md) if the setting name differs · specs/003-mentor-role/research.md

**Checkpoint**: every extension point and API the build uses is confirmed on our branch.

---

## Phase 2: Foundational (the role, its declaration and its guard)

The role and its capability serve every story. No story starts until this phase is done.

**Wave 1, independent (different files):**

- [X] T004 [P] Add the capability `local/ltuse:viewmenteeprogress` to `db/access.php` exactly as contracts/local-ltuse.md states: `riskbitmask RISK_PERSONAL`, `captype read`, `contextlevel CONTEXT_USER`, `archetypes []`.
  - Add its `ltuse:viewmenteeprogress` string to `lang/en/local_ltuse.php`.
  - Bump `$plugin->version` in `version.php`, with a comment naming spec 003, and the same stamp in `moodle/site/site.yaml`'s `local_ltuse` pin. `validate` fails if they differ.

  Files: moodle/local_ltuse/db/access.php, moodle/local_ltuse/lang/en/local_ltuse.php, moodle/local_ltuse/version.php, moodle/site/site.yaml
- [X] T005 [P] Write failing-first cases. Each asserts pass or fail, never message wording.
  - **`mentor` role**:
    - passes with the three allowlisted capabilities;
    - fails with `moodle/user:editprofile`, with `moodle/competency:usercompetencyrate`, with any `prohibit`, with `contextlevels: [course, user]`, and with a non-empty `archetype`.
  - **`allowassign`**:
    - `manager: allowassign: [mentor]` passes;
    - an unknown role name fails, a duplicate fails, and `allowassign` on `orgmanager` or on `mentor` fails;
    - the rendered payload carries `allowassign`, and `[]` when absent.
  - **`settings/mentoring.yaml`** validates.

  File: tests/test_site_config.py
- [X] T006 [P] Teach `site_config.py` the two new rules from contracts/declaration.md:
  - **`_check_mentor(role, problems)`**: `contextlevels` "must be exactly `[user]`", `archetype` must be `""`, no `prohibit`, and capabilities only from `MENTOR_ALLOW = {moodle/user:viewdetails, moodle/user:viewuseractivitiesreport, local/ltuse:viewmenteeprogress}`. Add a comment pointing at research R2 and R11 for how the allowlist may be widened.
  - **The `allowassign` key on any role**: a list of declared role shortnames or core archetype shortnames, with no duplicates. It is forbidden on `orgmanager` and `mentor`. Render it into each role's payload, with `[]` when absent.

  Call `_check_mentor` beside `_check_orgmanager` (around `scripts/site_config.py:744`). File: scripts/site_config.py
- [X] T007 [P] Support `allowassign` in the PHP siteconfig classes:
  - **Inspector**: read the `role_allow_assign` rows for declared pairs only.
  - **Applier**: for each declared pair with no row, call `core_role_set_assign_allowed($fromroleid, $targetroleid)`. Run this after roles are created, so a new `mentor` exists first.
  - **Drift**: report a missing declared pair as `changed` on the role, and never report undeclared rows (research R6).
  - **Report**: add nothing new, since the pair is a role property.

  Files: moodle/local_ltuse/classes/siteconfig/inspector.php, applier.php, drift.php
- [X] T008 [P] Create `settings/mentoring.yaml`, as contracts/declaration.md writes it: `rows: [11]`, `showreports` (plugin `moodlecourse`, or the name T003 confirmed) = `0`, with its `why`. File: moodle/site/settings/mentoring.yaml

**⟶ Wait for Wave 1 to finish, then:**

- [X] T009 Declare the role in `roles.yaml`:
  - Add the `mentor` role exactly as contracts/declaration.md shows: name, description, `archetype: ""`, `contextlevels: [user]`, the three capabilities with comments, and a `why` citing #11 with FR-003, FR-005, FR-006 and FR-013.
  - Add `allowassign: [mentor]` to the existing `manager` entry.
  - Run `python scripts/site_config.py validate` and `python -m pytest tests/test_site_config.py -q`. Both must pass.

  File: moodle/site/roles.yaml
- [ ] T010 Install and apply on the instance:
  - Install the bumped `local_ltuse` (README "Install").
  - Run `site_config.py apply`, then `drift`, which must be clean (quickstart A1).
  - Create the test accounts the quickstart lists, `ltct-test-*`, and the test courses C1–C3, with `ltct-test-learner-1` and `ltct-test-mentor-1` sharing no course.
  - As `ltct-test-siteteam`, assign `ltct-test-mentor-1` to `ltct-test-learner-1` through "Assign roles relative to this user". Confirm that Mentor is offered and that assignment and removal take effect on the mentor's next page load (instance research task 1).

  Record the result under a new "Instance results" heading · specs/003-mentor-role/research.md

**Checkpoint**: the role exists on the server exactly as declared, the site team can assign it, and drift is clean.

---

## Phase 3: User Story 1: A mentor sees an assigned learner's progress across all their courses (Priority: P1) 🎯 MVP

**Goal**: a mentor opens Mentoring, in the browser or the app, and sees each assigned learner's courses and completion, including courses joined later and courses finished long ago, and nobody else's. A learner sees their own mentors there too (FR-010).

**Independent test**: quickstart A3–A8, A11–A13, A16 and A17. The site team does the assigning (T010).

**Wave 1, independent:**

- [X] T011 [P] [US1] Write a failing-first harness for `\local_ltuse\mentoring::progress_status()`. Cover every row of data-model.md "Progress view":
  - completion not enabled → *Completion not tracked*;
  - complete → *Completed on <timecompleted>*;
  - percentage 0 or null → *Not started*;
  - 1–99 → *In progress, N%*;
  - an enrolment removed but a completed row present → *Completed*.

  Also cover the course ordering rule: in progress, then not started, then completed, newest first. File: tests/mentoring_harness.php
- [X] T012 [P] [US1] Add a failing-first case to the profile-hook harness: "manager of A, mentor of a learner in B" → `VIEWPROFILE_DO_NOT_PREVENT` with `$viewerismentor = true`, and `PREVENT` with it false (research R9). File: tests/profile_access_harness.php

**⟶ Wait for Wave 1, then Wave 2 (independent):**

- [X] T013 [P] [US1] Create `classes/mentoring.php`:
  - **`progress_status()` and the ordering**, as pure static functions that make T011 pass.
  - **`for_user(int $userid): array`**:
    - **Learners**: the viewer's `mentor` assignments in `CONTEXT_USER`, read from `role_assignments` joined to `context` by indexed `userid`. Keep each learner only if `has_capability('local/ltuse:viewmenteeprogress', context_user::instance($learnerid))`. FR-005 rests on this check, not on the query.
    - **Each learner's courses**: `enrol_get_all_users_courses($learnerid, false)`, plus courses with a `course_completions` row whose `timecompleted` is set (indexed by `userid`).
    - **Each course's status**: from `completion_info` and `\core_completion\progress::get_course_progress_percentage()`.
    - **Links**: profile, Grades overview (`/grade/report/overview/index.php?id=SITEID&userid=`) and Message.
    - **Mentors**: `get_role_users($mentorroleid, context_user::instance($userid))`.

  Look the `mentor` role up by shortname, and return an empty array if it is missing. Never include quiz attempts, submissions, logs or hidden profile fields. File: moodle/local_ltuse/classes/mentoring.php
- [X] T014 [P] [US1] Make the profile hook exempt mentors (research R9):
  - Add `bool $viewerismentor` as the sixth argument of `profile_access::decide()`, returning `VIEWPROFILE_DO_NOT_PREVENT` when it is true. Update the docblock.
  - In `local_ltuse_control_view_profile()`, pass `has_capability('local/ltuse:viewmenteeprogress', $context)`.
  - Keep the `viewalldetails` exemption, and keep never returning `FORCE_ALLOW`.
  - Run T012's harness.

  Files: moodle/local_ltuse/classes/profile_access.php, moodle/local_ltuse/lib.php

**⟶ Wait for T013, then Wave 3 (independent):**

- [X] T015 [P] [US1] Add the browser page and its template:
  - **`mentoring.php`**: `require_login()`, system context, page title "Mentoring", rendering `for_user($USER->id)`.
  - **`templates/mentoring.mustache`**: two sections, "Learners you mentor" and "Your mentors", each shown only when non-empty, plus the empty state "Nobody is linked to you as a mentor or learner yet."
  - **Layout**: phone-width, with no tables wider than the screen (SC-001).
  - **Strings**: add every one to the lang file, with no CBC level vocabulary and no "certified" (FR-013).

  Files: moodle/local_ltuse/mentoring.php, moodle/local_ltuse/templates/mentoring.mustache, moodle/local_ltuse/lang/en/local_ltuse.php
- [X] T016 [P] [US1] Add navigation in `lib.php` (and `db/hooks.php` if T001 confirmed the hook):
  - **Primary navigation**: a "Mentoring" item to `/local/ltuse/mentoring.php`, only when the user has a mentor or a learner. Use a cheap existence query, cached per request.
  - **`local_ltuse_myprofile_navigation($tree, $user, $iscurrentuser, $course)`**:
    - on your own profile, a "Your mentors" node when you have any;
    - on a learner's profile, a "Mentoring" node when the viewer mentors them.

  Files: moodle/local_ltuse/lib.php, moodle/local_ltuse/db/hooks.php
- [X] T017 [P] [US1] Add the app handler, using the contract T002 confirmed:
  - **`db/mobile.php`**: one `CoreMainMenuDelegate` handler, "Mentoring".
  - **`classes/output/mobile.php`**: `mentoring_view(array $args)`, which returns `templates/mobile_mentoring.mustache` rendered from `for_user()`. Hide the handler, or show the empty state, for users with no relationship.
  - **Strings**: add the mobile string keys.

  Files: moodle/local_ltuse/db/mobile.php, moodle/local_ltuse/classes/output/mobile.php, moodle/local_ltuse/templates/mobile_mentoring.mustache, moodle/local_ltuse/lang/en/local_ltuse.php

**⟶ Wait for Wave 3, then:**

- [ ] T018 [US1] Bump `version.php` and the `site.yaml` pin again if T004's stamp has already been installed. Install on the instance and run quickstart A3–A8, A11–A13, A16 and A17 in the browser and the Android app. Settle instance research tasks 2, 3, 5 and 6. Record pass or fail per scenario under "Instance results" (FR-003, FR-004, FR-005, FR-006, FR-010, FR-013, SC-001, SC-002, SC-004) · specs/003-mentor-role/research.md

**Checkpoint**: US1 works on its own. A site-team-assigned mentor sees progress on a phone, and sees no one else.

---

## Phase 4: User Story 2: Mentor and learner stay in contact (Priority: P2)

**Goal**: an assigned mentor and learner can message each other in the browser and the app with no shared course, whatever the learner's privacy preference. A learner's block still wins (research R5).

**Independent test**: quickstart A9 and A10, and the contact part of A14.

**Wave 1, independent:**

- [X] T019 [P] [US2] Add the table `local_ltuse_mentor_contact` exactly as data-model.md defines it:
  - columns: `id` int; `mentorid` int, FK `user.id`, indexed; `learnerid` int, FK `user.id`, indexed, "unique with `mentorid`"; `timecreated` int;
  - in `db/install.xml`, with a `db/upgrade.php` step that creates it at T004's or T018's version stamp, through `xmldb` (`$dbman->create_table`).

  Files: moodle/local_ltuse/db/install.xml, moodle/local_ltuse/db/upgrade.php
- [X] T020 [P] [US2] Add the privacy provider as contracts/local-ltuse.md states:
  - implement metadata `provider`, `request\plugin\provider` and `core_userlist_provider`;
  - declare `local_ltuse_mentor_contact`;
  - export and delete rows where the user is mentor or learner, in `CONTEXT_USER`;
  - add the `privacy:metadata:*` strings.

  Files: moodle/local_ltuse/classes/privacy/provider.php, moodle/local_ltuse/lang/en/local_ltuse.php

**⟶ Wait for T019, then:**

- [X] T021 [US2] Add the observers in `classes/observer.php` and `db/events.php`, following contracts/local-ltuse.md's "Observer" table:
  - **`role_assigned`** (`mentor` role, `CONTEXT_USER`, learner ≠ mentor): if `!is_contact()`, `add_contact(mentor, learner)` plus a row.
  - **`role_unassigned`**: if the plugin made the contact and no `mentor` assignment remains for the pair, `remove_contact()` and delete the row.
  - **`user_deleted`**: delete the user's rows and remove those contacts.

  Never call `unblock_user()`, and never throw into core: `debugging()` on error. Files: moodle/local_ltuse/classes/observer.php, moodle/local_ltuse/db/events.php
- [X] T022 [US2] Add `cli/mentor_contacts.php --sync`. For every `mentor` assignment it ensures the contact and its row exist. It is idempotent and prints counts only, never a name (Principle III). File: moodle/local_ltuse/cli/mentor_contacts.php

**⟶ Wait for T020–T022, then:**

- [ ] T023 [US2] Bump the version and install on the instance, then run `--sync` once. Run these in the browser and the Android app:
  - quickstart A9: `ltct-test-learner-1` on "My contacts only", with no shared course, messages both ways;
  - A10: a block stops the mentor;
  - the contact part of A14: unassigning removes the plugin's contact, while a contact the pair made themselves stays.

  Settle instance research task 4, and record under "Instance results" (FR-007) · specs/003-mentor-role/research.md

**Checkpoint**: US1 and US2 work together; contact begins and ends with the relationship.

---

## Phase 5: User Story 3: The relationship is assigned and ended without an LMS administrator (Priority: P3)

**Goal**: the site team assigns, reassigns and ends relationships on core's page (Phase A). Organisation managers do it for their own learners only on one page of ours, and only if the maintainer approves Phase B (research R7).

**Independent test**: quickstart A2, A14 and A15 (Phase A); B1–B5 (Phase B).

### Phase A: site team (no gate)

- [X] T024 [P] [US3] Add `--end-all --mentor=<username>` to `cli/mentor_contacts.php`:
  - it calls `role_unassign_all(['userid' => <id>, 'roleid' => <mentor id>])`, so the T021 observers remove the contacts;
  - it asks for confirmation unless `--yes`, and prints counts only.

  File: moodle/local_ltuse/cli/mentor_contacts.php
- [X] T025 [P] [US3] Add a section to the site README: "Mentors: assigning and ending a relationship".
  - **Steps**: the core steps from research R6, from the learner's profile through Preferences to "Assign roles relative to this user".
  - **Rules**: a mentor may be from any organisation; reassigning is remove then add; who mentors whom is never written in the repo (FR-012).
  - **Ending all of a mentor's relationships**: the `--end-all` CLI.
  - **Bulk assignment**: spec 008's.

  File: moodle/site/README.md
- [ ] T026 [US3] Run quickstart A2, A14 and A15 on the instance:
  - assign in under two minutes;
  - after ending, the mentor loses the view on the next load, and the learner's records are unchanged;
  - `--end-all` ends both relationships and prints counts only.

  Record under "Instance results" (FR-008 site team, FR-009) · specs/003-mentor-role/research.md

### Phase B: organisation managers (GATED)

- [ ] T027 [US3] **Gate.** Ask the maintainer to decide whether organisation managers may assign mentors for their own organisation's learners. Give the context:
  - it reverses, for mentors only, spec 002's 2026-10-01 decline of manager self-service;
  - the recommended design is research R7.

  Record the decision, dated, in `INTENT.md` Decisions, and update the "Should partner managers enrol their own learners?" open question. **If declined**: skip T028–T033, amend spec FR-008 and SC-003 to the site team, and add the manager case to spec 008's scope in the same PR. Files: INTENT.md, specs/003-mentor-role/spec.md

**⟶ Only if T027 approves:**

- [ ] T028 [P] [US3] Write a failing-first harness for `\local_ltuse\mentor_admin::decide(bool $isself, bool $learnerexists, bool $canassigncore, array $managedkeys, string $learnerorg): bool`, covering the contract's cases:
  - the site team is allowed;
  - a manager of the learner's organisation is allowed;
  - self, a missing or deleted learner, an empty `ltct_org` and another organisation are each refused.

  File: tests/mentor_admin_harness.php
- [ ] T029 [P] [US3] Declare the `ltct:mentors` cohort (`mentors_cohort: {idnumber: ltct:mentors, name: Mentors, visible: false}`):
  - teach `site_config.py` to validate the key and render it with spec 002's cohort item type;
  - add a test case;
  - confirm that `apply` and `drift` need no PHP change.

  Files: moodle/site/organisations.yaml, scripts/site_config.py, tests/test_site_config.py

**⟶ Wait for T028, then:**

- [ ] T030 [US3] Create `classes/mentor_admin.php` with the pure `decide()` that makes T028 pass. Its inputs are gathered in the page, not in the class. File: moodle/local_ltuse/classes/mentor_admin.php
- [ ] T031 [US3] Build the "Manage mentors" page, `mentors.php?userid=<learner>`:
  - **Access**: `require_login()`. Recompute `decide()` on every GET and POST:
    - `$canassigncore`: `has_capability('moodle/role:assign', $learnerctx)` and `mentor` is in `get_assignable_roles($learnerctx)`;
    - `$managedkeys`: `local_ltuse_managed_organisation_keys($USER->id)`;
    - `$learnerorg`: `profile_user_record()`.
  - **Writes**: `confirm_sesskey()`, then `role_assign()` or `role_unassign()`.
  - **Picker**: members of `ltct:mentors` only, excluding the learner and existing mentors.
  - **Profile link**: add "Manage mentors" to `local_ltuse_myprofile_navigation()` when `decide()` allows.
  - **Strings**: add them to the lang file.

  Files: moodle/local_ltuse/mentors.php, moodle/local_ltuse/lib.php, moodle/local_ltuse/lang/en/local_ltuse.php
- [ ] T032 [US3] Bump the version, install, and run quickstart B1–B5 on the instance with `ltct-test-orgmgr-a`, B5 being the forged POST. Time B1 against SC-003's two minutes. Record under "Instance results" (FR-008 manager part, US3-1 to US3-4) · specs/003-mentor-role/research.md
- [ ] T033 [US3] Record the Phase B page in the plugin README under its own heading: its authorisation, and that it reads `cohort` ⋈ `cohort_members` through the existing `local_ltuse_managed_organisation_keys()`. File: moodle/local_ltuse/README.md

**Checkpoint**: relationships are assigned and ended by the site team, and by organisation managers if Phase B was approved.

---

## Phase 6: User Story 4: A mentor responds to a learner's work (Priority: P4)

**Goal**: where a course asks for work, the learner's mentor reads it and leaves feedback. This comes through spec 012's Course mentor role, not the user-context role (research R8).

**Independent test**: one test course with a submission activity.

- [X] T034 [P] [US4] Correct spec 012's plan. Its "Cross-spec effects" sentence, "Until then the organisation manager enrols the mentor with the cohort", becomes "Until then the site team enrols the mentor as Course mentor with the organisation's group (spec 002 decision 2026-10-01; spec 003 research R8)". File: specs/012-assignments-peer-review/plan.md
- [ ] T035 [US4] On the instance:
  - In test course C2, use a hand-made core assignment if spec 012's publisher does not yet ship assignments.
  - The site team enrols `ltct-test-mentor-1` as Course mentor (`teacher`) in organisation A's group.
  - `ltct-test-learner-1` submits.
  - The mentor reads the submission and leaves feedback; the learner sees it (US4-1, US4-2).
  - The user-context `mentor` role alone still cannot open the submission (A7).

  Record under "Instance results" (FR-011) · specs/003-mentor-role/research.md

**Checkpoint**: all four stories are verified.

---

## Phase 7: Polish & cross-cutting

- [X] T036 [P] Document in the plugin README:
  - the capability;
  - the Mentoring page and app handler;
  - the observers and the `local_ltuse_mentor_contact` table;
  - the CLI;
  - the profile-hook change;
  - each raw read with its reason: `role_assignments` ⋈ `context` by `userid`, and `course_completions` by `userid` (constitution XI).

  File: moodle/local_ltuse/README.md
- [X] T037 [P] Update row #11 in `moodle/REQUIREMENTS.md`:
  - set it to "Built (spec 003), <date>; verified when A1–A17 pass; done when SC-005's real mentors and managers have used it", naming what was built;
  - leave rows #8 and #15 unchanged unless Phase B shipped.

  File: moodle/REQUIREMENTS.md (constitution X)
- [X] T038 [P] Update the spec text the plan names:
  - FR-007: "regardless of shared enrolment or the learner's messaging privacy preference; a learner's block of one person still applies (research R5)";
  - FR-006's deferral: "deferred to spec 008 (plan R8)";
  - FR-008 and SC-003, if T027 declined.

  File: specs/003-mentor-role/spec.md
- [ ] T039 Run every repo check, and fix anything they trip:
  - `python scripts/site_config.py validate`;
  - `python -m pytest tests -q`;
  - each PHP harness (`php tests/profile_access_harness.php`, `php tests/mentoring_harness.php`, and, if Phase B shipped, `php tests/mentor_admin_harness.php`);
  - the CI gates in the constitution (`gen_coverage.py`, `check_competency_descriptors.py`, `quiz_parse.py --check-all`).

  (verification only)
- [ ] T040 Clean up the instance:
  - Delete every `ltct-test-*` account, which also exercises the `user_deleted` observer, and test courses C1–C3.
  - Run `drift` and confirm it is clean.
  - Leak check: no instance output, name or screenshot is in the repo. Run `git grep -n "ltct-test-"`; it must match only the quickstart and tasks.

  (verification only)
- [ ] T041 SC-005: before row #11 is marked done, 2–3 real mentors and 2–3 real organisation managers use the relationship during a pilot. Record their findings, without names, in the delivering PR. This is not blocking for merge: row #11 stays "built" or "verified" until then (constitution X) · moodle/REQUIREMENTS.md

---

## Dependencies & execution order

```text
Phase 1 (T001–T003) ──► Phase 2 (T004–T010) ──┬──► US1 (T011–T018) 🎯 MVP
                                              ├──► US2 (T019–T023)
                                              ├──► US3 A (T024–T026; T024 needs T022's file)
                                              │      └──► T027 gate ──► US3 B (T028–T033)
                                              └──► US4 (T034–T035)
                     all stories done ──► Polish (T036–T041)
```

- **US1** depends only on Phase 2.
- **US2** depends on Phase 2. It is testable without US1 through core's own messaging UI, though A14 reads better after T018.
- **US3 Phase A**: T024 extends T022's file, so it runs after T022. T026 needs T021's observers for A14 and A15.
- **US3 Phase B** waits on T027 and nothing else.
- **US4** needs only Phase 2's role and spec 012's `teacher` declaration, which is already on `main`.
- **Version bumps**: T018, T023 and T032 each bump the stamp in `version.php` and the `site.yaml` pin together. When two land in one install, bump once.

## Parallel opportunities

- T001–T003 together (source lookups).
- T004–T008 together (five different files).
- US1 Wave 1 (T011, T012), then T013 ∥ T014, then T015 ∥ T016 ∥ T017.
- US2: T019 ∥ T020.
- Across stories: once Phase 2 is done, US1, US2, US3 Phase A docs (T025) and US4's T034 can proceed side by side.
- Polish: T036 ∥ T037 ∥ T038.

## Parallel example: User Story 1

```text
Wave 1:  T011 mentoring_harness.php        ∥  T012 profile_access_harness.php case
Wave 2:  T013 classes/mentoring.php        ∥  T014 profile_access.php + lib.php hook input
Wave 3:  T015 mentoring.php + template     ∥  T016 navigation  ∥  T017 db/mobile.php + output
Then:    T018 instance run
```

## Implementation strategy

- **MVP**: Phases 1–3. One role, site-team assignment and the Mentoring view in the browser and app satisfy row #11's core ("mentor can see assigned learners' progress").
- **Increment 2**: US2's contacts make the relationship two-way.
- **Increment 3**: US3 Phase A docs and CLI make it operable by the site team.
- **Increment 4**: Phase B only after the maintainer's decision; it is independent of everything else.
- **US4**: verification and a one-line correction in spec 012, so it can land with any increment.
