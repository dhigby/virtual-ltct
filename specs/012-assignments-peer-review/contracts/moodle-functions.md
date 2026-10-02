# Contract: Moodle-side functions

Web-service functions added to `local_ltuse`, all in the existing `ltuse_publish` service and behind `local/ltuse:publish`. The rule from Principle XI applies: each one calls Moodle's public APIs and writes no core-owned table directly. Every API named here is confirmed in [research.md](../research.md), and the R-numbers say where.

## `local_ltuse_upsert_assignment`

Creates or updates one `mod_assign`, addressed by `idnumber`. Idempotent.

| Param | Type | Notes |
|---|---|---|
| `courseidnumber`, `idnumber`, `name`, `section` | as `create_page` | |
| `intro` | raw HTML | learner-visible brief and criteria |
| `introitemid` | int | draft area holding the brief's images, or 0 |
| `submittext`, `submitfile` | bool | → `assignsubmission_onlinetext_enabled`, `assignsubmission_file_enabled` |
| `maxbytes`, `maxfiles`, `filetypes` | int, int, string | → `assignsubmission_file_maxsizebytes`, `…_maxfiles`, `…_filetypes` |
| `required` | bool | → `completion=2, completionsubmit=1, completionusegrade=1` or `completion=0` (R2) |
| `criteria` | list of `{shortname, description, descriptionmarkers, maxscore}` | the marking guide (R1) |
| `allowcriteriachange` | bool, default false | R7 |

Behaviour:
- Calls `util::upsert_module($course, 'assign', …)` with:
  - `assignfeedback_comments_enabled = 1`
  - `grade` = the sum of `maxscore`, with `gradepass = 0`
  - `groupmode = SEPARATEGROUPS`
  - `advancedgradingmethod_submissions = 'guide'`
- Sets `completionunlocked = 1` only when the live completion differs from `required` (R2).
- Then `get_grading_manager($context, 'mod_assign', 'submissions')->get_controller('guide')->update_definition()`, with:
  - `status = READY`
  - `alwaysshowdefinition = 0`
  - `showmarkspercriterionstudents = 1`
  - existing criterion ids reused by shortname
- If the definition has graded instances and the criteria set or a `maxscore` would change, it throws `error:criteriachange`, unless `allowcriteriachange` is set.
- Never passes `grade_rescalegrades`.
- Returns `{cmid, instance, created, completionchanged}`.

## `local_ltuse_upsert_workshop`

Creates or updates one `mod_workshop`. Idempotent.

| Param | Notes |
|---|---|
| `courseidnumber`, `idnumber`, `name`, `section`, `intro` | as above |
| `instructauthors`, `instructreviewers` | HTML; each saved through a fresh `file_get_unused_draft_itemid()` draft area (R4) |
| `aspects` | list of `{description}`, one per criterion, for the **comments** strategy |
| `peers` | int; stored for the allocator |
| `required` | bool; `completion=2, completionusegrade=1` when true (grades arrive on close, R4) |

Behaviour:
- Calls `upsert_module` with `strategy = 'comments'` and `groupmode = SEPARATEGROUPS`. It **never** passes `phase`. On create, the workshop starts in setup, and the mentor moves it on.
- Then calls the strategy's public `save_edit_strategy_form()` with the edit-form data shape, keeping existing dimension ids.
- Stores `peers` and `autoallocate=1` in `workshopallocation_orgcohort`'s own table.
- Never touches allocations, submissions or assessments.

## `local_ltuse_ensure_discussion`

| Param | Notes |
|---|---|
| `courseidnumber`, `idnumber`, `name`, `intro` | `name` and `intro` are used **only on create** |
| `shared` | bool → `VISIBLEGROUPS` if true, else `SEPARATEGROUPS`; `groupingid = 0` |

Behaviour:
- Creates a `type = general` forum in section 0 if it is absent.
- If it exists, sets only its group mode, and clears a hand-set grouping. It writes no discussion or post. Group mode goes through `formatactions::cm()->set_groupmode()`. A grouping is cleared through `update_moduleinfo()`, core's only setter for it, which makes `forum_update_instance()` re-read the forum's ratings. The maintainer chose that over a direct table write (2026-10-02).
- Reports `courseforced = true` when `course.groupmodeforce` overrides the setting.
- Returns `{cmid, created, groupmode}`.

## `local_ltuse_create_page` (changed)

- Gains `prohibitstudentview` (bool, default false). When it is true, it applies `role_change_permission()` at the module context to set `mod/page:view` = prohibit for the `student` archetype role, as the second lock on mentor-notes pages (R1).
- Mentor-notes pages are always sent with `visible = 0`.

## `local_ltuse_hide_modules` (already on main, PR #71)

- This spec first built its own `hide_orphans`. Main shipped `local_ltuse_hide_modules` for the same R7 gap first, so this spec uses that one and dropped its own.
- The publisher computes which of the server's `ltct:<slug>:` modules this run no longer produces and passes those idnumbers. It never deletes anything, and it refuses the question bank.
- **Spec 012's one obligation**: the discussion forum `ltct:<slug>:discussion` is produced by no file, so the publisher counts it as this run's. Otherwise every publish would hide the forum (`tests/test_publish_moodle.py::test_discussion_forum_is_never_hidden`).
- Every assignment, workshop and mentor-notes idnumber added in Phases B and C must be counted the same way.

## Plugin `workshopallocation_orgcohort` (new, `moodle/workshopallocation_orgcohort/`)

- Plugin type `workshopallocation`, installed at `mod/workshop/allocation/orgcohort`.
- Declares `requires` and `supported` per Principle XI.
- For each submission, it picks `peers` reviewers holding `mod/workshop:peerassess`:
  1. first from the author's **cohort** group (group idnumber `ltct:cohort:<key>`, spec 002)
  2. then from other groups of the same **organisation** (`ltct:org:<key>`)
  3. never anyone sharing no organisation group with the author
- It balances load and never allocates a self-review. It calls `workshop::add_allocation()` only.
- Runs from the Allocation page ("Allocate within organisation"), and from an observer on `\mod_workshop\event\phase_switched` into `PHASE_ASSESSMENT` when `autoallocate = 1`.
- Reports authors left with fewer than `peers` reviewers, so the mentor knows whom to assess (FR-013).
- Its own table, `workshopallocation_orgcohort` (`workshopid`, `peers`, `autoallocate`), is its only storage.
