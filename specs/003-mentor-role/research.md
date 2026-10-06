# Research: Mentor relationship and visibility

**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md) · **Date**: 2026-10-02

Every Moodle claim below was confirmed in upstream source on `MOODLE_502_STABLE` (files under
`public/`) and, for the app, `moodlehq/moodleapp` `main`, as Principle XI asks. Behaviour that
source cannot settle is listed under **Instance research tasks** at the end, to be verified on
the temporary 5.2.3+ instance before anything depends on it (Principle X).

## R1. The relationship is a core user-context role assignment

**Decision**: One role, `mentor`, with `contextlevels: [user]` and `archetype: ""`. A
relationship is one row in core's `role_assignments`: the mentor holds `mentor` in the
learner's user context. The row is manual (`component ''`), not owned by `local_ltuse`.

**Rationale**:
- It is the core "parent / mentor" pattern, so the relationship is plain core data. It moves
  with a data restore and is exportable through core privacy (II, III). FR-002 and FR-012 come
  for free.
- It is independent of enrolment. Every check that follows reads the learner's user context
  on each request, and a course context is never a child of a user context. So a course the
  learner joins after assignment is covered with no further step (FR-004, SC-004).
- Ending it is immediate. `role_unassign_all()` calls `mark_user_dirty()`, and
  `context::reload_if_dirty()` reloads the mentor's access on their next request
  (`lib/classes/context.php`). There is no session-length delay (FR-009).
- A user context's only children are that user's own blocks, so nothing the role grants
  reaches any other learner (FR-005, edge case "two mentors").

**Why manual and not component-owned**: a `local_ltuse`-owned assignment cannot be removed in
core's UI (`admin/roles/classes/existing_role_holders.php` disables rows whose component is not
empty). It cannot be removed through `core_role_unassign_roles` either, and plugin uninstall
leaves it orphaned (`adminlib.php::uninstall_plugin`). The audit trail comes from
`role_assignments.modifierid` and the `role_assigned`/`role_unassigned` events instead.

**Alternatives considered**: a course-level role in every course the learner takes. This
breaks FR-002 and FR-004, and spec 012's Course mentor already is that role (R8). A custom
relationship table in `local_ltuse`. This duplicates core and loses core privacy export.

## R2. What the role may hold

**Decision**: exactly three capabilities, all allow, all checked in the learner's user
context (four since spec 016; see the note under the table):

| Capability | What it unlocks | Why |
|---|---|---|
| `moodle/user:viewdetails` | the learner's profile, and the "parent" route in `user/view.php` | the mentor must open their learner |
| `moodle/user:viewuseractivitiesreport` | core Grades overview across all the learner's courses (`grade_report_overview::check_access`) | read-only, and the one cross-course page core gives a mentor |
| `local/ltuse:viewmenteeprogress` (new) | our Mentoring page (R3) | lets our page check the relationship by capability rather than by guessing |
| `local/ltuse:viewidentity` (spec 016) | a protected learner's real identity and the Protected marker, on the profile, the Mentoring page (web and app) and "People I support" | spec 016 FR-006 and R7 path 2. Added after this decision, see below |

**Widened by spec 016 (2026-10-05)**: the role now holds a fourth capability,
`local/ltuse:viewidentity`, so an assigned mentor sees their learner's real identity while
assigned (016 research R7 path 2). It is read only and checked in the learner's user context
like the other three, so FR-005 and FR-006 hold. It is a reviewed widening of the allowlist:
`MENTOR_ALLOW` in `scripts/site_config.py` (around :316-320) and the mentor declaration in
`moodle/site/roles.yaml` (around :208-212) both carry it with their reason.

**Left out, on purpose**:
- `moodle/user:editprofile`. It is a write capability (FR-006).
- `moodle/user:viewalldetails`, `viewhiddendetails` and `viewlastip`. These expose hidden
  profile fields, including teachers-only fields such as `ltct_role`, and IP addresses.
- `moodle/user:readuserposts` and `readuserblogs`. Mentors follow progress, not every post.
- Every `moodle/competency:*` rating or review capability. Rating a competency is the nearest
  thing in Moodle to awarding a level (FR-013). Spec 006 may add plan *viewing* later through
  the validator's allowlist (R11).
