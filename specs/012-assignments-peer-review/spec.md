# Feature Specification: Assignments and peer review in courses

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver REQUIREMENTS.md row #22 (Assignments + peer review, Pref) and the in-course discussion part of row #10 (Peer-to-peer interaction, Must). Learners submit practical work, mentors give feedback against a rubric, and learners review each other's work within their own cohort. Because courses are authored in markdown and published one way, an assignment's brief, rubric and model answer must be authored in the course package and published — a change to the course content model, the publisher and the disclosure boundary (rubric grading notes and model answers are mentor-only). Under constitution X that needs an approved design before any build."

## Clarifications

### Session 2026-10-01

- Q: When peers are matched for review, limited to the learner's cohort or anywhere in their organisation? → A: Cohort first, falling back to others in the same organisation when the cohort can't supply enough reviewers; never across organisations.
- Q: When a course includes an assignment, must it be completed before the course counts as complete? → A: The author chooses per assignment: optional (never affects completion) or required, where required means submitted and given mentor feedback, with no pass mark.
- Q: In peer review, do learners see who wrote the work they review, and who reviewed theirs? → A: Anonymous both ways between peers; the mentor sees both names.
- Q: Does the in-course discussion space wait on the assignment design gate, and does it cover legacy courses? → A: Independent of the design gate; every course published to Moodle gets one, backfilled courses included.
- Q: Where is the decision to share a course's discussion across organisations recorded? → A: Per course in the repo's Moodle site declaration, defaulting to separated by organisation; only the maintainer changes it.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The maintainer approves how assignments are authored and published (Priority: P1)

Before any course gains an assignment, the maintainer receives a written design for how an assignment brief, its rubric, grading notes and any model answer are written in the course package, which parts a learner may see, how they pass through the production stages and how they are published. The maintainer approves one design, and nothing on the content side is built until that approval is recorded.

**Why this priority**: Adding a new kind of course file touches the course layout, stage detection, the package check, the review sites and the publisher, and it adds new material that must never reach a learner. Constitution X requires an approved design first. Every other story depends on it.

**Independent Test**: The design exists, answers every question in FR-001 to FR-008, names a recommended option and the rejected alternatives, and carries the maintainer's recorded decision and date.

**Acceptance Scenarios**:

1. **Given** the design, **When** the maintainer reads it, **Then** it compares at least two ways of holding mentor-only material (for example a separate mentor-only file beside the brief, or a marked section inside it) on disclosure safety, raw readability, contributor simplicity and publisher impact.
2. **Given** the design, **When** it defines what is mentor-only, **Then** grading notes and model answers are mentor-only by default, and any part shown to learners (such as rubric criteria) is named explicitly.
3. **Given** the design, **When** it describes disclosure, **Then** the new rules are added to the one existing disclosure definition, and an assignment whose mentor-only part cannot be cleanly separated is withheld whole.
4. **Given** the design is not yet approved, **When** an author asks to add an assignment to a course, **Then** they are told it waits on the design, and no assignment file is committed.

---

### User Story 2 - A learner submits work and gets mentor feedback (Priority: P1)

After the design is approved and built, a learner in a published course opens an assignment, reads the brief, prepares their work (offline if need be) and submits it. Their mentor reviews it against the rubric and returns feedback the learner can read in the web interface or the app.

**Why this priority**: Practical work reviewed by a mentor is the core of row #22. It also carries the mentor relationship INTENT puts at the centre of the training.

**Independent Test**: Publish a test course with one assignment to the temporary instance. As a test learner, submit; as their test mentor, give rubric feedback. Confirm the learner sees the feedback, and that no learner-visible page contains the grading notes or model answer.

**Acceptance Scenarios**:

1. **Given** a published course with an assignment, **When** a learner opens it, **Then** they see the brief and the learner-visible criteria, and nothing marked mentor-only.
2. **Given** a learner in the Moodle app, **When** they prepare a submission offline, **Then** it is kept and sent when they reconnect, or the brief says plainly that the task must be submitted online.
3. **Given** a submission, **When** the learner's mentor opens it, **Then** they see the rubric with its grading notes and model answer, and can return feedback.
4. **Given** the course is republished after the brief changes, **When** the learner looks again, **Then** the brief is updated in place and their earlier submission and feedback are still there.
5. **Given** a course is published, **When** its payload is checked before leaving the machine, **Then** the check fails if any mentor-only text appears in learner-visible content.

