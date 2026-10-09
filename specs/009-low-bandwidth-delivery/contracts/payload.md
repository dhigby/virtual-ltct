# Contract: the publish payload and its commands

The payload is the platform boundary (INTENT portability; scripts/moodle_payload.py). This
contract covers only what this spec adds. Every existing key and file is unchanged.

## Layout (unchanged)

```text
<out>/<slug>/manifest.json
<out>/<slug>/pages/<name>.html
<out>/<slug>/assets/<name>        <- now the DELIVERED (reduced) bytes, same name as committed
```

## `manifest.json` additions

| Key | Type | Meaning |
|---|---|---|
| `assets` | object, keyed by file name | one [asset record](../data-model.md#asset-record-new-manifestjson-key-assets) per file in `assets/` |
| `image_weight` | object | `source_bytes`, `delivered_bytes`, `by_treatment` ([weight report](../data-model.md#weight-report-new-manifestjson-key-image_weight)) |

Every file in `assets/` has exactly one record, and every record has its file. A different
delivery target reads the same keys. Nothing in them is Moodle-specific, apart from `sha1`
being useful to Moodle (FR-005).

## `scripts/image_reduce.py` (new, platform-neutral)

```text
deliver(path: Path) -> Delivered(bytes, treatment, note)
treatment_for(name: str) -> "standard" | "small" | "full" | None   # None = unrecognised suffix
PROFILES = {"standard": {...}, "small": {...}}
```

- Pure function: no I/O except reading `path`.
- Raises `ImageError(path, reason)` on an undecodable raster file.
- Imports Pillow lazily, so modules that only need `treatment_for` (the package check) do
  not need Pillow installed.

## `scripts/moodle_payload.py`

| Behaviour | Contract |
|---|---|
| assets | every used asset passes through `image_reduce.deliver()`; the record goes into `manifest["assets"]` |
| corrupt image | exit 1, `cannot read image <course>/<source>: <reason>`, nothing written |
| `--out` inside the repo | exit 2, `refusing to write the payload inside the repository (<path>); choose a folder outside it, or omit --keep-payload to use a temp folder`, nothing written |
| unrecognised suffix | a note: `<name>: unrecognised delivery suffix '.<x>' -- delivered with the standard reduction` |
| report | the `images` lines from the weight report, after `assets` |
| page weight | a note for any page whose HTML plus delivered images exceed 1 MB (SC-002) |

## `scripts/check_moodle_payload.py`: new check 5 (fail-closed)

**Asset provenance.** For every record and every file in `assets/`:

1. The file and the record both exist; nothing is unrecorded and nothing is missing.
2. `source` exists in the course folder, and neither its name nor the delivered name is an
   excluded asset (`disclosure.excluded_asset`).
3. The re-hashed committed file equals `source_sha256`; the source has not moved under us.
4. The re-hashed delivered file equals `sha256`, and its size equals `bytes`.
5. `bytes <= source_bytes`.
6. For `full`, `unchanged`, `vector` and `passthrough`, the delivered bytes equal the
   committed bytes.

Any failure is a `LEAK`-class problem, and nothing is published. This is what keeps FR-008
and FR-009 true now that the delivered bytes are no longer a copy of the committed file.

## `scripts/publish_moodle.py`

| Behaviour | Contract |
|---|---|
| `--keep-payload` inside the repo | refused, as for `--out` |
| per page | prints `created`, `updated` or `unchanged`, and `n sent, m kept` for files |
| summary | `pages: a created, b updated, c unchanged; images: x sent (KB), y kept` |
| unchanged republish | `0 created, 0 updated`, `0 sent` (SC-007) |
| `--dry-run` | unchanged: sends nothing, and makes no read calls either |

## `scripts/check_course_package.py`: new check

**Delivery suffix.** For each file under `modules/<slug>/assets/` in a pipeline course, a stem
containing `.` must end in `.full` or `.small`, with no other `.` in it. Error text:
`assets/<name>: unrecognised delivery suffix -- use <stem>.full.<ext> (send the original) or <stem>.small.<ext> (smaller), or no suffix`.
