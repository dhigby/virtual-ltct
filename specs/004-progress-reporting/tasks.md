# Tasks: Progress tracking and reporting

**Input**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/declaration.md](contracts/declaration.md), [contracts/publish.md](contracts/publish.md), [quickstart.md](quickstart.md)

**Tests**: The plan includes tests:
- `pytest`, for `site_config.py`, `moodle_payload.py` and `publish_moodle.py`;
- a PHP harness that runs without Moodle, for the completion rule and the criteria diff;
- PHPUnit, for the per-competency datasource.

Each story writes its tests first, and they must fail before the code that makes them pass is written.

Verification runs on the temporary 5.2.3+ instance (`ssh ltuse`, `https://ltuse.net`), with test accounts only (constitution X). That host carries other people's live sites. So no task touches anything outside `/home/ltuse`, and **nothing runs PHPUnit there** (spec 002's rule). T003 decides where PHPUnit runs.

**Keep test data and evidence out of the repo tree.** GitDoc commits and pushes any edit in the repo about five minutes after it is made, so "local and uncommitted" is not safe here.
- **Test organisations** go only in a copy of `moodle/site/` in the session scratchpad. Every command that needs them runs as `python scripts/site_config.py <validate|apply|drift> --site-dir <scratch copy>`. The tracked `moodle/site/organisations.yaml` never holds a test key.
- **Test accounts** are named `ltct-test-*`, and T074 deletes every one at the end.
- **Downloads, screenshots and email attachments** hold learner rows, even test ones. They are saved outside the repo folder and never attached to the PR.
- **Evidence** is saved outside the repo: apply and drift output, and checklist notes. What goes into `research.md` is an outcome ("A saw only A's rows"), never a member list, a row count from a real organisation or a profile value.

**Pending decisions** (plan "Decisions on the plan's limits", #1, #2 and #6). Tasks may be built as drafted. T031 (US2), T045 (US4) and T068 (US5) may not be closed until the maintainer's decision is recorded (T004).

## Format: `[ID] [P?] [Story] Description · file`

- **[P]**: can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: the user story the task serves (US1–US5)

---

## Phase 1: Setup

Files: `specs/004-progress-reporting/research.md`, `specs/004-progress-reporting/contracts/declaration.md`, `specs/004-progress-reporting/data-model.md`, `specs/004-progress-reporting/plan.md`

Before any PHP is written, every Moodle API it will call is looked up: Context7 first (`/websites/moodledev_io_5_2_apis`), then confirmed in `MOODLE_502_STABLE` source (Principle XI; CLAUDE.md "Look up every Moodle API before using it").

**Wave 1, independent:**

- [X] T001 [P] Confirm these points in `MOODLE_502_STABLE` source. Record each under its R in research.md, with the file and line it was read from:
  - **R15's four points**:
    - `\core_privacy\local\metadata\null_provider`;
    - XMLDB `db/install.xml` plus `db/upgrade.php` for a plugin that has had no tables until now;
    - `add_base_condition_sql()` on a plugin datasource's main table;
    - `toggle_report_column_sorting()` honouring a column's sort fields.
  - **R3**:
    - `completion_criteria_activity::insert()` and `delete()`;
    - `completion_aggregation::setMethod()` and `save()`;
    - the `completion_completion` field or method that flags a row for re-aggregation;
    - the `\core\event\course_completion_updated::create()` arguments.
  - **R9**:
    - `core_reportbuilder\local\helpers\report::create_report()`, `add_report_column()`, `add_report_condition()` and `add_report_filter()`;
    - `base::set_condition_values()`;
    - `local\audiences\base::create()`;
    - `local\schedules\base::create()` with the `message` type;
    - how a column's heading and aggregation are set after it is added.
  - **R11**: `course_handler::create_category()` and `save_field_configuration()`.
  - **R12**: the schedule constants for recurrence, `userviewas` and `reportempty`. data-model.md renders "send nothing when empty" as `reportempty: 0`. If `0` is the "send an empty report" value, correct data-model.md and contracts/declaration.md to the "don't send" constant.

  · specs/004-progress-reporting/research.md
- [X] T002 [P] Check every `entity:name` in the four report templates against `MOODLE_502_STABLE` source:
  - the participants datasource and the entities it joins (user, group, course, enrolment, enrol, role, completion, access);
  - for the column ones, whether each `aggregation` is allowed on that column type;
  - whether `min` is allowed on `enrolment:timecreated` and `groupconcatdistinct` on `group:name`, the fallback T039 uses if a two-cohort learner appears twice.

  Correct any wrong identifier in the contract, and record the source file for each entity. V5 (T038) still confirms them on the instance · specs/004-progress-reporting/contracts/declaration.md
- [ ] T003 [P] Ask the maintainer where `moodle/local_ltuse/tests/competency_coverage_test.php` runs. Recommend a CI job using `moodle-plugin-ci` against `MOODLE_502_STABLE` with Postgres. The other choice is a local Docker Moodle. It is never `ltuse.net`. Record the decision under "Testing" in Technical Context · specs/004-progress-reporting/plan.md
- [ ] T004 [P] Ask the maintainer to decide limits #1 (no "next lesson" link), #2 (managers cannot opt out of the weekly email) and #6 (pilot-era completion counted as delivery after a later cohort enrolment). Record each answer and its date in "Decisions on the plan's limits". This does not block starting work. It blocks closing T031, T045 and T068 · specs/004-progress-reporting/plan.md

**Checkpoint**: every API and report identifier the code will use is confirmed, and the PHPUnit location is decided.

---

## Phase 2: Foundational

Files: `.gitignore`, `moodle/local_ltuse/version.php`, `moodle/local_ltuse/db/install.xml`, `moodle/local_ltuse/db/upgrade.php`, `moodle/local_ltuse/classes/privacy/provider.php`, `moodle/local_ltuse/lang/en/local_ltuse.php`, `moodle/site/site.yaml`, `scripts/site_config.py`, `tests/test_site_config.py`, `moodle/local_ltuse/classes/siteconfig/report.php`

No story starts until this phase is done.

**Wave 1, independent:**

- [X] T005 [P] Add `*.xlsx`, `*.xls` and `*.ods` as a backstop, beside 002's `*.csv`, under a comment citing constitution III and GitDoc (R14). Run `git ls-files '*.xlsx' '*.xls' '*.ods'`, and add a `!` exception for any tracked file it lists · .gitignore
- [X] T006 [P] Bump `$plugin->version` once for the whole feature: a `20261002NN` stamp greater than `2026100201`, with `release` `0.6.0`. Keep `requires` and `supported` at 5.2. This stamp is the savepoint T051 uses · moodle/local_ltuse/version.php
- [X] T007 [P] Write one FR-010 label check, `_check_aim_label(where, label, problems, strict=False)`. It refuses:
  - `certif` in any case;
  - `Advanced Beginner`, `Practitioner`, `Trainer` or `Proficient` as a word, anywhere;
  - `Learner` only within three words of `level`, so `Learner` as a column heading passes and `Learner level` fails;
  - `reached`, `achieved` or `attained` within three words of `level`.

  With `strict=True`, which is for `competency-coverage`, it also refuses `reached`, `achieved` and `attained` anywhere, and `competent` as a word · scripts/site_config.py
- [X] T008 [P] Write failing-first cases for T007: one passing and one failing label per rule, plus the strict-only rules. `Learner` and `{org}: learner progress` pass; `Learner level` fails. Assert pass or fail, not the message wording. Any organisation key in a fixture is `fixture-*` · tests/test_site_config.py
- [X] T009 [P] Add the kinds `unknown` and `missing` if `report.php` lacks them:
  - `unknown` is `fail`, and blocks apply;
  - `missing`, for an audience cohort, is `fail` and blocks only that report.

  Give `has_blocking()` the distinction · moodle/local_ltuse/classes/siteconfig/report.php

**⟶ Wait for T006, then:**

- [X] T010 Raise the `local_ltuse` pin to T006's version (#7) · moodle/site/site.yaml
- [X] T051 [P] [US5] Write `db/install.xml`. It holds no user data. It is done here, not in US5, because the version is bumped once (T006): US1's first deploy (T027) is the only upgrade that runs `upgrade.php`, so the tables must exist by then. They stay empty until US5.
  - **`local_ltuse_competency`**: `id`; `name`, char 255, with a unique index; `category`, char 255; `sortorder`, int; `retired`, int 1, default 0; `timemodified`.
  - **`local_ltuse_course_comp`**: `id`; `courseid`, a foreign key to `course.id`; `competencyid`, a foreign key to `local_ltuse_competency.id`; `timemodified`. A unique index on (`courseid`, `competencyid`).

  Write `db/upgrade.php` too. It creates both tables with XMLDB when the old version is below T006's stamp, then saves the upgrade savepoint at that stamp (T001's R15 confirmation) · moodle/local_ltuse/db/install.xml
