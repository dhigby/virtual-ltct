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
| `moodle/site/organisations.yaml`, `moodle/site/profile-fields.yaml` | Added by spec 002. Partner organisations, shared categories and profile fields. See below. |
| `moodle/site/reports.yaml`, `moodle/site/course-fields.yaml`, `moodle/site/settings/completion.yaml` | Added by spec 004. Custom report templates with their audiences and schedules, the course custom fields, and the completion switches. See below. |

**Spec 002 extends this contract.** [Its declaration contract](../../002-org-structure-cohorts/contracts/declaration.md) adds three things:
- `organisations.yaml` and `profile-fields.yaml`;
- four item types the applier handles after settings: course categories, cohorts, profile fields and `tool_dynamic_cohorts` cohort rules;
- the `local_ltuse_control_view_profile()` profile hook.

**Spec 004 extends it again.** [Its declaration contract](../../004-progress-reporting/contracts/declaration.md) adds:
- `reports.yaml` (top-level keys `rows`, `purpose`, `reports`) and `course-fields.yaml` (`rows`, `category`, `fields`, optional `purpose`). A report template with `per: organisation` is expanded by `validate` into one report per organisation in `organisations.yaml`, with area `org_<key>_<template key>`, so PHP never sees an organisation;
- `settings/completion.yaml`, an ordinary settings file under the rules below (`enablecompletion`, `moodlecourse/enablecompletion`, `moodlecourse/showcompletionconditions`);
- four payload arrays the applier handles after spec 002's, in this order: `course_field_category` and `course_fields` (one class, `coursefields`: the category, then the fields), `competencies`, then `reports` last, because a report's columns and audiences need the fields and cohorts made before it;
- the competency list, which has no file under `moodle/site/`: `validate` renders it from the repo-root `competencies.yaml`, leaving out the `Meta` category, into the plugin's own `local_ltuse_competency` table;
- report identity by (`component = local_ltuse`, `area`), never by id or name. More than one match is `ambiguous`, and that report is left alone;
- a second blocking scope. A report-scoped problem, such as an audience cohort that does not exist yet, leaves only that report unwritten; the rest of the run still applies. An `entity:name` the datasource does not offer still blocks the whole run, as an unknown setting does, except where this run can still create it (a course or profile custom field applied first, or a column of the `local_ltuse` datasource, whose plugin upgrade comes first); those block only their report.

Apply still never deletes: a competency no longer declared is kept and marked retired, and an undeclared report or course field is reported `extra` and kept. A creation is reported as spec 002 reports one: status `changed`, kind `missing`, message `created`. Spec 004 also changes `roles.yaml` (`report/progress:view` and `report/completion:view` on `orgmanager`, `moodle/course:changelockedcustomfields` on `ltcpublisher`) and re-pins `local_ltuse` in `site.yaml`.

Everything in this contract still holds for them.

The baseline settings files are `content-embeds.yaml` (#3), `mobile.yaml` (#4), `data-export.yaml` (#16), `messaging.yaml` (#19), `notifications.yaml` (#25) and, once a per-server value is needed, `server.yaml` (values from the environment). The first drift run found none, because `debug` and `wwwroot` live in `config.php`. Specs 002–014 add their own file here.

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
| #25 | `message_airnotifier` (plugin) | disabled |

`moodle.requires` is `2026042003.03` (R10). Settings that `config.php` sets, such as `wwwroot` and `debug`, are never declared (R6). Google Drive embeds (R9) are settled at T025.
