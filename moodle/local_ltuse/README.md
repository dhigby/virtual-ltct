# `local_ltuse` — the publish endpoint

A small Moodle local plugin exposing the few web service functions the LTC curriculum
publisher needs. Moodle has no core web service that writes a quiz; these four functions
close that gap, each a thin wrapper over the same internal APIs the web UI calls. After
installing it, [`scripts/publish_moodle.py`](../../scripts/publish_moodle.py) talks plain
REST, and the contract is defined around our content model rather than bent to fit
someone else's.

It lives in this repo rather than its own because it and the publisher are two halves of
one contract: a change to the page shape or the quiz model touches both, and one pull
request should carry both. [`INTENT.md`](../../INTENT.md) names it, with the publisher,
as the bridge between the repo's two products — the only code that knows both a course's
shape and Moodle's.

## What it adds

| Function | Type | Does |
|---|---|---|
| `local_ltuse_get_course_manifest` | read | Returns the repo→Moodle map for one course: its sections, and every module carrying an `ltct:` idnumber. |
| `local_ltuse_create_page` | write | Creates or updates one `mod_page`, addressed by course-module idnumber. |
| `local_ltuse_import_questions` | write | Imports Moodle XML into a category in the course question bank, creating the `mod_qbank` instance and category if needed. |
| `local_ltuse_create_quiz` | write | Creates or updates a `mod_quiz` and rebuilds its slots as references into that category. |

Course create/update stays on core (`core_course_create_courses`,
`core_course_update_courses`, `core_course_get_courses_by_field`) and section handling on
[`local_wsmanagesections`](https://moodle.org/plugins/local_wsmanagesections). Neither is
duplicated here.

`get_course_manifest` is the one function not in the original sketch, and the publish is
not idempotent without it: `core_course_get_contents` does not reliably return a module's
idnumber, so there is otherwise no way to ask Moodle which modules a previous publish
created. With it, republishing is a diff rather than a blind re-create.

## Republishing touches only what changed (spec 009)

Saving a page bumps its `revision`, which Moodle puts in every image URL on it, and
re-uploading an image gives it a new `timemodified`. Either makes the Moodle app download
that page's images again, on a learner's metered connection. So a republish writes only
what differs:

- **`get_course_manifest`** also returns, per page, the files in its `mod_page/content`
  area with their `contenthash` (SHA-1), read with `file_storage::get_area_files()`. Still
  read-only, same capability. The publisher compares those hashes with the images it is
  about to send.
- **`create_page`** returns `outcome`: `created`, `updated` or `unchanged`. When name,
  section, visibility, intro and content all match what is stored and no files are to
  change, it writes **nothing**: no `update_moduleinfo()`, no event, no revision bump.
- **`create_page` with `syncfiles`** makes the page's file area exactly the uploaded draft
  plus `keepfiles`. Kept files are copied from the page's own area into the draft with
  `file_storage::create_file_from_storedfile()`, which keeps their stored `timemodified`,
  and given the same "original" source record core's `file_prepare_draft_area()` writes.
  That record is what makes `file_save_draft_area_files()` keep the existing file rather
  than delete and re-create it with a new time. When nothing was uploaded (a page that
  only lost an image), the draft comes from `file_get_unused_draft_itemid()`. A stored file
  in neither list is deleted by the save, as before.

A publisher that sends neither new parameter gets the old behaviour, so an older
publisher keeps working against this plugin, and the new publisher omits them whenever no
file changes, which keeps it working against an older plugin.

## Offline quizzes (spec 009)

`create_quiz` sets `allowofflineattempts = 1` on create and on update. Without it the
Moodle app will not download a quiz, and a consultant without a connection cannot take it.
The setting has no admin default (it belongs to `quizaccess_offlineattempts`, which has no
settings page), so there is nothing to declare in `moodle/site/`; the plugin sets it per
quiz. That rule's four conditions (no time limit, no subnet, non-sequential navigation,
deferred feedback or deferred CBM) are checked only by its form validation, which
`add_moduleinfo()` never runs, so `create_quiz` checks them itself and refuses a quiz that
could not go offline, naming the setting at fault.

## Identity, and why republishing does not duplicate

Every object the publisher creates carries an idnumber:

```
course         ltct:<slug>
course module  ltct:<slug>:<source filename>      e.g. ltct:bloom:01-what-bloom-is.md
```