- [X] T052 [P] [US5] Write `local_ltuse\privacy\provider`, implementing `\core_privacy\local\metadata\null_provider`, with `get_reason()` returning the `privacy:metadata` string. Add that string to `lang/en/local_ltuse.php`. It is done here, beside T051, so the plugin never has tables without a privacy declaration · moodle/local_ltuse/classes/privacy/provider.php

**Checkpoint**: `python scripts/site_config.py validate` and `python -m pytest -q tests/test_site_config.py` pass, and `install.xml` loads in the XMLDB editor without errors.

---

## Phase 3: User Story 1, every published course tracks completion the same way (P1, the MVP)

**Goal**: Every course the repo publishes gets the one completion rule, with no hand steps. A republish never erases a recorded completion.

**Independent test**: quickstart V2, V3, V4 and V7. Publish two courses. A test learner completes one fully and the other in part, including one lesson opened offline in the app. Both show the expected state, and nothing was set in the admin UI.

Files: `scripts/moodle_payload.py`, `scripts/check_moodle_payload.py`, `scripts/publish_moodle.py`, `moodle/local_ltuse/classes/completion_rule.php`, `moodle/local_ltuse/classes/criteria_diff.php`, `moodle/local_ltuse/classes/util.php`, `moodle/local_ltuse/classes/external/create_page.php`, `moodle/local_ltuse/classes/external/create_quiz.php`, `moodle/local_ltuse/classes/external/set_course_completion.php`, `moodle/local_ltuse/db/services.php`, `moodle/site/settings/completion.yaml`, `tests/test_payload_completion.py`, `tests/test_publish_moodle.py`, `tests/criteria_harness.php`, `.github/workflows/site-config.yml`, `.github/workflows/publisher-tests.yml`

**Wave 1, tests first (they must fail):**

- [X] T011 [P] [US1] Write the payload rule tests on a synthetic course folder in `tmp_path`. Never use a real course:
  - a lesson page gives `"view"`;
  - a withheld-quiz placeholder page gives `"view"`;
  - a quiz with `threshold_pct: 80` gives `"pass"`;
  - a quiz with no threshold, or with `threshold_pct: 0`, gives `"submit"`;
  - the manifest has `"completion": "all"`;
  - `check_moodle_payload` refuses a manifest with one module's `completion` removed, and one set to `"done"`.

  · tests/test_payload_completion.py
- [X] T012 [P] [US1] Write a harness that runs without Moodle, like `tests/report_harness.php`. It requires the two pure classes by path and checks:
  - `completion_rule::fields()` returns, for `view`, `submit` and `pass`, exactly the fields in data-model.md's "Completion rule" table;
  - an unknown value throws;
  - `criteria_diff::diff()` handles add only, remove only, both, equal sets (empty `add` and `remove`), unsorted input (sorted output) and duplicated input;
  - the source of `classes/external/set_course_completion.php` contains neither `clear_criteria` nor `delete_course_completion_data`.

  It exits non-zero on any failure · tests/criteria_harness.php
- [X] T013 [P] [US1] Write publisher tests with a stub client that records every call, `dry_run` false:
  - `ensure_course` sends `enablecompletion: 1` on both `core_course_create_courses` and `core_course_update_courses`;
  - every `local_ltuse_create_page` and `local_ltuse_create_quiz` call carries the module's payload `completion`;
  - `local_ltuse_set_course_completion` is called once, after `hide_modules`, and is the last write;
  - the output has `completion  N added, M removed`;
  - a module result of `completion: differs` makes `main()` exit 1 after the summary, naming that module;
  - under `--dry-run`, `set_course_completion` is listed and nothing is sent.

  · tests/test_publish_moodle.py

**Wave 2, implementation (independent files):**

- [X] T014 [P] [US1] Add one function, `completion_for(kind, threshold_pct)`, giving the rule in data-model.md "Completion rule":
  - `view` for every page, the withheld-quiz placeholder included;
  - `pass` for a quiz only when `threshold_pct` is a positive integer, otherwise `submit`.

  Set `completion` on every module, and `"completion": "all"` on the manifest. Use no Moodle field names (Principle II). This makes T011's rule cases pass · scripts/moodle_payload.py
