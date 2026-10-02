# Tasks: Site configuration as code

**Input**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md), [data-model.md](data-model.md), [contracts/](contracts/)

Verification runs on the temporary 5.2.3+ instance (`ssh ltuse`, `https://ltuse.net`) with test accounts only (constitution X). That host carries other people's live sites, so no task there touches anything outside `/home/ltuse`. Evidence kept for the pull request is drift and apply output only. That output holds no secret and no learner data.

## Phase 1: Setup

Files: `specs/001-site-config-as-code/research.md`, `specs/001-site-config-as-code/contracts/declaration.md`

**Wave 1, a single task:**

- [x] **T001** Read the open facts from the instance and record each in research.md: the exact `$CFG->version` stamp for 5.2.3+ (R10); the message-provider default key names for #25, and whether they are in the admin tree (R9, which settles whether `raw: true` is needed); and the keys in `$CFG->config_php_settings` (R6). If `raw: true` is not needed, remove it from contracts/declaration.md in the same task · specs/001-site-config-as-code/research.md

## Phase 2: Foundational

Files: `moodle/local_ltuse/version.php`, `moodle/site/site.yaml`, `moodle/site/roles.yaml`, `moodle/local_ltuse/classes/siteconfig/report.php`, `moodle/local_ltuse/classes/siteconfig/inspector.php`, `scripts/site_config.py`, `moodle/local_ltuse/cli/site_config.php`, `.github/workflows/site-config.yml`

No user-story work starts until this phase is done.

**Wave 1, independent (different files):**

- [x] **T002** [P] Bump `$plugin->version`, and add `$plugin->supported = [502, 502]` (constitution XI) · moodle/local_ltuse/version.php
- [x] **T003** [P] Declare roles. `ltcpublisher` has no archetype and takes the 15 capabilities now hard-coded in `setup_publishing.php`, plus its context levels. `manager` and `editingteacher` keep their archetypes, with `moodle/site:trustcontent` set to `allow` for `manager` and `ltcpublisher` and `inherit` for `editingteacher` (FR-012) · moodle/site/roles.yaml
- [x] **T004** [P] Write the report class. It takes per-item results and emits line output or `--json` in the shape in contracts/output.md. It redacts every declared `env:` setting and every `admin_setting_configpasswordunmask` (or subclass) value as `<secret>`, and maps outcomes to exit codes 0, 1 and 2 (FR-009, FR-010) · moodle/local_ltuse/classes/siteconfig/report.php
- [x] **T005** [P] Write the inspector class. It builds the full admin tree as the site admin (R4) and finds each declared setting by `plugin` and `name`. It reads live plugin state through `core_plugin_manager` (R7) and live system-context role permissions from `role_capabilities` (R8). It computes the expected role set from `get_default_capabilities()` plus overrides, and detects `forced` settings (R6). Each declared item gets a comparison result: ok, changed, missing, unknown, forced, wrong-release or pending-upgrade. It writes nothing · moodle/local_ltuse/classes/siteconfig/inspector.php
- [x] **T006** [P] Write the Python command with the subcommands `validate`, `render`, `apply` and `drift`. `validate` checks every rule in data-model.md, including literal-secret detection (FR-006). `render` prints JSON with `env:` values redacted. `apply` resolves `env:` values from the local environment and skips a missing one with `[fail] … NAME is not set`. The payload carries `MOODLE_URL` as its target. The transport runs `ssh "$MOODLE_SSH" php "$MOODLE_DIR/public/local/ltuse/cli/site_config.php" --mode=…` with JSON on stdin, or runs it locally when `MOODLE_SSH` is unset. It relays output and exit codes. No secret ever goes in argv, on disk or into a log (FR-005, FR-006) · scripts/site_config.py

**⟶ Wait for Wave 1 to finish, then:**

**Wave 2, independent (different files):**

