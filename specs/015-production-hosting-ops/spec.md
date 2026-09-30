# Feature Specification: Production Hosting and Operations

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver row #17 of moodle/REQUIREMENTS.md (Pricing at scale, Must) and the operations dependency of every 'Ongoing' row (#9/#20 if bolted on, #14, and the backups side of #16): move Moodle from the shared build/pilot host to a dedicated production VPS before any learner goes live, and define the recurring operations it needs — hosting, backups and restore drills, upgrades, monitoring, support and the Moodle app plan — each with a named owner and a flat cost. Who operates the server is INTENT's most important open question."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Production stands up from the repo plus a restore (Priority: P1)

The operator provisions a dedicated server, applies the site configuration from the repo, restores the data, and republishes courses. The result is a working training system; nobody clicked a setting into the admin UI to get there.

**Why this priority**: INTENT forbids taking learners live on the shared host; nothing else in this spec matters until production exists, and rebuildability is what makes it survivable.

**Independent Test**: on a fresh test server, rebuild from the repo plus a test-data restore (no real learner data), republish two courses, and log in as a test learner in the browser and the Moodle app.

**Acceptance Scenarios**:

1. **Given** an empty server that meets the documented minimum, **When** the operator follows the runbook, **Then** Moodle 5.2.3+ runs with every setting, plugin, role and structure from `moodle/`, reached at the address given by `MOODLE_URL`.
2. **Given** a restored database and file store, **When** courses are republished, **Then** existing course identities are updated, not duplicated, and learners' completions are intact.
3. **Given** the production address differs from the build host's, **When** the publisher and configuration are pointed at it, **Then** nothing needs changing in the repo other than the environment.

---

### User Story 2 - Learner data survives any single failure (Priority: P1)

The server's disk fails, or an upgrade goes wrong. The operator restores from the last backup and learners lose at most one day's work.

**Why this priority**: learner data lives only in Moodle and never in the repo, so a backup is the only copy of every learner's history.

**Independent Test**: run a scheduled restore drill into a separate test server and time it; confirm test learners' enrolments, completions and badges are present.

**Acceptance Scenarios**:

1. **Given** nightly backups, **When** the production server is lost, **Then** a restore from off-site backup brings the site back with no more than 24 hours of data lost.
2. **Given** the restore drill schedule, **When** a drill is due, **Then** it is carried out, timed and its result recorded in the operations log (not in the public repo if it names learners).
3. **Given** backups are stored off the server, **When** someone inspects the repo, **Then** no backup, dump or credential for them is present.

---

### User Story 3 - The operator knows when something is wrong (Priority: P2)

The site goes down, a disk nears full, a certificate is about to expire, or scheduled tasks stop running. The operator is told before learners report it.

**Why this priority**: a small team cannot watch a server; without alerts, failures surface as learner complaints.

**Independent Test**: stop the web service on a test server and confirm an alert reaches the named on-call person within the target time.

**Acceptance Scenarios**:

1. **Given** the site is unreachable, **When** 10 minutes pass, **Then** the operator is alerted.
2. **Given** scheduled Moodle tasks have not run for an hour, **When** monitoring checks, **Then** the operator is alerted (these drive completions, badges and notifications).

---

### User Story 4 - Upgrades happen on a schedule, tested first (Priority: P2)

Moodle, its platform software and each pinned plugin receive security and minor updates regularly. Each is tried on a test instance before production.

**Why this priority**: an unpatched public site holding people's data is a liability; pinned plugins are an upgrade burden the constitution names.

**Independent Test**: take a minor Moodle update through the test instance, publish a course there, then apply to production with a rollback point.

**Acceptance Scenarios**:

1. **Given** a Moodle security release, **When** it is published, **Then** it reaches production within 14 days after passing a test publish.
2. **Given** a pinned plugin lacks support for a new Moodle version, **When** the upgrade is planned, **Then** the upgrade waits or the plugin is replaced, and the decision is recorded.

---

### User Story 5 - Cost stays flat as learners grow (Priority: P3)

The maintainer can state the yearly running cost at 100, 1,000 and 5,000 learners, and it does not rise per learner. The app notification plan is a deliberate flat-rate choice.

**Why this priority**: row #17 is a Must, but the cost model is only testable once production is sized.

**Independent Test**: produce the cost table and check each line is flat-rate or stepwise-flat, with the app plan's device limit compared against active app users.

**Acceptance Scenarios**:

1. **Given** the cost table, **When** learner numbers grow tenfold, **Then** no line grows per learner; any step (a larger server, a higher app plan) is a flat tier.
2. **Given** active app devices exceed the free plan's 50 a month, **When** the monthly figure is reviewed, **Then** the decision to move to a flat paid plan or rely on email is taken and recorded, and learners still receive notifications by email meanwhile.

### Edge Cases

