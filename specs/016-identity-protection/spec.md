# Feature Specification: Identity protection for at-risk users

**Feature Branch**: `016-identity-protection`

**Created**: 2026-10-02

**Status**: Draft

**Input**: User description: "Identity protection for at-risk users (spec 016). Some users need extra protection of their identity because of the areas they work in: hiding their email address, showing only a first name, or using a pseudonym. Following the 2026-10-02 decision (spec 011 handoff, PR #82) courses are open: students see all classmates, course leaders see their students, mentors see and interact with mentees across organisations, and organisation managers see and manage their users. Identity protection must not block a protected user from interacting in Moodle (forums, peer review, messaging, mentor booking, events). Reference: https://studentprivacy.ed.gov/ferpa."

## Clarifications

### Session 2026-10-02

- Q: Who besides the site team and assigned mentors sees a protected learner's real identity? → A: Their own organisation's managers too. Other organisations' managers never do. ~~An organisation may withhold it from its own managers.~~ *(Last sentence reversed: no organisation setting withholds it. Doug, 2026-10-05 (scope review), change 10; see Session 2026-10-05.)*
- Q: Who grants or changes protection? → A: The learner's organisation manager or the site team. The learner can always ask. ~~Organisation minimums are declared by the maintainer.~~ *(Narrowed: managers grant only at intake; there are no organisation minimums. Doug, 2026-10-05 (scope review), changes 9 and 14.)*
- Q: What do a pseudonymous learner's certificate and badge show? → A: ~~The real name on the learner's own downloaded certificate~~; only the protected display, or the fact of a valid award, on public verification pages. *(The certificate now prints the account's name, which is the protected display; a real-name certificate is issued by the site team on request. Doug, 2026-10-05 (scope review), change 8.)*
- (Matthew) ~~Protection can be set for a whole organisation as well as for one user.~~ *(Superseded: protection is per person, only for someone who asks. Doug, 2026-10-05 (scope review), change 9.)*

### Session 2026-10-04

Doug's decisions of 2026-10-03 and 2026-10-04 (`INTENT.md`; spec 002 Clarifications 2026-10-03, PR #87) change who counts as a learner's organisation and their mentors. The answers below amend this spec where stated.

- Q: Who is a learner's "own organisation" now? → A: The organisation entry their `ltct_org` names (constitution 2.1.0). SIL and SIL Partner are declared Area by Area, so a SIL learner's own organisation managers are their Area Language Technology Coordinators (ALTCs). A SIL partner learner's are the SIL ALTCs of their Area too, with full manager rights, protected identities included (Doug, 2026-10-03).
- Q: Does a mentor who mentors a learner for just one course see their real identity? → A: Yes (Doug, 2026-10-04). A course is taken by a cohort with one or more course mentors, or by a student with their mentor, and mentors grade. ~~Every course mentor of a course the learner takes is entitled while they hold that role, whether or not they are the learner's default (spec 003) mentor (FR-006).~~ *(Narrowed: only the course mentor whose own mentor group in that course holds the learner, whether or not they are the learner's default mentor. Doug, 2026-10-05 (scope review), change 22.)*
- Q: Where are organisation minimums kept? → A: ~~As Moodle data, set by the site team, never declared in the repo (plan decision 8, accepted 2026-10-04 with constitution 2.1.0, Principle II).~~ *(Superseded: there are no organisation minimums. Doug, 2026-10-05 (scope review), change 9.)*
- Q: Do the Area keys break research R12's neutral-key rule? → A: No. `sil-americas`, `sil-eurasia` and the rest name SIL's own published structure and mark no one as at risk, and the country-to-Area map is never in the repo (spec 002 FR-016). Protection inside an Area is set person by person. *(The neutral-key rule itself is now: an organisation that asks not to be named publicly gets a neutral key and name from its first commit. Doug, 2026-10-05 (scope review), change 23.)*

### Session 2026-10-05 (scope review)

Doug ruled on 2026-10-04 and 2026-10-05 that identity protection is **per person and opt-in**: it is for people concerned about exposure who ask when they are added, and it must not burden anyone else. Protected people usually already have pseudonymous email addresses, and everyone signs in with their email (spec 008, `authloginviaemail`). On 2026-10-05 he accepted every recommendation of the scope review. The answers below amend this spec where stated; each is recorded as "Doug, 2026-10-05 (scope review)", with the review's change number.

