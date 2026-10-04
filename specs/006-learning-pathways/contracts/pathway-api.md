# Contract: The pathway API and events, for spec 008

The seam between 006 (what is on a pathway, and who has it) and 008 (enrolment). Agreed with the
008 session on 2026-10-04 (research R9). 008 codes against exactly what is here. Anything not
listed is internal to 006 and may change.

**The split.** 006 decides which courses are in a pathway and which cohorts have a pathway. It
enrols nobody. 008 owns the one enrolment mechanism: it reads this API, sets the `enrol` flag
through it, and observes these events. Neither unassigning a pathway nor a course leaving one
unenrols anyone, in either spec.

## Pathway keys

| Form | Example | Names |
|---|---|---|
| `competency:<slug>` | `competency:keyboards` | the competency whose descriptor `slug` is `<slug>` |
| `role:<key>` | `role:translation-support` | the role declared in `moodle/site/pathways.yaml` with that `key` |

At most 100 characters. A key is the only identifier 008 stores or passes; no database id of a
pathway exists. A rebuilt server regains every key from `site_config.py apply` (R3).

Constants on `\local_ltuse\pathway\catalogue`:

```php
const COMPETENCY_PREFIX = 'competency:';
const ROLE_PREFIX = 'role:';
const KEY_PATTERN = '/^(competency:[a-z0-9][a-z0-9-]*|role:[a-z][a-z0-9-]*)$/';
const KEY_MAX_LENGTH = 100;
```

## `\local_ltuse\pathway\catalogue`: what is on a pathway

Read only. No user data.

| Method | Returns |
|---|---|
| `static exists(string $key): bool` | true for a competency pathway with at least one course, and for a live (not retired) role |
| `static is_assignable(string $key): bool` | `exists()`, or a competency key whose competency is live but has no course yet. A retired role or an unknown key is false. |
| `static courses(string $key): int[]` | course ids on the pathway now, ordered by target level, then course full name. A role key gives the union over its competencies, each course once. A retired role, an unknown key or a hidden course gives nothing. |
| `static pathways_for_course(int $courseid): string[]` | every key, competency then role, the course is on now |
| `static all(): string[]` | every key `exists()` is true for: competency keys in framework order, then role keys in declared order |

A course is on a competency pathway under the rule in [publish.md](publish.md), step 2. That rule
lives in `catalogue` and nowhere else.

## `\local_ltuse\pathway\assignments`: who has a pathway

Rows of `local_ltuse_pathway_cohort` (`pathwaykey`, `cohortid`, `enrol`, `usermodified`,
`timecreated`, `timemodified`; unique `pathwaykey` + `cohortid`).

| Method | Behaviour |
|---|---|
| `static assign(string $key, int $cohortid, bool $enrol = false): int` | Links the pathway to the cohort and returns the row id. Idempotent. Refuses a key `catalogue::is_assignable()` rejects, and a cohort that does not exist, with `invalid_parameter_exception`. New row: `enrol` is `(int)$enrol`. Existing row: `true` raises `enrol` to 1; `false` leaves it as it is, so `enrol` is never lowered and a manager re-assigning in 006's page never clears 008's flag. Fires `pathway_assigned` when a row is created or its `enrol` changes. Checks no capability: the caller does. |
| `static unassign(string $key, int $cohortid): bool` | Deletes the row if present and fires `pathway_unassigned`, carrying the row's last `enrol`. The only way a row is removed. Unenrols nobody. Returns true when a row was deleted. Checks no capability. |
| `static for_cohort(int $cohortid): array` | `[{pathwaykey, enrol}]` for the cohort, by key |
| `static cohorts_for(string $key, ?bool $enrol = null): int[]` | cohort ids the key is assigned to, by id; `true` gives only rows with `enrol = 1`, `false` only `enrol = 0`, `null` all |
| `static pathways_for_user(int $userid): string[]` | every key assigned to any cohort the user belongs to, each once (spec Edge Cases), in `catalogue::all()` order; a key that no longer `exists()` is left out, its rows kept |
| `static may_assign(int $userid, int $cohortid): bool` | the rule below |

**006 never passes `$enrol = true`.** The names and signatures above were locked with the 008 session on 2026-10-04. The manage page calls `assign($key, $cohortid)` and
`unassign()`. Only 008 sets `enrol`.

### `may_assign()`

True when either:

- the user has `moodle/cohort:assign` in the system context: any cohort; or
- for some key `K` in `local_ltuse_managed_organisation_keys($userid)`, the cohort's idnumber is
  `ltct:org:K`, or starts `ltct:org:K:` and is not `ltct:org:K:managers`.

Anything else, including a cohort with no idnumber, is false. 008 may call it to apply the same
scope.

## Events

All three are `\core\event\base` subclasses in `local_ltuse`, with `edulevel` `LEVEL_OTHER`.
Each carries keys and ids in `other`; none carries a name or a count of learners.

| Event | `crud` | `objecttable`, `objectid` | `contextid` | `userid` | `other` |
|---|---|---|---|---|---|
| `\local_ltuse\event\pathway_courses_changed` | `u` | none, none | system | the web service or `apply` caller | `{pathwaykey: string, added: int[], removed: int[]}` course ids |
| `\local_ltuse\event\pathway_assigned` | `c` | `local_ltuse_pathway_cohort`, row id | the cohort's context | who assigned | `{pathwaykey: string, cohortid: int, enrol: int}` |
| `\local_ltuse\event\pathway_unassigned` | `d` | `local_ltuse_pathway_cohort`, row id | the cohort's context | who unassigned | `{pathwaykey: string, cohortid: int, enrol: int}` the row's last value |

`validate_data()` requires every `other` key listed.

When `pathway_courses_changed` fires:

- from `local_ltuse_set_course_pathway`, once per key the published course joined or left, each
  with that one course id in `added` or `removed` ([publish.md](publish.md));
- from `site_config.py apply`, once per role whose competency set changed, or that was retired or
  declared again, with every course that joined or left that role ([declaration.md](declaration.md)).

It does **not** fire when the site team hides or shows a course in Moodle, or retires a
competency. The publish keeps the last announced set per course, so the course's next publish
fires the difference. An observer that must act at once on visibility observes core's
`\core\event\course_updated` as well.

## What 008 can rely on

1. `catalogue::courses($key)` is the whole membership of a pathway at the moment of the call.
2. Every change to it through a publish or an apply is announced by `pathway_courses_changed`,
   after the change is committed.
3. `enrol` is never set to 1 by 006, and is never cleared by a 006 re-assign.
4. Every deleted assignment fires `pathway_unassigned`: 006 deletes rows only through
   `unassign()`. A deleted cohort's rows are removed by 006's `\core\event\cohort_deleted`
   observer through `unassign()`, so that also fires the event.
5. A retired role keeps its rows; `courses()` returns nothing for it and `pathways_for_user()`
   leaves it out.

## Privacy

`local_ltuse_pathway_cohort.usermodified` is personal data, declared by the privacy provider as
the user who made the link. Export lists the links the user made; deletion sets `usermodified` to
0 and keeps the link, since the link belongs to the cohort, not to the user.
