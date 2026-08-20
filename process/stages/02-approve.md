# Stage 2 — Design approval

**Board status:** `Design` · **Who:** Design Approver (**not** the Author) · **Tool:** human review

This gate has never existed before — it's the single most important addition to the
process. Approving a design is a real review, not a rubber stamp: once approved, the
authoring agents will build exactly what the doc specifies and nothing else.

## Entry criteria

- `00-design.md` exists and its `**Design status:**` line reads `Draft`.
- A PR (or the tracker issue) points you at the design doc.
- You are **not** the person who wrote it.

## How

Read `modules/<slug>/00-design.md` and check:

1. **Objective traceability.** Every learning objective names a real source criterion from
   a `competencies/<slug>.md` descriptor — spot-check two against the descriptor. Reject
   objectives invented from thin air.
2. **Outcome-level fit.** Verbs follow the CBC level: `1 - Has Knowledge` uses
   recognize/explain; `2 - With Assistance` uses perform/configure/diagnose;
   `3 - Independent` uses unaided-judgment verbs (resolve, recover from); `4 - Expert` uses
   teaching verbs. Flag mismatches. Check the objectives were lifted from the descriptor row
   **one level below** the target — the row whose activities *reach* it.
3. **Time budget.** Each lesson row is ≤ 90 minutes and the totals are believable.
4. **Assessment coverage.** Every objective maps to at least one quiz question or scenario,
   and vice versa — no orphan assessments.
5. **SME grounding.** The SME knowledge notes section contains real field detail, not
   placeholders.
6. **Competency names** match `competencies.yaml` verbatim.

**To approve:** in the same PR, edit the design doc's status line to:

```
| **Design status** | Approved by <your name> on <YYYY-MM-DD> |
```

Then approve/merge the PR.

**To reject:** leave review comments on the PR describing what must change and send it back
to the Author. Board status stays `Design`; nothing advances.

## Exit criteria

- `00-design.md` contains `Approved by <name> on <date>` (this is what `/next-step` and the
  package check look for).
- The design PR is merged.

## Then

- ✅ Tick **"2. Design approved"** on the tracker issue.
- Board status stays `Design` until drafting starts, then move to `Drafting`.
- Go to [Stage 3 — Draft](03-draft.md).
