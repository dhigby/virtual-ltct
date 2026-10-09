---

description: "Task list for 007 simple learner experience"
---

# Tasks: Simple Learner Experience

**Input**: Design documents from `/specs/007-learner-experience/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Included. The plan asks for:
- pytest in `tests/`: payload `time_text`, the publisher's summary send, and `site_config.py`
  validate rules for the dashboard and the new settings file;
- PHP harnesses in `tests/*_harness.php`, in the existing Moodle-free style (`define('MOODLE_INTERNAL', 1)`,
  a local `check()`, the class file required directly, exit 1 on failure), for the continue
  rule, the next-`cm` rule and the dashboard applier's plan;
- a wording test that holds the new lang strings to `scripts/cbc_wording.py`.

The wording test is a **pytest** (`tests/test_learner_wording.py`), in the
`tests/test_pathway_wording.py` style, not PHPUnit. `cbc_wording.py` has no PHP-readable
list, and the repo's precedent parses the PHP lang file from Python. PHPUnit tests
(`advanced_testcase`, synthetic data only, never run on the shared host) cover what needs a
real Moodle: `learner_home` against generated courses, the block's output, the dashboard
applier's complete and reset paths, and `update_sections`' skip rules. The plugin CI gains a
`block_ltuse` job so the block's tests run (T002).

Each test file is wired into CI by the task that creates it, never ahead of it, because GitDoc
pushes the feature branch about every five minutes and a workflow naming a missing file goes red.

**Organization**: One phase per user story, after a short foundation that both views and the
hook need. US1 (first login, continue, empty state, trimmed dashboard) is the MVP. US2 (a
course reads as lessons, Next button, estimated times) is independent of US1 at code level,
apart from the shared files named under Dependencies (`learner_home_rules.php`, its harness,
`styles.css` and the wording test). US3 (the app) and US4 (onward routes) **extend** the block
US1 builds: they are testable only after US1's T029–T031, not straight after Foundational.

**One merge**: every included story merges together. `moodle/local_ltuse/version.php`, the
`site.yaml` pin, the block's dependency on local_ltuse and `mobilecssurl`'s `?v=` are each
changed **once** for that merge, in the Polish phase (T062, T066), and T071 and T073 run once
for it. If a later story ships in a separate merge, that merge repeats T062, T066 and T073 with
the next `YYYYMMDDXX` and the next `?v=`.

**Plan decisions**: Decision 4 is **decided** (SIL Blue `#005CB9`, translucent SIL Blue tints,
R11). Decisions 1 (build Continue now), 2 (prevent `moodle/my:manageblocks` for `user` and
reset leftover personal dashboards), 3 (the section summary is a publisher change) and 5
(`supportemail = env:MOODLE_SUPPORT_EMAIL`) proceed on their recorded defaults. The PR
description lists 1, 2, 3 and 5 for the maintainer. If he reads constitution X as covering
decision 3, T034, T035, T037 and T039–T042 (the time display) wait for his approval of
[contracts/update-sections.md](contracts/update-sections.md); nothing else in US2 depends on
them.

**Live checks**: the development checkout has no server or Android device, and no task here
contacts the Moodle server before merge. Tasks marked **(live, post-merge)** run quickstart
V1–V9 on the temporary 5.2.3+ instance and a device, with test accounts only (`test-007-*`).
Only pass/fail, the device and the app version are committed from them. They move
REQUIREMENTS.md row #13 from **built** to **verified**, and the pilot (V9) makes it done
(FR-014). Everything else must pass before merge.

**Never**: edit anything under `modules/`; write a token, a support address, a learner's name,
email or a real count into the repo (Principle III).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: US1, US2, US3 or US4, from spec.md

---

## Phase 1: Setup

**Purpose**: CI triggers for the new plugin, and the design-doc corrections the code below
relies on. Each correction resolves a gap found while preparing these tasks.

- [X] T001 Extend `.github/workflows/site-config.yml`: in **both** the `push.paths` and `pull_request.paths` lists, add `'moodle/block_ltuse/**'` (the `site.yaml` path pin reads `moodle/block_ltuse/version.php`, and the wording test reads its lang file). Nothing else here: each new test file is wired in by the task that creates it (T009, T014, T016).
- [X] T002 [P] Add a `block_ltuse` job to `.github/workflows/local-ltuse-plugin-ci.yml`, beside the existing `test` job. Copy the existing job (moodle-plugin-ci `^4`, PHP 8.3, pgsql 16, `MOODLE_502_STABLE`, `MUSTACHE_IGNORE_NAMES: 'mobile_*.mustache'`). Install with `moodle-plugin-ci install --plugin ./repo/moodle/block_ltuse` plus local_ltuse as an extra plugin, because the block depends on it. Before writing, look up `install`'s extra-plugins option for moodle-plugin-ci `^4` (ctx7, then the moodle-plugin-ci source). It takes a **directory of plugin directories**, so copy `moodle/local_ltuse` to `extra/local_ltuse` in a step first if needed. Run phplint, validate, savepoints, mustache and `phpunit --fail-on-warning`; phpcs and phpdoc are `continue-on-error`, as in the existing job. Add `'moodle/block_ltuse/**'` to both trigger path lists. Path filters are workflow-level, so both jobs run on a change to either plugin. That is intended, because the block depends on local_ltuse.
- [X] T003 [P] Amend `specs/007-learner-experience/contracts/dashboard-declaration.md` to settle what the code below implements:
  - (a) **Payload shape**: `dashboard` stays a list of `{block, region, weight?}`, with `weight` present only when declared, so `inspector::entries()` keeps working. Two sibling keys follow it in the payload: `dashboard_complete` (bool, default `false`) and `dashboard_personal` (`"reset"` · `"keep"`, default `"keep"`).
  - (b) A block is valid if it is a core block (`STANDARD['block']`) **or** `block_<name>` is pinned in `site.yaml`.
  - (c) `personal_dashboards: reset` requires the `user` entry in `roles.yaml` to declare `moodle/my:manageblocks` as exactly `prevent`. `prohibit` is refused too, because it binds the site team (R4).
  - (d) Before resetting, `apply` reads the **live** permission of `moodle/my:manageblocks` for the `user` role at system context, and refuses the reset (a `fail` line) unless it is `CAP_PREVENT`. Roles are applied earlier in the same run, and the dashboard step re-plans after them, so a first apply passes.
  - (e) The `complete` delete touches only block instances with `parentcontextid` = system context, `pagetypepattern` = `'my-index'` and `subpagepattern` = the default `my_pages` id. It is not limited by region, so blocks in `side-post` are removed too.
  - (f) The personal-dashboard count is `my_pages` rows with `userid IS NOT NULL`, `name = '__default'` and `private = 1`. The per-user `__courses` rows are not counted.
  - (g) `ltuse` must be declared at region `content`, weight `0` (data-model §1).
  - (h) Drift item names: `dashboard <block>` (missing / extra), `dashboard <block> weight` (changed), `dashboard personal dashboards` (declared `reset`, live `N`).
  - (i) A block is added at its declared weight (0 when undeclared), so a second apply finds nothing to do.
  - (j) The region of a live instance is not compared (011's `present()`): a declared block live in another region is neither added, deleted nor reweighted.
  - (k) `reset` with a count of 0 plans nothing and reports no line, whether or not editing is prevented.
- [X] T004 [P] Amend `specs/007-learner-experience/contracts/update-sections.md`:
  - `time_text` is "the header line alone (the whole source line holding the `TIME_RE` match, trailing text included), rendered with `moodle_payload.render()`, not wrapped". It is not "exactly as the lesson renders it": in `coretech-computer-hardware/01..04-module-N.md` the header has no blank line after it, so the lesson renders it in one `<p>` with Target Audience and Format.
  - The check uses its own rendered-HTML pattern, because `check_moodle_payload.py` imports neither `moodle_payload` nor `markdown`.
  - The web service now also skips a **name** write when the stored name is byte-identical, and counts `renamed` only for real writes. That is what makes "an unchanged republish writes nothing" true, since today every name is rewritten. Stored values are compared as `(string)`, so a NULL equals `''`.
  - An empty `name` skips only the name, never the summary.
  - The `summary` key is `external_value(PARAM_RAW, …, VALUE_OPTIONAL)`, tested with `array_key_exists`.
  - Deploy order: a server still on local_ltuse `2026100900` rejects the unknown `summary` key in `validate_parameters` (`invalidparameter`), after the course, placement, competencies and pathway calls have already written. So the plugin is deployed and its pin applied before the first publish with the new publisher. That includes `.github/workflows/moodle-publish.yml`, which publishes on a push to main touching `scripts/publish_moodle.py` or `scripts/moodle_payload.py` when the repository variable `MOODLE_ENABLED` is `true`: with it on, local_ltuse is deployed before the merge.
  - Quiz files carry no `**Estimated time:**` line, so a quiz section's `time_text` is `""` and its summary is cleared.
- [X] T005 [P] Amend `specs/007-learner-experience/contracts/learner-ui.md`:
  - (a) The block plugin's required files: `db/access.php` with `block/ltuse:myaddinstance` (captype `write`, contextlevel `CONTEXT_SYSTEM`, archetypes `['manager' => CAP_ALLOW]`, no `clonepermissionsfrom`) and `block/ltuse:addinstance` (riskbitmask `RISK_SPAM | RISK_XSS`, captype `write`, contextlevel `CONTEXT_BLOCK`, archetypes `['manager' => CAP_ALLOW]`, no `clonepermissionsfrom`). Cloning from `moodle/my:manageblocks` would grant `user`, whose archetype allows it until apply prevents it. The declaration places the block, and no learner or teacher adds it (R4). Also `classes/privacy/provider.php` as a `null_provider` with `privacy:metadata`; `version.php` `maturity` and `release`; lang ids `pluginname`, `ltuse:addinstance`, `ltuse:myaddinstance`, `privacy:metadata`.
  - (b) A **published course** is one whose `idnumber` matches `^ltct:[^:]+$` and is not `local_ltuse\officehours::COURSE` (`ltct:officehours`). Both the hook and the continue choice use this one rule, `learner_home_rules::is_published_course()`.
  - (c) The hook acts only when `$PAGE->pagetype` matches `^mod-[a-z0-9]+-view$`, not on every `incourse` page, so there is no Next in a quiz attempt, a quiz review or a forum discussion.
  - (d) `mentoring::mentors()` becomes public, so the block does not have to call `for_user()`, which builds every mentee's courses.
  - (e) The pathway lines are absent when `pathway\view::levels() === null`, and any `moodle_exception` from `summaries()` is caught.
  - (f) `displaydata` keys for `CoreBlockDelegate` are those confirmed at T049, not assumed.
  - (g) The offline hints appear in the block's app view only. R8's "and on the course page's top section" has no mechanism in this contract, so it is dropped. FR-007 inside a course is met by the app's native course-menu Download course, which spec 009 keeps enabled (`tool_mobile/disabledfeatures` empty) and V6 step 3 verifies. The Home-tab hint tells the learner where it is.
  - (h) The styling scopes are `div.ltuse-home` and `div.ltuse-next`, named in the `styles.css` header.
  - (i) "Never shown": replace "a PHP unit test asserts the lang file contains none of them" with "`tests/test_learner_wording.py` (pytest) holds every block_ltuse string and the spec 007 local_ltuse block to `cbc_wording.report_label_problems(strict=True)`".
- [X] T006 [P] Correct `specs/007-learner-experience/data-model.md`, `quickstart.md`, `research.md` and `plan.md`:
  - data-model §2: `courses_active` becomes "`enrol_get_all_users_courses($userid, true)` in visible courses (a hidden one only for a user with `moodle/course:viewhiddencourses` in it), filtered by `learner_home_rules::is_published_course()`".
  - data-model §3 Validation: replace "so `time_text` is never empty for a pipeline course" with: lessons and scenario banks always carry the header (`check_course_package.py`); quiz sections have none, so their summary is cleared.
  - data-model §4: "applies" gains the pagetype rule and the published-course rule from T005.
  - quickstart V4: "Each lesson section shows its Estimated time line; a quiz section shows none". "The quiz sits in its own section after the lessons, where the publisher places it". "Following Next twice reaches lesson 3".
  - quickstart Prerequisites gain the deploy order:
    1. fast-forward `main`;
    2. archive and copy `moodle/local_ltuse` to `public/local/ltuse`, and `moodle/block_ltuse` to `public/blocks/ltuse`, each with `git -c core.autocrlf=false archive`;
    3. run `php -d max_input_vars=5000 admin/cli/upgrade.php --non-interactive`;
    4. then `site_config.py apply`;
    5. only then publish with the new publisher.
  - quickstart "Apply and check": the first drift lists dashboard, myoverview grouping, brandcolor and supportemail lines, and no nav line, because R1 declares the four navigation settings at their live values. It also exits 1 in a shell without `MOODLE_SUPPORT_EMAIL`, as it does for `badges_defaultissuercontact`.
  - quickstart "Apply and check" **Pass** becomes: "The second drift shows only the documented Expected differences: no dashboard line and no `dashboard personal dashboards` line."
  - research R2: the decision covers **every** `block_myoverview/displaygrouping*` setting, adding `displaygroupingallincludinghidden = 0` and `displaygroupingcustomfield = 0` to the six.
  - research R4 Verify: "after apply, drift shows no personal dashboards line".
  - research R10: the `ensure_sections` citation is `publish_moodle.py:102-119`, called at `:406-409` (not `:222-224`).
  - research R8: the course-page hint is dropped, and FR-007 inside a course is met as T005 (g) states.
  - plan.md Testing and Project Structure: the wording test is `tests/test_learner_wording.py` (pytest), not a PHP unit test in `block_ltuse/tests/`; that folder holds the block's PHPUnit tests.

**Checkpoint**: CI triggers on the new plugin; the contracts say what the code does.

---

## Phase 2: Foundational

**Purpose**: The block plugin, its pin, and the one rule both the block and the hook use.
US1, US3 and US4 all render through `block_ltuse` and `local_ltuse\learner_home`. US2's
hook needs `learner_home_rules::is_published_course()`. (FR-009, FR-010)

**Ordering note**: pinning `block_ltuse` in `site.yaml` makes every live `apply` refuse
(`inspector::check_plugin` reports `missing`, blocking) until the block is installed on the
server. That is intended. The live order is in T074, and nothing is applied before merge.

- [X] T007 Create the `moodle/block_ltuse/` skeleton. Before writing, look up `block_base` on `MOODLE_502_STABLE` (`blocks/moodleblock.class.php`): `init()`, `get_content()`, `applicable_formats()`, `instance_allow_multiple()`, `hide_header()`, `has_config()`, and the `$this->content` shape. Record the file and line for T070.
  - `version.php`:
    - `$plugin->component = 'block_ltuse'`;
    - a new `YYYYMMDDXX` version;
    - `requires` and `supported` as local_ltuse's (`2026042000`, `[502, 502]`);
    - `maturity = MATURITY_ALPHA`, `release = '0.1.0'`;
    - `dependencies = ['local_ltuse' => 2026100900]`, with a comment that T062 raises it to the version that adds `learner_home`.
  - `block_ltuse.php`:
    - `class block_ltuse extends block_base`;
    - `init()` sets the title from `pluginname`;
    - `applicable_formats()` returns `['all' => false, 'my' => true]`;
    - `instance_allow_multiple()` returns false, `hide_header()` true, `has_config()` false;
    - `get_content()` returns an empty content object for now.
  - `db/access.php`: the two capabilities exactly as T005 (a) defines them: `manager` only, no `clonepermissionsfrom`.
  - `lang/en/block_ltuse.php`:
    - `pluginname` = `'Learner home'`;
    - `ltuse:addinstance` = `'Add the learner home block'`;
    - `ltuse:myaddinstance` = `'Add the learner home block to the Dashboard'`;
    - `privacy:metadata` = `'The learner home block stores no personal data. It shows what local_ltuse already holds.'`.
  - `classes/privacy/provider.php`: `block_ltuse\privacy\provider implements \core_privacy\local\metadata\null_provider`.

  Run `php -l` on every file.
- [X] T008 Pin the block in `moodle/site/site.yaml`, beside the `local_ltuse` entry: `- component: block_ltuse`, `version: <the stamp from moodle/block_ltuse/version.php>`, `source: {path: moodle/block_ltuse}`, `why: "#13 (spec 007 R5): the learner home block on the default Dashboard: continue, the empty state and onward routes. Core has none of these (R3)."`. Run `python scripts/site_config.py validate`. `_check_source` requires the version and component to equal those in `version.php`.
- [X] T009 [P] Create `tests/learner_home_harness.php` in the existing harness style. It requires `../moodle/local_ltuse/classes/learner_home_rules.php`, uses `fixture-*` names only, and exits 1 with `FAILURES: n` when the class or a method is missing. First cases, for `learner_home_rules::is_published_course(string $idnumber): bool`:
  - true for `ltct:fixture-a`;
  - false for `ltct:officehours`, for `ltct:fixture-a:03` (a module), for `''`, for `LTCT:x`, for `ltct:` and for `other:x`.

  Wire it into `.github/workflows/site-config.yml` in the same edit: add `'tests/learner_home_harness.php'` to both path lists, and a plain `php tests/learner_home_harness.php` run step after the existing harness steps. Run it and see it fail.
- [X] T010 Create `moodle/local_ltuse/classes/learner_home_rules.php`: `namespace local_ltuse; final class learner_home_rules`. Pure static methods only: no Moodle function, no `$DB`, no constant from Moodle, so the harness can load it. Add `is_published_course()`, matching `^ltct:[^:]+$` and excluding the office-hours idnumber. Copy the literal `'ltct:officehours'` into a class constant, with a comment pointing at `officehours::COURSE`, because requiring `officehours.php` would pull in Moodle. Make T009 pass.
- [X] T011 Create `moodle/local_ltuse/classes/learner_home.php`: `namespace local_ltuse; final class learner_home`, the data class of data-model §2 (one class, both views). This task adds the skeleton only:
  - `published_courses(int $userid): array` returns the user's active enrolments in visible courses, filtered by `learner_home_rules::is_published_course($course->idnumber)`. Use `enrol_get_all_users_courses($userid, true, 'idnumber, enablecompletion, visible')`, after confirming its signature and its `$onlyactive` semantics on `MOODLE_502_STABLE` (`lib/enrollib.php`). Keep a course only when `$course->visible` is 1 or the user has `moodle/course:viewhiddencourses` in its context. Confirm on `MOODLE_502_STABLE` whether `enrol_get_my_courses()` or `enrol_get_all_users_courses()` already filters hidden courses, and record it for T070. The publisher creates courses hidden, so a pilot course not yet opened is never offered.
  - `state(int $userid): array` returns `['mode' => 'empty'|'continue'|'start'|'done', 'course' => ?array, 'cm' => ?array, 'onward' => ['pathways' => [], 'mentors' => [], 'community' => null]]`. For now it is `empty` when `published_courses()` is empty, and `done` otherwise.
  - `applies(int $userid): bool` is false for a user with `moodle/site:config` at system context (R12).

  The docblock says the class reads learner data inside Moodle only, to render, and never stores it (Principle III).

**Checkpoint**: The block installs (empty), is pinned and validates, and the published-course
rule is tested. US1 and US2 can start; US3 and US4 follow US1.

---

## Phase 3: User Story 1 — First login, first lesson, no help (Priority: P1) 🎯 MVP

**Goal**: The Dashboard holds exactly `ltuse` (content 0), `myoverview` (content 1) and
`calendar_upcoming` (side-pre). The block shows "Start" / "Continue: <lesson>" in one tap, or
the empty-state message with Contact site support. Learners cannot customise the Dashboard,
leftover personal dashboards are reset, and the brand colour is SIL Blue. All of it is
declared and enforced from `moodle/site/`. (FR-001, FR-002, FR-005, FR-013)

**Independent Test**: `python scripts/site_config.py validate`, `python -m pytest -q
tests/test_site_config.py tests/test_learner_wording.py`, `php tests/learner_home_harness.php`,
`php tests/dashboard_plan_harness.php` and `php -l` all pass, and in plugin CI the PHPUnit
tests T017, T018 and T019 pass. Then (live, post-merge) quickstart "Apply and check", V1, V2
and V3.

### Tests for User Story 1

> Write these first and confirm they fail before implementing T020–T032.

- [X] T012 [P] [US1] In `tests/test_site_config.py`, update the spec 011 classes for the new tracked files.
  - `Events` appends a `BLOCK_PIN` to `SITE`, built like `SCHEDULER_PIN`: `- component: block_ltuse`, `version:` read with `sc._php_stamp(<moodle/block_ltuse/version.php>, 'version')`, `source: {path: moodle/block_ltuse}`.
  - `test_the_tracked_declaration_is_accepted_and_rendered` now asserts:
    - `decl['dashboard'] == [{'block': 'ltuse', 'region': 'content', 'weight': 0}, {'block': 'myoverview', 'region': 'content', 'weight': 1}, {'block': 'calendar_upcoming', 'region': 'side-pre'}]`;
    - `payload['dashboard_complete'] is True`;
    - `payload['dashboard_personal'] == 'reset'`.
  - `EventsAbsent` also asserts `payload['dashboard_complete'] is False` and `payload['dashboard_personal'] == 'keep'`.
  - The key-order assertion (`list(payload)[-11:]`, about L1515) becomes the last 13 keys, with `'dashboard', 'dashboard_complete', 'dashboard_personal'` before `'protection'`.
- [X] T013 [US1] Add `class Dashboard007(Events)` to `tests/test_site_config.py` (after T012, same file). It inherits `Events.setUp` (`SCHEDULER_PIN`, `NO_TIMEZONE_IGNORE`, `MENTORING_CATEGORY` and the tracked office-hours, dashboard, calendar and scheduler files) with T012's `BLOCK_PIN`. Its `setUp` calls `super().setUp()` and also copies the tracked `settings/learner-experience.yaml`. Each case calls `self.reset()` and then one `edit()`, inside a `subTest`, with `assertRejected()` / `assertInvalid(needle)`:
  - (a) the tracked files are accepted;
  - (b) site.yaml rewritten as `SITE.format(ver=VER, sha='a'*64) + SCHEDULER_PIN`, without `BLOCK_PIN`, so `block: ltuse` is refused: `assertInvalid('site.yaml')`;
  - (c) an unknown core-looking block (`block: nosuchblock`) is refused;
  - (d) a block named twice;
  - (e) `region: side-post`;
  - (f) `weight: one`, and `weight: -1`;
  - (g) `complete: yes-please`, and `complete: 1` (only `true`/`false` are accepted, as `secret` is);
  - (h) `personal_dashboards: wipe`;
  - (i) `personal_dashboards: reset` with the `moodle/my:manageblocks: prevent` line edited out of the tracked `roles.yaml` text;
  - (j) the same with `prohibit` in its place;
  - (k) `ltuse` at `weight: 1`, or in `side-pre`;
  - (l) a top key `layout:` in `dashboard.yaml`;
  - (m) `value: #005CB9` (unquoted) for `theme_boost/brandcolor` gives "no value";
  - (n) `defaulthomepage` declared again in `settings/test.yaml` is refused as a second home (FR-011 of spec 001);
  - (o) the tracked `settings/learner-experience.yaml` declares `supportemail` with a value starting `env:`, never a literal address (Principle III).
- [X] T014 [P] [US1] Create `tests/dashboard_plan_harness.php`. It requires `../moodle/local_ltuse/classes/siteconfig/dashboard_plan.php` and tests `dashboard_plan::plan(array $declared, array $live, bool $complete, string $personal, bool $editingprevented, int $personalcount): array`. `plan()` is pure, so its caller decides when to call it (`dashboard.php` calls it in `check()` and again at the start of `apply()`). The arguments:
  - `$declared` is `[{block, region, weight?}]`;
  - `$live` is `[{id, block, region, weight}]`, every instance on the default page.

  It returns `['add' => [{block, region, weight}], 'delete' => [{id, block}], 'reweight' => [{id, block, from, to}], 'reset' => bool, 'refusereset' => bool]`. Cases:
  - (a) nothing live gives three adds, each at its declared weight (`ltuse` 0, `myoverview` 1, `calendar_upcoming` 0 as undeclared), and a second `plan()` over the live set those adds produce is empty;
  - (b) live equal to declared gives everything empty (idempotent);
  - (c) `complete=false` with `timeline` live: no delete (011's additive rule);
  - (d) `complete=true` with `timeline`, `calendar_month` (in side-post) and `recentlyaccesseditems` live: three deletes;
  - (e) two live `myoverview` instances with `complete=true`: the one with the lower weight, then the lower id, is kept and the other deleted;
  - (f) `myoverview` live at 0 and declared 1: one reweight;
  - (g) a declared block without `weight`: never reweighted;
  - (h) `personal='reset'`, count 6, prevented: `reset=true`;
  - (i) `personal='reset'`, count 0, prevented: `reset=false`;
  - (j) `personal='reset'`, count 6, not prevented: `reset=false`, `refusereset=true`;
  - (k) `personal='keep'`: neither;
  - (l) `myoverview` declared content/1 but live in side-pre at weight 1: no add, delete or reweight (the region is not compared, 011's `present()`);
  - (m) `personal='reset'`, count 0, not prevented: `reset=false`, `refusereset=false`;
  - (n) nothing live except `myoverview` at 0: adds `{ltuse, content, 0}` and `calendar_upcoming`, and reweights `myoverview` 0→1;
  - (o) `complete=false` with two live `myoverview`: no delete.

  Wire it into `.github/workflows/site-config.yml` in the same edit: add `'tests/dashboard_plan_harness.php'` to both path lists, and a `php tests/dashboard_plan_harness.php` run step after T009's. Run it and see it fail.
- [X] T015 [P] [US1] Extend `tests/learner_home_harness.php` with the continue rule (R3, data-model §2).
  - `learner_home_rules::order_candidates(array $courses): array`, where `$courses` is `[{id, lastaccess, enroltime, complete}]`. It orders by `lastaccess` descending (0 = never accessed, sorts last), then `enroltime` descending, then `id` ascending.
  - `learner_home_rules::first_incomplete(array $cms): ?array`, where `$cms` is `[{id, name, url, uservisible, stealth, hasurl, tracked, complete}]` in course order. It returns the first with `uservisible && !stealth && hasurl && tracked && !complete`. A hidden cm, or one in the Retired section, is skipped (FR-013), and so are an untracked cm and a completed one.
  - `learner_home_rules::choose(array $courses, callable $cmsof): ?array`. It orders with `order_candidates()`, skips courses whose `complete` is true, and calls `$cmsof((int) $course['id'])` (which returns the `first_incomplete()` shape) only as the walk reaches each course. It returns `{course_id, cm, anycomplete}` for the first course whose `first_incomplete()` is not null, `anycomplete` being whether any tracked cm in that course is complete; otherwise null. The callable keeps the class pure: the harness passes a closure over fixture arrays, and T029 passes one that reads Moodle.
  - `learner_home_rules::mode(int $activecount, ?array $chosen): string`, where `$activecount` is `count(published_courses())`, completion-off courses included:
    - `empty` when `$activecount === 0`;
    - `done` when `$chosen === null`;
    - `continue` when `$chosen['anycomplete']`;
    - `start` otherwise.

  Cases:
  - (a) two courses, the most recently accessed one complete: the older one is chosen;
  - (b) the most recent course has every tracked cm complete but no course-completion record: the older one is chosen;
  - (c) every course complete gives null, and `mode()` gives `done`;
  - (d) nothing started gives `start`;
  - (e) one cm complete gives `continue`;
  - (f) zero published courses gives `empty`;
  - (g) the only published course has completion off, so it is no candidate: `done`. Recorded as accepted, because the publisher always enables completion (spec 004), unless the maintainer picks another rule;
  - (h) when the most recent course has an incomplete cm, `$cmsof` is called once (plan Performance Goals).
- [X] T016 [US1] Create `tests/test_learner_wording.py` (after T014, same workflow file), modelled on `tests/test_pathway_wording.py` (same `STRING` regex; assert every `$string` line was parsed). It covers `block_ltuse` only, so US1 passes without US2; the local_ltuse block is T038's.
  - It checks every string in `moodle/block_ltuse/lang/en/block_ltuse.php` with `cbc_wording.report_label_problems(text, strict=True)` (parametrized ids). It also has a teeth test (`'You are certified'` is refused).
  - A second test checks FR-012: every `get_string('<id>', 'block_ltuse')` in `moodle/block_ltuse/**/*.php`, every `{{#str}}<id>, block_ltuse{{/str}}` / `<%#str%><id>, block_ltuse<%/str%>` in its templates, and every `['<id>', 'block_ltuse']` pair in `moodle/block_ltuse/db/mobile.php` (when the file exists) names an id defined in the lang file.

  Wire it into `.github/workflows/site-config.yml` in the same edit: add `'tests/test_learner_wording.py'` to both path lists, and append it to the existing pytest command (after `tests/test_pathway_wording.py`).
- [X] T017 [P] [US1] Create `moodle/local_ltuse/tests/learner_home_test.php`: `namespace local_ltuse; final class learner_home_test extends \advanced_testcase`, with a docblock carrying `@package local_ltuse`, `@category test` and `@covers \local_ltuse\learner_home`, and the header comment "synthetic data only, never run on the shared host". Use the data generator for courses with `idnumber` `ltct:fixture-a`, `ltct:fixture-b` and `ltct:officehours`, completion on, and three page cms completing on view. Cases:
  - `test_no_published_enrolment_is_empty`: enrolled only in `ltct:officehours` gives `empty`;
  - `test_a_new_learner_is_offered_the_first_lesson`: `start`, cm = the first page;
  - `test_after_two_lessons_the_third_is_offered`: mark two complete gives `continue`, cm = the third;
  - `test_a_finished_learner_is_done`;
  - `test_a_hidden_activity_is_never_offered`;
  - `test_a_hidden_course_is_never_offered`: a learner enrolled in a hidden `ltct:fixture-b` and a visible `ltct:fixture-a` is offered fixture-a, and with only the hidden course gets `empty`;
  - `test_a_site_admin_is_not_shown_the_block`: `applies()` is false.
- [X] T018 [P] [US1] Create `moodle/local_ltuse/tests/siteconfig_dashboard_test.php`: `namespace local_ltuse; final class siteconfig_dashboard_test extends \advanced_testcase`, docblock `@package local_ltuse`, `@category test`, `@covers \local_ltuse\siteconfig\dashboard`, synthetic data only, `resetAfterTest()`. Build the dashboard as `new siteconfig\dashboard($entries, $complete, $personal, new siteconfig\inspector($declaration))` and the report as `recognition_test` does (`new siteconfig\report('apply', true, function() {})`, read back with `items()`). On the default page that install creates:
  - (1) `check()` with `complete=false` reports no `extra` item;
  - (2) `complete=true` removes `timeline` and a block added to `side-post` on the default page, and leaves a block on a generated user's private `my-index` page and a block on a course page untouched;
  - (3) a declared `myoverview` at weight 1 is reweighted to 1, and an added block carries its declared weight;
  - (4) with a user who has a private `__default` page and a `__courses` row, `personal_count()` is 1;
  - (5) with `moodle/my:manageblocks` still allowed for `user`, `personal_dashboards: reset` reports `fail` and no `my_pages` row is deleted;
  - (6) after `assign_capability('moodle/my:manageblocks', CAP_PREVENT, $userroleid, $syscontextid)`, the reset removes the private `__default` row and keeps `__courses`;
  - (7) a second `apply()` reports no `changed` item.

  It runs in the existing local_ltuse plugin-CI job.
- [X] T019 [P] [US1] Create `moodle/block_ltuse/tests/block_test.php`: `namespace block_ltuse; final class block_test extends \advanced_testcase`, with a docblock carrying `@package block_ltuse` and `@covers \block_ltuse`, synthetic data only. Every string is asserted escaped as the template outputs it, `assertStringContainsString(s(get_string(...)), $content->text)`, because mustache escapes the apostrophe in `empty:who`. Cases:
  - `test_empty_state_names_who_to_ask_and_links_support`: content contains the `empty` and `empty:who` strings and a link equal to `(new moodle_url('/user/contactsitesupport.php'))->out(false)`;
  - `test_start_button_links_to_the_first_lesson`;
  - `test_site_admin_sees_nothing`;
  - `test_only_the_dashboard_can_hold_it`: `applicable_formats()`.

### Implementation for User Story 1

- [X] T020 [P] [US1] Create `moodle/site/settings/learner-experience.yaml`: `rows: [13]`, a `purpose:` along the lines of "What a learner lands on and the colour the site wears: the Dashboard, one way into a course, a short course list, and who to ask", and `settings:`, each `why:` taken from the research item named:
  - `defaulthomepage: 1` (R1, HOMEPAGE_MY: the Dashboard is the only page that carries Continue beside the course list, and it is the app's Home tab);
  - `enabledashboard: 1` (R1);
  - `enablemycourses: 0` (R1: a second list of the same courses);
  - `enablemyhome: 0` (R1);
  - `block_myoverview/displaygroupingall: 1`, `block_myoverview/displaygroupinginprogress: 1`, `block_myoverview/displaygroupingpast: 1`, `block_myoverview/displaygroupingfuture: 0`, `block_myoverview/displaygroupingfavourites: 0`, `block_myoverview/displaygroupinghidden: 0`, `block_myoverview/displaygroupingallincludinghidden: 0`, `block_myoverview/displaygroupingcustomfield: 0` (R2: every grouping is declared, so a hand change that adds another "All" view or a custom-field filter shows in drift);
  - `theme_boost/brandcolor: "#005CB9"`, **quoted**, because an unquoted `#` starts a YAML comment (R11: SIL Blue, SIL Brand Manual v1.1; Boost makes it `$primary`; white on it is about 6.5:1, WCAG AA);
  - `supportavailability: 1` (R9: logged-in users only, never an anonymous spam route);
  - `supportemail: env:MOODLE_SUPPORT_EMAIL` (R9: a provisioning value, never a person's address in the public repo).

  Before writing, confirm on `MOODLE_502_STABLE`:
  - every `block_myoverview/displaygrouping*` setting in `blocks/myoverview/settings.php`, declaring each one found;
  - that `supportavailability` 1 is `CONTACT_SUPPORT_AUTHENTICATED`;
  - that `HOMEPAGE_MY` is 1.

  Do not declare `block_myoverview/layouts` (core default, R2). No absolute URL is allowed in a settings file. Run `python scripts/site_config.py validate`.
- [X] T021 [P] [US1] In `moodle/site/roles.yaml`, add `moodle/my:manageblocks: prevent` under the `user` entry's `capabilities:`. Extend its `why:` with: "#13 (spec 007 R4): a learner cannot customise the Dashboard, so every learner keeps the declared default and a future change reaches them. `prevent`, not `prohibit`, so managers keep editing through their archetype." Run `python scripts/site_config.py validate`.
- [X] T022 [P] [US1] In `moodle/site/settings/completion.yaml`, replace the comment at L19-20 ("block_myoverview grouping settings: added only if quickstart V8 shows …") with one line: "block_myoverview grouping settings are declared in learner-experience.yaml (spec 007 R2)." Run `python scripts/site_config.py validate`.
- [X] T023 [US1] Extend `scripts/site_config.py` for the amended dashboard contract (T003).
  - `TOP_FILES[DASHBOARD_FILE]` becomes `({'rows', 'default_blocks'}, {'purpose', 'complete', 'personal_dashboards'})`.
  - Add `"dashboard_complete": False, "dashboard_personal": "keep"` to the initial `decl` dict in `validate()` (about L528-533), so a declaration without `dashboard.yaml` still builds a payload.
  - Change `_validate_dashboard(where, data, rows, problems)` to `_validate_dashboard(where, data, rows, decl, problems)`, and update its call (about L917-919). It still runs after `site.yaml`, `roles.yaml` and the settings files are read. It sets `decl['dashboard_complete']` and `decl['dashboard_personal']` itself and returns the block list. Rules:
    - entry keys `{'block', 'region', 'why'}` plus optional `{'weight'}`;
    - `weight` must pass `_is_int` and be `>= 0`;
    - a block is accepted if it is in `STANDARD['block']` **or** `'block_' + name` is a component in `decl['plugins']`, and the message for neither names `site.yaml`;
    - no duplicates, and regions are still `DASHBOARD_REGIONS`;
    - `ltuse`, when declared, must be `content` / `0`;
    - `complete` is accepted only as a `Flag` whose text is `true`/`false`, handled as `secret` is (about L780-785);
    - `personal_dashboards` is `reset` or `keep`, and `reset` requires `decl['roles']`'s `user` entry to have `capabilities['moodle/my:manageblocks'] == 'prevent'`, exactly (`prohibit` refused, R4).
  - It returns `[{block, region, weight?}]`, with `weight` present only when declared.
  - `build_payload` puts them right after `payload['dashboard']`, as `dashboard_complete` and `dashboard_personal`.
  - `_summary` prints `N dashboard blocks` plus `, complete` and `, personal dashboards reset` when set.

  Make T013 (b)–(o) pass.
- [X] T024 [US1] Rewrite `moodle/site/dashboard.yaml` per the contract.
  - The header comment: rows #13 and #21; read by `site_config.py` (contracts: 011's declaration, amended by `specs/007-learner-experience/contracts/dashboard-declaration.md`); complete: apply removes any block on the default page that is not listed; it never touches a user's own dashboard except the declared reset.
  - `rows: [13, 21]`; `purpose:` the contract's text; `complete: true`.
  - `default_blocks`:
    - `ltuse` / `content` / `weight: 0` (why: R5, the one continue route, the empty state and onward routes);
    - `myoverview` / `content` / `weight: 1` (why: R2, the clean course list with progress bars, spec 004 R6);
    - `calendar_upcoming` / `side-pre`, with 011's entry and why unchanged.
  - `personal_dashboards: reset` (why in a comment: R4, plan decision 2).

  Run `python scripts/site_config.py validate` and `python -m pytest -q tests/test_site_config.py`. Make T012 and T013 (a) pass.
- [X] T025 [US1] Create `moodle/local_ltuse/classes/siteconfig/dashboard_plan.php`: `namespace local_ltuse\siteconfig; final class dashboard_plan`, with one pure static `plan()` exactly as T014 specifies (no Moodle calls), and a docblock saying `dashboard.php` is its only caller. Make T014 pass.
- [X] T026 [US1] Extend `moodle/local_ltuse/classes/siteconfig/dashboard.php` to drive `dashboard_plan::plan()`.

  **First, confirm each API on `MOODLE_502_STABLE` and record the file:line for T070:**
  - `blocks_delete_instance()` (`lib/blocklib.php`): its signature, and that it removes the instance's `block_positions` and context;
  - `my_reset_page_for_all_users()` (`my/lib.php:232`): its full signature, and whether a third pagename argument exists;
  - how a default-page block's order is stored: `block_instances.defaultweight`, and whether a `block_positions` row for the default page's subpage overrides it.

  Use `block_manager::reposition_block()` from a `moodle_page` set up as `apply()` already does, if that works for the default page. If it does not, the only fallback is a direct `block_instances.defaultweight` update, listed in README's direct-writes table (T063).

  Then:
  - The constructor takes `(array $entries, bool $complete = false, string $personal = 'keep', ?inspector $inspector = null)`.
  - `require_once($CFG->dirroot . '/my/lib.php')`, beside the existing `blocklib.php` require, in every method that reads `MY_PAGE_PRIVATE` / `MY_PAGE_DEFAULT` or calls `my_reset_page_for_all_users()`: `apply()`, and `check()` and `personal_count()` if they use the constants.
  - `live_blocks()` lists every instance on the default page by the T003 (e) filter only (system context, `my-index`, subpage = default page id, any region), as `{id, block, region, weight}`, the weight being the effective one found above.
  - `personal_count()` counts per T003 (f), with no user ids.
  - `editing_prevented()` looks up the `user` role id (`$DB->get_field('role', 'id', ['shortname' => 'user'])`) and returns `($this->inspector->live_role_capabilities($roleid)['moodle/my:manageblocks'] ?? null) === CAP_PREVENT`. `live_role_capabilities()` (inspector.php:981) reads `role_capabilities` on every call, so the answer is live. With no inspector or no `user` role it returns false, so the reset is refused.
  - A protected `plan()` calls `dashboard_plan::plan($this->declared, $this->live_blocks(), $this->complete, $this->personal, $this->editing_prevented(), $this->personal_count())`. `check()` and `apply()` each call it; `apply()` never reuses a plan computed in `check()`, because the applier's roles step (applier.php:107-111) runs before the dashboard step (:144-145) and may just have prevented editing.
  - `check()` builds its items from the plan:
    - `dashboard <block>` ok for each declared block present with nothing planned, missing for each planned add, and unknown (blocking) when the site has no default page, as today;
    - `dashboard <block>` with result `inspector::RESULT_EXTRA` (`'extra'`, inspector.php:137) for each planned delete;
    - `dashboard <block> weight` with result `RESULT_CHANGED`, declared and live weights, for each reweight;
    - `dashboard personal dashboards`, declared `reset`, live `<N>`, `RESULT_CHANGED`, when `reset` is planned or refused.

    No item names a user. The count appears only in live drift output, never in the repo.
  - `apply()` no longer iterates `check()` or pairs items with `$this->declared[$i]` by index. It re-plans once at its start and runs the plan in the contract's order:
    1. each `add`: `add_region($region)`, then `add_block($block, $region, $weight, false, self::PAGETYPE, (string)$pageid)` with the planned weight (the declared weight, 0 when undeclared), replacing today's hard-coded `0`;
    2. each `delete`, with `blocks_delete_instance()`;
    3. each `reweight`;
    4. the reset, with `my_reset_page_for_all_users(MY_PAGE_PRIVATE, 'my-index')`, only when the plan says `reset`. On `refusereset`, `add_result($item, 'fail', 'editing is still allowed for the user role; the reset is refused')`.

    It reports one result per planned operation and `ok` for each declared block that needs nothing. Each write is reported `changed`, and each refusal by Moodle `fail` with `Moodle refused: …`.
  - Rewrite the class docblock: it is no longer "additive … never removes one, and never calls `my_reset_page_for_all_users()`".

  Run `php -l`. Make T018 pass in plugin CI.
- [X] T027 [US1] In `moodle/local_ltuse/classes/siteconfig/inspector.php`:
  - `dashboard()` passes `(bool) ($this->declaration['dashboard_complete'] ?? false)`, `(string) ($this->declaration['dashboard_personal'] ?? 'keep')` and `$this` as the fourth argument, so an older payload still works and `editing_prevented()` can read the live role;
  - the header doc (about L65-70) names `dashboard [{block, region, weight?}]`, `dashboard_complete` and `dashboard_personal`.

  In `moodle/local_ltuse/classes/siteconfig/applier.php`, rewrite the header (about L57-60 and L135-147, "Never deletes … a block"). It now says: apply deletes undeclared blocks from the default Dashboard page only when `dashboard.yaml` says `complete`, and resets personal dashboards only when it says `reset` and editing is prevented live. Run `php -l` on both.
- [X] T028 [US1] Add `order_candidates()`, `first_incomplete()`, `choose()` and `mode()` to `moodle/local_ltuse/classes/learner_home_rules.php`, exactly as T015 specifies. Make `php tests/learner_home_harness.php` pass.
- [X] T029 [US1] Fill in `learner_home::state()` in `moodle/local_ltuse/classes/learner_home.php` for the four modes of data-model §2.

  **First, confirm on `MOODLE_502_STABLE`:**
  - how `course_get_recent_courses()` and `user_lastaccess` read the last access;
  - `completion_info::is_course_complete()` and `completion_info::get_data()`'s signature;
  - `cm_info->uservisible`, `is_stealth()` and `->url`.

  Then:
  - For each of `published_courses()` whose `enablecompletion` is on, build `{id, lastaccess, enroltime, complete}`:
    - `complete` from `is_course_complete($userid)`;
    - `lastaccess` from `user_lastaccess`, by API if one exists, else by indexed columns (a stable core table; list it in T063);
    - `enroltime` = the maximum, over the user's active enrolments in the course, of `timestart ?: timecreated`.
  - Call `learner_home_rules::choose($courses, $cmsof)`, where the closure builds one course's cm facts from `get_fast_modinfo($course, $userid)->get_cms()` plus `completion_info::get_data()`. `choose()` stops at the first course with an incomplete cm, so a learner on one course costs one completion read (plan Performance Goals).
  - `mode(count(published_courses()), $chosen)` gives the mode.
  - `course` is `{id, fullname: format_string(…), url: /course/view.php?id=}`.
  - `cm` is `{id, name: format_string(…), url}`.

  Make T017 pass.
- [X] T030 [US1] Add the US1 strings to `moodle/block_ltuse/lang/en/block_ltuse.php`, alphabetised as Moodle requires:
  - `continue` = `'Continue: {$a}'`;
  - `start` = `'Start: {$a}'`;
  - `coursename` = `'Course: {$a}'`;
  - `empty` = `'No course has been assigned to you yet.'`;
  - `empty:who` = `'Ask your organisation\'s language technology coordinator, or contact the site team.'`;
  - `contactsupport` = `'Contact the site team'`;
  - `done` = `'You have finished your courses.'`.

  Make `python -m pytest -q tests/test_learner_wording.py` pass.
- [X] T031 [US1] Render the block on the web.
  - Create `moodle/block_ltuse/templates/block.mustache`: a docblock with `@template block_ltuse/block`, a description and a **valid** "Example context (json)" (it is linted by `moodle-plugin-ci mustache`), and the markup:
    - `<div class="ltuse-home">`;
    - for `continue` / `start`: the course name in a small line, then one `a.btn.btn-primary` to the cm url;
    - for `empty`: the two sentences and a link to `/user/contactsitesupport.php`;
    - for `done`: the sentence;
    - an empty `{{#onward}}` section that US4 fills.
  - Build the template context in an autoloaded class, `moodle/block_ltuse/classes/output/home.php`: `namespace block_ltuse\output; final class home { public static function context(array $state): array }`. The block's main class (`block_ltuse.php`) is not autoloaded, so US3's web-service request could not reach a method on it; this class is what US3 reuses.
  - In `moodle/block_ltuse/block_ltuse.php`, `get_content()`:
    - returns empty content when `!learner_home::applies($USER->id)`;
    - otherwise renders the template from `\block_ltuse\output\home::context(learner_home::state($USER->id))`;
    - takes every visible word from `get_string()`.

  Make T019 pass.
- [X] T032 [US1] In `moodle/local_ltuse/styles.css`, add the block panel per R11:
  - `div.ltuse-home { background: rgba(0, 92, 185, 0.08); border-left: 4px solid #005CB9; padding: 1rem 1.25rem; margin-bottom: 1rem; }`;
  - `div.ltuse-home .btn { white-space: normal; max-width: 100%; }`, so the course name and the button fit at the narrowest phone width (V1 step 3);
  - `div.ltuse-home .ltuse-onward { border-top: 1px solid rgba(0, 92, 185, 0.20); margin-top: 1rem; padding-top: 0.75rem; }`, used in US4.

  No `color:` declaration and no `prefers-color-scheme` block. Amend the header comment: the rules are scoped to `.local-ltuse-page`, to spec 006's `.local-ltuse-pathways` / `.local-ltuse-pathway`, and to spec 007's `div.ltuse-home` and `div.ltuse-next`, so nothing affects another page. Do **not** bump `mobilecssurl` here (T066).
- [X] T033 [US1] Run the US1 gate:
  - `python scripts/site_config.py validate`;
  - `python -m pytest -q tests/test_site_config.py tests/test_cbc_wording.py tests/test_pathway_wording.py tests/test_protection_declaration.py tests/test_learner_wording.py`;
  - every `php tests/*_harness.php`;
  - `php -l` on every changed or new PHP file.

  The PHPUnit tests T017, T018 and T019 run in plugin CI (T002 and the existing local_ltuse job).

**Checkpoint**: US1 complete in the repo. It deploys with T062 (version bump) and is verified by
T074–T075 (live).

---

## Phase 4: User Story 2 — A course reads as a short, ordered set of lessons (Priority: P1)

**Goal**: Each lesson section shows its own `**Estimated time:**` line as the section summary,
sent one-way by the publisher (R10). Every lesson page on the web ends with "Next: <name>",
or "Back to the course" on the last one (R6). (FR-003, FR-004, FR-011, FR-013)

**Independent Test**: `python -m pytest -q tests/test_payload_time_text.py tests/test_publish_moodle.py tests/test_payload_completion.py tests/test_learner_wording.py`,
`php tests/learner_home_harness.php` and `php -l` pass, and in plugin CI the PHPUnit test T037
passes. `python scripts/publish_moodle.py --slug paratext-quotation-rules --dry-run` prints
`summaries N`. Then (live, post-merge) quickstart V4 and V5.

### Tests for User Story 2

> Write these first and confirm they fail before implementing T039–T046.

- [X] T034 [P] [US2] Create `tests/test_payload_time_text.py` (pytest, the `tests/test_payload_completion.py` style). It builds a throwaway course in `tmp_path` and monkeypatches `cmp.MODULES`; it never reads `modules/`. Cases:
  - (a) a lesson with `**Estimated time:** 30 minutes` gives `time_text == '<p><strong>Estimated time:</strong> 30 minutes</p>'`, equal to `moodle_payload.render('**Estimated time:** 30 minutes')`, and not wrapped in `local-ltuse-page`;
  - (b) a header followed directly by `**Target Audience:** …` on the next line (no blank line) gives only the header line;
  - (c) a scenario bank with `**Estimated time:** 15 minutes to read through and orient (…)` keeps the trailing text;
  - (d) a quiz, and `**Estimated time:** [X] minutes`, give `''`;
  - (e) every section in `manifest['sections']` carries `time_text` as a `str`, and `minutes` is unchanged;
  - (f) `check_moodle_payload.check()` is clean, then reports a problem when `time_text` is edited to carry a second element (`…</p><p>more</p>`), a newline, a `<script>`, or a non-matching paragraph, or when the key is deleted from one section;
  - (g) Principle II: every `manifest['sections']` entry has the key `time_text` and has neither a `summary` nor a `summaryformat` key; and the manifest text contains no `"summaryformat"` and no `"summary":` (checked with the quotes and colon, so the existing `summary_html` key is not matched).

  Wire it into `.github/workflows/publisher-tests.yml` in the same edit, in its three places: `push.paths`, `pull_request.paths` and the `python -m pytest -q …` command.
- [X] T035 [P] [US2] Extend `tests/test_publish_moodle.py`.
  - `FakeClient` gains a `local_ltuse_update_sections` branch returning `{courseid, sectionsbefore, sectionsafter, renamed, summaries}`, and a `sections_error` attribute. When it is set, that branch raises `MoodleError('local_ltuse_update_sections', {'errorcode': 'invalidparameter', 'message': 'Invalid parameter value detected'})`, with no debuginfo, as a production server sends it.
  - `PublishBase.write_manifest()` keeps writing sections **without** `time_text` for old-manifest coverage, and a new helper writes them with it.
  - Cases:
    - (a) with `time_text`, every section is sent with `number`, `name` and `summary` equal to its `time_text`, including `''` for a quiz;
    - (b) an old manifest without `time_text` sends no `summary` key at all, so the server leaves summaries untouched;
    - (c) the output has `  summaries %d` on the line after `  sections  %d`, counted from what was **sent** in dry run (`client.calls`) and from the reply's `summaries` otherwise, read with `.get()`;
    - (d) with `sections_error` set and summaries sent, the publish exits 1 and stderr holds the hint "the server's local_ltuse predates spec 007; deploy it before publishing" plus the original error.
- [X] T036 [P] [US2] Extend `tests/learner_home_harness.php` with the next-lesson rule (R6, data-model §4).
  - `learner_home_rules::next_cm(array $cms, int $currentid): ?array`, with `$cms` as `[{id, name, url, uservisible, stealth, hasurl}]` in `get_cms()` order. It returns the first after `$currentid` that is `uservisible && !stealth && hasurl`, and null at the end. When `$currentid` is absent, it returns `['absent' => true]`, which the hook treats as "render nothing". Cases:
    - (1) the next visible cm is returned;
    - (2) a hidden cm is skipped;
    - (3) a stealth cm is skipped;
    - (4) a label (`hasurl` false) is skipped;
    - (5) the current cm is last: null;
    - (6) only hidden or locked cms follow: null;
    - (7) `$currentid` not in the list: `['absent' => true]`;
    - (8) the quiz followed by a locked certificate (`uservisible` false; `certificate::last_lesson_section()` places it after the quiz): null, so Back to the course;
    - (9) the same certificate unlocked: the certificate;
    - (10) a current cm that is itself hidden (a teacher's view) still finds the next one.
  - `learner_home_rules::applies_next(string $pagelayout, string $pagetype, bool $modulecontext, string $idnumber): bool`, true only for `incourse` + `^mod-[a-z0-9]+-view$` + module context + `is_published_course()`. False for `mod-quiz-attempt`, `mod-quiz-review`, `mod-forum-discuss`, a `ltct:officehours` scheduler view, `course-view-topics`, and a course with `idnumber` `''`.
- [X] T037 [P] [US2] Create `moodle/local_ltuse/tests/update_sections_test.php`: `namespace local_ltuse; final class update_sections_test extends \advanced_testcase`, docblock `@package local_ltuse`, `@category test`, `@covers \local_ltuse\external\update_sections`, synthetic data only, `resetAfterTest()`. A generated course with `idnumber` `ltct:fixture-a`, called as `setAdminUser()` (who holds `local/ltuse:publish` and `moodle/course:update`). Cases:
  - (1) a first call with names and summaries returns `renamed` N and `summaries` N;
  - (2) the same call again returns `renamed` 0 and `summaries` 0;
  - (3) an item without a `summary` key leaves a stored summary unchanged;
  - (4) `summary: ''` clears a stored summary and counts 1;
  - (5) `name: ''` with a summary writes the summary only;
  - (6) a stored `summaryformat` other than `FORMAT_HTML` is rewritten;
  - (7) a section whose stored `name` is NULL, sent `name: ''`, writes nothing.

  It runs in the existing local_ltuse plugin-CI job.
- [X] T038 [P] [US2] Extend `tests/test_learner_wording.py` (T016; if US2 is built before US1, create it as T016 describes, CI wiring included) with the spec 007 local_ltuse block: every string between `// Spec 007: learner experience.` and `// End of the spec 007 learner experience block.` in `moodle/local_ltuse/lang/en/local_ltuse.php` is held to `cbc_wording.report_label_problems(text, strict=True)`, the block must exist (the `test_pathway_wording.py` `_block()` assertion), and every `get_string('nextlesson'|'backtocourse', 'local_ltuse')` in `moodle/local_ltuse/**/*.php` names an id defined there.

### Implementation for User Story 2

- [X] T039 [US2] In `scripts/moodle_payload.py`, add `time_text_of(raw: str) -> str`:
  - `m = TIME_RE.search(raw)`;
  - if `m` is none, return `''`;
  - otherwise take the **whole source line** that holds `m` (from the newline before `m.start()` to the next newline), `.strip()` it, and return `render(line)`, never `wrap()`, because a section summary is outside the page.

  Add `"time_text": time_text_of(raw)` to each of the three `sections.append` calls in `Payload.build()` (lessons about L297-302, the quiz branch about L310-312, the page branch about L317-318), beside the unchanged `minutes`. Make T034 (a)–(e) and (g) pass.
- [X] T040 [US2] In `scripts/check_moodle_payload.py`, add check 11, `check_section_summaries(slug, manifest) -> list[str]`, called in the structural block on every view. Every section must carry `time_text` as a `str` (fail closed if a builder forgets it), equal to `''` or fully matching `^<p><strong>Estimated time:</strong>\s*\d+\s*minutes[^<>\n]*</p>$`. That pattern allows the scenario bank's trailing plain text and entities like `&amp;`, and refuses any second element or newline. Messages are `'%s: section %d time_text is not the Estimated time line' % (slug, n)`. In the same edit, fix the numbering:
  - the module docstring lists checks 1–11 and says "Five disclosure checks" and "six structural checks";
  - the block comment `--- 6 and 7. structural` becomes `--- 6 to 11. structural`;
  - `check_target_level`'s docstring says Check 10.

  Optionally name the new check in the success sentence (about L427-430). Make T034 (f) pass.
- [X] T041 [US2] Extend `moodle/local_ltuse/classes/external/update_sections.php` per the amended contract (T004).

  **First, confirm on `MOODLE_502_STABLE`:**
  - that `course_update_section()` (`course/lib.php`) accepts `summary` and `summaryformat` in its data argument, and whether it takes an array or an object (the existing call passes `(object)`; match what the source accepts);
  - that `validate_parameters` keeps a `VALUE_OPTIONAL` key absent rather than defaulting it.

  Then:
  - `sections` items gain `'summary' => new external_value(PARAM_RAW, 'section summary HTML, FORMAT_HTML; absent leaves it untouched', VALUE_OPTIONAL)`.
  - Restructure the loop so name and summary are decided separately, comparing `(string)$record->name` and `(string)$record->summary`, so a stored NULL equals `''`:
    - the name is written only when it is non-empty **and** differs byte-for-byte from the stored `name` (`renamed++` only then);
    - the summary is written only when `array_key_exists('summary', $s)` **and** it differs from the stored `summary`, or the stored `summaryformat` is not `FORMAT_HTML` (`summaries++` only then);
    - one `course_update_section()` call carries whichever fields changed;
    - an empty name never skips the summary.
  - `execute_returns()` gains `'summaries' => new external_value(PARAM_INT, 'summaries actually written')`.
  - Update the class docblock: an unchanged republish writes no section.

  Run `php -l`. Make T037 pass in plugin CI.
- [X] T042 [US2] In `scripts/publish_moodle.py`, change `ensure_sections(client, courseidnumber, count, names)` to `ensure_sections(client, courseidnumber, count, sections)`, taking `manifest["sections"]`.
  - Send one item per section: `{"number": n, "name": s["name"]}`, plus `"summary": s["time_text"]` only when the section has the key (`s.get`), so an old manifest sends none.
  - Return `(sent_summaries, reply)`.
  - In `publish()` (about L406-409), drop the `names =` line (and its missing space), and print `  sections  %d`, then `  summaries %d`. The count is the number sent in dry run and `reply.get("summaries", sent)` otherwise.
  - In dry run, print each section's number and its `time_text` with tags stripped, one indented line each ("shows the summaries it would send").
  - Catch `MoodleError` around the `update_sections` call. When `e.function == 'local_ltuse_update_sections'`, `e.errorcode == 'invalidparameter'` and at least one item carried `summary`, print to stderr "the server's local_ltuse predates spec 007; deploy it before publishing" plus the original error, and exit 1. The test is the errorcode, not the message: a server without debugging returns only "Invalid parameter value detected", and the key name appears only in debuginfo (confirm in `MOODLE_502_STABLE` `webservice/rest/locallib.php` that debuginfo is sent only under debugging, and record it for T070).

  Make T035 pass, and confirm `python -m pytest -q tests/test_publish_moodle.py tests/test_payload_completion.py` is green.
- [X] T043 [US2] Add `next_cm()` and `applies_next()` to `moodle/local_ltuse/classes/learner_home_rules.php`, exactly as T036 specifies. Make `php tests/learner_home_harness.php` pass.
- [X] T044 [US2] In `moodle/local_ltuse/lang/en/local_ltuse.php`, add a block opened by `// Spec 007: learner experience. Learner-facing navigation; no string here names a CBC level or says "certified".` and closed by `// End of the spec 007 learner experience block.`, appended after the spec 008 blocks. It holds `$string['backtocourse'] = 'Back to the course';` and `$string['nextlesson'] = 'Next: {$a}';`. Make `python -m pytest -q tests/test_learner_wording.py` (T038) pass.
- [X] T045 [US2] Add the next-lesson button.

  **First, confirm on `MOODLE_502_STABLE`:**
  - `core\hook\output\after_standard_main_region_html_generation` (`lib/classes/hook/output/`, dispatched at `core_renderer.php:586`), its `add_html()` and `renderer` members;
  - `core_renderer::activity_navigation()`'s ordering (`:469-493`), to mirror it.

  Then:
  - In `moodle/local_ltuse/db/hooks.php`, register `['hook' => \core\hook\output\after_standard_main_region_html_generation::class, 'callback' => \local_ltuse\hook_callbacks::class . '::after_main_region']`, with a one-line comment "Spec 007 R6: Next on a lesson page; Boost's course index hides core's own."
  - In `moodle/local_ltuse/classes/hook_callbacks.php`, add `public static function after_main_region(\core\hook\output\after_standard_main_region_html_generation $hook): void`:
    - it starts with the class's `isloggedin() / isguestuser() / during_initial_install()` guard;
    - it reads `$cm = $PAGE->cm;` as a plain read, as `top_of_body()` does, with the same `__isset` comment;
    - it returns unless `learner_home_rules::applies_next($PAGE->pagelayout, $PAGE->pagetype, $PAGE->context->contextlevel == CONTEXT_MODULE, (string) $PAGE->course->idnumber)`;
    - it builds the cm facts from `get_fast_modinfo($PAGE->course)->get_cms()` (`uservisible`, `is_stealth()`, `url !== null`);
    - it calls `next_cm()`, and renders nothing on `absent`;
    - otherwise it adds `<div class="ltuse-next">`, holding either an `html_writer::link($next['url'], get_string('nextlesson', 'local_ltuse', format_string($next['name'])), ['class' => 'btn btn-primary'])`, or a `btn btn-secondary` link to `course_get_url($PAGE->course)` with `backtocourse`.
  - Its docblock cites the core dispatch line. Run `php -l`, and `python -m pytest -q tests/test_learner_wording.py`.
- [X] T046 [US2] In `moodle/local_ltuse/styles.css`, add `div.ltuse-next { margin-top: 1.5rem; text-align: end; }` and `div.ltuse-next .btn { white-space: normal; max-width: 100%; }`. Layout only: no colour, and no `prefers-color-scheme`. After T032 when US1 is in flight (same file).
- [X] T047 [US2] Run the US2 gate:
  - `python -m pytest -q tests/test_payload_time_text.py tests/test_publish_moodle.py tests/test_payload_completion.py tests/test_learner_wording.py`;
  - `php tests/learner_home_harness.php`;
  - `php -l` on the changed PHP;
  - `python scripts/publish_moodle.py --slug paratext-quotation-rules --dry-run` (sends nothing, and needs no token for a dry run; if the script insists on `MOODLE_URL`, use a placeholder value, never a real token);
  - `git status --porcelain` shows no file under `modules/`.

  The PHPUnit test T037 runs in plugin CI.

**Checkpoint**: US2 code complete. It deploys with T062, and the plugin must be deployed before
the first publish with the new publisher (T004, T073, T074).

---

## Phase 5: User Story 3 — The same experience in the Android app (Priority: P2)

**Goal**: The block renders on the app's Home tab from the same data class, through a
`CoreBlockDelegate` handler, with the two offline hints shown only in the app (R7, R8). (FR-006, FR-007)

**Depends on US1**: this story extends the block US1 builds. It needs T029 (state), T030
(strings) and T031 (`\block_ltuse\output\home::context()`), so it is testable only after them.

**Independent Test**: after T029–T031, PHPUnit (T048) in plugin CI; `moodle-plugin-ci validate`
for the block; `python -m pytest -q tests/test_learner_wording.py`; `php -l`. Then (live,
post-merge) quickstart V6 and V7.

### Tests for User Story 3

- [X] T048 [US3] Extend `moodle/block_ltuse/tests/block_test.php`:
  - `test_app_view_returns_the_same_mode_as_the_web`: `block_ltuse\output\mobile::mobile_block_view([])` for a `start` learner returns one template whose HTML holds the same `start` string and cm URL as `get_content()`, plus both `offline:course` and `offline:quiz`;
  - `test_app_view_is_empty_for_a_site_admin`: the returned `templates[0]['html']` is `''`;
  - `test_web_view_has_no_offline_hint`.

  Run it in plugin CI and see it fail.

### Implementation for User Story 3

- [X] T049 [US3] Research task (constitution X, XI). Look up the following for the Moodle app 5.2.x on moodledev.io (ctx7 `/websites/moodledev_io_5_2_apis`, then the "Moodle App Plugins Development Guide"):
  - the `CoreBlockDelegate` handler keys in a block's `db/mobile.php` (`delegate`, `method`, `displaydata`, and whether `title`, `class` and `type` are needed);
  - the site-plugin method's signature for a `CoreBlockDelegate` handler: the `$args` keys it receives, whether `$USER` is the learner in that call, and the return keys (`templates`, `javascript`, `otherdata`);
  - the `core-link` directive and its `capture` attribute in the app 5.2.x;
  - `core_block_get_dashboard_blocks`' return shape for a plugin block (`MOODLE_502_STABLE` `blocks/classes/external/` or `lib/external`).

  Record what was confirmed and where, for T070, and amend `contracts/learner-ui.md`'s handler sketch if a key differs.
- [X] T050 [US3] Add the app strings to `moodle/block_ltuse/lang/en/block_ltuse.php`:
  - `offline:course` = `'To use a course without a connection: open it, tap ⋮, then Download course.'`;
  - `offline:quiz` = `'Open the quiz once while you are online; then you can finish it offline.'`.

  The exact menu wording is re-checked against the app at T077. Run `python -m pytest -q tests/test_learner_wording.py`.
- [X] T051 [US3] Create `moodle/block_ltuse/db/mobile.php` with `$addons['block_ltuse']`:
  - `handlers` → `ltuse` → `delegate: 'CoreBlockDelegate'`, `method: 'mobile_block_view'`, and the `displaydata` keys confirmed at T049;
  - `lang`, listing `[id, 'block_ltuse']` for every learner-visible string defined so far: `continue`, `start`, `coursename`, `empty`, `empty:who`, `contactsupport`, `done`, `offline:course`, `offline:quiz`. The onward ids are added by T059 (US4).

  Model it on `moodle/local_ltuse/db/mobile.php`. Run `python -m pytest -q tests/test_learner_wording.py` (T016's `db/mobile.php` check).
- [X] T052 [US3] Create `moodle/block_ltuse/classes/output/mobile.php`: `namespace block_ltuse\output; class mobile`, with `public static function mobile_block_view(array $args): array`, using the `$args` and return shape confirmed at T049.
  - For a user without `learner_home::applies($USER->id)`, it returns `['templates' => [['id' => 'main', 'html' => '']], 'javascript' => '', 'otherdata' => '']`.
  - Otherwise it returns `['templates' => [['id' => 'main', 'html' => $OUTPUT->render_from_template('block_ltuse/mobile_block', \block_ltuse\output\home::context(learner_home::state($USER->id)) + ['app' => true])]], 'javascript' => '', 'otherdata' => '']`.

  It uses the same data class and context builder as the web, which is the parity rule `local_ltuse\output\mobile` follows. Make T048 pass.
- [X] T053 [US3] Create `moodle/block_ltuse/templates/mobile_block.mustache` in the `mobile_pathways.mustache` style:
  - a docblock with `@template` and an example context, then `{{=<% %>=}}`;
  - Ionic markup: an `ion-item class="ion-text-wrap"` holding the course name and an `ion-button` link `<a href="<% url %>" core-link capture="true">` (the directive as confirmed at T049), so the lesson opens in the app's module page and not the browser;
  - the empty state, with the support link via `core-link`;
  - `done`;
  - the `onward` section, left for US4;
  - beneath everything, two `ion-note`/`ion-text color="medium"` lines with `offline:course` and `offline:quiz`. That is Ionic's muted colour, not a CSS text colour (R11).
  - Learner text carries `ngNonBindable`; strings use `<%#str%>id, block_ltuse<%/str%>`.

  Its name starts `mobile_`, so `MUSTACHE_IGNORE_NAMES` skips it in CI (T002).
- [X] T054 [US3] Run the US3 gate: `php -l` on the new files, `python -m pytest -q tests/test_learner_wording.py`, and (via T002 in CI) `moodle-plugin-ci validate` and `phpunit` for `block_ltuse`.

**Checkpoint**: US3 code complete. If V6 shows the app does not render a site-plugin block on the
Home tab, or only on the Premium plan, the fallback is a `CoreMainMenuDelegate` "Start here"
item from the same template (R7). It is not built speculatively, but row #13 does not move to
verified until either the block or the fallback shows on the app (T077, T078).

---

## Phase 6: User Story 4 — Knowing what comes next after a course (Priority: P3)

**Goal**: Beneath any mode, under "Where next": the next course of each pathway the learner
holds, "Message <mentor>" for each mentor, and the community line once spec 005 declares one.
Each is absent, never empty or broken, when its source has nothing (FR-008).

**Depends on US1**: this story extends the block US1 builds. It needs T029 (state) and T031
(the web template and `\block_ltuse\output\home::context()`), so it is testable only after them.

**Independent Test**: after T029 and T031, the harness (T055) and PHPUnit (T056) pass; then
(live, post-merge) quickstart V8.

### Tests for User Story 4

- [X] T055 [P] [US4] Extend `tests/learner_home_harness.php` with `learner_home_rules::onward(array $pathways, array $mentors, ?array $community): ?array`. It returns null when all three are empty, so the "Where next" heading is never shown alone. Otherwise it returns `{pathways, mentors, community}` with empty parts dropped and pathway lines without a `nextcourse` removed. Cover: all empty gives null; only a mentor; a pathway whose `nextcourse` is null is dropped; `community` null is omitted.
- [X] T056 [P] [US4] Extend `moodle/local_ltuse/tests/learner_home_test.php`:
  - `test_a_mentor_is_offered_as_a_message_route`: local_ltuse has no data generator and no `mentoring_test.php`, so create spec 003's mentor role with `$this->getDataGenerator()->create_role(['shortname' => 'mentor', 'name' => 'Mentor'])` and assign it with `role_assign($roleid, $mentor->id, \context_user::instance($learner->id)->id)`, as `admin_test::mentoring()` (admin_test.php:481) does. Assert `onward['mentors'][0]['url'] === (new moodle_url('/message/index.php', ['id' => $mentor->id]))->out(false)`;
  - `test_pathway_lines_are_absent_when_levels_are_not_applied`: `pathway\view::levels()` is null, so no pathway entry and no exception;
  - `test_no_community_line_without_spec_005`.

### Implementation for User Story 4

- [X] T057 [US4] In `moodle/local_ltuse/classes/mentoring.php`, make `mentors(int $userid)` `public`, with an unchanged body. Its docblock says spec 007's `learner_home` calls it, and it returns `[{id, fullname, firstname, lastname, messageurl}]` sorted by last name. Confirm `tests/mentoring_harness.php` still passes.
- [X] T058 [US4] In `moodle/local_ltuse/classes/learner_home.php`, fill `state()['onward']` through `learner_home_rules::onward()`:
  - **pathways**: only when `pathway\view::levels() !== null`. Inside a `try { … } catch (\moodle_exception $e) { /* absent */ }`, read `pathway\view::summaries($userid, assignments::pathways_for_user($userid))` and keep `{title, nextcourse: {fullname, url}}` per pathway whose `nextcourse` is set.
  - **mentors**: from `mentoring::mentors($userid)`, as `{fullname, url: messageurl}`. These are the only other users the block may name.

    Confirm the `core_message` one-to-one URL on `MOODLE_502_STABLE` and keep `/message/index.php?id=` if it is still the route (R13).
  - **community**: always `null`, with a comment "spec 005 declares the community space; until then this route is absent (FR-008)".

  Make T055 and T056 pass.
- [X] T059 [US4] Add the onward strings to `moodle/block_ltuse/lang/en/block_ltuse.php`: `onward` = `'Where next'`, `onward:pathway` = `'Next on your pathway: {$a}'`, `onward:mentor` = `'Message {$a}'`, `onward:community` = `'Community: {$a}'`. When `moodle/block_ltuse/db/mobile.php` exists (US3), also add the four ids to its `lang` list. Run `python -m pytest -q tests/test_learner_wording.py`.
- [X] T060 [US4] Fill the `{{#onward}}` section in `moodle/block_ltuse/templates/block.mustache` and in `moodle/block_ltuse/templates/mobile_block.mustache`, and extend `\block_ltuse\output\home::context()`:
  - a `div.ltuse-onward` (web) or `ion-item-divider` (app) titled by `onward`;
  - one link per pathway, one per mentor, and the community line when set;
  - app links use `core-link capture="true"`;
  - the web example context includes an onward section, so the mustache lint covers it.

  Make T019, T048 and T056 still pass.
- [X] T061 [US4] Run the US4 gate: every `php tests/*_harness.php`, `python -m pytest -q tests/test_learner_wording.py`, `php -l`, and plugin CI for both plugins.

**Checkpoint**: All four stories are code complete.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: The one version bump, documentation, cross-spec pointers, the requirements row,
and the live checks that verify row #13.

- [X] T062 Bump `$plugin->version` in `moodle/local_ltuse/version.php` once for this merge (the next `YYYYMMDDXX` after `2026100900`), add a line to its running comment chain naming spec 007, and raise `release` to `'0.14.0'`. No schema, so no `db/upgrade.php` savepoint. In the same commit:
  - move the `local_ltuse` `version:` pin in `moodle/site/site.yaml` to the same value;
  - set `moodle/block_ltuse/version.php` `dependencies` to `['local_ltuse' => <that value>]`.

  Run `python scripts/site_config.py validate` and `python -m pytest -q tests/test_site_config.py`.
- [X] T063 [P] Update `moodle/local_ltuse/README.md`:
  - a **Spec 007** section in the per-spec style, with a components table:
    - Next button: `db/hooks.php`, `classes/hook_callbacks.php` `after_main_region`, `classes/learner_home_rules.php`;
    - Learner state: `classes/learner_home.php`;
    - Dashboard declaration: `classes/siteconfig/dashboard.php`, `dashboard_plan.php`;
    - Section summaries: `classes/external/update_sections.php`;
  - "What it adds": add the `local_ltuse_update_sections` row, missing today, and fix the stale line about `local_wsmanagesections` (about L38);
  - "What the plugin relies on" (Principle XI): `after_standard_main_region_html_generation`, `course_update_section()` with `summary`, `blocks_delete_instance()`, `my_reset_page_for_all_users()`, `completion_info`, `get_fast_modinfo`, and `enrol_get_all_users_courses`;
  - the raw-reads row (about L750) gains:
    - the personal-dashboard count (`my_pages` `userid IS NOT NULL`, `name '__default'`, `private 1`);
    - every block instance on the default page (no region filter), plus `defaultweight` and `block_positions` if read;
    - `user_lastaccess` if read directly;
    - the live `role_capabilities` read for `moodle/my:manageblocks`;
  - a direct-writes entry only if T026 needed the `defaultweight` fallback;
  - L728 "It never deletes anything" is qualified for the dashboard;
  - the "Install" section gains the `block_ltuse` copy to `public/blocks/ltuse`, the order (local_ltuse first, then the block, then `upgrade.php`, then `apply`) and the LF archive command.
- [X] T064 [P] Create `moodle/block_ltuse/README.md`:
  - what the block does (four modes, onward routes, app view);
  - its components: `block_ltuse.php`, `classes/output/home.php` (the shared context builder), `classes/output/mobile.php`, `db/mobile.php`, `templates/`;
  - that it holds no data and depends on local_ltuse;
  - install, pinned in `site.yaml` and placed by `dashboard.yaml`, never by hand;
  - what it relies on (`block_base`, `CoreBlockDelegate`), and that it is verified against Moodle 5.2.3+;
  - what it deliberately does not do: no level, no "certified", no link into a hidden section, nothing for site admins.
- [X] T065 [P] Update `moodle/site/README.md`:
  - the file table row for `dashboard.yaml` (L22) now reads "The whole default Dashboard: the learner home block, the course list, Upcoming events (specs 007, 011)", and there is a row for `settings/learner-experience.yaml`;
  - a new `## The learner experience` section after `## Office hours`: what a learner lands on, why editing is prevented, what `complete` and `personal_dashboards: reset` do, and that `MOODLE_SUPPORT_EMAIL` is a provisioning value;
  - "Expected differences" (L117) gains `supportemail` (`MOODLE_SUPPORT_EMAIL` unset in this shell), as for `badges_defaultissuercontact`, and notes that once applied, `personal dashboards` does not appear;
  - "Changing something" (L64) documents the `{path: moodle/<plugin>}` pin for our own plugins beside the `url` + `sha256` one.
- [X] T066 In `moodle/site/settings/mobile.yaml`, change `mobilecssurl`'s value to `env:MOODLE_URL/local/ltuse/styles.css?v=3`. This is this merge's one bump, covering T032 and T046. Its `why:` already says to raise `v=` whenever `styles.css` changes; the app re-reads it only after logout and login. Run `python scripts/site_config.py validate`.
- [X] T067 [P] In `specs/001-site-config-as-code/contracts/declaration.md`:
  - add a 007 row to the Extensions table (L21-40): `settings/learner-experience.yaml`; `roles.yaml` `user` `moodle/my:manageblocks: prevent`; `site.yaml` `block_ltuse` path pin; the `dashboard.yaml` `complete` / `weight` / `personal_dashboards` amendment;
  - add a "Spec 007 extends it again" paragraph;
  - qualify the spec 011 paragraph's "Apply never deletes … a dashboard block" with "unless `dashboard.yaml` declares `complete` (spec 007)".
- [X] T068 [P] In `specs/011-events-calendar/contracts/declaration.md`, add a pointer to `specs/007-learner-experience/contracts/dashboard-declaration.md` at L10, in "Payload arrays" (L37: `dashboard` items may carry `weight`, and the sibling keys `dashboard_complete` and `dashboard_personal` follow), at L45 ("never removes a block, never resets a user's own dashboard": now unless 007's keys say so), and in Validation (L71).
- [X] T069 [P] Close spec 004's handoff:
  - in `specs/004-progress-reporting/research.md` R6, change the "**Status**" line to say spec 007 builds the continue link (007 plan decision 1, research R3) and owns the myoverview groupings (007 R2);
  - in `specs/004-progress-reporting/plan.md` "Decisions on the plan's limits" row 1, point to spec 007 the same way, leaving the maintainer's confirmation of 007 decision 1 as the open item.
- [X] T070 [P] In `specs/007-learner-experience/research.md` R13, move each API confirmed at T007, T011, T020, T026, T029, T041, T042, T045, T049 and T058 from "To confirm" to "Confirmed", with the `MOODLE_502_STABLE` file:line read. Any that turned out different from the plan is noted with what changed.
- [X] T071 Update `moodle/REQUIREMENTS.md`:
  - row #13 to **Built ([spec 007](../specs/007-learner-experience/spec.md)), <date>; not yet verified on the instance.**, then one sentence each:
    - the Dashboard as home, with three declared blocks and learners unable to customise;
    - the learner home block (Start/Continue, the empty state with Contact site support, onward routes, app Home tab);
    - Next on every lesson page;
    - each lesson's estimated time as its section summary;
    - SIL Blue as Boost's brand colour;
  - **Still pending:** quickstart V1–V8, 2–3 real partner learners (V9, FR-014), and SC-005's full rebuild-and-restore check, which is spec 015's. Claim no live success criterion as met;
  - in row #7's bullet (L38), replace "if learners can't find it, spec 007 adds one" with "spec 007 adds it (the learner home block's Continue)".
- [X] T072 Confirm `scripts/moodle_client.py` `REQUIRED_FUNCTIONS` (L254, the list `--whoami` checks) needs no change: spec 007 adds no web service function, only a parameter. Its comment (L251-253) gains "spec 007 added a parameter to update_sections". Record that in the PR.
- [X] T073 Run the full local gate before merge:
  - `python -m pytest -q tests/`;
  - every `php tests/*_harness.php`;
  - `python scripts/site_config.py validate`;
  - `python scripts/check_course_package.py`;
  - `python scripts/quiz_parse.py --check-all`;
  - `python scripts/gen_coverage.py` (no diff);
  - `python scripts/publish_moodle.py --slug paratext-quotation-rules --dry-run`;
  - `git diff --name-only main -- modules/` (empty);
  - `git status --porcelain` clean after the dry run;
  - confirm the repository variable `MOODLE_ENABLED` is not `true` (`gh variable list`). If it is, deploy local_ltuse (T074 steps 1–3) before merging, or `moodle-publish.yml` publishes with the new publisher against the old plugin (T004).
- [ ] T074 (live, post-merge) Deploy and apply, in this order (quickstart Prerequisites as amended at T006):
  1. `git log main..origin/main` empty;
  2. archive `moodle/local_ltuse` and `moodle/block_ltuse` with `git -c core.autocrlf=false archive` and copy them to `public/local/ltuse` and `public/blocks/ltuse`;
  3. `php -d max_input_vars=5000 admin/cli/upgrade.php --non-interactive`;
  4. `python scripts/site_config.py drift` (expect: dashboard, myoverview grouping, brandcolor, supportemail and personal-dashboard lines, and no blocking `missing` for `block_ltuse`);
  5. `apply`;
  6. `drift` again, which must be empty apart from the documented Expected differences, with no `personal dashboards` line. This empty drift, with `block_ltuse` installed from its pin and placed by apply alone, is the SC-005 evidence on this instance. The full rebuild-and-restore check is spec 015's.

  If the first drift shows any of `defaulthomepage`, `enabledashboard`, `enablemycourses` or `enablemyhome`, R1's live reading was wrong: record which. Commit only pass/fail.
- [ ] T075 (live, post-merge) Quickstart V1–V3 with test accounts **A** and **B**: the Start button and the course list only; the empty state and the Contact site support form addressed to the provisioned address; the narrow phone width; no "Customise this page"; All / In progress / Past only; SIL Blue buttons and the pale tinted panel; after lessons 1–2, "Continue: <lesson 3>" in one click.
- [ ] T076 (live, post-merge) Quickstart V4–V5: each lesson section shows its time; a quiz section shows none; Done / To do; the Retired section is invisible; "Next" twice reaches lesson 3; "Back to the course" on the last module; the app's own arrows. First publish `paratext-quotation-rules` with the new publisher (after T074), and confirm a second publish prints `summaries 0` and `0 updated`. In a dry-run payload of a course with a withheld quiz, the quiz section's `time_text` is `""` and the placeholder page that names who to ask is the section's only module; Next from the last lesson leads to it (spec Edge Cases). Record the app version.
- [ ] T077 (live, post-merge) Quickstart V6–V7 (SC-004) on an Android device with the Moodle app 5.2.x:
  - the Home tab renders the block at its position;
  - Continue opens the lesson in the app;
  - the hint's menu wording matches the app, or fix `offline:course` / `offline:quiz` and record the app version;
  - the course menu's Download course is offered inside a course (FR-007);
  - offline lessons and quiz, and one offline lesson whose Watch-the-video link cannot load still shows its text and screenshots (US2-3);
  - after the device logs out and in, the panel shows the `?v=3` styles in the app's light and dark schemes.

  Record the app version, the device and the app plan. If the block does not render, record it, build the R7 `CoreMainMenuDelegate` fallback (a new task in the owning story's files) and re-run V6 before T078 moves the row.
- [ ] T078 (live, post-merge) Quickstart V8 with **C**, **D** and a site administrator: the onward routes work; there is no community line; Mentoring, Pathways and My organisation are still where they were; the admin sees no block content. Then record dates, app version and device for T074–T078 in `moodle/REQUIREMENTS.md` row #13, and move it to **verified** only if V6 showed the learner home on the app's Home tab, the block or the R7 fallback. Otherwise the fallback is built and V6 re-run first. This comes ahead of Phase 8.

---

## Phase 8: Pilot and row #13 (FR-014, SC-001–SC-004, SC-006)

**Purpose**: Row #13 is a "simple" row: it is not done until 2–3 real partner learners have
used it and their findings are recorded (constitution X). Runs after T074–T078. SC-005 is
evidenced at T074 and completed by spec 015 (T071).

- [ ] T079 (live, post-merge) At the first pilot session, create `specs/007-learner-experience/pilot-findings.md`. It has a short header (what the file is, that it is de-identified, the date range) and one table with the data-model §5 columns. Quote these rules in the header verbatim:
  - `id`: "`F<n>`, in order of recording";
  - `when`: "date of the session (no time of day)";
  - `who`: "role and context only … The organisation is a letter assigned in the file, never its key or name if that could identify the person, and never a region";
  - `device`;
  - `tried`;
  - `happened`: "in observable terms";
  - `measure`: "which success criterion it bears on (SC-001 … SC-004), and the observed value";
  - `outcome`: "`resolved` (with the commit or PR that fixed it) · `accepted` (with the reason and who accepted) · `open`";
  - and the "Never recorded" line: names, email addresses, usernames, account ids, screenshots showing a person, or progress data beyond the one measured value.
- [ ] T080 (live, post-merge) Run quickstart V9 with two or three real partner learners who have never been shown Moodle. Give only login details and observe without helping. Record each finding in `pilot-findings.md` during or straight after the session, de-identified per T079. Measure:
  - SC-001: first lesson within 5 minutes, for at least 2 of 3;
  - SC-002: 2 or fewer taps to the lesson left off, web and app;
  - SC-003: zero uses of Moodle's menus across two lessons;
  - SC-004: one offline app session.

  Do not commit any name, account, organisation key or region.
- [ ] T081 (live, post-merge) Take each `open` finding to `resolved`, with a fix through the owning story's files and that PR cited, or to `accepted`, with the reason and the maintainer as acceptor (SC-006). A fix that changes a declaration re-runs `site_config.py validate` / `apply` / `drift`. A fix that touches section 0 (R10) is a new design question for the maintainer, not a quiet change.
- [ ] T082 (live, post-merge) When at least two learners are recorded, no finding is `open`, and the app shows the learner home (the block or the R7 fallback), mark `moodle/REQUIREMENTS.md` row #13 **done**, citing `pilot-findings.md`, in the same PR as the last finding's outcome (constitution X, FR-014). Until then it stays **verified** at most.

---

## Dependencies & Execution Order

### Phase dependencies

- **Setup (Phase 1)**: none. T001–T006 are independent.
- **Foundational (Phase 2)**: after Setup. T007 → T008 (the pin needs `version.php`). T009 → T010. T011 after T010. Blocks all stories.
- **US1 (Phase 3)**: after Phase 2.
- **US2 (Phase 4)**: after Phase 2 (T010). Independent of US1 at code level, except for shared files: T043 edits `learner_home_rules.php` after T028, T036 edits the harness after T015, T046 edits `styles.css` after T032, and T038 extends T016's `tests/test_learner_wording.py` (T044's lang block is checked by it). Run those sequentially if both stories are in flight.
- **US3 (Phase 5)**: extends US1: after T029–T031 (US1's state, strings and `\block_ltuse\output\home::context()`).
- **US4 (Phase 6)**: extends US1: after T029 and T031. T060 also edits the US3 template, so it comes after T053 when US3 is included, and T059 edits `db/mobile.php` after T051.
- **Polish (Phase 7)**: T062 after every included story's plugin code. T063–T072 after the stories they document. T073 last before merge. T074–T078 after merge, in order.
- **Pilot (Phase 8)**: after merge and T074–T078.

### Within stories

- US1: T012–T019 (fail first; T013 after T012, same file; T016 after T014, same workflow file) → T020, T021, T022 in parallel → T023 → T024 → T025 → T026 → T027. Separately, T028 → T029 → T030 → T031. T032 any time. T033 last.
- US2: T034–T038 (fail first) → T039 → T040. T041 → T042. T043 → T044 → T045. T046 any time (after T032 if US1 is in flight). T047 last.
- US3: T048 (fail first). T049 before T050–T053. Then T050 → T051 → T052 → T053 → T054.
- US4: T055, T056 (fail first) → T057 → T058 → T059 → T060 → T061.

### Story dependency summary

```text
Setup ──► Foundational (block skeleton + pin, learner_home_rules, learner_home)
              │
              ├──► US1 (MVP: dashboard declaration, state, web block) ──┬──► US3 (app view)
              │                                                       └──► US4 (onward routes)
              └──► US2 (time_text, update_sections, Next hook)
                                   all ──► Polish (T062–T073) ──merge──► live T074–T078 ──► Pilot T079–T082
```

---

## Parallel examples

### User Story 1

```text
Task: "T012 Update spec 011 test fixtures in tests/test_site_config.py"
Task: "T014 Create tests/dashboard_plan_harness.php"
Task: "T015 Continue-rule cases in tests/learner_home_harness.php"
Task: "T017 Create moodle/local_ltuse/tests/learner_home_test.php"
Task: "T018 Create moodle/local_ltuse/tests/siteconfig_dashboard_test.php"
Task: "T019 Create moodle/block_ltuse/tests/block_test.php"
# then, after T012 and T014 (same files):
Task: "T013 Dashboard007 class in tests/test_site_config.py"
Task: "T016 Create tests/test_learner_wording.py"
# then:
Task: "T020 Create moodle/site/settings/learner-experience.yaml"
Task: "T021 user moodle/my:manageblocks prevent in moodle/site/roles.yaml"
Task: "T022 Redirect the myoverview comment in moodle/site/settings/completion.yaml"
```

### User Story 2

```text
Task: "T034 Create tests/test_payload_time_text.py"
Task: "T035 Summary-send cases in tests/test_publish_moodle.py"
Task: "T036 Next-cm cases in tests/learner_home_harness.php"
Task: "T037 Create moodle/local_ltuse/tests/update_sections_test.php"
Task: "T038 Spec 007 local_ltuse block in tests/test_learner_wording.py"
# then, on separate files:
Task: "T039 time_text in scripts/moodle_payload.py"
Task: "T041 summary + unchanged-skip in moodle/local_ltuse/classes/external/update_sections.php"
Task: "T043 next_cm/applies_next in moodle/local_ltuse/classes/learner_home_rules.php"
```

### User Story 3

```text
Task: "T048 App-view cases in moodle/block_ltuse/tests/block_test.php"
Task: "T049 Confirm CoreBlockDelegate keys, method shape, core-link and core_block_get_dashboard_blocks"
```

### User Story 4

```text
Task: "T055 onward() cases in tests/learner_home_harness.php"
Task: "T056 Onward cases in moodle/local_ltuse/tests/learner_home_test.php"
```

### Polish

```text
Task: "T063 moodle/local_ltuse/README.md"
Task: "T064 moodle/block_ltuse/README.md"
Task: "T065 moodle/site/README.md"
Task: "T067 specs/001-site-config-as-code/contracts/declaration.md"
Task: "T068 specs/011-events-calendar/contracts/declaration.md"
Task: "T069 spec 004 research R6 and plan limits row 1"
Task: "T070 research.md R13 confirmations"
```

---

## Implementation Strategy

### MVP first (Phase 1 + Phase 2 + US1)

1. T001–T006: CI triggers and the settled contracts.
2. T007–T011: the block, its pin and the shared rule.
3. T012–T033: the declared Dashboard, the learner state, the web block.
4. If the MVP is merged alone, it is one merge with its own Polish run: T062, T066, T071 and
   T073 once for it, then T074–T075. **Stop and validate**: a new learner lands on Start, a
   learner with no course sees who to ask, and nothing on the Dashboard can be customised
   (SC-001's precondition, SC-002 on the web, and T074's empty second drift as this instance's
   SC-005 evidence for the Dashboard).

### Incremental delivery

All included stories normally merge together, with T062, T066, T071 and T073 run once. A story
shipped in a later merge repeats T062, T066 and T073 with the next `YYYYMMDDXX` and `?v=`.

1. MVP → the Dashboard.
2. US2 → times on the course page and Next on every lesson (SC-003). The plugin is deployed
   before the first publish with the new publisher (T004, T073).
3. US3 → the same block in the app, with the offline hints (SC-002 in the app, SC-004).
4. US4 → onward routes (FR-008).
5. Polish → row #13 **built** at merge. T074–T078 move it to **verified**, and Phase 8 makes it
   **done**.

### Notes

- Look up every Moodle API in Context7 (`/websites/moodledev_io_5_2_apis`, `/moodle/moodle`) and
  then in `MOODLE_502_STABLE` source before writing PHP: T007, T011, T020, T026, T029, T041,
  T042, T045, T049, T058. Never edit vendored code (constitution XI).
- Never write `MOODLE_TOKEN`, `MOODLE_SUPPORT_EMAIL`'s value, a learner's name or email, or a
  live count into any file. GitDoc auto-commits the working tree.
- Do not touch `modules/`. Quiz sections having no time line is accepted, not fixed in content.
- Commit after each task or logical group. The PR description lists plan decisions 1, 2, 3 and 5
  for the maintainer.
