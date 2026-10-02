# Research: Assignments and peer review in courses

Every Moodle API below was looked up in Context7 (`/websites/moodledev_io_5_2_apis`, `/moodle/moodle`) and then confirmed in upstream source on `MOODLE_502_STABLE` on 2026-10-01, under `public/` (the 5.2 layout). App behaviour was confirmed in `moodlehq/moodleapp` `main`. **VIS** means read in that source file. **DOC** means documentation only, and is re-verified on the temporary 5.2.3+ instance before the plan depends on it (constitution X). The R-numbers are cited from [plan.md](plan.md), [design.md](design.md) and the contracts.

## R1. Where a learner's brief, and mentor-only text, can live in Moodle

- **Decision**: the brief and the learner-visible criteria go in the assignment's `intro` (Description) field. Per-criterion **grading notes** go in a core **marking guide** (`gradingform_guide`), in each criterion's *description for markers*, with the guide option `alwaysshowdefinition = 0`. The **model answer** goes in a mentor-notes `mod_page` created **hidden** (`visible = 0`, not stealth), with a module-level prohibit on `mod/page:view` for the `student` role as a second lock.
- **Rationale**:
  - The guide has exactly the field we need. Each criterion has a `description` (students) and a separate `descriptionmarkers` (`guide/lib.php` `update_definition()` L111, criteria fields L167). The student renderer omits the markers' text: `display_instance()` uses `DISPLAY_VIEW` when the user cannot grade (`guide/renderer.php` L750–757), and the `DISPLAY_VIEW` / `DISPLAY_PREVIEW_GRADED` branches add only `description` (L198–206). Graders get `DISPLAY_REVIEW` / `DISPLAY_EVAL`, which show both. **VIS**.
  - `core_grading_get_definitions` returns the whole definition to a non-manager whenever it is READY. The exception is `alwaysshowdefinition = 0`, when it returns nothing (`lib/classes/grading_external.php` L120–147). **VIS**. With the option off, the web-service route is shut too. Learners lose Moodle's preview of the guide, but the learner-visible criteria are already printed in the brief, so nothing is hidden from them that the author meant them to see.
  - Rubrics have **no** per-criterion private field, and their only hideable text (the definition description) still goes out over that web service (research notes §2). **VIS**.
  - No assign field holds teacher-only text. `activity` ("Activity instructions") is learner-facing and returned to students by `mod_assign` web services (`assign/db/install.xml`, `externallib.php` L500, L2532). **VIS**.
  - Hidden modules need `moodle/course:viewhiddenactivities` (teacher, editingteacher, manager; `lib/db/access.php` L1005–1012). **VIS**. Stealth pages are reachable by link, so they are never used.
- **Alternatives considered**: a rubric (no private field, and its description leaks over the web service); `activity` (learner-facing); a stealth page (reachable by URL); keeping the model answer out of Moodle so mentors read it on the `/review/` site (it works, but sends mentors to a second place and to a site holding every answer key); a role-based availability condition (none exists in core).

## R2. Completion for a required assignment (FR-019)

- **Decision**:
  - A **required** mentor-reviewed assignment uses `completion = 2`, `completionsubmit = 1` and `completionusegrade = 1`, with no pass grade.
  - Its grade is a **point grade**: the sum of the guide criteria's maximum scores, with `gradepass = 0`.
  - An **optional** assignment uses `completion = 0`.
  - When a republish changes the declared completion, the publisher sets `completionunlocked = 1` for that call only, and reports the change.
- **Rationale**:
  - "Receive a grade" completes on any non-null grade when `gradepass` is 0 (`lib/completionlib.php` L1584–1615). **VIS**.
  - With `grade = 0`, the grade item is text-only, so feedback alone never completes the assignment (`assign/lib.php` L1012–1050). **VIS**. A marking guide needs a point grade anyway.
  - `update_moduleinfo()` ignores completion changes unless `completionunlocked` is set, and when it is set it recalculates every learner's completion (`course/modlib.php` L656–672, L790–797). **VIS**. Recalculation is derived state: grades and submissions are untouched.
- **Spec consequence**: "feedback returned" is implemented as "the mentor saved a marking-guide grade". Every save of the guide writes a score, so the two coincide. The score is course training evidence only (Principle V). It is never a threshold and never a CBC level.

## R3. Who assesses: the mentor role and grading capability

