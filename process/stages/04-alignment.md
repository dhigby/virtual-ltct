# Stage 4 — Alignment check

**Board status:** `Drafting` · **Who:** Author · **Tool:** `alignment-reviewer` agent

A mechanical, read-only check that the finished package matches its design doc and follows
house conventions — before a human reviewer spends time on it. This does **not** verify
technical accuracy (that's stage 5).

## Entry criteria

- All package files from [stage 3](03-draft.md) are drafted.

## How

> Use the **alignment-reviewer** agent to validate `modules/<slug>/` against its
> `00-design.md`.

It returns a pass/fail checklist covering:

- **Objective coverage** — every design objective appears in at least one lesson.
- **Assessment traceability** — every "Assessed by" mapping holds; no orphan questions or
  scenarios.
- **Durations** — every lesson + scenario bank has `**Estimated time:**`, none over 90, and
  the total matches the design doc.
- **Quiz format** — sections, pass threshold, mixed question types, answer key.
- **Competency names** — verbatim match to `competencies.yaml`.

## Exit criteria

- The checklist is **all ✓**. Any ✗ sends you back to [stage 3](03-draft.md) to fix, then
  re-run.
- Paste the final all-✓ table as a **comment on the tracker issue** (it's the evidence the
  internal reviewer relies on).

## Then

- ✅ Tick **"4. Alignment check passed"** on the tracker issue.
- Board status stays `Drafting`.
- Go to [Stage 5 — SME fact-check](05-sme-factcheck.md).
