# Research: Learning pathways mapped to CBC

Each entry is a decision the plan rests on. "Verify" items are instance or source checks that
block the task that depends on them (constitution X, XI).

## R1. The mechanism: our own pathway pages over 004's competency map

**Decision**: A pathway is not a stored object in Moodle. `local_ltuse` builds it at view time
from tables spec 004 already fills on every publish (`local_ltuse_competency`,
`local_ltuse_course_comp`), plus one small table of its own, `local_ltuse_course_pathway`, which
holds each published course's delivery state and target level (R2). Learners, mentors and
managers see it on `local_ltuse` pages and in the Moodle app through a mobile handler, as 003's
Mentoring page does.

**Rationale**: FR-002 and FR-006 want a pathway that nobody can hand-build or hand-edit. A
pathway that is computed from publisher-owned tables has nothing to edit, so it cannot drift
from the repo, and a republish changes it with no extra step (SC-005). It adds no plugin, and
it keeps the one competency list and course map that 004's coverage report already counts,
which is what makes FR-014 true by construction: both read the same frontmatter through the
same web service, and the publisher already exits 1 when the map it reads back differs.

**Alternatives considered**:
- **Core competency learning plans (`tool_lp`), the core-first option.** Plan templates can be
  synced to cohorts, which fits FR-013. But a plan is a list of competencies, each with a
  *rating* and a *proficient* flag, shown on the plan page and in the app's Learning plans
  screen. That is exactly the CBC level FR-011 and constitution V forbid us to show. A
  framework also needs a scale with a proficient value, course links attach evidence on
  completion by default, and site admins can rate regardless of capabilities. 004 R15 already
  rejected `tool_lp` for these reasons and noted the choice would constrain this spec. Plans
  also cannot order courses by target level, or say "no course yet" for a level.
- **Programs (`tool_muprog`, free, Petr Škoda).** Its GitHub README and a search result now say
  the suite supports Moodle 4.5 to 5.2. That is unverified on our instance. More decisive: it
  needs four more plugins (`tool_mulib`, `enrol_muprog`, `block_muprog_my`,
  `block_muprogmyoverview`). It gives course access through its own enrolment plugin, which
  would be a second enrolment mechanism beside 002's cohort sync and organisation enrolment
  (and beside 008's, see R9). Programs are composed in its UI, and no web service for building
  them is documented, so a derived pathway would need our own code writing into its tables.
  Principle XI forbids that. Every plugin is an upgrade liability (Platform: core first).
  It also does sequencing and locking we must not use (FR-008).

**Verify** (blocks US1): none for the mechanism itself: it uses only our own tables and core
completion. The core APIs it calls are in R11.

## R2. Which courses are in a pathway: a delivery flag the publisher writes

**Decision**: A new web service, `local_ltuse_set_course_pathway(courseid, delivery,
targetlevel)`, called by the publisher on every publish right after
`local_ltuse_set_course_competencies`. It upserts one row of `local_ltuse_course_pathway`
(courseid unique, delivery 0/1, targetlevel 1–4). `delivery` is the payload's existing
`recognition.delivery`, which `course_stage.py` decides (stage 8), exactly as spec 013 uses it.
`targetlevel` is the leading digit of the frontmatter `target_outcome_level`.

A course is in a competency's pathway when all hold: its idnumber is `ltct:<slug>` (no second
colon); it exists and is visible; its `local_ltuse_course_pathway` row says delivery = 1; and
its map row names that competency, which is not retired.

**Rationale**: FR-005 says only stage-8 courses appear, and the only stage authority is
`course_stage.py` (constitution I). The published category is not a reliable marker: the
category is passed by hand with `--category` and set only on creation. 013's badge activation
records delivery only as a side effect of an inactive-to-active badge, so reading it would tie
pathways to badges. A course custom field could hold it, but a site admin can unlock and edit a
custom field, and course fields support only text in our declaration. A plugin table is written
only by the publisher's web service, which is what FR-006 wants. The numeric level lets SQL
order courses without parsing "2 - With Assistance".

