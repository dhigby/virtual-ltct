# Tasks: Simple administration tooling

**Input**: Design documents from `specs/008-admin-tooling/`
**Prerequisites**: plan.md, spec.md (US1–US5), research.md (R1–R18), data-model.md, contracts/cli.md, contracts/admin-service.md, quickstart.md (V1–V19)

**Tests**: Included. Each user story has an Independent Test in spec.md, and plan.md §Testing requires pytest (`tests/test_ltct_admin.py`), a PHP harness (`tests/admin_harness.php`), PHPUnit (`moodle/local_ltuse/tests/admin_test.php`) and instance checks V1–V19. Every test address is `@example.org`; every organisation key is `fixture-*`; no CSV fixture is committed (`.gitignore` ignores `*.csv`; build rows in memory). Instance evidence stays outside the repo and is recorded as counts and outcomes only.

**Blocking markers** — a task carrying one may be written, but is not closed until the blocker clears:
- **D1, D2, D3, D6, D11**: maintainer decisions (plan.md "Decisions on the plan's limits"). All approved by Doug on 2026-10-04 and cleared, except that D11's approved rule is a change to spec 016, so T067's switch now waits on ⛔ **016**.
- ⛔ **016**: needs spec 016's `protection\service` / `entitlement` on the branch (agreed names in research R5).
- ⛔ **006**: needs spec 006's `pathway\catalogue` / `assignments` / `pathway_courses_changed` (research R11).
- ⛔ **002-A**: needs the amendment to 002's `organisation\actions` contract (`do_<action>()` + manager wrapper, research R6), and either 002's T071 or this spec's T012.

Code that calls 016 or 006 guards with `class_exists()` and degrades as research R5/R11 describe, so it can merge before they do.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: US1–US5 (spec.md)

## Path Conventions

