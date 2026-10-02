# Contract: Site configuration commands

Two commands make up the interface. `scripts/site_config.py` runs on the operator's machine (or on the server, for a schedule). `moodle/local_ltuse/cli/site_config.php` runs on the server and is only ever started by the Python command.

## `scripts/site_config.py`

- **Usage**: `site_config.py <validate|render|apply|drift> [--json]`
- **Reads**: the declaration under `moodle/site/` (see [declaration.md](declaration.md)) and the environment variables below. Nothing else.
- **Writes**: nothing into the repo working tree. `render` writes to stdout only.

### Subcommands

| Subcommand | Touches a server | Does |
|---|---|---|
| `validate` | no | Parses every file under `moodle/site/`, checks the shape in [declaration.md](declaration.md), and fails on anything that looks like a literal secret. CI runs this (`.github/workflows/site-config.yml`) on changes to `moodle/site/**`. |
| `render` | no | Prints the declaration as the JSON stream `site_config.php` reads, with every `env:` value shown as `<secret>` or as its variable name, never resolved. For review and debugging. |
| `apply` | yes, writes | Resolves `env:` values from the local environment, streams the JSON to `site_config.php --mode=apply`, and relays its report. |
| `drift` | yes, read-only | Streams the JSON to `site_config.php --mode=drift` with no secret resolved, and relays its report. Changes nothing on the server. |

### Options

- `--json`: print the report as JSON (see [output.md](output.md)) instead of lines, for evidence kept with a pull request or operations log.

### Environment

| Name | Required by | Meaning |
|---|---|---|
| `MOODLE_URL` | `apply`, `drift` | The target site. It must equal the server's `$CFG->wwwroot`, or the run exits 2. Never hard-coded. Same name the publisher and spec 015 use. |
| `MOODLE_SSH` | optional | The `ssh` destination that runs `site_config.php`. When unset, the PHP script is run locally, which is what an on-server schedule uses. |
| `MOODLE_DIR` | `apply`, `drift` | The Moodle code directory on the server. The PHP script is `$MOODLE_DIR/public/local/ltuse/cli/site_config.php`. Same name spec 015 defines. |
| any `env:NAME` | `apply` only | A variable a declared setting names (for example `MOODLE_DEBUG`, `MOODLE_DEBUGDISPLAY`, or an outgoing-mail password). Resolved in memory and sent only inside the stdin stream. |

A missing `env:NAME` variable at `apply` time skips that one setting with `[fail] … NAME is not set`, writes nothing for it, and makes the run exit 1. It never writes an empty value over a working one. `drift` never needs a secret.

## `moodle/local_ltuse/cli/site_config.php`

- **Usage**: `php site_config.php --mode=apply|drift`, with the rendered declaration as JSON on stdin.
- **Runs as**: the web user, as a Moodle admin CLI script (`CLI_SCRIPT`), acting as the site admin.
- **Arguments**: `--mode=apply` or `--mode=drift`. Anything else is a usage error, exit 2.
- **Never**: reads YAML, reads the repo, writes secrets to disk or a log, installs, upgrades or downgrades plugin code, edits vendored Moodle code, or resets a role.

### `--mode=apply`

1. Prints the target (`$CFG->wwwroot`) and its release.
2. Checks every blocking condition before writing anything:
   - the server's `$CFG->version` is below `moodle.requires`;
   - a declared plugin is missing, its installed `version` differs from its pin, or it has an upgrade pending (`versiondisk ≠ versiondb`);
   - a declared setting is `unknown` (not in the admin tree, including a setting whose plugin is not installed or that an upgrade renamed);
   - a declared capability is unknown.
   Any one of these exits 1 with nothing changed, naming the item and, for a pin mismatch, the pinned and installed releases.
3. Changes only what differs: writes settings through the admin setting, toggles plugins, creates missing roles, sets context levels, and assigns or unassigns capabilities.
4. Refuses to write a setting `forced` in `config.php`, and reports it.
5. Ends with a summary of what changed. A second run against a matching server reports zero changes.

### `--mode=drift`

Compares the live site with the declaration and writes nothing. Reports every difference by kind (see [output.md](output.md)), including undeclared settings changed from Moodle's default and installed non-standard plugins that are not declared.

## Exit codes (both commands)

| Code | Meaning |
|---|---|
| `0` | `apply`: applied or already matching. `drift`: no differences. `validate`: declaration valid. |
| `1` | `apply`: a blocking problem or a failed step. `drift`: differences found. `validate`: declaration invalid. |
| `2` | Usage or configuration error, including a missing required variable and a `MOODLE_URL` that does not match `$CFG->wwwroot`. Nothing is changed. |

## Change to `moodle/local_ltuse/cli/setup_publishing.php`

It no longer defines the `ltcpublisher` role. That role is declared in `moodle/site/roles.yaml`. The script keeps the account, the service authorisation and the token, and fails with `run site_config.py apply first` if the role is missing.
