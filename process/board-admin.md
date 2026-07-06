# Board administration (maintainer only)

The GitHub Project board ("LTC Training Modules", project #1, owner `dhigby`) is
user-owned, so only the Maintainer (or someone granted project admin) can change its
fields. This page covers the **one-time status expansion** the new process needs.

## One-time: expand the Module Status field — ✅ DONE (2026-07-06)

> The board now carries all eight options
> (`Not started · Design · Drafting · SME Check · Internal Review · Pilot · Publishing · Online`),
> and the five courses that were `In progress` were preserved as `Drafting`. The steps below
> are kept for reference / a from-scratch rebuild — you shouldn't need to run them again.

The board shipped with four status options
(`Not started · In progress · Internal Review · Online`). The pipeline needs eight. Do this
**in the Projects web UI** — it's safer than the API (see the warning below).

1. Open the board → **⋯ → Settings → Module Status** field.
2. **Rename** `In progress` → `Drafting`. Renaming preserves the option's internal ID, so
   every course already set to "In progress" keeps its value.
3. **Add** four new options: `Design`, `SME Check`, `Pilot`, `Publishing`.
4. **Reorder** the options to match the pipeline:

   ```
   Not started · Design · Drafting · SME Check · Internal Review · Pilot · Publishing · Online
   ```

Leave `Not started`, `Internal Review`, and `Online` untouched.

After this, the code that references the vocabulary is already updated to match:
`scripts/bootstrap_github.py` (for a from-scratch rebuild), `.claude/agents/migration-reconciler.md`,
`CLAUDE.md`, and `process/PROCESS.md`.

## Why the UI, not the API

You *can* script this with the GraphQL `updateProjectV2Field` mutation, but it replaces the
entire option list at once. **If you send a new option list without each existing option's
ID, GitHub treats them as brand-new options and wipes the value from every item that used an
old one.** The UI's rename-in-place avoids that entirely. Only script it if you're prepared
to fetch and re-supply every existing option ID.

For reference / verification only (read-only):

```bash
gh auth refresh -s project          # needed once for any project write
gh project field-list 1 --owner dhigby --format json
gh project item-list 1 --owner dhigby --format json
```

## Granting team members board access

Team members can't move a course's status unless they have write access to the project.
Grant it under the board's **⋯ → Settings → Manage access**. Without it, they can still run
`/next-step` (it degrades to repo evidence) but can't advance the board.

## Labels — ✅ created (2026-07-06)

- `course-production` — applied by the [course-production issue template](../.github/ISSUE_TEMPLATE/course-production.yml).
- `backfill` — for [backfill](backfill.md) PRs.

(For a from-scratch rebuild:
`gh label create course-production --repo dhigby/virtual-ltct --color 0e8a16 --description "Course production tracker"`
and
`gh label create backfill --repo dhigby/virtual-ltct --color fbca04 --description "Legacy content backfill from Cypher"`.)
