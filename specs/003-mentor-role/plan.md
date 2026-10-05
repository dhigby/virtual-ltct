# Implementation Plan: Mentor relationship and visibility

**Branch**: `003-mentor-role` | **Date**: 2026-10-02 | **Spec**: [spec.md](spec.md)

## Summary

A mentor relationship is a **core user-context role assignment**: one declared role, `mentor`,
held by the mentor in the learner's own user context. It is plain core data and lives only in
Moodle (FR-012). It is independent of every enrolment, so courses the learner joins later are
covered with no further step. Ending it takes effect on the mentor's next page load (research
R1).

Core grants the role very little, and that is deliberate. It holds three read capabilities, and
`site_config.py validate` enforces them as an allowlist (R2). *(Since spec 016 it holds a
fourth, `local/ltuse:viewidentity`, so an assigned mentor sees a protected learner's real
identity (016 R7 path 2); a reviewed widening, R2.)* Core's own mentor pages cannot meet
SC-001 for three reasons:
- they show completion one course at a time, behind a link nothing in the interface offers;
- they show nothing at all in the Moodle app;
- opening them (`showreports = 1`) would also show the mentor every assignment submission and
  the learner's logs.

So `local_ltuse` gains one page, **Mentoring**, that lists each of the mentor's learners with
their courses and completion status. The same data is served to the Moodle app through a
site-plugin main-menu handler (R3, R4). The page also lists a learner's own mentors (FR-010).

Messaging needs code too. Core never checks a capability in the recipient's user context when
deciding who may send a message, so an observer makes mentor and learner **message contacts**
when the relationship starts and removes the contact it made when the relationship ends. A
learner's block still wins (R5).

