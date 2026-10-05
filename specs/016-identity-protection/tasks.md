# Tasks: Identity protection for at-risk users

**Input**: Design documents from `specs/016-identity-protection/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included. plan.md §Testing requires pytest, a PHP harness for the pure rules, PHPUnit with synthetic users, and instance checks V1–V17 (test accounts only; evidence stays out of the repo).

**Decision gates** (plan "Decisions on the plan's limits"): a task marked **[D1]**, **[D2]** or **[D3]** depends on a decision still pending the maintainer. It may be built as drafted, but it is not closed until Doug confirms that decision. **[D2]** tasks are also a precondition: until they land, the service refuses `firstname` and `pseudonym` (R11), so building them last is safe.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel (different files, no dependencies)
- **[Story]**: US1–US4 from spec.md
- Paths: plugin `moodle/local_ltuse/`, declarations `moodle/site/`, scripts `scripts/`, tests `tests/`

---

## Phase 1: Setup

- [x] T001 Confirm in `MOODLE_502_STABLE` source the names this spec declares: the `core_user` search area flag (`core_search/core_user_user_enabled`), `grade_export_userprofilefields` default, `auth` holding `webservice` for the publisher, the `before_user_updated` hook's `$user`, `profile_save_data()` saving only fields set on the object, `core_user::update_picture()` with `deletepicture`, `lock_config::get_lock_factory()`, the `core/coursecontacts` cache, and `customcert`'s `userfield` payload `{userfield: <field id>}`. Record the results in research.md.
- [x] T002 Merge `origin/main` into the branch so 003's and 011's plugin files (events, hooks, mobile, Mentoring page, office hours) are present, and pick the `local_ltuse` stamp `2026100500` (006 takes `2026100600`, 008 `20261008NN`; agreed 2026-10-04).

## Phase 2: Foundational (blocks every story)

- [x] T003 [P] Pure rules in `classes/protection/levels.php`: the level order, `max()`, the withhold set per level from the stored config, pseudonym normalisation (NFC + case fold) and its rules, the real-name-in-username check, organisation member-cohort matching (managers cohort excluded), and path 4's course-idnumber rule. No Moodle calls.
- [x] T004 [P] `tests/protection_harness.php`: every rule in T003, runnable with plain `php`, failing first; add it to `.github/workflows/site-config.yml`.
- [x] T005 Tables `local_ltuse_protection`, `local_ltuse_org_protection`, `local_ltuse_protection_log` in `db/install.xml` and `db/upgrade.php` (savepoint `2026100500`, renumbered `2026100900` on the 2026-10-05 merge of main, after 006's and 002's steps and above 008's `2026100801`), with the `ltct_certname` back-fill for every existing user; bump `version.php` and the `site.yaml` pin.
- [x] T006 Capabilities `local/ltuse:viewidentity`, `manageprotection` (CONTEXT_USER) and `manageorgprotection` (CONTEXT_SYSTEM), `RISK_PERSONAL`, archetype `manager`, in `db/access.php`.
- [x] T007 `classes/protection/entitlement.php`: `can_view_identity()` (paths 1–4), `can_manage_protection()`, `marker()`.
- [x] T008 `classes/protection/service.php`: `set_protection()`, `apply()`, `effective_level()`, `is_settled()`, `is_protected()`, `real_identity()`, the bypass set, per-user lock, transaction, snapshot, account rewrite, picture deletion, log row, cache purge, scheduler re-save (R15) and the `protectionchanged` notification; `set_org_protection()` with the activity count.
- [x] T009 [P] `db/messages.php`: the `protectionchanged` provider, to the learner only.

## Phase 3: US1 — a protected learner takes part without exposing their identity (P1)

- [x] T010 [US1] `classes/protection/hook_callbacks.php` + `db/hooks.php`: `before_user_updated` re-applies the protected values unless the user is in the bypass set (R2).
- [x] T011 [US1] `classes/protection/observer.php` + `db/events.php`: `user_created` (queue apply), `user_updated` (re-apply or keep `ltct_certname` in step), `cohort_member_added`/`removed` for `ltct:org:<key>` only, `user_deleted` (shared deletion routine).
- [x] T012 [US1] `classes/task/apply_protection.php` (adhoc) and `classes/task/reconcile_protection.php` (scheduled, every `reconcile_minutes`) in `db/tasks.php`; reconcile logs a count only.
- [ ] T013 [P] [US1] **[D1]** `moodle/site/settings/identity.yaml` (rows `[26]`): `showuseridentity`, grade export fields, `allowedemaildomains`, `enablegravatar`, `forceloginforprofileimage`, `protectusernames`, the `core_user` search area off; `roles.yaml`: `editingteacher` and `teacher` prohibit `moodle/course:useremail`. **Built 2026-10-04; open until Doug confirms decision 1.**
- [ ] T014 [P] [US1] **[D3]** `settings/identity.yaml`: `registerauth`, `authpreventaccountcreation`, `auth`, and the three `auth_manual/field_lock_*` locks. **Built 2026-10-04; open until Doug confirms decision 3.**
- [ ] T015 [US1] PHPUnit `tests/protection_test.php`: the hook-mutation guard (R2), an exception inside the bypass set, a raise and a lowering restoring fields exactly, the picture deleted only at `firstname`+. **Written 2026-10-04; not yet run (no Moodle test site here).**
- [ ] T016 [US1] Instance checks V2, V3, V5, V7, V8, V9, V14 (quickstart). Record pass/fail only in the PR.

## Phase 4: US2 — the people responsible still know who they are (P1)

- [x] T017 [US2] `roles.yaml`: `manager` gets `viewidentity` and `manageprotection`; `mentor` gets `viewidentity` (`MENTOR_ALLOW` gains it, a reviewed change); `teacher` gets `viewidentity` (R7 path 4).
- [x] T018 [US2] Profile node in `lib.php` (`local_ltuse_myprofile_navigation()`): the learner's own level, preview and how to ask; the real identity and marker for the entitled; a granting link for managers.
- [x] T019 [US2] Mentoring page and app handler (`classes/mentoring.php`, both templates): `protected` and, for the entitled only, `realname` on each learner row.
- [x] T020 [US2] `protected.php` ("People I support") with a CSV download carrying the marker per row.
- [ ] T021 [US2] Instance check V4.

## Phase 5: US3 — set up and changed without fuss (P2)

- [x] T022 [US3] `classes/external/set_protection.php` and `set_org_protection.php`, registered in `db/services.php`.
- [x] T023 [US3] `protection.php` (granting page: level, pseudonym, corrections, neutral username, acknowledgement) and `orgprotection.php` (site team only).
- [ ] T024 [US3] Instance checks V11, V12, V16.

## Phase 6: US4 — what leaves the site protects them too (P2)

- [x] T025 [US4] `profile-fields.yaml`: `ltct_certname` (text, private, locked); `profilefields.php` and `site_config.py` accept the `text` datatype.
- [x] T026 [US4] `certificate/template.yaml`: `userfield` bound to `ltct_certname`; `certtemplate.php` writes the `userfield` element with the field's id; `emailteachers: 0`, `emailothers: ""` on every certificate activity. `emailteachers`/`emailothers` needed no declaration: spec 013's publisher already writes both on every publish.
- [x] T027 [P] [US4] `roles.yaml`: `editingteacher` and `teacher` prohibit `moodle/backup:downloadfile`, `report/log:view`, `report/loglive:view` (R14, decision 5).
- [ ] T028 [US4] Instance checks V6, V13, V17.

## Phase 7: Declaration and drift

- [x] T029 `moodle/site/protection.yaml` and `_validate_protection()` in `scripts/site_config.py` (TOP_FILES, payload key `protection`, `orgscope_ready` computed).
- [x] T030 `site_config.py` rules: `text` datatype; `ltct_certname` private and locked; `ltct_org` private only without a `user:profilefield_ltct_org` report condition; `viewidentity` only on `manager`/`mentor`/`teacher`; `manageprotection` only on `manager`; teacher-role prohibits; certificate name element rule and email rule.
- [x] T031 [P] `tests/test_protection_declaration.py` and cases in `tests/test_site_config.py`, failing first.
- [x] T032 `classes/siteconfig/protection.php`: store the config, check, drift counts (reconcile backlog, waiting usernames); wire into `inspector.php`, `applier.php` (after structure, before reporting) and `drift.php`.
- [ ] T033 Instance check V1 (apply, idempotent, fresh rebuild, organisation minimums from the data restore).

## Phase 8: Organisation scope (decision 2, the precondition for `firstname` and `pseudonym`)

- [ ] T034 **[D2]** Confirm in source that the `participants` datasource offers a cohort-membership condition; if not, record the alternative in research.md R11 before T035.
- [ ] T035 **[D2]** `reports.yaml`: `progress` scoped by the organisation's member cohort, the `Organisation` column and `ltct_org` filter dropped; `site_config.py` refuses a `user:profilefield_ltct_org` scope condition.
- [ ] T036 **[D2]** `profile-fields.yaml`: `ltct_org` `visible: private`; instance check V10.

## Phase 9: Polish

- [x] T037 Privacy provider: the three tables, `core_userlist_provider`, export and the shared deletion routine (actor ids set to 0); instance check V15.
- [x] T038 [P] Plugin README: the three Principle XI exceptions; `moodle/site/README.md`: protecting a person or an organisation, neutral keys, protect early.
- [x] T039 [P] Lang strings in `lang/en/local_ltuse.php`.
- [ ] T040 `moodle/REQUIREMENTS.md` row 26 updated on delivery; known gaps listed in the PR (FR-015). **Row 26 updated 2026-10-04; the known gaps go in the delivering PR.**
- [ ] T041 SC-007 with 2–3 real protected users; the PR records the count only.
