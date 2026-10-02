# Research: Events, office hours and live sessions

**Plan**: not yet written. It waits on the decisions in [handoff.md](handoff.md). Every API below was looked up on 2026-10-02. Context7 was queried first (`/websites/moodledev_io_5_2_apis`, and `/moodle/moodle` for `UPGRADING.md`), then each claim was confirmed in upstream source:
- core: `MOODLE_502_STABLE`. In 5.1 and later core lives under `public/`; paths here leave that prefix off.
- the Moodle app: `moodlehq/moodleapp`, branch `main`.
- `mod_scheduler`: `learnweb/moodle-mod_scheduler`, release `v5.2-r1` (zip from the plugins directory).
- `mod_organizer`: release `v5.2.0`. `mod_attendance`: build `2026042102`.

A second, independent pass re-read the deciding code for the 23 claims the handoff rests on: 21 held as stated and 2 needed the small corrections now in R1 and R9. "Inferred" marks a behaviour read from source but not run. Each **Verify** line has to be confirmed on the 5.2.3+ instance, with test accounts only, before the task that depends on it is closed (constitution X).

Unlike a finished research file, most sections here end in **Status: pending** rather than a decision, because the decision belongs to the maintainer.

## R1. Core has five event types, and each one is scoped by enrolment

| Type | `eventtype` | Who sees it | Who may create it |
|---|---|---|---|
| Site | `site` | Everyone | `moodle/calendar:manageentries` in the site course |
| Category | `category` | Users enrolled in a course in that category or below it (R2) | `moodle/category:manage` in the category |
| Course | `course` | Everyone actively enrolled in the course, **whatever their group** | `manageentries` in the course |
| Group | `group` | Members of the group only | `manageentries` (any group), or `managegroupentries` and membership of that group |
| User | `user` | That user | `manageownentries` (the `user` archetype has it) |

- Context: `calendar_event::calculate_context()` checks `categoryid`, then `courseid`, then `groupid`, then the user.
- Visibility: `calendar_information::create()` and `set_sources()`, `calendar_set_filters()` (`calendar/lib.php`); course events are selected as `groupid = 0 AND courseid IN (enrolled)` (`core_calendar\local\event\strategies\raw_event_retrieval_strategy`).
- Creation: `calendar_add_event_allowed()`; editing: `calendar_edit_event_allowed()`. Editing a category event checks `manageentries` in the category, not `category:manage`. Anyone holding `manageentries` at **system** context passes every non-user check first (`calendar_can_manage_non_user_event_in_system()`).
- Capabilities (`lib/db/access.php`): `manageentries` and `managegroupentries` are course-context, held by the `teacher`, `editingteacher` and `manager` archetypes. Both carry `RISK_SPAM`.

**Consequence for us**: separation by group, which is how spec 002 keeps organisations apart inside a shared course, holds for group events only. A course event in a shared course reaches every organisation enrolled in it.

## R2. Category events do not fit how organisations are built

Spec 002 made each organisation a category (`ltct:org:<key>`), but learners are enrolled only in shared courses in `LTC Published` (002 R1, R6). Nothing sits inside an organisation's category.

- **They reach nobody.** The dashboard and site calendar include a category's events only if the user is enrolled in a course in that category or one of its descendants, or manages it (`calendar_information::set_sources()`). An organisation's learners are enrolled in none.
- **They leak.** `calendar/export_execute.php` with "All" or "Categories" and no course sets `$paramcategory = true`, which `calendar_get_legacy_events()` treats as *every* category, and the only filter afterwards is `core_course_category::is_uservisible()`. So every learner's exported feed would carry every visible organisation's category events. `calendar/view.php?category=<id>` also shows them to anyone who can see the category, and the `user` archetype has `moodle/category:viewcourselist`.
- **Nobody we have can create them.** Creation needs `moodle/category:manage` (`RISK_XSS`), a full category-management right. `orgmanager` exists only at course context (002 R2).

**Status**: category events are ruled out. With open courses (handoff Part 1) they are not needed.

## R3. Where an organisation-wide event can live

Because of R2, an event for "everyone in organisation A" needs a course that A's learners are enrolled in.

