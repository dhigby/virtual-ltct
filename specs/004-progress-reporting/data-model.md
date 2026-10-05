# Data model: Progress tracking and reporting

Nothing here holds learner data. Progress records live only in Moodle, and the repo holds the rule and the report definitions that read them.

## Completion rule (R1)

Stated in `scripts/moodle_payload.py`, once, as a function of a module's kind.

| Payload item | Field | Value | Condition |
|---|---|---|---|
| page module | `completion` | `"view"` | always, a withheld-quiz placeholder included |
| quiz module | `completion` | `"pass"` | `threshold_pct` is a positive integer |
| quiz module | `completion` | `"submit"` | no threshold |
| manifest | `completion` | `"all"` | always |

**Validation**: Every module in the manifest has a `completion` value from this table, and `check_moodle_payload.py` refuses one that has none. Only `local_ltuse/classes/completion_rule.php` gives the values Moodle meaning:

| Value | Moodle fields |
|---|---|
| `view` | `completion = 2`, `completionview = 1` |
| `submit` | `completion = 2`, `completionusegrade = 1`, `completionpassgrade = 0` |
| `pass` | `completion = 2`, `completionusegrade = 1`, `completionpassgrade = 1` (requires `gradepass > 0`, set by `create_quiz`) |

An unknown value is an error, never a default.

## Course metadata in the payload (R11)

The manifest gains:
- `competencies`: the README frontmatter `competencies`, as a list of names, verbatim.
- `target_outcome_level`: the frontmatter value, verbatim, or `null` if absent.

The publisher renders the first as `[Name] [Name]`, in frontmatter order, for the `ltct_competencies` field. It sends the second as it is.

## Completion state per module (R2)

`local_ltuse` reads it before an update, and never stores it.

| Current tracking on server | Rule wants | Plugin action | Result `completion` |
|---|---|---|---|
| none (`0`), and the cmid is not a course activity criterion | anything | set fields with `completionunlocked = 1` | `set` |
| none (`0`), but the cmid is a course activity criterion | anything | send nothing about completion | `differs` |
| equals the rule | the same | send nothing about completion | `unchanged` |
| differs from the rule | anything | send nothing about completion | `differs` |
| (new module) | anything | `add_moduleinfo()` with the fields | `set` |

The second row is the guard against `reset_all_state()`: unlocking a module that is still a criterion deletes every `course_completions` row in the course (R2). "A criterion" means a `course_completion_criteria` row with `criteriatype = 4` and `moduleinstance` = the cmid.

"Equals the rule", read from the cm row (DB values are strings): `view` is `completion = 2`, `completionview = 1`, `completiongradeitemnumber` null; `submit` is `completion = 2`, `completiongradeitemnumber` not null, `completionpassgrade = 0`; `pass` is the same with `completionpassgrade = 1`.

Core writes cm completion fields only when completion is on for the site and the course (`completion_info::is_enabled()`). If it is off, the plugin never reports `set`, because nothing would have been written.

`create_page`'s content `unchanged` short-circuit does not skip this table: a rule sent for a cm whose tracking is none counts as a change, and the short-circuit still returns `unchanged` or `differs` for completion.

`differs` makes `publish_moodle.py` exit 1 after the publish, naming each module.

## Course criteria diff (R3)

`local_ltuse/classes/criteria_diff.php` is a pure function:

```text
diff(array $wantedcmids, array $presentcmids): ['add' => int[], 'remove' => int[]]
```

- `wanted`: the cmids of every **visible** module in the course whose idnumber starts with `ltct:<slug>:`. Never the question bank.
- `present`: the `moduleinstance` of every `COMPLETION_CRITERIA_TYPE_ACTIVITY` row in `course_completion_criteria` for the course. `moduleinstance` is the cmid. The rows are read directly by (`course`, `criteriatype = 4`), never through `completion_info::get_criteria()`, which drops rows whose cm is gone, or `completion_criteria_activity::fetch()`, which throws on duplicates. The table has no unique index on (`course`, `moduleinstance`), so `present` is de-duplicated.
- `add` = wanted − present; `remove` = present − wanted. Both sorted.

An added criterion is inserted with `module` = the module's name (`page` or `quiz`, from `owned_modules()`'s `modname`) and `moduleinstance` = the cmid, never `cm.instance`.

Other criteria types (date, role, course, self, grade, unenrol, duration) are left alone and reported as `other-criteria` if present, so a hand-added criterion is visible but never deleted.

**Aggregation**: overall and activity aggregation are both `COMPLETION_AGGREGATION_ALL` (`1`). Any other value is set back and reported as `aggregation-set`.

