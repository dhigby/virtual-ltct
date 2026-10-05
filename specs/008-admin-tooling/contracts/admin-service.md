# Contract: the `ltuse_admin` service and the course-mentor sync

## Service

Declared in `moodle/local_ltuse/db/services.php`, in a block headed `// Spec 008: administration` (append-only; specs 006 and 016 append their own blocks):

```php
'LTC administration' => [
    'shortname' => 'ltuse_admin',
    'functions' => [ /* every local_ltuse_admin_* below */, 'core_webservice_get_site_info' ],
    'requiredcapability' => 'local/ltuse:administer',
    'restrictedusers' => 1,
    'enabled' => 1,
    'uploadfiles' => 0,
],
```

Every function: `validate_context(context_system::instance())`, `require_capability('local/ltuse:administer', …)`, then the core capability for the write it makes (defence in depth; the `ltctadmin` role holds both). Read-only functions declare `'type' => 'read'`, the rest `'write'`. All are idempotent. None returns a database id the operator must type back; rows are identified by row number and masked email.

**Shape rule.** Every `preview_*` takes the whole file's rows and returns, first, a file-level `refusal` (any named course, cohort or organisation cohort that does not exist; data-model §1) or else one result per row. Every `apply_*` takes **exactly one row** plus that row's `expectedoutcome` (for a move, also the expected per-course set) and applies the "further along" rule (research R15): same → apply; further along → finish or `already done`; otherwise → `refused: changed since the preview`.

| Function | Type | Params (summary) | Returns | Calls |
|---|---|---|---|---|
| `local_ltuse_admin_check` | read | — | the settings in research R13 and their values; capabilities from research R12 the token user lacks; whether 006 and 016 are installed and `level_available()` per level | research R12, R13 |
| `local_ltuse_admin_list` | read | `what` (`cohorts`\|`courses`), `orgkey?` | idnumbers and names | |
| `local_ltuse_admin_preview_intake` | read | `rows[{row, email, firstname, lastname, organisation, country?, protection?, pseudonym?, emailchecked?, courses[]?}]`, `showpeople` | `refusal?`, `rows[{row, key, outcome, reason, changes[]}]` | `admin\intake_service` → `intake_rules`; 016 `level_available`, `effective_level`, `is_settled` (no `org_minimum` since 2026-10-05) |
| `local_ltuse_admin_apply_intake_row` | write | one row, `expectedoutcome` | `{row, outcome, status: done\|already_done\|refused, reason}` | per-email lock; `user_create_user`; 016 `set_protection`/`is_settled`/`apply`; `profile_save_data`; 002 `organisation\actions::do_enrol` |
| `local_ltuse_admin_preview_cohort_enrolment` | read | `cohortidnumber`, one of `courseidnumber` \| `pathwaykey` \| `mirrorfrom`, `action` (`ensure`\|`remove`) | `refusal?`, per course: `would_add` / `would_enable` / `would_disable` / `already` / `refused` + reason, member count | `admin\cohort_enrolment` → `enrolment_rules` |
| `local_ltuse_admin_apply_cohort_enrolment` | write | one (cohort, course) pair, `action`, `expectedoutcome`; for a pathway the CLI calls it once per course after one `apply_pathway_assignment` | `{status, reason}` | `enrol_cohort_plugin::add_instance`, `update_status`; then `course_mentor_sync::sync_course` |
| `local_ltuse_admin_apply_pathway_assignment` | write | `cohortidnumber`, `pathwaykey`, `expectedoutcome` | `{status, reason}`; refused unless `enrolment_rules` allows the cohort in every catalogue course | 006 `assignments::assign($key, $cohortid, true)` (research R11) |
| `local_ltuse_admin_preview_suspension` / `apply_suspension` | read / write | preview: `rows[{row, email}]`, `suspend`; apply: one row, `suspend`, `expectedoutcome` | per row | 002 `organisation\actions::do_suspend` / `do_reactivate` (research R6) |
| `local_ltuse_admin_preview_move` / `apply_move` | read / write | preview: `rows[{row, email, organisation}]`; apply: one row, `expectedoutcome`, `expectedcourses` | per learner: courses `kept`/`gained`/`suspended_by_rule`/`lost`; refused if any `lost` | `admin\move_service` → `move_rules`; no 016 call (protection neither read nor set, Doug, 2026-10-05 (scope review)); `profile_save_data` |
| `local_ltuse_admin_preview_cohort_members` / `apply_cohort_members` | read / write | preview: `rows[{row, email, cohortidnumber, action}]`; apply: one row, `expectedoutcome` | per row, with the "manages own organisation" note (research R9) | `cohort_add_member`, `cohort_remove_member` (managers and `ltct:mentors` only) |
| `local_ltuse_admin_preview_mentors` / `apply_mentors` | read / write | preview: `rows[{row, learneremail, mentoremail}]` or `endmentoremail`; apply: one row (or one learner of the end-all list), `expectedoutcome` | per row | `role_assign`, `role_unassign` (003 `mentor`, user context) |
| `local_ltuse_admin_preview_course_mentors` / `apply_course_mentors` | read / write | preview: `rows[{row, courseidnumber, mentoremail, learneremail?, cohortidnumber?}]`, `remove`; apply: one row, `remove`, `expectedoutcome` | per row; then the sync result for the course | `local_ltuse_course_mentor` get-or-create / delete, then `admin\course_mentor_sync::sync_course()` |
| `local_ltuse_admin_summary` | read | `orgkey`, `showpeople` | cohort members (masked), suspended count, per-course enrolment counts; pathway-made instances (`customchar2 = 'pathway'`) whose course is in none of the cohort's enrolling pathways' `catalogue::courses()` | |