That is the whole idempotency story, and it lives **in Moodle**, not in a state file in
the repo. Nothing new to keep honest, it survives someone else republishing, and moving
to a different Moodle server is a re-publish rather than a data move.

## Install

```bash
# On the Moodle server, from the Moodle root:
cp -r /path/to/virtual-ltct/moodle/local_ltuse public/local/ltuse
php -d max_input_vars=5000 admin/cli/upgrade.php --non-interactive
```

The `-d max_input_vars=5000` is there because the upgrade's environment check reads the
CLI's `php.ini`, which on the build host is set lower than the web server's.

Then, from your own machine, apply the site declaration and create the publishing account:

```powershell
python scripts/site_config.py apply      # settings, plugin states and roles from moodle/site/
```

```bash
# On the server again:
php public/local/ltuse/cli/setup_publishing.php --token-file=/home/ltuse/.ltuse-token --email=ADDRESS
```

`apply` turns on web services and the mobile app service, sets `mobilecssurl` so published
callouts render in the Android app, and creates the `ltcpublisher` role with exactly the
capabilities the publisher uses. `local/ltuse:publish` is deliberately in no archetype,
because this token rewrites course content wholesale. `setup_publishing.php` then creates
the account, authorises it on the restricted *LTC curriculum publishing* service, and writes
the token to a mode-600 file. It never prints the token. That token is `MOODLE_TOKEN`.

The repo is public, so the token goes in the environment and never in a file here.

## Site configuration: `cli/site_config.php`

The applier and drift check behind `scripts/site_config.py`. It reads the declaration as JSON
on stdin and is never run by hand. See [`moodle/site/README.md`](../site/README.md). The code
is in `classes/siteconfig/`. `inspector` reads and compares, and writes nothing. `applier` and
`drift` act on its results, and `report` prints them, redacting secrets.

It uses Moodle's public APIs: `admin_setting::write_setting()`, plugininfo `enable_plugin()`,
`create_role()`, `set_role_contextlevels()`, `assign_capability()` and
`unassign_capability()`. It makes one raw read:

| Table | Read by | Why there is no API |
|---|---|---|
| `role_capabilities` | `roleid`, `contextid` (system) | Drift compares a role's own system-context permissions. `role_context_capabilities()` merges parent contexts, which is not that comparison. |

### Organisations, cohorts and profile fields (spec 002)

The applier also handles four item types after settings. They are applied in this order:

| Class | Writes through | Reads |
|---|---|---|
| `categories` | `core_course_category::create()`, `->update()` | `course_categories` by `idnumber`, then by `parent` and `name` to find a category to adopt |
| `cohorts` | `cohort_add_cohort()`, `cohort_update_cohort()` | `cohort` by `idnumber`, in any context |
| `profilefields` | `profile_save_category()`, `profile_save_field()` | `user_info_category` by `name`, `user_info_field` by `shortname` |
| `cohortrules` | `tool_dynamic_cohorts` API: `rule_manager::process_form()`, then the `rule` persistent's `set('enabled', 1)` and `save()`, as the plugin's own `toggle_status` does; `rule_manager::delete_rule()` is never called by apply | rules through the `rule` persistent's `get_records()`, conditions through `get_condition_records()` |

None of them writes another component's table.

**The profile hook.** `lib.php` defines `local_ltuse_control_view_profile()`, the callback core's `user_can_view_profile()` calls through `user_process_profile_callbacks()`. It refuses an organisation manager the profile of anyone outside the organisations they manage (spec 002, research R9). It never allows anything core would refuse. It reads:
- `profile_user_record()` for the viewed user's `ltct_org`;
- `has_coursecontact_role()` and `has_capability('moodle/user:viewalldetails')` at system context, to recognise staff;
- `has_capability('moodle/user:viewalldetails')` in the viewed user's context, to exempt the site team and mentors.

It runs only while `forceloginforprofiles` is on (declared in `moodle/site/settings/groups.yaml`).

**Raw reads added by spec 002.** All are reads of core tables; none is a write.