The site team assigns and ends relationships on core's own "Assign roles relative to this user"
page, once `roles.yaml` declares that `manager` may assign `mentor` (R6). **Organisation
managers assigning mentors (FR-008's second half) is Phase B.** ~~It is gated on a maintainer
decision, because spec 002 declined manager self-service on 2026-10-01 (R7).~~ *(Updated
2026-10-05: approved 2026-10-02 (R7) and built, narrowed to an organisation's learners by spec
002 R10 (T027, T031). The site team also assigns in bulk with spec 008's
`ltct_admin.py mentors assign` and ends with `mentors end`.)*

Story 4 (feedback on work) is delivered by spec 012's course-level Course mentor role. ~~Syncing a
learner's mentor into their courses is deferred to spec 008 (R8).~~ *(Updated 2026-10-05: spec
008 delivered the sync (008 research R10), on since 2026-10-05 (#97). A learner's mentor is, by
default, their course mentor in each course they take, enrolled as `teacher` in their own
"Mentor group `<n>`" (`ltct:mentorgroup:<mentor id>`) and removed when the reason ends; R8.)*

## Technical Context

**Language/Version**: Python 3 (repo scripts; CI runs 3.12). PHP 8.2+ for `local_ltuse`.

**Primary Dependencies**:
- **Moodle 5.2.3+ core**:
  - roles: `role_assign`, `role_unassign_all`, `get_role_users`, `core_role_set_assign_allowed`
  - messaging: `\core_message\api::add_contact`, `remove_contact`, `is_contact`
  - completion: `completion_info`, `\core_completion\progress`
  - courses: `enrol_get_all_users_courses`
  - the profile callbacks `myprofile_navigation` and `control_view_profile`
  - events, the privacy API, and Moodle app site plugins (`db/mobile.php`)
- **Python**: the existing `pyyaml` and `requests`
- **No third-party plugin**

**Storage**:
- **Repo**: declarations in `moodle/site/`.
- **Moodle**: relationships (core `role_assignments`), contacts (core `message_contacts`), and
  one plugin table, `local_ltuse_mentor_contact`.

**Testing**:
- `pytest` for `site_config.py` validation
- PHP harnesses for the three pure decisions (`profile_access`, `progress_status`,
  `mentor_admin`), run like the existing `profile_access_harness.php`
- [quickstart.md](quickstart.md) A1–A17 and B1–B5 on the temporary instance with test accounts

**Target Platform**: self-hosted open-source Moodle LMS 5.2.3+, and the free Moodle Android app.

**Project Type**: repo tooling (the declarative site config) plus our Moodle plugin.

**Performance Goals**: the Mentoring page renders in under 2 s for a mentor with 25 learners of
10 courses each. That is one candidate query, then per-learner capability and completion
reads, all core-cached.

**Constraints**:
- no learner data in the repo
- no vendored edits
- core first
- `messagingallusers` stays 0 (row #19)
- `showreports` stays 0
- works in the app

**Scale/Scope**: hundreds of learners, tens of mentors, 1–3 mentors per learner, one site.

No NEEDS CLARIFICATION remains. Six behaviours are open instance research tasks, listed at the
end of [research.md](research.md), not assumed.

## Constitution Check

*Gate before Phase 0, re-checked after Phase 1 design.*

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. The role, its allow-assign pair and `showreports` are declared and applied, and drift reports hand changes. Nothing reads relationships back into the repo. |
| II. Portability | PASS. The relationship is core `role_assignments` data, so it moves with a data restore and exports through core privacy. The plugin table gets a privacy provider. A new server gets the role from `apply`. |
| III. Public repo (NON-NEGOTIABLE) | PASS. Who mentors whom is never declared (FR-012). The CLI prints counts, never names. Verification uses `ltct-test-*` accounts, and the quickstart forbids committing run output. |
| IV. Disclosure (NON-NEGOTIABLE) | PASS. Mentor guides are not published (spec). `showreports = 0` is declared so that mentors cannot reach submissions or logs through core reports. The Mentoring page shows completion only, never quiz attempts or answers. |
| V. CBC fidelity | PASS. The page shows course completion, which is training evidence. The allowlist excludes every competency rating or review capability. Strings carry no level and no "certified" (A17). |
| VI. No LMS orientation | PASS, proven only by SC-005. The mentor gets one menu item in browser and app. The site team assigns in about six clicks on core's page. Phase B gives managers one page linked from the learner's profile. |
| VII. One shape | PASS. One mentor role for every partner, with no per-organisation variant. |
| VIII. Language data | Not applicable. |
| IX. Flat cost, field-ready | PASS. Core plus our plugin. Site plugins are in the free app, and the app handler is what makes the view field-ready. No push dependency: messages arrive whenever the app syncs. |
| X. Traceable and verified | PASS, with gates. Cites row #11, whose status is updated in the delivering PR. Every API was confirmed on `MOODLE_502_STABLE`, and six behaviours are instance tasks. SC-005 needs real mentors and managers before "done". New recurring operation: the site team assigns mentors ~~(until spec 008's bulk tool and, if approved, Phase B)~~ *(updated 2026-10-05: in bulk through spec 008's `ltct_admin.py mentors assign` and `mentors end`; organisation managers assign their own learners' mentors through Phase B, approved 2026-10-02 and built)*. There is a one-off `--sync` after upgrade. No new hosting cost. |
| XI. Survives an upgrade | PASS. Configuration plus `local_ltuse` on supported extension points: capability, pages, event observers, `myprofile_navigation`, `control_view_profile`, the mobile handler, CLI and privacy provider. Writes only through `role_assign`/`role_unassign`/`role_unassign_all`, `core_role_set_assign_allowed`, `api::add_contact`/`remove_contact`, and the plugin's own table. Raw reads: `role_assignments` ⋈ `context` by `userid`, and `course_completions` by `userid`. Both are stable core tables read by indexed columns, and both are listed in the README regardless. The plugin's `version` is bumped. `requires` and `supported` are unchanged at 5.2. |
| Platform: core first | PASS, with justified own code (Complexity Tracking). The role, assignment page, messaging, completion and Course mentor are all core. Our code fills only the gaps core leaves: a cross-course view in browser and app, contacts across no shared course, and (Phase B) organisation scoping. |

Re-checked after Phase 1 design: no change. Two points are raised for the maintainer, not
routed around: the Phase B decision (R7), and the reading of FR-007 that a block still wins
(R5). *(Updated 2026-10-05: Phase B was approved on 2026-10-02, R7.)*

## Project Structure

### Documentation (this feature)

```text
specs/003-mentor-role/
├── spec.md
├── plan.md              # this file
├── research.md          # R1–R11 + instance research tasks
├── data-model.md
├── quickstart.md        # A1–A17, B1–B5
├── contracts/
│   ├── declaration.md   # roles.yaml mentor + allowassign, settings/mentoring.yaml, Phase B cohort
│   └── local-ltuse.md   # capability, pages, observer, app handler, CLI, privacy, profile hook
└── tasks.md             # /speckit-tasks
```

### Source code

```text
moodle/
├── site/
│   ├── roles.yaml                     # + mentor; manager gains allowassign: [mentor]
│   ├── settings/mentoring.yaml        # new: moodlecourse/showreports = 0 (#11)
│   ├── site.yaml                      # local_ltuse pin bumped
│   ├── organisations.yaml             # Phase B: + mentors_cohort ltct:mentors
│   └── README.md                      # how to assign and end a mentor relationship
├── local_ltuse/
│   ├── db/access.php                  # + local/ltuse:viewmenteeprogress (CONTEXT_USER)
│   ├── db/events.php                  # new: role_assigned, role_unassigned, user_deleted
│   ├── db/install.xml, db/upgrade.php # new table local_ltuse_mentor_contact
│   ├── db/mobile.php                  # new: CoreMainMenuDelegate "Mentoring"
│   ├── classes/mentoring.php          # new: for_user(), progress_status() (pure)
│   ├── classes/observer.php           # new: contacts on assign/unassign/delete
│   ├── classes/output/mobile.php      # new: mentoring_view()
│   ├── classes/privacy/provider.php   # new: declares and exports the contact table
│   ├── classes/profile_access.php     # decide() gains $viewerismentor (R9)
│   ├── classes/siteconfig/applier.php, drift.php, inspector.php  # allowassign
│   ├── classes/mentor_admin.php       # Phase B: pure decision
│   ├── mentoring.php                  # new page
│   ├── mentors.php                    # Phase B page
│   ├── cli/mentor_contacts.php        # new: --sync, --end-all
│   ├── templates/mentoring.mustache, mobile_mentoring.mustache
│   ├── lib.php                        # myprofile_navigation, primary navigation, hook input
│   ├── lang/en/local_ltuse.php        # strings
│   ├── version.php                    # bumped
│   └── README.md                      # raw reads, observers, CLI
└── REQUIREMENTS.md                    # row #11 status on delivery
scripts/
└── site_config.py                     # _check_mentor allowlist; allowassign validate/render
tests/
├── test_site_config.py                # mentor and allowassign cases
├── profile_access_harness.php         # + mentor-who-is-a-manager case
├── mentoring_harness.php              # new: progress_status()
└── mentor_admin_harness.php           # Phase B
specs/012-assignments-peer-review/plan.md   # corrects "organisation manager enrols the mentor" (R8)
```

**Structure Decision**: everything that touches Moodle goes in `local_ltuse`, which is already
the training system's plugin. A second plugin would be a second upgrade liability for the same
team. The decision logic sits in small pure classes beside `profile_access.php`, so the harness
pattern from spec 002 tests it without Moodle. The allowassign support goes into the existing
siteconfig classes because it is a property of a role declaration, not a new item type.

### Phases and the gate

| Phase | Ships | Gate |
|---|---|---|
| **A** | role + allowlist, `allowassign`, `showreports`, capability, Mentoring page and app handler, contacts observer + CLI, privacy provider, profile-hook change, README and docs, row #11 → built | none |
| **B** | `ltct:mentors` cohort, `mentors.php` + `mentor_admin` decision, profile link for managers, quickstart B1–B5, SC-003 | **maintainer records a decision on organisation-manager self-service (R7)**, in `INTENT.md` Decisions |

## Cross-spec effects

- **Spec 002 (decision needed; decided 2026-10-02, approved, R7)**: Phase B reverses the
  2026-10-01 decline of manager self-service, for mentor assignment only. If it is declined,
  FR-008's manager part and SC-003 move to spec 008, and this spec's FR-008 is amended in the
  same PR. The profile hook gains a mentor exemption (R9) and still only takes access away.
- **Spec 012**: story 4 relies on its Course mentor role, and its cross-spec note is corrected:
  ~~the site team, not the organisation manager, enrols a mentor as Course mentor (R8).~~ 012's
  `teacher` declaration is untouched. *(Updated 2026-10-05: nobody enrols a course mentor by
  hand; spec 008's sync does (R8). Spec 012 is parked for re-plan (Doug, 2026-10-05).)*
- **Spec 004**: consolidated mentor and organisation reporting can build on
  `\local_ltuse\mentoring::for_user()` or replace the browser page with a Report builder source.
  Its scope rule is the same capability.
- **Spec 006**: pathway or learning-plan visibility for mentors widens the allowlist by
  `moodle/competency:planview` only, in 006's change (R11).
- **Spec 008**: owns bulk assignment (via `core_role_assign_roles` with `contextlevel: user`),
  a UI for ending all of a mentor's relationships, and auto-enrolling mentors as Course mentor.
  *(Updated 2026-10-05: delivered. `ltct_admin.py mentors assign` and `mentors end`, and the
  course-mentor sync (008 research R10), on since 2026-10-05 (`local_ltuse/coursementorsync: 1`,
  #97). A course mentor holds Teacher and so sees the whole course; accepted (Doug, 2026-10-05),
  spec FR-005's note. Also accepted: the sync stays on before 008's instance checks V9 and
  V11–V13 (T067) have run.)*
- **Spec 001**: the role declaration gains the `allowassign` key ([contracts/declaration.md](contracts/declaration.md)),
  and the settings folder gains `mentoring.yaml`.
- **Spec 015**: no new scheduled operation. Drift covers the new declarations under its
  existing schedule.
- **Spec text**: the delivering PR should update the spec in three places:
  - its header branch;
  - FR-007's wording, to "privacy preference; a block of one person still applies";
  - FR-008, if Phase B is declined.

## Complexity Tracking

| Exception | Why needed | Simpler alternative rejected because |
|---|---|---|
| Own page + app handler (Mentoring) | SC-001 (one minute, on a phone) and FR-003 (completion across all courses). Core gives no cross-course completion view and nothing in the app (R3). | **Profile + Grades overview only**: no completion, nothing in the app. **`showreports = 1`**: shows submissions and logs. **`block_mentees`**: names only, and not in the app. |
| Own observer + table for message contacts | FR-007 across no shared course. Core checks `messageanyuser` only at system or course context (R5). | **`messageanyuser` at system context, or `messagingallusers = 1`**: a mentor could message the whole site, undoing row #19. **Contact requests**: refused without a shared course, and they need the learner to accept. |
| Own page for organisation managers (Phase B, gated) | FR-008: core cannot scope user-context assignment to one organisation (R7). | **`role:assign` at system context for managers**: reaches every user on the site. **Site team only**: possible, and it is the fallback if Phase B is declined. |
