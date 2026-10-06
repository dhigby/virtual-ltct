# Contract: section summaries (estimated time)

**Amends**: the `local_ltuse_update_sections` web service
(`moodle/local_ltuse/classes/external/update_sections.php`) and the publisher's manifest.

## Manifest (`moodle_payload.py`, platform-neutral)

Each entry of `manifest["sections"]` gains:

| Key | Type | Value |
|---|---|---|
| `time_text` | string | The lesson's `**Estimated time:** N minutes` line, rendered to HTML exactly as the lesson renders it. `""` when the lesson has none. |

`minutes` (existing) is unchanged. `time_text` carries only the header line, never the
rest of the lesson, so it adds nothing to the disclosure boundary. `check_moodle_payload.py`
asserts that each `time_text` either is empty or matches the header pattern
`moodle_payload.TIME_RE` uses, rendered.

## Web service

`sections[]` items gain an optional key:

| Key | Type | Default | Meaning |
|---|---|---|---|
| `summary` | `PARAM_RAW` | absent | Section summary HTML, `FORMAT_HTML`. Absent: the summary is not touched (callers that predate this keep working). `""`: the summary is cleared. |

- It is written with `course_update_section($course, $section, ['summary' => …, 'summaryformat' => FORMAT_HTML])`
  (to confirm in `MOODLE_502_STABLE`, R13).
- The write is skipped when the stored summary is byte-identical, which keeps 009's
  "an unchanged republish writes nothing".
- Return value: a new `summaries` count of summaries actually written, beside `renamed`.

## Publisher

`ensure_sections()` sends `summary: time_text` for every section, and prints
`summaries N` beside `sections N`. `--dry-run` shows the summaries it would send.

## Version

local_ltuse's `version.php` is bumped once for this change together with the hook in
[learner-ui.md](learner-ui.md). `site.yaml`'s pin follows it.
