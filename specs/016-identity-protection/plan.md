# Implementation Plan: Identity protection for at-risk users

**Branch**: `016-identity-protection`, requires `003-mentor-role` and the 011 open-courses change | **Date**: 2026-10-02 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/016-identity-protection/spec.md`

## Summary

**Names: change the account itself, not the page.** Moodle 5.2 has no hook into `fullname()`. Every view, on the web and in the app, renders names live from the user record, and so do emails, badges and search (R1). So for a protected user, `local_ltuse` writes the protected display into the account's own `firstname` and `lastname`. It also blanks the alternate names and the other identifying fields, including `ltct_role` (R6). Every view then protects the person at once.

**Real identity** is kept in our own table, `local_ltuse_protection`, which has a privacy provider. One PRIVATE profile field, `ltct_certname`, holds every learner's real name for the certificate (R5, R10). The learner's own words (their profile description, interests and posts) are never rewritten; the learner is told to review them.

**Supporters see the real identity through one entitlement function.** It covers the site team, the assigned mentor (a user-context capability on the 003 role), and managers of the learner's own organisation, recognised by managers-cohort membership unless the organisation withholds it (R7). The real identity and a **Protected** marker appear only on our own surfaces:
- the profile node;
- the Mentoring page, on the web and in the app;
- a "People I support" page, with a CSV export;
- the granting page.

A non-entitled viewer never sees the marker.

**Nobody can quietly undo it** (R2):
- A `before_user_updated` hook callback re-applies the protected state. A keyed bypass set, a per-user lock and a transaction keep the service's own writes safe.
- Observers and an hourly reconcile task cover the writers the hook misses, and cohort changes that fire no event.
- Name and email fields are locked for manual accounts (R3).

**What leaves the site is closed with declared settings and role overrides** (R8, R9, R14):
- Email is removed from course leaders' views for everyone.
- Gravatar, self-registration and the `core_user` search area are off.
- Course leaders lose backup download and course logs.
- Certificate emails to teachers are off.

**Organisations**:
- A minimum level (`email` or `firstname`) is **Moodle data**, set by the site team. It is never declared in the public repo, because that would publish which partners are at risk (R12).
- `ltct_org` becomes private for everyone, once spec 004's report scope has moved to a cohort condition. Until then, `firstname` and `pseudonym` are refused (R11).

**Protection is meant to be set before a learner's first activity.** Any later raise or lowering for someone with activity needs an acknowledgement, because a rename links the two identities. Leaving a protected organisation never lowers anyone automatically (R13).

## Technical Context

**Language/Version**: Python 3.12 (`scripts/`; CI uses 3.12), PHP 8.3 for `local_ltuse` on Moodle 5.2.3+.

**Primary Dependencies**:
- Moodle core:
  - `core_user` (`user_update_user`, `core_user::update_picture`, the `before_user_updated` hook);
  - `core_privacy` (including `core_userlist_provider`);
  - `core_message`, `core_cache`, `core\lock`.
- `mod_customcert` 5.2.9 (pinned by 013), for its `userfield` element.
- Our own `local_ltuse`, built on 003's `db/events.php`, `db/hooks.php` and `db/mobile.php`.
- PyYAML.

**Storage**: Moodle's database, in three new `local_ltuse` tables (data-model.md). The repo holds only declarations.

**Testing**:
- `pytest` for the new validation in `site_config.py`, on synthetic inputs.
- A PHP harness, `tests/protection_harness.php`, for the pure pieces: effective level, withhold sets, pseudonym rules, member-cohort matching.
- PHPUnit with synthetic users in `moodle/local_ltuse/tests/`. It includes:
  - the hook-mutation test (R2);
  - an exception thrown while a user is in the bypass set;
  - two concurrent applications for one user.
- Instance checks V1–V17 in [quickstart.md](quickstart.md), with test accounts only.

**Target Platform**: the self-hosted Moodle 5.2.3+ build host and, later, production (015), plus the Moodle Android app.

**Project Type**: the training system: declarative site configuration and its Moodle plugin.

**Performance Goals**:
- A grant takes under 2 minutes.
- A change shows on the web within 5 minutes, including a new member of a protected organisation.
- Writers the hook misses are repaired within one reconcile run (60 minutes).
- The app catches up on its own refresh (decision 6).

**Constraints**:
- No learner data, pseudonym, real identity or at-risk organisation in git, fixtures, logs, PRs or quickstart evidence (III, FR-013).
- No edit to core, the theme, `mod_customcert` or `mod_scheduler` (XI).
- Participation is never limited (FR-003).

**Scale/Scope**: a small number of protected users, a handful of organisations, and four levels including `none`.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. Levels, withheld fields, settings, roles and the certificate are declared in `moodle/site/`. Who is protected, and organisation minimums, live only in Moodle and are never read back. |
| II. Portability | PASS. `apply` rebuilds every declared piece on a fresh instance (V1, SC-006). Organisation minimums and protection data come back with the database restore, the same as all learner data. Protection data is in the privacy export. |
| III. Public repo (NON-NEGOTIABLE) | PASS. Declarations only. Organisation minimums are kept out of the repo, because they would publish which partners are at risk (R12). An organisation that may need protection gets a neutral key and name from its first commit. `drift` reports counts. Quickstart evidence and SC-007 findings stay in a private store, and the PR records counts and issues only. |
| IV. Disclosure boundary (NON-NEGOTIABLE) | PASS. Not touched. |
| V. CBC fidelity | PASS. The certificate keeps 013's "training completed" wording; only its name element changes (R10). |
| VI. No LMS orientation | PASS with a gate. A protected learner does nothing extra. Granting is one page (R12). SC-007 needs real users. |
| VII. One shape | PASS. One set of levels and one entitlement rule for every organisation. An organisation minimum is a value in Moodle, not a different role set or setup. |
| VIII. Language data | PASS. Not applicable. Pseudonyms are free text in any script, compared after NFC normalisation. |
| IX. Flat cost, field-ready | PASS. No paid service. The protection holds in the app, because the app shows server-side names (R1). Its cache lag is a listed gap. |
| X. Traceable and verified | PASS with gates. Cites row #26, which the delivering PR updates. Every mechanism has a V check that blocks its story. **Recurring work added** (below) is named, and it is not covered until 015's operator is decided. |
| XI. Survives an upgrade | **PASS with listed risks.** Only supported extension points are used: a hook, events, tasks, web services, `myprofile_navigation`, `db/mobile.php`, privacy and message providers. Core writes go through core APIs. Three exceptions are listed in the plugin README (contracts/protection-service.md): mutating the hook's object (guarded by a PHPUnit test, and V7 re-run on every core upgrade); `mod_scheduler`'s `slot` class (V17 re-run on every re-pin); and indexed reads of `logstore_standard_log` and `messages`. `local_ltuse` keeps `requires = 2026042000` and `supported = [502, 502]`, and the version bump is verified by V1. |
| Platform: core first | PASS. Core settings and capabilities do most of it. Our plugin does only what no core setting can do per user (R1), and no free plugin offers it. |

**Recurring work this spec adds** (Principle X), carried by the site team until 015 names an operator:
- every learner's name and email changes (decision 3);
- granting, correcting and reviewing protection;
- choosing neutral usernames and organisation names;
- watching the reconcile repair count in the task log.

Re-checked after Phase 1 design and the review pass: no change beyond the XI risks and the X burden recorded above.

## Project Structure

### Documentation (this feature)

```text
specs/016-identity-protection/
├── plan.md              # This file
├── research.md          # R1–R15 and the known gaps
├── data-model.md        # Declared items, three tables, the account while protected, state transitions
├── quickstart.md        # Instance checks V1–V17
├── contracts/
│   ├── declaration.md         # protection.yaml, identity settings, profile fields, roles, certificate, report scope
│   └── protection-service.md  # entitlement, the service, two web services, hook, observers, tasks, surfaces, privacy
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
moodle/
├── site/
│   ├── protection.yaml                 # new: levels, withheld fields, neutral surname (#26)
│   ├── settings/identity.yaml          # new: identity, email, auth, search area, picture settings
│   ├── profile-fields.yaml             # changed: + ltct_certname; ltct_org private (decision 2)
│   ├── roles.yaml                      # changed: teacher-role prohibits; viewidentity, manageprotection
│   ├── reports.yaml                    # changed (decision 2): cohort scope condition, no ltct_org column
│   ├── certificate/template.yaml       # changed: userfield ltct_certname
│   └── README.md                       # changed: protecting a person or an organisation; neutral keys; protect early
├── local_ltuse/
│   ├── classes/protection/entitlement.php      # new: can_view_identity, can_manage_protection
│   ├── classes/protection/service.php          # new: the one place a level is applied
│   ├── classes/protection/levels.php           # new: pure rules (harness-tested)
│   ├── classes/protection/hook_callbacks.php   # new
│   ├── classes/protection/observer.php         # new
│   ├── classes/task/apply_protection.php       # new: adhoc
│   ├── classes/task/reconcile_protection.php   # new: scheduled
│   ├── classes/external/set_protection.php     # new
│   ├── classes/external/set_org_protection.php # new
│   ├── classes/privacy/provider.php            # changed: three tables, userlist, message provider
│   ├── classes/siteconfig/protection.php       # new: store config; drift counts
│   ├── classes/siteconfig/{inspector,applier,drift,profilefields}.php  # changed
│   ├── classes/mentoring.php                   # changed (003's): marker and real name for the entitled
│   ├── protection.php, orgprotection.php, protected.php   # new: granting, organisation, People I support
│   ├── lib.php                                 # changed: profile node
│   ├── db/access.php                           # changed: three capabilities
│   ├── db/hooks.php, db/events.php, db/mobile.php          # changed (003's files)
│   ├── db/tasks.php, db/messages.php           # new
│   ├── db/install.xml, db/upgrade.php          # changed: three tables; back-fill ltct_certname
│   ├── db/services.php                         # changed: two web services
│   ├── tests/protection_test.php               # new
│   ├── version.php                             # bumped
│   └── README.md                               # changed: the three XI exceptions
└── REQUIREMENTS.md                             # row 26 updated on delivery
scripts/site_config.py                  # changed: protection.yaml, text datatype, role, certificate and report rules
tests/test_site_config.py               # changed
tests/test_protection_declaration.py    # new
tests/protection_harness.php            # new
.github/workflows/site-config.yml       # changed: runs protection_harness.php
```

**Structure Decision**: This follows 004 and 013. Declarations go through `site_config.py` and a new `siteconfig` class. Everything that touches a user goes through `entitlement` and `service`, which every page, web service, hook, observer and task calls. The pure rules have no Moodle calls and are harness-tested in CI.

## Decisions on the plan's limits

These are for the maintainer (Doug). `/speckit-tasks` may generate tasks as drafted, but a task that depends on a pending decision is not closed until it is confirmed.

| # | Limit or choice | Status |
|---|---|---|
| 1 | **Email leaves course leaders' views for everyone** (R8). Teachers no longer see any learner's address on participants, downloads, grade exports or reports. Messaging stays. **If rejected**, US1's email protection needs the relay-address design (R8), which needs a mail relay someone operates. | **Pending the maintainer.** |
| 2 | **`ltct_org` becomes private for everyone**, after spec 004's report scope moves to a cohort condition (R11). Classmates then see no one's organisation. This is a **precondition**: until it lands, only the `email` level is available. Amends 002 FR-008. | **Pending the maintainer.** Recommended. |
| 3 | **Names and email are locked for every manual account** (R3). Learners no longer edit their own. The site team changes them in core's admin editor; for protected users, entitled people correct them on the granting page. This is recurring site-team work. **If rejected**, R4's placeholder surname becomes mandatory, and the hook alone guards names. | **Pending the maintainer.** |
| 4 | **Late changes and history** (R13). Raising or lowering a level for someone with activity needs an acknowledgement. Leaving a protected organisation keeps the level until someone lowers it. Amends the spec's "History" and "moves between organisations" edge cases. | Proposed: accept. |
| 5 | **Course leaders lose course backups and course logs** (R14). | Proposed: accept. |
| 6 | **Known gaps** (research): the badge email hash, app cache lag, copies already sent, file metadata, backups carrying `ltct_certname`, core exports without a marker, same-first-name lookalikes. Amends SC-005 for the app. | Proposed: accept, listed in the delivering PR (FR-015). |
| 7 | **Where supporters see the real identity** (R7): the profile node (web), the Mentoring page (web and app), "People I support" with its CSV, and the granting page. Everywhere else they see the protected display. Amends US2-1, US2-3 and FR-007. | Proposed: accept. |
| 8 | **Organisation minimums are Moodle data, not repo declarations** (R12). They are set by the site team, and the maximum is `firstname`. An organisation that may need protection gets a neutral key and name from its first commit. Amends FR-014. | Proposed: accept. |
| 9 | **The global search `core_user` area is disabled**, but global search itself is not (R9), so 014 can still use it. | Proposed: accept. |

## Cross-spec effects

- **011 open-courses change**: lands first. Its removal of organisation groups is what keeps a protected person's organisation off forum posts and the participants page. 016 needs no new manager role from it: entitlement uses managers-cohort membership (R7).
- **002**: `ltct_org` becomes private (decision 2, which amends FR-008). Organisation rules keep `bulkprocessing = 0`. New organisations that may need protection get neutral keys.
- **003**: merges first. 016 adds to its `db/events.php`, `db/hooks.php`, `db/mobile.php` and the Mentoring page. `MENTOR_ALLOW` gains `local/ltuse:viewidentity`, a reviewed change.
- **004**: the `progress` report's scope moves to a cohort condition, and its Organisation column and filter are dropped (decision 2).
- **011 events**: `mod_scheduler` slots are re-saved when a level changes (R15). 011's `seeotherstudentsbooking` override stays.
- **012**: workshop anonymity between learners is unchanged.
- **013**: the certificate's name element and its validator change. `ltct_certname` is back-filled for every user (R10).
- **014**: global search stays available, because only the `core_user` area is off (decision 9).
- **015**: the reconcile task runs on the monitored cron. The database restore carries organisation minimums and protection data.
- **008**: bulk protection can use `local_ltuse_set_protection`.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Our plugin rewrites user records (names, fields, picture) | No 5.2 hook overrides a display name, and every view renders from the record (R1) | Filtering views would mean editing core or the theme (XI), and would still leak initials and alt text. A site-wide alternative name format is wrongly scoped. |
| Three new `local_ltuse` tables, two of them holding personal data | Real identity must be readable by mentors and organisation managers without `viewalldetails`. Organisation minimums must not be in the public repo (R5, R12). | PRIVATE profile fields need `viewalldetails`, which 003 and 002 refuse and which exposes more. Declaring minimums publishes who is at risk. |
| Site-wide setting changes that affect unprotected users (email out of teacher views, names locked, teacher logs and backups off, `ltct_org` private) | Core has no per-user exception for any of them (R3, R8, R11, R14) | Per-user filtering is impossible without core edits. Leaving them open exposes protected users to every course leader. |
| The XI exceptions (hook mutation, scheduler internals, activity reads) | The only routes to the behaviour the spec requires (R2, R13, R15) | Listed in the README, each with a guard test or re-run check. |
