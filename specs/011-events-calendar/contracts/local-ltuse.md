# Contract: `local_ltuse` additions (spec 011)

Everything here uses Moodle's supported extension points: event observers, message providers, adhoc and scheduled tasks, an output hook, the `allow_group_member_remove` callback, and the privacy provider. Writes go through public APIs and our own table only. The two Principle XI exceptions are listed in the plugin README.

## Observers (`db/events.php`)

| Event | `internal` | Action |
|---|---|---|
| `\core\event\calendar_event_created` | `false` | Records `objectid` in a per-request static set. Nothing is queued. |
| `\core\event\calendar_event_updated` | `false` | If `calendar_notify::decide($snapshot, $other, $createdthisrequest)` is true, add the event to the request's buffer under `calendar_notify::key()`. The first event of a key gives `firststart`. A second one marks the key `scope = series`. |
| `\core\event\calendar_event_deleted` | `false` | The same, as `cancelled`. A cancellation outranks a change for the same key. |

The first buffered event registers one `\core\shutdown_manager::register_function()` callback (`lib/classes/shutdown_manager.php:165`). That callback queues one task per key with `reschedule_or_queue_adhoc_task()`. Custom shutdown callbacks run before core closes the database (`:178` onwards). A cancellation needs no clean-up. A `changed` task queued earlier for the same key finds its event gone and sends nothing. Core has no public API to delete a queued adhoc task, so none is attempted.
| the same three calendar events, for `modulename = scheduler`, an `SSstu:` eventtype and the office-hours instance | default | `booking_notice::from_calendar()`: insert, compare or delete the `local_ltuse_booking` row, and send **booked**, **changed** or **cancelled** to the learner and the mentor (R20). |
| `\mod_scheduler\event\slot_deleted` | default (runs before the slot is removed) | `booking_notice::from_slot_deleted()`: for each row with `objectid` as its `slotid`, delete it and send **cancelled** to the learner. The mentor (`relateduserid`) gets one summary message. |
| `\core\event\role_assigned`, `role_unassigned`, `user_deleted` (003's, extended) | unchanged | After 003's contact handling, call `officehours::sync_pair($mentorid, $learnerid)`, or for a deleted user, `officehours::sync_user($userid)`. |

`calendar_notify::decide()` is pure and is tested by `tests/calendar_notify_harness.php`. It returns false for:
- no snapshot;
- `modulename`, `component` or `subscriptionid` set;
- `eventtype` of `user`, `category` or anything else not `site`, `course` or `group`;
- `other.repeatid != snapshot.repeatid`;
- `visible = 0` (on update);
- an id created in the same request.

Observers never throw into core. Errors go to `debugging()`, and the calendar or role change still stands.

## Adhoc task `\local_ltuse\task\event_change_notice`

- Queued with `reschedule_or_queue_adhoc_task()`, `component = local_ltuse` and next run `time() + 120`. Customdata is as in [data-model.md](../data-model.md).
- **Recipients**:

  | Event type | Recipients |
  |---|---|
  | `site` | every active, confirmed, non-guest, non-deleted user, read as a recordset in chunks of 500. The task re-queues itself with an offset until done. |
  | `course` | `get_enrolled_users($coursectx, '', 0, 'u.id', null, 0, 0, true)` |
  | `group` | `get_enrolled_users($coursectx, '', $groupid, 'u.id', null, 0, 0, true)` |

  `actorid` is removed from the list.
- **The message**:
  - It is a `\core\message\message`: `component = local_ltuse`, `name = eventchange`, `userfrom = core_user::get_noreply_user()` and `notification = 1`.
  - `courseid` is the event's course, or `SITEID`.
  - `contexturl` is `/calendar/view.php?view=day&time=<timestart>` for a change, or `/calendar/view.php?view=upcoming` for a cancellation.
  - The subject and body give the event name, the new start in the recipient's zone (`userdate()` with the recipient's zone), and whether it is one occurrence or a series.
  - It never names a person.
- **Before sending**: a `changed` task whose event no longer exists, or is hidden, sends nothing.

## Booking notices (`classes/booking_notice.php`)

- **The decision** is pure: `booking_notice::decide(string $kind, ?stdClass $row, stdClass $event): ?string` returns `booked`, `changed`, `cancelled` or null. On an update, null means `timestart` and `timeduration` are unchanged. It is tested by `tests/booking_notice_harness.php`.
- **The mentor** is read from `scheduler_slots.teacherid` by the slot id parsed from `SSstu:<slotid>`. This is a raw read, listed in the README.
- **The messages** go through `\core\message\message`:
  - `component = local_ltuse` and `name = bookingnotice`;
  - `userfrom = core_user::get_noreply_user()` and `notification = 1`;
  - `courseid` is the office-hours course, and `contexturl` is the scheduler's `view.php`.
  - Each is sent at once, to the learner and to the mentor.
