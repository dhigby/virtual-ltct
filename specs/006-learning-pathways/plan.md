# Implementation Plan: Learning pathways mapped to CBC

**Branch**: `006-learning-pathways` | **Date**: 2026-10-04 | **Spec**: [spec.md](spec.md)

## Summary

A learner opens a competency's pathway and sees every delivered course that trains it, grouped
under the CBC level each course aims at, with what they have completed and which course is
next. Role pathways group several competency pathways under a role the repo declares. Mentors
see their mentees' pathways. Organisation managers assign a pathway to their own cohorts and
follow their own learners' progress.

Nothing about a pathway is stored as a Moodle object that someone could hand-edit. `local_ltuse`
builds each pathway at view time from tables the publisher already writes on every publish: 004's
competency list and course-to-competency map, plus one new table holding each course's delivery
state and target level (R1, R2). The publisher writes that row through a new web service,
`local_ltuse_set_course_pathway`, so a republish changes the pathways with no other step
(SC-005). Role pathways are declared in `moodle/site/pathways.yaml` and applied by
`site_config.py` (R5). The plugin adds pages for learners and managers, a Pathways handler in the
Moodle app, and a link from 003's Mentoring page. It neither awards nor shows a CBC level for
anyone (R13), and it enrols no one: spec 008 owns enrolment and builds on this spec's assignment
table and events (R9, [contracts/pathway-api.md](contracts/pathway-api.md)).

Core learning plans and the Programs plugin were both rejected (R1). Learning plans show a
rating and a proficient flag per competency, which would put a CBC level in front of every
learner. Programs adds four plugins and a second enrolment mechanism, and it has no documented
API for building a pathway from the repo.

## Technical Context

**Language/Version**: Python 3.12 (`scripts/`), PHP 8.3 for `local_ltuse` on Moodle 5.2.3+.

