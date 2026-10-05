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
| `local_ltuse_ensure_discussion` | write | Spec 012, row #10. Creates the course's one `general` forum, `ltct:<slug>:discussion`, in section 0 if it is absent; its name and intro are set only then. On every call it sets only the forum's group mode: no groups, with no grouping, for every course (spec 002 R14, 2026-10-02). It never writes a discussion or post. Returns `{cmid, created, groupmode, courseforced}`; `courseforced` means the course's own forced group mode overrides the forum's. |
| `local_ltuse_set_course_completion` | write | Makes a course's activity completion criteria exactly its visible, tracked `ltct:` modules, one criterion at a time. Never clears a learner's course completion (spec 004). |
| `local_ltuse_set_course_competencies` | write | Replaces the competencies a published course aims at, by name, in the plugin's own map table. Fails closed on a name the site does not have (spec 004). |
| `local_ltuse_set_course_recognition` | write | Creates or rewords a published course's completion badge, activates it on a delivery publish, and on delivery makes its certificate activity. Never deactivates a badge or deletes a certificate (spec 013). |
| `local_ltuse_place_course` | write | Spec 002 R11 (2026-10-02). Moves a published course into the category with a given idnumber, only when it is elsewhere, with `move_courses()`. Accepts only `ltct:org:<key>`, `ltct:pilots` or `ltct:published`, and checks `local/ltuse:publish` in the course and the target category. Returns `{moved}`. The publisher calls it on every publish of a course `moodle/site/org-courses.yaml` declares organisation-only. |

**The discussion forum is open to the whole course** (spec 002, amended 2026-10-02). Shared
courses are open across organisations, so the forum has no groups, and a post written while it
had them shows to everyone whatever `groupid` it carries (`mod/forum/lib.php:6790-6795`). An
organisation that needs a private forum has an organisation-only course instead (R11).

