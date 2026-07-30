# Competency Descriptors

One markdown file per framework competency. These are the **authoritative, hand-authored**
descriptions of each competency — rationale, target statement, and either a per-level
activity ladder or sub-competencies with observable criteria.

- **Edit these files directly.** [`competencies.yaml`](../competencies.yaml) remains the
  canonical *name list*; workflow state (status, priority, assignee) stays on the GitHub
  Project. A frontmatter `name:` must match `competencies.yaml` exactly, or CI fails
  (`scripts/check_competency_descriptors.py`).
- **Browse the rendered site:** <https://dhigby.github.io/virtual-ltct/> — the published
  version of everything here, grouped by category with search.
- **`resources:` is the reading list**, hand-maintained here and rendered as each page's
  **Further Information** section. One entry per link, `title` then `url`; use `[]` when a
  competency has none:

  ```yaml
  resources:
    - title: Keyman
      url: https://keyman.com/
  ```

  These were originally a single pointer to the competency's page on lingtransoft.info; the
  links that page listed are now recorded here directly, so this repo is the source of
  truth. Add, drop, or fix a link by editing the list — nothing re-syncs from upstream.
- The files were first seeded from the source documents in
  [`../import-seeds/`](../import-seeds/) (a spreadsheet and the CBC guide); that importer is
  retained for provenance only and is no longer the editing surface.
