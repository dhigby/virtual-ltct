# Quickstart: instance checks for spec 008

Run these on the temporary 5.2.3+ instance (`ltuse.net`) with **test accounts only**: every address `@example.org`.

- Organisation keys: `fixture-a` and `fixture-b`, declared only in a copy of `moodle/site/` (see Prerequisites).
- Courses: `ltct:fixture-shared`, in `ltct:published`, and `ltct:fixture-a-only`, in `ltct:org:fixture-a`.
- Files: keep every file for these checks under `~/ltct-private/008-checks/`, never in the repo.
- Results: record them in [research.md](research.md) "Instance results" as outcomes and counts only, never names or addresses.

## Prerequisites

1. `local_ltuse` is at `20261008NN` and installed.
2. Copy `moodle/site/` to `~/ltct-private/008-checks/site/`, and add `fixture-a` and `fixture-b` to the copy's `organisations.yaml`. The repo's `moodle/site/organisations.yaml` is **never** edited for a check.
3. In the copy's `settings/admin.yaml`, set `local_ltuse/coursementorsync` to 1. Run `python scripts/site_config.py apply --site-dir ~/ltct-private/008-checks/site`. This applies role `ltctadmin`, `settings/admin.yaml` and the fixture organisations.
4. Create the two fixture courses. Enrol `ltct:org:fixture-a` in `ltct:fixture-shared` by hand, as the starting state.
5. On the server, run `cli/setup_admin_token.php --username=<a test site-team account holding only ltctadmin> --token-file=…`. Set `MOODLE_URL` and `MOODLE_ADMIN_TOKEN` in your shell.
6. `python scripts/ltct_admin.py check --site-dir ~/ltct-private/008-checks/site` exits 0 and reports no missing capability.

Every `ltct_admin.py` command below takes the same `--site-dir`.

