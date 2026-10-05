# Data Model: Partner organisations, cohorts and profiles

> **Amended 2026-10-03 in the spec** (Areas and Area Language Technology Coordinators, [Clarifications 2026-10-03](spec.md)). This file was not redone for it; ~~that happens in the plan step, before any build~~ *(2026-10-05: what it describes is built and deployed through the 2026-10-02 to 2026-10-05 amendments; FR-014 to FR-019 and SC-006 stay deferred to a later plan step, Doug, 2026-10-05)*. Where this file disagrees with the 2026-10-03 Clarifications, the spec wins.

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Research**: [research.md](research.md)

> **Amended 2026-10-02: open courses** (spec Clarifications 2026-10-02; research R2, R3, R8–R14; plan, "Amendment 2026-10-02"). Organisations are no longer separated inside a course. Text describing current behaviour is rewritten in place and marked *(amended 2026-10-02)*; sections added by the amendment are marked *(2026-10-02)*.

This feature extends spec 001's declaration ([data model](../001-site-config-as-code/data-model.md)) with three new files and one new settings file, and the applier with four new item types plus two course checks. *(Amended 2026-10-02.)* It adds one database table of its own, `local_ltuse_org_contact`, which records the message contacts `local_ltuse` makes between managers and their people (R12). Its entities live in two places:

- **Declared** in `moodle/site/` in the public repo: organisations, shared categories, the `ltct:mentors` cohort, organisation-only courses, profile fields, the `orgmanager` role, the group and cohort-sync defaults. These hold names and definitions only.
- **Moodle only**: who belongs to which cohort, who manages which organisation, each learner's profile values, each course's cohort-sync instances, and everything a manager does for their people: individual enrolments, suspensions, mentor assignments, and the contacts made for them. These are learner data. The repo never declares, reads back or reports them (constitution III, FR-012). *(Amended 2026-10-02: courses hold no organisation groups.)*

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
| Mentors cohort *(2026-10-02)* | `organisations.yaml` → `mentors` | Yes (definition only) | New |
| Organisation-only courses file *(2026-10-02)* | `moodle/site/org-courses.yaml` | Yes | New; replaces `course-discussions.yaml` |
| Organisation-only course *(2026-10-02)* | `org-courses.yaml` → `org_only` | Yes | New |
| Course group mode *(2026-10-02)* | each `ltct:` course, checked by drift | Derived | New check |
| Course placement *(2026-10-02)* | publish payload `placement`; `local_ltuse_place_course` | Derived | New; replaces `discussion.shared` |
| Item result | run report line | Evidence only | Reshaped (new subjects and kinds) |
| Rendered payload | stdin of `site_config.php` | No | Reshaped (five new arrays) |
| Profile access decision | computed per request | No | Reshaped 2026-10-02 (new inputs, one allow) |
| Organisation access decision *(2026-10-02)* | computed per request | No | New |
| Cohort membership | Moodle | No | Moodle only |
| Organisation manager assignment | Moodle (managers cohort membership) | No | Moodle only |
| Profile value | Moodle | No | Moodle only |
| Cohort-sync instance | Moodle | No | Moodle only |
| Organisation-enrolment instance *(2026-10-02)* | Moodle, one `enrol_self` instance per course, created on first use | No | Moodle only |
| Manager action (enrolment, suspension, reset, mentor) *(2026-10-02)* | Moodle | No | Moodle only |
| Organisation contact record *(2026-10-02)* | Moodle, `local_ltuse_org_contact` | No | New plugin table |

*(Amended 2026-10-02.)* Courses hold no organisation groups, so "course group" is no longer an entity of this spec; a group a course uses for its own teaching is the course's (FR-011).

The spec's key entities map onto these: **Partner organisation** is the organisation entry plus its derived category and cohorts; **Organisation cohort** is the derived cohort with its rule; **Organisation manager** is a managers-cohort membership, which the organisation access decision reads on every request, plus, in organisation-only courses only, the `orgmanager` role it brings through cohort sync *(amended 2026-10-02)*; **Profile field** is a profile field declaration.

### Identity

Every item is found in Moodle by a stable key, never by display name (spec edge case "name changes", R6).

| Item | Moodle table, column | Value |
|---|---|---|
| Shared category | `course_categories.idnumber` | `ltct:<key>`, e.g. `ltct:published`, `ltct:pilots`, `ltct:organisations` |
| Organisation category | `course_categories.idnumber` | `ltct:org:<orgkey>` |
| Organisation cohort | `cohort.idnumber` | `ltct:org:<orgkey>` |
| Managers cohort | `cohort.idnumber` | `ltct:org:<orgkey>:managers` |
| Mentors cohort *(2026-10-02)* | `cohort.idnumber` | `ltct:mentors` |
| Course (organisation-only or shared) | `course.idnumber` | `ltct:<slug>`, set by the publisher, as before |
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
| `mentors` *(2026-10-02)* | mentors cohort | Yes | The one site-wide cohort a manager's mentor picker draws from (R10). See Mentors cohort. |

### Validation rules (`site_config.py validate`)