| Option | Organisation event is | Reaches | Cost |
|---|---|---|---|
| A. Spec 005's community course | a group event in the community course, in A's group | A's members only; site-wide and cross-organisation events are course events there | Depends on 005's course existing, with one group per organisation (005 FR-007 already asks for per-organisation rooms) |
| B. A hub course per organisation | a course event in A's hub, in A's category | A's members only | One more course on every learner's dashboard; site-config or the publisher must create it |
| C. Group events in each shared course | a group event per shared course | A's learners in that course only | The same event posted once per course; misses learners not currently enrolled |
| D. Open events | a site event or a course event in the community course | everyone | No organisation-private events at all |

**Status**: with open courses (handoff Part 1), option D covers cross-organisation events and an organisation-only course covers private ones; organisation-wide announcements are handoff D2. Option A fits 005's "one standing community space ... independent of any course enrolment" and "every new account is a member automatically" (005 FR-001, FR-002) and "community events are 011" (005 spec, line 147).

## R4. Who can post an event, and a gap in shared courses today

- **Organisation managers cannot post today.** `orgmanager` holds no calendar capability, and INTENT's 2026-10-01 decision says a manager "follows their own people ... and does nothing else". Proposed (Matthew, 2026-10-02): treat manager posting as **Phase B**, as spec 003 did with manager-assigned mentors. Phase A: the site team posts. Phase B, once the maintainer reopens INTENT: `orgmanager` gains `moodle/calendar:managegroupentries` only, which lets a manager post a group event for a group they belong to, which is their own organisation's group (R1).
- **A gap exists already.** `teacher` (our "Course mentor") and `editingteacher` hold `manageentries` by default. In a shared course that lets a course mentor post a **course** event that reaches every organisation, and a group event for *any* group, since `manageentries` passes the group check without membership (`calendar_add_event_allowed()`). If organisation boundaries are to hold for events, `roles.yaml` should set `moodle/calendar:manageentries: inherit` for `teacher` and keep `managegroupentries`. With open courses (handoff Part 1) this is intended, and it can stay.
- **Course start date**: the event form refuses a course or group event before the course's `startdate` (`forms/create.php`, `errorbeforecoursestart`).
- **Privacy deletion removes the events a person created.** `core_calendar\privacy\provider::delete_data_for_user()` deletes events by `userid`. If a mentor or manager asks for their data to be deleted, every course and group event they posted goes too. Inferred; relevant to spec edge case "an organisation manager leaves".

**Status**: pending (handoff D3).

## R5. Time zones: correct by default, but the zone is never shown

- Stored as a UTC integer; shown with `userdate()` in the viewer's zone (`core_date::get_user_timezone()`: `forcetimezone`, else the user's `timezone`, else `$CFG->timezone`). Defaults: `forcetimezone = 99` (not forced).
- Weekly repeats are generated in the **creator's** zone (`calendar_event::update()`), so a repeating event keeps the creator's wall-clock time across daylight saving. A viewer in a zone with a different DST date sees it shift by an hour for those weeks. That is correct behaviour, but it needs saying in the quickstart (SC-002).
- **No calendar view shows the zone.** The time formats (`humandate`, `humantimeperiod`, `strftimetime`) have no `%Z`, and the `event_details` and `upcoming_mini` templates print no zone. FR-002 ("with the zone indicated") and the spec edge case ("the zone in use is always visible beside the time") are not met by core.
- Options: a template override in a child theme (allowed by constitution XI, but adds a theme), our own block or output hook, or relaxing FR-002 to "the learner's zone is shown on their profile and set at first login".

**Status**: pending (handoff D5).

## R6. Core sends no notification when an event changes

- No calendar message provider in `lib/db/messages.php`, no `message_send` in calendar code, and no core task sends reminders. `\core\task\calendar_cron_task` only refreshes iCal subscriptions.
- The app schedules its own **local** reminders before events (`CoreReminders`, `src/addons/calendar/services/calendar.ts`).
- `\core\event\calendar_event_created`, `_updated` and `_deleted` fire from `calendar_event::update()` and `delete()`, from the web and web services, **once per occurrence** of a series. Updated and deleted carry a snapshot of the row; created does not.
- So FR-006 needs our own code: a `local_ltuse` observer on those three events and a message provider, which resolves the affected users from `eventtype`, `courseid` and `groupid` and sends through the core message API. Email is the default; push is off until spec 015 decides the app plan (`site.yaml`, `message_airnotifier: 0`). The observer must collapse a series into one message.

