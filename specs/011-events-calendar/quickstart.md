# Quickstart: verifying events, office hours and live sessions

Run these checks on the temporary 5.2.3+ instance, with **test accounts only** (constitution X). Keep every screenshot, exported `.ics` file and email **outside this repository folder**, because they show test users' names. Record each result in the PR that closes the task, never as a committed file, with:
- the date;
- the device and app version, for checks in the app;
- the time zones used.

## Prerequisites

- `MOODLE_URL`, `MOODLE_TOKEN`, `MOODLE_DIR` and `MOODLE_SSH` are set (see the [site README](../../moodle/site/README.md)).
- The plugins are in place: `mod_scheduler` is installed at its pin, `local_ltuse` is upgraded, and cron runs every minute.
- **The open-courses change (002 amendment) has landed:**
  - test organisations A and B share course **S**;
  - A has an organisation-only course **OA**;
  - manager **MA** of A is enrolled only in OA, through A's managers cohort.
- **Test accounts:**
  - learners A1 and A2 (A) and B1 (B);
  - mentor **M**, who is a Course mentor (`teacher`) in S.
  - Spec 003 relationships: M mentors A1 and B1. A second mentor, **N**, mentors A2.
- **Time zones**: A1 on `Africa/Nairobi`, B1 on `America/Bogota`, and A2 never set (so UTC).
- **The app**: spec 009's V7 device, with the Moodle app installed.

## Checks

| # | Check | How | Expected |
|---|---|---|---|
| V1 | Config applies and is idempotent (FR-011, SC-005) | `site_config.py validate`, then `apply`, then `drift` | `apply` creates the category, `ltct:officehours`, its scheduler, its manual instance and the dashboard block, and sets the calendar and scheduler settings. `drift` says `No differences.` A second `apply` changes nothing. Output holds counts and idnumbers only. |
| V2 | Reach and the dashboard (US1-1 to -3, FR-001, FR-003, SC-001) | As the site team: a site event. As M: a course event in S and a group event in S, for a test group holding A1 only. As MA: a course event in OA. Then view the dashboard and calendar as A1, A2 and B1. | A1 sees all four. A2 sees the site, S and OA events, not the group event. B1 sees the site and S events only. Each dashboard shows the Upcoming events block. |
| V3 | Time zone at first login (FR-002, D5, R14) | Log in as a fresh test learner, first on the web, then a second one only in the app. Then log in as the site team, as `ltcpublisher` by token, and by "log in as" | On the web, the zone page appears once; saving returns to where they were going, and the profile shows the zone. In the app, the profile form opens in the in-app browser; saving returns to the app, and About shows the zone. The site team, the publisher and log-in-as are never asked. Re-run on every core or app upgrade. |
| V4 | Local times and daylight saving (US1-1, SC-002) | M creates a weekly 10:00 series in S spanning a DST change in M's zone. A1, B1 and A2 view it. | Each sees the time in their own zone (A2 in UTC). Across M's DST change, the viewers' times shift by an hour and M's stay at 10:00 (R5). The explanation goes in the README. |
| V5 | Offline in the app (US1-4, FR-003, R8) | On the 009 V7 device, A1 opens the app calendar online, then goes offline | The upcoming events already seen still show. |
| V6 | Managers post in their own course only (US2, FR-005, SC-004, R17) | As MA: create a one-off and a weekly repeating event in OA; try a site event; try an event in S; try one in a course of B's. As a second manager of A: edit MA's event. | OA events reach only OA's learners. The site type is not offered. S and B's course are not offered, and are refused by URL or web service. The second manager can edit. |
| V7 | Export and subscribe (US1-5, FR-004, R7) | As A1: Calendar, Export, "All"; copy the subscribe URL into an external calendar | The `.ics` holds A1's events, in UTC. The subscription refreshes without a login. No OA event reaches B1's export. |
| V8 | One message per change (US2-3, FR-006, R15) | As M in S: edit a whole weekly series; edit one occurrence; drag one; create an event with an image in its description; hide an event; delete the series' first occurrence alone; delete the whole series | Edits and drags give one "changed" message each for A1 and B1. The create with an image gives none, and the hide gives none. Deleting the first occurrence alone gives a "cancelled" message for that occurrence and no "changed". Deleting the series gives one "cancelled". M gets nothing. |
| V9 | Who is told, and how (FR-006, R15) | Repeat one change for a group event, an OA event and a site event. Turn off A1's email for "Event changed" and keep push. | Only the group's, OA's or the site's active users are told. A1 receives push only. A suspended learner is not told. The message names no person and its link opens the event. |
| V10 | A learner sees only their mentor's slots (US3-1, US3-4, FR-007, R16) | M adds three slots and N adds two, in the office-hours course. As A1, B1 and A2, open the course | A1 and B1 see M's three slots, at their own local time. A2 sees N's two. Nobody sees a slot of a mentor who does not mentor them. Re-run on every scheduler re-pin. |
| V11 | Bookings stay private (US3-2, FR-008, D4) | A1 books one of M's slots, then B1 views the slots and the participants page | B1 sees the slot as taken, with no name. B1 does not see A1 on the participants page or in the group. M sees A1's booking. |
| V12 | The sync and reconcile (D6, R16) | End M's relationship with B1 (003's page). As the site team, remove A1 from M's group by hand, then run the reconcile task. | B1 leaves M's group and is suspended in the course, and B1's past appointment is still in their privacy export. The hand removal is refused, and if forced through the database for the test, the next reconcile puts A1 back. The task output holds counts only. |
| V13 | Booking end to end (US3-3, FR-007, R19) | A1 books in the browser; then opens the office-hours course in the app; then cancels outside the window; then tries to cancel inside it | Both calendars, on the web and in the app, show the booking. The app offers "Open in browser" and keeps A1 logged in. M is told of the booking and the cancellation. Inside the window, cancelling is refused and the page says to message the mentor. The reminder task runs. |
| V14 | Organisation-wide message (D2) | As the site team: Bulk user actions, filter cohort `ltct:org:a`, "Send a message" | A1 and A2 receive it; B1 does not. |
| V15 | A live session (US4, FR-009, FR-010, SC-006) | M creates an S course event with a meeting link in its description and nothing in Location. After it, M adds a recording link. A2 never attends and completes S. | The link opens the meeting from the web and from the app (not a maps search). The follow-up shows on the same event. A2's course completion is unaffected. |
| V16 | Rebuild (SC-005) | `apply` to an empty test instance, then re-create the 003 test relationships | The course, activity, groups and memberships come back the same (compare `drift --json` and the reconcile counts). No booking exists, because bookings are Moodle data. |
| V17 | Export of learner data (FR-012) | A1 requests a privacy export | It contains A1's appointment, the time zone confirmation and their own events. |
| V18 | With spec 016, once both have landed | Protect M at pseudonym level, then run 016's V17 | Group names never carried M's name. The booking events and A1's feed show only M's protected display. |

## The criteria for real users

SC-003 (learners find their next event and book a mentor slot within 3 minutes) and SC-004 (managers create an event within 5 minutes) are "simple" criteria. US1, US2 and US3 are not marked done until 2–3 real partner learners, and 2–3 real organisation managers, have done this unaided and their findings are recorded (constitution X).
