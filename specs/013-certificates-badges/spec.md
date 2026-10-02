# Feature Specification: Completion Badges and Certificates

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver row #23 of moodle/REQUIREMENTS.md (Certificates / badges, Pref): learners who finish a published course receive a completion badge and can download a certificate of training completed, using core Moodle capability where it exists. Both are training evidence only — the wording says 'training completed', never 'certified', and neither states a CBC level as awarded. Designs, criteria and wording are held as configuration in the repo so the site can be rebuilt."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Learner earns a completion badge (Priority: P1)

A consultant finishes every required activity of a published course. Without asking anyone, they receive a badge naming the course and saying the training was completed, and they can see it on their profile in the browser and in the Moodle app.

**Why this priority**: it is the smallest slice that gives a learner visible recognition for finishing, and it rests on course completion, which progress reporting (spec 004) already needs.

**Independent Test**: on the temporary instance, complete a published course with a test account and confirm the badge is issued automatically, is visible on the profile and in the app, and its wording passes the wording rule (FR-004).

**Acceptance Scenarios**:

1. **Given** a published course with completion criteria and a badge attached, **When** a learner meets the completion criteria, **Then** the badge is issued to them without manual action, and they are notified.
2. **Given** a learner has earned the badge, **When** they open their profile in the Moodle app, **Then** the badge is listed with the course name and the date issued.
3. **Given** a learner has completed only some required activities, **When** they view the course, **Then** no badge has been issued and they can see what remains.

---

### User Story 2 - Learner downloads a certificate of training completed (Priority: P2)

A learner who has completed a course downloads a certificate they can show a supervisor or keep for their records. It names them, the course, the date and the issuing training programme, and says "training completed".

**Why this priority**: partners and supervisors often want a document rather than a badge, but a badge alone already delivers the core recognition.

**Independent Test**: complete a course with a test account, download the certificate on a phone over a slow connection, and check its wording and that it identifies the learner, course and date.

**Acceptance Scenarios**:

1. **Given** a learner has completed a course, **When** they choose to download its certificate, **Then** they receive a document stating the learner's name, the course title, the completion date, a verification reference and the words "training completed".
2. **Given** a learner has not completed the course, **When** they look for the certificate, **Then** it is not available and they are told it becomes available on completion.
3. **Given** a certificate has been issued, **When** a third party enters its verification reference, **Then** they can confirm it was issued for that learner and course without seeing anything else about the learner.

---

### User Story 3 - A CBC assessor or mentor consults the evidence (Priority: P3)

A CBC assessor or mentor looks at which courses a learner has completed, including the competencies each course is mapped to, as one input to an assessment the CBC programme makes elsewhere.

**Why this priority**: INTENT allows assessors to consult training evidence; it adds value only once badges exist.

**Independent Test**: with a test mentor account (spec 003) view an assigned learner's badges and confirm that competencies are shown as "trained in", never as a level achieved.

**Acceptance Scenarios**:

1. **Given** a learner holds badges, **When** their mentor views them, **Then** each badge shows the course and the competencies the course addresses, and no badge shows a CBC level as reached or awarded.
2. **Given** a manager of another partner organisation, **When** they try to view this learner's badges beyond what the learner has made public, **Then** they cannot (spec 002 scoping).

---

### User Story 4 - Maintainer rebuilds badges from the repo (Priority: P3)

The maintainer stands up a fresh instance and every badge design, criterion and certificate template reappears from the repo, with no admin-UI clicking.

**Why this priority**: required by constitution II, but it only matters once the first badge exists.

**Independent Test**: apply the badge and certificate configuration to an empty test instance and compare against the reference instance.

**Acceptance Scenarios**:

1. **Given** an empty test instance with the site baseline (spec 001) applied, **When** the badge configuration is applied from the repo, **Then** every course badge and the certificate template exist with the same wording and criteria.
2. **Given** a badge's wording is changed in the repo, **When** the configuration is applied again, **Then** the change appears without creating a duplicate badge.

### Edge Cases