- Q: Who is protected? → A: Only a person who asks, normally when they are added. A grant records that they asked (FR-008). Nothing changes for anyone who did not ask, except site settings that cost nobody anything (changes 12, 13, 20).
- Q: Is protection ever set for a whole organisation? → A: No. FR-001a is withdrawn, which supersedes Matthew's 2026-10-02 line (change 9).
- Q: May an organisation withhold its people's real identities from its own managers? → A: No. This reverses the 2026-10-02 answer. A person who does not want their organisation's managers to see their identity is placed by the site team under a neutral organisation entry with no managers (change 10).
- Q: Is email hidden from course staff for everyone? → A: No (plan decision 1 rejected). When any level is granted, the granter confirms that the account's email address identifies neither the person nor their organisation, and an identifying address is replaced with a pseudonymous one first (FR-016; changes 1, 2).
- Q: Are names and email locked for everyone? → A: No (plan decision 3 rejected). The protected record is restored by the plugin, and the neutral surname is a placeholder (changes 3, 4).
- Q: Is the organisation hidden at the higher levels? → A: No (plan decision 2, option a). `ltct_org` stays visible at every level; the person is told so when they are protected (FR-001, FR-012, FR-015; change 11).
- Q: What does the certificate print? → A: The name on the account, as for everyone, which for a protected learner is the protected display. The site team issues a real-name certificate on request (FR-010; change 8).
- Q: Who changes protection after intake? → A: The site team. An organisation's manager grants it when the person is added, before any activity. Corrections, later raises, lowering and removal are the site team's (FR-008; change 14).
- Q: How does a learner know they can ask? → A: It is offered at intake, in the welcome message and in site help (FR-008; change 12).
- Q: Are course logs and global search closed for everyone? → A: No. Course logs stay open to course staff; a protected person who asks for their location to be hidden gets a block in the courses they take (plan decision 5, logs; change 5). The global search user area stays on; the spec that turns global search on handles protected people (change 7).

## Context

On 2026-10-02 the maintainer decided that the training system is **open by default**. Students see all their classmates, course leaders see their students, mentors see and work with their mentees across organisations, and organisation managers see and manage their own users ([spec 011 handoff](../011-events-calendar/handoff.md), decision record). That removes the per-organisation walls that, until now, also limited who could see whom.

Some consultants work in places where being identifiable as part of this training, or as connected to Bible translation, puts them or the people they work with at risk. For them, an open site needs a second setting: **the same participation, under less of their identity**. The model is the "directory information" opt-out in US education privacy practice (FERPA), where a learner can withhold the details a school would otherwise publish without stopping them taking part in their education. This spec borrows that idea. It does not claim that FERPA governs this programme.

The rule throughout: **protection limits what others learn about a person, never what the person can do.** And since 2026-10-05: **protection is for the person who asks, and costs nobody else anything.**

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A protected learner takes part without exposing their identity (Priority: P1)

A learner working in a sensitive area asks to be protected. In every course, forum, peer review, message and event they appear under the name chosen for them, with no detail that would identify them: classmates see no email address, and the address course staff see has been checked not to identify them. They take part exactly as any other learner does.

**Why this priority**: This is the reason for the spec. Without it, opening courses (spec 011 handoff) exposes these learners to every classmate, or forces them to stay out.

**Independent Test**: On a rebuilt test instance, make one test learner protected at each level. Enrol them with ordinary test learners in one course. As a classmate, check every place a person shows up (course participants, forum posts, peer-review allocations, messages, calendar and booking views, profile page, the app). Confirm that the protected learner's real name, email and the details their level withholds appear nowhere, and that the protected learner can post, reply, submit, review, message and book exactly as the classmate can.

**Acceptance Scenarios**:

1. **Given** a learner protected at the "email hidden" level, **When** a classmate views their profile or any post or message from them, **Then** no email address is shown, and their name shows as normal.
2. **Given** a learner protected at the "first name only" level, **When** anyone without the right to see more views them anywhere on the site or in the app, **Then** only their first name is shown.
3. **Given** a learner protected at the "pseudonym" level, **When** anyone without the right to see more views them anywhere on the site or in the app, **Then** only their pseudonym is shown, and nothing on the page links it to their real name.
4. **Given** a protected learner, **When** they post in a forum, submit and review in peer review, send a message, or book a mentor slot, **Then** each action succeeds as it would for an unprotected learner.
5. **Given** a protected learner, **When** a notification about their activity reaches another user by email, **Then** the email carries only the protected display name and no address of theirs.

---

### User Story 2 - The people responsible for a protected learner still know who they are (Priority: P1)

A protected learner's mentor, their organisation manager and the site team need to support them: answer their questions, follow their progress, contact them. They see enough of the learner's real identity to do that, and no one else does.

**Why this priority**: Protection that also hides a learner from the people supporting them would block them from mentoring and progress follow-up, which INTENT puts at the centre. It is P1 alongside US1 because each without the other fails the learner.

**Independent Test**: With a protected learner from organisation A, check what their assigned mentor, their course mentor, A's organisation manager, B's organisation manager, a course leader in their course, a classmate and the site team each see, on the web and in the app.

**Acceptance Scenarios**:

1. **Given** a protected learner, **When** a person entitled to see their real identity (FR-006) views them on one of the surfaces that show it (FR-007), **Then** that person sees the real identity and a clear marker that the learner is protected.
2. **Given** a protected learner, **When** anyone else views them, including an organisation manager of another organisation, **Then** they see only the protected display (US1).
3. **Given** an entitled person, **When** they export or download a core report that includes a protected learner, **Then** ~~the export carries a protection marker on that learner's row~~ the export carries no marker, which is a known gap (FR-015); the marker is shown on the "People I support" page, which has no download. *(Amended: plan decision 7, and Doug, 2026-10-05 (scope review), change 14.)*
4. **Given** an entitled person, **When** they are no longer entitled (for example, the mentor assignment ends), **Then** they stop seeing the real identity.

---

### User Story 3 - Protection is set up and changed without fuss (Priority: P2)

A learner who is concerned about exposure asks for protection, normally when they are added. It is set up quickly by the people allowed to do so, it applies everywhere at once, and it can be changed or removed later. ~~Or an organisation whose people all work in sensitive areas is protected as a whole.~~ *(Withdrawn: Doug, 2026-10-05 (scope review), change 9.)*

**Why this priority**: Protection must be easy to grant, or people at risk will avoid the site. It follows US1 and US2 because it only matters once they work.

**Independent Test**: As an organisation manager, protect a new test learner who asked, at the "pseudonym" level, before their first activity. Then, as the site team, change it to "first name only", then remove it. After each step, confirm as a classmate that every place in US1 shows the new state, without the learner having to log out or re-enrol. ~~Then set a test organisation to "first name only", and confirm that every member is protected at that level, that a new member is protected from the start, and that a member who moves out drops back to their own level.~~ *(Withdrawn with FR-001a: Doug, 2026-10-05 (scope review), change 9.)*

**Acceptance Scenarios**:

1. **Given** a learner who asks for protection, **When** a person allowed to grant it does so, recording that the person asked and that their email address does not identify them, **Then** the protection applies across every course and view at once, and the learner is told what others now see.
2. **Given** a protected learner, **When** the protection level is changed or removed, **Then** what others see follows the change, with no manual step in each course.
3. ~~**Given** an organisation set to a protection level, **When** a learner joins that organisation, **Then** they are protected at that level from the start, and **When** they leave it, **Then** their level drops back to their own setting.~~ *(Withdrawn: Doug, 2026-10-05 (scope review), change 9.)*
4. ~~**Given** a learner in a protected organisation, **When** someone tries to set that learner's own level looser than the organisation's, **Then** the stricter level still applies.~~ *(Withdrawn: Doug, 2026-10-05 (scope review), change 9.)*
5. **Given** a protected learner, **When** they view their own profile, **Then** they can see their protection level and exactly what others see of them.
6. **Given** someone not allowed to grant or change protection, **When** they try, **Then** they cannot.
7. **Given** someone who did not ask, **When** anyone tries to protect them, **Then** the change is refused until the request is recorded (Doug, 2026-10-05 (scope review), change 13).