Course create/update stays on core (`core_course_create_courses`,
`core_course_update_courses`, `core_course_get_courses_by_field`) and section handling on
[`local_wsmanagesections`](https://moodle.org/plugins/local_wsmanagesections). Neither is
duplicated here.

`get_course_manifest` is the one function not in the original sketch, and the publish is
not idempotent without it: `core_course_get_contents` does not reliably return a module's
idnumber, so there is otherwise no way to ask Moodle which modules a previous publish
created. With it, republishing is a diff rather than a blind re-create.

The site team's administration functions (`local_ltuse_admin_*`, spec 008) are a separate
service with its own token; see [Administration (spec 008)](#administration-spec-008).

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

**Later specs list theirs with their own pieces**: spec 003 under
[Mentors](#mentors-spec-003), spec 011 under
[Events and office hours](#events-and-office-hours-spec-011), and spec 008 under
[Administration](#administration-spec-008). Spec 008's include raw reads of `cohort` with
its `component` (to refuse a cohort a plugin owns), of `user` by email, and of
`role_assignments` with `component` and `itemid`, and the two direct `$DB` writes
`cli/setup_publishing.php` still makes.

**The pathway pages' raw reads (spec 006)**, all read-only, by indexed columns:

| Table | Columns used | For |
|---|---|---|
| `{course}` | `id`, `idnumber`, `visible`, `fullname` | A pathway lists visible `ltct:<slug>` courses (`pathway\catalogue::membership_sql()`, the one membership rule). |
| `{course_completions}` | `course`, `userid`, `timecompleted` | A course is completed for the learner (`pathway\progress`). |
| `{user_enrolments}`, `{enrol}` | `userid`, `enrolid`, `id`, `courseid` | A course is in progress: the learner holds any enrolment in it. |
| `{cohort}`, `{cohort_members}` | `id`, `idnumber`, `name`, `contextid`; `cohortid`, `userid` | Which pathways a learner has, and which cohorts a manager may give one to. |

They write only the plugin's own tables.

## Learning pathways (spec 006)

A pathway is never stored as a Moodle object. `classes/pathway/` builds it when it is opened,
from what the publisher and `site_config.py apply` keep up to date:

- **`local_ltuse_course_pathway`**: one row per published course, written only by
  `local_ltuse_set_course_pathway`, which the publisher calls on every publish straight after
  `set_course_competencies`. It holds whether the course is delivered (stage 8, from
  `course_stage.py`) and the level it aims at (1–4), and the pathway keys last announced, so
  the next publish fires `pathway_courses_changed` for what changed.
- **`local_ltuse_role_pathway`, `local_ltuse_role_pathway_comp`**: role pathways from
  `moodle/site/pathways.yaml`, applied by `classes/siteconfig/rolepathways.php`. Retired,
  never deleted.
- **`local_ltuse_pathway_cohort`**: which cohorts have which pathway, set on
  `pathways_manage.php`. Holds `usermodified`, declared by the privacy provider.
- **`local_ltuse_competency`** gains `slug` and `url`, the competency's page on the competency
  site, which a level with no course links to. Plugin config `pathwaylevel1`–`4` holds the
  level labels from `outcome-levels.yaml`.

| Class | Does |
|---|---|
| `pathway\catalogue` | Keys (`competency:<slug>`, `role:<key>`), which courses are on a pathway, every pathway. |
| `pathway\builder` | Pure: lays out a pathway, marks the next course, totals a role. Tested by `tests/pathway_harness.php`. |
| `pathway\viewer` | Pure: who may see whose pathways (the learner, their mentor, their organisation's manager, the site team). |
| `pathway\progress` | One learner's state per course, through `mentoring::progress_status()`. |
| `pathway\view` | Glue: one key for one learner as a template context. |
| `pathway\assignments` | Pathway ↔ cohort, and who may assign. |

**006 enrols nobody.** Spec 008 owns enrolment. It codes against
[`specs/006-learning-pathways/contracts/pathway-api.md`](../../specs/006-learning-pathways/contracts/pathway-api.md):
`catalogue::courses()`, `assignments::assign($key, $cohortid, true)`, `cohorts_for()`, and
the events `pathway_courses_changed`, `pathway_assigned` and `pathway_unassigned`. Those names
are frozen; change them only together with 008.

**No level for a learner, anywhere.** A level appears only as what a course aims at and as a
row heading. Finishing a pathway says the training is completed. `tests/test_pathway_wording.py`
holds every pathway string to `scripts/cbc_wording.py`'s strict rule.

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

**The profile hook.** `lib.php` defines `local_ltuse_control_view_profile()`, the callback core's `user_can_view_profile()` calls through `user_process_profile_callbacks()`. It lets an organisation manager see the profile of each person in the organisations they manage, with `VIEWPROFILE_FORCE_ALLOW`, the one case it grants anything; core lets any plugin's refusal win over it. It refuses a manager anyone else they would reach only as a manager, and leaves the site team, a mentor and a fellow participant in a course to core (spec 002, research R9, amended 2026-10-02). It reads:
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

Spec 012 adds course discussions; spec 002's amendment (2026-10-02, R14) opens them. Drift
lists every course whose idnumber starts `ltct:` (a lookup by that column, as
`util::course_by_idnumber()` does) and checks its `ltct:<slug>:discussion` forum against the
one mode every forum has, no groups and no grouping: `differs`, `missing`, and the warning
`forced` (the course forces a group mode, which would wall the forum). No discussion, post or
author is read. `course-discussions.yaml` is retired, and `site_config.py` refuses it if it
comes back. `apply` corrects `differs` through `ensure_discussion::apply_groupmode()`, the
publisher's own path, and never creates a missing forum. Warnings print as `[skip]`.

Drift also reports each `ltct:` course whose group mode is not 0 (`changed`; apply sets it
through `update_course()`), and, as a blocking count that names nothing, any managers cohort
synced into a course outside the `ltct:org:*` categories (R2). Placement (R11, 2026-10-02):
a course `org-courses.yaml` declares that sits outside its organisation's category is
`changed`, and an undeclared `ltct:` course inside an `ltct:org:*` category is `extra`.
Neither blocks, and apply never moves a course, because a move changes which category roles
it inherits; the next publish re-places a declared one through `local_ltuse_place_course`.
Placement is checked only when the payload carries `org_courses`.

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

## Managers and their own people (spec 002, 2026-10-02)

Shared courses are open across organisations, so an organisation manager is not enrolled with
their people and core gives them nothing for them. This plugin gives a manager one page and a
few actions over their own learners, all decided by one pure, tested class.

| Piece | Where | Does |
|---|---|---|
| The decision | `classes/organisation/access.php` (pure), `tests/org_access_harness.php` | `is_org_member_of_manager()`: the person's `ltct_org` is a key the viewer manages and the person is in that key's member cohort. `may_manage_account()`: that, and the person is a learner (not a site admin, course contact, system or category role holder, manager or `ltct:mentors` member, nor deleted). The per-action rules `may_enrol_into()` and `may_unenrol_from()`, and `is_placement_category()` for `place_course`. |
| The facts | `classes/organisation/people.php`, `lib.php` | `people::facts()` gathers the decision's inputs through `local_ltuse_organisation_person_facts()`; actions read them afresh (`$reload`) before every write. `for_manager()` lists each managed organisation's people with email, courses and completion (`mentoring::courses()`, the Mentoring page's reading). |
| The page | `organisation.php`, `templates/organisation.mustache` | Managers-cohort members only. Per learner: enrol, unenrol, a password reset link, suspend, reactivate, and a link to `mentors.php?userid=` (spec 003). Each opens a confirmation that says what it does; only the confirmed POST, with a sesskey, writes. |
| The actions | `classes/organisation/actions.php` | Every method acts as the signed-in user and re-checks `may_manage_account()` and its own rule; none of the core calls below checks a capability. Enrol, unenrol, suspend and reactivate each have an unchecked `do_*()` core for spec 008's administration service, which keeps the per-action rules but not the manager check. |
| User menu | `db/hooks.php`, `classes/hook_callbacks.php` `user_menu()` | "My organisation", through `\core_user\hook\extend_user_menu`, for managers-cohort members only. |
| Contacts and leavers | `classes/organisation/contacts.php`, `classes/observer.php`, `db/events.php`, table `local_ltuse_org_contact`, `classes/task/reconcile_org_contacts.php` | On `cohort_member_added` to `ltct:org:<key>` or its managers cohort, managers and members become message contacts, recorded as spec 003 records mentors'. On `cohort_member_removed`, only the contacts this plugin made go, and only when no other organisation or mentoring links the pair; a person leaving a member cohort has their organisation-enrolment enrolments in that organisation's `ltct:org:<key>` courses suspended (shared courses stand). The hourly task repairs both ways, for changes that fire no event. Counts only. |
| Placement | `classes/external/place_course.php`, `classes/siteconfig/inspector.php`, `drift.php` | See [Course discussions](#course-discussions-spec-012) and the function table. |
| Privacy | `classes/privacy/provider.php` | Declares, exports and deletes `local_ltuse_org_contact` rows in each person's user context, removing the contact each stands for. |
| Migration | `cli/open_courses.php` | One-off: `--dry-run` (default) or `--execute`. For every `ltct:` course it sets each cohort sync's group to none, deletes the organisation groups, and, outside the `ltct:org:*` categories, deletes managers-cohort syncs. Counts only, idempotent. Run `site_config.py apply` after it for course and forum group modes (R13). |

**The organisation-enrolment instance.** A manager enrols through a separate instance of core's
`enrol_self` in each course, made on first use and found by `customchar1 = ltct:orgenrol`:
named "Organisation enrolment", new self-enrolments off (`customint6 = 0`), a random key, no
welcome message, no inactivity unenrolment, no expiry, role Student. It is never the manual
(pilot) instance, so spec 004 tells it from pilots by method. `enrol_self` must stay enabled
site-wide: an enrolment through a disabled plugin is inactive, and `enrol()` refuses then.

**Core APIs this adds**, each confirmed on `MOODLE_502_STABLE` (research R10-R13):

- `enrol_get_plugin('self')->add_instance()` (`enrol/self/lib.php:1145`, `lib/enrollib.php:2601`), `enrol_plugin::enrol_user()` (`:2112`), `unenrol_user()` (`:2294`), `update_user_enrol()` (`:2214`), `enrol_is_enabled()` (`:205`);
- `core_login_process_password_reset($username, '')` (`login/lib.php:84`). It prints nothing, applies every guard itself (auth that can reset, `moodle/user:changeownpassword`, confirmed, not suspended, reuse or expiry of a live reset within `$CFG->pwresettime`), emails only the account's own address, and returns a status the page maps (`actions::reset_outcome()`). It reads and writes `user_password_resets` itself, so this plugin never touches that table, which has no public API;
- `\core\session\manager::destroy_user_sessions()` (`lib/classes/session/manager.php:985`) then `user_update_user()` with a minimal `{id, suspended}` (`user/lib.php:156`), as `admin/user.php:127-138` does;
- `get_user_roles()` (`lib/accesslib.php:3097`) over `core_course_category::get_all()` (`course/classes/category.php:370`), `is_siteadmin()`, `cohort_is_member()` (`cohort/lib.php:239`);
- `\core_message\api::is_contact()`, `add_contact()`, `get_contact()`, `remove_contact()` (`message/classes/api.php:2242-2360`), as spec 003 uses them;
- `move_courses()` (`course/lib.php:1548`), which fires `course_updated` and hides a course moved into a hidden category;
- `\core_user\hook\extend_user_menu` (`user/classes/hook/extend_user_menu.php`), dispatched from `user_get_user_navigation_info()` (`user/lib.php:970`).

**5.3 note.** `user_update_user()` is deprecated on `main` for 5.3 (MDL-82650) in favour of
`\core\user::update_user()`. It is current in 5.2 and sits in one method,
`actions::do_write_suspended()`, which the manager wrappers and spec 008's unchecked cores share. Spec 016 relies on the `before_user_updated` hook it dispatches,
so both specs' user writes move to the 5.3 API together, once 016's hook test passes on it.

**Raw reads added by the amendment.** None is a write; the only table written is this plugin's
own.

| Table | Read by | Indexed? | Why there is no API |
|---|---|---|---|
| `cohort` | `idnumber`, `contextid` (`ltct:org:%`, `ltct:mentors`) | `cohort.idnumber` is not (Principle XI exception) | `cohort_get_cohort()` takes an id, and `cohort_get_user_cohorts()` skips hidden cohorts; every organisation cohort is hidden. |
| `cohort_members` | `cohortid`; `userid` | both are | Core has no function that lists one cohort's members. |
| `course_categories` | `idnumber` (`ltct:published`, `ltct:org:<key>`) | not indexed (Principle XI exception) | No core function finds a category by `idnumber`; `core_course_get_categories` by idnumber needs `moodle/category:manage` at system context. |
| `course` joined to `course_categories` | `c.idnumber LIKE 'ltct:%'` | `course.idnumber` is | The courses a manager may enrol into, and placement drift. |
| `enrol` | `courseid`, `enrol = self`, `customchar1` | `courseid` is | Finding the organisation-enrolment instance by its marker; `enrol_get_instances()` has no filter. |
| `user_enrolments` joined to `enrol` | `ue.userid`; `ue.enrolid`, `status` | both are | Which courses a person is enrolled in through that instance, and active enrolments to suspend. |
| `role_assignments` joined to `context` and `role` | `ra.userid`; `contextlevel = CONTEXT_COURSE`, `r.shortname <> 'student'` | `ra.userid` is | Whether a person is staff, so not a manager's to manage: any role but student in any course. `get_user_roles()` takes one context, and `has_coursecontact_role()` sees only `$CFG->coursecontact` (teachers by default). |

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

## Administration (spec 008)

The site team brings learners on, enrols cohorts, suspends, moves and assigns mentors with
[`scripts/ltct_admin.py`](../../scripts/ltct_admin.py), which talks only to this plugin's
second web service. The recipes are in
[`moodle/site/README.md`](../site/README.md#the-site-teams-administration-tool); the
contracts are spec 008's
[`contracts/admin-service.md`](../../specs/008-admin-tooling/contracts/admin-service.md) and
[`contracts/cli.md`](../../specs/008-admin-tooling/contracts/cli.md).

| Piece | Where | Does |
|---|---|---|
| Service `ltuse_admin` ("LTC administration") | `db/services.php` | A second external service, separate from the publisher's, so the admin tool has its own credential (FR-009). `restrictedusers = 1`, `requiredcapability` `local/ltuse:administer`, no file uploads. It lists only the `local_ltuse_admin_*` functions and `core_webservice_get_site_info`; spec 016's `local_ltuse_set_protection` is not listed, because intake calls the PHP method. |
| Functions `local_ltuse_admin_*` | `classes/external/admin_*.php` | `check` and `list`; a `preview_*` and an `apply_*` for intake, cohort enrolment, suspension, move, cohort members, mentors and course mentors; `apply_pathway_assignment`; `summary`. Every preview is read-only and returns outcomes with masked people. Every apply takes one row and its expected outcome, classifies it again, and refuses if it moved off its path, so a repeat reports `already done`. Each re-checks `local/ltuse:administer` and then the core capability for its write. |
| Capability `local/ltuse:administer` | `db/access.php` | System context, `RISK_PERSONAL \| RISK_DATALOSS \| RISK_SPAM` (it creates accounts that receive email), no archetype. |
| Role `ltctadmin` | [`moodle/site/roles.yaml`](../site/roles.yaml) | Holds `local/ltuse:administer` and exactly the core capabilities the functions check, at system level, one assignment per site-team member. `local_ltuse_admin_check` names any the token user lacks. |
| Token script | `cli/setup_admin_token.php` | `--username=<u> --token-file=<path>` authorises one site-team member for `ltuse_admin` and writes their own token to a mode-600 file. It never prints the token; `--rotate` revokes theirs and issues a new one. It uses core's `webservice::add_ws_authorised_user()`, `\core_external\util::generate_token()` and `webservice::delete_user_ws_token()`. There is no shared admin account, so Moodle's logs show who made each change. |
| Rules | `classes/admin/*_rules.php` | Pure classes, no Moodle calls: `intake_rules`, `enrolment_rules` (which cohort may be enrolled in which course, and as what), `move_rules` (kept, lost, gained, suspended by rule), `course_mentor_rules`. Tested without Moodle by `tests/admin_harness.php`. |
| Services | `classes/admin/` | `intake_service`, `cohort_enrolment` (cohort sync added or re-enabled, marked `customchar1 = 'ltct:008'`, disabled and never deleted), `suspension_service`, `move_service`, `membership_service`, `course_mentor_records`, `course_mentor_sync`, `masking`. Suspension and per-row course enrolment call spec 002's `organisation\actions` `do_*()` methods, the same writes an organisation manager's page makes. A new account's username is its email, lowercased; `intake_rules::username()` keeps a neutral `ltc-` code for a `firstname` or `pseudonym` target (spec 016 refuses those levels for a username holding the real name) and for an email `PARAM_USERNAME` would change, longer than 100 characters, or already a username here. Everyone signs in with their email (`authloginviaemail`). |
| Observers | `db/events.php`, `classes/admin/observer.php` | `role_assigned` and `role_unassigned` (the mentor role in a user context), `user_enrolment_created`, `_updated` and `_deleted`, `enrol_instance_updated` and `_deleted`, and `user_updated` keep course mentors in step: internal, so a course mentor whose reason ends loses Teacher, their enrolment and their group in the same request (spec 016 relies on it). `pathway_courses_changed` (spec 006) enrols each enrolling cohort in a course that joins a pathway; never internal, so a rolled-back change enrols no one. |
| Task | `db/tasks.php`, `classes/task/course_mentor_reconcile.php` | Hourly. Recomputes every `ltct:` course's course mentors, removes stray `local_ltuse` Teacher assignments and orphaned records, and re-syncs pathway cohort enrolments. The backstop, not the mechanism. |
| Table | `db/install.xml`, `local_ltuse_course_mentor` | One-course mentors (learner, course) and the mentors of a cohort in a course. Default mentors are never copied in; they are read from spec 003's role assignments. Declared, exported and deleted by `classes/privacy/provider.php`; the database backup must include it (spec 015). |
| Course-mentor enrolment | `classes/admin/course_mentor_sync.php` | One `enrol_self` instance per course, `customchar1 = 'ltct:coursementor'`, closed to self-enrolment. A course mentor is enrolled with no role, then given Teacher with component `local_ltuse`, so the role goes with the reason even when they are enrolled another way too. Each has a "Mentor group <n>" (idnumber `ltct:mentorgroup:<mentor id>`) holding them and the learners they assess there. |
| Setting `local_ltuse/coursementorsync` | `settings.php` | The switch for everything in the three rows above. Declared **0** in [`moodle/site/settings/admin.yaml`](../site/settings/admin.yaml) until spec 008's plan decision 11 (how far a course mentor may see); at 0 the observers and the reconcile do nothing, and course-mentor records can still be written. `local_ltuse_admin_check` reports it. |

**Removing `ltuse_admin` from `db/services.php` deletes every token issued for it** on the
next upgrade, with its authorised users (`lib/upgradelib.php`, `external_update_descriptions()`),
and each site-team member then needs a new token. The entry is keyed by its name, "LTC
administration", so renaming that key does the same. Retire or rename it deliberately.

**Principle XI exceptions added by spec 008.** `cli/setup_publishing.php` still makes two
direct `$DB` writes, which predate spec 008: it inserts the publisher's
`external_services_users` row and deletes its `external_tokens` rows itself, where
`cli/setup_admin_token.php` uses `webservice::add_ws_authorised_user()` and
`webservice::delete_user_ws_token()`. They stay listed here until the publisher's script is
moved onto the same core APIs.

**Raw reads added by spec 008** (the administration service), all read-only. The organisation actions it calls (`organisation\actions::do_*()`) make only the reads listed for spec 002 above.

| Table | Read by | Why there is no API |
|---|---|---|
| `cohort` | `idnumber LIKE 'ltct:%'` (`admin_list`); `idnumber` exact and `id` (`admin\intake_service`, `admin\cohort_enrolment`) | Every organisation cohort is hidden and in system context, which `cohort_get_all_cohorts()` filters by visibility for the caller. `cohort.idnumber` is not indexed in core; the table holds tens of rows. |
| `cohort_members` | `cohortid`, a count (`admin\cohort_enrolment::member_count()`) | The member count a cohort-enrolment preview prints. `core_cohort_get_cohort_members` returns every user id, not a count (research R18). |
| `course` joined to `course_categories` | `course.idnumber LIKE 'ltct:%'`, the category's `idnumber` (`admin_list`) | The courses the site team may name, with the category that decides which organisations may be enrolled. `core_course_category` lists by category, not by course idnumber. |
| `course` | `idnumber` exact, and `id` (`admin\intake_service::resolve()`, `admin\cohort_enrolment`) | The course an intake row or a cohort enrolment names. `get_course()` takes an id; there is no lookup by idnumber that is not a read. |
| `course_categories` | `id` (`admin\intake_service`, `admin\cohort_enrolment`, `admin\move_service::category_idnumber()`, `admin_summary`) | The idnumber of one course's category, for `access::may_enrol_into()`, `admin\enrolment_rules`, `admin\move_rules` (an organisation-only course of the old organisation) and the summary. |
| `role` | `shortname` of the role `enrolment_rules` gives (`admin\cohort_enrolment`) | The Student (or `orgmanager`) role's id for a new enrolment instance. Core has no lookup of a role by shortname that is not a raw read. |
| `user` | `deleted = 0`, `mnethostid`, `email` compared case-insensitively (`admin\intake_service::match_accounts()`, also called by `admin\suspension_service`, `admin\move_service` and `admin\membership_service`); `username` and `mnethostid` exists, deleted rows included (`choose_username()`, `new_username()`) | Matching an intake row to every live account with its email, so two accounts for one email are refused rather than one being picked; and checking that a new username is free, over the `(mnethostid, username)` unique index, which holds deleted accounts too. `core_user::get_user_by_email()` is case-sensitive and returns one record. The table has no index on `email`; intakes are tens of rows. |
| `enrol` | `enrol = 'cohort'` and `customint1` (a cohort id), with `status` (`admin\cohort_enrolment::enabled_courses()`, used by `enrol mirror` and `admin\move_service`; `admin_summary`) | Which courses one cohort is enrolled in. `enrol_get_instances()` reads one course at a time; the question is "which courses", across all of them. |
| `cohort` | `idnumber` exact, with `contextid` and `component` (`admin\membership_service::resolve()`); `idnumber` exact (`admin\move_service`, `admin_summary`) | The managers cohort or `ltct:mentors` a managers file names, and whether a plugin owns its members; an organisation's cohort for a move or a summary. As the first `cohort` row. |
| `cohort_members` joined to `user` | `cohortid`, `user.deleted = 0`; returns `id`, `email`, `suspended` (`admin_summary`) | One organisation's members, masked on the server, and the suspended count. `core_cohort_get_cohort_members` returns user ids only (research R18). |
| `user_enrolments` | `enrolid` and `status`, a count (`admin_summary`) | Active enrolments through one cohort-sync instance. `count_enrolled_users()` counts by course, not by instance. |
| `course` | `id` (`admin_summary`) | The idnumber and category of the course an instance belongs to. As the `course` rows above. |
| `role` | `shortname` in (`teacher`, `student`, `mentor`) (`admin\course_mentor_sync::role_ids()`); `shortname = 'mentor'` (`admin\membership_service::mentor_setup()`) | The course-mentor, learner and mentor roles' ids. As the first `role` row. |
| `role_assignments` joined to `context` | mentor `roleid`, `contextlevel = user`, `instanceid` in the course's learners (`admin\course_mentor_sync::default_mentors()`); `userid` of one mentor (`sync_mentor()`, `admin\membership_service::mentor_learners()`, with `component = ''`) | A learner's default mentors, and one mentor's learners: the same read as spec 003's Mentoring page (above), restricted to those people. `get_role_users()` reads one context at a time and never by holder. |
| `role_assignments` | `contextid`, `roleid` (Teacher), `component = 'local_ltuse'`; returns `userid`, `itemid` (`admin\course_mentor_sync::state()`); joined to `context` and `course` for the same in courses no longer `ltct:` (`remove_stray_roles()`) | The Teacher assignments the sync gave and must take away. `get_role_users()` does not return `component` or `itemid`. |
| `groups`, `groups_members` | `courseid` and `idnumber LIKE 'ltct:mentorgroup:%'`; `groupid` and `component` (`admin\course_mentor_sync`) | The mentor groups and the memberships the sync owns, as office hours' (above). |
| `course` | `idnumber LIKE 'ltct:%'` (`admin\course_mentor_sync::reconcile()`); `id` (`admin\observer`, `sync_course()`) | Every course the reconcile visits, and whether an event's course is one the sync looks after. As the `course` rows above. |
| `user_enrolments`, `enrol` | `id`, for an event's `enrolid` and its instance's `enrol` and `customchar1` (`admin\observer::user_enrolment_changed()`) | Whether a user-enrolment event is about the course-mentor instance, so the sync ignores its own writes. The event carries only the user enrolment's id. |
| `user` | `id`, returns `email` (`admin\membership_service::preview_end()`) | The masked email of each learner an end-all lists. `core_user::get_user()` reads the whole record. |
| `course`, `cohort` | `idnumber` exact, returns `id` (`admin\course_mentor_records::resolve()`, including `ltct:mentors`; `admin\membership_service::mentor_setup()`, `ltct:mentors` only) | The course and cohort a course-mentors row names, and the mentors cohort a mentor must belong to. As the first `course` and `cohort` rows. |
| `local_ltuse_course_mentor` left-joined to `course`, `user`, `cohort` | `id` (`admin\course_mentor_sync::remove_orphan_records()`) | Course-mentor records whose course, cohort or people are gone. The plugin's own table. |

`admin\course_mentor_sync` reads each course's enrolments with core's
`enrol_get_course_users()` and `enrol_get_instances()`, and each person's courses with
`enrol_get_all_users_courses()`. It writes only through core: `enrol_self_plugin::add_instance()`,
`enrol_user()`, `update_user_enrol()`, `unenrol_user()`, `update_status()`, `role_assign()`,
`role_unassign()`, `groups_create_group()`, `groups_add_member()` and `groups_remove_member()`.

`admin\move_service` reads a learner's active courses and enrolments with core's
`enrol_get_all_users_courses()`, `enrol_get_course_users()` and `enrol_get_instances()`, and
`admin\membership_service` writes with `cohort_add_member()` / `cohort_remove_member()` after
`cohort_is_member()`; none of these is a raw read.

## Identity protection (spec 016)

Some learners need to take part under less of their identity: email hidden, first name only,
or a pseudonym. Protection is per person, only for someone who asks; no organisation sets a
minimum for its members (Doug, 2026-10-05 (scope review)). Moodle 5.2 has no hook into
`fullname()`, and every view renders names live from the user record, so this plugin writes
the protected display into the account itself and keeps the real values in its own table
(research R1, R5). **Who is protected, their pseudonym and their real identity are Moodle
data, never the repo's.** Not yet run on a server; quickstart V1–V17 are the instance checks.

| Piece | Where | Does |
|---|---|---|
| Rules | `classes/protection/levels.php` (pure, `tests/protection_harness.php`) | The four levels, the withheld fields per level, pseudonym and username rules (NFC and case folding), the email-address warning, what a manager who is not the site team may change, and which courses count for a course mentor. Every level is available once `protection.yaml` is stored: the organisation stays visible at every level (decision 2, option a). |
| Entitlement | `classes/protection/entitlement.php` | `can_view_identity()`: the site team, an assigned mentor, a course mentor in an `ltct:<slug>` course the learner takes (never `ltct:officehours`) whose own group there, `ltct:mentorgroup:<mentor id>` (spec 008), holds the learner, and a manager of the learner's own organisation. `can_manage_protection()`, `is_site_team()`, `may_be_entitled()` (the cheap test before "People I support"), `marker()`. Every surface asks it; none checks a capability alone. |
| Service | `classes/protection/service.php` | The one place a level is applied: `set_protection()`, `apply()` (a repair at the level already set), `effective_level()`, `is_settled()`, `level_available()`, `real_identity()`, `email_warnings()`, `picture_levels()` (the granting page's warning before a picture is deleted), `neutral_username()`, `sync_log_blocks()`. A raise needs `requested` (the person asked) and `emailchecked` (the address identifies neither them nor their organisation), and the log row records both. Anyone but the site team grants only at intake: a raise, before any activity (`user.firstaccess`, or an enrolment), with no correction. At `firstname`+ a username that holds the real name becomes `ltc-` and 8 base32 characters, spec 008's `intake_service::new_username()` format; the person still signs in with their email. Each write runs under a per-user lock and one transaction, with the user in a private bypass set the hook and observer skip. It snapshots each newly withheld field, rewrites the account through `user_update_user()` and `profile_save_data()`, deletes the picture at `firstname`+, keeps the account on a site login (`auth` `manual` or `nologin`, no `auth_oauth2` linked login, R3), logs, purges `core/coursecontacts`, re-saves office-hours slots and sends `protectionchanged`. It never lowers anyone automatically. A person who asks for their location to be hidden (`hidelogs`) gets `editingteacher` and `teacher` prohibited `report/log:view`, `report/log:viewtoday` and `report/loglive:view` in each course they are enrolled in, through `assign_capability()`; this plugin owns those course-level prohibits, so one set by hand where nobody asked is removed (R14). |
| Enforcement | `classes/protection/hook_callbacks.php`, `classes/protection/observer.php`, `classes/task/apply_protection.php`, `classes/task/reconcile_protection.php` | `before_user_updated` re-applies the protected values and the site login on every `user_update_user()`, and never throws: a failure goes to `debugging()`. The `user_updated` observer returns at once for anyone with no protection row and re-applies a protected account that drifted; `user_deleted` runs the shared deletion. The hourly task reads only the protected rows, does nothing when there are none, repairs drift (a non-site login and linked logins included), brings the course-log block into line with the courses of those who asked, and fails the run when any repair fails, so core's failed-task handling flags it; its log has counts only. |
| Surfaces | `lib.php` (profile node), `classes/mentoring.php` and its templates, `protected.php`, `protection.php`, `classes/protection/surfaces.php` | The real identity and the **Protected** marker appear only here, only to the entitled: the profile, the Mentoring page (web and app), "People I support" (no download), and the granting page. A non-entitled viewer never sees the marker. The profile section appears only on a protected person's profile, and on your own only when you are protected or support someone who is; a first grant is made at intake, so an unprotected profile has no granting link. |
| Web services | `local_ltuse_set_protection` | The granting page's logic for scripts and spec 008. `requested` is a required parameter; `emailchecked` is needed for a raise. Not in the publishing service. Never returns a real identity. |
| Site config | `classes/siteconfig/protection.php` | `apply` stores `protection.yaml` in `local_ltuse/protection`; `drift` reports a count only (accounts awaiting repair). |
| Privacy | `classes/privacy/provider.php` | The two tables: a user's own rows in full, changes they made to others as a count and dates, deletion shared with the `user_deleted` observer. |

**Principle XI exceptions added by spec 016.** Each is the only route to the behaviour.

| Exception | Why | Re-checked by |
|---|---|---|
| Mutating the object `before_user_updated` carries. The hook is a notification; the change persists because `user_update_user()` keeps using the same object after dispatch (`user/lib.php`, `MOODLE_502_STABLE`). | No 5.2 hook overrides a display name, and every view renders from the record (R1, R2). | `tests/protection_test.php` `test_the_hook_reapplies_the_protected_record`, and quickstart V7 on every core upgrade while anyone is protected. `user_update_user()`, which dispatches it, is deprecated for 5.3 (MDL-82650): re-check the hook on the 5.3 API before widening `$plugin->supported` |
| `mod_scheduler`'s `\mod_scheduler\model\slot::load_by_id()->save()` to re-save a protected user's future slots, which re-runs its private `update_calendar()` and renames the calendar events it names from `fullname()`. | The scheduler stores names in `{event}.name`; nothing else rewrites them (R15). Re-saving changes no time, so spec 011's booking notice stays silent. | Quickstart V17 on every scheduler re-pin |

**Raw reads added by spec 016**: `user_info_field` (`shortname`, `datatype`, every row, a few
dozen) to blank the withheld profile fields, and, by indexed columns, `scheduler_slots` and
`scheduler_appointment` by `teacherid` and `studentid`, and `user` by its unique
(`mnethostid`, `username`) index to keep a generated username unused, as spec 008's intake does. For the course-log block: `user_enrolments` joined to `enrol` for the courses of the protected rows with `hidelogs`, and `role_capabilities` joined to `context` for the course-level prohibits of the three log capabilities. Linked logins are read and deleted through `\auth_oauth2\linked_login`, never the table.

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
- **Enrolment through the administration service and the organisation page; never grades.**
  The publish functions create no learner data. Accounts, cohort membership and enrolments
  are written only by the administration service (spec 008) and spec 002's organisation
  page, through core's APIs, and never stored in the repo. Learner data is Moodle's alone
  (`INTENT.md`: *"Learner data lives in Moodle, never in this repo"*). Nothing here writes a
  grade, an attempt or a completion. Spec 004 comes closest, and stops here: `set_course_completion` flags incomplete course
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
