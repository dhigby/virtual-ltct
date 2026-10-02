# Contract: `local_ltuse` web-service changes

Every function keeps its name and existing parameters, and stays backward compatible. A
publisher older than this change still works: it simply gets today's behaviour. Bump
`version.php` and the pin in `moodle/site/site.yaml` in the same change. Every API used
below is confirmed in research.md; the two "to verify" items there are tasks.

## `local_ltuse_get_course_manifest` (extended return)

Each entry in `modules[]` gains:

```text
files: [ { filename: PARAM_FILE, contenthash: PARAM_ALPHANUM, filesize: PARAM_INT } ]
```

- For `modname == 'page'`: `get_file_storage()->get_area_files($ctx->id, 'mod_page', 'content', 0, 'filename', false)`.
- For anything else: `[]`.

It stays read-only, needs the same `local/ltuse:publish` capability, and adds one query per
page, all on an indexed context.

## `local_ltuse_create_page`

New optional parameters:

| Param | Type | Default | Meaning |
|---|---|---|---|
| `syncfiles` | `PARAM_BOOL` | `false` | the page's file area is to become exactly `keepfiles` plus the draft's contents; set whenever any file is new, changed or removed |
| `keepfiles` | list of `PARAM_FILE` | `[]` | existing files in this page's `content` area to carry into the draft unchanged; read only when `syncfiles` is true |

New return field:

| Field | Type | Values |
|---|---|---|
| `outcome` | `PARAM_ALPHA` | `created` · `updated` · `unchanged` |

`created` stays in the return, for older callers.

Behaviour, in order:

1. **No module with this idnumber.** Behaviour is unchanged → `created`.
2. **`syncfiles` is false, and name, section number, visibility and `page.content` are all
   byte-equal to the stored page.** Return `unchanged`. Write nothing: no
   `update_moduleinfo()`, no event, no cache rebuild.
3. **`syncfiles` is true.** If `contentitemid == 0` (nothing was uploaded, e.g. a page that
   only lost an image), take an empty draft with `file_get_unused_draft_itemid()`
   (`public/lib/filelib.php:331`) and use it as `contentitemid`. Then for each name in
   `keepfiles`, copy the stored file into the draft with
   `$fs->create_file_from_storedfile(['contextid' => usercontext, 'component' => 'user',
   'filearea' => 'draft', 'itemid' => contentitemid], $stored)`. A name not present in the
   area is an error, naming the file, and nothing is written. Then `update_moduleinfo()` →
   `updated`: `file_save_draft_area_files()` deletes every stored file absent from the
   draft, so an empty `keepfiles` with nothing uploaded removes every file.
4. **Otherwise** (`syncfiles` false, something differs). `update_moduleinfo()` with
   `contentitemid` as sent (0 leaves the file area untouched) → `updated`.

The publisher sends `syncfiles=false` for a page whose files are unchanged, so rule 2 is the
whole unchanged path. A caller that sends `contentitemid != 0` without `syncfiles` (an older
publisher) gets today's behaviour: the draft replaces the area.

## `local_ltuse_create_quiz`

| Change | Detail |
|---|---|
| sets | `allowofflineattempts = 1` on create and on update |
| asserts | `timelimit == 0`, `subnet == ''`, `navmethod != 'sequential'`, `preferredbehaviour in (deferredfeedback, deferredcbm)`; otherwise throws, naming the setting that breaks offline use |

No new parameters. The four constraints are those `quizaccess_offlineattempts` enforces in
its form validation, which `add_moduleinfo()` does not run (research.md R4).

## README

`moodle/local_ltuse/README.md` records:
- No new direct table writes.
- The `create_file_from_storedfile()` use, with its reason (keeping unchanged files'
  `timemodified`, so the app doesn't re-download them).
