# `local_ltuse` — the publish endpoint

A small Moodle local plugin exposing the few web service functions the LTC curriculum
publisher needs. Moodle has no core web service that writes a quiz; these functions
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
| `local_ltuse_hide_modules` | write | Retires modules the course no longer has, by course-module idnumber: hides them and moves them into the hidden Retired section. Never deletes. |
| `local_ltuse_ensure_discussion` | write | Spec 012, row #10. Creates the course's one `general` forum, `ltct:<slug>:discussion`, in section 0 if it is absent; its name and intro are set only then. On every call it sets only the forum's group mode: separate groups, or visible groups when [`moodle/site/course-discussions.yaml`](../site/course-discussions.yaml) lists the course as shared, always with no grouping. It never writes a discussion or post. Returns `{cmid, created, groupmode, courseforced}`; `courseforced` means the course's own forced group mode overrides the forum's. |
| `local_ltuse_set_course_completion` | write | Makes a course's activity completion criteria exactly its visible, tracked `ltct:` modules, one criterion at a time. Never clears a learner's course completion (spec 004). |
| `local_ltuse_set_course_competencies` | write | Replaces the competencies a published course aims at, by name, in the plugin's own map table. Fails closed on a name the site does not have (spec 004). |
| `local_ltuse_set_course_recognition` | write | Creates or rewords a published course's completion badge, activates it on a delivery publish, and on delivery makes its certificate activity. Never deactivates a badge or deletes a certificate (spec 013). |

**The discussion forum and organisations.** Separate groups hides a group's discussions from
anyone without `moodle/site:accessallgroups`, and every group in a course belongs to one
organisation (spec 002), so with no grouping no organisation reads another's posts. The one
way round it is a discussion posted to "All participants", which only a user with
`accessallgroups` can do (editing teachers and managers, never the Course mentor or a learner).
`site_config.py drift` reports such discussions as `allparticipants`, as a count only.

