# Contract: the protection service (spec 016)

Everything in `local_ltuse` that reads or writes protection goes through two classes:

- `\local_ltuse\protection\entitlement`: who may see and who may manage.
- `\local_ltuse\protection\service`: applying a level.

The pages, the web service, the hook, the observer and the tasks all call them, so a level is applied one way and checked one way, whatever triggered it.

Amended by the scope review (Doug, 2026-10-05 (scope review)): protection is per person, for someone who asks. Organisation minimums, `managers_see_identity`, `ltct_certname`, the organisation page and web service, the user-created and cohort observers, the CSV and the granter's username field are removed; each removal is noted where it applied.

## Capabilities (`db/access.php`)

| Capability | Context | Type | Risk | Archetypes |
|---|---|---|---|---|
| `local/ltuse:viewidentity` | CONTEXT_USER | read | `RISK_PERSONAL` | `manager` |
| `local/ltuse:manageprotection` | CONTEXT_USER | write | `RISK_PERSONAL` | `manager` |
| ~~`local/ltuse:manageorgprotection`~~ | — | — | — | Removed (change 9) |

`mentor` (in the learner's user context) and `teacher` (in a course's context, R7 path 4) get `viewidentity` through `roles.yaml`. Spec 008's `ltctadmin`, held at system level by each site-team member's administration account, gets `manageprotection` there too (008 research R5), so its intake can grant protection to an account with no organisation yet.

## Entitlement

- **`can_view_identity($viewer, $user)`**: true for the user themselves, and otherwise if any of these holds:
  - the viewer has `viewidentity` at system context (the site team);
  - the viewer has `viewidentity` in the user's context (an assigned mentor);
  - the viewer is a member of `ltct:org:<key>:managers`, where `<key>` is the user's `ltct_org` and the user is in `ltct:org:<key>`. Unconditional: ~~**and** that organisation's `managers_see_identity` is 1~~ (removed, change 10);
  - the user is actively enrolled in a course with idnumber `ltct:<slug>` other than `ltct:officehours`, the viewer has `viewidentity` in that course's context, **and** the user is a member of the viewer's group `ltct:mentorgroup:<viewer id>` in that course (a course mentor, R7 path 4, narrowed by change 22).
- **`marker($viewerid, $userid)`**: the **Protected** badge as HTML, or `''` when the user is not protected or the viewer is not entitled. Every surface uses it, so none can show the marker to a non-entitled viewer.
- **`can_manage_protection($viewer, $user)`**: true if either holds, never for oneself:
  - the viewer has `manageprotection` in the user's context (the site team);
  - the viewer is a member of the managers cohort of the user's own organisation (path 3).
- **`is_site_team($viewer, $user)`**: `manageprotection` in the user's context. Anyone else who may manage is limited to intake (`levels::manager_may()`, below).
- **`may_be_entitled($viewer)`**: a cheap test, never false for an entitled viewer: a site admin, a manager of some organisation, or someone holding a role that allows `viewidentity`. Asked before the per-person checks, so an ordinary learner's own profile costs a few small reads (change 12).
- The user themselves can always see their own protection and preview. They cannot change it.

## Service methods (PHP, `\local_ltuse\protection\service`)

Named for the 006 and 008 sessions (2026-10-04); they are stable.

