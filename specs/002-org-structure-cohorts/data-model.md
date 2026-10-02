# Data Model: Partner organisations, cohorts and profiles

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Research**: [research.md](research.md)

This feature adds no database table of its own. It extends spec 001's declaration ([data model](../001-site-config-as-code/data-model.md)) with two new files and one new settings file, and the applier with four new item types. Its entities live in two places:

- **Declared** in `moodle/site/` in the public repo: organisations, shared categories, profile fields, the `orgmanager` role, the group and cohort-sync defaults. These hold names and definitions only.
- **Moodle only**: who belongs to which cohort, who manages which organisation, each learner's profile values, each course's groups and cohort-sync instances. These are learner data. The repo never declares, reads back or reports them (constitution III, FR-012).

`site_config.py` expands the organisation list into the items the applier works on. PHP never sees an "organisation". It receives categories, cohorts, profile fields and cohort rules, each carrying its own `idnumber` or `shortname`.

| Entity | Where it lives | Public repo? | New or reshaped |
|---|---|---|---|
| Organisation declaration file | `moodle/site/organisations.yaml` | Yes | New |
| Shared category | `organisations.yaml` → `categories` | Yes | New |
| Partner organisation | `organisations.yaml` → `organisations` | Yes | New |
| Organisation category | derived, one per organisation | Derived | New |
| Organisation cohort | derived, one per organisation | Derived | New |
| Managers cohort | derived, one per organisation | Derived | New |
| Cohort rule | derived, one per organisation | Derived | New |
| Profile field file | `moodle/site/profile-fields.yaml` | Yes | New |
| Profile field category | `profile-fields.yaml` → `category` | Yes | New |
| Profile field declaration | `profile-fields.yaml` → `fields` | Yes (definition only) | New |
| Role declaration `orgmanager` | `moodle/site/roles.yaml` | Yes | Reshaped (one new role) |
| Plugin declarations | `moodle/site/site.yaml` | Yes | Reshaped (`tool_dynamic_cohorts`, `enrol_cohort`) |
| Groups settings file | `moodle/site/settings/groups.yaml` | Yes | New |
| Item result | run report line | Evidence only | Reshaped (new subjects and kinds) |
| Rendered payload | stdin of `site_config.php` | No | Reshaped (four new arrays) |
| Cohort membership | Moodle | No | Moodle only |
| Organisation manager assignment | Moodle (managers cohort membership) | No | Moodle only |
| Profile value | Moodle | No | Moodle only |
| Course group and cohort-sync instance | Moodle | No | Moodle only |

The spec's key entities map onto these: **Partner organisation** is the organisation entry plus its derived category and cohorts; **Organisation cohort** is the derived cohort with its rule; **Organisation manager** is a managers-cohort membership plus the `orgmanager` role it brings through cohort sync; **Profile field** is a profile field declaration.

### Identity

Every item is found in Moodle by a stable key, never by display name (spec edge case "name changes", R6).

| Item | Moodle table, column | Value |
|---|---|---|
| Shared category | `course_categories.idnumber` | `ltct:<key>`, e.g. `ltct:published`, `ltct:pilots`, `ltct:organisations` |
| Organisation category | `course_categories.idnumber` | `ltct:org:<orgkey>` |
| Organisation cohort | `cohort.idnumber` | `ltct:org:<orgkey>` |
| Managers cohort | `cohort.idnumber` | `ltct:org:<orgkey>:managers` |
| Cohort rule | the plugin's rule, found through its cohort | the cohort's `idnumber` |
| Profile field category | `user_info_category.name` | the declared name (core has no idnumber here) |
| Profile field | `user_info_field.shortname` | `ltct_org`, `ltct_role`, `ltct_exp_<area>` |
| Role | `role.shortname` | `orgmanager` |

A category and a cohort may share the text `ltct:org:<orgkey>`; they are different tables, and each is looked up only in its own.

---

## Organisation declaration file

