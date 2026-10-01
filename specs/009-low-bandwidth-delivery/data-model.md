# Data model: Low-bandwidth and offline delivery

Everything here is a file on disk or a field in the payload's `manifest.json`. No new state
lives in the repo, and the only new state in Moodle is what Moodle already stores: a file's
`contenthash` in its file area.

## Committed image (source of truth, unchanged by this spec)

A file under `modules/<slug>/assets/`, committed and never altered by publishing (FR-002).

| Field | Rule |
|---|---|
| path | `modules/<slug>/assets/<stem>[.<suffix>].<ext>` |
| `<stem>` | `ss-<lesson>-<what-it-shows>`, lowercase and hyphens (CLAUDE.md rule 6). Contains no `.` |
| `<suffix>` | optional, exactly one of `full`, `small` ([delivery suffix](#delivery-suffix)) |
| `<ext>` | `png` or `svg` by the package rules; `jpg`/`jpeg` tolerated; anything else passes through unchanged |

## Delivery suffix

The author's per-image override (FR-007, clarification 2026-10-01). It is part of the file
name, so it travels with the file through renames, copies and review. No list has to be kept
in step with it.

| Suffix | Treatment | Profile |
|---|---|---|
| none | `standard` | fit to 1280 px wide, 256-colour PNG |
| `.full` | `full` | committed bytes, unchanged |
| `.small` | `small` | fit to 800 px wide, 64-colour PNG |

Validation (`check_course_package.py`, pipeline courses only). For a file under `assets/`
whose stem contains a `.`, the part after the last `.` must be `full` or `small`, and the
stem before it must contain no further `.`. So `x.ful.png` and `x.full.small.png` are
errors. The payload builder treats an unrecognised suffix as `standard` and adds a note, so
a legacy course that was never package-checked still publishes.

The suffix is read only for files under `assets/`. A legacy folder such as
`supporting-pre-publishing-checks/images/` predates the convention and has names like
`L2-1.Blank-BT-Row.png`; its dots mean nothing, so those files get `standard` and no note
(found while implementing).

## Reduction profile

A named set of parameters, defined once as a constant in `scripts/image_reduce.py`.

| Field | `standard` | `small` |
|---|---|---|
| `max_width` (px) | 1280 | 800 |
| `colours` | 256 | 64 |
| resample | Lanczos | Lanczos |
| quantiser | median cut (opaque) / fast octree (alpha) | same |
| dither | none (research.md R1, "Dithering") | same |
| output | PNG, `optimize=True`, no text or time chunks | same |

Height scales with width. An image is never enlarged. Changing a value changes every
delivered image, so it is a reviewed change, and research.md R1 records the evidence behind
the current values.

## Delivered image

The lighter copy carried in the payload at `<out>/<slug>/assets/<name>`. It is never stored
in the repo working tree (FR-002, enforced by the [output guard](#output-location-guard)).

| Field | Type | Rule |
|---|---|---|
| `name` | str | **identical to the committed file name**, suffix included, so every existing `@@PLUGINFILE@@/<name>` link, alt text and disclosure rule applies unchanged (FR-011) |
| bytes | bytes | deterministic for a given source and Pillow version (FR-006) |

Treatment decision, in order:

1. `.svg`, or any extension that is not raster → `vector` / `passthrough`: committed bytes (FR-004).
2. `.full` → `full`: committed bytes.
3. Otherwise reduce with the profile. If the result is not strictly smaller than the
   committed file → `unchanged`: committed bytes (FR-003).
4. If the image cannot be decoded → **the build stops**, naming the file (edge case). Nothing
   is written.

## Asset record (new `manifest.json` key `assets`)

One entry per delivered image, keyed by `name`. This is what makes every delivered file
traceable to its source (FR-008) and gives the weight report its numbers (FR-010).

```json
"assets": {
  "ss-01-find-mode-preview.png": {
    "source": "assets/ss-01-find-mode-preview.png",
    "source_sha256": "…",
    "source_bytes": 423903,
    "sha256": "…",
    "sha1": "…",
    "bytes": 138079,
    "treatment": "standard"
  }
}
```

| Field | Rule |
|---|---|
| `source` | path relative to the course folder; must exist and must not be an excluded asset |
| `source_sha256` | of the committed file at build time; the gate re-hashes the file and compares |
| `sha256` | of the delivered bytes; the gate re-hashes `assets/<name>` and compares |
| `sha1` | of the delivered bytes; equals Moodle's `contenthash`, used to skip unchanged uploads (SC-007) |
| `bytes` ≤ `source_bytes` | always (FR-003); the gate enforces it |
| `treatment` | `standard` · `small` · `full` · `unchanged` · `vector` · `passthrough` |

For `full`, `unchanged`, `vector` and `passthrough`, `sha256 == source_sha256`.

## Weight report (new `manifest.json` key `image_weight`)

```json
"image_weight": {"source_bytes": 4005888, "delivered_bytes": 1316864,
                 "by_treatment": {"standard": 24, "full": 1, "unchanged": 1}}
```

Printed per course by `moodle_payload.py` and `publish_moodle.py` (FR-010):

```text
  images    26: 3912 KB -> 1286 KB (67% lighter)
            full       ss-03-dense-dialog.full.png
            unchanged  ss-04-zero-results.png (already smaller than any reduction)
```

## Server file record (extension to `local_ltuse_get_course_manifest`)

Per page module, the files in its `mod_page/content` area, read through Moodle's file API
(`get_file_storage()->get_area_files()`):

| Field | Source |
|---|---|
| `filename` | `stored_file::get_filename()` |
| `contenthash` | `stored_file::get_contenthash()` (SHA-1 of the content) |
| `filesize` | `stored_file::get_filesize()` |

The publisher compares the page's `{name: sha1}` set from the payload with this set, and
splits it three ways:

| Set | What is sent |
|---|---|
| unchanged (same name, same hash) | nothing; named in `keepfiles` |
| new or changed | uploaded to the draft area |
| on the server but no longer in the payload | nothing; the draft omits it, so the save removes it |

If nothing is new, changed or removed, `syncfiles=false`, `contentitemid=0`, and the file
area is not touched. Otherwise `syncfiles=true`: the plugin copies the `keepfiles` into the
draft (taking an empty one itself if nothing was uploaded), keeping their `timemodified`,
before saving. Only changed files get a new time, and removed ones are deleted (FR-016,
research.md R2).

## Page publish outcome

What `local_ltuse_create_page` reports, and the publisher prints, per page:

| Outcome | When | Effect in Moodle |
|---|---|---|
| `created` | no module with this idnumber | new page, revision 1 |
| `updated` | name, section, visibility, content or files differ | `update_moduleinfo()`; revision +1; only changed files get a new time |
| `unchanged` | all equal, and no files to change | nothing written; revision, times and image URLs unchanged |

## Output location guard

| Input | Rule |
|---|---|
| `moodle_payload.py --out <dir>` | refused if `<dir>` resolves inside the repository root |
| `publish_moodle.py --keep-payload <dir>` | same |
| default (temp dir) | unchanged |

The check is `Path(dir).resolve()` against `REPO.resolve()`, made before anything is
written, and it exits non-zero with a message naming the safe alternative (clarification
2026-10-01).

## Site configuration additions

`moodle/site/settings/caching.yaml` (row #5) and quiz defaults: see research.md R3–R5 and
[contracts/site-settings.md](contracts/site-settings.md).