- The operator is unavailable for weeks: a second named person can run a restore from the runbook alone.
- The VPS provider fails or raises prices: the site moves to another provider by rebuild plus restore (Story 1).
- A backup is taken but never restored: counts as untested; the drill schedule prevents this.
- Load spikes at a cohort launch: the server size is chosen for the expected concurrent users with headroom.
- The shared build host is still running after go-live: it holds no real learner data and is used only for building and piloting.
- A learner asks for their data to be deleted: deletion in production is possible, but data in older backups persists until they expire; this needs the missing retention rule (see Assumptions).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Learners MUST NOT go live until Moodle runs on a dedicated production server; the shared host is limited to building and piloting with test and pilot accounts.
- **FR-002**: Production MUST be rebuildable from the repo plus a data restore, with a written runbook a second person can follow; the server address MUST come from `MOODLE_URL`, never be hard-coded.
- **FR-003**: Production MUST be backed up at least nightly — database and file store — to storage off the production server, encrypted, and kept for a stated retention period.
- **FR-004**: A restore drill into a separate server MUST run at least quarterly and after each major upgrade, with its time and result recorded.
- **FR-005**: No backup, database dump, report export or credential MUST ever be committed to the repo; credentials come from the environment or the host's secret store.
- **FR-006**: Monitoring MUST alert a named person on site unavailability, failed scheduled tasks, low disk, failed backups and expiring encryption certificates.
- **FR-007**: Moodle, its platform software and every pinned plugin MUST be updated on a stated schedule, each change tried on a test instance with a test publish first, with a rollback point before production changes.
- **FR-008**: Every recurring burden MUST be listed with its owner and yearly cost: hosting, off-site backup storage, restore drills, upgrades, monitoring, learner and partner support, the Moodle app plan, and any bolt-on service adopted by specs 005 or 014.
- **FR-009**: Every cost line MUST be flat-rate or tiered-flat; none may scale per learner.
- **FR-010**: The Moodle app notification plan MUST be chosen deliberately: the free plan covers 50 active devices a month (Pro 500, Premium unlimited); active devices MUST be reviewed monthly against the plan, and email MUST remain a working notification route whatever plan is chosen.
- **FR-011**: The server MUST be sized for the expected concurrent learners with headroom, and the sizing assumption recorded so it can be revisited.
- **FR-012**: Production operations MUST be carried by [NEEDS CLARIFICATION: who operates the production Moodle server — the department (which person, with what backup cover), a partner such as Seed Company, a managed Moodle host at a flat rate, or someone else? INTENT names this the most important open question; until it is answered, no spec may claim its operations are covered.]
- **FR-013**: Operations records that name learners (support tickets, restore logs listing accounts) MUST be kept outside the public repo.

### Key Entities

- **Production server**: the dedicated host running the live training system; its address comes from the environment.
- **Backup set**: nightly database and file-store copy, off-server, with a retention period.
- **Runbook**: the written, repo-held procedure for rebuild, restore, upgrade and incident response (no credentials, no learner data).
- **Operations register**: each recurring burden with owner, frequency and yearly cost.
- **App plan**: the Moodle app notification subscription tier.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A rebuild plus restore onto a fresh server completes within 1 working day, by someone other than the person who wrote the runbook.
- **SC-002**: Worst-case data loss from any single failure is 24 hours or less, demonstrated by a restore drill.
- **SC-003**: Every restore drill due in a year is carried out and recorded (100%).
- **SC-004**: The site is available at least 99% of each month, measured by external monitoring.
- **SC-005**: Security releases reach production within 14 days of publication.
- **SC-006**: The operations register names an owner for 100% of recurring burdens, and the yearly cost does not rise per learner between 100 and 5,000 learners.

## Assumptions

- A modest VPS serves thousands of learners (REQUIREMENTS #17); exact size is set at plan time from expected concurrency.
- Nightly backups and a 24-hour data-loss ceiling suit a training system; nothing here needs real-time replication.
- The runbook lives in the repo under `moodle/`, free of credentials and learner data; operations logs naming learners live elsewhere.
- Until an app plan is chosen, the free plan applies and email is the dependable notification route.
- **Data protection is a dependency, not delivered here.** INTENT's open question — privacy notice, consent at sign-up, a retention rule, and an answer to "delete my data" — is not owned by any of specs 001–015. This spec only needs a retention period for backups and a rule for how deletions interact with backups; both wait on that policy. Until it exists, backup retention defaults to 30 days, the shortest that still allows monthly drills. Recorded as a gap for the maintainer.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 17 | Pricing at scale | Must | Flat-rate cost register across hosting, backups, admin time and app plan; app-plan review against the 50-device free limit. |
| 16 | Data export / ownership | Must | Off-site backups and restore drills (the backups side; exports are specs 001/004). |
| 14 | Simple administration | Must | The ongoing operations share of "Ongoing" (upgrades, monitoring, support ownership). |
| 9, 20 | Community beyond courses / channels | Must | Operations and cost ownership only if a bolt-on is adopted (spec 005). |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: the runbook and configuration live in the repo; production is rebuilt from it; no Moodle-to-repo sync.
- **II. Portability / config-as-code**: rebuild from repo plus data restore is the P1 test (FR-002, SC-001); moving provider is a rebuild.
- **III. Public repo, private people (NON-NEGOTIABLE)**: backups, dumps, exports and credentials never enter the repo (FR-005, FR-013); drills use a separate server and test verification uses test accounts.
- **VI. No LMS orientation**: learners see none of this; the runbook is for the operator.
- **IX. Flat cost, field-ready**: every line flat-rate (FR-009); app plan chosen deliberately with email as fallback (FR-010).
- **X. Traceable and verified**: cites rows #17, #16, #14, #9/#20. Names every recurring burden and requires an owner (FR-008), but because the operator is undecided (FR-012) this spec does **not** claim operations are covered. Upgrades are verified on a test instance before production (FR-007).
- **Platform & Delivery**: one production instance for all hosted partners; pinned plugins reviewed at each upgrade; server from `MOODLE_URL`; never `mkdocs gh-deploy` locally.

## Dependencies

- **001-site-config-as-code**: the declarative configuration production is rebuilt from.
- **005-community-space** and **014-resource-library**: add operations burdens only if they adopt a bolt-on or search service.
- **All other specs (002–014)**: depend on this one for the live system they run on; none can be operated in production until FR-012 is answered.
