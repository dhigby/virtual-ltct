# Impact analysis: cross-organisation competency courses

**For**: Doug · **Date**: 2026-10-02 · **Linked from**: [handoff.md](handoff.md)

**The direction (Doug, relayed by Matthew, 2026-10-02):** competency courses run across organisations (most mentors will be SIL). Cohorts are small, so there are no organisational walls inside a course. An organisation may host a course only for its own people, as the exception. Organisation leaders can still manage their people in Moodle.

This document lists what would have to change to get there. It is wider than spec 011: it reverses spec 002's in-course separation and touches 003, 004, 012 and 013. Line numbers are as of `main` at `de31ede`. Paths are repo-relative; `003:` means the `003-mentor-role` branch. The Moodle claims were confirmed in `MOODLE_502_STABLE` source by research agents, and each still has to be verified on the instance (constitution X). Bare `spec.md`, `research.md`, `data-model.md`, `plan.md` and `tasks.md` mean spec 002's, unless another spec is named.

## 1. Summary

Spec 002 uses separate groups for two jobs. The obvious one is walling organisations apart inside a shared course. The less obvious one is keeping an organisation manager to their own people: `orgmanager` is enrolled in each course, has no `accessallgroups`, and so sees only its own group. The publisher re-applies that wall on every publish. The course discussion forum and the planned peer-review allocator are both built on it.

Dropping the walls is a paired change:

1. **Open the courses.** Shared courses default to no groups, and the publisher stops forcing separate groups back on.
2. **Scope managers some other way.** Managers stop being enrolled in shared courses. They follow their people through the per-organisation reports that spec 004 already scopes by the `ltct_org` profile field, optionally with a user-context "parent" role for per-learner detail.

Org-only courses stay as they are designed today: a course in the organisation's `ltct:org:<key>` category, kept to that organisation by enrolment alone. They need a declared host so the publisher can put the course in the right category and keep it there.

## 2. What changes

### Configuration (`moodle/site/*`)

| Where | Today | Change |
|---|---|---|
| `settings/groups.yaml:58-63` | `moodlecourse/groupmode = 1` | Set it to `0` and rewrite the `why`. |
| `settings/groups.yaml:49-56, :64-68` | The file's purpose and the `groupmodeforce` `why` say organisations "stay apart inside a course they share". | Keep the values and reword: shared courses are open, and a course or activity may opt into groups. |
| `settings/groups.yaml:69-75, :76-81, :82-87` | `unenrolaction = 3`, `forceloginforprofiles = 1`, `hiddenuserfields` | Unchanged. Reword the `forceloginforprofiles` `why` if the profile hook is narrowed. |
| `organisations.yaml:16-20, :25`; `settings/cohorts.yaml` | Category, hidden org cohort and managers cohort per organisation | Structure unchanged. Only the wording changes: shared courses are cross-organisation. |
| `reports.yaml:26-58` | Per-organisation progress report: condition `ltct_org = {org}`, audience `ltct:org:{org}:managers` | No scoping change. This becomes the main way managers follow their people. |
| `reports.yaml:35, :109` | `group:name` column | **[DEL/SIMPL]** Drop it, since it would be empty. The `groupconcatdistinct` fallback (spec 004 `tasks.md:61, :318`) goes with it. |
| `course-discussions.yaml:1-20` | Forums are separate groups by default; `shared: []` lists the exceptions. | **[DEL]** Retire the file, or invert it to list separated courses. Keep `rows: [10]` if it is kept. |
| `README.md:14, :16` | Describes the forum as "separated by organisation unless listed". | Rewrite. Add how an org-only course is declared. |
| `README.md:169-180` (site team steps 2-4) | Check separate groups, create an org group, add two cohort-sync methods into it, then unenrol movers by hand. | **[SIMPL]** Split into two recipes. Shared course: one cohort sync per organisation, as Student, no group, no managers cohort. Org-only course: the existing recipe in the org's category. Step 4 (hand unenrol) goes for shared courses. |
| `README.md:181, :152` | Managers only follow their own people, through reports. | Keep. Add "through their organisation's report". |

