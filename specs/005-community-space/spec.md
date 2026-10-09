# Feature Specification: Community Space Beyond Courses

**Feature Branch**: `specs/moodle-requirements` (planned on `005-community-space`)

**Created**: 2026-09-30 · **Revised**: 2026-10-06, from the maintainer's communication purposes; 2026-10-07, with the maintainer's decisions on the plan's questions (plan.md, "Decisions"), and again the same day with his round-2 decisions on the checker's findings (plan.md, "Round 2 (Doug, 2026-10-07)")

**Status**: Draft

**Input**: User description: "Deliver a standing community space for learners, mentors and contributors that is not tied to any course enrolment, from `moodle/REQUIREMENTS.md` rows #9 (Community beyond courses), #20 (Community channels / topic groups) and #10 (Peer-to-peer interaction, outside courses; in-course discussion is 012). Following INTENT's default, start with a standing community course inside Moodle with topic forums, and define the engagement signal that would trigger reconsidering a bolt-on such as Discourse or Matrix."

## Communication purposes

*Stated by Doug Higby, maintainer, 2026-10-06. These are authoritative for this spec: every user story and requirement below serves one of them, and no other purpose is assumed.*

1. **Mentor ↔ mentee.** Back-and-forth between a mentor and their mentee. For the busy mentor it is very important that it comes **by email** and that they can **reply by email**. For the student it happens **in the course**, in the browser or the app.
2. **Peer review.** Mentors are scarce, so certain courses have peer review: responses to the scenario bank are sent to peers to review. The peers are identified at the beginning of the course.
3. **In a cohort**, several kinds:
   - (a) between fellow students in the same course, including peer review of work;
   - (b) between fellow members of the same cohort **outside a particular course**, on friendly and team-building topics as well as problem-related ones;
   - (c) between a mentor and the cohort members.

**Identity** (Doug, 2026-10-07, round 2). Everyone appears by their recognisable full name, as Moodle normally shows it, in posts, participant lists and emails. Only a person protected under spec 016 shows a pseudonym instead: "the protected one is the exception, not the rule. Make the protected person suffer and not everybody else." Users are never shown login usernames, numeric ids or codes: not in posts, not in forum email, not in a space's name. Everyone signs in with their email; apart from the email address course staff see (Doug, 2026-10-05; for space mentors, round 2), no one is shown another person's login.

**Openness.** Conversations are open to everyone who is part of that course or that cohort.

**Offline and poor connections.** We accept whatever Moodle and the free Moodle app can do today.

**Today's baseline.** Many regional Language Technology groups already communicate through WhatsApp. The spaces here start beside that, not on empty ground.

**Who owns which purpose.** Purpose 2 and purpose 3(a) are spec 012's (peer review; the course discussion forum `ltct:<slug>:discussion`, already built). Who a mentor is belongs to spec 003, and enrolling mentors into courses belongs to spec 008. What a cohort is belongs to spec 002. This spec owns the cohort spaces (3b, 3c), the email route that lets a mentor work from their inbox (1), and the site-wide forum and email behaviour those spaces and 012's forum share. It references the rest and does not duplicate it.

**Not named by Doug.** A site-wide space open to every account (User Story 1 of the first draft) was not among the purposes. **Deferred** (Doug, 2026-10-07, Q1): it is out of this build and revisited after the engagement reviews. Its text is kept below, marked deferred, and nothing else in this spec depends on it.

**Which cohorts** (Doug, 2026-10-07, Q2; round 2). Two kinds of cohort get a space: a **teaching cohort**, the ALTC-assembled group spec 002 defines, and the cohort of an **organisation entry that opts in** by declaring `space: area` in `organisations.yaml` (an Area space; in practice SIL's and SIL partners' Area entries, 002 FR-014). Any organisation entry may opt in the same way; `independent` and spec 016 neutral entries never can (validate refuses `independent`; a neutral entry is refused by a review check, since marking it neutral in the public repo would point at the people it protects; Doug, 2026-10-07, round 2).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A mentor works from their inbox; the mentee works in the course (Priority: P1) — purpose 1

A mentee posts, from the browser or the app, in a forum where their mentor is set to per-post mail: a course forum where that person is their course mentor (spec 008; by default their spec 003 mentor), or the Problems forum of a teaching-cohort space where they are a recorded cohort mentor. Their mentor receives it as an email, replies to that email, and the reply appears as a post the mentee reads in the browser or the app, where they answer it. The mentor never has to log in to keep a conversation going; starting one, posting a prompt or pinning needs a login (research R1). The thread is open to everyone in that course or cohort space. This reads Doug's openness sentence as covering purpose 1. Core forces it, because only an open forum post can be answered by email (research R1, R5); in a shared course it means learners and course mentors of every organisation can read the thread. Doug accepts this as the price of reply by email (2026-10-07, Q23).

**Why this priority**: Mentors are the scarcest people in the programme. A channel that makes them log in to answer is one they answer late or not at all.

**Independent Test**: With a test mentor and a test mentee in one course: the mentee posts in the app; the mentor gets one email for that post (not a digest), replies from their registered address, and the reply appears in the mentee's app after sync. Repeat in a test teaching-cohort space's Problems forum with the cohort's recorded mentor.