- **The wording**:
  - It names the actor (the event's `userid`): "You booked …" to the actor, and "<fullname> booked …" to the other side.
  - It gives the time with `userdate()` in the recipient's zone, followed by the zone's name.
  - The changed message says "moved to …", and a slot deletion says "cancelled by your mentor".
- **Never**: a message for an update that changes no time, or for anything outside the office-hours scheduler.

## Message providers (`db/messages.php`)

```php
$messageproviders = [
    'eventchange' => ['defaults' => [
        'popup'       => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        'email'       => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    ]],
    'bookingnotice' => ['defaults' => [
        'popup'       => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        'email'       => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    ]],
];
```

Each learner can turn any route off in their notification preferences (FR-006, "their chosen notification route").

## Time zone notice (R14)

- **The hook.** `db/hooks.php` registers `\local_ltuse\hook_callbacks::top_of_body` for `\core\hook\output\before_standard_top_of_body_html_generation`, which is dispatched from `core_renderer::standard_top_of_body_html()` (`lib/classes/output/core_renderer.php:308`).
- **The callback.** For a logged-in, non-guest user, it calls `timezone_notice::applies($PAGE->pagetype, $PAGE->cm?->idnumber)`, which is true only for `mod-scheduler-*` pages of `ltct:officehours:scheduler`. If it applies, the callback calls `$hook->add_html()` with one `notification` box. The box holds `timezone_notice::text()` for `core_date::get_user_timezone($USER)`, a "Change it" link to `/user/edit.php?returnto=profile`, and for a stored `99` the words "UTC, the site default".
- **Pure parts.** `applies()` and `text()` make no Moodle calls, and `tests/timezone_notice_harness.php` tests them. The strings live in `lang/en/local_ltuse.php`.
- **Never** on any other page, and never a redirect.

## Office-hours sync

- **`\local_ltuse\officehours::sync_pair(int $mentorid, int $learnerid)`**:
  - Reads whether a `mentor` assignment links the pair (`user_has_role_assignment()`).
  - If the course is not yet created, it does nothing; `apply` reconciles later.
  - **When the pair is linked**:
    - It enrols both through the course's manual instance, if not already: `enrol_user()`, with `teacher` for the mentor and `student` for the learner. It reactivates a suspended enrolment with `update_user_enrol()`.
    - It ensures the group exists: `groups_create_group()` with `idnumber ltct:mentor:<mentorid>`, the name from the template with `{n}` = the next free number, and visibility `GROUPS_VISIBILITY_OWN`.
    - It adds both people with `groups_add_member($group, $user, 'local_ltuse', $mentorid)`.
  - **When the pair is no longer linked**:
    - It removes the learner from that group with `groups_remove_member()`.
    - It suspends the learner's enrolment if no other relationship remains.
    - It does the same for the mentor once they have no mentee.
- **`sync_user(int $userid)`** runs the same logic for every pair the user was in.
- **`reconcile(): array`**:
  - Reads every `mentor` assignment at `CONTEXT_USER`, using 003's raw read, listed in the README.
  - Reads the course's `local_ltuse` memberships and its manual enrolments.
  - Applies `officehours_plan::diff()` and returns counts.
  - Also deletes `local_ltuse_booking` rows whose calendar event no longer exists, which sends no message. `sync_user()` for a deleted user removes that user's rows.
- **Scheduled task** `\local_ltuse\task\officehours_reconcile`: hourly (`db/tasks.php`, minute `R`), calling `reconcile()`.
- **`local_ltuse_allow_group_member_remove($itemid, $groupid, $userid)`** returns false, so the site team cannot remove a sync-owned member by hand.

## Privacy provider (changed)

- Metadata declares both message providers (`link_subsystem('core_message')`), and the table `local_ltuse_booking` (`add_database_table()`).
- `local_ltuse_booking` rows are exported, and deleted for a user or a user list, where the user is the learner or the mentor (in `CONTEXT_USER`).
- Group memberships, enrolments, events and appointments are exported by core and by the scheduler.

## README additions (Principle XI)

The README adds these:
- **Exception 1**: the office-hours privacy rests on `mod_scheduler`'s display-only group filter, and the plugin does not check groups when a slot is booked. It is re-checked by V10 on every re-pin.
- **Exception 2**: the raw read of `scheduler_slots.teacherid` by primary key, and reliance on the scheduler's `SSstu:` eventtype convention (R20). Re-checked by V13 on every re-pin.
- **Observers and tasks**: the new observers, the adhoc and scheduled tasks, and the reuse of 003's raw read of `role_assignments`.
