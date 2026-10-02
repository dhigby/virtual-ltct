---

description: "Task list for 009 low-bandwidth and offline delivery"
---

# Tasks: Low-bandwidth and offline delivery

**Input**: Design documents from `/specs/009-low-bandwidth-delivery/`

**Prerequisites**: [plan.md](plan.md), [spec.md](spec.md), [research.md](research.md),
[data-model.md](data-model.md), [contracts/](contracts/), [quickstart.md](quickstart.md)

**Tests**: Included. The plan names `tests/test_image_reduce.py`, `tests/test_payload_assets.py`
and a new `publisher-tests.yml` workflow, in the existing `tests/test_site_config.py` style
(unittest classes, synthetic fixtures generated in a temp dir, `REPO`/`sys.path` header).
No test fixture may be a committed binary (SC-005): every image is generated with Pillow at
test time.

**Organization**: One phase per user story. US1 (lighter images) is the MVP. US2 (offline)
and US3 (caching and idempotent republish) both touch `moodle/local_ltuse/`, so the plugin
version bump is done once, in the final phase, after whichever of them lands.

**Live checks**: the development checkout has no server or Android device. Tasks marked
**(live, post-merge)** run quickstart V1–V8 on the temporary 5.2.3+ instance and a device;
they are what move REQUIREMENTS.md row #5 from **built** to **verified** (spec
clarification 2026-10-01). Everything else must pass before merge.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: US1, US2 or US3, from spec.md

---

## Phase 1: Setup

**Purpose**: The one new dependency and the CI job that will run the new tests.

- [X] T001 Add `pillow==12.3.0` to `publish-requirements.txt`, with a comment in the file's existing style: needed only where a payload is built; pinned exactly because a Pillow upgrade can change delivered bytes and so cost one re-upload of every image (research.md R1, FR-006). Do NOT add it to `docs-requirements.txt` — the package check and review-site build must not need it.
- [X] T002 [P] Create `.github/workflows/publisher-tests.yml`, modelled on `.github/workflows/site-config.yml`: on push/PR touching `scripts/image_reduce.py`, `scripts/moodle_*.py`, `scripts/check_moodle_payload.py`, `scripts/check_course_package.py`, `scripts/publish_moodle.py`, `scripts/disclosure.py`, `publish-requirements.txt`, `tests/test_image_reduce.py`, `tests/test_payload_assets.py`, `tests/test_publish_moodle.py` or the workflow itself; Python 3.12 on `ubuntu-latest`; `pip install -r publish-requirements.txt pytest`; run `python -m pytest -q tests/test_image_reduce.py tests/test_payload_assets.py tests/test_publish_moodle.py`.

---

## Phase 2: Foundational

**Purpose**: None required. US1, US2 and US3 touch disjoint code paths (payload/gate,
`create_quiz.php` + settings, `create_page.php`/`get_course_manifest.php` + publisher diff),
and the only shared file — `moodle/local_ltuse/version.php` — is handled in the final phase.

**Checkpoint**: Setup done → user stories can start, in parallel if staffed.

---

## Phase 3: User Story 1 — Lessons load quickly on a slow connection (Priority: P1) 🎯 MVP

**Goal**: The payload carries lighter copies of every raster a learner page uses, traceable
to its committed source and gated by a fail-closed provenance check; the repo is never
written to.

**Independent Test**: quickstart L1–L6. `moodle_payload.py --slug data-manipulation-skills
--out "$TEMP/ltct"` reports ≥50% lighter images and no page over 1 MB; the gate passes
clean and fails on each tampering case; `--out ./payload` exits 2; `git status --porcelain`
is empty afterwards.

### Tests for User Story 1

> Write these first and confirm they fail before implementing T006–T014.

