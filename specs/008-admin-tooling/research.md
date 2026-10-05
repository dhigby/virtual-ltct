# Research: Simple administration tooling (spec 008)

Every Moodle API below was looked up first in Context7 (`/websites/moodledev_io_5_2_apis`, `/moodle/moodle`) and then confirmed in upstream source on `MOODLE_502_STABLE`, under `public/` (constitution XI). Line numbers are for that branch on 2026-10-04. "Instance" items are behaviours the source implies but that must still be run on the temporary 5.2.3+ instance before the task that relies on them closes (constitution X); they are listed in [quickstart.md](quickstart.md).

Cross-spec names come from the sessions planning those specs on 2026-10-04: spec 006's pathway API (`specs/006-learning-pathways/contracts/pathway-api.md`, to be committed by 006) and spec 016's protection service (`specs/016-identity-protection/contracts/protection-service.md`). If either moves, the contract file wins and this plan follows it.

---

## R1. What the tooling is: a site-team CLI over our own web service

**Decision**: A Python command, `scripts/ltct_admin.py`, run by a site-team member on their own machine. It calls a second external service, **"LTC administration"** (`ltuse_admin`), declared in `local_ltuse`, whose functions are our own `local_ltuse_admin_*` functions. A guided slash command, `/manage-learners`, walks a non-git operator through it in plain language. Managers use none of this: their actions are on spec 002's organisation page (spec Clarifications).

**Rationale**:
- INTENT ("Where does admin tooling go?") and the spec input place scripted helpers in the training-system half, run against Moodle. A CLI on the operator's machine is that, and it reuses the publisher's tested patterns: `moodle_client.py`, a dry run first, `site_config.validate()` for the declared organisations.
- A web-service token is a narrower credential than SSH to the server, which `site_config.py`'s pattern needs (`run_remote`, `MOODLE_SSH`). FR-009 asks for a credential distinct from publishing with only the permissions administration needs. A second service with its own `requiredcapability` is the standard way to get that (R12).
- Our own functions, not core's, are needed for every write that matters (R2–R7): no core web service creates a cohort-sync instance or enrols someone into a chosen self-enrolment instance; core's cohort service ignores the dynamic-cohort guard; account creation must be ordered around protection (R5); and every write needs a server-side preview to be idempotent.

**Alternatives considered**:
- **Core Upload users (`tool_uploaduser`)**, named in FR-010 as the core-first example. It has a preview, `profile_field_<shortname>`, `cohortN` by idnumber, `suspended`, and match-on-email in update modes (`admin/tool/uploaduser/classes/process.php:146-175, 466-489, 1092-1119`; `classes/preview.php:89-157`). Rejected as the intake path, kept as the documented fallback:
  - its account creation saves profile data after `user_created` and cannot set protection before enrolment (016 R2);
  - usernames come from the file, so the operator would have to supply one for every person, and a neutral one for anyone name-protected (016 R13);
  - match-on-email works only in update modes, not "add new", so one file cannot both create and match;
  - it needs `moodle/site:uploadusers` and the admin UI, or a CLI that runs as the main admin over SSH (`cli/uploaduser.php:40`);
  - course enrolment uses the first manual instance, which spec 004 counts as a pilot (004 R10).
