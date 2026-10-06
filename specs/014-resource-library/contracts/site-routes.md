# Contract: site routes

Pages `scripts/gen_site.py` generates from `resources.yaml` on the public competency
site. Public, no log-in (FR-005). The build stays `strict: true`.

## `/library/`

- One page. Grouped by framework category (`##`), then by competency (`###`).
- One `####` heading per resource, titled with its `title`. Under it: description, type,
  language, and the competency names, each linking to its competency page.
- Text only: no images, no embeds (R8, FR-006).
- Indexed by the Material `search` plugin, one entry per heading (R3).

## Anchors: `/library/#<anchor>`

- `<anchor>` is the heading id the `toc` extension gives the resource heading.
- Lessons and Moodle link to a resource by this URL (R7).
- A resource listed under several competencies appears once per competency; the first
  occurrence keeps the plain anchor.
- Renaming a resource's title changes its anchor. Treat a title change like a moved
  page: keep the old anchor working in the same change (FR-011).

## Competency pages: `/<category-slug>/<competency-slug>/`

- URLs unchanged (FR-011 needs no redirect).
- `## Further Information` lists every resource whose `competencies` names that
  competency, with type and description. Absent when there are none.
- Slugs come from `slugify()` in `gen_site.py`, as today.

## Moodle entry point

A "Library" item linking to `/library/`, in the site custom menu and the app main menu,
declared in `moodle/site/settings/learner-experience.yaml` (R7). Setting names are
confirmed in `MOODLE_502_STABLE` source before use.
