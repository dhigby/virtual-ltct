# Feature Specification: Progress Tracking and Reporting

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver progress tracking and reporting for the training system, from `moodle/REQUIREMENTS.md` row #7 (Progress tracking) and the report-exports side of row #16 (Data export / ownership). Every published course tracks completion by one consistent rule; learners see their own progress; organisation and cohort managers see their own people's progress and nobody else's; managers and the maintainer can export and schedule reports. Reports are training evidence, never a CBC certification record, and no report or export ever reaches the public repo."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Every published course tracks completion the same way (Priority: P1)

A learner works through a published course, on the web or in the Moodle app, online or offline. Each lesson and the quiz record completion as they go, and the course marks itself complete when the learner has done what the course requires. The rule is the same for every course the repo publishes, so "complete" means one thing across the catalogue.

**Why this priority**: Every report, pathway (006) and badge (013) reads completion. If completion is set per course by hand, or differs between courses, everything built on it is untrustworthy.

**Independent Test**: Publish two different courses to the test instance; as a test learner, complete one fully and the other partly, including one lesson viewed offline in the app. Both courses show the expected state with no manual setup in either.

**Acceptance Scenarios**:

1. **Given** a course published from the repo, **When** a test learner opens a lesson, **Then** that lesson shows as complete for them without any further action.
2. **Given** a learner has completed every lesson and met the quiz requirement, **When** they next view the course, **Then** the course shows as complete.
3. **Given** a learner completed lessons offline in the Moodle app, **When** the app next syncs, **Then** those completions appear on the server.
4. **Given** a freshly published course, **When** an administrator inspects its completion settings, **Then** nothing was set by hand in the admin UI — the settings came from the publish or from `moodle/`.

---

### User Story 2 - A learner sees their own progress (Priority: P1)

A learner can see, from where they land after logging in, which of their courses they have finished, how far they are through the rest, and what is left, without being taught where to look.

**Why this priority**: Knowing where you are is the smallest useful slice of progress and the one every learner uses; it is also what the "no LMS orientation" rule tests first.

**Independent Test**: A test learner enrolled in three courses at different stages can say, from their landing page alone, which is finished and which lesson comes next in the others.

**Acceptance Scenarios**:

1. **Given** a learner part-way through a course, **When** they log in, **Then** they see that course's progress and a direct route to their next incomplete lesson.
2. **Given** a learner has completed a course, **When** they view their courses, **Then** it is shown as completed, not mixed in with courses in progress.

---

### User Story 3 - A manager sees their own organisation's progress, and only theirs (Priority: P2)

An organisation or cohort manager opens a progress report and sees, for their own learners only, who is enrolled in what, who has started, who has finished, and who has not been active recently. They cannot reach any other organisation's learners through the report, by filtering, or by export.

**Why this priority**: This is what lets a partner run its own programme without asking us; the scoping is what makes one shared instance acceptable to every partner on it.

**Independent Test**: With two test organisations set up (002), a manager of organisation A runs every provided report and export; every row belongs to organisation A.

**Acceptance Scenarios**:

1. **Given** a manager of organisation A, **When** they open the progress report, **Then** they see completion and last-activity for organisation A's learners and no one else's.
2. **Given** the same manager, **When** they try to widen a filter or open a report built for another scope, **Then** they are refused or shown nothing outside their organisation.
3. **Given** a cohort manager, **When** they open the report, **Then** they see only their cohort.

---

### User Story 4 - Managers export and schedule reports (Priority: P3)

A manager downloads their scoped report as a spreadsheet file, or has it emailed to them on a schedule, so they can report to their own organisation without logging in each time.

