# Feature Specification: Simple Learner Experience

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver REQUIREMENTS.md row #13, Simple learner experience (Must): a partner learner can log in to the training system and know what to do without any Moodle orientation — a trimmed dashboard, a course layout that shows a course as a short sequence of lessons, a clean list of their courses, a light theme tweak, and the same experience in the Moodle Android app. Row #13 is a 'simple' row: it is not done until 2–3 real partner learners have used it and their findings are recorded."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - First login, first lesson, no help (Priority: P1)

A partner learner has been enrolled by their organisation. They receive their login, sign in for the first time and, without having been shown Moodle, can see which course they are on and open its first lesson. Nothing on the landing page competes with that: no empty blocks, no administrator-oriented panels, no "timeline" of unrelated items.

**Why this priority**: This is the whole of INTENT outcome B.4 on the learner side, and the constitution's "no LMS orientation" rule. If a learner stalls here, nothing else in the training system reaches them.

**Independent Test**: Enrol a test learner in one published course, give them only the login details, and observe whether they reach the first lesson unaided and how long it takes.

**Acceptance Scenarios**:

1. **Given** a learner enrolled in one course, **When** they log in for the first time, **Then** the first page they see names that course and offers one obvious way into it.
2. **Given** a learner who has finished part of a course, **When** they log in again, **Then** the landing page offers to take them to where they left off.
3. **Given** a learner enrolled in no course yet, **When** they log in, **Then** they see a plain statement that nothing is assigned yet and who to contact, not an empty dashboard.

---

### User Story 2 - A course reads as a short, ordered set of lessons (Priority: P1)

Inside a course, the learner sees the lessons in order, each with its title and estimated time, a clear sign of which ones they have completed, and the quiz where it belongs. Moving from one lesson to the next needs no knowledge of Moodle's navigation.

**Why this priority**: Every published course has the same shape (INTENT A.1); the learner should see that shape rather than Moodle's default page furniture. Equal to P1 because a learner who reaches a course but cannot follow it is still stuck.

**Independent Test**: Open a published pipeline course as a test learner and complete two lessons in sequence using only what is on screen.

**Acceptance Scenarios**:

1. **Given** a published course, **When** a learner opens it, **Then** they see its lessons in order with each lesson's estimated time and their completion state.
2. **Given** a learner at the end of a lesson, **When** they want to continue, **Then** a visible "next" route takes them to the following lesson or the quiz.
3. **Given** a lesson whose overview video is not yet recorded or is unreachable offline, **When** the learner opens it, **Then** the lesson's text and screenshots still carry the content.

---

### User Story 3 - The same experience in the Android app (Priority: P2)

A consultant working in the field uses the Moodle app on an Android phone or tablet. What they see — their courses, a course's lessons, the route to the next lesson — matches the web experience closely enough that nothing learned on one confuses them on the other, and they can find how to keep a course for offline use.

**Why this priority**: INTENT names Android and offline as requirements, not preferences. It follows P1 because the app inherits most of its layout from the site configuration built for P1–P2.

**Independent Test**: With a test account on an Android device, sign in to the app, open a course, make it available offline, switch the device offline and continue a lesson.

**Acceptance Scenarios**:

1. **Given** a learner signed in to the app, **When** they open it, **Then** they see the same courses, in the same order, as on the web.
2. **Given** published callouts and screenshots, **When** a lesson is viewed in the app, **Then** they render as on the web.
3. **Given** a learner who wants to work offline, **When** they look for how, **Then** the course offers a visible way to download it, and the downloaded lessons open with no connection.

---

### User Story 4 - Knowing what comes next after a course (Priority: P3)

A learner who has completed a course sees, from the same landing page, where they can go next: the next course on their pathway, the community space, and how to reach their mentor — without searching.

**Why this priority**: INTENT B.2 and B.3 say learning continues beyond a course. The destinations themselves are other specs; this story only makes them findable from the learner's landing page.

**Independent Test**: As a test learner with a completed course, confirm the landing page shows at least one onward route and that each route works.