- `set_protection(int $userid, string $level, array $options = [], ?int $actorid = null): array`: the logic below, **without** a permission check. Callers check `can_manage_protection()` first, and accept corrections only from someone who `can_view_identity()`. It does check what the actor may change once there: anyone but the site team may only raise, before any activity, with no correction (change 14). Returns `{effectivelevel, warnings}`; refuses with `moodle_exception` (`protection:err:*`). `$options`: `requested`, `emailchecked` (both required for a raise), `pseudonym`, `realfirstname`, `reallastname`, `realfields`, `acknowledgehistory`, `hidelogs` (absent keeps the current choice; changing it at the same level is a correction, so the site team's).
- `apply(int $userid): string`: re-apply one protected user's level where the account has drifted from it, under the lock. A repair: it changes no level and logs nothing. The adhoc and reconcile tasks and the observer call it.
- `effective_level(int $userid): string`: the level set for the user (`none` when there is no row). ~~The maximum of the user's own level and their organisation's minimum~~ (change 9).
- `is_settled(int $userid): bool`: the account shows its protected state. Spec 008 holds an enrolment back on it.
- `level_available(string $level): bool`: can this level be applied on this site now; true for every level once `protection.yaml` is stored. Spec 008's intake preview asks it before any account exists.
- `is_protected(int $userid): bool`, and `real_identity(int $userid): ?array` (`{firstname, lastname, level}`), which callers read only after `can_view_identity()`.
- `has_activity(int $userid): bool`: `user.firstaccess > 0`, or enrolled in any course (change 16).
- `email_warnings(int $userid): array`: why the account's email address may identify the person (`levels::email_reveals()`: the part before the @ holds the real first name or surname, or the domain holds a part of the organisation entry's key, a heuristic that cannot recognise a SIL partner's or an independent learner's employer domain; the granter's `emailchecked` is the control) (change 2).
- `picture_levels(int $userid): array`: the levels that would delete the user's picture, for the granting page's warning; empty when they have none or it is already withheld.
- `sync_log_blocks(): void`: the course-log block (R14), below.
- `neutral_username(): string`: `ltc-` and 8 lowercase base32 characters, unused, in spec 008's `intake_service::new_username()` format (change 15). Since main (with spec 008) was merged in on 2026-10-05 it calls that method, so the site has one generator and its format is 008's.

### Spec 008's intake (the main grant path)

008's `intake_service::protect()` calls `set_protection()` directly. A raise is refused without both recorded facts, so intake MUST pass:

- `requested => true` when the row's protection column asks for a level: the row is the person's request;
- `emailchecked => true` only after intake has checked the row's email itself and the operator has confirmed, or swapped, the address. Intake runs `levels::email_reveals($email, $first, $last, $orgkey)` with the **row's** organisation key, because `ltct_org` is not yet set when `protect()` runs, so `service::email_warnings()` would miss the organisation half. Every row that needs a grant waits until its `email_checked` column confirms the address, flagged or not (FR-016: the heuristic cannot recognise every employer domain), and intake passes that column as `emailchecked`, so the log never records a confirmation nobody gave. Intake also runs `levels::pseudonym_problems()` before creating the account, so a pseudonym `set_protection()` would refuse never leaves an account made with no organisation.

008's intake passes both (#93, 19a6d91; `moodle/local_ltuse/classes/admin/intake_service.php`, `protect()`): `requested` is always true for a row that asks, and `emailchecked` is the row's own `email_checked`. A refusal from `set_protection()` stops the row as `protection_failed`, and one that `can_manage_protection()` refuses as `protection_not_permitted` (tasks T043). 008's intake contract names the same two options.

## Web service `local_ltuse_set_protection`

**Parameters**:
- `userid`;
- `level`, one of `none`, `email`, `firstname`, `pseudonym`;
- `requested`, a bool, **required**: the person asked for this change (change 13);
- `emailchecked`, a bool: the granter confirmed the account's email identifies neither the person nor their organisation; needed for a raise (change 2);
- `pseudonym`, required for `pseudonym`;
- `realfirstname`, `reallastname` and `realfields`, all optional corrections, accepted only from the site team, and only when `can_view_identity()` also holds;
- `acknowledgehistory`, a bool, default false.

There is no `hidelogs` parameter: the course-log block is asked for on the granting page, and a web-service call keeps the current choice.

~~`newusername`~~ is removed: a neutral username is applied automatically (change 15).

**Capability**: `can_manage_protection()`.

**Refused when**:
- a raise lacks `requested` (`protection:err:notrequested`) or `emailchecked` (`protection:err:emailnotchecked`);
- the actor is not the site team, and the change is not a raise for someone with no activity, or it corrects a real value (`protection:err:siteteam`);
- a raise or a lowering needs `acknowledgehistory` for a user with activity (R13) and it is not given (`protection:err:needsack`);
- the pseudonym fails the rules (unique among protected users, at most 100 characters, and must not contain the real name; see [data-model.md](../data-model.md)).

~~The level is looser than the organisation minimum; the level is `firstname` or `pseudonym` and `orgscope_ready` is false; the username contains the real name and `newusername` is not given~~ (removed, changes 9, 11, 15).

**What the plugin does, in order** (one per-user `\core\lock` and one delegated transaction around steps 2–6):
1. Validate the request and the actor's limits.
2. Snapshot every field that becomes withheld now and is not already held (per field, R5). Apply corrections. Never overwrite a held value with an empty one.
3. Add the user ID to the service's private bypass set. Call `user_update_user($user, false, true)` to write names, the alternate names, `maildisplay`, the withheld core fields and, at `firstname` and above, a neutral username when the current one holds the real name. Call `profile_save_data()` to write `ltct_role` and `ltct_exp_*`. Remove the ID in `finally`.
   The same write sets `auth` to `manual` when it is not `manual` or `nologin` (R3).
4. If the level entered `firstname` or `pseudonym`, call `core_user::update_picture(deletepicture = 1)`. Above `none`, delete the user's `\auth_oauth2\linked_login` records (R3).
5. Write or delete the protection row (deleted at `none`).
6. Write a log row, with `requested` and `emailchecked`.
7. After the commit: purge `core/coursecontacts`; re-save the user's `mod_scheduler` slots, if that plugin is installed (R15); send `protectionchanged` when the level changed; when the person has or had `hidelogs`, run `sync_log_blocks()`, and on failure warn that the hourly reconcile will apply it.

**The course-log block** (`sync_log_blocks()`, R14, change 5): `editingteacher` and `teacher` are prohibited `report/log:view`, `report/log:viewtoday` and `report/loglive:view` with `assign_capability()` in the context of every course where a protected person with `hidelogs` is enrolled, active or not, and the prohibit is removed with `unassign_capability()` from every other course that has it. local_ltuse owns those course-level prohibits, so one set by hand where nobody asked is removed. It runs after a change, in the hourly reconcile (which covers a course the person joins later, within the hour) and when a person with `hidelogs` is deleted.

**`protectionchanged` notification**:
- It is sent to the learner only. It names the level and lists what others now see, with no real name and no actor.
- It tells them:
  - to review their own profile description and interests;
  - that copies already sent, and file author metadata, cannot be changed;
  - that a removed picture must be uploaded again after a downgrade.
- ~~Their new login, if the username changed~~ (removed, change 15: everyone signs in with their email).

**Returns**: `{effectivelevel, warnings[]}`. The warnings cover copies that cannot be recalled. The real identity is never returned.

## ~~Web service `local_ltuse_set_org_protection`~~

Removed (Doug, 2026-10-05 (scope review), changes 9 and 10), with the organisation page `orgprotection.php`, its form, and `service::set_org_protection()`. It stored an organisation's minimum level (`none`, `email` or `firstname`) and its `managers_see_identity` setting, and queued `apply_protection` for every member.

## Hook callback (`db/hooks.php`)

`core_user\hook\before_user_updated`: if the user has a protection row and is not in the bypass set, the callback overwrites the protected fields on the hook's `$user` with the protected values, and sets an `auth` other than `manual` or `nologin` back to `manual` (R3). Nothing else. It is wrapped in `try`/`catch (\Throwable)` and reports a failure through `debugging()`, so it never blocks an account edit (change 17). It is listed in the plugin README as an upgrade risk (R2).

## Observers (`db/events.php`, in the file spec 003 adds)

| Event | Action |
|---|---|
| `\core\event\user_updated` | Returns at once when the user is in the bypass set, has no protection row, or is at `none`. Otherwise re-applies a drifted account, or queues `apply_protection` when the caller is inside a transaction of its own or the user is busy; an undrifted edit to a name the level does not withhold refreshes the held real name. Failures go to `debugging()`. |
| `\core\event\user_deleted` | Call the shared deletion routine (Privacy, below). |

~~`\core\event\user_created`, `\core\event\cohort_member_added`, `\core\event\cohort_member_removed`~~ are removed (change 18): they existed for `ltct_certname` and organisation minimums, and ran on every account.

## Tasks (`db/tasks.php`)

- **`apply_protection`** (adhoc, one class): re-applies one user's level. Queued by the observer when it cannot apply at once.
- **`reconcile_protection`** (scheduled, every `reconcile_minutes`): reads only the protection rows above `none`, returns when there are none, re-applies each account that drifted (a non-site `auth` and linked logins included), and runs `sync_log_blocks()`. It logs counts to the task log and writes no log rows. When any repair fails it throws `protection:err:reconcile`, so core records the run as failed (change 19). ~~Every member of every organisation whose minimum is above `none`, and every user's `ltct_certname`~~ (removed, changes 8, 9).

## Surfaces

The real identity and the **Protected** marker are shown only where `can_view_identity()` is true. A non-entitled viewer never sees the marker.

1. **Profile node** (`local_ltuse_myprofile_navigation()`), web only. The section appears only on a protected person's profile, and on one's own profile only when one is protected or supports someone who is (change 12):
   - a protected learner sees their level, a preview of what others see (including that their organisation and, to course staff, their email address stay visible), and that changes go through the site team;
   - an entitled viewer sees the real identity and the marker;
   - someone who may manage sees a link to the granting page. An unprotected profile carries no link: a first grant is made at intake.
2. **003 Mentoring page and its app handler**: the marker and the real name for entitled viewers.
3. **"People I support"** (`/local/ltuse/protected.php`): the protected people the viewer is entitled to see, with level, real name and display name. ~~A CSV download carrying the marker per row~~ (removed, change 14). It lives in our own plugin, because core report datasources cannot take our column (R7).
4. **Granting page** (`/local/ltuse/protection.php?id=`): the web-service logic above, with the `requested` and `emailchecked` checkboxes, the email warnings, the `hidelogs` checkbox, a warning naming the levels that would delete the person's picture when they have one, and, for the site team only, corrections and the history acknowledgement. ~~**Organisation page** (`/local/ltuse/orgprotection.php`)~~ (removed, change 9).

## Privacy provider

- **Metadata**: the two tables and the `protectionchanged` message provider.
- **Contexts and users**: the user's own rows. Rows where they are the **actor** (`actorid`, `usermodified`) are also covered. The provider implements `core_userlist_provider`.
- **Export**: the user's protection row (level, pseudonym, real values), their log rows with `requested` and `emailchecked`, and the changes they made as an actor, by count and date only, without other users' identities.
- **Delete**: one routine, shared with the `user_deleted` observer. It deletes the user's own protection row and log rows, and sets `actorid` and `usermodified` to 0 wherever they acted.

## Plugin README exceptions (Principle XI)

1. Mutating the object carried by `before_user_updated` (R2). Re-checked by V7 on every core upgrade while anyone is protected; `user_update_user()` is deprecated for 5.3 (MDL-82650).
2. Calling `mod_scheduler`'s `slot` class to re-save slots (R15).

~~3. Reading `logstore_standard_log` and `messages` by indexed columns to decide "has activity" (R13)~~ (removed, change 16).