### Roles (`moodle/site/roles.yaml`, `scripts/site_config.py`)

| Where | Today | Change |
|---|---|---|
| `roles.yaml:89-114` (orgmanager) | Holds `course:viewparticipants` (:101), `report/progress:view` and `report/completion:view` (:103-104), and `site:viewuseridentity`. These are scoped "to their own group only" by separate groups. | In a no-groups course this shows every organisation's participants, completion and email addresses. Keep the role only for org-only courses and rewrite the description and `why`. |
| `roles.yaml:125-135` (teacher) | Missing `accessallgroups` is said to "keep a mentor inside their organisation's groups (#10)". | Keep it as `inherit` so drift still catches a hand grant. Rewrite the `why`: it no longer scopes anyone in shared courses. The same text is in `003:moodle\site\roles.yaml:105-111`. |
| `roles.yaml:67` (user `badges:viewotherbadges: inherit`) | Hides badges. Groups also stopped cross-organisation profile access. | Keep. It is now the only thing hiding badges from classmates. State that in spec 013 R10. |
| `roles.yaml:116-123` (student `workshop:viewauthornames`) | Peer anonymity | Unchanged. It matters more across organisations. |
| `site_config.py:273-280` (`ORGMANAGER_DENY`); `tests/test_site_config.py:550` | Refuses `accessallgroups` and other reach-widening capabilities for orgmanager. | Keep as least privilege. Consider adding `moodle/site:viewuseridentity`. |
| `003:moodle\site\roles.yaml:114-136` (mentor) | Lacks `moodle/badges:viewotherbadges`. | Add `allow`, plus the matching validate allowlist entry (spec 003 R2). Spec 013 US3 needs it. This is not caused by the org change. |
| New (optional, §3) | — | A user-context "organisation manager (follow)" role. |

### `local_ltuse` code

| Where | Today | Change |
|---|---|---|
| `lib.php:10-63`, `classes/profile_access.php:8-54`; R9 (`research.md:168-194`) | Refuses any managers-cohort member the profile of anyone outside their organisations, in every context. | Narrow it, don't delete it. As written it stops a manager who is also a learner from opening classmates' profiles in a shared course, which is a wall (spec.md:94). Options: skip the check when `$course` is set and not in an `ltct:org:*` category; or skip it unless the viewer's only path to that person is an orgmanager enrolment. `decide()` needs a course-scope input, and `tests/profile_access_harness.php` needs new cases. `local_ltuse_managed_organisation_keys()` (:65-112) is unchanged. |
| `classes/external/ensure_discussion.php:22-48, :66-68, :92, :153-155` | Defaults to SEPARATEGROUPS; `shared` switches to VISIBLEGROUPS. | Default to NOGROUPS, or VISIBLEGROUPS if label groups are kept. Drop or invert `shared`. Keep `apply_groupmode` (:169-186), the groupingid clear and courseforced reporting. Rewrite the docblock. |
| `classes/siteconfig/inspector.php:35, :1034-1054, :1092, :1100-1127`; `applier.php:100-101, :175-190` | Discussion drift, the allparticipants warning and the forum vault count. | **[DEL]** Remove the allparticipants warning (:1103-1110) and the vault count (:1116-1127). Keep the drift and apply mechanism against the new default. Invert the meaning of "forced". |
| `classes/util.php:199-200` | Pages, quizzes and certificates are created with groupmode 0. | No change; this already fits. Note that quiz reports were never organisation-scoped. |
| `classes/reportbuilder/local/entities/coverage.php:25-35, :82-93, :136-175`; `tests/competency_coverage_test.php` | "In delivery" means a cohort-sync instance with the student role. | Unchanged while delivery stays per-organisation cohort sync, now with `customint2 = 0`. |
| Planned, not built: `upsert_assignment` (012 T032), `upsert_workshop` (T048), `workshopallocation_orgcohort` (T047/T049/T051) | Separate groups; "never across organisations". | **[DEL]** The allocator plugin is probably unnecessary: use core random allocation within cohort groups. Settle the assignment group mode before T032 is built. |