- The file parses as YAML and matches its schema; an unknown key is an error.
- Category keys are unique, and organisation keys are unique.
- No field anywhere in the file names a person, an email address or a user id. The schema has no field that could hold one, and an unknown key is an error (constitution III).

---

## Mentors cohort *(2026-10-02)*

The hidden system cohort `ltct:mentors`, from which a manager picks a learner's mentor on `mentors.php` (research R10; spec 003 R7 Phase B). It is also the set the organisation access decision treats as "not a learner": a manager cannot manage a member of it.

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | text | Yes | Display name of the cohort. |
| `why` | text | Yes | Which row or spec needs it. |

The `idnumber` is fixed, `ltct:mentors`, and is not declared. The cohort is rendered into the payload's `cohorts` array like an organisation cohort: system context, `visible = 0`, no rule. The site team fills it in Moodle, since 2026-10-05 with `ltct_admin.py managers` (spec 008); mentors may come from any organisation, so it belongs to none. Its membership is learner data and never appears in the repo.

### Validation rules

- Exactly one `mentors` entry, with no other keys.
- At the server it shares the organisation cohorts' rules: found by `idnumber`, a wrong context is a blocking `[fail] wrong-context`, a `name` or `visible` difference is `changed`, and no line lists its members or counts them.

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
| Organisation category | `ltct:org:<key>` | `<name>` | shared category `organisations` | Holds that organisation's organisation-only courses only, each declared in `org-courses.yaml` and placed there by the publisher (FR-003, R11). *(Amended 2026-10-02.)* |
| Organisation cohort | `ltct:org:<key>` | `<name>` | system context, `visible = 0` | Filled by its cohort rule (FR-010). |
| Managers cohort | `ltct:org:<key>:managers` | `<name> managers` | system context, `visible = 0` | No rule. Filled by the site team in Moodle (FR-012), since 2026-10-05 with `ltct_admin.py managers` (spec 008). |
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

The cohort rows apply creates. They share one shape (R1), and so does the `ltct:mentors` cohort *(2026-10-02)*.

### Fields set by apply