- Every course-context capability. The mentor needs none.

`site_config.py validate` enforces this as an **allowlist**, like `orgmanager`'s deny list.
`mentor` must have `contextlevels` of exactly `[user]`, an empty archetype, no `prohibit`, and
capabilities only from the list above. Widening it is a reviewed change to the validator.

**`showreports` stays 0.** With `viewuseractivitiesreport`, a course with `showreports = 1`
would also show the mentor the Outline report, the Complete report and the logs.
`report/outline` calls each module's `*_user_complete()`:
- assignment submissions show in full, with feedback;
- the logs show every event the learner triggered, with IP addresses.

No core capability keeps course completion while dropping those. The publisher already leaves
`showreports` at its default of 0. This spec declares `moodlecourse/showreports: 0` so that
`drift` catches anyone turning it on site-wide.

## R3. The progress view is our own page, because core's cannot meet SC-001

**Finding: what core gives a user-context mentor**
- **Browser**: the learner's profile lists their active courses (`enrol_get_all_users_courses`)
  plus Grades overview. That is all, and nothing lists the learner's *completion* across
  courses:
  - `report/completion/user.php` is the only per-course completion view. It needs
    `showreports = 1` (R2 rules that out), and nothing links to it.
  - `blocks/completionstatus/details.php` calls `require_login($course)`, which fails for a
    mentor who is not enrolled.
  - `block_mentees` lists names only.
- **App**: nothing. `block_mentees` has no app handler, so it renders nothing. Every web
  service the app would call refuses a mentor who is not enrolled:
  - `core_enrol_get_users_courses`, `core_completion_get_course_completion_status`,
    `core_completion_get_activities_completion_status` and `core_user_get_course_user_profiles`
    all go through `validate_context(course)`;
  - `gradereport_overview_get_course_grades` needs `moodle/grade:viewall` at system context.

SC-001 asks for one minute on a phone. Core meets neither the minute nor the phone.

**Decision**: add one page to `local_ltuse`, **Mentoring** (`local/ltuse/mentoring.php`). It
has two sections, and each appears only when it has entries:
- **Learners you mentor**: each learner, then their courses, each with a status (*Not
  started* · *In progress, N%* · *Completed on <date>* · *Completion not tracked*). Below
  that, links to the learner's profile, to core's Grades overview, and to Message.
- **Your mentors**: each mentor's name, with a Message link (FR-010).

The same data feeds a Moodle app site-plugin handler (R4), so both views come from one
function.

**APIs (public)**:
- `enrol_get_all_users_courses($userid, false)` lists the learner's courses, with suspended
  enrolments included. A finished course stays listed after its enrolment is suspended.
- `completion_info::is_enabled()`, `completion_info::is_course_complete($userid)` and
  `\core_completion\progress::get_course_progress_percentage($course, $userid)` give the status.
- A course whose enrolment was deleted outright but which has a `course_completions` row with
  `timecompleted` set is still shown as completed (FR-004, scenario "finished months ago").
  This is a raw read of a stable core table by its indexed `userid`, and it is listed in the
  plugin README.
- The mentor's learners come from `role_assignments` joined to `context` at
  `CONTEXT_USER`, by indexed `userid`, as `block_mentees` does. Every learner is then
  rechecked with
  `has_capability('local/ltuse:viewmenteeprogress', context_user::instance($learnerid))`
  before anything is shown. The query only finds candidates; the capability decides.
- A learner's mentors come from `get_role_users($mentorroleid, context_user::instance($me))`.

**What it never shows**: quiz attempts or answers, submission content, logs, grades beyond
core's overview link, or any profile field `viewdetails` does not already allow.

**Navigation**: the page is linked from the primary navigation only for someone who has a
mentor or a learner. A user with neither never sees it. The hook to use
(`\core\hook\navigation\primary_extend`) is an instance research task.

**Relation to spec 004**: this is the scoped view FR-003 asks for. Spec 004's consolidated
reporting (filters, exports, organisation roll-ups) can build on the same function, or replace
the browser page with a Report builder source, without changing the relationship.

