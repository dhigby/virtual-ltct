# Contract: Configuration declaration

The declaration is YAML under `moodle/site/`, read only by `scripts/site_config.py`. The PHP side receives the same content as JSON on stdin. It is the single statement of what a correctly configured server looks like.

## Files

| File | Holds |
|---|---|
| `moodle/site/site.yaml` | The minimum Moodle release and the plugins and their pins. |
| `moodle/site/ignore.yaml` | The `ignore` list: undeclared settings drift expects to differ from default. |
| `moodle/site/roles.yaml` | Roles: archetype plus overrides at system context. |
| `moodle/site/settings/*.yaml` | Settings, one file per topic, each citing the `moodle/REQUIREMENTS.md` rows it serves. |
| `moodle/site/README.md` | How to add a setting and what each kind means. Not read by the tool. |

Those are spec 001's own. Every other file under `moodle/site/`, and every section a later spec adds to one of the files above, is listed in [Extensions](#extensions) below.

## Extensions

This table is the index of every extension of the declaration: every spec that adds a file under `moodle/site/`, or a section to an existing one (Doug, 2026-10-05). It was built from the files themselves, from `TOP_FILES` in `scripts/site_config.py` (:241-274) and from each spec's own contract. `validate` refuses a top-level YAML file that is not in `TOP_FILES` (`scripts/site_config.py` :539-545). A spec that extends the declaration adds its row here in the same change.

