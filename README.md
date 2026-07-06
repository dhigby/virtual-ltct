# Virtual LTC — Training Modules

Collaborative home for the **Language Technology Consultant (LTC) training curriculum**.
Each module trains consultants toward a fixed [competency framework](competencies.yaml)
(42 competencies across Core Technical, Technology Domain, Core, Professional, Education).

Migrated from a Notion database on 2026-06-18. Content now lives here as markdown;
progress is tracked on the GitHub Project board.

## Where things live

| What | Where |
| --- | --- |
| **Course content** | [`modules/<slug>/`](modules/) — one folder per course, plain markdown |
| **How we build courses** | [`process/PROCESS.md`](process/PROCESS.md) — the 8-stage production pipeline, roles, and agents |
| **New to the team?** | [`ONBOARDING.md`](ONBOARDING.md) — setup and your first contribution |
| **Progress tracking** | The **LTC Training Modules** GitHub Project (board) — status, priority, owner |
| **Coverage roadmap** | [`ROADMAP.md`](ROADMAP.md) — which gap competencies we're committing to close |
| **Legacy content backfill** | [`BACKFILL.md`](BACKFILL.md) — importing Cypher-delivered courses into the repo |
| **Competency coverage / gaps** | [`COVERAGE.md`](COVERAGE.md) — auto-generated; shows which competencies still have no course |
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

Team members build courses with **Claude Code** — start at [`ONBOARDING.md`](ONBOARDING.md),
then run `/next-step <course-slug>` to see what to do next. The full method is in
[`process/PROCESS.md`](process/PROCESS.md); editing mechanics are in
[CONTRIBUTING.md](CONTRIBUTING.md) (small fixes can still be made in the browser).

## Maintainer scripts (`scripts/`)

- `export_from_notion.py` — one-time/repeatable export from the Notion DB (idempotent;
  won't overwrite content authored here).
- `gen_coverage.py` — regenerates `COVERAGE.md` (also run automatically by CI).
- `bootstrap_github.py` — creates the labels, issues, and Project fields from the export.