| # | Do | Expect | Proves |
|---|---|---|---|
| V1 | Put an intake file inside the repo folder and run `intake` on it. Repeat in a fresh `git init` folder, in a missing subfolder of that folder (as `--out`), and with `git` removed from PATH. | All refused, naming `~/ltct-private/`. For the repo copy, the tool says whether git has it. Nothing sent. | FR-004, US1-4, R14 |
| V2 | `intake` 30 rows: 27 new, 2 existing in `fixture-a`, 1 existing in `fixture-b` but listed as `fixture-a`. Time the whole run. Log in as one new account with the emailed password. | Preview counts 27/2/1, nothing sent. Apply with the code creates 27 and flags 1, in under 15 minutes including the preview. Each new account's username is its email, lowercased. Login with the email works, and a password change is forced. A 31st row whose email is a fixture account's username is `rejected` (`login_clash`) and makes no account. | US1-1, US1-2, SC-001, R2 |
| V3 | Right after V2, without waiting for cron, look at `ltct:org:fixture-a` membership and the enrolments in `ltct:fixture-shared`. | All 27 are cohort members and actively enrolled, in the same request. | R3 (dynamic cohorts realtime), R7 |
| V4 | Run V2's apply again with the same code, then preview again. | Second apply: every row `already done`, exit 0. New preview: 29 `unchanged`, 1 `flagged_other_org`. | FR-003, SC-002, US1-3, R15 |
| V5 | With 016 installed, set `fixture-b`'s minimum to `email`. Then run intake rows: (a) `protection: email` in `fixture-a`; (b) no protection column, in `fixture-b`; (c) `firstname`, before 016's decision 2; (d) an existing `fixture-a` account whose row now asks for `email`. Watch the Moodle logs. | (a) and (b): protection settled before any `cohort_member_added` or `user_enrolment_created`. (c): `waits` in the preview, and no account exists after apply; once `firstname` is available, the account it makes has a `^ltc-[a-z2-7]{8}$` username and its email login works. (d): `flagged_protection`, nothing enrolled. | FR-019, R3, R5 |
| V6 | Kill the network mid-apply (unplug after about 10 rows), reconnect, and run the same apply with the same code. | The rest complete. Rows already done report `already done`. No duplicate account, membership or enrolment. | edge case "interrupted", R15 |
| V7 | `enrol course --cohort ltct:org:fixture-a --course ltct:fixture-a-only`. Then the same for `ltct:org:fixture-b`, then `ltct:org:fixture-a:managers`, then any cohort into a course in `ltct:pilots`, then a course idnumber that does not exist. | A's cohort as Student: allowed. B's: refused. A's managers: `orgmanager`. Pilots: refused. Missing course: file-level refusal with no code. | R7, 002 R11, data-model §1 |
| V8 | Give a test `fixture-a` learner a completed activity and a quiz attempt in `ltct:fixture-a-only`. Then `move` them to `fixture-b` (after V10's mirror). Separately, set a new test account's `ltct_org` to `fixture-a`. | New member enrolled with no further step. Move preview shows `suspended_by_rule` for `ltct:fixture-a-only`. After apply, the completion and attempt remain, the old cohort-sync enrolment is suspended (not deleted), and the learner is active through `ltct:org:fixture-b`'s instance in `ltct:fixture-shared` (kept) and in each gained course. | US2-2, US2-3, US3-2 |
| V9 | **Run after V13.** `unenrol` A's cohort from `ltct:fixture-shared`, where V11's learner has a course mentor. Then re-enable it with `enrol course --cohort ltct:org:fixture-a --course ltct:fixture-shared`. | Instance disabled, not deleted. Learners' grades and completion remain. The course mentor is unenrolled in the same request. Re-enabling restores the enrolments and, with the sync on, the course mentor. | FR-006, R7, R10 |
| V10 | Run `move` for a learner A → B whose shared course B's cohort is not yet in. Then `enrol mirror --from fixture-a --to fixture-b`, and run `move` again. | Before the mirror: refused, with `lost`. After: `kept`, applied. | FR-015, 002 FR-017 |
| V11 | `mentors assign` a test mentor to a learner enrolled in `ltct:fixture-shared`. Open the course participants and 004's delivery report. | Mentor enrolled through `ltct:coursementor`, with Teacher held by component `local_ltuse`, in "Mentor group 1" with that learner. The mentor is not listed in 004's delivery report. | FR-017, FR-018, R10 |
| V12 | End that mentor relationship. Separately, suspend the learner's enrolment; suspend the learner's account; and, for a mentor also enrolled in the course another way, end their reason. | In each case the mentor's Teacher role and course-mentor enrolment go, and they leave the group, in the same request; the last case still loses Teacher. With 016, the mentor's identity view of a protected learner ends. | FR-018, R10, 016 FR-006 |
| V13 | Record a one-course mentor for the learner in `ltct:fixture-shared`. | Replaces the default mentor in that course only. | R10 precedence (plan decision 2) |
| V14 | `suspend` a logged-in test learner (on another browser), a test mentor, and a learner in a holding entry. Then `reactivate` them. | Sessions end, login is refused, enrolments are intact; reactivation restores access. All three succeed for the site team. | FR-006, US3-1, R6 |
| V15 | `summary --org fixture-a`, then with `--out` inside the repo, then outside. | Masked rows on screen; refused in the repo; written outside. | FR-007, US3-3 |
| V16 | As a token user without `local/ltuse:administer`, run `check`. As a `fixture-a` manager on 002's page, try to see, enrol, suspend and assign a mentor for a `fixture-b` learner. | `check` refused by the service. Each manager attempt refused, with nothing of B visible. | FR-008, FR-009, SC-005, US4 |
| V17 | With 006 installed, `enrol pathway` a fixture cohort into a pathway, then publish a course that joins it. Also try `enrol pathway --cohort ltct:mentors`. | The cohort is enrolled in the new course with no further step. The `ltct:mentors` attempt is refused, and 006's table has no row for it. | FR-005 pathway half, R11 |
| V18 | `managers`: add a test ALTC to two fixture managers cohorts and to `ltct:mentors`, including a member of `fixture-a` added to `ltct:org:fixture-a:managers`. Remove a non-member. Try an `ltct:org:fixture-a` cohort. | Adds succeed, and the own-organisation case shows its note. Removing a non-member fires no `cohort_member_removed`. The organisation cohort is refused. | FR-016, R9 |
| V19 | After V1–V18, and again after the manager pilot: run `git status --porcelain`, and `git log --all --name-only --since=<run start>`, in the repo and in every worktree used. | No new untracked or committed data file (`.csv`, `.tsv`, `.txt`, `.xlsx`), and no `example.org` address outside `tests/`. | SC-003, R14 |

Clean-up after the run: hide the fixture courses, and retire the fixture organisations from the copy's declaration. Nothing in the repo changes.

## Done gate (FR-014, SC-004, SC-006)

After V1–V19, 2–3 real ALTCs or organisation managers each:
- (a) fill in an intake list from `ltct_admin.py template --kind intake` for people they need, which the site team applies with no correction;
- (b) enrol, suspend and reactivate their own people on spec 002's organisation page (plan decision 6).

Record findings in research.md, each resolved or accepted:
- Name testers only as "tester 1/2/3", with the role "ALTC" or "organisation manager".
- Name no Area or organisation entry, and give no protection level or count tied to one, in research.md, a PR or an issue (constitution III).
