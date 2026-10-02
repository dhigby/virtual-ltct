# Contract: Completion through the publisher

Changes to the payload, to `local_ltuse`'s publish functions, and to the publisher's call sequence. The payload stays platform-neutral (Principle II). Only `local_ltuse` knows Moodle's completion fields.

## Payload (`moodle_payload.py`)

Every module gains `completion`, and the manifest gains three fields:

```json
{
  "completion": "all",
  "competencies": ["Paratext", "Translation Tools"],
  "target_outcome_level": "2 - With Assistance",
  "sections": [{"modules": [{"kind": "page", "idnumber": "ltct:bloom:01", "completion": "view"}]}],
  "quizzes": [{"idnumber": "ltct:bloom:05", "threshold_pct": 80, "completion": "pass"}]
}
```

The values and their conditions are in [data-model.md](../data-model.md) "Completion rule". `check_moodle_payload.py` refuses a module without a value, and a manifest competency that is not in `competencies.yaml`. `gen_coverage.py` already enforces the second in CI.

## `local_ltuse_create_page` and `local_ltuse_create_quiz`

Each gains one optional parameter:

| Parameter | Type | Default | Meaning |
|---|---|---|---|
| `completion` | `PARAM_ALPHA` | `''` | `view`, `submit` or `pass`. Empty leaves completion untouched, which is what an older publisher sends. |

Each also gains one return field:

| Field | Type | Values |
|---|---|---|
| `completion` | `PARAM_ALPHA` | `set`, `unchanged`, `differs`, or `''` if the parameter was empty |

Behaviour is in [data-model.md](../data-model.md) "Completion state per module". `create_quiz` refuses `pass` when the computed `gradepass` is `0`, so a quiz can never claim a pass rule it cannot enforce.

## `local_ltuse_set_course_completion` (new)

| | |
|---|---|
| Type | write |
| Capability | `local/ltuse:publish` in the course context, and `moodle/course:update` |
| Parameters | `courseidnumber` (`PARAM_RAW`) |
| Returns | `added` (int[] cmids), `removed` (int[] cmids), `aggregation` (`unchanged` or `set`), `reaggregated` (int, a count of incomplete rows flagged, never user ids), `othercriteria` (bool) |

What it does:
1. It requires `enablecompletion` on the course and on the site, and fails with `error:completionoff` otherwise.
2. It computes the wanted and present sets and the diff, as in [data-model.md](../data-model.md) "Course criteria diff".
3. It inserts each added criterion as a `completion_criteria_activity` data object, and deletes each removed one through the same object.
4. It sets both aggregations to ALL.
5. If `remove` is non-empty, it flags incomplete rows for re-aggregation.
6. If anything changed, it fires `\core\event\course_completion_updated`.

It never calls `completion_info::clear_criteria()` or `delete_course_completion_data()`. That is the whole point of the function (R3), and a test in `criteria_harness.php` greps the class source to keep it true.

Idempotent: a second call with no module change returns empty `added` and `removed`, and `aggregation: unchanged`.

## `local_ltuse_set_course_competencies` (new)

```
local_ltuse_set_course_competencies(courseid: int, competencies: [string]) ->
  {added: [string], removed: [string], competencies: [string]}
```

- **Capability**: `local/ltuse:publish` in the course context.
- **Refuses** a course whose `idnumber` does not start with `ltct:`.
- **Transaction**: the call runs in one delegated transaction. It resolves every name against non-retired `local_ltuse_competency` rows. One unknown name fails the whole call with `invalid_parameter_exception` ("competency '<name>' is not on this site: run site_config.py apply"), and the map is unchanged.
- **Write**: it replaces the course's `local_ltuse_course_comp` rows with the declared set.
- **Return**: `competencies` is the set read back from the table after the write.
- **Idempotent**: a second call with the same list returns empty `added` and `removed`.
- **User data**: none is read or written.

It is registered in `db/services.php` and added to the publisher's service.

## Publisher sequence (`publish_moodle.py`)

1. `ensure_course`: the create and update both send `enablecompletion: 1` and `customfields`:
   - `ltct_competencies`: `"[A] [B]"`;
   - `ltct_target_level`: the level, or `""` if absent.
2. **New**: the publisher drops any name in the `Meta` category of `competencies.yaml`, printing `competencies  skipped Meta: <names>` if there are any. It then calls `local_ltuse_set_course_competencies` (R15) and prints `competencies  N added, M removed`. If the returned set differs from the one sent, it prints both sets and exits 1 after the summary.
3. Sections, then pages and quizzes, as now, each with its `completion` from the payload.
4. Pass 2 links, as now.
5. Hide what the course no longer has, as now.
6. **New**: `local_ltuse_set_course_completion`, after hiding, so a hidden module is never left as a criterion.
7. Print `completion  N added, M removed` and any `differs` modules. If any module `differs`, or the competency read-back differed, exit 1 after the summary. The publish itself has completed, and the exit code tells the author a person must decide.

Under `--dry-run`, every call is listed and none is sent, as now.
