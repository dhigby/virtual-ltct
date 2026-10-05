# Research: Partner organisations, cohorts and profiles

> **Amended 2026-10-03 in the spec** (Areas and Area Language Technology Coordinators, [Clarifications 2026-10-03](spec.md)). This file is not yet redone for it; that happens in the plan step, before any build. Where this file disagrees with the 2026-10-03 Clarifications, the spec wins.

**Plan**: [plan.md](plan.md). R1–R9 date from 2026-10-01; the 2026-10-02 amendment rewrites R2, R3, R7's known limit and R8 in place, amends R9 (its 2026-10-01 text is marked superseded where it no longer holds), and adds R10–R14. Every API below was confirmed in `MOODLE_502_STABLE` source on 2026-10-01 or 2026-10-02, after a Context7 query (`/websites/moodledev_io_5_2_apis`). The context7 guides cover contexts and enrolment, not cohort availability, profile fields or profile access, so source is the authority here. Each **Verify** line is something to confirm on the 5.2.3+ instance, with test accounts only, before the task that depends on it is closed.

## R1. Organisation cohorts are site-level and hidden

**Decision**: Every cohort this spec creates lives at system context with `visible = 0`. That covers each organisation's cohort (`ltct:org:<key>`) and its managers cohort (`ltct:org:<key>:managers`). No country cohorts are created (spec Clarifications 2026-10-01).

**Rationale**: `cohort_get_available_cohorts()` (`public/cohort/lib.php`) offers a course only the cohorts in that course's parent contexts. A cohort in organisation A's category could never be enrolled into a course in the shared curriculum category, and the shared curriculum is the point. A visible system cohort is offered to anyone with `enrol/cohort:config` in any course, so A's manager could pick B's cohort. A hidden one is offered only to someone with `moodle/cohort:view` at system context, which means the site team.

**Alternatives considered**: Category cohorts (cannot reach shared courses). Two cohorts per organisation, one in its category and one at system level (twice the rules). Visible system cohorts (cross-organisation enrolment).

**2026-10-02**: unchanged. Managers now enrol their own people (R10), but through our own page, which reads the hidden system cohort server-side. They never pick a cohort in core's form, so the cohorts stay hidden and at system level.

## R2. The manager role is kept only for organisation-only courses (amended 2026-10-02)

**Decision (2026-10-02)**: `orgmanager` stays, but it is used only in organisation-only courses (R11), with its capabilities unchanged, including `moodle/site:viewuseridentity`, so managers see their people's email. Managers see the email of every person in their organisation, protected people included; a mentor sees their mentee's; classmates never see a protected person's (Doug, 2026-10-02; spec 016 implements the protected case). There the site team enrols the organisation's managers cohort as `orgmanager` by cohort sync, as before, now with no group. Every learner in such a course belongs to that organisation, so the role's course-wide reach is already scoped. In shared courses the managers cohort is **not** enrolled at all (Clarifications 2026-10-02). With no groups, `orgmanager`'s `moodle/course:viewparticipants`, `report/progress:view`, `report/completion:view` and `moodle/site:viewuseridentity` would show every organisation's participants, completion and email addresses: core scopes the participants page and both reports by course group mode alone (`public/user/index.php:126-143`, `public/report/progress/index.php:99-110`, `public/report/completion/index.php:85-86`).

Managers reach their people outside course enrolment in three ways: spec 004's per-organisation report, the profile hook's own-organisation allow (R9), and the "my organisation" page with its management actions (R10). None of them is a role, so none needs keeping in step with the managers cohort.

`ORGMANAGER_DENY` in `scripts/site_config.py` otherwise stands. Management goes through our own pages (R10), never through a capability on `orgmanager`, so the deny list still holds: no enrolment, cohort, role-assignment or user-editing capability.

Shared courses are safe only while no managers cohort is synced into one. The inspector therefore gains a count-only check: any cohort-sync instance for an `ltct:org:%:managers` cohort in an `ltct:` course outside the `ltct:org:*` categories is reported as a blocking `[fail]`, without names. An enrolment instance is course configuration, not learner data, so `site_config` may read it.

The description and `why` of `orgmanager` and of the teacher role's `accessallgroups: inherit` in `roles.yaml` are reworded: neither scopes anyone in shared courses any more. `reports.yaml` drops its `group:name` columns from the `progress` and `pilots` reports, which would be empty (R3).

**Superseded (2026-10-01)**: the role arrived through cohort sync in every course an organisation was enrolled in, into the group named for the organisation, and was scoped by separate groups (R3).

**Rationale**: Every core way to let a manager enrol, assign to a cohort or edit a profile reaches the whole site. The cohort assignment selector (`cohort_candidate_selector`, `public/cohort/locallib.php`) and the manual enrolment selector (`public/enrol/locallib.php:507-523`) search every user. A locked profile field needs `moodle/user:update` at system context (`profile_field_base::edit_field_set_locked()`). With groups gone, following inside a shared course cannot be scoped either, so the role is confined to the courses where enrolment already scopes it.

**Alternatives considered**: A user-context "follow" role on each learner, kept in step with the managers cohort by `tool_cohortroles` or our own observers. It would add core's per-learner grade and outline views, but `tool_cohortroles` syncs hourly (`public/admin/tool/cohortroles/db/tasks.php`), so a removed manager would keep reach for up to an hour, against spec 016's "leaving ends the entitlement at once". Declined on 2026-10-02 (B3). Inverted separate groups (`accessallgroups` for every role except `orgmanager`): it keeps walls on reports, puts managers on every participants list, and gives learners a capability core treats as unrestricted. A `manager` archetype at category level, which brings course editing and every enrolment selector.

## R3. Shared courses are open: no groups (amended 2026-10-02)

**Decision (2026-10-02)**: Courses default to no groups (`moodlecourse/groupmode = 0`), not forced (`moodlecourse/groupmodeforce = 0`). The publisher sends `groupmode: 0` on create and on update. The applier gains a drift check that reports any `ltct:` course whose group mode is not 0, and `apply` sets it to 0, modelled on the existing `showreports` check. The course discussion forum is created and kept at no groups (`ensure_discussion`). No organisation groups are created, and the organisation groups already on the build host are removed (R13).

