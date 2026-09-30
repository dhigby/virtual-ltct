# Feature Specification: Simple Administration Tooling

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver REQUIREMENTS.md row #14, Simple administration (Must, M, Ongoing): a small team — and the organisation and cohort managers of partner organisations — can create learner accounts in bulk, put learners into cohorts, enrol cohorts into courses and keep that up to date, without a dedicated LMS administrator. Scripted helpers run against Moodle from the training-system half of this repo and never write learner data into git; because GitDoc auto-commit pushes the working tree, their inputs and outputs must live outside it. Row #14 is a 'simple' row: it is not done until 2–3 real organisation or cohort managers have used it and their findings are recorded."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Bring a new intake of learners onto the system (Priority: P1)

A member of the team has a list of new learners from a partner organisation — names, email addresses, organisation, perhaps country. They run one guided step that shows them exactly what will happen (which accounts will be created, which already exist, which cohort each will join), confirm it, and the learners are created and placed in the right cohort. The list stays on their own machine, outside the repository, and is never copied into it.

**Why this priority**: Without this, every learner is typed in by hand, and "a small team can run it" (INTENT B.4) is false from the first intake. It is also the path most exposed to Principle III, so it must be right first.

**Independent Test**: With a list of fictitious test learners held outside the working tree, preview and then apply an intake against the temporary instance; confirm the accounts and cohort memberships exist, that re-running changes nothing, and that nothing new appears in the repo.

**Acceptance Scenarios**:

1. **Given** a list of learners held outside the repo, **When** the operator previews the intake, **Then** they see each learner's outcome (new, already exists, would change, rejected with reason) and nothing is sent to Moodle.
2. **Given** a confirmed preview, **When** the intake is applied, **Then** new accounts are created, existing ones are matched rather than duplicated, and each learner is in the named cohort.
3. **Given** an intake already applied, **When** the same list is applied again, **Then** no account or membership is created or changed a second time.
4. **Given** an input list located inside the repository working tree, **When** the operator tries to use it, **Then** the tooling refuses and says to move the file out of the repository first.

---

### User Story 2 - Enrol a cohort into a course or pathway (Priority: P1)

A manager wants everyone in a cohort to take a course, or every course on a pathway. They enrol the cohort once; learners added to the cohort later are enrolled automatically, and learners removed lose access to that enrolment without losing their history.

**Why this priority**: Enrolment by cohort is what keeps administration proportional to the number of intakes, not the number of learners. Equal to P1 because an intake with no enrolment gives a learner nothing to do.

**Independent Test**: Enrol a test cohort into one published course; add and remove a test learner from the cohort and confirm their enrolment follows, and that a removed learner's completed work is still in Moodle.

**Acceptance Scenarios**:

1. **Given** a cohort and a published course, **When** the manager enrols the cohort, **Then** every current member is enrolled as a learner.
2. **Given** a cohort already enrolled, **When** a new member joins the cohort, **Then** they are enrolled with no further step.
3. **Given** a learner removed from a cohort, **When** their enrolment ends, **Then** their completions and attempts remain in Moodle and are not deleted.

---

### User Story 3 - Routine changes without an LMS administrator (Priority: P2)

Over months, learners change: someone moves organisation, leaves, needs their account suspended, or joins a second cohort. A team member makes these changes through the same previewed, confirmed step, and can see a plain summary of who is in which cohort for their own organisation — on screen, not saved into the repo.

**Why this priority**: Row #14 is marked Ongoing; the recurring work, not the first intake, is what exhausts a small team.

**Independent Test**: Suspend, move and re-cohort test learners using the tooling alone, then view the cohort summary; confirm each change took effect and no output file was written into the working tree.

**Acceptance Scenarios**:

1. **Given** a learner who has left, **When** the operator suspends them, **Then** they can no longer log in and their records remain.
2. **Given** a learner moving between cohorts, **When** the change is applied, **Then** their enrolments follow the new cohort and their history is kept.
3. **Given** a request for a cohort summary, **When** it is produced, **Then** it is shown to the operator or saved only to a location outside the repository.

