# Research: Events, office hours and live sessions

**Plan**: [plan.md](plan.md). The maintainer approved decisions D1–D7 of [handoff.md](handoff.md) on 2026-10-02; they are recorded in the spec's Clarifications. R1–R13 are the findings that went into the handoff. R14–R20 were added for the plan, and each was confirmed in source the same way. Every API below was looked up on 2026-10-02. Context7 was queried first (`/websites/moodledev_io_5_2_apis`, and `/moodle/moodle` for `UPGRADING.md`), then each claim was confirmed in upstream source:
- core: `MOODLE_502_STABLE`. In 5.1 and later core lives under `public/`; paths here leave that prefix off.
- the Moodle app: `moodlehq/moodleapp`, branch `main`.
- `mod_scheduler`: `learnweb/moodle-mod_scheduler`, release `v5.2-r1` (zip from the plugins directory).
- `mod_organizer`: release `v5.2.0`. `mod_attendance`: build `2026042102`.

A second, independent pass re-read the deciding code for the 23 claims the handoff rests on: 21 held as stated and 2 needed the small corrections now in R1 and R9. "Inferred" marks a behaviour read from source but not run. Each **Verify** line has to be confirmed on the 5.2.3+ instance, with test accounts only, before the task that depends on it is closed (constitution X).

Each section ends in a **Status** line naming the decision it rests on.

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

**Status**: decided (D1, D2). Option D covers cross-organisation events, and an organisation-only course covers an organisation's own. Organisation-wide announcements are not in 011. The site team sends them as a message to the organisation's cohort, using core's bulk user action "Send a message" filtered by cohort (quickstart V14), and they are revisited with 005. Option A fits 005's "one standing community space ... independent of any course enrolment" and "every new account is a member automatically" (005 FR-001, FR-002) and "community events are 011" (005 spec, line 147).

## R4. Who can post an event, and a gap in shared courses today

- **Organisation managers cannot post today.** `orgmanager` holds no calendar capability, and INTENT's 2026-10-01 decision says a manager "follows their own people ... and does nothing else". Proposed (Matthew, 2026-10-02): treat manager posting as **Phase B**, as spec 003 did with manager-assigned mentors. Phase A: the site team posts. Phase B, once the maintainer reopens INTENT: `orgmanager` gains `moodle/calendar:managegroupentries` only, which lets a manager post a group event for a group they belong to, which is their own organisation's group (R1).
- **A gap exists already.** `teacher` (our "Course mentor") and `editingteacher` hold `manageentries` by default. In a shared course that lets a course mentor post a **course** event that reaches every organisation, and a group event for *any* group, since `manageentries` passes the group check without membership (`calendar_add_event_allowed()`). If organisation boundaries are to hold for events, `roles.yaml` should set `moodle/calendar:manageentries: inherit` for `teacher` and keep `managegroupentries`. With open courses (handoff Part 1) this is intended, and it can stay.
- **Course start date**: the event form refuses a course or group event before the course's `startdate` (`forms/create.php`, `errorbeforecoursestart`).
- **Privacy deletion removes the events a person created.** `core_calendar\privacy\provider::delete_data_for_user()` deletes events by `userid`. If a mentor or manager asks for their data to be deleted, every course and group event they posted goes too. Inferred; relevant to spec edge case "an organisation manager leaves".

**Status**: decided (D3). Managers post now, in their organisation's organisation-only courses (R17). `teacher` keeps `manageentries`, so course mentors post course events in shared courses, which is what open courses intend.

## R5. Time zones: correct by default, but the zone is never shown

