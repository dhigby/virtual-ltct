# Tasks: Events, office hours and live sessions

**Input**: Design documents from `specs/011-events-calendar/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md (R1–R19), data-model.md, contracts/declaration.md, contracts/local-ltuse.md, quickstart.md (V1–V18)

**Tests**: Included. The plan's Testing section names `pytest`, three PHP harnesses, PHPUnit and the instance checks V1–V18, and each research **Verify** line blocks its story. Instance checks use `ltct-test-*` accounts only. Their evidence (screenshots, `.ics` files, emails) stays outside the repo and is recorded in the PR (quickstart.md).

**Organization**: Tasks are grouped by user story, so each story can be built and tested on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel (different files, no dependency on an incomplete task)
- **[Story]**: the user story this task belongs to (US1–US4)

## Path Conventions

- Site declarations: `moodle/site/`
- Plugin: `moodle/local_ltuse/`
- Site config CLI: `scripts/site_config.py`
- Repo tests and harnesses: `tests/`
- CI: `.github/workflows/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: The dependency gate, the harness skeletons and the plugin version.

- [ ] T001 Confirm that the open-courses change (spec 002 amendment, branch `002-open-courses`) has merged to `main`, then merge `main` into `011-events-calendar`. Record the merge commit in the PR. T023 must not merge before this (plan decision 6, research R17).
- [ ] T002 [P] Create harness skeletons, following `tests/profile_access_harness.php`:
  - `tests/calendar_notify_harness.php`
  - `tests/timezone_gate_harness.php`
  - `tests/officehours_harness.php`
  - `tests/booking_notice_harness.php`
- [ ] T003 [P] Add the four harnesses to `.github/workflows/site-config.yml`, beside the existing `profile_access_harness.php` and `recognition_harness.php` runs.
- [ ] T004 Bump `$plugin->version` in `moodle/local_ltuse/version.php`, and set the matching `local_ltuse` `version` in `moodle/site/site.yaml`. `requires` and `supported` stay at 5.2 (Principle XI).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The plumbing every story's declarations pass through.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T005 Register the four new declaration files in `scripts/site_config.py`: `moodle/site/office-hours.yaml`, `moodle/site/dashboard.yaml`, `moodle/site/settings/calendar.yaml` and `moodle/site/settings/scheduler.yaml`. Absent files are allowed. Render the two new payload arrays, `officehours` and `dashboard`, after spec 013's, exactly as in `contracts/declaration.md` "Payload arrays".
- [ ] T006 Wire the `officehours` and `dashboard` arrays through `moodle/local_ltuse/classes/siteconfig/inspector.php`, `applier.php` and `drift.php` to two new classes, `classes/siteconfig/officehours.php` and `classes/siteconfig/dashboard.php`. Both are created here with empty `apply()`, `inspect()` and `drift()` that report nothing.
- [ ] T007 [P] Add synthetic fixtures for the four files to `tests/test_site_config.py`, and a test that `validate` passes when all four are absent.

**Checkpoint**: Foundation ready. The user stories can now proceed in parallel.

---

## Phase 3: User Story 1 - A learner sees what is coming up, for them (Priority: P1) 🎯 MVP

**Goal**: A learner sees the site's events, their courses' and their groups' events, and no others, at their own local time. They see them on the dashboard and in the app, and they can export them. Their zone is confirmed at first login and shown on their profile (FR-001 to FR-004, D1, D5, D7).

**Independent Test**: quickstart V1, V2, V3, V4, V5 and V7. Research R5, R7, R8, R14 and R18 must have passed their Verify lines.

### Tests for User Story 1

- [ ] T008 [P] [US1] Cover `timezone_gate::decide()` in `tests/timezone_gate_harness.php`, from the inputs in `contracts/local-ltuse.md` "Time zone gate":
  - **`none`** for each of: not logged in, guest, `loginas`, site admin or `moodle/site:config`, `ltcpublisher`, the preference set, the script `timezone.php`, `user/edit.php`, `login/*` or `admin/*`, and `AJAX_SCRIPT`;
  - **`redirect`** for a web request not yet asked this session;
  - **`throw`** when `$preventredirect` is set.
