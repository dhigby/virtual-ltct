# Feature Specification: Site configuration as code

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Make the Moodle training system rebuildable from the repo plus a data restore. Every site setting, plugin (pinned to the release it was verified against) and role the training system depends on is declared under `moodle/`, applied to whichever server `MOODLE_URL` names, and checkable for drift. Ship the core baseline settings for the rows of moodle/REQUIREMENTS.md that are pure configuration: #3 (SCORM and robust embeds), #4 (mobile-friendly), #16 (data export and ownership), #19 (direct messaging) and #25 (manageable notifications)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Rebuild a server from the repo (Priority: P1)

A maintainer stands up a fresh Moodle 5.2.3+ server — for example the dedicated production VPS that replaces the shared build host — and brings it to the same configuration as the current site by applying what the repo declares. Together with a restore of learner data, the result is the training system as it was, with nothing re-clicked from memory.

**Why this priority**: This is the rule INTENT B.5 and constitution II make binding. Every other training-system spec adds configuration; without a way to apply it, each one would be a setting that "exists only because someone clicked it", and the move to production would be a rebuild by hand.

**Independent Test**: On an empty temporary 5.2.3+ instance, apply the declared configuration once, then run the drift check; it reports no differences, and a published course renders and works as it does on the current site.

**Acceptance Scenarios**:

1. **Given** a fresh Moodle 5.2.3+ server and the repo, **When** the maintainer applies the declared configuration against the server named in `MOODLE_URL`, **Then** every declared setting, plugin and role is in place and the run ends with a summary of what it changed.
2. **Given** a server that already matches the declaration, **When** the configuration is applied again, **Then** nothing changes and the summary says so.
3. **Given** a declared plugin whose installed release differs from the pinned one, **When** the configuration is applied, **Then** the run stops before changing anything and names the plugin, the pinned release and the installed release.
4. **Given** a server older than the minimum Moodle release the declaration names, **When** the configuration is applied, **Then** it refuses and says why.

---

### User Story 2 - Detect drift without changing anything (Priority: P2)

A maintainer, or a scheduled check, compares a live server against the declaration and gets a plain list of what differs: declared settings with a different value, missing or wrong-version plugins, roles whose permissions have changed, and settings someone has changed in the admin interface that the repo does not declare at all.

**Why this priority**: Rebuildability silently decays the first time someone fixes a problem by clicking. Drift detection is how "not done until it is in `moodle/`" is enforced rather than hoped for.

**Independent Test**: Change one declared setting and one undeclared setting in the admin interface of a test instance; the check reports both, labelled differently, and changes nothing.

**Acceptance Scenarios**:

1. **Given** a declared setting changed by hand on the server, **When** the drift check runs, **Then** it reports the setting, the declared value and the live value, and exits as a failure.
2. **Given** an undeclared setting changed away from Moodle's default, **When** the drift check runs, **Then** it is reported as unmanaged, so the maintainer can either declare it or revert it.
3. **Given** a server that matches the declaration, **When** the drift check runs, **Then** it reports no differences and exits as a success.
4. **Given** any drift check, **When** it runs, **Then** it changes nothing on the server.

---

### User Story 3 - Change a setting through a reviewed change (Priority: P3)

A maintainer who needs a new setting — for another spec, or to fix something found in a pilot — makes the change in the repo declaration, has it reviewed like any other change, and applies it. The pull request that adds a setting is the record of why it exists.

**Why this priority**: It turns configuration into something reviewable and attributable, but it builds on stories 1 and 2 rather than standing without them.

**Independent Test**: Add one setting to the declaration, apply it to a test instance, confirm the drift check is clean, then remove it and confirm the drift check reports it as unmanaged.

**Acceptance Scenarios**:

1. **Given** a new declared setting, **When** the configuration is applied, **Then** the setting takes its declared value and only that setting changes.
2. **Given** a setting whose value is a secret (such as an outgoing-mail password), **When** it is declared, **Then** the repo names the setting and the environment variable it comes from, and never holds the value.

---

### User Story 4 - A working baseline for content, mobile, data, messaging and notifications (Priority: P4)

A learner on an Android phone opens a published course in the Moodle app, views embedded and packaged content, messages a colleague, and receives notifications they can control; an administrator can export a learner's data and a course's structure on request. All of it comes from the declared baseline, not from hand configuration.

**Why this priority**: These five rows are pure core configuration and are prerequisites for the pilot, but each is small once stories 1–3 exist.

