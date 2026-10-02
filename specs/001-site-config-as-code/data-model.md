# Data Model: Site configuration as code

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Research**: [research.md](research.md)

This feature adds no database table. Its entities live in three places: YAML files under `moodle/site/` in the public repo (the declaration), the operator's environment (the target and the secrets), and the Moodle site itself (the live state the declaration is compared with). What travels between them is one JSON payload on stdin and one report on stdout.

| Entity | Where it lives | Public repo? | New or reshaped |
|---|---|---|---|
| Configuration declaration | `moodle/site/` | Yes | New |
| Minimum release | `moodle/site/site.yaml` → `moodle` | Yes | New |
| Plugin declaration | `moodle/site/site.yaml` → `plugins` | Yes | New |
| Ignore entry | `moodle/site/ignore.yaml` → `ignore` | Yes | New |
| Settings file | `moodle/site/settings/*.yaml` | Yes | New |
| Setting declaration | one entry in a settings file | Yes (never a secret value) | New |
| Environment reference | `env:NAME` value in a setting | Name only | New |
| Role declaration | `moodle/site/roles.yaml` | Yes | New; absorbs `ltcpublisher` from `setup_publishing.php` |
| Server target | operator's environment | No | New |
| Rendered payload | stdin of `site_config.php` only, in memory | No | New |
| Item result | one line of a run report | Evidence only, redacted | New |
| Run report | stdout of `site_config.py apply` / `drift` | Evidence only, redacted | New |

The spec's four key entities map onto these: **Configuration declaration** is the first six rows plus roles; **Server target** is unchanged; **Drift report** is a run report in `drift` mode; **Secret reference** is an environment reference marked `secret`.

---

## Configuration declaration

The single statement of what a correctly configured server looks like (FR-001). It is the union of `site.yaml`, `roles.yaml` and every file in `settings/`. It has no identity of its own beyond the git commit it is read from.

### Fields

| Field | Source | Notes |
|---|---|---|
| `moodle` | `site.yaml` | One minimum release. Required. |
| `plugins` | `site.yaml` | List of plugin declarations. May be empty only in theory; the baseline declares several. |
| `ignore` | `ignore.yaml` | List of ignore entries. |
| `roles` | `roles.yaml` | List of role declarations. |
| `settings` | `settings/*.yaml` | The union of every settings file's setting declarations. |

### Validation rules (`site_config.py validate`, run in CI on `moodle/site/**`)

- Every file parses as YAML and matches its schema; an unknown top-level key is an error, not ignored.
- Setting keys are unique across *all* settings files. Two files declaring the same setting is an error, so a setting has exactly one reviewed home (FR-011).
- Plugin `component` values are unique. Role `shortname` values are unique.
- No ignore entry names a setting that is also declared.
- Every setting whose owning plugin is non-core names a component that is either standard in Moodle core or declared in `plugins` (the edge case "a setting belongs to a plugin that is not installed" is caught early where it can be; the server check in R4 catches the rest).
- Nothing in any file looks like a literal secret: a setting whose name matches a secret-shaped pattern (`*password*`, `*secret*`, `*token*`, `*key*` other than known non-secret keys) must have an `env:` value with `secret: true` (FR-006, SC-004).
- No file contains a hard-coded host. A value that parses as an absolute URL is an error unless it is built from `MOODLE_URL` (FR-005, constitution II).

---

## Minimum release

The oldest Moodle the declaration was verified against (FR-002, R10).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `requires` | number | Yes | The `$CFG->version` stamp of 5.2.3+ (Build 20260928). `2026042003.03`, read on the instance (R10). |
| `release` | text | Yes | Human name, e.g. `5.2.3+ (Build: 20260928)`. Printed in the refusal; never compared. |

### Validation rules

- `requires` is a `YYYYMMDDXX.XX` version number, and is not lower than `local_ltuse`'s own `$plugin->requires`.
- On the server: `$CFG->version < requires` is a blocking problem; apply refuses before any write and names both releases (US1-4).

---

## Plugin declaration

One plugin the training system depends on, and the state it must be in (FR-001, FR-003, R7).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `component` | frankenstyle name | Yes | e.g. `mod_scorm`, `filter_displayh5p`, `message_airnotifier`, `local_ltuse`. |
| `enabled` | boolean, or for a filter `on` / `off` / `disabled` | No | Absent means "whatever it is; not managed". Applied through the plugininfo class's `enable_plugin()`. |
| `version` | integer | Non-standard only | The `$plugin->version` stamp verified. Forbidden on a standard (core) plugin, whose version is the Moodle release. |
| `source` | map | Non-standard only | Where provisioning (spec 015) gets the code. Either `{url, sha256}` for a third-party release, or `{path}` for a plugin in this repo. The applier never reads it. |
| `source.url` | URL | with `sha256` | The release archive. |
| `source.sha256` | hex string, 64 chars | with `url` | Checksum of that archive. |
| `source.path` | repo path | alternative to `url` | e.g. `moodle/local_ltuse`; its `version.php` stamp must equal `version`. |
| `why` | text | Yes | One line: which requirement row or spec needs it. |