**Primary Dependencies**: Moodle core (`core_completion`, `core_cohort`, events, the
`primary_extend` hook, the app's `CoreMainMenuDelegate`), our own `local_ltuse`, PyYAML. No new
third-party plugin.

**Storage**: Moodle's database. Three new `local_ltuse` tables hold no user data:
`local_ltuse_course_pathway`, `local_ltuse_role_pathway` and `local_ltuse_role_pathway_comp`.
A fourth, `local_ltuse_pathway_cohort`, links a pathway to a cohort and records who made the
link (`usermodified`), which the privacy provider declares. `local_ltuse_competency` gains `slug`
and `url`. Level labels are stored in plugin config. The repo holds only declarations.

**Testing**: `pytest` for `site_config.py` (pathways.yaml validation, competency slug and url,
levels), `publish_moodle.py` (the new call and its read-back) and the lang-string wording check.
PHP harnesses with no Moodle for the pure pieces: `pathway\builder` (layout, next, done, role
totals) and `pathway\viewer` (who may see whom). Instance checks are in
[quickstart.md](quickstart.md).

**Target Platform**: The self-hosted Moodle 5.2.3+ build host, later production (015), and the
Moodle Android app.

**Constraints**: no CBC level shown as anyone's (FR-011); no learner data in git; only
upgrade-safe extension points (XI); host from `MOODLE_URL` and `mkdocs.yml`'s `site_url`, never
hard-coded.

**Scale/Scope**: 42 competencies, about ten delivered courses, roles as supplied (none yet).

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. A pathway is derived from frontmatter via the publisher (competencies, target level, delivery from `course_stage.py`) and from `competencies.yaml`, `outcome-levels.yaml`, the descriptors' slugs and `pathways.yaml` via `apply`. Nothing is read back into the repo. There is no pathway object in Moodle to hand-edit (FR-006). FR-014 holds by construction: pathways and `COVERAGE.md` read the same frontmatter, and the publisher fails on a map read-back mismatch. |
| II. Portability | PASS. The payload says `delivery` and `target_outcome_level` only; the plugin owns the Moodle mapping. Keys are `competency:<slug>` and `role:<key>`, never database ids (R3). A rebuilt server regains every pathway from `apply` and a republish. Cohort assignments are Moodle data and come back with the data restore. |
| III. Public repo (NON-NEGOTIABLE) | PASS. Declarations only. `apply` and `drift` report roles and competencies, never assignments or progress. Harness fixtures are synthetic. |
| IV. Disclosure | PASS. Pathways link to course pages and the public competency site only. No page HTML from the repo. |
| V. CBC fidelity | PASS. Competency names are verbatim and checked at `validate` and in the web service. Level labels come from `outcome-levels.yaml`. A level is only ever what a course *aims at*. Finishing a pathway says training is completed and names no level (FR-011). A pytest holds every 006 string to `cbc_wording`'s strict rule (R13). |
| VI. No LMS orientation | PASS with a gate. One "Pathways" item in the primary navigation and the app menu, with no locks to explain (FR-008, FR-009). SC-003 needs 2–3 real partner learners (quickstart V9). |
| VII. One shape | PASS. Every competency pathway is built by the same code from the same data. Role pathways follow one declared shape. No per-course or per-partner pathway. |
| VIII. Language data | PASS. Not applicable. |
| IX. Flat cost, field-ready | PASS. Core and our own plugin. App handler (FR-015). Pages carry no images. |
| X. Traceable and verified | PASS with gates. Cites row #12 and updates it in the same PR. Each core API the plan uses is confirmed in `MOODLE_502_STABLE` source before its task closes (R11). The instance checks in quickstart block marking row #12 verified. No new recurring operations. |
| XI. Survives an upgrade | PASS. Our own plugin on supported extension points: web services, events, a hook callback, a mobile handler, our own tables. It reads core's `course`, `course_completions`, `cohort` and `cohort_members` by indexed columns, read only. It writes only its own tables. The README lists the reads. `requires` and `supported` stay at 5.2. |
| Platform: core first | PASS, with the exception justified below. |

Re-checked after Phase 1 design: no change.

### Complexity Tracking

| Violation | Why needed | Simpler alternative rejected |
|---|---|---|
| Our own pathway pages instead of core learning plans | Core plans show a rating and proficient flag per competency in the browser and the app (FR-011), cannot order courses by target level or say "no course yet" (FR-003, FR-004), and would need a scale with a proficient value (constitution V). | `tool_lp` plan templates synced to cohorts (R1). `tool_muprog`: four plugins, a second enrolment mechanism, no API for building pathways from the repo (R1). |

## Project Structure

### Documentation (this feature)

```text
specs/006-learning-pathways/
├── plan.md              # this file
├── research.md          # R1–R13
├── data-model.md        # tables, payload additions, the pathway view model
├── quickstart.md        # instance checks V1–V9
├── contracts/
│   ├── declaration.md   # pathways.yaml, competency slug/url, levels: validate and apply
│   ├── publish.md       # local_ltuse_set_course_pathway and the publisher's call
│   └── pathway-api.md   # PHP API and events for spec 008 (agreed 2026-10-04)
└── tasks.md             # /speckit-tasks
```

### Source Code (repository root)

```text
moodle/
├── site/
│   ├── pathways.yaml                    # new: role pathways (roles: [] until a human supplies them)
│   ├── site.yaml                        # changed: local_ltuse pinned at 2026100600
│   └── README.md                        # changed: pathways, adding a role, retiring a course
├── local_ltuse/
│   ├── pathways.php                     # new: a learner's pathways, one pathway, browse all
│   ├── pathways_manage.php              # new: assign to cohorts; a cohort's progress
│   ├── classes/pathway/catalogue.php    # new: which courses are in which pathway (SQL)
│   ├── classes/pathway/builder.php      # new: pure: layout, status, next, done, role totals
│   ├── classes/pathway/viewer.php       # new: pure: may V see L's pathways
│   ├── classes/pathway/assignments.php  # new: pathway <-> cohort (the 008 seam)
│   ├── classes/pathway/progress.php     # new: one learner's completion over a pathway
│   ├── classes/external/set_course_pathway.php  # new: delivery + target level; fires the event
│   ├── classes/event/pathway_courses_changed.php, pathway_assigned.php, pathway_unassigned.php  # new
│   ├── classes/siteconfig/pathways.php  # new: role pathways and level labels
│   ├── classes/siteconfig/competencies.php  # changed: slug and url
│   ├── classes/siteconfig/{inspector,applier,drift}.php  # changed: hand pathways its payload
│   ├── classes/hook_callbacks.php       # changed: + Pathways in primary navigation
│   ├── classes/output/mobile.php        # changed: + pathways_view
│   ├── classes/mentoring.php            # changed: each learner row links to their pathways
│   ├── classes/privacy/provider.php     # changed: pathway_cohort.usermodified
│   ├── templates/pathways.mustache, pathway.mustache, pathways_manage.mustache, mobile_pathways.mustache  # new
│   ├── templates/mentoring.mustache     # changed: Pathways link
│   ├── db/install.xml, db/upgrade.php   # changed: four tables, two competency columns
│   ├── db/services.php, db/mobile.php   # changed
│   ├── lang/en/local_ltuse.php          # changed: "Spec 006: pathways" block
│   ├── version.php                      # 2026100600
│   └── README.md                        # changed: pathways, core reads
└── REQUIREMENTS.md                      # row 12 updated on delivery
scripts/
├── site_config.py                       # changed: pathways.yaml, competency slug/url, levels
└── publish_moodle.py                    # changed: set_course_pathway after set_course_competencies
tests/
├── test_site_config.py                  # changed: pathways.yaml, slug/url, levels
├── test_publish_moodle.py               # changed: the new call, dry run, read-back mismatch
├── test_pathway_wording.py              # new: every 006 string passes cbc_wording strict
└── pathway_harness.php                  # new: builder and viewer
.github/workflows/site-config.yml        # changed: run pathway_harness.php and the new pytest
```

**Structure Decision**: Follow 003 and 004. Pure decisions (layout, next, visibility) are
classes with no Moodle calls, tested by a PHP harness in CI. Database reads sit in thin classes
beside them. Site configuration gets one class beside `competencies.php`, handed its payload by
`inspector`. The publisher gains one call, placed after the competency map so a pathway is
never computed from a stale map.

## Retiring a course

To take a course out of delivery, the site team hides it in Moodle. A hidden course leaves every
pathway at once, and its learners keep their completion records. A course removed from the repo
stops being republished, but its Moodle row stays until hidden. The README says so. This uses
existing course visibility and adds no mechanism.

## Decisions for the maintainer

1. **006 enrols nobody** (R9). Assigning a pathway to a cohort changes what members see, not
   their enrolments. Spec 008 owns enrolment. Should a manager's assignment also enrol, that is
   one observer in 008.
2. **Meta: Uncategorized has no pathway** (R12). This deviates from the spec's Edge Cases, to
   stay consistent with 004 and the competency site.
3. **Role pathways ship empty** (R5). Story 3 works as soon as a role is added to
   `pathways.yaml`. No role is invented here.
