# Feature Specification: Partner organisations, cohorts and profiles

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Organise every partner we host inside the one Moodle site: a course category per organisation, site and organisation cohorts, custom profile fields (organisation, country, role in the work, expertise) with visibility controls, cohorts that fill themselves from those profile fields, groups inside courses, and an organisation manager role scoped to its own organisation. One structure and one role set for every partner. Delivers moodle/REQUIREMENTS.md rows #8 (cohorts / groups), #15 (scales across orgs) and #18 (persistent profiles)."

## Clarifications

### Session 2026-10-01

Moodle categories separate courses, not people: every account belongs to the whole site. Three core limits, confirmed in `MOODLE_502_STABLE` source, decide what an organisation manager can do without our own code. A category cohort cannot be enrolled into a course outside its category. The screen for adding someone to a cohort by hand searches every user on the site. A locked profile field can be edited only with `moodle/user:update` at site level, which reaches every user.

- Q: How much do organisation managers do for themselves in this spec? → A: Core only. The site team (later with spec 008's tooling) creates accounts, sets the organisation field and enrols each organisation into shared courses, with one group per organisation. Managers see and follow only their own people inside those courses. Manager self-service would need our own code and is not in this spec.
- Q: Who creates new learner accounts (FR-013)? → A: The site team only. Managers never create accounts, because `moodle/user:create` cannot be scoped to one organisation.
- Q: A learner who moves organisation, or a manager the site team removes, keeps a suspended enrolment and their old group, so the old organisation's manager could still open their profile. How is that closed? → A: Cohort sync suspends and removes roles (`enrol_cohort/unenrolaction = 3`), so a removed manager loses the role and history is kept. A small `local_ltuse` callback on core's profile-view hook refuses an organisation manager the profile of anyone outside the organisations they manage. It is our own code, through a supported extension point, for this one gap core cannot close.
- Q: In a course both organisations share, a learner who moved from A to B keeps a suspended A enrolment and their place in group A, beside their active B enrolment, so A's manager still sees them on the participants list. Is more code wanted to close that? → A: No. It is a known limit: the site team deletes the learner's old, suspended cohort-sync enrolment in that course by hand. That keeps their history, because Moodle clears grades and completion only when a learner's last enrolment in a course goes. In a course only A is enrolled in, the suspended learner is already hidden from the list, and the profile hook refuses their profile.
- Q: Organisation categories show their course names to anyone signed in. Does FR-007 forbid that? → A: No. Separation covers people: learners, cohorts, enrolments and profiles. A category and its course names hold no personal data, and every course comes from the public repo, so category pages stay visible.
- Q: Should a cohort be created for every country, so learners join one from their country field? → A: No. Nobody knows yet where cohorts will be needed, and nothing says a cohort's members will share a country. So no country cohorts are created in advance. Country stays an ordinary profile field. If a real need for a country cohort appears, it is added then, by a reviewed change.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Onboard a partner organisation (Priority: P1)

The maintainer adds a new partner organisation to the repo's organisation list and applies it. The organisation gets its own course category, its own organisation cohort, and a place for its managers — the same shape every other partner has. No part of the setup is done by hand in the admin interface.

**Why this priority**: Every other organisation-scoped feature (manager scope, progress reports, pathways per organisation) needs the organisation to exist first. INTENT B.1 requires one instance serving every partner, separated inside it.

**Independent Test**: Add a test organisation to the declaration, apply it to the temporary instance, and confirm the category and cohort exist with the standard shape; apply again and confirm nothing changes.

**Acceptance Scenarios**:

1. **Given** an organisation added to the declaration, **When** it is applied, **Then** the organisation's category and organisation cohort exist and are identical in shape to every other partner's.
2. **Given** an organisation already applied, **When** the declaration is applied again, **Then** nothing is duplicated.
3. **Given** an organisation removed from the declaration, **When** it is applied, **Then** nothing is deleted automatically; the drift check reports it as no longer declared, so learner history is never lost by accident.

---

### User Story 2 - An organisation manager sees only their own people (Priority: P2)

A manager at a partner organisation signs in and can see and follow their organisation's learners in every course their organisation is enrolled in, including the shared, published curriculum courses, without seeing or affecting any other organisation's people. The site team does the enrolling (Clarifications 2026-10-01).

**Why this priority**: INTENT names organisation and cohort managers as users who must work "without seeing anyone else's". Separation is the condition for putting several partners on one site at all.

**Independent Test**: With two test organisations enrolled in one shared course and a test manager in each, confirm each manager can list and open their own test learners and cannot find, view or enrol the other organisation's.

**Acceptance Scenarios**:

1. **Given** a manager of organisation A, **When** they look for learners, **Then** they see organisation A's learners only.
2. **Given** organisation A's cohort enrolled in a shared curriculum course by the site team, **When** A's manager opens that course, **Then** they see A's learners in A's group and no other organisation's learners or enrolments.
3. **Given** a manager of organisation A, **When** they try to open a learner profile or cohort of organisation B, including a learner who has just moved from A to B, **Then** access is refused. Category pages and course names are public (Clarifications 2026-10-01).
4. **Given** two organisations, **When** their managers are compared, **Then** both hold the same role with the same permissions; there is no per-partner role.

---

### User Story 3 - Learners join the right cohorts automatically (Priority: P3)

When a learner's organisation is recorded on their profile, they join that organisation's cohort without anyone adding them by hand, and cohort-based enrolments follow.

**Why this priority**: It is what makes "a small team can run programs" true at hundreds and then thousands of learners, but manual cohort membership works in the meantime.

**Independent Test**: Set a test learner's organisation field; confirm they appear in that organisation's cohort and are enrolled in any course synchronised to it; change the field and confirm membership follows.

**Acceptance Scenarios**:

1. **Given** a learner whose organisation field is set to A, **When** membership is refreshed, **Then** they are in organisation A's cohort.
2. **Given** that learner's organisation changes to B, **When** membership is refreshed, **Then** they leave A's cohort and join B's, and their course history is kept.
3. **Given** a learner, **When** they edit their own profile, **Then** they cannot change their organisation field; only the site team can.

---

### User Story 4 - Persistent profiles that say who a learner is (Priority: P4)

A learner's profile carries the few facts that matter across courses and years — organisation, country, role in the work, areas of expertise — each with a visibility suited to it, so mentors and peers can find the right people and the learner controls what is public.

**Why this priority**: Profiles feed cohorts, mentoring and community, but none of those is blocked on the richer fields.

**Independent Test**: Create a test learner, fill the fields, and view the profile as another learner, as their organisation's manager and as an administrator; each sees what the field's visibility allows.

**Acceptance Scenarios**:

1. **Given** the profile fields, **When** a learner fills them, **Then** they persist across every course and after every course ends.
2. **Given** a field marked visible only to the learner and staff, **When** another learner views the profile, **Then** that field is hidden.

---

### Edge Cases

- A learner belongs to two organisations (e.g. seconded): they have one organisation of record, and only that organisation's manager can open their profile. A second affiliation can be an additional cohort for enrolment, but it gives the second organisation's manager no reach. Fuller support for second affiliations is an open question for after SC-004.
- A learner has no organisation (an independent consultant): they belong to a declared "independent" organisation with the same shape as any other, so nobody sits outside the structure.
- An organisation manager is also a learner: their own learning is unaffected, except that the profile hook limits whose profiles they can open to the organisations they manage and to staff (Clarifications 2026-10-01).
- A partner asks for its own branding or a special role: the answer is no by default (constitution VII); a partner needing real isolation is a candidate for its own Moodle, which is a separate publish target.
- An organisation's name changes: the change is made in the declaration and applied; identity is a stable key, not the display name, so nothing is duplicated.
- Profile field values are personal data: they live only in Moodle; the repo declares the fields and their allowed values, never any learner's value.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The repo MUST declare the list of partner organisations we host, each with a stable key and display name, and apply it through spec 001's mechanism.
- **FR-002**: Each declared organisation MUST receive the same structure: one course category, one organisation cohort and one managers cohort, created only if absent.
- **FR-003**: The published curriculum MUST live in a shared category available to every organisation; an organisation's category holds any organisation-specific courses, published from the repo like any other.
- **FR-004**: Removing an organisation from the declaration MUST NOT delete anything; the drift check MUST report it instead.
- **FR-005**: There MUST be exactly one organisation manager role, declared in the repo, used for every partner.
- **FR-006**: An organisation manager MUST be able to see their organisation's learners, and open their profiles, in every course their organisation is enrolled in. Enrolling an organisation in a course is done by the site team (Clarifications 2026-10-01).
- **FR-007**: An organisation manager MUST NOT be able to view, find, enrol or edit any other organisation's learners or cohorts, including a learner who has left their organisation. The one known limit is the shared-course participants list, until the site team removes the old enrolment (Clarifications 2026-10-01). A category page and its course names are not personal data and stay visible (Clarifications 2026-10-01).
- **FR-008**: The site MUST offer custom profile fields for organisation, country, role in the work and areas of expertise, each with a declared visibility.
- **FR-009**: The organisation field MUST be editable only by the site team, never by the learner or an organisation manager.
- **FR-010**: Learners MUST be added to and removed from organisation cohorts automatically from their organisation field, using core Moodle capability where it exists. No country cohorts are created (Clarifications 2026-10-01).
- **FR-011**: Courses MUST support groups, so a cohort or organisation can be worked with separately inside a shared course.
- **FR-012**: Assigning a person to be an organisation's manager MUST happen in Moodle (by an administrator, or later by the tooling in spec 008), never by recording that person in the repo.
- **FR-013**: New learner accounts MUST be created by the site team only; an organisation manager never creates accounts.

### Key Entities

- **Partner organisation**: an organisation whose learners we host; stable key, display name; owns one category and one organisation cohort.
- **Organisation cohort**: the set of learners belonging to one organisation; filled from the organisation profile field.
- **Organisation manager**: a person holding the one organisation manager role over one organisation, by being a member of that organisation's managers cohort.
- **Profile field**: a declared attribute of every learner, with allowed values and a visibility; its values live only in Moodle.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new partner organisation is fully set up from the repo in under 15 minutes of maintainer time, with no admin-interface steps.
- **SC-002**: In a two-organisation test, each manager can reach 100% of their own test learners through the participants list and profiles, and 0% of the other organisation's through any page: people lists, cohorts, enrolments and profiles. A learner moved from one organisation to the other counts as the other organisation's, once the site team has removed their old enrolment in any course both organisations share (Clarifications 2026-10-01).
- **SC-003**: A learner whose organisation field is set appears in the right cohort, and in its synchronised courses, within one scheduled refresh, with no manual step.
- **SC-004**: 2–3 real partner organisation managers, given a small test cohort the site team has enrolled in a shared course, find and follow their own learners there without help, and their findings are recorded, before row #15 is marked done.
- **SC-005**: Every organisation on the site holds an identical role set; a comparison finds zero per-partner roles or permission overrides.

## Assumptions

- Partner organisation names and the category structure are not personal data and may be declared in the public repo; which people belong to or manage an organisation is personal data and never is.
- Country values come from Moodle's own country list; role-in-the-work and expertise values are a short declared list, extended by reviewed change.
- An "independent" organisation holds consultants with no partner organisation.
- Automatic cohort filling from profile fields may need a maintained free plugin if core cannot do it on 5.2; that choice and its verification belong in the plan.
- Real multi-tenancy (per-partner branding and isolation) is out of scope; category, cohort and role separation is enough.
- INTENT's data-protection position (privacy notice, consent, retention) is still open; profile fields are kept to the minimum above until it is settled, and no sensitive categories of data are collected.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 8 | Cohorts / groups | Must | Organisation cohorts, filled from the organisation field; groups in courses; cohort enrolment |
| 15 | Scales across orgs | Must | One category and cohort per organisation, one shared curriculum category, one category-scoped manager role |
| 18 | Persistent profiles | Pref | Organisation, country, role and expertise fields with visibility controls |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: organisations, fields and roles are declared in the repo; nothing about people flows back from Moodle.
- **II. Portability**: categories, cohort definitions, profile fields and the role are applied from `moodle/` via spec 001 and are rebuildable; membership is learner data and moves with the data restore.
- **III. Public repo, private people**: the repo holds organisation names and field definitions only; memberships, manager assignments and field values stay in Moodle (FR-012, assumptions).
- **VI. No LMS orientation**: learners do nothing to join cohorts (FR-010); managers get one role with a small, predictable scope. Proven simple only by SC-004.
- **VII. Standardisation**: one shape and one role set for every partner; special cases are refused by default.
- **IX. Flat cost**: core capability first; any plugin must be free and maintained.
- **X. Traceable and verified**: cites #8, #15, #18; manager separation and automatic cohorts verified on the temporary 5.2.3+ instance with test accounts only; admin simplicity (#15 via #14) needs 2–3 real organisation managers (SC-004). The new recurring operations are maintaining the organisation list and the site team's four steps in research R8: accounts, enrolling an organisation in a course, managers, and removing a moved learner's old enrolment. Spec 008 owns reducing them.
- **Platform & Delivery**: one instance for every partner, separated by category, cohort, role and profile field; no hard-coded host.

## Dependencies

- 001-site-config-as-code: applies the organisations, cohorts, fields and role.
- 008-admin-tooling: bulk account creation, enrolment and manager assignment build on this structure.
- Relied on by 003-mentor-role, 004-progress-reporting (reports scoped per organisation), 006-learning-pathways and 005-community-space.
