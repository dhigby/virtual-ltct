# Data model: Learning pathways mapped to CBC

A pathway is never stored. `local_ltuse` computes it at view time from tables the publisher and
`site_config.py apply` fill (research R1). The repo holds declarations only: role pathways in
`moodle/site/pathways.yaml`, and course frontmatter, `competencies.yaml`, `outcome-levels.yaml`
and the descriptors, which already exist. Of the tables below, only `local_ltuse_pathway_cohort`
touches a user, through `usermodified`. Learner progress is read from core completion and never
copied.

## Pathway key (R3)

The one name a pathway has, everywhere: in the cohort-assignment table, in the URL, in events
and in the 008 API.

| Kind | Form | Source of the variable part |
|---|---|---|
| Competency pathway | `competency:<slug>` | `slug` in the frontmatter of the descriptor in `competencies/` whose `name` matches |
| Role pathway | `role:<key>` | `key` of a role in `moodle/site/pathways.yaml` |

**Validation**:
- `<slug>` and `<key>` match `^[a-z][a-z0-9-]*$`. The whole key is at most 100 characters.
- A key holds no database id, so a rebuilt server reproduces every key from the repo.
- A key resolves only to a row that is not retired. A key that does not resolve is refused by
  `assignments::assign()` and shows nothing on a page.

## Declarations (repo)

### Competency entry, extended (R4)

Each entry of the payload's `competencies` array (spec 004, built by `_competency_list()`) gains
two fields:

| Field | Type | Rule |
|---|---|---|
| `slug` | string | The matching descriptor's frontmatter `slug`, verbatim. Matches the key pattern. |
| `url` | string | `<mkdocs.yml site_url><slugify(category)>/<slug>/`, the page `gen_site.py` generates. |

