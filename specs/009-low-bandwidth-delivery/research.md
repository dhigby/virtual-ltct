# Research: Low-bandwidth and offline delivery

Sources: Moodle `MOODLE_502_STABLE` at 16f374ba78 (weekly 5.2.3+). In 5.x, core lives under
`public/`. Moodle app: moodleapp `latest` at 66f26ca (5.2.1), cross-checked against `main` on
2026-10-01. Measurements were taken on the 26 PNGs committed on this branch. Line numbers
are from those checkouts and may drift.

Each item says whether it was **verified in source**, **measured**, or is **to verify on the
instance**. Under Constitution X, every "to verify" item is a task, not an assumption.

---

## R1. How to make images lighter

**Decision.** Fit each image to a maximum width, then save it as a 256-colour (palette)
PNG with `optimize=True`, without dithering (see "Dithering" below). The `standard` profile uses 1280 px
and 256 colours. `.small` uses 800 px and 64 colours. The format stays PNG and the file name
stays the same. Use Pillow, pinned to an exact version.

**Measured** (all 26 committed PNGs, 3912 KB in total; an image is never larger than its
original):

| Method | Delivered | Saved |
|---|---|---|
| Fit to 1280, PNG optimise only | 3726 KB | 4.7% |
| Fit to 1280, lossless WebP | 2984 KB | 23.7% |
| Fit to 1280, WebP quality 90 | 1577 KB | 59.7% |
| **Fit to 1280, 256-colour PNG** (`standard`) | **1286 KB** | **67.1%** |
| Fit to 800, 64-colour PNG (`.small`) | 843 KB | 78.4% |

- **Resizing alone barely helps.** Most committed screenshots are already 600–1000 px wide.
  Their weight comes from 24-bit and RGBA colour depth, not from their dimensions.
- **Legibility.** I compared the originals with the 256-colour copies side by side for the
  two heaviest screenshots (RegEx Pal, Paratext editor). Every menu label, button, field and
  line of text is indistinguishable. The one visible change is a slight hue shift in a small
  multicoloured icon. That matters only where a lesson names a colour, and `.full` is the
  escape hatch for that case. A reviewer still checks this per course (SC-004).
- **Page weight (SC-002).** Today the heaviest lesson (`data-manipulation-skills/01`) carries
  1601 KB of images, which already breaks the 1 MB budget. With `standard` it carries 488 KB.
  Every current lesson then fits within 1 MB.
- **Determinism (FR-006).** Two runs on the same input produced byte-identical output with
  Pillow 12.3.0. Pillow writes no time chunk to a PNG. A Pillow upgrade may change the bytes,
  which costs one re-upload of each changed image and nothing else. So the version is
  pinned in `publish-requirements.txt` and changed deliberately.
- **Dithering** (found while implementing). Pillow's `quantize()` dithers only when it
  maps onto a fixed palette, so the measurements above were undithered. A median-cut palette
  re-applied with Floyd–Steinberg gave 58.8% against 64.4% undithered with Pillow 12.3.0
  (the table's 67.1% came from the research script's slightly different mode handling), by
  speckling flat UI backgrounds. The legibility review was made on undithered output, so
  that is what ships.
- **Transparency.** RGBA sources are quantised with fast octree, which keeps alpha. Opaque
  sources use median cut.

**Alternatives considered.**
- **WebP, lossy or lossless.** Moodle core (`public/lib/classes/filetypes.php:295`) and the
  app (`src/assets/exttomime.json`) both recognise WebP. But WebP is heavier than palette PNG
  on these screenshots, it blurs text when lossy, and it would change file names. A changed
  name breaks the rule that a delivered image keeps its committed name, and with it the alt
  text and the disclosure rules.
- **pngquant / libimagequant.** It gives better palettes, but it is not in Pillow's wheels
  (`features.check_feature('libimagequant')` is False) and it would mean installing a
  separate binary on every contributor laptop.
- **A lighter copy committed beside the original.** Rejected: FR-002 and SC-005, and binary
  churn in git.

**New dependency.** Pillow has binary wheels for Windows, macOS and Linux. It is needed only
where a payload is built (`publish-requirements.txt`, the publish workflow and the new test
workflow). `check_course_package.py` and the review-site build do not import it.

## R2. Why image URLs change on republish, and how to stop it (FR-016)