- Stored as a UTC integer; shown with `userdate()` in the viewer's zone (`core_date::get_user_timezone()`: `forcetimezone`, else the user's `timezone`, else `$CFG->timezone`). Defaults: `forcetimezone = 99` (not forced).
- Weekly repeats are generated in the **creator's** zone (`calendar_event::update()`), so a repeating event keeps the creator's wall-clock time across daylight saving. A viewer in a zone with a different DST date sees it shift by an hour for those weeks. That is correct behaviour, but it needs saying in the quickstart (SC-002).
- **No calendar view shows the zone.** The time formats (`humandate`, `humantimeperiod`, `strftimetime`) have no `%Z`, and the `event_details` and `upcoming_mini` templates print no zone. FR-002 ("with the zone indicated") and the spec edge case ("the zone in use is always visible beside the time") are not met by core.
- Options: a template override in a child theme (allowed by constitution XI, but adds a theme), our own block or output hook, or relaxing FR-002 to "the learner's zone is shown on their profile and set at first login".

**Status**: decided (D5, D7). FR-002 is relaxed: the zone is set at first login and shown on the profile (R14), and `$CFG->timezone` is `UTC`. Showing the zone beside each time is revisited with 007.

## R6. Core sends no notification when an event changes

- No calendar message provider in `lib/db/messages.php`, no `message_send` in calendar code, and no core task sends reminders. `\core\task\calendar_cron_task` only refreshes iCal subscriptions.
- The app schedules its own **local** reminders before events (`CoreReminders`, `src/addons/calendar/services/calendar.ts`).
- `\core\event\calendar_event_created`, `_updated` and `_deleted` fire from `calendar_event::update()` and `delete()`, from the web and web services, **once per occurrence** of a series. Updated and deleted carry a snapshot of the row; created does not.
- So FR-006 needs our own code: a `local_ltuse` observer on those three events and a message provider, which resolves the affected users from `eventtype`, `courseid` and `groupid` and sends through the core message API. Since this was written, spec 001 turned push on (`site.yaml`: `message_airnotifier` enabled, row #25, Premium app plan), so the provider defaults to email and push. The observer must collapse a series into one message.

**Status**: not in dispute. Designed in R15.

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

**Status**: 011 adds an Upcoming events block to the default dashboard (R18). Removing Timeline stays with 007, which owns the dashboard's layout.

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

**Status**: decided (D4, D6). Booking is browser-only, co-bookers are hidden, and each mentor has one group in a dedicated office-hours course, kept in step with 003's assignments (R16). The scheduler's settings and one gap are in R19.

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
| `timezone` | server | `UTC` (D7): the zone for learners who have set none. A valid `admin_setting_servertimezone` value (`lib/adminlib.php:10967-11010`, `admin/settings/location.php:32`) |

All are read by the app through `tool_mobile`.

## R13. Smaller findings to carry into the plan

- **The category list is cached per session** (`calendar_categories`, `MODE_SESSION`, TTL 900 s, `lib/db/caches.php`), so a new enrolment can take up to 15 minutes to change which category events a learner sees.
- **Suspension keeps group membership.** With `enrol_cohort/unenrolaction = 3` (002), a learner who leaves a cohort is suspended, not unenrolled, and stays in the group (`enrol/cohort/locallib.php`). The calendar's course list uses active enrolments only, so the course drops out of their calendar; whether a direct `view.php?course=` or event URL still shows its group events is **open**.
- **Exported feeds outlive a change.** Anything a learner could see is copied into their external calendar and stays there, and `calendar_showicalsource = 1` adds the course or category name to each exported event.
- **The Communication API (`communication_customlink`, `communication_matrix`) is `MATURITY_ALPHA`** in 5.2. It is not a route to per-course meeting rooms.
- **Site managers can manage anyone's user events** (`calendar_can_manage_user_event()`, system `manageentries`). Personal events are private from other learners, not from the site team.

## R14. Setting the time zone at first login (D5, D7)

Paths are on `MOODLE_502_STABLE`, under `public/`; the app is `moodlehq/moodleapp` `main`.

**What core already gives:**
- **Web profile.** It shows the zone as a "Time zone" node (`lib/myprofilelib.php:179-182`), unless `timezone` is in `hiddenuserfields`, which we keep empty (`settings/groups.yaml`). The value is the identifier, such as `Africa/Nairobi`, from `core_date::get_user_timezone()`.
- **App profile.** The About page shows it too (`src/core/features/user/pages/about/about.html:86-90`). `'99'` is replaced by the site zone (`about.ts:114-117`).
- **Defaults.** A stored `99` means "server zone", and with `$CFG->timezone = UTC` it resolves to UTC (`lib/classes/date.php:170-178, :268-269`). `forcetimezone = 99` forces nothing (`:249`).
- **How a zone gets stored, by route:**

  | Route | What is stored |
  |---|---|
  | Add a user (`user/editadvanced.php:73`) | `99` |
  | `core_user_create_users` | `99`, the database default (`user/lib.php:46-120`; `lib/db/install.xml:895`) |
  | Upload users | Whatever the CSV's `timezone` column says. With no column, the form default, the literal server zone (`admin/tool/uploaduser/user_form.php:283-285`) |

  So "the zone is 99" cannot tell a learner who never chose from one who did.

**What core does not do:**
- `user_not_fully_set_up()` checks names, email and required custom profile fields, never the zone (`lib/moodlelib.php:3036-3071`).
- The app has no time zone control of its own. The only place to set one is `user/edit.php` (`user/editlib.php:309-316`).

**The supported extension points:**
- `\core_user\hook\after_login_completed` fires inside `complete_user_login()` (`lib/moodlelib.php:4151`), before `login/index.php` works out where to send the user. Redirecting from it would break that flow.
- The legacy callback `<plugin>_after_require_login()` runs on every `require_login()` (`lib/moodlelib.php:2427, :2444-2446, :2679-2681`). It is still live and has no hook replacement.
- **App users.** Web services call `require_login(..., preventredirect = true)` (`lib/external/classes/external_api.php:521`). When core throws `usernotfullysetup`, the app opens `user/edit.php` in its browser, and treats reaching `user/preferences.php` as done (`src/core/classes/sites/authenticated-site.ts:739-743`; `src/core/features/user/pages/complete-profile/complete-profile.ts:60-108`).

**Decision**: `local_ltuse` records a user preference, `local_ltuse_tzconfirmed`. Until it is set:
- On the web, `local_ltuse_after_require_login()` redirects the learner once per session to `local/ltuse/timezone.php`. That page has one time-zone menu, preset from the browser's zone, and saves through `user_update_user()`.
- In the app, the callback throws core's own `usernotfullysetup`. The app opens `user/edit.php`, and the time zone menu is on that form. A `\core\event\user_updated` observer sets the preference when the learner saves their own profile, and our page sets it directly.
- Never for: the site team, the publisher's web-service account (`ltcpublisher`), guests, a `loginas` session, or AJAX and service calls that the web redirect cannot reach.

The site team may also put a `timezone` column in a user upload. That does not set the preference, so the learner still confirms.

**Alternatives considered**:
- A required custom profile field "Confirm your time zone". It is core-only and uses the same path in the app, but the tick and the zone are separate, so a learner can tick and leave UTC. It also adds a field to every profile.
- Relying on `timezone == 99`. Uploaded users hold the literal zone, so it misses them.
- The site team sets the zone and nobody confirms it. That does not meet "set at first login".

**Principle XI**: the callback and the observer are supported extension points. Throwing core's `usernotfullysetup` from our callback reuses an error code core owns to reach the app's existing handling. It is listed in the plugin README, and quickstart V3 is re-run on every app or core upgrade.

**Verify** (V3): web, the redirect happens once and saving ends it; app, the login opens the profile form and saving returns to the app; the site team, `ltcpublisher` and `loginas` are never redirected.

## R15. Notifying a change or cancellation (FR-006)

Paths are on `MOODLE_502_STABLE`, under `public/`.

**The events:**
- `\core\event\calendar_event_updated` and `_deleted` carry `objectid` (the event id) and `other` = `repeatid`, `name`, `timestart` (`lib/classes/event/calendar_event_updated.php`, `calendar_event_deleted.php`, `validate_data`). `eventtype` is not in `other`, so it is read from the record snapshot.
- **Snapshots** (`calendar/lib.php`):
  - An update attaches the row after the change (`:668-685`).
  - A delete attaches the row from before the delete (`:712, :723-725`).
  - There is no "before" row for an update, so a message can say "changed", with the new time, but not what the old time was.
- **Which context** (`calendar_event::calculate_context()`, `:326-353`):

  | Event | Context |
  |---|---|
  | Site event | The front-page course, because `courseid = SITEID` (`calendar/classes/local/event/mappers/create_update_form_mapper.php:104-105`) |
  | Course event | Its course |
  | Group event | Its course |

**When they fire:**
- **A series** fires one event per occurrence. An edit of the whole series (`repeateditall`) does one bulk update and then fires N `updated` (`:624, :663-675`), and deleting a series fires N `deleted` (`:702-725, :763-771`).
- **Deleting a series' first event alone** promotes a new parent and fires N-1 `updated` events. In those, `other.repeatid` is the old parent and the snapshot holds the new one (`:728-746`). This is not a change the learner needs to hear about.
- **Unconditional updates.** Every `update()` fires `updated`, with no check that anything differs (`:597-685`). That includes:
  - module code re-saving its own events, such as the scheduler;
  - iCal subscription syncs (`:2386-2404`);
  - hiding or showing an event (`:879-913`).
- **A description with an embedded file** makes `core_calendar_submit_create_update_form` call `update()` a second time (`calendar/externallib.php:954, :974-979`). So such a create also fires one `updated`.
- **A privacy deletion** removes a user's events with `delete_records_list`, and fires nothing (`calendar/classes/privacy/provider.php:310-327, :694-703`).

**Queueing and messages:**
- **Adhoc tasks.** `queue_adhoc_task($task, $checkforexisting)` and `reschedule_or_queue_adhoc_task()` match an existing task on class, component and the exact `customdata` text (`lib/classes/task/manager.php:200-246, :257`).
- **Messages.** A provider in `db/messages.php`, with defaults for `popup`, `email` and `airnotifier`. `message_send()` (`lib/messagelib.php:59`) needs `notification = 1` for a non-core provider (`:96-105`). There are no message-API changes in 5.0–5.2 (`UPGRADING.md`).
- **Recipients:**
  - `get_enrolled_users($context, '', $groupid, 'u.*', null, 0, 0, true)` gives active enrolments, optionally in one group (`lib/enrollib.php:1664`).
  - `groups_get_members()` alone ignores enrolment status (`lib/grouplib.php:654`).
  - Site events have no "everyone" helper, so a recordset over active, confirmed, non-guest users is read in chunks.

**Decision**:
- **The observer.** It is registered with `internal => false`, so it runs only after the transaction commits. (Within one request the deferred observers still run in the same PHP process, so a per-request static set works.) It ignores a snapshot that is:
  - missing;
  - from a module or component, or from a subscription;
  - a `user` or `category` event;
  - a promotion artifact (`other.repeatid != snapshot.repeatid`);
  - now hidden (`visible = 0`). A hide cannot be told from an edit, because there is no "before" row. So showing an event again is announced as a change, which is accepted.
- **One task per series or event.** It calls `reschedule_or_queue_adhoc_task()` with a 2-minute delay. The key is the `repeatid`, or else the event id.
  - Within one request, the observer only buffers by key. One callback registered with `\core\shutdown_manager::register_function()` (`lib/classes/shutdown_manager.php:165`) queues one task per key at the end of the request, so a series and the double `update()` collapse into one message. The customdata is `{action, key, eventtype, courseid, groupid, name, firststart, actorid}`, where `firststart` is that first event's start. Across requests, `reschedule_or_queue_adhoc_task()` folds only identical customdata, which is enough for a quick re-edit of the same occurrence.
  - The task tells one date from a whole series by what remains. For a cancellation, rows still carrying the `repeatid` mean one date (`firststart`) was cancelled; none mean the series was. A change says "the series" when the request touched more than one occurrence, which the observer records as `scope`.
  - A delete does not touch a pending "changed" task for the same key. That task finds the event gone and sends nothing. Core has no public API to delete a queued adhoc task (`lib/classes/task/manager.php`).
  - The observer also watches `calendar_event_created`, but only to note the new id for the request. It then ignores an `updated` for an event created in the same request, so a create with an embedded file is not mistaken for a change. The `event` table has no `timecreated` to use instead (`lib/db/install.xml`).
- **The task.** For "changed", it re-reads the event or series and sends the new time (one occurrence, or "a weekly series from … "). For "cancelled", it sends the name it was given, because the row is gone.
  - Recipients: the site's active users, the course's active enrolments, or the group's active members.
  - The person who made the change is left out.
  - Each message comes from the no-reply user, carries `courseid` and links to the event in the calendar.
- **Accepted limits:**
  - No "was" time.
  - New events are not announced: FR-006 covers changes and cancellations only, and a new site event would otherwise email every account.
  - Privacy deletions are silent.

**Verify** (V8, V9):
- One message for an edit of a whole series, a single occurrence and a drag.
- Nothing for a scheduler booking, a hide, or a create with an image.
- A cancellation reaches only that course's or group's learners, and never the person who made it.
- Email and push both arrive, and the learner's preference turns either off.

## R16. Office hours: one course, one group per mentor (D6)

`mod_scheduler` v5.2-r1 was read from the plugins-directory zip (sha256 checked). Core is `MOODLE_502_STABLE`.

**How the scheduler filters slots:**
- It uses the activity's effective group mode (`scheduler.php:117`, `groups_get_activity_groupmode()`), not `bookingrouping`. In any mode except no groups, `get_slots_available_to_student()` keeps only slots whose teacher shares a group with the student (`classes/model/scheduler.php:753-788`).
- A student in no group sees no slots (`:779`). A teacher in no group offers to nobody. `bookingrouping` is "book for my whole group" (`:269-273`), which we do not use.
- Membership `component` is ignored, so memberships owned by our plugin behave like any other (R19).

**Group visibility** (`lib/grouplib.php:64-79, :331-347`; `group/lib.php:261-283`):
- `GROUPS_VISIBILITY_OWN` lets a member see the group but only their own membership. Members' lists are filtered by `group/classes/visibility.php:129-158`.
- `OWN` also forces `participation` and `enablemessaging` off.
- `GROUPS_VISIBILITY_NONE` drops the group from a student's own group list, which would empty their slot list. It must not be used.
- `moodle/course:viewhiddengroups` (teacher, editingteacher, manager) lets mentors see their groups.

**Membership and enrolment:**
- `groups_add_member($group, $user, 'local_ltuse', $itemid)` refuses a user who is not enrolled (`group/lib.php:41, :68-70`).
- `local_ltuse_allow_group_member_remove()` returning false protects the membership in the interface (`:155-190`).
- Enrolment is through one manual enrolment instance in the course: `enrol_user()`, `update_user_enrol()` and `unenrol_user()` (`lib/enrollib.php:2112, :2214, :2294`).
- `create_course()` (`course/lib.php:1768`) and `create_module()` (`:2463`, around `add_moduleinfo()`) create the course and the activity.

**Decision**: one course, `ltct:officehours` ("Mentor office hours"), in a declared category. Our tooling creates it, and it holds one scheduler activity, `ltct:officehours:scheduler`.
- **Course groups.** `groupmode = SEPARATEGROUPS` and `groupmodeforce = 1`.
- **Groups.** One group per mentor, `idnumber = ltct:mentor:<mentor user id>`, with visibility `OWN`.
  - Its name carries no person's name ("Office hours <n>"), because a mentor's name may be a protected identity (spec 016).
  - A learner with two mentors is in two groups.
- **Enrolment.**
  - A mentor is enrolled as `teacher` ("Course mentor") through the course's manual instance once they have a first mentee, and suspended when their last relationship ends. Suspension, not unenrolment, keeps their slot and appointment history.
  - A mentee is enrolled as `student` the same way, and suspended when their last mentor relationship ends.
- **Keeping it in step.**
  - 003's existing `role_assigned`, `role_unassigned` and `user_deleted` observers also call `\local_ltuse\officehours::sync_pair()`.
  - A scheduled reconcile task, `\local_ltuse\task\officehours_reconcile`, compares every user-context `mentor` assignment with the groups and memberships tagged `local_ltuse`, and repairs both, including a role changed by hand.
  - `site_config apply` creates the course and the activity, and `drift` reports them. Who is in them is Moodle data, never declared.
- **Why one course and not a scheduler in every course.** 003's relationship is independent of course enrolment, so a learner's office hours should not depend on which courses they and their mentor share. One place also means one link for the learner and one slot list for the mentor. And the course card on the learner's dashboard is the way in (Principle VI).

**Alternatives considered**:
- A scheduler in every shared course: the same mentor's slots repeated per course, and a learner sees nothing in a course their mentor does not teach.
- Spec 005's community course: not built yet, and a later spec may move the scheduler there.
- Open slots with no groups: Doug chose a group per mentor. "Offered openly" (US3-4) stays possible as a second scheduler with no groups, left out of 011.

**Verify** (V10–V12):
- A mentor sees their mentees' bookings.
- A learner sees only their mentors' slots.
- Two mentees of one mentor cannot see each other on the participants page or in the group.
- Ending a relationship removes the learner from the group and keeps the history.
- The reconcile task repairs a membership removed by hand.

## R17. Managers post events in organisation-only courses (D3)

- `moodle/calendar:manageentries` is course-context with `RISK_SPAM` (`lib/db/access.php:1314-1350`).
- **Course context alone** allows course events in that course. It also allows group events, but only with `accessallgroups` or membership of the group (`calendar/lib.php:3369-3395`).
- **It never allows a site event.** That needs `manageentries` in the front-page course (`calendar/lib.php:2069, :3241-3243`).
- **Editing and deleting** someone else's course event is allowed to anyone with `manageentries` in that course (`calendar_edit_event_allowed()`, `:1789-1880`). So when a manager leaves, the organisation's other managers keep the event editable (spec edge case).
- A privacy deletion of the creator still removes it (R4, R15).

**Decision**: `orgmanager` gains `moodle/calendar:manageentries: allow`. Under the open-courses change (002 amendment), the managers cohort is enrolled only in its own organisation's organisation-only courses, so this reaches exactly those courses. `orgmanager` stays without `accessallgroups`, so in practice managers post course events. `site_config.py`'s `ORGMANAGER_DENY` is unchanged. `validate` adds a rule that `orgmanager` may hold no calendar capability other than `manageentries`.

**Dependency**: this grant must not land before the open-courses change. While managers are still enrolled in shared courses, it would let them post a course event to every organisation in them. The 011 PR is therefore merged after 002's amendment, and V6 runs on that state.

**Verify** (V6): a test manager posts and repeats an event in their organisation-only course; they are refused a site event, an event in a shared course and an event in another organisation's course; a second manager edits the first manager's event.

## R18. Upcoming events on the dashboard (FR-003)

- The default dashboard is the `my_pages` row with `userid = null`, `name = '__default'` and `private = 1` (`my/lib.php:30-33, :44-61`).
- `blocks_add_default_system_blocks()` adds `calendar_month`, `recentlyaccesseditems`, `myoverview` and `timeline` to page type `my-index` with that row's id as the subpage (`lib/blocklib.php:2789-2830`).
- A block added the same way, through `block_manager::add_block()` (`:826`), reaches every learner who has not customised their dashboard.
- `my_reset_page_for_all_users()` (`my/lib.php:232`) would push it to everyone else, but it throws away each person's own layout.

**Decision**: `apply` ensures one `calendar_upcoming` block on the default dashboard, and `drift` reports it if it is missing. A learner who has customised their dashboard keeps their own. The calendar is always one tap away in the app's main menu. Timeline is left as it is, for 007.

**Verify** (V2): a new test learner's dashboard shows Upcoming events, with a site event, a course event and a group event, on the web.

## R19. Scheduler settings, permissions and one gap

- **Permissions** (`db/access.php`):
  - A non-editing teacher holds `attend` and `manage` and sees only their own slots (`scheduler_permissions.php:51-75`). `manageallappointments`, `canscheduletootherteachers` and `canseeotherteachersbooking` are editingteacher and above.
  - The student archetype holds `seeotherstudentsbooking`. With it, the slot list names other students who booked, using `fullname()` (`studentview.php:203-212`, `renderer.php:621-624`).
  - **Decision**: `roles.yaml` sets it to `inherit` for `student`, the same pattern as `mod/workshop:viewauthornames`. It also declares the teacher's three "other teachers" capabilities as `inherit`, so a hand grant shows as drift.
- **Instance values**, set by `apply` on `ltct:officehours:scheduler` from `moodle/site/office-hours.yaml`:

  | Value | Setting | Meaning |
  |---|---|---|
  | `maxbookings` | 1 | |
  | `schedulermode` | `onetime` | |
  | `guardtime` | 43200 (12 hours, plan decision 3) | It blocks both booking and cancelling inside it (`slot.php:189-193`) |
  | `allownotifications` | 0 | Off: `local_ltuse` sends every booking message instead (R20). Reminders do not depend on it (`classes/task/send_reminders.php:57-90`) |
  | `usebookingform` | 0 | |
  | `staffrolename` | `''` | |
  | `grade` | 0 | |

- **Site settings**: `mod_scheduler/groupscheduling = 0`, which hides "book for the whole group". The others stay at their defaults and are declared: `showemailplain 0`, `mixindivgroup 1`, `maxstudentlistsize 200`, `uploadmaxfiles 5`, `revealteachernotes 0`.
- **Calendar**:
  - A booking writes one user event for each side: `courseid = 0`, `modulename = scheduler`, eventtype `SSstu:`/`SSsup:`. The event's name carries the other side's `fullname()` (`classes/model/slot.php:358-454`).
  - An unbooked slot writes no event.
  - Spec 016 re-saves these when a protection level changes.
  - R15's change observer ignores them. R20's booking observer handles them instead.
- **Housekeeping**: `purge_unused_slots` deletes past unbooked slots every 5 minutes, and `send_reminders` runs hourly for slots with a reminder date.
- **Gap: the group check is display-only.** `scheduler_book_slot()` checks only that the slot belongs to this scheduler and is open (`studentview.controller.php:45-52`), so a crafted request can book another mentor's slot. The mentor then sees an unexpected booking with the learner's name and can remove it, which tells the learner.
  - **Decided** (plan decision 4, accepted 2026-10-02): accept it and say so in the README. Closing it would need our code to cancel appointments through the scheduler's internal classes, which would be another Principle XI exception.
- **Privacy**: the provider exports and deletes slots and appointments (`classes/privacy/provider.php:48-91, :227-500`), which meets FR-012.

## R20. Booking messages to both sides (FR-007, decided 2026-10-02)

**The decision** (PR #85): a booking, a cancellation or a change of time is emailed to both the mentee and the mentor. Whoever made it gets a confirmation, and the other side gets a notice.

**What the scheduler sends by itself** (v5.2-r1, only while `allownotifications` is on):

| Action | Who is told | Where |
|---|---|---|
| A learner books | The mentor (`applied`) | `studentview.controller.php:116-131` |
| A learner cancels | The mentor (`cancelled`) | `:300-317` |
| A mentor uses "revoke all" | Each learner (`teachercancelled`) | `teacherview.controller.php:296-325` |

So these are never confirmed to the person who acted, and two mentor actions send nothing:
- A mentor deleting a booked slot (`scheduler_action_delete_slots()`, `:229-236`).
- A mentor editing a slot's time, or removing one learner, in the slot form (`slotforms.php:508-588`).

The provider `bookingnotification` declares no defaults (`db/messages.php:27-41`).

**What every booking change does to the calendar** (`classes/model/slot.php:388-500`): `save()` rewrites the per-user events through core's calendar API.
- An event is updated for each student who stays.
- An event is created for a new one (`calendar_event::create()`).
- `calendar_event::delete()` is called for one who left.

Each of these fires core's `calendar_event_created`, `_updated` or `_deleted`. The learner's event, `SSstu:<slotid>`, carries `userid` (the learner), `timestart`, `timeduration` and `instance` (the scheduler id).

The exceptions:
- **A deleted slot.** `delete()` and `delete_all_appointments()` call `clear_calendar()`, a raw `delete_records('event', …)` that fires nothing (`:330-344, :376-381`). The plugin's own `\mod_scheduler\event\slot_deleted` fires just before (`teacherview.controller.php:234`). It carries `objectid` (the slot), `relateduserid` (the teacher) and a `scheduler_slots` snapshot (`classes/event/slot_base.php`).
- **Updates that change no time.** Updates fire on every save, including a note edit and 016's re-save of a renamed user. The old time is not in the event (R15).

**Decision**:
- `allownotifications` is set to `0`, so the scheduler sends no booking messages. `local_ltuse` sends them all, so there is one sender, no duplicates and one wording. The scheduler's reminders are unaffected: `send_reminders` does not check `allownotifications`.
- **A small `local_ltuse` table**, `local_ltuse_booking`, holds `eventid`, `slotid`, `learnerid`, `mentorid`, `timestart` and `timeduration`. It is the "before" that core does not keep.

How each event is handled. The observer acts only on `modulename = scheduler` events whose `eventtype` starts `SSstu:` and whose `instance` is the `ltct:officehours:scheduler` instance.

| Event | What we do | Messages |
|---|---|---|
| `calendar_event_created` | Read `mentorid` from `scheduler_slots.teacherid` by the slot id, then insert a row | **Booked** to the learner and the mentor |
| `calendar_event_updated` | Compare `timestart` and `timeduration` with the row. If they differ, update the row | **Changed** to both. Otherwise nothing |
| `calendar_event_deleted` | Delete the row | **Cancelled** to both |
| `\mod_scheduler\event\slot_deleted` | For each row with that `slotid`, delete it. The observer runs at trigger time, before the slot is removed | **Cancelled** to each learner. One message to the mentor, naming how many bookings were cancelled |

The rules for every message:
- **Who.** It names the actor: "You booked…" to the actor, "<name> booked…" to the other.
- **The time.** It is given in each recipient's own zone, with the zone named, because email has no other way to show it (D5).
- **Timing.** It is sent at once, because one booking is two messages, not a site-wide fan-out.
- **The provider.** `bookingnotice`, with email, popup and push enabled by default. Each person can turn any of them off.
- **Names.** These go through `fullname()`, so spec 016's protected display applies.

**Housekeeping**: the hourly `officehours_reconcile` task (R16) also deletes rows whose calendar event no longer exists. That covers a course or module deleted without per-slot events.

**Principle XI**: the observer reads `scheduler_slots.teacherid` by primary key. That is a raw read of a third-party table, used because the plugin has no API that returns a slot's teacher without loading its internal model classes. It is listed in the README, and V13 is re-run on every re-pin. The `SSstu:`/`SSsup:` eventtype convention is plugin internals too, and is covered by the same check.

**Alternatives considered**:
- **Keep `allownotifications` on and add only the missing messages.** The mentor's "revoke all" sends the learner a message, and our calendar-event path then sends another. The two can't be told apart, so learners would get duplicates.
- **Use only the scheduler's `booking_added` and `booking_removed`.** They fire only for the learner's own actions, never for the mentor's edits or deletions.

**Verify** (V13):
- A learner booking, cancelling, and being moved, removed or deleted by the mentor each produce exactly one message to the learner and one to the mentor, with the right wording and zone.
- A note edit and a 016 re-save produce none.
- Reminders still arrive.
