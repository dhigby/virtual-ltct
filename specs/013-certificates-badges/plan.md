# Implementation Plan: Completion badges and certificates

**Branch**: `013-certificates-badges`, stacked on `004-progress-reporting` | **Date**: 2026-10-02 | **Spec**: [spec.md](spec.md)

## Summary

**The badge** is core Open Badges: one **course badge** per delivered course, awarded by core the moment the learner's course completion is recorded. That record is spec 004's completion, which a republish never wipes. Core has no web service that creates badges and no identity field on them. So `local_ltuse` gains one function, `local_ltuse_set_course_recognition`, which creates and rewords the badge through core's `badge` and `award_criteria` classes, and a small table that maps each course to its badge (R1).

- A badge is created **inactive**, and is activated only on a **stage-8** publish, as `course_stage.py` reports it. That is how a pilot issues nothing (R4).
- Once active, a badge is reworded in place and **never** deactivated. Deactivating it would break verification for everyone who already holds it (R5).
- The award message, the visibility on the profile and the app view are all core (R5, R10, R11).

**The certificate** is `mod_customcert` 5.2.9, the only certificate plugin with a branch built for 5.2 (R6). It prints the learner's **current** name and the **course completion** date, regenerated on every download, with a verification code that a third party can check without logging in (R9).

- The design is one `customcert` site template, declared in `moodle/site/certificate/template.yaml`. `apply` builds it and copies it into each course's activity (R7).
- The activity is created only on a delivery publish, and unlocks on course completion through **`availability_coursecompleted`**. Core has no condition for course completion, and an AND of lesson conditions would re-lock a learner who completed the course before a lesson was added (R8). This is a second plugin, accepted on 2026-10-02 (decision 1).

**The wording rule**: "training completed", never "certified", and never a CBC level held. It lives in one module, `scripts/cbc_wording.py`, which also takes over spec 004's report rule. `validate` runs it on the declarations, `check_moodle_payload.py` on each course's rendered badge text, and the plugin re-checks it against the same patterns before writing (R15).

Per-course text is filled in on the server from 004's locked course fields, so `apply` can reword every badge without a republish (R16).

## Technical Context

**Language/Version**: Python 3.12 (`scripts/`; CI uses 3.12), PHP 8.3 for `local_ltuse` on Moodle 5.2.3+.

**Primary Dependencies**:
- Moodle core: `core_badges`, `core_completion`, `core_availability`, `core_customfield` (read only).
- `mod_customcert` 5.2.9 (`2026042014`) and `availability_coursecompleted` v5.5.3 (`2026070100`), both free and pinned in `site.yaml`.
- Our own `local_ltuse`, and PyYAML.

**Storage**: Moodle's database. That holds badges, awards, certificate issues, and one new `local_ltuse` table, `local_ltuse_course_badge`, which holds no user data. The badge template is stored in `local_ltuse` config and file area. The repo holds only declarations and the two images.

**Testing**:
- `pytest` for `cbc_wording.py`, `site_config.py`'s new validation and rendering, `moodle_payload.py`'s `recognition` block and `check_moodle_payload.py`'s assertions, all on synthetic inputs.
- A PHP harness, `tests/recognition_harness.php`, for the pure pieces: text rendering from the template and the course fields, the deny-list check, and the availability JSON.
- PHPUnit on synthetic data in `moodle/local_ltuse/tests/` for the badge service. It runs wherever 004's T003 decides. Instance checks V1–V14 are in [quickstart.md](quickstart.md).

**Target Platform**: The self-hosted Moodle 5.2.3+ build host and, later, production (015), plus the Moodle Android app.

**Project Type**: The training system: the publisher, its Moodle plugin, and declarative site configuration.

**Performance Goals**: A badge within 1 hour of completion (SC-001); in practice within minutes, because the award is event-driven (R3). A certificate PDF in under 30 s at 256 kbit/s (SC-005).

**Constraints**:
- No "certified" and no CBC level awarded (FR-004, FR-005).
- No learner data in git (FR-008).
- Never deactivate a badge, and never delete a certificate activity (R5, R14).
- Only upgrade-safe extension points (Principle XI).
- No hard-coded host: `issuerurl` comes from wwwroot, and the contact from `env:`.