- [X] T015 [P] [US1] Refuse a module whose `completion` is missing or not one of `view`, `submit` and `pass`, and a manifest whose `completion` is not `all`. This makes T011's refusal cases pass · scripts/check_moodle_payload.py
- [X] T016 [P] [US1] Write `local_ltuse\completion_rule`, a pure class with no Moodle calls. `fields(string $rule): array` returns:
  - `view`: `completion = 2`, `completionview = 1`;
  - `submit`: `completion = 2`, `completionusegrade = 1`, `completionpassgrade = 0`;
  - `pass`: `completion = 2`, `completionusegrade = 1`, `completionpassgrade = 1`.

  Write the values as literals, with the core constant names in comments, so T012 runs without Moodle. An unknown value throws, and is never given a default · moodle/local_ltuse/classes/completion_rule.php
- [X] T017 [P] [US1] Write `local_ltuse\criteria_diff`, a pure class. `diff(array $wantedcmids, array $presentcmids): array` returns `['add' => wanted − present, 'remove' => present − wanted]`, each sorted and de-duplicated. This makes T012's diff cases pass · moodle/local_ltuse/classes/criteria_diff.php
- [X] T018 [P] [US1] Write the site and course completion settings with `rows: [7]` and a `purpose`. Set `enablecompletion`, `moodlecourse/enablecompletion` and `moodlecourse/showcompletionconditions` to `1`, each with the `why` given in contracts/declaration.md (R4) · moodle/site/settings/completion.yaml
- [X] T019 [P] [US1] Add a step that runs `php tests/criteria_harness.php`. Add `moodle/local_ltuse/classes/**` and `tests/criteria_harness.php` to the push and pull-request paths · .github/workflows/site-config.yml
- [X] T020 [P] [US1] Add `tests/test_payload_completion.py` to the pytest step and to the trigger paths · .github/workflows/publisher-tests.yml

**⟶ Wait for T016, then:**

- [X] T021 [US1] Change `util::upsert_module()` to take the completion fields from the caller, and remove the hard-coded `completion = 0` (R2):
  - **On create**, pass the fields to `add_moduleinfo()` and return `set`.
  - **On update**, read the cm's current tracking. If it is `0`, send the fields with `completionunlocked = 1` and return `set`. If it equals the rule, send no completion field and return `unchanged`. Otherwise, send no completion field and return `differs`.
  - **When the caller passes no rule**, it touches nothing and returns `''`.

  · moodle/local_ltuse/classes/util.php

**⟶ Wait for T017 and T021, then (independent files):**

- [X] T022 [P] [US1] Add the optional parameter `completion` (`PARAM_ALPHA`, default `''`) and the return field `completion`. Map the value through `completion_rule::fields()`, and pass it to `upsert_module()` · moodle/local_ltuse/classes/external/create_page.php
- [X] T023 [P] [US1] Do the same as T022 for quizzes. Also refuse `pass` when the computed `gradepass` is `0`, so a quiz never claims a pass rule it cannot enforce (contracts/publish.md) · moodle/local_ltuse/classes/external/create_quiz.php
- [X] T024 [P] [US1] Write `local_ltuse_set_course_completion(courseidnumber)`, following contracts/publish.md's six steps:
  - **Access**: it needs `local/ltuse:publish` and `moodle/course:update` in the course context. It fails with `error:completionoff` if completion is off for the site or the course.
  - **Wanted set**: the visible cms whose idnumber starts with `ltct:<slug>:`. The question bank is never in it.
  - **Present set**: the `COMPLETION_CRITERIA_TYPE_ACTIVITY` rows, read by `course`.
  - **Writes**: it uses `criteria_diff::diff()`, then inserts or deletes each criterion through `completion_criteria_activity`. It sets overall and activity aggregation to ALL, and returns `set` only if one was not ALL.
  - **Re-aggregation**: only when `remove` is non-empty, it flags the course's incomplete `course_completions` rows through `completion_completion`.
  - **Event**: if anything changed, it fires `course_completion_updated`.
  - **Returns** `added`, `removed`, `aggregation`, `reaggregated` (a count, never user ids) and `othercriteria`.
  - **Never** calls `clear_criteria()` or `delete_course_completion_data()`.

  · moodle/local_ltuse/classes/external/set_course_completion.php

**⟶ Wait for T024, then:**

- [X] T025 [US1] Register `local_ltuse_set_course_completion` as a `write` function, and add it to the publisher's service · moodle/local_ltuse/db/services.php
- [X] T026 [US1] Change the publisher to follow contracts/publish.md steps 1, 3, 6 and 7:
  - `enablecompletion: 1` on course create and update;
  - each module's payload `completion` on `create_page` and `create_quiz`;
  - `local_ltuse_set_course_completion` after hiding;
  - print `completion  N added, M removed` and each `differs` module;
  - exit 1 after the summary if any module `differs`.

  This makes T013 pass · scripts/publish_moodle.py

**⟶ Wait for Wave 2 and T021–T026, then verify on the instance (sequential):**

- [ ] T027 [US1] Run the offline checks:
  - `validate`;
  - `python -m pytest -q tests/`;
  - `php tests/criteria_harness.php`;
  - `publish_moodle.py --slug <one in-flight course> --dry-run`, where every module shows a completion value.

  Then deploy `local_ltuse` to `/home/ltuse` with LF line endings, run `admin/cli/upgrade.php` and purge caches. Run `apply`, then `drift`, which should say `No differences.` Save the output outside the repo · moodle/site/settings/completion.yaml
- [ ] T028 [US1] Run quickstart V2, V3 and V4 with `ltct-test-*` learners X, Y and Z on two published courses:
  - **V2**: completion is on, every lesson shows "Done: View", the quiz shows pass or grade, and course completion is ALL over every item.
  - **V3**: course 1 is complete after `completion_regular_task`, and course 2 has one lesson done.
  - **V4**, each case with the publisher's output and the learners' state:
    - republish unchanged;
    - add a lesson: X keeps the same `course_completions` id and date;
    - remove a lesson Y had not done: Y becomes complete;
    - a page set to manual by hand: exit 1 naming it, and no ticks change.

  Record the outcomes in research R2 and R3 "Verify" · specs/004-progress-reporting/research.md
- [ ] T029 [US1] Run quickstart V7 with spec 009's V7, on the same Android device. As a test learner: download, go offline, open two lessons, submit the quiz, then reconnect. All three appear in the completion report, and the My courses bar moves. Record the device, the app version and the dates shown (expected: sync time) in research R5 · specs/004-progress-reporting/research.md

**Checkpoint**: US1 works on its own. Completion is uniform, set by the publish, synced from the app, and never erased by a republish (FR-001 to FR-003, FR-011, SC-001).

---

