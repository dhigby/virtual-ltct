# Feature Specification: Learning Pathways Mapped to CBC

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver role- and competency-based learning pathways mapped to the CBC framework, from `moodle/REQUIREMENTS.md` row #12 (Learning pathways). Courses already declare `competencies` and `target_outcome_level` in their frontmatter; pathways must be derived from that repo data on publish, never hand-built in Moodle. A learner sees where they are and what comes next; mentors and managers see their people's pathway progress. The mechanism (core competency learning plans or the Programs plugin, whose 5.2 support is unconfirmed) is a plan-level research decision."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A learner follows a competency pathway (Priority: P1)

A consultant working toward a competency — say, Keyboards — opens its pathway and sees every published course that trains it, in order of the CBC level each course aims to bring a learner to, which of those they have finished, and which to take next. Where no course yet exists for a level, the pathway says so and links to that competency's page on the public competency site.

**Why this priority**: "A learner can see where they are and what comes next" (INTENT B.3) is the core of the requirement, and competency pathways follow directly from data every course already declares.

**Independent Test**: Publish two test courses declaring the same competency at different target levels; a test learner who has completed the lower one sees it done and the higher one as next.

**Acceptance Scenarios**:

1. **Given** published courses declaring a competency, **When** a learner opens that competency's pathway, **Then** the courses are listed by target level, lowest first, each with its level shown in CBC vocabulary.
2. **Given** a learner has completed one course in the pathway, **When** they view it, **Then** that course shows as completed and the next course is highlighted.
3. **Given** a competency with no published course at some level, **When** a learner views the pathway, **Then** it shows "no course yet" for that level with a link to the competency site page.
4. **Given** a learner completes every course in a pathway, **When** they view it, **Then** it says they have completed the training on the pathway — not that they have reached any CBC level.

---

### User Story 2 - Pathways update themselves when courses are published (Priority: P1)

When a course reaches stage 8 and is published, or is republished with a changed competency list or target level, the affected pathways change on that publish. Nobody edits a pathway in Moodle; if someone does, the next publish puts it back.

**Why this priority**: A pathway built by hand drifts from the courses and from `COVERAGE.md` within weeks, and makes Moodle a second source of truth. Derivation is what makes Story 1 trustworthy.

**Independent Test**: Change a test course's `competencies:` list and republish; the course leaves the old pathway and joins the new one with no admin-UI action.

**Acceptance Scenarios**:

1. **Given** a course is published for the first time, **When** the publish completes, **Then** it appears in the pathway for each competency it declares.
2. **Given** a published course's frontmatter drops a competency, **When** it is republished, **Then** it leaves that competency's pathway.
3. **Given** a pathway was altered by hand in Moodle, **When** the next publish runs, **Then** the pathway matches the repo again.
4. **Given** a course in pilot (stage 7), **When** pathways are viewed, **Then** it does not appear in any pathway.

---

### User Story 3 - A learner follows a role pathway (Priority: P2)

A new translation-team support consultant opens the pathway for their role and sees the set of competencies that role needs, each with its own competency pathway beneath it, and overall progress across them.

**Why this priority**: Role pathways are named in INTENT and are how most learners would think about their training, but they need a role definition that does not yet exist, and competency pathways deliver value first.

**Independent Test**: With one role declared in the repo naming three competencies, a test learner assigned to that role sees the three competency pathways and a combined progress figure.

**Acceptance Scenarios**:

1. **Given** a role pathway declared in the repo, **When** the configuration is applied, **Then** it exists in Moodle, listing its competencies and their courses, with no hand-building.
2. **Given** a learner assigned to a role pathway, **When** they log in, **Then** the pathway is reachable from their landing page.

---

### User Story 4 - A mentor sees a learner's pathway progress (Priority: P2)

A mentor opens an assigned learner's pathway and sees what they have completed and what is next, so their conversations can be about the next step rather than about where the learner is.

