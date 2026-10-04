# Tasks: Partner organisations, cohorts and profiles

> **Amended 2026-10-03 in the spec** (Areas and Area Language Technology Coordinators, [Clarifications 2026-10-03](spec.md)). This file is not yet redone for it; that happens in the plan step, before any build. Where this file disagrees with the 2026-10-03 Clarifications, the spec wins.

**Input**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/declaration.md](contracts/declaration.md)

Verification runs on the temporary 5.2.3+ instance (`ssh ltuse`, `https://ltuse.net`) with test accounts only (constitution X). That host carries other people's live sites, so no task touches anything outside `/home/ltuse`, and nothing runs PHPUnit there.

**Keep test data and evidence out of the repo tree.** GitDoc commits and pushes any edit in the repo about five minutes after it is made. So "local and uncommitted" is not safe here.
- **Test organisations.** They go only in a copy of `moodle/site/` in the session scratchpad. Every command that needs them runs as `python scripts/site_config.py <validate|apply|drift> --site-dir <scratch copy>`. The tracked `moodle/site/organisations.yaml` never holds a test key.
- **Test accounts.** They are named `ltct-test-*`, and every one is deleted at the end (T031).
- **Evidence.** Apply and drift output and checklist notes are saved outside the repo too. They hold no member list, no member count and no profile value.

All the configuration code lands in Phase 2, because one applier serves every story. Each story phase then proves its own acceptance scenarios on the instance. US2 also adds the profile hook it needs.

## Phase 1: Setup

Files: `specs/002-org-structure-cohorts/research.md`, `specs/002-org-structure-cohorts/contracts/declaration.md`

**Wave 1, a single task:**

- [x] **T001** Verify `tool_dynamic_cohorts`, following R4's Verify:
  - Pick the newest stable release, and record its `$plugin->version`, archive URL and sha256.
  - Do not run its PHPUnit suite. The maintainer decided on 2026-10-01 to rely on the hand checks, and R4 records the risk.
  - Check free disk on the instance, then install the release into `/home/ltuse/moodle`.
  - Using the probe field `ltct_t001_probe`, probe cohorts `ltct:probe:*` and two `ltct-test-*` accounts, run the hand checks and record the condition config keys used.
  - Delete every probe item and account.

  Record the outcome and the pin in research R4. If the plugin fails, uninstall it with `admin/cli/uninstall_plugins.php` and switch to the R4 fallback. Then, for `local_profilecohort`:
  - install its pinned release and record its `$plugin->version`, archive URL and sha256;
  - repeat the same probe hand checks;
  - record the table columns its rules use, and whether it sets cohort `component`;
  - record why the switch was made.

  specs/002-org-structure-cohorts/research.md

**⟶ Wait for Wave 1 to finish, then:**

- [x] **T002** Fill the "Condition config" table from the keys T001 recorded. If T001 chose the fallback, replace the table with the `local_profilecohort` columns, as the contract says · specs/002-org-structure-cohorts/contracts/declaration.md

**⟶ Wait for T002 to finish, then:**

- [x] **T003** Ask the maintainer for two lists and record them in research R5 and R6:
  - the `ltct_role` options;
  - the first partner organisations, each with a key and a display name, `independent` among them.

  These are reviewed choices, not something to invent · specs/002-org-structure-cohorts/research.md

**Checkpoint**: the cohort plugin is pinned or replaced by its fallback, and every value the declaration needs is known.

## Phase 2: Foundational

Files: `scripts/site_config.py`, `tests/test_site_config.py`, `.github/workflows/site-config.yml`, `moodle/local_ltuse/version.php`, `moodle/local_ltuse/classes/siteconfig/categories.php`, `moodle/local_ltuse/classes/siteconfig/cohorts.php`, `moodle/local_ltuse/classes/siteconfig/profilefields.php`, `moodle/local_ltuse/classes/siteconfig/cohortrules.php`, `moodle/local_ltuse/classes/siteconfig/report.php`, `moodle/local_ltuse/classes/siteconfig/inspector.php`, `moodle/local_ltuse/classes/siteconfig/applier.php`, `moodle/local_ltuse/classes/siteconfig/drift.php`, `moodle/site/site.yaml`, `moodle/site/settings/cohorts.yaml`, `moodle/site/profile-fields.yaml`, `moodle/site/organisations.yaml`

No story starts until this phase is done. Every PHP class calls only the APIs that research and the contract name, and looks each one up before using it. None writes another component's table (constitution XI).

**Wave 1, independent (different files):**

- [x] **T004** [P] Teach `site_config.py` the two new files.
  - **Files.** Add `organisations.yaml` and `profile-fields.yaml` to the top-level files. A field with `options_from: organisations` is an error when `organisations.yaml` is missing or has no organisations.
  - **Validation.** Check every rule in contracts/declaration.md and data-model.md:
    - the key pattern and its 30-character limit;
    - the required `independent`, `published`, `pilots` and `organisations` entries, and no shared key `org`;
    - `options` against `options_from`, and `ltct_org` being `locked: 1`;
    - `area` names verbatim against `competencies.yaml`, without `Meta`;
    - no `description` on a profile field, and no `why` on an organisation entry;
    - the `orgmanager` deny list, `moodle/user:viewalldetails` included, and no role shortname that contains an organisation key.
  - **Expansion.** Expand into the four payload arrays in the data model's shape: `categories` parents first, `cohorts`, `profile_fields` with `options_from` resolved, and `cohort_rules`.
  - **Render.** Make `render` show the four arrays.

  (FR-001, FR-002, FR-005, FR-008, FR-009, FR-013) · scripts/site_config.py
- [x] **T005** [P] Write a failing-first case for each rule T004 checks. Each case asserts whether `validate` passes or fails on a fixture, not the wording of its message, so it does not depend on how T004 phrases errors. Add one case for the expansion: one organisation becomes one category, two cohorts and one rule, `ltct_org`'s options are the keys in declaration order, and a `countries` key is rejected as unknown. Give the `Base` fixture a minimal `organisations.yaml` and `profile-fields.yaml`. Fixture organisation keys are `fixture-*`, never `test-a` or `test-b`, so T038's leak check stays meaningful · tests/test_site_config.py
- [x] **T006** [P] Add `competencies.yaml` to the push and pull-request paths, because `validate` now reads it · .github/workflows/site-config.yml
- [x] **T007** [P] Bump `$plugin->version` · moodle/local_ltuse/version.php
- [x] **T008** [P] Write the category class.
  - It finds a category by `idnumber`. Failing that, it looks for a single category with the declared name and parent and an idnumber that is empty or does not start with `ltct:`. Drift reports that as `missing` with the message "adoptable". Apply sets the idnumber and reports `[changed] adopted`. More than one candidate is a blocking `ambiguous`.
  - It compares name and parent, and applies with `core_course_category::create()` and `->update()`. It leaves visibility and permissions at Moodle's defaults (R6).
  - It never deletes anything.

  (FR-002, FR-003, FR-004, R6) · moodle/local_ltuse/classes/siteconfig/categories.php
