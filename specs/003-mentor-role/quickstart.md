# Quickstart: verifying the mentor relationship

**Spec**: [spec.md](spec.md) · **Contracts**: [declaration](contracts/declaration.md),
[local_ltuse](contracts/local-ltuse.md)

This guide proves the feature end to end. It uses **test accounts only** on the temporary
5.2.3+ instance (Principle X). Nothing a run produces is committed: no names, screenshots of
real people or exports. Report results by scenario ID.

## Prerequisites

- `MOODLE_URL` and `MOODLE_TOKEN` in the environment, never in a file.
- `local_ltuse` at the version that adds the mentor capability, installed by
  `python scripts/site_config.py apply`.
- Test accounts:
  - **mentors**: `ltct-test-mentor-1` and `ltct-test-mentor-2`, with `ltct-test-mentor-2` in organisation B;
  - **learners**: `ltct-test-learner-1` to `ltct-test-learner-4`, with 1 and 2 in organisation A and 3 and 4
    in organisation B;
  - **organisation manager**: `ltct-test-orgmgr-a`, in A's managers cohort;
  - **site team**: `ltct-test-siteteam`, with `manager` at system context.
- Three published test courses, C1–C3, with completion tracking on.
  - `ltct-test-learner-1` is enrolled in C1 and C2, and has completed C1.
  - `ltct-test-learner-1` and `ltct-test-mentor-1` share no course.
  - *(Added 2026-10-05)* The course-mentor sync is on since 2026-10-05 (spec 008 research R10).
    It would make each test mentor Course mentor (`teacher`) in their learner's courses. The
    prerequisite above, that `ltct-test-learner-1` and `ltct-test-mentor-1` share no course,
    would then fail as soon as A2 assigns the mentor, and A6, A7, A8, A9 and A13 would test the
    Teacher role, not the Mentor role. A7 would fail outright: `teacher` holds
    `report/completion:view` and `report/progress:view` by default (spec FR-005's note, research R8;
    accepted by Doug, 2026-10-05). So for
    Phase A, either enrol the test learners in C1–C3 manually (a pilot enrolment, which the sync
    never counts) or run with `local_ltuse/coursementorsync: 0` in the run's `--site-dir` copy.
    Task T035 turns it on for story 4: if Phase A ran with a `--site-dir` copy at 0, T035 starts
    by running `site_config.py apply` from the repo declaration again (`coursementorsync: 1`).
- The Android Moodle app, signed in as each user in turn.

## Repo checks (no server)

```bash
python scripts/site_config.py validate                 # exit 0
python -m pytest tests/test_site_config.py -q          # mentor allowlist + allowassign cases pass
php tests/profile_access_harness.php                   # includes "manager of A, mentor in B"
php tests/mentoring_harness.php                        # progress_status() cases
php tests/mentor_admin_harness.php                     # Phase B only
```

Then plant each violation in a scratch copy of `roles.yaml` and confirm that `validate` exits 1
naming it:
- `moodle/user:editprofile` on `mentor`;
- `contextlevels: [course, user]`;
- `allowassign` on `orgmanager`.

## Phase A scenarios

