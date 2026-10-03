# Implementation Plan: Events, office hours and live sessions

**Branch**: `011-events-calendar`, planned on the open-courses change (002 amendment, branch `002-open-courses`) | **Date**: 2026-10-02 | **Spec**: [spec.md](spec.md) | **Decisions**: [handoff.md](handoff.md) D1–D7, approved 2026-10-02

## Summary

Most of this spec is core Moodle, configured from `moodle/site/`. Open courses make the calendar simple: a site event reaches everyone, a course event reaches the course's learners from every organisation, a group event reaches its group, and an organisation-only course's events reach only that organisation, because only its people are enrolled (D1, R1). Category events are not used (R2).

- **Who posts** (R4, R17):
  - The site team posts site events.
  - Course mentors (`teacher`) post in their courses, as core already allows.
  - Organisation managers post in their organisation's organisation-only courses: `orgmanager` gains `moodle/calendar:manageentries` (D3). Because the open-courses change enrols managers only in those courses, that is exactly where the capability reaches. This PR therefore merges after that change.
- **Time zones** (R5, R14):
  - The site default zone is UTC (D7).
  - FR-002 is relaxed: the learner's zone is confirmed at first login and shown on their profile (D5).
  - Core shows it on the profile, in the browser and in the app, but never asks for it. So `local_ltuse` asks once: on the web through a one-field page, and in the app through core's own "complete your profile" route.
