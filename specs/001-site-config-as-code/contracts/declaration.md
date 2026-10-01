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