---

### User Story 4 - A partner's own manager does it themselves (Priority: P3)

An organisation or cohort manager from a partner organisation brings on their own learners and enrols their own cohorts, within their organisation only, without asking our team — and cannot see or change anyone in another organisation.

**Why this priority**: INTENT lists this as a need of organisation and cohort managers, but also as an open question: whether a partner may enrol its own learners without us. [NEEDS CLARIFICATION: May partner organisation managers create accounts and enrol their own learners without our team, or do they request it and our team applies it? This decides whether US4 is in scope for this spec; it is the same open question as 002-org-structure-cohorts FR-013 and should be answered once for both.]

**Independent Test**: As a test organisation manager scoped to one organisation, add and enrol test learners; confirm they cannot see or affect learners, cohorts or courses in a second test organisation.

**Acceptance Scenarios**:

1. **Given** a manager scoped to organisation A, **When** they apply an intake, **Then** only organisation A's cohorts can be targeted.
2. **Given** a manager scoped to organisation A, **When** they look for learners of organisation B, **Then** none are visible.

---

### Edge Cases

- A list row with a malformed or duplicate email address: reported as rejected in the preview, never half-created.
- A learner who already exists under a different organisation: matched, not duplicated, and flagged for a human decision rather than silently moved.
- An intake interrupted part-way (network loss on a low-bandwidth link): re-running completes it without duplicates.
- A cohort or course named in the list that does not exist: the whole intake is refused in preview, naming the missing item.
- An operator who saves the list, a report or a log inside the repo folder by habit: the tooling refuses, because auto-commit would publish it within minutes.
- A credential missing or wrong: the tooling stops before any change and says which setting is absent, never printing its value.
- A request to delete a learner's data entirely: out of scope until the data-protection position is decided; the tooling must not claim to do it.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Operators MUST be able to create learner accounts in bulk from a list they hold, and place each learner in a named cohort, in one step.
- **FR-002**: Every change MUST be previewable first, showing per learner what would happen, with nothing sent to Moodle until the operator confirms.
- **FR-003**: Applying the same intake or change twice MUST leave Moodle exactly as applying it once did; existing accounts are matched, never duplicated.
- **FR-004**: The tooling MUST refuse any input, output, report or log location inside the repository working tree, and MUST NOT write learner data into the repository under any circumstance.
- **FR-005**: Operators MUST be able to enrol a cohort into a course, or into every course of a pathway, such that later cohort changes carry through automatically.
- **FR-006**: Operators MUST be able to suspend a learner, move a learner between cohorts and add a learner to a further cohort; none of these MAY delete a learner's completions, attempts or other history.
- **FR-007**: Operators MUST be able to see a summary of cohort membership for the organisations they are responsible for, shown on screen or saved only outside the repository.
- **FR-008**: Every action MUST be confined to the organisations the operator is responsible for, using the organisation structure and category-scoped manager roles of 002; the same single role set applies to every partner.
- **FR-009**: Credentials and the server address MUST come from the environment only, and MUST NOT be written, echoed or logged into the repository; the tooling uses a credential distinct from the publishing credential, with only the permissions administration needs.
- **FR-010**: The tooling MUST use core Moodle capability where it exists (for example its own bulk user upload and cohort enrolment) and add helpers only where core leaves the job hard for a small team.
- **FR-011**: A team member MUST be able to run the tooling without git knowledge or LMS administration experience, guided through each step in plain language.
- **FR-012**: The tooling MUST NOT record or change any CBC level; it handles accounts, cohorts and enrolments only.
- **FR-013**: Automated tests and committed examples MUST use only obviously fictitious identities on a reserved example domain, never real learner data.
- **FR-014**: The feature MUST NOT be marked done until 2–3 real organisation or cohort managers have used it and their findings are recorded, de-identified, with each finding resolved or consciously accepted.

### Key Entities