**Status**: proposed for the plan, not in dispute.

## R7. Learners can export or subscribe unaided

- `calendar/export.php` needs only `require_login()` and `enablecalendarexport` (default 1). There is no capability check. "Get calendar URL" gives a subscribable link that works without a session; its token is `sha1(userid . password hash . calendar_exportsalt)`, so it changes when the password or the salt changes. `calendar_exportsalt` is already in `ignore.yaml`.
- Times are exported in UTC (`Bennu::timestamp_to_datetime()`), which external calendars convert correctly.
- The export carries category events for every visible category (R2). That only matters if category events are used.

**Status**: meets FR-004 with no configuration, beyond keeping `enablecalendarexport` and `calendar_customexport` declared.

## R8. The dashboard and the app

- `block_timeline` shows only action events (activity due dates). A hand-made event **never appears** in the Timeline.
- `block_calendar_upcoming` shows every standard type, using `calendar_lookahead` (21 days) and `calendar_maxevents` (10). It is **not** a default dashboard block on a new 5.2 site (`blocks_add_default_system_blocks()` adds `calendar_month`, `recentlyaccesseditems`, `myoverview`, `timeline`).
- Spec 007 (unplanned) wants "no empty blocks ... no 'timeline' of unrelated items" (007 US1, FR-001). So FR-003 here needs agreeing with 007: an Upcoming events block, and probably not Timeline.
- The app reads the calendar through `core_calendar_get_calendar_upcoming_view`, `_monthly_view`, `_day_view` and related services, supports category events, caches results with `updateFrequency: SOMETIMES`, and can create, edit and delete events offline for later sync (`src/addons/calendar/services/calendar-offline.ts`, `calendar-sync.ts`). That cached results are served while offline is general app behaviour and is not confirmed in calendar code.

**Verify** (blocks US1, FR-003): on spec 009's V7 device, open the app online, then go offline, and the upcoming events already seen still show.

**Status**: dashboard part pending agreement with 007.

## R9. Office hours: `mod_scheduler` works on 5.2, but not in the app

Core has no booking module (`public/mod` on `MOODLE_502_STABLE`).

**`mod_scheduler`** is maintained again, now by Learnweb (`learnweb/moodle-mod_scheduler`, last push 2026-10-01).
- Pin candidate: `v5.2-r1`, version `2026080400`, `supported = [500, 502]`, `MATURITY_STABLE`; `https://marketplace.moodle.com/api/plugins/mod_scheduler/versions/2026080400/download`, sha256 `928ea42b8a4835dcc2c398d2c069cd198c8d41e3130883c2299f36e090a97826`. The zip's root folder is `moodle-mod_scheduler-5.2-r1/`, the same layout the `mod_customcert` pin already handles.
- **No app support.** It has no `db/mobile.php`, and the app has no scheduler addon (`src/addons/mod`). The app shows "This content is not available in the app" with **Open in browser**. Booking is therefore browser-only; the resulting calendar entries are user events, which the app does show (inferred).
- **Learners see who else booked** by default: the `student` archetype holds `mod/scheduler:seeotherstudentsbooking`. FR-008 needs it set to `inherit` (or `prohibit`) for `student` in `roles.yaml`.
- **"My mentor's slots only"** comes from group mode: a learner sees only slots of a teacher who shares a group with them. There is no per-slot list of allowed learners.
- A non-editing `teacher` holds `attend` and `manage` (own slots) by default, but not `canscheduletootherteachers`.
- Calendar: one per-user event for the learner and one for the teacher (`courseid = 0`, `userid` set, `eventtype` `SSstu:<slotid>` / `SSsup:<slotid>`, `modulename = scheduler`; `classes/model/slot.php`), so a booking shows in both calendars (FR-007).
- Notifications: its own message providers for booking, cancellation and reminders, and a `send_reminders` task.
- A meeting link belongs in the slot's **Comments** (rich text), not **Location** (plain text).
- Privacy provider present.

**The fit with spec 003 is the open problem.** 003 models a mentor as a **user-context** role on the learner, independent of courses. Scheduler only knows **course** teachers and groups. So a mentor must also be a `teacher` in whichever course holds the scheduler, in a group with their mentees. Options: one scheduler in the community course (R3 option A), with a group per mentor; or one scheduler per shared course. Either needs a step that turns 003's assignments into course groups.

