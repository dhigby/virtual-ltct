# Feature Specification: Community Space Beyond Courses

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver a standing community space for learners, mentors and contributors that is not tied to any course enrolment, from `moodle/REQUIREMENTS.md` rows #9 (Community beyond courses), #20 (Community channels / topic groups) and #10 (Peer-to-peer interaction, outside courses; in-course discussion is 012). Following INTENT's default, start with a standing community course inside Moodle with topic forums, and define the engagement signal that would trigger reconsidering a bolt-on such as Discourse or Matrix."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Anyone can reach the community, and it outlasts their courses (Priority: P1)

A learner who has just finished their only course can still reach the community from where they land after logging in, on the web or in the Moodle app. They did not have to find or enrol in anything: every learner account is a member from the start, and stays one when their course enrolments end.

**Why this priority**: "Learning continues beyond a course" (INTENT B.2) is the whole point; a space that learners must discover and join by themselves is one most never find.

**Independent Test**: Create a test learner, enrol them in one course, complete and unenrol it; the learner still reaches the community in one step from their landing page, on web and app.

**Acceptance Scenarios**:

1. **Given** a newly created learner account, **When** the learner first logs in, **Then** the community is visible from their landing page with no enrolment step.
2. **Given** a learner whose course enrolments have all ended, **When** they log in, **Then** the community is still there and their posts are intact.
3. **Given** a learner using the Moodle app, **When** they open the community, **Then** they can read recent discussions and write a reply, including composing one offline that is sent when they reconnect.

---

### User Story 2 - Topic channels where peers answer each other (Priority: P1)

A consultant stuck on a keyboard problem posts in the topic for that area, and another consultant or a mentor answers. Topics follow the competency categories so people find the right place without being told, plus a general space for introductions and anything else. Members can follow the topics they care about and get a digest rather than one email per post.

