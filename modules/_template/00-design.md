# Course Design Document

> **Before proceeding:** Copy this file into your new course folder as `00-design.md`. Content drafting must not begin until this document is approved by a human reviewer. This is stage 1 of the [production pipeline](../../process/PROCESS.md) — see [`process/stages/01-design.md`](../../process/stages/01-design.md) and [`02-approve.md`](../../process/stages/02-approve.md).

## Course overview

| Item | Description |
| --- | --- |
| **Title** | [Your course title] |
| **Competencies addressed** | [List verbatim competency names from `competencies.yaml`, one per line] |
| **Target outcome level** | [Has knowledge \| With Assistance] |
| **SME(s) consulted** | [Name(s) and affiliation] |
| **Design status** | Draft |
<!-- On approval (stage 2), the Design Approver replaces the line above with:
     | **Design status** | Approved by <name> on <YYYY-MM-DD> | -->


## Learning objectives

One row per objective. The `Source` column names the specific criterion or activity ladder level from the matching `competencies/<slug>.md` descriptor that this objective draws from.

| # | Objective | Source | Assessed by |
| --- | --- | --- | --- |
| 1 | [Learner can …] | [Competency name, section/criterion] | [Quiz section or scenario #] |
| 2 | [Learner can …] | [Competency name, section/criterion] | [Quiz section or scenario #] |

## Module breakdown

One row per planned numbered lesson file. Each row stays ≤ 90 minutes. The total must sum to the "Assessment plan" estimate below.

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-….md` | [Topic] | [#, #] | XX |
| `02-….md` | [Topic] | [#] | XX |
| `NN-scenario-bank.md` | [Applied practice] | [#, #] | XX |
| `NN-mentor-guide.md` | [Facilitator notes] | — | — |
| `NN-quiz.md` | [Assessment] | [#, #] | XX |
| **Total learner seat time** | | | **≤ 270** |

## Assessment plan

[2-3 sentences describing:] How many quiz questions, what pass threshold (default 80%), and which objectives the scenario bank exercises vs. the quiz. Example: "A 15-question quiz (80% = 12/15 to pass) covers objectives 1–5; scenarios 1–3 target objectives 6–8."

## SME knowledge notes

[Record here the real field cases, stories, and domain knowledge from your SME interview — the specifics that feed the scenario bank and ground the lesson content in consultant reality. Include tool-version specifics if relevant. Do not invent field stories; record what the SME actually said.]

---

### How this document works

1. **Read** the competency descriptor(s) under `competencies/` for each competency listed above. Lift your learning objectives from their observable criteria and activity ladders, not from thin air.
2. **Interview the SME** before drafting objectives or module breakdown. Ask: real field cases they've seen, what learners most commonly get wrong, what "good" looks like at your target outcome level, any tool-version specifics. Record answers in the SME knowledge notes section.
3. **Enforce the 90-minute rule.** Each numbered module file (01-, 02-, …) has a documented estimated duration, stated at the top as `**Estimated time:** X minutes`, and must not exceed 90 minutes. If your content can't fit, split it into another file rather than overflowing — update this table and the `module-author` will implement it.
4. **Align objectives with assessment.** Every objective in the table above must map to at least one quiz question or scenario. Every quiz question and scenario must trace back to an objective — no orphan assessments.
5. **Outcome-level verb choice.** "Has knowledge" objectives use recognize/explain language; "With Assistance" objectives use perform/configure language.
6. **Hand off.** Once approved, this document becomes a contract: the `module-author` and `quiz-writer` agents will draft content and assessment to fulfill it, not freelance or add unstated competencies.
