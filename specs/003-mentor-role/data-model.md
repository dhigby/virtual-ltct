# Data model: Mentor relationship and visibility

**Spec**: [spec.md](spec.md) · **Research**: [research.md](research.md)

There are two kinds of data. **Declared** data lives in the repo and `site_config.py` applies
it. **Moodle data** lives only in Moodle, never in the repo (FR-012, Principle III), and moves
with a data restore.

## Declared (repo)

### Role declaration `mentor` (`moodle/site/roles.yaml`)

| Field | Value | Rule (`site_config.py validate`) |
|---|---|---|
| `shortname` | `mentor` | fixed; the plugin looks the role up by it |
| `name` | `Mentor` | |
| `description` | follows the learners assigned to them across every course; changes nothing | |
| `archetype` | `""` | must be empty, so every capability is managed and a hand-granted one shows as drift |
| `contextlevels` | `[user]` | must be exactly `[user]` (FR-002) |
| `capabilities` | `moodle/user:viewdetails`, `moodle/user:viewuseractivitiesreport`, `local/ltuse:viewmenteeprogress`, since spec 016 `local/ltuse:viewidentity` (016 R7 path 2), and since spec 013 `moodle/badges:viewotherbadges` (013 R10), all `allow` | only from the allowlist (research R2). No `prohibit`. The fourth and fifth are reviewed widenings (`MENTOR_ALLOW`, `scripts/site_config.py`). |
| `why` | cites #11 and FR-003, FR-005, FR-006, FR-013 | |

### `allowassign` (new key on a role declaration)

| Field | Value | Rule |
|---|---|---|
| `allowassign` | list of role shortnames, e.g. `manager: allowassign: [mentor]` | each name must be a declared role or a core archetype role; no duplicates |

- It is **additive**. Apply creates any missing `role_allow_assign` row. Drift reports a
  declared pair that is missing and never reports undeclared pairs (research R6).
- `orgmanager` must not carry `allowassign` (its deny list grows by this key).

### Setting `moodlecourse/showreports`

- Declared `0` in `settings/mentoring.yaml` (new; row #11). That is the default for new
  courses only.
- With `1`, a mentor would see assignment submissions and logs (research R2). So each
  `ltct:` course's own `course.showreports` is also kept at `0`: the publisher sends `0` on
  every create and update, `drift` reports a course where it is on as
  `course:<idnumber>:showreports`, and `apply` turns it off again with `update_course()`.

### Cohort `ltct:mentors` (Phase B only)

- A hidden system cohort, declared beside the organisation cohorts.
- It holds the people an organisation manager may pick as a mentor. Membership is Moodle data.

## Moodle data (never in the repo)

### Mentor relationship

The core `role_assignments` row: `roleid = mentor`, `contextid = context_user(learner)`,
`userid = mentor`, `component = ''`, `itemid = 0`, `timemodified`, `modifierid`.

| Rule | Source |
|---|---|
| One row per mentor–learner pair. A learner may have several mentors; a mentor may have many learners. | edge case "two mentors" |
| A mentor cannot be their own mentor. | the observer ignores it; the Phase B page refuses it |
| It is independent of enrolment and organisation. A mentor from any organisation may be assigned. | FR-002, US3-4 |
| Created and ended only through `role_assign()`/`role_unassign()`: core's page, the web services, or the Phase B page. | research R6, R7 |

**States**: *active* while the row exists. *Ended* means the row is deleted. Core keeps no
history row, and the `role_unassigned` event in the log is the record that it ended. The
learner's own records (enrolments, completions, grades, submissions) are never touched by
either transition (FR-009).

### Mentor contact record (`local_ltuse_mentor_contact`, new plugin table)

| Column | Type | Note |
|---|---|---|
| `id` | int | |
| `mentorid` | int, FK `user.id` | indexed |
| `learnerid` | int, FK `user.id` | indexed; unique with `mentorid` |
| `contactid` | int | the `message_contacts.id` the plugin made; only that contact is ever removed |
| `timecreated` | int | |

- Meaning: "`local_ltuse` created the message contact between these two."
- It exists only so that ending a relationship removes the contacts the plugin made and no
  others (research R5).
- Created on `role_assigned` when no contact existed. Deleted on `role_unassigned` when no
  `mentor` assignment still links the pair, and on `user_deleted` of either user.
- It is personal data, so the plugin's privacy provider must declare and export it
  (`\core_privacy\local\metadata\provider` and request plugin).

### Message contact

The core `message_contacts` row, made by `\core_message\api::add_contact()`. Owned by core; the
learner can delete it, and a block outranks it.

### Progress view (derived, never stored)

For each learner the viewer mentors, one row per course:

| Field | From |
|---|---|
| course id, full name, visible | `enrol_get_all_users_courses($learnerid, false)`, plus courses with a completed `course_completions` row |
| enrolment active / suspended / removed | the same call; *removed* when only a completion row remains |
| status | a `course_completions.timecompleted` (read directly, so it survives a removed enrolment) → *Completed on <date>*; else `completion_info::is_enabled()` false → *Completion not tracked*; else the learner's percentage → *In progress, N%* (1–99) or *Not started* (0, or no tracked activity) |
| percentage | core's own calculation on the **learner's** modinfo (`get_fast_modinfo($course, $learnerid)`): the activities with completion on that are visible to the learner and not excluded by a group restriction, then `completion_info::count_modules_completed($learnerid, $ids)`. Not `progress::get_course_progress_percentage()`, which judges visibility for the viewer, the mentor, and drops quizzes and assignments. A suspended learner keeps their progress. |

Profile link, Grades overview link and Message link per learner.

Invariant: a learner appears only if
`has_capability('local/ltuse:viewmenteeprogress', context_user::instance($learnerid))` is true
for the viewer on this request (FR-005).
