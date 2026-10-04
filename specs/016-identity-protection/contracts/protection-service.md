# Contract: the protection service (spec 016)

Everything in `local_ltuse` that reads or writes protection goes through two classes:

- `\local_ltuse\protection\entitlement`: who may see and who may manage.
- `\local_ltuse\protection\service`: applying a level.

The pages, web services, hook, observers and tasks all call them, so a level is applied one way and checked one way, whatever triggered it.

## Capabilities (`db/access.php`)

| Capability | Context | Type | Risk | Archetypes |
|---|---|---|---|---|
| `local/ltuse:viewidentity` | CONTEXT_USER | read | `RISK_PERSONAL` | `manager` |
| `local/ltuse:manageprotection` | CONTEXT_USER | write | `RISK_PERSONAL` | `manager` |
| `local/ltuse:manageorgprotection` | CONTEXT_SYSTEM | write | `RISK_PERSONAL` | `manager` |

`mentor` (in the learner's user context) and `teacher` (in a course's context, R7 path 4) get `viewidentity` through `roles.yaml`.

## Entitlement

- **`can_view_identity($viewer, $user)`**: true if any of these holds:
  - the viewer has `viewidentity` at system context (the site team);
  - the viewer has `viewidentity` in the user's context (an assigned mentor);
  - the viewer is a member of `ltct:org:<key>:managers`, where `<key>` is the user's `ltct_org` and the user is in `ltct:org:<key>`, **and** that organisation's `managers_see_identity` is 1;
  - the user is actively enrolled in a course with idnumber `ltct:<slug>` other than `ltct:officehours`, and the viewer has `viewidentity` in that course's context (a course mentor, R7 path 4).
- **`marker($viewerid, $userid)`**: the **Protected** badge as HTML, or `''` when the user is not protected or the viewer is not entitled. Every surface uses it, so none can show the marker to a non-entitled viewer.
- **`can_manage_protection($viewer, $user)`**: true if either holds:
  - the viewer has `manageprotection` at system context;
  - the viewer is a member of that managers cohort.

  Managers manage their own people even when their organisation withholds identity. The granting page then shows them the protected display only, not the real values.
- The user themselves can always see their own protection and preview. They cannot change it.

## Service methods (PHP, `\local_ltuse\protection\service`)

Named for the 006 and 008 sessions (2026-10-04); they are stable.

- `set_protection(int $userid, string $level, array $options = [], ?int $actorid = null): array`: the logic below, **without** a permission check. Callers check `can_manage_protection()` first. Returns `{effectivelevel, warnings}`; refuses with `moodle_exception` (`protection:err:*`).
- `apply(int $userid): string`: recompute and apply one user's effective level and `ltct_certname`, under the lock. The adhoc and reconcile tasks call it.
- `effective_level(int $userid): string`: computed live, the maximum of the user's own level and their organisation's minimum.
- `is_settled(int $userid): bool`: the level applied to the account equals `effective_level()`. Spec 008 gates enrolment on it.
- `is_protected(int $userid): bool`, and `real_identity(int $userid): ?array` (`{firstname, lastname, level}`), which callers read only after `can_view_identity()`.

## Web service `local_ltuse_set_protection`

**Parameters**:
- `userid`;
- `level`, one of `none`, `email`, `firstname`, `pseudonym`;
- `pseudonym`, required for `pseudonym`;
- `realfirstname`, `reallastname` and `realfields`, all optional corrections, accepted only when `can_view_identity()` also holds;
- `newusername`, optional;
- `acknowledgehistory`, a bool, default false.

**Capability**: `can_manage_protection()`.

**Refused when**:
- the level is looser than the organisation minimum;
- the level is `firstname` or `pseudonym` and `orgscope_ready` is false (R11);
- a raise or a lowering needs `acknowledgehistory` for a user with activity (R13) and it is not given;
- the username contains the real first name or surname (R13) and `newusername` is not given;
- the pseudonym fails the rules (unique among protected users, at most 100 characters, and must not contain the real name; see [data-model.md](../data-model.md)).

**What the plugin does, in order** (one per-user `\core\lock` and one delegated transaction around steps 2–7):
1. Validate the request and compute `effectivelevel` as the maximum of `level` and the organisation minimum.
2. Snapshot every field that becomes withheld now and is not already held (per field, R5). Apply corrections. Never overwrite a held value with an empty one.
3. Add the user ID to the service's private bypass set. Call `user_update_user($user, false, true)` to write names, the alternate names, `maildisplay`, the withheld core fields and `newusername`. Call `profile_save_data()` to write `ltct_role`, `ltct_exp_*` and `ltct_certname`. Remove the ID in `finally`.
4. If the level entered `firstname` or `pseudonym`, call `core_user::update_picture(deletepicture = 1)`.
5. Write a log row.
6. Purge `core/coursecontacts`. Re-save the user's `mod_scheduler` slots, if that plugin is installed (R15).
7. Send `protectionchanged`.

