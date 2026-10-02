# Tasks: Partner organisations, cohorts and profiles

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