**Validation** (`validate`): every non-Meta competency has exactly one descriptor whose `name`
matches it verbatim; slugs are unique across the framework; `site_url` is read from `mkdocs.yml`
and ends in `/`; the url is at most 255 characters. The host is never written into PHP.
`Meta: Uncategorized` stays out of the list, as in 004, so it has no slug, no url and no pathway
(R12, a deviation from the spec's Edge Cases for the maintainer to confirm).

### Level labels

The payload gains `levels`: the entries of `outcome-levels.yaml` `levels` whose `id` is in
`course_target_levels`, as `[{level, label}]`, in id order ([contracts/declaration.md](contracts/declaration.md)).

**Validation**: exactly the ids `1`–`4`, each with a non-empty label copied verbatim. Level `0`
is never sent: no course aims at it, so no pathway row is headed by it.

### Role pathway (`moodle/site/pathways.yaml`, R5)

```yaml
rows: [12]
purpose: Role pathways for spec 006 ...
roles: []        # supplied by a human; none is invented
```

Each item of `roles`:

| Field | Type | Rule |
|---|---|---|
| `key` | string | Matches `^[a-z][a-z0-9-]*$`; `role:<key>` is at most 100 characters; unique in the file. |
| `name` | string | Required, at most 255 characters. Passes `cbc_wording` strict; contains no CBC level label or level word. |
| `description` | string | Optional. Same wording rules as `name`. |
| `competencies` | list of strings | At least one. Each a `competencies.yaml` name, verbatim, not in Meta. No duplicates within the role. Order is the display order. |
| `why` | string | Required. Who supplied the role and on what authority. Stays in the repo; not sent to Moodle. |

Unknown fields are refused. The file is optional; absent means `roles: []`. `drift` and `apply`
report role keys and competency names only, never an assignment or a learner.

## Publisher payload and call (R2)

No new manifest fields. The publisher reads two it already has:

| Payload field | Becomes |
|---|---|
| `recognition.delivery` (spec 013; `course_stage.py` says stage 8) | `delivery`: `1` or `0` |
| `target_outcome_level` (spec 004) | `targetlevel`: the leading digit, `1`–`4` |

It calls `local_ltuse_set_course_pathway(courseid, delivery, targetlevel)` on every publish,
immediately after `local_ltuse_set_course_competencies`, so a pathway is never computed from a
stale map. It reads the row back and exits 1 on a mismatch, as it does for the map.

**Validation**:
- Publisher, before sending: on a delivery publish, a missing `target_outcome_level`, or one
  whose leading digit is not in `course_target_levels`, is an error and nothing is sent. On a
  non-delivery publish (a pilot), a missing level is sent as `0`.
- Web service: `courseid` is a course whose idnumber is `ltct:<slug>` (no second colon);
  `delivery` is `0` or `1`; `targetlevel` is `0`–`4`, and `0` only when `delivery = 0`. Anything
  else throws `invalid_parameter_exception` and writes nothing.

## Tables

All are `local_ltuse` tables, created by `db/install.xml` and by an `upgrade.php` step to
`2026100600`.

### `local_ltuse_competency` (spec 004), two new fields

| Field | Type | Rule |
|---|---|---|
| `slug` | char(100), not null, default `''` | From the payload. Unique among non-empty values (non-unique index; the check is in `apply`). |
| `url` | char(255), not null, default `''` | From the payload. |

`differences()` compares both, so `apply` sets them back like `category` and `sortorder`. Rows
are retired, never deleted (004). A retired competency has no pathway.

### `local_ltuse_course_pathway` (new)

One row per published course. Written only by `local_ltuse_set_course_pathway`.

| Field | Type | Rule |
|---|---|---|
| `id` | int(10), sequence | |
| `courseid` | int(10), not null | `foreign-unique` to `course.id` |
| `delivery` | int(1), not null, default 0 | `1` when the last publish was a delivery (stage 8) |
| `targetlevel` | int(1), not null, default 0 | `1`–`4`; `0` when the course declares no `target_outcome_level` |
| `pathwaykeys` | text, null | The pathway keys last announced for this course, so the next publish fires `pathway_courses_changed` for the difference even though 004's map changed first ([contracts/publish.md](contracts/publish.md)) |
| `timemodified` | int(10), not null, default 0 | |

No user data.

### `local_ltuse_role_pathway` (new)

One row per role ever declared. Written only by `apply`.

| Field | Type | Rule |
|---|---|---|
| `id` | int(10), sequence | |
| `rolekey` | char(95), not null | The declared `key`. Unique index. |
| `name` | char(255), not null | |
| `description` | text, null | |
| `sortorder` | int(10), not null, default 0 | 1-based position in `pathways.yaml` |
| `retired` | int(1), not null, default 0 | `1` once no longer declared |
| `timemodified` | int(10), not null, default 0 | |

No user data. Not deleted when undeclared, so cohort assignments keep pointing at something.

### `local_ltuse_role_pathway_comp` (new)

Which competencies a role names, in order. Written only by `apply`, which replaces a role's rows
to match the declaration.

| Field | Type | Rule |
|---|---|---|
| `id` | int(10), sequence | |
| `roleid` | int(10), not null | `foreign` to `local_ltuse_role_pathway.id` |
| `competencyid` | int(10), not null | `foreign` to `local_ltuse_competency.id` |
| `sortorder` | int(10), not null, default 0 | 1-based position in the role's `competencies` |

Unique index (`roleid`, `competencyid`). No user data.

### `local_ltuse_pathway_cohort` (new, R9)

A pathway assigned to a cohort. The seam with spec 008 ([contracts/pathway-api.md](contracts/pathway-api.md)).

| Field | Type | Rule |
|---|---|---|
| `id` | int(10), sequence | |
| `pathwaykey` | char(100), not null | A pathway key that resolved when assigned |
| `cohortid` | int(10), not null | `foreign` to `cohort.id` |
| `enrol` | int(1), not null, default 0 | Set to `1` only by spec 008 through `assignments::assign($key, $cohortid, true)`. 006 never sets it and never acts on it. |
| `usermodified` | int(10), not null | `foreign` to `user.id`; who made or last changed the link |
| `timecreated` | int(10), not null, default 0 | |
| `timemodified` | int(10), not null, default 0 | |

Unique index (`pathwaykey`, `cohortid`). The privacy provider declares `usermodified`; an export
lists the assignments a user made, and a deletion request sets `usermodified` to 0
rather than deleting the assignment, which belongs to the cohort. Rows for a deleted cohort are
removed by an observer on `\core\event\cohort_deleted` (confirm in `MOODLE_502_STABLE`, R11).

### Plugin config

`local_ltuse | pathwaylevel1` … `pathwaylevel4`: the payload's `levels` labels, one setting per
level, written by `apply` and compared by `drift`. The plugin reads labels from here and nowhere else; a missing or
malformed value makes pathway pages show an error, never a guessed label.

## Pathway membership (R2, R12)

`pathway\catalogue` answers, in SQL, which courses are in competency `C`'s pathway. A course is
in it when all hold:

1. `course.idnumber` is `ltct:<slug>` with no second colon;
2. `course.visible = 1`;
3. its `local_ltuse_course_pathway` row has `delivery = 1`;
4. a `local_ltuse_course_comp` row links it to `C`;
5. `C` is not retired.

A course meeting all five for several competencies is in each of their pathways. A role
pathway's courses are the union over its non-retired competencies, de-duplicated by course id.
A course with no `course_pathway` row (published before 006, not yet republished) is in no
pathway.

**FR-014**: this reads the same `course_comp` rows 004's coverage report counts, so a pathway and
`COVERAGE.md` list the same delivered courses for a competency.

## View model (computed, never stored, R6)

Built by the pure `pathway\builder` from facts handed to it; tested by `pathway_harness.php`.

### Competency pathway view

| Field | Value |
|---|---|
| `key` | `competency:<slug>` |
| `title` | Competency name, verbatim |
| `levels` | Four level rows, ids 1–4 in order |
| `nextcourse` | The first course, in level then course full-name order, whose status is not `completed`; null if none |
| `done` | True when the pathway lists at least one course and every one is `completed` |

Level row:

| Field | Value |
|---|---|
| `id`, `label` | From `pathwaylevel<n>` |
| `courses` | Courses at this target level, by full name |
| `nocourseyet` | True when `courses` is empty |
| `competencyurl` | The competency's `url`; present only when `nocourseyet` |

Course entry:

| Field | Value |
|---|---|
| `courseid`, `fullname`, `url` | From `course`; `url` is the course page, always linked (FR-008) |
| `status` | `completed`, `inprogress` or `notstarted`, for the learner being viewed |
| `next` | True for the one course that is `nextcourse` |

The page says "Aims at <label>" for a course and, when `done`, "You have completed the training
on this pathway". No field holds a level for the learner, and no string names one (FR-011,
R13).