`moodle/site/organisations.yaml`. The one list of partners we host, plus the shared categories (FR-001, FR-003). It declares no country cohorts (spec Clarifications 2026-10-01).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `rows` | list of integers | Yes | `[8, 15]`. Every cited row must exist in `moodle/REQUIREMENTS.md`, as for a settings file. |
| `purpose` | text | Yes | One paragraph a reviewer reads first. |
| `categories` | list of shared categories | Yes | Must include `published`, `pilots` and `organisations`. |
| `organisations` | list of partner organisations | Yes | Must include `independent` (spec edge case). |

### Validation rules (`site_config.py validate`)

- The file parses as YAML and matches its schema; an unknown key is an error.
- Category keys are unique, and organisation keys are unique.
- No field anywhere in the file names a person, an email address or a user id. The schema has no field that could hold one, and an unknown key is an error (constitution III).

---

## Shared category

A course category that is not owned by one organisation: the published curriculum, pilots, and the parent that holds the organisation categories (FR-003).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `key` | `[a-z][a-z0-9-]*` | Yes | Identity. Rendered as `idnumber: ltct:<key>`. |
| `name` | text | Yes | Display name, e.g. `LTC Published`. Adoption matches on it (R6). |
| `parent` | category key, or absent | No | Absent means top level. |
| `why` | text | Yes | Which row or spec needs it. |

### Validation rules

- `parent` names a declared shared category; parents form no cycle.
- `key` is not `org`, so a shared category's idnumber can never collide with `ltct:org:<orgkey>`.
- `organisations` is declared, because every organisation category sits inside it.

---

## Partner organisation

One organisation whose learners we host (FR-001, FR-002). One list entry generates its whole shape, so two organisations cannot differ (constitution VII, SC-005).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `key` | `[a-z][a-z0-9-]*`, at most 30 characters | Yes | Identity, and the value stored in each learner's `ltct_org` field. Never changes once applied. |
| `name` | text | Yes | Display name. A rename changes only this (spec edge case). |

There are deliberately no other fields: no branding, no per-organisation role, no per-organisation setting (spec edge case "special role", constitution VII). Adding one is a reviewed change to this model, not to one entry.

### Derived items

From each entry, `site_config.py` renders:

| Item | `idnumber` | Name | Parent / context | Notes |
|---|---|---|---|---|
| Organisation category | `ltct:org:<key>` | `<name>` | shared category `organisations` | Holds organisation-specific courses only (FR-003). |
| Organisation cohort | `ltct:org:<key>` | `<name>` | system context, `visible = 0` | Filled by its cohort rule (FR-010). |
| Managers cohort | `ltct:org:<key>:managers` | `<name> managers` | system context, `visible = 0` | No rule. Filled by hand in Moodle (FR-012). |
| Cohort rule | (of `ltct:org:<key>`) | `ltct: ltct:org:<key>` | | Condition `ltct_org` equals `<key>`. See Cohort rule. |
| Menu option | | `<key>` | option of `ltct_org` | See Profile field declaration. |

### Validation rules

- `key` contains no `:`, so an `idnumber` splits unambiguously, and no character a Moodle menu option cannot hold (no newline).
- An organisation with key `independent` is present, so no learner sits outside the structure.
- Every derived name fits the column it lands in (category and cohort `name`, 254 characters). `<name> managers` is the longest.
- The key cannot be checked for immutability offline. On the server, a key change shows as a new organisation plus an `extra` for the old one, and the reviewer sees both (R7).

### State transitions

```text
                 apply                         apply (name changed)
declared ──────────────────► applied ─────────────────────────────► applied, renamed
   │      category + 2 cohorts            category and cohort names updated;
   │      + rule created if absent        idnumbers, menu value and memberships unchanged
   │                                 │
   │                                 │ removed from organisations.yaml
   │                                 ▼
   │                            undeclared ── drift/apply: [extra], non-blocking, nothing deleted (FR-004)
   │                                 │
   │                                 │ site team deals with learners' records, then by hand
   │                                 ▼
   │                             retired (outside apply)
   │
   └── apply finds one category with the declared name, declared parent and an idnumber that is empty or not ltct:
       ──► adopted: idnumber set, reported [changed] adopted (R6)
       apply finds more than one ──► [fail] ambiguous, blocking; nothing written
```

---

## Organisation cohort and managers cohort (applied state)

The cohort rows apply creates. They share one shape (R1).