**Acceptance Scenarios**:

1. **Given** a mentee posts in a delivery-course forum where their mentor is their course mentor, or in the Problems forum of a teaching-cohort space their mentor is recorded for, **When** the editing delay has passed, **Then** the mentor receives one email for that post, with an address they can reply to. A post in a teaching space's Team forum reaches the mentor in the next digest. An Area space sets no per-post override for anyone; people get only what they subscribe to, in their own digest setting (FR-006a).
2. **Given** the mentor replies to that email from their registered address, **When** Moodle next collects mail, **Then** the reply appears as a post in the same discussion, under the mentor's full name (or their pseudonym, if spec 016 protects them).
3. **Given** the mentor replies from a different address, or to an old email, **When** the reply arrives, **Then** Moodle asks the mentor to confirm by email before posting, and does not post silently or lose it.
4. **Given** a mentor's account is set to the site's daily forum digest, **When** they are assigned as a mentor, **Then** they still receive individual, answerable emails for the forums they mentor in, set for them automatically, not left to them (Q9). In a teaching-cohort space this covers the Problems forum; Team stays on the digest (Q10). In an Area space no override is set for anyone: a mentor there gets only the discussions they subscribe to, in their own digest setting, and can reply by email only if they change that setting themselves (FR-006a).
5. **Given** a mentee is offline, **When** they write a reply in the app, **Then** it is sent when they reconnect.
6. **Given** the mentor's emailed reply has posted, **When** the mentee uses the app, **Then** they are notified of it (push or popup) without waiting for a daily digest, because the mentee is set to per-post mail on each delivery-course forum where they have a mentor and on their teaching space's Problems forum (Q9, round 2; on the site's digest default a mentee would get no per-post push, popup or email at all, research R4).
7. **Given** a person already chose their own digest setting for a forum, **When** sync sets overrides, **Then** their choice is kept, and removing a mentor later never erases it (FR-005a).

---

### User Story 2 - A cohort has its own standing space, outside any course (Priority: P1) — purpose 3(b)

The members of one cohort can reach a space of their own from their landing page, in the browser and the app, whether or not they are in a course together right now. It carries the cohort's own name, never a number or a code. It has two forums, one for friendly, team-building talk and one for problems and questions (Q12). Everyone in the cohort can read and post, see who else is a member and message them (Q6); nobody outside it can see it. Members are added and removed with the cohort itself; nobody joins by hand. A teaching cohort's space and an Area's space work the same way; they differ only in how mail is sent (FR-006a).

**Why this priority**: This is the space the regional WhatsApp groups show people already want, and it is the part with no home in Moodle today.

**Independent Test**: With two test cohorts, a member of cohort A reaches A's space in one step on web and app, reads and posts there, and cannot see, find or open B's space by browsing, searching or direct link.

**Acceptance Scenarios**:

1. **Given** a person is added to a cohort, **When** they next load their landing page, **Then** the cohort's space is there with no enrolment step, under the cohort's own name.
2. **Given** a member of cohort A, **When** they browse, search or follow a link to cohort B's space, **Then** they cannot see it or its posts.
3. **Given** a person is removed from the cohort, **When** they next log in, **Then** they no longer reach the space and get no more of its mail; the posts they made stay.
4. **Given** a cohort's courses have all ended, **When** members log in, **Then** the space is still there and open for posting. A space has no end state (Q7).
5. **Given** a member opens the space's participant list, **When** they look for another member, **Then** they see them by name and can send them a message (Q6).
6. **Given** a cohort's space has never had a recorded mentor, **When** apply or sync runs, **Then** the space exists but admits no members until a mentor, its moderator, is recorded for the first time (Q13).
7. **Given** a space that has opened loses its last recorded mentor, **When** sync runs, **Then** the space stays open to its members, and drift reports that it has no moderator (Q7, round 2).
8. **Given** a teaching cohort whose space has a recorded mentor is deleted in Moodle, **When** sync and drift run, **Then** the members no longer reach the space, the course and its posts are kept, the mentor still reaches the space and its posts, and drift flags `cohort deleted` for the site team (round 2).

---

### User Story 3 - A cohort's mentor is in the cohort's space (Priority: P1) — purpose 3(c)

The mentor or mentors of a cohort are in its space with the tools to lead it: they can post to everyone, pin a guideline or a weekly prompt, lock, move and remove posts (after logging in), and they moderate it, including posts reported to them as permalinks (Q13; locking by hand, round 2). In a teaching-cohort space they are emailed every post in Problems one at a time and can reply by email, as in User Story 1, and get Team in the daily digest (Q10). In an Area space no mail override is set: they get only the discussions they subscribe to, in their own digest setting (FR-006a). Mentors of other cohorts are not let in, and members' own default mentors are not let in (Q4).

**Why this priority**: Without the mentor the cohort space is a chat room; with them it is where a scarce mentor reaches many people at once.

**Independent Test**: A test cohort mentor is recorded once; they appear in that cohort's space, can post, pin and lock, receive members' Problems posts by email and reply by email; a second mentor recorded for another cohort does not appear.

**Acceptance Scenarios**:

1. **Given** a mentor is recorded as a cohort's mentor, **When** configuration or sync runs, **Then** they are in that cohort's space and can post, pin, lock and reply.
2. **Given** a mentor is recorded for cohort A only, **When** they look for cohort B's space, **Then** they cannot see it.
3. **Given** a member of the cohort whose own default mentor is not the cohort's mentor, **When** sync runs, **Then** that default mentor is not added to the cohort space (Q4).
4. **Given** a member posts in Problems in a teaching-cohort space, **When** the editing delay has passed, **Then** the cohort mentor receives one answerable email for it; a post in Team reaches them in the next digest (Q10).

---

### User Story 4 - Fellow students in a course, and peer review (Priority: P2) — purposes 2 and 3(a), delivered by spec 012

Fellow students in the same course talk in that course's discussion forum, which spec 012 already creates for every published course and keeps open to everyone in it. "Fellow students in the same course" means everyone in the course (Doug, 2026-10-07, Q5). Peer review of scenario-bank responses is spec 012's design. This spec adds nothing to either, except the site-wide forum and email behaviour they share with the cohort spaces (FR-010, FR-011), and four facts handed to spec 012 as re-plan inputs (see Dependencies).

**Why this priority**: The in-course forum exists; peer review is not this spec's to build. The priority reflects this spec's share of the work, not the purposes' importance.

**Independent Test**: In a test course, two learners and a course mentor post in `ltct:<slug>:discussion`; a reply sent by email from a per-post email lands there once incoming mail is on.

**Acceptance Scenarios**:

1. **Given** incoming mail is enabled site-wide, **When** a subscribed member of a course replies to a per-post email from the course forum, **Then** the reply lands in the course forum, with no setting in the course itself.
2. **Given** a shared course, **When** a learner opens its discussion forum, **Then** it holds everyone in the course, with no cohort-only part; a cohort that wants to talk among itself about the course does so in its own cohort space (Q5).

---

### User Story 5 - The maintainer knows when the spaces have outgrown Moodle (Priority: P3)

Each quarter, the maintainer gets an engagement review built from aggregate figures and from what Area coordinators and mentors report. It says plainly whether the reconsideration trigger (below) has fired. If it has, the next step is a recorded decision, not a quiet migration.

**Why this priority**: INTENT's default is conditional ("move only if engagement shows the need"); without a defined signal, that condition can never be judged.

**Independent Test**: With test activity, the quarterly figures can be produced from Moodle without exporting any learner-identifying data, and each trigger condition can be evaluated from them.

**Acceptance Scenarios**:

1. **Given** a quarter has ended, **When** the maintainer runs the engagement review, **Then** they receive the figures the trigger needs, in aggregate, with no names.
2. **Given** the trigger has fired, **When** the maintainer reviews it, **Then** the outcome is recorded as a decision in `INTENT.md`, and any bolt-on becomes its own spec.

---

### User Story 6 - A site-wide space for every account (Priority: deferred) — **Deferred (Doug, 2026-10-07, Q1)**

*Kept from the first draft, not deleted. Doug named no all-accounts space on 2026-10-06 and deferred it on 2026-10-07: it is out of this build and is revisited after the engagement reviews (User Story 5). If it is taken up later, it is built on the same mechanism as the cohort spaces; nothing in this build depends on it.*

Every account can reach one space with topic forums per competency category, a general space and announcements, independent of any course or cohort.

**Independent Test**: As in the first draft: a test learner with no course and no cohort reaches the space in one step on web and app.

**Acceptance Scenarios**:

1. **Given** a newly created account, **When** the person first logs in, **Then** the site-wide space is visible from their landing page with no enrolment step.
2. **Given** the site-wide space exists, **When** a cohort space is created or removed, **Then** neither affects the other.

### Engagement signal and reconsideration trigger

Low engagement on its own is **not** a reason to bolt on another system: a better tool does not fix a community nobody has time for. The trigger looks for evidence that people want to talk and Moodle is what stops them. Measured quarterly, starting once cohort spaces have been open to live (production) learners for two full quarters:

*Definitions (Doug, 2026-10-07, Q16, with the round-2 confirmations): a learner is an account holding a Student or `spacemember` enrolment, so a mentor-only account is not one; an active learner is any learner who logged in during the quarter, counted as any logged event in the quarter; only learners count as posters, and a post counts only if its author is not a Course mentor in that course; a mentor counts only in the "answered" figure, where a mentor's reply counts as an answer; counts below 5 are suppressed; figures are site-wide only.*

- **Base condition (demand is real)**: at least 15% of learners active on the site in the quarter posted or replied in a cohort space or a course forum, *or* those learners' posts grew quarter on quarter for two quarters running.
- **And at least one obstacle signal**:
  1. **Unanswered help**: more than 30% of discussions opened in the problems forums of cohort spaces have no reply after 7 days, while the base condition holds.
  2. **Conversation moving further off-platform**: WhatsApp is already where regional groups talk, so its presence proves nothing. At the first review, each Area coordinator asked records a baseline: where their group's tool-help questions mostly go today (Moodle, WhatsApp, or both). The signal fires when coordinators from at least 2 organisations or Areas report, against their own baseline, that tool-help talk has moved *towards* WhatsApp over the last two quarters, or that people who started in the cohort space have gone back to WhatsApp, because the space is harder to use. A self-reported baseline is good enough (Doug, 2026-10-07, Q16).
  3. **Tool named as the barrier**: in the quarter's feedback from real partner users, at least 2 of 3 organisations asked say the space "feels like a course" or name a missing capability (such as real-time chat) as why they keep using WhatsApp instead.

When the base condition holds and any obstacle signal fires, the maintainer reconsiders. When neither holds for a year, the review also asks whether the cohort spaces should be simplified or retired.

### Edge Cases

- A learner posts real project data in a minority language: nobody judges, translates or "corrects" it, and no automated moderation assesses minority-language text.
- A learner posts a quiz answer from a course in a cohort space: the cohort mentor, its moderator, may remove it; the course's disclosure boundary is not weakened.
- A discussion needs locking: no discussion locks automatically (Q12). In a cohort space the mentor locks it by hand, through the declared override that gives Course mentors `moodle/course:manageactivities` in space courses only (round 2). In a delivery course a Course mentor still cannot lock; the site team does.
- A space mentor uses that override to change a forum's settings or add an activity: drift reports it, and apply restores the declared forum settings (except the subscription mode, which apply sets only at creation and drift reports) (FR-015).
- A member wants to report a post: core has no report button, so they send its permalink to the space's mentor (Q13).
- A republish of any course never touches a cohort space or its posts.
- A person leaves a cohort: they lose its space and its mail; their posts stay, under their name, readable only by current members. Only their contacts, and people they still share an active course with, can still message them; an earlier conversation alone does not keep the route open.
- A person's protection level changes (spec 016): from then on their posts show the name their new level allows; copies already emailed cannot be recalled.
- A space loses its last recorded mentor: it stays open (Q7), and drift reports it has no moderator until the site team records a new one (round 2).
- A mentor replies to a daily digest: digests carry no reply address, so the reply cannot land; the mentor's settings must give them individual emails (US1 scenario 4).
- A person had already chosen a digest setting for a forum before sync reached it: sync keeps it, so that person gets the forum as they chose; if it is a digest, a mentor cannot reply by email there until they change it (FR-005a).
- A mentor replies by email to a *private* reply: core cannot accept it, and the mentor gets an error email. Private replies are therefore not part of the mentor route (FR-004). Course mentors keep the private reply for one-way notes in the browser (Q14).
- A mentor replies after a week: Moodle asks them to confirm by email first (US1 scenario 3).
- A mentor wants to start a conversation, post a weekly prompt or pin a guideline: core accepts only replies by email (research R1), so these need a login in the browser or app. Email covers replying to what mentees and members post.
- A mentor's email client adds a signature, or quotes the earlier mail without `>` (Outlook's From:/Sent:/To: block): the whole body is posted as written, signature and quoted header included, readable by everyone in the space (research R11).
- A mentor replies by email with a large photo, or several images: the forum's attachment limits apply to the total, and an over-limit reply is refused by email rather than posted (research R8).
- The host cannot yet provide incoming mail (spec 015): the plan relies on one IMAP mailbox with `+` subaddressing and cron every minute (Q8); until 015 provides it and instance check V2 passes, the mentor route is one-way email (notify, then log in), and this spec says so rather than claiming purpose 1 is met.
- A cohort is a whole Area of hundreds of people: everyone in it can see and message everyone else (Q6, accepted for Area spaces too). Its forums use Optional subscription and the space sets no per-post override for anyone: people get only what they subscribe to, in their own digest setting, so nobody is sent every post unless they choose it (FR-006a). Its mentors are Course mentors and so see members' email addresses, hundreds of them in an Area (accepted by Doug, 2026-10-05, extended to space mentors including Area spaces on 2026-10-07, round 2).
- A group keeps using WhatsApp beside its cohort space: that is allowed. The space does not link to or mirror the WhatsApp group and has no Moodle group chat (Q15), because a WhatsApp group shows members' phone numbers, outside spec 016.
- A mentor sends a Moodle message from the landing page's mentor link, or receives one: it cannot be answered by email (research R1). The link is kept as it is (Q22); spec 003's mentor onboarding tells mentors to use the forum for anything they want to answer by email (round 2, follow-up for 003); a custom handler for answering messages by email is later work (Q19).
- A teaching cohort is deleted in Moodle: core removes its enrolment, so members lose the space. The course and its posts are kept and the recorded mentor takes over: their own enrolment is not removed with the cohort, so they keep the space and its posts. Drift flags `cohort deleted` for the site team (Doug, 2026-10-07, round 2).
- Two cohorts share a name, or a cohort's name matches an existing course's short name: the space cannot take that name, and apply reports it blocked rather than adding a number or code to the name (FR-006).