**Verified in source.**
- **`page_update_instance()` increments the revision.** `public/mod/page/lib.php:150`:
  `$data->revision++;`. Our publisher reaches it through `update_moduleinfo()`
  (`moodle/local_ltuse/classes/util.php:195` → `public/course/modlib.php:741`).
- **The revision goes into every image URL.** `public/mod/page/view.php:88`:
  `file_rewrite_pluginfile_urls($page->content, 'pluginfile.php', $context->id, 'mod_page', 'content', $page->revision)`.
- **Serving ignores it.** `lib.php:338-339` (`$arg = array_shift($args);`) and `:362`
  (`/0/$relativepath`). It exists only to bust caches.
- **Headers.** `send_stored_file($file, null, 0, ...)` (`lib.php:382`). A null lifetime means
  `$CFG->filelifetime`, 6 hours by default (`public/lib/setup.php:956-958`). That value can
  only be set in `config.php`; there is no admin setting for it. The header is
  `Cache-Control: private, max-age=…` with `Etag: "<contenthash>"` and 304 revalidation
  (`public/lib/filelib.php:2552-2577`, `:2196-2214`).
- **No new files means existing files are left as they are.** `lib.php:166-169`:
  `if ($draftitemid) { … file_save_draft_area_files(…) }`. A save with `itemid=0` leaves the
  file area and every file's `timemodified` alone. Pass 2 of today's publisher already
  relies on this.
- **A re-uploaded file takes the draft's time.** Inside `file_save_draft_area_files()`
  (`public/lib/filelib.php`), when a file survives a save its record is kept, but
  `set_timemodified($newfile->get_timemodified())` copies the draft file's time onto it.
  Today's publisher re-uploads every image on every publish, so every image's time is
  bumped.