---

### User Story 3 - Learners review each other's work within their cohort (Priority: P2)

For an assignment set up for peer review, each learner submits, then assesses a few peers' submissions using the rubric criteria, and receives peers' comments on their own. Peers are drawn from the learner's own cohort first, and from elsewhere in the same organisation only when the cohort is too small; never from another organisation.

**Why this priority**: Structured peer assessment is the second half of row #22 and serves "learning continues beyond a course" through learners teaching each other. It comes after mentor feedback because it needs cohorts of enough learners at once.

**Independent Test**: Publish a test peer-review assignment. With four test learners across two test organisations, confirm each assesses only peers in their own organisation and receives their comments.

**Acceptance Scenarios**:

1. **Given** a peer-review assignment, **When** peers are allocated, **Then** every reviewer and author belong to the same organisation, and reviewers come from the author's own cohort whenever that cohort can supply enough of them.
2. **Given** a learner assessing a peer, **When** they open the assessment form, **Then** they see the learner-visible criteria and never the grading notes or model answer, and they are not shown the author's name.
3. **Given** a learner reading peer comments on their own work, **When** they open them, **Then** the reviewers' names are not shown, while their mentor can see who wrote each review.
4. **Given** too few learners submit for allocation, **When** the phase closes, **Then** the mentor can review instead, and no learner is left without feedback.
5. **Given** a peer-review task, **When** its criteria are written, **Then** no criterion asks a peer to judge whether minority-language text is correct.

---

### User Story 4 - Learners discuss a course with each other inside it (Priority: P3)

Every published course has a place where its learners can ask questions and help each other, visible to that course's learners and mentors in their own organisation.

**Why this priority**: This is the in-course part of row #10. Community beyond courses is spec 005. The in-course space is simpler and valuable, but not as central as submitted work. It adds no mentor-only material and no change to how course content is authored, so it is **not** behind the US1 design gate and may ship before it.

**Independent Test**: Publish a test course; as test learners in one organisation, post and reply; confirm a learner in another organisation cannot read the thread.

**Acceptance Scenarios**:

1. **Given** a published course, **When** a learner opens it, **Then** one clearly named discussion space is there, with no setup by the learner.
2. **Given** two organisations in one course, **When** a learner in one posts, **Then** learners in the other do not see it unless the site declaration marks that course's discussion as shared across organisations.
3. **Given** someone changes a course's discussion sharing by hand in Moodle, **When** the site declaration is checked for drift, **Then** the difference is reported.
4. **Given** a course is republished, **When** the discussion already has posts, **Then** the posts remain untouched.

---

### Edge Cases

- An assignment file exists but its mentor-only section cannot be cleanly separated: the whole assignment is withheld and the publish reports why.
- An author puts a model answer in the learner brief by mistake: the pre-publish check catches it because the rule is defined once and checked positively.
- A course is republished after learners have submitted: submissions, grades, feedback and discussion posts are never overwritten or deleted by the publish.
- An assignment is removed from the source: the publish does not silently delete learners' submissions; the design must say what happens instead.
- A submission contains real project data, for example a screenshot of a translation team's files: it stays in Moodle and is visible only to the learner, their mentor and entitled managers (and peers, for peer review, within the organisation).
- Large attachments on low bandwidth: the brief states the expected size and format, and a text answer is always acceptable where the task allows it.
- A peer submission identifies its author anyway (a name in a screenshot, a file path): anonymity is best-effort for content the learner supplies. The brief warns against it, and the mentor can withdraw the submission from allocation.
- A required assignment is submitted but the mentor has not yet given feedback: the course stays incomplete for that learner, and the submission awaiting feedback is visible to the mentor and in organisation reports (spec 004).
- A mentor supports learners in several organisations: they grade all their mentees, and peers are still kept within each organisation.
- A cohort is too small for peer allocation: reviewers are drawn from other cohorts in the same organisation; if the organisation is also too small, the mentor reviews (FR-013).