### Fields set by apply

| Field | Value | Notes |
|---|---|---|
| `contextid` | system context | A category cohort cannot be enrolled into a shared-category course (R1). |
| `idnumber` | as in Identity | Lookup key. |
| `name` | as derived | Updated by apply when the declaration changes. |
| `visible` | `0` | Offered only to someone with `moodle/cohort:view` at system context, so only the site team can enrol it (R1, FR-007). |
| `component` | not set by apply | `tool_dynamic_cohorts` sets it on cohorts it manages, which also blocks hand edits of their membership (R4). Drift does not report it as `changed`. Managers cohorts keep it empty, because they are filled by hand. |
| `description` | not managed | |

Written with `cohort_add_cohort()` and `cohort_update_cohort()` only (Principle XI).

### Validation rules

- A cohort is looked up by `idnumber` in any context, so one at the wrong context is found rather than duplicated. That is a blocking `[fail]`, because moving it would change what it can be enrolled into. Otherwise drift compares `name` and `visible`; a difference is `changed`, and apply corrects it.
- Drift and apply report a cohort by `idnumber` and name only. They never list its members, and never report a member count (plan, constitution III).

---

## Cohort rule

The `tool_dynamic_cohorts` rule that keeps one cohort's membership equal to one profile condition (FR-010, R4).

### Fields (rendered, not hand-declared)

| Field | Type | Notes |
|---|---|---|
| `cohort` | cohort `idnumber` | The rule's target. One rule per organisation cohort; none for managers cohorts. |
| `name` | text | `ltct: <cohort idnumber>`, so a rule we own is recognisable in the plugin's UI and in drift. |
| `condition` | `user_custom_profile` | Custom field `ltct_org`. |
| `field` | `ltct_org` | |
| `operator` | equals | |
| `value` | organisation key | |
| `enabled` | true | |

The exact condition config keys are recorded in [contracts/declaration.md](contracts/declaration.md) once R4's verification reads them from the plugin. Rules are written through the plugin's `rule` and `condition` persistent classes and its `rule_manager`, never by a table write.

### Validation rules

- Rendered only after its cohort and, for an organisation, after the `ltct_org` field exist in the same run.
- Drift compares the condition and `enabled`. A rule on an `ltct:` cohort with any other condition is `changed`; a rule named `ltct:` whose cohort is no longer declared is `extra`.
- If R4's verification fails and the fallback `local_profilecohort` is used, this entity keeps the same fields; only how apply writes it changes (R4 fallback).

### State transitions (one learner)

```text
no ltct_org value ──(site team sets A)──► rule matches A
                                           │ scheduled task or user_updated event
                                           ▼
                                      member of ltct:org:A ──► cohort sync: enrolled as student, in group A
                                           │
                     (site team changes value to B)
                                           ▼
                     removed from ltct:org:A ──► A's enrolments suspended and their roles removed; history kept (R7, US3-2)
                                                 still a member of group A, which core does not change;
                                                 A's manager is refused their profile by the profile hook (R9)
                     added to ltct:org:B     ──► enrolled in B's synchronised courses, in group B
```

---

## Profile field file

