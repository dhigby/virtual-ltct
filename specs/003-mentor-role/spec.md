# Feature Specification: Mentor relationship and visibility

**Feature Branch**: `003-mentor-role`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Give mentors a standing relationship with the learners assigned to them: a mentor sees each assigned learner's progress across every course, for months, not tied to any one enrolment; mentor and learner can stay in contact after a course ends; the relationship is assigned and ended by the small team or the learner's organisation manager, and the mentor sees no one else. Delivers moodle/REQUIREMENTS.md row #11 (mentor / trainer interaction), following the mentor model in INTENT.md."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A mentor sees an assigned learner's progress across all their courses (Priority: P1)

A mentor signs in and finds the learners assigned to them. For each one they can see which courses the learner is enrolled in, how far through each they are, and what they have completed — across the whole site and over the months of the relationship, including courses the learner joined after the mentor was assigned.

**Why this priority**: INTENT: "Learners are supported by a mentor, not left alone with a video," and mentors need "a way to see their learners' progress ... over time." A mentor who cannot see progress cannot guide it.

**Independent Test**: Assign a test mentor to a test learner enrolled in two courses; confirm the mentor sees progress in both, then enrol the learner in a third course and confirm it appears without any change to the mentor's setup.

**Acceptance Scenarios**:

1. **Given** a mentor assigned to a learner, **When** the mentor opens their list of learners, **Then** that learner appears with their courses and completion state.
2. **Given** the learner enrols in a new course after assignment, **When** the mentor looks again, **Then** the new course is included without re-assigning anything.
3. **Given** a course the learner finished months ago, **When** the mentor looks, **Then** its completion is still visible.
4. **Given** a mentor, **When** they look for a learner not assigned to them, **Then** that learner's progress and profile beyond what any site member may see are not available.

---

### User Story 2 - Mentor and learner stay in contact (Priority: P2)

A mentor and their learner can message each other directly — in the browser and the Moodle app — while a course is running and after it ends, without either needing to be enrolled in a shared course.

**Why this priority**: The relationship persists "over months rather than minutes" and is "not tied to any one enrolment" (INTENT B.2). Contact is the second half of mentoring; it relies on the messaging baseline from spec 001.

**Independent Test**: With a test mentor and learner who share no course, confirm each can find the other and exchange messages in the browser and the app.

**Acceptance Scenarios**:

1. **Given** an assigned mentor and learner with no course in common, **When** either sends a message, **Then** the other receives it.
2. **Given** a learner who restricts who may message them, **When** their assigned mentor writes, **Then** the mentor can still reach them.

---

### User Story 3 - The relationship is assigned and ended without an LMS administrator (Priority: P3)

The small team, or the learner's organisation manager, assigns a mentor to a learner, reassigns them, or ends the relationship, in a few steps and without learning Moodle's permission system.

**Why this priority**: The first two stories can be tested with an administrator doing the assignment; this story is what makes it sustainable for a small team at hundreds of learners.

**Independent Test**: As a test organisation manager, assign a test mentor to one of the organisation's learners, confirm story 1 works, then end the relationship and confirm the mentor's view of that learner is gone.

**Acceptance Scenarios**:

1. **Given** an organisation manager, **When** they assign a mentor to one of their organisation's learners, **Then** the mentor gains the view in story 1.
2. **Given** an organisation manager, **When** they try to assign a mentor to another organisation's learner, **Then** it is refused.
3. **Given** an active relationship, **When** it is ended, **Then** the mentor immediately loses the progress view, and the learner's records are unchanged.
4. **Given** a mentor from a different organisation than the learner, **When** they are assigned, **Then** the relationship works the same way and grants no view of anyone else in the learner's organisation.

---

### User Story 4 - A mentor responds to a learner's work (Priority: P4)

Where a course asks the learner for work a mentor should respond to — an assignment, a reflection, a learning-plan review — the assigned mentor can read it and leave feedback.

**Why this priority**: Valuable, but depends on assignments (spec 012) and pathways (spec 006) existing; stories 1–3 deliver a working mentor relationship without it.

**Independent Test**: In a test course with a submission activity, confirm the assigned mentor can read the learner's submission and leave feedback that the learner sees.

**Acceptance Scenarios**:

1. **Given** a learner's submission in a course, **When** their assigned mentor opens it, **Then** the mentor can read it and leave feedback.
2. **Given** a mentor's feedback, **When** the learner opens the activity, **Then** they see it.

---

### Edge Cases

- A learner has two mentors (e.g. a technical and a regional mentor): each sees the learner; neither sees the other's other learners.
- A mentor is also a learner: their own courses and progress are unaffected by holding the mentor role.
- A mentor leaves the program: all their relationships can be ended at once; learners keep their history.
- A mentor tries to change a grade, completion or enrolment: refused; mentors view and respond, they do not alter records.
- A mentor tries to award a level: impossible; nothing in this role records or awards a CBC level, and feedback is training evidence only.
- A mentor needs the course's mentor guide: this spec does not publish mentor guides to Moodle. Any future route must keep them off every learner-visible page (constitution IV).
- A learner leaves their organisation: existing mentor relationships continue unless ended; the organisation manager's own scope follows spec 002.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The site MUST have exactly one mentor role, declared in the repo and applied through spec 001, used for every partner.
- **FR-002**: A mentor MUST be assignable to an individual learner, at the level of that learner rather than of a course, so the relationship is independent of any enrolment.
- **FR-003**: A mentor MUST be able to see, for each assigned learner, their course enrolments, activity and course completion, and profile details permitted to mentors, across all courses on the site. *(Note 2026-10-05: for a protected learner, the profile details permitted to an assigned mentor include their real identity and the Protected marker, on the profile and the Mentoring page (web and app), and only while assigned (spec 016 FR-006, FR-007; 016 research R7 path 2). The mentor role holds `local/ltuse:viewidentity` for this, a reviewed widening of the allowlist (research R2).)*
- **FR-004**: The mentor's view MUST include courses the learner enrols in after assignment, and completed courses, for as long as the relationship lasts.
- **FR-005**: A mentor MUST NOT see progress or restricted profile details of any learner not assigned to them. *(Note, Doug, 2026-10-05: this covers the Mentor role, held in the learner's user context, and is unaffected by what follows. A mentor who is also a course mentor holds Moodle's non-editing Teacher role (`teacher`) in that course. Its Moodle 5.2 defaults (`moodle/grade:viewall`, `gradereport/grader:view`, `report/progress:view`, `report/completion:view`, `moodle/site:viewuseridentity`, `moodle/course:viewhiddenuserfields`; `public/lib/db/access.php` and the report plugins' `db/access.php`, `MOODLE_502_STABLE`) reach every learner in a course in group mode 0, not only the learners they assess. Accepted (Doug, 2026-10-05): we trust people who are in the system. A protected learner's real identity still reaches a course mentor only through a shared mentor group (spec 016 R7 path 4).)*
- **FR-006**: The mentor role MUST NOT be able to change a learner's grades, completion, enrolments or profile. This covers the learner-level mentor role only. Assessing a course assignment is done through the course-level "Course mentor" role (Moodle's non-editing teacher), which may grade the work of its own groups in that course and never awards a CBC level (spec 012, research R3). ~~Enrolling a learner's mentor as Course mentor in the courses they take is deferred to spec 008 (plan research R8).~~ *(Updated 2026-10-05: delivered by spec 008's course-mentor sync (008 research R10), on since 2026-10-05 (`local_ltuse/coursementorsync: 1`, #97). Each learner's course mentor, by default their mentor here, is enrolled as Course mentor (`teacher`) through the course's `ltct:coursementor` enrolment, in their own mentor group `ltct:mentorgroup:<mentor id>`, and removed as soon as the reason ends; a one-course or cohort mentor replaces the default mentor in that course (008 plan decision 2). Only learners actively enrolled as Student through cohort sync or the Organisation enrolment count, so a pilot learner gets none. `moodle/local_ltuse/classes/admin/course_mentor_sync.php`, `course_mentor_rules.php`.)*
- **FR-007**: An assigned mentor and learner MUST be able to message each other in the browser and the Moodle app regardless of shared enrolment or the learner's messaging privacy preference. A learner's block of one named person still applies (plan research R5).
- **FR-008**: A site administrator and the learner's organisation manager MUST be able to assign, reassign and end a mentor relationship, using core Moodle capability where it exists; an organisation manager only for their own organisation's learners.
- **FR-009**: Ending a relationship MUST remove the mentor's view at once and leave the learner's records intact.
- **FR-010**: A learner MUST be able to see who their mentors are.
- **FR-011**: Where a course activity invites mentor feedback, the assigned mentor MUST be able to read the learner's submission and leave feedback.
- **FR-012**: Who mentors whom MUST be recorded only in Moodle, never in the repo.
- **FR-013**: The mentor role MUST NOT be able to award, record or display a CBC level.

### Key Entities

- **Mentor**: a person holding the mentor role over one or more learners; may also be a learner.
- **Mentor relationship**: the link between one mentor and one learner, with a start and, when ended, an end; independent of courses; lives only in Moodle.
- **Progress view**: what a mentor sees of an assigned learner — enrolments, completion and permitted profile details across the site.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A mentor can find any assigned learner's current progress in every course within one minute of signing in, on a phone as well as a desktop.
- **SC-002**: In a test with two mentors and four learners, each mentor sees 100% of their assigned learners' courses and 0% of unassigned learners' progress. *(Note, Doug, 2026-10-05: measured through the Mentor role in each learner's user context, as FR-005. A mentor who is also a course mentor holds Teacher in that course and sees the whole course there; accepted, see FR-005's note.)*
- **SC-003**: A relationship can be created or ended by an organisation manager in under two minutes without help.
- **SC-004**: A relationship created before a learner enrols in a new course covers that course with no further action, in 100% of test cases.
- **SC-005**: 2–3 real mentors and 2–3 real organisation managers use the relationship during a pilot, and their findings are recorded, before row #11 is marked done.

## Assumptions

- The mentor model is INTENT's: mentors follow learners over months, across courses, not inside one enrolment; within-course teaching roles ~~(such as a non-editing teacher in a cohort group)~~ remain available but are not the mentor relationship. *(Updated 2026-10-05: the within-course role is the Course mentor (`teacher`). Spec 008's sync derives each learner's course mentor from their mentor here by default (a one-course or cohort mentor replaces them, 008 plan decision 2) and enrols them in their own "Mentor group `<n>`" (`ltct:mentorgroup:<mentor id>`); the course stays in group mode 0 and has no cohort or organisation groups (spec 002, open courses). See FR-006.)*
- Mentors see completion and progress, not attempt-by-attempt quiz answers; quiz review stays with course staff.
- A mentor may be from any organisation; the assignment, not organisation membership, is what grants the view.
- Bulk assignment (many learners to one mentor from a list) is spec 008's tooling; this spec requires only that individual assignment is simple.
- A consolidated mentor report across all learners is spec 004's; this spec requires only that the view exists and is scoped correctly.
- Pathway and learning-plan review by mentors is detailed in spec 006; assignment feedback in spec 012.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 11 | Mentor / trainer interaction | Must | One mentor role assigned per learner, site-wide progress view, direct contact, feedback on learner work, assignment by organisation managers |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: the role is declared in the repo; relationships are Moodle data and never flow back.
- **II. Portability**: the role and its permissions are applied from `moodle/` via spec 001; relationships move with the data restore.
- **III. Public repo, private people**: who mentors whom lives only in Moodle (FR-012); verification uses test accounts only.
- **IV. Disclosure boundary**: mentor guides are not published to Moodle by this spec; mentors see progress, not quiz answer keys.
- **V. CBC fidelity**: mentors cannot award or record a CBC level (FR-013); feedback is training evidence only.
- **VI. No LMS orientation**: assignment is a few steps for an organisation manager (SC-003); the mentor's view needs no navigation of individual courses. Proven only by SC-005.
- **VII. Standardisation**: one mentor role for every partner.
- **IX. Flat cost, field-ready**: core capability, no paid service; view and messaging work in the Moodle app.
- **X. Traceable and verified**: cites #11; cross-course visibility and its scoping verified on the temporary 5.2.3+ instance with test accounts; real mentors and managers before done (SC-005). No new recurring operation.
- **Platform & Delivery**: core user-level role first; one instance; no hard-coded host.

## Dependencies

- 001-site-config-as-code: applies the mentor role and relies on its messaging baseline (#19).
- 002-org-structure-cohorts: organisation managers and their scope.
- 004-progress-reporting: consolidated mentor reporting builds on this view.
- 006-learning-pathways and 012-assignments-peer-review: host the learner work a mentor responds to (story 4).
- 008-admin-tooling: bulk assignment of mentors.
