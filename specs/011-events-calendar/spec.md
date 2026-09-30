# Feature Specification: Events, office hours and live sessions

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver REQUIREMENTS.md row #21 (Events / calendar, Pref). Learners, mentors and partner organisations need a shared calendar of what is happening — site-wide events, an organisation's own events, course and cohort events — plus mentor office hours that a learner can book, and a way to join a live session. Live sessions supplement courses and are never the only route to content. Events are separated by organisation on the one shared site, work in the Moodle app, and add no per-learner cost."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A learner sees what is coming up, for them (Priority: P1)

A learner opens their dashboard or the Moodle app and sees upcoming events that concern them — site-wide announcements, their organisation's events, their cohort's sessions — each shown in their own time zone. They never see another organisation's events.

**Why this priority**: This is the core of row #21 and the base every other story builds on. Learners are spread across every time zone; an event shown in the wrong time or to the wrong organisation is worse than none.

**Independent Test**: On a rebuilt test instance, create one site event, one event per test organisation and one cohort event; log in as test learners in two organisations and two time zones and confirm each sees exactly their own events at the correct local time, on web and in the app.

**Acceptance Scenarios**:

1. **Given** a site-wide event, **When** any learner views their calendar, **Then** they see it at the correct time in their own time zone.
2. **Given** an event for organisation A, **When** a learner in organisation B views their calendar, **Then** the event does not appear.
3. **Given** a cohort or group event, **When** a learner outside that cohort or group views their calendar, **Then** it does not appear.
4. **Given** a learner using the Moodle app, **When** they have been online once and then go offline, **Then** they can still see the upcoming events already synced.
5. **Given** a learner, **When** they want the events in their own calendar tool, **Then** they can subscribe to or export their events without administrator help.

---

### User Story 2 - An organisation or cohort manager schedules events for their own people (Priority: P1)

An organisation manager creates an event for their organisation or one of its cohorts — a kick-off call, a deadline, a meet-up — without an administrator, and cannot create or see events for other organisations.

**Why this priority**: "A small team can run it without a dedicated LMS administrator" (INTENT B.4). If only the site administrator can post events, the calendar becomes a bottleneck.

**Independent Test**: As a test manager of organisation A, create an organisation event and a cohort event; confirm they reach the right learners, and that the same manager cannot create or view an event in organisation B.

**Acceptance Scenarios**:

1. **Given** an organisation manager, **When** they create an event for their organisation, **Then** all and only that organisation's learners see it.
2. **Given** an organisation manager, **When** they try to create a site-wide event or an event in another organisation, **Then** they cannot.
3. **Given** an event is changed or cancelled, **When** affected learners next look, **Then** they see the change, and learners who opted into notifications are told.

---

### User Story 3 - A learner books time with their mentor (Priority: P2)

A mentor publishes office-hour slots. A learner they support picks a slot, both are reminded, and the booking appears in both calendars. The mentor can see who booked which slot; other learners cannot.

**Why this priority**: INTENT puts the mentor at the centre ("working alongside a mentor who helps them succeed") and asks that learners "reach a mentor over months rather than minutes". Booking removes the email back-and-forth across time zones.

**Independent Test**: As a test mentor, offer three slots; as two test learners, book one each; confirm both calendars, the mentor's view of bookings, and that neither learner sees who holds the other slot.

**Acceptance Scenarios**:

1. **Given** a mentor has offered slots, **When** a learner they support views them, **Then** they see free slots in their own time zone and can book one.
2. **Given** a slot is booked, **When** another learner views slots, **Then** it shows as taken without naming who took it.
3. **Given** a booking, **When** either side cancels within the allowed window, **Then** the slot is freed and the other side is told.
4. **Given** a learner who has no mentor relationship with that mentor, **When** they look for that mentor's slots, **Then** they cannot book them unless the slots were offered openly.

---

### User Story 4 - A learner joins a live session, or catches up if they could not (Priority: P3)

An event for a live session carries a clear "join" link. A learner who cannot attend — offline, on low bandwidth, asleep in another time zone — can still reach the same learning, because a live session only ever supplements course content.

**Why this priority**: Live sessions are useful but optional for this learner base; building for them first would favour learners on good connections.

**Independent Test**: Create a live-session event with a join link and a follow-up note; confirm a learner can join from the web and the app, and that a learner who did not attend can reach the follow-up and complete the related course without having attended.

**Acceptance Scenarios**:

1. **Given** a live-session event, **When** a learner opens it at the scheduled time, **Then** they can join from one clear link, from the web or the app.
2. **Given** a course that has a related live session, **When** a learner did not attend, **Then** they can still complete the course.
3. **Given** a session was recorded or summarised, **When** the host adds the recording link or notes, **Then** learners who missed it can reach them from the event.

---

### Edge Cases