## Requirements *(mandatory)*

### Functional Requirements

**Mentor route (purpose 1)**

- **FR-001**: The site MUST accept replies to forum post emails as forum posts (Moodle's incoming mail processing for forums), so that a mentor can reply by email to any per-post email from a forum they can post in. Its settings MUST be declared under `moodle/` and applied by script; its mailbox password MUST come from the environment and never be written into the repo.
- **FR-002**: A mentor MUST receive one email per post, each answerable, for the forums they mentor in: their mentees' delivery-course forums, and the Problems forum of their teaching cohorts' spaces. The site default for forum mail stays a daily digest (row #25). The system MUST set this automatically, as a per-forum override, never by asking the mentor (Q9). A teaching space's Team forum is set to the digest for the mentor (Q10). In an Area space no override is set: the mentor gets only the discussions they subscribe to, in their own digest setting (FR-006a).
- **FR-003**: In a delivery course, a mentor MUST be subscribed only to the discussions their own mentees start or post in, never to the whole forum, so they are not sent every post of every learner in a shared course (Q10, changed by Doug on 2026-10-07 from "start" to "start or post in", so a mentee's reply in a classmate's discussion also reaches their mentor).
- **FR-004**: The mentor route MUST use open forum posts, readable by everyone in that course or cohort space, not private replies, which core can neither answer nor accept by email. Doug accepts this openness, also in shared course forums (Q23).
- **FR-005**: A mentee MUST be able to read the mentor's reply and answer it in the browser and in the free Moodle app, including composing offline for sending on reconnect, and MUST be notified of the mentor's reply without waiting for a daily digest. The system MUST set a per-forum per-post override for each mentee on every delivery-course forum where they have a mentor, and for each member of a teaching space on its Problems forum, with Team on the digest, the same as the mentor (Q9, round 2). An Area space sets no override for anyone (FR-006a). Everyone else keeps the daily digest.
- **FR-005a**: An override MUST NOT replace a digest choice the person made for that forum themselves. The system MUST record which overrides it wrote, and removing a mentor MUST remove only those, never a personal choice; a person who resets an override to their default keeps that reset (round 2). When a person leaves a cohort, core removes their role first, so their overrides are left in place, harmless while they are suspended, and hold again if they rejoin.

**Cohort spaces (purposes 3b, 3c)**

- **FR-006**: Each teaching cohort (spec 002), and each organisation entry that opts in by declaring `space: area` (an Area space; one declared field, round 2), MUST have one space of its own, outside every delivery course, holding its forums. Opting in is per entry and uniform: any organisation entry may declare it, except `independent`, which validate refuses, and spec 016 neutral entries, which a review check refuses: the repo never marks an entry neutral (Principle III), so validate cannot check it (round 2). Its members MUST be exactly the cohort's members plus its recorded mentors, kept in step with the cohort automatically (Q2, Q4). Each space is a course `ltct:site:cohort:<key>` in the visible category `ltct:cohort-spaces`; the key is the numeric Moodle cohort id for a teaching space and the organisation key for an Area space. The course name every user sees MUST be the cohort's own human-readable name (the Moodle cohort name, or the organisation entry's name), never an id or code such as "Cohort space: 42" (round 2). An `INTENT.md` decision and a MINOR constitution amendment (2.1.1 to 2.2.0), drafted in the same PR, allow these courses (Q3). A teaching cohort is created and filled by an ALTC through 002's scripted action; teachers creating cohorts is deferred to spec 017.
- **FR-006a**: A space MUST take one of two declared shapes. **Teaching**: both forums Auto subscription; mentor and members per-post on Problems and digest on Team (Q10; members the same as the mentor, round 2). **Area**: both forums Optional subscription; no per-post override for anyone, mentors included, so everyone keeps their own digest setting. The Area shape exists because an Area has hundreds of members and its people already talk on WhatsApp; Auto subscription with per-post mail would send each of them every post.
- **FR-007**: A cohort space MUST offer exactly two forums, "Team and friendship" and "Problems and questions" (Q12), with screenshots attachable and no automatic locking, and MUST be reachable in one step from the member's landing page on web and app.
- **FR-008**: A cohort's recorded mentors MUST be in its space with the ability to post, pin, lock, move and remove posts and lead it; mentors of other cohorts, and members' default mentors, MUST NOT be. As Course mentors they see members' email addresses, accepted by Doug (2026-10-05, extended to space mentors including Area spaces on 2026-10-07, round 2). The record of a cohort's mentors MUST reuse spec 008's cohort-mentor records, made by the site team through `ltct_admin.py`, rather than add a second way to name mentors (Q4). ALTCs recording their own cohorts' mentors is deferred to spec 017 (round 2).
- **FR-008a**: Members of a space MUST be able to see its participant list and message each other (Q6). Their declared member role MUST keep spaces out of the progress, programme and pilots reports (Q17).
- **FR-008b**: A space MUST stay open for as long as it exists; it has no read-only or end state, and no apply ever deletes it (Q7). This holds after its last mentor is removed: the mentor gate (FR-015) applies only to a space's first opening. If a teaching cohort is deleted in Moodle, its space and posts MUST be kept and its recorded mentor MUST keep access to them; drift MUST flag `cohort deleted` (round 2).
- **FR-009**: A cohort space MUST stand alone: it MUST NOT depend on the deferred site-wide space (FR-012), on any delivery course, or on another cohort's space.

**Shared forum and email behaviour (all purposes)**

- **FR-010**: Every post in a cohort space or a course forum, every participant list and every forum email MUST show the sender by their full name as Moodle normally shows it, or by their pseudonym only when spec 016 protects them. Users MUST never be shown a login username, a numeric id or a code, including in a space's name and in forum email subjects (round 2), apart from the email address course staff see (Doug, 2026-10-05; round 2), which for an account is also its login.
- **FR-011**: The site-wide forum preferences and settings that every forum inherits MUST be declared under `moodle/` and drift-checked: daily digest default (row #25), subscribe on posting, read tracking on for new accounts (Q18), RSS off because an RSS URL is a bearer key (research R8), portfolios off (Q14), the 30-minute editing window (Q11), forum tags on (Q19), and a one-week reply-address expiry (Q20). Read tracking MUST also be switched on once for existing accounts, by a one-off script that writes no learner data to the repo (round 2). This spec MUST NOT change the settings of spec 012's course forum module.
- **FR-011a**: The Course mentor (`teacher`) role MUST NOT be able to export forum posts: `mod/forum:exportforum`, `mod/forum:exportdiscussion`, `mod/forum:exportpost` and `mod/forum:exportownpost` are all removed; portfolios stay off. It keeps the private reply (Q14, round 2). This changes a role specs 012 and 016 also use, and is made with their tests.
- **FR-011b**: Site logs MUST be kept for 365 days (`loglifetime`), enough for a quarterly review run well after quarter end (Q21).

**Site-wide space — Deferred (Doug, 2026-10-07, Q1); not part of this build**

- **FR-012**: *Deferred*: one standing space inside Moodle open to every learner, mentor and contributor account, independent of any course or cohort, membership automatic and persistent.
- **FR-013**: *Deferred*: one topic space per competency category in `competencies.yaml` (excluding `Meta`), plus a general and a read-only announcements space, derived from repo data.
- **FR-014**: *Deferred*: reachable in one step from the landing page on web and app.

**Safety, structure and review**

- **FR-015**: Every cohort space MUST be moderated by its recorded mentor, as Course mentor: pin, lock, move and remove posts (Q13). Discussions never lock automatically (Q12). A mentor locks by hand through a declared override granting `moodle/course:manageactivities` to Course mentors in space courses only; drift MUST report any change to a space's declared forum settings, and apply MUST restore them (except the subscription mode, which apply sets only at creation and drift reports) (round 2). Members report a post by sending its permalink to the mentor; guidelines MUST be visible where members post. A space MUST admit no members until it has had at least one recorded mentor, so no real learner enters a space without a moderator. Once open, it stays open if its last mentor is removed, and drift reports it has no moderator (FR-008b).
- **FR-016**: The spaces' structure and settings MUST be defined under `moodle/` and applied by script; posts, memberships and cohort names are learner data and MUST stay in Moodle only.
- **FR-017**: The spaces MUST be kept distinct from published courses, so that no publish ever creates, changes or removes one.
- **FR-018**: The system MUST produce the quarterly aggregate figures the engagement signal needs (active learners, posters, post counts, unanswered-after-7-days rate) without exporting names or other identifying data, suppressing counts below 5 and giving site-wide figures only (Q16). Posters are learners only, and a post by someone who is a Course mentor in that course never counts; a mentor counts only in the answered figure (round 2). It MUST run on the existing `ltuse_admin` service (Q21).

### Key Entities

- **Cohort**: either a teaching cohort, a group of people taking courses together within an Area with one or more mentors (INTENT, 2026-10-03/04), made by an ALTC as a Moodle cohort; or the cohort `ltct:org:<key>` of an organisation entry declared `space: area` (002 FR-014). Both are defined by spec 002, not here.
- **Cohort space**: a small Moodle course, one per cohort, outside every delivery course, named after its cohort, whose members are kept in step with the cohort; it holds the cohort's two forums. Shape teaching or area (FR-006a).
- **Cohort mentor**: a mentor recorded for one cohort through spec 008's cohort-mentor records (`ltct_admin.py`, by the site team), enrolled in its space as Course mentor; the space's moderator.
- **Space member role** (`spacemember`): the role members hold in a space; it allows seeing participants and messaging, and keeps spaces out of the course reports.
- **Mentor route**: per-post email to a mentor with an inbound reply address, and the reply landing as a forum post; it works in any forum the mentor can post in and is set to per-post mail for.
- **Course forum**: spec 012's `ltct:<slug>:discussion`, open to everyone in the course; referenced, not owned.
- **Site-wide space** *(deferred, Q1)*: the first draft's all-accounts community.
- **Engagement review**: a quarterly, aggregate-only assessment of the trigger conditions, whose outcome is recorded as a decision.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A test mentor completes three rounds of a conversation with a test mentee entirely by email, while the mentee uses only the app, with no message lost and no login by the mentor.
- **SC-002**: 100% of test members of a cohort reach its space in one step from their landing page, on web and app, under the cohort's own name, with no enrolment action, once it has a recorded mentor; 0 posts from one cohort's space are visible to a member of another. Checked for one teaching-shape and one area-shape space.
- **SC-006**: In a test teaching space, the cohort mentor and each member receive each Problems post as one answerable email and Team posts only in the digest; a member receives the mentor's reply as a per-post email or push; in a test Area space, sync sets no override for anyone.
- **SC-003**: 2–3 real partner learners and at least one real mentor use a cohort space and the mentor route without help, and their findings are recorded.
- **SC-004**: A rebuilt test server has the same cohort spaces, forums, mentors and email settings, with no admin-UI steps.
- **SC-005**: Each quarterly engagement review can be produced within one hour and states whether the trigger fired.

## Assumptions

- INTENT's default is taken as decided for this spec: start inside Moodle; a bolt-on is a later, separate spec only if the trigger fires.
- "Cohort" in purpose 3 means both the ALTC-assembled teaching group of spec 002 FR-015/FR-019 and the organisation entries that opt in as Area spaces (Doug, 2026-10-07, Q2). The teaching cohort does not exist as a Moodle object yet; 002 must define it, as a Moodle cohort, before teaching spaces are built. The Area entries exist only once 002 applies FR-014; until then, no Area space is built, because `sil` holds all of SIL and does not opt in.
- "Identified by a username" is decided (Doug, 2026-10-07, round 2): it means people are identified by their recognisable full name; only spec 016 protected people show a pseudonym, and no username is ever shown, apart from the email address course staff see (Identity, FR-010).
- "Fellow students in the same course" (3a) means everyone enrolled in that course, which the existing course forum gives (Doug, 2026-10-07, Q5).
- Reply by email is possible in core only for forum posts. A Moodle private message (spec 003 FR-007) cannot be answered by email, so the mentor route is forum-based. A custom inbound handler for messages is later work, not this build (Q19).
- The production host provides one IMAP mailbox with case-preserving `+` subaddressing and cron every minute, recorded as a spec 015 operator task (Q8); instance check V2 confirms it.
- The thresholds (15%, 30%, 7 days, 2 organisations) are informed starting points; changing them is a small edit, recorded in the next review.
- ltuse.net is on the Premium app plan (unlimited push devices), as `site.yaml` records (Doug, 2026-10-07, round 2). Email stays the dependable route because of the busy mentor and field conditions, not a device cap. Spec 015's FR-010 wording, which reads as if no plan were chosen, needs fixing in 015 (plan, follow-ups).
- The interface language follows each user's setting (010); posts may be in any language.
- Build, verification and pilot happen on `ltuse.net`: nobody uses the current system yet (Doug, 2026-10-07: "Just do it."), so instance checks run there now, with test accounts and test cohorts only (constitution X). The engagement signal is only measured on production (015).

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
| --- | --- | --- | --- |
| 9 | Community beyond courses | Must | A standing space per teaching cohort and per opted-in Area entry, outside every course, reachable from the landing page and the app; the site-wide space is deferred (Q1). |
| 20 | Community channels / topic groups | Must | Cohort spaces with friendly and problem forums; topic forums per competency category only in the deferred site-wide space. |
| 10 | Peer-to-peer interaction | Must | Peer talk and messaging outside courses, in cohort spaces (Q6). In-course discussion and peer review are delivered by 012. |
| 11 | Mentor / trainer interaction | Must | The mentor route: per-post email with reply by email, landing as a forum post (FR-001 to FR-005a); messages stay in-app (#19 note). |
| 25 | Manageable notifications | Pref | Automatic per-forum no-digest override for mentors and mentees, respecting personal choices (Q9, FR-005a); site default digest unchanged; read tracking on for new accounts (Q18) and once for existing ones; incoming mail processing. |
| 19 | Direct messaging | Pref | Not changed; members of a space may message each other (Q6). Recorded: messages cannot be answered by email, which is why the mentor route is forum-based (spec 003); a message handler is later work (Q19). |

Row #26 (identity protection) is relied on (FR-010), not delivered. On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: the spaces' structure is configuration in the repo; posts never sync back; the publisher never touches a cohort space (FR-017).
- **II. Config as code**: spaces, forums, mentor enrolment, incoming-mail settings and forum defaults are applied from `moodle/` and rebuildable (FR-016, SC-004). Cohort definitions are spec 002's, made by a scripted action. The idnumber form `ltct:site:cohort:<key>` is not among II's forms today; it passes once the MINOR amendment drafted in this PR merges (Q3).
- **III. Public repo, private people (NON-NEGOTIABLE)**: posts, memberships, cohort names and engagement data stay in Moodle; a teaching space is keyed by the numeric cohort id, so no cohort name reaches the repo or tool output; the mailbox password comes from the environment; the review uses aggregate figures and only its decision is recorded in the repo (FR-001, FR-018).
- **IV. Disclosure**: the spaces publish no course material; a posted answer key may be removed.
- **V. CBC fidelity**: nothing in a space awards or implies a CBC level.
- **VI. No LMS orientation**: membership follows the cohort with no action; one-step reach; the mentor works from email (SC-001, SC-003).
- **VII. Standardisation**: every cohort space is made from one template in one of two declared shapes, teaching and area, justified in FR-006a; opting in as an Area space is one declared field open to any organisation entry, so it is a uniform variant, not a per-partner special case; no per-cohort roles.
- **VIII. Language humility**: no human or automated moderation judges minority-language text.
- **IX. Flat cost, field-ready**: core capability; offline reading and posting in the app (FR-005). New host burdens, all spec 015 operator tasks (Q8): one IMAP mailbox with `+` subaddressing; cron every minute; outgoing mail volume under the host's send limits; mailbox content retention (it holds learner text); log table growth at `loglifetime` 365 days.
- **X. Traceable and verified**: cites rows #9, #10, #11, #19, #20, #25; the mentor route, cohort isolation and app offline posting MUST be verified on the instance, with test accounts and test cohorts, before the plan depends on them; recurring burdens (mailbox, moderation by each space's mentor) are named in the plan. While the Moodle operator is undecided (`INTENT.md` L68), the operator burdens under IX are named, not covered.
- **XI. Survives an upgrade**: public APIs where core has them. Direct writes: the `messageinbound_handlers` row and `forum_discussion_subs.preference`. Raw reads: `forum_digests`, `forum_discussions` and `forum_posts` for the mentor sync and observer, `forum` for comparing a space forum's declared settings (restored through `update_moduleinfo()`), and the engagement figures' reads. All are listed in `moodle/local_ltuse/README.md` (plan).
- **Platform & Delivery**: one instance; shared courses stay open with no groups; a per-cohort space is a second way to keep a course to one group of people. Doug approved (2026-10-07, Q3; level MINOR, 2.1.1 to 2.2.0, confirmed in round 2) an `INTENT.md` decision and a constitution amendment allowing site-declared cohort space courses; both are drafted in this feature's PR.

## Dependencies

- **001-site-config-as-code** — configuration mechanism, messaging and notification baseline.
- **002-org-structure-cohorts** — MUST choose a **Moodle cohort** as the mechanism for teaching groups (its deferred FR-015/FR-019 plan step leaves "a group inside one course, or a cohort across several" open); 005 requires the cohort, because a teaching space follows a Moodle cohort through `enrol_cohort` and could follow no group. 002 also fixes the teaching cohorts' idnumber namespace and an ALTC-only scripted action to create and fill them (Q2), and applies the Area entries (FR-014) that Area spaces follow.
- **003-mentor-role** — who a mentee's mentor is; its direct messaging cannot be answered by email, which this spec records for 003 to weigh; the landing-page mentor link stays as it is (Q22). Follow-up for 003 (not edited here): its mentor onboarding gains a note telling mentors to use the forum for anything they want to answer by email (round 2).
- **007-learner-experience** — the landing page the spaces are reached from.
- **008-admin-tooling** — cohort-mentor records and sync, extended to cohort spaces (Q4), recorded by the site team; its plan decision 8's reserved rule row for a teaching cohort. Its sync also sets mentors' and mentees' forum digest overrides and mentors' per-discussion subscriptions in delivery courses (research R4, R10; Q9, Q10).
- **010-multilingual** — interface language per user.
- **012-assignments-peer-review** — the course forum (3a) and peer review (2). Re-plan inputs handed to 012: (1) the scenario bank, today a read-only learner page, is the work peers review and has no submission point; (2) peers fixed at course start cannot be done by core workshop allocation, which needs a submission; (3) if "identified" also means that peers know who they are reviewing and who reviewed them, it reverses 012 FR-012a and the live `roles.yaml` override; that is a question for Doug in 012's re-plan, not an instruction, and Doug's 2026-10-07 names rule (Identity) is input to it (input 2 already covers assignment at course start); (4) a course forum of type "each person posts one discussion" can carry scenario-bank responses for peers to reply to, with no submission and no allocation; it cannot fix named reviewers, so input 2 still applies if Doug requires assigned peers. Course mentors' per-discussion subscriptions and mentors' and mentees' digest overrides on `ltct:<slug>:discussion` are per-user data set by 005; the module's settings are untouched (FR-011). The `teacher` role loses all forum export (FR-011a), which 012 is told of.
- **015-production-hosting-ops** — hand-off, decided (Q8): 015 records as an operator task one IMAP mailbox with case-preserving `+` subaddressing, its secret, cron every minute, outgoing send limits, mailbox retention, and log growth at `loglifetime` 365 days. Instance check V2 is still required. Also the operator. Follow-up for 015 (not edited here): FR-010 should record that the Premium app plan is chosen, as `site.yaml` says.
- **016-identity-protection** — pseudonyms for protected people only, in posts and emails; `allowedemaildomains` stays empty; neutral entries can never opt in to a space (FR-006); because no neutral marker may be written to the public repo, this is a review check, and 016's procedure for a neutral entry says never to declare `space: area` on it (plan, follow-ups); the `teacher` role change (FR-011a) is made with 016's tests.
- **017** (not yet written) — teachers creating and filling teaching cohorts (Q2), and ALTCs recording their own cohorts' mentors (round 2), both deferred there.