- **A Moodle page for the site team** (like 002's organisation page). It would avoid a token and Python. Rejected for now: the site team are the people who already run `site_config.py` and the publisher from this repo, and a page is more PHP and UI to test for two or three users. It stays possible later, because every rule lives in the PHP classes the web service calls (R4), not in the CLI.
- **A PHP CLI over SSH** (the `site_config.py` pattern). Rejected: every operator would need shell access to the server, a far wider privilege than an administration token.

## R2. Accounts: one row per call, matched by email, the email as username

**Decision**: `local_ltuse_admin_apply_intake_row` creates one account per call with `user_create_user()` (`public/user/lib.php`), `auth = manual`, the lowercased email as its username (a neutral generated one for a name-protected row, or when the email cannot be one), and Moodle's own password email (`setnew_password_and_mail()`). An existing account is found by email, case-insensitive, excluding deleted accounts; two live accounts with one email reject the row.

**Rationale**:
- `core_user_create_users` runs a whole batch in one delegated transaction (`user/externallib.php:151`), so one bad row rolls back every other. One row per call makes an interrupted run resumable and a re-run idempotent (FR-003, edge case "interrupted part-way").
- Email is the one identifier every partner can supply (spec Assumptions). **008's own lookup is the guard**: `user_create_user()` never checks email, and the `user` table has no unique index on it. The duplicate check that `allowaccountssameemail` controls lives only in `core_user_create_users` (`user/externallib.php:199-211`), signup and Upload users. So `apply_intake_row` takes a lock from `\core\lock\lock_config::get_lock_factory('local_ltuse')`, keyed on the lowercased email, re-checks for a live account inside the lock, and only then creates. Two runs, or a retry racing its own lost first attempt, cannot both create. 008 still declares `allowaccountssameemail = 0` (R13) as a backstop for core's own creation paths.
- **Fields core's web service sets and the PHP function does not** (`user/externallib.php:192-227`): `confirmed = 1`, `mnethostid = $CFG->mnet_localhost_id`, `auth = 'manual'`, `password = ''`. Without `mnethostid` and `confirmed`, the account cannot log in, because login looks users up by `mnethostid`. `apply_intake_row` sets all four before `user_create_user($user, false, false)`, then fires `user_created` itself after the profile fields, as core does.
- **Password**: `setnew_password_and_mail()` only generates, stores and emails a password; it does not force a change. Core's `createpassword` path also sets `auth_forcepasswordchange` (`user/externallib.php:250-255, 274-277`). So 008 calls `set_user_preference('auth_forcepasswordchange', 1, $user)` and, after commit, `setnew_password_and_mail($user)` with the full user record. Learners sign in with an account Moodle issues (spec Assumptions). **Instance check V2** includes logging in with the emailed password.
- Usernames (revised 2026-10-05): a username must be lowercase and unchanged by `PARAM_USERNAME`, or `user_create_user()` throws `usernamelowercase` or `invalidusername` (`user/lib.php:55-65`); it must fit `user.username`, `char(100)` (`lib/db/install.xml:878`); and `(mnethostid, username)` is unique over every row, deleted ones included (`lib/db/install.xml:923`). The username is the person's email, lowercased (`intake_rules::username()`, `intake_service::choose_username()`). Doug, 2026-10-05: nobody can be expected to remember a generated code; email login is on (`authloginviaemail`, R13; spec Assumptions). Two exceptions get `ltc-` plus 8 characters from a lowercase base32 alphabet, retrying on collision:
  - **a name-protected row**: a target protection (R5) of `firstname` or `pseudonym`. 016's service refuses those levels for a username containing the real name (016 R13), and an email can contain it. 008 does not inspect the address, so it gives these few a code; protected people usually have pseudonymous addresses anyway (Doug, 2026-10-05). They still sign in with their email, so they never need the code. A target of `email` or `none` uses the email.
  - **an email that cannot be a username**: one `PARAM_USERNAME` would change (a `+` while `extendedusernamechars` is off: `lib/classes/param.php:1291-1304`), one longer than 100 characters, or one a deleted account on this site still holds as its username (the unique index covers deleted rows). Login is by email, so the fallback costs the person nothing. `extendedusernamechars` is not declared (core default off); either way is safe, for the same reason.
  - **an email that is a live account's username** is not a fallback but a refusal: the row is `rejected` with `login_clash`. Sign-in looks the typed text up as a username before an email (`authenticate_user_login()`, `lib/moodlelib.php`), so the new person would reach the other account and never sign in. The site team changes that account's username first.
  An existing account's username is never changed.
- `suspended` cannot be set at creation through the web service (absent from `user/externallib.php:42-117`). Not needed: intake never creates suspended accounts.

**Re-run behaviour**: a row whose email already exists is matched and never re-created; its names are never written (FR-019, 016 decision 3). The only fields 008 ever writes on an existing account are `suspended` (R6) and `ltct_org` (R3, R8).

**Alternatives**: a generated code for everyone (the first decision; replaced 2026-10-05, because protection is per person and opt-in, and nobody should have to remember a code); username = email for everyone, protected or not (rejected: 016 refuses `firstname` and `pseudonym` for a username holding the real name, 016 R13); username from the name (rejected, 016 R13); batch creation (rejected, transaction above).

## R3. Organisation cohorts: set `ltct_org`, never add by hand

**Decision**: The tooling places a learner in their organisation by writing `profile_field_ltct_org` on an **existing** account, through `profile_save_data()` followed by a `\core\event\user_updated`, and only once any protection is settled (R5). It is never written at creation. It never calls `cohort_add_member()` on an `ltct:org:<key>` cohort.

**Rationale**:
- `tool_dynamic_cohorts` owns those cohorts (`component = 'tool_dynamic_cohorts'`, its `classes/cohort_manager.php:68`). Core's `core_cohort_add_cohort_members` does not check `component` at all (`cohort/externallib.php`; `cohort/lib.php:191`), so a manual add would "succeed" and be undone by the plugin's next run for that user. The field is the only lever (002 R4).
- The plugin reacts in real time: it observes every event and processes rules whose condition lists the event; `user_custom_profile` extends `user_profile`, whose `get_events()` returns `user_created` and `user_updated` (`classes/local/tool_dynamic_cohorts/condition/user_profile.php:215-220`). Core fires both after `profile_save_data` (`user/externallib.php:247/258` create, `655/659` update). So the cohort membership, and with it cohort-sync enrolment, happens in the same request. **Instance check V3.**
- Rules must stay non-bulk (`bulkprocessing 0`), or members are inserted without `cohort_member_added` and cohort sync waits for its scheduled task (`classes/rule_manager.php` ~368-381). 002 R12 already requires this; 008 relies on it.
- Writing only `ltct_org` keeps FR-019: `profile_save_data()` bypasses 016's `before_user_updated` hook until its hourly reconcile, so 008 passes a data object holding only `id` and `profile_field_ltct_org`, never another field (016's never-send list).

**Order at creation** (016, INTENT 2026-10-03): create with no `ltct_org` → set protection to the row's own level, when it asks for one (R5) → confirm settled → set `ltct_org` → per-row courses (R7). 008 does **not** rely on any observer order. By the time the person joins the cohort, any protection they asked for is already set and settled. Organisations have no protection minimum and 016 keeps no cohort observer (Doug, 2026-10-05 (scope review)), so joining a cohort changes nobody's protection.

## R4. Where the rules live: pure classes, harness-tested

**Decision**: Every rule that decides an outcome is in a pure class under `classes/admin/` with no Moodle calls, tested by `tests/admin_harness.php` in CI like `org_access_harness.php`:
- `intake_rules`: classify a row against what Moodle holds, into the keys of data-model §2: `new`, `unchanged`, `will_set_org`, `will_enrol`, `flagged_other_org`, `flagged_suspended`, `flagged_protection`, `waits`, `rejected`; and the "further along" rule (R15).
- `enrolment_rules`: whether a cohort may be enrolled into a course, and with which role (R7).
- `move_rules`: per learner and course, whether a move keeps, gains or loses access (R8).
- `course_mentor_rules`: the mentors a learner should have in a course (R10).

Thin `classes/admin/*_service.php` classes gather Moodle state, call the rules, and write through core APIs. The external functions are thinner still. This is 002's structure (one tested `access` class, thin pages and actions).

**Rationale**: the rules are where a mistake costs a learner their access or exposes them; keeping them pure lets CI test them without a Moodle, and lets a later Moodle page (R1 alternative) reuse them unchanged.

## R5. Protection before enrolment (spec 016)