- **Decision**: the assessor is a **course-level** role, the core non-editing `teacher` role (called "Course mentor" in our docs; the Moodle role is not renamed, since that would relabel it on every course). It is enrolled in the course and placed in the learner's cohort group. It does not hold `moodle/site:accessallgroups`, so in separate-groups mode it sees and grades only its own groups. Spec 003's user-context mentor role stays the long-running relationship. It cannot grade, because `mod/assign:grade` and every `mod/workshop:*` capability are module-context (`assign/db/access.php` L49–58; `workshop/db/access.php`). **VIS**.
- **Cross-spec conflict, raised and not routed around**:
  - Spec 003 FR-006 says a mentor "MUST NOT be able to change a learner's grades". In Moodle, assessing with a marking guide *is* writing a grade.
  - Recommendation: amend 003 FR-006 to cover only the user-context mentor role, and record that assignment feedback is given through the course-level role. The same rule must also never let it award a CBC level.
  - Keeping the two in step automatically is deferred. That means enrolling a learner's spec-003 mentor as Course mentor in each course the learner takes. It would be our own code (a sync task) and belongs with spec 003's plan or spec 008's tooling.
  - Until then, the organisation manager enrols the Course mentor together with the cohort.
- **Alternatives considered**: granting `mod/assign:grade` to the user-context role (impossible, wrong context level); `editingteacher` (has `accessallgroups`, so it would see other organisations' work, against FR-011 and SC-004).

## R4. Peer review engine and allocation (FR-011)

- **Decision**:
  - Peer review uses core **`mod_workshop`**, with the **comments** grading strategy. Peers write a comment against each learner-visible criterion and score nothing.
  - Allocation uses **our own workshop allocation subplugin**, `workshopallocation_orgcohort`, which calls the public `workshop::add_allocation()`. It allocates from the author's cohort group first, then from other groups of the same organisation, and never from another organisation.
  - It runs when the mentor clicks "Allocate" in the workshop's own Allocation page, or automatically on the `\mod_workshop\event\phase_switched` event into the assessment phase, when the instance enables that.
- **Rationale**:
  - Core random allocation in separate-groups mode stays strictly inside the author's group and stops when the group runs out (`workshop/allocation/random/lib.php` L509–575). **VIS**.
  - Visible-groups mode can cross groups, but it knows nothing of organisations. So the clarified rule (cohort first, organisation fallback, never across) is not in core.
  - `add_allocation($submission, $reviewerid, $weight, $bulk)` is public (`workshop/locallib.php` L1537), and allocators are a supported plugin type (`workshopallocation`). This is Principle XI form 3, with no vendored edit.
- **Facts the design relies on**:
  - **Phases are per instance** (`locallib.php` L49–53, `switch_phase()` L2006–2038). A peer-review assignment runs as a cohort exercise, and the mentor switches phases.
  - Students **see author names by default** (`mod/workshop:viewauthornames` allows the student archetype). FR-012a needs a declared override. Reviewer names are already hidden from students. **VIS**.
  - Mentor fallback (FR-013): a user with `mod/workshop:allocate` gets "Assess", which self-allocates the assessment. In the evaluation phase, a user with `overridegrades` writes feedback to the author (`submission.php` L74–171; `locallib.php` `assessing_allowed()` L1941–1944). Both are teacher defaults. **VIS**.
  - Completion is by view or grade only, and grades reach the gradebook only when the workshop **closes** (`workshop/lib.php` `workshop_supports()` L54–63; `locallib.php` L2014–2023). **VIS**. A *required* peer-review assignment therefore completes for the whole cohort when the mentor closes it. The design states this limit (D6).
  - Strategy definitions are written only through `save_edit_strategy_form()`, in edit-form data shape, and are all shown to reviewers (`workshop/form/*/lib.php`). **VIS**. So no mentor-only text ever goes into a workshop form. It goes to the mentor-notes page (R1).
  - `instructauthors`, `instructreviewers` and `conclusion` are saved only when their editor `itemid` is non-zero (`workshop/lib.php` L79–230). **VIS**. The plugin passes a fresh empty draft area from `file_get_unused_draft_itemid()`, so it writes no table directly.
- **Alternatives considered**:
  - Core random allocation scoped to organisation groups only. This loses cohort-first, and the clarification asked for it.
  - Core random allocation scoped to cohort groups, with the mentor topping up by hand through manual allocation. This puts LMS mechanics on the mentor (Principle VI).
  - A scheduled task in `local_ltuse` instead of a subplugin. It works, but it is invisible on the workshop's own Allocation page, where a mentor would look.
  - A third-party allocator. None found in the plugins directory with organisation scoping.

## R5. In-course discussion and organisation separation (FR-015)

- **Decision**:
  - Every published course gets one `forum` of type `general`, identity `ltct:<slug>:discussion`, in section 0, created by the publish if absent. Its name and intro are set on creation only, and its posts are never touched.
  - Its group mode is **separate groups**, with `groupingid = 0`, unless `moodle/site/course-discussions.yaml` marks the course `shared` (visible groups).
  - The publisher sets the group mode on every publish. `site_config.py drift` reports a live mismatch, and `apply` corrects it.
- **Rationale**:
  - Separate groups hides a group's discussions from non-members without `accessallgroups` (`forum/lib.php` `forum_user_can_see_group_discussion()`). **VIS**.
  - With `groupingid = 0`, a learner sees discussions of every group they belong to. Spec 002's invariant is that **every group in a course belongs to exactly one organisation**: organisation groups, and cohort groups inside them. So no discussion is visible across organisations, and no grouping has to be kept in step.
- **Known leak path, closed by role, not by hope**: a discussion posted to "All participants" (`groupid = -1`) is visible to every group (`forum/classes/local/managers/capability.php` L150–182). **VIS**. Only users with `accessallgroups` can post there in separate-groups mode: editing teachers and managers, never the Course mentor or a learner. The mentor guide says so, and the drift report lists any `-1` discussion in a separated forum as a warning, without reading its content.
- **Alternatives considered**: an organisations grouping (correct, but one more object per course to keep in step); one forum per organisation (it multiplies modules and breaks the "one clearly named space" scenario); frontmatter `shared:` (forbidden by FR-015).

## R6. Moodle app behaviour (FR-009, SC-005)

- **Decision**: briefs say "can be prepared offline in the app" for text and file submissions. Mentors are told that assessing with the marking guide is done in a browser.
- **Rationale**:
  - Assign online-text and file submissions are edited offline and synced later (`assign-offline.ts` L368; `submission/*/services/handler.ts` `isEnabledForEdit()`). **VIS**.
  - Workshop submissions and assessments work offline (`workshop-offline.ts` L204, L329, L453), and so do forum posts (`forum-offline.ts`). **VIS**.
  - App grading supports only `simple` grading, so advanced grading needs the web (`submission.ts` L634–640). **VIS**.
  - The free app plan's limit of **2 offline courses per device per site** (moodle.com/app; **DOC**) is reported to spec 009. It does not change this design.

## R7. Identity, republish and removal (FR-006, FR-008)

- **Decision**:
  - Identities:
    - `ltct:<slug>:<NN>-…-assignment.md` for the assign or workshop
    - `ltct:<slug>:<NN>-…-assignment.md:mentor-notes` for its hidden page
    - `ltct:<slug>:discussion` for the forum
  - Guide criteria are matched to the source by criterion **shortname**, so their ids survive a republish.
  - A republish that would add, remove or rename a criterion, or change its maximum, on an assignment that already has grades stops with a message and needs `--allow-criteria-change`. That flag guards learners' grades, not disclosure; the disclosure gate still has no override.
  - A module whose source is gone is **hidden**, never deleted. Neither `phase` nor allocations are ever passed on a workshop update.
- **Rationale**:
  - `update_moduleinfo()` leaves submissions and grades alone (`course/modlib.php` L700–797). **VIS**.
  - Re-saving a definition with new ids deletes the old criteria and flags fillings for regrade. **VIS** for rubric (research notes §2); the guide's `update_definition()` follows the same pattern and is re-checked at T-verify.
  - Deleting a module deletes its submissions unless the recycle bin catches them, temporarily (`admin/tool/recyclebin/lib.php` L148–164). **VIS**.
- **Gap found**: `publish_moodle.py` documents "a module the course no longer has is HIDDEN", but no code does it today. This spec builds it, using `local_ltuse_get_course_manifest`, because removing an assignment from the source is one of the spec's edge cases.

## R8. Where the design gate is enforced (FR-001, SC-001)

- **Decision**: until the maintainer approves [design.md](design.md), `check_course_package.py` fails CI on any `*-assignment.md` under `modules/`, with "assignments wait on the spec 012 design approval". This guard ships first, before anything else in this spec. When the design is approved, the same change that records the decision replaces the guard with the format check in [contracts/assignment-file.md](contracts/assignment-file.md).
- **Rationale**: SC-001 asks for zero assignment files before the decision. A CI check keeps that true even when a contributor ignores the agent.

## Settled on the temporary 5.2.3+ instance before tasks depend on them

These are research tasks (constitution X), not assumptions:

1. A learner in no group, and a learner in both an organisation group and a cohort group, in a separate-groups forum and workshop: what they can read and post.
2. Guide `update_definition()` with ids kept stable, after grading: the grades survive and no regrade flag appears.
3. A learner, through the web, the app and every web service in the mobile service, never receives `descriptionmarkers` or the hidden page's content.
4. `file_get_unused_draft_itemid()` passed to the workshop instruction editors and to `activityeditor` saves the text and attaches nothing.
5. With the `student` override on `mod/workshop:viewauthornames`, the workshop shows no author name to a peer in the web or the app.
6. `backup_auto_users` and the recycle bin, so the runbook can say truthfully what an accidental delete loses.