A course that is unpublished is hidden by the site team (see the plan's retirement note); a
hidden course leaves pathways at once, and its learners keep their completion record.

**Alternatives considered**: reading `ltct_target_level` text and the category (rejected
above); extending 004's `set_course_competencies` with a `delivery` parameter (rejected: it
changes 004's contract and mixes two concerns in one call).

## R3. Pathway keys

**Decision**: A pathway is named by a key: `competency:<slug>` for a competency pathway, where
`<slug>` is the descriptor's frontmatter `slug` (for example `competency:keyboards`), and
`role:<key>` for a role pathway, where `<key>` is declared in `moodle/site/pathways.yaml` and
matches `^[a-z][a-z0-9-]*$`. Keys are at most 100 characters.

**Rationale**: a stable, readable key that 008 can pass (`assignments::assign`), that holds no
database id, and that a rebuilt server reproduces from the repo. The descriptor slug is already
the competency's public URL segment.

## R4. "No course yet" links to the competency site

**Decision**: `site_config.py` adds `slug` and `url` to each entry of the payload's
`competencies` array. The slug comes from the descriptor in `competencies/` whose `name`
matches. The url is `<mkdocs.yml site_url><category slug>/<slug>/`, the path `gen_site.py`
generates (`slugify(category)/<slug>.md`, served as a directory URL). The plugin stores both in
two new columns of `local_ltuse_competency`, and `differences()` compares them, so `apply`
sets them back like any other declared property.

**Rationale**: the host comes from repo data, never from PHP (Platform: no hard-coded host).
Every framework competency outside Meta has a descriptor, which the descriptor check already
enforces in CI, so every row has a page.

## R5. Role pathways are declared in `moodle/site/pathways.yaml`

**Decision**: A new optional top-level declaration file, `pathways.yaml`, with `rows: [12]`, a
`purpose`, and `roles:`, a list of `{key, name, description, competencies: [names], why}`.
`validate` refuses an unknown competency name (verbatim against `competencies.yaml`, Meta
excluded), a duplicate key or competency, a name that breaks `cbc_wording`, and a level word
in a role's name or description. `apply` writes two plugin tables, `local_ltuse_role_pathway`
and `local_ltuse_role_pathway_comp`, retiring a role that is no longer declared and never
deleting it, as `competencies.php` does.

The file ships with **`roles: []`**. Which roles exist and which competencies each needs is
supplied by a human (spec Assumptions); no role is invented here. The README says how to add
one.

**Rationale**: FR-007, constitution II. Retiring rather than deleting keeps cohort assignments
pointing at something.

## R6. How a pathway is laid out, and what "next" means

**Decision**: A competency pathway shows the four target levels in order, `1 - Has Knowledge`
to `4 - Expert`, labels read from `outcome-levels.yaml` through the payload. Under each level
it lists the delivered courses aiming there, by course full name. A level with none says "No
course yet" and links to the competency's page (R4). For the viewer's learner, each course is
*completed* (core course completion recorded), *in progress* (enrolled, not complete) or
*not started*. The **next** course is the first course, in that order, that is not completed.
When every listed course is completed, the pathway says "You have completed the training on
this pathway", with no level named (FR-011). Nothing is locked: every course links to its
course page (FR-008).

A role pathway shows its competencies in declared order, each with its competency pathway
folded beneath it, and a combined figure: "N of M courses completed", counting a course once
even when it serves several of the role's competencies.

**Rationale**: FR-003, FR-004, FR-008, FR-009, US1–US3. A course that serves two competencies
appears in both and counts in both (spec Edge Cases). Two courses at one level are both shown;
neither is "first" in any sense the learner must follow.

## R7. Completion is core course completion, read as 003 and 004 read it

**Decision**: Pathway progress reads `course_completions.timecompleted` for the learner, the
same record 004 defines as completion and 003's Mentoring page shows (FR-010). It reuses
`mentoring::progress_status()` for the state of each course.

## R8. Who sees whose pathway

**Decision**: one predicate, `pathway\viewer::may_view(viewerid, learnerid, facts)`, pure and
harness-tested, true when:
- the viewer is the learner; or
- the viewer holds `local/ltuse:viewmenteeprogress` in the learner's user context (003's mentor
  relationship, FR-012); or