## Phase 4: User Story 2, a learner sees their own progress (P1)

**Goal**: From My courses, a learner sees which courses are finished, how far through the others they are, and which lessons are still to do.

**Independent test**: quickstart V8. A test learner in three courses (one complete, two part-way) can tell, from the landing page alone, which is finished and what comes next.

Files: `moodle/site/settings/completion.yaml`, `specs/004-progress-reporting/research.md`, `specs/004-progress-reporting/plan.md`

Depends on US1, because progress shows only once completion is tracked.

- [ ] T030 [US2] Run quickstart V8 with one `ltct-test-*` learner in three courses:
  - a progress bar per course;
  - the complete course under Past, and only the other two under In progress;
  - Done or To do on each lesson of a part-way course.

  Record which grouping is shown first. Check `block_myoverview/displaygroupingall` and its siblings (R6). If hiding "All" makes "In progress" the first view, declare those settings in `settings/completion.yaml`, each with a `why`, then re-apply and run `drift`. Record the outcome in research R6 · moodle/site/settings/completion.yaml
- [ ] T031 [US2] Run SC-004 with 2–3 real partner learners: within one minute of logging in, can each say what they have finished and what comes next? Record their findings on the course production tracker issue, not in the repo. If any could not find their next lesson, add the "next incomplete lesson" link to spec 007's scope, citing R6. **Do not close until T004 has recorded the maintainer's decision #1** · specs/004-progress-reporting/plan.md

**Checkpoint**: US2 works on its own (FR-004, SC-004), or the gap has been handed to spec 007 with the evidence.

---

## Phase 5: User Story 3, a manager sees their own organisation's progress, and only theirs (P2)