- [ ] T009 [P] [US1] Validation tests in `tests/test_site_config.py`.
  - For `settings/calendar.yaml`:
    - `timezone` must be a valid zone identifier (`UTC` passes, `Mars/Base` fails);
    - `forcetimezone` must be `99`;
    - `enablecalendarexport`, `calendar_customexport` and `calendar_adminseesall` must be present.
  - For `dashboard.yaml`:
    - "names no block twice";
    - `block` must be a core block name.

### Implementation for User Story 1

- [ ] T010 [US1] Create `moodle/site/settings/calendar.yaml`, with `rows: [21]` and a `why` per setting citing R12 and D7:
  - `enablecalendarexport: 1`
  - `calendar_customexport: 1`
  - `calendar_adminseesall: 0`
  - `timezone: UTC`
  - `forcetimezone: 99`

  Do not declare `calendar_lookahead`, `calendar_maxevents` or `calendar_exportlookahead`.
- [ ] T011 [US1] Validate `settings/calendar.yaml` in `scripts/site_config.py`. The zone list is `zoneinfo.available_timezones()` plus `UTC`, and `forcetimezone` must be `99`.
- [ ] T012 [P] [US1] Create `moodle/site/dashboard.yaml` with `rows: [21]` and `default_blocks: [{block: calendar_upcoming, region: side-post, why: …}]` (R18, FR-003). Validate it in `scripts/site_config.py`: "additive: `apply` never removes a block it did not declare".
- [ ] T013 [US1] Implement `moodle/local_ltuse/classes/siteconfig/dashboard.php`:
  - **`apply`**: for each declared block not already on the default dashboard, call `block_manager::add_block($block, $region, 0, false, 'my-index', <default my_pages id>)` at system context. The default page is `my_pages` with `userid null`, `name '__default'` and `private 1`.
  - **`drift`**: report `missing` when the block is absent.
  - Never call `my_reset_page_for_all_users()`, and never remove a block.
- [ ] T014 [P] [US1] Implement the pure `moodle/local_ltuse/classes/timezone_gate.php` `decide(): string` (`none` | `redirect` | `throw`), with no Moodle calls, per `contracts/local-ltuse.md`.
- [ ] T015 [US1] Add `local_ltuse_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect)` to `moodle/local_ltuse/lib.php`. It collects the gate's inputs and calls `decide()`.
  - On `redirect`: once per session (a `$SESSION` flag), go to `/local/ltuse/timezone.php?returnurl=<$FULLME>`.
  - On `throw`: `throw new moodle_exception('usernotfullysetup')`.
- [ ] T016 [US1] Create the page `moodle/local_ltuse/timezone.php` and `templates/timezone.mustache`, with their strings in `lang/en/local_ltuse.php`.
  - The page calls `require_login()` and shows one select built from `core_date::get_list_of_timezones()`. A small inline script presets it to the browser's zone; without one, it shows the user's current zone.
  - On save:
    - `user_update_user((object)['id' => $USER->id, 'timezone' => $tz], false, true)`;
    - `set_user_preference('local_ltuse_tzconfirmed', time())`;
    - redirect to `returnurl`, local URLs only.
  - Strings carry no level vocabulary and no "certified".
- [ ] T017 [US1] In `moodle/local_ltuse/classes/observer.php` and `db/events.php`, observe `\core\event\user_updated`. When `relateduserid == userid`, set `local_ltuse_tzconfirmed`, so the app's `user/edit.php` route ends the gate.
- [ ] T018 [US1] Add `\core_privacy\local\request\user_preference_provider` to `moodle/local_ltuse/classes/privacy/provider.php`:
  - `export_user_preferences()` exports `local_ltuse_tzconfirmed`;
  - metadata gains `add_user_preference('local_ltuse_tzconfirmed', …)`.
- [ ] T019 [US1] Add Principle XI exception 1 to `moodle/local_ltuse/README.md`: `usernotfullysetup` is thrown from `after_require_login`, and V3 is re-run on every core or app upgrade.
- [ ] T020 [US1] Instance checks V1 (calendar and dashboard parts), V2, V3, V4, V5 and V7 per `specs/011-events-calendar/quickstart.md`. Record the results in the PR.