- A learner has no time zone set, or travels: events show in the site default zone until they set one, and the zone in use is always visible beside the time.
- Daylight-saving changes between booking and meeting: the booked time stays correct for both sides.
- A mentor supports learners in several organisations: they see their own mentees' bookings across organisations, but learners never see each other's organisation.
- A recurring event is edited: the manager can change one occurrence or the whole series.
- An organisation manager leaves: events they created stay visible and editable by the organisation's other managers.
- Notification reach: push notifications to the app are limited by the app plan; email remains the dependable route.
- Attendance records, if kept, are learner data and exist only in Moodle.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST offer events at site, organisation, course, cohort or group level, and MUST show each learner only the events at levels they belong to, using core Moodle capability where it exists.
- **FR-002**: Every event time MUST be shown in the viewer's own time zone, with the zone indicated.
- **FR-003**: Upcoming events MUST appear on the learner dashboard and in the Moodle app, and events already synced MUST remain viewable offline.
- **FR-004**: Learners MUST be able to subscribe to or export their own events to an external calendar without administrator help.
- **FR-005**: Organisation and cohort managers MUST be able to create, edit, repeat and cancel events for their own organisation and cohorts only, within the single shared role set.
- **FR-006**: Changes and cancellations MUST reach affected learners through their chosen notification route.
- **FR-007**: Mentors MUST be able to offer bookable office-hour slots; learners they support MUST be able to book and cancel them; bookings MUST appear in both calendars.
- **FR-008**: Who booked a slot MUST be visible to the mentor and to managers entitled to see that learner, and to no other learner.
- **FR-009**: A live-session event MUST carry one clear join link usable from web and app, and MAY carry a follow-up link or notes afterwards.
- **FR-010**: No course's completion, and no published course content, may depend on attending a live session.
- **FR-011**: Event categories, permissions, calendar display and any booking or attendance tool MUST be configured from the repo's Moodle configuration and be rebuildable; individual events are operational records created in Moodle, not configuration.
- **FR-012**: Any attendance or booking records MUST remain in Moodle and be exportable with other learner data.
- **FR-013**: The default live-session route MUST be a link to a meeting tool the host already uses, adding no new service to operate; hosting a conferencing service is out of scope unless a later spec justifies it at flat cost with a named operator.

### Key Entities

- **Event**: a dated item with a level (site, organisation, course, cohort, group), title, time and optional join or follow-up link.
- **Office-hour slot**: a bookable period offered by a mentor, free or taken, linked to at most one learner booking.
- **Booking**: a learner's claim on a slot; learner data, held in Moodle only.
- **Live session**: an event with a join link and optional follow-up; never a prerequisite for course content.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In a test with two organisations, 100% of events reach exactly the intended learners and 0 reach another organisation's learners.
- **SC-002**: 100% of events display at the correct local time for test learners in at least three different time zones, including across a daylight-saving change.
- **SC-003**: At least 2 of 3 test partner learners find their next event and book a mentor slot unaided within 3 minutes.
- **SC-004**: At least 2 of 3 test organisation managers create a cohort event unaided within 5 minutes.
- **SC-005**: A server rebuilt from the repo reproduces the calendar and booking configuration with no manual step.
- **SC-006**: Every course with a related live session can be completed by a learner who attended none.

## Assumptions

- The core Moodle calendar covers site, course, group and user events; organisation- and cohort-level events are delivered through the organisation structure of spec 002 (for example category events). How exactly is a plan decision.
- Office-hour booking may need a maintained free plugin; its Moodle 5.2 compatibility is unverified and is a research task before planning depends on it.
- Attendance tracking is optional and not required for any course; it is included only if a partner asks for it.
- Live sessions use meeting tools hosts already have. No conferencing server is run by us under this spec.
- Email is the dependable notification route; app push reach depends on the app-plan decision in spec 015.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 21 | Events / calendar | Pref | Organisation-scoped calendar, manager-created events, mentor office-hour booking, live-session links with follow-up. Hosted conferencing is excluded. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: Calendar configuration lives in `moodle/`. Events are operational records in Moodle and are never synced to the repo.
- **II. Portability**: Event levels, permissions and any booking plugin (pinned) are applied from `moodle/` and rebuildable (SC-005). Booking and attendance data stays exportable (FR-012).
- **III. Public repo, private people**: Bookings and attendance are learner data and stay in Moodle only. Test accounts are used for verification, and no fixture holds real names.
- **IV. Disclosure**: Not touched. Events carry no course content.
- **V. CBC fidelity**: No event, booking or attendance record awards or implies a CBC level.
- **VI. No LMS orientation**: Learners find events and book slots unaided (SC-003). Managers schedule without an administrator (SC-004).
- **VII. One shape**: The same event levels and roles apply to every organisation. There is no per-partner calendar setup.
- **VIII. Language data**: Not touched.
- **IX. Flat cost, field-ready**: No new paid service. Events show in the app and offline. Live sessions are never the only route to content (FR-010), so low-bandwidth learners lose nothing.
- **X. Traceable and verified**: Row #21 is cited. Organisation-scoped events, app offline display and any booking or attendance plugin are verified on the temporary 5.2.3+ instance before planning depends on them. The "unaided" criteria need 2–3 real partner learners and managers. Keeping a plugin updated is recurring operations. Hosted conferencing would be too, and is excluded until an operator exists (spec 015).
- **Platform & delivery**: Core calendar first, a plugin only for booking, and no bolt-on. One instance and one role set. The server comes from `MOODLE_URL`.

## Dependencies

- **001-site-config-as-code**: the configuration the calendar settings and plugins are applied through.
- **002-org-structure-cohorts**: organisations, cohorts and category-scoped managers that scope events.
- **003-mentor-role**: the mentor–learner relationship that decides who can book whose slots.
- **007-learner-experience**: the dashboard where upcoming events appear.
- **015-production-hosting-ops**: the app-plan and operator decisions that govern push reach and plugin upkeep.