**Why this priority**: Mentors are central to the model (INTENT A.6), but the mentor relationship itself belongs to 003; this story adds pathway visibility to it.

**Independent Test**: A test mentor assigned to a test learner (003) sees that learner's pathway progress and no one else's.

**Acceptance Scenarios**:

1. **Given** a mentor assigned to a learner, **When** they view the learner, **Then** they see the learner's pathways and progress through each.
2. **Given** a learner the mentor is not assigned to, **When** the mentor looks for them, **Then** they see nothing of their pathways.

---

### User Story 5 - A manager assigns a pathway and follows their organisation's progress (Priority: P3)

An organisation or cohort manager assigns a role or competency pathway to a cohort, and sees, for their own people only, how far each learner has got.

**Why this priority**: Supports partner-run programmes, but depends on role pathways (Story 3), cohorts (002) and scoped reporting (004).

**Independent Test**: A test manager assigns a pathway to a test cohort; each member sees it on their landing page, and the manager's pathway report lists only that cohort.

**Acceptance Scenarios**:

1. **Given** a manager and a cohort in their organisation, **When** they assign a pathway to the cohort, **Then** every cohort member sees it and new members get it on joining.
2. **Given** that manager, **When** they view pathway progress, **Then** they see only their own organisation's learners.

### Edge Cases

- A course declares several competencies: it appears in each of their pathways, and completing it counts in each.
- Two courses declare the same competency and the same target level: both appear at that level; either may be taken first.
- A course declares a competency not in `competencies.yaml`: CI already fails it (`gen_coverage.py`), so it can never reach a pathway.
- A course is retired or unpublished: it leaves pathways; learners who completed it keep the completion record.
- A legacy course delivered only on Cypher (`cypher:` link, not on Moodle): it is not in pathways until backfilled and published to Moodle; its level shows "no course yet" meanwhile.
- A learner is assigned the same pathway through two cohorts: it appears once.
- A course tagged only `Meta: Uncategorized` (which has no descriptor): it gets a pathway, but no "no course yet" link to the competency site, since there is no page to link to.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST provide one competency pathway for every competency in `competencies.yaml` that at least one published course declares.
- **FR-002**: A competency pathway's contents MUST be derived solely from published courses' frontmatter (`competencies`, `target_outcome_level`), `competencies.yaml` and `outcome-levels.yaml`, and updated by the publish or configuration apply.
- **FR-003**: Courses in a pathway MUST be ordered by `target_outcome_level`, lowest first, and each level MUST be shown in CBC vocabulary only.
- **FR-004**: A level with no published course MUST show "no course yet" and link to the competency's page on the public competency site.
- **FR-005**: Only courses published for delivery (stage 8) on Moodle MUST appear in pathways; pilot courses and courses not on Moodle MUST NOT.
- **FR-006**: Hand changes to a pathway in Moodle MUST be overwritten by the next publish or apply; pathways are generated artefacts.
- **FR-007**: Role pathways MUST be declared in the repo as a named list of competencies, and generated in Moodle from that declaration.
- **FR-008**: Pathways MUST recommend an order without locking courses: a learner may take any course in a pathway at any time.
- **FR-009**: A learner MUST be able to reach their pathways from their landing page and see completed, next and remaining courses without orientation.
- **FR-010**: Pathway progress MUST read course completion as defined by 004.
- **FR-011**: The system MUST NEVER award, record or display a CBC level for a learner. If the chosen mechanism has a rating or proficiency concept, it MUST either be unused or mean "training completed", never a CBC level.
- **FR-012**: Mentors MUST see pathway progress for their assigned learners only (via 003); managers MUST see it for their own scope only (via 002/004).
- **FR-013**: Managers MUST be able to assign a pathway to a cohort within their scope, with new cohort members receiving it automatically.
- **FR-014**: The courses a pathway lists for a competency MUST agree with the courses `COVERAGE.md` lists for it, since both derive from the same frontmatter.
- **FR-015**: Pathways MUST be usable in the Moodle app.

