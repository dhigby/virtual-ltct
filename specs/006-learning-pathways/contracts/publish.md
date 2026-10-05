# Contract: A course's place in the pathways, through the publisher

One new web service and one new publisher step. The payload gains nothing: the two facts a
pathway needs from a course, whether it is delivered and the level it aims at, are already in
the manifest (Principle II). Only `local_ltuse` knows how they become pathways.

## Payload (`moodle_payload.py`): no change

The step reads two manifest fields that exist today:

| Manifest field | Set by | Used as |
|---|---|---|
| `recognition.delivery` | `course_stage.py` (stage 8), as spec 013 uses it | `delivery` |
| `target_outcome_level` | the course `README.md` frontmatter, e.g. `"2 - With Assistance"` | `targetlevel`: its leading digit, `1`–`4`; `0` when the field is absent |

`check_moodle_payload.py` gains one rule: a `target_outcome_level` that is present must be one
of the `course_target_levels` labels in `outcome-levels.yaml`, verbatim. A label it cannot read
a digit from never reaches the web service.

## `local_ltuse_set_course_pathway` (new)

```
local_ltuse_set_course_pathway(courseid: int, delivery: int, targetlevel: int) ->
  {delivery: int, targetlevel: int, visible: int,
   pathways: [{key: string, competency: string}],
   added: [string], removed: [string]}
```

| | |
|---|---|
| Type | write |
| Capability | `local/ltuse:publish` in the course context |
| `courseid` | `PARAM_INT` |
| `delivery` | `PARAM_INT`, `0` or `1`. `1` only for a course `course_stage.py` puts at stage 8. |
| `targetlevel` | `PARAM_INT`, `0`–`4`. `0` means the course declares no `target_outcome_level`. |
| Refuses | a course whose `idnumber` does not start `ltct:` (`error:notltctcourse`, as 004); an idnumber with a second `:` (`ltct:<slug>:03` names a module, not a course); `delivery` outside `0`–`1`; `targetlevel` outside `0`–`4`, or `0` with `delivery = 1`, with `invalid_parameter_exception` |

What it does:

1. Upserts the course's one row of `local_ltuse_course_pathway` (`courseid` unique), setting
   `delivery`, `targetlevel` and `timemodified`.
2. Computes the pathways the course is now in, through `\local_ltuse\pathway\catalogue`. A course
   is in `competency:<slug>` when its idnumber is `ltct:<slug>` with no second `:`, it is visible,
   `delivery = 1`, `targetlevel` is `1`–`4`, and its `local_ltuse_course_comp` row names that
   competency, which is not retired. It is in `role:<key>` when it is in a competency pathway of
   that live role (R2, R6).
3. Compares that set with the row's `pathwaykeys`, the set it last announced, and stores the new
   set there.
4. For each pathway key the course joined or left, fires one
   `\local_ltuse\event\pathway_courses_changed` ([pathway-api.md](pathway-api.md)).

The row and `pathwaykeys` are written in one delegated transaction; the events fire after the
commit.

Returns, read back from the tables after the commit:

| Field | Type | Meaning |
|---|---|---|
| `delivery`, `targetlevel` | int | as stored |
| `visible` | int | the course's `visible`. A course the publisher has just created is hidden until a person shows it, and a hidden course is in no pathway. |
| `pathways` | `[{key, competency}]` | each competency pathway the course is in now, with its competency name; role keys are not listed here |
| `added`, `removed` | `[string]` | pathway keys, competency and role, joined or left on this call |

Idempotent: a second call with the same values and no change to the course's competencies or
visibility returns empty `added` and `removed`, and fires nothing.

Reads and writes no user data. It never enrols, unenrols or assigns anyone (R9).

Registered in `db/services.php` and added to the publisher's service, beside
`local_ltuse_set_course_competencies`.

## Publisher sequence (`publish_moodle.py`)

Spec 004's sequence, with one step added straight after the competency map, so a pathway is
never computed from a stale map:

1. `ensure_course`, as now.
2. `ensure_competencies`: `local_ltuse_set_course_competencies`, as now.
3. **New**: `ensure_pathway` calls `local_ltuse_set_course_pathway` with `courseid`,
   `delivery` (`recognition.delivery` as `0` or `1`) and `targetlevel`, and prints one line:

   | Case | Line |
   |---|---|
   | pilot (`delivery` 0) | `  pathways  pilot: in no pathway until stage 8` |
   | delivered, hidden | `  pathways  hidden: joins N pathways when the course is shown` |
   | delivered, visible | `  pathways  in N; joined: <keys>; left: <keys>` (`joined`/`left` only when non-empty) |
   | `--dry-run` | `  pathways  dry-run: delivery D, target level T` |

4. Sections, pages, quizzes, links, hiding, completion and recognition, as now.

`ensure_pathway` adds to `problems`, so the publish completes and then exits 1, when:

- `delivery` is 1 and `targetlevel` is 0: `delivered but declares no target_outcome_level, so it
  is in no pathway while COVERAGE.md lists it` (FR-014);
- `delivery` is 1, the course is visible, and the competency names in `pathways` differ from the
  set `ensure_competencies` sent (Meta already dropped). Both sets are printed, as 004 prints a
  map mismatch (FR-014, SC-001).

A refusal from the web service is a `MoodleError` and stops the publish, as any other.

## What the publisher does not do

- It sends no pathway, course order or level label. Those are computed by the plugin from the
  map, this row and `site_config.py apply` (R1).
- It does not show, hide or retire a course. To take a course out of every pathway, the site team
  hides it in Moodle; its learners keep their completion records. The next publish of a hidden
  course reports `hidden` and fires `removed` for any pathway it was still announced in.
- It never reads a pathway assignment or anyone's progress.