**Decision**: The intake file has an optional `protection` column: `none`, `email`, `firstname` or `pseudonym` (016's level keys). Let **target** = the row's own level, `none` when the column is blank, as it is for nearly everyone. (Until 2026-10-05 the target was the stricter of that and the organisation's minimum; 016's scope review cut organisation minimums, so a row's organisation plays no part: Doug, 2026-10-05 (scope review).) For a row whose target is above `none`:

0. **Before anything is written**, in preview and again at the start of apply: `service::level_available($target)` (read-only; agreed with 016 and exposed, 2026-10-04). If 016 is not installed (`class_exists` false) or the level is not yet available, the outcome is **waits** and no account is created (INTENT 2026-10-03: "a request that asks for any protection waits").
0b. **The address check** (016 scope review change 2; 016 contracts/protection-service.md, "Spec 008's intake"): a protected person's email stays visible to others in a course, so preview runs `levels::email_reveals($email, $first, $last, $orgkey)` with the row's own names and organisation key (`ltct_org` is not set yet). A flagged row **waits** (`email_reveals`) until its `email_checked` column says `yes`: someone confirmed with the person that the address does not identify them, or the row gives another address. Doug, 2026-10-05 (scope review).
1. create the account without `ltct_org` (R2);
2. call `\local_ltuse\protection\service::set_protection($userid, $target, $options, $USER->id)` with `requested => true` (the row is the person's request) and `emailchecked => true` (step 0b let it through), after `\local_ltuse\protection\entitlement::can_manage_protection($USER->id, $userid)`;
3. check `service::is_settled($userid)` and that `service::effective_level($userid)` is at least `target`; if not settled, call `service::apply($userid)` synchronously and re-check;
4. only then set `ltct_org` and make any per-row enrolment.

If step 2 or 3 fails after the account exists, the row stops there with `ltct_org` unset. The account is not in any organisation cohort and enrols nowhere. A re-run classifies it `will_set_org` and resumes from step 2 (data-model §2).

**Existing accounts**: an existing account whose `effective_level` is below the row's target is classified **flagged_protection**, and nothing is enrolled for that row. Raising protection on an account that already has activity can need an acknowledgement (016 R13), which is 016's page, not a bulk side effect.

**Availability**: `service::level_available(string $level): bool` (016, added 2026-10-04) is read-only, needs no user, is true for `none`, and otherwise false until 016 can set that level (its `protection.yaml` applied). 016's scope review dropped the wait on its decision 2 for `firstname` and `pseudonym` (option (a): those levels no longer promise to hide the organisation). The only 016 calls 008 makes are `level_available`, `effective_level`, `is_settled`, `set_protection` and `apply`, each behind `class_exists`. `org_minimum()` is no longer called (dropped 2026-10-05, Doug, 2026-10-05 (scope review)).

**Capability**: `can_manage_protection` for a new account with no organisation is true only with `local/ltuse:manageprotection` at system context (016's contract). 016's roles validator allows that capability on `manager` only (`PROTECTION_MANAGE_ROLES` in `site_config.py`). 008's PR widens that allowlist by one entry, `ltctadmin`, with its reason, and grants the capability in `roles.yaml` (agreed with 016, 2026-10-04). Until then, a token user would also need `manager`. `ltct_admin.py check` reports the capability missing as "protected rows will be refused".

**Rationale**: names and contract from spec 016 (contracts/protection-service.md; agreed with the 016 session on 2026-10-04). The service itself does not check permission, which is why 008 calls the entitlement check first. Calling the PHP method rather than 016's external function avoids a second permission layer and keeps one code path in one plugin.

**Instance check V5**: with 016 installed, a protected intake row is never in a cohort, or enrolled, with its effective level below what was asked, at any point (watch `cohort_member_added` and `user_enrolment_created` ordering in the logs).

## R6. Suspend and reactivate

**Decision**: `local_ltuse_admin_apply_suspension` calls the unchecked core of spec 002's `\local_ltuse\organisation\actions` (002 T071): `do_suspend($userid)` / `do_reactivate($userid)`. They write a minimal `{id, suspended}` with `user_update_user()` and call `\core\session\manager::destroy_user_sessions($userid)` themselves.

**The split this needs in 002's contract**: 002's contract has every `actions` method re-check `access::may_manage_account(V, P)`. That check is false for a site-team actor in no managers cohort (`managedkeys` empty). It is also false for the people only the site team may act on: staff, mentors, managers, and learners in the `sil` and `sil-partner` holding entries. So each action becomes two layers:
- `do_<action>()` does the write and keeps the per-action rules: never a site admin, never the acting user, and `may_enrol_into()` for enrolment.
- The manager wrapper adds `may_manage_account()`. `organisation.php` calls the wrappers. 008's `local_ltuse_admin_*` functions call `do_*()` after `require_capability('local/ltuse:administer')` and their own rules.

This is recorded as a cross-spec requirement on 002 (plan, Cross-spec effects). V14 suspends a mentor and a holding-entry learner as well as an ordinary learner.

**Rationale**:
- `core_user_update_users` destroys sessions when suspending (`user/externallib.php:670`), but `user_update_user()` does not (`user/lib.php:156-257`), so PHP callers must. `kill_user_sessions` is deprecated since 4.5 (`lib/classes/session/manager.php:926-931`).
- One suspend path for managers and the site team: 016 adds its PHPUnit case (suspending a protected user leaves names unchanged) once, to that class (002 plan, cross-spec effects for 016).
- A suspended account keeps every enrolment, grade and completion; Moodle refuses its login. Nothing is deleted (FR-006, US3-1).

**Shared seam**: `classes/organisation/actions.php` is spec 002's (T071, not yet built). Whichever spec builds first writes it to 002's contract as amended above (002 data-model "Management actions", R10); the other adopts it. 008 does not write a second suspend.

## R7. Enrolling a cohort: cohort sync, disabled never deleted, with the organisation rules

**Decision**: `\local_ltuse\admin\cohort_enrolment::ensure($cohortid, $courseid)` adds, or re-enables, one `enrol_cohort` instance per (cohort, course), with `roleid` from `enrolment_rules`, `customint2 = COHORT_NOGROUP`, and the marker `customchar1 = 'ltct:008'`. An instance made through a pathway also carries `customchar2 = 'pathway'`, so the summary can report a pathway-made enrolment whose course is no longer in any of that cohort's enrolling pathways (R11). `remove()` sets that instance's status to `ENROL_INSTANCE_DISABLED`; it never deletes it.

**API** (confirmed): `enrol_get_plugin('cohort')->add_instance($course, ['customint1' => $cohortid, 'roleid' => ..., 'customint2' => 0])` (`enrol/cohort/lib.php:111-139`) runs `enrol_cohort_sync($trace, $courseid)` at once, so current members are enrolled in the same call. `add_instance` does not check for duplicates (the check is only in `edit_instance_validation`, lines 471-480), so `ensure` looks for an existing instance with the same cohort first, including disabled ones and ones the site team added by hand. `update_status($instance, ENROL_INSTANCE_ENABLED|DISABLED)` is core's `enrol_plugin` method; `cli/open_courses.php` already uses `update_instance()` and `delete_instance()` on cohort-sync instances.

**Why disable, not delete**: deleting the instance unenrols every member, and Moodle clears a learner's grades and completion when their last enrolment in a course goes (002 R7). Disabling makes the enrolments inactive and keeps everything (FR-006, US2-3). Disabling removes the instance's role assignments, which come back on re-enable; the `user_enrolments` rows stay active, and `update_status()` fires only `\core\event\enrol_instance_updated`, with no per-user event. That is why the course-mentor sync observes instance events too (R10), and why `ensure()` and `remove()` call `course_mentor_sync::sync_course()` themselves.

**The rules** (`enrolment_rules`, from 002 R8, R10, R11 and FR-019):

| Cohort | Course in `ltct:published` | Course in `ltct:org:<K>` (organisation-only) | Anywhere else |
|---|---|---|---|
| `ltct:org:<K>` | Student | Student, only if K is the course's organisation | refused |
| `ltct:org:<K>:managers` | refused (002 R2: the inspector fails on it) | `orgmanager`, only for K's own course | refused |
| `ltct:mentors` | refused | refused | refused |
| any other cohort | refused until 002 decides how ALTCs make teaching cohorts (plan decision 8) | refused | refused |

"Anywhere else" includes `ltct:pilots` (pilot enrolment is the coordinator's, at stage 7, by the manual method), `ltct:officehours`, and any course whose idnumber is not `ltct:<slug>`. This is the organisation-only check 002 R11 and 011 org-boundaries.md:155 said 008 "may add": it is added, because `site_config` cannot see enrolments.

**Removal from a cohort** (a learner who leaves an organisation): handled by core with 002's `enrol_cohort/unenrolaction = 3` (`lib/enrollib.php:49-68`; `enrol/cohort/locallib.php:112-119`): the enrolment is suspended and the instance's roles removed; the row, grades and completion stay. Re-adding re-activates it (`locallib.php:76, 209-210`). Note the core default is 0 (unenrol, `enrol/cohort/settings.php:41-45`): 002's setting is what makes this safe, and `drift` already guards it.

**Delivery, not pilot**: a cohort-sync instance with role Student is what 004's delivery reports count (004 R10; 002 changes the condition to "not manual"). 008 never uses the manual method.

## R8. Moving learners between organisations, and the `sil` → Area move

**Decision**: Two commands.
- `enrol mirror --from <K1> --to <K2>`: for every course where `ltct:org:<K1>` has an enabled cohort-sync instance in `ltct:published`, `ensure(ltct:org:<K2>, course)`. Previewed first.
- `move <file>`: per learner, a counted dry run, then set `ltct_org` to the new key (R3).

`move_rules` classifies, per learner and course they are actively enrolled in:
- **kept**: a shared course where the new organisation's cohort also has an enabled instance, or where the learner has another active enrolment (their Organisation enrolment stands in shared courses, 002 R10);
- **lost**: a shared course with no such match. The move is **refused** for that learner (FR-015, 002 FR-017);
- **suspended, by rule**: an organisation-only course of the old organisation. Reported; allowed, because 002 FR-017 says a mover's enrolment there is suspended unless the maintainer declares a replacement;
- **gained**: courses the new organisation's cohort is enrolled in.

The preview prints totals per course and per outcome. Apply sets `ltct_org` only for learners with no **lost** course. Dynamic cohorts then move the person, and core suspends the old cohort-sync enrolment (R7).

**Rationale**: 002 FR-017 and Clarifications 2026-10-03 ("Spec 008 provides a counted dry run first"). The order "mirror, then move" is exactly 002's rule that each Area's cohort is enrolled in every shared course the `sil` cohort is before anyone moves.

**Protection on move** (revised 2026-10-05): none. Protection is per person and goes with them; an organisation has no minimum, so a new organisation asks nothing of a mover. `move_rules` has no `flagged_protection` outcome and `move_service` neither reads nor sets protection, before or after the move (Doug, 2026-10-05 (scope review)). Until then a move read `service::org_minimum(<new key>)`, refused a learner below it, and confirmed `is_settled()` afterwards; that was dropped with organisation minimums.

**Edge case** (spec): a learner who already exists under another organisation is never moved by intake (outcome `flagged_other_org`). Only `move`, run deliberately, changes it.

## R9. Managers cohorts, the mentors cohort, and bulk mentor assignment

**Decision**:
- `local_ltuse_admin_apply_cohort_members` adds and removes people from `ltct:org:<K>:managers` and `ltct:mentors` only, with `cohort_add_member()` / `cohort_remove_member()` (`cohort/lib.php:191, 218-230`). It refuses any cohort with a `component`, and any `ltct:org:<K>` cohort. Removal checks membership first, because `cohort_remove_member()` fires `cohort_member_removed` even for a non-member.
- An ALTC covering several Areas is one row per Area entry (002 FR-015). Adding a member of organisation K to K's managers cohort is allowed. 002 permits a manager who is also one of its own organisation's people, and `may_manage_account` excluding managers from management is by design. The preview adds a non-blocking note: "this person is in an organisation they will manage; from now on only the site team can manage their account".
- `local_ltuse_admin_apply_mentors` writes manual user-context assignments of the `mentor` role, `component ''`, with `role_assign($mentorroleid, $mentorid, context_user::instance($learnerid)->id)` (`lib/accesslib.php:1621-1634`, idempotent). It requires the mentor to be in `ltct:mentors`, as 002's `mentors.php` does. With `endmentoremail` it removes all of one mentor's assignments with `role_unassign()`, the same effect as `cli/mentor_contacts.php --end-all`.

**Rationale**: 003 R1 (assignments must be manual rows, or `core_role_unassign_roles` cannot remove them), R5 (the `role_assigned`/`role_unassigned` observer maintains message contacts, so bulk assignment gets contacts for free), R6 (end-all). Core's `core_role_assign_roles` would also work (`enrol/externallib.php:1369-1424`, `contextlevel: 'user'`), but it requires a `role_allow_assign` row for the caller's role and offers no preview, so 008 calls `role_assign` from its own function behind the same capability check.

## R10. Course mentors: enrolled automatically, removed promptly

**Decision**: `local_ltuse` keeps every learner's course mentors enrolled in the courses they take, as Moodle's non-editing teacher (`teacher`, 012's "Course mentor"), through one dedicated enrolment instance per course, and removes them when their reason ends.

**Who is a learner's course mentor in course C** (`course_mentor_rules`), in order, first match wins:
1. a **one-course mentor** recorded for (learner, C);
2. the **mentors of a cohort** recorded for (cohort, C), when the learner is enrolled in C through that cohort;
3. otherwise the learner's **default mentors**: holders of the `mentor` role in the learner's user context (003).

A learner counts when they hold an **active** Student enrolment in C, through cohort sync or the Organisation enrolment, and C's idnumber is `ltct:<slug>`, not `ltct:officehours`. **Active** means all of: `user_enrolments.status` active, the instance (`enrol.status`) enabled, now within `timestart`/`timeend`, and the account not suspended. "Student" is read from the instance's `roleid`, not from the user's role assignments at the moment of an event, because core removes the role before it fires the unenrol event. A pilot learner (manual enrolment) is excluded: pilots are assessed by the pilot coordinator (stage 7). Precedence 1 and 2 replace the default for that course rather than adding to it, because "a course mentor is whoever assesses the learner in one course, and may be someone else" (002 Dependencies). This is plan decision 2.

**Records**: one new table, `local_ltuse_course_mentor` (data-model.md), holding one-course and cohort-mentor rows. Default mentors are never copied into it; they are read from `role_assignments` each time.

**Enrolment instance**: an `enrol_self` instance per course, marker `customchar1 = 'ltct:coursementor'`, `customint6 = 0` (no new self-enrolments).
- **Enrol**: `enrol_get_plugin('self')->enrol_user($instance, $userid, null)` with **no role**. `lib/enrollib.php:2112` checks no permission, so the service checks `local/ltuse:administer`, or the call runs from our own observer.
- **Assign the role separately**: `role_assign($teacherroleid, $userid, $coursecontext->id, 'local_ltuse', $instance->id)`. `enrol_self` reports `roles_protected() = false`, so a role given through `enrol_user()` has no component, and `unenrol_user()` would not remove it. Worse, a mentor also enrolled in the course another way would keep Teacher, and with it 016's identity entitlement.
- **Remove**: `role_unassign($teacherroleid, $userid, $coursecontext->id, 'local_ltuse', $instance->id)`, then `unenrol_user()`. The reconcile also removes any `local_ltuse`-component Teacher assignment that has no reason left.
- **Not the manual method**: `enrol_manual_enrol_users` always takes the first manual instance (`enrol/manual/externallib.php:107-114`), so a mentor instance could capture the pilot coordinator's enrolments, and manual counts as pilot in 004.
- **004**: its delivery and pilot reports already filter `role:name = student`, and `coverage.php` counts only Student-role instances. So a mentor's Teacher enrolment is never counted as delivery. 002's T080 must keep counting the Organisation enrolment by `customchar1 = 'ltct:orgenrol'` (or Student role), never by `enrol = 'self'` alone, so the `ltct:coursementor` instance is never counted (cross-spec effect). V11 confirms the mentor is not listed.

**Groups**: each course mentor has one group in the course holding them and the learners they assess there, idnumber `ltct:mentorgroup:<mentor user id>`, name "Mentor group <n>" (n the next free number in that course). Groups are never named after an organisation, an Area or a learner (002 FR-019), and the course stays in group mode 0 (002 FR-011). Spec 012's assessed activities, set to separate groups, limit each assessor to their own learners. Plan decision 3.

**Sync**: event-driven, with an hourly reconcile.
- **Observers.**
  - `\core\event\role_assigned` and `role_unassigned` for the mentor role in a **user** context: `sync_learner($event->contextinstanceid)`, because the learner owns the context, and `sync_mentor($event->relateduserid)`, because the mentor is the user who holds the role.
  - `user_enrolment_created`, `updated` and `deleted`, filtered on the course (idnumber `^ltct:[^:]+$`, not `ltct:officehours`) and the instance (not `ltct:coursementor`), never on the user's role at event time.
  - `enrol_instance_updated` and `enrol_instance_deleted` on those courses, because disabling or deleting an instance fires no per-user event.
  - every `\core\event\user_updated`, because suspending an account ends every enrolment's reason without touching an enrolment, and the event does not say which fields changed. `sync_learner` is cheap and idempotent.
  - Our own table writes.
  All of them call `sync_course`, `sync_learner` or `sync_mentor`. `cohort_enrolment::ensure()` and `remove()` call `sync_course()` directly.
- **Scheduled task.** `\local_ltuse\task\course_mentor_reconcile`, hourly, recomputes every `ltct:` course, like `officehours_reconcile`, and runs `cohort_enrolment::reconcile_pathways()` (R11).
- **Removal.** The mentor's Teacher role and enrolment go (above), and they leave the group, when no learner they assess in C remains active. Grades they gave stay on the learner's record.
- **Switch.** The sync runs only when the `local_ltuse/coursementorsync` setting is 1. `moodle/site/settings/admin.yaml` declares it 0 until 016 narrows its course-mentor path to a shared mentor group (plan decision 11). Instance checks turn it on in their `--site-dir` copy, and `local_ltuse_admin_check` reports it. With it off, the observers and the reconcile do nothing, and the course-mentor table can still be filled.
- **Concurrency.** `sync_course` takes a per-course lock (`\core\lock`), so an observer and the reconcile never interleave on one course. Its writes are idempotent.

**Why it must be prompt**: spec 016 entitles anyone who holds `local/ltuse:viewidentity` (granted to `teacher`) in a course where a protected learner is actively enrolled (016 FR-006 as amended by Doug 2026-10-04: "Every course mentor of a course the learner takes is entitled while they hold that role"). A course-mentor enrolment that outlives its reason would keep showing a protected learner's real identity. So removal is event-driven, and the reconcile is a backstop, not the mechanism.

**Reach, as written today**: that entitlement is course-wide. Shared courses are open across organisations and in group mode 0, so a course mentor sees the real identity of **every** protected learner actively enrolled in the course, not only the learners they assess. Auto-enrolment turns every default mentor into such a viewer in every course their mentees take. A safer reading would narrow 016's course-mentor path to "viewer and learner share a group with idnumber `ltct:mentorgroup:*` in that course", using the groups 008 already makes. That is one function in 016 (`entitlement::is_course_mentor_of()`). It changes Doug's wording, so it is plan decision 11, raised with 016 as an open question on 2026-10-04.

**Rationale**: 002 Dependencies ("automatic behaviour in `local_ltuse`, not a manual step"), 003 R8, 012 R3, 016 FR-006.

## R11. Pathways (spec 006)

**Decision**: `enrol pathway --cohort <idnumber> --pathway <key>` first runs `enrolment_rules` for the cohort against every course in `\local_ltuse\pathway\catalogue::courses($key)`. Unless the cohort is of a kind the rules allow in shared courses (an `ltct:org:<K>` cohort today), the whole command is refused and 006's table is not touched. Only then does it call 006's `\local_ltuse\pathway\assignments::assign($key, $cohortid, true)`, and `cohort_enrolment::ensure($cohortid, $courseid)` for each course. The order matters because 006 never lowers `enrol`: a refused cohort left at `enrol = 1` would stay there. An observer on 006's `\local_ltuse\event\pathway_courses_changed` ensures the new courses for every cohort in `assignments::cohorts_for($key, true)`. A course leaving a pathway, or `unassign`, unenrols nobody; the site team's summary reports it.

**Rationale**: agreed with the 006 session on 2026-10-04 and locked: 006 never enrols and never sets `enrol = 1`; 008 adds no table of its own (`local_ltuse_pathway_cohort` has `enrol int(1) default 0`, unique `(pathwaykey, cohortid)`; `assign(..., true)` upgrades and never downgrades). A pathway's course list changes whenever a course is republished with different competencies or reaches stage 8, because 006 derives it at view time, so a re-sync is required, not optional. Manager pathway assignment on 006's page never enrols; making it enrol would be one observer on 006's `pathway_assigned` (plan decision 9).

**Final names** (006's `contracts/pathway-api.md`, 2026-10-04):
- Keys are `competency:<descriptor slug>` or `role:<key>`, at most 100 characters. The server validates them with `catalogue::parse_key($key)` (null on a bad key; refuses a trailing newline, which `preg_match` on `KEY_PATTERN` would let through), then `exists()` and `is_assignable()`. Ids in the event's `other` are strict ints, so DB ids are cast before comparing.
- `catalogue::courses(string $key): int[]` returns course ids, delivery courses only (`local_ltuse_course_pathway.delivery = 1`, visible, idnumber `^ltct:[^:]+$`). 008 uses the catalogue for membership and never reads 006's table, so the delivery rule lives in one place.
- `pathway_courses_changed` carries `other = {pathwaykey, added: int[], removed: int[]}`, one event per key, fired after commit. 008 ensures `added` for every cohort in `cohorts_for($key, true)`, and reports `removed` in the summary without unenrolling.
- It is not fired when a course is hidden or shown, so a re-shown course is picked up by the hourly reconcile (`course_mentor_reconcile` also runs `cohort_enrolment::reconcile_pathways()`), not by an observer on `course_updated`.

**Until 006 landed** (it merged on 2026-10-04, #90): the pathway commands reported "pathways are not installed" (`class_exists` false) and do nothing. Courses are still enrolled one by one.

## R12. The credential: a second service, per-person tokens

**Decision**:
- `db/services.php` declares a second service: name "LTC administration", shortname `ltuse_admin`, `requiredcapability` `local/ltuse:administer`, `restrictedusers = 1`, `enabled = 1`, `uploadfiles = 0`, listing only the `local_ltuse_admin_*` functions and `core_webservice_get_site_info`. 016's `local_ltuse_set_protection` is not listed, because R5 calls the PHP method.
- New capability `local/ltuse:administer`, system context, `RISK_PERSONAL | RISK_DATALOSS | RISK_SPAM` (it creates accounts that receive email), no archetype.
- New declared role `ltctadmin` (system context) in `moodle/site/roles.yaml`, `allowassign: [mentor, teacher]`, holding exactly:

  | Capability | Why |
  |---|---|
  | `local/ltuse:administer` | the service's `requiredcapability`, re-checked by every function |
  | `webservice/rest:use` | without it every call fails as an access exception that reads like a bad token (as for `ltcpublisher`) |
  | `moodle/user:create`, `moodle/user:update` | intake, suspension, organisation field |
  | `moodle/user:viewdetails`, `moodle/user:viewhiddendetails`, `moodle/site:viewuseridentity` | matching by email, summaries |
  | `moodle/cohort:view`, `moodle/cohort:assign` | managers and mentors cohorts; reading organisation cohorts |
  | `moodle/course:enrolconfig`, `enrol/cohort:config`, `enrol/self:config`, `enrol/self:manage` | cohort-sync and `enrol_self` instances, course-mentor enrolment |
  | `moodle/role:assign` | mentor (user context) and Course mentor (course context) assignments |
  | `moodle/course:managegroups` | mentor groups |
  | `local/ltuse:manageprotection` | 016's `can_manage_protection` for new accounts (R5); needs 016's allowlist widened in the same PR |

  `local_ltuse_admin_check` reports any of these the token user lacks, by name.
- Each site-team member gets **their own** token on **their own** account, made by a new `cli/setup_admin_token.php --username=<u> --token-file=<path>`. The token is written to a mode-600 file outside the repo and never printed. The CLI reads it from `MOODLE_ADMIN_TOKEN`. Unlike `setup_publishing.php`, which writes `external_services_users` and deletes `external_tokens` with `$DB` directly (lines 106-129), the new script uses core's APIs: `(new webservice())->add_ws_authorised_user()` (`webservice/lib.php:247`), `\core_external\util::generate_token()` for creation, and `webservice::delete_user_ws_token()` (`webservice/lib.php:483`) on `--rotate`. Each signature is confirmed on `MOODLE_502_STABLE` before use. The same change to `setup_publishing.php` is a cross-spec note, and until it is made those two writes go in the README's XI list.

**Signatures confirmed for `setup_admin_token.php`** (T018, `MOODLE_502_STABLE`, `public/`, 2026-10-04):
- `webservice::add_ws_authorised_user($user)` (`webservice/lib.php:247`): takes a `stdClass` with `externalserviceid` and `userid`, sets `timecreated` itself and inserts the `external_services_users` row. It does not check for an existing row, so the script asks `webservice::get_ws_authorised_user($serviceid, $userid)` (`:311`) first.
- `webservice::delete_user_ws_token($tokenid)` (`:483`): deletes one `external_tokens` row by id. The ids come from `webservice::get_user_ws_tokens($userid)` (`:395`), which lists permanent tokens with their service id (`wsid`) but never the token value; so an existing token cannot be read back, and without `--rotate` the script leaves it alone and writes nothing.
- `webservice::get_external_service_by_shortname($shortname, $strictness)` (`:772`).
- `\core_external\util::generate_token(int $tokentype, stdClass $service, int $userid, context $context, int $validuntil = 0, string $iprestriction = '', string $name = ''): string` (`lib/external/classes/util.php:193`): refuses a user without the service's `requiredcapability` in `$context` (`nocapabilitytousethisservice`), records `creatorid = $USER->id`, and returns the token.
- `core_user::get_user_by_username($username, $fields = '*', $mnethostid = null, $strictness = IGNORE_MISSING)` (`lib/classes/user.php:181`).

**Rationale**:
- A token belongs to one user and one service; Moodle enforces the service's `requiredcapability` at system context and, with `restrictedusers`, membership of `external_services_users` (`webservice/lib.php:70-165`). A plugin may list core functions in its own service (`lib/upgradelib.php:1376-1410`). So a second service is how the admin tool gets a credential distinct from the publisher's (FR-009).
- The service's function list grants nothing; the token's user still needs each capability the functions check. That is why the role exists and is declared, not clicked (constitution II).
- Per-person tokens mean Moodle's logs show which team member made each change. A shared admin account would hide that. Plan decision 7.
- Removing a service from `db/services.php` deletes its tokens on upgrade (`lib/upgradelib.php:1312-1318`); the README says so.

**Alternatives**: reuse `MOODLE_TOKEN` (rejected, FR-009); a shared `ltctadmin` user (rejected above); SSH (R1).

## R13. Site settings the tooling relies on

**Decision**: a new `moodle/site/settings/admin.yaml` (`rows: [14]`) declares `allowaccountssameemail = 0` (R2) and, since 2026-10-05, `authloginviaemail = 1`, so everyone signs in with their email address (R2's usernames). Since the same day it also holds the site's general account rules, moved from 016's `identity.yaml` by 016's scope review because they apply to everyone and are not protection measures: `protectusernames = 1` (with email as the login, the forgotten-password form must not confirm an address has an account; core's default), the login methods as plugin entries in `site.yaml`, `auth_webservice` enabled and `auth_email` disabled (webservice is the only method beside manual and nologin, for the publisher's account; `$CFG->auth` is in no admin setting, so the applier sets it through core's `\core\plugininfo\auth::enable_plugin()`), `registerauth = ""` (no self-registration; core's default) and `authpreventaccountcreation = 1` (no account from an outside login) (Doug, 2026-10-05 (scope review)). `enrol_cohort/unenrolaction = 3` (002, `groups.yaml`) and `tool_dynamic_cohorts` realtime and non-bulk rules (002, `cohorts.yaml`) stay where those specs declare them; 008's preflight (`ltct_admin.py check`) reads them through `local_ltuse_admin_check` and refuses to run if any is wrong, naming the setting.

**Rationale**: every rule 008 depends on must be in the repo (constitution II) and checked before a write, because a wrong `unenrolaction` turns "remove" into data loss.

## R14. Learner data never enters the repository

**Decision**:
1. `ltct_admin.py` refuses any input or output path inside **any git working tree**, not just this repo, and anything under the repo root. It **fails closed** and needs no `git` executable:
   - resolve the path (`Path.resolve()`), then walk up from it, or from its nearest existing ancestor if it does not exist yet (a new `--out` folder), refusing if any ancestor holds a `.git` directory **or file** (worktrees have a `.git` file);
   - also refuse anything `is_relative_to` the repo root;
   - if a check cannot be run (a permission error reading an ancestor), refuse.
   If the refused input is inside this repository and `git` is available, it also tells the operator whether git already has it (`git log --all --oneline -- <path>`), because GitDoc commits a saved file within minutes and refusing to read it does not un-publish it.
2. The tool writes no file unless asked (`--out` for a summary or a blank template), and no log file ever. Output goes to the terminal.
3. Default output **masks people**: row numbers and masked emails (`a***@example.org`), never names. `--show-people` prints names and emails, for a human at their own terminal. The `/manage-learners` command never passes it and never reads the intake file (R16).
4. The token is read from the environment, never printed; errors print the setting's name, not its value (spec edge case).
5. A suggested home for operator files, outside every repository: `~/ltct-private/` (Windows: `%USERPROFILE%\ltct-private\`). The tool suggests it in every refusal.
6. Tests and committed examples use `example.org` addresses and `fixture-*` organisation keys only (FR-013), matching the existing test convention.

**Rationale**: constitution III (NON-NEGOTIABLE) and FR-004. `.gitignore` already ignores `*.csv`, `*.xlsx`, `*.xls`, `*.ods` as a backstop (002 R8), but a `.txt` or `.tsv` would not be caught, and the repo cannot see an operator's GitDoc setting. Checking every git tree, not only this one, also covers the sibling worktrees (`virtual-ltct-008` and the like) that the team uses. Masking is new: a guided command runs the tool through an AI assistant, so whatever the tool prints reaches the assistant's context. INTENT's data-protection position is undecided (Open questions), so 008 sends the assistant row numbers, not people (plan decision 1).

## R15. Preview, confirm, idempotent apply, interrupted runs

**Decision**: every changing command is two steps. The first previews (server-side, read-only) and prints a **confirmation code**. The second repeats the command with `--apply --confirm <code>`. There is no interactive prompt, so the same flow works in a terminal and in the guided command.

**The code binds the inputs and the kind of outcome, not the display.** It is the first 10 hex characters of a SHA-256 over, for each row in order:
- the row number;
- a SHA-256 of the full normalised input row (email lowercased, organisation, courses sorted, protection, pseudonym, names, and for other kinds their columns);
- the row's **outcome class**.

`new`, `will_set_org`, `will_enrol` and `unchanged` share one class, *proceeds*, as do a membership, mentor or suspension row's `would change` and `unchanged`, and a move's `would move` and `moved`. Each `flagged_*`, `rejected` and `waits` value is a class of its own. `changes[]` is left out, because its targets are already in the input hash.

The masked key is not part of the code, so `--show-people` does not change it, and two addresses that mask alike cannot be swapped. Nothing is stored between the two runs (data-model §2). On `--apply --confirm`, the CLI re-reads the file, **previews again**, and recomputes the code from the file and the fresh outcome classes. It refuses if the result differs from the confirmed code, which catches an edited column, or a row that has become flagged, rejected or waits since. It then sends each row with its fresh outcome as `expectedoutcome`.

**Resuming is "further along", not "changed".** Because the outcome class absorbs progress, a re-run after a partial apply recomputes the same code. Each apply call carries the row's outcome from that fresh preview, and the "further along" rule covers what changes between the fresh preview and the write: a retry, or another run racing this one. The server classifies the row's current state and acts as follows (pure rules, harness-tested):
- **the same outcome**: apply it;
- **further along the same path**: finish what remains, or report `already done`. For intake the path is `new → will_set_org → will_enrol → unchanged`. For a membership, mentor or suspension row it is `would change → unchanged`. For a move it is `would move → moved`;
- **anything else** (for example a row that has since become `flagged_*`, `rejected` or `waits`, or a move whose `lost` set changed): refuse that row with "changed since the preview; preview again".

An interrupted run, a lost response and a plain re-run with the same code all finish the work, and already-done rows count as success (exit 0). Every apply function takes **one row** with its expected outcome, so a refusal is per row and a retry re-sends exactly one row.

**Network**: `moodle_client.py` gains bounded retries (3 attempts, back-off 2/4/8 s) for connection errors and HTTP 5xx only, never for a Moodle exception. Because every apply is one idempotent row with a "further along" rule, a retry after a lost response reports `already done` rather than failing. A lost "create" is re-matched by email under the R2 lock.

**Rationale**: FR-002 (nothing sent until the operator confirms), FR-003 and SC-002 (re-apply changes nothing), and the low-bandwidth edge case. The code ties the confirmation to exactly what was previewed, which a bare `--yes` cannot.

## R16. The guided command and plain language

**Decision**: `.claude/commands/manage-learners.md`, modelled on `publish-to-moodle.md`: frontmatter `allowed-tools: Bash(python scripts/ltct_admin.py:*)`, numbered steps (check the connection → ask what the operator wants to do → preview → explain the preview in plain words → apply only after the operator says yes, passing the code), and a Rules section:
- never Read, open, or copy an intake file, and never pass `--show-people`;
- never put an intake file, a summary or any output inside the repository;
- never invent a cohort, course or organisation key; offer the ones `ltct_admin.py list` prints;
- after any refusal, quote the tool's own message;
- a row that `waits` joins once identity protection is ready for that person. This is all that is left of 002 R13's production gate, narrowed on 2026-10-05 to "a person who asked for protection waits until 016 can set their level". Intake enforces it row by row, so `ltct_admin.py` prints no reminder and nobody marks an organisation (Doug, 2026-10-05 (scope review)).

`ltct_admin.py` itself prints plain sentences and the next command to run, never a stack trace (FR-011). `ltct_admin.py template --kind intake --out <path>` writes a blank intake file with the column headers, so a manager can fill in a list the site team applies unchanged (US4).

## R17. Moving a course from Pilots to Published: not 008's

**Decision**: Out of scope. 002 R11 left it "to spec 008 or a later change". It is a publishing step: a course reaches Published at stage 8, which only `course_stage.py` may detect (constitution I), and the publisher already calls `local_ltuse_place_course`, which accepts `ltct:published`. The natural home is the publisher placing a stage-8 course there on its delivery publish. Recorded as plan decision 4 and as a cross-spec note.

## R18. What core and existing code already give, reused

- `site_config.validate(site_dir)` → declared organisation keys, cohort idnumbers and `MENTORS_COHORT`, offline, so a bad key is refused before any call.
- `moodle_payload.refuse_inside_repo()` → the pattern R14 extends.
- `\local_ltuse\organisation\access` (002, built) → `ENROL_MARKER`, `may_enrol_into()`, used for per-row course enrolment so the site team and managers follow one rule.
- `local_ltuse_managed_organisation_keys()` / `local_ltuse_organisation_member_keys()` (`lib.php:110, 158`) → summaries.
- `cli/mentor_contacts.php` → end-all behaviour and contacts.
- `cli/open_courses.php` → cohort-sync instance handling precedent.
- `core_cohort_get_cohort_members` (`lib/db/services.php:397`) returns user ids only; 008's summary is its own function so it can return masked rows and counts in one call.

## Instance results

To be filled by the quickstart run (V1–V19), with test accounts on `example.org` only. Manager-pilot findings name testers only as "tester 1/2/3" with the role "ALTC" or "organisation manager"; they name no Area or organisation entry, and give no protection level or count tied to an entry (constitution III), here or in a PR or issue.