## Requirements *(mandatory)*

### Functional Requirements

**Design gate (before any build)**

- **FR-001**: An assignment and peer-review content design MUST be written and approved by the maintainer before any assignment file is committed or any layout, stage-detection, package-check, review-site or publisher change for it is built. The in-course discussion space (FR-015) is outside this gate.
- **FR-002**: The design MUST define how an assignment brief, rubric criteria, grading notes and model answer are written as plain, raw-readable markdown in the course package.
- **FR-003**: The design MUST name exactly which parts are learner-visible and which are mentor-only. Grading notes and model answers are mentor-only by default.
- **FR-004**: The design MUST add its disclosure rules to the single existing disclosure definition, not a second one, and MUST fail closed: an assignment whose mentor-only content cannot be cleanly separated is withheld whole.
- **FR-005**: The design MUST state how assignments pass through the production stages: authored at draft, checked for alignment with the design objectives, fact-checked and reviewed with the rest of the package.
- **FR-006**: The design MUST state how an assignment is identified in Moodle so republishing updates it rather than duplicating it, consistent with the existing identity scheme.
- **FR-007**: The design MUST state how the reviewer view and the learner view show assignments, keeping mentor-only parts out of the learner view.
- **FR-008**: The design MUST state that a republish never overwrites or deletes learner submissions, grades, feedback or discussion posts.
- **FR-008a**: The design MUST define how an author declares, per assignment in the course package, whether it is **optional** or **required for completion**, and which of the two applies when the author declares nothing.

**Behaviour once built**

- **FR-009**: Learners MUST be able to read a brief and submit work from the web and the Moodle app, using core Moodle capability where it exists.
- **FR-010**: Mentors MUST be able to assess their mentees' submissions against the rubric, with grading notes and model answer visible to them, and return feedback the learner can read.
- **FR-011**: Peer-review allocation MUST draw reviewers from the author's own cohort first, MAY fall back to other learners in the same organisation only when the cohort cannot supply enough reviewers, and MUST NEVER allocate across organisations.
- **FR-012**: Peer reviewers MUST see only the learner-visible criteria.
- **FR-012a**: Peer review MUST be anonymous in both directions between learners: a reviewer is not shown the author's name and an author is not shown their reviewers' names. The learner's mentor MUST see both. Briefs for peer-review tasks MUST remind learners not to put their name in the submission itself.
- **FR-013**: When peer allocation cannot give a learner reviewers, the mentor MUST be able to review instead.
- **FR-014**: No assignment or peer criterion may require judging whether minority-language text is correct. The competence assessed is diagnosing the tool, the data and the workflow.
- **FR-015**: Every course published to Moodle, backfilled courses included, MUST offer one in-course discussion space, created by the publish with no authoring step, scoped so that learners in different organisations do not see each other's posts unless the course is deliberately shared. Sharing is declared per course in the repo's Moodle site declaration, defaults to separated, is changed only by the maintainer, and is never set in course frontmatter or only by hand in Moodle.
- **FR-016**: The pre-publish payload check MUST fail if any mentor-only assignment text appears in learner-visible content. There is no override.
- **FR-017**: Assignment and discussion settings (defaults, peer-review phases, group scoping) MUST be applied from the repo's configuration or the publish, never only by hand.
- **FR-018**: Submissions, grades, feedback and posts MUST remain in Moodle only and be exportable with other learner data.
- **FR-019**: An optional assignment MUST NOT affect course completion. A required assignment MUST count as complete once the learner has submitted and their mentor (or, for peer review, the fallback reviewer under FR-013) has returned feedback. No grade threshold may gate completion.

### Key Entities

