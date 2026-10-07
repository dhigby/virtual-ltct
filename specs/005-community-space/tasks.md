---

description: "Task list for 005 community space beyond courses"
---

# Tasks: Community Space Beyond Courses

**Input**: Design documents from `/specs/005-community-space/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Decisions**: every task implements plan.md "Decisions (Doug, 2026-10-07)" and "Round 2 (Doug,
2026-10-07)" exactly. No task reopens one. Where a task meets a case the decisions do not cover,
it stops and raises it. None is open: the one case found while writing these tasks, removing a
space's mentor row after its cohort is deleted, is settled within round 2 item 24 by the written
removal procedure (T005).

**Tests**: Included. The plan asks for:
- Python `unittest`/pytest in `tests/`: `tests/test_site_config.py`, `tests/test_ltct_admin.py`,
  `tests/test_publish_moodle.py` and a new `tests/test_engagement_review.py`;
- Moodle-free PHP harnesses in `tests/*_harness.php` (existing style: `define('MOODLE_INTERNAL', 1)`,
  a local `check()`, the class file required directly, exit 1 on failure):
  `tests/learner_home_harness.php` and `tests/admin_harness.php`;
- PHPUnit (`advanced_testcase`, synthetic data only, run in plugin CI, never on the shared host) in
  `moodle/local_ltuse/tests/`: new `siteconfig_cohortspaces_test.php`, `siteconfig_inbound_test.php`,
  `course_mentor_sync_test.php`, `mentor_subscriptions_test.php`, `trackforums_test.php`,
  `community_engagement_test.php`; and `moodle/block_ltuse/tests/block_test.php` (existing).

Spec 008's existing sync tests live in `moodle/local_ltuse/tests/admin_test.php`. They must pass
**unchanged**; this feature adds its sync cases in the new `course_mentor_sync_test.php`. Spec 007's
`tests/learner_home_harness.php` cases must also pass unchanged.

Each new Python test file is wired into CI by the task that creates it, never ahead of it: GitDoc
pushes the branch about every five minutes, and a workflow naming a missing file goes red. For the
same reason a new top-level declaration file is registered in `TOP_FILES` (`scripts/site_config.py`)
by an earlier task than the one that creates it: validate refuses an unregistered top-level yaml as
"unexpected file" (L545), and an absent registered file is skipped. PHPUnit files under
`moodle/local_ltuse/tests/` are picked up by `.github/workflows/local-ltuse-plugin-ci.yml` with no
wiring.

**Area shape**: PHPUnit only (`siteconfig_cohortspaces_test.php`, `course_mentor_sync_test.php`)
until a real Area entry has opted in and its first real mentor is recorded (round 2, item 23). No
task opts a real entry in for testing, adds a test organisation to `organisations.yaml`, puts a test
account in a real Area cohort, or opens or deletes an Area space. The shape-agnostic parts of SC-002
(isolation, the landing-page line) are proven on the teaching shape (T048, T049, V7); the area half of
SC-002 and SC-006 is PHPUnit-only until T104.

**Live checks**: tasks marked **(live)** run on ltuse.net (`$MOODLE_URL`) now, before merge, with
test accounts and test cohorts only (round 2, item 16: "Just do it."; constitution X). Test teaching
cohorts are made through spec 002's scripted action with Moodle names starting `test-`. Record
pass/fail, counts, times, the app version and the device only, never a name, email or cohort name.
Results go in a `## Results` section appended to `specs/005-community-space/quickstart.md`, created
by the first live task that records one (T004).

**Deploy procedure** (every live task that needs this branch's code on the server says "deploy"):
0. if `moodle/site/community.yaml` exists in the tree, confirm T041 and T042 are drafted in this
   branch and T006 has recorded (a)–(c); otherwise do not deploy;
1. `git fetch` and confirm `git log HEAD..origin/005-community-space` is empty;
2. archive `moodle/local_ltuse` (and `moodle/block_ltuse` once US2's block change exists) with
   `git -c core.autocrlf=false archive` and copy to `public/local/ltuse` (and `public/blocks/ltuse`);
3. `php -d max_input_vars=5000 admin/cli/upgrade.php --non-interactive`, then
   `php admin/cli/purge_caches.php`;
4. `python scripts/site_config.py validate`, `drift`, `apply`, `drift`.
After merge, the next deploy is from a fast-forwarded `main` (the 2026-10-02 lesson: a stale tree
reverted live settings).

**Version rule**: `admin/cli/upgrade.php` exits with "no upgrade needed" when no plugin version has
changed, and only an upgrade re-reads `db/events.php`, `db/services.php` and `db/install.xml`. So
before any deploy whose diff since the last deploy changes anything under `moodle/local_ltuse/db/`,
raise `$plugin->version` in `moodle/local_ltuse/version.php` to a new `YYYYMMDDXX`, set the same stamp
in the `local_ltuse` pin in `moodle/site/site.yaml` (`_check_source` requires them equal), and, when
block code needs the new version, raise `moodle/block_ltuse/version.php`'s
`dependencies['local_ltuse']` and its own pin. This happens at least at T017 (the table), before T034
(T033's observers), at T065 before T066 (T062's observers, the block) and before T092 (T090's
service). A version bump with no schema change needs no `upgrade.php` step.

**Verification gates** (constitution X, plan "GATED"): a live task that settles a behaviour another
task relies on comes first, and the dependent task names it:
- `messageinbound_enabled: 1` is committed only after V2's mailbox part passes (T036 follows T035).
- No cohort space is applied to any server before T041 (INTENT decision) and T042 (constitution
  2.2.0) are drafted in this branch, and before spec 002's gate T006 passes (Deploy step 0).
- No real learner is admitted to any space, and SC-003 (T103) does not start, until the PR with
  T041/T042 has merged and these have passed: V2 (T035, T039), V4 (T037), V5 (T038), SC-001 (T040),
  V6 (T066, T067), V7 (T069), V8 (T070), V9 (T068, T080–T084), V10 (T071) and V11 (T072). If spec 015 still
  has no mailbox, V2 and the reply-by-email parts of T038 and T040 cannot run; SC-003 then checks the
  mentor route as one-way only (per-post email, then log in to reply), and Results says so.

**Never**: edit anything under `modules/`; write a token, `MOODLE_INBOUND_PASS`, a mailbox address,
a learner's or cohort's name, an email or a live count into the repo (Principle III); add groups or
`groupmode: 1` anywhere; build the site-wide space (Q1); edit another spec's files (follow-ups are
raised with that spec).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel within its phase (different files, no dependency on an incomplete task)
- **[Story]**: US1–US5, from spec.md; none in Setup, Foundational and Polish

---

## Phase 1: Setup

**Purpose**: the API confirmations every PHP task relies on, the plan's path corrections, the
dependency gates and the requests to the specs that must answer them, and the written procedure for
a space whose cohort is deleted.

- [X] T001 [P] Confirm every Moodle API this feature calls, Context7 first (`npx ctx7@latest docs /websites/moodledev_io_5_2_apis "<question>"`, then `/moodle/moodle`), then upstream source on `MOODLE_502_STABLE` (5.x paths under `public/`), and record file:line for each in a new section "R18. API confirmations (tasks)" at the end of `specs/005-community-space/research.md`. The list: `forum_set_user_maildigest()` (lib.php ~L5958, its `require_capability('mod/forum:viewdiscussion', …)`); `\mod_forum\subscriptions::subscribe_user_to_discussion()` (L741, `preference = time()` L764-771); `forum_add_discussion()`, `forum_discussion_pin()`; `\core\message\inbound\manager::get_handler()`, `record_from_handler()` and the handler's `can_change_enabled()`, `can_change_defaultexpiration()`, `can_change_validateaddress()`; `enrol_cohort_plugin::add_instance()` (L111-125, no de-duplication) and `enrol_plugin::update_status()`; `create_course()` side effects (`newsitems`, `enablecompletion`, category visibility L1823-1827, mod_forum `course_created` observer L123-135); `assign_capability()`; `update_moduleinfo()` as reached by `local_ltuse\util::upsert_module()` (`moodle/local_ltuse/classes/util.php` L167); `user_update_user()` (for T020); `\core\event\cohort_created`, `cohort_updated`, `cohort_deleted` payloads; `\mod_forum\event\discussion_created`, `post_created` (objectid, other); the internal log `sql_reader` used for "any logged event"; the standard log store's retention setting `logstore_standard/loglifetime` (`public/admin/tool/log/store/standard/settings.php` ~L50, a configselect: 0 never delete, 1000, 365, 180, 150, 120, 90, …; Moodle 5.2 has no core `loglifetime`); `admin/cli/cfg.php` (to read it in T092); and that `admin/cli/upgrade.php` does nothing without a version change while `admin/cli/purge_caches.php` clears the cached observer and service lists. Note any that differ from research.md and stop for the maintainer if a difference changes a decision.
- [X] T002 [P] Correct the plan's paths so later tasks and reviewers find the real files, in `specs/005-community-space/plan.md` Project Structure: the block is `moodle/block_ltuse/` (not `moodle/blocks/ltuse`); `util::upsert_module()` is in `moodle/local_ltuse/classes/util.php`; spec 008's sync tests are in `moodle/local_ltuse/tests/admin_test.php` and stay unchanged, while `moodle/local_ltuse/tests/course_mentor_sync_test.php` is **new**; the cohort-space observer callbacks go in the existing `moodle/local_ltuse/classes/observer.php` (which already has `cohort_deleted`); the new helper classes `moodle/local_ltuse/classes/admin/digest_overrides.php`, `moodle/local_ltuse/classes/mentor_subscriptions.php`, `moodle/local_ltuse/classes/trackforums.php` and the PHPUnit files `mentor_subscriptions_test.php` and `trackforums_test.php` are added to the tree; and these existing files, which later tasks edit, are listed: `scripts/ltct_admin.py` (T055), `scripts/publish_moodle.py` (T054, if needed), `moodle/local_ltuse/classes/privacy/provider.php` (T019), `moodle/local_ltuse/classes/task/course_mentor_reconcile.php` (T062), `moodle/local_ltuse/classes/external/admin_preview_course_mentors.php` (T057), `moodle/local_ltuse/classes/siteconfig/drift.php` (T061), `moodle/site/README.md` (T079, T094) and `.github/workflows/site-config.yml` (T087).
- [X] T003 [P] Read `.specify/memory/constitution.md` (L29-51 II, L119-139 VII, L239-266 Platform & Delivery, L285-330 Governance and version history) and `INTENT.md` `## Decisions` (L117) so T041 and T042 can be drafted in the house wording. No edit in this task.
- [ ] T004 (live) Core-behaviour probes on ltuse.net with test accounts, no code deployed (they settle what US1's code relies on): (a) V3: send test mentor M a Moodle message, including through test mentee A1's landing-page mentor link (kept, Q22), and confirm the notification email's Reply-To is the no-reply address (quickstart US1 step 9); (b) the V4 core part: on a test course forum, set a test user's per-forum digest to 0 by hand in the browser and confirm they get a per-post email and an app push for a post, while a user on digest 1 gets neither until the digest, and the digest email has no Reply-To (quickstart US1 step 8). Create `## Results` at the end of `specs/005-community-space/quickstart.md` and record pass/fail and the app version there.
- [X] T005 Write down the removal of a space's mentor row after its teaching cohort is deleted, within round 2 item 24 (no Doug decision; plan.md says no marker remains, and item 24 already leaves the space's future to the site team). The procedure: the site team keeps the mentor, or removes the whole space by the written procedure, `delete_course()` on the space, after which the next `\local_ltuse\task\course_mentor_reconcile` run's `remove_orphan_records()` deletes the row because its course is gone (T078 keeps such a row only while its course exists). `ltct_admin.py` cannot name the row, because it names a row's cohort by idnumber (`scripts/admin_files.py` `_check_course_mentors`, `course_mentor_records::resolve()`) and a deleted cohort has none; no tool route is built. In `specs/005-community-space/contracts/site-config-community.md` "Mentor sync", "Cohort deleted (round 2)", add that sentence. In `specs/005-community-space/quickstart.md` "Where to run", change the removal procedure to T102's order: for each test space, `delete_course()` the space, then remove its cohort, then confirm after one reconcile run that the test cohort-mentor rows are gone (count only); drop the `ltct_admin.py` step. A tool route (a `cohort: deleted` remove mode in `ltct_admin.py`) is listed under Deferred as later work.
- [X] T006 Spec 002 gate for teaching spaces: read `specs/002-org-structure-cohorts/` (plan, spec FR-014/FR-015/FR-019, `contracts/declaration.md`) and record in the PR description whether 002 has (a) chosen a **Moodle cohort** for teaching groups, (b) fixed the teaching cohorts' idnumber namespace (and whether it sits under `ltct:`, which T061's drift exclusion handles), (c) built the ALTC scripted action that creates and fills them, and (d) applied its Area entries. US2's live tasks T066 onward wait for (a)–(c); the Area-shape later check T104 waits for (d). If (b) is not fixed, T053 commits `community.yaml` without a teaching selector (T052 accepts that as "no teaching spaces"), and T066–T072 stay unchecked. Re-read after T008 is answered. Do not edit 002's files.
- [X] T007 [P] Raise with spec 015 (do not edit 015's files): an issue "Spec 005 operator tasks" listing one IMAP mailbox with case-preserving `+` subaddressing, its secret, cron every minute, outgoing send limits for per-post mentor and mentee mail, mailbox retention (it holds learner text and `tobeconfirmed`), log growth at `logstore_standard/loglifetime` 365 days, SC-004's rebuild run (T105), and that FR-010 should record the Premium app plan as chosen, as `moodle/site/site.yaml` L42 says. Link it in the PR description. T035 waits on the mailbox.
- [X] T008 [P] Raise with spec 002 (do not edit 002's files; two items): `contracts/declaration.md`'s organisation entry shape gains the optional `space` field (T052 changes the `_validate_organisations()` comment in this PR); and 002's plan step takes "teaching groups are Moodle cohorts" as a constraint, fixing their idnumber namespace and the ALTC scripted action (T006 (b), (c)). Link it in the PR description.

**Checkpoint**: APIs confirmed, paths corrected, gates known and their answers requested, the
cohort-deleted removal written down.

---

## Phase 2: Foundational (blocks every story)

**Purpose**: the site-wide forum and email behaviour every forum inherits (FR-011, FR-011a, FR-011b),
the `spacemember` role, the record of overrides sync writes, and the one-off read-tracking switch
(plan build order 1). US1, US2 and US3 all write digest overrides through T018's helper.

### Tests (write first, see them fail)

- [X] T009 [P] Add `class SiteForum005(...)` to `tests/test_site_config.py`, following the existing per-spec classes (copy the tracked files into the temp site in `setUp`, `self.reset()` then one `edit()` per `subTest`, `assertRejected()` / `assertInvalid(needle)`): (a) the tracked `settings/forums.yaml`, `settings/logging.yaml` and `settings/notifications.yaml` are accepted and render `enablerssfeeds` 0, `forum_enablerssfeeds` 0, `enableportfolios` 0, `maxeditingtime` 1800, `usetags` 1, `logstore_standard/loglifetime` 365, `defaultpreference_autosubscribe` 1, `defaultpreference_trackforums` 1, `defaultpreference_mailformat` 1 and `defaultpreference_maildigest` 1 (unchanged); (b) `enableportfolios: 1` is refused outright; (c) `logstore_standard/loglifetime` 119 is refused, 120 accepted ("one quarter plus a month"), and 0 (never delete, which keeps every log) accepted by the floor; (d) the tracked `roles.yaml` gives `teacher` `mod/forum:exportforum: prohibit` and `mod/forum:exportdiscussion`, `mod/forum:exportpost`, `mod/forum:exportownpost: prevent`, and leaves `mod/forum:postprivatereply` undeclared (unchanged); (e) `spacemember` exists, archetype `student`, `contextlevels` including course.
- [X] T010 [P] Create `moodle/local_ltuse/tests/course_mentor_sync_test.php` (`advanced_testcase`, `@covers \local_ltuse\admin\digest_overrides`) with the helper-level cases for `digest_overrides` (T018), on a generated course and forum with a user enrolled as student: (a) `set()` with no `forum_digests` row and no record writes the value through `forum_set_user_maildigest()` and inserts a record `(userid, forumid, value, released 0)`; (b) a person's own `forum_digests` row is kept and **never recorded**; (c) an unreleased record with no `forum_digests` row is marked `released` and nothing is written, and two further `set()` calls write nothing; (d) `remove()` sets -1 only where an unreleased record exists and the current value equals the recorded value, then deletes the record; (e) a value the person changed after sync wrote it is kept and only the record is deleted; (f) a user who no longer holds `mod/forum:viewdiscussion` (role removed) gets no call and no exception, and the override and record stay. US1–US3 add their cases to this file (T025, T050, T073), so it is edited sequentially.
- [X] T011 [P] Create `moodle/local_ltuse/tests/trackforums_test.php`: with three generated users (trackforums 0, 0, 1), one deleted user and the guest, `\local_ltuse\trackforums::enable_existing()` returns `['seen' => 3, 'changed' => 2]` (deleted and guest excluded), every remaining account then has `trackforums = 1`, and a second call returns `changed 0`.

### Implementation

- [X] T012 [P] Create `moodle/site/settings/forums.yaml` with `rows: [25]` (and the rows the spec's traceability gives each setting), a `purpose`, and one entry each with a `why`: `enablerssfeeds: 0` and `forum_enablerssfeeds: 0` ("an RSS URL is a bearer key", R8), `enableportfolios: 0` (Q14), `maxeditingtime: 1800` (Q11, kept so drift reports a change), `usetags: 1` (Q19, kept on). Run `python scripts/site_config.py validate`.
- [X] T013 [P] Create `moodle/site/settings/logging.yaml` in the plugin-setting form `moodle/site/settings/cohorts.yaml` uses (`settings:` list of `name`/`value`/`why`): `name: logstore_standard/loglifetime`, `value: 365`, `why` "Q21: a quarterly engagement review run well after quarter end (FR-011b). Moodle 5.2 keeps standard-log retention as this plugin setting; there is no core `loglifetime`." Validate.
- [X] T014 [P] In `moodle/site/settings/notifications.yaml`, add `defaultpreference_autosubscribe: 1` (R8: the mentee gets the mentor's reply), `defaultpreference_trackforums: 1` (Q18: new accounts; existing ones by T020) and `defaultpreference_mailformat: 1` (R8), each with a `why`; leave `defaultpreference_maildigest: 1` unchanged (row #25). Validate.
- [X] T015 In `moodle/site/roles.yaml`: (a) on `teacher`, `mod/forum:exportforum: prohibit` and `mod/forum:exportdiscussion`, `mod/forum:exportpost`, `mod/forum:exportownpost: prevent`, `mod/forum:postprivatereply` untouched, with a `why` citing Q14 and round 2 item 7 and saying 012's and 016's role tests run with it; (b) a new `spacemember` role per data-model "Member role `spacemember`": "Archetype student. Keeps `moodle/course:viewparticipants`, `moodle/user:viewdetails` and messaging, so members see the participant list and message each other (Q6). Keeps `mod/forum:allowforcesubscribe`. Its only purpose beside student is that the progress, programme and pilots reports, which filter on role `student`, do not show spaces." `contextlevels: [course]`, name "Space member", no capability overrides. Validate.
- [X] T016 In `scripts/site_config.py`, in the settings validator, refuse `enableportfolios` with any value but 0 ("learners' `student` role still holds `mod/forum:exportownpost`, which portfolios would expose", FR-011) and `logstore_standard/loglifetime` with a value from 1 to 119 (0, never delete, passes the floor; the declared value stays 365 and drift reports any change). Make T009 pass: `python -m pytest -q tests/test_site_config.py`.
- [X] T017 Add the table `local_ltuse_digest_override` to `moodle/local_ltuse/db/install.xml` with exactly the fields data-model names, "userid, forumid, value, released, timecreated" (plus `id`): `userid` and `forumid` int(10) not null with foreign keys to `user` and `forum`, `value` int(2) signed not null, `released` int(1) not null default 0, `timecreated` int(10) not null; a unique index on (`userid`, `forumid`); COMMENT "The per-forum digest overrides course-mentor sync wrote (spec 005, round 2), and the ones a person reset, so removal never erases a personal choice." Add the matching step to `moodle/local_ltuse/db/upgrade.php` with `upgrade_plugin_savepoint`, raise `$plugin->version` in `moodle/local_ltuse/version.php` to a new `YYYYMMDDXX` (the first bump of this feature; later deploys that change `db/` raise it again per the Version rule), and set the same stamp in the `local_ltuse` pin in `moodle/site/site.yaml`. Run `php -l` and `python scripts/site_config.py validate`.
- [X] T018 Create `moodle/local_ltuse/classes/admin/digest_overrides.php` (`namespace local_ltuse\admin; final class digest_overrides`), the one place that writes and removes overrides, `require_once($CFG->dirroot . '/mod/forum/lib.php')` and passing `\core_user::get_user($id)`. Methods `set(int $userid, int $forumid, int $value): string` (`written` | `kept_personal` | `released` | `recorded` | `skipped_nocap`) and `remove(int $userid, int $forumid): string`. Implement data-model "Mail state" verbatim: "Written only when the person has no `forum_digests` row for that forum (a raw read, listed in the README) **and** no `local_ltuse_digest_override` record for it, so a personal choice is kept (round 2); each write is recorded in `local_ltuse_digest_override`. A record with no `forum_digests` row means the person set the forum back to their default: sync marks its record `released` and never writes that override again (deleting the record would let the next run write it again)." and "Removal sets -1 only where an unreleased record exists **and** the current `forum_digests` value still equals the recorded value; otherwise the person has changed it, so sync keeps their setting and deletes only its record (round 2)." Before either call, check `has_capability('mod/forum:viewdiscussion', <forum module context>, $userid)`; without it return `skipped_nocap`, leave the override and record in place, throw nothing and queue no retry. Make T010 pass.
- [X] T019 Declare `local_ltuse_digest_override` in `moodle/local_ltuse/classes/privacy/provider.php`, following its `local_ltuse_mentor_contact` handling (metadata with `userid`, `forumid`, `value`, `released`, `timecreated`; contexts; export; delete for a user and for a context), and add the `privacy:metadata:local_ltuse_digest_override` strings to `moodle/local_ltuse/lang/en/local_ltuse.php`. Update the provider's header comment, which says the plugin keeps one table of personal data.
- [X] T020 Create `moodle/local_ltuse/classes/trackforums.php` (`enable_existing(): array`, setting `trackforums = 1` through `user_update_user()` as confirmed at T001, for every non-deleted, non-guest account with `trackforums = 0`, returning counts only) and `moodle/local_ltuse/cli/trackforums_existing.php` (`define('CLI_SCRIPT', true)`, the existing `cli/` scripts' style, prints `seen N, changed M` and nothing else, writes no file, idempotent). Make T011 pass.
- [ ] T021 Before T022 deploys T015's `teacher` change, run the tests of the specs whose roles and course forum this feature touches, with T015 in the tree, and fix only this feature's side if one fails: `python -m pytest -q tests/test_moodle_payload_discussion.py tests/test_protection_declaration.py tests/test_publish_moodle.py`, `php tests/protection_harness.php`, and in plugin CI `moodle/local_ltuse/tests/protection_test.php`. Later, once T060 and T053 exist, confirm no file this feature adds addresses an idnumber ending `:discussion` (grep `moodle/local_ltuse/classes/siteconfig/cohortspaces.php`, `moodle/site/community.yaml`) and record it in the PR description.
- [X] T022 (live) Deploy (procedure above; T017 raised the version). Run `php local/ltuse/cli/trackforums_existing.php` once and record its `seen N, changed M` (counts only; M is the count of accounts that had read tracking off); run it again and confirm `changed 0` (quickstart "Read tracking for existing accounts"). Confirm drift shows no line for the new settings and roles. Record in `specs/005-community-space/quickstart.md` Results.

**Checkpoint**: validate passes, T009–T011 pass, 012's and 016's role tests pass with the `teacher`
change, site-wide forum defaults, the `teacher` change and `spacemember` are live, and read tracking
is on for every account.

---

## Phase 3: User Story 1 — A mentor works from their inbox; the mentee works in the course (P1) 🎯 MVP

**Goal**: a mentor gets each of their mentees' delivery-course posts as one answerable email,
replies by email, and the reply lands as a post the mentee is pushed in the app (FR-001–FR-005a).

**Independent Test**: with test mentor M and test mentees A1, A2 in one test course with M as their
course mentor: A1 posts in the app; M gets one email (not a digest), replies from their registered
address, and the reply appears in A1's app after sync, with a push (quickstart US1, SC-001).

### Tests for User Story 1

- [X] T023 [US1] Add `class InboundMail005(...)` to `tests/test_site_config.py` (after T009, same file) for contract inbound-mail "Rules": (a) the tracked `settings/inbound-mail.yaml` and `inbound-mail.yaml` are accepted; (b) a literal `messageinbound_hostpass` is refused, and `env:` without `secret: true` is refused; (c) `messageinbound_mailbox` of 16 characters refused; (d) a value `<mailbox>` or `(from 015)` refused; (e) a double-quoted classname refused; (f) `validateaddress: 0` refused; (g) `defaultexpiration: 0` refused; (h) a classname not in `\mod_forum\message\inbound\reply_handler`, `\core\message\inbound\private_files_handler` refused, and `private_files_handler` with `enabled: 1` refused; (i) `messageinbound_enabled: 1` refused while mailbox, domain, host or hostuser is missing; (j) a non-empty `allowedemaildomains` in `settings/identity.yaml` refused while inbound mail is enabled; (k) the payload carries the handler row and renders the password as `<secret>`; (l) the top file without `rows` or `why` refused.
- [X] T024 [P] [US1] Create `moodle/local_ltuse/tests/siteconfig_inbound_test.php`: apply with the declared row enables `\mod_forum\message\inbound\reply_handler` with `defaultexpiration` 604800 and `validateaddress` 1 and returns `changed`; a second apply returns `ok`; a hand change of each field is reported by drift; a missing row is reported; a field whose `can_change_*()` is false is reported `blocked` and not written.
- [X] T025 [US1] Add delivery-course cases to `moodle/local_ltuse/tests/course_mentor_sync_test.php` (after T010, same file), on a generated `ltct:fixture-a` course with 008's cohort enrolment, a course forum and course-mentor sync switched on: (a) after `sync_course()`, the mentor and each synced mentee have override 0 on every forum of the course, recorded; (b) a learner with no mentor in the course has none; (c) a mentee with their own digest row (A3, quickstart step 3a) keeps it, it is never recorded, and removing their mentor leaves it; (d) removing the mentor's row removes only recorded overrides, in the removerole step before `role_unassign()`, with no exception; (e) a mentee who no longer has a mentor there while still enrolled loses only the recorded override; (f) a mentee whose cohort enrolment is removed (`unenrolaction = 3`) raises no exception, keeps the override and its record, and on rejoin nothing new is written; (g) an override is set on a forum added after enrolment, on the next sync; (h) a sync-written override the person resets is marked released and not written on the next two runs; (i) delivery-course enrolments, roles and `ltct:mentorgroup:` groups are exactly as before (run `admin_test.php` unchanged).
- [X] T026 [P] [US1] Create `moodle/local_ltuse/tests/mentor_subscriptions_test.php` (`@covers \local_ltuse\mentor_subscriptions`, `\local_ltuse\observer`): (a) a synced mentee starts a discussion and their mentor is subscribed to that discussion only, not the forum; (b) with the clock set so the subscription lands one second after the post's `created`, the stored `forum_discussion_subs.preference` is set back to the first post's `created`, and the forum cron task queues the opening post for the mentor; (c) a mentee's reply in a classmate's discussion subscribes the mentor to it (Q10, "start or post in"); (d) a non-mentee's post subscribes nobody; (e) when sync adds a mentor, they are subscribed to every existing discussion their mentees started or posted in; (f) both events in an `ltct:site:cohort:7` course are a no-op; (g) an existing subscription is not duplicated.

### Implementation for User Story 1

- [X] T027 [P] [US1] Create `moodle/site/settings/inbound-mail.yaml` per contract inbound-mail "Shape": `rows: [11, 25]`, a `purpose`, `messageinbound_enabled: 0` (`why`: "Off until the host provides the mailbox (Q8, spec 015 operator task) and V2 passes."), `messageinbound_hostssl: ssl`, `messageinbound_hostuser: env:MOODLE_INBOUND_USER`, `messageinbound_hostpass: env:MOODLE_INBOUND_PASS` with `secret: true`. Leave out `messageinbound_mailbox`, `_domain` and `_host` until spec 015 provides them (placeholders are refused); a comment says so. Never put a real value for a 015 field in this task.
- [X] T028 [US1] In `scripts/site_config.py` (after T016, same file), before T029 creates the file: add `inbound-mail.yaml` to `TOP_FILES` (required `rows, purpose, handlers, why`; an absent file is skipped, so validate stays green until T029); a `_validate_inbound()` implementing every contract inbound-mail rule (T023), including the cross-file `allowedemaildomains` check against `settings/identity.yaml`; `build_payload()` gains `inbound_handlers: [{classname, enabled, defaultexpiration, validateaddress}]`; `_summary()` gains an `inbound handlers: N` line. Run validate.
- [X] T029 [US1] After T028, create `moodle/site/inbound-mail.yaml` (keys `{rows, purpose, handlers, why}`): `rows: [11, 25]`, one handler `classname: '\mod_forum\message\inbound\reply_handler'` (single-quoted), `enabled: 1`, `defaultexpiration: 604800` (one week, Q20), `validateaddress: 1`, and a `why`. Make T023 pass: `python -m pytest -q tests/test_site_config.py`.
- [X] T030 [US1] Create `moodle/local_ltuse/classes/siteconfig/inbound.php` per contract inbound-mail "Apply" and "Drift": `get_handler()` → `record_from_handler()` → for each field write only when `can_change_*()` is true, else `blocked` → `$DB->update_record('messageinbound_handlers', …)`, returning `ok | changed | blocked`; drift reports a difference in `enabled`, `defaultexpiration`, `validateaddress` or a missing row. Call it from `moodle/local_ltuse/classes/siteconfig/applier.php` `run()` (after settings) and its drift from `moodle/local_ltuse/classes/siteconfig/inspector.php`; add one line to the applier's header list. Make T024 pass.
- [X] T031 [US1] In `moodle/local_ltuse/classes/admin/course_mentor_sync.php`, for `ltct:<slug>` courses only: on every sync run, call `digest_overrides::set()` with 0 for the mentor on each forum where they hold the synced Teacher role and for each synced mentee of a mentor on the same forums; in the removerole step, inside the same per-course lock and **before** `role_unassign()`, call `digest_overrides::remove()` for the mentor, and for a mentee who no longer has a mentor there while still enrolled. Enrolment, role and group outcomes stay as 008 has them. Make T025 pass with `admin_test.php` unchanged and green.
- [X] T032 [US1] Create `moodle/local_ltuse/classes/mentor_subscriptions.php`: `for_post(int $postid)` (find the author's mentors in the post's `ltct:<slug>` course through 008's records and sync state; `\mod_forum\subscriptions::subscribe_user_to_discussion()`; then, if the stored `forum_discussion_subs.preference` is later than the discussion's first post `created`, read from `forum_posts`, set it to that `created` by direct write, R10) and `existing_for_mentor(int $mentorid, int $courseid)` (raw read of `forum_discussions` and `forum_posts` for discussions their mentees started or posted in, same subscribe and fix). Return early for any course whose idnumber starts `ltct:site:`.
- [X] T033 [US1] Register `\mod_forum\event\discussion_created` and `\mod_forum\event\post_created` in `moodle/local_ltuse/db/events.php` with callbacks in `moodle/local_ltuse/classes/observer.php` that call `mentor_subscriptions::for_post()` inside `try`/`catch (\Throwable)` with `debugging()`, as the existing callbacks do. In `moodle/local_ltuse/classes/admin/course_mentor_sync.php` (after T031, same file), call `mentor_subscriptions::existing_for_mentor()` when sync adds a mentor in a delivery course. Make T026 pass.
- [ ] T034 [US1] (live) Raise the local_ltuse version per the Version rule (T033 changed `db/events.php`), then deploy. Quickstart US1 step 1 / **V1**: before apply, the Message handlers page lists exactly three handlers with the forum handler disabled; after apply it is enabled; a hand change is reported by drift. Record in Results.
- [ ] T035 [US1] (live) **V2, mailbox part** (quickstart US1 step 2), once spec 015 has provisioned the mailbox (T007 answered): confirm `<mailbox>+<code>@<domain>` is accepted with the local part's case and `+ / =` kept, and pickup runs every minute. Fill `messageinbound_mailbox`, `_domain`, `_host` in `moodle/site/settings/inbound-mail.yaml` from 015's values only if they are not personal or secret (otherwise `env:`), set `MOODLE_INBOUND_USER`/`MOODLE_INBOUND_PASS` in the operator's environment, never in the tree. Record pass/fail.
- [ ] T036 [US1] (live) Only after T035 passes: set `messageinbound_enabled: 1` in `moodle/site/settings/inbound-mail.yaml`, its `why` updated to cite V2 and the date; validate; apply on ltuse.net.
- [ ] T037 [US1] (live) **V4** (quickstart US1 steps 3, 3a, 8, 10): M, A1, A2 on the site digest default get override 0 on the course forum after sync, a learner with no mentor none; A3's own setting unchanged before and after M stops mentoring them; a digest has no Reply-To. Mentor subscribed to discussions existing at assignment: record M as A1's mentor only, have A2 open a discussion, then record M as A2's mentor and confirm M is subscribed to A2's discussion after sync. Then A2 starts a discussion with a 1 MB attachment ten times and M is subscribed to that discussion only and receives the opening post every time; A2 replies in a classmate's discussion and M is subscribed to it.
- [ ] T038 [US1] (live, needs T036) **V5** and the reply path (quickstart US1 steps 4–5): A1 posts from the app; M gets one email with a reply footer (record the time); M replies from their registered address; the reply appears in A1's app under M's full name (record the time) with a push or popup; control: with A1's override removed by hand, no push arrives.
- [ ] T039 [US1] (live, needs T036) **V2 round trip (b)–(e)** (quickstart US1 steps 6, 7, 10a): a reply from another address asks M to confirm and posts once confirmed; a reply to an email 8 or more days old asks for confirmation; replies from Gmail web, Gmail Android and Outlook (record whether the signature and quoted header are posted); a 2 MB phone photo; a signature with an image.
- [ ] T040 [US1] (live, needs T036) **SC-001** (quickstart US1 step 11): three rounds between M (email only, no login) and A1 (app only, one round written offline), no message lost. Record pass/fail.

**Checkpoint**: purpose 1 works in delivery courses. If 015 has no mailbox yet, US1 ships with
`messageinbound_enabled: 0` and the route is one-way email (notify, then log in); the spec says so,
and T038's reply part, T039 and T040 wait.

---

## Phase 4: User Story 2 — A cohort has its own standing space, outside any course (P1)

**Goal**: one space course per teaching cohort and per opted-in Area entry, named after the cohort,
two forums, members kept in step by `enrol_cohort`, reachable in one step from the landing page,
invisible to everyone else (FR-006–FR-009, FR-015's gate, FR-016, FR-017).

**Independent Test**: with test cohorts A and B, a member of A reaches A's space in one step on web
and app, reads and posts there, and cannot see, find or open B's space by browsing, searching or
direct link (quickstart US2).

**Ships with US3**: a space admits members once it has a cohort-mentor row; US3 enrols that mentor.
No real learner enters a space until both stories are live and verified (gates above). US2 is
independent of US1 in behaviour but not in files: its T043, T052, T059, T061 and T062 follow US1's
T023, T028, T033 and T030 (shared files, below), and T049's `mentor_subscriptions` exemption case
needs US1's T032.

### Governance drafts (gate every server apply of a space, and the commit of T051, T053, T060, T062)

- [ ] T041 [US2] Draft the `INTENT.md` decision under `## Decisions`, dated **2026-10-07**, citing Doug (Q3): "Community starts inside Moodle, per cohort": one site-declared cohort space course per teaching cohort and per organisation entry that opts in with `space: area` (`ltct:site:cohort:<key>`, visible category `ltct:cohort-spaces`); members by `enrol_cohort`, mentors by spec 008 cohort-mentor rows recorded by the site team; mentor route by forum email; spaces sit beside WhatsApp (Q15); a bolt-on only if spec 005's engagement trigger fires, recorded as its own decision. A second sentence: the site-wide all-accounts space is deferred (Q1), revisited after the engagement reviews. Edit `INTENT.md` only.
- [ ] T042 [US2] Draft the MINOR constitution amendment **2.1.1 → 2.2.0** in `.specify/memory/constitution.md`, citing 2026-10-07, with exactly the plan's three bullets: **II** adds `ltct:site:cohort:<key>` (a site-declared cohort space course) to the idnumber forms beside `ltct:<slug>` and `ltct:<slug>:<file number>`; **Platform & Delivery**: the organisation-only course stays the one mechanism for keeping a *delivery* course to one organisation, and a site-declared cohort space course, which carries no course content, is the one mechanism for keeping a community space to one cohort; **VII**: one sentence beside the organisation-only course, that a cohort space is not a special case either, it is a uniform variant made from one template, open to every teaching cohort and to any organisation entry that opts in. Change the version line (L302) to `**Version**: 2.2.0 | **Ratified**: 2026-09-30 | **Last Amended**: 2026-10-07` and add a `*2.2.0 — …*` history entry above `*2.1.1 —` in the existing style, naming spec 005 and Q3. No other wording changes.

### Tests for User Story 2

- [ ] T043 [US2] Add `class Community005(...)` to `tests/test_site_config.py` (after T023, same file) for contract community-yaml "Rules" and "Reserved names", each a `subTest`: tracked `community.yaml` accepted; payload `community: {space, shapes}` with no teaching cohort key, name or member; a derived idnumber not starting `ltct:site:cohort:`, without a second colon, or over 100 characters refused; one matching `^ltct:[^:]+$` or ending `:discussion` refused; `shapes` keys other than exactly `teaching` and `area` refused; `teaching.key` ≠ `cohort_id` or `area.key` ≠ `organisation_key` refused; a `teaching.cohorts.match` selecting `ltct:mentors`, a managers cohort or an `ltct:org:` cohort refused; the teaching shape with no `cohorts` key accepted and yielding 0 teaching spaces in the payload, while `cohorts: {}` or an empty `match` refused; `space.name` other than `cohort` refused; `forcesubscribe: forced` refused anywhere and `initial` refused in the area shape; digest values other than `0`, `1`, `none`, or keyed by an undeclared forum, refused, and area digests other than `none` refused; forum keys other than exactly `team`, `problems` refused; a `lockdiscussionafter` key refused; `mentor_overrides` granting any other capability, any permission but `allow`, refused; `space.role` missing from `roles.yaml`, without course context, or with `mod/forum:allowforcesubscribe`, `moodle/course:viewparticipants`, `moodle/user:viewdetails` or `moodle/site:sendmessage` set to prevent or prohibit, refused; any `groupmode` key refused; a `site_wide` key refused (Q1); `community.yaml` refused when `organisations.yaml` has no category with key `cohort-spaces`, when that category is hidden, and when its idnumber is not `ltct:cohort-spaces`; in `organisations.yaml`, `space: area` accepted on an entry, refused on `independent`, any other `space` value refused, and a second category with key `cohort-spaces` or a category key `org` refused; the summary line has the contract's exact shape.
- [ ] T044 [P] [US2] In `tests/test_publish_moodle.py`: a course folder slugged `site` or `cohort-spaces` is refused by `scripts/check_course_package.py` and by the publisher before any call; and, over a dry-run of an existing fixture course, no idnumber the publisher sends starts `ltct:site:` (FR-017).
- [ ] T045 [P] [US2] In `tests/test_ltct_admin.py`, course-mentors rows (synthetic emails): `ltct:site:cohort:7` and `ltct:site:cohort:42`, each with a cohort idnumber, accepted offline (whether that cohort's numeric id equals the key is known only to the server, so that refusal is `course_mentor_records`' and is tested in T050 (h); the CLI test asserts, with a mocked preview response, that the server's `space_cohort_mismatch` reason is shown as a refusal and its `expectedspacekey` is printed); `ltct:site:cohort:sil-americas` with `ltct:org:sil-americas` accepted; `ltct:site:cohort:sil-americas` with any other cohort refused; a space row with `learner_email` refused; `ltct:site:cohort:07`, `ltct:site:cohort:0` and `ltct:site:cohort:Sil` refused by the pattern `^ltct:site:cohort:(?:[1-9][0-9]*|[a-z][a-z0-9-]{0,29})$`; an intake row whose COURSE is a space refused; the preview never prints a cohort name.
- [ ] T046 [P] [US2] In `tests/admin_harness.php`, `course_mentor_rules`: an enrolment with the space member role id counts as an active learner when `facts()` passes that id as `studentroleid` (a space), and never when Student is passed (an `ltct:<slug>` course); a member on a disabled instance never counts (the gate order, R9).
- [ ] T047 [P] [US2] In `tests/learner_home_harness.php`, extend `learner_home_rules::onward()` cases with a fourth argument `cohorts` (`[{name, url}]`): two cohorts give two lines in order; an empty list gives no `cohorts` key; every existing 007 case, which passes three arguments, is unchanged and still passes.
- [ ] T048 [P] [US2] In `moodle/block_ltuse/tests/block_test.php`: a learner with an active enrolment in a space course sees one line "Your cohort: <the cohort's name>" linking to the space, in the web and app context; a learner in two spaces sees two lines; a suspended enrolment or a disabled instance gives no line; no line ever shows `ltct:site:cohort:` or a number from the key.
- [ ] T049 [US2] Create `moodle/local_ltuse/tests/siteconfig_cohortspaces_test.php` per contract site-config-community "Tests": idempotent apply for both shapes (teaching from a generated cohort matching the selector, area from a generated `ltct:org:fixture-area` cohort for an entry declaring `space: area` in the test declaration); with no teaching selector in the declaration, apply creates no teaching space and drift reports nothing for teaching cohorts; a generated cohort matching the teaching selector gets its space from the `cohort_created` observer with no apply, and, created under `$this->redirectEvents()` so no observer runs, from one execution of `\local_ltuse\task\course_mentor_reconcile` with no apply; `blocked: cohort_missing` with nothing created; `blocked: name_taken` with no code added; fullname and shortname equal the cohort's name and follow a rename (apply and `cohort_updated`); `forcesubscribe` unchanged on re-apply; Auto in teaching and Optional in area; groupmode 0, no groups; `enablecompletion` 0; exactly two forums (`<space>:team`, `<space>:problems`), no Announcements forum, no `lockdiscussionafter`, `maxattachments` 3, `maxbytes` 5242880; each forum's intro equals `community.yaml` `space.guidelines`; the pinned pointer discussion posted once; cohort instance created disabled with role `spacemember` and `customint2` 0, and left enabled by apply once enabled; the manual instance disabled and empty; no space for an entry without `space: area`; a deleted teaching cohort's space kept with its posts and reported `cohort deleted` by apply and drift; apply and drift output contain neither a teaching cohort's name nor its idnumber, and with one teaching cohort present drift (`classes/siteconfig/drift.php`) reports no `extra` line for it; exemptions: `learner_home` continue, `levels::course_counts`, the inspector's groupmode and discussion checks, `admin_list`, `access::may_enrol_into`, the pathway catalogue and `mentor_subscriptions` (needs US1's T032) all ignore an `ltct:site:cohort:*` course.
- [ ] T050 [US2] Add space cases to `moodle/local_ltuse/tests/course_mentor_sync_test.php` (after T025, same file). Fixture, built with generators and not through `cohortspaces::apply()`: `create_course()` with idnumber `ltct:site:cohort:<the generated cohort's id>`, a disabled `enrol_cohort` instance on that cohort, two forums with idnumbers `<space>:team` and `<space>:problems`, and a `spacemember` role made with `create_role('Space member', 'spacemember', '', 'student')` in `setUp`. Cases: (a) a teaching space starting with its cohort instance disabled and one cohort-mentor row ends, after two `sync_course()` runs, with the instance enabled and the members enrolled (the mentor's enrolment is asserted in T073); (b) with no row the instance stays disabled; (c) no one-course rows and no default-mentor fallback in a space (a member's default mentor is not enrolled, Q4); (d) no `ltct:mentorgroup:` group is made in a space; (e) every active member of a teaching space gets 0 on Problems and 1 on Team, recorded, whether or not the space has a mentor, and a member added later gets them; (f) an area space sets no override for anyone; (g) a member removed from the cohort raises no exception, keeps the override and its record, and on rejoin nothing new is written; (h) `course_mentor_records` refuses a row on `ltct:site:cohort:42` naming another cohort, and on `ltct:site:cohort:fixture-area` naming a cohort other than `ltct:org:fixture-area`, with reason `space_cohort_mismatch`; (i) `enrolment_rules::decide()` refuses `ltct:site:cohort:7` with `course_not_ltct`; (j) `local_ltuse_admin_preview_course_mentors` returns, per space row, `expectedspacekey` and, for a mismatched row, the `space_cohort_mismatch` reason, and no cohort name.

### Implementation for User Story 2

- [ ] T051 [P] [US2] After T041 and T042 are drafted: in `moodle/site/organisations.yaml`, add the category `key: cohort-spaces` (idnumber `ltct:cohort-spaces`, **visible**, `why` citing Q3 and that isolation is by enrolment, not category visibility, R7); add a header comment that an entry opts in to an Area space with the one field `space: area`, that `independent` never may, and that the reviewer of any change here confirms no spec 016 neutral entry declares it (round 2, item 22). Do not add `space: area` to any entry: `sil` holds all of SIL and does not opt in, and the Area entries arrive with 002.
- [ ] T052 [US2] In `scripts/site_config.py` (after T028, same file), before T053 creates the file: add `community.yaml` to `TOP_FILES` (an absent file is skipped); `_validate_community()` implementing every contract community-yaml rule (T043), including: community.yaml requires exactly one `organisations.yaml` category with key `cohort-spaces`, idnumber `ltct:cohort-spaces` and visible true; and a teaching shape with no `cohorts` key means "no teaching spaces", not an error (the state until spec 002 fixes the namespace), while `cohorts: {}` or an empty `match` is refused; `_validate_organisations()` (L968) accepts `space` as the one optional key beside `key` and `name`, value `area` only, refused on `independent`, and its "only key and name" comment is rewritten to cite the VII amendment bullet (T042); the reserved names table (slug `site`, `cohort-spaces`; category keys `org`, `cohort-spaces`) defined once as a module constant; `build_payload()` gains `community: {space, shapes}` with, for the area shape, only the organisation keys declaring `space: area`, and for the teaching shape no selector when `cohorts` is absent; `_summary()` gains `community: N spaces (teaching t, area a; created c, ok o, waiting w, blocked b, no moderator m, cohort deleted d), forums f`. Run validate.
- [ ] T053 [US2] After T041, T042, T051 and T052: create `moodle/site/community.yaml` exactly as contract community-yaml "Shape": `purpose`; `space` (`idnumber_prefix: "ltct:site:cohort:"`, `name: cohort`, `role: spacemember`, `mentor_overrides` with `moodle/course:manageactivities` `allow` and its `why`, `guidelines` text, the two `forums`); `shapes.teaching` (`cohorts.match` set to the namespace spec 002 fixed, per T006; if 002 has not fixed it, omit the `teaching` shape's `cohorts` block behind a comment "waits on spec 002 (plan, What cannot start yet)", which T052 accepts as "no teaching spaces"), `key: cohort_id`, `forcesubscribe: initial`, `mentor_digest {problems: 0, team: 1}`, `member_digest {problems: 0, team: 1}`; `shapes.area` (`cohorts.from_organisations: area`, `key: organisation_key`, `forcesubscribe: optional`, `mentor_digest: none`, `member_digest: none`). No `site_wide` key. The guidelines say: do not judge, translate or correct anyone's language data; remove posted quiz answers; to report a post, send its link to your mentor; an email reply is posted as written, signature included. Make T043 pass: `python -m pytest -q tests/test_site_config.py`.
- [ ] T054 [US2] After T052 (it imports T052's constant): in `scripts/check_course_package.py`, refuse a course whose slug is `site` or `cohort-spaces`, importing the reserved slugs from `scripts/site_config.py` (or, if that import drags in more than `pyyaml`, from a small shared constant, named in the commit) so the list exists once. If the publisher does not already stop on a `check_course_package.py` error before its first call, add the same refusal at slug resolution in `scripts/publish_moodle.py`. Make T044 pass.
- [ ] T055 [P] [US2] In `scripts/admin_files.py` `_check_course_mentors` (L368): also accept a course matching `^ltct:site:cohort:(?:[1-9][0-9]*|[a-z][a-z0-9-]{0,29})$` only with a `cohort` value (refused with `learner_email`) and, for an organisation key, only with `ltct:org:<key>`; leave `COURSE` (L69) and intake unchanged so an intake COURSE that is a space is still refused. In `scripts/ltct_admin.py`, the course-mentors preview prints, per space row, the server's `expectedspacekey` and renders a `space_cohort_mismatch` reason as a refusal (both returned by T057's preview; never a cohort name). Make T045 pass.
- [ ] T056 [US2] In `moodle/local_ltuse/classes/admin/course_mentor_records.php` `classify()`, accept an `ltct:site:cohort:<key>` course for cohort-mentor rows only, and only with the space's own cohort: teaching, the cohort's numeric id equals the key; area, the cohort idnumber is `ltct:org:<key>`; otherwise a new reason `space_cohort_mismatch` (add its string to `moodle/local_ltuse/lang/en/local_ltuse.php`, after T019). Add a method that computes a row's expected space key from the cohort it names (`ltct:site:cohort:<cohort id>` for a teaching cohort, `ltct:site:cohort:<key>` for `ltct:org:<key>`), returning a key, never a name. In `moodle/local_ltuse/classes/admin/enrolment_rules.php`, refuse `ltct:site:*` explicitly in `decide()` (L61) with `course_not_ltct`, with a comment citing R7.
- [ ] T057 [US2] After T056: in `moodle/local_ltuse/classes/external/admin_preview_course_mentors.php`, return per row an optional `expectedspacekey` (`PARAM_RAW`, `VALUE_OPTIONAL`, set only for a space row, computed by `course_mentor_records`, never a cohort name) and let the row's reason be `space_cohort_mismatch`. Its `execute_returns()` today reuses `admin_preview_suspension::rows_returns()`; extend a copy of that structure for this function only, so the other previews' returns are unchanged. Make T050 (j) pass.
- [ ] T058 [US2] In `moodle/local_ltuse/classes/admin/course_mentor_rules.php`, document (docblock) that `studentroleid` is the space member role in a space and Student elsewhere; no logic change unless T046 shows one is needed. Make T046 pass.
- [ ] T059 [US2] In `moodle/local_ltuse/classes/admin/course_mentor_sync.php` (after T033, same file): `is_ltct_course()` (L97) gains an explicit second kind, `ltct:site:cohort:*`, with a helper `is_space(string $idnumber): bool`; for a space, `facts()` (L315) passes the role id of `community.yaml`'s `space.role` (read from the site-config payload stored by apply, or by shortname `spacemember`) as `studentroleid`; precedence 2 (cohort rows) only, no one-course rows, no default-mentor fallback; `apply_course()` skips creategroups/add/remove in a space; the **mentor gate**: when a space has at least one cohort-mentor row and its cohort instance is disabled, enable it first with `enrol_plugin::update_status(ENROL_INSTANCE_ENABLED)`, never disable it; member overrides by shape through `digest_overrides::set()` (teaching: each active member 0 on `<space>:problems`, 1 on `<space>:team`; area: none). Make T050 pass with `admin_test.php` unchanged (its generator-built fixture needs no apply).
- [ ] T060 [US2] After T041 and T042 are drafted: create `moodle/local_ltuse/classes/siteconfig/cohortspaces.php` (`namespace local_ltuse\siteconfig`), modelled on `moodle/local_ltuse/classes/siteconfig/officehours.php` (`create_course` L210, `apply_enrolment` L287), implementing contract site-config-community "Apply" steps 1, 1a, 2, 3, 4 and 5 for each cohort matching a shape's selector, and nothing for the teaching shape when it has no selector. Quote data-model "Cohort space" in the docblock and honour it exactly: course idnumber "`ltct:site:cohort:<key>`" ("Colon form; matches no `ltct:<slug>` rule (R7). ≤ 100 characters."); fullname and shortname "both the cohort's name (above), read live", "Never "Cohort space: 42", an id or a code", and "If the name is already another course's short name, the space is `blocked: name_taken` and no code is added."; category `ltct:cohort-spaces`, `visible = 1`; groupmode 0, not forced; enrolment "one `enrol_cohort` instance: `customint1` = the cohort, `customint2` = 0 (no group chat, Q15), role `spacemember` (Q17)", "Found before adding; `add_instance` does not de-duplicate. Created **disabled**", "The default manual instance is disabled and left empty."; `create_course()` with "`newsitems` 0 (no Announcements forum), `showreports` 0" and `enablecompletion` 0 (contract Apply step 1); forums per data-model "Forums in a space" (`general`, `forcesubscribe` INITIALSUBSCRIBE / OPTIONAL "at creation only", tracking optional, "3, 5 MB total", no lock after), each forum's intro the declared `space.guidelines`, and one pinned pointer discussion posted at creation with `forum_add_discussion()` and `forum_discussion_pin()`. Public entry points `apply()`, `for_cohort(int $cohortid)` (one cohort, for T062's observer) and `reconcile()` (every matching cohort, for the reconcile task). Apply never enables the cohort instance and never disables an enabled one. Report `created | ok | changed | waiting: no_mentor | blocked: name_taken | blocked: cohort_missing | cohort deleted`; never delete a course, forum, post or enrolment, never make a space read-only (Q7). Output names a teaching space only by its numeric key (Principle III). The space-mentor override (step 1b) and the forum-setting and intro restore are added by T075.
- [ ] T061 [US2] In `moodle/local_ltuse/classes/siteconfig/applier.php` (after T030, same file), call `cohortspaces::apply()` after `apply_structure()` (L215, so the category and organisation cohorts exist), and in `moodle/local_ltuse/classes/siteconfig/inspector.php` (after T030, same file) add per-space drift per contract site-config-community "Drift" except the mentor-specific items T076 adds: missing course, missing forum, an extra forum or activity, a forum setting or intro that differs (read from the `forum` row by the module's instance id), a hand change of `forcesubscribe` (reported, not reverted), a `lockdiscussionafter` set by hand, `name differs` (printing neither name), enrol instance missing or with another role or group, `groupmode` ≠ 0, an enabled instance other than the cohort instance and the course-mentor `enrol_self` instance (fail; the latter is expected), any `ltct:mentorgroup:` group (fail), a space whose cohort no longer matches (extra; the space stays), `cohort deleted`; with no teaching selector, nothing is reported for teaching cohorts. Make the inspector's existing groupmode and discussion checks skip `ltct:site:cohort:*`. In `moodle/local_ltuse/classes/siteconfig/drift.php` `report_extra_owned()` (L157), which reports every cohort whose idnumber starts `ltct:` and is not declared in `organisations.yaml` and prints its `name`, skip cohorts matching `community.yaml`'s `teaching.cohorts.match`, so a teaching namespace under `ltct:` is never flagged and no teaching cohort's name is printed (Principle III). Make T049 pass except its `cohort_created`, `cohort_updated` and reconcile-task cases and its `mentor_subscriptions` exemption.
- [ ] T062 [US2] After T041 and T042 are drafted: register `\core\event\cohort_created` and `\core\event\cohort_updated` in `moodle/local_ltuse/db/events.php` (after T033, same file) with callbacks in `moodle/local_ltuse/classes/observer.php` that call `cohortspaces::for_cohort(int $cohortid)` for a teaching cohort matching the selector (create the space; keep the name in step), inside `try`/`catch`. Extend the existing `cohort_deleted` callback (L283) to report through `debugging()` only and change nothing in the space (the course, posts and mentor are kept, round 2). In `moodle/local_ltuse/classes/task/course_mentor_reconcile.php`, call `cohortspaces::reconcile()` so a space appears within one run of an ALTC making a matching cohort (V6). Make T049 pass (its `mentor_subscriptions` exemption needs US1's T032).
- [ ] T063 [US2] In `moodle/local_ltuse/classes/learner_home_rules.php` `onward()` (L187), add a fourth parameter `array $cohorts = []` (`[{name, url}]`), returned under `cohorts` only when non-empty, so every 007 call is unchanged; in `moodle/local_ltuse/classes/learner_home.php`, build it from the user's active enrolments in `ltct:site:cohort:*` courses on enabled instances, one per space, name = the space's fullname (the cohort's own name), per data-model "Learner-home route"; the mentor link (`onward.mentors`) is unchanged (Q22). Make T047 pass and every existing 007 harness case still pass.
- [ ] T064 [US2] In `moodle/block_ltuse/classes/output/home.php` `onward()` (L85), render each cohort as "Your cohort: <name>" (name `s()`ed, as the mentor name is), using a new lang string `onward:cohort` = `'Your cohort: {$a}'` in `moodle/block_ltuse/lang/en/block_ltuse.php`; add the line to `moodle/block_ltuse/templates/block.mustache` and `moodle/block_ltuse/templates/mobile_block.mustache` and their example-context doc comments. Run `python -m pytest -q tests/test_learner_wording.py` (it holds block strings to `cbc_wording`). Make T048 pass.
- [ ] T065 [US2] Per the Version rule, before T066's deploy (T062 changed `db/events.php`): raise `moodle/local_ltuse/version.php` to a new `YYYYMMDDXX` and its `site.yaml` pin; raise `moodle/block_ltuse/version.php` to a new `YYYYMMDDXX`, its `dependencies['local_ltuse']` to that new local_ltuse stamp (the first that carries T063), and the `block_ltuse` pin in `moodle/site/site.yaml` to match. Validate.

### Live checks for User Story 2 (need T006 (a)–(c), T041, T042)

- [ ] T066 [US2] (live) Deploy (local_ltuse and block_ltuse; versions raised at T065). Quickstart US2 step 1 / **V6**: create test cohorts A and B through 002's scripted action (names starting `test-`); run apply twice; a space appears for each, the second apply reports `waiting: no_mentor` for each; each has exactly the two declared forums, no lock setting, no enabled manual instance, the space-mentor override present (T075; if T075 is not deployed yet, check it at T080 and say so in Results), a disabled cohort instance; A1 cannot reach space A yet; `independent` has no space. **V6, the ALTC path**: then create test cohort C through 002's scripted action, run no apply, and confirm space C exists after at most one run of `\local_ltuse\task\course_mentor_reconcile` (or at once, through the `cohort_created` observer); record which and the time taken. Record.
- [ ] T067 [US2] (live) Quickstart US2 step 1a / **V6 names**: course list, landing page, breadcrumbs, a forum email subject and the app show cohort A's own name, nothing shows `ltct:site:cohort:`, a numeric id or a code; rename cohort A and after apply the space has the new name; apply and drift output show space A only by its numeric key, and drift prints no `extra` line for a teaching cohort. Step 7: inspector, drift, learner-home continue and identity path 4 ignore `ltct:site:cohort:*`.
- [ ] T068 [US2] (live) Record test mentor M for A and M2 for B with `scripts/ltct_admin.py` course-mentors (quickstart US2 step 1b): the preview prints each row's expected space key and no cohort name; after the first sync each cohort instance is enabled and members enrolled. (The mentor's enrolment on the next sync is checked in T080.) **V9 refusal**: try a course-mentors row on space A naming cohort B; confirm the preview refuses it with `space_cohort_mismatch` and nothing is applied. Then quickstart US2 steps 2 and 4: A1 reaches space A in one step on web and app, posts in both forums, sees A2 by full name in the participant list and messages A2; a person added to cohort A is enrolled and subscribed to both forums at once.
- [ ] T069 [US2] (live) **V7** (quickstart US2 steps 3, 5): from A1, try space B by browse, course search, forum search, recent activity, a digest, direct URL and app download; every attempt fails; a non-member finds no space in course search. Remove A2 from cohort A: suspended, no more mail, posts kept, next sync no exception and A2's overrides unchanged; re-add A2: access and mail return, Problems still per post; with every member's courses removed the space stays open.
- [ ] T070 [US2] (live) **V8** (quickstart US2 step 6): space A in the app's My courses; download, go offline, read a discussion, write a reply with a screenshot, and start a new discussion with a screenshot; reconnect: the reply, the new discussion and both screenshots sync; the app has no subscribe control. Record the app version and device.
- [ ] T071 [US2] (live) **V10** (quickstart "Identity"): an unprotected test learner's posts, participants page entry, email From name and app view show their full name; the spec 016 test learner at pseudonym level shows only the pseudonym and the no-reply address in raw headers and body; no display name, post, email or app view shows anyone's username field (the email address course staff see is the accepted exception).
- [ ] T072 [US2] (live) **V11** (quickstart "Member role and reports" and US4 step 3): a member reads, posts, sees the participants page and can message another member; no space appears in the progress, programme or pilots reports; as M (Course mentor) in a delivery course, exporting a forum, a discussion, a post and their own post is refused and "Reply privately" is still offered.

**Checkpoint**: spaces exist, are named after their cohort, appear for a new cohort without an
apply, open on their first row, and are isolated. Not yet for real learners (US3 enrols the
moderator).

---

## Phase 5: User Story 3 — A cohort's mentor is in the cohort's space (P1)

**Goal**: a recorded cohort mentor is enrolled as Course mentor, moderates (pin, lock, move,
remove), gets Problems per post and Team in the digest in a teaching space and no override in an
Area space; the space survives losing its last mentor and losing its cohort (FR-008, FR-008b, FR-015).

**Independent Test**: test mentor M, recorded once for cohort A, appears in space A, can post, pin
and lock, receives members' Problems posts by email and replies by email; M2, recorded for B, does
not appear (quickstart US3).

### Tests for User Story 3

- [ ] T073 [US3] Add mentor cases to `moodle/local_ltuse/tests/course_mentor_sync_test.php` (after T050, same file): (a) continuing T050 (a), after the second run the mentor is enrolled as `teacher` through the course-mentor `enrol_self` instance; (b) a mentor recorded for cohort B is not enrolled in space A; (c) teaching space: the mentor gets 0 on Problems and 1 on Team, recorded; area space: none for the mentor; (d) removing the last row removes the mentor and only the overrides sync wrote, with no exception, leaves the instance enabled, and members keep their overrides; (e) a teaching cohort deleted after its space opened: the members lose access, the mentor stays enrolled as `teacher` with their overrides and can read and post in both forums, across two sync runs and a run of `\local_ltuse\task\course_mentor_reconcile` (whose `remove_stray_roles()` skips the space because `is_ltct_course()` is true for it), the posts are kept, and the cohort-mentor row survives `remove_orphan_records()`; (f) `remove_orphan_records()` still deletes a space's row when its **course** is gone (the T005 removal path) or its **mentor** is deleted, and still deletes a delivery-course row whose cohort is gone.
- [ ] T074 [US3] Add to `moodle/local_ltuse/tests/siteconfig_cohortspaces_test.php` (after T049, same file): the space-mentor override `moodle/course:manageactivities` allow for `teacher` is present in each space's course context and in no delivery course; a mentor enrolled there has the lock control (`moodle/course:manageactivities`) in the space and not in a delivery course; a hand-edited forum setting (e.g. `maxbytes`) and a hand-edited forum intro are each reported by drift and restored by the next apply through `util::upsert_module()`, while a hand change of `forcesubscribe` is reported and not reverted; drift reports `no moderator` for an enabled cohort instance with no cohort-mentor row, and the override missing.

### Implementation for User Story 3

- [ ] T075 [US3] In `moodle/local_ltuse/classes/siteconfig/cohortspaces.php` (after T060, same file): step 1b, `assign_capability('moodle/course:manageactivities', CAP_ALLOW, <teacher role id>, <course context id>, true)` from `community.yaml` `space.mentor_overrides`; and on every apply, compare each forum's declared settings and intro (`space.guidelines`) with its `forum` row (raw read by the module's instance id) and restore any difference through `\local_ltuse\util::upsert_module()`, never writing the `forum` table, never changing `forcesubscribe` after creation.
- [ ] T076 [US3] In `moodle/local_ltuse/classes/siteconfig/inspector.php` (after T061, same file): per-space drift for the space-mentor override missing, and `no moderator` (an enabled cohort instance in a space with no cohort-mentor row: the space stays open, round 2). Make T074 pass.
- [ ] T077 [US3] In `moodle/local_ltuse/classes/admin/course_mentor_sync.php` (after T059, same file): mentor overrides by shape through `digest_overrides` (teaching: `mentor_digest` 0 on Problems, 1 on Team; area: none); removal of the mentor's recorded overrides in the removerole step before `role_unassign()`; never disable the cohort instance when the last row goes. **Cohort deleted**: for a space whose cohort no longer exists, make no change at all: keep the mentor enrolled with `teacher`, their overrides and their row; in `apply_course()`'s removerole step (reached from `sync_course()`), skip a space whose cohort no longer exists. `remove_stray_roles()` (L557) needs no change: it already skips every course for which `is_ltct_course()` is true, which T059 makes true for spaces, and T073 (e) asserts it across the reconcile task.
- [ ] T078 [US3] **Known gap 1**: in `remove_orphan_records()` (`moodle/local_ltuse/classes/admin/course_mentor_sync.php` L586, after T077, same file), keep `ltct:site:cohort:*` rows whose only orphan reason is a missing cohort: the query already left-joins `{course} c`; change the cohort clause to `(r.cohortid > 0 AND h.id IS NULL AND c.idnumber NOT LIKE 'ltct:site:cohort:%')` (use `$DB->sql_like()` with a NOT and escaped `_`/`%`), so a row whose course is gone (`c.id IS NULL`) or whose mentor is deleted (`m.id IS NULL`) is still deleted, and a delivery-course row whose cohort is gone still is. Update the docblock: "…or whose cohort is gone, except in a cohort space, whose recorded mentor takes over when its teaching cohort is deleted (spec 005, round 2, item 24); such a row goes when the space course is deleted." Make T073 (e) and (f) pass.
- [ ] T079 [US3] **Known gap 2** (T005's procedure, no code): add one paragraph to `moodle/site/README.md`, "Removing a space's mentor after its cohort is deleted": `ltct_admin.py` cannot name the row (it names cohorts by idnumber and the cohort is gone); the site team either keeps the mentor, or removes the space by the written procedure (`delete_course()` on the space), after which the next `course_mentor_reconcile` run's `remove_orphan_records()` deletes the row; a tool route is later work (Deferred).

### Live checks for User Story 3

- [ ] T080 [US3] (live) Deploy. Quickstart US3 step 1 / **V9 start**: before the space syncs, count the test delivery course's `ltct:mentorgroup:` groups and their members; after the second sync M is `teacher` in space A and can post, pin, lock, move and remove; no `ltct:mentorgroup:` group in space A; M2 is not in space A and a member's default mentor is not added (step 2); the test delivery course's `ltct:mentorgroup:` group and member counts are unchanged.
- [ ] T081 [US3] (live) Quickstart US3 steps 1a, 1b, 1c: M pins a guideline, locks a discussion by hand, moves one between the two forums, removes a post; A1 reports a post by sending M its permalink; no discussion locks automatically; M has no lock control in the delivery course; a forum-setting change by M is reported by drift and restored by apply; M, A1, A2 have 0 on Problems and 1 on Team in space A.
- [ ] T082 [US3] (live, needs T036) Quickstart "Mail by shape" / **SC-006** (teaching only): A1's Problems post reaches M and A2 as one answerable email each after the editing delay; A1's Team post only in the next digest; M's emailed reply to the Problems post reaches A1 and A2 per post or by push.
- [ ] T083 [US3] (live) Quickstart US3 step 3: remove M's record (the last for A): after sync M is removed, only the overrides sync wrote for M are gone, no exception; A1 and A2 keep their overrides and still reach and post in the space; drift reports space A `no moderator`; record M again to clear it.
- [ ] T084 [US3] (live) Quickstart US3 step 4, once every other check on space B is done: delete test cohort B in Moodle; B1 no longer reaches space B; space B and its posts are kept; M2 still reaches it, reads and posts in both forums, and keeps `teacher` and their overrides after two syncs and the reconcile task; apply and drift flag space B `cohort deleted`, by its numeric key only. Space B is then removed in T102.

**Checkpoint**: purposes 3(b) and 3(c) work for teaching spaces; the Area shape is proven by PHPUnit.

---

## Phase 6: User Story 4 — Fellow students in a course, and peer review (P2), delivered by spec 012

**Goal**: this spec's share only: the site-wide forum and email behaviour reaches 012's
`ltct:<slug>:discussion` with no change to its module (FR-010, FR-011), and 012 gets its four
re-plan inputs. (The check that 012's and 016's tests pass with the `teacher` change is T021, run
in Phase 2 before that change is deployed.)

**Independent Test**: in a test course, two learners and a course mentor post in
`ltct:<slug>:discussion`; a reply by email from a per-post email lands there (quickstart US4).

- [ ] T085 [US4] (live, needs T036) Quickstart US4 steps 1–2: with incoming mail on, a test learner replies to a per-post email from a test course's `ltct:<slug>:discussion` and the reply lands there; the course module's settings are unchanged (compare its settings page before and after). Record.
- [ ] T086 [P] [US4] Raise with spec 012 (do not edit 012's files): open a GitHub issue, or a comment on 012's tracking issue, titled "Spec 005 re-plan inputs for 012", carrying spec.md Dependencies' four inputs verbatim (scenario bank has no submission point; peers fixed at course start cannot use core workshop allocation; whether "identified" reverses 012 FR-012a and the `roles.yaml` override is Doug's question in 012's re-plan, with the round-2 names rule as input; an "each person posts one discussion" forum as a carrier), plus: the `teacher` role loses all four forum export capabilities (FR-011a), and 005 sets per-user digest overrides and per-discussion subscriptions on `ltct:<slug>:discussion` without touching the module. Link the issue in the PR description.

**Checkpoint**: 012's forum gets reply by email; 012 has its inputs.

---

## Phase 7: User Story 5 — The maintainer knows when the spaces have outgrown Moodle (P3)

**Goal**: a quarterly, aggregate-only review that states whether the trigger fired (FR-018, SC-005).

**Independent Test**: with test activity, `scripts/engagement_review.py` prints suppressed figures
and a verdict from the server, writes no file, and contains no user field (quickstart US5).

### Tests for User Story 5

- [ ] T087 [P] [US5] Create `tests/test_engagement_review.py` (no server; the web-service call mocked) per contract engagement-review-cli "Tests": each verdict (`RECONSIDER`, `NO CHANGE`, `SIMPLIFY-OR-RETIRE CHECK` at `--quarters-without-trigger 3` and not at 2); a suppressed `"<5"` value or a `null` rate gives "insufficient data"; obstacle 2 fires at 2 and not at 1; obstacle 3 prints "ask partners"; the base condition's 15% and the two-quarter growth rule; obstacle 1's 30% after 7 days; `--out` refused as an unknown argument (exit 2); a run writes no file (temp cwd stays empty); exit 3 on a server or token error; `MOODLE_ADMIN_TOKEN`, never `MOODLE_TOKEN`, is read. Wire it into `.github/workflows/site-config.yml` in the same edit: add `'tests/test_engagement_review.py'` and `'scripts/engagement_review.py'` to both `push.paths` and `pull_request.paths`, and append the test file to the pytest line (L95).
- [ ] T088 [P] [US5] Create `moodle/local_ltuse/tests/community_engagement_test.php` per contract engagement-ws "Tests": suppression at 4 (`"<5"`) and 5 (`5`); admin, a `webservice` account and guest excluded; an unended quarter and `2027Q5` refused with `invalid_parameter_exception`; the asker's own reply is not an answer, a mentor's reply is; an account with no Student or `spacemember` enrolment is not an active learner; a mentor's posts are counted in neither `posters` nor `posts`, a quarter where only mentors posted gives `posters` 0, including a mentor who also holds a `spacemember` enrolment in an Area space and a Student enrolment in another course, whose own post as a learner in that other course counts; with the standard log store disabled, the call throws `logstoredisabled` from component `local_ltuse`; the return structure has no user field.

### Implementation for User Story 5

- [ ] T089 [US5] Create `moodle/local_ltuse/classes/external/community_engagement.php` (`local_ltuse\external\community_engagement`, `type` read, system context, `require_capability('local/ltuse:administer')`), returning exactly the contract engagement-ws "Returns" shape. Implement data-model "Engagement review figures" verbatim: `active_learners` "distinct learners with any logged event in the quarter … a learner is an account holding a Student or `spacemember` enrolment; excluding admin, publisher, guest"; `posters` "distinct **learners** … who posted in a cohort space or course forum; a post counts only if its author holds no `teacher` role in that course"; `posts` for the quarter and the two before; `unanswered_rate` "share of discussions opened in Problems forums with no reply by another user, a mentor included, within 7 days". "Values 1–4 return `"<5"`; rates with a small numerator or denominator return not-computed. Site-wide only." Quarter bounds in the site timezone from `^\d{4}Q[1-4]$`; `throw new moodle_exception('logstoredisabled', 'local_ltuse')` when the standard log store is off. Never return a userid, name or message. Make T088 pass.
- [ ] T090 [US5] Register `local_ltuse_community_engagement` in `moodle/local_ltuse/db/services.php` on the existing `ltuse_admin` service (Q21), no new capability (confirm `local/ltuse:administer` in `moodle/local_ltuse/db/access.php` is reused, no edit), and add to `moodle/local_ltuse/lang/en/local_ltuse.php` (after T056, same file) its description string and the `logstoredisabled` error string.
- [ ] T091 [US5] Create `scripts/engagement_review.py` per contract engagement-review-cli: arguments `--quarter`, `--asked`, `--moved-towards`, `--returned`, `--quarters-without-trigger` (default 0), no `--out`; `MOODLE_URL` and `MOODLE_ADMIN_TOKEN` from the environment, reusing `scripts/moodle_client.py`'s REST call; prints the figures, the base condition, one line per obstacle and the verdict to stdout only; exit codes 0, 2, 3; writes no file anywhere. Make T087 pass.
- [ ] T092 [US5] (live) Raise the local_ltuse version per the Version rule (T090 changed `db/services.php`), then deploy. Quickstart US5 / **V12**: run `python scripts/engagement_review.py --quarter <last ended> --asked 3 --moved-towards 1 --returned 0 --quarters-without-trigger 0` with the operator's own token; figures suppressed, a verdict printed; posts made only by test mentors do not count as posters; no file written, `--out` refused; record the run time; `php admin/cli/cfg.php --component=logstore_standard --name=loglifetime` prints 365 and the enabled log stores include `logstore_standard`; an app-only test user is counted as active.

**Checkpoint**: SC-005 can be measured. The first real review starts once cohort spaces have been
open to live learners for two full quarters (spec, engagement signal); its outcome, when the
trigger fires, is recorded as an `INTENT.md` decision by the maintainer, and any bolt-on is its own
spec.

---

## Deferred (no build phase)

- **User Story 6 — the site-wide space for every account** (FR-012–FR-014): deferred by Doug on
  2026-10-07 (Q1). No task builds `ltct:site:community`, its cohort, topic forums, a `news` forum or
  the `onward.community` slot, and validate refuses a `site_wide` key (T043). Revisited after the
  engagement reviews (US5); if taken up, it is a new tasks list on this mechanism (research R16).
- A custom inbound handler so Moodle messages can be answered by email (Q19): later work.
- ALTCs recording their own cohorts' mentors, and teachers creating teaching cohorts: spec 017 (T099).
- A tool route for removing a space's mentor row after its cohort is deleted (a `cohort: deleted`
  remove mode in `ltct_admin.py`, `scripts/admin_files.py` and `course_mentor_records`, with a new
  refusal reason): beyond the plan; until then the written procedure applies (T005, T079). Raise it
  with Doug only if the site team needs it.

---

## Phase 8: Polish & cross-cutting

- [ ] T093 [P] Update `moodle/local_ltuse/README.md` (Principle XI): the direct-writes list gains `messageinbound_handlers` (T030, no core API; core's own admin page does the same) and `forum_discussion_subs.preference` (T032, the R10 race fix); the raw-reads list gains `forum_digests` (T018, the existing-choice check), `forum_discussions` and `forum_posts` (T032 and the observer; the first post's `created`), `forum` by id (T061, T075: space forum settings and intros, apply and drift, restored through `update_moduleinfo()`), and the engagement reads (T089: log store, `forum_discussions`, `forum_posts`); the table list gains `local_ltuse_digest_override`; the components list gains `siteconfig\cohortspaces`, `siteconfig\inbound`, `admin\digest_overrides`, `mentor_subscriptions`, `trackforums`, `cli/trackforums_existing.php`, `external\community_engagement`; the `course`/`cohort` row (L867) notes the space rows and the preview's `expectedspacekey`.
- [ ] T094 [P] Update `moodle/site/README.md` (after T079, same file): file-table rows for `community.yaml`, `inbound-mail.yaml`, `settings/inbound-mail.yaml`, `settings/forums.yaml`, `settings/logging.yaml`; a `## Cohort spaces` section (two shapes, opting in with `space: area`, the reviewer's neutral-entry check, the mentor gate, `waiting: no_mentor`, `no moderator`, `cohort deleted`, that apply never deletes a space, and the written removal procedure for test spaces in T102's order); `## Incoming mail` (enabled 0 until 015 and V2, the `MOODLE_INBOUND_*` variables, never in the tree); "Expected differences" gains the inbound settings while `MOODLE_INBOUND_*` are unset in a shell; the deploy note that a deploy changing `db/` needs a version bump and `admin/cli/purge_caches.php`.
- [ ] T095 [P] Update `CLAUDE.md` "Delivery: Moodle": cohort spaces (`ltct:site:cohort:<key>`, never addressed by the publisher, named after the cohort, members by `enrol_cohort`, mentors by 008 cohort-mentor rows recorded by the site team), and reply by email (forum only; digests carry no reply address; the per-forum overrides); add `engagement_review.py` to "Maintainer scripts".
- [ ] T096 [P] Update `moodle/REQUIREMENTS.md` in this PR (constitution X): rows **#9** and **#20** to **Built ([spec 005](../specs/005-community-space/spec.md)), <date>; not yet verified on the instance.** with one sentence each from spec "Requirements Traceability" (a standing space per teaching cohort and per opted-in Area entry; site-wide space deferred, Q1; topic forums only in the deferred site-wide space), replacing the "Weak point" and "Same answer as #9" text; **#10** adds the out-of-course part (peer talk and messaging in cohort spaces, Q6) beside 012's; **#11** adds the mentor route (per-post email, reply by email, landing as a forum post; messages stay in-app); **#25** adds the automatic per-forum overrides respecting personal choices, read tracking for new and existing accounts, and incoming mail processing; **#19** records that messages cannot be answered by email (forum-based route; a message handler is later work, Q19). Row #26 unchanged (relied on). Each states what is still pending (the V-checks not yet passed, SC-003, SC-004, and SC-002's and SC-006's area half, PHPUnit-only until T104) and claims no live criterion not recorded in quickstart Results.
- [ ] T097 [P] Raise with spec 016 (do not edit 016's files): its procedure for placing someone under a neutral entry must say that the entry never declares `space: area` (no neutral marker goes in the repo; the review check of round 2 item 22 rests on it), and the `teacher` export change runs with 016's tests (T021).
- [ ] T098 [P] Raise with spec 003 (do not edit 003's files): mentor onboarding gains a note to use the forum for anything a mentor wants to answer by email (the landing-page mentor link opens Moodle messaging, Q22), and mentor guidance that an email reply is posted as written, signature and quoted header included; mentors moderate and lock in their space, handle reported permalinks, and must not delete a forum (its posts cannot be restored); a protected mentor should not use the email route or should reply without a signature (R11).
- [ ] T099 [P] Raise with spec 017 (not yet written; open a placeholder issue): teachers creating and filling teaching cohorts (Q2), and ALTCs recording their own cohorts' mentors (round 2, item 11).
- [ ] T100 [P] Raise with spec 008 (coordinate; do not edit 008's files): `course_mentor_sync`, `course_mentor_records`, `admin_preview_course_mentors` and `admin_files.py` gain the space kind (R9, T056, T057, T059, T077, T078); `admin_test.php` passes unchanged; plan decision 8's reserved rule row for a teaching cohort. Note it in 008's tracking issue.
- [ ] T101 Run the full local gate before merge: `python -m pytest -q tests/`; every `php tests/*_harness.php`; `python scripts/site_config.py validate`; `python scripts/check_course_package.py`; `python scripts/quiz_parse.py --check-all`; `python scripts/gen_coverage.py` (no diff); `python scripts/check_competency_descriptors.py`; `python scripts/publish_moodle.py --slug paratext-quotation-rules --dry-run`; `php -l` on every changed PHP file; `git diff --name-only main -- modules/` empty; `git grep -n "MOODLE_INBOUND_PASS=\|hostpass: [^e]" -- moodle/site` finds no literal (scoped so T023's refusal fixtures in `tests/` and contract examples in `specs/` do not match); plugin CI green for local_ltuse and block_ltuse, including `moodle-plugin-ci savepoints`. Confirm each of `moodle/local_ltuse/version.php` and `moodle/block_ltuse/version.php` equals its `site.yaml` pin and is ≥ the version live on ltuse.net, and block_ltuse's `dependencies['local_ltuse']` is ≤ the local_ltuse version.
- [ ] T102 (live) After the checks, remove every test space in this order (the written procedure, as T005 rewrote quickstart "Where to run"): for each Moodle cohort whose name starts `test-` (A, C, and B, whose cohort T084 already deleted), `delete_course()` its space `ltct:site:cohort:<cohort id>`, then remove the cohort if it still exists; then, after one run of `\local_ltuse\task\course_mentor_reconcile`, confirm `remove_orphan_records()` has dropped the test cohort-mentor rows (count only). No `ltct_admin.py` step: it cannot name a row whose cohort is gone. Drift then reports no test space and no `no moderator` or `cohort deleted` space. Record counts only.
- [ ] T103 (live, post-merge) **SC-003**: once the PR with T041/T042 has merged and V2 (T035, T039), V4 (T037), V5 (T038), SC-001 (T040), V6 (T066, T067), V7 (T069), V8 (T070), V9 (T068, T080–T084), V10 (T071) and V11 (T072) have passed, 2–3 real partner learners and at least one real mentor use a cohort space and the mentor route without help. If spec 015 still has no mailbox, V2 and the reply-by-email parts of T038 and T040 are not run, and SC-003 checks the mentor route as one-way only (per-post email, then log in to reply); say so in Results. Record findings de-identified (no name, email, organisation key or region) in `specs/005-community-space/quickstart.md` Results, and move the REQUIREMENTS.md rows from built to verified/done only on that evidence.
- [ ] T104 (later, tracking) **Area shape, later** (quickstart): once a real organisation entry has opted in with `space: area`, 002 has applied it, and the site team has recorded its first real mentor, run the checks on that real space, adding no test account and deleting nothing, recording counts only. It closes these area-shape items: **SC-002** "under the cohort's own name" (its name is the entry's `name`) and one-step reach (a counts-only check by the site team, as themselves: the number of members with an active enrolment on the enabled cohort instance equals the cohort's member count, so each gets the one "Your cohort" line T048/T063 render for an active enrolment on an enabled instance); **SC-006** area (exactly two Optional forums; cohort instance enabled; no per-forum override for the mentor or any member; a member added is subscribed to neither forum). Record in Results that the shape-agnostic parts of SC-002, 0 cross-cohort visibility and the landing-page line, rest on T048, T049 and V7 (T069) on the teaching shape, and that until this task SC-002's and SC-006's area half is PHPUnit-only.
- [ ] T105 (later, tracking) **SC-004**: on a freshly rebuilt test server (spec 015), apply alone reproduces spaces, forums, the space-mentor override, the handler row, the roles and the site-wide settings; mentor rows are re-created through `ltct_admin.py`. Raised in T007's issue; recorded when 015 runs it.

---

## Dependencies & Execution Order

### Phase dependencies

- **Setup (Phase 1)**: T001–T005, T007 and T008 independent. T007 and T008 go out first, because later tasks wait on their answers: T035 after T007 is answered (the mailbox); T006 (b)/(c) after T008 is answered (namespace, scripted action). T006 blocks US2's live tasks.
- **Foundational (Phase 2)**: after T001. Tests T009–T011 first; then T012–T015 in parallel; T016 after T009 and T012–T015; T017 → T018 → T019; T020 after T011; T021 after T015 and before T022. T022 (live) last. Blocks every story (T018 is the only override writer).
- **US1 (Phase 3)**: after Phase 2. T028 before T029 (TOP_FILES first); T034 after a version bump; T036 (live) after T035; T038, T039, T040 after T036.
- **US2 (Phase 4)**: after Phase 2. T041 and T042 drafted before T051, T053, T060 and T062 are committed, and before any deploy once `community.yaml` exists (Deploy step 0). T052 before T053; T054 after T052; T056 → T057 → T055's server half. Shared files with US1: T043 after T023, T052 after T028, T059 after T033, T061 after T030, T062 after T033; T049's `mentor_subscriptions` case after T032. Live tasks need T006 (a)–(c), T041, T042 and T065's version bump.
- **US3 (Phase 5)**: after US2's T059, T060, T061 (same files, extended). Live tasks after T068.
- **US4 (Phase 6)**: T085 after T036; T086 any time.
- **US5 (Phase 7)**: after Phase 2 (T017 version). Independent of US1–US4 at code level apart from `lang/en/local_ltuse.php` (T090 after T056); meaningful figures need spaces (US2). T092 after its version bump.
- **Polish (Phase 8)**: T093–T100 after the stories they document; T101 last before merge; T102 after every live check; T103–T105 after merge.

### Shared files (edit sequentially, in this order)

- `tests/test_site_config.py`: T009 → T023 → T043.
- `moodle/local_ltuse/tests/course_mentor_sync_test.php`: T010 → T025 → T050 → T073.
- `moodle/local_ltuse/tests/siteconfig_cohortspaces_test.php`: T049 → T074.
- `moodle/local_ltuse/classes/admin/course_mentor_sync.php`: T031 → T033 → T059 → T077 → T078.
- `moodle/local_ltuse/classes/admin/course_mentor_records.php`: T056 (then read by T057).
- `moodle/local_ltuse/classes/siteconfig/cohortspaces.php`: T060 → T075.
- `moodle/local_ltuse/classes/siteconfig/inspector.php`: T030 → T061 → T076.
- `moodle/local_ltuse/classes/siteconfig/applier.php`: T030 → T061.
- `moodle/local_ltuse/classes/siteconfig/drift.php`: T061.
- `moodle/local_ltuse/db/events.php` and `classes/observer.php`: T033 → T062.
- `scripts/site_config.py`: T016 → T028 → T052.
- `scripts/admin_files.py` and `scripts/ltct_admin.py`: T055. `tests/test_ltct_admin.py`: T045.
- `moodle/local_ltuse/lang/en/local_ltuse.php`: T019 → T056 → T090.
- `moodle/local_ltuse/version.php` and `moodle/site/site.yaml`: T017 → T034 → T065 → T092.
- `moodle/site/README.md`: T079 → T094.
- `specs/005-community-space/quickstart.md`: T004 (Results) → T005 ("Where to run"); live tasks append to Results in run order.

### Story dependency summary

```text
Setup (T001–T008; T007, T008 raised first) ──► Foundational (defaults, roles, override table + helper,
                                                 012/016 role tests, read tracking)
                                     │
        ┌────────────────────────────┼────────────────────────────┬──────────────────┐
        ▼                            ▼                            ▼                  ▼
  US1 mentor route (MVP)      US2 cohort spaces ──► US3 space mentors        US5 engagement review
  (V1–V5, SC-001;             (T041/T042 drafted;    (V9, SC-006;
   enable inbound after V2;    002 gate; shares      orphan skip; T005
   T035 waits on 015)          files with US1;       procedure)
        │                      V6–V8, V10, V11)
        └──► US4 (012's forum: reply by email, 012 inputs)
                         all ──► Polish (T093–T101) ──► T102 ──► merge ──► T103 SC-003, T104, T105
```

---

## Parallel examples

### Setup

```text
Task: "T001 API confirmations in specs/005-community-space/research.md"
Task: "T002 Path corrections in specs/005-community-space/plan.md"
Task: "T003 Read constitution and INTENT.md"
Task: "T007 Raise spec 015 operator tasks"
Task: "T008 Raise spec 002 constraint and organisation field"
```

### Foundational

```text
Task: "T009 SiteForum005 in tests/test_site_config.py"
Task: "T010 Create moodle/local_ltuse/tests/course_mentor_sync_test.php (helper cases)"
Task: "T011 Create moodle/local_ltuse/tests/trackforums_test.php"
# then:
Task: "T012 Create moodle/site/settings/forums.yaml"
Task: "T013 Create moodle/site/settings/logging.yaml"
Task: "T014 Extend moodle/site/settings/notifications.yaml"
```

### User Story 1

```text
Task: "T024 Create moodle/local_ltuse/tests/siteconfig_inbound_test.php"
Task: "T026 Create moodle/local_ltuse/tests/mentor_subscriptions_test.php"
Task: "T027 Create moodle/site/settings/inbound-mail.yaml"
# T023 (shared test file) and T028 → T029 run in sequence beside these.
```

### User Story 2

```text
Task: "T044 Reserved slugs in tests/test_publish_moodle.py"
Task: "T045 Space rows in tests/test_ltct_admin.py"
Task: "T046 spacemember counting in tests/admin_harness.php"
Task: "T047 cohorts onward cases in tests/learner_home_harness.php"
Task: "T048 Cohort line in moodle/block_ltuse/tests/block_test.php"
# then, on separate files (after T041/T042 are drafted):
Task: "T051 cohort-spaces category in moodle/site/organisations.yaml"
Task: "T055 Space rows in scripts/admin_files.py and scripts/ltct_admin.py"
# T043 (after T023), T052 → T053 → T054 run in sequence beside these.
```

### User Story 3

```text
Task: "T073 Mentor cases in moodle/local_ltuse/tests/course_mentor_sync_test.php"
Task: "T074 Override and restore cases in moodle/local_ltuse/tests/siteconfig_cohortspaces_test.php"
# then:
Task: "T075 Step 1b and restore in moodle/local_ltuse/classes/siteconfig/cohortspaces.php"
Task: "T076 no moderator drift in moodle/local_ltuse/classes/siteconfig/inspector.php"
Task: "T079 Cohort-deleted removal paragraph in moodle/site/README.md"
```

### User Story 5

```text
Task: "T087 Create tests/test_engagement_review.py"
Task: "T088 Create moodle/local_ltuse/tests/community_engagement_test.php"
```

### Polish

```text
Task: "T093 moodle/local_ltuse/README.md"
Task: "T095 CLAUDE.md"
Task: "T096 moodle/REQUIREMENTS.md"
Task: "T097, T098, T099, T100 follow-up issues for 016, 003, 017, 008"
```

---

## Implementation Strategy

### MVP first (Setup + Foundational + US1)

1. Setup: raise T007 (015's mailbox) and T008 (002's namespace and scripted action) first, then
   APIs confirmed, paths corrected, gates known, the cohort-deleted procedure written.
2. Foundational: site-wide forum defaults, `teacher` export removed (012's and 016's tests checked
   first), `spacemember`, the override record and helper, read tracking on for everyone (plan
   build order 1).
3. US1: digest overrides and the mentor subscription observer in delivery courses (build order 2),
   the incoming-mail declaration and handler kind (build order 3), enabled after V2.
4. **Stop and validate**: SC-001. If spec 015 has no mailbox yet, the MVP is still useful: mentors
   get each mentee's post individually and the mentee is pushed the reply; the reply itself needs a
   login until T036.

### Incremental delivery

1. MVP → purpose 1 in delivery courses.
2. US2 + US3 together (build order 4): T041/T042 drafted first; then spaces, the area shape by
   PHPUnit, teaching spaces once 002 defines the teaching cohort. Real learners only after merge
   and the gate list above.
3. US2's learner-home line (build order 5) ships with US2.
4. US4 → reply by email in 012's course forum, and 012's inputs.
5. US5 → the engagement review (build order 6).
6. Polish → REQUIREMENTS.md rows built in this PR; live checks move them to verified, and SC-003
   to done.

### Notes

- Look up every Moodle API in Context7 (`/websites/moodledev_io_5_2_apis`, `/moodle/moodle`) and then
  in `MOODLE_502_STABLE` source before writing PHP (T001). We run open-source core: no Workplace,
  MoodleCloud or paid-app feature. Never edit vendored code (constitution XI).
- GitDoc pushes the branch within minutes: no secret, mailbox address, name or live count ever
  touches the tree, even briefly.
- Commit after each task or logical group.