### Publisher and payload

| Where | Today | Change |
|---|---|---|
| `scripts/publish_moodle.py:111-115, :183, :208` | `GROUPMODE_SEPARATE = 1` on every create and update, which rebuilds the wall each time. | Send 0, or stop sending groupmode on update. Keep it unforced. The same lines in `003:scripts\publish_moodle.py:97-140` need the same change, or merging spec 003 brings the wall back. Keep 003's `showreports: 0`. |
| `tests/test_publish_moodle.py:427-464`, :107, :174 | Assert groupmode 1. | Change the expected value. Keep `test_groupmode_is_not_forced` and fix its comment. |
| `scripts/publish_moodle.py:439-458` | Label "separated by organisation"; courseforced warning says "organisations may see each other's posts". | Reword. The warning becomes "a forced mode walls the cohort's forum". |
| `scripts/moodle_payload.py:21-22, :119-128, :437-456, :646-651`; `site_config.py:17-18, :65, :244, :464, :761` (`load_discussions`) | `discussion.shared` is opt-in; absent means separated. | **[SIMPL]** Derive the scope from org-only status, or drop the field. Keep the intro warning about partner data (:124-128). |
| `tests/test_moodle_payload_discussion.py:49-75`; `tests/test_site_config.py:624-710` | Pin "absent means separated". | Rewrite or delete. |
| `scripts/publish_moodle.py:532-533, :579, :201, :178-187` | `--category` is a bare int, sent only on create. | Resolve the category from the declared host (§4) and send `categoryid` on update too. |
| `scripts/moodle_client.py:220` | Required-function list | Change only if the `ensure_discussion` signature changes. |
| `scripts/check_moodle_payload.py` | Disclosure only | Unchanged. Optionally validate the host key. |
| `process/stages/08-publish.md:61-65` | No enrolment step. | Add the shared-course and org-only enrolment recipes. Stage 7 is unchanged. |

### Specs to amend

