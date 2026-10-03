# Contract: `local_ltuse` additions (spec 011)

Everything here uses Moodle's supported extension points: event observers, a message provider, adhoc and scheduled tasks, a page, the legacy `after_require_login` callback, the `allow_group_member_remove` callback, and the privacy provider. Writes go through public APIs only. The two Principle XI exceptions are listed in the plugin README.

## Observers (`db/events.php`)

| Event | `internal` | Action |
|---|---|---|
| `\core\event\calendar_event_created` | `false` | Records `objectid` in a per-request static set. Nothing is queued. |
| `\core\event\calendar_event_updated` | `false` | If `calendar_notify::decide($snapshot, $other, $createdthisrequest)` is true, add the event to the request's buffer under `calendar_notify::key()`. The first event of a key gives `firststart`. A second one marks the key `scope = series`. |
| `\core\event\calendar_event_deleted` | `false` | The same, as `cancelled`. A cancellation outranks a change for the same key. |

The first buffered event registers one `\core\shutdown_manager::register_function()` callback (`lib/classes/shutdown_manager.php:165`). That callback queues one task per key with `reschedule_or_queue_adhoc_task()`. Custom shutdown callbacks run before core closes the database (`:178` onwards). A cancellation needs no clean-up. A `changed` task queued earlier for the same key finds its event gone and sends nothing. Core has no public API to delete a queued adhoc task, so none is attempted.
| `\core\event\user_updated` | default | If `relateduserid == userid` (a user saving their own profile), set `local_ltuse_tzconfirmed`. |
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

## Message provider (`db/messages.php`)

```php
$messageproviders = [
    'eventchange' => ['defaults' => [
        'popup'       => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        'email'       => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        'airnotifier' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    ]],
];
```

Each learner can turn any route off in their notification preferences (FR-006, "their chosen notification route").

## Time zone gate

- **`lib.php`, `local_ltuse_after_require_login($courseorid, $autologinguest, $cm, $setwantsurltome, $preventredirect)`**:
  - Calls `timezone_gate::decide()`, which is pure and tested by `tests/timezone_gate_harness.php`. Its inputs are: logged in; guest; `\core\session\manager::is_loggedinas()`; is site admin or holds `moodle/site:config`; holds the `ltcpublisher` role; the preference is set; the script is `timezone.php`, `user/edit.php`, `login/*` or `admin/*`; `AJAX_SCRIPT`; `$preventredirect`.
  - Returns `none`, `redirect` or `throw`:

    | Result | When | Action |
    |---|---|---|
    | `redirect` | Web, not yet asked this session | `redirect(new moodle_url('/local/ltuse/timezone.php', ['returnurl' => $FULLME]))` |
    | `throw` | `$preventredirect` (web services, including the app) | `throw new moodle_exception('usernotfullysetup')`. This is the listed exception: the app's complete-profile page opens `user/edit.php` (R14). |
- **`timezone.php`**:
  - `require_login()`, then one form with the core time zone menu (`core_date::get_list_of_timezones()`). It is preset to the browser's zone, read by a small inline script, else to the user's current zone.
  - On save, `user_update_user((object)['id' => $USER->id, 'timezone' => $tz], false, true)`, set the preference, and redirect to `returnurl` (local only).
  - Strings in `lang/en/local_ltuse.php`.

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
- **Scheduled task** `\local_ltuse\task\officehours_reconcile`: hourly (`db/tasks.php`, minute `R`), calling `reconcile()`.
- **`local_ltuse_allow_group_member_remove($itemid, $groupid, $userid)`** returns false, so the site team cannot remove a sync-owned member by hand.

## Privacy provider (changed)

- It adds `\core_privacy\local\request\user_preference_provider`.
- `export_user_preferences()` exports `local_ltuse_tzconfirmed`.
- Metadata declares the preference and the `eventchange` provider, through `add_user_preference()` and `link_subsystem('core_message')`.
- Group memberships, enrolments, events and appointments are exported by core and by the scheduler.

## README additions (Principle XI)

The README adds these:
- **Exception 1**: `usernotfullysetup` is thrown from our `after_require_login` callback. It is re-checked by V3 on every core or app upgrade.
- **Exception 2**: the office-hours privacy rests on `mod_scheduler`'s display-only group filter, and the plugin does not check groups when a slot is booked. It is re-checked by V10 on every re-pin.
- **Observers and tasks**: the new observers, the adhoc and scheduled tasks, and the reuse of 003's raw read of `role_assignments`.