- [x] **T007** [P] Declare `moodle.requires` and `moodle.release` from T001 (FR-002). Declare plugins: `local_ltuse` pinned to T002's version, `mod_scorm`, `mod_h5pactivity`, `filter_displayh5p`, `filter_mediaplugin`, `media_vimeo` and `media_videojs` enabled, and `message_airnotifier` disabled (FR-003, FR-012, FR-016) · moodle/site/site.yaml
- [x] **T008** [P] Write the CLI entry point. It defines `CLI_SCRIPT`, parses `--mode=apply|drift` (anything else exits 2) and reads JSON from stdin. It refuses with exit 2 when the payload's target does not equal `$CFG->wwwroot`, and prints the target and release before anything else (FR-005). It dispatches to `\local_ltuse\siteconfig\applier` or `\local_ltuse\siteconfig\drift`, which US1 and US2 create · moodle/local_ltuse/cli/site_config.php
- [x] **T009** [P] Add a CI workflow that runs `python scripts/site_config.py validate` on changes to `moodle/site/**` and `scripts/site_config.py`, matching the shape of `competency-descriptors.yml` · .github/workflows/site-config.yml

**Checkpoint**: `site_config.py validate` and `render` work offline. The server side can read a payload and announce its target.

## Phase 3: User Story 1, rebuild a server from the repo (P1, the MVP)

Files: `moodle/local_ltuse/classes/siteconfig/applier.php`, `moodle/local_ltuse/cli/setup_publishing.php`

**Goal**: apply brings a server to the declaration, refuses on any blocking problem before writing, and a second run changes nothing.

**Independent Test**: apply the declaration to the instance twice. The second run reports zero changes. A locally edited pin, or a raised `requires`, is refused before any write.

**Wave 1, independent (different files):**

- [x] **T010** [P] [US1] Write the applier. Preflight runs every blocking check in contracts/site-config-cli.md first, and exits 1 with nothing changed when any check fails. It names the pinned and installed releases on a mismatch (FR-002, FR-003). Then it writes only what differs: settings through `write_setting()`, plugins through `enable_plugin()`, and roles through `create_role()`, `set_role_contextlevels()`, `assign_capability(…, true)` or `unassign_capability()`. It refuses `forced` settings and never resets a role. It ends with the change summary (FR-004, FR-010) · moodle/local_ltuse/classes/siteconfig/applier.php
- [x] **T011** [P] [US1] Remove the role definition and the capability grants. The script now fails with `run site_config.py apply first` if `ltcpublisher` is missing, and keeps the account, the service authorisation and the token unchanged (R8) · moodle/local_ltuse/cli/setup_publishing.php

**⟶ Wait for Wave 1 to finish, then:**

- [x] **T012** [US1] Deploy `local_ltuse` to the instance and run `admin/cli/upgrade.php`. Then verify, keeping the output as PR evidence: apply announces the target and applies; a second apply reports zero changes (SC-002); a local, uncommitted pin edit is refused before any write, naming both releases (US1 scenario 3); a local, uncommitted raised `requires` is refused (US1 scenario 4); a wrong `MOODLE_URL` exits 2; and `setup_publishing.php` still issues its token against the declared role. No files

**Checkpoint**: US1 is independently functional. The instance is configured from the repo.

## Phase 4: User Story 2, detect drift without changing anything (P2)

Files: `moodle/local_ltuse/classes/siteconfig/drift.php`, `moodle/site/ignore.yaml`, `moodle/site/settings/server.yaml`

**Goal**: a read-only comparison that reports every difference by kind and exits non-zero on any.

**Independent Test**: change one declared and one undeclared setting by hand. Drift reports both, labelled differently, and changes nothing.

**Wave 1, a single task:**

- [x] **T013** [US2] Write the drift class. It reports the inspector's per-item results. It walks every `admin_setting` for undeclared, non-default values not in `ignore.yaml` (`unmanaged`, R5), lists installed non-standard plugins that are not declared (`extra`), and reports role permission differences per capability. It writes nothing, and it exits 1 on any difference (FR-007, FR-008) · moodle/local_ltuse/classes/siteconfig/drift.php