- CLI and helpers: `scripts/`
- Repo tests: `tests/`
- Plugin: `moodle/local_ltuse/`
- Site declarations: `moodle/site/`
- Guided command: `.claude/commands/`
- CI: `.github/workflows/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Skeletons and the version stamp every later task builds on.

- [X] T001 Bump `moodle/local_ltuse/version.php` to `2026100800` (agreed: 016 = 2026100500, 006 = 20261006NN; whichever merges second re-bumps above main, renumbers its `upgrade.php` savepoint and re-pins `site.yaml`), and re-pin `local_ltuse` in `moodle/site/site.yaml`
- [X] T002 [P] Create `scripts/admin_files.py` with empty, documented functions: `validate_file(kind, rows, declared_orgs)`, `guard_path(path, repo_root)`, `mask_email(email)`, `outcome_class(outcome)`, `confirmation_code(rows, outcomes)`, `read_csv(path)`; module docstring cites research R14, R15
- [X] T003 [P] Create `scripts/ltct_admin.py` skeleton: argparse with every command in contracts/cli.md (`check`, `list`, `template`, `intake`, `enrol course|pathway|mirror`, `unenrol`, `suspend`, `reactivate`, `move`, `managers`, `mentors assign|end`, `course-mentors`, `summary`), global `--show-people`, `--apply`, `--confirm`, hidden `--site-dir`; exit codes 0/1/2 as contracts/cli.md
- [X] T004 [P] Create `tests/test_ltct_admin.py` with a `FakeClient` (pattern from `tests/test_publish_moodle.py:21`) and helpers that build `@example.org` rows in memory
- [X] T005 [P] Create `tests/admin_harness.php` (pattern from `tests/org_access_harness.php`): loads `classes/admin/*_rules.php` without Moodle, prints pass/fail, exits non-zero on failure
- [X] T006 [P] Create empty pure classes `moodle/local_ltuse/classes/admin/{intake_rules,enrolment_rules,move_rules,course_mentor_rules}.php` (namespace `local_ltuse\admin`, no Moodle calls) with docblocks citing research R4

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The credential, the safety rails (path guard, masking, confirmation code, preview → apply), and the preflight. No story starts before this phase is done.

### Tests for the foundation

- [X] T007 [P] pytest for `guard_path` in `tests/test_ltct_admin.py`: refuses a folder containing a `.git` **directory**; refuses one containing a `.git` **file** (worktree); refuses a not-yet-existing subfolder of a git tree; refuses anything `is_relative_to` the repo root; still refuses with `git` absent from `PATH` (monkeypatch `shutil.which`); refuses when an ancestor cannot be read; accepts a plain temp dir (research R14)
- [X] T008 [P] pytest for `mask_email` and `confirmation_code` in `tests/test_ltct_admin.py`: masking gives `a***@example.org`; the code is stable across runs; changes when **any** input column changes even if the outcome does not; does **not** change with `--show-people`; is equal for `new`/`will_set_org`/`will_enrol`/`unchanged` (class *proceeds*) and differs for each `flagged_*`, `rejected`, `waits` (research R15)
- [X] T009 [P] pytest for the CLI preflight in `tests/test_ltct_admin.py`: missing `MOODLE_URL` or `MOODLE_ADMIN_TOKEN` exits 2 naming the variable and never printing a value; `MOODLE_ADMIN_TOKEN == MOODLE_TOKEN` exits 2; a preview's file-level `refusal` prints no code and exits 1

### Implementation of the foundation

- [X] T010 Implement `guard_path`, `mask_email`, `outcome_class`, `confirmation_code`, `read_csv` in `scripts/admin_files.py` exactly as research R14/R15: pure Python walk to the nearest existing ancestor looking for `.git` dir **or file**, fail closed; code = first 10 hex of SHA-256 over (row number, SHA-256 of the normalised input row, outcome class), never the masked key and never `changes[]`; `read_csv` rejects unknown columns (data-model §1). Makes T007–T008 pass
- [X] T011 Extend `scripts/moodle_client.py`: accept `token=` from `MOODLE_ADMIN_TOKEN` for the admin path (existing publishing behaviour unchanged); add bounded retries (3 attempts, back-off 2/4/8 s) for `URLError` and HTTP 5xx only, never for a Moodle `exception` body (research R15); add a pytest in `tests/test_ltct_admin.py` that a Moodle exception is not retried and a 503 is
- [ ] T012 ⛔ **002-A** Record the required amendment to 002's actions contract — each action split into an unchecked `do_suspend/do_reactivate/do_enrol(int $userid …)` that keeps the per-action rules (never a site admin, never the acting user, `access::may_enrol_into()` for enrolment) plus a manager wrapper adding `access::may_manage_account()` — in `specs/002-org-structure-cohorts/data-model.md` ("Management actions") and `specs/002-org-structure-cohorts/tasks.md` (T071, T064), citing 008 research R6. If 002's T071 is not built when US1 starts, build `moodle/local_ltuse/classes/organisation/actions.php` here to that amended contract, with `do_suspend` calling `destroy_user_sessions()` then `user_update_user((object)['id'=>…,'suspended'=>1])`
- [X] T013 Add capability `local/ltuse:administer` to `moodle/local_ltuse/db/access.php` in a block headed `// Spec 008: administration`: `captype write`, `contextlevel CONTEXT_SYSTEM`, `riskbitmask RISK_PERSONAL | RISK_DATALOSS | RISK_SPAM`, no archetypes; add its lang string to `moodle/local_ltuse/lang/en/local_ltuse.php` in an appended `// Spec 008` section
- [ ] T014 Add role `ltctadmin` to `moodle/site/roles.yaml`: system context, `allowassign: [mentor, teacher]`, and exactly the capabilities in research R12's table, each with a `why`: `local/ltuse:administer`, `webservice/rest:use`, `moodle/user:create`, `moodle/user:update`, `moodle/user:viewdetails`, `moodle/user:viewhiddendetails`, `moodle/site:viewuseridentity`, `moodle/cohort:view`, `moodle/cohort:assign`, `moodle/course:enrolconfig`, `enrol/cohort:config`, `enrol/self:config`, `enrol/self:manage`, `moodle/role:assign`, `moodle/course:managegroups`, `local/ltuse:manageprotection` — *written 2026-10-04; open until ⛔ **016**: `local/ltuse:manageprotection` is left commented out in `roles.yaml`, because apply refuses a capability the server does not define ("no such capability on this server"); it is uncommented with T015*
- [ ] T015 In `scripts/site_config.py`, add `ltctadmin` to 016's `PROTECTION_MANAGE_ROLES` allowlist with a comment giving the reason (research R5; agreed with 016 2026-10-04) — ⛔ **016** (the constant arrives with 016; until then add the role to the validator's list of known roles only); extend `tests/test_site_config.py` with a case that `ltctadmin` passes validation
- [X] T016 [P] Create `moodle/site/settings/admin.yaml`: `rows: [14, 11]`, `purpose`, `allowaccountssameemail: 0` (why: backstop for core creation paths; 008's guard is its own lock, research R2), `local_ltuse/coursementorsync: 0` (why: off until plan decision 11, research R10); confirm `python scripts/site_config.py validate` passes
- [X] T017 Declare the service in `moodle/local_ltuse/db/services.php`, block `// Spec 008: administration`: name `LTC administration`, shortname `ltuse_admin`, `requiredcapability local/ltuse:administer`, `restrictedusers 1`, `enabled 1`, `uploadfiles 0`, functions = every `local_ltuse_admin_*` in contracts/admin-service.md plus `core_webservice_get_site_info` (add each function entry as its task lands; start with `check` and `list`)
- [X] T018 Write `moodle/local_ltuse/cli/setup_admin_token.php` (`--username=<u> --token-file=<path> [--rotate]`): authorise with `(new webservice())->add_ws_authorised_user()`, create with `\core_external\util::generate_token()`, revoke on `--rotate` with `webservice::delete_user_ws_token()`; write the token to a mode-600 file, never print it; refuse a user without `local/ltuse:administer`. First confirm each signature on `MOODLE_502_STABLE` (`webservice/lib.php:247, 483`) and record it in research.md R12 (research R12, constitution XI)
- [X] T019 [P] Write `moodle/local_ltuse/classes/admin/masking.php` (`mask_email(string): string`, same rule as `admin_files.mask_email`) and add a harness case comparing both outputs for five fixed `@example.org` addresses in `tests/admin_harness.php`
- [X] T020 Write `local_ltuse_admin_check` in `moodle/local_ltuse/classes/external/admin_check.php`: `validate_context(system)`, `require_capability('local/ltuse:administer')`; returns each setting from research R13 with its value (`enrol_cohort/unenrolaction`, `tool_dynamic_cohorts/realtime`, `allowaccountssameemail`, `local_ltuse/coursementorsync`), the research R12 capabilities the caller lacks, whether 006 and 016 classes exist, and `level_available()` per level when 016 exists
- [X] T021 Write `local_ltuse_admin_list` in `moodle/local_ltuse/classes/external/admin_list.php`: `what` = `cohorts` (idnumbers starting `ltct:`) or `courses` (idnumber `^ltct:[^:]+$`, with category idnumber), optional `orgkey`
- [X] T022 Implement `check`, `list` and `template --kind intake|move|mentors|course-mentors|managers|suspension --out <path>` in `scripts/ltct_admin.py`: `check` refuses to continue (exit 2) if `enrol_cohort/unenrolaction != 3` or realtime is off, naming the setting; prints missing capabilities by name; `template` writes headers only (data-model §1) through `guard_path`; organisation keys read via `site_config.validate(site_dir)`
- [X] T023 Implement the shared preview → confirm → apply driver in `scripts/ltct_admin.py`: preview call → print counts per outcome, masked rows for non-`proceeds` outcomes, the code and the exact apply command; on `--apply --confirm C`: re-read file, preview again, recompute, refuse on mismatch (exit 1), then call the kind's `apply_*` **one row per call** with the fresh `expectedoutcome`; count `done` / `already_done` / `refused`; `already_done` counts as success (research R15)
- [X] T024 Wire CI: add `tests/admin_harness.php` to `.github/workflows/site-config.yml`; add `scripts/ltct_admin.py`, `scripts/admin_files.py` to `.github/workflows/publisher-tests.yml` `paths:` and `tests/test_ltct_admin.py` to its pytest list
- [ ] T025 Instance: run quickstart Prerequisites 1–6 and **V1** (path guard) and **V16**'s first half (`check` as a user without `local/ltuse:administer` is refused); record outcomes in research.md "Instance results"

**Checkpoint**: `ltct_admin.py check` passes against the build host with a per-person token; files inside any git tree are refused.

---

## Phase 3: User Story 1 — Bring a new intake of learners onto the system (P1) 🎯 MVP

**Goal**: A previewed, confirmed, repeatable intake creates accounts (neutral usernames, Moodle's password email), sets protection first when asked, then the organisation field, so dynamic cohorts place and enrol each learner.

**Independent Test**: spec.md US1 — fictitious learners held outside the tree; preview, apply, re-apply; accounts and cohort memberships exist; re-run changes nothing; nothing new in the repo. Quickstart V2–V6.

### Tests for User Story 1

- [X] T026 [P] [US1] Harness cases for `intake_rules::classify()` in `tests/admin_harness.php`: every outcome in data-model §2 — `new`, `unchanged`, `will_set_org` (incl. created-but-protection-unsettled), `will_enrol`, `flagged_other_org`, `flagged_suspended`, `flagged_protection` (existing account, `effective_level` below target), `waits` (target above `none` and 016 absent or `level_available` false), `rejected` (two live accounts share the email; course not allowed for the organisation)
- [X] T027 [P] [US1] Harness cases for `intake_rules::progress()` ("further along", research R15): same → `apply`; `new→will_set_org`, `new→will_enrol`, `new→unchanged`, `will_set_org→unchanged`, `will_enrol→unchanged` → `finish_or_already_done`; `new→flagged_*`/`rejected`/`waits` → `refused`
- [X] T028 [P] [US1] pytest for intake offline validation in `tests/test_ltct_admin.py`: missing required column; unknown column; malformed email; duplicate email (case-insensitive); undeclared organisation key; `protection` not in `none|email|firstname|pseudonym`; `pseudonym` present without `protection: pseudonym`; malformed `ltct:<slug>` in `courses` — each refuses the whole file with row numbers before any call
- [X] T029 [P] [US1] pytest for intake resume in `tests/test_ltct_admin.py` with `FakeClient`: after a simulated partial apply, re-running with the same code finishes the rest and reports `already done` (exit 0); a lost-response retry reports `already done`; a row that became `flagged_other_org` is refused (exit 1)
- [X] T030 [P] [US1] PHPUnit in `moodle/local_ltuse/tests/admin_test.php` (synthetic data): a created account has `confirmed=1`, `mnethostid=$CFG->mnet_localhost_id`, `auth=manual`, username matching `^ltc-[a-z2-7]{8}$`, preference `auth_forcepasswordchange=1`, no `ltct_org` until after protection; two concurrent applies for one email create one account; an existing account's names are never written

### Implementation for User Story 1

- [X] T031 [US1] Implement `intake_rules::classify()` and `progress()` in `moodle/local_ltuse/classes/admin/intake_rules.php` (pure; inputs: the row, the matched account summary or none, its `ltct_org`, `suspended`, `effective_level`, org minimum, `level_available`, active course set, allowed-course set); makes T026–T027 pass
- [X] T032 [US1] Implement `moodle/local_ltuse/classes/admin/intake_service.php` `preview(array $rows, bool $showpeople)`: first resolve every distinct course idnumber and `ltct:org:<key>` cohort; any missing → file-level `refusal` naming it (data-model §1); per row: match live accounts by email case-insensitively excluding deleted, gather state, call `intake_rules`, mask unless `$showpeople`
- [X] T033 [US1] Implement `intake_service::apply_row(array $row, string $expectedoutcome)`: lock from `\core\lock\lock_config::get_lock_factory('local_ltuse')` keyed on the lowercased email; re-match inside the lock; `progress()` decides; for `new`: build the user with `confirmed=1`, `mnethostid`, `auth='manual'`, `password=''`, neutral username `ltc-`+8 lowercase base32 (retry on collision), names, optional `country`, **no** `ltct_org`; `user_create_user($user, false, false)`; `set_user_preference('auth_forcepasswordchange', 1, $user)`; fire `\core\event\user_created`; after commit `setnew_password_and_mail($user)` (research R2)
- [ ] T034 [US1] ⛔ **016** In `intake_service::apply_row`, the protection steps (research R5): target = max(row level, `service::org_minimum($org)`); if target > `none`: `level_available($target)` false → `waits` before any write; after create, `entitlement::can_manage_protection($USER->id, $uid)`, `service::set_protection($uid, $target, $options, $USER->id)`, `is_settled()`/`effective_level()` check, `service::apply()` once if unsettled; on failure stop with `ltct_org` unset. Guard all of it with `class_exists('\local_ltuse\protection\service')`; when absent, any target > `none` is `waits`
- [ ] T035 [US1] In `intake_service::apply_row`, set the organisation: `profile_save_data((object)['id'=>$uid, 'profile_field_ltct_org'=>$key])` with **no other field**, then trigger `\core\event\user_updated::create_from_userid($uid)`; then per-row `courses` via ⛔ **002-A** `organisation\actions::do_enrol($uid, $courseid)` (Organisation enrolment, `customchar1='ltct:orgenrol'`) after `enrolment_rules`/`access::may_enrol_into()`
- [X] T036 [US1] Write `local_ltuse_admin_preview_intake` and `local_ltuse_admin_apply_intake_row` in `moodle/local_ltuse/classes/external/admin_preview_intake.php` and `admin_apply_intake_row.php` (params and returns exactly as contracts/admin-service.md; capability checks: `local/ltuse:administer` then `moodle/user:create`); add both to the `ltuse_admin` service (T017) and lang strings for every `reason`
- [X] T037 [US1] Implement `intake` in `scripts/ltct_admin.py` on the T023 driver: offline validation via `admin_files.validate_file('intake', …)`, `--site-dir` organisation keys, preview/apply; print the 002 R13 production-gate reminder when `check` reports 016 absent or not ready and any row has `courses` (research R16)
- [ ] T038 [US1] Instance: quickstart **V2, V3, V4, V6** (and **V5** ⛔ **016**); record counts/outcomes in research.md "Instance results"; V2 includes logging in with the emailed password

**Checkpoint**: a 30-row intake previews, applies, re-applies as no-op, and resumes after interruption.

---

## Phase 4: User Story 2 — Enrol a cohort into a course or pathway (P1)

**Goal**: Cohort sync under 002's organisation rules; disable, never delete; pathways kept in step with 006.

**Independent Test**: spec.md US2; quickstart V7, V8 (first half), V9 (disable half), V17.

### Tests for User Story 2

- [X] T039 [P] [US2] Harness cases for `enrolment_rules::decide(cohortidnumber, courseidnumber, categoryidnumber)` in `tests/admin_harness.php`, one per cell of research R7's table: `ltct:org:K` → Student in `ltct:published`; → Student in `ltct:org:K` only; refused in `ltct:org:K2`; `ltct:org:K:managers` → `orgmanager` only in `ltct:org:K`, refused in `ltct:published`; `ltct:mentors` refused everywhere; any other cohort refused (decision 8); `ltct:pilots`, `ltct:officehours`, non-`ltct:<slug>` refused
- [X] T040 [P] [US2] PHPUnit in `moodle/local_ltuse/tests/admin_test.php`: `ensure()` twice creates one instance (`customchar1='ltct:008'`, `customint2=0`); `ensure()` re-enables a disabled instance and reuses a hand-made one for the same cohort; `remove()` sets `ENROL_INSTANCE_DISABLED` and never deletes; members' grades and completion remain after `remove()`

### Implementation for User Story 2

- [X] T041 [US2] Implement `enrolment_rules::decide()` in `moodle/local_ltuse/classes/admin/enrolment_rules.php` (pure) to research R7's table; makes T039 pass
- [X] T042 [US2] Implement `moodle/local_ltuse/classes/admin/cohort_enrolment.php`: `preview(cohortid, courseid, action)`, `ensure(cohortid, courseid)` — find any existing `enrol_cohort` instance with `customint1 = cohortid` (enabled or not); else `enrol_get_plugin('cohort')->add_instance($course, ['customint1'=>…, 'roleid'=>…, 'customint2'=>0, 'customchar1'=>'ltct:008'])`; else `update_status(…, ENROL_INSTANCE_ENABLED)`; `remove()` → `update_status(…, ENROL_INSTANCE_DISABLED)`; both end by calling `course_mentor_sync::sync_course($courseid)` when that class exists (research R7, R10)
- [X] T043 [US2] Write `local_ltuse_admin_preview_cohort_enrolment` and `local_ltuse_admin_apply_cohort_enrolment` (one (cohort, course) pair per apply, `expectedoutcome`) in `moodle/local_ltuse/classes/external/`; capability `local/ltuse:administer` then `enrol/cohort:config` in the course context; add to the service and lang
- [X] T044 [US2] Implement `enrol course` and `unenrol` in `scripts/ltct_admin.py` on the T023 driver; production-gate reminder on shared-course `enrol` (research R16)
- [ ] T045 [US2] ⛔ **006** Implement the pathway path: `cohort_enrolment::preview_pathway()` runs `enrolment_rules` against every course of `catalogue::courses($key)` (key validated by `catalogue::parse_key()`, then `exists()`, `is_assignable()`); `local_ltuse_admin_apply_pathway_assignment` refuses unless every course is allowed, then `assignments::assign($key, $cohortid, true)`; pathway-made instances get `customchar2='pathway'`; CLI `enrol pathway` calls assignment once then `apply_cohort_enrolment` per course (research R11)
- [ ] T046 [US2] ⛔ **006** Observer on `\local_ltuse\event\pathway_courses_changed` in `moodle/local_ltuse/db/events.php` (block `// Spec 008`) and `classes/admin/observer.php`: for `other['added']` (cast ints) × `assignments::cohorts_for($key, true)` → `cohort_enrolment::ensure()`; never unenrol on `removed`; and `cohort_enrolment::reconcile_pathways()` for the hourly task (picks up re-shown courses)
- [ ] T047 [US2] Instance: quickstart **V7**, **V9** disable-not-delete half, **V17** ⛔ **006**; record results

**Checkpoint**: a cohort is enrolled into a course under the rules; removal keeps history; (with 006) pathways stay in step.

---

## Phase 5: User Story 3 — Routine changes without an LMS administrator (P2)

**Goal**: Suspend/reactivate, move between organisations with a counted dry run (incl. `sil` → Area), `enrol mirror`, managers and mentors cohorts, and an on-screen summary.

**Independent Test**: spec.md US3; quickstart V8, V10, V14, V15, V18.

### Tests for User Story 3

- [X] T048 [P] [US3] Harness cases for `move_rules::classify()` in `tests/admin_harness.php`: `kept` (new org cohort enabled in the shared course, or another active enrolment there), `gained`, `suspended_by_rule` (old org's organisation-only course), `lost` (shared course with no match → learner refused), `flagged_protection` (`effective_level` below new org minimum → refused)
- [X] T049 [P] [US3] pytest for suspension, move and managers file validation and their resume behaviour in `tests/test_ltct_admin.py` (one row per apply; `already done` on re-run)

### Implementation for User Story 3

- [ ] T050 [US3] ⛔ **002-A** Write `local_ltuse_admin_preview_suspension` / `apply_suspension` (one row, `suspend` bool, `expectedoutcome`) calling `organisation\actions::do_suspend()` / `do_reactivate()`; capability `local/ltuse:administer` then `moodle/user:update`; and `suspend`/`reactivate` in `scripts/ltct_admin.py` (file or `--email`) — *written 2026-10-04 (`classes/admin/suspension_service.php`, on the `actions.php` built under T012); open until T012's amendment to 002 is recorded*
- [X] T051 [US3] Implement `move_rules::classify()` in `moodle/local_ltuse/classes/admin/move_rules.php` (pure); makes T048 pass
- [ ] T052 [US3] Implement `moodle/local_ltuse/classes/admin/move_service.php` and `local_ltuse_admin_preview_move` / `apply_move` (one row, `expectedoutcome`, `expectedcourses`): refuse on any `lost` or `flagged_protection`; ⛔ **016** read `service::org_minimum(<new key>)` / `effective_level()`, **never** call `set_protection`; write only `profile_field_ltct_org` + `user_updated`; confirm `is_settled()` after (research R8) — *written 2026-10-04, guarded by `class_exists()` (without 016 every minimum counts as `none`); open until 016 is on the branch*
- [X] T053 [US3] Implement `enrol mirror --from K1 --to K2` in `cohort_enrolment` + CLI: for each enabled `ltct:org:K1` instance in `ltct:published`, `ensure(ltct:org:K2, course)`, previewed first (research R8)
- [X] T054 [US3] Implement `moodle/local_ltuse/classes/admin/membership_service.php` cohort part and `local_ltuse_admin_preview_cohort_members` / `apply_cohort_members`: only `ltct:org:K:managers` and `ltct:mentors`; refuse any cohort with a `component` and any `ltct:org:K`; check membership before `cohort_remove_member()`; preview note when a member of K joins K's managers cohort (research R9); CLI `managers`
- [X] T055 [US3] Implement `local_ltuse_admin_summary` in `moodle/local_ltuse/classes/external/admin_summary.php` (masked unless `showpeople`; member counts, suspended count, per-course enrolment counts, pathway-made instances whose course is in none of the cohort's enrolling pathways) and `summary --org K [--out]` in the CLI through `guard_path`
- [ ] T056 [US3] Instance: quickstart **V8**, **V10**, **V14**, **V15**, **V18**; record results

**Checkpoint**: the site team can suspend, move (with dry run), mirror, manage manager/mentor cohorts and see a summary without Moodle's admin screens.

---

## Phase 6: User Story 5 — Mentors follow their learners (P2)

**Goal**: Bulk mentor assignment and end-all; one-course and cohort mentors recorded; course mentors enrolled automatically as Teacher and removed by event.

**Independent Test**: spec.md US5; quickstart V11, V12, V13, V9 (course-mentor half).

### Tests for User Story 5

- [X] T057 [P] [US5] Harness cases for `course_mentor_rules::target(course state)` in `tests/admin_harness.php`: one-course row beats cohort rows beats default mentors; pilot (manual) learners excluded; "active" = `ue.status` active AND `enrol.status` enabled AND within `timestart`/`timeend` AND account not suspended; Student read from the instance `roleid` — *written 2026-10-04 (with cases for `diff()`); D2 approved 2026-10-04*
- [X] T058 [P] [US5] PHPUnit in `moodle/local_ltuse/tests/admin_test.php`: `role_unassign` of a mentor removes the `ltct:coursementor` enrolment, the `local_ltuse` Teacher assignment and the group membership in the same request; disabling a cohort-sync instance does the same for that cohort's course mentors; suspending the learner's account does the same; a mentor also enrolled another way loses Teacher; nothing happens while `local_ltuse/coursementorsync = 0`

### Implementation for User Story 5

- [X] T059 [US5] Add table `local_ltuse_course_mentor` to `moodle/local_ltuse/db/install.xml` and an `upgrade.php` step at `2026100800`: `id`; `courseid` int(10) FK `course.id`; `mentorid` int(10) FK `user.id`; `learnerid` int(10) **NOT NULL default 0**; `cohortid` int(10) **NOT NULL default 0**; `usermodified`, `timecreated`, `timemodified` int(10); unique index `(courseid, mentorid, learnerid, cohortid)`; indexes `(courseid)`, `(learnerid)`, `(cohortid)`, `(mentorid)` (data-model §3) — *savepoint and version are `2026100801`, not `2026100800`: the service and capability already shipped at `2026100800` with no schema change, so a site at that stamp would skip a step numbered `2026100800`; `site.yaml` re-pinned*
- [X] T060 [US5] Extend `moodle/local_ltuse/classes/privacy/provider.php` for `local_ltuse_course_mentor` (export for learner and mentor; delete on user deletion) and the existing `user_deleted` observer to remove that user's rows
- [X] T061 [US5] Implement `membership_service` mentor part and `local_ltuse_admin_preview_mentors` / `apply_mentors` (one row, or one learner of an end-all list): mentor must be in `ltct:mentors`; `role_assign($mentorroleid, $mentorid, context_user::instance($learnerid)->id)` with component `''`; `endmentoremail` → `role_unassign()` per learner; CLI `mentors assign|end` (research R9)
- [X] T062 [US5] Implement `course_mentor_rules::target()` in `moodle/local_ltuse/classes/admin/course_mentor_rules.php` (pure) to data-model §4; makes T057 pass — *written 2026-10-04, with the pure `diff()` the sync applies; D2 approved 2026-10-04*
- [X] T063 [US5] Implement `moodle/local_ltuse/classes/admin/course_mentor_sync.php`: `sync_course($courseid)` under a per-course `\core\lock`, returns counts; no-op when `local_ltuse/coursementorsync` is 0; one `enrol_self` instance per course (`customchar1='ltct:coursementor'`, `customint6=0`) created on first need; enrol with `enrol_user($instance, $uid, null)` then `role_assign($teacherroleid, $uid, $coursectx->id, 'local_ltuse', $instance->id)`; removal `role_unassign(… 'local_ltuse', $instance->id)` then `unenrol_user()`; group per mentor, idnumber `ltct:mentorgroup:<mentor id>`, name "Mentor group <n>" via `groups_create_group()`, membership via `groups_add_member()`/`groups_remove_member()`; `sync_learner()`, `sync_mentor()` (research R10) — *written 2026-10-04; D3 approved 2026-10-04; 012's re-plan still adopts the groups*
- [X] T064 [US5] Observers in `moodle/local_ltuse/db/events.php` (block `// Spec 008`) → `classes/admin/observer.php` exactly as contracts/admin-service.md's table: `role_assigned`/`role_unassigned` (mentor role, user context) → `sync_learner($event->contextinstanceid)` + `sync_mentor($event->relateduserid)`; `user_enrolment_created|updated|deleted` filtered on course idnumber and instance (never role) → `sync_course`; `enrol_instance_updated|deleted` → `sync_course`; every `user_updated` → `sync_learner`; ignore the `ltct:coursementor` instance
- [X] T065 [US5] Scheduled task `moodle/local_ltuse/classes/task/course_mentor_reconcile.php`, hourly in `db/tasks.php`: `sync_course` for every `ltct:` course except `ltct:officehours`; remove `local_ltuse`-component Teacher assignments with no reason; `cohort_enrolment::reconcile_pathways()`; counts only in output
- [X] T066 [US5] Write `local_ltuse_admin_preview_course_mentors` / `apply_course_mentors` (get-or-create / delete on `local_ltuse_course_mentor`, duplicate-key treated as success, then `sync_course`) and CLI `course-mentors [--remove]`
- [ ] T067 [US5] Instance: quickstart **V11**, **V12**, **V13**, then **V9** course-mentor half, with `coursementorsync` on in the `--site-dir` copy only; record results. `settings/admin.yaml` keeps `0` until ⛔ **016** narrows its course-mentor path to a shared mentor group (D11, approved 2026-10-04)

**Checkpoint**: mentors assigned in bulk; course mentors appear and disappear with their reason, in the same request.

---

## Phase 7: User Story 4 — A partner's own manager does it themselves (P3)

**Goal**: 008's share of US4: a template a manager fills in that the site team applies unchanged; manager scope itself is 002's page.

**Independent Test**: spec.md US4; quickstart V16 second half.

- [X] T068 [US4] Add a one-page manager guide section to `moodle/site/README.md` ("Asking for new accounts"): how to get a blank file (`ltct_admin.py template --kind intake`, sent by the site team), each column from data-model §1 in plain words, where to keep it (never in a repo folder), and that enrol/suspend/reactivate of one's own people is on the organisation page (002)
- [ ] T069 [US4] Instance: quickstart **V16** second half — as a `fixture-a` manager on 002's page, try to see, enrol, suspend and assign a mentor for a `fixture-b` learner; each refused (depends on 002's organisation page being built); record results

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T070 [P] Write `.claude/commands/manage-learners.md` (pattern: `publish-to-moodle.md`): frontmatter `allowed-tools: Bash(python scripts/ltct_admin.py:*)`; numbered steps (check → ask what to do → preview → explain in plain words → apply only on the operator's yes, passing the code); Rules: never Read/open/copy an intake file, never pass `--show-people`, never put files in the repo, never invent keys (offer `list` output), quote refusals verbatim, state 002 R13's production gate (research R16) — *D1 approved 2026-10-04*
- [X] T071 [P] Rewrite `moodle/site/README.md` "The site team's four steps" as `ltct_admin.py` recipes (intake, enrol, mirror then move, managers, mentors, suspend), each stating 002 R13's production gate where it enrols; fix line 217's mentor paragraph to point at `mentors assign` and the automatic sync (drop "with the organisation's group")
- [X] T072 [P] Update `moodle/local_ltuse/README.md`: new "Administration (008)" section (service, capability, role, token script, observers, task, table, `coursementorsync`); replace "No enrolment, grades or learner records" with "enrolment through the administration service and the organisation page; never grades"; add to the Principle XI section any raw read introduced (e.g. `cohort.component`) and, until `setup_publishing.php` is changed, its two direct `$DB` writes; note that removing `ltuse_admin` from `db/services.php` deletes its tokens
- [X] T073 [P] Update `CLAUDE.md`: `ltct_admin.py` and `admin_files.py` under "Maintainer scripts"; one line under "Delivery: Moodle" (site-team admin tool, its own token `MOODLE_ADMIN_TOKEN`, files outside every git tree)
- [X] T074 Update `moodle/REQUIREMENTS.md` rows **#14** (after Phase 3–4), **#8/#15** (after T052–T053), **#11** (after Phase 6) with built/verified status and the quickstart checks run, each in the PR that delivers it (constitution X)
- [X] T075 Run `python scripts/site_config.py validate`, `pytest -q tests/test_ltct_admin.py tests/test_site_config.py`, `php tests/admin_harness.php`; fix any failure
- [ ] T076 Instance: quickstart **V19** (no data file in `git status` / `git log --all --name-only --since=<run start>` in the repo and every worktree used; no `example.org` outside `tests/`); record result
- [ ] T077 Done gate (FR-014, SC-004, SC-006): 2–3 real ALTCs or organisation managers per quickstart "Done gate"; record findings in research.md naming testers only "tester 1/2/3" and role, never an Area/organisation or protection level/count; resolve or accept each. Row #14 is not marked done until this closes

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: none.
- **Foundational (Phase 2)**: after Setup; blocks every story. T012 (002-A) blocks T035's per-row courses and T050.
- **US1 (Phase 3)** and **US2 (Phase 4)**: after Foundational; independent of each other except that US1's per-row `courses` uses `enrolment_rules` (T041) — build T041 first if both run in parallel.
- **US3 (Phase 5)**: after Foundational; T053 uses `cohort_enrolment` (T042); T052 benefits from US1's organisation-field writer (T035).
- **US5 (Phase 6)**: after Foundational and T042 (`ensure`/`remove` call the sync); must land no later than spec 016 (016's course-mentor entitlement needs it).
- **US4 (Phase 7)**: T068 any time after T022; T069 needs 002's organisation page.
- **Polish (Phase 8)**: after the stories it documents; T077 after everything.

### External blockers

| Blocker | Tasks | Clears when |
|---|---|---|
| 002-A | T012, T035 (courses), T050 | 002's actions contract amended (T012) and `actions.php` exists (002 T071 or T012) |
| 016 | T015, T034, T038 (V5), T052 (protection reads), T067 (turning the sync on: D11's narrower course-mentor rule) | 016 merged to main and rebased in |
| 006 | T045, T046, T047 (V17) | 006 merged to main and rebased in |
| D1 | T070 | Cleared: approved 2026-10-04 |
| D2 | T057, T062, T067 (V13) | Cleared: approved 2026-10-04 |
| D3 | T063 | Cleared: approved 2026-10-04 (012's re-plan still adopts it) |
| D6 | T077 | Cleared: approved 2026-10-04 |
| D11 | T067 (turning the sync on) | Approved 2026-10-04 as the narrower rule; the sync stays `coursementorsync: 0` until 016 implements it |

### Within Each User Story

Harness/pytest/PHPUnit first (they fail), then pure rules, then services, then external functions, then CLI, then the instance check.

### Parallel Opportunities

- Setup: T002–T006 together.
- Foundational tests T007–T009 together; T013, T016, T019 together.
- US1 tests T026–T030 together; US2 tests T039–T040; US3 tests T048–T049; US5 tests T057–T058.
- Once Foundational is done, US1 and US2 can proceed in parallel (after T041), and US5's table/privacy work (T059–T060) can start alongside.
- Polish T070–T073 together.

---

## Parallel Example: User Story 1

```text
T026 harness: intake_rules::classify outcomes        (tests/admin_harness.php)
T028 pytest: intake offline validation                (tests/test_ltct_admin.py)
T030 PHPUnit: created-account fields, lock, no names  (moodle/local_ltuse/tests/admin_test.php)
```

## Parallel Example: User Story 5

```text
T058 PHPUnit: removal paths                           (moodle/local_ltuse/tests/admin_test.php)
T059 install.xml + upgrade step                       (moodle/local_ltuse/db/)
T060 privacy provider                                 (moodle/local_ltuse/classes/privacy/provider.php)
```

---

## Implementation Strategy

### MVP First (User Story 1, then User Story 2)

1. Phase 1 → Phase 2 (credential, guard, code, driver, `check`).
2. Phase 3 (intake) → V2–V4, V6. **Stop and validate.**
3. Phase 4 (cohort enrolment into a course) → V7, V9 half. Together these are the P1 MVP: learners brought on and enrolled.

### Incremental Delivery

4. Phase 5 (routine changes; the `sil` → Area move once 002 declares the Areas).
5. Phase 6 (mentors), no later than 016's merge; sync stays off until 016 implements D11's narrower rule.
6. Pathways (T045–T047) once 006 merges.
7. Polish; the done gate (T077) stays open after merge.

### Notes

- [P] = different files, no dependency on an incomplete task.
- Every learner-data file used during development lives under `~/ltct-private/`; never commit one, never paste one into an issue or PR.
- Commit after each task or logical group; each delivering PR updates the REQUIREMENTS rows it delivers (T074).