### Role pathway view

| Field | Value |
|---|---|
| `key`, `title`, `description` | From `local_ltuse_role_pathway` |
| `competencies` | A competency pathway view per non-retired competency, in declared order |
| `completed`, `total` | Distinct courses across the role, and how many are `completed`, each course counted once |
| `done` | `total > 0` and `completed = total` |

### A learner's pathways

The distinct pathway keys assigned to any cohort the learner belongs to, resolving keys that are
retired or unknown to nothing. A key reached through two cohorts appears once. Every competency
pathway is also browsable by anyone signed in.

## Course status for a learner (R7)

Read from core, never stored. `mentoring::progress_status()` decides it.

```text
notstarted --(enrolled)--> inprogress --(course_completions.timecompleted set)--> completed
```

| Status | Condition |
|---|---|
| `completed` | `course_completions.timecompleted` is set for the learner and course |
| `inprogress` | enrolled, not completed |
| `notstarted` | not enrolled and not completed |

A completed course stays `completed` after unenrolment, and after the course leaves the
pathway the completion record remains (spec Edge Cases), though the course is no longer listed.

## State transitions

### A course in pathways

| From | Event | To |
|---|---|---|
| no row | first publish, pilot | `delivery = 0`; in no pathway |
| no row or `delivery = 0` | publish at stage 8 | `delivery = 1`; in the pathway of each mapped competency |
| in pathways | republish drops a competency | leaves that pathway (map row removed by 004) |
| in pathways | republish changes `target_outcome_level` | moves to the new level row |
| in pathways | site team hides the course | out of every pathway at once; completions kept |
| hidden | site team shows it again | back in its pathways |
| in pathways | course removed from the repo | stays until hidden (plan, "Retiring a course") |