**⟶ Wait for Wave 1 to finish, then:**

**Wave 2, independent (different files):**

- [x] **T014** [P] [US2] Run drift on the instance. Write every install-time `unmanaged` value into the ignore list, each with its reason (R5). Hand any value that must be declared to T019–T023 for the settings files they own · moodle/site/ignore.yaml
- [x] **T015** [P] [US2] Declare the per-server values T014's drift run surfaces, such as the mail host or `noreplyaddress`, as `env:` references. `debug` is set in `config.php` and is never declared (R6) · moodle/site/settings/server.yaml

**⟶ Wait for Wave 2 to finish, then:**

- [x] **T016** [US2] Verify on the instance: a matching server drifts clean and exits 0 (US2 scenario 3). Then make five hand changes, three declared and two undeclared. Drift reports all five, labels them `changed` and `unmanaged` correctly, and exits 1 (SC-003). Confirm nothing changed on the server, then restore the five values. No files

**Checkpoint**: US2 is independently functional. Drift can run unattended, with no secret.

## Phase 5: User Story 3, change a setting through a reviewed change (P3)

Files: `moodle/site/README.md`

**Wave 1, a single task:**

- [x] **T017** [US3] Write the maintainer's guide: how to add a setting, plugin or role; what each difference kind means; how to declare a secret or per-server value as `env:NAME`; and why a setting is not done until it is declared, which other specs follow (FR-011). Write it for a maintainer who did not write this tool · moodle/site/README.md

**⟶ Wait for Wave 1 to finish, then:**

- [x] **T018** [US3] Verify, keeping the output as PR evidence. Add one harmless setting locally and apply it: only that setting changes (US3 scenario 1). Drift is clean. Remove the setting: drift reports it `unmanaged`. Revert it. `validate` rejects a literal password, and `render` shows `<secret>` for an `env:` setting whose variable is set (US3 scenario 2). No files

**Checkpoint**: US3 is independently functional.

## Phase 6: User Story 4, a working baseline (P4)

Files: `moodle/site/settings/content-embeds.yaml`, `moodle/site/settings/mobile.yaml`, `moodle/site/settings/data-export.yaml`, `moodle/site/settings/messaging.yaml`, `moodle/site/settings/notifications.yaml`

**Wave 1, independent (different files):**

- [x] **T019** [P] [US4] `rows: [3]`, a `purpose`, and a `why` per setting. Set `scorm/scormstandard` to 0 (core default: relaxed 1.2 limits) and `enabletrusttext` to 1 (FR-012) · moodle/site/settings/content-embeds.yaml
- [x] **T020** [P] [US4] `rows: [4]`, a `purpose`, and a `why` per setting. Set `enablewebservices` and `enablemobilewebservice` to 1, `mobilecssurl` to `/local/ltuse/styles.css` under `env:MOODLE_URL`, and `tool_mobile/disabledfeatures` to empty (FR-013) · moodle/site/settings/mobile.yaml
- [x] **T021** [P] [US4] `rows: [16]`, a `purpose`, and a `why` per setting. Set `tool_dataprivacy/contactdataprotectionofficer` to 1, and set `automaticdataexportapproval` and `automaticdatadeletionapproval` to 0. Site admins act as DPO by default, so `dporoles` is not declared (R9) (FR-014) · moodle/site/settings/data-export.yaml
- [x] **T022** [P] [US4] `rows: [19]`, a `purpose`, and a `why` per setting. Set `messaging` to 1 and `messagingallusers` to 0 (FR-015) · moodle/site/settings/messaging.yaml
- [x] **T023** [P] [US4] `rows: [25]`, a `purpose`, and a `why` per setting. Set `defaultpreference_maildigest` to 1. Message-provider defaults stay at core defaults (R9), so every learner can still change their own preferences (FR-016) · moodle/site/settings/notifications.yaml