**Re-aggregation**: only when `remove` is non-empty, and only on rows of `course_completions` for the course with `timecompleted` null. Each row gets `reaggregate = time()` and then `mark_enrolled()`, core's pattern in `completion_daily_task`. `completion_regular_task`, which runs every minute, then marks complete anyone who has met the remaining criteria.

## Report (R7, R9, R10)

A report template in `reports.yaml`. One template becomes one Moodle report, or one per organisation when `per: organisation`.

| Field | Type | Rule |
|---|---|---|
| `key` | string | `[a-z][a-z0-9-]*`, unique. With `per: organisation`, the report's area is `org_<org key>_<key>`; otherwise `<key>`. In both, every `-` becomes `_`, because `area` is `PARAM_AREA` (`^[a-z](?:[a-z0-9_](?!__))*[a-z0-9]+$`, no colon or hyphen). For example `org_seed_company_progress`, `competency_coverage`. Never changes once applied. |
| `name` | string | Display name. With `per: organisation` it may contain `{org}`, replaced by the organisation's name. Safe to change. |
| `per` | `organisation` or absent | Expand once per entry in `organisations.yaml`. |
| `source` | string | A report builder datasource class, for example `core_course\reportbuilder\datasource\participants`. |
| `uniquerows` | `0` or `1` | |
| `columns` | list | `{column: <entity:name>, heading?: <string>, aggregation?: count|countdistinct|...}`, in display order. |
| `conditions` | list | `{condition: <entity:name>, values: {…}}`. With `per: organisation`, a value may be `{org}`, replaced by the organisation key. Conditions are what scope a report, so `validate` requires the three scoping conditions on every `per: organisation` report. |
| `filters` | list | `<entity:name>` strings. Filters only narrow. |
| `sorting` | list or absent | `{column: <entity:name>, direction: asc|desc}`, in precedence order. The column must be in `columns`. Applied with `toggle_report_column_sorting()` (enabled, direction) and `reorder_report_column_sorting()` (precedence, the column's `sortorder`). Drift compares all three. |
| `audiences` | list | `{type: cohortmember, cohort: <idnumber>}` or `{type: systemrole, role: <shortname>}`. `{org}` is allowed in a cohort idnumber. Core stores ids (`{"cohorts": [id]}`, `{"roles": [id]}`), so apply resolves idnumber or shortname to an id and drift maps ids back. An audience is changed in place with `update_configdata()`, because schedules reference its id. |
| `schedule` | object or absent | `{recurrence: weekly, format: excel, viewas: recipient, send_when_empty: 0, start, subject, message}`. Sent to the report's audiences. `viewas` must be `recipient`. `send_when_empty: 0` maps to `reportempty = 2` (`REPORT_EMPTY_DONT_SEND`), not to 0. `start` is required, because the schedule's `timescheduled` has no default; it is a rule such as `monday 07:00`, resolved in site time at first apply. |
| `why` | string | Required. |

**Validation rules** (`validate`):

> **Amended by spec 016 decision 2 option (a) (Doug, 2026-10-05 (scope review)):** the scope condition is `cohort:idnumber = ltct:org:{org}`, a text condition; the Organisation column is dropped; `validate` refuses `user:profilefield_ltct_org` conditions. The two rules below are kept as written for the record; read `cohort:idnumber` for `user:profilefield_ltct_org`, and the select-filter rule for the two remaining select conditions only.

- A `per: organisation` report has, verbatim, the conditions `user:profilefield_ltct_org = {org}`, `role:name = student` and `enrol:plugin = cohort`, and exactly one audience, `cohortmember ltct:org:{org}:managers`. A missing one is a hard failure: it is the scope (FR-005).
- All three scoping conditions are select filters, and a select whose value is not among its options produces no SQL and is silently skipped (MDL-84213), which would widen the report to every participant. So the stored values are what the options are keyed by: `role:name` is the student role's **id**, resolved by shortname at apply; `enrol:plugin` is `cohort`, which must be an enabled enrol plugin; `user:profilefield_ltct_org` is the organisation key, which must be one of the field's menu options. `apply` and `drift` build each scoping condition's filter and require `get_sql_filter($values)[0] !== ''`. If one is empty (student role missing, `enrol_cohort` disabled, the key not an `ltct_org` option), that report fails hard.
- No report has an `allusers` audience.
- Every `heading`, `name` and schedule `subject` is checked against FR-010. It may not contain `certif` (any case), a retired level name as a word (`Advanced Beginner`, `Practitioner`, `Trainer`, `Proficient` anywhere; `Learner` only within three words of "level", so a `Learner` column heading passes), or a phrase that puts a learner at a level ("reached", "achieved", "attained" next to "level"). Course-level headings must use "aims at".
- A schedule's `viewas` is `recipient`, never `creator` (R12).
- Expanded report areas, in their encoded form, match the `PARAM_AREA` pattern, are unique, and are at most 100 characters. Uniqueness is checked after encoding: org `a-b` with template `c` and org `a` with template `b-c` both give `org_a_b_c`, and that is a hard failure.

**Lifecycle**: absent → created. Present and equal → nothing. Present and differing → its columns, conditions, filters, audiences and schedule are set back to the declaration, reported as `changed`. No longer declared → `extra`, kept. There is no unique index on (`component`, `area`), and a report duplicated in the UI keeps both, so more than one live report with one area is an error for that report; the applier never updates an arbitrary one. Drift ignores the schedule's `timescheduled`, `timenextsend` and `timelastsent`.

## Course fields (R11)

`course-fields.yaml`:

```yaml
rows: [7]
category: LTC curriculum          # the course custom field category, found by name
fields:
  - shortname: ltct_competencies
    name: Competencies this course aims at
    type: text
    locked: 1
    visibility: everyone          # everyone | teachers | nobody  -> 2 | 1 | 0
    why: …
  - shortname: ltct_target_level
    name: Level this course aims at
    type: text
    locked: 1
    visibility: everyone
    why: …
```

**Validation**: `shortname` matches `^ltct_[a-z0-9_]+$`. `type` is `text`. The names pass the FR-010 check. The two fields above must be present, because the publisher writes them. No competency name in `competencies.yaml` contains `[` or `]`.

**Lifecycle**: as spec 002's profile fields: created if absent, set back if different, `extra` if undeclared, never deleted. The category lookup keeps only `component = core_course`, `area = course` categories, because the course handler also returns enabled shared categories. Core does no shortname-uniqueness check when a field is saved, so the applier looks the shortname up across the course and shared fields before it creates one. Every text-field configdata key is written (`displaysize 50`, `maxlength 1333`, `ispassword 0`, …), and on update the declared keys are merged into the live configdata.

**Writing the values** (publisher): the course web services silently drop a custom field that is unknown or that the caller may not edit, and `core_course_update_courses` reports a refused course as a `warnings` entry, not an error. So the publisher treats non-empty `warnings` as a failure and reads the two values back with `core_course_get_courses_by_field`, comparing `valueraw`.

## Competency list (R15)

There is no file under `moodle/site/`. `site_config.py` reads the repo-root `competencies.yaml` and renders it into the payload:

```json
"competencies": [
  {"name": "Computer Hardware", "category": "Core Technical", "sortorder": 1},
  {"name": "OS Basics",         "category": "Core Technical", "sortorder": 2}
]
```

| Field | Type | Rule |
|---|---|---|
| `name` | string | Verbatim from `competencies.yaml`, including `&` and capitalisation. Unique. At most 255 characters. No control characters, `[` or `]`. |
| `category` | string | The YAML category key, verbatim. At most 255 characters. |
| `sortorder` | int | 1-based position across the whole file: category order, then list order. |

**Validation** (`validate`):
- No name is listed twice.
- The `Meta` category (`META_CATEGORY`, which holds only `Uncategorized`) is not rendered. The list has 42 entries.
- Every name passes the FR-010 label check.

**Plugin table `local_ltuse_competency`**: `id`, `name` (char 255, unique index), `category` (char 255), `sortorder` (int), `retired` (int 1, default 0), `timemodified`. It holds no user data.

**Lifecycle** (subject `competency <name>`):
- Absent: inserted, reported as a creation (status `changed`, kind `missing`, message `created`, as spec 002 does; `report::KINDS` has no `created`).
- Category or sortorder differs: updated, reported `changed`.
- Declared but `retired = 1`: un-retired, reported `changed`.
- Live but not declared: reported `extra`, kept and set to `retired = 1`. Its map rows are kept.

Rows are never deleted. Application order: after spec 002's arrays and the course fields, and before `reports`, because the report's source needs the list.

## Course-to-competency map (R15)

**Plugin table `local_ltuse_course_comp`**: `id`, `courseid` (FK `course.id`), `competencyid` (FK `local_ltuse_competency.id`), `timemodified`. Unique index on (`courseid`, `competencyid`). It holds no user data.

**Written by** `local_ltuse_set_course_competencies` only, from the manifest `competencies` list. Within one delegated transaction, the call replaces the course's rows with the declared set.

**Validation**:
- The course's `idnumber` starts with `ltct:`.
- Every name resolves to a non-retired row of `local_ltuse_competency`. Otherwise the call fails, and the map is unchanged.
- The publisher drops names in the `Meta` category before the call and says so in its output.

**Lifecycle**:
- Republishing replaces the course's set.
- A course deleted in Moodle drops out of every count, because the datasource joins `{course}`. Its map rows are left and harmless.
- A retired competency's rows stay until each course's next publish.

## Per-competency report (R15, FR-013)

Datasource `local_ltuse\reportbuilder\datasource\competency_coverage`:
- **Main table**: `local_ltuse_competency`.
- **Base condition**: `retired = 0`, set with `add_base_condition_sql()`. It is not a report condition, so it cannot be removed by editing the report.
- **Entities**: `competency` and `coverage`, both on the main table. There is no user, course or enrolment entity. An entity's name is its class's short name (`get_default_entity_name()` is private in 5.2), so the classes are `local_ltuse\reportbuilder\local\entities\competency` and `…\coverage`. They override `get_available_columns()`, `get_available_filters()` and `get_available_conditions()`, not `initialise()`.

| Column | Heading (lang string) | Value |
|---|---|---|
| `competency:category` | Category | `category` |
| `competency:name` | Competency courses aim at | `name`. Sorts on `sortorder`. |
| `coverage:courses` | Courses that aim at it | Mapped courses that exist with `idnumber LIKE 'ltct:%'`. |
| `coverage:indelivery` | Of which in delivery | Those with an enabled (`status = 0`) cohort-sync instance whose role is `student`. |
| `coverage:enrolments` | Delivery enrolments (not learners) | Active (`ue.status = 0`) user enrolments on those instances. Users not deleted. |
| `coverage:learners` | Delivery learners | The same, `COUNT(DISTINCT userid)`. |
| `coverage:completions` | Delivery course completions | `course_completions` with `timecompleted` set in mapped courses, where the user has a cohort/student enrolment in that course in any status. |

Every count column is `TYPE_INTEGER`. Its value is one correlated subquery on `competencyid = {c}.id`, with `set_disabled_aggregation_all()`, added with `add_field($sql, $alias, $params)`: the alias is required for a subquery, and every param name comes from `database::generate_param_name()` (`rbparamN`), or `validate_params()` throws. It is never NULL, so a competency no course declares shows 0 in every count. The student role is matched by `shortname`, never by id.

**Rules**:
- The report shows one row per non-retired competency: 42 today.
- No count column can be removed from the SQL by a report condition or filter.
- No column shows a level or names a person.

## Rendered payload additions

The payload `site_config.py` sends to `site_config.php` gains:

```json
"course_field_category": "LTC curriculum",
"course_fields": [{"shortname": "...", "name": "...", "type": "text", "locked": 1, "visibility": 2}],
"reports": [{
  "area": "org_seed_company_progress",
  "name": "Seed Company: learner progress",
  "source": "core_course\\reportbuilder\\datasource\\participants",
  "uniquerows": 1,
  "columns": [...], "conditions": [...], "filters": [...],
  "audiences": [{"type": "cohortmember", "cohort": "ltct:org:seed-company:managers"}],
  "schedule": {"name": "...", "recurrence": 3, "format": "excel", "userviewas": -1, "start": "monday 07:00",
               "configdata": {"subject": "...", "message": {"text": "...", "format": 1}, "reportempty": 2}}
}]
```

`subject`, `message` and `reportempty` are not schedule columns: core's `message` schedule type reads them from `configdata`, with `message` as an editor array. `reportempty = 2` is `REPORT_EMPTY_DONT_SEND`; `0` would send an empty attachment. The applier resolves `start` to `timescheduled`, and each audience's idnumber or shortname to an id.

It also gains `"competencies": [{"name", "category", "sortorder"}]`, as defined in "Competency list" above.

Order of application, after spec 002's arrays: course field category, course fields, competencies, reports. Reports come last, because an audience needs its cohort and the per-competency report needs its list.

## Not modelled

- **Per-competency counts**: Moodle computes them when the report is viewed. They are never stored in the plugin's tables, never printed by `apply` or `drift`, and never written to the repo.
- **Progress record** (spec Key Entities): Moodle's `course_completions`, `course_modules_completion`, grades and `user_lastaccess`. Read by reports, never by the repo.
- **Scope**: spec 002's `ltct_org` field and managers cohort. This spec adds no scope of its own.
