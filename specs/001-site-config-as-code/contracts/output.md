# Contract: Apply and drift output

Both `apply` and `drift` print the same output shape. It follows spec 015's `ops-scripts.md` convention.

## Line output (default)

1. The first line names the target (`$CFG->wwwroot`) and its Moodle release, before anything is changed.
2. One line per item, prefixed `[ok]`, `[changed]`, `[fail]` or `[skip]`, naming the item and its kind.
3. A final summary line counting what changed (`apply`) or what differs (`drift`). On a matching server it says nothing changed or no differences.

## Difference kinds

| Kind | Meaning |
|---|---|
| `changed` | A declared setting whose live value differs from the declared value. The line shows both values. |
| `missing` | A declared plugin or role not present on the server. |
| `extra` | An installed non-standard plugin that is not declared. |
| `unmanaged` | An undeclared setting whose value differs from Moodle's default and that is not on the `ignore` list in `ignore.yaml`. |
| `unknown` | A declared setting or capability that is not on the server, including one whose plugin is not installed or that a core upgrade renamed. An error, never silently skipped. |
| `forced` | A setting overridden in `config.php` (`$CFG->config_php_settings` or `$CFG->forced_plugin_settings`). `apply` refuses to write it. |

Plugin pin mismatches and pending upgrades are reported against the plugin, with the pinned and installed releases. Role differences are reported per role and capability, with the declared and live permission.

## `--json`

The same output as one JSON object, for evidence:

```json
{
  "target": "<wwwroot>",
  "release": "<Moodle release>",
  "mode": "apply | drift",
  "items": [
    {"status": "ok | changed | fail | skip", "kind": "<kind above or empty>", "item": "<plugin/name, component or role:capability>", "declared": "<value>", "live": "<value>", "message": "<text>"}
  ],
  "summary": {"changed": 0, "failed": 0, "differences": 0}
}
```

## Redaction (never relaxed)

- A declared `env:` setting shows `<secret>`, never its value. `drift` reports a secret setting only as set or empty.
- Any setting whose admin class is `admin_setting_configpasswordunmask`, or a subclass, shows `<secret>` whether declared or not.
- No output lists users or any learner data.
