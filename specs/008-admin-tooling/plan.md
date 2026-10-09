# Implementation Plan: Simple administration tooling

**Branch**: `008-admin-tooling` (off main `7fd9999`) | **Date**: 2026-10-04 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/008-admin-tooling/spec.md`, re-planned on spec 002's amendments of 2026-10-02 and 2026-10-03 (spec Clarifications 2026-10-04).

## Summary

**008 is the site team's tooling.** Managers act for their own people on spec 002's organisation page; 008 builds nothing manager-facing (spec Clarifications).

**The tool.** A Python command, `scripts/ltct_admin.py`, runs on a site-team member's machine. It calls a second `local_ltuse` service, **LTC administration** (`ltuse_admin`), with the member's own token. A guided `/manage-learners` command walks a non-git operator through it.
- Every changing command previews first, server-side and read-only, and prints a confirmation code. Applying needs that code, so what is applied is what was previewed (R15).
- Every write is idempotent and goes one row per call, so an interrupted run on a poor link is finished by running it again.

**Accounts** (R2, R3, R5):
- An account is created with its email as its username (a neutral generated one when name-protected or when the email cannot be a username) and Moodle's own password email, and matched by email on every later run.
- Its organisation is set through the `ltct_org` field; dynamic cohorts then place it in the organisation's cohort, which enrols it.
- Any protection it asks for is set by spec 016's service, and confirmed settled, before the organisation field is set. Until 016 allows the level, the row waits.
- On an existing account, 008 writes only `suspended` and `ltct_org`.

**Enrolment** (R7, R11):
- A cohort is enrolled by cohort sync, under 002's organisation rules: shared courses are open to every organisation's cohort, an organisation-only course only to its own, and pilots never.
- Removing a cohort disables the instance and never deletes it, so history is kept.
- Pathway enrolment rides spec 006's assignment table and its "courses changed" event.

**What 002, 003, 012 and 016 handed to 008** (spec FR-015–FR-019):
- the counted dry run and the `sil` → Area move (R8);
- managers and mentors cohort membership (R9);
- bulk mentor assignment and ending all of one mentor's relationships (R9);
- automatic course-mentor enrolment, which matters most (R10). A learner's course mentors are enrolled as Moodle's non-editing teacher in each course they take, defaulting to their default mentor, and removed by event as soon as the reason ends. That enrolment, together with the shared mentor group 008 makes, is what entitles the mentor to a protected learner's real identity (016 FR-006, path 4); 008 removes both by event.

**Learner data stays out of the repo** (R14):
- Files are refused inside any git working tree, not just this one.
- Nothing is written unless asked, and no log file is ever written.
- People are masked by default, so a guided session sends the assistant row numbers, not names.

## Technical Context

**Language/Version**: Python 3.12 (`scripts/`, as CI); PHP 8.3 for `local_ltuse` on Moodle 5.2.3+.

**Primary Dependencies**:
- Moodle core `user`, `cohort`, `enrol_cohort`, `enrol_self`, role and group APIs, all confirmed on `MOODLE_502_STABLE` ([research.md](research.md));
- `tool_dynamic_cohorts` (pinned by 002);
- spec 002's `organisation\access` (built) and `organisation\actions` (shared seam, R6);
- spec 016's `protection\service` and `entitlement`;
- spec 006's `pathway\catalogue`, `pathway\assignments` and `pathway_courses_changed`;
- PyYAML. No new third-party plugin.

**Storage**:
- Moodle's database: accounts, memberships, enrolments and role assignments, plus one new table, `local_ltuse_course_mentor` ([data-model.md](data-model.md)).
- Operator files live on the operator's machine, outside every git tree.

**Testing**:
- `pytest`: `tests/test_ltct_admin.py`, offline, with a `FakeClient`.
- PHP harness: `tests/admin_harness.php`, covering the four pure rule classes (R4), in CI.
- PHPUnit on synthetic data for the services and the sync, in `moodle/local_ltuse/tests/admin_test.php`, in plugin CI (`.github/workflows/local-ltuse-plugin-ci.yml`, a throwaway Moodle 5.2; 004's T003 left this open). Green on main `a0dccef` on 2026-10-05 (run 37350183130).
- Instance checks V1–V19 in [quickstart.md](quickstart.md).

**Target Platform**:
- The operator's Windows, macOS or Linux machine (Python 3.12).
- The self-hosted Moodle 5.2.3+ build host, then production (015).

**Project Type**: The training system: a CLI, a Moodle plugin's web service, an observer and a task, and declared site configuration.

**Performance Goals**:
- A 30-learner intake, placed and enrolled, in under 15 minutes, preview included (SC-001). Per-row calls take about a second each.
- Course-mentor removal in the same request as its cause, with the hourly reconcile as a backstop (R10).

**Constraints**:
- No learner data in the repo, its history, fixtures or logs (FR-004, FR-013; constitution III).
- No credential in any file (FR-009).
- Nothing applied without a matching preview (FR-002).
- No delete of an account, an enrolment instance or history (FR-006).
- No CBC level touched (FR-012).
- No field of an existing account written except `suspended` and `ltct_org` (FR-019).
- No hard-coded host.
- Upgrade-safe extension points only (constitution XI).

**Scale/Scope**:
- Tens of organisation entries (four declared today, plus ten Area entries decided).
- Hundreds of learners; intakes of up to about 100 rows.
- About ten delivered courses.

## Constitution Check

*GATE: passed before Phase 0; re-checked after Phase 1 design (no change).*

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. Nothing flows from Moodle to the repo. Summaries go to the screen, or to `--out` outside every git tree (R14). Organisation keys come from the repo's declaration (`site_config.validate`). Memberships, enrolments and the course-mentor table live only in Moodle. Stage detection is untouched; moving Pilots → Published is left to the publisher (R17). |
| II. Portability / config as code | PASS. The service, capability, role, `allowaccountssameemail`, the general account rules (`authloginviaemail`, `protectusernames`, `registerauth`, `authpreventaccountcreation`, and the login methods as `auth_webservice` and `auth_email` plugin entries in `site.yaml`) and the reconcile task are all declared in the plugin or `moodle/site/` and applied by script (R12, R13). Identity is by idnumber (`ltct:org:<key>`, `ltct:<slug>`) and email, never a database id typed by a person. The new table is in the plugin's install XML and in the database backup. Learner data stays exportable: the privacy provider covers the new table. |
| III. Public repo, private people (NON-NEGOTIABLE) | PASS. Paths inside any git working tree are refused, and an operator is told if git already has a file (R14). Nothing is written by default and there are no log files. People are masked by default, so guided runs send the assistant row numbers only. The token comes from `MOODLE_ADMIN_TOKEN` only. Tests build `example.org` and `fixture-*` data in memory, with no CSV fixtures. Quickstart evidence is counts and outcomes only. |
| IV. Disclosure boundary (NON-NEGOTIABLE) | PASS, not touched. No course content, quiz or page HTML. |
| V. CBC fidelity | PASS. Accounts, cohorts, enrolments and mentors only. No level is read, recorded or changed (FR-012). Course mentors grade course work, which is training evidence (002 Clarifications). |
| VI. No git, no LMS orientation | PASS, with a gate. `/manage-learners` and plain-sentence output mean no git or Moodle admin screens for the site team (R16). Managers use 002's page. Done needs 2–3 real managers; what they test is conditional on decision 6. |
| VII. One shape | PASS. One service, one role and one set of rules for every organisation entry, the ten Areas included. The organisation-only course is 002's uniform variant, enforced by the same rule table (R7). No per-partner path. |
| VIII. Language data | PASS, not touched. Names are copied as given and never judged or normalised. |
| IX. Flat cost, field-ready | PASS. Core plus our plugin; no paid plugin or service. Writes are resumable on a bad link (R15). |
| X. Traceable and verified | PASS, with gates. Cites row #14; the delivering PR updates it. Each Moodle API is confirmed in source (research). Behaviours the source only implies are instance checks V1–V19, each blocking the story that relies on it. Not done until 2–3 real managers (FR-014), whose findings name no Area or organisation (quickstart). 002 R13's gate, narrowed on 2026-10-05 to "a person who asked for protection waits until 016 can set their level", is enforced by intake itself: that person's row `waits` (R5, R16). The tool prints no reminder, and no organisation is marked (Doug, 2026-10-05 (scope review)). Cites rows #14, #11 (FR-017, FR-018) and #8, #15 (FR-015), each updated by the PR that delivers it. **Recurring burden**: account creation, moves and mentor records stay with the site team, now as minutes per intake rather than per learner. The hourly reconcile rides the cron 015 monitors. Server operation is not claimed as covered while the operator is undecided (015). |
| XI. Survives an upgrade | PASS. Our own code uses supported extension points only: web services, events, a scheduled task and the plugin's own table. Every write uses a core API (`user_create_user`, `profile_save_data`, `cohort_add_member`, `enrol_plugin::add_instance`/`update_status`/`enrol_user`, `role_assign`, `groups_*`), and the new `setup_admin_token.php` uses `webservice::add_ws_authorised_user()` and `delete_user_ws_token()` rather than the direct `$DB` writes in `setup_publishing.php` (R12). Reads use indexed core columns; any raw read of another component's schema (e.g. `tool_dynamic_cohorts`'s `component` value) is listed in the plugin README. `requires` and `supported` stay at 5.2. |
| Platform: core first | PASS, with one justified exception (Complexity Tracking): our own functions instead of core Upload users, because core cannot order protection before enrolment, give name-protected people neutral usernames, match by email when creating, or enrol without the manual method (R1). Core Upload users stays documented as the fallback. |

## Project Structure

### Documentation (this feature)

```text
specs/008-admin-tooling/
├── spec.md              # amended 2026-10-04 (Clarifications, FR-015–FR-019)
├── plan.md              # This file
├── research.md          # R1–R18
├── data-model.md        # operator files, previews, Moodle writes, local_ltuse_course_mentor
├── quickstart.md        # instance checks V1–V19 and the done gate
├── contracts/
│   ├── cli.md           # ltct_admin.py commands, environment, output
│   └── admin-service.md # ltuse_admin functions, the course-mentor sync, version
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
scripts/
├── ltct_admin.py                       # new: the site team's command (contracts/cli.md)
├── admin_files.py                      # new: file kinds, offline validation, path guard, masking, confirmation code (pure)
├── moodle_client.py                    # changed: token= used with MOODLE_ADMIN_TOKEN; bounded retries on connection errors/5xx (R15)
└── site_config.py                      # changed: PROTECTION_MANAGE_ROLES gains ltctadmin (R5); settings/admin.yaml validated
moodle/local_ltuse/
├── classes/admin/intake_rules.php          # new, pure (R4)
├── classes/admin/enrolment_rules.php       # new, pure (R7)
├── classes/admin/move_rules.php            # new, pure (R8)
├── classes/admin/course_mentor_rules.php   # new, pure (R10)
├── classes/admin/intake_service.php        # new: preview/apply one row (R2, R3, R5)
├── classes/admin/cohort_enrolment.php      # new: ensure/remove cohort sync (R7); pathway via 006 (R11)
├── classes/admin/move_service.php          # new (R8)
├── classes/admin/membership_service.php    # new: managers/mentors cohorts, mentor assignment (R9)
├── classes/admin/course_mentor_sync.php    # new (R10)
├── classes/admin/masking.php               # new: masked email, server-side (R14)
├── classes/organisation/actions.php        # shared with 002 (T071): built here only if 002 has not (R6)
├── classes/external/admin_*.php            # new: one per function in contracts/admin-service.md
├── classes/task/course_mentor_reconcile.php # new, hourly
├── classes/privacy/provider.php            # changed: local_ltuse_course_mentor
├── cli/setup_admin_token.php               # new: per-person ltuse_admin token to a file (R12)
├── db/services.php                         # changed: "// Spec 008" block, service ltuse_admin
├── db/access.php                           # changed: local/ltuse:administer
├── db/events.php                           # changed: course-mentor and pathway observers
├── db/tasks.php                            # changed: course_mentor_reconcile
├── db/install.xml, db/upgrade.php          # changed: local_ltuse_course_mentor
├── lang/en/local_ltuse.php                 # changed: "// Spec 008" section, appended
├── tests/admin_test.php                    # new: PHPUnit, synthetic data
├── version.php                             # 2026100800, then 2026100801 (course-mentor table); 016 re-bumped to 2026100900
└── README.md                               # changed: administration section; "does no enrolment" corrected; XI reads
moodle/site/
├── roles.yaml                              # changed: + ltctadmin
├── settings/admin.yaml                     # new: allowaccountssameemail 0; the general account rules (email login, protectusernames, registerauth, authpreventaccountcreation); local_ltuse/coursementorsync 1 since 016 narrowed it (decision 11; 0 until 2026-10-05)
└── README.md                               # changed: "The site team's four steps" become the ltct_admin.py recipes; a row that waits joins once identity protection is ready for that person
tests/
├── test_ltct_admin.py                      # new
└── admin_harness.php                       # new
.claude/commands/manage-learners.md         # new (R16)
.github/workflows/site-config.yml           # changed: runs admin_harness.php
.github/workflows/publisher-tests.yml       # changed: paths + test_ltct_admin.py
moodle/REQUIREMENTS.md                      # row 14 updated on delivery
CLAUDE.md                                   # changed: ltct_admin.py in "Maintainer scripts"; one line under "Delivery: Moodle"
```

**Structure Decision**: Like 002 and 013. Pure rule classes, harness-tested in CI, make every decision; thin services gather state and write through core APIs; external functions are thinner still. The Python side only validates files offline, guards paths, masks, and drives preview → confirm → apply. So the same rules could later back a Moodle page without change (R1).

### Order of work

1. **Governing text** (this branch's first commit): spec amendment, plan, research, data model, contracts, quickstart.
2. **Foundation**: service, capability, role, setting, `setup_admin_token.php`, `ltct_admin.py check`, path guard, masking, confirmation code (V1, V16).
3. **US1 + US2 (P1, the MVP)**: intake, and cohort enrolment into a course (V2–V7; V9's disable-not-delete half). Updates row #14's status. Protection gating shipped with US1 and was inert until 016 was installed; it is live since 2026-10-05 (016 merged in #84; `local_ltuse` 2026100900 on ltuse.net).
4. **US3 (P2)**: suspend and reactivate, move with dry run, `enrol mirror`, summary, managers and mentors cohorts (V8, V10, V14, V15, V18). Updates rows #8 and #15 for the move.
5. **Mentors (P2)**: bulk assignment, end-all, the course-mentor table and sync, reconcile task (V11–V13, then V9's course-mentor half). Updates row #11. Lands no later than 016, because 016's course-mentor entitlement needs it.
6. **Pathways**, once 006 has landed (V17).
7. **Docs, CLAUDE.md**, and V19. The done gate (decision 6) stays open after merge.

## Decisions on the plan's limits

These are for the maintainer (Doug). `/speckit-tasks` may generate tasks as drafted, but a task that depends on a pending decision is not closed until it is confirmed.

**Doug approved all eleven on 2026-10-04** (PR #93), with one caveat on decision 1, recorded in its row: identity protection, and a pseudonym above all, is for a person who asks for it when they are added. It is never applied to everyone.

| # | Limit or choice | Status |
|---|---|---|
| 1 | **Personal data and the AI assistant.** This is about what the *assistant* sees, not about how learners are named: learners keep their real names in Moodle. The tool masks people in its own output by default (row numbers and `a***@example.org`). The guided command never reads an intake file and never asks for names. A human sees names only by running `--show-people` in their own terminal. Without this, every guided intake sends learners' names and emails into an assistant's context, while INTENT's data-protection position is undecided (R14). | **Approved** (Doug, 2026-10-04). Caveat: this decision makes no one use a pseudonym. Identity protection (016) is per person and opt-in. It applies only to someone concerned about their identity being exposed who says so when they are added; that is what the intake's `protection` column records, and it defaults to `none` (data-model §1). There are no organisation minimums: 016's scope review cut them (Doug, 2026-10-05 (scope review)). |
| 2 | **Course-mentor precedence.** A one-course mentor replaces the default mentor in that course; cohort mentors replace it for learners enrolled through that cohort; otherwise the default mentor (R10). The alternative is to add them alongside the default mentor. | **Approved** (Doug, 2026-10-04): replace, not add alongside. |
| 3 | **Mentor groups.** One group per course mentor per course, named "Mentor group <n>", holding them and the learners they assess there; assessed activities use separate groups (012). Never named after a person, organisation or Area (002 FR-019). | **Approved** (Doug, 2026-10-04); still to be adopted by 012's re-plan. 012 is parked for re-plan (Doug, 2026-10-05), so the hand-off waits for that re-plan. |
| 4 | **Pilots → Published** is not 008's. The publisher should place a course in `ltct:published` on its stage-8 delivery publish, through the existing `local_ltuse_place_course` (R17). | **Approved** (Doug, 2026-10-04). |
| 5 | **Removing a cohort disables its instance, never deletes it**, so learners keep grades and completion. A course with many disabled instances is tidied by hand only when nobody's history depends on it. | **Approved** (Doug, 2026-10-04). |
| 6 | **What the real-manager test means** (FR-014, SC-004). Managers never run 008's tool. So: 2–3 real ALTCs or organisation managers (a) fill in an intake list from the template that the site team applies with no correction, and (b) enrol, suspend and reactivate their own people on 002's page. SC-004's "complete an intake unaided" becomes "a list applied unchanged". Findings name testers as "tester 1/2/3" and no Area or organisation entry (constitution III). | **Approved** (Doug, 2026-10-04). |
| 7 | **Per-person tokens.** Each site-team member gets their own `ltuse_admin` token on their own account, so Moodle's logs show who did what; there is no shared admin account (R12). | **Approved** (Doug, 2026-10-04). |
| 8 | **ALTC teaching cohorts.** 008 enrols organisation cohorts, and managers cohorts into organisation-only courses, and refuses every other cohort until 002's re-plan decides how an ALTC makes a teaching group (002 FR-015, FR-019). The rule table gains one row when it does. Until then, FR-006's "add a learner to a further cohort" is covered only for managers cohorts and `ltct:mentors` (the `managers` command); the rest waits on the same 002 decision and is not claimed as delivered. | **Approved** (Doug, 2026-10-04); the extra row still waits on 002. |
| 9 | **Intake never reactivates or moves.** A suspended account, or one under another organisation, is flagged in the preview and left alone; `reactivate` and `move` are run deliberately. Pathway assignment by a manager on 006's page never enrols; making it enrol is one observer, if wanted. | **Approved** (Doug, 2026-10-04). |
| 10 | **Per-row courses in an intake** go through 002's Organisation enrolment, the same path managers use. It counts as delivery once 002 T080 changes 004's condition to "not manual". | **Approved** (Doug, 2026-10-04). |
| 11 | **How far a course mentor sees.** Auto-enrolled course mentors hold Teacher, and 016 FR-006 (as Doug amended it on 2026-10-04) entitles every course mentor of a course to every protected learner actively enrolled in it. Shared courses are open across organisations, so a default mentor auto-enrolled in a mentee's course would see the real identity of protected learners from other organisations who are not their mentees. The safer reading narrows 016's course-mentor path to "viewer and learner share a `ltct:mentorgroup:*` group in that course", using the groups 008 makes; that is one function in 016. 008's design works either way. | **Approved** (Doug, 2026-10-04): the narrower reading. A course mentor sees a protected learner's real identity only when they share a `ltct:mentorgroup:*` group in that course. That is a change to 016's course-mentor path, which belongs to 016 (PR #84). Until 016 makes it, R10 stays behind `local_ltuse/coursementorsync`, declared 0 in `settings/admin.yaml`, so course mentors are not auto-enrolled anywhere the repo's declaration applies. Instance checks turn it on in their own copy. 016's scope review keeps the narrower reading; the setting stays 0 until 016's narrowed course-mentor path is merged (Doug, 2026-10-05 (scope review)). **Met 2026-10-05**: 016 merged it (#84; `entitlement::is_course_mentor_of()` needs the learner in the viewer's `ltct:mentorgroup:<id>` group), and `settings/admin.yaml` declares `coursementorsync: 1` since 2026-10-05 (#97). **Accepted (Doug, 2026-10-05)**: a course mentor holds Moodle's non-editing Teacher role (`teacher`), whose Moodle 5.2 defaults (`moodle/grade:viewall`, `gradereport/grader:view`, `report/progress:view`, `report/completion:view`, `moodle/site:viewuseridentity`, `moodle/course:viewhiddenuserfields`; `public/lib/db/access.php` and the report plugins' `db/access.php`, `MOODLE_502_STABLE`) reach every learner in a course in group mode 0, not only the learners they assess; we trust people who are in the system. A protected learner's real identity still reaches a course mentor only through a shared mentor group (016 path 4). The sync stays on before instance checks V9 and V11–V13 (T067) have run (research R10). Spec 003's FR-005/SC-002 are about the Mentor role (user context) and are unaffected. |

## Cross-spec effects

- **Spec 002**:
  - `classes/organisation/actions.php` (T071) is a shared seam. Whichever spec builds first writes it to 002's contract, and the other adopts it (R6). *Done 2026-10-04: 002's T071 reached main first (#92); 008's merge of main kept it and added the `do_*()` cores (T012, merged with #93).*
  - **Required amendment to 002's actions contract** before T071 is built: each action splits into an unchecked `do_<action>()` (the write plus its per-action rules) and a manager wrapper that adds `may_manage_account()`. 002's contract has every method re-check `may_manage_account`, which is false for any site-team actor and for staff, mentors, managers and holding-entry learners (R6). *Done 2026-10-04: the split landed with T071 and T012.*
  - 002 R13's gate, as narrowed on 2026-10-05: a person who asked for protection waits until 016 can set their level and, now that 016 is installed, until their row's `email_checked` says yes (research R5 step 0b). 008's intake does that row by row (`waits`, R5); nobody else is held back, and the tool prints no reminder (R16; Doug, 2026-10-05 (scope review)).
  - T080: `coverage.php` counts the Organisation enrolment by its `ltct:orgenrol` marker (or Student role), never by `enrol = 'self'` alone, so the `ltct:coursementor` instance is never counted (R10).
  - 008 adds the organisation-only enrolment check 002 R11 left open (R7).
  - 008 replaces `moodle/site/README.md` "The site team's four steps", which 002's T082 was to rewrite. 008 does that rewrite; T082 keeps only the organisation-only recipe.
  - The Area entries (002 FR-014) are 002's to declare. 008 needs nothing beyond their keys being in `organisations.yaml`.
- **Spec 003**:
  - R8's "with the organisation's group" and FR-006's deferral are met by R10.
  - The site README's mentor section (line 217) points to `ltct_admin.py mentors` and the automatic course-mentor sync.
- **Spec 004**: no change. Its reports already filter `role:name = student`, so course mentors are never counted (R10, V11).
- **Spec 006** (agreed 2026-10-04, locked):
  - 006 owns the catalogue, the assignment table with `enrol int(1) default 0`, the manager page and the events.
  - 008 owns all enrolment, through `assign(…, true)` and `cohort_enrolment`.
  - 008 adds no table for pathways.
- **Spec 012**:
  - Its re-plan adopts decision 3's mentor groups for assessed activities. 012 is parked for re-plan (Doug, 2026-10-05), so this hand-off stays open until that re-plan.
  - Its plan.md:149 interim ("site team enrols the mentor as Course mentor with the organisation's group") is superseded by R10.
- **Spec 013**:
  - Stage 8 already suspends a pilot learner's manual enrolment. A learner later enrolled by intake keeps their pilot-era completion (004 decision 6, unchanged).
- **Spec 015**: the reconcile task rides the cron 015 monitors. The database backup must include `local_ltuse_course_mentor`.
- **Spec 016** (agreed 2026-10-04; amended by 016's scope review, Doug, 2026-10-05 (scope review)):
  - 008 calls `service::set_protection` (options `requested`, `emailchecked`, and `pseudonym` for that level), `is_settled`, `effective_level` and `apply` after `entitlement::can_manage_protection`, and `levels::email_reveals` and `levels::pseudonym_problems` in preview and again on apply (research R5, steps 0b and 0c).
  - Course mentors are enrolled as `teacher` in the `ltct:<slug>` course, never `ltct:officehours`. That enrolment, together with the shared mentor group 008 makes (`ltct:mentorgroup:<mentor id>`), is what entitles them (016 path 4, `entitlement::is_course_mentor_of()`); 008 removes both by event.
  - 008 never writes 016's never-send fields.
  - 008 also calls `level_available()` (agreed and exposed by 016 on 2026-10-04), and relies on nothing about observer order.
  - **Organisation minimums are gone** (Doug, 2026-10-05 (scope review)). 008 no longer calls `org_minimum()`: an intake row's target is its own `protection` level, and a move neither reads nor sets protection, so `move_rules` has no `flagged_protection` and `move_service` no post-move `is_settled()`/`apply()` step. The per-person check stays: a row asking for a level 016 cannot set yet `waits`, and, now that 016 is installed, also waits until its `email_checked` says yes (research R5 step 0b); an existing account below its row's level is `flagged_protection`.
  - `registerauth`, `authpreventaccountcreation` and `protectusernames` move from 016's `identity.yaml` to 008's `settings/admin.yaml`, as general account rules beside `authloginviaemail`; 016's `auth` setting becomes `auth_webservice` enabled and `auth_email` disabled as plugin entries in `site.yaml`, because `$CFG->auth` is not in the admin tree and the applier could not check it.
  - 008's PR adds `ltctadmin` to 016's `PROTECTION_MANAGE_ROLES` allowlist in `site_config.py`, with its reason (R5, agreed with 016).
  - Decision 11, about how far a course mentor sees, is settled by 016's scope review: its course-mentor path is narrowed to the viewer's own mentor group. `local_ltuse/coursementorsync` was 0 until it merged (#84), and is 1 since 2026-10-05.
- **Spec 017**: when an ALTC may create an account by approving a request, it should create it through `intake_service`, so that ordering, the username rule and protection stay in one place.
- **Publisher**: decision 4 (Pilots → Published). `setup_publishing.php`'s two direct `$DB` writes should move to the same core APIs as `setup_admin_token.php`; until they do, they go in the plugin README's XI list (R12).
- **`local_ltuse` README**: its line "No enrolment, grades or learner records" is already untrue after 002's R10, and becomes "enrolment through the administration service and the organisation page; never grades".

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Our own administration web service and functions, instead of core Upload users (FR-010 named it as the core example) | Protection set before enrolment (016), the email as username with neutral ones for name-protected people (016 R13), create-or-match by email in one run, enrolment that is not the manual method (004), server-side preview with a confirmation code, and refusal of dynamic and other organisations' cohorts (R1, R7) | Core Upload users matches by email only in update modes, saves profile data after `user_created`, enrols through the first manual instance (counted as a pilot), and needs the admin UI or SSH as the main admin. It stays the documented fallback. |
| A second external service and a new role | FR-009: a credential distinct from publishing, with only administration's permissions (R12) | Reusing `MOODLE_TOKEN` breaks FR-009; SSH to the server is a wider privilege than a scoped token. |
| A new table, `local_ltuse_course_mentor` | One-course and cohort mentors are not 003 role assignments and have no core home (R10) | Using group membership as the record mixes the record with its effect and cannot hold a cohort mentor before anyone is enrolled. |
| A dedicated `enrol_self` instance for course mentors | The mentor's enrolment must be removable on its own and must not be the manual method (R10) | Manual enrolment is taken by `enrol_manual_enrol_users`' "first instance" rule and counts as a pilot; reusing the Organisation enrolment would let 002's manager unenrol action remove a mentor. |