**Independent Test**: Apply the baseline to a test instance and walk the acceptance scenarios below with test accounts only.

**Acceptance Scenarios**:

1. **Given** the baseline, **When** a course contains a SCORM 1.2 package, an H5P item or an embedded video from an approved source, **Then** a learner can open it in a browser and in the Moodle app. (#3)
2. **Given** the baseline, **When** an author who is not trusted to embed arbitrary content saves a page with an arbitrary embed, **Then** it is not rendered to learners; only the publishing role and managers may embed trusted content. (#3)
3. **Given** the baseline, **When** a learner signs in through the Moodle app, **Then** app access is enabled, published pages use the mobile stylesheet, and courses can be downloaded for offline use. (#4)
4. **Given** the baseline, **When** an administrator is asked for one learner's data, **Then** they can produce a full export for that learner and act on a deletion request using core Moodle capability. (#16)
5. **Given** the baseline, **When** two learners on the site message each other, **Then** one-to-one and group conversations work in the browser and the app, and a learner can restrict who may message them. (#19)
6. **Given** the baseline, **When** a learner opens their notification preferences, **Then** sensible site defaults are already set (forum digests rather than one email per post), notifications also reach the learner's Moodle app as push, and the learner can change any of it. (#25)

---

### Edge Cases

- The server named in `MOODLE_URL` is not the one the maintainer intended: every apply and drift run states the server it is acting on before it changes anything.
- A declared plugin is no longer available at its pinned release: the apply fails and names it; it never substitutes a newer release.
- A plugin upgrade is needed: the pin is raised in a reviewed change after the new release is verified on the temporary instance, never by the apply run itself.
- A setting belongs to a plugin that is not installed: reported as an error, not silently skipped.
- A Moodle core upgrade changes a setting's name or default: the drift check reports it rather than hiding it.
- A secret's environment variable is missing: the apply stops for that setting and says which variable is missing; it never writes an empty value over a working one.
- The drift output itself must not leak: it shows setting names and non-secret values only, and never learner data.
- Settings that are truly per-server (site URL, file paths, mail host) are declared as coming from the environment, so one declaration serves the build host, production and any partner-run server.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The repo MUST hold, under `moodle/`, a complete declaration of the site settings, plugins, plugin releases, roles and role permissions the training system depends on.
- **FR-002**: The declaration MUST name the minimum Moodle release it was verified against (5.2.3+), and applying it to an older server MUST be refused.
- **FR-003**: Each declared plugin MUST be pinned to the release it was verified against; applying MUST refuse a mismatch rather than upgrade or downgrade.
- **FR-004**: Applying the declaration MUST be repeatable: a second run against a matching server changes nothing.
- **FR-005**: Applying MUST target only the server named in the environment (`MOODLE_URL`), announce it before changing anything, and never contain a hard-coded host.
- **FR-006**: Secret values MUST come from the environment; the declaration names the setting and its variable, never the value (constitution III).
- **FR-007**: A drift check MUST compare a live server with the declaration, change nothing, and report: declared settings that differ; missing, extra or wrong-release plugins; role permission differences; and undeclared settings changed from Moodle's default.
- **FR-008**: The drift check MUST return a pass/fail result usable by an unattended schedule.
- **FR-009**: Neither apply nor drift output MUST include learner data or secret values.
- **FR-010**: Each apply MUST end with a human-readable summary of what changed, suitable for a pull request or operations log.
- **FR-011**: A setting is not "done" until it is in the declaration; other specs MUST add their settings here rather than configure by hand.
- **FR-012**: The baseline MUST enable core SCORM delivery (1.2 as the supported standard), H5P, and embedded content from a declared list of trusted sources, and MUST restrict arbitrary embedding to managers and the publishing role. (#3)
- **FR-013**: The baseline MUST enable Moodle app access and offline course download, and MUST set the mobile stylesheet that published pages rely on. (#4)
- **FR-014**: The baseline MUST enable core per-learner data export and data-deletion requests, and course backup, so that a learner's data and a course's structure can be exported on request. (#16)
- **FR-015**: The baseline MUST enable one-to-one and group messaging, in the browser and the app, with learners able to restrict who may contact them. (#19)
- **FR-016**: The baseline MUST set site-wide notification defaults that avoid email floods (forum digests on) and leave every learner able to change their own preferences. Mobile push through the Moodle app MUST be on, as a channel each learner can turn off per notification. (#25)
- **FR-017**: Each baseline setting MUST be verified on the temporary 5.2.3+ instance before this spec is marked delivered.

### Key Entities

- **Configuration declaration**: the set of settings, plugins with pinned releases, roles and permissions, and minimum Moodle release, held in the repo; the single statement of what a correctly configured server looks like.
- **Server target**: the Moodle site named by the environment that a run acts on; never stored in the repo.
- **Drift report**: a read-only comparison of one server against the declaration, listing each difference by kind (changed, missing, extra, unmanaged).
- **Secret reference**: a declared setting whose value is supplied by a named environment variable at apply time.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A fresh 5.2.3+ server reaches a clean drift check from the repo alone (before any data restore) in under one hour of maintainer time, with no step performed in the admin interface.
- **SC-002**: Applying the declaration twice in a row reports zero changes on the second run.
- **SC-003**: In a test where five settings are changed by hand (three declared, two undeclared), the drift check reports all five and misclassifies none.
- **SC-004**: A search of the repo finds no secret values and no learner data in the declaration, apply output or drift output kept as evidence.
- **SC-005**: Every baseline acceptance scenario for rows #3, #4, #16, #19 and #25 passes on the temporary instance using test accounts only.

## Assumptions

- The temporary `ltuse.net` instance on the shared host is used to build and verify against; production is a dedicated VPS still to be chosen (spec 015). The same declaration serves both.
- A "data restore" means restoring the database and uploaded files from a backup; producing and storing that backup is operations work owned by spec 015, not by this spec. This spec ensures the configuration half needs no backup at all.
- Undeclared settings left at Moodle's default are treated as intentional; only non-default undeclared settings are flagged.
- SCORM 2004 packages may run, but sequencing is only partly supported in core, so no course is designed to rely on it; SCORM 1.2 is the supported standard.
- Trusted embed sources start as the video hosts the curriculum already links (Vimeo, Google Drive); adding one is a reviewed change.
- Push notifications run through Moodle HQ's Premium app plan, which allows unlimited devices at a flat rate (ltuse.net since 2026-10-02). Email stays on beside it. Spec 015 confirms the plan for production.
- The theme, dashboard and course-format choices are declared through this mechanism but specified by spec 007.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 3 | SCORM and/or robust embeds | Must | Core SCORM 1.2, H5P and trusted-source embeds enabled; arbitrary embedding restricted |
| 4 | Mobile-friendly | Must | App access, offline download and the mobile stylesheet, declared and verified |
| 16 | Data export / ownership | Must | Per-learner export and deletion requests, course backup enabled; configuration needs no backup to rebuild (report exports are spec 004; backup operations are spec 015) |
| 19 | Direct messaging | Pref | One-to-one and group messaging, browser and app, with learner contact controls |
| 25 | Manageable notifications | Pref | Site notification defaults and per-learner control; push on through the app |

Supports every other row: it is the mechanism by which their configuration is applied (constitution II).

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: the repo declaration is authoritative; the drift check reads the server but never writes back to the repo.
- **II. Portability**: this spec *is* principle II's test — a new server rebuilt from the repo plus a data restore. Learner-data export is enabled (FR-014).
- **III. Public repo, private people**: secrets only by environment-variable reference; no learner data in declaration or output (FR-006, FR-009).
- **IV. Disclosure boundary**: untouched; restricting arbitrary embeds reduces the ways content reaches learner pages unreviewed.
- **V. CBC fidelity**: no levels, badges or competency settings introduced here.
- **VI. No git, no LMS orientation**: run by the maintainer, not contributors or learners; baseline defaults (digests, app access) reduce what a learner must learn.
- **IX. Flat cost, field-ready**: no paid plugin or service; app and offline enabled; push uses the Premium app plan, which is flat-rate (unlimited devices), so it stays within IX.
- **X. Traceable and verified**: cites rows #3, #4, #16, #19, #25; every setting verified on the temporary 5.2.3+ instance (FR-017). Scheduled drift checks are a recurring operation: who runs and answers them is the undecided Moodle operator, so this spec does not claim that operation is covered.
- **Platform & Delivery**: core settings only; plugins pinned (FR-003); one declaration for every partner; the server comes from `MOODLE_URL`, never a hard-coded host.

## Dependencies

- None upstream. Every other training-system spec (002–014) depends on this one to apply its configuration.
- 015-production-hosting-ops: consumes this spec to build the production VPS and owns backups, scheduling of drift checks and the app-plan decision.