- A course is republished with changed activities after some learners hold its badge: their badge stands; new criteria apply only to learners completing afterwards.
- A course is retired: issued badges and certificates remain valid and verifiable.
- A learner's name changes: the certificate reflects the name on the account at download time.
- A backfilled legacy course that learners completed in Cypher: no badge is issued retroactively from Cypher records (no such data is imported).
- Someone asks for a badge that says "CBC certified" or "Level 3 – Independent": refused by rule; the wording check fails.
- A learner is offline when they complete the course in the app: the badge issues when completion syncs.
- A pilot (stage 7) course: pilot runs issue no badge; a pilot learner who later completes the published course earns it then.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST issue a completion badge automatically when a learner meets a published course's completion criteria, using core Moodle capability where it exists.
- **FR-002**: Every published course that has completion criteria MUST have exactly one completion badge; the badge MUST be tied to the course's stable identity so republishing updates it rather than duplicating it.
- **FR-003**: Learners who have completed a course MUST be able to download a certificate of training completed, stating learner name, course title, completion date, issuing programme and a verification reference.
- **FR-004**: All badge and certificate wording MUST say "training completed" (or "completed the course"); it MUST NOT contain "certified", "certification", "certificate of competency" or any equivalent, and MUST NOT state a CBC level as awarded, reached or held.
- **FR-005**: A badge or certificate MAY name the competencies the course addresses and the course's target level, but only as a description of the course ("designed to support progress towards …"), never as the learner's attained level. Any level shown MUST use CBC vocabulary (`0 - No Competency` … `4 - Expert`) exactly.
- **FR-006**: The wording rules in FR-004 and FR-005 MUST be enforced by an automated check that fails before any badge or certificate configuration is applied.
- **FR-007**: Badge designs, criteria, wording and the certificate template MUST be held in the repo under `moodle/` and applied from there; the site MUST be rebuildable from the repo plus a data restore.
- **FR-008**: Issued badges and certificates MUST be learner data and live only in Moodle; nothing issued (names, dates, recipient lists) may be written to the repo.
- **FR-009**: A third party MUST be able to verify a certificate or badge by its reference, seeing only the learner name, course, and issue date.
- **FR-010**: Badges MUST be viewable in the Moodle app and certificates downloadable on an Android phone; the certificate MUST be small enough to download on a low-bandwidth connection.
- **FR-011**: Mentors and organisation managers MUST see badges only for learners within their scope (specs 002, 003).
- **FR-012**: One badge and certificate design MUST serve every partner organisation; no per-partner variants.
- **FR-013**: Learners MUST be able to export their own badges, so their training history is portable.

### Key Entities

- **Completion badge**: one per published course; name, description, image, criterion (course completion), stable identity linked to the course's.
- **Badge award**: a learner holding a badge, with issue date; lives only in Moodle.
- **Certificate template**: one shared template; fixed wording plus fields for learner, course, date and verification reference.
- **Issued certificate**: a learner's certificate instance with its verification reference; lives only in Moodle.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of test completions of a published course produce the badge within 1 hour, with no manual step.
- **SC-002**: 0 badges or certificates on the site contain "certified" or state a CBC level as awarded, confirmed by the automated check and by a manual review of each design.
- **SC-003**: A learner can find and download their certificate in under 1 minute from finishing the course, on an Android phone, without instructions.
- **SC-004**: Rebuilding an empty test instance from the repo recreates 100% of badges and the certificate template with identical wording.
- **SC-005**: A certificate downloads in under 30 seconds on a 256 kbit/s connection.

## Assumptions

- Recognition is per course only; pathway-level badges wait for the pathway mechanism (spec 006, INTENT open question).
- Badges are issued from course completion alone; competency-based badge criteria are not used, because they would read as a competency being awarded.
- The issuing programme named on certificates is the LTC training programme; the exact organisational name and logo come from the maintainer at plan time.
- A certificate plugin is likely needed (core has badges but no certificates); per constitution this is chosen and pinned in the plan after checking 5.2 support.
- Pilot runs issue no badges by default.
- Badges are private to the learner by default; the learner chooses whether to make them public.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 23 | Certificates / badges | Pref | Automatic per-course completion badges, a shared "training completed" certificate with verification, enforced wording rules, and badge configuration held as code. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: badge and certificate definitions live in the repo; published one-way; nothing issued is synced back.
- **II. Portability / config-as-code**: every badge, criterion and template is applied from `moodle/`; FR-007 and SC-004 test rebuildability; learners can export badges (FR-013).
- **III. Public repo, private people**: awards and issued certificates are learner data and stay in Moodle (FR-008); verification uses test accounts only.
- **IV. Disclosure boundary**: badges and certificates carry no quiz content or answers.
- **V. CBC fidelity**: the heart of this spec — "training completed", never "certified"; no CBC level awarded or recorded (FR-004, FR-005, FR-006); CBC vocabulary only.
- **VI. No LMS orientation**: badges arrive automatically and the certificate is found without instructions (SC-003).
- **VII. One shape**: one badge per course, one certificate design for all partners (FR-012).
- **IX. Flat cost, field-ready**: core badges plus two free plugins (a certificate plugin and a course-completion availability condition); works in the app and on low bandwidth (FR-010, SC-005).
- **X. Traceable and verified**: cites row #23; badge behaviour and any certificate plugin are verified on the temporary 5.2.3+ instance before the plan depends on them. Because SC-003 is a "simple" criterion, 2–3 real partner learners must use it (e.g. at a stage-7 pilot) before it is marked done.
- **Platform & Delivery**: core badges first; at most two plugins, each pinned: one for certificates, and one availability condition that unlocks the certificate on course completion (decided 2026-10-02, plan decision 1); one instance, no per-partner design; server from `MOODLE_URL`.

## Dependencies

- **001-site-config-as-code**: the mechanism that applies configuration from `moodle/`, and badges enabled in the baseline.
- **004-progress-reporting**: course completion criteria that trigger the badge.
- **002-org-structure-cohorts** and **003-mentor-role**: scoping of who can see a learner's badges.
- **015-production-hosting-ops**: issued badges survive only if production backups cover them.