`showpeople` false returns masked emails and no names. The masking is done on the server, so unmasked data never crosses the network unless asked. The confirmation code never depends on it (research R15).

## Errors

A refusal is a result row (`refused`, with a `local_ltuse` language string as `reason`), not an exception, so one bad row never stops the others. An exception is reserved for a configuration fault (missing capability, wrong setting, 016/006 call failing), and its message names the fault, never a person.

## Course-mentor sync

`\local_ltuse\admin\course_mentor_sync`:

- `sync_course(int $courseid): array` — computes `course_mentor_rules` (data-model §4) for one course, then enrols, unenrols and regroups to match. Returns counts.
- `sync_learner(int $userid)` — every `ltct:` course the learner is in.
- `sync_mentor(int $mentorid)` — every course where the mentor is a course mentor or a default mentor of an enrolled learner.

Observers (`db/events.php`, block `// Spec 008`):

"An `ltct:` course" below means idnumber `^ltct:[^:]+$`, not `ltct:officehours`.

| Event | Filter | Calls |
|---|---|---|
| `\core\event\role_assigned`, `role_unassigned` | role `mentor`, context level user | `sync_learner($event->contextinstanceid)` (the learner owns the context) and `sync_mentor($event->relateduserid)` (the mentor holds the role) |
| `\core\event\user_enrolment_created`, `user_enrolment_updated`, `user_enrolment_deleted` | an `ltct:` course; instance (from `enrolid`) not `ltct:coursementor`. **Never** the user's role at event time: core assigns the role after `user_enrolment_created` and removes it before `user_enrolment_deleted`. | `sync_course($event->courseid)` |
| `\core\event\enrol_instance_updated`, `enrol_instance_deleted` | an `ltct:` course; not the `ltct:coursementor` instance | `sync_course($event->courseid)` (disabling fires no per-user event) |
| `\core\event\user_updated` | none (the event does not say which fields changed; the call is cheap and idempotent) | `sync_learner($event->relateduserid)` |
| `\local_ltuse\event\pathway_courses_changed` (006) | — | `cohort_enrolment::ensure` for `other.added` × `cohorts_for($key, true)` (research R11) |

`cohort_enrolment::ensure()` and `remove()` also call `sync_course()` directly. The sync ignores events for its own instance (`ltct:coursementor`) and takes a per-course lock, so it cannot loop and an observer never interleaves with the reconcile on one course.

Observers and the task do nothing while `local_ltuse/coursementorsync` is 0 (research R10). Scheduled task `\local_ltuse\task\course_mentor_reconcile`, hourly (`db/tasks.php`), runs `sync_course` for every `ltct:` course, removes any `local_ltuse`-component Teacher assignment with no reason left, runs `cohort_enrolment::reconcile_pathways()`, and reports counts only.

PHPUnit cases (in `tests/admin_test.php`, synthetic data): `role_unassign` of a mentor removes their `ltct:coursementor` enrolment, their `local_ltuse` Teacher role and their group membership in the same request; disabling a cohort-sync instance does the same for that cohort's course mentors; suspending a learner's account does the same; a mentor also enrolled through another method loses Teacher.

## Version

`moodle/local_ltuse/version.php` bumps to `20261008NN` (agreed 2026-10-04: 016 uses 2026100500, 006 uses 20261006NN; whichever merges second re-bumps above main, renumbers its `upgrade.php` savepoint and re-pins `site.yaml`). `db/install.xml` and `db/upgrade.php` gain `local_ltuse_course_mentor`.