- **Intake list**: the operator's list of learners to bring on (name, email, organisation, optional profile details, target cohort). Held outside the repository; never committed.
- **Cohort membership**: which learners belong to which cohort. Lives only in Moodle. Cohort *definitions* are configuration owned by 002; *memberships* are learner data.
- **Cohort enrolment**: the link from a cohort to a course or pathway that keeps enrolment in step with membership.
- **Change preview**: the per-learner account of what an action would do, shown before confirmation.
- **Manager finding**: one de-identified observation from a real manager's use of the tooling.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A team member brings on an intake of 30 learners, placed in a cohort and enrolled in a course, in under 15 minutes, excluding time spent preparing the list.
- **SC-002**: Re-applying any intake or change produces zero new accounts, memberships or enrolments.
- **SC-003**: Across the verification run and the manager pilot, zero files containing learner data appear in the repository working tree or its history.
- **SC-004**: At least 2 of the 2–3 real managers complete an intake and a cohort enrolment unaided on first attempt, and none needs to be taught Moodle's administration screens.
- **SC-005**: An operator scoped to one organisation can see or change zero learners, cohorts or enrolments of another organisation.
- **SC-006**: Every manager finding is recorded and marked resolved or accepted before the row is marked done.

## Assumptions

- Operators are our team and, subject to the clarification in US4, partner organisation and cohort managers; none is expected to be an LMS administrator.
- The operator prepares the intake list from their organisation's own records; the tooling does not collect learner data from anywhere else.
- Consent, a privacy notice and "delete my data" belong to the undecided data-protection position (INTENT open questions); this spec neither implements nor precludes them.
- Account matching is by email address, as the one identifier every partner can supply.
- Learners sign in with an account and password Moodle issues; single sign-on is out of scope.
- Everything is built and verified on the temporary instance with test accounts; real intakes wait for the dedicated VPS (015).
- Manager findings are recorded without names, emails or other identifying detail.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 14 | Simple administration | Must | Previewed, repeatable bulk intake into cohorts; cohort enrolment into courses and pathways; routine suspend and move; scoped cohort summaries; guidance a non-administrator can follow; and the 2–3 real-manager test that makes it done. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: Nothing flows Moodle → repo; summaries go to the screen or outside the tree. Cohort definitions stay configuration in the repo (002); memberships stay in Moodle.
- **II. Portability / config-as-code**: Any role, capability or setting the tooling needs is applied from `moodle/`, not clicked in. Learner data remains exportable through Moodle (001, 004).
- **III. Public repo, private people (NON-NEGOTIABLE)**: Inputs, outputs, reports and logs are refused inside the working tree (FR-004) because auto-commit would push them; credentials come from the environment only (FR-009); fixtures are fictitious (FR-013).
- **V. CBC fidelity**: The tooling never records or changes a CBC level (FR-012).
- **VI. No git, no LMS orientation**: A team member runs it without git or LMS administration knowledge (FR-011, SC-004).
- **VII. One shape**: One role set and one process for every partner; no per-partner variant (FR-008).
- **IX. Flat cost**: No paid plugin or service; resilient to interrupted low-bandwidth runs.
- **X. Traceable and verified**: Cites row #14; every Moodle capability the plan relies on is verified on the temporary 5.2.3+ instance with test accounts; not done until 2–3 real managers have used it (FR-014). The recurring burden is administration time, carried by our team and, if US4 is confirmed, partner managers for their own people; server operation is not claimed as covered while the operator is undecided (015).
- **Platform & Delivery**: Core first (FR-010); Moodle core never modified; any plugin pinned; one instance for all partners; server always from `MOODLE_URL`, never hard-coded.

## Dependencies

- **001-site-config-as-code** — applies the roles, capabilities and settings the tooling relies on.
- **002-org-structure-cohorts** — organisations, categories, cohort definitions, profile fields and category-scoped managers the tooling acts within.
- **006-learning-pathways** — the pathways a cohort may be enrolled into (FR-005, pathway half).
- **004-progress-reporting** — the place managers see progress; this spec does not duplicate it.
- **015-production-hosting-ops** — real intakes wait for the production server and its operator.