| File or section | Spec | What it declares | Contract |
|---|---|---|---|
| `organisations.yaml` | 002 | The shared categories, the partner organisations and the `ltct:mentors` cohort. 011 adds the `mentoring` category. | [002](../../002-org-structure-cohorts/contracts/declaration.md) |
| `profile-fields.yaml` | 002 | The profile field category and its fields. | [002](../../002-org-structure-cohorts/contracts/declaration.md) |
| `org-courses.yaml` | 002 (amendment 2026-10-02, R11) | The organisation-only courses, maintainer-declared. Loaded by `load_org_courses()`, which the publisher also uses. | [002](../../002-org-structure-cohorts/contracts/declaration.md) |
| `settings/groups.yaml`, `settings/cohorts.yaml` | 002 | No groups by default, cohort sync that suspends rather than unenrols, and profiles behind login; how `tool_dynamic_cohorts` keeps members and how soon it follows a profile change. | [002](../../002-org-structure-cohorts/contracts/declaration.md) |
| `roles.yaml`: `orgmanager`; `ignore.yaml`: `enrol_flatfile/map_10`; `site.yaml`: `tool_dynamic_cohorts` (pinned), `enrol_cohort`, `enrol_self` | 002 | The organisation manager role and the flat-file role-mapping field its role id creates, and the plugins behind organisation cohorts and the organisation-enrolment instance. | [002](../../002-org-structure-cohorts/contracts/declaration.md) |
| `roles.yaml`: `mentor`, and the `allowassign` key on any role; `ignore.yaml`: `enrol_flatfile/map_11` | 003 | The user-context Mentor role, which roles may assign which, and the flat-file role-mapping field the role's id creates. | [003](../../003-mentor-role/contracts/declaration.md) |
| `settings/mentoring.yaml` | 003 | `moodlecourse/showreports` 0, so a mentor never reaches the activity reports. | [003](../../003-mentor-role/contracts/declaration.md) |
| `reports.yaml`, `course-fields.yaml`, `settings/completion.yaml` | 004 | Report templates with their audiences and schedules, the two course custom fields, and the completion switches. Also the competency list, rendered from `competencies.yaml` with no file here; report capabilities on `orgmanager` and `moodle/course:changelockedcustomfields` on `ltcpublisher` in `roles.yaml`; `enrol_manual` in `site.yaml`. | [004](../../004-progress-reporting/contracts/declaration.md) |
| `pathways.yaml` | 006 | Role pathways (ships with `roles: []`), and the `levels` and `role_pathways` payload arrays; each competency gains a `slug` and `url`. | [006](../../006-learning-pathways/contracts/declaration.md) |
| `settings/admin.yaml` | 008 | `allowaccountssameemail` 0; `authloginviaemail` 1 (Doug, 2026-10-05; 086006c); the general account rules `protectusernames` 1, `registerauth` empty and `authpreventaccountcreation` 1 (moved from 016's `identity.yaml` (Doug, 2026-10-05 (scope review)), 1cb6ca6); and `local_ltuse/coursementorsync`, 1 since 2026-10-05 (#97). | 008 has no declaration contract of its own: [research R13](../../008-admin-tooling/research.md) and the [plan](../../008-admin-tooling/plan.md) |
| `site.yaml`: `auth_webservice` enabled, `auth_email` disabled | 008 | The login methods beside manual and nologin. They are plugin entries, not settings, because core keeps them in `$CFG->auth`, which is in no admin setting. See `site.yaml` below. | [008 research R13](../../008-admin-tooling/research.md) |
| `roles.yaml`: `ltctadmin`; `ignore.yaml`: `enrol_flatfile/map_12` | 008 | The site team's administration role (R12; R5 grants it `local/ltuse:manageprotection`), and the flat-file role-mapping field its role id creates. | [008 research R12](../../008-admin-tooling/research.md) and the [plan](../../008-admin-tooling/plan.md) |
| `settings/caching.yaml`; `settings/mobile.yaml`: `tool_mobile/autologout`, `tool_mobile/forcelogout` | 009 | Browser and server caching kept on, and an offline learner never logged out. | [009](../../009-low-bandwidth-delivery/contracts/site-settings.md) |
| `office-hours.yaml`, `dashboard.yaml`, `settings/calendar.yaml`, `settings/scheduler.yaml` | 011 | The office-hours course and its scheduler, the default dashboard's blocks, and the calendar, time zone and scheduler settings. Also the `mod_scheduler` pin, the `mentoring` category, scheduler capabilities for `student` and `teacher`, and `moodle/calendar:manageentries` on `orgmanager` in `roles.yaml` (D3). | [011](../../011-events-calendar/contracts/declaration.md) |
| `roles.yaml`: `mod/workshop:viewauthornames` on `student`, `moodle/site:accessallgroups` on `teacher`, three capabilities on `ltcpublisher` | 012 | Anonymous peer review and the marking guide. 012 is parked for re-plan (Doug, 2026-10-05). The `course-discussions.yaml` its contract proposed was retired by spec 002 R14, and `validate` refuses the file (`RETIRED_FILES`). | [012](../../012-assignments-peer-review/contracts/site-declaration.md) |
| `badges.yaml`, `badges/`, `certificate/`, `settings/badges.yaml` | 013 | The completion badge template and its image, the certificate site template and its images, the badge settings, the `mod_customcert` and `availability_coursecompleted` pins, and `badges_badgesalt` in `ignore.yaml`. Also, in `roles.yaml`, `moodle/badges:viewotherbadges: inherit` on `user` and `allow` on `mentor` (R10), and the badge and `mod/customcert` capabilities on `ltcpublisher`. | [013](../../013-certificates-badges/contracts/declaration.md) |
| `protection.yaml`, `settings/identity.yaml` | 016 | The protection levels and what each withholds, and the identity settings kept for everyone. Also the protection capabilities and the course-backup prohibits in `roles.yaml`, and the cohort scope condition in `reports.yaml`. | [016](../../016-identity-protection/contracts/declaration.md) |

**Spec 002 extends this contract.** [Its declaration contract](../../002-org-structure-cohorts/contracts/declaration.md) adds three things:
- `organisations.yaml` and `profile-fields.yaml`;
- four item types the applier handles after settings: course categories, cohorts, profile fields and `tool_dynamic_cohorts` cohort rules;
- the `local_ltuse_control_view_profile()` profile hook.

Its amendment of 2026-10-02 (open courses, 002 research R11 and R14) adds `org-courses.yaml`, read only by `site_config.load_org_courses()`, which both `validate` (the `org_courses` payload drift checks placement against) and the publisher call, so they cannot disagree. After its four item types the applier sets each `ltct:` course's group mode to 0 and its discussion forum to no groups. It retires `course-discussions.yaml`: `validate` refuses the file.

**Spec 003 extends it again.** [Its contract](../../003-mentor-role/contracts/declaration.md) adds the `mentor` role (user context, every capability managed), an `allowassign` key any role in `roles.yaml` may carry, and `settings/mentoring.yaml`. The applier also turns each `ltct:` course's "Show activity reports" off again where someone turned it on.

**Spec 004 extends it again.** [Its declaration contract](../../004-progress-reporting/contracts/declaration.md) adds:
- `reports.yaml` (top-level keys `rows`, `purpose`, `reports`) and `course-fields.yaml` (`rows`, `category`, `fields`, optional `purpose`). A report template with `per: organisation` is expanded by `validate` into one report per organisation in `organisations.yaml`, with area `org_<key>_<template key>`, so PHP never sees an organisation;
- `settings/completion.yaml`, an ordinary settings file under the rules below (`enablecompletion`, `moodlecourse/enablecompletion`, `moodlecourse/showcompletionconditions`);
- four payload arrays the applier handles after spec 002's, in this order: `course_field_category` and `course_fields` (one class, `coursefields`: the category, then the fields), `competencies`, then `reports` last, because a report's columns and audiences need the fields and cohorts made before it;
- the competency list, which has no file under `moodle/site/`: `validate` renders it from the repo-root `competencies.yaml`, leaving out the `Meta` category, into the plugin's own `local_ltuse_competency` table;
- report identity by (`component = local_ltuse`, `area`), never by id or name. More than one match is `ambiguous`, and that report is left alone;
- a second blocking scope. A report-scoped problem, such as an audience cohort that does not exist yet, leaves only that report unwritten; the rest of the run still applies. An `entity:name` the datasource does not offer still blocks the whole run, as an unknown setting does, except where this run can still create it (a course or profile custom field applied first, or a column of the `local_ltuse` datasource, whose plugin upgrade comes first); those block only their report.

Apply still never deletes: a competency no longer declared is kept and marked retired, and an undeclared report or course field is reported `extra` and kept. A creation is reported as spec 002 reports one: status `changed`, kind `missing`, message `created`. Spec 004 also changes `roles.yaml` (`report/progress:view` and `report/completion:view` on `orgmanager`, `moodle/course:changelockedcustomfields` on `ltcpublisher`) and re-pins `local_ltuse` in `site.yaml`.

**Spec 006 extends it again.** [Its declaration contract](../../006-learning-pathways/contracts/declaration.md) adds `pathways.yaml` and two payload arrays the applier handles after `competencies`: `levels` (the four level labels from `outcome-levels.yaml`, stored as `local_ltuse/pathwaylevel1`–`4`) and `role_pathways`. Each `competencies` entry gains a `slug` and a `url`. A role pathway no longer declared is retired, never deleted.

**Spec 008 extends it again.** It has no declaration contract of its own; [research R13](../../008-admin-tooling/research.md) and the [plan](../../008-admin-tooling/plan.md) say what it declares:
- `settings/admin.yaml`, an ordinary settings file: `allowaccountssameemail`, `authloginviaemail` (Doug, 2026-10-05; added there directly in 086006c), the general account rules `protectusernames`, `registerauth` and `authpreventaccountcreation` (moved there from 016's `identity.yaml` (Doug, 2026-10-05 (scope review)), 1cb6ca6), and `local_ltuse/coursementorsync`;
- the login methods as plugin entries in `site.yaml`, `auth_webservice` enabled and `auth_email` disabled, because `$CFG->auth` is in no admin setting (see `site.yaml` below);
- the `ltctadmin` role in `roles.yaml`, and `enrol_flatfile/map_12` in `ignore.yaml`, the role-mapping field that role's id creates.

**Spec 009 extends it again.** [Its contract](../../009-low-bandwidth-delivery/contracts/site-settings.md) adds `settings/caching.yaml` and two settings in `settings/mobile.yaml` (`tool_mobile/autologout`, `tool_mobile/forcelogout`). Both are ordinary settings files.

**Spec 013 extends it again.** [Its declaration contract](../../013-certificates-badges/contracts/declaration.md) adds:
- `badges.yaml` (one badge template) and `certificate/template.yaml` (one `mod_customcert` site template), each checked by `validate` with `scripts/cbc_wording.py`, rendered against every course in `modules/`;
- `settings/badges.yaml`, an ordinary settings file, and two plugin pins, `mod_customcert` and `availability_coursecompleted`;
- two payload items the applier handles after `reports`: `badge_template` (stored, then every mapped badge reworded in place) and `certificate_template` (the site template, then each activity's copy). Images travel as base64. A second site template with the declared name is `ambiguous` and blocks the run;
- `badges_badgesalt` in `ignore.yaml`, never declared, because changing it breaks every issued badge;
- in `roles.yaml`, `moodle/badges:viewotherbadges: inherit` on `user`, so a learner's badges are not shown to other learners, and `allow` on `mentor`, so an assigned mentor still sees them (R10), and the badge and `mod/customcert` capabilities on `ltcpublisher`, so the publisher can make each course's badge and certificate activity.

Apply never deactivates, archives or deletes a badge, and never deletes a certificate activity or a site template.

**Spec 011 extends it again.** [Its declaration contract](../../011-events-calendar/contracts/declaration.md) adds:
- `office-hours.yaml` (the one office-hours course and its `mod_scheduler` activity) and `dashboard.yaml` (blocks the default dashboard carries);
- `settings/calendar.yaml` and `settings/scheduler.yaml`, ordinary settings files, and one plugin pin, `mod_scheduler`;
- the `mentoring` category in `organisations.yaml`, scheduler capabilities for `student` and `teacher` in `roles.yaml`, and `moodle/calendar:manageentries` on `orgmanager` (D3);
- two payload items the applier handles last: `officehours` (the course, the activity, its enrolment instance and group name template, then a reconcile of memberships) and `dashboard` (each missing block added to the default dashboard).

Apply never deletes the office-hours course, its activity, a group or a dashboard block.

**Spec 016 extends it again.** [Its declaration contract](../../016-identity-protection/contracts/declaration.md) adds:
- `protection.yaml` (the protection levels and the account fields each withholds) and `settings/identity.yaml`;
- protection capabilities in `roles.yaml`, and a course-backup prohibit for `editingteacher` and `teacher` (the `text` datatype, `ltct_certname` and the `userfield` certificate element were removed by 016's scope review, 2026-10-05);
- one payload item, `protection`, which the applier stores after structure and before reporting.

Apply never reads or writes any user's protection: who is protected is Moodle data. There is no organisation minimum (Doug, 2026-10-05 (scope review)).

Everything in this contract still holds for them.

The baseline settings files are `content-embeds.yaml` (#3), `mobile.yaml` (#4), `data-export.yaml` (#16), `messaging.yaml` (#19), `notifications.yaml` (#25) and, once a per-server value is needed, `server.yaml` (values from the environment). The first drift run found none, because `debug` and `wwwroot` live in `config.php`. Later specs add their own file here (e.g. 008 `admin.yaml`, 016 `identity.yaml`); [Extensions](#extensions) lists them all.

## Values

- A literal value is a string or number, compared with the setting's stored value.
- `env:NAME` means the value comes from environment variable `NAME` at `apply` time. It is the only way a secret or a per-server value (mail host, `noreplyaddress`, `mobilecssurl`'s host) enters the declaration. The repo never holds the value.
- `validate` fails on any value that looks like a literal secret.

## `site.yaml`

```yaml
moodle:
  requires: 2026042003.03    # $CFG->version of 5.2.3+ (Build: 20260928); apply refuses below it
  release: "5.2.3+ (Build: 20260928)"
plugins:
  - component: <frankenstyle>   # e.g. mod_scorm, filter_displayh5p, message_airnotifier, local_ltuse
    enabled: 1 | 0              # optional; a filter_* takes on | off | disabled. Absent = not managed
    version: <int>              # non-standard only: the $plugin->version stamp it is pinned to
    source:                     # non-standard only, for provisioning (spec 015); the applier never reads it
      url: <string>             # a third-party release archive, with
      sha256: <hex>             #   its checksum
      path: <repo path>         # or, for a plugin in this repo (local_ltuse); its version.php must equal `version`
    why: <string>               # required: which row or spec needs it
```

- A core plugin has no `version` or `source`.
- Login methods are declared here as `auth_*` plugin entries with `enabled`, not as a setting, because core keeps them in `$CFG->auth`, which is in no admin setting (spec 008 R13; `moodle/site/site.yaml`, the row #14 entries `auth_webservice` and `auth_email`). The applier sets them through the plugininfo class's `enable_plugin()`, as for any plugin (`applier.php`). Drift checks only the auth plugins declared here: another login method enabled on the server, OAuth2 say, is not reported.

## `ignore.yaml`

```yaml
ignore:
  - setting: <name or plugin/name>
    reason: <string>            # required; why this undeclared non-default value is expected
```

## `roles.yaml`

```yaml
roles:
  - shortname: <string>         # e.g. ltcpublisher, editingteacher, manager
    name: <string>              # required only when apply may create the role
    description: <string>       # optional, passed to create_role()
    archetype: <string>         # expected permissions start from get_default_capabilities(archetype)
    contextlevels: [system, coursecat, course]   # required only when apply may create the role
    capabilities:
      <capability>: allow | prevent | prohibit | inherit
    why: <string>               # required
```

- Capabilities are set at system context. The expected set is the archetype's defaults with this map laid over them. A role with no archetype starts empty.
- `inherit` removes the system-context permission (`unassign_capability()`). The others assign it with overwrite.
- An unknown capability is a server-side `[fail]` and blocks `apply`.
- Baseline (#3): `moodle/site:trustcontent` is `inherit` for `editingteacher` and `allow` for `manager` and `ltcpublisher`.

## `settings/*.yaml`

```yaml
rows: [<row>, ...]              # the moodle/REQUIREMENTS.md rows this file serves, e.g. [3]; server.yaml uses []
purpose: <string>               # one paragraph a reviewer reads first
settings:
  - name: <name or plugin/name> # e.g. enabletrusttext, scorm/scormstandard, tool_dataprivacy/dporoles
    value: <literal or env:NAME>
    secret: true                # optional, only with env:; shown as <secret>, drift checks set/empty only
    why: <string>               # required: what breaks without it
    verify: <string>            # optional: the instance check that confirmed it (FR-017)
```

- A setting is identified by `name`: `name` for core, `plugin/name` for a plugin. Reports use the same key.
- Values are strings or integers. YAML booleans are rejected; write `1` or `0`.
- A key may appear in only one settings file.

The full validation rules are in [data-model.md](../data-model.md).

## Baseline identifiers (from research R9)

| Row | Identifier | Declared value |
|---|---|---|
| #3 | `mod_scorm` (plugin), `scorm/scormstandard` | enabled; 0 (core default: relaxed limits, so more 1.2 packages run) |
| #3 | `mod_h5pactivity`, `filter_displayh5p` (plugins) | enabled |
| #3 | `filter_mediaplugin`, `media_vimeo`, `media_videojs` (plugins) | enabled |
| #3 | `enabletrusttext` | `1` |
| #4 | `enablewebservices`, `enablemobilewebservice` | `1`, `1` |
| #4 | `mobilecssurl` | `/local/ltuse/styles.css` from `MOODLE_URL` |
| #4 | `tool_mobile/disabledfeatures` | empty |
| #16 | `tool_dataprivacy/contactdataprotectionofficer` | `1` |
| #16 | `tool_dataprivacy/automaticdataexportapproval`, `tool_dataprivacy/automaticdatadeletionapproval` | `0`, `0` |
| #19 | `messaging`, `messagingallusers` | `1`, `0` |
| #25 | `defaultpreference_maildigest` | `1` |
| #25 | `message_airnotifier` (plugin) | enabled |

`moodle.requires` is `2026042003.03` (R10). Settings that `config.php` sets, such as `wwwroot` and `debug`, are never declared (R6). Google Drive embeds (R9) are settled at T025.