**Alternatives considered**:
- **`block_mentees` plus profile links**: names only, and nothing in the app.
- **Turning on `showreports` in published courses**: this exposes assignment submissions and
  logs (R2).
- **Granting `report/progress:view` or `moodle/grade:viewall`**: these are course or system
  capabilities, so they would reach every learner.

## R4. The Moodle app gets the Mentoring page as a site plugin

**Decision**: `local_ltuse/db/mobile.php` declares one handler with the
`CoreMainMenuDelegate` delegate, titled "Mentoring". Its method,
`\local_ltuse\output\mobile::mentoring_view()`, returns a template rendered from the same data
as the browser page. Site plugins are a feature of the free app, not of a paid plan, so
Principle IX holds. The handler is offered only to users with at least one mentor or learner.
If that per-user restriction cannot be expressed, the page shows "Nobody is linked to you
yet."

**Rationale**: it is the only route by which a mentor sees progress in the app (R3), and it
uses a supported extension point (Principle XI).

**Verification needed** (instance research task 2): the handler shows in the Android app's
main menu, renders offline from cache, and opens on a phone within SC-001's minute.

## R5. Messaging: mentor and learner become message contacts

**Finding**: configuration alone cannot meet FR-007. `\core_message\api::can_send_message()` →
`can_contact_user()` (`message/classes/api.php`) checks `moodle/site:messageanyuser` only at
**system** context and in shared **course** contexts, never in the recipient's user context.
Granting it to `mentor` therefore does nothing. Granting it at system context, or turning on
`messagingallusers`, would let mentors message the whole site and undo row #19's setting
(`messagingallusers: 0`, `settings/messaging.yaml`). Contacts, however, pass both remaining
learner privacy choices (COURSEMEMBER and ONLYCONTACTS), and the app's messaging uses the same
check.

**Decision**: an event observer in `local_ltuse`:
- **On `\core\event\role_assigned`** for the `mentor` role in a user context: if the learner
  and mentor are different users and not already contacts, call
  `\core_message\api::add_contact($mentorid, $learnerid)`. No request is sent and no
  notification goes out. Record the pair in `local_ltuse_mentor_contact`.
- **On `\core\event\role_unassigned`**: if the plugin made that contact and no other `mentor`
  assignment still links the pair, call `remove_contact()` and delete the record. A contact
  the two made themselves, before or during the relationship, is left alone.
- **On `\core\event\user_deleted`**: delete that user's records. Deleting a learner removes
  their user-context assignments with no `role_unassigned` event
  (`context::delete_content()`), so this is the only clean-up path.
- **Existing assignments**: a CLI script, `cli/mentor_contacts.php --sync`, creates missing
  contacts for assignments made before the plugin version that adds the observer. It is safe
  to rerun.

**Boundaries, and how the spec is read**:
- **A learner's block still wins.** Core checks the block before the contact, in the browser
  and the app. FR-007's "regardless of the learner's contact restrictions" is read as their
  privacy *preference*, not a block of one named person. A block is a safeguarding choice,
  and overriding it would need `messageanyuser` at system context. This reading is recorded
  in the plan for the maintainer and should be written into FR-007 when the spec is next
  touched.
- **A learner who deletes the contact has made the same kind of choice**, and the plugin does
  not re-add it. The relationship and the progress view are unaffected.
- **After a relationship ends**, the two can message only as core allows: as course members
  if they share a course, or as contacts they made themselves.

**Alternatives considered**: contact *requests* (`create_contact_request`). These need the
learner to accept and send a notification. `can_create_contact()` also refuses them without a
shared course when `messagingallusers` is off, which is exactly our case.

## R6. The site team assigns with core's own page

**Decision**: the site team (role `manager` at system context) and admins assign, reassign and
end relationships in core:
1. Open the learner's profile.
2. Go to Preferences.
3. Under Roles, choose "Assign roles relative to this user". This runs `admin/roles/assign.php`
   in the learner's user context.