### Key Entities

- **Competency pathway**: every published course declaring one competency, ordered by target level; generated.
- **Role pathway**: a named, repo-declared set of competencies, each carrying its competency pathway.
- **Pathway assignment**: a learner or cohort linked to a pathway; lives in Moodle.
- **Pathway progress**: per learner, which courses in a pathway are complete; read from 004's completion data.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of competency pathways match the repo's frontmatter after each publish, checked by comparing each pathway with `COVERAGE.md`.
- **SC-002**: 0 pathways or pathway entries are created or edited by hand in the admin UI.
- **SC-003**: 2–3 real partner learners can say, from their pathway, what they have finished and which course to take next, without help; findings recorded.
- **SC-004**: 0 learners are shown at a CBC level anywhere in the training system.
- **SC-005**: A course's competency change reaches the pathways on the same publish, with no separate step.

## Assumptions

- Courses have no declared prerequisites today, so pathways recommend order by target level and never lock; hard prerequisites would need a frontmatter addition and are out of scope.
- A pathway lists a course under the level it declares as `target_outcome_level`; the ladder offset (a level names where a learner is) is already accounted for in how courses set that field.
- The mechanism — core competency learning plans or the Programs plugin — is decided in the plan after verifying each on the temporary 5.2.3+ instance; this spec is written so either can satisfy it.
- Which roles exist, and which competencies each needs, is supplied by a human (the maintainer with the department or the CBC program) and recorded in the repo; no role pathway is invented by an agent. Story 3 ships with whatever roles have been supplied, and competency pathways (Stories 1–2) do not wait on it.
- Pathway definitions contain no learner data; assignments and progress live in Moodle only.
- Legacy Cypher-delivered courses join pathways only once backfilled and published to Moodle.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
| --- | --- | --- | --- |
| 12 | Learning pathways | Must | Generated competency pathways and repo-declared role pathways mapped to CBC, with learner, mentor and manager views. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: pathways are generated from frontmatter and repo data; hand edits in Moodle are overwritten (FR-002, FR-006); nothing syncs back; `COVERAGE.md` and pathways agree (FR-014).
- **II. Config as code**: role pathway declarations and all pathway configuration live under the repo and are rebuildable; the mechanism and its plugin, if any, are pinned.
- **III. Public repo, private people (NON-NEGOTIABLE)**: repo holds pathway definitions only; assignments and progress stay in Moodle.
- **IV. Disclosure**: pathways link to courses and the public competency site only.
- **V. CBC fidelity**: competency names verbatim, CBC levels only, no CBC level awarded or recorded (FR-003, FR-011, SC-004).
- **VI. No LMS orientation**: reachable from the landing page, no locks to explain (FR-008, FR-009); tested with real partner learners (SC-003).
- **VII. Standardisation**: every pathway is built the same way from the same data; no per-course or per-partner pathways by hand.
- **IX. Flat cost, field-ready**: core or free plugin, no per-learner cost; usable in the app (FR-015).
- **X. Traceable and verified**: cites row #12; Programs' 5.2 support is unconfirmed and is a research task, not an assumption; whichever mechanism is chosen MUST be verified on the temporary 5.2.3+ instance before the plan depends on it. If it adds work to the publish, that is publisher change needing plan review. No new recurring cost; upgrade upkeep of a plugin, if chosen, falls to the undecided operator (015).
- **Platform & Delivery**: core first; a plugin only if core cannot meet the stories; pinned; one instance; server from `MOODLE_URL`.

## Dependencies

- **001-site-config-as-code** — configuration mechanism.
- **002-org-structure-cohorts** — cohorts and manager scope for assignment.
- **003-mentor-role** — mentor visibility of learners.
- **004-progress-reporting** — course completion, which pathway progress reads.
- **007-learner-experience** — the landing page pathways are reached from.
- **015-production-hosting-ops** — operator for any plugin's upgrades.
