# Contract: Events and office-hours declaration

This extends [spec 001's declaration contract](../../001-site-config-as-code/contracts/declaration.md), as specs 002, 004 and 013 do. Everything in 001's contract still holds. Field rules are in [data-model.md](../data-model.md).

## New files

| File | Holds |
|---|---|
| `moodle/site/office-hours.yaml` | The office-hours course, its scheduler activity and the group name template (R16, R19). |
| `moodle/site/dashboard.yaml` | The blocks the default dashboard must carry: `calendar_upcoming` (R18). |
| `moodle/site/settings/calendar.yaml` | `enablecalendarexport`, `calendar_customexport`, `calendar_adminseesall`, `timezone: UTC`, `forcetimezone: 99` (R12, D7). Cites row 21. |
| `moodle/site/settings/scheduler.yaml` | The six `mod_scheduler/*` site settings (R19). Cites row 21. |

## Changes to existing files

- **`site.yaml`**:
  - adds `mod_scheduler` version `2026080400`, sha256 `928ea42b8a4835dcc2c398d2c069cd198c8d41e3130883c2299f36e090a97826`, URL `https://marketplace.moodle.com/api/plugins/mod_scheduler/versions/2026080400/download`. Its `why` cites #21, R9 and quickstart V10;
  - re-pins `local_ltuse`.
- **`roles.yaml`**:
  - `orgmanager`: `moodle/calendar:manageentries: allow`. The description changes from "Changes nothing" to "posts events in its organisation's own courses".
  - `student`: `mod/scheduler:seeotherstudentsbooking: inherit`.
  - `teacher`: the three scheduler "other teachers" capabilities as `inherit`.
  - Each `why` cites #21 and the R section.
- **`organisations.yaml`**: adds the `mentoring` category.
- **`README.md`**: adds the site team's recipes.
  - **Post a site event**: Calendar, New event, type Site.
  - **Post a live session**: put the join link in the description, never in Location. Add the follow-up note or recording afterwards.
  - **Send an organisation-wide message**: Site administration, Users, Bulk user actions. Filter by cohort `ltct:org:<key>`, then "Send a message" (D2).
  - **How office hours work**: groups are automatic. Never edit them by hand, because the reconcile task undoes it. Pick the mentor relationship on the learner's profile (003).
  - **When booking another mentor's slot is possible** (plan decision 4).

## Payload arrays (PHP side)

Two arrays are handled after spec 013's:

1. **`officehours`**: the rendered `office-hours.yaml`, with `guardtime` in seconds. The category is resolved by its `ltct:` idnumber.
2. **`dashboard`**: the list of `{block, region}`.

`apply`:
- **`officehours`**:
  - It creates the course with `create_course()` when no course has the idnumber `ltct:officehours`, and otherwise updates its declared fields with `update_course()`.
  - It creates the scheduler with `create_module()` when no course module in the course has the idnumber `ltct:officehours:scheduler`, and otherwise sets its declared instance values and its course-module group mode.
  - It ensures the course has exactly one manual enrolment instance named `ltct:officehours`, adding it with `add_instance()`.
  - It then runs `\local_ltuse\officehours::reconcile()` once and reports counts only (`groups 4, members +2 −0, enrolments suspended 1`).
- **`dashboard`**: for each declared block not already on the default `my-index` page, it calls `add_block()` there, with the default `my_pages` row as the subpage. It never removes a block and never resets a user's own dashboard.
- **Settings**: applied as 001 applies any setting. As with spec 013's plugins, `mod_scheduler` is installed before `apply` runs. Until it is, its settings are `unknown`, which stops the run.

`drift`:
- **`missing`**:
  - the course, the activity or the enrolment instance does not exist;
  - a declared dashboard block is absent from the default page.
- **`changed`**:
  - a declared course or instance field differs;
  - the course's group mode or forced flag differs.
- **`extra`**:
  - a second course or course module carrying the declared idnumber, which is also `ambiguous` and blocks the run;
  - a group in the course with an `ltct:mentor:` idnumber that no `mentor` assignment explains. It is reported as a count and repaired by the reconcile task, not by `apply`.

Memberships and enrolments are learner data: `drift` reports counts, never names.

`apply` never deletes the course, the activity, a group, a slot or an appointment.

## Validation (`validate`)

These are the rules in data-model.md, plus:
- `mod_scheduler` is pinned whenever `office-hours.yaml` exists;
- `settings/calendar.yaml` declares `timezone` as a valid zone identifier;
- the group name template holds `{n}` and no name placeholder;
- `orgmanager` holds no calendar capability other than `manageentries`;
- `student` does not allow `mod/scheduler:seeotherstudentsbooking`;
- `dashboard.yaml` names no block twice;
- `hiddenuserfields` (`settings/groups.yaml`) never contains `timezone`, so the profile always shows the zone (R14).
