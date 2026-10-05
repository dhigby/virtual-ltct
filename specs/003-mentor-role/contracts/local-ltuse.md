# Contract: `local_ltuse` additions (spec 003)

Everything here is built on Moodle's supported extension points: a capability, pages, an
event observer, a profile-navigation callback, a profile-view callback, a mobile handler, a CLI
script and a privacy provider. Every write goes through a public API (Principle XI). Raw reads
are listed in `moodle/local_ltuse/README.md` with their reasons.

## Capability

```php
'local/ltuse:viewmenteeprogress' => [
    'riskbitmask'  => RISK_PERSONAL,
    'captype'      => 'read',
    'contextlevel' => CONTEXT_USER,
    'archetypes'   => [],          // granted only by the declared mentor role
],
```

## Page: Mentoring (`/local/ltuse/mentoring.php`)

| | |
|---|---|
| Access | `require_login()`. No capability is needed to open the page. It shows only the viewer's own relationships, and is empty for everyone else. |
| Section "Learners you mentor" | Candidates are the viewer's `mentor` assignments in `CONTEXT_USER` (raw read: `role_assignments` ⋈ `context`, by `userid`). Each is kept only if `has_capability('local/ltuse:viewmenteeprogress', context_user::instance($learnerid))`. Per learner: name, profile link, Grades overview link (`/grade/report/overview/index.php?id=SITEID&userid=`), Message link, and one row per course as in [data-model.md](../data-model.md) "Progress view". |
| Section "Your mentors" | `get_role_users($mentorroleid, context_user::instance($USER->id))`. Per mentor: name and Message link. |
| Order | Learners and mentors by last name, then first name. Courses: in progress, then not started, then not tracked, then completed (newest first). |
| Never shown | quiz attempts or answers, submission content, logs, hidden profile fields, learners not passing the capability check |
| Strings | in `lang/en/local_ltuse.php`; no CBC level vocabulary and no "certified" (FR-013) |

The data comes from one function, `\local_ltuse\mentoring::for_user(int $userid): array`, so
the page and the app handler cannot differ. The function is pure apart from its Moodle reads.
Its status rule (`progress_status()`) is a pure function that `tests/mentoring_harness.php`
tests without Moodle.

## Navigation

- **Primary navigation**: a "Mentoring" item appears when `for_user()` would be non-empty
  (cached per request). The hook is to be confirmed (research, instance task 3).
- **Profile callback**: `local_ltuse_myprofile_navigation($tree, $user, $iscurrentuser,
  $course)`. On your own profile it adds a "Mentoring" node that links to the page, when you have a mentor or a learner. "Your mentors" would mislead a mentor who has none. On a
  learner's profile, for a viewer who mentors them, it adds "Mentoring" (Phase A). For a
  viewer the Phase B decision allows, it adds "Manage mentors".

## Moodle app (`db/mobile.php`)

```php
$addons = ['local_ltuse' => ['handlers' => ['mentoring' => [
    'delegate'    => 'CoreMainMenuDelegate',
    'method'      => 'mentoring_view',            // \local_ltuse\output\mobile::mentoring_view
    'init'        => 'mentoring_init',            // returns disabled = true with no relationship
    'displaydata' => ['title' => 'mentoring', 'icon' => 'fa-users'],
    'priority'    => 500,
]], 'lang' => [['mentoring', 'local_ltuse'], ['nomentoring', 'local_ltuse']]]];
```

`mentoring_init()` returns `disabled: true` for a user with no relationship, so the app hides
the item (research, Source results T002). `mentoring_view($args)` returns one Ionic template
rendered from the same `for_user()` data.

## Observer (`db/events.php`)

| Event | Condition | Action |
|---|---|---|
| `\core\event\role_assigned` | `objectid` is the `mentor` role id; context level is `CONTEXT_USER`; learner (`context->instanceid`) is not `relateduserid` | if `!\core_message\api::is_contact()`, call `add_contact(mentor, learner)` and insert a `local_ltuse_mentor_contact` row holding the new contact's id |
| `\core\event\role_unassigned` | same | if a row exists for the pair and `user_has_role_assignment(mentor, mentorrole, learnerctx)` is false, delete the row, and call `remove_contact()` only if `api::get_contact()` still returns the row's `contactid` (a contact the two made again themselves is never removed) |
| `\core\event\user_deleted` | always | delete rows where the user is mentor or learner; remove each matching contact on the same `contactid` rule |

- The observers never throw into core. An error is logged with `debugging()`, and the role
  change still stands.