- **002:** spec.md:9, :17 (Q1 "one group per organisation"), :20, US2 :42-56, FR-003 :105 (state that org-only means restricted enrolment, not hidden content), FR-007 :109 (scope managers' follow-up data, not peer visibility; drop the moved-learner limit), FR-011 :113 (replace with "courses MAY use groups; organisations are not separated by group"), SC-002 :129, SC-004 :131, :162, Dependencies :169. Also plan.md:9-11 (**[DEL]** the "Separation comes from groups" paragraph), :25, :44; research R2 :13-27, R3 :29-51 (rewrite, including the Verify list), R7 :148, R8 :154-166 **[SIMPL]**, R9 :168-194 (narrow); data-model.md:300-318, :341, :349, :353-365, :376-377; contracts/declaration.md:142-144, :150, :172-178; tasks T023/T026 (:178, :191), :208-211, :270. Record a 2026-10-02 clarification.
- **003:** research R8 `003:specs\003-mentor-role\research.md:261-266` (drop "with the organisation's group"); tasks T034 (:300, re-correct 012's sentence) and T035 (:303, mentor with no group, plus a check across two organisations); spec.md:123; the docblock at `profile_access.php:11-15`. R7 Phase B and US3-4 are unchanged.
- **004:** R8 (`research.md:165-177`), contracts/declaration.md:244-253, tasks :250, :291 (T036), :319; plan.md:18, :151; quickstart V6 :54-56 (rewrite: no in-course reports for managers in shared courses, or run V6 in an org-only course only). FR-006 second sentence (spec.md:107) needs relaxing if per-lesson detail is lost. FR-005 and R7 are unchanged.
- **012:** spec.md:15, :19, :60, :64, :68, :78-87, :99 (partner data in cross-organisation peer review needs an explicit rule), :103-104, FR-011 :126, FR-015 :131, SC-004 :152, :168-169, :190. Also design D6 :114-118 (change before approval, :141-148), research R3 :31-36 (who enrols SIL mentors), R5 :62-72, :108, data-model, contracts, quickstart A4 :36-38, C1, and tasks T025, T047-T051, T056 **[SIMPL]** (withdraw the "one org per group" demand on 002).
- **013:** spec.md:56 (US3-2), FR-011 :97, :152 (reword the mechanism); research.md:163-167 (R10 rationale), :175 and plan.md:144 (note the reliance on 004's `ltct_org` scoping); research.md:70-79, plan.md:143, quickstart V4 :19 (wording, only if enrolment changes); quickstart V9 :27 (co-enrol B1 with A1; Manager A's own learner opens with no badges; Manager B is refused).
- **005, 006, 008:** 005 US3 and FR-007: org rooms in the community space stay. Say that removing groups from delivery courses does not remove them there. 006 FR-012: "via 002/004" must mean `ltct_org` scoping. 008 FR-008 :98 ("category-scoped manager roles" is already inaccurate) and US4: define a shared-course case and an org-only case for enrolment. 007 and 014: wording only, or none.

### INTENT, constitution, REQUIREMENTS, CLAUDE.md

- **`INTENT.md`:**
  - :110: keep "one site"; replace "separated inside it".
  - :61: replace "grouped with their own colleagues".
  - :64: clarify that scoping applies to reports.
  - :96: frame the org-only course as one standard shape.
  - :126-129: append a 2026-10-02 decision that supersedes the in-course part. Don't edit the 2026-10-01 entry.
  - :139: add the open questions: who approves an org-only course, and who enrols into it.
- **`.specify/memory/constitution.md:236-240`:** "are separated by" becomes "are identified and scoped by". Add: shared by default, one declared org-only mechanism, manager scoping through reporting. Amend via speckit-constitution with a version bump. Also :119: the org-only course must be a uniform variant, not a per-partner special case.
- **`moodle/REQUIREMENTS.md`:**
  - #7 :34: drop core per-course reports for managers.
  - :35: rework the SC-002 test so learners see each other and managers see only theirs; add an org-only closure test.
  - #8 :40.
  - #10 :42.
  - #11 :43 **[SIMPL]**.
  - #15 :47-53.
  - Reset all of these to re-verify (Principle X).
- **`CLAUDE.md`:** optionally add one line under "Delivery: Moodle": shared courses have no org groups, and managers are scoped by reports. This stops agents bringing back `groupmode 1`.

## 3. How org managers keep following their own people

Research confirms in source that in visible-groups or no-groups mode, `report/progress`, `report/completion`, Participants and the gradebook show every learner to anyone holding the capability. No course-level role can be scoped to one organisation there.

**Mechanism 1 (recommended): per-organisation report builder reports.** These are already built (`reports.yaml:26-58`; `site_config.py:1208-1218`).
- **Confirmed in source:**
  - `reportbuilder/classes/permission.php` `can_view_report()` checks the audience only.
  - The `cohortmember` audience gates who may view, not which rows.
  - Viewers can remove filters but not conditions.
  - The Course participants datasource includes course completion.
- **Cost:** no per-lesson ticks or per-quiz pass/fail (there is no core activity-completion datasource), so FR-006's second sentence needs relaxing.
- **Verify on the instance:**
  - `enrol:plugin = cohort` still matches when the cohort-sync instance has no group (`customint2 = 0`). If the condition matches nothing, the report widens to everyone (MDL-84213, 004 `research.md:243-250`), so re-run the fail-closed SQL proof.
  - Managers hold no `moodle/reportbuilder:edit` or `editall`.
  - Downloads and the scheduled email respect the conditions.

**Mechanism 2 (recommended addition): a user-context "parent" role.** This restores per-learner detail with no course enrolment.
- **Confirmed in source:**
  - `user/view.php:95-98, :137` (`$isparent`)
  - `course/user.php:71, :102, :114`
  - `report/outline/user.php:50`
  - `grade/report/overview/index.php:57`
  - `completionlib.php` `completion_can_view_data()` ~:205-209
  - `blocks/mentees` (the "my people" list)
  - `moodle/user:viewuseractivitiesreport` is CONTEXT_USER with RISK_PERSONAL (`lib/db/access.php:1414`)
- **Automation:** `tool_cohortroles` is core in 5.2 (version 2026042000). It runs an hourly `cohort_role_sync`, but its records are keyed to individual managers (userid, roleid, cohortid). `local_ltuse` would have to keep those records in step with `ltct:org:<key>:managers` through `api::create_cohort_role_assignment()` and `delete_cohort_role_assignment()`. The alternative is our own observers on `cohort_member_added` and `cohort_member_removed` calling `role_assign(..., 'local_ltuse')` (`accesslib.php:1579`, `:1691`), plus a reconcile task.
- **Does not cover:** `report/progress`, `report/completion`, Participants or the grader report.
- **Verify on the instance:**
  - The role is assignable at CONTEXT_USER.
  - The sync task runs.
  - `block_mentees` is available.
  - A manager who loses the managers cohort loses every assignment.
  - The narrowed profile hook still lets a manager through to their own learners.

**Mechanism 3 (not recommended): inverted separate groups.** Grant `accessallgroups` to student and teacher, withhold it from orgmanager, and keep courses in separate groups. It is confirmed workable through the `'aag'` branches in `grouplib.php`. But managers would have to be enrolled and grouped in every course, they would appear as participants, and learners would hold a capability core treats as unrestricted everywhere.

A fourth option is a `local_ltuse` datasource with a viewer-relative "same organisation" condition. It is feasible (custom filter `get_sql_filter()` using `$USER`; no core injection hook), but 004 R7 already rejected our own code on this path.

**Recommendation:** Mechanism 1 now. Add Mechanism 2 if leaders need per-learner detail. Don't enrol the managers cohort in shared courses at all.

**Managing people, if leaders should enrol their own people.** Core selectors search every site user (`enrol/manual/locallib.php`; `enrol/locallib.php:404, :507`), and `cohort_get_available_cohorts()` (`cohort/lib.php:260`) can't be scoped to one organisation. That would need a `local_ltuse` page or web service listing only the manager's own organisation cohort members (B §5a). Mentor assignment needs the same; spec 003 R7 Phase B already does this.

## 4. Org-only courses (the exception)

- **Where the course lives:** the organisation's category `ltct:org:<key>` (FR-003; data-model.md:116).
- **Enrolment:** only that organisation's learner cohort, as Student. Separation comes from enrolment; groups play no part. For manager reach, either enrol the managers cohort as `orgmanager` (scoping is free because every learner belongs to that organisation), or have `local_ltuse` assign `orgmanager` in the category context from the managers cohort (B §4), which cascades to every course in it. Org-scoped enrolment for that organisation's managers could use cohorts created in the category context (B §5b). The narrowed profile hook applies here.
- **Declaring the host (conflict):**
  - The 002 area proposes a frontmatter key, `host_organisation: <key>`, validated by `check_course_package.py`.
  - The publisher area proposes a maintainer-only `moodle/site/` declaration (for example `org-courses.yaml: [{slug, organisation, why}]`). It follows the `course-discussions.yaml` precedent, which forbids putting sharing in frontmatter.
  - The second fits the content-versus-delivery-policy split better. Either way, one loader feeds the payload, drift and `check_moodle_payload.py`, and the key must exist in `organisations.yaml`.
- **Publisher:** resolve the category by idnumber with `core_course_get_categories` (criteria `idnumber`; verify on MOODLE_502_STABLE per Principle XI). Send `categoryid` on update too, so the placement is re-asserted, or refuse with a clear message when it differs. Keep `--category` only as an override. The forum scope comes from the same declaration. Related gap: pilot and published are one Moodle course (`08-publish.md:61-62`), and nothing ever moves a course from LTC Pilots.
- **Content:** it stays in the public repo and on its review site, and the course name is listed in a public category (spec.md:21, R6). "Only within their organisation" means enrolment only, not confidentiality. Real isolation is deferred to a separate Moodle (spec.md:95). Say this in FR-003.
- **Gaps that remain:** nothing stops the site team enrolling another organisation's cohort. Options are guidance in the site README or spec 008 tooling. `site_config` can't see enrolments, which are learner data.

## 5. Decisions for the maintainer

1. Shared-course group mode: 0, or 2 with organisation names as labels only. Should the publisher stop sending groupmode on update?
2. Should managers cohorts never be enrolled in shared courses? This is recommended. It costs `report/progress` and `report/completion` in shared courses, so FR-006's second sentence needs relaxing.
3. Is the per-organisation report enough, or should we build the user-context follow role (`tool_cohortroles` records kept in sync, or our own observers)?
4. How should the profile hook be narrowed: by course category, or by "the only path is an orgmanager enrolment"?
5. Should `orgmanager` lose `moodle/site:viewuseridentity`, or should a rule confine managers-cohort enrolments to `ltct:org:*` courses?
6. Org-only declaration: frontmatter or `moodle/site/`? Who approves an org-only course, and who enrols into it?
7. Should the publisher re-assert the category on every publish, including moving a course from Pilots to Published?
8. Spec 012: retire or invert `course-discussions.yaml`; replace the allocator with core random allocation within cohort groups; and set a rule for partner data in cross-organisation peer review (redaction instruction, or `**Review:** mentor` for sensitive briefs).
9. Who enrols SIL mentors into shared courses: the site team, or the spec 003 sync rather than organisation managers?
10. Should leaders be able to enrol their own people? That needs a `local_ltuse` org-scoped enrol page.
11. Amend the constitution (version bump) and append a 2026-10-02 INTENT decision.

## 6. Effects on spec 011 (events)

None of the area reports covered spec 011's files, so no 011 paths are cited here. What follows is drawn from the mechanisms above.

**Simpler:**
- A course event reaches everyone enrolled in a shared course: every organisation, and the SIL mentors.
- Group events and per-organisation copies of an event aren't needed in shared courses.
- A cross-organisation event no longer has to live in the spec 005 community course to get round the course walls.
- Nothing in 011 needs spec 002's "one organisation per group" invariant.

**Still needed:**
- **Org-only course events.** These are naturally limited to that organisation by enrolment.
- **Organisation-wide announcements for an organisation's people across courses.** These have no course home. Candidates:
  - the organisation's room in the spec 005 community space, which keeps its org groups (005 US3 and FR-007);
  - a category event in `ltct:org:<key>`, which reaches only people enrolled in that category's courses (confirmed in source, 011 research R2), so only learners in an org-only course, and which leaks through calendar export;
  - a message sent to the org cohort.
- **Manager-facing event attendance or follow-up.** This must be scoped by `ltct_org` or cohort, as in §3, never by course group.

## Conflicts between area reports

1. **Keeping org groups.** The 012 area says to keep organisation groups and course-level separate groups for manager scoping (`roles.yaml:105-114`), with activities overriding their own group mode. The 002, 004, publisher and governance areas say to set course groupmode 0 and move manager scoping to reports. The second is consistent with "no walls". The first leaves walls on Participants and the gradebook, and the publisher's forced groupmode 1 would keep re-applying them.
2. **Profile hook.** The 013/003 area says "keep it; it becomes primary manager scoping". The 002 area says it over-reaches and should be narrowed. Both agree it stays. They disagree on scope.
3. **Spec 004 scoping.** The 013/003 area says spec 004 "will need re-scoping by `ltct_org`". The 004 area shows it already scopes that way (`reports.yaml:40-48`). Only the in-course reports (R8) break.
4. **State of spec 003 code.** Resolved: `003-mentor-role` is committed at `0ae5942` with a clean working tree. Its `publish_moodle.py` also sends groupmode 1, so the groupmode change has to land in 003 as well, or merging 003 brings the wall back.
5. **Teacher/mentor scoping.** The 012 area keeps cohort groups so a mentor sees only their cohort. The 003 and governance areas say a mentor sees the whole course with no groups. That is fine in small cohorts, but it is a decision (item 1 or 8).
6. **Org-only declaration.** Frontmatter (002 area) or a `moodle/site/` file (publisher area). See decision 6.