**Scale/Scope**: About ten delivered courses, each with one badge and one certificate activity. One badge template and one certificate template.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. The wording, the design, the criteria and the certificate layout are in `moodle/site/`. Per-course text is rendered from 004's course fields, which come from frontmatter. Whether a course is delivered comes from `course_stage.py`, which stays the only stage detector (R4). Nothing is read back from Moodle. |
| II. Portability | PASS. The payload says only `delivery` and gives the certificate's idnumber. It knows nothing of badges or `customcert` (contracts/publish.md). Badges are identified by our map table, and the certificate by its cm idnumber, never by a database id (R1, R7). `apply` re-renders everything on a rebuilt server (SC-004). Learners can export their badges and certificates (R13). |
| III. Public repo (NON-NEGOTIABLE) | PASS. Declarations and two images only. The issuer contact comes from `env:MOODLE_BADGE_CONTACT`. `apply`, `drift` and the publisher report badges and activities by course idnumber, never awards, names or codes. Quickstart evidence stays outside the repo. |
| IV. Disclosure boundary (NON-NEGOTIABLE) | PASS. Badges and certificates carry no quiz content. The certificate activity is a module with no page HTML from the repo. The payload gates are unchanged. |
| V. CBC fidelity | PASS. This is the heart of the spec. `cbc_wording.check_recognition()` refuses "certified" and its relatives, retired level names, and any level as held. It runs before apply, before publish and in the plugin (FR-006, R15). A target level appears only as "designed to support progress towards `<CBC label>`" (FR-005). The badges use no competency criterion and no `tool_lp` (R1). |
| VI. No LMS orientation | PASS with a gate. The badge arrives automatically with a notification. The certificate is an activity in the course, labelled with what it is and when it unlocks. SC-003 needs real partner learners (quickstart). |
| VII. One shape | PASS. One badge template and one certificate template for every course and every partner (FR-012). No per-course override. |
| VIII. Language data | PASS. Not applicable. The certificate font covers names in any script, so no script is assumed (R11). |
| IX. Flat cost, field-ready | PASS. Core badges and two free plugins. The badge is visible in the app through services already in the mobile service, and the certificate downloads in the app (R11). The PDF is checked at 256 kbit/s. |
| X. Traceable and verified | PASS with gates. The plan cites row #23, and the delivering PR updates it. Each plugin pin, `replace()` (R7), the suspended-pilot rule (R4), verification (R9) and visibility (R10) are verify tasks that block their stories. No recurring operations are added: badges ride the cron that 015 already monitors. |
| XI. Survives an upgrade | PASS with a listed risk. Badges go through core's `badge`, `award_criteria` and `badges_process_badge_image()`. The certificate activity goes through `add_moduleinfo()`. Two third-party plugins are pinned. Our one dependency on another plugin's internal classes, `mod_customcert`'s `template_load_service` and its element classes, is listed in the `local_ltuse` README, and V6 is re-run on every re-pin (R7). The new table is our own. `local_ltuse` keeps `requires` and `supported` at 5.2. |
| Platform: core first | PASS, with one exception justified below. Badges are core. The certificate is a maintained free plugin, because core has none. The course-completion gate is a second free plugin (Complexity Tracking). Our own code is only what core gives no web service for (R1). |

Re-checked after Phase 1 design: no change.

## Project Structure

### Documentation (this feature)

```text
specs/013-certificates-badges/
├── plan.md              # This file
├── research.md          # R1–R16
├── data-model.md        # The templates, the badge and certificate records, the map table, the payload block
├── quickstart.md        # Instance checks V1–V14
├── contracts/
│   ├── declaration.md   # badges.yaml, certificate/, settings/badges.yaml, site/roles/ignore changes
│   └── publish.md       # recognition payload, set_course_recognition, call sequence
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
moodle/
├── site/
│   ├── badges.yaml                     # new: the badge template (#23)
│   ├── badges/completion.png           # new: the badge design (from the maintainer)
│   ├── certificate/template.yaml       # new: the customcert site template (#23)
│   ├── certificate/logo.png            # new: the issuing programme's logo (from the maintainer)
│   ├── settings/badges.yaml            # new: badge and customcert settings (R12)
│   ├── site.yaml                       # changed: + mod_customcert, + availability_coursecompleted, local_ltuse re-pinned
│   ├── roles.yaml                      # changed: user viewotherbadges inherit; ltcpublisher badge and customcert caps
│   ├── ignore.yaml                     # changed: issuer entries out, badges_badgesalt in
│   └── README.md                       # changed: badges and certificate; retire by hiding, never delete
├── local_ltuse/
│   ├── classes/recognition/renderer.php     # new: template + course fields -> badge text (pure, harness-tested)
│   ├── classes/recognition/wording.php      # new: applies the deny patterns apply stored (pure)
│   ├── classes/recognition/badges.php       # new: create, reword, activate a course badge (R1, R4, R5)
│   ├── classes/recognition/certificate.php  # new: create the activity, availability, copy the template (R7, R8)
│   ├── classes/external/set_course_recognition.php  # new (contracts/publish.md)
│   ├── classes/external/set_course_completion.php   # changed: leaves out ltct:<slug>:certificate
│   ├── classes/siteconfig/badgetemplate.php         # new: store the template, re-render mapped badges
│   ├── classes/siteconfig/certtemplate.php          # new: build the site template, copy it into activities
│   ├── classes/siteconfig/{inspector,applier,drift}.php  # changed: hand them their arrays
│   ├── db/install.xml, db/upgrade.php  # changed: local_ltuse_course_badge
│   ├── db/services.php                 # changed: + local_ltuse_set_course_recognition
│   ├── tests/recognition_test.php      # new: PHPUnit, synthetic data
│   ├── version.php                     # bumped
│   └── README.md                       # changed: calls into mod_customcert internals, and why (Principle XI)
└── REQUIREMENTS.md                     # row 23 updated on delivery
scripts/
├── cbc_wording.py                      # new: 004's FR-010 rule (moved, unchanged) + 013's recognition rule
├── site_config.py                      # changed: imports cbc_wording; validates and renders badges.yaml and certificate/
├── moodle_payload.py                   # changed: recognition block from course_stage
├── check_moodle_payload.py             # changed: start date, rendered badge title, certificate idnumber
└── publish_moodle.py                   # changed: set_course_recognition call; certificate idnumber kept out of stale
tests/
├── test_cbc_wording.py                 # new: both rules; "certificate" allowed, "certified" refused
├── test_site_config.py                 # changed: badges.yaml and certificate validation
├── test_payload_recognition.py         # new: delivery from course_stage, certificate block, assertions
├── test_publish_moodle.py              # changed: call order, warnings exit 1, dry run, stale exclusion
└── recognition_harness.php             # new
process/stages/08-publish.md            # changed: suspend the pilot learner's enrolment before publishing (R4); retire by hiding
.github/workflows/site-config.yml       # changed: runs recognition_harness.php
```