---

### User Story 4 - What leaves the site protects them too (Priority: P2)

Some things leave the site and outlive any setting: emailed notifications, calendar feeds, a downloaded certificate, a badge someone can verify publicly, a report export. Each of these respects the learner's protection level.

**Why this priority**: A protection that holds on the page but leaks in an email or a public verification page is not a protection. It is P2 because most of these paths only matter once US1 holds on the site.

**Independent Test**: For a protected learner who has completed a test course, trigger each outbound item (forum email, message email, an exported calendar feed of a classmate, the badge's public verification page, the certificate PDF and its verification page, an organisation report export) and confirm that each shows only what FR-010 allows.

**Acceptance Scenarios**:

1. **Given** a protected learner holds a badge or certificate, **When** a third party opens its public verification page, **Then** it shows what FR-010 says, and no real name for a learner protected at the "first name only" or "pseudonym" level.
2. **Given** a classmate exports their calendar, **When** an event or booking involves the protected learner, **Then** the feed shows only the protected display.
3. **Given** any email notification about the protected learner's activity, **When** it is sent, **Then** it carries only the protected display name.

---

### Edge Cases

- **Mentoring across organisations**: a mentor from a different organisation is assigned to a protected learner. Under FR-006 the assignment, not the organisation, is what entitles them to see more.
- **Several roles at once**: a person is both a classmate and the learner's mentor. They see what their most entitled role allows, and only in that capacity's views.
- **Usernames and login**: everyone signs in with their email address (spec 008). A username is never shown to classmates. At "first name only" and "pseudonym", a username that contains the real first name or surname is replaced with a neutral one automatically, and the person keeps signing in with their email (Doug, 2026-10-05 (scope review), change 15). Course staff see the account's email address, which is why it is checked when protection is granted (FR-016).
- **Things the learner writes**: the learner's own words (a forum post that signs off with their real name, a profile description) are theirs to control. The site does not rewrite them, but the learner is told about this when protection is set.
- **The organisation itself identifies people**: the learner's organisation stays visible at every level (FR-001, decision 2 option a), and the person is told so when they are protected. If one person cannot accept that, the maintainer decides then: place them under a neutral organisation entry, or make the organisation field private for everyone (Doug, 2026-10-05 (scope review), change 11). An organisation that asks not to be named publicly gets a neutral key and display name from its first commit, because its category and display name are public (spec 002 R6) and its key can never change (change 23). SIL's Area entries name SIL's public structure and need no neutral key (Clarifications 2026-10-04).
- **A learner moves between organisations**: their level is their own and moves with them unchanged. Moving changes only who their own organisation's managers are (FR-006). Moving must not expose details from their time in the first organisation, such as earlier posts, beyond what their level allows. *(Amended: no organisation sets a level. Doug, 2026-10-05 (scope review), change 9.)*
- **Organisation and profile details**: the learner's country and experience fields can identify them as much as their name does. At "first name only" and "pseudonym" levels, these are shown only to entitled people. Their organisation is not hidden (FR-001).
- **Profile pictures**: a photo identifies a person. At "first name only" and "pseudonym" levels, others see the default picture.
- **History**: protection is set after a learner has already posted. Their past posts, submissions and messages show the protected display from then on. Copies already sent by email or exported cannot be recalled, and the person granting protection is told so. A fresh account is one option to discuss with the person, not the default advice.
- **Search**: searching for the learner's real name or surname returns nothing to people who are not entitled. Classmates cannot search by email. The site's global search is not switched on; the spec that switches it on handles protected people (Doug, 2026-10-05 (scope review), change 7).
- **Group work and peer review**: peer review stays anonymous between learners (spec 012), and a protected learner's work is never identified to a peer by real name.
- **A protected learner who is also a mentor or manager**: their protection applies to how others see them, whatever role they hold.
- **Leaving the programme**: when a protected learner's account is removed, data export and deletion follow the site's existing rules (row #16), with the protection marker carried in the export.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST support three protection levels, each including the ones before it: **email hidden** (classmates do not see the email address; course staff still do, so the address is checked, FR-016); **first name only** (also hides surname, country, experience fields and profile picture); and **pseudonym** (a chosen name replaces the real name everywhere). The learner's organisation is shown at every level (Doug, 2026-10-05 (scope review), decision 2 option a). A level is set only for a person who asks (FR-008).
- **FR-001a**: ~~A protection level MUST be settable for a **whole organisation** (spec 002) as well as for an **individual user**. An organisation's level applies to every member as a minimum. A member's own level may be stricter than their organisation's, never looser. A user's effective level is the stricter of the two, and it changes when they join or leave the organisation.~~ **Withdrawn** (Doug, 2026-10-05 (scope review), change 9): protection is per person; no organisation sets a level for its members.
- **FR-002**: For a protected user, every place another user can see them MUST show only what their level allows. That covers course participant lists, profiles, forum posts, peer review, messages and contact lists, calendar and booking views, reports, user search and selectors, on the web and in the Moodle app.
- **FR-003**: A protected user MUST be able to do everything an unprotected user in the same role can do: enrol, post, reply, submit, peer-review, message, book mentor slots, join events and earn completion.
- **FR-004**: Searching by a protected user's real name, surname or username MUST return nothing to anyone not entitled under FR-006. Searching by their email MUST return nothing to classmates. Course staff, who see the account's email, can find it by that address, which identifies no one (FR-016; Doug, 2026-10-05 (scope review), change 2).
- **FR-005**: Email and other notifications about a protected user's activity MUST carry only their protected display name, and never their email address.
- **FR-006**: The real identity of a protected user MUST be visible to:
  - the site team;
  - their assigned mentors (spec 003), while assigned;
  - their course mentor in each course they take, including someone who mentors them for that one course only, while they hold that role: the course mentor whose own mentor group in that course holds the learner, never every course mentor of the course (Clarifications 2026-10-04; narrowed by Doug, 2026-10-05 (scope review), change 22);
  - the organisation managers of the learner's **own** organisation, since managers see and manage their users (2026-10-02). For SIL and SIL partner learners these are the ALTCs of their Area (Clarifications 2026-10-04).

  ~~An organisation's setting, which is Moodle data set by the site team (R12), MAY withhold real identities from its own managers.~~ No organisation setting withholds it from its own managers (Doug, 2026-10-05 (scope review), change 10); a person who does not want them to see it is placed by the site team under a neutral organisation entry with no managers. No one else may see it, including managers of any other organisation, and course leaders.
- **FR-007**: People who can see a protected user's real identity MUST see a clear protection marker beside it, on the surfaces that show it: the learner's profile, the Mentoring page (web and app), the "People I support" page and the granting page (plan decision 7). Core reports and their exports carry no marker, a known gap (FR-015). There is no download of protected people (Doug, 2026-10-05 (scope review), change 14).
- **FR-008**: Protection MUST be set only for a person who asks, and every grant MUST record that they asked (Doug, 2026-10-05 (scope review), change 13). The learner's own organisation manager grants it when the person is added, before any activity; corrections to the real name or held values, raises after activity, lowering and removal are the site team's (change 14). The way to ask MUST be offered at intake, in the welcome message and in site help (change 12). Every change MUST be recorded in Moodle (who, when, which level, that the person asked, that the email was checked), never in the repo. The way to ask, and the preview of FR-012, MUST say who will see the learner's real identity, and that their organisation stays visible; for a SIL partner learner that includes their SIL Area coordinator. ~~An organisation's minimum level (FR-001a) is Moodle data, set by the site team only, never declared in the repo.~~ *(Withdrawn with FR-001a, change 9.)*
- **FR-009**: A change of protection level MUST take effect across the whole site at once, with no per-course step.
- **FR-010**: A protected user's badges and certificates MUST respect their protection level on the item and on any public verification page. The certificate prints the name on the account, which for a protected learner is the protected display, so nothing on it or on its verification page links the award to the real name. A certificate in the real name is issued by the site team on request (Doug, 2026-10-05 (scope review), change 8). Public verification pages for the badge and the certificate show only the protected display, or only that a valid award exists.
- **FR-011**: Calendar exports and feeds (spec 011) MUST show protected users only as their protected display.
- **FR-012**: The protected user MUST be able to see their own level and a preview of what others see of them, including that their organisation stays visible, and that course staff see their email address.
- **FR-013**: Protection settings and pseudonyms are learner data. They MUST live only in Moodle, MUST NOT appear in the repo or in any committed fixture, log or test output, and MUST be included in the learner's data export.
- **FR-014**: The protection levels, which fields each level hides, and the capabilities that make someone entitled MUST be declared from the repo's Moodle configuration and be rebuildable. Which individual users are protected, and their pseudonyms, is operational data in Moodle, not configuration. ~~Each organisation's minimum level and its withholding setting are Moodle data, set by the site team and recovered by the data restore.~~ *(Withdrawn: there are none. Doug, 2026-10-05 (scope review), changes 9 and 10.)*
- **FR-015**: Any behaviour that cannot be protected through core Moodle or a maintained plugin, and that would leak identity, MUST be listed in the delivering PR as a known gap with its workaround, and not silently shipped. These include, since 2026-10-05: a protected person's (pseudonymous) email address is visible to course staff (FR-016); their organisation is visible at every level (FR-001); and the IP-derived location in course logs is visible to course staff unless they ask for it to be hidden (research R14).
- **FR-016**: When any protection level is granted, the person granting it MUST confirm that the account's email address reveals neither the person's name nor their organisation, and the confirmation MUST be recorded with the change. The granting page MUST warn when the part before the @ contains the first name or surname, or the domain matches the organisation's. An address that identifies the person MUST be replaced with a pseudonymous one before the level takes effect. (Doug, 2026-10-05 (scope review), change 2.)

### Key Entities

- **Protection level**: one of *none*, *email hidden*, *first name only*, *pseudonym*, and the set of identity details each one withholds from people who are not entitled.
- ~~**Organisation protection**: the minimum protection level set for every member of an organisation (spec 002).~~ *(Withdrawn: Doug, 2026-10-05 (scope review), change 9.)*
- **Protected user**: a user who asked for protection and whose level is other than *none*, and, at the *pseudonym* level, the pseudonym shown in place of their name. Learner data, Moodle only.
- **Entitlement**: the relationship that lets someone see a protected user's real identity: site team membership, an active mentor assignment, being the course mentor whose mentor group holds the user in a course they take, or managing the user's own organisation (FR-006).
- **Protection change record**: who changed a user's level, when, from what to what, that the person asked, and that their email address was checked. Moodle only.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In an audit of every view in FR-002, covering web and the Moodle app, 0 views show a protected test user's withheld details to a non-entitled test user, at each of the three levels.
- **SC-002**: A protected test learner completes every interaction in FR-003 with the same steps and the same outcome as an unprotected test learner: 100% of interactions succeed.
- **SC-003**: Searches by a protected test user's real name, surname and username return 0 results for non-entitled test users, and searches by their email return 0 results for classmates.
- **SC-004**: 100% of outbound items in US4 (notification emails, calendar feeds, certificates and their verification, badge verification pages, report exports) show only what the protection level allows.
- **SC-005**: A protection level is granted, changed or removed in under 2 minutes by the person allowed to do it, and the change is visible site-wide within 5 minutes.
- **SC-006**: A server rebuilt from the repo reproduces the protection levels and entitlements with no manual step, and no learner's protection data is in the repo.
- **SC-007**: At least 2 of 3 real protected users, or people acting for them, confirm in a pilot that what others see matches what they expected. This is spec 016's own pilot. If three real protected people do not exist yet, each person is asked at their first grant, and the count builds as they come (Doug, 2026-10-05 (scope review), change 25).

## Assumptions

- Core Moodle offers several relevant settings (per-user email visibility, how full names are displayed, alternate name fields, hidden user fields, identity fields shown to staff), but no single "protected user" switch. Which of these cover which views, and where a plugin or our own code is needed, is a research task for planning (constitution X, XI), not an assumption.
- Peer review between learners is already anonymous (spec 012), which this spec relies on and does not change.
- Content a learner writes in their own words is theirs to manage. The site protects the identity it displays, not text the learner types.
- Copies already sent (emails, exported files, calendar feeds already synced) cannot be recalled. Protection applies from the moment it is set.
- People who ask for protection usually already have a pseudonymous email address (Doug, 2026-10-05).
- The FERPA reference is a model for the directory-information opt-out, not a legal finding about this programme. Whether a data-protection law applies is part of INTENT's open question "What is our data-protection position?".
- Spec 015's production server and its backups hold protection data like any other learner data, under the same access rules.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 26 | Identity protection for at-risk users | Must | Three protection levels, set per person for those who ask, applied across every view, outbound item and search, with real identity limited to entitled supporters, and full participation kept. Added to `moodle/REQUIREMENTS.md` by this spec (constitution X). |

On delivery, the same PR updates this row's status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: Levels, the fields each one hides and entitlements are declared in `moodle/`. Who is protected, and their pseudonym, live only in Moodle and are never synced back.
- **II. Portability**: Configuration is rebuildable (SC-006). Protection data is learner data, recovered by the data restore and included in the learner's data export (FR-013).
- **III. Public repo, private people (NON-NEGOTIABLE)**: This spec is Principle III extended inside Moodle. No protected user, pseudonym or change record is ever committed, including in fixtures and logs. Verification uses `ltct-test-*` accounts only; evidence from those accounts alone may go in the PR (Doug, 2026-10-05 (scope review), change 24).
- **IV. Disclosure**: Not touched. Protection concerns people, not course content.
- **V. CBC fidelity**: Certificates and badges keep the "training completed" wording (spec 013). Only the name shown changes (FR-010).
- **VI. No LMS orientation**: A protected learner does nothing extra to take part (FR-003), and nobody who did not ask is changed by it. Granting protection takes one step by the right person (SC-005).
- **VII. One shape**: The same three levels and the same entitlements apply to every person and every organisation. SIL's Area entries are ordinary organisations (constitution 2.1.0).
- **VIII. Language data**: Not touched. Pseudonyms are free text in any script.
- **IX. Flat cost, field-ready**: No paid service. Protection holds in the Moodle app (FR-002) and in offline-synced data.
- **X. Traceable and verified**: Adds row #26. Every view in FR-002 is verified on the 5.2.3+ instance, on web and app, before the plan depends on it. Gaps are listed, not shipped silently (FR-015). SC-007 needs real protected users.
- **XI. Survives an upgrade**: Core settings and capabilities first, then a maintained plugin, then our own plugin on supported extension points. No core or theme edits to hide a name.
- **Platform & delivery**: One instance and one role set. Protection is a user-level property, not a separate site or category.

## Dependencies

- **011 handoff decision (2026-10-02)**: the open-by-default direction that makes this spec necessary. The open-courses change (spec 002 amendment) and this spec should land together, so no protected user is exposed in between.
- **002-org-structure-cohorts**: the experience profile fields that FR-001 hides at higher levels, `ltct_org`, which stays visible, and the organisation manager role in FR-006.
- **002 Areas amendment (2026-10-03, PR #87)**: SIL and SIL Partner as Area entries, ALTCs as their managers, and the country-to-Area map kept out of the repo.
- **003-mentor-role**: the mentor assignment that entitles a mentor to the real identity (FR-006).
- **012-assignments-peer-review and the 008 re-plan**: the course-level Course mentor role, and spec 008's per-mentor group `ltct:mentorgroup:<mentor id>` in each course, which entitles a course mentor to the learners in it (FR-006).
- **004-progress-reporting**: the per-organisation progress report, scoped by the organisation's member cohort rather than by `ltct_org` (plan decision 2, option a).
- **011-events-calendar**: calendar views, booking and feeds (FR-011).
- **012-assignments-peer-review**: anonymous peer review and course discussions (FR-002, FR-003).
- **013-certificates-badges**: certificates and public badge verification (FR-010).
- **016 → 008-admin-tooling**: protection is granted at intake, so 008's intake calls this spec's service, records that the person asked and that their email was checked (FR-008, FR-016), and holds back a row whose level cannot yet be set. 008 gives everyone their email as username and turns on sign-in by email; the general account rules (`auth`, `registerauth`, `authpreventaccountcreation`, `protectusernames`) are declared in 008's `admin.yaml`. 008's course-mentor sync stays off until this spec's narrowed course-mentor path is merged (plan, Cross-spec effects).