### Validation rules

- A non-standard plugin without `version` and `source` is an error.
- For `source.path`, `validate` reads `version.php` and fails if its stamp differs from `version`, so the pin cannot silently drift from the code in the repo.
- `enabled` values other than `on` / `off` / `disabled` are rejected for a `filter_*` component; those three are rejected for any other type.

### Live states and how each is reported

| Live state | Reported as | Blocks apply? |
|---|---|---|
| Installed, `versiondb = versiondisk = version`, enabled state matches | `[ok]` | No |
| Installed and pinned correctly, enabled state differs | `changed` (drift) / `[changed]` (apply, after `enable_plugin()`) | No |
| Not installed | `missing` | Yes |
| `versiondb ≠ version` | `wrong-release`, naming pinned and installed | Yes (US1-3) |
| `versiondisk ≠ versiondb` | `pending-upgrade` | Yes |
| Installed, non-standard, not declared | `extra` | No (drift failure only) |

Apply never installs, upgrades or downgrades code; a blocking plugin state is fixed by provisioning or by a reviewed pin change, then apply is re-run.

---

## Ignore entry

An undeclared setting that drift should not flag even though it differs from Moodle's default (R5). Typically an install-time value such as `supportemail`, `timezone` or `siteidentifier`.

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `setting` | setting key | Yes | Same key form as a setting declaration. |
| `reason` | text | Yes | Why it is per-install and not declared. An entry without a reason is an error. |

### Validation rules

- Must not name a declared setting.
- The initial list comes from the first drift run on the build host (R5); each entry there is either declared, moved to `server.yaml` as `env:`, or ignored here with a reason.

---

## Settings file

One topic's worth of settings, each file citing the `moodle/REQUIREMENTS.md` rows it serves (constitution X).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `rows` | list of integers | Yes | e.g. `[3]` for `content-embeds.yaml`. `server.yaml` uses `[]` and says so in `purpose`. |
| `purpose` | text | Yes | One paragraph a reviewer reads first. |
| `settings` | list of setting declarations | Yes | |

### Validation rules

- Every cited row exists in `moodle/REQUIREMENTS.md`.
- File name is lowercase-hyphenated `.yaml`; one topic per file. Specs 002–014 add their own file rather than editing another spec's (plan, cross-spec effects).