- [x] **T009** [P] Write the cohort class.
  - It finds a cohort by `idnumber` in any context. One outside system context is a blocking `[fail] wrong-context`.
  - It compares `name` and `visible = 0`, and applies with `cohort_add_cohort()` and `cohort_update_cohort()`.
  - It never sets `component`, and never reads or reports members.

  (FR-002, R1) · moodle/local_ltuse/classes/siteconfig/cohorts.php
- [x] **T010** [P] Write the profile field class.
  - It finds the category by name and each field by `shortname`. It compares name, visibility, `locked`, `required` and the menu options.
  - It applies with `profile_save_category()` and `profile_save_field()`.
  - A different `datatype` is a blocking `[fail] wrong-datatype`. A live menu option no longer declared is `extra` and is kept. No learner value is read or written.

  (FR-008, FR-009, R5) · moodle/local_ltuse/classes/siteconfig/profilefields.php
- [x] **T011** [P] Write the cohort rule class, using the plugin API and condition keys from T001 and T002.
  - Before touching any plugin class, it checks that `tool_dynamic_cohorts` is installed. If it is not, each rule is reported `missing` with "plugin not installed", non-blocking, because the plugin item already blocks.
  - It finds a rule through its cohort, then compares the condition and `enabled`. Apply creates or corrects the rule.
  - If T001 chose the fallback, it checks that `local_profilecohort` is installed instead. It writes and reads that plugin's rules through its table, as T002's contract table says.

  (FR-010, R4) · moodle/local_ltuse/classes/siteconfig/cohortrules.php
- [x] **T012** [P] Add the kinds `adopted` (status `changed`), and `ambiguous`, `wrong-context` and `wrong-datatype` (status `fail`, blocking), to the allowed kinds · moodle/local_ltuse/classes/siteconfig/report.php

**⟶ Wait for Wave 1 to finish, then:**

**Wave 2, independent (different files):**

- [x] **T013** [P] Hand `categories`, `cohorts`, `profile_fields` and `cohort_rules` to their classes for checking, in the contract's order. Count their blocking results in `has_blocking()`, so a blocking problem anywhere stops apply before any write · moodle/local_ltuse/classes/siteconfig/inspector.php
- [x] **T014** [P] Apply the four arrays after settings: categories, cohorts, the profile field category, profile fields, then cohort rules · moodle/local_ltuse/classes/siteconfig/applier.php
- [x] **T015** [P] Report these as non-blocking `extra`:
  - an `ltct:` category or cohort that is no longer declared;
  - an `ltct_` field that is no longer declared;
  - an `ltct:`-named rule that is no longer declared. Skip this scan when the cohort plugin is not installed. If T001 chose the fallback, scan `local_profilecohort`'s rules instead.

  Never list a member (FR-004) · moodle/local_ltuse/classes/siteconfig/drift.php