Course create/update stays on core (`core_course_create_courses`,
`core_course_update_courses`, `core_course_get_courses_by_field`) and section handling on
[`local_wsmanagesections`](https://moodle.org/plugins/local_wsmanagesections). Neither is
duplicated here.

`get_course_manifest` is the one function not in the original sketch, and the publish is
not idempotent without it: `core_course_get_contents` does not reliably return a module's
idnumber, so there is otherwise no way to ask Moodle which modules a previous publish
created. With it, republishing is a diff rather than a blind re-create.

## A module the repo no longer has is retired, never deleted

Rename, renumber or remove a lesson in the repo, and its old module is still in Moodle
under an idnumber the publisher no longer produces. At the end of every publish, once
everything new exists, the publisher compares the server's `ltct:<slug>:` modules with
the payload and sends the leftovers to `hide_modules`, skipping any an earlier publish
already retired. That function checks every idnumber before it changes anything, refuses
the course question bank, then:

- moves each module into **"Retired: no longer in the course"**, a hidden section kept
  after every lesson section, created on first need (`cmactions::move_end_section()`,
  core's 5.2 replacement for the deprecated `moveto_module()`);
- hides it, as core's own hide action does (`core_course_external::edit_module`,
  `'hide'`): `set_coursemodule_visible()`, then the `course_module_updated` event.

**Why a section, not just hidden.** *Next activity* offers whatever the viewer can open,
hidden or not (`core_renderer::activity_navigation()`). Hidden in place, an old copy sat
between lessons for every admin, teacher or mentor walking the course; learners never
saw it. In the Retired section it comes only after the last real activity. The publisher
sets each course's *Hidden sections* to **Hide completely**, so learners never see even
the section's name.

**Why not deleted.** A learner's attempt and grade live on the module: deleting it would
take them away, and a mistaken publish could not be undone. A teacher can still open it.
If the file comes back, the next publish finds the module by its idnumber,
`util::upsert_module()` moves it home, and `update_moduleinfo()` shows it again
(`course/modlib.php:772`, MOODLE_502_STABLE). Modules without an `ltct:` idnumber, the
ones a person made by hand, are never touched.

**Keeping the section last.** Lesson N lives in section N, so a course that gains a
lesson may need the Retired section's number. `update_sections` moves the Retired section
past the lessons first (`util::keep_retired_last()`), so it is never named as one. It is
found by its exact name, which is plain text rather than a lang string so no translation
can hide it from the publisher.

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

## Progress reporting (spec 004)

Built 2026-10-02 and **not yet verified on the instance** (constitution X). Everything below
describes the code; none of it has been exercised on a live Moodle yet. The pure parts are
checked by `tests/criteria_harness.php` with a bare PHP CLI, and the per-competency
datasource by the PHPUnit test `tests/competency_coverage_test.php`, on synthetic data.

### Completion rules on pages and quizzes

`create_page` and `create_quiz` take an optional `completion`: `view`, `submit` or `pass`.
`completion_rule` is the one place those words become moduleinfo fields
(`completion = 2` with `completionview`, or with `completionusegrade` and
`completionpassgrade`). An unknown word is refused, never mapped to a default. Both
functions return `completion`: `set`, `unchanged`, `differs`, or empty when no rule was sent.

`util::completion_outcome()` decides, before anything is written:

- no rule sent: empty, and completion is not touched, which is what an older publisher gets;
- **completion off for the site or the course** (`completion_info::is_enabled()`): refused
  with `error:completionoff`. Core silently drops completion fields when it is off, so
  reporting `set` would claim a write that never happened;
- a new module: `set`;
- the stored row already carries the rule: `unchanged`;
- the module is untracked and is not an activity criterion of its course: `set`. The update
  passes `completionunlocked = 1`, which `update_moduleinfo()` needs;
- anything else: `differs`, and nothing about completion is written. The publisher stops on it.

The criterion test is there because `completionunlocked` calls `reset_all_state()`, which for
an activity criterion deletes every `course_completions` row in the course, completed ones
included (`delete_all_state()`, `lib/completionlib.php`). An untracked module has no learner
state to lose, unless someone turned tracking off by hand while it was still a criterion, and
that case is reported, never unlocked.

`create_page` still writes nothing for an unchanged page, except when the outcome is `set`: a
page published before this spec must be saved once to become tracked. `create_quiz` refuses
`pass` when the stored pass mark would be 0 (`error:passnograde`), because core checks
"passing grade" only in its form, and with `gradepass` 0 any grade completes the quiz.

### Course completion criteria: `set_course_completion`

Moodle has no API for changing course completion criteria. Its only route, the course
completion form (`course/completion.php`), starts by clearing every criterion, which deletes
every learner's course completion in the course. This function never takes that route, and
`tests/criteria_harness.php` fails if its source names `clear_criteria` or
`delete_course_completion_data`. Instead, for a course whose idnumber starts `ltct:` and has
completion on, it:

1. computes **wanted**, the visible, tracked modules carrying `<course idnumber>:` (never the
   question bank), and **present**, the cmids of the course's activity criteria, and their
   difference (`criteria_diff`, which de-duplicates, because the table allows duplicate rows);
2. inserts each added criterion and deletes every row for each removed one through
   `completion_criteria_activity`, the data object the form writes (`module` is the module
   name, `moduleinstance` the cmid);
3. sets overall and activity aggregation to ALL through `completion_aggregation`;
4. if a criterion was removed, flags each **incomplete** course completion for re-aggregation
   through `completion_completion` (`reaggregate`, then `mark_enrolled()`), as
   `completion_daily_task` does. A completed row is never touched;
5. fires `course_completion_updated` if anything changed, as the form does.

Criteria of other types (date, role, self, grade) are left alone and reported as
`othercriteria`. The function returns cmids and a count, never a user.

### Competencies a course aims at: `set_course_competencies`

The per-competency report cannot join on a text custom field, so the publisher also writes a
course's frontmatter `competencies:` into `local_ltuse_course_comp`. Every name is resolved,
compared exactly in PHP, against the non-retired rows of `local_ltuse_competency` before
anything is written; one unknown name fails the call and leaves the map as it was. The writes
run in one delegated transaction, and the stored set is read back after the commit.

### The plugin's own tables

`db/install.xml` (and the matching steps in `db/upgrade.php`) adds four tables. The first two
arrive at version `2026100204`; `local_ltuse_course_badge` arrives at `2026100300` and is
described under [Badges and certificates](#badges-and-certificates-spec-013);
`local_ltuse_mentor_contact` arrives at `2026100301` and is described under
[Mentors](#mentors-spec-003). Only the last holds user data, and `classes/privacy/provider.php`
declares, exports and deletes it:

| Table | Holds | Written by |
|---|---|---|
| `local_ltuse_competency` | `name` (unique), `category`, `sortorder`, `retired` | `site_config.py apply`, from `competencies.yaml` (the `Meta` category left out). Rows are retired, never deleted. |
| `local_ltuse_course_comp` | `courseid`, `competencyid` (unique together) | `set_course_competencies` |
| `local_ltuse_course_badge` | `courseid` (unique), `badgeid` (unique), `imagehash` | `set_course_recognition` |
| `local_ltuse_mentor_contact` | `mentorid`, `learnerid` (unique together), `contactid` | the `role_assigned` observer and `cli/mentor_contacts.php` |

### The per-competency datasource

`reportbuilder/datasource/competency_coverage` is a report builder datasource, a supported
extension point core finds by namespace. Its main table is `local_ltuse_competency`, with
retired rows removed by a base condition, so a competency no course aims at is still a row
with 0 in every count. It has two entities and nothing else: `competency` (name, sorted in
framework order, and category) and `coverage` (five counts, each a correlated subquery). There
is no user, course or enrolment entity, so a report on this source cannot name a person or a
course, and no column shows a level.

What counts as delivery is fixed in the entity's SQL, not in a report condition, so editing
the report cannot widen it: a course counts only if its idnumber starts `ltct:`; delivery is an
enabled `cohort` enrol instance whose role has shortname `student` (looked up by shortname,
never by id), which leaves out each organisation's managers cohort and every manual (pilot)
enrolment.

## Badges and certificates (spec 013)

Training evidence only: "training completed", never "certified", and never a CBC level held.
The wording rule is `scripts/cbc_wording.py`. `site_config.py apply` sends its deny patterns
with the badge template, and `classes/recognition/wording.php` re-checks each course's
rendered text against them before anything is written. The plugin holds no copy of the rule.

### The badge: `classes/recognition/badges.php`

One core **course badge** per published course, awarded by core's own observer the moment the
learner's course completion is recorded. Core has no web service that creates or updates a
badge, so the plugin calls the classes the badge pages use:

| Call | For |
|---|---|
| `\core_badges\badge::create_badge($data, $courseid)` | A new badge. It sets the status to inactive and a default message, which the plugin then rewrites. |
| `award_criteria::build([...])->save([...])` | The overall criterion (`agg` ALL), then the course criterion `course_<id>`. Nothing else: a competency criterion would read as a competency awarded. |
| `$badge->save()` | Rewording in place, active or not. The details form freezes an active badge, but that is a UI rule only. |
| `$badge->set_status()` | Activation, on a delivery publish only. Never a deactivation: an inactive-locked or archived badge makes the BadgeClass JSON return 410 to everyone who already holds it. |
| `badges_process_badge_image($badge, $tmp)` | The image. It deletes the file it is given, so it gets a temporary copy, and it does nothing without GD, so the plugin refuses loudly instead. |
| `course_handler::create()->get_instance_data($courseid, true)` | The raw values of `ltct_competencies` and `ltct_target_level`, which fill `{competencies}` and `{target_level}`. |

**The pilot learner at delivery.** A pilot and the published course are one Moodle course, so
a pilot learner's completion is still there when the badge is activated, and the badge cron
would award them. Stage 8 suspends their manual enrolment first (`process/stages/08-publish.md`).

**Who sees a learner's badges.** `roles.yaml` sets `moodle/badges:viewotherbadges` to
`inherit` for the authenticated-user role, so learners cannot see each other's badges.
**Spec 003's mentor role must grant `moodle/badges:viewotherbadges`, assigned in the learner's
user context**, so a mentor sees exactly their assigned learners' badges. Managers keep it
through their archetype. Organisation managers follow completion through spec 004's reports
instead: a course-context role cannot reach a user-context capability.

### The certificate: `classes/recognition/certificate.php`, `classes/siteconfig/certtemplate.php`

A `mod_customcert` activity per delivered course, made through `util::upsert_module()` with
course-module idnumber `ltct:<slug>:certificate`, `verifyany` 1, completion off, and the
availability `{"op":"&","c":[{"type":"coursecompleted","id":"1"}],"showc":[true]}` from
`availability_coursecompleted`. The call refuses while `enableavailability` is off, because
`add_moduleinfo()` would drop the condition and leave the certificate open.
`set_course_completion` leaves the certificate's idnumber out of its wanted set. The activity
is **never deleted**: deleting it deletes every issued code. A course is retired by hiding it.

**Calls into `mod_customcert`'s own classes (Principle XI).** These are not a published API,
and they were refactored heavily during 5.2.x. Re-run quickstart V6 on every `mod_customcert`
re-pin.

| Class | Method | For |
|---|---|---|
| `mod_customcert\template` | `create()`, `from_record()` | The site template, found by its exact name at system context. |
| `mod_customcert\service\template_repository` | `list_by_context()` | Finding it. Two with the declared name is `ambiguous` and blocks the run. |
| `mod_customcert\service\template_service` | `create()`, `delete_page()` | Replacing the site template's pages when the declaration changes. |
| `mod_customcert\service\page_repository` | `create()`, `list_by_template()` | Its pages. |
| `mod_customcert\service\element_factory` | `build_with_defaults()`, `create()` | Its elements, from records whose `data` mirrors each element's `normalise_data()`. |
| `mod_customcert\service\element_repository` | `create()`, `list_by_page()` | Writing and reading elements. |
| `mod_customcert\service\element_layout` | `from_record()` | An element's position, reference point and alignment. |
| `mod_customcert\service\template_load_service` | `create()`, `replace()` | Copying the site template into an activity whose pages differ. |
| `mod_customcert\element_helper` | `CUSTOMCERT_REF_POINT_*` | The reference point for each alignment. |

Images go into `mod_customcert`'s own `image` file area at system context, through the file
API, which is where its image element looks for a site template's files. Each is stored under
its content hash and its name, because the image element reuses a course's copy of a file with
the same name and never refreshes it, so a new logo must have a new name to reach courses
already copied. Copying into a course moves the file to the course's context, so templates are
compared by what they show (element type, name, position and data, and an image by its stored
name and size), never by where the file is. The plugin writes none of `mod_customcert`'s tables with SQL. It reads them only
through the classes above, plus one join to find the activities: `course_modules` and
`customcert`, by `cm.idnumber LIKE 'ltct:%:certificate'`.

### The badge map: `local_ltuse_course_badge`

A badge has no idnumber, and a restore or a course copy duplicates its name, so this table is
its identity: `courseid` (unique), `badgeid` (unique), `imagehash` (the sha256 of the template
image the badge's image was made from, because core resizes it) and `timecreated`. It holds no
user data, so the privacy provider does not declare it. A badge in an `ltct:` course that
the map does not name is reported `extra` and never adopted.

## What the plugin relies on, and why (Principle XI)

Spec 004 adds these dependencies on Moodle. Each was confirmed in `MOODLE_502_STABLE` source.

**Completion data objects** (`lib/completionlib.php` and `completion/`), written through,
never by raw SQL:

- `completion_info::is_enabled()`, to refuse a rule while completion is off;
- `completion_criteria_activity` `insert()` and `delete()`. Its file is not autoloaded and
  `completionlib.php` does not include it, so `set_course_completion` requires it itself, as
  `course/completion.php` does;
- `completion_aggregation` `setMethod()` and `save()`;
- `completion_completion` `mark_enrolled()`, after setting `reaggregate`;
- `add_moduleinfo()` and `update_moduleinfo()` with `completionunlocked`, which write the
  module's rule; `set_moduleinfo_defaults()` turns `completionusegrade` into
  `completiongradeitemnumber`;
- the `\core\event\course_completion_updated` event.

**Raw reads by the publish functions**, all read-only:

| Table | Read by | Why there is no API |
|---|---|---|
| `course_completion_criteria` | `course` (the table's index), with `criteriatype` and, in `completion_outcome()`, `moduleinstance` | `completion_info::get_criteria()` drops rows whose module is gone, so they would never be removed, and `completion_criteria_activity::fetch()` throws on the duplicate rows the table allows. |
| `course_modules` | `id` list, column `completion` | `set_course_completion` leaves untracked modules out of the criteria. The module records `util` already reads carry no tracking value. |
| `course_completions` | `course`, `timecompleted IS NULL` (`id`, `userid`) | To find the incomplete rows to flag after a criterion is removed. Each is then loaded and saved through `completion_completion`. |

**Report builder** (`reportbuilder/classes/`), used by `siteconfig\reports`:

- `local\helpers\report`: `create_report($data, false)`, and add, delete and reorder for
  columns, conditions and filters, plus column sorting;
- `local\audiences\base::create()` and `update_configdata()`, and `local\helpers\audience`
  `get_base_records()` and `delete_report_audience()`;
- `local\schedules\base::create()` through `reportbuilder\schedule\message`, not the
  deprecated `helpers\schedule::create_schedule()` (MDL-86066); `helpers\schedule`
  `update_schedule()` and `toggle_schedule()`;
- the `report`, `column`, `filter` and `schedule` persistents, read directly. A report is
  **found by `component = local_ltuse` and `area`**, never by id or name. `area` is
  `PARAM_AREA`, so it is `org_<org key>_<key>` or `<key>`, with every `-` turned into `_`.
  Nothing makes the pair unique, so more than one match is `ambiguous` and left alone;
- each declared condition is built on the server and must give SQL from `get_sql_filter()`,
  because a select filter silently drops a value not among its options (MDL-84213) and the
  report would then show everyone. A `role:name` condition is declared by shortname and stored
  as the role's id.

Its raw reads are `role` by `shortname` or `id` and `cohort` by `idnumber` or `id`, to resolve
and display audiences and the role condition; neither table has a lookup by those keys in the
report builder API.

**Course custom fields**, used by `siteconfig\coursefields`: writes only through
`core_course\customfield\course_handler` `create_category()`, `move_field()` and
`save_field_configuration()`, so core's events and caches stay right. Its raw reads:

| Table | Read by | Why there is no API |
|---|---|---|
| `customfield_category` | `component = core_course`, `area = course`, `itemid = 0`, **`name`** | A custom field category has no idnumber; its name is its identity. The handler also returns enabled shared categories, and a shared one of the same name must never be adopted. |
| `customfield_field` joined to `customfield_category` | `shortname`, across course and shared fields; and the `ltct_` prefix, for drift | `save_field_configuration()` does no uniqueness check, so the lookup is what stops a second apply creating a duplicate. |

Course values (`customfield_data`) are never read or written here; the publisher sets them
through `core_course_update_courses`.

**The datasource's raw reads**, all read-only, aggregate-only, and in the same join shapes as
core's participants datasource:

| Table | Columns used | For |
|---|---|---|
| `{course}` | `id`, `idnumber` | Only courses whose idnumber starts `ltct:` count. |
| `{enrol}` | `id`, `courseid`, `enrol`, `status`, `roleid` | An enabled `cohort` instance with the student role is delivery. |
| `{user_enrolments}` | `enrolid`, `userid`, `status` | Active enrolments on those instances; learners are the same rows by distinct `userid`. |
| `{user}` | `id`, `deleted` | Deleted users are not counted. |
| `{role}` | `id`, `shortname` | The student role, by shortname, never by id. |
| `{course_completions}` | `course`, `userid`, `timecompleted` | Completed rows, for a user with a cohort/student enrolment in that course. |

It writes nothing.

**Frozen once released.** The column identifiers `competency:name`, `competency:category`,
`coverage:courses`, `coverage:indelivery`, `coverage:enrolments`, `coverage:learners` and
`coverage:completions` are stored in every saved report and in `moodle/site/reports.yaml`.
Renaming one breaks those reports. Add a new one instead.

**Re-checked on every Moodle branch.** The datasource extends `core_reportbuilder\datasource`
and the entities `core_reportbuilder\local\entities\base`. Before raising `requires` or
`supported`, read `reportbuilder/UPGRADING.md` for the new branch and check both classes
against it. Moodle 5.0 to 5.2 changed default entity initialisation, entity order, the
abstract `get_default_tables()`, custom sort fields and select-filter values (research R15).

## Identity, and why republishing does not duplicate

Every object the publisher creates carries an idnumber:

```
course         ltct:<slug>
course module  ltct:<slug>:<file number>          e.g. ltct:bloom:01 for 01-what-bloom-is.md
question       ltct:<slug>:<file number>:q<quiz>.<n>
discussion     ltct:<slug>:discussion             the course's one forum (spec 012)
```

The file's number, not its whole name, because Moodle stores every idnumber in a
`VARCHAR(100)` (`course`, `course_modules`, `question`) and does not truncate: an overlong
one fails the insert part-way through a publish. A long course slug plus a long lesson
filename overflowed it. A file with no number keeps its stem. `moodle_payload.py` refuses a
course where two published files share a number, or any idnumber exceeds 100 characters.

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
callouts render in the Android app (the app applies it only on the Premium app plan), and creates the `ltcpublisher` role with exactly the
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
- `has_capability('moodle/user:viewalldetails')` in the viewed user's context, to exempt the site team;
- `has_capability('local/ltuse:viewmenteeprogress')` in the viewed user's context, to exempt their mentor, even one who manages another organisation (spec 003, research R9).

It runs only while `forceloginforprofiles` is on (declared in `moodle/site/settings/groups.yaml`).

### Course fields, competencies and reports (spec 004)

After spec 002's items, `apply` runs three more classes in this order: `coursefields` (the
course field category, then its fields), `competencies` (the competency list, retiring rows a
list leaves out, never deleting) and `reports` (last, because a report's columns and audiences
need the fields and cohorts made before it). A block that belongs to one report, such as an
audience cohort that does not exist yet, leaves only that report unwritten. What each class
calls and reads is listed under [What the plugin relies on](#what-the-plugin-relies-on-and-why-principle-xi).

**Raw reads added by spec 002.** All are reads of core tables; none is a write.

| Table | Read by | Indexed? | Why there is no API |
|---|---|---|---|
| `course_categories` | `idnumber`; `parent` and `name` | `parent` is; `idnumber` is not | No core function finds a category by `idnumber`, or lists the candidates for adoption. |
| `cohort` | `idnumber` | No: core indexes only `contextid` | `cohort_get_cohort()` takes an id. Finding a cohort by `idnumber` in any context is needed to report one in the wrong context instead of duplicating it. |
| `user_info_category` | `name` | No | A profile field category has no `idnumber`. Its name is its identity. |
| `user_info_field` | `shortname` | No (unique only by validation) | `profile_get_custom_field_data_by_shortname()` exists. The class reads the row directly so it can compare every column. |
| `cohort` joined to `cohort_members` | `cm.userid`, `c.contextid`, `c.idnumber LIKE 'ltct:org:%:managers'` | `cohort_members.userid` is | `cohort_get_user_cohorts()` returns only visible cohorts, and every managers cohort is hidden. One query per request, cached. |
| `course_categories`, `cohort`, `user_info_field` | `idnumber` or `shortname` prefix (`ltct:`, `ltct_`) | as above | Drift's scan for undeclared items. There is no core listing by prefix. |

### Course discussions (spec 012)

Spec 012 adds course discussions. Drift lists every course whose idnumber starts `ltct:` (a
lookup by that column, as `util::course_by_idnumber()` does) and compares its
`ltct:<slug>:discussion` forum with `course-discussions.yaml`: `differs`, `missing`, and the
warnings `forced` (the course forces a group mode) and `allparticipants`. The last is a
count through mod_forum's `discussion_list_vault::get_total_discussion_count_from_forum_id_and_group_id()`,
which runs `SELECT COUNT(1)`: no subject, post or author is read. `apply` corrects `differs`
through `ensure_discussion::apply_groupmode()`, the publisher's own path, and never creates a
missing forum. Warnings print as `[skip]`.

`ensure_discussion` writes no table directly. Group mode goes through
`\core_courseformat\formatactions::cm()->set_groupmode()`, the 5.2 replacement for the
deprecated `set_coursemodule_groupmode()`. Clearing a hand-set grouping goes through
`update_moduleinfo()`, core's only setter for `groupingid`. That path calls
`forum_update_instance()`, which re-reads the forum's ratings to recalculate grades: a
deliberate trade for using the public API, and it only runs after someone set a grouping by
hand.

### Badge and certificate templates (spec 013)

After reports, `apply` stores the badge template (config `local_ltuse/badge_template` and the
plugin's `badgetemplate` file area) and rewords every mapped badge from it, reporting each as
`badge <course idnumber>`. It then builds the certificate site template and copies it into
each `ltct:<slug>:certificate` activity whose pages differ. `drift` reports a template that
differs, a badge whose text or image differs from its rendering, a mapped badge that is gone
(`missing`) and an unmapped badge in an `ltct:` course (`extra`). It never judges whether a
badge should be active: only the publisher knows a course's stage.

## Mentors (spec 003)

A mentor relationship is the declared `mentor` role (`moodle/site/roles.yaml`) held by the
mentor in a learner's user context. Core gives that role a profile and the Grades overview,
but no cross-course completion and nothing in the Moodle app, and core's messaging ignores it.
This plugin fills those three gaps and nothing else.

| Piece | Where | Does |
|---|---|---|
| `local/ltuse:viewmenteeprogress` | `db/access.php` | Read, `CONTEXT_USER`, `RISK_PERSONAL`, no archetype. Granted only by the `mentor` role, so it reaches only assigned learners. |
| Mentoring page | `mentoring.php`, `templates/mentoring.mustache` | The learners you mentor, each with their courses and completion (in progress N%, not started, completed on a date, not tracked), and your own mentors. Links to profile, core Grades overview and Message. Never shows quiz attempts, submissions, logs or hidden profile fields. |
| Data | `classes/mentoring.php` | `for_user()` feeds both the page and the app, so they cannot differ. Every learner is rechecked with the capability on every call. `progress_status()` and `sort_courses()` are pure, tested by `tests/mentoring_harness.php`. |
| Navigation | `db/hooks.php`, `classes/hook_callbacks.php`, `lib.php` | A "Mentoring" primary-navigation item (`\core\hook\navigation\primary_extend`) and profile links (`local_ltuse_myprofile_navigation()`), only for someone with a mentor or a learner. |
| App | `db/mobile.php`, `classes/output/mobile.php`, `templates/mobile_mentoring.mustache` | A `CoreMainMenuDelegate` handler under the app's More menu. Its `init` returns `disabled` for anyone with no relationship. |
| Message contacts | `db/events.php`, `classes/observer.php`, table `local_ltuse_mentor_contact` | On `role_assigned` of `mentor` in a user context, `\core_message\api::add_contact()` unless the two are already contacts, recorded in the table. On `role_unassigned`, once no mentor assignment links the pair, `remove_contact()`, only while the pair's contact is still the one the plugin made (the table keeps its `message_contacts` id), so a contact the two make again themselves is never removed. On `user_deleted`, the user's rows and those contacts go. A learner's block is never touched, so it still wins. |
| CLI | `cli/mentor_contacts.php` | `--sync` makes missing contacts for existing assignments (run once after upgrading). `--end-all --mentor=<username>` ends every relationship one mentor holds, through `role_unassign_all()`. Prints counts, never names. |
| Privacy | `classes/privacy/provider.php` | Declares, exports and deletes `local_ltuse_mentor_contact` rows in each person's user context, and declares the link to `core_message`. The role assignment and the contact are core's. |
| Course reports | `classes/siteconfig/inspector.php`, `drift.php`, `applier.php` | Drift reports each `ltct:` course whose own "Show activity reports" is on (a mentor would see submissions and logs); apply turns it off with `update_course()`. The publisher also sends `showreports: 0` on every publish. |

**Raw reads added by spec 003.** None is a write; the only table written is this plugin's own.

| Table | Read by | Indexed? | Why there is no API |
|---|---|---|---|
| `role_assignments` joined to `context` | `ra.userid`, `ra.roleid`, `ctx.contextlevel = CONTEXT_USER` | `role_assignments.userid` is | Core has no "contexts where this user holds this role" function. `block_mentees` reads the same join. The result only finds candidates; the capability decides. |
| `course_completions` | `userid`, `timecompleted IS NOT NULL` | `userid` is | Lists a course the learner completed after their enrolment was deleted, which `enrol_get_all_users_courses()` no longer returns (FR-004). |
| `role_allow_assign` | `(roleid, allowassign)` | unique key | The applier and drift check one declared allow-assign pair. `get_assignable_roles()` answers for a user in a context, not for a pair. |

### Manage mentors: organisation managers (spec 003 Phase B)

`mentors.php?userid=<learner>` shows one learner's current mentors, each with Remove, and an
Add picker. It manages the user-context relationship only, the learner's default mentor; a
mentor for one course is spec 008's. It is linked from the learner's profile
(`local_ltuse_myprofile_navigation()`) whenever the same decision allows it. The site team
can still use core's "Assign roles relative to this user" page instead.

**Authorisation**, recomputed on every GET and POST, so a forged POST naming another learner
is refused like a direct visit. `local_ltuse_may_manage_mentors()` in `lib.php` gathers the
inputs, and `\local_ltuse\mentor_admin::decide()` decides. It is pure, and
`tests/mentor_admin_harness.php` tests it. It allows:

- **the site team**: `moodle/role:assign` in the learner's user context, with `mentor` among
  `get_assignable_roles()` there. Any learner.
- **an organisation manager**: `\local_ltuse\organisation\access::may_manage_account()`, the
  shared check of spec 002 (research R10). The learner's `ltct_org` is one of the manager's
  organisations, read through the existing `local_ltuse_managed_organisation_keys()` (`cohort`
  ⋈ `cohort_members`), and the learner is in that organisation's member cohort. The person
  must also be a learner: staff, mentors and other managers stay with the site team.

It refuses everyone, the site team included, for themselves and for a missing or deleted
user, with the same message as any other refusal. The facts about the person come from
`local_ltuse_organisation_person_facts()`, which spec 002's organisation pages also use.

**Picker**: members of the hidden system cohort `ltct:mentors` only, which the site team fills
(`moodle/site/organisations.yaml`), never a site-wide user search. It leaves out the learner,
their existing mentors, and deleted or suspended accounts. An Add or Remove names a mentor
the page would itself offer, or it is refused.

**Writes**: after a confirmation, as a POST with the sesskey, `role_assign()` or
`role_unassign()` of `mentor` in the learner's user context. Neither checks a capability, so
the decision is the only gate. Core's `role_assigned` and `role_unassigned` events record the
viewer as the actor, and the observers above add or remove the message contacts.

**Raw reads added by Phase B**, both read-only:

| Table | Read by | Indexed? | Why there is no API |
|---|---|---|---|
| `cohort` | `idnumber = 'ltct:mentors'`, `contextid` (system) | no (`cohort.idnumber`) | No cohort API looks a cohort up by idnumber. One query per request, cached. |
| `cohort_members` joined to `user` | `cm.cohortid`; `u.deleted = 0`, `u.suspended = 0` | `cohort_members.cohortid` is | Core has no function that lists one cohort's members. |

Membership of `ltct:mentors` for one person is `cohort_is_member()`.

## Events and office hours (spec 011)

Core's calendar does the rest of spec 011: event levels, export, the app's calendar, and the
time zone on the profile. This plugin fills four gaps.

| Piece | Where | Does |
|---|---|---|
| Change notices | `classes/calendar_notify.php` (pure), `classes/observer.php`, `classes/task/event_change_notice.php`, `db/messages.php` (`eventchange`) | Core sends nothing when an event changes. The observer (`calendar_event_updated` and `_deleted`, `internal => false`) keeps a site, course or group event's change or cancellation. It never keeps a module's, a subscription's or a user's. It buffers by series key (`r<repeatid>` or `e<id>`), and one `\core\shutdown_manager::register_function()` callback queues one adhoc task per key, two minutes ahead, with `reschedule_or_queue_adhoc_task()`. The task tells the event's active audience, except the person who made the change. New events are not announced (plan decision 5). |
| Booking notices | `classes/booking_notice.php`, `classes/observer.php`, `db/messages.php` (`bookingnotice`), table `local_ltuse_booking` | Every office-hours booking, change of time and cancellation is emailed to the mentee and the mentor. The person who acted gets "You …"; the other side gets a notice. The office-hours scheduler sends none itself (`allownotifications` 0). The observer reads the scheduler's `SSstu:<slotid>` calendar events, which it rewrites on every save, and mod_scheduler's own `slot_deleted`. The table keeps each booking's last notified time, because core's update event carries no old one. So a note edit or spec 016's re-save sends nothing. |
| Office-hours sync | `classes/officehours.php`, `classes/officehours_plan.php` (pure), `classes/task/officehours_reconcile.php`, `lib.php` `local_ltuse_allow_group_member_remove()` | One group per mentor (`ltct:mentor:<id>`, visibility OWN, a name with no person's name) in `ltct:officehours`, holding the mentor and each mentee. Members are added with component `local_ltuse`, through the course's one manual enrolment instance, and are suspended, never unenrolled. Spec 003's role observers sync a pair as it changes. The hourly task reconciles everything and clears booking records whose event is gone. Logs carry counts only. |
| Time zone notice | `classes/timezone_notice.php` (pure), `classes/hook_callbacks.php` `top_of_body()`, `db/hooks.php` | On the office-hours scheduler's pages only, a notice through `\core\hook\output\before_standard_top_of_body_html_generation` names the zone their times are in, with a link to change it on the profile. It never redirects. |
| Site config | `classes/siteconfig/officehours.php`, `classes/siteconfig/dashboard.php` | `apply` creates or updates the office-hours course and its scheduler, the enrolment instance and the group name template, then reconciles. It also adds the declared blocks to the default dashboard. It never deletes anything. |
| Privacy | `classes/privacy/provider.php` | Declares, exports and deletes `local_ltuse_booking` rows in the learner's and the mentor's user contexts. The appointment is mod_scheduler's, and the events and notifications are core's. |

Discussion checks (spec 012) skip `ltct:officehours`: it is site config's course, not the
publisher's, and has no forum.

**Principle XI exceptions added by spec 011.** Each is the only route to the behaviour, and
each is re-checked as stated.

| Exception | Why | Re-checked by |
|---|---|---|
| The office-hours privacy rests on mod_scheduler's group filter (`get_slots_available_to_student()`), and the plugin does not check groups when a slot is booked. | The plugin is the booking tool D6 chose. A crafted request can book another mentor's slot; the mentor sees it and can remove it (plan decision 4, accepted). | Quickstart V10 on every scheduler re-pin |
| Raw read of `scheduler_slots.teacherid` by primary key, and reliance on the `SSstu:<slotid>` eventtype convention (`classes/model/slot.php`). | The plugin has no API that returns a slot's teacher without loading its internal model classes. Its calendar events are the only record of a booking's time that core's events report. | Quickstart V13 on every scheduler re-pin |
| `update_record('scheduler', …)` for the declared columns of an existing activity (`maxbookings`, `schedulermode`, `guardtime`, `allownotifications`, `defaultslotduration`, `usebookingform`, `scale`), and the read of the same row. The name and group mode go through core's `set_coursemodule_name()` and `set_coursemodule_groupmode()`. | `scheduler_update_instance()` calls the activity form's `save_mod_data()` unconditionally, so `update_moduleinfo()` cannot run without a form. A new activity goes through `add_moduleinfo()`. | Quickstart V1 on every scheduler re-pin |

**Raw reads added by spec 011**, all by indexed columns of stable core tables:

| Table | Read by | Why there is no API |
|---|---|---|
| `role_assignments` joined to `context` | `roleid` (the mentor role), `contextlevel = CONTEXT_USER` | Every mentor relationship at once, for the reconcile. Spec 003's Mentoring page reads the same join. |
| `user_enrolments` | `enrolid` | Every enrolment in the office-hours instance, with its status. `get_enrolled_users()` omits suspended ones. |
| `groups`, `groups_members` | `courseid` and `idnumber`; `groupid` and `component` | The mentor groups and the memberships this plugin owns. `groups_get_members()` does not return `component` or `itemid`. |
| `my_pages`, `block_instances` | `userid IS NULL`, `name`, `private`; `blockname`, `parentcontextid`, `pagetypepattern`, `subpagepattern` | Whether a declared block is on the system default dashboard. The block manager reads blocks only for a page being displayed. |
| `event` left-joined from `local_ltuse_booking` | `id` | Booking records whose calendar event is gone. |

## Verified against Moodle 5.2.3+ (2026-09-29)

Installed and exercised end to end on Moodle 5.2.3+ (Build 20260928), PHP 8.3, PostgreSQL
16. A full publish of `coretech-computer-hardware` creates 6 pages, 7 sections and a
27-question quiz, and republishing changes nothing. The four things the first draft asked
you to verify have now been settled, three of them by failing. (This section predates spec 004;
its completion, criteria, competency and report code has not been run on the instance yet.)

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
- **No enrolment, grades or learner records.** This plugin publishes content and creates no
  learner data. Learner data is Moodle's alone (`INTENT.md`: *"Learner data lives in
  Moodle, never in this repo"*); admin tooling that works with it is a separate concern.
  Spec 004 comes closest, and stops here: `set_course_completion` flags incomplete course
  completions for Moodle to re-check, through core's own data object, and never alters or
  deletes a completion; the per-competency datasource counts enrolments and completions at
  query time and stores none of them; nothing returns a user.
- **No direct table writes** for anything a Moodle API covers. Bypassing
  `add_moduleinfo()` / `update_moduleinfo()` would skip grade items, completion, events
  and the file API, and leave a course that looks right until one of those is needed.
  Spec 009 adds none: files go through `file_storage` and pages through
  `update_moduleinfo()`. Spec 004 writes `$DB` only to the plugin's own two tables; criteria,
  aggregation and completions go through the completion data objects, reports through report
  builder's helpers, and custom fields through `course_handler`.