**Acceptance Scenarios**:

1. **Given** a learner who has completed a course that belongs to a pathway, **When** they return to the landing page, **Then** it shows the next course on that pathway.
2. **Given** a learner with an assigned mentor, **When** they look for help, **Then** a route to contact the mentor is visible from the landing page or the course.

---

### Edge Cases

- A learner enrolled in many courses at once: the landing page must still point to one clear "continue" action rather than an unordered wall of cards.
- A learner whose interface language is not English: the trimmed landing page must not rely on custom text that exists only in English (interface languages are delivered by 010).
- A small screen at the narrowest supported phone width: nothing essential (course name, next lesson) may be off-screen or behind a menu.
- A learner who is also a mentor, or a manager: the simplification must not hide what their other role needs; each role sees what it needs and no more.
- A withheld quiz (disclosure boundary failed closed): the learner sees the existing placeholder, which names who to ask, not a broken link or an empty section.
- A theme or Moodle upgrade that resets settings: the experience must be re-applied from the repo, not re-clicked.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The landing page a learner sees after login MUST show their enrolled courses and one obvious "continue" route, and MUST NOT show blocks, panels or links a learner does not need.
- **FR-002**: A learner with no enrolment MUST see a plain message saying nothing is assigned yet and who to contact.
- **FR-003**: A published course MUST present its lessons as an ordered sequence showing each lesson's title, estimated time and the learner's completion state.
- **FR-004**: Each lesson MUST offer a visible route to the next lesson or quiz without the learner using Moodle's own navigation menus.
- **FR-005**: Completion state MUST be tracked per lesson and per course so that "where you left off" and "completed" are shown accurately (tracking itself is delivered by 004; this spec requires it to be visible to the learner).
- **FR-006**: The Moodle Android app MUST present the same courses, lesson order and completion state as the web, and published pages MUST render correctly in it.
- **FR-007**: A learner MUST be able to find, from within a course in the app, how to make it available offline.
- **FR-008**: Where they exist for that learner, the landing page MUST show onward routes — next pathway course, community space, mentor contact (destinations delivered by 006, 005 and 003).
- **FR-009**: Every setting, theme adjustment, dashboard default and course-format choice this feature depends on MUST be applied from the repo's training-system configuration, so a rebuilt server presents the same experience with no manual steps.
- **FR-010**: The experience MUST use core Moodle capability where it exists; a plugin or theme change is used only where core cannot achieve a requirement here, and is pinned to the version verified.
- **FR-011**: Nothing the learner sees MUST describe a completion, badge or level as "certified", or state that the learner has reached a CBC level; where a course's target level is shown, it MUST use CBC vocabulary (for example `2 - With Assistance`) and read as the course's aim.
- **FR-012**: Learner-facing text added by this feature MUST be translatable through Moodle's interface language mechanism, not hard-coded in one language.
- **FR-013**: Nothing in this feature MAY expose a design doc, mentor guide, video script or answer key on any learner-visible page.
- **FR-014**: The feature MUST NOT be marked done until 2–3 real partner learners have used it (for example at a stage-7 pilot) and their findings are recorded, de-identified, with each finding either resolved or consciously accepted.

### Key Entities

- **Learner landing page**: what a learner sees after login — their courses, the continue route, onward routes.
- **Course view**: one course presented as its ordered lessons and quiz, with estimated times and completion.
- **Pilot finding**: one observation from a real partner learner's use — what they tried, where they stalled, what was changed — recorded without names, emails or other identifying detail.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: At least 2 of 3 (and never fewer than 2) real partner learners in the pilot reach the first lesson of their course within 5 minutes of first login, with no help and no prior Moodle orientation.
- **SC-002**: From the landing page, a returning learner reaches the lesson they left off in no more than 2 taps or clicks, on web and in the app.
- **SC-003**: A pilot learner completes two consecutive lessons using only on-screen routes, with zero uses of Moodle's own navigation menus.
- **SC-004**: In the app, a downloaded course's lessons open and read fully (text and screenshots) with the device offline.
- **SC-005**: A server rebuilt from the repo plus a data restore presents the identical learner experience with zero manual admin-interface steps.
- **SC-006**: Every pilot finding is recorded and each is marked resolved or accepted before the row is marked done.

