# Contract: section summaries (estimated time)

**Amends**: the `local_ltuse_update_sections` web service
(`moodle/local_ltuse/classes/external/update_sections.php`) and the publisher's manifest.

## Manifest (`moodle_payload.py`, platform-neutral)

Each entry of `manifest["sections"]` gains:

| Key | Type | Value |
|---|---|---|
| `time_text` | string | The header line alone: the whole source line holding the `TIME_RE` match, trailing text included, rendered with `moodle_payload.render()` and not wrapped. `""` when the file has none. |

It is not "exactly as the lesson renders it". In
`coretech-computer-hardware/01..04-module-N.md` the header has no blank line after it, so the
lesson renders it in one `<p>` with Target Audience and Format. `time_text` renders the line
on its own.

Quiz files carry no `**Estimated time:**` line, so a quiz section's `time_text` is `""` and
its summary is cleared.

`minutes` (existing) is unchanged. `time_text` carries only the header line, never the
rest of the lesson, so it adds nothing to the disclosure boundary. `check_moodle_payload.py`
asserts that each `time_text` either is empty or matches the header pattern, rendered. It
uses its own rendered-HTML pattern for this, because `check_moodle_payload.py` imports
neither `moodle_payload` nor `markdown`.

## Web service

`sections[]` items gain an optional key:

| Key | Type | Default | Meaning |
|---|---|---|---|
| `summary` | `external_value(PARAM_RAW, …, VALUE_OPTIONAL)` | absent | Section summary HTML, `FORMAT_HTML`. Absent: the summary is not touched (callers that predate this keep working). `""`: the summary is cleared. |

- Presence is tested with `array_key_exists('summary', $s)`, not `isset` or `empty`, so `""`
  clears.
- It is written with `course_update_section($course, $section, ['summary' => …, 'summaryformat' => FORMAT_HTML])`
  (to confirm in `MOODLE_502_STABLE`, R13).
- Each write is skipped when the stored value is byte-identical, which keeps 009's "an
  unchanged republish writes nothing". This now covers the **name** as well as the summary:
  today every name is rewritten on every publish. Stored values are compared as `(string)`,
  so a NULL equals `''`.
- An empty `name` skips only the name write, never the summary.
- Return value: `renamed` counts only names actually written; a new `summaries` count, beside
  it, counts summaries actually written.

## Publisher

`ensure_sections()` sends `summary: time_text` for every section, and prints
`summaries N` beside `sections N`. `--dry-run` shows the summaries it would send.

## Version and deploy order

local_ltuse's `version.php` is bumped once for this change together with the hook in
[learner-ui.md](learner-ui.md). `site.yaml`'s pin follows it.

A server still on local_ltuse `2026100900` rejects the unknown `summary` key in
`validate_parameters` (`invalidparameter`). By then the publisher's course, placement,
competencies and pathway calls have already written, so the publish stops half done. The
plugin is therefore deployed and its pin applied **before** the first publish with the new
publisher.

That includes `.github/workflows/moodle-publish.yml`. It publishes on a push to `main`
touching `scripts/publish_moodle.py` or `scripts/moodle_payload.py` when the repository
variable `MOODLE_ENABLED` is `true`. With it on, local_ltuse is deployed before the merge.