| Field | Value | Notes |
|---|---|---|
| `contextid` | system context | A category cohort cannot be enrolled into a shared-category course (R1). |
| `idnumber` | as in Identity | Lookup key. |
| `name` | as derived | Updated by apply when the declaration changes. |
| `visible` | `0` | Offered only to someone with `moodle/cohort:view` at system context, so only the site team can enrol it (R1, FR-007). A manager's "my organisation" page reads the cohort server-side and never offers it in core's form, so this stays `0` (R1, 2026-10-02). |
| `component` | not set by apply | `tool_dynamic_cohorts` sets it on cohorts it manages, which also blocks hand edits of their membership (R4). Drift does not report it as `changed`. Managers cohorts keep it empty, because they are filled by hand *(2026-10-05: since spec 008, PR #93, by the site team with `ltct_admin.py managers FILE`, or by hand in Moodle)*. |
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
                                      member of ltct:org:A ──► cohort sync: enrolled as student, no group
                                           │                    message contact of each A manager (R12)
                     (site team changes value to B)
                                           ▼
                     removed from ltct:org:A ──► A's cohort-sync enrolments suspended and their roles removed; history kept (R7, US3-2)
                                                 organisation-enrolment enrolments in A's ltct:org:* courses suspended (R10, R12)
                                                 A's managers lose them at once: page, profile and every action (R9, R10)
                                                 contacts with A's managers removed, unless another relationship keeps them (R12)
                     added to ltct:org:B     ──► enrolled in B's synchronised courses, no group;
                                                 B's managers reach and manage them; contacts with B's managers added
```

*(Amended 2026-10-02.)* There are no organisation groups, so nothing stale is left for the site team to clean up (R7). An enrolment a manager made individually goes through the course's organisation-enrolment instance, not cohort sync (R10). In a shared course the move leaves it in place: courses are open. In a course in A's `ltct:org:*` category it is suspended, as cohort sync would do, by the R12 observer on `cohort_member_removed` and by its reconcile task, so an organisation-only course stays its organisation's (SC-002). The 2026-10-01 diagram, in which the learner stayed in group A and A's manager still saw them on the participants list, is superseded.

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

One role for every partner (FR-005, SC-005). Follow-only, and used only in organisation-only courses: there a manager sees the course's participants and reports, who are all their own organisation's people, and does nothing else through the role (FR-006, FR-007, R2). *(Amended 2026-10-02.)* In shared courses the managers cohort is not enrolled, so the role is never held there: with no groups, its capabilities would reach every organisation's participants and completion (R2). It keeps `moodle/site:viewuseridentity`, so managers see email; Managers see the email of every person in their organisation, protected people included; a mentor sees their mentee's; classmates never see a protected person's (Doug, 2026-10-02; spec 016 implements the protected case). Everything else a manager does for their people goes through the organisation access decision and `local_ltuse`'s own pages, never through a capability on this role (R10).

### Fields (as spec 001's role declaration)

| Field | Value |
|---|---|
| `shortname` | `orgmanager` |
| `name` | `Organisation manager` |
| `archetype` | `""`: every capability is managed, so a hand grant shows as drift |
| `contextlevels` | `[course]` |
| `capabilities` | `moodle/course:viewparticipants`, `moodle/user:viewdetails`, `moodle/site:viewuseridentity`, each `allow` (plus spec 004's report capabilities). No `moodle/user:viewalldetails`, so managers do not see `teachers`-visibility fields (R5). *(Amended 2026-10-02.)* Managers see the email of every person in their organisation, protected people included; a mentor sees their mentee's; classmates never see a protected person's (Doug, 2026-10-02; spec 016 implements the protected case). |
| `why` | rows #8 and #15; FR-005 to FR-007 |

### Relationships

- **Managers cohort → role**: a person gets `orgmanager` only through a cohort-sync instance that enrols organisation A's managers cohort into one of A's organisation-only courses with role `orgmanager` and no group. There is no system, category or per-person assignment, and no such instance in a shared course (R2, R8, FR-012). *(Amended 2026-10-02.)* The inspector enforces the last part: a cohort-sync instance for any `ltct:org:%:managers` cohort in an `ltct:` course outside the `ltct:org:*` categories is a blocking `[fail]`, reported as a count only, never with names. An enrolment instance is course configuration, not learner data, so `site_config` may read it (R2).
- **Role → groups**: the role holds no `moodle/site:accessallgroups`. *(Amended 2026-10-02.)* Courses have no groups, so the role's reach is the whole course; it is scoped only because every learner in an organisation-only course belongs to that organisation (R2, R3). The 2026-10-01 relationship, separate groups confining a manager to their own group, is superseded.
- **Managers cohort → management** *(2026-10-02)*: seeing and managing a manager's people outside course enrolment is not a role. It is the organisation access decision, read from managers-cohort membership on every request, so leaving the cohort ends it at once (R10).
- **Role → other roles**: no capability is `prohibit`, so a manager who is also a mentor (spec 003) or a learner keeps that role's permissions. The profile hook still limits whose profiles they can open, whatever role they open them through (spec edge case, R9).
- Spec 004 adds grade and report capabilities to this role; it does not add a second manager role.

### Validation rules

- `validate` rejects `orgmanager` if it grants `moodle/site:accessallgroups`, any `moodle/user:*` editing or creating capability (`moodle/user:create` among them, FR-013), any `moodle/cohort:*` capability, any `enrol/*` capability, or any role-assignment capability, and if any of its values is `prohibit`. The list of what it may grant is reviewed in `roles.yaml`; the deny list guards against the dangerous additions. *(2026-10-02: unchanged. Managers now enrol, suspend and assign mentors, but through `local_ltuse`'s pages, so the deny list still holds; R2, R10.)*
- `contextlevels` is exactly `[course]`.
- No other role in `roles.yaml` is per-organisation: a role `shortname` containing an organisation key is an error (SC-005).

---

## Plugin declarations (reshape `site.yaml`)

| Component | Fields | Why |
|---|---|---|
| `tool_dynamic_cohorts` | `version`, `source: {url, sha256}` | Fills cohorts from profile fields (R4). The pin is the release verified on the 5.2.3+ instance. If R4 fails, this entry is replaced by `local_profilecohort` with the same fields. |
| `enrol_cohort` | `enabled: 1` | Cohort sync puts organisations into courses, and managers cohorts into organisation-only courses only (R2, R8). *(Amended 2026-10-02.)* |

Spec 001's plugin rules apply unchanged: a missing, wrong-release or pending-upgrade plugin blocks apply before any write.

---

## Groups settings file

`moodle/site/settings/groups.yaml`, a spec 001 settings file with `rows: [8, 15]`.

| Setting | Value | Why |
|---|---|---|
| `moodlecourse/groupmode` | `0` (no groups) | New courses are open across organisations (R3, FR-011). *(Amended 2026-10-02; was `1`, separate groups.)* |
| `moodlecourse/groupmodeforce` | `0` | Not forced, so a course or activity may still opt into groups for its own teaching reasons (FR-011, R3). *(Amended 2026-10-02.)* |
| `enrol_cohort/unenrolaction` | `3` (`ENROL_EXT_REMOVED_SUSPENDNOROLES`) | Someone leaving a cohort keeps their grades and completion (US3-2) and loses the cohort's role, so a removed manager loses `orgmanager` in organisation-only courses (R7). |
| `forceloginforprofiles` | `1` | The profile hook runs only while this is on (R9). |
| `hiddenuserfields` | empty | Country, and every other core field, stays visible to everyone (FR-008, R5). |

### Course (reshaped by the publisher) *(amended 2026-10-02)*

`publish_moodle.py` sends `groupmode: 0` on update as well as on create, so a course published before this change loses separate groups on its next publish (R3). For an organisation-only course it also calls `local_ltuse_place_course` on every publish (see Course placement). No other course field changes. The 2026-10-01 `groupmode: 1` is superseded.

### Course group mode (drift item) *(2026-10-02)*

Not declared per course: every `ltct:` course should have `course.groupmode = 0`. Drift lists each `ltct:` course whose group mode is not `0` as `changed`, keyed `course:<idnumber>:groupmode`, and `apply` sets it to `0` with `update_course()`. It is modelled on spec 003's `course:<idnumber>:showreports` check. It covers hand changes and courses published before the publisher sent `0` (R3). An activity's own group mode is not checked: a course may opt an activity into groups for teaching reasons (FR-011).

The course discussion forum is part of the same change: `ensure_discussion` creates and keeps it at no groups for every course, and the applier re-applies that mode (R14). `course-discussions.yaml`, its loader and the payload's `discussion.shared` are removed, because with no groups there is nothing to share or separate.

---

## Organisation-only courses file *(2026-10-02)*

`moodle/site/org-courses.yaml`. Maintainer-only. The list of courses that are only for one organisation's people (FR-003, R11, spec Clarifications 2026-10-02). It replaces `course-discussions.yaml` and reuses its checks. An absent file or an empty `org_only` list means every course is shared.

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `rows` | list of integers | Yes | `[8, 15]`. Every cited row must exist in `moodle/REQUIREMENTS.md`. |
| `org_only` | list of organisation-only courses | Yes (may be empty) | |

## Organisation-only course *(2026-10-02)*

One course restricted by enrolment to one organisation. Its content stays in the public repo; "only for its own people" means enrolment, not confidentiality (FR-003).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `slug` | course slug | Yes | A course under `modules/`, compared with `course_stage.branch_slug()`, as `course-discussions.yaml` did. |
| `organisation` | organisation key | Yes | A key declared in `organisations.yaml`. |
| `why` | text | Yes | The approval only ("approved by the maintainer, issue #N"), never the organisation's circumstances, which are kept privately (R11). |

### Validation rules (`site_config.load_org_courses()`)

- The file parses and matches its schema; an unknown key is an error. Its rows cite `moodle/REQUIREMENTS.md`.
- Each `slug` resolves to a course; no slug appears twice, so a course has at most one host organisation.
- `organisation` is a declared organisation key.
- `why` is non-empty.
- It is the only reader of the file: `moodle_payload.py` calls it for each course's `placement`, and `validate` for the drift payload, so the publisher and drift cannot disagree.
- The file names a course and its host organisation, both already public. An organisation that asks not to be named publicly appears only under a neutral key from its first commit (016, Doug, 2026-10-05 (scope review); constitution III).

### Derived

| Item | Value |
|---|---|
| Target category | `ltct:org:<organisation>` |
| Enrolment | by the site team: `ltct:org:<organisation>` as Student and `ltct:org:<organisation>:managers` as `orgmanager`, both by cohort sync, no group (R8). Managers may also enrol their own learners individually (R10). Learner data, never declared. |

### Placement drift

| Live state | Reported as | Apply does |
|---|---|---|
| Declared course in its organisation's category | `[ok]` | nothing |
| Declared course in any other category | `changed` (non-blocking) | nothing: a move changes which category roles the course inherits, so only the publisher moves it (R11) |
| Undeclared `ltct:` course inside an `ltct:org:*` category | `extra` (non-blocking) | nothing |
| Declared course not yet published | not reported | nothing |

---

## Course placement *(2026-10-02)*

How a published course reaches its category. It replaces the payload's `discussion.shared`.

### Payload field (`moodle_payload.py` manifest)

| Field | Type | Notes |
|---|---|---|
| `placement.org_only` | boolean | `true` when the course is listed in `org-courses.yaml`. |
| `placement.category_idnumber` | `ltct:org:<key>`, or null | The host organisation's category when `org_only`; null for a shared course, which keeps the publisher's `--category` for now (R11). |

`check_moodle_payload.py` checks its shape: both keys present, `category_idnumber` is `ltct:org:<key>` exactly when `org_only` is true, and null otherwise. It holds no user data.

### Web service `local_ltuse_place_course`

`local_ltuse_place_course(courseidnumber, categoryidnumber)`, called by the publisher on every publish of an organisation-only course, so the placement is re-asserted each time (R11). It requires `local/ltuse:publish`, accepts only a target whose idnumber is `ltct:org:<key>`, `ltct:pilots` or `ltct:published`, resolves it by a read of `course_categories.idnumber` (not indexed in core, so listed in `moodle/local_ltuse/README.md` as a Principle XI exception), and, only when the course is elsewhere, moves it with `move_courses()`, which fires `course_updated`. It returns whether it moved the course. Moving into a hidden category hides the course; organisation categories are visible (R6). Core's route is not used: `core_course_get_categories` by idnumber needs `moodle/category:manage` at system context, and `core_course_update_courses` never checks the target category (R11).

---

## Profile access decision (R9) *(amended 2026-10-02)*

Not declared and not stored: `local_ltuse_control_view_profile()` decides it each time core asks whether a viewer may open a profile.

| Input | Read with | Notes |
|---|---|---|
| viewer is the viewed user | ids | Never prevented. |
| viewer's managed organisation keys | one read of `{cohort}` joined to `{cohort_members}` at system context, `idnumber` like `ltct:org:%:managers` (R9) | Not `cohort_get_user_cohorts()`, which skips hidden cohorts. `cohort.idnumber` is not indexed in core, so the README lists this read as a Principle XI exception. Cached for the request. Empty means the hook does nothing. |
| viewed user's `ltct_org` | `profile_user_record($user->id)` | Empty counts as no organisation. |
| viewer manages the viewed user's organisation *(2026-10-02)* | the organisation access decision's `is_org_member_of_manager(V, P)` (R10) | The viewed user's non-empty `ltct_org` is a key the viewer manages and they are in that `ltct:org:<key>` cohort. No role condition. The same predicate, not a copy. |
| viewed user is staff | `has_coursecontact_role($user->id)`, or `moodle/user:viewalldetails` at system context | Exempts a course teacher or the site team whose `ltct_org` is empty. |
| viewer has `moodle/user:viewalldetails` in the viewed user's context | `has_capability()` on `$usercontext ?? context_user::instance($user->id)` | The site team. Core passes a null context from several callers. |
| viewer is the viewed user's mentor *(2026-10-02)* | spec 003's mentor test (003 R9) | Lets core decide. |
| viewer has a participant path to the viewed user *(2026-10-02)* | `enrol_get_shared_courses()` and `get_user_roles()`, in `lib.php` | A course both are actively enrolled in where the viewer holds any role other than `orgmanager`. Lets core decide, so a manager who is also a learner sees classmates from every organisation. |

Outcome, in order *(amended 2026-10-02)*:

1. Viewing yourself, or managing no organisation: `core_user::VIEWPROFILE_DO_NOT_PREVENT`.
2. `is_org_member_of_manager(V, P)` holds (the viewer manages the organisation the viewed person belongs to, whoever they are): `core_user::VIEWPROFILE_FORCE_ALLOW`. This is the one place the hook grants access, so a manager reaches their own organisation's people while enrolled in nothing. The person's role does not narrow it: a mentor or manager of A is still A's member, and seeing a profile shows only what the profile shows. Core lets any PREVENT win over any FORCE_ALLOW, so it cannot override another plugin's refusal; `user/view.php` with a course still requires access to that course.
3. The viewer has `moodle/user:viewalldetails` in the viewed user's context, is their mentor, or has a participant path to them: `DO_NOT_PREVENT`, so core decides.
4. Otherwise, for a managers-cohort member: `core_user::VIEWPROFILE_PREVENT`, including an empty `ltct_org` for a person who is not staff.

The decision is a pure function in `classes/profile_access.php`, with the inputs in this table's order. `lib.php` gathers them, cheapest first; the participant path is computed only when cases 1 and 2 do not decide. It runs only while `forceloginforprofiles` is `1`, which `settings/groups.yaml` declares. The harness's sweep "never FORCE_ALLOW" becomes "FORCE_ALLOW only in case 2".

*Superseded (2026-10-01)*: five inputs, `decide(bool $isself, array $managedkeys, string $viewedorg, bool $viewedisstaff, bool $viewerhasviewalldetails): int`, PREVENT whenever a manager viewed someone outside their organisations, and never FORCE_ALLOW. Once courses were open, that PREVENT walled a manager-learner off from classmates, and managers, no longer enrolled with their people, could reach none of them (R9, amended).

## Organisation access decision (R10) *(2026-10-02)*

Not declared and not stored: `local_ltuse\organisation\access`, a pure class tested by `tests/org_access_harness.php`, decides on every request what viewer V may do for person P (FR-006a). It holds two predicates:

- **`is_org_member_of_manager(V, P)`**: V is not P; P's `ltct_org` is non-empty and is one of V's managed keys; and P is a member of the `ltct:org:<that key>` cohort, so the field and the cohort agree. It says nothing about P's role. The profile decision's case 2 (FORCE_ALLOW) and the "my organisation" page's list use it, and spec 016's entitlement is asked to call it rather than re-derive it.
- **`may_manage_account(V, P)`**: the first predicate, and P is a **learner** (below). Every management action uses it. Staff, mentors and other managers are listed on the page but are the site team's to manage.

| Input | Read with | Used by | Notes |
|---|---|---|---|
| V is P | ids | both | Never applies to oneself. |
| V's managed organisation keys | `local_ltuse_managed_organisation_keys()`, the one read of `ltct:org:%:managers` membership (R9) | both | Read on every request, so leaving the managers cohort ends everything at once. |
| P's `ltct_org` | `profile_user_record()` | both | Must be non-empty and one of V's keys. |
| P is in `ltct:org:<P's ltct_org>` | the cohort read | both | A learner whose field is set but who has not yet joined the cohort cannot be managed, so the field and the cohort agree. Protection is per person and is set before enrolment by spec 008's intake, and an organisation has no minimum (Doug, 2026-10-05 (scope review); research R10). *(Until 2026-10-05 this read "so spec 016 can settle their protection first", the organisation-minimum rationale the scope review removed.)* |
| P is deleted | the user record | `may_manage_account` | |
| P is a site admin | core's admin list | `may_manage_account` | |
| P is staff | ~~`has_coursecontact_role($P)`, the same staff test as R9~~ *(amended 2026-10-04, `38a2c5b`)* any role but `student` in any course context, one read of `{role_assignments}` in `local_ltuse_organisation_person_facts()` (`lib.php`); `has_coursecontact_role()` sees only `$CFG->coursecontact` | `may_manage_account` | Course teachers, course leaders and course mentors (`teacher`) are the site team's. |
| P holds a role assignment at system or any category context | `get_user_roles()` for the system context and each category from `core_course_category::get_all()`; public APIs only | `may_manage_account` | Staff are the site team's to manage. |
| P is in any managers cohort | the same cohort read | `may_manage_account` | Other managers are the site team's. |
| P is in `ltct:mentors` | the same cohort read | `may_manage_account` | Mentors are the site team's. |

Outcome: `is_org_member_of_manager` holds when V is not P, P's `ltct_org` is one of V's keys and P is in that organisation's cohort. `may_manage_account` holds when, in addition, P is a learner: not deleted, not a site admin, not staff, no system or category role assignment, in no managers cohort, not in `ltct:mentors`. When the predicate an action needs fails, the action is refused.

### Per-action rules

Each action re-checks its predicate on the server, with `require_login()` and a sesskey on every write. None of the core functions it calls checks a capability itself, so for a manager this decision is the only gate. *(2026-10-04, spec 008.)* Spec 008's admin service does not go through it: it calls the unchecked `do_*()` cores (below; `classes/admin/intake_service.php:579`, `classes/admin/suspension_service.php:76-78`) after `require_capability('local/ltuse:administer')` in its external functions.

| Action | Predicate | Extra inputs | Rule |
|---|---|---|---|
| See P and their progress | `is_org_member_of_manager` | P's course completion | Email is shown, protected people's included (Doug, 2026-10-02). |
| Enrol | `may_manage_account` | target course's idnumber and category | The course has an `ltct:` idnumber and sits in `ltct:published` or in `ltct:org:<P's ltct_org>`. Never `ltct:pilots`: pilot learners are the pilot coordinator's (stage 7). Always Student, through the course's organisation-enrolment instance (below), with `enrol_plugin::enrol_user()`. |
| Unenrol | `may_manage_account` | the enrolment's instance | Only from the organisation-enrolment instance, with `enrol_plugin::unenrol_user()`. A cohort-sync enrolment is the site team's; a manual (pilot) enrolment is never touched. |
| Send a reset link | `may_manage_account` | — | `core_login_process_password_reset($user->username, '')`, which applies core's own guards (enabled auth that can reset, `moodle/user:changeownpassword`, confirmed and not suspended, reuse or expiry of a live reset). The link goes only to P's own address; V never sees it. |
| Suspend | `may_manage_account` | P's suspended state | `destroy_user_sessions()`, then `user_update_user()` with a minimal `{id, suspended: 1}` object, never a reloaded full record, so a concurrent spec 016 name change cannot be overwritten. Site-wide; the confirmation says so. |
| Reactivate | `may_manage_account` | P's suspended state | `user_update_user()` with a minimal `{id, suspended: 0}` object. A learner who has moved is the new organisation's. |
| Assign / end a mentor | `may_manage_account` | the candidate's `ltct:mentors` membership | Candidates come only from `ltct:mentors`. |

Every write fires core's own event with V as the actor; there is no event of our own.

### Management actions: two layers *(amended for spec 008, 2026-10-04)*

`local_ltuse\organisation\actions` splits every action in two (spec 008 research R6), because `may_manage_account` is false for a site-team actor in no managers cohort, and false for the people only the site team may act on (staff, mentors, managers, and learners in the `sil` and `sil-partner` holding entries):

| Layer | Methods | Checks | Called by |
|---|---|---|---|
| Unchecked core | `do_suspend(int $userid)`, `do_reactivate(int $userid)`, `do_enrol(int $userid, int $courseid)`, `do_unenrol(int $userid, int $courseid)` | The per-action rules above that do not depend on who manages P: never a site admin, never the acting user, never a deleted account for enrolment; `may_enrol_into()` for enrolment, `may_unenrol_from()` for unenrolment. `do_suspend` calls `destroy_user_sessions()` then `user_update_user((object)['id' => …, 'suspended' => 1], false)`. | Spec 008's `local_ltuse_admin_*` functions, after `require_capability('local/ltuse:administer')` and their own rules: `do_enrol()` from intake (`classes/admin/intake_service.php:579`), `do_suspend()` and `do_reactivate()` from `suspension_service.php:76-78`. `do_unenrol()` is available; no 008 caller yet (only `tests/organisation_test.php`). Never a page. |
| Manager wrapper | `suspend()`, `reactivate()`, `enrol()`, `unenrol()` (and `send_reset()`) | `may_manage_account(V, P)` for the acting user (`require_manageable()`), then the same core write as the matching `do_*()`: the wrappers call the helpers both layers share (`enrol_course()`, `unenrol_instance()`, `do_write_suspended()`; `actions.php` ~:82-84, :130-134), not `do_*()` itself. | `organisation.php` only. |

One suspend path for managers and the site team, so spec 016's PHPUnit case (suspending a protected user leaves names unchanged) is written once, against this class. 002's T071 built the class with manager checks only; spec 008 added the unchecked cores to it when it merged main (PR #93). `do_suspend()` and `do_reactivate()` return whether anything changed, as the wrappers do.

### Organisation-enrolment instance *(2026-10-02)*

The enrolment instance a manager enrols through (R10). One per course, of core's `enrol_self` plugin, which allows several instances per course:

| Property | Value | Why |
|---|---|---|
| name | `Organisation enrolment` | Recognisable on the course's enrolment methods page. |
| `customint6` | `0` | New self-enrolments off, so no learner can use it. |
| enrolment key | random | A second barrier against self-enrolment. |
| welcome message | none | |
| `customchar1` | a fixed marker | How our code finds the instance again. |

It is created on first use with `enrol_get_plugin('self')->add_instance()`, not declared, and is never the manual instance. Spec 004 tells delivery from pilots by enrolment method, so its delivery condition becomes `enrol:plugin` not equal to `manual` (or `cohort` and `self`, if the select condition can hold two values), and `coverage.php` counts this instance as delivery. When a learner moves organisation, an enrolment through this instance in a course in their old organisation's `ltct:org:*` category is suspended by the R12 observer on `cohort_member_removed` and by its reconcile task; one in a shared course stands.

## Moodle-only entities (never declared)

These are learner data. They are created in Moodle by the site team, by managers for their own learners (2026-10-02), or by `local_ltuse` on their behalf, and never appear in the repo, a declaration, a payload or a run report (R8, constitution III). *(2026-10-05: spec 008 merged, PR #93. The site team now makes most of them with `scripts/ltct_admin.py`, whose command is named in each row it covers; learner files stay outside every git tree.)*

| Entity | Created by | Relationships | Rule |
|---|---|---|---|
| Profile value | site team (account creation: `ltct_admin.py intake`, which sets `ltct_org`, or core CSV `profile_field_ltct_org`; a change of organisation: `ltct_admin.py move`); learner for the unlocked fields | one per user per field | `ltct_org` holds an organisation key. Values persist across courses and after a course ends (US4-1). |
| Organisation cohort membership | `tool_dynamic_cohorts` | user → `ltct:org:<key>` | Never edited by hand: the cohort's `component` blocks it. |
| Managers cohort membership | site team, with `ltct_admin.py managers` (or by hand in Moodle) | user → `ltct:org:<key>:managers` | The only way to make someone a manager (FR-012). A person in two managers cohorts manages both. |
| Mentors cohort membership *(2026-10-02)* | site team, with `ltct_admin.py managers` (or by hand in Moodle) | user → `ltct:mentors` | The only source of a manager's mentor candidates (R10). |
| Cohort-sync instance *(amended 2026-10-02)* | site team, with `ltct_admin.py enrol course`, `enrol pathway`, `enrol mirror` and `unenrol` | course + cohort + role, no group | Shared course: one per enrolled organisation, `ltct:org:<key>` as `student`; the managers cohort is **not** enrolled. Organisation-only course: `ltct:org:<key>` as `student` and `ltct:org:<key>:managers` as `orgmanager`. On leaving the cohort: suspended, roles removed (R7). |
| Organisation enrolment *(2026-10-02)* | a manager, for their own learner, or the site team (`ltct_admin.py intake`, through `do_enrol()`) | user → course's organisation-enrolment instance (`enrol_self`), Student | Made and ended by a manager only through `may_manage_account` (R10). On a move of organisation it stands in shared courses and is suspended in the old organisation's `ltct:org:*` courses (R12 observer and reconcile task). |
| Manual enrolment | the site team or the pilot coordinator | user → course's manual instance | Pilots (stage 7). Never made or ended by a manager. |
| Suspension *(2026-10-02)* | a manager, for their own learner, or the site team (`ltct_admin.py suspend` and `reactivate`, through `do_suspend()` / `do_reactivate()`) | `user.suspended` | Site-wide. Sessions are ended first (R10). |
| Mentor assignment *(2026-10-02)* | a manager, for their own learner (`mentors.php`), or the site team (`ltct_admin.py mentors assign` and `mentors end`) | `mentor` role in the learner's user context | Spec 003's role and contact observer (003 R7 Phase B). |
| Organisation contact record *(2026-10-02)* | `local_ltuse` observer and reconcile task | manager ↔ member, plus the core contact | See below. |
| Account | site team only (`ltct_admin.py intake`) | | Managers never create accounts (FR-013). |

*Superseded (2026-10-01)*: a course group per enrolled organisation, named for it and holding both of that organisation's cohort-sync instances, and two instances per organisation in every course. Existing ones on the build host are removed by a one-off CLI that reports counts only (R13): for every `ltct:` course it sets each cohort-sync instance's group to none and deletes the organisation groups; only for courses outside the `ltct:org:*` categories does it also delete the managers-cohort sync instances.

### Organisation contact record (`local_ltuse_org_contact`, new plugin table) *(2026-10-02)*

Records the message contacts `local_ltuse` made between a manager and a person in their organisation, so a leave removes only those (R12, FR-006b). Modelled on spec 003's `local_ltuse_mentor_contact`.

| Column | Type | Note |
|---|---|---|
| `id` | int | |
| `managerid` | int, FK `user.id` | |
| `memberid` | int, FK `user.id` | |
| `contactid` | int | the core contact this plugin made; only that contact is ever removed |
| `timecreated` | int | |

- **Added** on `\core\event\cohort_member_added`: a new member of `ltct:org:<key>` becomes a contact of each member of `ltct:org:<key>:managers`, and a new manager a contact of each member. `\core_message\api::add_contact()` throws on a duplicate, so `is_contact()` is checked first.
- **Removed** on `\core\event\cohort_member_removed`: only the contacts this plugin made, and only when no remaining relationship (another managed organisation, or mentoring) links the pair. A contact the two made themselves is never removed.
- **Repaired** hourly by the scheduled task `reconcile_org_contacts`, both ways, because some membership changes fire no event (R12).
- A learner's block still wins: core checks blocks before contacts.
- Personal data: the privacy provider declares it.

### Organisation manager lifecycle *(amended 2026-10-02)*

```text
person ──(site team adds to ltct:org:A:managers)──► in managers cohort
                                                      │ read on every request (no sync)
                                                      ├──► sees A's members: "my organisation" page, profiles (FORCE_ALLOW) (R9, R10)
                                                      ├──► manages A's learners: enrol, unenrol, reset link, suspend, reactivate, mentors (R10)
                                                      ├──► message contact of each person in A (R12)
                                                      │ cohort sync, only A's organisation-only courses
                                                      ├──► orgmanager there, no group (R2); not enrolled in shared courses
                                                      │
                     (site team removes from cohort) ─┘
                                                      ▼
                                       page, profiles and every action refused at once (R10);
                                       organisation-only enrolments suspended and orgmanager removed (unenrolaction 3, R7);
                                       contacts this plugin made removed, unless another relationship keeps them (R12);
                                       a person who is also a learner keeps a learner's view of their classmates
```

*Superseded (2026-10-01)*: `orgmanager` arrived by cohort sync in every course the organisation was enrolled in, into group A, and saw A's group only. Managers are no longer enrolled in shared courses (spec Clarifications 2026-10-02, B2).

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
| 6 *(2026-10-02)* | `course:<idnumber>:groupmode` | `course:ltct:<slug>:groupmode` |
| 7 *(2026-10-02)* | `course:<idnumber>:placement` | `course:ltct:<slug>:placement` |

This is the order in [contracts/declaration.md](contracts/declaration.md). A rule comes after its cohort and its field. The two course items come last; apply fixes group mode and only reports placement (R3, R11). The `ltct:mentors` cohort is a `cohort:` item like any other.

New `kind` values: `adopted` (status `changed`, a category given its idnumber, R6); `ambiguous` (status `fail`, blocking, more than one adoptable category); `wrong-context` (status `fail`, blocking, a cohort outside system context); `wrong-datatype` (status `fail`, blocking, a profile field of another type). Drift reports an adoptable category as `missing` with the message "adoptable". `extra` now also covers undeclared `ltct:` categories, cohorts and rules, undeclared `ltct_` fields, and undeclared menu options.

*(2026-10-02.)* The inspector also reports, as a blocking `[fail]`, any cohort-sync instance for an `ltct:org:%:managers` cohort in an `ltct:` course outside the `ltct:org:*` categories: a managers cohort synced into a shared course would show `orgmanager` every organisation's participants and completion (R2). The line gives a count only, never a course, cohort or person name.

### Validation rules

- No item names a cohort member, a manager, a profile value or a member count.
- A blocking problem in any new item type (`ambiguous`, a cohort at the wrong context, a field with the wrong datatype) stops apply before any write, as spec 001's preflight does.

## Rendered payload (reshapes spec 001)

Five arrays are added, all already expanded from `organisations.yaml`, `profile-fields.yaml` and `org-courses.yaml`:

| Field | Contents |
|---|---|
| `categories` | `{idnumber, name, parent_idnumber}` for shared and organisation categories, parents first. |
| `profile_fields` | `{category, shortname, name, datatype, visible, locked, required, options}` with `options_from` already resolved to the organisation keys. |
| `cohorts` | `{idnumber, name, visible: 0}` for organisation and managers cohorts, and for `ltct:mentors` *(2026-10-02)*. |
| `cohort_rules` | `{cohort_idnumber, name, condition, field, value}`. |
| `org_courses` *(2026-10-02)* | `{course_idnumber, category_idnumber}`, one per organisation-only course, for placement drift. |

None of them carries a user id, a membership or a profile value. *(2026-10-02.)* The discussion-sharing list from `course-discussions.yaml` is removed (R14).