The baseline files and their rows: `content-embeds.yaml` (#3), `mobile.yaml` (#4), `data-export.yaml` (#16), `messaging.yaml` (#19), `notifications.yaml` (#25), `server.yaml` (per-server values only, all `env:`).

---

## Setting declaration

One admin setting and the value a correctly configured server holds for it (FR-001, R4).

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | setting key | Yes | `<name>` for a core `$CFG` setting (`enabletrusttext`), `<plugin>/<name>` for a plugin setting (`scorm/scormstandard`, `tool_dataprivacy/dporoles`). Matched against the admin tree by plugin and name. |
| `value` | string, integer, list of strings, or environment reference | Yes | Rendered to the form `write_setting()` expects: integer → string, list → the setting's multi-value form. |
| `secret` | boolean | No, default `false` | Only valid with an `env:` value. Marks the setting as a secret reference. |
| `why` | text | Yes | What breaks or which acceptance scenario fails without it. |
| `verify` | text | No | The check that confirmed it on the 5.2.3+ instance (FR-017, R9's "Verify" column). |

### Validation rules

- YAML booleans are rejected as values. `on`, `off`, `yes`, `no`, `true` and `false` are written as `1` or `0`, because YAML 1.1 silently turns `off` into `false` and the review would not show it.
- `secret: true` without an `env:` value is an error; a secret is never a literal (FR-006).
- On the server, a declared name that is not found in the admin tree is `unknown`: a blocking error, not a skip. That covers a missing plugin and a setting renamed by an upgrade (spec edge cases).
- On the server, a name present in `$CFG->config_php_settings` or `$CFG->forced_plugin_settings[plugin]` is `forced`; apply refuses to write it (R6).

### Item states in one run

```text
            ┌─────────── forced ───────────► [fail] (config.php overrides it)
            ├─────────── unknown ──────────► [fail] (not in admin tree)
declared ───┼── env var missing ───────────► [fail] (NAME is not set; nothing written)
            ├── live == declared ──────────► [ok]
            └── live != declared ─┬─ drift ► [fail] changed (declared vs live shown)
                                  └─ apply ► write_setting() ─┬─ '' ────► [changed]
                                                              └─ error ─► [fail] (Moodle's message)
```

A secret setting compares only *set* versus *empty*: drift reports `changed` only when the live value is empty, and apply writes it only when it is empty or when the operator passes the resolved value and it differs. Either way the value shows as `<secret>`.

---

## Environment reference

A setting value written `env:NAME`, resolved by `site_config.py` from the operator's environment at run time (R3). Two kinds share one syntax:

| Kind | Marker | Example | Shown in output as |
|---|---|---|---|
| Per-server value | `secret: false` (default) | `noreplyaddress: env:MOODLE_NOREPLY` in `server.yaml` | the resolved value |
| Secret reference | `secret: true` | an SMTP password | `<secret>` |

### Fields

| Field | Type | Notes |
|---|---|---|
| `NAME` | `[A-Z][A-Z0-9_]*` | The environment variable. Documented in `moodle/site/README.md`; names only, never values. |

### Validation rules

- A missing variable fails that one setting with `[fail] … NAME is not set`, writes nothing for it, and makes the run exit 1. It never writes an empty value over a working one (spec edge case).
- A drift run resolves per-server references the same way; it does not need secret references, because secrets are compared only as set or empty.
- Resolved values exist only in memory and in the stdin stream; never in a file, `argv` or a log.
- Any setting whose admin class is `admin_setting_configpasswordunmask` or a subclass is shown as `<secret>` whether declared secret or not, and whether declared at all (fails closed, R3).

---

## Role declaration

A role the training system depends on, and its system-context permissions (FR-001, R8). Reshapes the `ltcpublisher` role, which moves here from `moodle/local_ltuse/cli/setup_publishing.php`; that script keeps the account, service authorisation and token, and now fails with "run site_config.py apply first" if the role is missing.

### Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `shortname` | text | Yes | Identity. e.g. `ltcpublisher`, `editingteacher`, `manager`. |
| `name` | text | When apply may create it | Display name. Not needed for core roles that always exist. |
| `description` | text | No | Passed to `create_role()`. |
| `archetype` | archetype name or empty | When apply may create it | Base permissions are `get_default_capabilities(archetype)`; empty means the role starts with none. |
| `contextlevels` | list of `system` / `coursecat` / `course` / `module` / `user` / `block` | When apply may create it | Applied with `set_role_contextlevels()`. `ltcpublisher` is `[system, coursecat, course]`. |
| `capabilities` | map of capability → `allow` / `prevent` / `prohibit` / `inherit` | No | Overrides at system context, laid over the archetype defaults. |
| `why` | text | Yes | For each override map, which row or spec needs it (e.g. #3 for `moodle/site:trustcontent`). |

### Relationships

- A setting may reference a role by shortname (e.g. `tool_dataprivacy/dporoles: [manager]`); `validate` checks the shortname is declared or is a core role.
- The `ltcpublisher` role is consumed by `setup_publishing.php` and by the publisher's web-service token; the role's capabilities are the list that script used to hard-code.

### Validation rules

- Every capability name is rejected on the server if `get_capability_info()` does not know it; `validate` cannot check this offline, so it is a server-side `[fail]`.
- Expected permissions = archetype defaults ⊕ declared overrides. Drift compares that with the live `role_capabilities` rows for the role at system context; any difference is `changed`, naming capability, expected and live permission.
- Apply never resets a role. It only creates a missing role, sets its context levels, and assigns or unassigns the differing capabilities.

### State transitions

```text
absent ──create_role()──► created ──set_role_contextlevels()──► levels set
   ▲                                                                  │
   │ (never: apply does not delete roles)                             ▼
   └───────────────────────────────────────────────── assign_capability() / unassign_capability()
                                                                      │
                                                                      ▼
                                                                  matches ──(hand edit)──► drifted ──apply──► matches
```

A role absent and lacking `name`, `archetype` or `contextlevels` cannot be created; it is a blocking `missing`.

---

## Server target

The Moodle site a run acts on (FR-005). Never stored in the repo.

### Fields

| Field | Source | Notes |
|---|---|---|
| `url` | `MOODLE_URL` | Required. Must equal the server's `$CFG->wwwroot`, or the run exits 2. |
| `ssh` | `MOODLE_SSH` | Optional. Unset means run locally, which is what an on-server schedule uses. |
| `dir` | `MOODLE_DIR` | Moodle root on the server; the CLI is `$MOODLE_DIR/public/local/ltuse/cli/site_config.php`. Name shared with spec 015. |
| `release`, `version` | read from the server | `$CFG->release` and `$CFG->version`, printed on the first line. |

### Validation rules

- The first line of every run names the target URL and its release, before any write (spec edge case "not the server the maintainer intended").
- A missing `MOODLE_URL` is a usage error (exit 2).

---

## Rendered payload

The declaration after validation and environment resolution, as JSON, written to the PHP script's stdin and nowhere else (R1, R2). PHP never reads YAML.

### Fields

| Field | Notes |
|---|---|
| `mode` | `apply` or `drift`. Also passed as `--mode`; the two must agree. |
| `target_url` | `MOODLE_URL`, for the `wwwroot` check. |
| `moodle` | `{requires, release}`. |
| `plugins`, `ignore`, `roles` | As declared, minus `source` and `why`. |
| `settings` | Each with resolved `value` (or, for a secret in drift mode, no value and `secret: true`), `secret`. |
| `failed_env` | Settings whose variable was missing, so the server can report them in order without seeing a value. |

`site_config.py render` prints this with every secret replaced by `<secret>`, for debugging; the unredacted form is never printed.

---

## Item result

One line of a run report: one setting, plugin, role capability, or preflight check.

### Fields

| Field | Type | Notes |
|---|---|---|
| `status` | `ok` / `changed` / `fail` / `skip` | Printed as `[ok]` etc. (R11). |
| `kind` | `changed` / `missing` / `extra` / `unmanaged` / `wrong-release` / `pending-upgrade` / `forced` / `unknown` / `env-missing` / `below-minimum` | Present unless `status` is `ok`. |
| `subject` | text | Setting key, plugin component, or `role:capability`. |
| `declared` | text or `<secret>` | Omitted for `extra` and `unmanaged`. |
| `live` | text or `<secret>` | Omitted for `missing`. |
| `message` | text | Moodle's error string for a failed write; the missing variable's name; the pinned and installed release. |

`kind` maps onto the spec's four drift categories: **changed** (`changed`), **missing** (`missing`, `env-missing`, `unknown`), **extra** (`extra`, `wrong-release`, `pending-upgrade`), **unmanaged** (`unmanaged`). `forced` and `below-minimum` are additional so that nothing is misclassified into one of those four (SC-003).

### Validation rules

- No item carries learner data: subjects are setting, plugin and capability names only; no item lists a user (FR-009).
- `declared` and `live` are `<secret>` for any secret reference and any password-class setting.

---

## Run report

The full output of one `apply` or `drift` run (FR-007, FR-008, FR-010, R11).

### Fields

| Field | Notes |
|---|---|
| `target` | First line: URL and release. |
| `mode` | `apply` or `drift`. |
| `items` | Item results, in order: preflight, plugins, roles, settings, then (drift only) unmanaged. |
| `summary` | Last line: counts by status, e.g. `3 changed, 41 ok, 0 failed` or `no differences`. |
| `exit_code` | `0` clean or applied, `1` drift found or a step failed, `2` usage or configuration error. |

With `--json`, the same fields as one JSON document, for evidence attached to a PR or operations log.

### State transitions (one run)

```text
start
  │ validate declaration ──── invalid ─────────────────────────────► exit 2
  │ resolve environment (missing vars recorded, not fatal yet)
  │ connect, read wwwroot ─── mismatch / no MOODLE_URL ────────────► exit 2
  │ print target line
  ▼
preflight: below-minimum? missing / wrong-release / pending-upgrade plugin?
           uncreatable role? forced or unknown declared setting?
  │
  ├── any blocking problem ── apply: report all, write nothing ────► exit 1
  │                          drift: report and continue reading
  ▼
compare plugins → roles → settings  (drift: then walk tree for unmanaged)
  │
  ├── drift ── no differences ─► exit 0      ── any difference ────► exit 1
  └── apply ── write each difference ──┬─ all ok, none changed ────► exit 0 "nothing changed" (US1-2, SC-002)
                                        ├─ all ok, some changed ───► exit 0 with change summary (FR-010)
                                        └─ any [fail] (incl. env-missing) ► exit 1
```

### Validation rules

- Drift mode performs no write of any kind: no `write_setting()`, `set_config()`, `enable_plugin()`, `create_role()` or capability change (US2-4).
- Apply is repeatable: a run against a server that matches the declaration produces zero `changed` items (FR-004).
- Apply writes nothing if preflight finds a blocking problem; it does not apply the non-blocked part.
