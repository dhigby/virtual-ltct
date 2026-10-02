# Contract: Organisations, profile fields and the manager role

This extends [spec 001's declaration contract](../../001-site-config-as-code/contracts/declaration.md) and its [output contract](../../001-site-config-as-code/contracts/output.md). Everything there still holds: the files are read only by `scripts/site_config.py`, the PHP side receives them as JSON on stdin, apply creates only what is absent and never deletes, and nothing names a learner. The CLI is unchanged: `python scripts/site_config.py validate | drift | apply`, with the same `--json`.

## Files

| File | Status | Holds | Rows |
|---|---|---|---|
| `moodle/site/organisations.yaml` | new | The shared categories and the partner organisations. | #8, #15 |
| `moodle/site/profile-fields.yaml` | new | The profile field category and its fields. | #18 |
| `moodle/site/settings/groups.yaml` | new | Separate groups by default; cohort sync suspends and removes roles, never unenrols. | #8, #15 |
| `moodle/site/roles.yaml` | changed | Adds `orgmanager`. | #15 |
| `moodle/site/site.yaml` | changed | Adds `tool_dynamic_cohorts` (pinned) and `enrol_cohort` (enabled). | #8 |

Nothing in any of these files is personal data. Who belongs to an organisation, who manages one, and any learner's field value live only in Moodle (FR-012, constitution III).

## `organisations.yaml`

```yaml
rows: [8, 15]
purpose: <string>                 # one paragraph a reviewer reads first
categories:                       # shared categories, in creation order
  - key: <key>                    # identity: idnumber ltct:<key>
    name: <string>                # display name; adoption matches on it (R6)
    parent: <key>                 # optional: another shared category's key; absent means top level
    why: <string>
organisations:
  - key: <key>                    # identity; never changes once applied; also the stored ltct_org value
    name: <string>                # display name; may change, nothing is duplicated (edge case: rename)
```

Baseline shared categories (R6):

| `key` | `idnumber` | `name` | `parent` | Holds |
|---|---|---|---|---|
| `published` | `ltct:published` | `LTC Published` | absent | The published curriculum, shared by every organisation (FR-003). Adopted from the existing category. |
| `pilots` | `ltct:pilots` | `LTC Pilots` | absent | Stage-7 pilots. Adopted from the existing category. |
| `organisations` | `ltct:organisations` | declared in the file | absent | The parent of every organisation category. |

The organisation list always includes `independent`, for consultants with no partner organisation (edge case). It has the same shape as any other.

### What one organisation entry becomes

Every organisation entry produces exactly these four items, and nothing else differs between organisations (FR-002, constitution VII).

| Item | `idnumber` | Name | Where | Properties |
|---|---|---|---|---|
| Course category | `ltct:org:<key>` | `<name>` | parent `ltct:organisations` | Created with `core_course_category::create()`. |
| Organisation cohort | `ltct:org:<key>` | `<name>` | system context | `visible = 0`. Filled by a rule (R4). |
| Managers cohort | `ltct:org:<key>:managers` | `<name> managers` | system context | `visible = 0`. No rule: filled by hand in Moodle (FR-012). |
| Cohort rule | on cohort `ltct:org:<key>` | `ltct: ltct:org:<key>` | `tool_dynamic_cohorts` | One condition: profile field `ltct_org` equals `<key>`. |

No country cohorts are declared or created (spec Clarifications 2026-10-01). Country is only Moodle's core profile field.

Cohorts are created with `cohort_add_cohort()` and renamed with `cohort_update_cohort()`. Rules are created through `tool_dynamic_cohorts`' `rule` and `condition` persistent classes and its `rule_manager`. No table is written directly (constitution XI), unless R4's fallback is used, as the next section says.

### Condition config (recorded at R4 Verify, 2026-10-01)

`tool_dynamic_cohorts` stores each condition's settings as config keys the applier must write exactly. These were read from release `2026031300` and confirmed on the 5.2.3+ instance (research R4).

| Condition | Class | Config keys and values |
|---|---|---|
| Custom profile field equals | `tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\user_custom_profile` | `profilefield`: `profile_field_ltct_org`; `profile_field_ltct_org_operator`: `3` (`condition_base::TEXT_IS_EQUAL_TO`); `profile_field_ltct_org_value`: the organisation key; `include_missing_data`: `0` |

The configdata is that object as JSON. A rule is written through `rule_manager::process_form()` with the fields in research R4's result. It is created disabled, then enabled by setting `enabled = 1` once `is_broken()` is false. Its `name` is `ltct: <cohort idnumber>`.

If R4 fails and the fallback `local_profilecohort` is used, this table is replaced by the `local_profilecohort` table columns the applier writes, and those are listed in `moodle/local_ltuse/README.md`. Nothing else in this contract changes.

### Validation rules (`validate`)

- `key` matches `^[a-z][a-z0-9-]*$`, like a course slug, so it is valid in an `idnumber` and as a menu option. An organisation key is at most 30 characters. Keys are unique within `categories` and within `organisations`. No shared category key is `org`, so `ltct:<key>` can never collide with `ltct:org:<key>`.
- Organisation `name` values are unique, and so are category `name` values under one parent.
- `organisations` contains `independent`.
- A category `parent` names a category declared earlier in the list, or is absent.
- No entry carries a person, an email address or a user id. An unknown key in any entry is an error.

## `profile-fields.yaml`

```yaml
rows: [8, 18]
purpose: <string>
category:
  name: About your work           # the one profile field category; found by name
fields:
  - shortname: <shortname>        # identity; matches [a-zA-Z0-9_]+ (define_validate_common())
    datatype: menu | checkbox
    name: <string>                # the label a learner sees
    visible: all | teachers | private | none
    locked: 1 | 0                 # 1 and 0, never YAML booleans (spec 001)
    required: 0
    options: [<string>, ...]      # menu only; the stored values
    options_from: organisations   # menu only, instead of options; the organisation keys in declaration order
    area: <competency category>   # ltct_exp_* only; the framework category this checkbox stands for
    why: <string>
```

`visible` maps to core's constants: `all` is `PROFILE_VISIBLE_ALL` (`'2'`), `teachers` is `PROFILE_VISIBLE_TEACHERS` (`'3'`), `private` is `PROFILE_VISIBLE_PRIVATE` (`'1'`), `none` is `PROFILE_VISIBLE_NONE` (`'0'`). Fields are written with `profile_save_category()` and `profile_save_field()` and found by `shortname`. The declared keys map onto `user_info_field` columns of the same name; a menu's `options` become `param1`, one option per line.

### Declared fields (R5)

| `shortname` | `datatype` | `name` | `visible` | `locked` | `options` |
|---|---|---|---|---|---|
| `ltct_org` | `menu` | Organisation | `all` | `1` | `options_from: organisations` (the keys, not the display names) |
| `ltct_role` | `menu` | Role in the work | `teachers` | `0` | a short declared list, extended by reviewed change |
| `ltct_exp_<area>` | `checkbox` | one `competencies.yaml` category name each, in `area` | `all` | `0` | none |

Country is Moodle's own `country` field and is not declared here. The CSV upload column for the organisation field is `profile_field_ltct_org` (R8).

### Validation rules (`validate`)

- `shortname` values are unique and start with `ltct_`.
- Exactly one field has `options_from: organisations`, and it is `ltct_org` with `locked: 1` and no `options` (FR-009). Every other `menu` has `options` and no `options_from`; options are unique and non-empty.
- The `ltct_exp_*` fields' `area` values are the top-level category names of `competencies.yaml` except `Meta`, which holds only `Uncategorized`. They are copied verbatim, `&` and capitalisation included, with one field each. The shortname is declared, not derived, and never changes once applied, so a renamed framework category cannot move a learner's stored value onto a different field.
- `required` is `0` on every field, so a learner can leave an optional field empty (US4). No field declares a default value, because a default writes the same value onto every learner.
- No field collects a sensitive category of data (spec Assumptions); a new field is a reviewed change to this file.

## `roles.yaml` addition

```yaml
  - shortname: orgmanager
    name: Organisation manager
    description: <string>
    archetype: ""                 # every capability managed; a hand grant shows as drift
    contextlevels: [course]       # assigned only through cohort sync, in a course (R2)
    capabilities:
      moodle/course:viewparticipants: allow
      moodle/user:viewdetails: allow
      moodle/site:viewuseridentity: allow
    why: <string>
```

- There is exactly one organisation manager role, used for every partner (FR-005, SC-005).
- It holds no `moodle/site:accessallgroups`, no `moodle/user:viewalldetails`, no `moodle/user:create`, no `moodle/user:update`, and no enrolment, cohort or role-assignment capability (FR-007, FR-013). `validate` fails if any of these appears on `orgmanager`. Without `moodle/user:viewalldetails`, managers do not see `teachers`-visibility fields (R5).
- Nothing is `prohibit`, so a person who also holds spec 003's mentor role keeps its permissions.

## `settings/groups.yaml`

| `name` | `value` | Meaning |
|---|---|---|
| `moodlecourse/groupmode` | `1` | New courses default to separate groups (R3). |
| `moodlecourse/groupmodeforce` | `0` | Not forced, so a forum can still run across organisations (spec 005). |
| `forceloginforprofiles` | `1` | The profile hook runs only while this is on (R9). |
| `hiddenuserfields` | empty | Country stays visible to everyone (FR-008, R5). |
| `enrol_cohort/unenrolaction` | `3` | `ENROL_EXT_REMOVED_SUSPENDNOROLES`: someone who leaves a cohort is suspended and loses the cohort's roles, so history is kept (US3-2) and a removed manager loses `orgmanager` (R7). |

All five names are confirmed in `MOODLE_502_STABLE` (`public/admin/settings/courses.php`, `public/enrol/cohort/settings.php`, `public/lib/enrollib.php`, and `public/user/lib.php` for `forceloginforprofiles`).

## `site.yaml` additions

```yaml
  - component: tool_dynamic_cohorts
    version: <int>                # the $plugin->version of the release verified at R4
    source:
      url: <release archive URL>
      sha256: <hex>
    why: "#8 fills organisation cohorts from the organisation field."
  - component: enrol_cohort
    enabled: 1
    why: "#8 #15 cohort sync enrols an organisation, and its managers, into a course with its group."
```

## Profile hook (`local_ltuse`)

`moodle/local_ltuse/lib.php` defines `local_ltuse_control_view_profile($user, $course, $usercontext): int`, the callback core's `user_process_profile_callbacks()` finds through `get_plugins_with_function('control_view_profile')`. It returns `core_user::VIEWPROFILE_PREVENT` or `core_user::VIEWPROFILE_DO_NOT_PREVENT`, decided as in the data model's "Profile access decision" (R9), and never `VIEWPROFILE_FORCE_ALLOW`. It reads the viewer's managers cohorts with one read of `{cohort}` joined to `{cohort_members}` by indexed columns, because `cohort_get_user_cohorts()` skips hidden cohorts. It also reads `profile_user_record()`, `has_coursecontact_role()` and `has_capability()`, the last on `$usercontext ?? context_user::instance($user->id)`. It writes nothing. It runs only while `forceloginforprofiles` is `1`.

## Publisher

`scripts/publish_moodle.py` sends `groupmode: 1` on course update as well as on create, so courses published before this spec also become separate groups (R3). No other publisher interface changes; `--category` still takes a numeric id.

## Output additions

The four new item types use spec 001's line and `--json` shapes. Item names:

| Item type | `item` | Example |
|---|---|---|
| Course category | `category:<idnumber>` | `category:ltct:org:independent` |
| Cohort | `cohort:<idnumber>` | `cohort:ltct:org:independent:managers` |
| Profile field category | `profilecategory:<name>` | `profilecategory:About your work` |
| Profile field | `profilefield:<shortname>` | `profilefield:ltct_org` |
| Cohort rule | `cohortrule:<cohort idnumber>` | `cohortrule:ltct:org:independent` |

Apply runs them in this order: categories, cohorts, profile field category, profile fields, cohort rules. A rule needs its cohort and its field to exist.

| Situation | Reported as | Blocks apply? |
|---|---|---|
| Declared, present, matching | `[ok]` | No |
| Declared, absent | `missing`; apply creates it and prints `[changed] … created` | No |
| Category absent by `idnumber`, with exactly one adoptable match | drift: `missing` with the message "adoptable" | No |
| Present, but name, parent, visibility, field property, or an added or changed menu option differ | `changed`; apply updates it | No |
| A live option of an `ltct_` menu no longer declared or generated | `extra` on `profilefield:<shortname>`; apply keeps it | No |
| Category absent by `idnumber`, exactly one with the declared name and parent and an `idnumber` that is empty or does not start with `ltct:` | apply sets the `idnumber` and prints `[changed] … adopted` | No |
| More than one category could be adopted | `[fail] ambiguous`, naming the candidates' ids | Yes |
| A declared cohort found outside system context | `[fail] wrong-context` | Yes |
| A declared profile field of another datatype | `[fail] wrong-datatype` | Yes |
| An `ltct:` category, cohort or rule, or an `ltct_` field, no longer declared | `extra` | No (FR-004) |
| `tool_dynamic_cohorts` absent or at the wrong release | the plugin's own `missing` / `wrong-release` | Yes. As in spec 001, apply writes nothing while a declared plugin blocks |

Redaction adds one rule: a cohort is reported by `idnumber` and name only. No line, and no `--json` field, lists a cohort's members or gives a member count.