**Why this priority**: Useful for partner reporting and for learner-data portability (row #16), but the in-Moodle view in Story 3 already delivers the core value.

**Independent Test**: A test manager downloads a report and receives a scheduled copy by email; both contain only their organisation's rows.

**Acceptance Scenarios**:

1. **Given** a manager viewing their report, **When** they export it, **Then** they receive a spreadsheet file holding exactly the rows they could see.
2. **Given** a manager has subscribed to a weekly report, **When** the week passes, **Then** they receive it by email, scoped exactly as the on-screen report.

---

### User Story 5 - The maintainer sees programme-wide totals (Priority: P3)

The maintainer (or another member of the site team with the system manager role) sees totals across all organisations: enrolments, completions per course and per competency, and activity over time — the evidence for which courses are used and where effort should go next.

**Why this priority**: Informs prioritisation alongside `COVERAGE.md`, but no learner or partner depends on it.

**Independent Test**: With test data across two organisations, the site-wide report shows per-course completion counts that match the sum of the two organisations' reports.

**Acceptance Scenarios**:

1. **Given** the system manager role, **When** the maintainer opens the programme report, **Then** it shows completion counts per course and per competency the courses declare.

### Edge Cases

- A course is republished with a lesson added or removed: learners who had already completed the course stay complete; learners in progress see the new lesson as outstanding. The republish never silently erases recorded completions.
- A pilot (stage 7) course and its later published version: pilot completions are distinguishable from delivery completions in reports, so pilot numbers never inflate delivery figures.
- A learner moves from one organisation to another: from then on they appear in the new organisation's reports; their earlier records are not shown to the new manager unless 002 decides otherwise.
- A learner belongs to two cohorts under the same manager: they appear once per course, not twice.
- A manager with no learners yet: the report shows an empty state that says so, not an error.
- A quiz withheld from the learner view (fails-closed disclosure): the course's completion rule counts only what was actually published, so a learner can still complete the course.
- Offline completions arrive days late: reports reflect them when they sync, dated when they happened where the platform allows.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Every course published from the repo MUST have activity and course completion enabled, set by the publish or by configuration under `moodle/`, never by hand per course.
- **FR-002**: One completion rule MUST apply to every published course, defined once in the repo. The default rule is in Assumptions.
- **FR-003**: Completion MUST be recorded when a learner uses the course in the Moodle app, including offline, once the app syncs.
- **FR-004**: A learner MUST be able to see their own per-course progress and their next incomplete lesson from their landing page without navigation training.
- **FR-005**: Organisation and cohort managers MUST see progress only for learners within their own scope (as defined by 002), in the on-screen report, in filters and in every export.
- **FR-006**: The organisation report MUST show at least: learner, organisation, cohort or group, course, enrolment date, started or not, progress through the course as a percentage, quiz result, course completion date and last activity. Per-lesson completion (lessons done of total) MUST be available to the same manager in the course's own completion reports.
- **FR-007**: Managers MUST be able to export their scoped report as a spreadsheet file and subscribe to a scheduled emailed copy.
- **FR-008**: Report definitions, their audiences and their scoping MUST be defined under `moodle/` and applied by script, so a rebuilt server gets the same reports.
- **FR-009**: No report export, sample or fixture containing learner data MUST ever be written into the repository — including test runs, which use test accounts only.
- **FR-010**: Reports MUST NOT present any learner as having reached a CBC level. Where a report shows a course's declared competencies or `target_outcome_level`, it MUST label them as what the course aims at, in CBC vocabulary only.
- **FR-011**: A republish MUST NOT reset or delete recorded completions.
- **FR-012**: Pilot enrolments MUST be distinguishable from delivery enrolments in every report.
- **FR-013**: A site-wide report MUST give per-course and per-declared-competency completion counts across all organisations, visible only to the system manager role.
- **FR-014**: Any learner's full progress record MUST be exportable from Moodle on request, so leaving the platform does not lose learner history (row #16).

### Key Entities

- **Completion rule**: the single, repo-defined definition of when a lesson, a quiz and a course count as complete.
- **Progress record**: per learner, per course — enrolment, lessons completed, quiz result, completion date, last activity. Lives in Moodle only.
- **Report**: a named, repo-defined view of progress records with an audience and a scope (learner, cohort, organisation, site).
- **Scope**: the organisation or cohort boundary a manager's reports are confined to, as defined by 002.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of courses published from the repo have working completion with zero per-course manual steps.
- **SC-002**: In a scoping test across two test organisations, 0 rows outside a manager's scope appear in any report or export.
- **SC-003**: 2–3 real partner managers can find and read their organisation's progress, and export it, without help, and their findings are recorded.
- **SC-004**: 2–3 real partner learners can say what they have finished and what comes next within 1 minute of logging in.
- **SC-005**: A rebuilt test server has the same reports, audiences and scoping as before, with no admin-UI steps.
- **SC-006**: 0 files containing learner data are committed to the repository (checked at review of every PR in this feature).

## Assumptions

- Default completion rule: a lesson is complete when viewed; the quiz is complete when submitted, and when passed if the course's design sets a pass mark; the course is complete when all lessons and the quiz are complete. Scenario banks and job aids count as lessons only if numbered as lessons.
- By default a manager sees completion, progress, quiz result and last activity for their learners, but not individual quiz responses. What a manager may see beyond this is settled with 002 (INTENT open question on onboarding).
- Retention of progress records and exports follows the site-wide data-protection position (INTENT open question); this spec neither sets nor waits on it, but does not delete records.
- Scheduled report emails depend on the site's outgoing mail (001); push notification limits do not affect this feature.
- Mentor views of assigned learners' progress belong to 003; this spec supplies the progress data they read.
- Build and pilot happen on the shared `ltuse.net` host; learners go live only on the production VPS (015).

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
| --- | --- | --- | --- |
| 7 | Progress tracking | Must | Consistent completion on every published course; learner, manager and site-wide progress reports. |
| 16 | Data export / ownership | Must | The report-exports side: scoped spreadsheet export, scheduled reports, per-learner progress export. Database ownership and backups stay with 001/015. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: completion settings come from the publish or `moodle/`; nothing flows back from Moodle to the repo; published courses are not hand-edited to set completion.
- **II. Config as code**: completion rule, reports, audiences and schedules are defined under `moodle/` and rebuildable (FR-008, SC-005).
- **III. Public repo, private people (NON-NEGOTIABLE)**: reports and exports stay in Moodle and in managers' inboxes; no export or fixture is committed; tests use test accounts (FR-009, SC-006).
- **IV. Disclosure**: reports show quiz results, never answer keys; a withheld quiz does not make a course un-completable (Edge Cases).
- **V. CBC fidelity**: no learner is shown at a CBC level; course target levels are labelled as aims, in CBC vocabulary (FR-010).
- **VI. No LMS orientation**: learners find their progress without training (FR-004, SC-004); managers' ease is tested with real partner managers (SC-003).
- **IX. Flat cost, field-ready**: core reporting, no per-learner cost; offline completions sync from the app (FR-003).
- **X. Traceable and verified**: cites rows #7 and #16; completion syncing from the app offline and report scoping MUST be verified on the temporary 5.2.3+ instance before the plan depends on them; "simple" criteria need 2–3 real partner users. Scheduled email adds mail-delivery operations whose owner is the undecided Moodle operator (015); this spec does not claim they are covered.
- **Platform & Delivery**: core reporting capability first; one instance, one role set, scoping by category/cohort (002); server from `MOODLE_URL`.

## Dependencies

- **001-site-config-as-code** — the declarative configuration mechanism, outgoing mail and baseline settings.
- **002-org-structure-cohorts** — organisations, cohorts and the category-scoped manager roles that define report scope.
- **003-mentor-role** — consumes this progress data for mentor views.
- **006-learning-pathways** and **013-certificates-badges** — consume completion; they depend on this spec, not the reverse.
- **015-production-hosting-ops** — production host, mail delivery and the operator for scheduled reports.
