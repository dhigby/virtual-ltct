# Quickstart: verifying learning pathways on the build host

Run these on the temporary 5.2.3+ instance with **test accounts only** (constitution X).
Evidence (screenshots, notes) stays outside the repo; record only pass or fail, and the date, in
`moodle/REQUIREMENTS.md` row 12.

## Setup

1. Upgrade `local_ltuse` to `2026100600` and run `python scripts/site_config.py apply`. Then run
   `drift`: no `pathway` or `competency` item may show other than `ok`.
2. Publish two delivered test courses, A and B, that both declare `Keyboards`, with A at
   `1 - Has Knowledge` and B at `3 - Independent`. Publish a third course, P, declaring
   `Keyboards` at stage 7 (pilot).
3. Make test users: learner L1 in organisation `sil`, learner L2 in another organisation,
   mentor M assigned to L1 only (003), manager G in `sil`'s managers cohort.

## Checks

| # | Story | Steps | Expected |
|---|---|---|---|
| V1 | US1 | L1 opens Pathways > Keyboards. | A under `1 - Has Knowledge`, B under `3 - Independent`. Levels 2 and 4 say "No course yet" and link to the Keyboards page on the competency site. P is absent. |
| V2 | US1 | L1 completes A, then reloads. | A shows completed. B is marked next. |
| V3 | US1 | L1 completes B. | The pathway says the training on it is completed, and names no level. |
| V4 | US2 | Change A's frontmatter to drop `Keyboards` and add `Fonts & Encoding`; republish A. | A leaves Keyboards and appears under Fonts & Encoding. No admin step was taken. |
| V5 | US2 | Publish P at stage 8. | P joins Keyboards with no other step. |
| V6 | US2 | Hide B in the course settings. | B leaves the pathway. L1's completion of B is still in the course completion report. |
| V7 | US4 | M opens Mentoring and follows L1's Pathways link. M then edits the URL to L2's user id. | M sees L1's pathways and progress. For L2, access is refused. |
| V8 | US5 | G assigns Keyboards to `sil`'s cohort. G tries to assign to another organisation's cohort by URL. | Every `sil` learner sees Keyboards under "Assigned to you". A new `sil` learner sees it on joining. The other cohort is refused. G's progress view lists only `sil` learners. No one was enrolled. |
| V9 | SC-003 | 2–3 real partner learners are asked, without help, what they have finished and what to take next. | Findings recorded. Row 12 is not marked verified until this is done (constitution X). |
| V10 | FR-015 | L1 opens the Moodle app > More > Pathways. | The same pathway as in the browser, with completed and next marked. |
| V11 | FR-011 | Search every pathway page, in the browser and the app, for a level shown as L1's. | None. Levels appear only as what a course aims at. |
| V12 | US3 | Add a test role to `pathways.yaml` with three competencies (on a scratch branch, never merged), apply, and assign it to a test cohort. | Its three competency pathways show beneath it, with one combined "N of M courses completed". |