**Checkpoint**: US1 works and is testable on its own.

---

## Phase 4: User Story 2 - An organisation manager schedules events for their own people (Priority: P1)

**Goal**:
- Managers create, edit, repeat and cancel events in their organisation-only courses, and nowhere else (FR-005, D3).
- Each change or cancellation reaches the affected people once, by email and push (FR-006, US2-3).
- An organisation-wide message is a core bulk action (D2).

**Independent Test**: quickstart V6, V8, V9 and V14. Research R15 and R17 must have passed their Verify lines. **Depends on T001.**

### Tests for User Story 2

- [ ] T021 [P] [US2] Cover `calendar_notify::decide()` and `key()` in `tests/calendar_notify_harness.php`.
  - `decide()` is false for:
    - no snapshot;
    - `modulename`, `component` or `subscriptionid` set;
    - `eventtype` of `user`, `category` or any other type outside `site`, `course` and `group`;
    - `other.repeatid != snapshot.repeatid`;
    - `visible = 0` on update;
    - an id created in the same request.
  - It is true otherwise.
  - `key()` is `r<repeatid>` when `repeatid` is non-zero, and else `e<eventid>`. A cancellation outranks a change for the same key, and a second event of a key sets `scope = series`.
- [ ] T022 [P] [US2] Cover the rule "`orgmanager` may hold no `moodle/calendar:*` capability except `manageentries`" in `tests/test_site_config.py`.

### Implementation for User Story 2

- [ ] T023 [US2] In `moodle/site/roles.yaml`, give `orgmanager` `moodle/calendar:manageentries: allow`. Rewrite its description to include "posts events in its organisation's own courses", and its `why` to cite #21, D3 and R17. **Merge only after T001.**
- [ ] T024 [US2] Add the orgmanager calendar rule to `validate` in `scripts/site_config.py`, beside `_check_orgmanager`. `ORGMANAGER_DENY` is unchanged.
- [ ] T025 [P] [US2] Implement the pure `moodle/local_ltuse/classes/calendar_notify.php`: `decide()`, `key()`, and the buffer-merge rule (cancellation outranks change, and a second occurrence sets `series`).
- [ ] T026 [US2] Observe `\core\event\calendar_event_created`, `_updated` and `_deleted` in `moodle/local_ltuse/db/events.php`, with `'internal' => false`. In `classes/observer.php`:
  - **created**: record the id in a per-request static set;
  - **updated and deleted**: buffer by key through `calendar_notify`.
  - The first buffered event calls `\core\shutdown_manager::register_function()` once. That callback queues one `event_change_notice` per key with `reschedule_or_queue_adhoc_task()`, `component = local_ltuse`, a next run of `time() + 120`, and customdata `{action, scope, key, eventtype, courseid, groupid, name, firststart, actorid}`.
  - The observer never throws into core.
- [ ] T027 [P] [US2] Create `moodle/local_ltuse/db/messages.php` with the `eventchange` provider, `popup`, `email` and `airnotifier` each `MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED`. Add `messageprovider:eventchange` and the message strings to `lang/en/local_ltuse.php`.
- [ ] T028 [US2] Implement `moodle/local_ltuse/classes/task/event_change_notice.php` (an adhoc task), per `contracts/local-ltuse.md`.
  - **Recipients**:
    - `site`: active, confirmed, non-guest, non-deleted users, as a recordset in chunks of 500. The task re-queues itself with an offset.
    - `course`: `get_enrolled_users($ctx, '', 0, 'u.id', null, 0, 0, true)`.
    - `group`: the same with `$groupid`.
  - Remove `actorid` from the recipients.
  - **`changed`**: send nothing if the event is gone or hidden. Otherwise re-read the event or series and report the new start with `userdate()` in each recipient's zone.
  - **`cancelled`**: say "one date (`firststart`)" if rows with the `repeatid` remain, and "the series" otherwise.
  - **The message**: `userfrom = core_user::get_noreply_user()` and `notification = 1`. `courseid` is the event's course, or `SITEID`. `contexturl` is `/calendar/view.php?view=day&time=<timestart>`, or `?view=upcoming` for a cancellation. It never names a person.