| ID | Steps | Expected | Covers |
|---|---|---|---|
| A1 | `site_config.py apply`, then `drift` | `mentor` role, `manager → mentor` allow-assign and `showreports = 0` applied; drift clean | FR-001, R6 |
| A2 | As `ltct-test-siteteam`: open `ltct-test-learner-1`'s profile, go to Preferences, choose "Assign roles relative to this user", choose Mentor, add `ltct-test-mentor-1` and `ltct-test-mentor-2` | both assigned in under two minutes | FR-008 (site team) |
| A3 | As `ltct-test-mentor-1`: open Mentoring from the primary navigation | `ltct-test-learner-1` is listed with C1 *Completed on …* and C2 *In progress, N%*, plus profile, Grades and Message links. C2 has pages and a quiz with completion on: N% matches the learner's own course page, quizzes counted | FR-003, SC-001 |
| A4 | Enrol `ltct-test-learner-1` in C3; `ltct-test-mentor-1` reloads Mentoring | C3 appears as *Not started*, with nothing reassigned | FR-004, SC-004 |
| A5 | Suspend `ltct-test-learner-1`'s C1 enrolment, then delete it; `ltct-test-mentor-1` reloads | C1 still shows *Completed* both times | FR-004 |
| A6 | As `ltct-test-mentor-1`, open the profile URL, Grades overview URL and Mentoring for `ltct-test-learner-2` (unassigned) | profile no more than any site member sees; Grades refused; not listed | FR-005, SC-002 |
| A7 | As `ltct-test-mentor-1`, open `ltct-test-learner-1`'s Complete report and `report/completion/user.php` for C2. Then, as an editing teacher, turn C2's "Show activity reports" on and run `drift`, then `apply` | both refused (`showreports = 0`); drift reports `course:ltct:<C2>:showreports`, and apply turns it off again | R2 |
| A8 | As `ltct-test-mentor-1`, try to edit `ltct-test-learner-1`'s profile, change a grade, mark completion or enrol them | all refused | FR-006 |
| A9 | `ltct-test-learner-1` sets messaging to "My contacts only"; `ltct-test-mentor-1` messages them, and they reply. Repeat in the app. | delivered both ways, browser and app | FR-007, SC (story 2) |
| A10 | `ltct-test-learner-1` blocks `ltct-test-mentor-1`; `ltct-test-mentor-1` tries to message | refused (the block wins, research R5) | R5 |
| A11 | As `ltct-test-learner-1`: open your profile, then Mentoring, browser and app | "Your mentors" lists `ltct-test-mentor-1` and `ltct-test-mentor-2` with Message links | FR-010 |
| A12 | As `ltct-test-mentor-2`: open Mentoring | `ltct-test-learner-1` only; nothing of `ltct-test-learner-3`/`4` in B, though `ltct-test-mentor-2` is in B | US3-4, edge "two mentors" |
| A13 | `ltct-test-mentor-1` also enrols in C3 as a learner | their own course page and progress are unchanged; Mentoring still lists `ltct-test-learner-1` | edge "mentor is a learner" |
| A14 | As `ltct-test-siteteam`: remove `ltct-test-mentor-1`. As `ltct-test-mentor-1`, without signing out, reload Mentoring and the profile URL | `ltct-test-learner-1` gone and profile restricted at once; the plugin-made contact is removed; `ltct-test-learner-1`'s completions and grades unchanged | FR-009 |
| A15 | Make `ltct-test-mentor-1` mentor of `ltct-test-learner-1` and `ltct-test-learner-2`, then on the server run `php public/local/ltuse/cli/mentor_contacts.php --end-all --mentor=ltct-test-mentor-1 --yes` | both ended; the output shows counts only | edge "mentor leaves" |
| A16 | Make `ltct-test-orgmgr-a` also mentor of `ltct-test-learner-3` (organisation B), then open `ltct-test-learner-3`'s profile as `ltct-test-orgmgr-a` | profile opens (hook exemption, research R9) | R9 |
| A17 | Mentoring, read with the strings file | no CBC level and no "certified" anywhere | FR-013 |

## Phase B scenarios (only after the maintainer's decision)

| ID | Steps | Expected | Covers |
|---|---|---|---|
| B1 | As `ltct-test-orgmgr-a`: open `ltct-test-learner-1`'s profile, choose "Manage mentors", add `ltct-test-mentor-1` (in `ltct:mentors`) | assigned; A3 holds for `ltct-test-mentor-1` | US3-1 |
| B2 | As `ltct-test-orgmgr-a`: open `mentors.php?userid=<t-learner-3>` directly | refused | US3-2 |
| B3 | As `ltct-test-orgmgr-a`: remove `ltct-test-mentor-1` | A14's outcome | US3-3 |
| B4 | The picker as `ltct-test-orgmgr-a` | lists only `ltct:mentors` members, never the whole site | R7 |
| B5 | A forged POST with another learner's id | refused (the decision is rechecked on write) | R7 |

## Before row #11 is marked done

SC-005: 2–3 real mentors and 2–3 real organisation managers have used the relationship
during a pilot, and their findings are recorded in the delivering PR. Until then
`moodle/REQUIREMENTS.md` row #11 says "built" (and "verified" once A1–A17 pass), not "done".
