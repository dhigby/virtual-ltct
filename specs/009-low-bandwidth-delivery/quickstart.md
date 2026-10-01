# Quickstart: validating low-bandwidth and offline delivery

There are two tiers. **Local** runs in any checkout, Windows included, with no server, and
must pass before merge. **Live** needs the temporary 5.2.3+ instance and an Android device
with the Moodle app. It can run after merge, and it is what moves row #5 from **built** to
**verified** (spec clarification 2026-10-01). Use test accounts only.

Prerequisites: `pip install -r publish-requirements.txt pytest`, which now includes the
pinned Pillow.

## Local

### L1. Unit tests

```bash
python -m pytest -q tests/test_image_reduce.py tests/test_payload_assets.py
```

Expected, for synthetic fixtures generated in a temp dir:
- Reduction is deterministic: two runs produce the same bytes.
- Output is never larger than the input.
- `.full`, SVG and non-raster files pass through unchanged.
- Alpha survives.
- An undecodable PNG raises.
- Suffix parsing accepts `.full`/`.small`/none and rejects `.ful`/`.full.small`.
- The output guard refuses a path inside the repo.

### L2. Build a real payload and read the weight report

```bash
python scripts/moodle_payload.py --slug data-manipulation-skills --out "$TEMP/ltct"
```

Expected:
- An `images` line showing at least a 50% reduction (SC-001).
- No page-weight note, i.e. every page is 1 MB or less (SC-002).
- Total weight across the three screenshot courses at least 50% below the originals.

### L3. The disclosure gate covers the reduced assets

```bash
python scripts/check_moodle_payload.py --payload "$TEMP/ltct" --slug data-manipulation-skills
```

Expected: clean.

Then tamper with the payload and re-run each time. Each case must fail with exit 1 (FR-008,
FR-009):
- Replace one delivered PNG.
- Delete one record from `manifest.json`.
- Copy in an asset whose source is excluded.

### L4. The repository is untouched (SC-005)

```bash
python scripts/moodle_payload.py --slug data-manipulation-skills --out ./payload   # must exit 2, write nothing
python scripts/publish_moodle.py --slug data-manipulation-skills --dry-run
git status --porcelain                                                               # must print nothing
```

### L5. Legibility review (SC-004, a human step)

Build the payload. Open each delivered image beside its committed original, and check
every label, menu and field the lesson text names. If any is unreadable, rename that file
to `.full`, update its links, rebuild, and confirm the report lists it under `full`.

### L6. Package check accepts suffixes and rejects typos

```bash
python scripts/check_course_package.py --course paratext-quotation-rules
```

Expected: passes as it does today.

Then rename one asset to `x.ful.png`, still on a scratch branch. The check must fail and
name the file. Revert afterwards.

## Live (temporary instance and device; pending at merge)

### V1. Site settings

```bash
python scripts/site_config.py apply
python scripts/site_config.py drift      # exit 0
```

`caching.yaml` and the new `mobile.yaml` entries must apply with no manual step (FR-012).

### V2. Plugin

Deploy the bumped `local_ltuse`, then run `python scripts/moodle_client.py --whoami`. It must
print `OK` for every function.

### V3. Idempotent republish (FR-016, SC-007)

Publish a course twice. On the second run, expect `0 created, 0 updated, N unchanged;
images: 0 sent`.

In Moodle, `page.revision`, `page.timemodified` and each content file's `timemodified` must
be unchanged. Check these with the admin UI or a read-only SQL query on the test instance.

### V4. One edit, minimal churn (FR-013)

1. **Text edit.** Change one sentence in one lesson and republish. Only that page shows
   `updated`, with 0 images sent. Its files' `timemodified` are unchanged.
2. **Image edit.** Replace one screenshot and republish. Only that page shows `updated`,
   with `1 sent, n kept`. Only that file's `timemodified` changes. A browser reload shows the
   new image (no stale copy).

### V5. Caching (US3)

Load a lesson, then reload it with devtools open. Images and styles must come from cache or
answer `304` (no re-transfer). Then repeat V4 step 2: the new image arrives.

### V6. Throttled load (SC-003)

Throttle devtools to 256 kbit/s with an empty cache, and open the heaviest lesson. The text
and first screenshot must be visible within 15 seconds.

### V7. Offline in the Android app (US2, SC-006)

1. While online, sign in with a test learner and choose **Download course**.
2. Open the quiz once, which downloads it and starts the attempt.
3. Switch to airplane mode.
4. Open every lesson: text and every image must show. A lesson with a video must show the
   app's "not available offline" notice, and the lesson must still be readable.
5. Answer the quiz and submit.
6. Reconnect and sync (by hand if Wi-Fi-only sync is on). The attempt must appear in Moodle.
7. Also try syncing on mobile data, and record the result, because of the
   `SYNC_ONLY_ON_WIFI` question in research.md R4.

### V8. Record

- Write the results in `moodle/REQUIREMENTS.md` row #5, with the date, app version, device
  and course.
- Flip the row to **verified** in that change.
- Note any finding for spec 007's learner help, for example manual sync.