4. Choose Mentor.
5. Search for the mentor and click Add. To end a relationship, select the mentor and click
   Remove. To reassign, remove one mentor and add another.

For this to work:
- `get_assignable_roles()` needs a `role_allow_assign` row from a role the assigner holds. So
  `roles.yaml` gains an `allowassign` key, with `manager: allowassign: [mentor]`. Admins need
  no row.
- `site_config.py` validates the key, `apply` writes it with `core_role_set_assign_allowed()`,
  and `drift` reads `role_allow_assign`.
- The key is **additive**: declared pairs must exist, and undeclared pairs are not reported,
  because the archetypes already carry many default pairs that this repo does not manage.

**Ending all of one mentor's relationships** (edge case "a mentor leaves"): core has no single
button. `cli/mentor_contacts.php --end-all --mentor=<username>` calls
`role_unassign_all(['userid' => …, 'roleid' => mentor])`. That fires the unassign events, so
R5 removes the contacts too. A button for this is spec 008's (bulk tooling).
*(Delivered, noted 2026-10-05: spec 008 shipped it as `ltct_admin.py mentors end --mentor E`
(`scripts/ltct_admin.py:702`, `cmd_mentors_end`), a CLI, not a button.)*

**Scripting**: `core_role_assign_roles` and `core_role_unassign_roles` accept
`contextlevel: user` with `instanceid: <learner id>`. Spec 008's bulk assignment can use them
from a system-level account.

## R7. Organisation managers: a gated addition (Phase B)

**Finding**: core cannot scope assignment in a user context to one organisation. A user
context's parent is the system context, so no category or cohort role reaches it.
`moodle/role:assign` held at system context reaches every user, and `orgmanager` exists only
in course contexts (spec 002 R2).

**Conflict, raised and not routed around**: spec 002 recorded on 2026-10-01 that organisation
managers follow and do not act ("self-service through our own code ... declined; may come later
as its own row", `INTENT.md` Decisions and Open questions). FR-008 of this spec asks for
exactly that kind of self-service for mentor assignment. The plan therefore puts it in
**Phase B**, built only after the maintainer records a decision.

**Recommended design, if approved**: one page, `local/ltuse/mentors.php?userid=<learner>`,
linked from the learner's profile.
- **What it shows and does**: the learner's current mentors, a Remove button for each, and an
  Add picker. Writes go through `role_assign()` and `role_unassign()`.
- **Authorisation**, server-side on every request, as a pure decision class tested like
  `profile_access`:
  - Require login, and a sesskey on writes. The learner must exist, not be deleted and not be
    the viewer.
  - **Allow** if the viewer has `moodle/role:assign` in the learner's context and `mentor` is
    assignable there. This covers the site team.
  - **Otherwise allow** only if the learner's `ltct_org` is one of the viewer's managed
    organisations. That uses `local_ltuse_managed_organisation_keys()`, the same source of
    truth as the spec 002 hook.
  - Otherwise refuse.
- **Picker**: candidates come only from a hidden cohort `ltct:mentors`, which the site team
  fills. A site-wide user search would show every user's name to any manager. Mentors may
  come from any organisation (spec Assumptions; US3-4).

**If declined**: FR-008's manager part and SC-003 move to spec 008 alongside managers enrolling
their own learners. Phase A still delivers stories 1, 2 and 4, with the site team assigning.

**Decided, 2026-10-02**: approved, as part of "Courses are open across organisations" (`INTENT.md`
Decisions): managers assign and end mentors for their own people through our own pages. Spec
002 research R10 narrows the manager branch above: a manager acts only for a **learner** of
their organisation, through the shared `organisation\access::may_manage_account()`, and staff,
mentors and other managers stay with the site team. The page follows R10 (contract
`local-ltuse.md`, Phase B). It manages the default mentor only; a one-course mentor
(`INTENT.md`, 2026-10-04) is spec 008's.

## R8. Feedback on learner work comes through spec 012's Course mentor (story 4)

**Decision**: this spec does not enrol a learner's mentor into their courses.
- FR-011 is met by spec 012's course-level **Course mentor** role (`teacher`, without
  `accessallgroups`), which reads and gives feedback on submissions in its own groups.