- No observer ever calls `unblock_user()` (research R5).

## CLI (`cli/mentor_contacts.php`)

| Option | Effect |
|---|---|
| `--sync` | For every `mentor` assignment, ensure the contact and its record exist. Idempotent. Run once after the upgrade. |
| `--end-all --mentor=<username>` | `role_unassign_all(['userid' => <id>, 'roleid' => <mentor>])`. The observers then remove the contacts. Asks for confirmation unless `--yes`. |
| (output) | Counts only (`created 3, already contacts 2`). It never prints a name, so terminal logs pasted into an issue hold no personal data (Principle III). |

## Profile hook change (`classes/profile_access.php`, `lib.php`)

`decide()` gains `bool $viewerismentor`. When it is true the hook returns
`VIEWPROFILE_DO_NOT_PREVENT`. `lib.php` passes
`has_capability('local/ltuse:viewmenteeprogress', $usercontext)`. The existing
`viewalldetails` exemption stays for the site team.

## Course reports (`classes/siteconfig/inspector.php`, `drift.php`, `applier.php`; `scripts/publish_moodle.py`)

The site default `moodlecourse/showreports = 0` covers new courses only, and any editing teacher
can turn a course's own "Show activity reports" on. With it on, a mentor sees submissions and
logs (research R2). So:

- the publisher sends `showreports: 0` on every `core_course_create_courses` and `core_course_update_courses`;
- `inspector::check_course_reports()` lists each `ltct:` course with `showreports <> 0` as `changed`, keyed `course:<idnumber>:showreports`;
- `drift` reports these, and `apply` turns each off with `update_course()`.

## Privacy provider (`classes/privacy/provider.php`, new)

- It implements `\core_privacy\local\metadata\provider`, `\core_privacy\local\request\plugin\provider`
  and `core_userlist_provider`.
- It declares `local_ltuse_mentor_contact`. It exports the user's rows as mentor or learner,
  and deletes them for a user or for a user list (in `CONTEXT_USER`).
- The role assignments and contacts themselves are core's to export.

## Phase B: Manage mentors (`/local/ltuse/mentors.php?userid=<learner>`)

Approved by the maintainer's decision of 2026-10-02, "Courses are open across organisations"
(`INTENT.md` Decisions; research R7). That decision also amended the authorisation: spec 002
research R10 and task T078 describe this same page, and a manager's branch is spec 002's
shared check, `may_manage_account()`, not a bare organisation match. The contract below
follows spec 002 R10. It manages the user-context relationship only, the learner's default
mentor; a mentor for one course is spec 008's (`INTENT.md`, 2026-10-04).

| | |
|---|---|
| Decision | `\local_ltuse\mentor_admin::decide(int $viewerid, bool $learnerexists, bool $canassigncore, array $managedkeys, array $person): bool`. It is pure, and `tests/mentor_admin_harness.php` tests it. `$person` holds the facts `\local_ltuse\organisation\access` documents, from `local_ltuse_organisation_person_facts()`. |
| Allows | the site team (`moodle/role:assign` in the learner's context and `mentor` in `get_assignable_roles()`), any learner; or an organisation manager when `\local_ltuse\organisation\access::may_manage_account($viewerid, $managedkeys, $person)` holds (spec 002 R10) |
| Refuses | self and a deleted or missing learner, for everyone. For a manager, also: an empty `ltct_org`, any other organisation, a field the member cohort does not match, and anyone who is not a learner (a site admin, a course contact, a system or category role, another manager, an `ltct:mentors` member). A missing fact refuses. |
| Inputs | gathered on every request by `local_ltuse_may_manage_mentors()` in `lib.php`: `$canassigncore` as above; `$managedkeys` from `local_ltuse_managed_organisation_keys()`; `$person` read only when the viewer is not the site team |
| Writes | `role_assign($mentorroleid, $mentorid, $learnerctx->id)` and `role_unassign(...)`, after a confirmation posted with the sesskey. Every GET and POST rechecks the decision, and the mentor must be one the page would offer. |
| Picker | members of the cohort `ltct:mentors` only, not deleted or suspended, excluding the learner and existing mentors |
| Identity | ~~spec 016 adds `can_view_identity(V, P)` to this page once it lands (spec 002 R10)~~ Not added, and dropped as moot (Doug, 2026-10-05): after `managers_see_identity` was cut, everyone this page authorises already passes `can_view_identity`, the site team by 016 R7 path 1 and an own-organisation manager by path 3. `mentors.php:51-53` records the same ruling in a comment |
| Target | two minutes, unaided (SC-003) |