**`protectionchanged` notification**:
- It is sent to the learner only. It names the level and lists what others now see, with no real name and no actor.
- It tells them:
  - to review their own profile description and interests;
  - that copies already sent, and file author metadata, cannot be changed;
  - that a removed picture must be uploaded again after a downgrade;
  - their new login, if the username changed.

**Returns**: `{effectivelevel, warnings[]}`. The warnings cover copies that cannot be recalled. The real identity is never returned.

## Web service `local_ltuse_set_org_protection`

**Parameters**:
- `orgkey`;
- `minlevel`, one of `none`, `email`, `firstname`;
- `managers_see_identity`;
- `acknowledgehistory`.

**Capability**: `manageorgprotection` (the site team only).

**Refused when**:
- the organisation is not declared;
- the level is above `org_minimum_max`;
- the level is `firstname` while `orgscope_ready` is false;
- the raise affects members with activity and `acknowledgehistory` is not given. The refusal returns the **count** of such members.

**What it does**: it stores the row, logs the change (actor = the caller) and queues `apply_protection` for every member. Lowering a minimum does not lower anyone automatically: members keep their level as `organisation-kept` until an entitled person lowers it (R13).

## Hook callback (`db/hooks.php`)

`core_user\hook\before_user_updated`: if the user has a protection row and is not in the bypass set, the callback overwrites the protected fields on the hook's `$user` with the protected values. Nothing else. It is listed in the plugin README as an upgrade risk (R2).

## Observers (`db/events.php`, in the file spec 003 adds)

Every observer returns early when the user is in the bypass set.

| Event | Action |
|---|---|
| `\core\event\user_created` | Queue `apply_protection` for the user. It runs after the upload tool has saved profile data, then sets `ltct_certname` and applies any organisation minimum (R10, R2). |
| `\core\event\user_updated` | Protected user: re-apply if drifted. Unprotected user: keep `ltct_certname` in step with the name. |
| `\core\event\cohort_member_added` | **Only** for a cohort whose idnumber is exactly `ltct:org:<key>` for a declared key (never `...:managers`). Apply the minimum synchronously. If the user is already in progress, queue `apply_protection` instead. |
| `\core\event\cohort_member_removed` | Same cohort match. Mark the row `organisation-kept`, with the level unchanged. |
| `\core\event\user_deleted` | Call the shared deletion routine (Privacy, below). |

## Tasks (`db/tasks.php`)

- **`apply_protection`** (adhoc, one class): applies one user's effective level and `ltct_certname`. It is queued by the observers and by `set_org_protection`.
- **`reconcile_protection`** (scheduled, every `reconcile_minutes`): recomputes and re-applies, for every protected row, every member of every organisation whose minimum is above `none`, and every user's `ltct_certname`. It logs a count to the task log and writes no log rows.

## Surfaces

The real identity and the **Protected** marker are shown only where `can_view_identity()` is true. A non-entitled viewer never sees the marker.

1. **Profile node** (`local_ltuse_myprofile_navigation()`), web only:
   - the learner sees their level, a preview of what others see, and how to ask for protection;
   - an entitled viewer sees the real identity and the marker;
   - a manager sees a link to the granting page.
2. **003 Mentoring page and its app handler**: the marker and the real name for entitled viewers.
3. **"People I support"** (`/local/ltuse/protected.php`): the protected people the viewer is entitled to see, with level, real name and display name, and a CSV download carrying the marker per row. It lives in our own plugin, because core report datasources cannot take our column (R7).
4. **Granting page** (`/local/ltuse/protection.php?id=`) and **organisation page** (`/local/ltuse/orgprotection.php`, site team only). Both call the web-service logic above, including the acknowledgement steps.

## Privacy provider

- **Metadata**: the three tables and the `protectionchanged` message provider.
- **Contexts and users**: the user's own rows. Rows where they are the **actor** (`actorid`, `usermodified`) are also covered. The provider implements `core_userlist_provider`.
- **Export**: the user's protection row (level, pseudonym, real values), their log rows, and the changes they made as an actor, by count and date only, without other users' identities.
- **Delete**: one routine, shared with the `user_deleted` observer. It deletes the user's own protection row and log rows, and sets `actorid` and `usermodified` to 0 wherever they acted, including in `local_ltuse_org_protection`.

## Plugin README exceptions (Principle XI)

1. Mutating the object carried by `before_user_updated` (R2).
2. Calling `mod_scheduler`'s `slot` class to re-save slots (R15).
3. Reading `logstore_standard_log` (`userid`, `crud`, `eventname`) and `messages` (`useridfrom`) by indexed columns to decide "has activity" (R13).