- [ ] T029 [US2] Add `link_subsystem('core_message', …)` to the metadata in `moodle/local_ltuse/classes/privacy/provider.php`, for the `eventchange` notices.
- [ ] T030 [P] [US2] Add site-team recipes to `moodle/site/README.md`:
  - post a site event;
  - how a manager posts in their organisation-only course;
  - an organisation-wide message, through Site administration, Users, Bulk user actions, filtered by cohort `ltct:org:<key>`, then "Send a message" (D2).
- [ ] T031 [US2] Instance checks V6, V8, V9 and V14 per quickstart.md, on the state after T001. Record the results in the PR. SC-004 needs 2–3 real organisation managers before US2 is closed (constitution X).

**Checkpoint**: US1 and US2 both work independently.

---

## Phase 5: User Story 3 - A learner books time with their mentor (Priority: P2)

**Goal**:
- Mentors offer slots in the `ltct:officehours` course.
- A learner sees and books only their mentors' slots, in the browser.
- Both calendars show the booking.
- No learner sees who else booked (FR-007, FR-008, D4, D6).

**Independent Test**: quickstart V10, V11, V12, V13, V16 and V17. Research R16 and R19 must have passed their Verify lines.

### Tests for User Story 3

- [ ] T032 [P] [US3] Cover `officehours_plan::diff(assignments, memberships, enrolments)` in `tests/officehours_harness.php`:
  - a new pair gives group create, two adds and two enrols;
  - a pair that ended gives a remove and a learner suspend;
  - a mentor's last pair gives a mentor suspend too;
  - a learner with two mentors is in two groups;
  - a suspended person who returns is reactivated;
  - a member tagged `local_ltuse` with no assignment is removed;
  - a hand-removed member is re-added;
  - output holds counts only.
- [ ] T033 [P] [US3] Cover `officehours::sync_pair()`, `sync_user()` and `reconcile()` in `moodle/local_ltuse/tests/officehours_test.php` (PHPUnit, synthetic users only). Assert:
  - the group visibility is `GROUPS_VISIBILITY_OWN`, never `GROUPS_VISIBILITY_NONE`;
  - the group name holds no part of the user's name;
  - memberships carry `component = local_ltuse` and `itemid = <mentorid>`;
  - enrolments use the course's single manual instance.
- [ ] T034 [P] [US3] Validation tests for `office-hours.yaml` in `tests/test_site_config.py`, one case per rule in data-model.md:
  - `course.idnumber` must be `ltct:officehours`, and `scheduler.idnumber` must be `ltct:officehours:scheduler`;
  - `course.fullname` is ≤ 254 characters and `shortname` ≤ 100;
  - `course.category` must be a key in `organisations.yaml` `categories`;
  - `course.groupmode` and `groupmodeforce` must be exactly `1` and `1`, and `scheduler.groupmode` must be 1;
  - `scheduler.maxbookings` is 1–5;
  - `scheduler.schedulermode` is `onetime` or `oneonly`;
  - `scheduler.guardtime_hours` is 0–168;
  - `scheduler.allownotifications` must be 0, because `local_ltuse` sends every booking message (R20);
  - `scheduler.defaultslotduration` is 5–240 minutes;
  - `scheduler.usebookingform` and `grade` must be 0;
  - `groups.name_template` must contain `{n}` and must not contain `{name}`, `{firstname}` or `{lastname}`;
  - `mod_scheduler` is pinned whenever `office-hours.yaml` exists;
  - `student` does not allow `mod/scheduler:seeotherstudentsbooking`.

### Implementation for User Story 3

- [ ] T035 [US3] Pin `mod_scheduler` in `moodle/site/site.yaml`:
  - version `2026080400`
  - sha256 `928ea42b8a4835dcc2c398d2c069cd198c8d41e3130883c2299f36e090a97826`
  - URL `https://marketplace.moodle.com/api/plugins/mod_scheduler/versions/2026080400/download`
  - `why` citing #21, R9 and V10, saying "re-run V10 on every re-pin".
