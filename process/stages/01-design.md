# Stage 1 — Design

**Board status:** `Design` · **Who:** Author · **Tool:** `course-designer` agent

The design document is the **first artifact** for any course and becomes a binding
contract for everything downstream. Nothing gets drafted until it's approved (stage 2).

## Entry criteria

- A Course production tracker issue exists for this course and is on the board.
- You know which competency(ies) the course addresses (copy names **verbatim** from
  [`competencies.yaml`](../../competencies.yaml)) and the target outcome level
  (`Has knowledge` or `With Assistance`).

## How

In Claude Code, from the repo root:

> Use the **course-designer** agent to produce `modules/<slug>/00-design.md` for a course
> on _<topic>_, addressing the competencies _<names, verbatim>_ at the _<outcome level>_
> outcome level.

The agent will:

1. Read the competency descriptor(s) under [`competencies/`](../../competencies/) and lift
   learning objectives from their observable criteria / activity ladders.
2. **Interview you (the SME).** Answer its questions about real field cases, common learner
   mistakes, what "good" looks like at the target level, and tool-version specifics. If you
   have the field knowledge yourself, supply it directly; otherwise gather it from a
   colleague SME before running the agent. **Do not let it invent field stories** — every
   story in the doc must be real.
3. Fill in the objectives table, module breakdown (each lesson ≤ 90 minutes), and
   assessment plan, following [`modules/_template/00-design.md`](../../modules/_template/00-design.md).

## Exit criteria

- `modules/<slug>/00-design.md` exists and is complete: every objective traces to a
  descriptor criterion **and** to an assessment item; every lesson is ≤ 90 minutes; every
  competency name matches `competencies.yaml` exactly.
- The `**Design status:**` line still reads `Draft` (approval is the next stage).
- Open a PR with the design doc so the approver can review it.

## Then

- ✅ Tick **"1. Design doc drafted"** on the tracker issue.
- Board status stays `Design`.
- Go to [Stage 2 — Design approval](02-approve.md).
