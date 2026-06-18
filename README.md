# Virtual LTC — Training Modules

Collaborative home for the **Language Technology Consultant (LTC) training curriculum**.
Each module trains consultants toward a fixed [competency framework](competencies.yaml)
(42 competencies across Core Technical, Technology Domain, Core, Professional, Education).

Migrated from a Notion database on 2026-06-18. Content now lives here as markdown;
progress is tracked on the GitHub Project board.

## Where things live

| What | Where |
| --- | --- |
| **Module content** | [`modules/<slug>/README.md`](modules/) — one folder per module, plain markdown |
| **Progress tracking** | The **LTC Training Modules** GitHub Project (board) — status, priority, owner |
| **Competency coverage / gaps** | [`COVERAGE.md`](COVERAGE.md) — auto-generated; shows which competencies still have no module |
| **The framework** | [`competencies.yaml`](competencies.yaml) — the 42 competencies (source of truth) |

## How tracking maps from the old Notion table

| Notion | Here |
| --- | --- |
| Modules table | GitHub Project, one **issue** per module |
| Status | Project **Module Status** field |
| Priority / Consultant Tier / Target Outcome Level | Project fields |
| Person | Issue **assignee** |
| Competencies relation | Issue **labels** (`competency: …`) + each module's frontmatter |
| Coverage/gap rollup | [`COVERAGE.md`](COVERAGE.md) (regenerated automatically on every change) |

**Source-of-truth split:** a module's competencies, tier, links, etc. live in its
markdown **frontmatter**; its live workflow state (status, priority, who's working on it)
lives on the **Project board**. This keeps the two from drifting.

## Module states

- **content** — the teaching material is authored here in the repo.
- **stub** — the module still points to external material (Google Sites, a PDF, Vimeo);
  the goal is to gradually author real content here. See the banner in each stub.

## Contributing

You do **not** need to use Git on the command line. See [CONTRIBUTING.md](CONTRIBUTING.md)
for editing in the browser or with GitHub Desktop.

## Maintainer scripts (`scripts/`)

- `export_from_notion.py` — one-time/repeatable export from the Notion DB (idempotent;
  won't overwrite content authored here).
- `gen_coverage.py` — regenerates `COVERAGE.md` (also run automatically by CI).
- `bootstrap_github.py` — creates the labels, issues, and Project fields from the export.
