# Research: Site configuration as code

Every Moodle API below was looked up in Context7 (`/websites/moodledev_io_5_2_apis`) and then confirmed in upstream source on `MOODLE_502_STABLE` on 2026-10-01. The docs covered almost none of the signatures, so the source is what counts. On that branch the code is under `public/`, and the CLI scripts are at the root `admin/cli/`.

## R1. Where the applier runs

- **Decision**: a PHP CLI script, `local_ltuse/cli/site_config.php`, runs on the server and reads the declaration as JSON on stdin. `scripts/site_config.py` on the operator's machine starts it over `ssh "$MOODLE_SSH"`. When `MOODLE_SSH` is unset, it runs locally, which is what an on-server schedule uses. The script path is `$MOODLE_DIR/public/local/ltuse/cli/site_config.php`, using the variable name spec 015 already defines.
- **Rationale**: no core web service writes site config. Adding one would give a network token the power to rewrite the whole site. Moodle's own admin CLIs run on the server. `local_ltuse` is already deployed everywhere, and `setup_publishing.php` already sets the pattern for a CLI in it.
- **Alternatives considered**: core `admin/cli/cfg.php` (sets one value per call, with no validation, callbacks, roles, plugins or drift); a web-service function in `local_ltuse` (a remote config writer behind one token); a standalone script outside any plugin (one more thing to deploy, with no benefit).

## R2. Declaration format

- **Decision**: YAML under `moodle/site/`, parsed only by Python. PHP receives JSON.
- **Rationale**: the repo's data is already YAML (`competencies.yaml`, frontmatter), CI already has `pyyaml`, and YAML allows the comments reviewers need. PHP has no YAML parser without an extension, and the shared host should not need one.
- **Alternatives considered**: JSON files (no comments); INI read by `parse_ini_file` (type coercion quirks, and roles and plugins fit badly); PHP arrays (executable code passed off as data).

## R3. Secrets

- **Decision**: a value written `env:NAME` is resolved by `site_config.py` from the operator's environment and sent only inside the stdin stream. If the variable is missing, that setting is skipped with `[fail] … NAME is not set`, nothing is written for it, and the run exits 1. Drift compares a secret setting only as set or empty. Output always shows `<secret>` for declared secrets and for any setting whose admin class is `admin_setting_configpasswordunmask` or a subclass.
- **Rationale**: this matches the publisher, whose `MOODLE_TOKEN` lives on the operator's machine (015 `ops-env.md`). A secret never touches disk, `argv` or a log. Drift needs no secret, so it can run unattended. Redacting by admin class fails closed for password settings nobody declared.
- **Alternatives considered**: resolving secrets on the server from `/etc/ltct/ops.env` (root-owned, so a Moodle CLI running as the web user cannot read it, and the shared host has no such file); hashing secrets for drift (a hash of a short password can be brute-forced).

## R4. Reading and writing settings

- **Decision**: build the full admin tree with `admin_get_root(true, true)` after `\core\session\manager::set_user(get_admin())`. Find each declared setting by `plugin` and `name` across the tree. Read it with `get_setting()`. Write it with `write_setting($data)`, which returns `''` on success or an error string. Writing through the admin setting gets Moodle's own validation, `updatedcallback` (theme cache resets and the like) and the `config_log` entry. A declared name not found in the tree is an `unknown` error. That covers a setting whose plugin is missing and a setting renamed by an upgrade.
- **Confirmed**: `admin_get_root($reload=false, $requirefulltree=true)` at `lib/adminlib.php:8905`; `admin_setting::get_setting()`, `get_defaultsetting()` (honours `custom_defaults`) and `write_setting($data)` at 2032–2057; `admin_apply_default_settings()` at 8954, which is the same tree walk core uses at install.
- **Alternatives considered**: `set_config()` (moodlelib 934). It skips validation and callbacks, so it is used only for a key core writes outside the admin tree, and only if R9 finds one we need.

## R5. Undeclared settings changed from default