**Why this priority**: Topic groups (row #20) and peer help (row #10) are what make the space worth visiting; an empty general forum is not a community.

**Independent Test**: As two test learners and a test mentor, ask and answer a question in a topic forum, mark the helpful answer, and confirm the asker receives notification by their chosen route.

**Acceptance Scenarios**:

1. **Given** the community, **When** a learner opens it, **Then** they see one topic space per competency category plus general and announcement spaces, named in plain language.
2. **Given** a question posted in a topic, **When** another member replies, **Then** the asker is notified by their chosen route (digest by default).
3. **Given** a member who follows only two topics, **When** others post elsewhere, **Then** they receive nothing about the unfollowed topics.

---

### User Story 3 - A partner's people have their own room (Priority: P2)

A partner organisation's learners, mentors and managers have a space within the community that only their organisation can see, for their own announcements and conversations, alongside the shared topics everyone uses. It is set up the same way for every partner, using the organisation and cohort structure from 002 — no special roles per partner.

**Why this priority**: Partners asked to be grouped with their own colleagues (INTENT), but the shared topics already deliver community without it.

**Independent Test**: With two test organisations, a learner of organisation A sees A's room and the shared topics, and cannot see or find B's room.

**Acceptance Scenarios**:

1. **Given** a learner of organisation A, **When** they open the community, **Then** they see organisation A's room and not organisation B's.
2. **Given** a new partner organisation is added under 002, **When** the configuration is applied, **Then** its room exists with no hand setup.

---

### User Story 4 - Moderators keep it safe (Priority: P2)

Named moderators (from the existing role set) can pin, move, lock and remove posts, and any member can report a post. Community guidelines are one short page members see when they first post.

**Why this priority**: A shared worldwide space across organisations needs someone able to act, but moderation matters only once there is traffic.

**Independent Test**: A test learner reports a post; a test moderator sees the report and removes the post; the learner cannot remove others' posts.

**Acceptance Scenarios**:

1. **Given** a reported post, **When** a moderator reviews it, **Then** they can remove, move or lock it, and the reporter is not exposed to the poster.

---

### User Story 5 - The maintainer knows when the space has outgrown Moodle (Priority: P3)

Each quarter, the maintainer gets an engagement review built from aggregate figures and from what partners and mentors report. It says plainly whether the reconsideration trigger (below) has fired. If it has, the next step is a recorded decision, not a quiet migration.

**Why this priority**: INTENT's default is conditional ("move only if engagement shows the need"); without a defined signal, that condition can never be judged.

**Independent Test**: With test activity, the quarterly figures can be produced from Moodle without exporting any learner-identifying data, and each trigger condition can be evaluated from them.

**Acceptance Scenarios**:

1. **Given** a quarter has ended, **When** the maintainer runs the engagement review, **Then** they receive the figures the trigger needs, in aggregate, with no names.
2. **Given** the trigger has fired, **When** the maintainer reviews it, **Then** the outcome is recorded as a decision in `INTENT.md`, and any bolt-on becomes its own spec.

### Engagement signal and reconsideration trigger

Low engagement on its own is **not** a reason to bolt on another system: a better tool does not fix a community nobody has time for. The trigger looks for evidence that people want to talk and Moodle is what stops them. Measured quarterly, starting once the community has been open to live (production) learners for two full quarters:

- **Base condition (demand is real)**: at least 15% of learners active on the site in the quarter posted or replied in the community, *or* community posts grew quarter on quarter for two quarters running.
- **And at least one obstacle signal**:
  1. **Unanswered help**: more than 30% of questions in topic spaces have no reply after 7 days, while the base condition holds.
  2. **Conversation moving off-platform**: mentors or managers from at least 2 partner organisations report that their learners' tool discussions have moved to an outside channel (for example a messaging group) because the community is harder to use.
  3. **Tool named as the barrier**: in the quarter's feedback from real partner users, at least 2 of 3 organisations asked say the space "feels like a course" or name a missing capability (such as real-time chat) as why they do not use it.

When the base condition holds and any obstacle signal fires, the maintainer reconsiders. When neither holds for a year, the review also asks whether the community course should be simplified or retired.

### Edge Cases

- A learner posts real project data in a minority language: moderators do not judge, translate or "correct" it, and no automated moderation assesses minority-language text.
- A learner posts a quiz answer from a course: moderators may remove it; the course's disclosure boundary is not weakened by the community.
- A republish of any course never touches the community space or its posts.
- A learner leaves their organisation: they lose that organisation's room but keep the shared topics.
- A learner deletes their account under a future "delete my data" process: their posts are handled by that process, not by this feature.
- More than 50 app users want push notifications: the default route is email digest, so reach does not depend on the app plan.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST provide one standing community space inside Moodle, using core capability, open to every learner, mentor and contributor account, independent of any course enrolment.
- **FR-002**: Every new account MUST be a community member automatically, and membership MUST persist when course enrolments end.
- **FR-003**: The community MUST be reachable in one step from a learner's landing page, on web and in the Moodle app.
- **FR-004**: The community MUST offer one topic space per competency category in `competencies.yaml` (excluding the `Meta` placeholder category), plus a general space and a read-only announcements space. The topic list MUST be derived from repo data, so a category added to the framework adds a topic on the next configuration apply.
- **FR-005**: Topic spaces MUST support question-and-answer style peer help, with replies, attachments of screenshots, and a way to show which reply helped.
- **FR-006**: Members MUST be able to follow or unfollow individual topics and choose digest or per-post notification; the default MUST be a daily email digest.
- **FR-007**: Each partner organisation (from 002) MUST have a room visible only to its own members, created by configuration with no per-partner roles.
- **FR-008**: Moderators MUST be able to pin, move, lock and remove posts; members MUST be able to report a post; guidelines MUST be shown on first post.
- **FR-009**: The community's structure and settings MUST be defined under `moodle/` and applied by script; its posts are learner data and MUST stay in Moodle only.
- **FR-010**: The community MUST be kept distinct from published courses, so that no publish ever creates, changes or removes it.
- **FR-011**: The system MUST produce the quarterly aggregate figures the engagement signal needs (active learners, community posters, post counts, unanswered-after-7-days rate) without exporting names or other identifying data.
- **FR-012**: The app MUST allow reading recent discussions and composing replies offline, sent on reconnect.

### Key Entities

- **Community space**: the standing, non-course space holding all topics and rooms.
- **Topic space**: a discussion area for one competency category, or general/announcements.
- **Organisation room**: a topic space visible only to one partner organisation's members.
- **Engagement review**: a quarterly, aggregate-only assessment of the trigger conditions, whose outcome is recorded as a decision.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of test learner accounts reach the community in one step from their landing page, on web and app, with no enrolment action.
- **SC-002**: 2–3 real partner learners find the right topic and post a question without help, and their findings are recorded.
- **SC-003**: In a two-organisation test, 0 posts from one organisation's room are visible to the other.
- **SC-004**: A rebuilt test server has the same community structure, topics and rooms, with no admin-UI steps.
- **SC-005**: Each quarterly engagement review can be produced within one hour and states whether the trigger fired.

## Assumptions

- INTENT's default is taken as decided for this spec: start inside Moodle; a bolt-on is a later, separate spec only if the trigger fires.
- Topics follow the five teachable competency categories (the `Meta` placeholder excluded) because contributors and learners already navigate by them; finer topics can be added by configuration.
- The thresholds (15%, 30%, 7 days, 2 organisations) are informed starting points; changing them is a small edit, recorded in the next review.
- Direct messaging (row #19) is baseline configuration in 001; mentor relationships are 003; in-course discussion is 012; community events are 011.
- The interface language follows each user's setting (010); posts may be in any language.
- Build and pilot happen on `ltuse.net`; the engagement signal is only measured on production (015).

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
| --- | --- | --- | --- |
| 9 | Community beyond courses | Must | A standing community space independent of enrolment, reachable from the landing page and the app. |
| 20 | Community channels / topic groups | Must | Topic spaces per competency category, organisation rooms, follow/digest. |
| 10 | Peer-to-peer interaction | Must | Peer Q&A outside courses. In-course peer discussion is delivered by 012. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: the community's structure is configuration in the repo; posts never sync back; the publisher never touches the community (FR-010).
- **II. Config as code**: structure, topics, rooms, roles and notification defaults are applied from `moodle/` and rebuildable (FR-009, SC-004).
- **III. Public repo, private people (NON-NEGOTIABLE)**: posts and engagement data stay in Moodle; the quarterly review uses aggregate figures and only its decision is recorded in the repo (FR-011).
- **IV. Disclosure**: the community does not publish course material; moderators may remove posted answer keys.
- **V. CBC fidelity**: topics use competency category names verbatim; nothing in the community awards or implies a CBC level.
- **VI. No LMS orientation**: automatic membership and one-step reach (FR-002, FR-003); tested with real partner learners (SC-002).
- **VII. Standardisation**: every partner room is created the same way; no per-partner roles.
- **VIII. Language humility**: no human or automated moderation judges minority-language text (Edge Cases).
- **IX. Flat cost, field-ready**: core capability, no new system or per-learner cost; email digest default avoids the 50-device push limit; offline reading and posting in the app (FR-012).
- **X. Traceable and verified**: cites rows #9, #20, #10; automatic membership, offline posting in the app and room isolation MUST be verified on the temporary 5.2.3+ instance before the plan depends on them; "simple" success needs 2–3 real partner users. Moderation is a recurring human burden with no named owner yet, and the space's operations depend on the undecided Moodle operator (015); this spec does not claim either is covered. A bolt-on, if ever triggered, must name its operator and flat cost in its own spec.
- **Platform & Delivery**: core first, bolt-on last; one instance; server from `MOODLE_URL`.

## Dependencies

- **001-site-config-as-code** — configuration mechanism, messaging and notification baseline.
- **002-org-structure-cohorts** — organisations and cohorts that define rooms and automatic membership.
- **003-mentor-role** — mentors as community participants.
- **007-learner-experience** — the landing page the community is reached from.
- **010-multilingual** — interface language per user.
- **012-assignments-peer-review** — in-course discussion (the rest of row #10).
- **015-production-hosting-ops** — production host, operator, and the app plan behind push notifications.
