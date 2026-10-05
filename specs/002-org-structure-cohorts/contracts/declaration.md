# Contract: Organisations, profile fields and the manager role

> **Amended 2026-10-03 in the spec** (Areas and Area Language Technology Coordinators, [Clarifications 2026-10-03](../spec.md)). This file is not yet redone for it; that happens in the plan step, before any build. Where this file disagrees with the 2026-10-03 Clarifications, the spec wins.

> **Amended 2026-10-02: open courses** (spec Clarifications 2026-10-02; research R2, R3, R8–R14; plan, "Amendment 2026-10-02"). Organisations are no longer separated inside a course. Text describing current behaviour is rewritten in place and marked *(amended 2026-10-02)*; additions are marked *(2026-10-02)*.

This extends [spec 001's declaration contract](../../001-site-config-as-code/contracts/declaration.md) and its [output contract](../../001-site-config-as-code/contracts/output.md). Everything there still holds: the files are read only by `scripts/site_config.py`, the PHP side receives them as JSON on stdin, apply creates only what is absent and never deletes, and nothing names a learner. The CLI is unchanged: `python scripts/site_config.py validate | drift | apply`, with the same `--json`.

## Files

| File | Status | Holds | Rows |
|---|---|---|---|
| `moodle/site/organisations.yaml` | new | The shared categories, the partner organisations and *(2026-10-02)* the `ltct:mentors` cohort. | #8, #15 |
| `moodle/site/profile-fields.yaml` | new | The profile field category and its fields. | #18 |
| `moodle/site/settings/groups.yaml` | new | No groups by default *(amended 2026-10-02)*; cohort sync suspends and removes roles, never unenrols. | #8, #15 |
| `moodle/site/org-courses.yaml` | new *(2026-10-02)* | The organisation-only courses, maintainer-declared (R11). | #8, #15 |
| `moodle/site/course-discussions.yaml` | deleted *(2026-10-02)* | Nothing: with no groups there is nothing to share or separate (R14). | — |
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
mentors:                          # (2026-10-02) the one hidden cohort ltct:mentors (R10)
  name: <string>                  # cohort display name; the idnumber is fixed, ltct:mentors
  why: <string>
```

**The mentors cohort** *(2026-10-02)*. `mentors` renders one more cohort: `idnumber` `ltct:mentors`, system context, `visible = 0`, no rule. The site team fills it by hand; it belongs to no organisation, because mentors may come from any. It is the only source of candidates on a manager's mentor page, and its members are not learners to the organisation access decision, so no manager can manage them (research R10). It is reported, and checked at the server, exactly like an organisation cohort.

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
| Course category | `ltct:org:<key>` | `<name>` | parent `ltct:organisations` | Created with `core_course_category::create()`. Holds only the organisation-only courses `org-courses.yaml` declares for it *(2026-10-02)*. |
| Organisation cohort | `ltct:org:<key>` | `<name>` | system context | `visible = 0`. Filled by a rule (R4). |
| Managers cohort | `ltct:org:<key>:managers` | `<name> managers` | system context | `visible = 0`. No rule: filled by hand in Moodle (FR-012). |
| Cohort rule | on cohort `ltct:org:<key>` | `ltct: ltct:org:<key>` | `tool_dynamic_cohorts` | One condition: profile field `ltct_org` equals `<key>`. |

No country cohorts are declared or created (spec Clarifications 2026-10-01). Country is only Moodle's core profile field.

**Added by spec 004.** Each organisation entry now also produces one custom report and one schedule on it. They are declared once, as the `per: organisation` template `progress` in `moodle/site/reports.yaml`, and `scripts/site_config.py` expands that template for every entry here, so nothing about them differs between organisations either. See [spec 004's declaration contract](../../004-progress-reporting/contracts/declaration.md), "What one `per: organisation` template becomes".

| Item | Identity | Name | Properties |
|---|---|---|---|
| Custom report | `component` `local_ltuse`, `area` `org_<key>_progress`, every `-` in the key as `_` | `<name>: learner progress` | Participants datasource. Scoped by three conditions: `ltct_org` equals `<key>`, role is `student` (stored as the role's id), and the delivery condition on enrolment method. *(Amended 2026-10-02.)* That condition is `enrol:plugin` not equal to `manual` (or `cohort` and `self`, if the select condition can hold two values), so an enrolment a manager makes through the organisation-enrolment instance counts as delivery, and `coverage.php` counts it too (research R10); it was `cohort`. Its one audience is cohort members of `ltct:org:<key>:managers`. |
| Schedule | the report's first `message` schedule | — | Weekly, Excel, viewed as each recipient, not sent when empty (`reportempty` `2`), to the report's audience. Subject `<name>: weekly learner progress`. |

An organisation key must therefore also encode to a valid report `area`: a key containing `--` or ending in `-` fails `validate`. A report is shown as `report org_<key>_progress` in drift and apply output. Its audience is `missing` until the managers cohort exists. That blocks the report, not the run, and the same apply creates the cohort first.

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
- `mentors` is present, with a non-empty `name` and `why` and no other key *(2026-10-02)*.
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

## `org-courses.yaml` *(2026-10-02)*

```yaml
rows: [8, 15]
org_only:                         # may be empty; an absent file means every course is shared
  - slug: <course slug>           # a course under modules/, in course_stage.branch_slug() form
    organisation: <org key>       # a key in organisations.yaml
    why: <string>                 # the approval only ("approved by the maintainer, issue #N"), never the organisation's circumstances
```

Only the maintainer edits this file. A course is never made organisation-only in its frontmatter, and never only by hand in Moodle: drift reports a course in the wrong category (FR-003, research R11). Organisation-only means restricted enrolment, not hidden content: the course's content stays in the public repo and its name on a public category page.

### Validation rules (`validate`, `site_config.load_org_courses()`)

`load_org_courses()` replaces `load_discussions()` and is the only reader of the file. `moodle_payload.py` calls it for each course's `placement`, and `validate` for the drift payload, so the publisher and drift cannot disagree.

- The file parses as YAML; `rows` and `org_only` are its only keys, and an unknown key at either level is an error.
- `rows` is a list of row numbers that exist in `moodle/REQUIREMENTS.md`.
- Each entry has exactly `slug`, `organisation` and `why`.
- `slug` names a course under `modules/`; a slug that only matches after `branch_slug()` is reported with its canonical form, as `course-discussions.yaml` did.
- No `slug` appears twice.
- `organisation` is a key declared in `organisations.yaml`.
- `why` is non-empty. It records only the approval, never the organisation's circumstances; the reason itself is kept privately, and `moodle/site/README.md` says so (research R11).
- The file names a course and its host organisation, which are already public. An organisation that asks not to be named publicly appears only under a neutral key from its first commit (016, Doug, 2026-10-05 (scope review)).

### What one entry becomes

| Item | Where | Notes |
|---|---|---|
| Payload `placement` | the course's publish manifest | `{org_only: true, category_idnumber: "ltct:org:<organisation>"}`. Every other course gets `{org_only: false, category_idnumber: null}`. Replaces `discussion.shared`. |
| Placement call | `local_ltuse_place_course` | Made by the publisher on every publish of the course (see Publisher). |
| Drift item | `course:<idnumber>:placement` | Reported, never fixed by apply (see Output additions). |

Enrolment is the site team's (research R8): the organisation's cohort as Student and its managers cohort as `orgmanager`, both by cohort sync, no group. Managers may also enrol their own learners individually, through the course's organisation-enrolment instance (R10). Neither is declared.

## `roles.yaml` addition

```yaml
  - shortname: orgmanager
    name: Organisation manager
    description: <string>
    archetype: ""                 # every capability managed; a hand grant shows as drift
    contextlevels: [course]       # assigned only through cohort sync, in an organisation-only course (R2, amended 2026-10-02)
    capabilities:
      moodle/course:viewparticipants: allow
      moodle/user:viewdetails: allow
      moodle/site:viewuseridentity: allow
    why: <string>
```

- There is exactly one organisation manager role, used for every partner (FR-005, SC-005).
- *(Amended 2026-10-02.)* It is held only in organisation-only courses, where the site team enrols the organisation's managers cohort as `orgmanager` by cohort sync, with no group. Every learner there belongs to that organisation, so the role's course-wide reach is already scoped. The managers cohort is never enrolled in a shared course: with no groups, the role's capabilities would show every organisation's participants and completion (research R2). The inspector fails, blocking, on any such instance (see Output additions).
- It holds no `moodle/site:accessallgroups`, no `moodle/user:viewalldetails`, no `moodle/user:create`, no `moodle/user:update`, and no enrolment, cohort or role-assignment capability (FR-007, FR-013). `validate` fails if any of these appears on `orgmanager`. Without `moodle/user:viewalldetails`, managers do not see `teachers`-visibility fields (R5). *(2026-10-02: unchanged. Managers' enrolment, suspension, reset and mentor actions go through `local_ltuse`'s own pages, never a capability on this role; R10.)*
- Nothing is `prohibit`, so a person who also holds spec 003's mentor role keeps its permissions.
- **Added by spec 004.** `orgmanager` also holds `report/progress:view: allow` and `report/completion:view: allow`, so a manager can open a course's activity completion and course completion reports. The deny check above is unchanged and still applies. See [spec 004's declaration contract](../../004-progress-reporting/contracts/declaration.md), "`roles.yaml` changes". *(Amended 2026-10-02.)* Courses have no groups, so these reports show the whole course; that is why the role is held only in organisation-only courses. The 2026-10-01 text, "a manager sees only their own group's learners there", is superseded.

## `settings/groups.yaml`

| `name` | `value` | Meaning |
|---|---|---|
| `moodlecourse/groupmode` | `0` | New courses default to no groups, open across organisations (R3, FR-011). *(Amended 2026-10-02; was `1`.)* |
| `moodlecourse/groupmodeforce` | `0` | Not forced, so a course or activity may still opt into groups for its own teaching reasons (FR-011). *(Amended 2026-10-02.)* |
| `forceloginforprofiles` | `1` | The profile hook runs only while this is on (R9). |
| `hiddenuserfields` | empty | Country stays visible to everyone (FR-008, R5). |
| `enrol_cohort/unenrolaction` | `3` | `ENROL_EXT_REMOVED_SUSPENDNOROLES`: someone who leaves a cohort is suspended and loses the cohort's roles, so history is kept (US3-2) and a removed manager loses `orgmanager` in organisation-only courses (R7). |

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
    why: "#8 #15 cohort sync enrols an organisation into a course, and its managers into its organisation-only courses."
```

*(Amended 2026-10-02: no group, and managers only in organisation-only courses; R2, R8.)*

## Profile hook (`local_ltuse`)

`moodle/local_ltuse/lib.php` defines `local_ltuse_control_view_profile($user, $course, $usercontext): int`, the callback core's `user_process_profile_callbacks()` finds through `get_plugins_with_function('control_view_profile')`. *(Amended 2026-10-02.)* It returns `core_user::VIEWPROFILE_FORCE_ALLOW`, `core_user::VIEWPROFILE_PREVENT` or `core_user::VIEWPROFILE_DO_NOT_PREVENT`, decided as in the data model's "Profile access decision" (R9, amended). `FORCE_ALLOW` is returned in one case only: `access::is_org_member_of_manager(V, P)` holds, that is, the viewer manages the viewed person's non-empty `ltct_org` and the viewed person is in that organisation's cohort, whatever their role. There is no learner condition here; the narrower `may_manage_account` is for management actions only. Core lets any `PREVENT` win over any `FORCE_ALLOW`, so it cannot override another plugin's refusal. It reads the viewer's managers cohorts with one read of `{cohort}` joined to `{cohort_members}`, because `cohort_get_user_cohorts()` skips hidden cohorts; `cohort.idnumber` is not indexed in core, so `moodle/local_ltuse/README.md` lists this read as a Principle XI exception. It also reads `profile_user_record()`, `has_coursecontact_role()` and `has_capability()`, the last on `$usercontext ?? context_user::instance($user->id)`, and, only when the first cases do not decide, the participant path through `enrol_get_shared_courses()` and `get_user_roles()`. It writes nothing. It runs only while `forceloginforprofiles` is `1`. The 2026-10-01 contract, "never `VIEWPROFILE_FORCE_ALLOW`", is superseded.

## Organisation access check (`local_ltuse`) *(2026-10-02)*

`local_ltuse\organisation\access` is the one shared check behind every management surface (FR-006a, research R10). It is a pure class, tested without Moodle by `tests/org_access_harness.php` in CI, like `profile_access`. Its inputs and per-action rules are in the data model's "Organisation access decision". It has two predicates:

| Predicate | Holds when | Callers |
|---|---|---|
| `is_org_member_of_manager(V, P)` | V is not P; P's `ltct_org` is non-empty and one of V's managed keys; P is in the `ltct:org:<that key>` cohort. No role condition. | the profile hook's `FORCE_ALLOW`; the "my organisation" page's list and progress view; spec 016's entitlement, which is asked to call it rather than re-derive it |
| `may_manage_account(V, P)` | the first, and P is a learner: not deleted, not a site admin, not staff (`has_coursecontact_role()`), no role assignment at system or any category context (`get_user_roles()`), in no managers cohort, not in `ltct:mentors` | every management action: enrol, unenrol, reset link, suspend, reactivate, assign and end mentors |

Pages: `local/ltuse/organisation.php` (the "my organisation" page, linked from the user menu for managers-cohort members only) and `local/ltuse/mentors.php?userid=`. Each page calls `require_login()`, checks a sesskey on every write, and re-runs the check for the person named in the request, so an edited URL or form post naming someone else is refused. It reads managers-cohort and `ltct:mentors` membership on every request, so a removed manager loses everything at once. It shows each person's email, protected people's included (Doug, 2026-10-02).

Action contracts *(2026-10-02)*:
- **Enrol** goes through the course's **organisation-enrolment instance**, never the manual instance: one `enrol_self` instance named "Organisation enrolment", with `customint6 = 0` (no learner can self-enrol), a random enrolment key and no welcome message, found again by a `customchar1` marker and created on first use with `enrol_get_plugin('self')->add_instance()`. The target course has an `ltct:` idnumber and sits in `ltct:published` or in `ltct:org:<P's ltct_org>`; never `ltct:pilots`. The role is always Student.
- **Unenrol** only from that instance. Cohort-sync and manual (pilot) enrolments are never touched.
- **On a move of organisation**, enrolments through that instance in the old organisation's `ltct:org:*` courses are suspended by the R12 observer on `cohort_member_removed` and by its reconcile task; those in shared courses stand.
- **Reset link**: `core_login_process_password_reset($user->username, '')`, which applies core's own guards and emails only P's own address.
- **Suspend / reactivate**: `user_update_user()` with a minimal `{id, suspended}` object, never a reloaded full record; suspend ends P's sessions first with `destroy_user_sessions()`.

## Publisher *(amended 2026-10-02)*

`scripts/publish_moodle.py` sends `groupmode: 0` on course update as well as on create, so courses published before this change lose separate groups on their next publish (R3). The 2026-10-01 `groupmode: 1` is superseded.

For a course whose payload `placement.org_only` is true, the publisher then calls `local_ltuse_place_course` with the course's `ltct:<slug>` idnumber and `placement.category_idnumber`, on every publish, so the placement is re-asserted each time (R11). A shared course keeps `--category`, which still takes a numeric id. `moodle_client.py` lists `local_ltuse_place_course` among its required functions. `check_moodle_payload.py` checks `placement`'s shape before anything leaves the machine: both keys present, `category_idnumber` is `ltct:org:<key>` exactly when `org_only` is true and null otherwise. `discussion.shared` is removed from the payload, and `ensure_discussion` loses its `shared` parameter: every course forum is created and kept at no groups (R14).

### Web service `local_ltuse_place_course` *(2026-10-02)*

| | |
|---|---|
| Parameters | `courseidnumber`, `categoryidnumber` |
| Capability | `local/ltuse:publish` |
| Accepted targets | a category whose idnumber is `ltct:org:<key>`, `ltct:pilots` or `ltct:published`; anything else is refused |
| Resolves | the category by a read of `course_categories.idnumber`, which is not indexed in core (listed in `moodle/local_ltuse/README.md` as a Principle XI exception) |
| Writes | only when the course is in another category: `move_courses([$id], $catid)`, which fires `course_updated` |
| Returns | whether it moved the course (a `moved` flag) |

It exists because core's route needs more than the publisher should hold: `core_course_get_categories` by idnumber needs `moodle/category:manage` at system context (`RISK_XSS`), and `core_course_update_courses` checks `moodle/course:changecategory` in the course context only, never the target. Moving into a hidden category hides the course; organisation categories are visible (R6).

## Output additions

The four new item types use spec 001's line and `--json` shapes. Item names:

| Item type | `item` | Example |
|---|---|---|
| Course category | `category:<idnumber>` | `category:ltct:org:independent` |
| Cohort | `cohort:<idnumber>` | `cohort:ltct:org:independent:managers` |
| Profile field category | `profilecategory:<name>` | `profilecategory:About your work` |
| Profile field | `profilefield:<shortname>` | `profilefield:ltct_org` |
| Cohort rule | `cohortrule:<cohort idnumber>` | `cohortrule:ltct:org:independent` |
| Course group mode *(2026-10-02)* | `course:<idnumber>:groupmode` | `course:ltct:<slug>:groupmode` |
| Course placement *(2026-10-02)* | `course:<idnumber>:placement` | `course:ltct:<slug>:placement` |

Apply runs them in this order: categories, cohorts (`ltct:mentors` among them), profile field category, profile fields, cohort rules, then the course items. A rule needs its cohort and its field to exist.

**Course group mode** *(2026-10-02)*. Every `ltct:` course should have group mode `0`. One whose group mode is not `0` is `changed`, and apply sets it to `0` with `update_course()`, as spec 003's `course:<idnumber>:showreports` check does (research R3). An activity's own group mode is not checked (FR-011).

**Course placement** *(2026-10-02)*. Drift reports, without fixing, a course declared in `org-courses.yaml` that is outside its organisation's category, and an undeclared `ltct:` course inside any `ltct:org:*` category. Apply never moves a course, because a move changes which category roles it inherits; the next publish re-places a declared one (research R11).

**Discussion forum** *(amended 2026-10-02)*. Every `ltct:` course forum is kept at no groups, so apply changes every existing forum on its next run (R14). The inspector's "all participants" warning and forum vault count, which caught cross-organisation exposure, are removed. Its "course forces a group mode" warning stays: a forced mode would wall the forum.

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
| An `ltct:` course whose group mode is not `0` *(2026-10-02)* | `changed` on `course:<idnumber>:groupmode`; apply sets `0` | No |
| A declared organisation-only course outside its organisation's category *(2026-10-02)* | `changed` on `course:<idnumber>:placement`; apply leaves it | No |
| An undeclared `ltct:` course inside an `ltct:org:*` category *(2026-10-02)* | `extra` on `course:<idnumber>:placement`; apply leaves it | No |
| A cohort-sync instance for an `ltct:org:%:managers` cohort in an `ltct:` course outside the `ltct:org:*` categories *(2026-10-02)* | `[fail]`, with a count only, never a course, cohort or person name (research R2) | Yes |
| `tool_dynamic_cohorts` absent or at the wrong release | the plugin's own `missing` / `wrong-release` | Yes. As in spec 001, apply writes nothing while a declared plugin blocks |

Redaction adds one rule: a cohort is reported by `idnumber` and name only. No line, and no `--json` field, lists a cohort's members or gives a member count. *(2026-10-02.)* A course item is reported by the course's `idnumber` only; it never names an enrolled person or an enrolment count.