- **Decision**: drift walks every `admin_setting` in the tree. A setting that is not declared, whose `get_setting()` differs from `get_defaultsetting()`, and that is not on the `ignore` list in `ignore.yaml` is reported `unmanaged`. Each `ignore` entry carries a reason. Install-time values such as `supportemail`, `timezone` and `siteidentifier` are expected there.
- **Rationale**: it is the same comparison core uses to find new settings, and it needs no stored snapshot.
- **Open, verify on instance**: the first drift run on `ltuse.net` produces the real list of install-time values. Each one is then declared, sent to `server.yaml` as `env:`, or ignored with a reason. That run is the first implementation task.

## R6. Settings forced in `config.php`

- **Decision**: a key in `$CFG->config_php_settings`, or in `$CFG->forced_plugin_settings[plugin]`, is reported `forced`. `apply` refuses to write it, because a database write under a `config.php` override does nothing.
- **Rationale**: a silent no-op write would make the summary lie.
- **Verified on instance (2026-10-01)**: `config.php` on `ltuse.net` sets `debug` and `debugdisplay`, among the usual connection and path keys. `forced_plugin_settings` is empty. So `debug` is not declared here. Like `wwwroot`, it belongs to whoever writes `config.php` (spec 015's provisioning and restore). Turning DEVELOPER debug off before learners arrive is a go-live check for spec 015.

## R7. Plugins

- **Decision**: each `site.yaml` plugin entry has a `component`, optional `enabled`, and, for a non-core plugin, `version` (the `$plugin->version` stamp) plus `source` (URL and `sha256`) for provisioning. Read with `core_plugin_manager::instance()->get_plugin_info($component)`, using `versiondb`, `versiondisk`, `release` and `is_standard()`. Toggle with the plugininfo class's static `enable_plugin(string $name, int $enabled): bool` and read with `get_enabled_plugin()`. A pin mismatch, a missing plugin, or `versiondisk ≠ versiondb` (an upgrade is pending) blocks apply before any write. Drift reports an installed non-standard plugin that is not declared as `extra`.
- **Confirmed**: `core_plugin_manager::instance()` (`lib/classes/plugin_manager.php:122`), `get_plugin_info()` (671), `get_plugins_of_type()` (407); `plugininfo\base::enable_plugin()` (120), `get_enabled_plugin()`, `is_enabled()` (513). Filters accept states beyond 0/1 through `enable_plugin`. `filter_set_global_state()` (`filterlib.php:88`) is not needed.
- **Rationale**: the applier never installs code. Copying plugin code and running `admin/cli/upgrade.php` is provisioning (spec 015), so an apply run can never upgrade or downgrade anything (FR-003). `local_ltuse` is our own plugin, and its pin is the `version.php` stamp in this repo.

## R8. Roles

- **Decision**: a declared role has a `shortname`, plus `name`, `archetype` and `contextlevels` when apply may create it, and `capabilities`: a map of capability to `allow`, `prevent`, `prohibit` or `inherit`, at system context. Its expected permissions are `get_default_capabilities(archetype)` with the declared map laid over them. A role with no archetype starts empty. Drift compares live system-context permissions with that expected set. Apply calls `create_role()` if the role is missing, then `set_role_contextlevels()`, then `assign_capability(..., $overwrite=true)` or, for `inherit`, `unassign_capability()`. It never resets a role.
- **Confirmed**: `create_role($name, $shortname, $description, $archetype='')` at `accesslib.php:1303`; `assign_capability()` 1411; `unassign_capability()` 1486; `get_default_capabilities($archetype)` 2144; `get_capability_info()` 2607, used to reject an unknown capability; `get_role_contextlevels()` and `set_role_contextlevels()` 3562 and 3602.
- **Raw read**: the live system-context permissions come from `role_capabilities` by `roleid` and `contextid`. `role_context_capabilities()` (2529) merges parent contexts, which is not what drift needs. This is a stable core table read by indexed columns, and it is listed in the plugin README anyway.
- **Consequence**: the `ltcpublisher` role moves from `setup_publishing.php` into `roles.yaml`. That script keeps the account, the service authorisation and the token, and now fails with "run site_config.py apply first" if the role is missing. Two definitions of one role would drift.

## R9. Baseline settings

Every name below appears in a `MOODLE_502_STABLE` settings file. "Verify" means the value and its effect are confirmed on the 5.2.3+ instance before the baseline depends on it (constitution X, FR-017).

| Row | Setting or plugin | Value | Source file | Verify |
|---|---|---|---|---|
| #3 | `mod_scorm` enabled; `scorm/scormstandard` | enabled; 0 (core default: relaxed limits, so more 1.2 packages run) | `mod/scorm/settings.php` | SCORM 1.2 package plays in browser and app |
| #3 | `mod_h5pactivity`, `filter_displayh5p` | enabled | plugins | H5P item plays in browser and app |
| #3 | `filter_mediaplugin`, `media_vimeo`, `media_videojs` | enabled | plugins | a Vimeo link embeds |
| #3 | `enabletrusttext` | 1 | `admin/settings/security.php` | arbitrary iframe is stripped for a non-trusted author |
| #3 | role overrides `moodle/site:trustcontent` | `editingteacher: inherit`; `manager` and `ltcpublisher: allow` | `roles.yaml` | only those roles keep an iframe |
| #3 | Google Drive embed | open | n/a | no core media player handles Drive. Verify whether a Drive preview iframe survives trusted text. If it does not, Drive stays a link. |
| #4 | `enablewebservices`, `enablemobilewebservice` | 1, 1 | `admin/settings/plugins.php`, `admin/tool/mobile/settings.php` | app signs in |
| #4 | `mobilecssurl` | `/local/ltuse/styles.css` from `MOODLE_URL` | `admin/tool/mobile/settings.php` | callouts render in the app |
| #4 | `tool_mobile/disabledfeatures` | empty, so `NoDelegate_CoreCourseDownload`, `NoDelegate_CoreOffline` and `NoDelegate_H5POffline` stay on | `tool/mobile/classes/api.php:582–628` | course downloads offline |
| #16 | `tool_dataprivacy/contactdataprotectionofficer` | 1 | `admin/tool/dataprivacy/settings.php` | learner can request export or deletion |
| #16 | `tool_dataprivacy/automaticdataexportapproval`, `automaticdatadeletionapproval` | 0, 0 (a manager approves) | same | admin completes an export for a test account |
| #16 | `tool_dataprivacy/dporoles` | not declared | same | Verified 2026-10-01: the setting enters the admin tree only once some role holds `tool/dataprivacy:managedatarequests`, so declaring it would block the first apply. `api::is_site_dpo()` (`tool/dataprivacy/classes/api.php:220`) always treats site admins as DPO, which is enough for FR-014. Making managers DPO later is a reviewed change, in two applies. |
| #16 | course backup | core default; `backup/backup_auto_active` left to spec 015 | `admin/settings/courses.php` | manager backs up a course |
| #19 | `messaging`, `messagingallusers` | 1, 0 (contacts and course members only) | `admin/settings/messaging.php` | 1:1 and group messages work in browser and app; a learner restricts contact |
| #25 | `defaultpreference_maildigest` | 1 (complete digest) | `admin/settings/users.php` | a new account defaults to digest |
| #25 | `message_airnotifier` | enabled | plugins | push on since 2026-10-02: the Premium app plan is flat-rate with unlimited devices. Core message-provider defaults already route forum posts and contact requests to it |
| #25 | message-provider defaults | core defaults, undeclared | n/a | Verified 2026-10-01: they are stored as `message/message_provider_<component>_<name>_enabled`, outside the admin tree. Forum post emails already follow each learner's `maildigest` preference, so `defaultpreference_maildigest` is enough for #25. No `raw` escape is needed. |

## R10. Minimum release

- **Decision**: `site.yaml` holds `moodle.requires`, the `$CFG->version` stamp of the release verified (5.2.3+). Apply refuses below it. `moodle.release` is the human name, printed in the refusal.
- **Verified on instance (2026-10-01)**: `ltuse.net` runs `5.2.3+ (Build: 20260928)`, with `$CFG->version` = `2026042003.03`. That is `moodle.requires`.

## R11. Output and exit codes

- **Decision**: follow spec 015's `ops-scripts.md`. The first line names the target and its release. Then one line per item, `[ok]`, `[changed]`, `[fail]` or `[skip]`. Then a summary line. With `--json`, the same report as JSON, for evidence. Exit 0 means clean or applied, 1 means drift found or a step failed, and 2 means usage or configuration error, including a `MOODLE_URL` that does not match `$CFG->wwwroot`.
- **Rationale**: one convention across every ops tool, which a timer or a CI job can act on.
