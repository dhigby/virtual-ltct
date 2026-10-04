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

### Session 2026-10-03

The maintainer (Doug) set out how enrolment should work. SIL has Area Language Technology Coordinators (ALTCs), responsible for developing the consultants in each Area, and they should be the ones adding people as learners. A learner looks at the site and all of the courses, asks for one by giving their name and country, and the request reaches the ALTC of their Area, who adds them and, if asked, keeps their identity secret. Teachers should be able to add learners too, especially a teacher advertising a course for a group to take as a cohort; if that adds too much complexity, ALTCs alone is acceptable. Cohorts are within an Area, not a country. Another organisation, such as Seed Company, calls the role something else; this title is used for all of them.

Each answer below is tagged **(Doug)** when he decided it, or **(recommended; confirmed by Doug 2026-10-04)** when it was the assistant's design recommendation and Doug then confirmed it. The answers add to the 2026-10-02 session and change none of it except where stated. The request flow itself is a later spec, 017 (`moodle/REQUIREMENTS.md` row 27); this amendment is the structure it stands on (INTENT 2026-10-03).

- Q: How is an Area represented? → A: **(Doug)** As an organisation entry of the one shape (FR-002): its own category, cohort, managers cohort, cohort rule and report. A learner's organisation of record becomes their Area, and "SIL" becomes the family name the Area entries share. A nested Area field under SIL was declined, because every manager check, the profile hook, the contacts, the reports and spec 016 would need a second kind of scope. The accepted cost: a SIL-wide view is five Area reports until spec 016's decision 2 lets reports be scoped another way.
- Q: Which organisations are split by Area? → A: **(Doug)** SIL and SIL Partner, with full manager rights for the ALTCs over both. This narrows research R6, whose single `sil-partner` organisation had managers who followed every partner's learners. An Area's ALTC now manages the SIL partners' learners in their Area, protected identities included (spec 016 FR-006). The consequence is accepted: a protected SIL partner learner's real name and email are seen by their SIL Area coordinator. Spec 016's preview (FR-012) and its "how to ask" text (FR-008) should say who that is. **(recommended; confirmed by Doug 2026-10-04)** Seed Company and Independent stay single entries for now. Seed Company's coordinators fill the same role, so it may later be declared by region in the same way.
- Q: What are the keys? → A: **(Doug)** `sil-americas`, `sil-al-africa`, `sil-fr-africa`, `sil-eurasia`, `sil-asia-pacific`, and the same five with `silp-` for SIL partners. They are fixed now, because a key never changes once applied, and each fits the 30-character limit (`site_config.py` `ORG_KEY_MAX`). The display names in FR-014 were recommended and confirmed by Doug on 2026-10-04; a display name may change without changing a key.
- Q: May the repo say which countries belong to which Area? → A: **(Doug)** No. Some of those countries are places where being identified with this work is a risk, and the repo and its history are public and permanent. The map is Moodle data, kept by the site team, and the request flow (spec 017) reads it there. **(recommended; confirmed by Doug 2026-10-04)** The five Area names may be public, since they are SIL's own published structure and mark no one as at risk. Area keys are deliberately not neutral, so spec 016 is asked to record them as a case of its R12. Protection inside an Area is set person by person. If a group within an Area needs organisation-wide protection, it gets its own entry with a neutral key and name from its first commit (spec 016 R12), not a minimum on the Area entry.
- Q: What happens to the existing `sil` and `sil-partner` entries? → A: **(recommended; confirmed by Doug 2026-10-04)** They stay, as holding entries for people whose Area is not yet known, and their managers cohorts hold site-team members only. Doug's answer that the Area replaces SIL is met for everyone whose Area is known.
- Q: How do existing SIL learners reach their Area? → A: **(recommended; confirmed by Doug 2026-10-04)** The site team changes their organisation field from `sil` to their Area (FR-009), but only after each Area's cohort is enrolled in every shared course the `sil` cohort is. The learner's `sil` cohort-sync enrolment is then suspended as usual (R7), but they keep access and history through the Area's enrolment in the same course (FR-017). Spec 008 provides a counted dry run first.
- Q: How does an ALTC put learners together into a cohort within an Area? → A: **(Doug)** ALTCs put cohorts together, and a cohort follows the Area, not a country. **(recommended; confirmed by Doug 2026-10-04)** It is a teaching group, never a wall between organisations (FR-019). The mechanism (a group inside one course, or a cohort across several once spec 006's pathways exist) is a plan decision.
- Q: May teachers add learners? → A: **(Doug)** Doug wants teachers able to add learners too, especially a teacher advertising a course for a group to take as a cohort. He accepts ALTCs alone if teachers add too much complexity. **(recommended; confirmed by Doug 2026-10-04)** They do add too much, for now. Core's teacher enrolment searches every user on the site (FR-006a), counts as a pilot enrolment in spec 004, and lets a teacher change the Organisation enrolment instance. So ALTCs alone add learners in this amendment, and FR-018 applies. Teachers adding learners stays the goal for later, designed in spec 017.
- Q: Does an ALTC create accounts? → A: Not under this spec: FR-013 stands, so until spec 017 an ALTC enrols only people who already have an account. **(recommended; confirmed by Doug 2026-10-04)** Spec 017 revisits FR-013 (see Dependencies).
- Q: How is a course taken? → A: **(Doug, 2026-10-04)** By a cohort, or by a student with their mentor. In a pair, the student takes the course and the mentor works with them to assess and grade it. A cohort has one or more mentors for the course, and they do the grading. A learner may have a default mentor, and someone may also mentor them for just one course. What follows for this spec: an assessed activity may limit each assessor to the work of the learners they assess (FR-019), while the course stays open (FR-011). The mechanism that puts a pair's mentor into the course as the course-level assessor belongs to the 008 re-plan (see Dependencies). Grades are course training evidence, never a CBC level (constitution V).
- Q: Can there be an organisation-only course for all of SIL? → A: **(recommended; confirmed by Doug 2026-10-04)** Not as declared today. An organisation-only course belongs to one organisation entry, so a SIL-wide course would need one per Area. This is accepted as a known limit; a "family" variant is a reviewed change if a real need appears.

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
- An ALTC covers SIL and SIL partners in one Area, or acts across several Areas: they are a member of each Area managers cohort they cover, and nothing else changes. **(recommended; confirmed by Doug 2026-10-04)** A SIL-wide lead in all five SIL Area managers cohorts sees every SIL learner's real identity (spec 016 FR-006), so that membership is given only to a named person who needs it.
- A learner's Area is not yet known: **(recommended; confirmed by Doug 2026-10-04)** they stay in the `sil` or `sil-partner` holding entry, managed by the site team only, until the site team moves them (FR-017).
- A learner's Area shows on their profile: until spec 016's decision 2 makes `ltct_org` private, a learner's Area shows on their profile next to their country, so anyone who can log in could piece the country-to-Area map together. FR-016 keeps the map out of the public repo; it does not hide it from people with accounts. **(Doug, 2026-10-04)** The move to Areas does not wait for that: there will be no real users for a while.
- Going live before spec 016: research R13's production gate applies per Area entry. Which Area entries may need identity protection is decided by the site team in Moodle before spec 015's go-live, and is not recorded here (constitution III).
- A learner moves from one Area to another: it is an ordinary move between organisations (US3-2). The new Area's ALTC gains them at once and the old one loses them.
- A country spans two Areas, or a learner works in several countries: the Area of record is one entry, chosen by the site team. How a request is routed in that case, and where it goes when the person cannot or will not give their country, is spec 017's.
- An ALTC is absent or the Area has none: the Area's learners are still managed by the site team, which has every manager power. Escalating unhandled requests is spec 017's.
- A teacher wants a group to take a course together: Doug's first preference is that the teacher can add them. **(recommended; confirmed by Doug 2026-10-04)** In this amendment the group's ALTCs enrol them (FR-018). Each ALTC enrols their own Area's people, so a group across Areas needs each Area's ALTC.

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
- **FR-014**: SIL and SIL Partner MUST be declared Area by Area, as ordinary organisation entries of the one shape (Clarifications 2026-10-03):

  | Area | SIL key | SIL display name | Partner key | Partner display name |
  |---|---|---|---|---|
  | Americas | `sil-americas` | SIL Americas | `silp-americas` | SIL Partner, Americas |
  | Anglo-Lusophone Africa | `sil-al-africa` | SIL Anglo-Lusophone Africa | `silp-al-africa` | SIL Partner, Anglo-Lusophone Africa |
  | Francophone Africa | `sil-fr-africa` | SIL Francophone Africa | `silp-fr-africa` | SIL Partner, Francophone Africa |
  | EurAsia | `sil-eurasia` | SIL EurAsia | `silp-eurasia` | SIL Partner, EurAsia |
  | Asia Pacific | `sil-asia-pacific` | SIL Asia Pacific | `silp-asia-pacific` | SIL Partner, Asia Pacific |

  The keys are Doug's, and he confirmed the display names on 2026-10-04; a display name may still change without changing a key (FR-001). An entry still carries only a key and a name. **(recommended; confirmed by Doug 2026-10-04)** The existing `sil` and `sil-partner` entries stay as holding entries, and their managers cohorts hold site-team members only.
- **FR-015**: An Area Language Technology Coordinator (ALTC), the person responsible for developing consultants for an organisation entry, MUST be a member of the managers cohort of each entry they cover, and hold no other role. For SIL and SIL partners those entries are Areas. For those entries' learners, an ALTC holds the powers FR-006 to FR-006b give any organisation manager, and may put those learners together as a teaching group (FR-019). How the grouping is done is a plan decision; if it needs a new management action, that action goes through the same shared check (FR-006a). The ALTC title appears only in text people read, never in a role, key or code path.
- **FR-016**: Which countries belong to which Area MUST NOT be recorded in the repo, its history, a test fixture, a log, quickstart or verification evidence, or a GitHub issue, pull request or comment. Tests map countries only to made-up test Areas (for example `test-area-1`), never to a real Area key. The map is Moodle data, kept by the site team (constitution II and III, Clarifications 2026-10-03).
- **FR-017**: **(recommended; confirmed by Doug 2026-10-04)** Moving learners from `sil` or `sil-partner` to an Area entry MUST NOT cost them access to any shared course or any activity history. Each Area's cohort is enrolled in every shared course the old cohort is enrolled in before anyone is moved, so every suspended `sil` cohort-sync enrolment is matched by an active Area enrolment in the same course. An organisation-only course of `sil` or `sil-partner` (none is declared today) keeps its own rule: a mover's enrolment there is suspended (R10), unless the maintainer first declares what replaces it. The move is the site team's (FR-009).
- **FR-018**: **(recommended; confirmed by Doug 2026-10-04)** The course-editing teacher role MUST NOT hold core's enrolment and enrolment-configuration capabilities: manual enrolment, self-enrolment configuration, cohort-sync configuration, course enrolment configuration, and the course-context cohort view. The full list is taken from `MOODLE_502_STABLE`'s defaults. Before the change, it is confirmed that the stage-7 pilot coordinator can still enrol pilot learners with the course's manual enrolment method (`process/stages/07-pilot.md`).
- **FR-019**: A cohort an ALTC puts together MUST be a teaching group of the learners of the entries they manage, following the Area, not a country (FR-010). Groups MUST NOT separate organisations. The course group mode stays 0 (drift already reports any `ltct:` course where it is not), and forums and every other shared activity stay open to the whole course. Only an assessed activity may use separate groups, so that each assessor (a pair's mentor, or whoever assesses a cohort) sees and grades only the work of the learners they assess (Clarifications 2026-10-04). A group is never named after an organisation, an Area or a learner.

### Key Entities

- **Partner organisation**: an organisation whose learners we host; stable key, display name; owns one category and one organisation cohort.
- **Organisation cohort**: the set of learners belonging to one organisation; filled from the organisation profile field.
- **Organisation manager**: a member of one or more organisations' managers cohorts. That membership alone scopes them, through one shared check (FR-006a); the `orgmanager` role is held only in organisation-only courses (Clarifications 2026-10-02).
- **Organisation-only course**: a course the maintainer declares, in `moodle/site/`, as hosted by one organisation; it lives in that organisation's category and only its people are enrolled.
- **Manager–member contact**: a message contact between a manager and a person of their organisation, made and ended automatically (FR-006b).
- **Profile field**: a declared attribute of every learner, with allowed values and a visibility; its values live only in Moodle.
- **Area**: a region an organisation is managed by, declared as an organisation entry of its own (FR-014). For SIL these are the five SIL Areas, and "SIL" is the family name their entries share. **(recommended; confirmed by Doug 2026-10-04)** The `sil` and `sil-partner` entries stay as holding entries for people whose Area is not yet known.
- **Area Language Technology Coordinator (ALTC)**: the person responsible for developing consultants for an organisation entry (an Area for SIL and SIL partners), and that entry's organisation manager (FR-015).
- **Country-to-Area map**: which countries belong to which Area. Moodle data, never in the repo (FR-016).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new partner organisation is fully set up from the repo in under 15 minutes of maintainer time, with no admin-interface steps.
- **SC-002**: In a two-organisation test with one shared course and one organisation-only course: every learner in the shared course sees 100% of their classmates from both organisations; each manager reaches and can manage 100% of their own test learners through their "my organisation" page, their report and profiles, and 0% of the other organisation's through any page, management action or web service; and 0 learners of the other organisation are enrolled in, or can open, the organisation-only course. A learner moved from one organisation to the other counts as the other organisation's at once, with no manual step (Clarifications 2026-10-02).
- **SC-003**: A learner whose organisation field is set appears in the right cohort, and in its synchronised courses, within one scheduled refresh, with no manual step.
- **SC-004**: 2–3 real partner organisation managers, given a small test cohort in a shared course, find, follow and manage their own learners without help (enrol one, send a reset link, assign a mentor), and their findings are recorded, before row #15 is marked done.
- **SC-005**: Every organisation on the site holds an identical role set; a comparison finds zero per-partner roles or permission overrides.
- **SC-006**: Once the Area entries are applied, every Area entry has the same shape as every other organisation (SC-005), and the repo holds zero country-to-Area assignments. A test learner moved from `sil` to an Area loses access to zero shared courses and keeps all their activity history. Before the Area structure counts as done, 2–3 real ALTCs each find, enrol and manage learners of their own Area without help, and their findings are recorded (constitution X).

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
| 8, 15 | (as above) | Must | (2026-10-03) SIL and SIL Partner declared Area by Area, and ALTCs as their managers (Doug); cohorts within an Area (Doug); holding entries and the course-editing teacher without core enrolment (confirmed by Doug 2026-10-04) |
| 27 | Enrolment requests | Must | (2026-10-03) Not delivered here: this amendment is the structure spec 017 routes requests through |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: organisations, fields and roles are declared in the repo; nothing about people flows back from Moodle.
- **II. Portability**: categories, cohort definitions, profile fields and the role are applied from `moodle/` via spec 001 and are rebuildable; membership is learner data and moves with the data restore. The country-to-Area map is Moodle data, recovered by the data restore, so spec 015's backups must include `local_ltuse`'s tables (constitution 2.1.0).
- **III. Public repo, private people**: the repo holds organisation names and field definitions only; memberships, manager assignments and field values stay in Moodle (FR-012, assumptions). The country-to-Area map is never in the repo (FR-016); the Area names are SIL's public structure.
- **VI. No LMS orientation**: learners do nothing to join cohorts (FR-010); managers get one page for everything they do for their people. Proven simple only by SC-004.
- **VII. Standardisation**: one shape, one role set and one set of management actions for every partner; the organisation-only course is one declared variant open to every organisation, not a per-partner special case. Declaring SIL and SIL Partner Area by Area uses the same shape and adds no key, role or code path; another organisation could be declared by region the same way, by a reviewed change (constitution 2.1.0, confirmed by Doug 2026-10-04).
- **IX. Flat cost**: core capability first; any plugin must be free and maintained. Management actions are our own code because core cannot scope them to one organisation (research R10).
- **XI. Survives an upgrade**: our own code only on supported extension points (the profile callback, events, a scheduled task, web services, pages, the user-menu hook); core writes through core APIs (research R10–R13); raw reads listed in the plugin README.
- **X. Traceable and verified**: cites #8, #15, #18, and #7, #10, #11 for the 2026-10-02 amendment; open courses, manager scope and automatic cohorts verified on the temporary 5.2.3+ instance with test accounts only; admin simplicity (#15 via #14) needs 2–3 real organisation managers (SC-004). The recurring operations are maintaining the organisation list and the site team's steps in research R8: accounts, enrolling an organisation in a course, managers, and approving and enrolling an organisation-only course. Managers now take over some per-person enrolment and account work. Spec 008 owns reducing the rest. 2026-10-03 adds, carried by the site team: keeping the country-to-Area map in Moodle (where and how is spec 017's); moving learners from `sil` and `sil-partner` to Areas after enrolling each Area cohort (FR-017, with spec 008's dry run); and assigning ALTCs to up to ten Area managers cohorts. It cites #27 as structure only. These operations are not claimed as covered while the Moodle operator is undecided. SC-006 needs 2–3 real ALTCs.
- **Platform & Delivery**: one instance for every partner, with organisations identified and managers scoped by category, cohort, role and profile field; shared courses are open; no hard-coded host.

## Dependencies

- 001-site-config-as-code: applies the organisations, cohorts, fields and role.
- 008-admin-tooling: bulk account creation, enrolment and manager assignment build on this structure.
- Relied on by 003-mentor-role, 004-progress-reporting (reports scoped per organisation), 006-learning-pathways, 005-community-space and 012-assignments-peer-review.
- **012-assignments-peer-review** relied on every course group lying within one organisation, with idnumbers `ltct:org:<key>` and `ltct:cohort:<key>`, for its peer allocator and its separated discussion forum. **Withdrawn 2026-10-02**: there are no organisation groups, so 012 re-plans peer review and discussion on open courses, including a rule for partner data in submissions reviewed across organisations.
- **017-enrolment-requests** (to be specified, row 27) relies on the 2026-10-03 Area structure. It routes a request by country, through the Moodle-held map, to an Area's coordinators. Open for 017 and Doug: a country gives an Area, not an organisation entry, so how a request identifies whether the person is SIL, a SIL partner, Seed Company or independent, and where Seed Company and independent requests go. **(recommended; confirmed by Doug 2026-10-04)** 017 revisits FR-013 so that an ALTC may create an account only by approving a request for their own Area. 017 must keep FR-009, so the ALTC never edits the organisation field, and R10, so enrolment waits until the learner is in the Area cohort.
- **Cohorts, pairs and their mentors (Doug, 2026-10-04)**: every course is assessed by mentors in the course, through spec 012's course-level Course mentor role (Moodle's non-editing teacher). A cohort has one or more Course mentors for that course. A pair has one. A learner's **default mentor** is spec 003's long-running mentor relationship. It cannot grade, because grading needs a course-level role. A **course mentor** is whoever assesses the learner in one course, and may be someone else. Enrolling the course mentor, defaulting to the learner's default mentor when a pair is set up, with a group for the pair or the cohort, was deferred by spec 003 (R8) and spec 012 (R3) to spec 008. The 008 re-plan owns it, as automatic behaviour in `local_ltuse`, not a manual step. Spec 012 re-plans its group scoping for open courses, with pair and cohort groups, because its 2026-10-01 design relied on organisation groups. **(Doug, 2026-10-04)** A course mentor, including one who mentors the learner for just one course, sees a protected learner's real identity, as an assigned mentor does under spec 016 FR-006. Spec 016 records this when it is next amended.
- **008-admin-tooling** is re-planned on the 2026-10-02 and 2026-10-03 amendments. Any 008 plan drafted before this amendment (none has been committed) assumed the 2026-10-01 model and is superseded. It gains the `sil` → Area move with a dry run (FR-017).
- **plan.md, research.md, data-model.md, contracts and tasks.md** of this spec are not yet amended for 2026-10-03. They are amended in the plan step, before any build. Where they disagree with the 2026-10-03 Clarifications, the spec wins.
- **016-identity-protection** relies on this amendment: organisation groups gone, and organisation managers recognised by managers-cohort membership through the same check FR-006a defines.