Confirmed in `MOODLE_502_STABLE` source:
- `core_course_update_courses` treats `groupmode` and `groupmodeforce` as optional, and needs only `moodle/course:update` (`public/course/externallib.php:1185-1186`, `:1240`). Create defaults both from `get_config('moodlecourse')` (`:970-973`).
- `update_course()` writes only the course record; an activity keeps its own group mode unless the course forces one (`public/course/classes/cm_info.php:1123-1131`, `public/lib/grouplib.php:743`). So a course or activity may still opt into groups for teaching reasons (FR-011).
- A forum left in separate groups after the groups are gone stops learners posting: in a group mode, a user in no group without `accessallgroups` cannot start a discussion (`public/mod/forum/lib.php:3594-3604`). So the forum's mode changes in the same change as the groups.

**Rationale**: Doug's decision B1. Visible groups with organisation names as labels (mode 2) were rejected because any course-context reader could page through every organisation's people by group, and because a group name on a forum post would show a protected person's organisation (spec 016).

**Verify** (blocks US2's checkpoint, SC-002). Two test organisations in one visible shared test course, two learners in each, a manager for each whose own `ltct_org` is `independent` and who is not enrolled in the course. One graded item with manual completion; every learner has a grade and a completion mark. Plus one organisation-only test course for A (R11).
- The shared course's group mode is 0 and it has no groups. Every learner's participants page lists all four learners, and each can open the others' profiles and post in the course forum.
- Neither manager is enrolled in the shared course, and `course/view.php` for it refuses them.
- As manager A, the "my organisation" page lists both A learners with their progress and no B learner; `user/profile.php?id=<A learner>` opens; `user/profile.php?id=<B learner>` is refused (R9).
- As manager A, every management action (R10) succeeds for an A learner and is refused, by the page and by an edited URL or form post, for a B learner, for a mentor, for another manager and for the site team.
- After the site team moves one A learner to B, A's manager can no longer open, list or manage them, B's manager can, and their grade and completion mark in the shared course are unchanged.
- After the site team removes A's manager from `ltct:org:A:managers`, the page, the profile and every action are refused to that person at once. Spec 004's report is refused within its audience cache's 30 minutes (`core/reportbuilder_allowed_reports`, 004 research), an accepted lag for read-only data.
- A learner a manager enrolled (R10) appears in that manager's organisation report and not in the site team's pilots report.
- After R13, re-run spec 004's fail-closed proof (MDL-84213) with cohort-sync instances at `customint2 = 0`; managers hold no `moodle/reportbuilder:edit`; the scheduled email and download respect the conditions.
- The organisation-only course sits in A's category, only A's learners and A's managers cohort are enrolled, and B's learner cannot open it.
- Through the mobile app web service: with a B learner's token, `core_user_get_course_user_profiles` for the shared course returns A learners (open course); with manager A's token it returns nothing for the shared course (not enrolled).

Repeat as manager B. Record the result for the delivering PR, outside the repo tree.

**Superseded (2026-10-01)**: separate groups by default, the publisher sending `groupmode: 1`, and the separation checks that went with them.

**Alternatives considered**: Visible groups with organisation labels (above). Not sending group mode on update, which would leave every course published before this change in separate groups until someone fixed it by hand; the drift check covers hand changes instead. Separate shared courses per organisation, which break the one-published-course model and the `idnumber` identity.

## R4. Cohorts fill themselves through `tool_dynamic_cohorts`

**Decision**: Pin `tool_dynamic_cohorts` (Catalyst IT, GPL, Moodle plugins directory). The applier creates one rule per organisation cohort (`ltct_org` equals the key). It calls the plugin's own `rule` and `condition` persistent classes and its `rule_manager`, and writes none of its tables. Managers cohorts get no rule: they are filled by hand (FR-012).

**Rationale**: Core 5.2 cannot fill a cohort from a profile field. Of the two maintained free plugins, `tool_dynamic_cohorts` is actively developed (last push 2026-09-29) and recommended by `local_profilecohort`'s own maintainers. Its rules are persistent classes, which are a public API, so declaring rules as code needs no direct table write. A cohort it manages has its `component` set, which also stops anyone editing that cohort's membership by hand.

**Risk**: Its `version.php` declares `supported = [404, 501]`, and issue "Request official Moodle 5.2 support" is open. Principle X says an unverified plugin is a research task, not an assumption.

**Verify** (blocks every rule task).
- **No test suite run (decided 2026-10-01).** The plugin's own PHPUnit suite is not run against 5.2. It cannot run on the shared host, which carries other people's live sites, and this team has no local Moodle or CI for it. The hand checks below are the evidence. The remaining risk is a 5.2 incompatibility the hand checks do not exercise. That is accepted for now, and a new pin is re-checked the same way.
- **Install on the instance.** Check free disk first, then install the pinned release into `/home/ltuse/moodle`.
- **Hand checks with `ltct-test-*` accounts.** Use a probe menu field `ltct_t001_probe` and probe cohorts `ltct:probe:*`:
  - a menu-field equality rule fills its cohort, both on the scheduled task and on `user_updated`;
  - changing the field moves the learner between cohorts;
  - a rule created through the persistent classes, outside the UI, behaves like one made in the form.

  Record the condition config keys those rules used.
- **Clean up.** Delete the probe field, cohorts, rules and accounts at the end, so later checks start clean. If the plugin fails, uninstall it with `admin/cli/uninstall_plugins.php` before switching to the fallback.

**Result (T001, 2026-10-01): passed. The fallback is not needed.**
- **Pin.** `$plugin->version` `2026031300`, the newest release in the Moodle plugins directory, maturity stable. Archive `https://marketplace.moodle.com/api/plugins/tool_dynamic_cohorts/versions/2026031300/download`. sha256 `c959d01def5d88b074a189ba8c423b054f7e4e97f2293d127a4ac901d76444b2`; the directory's md5 matched.
- **Install.** Installed into `/home/ltuse/moodle/public/admin/tool/dynamic_cohorts` with 23 GB free. The CLI upgrade succeeded on 5.2.3+ (`2026042003.03`) and set `tool_dynamic_cohorts/releasemembers = 0` and `tool_dynamic_cohorts/realtime = 1`.
- **Hand checks.** All 13 passed with probe items and two `ltct-test-probe*` accounts, all deleted afterwards:
  - a menu-equality rule filled its cohort on `user_updated` and through the scheduled task's `process_rule`;
  - changing the field moved the learner between cohorts;
  - every managed cohort got `component = tool_dynamic_cohorts`.
- **How the applier must write a rule.**
  - Call `rule_manager::process_form()` with `name`, `description`, `cohortid`, `bulkprocessing 0`, `operator 0`, `realtime 1`, `enabled 0`, `isconditionschanged 1` and a `conditionjson` list of one condition.
  - `process_form()` always saves a rule disabled. The applier then sets `enabled` to `1` and saves, after checking `is_broken()`, the way the plugin's own `toggle_status` web service does.
  - `process_form()` only accepts a cohort not yet managed by another component.
- **Two plugin settings the declaration must pin.** `releasemembers` must stay `0`: at `1`, unmanaging a cohort deletes all its members without events. `realtime` stays `1`, so membership follows a profile change at once.

**Fallback**: `local_profilecohort` (moodle-an-hochschulen), which has an official `MOODLE_502_STABLE` branch (`v5.2-r1`, `supported = [502, 502]`). It has no API for its rules. Using it means the applier writes its `local_profilecohort` table directly, listed in our README per Principle XI. If R4's verification fails, switch to the fallback and record why. The rest of this design does not change.

**Alternatives considered**: Our own observer and scheduled task in `local_ltuse`. It is about 100 lines and fully derived from the declaration, but the constitution puts a maintained plugin ahead of our own code.

## R5. Profile fields: three custom fields plus core country

**Decision**: One profile field category, "About your work", holding:

| Shortname | Type | Visible to | Locked | Values |
|---|---|---|---|---|
| `ltct_org` | menu | everyone (`PROFILE_VISIBLE_ALL`) | yes | the organisation keys, generated from `organisations.yaml` |
| `ltct_role` | menu | the learner and the site team (`PROFILE_VISIBLE_TEACHERS`) | no | a short declared list |
| `ltct_exp_<area>` | checkbox, one per area | everyone | no | the category names of `competencies.yaml`, except `Meta` (it holds only `Uncategorized`) |

Country is Moodle's own `country` field, which every account already has. Its visibility is core's `hiddenuserfields` setting, declared as empty in `settings/groups.yaml`, so country is visible to everyone (FR-008). Fields are created and updated with `profile_save_category()` and `profile_save_field()` (`public/user/profile/definelib.php`), and found by shortname. A shortname must match `[a-zA-Z0-9_]+` (`define_validate_common()`).

**Rationale**: A menu option is the value stored on the user. Storing the key, not the display name, means renaming an organisation changes no learner's data: the category and cohort names change, the value does not. A learner never picks it, because the field is locked. Core has no multi-select field type, so expertise is one checkbox per area. Using the framework's categories keeps the list reviewed and tied to CBC, and `validate` checks the names verbatim. A learner controls what is public by leaving the optional fields empty. Core has no per-learner visibility.

`PROFILE_VISIBLE_TEACHERS` covers US4-2. For a known user, `profile_field_base::is_visible()` shows it to the learner and to anyone with `moodle/user:viewalldetails`. `orgmanager` deliberately lacks that capability (R2), so organisation managers do not see `ltct_role`. "Staff" in US4-2 means the site team.

**Verify**: A test learner fills `ltct_role` and ticks one expertise checkbox. Viewed as another learner and as an organisation manager, `ltct_role` is hidden; viewed as the learner and as an administrator, it is shown. The organisation, country and expertise values are shown to all four.

**`ltct_role` options (maintainer, 2026-10-01)**: `LT Consultant`, `Mentor`, `Translator`, in that order.

**Alternatives considered**: Organisation as a text field (typos split cohorts). The display name as the stored value (a rename breaks every learner's value). A custom country menu (duplicates a core field).

## R6. Identity is the `idnumber`, and existing categories are adopted

> **Superseded in part, 2026-10-03.** `sil-partner` is no longer the one organisation for every SIL partner: SIL partners are declared Area by Area, and `sil-partner` stays only as a holding entry for people whose Area is not yet known (spec Clarifications 2026-10-03, FR-014).

**Decision**: Categories carry `idnumber` `ltct:<key>` for shared categories (`ltct:published`, `ltct:pilots`, `ltct:organisations`) and `ltct:org:<key>` for organisation categories. Cohorts use the idnumbers in R1. Organisation categories sit inside `ltct:organisations`, so the category list stays short. When no category has the idnumber, apply adopts a category with the declared name, the declared parent and an idnumber that is empty or does not start with `ltct:`, if there is exactly one: it sets the idnumber and reports `[changed] adopted`. More than one match is a blocking `[fail]`. The build host's existing "LTC Pilots" and "LTC Published" are adopted this way, so their ids, and the publisher's `--category`, do not change.

Categories are created visible, with Moodle's default permissions. A category page and its course names hold no personal data, and every course comes from the public repo, so they stay public (Clarifications 2026-10-01).

**First organisations (maintainer, 2026-10-01)**:

| Key | Display name |
|---|---|
| `sil` | SIL |
| `sil-partner` | SIL Partner |
| `seed-company` | Seed Company |
| `independent` | Independent |

`sil-partner` is deliberately one shared organisation for every SIL partner. So any SIL Partner manager follows every partner's learners. A partner that needs its own manager scope gets its own entry later, and its learners move by changing their `ltct_org`. `independent` is required by the spec.

**Rationale**: Spec 001's rule: identity lives in Moodle, never in a repo state file. Adopting by name only when the idnumber is empty or not `ltct:` can never take over a category another declaration owns. The build host's two categories had hand-set idnumbers, `ltc-pilots` and `ltc-published`, found at T008; nothing in the repo used them, and the adopt report names the old idnumber.

**Alternatives considered**:
- Creating new categories and moving courses, which changes course URLs and the publisher's ids for nothing.
- Matching by name alone, which breaks on the first rename.
- Hiding organisation categories. `core_course_category::hide()` also hides every course inside, so an organisation's own learners would lose their courses.
- Overriding `moodle/category:viewcourselist` per category. Declined on 2026-10-01, because course names are not personal data.

## R7. Removal and history

**Decision**: Nothing this spec creates is ever deleted by apply. Drift reports an `ltct:` category, cohort or rule, or an `ltct_` field or menu option, that is no longer declared as `extra`, which is non-blocking (FR-004). Cohort sync suspends and removes roles when a member leaves a cohort: `enrol_cohort/unenrolaction = 3` (`ENROL_EXT_REMOVED_SUSPENDNOROLES`), declared in `settings/groups.yaml`.

**What core does on leaving a cohort**, from `public/enrol/cohort/locallib.php`, `public/group/lib.php` and `public/user/lib.php`:
- With `2` (suspend) and with `3` (suspend, remove roles), the `user_enrolments` row stays, suspended. Grades and completion are kept (US3-2).
- `role_unassign_all()` runs only when the value is not `2`. With `2`, a manager removed from a managers cohort keeps `orgmanager` and its `moodle/user:viewdetails`.
- With both values, `groups_sync_with_enrolment()` keeps the person in the old group.
- `user_can_view_profile()` counts suspended enrolments and compares groups. So, with groups alone, the old organisation's manager can still open the profile of a learner who moved away.

So `3` closes the removed-manager case for a manager who is not also one of the organisation's learners. A manager who is also a learner keeps a learner's view, which the spec intends. R9 closes the moved-learner case for profiles.

**Known limit (2026-10-01), closed 2026-10-02.** With organisation groups, a moved learner stayed in their old group in a shared course, so the old organisation's manager still saw them on the participants list until the site team removed the stale enrolment by hand. Now there are no organisation groups and managers are not enrolled in shared courses, so the participants list is no longer a manager's view. A manager's view of a moved learner is the "my organisation" page and the profile hook, which both read the organisation of record and follow a move at once. The suspended old cohort-sync enrolment stays and keeps the learner's history; the learner is still active through their new organisation's enrolment.

**Rationale**: A deleted cohort or an unenrol (`0`) removes the learner's place in the course and its activity history. Suspending keeps it.

**Alternatives considered**: `2`, which leaves removed managers with their role. `0` (unenrol), which takes people out of the old group but loses history in a course they are not otherwise enrolled in. An observer in `local_ltuse` that unenrols the stale enrolment automatically, declined on 2026-10-01.

## R8. The site team's work, until spec 008 (amended 2026-10-02)

**Decision (2026-10-02)**: These recurring steps stay with the site team, all on learner data in Moodle:
- creating an account with its organisation field, through core CSV user upload (`profile_field_ltct_org`);
- enrolling a whole organisation in a shared course: one cohort-sync instance for the organisation's cohort, as Student, with no group. The managers cohort is **not** added;
- for an organisation-only course the maintainer has declared (R11): the organisation's cohort as Student and its managers cohort as `orgmanager`, both by cohort sync, with no group;
- adding a person to a managers cohort, or removing them;
- enrolling course leaders (teachers) in a course by hand, and adding mentors to `ltct:mentors`. A mentor follows a mentee through spec 003's user-context role and is not enrolled in their courses (003 R8); spec 012 decides whether course mentors are enrolled.

The 2026-10-01 step of unenrolling a moved learner's old enrolment in a shared course is gone (R7). Managers now do per-person enrolment, suspension, reset links and mentor assignment for their own learners (R10), so the site team does those only when asked.

Nothing stops the site team enrolling another organisation's cohort into an organisation-only course. `site_config` cannot see enrolments, which are learner data, so this is guidance in `moodle/site/README.md`, and spec 008 may add a check.

`moodle/site/README.md` documents each step. None of them is configuration, so none goes in the declaration (Principle III). The upload CSV holds real learners, so it is made and kept outside the repository folder and deleted after the upload. GitDoc commits and pushes anything left in the tree. A repo-wide `.gitignore` pattern is a backstop, not the rule.

**Rationale**: This is the burden Principle X says a spec must name. Spec 008 owns reducing it.

## R9. A profile hook scopes organisation managers to their own learners (amended 2026-10-02)

**Decision**: `local_ltuse` implements `local_ltuse_control_view_profile($user, $course, $usercontext)` in its `lib.php`. Core's `user_process_profile_callbacks()` calls it from `user_can_view_profile()` (`public/user/lib.php`), which both `user/profile.php` and `user/view.php` go through. It returns `core_user::VIEWPROFILE_PREVENT` only when all of these hold:
- the viewer is not the user being viewed;
- the viewer is a member of at least one `ltct:org:<key>:managers` cohort;
- the viewed user's `ltct_org`, read with `profile_user_record($user->id)`, is not one of those keys. An empty value counts too, unless the viewed user is staff: `has_coursecontact_role($user->id)` or `moodle/user:viewalldetails` at system context. Course teachers have no organisation, and the spec lets a manager see them;
- the viewer lacks `moodle/user:viewalldetails` in the viewed user's context. That is `$usercontext ?? context_user::instance($user->id)`, because core passes a null context from several callers. The site team has it at system level, and a spec 003 mentor would hold it in that user's context.

Otherwise it returns `core_user::VIEWPROFILE_DO_NOT_PREVENT`, so core's own checks decide. It never returns `VIEWPROFILE_FORCE_ALLOW`. *(Superseded 2026-10-02: it does, in one case, below.)*

The viewer's managed keys come from one read of `{cohort}` joined to `{cohort_members}`: `cm.userid` is the viewer, `c.contextid` is the system context, and `c.idnumber` is like `ltct:org:%:managers`, using `$DB->sql_like()`. `cohort_get_user_cohorts()` cannot be used, because it filters on `c.visible = 1` and every managers cohort is hidden (R1). `cohort.idnumber` is not indexed in core, so the README lists this read as a Principle XI exception. The keys are cached for the request.

`lib.php` checks the cheap cases first, viewing yourself and managing no organisation, so most viewers never reach the profile or capability reads. The decision itself is a pure function in `classes/profile_access.php`, tested without Moodle by a harness like `tests/report_harness.php`.

The hook runs only while `forceloginforprofiles` is on: `user_can_view_profile()` returns true before any callback when it is off. So `settings/groups.yaml` declares it as `1`, and drift reports it if it is turned off.

The decision's signature is `decide(bool $isself, array $managedkeys, string $viewedorg, bool $viewedisstaff, bool $viewerhasviewalldetails): int`, with the inputs in the data model's order. *(Superseded 2026-10-02: new inputs, below and in the data model.)*

A seconded learner has one organisation of record, so only that organisation's manager can open their profile (spec edge case).

**Rationale**: Core keeps a suspended enrolment and its group membership, so groups cannot tell a former member from a current one (R7). The organisation field can, because it is the source of truth for membership. The hook is a supported extension point (constitution XI, form 3), only ever takes access away *(superseded 2026-10-02: it also grants managers their own organisation's members, below)*, and touches no table.

Confirmed on `MOODLE_502_STABLE`: `user/view.php` calls `user_can_view_profile($user, $course, $usercontext)` at line 137, and `user/profile.php` calls `user_can_view_profile($user, null, $context)` at line 87.

**Verify**: After deploying, purge caches so `get_plugins_with_function()` finds the callback. Confirm `forceloginforprofiles` is `1`. Then run R3's moved-learner and removed-manager checks. As a manager, load the participants page, which reaches the hook with a null context. And open the test course teacher's profile, which an empty `ltct_org` must not block.

**Alternatives considered**: Unenrolling on leave (`0`), which loses history. Accepting the gap with a manual clean-up step, which loses the same history. Checking group membership in the hook, which cannot tell a suspended member from an active one without reading enrolment state for every course.

**Amended 2026-10-02.** Two things change once courses are open (Clarifications 2026-10-02):
- **The PREVENT is now a wall for a manager who is also a learner.** A manager enrolled as a student in a shared course could not open a classmate's profile from another organisation, which the spec now allows.
- **Managers can no longer reach their own people.** They are not enrolled in shared courses, so core's `user_can_view_profile()` refuses them every profile there: with a null course it checks `viewdetails` or `viewalldetails` in the learner's user context, then the courses the two share (`public/user/lib.php:1235-1290`). Spec 016's profile node (its surface 1) assumes they can.

**Decision (2026-10-02)**: `decide()` gains inputs and one outcome. In order:
1. Viewing yourself, or managing no organisation: `DO_NOT_PREVENT` (unchanged).
2. R10's `is_org_member_of_manager(V, P)` holds (the viewer manages the organisation the viewed person belongs to, whoever they are): `VIEWPROFILE_FORCE_ALLOW`. This is the one place the hook grants access, and it is the rule spec 016's profile node needs. The person's role does not narrow it: a mentor or manager of A is still A's member, and seeing a profile shows only what the profile shows.
3. The viewer has `viewalldetails` in the user's context, is their mentor, or **has a participant path to them**: `DO_NOT_PREVENT`, so core decides. A participant path is a course both are actively enrolled in where the viewer holds any role other than `orgmanager` (computed in `lib.php` with `enrol_get_shared_courses()` and `get_user_roles()`; `public/lib/enrollib.php:300`, `public/lib/accesslib.php:3097`).
4. Otherwise, for a managers-cohort member: `PREVENT`, as before, including an empty `ltct_org` for a non-staff person.

Scoping by course category was rejected because `user/profile.php` passes a null course (`public/user/profile.php:87`). `forceloginforprofiles` stays `1`: the callbacks run only when it is on.

Core's `user_process_profile_callbacks()` lets any PREVENT win over any FORCE_ALLOW (`public/user/lib.php:1315-1332`), so this hook's allow cannot override another plugin's refusal. Its allow reaches `user/profile.php` (null course), which is all spec 016 needs; `user/view.php` with a course still requires access to that course. The harness's sweep "never FORCE_ALLOW" becomes "FORCE_ALLOW only in case 2", with new cases for each branch above.

**Verify**: R3's moved-learner and removed-manager checks; a manager who is a student in the shared course opens a classmate's profile from the other organisation; a manager opens their own learner's profile, and a mentor's in their own organisation, while enrolled in nothing; a manager is refused the profile of another organisation's mentor unless core allows it.

## R10. Managers manage their own learners through our own pages (2026-10-02)

**Decision**: One shared check and one page. Every management surface calls `local_ltuse\organisation\access`, a pure class tested by `tests/org_access_harness.php`, as `profile_access` is.

**Two predicates, one class.**
- **`is_org_member_of_manager(V, P)`**: V is not P; P's `ltct_org`, read with `profile_user_record()`, is non-empty and is one of V's managed keys from `local_ltuse_managed_organisation_keys()`, the one existing read of `ltct:org:%:managers` membership (R9); and P is a member of the `ltct:org:<that key>` cohort, so the field and the cohort agree. It is read on every request, so leaving the managers cohort ends everything at once. It says nothing about P's role. R9's profile allow and the "my organisation" page's list use it, and it is the rule spec 016's entitlement needs; 016 is asked to call it rather than re-derive it, with its own `managers_see_identity` layer on top.
- **`may_manage_account(V, P)`**: the first predicate, and P is a **learner**: not deleted, not a site admin, not staff (`has_coursecontact_role($P)`, the same staff test as R9, or any role assignment at system context, or at any category context, checked with `get_user_roles()` for the system context and each category from `core_course_category::get_all()`, public APIs only), in no managers cohort and not in `ltct:mentors`. Every management action uses it. Staff, mentors and other managers are listed but are the site team's to manage.

The cohort-membership condition matters for spec 016: it applies an organisation's minimum protection when the person joins the member cohort, so a learner whose field is set but who has not yet joined cannot be enrolled anywhere by a manager before their protection is settled.

**Per-action rules**, each re-checked on the server for every request, with `require_login()`, a sesskey on every write, and the shared check:

| Action | Core calls (confirmed in `MOODLE_502_STABLE`) | Extra rule |
|---|---|---|
| See the person and their progress | `profile_user_record()`; course completion through `\completion_info` for each course P is enrolled in | `is_org_member_of_manager`. Shows each person's email, protected people's included (Doug, 2026-10-02). |
| Enrol | the course's **organisation-enrolment instance** (below), created on first use; `enrol_plugin::enrol_user($instance, $userid, $studentroleid)` (`public/lib/enrollib.php:2112`) | The course has an `ltct:` idnumber and sits in `ltct:published` or in `ltct:org:<P's ltct_org>`. Never `ltct:pilots`: pilot learners are the pilot coordinator's (stage 7). Role is always Student. |
| Unenrol | `enrol_plugin::unenrol_user($instance, $userid)` (`:2294`) | Only from the organisation-enrolment instance. An enrolment by cohort sync is the site team's and is shown as "enrolled with your organisation"; a manual (pilot) enrolment is never touched. If it was the learner's last enrolment in the course, their grades and group places are removed; activity and completion records stay. The page says so before confirming. |
| Send a reset link | `core_login_process_password_reset($user->username, '')` (`public/login/lib.php:84`) | Core applies every guard itself: enabled auth with `can_reset_password()`, `moodle/user:changeownpassword`, confirmed and not suspended, and reuse or expiry of a live reset within `$CFG->pwresettime` (`:139-163`). It emails only P's own address; V never sees the link. Its returned status is mapped to the page's message. Building it from `core_login_generate_password_reset()` would mean reading and writing `user_password_resets` ourselves, which has no public API. **Verify** it prints nothing when called outside the forgot-password page. |
| Suspend | `\core\session\manager::destroy_user_sessions($id)` (`public/lib/classes/session/manager.php:985`) then `user_update_user((object)['id' => $id, 'suspended' => 1], false)` (`public/user/lib.php:156`) | The same order and guards as `admin/user.php:127-138`. A minimal object, never a reloaded full record, so a concurrent spec 016 name change cannot be overwritten. Suspension applies to the whole site; the confirmation says so. |
| Reactivate | `user_update_user((object)['id' => $id, 'suspended' => 0], false)` | `may_manage_account`. A learner who has since moved organisation is the new organisation's. |
| Assign and end mentors | spec 003 R7 Phase B: `role_assign()` / `role_unassign()` of `mentor` in P's user context | Candidates come only from the hidden system cohort `ltct:mentors`, declared in `organisations.yaml` and filled by the site team. 003's contact observer then makes the mentor and learner contacts. Once spec 016 lands, assigning a mentor to a protected learner also needs 016's `can_view_identity(V, P)`, because a mentor sees the real identity (016 R7); otherwise the site team assigns. 016 adds that check to this page. |

None of these core functions checks a capability itself, so the shared check is the only gate. That is why it is one tested class. Every write fires core's own event (`user_enrolment_created`, `user_updated`, `role_assigned` and so on) with V as the actor, so the standard log records who did what. We add no event of our own.

**The organisation-enrolment instance.** Spec 004 tells delivery from pilots by enrolment method: its organisation and programme reports require `enrol:plugin = cohort`, its pilots report is `enrol:plugin = manual`, and `coverage.php` counts only cohort sync as delivery. A manager's enrolment through the manual instance would drop out of the manager's own report and show as a pilot. So managers enrol through a separate instance of core's self-enrolment plugin, `enrol_self`, which allows several instances per course: named "Organisation enrolment", with new self-enrolments off (`customint6 = 0`) so no learner can use it, a random enrolment key, and no welcome message. Our code creates it on first use with `enrol_get_plugin('self')->add_instance()` and finds it again by its `customchar1` marker. Spec 004 changes with it: its delivery condition becomes `enrol:plugin` not equal to `manual` (or `cohort` and `self` if the instance check shows the select condition can hold two values), and `coverage.php` counts both. **Verify** in source and on the instance, before the enrol task: `enrol_self` with `customint6 = 0` refuses a learner's self-enrolment and accepts `enrol_user()`; the condition fails closed (MDL-84213).

**When a learner moves organisation.** Their enrolments in shared courses stand: courses are open. An enrolment through the organisation-enrolment instance in a course in their *old* organisation's `ltct:org:*` category is suspended, as cohort sync would do, by the R12 observer on `cohort_member_removed` and by its reconcile task. That keeps an organisation-only course to its organisation (SC-002).

**Where it lives**: `local/ltuse/organisation.php`, linked from the user menu for managers-cohort members only (`extend_user_menu` hook, `public/user/classes/hook/extend_user_menu.php`, registered in `db/hooks.php`), and `local/ltuse/mentors.php?userid=` linked from each row. The Moodle app opens both in the browser; an app handler is not in this change.

**5.3 note**: `user_update_user()` is deprecated on `main` for 5.3 (MDL-82650) in favour of `\core\user::update_user()`. It is current in 5.2, and the call sits in one method. Spec 016 relies on the `before_user_updated` hook that `user_update_user()` dispatches, so moving both specs' user writes to the 5.3 API is one change, gated on 016's hook test passing on the new API (Principle XI).

**Alternatives considered**: Granting core capabilities to `orgmanager` at system or category context: every one reaches every user (R2). `tool_cohortroles` plus a user-context role: lags an hour and still cannot suspend or enrol (B3). Core's enrolment form: its selector searches every user (`public/enrol/locallib.php:507-523`).

**Verify** (blocks the page's tasks): R3's manager checks; a manager's edited form post naming another organisation's learner, a mentor, a manager, a course teacher with the manager's `ltct_org`, and the site admin is refused; a manager of two organisations cannot enrol one organisation's learner in the other's organisation-only course; a learner whose field is set but who is not yet in the cohort cannot be enrolled; a reset link reaches the test learner's own inbox and the manager's view shows no link; a suspended learner's open session ends; the log shows the manager as the actor for each write.

**Source check, 2026-10-02 (T039–T041, source half only; the instance half is still to run).** Read in `MOODLE_502_STABLE`, all under `public/`:
- *Several `enrol_self` instances per course: confirmed.* `enrol/self/lib.php:132-140` `can_add_instance()` checks only `moodle/course:enrolconfig` and `enrol/self:config`. Caveat: `find_instance()` (:1220-1232) "grabs the first available" self instance, so CSV upload resolves to whichever comes first.
- *`customint6 = 0` refuses self-enrolment: confirmed.* `is_self_enrol_available()` :325-328 returns `canntenrol`. `show_enrolme_link()` :117-123 and `enrol_page_hook()` :211-264 then show no button, but the enrol page still shows a "You cannot enrol" notice.
- *`enrol_plugin::enrol_user()` ignores `customint6` and `status`: confirmed* (`lib/enrollib.php:2112-2202`). Keep the instance enabled: under a disabled instance the enrolment is inactive.
- *Marker field: `customchar1`.* `enrol_self` uses `customint1`–`customint6` and `customtext1`, and no `customchar*`. `name` is not free: it is shown to learners as the instance title (:86-87, :885). `local_ltuse\organisation\access::ENROL_MARKER` is `ltct:orgenrol`.
- *Report builder enrolment-method condition (T040): one value only.* `enrol/classes/reportbuilder/local/entities/enrol.php:171-183` uses `filters\select`, whose operators are `ANY_VALUE = 0`, `EQUAL_TO = 1` and `NOT_EQUAL_TO = 2` (`reportbuilder/classes/local/filters/select.php:39-45, 157-168`). There is no IN, so spec 004's form is "`enrol:plugin` not equal to `manual`". Whether the same condition can be added twice, and the MDL-84213 fail-closed proof, are still to check on the instance.
- *`core_login_process_password_reset($username, '')` (T041): only returns, prints nothing* (`login/lib.php:84`, returns `[$status, $notice, $url]` at :215). It matches one account, `deleted = 0, suspended = 0`, local `mnethostid`. It needs `confirmed` (:129). It sends `send_password_change_info()` instead when the auth plugin cannot reset or the user lacks `moodle/user:changeownpassword` (:131-137). An expired request is replaced (:147-152). A live one is reused on the first re-request with the same token (:153-160), then `ALREADYSENT` sends nothing (:161-165). It throws `cannotmailconfirm` when sending fails. The `$status` strings to map are `emailresetconfirmsent`, `emailalreadysent`, `emailpasswordconfirmsent`, `emailpasswordconfirmnoemail`, `emailpasswordconfirmnotsent` and `emailpasswordconfirmmaybesent` (when `protectusernames` is on). *Mapping note (T071, 2026-10-04, from `login/lib.php:176-214`):* `emailresetconfirmsent` covers both a sent link and `send_password_change_info()`'s email; `emailpasswordconfirmsent`, despite its name, means nothing was sent because the account is unconfirmed; `emailpasswordconfirmnotsent` is also what a suspended account gets; and with `protectusernames` on, every outcome reads `emailpasswordconfirmmaybesent`. `actions::reset_outcome()` maps them so, and the page checks suspension itself before calling.

## R11. Organisation-only courses are declared in `moodle/site/` and placed by idnumber (2026-10-02)

**Decision**: A maintainer-only file, `moodle/site/org-courses.yaml`:

```yaml
rows: [8, 15]
org_only:
  - slug: <course slug>         # a course under modules/
    organisation: <org key>     # a key in organisations.yaml
    why: who approved it and why it is not shared
```

`site_config.load_org_courses()` replaces `load_discussions()` and reuses its checks: known keys, the REQUIREMENTS rows, the slug resolving to a course, no duplicates, a required `why`. It adds one: `organisation` is a declared key. `moodle_payload.py` puts `placement: {org_only, category_idnumber}` into the manifest, and `check_moodle_payload.py` checks its shape.

**Placement.** A new web service, `local_ltuse_place_course(courseidnumber, categoryidnumber)`, requires `local/ltuse:publish`, accepts only a target category whose idnumber is `ltct:org:<key>`, `ltct:pilots` or `ltct:published`, resolves it by a read of `course_categories.idnumber` (not indexed in core, so listed in the README as an XI exception), and, only when the course is elsewhere, moves it with `move_courses([$id], $catid)` (`public/course/lib.php:1548`), which fires `course_updated`. It returns whether it moved the course. The publisher calls it on every publish of an organisation-only course, so its placement is re-asserted each time. Shared courses keep `--category`; moving a course from Pilots to Published is out of scope here, left to spec 008 or a later change.

Core's own route needs more than the publisher should hold: `core_course_get_categories` by idnumber needs `moodle/category:manage` at system context (`public/course/externallib.php:1960-1972`, `RISK_XSS`), and `core_course_update_courses` checks `moodle/course:changecategory` in the course context only, never the target (`:1243-1246`). Our service checks the target itself.

**Drift**: reports, without fixing, a declared course outside its category and an undeclared `ltct:` course inside any `ltct:org:*` category. `apply` never moves a course, because a move changes which category roles it inherits.

**Enrolment**: by the site team (R8). Managers may also enrol their own learners individually (R10).

**Moving into a hidden category hides the course** (`move_courses`). Organisation categories are visible (R6), so this matters only if one is hidden by hand.

**Public**: the file names the host organisation. That is consistent with spec 016 R12's rule that an organisation that may need protection has a neutral key from its first commit, but 016 does not consider this file; it is asked to list it. `why` records only the approval ("approved by the maintainer, issue #N"), never the organisation's circumstances; the README says so, and the reason itself is kept privately.

**Alternatives considered**: A frontmatter key (`host_organisation:`): who may enrol a partner's people is delivery policy, not content (B4). Granting the publisher `category:manage`: far too broad.

**Verify**: a declared course is created in, and moved back to, its category; an undeclared course placed by hand in an organisation category shows in drift; the service refuses a category outside the three kinds.

## R12. Managers and their people become message contacts (2026-10-02)

**Decision**: Extend spec 003's contact pattern (`classes/observer.php`), which makes a mentor and learner contacts and records which contacts it made in `local_ltuse_mentor_contact`. A new table `local_ltuse_org_contact` (managerid, memberid, contactid, timecreated) records the contacts made for organisations.
- `\core\event\cohort_member_added` on `ltct:org:<key>` adds the new member as a contact of each member of `ltct:org:<key>:managers`; on the managers cohort, adds the new manager as a contact of each member. `cohort_member_removed` removes only the contacts this plugin made, and only when no remaining relationship (another managed organisation, or mentoring) links the pair.
- `\core_message\api::add_contact()` (`public/message/classes/api.php:2242`) needs no contact request and checks nothing, but throws on a duplicate, so `is_contact()` (`:2343`) is checked first, as 003 does. A contact the two people made themselves is never removed.
- A scheduled task, `reconcile_org_contacts`, runs hourly and repairs both ways, because some membership changes fire no event (below).

**Events fire for the paths we use**: `cohort_add_member()` and `cohort_remove_member()` fire them (`public/cohort/lib.php:203`, `:224`). `tool_dynamic_cohorts` uses them for rules without bulk processing (`classes/rule_manager.php:417`, `:421`); our rules set `bulkprocessing 0` (R4). Unmanaging a cohort with `releasemembers = 1` deletes members without events, which is why `releasemembers` stays `0` (R4) and why the reconcile task exists.

**Scale**: a manager of a large organisation gets one contact per person in it. That is Doug's choice (2026-10-02); the contacts list is the cost.

**A learner's block still wins**, as with mentors: core checks blocks before contacts.

**Verify**: adding a learner to an organisation adds the contact; moving them removes it; a contact the two made themselves survives; the reconcile task repairs a deleted contact row.

**Alternatives considered** (2026-10-02, Doug): messaging from the "my organisation" page through our own sender, which would bypass the learner's messaging privacy setting; no messaging, leaving managers to email. Both declined in favour of contacts.

**Source check, 2026-10-02 (T042, source half only; the instance half is still to run).**
- *Core fires the events:* `cohort/lib.php` `cohort_add_member()` :189-203 and `cohort_remove_member()` :218-224 trigger `cohort_member_added` / `cohort_member_removed`.
- *`tool_dynamic_cohorts` fires them only for non-bulk rules.* Its `classes/rule_manager.php` calls `cohort_add_member()` / `cohort_remove_member()` (:417, :421) for rules with `bulkprocessing = 0`, the default (`rule.php:59`). A bulk rule writes `cohort_members` directly (:381, :401). It fires the events only when `tool_dynamic_cohorts/bulkprocessingevents` is on, and that setting defaults to 0 (`settings.php:44-47`).
- *What R12 needs:* our four cohort rules must keep `bulkprocessing = 0`, or `settings/cohorts.yaml` must pin `bulkprocessingevents = 1`. Otherwise the contacts observer and the old-organisation suspension never run, and only the hourly reconcile task catches up.
- *Caveat:* this was read on the plugin's default branch, `MOODLE_404_STABLE` (version 2026031302). The pinned 2026031300 was not found by name, so recheck against the pinned archive.
- *Forum NOGROUPS shows stale-group posts: confirmed in source.* `mod/forum/lib.php:6790-6795` returns null, meaning no group filter, when the effective group mode is empty. `forum_user_can_see_group_discussion()` (:3744-3754) tests membership only under `SEPARATEGROUPS`.

## R13. Removing the organisation groups already on the build host (2026-10-02)

**Decision**: A one-off CLI, `local_ltuse/cli/open_courses.php`, with `--dry-run` (default) and `--execute`. For every course with an `ltct:` idnumber it does steps 1 and 2; for those outside the `ltct:org:*` categories it also does step 3:
1. sets each `enrol_cohort` instance's group to none, through `enrol_cohort_plugin::update_instance($instance, $data)` (`public/enrol/cohort/lib.php:147`) with `customint2 = 0` and the instance's own `roleid`, which then runs the cohort sync;
2. deletes each group whose idnumber is `ltct:org:<key>`, or that was named for an organisation, with `groups_delete_group()` (`public/group/lib.php:591`). That also deletes the group's calendar events, which matters for spec 011;
3. deletes the cohort-sync instance for any `ltct:org:<key>:managers` cohort, through `enrol_cohort_plugin::delete_instance()`, so managers leave shared courses.

It prints counts per step only, never names (Principle III), and is idempotent. Group mode and the forum mode are configuration and are fixed by `apply` (R3, R14), not by this CLI. Groups are learner data that `site_config` cannot see, which is why this is a CLI and not an apply step.

The build host holds only test accounts, so posts written under separation becoming readable across organisations is not a concern. Production starts open, with one gate: a person who asked for identity protection waits until spec 016 can set their level. Spec 008's intake enforces it row by row, holding that person's row as `waits`; nobody else is held back, and no organisation or Area entry is marked (Doug, 2026-10-05 (scope review), replacing the 2026-10-02 gate on whole organisations). `ltct_org` is visible to classmates and course leaders see email.

**Verify**: a dry run's counts match the build host; after `--execute`, R3's checks pass and a second run reports zero.

## R14. The course discussion forum runs with no groups, and `course-discussions.yaml` retires (2026-10-02)

**Decision**: `ensure_discussion`'s `wanted_groupmode()` returns `NOGROUPS` for every course. The `shared` parameter, `course-discussions.yaml`, `site_config.load_discussions()`, the payload's `discussion.shared` and their tests are removed. The applier keeps re-applying the forum mode, now NOGROUPS, so every existing forum changes on the next `apply`. The inspector's "all participants" warning and its forum vault count, which existed to catch cross-organisation exposure, are removed. Its "course forces a group mode" warning stays, reworded: a forced mode would wall the forum.

A NOGROUPS forum shows every post whatever its stored `groupid` (`public/mod/forum/lib.php:6792-6796`, inferred from source, to verify).

**Rationale**: With no groups there is nothing left to share or separate. Keeping the file inverted ("separated courses") would re-introduce a wall the decision rules out; an organisation that needs a private forum has an organisation-only course.

**Verify**: after `apply`, every `ltct:` course forum is NOGROUPS; learners from both test organisations post and read each other's posts.