**Structure Decision**: This follows 004's layout. Site-wide declarations are applied by `site_config.py` through new `siteconfig` classes. Per-course instances are made by the publisher through one new web service, because a course exists only once it has been published. Both sides call the same `recognition` classes, so a badge is rendered one way whether apply or publish touches it. The pure pieces (rendering, wording, availability) have no Moodle calls, and a harness tests them in CI.

## Decisions on the plan's limits

These are for the maintainer (Doug). `/speckit-tasks` may generate tasks as drafted, but a task that depends on a pending decision is not closed until it is confirmed.

| # | Limit or choice | Status |
|---|---|---|
| 1 | **A second plugin**, `availability_coursecompleted`, unlocks the certificate on course completion. The spec's Constitution Check said "one plugin at most". The core-only alternative re-locks the certificate for anyone who completed the course before a lesson was added (R8). | **Accepted 2026-10-02.** The spec's Constitution Check now allows two pinned plugins. |
| 2 | **The pilot learner at stage 8.** One Moodle course means one completion record, so the badge's cron review would award a pilot learner at delivery. Stage 8 suspends their manual enrolment first, and they earn the badge once they join through their organisation's cohort, from the completion they already have (R4; same limit as 004 decision #6). | **Pending the maintainer.** |
| 3 | **Badge visibility.** Other learners cannot see a learner's badges; the learner's mentor and the site team can. Organisation managers see completion through 004's reports, not badges. The hash URL is shareable by the learner (R10). | **Pending the maintainer.** |
| 4 | **Issuer details.** The issuing programme's name, the contact address (set through `env:`), the logo and the badge design are needed from the maintainer before V6. Placeholders are used until then, and `validate` refuses a placeholder on a delivery branch. | **Pending the maintainer.** |
| 5 | **The certificate verify page** also shows the activity's name, and shows the issue date (the first download), not the completion date. The printed certificate shows the completion date (R9). | Proposed: accept. |
| 6 | **Already-downloaded badge images** keep their old wording after a reword. The online badge and its verification show the new wording (R5). | Proposed: accept. |

## Cross-spec effects

- **Spec 001**: its declaration contract gains `badges.yaml`, `certificate/`, `settings/badges.yaml`, two plugin pins and two item types ([contracts/declaration.md](contracts/declaration.md)). 001's contract links to it, as it does to 002's and 004's.
- **Spec 003**: its mentor role must grant `moodle/badges:viewotherbadges` in user context (R10). Until 003 is built, US3's mentor check is limited to the site team view, and US3 is not closed. *(2026-10-05: done. The `mentor` role in `moodle/site/roles.yaml` grants it, and `MENTOR_ALLOW` allows it; the mentor half of V9 can now run.)*
- **Spec 004**: this spec depends on its completion criteria and its two course fields. `set_course_completion` leaves out the certificate's idnumber. 004's FR-010 rule moves to `cbc_wording.py` unchanged, and its tests move with it.
- **Spec 006**: pathway badges wait for pathways (spec Assumptions). R1's course criterion is the pattern to follow.
- **Spec 009**: V7 runs on 009's V7 device.
- **Spec 015**: the production database backup is what preserves awards and issued certificates. Course backups do not carry awards (R14). The cron 015 monitors is what issues badges.
- **Stage 8 how-to**: suspend the pilot learner's enrolment before the delivery publish (decision 2), and retire a course by hiding it, never by deleting it (R14).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| A second third-party plugin, `availability_coursecompleted` (accepted 2026-10-02; the spec now allows two) | It unlocks the certificate on the course completion record, which a republish never wipes (R8, edge case 1) | Core's AND of lesson conditions re-locks the certificate for a learner who completed before a lesson was added, and gives a long condition list. |
| Calls into `mod_customcert`'s internal service and element classes (R7) | One shared design copied into every course's activity (FR-012), applied from the repo (FR-007) | The plugin has no published API for templates, and its import always duplicates. Building each activity's template from scratch means more of our code against the same classes. |
| A new `local_ltuse` table, `local_ltuse_course_badge` (R1) | A badge has no idnumber, and restore and copy duplicate names | Identifying badges by name breaks on a rename or a restore. |