- [ ] T036 [P] [US3] Create `moodle/site/settings/scheduler.yaml` with `rows: [21]`:
  - `mod_scheduler/groupscheduling: 0`
  - `mod_scheduler/showemailplain: 0`
  - `mod_scheduler/mixindivgroup: 1`
  - `mod_scheduler/maxstudentlistsize: 200`
  - `mod_scheduler/uploadmaxfiles: 5`
  - `mod_scheduler/revealteachernotes: 0`

  Make `site_config` report these as `missing`, not `changed`, until the plugin is installed.
- [ ] T037 [P] [US3] Add `{key: mentoring, name: LTC Mentoring, why: …}` to `categories` in `moodle/site/organisations.yaml`.
- [ ] T038 [US3] Create `moodle/site/office-hours.yaml` with every field in data-model.md, and `why` citing #21, D6 and R16:
  - `fullname: Mentor office hours`, `shortname: ltct-officehours`, `category: mentoring`
  - `groupmode: 1`, `groupmodeforce: 1`
  - `maxbookings: 1`, `schedulermode: onetime`, `guardtime_hours: 12`, `allownotifications: 0`, `defaultslotduration: 30`, `usebookingform: 0`, `grade: 0`
  - `name_template: "Office hours {n}"`

  `guardtime_hours: 12` and `allownotifications: 0` follow plan decisions 3 and 5, both decided on 2026-10-02.
- [ ] T039 [US3] In `moodle/site/roles.yaml`:
  - `student`: `mod/scheduler:seeotherstudentsbooking: inherit`;
  - `teacher`: `mod/scheduler:manageallappointments`, `mod/scheduler:canscheduletootherteachers` and `mod/scheduler:canseeotherteachersbooking`, each `inherit`, with a `why` citing #21, D4 and R19.