**Alternatives**: `mod_organizer` `v5.2.0` (`2026072400`, sha256 `ffa7ba5ad2e0f624e7ba88aada3a76ddd8be23b3d3503c8bcb74b01ec8bf4ed8`) has better per-slot "who sees whom" control but the same app gap. `mod_booking` is heavy, has a Pro tier, and its download needs a login. `mod_facetoface` stops at 4.5.

**Verify** (blocks US3): install the pin on the instance; a non-editing teacher adds slots; in separate groups, a learner sees only their mentor's slots; with `seeotherstudentsbooking` removed, co-bookers are hidden; both calendars show the booking, on the web and in the app; the reminder task runs; the app's Open in browser keeps the learner logged in; a privacy export holds the appointment.

**Status**: pending (handoff D4, D6). With open courses, "my mentor's slots" needs a group per mentor or open slots, not organisation groups.

## R10. Live sessions: a link in the event description

- Core events have no "join" concept; only module action events carry an action (`event_action_exporter`).
- On the web, a `location` that is a bare `http(s)` URL is rendered as a link (`calendar_format_event_location()`). **In the app, `location` always becomes a maps link** (`CoreUrl.buildMapsURL`, `src/addons/calendar/pages/event/event.ts`), so a meeting URL there opens a maps search. The join link goes in the **description** (HTML, links work in both), with location left empty or used for a real place.
- `mod_bigbluebuttonbn` is bundled and disabled on install. It stays disabled: running a conferencing server is out of scope (FR-013).
- Follow-up notes or a recording link are added to the same event's description after it (FR-009).
- FR-010 (no completion depends on attending) is about course design and the publisher, not the calendar: a published course must not carry a completion condition on a live session. `check_course_package.py` or the payload check could assert it.

**Status**: proposed for the plan, not in dispute.

## R11. Attendance is optional and can wait

`mod_attendance` build `2026042102` (`supported = [501, 502]`; sha256 `3c709733aa74e159633e2dc9d7a26b348101c56bec4b0a20c104ecd0dc841b35`) has app support (`db/mobile.php`), a privacy provider, and creates course or group events per session. Do **not** pin the newer `2026090300`, which is the 5.3 line. The spec makes attendance optional ("included only if a partner asks for it"), so the proposal is to leave it out of 011 and record the pin candidate.

**Status**: proposed: not in scope until a partner asks.

## R12. Settings to declare

Calendar settings live on the `calendar` admin page (`admin/settings/appearance.php`); the time zone settings are in `admin/settings/location.php`.

| Setting | Default | Proposed |
|---|---|---|
| `enablecalendarexport` | 1 | 1 (FR-004) |
| `calendar_customexport` | 1 | 1 |
| `calendar_exportlookahead` | 365 | default |
| `calendar_lookahead` | 21 | default; the learner can change it |
| `calendar_maxevents` | 10 | default |
| `calendar_adminseesall` | 0 | 0, so a site admin's calendar is not every course's |
| `forcetimezone` | 99 | 99: never force; learners are in every zone |
| `timezone` | server | the site default zone for learners who set none (spec edge case); value for the maintainer |

All are read by the app through `tool_mobile`.

## R13. Smaller findings to carry into the plan

- **The category list is cached per session** (`calendar_categories`, `MODE_SESSION`, TTL 900 s, `lib/db/caches.php`), so a new enrolment can take up to 15 minutes to change which category events a learner sees.
- **Suspension keeps group membership.** With `enrol_cohort/unenrolaction = 3` (002), a learner who leaves a cohort is suspended, not unenrolled, and stays in the group (`enrol/cohort/locallib.php`). The calendar's course list uses active enrolments only, so the course drops out of their calendar; whether a direct `view.php?course=` or event URL still shows its group events is **open**.
- **Exported feeds outlive a change.** Anything a learner could see is copied into their external calendar and stays there, and `calendar_showicalsource = 1` adds the course or category name to each exported event.
- **The Communication API (`communication_customlink`, `communication_matrix`) is `MATURITY_ALPHA`** in 5.2. It is not a route to per-course meeting rooms.
- **Site managers can manage anyone's user events** (`calendar_can_manage_user_event()`, system `manageentries`). Personal events are private from other learners, not from the site team.