**Verified in the app source.**
- **The app stores each file once.** It keys files with the revision removed
  (`src/core/services/filepool.ts:1617`, "so updates on the file aren't detected as a
  different file"). The page handler rewrites `/mod_page/content/<n>/` to `/0/`
  (`src/addons/mod/page/services/handlers/pluginfile.ts`).
- **What counts as changed.** `isFileOutdated()` (`filepool.ts:2611-2627`) ignores the
  revision when it has a `timemodified`.
  - **Course download** passes each file's `timemodified` from `get_contents`, so it
    re-fetches only the files whose time increased.
  - **Viewing a page** passes `timemodified=0`, so a revision bump makes the app re-fetch each
    of that page's images once, on the next online view.
- **Module-level updates.** `page_check_updates_since` (`page/lib.php:549`) reports
  `configuration` when the page's `timemodified` changes and `contentfiles` when a file's
  time is newer. The app then marks the module outdated.

**Decision.** Three behaviours, all in our own plugin and publisher:

1. **An unchanged page is not saved.** `local_ltuse_create_page` compares the incoming
   name, section, visibility and content with the stored page. When there are no files to
   change and all of those match, it returns `outcome: unchanged` without calling
   `update_moduleinfo()`. Revision, `timemodified` and every image URL stay as they were.
2. **Unchanged files are never re-sent.** `local_ltuse_get_course_manifest` returns each
   page's files with their `contenthash` (SHA-1). The publisher compares those with the SHA-1
   of each delivered image.
   - If the sets match, it uploads nothing and passes `contentitemid=0`, `syncfiles=false`.
   - If they differ, it uploads only the new or changed files, and passes `syncfiles=true`
     with the names of the unchanged ones as `keepfiles`. When nothing needs uploading (a
     page that only lost an image) the plugin takes its own empty draft with
     `file_get_unused_draft_itemid()` (`public/lib/filelib.php:331`). The plugin copies those stored files into the same draft
     (`file_storage::create_file_from_storedfile()`, which keeps the source record's
     `timemodified`) before saving. So only the changed images get a new time.
   - Removed images are absent from the draft, and `file_save_draft_area_files()` deletes
     them, as it does today.
3. **Final HTML in the first pass.** Sibling-lesson links (`@@MODULE:…@@`) are resolved
   before the first send whenever the target already exists. The existing cmids come from
   the same manifest call. Otherwise pass 1 would always send tokenised HTML, which never
   matches the stored resolved HTML, and an unchanged page would still be updated twice.
   Pass 2 stays, but only for links to modules created in this run.

**To verify on the instance** (task, Constitution X):
- `create_file_from_storedfile()` into a draft keeps `timemodified`, and so does
  `file_save_draft_area_files()` (so a kept file's time is unchanged).
- An unchanged republish leaves `page.revision`, `page.timemodified` and each file's
  `timemodified` unchanged.
- A save from a draft holding only the kept files deletes the dropped file and leaves the
  kept ones' `timemodified` alone.

**If a timestamp is not kept.** FR-016 degrades safely. Kept files on a *changed* page would
get a new time and be re-downloaded once, which is today's behaviour. Unchanged pages are
never saved at all (decision 1), so they are unaffected either way. There is no regression
from today.

**Alternatives considered.**
- **Images hosted outside the page** (raw GitHub, the gh-pages review site, a server folder,
  or a `local_ltuse` file area addressed by content hash). Rejected, see R3: they are not part
  of "download course".
- **Patching mod_page so the revision isn't bumped.** Forbidden by Constitution XI.
- **Stopping only the uploads but still saving the page.** Course download would be fine,
  but every page view after any republish would re-fetch every image. That fails FR-016.

## R3. Images from anywhere but the page's own files don't work offline

**Verified in the app source.**
- **"Download course" fetches only what the page lists.** For mod_page that is what
  `core_course_get_contents` lists: the page's own `mod_page/content` files plus
  `index.html`. Server: `page_export_contents()` (`public/mod/page/lib.php:402-466`). App:
  `resource-prefetch-handler.ts:89-120` and `module-prefetch-handler.ts`
  `getContentDownloadableFiles` (`module.contents`).
- **The page body is never scanned for image links.** Only the intro is
  (`getIntroFilesFromInstance` → `extractDownloadableFilesFromHtmlAsFakeFileObjects`).
- **Images from elsewhere are cached only when the page is viewed online**
  (`external-content.ts` → `getSrcByUrl`, `downloadUnknown=true`).
  - This covers both external URLs and same-site areas outside the page.
  - Even then, only images up to 2 MB on mobile data, or 20 MB on Wi-Fi, are cached. An image
    of unknown size is cached only on Wi-Fi (`filepool.ts:404-405`, `:842`).
  - These images carry no time, so a pull-to-refresh marks them stale
    (`invalidateFilesByComponent(…, onlyUnknown=true)`).

**Decision.** Images stay in each page's own file area (`@@PLUGINFILE@@`), as today. Hosting
them on GitHub would also serve the full-size committed originals, because the lighter
copies are never committed. And it would tie delivery to GitHub, against INTENT.md
portability (Principle II).

## R4. Offline quiz attempts need a flag we don't set today (FR-014)

**Verified in source.**
- **The field.** `quiz.allowofflineattempts`, default 0 (`public/mod/quiz/db/install.xml:50`).
  Its form element and validation belong to `quizaccess_offlineattempts`
  (`public/mod/quiz/accessrule/offlineattempts/rule.php`), not to `mod_form.php`.
- **There is no admin default.** That access rule has no `settings.php`.
- **Constraints**, from `validate_settings_form_fields`: no time limit, no subnet, navigation
  not sequential, behaviour `deferredfeedback` or `deferredcbm`.
  - Our quizzes meet all four. `create_quiz.php` sets `deferredfeedback` and no time limit,
    and leaves `navmethod` at the `free` default (`public/mod/quiz/settings.php:175`).
  - `add_moduleinfo()` does not run form validation, so our plugin must set the flag and
    enforce the constraints itself.
- **The app won't download a quiz without the flag.**
  `src/addons/mod/quiz/services/handlers/prefetch.ts:184`:
  `if (!AddonModQuiz.isQuizOffline(quiz) || …) return false;`. `isQuizOffline()`
  (`quiz.ts:1625`) also needs non-sequential navigation and offline use not disabled on the
  site.
- **Downloading a quiz starts an attempt on the server** (`prefetch.ts:270-297`). The learner
  must be online to download it, answers offline, and the attempt syncs on reconnection
  (`save_attempt`/`process_attempt` → `set_offline_modified_time`, `external.php:1532`,
  `:1638`).
- **Capabilities.** `mod/quiz:attempt` (student archetype) and
  `moodle/webservice:createmobiletoken` (user archetype). The roles already have both.

**Decision.** `local_ltuse_create_quiz` sets `allowofflineattempts = 1` on create and on
update, and asserts the four constraints. It refuses rather than silently produce a quiz
that cannot go offline. Bump the plugin version and pin it in `moodle/site/site.yaml`.

**To verify on the device.**
- The app accepts our question types offline. Our quizzes are multiple choice only; the
  app's `getQuizRequiredQtypes` check was not enumerated.
- **Wi-Fi-only sync is a per-user app setting the server cannot change.** Background sync
  honours `SYNC_ONLY_ON_WIFI`, and its defaults disagree between `cron.ts:103` (false) and
  the settings page (true). Test sync on mobile data. If it won't sync, the learner-facing
  help (spec 007) says how to sync by hand.

## R5. Server caching and app settings to declare (FR-012)

**Verified in source.** All of these are core settings in `config`.

| Setting | Defined | Default | Declared value | Why |
|---|---|---|---|---|
| `cachejs` | `public/admin/settings/appearance.php:288` | 1 | 1 | JS caching and minification |
| `yuicomboloading` | `appearance.php:287` | 1 | 1 | Combined YUI requests |
| `cachetemplates` | `appearance.php:306` | 1 | 1 | Mustache templates cached in the browser |
| `themedesignermode` | `appearance.php:319` | 0 | 0 | When on, theme caching is off for every user |
| `langstringcache` | `public/admin/settings/language.php:18` | 1 | 1 | Server-side string cache |
| `slasharguments` | `public/admin/settings/server.php:157` | 1 | 1 | Revisioned path URLs for theme assets, which caches handle better than `?rev=` |
| `tool_mobile/autologout` | `public/admin/tool/mobile/settings.php:479` | 0 | 0 | An offline learner is never logged out with an unsynced attempt |
| `tool_mobile/forcelogout` | `settings.php:467` | 0 | 0 | Same reason |

- **Every value is a default.** Declaring them is what lets `drift` catch someone turning on
  designer mode, or switching off JS caching or offline use. 001 already declares
  `tool_mobile/disabledfeatures: ""`, `enablewebservices` and `enablemobilewebservice`.
- **Not declared here.**
  - `$CFG->filelifetime` can only be set in `config.php`, so it belongs to provisioning
    (015). The 6-hour default is fine, because a content change produces a new URL anyway.
  - HTTP compression has no core setting for pages; it is web-server or PHP configuration
    (015). Core compresses only its own static-asset scripts (`min_enable_zlib_compression`,
    `public/lib/configonlylib.php:131`).
  - Cache stores (MUC, Redis) are 015's job.
  - `tool_mobile/minimumversion` is left unset, so no learner is locked out of an older app.
- **App constants we can't change** but design for:
  - A download confirmation appears above 10 MB on mobile data and 100 MB on Wi-Fi
    (`src/core/services/file.ts:92-93`).
  - At the measured weights, a whole course stays well under 10 MB.

## R6. Linked video offline (FR-015)

**Verified in the app source.** `src/core/static/iframe.ts:70` (`checkOnlineFrameInOffline`)
hides an online-only iframe when the app is offline, shows "This content is not available
offline…", and reloads it when the connection returns. Vimeo embeds go through
`media/player/vimeo/wsplayer.php` (`format-text.ts:1130`), so they behave the same way.

**Decision.** Nothing to build. Lessons already carry the content in text and screenshots
(`check_course_package.py` requires a visual, and video is never the only route). The
device check confirms the notice appears.

## R7. Keeping payloads out of the working tree

**Decision.** `write_payload()` refuses an output directory that resolves inside `REPO`.
Both entry points (`moodle_payload.py --out` and `publish_moodle.py --keep-payload`) go
through it, so one guard covers both. It uses `Path.resolve()` with `is_relative_to()`,
which handles `..`, symlinks and a Windows drive-letter case mismatch.

## R8. Delivery suffix and the existing tooling

- **Verified in the repo.** `check_course_package.py` has no strict file-name regex
  (`IMAGE_LINK_RE` matches any `src`). `gen_course_site.py` mirrors the folder. The disclosure
  rules match on file names. So `x.full.png` works everywhere unchanged. The only addition
  is the new suffix check.
- **Decision.** The suffix check is an error for pipeline courses. The payload builder
  issues a note and uses `standard` for any unrecognised suffix.