- [ ] T040 [US3] Implement the `office-hours.yaml` validation (T034's rules) and the `officehours` array rendering in `scripts/site_config.py`. `guardtime_hours` is rendered to seconds, and the category is resolved by its `ltct:` idnumber.
- [ ] T041 [P] [US3] Implement the pure `moodle/local_ltuse/classes/officehours_plan.php` `diff()`, per data-model.md "Invariant".
- [ ] T042 [US3] Implement `moodle/local_ltuse/classes/officehours.php`: `sync_pair()`, `sync_user()` and `reconcile()`, per `contracts/local-ltuse.md` "Office-hours sync".
  - **Enrolment**: `enrol_user()` and `update_user_enrol()` on the course's one manual instance, with `teacher` for mentors and `student` for mentees. Suspend, never unenrol.
  - **Groups**: `groups_create_group()` with `idnumber ltct:mentor:<id>`, visibility `GROUPS_VISIBILITY_OWN`, and the name from the template with the next free `{n}`.
  - **Members**: `groups_add_member($g, $u, 'local_ltuse', $mentorid)` and `groups_remove_member()`.
  - It reuses 003's raw read of `role_assignments` ⋈ `context`. Logs hold counts only.
- [ ] T043 [US3] Implement `moodle/local_ltuse/classes/siteconfig/officehours.php`.
  - **`apply`**:
    - `create_course()` or `update_course()` for `ltct:officehours`;
    - `create_module()`, or an update of the instance and course-module group mode, for `ltct:officehours:scheduler`, passing `staffrolename = ''`;
    - ensure one manual instance named `ltct:officehours`;
    - then `officehours::reconcile()`, reporting counts.
  - **`drift`**: `missing`, `changed`, and `extra`/`ambiguous`, per `contracts/declaration.md`.
  - Never delete the course, the activity, a group, a slot or an appointment.
- [ ] T044 [US3] Add the scheduled task `moodle/local_ltuse/classes/task/officehours_reconcile.php` and `db/tasks.php`: hourly, minute `R`, calling `officehours::reconcile()`.
- [ ] T045 [US3] Extend the existing `role_assigned`, `role_unassigned` and `user_deleted` handlers in `moodle/local_ltuse/classes/observer.php`. After 003's contact handling, call `officehours::sync_pair()`, or `sync_user()` for a deleted user. A failure goes to `debugging()` and never throws.
- [ ] T046 [US3] Add `local_ltuse_allow_group_member_remove($itemid, $groupid, $userid)` to `moodle/local_ltuse/lib.php`, returning false.
- [ ] T047 [P] [US3] Cover `booking_notice::decide()` in `tests/booking_notice_harness.php`:
  - `created` gives `booked`;
  - `updated` with the same `timestart` and `timeduration` as the row gives null;
  - `updated` with either one changed gives `changed`;
  - `deleted` gives `cancelled`;
  - `slot_deleted` gives `cancelled` for each row;
  - an event that is not `modulename = scheduler`, not `SSstu:`, or from another scheduler instance gives null;
  - the wording is "You …" to the actor and a notice naming the actor to the other side.
- [ ] T048 [US3] Add the table `local_ltuse_booking` in `moodle/local_ltuse/db/install.xml` and `db/upgrade.php`. Columns: `id`, `eventid` (unique), `slotid` (indexed), `learnerid` (indexed), `mentorid` (indexed), `timestart`, `timeduration`, `timecreated`.
- [ ] T049 [US3] Implement `moodle/local_ltuse/classes/booking_notice.php`, per `contracts/local-ltuse.md` "Booking notices".
  - `decide()` is pure.
  - `from_calendar()` and `from_slot_deleted()` insert, update or delete the row.
  - They read `mentorid` from `scheduler_slots.teacherid` by the slot id in `SSstu:<slotid>`.
  - They send one `bookingnotice` each to the learner and the mentor at once. A slot deletion sends one summary to the mentor.
  - Each message gives the time with `userdate()` in the recipient's zone, followed by the zone name.
  - Each comes from `core_user::get_noreply_user()`, with `notification = 1`.
- [ ] T050 [US3] Route the scheduler's events to `booking_notice` in `moodle/local_ltuse/db/events.php` and `classes/observer.php`:
  - the three `\core\event\calendar_event_*` events, for `modulename = scheduler`, an `SSstu:` eventtype and the `ltct:officehours:scheduler` instance;
  - `\mod_scheduler\event\slot_deleted`, observed with the default `internal`, so it runs before the slot is removed.

  The observer never throws into core.
- [ ] T051 [US3] Add the `bookingnotice` provider (popup, email and airnotifier, each `MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED`) to `moodle/local_ltuse/db/messages.php`, creating the file if US2's T027 has not. Add its strings to `lang/en/local_ltuse.php`: booked, changed, cancelled and cancelled-by-mentor, each as "you" and as a notice.
- [ ] T052 [US3] Extend `moodle/local_ltuse/classes/privacy/provider.php` for `local_ltuse_booking`:
  - metadata through `add_database_table()`;
  - export, and delete for a user or a user list, as learner or mentor.
- [ ] T053 [US3] Make `officehours::reconcile()` delete `local_ltuse_booking` rows whose calendar event no longer exists, sending no message. Make `sync_user()` remove a deleted user's rows, in `moodle/local_ltuse/classes/officehours.php`.
- [ ] T054 [US3] Update `moodle/local_ltuse/README.md` with Principle XI exception 2 (the scheduler's display-only group filter, V10 on every re-pin) and exception 3 (the raw read of `scheduler_slots.teacherid` and the `SSstu:` convention, V13 on every re-pin). Also list the new observers and tasks, the `local_ltuse_booking` table, and the reuse of 003's raw read.
- [ ] T055 [P] [US3] Add a section "How office hours work" to `moodle/site/README.md`:
  - groups are automatic, so never edit them by hand;
  - the relationship is set on the learner's profile (003);
  - the cancellation window;
  - the booking gap from plan decision 4.
- [ ] T056 [US3] Instance checks V10, V11, V12, V13, V16 and V17 per quickstart.md. Record the results in the PR. SC-003 needs 2–3 real partner learners before US3 is closed (constitution X).

**Checkpoint**: US1, US2 and US3 work independently.

---

## Phase 6: User Story 4 - A learner joins a live session, or catches up if they could not (Priority: P3)

**Goal**: A live-session event carries one join link that works on the web and in the app, and later carries a follow-up. No course depends on attending (FR-009, FR-010, FR-013).

**Independent Test**: quickstart V15.

- [ ] T057 [P] [US4] Add a "Post a live session" recipe to `moodle/site/README.md`:
  - put the join link in the event **description**, never in Location, which the app turns into a maps link (R10);
  - add the follow-up note or recording link to the same event afterwards;
  - never make a live session a course completion condition (FR-010);
  - BigBlueButton stays disabled (FR-013).
- [ ] T058 [US4] Instance check V15 per quickstart.md. Record the result in the PR.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T059 [P] Link 011's declaration contract from `specs/001-site-config-as-code/contracts/declaration.md`, as 002, 004 and 013 are linked.
- [ ] T060 [P] Update row #21 in `moodle/REQUIREMENTS.md` to built and verified, citing V1–V17 (constitution X).
- [ ] T061 Run `python -m pytest tests/`, the three new harnesses, and `python scripts/site_config.py validate`. Then run `apply` and `drift` on the instance. `drift` must say `No differences.` (V1 in full).
- [ ] T062 Once spec 016 (PR #84) has landed, run V18 with 016's V17, and record the result in both PRs.
- [ ] T063 Fill in spec 011's header branch and Status once the stories close. The maintainer marks completion.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: no dependencies. T001 also gates T023.
- **Foundational (Phase 2)**: depends on Setup, and blocks every story.
- **US1 (Phase 3)**: depends on Phase 2 only.
- **US2 (Phase 4)**: depends on Phase 2. T023 and T031 also depend on T001 (the open-courses change has merged).
- **US3 (Phase 5)**: depends on Phase 2. It uses spec 003's observers, which are already on `main`. It is independent of US1 and US2, although V13 sees US2's notices if US2 is built.
- **US4 (Phase 6)**: documentation and one check. It can run any time after Phase 2.
- **Polish (Phase 7)**: after the stories it reports on. T062 waits on spec 016.

### Within Each User Story

- Harness, pytest and PHPUnit tests come first, and fail before the implementation.
- Pure classes come before the classes that call Moodle. Declarations come before the `site_config` validation that reads them.
- The instance checks close the story.

### Parallel Opportunities

- T002 and T003, and T007 inside Phase 2.
- US1: T008, T009, T012 and T014.
- US2: T021, T022, T025, T027 and T030.
- US3: T032, T033, T034, T036, T037, T041, T047 and T055.
- After Phase 2, the four stories touch mostly different files. `observer.php`, `db/events.php`, `db/messages.php`, `lib.php`, `roles.yaml`, `privacy/provider.php` and both READMEs are shared, so edit them in sequence.

---

## Parallel Example: User Story 1

```text
T008  tests/timezone_gate_harness.php
T009  tests/test_site_config.py (calendar + dashboard cases)
T012  moodle/site/dashboard.yaml
T014  moodle/local_ltuse/classes/timezone_gate.php
```

## Parallel Example: User Story 3

```text
T032  tests/officehours_harness.php
T033  moodle/local_ltuse/tests/officehours_test.php
T034  tests/test_site_config.py (office-hours cases)
T036  moodle/site/settings/scheduler.yaml
T037  moodle/site/organisations.yaml
T041  moodle/local_ltuse/classes/officehours_plan.php
T047  tests/booking_notice_harness.php
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phases 1 and 2.
2. Phase 3 (US1): the calendar settings, UTC, the dashboard block and the time zone gate.
3. **Stop and validate** with V1–V5 and V7. The calendar already works for every event the site team and course mentors post.

### Incremental Delivery

1. US1 is the MVP.
2. US2 comes once the open-courses change has merged (T001): managers post, and changes are announced.
3. US3 is office hours and their booking messages (decisions 1, 3, 4 and 5, decided 2026-10-02).
4. US4 is the live-session recipe.
5. Polish: row #21, the 001 contract link, and V18 once 016 lands.

## Notes

- **No learner data in git**: no name, booking, membership or relationship is committed, and neither is any screenshot or `.ics` file (Principle III).
- **Maintainer decisions** (plan.md): 1, 3, 4 and 5 were decided on 2026-10-02. Decision 2, whether the first-login prompt stays or the profile alone is enough, is still to be confirmed, and T014–T017 and T019 are not closed until it is.
- **Commit** after each task or logical group, with the attribution line from the session reminder.
