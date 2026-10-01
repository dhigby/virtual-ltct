# `moodle/site/`: what a correctly configured server looks like

Every Moodle setting, plugin and role the training system depends on is declared here. A
new server is built from this folder plus a data restore, with nothing clicked into the
admin interface. **A setting is not done until it is in this folder.** That holds for every
training-system spec. Each one adds its settings here rather than configuring by hand, and
the pull request that adds a setting is the record of why it exists.

| File | Holds |
|---|---|
| `site.yaml` | The minimum Moodle release, and each plugin with its pinned version and enabled state. |
| `roles.yaml` | Roles and their system-context permissions. |
| `settings/*.yaml` | Settings, one file per topic. Each cites the rows of [`../REQUIREMENTS.md`](../REQUIREMENTS.md) it serves. |
| `ignore.yaml` | Undeclared settings that are allowed to differ from Moodle's default, each with its reason. |

The shapes are specified in
[`specs/001-site-config-as-code/contracts/declaration.md`](../../specs/001-site-config-as-code/contracts/declaration.md).

## The three commands

You need `MOODLE_URL` (the site), `MOODLE_DIR` (the Moodle code directory on the server) and,
from your own machine, `MOODLE_SSH` (an `ssh` destination such as `ltuse`).

```powershell
python scripts/site_config.py validate   # offline; CI runs this on every change here
python scripts/site_config.py drift      # read-only: how does the server differ? exit 1 if it does
python scripts/site_config.py apply      # make the server match; a second run changes nothing
```

Every run names the server it is acting on first. A `MOODLE_URL` that does not match that
server is refused before anything happens. Add `--json` to keep the report as evidence for a
pull request. The output never contains a secret or any learner data.

## Changing something

1. Edit the right file, and give the entry a `why`: what breaks, or which requirement row
   needs it.
2. Run `validate`, then `drift` to see the difference, then `apply`.
3. Run `drift` again. It should say `No differences.`
4. Open a pull request with the change. Attach the apply output if a reviewer needs it.

**A setting** goes in the `settings/` file for its topic. Name it as Moodle's admin tree
does: `enabletrusttext` for a core setting, `scorm/scormstandard` for a plugin's. Write values
as strings or numbers. Use `1` and `0`, never `true`, `on` or `yes`: YAML quietly turns `off`
into `false`, and the review would not show it. Find a setting's name in Moodle's
`settings.php` for that component, on the branch the server runs.

**A plugin** goes in `site.yaml`. A core plugin takes only `enabled`. A third-party plugin is
pinned with `version` (its `$plugin->version`) and a `source` (`url` plus a quoted `sha256`).
Apply never installs, upgrades or downgrades plugin code: it refuses a mismatch, naming both
releases. To move a pin, verify the new release on the test instance, install it there, and
raise the pin in the same pull request.

**A role** goes in `roles.yaml`. A role with an `archetype`, such as `manager` or
`editingteacher`, is Moodle's own: only the capabilities you list are managed, and its other
permissions are left as the install made them. A role with `archetype: ""` is ours: every
capability it holds is managed, so one granted by hand shows up as drift.

## Secrets and per-server values

A value written `env:NAME` comes from the environment variable `NAME` on the machine you
run `apply` from. The repo never holds the value, because the repo is public. Add
`secret: true` to a password, key or token:

```yaml
  - name: smtppass
    value: env:MOODLE_SMTP_PASS
    secret: true
    why: "Outgoing mail (spec 015)."
```

The value travels only inside the `ssh` stream. It is never written to disk, put on a
command line or logged. If the variable is not set, apply skips that one setting, writes
nothing for it, and exits 1. Drift needs no secret: it checks a secret only for being set.

`env:MOODLE_URL/local/ltuse/styles.css` builds a URL from the target site. A plain
`https://…` value is refused, because a hard-coded host would tie the declaration to one
server.

Settings that `config.php` sets, such as `wwwroot` and `debug`, are not declared here.
Drift skips them, and apply refuses to write them. They belong to whoever writes
`config.php` when the server is provisioned (spec 015).

## Reading a drift report

| Kind | Means | What to do |
|---|---|---|
| `changed` | A declared value differs on the server. | Run `apply`, or change the declaration if the server is right. |
| `missing` | A declared plugin or role is not on the server. | Install the plugin at its pin. Apply creates a missing role. |
| `wrong-release` | A plugin is not at its pinned version. | Install the pinned release, or raise the pin in a reviewed change. |
| `pending-upgrade` | Plugin code is newer than the database. | Run `admin/cli/upgrade.php`. |
| `unknown` | A declared setting or capability does not exist on the server. | Its plugin is missing, or an upgrade renamed it. Fix the declaration. |
| `forced` | A declared setting is set in `config.php`. | Remove it from the declaration. |
| `extra` | A plugin is installed but not declared. | Declare it with a pin, or uninstall it. |
| `unmanaged` | An undeclared setting differs from Moodle's default. | Someone changed it by hand. Declare it, revert it, or add it to `ignore.yaml` with a reason. |
