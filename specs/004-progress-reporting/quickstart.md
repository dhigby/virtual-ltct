# Quickstart: validating progress tracking and reporting

Run on the temporary 5.2.3+ build host, with **test accounts only** (`ltct-test-*`). Record each result for the delivering PR **outside the repository folder**. A screenshot or a downloaded report holds learner rows, even test ones, and GitDoc pushes what is left in the tree.

## Prerequisites

- `MOODLE_URL`, `MOODLE_TOKEN`, `MOODLE_DIR` and `MOODLE_SSH` set, as for `site_config.py` and the publisher.
- Spec 002's test set-up: two test organisations, A and B, each with two learners and one manager. Each organisation is enrolled by cohort sync into two shared test courses, with its own group. One extra test learner is enrolled **manually** as a pilot.
- An Android device with the Moodle app (V7), shared with spec 009's V7.

## Offline (no server)

```powershell
python scripts/site_config.py validate          # reports.yaml, course-fields.yaml and completion.yaml are valid
python -m pytest -q tests/                      # payload rule, publisher calls, validation cases
php tests/criteria_harness.php                  # criteria diff and rule mapping; no clear_criteria in the class
python scripts/publish_moodle.py --slug <slug> --dry-run   # every module shows a completion value
```

Each must pass, or exit 0.

## V1. Apply is complete and idempotent (FR-008, SC-005)

`site_config.py apply`, then `drift`. Drift says `No differences.`. A second `apply` changes nothing. Rename one report column by hand: `drift` reports that report `changed`, and `apply` sets it back. Delete one organisation's report by hand: `apply` creates it again with the same area, columns, conditions and audience, and `drift` says `No differences.` (SC-005). See [contracts/declaration.md](contracts/declaration.md).

## V2. Publish switches completion on, with no hand steps (US1-4, FR-001)

Publish two courses. In each, course settings show completion on, every lesson shows "Done: View", and the quiz shows "Receive a pass grade" (or "Receive a grade" if it has no pass mark). Course completion lists every lesson and the quiz, with ALL. Nothing was set in the admin UI.

## V3. Learners complete (US1-1, US1-2)

Test learner A1 opens every lesson and passes the quiz in course 1, and opens one lesson in course 2. Course 1 shows complete after `completion_regular_task` runs. Course 2 shows one lesson done.

## V4. A republish never erases (FR-011, R2, R3)

Use three learners: X complete, Y part-way, Z not started.
- Republish unchanged: `completion 0 added, 0 removed`, and nothing moves.
- Add a lesson and republish: X is still complete, on the same date, with the same `course_completions` id. Y and Z have one more lesson outstanding.
- Remove a lesson Y had not done and republish: after `completion_regular_task`, Y is complete.
- Set one page's completion to manual by hand, then republish: the publisher exits 1 and names that module as `differs`. Nobody's ticks change.

## V5. Organisation reports are scoped (US3, FR-005, SC-002, R7)

First confirm every `entity:name` in `reports.yaml` against the datasource. As manager A:
- the A report lists A's learners only, with every FR-006 column;
- the B report, opened by its id, is refused;
- no filter, and no filter cleared, shows a B learner, the pilot learner or a manager;
- the CSV and Excel downloads hold the same rows;
- `core_reportbuilder_list_reports` and `core_reportbuilder_retrieve_report` with A's own token return only the A report and its rows;
- an A learner enrolled in the same course through a second A cohort appears once for that course. If they appear twice, set `enrolment:timecreated` to `aggregation: min` and `group:name` to `aggregation: groupconcatdistinct` in `reports.yaml`, re-apply and re-check.

Move one A learner to B: they leave A's report and appear in B's. Repeat as manager B. A test organisation with no learners shows an empty report, not an error.

## V6. In-course reports are group-scoped (R8)

As manager A, the activity completion report and the course completion report in a shared course list A's group only. Group 0, or B's group id in the URL, is refused. The downloads match.

## V7. Offline completion syncs (US1-3, FR-003, R5)

On the device, as a test learner: download a course, go offline, open two lessons and submit the quiz, then reconnect. All three appear in V6's reports, and the My courses bar moves. Record the device, the app version and the dates shown, which are expected to be sync time.

## V8. The learner's landing (US2, R6)

A learner in three courses (one complete, two part-way) logs in. My courses shows a progress bar per course, the complete course under Past, and only the other two under In progress. Record which grouping is shown first. If hiding "All" changes it, declare the setting (R6). On a part-way course page, each lesson shows Done or To do.

## V9. Schedules, the programme and pilot reports, and export (US4, US5, FR-007, FR-013, FR-014)

- Run `\core_reportbuilder\task\send_schedules`. Managers A and B each receive one Excel file with only their own rows, and the empty test organisation sends nothing.
- The programme report's per-course counts equal the sum of the A and B reports' completions, with the pilot learner excluded. The pilots report lists only the pilot.
- An approved data export for test learner A1 includes completion for both courses and each lesson's state.

## V10. The per-competency table (US5, FR-013, R15)

- **Per-competency table (R11)**. Run `site_config.py apply` twice. The second run reports nothing changed. As a test system manager, open "Competencies published courses aim at". There are 42 rows in `competencies.yaml` order and no Uncategorized row. Every competency that no published test course declares shows 0 in every count column, not a blank.
- **Courses columns**. Test courses A and B share Translation Tools, and only A declares Paratext. Translation Tools shows 2 courses and Paratext shows 1. Republish A without Paratext: Paratext shows 0. Both courses have only a manual enrolment method: "Of which in delivery" is 0. Add A's cohort-sync instances: it is 1.
- **Delivery columns**. In A:
  - pilot learner P is enrolled manually;
  - learners D1 and D2 are enrolled by organisation cohort sync;
  - organisation manager M is enrolled by the managers cohort as `orgmanager`.

  P and D1 complete. For Translation Tools, enrolments show 2, learners 2 and completions 1. P and M are not counted anywhere.
- **Two cohorts**. Enrol D1 through a second cohort in A: enrolments show 3, learners 2 and completions 1. Suspend D2's enrolment: enrolments drop by one.
- **Agreement**. "Courses that aim at it" agrees with COVERAGE.md for the published test courses. Delivery completions agree with the sum of the A and B organisation reports' completions on the same data.
- **Fail closed**. Publish a course whose frontmatter names a competency missing from the site, with a scratch competencies.yaml and no apply. The publish fails, and the map is unchanged. Remove a competency from the scratch file and apply. Drift shows `extra`, the row leaves the report, and its map rows remain.
- **Access**. A test orgmanager and a plain test user are refused at `reportbuilder/view.php?id=…`. The report is absent from `core_reportbuilder_list_reports`, and `core_reportbuilder_retrieve_report` with their own token is refused. As manager, the CSV and Excel downloads hold the same 42 rows of counts. A new custom report built from "Competency coverage" offers no column, condition or filter that names a person or a course.
- **Hand edits**. Add a condition to the report by hand: drift reports `changed`, and `apply` removes it.
- Record the results outside the repo tree.

## Real users (SC-003, SC-004)

These are not instance checks. They are done when 2–3 partner managers have found, read and downloaded their report without help, and 2–3 partner learners have said what they finished and what is next within a minute of logging in. Record their findings on the tracker. US2's "next lesson" question goes to spec 007 if the learners needed help (R6).