`moodle/site/profile-fields.yaml`. The profile field category and the fields in it (FR-008).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `rows` | list of integers | Yes | `[8, 18]`: the fields feed the cohorts (#8) and the profiles (#18). |
| `purpose` | text | Yes | |
| `category` | profile field category | Yes | |
| `fields` | list of profile field declarations | Yes | |

---

## Profile field category

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | text | Yes | `About your work`. Identity, because `user_info_category` has no idnumber. Created with `profile_save_category()`. |

A rename of the category is a new name with no way to find the old one, so `validate` cannot make it safe. A rename is done by hand once and the declaration updated in the same change; the README says so.

---

## Profile field declaration

One attribute every learner can carry across courses and years (FR-008, US4, R5). The repo declares the field and its allowed values; each learner's value lives only in Moodle (spec edge case, constitution III).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `shortname` | `ltct_[a-z0-9_]+` | Yes | Identity. Core requires `[a-zA-Z0-9_]+` (`define_validate_common()`). |
| `name` | text | Yes | Label shown on the profile. |
| `datatype` | `menu` / `checkbox` | Yes | Core has no multi-select, so expertise is one checkbox per area (R5). |
| `visible` | `all` / `teachers` / `private` / `none` | Yes | Maps to `PROFILE_VISIBLE_ALL`, `PROFILE_VISIBLE_TEACHERS`, `PROFILE_VISIBLE_PRIVATE`, `PROFILE_VISIBLE_NONE`. |
| `locked` | `1` / `0` | Yes | A locked field is editable only with `moodle/user:update` at system context, which is the site team (FR-009). |
| `required` | `0` | No, default `0` | All three kinds are optional, so a learner controls what is public by leaving them empty (R5). |
| `options` | list of text | `menu` only, unless `options_from` | The stored values. |
| `options_from` | `organisations` | `menu` only | Options are the organisation keys, in declaration order. Only `ltct_org` uses it. |
| `area` | competency category name | `ltct_exp_*` only | The framework category this checkbox stands for. |
| `why` | text | Yes | Which row or acceptance scenario needs it. |

### The declared fields

| Shortname | Type | Visible | Locked | Values |
|---|---|---|---|---|
| `ltct_org` | menu | `all` | yes | `options_from: organisations` |
| `ltct_role` | menu | `teachers` | no | a short declared `options` list, extended by reviewed change |
| `ltct_exp_<area>` | checkbox, one per area | `all` | no | `area`: one of the categories in `competencies.yaml` |

Country is core's `country` field, not a declaration here (R5).

### Validation rules

- `shortname` values are unique and start with `ltct_`, so drift can tell our fields from anyone else's.
- `ltct_org` exists, is a `menu` with `options_from: organisations`, is `locked: 1`, and has no `options` of its own. A learner and an organisation manager can never change it (FR-009, US3-3).
- `options` and `options_from` are mutually exclusive; a `menu` has exactly one of them, and its options are unique and non-empty.
- Every `area` is a top-level category name of `competencies.yaml`, copied verbatim including `&` and capitalisation, and every category except `Meta` has exactly one checkbox. `Meta` holds only `Uncategorized`, so it gets none. The shortname (e.g. `ltct_exp_core_technical`) is declared, not derived, and never changes once applied, so a renamed framework category cannot move learners' values onto another field.
- No field declares a default value, since a default would write the same value onto every learner.
- No field collects a sensitive category of data (spec assumptions). Adding a field is a reviewed change that names its row.

### Live states

| Live state | Reported as | Apply does |
|---|---|---|
| Absent | `missing` | creates it with `profile_save_field()` |
| Present, definition matches | `[ok]` | nothing |
| `name`, `visible`, `locked`, `required` or a generated option differs | `changed` | updates the definition; learner values untouched |
| Live menu holds an option no longer declared or generated | `extra` (non-blocking) | keeps the option: learners may hold that value, and removing it would invalidate their profile (FR-004) |
| Present with a different `datatype` | `[fail]`, blocking | nothing; changing type would discard every value |
| An `ltct_` field not declared | `extra` (non-blocking) | nothing |

---

## Role declaration `orgmanager` (reshapes spec 001's role list)

One role for every partner (FR-005, SC-005). Follow-only: a manager sees their own organisation's people inside the courses their organisation is enrolled in, and does nothing else (FR-006, FR-007, R2).

### Fields (as spec 001's role declaration)

| Field | Value |
|---|---|
| `shortname` | `orgmanager` |
| `name` | `Organisation manager` |
| `archetype` | `""`: every capability is managed, so a hand grant shows as drift |
| `contextlevels` | `[course]` |
| `capabilities` | `moodle/course:viewparticipants`, `moodle/user:viewdetails`, `moodle/site:viewuseridentity`, each `allow`. No `moodle/user:viewalldetails`, so managers do not see `teachers`-visibility fields (R5). |
| `why` | rows #8 and #15; FR-005 to FR-007 |

### Relationships

- **Managers cohort → role**: a person gets `orgmanager` only through a cohort-sync instance that enrols organisation A's managers cohort into a course with role `orgmanager` and group A. There is no system, category or per-person assignment (R2, FR-012).
- **Role → groups**: the role holds no `moodle/site:accessallgroups`. Combined with the separate-groups default, a manager sees only their own group (R3).
- **Role → other roles**: no capability is `prohibit`, so a manager who is also a mentor (spec 003) or a learner keeps that role's permissions. The profile hook still limits whose profiles they can open, whatever role they open them through (spec edge case, R9).
- Spec 004 adds grade and report capabilities to this role; it does not add a second manager role.

### Validation rules

- `validate` rejects `orgmanager` if it grants `moodle/site:accessallgroups`, any `moodle/user:*` editing or creating capability (`moodle/user:create` among them, FR-013), any `moodle/cohort:*` capability, any `enrol/*` capability, or any role-assignment capability, and if any of its values is `prohibit`. The list of what it may grant is reviewed in `roles.yaml`; the deny list guards against the dangerous additions.
- `contextlevels` is exactly `[course]`.
- No other role in `roles.yaml` is per-organisation: a role `shortname` containing an organisation key is an error (SC-005).

---

## Plugin declarations (reshape `site.yaml`)

| Component | Fields | Why |
|---|---|---|
| `tool_dynamic_cohorts` | `version`, `source: {url, sha256}` | Fills cohorts from profile fields (R4). The pin is the release verified on the 5.2.3+ instance. If R4 fails, this entry is replaced by `local_profilecohort` with the same fields. |
| `enrol_cohort` | `enabled: 1` | Cohort sync puts organisations and their managers into courses (R2). |

Spec 001's plugin rules apply unchanged: a missing, wrong-release or pending-upgrade plugin blocks apply before any write.

---

## Groups settings file

`moodle/site/settings/groups.yaml`, a spec 001 settings file with `rows: [8, 15]`.

| Setting | Value | Why |
|---|---|---|
| `moodlecourse/groupmode` | `1` (separate groups) | New courses separate organisations by group (R3). |
| `moodlecourse/groupmodeforce` | `0` | Forums can still run across organisations (spec 005). |
| `enrol_cohort/unenrolaction` | `3` (`ENROL_EXT_REMOVED_SUSPENDNOROLES`) | Someone leaving a cohort keeps their grades and completion (US3-2) and loses the cohort's role, so a removed manager loses `orgmanager` (R7). |
| `forceloginforprofiles` | `1` | The profile hook runs only while this is on (R9). |
| `hiddenuserfields` | empty | Country, and every other core field, stays visible to everyone (FR-008, R5). |

### Course (reshaped by the publisher)

`publish_moodle.py` sends `groupmode: 1` on update as well as on create, so a course published before this spec gets separate groups on its next publish (R3). No other course field changes.

---

## Profile access decision (R9)

Not declared and not stored: `local_ltuse_control_view_profile()` decides it each time core asks whether a viewer may open a profile.

| Input | Read with | Notes |
|---|---|---|
| viewer is the viewed user | ids | Never prevented. |
| viewer's managed organisation keys | one read of `{cohort}` joined to `{cohort_members}` at system context, `idnumber` like `ltct:org:%:managers` (R9) | Not `cohort_get_user_cohorts()`, which skips hidden cohorts. Cached for the request. Empty means the hook does nothing. |
| viewed user's `ltct_org` | `profile_user_record($user->id)` | Empty counts as no organisation. |
| viewed user is staff | `has_coursecontact_role($user->id)`, or `moodle/user:viewalldetails` at system context | Exempts a course teacher or the site team whose `ltct_org` is empty. |
| viewer has `moodle/user:viewalldetails` in the viewed user's context | `has_capability()` on `$usercontext ?? context_user::instance($user->id)` | The site team, and a spec 003 mentor. Never prevented. Core passes a null context from several callers. |

Outcome: `core_user::VIEWPROFILE_PREVENT` when the viewer manages at least one organisation, and the viewed user's `ltct_org` is a key they do not manage, or is empty and the viewed user is not staff, and the viewer has no `moodle/user:viewalldetails` there. Otherwise `core_user::VIEWPROFILE_DO_NOT_PREVENT`. Never `VIEWPROFILE_FORCE_ALLOW`, so the hook only ever takes access away. The decision is a pure function of the five inputs in `classes/profile_access.php`: `decide(bool $isself, array $managedkeys, string $viewedorg, bool $viewedisstaff, bool $viewerhasviewalldetails): int`. `lib.php` gathers them, cheapest first. It runs only while `forceloginforprofiles` is `1`, which `settings/groups.yaml` declares.

## Moodle-only entities (never declared)

These are learner data. They are created by the site team in Moodle, later by spec 008's tooling, and never appear in the repo, a declaration, a payload or a run report (R8, constitution III).

| Entity | Created by | Relationships | Rule |
|---|---|---|---|
| Profile value | site team (account creation, CSV `profile_field_ltct_org`); learner for the unlocked fields | one per user per field | `ltct_org` holds an organisation key. Values persist across courses and after a course ends (US4-1). |
| Organisation cohort membership | `tool_dynamic_cohorts` | user → `ltct:org:<key>` | Never edited by hand: the cohort's `component` blocks it. |
| Managers cohort membership | site team, by hand | user → `ltct:org:<key>:managers` | The only way to make someone a manager (FR-012). A person in two managers cohorts manages both. |
| Course group | site team | course → one group per enrolled organisation, named for it | Holds both of that organisation's cohort-sync enrolments. |
| Cohort-sync instance | site team | course + cohort + role + group | Two per organisation per course: `ltct:org:<key>` as `student`, `ltct:org:<key>:managers` as `orgmanager`, both into the organisation's group. On leaving the cohort: suspended, roles removed (R7). |
| Account | site team only | | Managers never create accounts (FR-013). |

### Organisation manager lifecycle

```text
person ──(site team adds to ltct:org:A:managers)──► in managers cohort
                                                      │ cohort sync, every course with A's managers instance
                                                      ▼
                                                orgmanager in that course, in group A
                                                      │ sees A's group only (no accessallgroups)
                                                      │
                     (site team removes from cohort) ─┘
                                                      ▼
                                       enrolment suspended and orgmanager removed (unenrolaction 3, R7);
                                       no longer in a managers cohort, so the profile hook no longer applies to them;
                                       a person who is also one of the organisation's learners keeps a learner's view
```

---

## Item result (reshapes spec 001)

New subjects, in apply order after settings:

| Order | Subject form | Example |
|---|---|---|
| 1 | `category:<idnumber>` | `category:ltct:org:independent` |
| 2 | `cohort:<idnumber>` | `cohort:ltct:org:independent:managers` |
| 3 | `profilecategory:<name>` | `profilecategory:About your work` |
| 4 | `profilefield:<shortname>` | `profilefield:ltct_org` |
| 5 | `cohortrule:<cohort idnumber>` | `cohortrule:ltct:org:independent` |

This is the order in [contracts/declaration.md](contracts/declaration.md). A rule comes last because it needs its cohort and its field.

New `kind` values: `adopted` (status `changed`, a category given its idnumber, R6); `ambiguous` (status `fail`, blocking, more than one adoptable category); `wrong-context` (status `fail`, blocking, a cohort outside system context); `wrong-datatype` (status `fail`, blocking, a profile field of another type). Drift reports an adoptable category as `missing` with the message "adoptable". `extra` now also covers undeclared `ltct:` categories, cohorts and rules, undeclared `ltct_` fields, and undeclared menu options.

### Validation rules

- No item names a cohort member, a manager, a profile value or a member count.
- A blocking problem in any new item type (`ambiguous`, a cohort at the wrong context, a field with the wrong datatype) stops apply before any write, as spec 001's preflight does.

## Rendered payload (reshapes spec 001)

Four arrays are added, all already expanded from `organisations.yaml` and `profile-fields.yaml`:

| Field | Contents |
|---|---|
| `categories` | `{idnumber, name, parent_idnumber}` for shared and organisation categories, parents first. |
| `profile_fields` | `{category, shortname, name, datatype, visible, locked, required, options}` with `options_from` already resolved to the organisation keys. |
| `cohorts` | `{idnumber, name, visible: 0}` for organisation and managers cohorts. |
| `cohort_rules` | `{cohort_idnumber, name, condition, field, value}`. |

None of them carries a user id, a membership or a profile value.