## Assumptions

- Pilot learners are the stage-7 pilot learners of an early course, who are real partner learners; no separate usability study is needed if the pilot is observed and findings captured.
- "Real partner learners" means people from a partner organisation who would use the system for real, not department staff standing in for them.
- Findings are recorded in the repo only in de-identified form (role, device, what happened); names, emails and anything else identifying stay out of the repo (Principle III).
- The default Moodle course format that shows one topic per lesson is expected to meet FR-003; a third-party course format is considered only if verification shows core cannot.
- Interface translation is delivered by 010; this spec only requires its own text to be translatable.
- The experience is built and piloted on the temporary instance; production learners wait for the dedicated VPS (015).
- Mentor, pathway and community destinations may not exist when this ships; FR-008 degrades to showing only those that do.
- Offline quizzes (from spec 009, research.md R4): in the Moodle app a learner must open the quiz once while still online, because downloading it is what starts the attempt. Learner help must say so, and must say how to sync by hand, since the app's per-user Wi-Fi-only sync setting can hold a finished attempt until the learner is on Wi-Fi or syncs manually. The live device check in 009 (quickstart V7) confirms the exact wording needed. Checked 2026-10-02 (Moodle app 5.2.1, Samsung S24+): the open-online-first step holds; after an offline submit the quiz shows "This quiz has offline data to be synchronized", and with default settings it synced by itself on mobile data within a minute of reconnecting. So manual sync is the fallback to mention, not the main path.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 13 | Simple learner experience | Must | Trimmed landing page, course-as-lessons view, clean course list, light theme adjustment, app parity and offline discoverability, and the 2–3 real-learner test that makes it done. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: No content is edited in Moodle; the course view presents what the one-way publish delivered. Nothing syncs back.
- **II. Portability / config-as-code**: Every dashboard default, theme adjustment and course-format choice is applied from `moodle/` (FR-009, SC-005); nothing is only clicked in. Content stays markdown; no content is bound to a theme.
- **III. Public repo, private people**: Pilot findings are de-identified before they are recorded; no learner names, emails or progress data enter the repo. Testing uses test accounts.
- **IV. Disclosure boundary**: The feature changes presentation only; FR-013 forbids exposing withheld material, and the withheld-quiz placeholder is kept.
- **V. CBC fidelity**: FR-011 — no "certified", no awarded level, CBC vocabulary only.
- **VI. No LMS orientation**: The feature exists to satisfy this rule; SC-001 and SC-003 measure it with real learners.
- **VII. One shape**: One learner experience for every partner and every course; no per-partner dashboard or theme.
- **IX. Flat cost, field-ready**: Built for the Android app and offline (US3, SC-004); lessons do not depend on video. No paid theme or plugin.
- **X. Traceable and verified**: Cites row #13; every setting is verified on the temporary 5.2.3+ instance before the plan depends on it; not done until 2–3 real partner learners have used it and findings are recorded (FR-014). No recurring operations are added beyond the server itself, whose operator is undecided (015).
- **Platform & Delivery**: Core first (FR-010); Moodle core never modified; any plugin pinned; one instance, one experience for all partners; server always from `MOODLE_URL`.

## Dependencies

- **001-site-config-as-code** — the mechanism that applies and re-applies every setting here; mobile baseline (#4).
- **002-org-structure-cohorts** — learners are enrolled through their organisation's cohorts.
- **004-progress-reporting** — completion tracking the learner's view displays.
- **003-mentor-role**, **005-community-space**, **006-learning-pathways** — destinations for US4 (optional for P1–P3).
- **009-low-bandwidth-delivery** — offline download and light pages the app experience relies on.
- **010-multilingual** — interface languages for learner-facing text.
- **015-production-hosting-ops** — production learners are not taken live until the dedicated VPS exists.