- ~~The site team enrols a mentor as Course mentor with the organisation's group, as 012
  describes.~~
- ~~Automatic syncing of a learner's mentor into their courses is spec 008's.~~

**Superseded (2026-10-05)**: spec 008's course-mentor sync (008 research R10) does this, on
since 2026-10-05 (`local_ltuse/coursementorsync: 1` in `moodle/site/settings/admin.yaml`, #97).
Each learner's course mentor in a course is, by default, their mentor here; a one-course or
cohort mentor replaces them in that course (008 plan decision 2). The sync enrols the course
mentor as `teacher` through the course's `ltct:coursementor` enrolment instance, puts them and
the learners they assess into their own "Mentor group `<n>`" (idnumber
`ltct:mentorgroup:<mentor id>`, 008 plan decision 3), and removes them when the reason ends
(`moodle/local_ltuse/classes/admin/course_mentor_sync.php`). The course stays in group mode 0
with no organisation groups (spec 002, open courses), so there is no "organisation's group" to
enrol into and nobody enrols course mentors by hand. Only learners actively enrolled as Student
through cohort sync or the Organisation enrolment count; a pilot learner (manual enrolment)
gets no course mentor.

A course mentor holds Teacher, whose Moodle 5.2 defaults reach every learner in a group-mode-0
course, not only those they assess. Accepted (Doug, 2026-10-05): we trust people who are in
the system. A protected learner's real identity still reaches a course mentor only through a
shared mentor group (spec 016 R7 path 4). See spec FR-005's note.

**Rationale**:
- The user-context role cannot reach course activities. Letting it read submissions would
  mean `showreports = 1`, which R2 rules out.
- Automatic course enrolment from a user-context relationship is a new recurring
  synchronisation with its own failure modes. FR-006 of the spec already defers it to "this
  spec's plan or spec 008", and this plan chooses 008.

**Correction for spec 012**: 012's plan says "until then the organisation manager enrols the
mentor with the cohort". Under spec 002's decision the site team enrols, not the manager. The
delivering PR fixes that sentence. *(Itself superseded 2026-10-05: neither enrols by hand now;
spec 008's sync does, as above. Spec 012 is parked for re-plan (Doug, 2026-10-05), and its
plan marks that sentence superseded.)*

## R9. Spec 002's profile hook must exempt mentors by the new capability

**Finding**: `local_ltuse_control_view_profile()` exempts a viewer holding
`moodle/user:viewalldetails` in the learner's context. Spec 002 assumed the mentor would hold
it. R2 leaves it out, so a mentor who is *also* a manager of a different organisation would be
refused their own learner's profile.

**Decision**: `profile_access::decide()` gains a sixth input, `$viewerismentor`, from
`has_capability('local/ltuse:viewmenteeprogress', $usercontext)`. The hook still only ever
takes access away.

**Testing**: `tests/profile_access_harness.php` gains the case "manager of A, mentor of a
learner in B".

## R10. A mentor who is also a learner, or also a manager

Roles are additive and nothing is prohibited:
- `mentor` grants nothing in course contexts, so a mentor's own courses and progress are
  unchanged (edge case "a mentor is also a learner").
- `orgmanager` uses no `prohibit` (spec 002), so the two roles combine.
- The Mentoring page shows the "Your mentors" and "Learners you mentor" sections side by side.

## R11. CBC fidelity and competency plans

**Decision**: nothing in this spec displays, records or awards a CBC level (FR-013).
- The page shows course completion only. Core completion is training evidence (constitution V).
- The allowlist (R2) excludes every `moodle/competency:*` capability.
- If spec 006 adopts core learning plans, it adds `moodle/competency:planview` (read) to the
  allowlist in its own change. It must not add `planreview`, `planmanage` or
  `usercompetencyrate`, since these change records.

## Instance research tasks

Verify these on the temporary 5.2.3+ instance with test accounts before the dependent task is
closed:

1. **R1/R6**: the role is applied, appears under the learner's "Assign roles relative to this
   user" for a system `manager`, and assignment and removal take effect on the mentor's next
   page load.
2. **R4**: the `CoreMainMenuDelegate` handler shows in the Android app for a mentor and for a
   learner with a mentor. It renders, works offline from cache, and can be hidden from users
   with neither.
3. **R3**: the hook `\core\hook\navigation\primary_extend` exists on `MOODLE_502_STABLE` and
   adds a per-user primary navigation item. If not, use `local_ltuse_extend_navigation()`
   with a user-menu item.
4. **R5**: with no shared course and the learner on ONLYCONTACTS, both directions deliver in
   the browser and the app. A learner's block stops the mentor. Ending the relationship
   removes the plugin's contact.
5. **R3**: a course finished and then unenrolled still shows as completed, and a course
   joined after assignment shows with no action (SC-004).
6. **R9**: a mentor who manages another organisation opens their learner's profile.

## Source results (2026-10-02, tasks T001–T003)

Read in upstream source on `MOODLE_502_STABLE` (files under `public/`) and in the Moodle app developer docs (`moodle/devdocs` `main`, `general/app/development/plugins-development-guide/`).

- **T001 — primary navigation hook: exists.** `\core\hook\navigation\primary_extend` (`lib/classes/hook/navigation/primary_extend.php`) carries `public readonly primary $primaryview` and `get_primaryview(): primary`. `\core\navigation\views\primary::initialise()` dispatches it after core's own nodes and before `set_active_node()` (`lib/classes/navigation/views/primary.php:92`). A plugin registers it in `db/hooks.php` as `['hook' => primary_extend::class, 'callback' => <class>::class . '::<method>']`, the shape `lib/db/hooks.php` uses, and adds a node with `$primaryview->add($text, $url, navigation_node::TYPE_CUSTOM, null, $key)`, as `initialise()` does for its own. The fallback is not needed.
- **T002 — app main-menu handler.** `CoreMainMenuDelegate` options: `displaydata.title` (a string id, listed in `mobile.php`'s `lang`), `displaydata.icon` (required), `displaydata.class`, `priority`, `ptrenabled`. Main-menu items always show under the More menu. A method's response is `templates` (`[{id, html}]`, Ionic markup, not Bootstrap), `javascript`, `otherdata` (no nested arrays) and `files`. **Per-user hiding** uses an `init` method on the handler: its response may carry `disabled: true`, or `restrict.users`, and the app then hides the handler. So the contract's handler gains `'init' => 'mentoring_init'`, which returns `disabled` for a user with no relationship.
- **T003 — setting and signatures.**
  - "Show activity reports" is `moodlecourse/showreports`, an `admin_setting_configselect` defaulting to 0 (`admin/settings/courses.php:204`). It is stored in `config_plugins` with plugin `moodlecourse`, name `showreports`, as the contract assumed.
  - `core_role_set_assign_allowed($fromroleid, $targetroleid)` (`lib/accesslib.php:3199`); `get_assignable_roles(context $context, $rolenamedisplay = ROLENAME_ALIAS, $withusercounts = false, $user = null)` (:3252); `role_unassign_all(array $params, $subcontexts = false, $includemanual = false)` (:1720). Its params are matched directly against `role_assignments`, so `['userid' => …, 'roleid' => …]` with no `component` key removes manual assignments too, and fires `role_unassigned` for each. `get_role_users($roleid, context $context, $parent = false, $fields = '', …)` (:4062); `user_has_role_assignment($userid, $roleid, $contextid = 0)` (:4513).
  - `\core_message\api::add_contact(int $userid, int $contactid)` (`message/classes/api.php:2242`), `remove_contact(int $userid, int $contactid)` (:2268), `is_contact(int $userid, int $contactid): bool` (:2343), `is_blocked(int $userid, int $blockeduserid): bool` (:2377).
  - `\core_completion\progress::get_course_progress_percentage($course, $userid = 0)` (`completion/classes/progress.php:48`).
  - `enrol_get_all_users_courses($userid, $onlyactive = false, $fields = null, $sort = null)` (`lib/enrollib.php:1061`).
  - `local_<plugin>_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course)` matches `core_myprofile_navigation` (`lib/myprofilelib.php:35`).
