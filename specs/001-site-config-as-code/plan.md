# Implementation Plan: Site configuration as code

**Branch**: `specs/moodle-requirements` | **Date**: 2026-10-01 | **Spec**: [spec.md](spec.md)

## Summary

Every setting, plugin and role the training system depends on is declared in YAML files under `moodle/site/`. One Python command, `scripts/site_config.py`, validates the declaration, renders it to JSON and streams it over `ssh` to a PHP CLI script in `local_ltuse`. That script compares the declaration with the live site through Moodle's own admin-settings, plugin-manager and access APIs. In `apply` mode it refuses on any blocking problem before writing anything, then changes only what differs. In `drift` mode it writes nothing and exits non-zero on any difference. The same declaration carries the baseline for rows #3, #4, #16, #19 and #25.

Two choices here are not obvious from the existing code. **The declaration is YAML read only by Python.** PHP never parses YAML. It receives JSON on stdin, so the server needs no YAML extension and no repo checkout. **Secrets are resolved on the operator's machine** from its environment and travel only inside that stdin stream. They never touch disk, a command line or a log. Drift needs no secret, because a secret setting is checked only for being set.

## Project Structure

```text
moodle/
├── site/                          # the declaration (new)
│   ├── README.md                  # how to add a setting; what each kind means
│   ├── site.yaml                  # minimum Moodle release, plugins and pins
│   ├── ignore.yaml                # undeclared install-time settings drift skips, each with a reason
│   ├── roles.yaml                 # roles: archetype + overrides at system context
│   └── settings/                  # one file per topic, each citing its REQUIREMENTS.md rows
│       ├── content-embeds.yaml    # #3
│       ├── mobile.yaml            # #4
│       ├── data-export.yaml       # #16
│       ├── messaging.yaml         # #19
│       ├── notifications.yaml     # #25
│       └── server.yaml            # per-server values from the environment; added when the first one is needed
├── local_ltuse/
│   ├── cli/site_config.php        # new: reads JSON on stdin; --mode=apply|drift
│   ├── cli/setup_publishing.php   # changed: stops defining the role; requires it to exist
│   ├── version.php                # bumped
│   └── README.md                  # install steps 1, 2 and 5 replaced by "run site_config.py apply"
└── REQUIREMENTS.md                # rows 3, 4, 16, 19, 25 status updated on delivery
scripts/
└── site_config.py                 # new: validate | render | apply | drift
.github/workflows/
└── site-config.yml                # new: runs `site_config.py validate` on moodle/site/** changes
```

**Structure Decision**: The applier lives in `local_ltuse`, because that plugin is already deployed to every target and `setup_publishing.php` already sets the precedent for a CLI in it. The declaration lives in `moodle/site/`, separate from the plugin, because it is data every spec adds to, not plugin code.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. The declaration is authoritative. Drift reads the server and reports. It never writes back to the repo. |
| II. Portability | PASS. This is the principle's own test. The target comes from `MOODLE_URL`, per-server values come from the environment, and nothing names `ltuse.net`. |
| III. Public repo (NON-NEGOTIABLE) | PASS. Secrets enter only by `env:` reference and are resolved in memory. `validate` fails CI on anything that looks like a literal secret. Password-type settings are always redacted in output. No output lists users. |
| IV. Disclosure boundary (NON-NEGOTIABLE) | PASS. Untouched. Removing `moodle/site:trustcontent` from editing teachers narrows what can reach a learner page. |
| V. CBC fidelity | PASS. No competency, level or badge settings. |
| VI. No git, no LMS orientation | PASS. Only the maintainer runs this. Baseline defaults (digests, app access, offline) mean learners have less to set up. |
| VII. One shape, gated stages | PASS. No course content touched. |
| VIII. Language data | PASS. Not applicable to configuration. |
| IX. Flat cost, field-ready | PASS. Core only, no paid plugin. App and offline download stay on. Push (`message_airnotifier`) stays disabled until spec 015 decides the app plan. |
| X. Traceable and verified | PASS. Each settings file cites its rows. Every setting name is confirmed in `MOODLE_502_STABLE` source (research.md) and verified on the 5.2.3+ instance before delivery (FR-017). Scheduled drift is a recurring operation owned by spec 015. This spec does not claim it is covered. |
| XI. Survives an upgrade | PASS. Uses only public APIs: `admin_setting::write_setting()`, `core_plugin_manager`, plugininfo `enable_plugin()`, `create_role()`, `assign_capability()` and `unassign_capability()`. No vendored code edited. One raw read, `role_capabilities` by `roleid` and `contextid`, is a stable core table read by indexed columns. It is still listed in the plugin README. |
| Platform: core first, pin plugins | PASS. Third-party plugins are pinned by version and sha256. The applier verifies them and never installs or upgrades. |

Re-checked after Phase 1 design: no change.

## Cross-spec effects

- **Spec 015**: `provision.sh` installs plugin code at the pinned release, then the runbook runs `site_config.py apply`. Its interim "explicit list of settings" goes away once this spec ships. Secrets this declaration references (SMTP password, from 015's research) live on the operator's machine, not in `/etc/ltct/ops.env`, because apply runs from there.
- **Specs 002–014**: each adds a file under `moodle/site/settings/` and any plugin or role to `site.yaml` or `roles.yaml`. They never use `setup_publishing.php` or the admin UI.