- [X] T003 [P] [US1] Create `tests/test_image_reduce.py` (unittest classes; generate every fixture with Pillow into a `tempfile.TemporaryDirectory`). Cover: `treatment_for()` returns `"standard"` for `ss-01-x.png`, `"full"` for `ss-01-x.full.png`, `"small"` for `ss-01-x.small.png`, `None` for `ss-01-x.ful.png` and `ss-01-x.full.small.png`; `deliver()` on a noisy 1600×900 RGB PNG returns treatment `standard`, width 1280, bytes strictly smaller than the input; `.small` gives width 800; a 400 px image is never enlarged; two `deliver()` calls on the same file return byte-identical output (FR-006); a tiny flat PNG already smaller than any reduction returns treatment `unchanged` with the committed bytes (FR-003); `.full.png` returns the committed bytes with treatment `full`; a `.svg` returns committed bytes with treatment `vector` (FR-004); a `.txt`/other extension returns `passthrough`; an RGBA source keeps an alpha channel in the output; a file of random bytes named `.png` raises `ImageError` naming the path; output PNG carries no `tIME`/`tEXt` chunks.
- [X] T004 [P] [US1] Create `tests/test_payload_assets.py` (unittest; builds a throwaway course folder with a `00-design.md`, one lesson linking `assets/ss-01-a.png`, `assets/ss-01-b.full.png` and `assets/ss-01-c.svg`, and generated images). Cover: (a) `manifest["assets"]` has exactly one record per file in `<out>/<slug>/assets/` with keys `source`, `source_sha256`, `source_bytes`, `sha256`, `sha1`, `bytes`, `treatment`, and `bytes <= source_bytes`; (b) `manifest["image_weight"]` has `source_bytes`, `delivered_bytes`, `by_treatment`; (c) delivered file names equal committed names, so the page HTML's `@@PLUGINFILE@@/<name>` links and alt text are unchanged (FR-011); (d) `check_moodle_payload.check()` is clean, then fails with a LEAK-class problem when a delivered PNG is replaced, when one record is deleted from `manifest.json`, when an unrecorded file is added to `assets/`, when a record's `source` names an excluded asset (per `disclosure.excluded_asset`), and when a `full`/`vector` record's delivered bytes differ from the committed bytes; (e) `write_payload()` / `moodle_payload.py --out <repo>/payload` exits 2 with the contract message and creates nothing, including for a path written with `..` that resolves inside the repo; (f) a corrupt committed PNG makes the build exit 1 with `cannot read image <course>/<source>: <reason>` and writes nothing; (g) `check_course_package` on a pipeline course with `assets/x.ful.png` or `assets/x.full.small.png` reports the contract error, and accepts `x.full.png`, `x.small.png` and `x.png`; (h) an unrecognised suffix in the payload builder yields the note `<name>: unrecognised delivery suffix '.<x>' -- delivered with the standard reduction` and treatment `standard`; (i) after building, the course folder's file list and every file's bytes are unchanged (FR-002).

### Implementation for User Story 1

