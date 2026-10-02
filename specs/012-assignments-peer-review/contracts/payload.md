# Contract: Payload additions and their checks

`scripts/moodle_payload.py` writes it and `scripts/check_moodle_payload.py` verifies it. The rest of the manifest is unchanged. The payload stays platform-neutral. Only names such as `marker_notes_html` say what the content *is*; none says where Moodle stores it.

## `manifest.json` additions

```jsonc
{
  "assignments": [
    {
      "idnumber": "ltct:<slug>:05-font-fallback-assignment.md",
      "source": "05-font-fallback-assignment.md",
      "section": 5,
      "name": "Diagnose a font fallback report",
      "minutes": 45,
      "review": "mentor",                 // mentor | peer
      "completion": "required",           // optional | required
      "submit": {"text": true, "file": true, "max_bytes": 5242880, "types": [".png", ".jpg", ".pdf"]},
      "offline": true,
      "peers": null,
      "intro_html": "...",                // LEARNER-VISIBLE: brief + criteria (learner wording)
      "criteria": [
        {"shortname": "Identifies the failing component", "max": 4,
         "learner_html": "...",            // LEARNER-VISIBLE
         "marker_notes_html": "..."}       // MENTOR-ONLY; mentor review only, "" for peer
      ],
      "assets": ["ss-05-fallback-report.png"],
      "mentor_page": {                     // null if there is no mentor-only text to publish
        "idnumber": "ltct:<slug>:05-font-fallback-assignment.md:mentor-notes",
        "name": "Mentor notes: Diagnose a font fallback report",
        "html_file": "mentor/05-font-fallback-assignment.html",
        "visible": 0                       // ALWAYS 0; the check fails on anything else
      }
    }
  ],
  "discussion": {
    "idnumber": "ltct:<slug>:discussion",
    "name": "Course discussion",
    "intro_html": "...",
    "shared": false                        // from moodle/site/course-discussions.yaml, default false
  }
}
```

- Mentor pages are written under `<out>/<slug>/mentor/`, **never** under `pages/`. That directory split is what lets the check treat "every file in `pages/`" as learner-visible without exceptions.
- A withheld assignment appears in `withheld` and gets a placeholder `intro_html` with **no** criteria and **no** `mentor_page`. Nothing derived from its source is shipped.
- A backfilled course gets a `discussion` block but no assignments (FR-015). A course with no lessons stays unpublishable, as today.

## Checks added to `check_moodle_payload.py` (fail closed, no `--force`)

5. **Mentor-only text** (positive check). For every `*-assignment.md` source, `disclosure.restricted_blocks()` lifts each mentor-only block, using the same `MIN_SIGNIFICANT` and "not also in legitimate learner content" rule as check 2. Each distinctive line must be absent from:
   - every `pages/*.html`
   - every `intro_html` and `learner_html` in the manifest
   - `discussion.intro_html`
6. **Mentor-only placement**:
   - `marker_notes_html` is allowed only when `review == "mentor"`.
   - Every `mentor_page.visible` is `0`.
   - Every `mentor_page.html_file` lies under `mentor/`.
   - No file under `pages/` derives from a mentor page.
7. **Withheld assignments**: a withheld source has the placeholder intro, no criteria and no mentor page, and is reported as a warning ("learners get no assignment from it").

Checks 1–4 are unchanged. A planted line copied from a grading note into a lesson page must fail check 5. That is SC-002's test, and a fixture under `tests/fixtures/` (test content only, no learner data) proves it in CI.

## Learner view of the review site

`gen_course_site.py` renders an assignment file like a lesson page:
- **Reviewer view**: each mentor-only block is shown in an admonition titled "Mentor only".
- **Learner view**: the page is stripped with `disclosure.strip_restricted()` and withheld on `ok = False`.

`check_learner_view.py` gains the same positive assertion as check 5, against the built learner site.
