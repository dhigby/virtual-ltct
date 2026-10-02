# Research: Partner organisations, cohorts and profiles

**Plan**: [plan.md](plan.md). Every API below was confirmed in `MOODLE_502_STABLE` source on 2026-10-01, after a Context7 query (`/websites/moodledev_io_5_2_apis`). The context7 guides cover contexts and enrolment, not cohort availability, profile fields or profile access, so source is the authority here. Each **Verify** line is something to confirm on the 5.2.3+ instance, with test accounts only, before the task that depends on it is closed.

## R1. Organisation cohorts are site-level and hidden

**Decision**: Every cohort this spec creates lives at system context with `visible = 0`. That covers each organisation's cohort (`ltct:org:<key>`) and its managers cohort (`ltct:org:<key>:managers`). No country cohorts are created (spec Clarifications 2026-10-01).

**Rationale**: `cohort_get_available_cohorts()` (`public/cohort/lib.php`) offers a course only the cohorts in that course's parent contexts. A cohort in organisation A's category could never be enrolled into a course in the shared curriculum category, and the shared curriculum is the point. A visible system cohort is offered to anyone with `enrol/cohort:config` in any course, so A's manager could pick B's cohort. A hidden one is offered only to someone with `moodle/cohort:view` at system context, which means the site team.

**Alternatives considered**: Category cohorts (cannot reach shared courses). Two cohorts per organisation, one in its category and one at system level (twice the rules, only useful if managers enrol, which the 2026-10-01 clarification ruled out). Visible system cohorts (cross-organisation enrolment).

## R2. The manager role is follow-only and arrives through cohort sync

**Decision**: `orgmanager` is our role (`archetype: ""`), assignable at course context only. A person becomes organisation A's manager by being added to `ltct:org:<key>:managers` in Moodle (FR-012). In each course, the site team adds two cohort-sync instances for A. One enrols A's cohort as `student`, the other enrols A's managers cohort as `orgmanager`, and both put their people in the group named for A. The role holds:

| Capability | Why |
|---|---|
| `moodle/course:viewparticipants` | See the participants page, filtered to their group (R3). |
| `moodle/user:viewdetails` | Open a participant's profile. |
| `moodle/site:viewuseridentity` | See the identity fields the site shows, such as email, for their own people. |

It holds nothing else: no enrolment, cohort, role-assignment or user-editing capability, no `moodle/site:accessallgroups` and no `moodle/user:viewalldetails`. That last one is not limited by group, so it would show every learner's hidden fields site-wide. Because the archetype is empty, every capability is managed, and a hand grant shows up as drift. Nothing is `prohibit`, so a manager who is also a mentor (spec 003) keeps the mentor role's permissions. The one thing that reaches beyond the role is R9's profile hook, which limits whose profiles any member of a managers cohort can open. Grade and report capabilities belong to spec 004.

**Rationale**: Every core way to let a manager enrol, assign to a cohort or edit a profile reaches the whole site. The cohort assignment selector (`cohort_candidate_selector`, `public/cohort/locallib.php`) and the manual enrolment selector search every user. A locked profile field needs `moodle/user:update` at system context (`profile_field_base::edit_field_set_locked()`). Following inside a course is the part core can separate. Assigning the role through cohort sync makes "make X a manager" a single step: add them to one cohort. That step then works in every course the organisation is enrolled in, with no per-course role assignment.

**Alternatives considered**: Assigning the role at the organisation's category, which gives no reach into shared courses and adds a second step. Manager self-service through our own code in `local_ltuse`, which was declined on 2026-10-01 and may come later as its own row. A `manager` archetype at category level, which brings course editing and every enrolment selector. `moodle/course:viewhiddenuserfields`, which governs only the core fields in the `hiddenuserfields` setting, not custom profile fields, so it does nothing here.

## R3. Separation inside a shared course is separate groups

**Decision**: New courses default to separate groups (`moodlecourse/groupmode = 1`), not forced (`moodlecourse/groupmodeforce = 0`). `core_course_create_courses` takes both defaults from `get_config('moodlecourse')` (`public/course/externallib.php`), so the publisher inherits them. The publisher also sends `groupmode: 1` on update, so courses published before this spec change too. Group mode is not forced, so a discussion forum can still run without groups and spec 005's cross-organisation community is not blocked.

