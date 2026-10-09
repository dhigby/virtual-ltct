# Implementation Plan: Progress tracking and reporting

**Branch**: `004-progress-reporting` | **Date**: 2026-10-02 | **Spec**: [spec.md](spec.md)

## Summary

**Completion** is one rule, stated once in the publish payload:
- a lesson is complete when viewed;
- a quiz is complete when passed, or when submitted if it has no pass mark;
- the course is complete when everything published is complete.

`local_ltuse` applies that rule to every module it creates, and a new `local_ltuse_set_course_completion` function keeps the course's completion criteria in step with the published modules. Moodle's own route for changing criteria wipes every learner's course completion (research R3). Ours adds and removes criteria one at a time, so a republish never erases a recorded completion. Completion is switched on at the site in `settings/completion.yaml`, and on each course by the publisher.

**Learners** see their progress through core: the progress bar on My courses, and "Done" and "To do" against each lesson on the course page. Core has no "go to my next lesson" link, so that part of US2 goes to spec 007 unless SC-004's learners find their way without it (R6).

**Managers** get two scoped views:
- One report builder report per organisation, generated from `organisations.yaml` as 002 generates cohorts. Its conditions are fixed to that organisation's learners on delivery enrolments, and only that organisation's managers cohort can open it. Report builder never filters rows by viewer, which is why the scope is built into each report (R7).
- Core's per-course completion reports, which separate groups already scope (R8). *Amended 2026-10-05: shared courses have no organisation groups (spec 002 open courses, PR #86), and `orgmanager` is enrolled only in organisation-only courses, where every learner is the organisation's (002 R2). So a manager has these reports only there; see "Cross-spec effects".*

Each organisation report is emailed weekly to its managers as Excel, viewed as each recipient (R12).

