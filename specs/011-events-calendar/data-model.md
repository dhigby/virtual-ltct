# Data model: Events, office hours and live sessions

**Spec**: [spec.md](spec.md) · **Research**: [research.md](research.md) · **Plan**: [plan.md](plan.md)

There are two kinds of data:
- **Declared** data lives in the repo, and `site_config.py` applies it.
- **Moodle data** lives only in Moodle, never in the repo (FR-011, FR-012, Principle III), and moves with a data restore.

## Declared (repo)

### Office-hours course (`moodle/site/office-hours.yaml`)

| Field | Value | Rule (`validate`) |
|---|---|---|
| `rows` | `[21]` | required |
| `course.idnumber` | `ltct:officehours` | fixed; the plugin finds the course by it |
| `course.fullname` / `shortname` | `Mentor office hours` / `ltct-officehours` | `check_recognition()` passes; ≤ 254 / 100 characters |
| `course.category` | `mentoring` | a key in `organisations.yaml` `categories` |
| `course.groupmode` / `groupmodeforce` | `1` / `1` | must be exactly these (R16) |
| `course.summary` | one or two plain sentences for learners | no level vocabulary |
| `scheduler.idnumber` | `ltct:officehours:scheduler` | fixed |
| `scheduler.name`, `intro` | learner-facing text | as above |
| `scheduler.groupmode` | `1` | must be 1 |
| `scheduler.maxbookings` | `1` | 1–5 |
| `scheduler.schedulermode` | `onetime` | `onetime` or `oneonly` |
| `scheduler.guardtime_hours` | `12` (plan decision 3) | 0–168; rendered to seconds |
| `scheduler.allownotifications` | `1` | must be 1 (FR-007) |
| `scheduler.defaultslotduration` | `30` | minutes, 5–240 |
| `scheduler.usebookingform`, `grade` | `0`, `0` | must be 0 (V: no grading, CBC fidelity) |
| `groups.name_template` | `Office hours {n}` | must contain `{n}`; must not contain `{name}`, `{firstname}` or `{lastname}` (spec 016) |
| `why` | cites #21, D6, R16 | required |

### Dashboard block (`moodle/site/dashboard.yaml`)

| Field | Value | Rule |
|---|---|---|
| `rows` | `[21]` | |
| `default_blocks` | `[{block: calendar_upcoming, region: side-post, why: …}]` | `block` must be a core block name. Additive: `apply` never removes a block it did not declare (R18). |

### Settings

Each file follows 001's settings shape.

| File | Setting | Value |
|---|---|---|
| `settings/calendar.yaml` | `enablecalendarexport` | `1` |
| | `calendar_customexport` | `1` |
| | `calendar_adminseesall` | `0` |
| | `timezone` | `UTC` (D7) |
| | `forcetimezone` | `99` |
| `settings/scheduler.yaml` | `mod_scheduler/groupscheduling` | `0` |
| | `mod_scheduler/showemailplain` | `0` |
| | `mod_scheduler/mixindivgroup` | `1` |
| | `mod_scheduler/maxstudentlistsize` | `200` |
| | `mod_scheduler/uploadmaxfiles` | `5` |
| | `mod_scheduler/revealteachernotes` | `0` |

`calendar_lookahead`, `calendar_maxevents` and `calendar_exportlookahead` stay at their defaults and are not declared (R12).

### Role changes (`moodle/site/roles.yaml`)

| Role | Capability | Value | Why |
|---|---|---|---|
| `orgmanager` | `moodle/calendar:manageentries` | `allow` | D3, R17 |
| `student` | `mod/scheduler:seeotherstudentsbooking` | `inherit` | D4, FR-008, R19 |
| `teacher` | `mod/scheduler:manageallappointments`, `canscheduletootherteachers`, `canseeotherteachersbooking` | `inherit` | Already absent from the archetype; declared so drift catches a hand grant (R19) |

**Validation**: `orgmanager` may hold no `moodle/calendar:*` capability except `manageentries`. Its existing deny list is unchanged.

### Category (`moodle/site/organisations.yaml`)

`categories` gains `{key: mentoring, name: LTC Mentoring, why: …}`. It is a shared category, like `published` and `pilots`.

