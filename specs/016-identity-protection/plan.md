# Implementation Plan: Identity protection for at-risk users

**Branch**: `016-identity-protection`, requires `003-mentor-role` and the 011 open-courses change | **Date**: 2026-10-02, amended 2026-10-05 (scope review) | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/016-identity-protection/spec.md`

## Summary

**Per person, only for someone who asks** (Doug, 2026-10-05 (scope review)). Protection is granted to a person who is concerned about exposure, normally when they are added. Nothing changes for anyone else, beyond a few site settings that cost nobody anything. The 2026-10-04 design changed the site for everyone so that the few would be covered without anyone acting; the scope review cut or narrowed those measures, and the decisions table below records each one.

**Names: change the account itself, not the page.** Moodle 5.2 has no hook into `fullname()`. Every view, on the web and in the app, renders names live from the user record, and so do emails, badges, certificates and search (R1). So for a protected user, `local_ltuse` writes the protected display into the account's own `firstname` and `lastname`. It also blanks the alternate names and the other identifying fields, including `ltct_role` (R6). Every view then protects the person at once.

**Real identity** is kept in our own table, `local_ltuse_protection`, which has a privacy provider (R5). The learner's own words (their profile description, interests and posts) are never rewritten; the learner is told to review them.

**Supporters see the real identity through one entitlement function.** It covers the site team, the assigned mentor (a user-context capability on the 003 role), the course mentor whose own mentor group holds the learner in a course they take (the same capability on the course-level `teacher` role, R7 path 4), and managers of the learner's own organisation, recognised by managers-cohort membership (R7). The real identity and a **Protected** marker appear only on our own surfaces:
- the profile node, on a protected person's profile;
- the Mentoring page, on the web and in the app;
- a "People I support" page, with no download;
- the granting page.

A non-entitled viewer never sees the marker.

**Nobody can quietly undo it** (R2):
- A `before_user_updated` hook callback re-applies the protected state, and never throws. A keyed bypass set, a per-user lock and a transaction keep the service's own writes safe.
- The `user_updated` observer and an hourly reconcile task cover the writers the hook misses. Both do nothing for anyone without protection, and a failed repair fails the task run so core raises the alert.

**What leaves the site** (R8, R10, R14):
- Classmates never see a protected person's email. Course staff still see it, so the granter confirms, at every grant, that the address identifies neither the person nor their organisation (FR-016).
- Gravatar is off, profile pictures need a login, and no email is sent from a person's own address.
- Course leaders cannot download course backups. Course logs stay open, and a protected person who asks gets them blocked in their courses (not built yet).
- The certificate prints the account's name, which is the protected display; certificate emails to teachers stay off.

**Organisations**: there is no organisation-wide protection and no setting that hides identities from an organisation's own managers (changes 9, 10). `ltct_org` stays visible at every level (decision 2, option a); spec 004's report now scopes by the member cohort instead (R11).

**Protection is meant to be set at intake, before a learner's first activity.** An organisation's manager grants it then. Any later raise or lowering, and every correction, is the site team's, and a change for someone with activity needs an acknowledgement, because a rename links the two identities. Nothing is lowered automatically (R12, R13).

## Technical Context

**Language/Version**: Python 3.12 (`scripts/`; CI uses 3.12), PHP 8.3 for `local_ltuse` on Moodle 5.2.3+.

**Primary Dependencies**:
- Moodle core:
  - `core_user` (`user_update_user`, `core_user::update_picture`, the `before_user_updated` hook);
  - `core_privacy` (including `core_userlist_provider`);
  - `core_message`, `core_cache`, `core\lock`, `core_group` (`groups_get_group_by_idnumber()`, `groups_is_member()`).
- `mod_scheduler`, once spec 011 installs it, for re-saving slot names (R15).
- Our own `local_ltuse`, built on 003's `db/events.php`, `db/hooks.php` and `db/mobile.php`.
- PyYAML.

**Storage**: Moodle's database, in two new `local_ltuse` tables (data-model.md). The repo holds only declarations.

**Testing**:
- `pytest` for the new validation in `site_config.py`, on synthetic inputs.
- A PHP harness, `tests/protection_harness.php`, for the pure pieces: levels and raises, what a manager may change, withhold sets, display, pseudonym and username rules, the email-address warning, and path 4's course rule.
- PHPUnit with synthetic users in `moodle/local_ltuse/tests/`. It includes:
  - the hook-mutation test (R2);
  - an exception thrown while a user is in the bypass set;
  - a raise refused without `requested` and `emailchecked`;
  - the site-team-only changes after intake;
  - path 4 limited to the mentor's own group.
- Instance checks V1–V17 in [quickstart.md](quickstart.md), with test accounts only.

**Target Platform**: the self-hosted Moodle 5.2.3+ build host and, later, production (015), plus the Moodle Android app.

**Project Type**: the training system: declarative site configuration and its Moodle plugin.

**Performance Goals**:
- A grant takes under 2 minutes.
- A change shows on the web within 5 minutes.
- Writers the hook misses are repaired within one reconcile run (60 minutes).
- The app catches up on its own refresh (decision 6).
- With nobody protected, the hook, the observer and the reconcile task each cost one indexed read or none.

**Constraints**:
- No learner data, pseudonym or real identity in git, fixtures, logs or PRs (III, FR-013). Evidence from `ltct-test-*` accounts only may go in a PR (change 24).
- No edit to core, the theme, `mod_customcert` or `mod_scheduler` (XI).
- Participation is never limited (FR-003).
- Nothing changes for a person who did not ask (spec Clarifications 2026-10-05).

**Scale/Scope**: a small number of protected users, and four levels including `none`.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. Levels, withheld fields, settings and roles are declared in `moodle/site/`. Who is protected lives only in Moodle and is never read back. |
| II. Portability | PASS. `apply` rebuilds every declared piece on a fresh instance (V1, SC-006). Protection data comes back with the database restore, the same as all learner data, and is in the privacy export. |
| III. Public repo (NON-NEGOTIABLE) | PASS. Declarations only. An organisation that asks not to be named publicly gets a neutral key and name from its first commit (change 23). `drift` reports a count. SC-007 findings stay in a private store, and the PR records counts and issues only; evidence from `ltct-test-*` accounts may go in the PR (change 24). |
| IV. Disclosure boundary (NON-NEGOTIABLE) | PASS. Not touched. |
| V. CBC fidelity | PASS. The certificate is unchanged: 013's wording and its `studentname` element (R10). |
| VI. No LMS orientation | PASS with a gate. A protected learner does nothing extra, and nobody else is changed. Granting is one page (R12). SC-007 needs real users. |
| VII. One shape | PASS. One set of levels and one entitlement rule for every person and organisation. |
| VIII. Language data | PASS. Not applicable. Pseudonyms are free text in any script, compared after NFC normalisation. |
| IX. Flat cost, field-ready | PASS. No paid service. The protection holds in the app, because the app shows server-side names (R1). Its cache lag is a listed gap. |
| X. Traceable and verified | PASS with gates. Cites row #26, which the delivering PR updates. Every mechanism has a V check that blocks its story. **Recurring work added** (below) is named, and it is not covered until 015's operator is decided. |
| XI. Survives an upgrade | **PASS with listed risks.** Only supported extension points are used: a hook, events, tasks, web services, `myprofile_navigation`, `db/mobile.php`, privacy and message providers. Core writes go through core APIs. Two exceptions are listed in the plugin README (contracts/protection-service.md): mutating the hook's object (guarded by a PHPUnit test, and V7 re-run on every core upgrade while anyone is protected; `user_update_user()` is deprecated for 5.3, MDL-82650); and `mod_scheduler`'s `slot` class (V17 re-run on every re-pin). The third, indexed reads of `logstore_standard_log` and `messages`, is gone (change 16). `local_ltuse` keeps `requires = 2026042000` and `supported = [502, 502]`, and the version bump is verified by V1. |
| Platform: core first | PASS. Core settings and capabilities do most of it. Our plugin does only what no core setting can do per user (R1), and no free plugin offers it. |

**Recurring work this spec adds** (Principle X), carried by the site team until 015 names an operator (Doug, 2026-10-05 (scope review), change 25):
- granting and correcting protection for people who ask;
- acting on a reconcile alert (the hourly task fails when a repair fails);
- V7 on a core upgrade while protected people exist.

~~Every learner's name and email changes (decision 3); choosing neutral usernames and organisation names; watching the reconcile repair count in the task log.~~ *(Removed by the scope review, changes 3, 15, 19, 23.)*

Re-checked after the 2026-10-05 scope review: the XI exceptions fell from three to two, and the recurring work from four items, one of them every learner's name and email changes, to three that arise only when someone is protected.

## Project Structure

### Documentation (this feature)

```text
specs/016-identity-protection/
├── plan.md              # This file
├── research.md          # R1–R15 and the known gaps
├── data-model.md        # Declared items, two tables, the account while protected, state transitions
├── quickstart.md        # Instance checks V1–V17
├── contracts/
│   ├── declaration.md         # protection.yaml, identity settings, roles, report scope
│   └── protection-service.md  # entitlement, the service, the web service, hook, observer, tasks, surfaces, privacy
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
moodle/
├── site/
│   ├── protection.yaml                 # new: levels, withheld fields, neutral surname (#26)
│   ├── settings/identity.yaml          # new: email, Gravatar and picture settings, mostly core defaults
│   ├── roles.yaml                      # changed: backup prohibit; viewidentity, manageprotection
│   ├── reports.yaml                    # changed (decision 2, option a): cohort scope condition, no Organisation column
│   └── README.md                       # changed: protecting a person; neutral keys on request; protect early
├── local_ltuse/
│   ├── classes/protection/entitlement.php      # new: can_view_identity, can_manage_protection, is_site_team, may_be_entitled
│   ├── classes/protection/service.php          # new: the one place a level is applied
│   ├── classes/protection/levels.php           # new: pure rules (harness-tested)
│   ├── classes/protection/surfaces.php         # new: what the profile, Mentoring and People I support pages show
│   ├── classes/protection/hook_callbacks.php   # new
│   ├── classes/protection/observer.php         # new
│   ├── classes/task/apply_protection.php       # new: adhoc
│   ├── classes/task/reconcile_protection.php   # new: scheduled
│   ├── classes/external/set_protection.php     # new
│   ├── classes/form/protection_form.php        # new
│   ├── classes/privacy/provider.php            # changed: two tables, userlist, message provider
│   ├── classes/siteconfig/protection.php       # new: store config; drift count
│   ├── classes/siteconfig/{inspector,applier,drift}.php  # changed
│   ├── classes/mentoring.php                   # changed (003's): marker and real name for the entitled
│   ├── protection.php, protected.php           # new: granting, People I support
│   ├── lib.php                                 # changed: profile node
│   ├── db/access.php                           # changed: two capabilities
│   ├── db/hooks.php, db/events.php, db/mobile.php          # changed (003's files)
│   ├── db/tasks.php, db/messages.php           # new
│   ├── db/install.xml, db/upgrade.php          # changed: two tables
│   ├── db/services.php                         # changed: one web service
│   ├── tests/protection_test.php               # new
│   ├── version.php                             # bumped
│   └── README.md                               # changed: the two XI exceptions
└── REQUIREMENTS.md                             # row 26 updated on delivery
scripts/site_config.py                  # changed: protection.yaml, role, settings and report rules
tests/test_site_config.py               # changed
tests/test_protection_declaration.py    # new
tests/protection_harness.php            # new
.github/workflows/site-config.yml       # changed: runs protection_harness.php
```

Removed by the scope review (2026-10-05): `orgprotection.php`, `classes/external/set_org_protection.php`, `classes/form/org_protection_form.php`, the `local_ltuse_org_protection` table, `local/ltuse:manageorgprotection`, the `ltct_certname` profile field with the `text` datatype and the certificate's `userfield` element (`profile-fields.yaml`, `certificate/template.yaml` and `certtemplate.php` are back to main's).

**Structure Decision**: This follows 004 and 013. Declarations go through `site_config.py` and a new `siteconfig` class. Everything that touches a user goes through `entitlement` and `service`, which every page, web service, hook, observer and task calls. The pure rules have no Moodle calls and are harness-tested in CI.

## Decisions on the plan's limits

These were for the maintainer (Doug). On 2026-10-05 he accepted every recommendation of the scope review; each row records the outcome. Rejected designs are kept here, struck through or marked, so their history stays readable.

| # | Limit or choice | Status |
|---|---|---|
| 1 | ~~**Email leaves course leaders' views for everyone** (R8). Teachers no longer see any learner's address on participants, downloads, grade exports or reports. Messaging stays. **If rejected**, US1's email protection needs the relay-address design (R8), which needs a mail relay someone operates.~~ | **Rejected** (Doug, 2026-10-05 (scope review), change 1). Staff views keep email (core's defaults). Replaced by a per-person check: at every grant, the address must identify neither the person nor their organisation (change 2, FR-016). The relay stays unbuilt; a protected person's address visible to course staff is a known gap (FR-015). |
| 2 | **`ltct_org` and spec 004's report scope** (R11). ~~`ltct_org` becomes private for everyone, after spec 004's report scope moves to a cohort condition. Classmates then see no one's organisation. This is a **precondition**: until it lands, only the `email` level is available. Amends 002 FR-008.~~ | **Option (a) accepted** (Doug, 2026-10-05 (scope review), change 11). The report scope moves to the member cohort (T034–T035, done); `ltct_org` stays visible (T036 not shipped); the orgscope gate is removed. FR-001 no longer promises to hide the organisation, and the preview and FR-015 say so. 002 FR-008 is not amended. |
| 3 | ~~**Names and email are locked for every manual account** (R3). Learners no longer edit their own. The site team changes them in core's admin editor; for protected users, entitled people correct them on the granting page. This is recurring site-team work. **If rejected**, R4's placeholder surname becomes mandatory, and the hook alone guards names.~~ | **Rejected** (Doug, 2026-10-05 (scope review), changes 3 and 4). No field locks; the placeholder surname `·` is mandatory (R4); the hook guards names. `auth`, `registerauth` and `authpreventaccountcreation` move to spec 008's `admin.yaml` as general account rules, and a per-account login guard replaces the refusal of other login methods (R3, T042). |
| 4 | **Late changes and history** (R13). Raising or lowering a level for someone with activity needs an acknowledgement. ~~Leaving a protected organisation keeps the level until someone lowers it.~~ Amends the spec's "History" and "moves between organisations" edge cases. | **Accepted in part** (Doug, 2026-10-05 (scope review), changes 9 and 16). The acknowledgement stays, with a cheaper activity signal (first access or any enrolment). The organisation half is **rejected** with organisation minimums. A fresh account is an option to discuss, not the default advice. |
| 5 | **Course leaders lose course backups and course logs** (R14). | **Split** (Doug, 2026-10-05 (scope review), changes 5 and 6). **5a, backups: accepted**, and the site team leaves no backup with users in a course. **5b, logs: rejected** site-wide; on request, a block in the courses a protected person takes, including `report/log:viewtoday` (T027 reopened, not built). |
| 6 | **Known gaps** (research): the badge email hash, app cache lag, copies already sent, file metadata, ~~backups carrying `ltct_certname`~~ backups carrying account fields, core exports without a marker, same-first-name lookalikes; since 2026-10-05 also the email visible to course staff, the organisation visible at every level, course-log locations unless asked, and a course leader's route to path 4. Amends SC-005 for the app. | Accepted, listed in the delivering PR (FR-015). The list was amended by the scope review (Doug, 2026-10-05 (scope review)). |
| 7 | **Where supporters see the real identity** (R7): the profile node (web), the Mentoring page (web and app), "People I support" ~~with its CSV~~, and the granting page. Everywhere else they see the protected display. Amends US2-1, US2-3 and FR-007. | Accepted, **without the CSV** (Doug, 2026-10-05 (scope review), change 14). |
| 8 | ~~**Organisation minimums are Moodle data, not repo declarations** (R12). They are set by the site team, and the maximum is `firstname`. An organisation that may need protection gets a neutral key and name from its first commit; SIL's Area entries are a deliberate exception (spec Clarifications 2026-10-04). Amends FR-014.~~ | Accepted by Doug, 2026-10-04; **rejected** (Doug, 2026-10-05 (scope review), change 9): there are no organisation minimums, which supersedes Matthew's line in the spec's 2026-10-02 Clarifications. A neutral key only for an organisation that asks not to be named publicly (change 23). |
| 9 | ~~**The global search `core_user` area is disabled**, but global search itself is not (R9), so 014 can still use it.~~ | **Rejected** (Doug, 2026-10-05 (scope review), change 7). Global search is not on; the spec that turns it on handles protected people. |
| 10 | **Course mentors see the real identity** (R7 path 4): `teacher` holds `local/ltuse:viewidentity`, counted only in `ltct:<slug>` courses the learner is actively enrolled in, never the office-hours course, ~~in the whole course~~ and only for learners in the mentor's own group there, `ltct:mentorgroup:<mentor id>`. A course leader (`editingteacher`) is still not entitled. | **Accepted** (Doug, 2026-10-04, FR-006); **narrowed** (Doug, 2026-10-05 (scope review), change 22). Spec 008's `coursementorsync` stays 0 until this is merged. |
| 11 | ~~**An organisation may withhold its people's real identities from its own managers** (`managers_see_identity`, R7 path 3, R12).~~ | Answered by Doug 2026-10-02; **rejected** (Doug, 2026-10-05 (scope review), change 10), reversing that answer. Path 3 is unconditional. A person who does not want their managers to see their identity is placed by the site team under a neutral organisation entry with no managers. |

## Cross-spec effects

- **011 open-courses change**: lands first. Its removal of organisation groups is what keeps a protected person's organisation off forum posts and the participants page as a group name. 016 needs no new manager role from it: entitlement uses managers-cohort membership (R7).
- **002**: no change to `ltct_org`'s visibility (decision 2, option a). An organisation that asks not to be named publicly gets a neutral key (change 23).
- **003**: merges first. 016 adds to its `db/events.php`, `db/hooks.php`, `db/mobile.php` and the Mentoring page. `MENTOR_ALLOW` gains `local/ltuse:viewidentity`, a reviewed change.
- **004**: the `progress` report's scope moves to the member-cohort condition `cohort:idnumber = ltct:org:<key>`, and its Organisation column is dropped (decision 2, option a). It had no `ltct_org` filter; the `programme` report keeps its `ltct_org` filter, since the field stays visible.
- **011 events**: `mod_scheduler` slots are re-saved when a level changes (R15). 011's `seeotherstudentsbooking` override stays.
- **012**: workshop anonymity between learners is unchanged.
- **013**: unchanged. The certificate keeps `studentname`, which prints the protected display; a real-name certificate is issued by the site team on request (R10).
- **014**: global search is not touched by this spec; turning it on is where protected people's indexed names are handled (decision 9 rejected).
- **015**: the reconcile task runs on the monitored cron, and a failed run is an alert to act on. The database restore carries protection data.
- **008** (agreed with the 008 session 2026-10-04; changed by the scope review, Doug, 2026-10-05):
  - Intake grants protection by calling `service::set_protection()` for a row that asks for it, after creating the account and before any enrolment. A raise now needs `requested` and `emailchecked` (FR-008, FR-016), so 008's intake must pass both, and make the non-identifying-email check at account creation. It holds a row back ("waits") with `service::level_available()` and `is_settled()`. 008's organisation-minimum inputs and its production gate are dropped on 008's side.
  - Usernames: 008 gives everyone their lowercased email as username, and `ltc-` with 8 base32 characters only where needed; 016's `neutral_username()` uses the same format, and once both are on main one generator calls the other (T049).
  - `authloginviaemail`, `protectusernames`, `auth`, `registerauth` and `authpreventaccountcreation` are declared in 008's `admin.yaml`, not here.
  - `local_ltuse/coursementorsync` stays 0 in 008's `admin.yaml` until 016, with the narrowed path 4 (change 22), is merged; 008's `ltct:mentorgroup:<mentor id>` groups are what path 4 reads.
- **006**: a mentor's pathway view reuses the Mentoring page's learner rows, which carry `protected` and, for the entitled only, `realname`. It never reads `ltct_org` for scope (agreed with the 006 session, 2026-10-04).
- **Course mentors (2026-10-04, narrowed 2026-10-05)**: FR-006 entitles the course mentor who assesses the learner in a course they take. Designed as R7 path 4: `teacher` gains `local/ltuse:viewidentity`, and the viewer is entitled while the learner is actively enrolled in a published or pilot course (idnumber `ltct:<slug>`, never `ltct:officehours`) where the viewer holds that capability **and the learner is in the viewer's own group `ltct:mentorgroup:<viewer id>` there**. The office-hours course is excluded because it enrols every mentor as `teacher` and every mentee as `student` (spec 011 R16). 008's course-mentor sync enrols course mentors as `teacher` in the `ltct:` course and puts their learners in their group, which is what grants the entitlement, and removes both when the pairing ends.
- **002 Areas amendment (2026-10-03)**: a SIL or SIL partner learner's own-organisation managers are their Area's ALTCs. No new code: entitlement already follows managers-cohort membership of the learner's organisation entry (R7).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Our plugin rewrites user records (names, fields, picture) | No 5.2 hook overrides a display name, and every view renders from the record (R1) | Filtering views would mean editing core or the theme (XI), and would still leak initials and alt text. A site-wide alternative name format is wrongly scoped. |
| Two new `local_ltuse` tables holding personal data | Real identity must be readable by mentors and organisation managers without `viewalldetails` (R5) | PRIVATE profile fields need `viewalldetails`, which 003 and 002 refuse and which exposes more. |
| ~~Site-wide setting changes that affect unprotected users (email out of teacher views, names locked, teacher logs and backups off, `ltct_org` private)~~ | — | **Removed by the scope review** (Doug, 2026-10-05): only settings that cost nobody anything remain, and the course-leader backup prohibit, which no one uses. |
| The XI exceptions (hook mutation, scheduler internals) | The only routes to the behaviour the spec requires (R2, R15) | Listed in the README, each with a guard test or re-run check. |