**The maintainer** gets three reports, for the site team only: a programme report (completions per course), a per-competency table with a row for every framework competency, and a pilot report. Pilot learners are enrolled manually and delivery learners by cohort sync, which keeps pilots out of delivery figures (R10). *Amended 2026-10-05 (spec 002 R10, a0389bc, PR #92): delivery is any enrolment method but `manual`, so a manager's enrolment through the organisation-enrolment instance counts as delivery too.*

A course's competencies and target level reach Moodle in two ways. Two locked course custom fields show them on the course, labelled as what it aims at (R11). A course-to-competency map, owned by the plugin, is what the per-competency table counts. That table is a `local_ltuse` report builder datasource, because core cannot give one row per competency (R15).

Reports, their audiences and their schedules are declared in a new `moodle/site/reports.yaml`, and the course fields in `moodle/site/course-fields.yaml`. Spec 001's applier creates them through core's report builder and custom field APIs (R9). No report output, export or fixture enters the repo (R14).

## Technical Context

**Language/Version**: Python 3.12 (`scripts/`; CI uses 3.12), PHP 8.3 for `local_ltuse` on Moodle 5.2.3+.
**Primary Dependencies**: Moodle core (`core_completion`, `core_reportbuilder`, `core_customfield`, `report_progress`, `report_completion`, `block_myoverview`, `tool_dataprivacy`) and our own `local_ltuse`, which is already installed. No new third-party plugin. PyYAML.
**Storage**: Moodle's database (completions, reports, schedules), plus two `local_ltuse` tables holding no user data: the competency list and the course-to-competency map (R15). The repo holds only declarations.
**Testing**: `pytest` for `site_config.py`, `moodle_payload.py` and `publish_moodle.py`, with synthetic inputs. PHP harnesses run without Moodle, like `tests/report_harness.php`, for the criteria diff. PHPUnit on synthetic data for the per-competency datasource (`moodle/local_ltuse/tests/`). Instance checks are in [quickstart.md](quickstart.md).
**Target Platform**: The self-hosted Moodle 5.2.3+ build host and, later, production (015). The Moodle Android app.
**Project Type**: The training system: the publisher, its Moodle plugin, and declarative site configuration.
**Performance Goals**: Not a driver. Reports cover tens to low thousands of learners. A republish adds one call per course.
**Constraints**:
- Never wipe a completion (FR-011).
- No viewer scoping in report builder (R7).
- No web service creates reports (R9).
- No learner data in git (Principle III).
- Upgrade-safe extension points only (Principle XI).
**Scale/Scope**: Four organisations today, each with one report and one schedule, plus three site-wide reports and about ten published courses. The per-competency table has 42 rows.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. The completion rule lives in the payload, and the reports, audiences, schedules and course fields in `moodle/site/`. Competencies and target level are copied from frontmatter on each publish. The competency list is applied from the repo-root `competencies.yaml`, and each course's competency map is written by the publisher from frontmatter. Both are one-way (R15). Nothing comes back from Moodle. |
| II. Portability | PASS. The payload states completion as `view`, `pass`, `submit` and `all`, with no Moodle field names (R1). Only `local_ltuse` maps the rule to Moodle. Reports are found by `component` and `area`, never by a database id, so a rebuilt server gets them from `apply` (SC-005). The competency list and course map are rebuilt by `apply` and a republish. Rows are found by name, and roles by shortname, never by id. |
| III. Public repo (NON-NEGOTIABLE) | PASS. Declarations only. `apply` and `drift` print report definitions, never rows or counts. Test fixtures are synthetic. `.gitignore` gains spreadsheet patterns as a backstop (R14). The plugin's two new tables hold no user data. The per-competency report is counts only, and `apply` and `drift` never run it (R15). |
| IV. Disclosure boundary (NON-NEGOTIABLE) | PASS. Reports show completion state and the course total grade, never answer keys or responses. A withheld quiz is a page criterion and never blocks completion (R3). The payload's gates are unchanged. |
| V. CBC fidelity | PASS. No report or field records a CBC level reached. The course fields and the per-competency report are labelled as what courses aim at, and the field values are the frontmatter's verbatim CBC vocabulary. The report has no level column and uses no `tool_lp` or `core_competency` data, so nothing can record a level reached (R15). `validate` refuses a report or field label that says "certified" or uses a retired level name. It also refuses "achieved", "attained" and "reached" in the per-competency report, and a test refuses them in the plugin's datasource strings (FR-010). |
| VI. No LMS orientation | PASS with a gate. Learners use core's My courses and course page. The "next lesson" link is not built here, and SC-004's learners decide whether spec 007 must add it (R6). Managers' ease is proven only by SC-003. |
| VII. One shape | PASS. One rule for every course, one report shape for every organisation, generated from one list. No per-course or per-partner override. |
| VIII. Language data | PASS. Not applicable. |
| IX. Flat cost | PASS. Core, plus `local_ltuse`, which is already installed. Offline completion syncs through the app (R5). |
| X. Traceable and verified | PASS with gates. The spec cites rows #7 and #16. Offline sync (R5), report scoping (R7), the criteria diff (R3) and report identity (R9) are verify tasks that block their stories. SC-003 and SC-004 need real partner users. Scheduled email adds mail operations whose production owner is undecided (015), and this plan does not claim them (R12). The per-competency table (R15) is a verify task that blocks US5, checked on synthetic data for zero rows and for pilot and `orgmanager` exclusion. |
| XI. Survives an upgrade | PASS. The plugin uses core APIs throughout: `add_moduleinfo()`/`update_moduleinfo()`; the completion data objects (`completion_criteria_activity`, `completion_aggregation`, `completion_completion`); report builder's `helpers\report`, `audiences\base` and `schedules\base`; `course_handler` for custom fields. Writes go through the data objects course completion itself uses, never raw SQL. The plugin reads `course_completion_criteria` by `course`, and the report and customfield-category lookups are by name or component, all listed in the plugin README (R3, R9, R11). `local_ltuse` keeps `requires` and `supported` at 5.2. The per-competency table is our own report builder datasource, a supported extension point that core finds by namespace. It writes only to its own tables. Its raw reads of `course`, `enrol`, `user_enrolments`, `user`, `role` and `course_completions` are listed in the plugin README. Its column identifiers are frozen once released, and each Moodle branch re-checks the datasource and entity base classes (R15). |
| Platform: core first | PASS. Everything else is core. Our own code is the criteria diff, where core's only route wipes data (R3); the report applier, where core has no web service (R9); and the per-competency datasource, because no core datasource can give one row per competency with enrolment and completion counts (R15). |

Re-checked after Phase 1 design: no change.

## Project Structure

### Documentation (this feature)

```text
specs/004-progress-reporting/
├── plan.md              # This file
├── research.md          # R1–R15
├── data-model.md        # The rule, the report shapes, the course fields, the payload additions
├── quickstart.md        # Instance checks V1–V10
├── contracts/
│   ├── declaration.md   # reports.yaml, course-fields.yaml, settings/completion.yaml, roles.yaml changes
│   └── publish.md       # payload completion fields, create_page/create_quiz changes, set_course_completion
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
moodle/
├── site/
│   ├── reports.yaml                  # new: report templates, audiences, schedules (#7, #16), incl. competency-coverage (R15)
│   ├── site.yaml                     # changed: local_ltuse re-pinned to the version with the two tables and the datasource
│   ├── course-fields.yaml            # new: ltct_competencies, ltct_target_level (#7)
│   ├── settings/completion.yaml      # new: enablecompletion, moodlecourse defaults, myoverview (#7)
│   ├── roles.yaml                    # changed: orgmanager + report/progress:view, report/completion:view;
│   │                                 #          ltcpublisher + moodle/course:changelockedcustomfields
│   └── README.md                     # changed: reports, downloads kept outside the repo folder
├── local_ltuse/
│   ├── classes/util.php              # changed: completion fields from the caller; unlock only from NONE (R2)
│   ├── classes/completion_rule.php   # new: payload rule value -> Moodle fields (pure, tested by harness)
│   ├── classes/criteria_diff.php     # new: pure diff of wanted vs present criteria (R3)
│   ├── classes/external/create_page.php   # changed: takes `completion`
│   ├── classes/external/create_quiz.php   # changed: takes `completion`
│   ├── classes/external/set_course_completion.php  # new (R3)
│   ├── classes/siteconfig/reports.php       # new: check and apply reports, audiences, schedules (R9)
│   ├── classes/siteconfig/coursefields.php  # new: check and apply the course field category and fields (R11)
│   ├── classes/siteconfig/competencies.php  # new: check and apply the competency list; extra -> retired, never deleted (R15)
│   ├── classes/siteconfig/{inspector,applier,drift,report}.php  # changed: hand the new arrays to those classes
│   ├── classes/external/set_course_competencies.php  # new: replace a course's map by name, fail closed, return the read-back (R15)
│   ├── classes/reportbuilder/datasource/competency_coverage.php  # new: main table = competency list, base condition retired = 0
│   ├── classes/reportbuilder/local/entities/competency.php       # new: name, category (sorted on sortorder)
│   ├── classes/reportbuilder/local/entities/coverage.php         # new: five count columns, each one correlated subquery
│   ├── classes/privacy/provider.php  # new: null_provider; the plugin stores no personal data
│   ├── db/install.xml                # new: local_ltuse_competency, local_ltuse_course_comp (no user data)
│   ├── db/upgrade.php                # new: creates the two tables on an existing site
│   ├── lang/en/local_ltuse.php       # changed: datasource, entity and column strings in 'aim at' wording
│   ├── tests/competency_coverage_test.php  # new: PHPUnit, synthetic data: zero row, pilot and orgmanager excluded, two-cohort learner, retired hidden
│   ├── db/services.php               # changed: + local_ltuse_set_course_completion, local_ltuse_set_course_competencies
│   ├── version.php                   # bumped
│   └── README.md                     # changed: the criteria writes and lookups, the datasource's raw reads, and why (Principle XI)
└── REQUIREMENTS.md                   # rows 7 and 16 updated on delivery
scripts/
├── moodle_payload.py                 # changed: completion rule on modules and course; competencies, level
├── publish_moodle.py                 # changed: enablecompletion, customfields, set_course_completion and set_course_competencies calls, Meta skip, read-back exit 1
├── check_moodle_payload.py           # changed: every module has a completion value; every competency is in competencies.yaml
└── site_config.py                    # changed: validate, expand and render reports.yaml and course-fields.yaml; render competencies from competencies.yaml (Meta excluded); validate competency-coverage
tests/
├── test_payload_completion.py        # new: the rule on every module kind, withheld quiz included
├── test_publish_moodle.py            # changed: calls and parameters, completion-differs exit, competency call, dry run, read-back mismatch exit
├── test_site_config.py               # changed: reports and course fields validation, per-org expansion, competencies rendering (42), competency-coverage rules
└── criteria_harness.php              # new: criteria_diff and completion_rule without Moodle
process/stages/07-pilot.md            # changed: enrol the pilot learner manually (R10)
.gitignore                            # changed: *.xlsx, *.xls, *.ods backstop (R14)
.github/workflows/site-config.yml     # changed: runs criteria_harness.php too
```

**Structure Decision**: The structure follows spec 002's. Each new item type gets its own class beside `inspector.php`, which only hands it its payload array. Reports get their own top-level file, because `validate` expands one template per organisation from `organisations.yaml`, as cohorts are. The completion rule's two pure pieces, the value mapping and the criteria diff, are classes with no Moodle calls, so a harness can test them in CI. The per-competency datasource reads Moodle tables in SQL, so it is tested with PHPUnit on a test site, not by a harness. With it, `local_ltuse` gains its first `install.xml` and privacy provider.

## Decisions on the plan's limits

These were reviewed on 2026-10-02, after the first draft of this plan.

| # | Limit | Status |
|---|---|---|
| 1 | No "go to my next lesson" link. Core gives the My courses progress bar and Done / To do on the course page. The link goes to spec 007 if SC-004's learners need it (R6). | Handed to spec 007, which builds the link now in its learner home block ([007 plan decision 1](../007-learner-experience/plan.md#decisions-to-confirm-with-the-maintainer), [research R3](../007-learner-experience/research.md#r3-continue-where-you-left-off-has-no-core-implementation)) and owns the My courses groupings ([007 R2](../007-learner-experience/research.md#r2-which-dashboard-blocks-stay)). **Pending the maintainer (Doug)**: confirming 007's decision 1. |
| 2 | Managers cannot subscribe themselves to the weekly email. Every manager of an organisation receives it, and per-person opt-in would need a `:subscribers` cohort per organisation (R12). | **Pending the maintainer (Doug).** |
| 3 | The organisation report shows progress as a percentage, not "4 of 6 lessons". The per-lesson detail is in core's in-course reports (R8). | Accepted. |
| 4 | Per-competency counts one competency at a time. | **Rejected and redesigned.** R15 now gives one table with a row for each of the 42 framework competencies, built on a `local_ltuse` datasource. The design was chosen from three candidates: core only, our own datasource, and core competencies. |
| 6 | A pilot learner who later gets a delivery enrolment in the same course has their pilot-era completion counted as delivery. Moodle keeps one completion per learner per course (R10, R15). | **Pending the maintainer (Doug).** |
| 5 | Offline completions are dated at sync, not when they happened (R5). | Accepted. |

`/speckit-tasks` may generate tasks for 1 and 2 as drafted. The tasks that close US2 and US4 are not closed until the maintainer confirms.

## Cross-spec effects

- **Spec 001**: its declaration contract gains two files and four item types: reports, the course field category, course fields, and the competency list read from the repo-root `competencies.yaml`. This plan's [contracts/declaration.md](contracts/declaration.md) is the addition, and 001's contract links to it.
- **Spec 002**: `orgmanager` gains two report capabilities. The organisation list now also generates one report and one schedule per organisation. 002's moved-learner limit now also shows on the two in-course reports (R8).
  - *Amended 2026-10-05:* spec 002's open-courses change (PRs #86 and #92) changed this spec's delivery condition from `enrol:plugin = cohort` to `enrol:plugin` not equal to `manual`, so a manager's enrolment through the organisation-enrolment instance counts as delivery (002 R10, a0389bc); `coverage.php` counts that instance too; `group:name` left the reports, because shared courses have no organisation groups (fe7e77c); and `orgmanager`, with its two report capabilities, is enrolled only in organisation-only courses (002 R2), so R8 and quickstart V6 apply only there.
- **Spec 016**: decision 2, option (a) (Doug, 2026-10-05 (scope review), 016 research R11, tasks T034–T035, d395494, PR #84) moved the organisation report's scope from the `user:profilefield_ltct_org` condition to `cohort:idnumber = ltct:org:{org}`, dropped the Organisation column, and made `validate` refuse an `ltct_org` condition on any report. FR-006, R7, data-model "Report" and contracts/declaration.md carry amendment notes; the original text stays for the record.
- **Spec 003**: mentors read the same completion data. A mentor view of assigned learners belongs to 003.
- **Spec 006 and 013**: both read course completion, which is now on in every published course and never wiped by a republish. This spec does not commit spec 006 to core competencies. R15 deliberately uses none, and its reasons for rejecting `tool_lp` (Principle V) are input for 006.
- **Spec 007**: receives the "next incomplete lesson" link if SC-004 shows it is needed (R6), and may group My courses by `ltct_competencies`.
- **Spec 009**: R5's offline check runs with 009's V7, on the same device.
- **Spec 015**: owns production mail and the operator for scheduled reports (R12).
- **Stage 7 how-to**: the pilot learner is enrolled manually (R10).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Own report builder datasource and two plugin tables (R15) | One row per competency, with enrolment and completion counts (FR-013, decision #4) | Core report builder cannot split a multi-valued field into rows, so core alone gives only single-row, 42-column reports. Core competencies record ratings (Principle V). |