| Table | Read by | Indexed? | Why there is no API |
|---|---|---|---|
| `course_categories` | `idnumber`; `parent` and `name` | `parent` is; `idnumber` is not | No core function finds a category by `idnumber`, or lists the candidates for adoption. |
| `cohort` | `idnumber` | No: core indexes only `contextid` | `cohort_get_cohort()` takes an id. Finding a cohort by `idnumber` in any context is needed to report one in the wrong context instead of duplicating it. |
| `user_info_category` | `name` | No | A profile field category has no `idnumber`. Its name is its identity. |
| `user_info_field` | `shortname` | No (unique only by validation) | `profile_get_custom_field_data_by_shortname()` exists. The class reads the row directly so it can compare every column. |
| `cohort` joined to `cohort_members` | `cm.userid`, `c.contextid`, `c.idnumber LIKE 'ltct:org:%:managers'` | `cohort_members.userid` is | `cohort_get_user_cohorts()` returns only visible cohorts, and every managers cohort is hidden. One query per request, cached. |
| `course_categories`, `cohort`, `user_info_field` | `idnumber` or `shortname` prefix (`ltct:`, `ltct_`) | as above | Drift's scan for undeclared items. There is no core listing by prefix. |

## Verified against Moodle 5.2.3+ (2026-09-29)

Installed and exercised end to end on Moodle 5.2.3+ (Build 20260928), PHP 8.3, PostgreSQL
16. A full publish of `coretech-computer-hardware` creates 6 pages, 7 sections and a
27-question quiz, and republishing changes nothing. The four things the first draft asked
you to verify have now been settled, three of them by failing:

1. **`mod_qbank`** works as assumed. `util::ensure_qbank()` creates the instance in
   section 0 and `qformat_xml` imports into a category inside its context.
2. **`quiz_add_quiz_question()` still resolves** in 5.2 and adds slots correctly.
3. **`quiz_update_sumgrades()` is gone.** Grade calculation moved into
   `mod_quiz\grade_calculator`, reached via `quiz_settings::create($id)
   ->get_grade_calculator()->recompute_quiz_sumgrades()`. Without it the quiz reports a
   maximum grade of zero, which reads like a failed import rather than a grading bug.
4. **`questions_in_category()` must filter on `status = ready`, not `<> draft`.**
   `question_delete_question()` will not delete a question a quiz slot references -- it
   hides it, to keep attempt history readable -- so a republish leaves the retired copies
   behind. Counting those too gave a quiz with exactly twice the questions it should have.

Three further things this cost, worth knowing if you port it:

- **`add_moduleinfo()` needs `module`** (the numeric `modules.id`), not just
  `modulename`. `course_modules.module` is NOT NULL, and the web UI supplies it from a
  hidden form field.
- **`get_moduleinfo_data()` returns five values**, `[$cm, $context, $module, $data, $cw]`.
  Destructuring two hands back the context object and fails much later with an empty
  module name.
- **mod_quiz's password field is `quizpassword`** on the form; `quiz_add_instance()` does
  `$quiz->password = $quiz->quizpassword`, so setting `password` alone is discarded and
  the insert fails on a NOT NULL column you believe you set. `create_quiz::quiz_defaults()`
  fills every quiz column from the live column metadata for this reason.

`$plugin->requires` is pinned to `2026042000` (Moodle 5.2) -- the release this was
verified against. Raise it deliberately, not reflexively: the pin is what makes a
question-bank change fail at install time rather than mid-publish.

**`local_wsmanagesections` is no longer used.** It could not be installed here, so
`update_sections` wraps core's `course_create_sections_if_missing()` and
`course_update_section()` instead. The publisher now depends on nothing but Moodle core
and this plugin.

## What it deliberately does not do

- **No Moodle → repo sync.** One-way only. Editing in Moodle and syncing back would break
  the source-of-truth split the whole repo rests on. Content edited in Moodle is
  overwritten by the next publish; change the markdown instead.
- **No enrolment, grades or learner records.** This plugin publishes content and touches
  no learner data. Learner data is Moodle's alone (`INTENT.md`: *"Learner data lives in
  Moodle, never in this repo"*); admin tooling that works with it is a separate concern.
- **No direct table writes** for anything a Moodle API covers. Bypassing
  `add_moduleinfo()` / `update_moduleinfo()` would skip grade items, completion, events
  and the file API, and leave a course that looks right until one of those is needed.
  Spec 009 adds none: files go through `file_storage` and pages through
  `update_moduleinfo()`.