**Goal**: One generated report per organisation, scoped by fixed conditions and opened only by that organisation's managers cohort. Core's in-course reports are scoped by separate groups. *(Amended 2026-10-05: shared courses have no organisation groups (spec 002 open courses, PR #86); a manager has the in-course reports only in an organisation-only course, where `orgmanager` is enrolled (002 R2).)*

**Independent test**: quickstart V1, V5 and V6. With test organisations A and B, every report and export manager A can reach holds only A's learners.

Files: `scripts/site_config.py`, `tests/test_site_config.py`, `moodle/local_ltuse/classes/siteconfig/reports.php`, `moodle/local_ltuse/classes/siteconfig/inspector.php`, `moodle/local_ltuse/classes/siteconfig/applier.php`, `moodle/local_ltuse/classes/siteconfig/drift.php`, `moodle/site/reports.yaml`, `moodle/site/roles.yaml`

Builds on spec 002's organisations, managers cohorts and `ltct_org` field. It does not depend on US1's code, but V5 needs US1's published, tracked test courses.

**Wave 1, tests first (they must fail):**

- [X] T032 [P] [US3] Write `reports.yaml` validation and expansion cases on `fixture-*` organisations:
  - **Expansion**: one `per: organisation` template with two organisations gives two reports. Their areas are `org:fixture-a:progress` and `org:fixture-b:progress`. `{org}` becomes the organisation's name in `name`, and its key in condition values and the audience cohort.
  - **Scope**: a template missing any of `user:profilefield_ltct_org = {org}`, `role:name = student` and `enrol:plugin = cohort` fails. So does a second audience. *(**Amended by spec 016 decision 2 option (a) (Doug, 2026-10-05 (scope review)):** the scope condition is `cohort:idnumber = ltct:org:{org}`, a text condition; the Organisation column is dropped; `validate` refuses `user:profilefield_ltct_org` conditions.)* *(**Amended by spec 002 R10 (a0389bc, PR #92; note 2026-10-05):** the delivery condition is `enrol:plugin` not equal to `manual`, not `enrol:plugin = cohort`, so cohort sync and the organisation-enrolment instance both count (`SCOPE_CONDITIONS`, `scripts/site_config.py`; research R10's amendment).)*
  - **Audiences**: an `allusers` audience on any report fails.
  - **Names**: an area over 100 characters fails, as does a duplicate area or a key not matching `[a-z][a-z0-9-]*`.
  - **Keys**: a missing `why`, a missing `source` or an unknown key fails.
  - **Labels**: a `name` or `heading` that fails T007 fails.
  - **Payload**: the rendered `reports` array has data-model.md's "Rendered payload additions" shape.

  · tests/test_site_config.py

**Wave 2, implementation (independent files):**

- [X] T033 [P] [US3] Load `reports.yaml` as an optional top-level file, following data-model.md "Report":
  - validate every rule T032 checks, using T007 for labels;
  - expand `per: organisation` from `organisations.yaml` the way `_expand()` does cohorts;
  - render the `reports` array last in `build_payload()`;
  - count reports in `_summary()`.

  `schedule` and `sorting` stay unknown keys until T041 and T060. This makes T032 pass · scripts/site_config.py
- [X] T034 [P] [US3] Write `local_ltuse\siteconfig\reports`, which checks and applies the `reports` array with the APIs T001 confirmed:
  - **Find**: each report by `component = local_ltuse` and `area`, never by id.
  - **Absent**: created with `create_report(default = false)`, then columns (order, heading, aggregation), conditions with `set_condition_values()`, filters and audiences (`audiences\base::create()`).
  - **Present and differing**: name, source, uniquerows, columns, conditions, filters or audiences are set back, reported `changed` with the part that differed.
  - **Unknown identifier**: an `entity:name` the datasource lacks is `unknown`, and blocks.
  - **Missing cohort**: an audience whose cohort is missing is `missing`, and blocks only that report.
  - **Undeclared**: a `local_ltuse` report nobody declares is `extra`, and kept.
  - **Never** runs a report, and never prints a row or a count (constitution III).

  · moodle/local_ltuse/classes/siteconfig/reports.php
- [X] T035 [P] [US3] Declare `rows: [7, 16]` and a `purpose`, then the `progress` template exactly as in contracts/declaration.md, with T002's corrected identifiers and no `schedule` yet · moodle/site/reports.yaml
- [X] T036 [P] [US3] Add `report/progress:view: allow` and `report/completion:view: allow` to `orgmanager` (R8). Confirm `validate`'s `ORGMANAGER_DENY` check still passes, and that `orgmanager` still has no `moodle/site:accessallgroups` · moodle/site/roles.yaml

**⟶ Wait for T034, then:**

- [X] T037 [US3] Hand `reports` to the reports class:
  - for checking, in `inspector.php`, counting its blocking results in `has_blocking()`;
  - for applying, in `applier.php`, last, after every spec 002 array;
  - for drift, in `drift.php`.

  · moodle/local_ltuse/classes/siteconfig/inspector.php

**⟶ Wait for Wave 2 and T037, then verify on the instance (sequential):**

- [ ] T038 [US3] Copy `moodle/site/` to the scratchpad and add test organisations A and B, plus an empty one, to the copy only. Deploy `local_ltuse`, run the CLI upgrade, then run `apply --site-dir <scratch>` twice. The second run changes nothing.
  - **V1**: rename one column by hand. `drift` reports that report `changed`, and `apply` sets it back.
  - **SC-005, rebuild**: delete test organisation A's report by hand, then run `apply`. It is `created` again with the same area, columns, conditions and audience, and `drift` says `No differences.`
  - **V5, first step**: confirm every `entity:name` against the instance. Fix `reports.yaml` and re-apply if any is `unknown`.

  Save the output outside the repo · moodle/site/reports.yaml
- [ ] T039 [US3] Run quickstart V5 and V6 as managers A and B, with T028's courses, a manually enrolled pilot learner and `ltct-test-*` accounts:
  - **The organisation's own report**: A's report has A's learners only, with every FR-006 column. *(Amended 2026-10-05: the columns FR-006 names as amended, the ones `reports.yaml` `progress` declares: Learner, Course, Enrolled, Started, Progress, Quiz result, Completed, Last active in course. No Organisation or Group column.)*
  - **Another organisation's report**: B's report, opened by id, is refused.
  - **Filters**: no filter, set or cleared, shows a B learner, the pilot or a manager.
  - **Downloads**: CSV and Excel hold the same rows. Save them outside the repo.
  - **Web services**: `core_reportbuilder_list_reports` and `core_reportbuilder_retrieve_report`, called with A's own token, return only A's report.
  - **A moved learner** leaves A's report and appears in B's.
  - **An empty organisation** shows an empty report, not an error.
  - **Two cohorts**: an A learner enrolled in the same course through a second A cohort appears once for that course. If they appear twice, set `enrolment:timecreated` to `aggregation: min` and `group:name` to `aggregation: groupconcatdistinct` in `reports.yaml` (T002 confirmed both), re-apply and re-check. *(`group:name` is no longer declared (fe7e77c, spec 002 open courses, PR #86; first reached main through PR #85, d8c1d5b), so the fallback is `enrolment:timecreated` `min` alone.)*
  - **V6**: both in-course reports show A's group only, and group 0 or B's group id in the URL is refused. *(**Superseded 2026-10-05** by spec 002's open courses (PR #86; 002 R2; spec 002 plan, cross-spec "004" follow-ups): shared courses have no organisation groups, and `orgmanager` is enrolled only in organisation-only courses. Run quickstart V6 as amended: in an organisation-only course. Still open.)*

  Record the outcomes in research R7 and R8 "Verify" · specs/004-progress-reporting/research.md

**Checkpoint**: US3 works on its own. 0 rows outside a manager's scope in any report or download (FR-005, FR-006, FR-008, SC-002).

---

## Phase 6: User Story 4, managers export and schedule reports (P3)

**Goal**: Each organisation report is emailed weekly to its managers as Excel, viewed as each recipient. Any learner's full progress record can be exported (FR-014).

**Independent test**: quickstart V9's schedule and export bullets. A test manager receives a scheduled copy holding only their organisation's rows, and an approved data export includes completion.

Files: `scripts/site_config.py`, `tests/test_site_config.py`, `moodle/local_ltuse/classes/siteconfig/reports.php`, `moodle/site/reports.yaml`, `specs/004-progress-reporting/research.md`

Depends on US3 (the reports it schedules).

**Wave 1, test first (it must fail):**

- [X] T040 [P] [US4] Write schedule cases:
  - `recurrence: weekly` renders as T001's weekly constant (`3`);
  - `format: excel` renders unchanged;
  - `viewas: recipient` renders as `userviewas: -1`, and `viewas: creator` fails;
  - `send_when_empty: 0` renders as T001's "don't send" `reportempty` value;
  - a missing `subject` fails, and so does a `subject` that fails T007;
  - a schedule on a report with no audience fails.

  · tests/test_site_config.py

**Wave 2, implementation (sequential: T041 and T042 are different files, but T043 needs both):**

- [X] T041 [P] [US4] Accept, validate and render `schedule`, following data-model.md "Report" and T040. This makes T040 pass · scripts/site_config.py
- [X] T042 [P] [US4] Add schedules to the reports class:
  - create it with `schedules\base::create()` and the `message` type, sent to the report's audiences, with `userviewas` set to recipient;
  - compare recurrence, format, `userviewas`, `reportempty`, subject and message, and set back any that differ, reported `changed: schedule`;
  - the owner is the account `apply` runs as (R12).

  · moodle/local_ltuse/classes/siteconfig/reports.php
- [X] T043 [US4] Add the `schedule` block to the `progress` template, exactly as in contracts/declaration.md · moodle/site/reports.yaml

**⟶ Wait for Wave 2, then verify on the instance:**

- [ ] T044 [US4] Deploy the plugin and `apply --site-dir <scratch>`. Run `\core_reportbuilder\task\send_schedules` by hand (quickstart V9, first bullet). Managers A and B each receive one Excel file holding only their own rows. The empty organisation sends nothing. Then make an approved data export for test learner A1 (R13). It includes completion for both courses and each lesson's state. Keep the mails and files outside the repo. Record the outcomes in research R12 and R13 "Verify" · specs/004-progress-reporting/research.md
- [ ] T045 [US4] Run SC-003 with 2–3 real partner managers: each finds, reads and downloads their own organisation's report without help. Record their findings on the tracker issue, not in the repo. **Do not close until T004 has recorded the maintainer's decision #2** · specs/004-progress-reporting/plan.md

**Checkpoint**: US4 works on its own (FR-007, FR-014, SC-003).

---

## Phase 7: User Story 5, the maintainer sees programme-wide totals (P3)

**Goal**: The site team gets three reports:
- a programme report, of completions per course;
- a per-competency table, with one row for each of the 42 framework competencies;
- a pilot report.

Courses carry their competencies and target level as locked fields, labelled as aims.

**Independent test**: quickstart V9's programme and pilot bullet, and V10. Per-course counts equal the sum of the organisation reports, pilots excluded. The competency table has 42 rows, and an uncovered competency shows 0.

Files:
- **Publisher**: `scripts/moodle_payload.py`, `scripts/check_moodle_payload.py`, `scripts/publish_moodle.py`
- **Site configuration**: `scripts/site_config.py`, `moodle/site/course-fields.yaml`, `moodle/site/reports.yaml`, `moodle/site/roles.yaml`
- **Tests**: `tests/test_payload_completion.py`, `tests/test_site_config.py`, `tests/test_publish_moodle.py`, `moodle/local_ltuse/tests/competency_coverage_test.php`
- **Plugin services** (the schema and privacy provider are T051 and T052, in Phase 2): `moodle/local_ltuse/db/services.php`
- **Plugin site configuration**: `moodle/local_ltuse/classes/siteconfig/coursefields.php`, `moodle/local_ltuse/classes/siteconfig/competencies.php`, `moodle/local_ltuse/classes/siteconfig/{reports,inspector,applier,drift}.php`
- **Plugin web service**: `moodle/local_ltuse/classes/external/set_course_competencies.php`
- **Plugin datasource**: `moodle/local_ltuse/classes/reportbuilder/datasource/competency_coverage.php`, `moodle/local_ltuse/classes/reportbuilder/local/entities/competency.php`, `moodle/local_ltuse/classes/reportbuilder/local/entities/coverage.php`, `moodle/local_ltuse/lang/en/local_ltuse.php`
- **Process**: `process/stages/07-pilot.md`

Depends on US3 (the reports class) and US1 (the publisher sequence it extends).

**Wave 1, tests first (they must fail):**

- [X] T046 [P] [US5] Add the manifest metadata cases on a synthetic folder:
  - `competencies` is the frontmatter list, verbatim and in order, `&` included;
  - `target_outcome_level` is verbatim, or `None` when absent;
  - `check_moodle_payload` refuses a competency that is not in `competencies.yaml`.

  · tests/test_payload_completion.py
- [X] T047 [P] [US5] Write site configuration cases. Use a synthetic `competencies.yaml` for the failing cases, and the real one only for the 42-entry and `sortorder` checks.
  - **Course fields** (`course-fields.yaml`):
    - a `shortname` not matching `^ltct_[a-z0-9_]+$` fails, and so does a `type` other than `text`;
    - a missing `ltct_competencies` or `ltct_target_level` fails;
    - `everyone`, `teachers` and `nobody` render as `2`, `1` and `0`;
    - a name failing T007 fails.
  - **Competency list**:
    - the render from the real file has 42 entries, `Meta` is left out, and `sortorder` is 1-based across categories;
    - a duplicate name fails, as does a name over 255 characters or one with `[`, `]` or a control character.
  - **`competency-coverage`**:
    - any condition fails;
    - any audience other than `systemrole manager` fails;
    - a column outside the `competency` and `coverage` entities fails;
    - the strict label rules apply.
  - **`sorting`**: a column that is not in `columns` fails, and so does a direction other than `asc` or `desc`.
  - **The other two templates**: `programme` and `pilots` validate.

  · tests/test_site_config.py
- [X] T048 [P] [US5] Add publisher cases:
  - the course create and update send `customfields` with `ltct_competencies` as `"[A] [B]"`, in frontmatter order, and `ltct_target_level` as the level, or `""`;
  - a `Meta` name is dropped, with `competencies  skipped Meta: <names>` printed;
  - `local_ltuse_set_course_competencies` is called after `ensure_course` with the filtered list;
  - a returned set that differs prints both sets, and exits 1 after the summary;
  - `--dry-run` lists the call and sends nothing.

  · tests/test_publish_moodle.py
- [X] T049 [P] [US5] Write the PHPUnit test, using synthetic data in the PHPUnit database (`resetAfterTest`):
  - the report has one row per non-retired competency, and an uncovered one shows 0, not NULL;
  - a manually enrolled pilot is not counted;
  - an `orgmanager` cohort enrolment is not counted;
  - a learner in two cohorts counts as two enrolments, one learner and one completion;
  - a suspended enrolment drops from the enrolment count;
  - a retired competency is hidden;
  - sorting on `competency:name` follows `sortorder`;
  - no datasource, entity or column string in `lang/en/local_ltuse.php` holds a word T007's strict rule refuses.

  It runs where T003 decided · moodle/local_ltuse/tests/competency_coverage_test.php

**Wave 2, implementation (independent files):**

- [X] T050 [P] [US5] Add `competencies` (verbatim frontmatter list) and `target_outcome_level` (verbatim or `null`) to the manifest. This makes T046's manifest cases pass · scripts/moodle_payload.py
- [X] T053 [P] [US5] Add the datasource, entity and column strings, worded as aims, with the headings in data-model.md "Per-competency report". This makes T049's lang case pass · moodle/local_ltuse/lang/en/local_ltuse.php
- [X] T054 [P] [US5] Declare `rows: [7]`, the category `LTC curriculum`, and the two fields, exactly as in data-model.md "Course fields", each with a `why` · moodle/site/course-fields.yaml
- [X] T055 [P] [US5] Write `local_ltuse\siteconfig\coursefields`, which checks and applies the category and fields through `course_handler`:
  - the category is found by name, and fields by `shortname`;
  - absent: created;
  - different: set back, reported `changed`;
  - undeclared: `extra`, never deleted, as spec 002's profile fields are.

  · moodle/local_ltuse/classes/siteconfig/coursefields.php
- [X] T056 [P] [US5] Write `local_ltuse\siteconfig\competencies`. Its subject is `competency <name>`, and it follows data-model.md "Competency list" Lifecycle:
  - absent: inserted, reported `created`;
  - category or sortorder differs, or the row is retired: set back, reported `changed`;
  - live but undeclared: `extra`, set to `retired = 1`, with its map rows kept;
  - never deletes a row;
  - never reads or prints `local_ltuse_course_comp`.

  · moodle/local_ltuse/classes/siteconfig/competencies.php
- [X] T057 [P] [US5] Write `local_ltuse_set_course_competencies(courseid, competencies)`, following contracts/publish.md:
  - **Access**: it needs `local/ltuse:publish` in the course context, and refuses a course whose `idnumber` does not start with `ltct:`.
  - **One delegated transaction**: it resolves every name against non-retired rows. One unknown name throws `invalid_parameter_exception` ("competency '<name>' is not on this site: run site_config.py apply"), and the map is unchanged.
  - **Write**: it replaces the course's rows.
  - **Returns** `added`, `removed`, and `competencies` read back after the write.
  - It is idempotent.

  · moodle/local_ltuse/classes/external/set_course_competencies.php
- [X] T058 [P] [US5] Write the two entities.
  - **`competency`** has `name` and `category`. `name` sorts on `sortorder` through `set_is_sortable(true, [sortorder])`.
  - **`coverage`** has `courses`, `indelivery`, `enrolments`, `learners` and `completions`:
    - each is `TYPE_INTEGER`, with `set_disabled_aggregation_all()`;
    - each value is one correlated subquery on `competencyid = {c}.id`, as data-model.md "Per-competency report" defines;
    - the student role is matched by `shortname` through `{role}`, never by id;
    - only `enrol = 'cohort'` counts, never manual. *(**Amended by spec 002 R10 (a0389bc, PR #92; note 2026-10-05):** the course's organisation-enrolment instance (`enrol = 'self'`, `customchar1 = 'ltct:orgenrol'`) counts as delivery too, in every count including "Of which in delivery" (`coverage.php` `delivery_instance()`). Manual never counts.)*
  - Neither entity has a user, course or enrolment entity, or a level column.

  · moodle/local_ltuse/classes/reportbuilder/local/entities/coverage.php

**⟶ Wait for T053 and T058 (T051 was done in Phase 2), then:**

- [X] T059 [US5] Write the `competency_coverage` datasource:
  - its main table is `local_ltuse_competency`, with the base condition `retired = 0` set through `add_base_condition_sql()`;
  - it adds the `competency` and `coverage` entities, and nothing else;
  - its default columns, conditions and filters are the `competency` ones.

  · moodle/local_ltuse/classes/reportbuilder/datasource/competency_coverage.php

**⟶ Wait for Wave 2 and T059, then (shared files, sequential):**

- [X] T060 [US5] In `site_config.py`, make four changes:
  - **Course fields**: load `course-fields.yaml`, validate it, and render `course_field_category` and `course_fields`.
  - **Competency list**: render `competencies` from the repo-root `competencies.yaml`, without `META_CATEGORY`.
  - **Sorting**: accept and validate `sorting` on reports.
  - **`competency-coverage`**: enforce its rules, with T007's strict mode.

  The payload order is course field category, course fields, competencies, then reports. This makes T047 pass · scripts/site_config.py
- [X] T061 [US5] In the reports class, apply `sorting` with `toggle_report_column_sorting()`, and compare it in drift. On `competency-coverage`, a condition added by hand is `changed`, and apply removes it · moodle/local_ltuse/classes/siteconfig/reports.php
- [X] T062 [US5] Hand `course_field_category` and `course_fields` to `coursefields`, and `competencies` to `competencies`, in `inspector.php`, `applier.php` and `drift.php`. Apply order, after spec 002's arrays: the course field category, course fields, competencies, then reports · moodle/local_ltuse/classes/siteconfig/applier.php
- [X] T063 [US5] Register `local_ltuse_set_course_competencies` as a `write` function, and add it to the publisher's service · moodle/local_ltuse/db/services.php
- [X] T064 [US5] Change the publisher to follow contracts/publish.md steps 1, 2 and 7:
  - `customfields` on course create and update;
  - drop `Meta` names, printing them;
  - call `set_course_competencies` and print `competencies  N added, M removed`;
  - exit 1 after the summary if the read-back differs.

  This makes T048 pass · scripts/publish_moodle.py
- [X] T065 [US5] Make three declaration changes:
  - In `roles.yaml`, add `moodle/course:changelockedcustomfields: allow` to `ltcpublisher` (R11).
  - In `reports.yaml`, add the `programme`, `competency-coverage` and `pilots` templates. `pilots` is `progress` without `per: organisation` and the `ltct_org` condition, with `enrol:plugin = manual`, the `systemrole manager` audience and no schedule (R10). *(Note 2026-10-05: `progress` has since lost its Organisation column and `ltct_org` condition (spec 016 decision 2 option (a), Doug, 2026-10-05 (scope review); d395494, PR #84), so the two now differ the other way: merged `pilots` keeps the `user:profilefield_ltct_org` column, headed Organisation, and `progress` no longer has it (`moodle/site/reports.yaml`).)*
  - In `07-pilot.md`, say in one line that the pilot learner is enrolled with the course's manual enrolment method, never through a cohort (R10).

  · moodle/site/reports.yaml

**⟶ Wait for T060–T065, then verify (sequential):**

- [ ] T066 [US5] Run the offline checks: `validate`, `python -m pytest -q tests/` and `php tests/criteria_harness.php`. Run T049's PHPUnit where T003 decided, and record that it ran there, never on `ltuse.net`. Deploy `local_ltuse` and run the CLI upgrade. Confirm the two tables exist; T027's first upgrade created them. Then run `apply --site-dir <scratch>` twice. The second run changes nothing · moodle/local_ltuse/tests/competency_coverage_test.php
- [ ] T067 [US5] Run quickstart V9's programme and pilot bullet, and every V10 bullet, with `ltct-test-*` accounts and courses only:
  - **Rows**: 42, in `competencies.yaml` order, and 0 where no course aims at it.
  - **Courses columns**: Translation Tools shows 2 and Paratext 1. After republishing A without Paratext, it shows 0. "Of which in delivery" is 0 with manual enrolment only, and 1 with cohort sync. *(2026-10-05, spec 002 R10: an enabled organisation-enrolment instance alone also gives 1; an ordinary self-enrolment instance does not. Not yet run.)*
  - **P, D1, D2 and M**: enrolments 2, learners 2, completions 1. A second cohort for D1, then a suspended D2, change the counts as quickstart says. *(Note 2026-10-05, spec 002 R2: M's managers cohort is enrolled only in an organisation-only course, so run this in an organisation-only test course A; see quickstart V10's note.)*
  - **Agreement**: the courses column agrees with COVERAGE.md, and completions with the organisation reports. The programme report's counts equal A's and B's totals. The pilots report lists only P.
  - **Fail closed**: a missing competency, and a retired one, behave as quickstart says.
  - **Access**: refused for an orgmanager and a plain user, in the UI and through the web services.
  - **Hand edits**: a condition added by hand is removed by apply.

  The fail-closed check uses a scratch `competencies.yaml`. Record the outcomes in research R10, R11 and R15 "Verify" · specs/004-progress-reporting/research.md
- [ ] T068 [US5] **Do not close until T004 has recorded the maintainer's decision #6.** If the decision needs a change, such as excluding a learner who also has a manual enrolment in the course, raise it as a new task before closing · specs/004-progress-reporting/plan.md

**Checkpoint**: US5 works on its own. Programme-wide counts per course and per declared competency, pilots kept apart, and no learner shown at a level (FR-010, FR-012, FR-013).

---

## Phase 8: Polish and cross-cutting

Files: `moodle/local_ltuse/README.md`, `moodle/site/README.md`, `moodle/REQUIREMENTS.md`, `specs/001-site-config-as-code/contracts/declaration.md`, `specs/002-org-structure-cohorts/contracts/declaration.md`

**Wave 1, independent:**

- [X] T069 [P] List what the plugin now relies on, and why (Principle XI):
  - the completion data objects it writes through;
  - its read of `course_completion_criteria` by `course`;
  - the report lookups by `component` and `area`, and the customfield category lookup by name;
  - the datasource's raw reads: `{course}`, `{enrol}`, `{user_enrolments}`, `{user}`, `{role}` and `{course_completions}`, with the columns each uses;
  - that the `coverage:*` and `competency:*` column identifiers are frozen once released;
  - that each Moodle branch re-checks the datasource and entity base classes against `reportbuilder/UPGRADING.md`.

  · moodle/local_ltuse/README.md
- [X] T070 [P] Add `reports.yaml`, `course-fields.yaml` and `settings/completion.yaml` to the file table. Say in plain language that report downloads and scheduled attachments are saved outside the repository folder, as 002 says for the upload CSV (R14) · moodle/site/README.md
- [X] T071 [P] Update rows #7 and #16 to what has actually been verified, citing this spec (constitution X):
  - Row #7 is built and verified, except the two "simple" criteria. Mark them "verified when SC-003's and SC-004's 2–3 real partner users have used it" unless T031 and T045 are done.
  - Row #16 gains the report exports.
  - Say that production scheduled mail belongs to spec 015.

  · moodle/REQUIREMENTS.md
- [X] T072 [P] Link this spec's contract as the addition for `reports.yaml`, `course-fields.yaml`, `settings/completion.yaml` and the four new item types · specs/001-site-config-as-code/contracts/declaration.md
- [X] T073 [P] Note that `orgmanager` gains `report/progress:view` and `report/completion:view`, and that each organisation also generates one report and one schedule, linking this spec · specs/002-org-structure-cohorts/contracts/declaration.md

**⟶ Wait for Wave 1, then:**

- [ ] T074 Clean up the instance:
  - delete every `ltct-test-*` account, and the scratch organisations' reports, schedules, cohorts and categories. Do it by hand, because apply never deletes;
  - delete the test courses;
  - with the tracked `moodle/site/`, run `apply`, then `drift`. It should say `No differences.`, or list only declared items that are not yet live.

  Save the output outside the repo · moodle/site/reports.yaml
- [ ] T075 Validate against the Success Criteria. SC-001 is T028; SC-002 is T039; SC-003 is T045; SC-004 is T031; SC-005 is T038's delete-and-reapply and T066's second apply. A full server rebuild is spec 015's, on the production VPS.
  - **SC-006**: run `git diff --stat main...HEAD` and `git ls-files '*.csv' '*.xlsx' '*.xls' '*.ods'`, and grep the branch for test organisation keys and `ltct-test-` names. No learner data, download or test key may be committed.

  Record the result in the PR description · specs/004-progress-reporting/tasks.md

---

## Dependencies & Execution Order

### Phases

- **Setup (Phase 1)**: T001–T003 have no dependencies. T004 runs at any time, but gates T031, T045 and T068.
- **Foundational (Phase 2)**: needs Setup (T001's confirmed APIs). It blocks every story.
- **US1 (Phase 3)**: needs Foundational. It is the MVP.
- **US2 (Phase 4)**: needs US1, because progress needs tracked completion.
- **US3 (Phase 5)**: needs Foundational. Its code can be built beside US1. Its instance checks (T039) need US1's tracked test courses.
- **US4 (Phase 6)**: needs US3 (reports.php, reports.yaml).
- **US5 (Phase 7)**: needs US3 (the reports class, T037's wiring), US1 (the publisher sequence, `services.php`) and US4, because US4's edits to `site_config.py`, `reports.php` and `reports.yaml` come first (see the shared-file list).
- **Polish (Phase 8)**: needs every story it documents.

```text
Setup ─▶ Foundational ─┬─▶ US1 ─┬─▶ US2
                       │        └───────────────────┐
                       └─▶ US3 ───▶ US4 ───▶ US5 ◀──┘ ─▶ Polish
```

### Files more than one story edits (always sequential, in story order)

- `scripts/site_config.py`: T007, then T033, then T041, then T060.
- `tests/test_site_config.py`: T008, then T032, then T040, then T047.
- `moodle/local_ltuse/classes/siteconfig/reports.php`: T034, then T042, then T061.
- `moodle/site/reports.yaml`: T035, then T043, then T065.
- `scripts/publish_moodle.py` and `tests/test_publish_moodle.py`: T013 and T026, then T048 and T064.
- `moodle/local_ltuse/db/services.php`: T025, then T063.
- `moodle/site/settings/completion.yaml`: T018, then T030 if needed.
- `moodle/site/roles.yaml`: T036, then T065.
- `moodle/local_ltuse/lang/en/local_ltuse.php`: T052, then T053.

### Within each story

Tests first, and failing. Then the pure classes and file declarations. Then the classes that use them, then the wiring. Last, the instance checks, which close the story.

## Parallel Examples

**US1, Wave 1 and 2.** Once T011–T013 exist and fail, all of these touch different files:

```text
T014 scripts/moodle_payload.py         T015 scripts/check_moodle_payload.py
T016 classes/completion_rule.php       T017 classes/criteria_diff.php
T018 settings/completion.yaml          T019 workflows/site-config.yml
T020 workflows/publisher-tests.yml
```

After T021: T022 (create_page), T023 (create_quiz) and T024 (set_course_completion) in parallel.

**US3.** T032 first, then T033 (site_config.py), T034 (reports.php), T035 (reports.yaml) and T036 (roles.yaml) together.

**US5.** Tests T046–T049 together. Then T050 and T053–T058 together, seven different files. T059 follows once its entities and strings exist.

**Across stories.** With two people, one builds US1 while the other builds US3's code (T032–T037). They meet at T039, which needs both.

## Implementation Strategy

### MVP: US1 only

1. Phase 1 and Phase 2.
2. Phase 3 (US1), through T029.
3. **Stop and validate.** Every published course tracks completion uniformly, a republish erases nothing, and the app syncs. Every later spec that reads completion (003, 006, 013) can start from here.

### Incremental delivery

1. US1, which makes completion trustworthy, then US2, which only verifies core and records SC-004.
2. US3, the scoped organisation reports. Partners can run their own programme from here.
3. US4, schedules and exports.
4. US5, the programme totals and the competency table, the largest piece of plugin code.
5. Polish. Rows #7 and #16 are updated in the same PR (constitution X).

Each checkpoint is a point where a PR can merge without breaking what came before. The plugin version is bumped once (T006), so every deploy of this branch installs the same stamp. The two tables appear at T027's first upgrade, and stay empty until US5.
