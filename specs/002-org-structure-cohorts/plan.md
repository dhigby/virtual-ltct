# Implementation Plan: Partner organisations, cohorts and profiles

> **Amended 2026-10-03 in the spec** (Areas and Area Language Technology Coordinators, [Clarifications 2026-10-03](spec.md)). This file is not yet redone for it; that happens in the plan step, before any build. Where this file disagrees with the 2026-10-03 Clarifications, the spec wins.

**Branch**: `specs/moodle-requirements`; amended on `002-open-courses` | **Date**: 2026-10-01, amended 2026-10-02 | **Spec**: [spec.md](spec.md)

> **Amended 2026-10-02: open courses.** Organisations are no longer separated inside a course. The [amendment](#amendment-2026-10-02-open-courses) at the end of this plan supersedes the paragraphs and lines marked *(superseded 2026-10-02)*; everything else stands.

## Summary

Each partner organisation is declared once, in `moodle/site/organisations.yaml`, by a stable key and a display name. Spec 001's applier turns that list into the same shape for every organisation: a course category, an organisation cohort and a managers cohort. The cohorts are site-level and hidden, so the site team can enrol them into any course but no organisation manager can browse them. Three kinds of profile field are declared in `moodle/site/profile-fields.yaml`: organisation, role in the work, and one checkbox per area of expertise. Country is Moodle's own field, and `settings/groups.yaml` pins its visibility (FR-008). The free `tool_dynamic_cohorts` plugin fills each organisation cohort from the organisation field. No country cohorts are created (spec Clarifications). One `orgmanager` role lets a manager follow their own people inside a course *(superseded 2026-10-02: only inside an organisation-only course)*.

*(Superseded 2026-10-02.)* Separation comes from groups, not categories, because Moodle categories separate courses and not people (spec Clarifications). In each course, the site team enrols an organisation's cohort and its managers cohort into one group named for the organisation. Courses default to separate groups, and `orgmanager` holds no `moodle/site:accessallgroups`, so a manager sees only their own group. Organisation managers never create accounts, enrol anyone or edit the organisation field. The site team does those, later with spec 008's tooling.

*(Superseded 2026-10-02 in part: the hook is narrowed and gains an own-organisation allow, research R9.)* Groups cannot tell a former member from a current one, because core keeps a suspended enrolment and its group. Two pieces close that gap (Clarifications 2026-10-01). Cohort sync removes roles on leaving, so a removed manager loses `orgmanager`. A small `local_ltuse` callback on core's profile-view hook, `local_ltuse_control_view_profile()`, refuses an organisation manager the profile of anyone whose organisation field is not one they manage. It is the one piece of our own code in this spec. It goes through a supported extension point and only ever takes access away.

The applier gains four item types: course categories, cohorts, profile fields and cohort rules. They are applied with the same rules as spec 001: create only what is absent, never delete, and report anything undeclared as `extra`.

## Project Structure

```text
moodle/
├── site/
│   ├── organisations.yaml          # new: shared categories and organisations (#8, #15)
│   ├── profile-fields.yaml         # new: the profile field category and its fields (#18)
│   ├── site.yaml                   # changed: + tool_dynamic_cohorts (pinned), enrol_cohort enabled
│   ├── roles.yaml                  # changed: + orgmanager
│   ├── settings/
│   │   └── groups.yaml             # new: separate groups by default (superseded 2026-10-02: no groups); cohort sync suspends and removes roles (#8, #15)
│   └── README.md                   # changed: how to add an organisation; how to make someone a manager
├── local_ltuse/
│   ├── classes/siteconfig/
│   │   ├── categories.php          # new: check and apply course categories, with adoption (R6)
│   │   ├── cohorts.php             # new: check and apply cohorts (R1)
│   │   ├── profilefields.php       # new: check and apply the field category and fields (R5)
│   │   ├── cohortrules.php         # new: check and apply rules through tool_dynamic_cohorts (R4)
│   │   ├── inspector.php           # changed: hands the four new payload arrays to those classes
│   │   ├── applier.php             # changed: applies them, in the contract's order
│   │   ├── drift.php               # changed: reports undeclared ltct: categories, cohorts, rules and fields as extra
│   │   └── report.php              # changed: the new kinds adopted, ambiguous, wrong-context and wrong-datatype
│   ├── classes/profile_access.php  # new: the pure decision behind the profile hook (R9)
│   ├── lib.php                     # new: local_ltuse_control_view_profile(), gathers the decision's inputs
│   ├── version.php                 # bumped
│   └── README.md                   # changed: lists the tool_dynamic_cohorts API and the profile hook
└── REQUIREMENTS.md                 # rows 8, 15, 18 status updated on delivery
scripts/
├── site_config.py                  # changed: validate, expand and render the two new files
└── publish_moodle.py               # changed: sets separate groups on course update, not only on create (superseded 2026-10-02: sends no groups)
tests/
├── test_site_config.py             # changed: validation cases for the new files
└── profile_access_harness.php      # new: tests the decision without Moodle, like report_harness.php
.github/workflows/
└── site-config.yml                 # changed: also runs when competencies.yaml changes, since validate reads it
.gitignore                          # changed: a backstop pattern for learner CSV uploads (R8)
```

**Structure Decision**: The organisation list and the profile fields get their own top-level files beside `roles.yaml`. They are not settings, and `validate` needs to cross-check them: the organisation menu's options are generated from the organisation keys. Each new item type gets its own class beside `inspector.php`. That file is already 768 lines, and separate classes let the four be written independently. `inspector.php`, `applier.php` and `drift.php` only hand each payload array to its class.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. Organisations, categories, cohort definitions, cohort rules, fields and the role are declared. Drift reports undeclared ones and never writes back. |
| II. Portability | PASS. Every category and cohort is found by `idnumber` (`ltct:org:<key>` and the like), so a new server gets the same structure from `apply`. Memberships move with the data restore. |
| III. Public repo (NON-NEGOTIABLE) | PASS. The repo holds organisation keys, names and field definitions only. Who manages which organisation is a cohort membership set in Moodle (FR-012). Drift and apply report cohorts by name and never list members. A member count is not reported either. |
| IV. Disclosure boundary (NON-NEGOTIABLE) | PASS. Untouched. |
| V. CBC fidelity | PASS. The expertise areas are the category names of `competencies.yaml` except `Meta`, checked verbatim by `validate`. No level is recorded. |
| VI. No LMS orientation | PASS for learners: joining cohorts takes them no action. For managers the role is follow-only, with nothing to configure *(superseded 2026-10-02: managers manage through one page)*. It is proven simple only by SC-004. |
| VII. One shape | PASS. One shape per organisation is generated from one list entry, so it cannot differ. There is one manager role and no per-partner override. |
| VIII. Language data | PASS. Not applicable. |
| IX. Flat cost | PASS. `tool_dynamic_cohorts` is free (GPL, Moodle plugins directory). |
| X. Traceable and verified | PASS, with gates. The spec cites #8, #15 and #18. `tool_dynamic_cohorts` does not declare 5.2 support yet, so verifying it on the 5.2.3+ instance is a blocking task, with a named fallback (research R4). Separation inside a shared course is verified there with test accounts (R3) *(superseded 2026-10-02: open courses and manager scope are verified, R3)*. SC-004 needs real managers before #15 is marked done. The new recurring operation is the site team's enrolling, and spec 008 owns reducing it. |
| XI. Survives an upgrade | PASS. Core APIs: `core_course_category::create()`, `cohort_add_cohort()`, `cohort_update_cohort()`, `profile_save_category()`, `profile_save_field()`. Plugin API: `tool_dynamic_cohorts`' `rule` and `condition` persistent classes. Our own code uses core's `control_view_profile` callback, a supported extension point, and reads through `profile_user_record()`, `has_coursecontact_role()` and `has_capability()`. No direct writes. There are raw reads of stable core tables: `course_categories` by `idnumber`, `cohort` by `idnumber`, `user_info_category` by `name`, `user_info_field` by `shortname`, and `cohort` joined to `cohort_members` for the hook. Most of those columns are not indexed in core, so the constitution's indexed-read exemption does not cover them. Each is listed in `moodle/local_ltuse/README.md` with its reason, which is what Principle XI asks for. The hook cannot use `cohort_get_user_cohorts()`, which skips hidden cohorts (R9). |
| Platform: core first, pin plugins | PASS. Core cohorts, categories, fields, groups and cohort sync. One third-party plugin, pinned by version and sha256. One small piece of our own code, the profile hook, where core and no plugin can close the gap (R7, R9). *(Superseded 2026-10-02: see the 2.0.0 check in the amendment.)* |

Re-checked after Phase 1 design, and again after the 2026-10-01 task review added the profile hook: no change.

## Cross-spec effects

- **Spec 001**: its contract gains two files and four item types. This plan's [contracts/declaration.md](contracts/declaration.md) is the addition; spec 001's contract links to it.
- **Spec 008**: owns the site team's recurring work: bulk account creation with the organisation field, adding an organisation's cohorts to a course with its group *(superseded 2026-10-02: with no group)*, and adding someone to a managers cohort. Until it ships, these are admin-interface steps on learner data, which is allowed: they are memberships, not configuration.
- **Spec 004**: progress reports per organisation build on the organisation cohorts and the `orgmanager` role *(superseded 2026-10-02: the role only in organisation-only courses; see the amendment's Cross-spec effects)*. It adds the grade and report capabilities this spec leaves out.
- **Spec 003**: a mentor who is also an organisation manager holds both roles. `orgmanager` uses no `prohibit`, so it never takes a permission away from another role. The profile hook exempts anyone with `moodle/user:viewalldetails` in the viewed learner's context, which is how a mentor reaches a mentee in Moodle.
- **Publisher**: `--category` still takes a numeric id. Categories now carry an `idnumber`, so accepting `ltct:published` instead is a small follow-on. It is not needed here. *(2026-10-02: organisation-only courses are placed by idnumber, research R11.)*
- **INTENT.md**: the open question "How are partner organisations onboarded?" gets a decision entry dated 2026-10-01 when this spec is delivered.

## Amendment 2026-10-02: open courses

**Branch**: `002-open-courses` | **Spec**: [spec.md](spec.md), Clarifications 2026-10-02 | **Decision record**: [spec 011 handoff](../011-events-calendar/handoff.md) B1–B6, approved by Doug 2026-10-02 | **Impact map**: [org-boundaries.md](../011-events-calendar/org-boundaries.md)

### Summary

Competency courses become open across organisations. Shared courses have no groups, and the publisher, the site default and a new drift check keep them so. The course discussion forum runs with no groups, and `course-discussions.yaml` retires.

Organisation managers stop being enrolled in shared courses. Instead they get three things, all scoped by **one shared check**: the viewer is in `ltct:org:<key>:managers`, the person's organisation of record is `<key>`, and the person is a learner (research R10).
- **Seeing.** The profile hook opens their own learners' profiles to them (R9). A new "my organisation" page lists their people with progress, beside spec 004's per-organisation report.
- **Managing.** From that page they enrol and unenrol their learners, send a password-reset link, assign and end mentors (spec 003's Phase B design), and suspend and reactivate accounts (R10).
- **Contact.** Each manager and each of their organisation's people become message contacts automatically (R12).

Everything a manager does is our own `local_ltuse` code, because core cannot scope any of it to one organisation (R2, R10). `orgmanager` keeps its capabilities and is used only in organisation-only courses.

An organisation-only course is declared by the maintainer in a new `moodle/site/org-courses.yaml`. The publisher places it in the organisation's category through a new `local_ltuse` web service, and drift reports any course in the wrong place (R11). A one-off CLI removes the organisation groups and the shared-course managers enrolments already on the build host, reporting counts only (R13).

Out of scope, by decision: moving spec 004's report scope to cohort membership (spec 016 decision 2; `ltct_org` stays visible under its option (a), Doug, 2026-10-05 (scope review)); a user-context follow role (B3); managers creating accounts or editing the organisation field (FR-009, FR-013); managers enrolling into pilot courses; moving a course from Pilots to Published (spec 008 or later).

### Technical Context

**Language/Version**: Python 3.12 (`scripts/`), PHP 8.3 for `local_ltuse` on Moodle 5.2.3+ (`requires = 2026042000`, `supported = [502, 502]`).

**Primary Dependencies**: Moodle core only. `core_user` (`user_update_user()`, the `control_view_profile` callback), `core_enrol` (the manual enrolment plugin's API, `enrol_cohort`), `core_message\api` (contacts), core course and group APIs, `core_cohort` events. `tool_dynamic_cohorts` is unchanged (R4). No new plugin.

**Storage**: Moodle's database. One new `local_ltuse` table for the contacts this plugin makes for managers (R12), with a privacy provider entry. The repo holds declarations only.

**Testing**:
- `pytest` for `org-courses.yaml` validation, the course group-mode item, the payload's placement field and the publisher's group mode and placement calls.
- PHP harnesses without Moodle: `tests/profile_access_harness.php` (new cases, R9) and a new `tests/org_access_harness.php` for the shared check and the per-action rules (R10).
- PHPUnit with synthetic users in `moodle/local_ltuse/tests/` for the actions, the contact observer and the reconcile task.
- Instance checks in research R3, R9, R10, R11, R12 and R13, with `ltct-test-*` accounts only.

**Target Platform**: the 5.2.3+ build host `ltuse.net`, and later production (spec 015). Management pages are web pages; the Moodle app opens them in the browser.

**Project Type**: the training system: declarative site configuration, the publisher, and `local_ltuse`.

**Performance Goals**: a removed manager loses every page, action and profile at once (no sync lag), and spec 004's report within its 30-minute audience cache (accepted, read-only); contacts follow a cohort change on its event, and the hourly reconcile repairs any missed one.

**Constraints**: no learner data, names or counts per organisation in git, logs or PRs (III); no edit to core or any plugin (XI); no management action through a core selector that searches every user (FR-006a); spec 016's managers-cohort entitlement is matched by `is_org_member_of_manager`, which has no learner condition, while management actions use the narrower `may_manage_account` (R10); a person who asked for protection waits until 016 can set their level (R13; Doug, 2026-10-05 (scope review)).

**Scale/Scope**: four organisations today; a handful of managers; per-organisation membership from tens to a few hundred.

### Constitution Check (2.0.0)

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. Group mode, the forum mode, the organisation-only list and the `ltct:mentors` cohort are declared. Drift reports a course whose group mode or placement differs, and never writes back. Enrolments, suspensions and mentor assignments a manager makes are learner data and stay in Moodle. |
| II. Portability | PASS. Every new item is found by `idnumber`. `apply` and a republish rebuild them on a fresh instance; manager actions move with the data restore. |
| III. Public repo (NON-NEGOTIABLE) | PASS. `org-courses.yaml` names a course and its host organisation, which are already public. An organisation that asks not to be named publicly hosts one only under a neutral key (Doug, 2026-10-05 (scope review)). The migration CLI and drift report counts, never names. |
| IV. Disclosure boundary (NON-NEGOTIABLE) | PASS. Untouched. The payload's new placement field is checked by `check_moodle_payload.py` like any other. |
| V. CBC fidelity | PASS. Not touched. |
| VI. No LMS orientation | PASS with a gate. Learners do nothing new. Managers get one page for everything they do. Proven simple only by SC-004. |
| VII. One shape | PASS. One check, one page and one set of actions for every organisation. The organisation-only course is one uniform variant open to every organisation (2.0.0). |
| VIII. Language data | PASS. Not applicable. |
| IX. Flat cost, field-ready | PASS. No new plugin or service. |
| X. Traceable and verified | PASS with gates. Cites #8, #10, #11, #15. REQUIREMENTS rows #7, #8, #10, #11 and #15 go back to re-verify until the instance checks run. **Recurring work changes**: the site team loses the moved-learner clean-up and some per-person work to managers, and gains approving and enrolling organisation-only courses (R8). |
| XI. Survives an upgrade | PASS with listed exceptions. Supported extension points only: the profile callback, events, a scheduled task, web services, our own pages, privacy provider. Core writes go through core APIs (R10–R13): `enrol_plugin::enrol_user()`/`unenrol_user()`, `user_update_user()` with `destroy_user_sessions()`, the core reset-link functions with `core_login_process_password_reset()`'s guards copied, `\core_message\api::add_contact()`, `move_courses()`, `groups_delete_group()`. `user_update_user()` is deprecated for 5.3 (MDL-82650) and sits in one method. The exceptions, listed in `moodle/local_ltuse/README.md`: indexed raw reads of `cohort`/`cohort_members` (already listed, R9) and of `course_categories` by `idnumber` (R11). |
| Platform: one instance, open courses (2.0.0) | PASS. This change is what the amended rule describes: identified and scoped, not separated. |

Re-check after tasks: required, because R10's per-action rules are the riskiest part.

### Project Structure (changes)

```text
moodle/
├── site/
│   ├── settings/groups.yaml            # changed: moodlecourse/groupmode 0; every why reworded (R3)
│   ├── org-courses.yaml                # new: organisation-only courses, maintainer-declared (R11)
│   ├── course-discussions.yaml         # deleted: no groups, so nothing to share (R14)
│   ├── organisations.yaml              # changed: + hidden system cohort ltct:mentors (R10); wording
│   ├── reports.yaml                    # changed: group:name dropped; delivery condition "not manual" (R10)
│   ├── roles.yaml                      # changed: orgmanager and teacher descriptions and whys (R2)
│   └── README.md                       # changed: two enrolment recipes, declaring an org-only course, managers' page
├── local_ltuse/
│   ├── classes/organisation/access.php # new: the shared check and per-action rules, pure (R10)
│   ├── classes/organisation/people.php # new: the manager's people and their progress (R10)
│   ├── classes/organisation/actions.php# new: enrol, unenrol, reset link, suspend, reactivate (R10)
│   ├── classes/organisation/contacts.php # new: manager–member contacts (R12)
│   ├── classes/observer.php            # changed: + cohort_member_added/removed (R12)
│   ├── classes/task/reconcile_org_contacts.php # new: hourly repair (R12)
│   ├── classes/profile_access.php      # changed: own-organisation allow, participant path (R9)
│   ├── lib.php                         # changed: gathers the new inputs; managed keys unchanged
│   ├── organisation.php                # new: the "my organisation" page (R10)
│   ├── mentors.php                     # new: spec 003 R7 Phase B mentor page (R10)
│   ├── classes/external/ensure_discussion.php # changed: NOGROUPS; `shared` removed (R14)
│   ├── classes/external/place_course.php      # new: web service placing a course by category idnumber (R11)
│   ├── classes/siteconfig/{inspector,applier,drift}.php # changed: course group mode; placement drift; managers-cohort sync in a shared course fails (R2); discussion warnings removed
│   ├── classes/reportbuilder/local/entities/coverage.php # changed: organisation-enrolment instance counts as delivery (R10)
│   ├── classes/privacy/provider.php    # changed: the new contacts table
│   ├── cli/open_courses.php            # new: one-off migration, counts only (R13)
│   ├── db/{events,hooks,tasks,services,install.xml,upgrade.php}  # changed (hooks: the user-menu link)
│   ├── version.php                     # bumped
│   └── README.md                       # changed: new APIs, raw reads, the open-courses migration
└── REQUIREMENTS.md                     # rows 7, 8, 10, 11, 15 reset to re-verify, then updated on delivery
scripts/
├── site_config.py                      # changed: load_org_courses replaces load_discussions; course group-mode item; ltct:mentors
├── moodle_payload.py                   # changed: placement replaces discussion.shared
├── check_moodle_payload.py             # changed: checks placement's shape
├── publish_moodle.py                   # changed: groupmode 0; place_course for org-only; label text
└── moodle_client.py                    # changed: required functions + local_ltuse_place_course
tests/
├── test_site_config.py                 # changed: OrgCourses replaces Discussions; group-mode item
├── test_moodle_payload_discussion.py   # changed: placement instead of shared
├── test_publish_moodle.py              # changed: groupmode 0; placement
├── profile_access_harness.php          # changed: R9 cases
└── org_access_harness.php              # new: R10 rules
process/stages/08-publish.md            # changed: the two enrolment recipes
.github/workflows/{site-config,publisher-tests}.yml  # changed: path triggers; runs org_access_harness.php
CLAUDE.md                               # changed: one line under "Delivery: Moodle"
```

**Structure Decision**: Everything a manager does lives under `classes/organisation/`, behind one pure `access` class that every page, action, the profile hook and (later) spec 016's entitlement call. It is harness-tested in CI like `profile_access`. Pages are thin: they gather inputs, call `access`, then call `actions`. That keeps the security rule in one tested place.

### Order of work

1. **Governing text** (this PR's first commit): spec, research, plan, data model, contracts, INTENT, constitution 2.0.0.
2. **Open the courses**, resetting REQUIREMENTS rows #7, #8, #10, #11 and #15 to re-verify in the same commit, with their text rewritten (#7 drops core per-course reports for managers; #10 open forums; #11 managers assign mentors; #15 one manager role set and one page; the SC-002 test reworked and an organisation-only closure test added): `groups.yaml`, the publisher's group mode, the course group-mode drift item, `ensure_discussion` NOGROUPS, retire `course-discussions.yaml`, drop `group:name`. Ship together: a forum left in separate groups with the groups gone stops posting (R3).
3. **Migration on the build host** (R13), then R3's checks.
4. **Shared check and profile hook** (R9, R10 `access`), with harnesses.
5. **"My organisation" page and actions**, then **mentors page**, then **contacts** (R10, R12).
6. **Organisation-only courses** (R11).
7. **Docs and REQUIREMENTS**; SC-004 with real managers stays open after merge.

Steps 2–3 are the minimum spec 016 needs. Step 4 closes the profile reach 016 assumes.

### Cross-spec effects

- **003 (merged)**: its `groupmode 1` is main's own and is removed here. R7 Phase B is built here as `mentors.php`. Doc follow-ups in 003: research R8 ("with the organisation's group"), tasks T034/T035 (a mentor with no group, checked across two organisations), `spec.md:123`, and the `profile_access.php` docblock. Any later 003 work branches from main after this change.
- **004**: the report scope stays on `ltct_org` (Doug, 2026-10-02: left to 016). Changed here, because R10 needs it: the delivery condition `enrol:plugin = cohort` in `reports.yaml` (progress, programme) and `SCOPE_CONDITIONS` in `site_config.py` become "not `manual`", and `coverage.php` plus `competency_coverage_test.php` count the organisation-enrolment instance as delivery; `group:name` goes. Doc follow-ups in 004: `orgmanager`'s in-course reports apply only in organisation-only courses, so FR-006's second sentence is relaxed for shared courses, R8, R10 and quickstart V6 are rewritten (run V6 in an organisation-only course).
- **Follow-up checklist**: [org-boundaries.md §2](../011-events-calendar/org-boundaries.md) is the per-file list for 003, 004, 012 and 013's own documents (line numbers as of `de31ede`).
- **011**: plans on open courses. Course events reach every organisation in a shared course; organisation-only course events reach one organisation by enrolment. Deleting organisation groups (R13) deletes any group events with them.
- **012**: no organisation groups to rely on. `course-discussions.yaml` is gone; the organisation-cohort workshop allocator (T047–T051) is withdrawn; 012 settles assignment group mode before T032 and adds a rule for partner data in cross-organisation peer review.
- **013**: R10's rationale now rests on `badges:viewotherbadges` alone; quickstart V9 is re-run (co-enrol B1 with A1; Manager A opens their own learner through R9's allow and sees no badges; Manager B is refused).
- **016**: lands after this. Until it can set a person's level, a person who asked for protection waits; nobody else is held back (R13; Doug, 2026-10-05 (scope review)). Asked of 016, to record in its own docs:
  - its entitlement calls `access::is_org_member_of_manager()` rather than re-deriving the managers-cohort rule. `managers_see_identity` was cut (Doug, 2026-10-05 (scope review)); a person who does not trust their managers is placed by the site team under a neutral entry with no managers;
  - `organisation.php` may show 016's marker and real name for a manager's own people, only through 016's `can_view_identity`; 016 keeps its own `protected.php` for mentors and the site team, and the two may share a listing component;
  - `mentors.php` gains 016's check that assigning a mentor to a protected learner needs `can_view_identity` (R10);
  - `org-courses.yaml` joins the public files its neutral-key guidance covers (R11);
  - R10's suspend and reactivate write a minimal `{id, suspended}` object; 016 adds a PHPUnit case that suspending a protected user leaves their names unchanged.
  - email (Doug, 2026-10-02): a protected person's address is shown to their own organisation's managers and to their mentors, never to classmates. This bears on 016 decision 1, which proposes removing email from course leaders' views for everyone.
  Its decision 2 (cohort report scope; `ltct_org` stays visible, option (a), Doug, 2026-10-05 (scope review)) is untouched here.
- **005**: the community space is not a shared delivery course, so the 2.0.0 rule does not forbid its per-organisation rooms. But a group named for an organisation shows a protected member's organisation on every post, so 005 and 016 must settle those rooms (rename, or keep protected members out) before 016 ships; 016 adds the community space to its audit.
- **006, 008**: wording. 008 FR-008's "category-scoped manager roles" becomes "the managers cohort and the organisation page"; its enrolment tooling has a shared-course and an organisation-only case.

### Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Our own management pages and actions in `local_ltuse` | Core cannot scope enrolment, suspension, reset or role assignment to one organisation (R2, R10) | Granting core capabilities reaches every user on the site; leaving it all to the site team contradicts B5. |
| A profile-hook FORCE_ALLOW | Managers are not enrolled with their people, and spec 016's profile node needs them to reach them (R9) | A user-context role per learner needs a sync and lags (B3, declined). |
| A contacts table and reconcile task | Messaging needs a shared course or a contact (R12) | Bypassing the learner's privacy setting through our own sender was declined. |
| A placement web service | `core_course_get_categories` by idnumber needs `moodle/category:manage` at system level, `RISK_XSS` (R11) | Granting the publisher that capability is far broader than placing one course. |