- [X] T005 [US1] Create `scripts/image_reduce.py` per contracts/payload.md and data-model.md: module docstring in the house style (what it is, that it is platform-neutral and knows nothing of Moodle, that Pillow is imported lazily); `PROFILES = {"standard": {"max_width": 1280, "colours": 256}, "small": {"max_width": 800, "colours": 64}}` with a comment pointing at research.md R1 and saying a change here re-delivers every image and is a reviewed change; `class ImageError(Exception)` carrying `path` and `reason`; `Delivered` named tuple `(data: bytes, treatment: str, note: str | None)`; `treatment_for(name) -> "standard" | "small" | "full" | None` — the stem must contain no `.` other than one optional suffix which is exactly `full` or `small`, otherwise `None`; `deliver(path) -> Delivered` applying the treatment decision **in this order**: (1) `.svg` → `vector`, any extension other than png/jpg/jpeg → `passthrough`, both with committed bytes; (2) `full` → committed bytes; (3) reduce: open, fit to `max_width` with Lanczos keeping aspect ratio and never enlarging, quantise to `colours` (median cut for opaque, fast octree for images with alpha — keep alpha), Floyd–Steinberg dither, save PNG with `optimize=True` and no text/time chunks (pass `pnginfo=None`, strip `info`); if the result is not strictly smaller than the committed bytes → `unchanged` with committed bytes; (4) any decode failure → raise `ImageError(path, reason)`. An unrecognised suffix (`treatment_for` returns `None`) reduces with `standard` and sets `note`. No I/O except reading `path`.
- [X] T006 [US1] Add the output-location guard to `write_payload()` in `scripts/moodle_payload.py` (research.md R7): before anything is created or removed, `pathlib.Path(out_dir).resolve()` checked with `.is_relative_to(REPO.resolve())`; if inside, print `refusing to write the payload inside the repository (<path>); choose a folder outside it, or omit --keep-payload to use a temp folder` to stderr and `raise SystemExit(2)`. Because `publish_moodle.py --keep-payload` also goes through `write_payload()`, confirm that path is covered and add nothing separate there unless it writes elsewhere first.
- [X] T007 [US1] In `scripts/moodle_payload.py`, route every used asset through `image_reduce.deliver()` inside the build (where the `assets` mapping `name -> src` is assembled), before `write_payload()`: build the asset record per data-model.md (`source` relative to the course folder, `source_sha256`, `source_bytes`, `sha256`, `sha1` of the delivered bytes, `bytes`, `treatment`) into `manifest["assets"]` keyed by delivered name (identical to the committed name), and `manifest["image_weight"] = {"source_bytes", "delivered_bytes", "by_treatment"}`. Change `write_payload()` to write the delivered bytes instead of `shutil.copy2`. Catch `ImageError` and exit 1 with `cannot read image <course>/<source>: <reason>` **before** `write_payload()` runs, so nothing is written. Reduction must not change which assets are included or withheld (FR-009): it runs only on the set the existing inventory already selected.
- [X] T008 [US1] In `scripts/moodle_payload.py`, add `report_images(manifest)` and call it from `main()` after the existing `assets` line (T024 calls it from `publish_moodle.py` too, so FR-007/FR-010 hold for a real publish). It prints the weight report in the data-model.md shape: `  images    <n>: <src KB> KB -> <delivered KB> KB (<p>% lighter)`, then one indented line per image whose treatment is `full`, `small` or `unchanged` (`unchanged` lines add `(already smaller than any reduction)`); and append to the existing notes any unrecognised-suffix note from `deliver()` and, per page, `<page>: <KB> KB with images exceeds the 1 MB page budget` when its HTML bytes plus its delivered images exceed 1 048 576 (SC-002).
- [X] T009 [US1] Add check 5 (asset provenance, fail-closed) to `check()` in `scripts/check_moodle_payload.py`, per contracts/payload.md: (1) every file in `assets/` has a record and every record has a file; (2) `source` exists under the course folder (resolve with the existing `source_folder(manifest)`) and neither the source name nor the delivered name is excluded by `disclosure.excluded_asset`; (3) re-hashed committed file == `source_sha256`; (4) re-hashed delivered file == `sha256` and its size == `bytes`; (5) `bytes <= source_bytes`; (6) for `full`, `unchanged`, `vector`, `passthrough` the delivered bytes equal the committed bytes. Every failure is reported in the existing LEAK class and makes the script exit 1. Update the module docstring's list of checks.
- [X] T010 [US1] Add the delivery-suffix check to `scripts/check_course_package.py` (pipeline courses only, alongside `check_images`): for each file under `modules/<slug>/assets/`, use `image_reduce.treatment_for()` (which must not import Pillow) and report `assets/<name>: unrecognised delivery suffix -- use <stem>.full.<ext> (send the original) or <stem>.small.<ext> (smaller), or no suffix` as an error when it returns `None`. Add `scripts/image_reduce.py` to both `paths:` lists in `.github/workflows/course-package.yml`, since the check now imports it.
- [X] T011 [US1] Run `python -m pytest -q tests/test_image_reduce.py tests/test_payload_assets.py` until green, then quickstart L2–L4 and L6 against the real courses (`data-manipulation-skills`, `paratext-quotation-rules`, `supporting-pre-publishing-checks`): record in the PR description the per-course `images` line and confirm ≥50% overall (SC-001), no page-budget note (SC-002), `git status --porcelain` empty (SC-005), and the package check still passing on every pipeline course (`python scripts/check_course_package.py`).
- [X] T012 [US1] Quickstart L5 legibility review (human, SC-004): build the payload for each screenshot course into a temp folder, compare every delivered PNG with its committed original for every label, menu and field the lesson text names; for any that is unreadable, rename the committed file to `<stem>.full.png`, update its links in the lesson, rebuild, and confirm the weight report lists it under `full`. Record the outcome (including "none needed") in the PR description. **Outcome 2026-10-02: all 36 reduced screenshots readable; none needed `.full`.**
- [X] T013 [P] [US1] Add one sentence to rule 6 ("Screenshots and diagrams") in `CLAUDE.md`: the publisher delivers a lighter copy of every screenshot; a file named `<name>.full.png` is sent unchanged and `<name>.small.png` is reduced further, and no other `.` is allowed in an asset's name.
- [X] T014 [P] [US1] Update `process/stages/03-draft.md` step 3e with one short paragraph on the `.full`/`.small` suffix (when to use `.full`: fine text a reviewer says is unreadable after reduction; rename the file and its link together), and `process/stages/06-internal-review.md` "How" with a legibility check on the published screenshots (SC-004) pointing at the `.full` rename as the fix.

