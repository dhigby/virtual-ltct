# Competency Descriptors

One markdown file per framework competency. These are the **authoritative, hand-authored**
descriptions of each competency — rationale, target statement, and either a per-level
activity ladder or sub-competencies with observable criteria.

- **Edit these files directly.** [`competencies.yaml`](../competencies.yaml) remains the
  canonical *name list*; workflow state (status, priority, assignee) stays on the GitHub
  Project. A frontmatter `name:` must match `competencies.yaml` exactly, or CI fails
  (`scripts/check_competency_descriptors.py`).
- **Keep the whole frontmatter block.** Two keys are required and CI rejects the file
  without them: `name:` (must match `competencies.yaml` verbatim) and `category:`.
- **`slug:` is a warning, not an error.** The page's URL comes from the *filename*, so a
  missing `slug:` changes nothing — `gen_site.py` falls back to the filename. It is worth
  keeping accurate anyway: when `slug:` and the filename disagree, the check says so,
  which is the signal that a rename has moved the published page and will break links
  people already have. Renaming a file is the way to shorten a URL; update `slug:` to
  match when you do.
- **Start from the file that's in `main`,** not from an older local copy. Edits are made
  in place, so pasting a whole file over the top silently reverts anyone else's fixes to
  it. If you keep descriptors outside the repo, re-copy from `main` before each round.
- **Open a pull request.** The sync check runs on every PR touching `competencies/**`, so
  a broken key is caught before it reaches `main` rather than after.
- **A competency's URL is a citation — don't move a page silently.** The published URL is
  `/<category>/<descriptor filename>/`, and the CBC program cites these as reference
  points. Three things move a page: renaming a file here, moving a competency to a
  different category in [`competencies.yaml`](../competencies.yaml), or renaming a
  category key there — the last moves every page in that category at once. If you do any
  of them, add the old path to `redirect_maps` in [`mkdocs.yml`](../mkdocs.yml) in the
  same change, so the old link keeps resolving. Note the frontmatter `category:` is
  display metadata only; the URL follows `competencies.yaml`.
- **Browse the rendered site:** <https://dhigby.github.io/virtual-ltct/> — the published
  version of everything here, grouped by category with search.
- **Resources live in [`resources.yaml`](../resources.yaml), not in these files.** Each
  entry there names the competencies it serves, and `gen_site.py` renders it into the
  site-wide **Library** page and into each competency page's **Further Information**
  section. A descriptor must not carry a `resources:` key; the sync check fails if one
  does. To add, drop or fix a link, edit `resources.yaml`:

  ```yaml
  - title: Keyman
    url: https://keyman.com/
    description: One line on what it is and when to use it.
    type: site            # guide | video | site | document
    competencies:
    - Keyboards           # verbatim framework names
    language: English
  ```

  The site is public, so list public material only.
- The files were first seeded from the source documents in
  [`../import-seeds/`](../import-seeds/) (a spreadsheet and the CBC guide); that importer is
  retained for provenance only and is no longer the editing surface.
