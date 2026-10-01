# Implementation Plan: Low-bandwidth and offline delivery

**Branch**: `009-low-bandwidth-delivery` | **Date**: 2026-10-01 | **Spec**: [spec.md](spec.md)

## Summary

There are three deliverables. Only the first is new code of any size.

1. **Lighter images, made in the payload.** A new platform-neutral module,
   `scripts/image_reduce.py`, turns each committed raster into a 256-colour PNG fitted to
   1280 px (`.small`: 64 colours, 800 px). `.full` and SVG pass through untouched. The
   delivered file keeps its committed name, so links, alt text and the disclosure rules are
   unchanged.
   - Measured on the 26 committed screenshots: 67% lighter. The heaviest lesson drops from
     1.6 MB to 488 KB.
   - Each delivered image carries an asset record (source path, both hashes, both sizes, its
     treatment) in `manifest.json`.
   - The pre-publish gate gains a fifth fail-closed check that re-hashes both sides.
   - A payload directory inside the repo is refused.
2. **Republish touches only what changed.** Moodle bumps a page's revision, and so every
   image URL on it, on every update (research.md R2). So the plugin returns `unchanged`
   without writing when nothing differs. The publisher uploads only new or changed images
   and keeps the rest with their original `timemodified`. Sibling links are resolved before
   the first send, so an unchanged page really does compare equal. The result:
   - An unchanged republish writes nothing and sends nothing.
   - A one-sentence edit costs one page's revision.
   - The app's course download re-fetches only files whose time changed.
3. **Offline that actually works, plus declared caching.** Our quizzes cannot be downloaded
   in the app today, because `allowofflineattempts` defaults to 0 with no admin default
   (R4). `local_ltuse_create_quiz` now sets it and asserts its four constraints. Caching and
   app-session settings are declared in `moodle/site/`. All of them are Moodle defaults,
   declared so `drift` guards them (R5).

Images stay in each page's own file area. Hosting them on GitHub or anywhere outside the
page was researched and rejected: the app's "download course" fetches only the page's own
files (R3).

## Technical Context

**Language/Version**: Python 3.12 (CI), and 3.11+ on contributor laptops. PHP 8.2+ for the
`local_ltuse` plugin on Moodle 5.2.3+.

**Primary Dependencies**:
- Existing: `markdown`, `pymdown-extensions`, `pyyaml`.
- New: **Pillow, pinned** (`pillow==12.3.0`), only in `publish-requirements.txt`.
- Moodle core APIs: `file_storage`, `update_moduleinfo`.

**Storage**: none new. Moodle's file area and `contenthash` are the only state.

**Testing**: pytest, in the existing `tests/` style (unittest classes, synthetic fixtures in
temp dirs). A new workflow runs it. Live checks are listed in [quickstart.md](quickstart.md)
V1–V8.

**Target Platform**:
- Publisher: Windows, macOS and Linux laptops, plus `ubuntu-latest` CI.
- Server: self-hosted Moodle 5.2.3+.
- Learners: the free Moodle Android app (5.2.x) and browsers.

**Project Type**: CLI scripts and a Moodle local plugin.

**Performance Goals**:
- At least 50% lighter images overall (measured: 67%).
- Every lesson page at 1 MB or less (measured maximum: about 506 KB with HTML).
- An unchanged republish uploads 0 bytes of images.

**Constraints**:
- Committed files are never modified (FR-002).
- The payload stays platform-neutral (FR-005).
- No vendored Moodle code is edited (Constitution XI).
- The development checkout has no server or device access, so live checks complete after
  merge.

**Scale/Scope**:
- Today: 3 screenshot courses and 26 images.
- Expected: dozens of courses, which is why the per-image override is a file-name suffix and
  not a list.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | **PASS.** Committed images are only read. Delivered copies exist only in a temp payload. An output folder inside the repo is refused. Publishing stays one-way: the plugin reads Moodle's file hashes only to decide what to send, never to write back to the repo. |