**Checkpoint**: US1 complete — a publish sends lighter, traceable, gated images and never writes to the repo. Deliverable on its own against the existing plugin.

---

## Phase 4: User Story 2 — A course works with no connection at all (Priority: P1)

**Goal**: Quizzes are created downloadable for the app (`allowofflineattempts = 1`, with its
four constraints asserted), and the app settings that keep an offline learner signed in are
declared and guarded by `drift`.

**Independent Test**: `python scripts/site_config.py validate` passes with the new
`mobile.yaml` entries; `php -l` on `create_quiz.php`; then (live, post-merge) quickstart V7
on an Android device.

### Implementation for User Story 2

- [X] T015 [P] [US2] In `moodle/local_ltuse/classes/external/create_quiz.php`, set `allowofflineattempts = 1` on the moduleinfo for both create and update, and before `add_moduleinfo()`/`update_moduleinfo()` assert the four constraints `quizaccess_offlineattempts` enforces only in form validation (research.md R4): `timelimit == 0`, `subnet === ''`, `navmethod !== 'sequential'`, `preferredbehaviour` in `['deferredfeedback', 'deferredcbm']`; on violation throw `\moodle_exception` (or `invalid_parameter_exception`) naming the setting that breaks offline use. No new parameters. Look up `add_moduleinfo`/`update_moduleinfo` quiz field handling in `MOODLE_502_STABLE` source before writing (CLAUDE.md "Look up every Moodle API").
- [X] T016 [P] [US2] Add to `moodle/site/settings/mobile.yaml`: `- {name: tool_mobile/autologout, value: 0, why: "an offline learner is never logged out holding an unsynced quiz attempt"}` and `- {name: tool_mobile/forcelogout, value: 0, why: "same reason as autologout: never sign out a learner with unsynced offline work"}`; change `rows: [4]` to `rows: [4, 5]`; extend `purpose:` with one clause about offline use. Run `python scripts/site_config.py validate`.
- [X] T017 [P] [US2] Add a cross-spec note to `specs/007-learner-experience/spec.md` (an Assumptions or Dependencies bullet, citing 009 research.md R4): learner help must say to open the quiz once while online (downloading it starts the attempt) and how to sync by hand if Wi-Fi-only sync blocks it. Also add one line to `process/stages/07-pilot.md` (and the matching point in `process/stages/08-publish.md`): don't republish a course's quiz while learners are working through it, because a republish rebuilds the quiz and can break an offline attempt that hasn't synced yet (spec.md edge case; policy only in 009).

**Checkpoint**: US2 code complete. Deploying it needs the plugin version bump (T026).

---

## Phase 5: User Story 3 — The server serves repeat visits cheaply (Priority: P2)

**Goal**: Caching settings declared as config; a republish leaves every unchanged page and
file untouched, so revisions, image URLs and file times stay the same (FR-016, SC-007), and a
changed image still reaches learners (FR-013).

**Independent Test**: `site_config.py validate` passes with `caching.yaml`;
`tests/test_publish_moodle.py` passes with a fake client; `php -l` on the changed plugin
files; then (live, post-merge) quickstart V3–V6.

### Tests for User Story 3

- [X] T018 [P] [US3] Create `tests/test_publish_moodle.py` (unittest, no network): a fake client object recording every `call()`/`upload()` and returning a canned `local_ltuse_get_course_manifest` result with per-page `files: [{filename, contenthash, filesize}]` and cmids. Cover: (a) unchanged republish — every page's payload `{name: sha1}` equals the server set → zero `upload()` calls, every `local_ltuse_create_page` sent with `contentitemid=0` and no `syncfiles`/`keepfiles`, and the printed summary reads `0 created, 0 updated` and `images: 0 sent`; (b) one changed image → exactly one upload, that page's call carries `syncfiles=True` and the other names in `keepfiles`; (c) an image removed from the payload, nothing else changed → zero uploads, `syncfiles=True`, `contentitemid=0`, and `keepfiles` lists the remaining names but not the removed one (the plugin takes its own empty draft); (d) sibling links to modules that already exist on the server are resolved in pass 1 (no `@@MODULE:` in the first `create_page` content) and pass 2 sends nothing; (e) a link to a module created in this run is still resolved in pass 2; (f) `--dry-run` makes no read call (`get_course_manifest` not called) and no writes.

