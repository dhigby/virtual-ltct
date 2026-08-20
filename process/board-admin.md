# Board administration (maintainer only)

The GitHub Project board ("LTC Training Modules", project #1, owner `dhigby`) is
user-owned, so only the Maintainer (or someone granted project admin) can change its
fields. This page covers the **one-time field migrations** the process has needed.

## One-time: adopt the CBC level scale — ⬜ TODO

The repo moved to the CBC scale (see [`outcome-levels.yaml`](../outcome-levels.yaml)); the
board still carries the old vocabulary. Do this **in the Projects web UI**, and **rename**
rather than replace — see the warning below.

**Target Outcome Level** — 30 items, of which 13 read `With Assistance`, 11 read
`Has knowledge`, and 6 are unset:

1. Open the board → **⋯ → Settings → Target Outcome Level**.
2. **Rename** `Has knowledge` → `1 - Has Knowledge`. Renaming preserves the option's ID, so
   all 11 items keep their value.
3. **Rename** `With Assistance` → `2 - With Assistance` (13 items preserved).
4. **Add** two new options: `3 - Independent` and `4 - Expert`.
5. Leave the order as `1 · 2 · 3 · 4`. There is deliberately no `0 - No Competency` option:
   the field records where a learner *lands*, and no course leaves someone at No Competency.

**Consultant Tier** — **delete the field.** It was set on 0 of 30 items, and the concept
doesn't survive contact with CBC: the growth-plan levels (LT Specialist L1/L2, LT Consultant
L1/L2, Senior LT Consultant L3) are earned by accumulating points across whole competency
categories — see the "Milestone Scoring" sheet in `import-seeds/Lang Tech Competencies.xlsx`
— and competencies are electives, so one course legitimately serves an LT Specialist 1 and an
LT Consultant 2 alike. Nothing in the repo references it any more.

Verify afterwards:

```bash
gh project item-list 1 --owner dhigby --format json   # 24 items still carry a level; no Consultant Tier
```

Then flip this heading to `✅ DONE (<date>)`.

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