**Rationale**: With separate groups and no `accessallgroups`, the participants page, profile view and group-aware reports show only the viewer's groups. Groups alone leave one gap, people who leave an organisation, which R7 and R9 close.

**Verify** (blocks US2's checkpoint, SC-002). Use two test organisations in one visible test course, two learners in each and a manager for each. A manager's own `ltct_org` is `independent`, which is not enrolled in the test course, so they are not also a learner there. The course has one graded item with manual completion, and every learner has a grade and a completion mark before anything moves. A test teacher with an empty `ltct_org` is enrolled by hand as `editingteacher` in both organisation groups. As manager A:
- the test course page itself opens, so any refusal below comes from separation, not from course visibility;
- the participants page lists both A learners and no B learner;
- `user/view.php?id=<A learner>&course=<test course>` and `user/profile.php?id=<A learner>` open for each A learner;
- `user/view.php?id=<B learner>&course=<test course>` and `user/profile.php?id=<B learner>` are refused;
- the enrolment methods page and cohort pages are refused;
- `user/profile.php?id=<test teacher>` opens, which proves the staff exemption, because group separation cannot be what allows it;
- through the mobile app web service, with this manager's `moodle_mobile_app` token, `core_user_get_course_user_profiles` for the test course returns an A learner. It returns nothing for a B learner, for an `ltct-test-*` user outside the test course, or, after the move, for the moved learner. Before the move, `core_enrol_get_enrolled_users` for the test course returns only A's group. The app reaches profiles this way, so this is the path the field relies on;
- searching for a B learner's name in any selector or search A can reach finds nothing;
- after the site team moves one A learner to B, both profile URLs for that learner are refused to A's manager (R9), both open for B's manager, and the learner's A grade and completion mark are unchanged in the gradebook and the completion report;
- after the move, A's manager's participants page still lists the moved learner, which is the known limit (spec Clarifications). Then the site team unenrols the learner's suspended A cohort-sync enrolment by hand. The participants page no longer lists them, and their grade and completion mark are unchanged;
- after the site team removes A's manager from `ltct:org:A:managers`, both profile URLs for every A learner are refused to that person (R7);
- a person who is both an A learner and in A's managers cohort, once removed from the managers cohort, loses `orgmanager` and keeps exactly what an A learner sees. That is intended (spec edge case).

Repeat as manager B. Record the result for the delivering PR, outside the repo tree.

**Alternatives considered**: Forced separate groups, which split every forum by organisation. Separate shared courses per organisation, which break the one-published-course model and the `idnumber` identity.

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

**Known limit.** Take a course both organisations share. A moved learner holds an active B enrolment and a suspended A one, and stays in group A, because `groups_sync_with_enrolment()` re-adds an `enrol_cohort` membership while its enrolment exists. So A's manager still sees them on the participants list. The site team removes the stale enrolment by hand: `enrol_cohort_plugin::allow_unenrol_user()` allows it once the enrolment is suspended. `enrol_plugin::unenrol_user()` (`public/lib/enrollib.php`) then removes only that enrolment's group membership and role. It clears grades, completion and group data only when it was the learner's last enrolment in the course, which here it is not. Closing this automatically would need an observer in our own code, declined on 2026-10-01.

**Rationale**: A deleted cohort or an unenrol (`0`) removes the learner's place in the course and its activity history. Suspending keeps it.

**Alternatives considered**: `2`, which leaves removed managers with their role. `0` (unenrol), which takes people out of the old group but loses history in a course they are not otherwise enrolled in. An observer in `local_ltuse` that unenrols the stale enrolment automatically, declined on 2026-10-01.

## R8. The site team's work, until spec 008

**Decision**: Four recurring steps stay with the site team, all on learner data in Moodle:
- creating an account with its organisation field, through core CSV user upload (`profile_field_ltct_org`);
- adding an organisation's two cohort-sync instances and its group to a course;
- adding a person to a managers cohort, or removing them;
- after moving a learner between organisations, unenrolling their old, suspended cohort-sync enrolment in each course both organisations share (R7's known limit). Their history is kept, because the course enrolment continues.

Before adding an organisation to a course, the site team confirms the course is in separate groups. New courses default to it (R3), and a course published earlier gets it on its next publish. A course made by hand, or one not republished since, is set by hand.

`moodle/site/README.md` documents each step. None of them is configuration, so none goes in the declaration (Principle III). The upload CSV holds real learners, so it is made and kept outside the repository folder and deleted after the upload. GitDoc commits and pushes anything left in the tree. A repo-wide `.gitignore` pattern is a backstop, not the rule.

**Rationale**: This is the burden Principle X says a spec must name. Spec 008 owns reducing it.

## R9. A profile hook refuses organisation managers anyone outside their organisations

**Decision**: `local_ltuse` implements `local_ltuse_control_view_profile($user, $course, $usercontext)` in its `lib.php`. Core's `user_process_profile_callbacks()` calls it from `user_can_view_profile()` (`public/user/lib.php`), which both `user/profile.php` and `user/view.php` go through. It returns `core_user::VIEWPROFILE_PREVENT` only when all of these hold:
- the viewer is not the user being viewed;
- the viewer is a member of at least one `ltct:org:<key>:managers` cohort;
- the viewed user's `ltct_org`, read with `profile_user_record($user->id)`, is not one of those keys. An empty value counts too, unless the viewed user is staff: `has_coursecontact_role($user->id)` or `moodle/user:viewalldetails` at system context. Course teachers have no organisation, and the spec lets a manager see them;
- the viewer lacks `moodle/user:viewalldetails` in the viewed user's context. That is `$usercontext ?? context_user::instance($user->id)`, because core passes a null context from several callers. The site team has it at system level, and a spec 003 mentor would hold it in that user's context.

Otherwise it returns `core_user::VIEWPROFILE_DO_NOT_PREVENT`, so core's own checks decide. It never returns `VIEWPROFILE_FORCE_ALLOW`.

The viewer's managed keys come from one read of `{cohort}` joined to `{cohort_members}`: `cm.userid` is the viewer, `c.contextid` is the system context, and `c.idnumber` is like `ltct:org:%:managers`, using `$DB->sql_like()`. `cohort_get_user_cohorts()` cannot be used, because it filters on `c.visible = 1` and every managers cohort is hidden (R1). It is a read of stable core tables by indexed columns, and the README lists it. The keys are cached for the request.

`lib.php` checks the cheap cases first, viewing yourself and managing no organisation, so most viewers never reach the profile or capability reads. The decision itself is a pure function in `classes/profile_access.php`, tested without Moodle by a harness like `tests/report_harness.php`.

The hook runs only while `forceloginforprofiles` is on: `user_can_view_profile()` returns true before any callback when it is off. So `settings/groups.yaml` declares it as `1`, and drift reports it if it is turned off.

The decision's signature is `decide(bool $isself, array $managedkeys, string $viewedorg, bool $viewedisstaff, bool $viewerhasviewalldetails): int`, with the inputs in the data model's order.

A seconded learner has one organisation of record, so only that organisation's manager can open their profile (spec edge case).

**Rationale**: Core keeps a suspended enrolment and its group membership, so groups cannot tell a former member from a current one (R7). The organisation field can, because it is the source of truth for membership. The hook is a supported extension point (constitution XI, form 3), only ever takes access away, and touches no table.

Confirmed on `MOODLE_502_STABLE`: `user/view.php` calls `user_can_view_profile($user, $course, $usercontext)` at line 137, and `user/profile.php` calls `user_can_view_profile($user, null, $context)` at line 87.

**Verify**: After deploying, purge caches so `get_plugins_with_function()` finds the callback. Confirm `forceloginforprofiles` is `1`. Then run R3's moved-learner and removed-manager checks. As a manager, load the participants page, which reaches the hook with a null context. And open the test course teacher's profile, which an empty `ltct_org` must not block.

**Alternatives considered**: Unenrolling on leave (`0`), which loses history. Accepting the gap with a manual clean-up step, which loses the same history. Checking group membership in the hook, which cannot tell a suspended member from an active one without reading enrolment state for every course.