### Implementation for User Story 3

- [X] T019 [P] [US3] Create `moodle/site/settings/caching.yaml` exactly as contracts/site-settings.md, filling each `why:` from research.md R5's "Why" column: `rows: [5]`, the `purpose:` text, and settings `cachejs: 1`, `yuicomboloading: 1`, `cachetemplates: 1`, `themedesignermode: 0`, `langstringcache: 1`, `slasharguments: 1`. Follow the shape in `moodle/site/README.md`; run `python scripts/site_config.py validate` and `python -m pytest -q tests/test_site_config.py`.
- [X] T020 [P] [US3] Extend `moodle/local_ltuse/classes/external/get_course_manifest.php` per contracts/local_ltuse.md: each `modules[]` entry gains `files`, a list of `{filename: PARAM_FILE, contenthash: PARAM_ALPHANUM, filesize: PARAM_INT}`; for `modname == 'page'` read `get_file_storage()->get_area_files($ctx->id, 'mod_page', 'content', 0, 'filename', false)` using `stored_file::get_filename()`, `get_contenthash()`, `get_filesize()`; `[]` for every other module. Update `execute_returns()` with the new structure (`VALUE_DEFAULT []`/optional so older callers are unaffected). Still read-only, same capability. Confirm the `get_area_files` signature on `MOODLE_502_STABLE` first.
- [X] T021 [US3] Extend `moodle/local_ltuse/classes/external/create_page.php` per contracts/local_ltuse.md: new optional parameters `syncfiles` (`PARAM_BOOL`, default `false`) and `keepfiles` (list of `PARAM_FILE`, default `[]`); new return field `outcome` (`PARAM_ALPHA`: `created`·`updated`·`unchanged`), keeping `created` for older callers. Behaviour in order: (1) no module with this idnumber → existing create path, `created`; (2) `syncfiles` false and name, section number, visibility and stored `page.content` all byte-equal to the incoming values → return `unchanged` without calling `update_moduleinfo()` (no event, no cache rebuild); (3) `syncfiles` true → if `contentitemid == 0`, take an empty draft with `file_get_unused_draft_itemid()` (`public/lib/filelib.php:331`); for each `keepfiles` name, find the stored file in this page's `mod_page/content` area and copy it into the draft with `$fs->create_file_from_storedfile(['contextid' => $usercontext->id, 'component' => 'user', 'filearea' => 'draft', 'itemid' => $contentitemid], $stored)`; a name not found → throw naming the file, before anything is written; then `update_moduleinfo()` with that draft → `updated` (the save deletes every stored file absent from the draft); (4) otherwise `update_moduleinfo()` with `contentitemid` as sent → `updated`. Confirm `create_file_from_storedfile` keeps the source `timemodified` in `MOODLE_502_STABLE` source before relying on it (research.md R2 "to verify").
- [X] T022 [US3] Make `scripts/publish_moodle.py` `publish()` diff each page against the server (contracts/payload.md, data-model.md "Server file record"): after `ensure_sections`, unless `client.dry_run`, call `client.course_manifest(manifest["idnumber"])` (the existing wrapper at `scripts/moodle_client.py:196`) to get existing cmids by idnumber and each page's `{filename: contenthash}`; per page split the payload's `{name: manifest["assets"][name]["sha1"]}` three ways — unchanged (same name and hash: add to `keepfiles`), new or changed (upload to the draft), on the server but not in the payload (send nothing; omitting it from the draft removes it). If nothing is new, changed or removed, pass `contentitemid=0` and omit `syncfiles`/`keepfiles`. Otherwise pass `syncfiles=True`, `keepfiles` (possibly empty) and `contentitemid` from the uploads (0 if nothing was uploaded). Omitting both parameters when nothing changed is what keeps an older plugin working. Use `result.get("outcome")`, falling back to `created`/`updated` from `result["created"]` for an older plugin.
- [X] T023 [US3] In `scripts/publish_moodle.py`, resolve sibling links before pass 1 (research.md R2 decision 3): seed `cmids` from the server manifest's existing modules, and substitute `MODULE_TOKEN_RE` for every target already in it before the first `create_page` send; leave unresolved tokens for pass 2, which now only rewrites pages still containing `@@MODULE:` (targets created in this run). Update the comment above pass 1/pass 2 to say so.
- [X] T024 [US3] In `scripts/publish_moodle.py`, call `moodle_payload.report_images(manifest)` (T008) after the `payload` line; print per page `created`/`updated`/`unchanged` with `n sent, m kept` for its files, and a course summary line `  pages: a created, b updated, c unchanged; images: x sent (<KB> KB), y kept`. `--dry-run` keeps today's behaviour: no read calls, no writes, prints `dry-run`. Run `python -m pytest -q tests/test_publish_moodle.py` until green, and `php -l` on every changed PHP file.