- `organisation\access::is_org_member_of_manager()` holds (002's scope, by managers-cohort
  membership and the learner's organisation member cohort, never the `ltct_org` value alone);
  or
- the viewer has `moodle/site:config`, the site team.

Names are shown with `fullname()` only. For a protected learner (spec 016) that already gives
the protected display, so this spec reads no real name and needs nothing from 016.

**Rationale**: FR-012 and US4's "no one else's". 002's predicate already fails closed on a
missing fact.

## R9. Assigning a pathway to a cohort, and the seam with spec 008

**Decision**: Table `local_ltuse_pathway_cohort` (pathwaykey, cohortid, enrol, usermodified,
timecreated, timemodified; unique pathwaykey + cohortid). A learner's pathways are those
assigned to any cohort they belong to, de-duplicated (spec Edge Cases). Any learner may also
browse every competency pathway from the same page.

Who may assign:
- the site team (`moodle/cohort:assign` at system context), to any cohort;
- an organisation manager, to their own organisation's member cohort `ltct:org:<key>` and to any
  cohort whose idnumber starts `ltct:org:<key>:` except the managers cohort, for each key in
  `local_ltuse_managed_organisation_keys()`.

**006 never enrols anyone.** An unenrolled learner sees the course in their pathway with a link
to its course page, where 002's organisation enrolment or 017's request applies. Spec 008 owns
the one enrolment mechanism. It sets `enrol = 1` through `assignments::assign($key,
$cohortid, true)`, enrols the cohort, and observes `\local_ltuse\event\pathway_courses_changed`
to enrol into a course that later joins the pathway. 006 never sets `enrol = 1`. Neither unassign
nor a course leaving a pathway unenrols anyone. This split was agreed with the 008 session on
2026-10-04 and is pinned in [contracts/pathway-api.md](contracts/pathway-api.md).

**Rationale**: FR-013. Whether a manager's assignment should also enrol is a product decision
the spec does not make, and it would let an organisation manager create cohort enrolments that
today only the site team makes. If it is wanted later, it is one observer in 008, with no change
here.

**Alternatives considered**: per-user assignment (not needed: a cohort of one serves it, and
the spec's entity says "a learner or cohort" only as what can be linked); syncing core learning
plan templates to cohorts (R1).

## R10. Entry points: primary navigation, profile, and the app

**Decision**: A "Pathways" item in the primary navigation, added by the existing
`primary_extend` hook callback for every signed-in non-guest user. The dashboard is the landing
page and the primary navigation is on it, so pathways are reachable from it (FR-009). Spec 007
may later put a pathway summary on the dashboard itself; this spec does not change the dashboard
layout, which 007 owns. A mentee's entry on the Mentoring page links to their pathways. In the
app, a `CoreMainMenuDelegate` handler "Pathways" renders the same data, as 003's Mentoring
handler does (FR-015).

## R11. APIs this plan relies on, to confirm in `MOODLE_502_STABLE` before use

Each is confirmed in upstream source before the task that uses it closes (constitution XI). The
plan's tasks record file and line.
- `\core\event\base` for a plugin event: `init()` (`crud`, `edulevel`, `objecttable`),
  `get_name()`, `get_description()`, `validate_data()`; `::create([...])->trigger()`.
- `\core\hook\navigation\primary_extend::get_primaryview()` and `navigation_node::add()`
  (already used by 003).
- `cohort_is_member()` and the `{cohort_members}` join in `cohort/lib.php`.
- `completion_info::is_enabled()` and `course_completions.timecompleted` (already used by 003).
- `core_external\external_api`, `external_function_parameters`, `external_value`
  (already used throughout).
- `CoreMainMenuDelegate` handler shape in `db/mobile.php` (already used by 003).
- XMLDB `upgrade.php` steps for new tables and for added fields (`add_field()` on an existing
  table, `field_exists()`).

## R12. Meta: Uncategorized has no pathway

**Decision**: A course whose only competency is `Uncategorized` (category Meta) is in no
pathway. This follows 004, which leaves Meta out of the competency list and the course map, and
the competency site, which has no Meta page.

**Note**: the spec's Edge Cases say such a course "gets a pathway". Giving it one would need a
Meta row in 004's list, which 004 R15 excludes and its coverage report would then count. This
is recorded as a deviation for the maintainer to confirm, not decided silently.

## R13. Keeping a CBC level off every pathway screen

**Decision**: Levels appear only as the *target* of a course ("Aims at 2 - With Assistance")
and as the headings of the level rows. Strings say "aims at", never "reached", "achieved",
"attained", "certified" or a learner's level. A pytest runs `cbc_wording.report_label_problems(
strict=True)` over every 006 lang string, as 004's test does for its datasource strings.
`pathways.yaml` names and descriptions pass the same check in `validate` (FR-011, SC-004).

## Confirmed in source (T001)

Checked on 2026-10-04 in upstream `MOODLE_502_STABLE` (paths under `public/`):
- **Events.** `lib/classes/event/base.php`: `abstract protected function init()` (:233–244) sets
  `crud` (`c`/`r`/`u`/`d`), `edulevel` (`LEVEL_OTHER` :58) and `objecttable` when there is an
  `objectid`; `final public static function create(?array $data = null)` (:195);
  `protected function validate_data()` (:276); `get_name()` (:292), `get_description()` (:313),
  `get_url()` (:354). `other` holds scalars and arrays only, never objects (:50, checked :556).
- **`\core\event\cohort_deleted`** (`lib/classes/event/cohort_deleted.php`): `objecttable`
  `cohort` (:43), `objectid` the cohort id (:56), no record snapshot. Our observer needs only the
  id.
- **XMLDB** (`lib/ddl/database_manager.php`): `table_exists($table)` (:90),
  `field_exists($table, $field)` (:133), `create_table(xmldb_table)` (:329),
  `add_field(xmldb_table, xmldb_field)` (:411), `index_exists` (:185), `add_index` (:635).
- Already confirmed by earlier specs and reused unchanged: `primary_extend` and
  `CoreMainMenuDelegate` (003, research "Source results T001"), `completion_info` and
  `course_completions` (003 R3, 004 R3), `core_external\external_api` (004 R15).
- **Cohort membership** is read with a join on `{cohort_members}` (`cohortid`, `userid`), the
  indexed columns `cohort/lib.php`'s `cohort_is_member()` itself queries, so no core function is
  needed per row.