| II. Portability | **PASS.** `image_reduce.py` and the asset records know nothing of Moodle. `sha1` is plain data that happens to match Moodle's `contenthash`. Settings live in `moodle/site/`. The suffix is readable in a raw file listing and needs no tool. |
| III. Public repo (NON-NEGOTIABLE) | **PASS.** No learner data. Tests use synthetic images. The device check uses a test account. |
| IV. Disclosure (NON-NEGOTIABLE) | **PASS, strengthened.** Reduction does not change which assets are included. Check 5 proves every delivered byte traces to a non-excluded committed source, and fails closed. |
| V. CBC fidelity | **PASS.** Not touched. |
| VI. No git, no LMS orientation | **PASS.** Authors only rename a file to `.full` if a reviewer flags it. Learners get working offline quizzes with nothing new to learn. |
| VII. One shape, gated stages | **PASS.** No content-model change. `check_course_package.py` gains one naming check. |
| VIII. Language data | **PASS.** Images are reduced by pixels, never interpreted. The legibility review is human (SC-004). |
| IX. Flat cost, field-ready | **PASS.** This is the principle's purpose. No paid service. No binaries committed. Video is never the only route (R6). |
| X. Traceable and verified | **PASS, with live checks pending.** Cites row #5. Every Moodle and app claim is confirmed in `MOODLE_502_STABLE` or moodleapp source (research.md). The two server behaviours not yet observed (R2, R4) are tasks. Row #5 goes to **built** at merge and **verified** after quickstart V1–V8. No new recurring operations; `filelifetime`, compression and cache stores belong to 015. |
| XI. Survives an upgrade | **PASS.** Only public APIs: `file_storage::get_area_files`, `create_file_from_storedfile`, `update_moduleinfo`, and quiz fields through `add_moduleinfo`. No direct table writes. Plugin `version` bumped, and the pin moved in `site.yaml`. |
| Platform: core first | **PASS.** No new plugin. Everything is core settings or our own plugin. |

Re-checked after Phase 1 design: no change. The design grew FR-016 (from a user decision
during planning) and the offline-quiz fix (from research). Both keep within the principles
above.

## Project Structure

### Documentation (this feature)

```text
specs/009-low-bandwidth-delivery/
├── spec.md
├── plan.md               # this file
├── research.md           # R1–R8
├── data-model.md
├── quickstart.md         # L1–L6 local, V1–V8 live
├── contracts/
│   ├── payload.md        # manifest additions, image_reduce, gate check 5, CLI behaviour
│   ├── local_ltuse.md    # get_course_manifest files, create_page keepfiles/outcome, create_quiz offline
│   └── site-settings.md  # caching.yaml, mobile.yaml additions
└── tasks.md              # /speckit-tasks
```

### Source Code (repository root)

```text
scripts/
├── image_reduce.py             # NEW: profiles, suffix parsing, deliver(); Pillow imported lazily
├── moodle_payload.py           # reduce assets, asset records, weight report, output guard
├── check_moodle_payload.py     # check 5: asset provenance
├── publish_moodle.py           # per-page diff against the manifest; keepfiles; pass-1 link resolution; summary
├── moodle_client.py            # upload unchanged; whoami unchanged
└── check_course_package.py     # delivery-suffix check
moodle/
├── local_ltuse/
│   ├── classes/external/get_course_manifest.php   # files[] per page
│   ├── classes/external/create_page.php           # keepfiles, outcome, unchanged short-circuit
│   ├── classes/external/create_quiz.php           # allowofflineattempts + constraint asserts
│   ├── version.php                                # bumped
│   └── README.md                                  # files API use recorded
├── site/
│   ├── site.yaml                                  # local_ltuse pin bumped
│   └── settings/
│       ├── caching.yaml                           # NEW, row #5
│       └── mobile.yaml                            # + autologout, forcelogout; rows [4, 5]
└── REQUIREMENTS.md                                # row #5 -> built (verified after V1–V8)
tests/
├── test_image_reduce.py        # NEW
└── test_payload_assets.py      # NEW: records, gate check 5, output guard, suffix check
publish-requirements.txt        # + pillow==12.3.0
.github/workflows/
└── publisher-tests.yml         # NEW: pytest on scripts/{image_reduce,moodle_*,check_*,disclosure}.py and tests/
CLAUDE.md                       # rule 6: the .full/.small suffix, one sentence
process/stages/03-draft.md      # step 3e: rename to .full/.small; 06-internal-review.md: legibility check (SC-004)
```

**Structure Decision**:
- **Reduction is its own module.** It is not part of `moodle_payload.py`, because the
  package check needs `treatment_for()` without needing Pillow, and because a future
  delivery target would import it directly.
- **The unchanged-page decision is made on the server.** `create_page` compares with what is
  actually stored, rather than in the publisher from a hash it would have to keep somewhere
  (no repo state file, per INTENT).

## Cross-spec effects

- **001 (site config).** Adds one settings file and two settings. The plugin pin moves.
- **007 (learner experience).** Learner help should cover "open the quiz once while online"
  (downloading it starts the attempt) and manual sync if Wi-Fi-only sync blocks it (R4).
- **015 (hosting).** Owns `filelifetime`, HTTP compression and cache stores. Nothing here
  depends on them.

## Complexity Tracking

No violations to justify.