No transition goes from `delivery = 1` back to `0` in normal use, since stage 8 does not regress;
if `course_stage.py` reports a lower stage, the next publish writes `0` and the course leaves
every pathway.

### A role pathway

| From | Event | To |
|---|---|---|
| absent | role declared, `apply` | row created, competencies written |
| active | declaration changed, `apply` | name, description, order and competencies set to match |
| active | role removed from `pathways.yaml`, `apply` | `retired = 1`; hidden; its cohort rows kept |
| retired | role declared again with the same key, `apply` | `retired = 0`; its cohort rows show again |

### A pathway assignment

| From | Event | Actor | To |
|---|---|---|---|
| none | assign | site team (`moodle/cohort:assign`, system) or a manager within their organisation's cohorts | row, `enrol = 0`; fires `pathway_assigned` |
| none or `enrol = 0` | assign with enrol | spec 008 only | row, `enrol = 1`; fires `pathway_assigned`. `enrol` is never lowered. |
| row | unassign | same actors | row deleted; fires `pathway_unassigned`; no one is unenrolled |
| row | cohort deleted | 006's `cohort_deleted` observer, through `unassign()` | row deleted; fires `pathway_unassigned` |

A manager may assign to `ltct:org:<key>` and to cohorts whose idnumber starts `ltct:org:<key>:`,
except `ltct:org:<key>:managers`, for each key in `local_ltuse_managed_organisation_keys()`.

## Visibility (R8)

`pathway\viewer::may_view(viewerid, learnerid, facts)`, pure, fails closed on a missing fact.
True when any holds: the viewer is the learner; the viewer holds
`local/ltuse:viewmenteeprogress` in the learner's user context (003); 002's
`organisation\access::is_org_member_of_manager()` holds; the viewer has `moodle/site:config`.
Names are shown through `fullname()` only.

## Events

All `\local_ltuse\event\*`, built on `\core\event\base` (R11). Shapes are pinned in
[contracts/pathway-api.md](contracts/pathway-api.md).

| Event | Fired by | `objecttable` | `other` |
|---|---|---|---|
| `pathway_courses_changed` | `set_course_pathway`, when a course's membership or level changes | `local_ltuse_course_pathway` | `courseid`, the pathway keys it joined and left |
| `pathway_assigned` | `assignments::assign()` | `local_ltuse_pathway_cohort` | `pathwaykey`, `cohortid`, `enrol` |
| `pathway_unassigned` | `assignments::unassign()` | `local_ltuse_pathway_cohort` | `pathwaykey`, `cohortid` |

## Requirement traceability

| Requirement | Where it lives in this model |
|---|---|
| FR-001, FR-002 | Pathway membership; no stored pathway |
| FR-003 | Level rows in id order, labels from `pathwaylevel<n>` |
| FR-004 | `nocourseyet` and `competencyurl` from the competency `url` |
| FR-005 | `delivery = 1` and `visible = 1` in membership |
| FR-006 | Every table written only by the publisher or `apply` |
| FR-007 | `pathways.yaml`, `local_ltuse_role_pathway`, `_comp` |
| FR-008 | Course entries always link; no lock field exists |
| FR-010 | Course status from `course_completions` |
| FR-011 | No learner-level field; "Aims at"; `done` names no level |
| FR-012 | `viewer::may_view()` |
| FR-013 | `local_ltuse_pathway_cohort`; cohort membership resolves new members |
| FR-014 | Membership reads 004's `course_comp` |