- **Changes and cancellations** (R6, R15). Core sends nothing, so `local_ltuse` adds an observer, a message provider and an adhoc task. A whole series produces one message, by email and app push (row #25).
- **Office hours** (R9, R16, R19).
  - **The plugin.** `mod_scheduler` v5.2-r1, pinned, used in the browser only (D6).
  - **One course.** `ltct:officehours` holds one scheduler and one hidden-membership group per mentor. `local_ltuse` keeps the groups in step with spec 003's mentor assignments, extending 003's observers and adding a reconcile task.
  - **Who sees what.** A learner sees only their mentors' slots. Bookings stay private (D4): learners never see who else booked, and mentees of one mentor cannot see each other.
- **Dashboard and live sessions** (R7, R8, R10, R18).
  - `apply` puts an Upcoming events block on the default dashboard.
  - Export and subscribe are core.
  - A live session's join link goes in the event description, and its follow-up is added there afterwards.
  - Nothing a course needs for completion is ever an event, so FR-010 holds by construction.

## Technical Context

**Language/Version**: Python 3.12 (`scripts/`, as CI runs). PHP 8.2+ for `local_ltuse` on Moodle 5.2.3+.

**Primary Dependencies**:
- Moodle core:
  - `core_calendar`: its events `calendar_event_created`, `_updated` and `_deleted`; export
  - the message API: `message_send`, `\core\message\message`
  - adhoc and scheduled tasks
  - groups: `groups_create_group`, `groups_add_member` with a component
  - enrolment: `enrol_manual`
  - `create_course`, `create_module`
  - the block manager on the default `my-index` page
  - `user_update_user`
  - the legacy `after_require_login` callback
  - the `user_updated` event
- `mod_scheduler` v5.2-r1 (`2026080400`), free and pinned in `site.yaml`.
- Our own `local_ltuse`, built on spec 003's observers, and PyYAML.

**Storage**:
- Moodle's database: events, scheduler slots and appointments, groups, enrolments, and one user preference, `local_ltuse_tzconfirmed`.
- No new `local_ltuse` table: group membership records its owner through `component` and `itemid`.
- The repo holds declarations only.

**Testing**:
- `pytest` for `site_config.py`'s new validation and rendering (synthetic inputs).
- Three PHP harnesses for the pure decisions, run as the existing ones are:
  - `calendar_notify_harness.php`: is this event change announced, and under which key;
  - `timezone_gate_harness.php`: is this request asked to confirm a zone;
  - `officehours_harness.php`: the difference between assignments and memberships.
- PHPUnit on synthetic data for the sync, in `moodle/local_ltuse/tests/officehours_test.php`.
- Instance checks V1–V18 in [quickstart.md](quickstart.md).

**Target Platform**: The self-hosted Moodle 5.2.3+ build host and, later, production (015), plus the Moodle Android app.

**Project Type**: The training system: declarative site configuration and its Moodle plugin. The publisher is not touched: events are operational records, not course content (FR-011).

**Performance Goals**:
- A change or cancellation reaches the affected learners within 10 minutes: a 2-minute debounce plus cron.
- A site event's notice is sent in batches of 500 per task run.
- A new mentor relationship shows the mentor's slots on the learner's next page load, through the observer, or within an hour, through the reconcile task.

**Constraints**:
- No learner data in git (FR-012, Principle III).
- Who mentors whom, who booked and who is in which group are never declared.
- Group names carry no person's name (spec 016).
- No hard-coded host.
- Only upgrade-safe extension points (Principle XI).
- No new conferencing service (FR-013).

**Scale/Scope**: hundreds of learners, tens of mentors, one office-hours course, about ten delivered courses, and a handful of organisation-only courses.

No NEEDS CLARIFICATION remains. Every API above was confirmed on `MOODLE_502_STABLE` or in the v5.2-r1 zip (research R14–R19). Every behaviour the plan relies on has a **Verify** line in research and a check in the quickstart.

## Constitution Check

*Gate before Phase 0; re-checked after Phase 1 design.*

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. Calendar settings, the time zone default, the scheduler pin and settings, the office-hours course and activity, the dashboard block and every role change are declared in `moodle/site/` and applied by `site_config.py`. Events, slots, bookings and memberships are operational records in Moodle and are never read back (FR-011). |
| II. Portability | PASS. The course and activity are identified by `idnumber` (`ltct:officehours`, `ltct:officehours:scheduler`) and the groups by `ltct:mentor:<id>`, so `apply` on a rebuilt server re-creates them and the reconcile task refills them (SC-005). Bookings export through the scheduler's privacy provider, and events through core's (FR-012). The payload is untouched. |
| III. Public repo (NON-NEGOTIABLE) | PASS. No relationship, booking or member is declared. `apply`, `drift` and the reconcile task report counts and idnumbers, never names. Group names carry no names. Quickstart evidence stays outside the repo, and only `ltct-test-*` accounts are used. |
| IV. Disclosure (NON-NEGOTIABLE) | PASS. Not touched. Events and the office-hours course carry no course content, and the payload gates are unchanged. |
| V. CBC fidelity | PASS. No event, booking or attendance awards or implies a level, and the scheduler's grading is off (`grade 0`). New strings carry no level vocabulary and no "certified". |
| VI. No LMS orientation | PASS with gates. Learners find events on their dashboard and in the app's calendar. They reach office hours from one course card. A manager posts from the course's own calendar. The zone is asked once, in one field. SC-003 and SC-004 need 2–3 real learners and managers (quickstart). |
| VII. One shape | PASS. One set of event levels, one office-hours course and one manager capability for every partner. An organisation-only course is the uniform variant the open-courses change defines, not a per-partner calendar. |
| VIII. Language data | Not applicable. |
| IX. Flat cost, field-ready | PASS with a stated limit. Core, one free plugin and our own code. Events and bookings show in the app's calendar and offline once synced. Booking itself is browser-only (D6), and the app's "Open in browser" covers it. Live sessions are never the only route (FR-010), and no conferencing server is run (FR-013). |
| X. Traceable and verified | PASS with gates. The plan cites row #21, and the delivering PR updates it. The scheduler pin, group filtering, the time zone route in the app, notification collapsing and manager reach are verify tasks that block their stories. New recurring operations: keeping the scheduler pin current on every Moodle upgrade (015's operator), and the hourly reconcile and the scheduler's own tasks, which ride the cron 015 already monitors. |
| XI. Survives an upgrade | PASS with listed exceptions. Writes go through core APIs only: `create_course`, `create_module`, `groups_*`, `enrol_manual`, `user_update_user`, `message_send`, block manager, tasks. Two exceptions are listed in the plugin README: throwing core's `usernotfullysetup` from our callback to reach the app's profile route (R14, V3 re-run on every core or app upgrade), and relying on the scheduler's group filter (R16, V10 re-run on every re-pin). The legacy `after_require_login` callback is used because no hook replaces it. `local_ltuse` keeps `requires` and `supported` at 5.2. |
| Platform: core first | PASS. The calendar, export, the profile's time zone, the dashboard block and messaging are core. Booking is a maintained free plugin because core has none (R9). Our code fills three gaps core leaves: notifying changes, asking for the zone, and turning 003's user-context relationships into scheduler groups (Complexity Tracking). |

Re-checked after Phase 1 design: no change.

## Project Structure

### Documentation (this feature)

```text
specs/011-events-calendar/
├── spec.md              # amended 2026-10-02 (Clarifications D1–D7)
├── handoff.md           # the decisions, approved 2026-10-02
├── org-boundaries.md    # the open-courses impact analysis
├── plan.md              # this file
├── research.md          # R1–R19
├── data-model.md        # declarations, Moodle records, the change notice, the sync
├── quickstart.md        # instance checks V1–V18
├── contracts/
│   ├── declaration.md   # office-hours.yaml, dashboard.yaml, settings, roles, site.yaml
│   └── local-ltuse.md   # observer, task, provider, time zone gate, office-hours sync
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
moodle/
├── site/
│   ├── office-hours.yaml               # new: the office-hours course and its scheduler (#21)
│   ├── dashboard.yaml                  # new: Upcoming events on the default dashboard (#21, FR-003)
│   ├── settings/calendar.yaml          # new: export, adminseesall, timezone UTC, forcetimezone (R12)
│   ├── settings/scheduler.yaml         # new: mod_scheduler site settings (R19)
│   ├── site.yaml                       # changed: + mod_scheduler v5.2-r1; local_ltuse re-pinned
│   ├── roles.yaml                      # changed: orgmanager + calendar:manageentries; student and teacher scheduler caps
│   ├── organisations.yaml              # changed: + category "mentoring" (LTC Mentoring)
│   └── README.md                       # changed: posting events, live-session links, org-wide messages, office hours
├── local_ltuse/
│   ├── classes/calendar_notify.php     # new: pure decide() and key() for a calendar change (R15)
│   ├── classes/task/event_change_notice.php  # new: adhoc task; resolves recipients, sends
│   ├── classes/timezone_gate.php       # new: pure decide() for the first-login prompt (R14)
│   ├── classes/officehours.php         # new: sync_pair(), reconcile(), group and enrolment writes (R16)
│   ├── classes/officehours_plan.php    # new: pure difference of assignments and memberships
│   ├── classes/task/officehours_reconcile.php  # new: scheduled, hourly
│   ├── classes/observer.php            # changed: calendar events, user_updated; mentor events also sync office hours
│   ├── classes/siteconfig/officehours.php      # new: apply/drift for the course and the activity
│   ├── classes/siteconfig/dashboard.php        # new: apply/drift for the default dashboard block
│   ├── classes/siteconfig/{inspector,applier,drift}.php  # changed: hand them their arrays
│   ├── classes/privacy/provider.php    # changed: declares the user preference
│   ├── timezone.php, templates/timezone.mustache  # new: the one-field page
│   ├── lib.php                         # changed: after_require_login, allow_group_member_remove
│   ├── db/events.php                   # changed: + calendar_event_created/_updated/_deleted, user_updated
│   ├── db/messages.php                 # new: provider eventchange
│   ├── db/tasks.php                    # new: officehours_reconcile
│   ├── lang/en/local_ltuse.php         # strings
│   ├── tests/officehours_test.php      # new: PHPUnit, synthetic data
│   ├── version.php                     # bumped
│   └── README.md                       # changed: the two XI exceptions, the new tasks and observers
└── REQUIREMENTS.md                     # row 21 updated on delivery
scripts/
└── site_config.py                      # changed: validate and render office-hours.yaml, dashboard.yaml, calendar rules
tests/
├── test_site_config.py                 # changed
├── calendar_notify_harness.php         # new
├── timezone_gate_harness.php           # new
└── officehours_harness.php             # new
.github/workflows/site-config.yml       # changed: runs the three harnesses
```

**Structure Decision**: Everything follows the shape specs 003, 004 and 013 set. Site-wide items are declared in `moodle/site/` and applied by `site_config.py` through new `siteconfig` classes. Behaviour lives in `local_ltuse` on supported extension points. Each decision with no Moodle calls is a small pure class tested by a harness in CI, which leaves only the writes to PHPUnit and the instance. The publisher and the payload are not touched, because nothing here is course content.

## Decisions on the plan's limits

These are for the maintainer (Doug). `/speckit-tasks` may generate tasks as drafted, but a task that depends on a pending item is not closed until it is confirmed.

| # | Limit or choice | Status |
|---|---|---|
| 1 | **One office-hours course** (`ltct:officehours`, "Mentor office hours") holds the scheduler for every mentor. Not one in every shared course, and not 005's community course, which doesn't exist yet (R16). It is one more course card for learners who have a mentor. | Proposed. |
| 2 | **How the zone is asked** (R14). A preference records the confirmation. The web uses a one-field page, and the app uses core's "complete your profile" route, reached by throwing core's `usernotfullysetup`. That reuse is a listed Principle XI exception. | Proposed. |
| 3 | **The cancellation window** (`guardtime`). Inside it, a learner can neither book nor cancel, and must message the mentor (R19). | **Pending the maintainer.** 12 hours is proposed. |
| 4 | **Booking another mentor's slot** by a crafted request is possible, because the scheduler's group check is display-only (R19). The mentor sees the booking and can remove it. Closing the gap would mean our code calling the scheduler's internals. | Proposed: accept, and document. |
| 5 | **New events are not announced**, only changes and cancellations (FR-006). A new site event would otherwise email every account. The Upcoming events block and the app calendar show new events. | Proposed. |
| 6 | **Merge order.** The `orgmanager` calendar grant is safe only once managers are enrolled in organisation-only courses alone (R17). This PR merges after the open-courses change, and V6 runs on that state. | Dependency, not a choice. |
| 7 | **Showing the zone beside each time** stays out until 007 (D5). The profile and the first-login prompt are what 011 delivers. | Decided (D5). |

## Cross-spec effects

- **002 (open-courses amendment)**:
  - Lands first.
  - 011 depends on its rule that managers cohorts are enrolled only in organisation-only courses.
  - The office-hours course is not an organisation course: it has no cohort sync, and its own groups are by design. The site default `groupmode 0` does not affect it, because `apply` sets the course's own mode.
  - `organisations.yaml` gains one shared category.
  - 011 also changes the `orgmanager` description, which the amendment rewrites. Whichever lands second rebases onto the other's wording.
- **003**:
  - Its `role_assigned`, `role_unassigned` and `user_deleted` observers also call the office-hours sync.
  - A mentor who mentors no one is never enrolled there.
  - 003's Mentoring page could later link to office hours. That is not in 011.
- **005**: may later host the scheduler instead. Moving it is a re-declaration plus a reconcile.
- **007**: owns the dashboard's layout. 011 adds only the Upcoming events block (R18), and 007 decides on Timeline. Showing the zone beside each event time is 007's to revisit (D5).
- **015**: its operator keeps the `mod_scheduler` pin current and re-runs V10 on each re-pin. Our reconcile task and the scheduler's `send_reminders` and `purge_unused_slots` tasks ride the cron it monitors.
- **016** (PR #84):
  - It re-saves a protected user's scheduler slots when their level changes. 011 installs the plugin it calls.
  - 011's `seeotherstudentsbooking` override and its name-free group names are what 016 assumes.
  - 016's V17 runs once both have landed.
  - The change notice comes from the no-reply user and names no person.

## Complexity Tracking

| Exception | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Our own change-notice observer, task and message provider | FR-006: core sends nothing when an event changes (R6) | **Nothing**: fails FR-006. **The app's local reminders**: they don't fire on a change, and don't reach email. |
| Our own first-login time zone prompt, with the app route through core's `usernotfullysetup` | D5: "set at first login", which core never asks for (R14) | **A required custom profile field**: the tick and the zone are separate, so UTC stays. **Site team sets it**: not "at first login", and they rarely know it. |
| Our own office-hours group sync and reconcile task | D6: 003's relationship is a user-context role, and the scheduler knows only course groups (R9, R16) | **Groups by hand**: drift between relationships and slots, and site-team work on every change. **Open slots**: Doug chose a group per mentor. |
| A third-party plugin, `mod_scheduler` | Core has no booking module (R9) | **Our own booking code**: rebuilds a maintained plugin. **`mod_organizer`**: the same app gap and a larger surface. |
