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

### Session 2026-10-02

The maintainer (Doug) reversed the in-course separation of 2026-10-01 (relayed by Matthew; decision record in [spec 011's handoff](../011-events-calendar/handoff.md), B1–B6). Competency courses run across organisations: most mentors will be SIL, and cohorts are small, so walls inside a course cost more than they protect. Organisations stay as the unit of identity and of management, not of separation. The answers below supersede the 2026-10-01 answers on groups, on what managers do, and on moved learners in shared courses. The 2026-10-01 answers on accounts, cohorts, public category pages and country cohorts stand.

- Q: Are organisations separated inside a shared course? → A: No. Shared courses have no organisation groups and course group mode 0, and the publisher stops sending group mode 1. Learners see all their classmates, course leaders see their students, and mentors see and interact with their mentees across organisations. A course or activity may still use groups for its own teaching reasons, but never to separate organisations (B1).
- Q: Are managers still enrolled in shared courses? → A: No. The managers cohort is no longer enrolled in shared courses, so `orgmanager` would not see every organisation's participants there. Managers follow their people through spec 004's per-organisation report and our own "my organisation" page, not through core's per-course reports (B2).
- Q: What may an organisation manager do for their own people? → A: See *and manage* them (B5, changed from "follow only"). Managing means, for members of their own organisation only: assigning and ending mentors (spec 003 Phase B), sending a learner a password-reset link, enrolling them in and unenrolling them from courses, and suspending or reactivating their account. Core cannot scope any of these to one organisation, so each is our own page in `local_ltuse`, authorised by managers-cohort membership on every request. Managers still never create accounts or edit the organisation field (FR-009, FR-013).
- Q: How does a manager see an individual learner, now they are not enrolled with them? → A: Through the learner's profile, which the profile hook opens to the managers of the learner's own organisation, and the "my organisation" page. No per-learner role is added; core's per-learner grade and outline views are not offered to managers (B3).
- Q: How does a learner get into a shared course? → A: Both ways. The site team can still enrol a whole organisation by cohort sync, now with no group. A manager can also enrol individual people of their own organisation from the "my organisation" page.
- Q: How do managers message their people, now they share no course? → A: A manager and each person in their organisation become message contacts automatically, as spec 003 does for mentors. A learner can still block a manager.
- Q: Can an organisation still have a course only for its own people? → A: Yes, as the one standard exception. An organisation-only course is declared in a maintainer-only file in `moodle/site/`, not in course frontmatter. The maintainer approves it and the site team enrols it. It lives in the organisation's category and only that organisation's people are enrolled. Its content stays in the public repo, so "only for its own people" means enrolment, not confidentiality (B4).
- Q: May managers enrol people into pilot courses? → A: No. Pilot enrolment stays with the pilot coordinator at stage 7; managers enrol into published courses and their own organisation's organisation-only courses (Doug, confirmed 2026-10-02).
- Q: Manual enrolment is how spec 004 recognises a pilot learner. How do manager enrolments stay counted as delivery? → A: Managers enrol through a separate "Organisation enrolment" instance that learners cannot use themselves, and 004's delivery condition and the competency coverage count are changed to include it (Doug, confirmed 2026-10-02; research R10).
- Q: Who sees a person's email? → A: Managers see the email of every person in their organisation, protected people included, and a mentor sees their mentee's. Classmates never see a protected person's email. `orgmanager` keeps `moodle/site:viewuseridentity`; the protected case is spec 016's to implement (Doug, 2026-10-02).
- Q: Can production enrol learners before identity protection exists? → A: Not from an organisation that may need protection: until spec 016 is delivered, none of its learners is enrolled in a shared course on production (Doug, confirmed 2026-10-02; research R13).
- Q: Which people may a manager act on? → A: A manager sees every member of their organisation, but the management actions apply to its learners only; staff, mentors and other managers stay with the site team (Doug, confirmed 2026-10-02; research R10).
- Q: Does the organisation field become private in this change? → A: No. Making `ltct_org` private and moving spec 004's report scope from the field to cohort membership are left to spec 016's decision 2. Until then a classmate can see a learner's organisation on their profile.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Onboard a partner organisation (Priority: P1)

The maintainer adds a new partner organisation to the repo's organisation list and applies it. The organisation gets its own course category, its own organisation cohort, and a place for its managers — the same shape every other partner has. No part of the setup is done by hand in the admin interface.

**Why this priority**: Every other organisation-scoped feature (manager scope, progress reports, pathways per organisation) needs the organisation to exist first. INTENT B.1 requires one instance serving every partner, with each organisation identified and its managers scoped inside it.

**Independent Test**: Add a test organisation to the declaration, apply it to the temporary instance, and confirm the category and cohort exist with the standard shape; apply again and confirm nothing changes.

**Acceptance Scenarios**:

1. **Given** an organisation added to the declaration, **When** it is applied, **Then** the organisation's category and organisation cohort exist and are identical in shape to every other partner's.
2. **Given** an organisation already applied, **When** the declaration is applied again, **Then** nothing is duplicated.
3. **Given** an organisation removed from the declaration, **When** it is applied, **Then** nothing is deleted automatically; the drift check reports it as no longer declared, so learner history is never lost by accident.

---

### User Story 2 - An organisation manager sees and manages only their own people (Priority: P2)

A manager at a partner organisation signs in and can see, follow and manage their organisation's learners, wherever those learners study, including the shared, published curriculum courses that every organisation takes together. As a manager they never see or affect any other organisation's people. Inside a shared course nobody is walled off: learners see their classmates from every organisation (Clarifications 2026-10-02).

**Why this priority**: INTENT names organisation and cohort managers as users who must "see their own people's progress, without seeing anyone else's". Scoping managers is the condition for putting several partners on one site at all; walling off learners is not (Clarifications 2026-10-02).

**Independent Test**: With two test organisations enrolled in one shared course and a test manager in each, confirm that the learners see each other, and that each manager can list, open, follow and manage their own test learners and cannot find, view, enrol or manage the other organisation's.

**Acceptance Scenarios**:

1. **Given** a manager of organisation A, **When** they open their "my organisation" page or their organisation's report, **Then** they see organisation A's people only, with their progress.
2. **Given** organisations A and B both enrolled in a shared curriculum course, **When** a learner of A opens the course's participants list or forum, **Then** they see B's learners too, and the course has no organisation groups.
3. **Given** a manager of organisation A, **When** they try to open a learner profile or cohort of organisation B, or to enrol, suspend, reset or assign a mentor for a learner of B, including a learner who has just moved from A to B, **Then** access is refused. Category pages and course names are public (Clarifications 2026-10-01).
4. **Given** a manager of organisation A, **When** they enrol one of A's learners in a published course, send them a password-reset link, assign them a mentor, or suspend and then reactivate them, **Then** each takes effect for that learner only.
5. **Given** two organisations, **When** their managers are compared, **Then** both have the same role set and the same management actions; there is no per-partner role.
6. **Given** a course declared organisation-only for A, **When** it is published and enrolled, **Then** it sits in A's category and only A's people are enrolled in it.

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
- An organisation manager is also a learner: their own learning is unaffected, and they see their classmates as any learner does. The profile hook refuses them only a profile they could reach in no other way than as a manager (Clarifications 2026-10-02).
- A learner moves from organisation A to B: from that moment A's manager can no longer open their profile, see them on the "my organisation" page or manage them, and B's manager can. In a shared course the learner keeps their place and history. With no organisation groups there is no stale group to clean up by hand (Clarifications 2026-10-02).
- A manager tries to manage someone in their organisation who is a site-team member, a mentor or another manager: the management actions apply to learners only, so the action is refused and the site team does it.
- A manager suspends a learner: the account is suspended for the whole site, including any course another organisation hosts. That is why suspension is limited to their own organisation's learners and is reversible from the same page.
- An organisation asks for a course of its own: it is declared organisation-only by the maintainer (FR-003). That is the one standard variant, not a per-partner special case (constitution VII).
- A partner asks for its own branding or a special role: the answer is no by default (constitution VII); a partner needing real isolation is a candidate for its own Moodle, which is a separate publish target.
- An organisation's name changes: the change is made in the declaration and applied; identity is a stable key, not the display name, so nothing is duplicated.
- Profile field values are personal data: they live only in Moodle; the repo declares the fields and their allowed values, never any learner's value.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The repo MUST declare the list of partner organisations we host, each with a stable key and display name, and apply it through spec 001's mechanism.
- **FR-002**: Each declared organisation MUST receive the same structure: one course category, one organisation cohort and one managers cohort, created only if absent.
- **FR-003**: The published curriculum MUST live in a shared category available to every organisation; an organisation's category holds any organisation-only courses, published from the repo like any other. A course is organisation-only only when the maintainer declares it so in `moodle/site/`, never in course frontmatter; the publisher then places it in that organisation's category. Organisation-only means restricted enrolment, not hidden content: its content stays in the public repo and its name on a public category page (Clarifications 2026-10-02).
- **FR-004**: Removing an organisation from the declaration MUST NOT delete anything; the drift check MUST report it instead.
- **FR-005**: There MUST be exactly one organisation manager role, declared in the repo, used for every partner.
- **FR-006**: An organisation manager MUST be able to see their organisation's people, open their profiles and follow their progress, wherever they are enrolled, without being enrolled in shared courses themselves. For their organisation's learners (not staff, mentors or other managers) they MUST be able to: enrol them in and unenrol them from a published course or their own organisation's organisation-only course (pilot enrolment stays with the pilot coordinator); send them a password-reset link; assign and end their mentors; and suspend and reactivate their account. Enrolling a whole organisation by cohort sync stays with the site team (Clarifications 2026-10-02).
- **FR-006a**: Every management action MUST be authorised on each request by the manager's managers-cohort membership and the learner's organisation of record, through one shared check that spec 016 also uses. No management action may use a core selector or search that reaches every site user (Clarifications 2026-10-02).
- **FR-006b**: A manager and each person of their organisation MUST become message contacts automatically, and stop being contacts when either leaves, unless another relationship (spec 003 mentoring) keeps them (Clarifications 2026-10-02).
- **FR-007**: An organisation manager, acting as a manager, MUST NOT be able to view, find, enrol, manage or edit any other organisation's people or cohorts, including a person who has left their organisation. This scopes managers' follow-up data and management actions only: learners in a shared course, a manager among them, see their classmates from every organisation. A category page and its course names are not personal data and stay visible (Clarifications 2026-10-01, 2026-10-02).
- **FR-008**: The site MUST offer custom profile fields for organisation, country, role in the work and areas of expertise, each with a declared visibility.
- **FR-009**: The organisation field MUST be editable only by the site team, never by the learner or an organisation manager.
- **FR-010**: Learners MUST be added to and removed from organisation cohorts automatically from their organisation field, using core Moodle capability where it exists. No country cohorts are created (Clarifications 2026-10-01).
- **FR-011**: Organisations MUST NOT be separated by groups inside a course. Shared and organisation-only courses default to no groups, and the publisher and the applier keep them so. A course or activity MAY use groups for its own teaching reasons (Clarifications 2026-10-02).
- **FR-012**: Assigning a person to be an organisation's manager MUST happen in Moodle (by an administrator, or later by the tooling in spec 008), never by recording that person in the repo.
- **FR-013**: New learner accounts MUST be created by the site team only; an organisation manager never creates accounts.

### Key Entities

- **Partner organisation**: an organisation whose learners we host; stable key, display name; owns one category and one organisation cohort.
- **Organisation cohort**: the set of learners belonging to one organisation; filled from the organisation profile field.
- **Organisation manager**: a member of one or more organisations' managers cohorts. That membership alone scopes them, through one shared check (FR-006a); the `orgmanager` role is held only in organisation-only courses (Clarifications 2026-10-02).
- **Organisation-only course**: a course the maintainer declares, in `moodle/site/`, as hosted by one organisation; it lives in that organisation's category and only its people are enrolled.
- **Manager–member contact**: a message contact between a manager and a person of their organisation, made and ended automatically (FR-006b).
- **Profile field**: a declared attribute of every learner, with allowed values and a visibility; its values live only in Moodle.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new partner organisation is fully set up from the repo in under 15 minutes of maintainer time, with no admin-interface steps.
- **SC-002**: In a two-organisation test with one shared course and one organisation-only course: every learner in the shared course sees 100% of their classmates from both organisations; each manager reaches and can manage 100% of their own test learners through their "my organisation" page, their report and profiles, and 0% of the other organisation's through any page, management action or web service; and 0 learners of the other organisation are enrolled in, or can open, the organisation-only course. A learner moved from one organisation to the other counts as the other organisation's at once, with no manual step (Clarifications 2026-10-02).
- **SC-003**: A learner whose organisation field is set appears in the right cohort, and in its synchronised courses, within one scheduled refresh, with no manual step.
- **SC-004**: 2–3 real partner organisation managers, given a small test cohort in a shared course, find, follow and manage their own learners without help (enrol one, send a reset link, assign a mentor), and their findings are recorded, before row #15 is marked done.
- **SC-005**: Every organisation on the site holds an identical role set; a comparison finds zero per-partner roles or permission overrides.

## Assumptions

- Partner organisation names and the category structure are not personal data and may be declared in the public repo; which people belong to or manage an organisation is personal data and never is.
- Country values come from Moodle's own country list; role-in-the-work and expertise values are a short declared list, extended by reviewed change.
- An "independent" organisation holds consultants with no partner organisation.
- Automatic cohort filling from profile fields may need a maintained free plugin if core cannot do it on 5.2; that choice and its verification belong in the plan.
- Real multi-tenancy (per-partner branding and isolation) is out of scope. Organisations are identified by category, cohort and profile field, and managers are scoped by cohort membership; that is enough (Clarifications 2026-10-02).
- Until spec 016 makes `ltct_org` private, a learner's organisation is visible to their classmates on their profile (Clarifications 2026-10-02).
- INTENT's data-protection position (privacy notice, consent, retention) is still open; profile fields are kept to the minimum above until it is settled, and no sensitive categories of data are collected.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 8 | Cohorts / groups | Must | Organisation cohorts, filled from the organisation field; cohort enrolment; open courses with no organisation groups (2026-10-02) |
| 15 | Scales across orgs | Must | One category and cohort per organisation, one shared curriculum category, one manager role and one set of management pages scoped by the managers cohort, organisation-only courses as one declared variant |
| 18 | Persistent profiles | Pref | Organisation, country, role and expertise fields with visibility controls |
| 7 | Progress reporting | Must | (2026-10-02) Managers no longer get core's per-course reports in shared courses; they follow through spec 004's report and the "my organisation" page |
| 10 | Discussion | Must | (2026-10-02) Course forums are open across organisations; `course-discussions.yaml` retires |
| 11 | Mentoring | Must | (2026-10-02) Organisation managers assign and end mentors for their own learners (spec 003 Phase B) |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: organisations, fields and roles are declared in the repo; nothing about people flows back from Moodle.
- **II. Portability**: categories, cohort definitions, profile fields and the role are applied from `moodle/` via spec 001 and are rebuildable; membership is learner data and moves with the data restore.
- **III. Public repo, private people**: the repo holds organisation names and field definitions only; memberships, manager assignments and field values stay in Moodle (FR-012, assumptions).
- **VI. No LMS orientation**: learners do nothing to join cohorts (FR-010); managers get one page for everything they do for their people. Proven simple only by SC-004.
- **VII. Standardisation**: one shape, one role set and one set of management actions for every partner; the organisation-only course is one declared variant open to every organisation, not a per-partner special case.
- **IX. Flat cost**: core capability first; any plugin must be free and maintained. Management actions are our own code because core cannot scope them to one organisation (research R10).
- **XI. Survives an upgrade**: our own code only on supported extension points (the profile callback, events, a scheduled task, web services, pages, the user-menu hook); core writes through core APIs (research R10–R13); raw reads listed in the plugin README.
- **X. Traceable and verified**: cites #8, #15, #18, and #7, #10, #11 for the 2026-10-02 amendment; open courses, manager scope and automatic cohorts verified on the temporary 5.2.3+ instance with test accounts only; admin simplicity (#15 via #14) needs 2–3 real organisation managers (SC-004). The recurring operations are maintaining the organisation list and the site team's steps in research R8: accounts, enrolling an organisation in a course, managers, and approving and enrolling an organisation-only course. Managers now take over some per-person enrolment and account work. Spec 008 owns reducing the rest.
- **Platform & Delivery**: one instance for every partner, with organisations identified and managers scoped by category, cohort, role and profile field; shared courses are open; no hard-coded host.

## Dependencies

- 001-site-config-as-code: applies the organisations, cohorts, fields and role.
- 008-admin-tooling: bulk account creation, enrolment and manager assignment build on this structure.
- Relied on by 003-mentor-role, 004-progress-reporting (reports scoped per organisation), 006-learning-pathways, 005-community-space and 012-assignments-peer-review.
- **012-assignments-peer-review** relied on every course group lying within one organisation, with idnumbers `ltct:org:<key>` and `ltct:cohort:<key>`, for its peer allocator and its separated discussion forum. **Withdrawn 2026-10-02**: there are no organisation groups, so 012 re-plans peer review and discussion on open courses, including a rule for partner data in submissions reviewed across organisations.
- **016-identity-protection** relies on this amendment: organisation groups gone, and organisation managers recognised by managers-cohort membership through the same check FR-006a defines.
