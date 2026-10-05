# Tasks: Identity protection for at-risk users

**Input**: Design documents from `specs/016-identity-protection/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included. plan.md §Testing requires pytest, a PHP harness for the pure rules, PHPUnit with synthetic users, and instance checks V1–V17 (test accounts only).

**Decision gates**: the gates **[D1]**, **[D2]** and **[D3]** were closed on 2026-10-05, when Doug accepted every recommendation of the scope review: decision 1 rejected, decision 2 option (a), decision 3 rejected (plan, "Decisions on the plan's limits"). A task for behaviour the review removed is marked **Dropped 2026-10-05** and carries no checkbox, so it counts as neither done nor open. The scope review's own work is Phase 10.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel (different files, no dependencies)
- **[Story]**: US1–US4 from spec.md
- Paths: plugin `moodle/local_ltuse/`, declarations `moodle/site/`, scripts `scripts/`, tests `tests/`

---

## Phase 1: Setup

- [x] T001 Confirm in `MOODLE_502_STABLE` source the names this spec declares: the `core_user` search area flag (`core_search/core_user_user_enabled`), `grade_export_userprofilefields` default, `auth` holding `webservice` for the publisher, the `before_user_updated` hook's `$user`, `profile_save_data()` saving only fields set on the object, `core_user::update_picture()` with `deletepicture`, `lock_config::get_lock_factory()`, the `core/coursecontacts` cache, and `customcert`'s `userfield` payload `{userfield: <field id>}`. Record the results in research.md. *(The search area flag, `auth` and `userfield` are no longer used, 2026-10-05.)*
- [x] T002 Merge `origin/main` into the branch so 003's and 011's plugin files (events, hooks, mobile, Mentoring page, office hours) are present, and pick the `local_ltuse` stamp `2026100500` (006 takes `2026100600`, 008 `20261008NN`; agreed 2026-10-04).

## Phase 2: Foundational (blocks every story)

- [x] T003 [P] Pure rules in `classes/protection/levels.php`: the level order, the withhold set per level from the stored config, pseudonym normalisation (NFC + case fold) and its rules, the real-name-in-username check, and path 4's course-idnumber rule. No Moodle calls. *(`max()` and organisation member-cohort matching dropped 2026-10-05, change 9; raises, `manager_may()` and `email_reveals()` added, changes 2 and 14.)*
- [x] T004 [P] `tests/protection_harness.php`: every rule in T003, runnable with plain `php`, failing first; add it to `.github/workflows/site-config.yml`.
- [x] T005 Tables `local_ltuse_protection` and `local_ltuse_protection_log` in `db/install.xml` and `db/upgrade.php` (savepoint `2026100500`, renumbered `2026100900` on the 2026-10-05 merge of main, after 006's and 002's steps and above 008's `2026100801`); bump `version.php` and the `site.yaml` pin. *(`local_ltuse_org_protection` and the `ltct_certname` back-fill dropped 2026-10-05, changes 8–10; the step drops the table where an earlier build made it. `requested` and `emailchecked` added to the log, changes 2 and 13.)*
- [x] T006 Capabilities `local/ltuse:viewidentity` and `manageprotection` (CONTEXT_USER), `RISK_PERSONAL`, archetype `manager`, in `db/access.php`. *(`manageorgprotection` dropped 2026-10-05, change 9.)*
- [x] T007 `classes/protection/entitlement.php`: `can_view_identity()` (paths 1–4), `can_manage_protection()`, `marker()`. *(Path 3 unconditional, change 10; path 4 narrowed to the mentor's own group, change 22; `is_site_team()` and `may_be_entitled()` added, changes 12 and 14.)*
- [x] T008 `classes/protection/service.php`: `set_protection()`, `apply()`, `effective_level()`, `is_settled()`, `is_protected()`, `real_identity()`, the bypass set, per-user lock, transaction, snapshot, account rewrite, picture deletion, log row, cache purge, scheduler re-save (R15) and the `protectionchanged` notification. *(`set_org_protection()` dropped 2026-10-05, change 9.)*
- [x] T009 [P] `db/messages.php`: the `protectionchanged` provider, to the learner only.

## Phase 3: US1 — a protected learner takes part without exposing their identity (P1)

- [x] T010 [US1] `classes/protection/hook_callbacks.php` + `db/hooks.php`: `before_user_updated` re-applies the protected values unless the user is in the bypass set (R2). *(Wrapped in try/catch, reporting through `debugging()`, change 17.)*
- [x] T011 [US1] `classes/protection/observer.php` + `db/events.php`: `user_updated` (re-apply a protected account that drifted; return at once for anyone else), `user_deleted` (shared deletion routine). *(`user_created` and the cohort observers dropped 2026-10-05, change 18.)*
- [x] T012 [US1] `classes/task/apply_protection.php` (adhoc) and `classes/task/reconcile_protection.php` (scheduled, every `reconcile_minutes`) in `db/tasks.php`; reconcile reads only protected rows and logs counts only. *(Organisation sweep and certificate back-fill dropped; a failed repair fails the run, change 19.)*
- [x] T013 [P] [US1] `moodle/site/settings/identity.yaml` (rows `[26]`): `showuseridentity` and the grade export fields at core's defaults, `grade_export_customprofilefields`, `allowedemaildomains`, `enablegravatar`, `forceloginforprofileimage`. *(Decision 1 rejected, Doug, 2026-10-05 (scope review): emptying `showuseridentity`, trimming the grade export fields, the `moodle/course:useremail` prohibit and the `core_user` search area flag dropped 2026-10-05, changes 1 and 7; `protectusernames` moved to 008's `admin.yaml`.)*
- ~~T014 [P] [US1] **[D3]** `settings/identity.yaml`: `registerauth`, `authpreventaccountcreation`, `auth`, and the three `auth_manual/field_lock_*` locks.~~ **Dropped 2026-10-05** (decision 3 rejected, changes 3 and 4): no field locks; `registerauth`, `authpreventaccountcreation` and `protectusernames` are 008's `admin.yaml`, and the login methods are 008's `site.yaml` plugin entries (`auth_webservice` on, `auth_email` off), not an `auth` setting. The per-account login guard that replaces them is T042.
- [ ] T015 [US1] PHPUnit `tests/protection_test.php`: the hook-mutation guard (R2), an exception inside the bypass set, a raise and a lowering restoring fields exactly, the picture deleted only at `firstname`+, a raise refused without `requested` and `emailchecked`, site-team-only changes after intake, path 4 only in the mentor's own group. **Written 2026-10-04, extended 2026-10-05; not yet run (no Moodle test site here).**
- [ ] T016 [US1] Instance checks V2, V3, V5, V7, V8, V9, V14 (quickstart). Record pass/fail in the PR.

## Phase 4: US2 — the people responsible still know who they are (P1)

- [x] T017 [US2] `roles.yaml`: `manager` gets `viewidentity` and `manageprotection`; `mentor` gets `viewidentity` (`MENTOR_ALLOW` gains it, a reviewed change); `teacher` gets `viewidentity` (R7 path 4).
- [x] T018 [US2] Profile node in `lib.php` (`local_ltuse_myprofile_navigation()`): the protected learner's own level and preview; the real identity and marker for the entitled; a granting link on a protected member's profile. *(Shown only for protected people; "People I support" behind a cheap check; the way to ask moved to intake, change 12.)*
- [x] T019 [US2] Mentoring page and app handler (`classes/mentoring.php`, both templates): `protected` and, for the entitled only, `realname` on each learner row.
- [x] T020 [US2] `protected.php` ("People I support"). *(The CSV download dropped 2026-10-05, change 14.)*
- [ ] T021 [US2] Instance check V4.

## Phase 5: US3 — set up and changed without fuss (P2)

- [x] T022 [US3] `classes/external/set_protection.php`, registered in `db/services.php`, with `requested` required and `emailchecked`. *(`set_org_protection.php` dropped 2026-10-05, change 9; `newusername` dropped, change 15.)*
- [x] T023 [US3] `protection.php` (granting page: level, pseudonym, "the person asked", "the email was checked" with the warnings, and for the site team corrections and the acknowledgement). *(`orgprotection.php` and the granter's username field dropped 2026-10-05, changes 9 and 15.)*
- [ ] T024 [US3] Instance checks V11, V12, V16.

## Phase 6: US4 — what leaves the site protects them too (P2)

- ~~T025 [US4] `profile-fields.yaml`: `ltct_certname` (text, private, locked); `profilefields.php` and `site_config.py` accept the `text` datatype.~~ **Dropped 2026-10-05** (change 8). Built 2026-10-04, then removed; `profile-fields.yaml` is back to main's.
- ~~T026 [US4] `certificate/template.yaml`: `userfield` bound to `ltct_certname`; `certtemplate.php` writes the `userfield` element with the field's id.~~ **Dropped 2026-10-05** (change 8): the certificate keeps `studentname`. `emailteachers: 0` and `emailothers: ""` stay, written by spec 013's publisher on every publish.
- [x] T027 [P] [US4] `roles.yaml`: `editingteacher` and `teacher` prohibit `moodle/backup:downloadfile` (R14, decision 5a) — **built**. **Reopened 2026-10-05** (decision 5 split, change 5): the site-wide `report/log:view` and `report/loglive:view` prohibits are removed. **Built 2026-10-05**: `local_ltuse_protection.hidelogs`, a checkbox on the granting page, and `service::sync_log_blocks()`, run after a change, by the hourly reconcile and on deletion; `test_the_course_log_block_follows_the_person_who_asked`. It is the per-person block, for a protected person who asks, of `report/log:view`, `report/log:viewtoday` and `report/loglive:view` for `editingteacher` and `teacher` in the courses they take, with `assign_capability()` (`lib/accesslib.php` L1411) in each course context.
- [ ] T028 [US4] Instance checks V6, V13, V17.

## Phase 7: Declaration and drift

- [x] T029 `moodle/site/protection.yaml` and `_validate_protection()` in `scripts/site_config.py` (TOP_FILES, payload key `protection`). *(`orgscope_ready` and `org_minimum_max` dropped 2026-10-05, changes 9 and 11; `neutral_surname` must be one non-letter character, change 3.)*
- [x] T030 `site_config.py` rules: `viewidentity` only on `manager`/`mentor`/`teacher`; `manageprotection` only on `manager`; report building only on `manager`; the backup prohibit; `enablegravatar`, `forceloginforprofileimage` and `allowedemaildomains` required. *(The `text` datatype, `ltct_certname`, `ltct_org`-private, certificate, useremail, log, `showuseridentity`, grade-export, search-area, field-lock and `AUTH_ALLOWED` rules dropped 2026-10-05, changes 1, 3, 4, 5, 7, 8, 11, 20.)*
- [x] T031 [P] `tests/test_protection_declaration.py` and cases in `tests/test_site_config.py`, failing first.
- [x] T032 `classes/siteconfig/protection.php`: store the config, check, drift count (reconcile backlog); wire into `inspector.php`, `applier.php` (after structure, before reporting) and `drift.php`. *(The waiting-usernames count dropped 2026-10-05, change 15.)*
- [ ] T033 Instance checks V1 (apply, idempotent, fresh rebuild, protection data from the data restore) and V10.

## Phase 8: Organisation scope (decision 2, option a)

- [x] T034 Confirm in source that the `participants` datasource offers a cohort-membership condition: `cohort:idnumber`, a text condition, through `cohort_members` (`course/classes/reportbuilder/datasource/participants.php`, `MOODLE_502_STABLE`; recorded in research R11).
- [x] T035 `reports.yaml`: `progress` scoped by `cohort:idnumber = ltct:org:{org}`, its `Organisation` column dropped (it had no `ltct_org` filter); `site_config.py` refuses a `user:profilefield_ltct_org` condition on any report. Landed 2026-10-05 (change 11).
- ~~T036 **[D2]** `profile-fields.yaml`: `ltct_org` `visible: private`; instance check V10.~~ **Dropped 2026-10-05** (decision 2, option a): `ltct_org` stays visible. V10 now checks the cohort scope and that the organisation shows (T033).

## Phase 9: Polish

- [x] T037 Privacy provider: the two tables, `core_userlist_provider`, export and the shared deletion routine (actor ids set to 0); instance check V15. *(`local_ltuse_org_protection` dropped 2026-10-05.)*
- [x] T038 [P] Plugin README: the Principle XI exceptions; `moodle/site/README.md`: protecting a person who asks, neutral keys on request, protect early, backups, logs on request, the reconcile alert. *(Rewritten 2026-10-05.)*
- [x] T039 [P] Lang strings in `lang/en/local_ltuse.php`.
- [ ] T040 `moodle/REQUIREMENTS.md` row 26 updated on delivery; known gaps listed in the PR (FR-015). **Row 26 updated 2026-10-04 and 2026-10-05; the known gaps go in the delivering PR.**
- [ ] T041 SC-007: 016's own pilot, with 2–3 real protected users; if three do not exist yet, each person is asked at their first grant (change 25). The PR records the count only.

## Phase 10: Scope review (Doug, 2026-10-05)

Done in four batches on branch `016-scope-review`: A (settings and validator), B (organisations, certificates, observers), C (service and pages), D (documents).

- [x] T042 Per-account login guard (change 4, R3): a protected account has `auth` `manual` (or `nologin`) and no `auth_oauth2_linked_login` row; set when a level is applied, held by the hook, reported by `drifted()`. **Built 2026-10-05**; `tests/protection_test.php` `test_a_protected_account_has_no_outside_login`.
- [X] T043 Spec 008 intake (008's side, change 2): pass `requested` (the row asked) and `emailchecked` (only after intake's own check, `levels::email_reveals()` with the row's organisation key, since `ltct_org` is unset when `protect()` runs; contracts/protection-service.md, "Spec 008's intake") to `set_protection()`, make the non-identifying-email check at account creation, and drop the organisation-minimum inputs and the production gate. Until it does, a raise from 008's intake is refused (`protection:err:notrequested`). — *done on 008's side 2026-10-05 (PR #93, commit 19a6d91): protect() passes both; a protected row whose address email_reveals() flags waits until its email_checked column says yes*
- [ ] T044 Granting page: warn when another protected person at `firstname` has the same first name (change 25). Not built.
- [x] T045 Granting page: warn before a raise to `firstname` or `pseudonym` deletes the picture (review "Keep" list). **Built 2026-10-05**: `service::picture_levels()` and a warning on `protection_form`; `test_the_picture_warning_names_the_levels_that_delete_it`.
- [ ] T046 Maintainer decision: by core's defaults `editingteacher` may assign `teacher`, manage groups and set group idnumbers, so a course leader could reach path 4 (research R7). Narrow `editingteacher`'s assignable roles, or keep it as a known gap because nobody holds the role in normal use.
- [ ] T047 The way to ask for protection at intake, in the welcome message and in site help (change 12; FR-008). The profile node already points to the site team; the intake form and welcome message are spec 008's, and no site-help page exists yet. Until then `moodle/site/README.md` tells the site team to say it in person, and no longer claims the offer exists (2026-10-05).
- [ ] T048 Run `python scripts/site_config.py drift` against the live site before merging, to confirm it already matches `identity.yaml`'s core defaults (change 21).
- [ ] T049 Once 016 and 008 are both on main, one username generator: 016's `neutral_username()` and 008's `intake_service::new_username()` share the format; make one call the other (008 interaction).
- [x] T050 Batch A: decision 1 rejected (change 1); the email rule's declaration side (2); no field locks and a mandatory placeholder surname (3); login settings out of 016 and `AUTH_ALLOWED` removed (4); site-wide log prohibits removed (5); backup prohibit kept (6); search area not disabled (7); validator narrowed (20); core-default whys (21).
- [x] T051 Batch B: `ltct_certname` removed (8); organisation minimums removed (9); `managers_see_identity` removed (10); hook try/catch (17); observers cut to `user_updated` and `user_deleted` (18); reconcile on protected rows only, failing on a failed repair (19).
- [x] T052 Batch C: decision 2 option (a), T034–T035 (11); profile section only for the protected (12); `requested` (13); managers at intake only, CSV removed (14); automatic neutral usernames (15); cheap `has_activity()` (16); path 4 narrowed (22); the per-person email check (2).
- [x] T053 Batch D: spec, plan, research, data model, contracts, quickstart, tasks and the READMEs follow the review (changes 2, 23–26, and the documents of 1–22).
