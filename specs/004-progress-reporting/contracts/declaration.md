# Contract: Reports, course fields and completion settings

Extends [spec 001's declaration contract](../../001-site-config-as-code/contracts/declaration.md) and [spec 002's](../../002-org-structure-cohorts/contracts/declaration.md). Everything in both still holds. In particular, apply never deletes, and drift never prints learner data.

## Files

| File | Holds |
|---|---|
| `moodle/site/reports.yaml` | New. Report templates, their audiences and schedules (#7, #16). |
| `moodle/site/course-fields.yaml` | New. The course custom field category and its two fields (#7). |
| `moodle/site/settings/completion.yaml` | New. Completion switches and the My courses view (#7). |
| `moodle/site/roles.yaml` | Changed. Two capabilities on `orgmanager`, one on `ltcpublisher`. |
| `competencies.yaml` (repo root) | Unchanged. `site_config.py` reads it as the competency list (R15). There is no copy under `moodle/site/`. |
| `moodle/site/site.yaml` | Changed. `local_ltuse` is re-pinned to the version that adds the two tables and the datasource. |

## `reports.yaml`

```yaml
rows: [7, 16]
purpose: >-
  …
reports:
  - key: progress
    per: organisation
    # Amended by spec 016 decision 2 option (a) (Doug, 2026-10-05 (scope review)): the scope condition is `cohort:idnumber = ltct:org:{org}`, a text condition; the Organisation column is dropped; `validate` refuses `user:profilefield_ltct_org` conditions.
    name: "{org}: learner progress"
    source: core_course\reportbuilder\datasource\participants
    uniquerows: 1
    columns:
      - {column: user:fullnamewithlink, heading: Learner}
      - {column: user:profilefield_ltct_org, heading: Organisation}
      - {column: group:name, heading: Group}
      - {column: course:coursefullnamewithlink, heading: Course}
      - {column: enrolment:timecreated, heading: Enrolled}
      - {column: completion:timestarted, heading: Started}
      - {column: completion:progresspercent, heading: Progress}
      - {column: completion:grade, heading: Quiz result}   # the course total grade (grade_items itemtype course)
      - {column: completion:timecompleted, heading: Completed}
      - {column: access:timeaccess, heading: Last active in course}
    conditions:                                   # the scope; validate requires all three
      - {condition: user:profilefield_ltct_org, values: {operator: equal, value: "{org}"}}
      - {condition: role:name, values: {operator: equal, value: student}}   # a shortname here; stored as the role's id
      - {condition: enrol:plugin, values: {operator: equal, value: cohort}}
    filters: [course:fullname, user:fullname, completion:completed, completion:timecompleted, access:timeaccess]
    audiences:
      - {type: cohortmember, cohort: "ltct:org:{org}:managers"}
    schedule:
      recurrence: weekly
      format: excel
      viewas: recipient
      send_when_empty: 0                          # stored as reportempty 2, REPORT_EMPTY_DONT_SEND
      start: "monday 07:00"                       # site time; becomes timescheduled at first apply
      subject: "{org}: weekly learner progress"
      message: "Your organisation's learner progress this week. The file holds only your own learners."
    why: "#7 US3, US4: a manager follows their own learners across every course (R7, R12)."

  - key: programme
    name: "Programme: completions per course"
    source: core_course\reportbuilder\datasource\participants
    uniquerows: 1
    columns:
      - {column: course:coursefullnamewithlink, heading: Course}
      - {column: course:customfield_ltct_competencies, heading: Competencies this course aims at}
      - {column: course:customfield_ltct_target_level, heading: Level this course aims at}
      - {column: user:username, heading: Enrolled, aggregation: countdistinct}   # the user entity has no id column
      - {column: completion:timecompleted, heading: Completed, aggregation: count}   # counts enrolment rows, see T002 notes
    conditions:
      - {condition: role:name, values: {operator: equal, value: student}}
      - {condition: enrol:plugin, values: {operator: equal, value: cohort}}
    filters: [user:profilefield_ltct_org, completion:timecompleted, enrolment:timecreated]
    audiences:
      - {type: systemrole, role: manager}
    why: "#7 US5: completions per course across every organisation, delivery enrolments only (FR-013)."

  - key: competency-coverage
    name: "Competencies published courses aim at: courses and delivery use"
    source: local_ltuse\reportbuilder\datasource\competency_coverage
    uniquerows: 0
    columns:
      - {column: competency:category,    heading: Category}
      - {column: competency:name,        heading: Competency courses aim at}
      - {column: coverage:courses,       heading: Courses that aim at it}
      - {column: coverage:indelivery,    heading: Of which in delivery}
      - {column: coverage:enrolments,    heading: Delivery enrolments (not learners)}
      - {column: coverage:learners,      heading: Delivery learners}
      - {column: coverage:completions,   heading: Delivery course completions}
    sorting:
      - {column: competency:name, direction: asc}   # sorts on framework order (sortorder)
    conditions: []
    filters: [competency:category, competency:name]
    audiences:
      - {type: systemrole, role: manager}
    why: >-
      FR-013 and US5. One row per framework competency, zero-coverage ones included. It shows
      what published courses aim at and how they are used in delivery, and never any learner's
      level. Pilots (manual enrolments) and managers-cohort enrolments are excluded inside
      the datasource (R10, R15).

  - key: pilots
    name: "Pilots: learner progress"
    # as progress, without per: organisation and the ltct_org condition. Its conditions are
    # role:name {operator: equal, value: student} and enrol:plugin {operator: equal, value: manual};
    # audience systemrole manager; no schedule (R10).
```

The `entity:name` identifiers are report builder's unique identifiers. Each is confirmed against the instance's datasource at quickstart V5 before the task that writes it is closed. A wrong one is a server-side `[fail] unknown` that blocks apply, as an unknown setting does in spec 001.

### Source of each entity (T002)

Checked against `MOODLE_502_STABLE` (5.2.3+). An entity's name is its class's short name (`reportbuilder/classes/local/entities/base.php:116`); `participants` renames none. Paths are under `public/`.

| Entity | Class | Source file | Used here |
|---|---|---|---|
| (datasource) | `core_course\reportbuilder\datasource\participants` | `course/classes/reportbuilder/datasource/participants.php` | `progress`, `programme`, `pilots` |
| `course` | `core_reportbuilder\local\entities\course` | `reportbuilder/classes/local/entities/course.php` | columns `coursefullnamewithlink`, `customfield_ltct_*`; filter `fullname` |
| `course` custom fields | `core_reportbuilder\local\helpers\custom_fields` (`core_course`/`course`) | `reportbuilder/classes/local/helpers/custom_fields.php` | `customfield_<shortname>`, column and filter |
| `user` | `core_reportbuilder\local\entities\user` | `reportbuilder/classes/local/entities/user.php` | columns `fullnamewithlink`, `username`; filter `fullname` |
| `user` profile fields | `core_reportbuilder\local\helpers\user_profile_fields` | `reportbuilder/classes/local/helpers/user_profile_fields.php` | `profilefield_<shortname, lower-cased>`, column, filter and condition |
| `enrol` | `core_enrol\reportbuilder\local\entities\enrol` | `enrol/classes/reportbuilder/local/entities/enrol.php` | condition `plugin` |
| `enrolment` | `core_course\reportbuilder\local\entities\enrolment` | `course/classes/reportbuilder/local/entities/enrolment.php` | column and filter `timecreated` |
| `role` | `core_role\reportbuilder\local\entities\role` | `admin/roles/classes/reportbuilder/local/entities/role.php` | condition `name` |
| `group` | `core_group\reportbuilder\local\entities\group` | `group/classes/reportbuilder/local/entities/group.php` | column `name` |
| `completion` | `core_course\reportbuilder\local\entities\completion` | `course/classes/reportbuilder/local/entities/completion.php` | columns `timestarted`, `progresspercent`, `grade`, `timecompleted`; filters `completed`, `timecompleted` |
| `access` | `core_course\reportbuilder\local\entities\access` | `course/classes/reportbuilder/local/entities/access.php` | column and filter `timeaccess` |
| `competency`, `coverage` | `local_ltuse\reportbuilder\local\entities\competency`, `…\coverage` | ours, not yet written (data-model "Per-competency report") | `competency-coverage`. The class names must be exactly `competency` and `coverage`, since `get_default_entity_name()` is private |

Corrections made by T002:
- `user:id` does not exist: the user entity has no `id` column (`user.php:168-302`). `programme` counts `user:username` with `countdistinct` instead.
- `course:coursefullnamewithlink` is a column only. It is not a filter (`course.php:273-318`), so `progress` filters on `course:fullname` (text).
- A condition's `values` always carries an operator. Each condition is stored with the keys in "Condition values" below.

Confirmed as written: every other column, filter and condition above. In particular:
- `completion:progresspercent` is `TYPE_TEXT` and not sortable.
- `completion:grade` is the course total (`grade_items.itemtype = 'course'`), not one quiz's grade.
- `completion:timecompleted` filters allow only any, not empty, empty, range, last and current.
- `course:customfield_*` exists only once the field exists. It is available only when the field's visibility lets everyone view it.
- The custom fields helper also lists enabled **shared** fields, so a shared field with an `ltct_*` shortname would take the same identifier.

**Aggregation** (`reportbuilder/classes/local/aggregation/`). `set_aggregation()` checks only `compatible($type)` and throws `coding_exception` otherwise (`report/column.php:536-546`), and that breaks the whole report when it is viewed. The UI also leaves out a column's `get_disabled_aggregation()` list (`output/column_aggregation_editable.php:62`), so `apply` accepts an aggregation only if `aggregation::get_column_aggregations($column->get_type(), $column->get_disabled_aggregation())` lists it.

| Column | Type | Aggregation | Allowed |
|---|---|---|---|
| `user:username` | text | `countdistinct` | yes: `count` and `countdistinct` take every type, and none is disabled |
| `completion:timecompleted` | timestamp | `count` | yes. `COUNT()` counts non-null values, one per joined row. A learner with two cohort-sync enrolments in one course counts twice |
| `enrolment:timecreated` | timestamp | `min` (T039's fallback) | yes. `min` takes integer, float, timestamp and boolean, and none is disabled |
| `group:name` | text (the default) | `groupconcatdistinct` (T039's fallback) | yes on Postgres and MySQL only (`groupconcatdistinct.php:51-60`), and on no timestamp. We run Postgres |

**Report creation.** `helpers\report::create_report($data, $default = true)` adds the datasource's default columns, filters and conditions (`helpers/report.php:55-62`). For `participants` those are `enrolment:status`, `user:suspended` and `user:confirmed`, with `enrolment:status` set to active (`participants.php:186-242`). `apply` creates with `$default = false`, or drift would report those as extra.

### Condition values (T002)

`set_condition_values()` replaces the report's whole `conditiondata` with one JSON object (`local/report/base.php:614-619`), so `apply` always writes every condition's keys together. Each key is the condition's unique identifier plus `_operator` or `_value` (`local/filters/base.php:49`). All three scoping conditions are `select` filters, and `select::EQUAL_TO` is `1`. The keys are:

| Condition | Filter class | Keys and stored values | Options it is checked against |
|---|---|---|---|
| `user:profilefield_ltct_org` | `select` (menu field) | `user:profilefield_ltct_org_operator: 1`, `user:profilefield_ltct_org_value: "<org key>"` | the field's menu options, keyed by option text (`user/profile/field/menu/field.class.php:58-64`) |
| `role:name` | `select` | `role:name_operator: 1`, `role:name_value: <student role id>` | `role_get_names(null, ROLENAME_ORIGINAL, true)`, keyed by role **id** (`role.php:166-176`) |
| `enrol:plugin` | `select` | `enrol:plugin_operator: 1`, `enrol:plugin_value: "cohort"` (`"manual"` for `pilots`) | `enrol_get_plugins(true)`, **enabled** plugins only, keyed by plugin name (`enrol.php:171-183`) |

If the value is not among the options, `select::get_sql_filter()` returns `['', []]` (`local/filters/select.php:137-155`). The table then skips that condition with no error (`table/base_report_table.php:81-89`), which widens the report. Conditions apply whether or not they are available to the viewer (`get_active_conditions(false)`).

`select` caches its options for each request, by filter name (`select.php:70-77`). So the proof that each scoping condition yields SQL must run after any change to the `ltct_org` options in the same run, or in a fresh request.

### What one `per: organisation` template becomes

For each organisation key `K` with name `N`, one report:

| Property | Value |
|---|---|
| `component` | `local_ltuse` |
| `area` | `org_<K>_<template key>`, with every `-` in both replaced by `_`. For example `org_seed_company_progress`. A template without `per` has area `<template key>` encoded the same way, for example `competency_coverage`. `area` is `PARAM_AREA` (`reportbuilder/classes/local/models/report.php:87`), which must match `^[a-z](?:[a-z0-9_](?!__))*[a-z0-9]+$` (`lib/classes/param.php:335`). Any other value makes the persistent's `create()` throw. |
| `name` | template name with `{org}` → `N` |
| conditions | template conditions with `{org}` → `K`, stored with the keys in "Condition values"; `role:name`'s shortname resolved to the role's id |
| audience | `core_cohort\reportbuilder\audience\cohortmember`, configdata `{"cohorts": [<id of the cohort whose idnumber is ltct:org:K:managers>]}` (`cohort/classes/reportbuilder/audience/cohortmember.php:59`). `systemrole` stores `{"roles": [<role id>]}` and matches only at system context (`reportbuilder/classes/reportbuilder/audience/systemrole.php:56-70`). Drift maps ids back to idnumber or shortname. An audience is changed in place with `update_configdata()`, because schedules hold audience ids. |
| schedule | `classname` `core_reportbuilder\reportbuilder\schedule\message`, a `name`, `format` `excel`, `userviewas` `-1` (`REPORT_VIEWAS_RECIPIENT`), `recurrence` `3` (`RECURRENCE_WEEKLY`), `audiences` the report's audience ids, `timescheduled` from `start` (it has no default, `models/schedule.php:106`), and `configdata` `{"subject": …, "message": {"text": …, "format": 1}, "reportempty": 2}`. `send_when_empty: 0` is `reportempty` `2` (`REPORT_EMPTY_DONT_SEND`); `0` would send an empty file (`schedule/message.php:40-46`). Drift ignores `timescheduled`, `timenextsend` and `timelastsent`. |

A report is found by (`component`, `area`). Nothing makes that unique, and the UI's Duplicate copies both (`helpers/report.php:125-131`). More than one match is reported as `ambiguous` (an existing `report::KINDS` kind) and fails that report. `apply` then updates none of them.

### Validation rules

As [data-model.md](../data-model.md) "Report". Summarised:
- every `per: organisation` report carries the three scoping conditions and exactly the managers-cohort audience;
- no `allusers` audience;
- FR-010 wording on every label;
- schedules view as recipient;
- every column's aggregation is one that column allows (the "Aggregation" table above);
- every expanded, encoded area matches the `PARAM_AREA` pattern. An org key with `--` or a trailing `-` fails, because it encodes to `__` or a trailing `_`;
- encoded areas are unique and at most 100 characters. Uniqueness is checked after encoding: org `a-b` with template `c` and org `a` with template `b-c` both encode to `org_a_b_c`, and `validate` fails on that;
- `apply` and `drift` build each scoping condition's filter on the server and require `get_sql_filter($values)[0] !== ''`. If it is empty, that report fails, because the condition would be skipped. This happens when the student role is missing, when `enrol_cohort` (or `enrol_manual` for `pilots`) is disabled, or when the org key is not an `ltct_org` option.

### Validation rules for `competency-coverage`

- The `competency-coverage` report must have `conditions: []`. A condition can only drop rows, and the zero rows are the point of the table. A declared condition fails `validate`. A condition added by hand on the server is drift `changed`, and `apply` removes it.
- Its only audience is `systemrole manager`. No `cohortmember` and no `allusers` audience.
- Every column on it is from the `competency` or `coverage` entity.
- The FR-010 check covers the report name and every heading. The check refuses:
  - `certif`, in any case;
  - a retired level name, as data-model.md "Report" defines it (`Learner` only next to "level");
  - `achieved`, `attained` and `reached` anywhere in this report;
  - `competent` as a word.

  Competency headings must say "aim at". The same words are refused in `local_ltuse`'s lang strings for the datasource, by a test.
- `sorting` is a new optional template key: `{column, direction: asc|desc}`, and the column must be in `columns`. `apply` sets it with `toggle_report_column_sorting()`, which sets `sortenabled` and `sortdirection`, and with `reorder_report_column_sorting()`, which sets the precedence (`sortorder`, `helpers/report.php:304`). Drift compares all three. A viewer's own header click overrides the stored sort, for that viewer only.

## Competency list (no new file)

`site_config.py validate` reads `competencies.yaml` and renders the `competencies` payload array. The array is defined in data-model "Competency list". It holds one entry per name, except the `Meta` category, so 42 entries.

`validate` fails on any of these:
- a duplicate name;
- a name over 255 characters;
- a control character, `[` or `]` in a name;
- a name that fails the FR-010 check.

## `course-fields.yaml`

The shape is in [data-model.md](../data-model.md) "Course fields". The two fields are `ltct_competencies` and `ltct_target_level`. Both are text, locked and visible to everyone.

## `settings/completion.yaml`

```yaml
rows: [7]
purpose: >-
  Completion is on for the site and for every course, and a learner sees what each lesson needs.
settings:
  - name: enablecompletion
    value: 1
    why: Without it no course can track completion, and the course web service would not refuse it (R4).
  - name: moodlecourse/enablecompletion
    value: 1
    why: New courses start with completion on (FR-001).
  - name: moodlecourse/showcompletionconditions
    value: 1
    why: The course page shows each lesson's Done / To do (US2, R6).
  # block_myoverview grouping settings: added only if quickstart V8 shows they make
  # "In progress" the first view (R6).
```

## `roles.yaml` changes

```yaml
  - shortname: orgmanager
    capabilities:
      # … spec 002's three, unchanged
      report/progress:view: allow      # per-lesson ticks for their own group (R8)
      report/completion:view: allow    # course completion for their own group (R8)

  - shortname: ltcpublisher
    capabilities:
      # … unchanged
      moodle/course:changelockedcustomfields: allow   # writes ltct_competencies, ltct_target_level (R11)
```

`orgmanager` still holds no `moodle/site:accessallgroups`, which is what keeps both reports to the manager's group. `site_config.py`'s existing `ORGMANAGER_DENY` check is unchanged and still applies.

## Output additions

| Kind | Item | Means |
|---|---|---|
| `missing` (created), `changed`, `extra` | `report <area>` | As for any item. `changed` names the part that differed: columns, conditions, filters, audiences or schedule. |
| `missing` (created), `changed`, `extra` | `course field <shortname>` | As for spec 002's profile fields. |
| `unknown` | `report <area>: <entity:name>` | The datasource has no such column, condition or filter. Blocks apply. |
| `missing` | `report <area>: audience cohort <idnumber>` | The cohort does not exist yet. Blocks the report, not the run. |
| `missing` (created), `changed` | `competency <name>` | Inserted, or its category, sortorder or retired flag set back. |

`report::KINDS` has no `created` kind (`moodle/local_ltuse/classes/siteconfig/report.php:42`). A creation follows spec 002's convention: status `changed`, kind `missing`, message `created` (`profilefields.php:220`, `:247`).
| `extra` | `competency <name>` | Not in `competencies.yaml`. Kept and retired, so it leaves the report. Its course map rows are kept. |
| `unknown` | `report competency-coverage: <entity:name>` | The installed `local_ltuse` lacks the column. Run the plugin upgrade first. Blocks the report. |

Reports are never run by `drift` or `apply`. No row or row count is ever printed, and nothing from `local_ltuse_course_comp`.