- [x] **T016** [P] Pin `tool_dynamic_cohorts` to T001's version and source, or the fallback if T001 chose it. Enable `enrol_cohort`, and raise the `local_ltuse` pin to T007's version (#8) · moodle/site/site.yaml
- [x] **T017** [P] Write a settings file with `rows: [8]` and a `purpose`. It pins `tool_dynamic_cohorts/releasemembers` to `0` and `tool_dynamic_cohorts/realtime` to `1`, each with a `why`. At `1`, `releasemembers` makes unmanaging a cohort delete all its members without events (research R4's result) · moodle/site/settings/cohorts.yaml
- [x] **T018** [P] Declare `rows: [8, 18]` and a `purpose`, then the "About your work" category and its fields:
  - `ltct_org`: a locked menu, `options_from: organisations`, visible to all;
  - `ltct_role`: T003's options, `teachers` visibility;
  - one `ltct_exp_*` checkbox per `competencies.yaml` category except `Meta`, each with its `area`.

  Each field gets a `why` (FR-008, FR-009, #18) · moodle/site/profile-fields.yaml
- [x] **T019** [P] Declare `rows: [8, 15]` and a `purpose`, then:
  - the shared categories: `published` named `LTC Published`, `pilots` named `LTC Pilots`, and `organisations`, each with a `why`;
  - T003's organisations, `independent` among them, each with only `key` and `name`;

  (FR-001, FR-002, FR-003) · moodle/site/organisations.yaml

**⟶ Wait for Wave 2 to finish, then:**

- [x] **T020** Run `validate` and `pytest tests/`. Deploy `local_ltuse` to the instance with LF line endings and run the CLI upgrade. Run `drift`. It should report as `missing`:
  - the organisations category and every organisation's category;
  - every organisation and managers cohort;
  - every cohort rule;
  - the profile field category and its fields.

  It should report `ltct:published` and `ltct:pilots` as `missing` with the message "adoptable", and nothing that blocks. Then `apply`, and `apply` again: the second run changes nothing. Save the output outside the repo · No files

**Checkpoint**: the applier knows every new item type, and the declared structure exists on the instance.

## Phase 3: User Story 1, onboard a partner organisation (P1, the MVP)

Files: none. The declaration lands in Phase 2; this phase proves it, using a scratch copy of `moodle/site/`.

**Goal**: one list entry gives an organisation its category and cohorts, the same shape as every other, with nothing done by hand.

**Independent Test**: apply a scratch copy with a test organisation added. It gets its category and both cohorts. A second apply changes nothing. Removing it reports each of its items as `extra` and deletes nothing.

- [x] **T021** [US1] Copy `moodle/site/` to the session scratchpad, then verify against the instance with `--site-dir <scratch copy>`, saving the output outside the repo:
  - "LTC Pilots" is still category id 2 and "LTC Published" still id 3, so `publish_moodle.py --category` is unchanged.
  - Each declared organisation has its category and both cohorts, all hidden and at system context (US1-1).
  - Add `test-a` to the copy and apply. It appears with the same shape. A second apply reports zero changes (US1-2).
  - Rename it in the copy and apply. Its category and cohorts are renamed, and nothing is duplicated.
  - Remove it from the copy and run drift. Drift reports as `extra` the category `ltct:org:test-a`, the cohorts `ltct:org:test-a` and `ltct:org:test-a:managers`, the rule on `ltct:org:test-a`, and the `test-a` option of `profilefield:ltct_org`. Nothing is deleted (US1-3, FR-004).
  - Time adding an organisation, from the edit to the second apply, against SC-001's 15 minutes.

  Leave `test-a` on the instance for US2. T031 removes it · No files

**Checkpoint**: US1 works on its own. Every declared organisation exists on the instance with the standard shape.

## Phase 4: User Story 2, a manager sees only their own people (P2)

Files: `moodle/site/roles.yaml`, `moodle/site/settings/groups.yaml`, `scripts/publish_moodle.py`, `tests/test_publish_moodle.py`, `moodle/local_ltuse/classes/profile_access.php`, `tests/profile_access_harness.php`, `moodle/local_ltuse/lib.php`

**Goal**: one follow-only role, arriving through a managers cohort, shows a manager their own organisation's group in a shared course. It shows nothing of anyone outside their organisations, including people who have just left it.

**Independent Test**: R3's checklist with two test organisations, two managers and four learners in one test shared course. Each manager reaches all of their own people and none of the other organisation's, before and after a learner moves and a manager is removed.

### Tests

- [x] **T022** [US2] Write a harness for the profile decision that needs no Moodle, like `tests/report_harness.php`, and write it to fail first against `profile_access::decide(bool $isself, array $managedkeys, string $viewedorg, bool $viewedisstaff, bool $viewerhasviewalldetails): int`. Its organisation keys are `fixture-*`, never `test-a` or `test-b`, as in T005. Cover each case in data-model.md's "Profile access decision":
  - viewing yourself is never prevented;
  - a viewer who manages no organisation is never prevented;
  - a manager viewing their own organisation's learner is not prevented;
  - a manager viewing another organisation's learner is prevented;
  - a manager viewing someone with an empty `ltct_org` is prevented, unless that person is staff (a course contact, or the site team);
  - a viewer with `moodle/user:viewalldetails` is never prevented;
  - the outcome is never `VIEWPROFILE_FORCE_ALLOW`.

  Run it as `php tests/profile_access_harness.php` · tests/profile_access_harness.php

- [x] **T023** [P] [US2] Write tests for `ensure_course()` with a stub client that records each call and has `dry_run` set to false. When `course_by_idnumber` returns `{"id": 5}`, the `core_course_update_courses` payload holds `groupmode: 1`. When it returns `None`, the create payload holds it too. A dry run cannot test this, because it never reaches the update path · tests/test_publish_moodle.py

### Implementation

**Wave 1, independent (different files):**

- [x] **T024** [P] [US2] Declare `orgmanager`:
  - `name`, `description`, `archetype: ""` and `contextlevels: [course]`;
  - capabilities `moodle/course:viewparticipants`, `moodle/user:viewdetails` and `moodle/site:viewuseridentity`, all `allow`;
  - a `why` citing #8, #15 and FR-005 to FR-007.

  It holds no `moodle/user:viewalldetails` (R2, R5) · moodle/site/roles.yaml
- [x] **T025** [P] [US2] Write the settings file with `rows: [8, 15]` and a `purpose`. It sets `moodlecourse/groupmode` to `1`, `moodlecourse/groupmodeforce` to `0`, `enrol_cohort/unenrolaction` to `3`, `forceloginforprofiles` to `1` and `hiddenuserfields` to empty, each with a `why` (FR-008, FR-011, R3, R5, R7, R9) · moodle/site/settings/groups.yaml
- [x] **T026** [P] [US2] In `ensure_course`, send `groupmode: 1` on `core_course_update_courses` as well as on create, so courses published earlier get separate groups (R3) · scripts/publish_moodle.py
- [x] **T027** [P] [US2] Write `local_ltuse\profile_access::decide()` with the signature in the data model. It is a pure static function from the five inputs to `core_user::VIEWPROFILE_PREVENT` or `core_user::VIEWPROFILE_DO_NOT_PREVENT`, and it makes T022 pass (R9) · moodle/local_ltuse/classes/profile_access.php

**⟶ Wait for Wave 1 to finish, then:**

- [x] **T028** [US2] Write `local_ltuse_control_view_profile($user, $course, $usercontext)`. It returns `DO_NOT_PREVENT` at once when the viewer is the viewed user, or manages no organisation. Otherwise it gathers the decision's inputs:
  - the viewer's managers-cohort keys, from R9's one read of `{cohort}` joined to `{cohort_members}`, cached for the request. Not `cohort_get_user_cohorts()`, which skips hidden cohorts;
  - the viewed user's `ltct_org`, from `profile_user_record($user->id)`;
  - whether the viewed user is staff: `has_coursecontact_role($user->id)`, or `moodle/user:viewalldetails` at system context;
  - `has_capability('moodle/user:viewalldetails', $usercontext ?? context_user::instance($user->id))`, because core passes a null context from several callers.

  It returns `profile_access::decide()`, and writes nothing (FR-007, R9) · moodle/local_ltuse/lib.php

**⟶ Wait for T028 to finish, then:**

- [x] **T029** [US2] Run `validate`, the harness and `pytest tests/`. `pytest tests/` includes T023's publisher tests, which cover T026. Deploy `local_ltuse` and purge caches. Then:
  - **Fresh scratch copy.** Re-copy the tracked `moodle/site/`, which now has T024's role and T025's settings, over the scratch copy. Add `test-a` and `test-b` to its `organisations.yaml`, then `validate` and `apply` with `--site-dir`. Confirm `forceloginforprofiles` is `1`.
  - **One course.** Create a throwaway course by hand, idnumber `ltct-test-course`, in `ltct:published`. First confirm nothing has that idnumber. Make it visible and confirm it is in separate groups. Add one manual grade item, and turn on completion with a manual criterion.
  - **Courses already there.** Check each course already on the instance. Set any that is not in separate groups by hand, before any organisation is added to it (R8).
  - **Accounts and managers.** Create the `ltct-test-*` accounts: two learners per organisation and one manager each. As the site team, set each learner's `ltct_org` to their organisation, and each manager's to `independent`, so a manager is not also a learner in the test course. Add each manager to their organisation's managers cohort. Add one A learner to A's managers cohort as well, for R3's last case. Create `ltct-test-teacher` with an empty `ltct_org` and enrol them by hand as `editingteacher`, in both organisation groups.
  - **Enrolment.** Add each organisation's two cohort-sync instances, learners as `student` and managers as `orgmanager`, into a group named for the organisation (R2, R8). Give every learner a grade and a completion mark.
  - **Check, as each manager.** Run every item of R3's checklist. That includes the pages that must open and the ones that must be refused, the moved-learner case with its known limit and the manual unenrol, both removed-manager cases, the participants page, which reaches the hook with a null context, the test teacher's profile, and the web-service calls with each manager's mobile token. That covers US2-1 to US2-3 and SC-002. Restore the removed managers to their cohorts afterwards.
  - **One role for everyone (SC-005).** Compare the two organisations' role sets. There is one role, `orgmanager`, with no per-partner override.

  Save the checklist result outside the repo · No files

**Checkpoint**: US2 works on its own. Separation holds on the instance, including for people who leave an organisation.

## Phase 5: User Story 3, learners join the right cohorts automatically (P3)

Files: none. This story's code and declaration land in Phase 2, so it is proven here.

**Goal**: setting a learner's organisation is enough. Their organisation cohort and its courses follow.

**Independent Test**: set a test learner's fields and let one scheduled refresh run. They are in the right cohorts and enrolled in the synchronised course. Change a field and membership follows.

- [x] **T030** [US3] On the instance with T029's structure, verify with a fresh `ltct-test-*` learner, saving the output outside the repo:
  - **Joining.** Setting `ltct_org` to `test-a` puts the learner in `test-a`'s cohort and enrols them in the test course within one scheduled refresh, with no manual step (US3-1, SC-003). Give them a grade and a completion mark.
  - **Changing organisation.** Changing `ltct_org` to `test-b` moves them to B's cohort, enrols them in group B, and leaves their grade and completion mark unchanged (US3-2). Their suspended A enrolment stays, as R7 describes. A's manager is refused their profile, and B's manager can open it.
  - **Locked field.** The learner's own profile edit form shows `ltct_org` frozen (US3-3, FR-009).
  - **No hand edits.** The organisation cohort's members cannot be edited by hand, because `component` is set (R4). If T001 chose the fallback, check against what T001 recorded about that plugin's `component`.

  No files

**Checkpoint**: US3 works on its own. Cohorts fill themselves from profile fields.

## Phase 6: User Story 4, persistent profiles (P4)

Files: none. The fields are declared in Phase 2.

**Goal**: organisation, country, role and expertise travel with the learner, and each field shows only where its visibility allows.

**Independent Test**: view one test learner's profile as another learner, as their organisation's manager and as an administrator.

- [x] **T031** [US4] On the instance, using T029's and T030's accounts:
  - **Fill.** One `test-a` learner fills `ltct_role` and ticks one `ltct_exp_*` checkbox in their own profile.
  - **Values persist.** Unenrol them from the test course. Their `ltct_role`, expertise, `ltct_org` and country values are still there (US4-1).
  - **Visibility.** View their profile four ways: as the other `test-a` learner, as A's manager, as themselves and as an administrator. `ltct_role` is hidden for the first two and shown for the last two (US4-2, R5). The organisation, country and expertise values are shown to all four (FR-008).
  - **Clean-up.** Every step uses the Moodle UI or a public API, because apply never deletes. Remove:
    - the course with idnumber `ltct-test-course`, which T029 created, and no other course;
    - the rules on `ltct:org:test-a` and `ltct:org:test-b`;
    - the `ltct:org:test-*` categories, organisation cohorts and managers cohorts;
    - the `test-a` and `test-b` options of `ltct_org`;
    - every `ltct-test-*` account.

    Then run `drift` against the tracked `moodle/site/`. It should report no item that mentions `test-`.

  No files

**Checkpoint**: US4 works on its own. Profiles carry the declared fields with their visibility, and the instance is clean.

## Phase 7: Polish

Files: `moodle/site/README.md`, `moodle/local_ltuse/README.md`, `moodle/REQUIREMENTS.md`, `INTENT.md`, `specs/001-site-config-as-code/contracts/declaration.md`, `.gitignore`

**Wave 1, independent (different files):**

- [x] **T032** [P] Add `organisations.yaml` and `profile-fields.yaml` to the file table, plus how to add an organisation. Then document the site team's four steps in plain language (R8):
  - creating accounts with `profile_field_ltct_org` through CSV upload. The CSV is made and kept outside the repository folder and deleted afterwards, because GitDoc pushes anything left in it;
  - adding an organisation to a course with its two cohort-sync instances and its group, after confirming the course is in separate groups;
  - making someone a manager by adding them to a managers cohort, and that removing them takes the role away;
  - after moving a learner to another organisation, unenrolling their old, suspended cohort-sync enrolment in each course both organisations share. This is the known limit in R7, and their history is kept.

  Note that the profile field category is found by name, so a rename is done once by hand · moodle/site/README.md
- [x] **T033** [P] List the core and `tool_dynamic_cohorts` APIs the new classes call, and the profile hook with the reads it makes: the `{cohort}`/`{cohort_members}` join, `profile_user_record()`, `has_coursecontact_role()`, and `has_capability('moodle/user:viewalldetails')` at system context and in the viewed user's context. List the raw reads by indexed column: `course_categories.idnumber`, `cohort.idnumber`, `user_info_field.shortname`, and `cohort` joined to `cohort_members` by `userid` for the hook (R9). If T001 chose the fallback, list its direct table writes with their reason (constitution XI) · moodle/local_ltuse/README.md
- [x] **T034** [P] Update rows #8 and #18 to built and verified, citing this spec. Mark #15 built, with "verified when SC-004's 2–3 real organisation managers have used it". It is not done until then (constitution X) · moodle/REQUIREMENTS.md
- [x] **T035** [P] Add a decision entry dated 2026-10-01 answering "How are partner organisations onboarded?":
  - the site team creates accounts and enrols organisations;
  - managers follow their own people;
  - a profile hook keeps people who leave out of their old manager's view;
  - category pages are public.

  Keep manager self-service as an open question for after SC-004 · INTENT.md
- [x] **T036** [P] Link spec 002's contract as the addition for `organisations.yaml`, `profile-fields.yaml`, the four new item types and the profile hook · specs/001-site-config-as-code/contracts/declaration.md
- [x] **T037** [P] Add a repo-wide backstop pattern for learner uploads, `*.csv` with exceptions for the CSVs already tracked, under a comment citing constitution III and GitDoc, like the existing credential patterns · .gitignore

**⟶ Wait for Wave 1 to finish, then:**

- [x] **T038** Validate against the Success Criteria:
  - Run `python scripts/site_config.py validate`, `pytest tests/`, `php tests/report_harness.php < moodle/local_ltuse/classes/siteconfig/report.php` and `php tests/profile_access_harness.php`.
  - Run `drift` on the instance; it should report no differences.
  - Run `git log -p main..HEAD -- moodle/ scripts/ tests/ .github/ .gitignore INTENT.md`. It must show no `test-a`, `test-b`, `ltct-test-` or `ltct_t001_probe`. `specs/002-org-structure-cohorts/` names those test keys on purpose, so it is left out of this check. Then search the whole branch log for any member list, member count or profile value: there must be none (constitution III).
  - Confirm T020, T021, T029, T030 and T031 cover SC-001, SC-002, SC-003 and SC-005.
  - Record that SC-004 is still open, waiting on real managers.

  No files

## Dependencies & Execution Order

- **Setup → Foundational → US1 → US2 → US3 → US4 → Polish.** No story starts before Phase 2's checkpoint.
- **Setup**: T001 → T002 → T003, in order. T002 reads what T001 recorded, and T001 and T003 share a file.
- **Foundational**: Wave 1 (T004–T012) blocks Wave 2 (T013–T019), which blocks T020.
  - T011 needs T001 and T002.
  - T016 needs T001 and T007.
  - T018 and T019 need T003.
- **US1**: T021 needs T020.
- **US2**:
  - T022 runs first, so it fails before T027 exists. T023 and Wave 1 (T024–T027) can run alongside it.
  - T028 needs T027.
  - T029 needs T022–T028, and a fresh scratch copy made after T024 and T025.
- **US3**: T030 needs T029's structure.
- **US4**: T031 needs T029's and T030's accounts. Its clean-up is the last instance step.
- **Polish**: Wave 1 (T032–T037) blocks T038.

---

# Amendment 2026-10-02: open courses

**Input**: [plan.md](plan.md) § "Amendment 2026-10-02: open courses", [spec.md](spec.md) Clarifications 2026-10-02, [research.md](research.md) R2, R3, R7–R14, [data-model.md](data-model.md), [contracts/declaration.md](contracts/declaration.md). Branch `002-open-courses`.

The rules at the top of this file still hold: verification on `ltuse.net` with `ltct-test-*` accounts only, test organisations only in a scratch copy of `moodle/site/`, evidence outside the repo tree, no member list, member count or profile value anywhere in git. Every Moodle API a task names was checked in `MOODLE_502_STABLE` (research R10–R14); a task that needs one research did not name looks it up in Context7 and `MOODLE_502_STABLE` first (constitution XI).

The amendment changes one story, **US2** (now "sees and manages only their own people", including US2-6, organisation-only courses). US1, US3 and US4 are unchanged; T086 re-runs their checks once, because the shape of every course changes.

## Phase 8: Setup (amendment)

Files: `specs/002-org-structure-cohorts/research.md`

**Wave 1, independent checks on the instance and in source** (each records its result in the named research section, as T001 did for R4):

- [ ] **T039** [P] Verify the organisation-enrolment instance (R10). In `MOODLE_502_STABLE` source, confirm `enrol_self` allows several instances per course, that `customint6 = 0` refuses a learner's self-enrolment, that `enrol_plugin::enrol_user()` still enrols through such an instance, and which `customchar*` field is free to hold our marker. Then on the instance, in a throwaway course `ltct-test-course`, add one such instance with `enrol_get_plugin('self')->add_instance()` from a CLI script kept outside the repo, enrol `ltct-test-learner-a1` through it, and confirm the learner sees no self-enrol button and the course participants show the enrolment. Record the field and the result under R10 · specs/002-org-structure-cohorts/research.md
- [ ] **T040** [P] Verify spec 004's delivery condition can change (R10). In source, confirm the report builder's enrolment-method condition (`enrol:plugin`) supports "is not equal to", and whether it can hold two values. Record which form 004 will use ("not `manual`", or `cohort` + `self`), and confirm it fails closed when no row matches (re-run 004's MDL-84213 SQL proof against it) · specs/002-org-structure-cohorts/research.md
- [ ] **T041** [P] Verify the reset-link call (R10). Call `core_login_process_password_reset($user->username, '')` from a CLI script outside the repo for `ltct-test-learner-a1`. Confirm it prints nothing, emails only that account's address, returns a status, and on a second call within `$CFG->pwresettime` reuses the reset rather than adding a row. Record the status values the page must map · specs/002-org-structure-cohorts/research.md
- [ ] **T042** [P] Verify cohort events and the NOGROUPS forum (R12, R14). Confirm on the instance that a `tool_dynamic_cohorts` rule change fires `\core\event\cohort_member_added` and `cohort_member_removed` (watch the standard log), and that a forum switched from SEPARATEGROUPS to NOGROUPS shows a post written with a stale `groupid` to a learner in no group (`public/mod/forum/lib.php:6792-6796`) · specs/002-org-structure-cohorts/research.md

**Checkpoint**: if T039 or T040 fails, stop and raise it with the maintainer: R10's enrolment design depends on both.

## Phase 9: Foundational (amendment): open the courses

Files: `moodle/site/settings/groups.yaml`, `moodle/site/course-discussions.yaml`, `moodle/site/reports.yaml`, `moodle/site/roles.yaml`, `moodle/site/organisations.yaml`, `scripts/site_config.py`, `scripts/moodle_payload.py`, `scripts/publish_moodle.py`, `moodle/local_ltuse/classes/external/ensure_discussion.php`, `moodle/local_ltuse/classes/siteconfig/{inspector,applier,drift}.php`, `moodle/REQUIREMENTS.md`, tests

Everything here ships together: a forum left in separate groups once the groups are gone stops learners posting (R3).

### Tests

- [X] **T043** [P] Change the publisher tests to expect `groupmode: 0`: the update payload, the create payload and the stub `ensure_discussion` return (today `:107`, `:427-435`, `:446-455`). Keep `test_groupmode_is_not_forced`, with its comment rewritten for open courses. Remove every assertion about `discussion.shared` and the "separated by organisation" label · tests/test_publish_moodle.py
- [X] **T044** [P] Replace the `Discussions` class with failing-first cases for: `moodlecourse/groupmode` must be `0`; `course-discussions.yaml` is no longer read and its presence is an error ("retired, spec 002 R14"); `ltct:mentors` is generated from `organisations.yaml`'s new `mentors: {name, why}` key; `ORGMANAGER_DENY` is unchanged and `orgmanager` keeps `moodle/site:viewuseridentity`. Keys are `fixture-*` · tests/test_site_config.py
- [X] **T045** [P] Rewrite or delete the cases pinning "absent means separated"; the payload carries no `discussion.shared` · tests/test_moodle_payload_discussion.py

### Implementation

**Wave 1, independent (different files):**

- [X] **T046** [P] Set `moodlecourse/groupmode` to `0` and reword the file's purpose and every `why`: shared courses are open across organisations; a course or activity may use groups for teaching, never to separate organisations (FR-011, R3). Keep `groupmodeforce 0`, `unenrolaction 3`, `forceloginforprofiles 1` (its `why` now: the profile hook scopes managers, R9) and `hiddenuserfields` · moodle/site/settings/groups.yaml
- [X] **T047** [P] Delete the file (R14) · moodle/site/course-discussions.yaml
- [X] **T048** [P] Drop the `group:name` column from the `progress` and `pilots` reports (R2). Leave the `ltct_org` scope condition: it is spec 016's to change · moodle/site/reports.yaml
- [X] **T049** [P] Reword `orgmanager`'s `description` and `why`: used only in organisation-only courses, never in a shared course; capabilities unchanged, `moodle/site:viewuseridentity` included (Doug, 2026-10-02). Reword the teacher role's `accessallgroups: inherit` `why`: it no longer scopes anyone in shared courses · moodle/site/roles.yaml
- [X] **T050** [P] Add `mentors: {name: "Mentors", why: …}` (R10, spec 003 R7). Reword the purpose and published `why`: shared courses are cross-organisation · moodle/site/organisations.yaml
- [X] **T051** [P] In `site_config.py`: delete `load_discussions()`, its `TOP_FILES` entry, the `DISCUSSIONS_FILE` constant and its call in `validate()`; add the `ltct:mentors` cohort (system context, hidden, no rule) to the cohorts `_expand` produces from `organisations.yaml`; require `moodlecourse/groupmode` to be `0`; refuse a present `course-discussions.yaml`. Make T044 pass · scripts/site_config.py
- [X] **T052** [P] Remove `discussion.shared` from the manifest and its print (today `:119-128`, `:437-456`, `:646-651`); keep the intro warning about partner data. Make T045 pass · scripts/moodle_payload.py
- [X] **T053** [P] Send `groupmode: 0` on create and update (replace `GROUPMODE_SEPARATE = 1` with a `GROUPMODE_NONE = 0` constant and a comment citing R3); never send `groupmodeforce`; keep `showreports: 0`. Rewrite the discussion label and the `courseforced` warning ("a forced group mode would wall the course forum"). Make T043 pass · scripts/publish_moodle.py
- [X] **T054** [P] `wanted_groupmode()` returns `NOGROUPS` always; remove the `shared` parameter from `execute_parameters()`, `execute()` and the docblock; keep `apply_groupmode()`, the grouping clear and `courseforced` · moodle/local_ltuse/classes/external/ensure_discussion.php

**⟶ Wait for Wave 1, then Wave 2:**

- [X] **T055** [P] Add a course group-mode check for every course with an `ltct:` idnumber, modelled on `check_course_reports()`: subject `course:<idnumber>:groupmode`, status `changed` when it is not `0`, fixed by `apply` through `update_course()` like `apply_course_reports()`. Remove the discussion "all participants" warning and the forum vault count; keep the "course forces a group mode" warning, reworded. Add the count-only blocking `[fail]` for any `enrol_cohort` instance whose cohort idnumber matches `ltct:org:%:managers` in an `ltct:` course outside the `ltct:org:*` categories (R2), naming no person · moodle/local_ltuse/classes/siteconfig/inspector.php, moodle/local_ltuse/classes/siteconfig/applier.php, moodle/local_ltuse/classes/siteconfig/drift.php
- [X] **T056** [P] Bump `$plugin->version` and `release`; note "open courses (spec 002, 2026-10-02)" · moodle/local_ltuse/version.php
- [X] **T057** [P] Drop `course-discussions.yaml` from the push and pull-request paths · .github/workflows/publisher-tests.yml, .github/workflows/site-config.yml
- [X] **T058** [P] Reset rows #7, #8, #10, #11 and #15 to "re-verify (spec 002 amendment, 2026-10-02)" and rewrite their text: #7 drops core per-course reports for managers in shared courses; #8 open courses, no organisation groups; #10 open course forums, `course-discussions.yaml` retired; #11 managers assign and end mentors for their own learners; #15 one manager role set, one page, organisation-only courses as one variant. Rework the SC-002 test line and add an organisation-only closure test · moodle/REQUIREMENTS.md

**⟶ Wait for Wave 2, then:**

- [ ] **T059** Run `python scripts/site_config.py validate` and `pytest tests/`. Deploy `local_ltuse` (LF line endings), run the CLI upgrade, purge caches. Run `drift` against a fresh scratch copy: it should report each `ltct:` course's group mode as `changed`, and nothing else unexpected. Do not `apply` yet; T068 does, after the migration · No files

**Checkpoint**: the repo no longer creates or re-creates organisation walls.

## Phase 10: User Story 2 (amended), a manager sees and manages only their own people (P2)

**Goal**: shared courses are open; each manager sees all of their own organisation's people and manages its learners through one page, and reaches no one else as a manager; an organisation-only course stays with its organisation.

**Independent Test**: R3's checklist (amended), R9's and R10's Verify lists, and R11's and R12's, with two test organisations, a manager each, two learners each, a mentor, a course teacher with an `ltct_org`, one shared test course and one organisation-only test course for A.

### Tests

- [X] **T060** [P] [US2] Extend the harness for the new `decide()` (data model, "Profile access decision", 2026-10-02 inputs in its order). Cases: viewing yourself; managing no organisation; own-organisation member gives `FORCE_ALLOW` whatever their role (learner, mentor, manager); another organisation's person with a participant path gives `DO_NOT_PREVENT`; another organisation's person reached only as a manager gives `PREVENT`; empty `ltct_org` and not staff gives `PREVENT`; `viewalldetails` or mentor gives `DO_NOT_PREVENT`. Replace the "never FORCE_ALLOW" sweep with "FORCE_ALLOW only when the viewed person is an own-organisation member". Write it to fail first · tests/profile_access_harness.php
- [X] **T061** [P] [US2] Write a harness for `local_ltuse\organisation\access`, like `profile_access_harness.php`, keys `fixture-*`. `is_org_member_of_manager`: true only when V≠P, P's `ltct_org` is one of V's keys and P is in that key's member cohort; false when the field is set but P is not yet in the cohort. `may_manage_account`: additionally false for a site admin, a deleted user, a course contact (`has_coursecontact_role`), anyone with a system- or category-context role assignment, a managers-cohort member and an `ltct:mentors` member. Per-action rules: enrol only into `ltct:published` or `ltct:org:<P's ltct_org>`, never `ltct:pilots`, and never another of V's organisations' categories; unenrol only from the organisation-enrolment instance. Run as `php tests/org_access_harness.php` · tests/org_access_harness.php
- [X] **T062** [P] [US2] Add failing-first cases for `org-courses.yaml` (data model and contract): `rows`, `org_only` entries with `slug`, `organisation`, `why` all required; `slug` resolves to a course under `modules/`; `organisation` is a declared key; no duplicate slug; `why` present. And for the payload's `placement: {org_only, category_idnumber}` (`{false, null}` for a shared course), and for `check_moodle_payload.py` refusing a malformed `placement` · tests/test_site_config.py, tests/test_moodle_payload_discussion.py
  **Done 2026-10-04:** `OrgCourses` cases in `tests/test_site_config.py`; `Placement` and `PlacementCheck` in `tests/test_moodle_payload_discussion.py`. Written first and seen failing.
- [X] **T063** [P] [US2] Add publisher tests with the recording stub: an organisation-only course calls `local_ltuse_place_course(courseidnumber, "ltct:org:<key>")` after create and after update; a shared course never calls it; a `moved: true` result is reported · tests/test_publish_moodle.py
  **Done:** `Placement` in `tests/test_publish_moodle.py`: the call after create and update, before content; never for a shared course; `moved: true` reported; a refusal stops the publish; the dry run; the client's required list.
- [X] **T064** [P] [US2] Add PHPUnit tests with synthetic users for the actions and observer: enrol creates the organisation-enrolment instance once and enrols as Student; unenrol never touches a manual or cohort-sync enrolment; suspend writes only `{id, suspended}` and ends sessions; reactivate; a manager's request for another organisation's learner, a mentor, a manager, a course teacher with the manager's `ltct_org`, and the site admin throws; a learner moving organisation has their organisation-enrolment enrolments in the old organisation's `ltct:org:*` courses suspended and their shared-course ones kept; contacts are added on `cohort_member_added` and removed on `cohort_member_removed` only when this plugin made them; a contact the two made themselves survives · moodle/local_ltuse/tests/organisation_test.php
  **Written, not yet run:** synthetic users and `fixture-*` keys only. Needs a Moodle PHPUnit environment (spec 004 T003's decision). Ending sessions on suspend is left to T081.

### Implementation

**Wave 1, independent (different files):**

- [X] **T065** [P] [US2] Write `local_ltuse\organisation\access`: pure static functions `is_org_member_of_manager()` and `may_manage_account()` and the per-action rules, from inputs gathered by the caller (data model, "Organisation access decision"). No Moodle calls. Make T061 pass · moodle/local_ltuse/classes/organisation/access.php
- [X] **T066** [P] [US2] Rewrite `profile_access::decide()` with the 2026-10-02 inputs and the four outcomes in research R9's order; fix the class docblock. Make T060 pass · moodle/local_ltuse/classes/profile_access.php
- [X] **T067** [P] [US2] Write `load_org_courses()` (R11) as a copy of the retired `load_discussions()` checks plus "`organisation` is a declared key"; add it to `TOP_FILES` and `validate()`; expand it to the payload's `org_courses: [{slug, category_idnumber}]` in Python. Write `moodle/site/org-courses.yaml` with `rows: [8, 15]` and `org_only: []`, and a header saying `why` records only the approval, never the organisation's circumstances. Make T062's site_config cases pass · scripts/site_config.py, moodle/site/org-courses.yaml
  **Done:** `load_org_courses()` reaches the PHP side as the payload's top-level `org_courses: [{slug, course_idnumber, category_idnumber}]`; `why` is never sent.

**⟶ Wait for Wave 1, then Wave 2:**

- [ ] **T068** [US2] Write the migration CLI `cli/open_courses.php` (R13), `--dry-run` by default, `--execute` to write. For every `ltct:` course: set each `enrol_cohort` instance's `customint2` to `0` through `enrol_cohort_plugin::update_instance()` with its own `roleid`; delete each group whose idnumber is `ltct:org:<key>` or whose name is an organisation's display name, with `groups_delete_group()`. Only outside `ltct:org:*` categories: delete each `enrol_cohort` instance for an `ltct:org:%:managers` cohort. Print counts per step, never a name. Then run it on the instance: `--dry-run`, check the counts, `--execute`, run it again and confirm zero; then `apply` the scratch copy (fixes group mode and every forum to NOGROUPS) and `drift` (clean) · moodle/local_ltuse/cli/open_courses.php
  **Code written (already on main, matching R13); the instance run is what is left open.**
- [X] **T069** [P] [US2] Rewrite `local_ltuse_control_view_profile()` to gather the new inputs: whether the viewed person is a member of the `ltct:org:<key>` cohort for a key the viewer manages (one read of `{cohort_members}` joined to `{cohort}` by idnumber, cached for the request), whether the viewer is their mentor, and the participant path (`enrol_get_shared_courses($viewer, $user, true)` plus `get_user_roles()` per shared course, any role but `orgmanager`). `local_ltuse_managed_organisation_keys()` is unchanged. Update the docblocks at the top of the file (R9) · moodle/local_ltuse/lib.php
- [X] **T070** [P] [US2] Write `local_ltuse\organisation\people`: the manager's people (members of each managed key's member cohort, through `is_org_member_of_manager`), each with their courses and course completion through `\completion_info`, and whether `may_manage_account` holds; email shown for everyone, protected people included (Doug, 2026-10-02) · moodle/local_ltuse/classes/organisation/people.php
  **Done:** reuses spec 003's `local_ltuse_organisation_person_facts()` and `mentoring::courses()` (now public).
- [X] **T071** [P] [US2] Write `local_ltuse\organisation\actions`, each method re-checking `may_manage_account` and its per-action rule (R10 table):
  - `enrol()`: find the course's organisation-enrolment instance by T039's marker, else create it with `enrol_get_plugin('self')->add_instance()` with `customint6 = 0`, a random key, no welcome message; `enrol_user($instance, $userid, $studentroleid)`;
  - `unenrol()`: only from that instance;
  - `send_reset()`: `core_login_process_password_reset($user->username, '')`, mapping T041's statuses;
  - `suspend()`: `\core\session\manager::destroy_user_sessions($id)` then `user_update_user((object)['id' => $id, 'suspended' => 1], false)`, refusing a site admin and the acting user, as `admin/user.php:127-138`;
  - `reactivate()`: `user_update_user((object)['id' => $id, 'suspended' => 0], false)`.

  Make T064's action cases pass · moodle/local_ltuse/classes/organisation/actions.php
  **Done:** each action re-reads cohort membership (`$reload`) before writing. `reset_outcome()` is pure, with harness cases; R10 records the status detail. Enrol refuses while `enrol_self` is disabled site-wide.
- [X] **T072** [P] [US2] Write `local_ltuse\organisation\contacts` and extend the observer: `cohort_member_added` / `cohort_member_removed` on `ltct:org:<key>` and `ltct:org:<key>:managers` add or remove manager–member contacts, recorded in `local_ltuse_org_contact` (`managerid`, `memberid`, `contactid`, `timecreated`), checking `\core_message\api::is_contact()` before `add_contact()` and removing only a contact this plugin made and no other relationship needs (R12). On `cohort_member_removed` from a member cohort, also suspend the person's organisation-enrolment enrolments in that organisation's `ltct:org:*` courses (R10). Register both events · moodle/local_ltuse/classes/organisation/contacts.php, moodle/local_ltuse/classes/observer.php, moodle/local_ltuse/db/events.php
  **Done:** `user_deleted` also clears `local_ltuse_org_contact`. Events fire only when the `tool_dynamic_cohorts` rules run without bulk processing; otherwise T074's task catches up.
- [X] **T073** [P] [US2] Add the `local_ltuse_org_contact` table (unique on `managerid, memberid`) and its upgrade step; add it to the privacy provider's metadata, export and delete, like `local_ltuse_mentor_contact` · moodle/local_ltuse/db/install.xml, moodle/local_ltuse/db/upgrade.php, moodle/local_ltuse/classes/privacy/provider.php
  **Done:** savepoint 2026100402.
- [X] **T074** [P] [US2] Write the hourly `reconcile_org_contacts` task: repairs manager–member contacts both ways and the old-organisation enrolment suspensions, reporting counts only · moodle/local_ltuse/classes/task/reconcile_org_contacts.php, moodle/local_ltuse/db/tasks.php
  **Done.**
- [X] **T075** [P] [US2] Write the `local_ltuse_place_course(courseidnumber, categoryidnumber)` web service (R11): requires `local/ltuse:publish`; accepts only a category idnumber `ltct:org:<key>`, `ltct:pilots` or `ltct:published`; resolves it by `course_categories.idnumber`; calls `move_courses([$id], $catid)` only when the course is elsewhere; returns `{moved: bool}`. Register it in `db/services.php` and the publishing service's function list · moodle/local_ltuse/classes/external/place_course.php, moodle/local_ltuse/db/services.php
  **Done:** the category check is the pure `access::is_placement_category()`, with harness cases.
- [X] **T076** [P] [US2] Add placement drift (R11): a declared organisation-only course outside its category is `changed`, an undeclared `ltct:` course inside an `ltct:org:*` category is `extra`, both non-blocking; `apply` never moves a course · moodle/local_ltuse/classes/siteconfig/inspector.php, moodle/local_ltuse/classes/siteconfig/drift.php
  **Done:** `inspector::check_course_placement()`, called from `drift.php` only, so `apply` never moves a course. With no `org_courses` key it checks nothing.

**⟶ Wait for Wave 2, then Wave 3:**

- [X] **T077** [US2] Write `organisation.php`: `require_login()`; the people list from T070 grouped by organisation; per learner, the actions from T071 behind a confirmation that says what each does (suspension is site-wide; unenrolling a last enrolment removes grades and group places, not completion records); a sesskey on every write; course choices only those T065's enrol rule allows. Link it from the user menu for managers-cohort members only through the `extend_user_menu` hook. Add the strings · moodle/local_ltuse/organisation.php, moodle/local_ltuse/classes/hook_callbacks.php, moodle/local_ltuse/db/hooks.php, moodle/local_ltuse/lang/en/local_ltuse.php
  **Done:** `organisation.php` and `templates/organisation.mustache`. A GET only shows a confirmation; the confirmed POST, with a sesskey, writes.
- [X] **T078** [US2] Write `mentors.php?userid=` from spec 003 research R7 Phase B: the learner's current mentors with Remove, and an Add picker drawn only from `ltct:mentors`; authorised by `moodle/role:assign` in the learner's context (site team) or `may_manage_account`; writes through `role_assign()` / `role_unassign()` of `mentor`. Link it from each row of `organisation.php` · moodle/local_ltuse/mentors.php
  **Done in spec 003's Phase B PR (#91), which this branch builds on:** `mentors.php` is authorised by `moodle/role:assign` or `may_manage_account`, as written here. Each `organisation.php` row links to it.
- [X] **T079** [P] [US2] Add `placement` to the manifest from `site_config.load_org_courses()`; an invalid file stops the build. Check its shape in `check_moodle_payload.py`. Call `local_ltuse_place_course` after create and update for an organisation-only course, and report a move. Add `local_ltuse_place_course` to the client's required functions. Make T062's payload cases and T063 pass · scripts/moodle_payload.py, scripts/check_moodle_payload.py, scripts/publish_moodle.py, scripts/moodle_client.py
  **Done:** check 9 in `check_moodle_payload.py`, no `--force`; `ensure_placement()` runs after `ensure_course()` on every publish; `REQUIRED_FUNCTIONS` in `moodle_client.py`.
- [X] **T080** [P] [US2] Change spec 004's delivery condition to T040's form in the `progress` and `programme` reports and in `SCOPE_CONDITIONS`; make `coverage.php` and its test count the organisation-enrolment instance as delivery, and still never a manual one · moodle/site/reports.yaml, scripts/site_config.py, moodle/local_ltuse/classes/reportbuilder/local/entities/coverage.php, moodle/local_ltuse/tests/competency_coverage_test.php, tests/test_site_config.py
  **Done in the repo:** `{operator: not_equal, value: manual}` in `progress` and `programme`, and in `SCOPE_CONDITIONS`; `pilots` keeps `equal manual`. `coverage.php` counts cohort sync and the marked `enrol_self` instance, never manual, and a new PHPUnit case says so (not yet run). `site.yaml` now declares `enrol_manual` and `enrol_self` enabled: the condition needs manual enabled (MDL-84213), and manager enrolments go inactive if self is disabled. T040's instance half is still open. Spec 004's own docs still say `enrol:plugin = cohort`.

**⟶ Wait for Wave 3, then:**

- [ ] **T081** [US2] Run `validate`, `pytest tests/`, both harnesses and `php tests/report_harness.php < moodle/local_ltuse/classes/siteconfig/report.php`. Deploy `local_ltuse`, upgrade, purge caches, `apply` the scratch copy. Then, with `test-a` and `test-b` in the scratch copy and an organisation-only test course for A declared in a scratch `org-courses.yaml`, run every item of R3's amended Verify list, then R9's, R10's, R11's and R12's. Include the moved learner, the removed manager, the two-organisation manager, the learner whose field is set but who is not yet in the cohort, the mobile web-service calls, and the 004 report items (a manager-enrolled learner appears in their organisation report and not in the pilots report). Save the checklist result outside the repo · No files

**Checkpoint**: US2 (amended) works on its own: open courses, scoped managers, an organisation-only course that stays closed.

## Phase 11: Polish (amendment)

**Wave 1, independent:**

- [X] **T082** [P] Rewrite the file table (`org-courses.yaml` added, `course-discussions.yaml` gone), the site team's steps as two recipes (shared course: one cohort sync per organisation as Student, no group, no managers cohort; organisation-only course: the organisation's cohort as Student and its managers cohort as `orgmanager`, no group), how to declare an organisation-only course (maintainer only; `why` records only the approval), the managers' page, and enrolling course leaders and filling `ltct:mentors`. Remove the moved-learner unenrol step (R8) · moodle/site/README.md
  **Done.**
- [X] **T083** [P] List the new core APIs (R10–R13), the `user_password_resets`-free reset call, the unindexed reads (`cohort.idnumber`, `course_categories.idnumber`) as Principle XI exceptions, the 5.3 note on `user_update_user()` (migrated together with spec 016's hook), and the open-courses migration CLI; rewrite the discussion section · moodle/local_ltuse/README.md
  **Done:** "Managers and their own people" section.
- [X] **T084** [P] Add the two enrolment recipes after publishing (shared, organisation-only) · process/stages/08-publish.md
  **Done:** new step 8; the old step 8 is now 9.
- [X] **T085** [P] Add one line under "Delivery: Moodle": shared courses have no organisation groups; managers are scoped by the managers cohort through `local_ltuse\organisation\access`; never send `groupmode: 1` · CLAUDE.md
  **Done.**
- [ ] **T086** [P] Run the US1, US3 and US4 checks again (T021, T030, T031's lists) on the open-course shape, to show nothing they relied on changed · No files
- [X] **T087** [P] Remove the Sync Impact Report comment from the top of the constitution before the governing-text commit · .specify/memory/constitution.md

**⟶ Wait for Wave 1, then:**

- [ ] **T088** Validate against the Success Criteria:
  - Run `validate`, `pytest tests/`, the three PHP harnesses, and `drift` on the instance; drift reports no differences.
  - `git log -p main..HEAD -- moodle/ scripts/ tests/ .github/ CLAUDE.md INTENT.md` shows no `test-a`, `test-b`, `ltct-test-` or member data (constitution III).
  - Confirm T081 covers SC-002 (amended) and T086 covers SC-001, SC-003 and SC-005.
  - Update REQUIREMENTS rows #7, #8, #10, #11 and #15 from "re-verify" to verified, citing T081; #15 stays "verified when SC-004's real managers have used it".
  - Record that SC-004 (real managers find, follow and manage their own learners) is still open · moodle/REQUIREMENTS.md

## Dependencies & Execution Order (amendment)

- **Phase 8 → Phase 9 → Phase 10 → Phase 11.** T039 and T040 gate T071, T080 and the whole of Phase 10's enrolment work; T041 gates T071's `send_reset()`; T042 gates T072 and T068's forum step.
- **Phase 9**: T043–T045 first (failing). Wave 1 (T046–T054) blocks Wave 2 (T055–T058), which blocks T059.
- **Phase 10**: T060–T064 first (failing). Wave 1 (T065–T067). Wave 2 (T068–T076): T068 needs T059 deployed; T069 needs T066; T070 and T071 need T065; T072 needs T042 and T073 needs nothing else; T079 needs T067 and T075. Wave 3 (T077–T080) needs Wave 2. T081 needs everything before it.
- **Phase 11**: Wave 1 (T082–T087) blocks T088. T087 can run any time before the governing-text commit.
- **Spec 016** starts after T068 and T069 are deployed (open courses and profile reach), and before production go-live (R13's gate).

### Parallel examples

- Phase 9 Wave 1: T046, T047, T048, T049, T050 (YAML), T051 (site_config), T052 (payload), T053 (publisher), T054 (PHP) all touch different files.
- Phase 10 Wave 2: T070, T071, T072, T073, T074, T075 are separate PHP files; T069 is `lib.php` only.

### Implementation strategy

- **MVP (what spec 016 needs first)**: Phase 9 plus T060, T066, T068, T069: courses open, organisation groups gone, managers reach their own people's profiles.
- **Then**: the shared check, the page and its actions (T061, T065, T070, T071, T077), mentors (T078), contacts (T072–T074), organisation-only courses (T062, T063, T067, T075, T076, T079), the 004 delivery change (T080).
- **Each increment** is deployed and checked against its slice of T081 before the next.
