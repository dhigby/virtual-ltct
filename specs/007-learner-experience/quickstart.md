# Quickstart: verifying the simple learner experience

**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md)

These are the live checks. V1–V8 use **test accounts only** on the temporary instance
(constitution X), and none of their output is committed beyond pass/fail, the device and the
app version. V9 is the real-learner pilot that makes row #13 done.

## Prerequisites

- `main` is fast-forwarded to `origin/main` before any deploy or apply, so apply never
  enforces a stale declaration: `git log main..origin/main` is empty.
- `local_ltuse` and `block_ltuse` are deployed with LF line endings
  (`git -c core.autocrlf=false archive`).
- `MOODLE_URL`, `MOODLE_TOKEN` and `MOODLE_SUPPORT_EMAIL` are in the environment, never in a file.
- One published pipeline course with at least three lessons and a quiz, for example
  `paratext-quotation-rules`.
- Test accounts, made with `/manage-learners` and named `test-007-*`:
  - **A**: enrolled in that course;
  - **B**: enrolled in nothing;
  - **C**: holds a pathway (spec 006), has a mentor (spec 003) and has completed one course;
  - **D**: an organisation manager who is also enrolled.
- An Android device with the Moodle app 5.2.x.

## Apply and check the declaration

```powershell
python scripts/site_config.py validate
python scripts/site_config.py drift      # expect: the dashboard, nav, myoverview, brand and support lines
python scripts/site_config.py apply
python scripts/site_config.py drift      # expect: nothing
```

**Pass**: The second drift is empty, and it reports `personal dashboards: 0`.

## V1. First login, enrolled and not (US1-1, US1-3, FR-001, FR-002)

1. Log in as **A** on the web for the first time.
   **Expect**: The Dashboard opens. The first thing in the content area is "Start: <lesson 1>"
   with the course name. Beneath it is the course list. No Timeline, no month calendar, no
   Recently accessed items.
2. Log in as **B**.
   **Expect**: "No course has been assigned to you yet." plus who to ask, and "Contact the
   site team" opens core's support form, addressed to `MOODLE_SUPPORT_EMAIL`.
3. Repeat 1 at the narrowest phone width in the browser's device view.
   **Expect**: The course name and the Start button are visible without scrolling sideways
   or opening a menu.

## V2. Nothing to customise (R2, R4, R11)

As **A**:
**Expect**: There is no "Customise this page" button. The course list offers All, In progress
and Past, and no Starred, Future or Removed from view. Buttons and links are SIL Blue
(`#005CB9`). The block's panel is a pale blue tint with a blue left rule.

## V3. Continue, in one tap (US1-2, SC-002, R3)

As **A**, open lessons 1 and 2, then return to the Dashboard.
**Expect**: "Continue: <lesson 3>". One click opens lesson 3.

## V4. A course is its lessons (US2, FR-003, FR-004, SC-003, R6, R10)

As **A**, open the course page.
**Expect**:
- Each lesson is a section named for the lesson.
- Each section shows its "Estimated time: N minutes" line.
- Each lesson shows Done or To do.
- The quiz sits in its lesson.

Then open lesson 1. **Expect**: "Next: <lesson 2>" beneath the content. Following "Next"
reaches lesson 3 with no use of the course index or any menu. On the last module the
button reads "Back to the course".

Also check the course page itself: the Retired section is invisible.

## V5. Next in the app (FR-004, FR-006)

As **A** in the app, open lesson 1.
**Expect**: The app's own next/previous arrows at the foot of the page reach lesson 2.
**Record**: the app version.

## V6. The block in the app, and offline (US3, FR-006, FR-007, R7, R8)

As **A** in the app:
1. Open the Home tab.
   **Expect**: The same Start/Continue button as on the web, the same course list in the
   same order, and the two offline hints.
2. Tap Continue.
   **Expect**: The lesson opens inside the app, not the browser.
3. Open the course, then follow the hint: tap ⋮, then Download course.
   **Expect**: The menu wording matches the hint. If not, fix the string and record the app
   version.

**Record**: whether the Home tab rendered the block, on which app version, and on which app
plan. If it did not render, switch to the `CoreMainMenuDelegate` fallback (R7) and re-run.

## V7. Offline lessons and quiz (SC-004)

Continuing from V6:
1. Open the quiz once while online.
2. Switch the device to aeroplane mode.
3. Open two lessons, then finish the quiz.

**Expect**: Text, screenshots and callouts all render. The quiz shows "offline data to be
synchronized". After reconnecting, the Dashboard's Continue moves on, on both the web and
the app.

## V8. Onward routes and other roles (US4, FR-008, R12)

1. As **C**.
   **Expect**: "Where next" shows the next course of each pathway and "Message <mentor>".
   Each link works. No community line appears while spec 005 has declared none.
2. As **D**.
   **Expect**: Their own Continue button, plus "Mentoring", "Pathways" and "My organisation"
   still where they were.
3. As a site administrator.
   **Expect**: No `block_ltuse` content.

## V9. Real partner learners (FR-014, SC-001–SC-006)

This runs at a stage-7 pilot. Choose two or three real partner learners who have never been
shown Moodle.

1. Give each learner only their login details. Observe without helping.
2. Record each finding in `pilot-findings.md` in the shape of [data-model.md](data-model.md) §5,
   de-identified, in the session or straight after.

**Measure**:
- time from first login to opening lesson 1 (SC-001: within 5 minutes, for at least 2 of 3);
- taps from the Dashboard to the lesson they left off (SC-002: 2 or fewer);
- uses of Moodle's own menus across two lessons (SC-003: zero);
- one offline session in the app (SC-004).

**Done when**: At least two learners are recorded, every finding is `resolved` or `accepted`,
and `moodle/REQUIREMENTS.md` row #13 is updated in the same PR (constitution X).