### Plugin pin (`moodle/site/site.yaml`)

`mod_scheduler` at version `2026080400`, with its sha256 and its marketplace URL (R9). `local_ltuse` is re-pinned.

## Moodle data (never in the repo)

### Event (core `event`)

| Level | Fields that decide reach | Who creates it |
|---|---|---|
| Site | `eventtype = site`, `courseid = SITEID` | The site team |
| Course | `eventtype = course`, `courseid`, `groupid = 0` | Course mentor (`teacher`) in shared courses; `orgmanager` in its organisation-only courses |
| Group | `eventtype = group`, `courseid`, `groupid` | Course mentor, or the site team |
| User | `eventtype = user`, or the scheduler's `SSstu:`/`SSsup:` with `modulename = scheduler` | The user, or the scheduler on booking |

- A live session is a site, course or group event. The join link goes in `description` (HTML), never in `location`, which the app turns into a maps link (R10). The follow-up note or recording link is added to the same `description` afterwards.
- No course completion ever references an event (FR-010).

### Change notice (adhoc task `\local_ltuse\task\event_change_notice`)

The observer buffers each request's events by key and queues one task per key when the request ends, so a series collapses into one task (R15).

| Field | Meaning |
|---|---|
| `action` | `changed` or `cancelled` |
| `scope` | `occurrence` or `series`, for a change: whether the request touched more than one occurrence. For a cancellation, the task decides it from whether rows with the `repeatid` remain. |
| `firststart` | The start of the first occurrence seen. It names the date when one occurrence is cancelled. |
| `key` | `r<repeatid>` for a series, else `e<eventid>` |
| `eventtype` | `site`, `course` or `group` |
| `courseid`, `groupid` | Reach |
| `name` | The event's name, needed for a cancellation, since the row is gone |
| `actorid` | The user who made the change, who is left out |

Within a request, one task is queued per key, and a cancellation outranks a change. Across requests, an earlier `changed` task whose event has since been deleted sends nothing. The task runs 2 minutes after it is queued.

**States**: *queued* → *sent*. If the event is gone or hidden when a `changed` task runs, it sends nothing.

### Time zone confirmation (user preference `local_ltuse_tzconfirmed`)

- The value is the confirmation time.
- It is set when the user saves `local/ltuse/timezone.php`, or saves their own profile in `user/edit.php` (`user_updated` where the user is updating themselves).
- It is never unset by us. Personal data: declared and exported by the privacy provider.

### Office-hours membership

| Record | Owner | Rule |
|---|---|---|
| Group `ltct:mentor:<mentorid>` in `ltct:officehours`, visibility `OWN`, name from the template | `local_ltuse` | One per mentor with at least one mentee. It is never deleted, so its slots' history stays. It is emptied when the mentor has no mentee. |
| Membership of the mentor | `component = local_ltuse`, `itemid = <mentorid>` | While the mentor has a mentee |
| Membership of each mentee | `component = local_ltuse`, `itemid = <mentorid>` | While the user-context `mentor` assignment exists. A learner with two mentors is in two groups. |
| Manual enrolment, role `teacher` (mentor) or `student` (mentee) | The course's one manual enrolment instance | Active while the person has at least one relationship; suspended, not removed, after the last one ends. A person who is both mentor and mentee holds both roles. |

**Invariant** (checked by the reconcile task): for every `mentor` assignment in a user context, with mentor M and learner L, the following hold, and nothing else is tagged `local_ltuse` in the course.
- M and L are actively enrolled.
- Group `ltct:mentor:<M>` exists.
- M and L are both members of it.

`officehours_plan::diff(assignments, memberships, enrolments)` is pure and returns the creates, adds, removes, suspends and reactivates the task applies. Its output is counts only when logged.

### Slot and appointment (`mod_scheduler`)

These are owned by the plugin.
- **A slot**: a teacher (mentor), a time, a duration, a location (plain text) and notes (rich text; the meeting link goes here, R9).
- **An appointment**: one student, attended and notes.
- **Calendar**: a booking writes one user event for each side (R19). Past unbooked slots are purged by the plugin.
- **Export**: through the plugin's privacy provider (FR-012).