- **Assignment brief**: the learner-visible task, with expected output, size, whether it can be done offline, and whether it is optional or required for course completion.
- **Rubric**: assessment criteria, each with learner-visible wording and mentor-only grading notes.
- **Model answer**: an example of good work; mentor-only.
- **Submission, feedback, peer assessment**: learner data held in Moodle only.
- **Assignment design decision**: the approved record of how the above are authored, staged, disclosed and published.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The design is approved or rejected, with the decision recorded, before any assignment file exists in the repo. Zero assignment files are committed before that date.
- **SC-002**: Across a test publish, 0 occurrences of grading notes or model-answer text appear in any learner-visible page, and a deliberately planted leak is caught by the pre-publish check 100% of the time.
- **SC-003**: A republish after submissions leaves 100% of test submissions, grades, feedback and posts intact.
- **SC-004**: In a two-organisation test, 0 peer allocations and 0 discussion posts cross organisation boundaries.
- **SC-005**: At least 2 of 3 test partner learners submit an assignment and find their feedback unaided, including once from the Android app.

## Assumptions

- Core Moodle assignments, rubrics and structured peer assessment cover the needed behaviour. The exact activities are a plan decision, verified on 5.2.3+ first.
- The existing mentor guide remains mentor-only. The design may choose to hold grading notes there, beside the brief, or in a marked section, but it must not weaken the mentor guide's exclusion.
- Assignments are optional in a course. No existing course must gain one, and backfilled courses gain no assignments. (They do gain the in-course discussion space, FR-015.)
- Offline submission support in the Moodle app varies by submission type. Where it is not supported, the brief says so rather than hiding the limitation.
- Mentor assignment to learners comes from spec 003. Organisation and cohort scoping comes from spec 002.
- Grades here are training evidence for the course only. They are not a CBC level and are never presented as one.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 22 | Assignments + peer review | Pref | Approved content design, then mentor-assessed assignments and organisation-scoped peer review, authored in markdown and published. |
| 10 | Peer-to-peer interaction | Must | The in-course part: one discussion space per published course, scoped by organisation. Cross-course community is spec 005. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: Briefs and rubrics are authored in the repo and published one way. Submissions and posts never come back to the repo. The disclosure rules stay in the single existing definition (FR-004), and stage detection stays in `course_stage.py`.
- **II. Portability**: Assignment content is plain markdown. The platform-neutral payload carries it, and only the Moodle-facing half knows how Moodle stores it. Settings are applied from the repo (FR-017), and learner work stays exportable (FR-018).
- **III. Public repo, private people**: Submissions, grades, feedback and posts are learner data held only in Moodle. Tests use test accounts and fixtures with no real work in them.
- **IV. Disclosure (NON-NEGOTIABLE)**: Grading notes and model answers are mentor-only by default. The pre-publish check fails closed with no override (FR-016). An unseparable assignment is withheld whole (FR-004).
- **V. CBC fidelity**: Grades are course training evidence, never a CBC level, and never a completion threshold (FR-019).
- **VI. No LMS orientation**: Learners submit and find feedback unaided (SC-005). Authors write markdown and never touch Moodle.
- **VII. One shape, gated stages**: Assignments go through the same stages and independent review as the rest of the package (FR-005). No per-course special structure.
- **VIII. Humility about language data**: No task or criterion asks anyone to judge minority-language text (FR-014).
- **IX. Flat cost, field-ready**: Core capability, so no new cost. Offline submission is used where the app supports it and stated where it does not. Attachment size is bounded in the brief.
- **X. Traceable and verified**: Rows #22 and #10 are cited. The content-model change is design-gated. Assignment, rubric, peer-review and app offline behaviour are verified on the temporary 5.2.3+ instance before planning depends on them. SC-005 needs 2–3 real partner learners, for example at a stage-7 pilot. No new recurring operations are added beyond the site itself, which spec 015 covers.
- **Platform & delivery**: Core first. One instance and one role set. The server comes from `MOODLE_URL`.

## Dependencies

- **001-site-config-as-code**: the configuration assignment and discussion defaults are applied through.
- **002-org-structure-cohorts**: the organisations and cohorts that scope peer allocation and discussion.
- **003-mentor-role**: which mentor assesses which learner.
- **004-progress-reporting**: assignment completion and grades appearing in organisation-scoped reports.
- **005-community-space**: the boundary between in-course discussion (here) and community beyond courses.
- **009-low-bandwidth-delivery**: attachment and offline expectations.