**⟶ Wait for Wave 1 to finish, then:**

- [x] **T024** [US4] Apply the baseline to the instance, and drift clean. No files

**⟶ Wait for T024, then (verification, independent):**

- [ ] **T025** [P] [US4] #3 with test accounts, in the browser and the Android app. A SCORM 1.2 package, an H5P item and a Vimeo embed all open. An iframe saved by an editing teacher is not rendered, but the same iframe from a manager is. Settle R9's Google Drive question: either a Drive iframe survives trusted text, or Drive stays a link. Record the result as a comment in content-embeds.yaml (US4 scenarios 1 and 2) · moodle/site/settings/content-embeds.yaml
- [x] **T026** [P] [US4] #4 with test accounts. A learner signs in through the Android app, sees callouts styled by the mobile stylesheet, and downloads a course for offline use (US4 scenario 3). No files
- [ ] **T027** [P] [US4] #16 with test accounts. An administrator completes a full data export for one test learner and acts on a deletion request. A manager backs up a course (US4 scenario 4). No files
- [ ] **T028** [P] [US4] #19 with test accounts. 1:1 and group conversations work in the browser and the app, and a learner restricts who may message them (US4 scenario 5). No files
- [ ] **T029** [P] [US4] #25 with a new test account. Forum digest is the default, and the learner can change their preferences. Push stays off (US4 scenario 6). No files

**Checkpoint**: US4 is complete, and SC-005 passes on the instance (FR-017).

## Phase 7: Polish

Files: `moodle/local_ltuse/README.md`, `CLAUDE.md`, `moodle/REQUIREMENTS.md`

**Wave 1, independent (different files):**

- [x] **T030** [P] Replace install steps 1, 2 and 5 with "run `site_config.py apply`". Document `cli/site_config.php`, and list the `role_capabilities` raw read with its reason (constitution XI) · moodle/local_ltuse/README.md
- [x] **T031** [P] Add `site_config.py` to the maintainer-scripts list. Name `moodle/site/` in the Delivery section as where every Moodle setting lives · CLAUDE.md
- [x] **T032** [P] Update the status of rows 3, 4, 16, 19 and 25, citing the verification from T025–T029 (constitution X) · moodle/REQUIREMENTS.md

**⟶ Wait for Wave 1 to finish, then:**

- [ ] **T033** SC-001: on a throwaway 5.2.3+ instance (a local container, or the spec 015 drill server once it exists), install `local_ltuse` and run apply from the repo alone. Drift is clean, with no admin-UI step, in under an hour. If no throwaway instance exists yet, record SC-001 as not yet verified rather than claiming it. No files
- [x] **T034** Validate against the Success Criteria. Run `python scripts/site_config.py validate`, run `php -l` on every new or changed PHP file, and run the existing CI checks (`gen_coverage.py`, `check_competency_descriptors.py`, `check_course_package.py`, `quiz_parse.py --check-all`). For SC-004, search the repo and the kept evidence for secret values and learner data. No files

## Dependencies & Execution Order

- **Setup (T001)** → **Foundational** → **US1** → **US2** → **US3** and **US4** → **Polish**.
- US1 must run before US2, US3 and US4, because only apply puts the declaration on the instance. US2 needs US1's apply to reach a known state. US3 and US4 can run in parallel once US2 is done. US3 needs drift for its verification, and US4 needs drift clean at T024.
- Foundational: Wave 1 (T002–T006) blocks Wave 2 (T007–T009). T007 needs T001 and T002, and T008 needs T004 and T005.
- US1: T010 and T011 block T012.
- US2: T013 blocks T014 and T015, which block T016. T014 may hand values to T019–T023.
- US3: T017, then T018.
- US4: T019–T023 block T024, which blocks T025–T029.
- Polish: T030–T032 block T033, which blocks T034.