**Checkpoint**: US3 code complete. Deploying it needs the plugin version bump (T026). An
older publisher against the new plugin, and the new publisher against the old plugin, both
keep today's behaviour.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: The plugin release shared by US2 and US3, the requirements row, and the live
verification that closes row #5.

- [X] T025 Update `moodle/local_ltuse/README.md` per contracts/local_ltuse.md: no new direct table writes; the `file_get_unused_draft_itemid()` and `create_file_from_storedfile()` uses and their reason (keeping unchanged files' `timemodified` so the app does not re-download them); the `get_area_files()` read in `get_course_manifest`; `allowofflineattempts` set by `create_quiz` and why it has no admin default (research.md R4).
- [X] T026 Bump `$plugin->version` in `moodle/local_ltuse/version.php` once for US2 + US3 together (next `YYYYMMDDXX`), and move the `local_ltuse` `version:` pin in `moodle/site/site.yaml` to the same value; run `python scripts/site_config.py validate`.
- [X] T027 Confirm `scripts/moodle_client.py` `--whoami` still lists every function the publisher calls (its `needed` list at `scripts/moodle_client.py:214`); no new function names are added by this spec, so expect no change — record that in the PR.
- [X] T028 Update row #5 in `moodle/REQUIREMENTS.md` to **built** (constitution X), citing spec 009, and list SC-003, SC-006, FR-012/FR-013 caching checks, the FR-016 server behaviours (research.md R2 "to verify") and the app's offline quiz qtype / Wi-Fi-only sync questions (R4) as pending live verification. Claim no live success criterion as met.
- [X] T029 Run the full local gate before merge: `python -m pytest -q tests/`, `python scripts/check_course_package.py`, `python scripts/quiz_parse.py --check-all`, `python scripts/site_config.py validate`, quickstart L1–L4 and L6, and `git status --porcelain` empty after a `publish_moodle.py --dry-run` of each screenshot course.
- [X] T030 (live, post-merge) Quickstart V1–V2 on the temporary 5.2.3+ instance: `python scripts/site_config.py apply` then `drift` exits 0 (FR-012); deploy the bumped `local_ltuse`; `python scripts/moodle_client.py --whoami` prints OK for every function. **Done 2026-10-02: drift clean, local_ltuse 0.6.0 deployed, every function OK.**
- [X] T031 (live, post-merge; run the file-time part as soon as the instance is reachable, without waiting for the rest of the live batch) Quickstart V3–V4 (FR-016, SC-007, FR-013). If kept files' `timemodified` turns out not to be preserved, record it; FR-016 then degrades only for changed pages (research.md R2), which doesn't block **built**. Publish a screenshot course twice — second run `0 created, 0 updated, N unchanged; images: 0 sent`, with `page.revision`, `page.timemodified` and each content file's `timemodified` unchanged (admin UI or read-only SQL on the test instance); then a one-sentence edit (only that page `updated`, 0 sent, its files' times unchanged) and a one-image replacement (only that page `updated`, `1 sent, n kept`, only that file's time changes, browser reload shows the new image). **Partial 2026-10-02: second publish of three courses = `0 created, 0 updated, N unchanged; images: 0 sent`; revision/file times not read directly. V4 2026-10-02 (`supporting-pre-publishing-checks`): one-sentence edit = only that page `updated`, 0 sent; one-image change = only that page `updated`, `1 sent, 9 kept`, only that file's hash changed on the server; reverted and republished, final run `0 updated, 0 sent`.**
- [ ] T032 (live, post-merge) Quickstart V5–V6 (US3, SC-003): reload a lesson with devtools open — images and styles from cache or `304`; repeat the image edit and confirm the new image arrives; throttle to 256 kbit/s with an empty cache and open the heaviest lesson — text and first screenshot visible within 15 s.
- [ ] T033 (live, post-merge) Quickstart V7 on an Android device with the Moodle app and a test learner account (US2, SC-006, FR-014, FR-015): download the course, open the quiz once online, airplane mode, open every lesson (all text and images; a lesson with a video shows the app's not-available-offline notice and stays readable), answer and submit the quiz, reconnect and sync (by hand if needed) and confirm the attempt in Moodle; also try sync on mobile data and record the result (R4 `SYNC_ONLY_ON_WIFI`); confirm the app accepts the quiz's question types offline. Then the republish case (spec.md edge case): start a second offline attempt, republish the course from the publisher while the device is still offline, reconnect and sync, and record whether the attempt survives. The result feeds the policy line from T017 and any later spec that skips unchanged quizzes. **Partial 2026-10-02 (app 5.2.1, Samsung S24+, paratext-quotation-rules): downloaded, every lesson and image offline, quiz submitted offline, synced by itself on mobile data, attempt Finished in Moodle. Not yet: a lesson with a video; the republish-while-offline case (skipped).**
- [ ] T034 (live, post-merge) Quickstart V8: record date, app version, device and course for T030–T033 in `moodle/REQUIREMENTS.md` row #5 and flip it to **verified**; carry any learner-help finding (manual sync, open-quiz-first) into `specs/007-learner-experience/spec.md`.

---

## Dependencies & Execution Order

### Phase dependencies

- **Setup (Phase 1)**: none. T001 must land before any task that imports Pillow runs (T003, T005, T011).
- **Foundational (Phase 2)**: empty.
- **US1 (Phase 3)**: after T001. Independent of US2/US3.
- **US2 (Phase 4)**: after Setup; independent of US1 and US3 at code level.
- **US3 (Phase 5)**: T022 needs `manifest["assets"][name]["sha1"]` from **T007 (US1)**. T019–T021 are independent of US1.
- **Polish (Phase 6)**: T025–T026 after whichever of US2/US3 is included; T028–T029 after all included stories; T030–T034 after merge and deployment, in order (T030 first, T034 last).

### Within stories

- US1: T003, T004 (fail first) → T005 → T006, T007 → T008, T009, T010 → T011 → T012. T013, T014 any time.
- US2: T015, T016, T017 all parallel.
- US3: T018 (fail first); T019, T020 parallel; T021 → T022 → T023 → T024 (T022–T024 share `publish_moodle.py`, so sequential).

### Story dependency summary

```text
Setup ──► US1 (MVP) ──────────────► US3 publisher diff (T022–T024)
   │                                   ▲
   ├──► US2 ──────────┐                │
   └──► US3 server side (T019–T021) ───┘
                      └──► Polish (T025–T029) ──merge──► live T030–T034
```

---

## Parallel examples

### User Story 1

```text
Task: "T003 Create tests/test_image_reduce.py"
Task: "T004 Create tests/test_payload_assets.py"
# after T005–T007:
Task: "T009 Add check 5 to scripts/check_moodle_payload.py"
Task: "T010 Add delivery-suffix check to scripts/check_course_package.py"
Task: "T013 CLAUDE.md rule 6 sentence"
Task: "T014 process/stages/03-draft.md and 06-internal-review.md"
```

### User Story 2

```text
Task: "T015 allowofflineattempts + constraint asserts in create_quiz.php"
Task: "T016 tool_mobile/autologout and forcelogout in mobile.yaml"
Task: "T017 learner-help note in specs/007-learner-experience/spec.md"
```

### User Story 3

```text
Task: "T018 Create tests/test_publish_moodle.py"
Task: "T019 Create moodle/site/settings/caching.yaml"
Task: "T020 files[] in get_course_manifest.php"
```

---

## Implementation Strategy

### MVP first (US1 only)

1. T001–T002.
2. T003–T014.
3. **Stop and validate**: quickstart L1–L6. US1 alone already meets SC-001, SC-002, SC-004 and SC-005 and works against the currently deployed plugin.

### Incremental delivery

1. US1 → lighter images, gated, repo untouched.
2. US2 → offline quizzes and sign-in settings (needs T026 to deploy).
3. US3 → caching config and idempotent republish (needs US1's `sha1` and T026 to deploy).
4. Polish → row #5 **built** at merge; live tasks T030–T034 move it to **verified**.

### Notes

- Look up every Moodle API in Context7 and `MOODLE_502_STABLE` source before writing PHP (T015, T020, T021); never edit vendored code.
- Never write `MOODLE_TOKEN` into any file; live tasks read it from the environment.
- Commit after each task or logical group; the PR description carries the T011/T012 measurements.
