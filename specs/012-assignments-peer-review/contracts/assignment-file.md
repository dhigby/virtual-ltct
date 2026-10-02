# Contract: The assignment file

Read by `scripts/assignment_parse.py` and nothing else. Every other consumer imports that parser, as the quiz consumers import `quiz_parse.py`. The rules are **strict**: anything outside this grammar raises, and a raised file is withheld whole ([design D3](../design.md#d3-disclosure-fr-004-fr-016)). This contract takes effect only once the design decision is recorded as approved. Until then, any `*-assignment.md` fails CI ([research R8](../research.md#r8-where-the-design-gate-is-enforced-fr-001-sc-001)).

## Name

`modules/<slug>/NN-<topic>-assignment.md`

- `NN` is the two-digit position in the course, like every other numbered file.
- `-assignment.md` is the **last** suffix. `course_stage.is_lesson()` returns False for it.
- One assignment per file.

## Header block

The header comes directly under the H1, one line each. Order is free, and unknown keys are errors.

| Line | Values | Default | Required |
|---|---|---|---|
| `# Assignment: <title>` | the H1; the activity name | — | yes |
| `**Estimated time:** N minutes` | 1–90 | — | yes (as for lessons) |
| `**Completion:** …` | `optional` · `required` | `optional` (package check warns if absent) | no |
| `**Review:** …` | `mentor` · `peer` | `mentor` | no |
| `**Submit:** …` | `text`, `file`, or `text, file`; for `file`, `(up to N MB, .ext .ext)` | `text` | no |
| `**Offline:** …` | `yes` · `no` | `yes` | no |
| `**Peers:** N` | 1–5; only with `**Review:** peer` | `2` | no |

Checks:
- `file` without a size: the parser fails, so the brief always states the expected size (spec edge case: large attachments).
- `**Submit:** file` alone: the package check warns "a text answer is always acceptable where the task allows it".

## Body sections (H2, in this order)

| H2 | Visibility | Content |
|---|---|---|
| `## Brief` | learner | the task. For `**Review:** peer`, the publisher appends the standard "do not put your name in your submission" line (FR-012a). Authors don't write it. |
| `## What to submit` | learner | optional; expected output |
| `## Criteria` | learner | one `### <name> (<N> point[s])` per criterion; the body is the learner wording. 1–12 criteria. Names are unique and are the guide **shortname** (R7). |
| `## Mentor only: grading notes` | **mentor** | one `### <name>` per criterion, each name matching a `## Criteria` name exactly. A criterion without notes is allowed. A notes heading with no matching criterion is an error. |
| `## Mentor only: model answer` | **mentor** | optional; free markdown, images allowed (`assets/` as for lessons) |
| `## Mentor only[: …]` | **mentor** | any further mentor-only notes, repeatable |

Any other H2 is learner-visible and is appended to the brief (for example `## Before you start`).

## Disclosure rules (enforced through `scripts/disclosure.py`)

- A mentor-only block is `^## Mentor only\b.*$` up to the next H1 or H2.
- After stripping, any line matching the residue pattern means **withhold whole**:
  - an answer-key marker
  - `(correct)`
  - a heading whose text is *grading notes*, *model answer*, *marking notes* or *mentor notes* outside a mentor-only block
- `**Points**` totals are computed, never written. That leaves no place for a learner-visible "answer" to hide in arithmetic.

## Content rules (checked at stage 4 by `alignment-reviewer`, FR-014)

- No criterion, brief or model answer may require judging whether minority-language text is correct. What is assessed is the diagnosis of the tool, the data and the workflow.
- A real example the author lacks is a marked placeholder: `> **Example needed:** <what it must show>`. The package check counts these, as it counts outstanding screenshots.

## Parse result (the shape every consumer gets)

```python
{
  "source": "05-font-fallback-assignment.md",
  "title": "Diagnose a font fallback report",
  "minutes": 45,
  "completion": "required",     # optional | required
  "review": "mentor",           # mentor | peer
  "submit": {"text": True, "file": True, "max_mb": 5, "types": [".png", ".jpg", ".pdf"]},
  "offline": True,
  "peers": None,                # int when review == "peer"
  "brief_md": "...",            # Brief + What to submit + other learner H2s
  "criteria": [{"name": "...", "points": 4, "learner_md": "...", "notes_md": "..."}],
  "model_answer_md": "...",     # "" if none
  "mentor_extra_md": "...",     # other Mentor only blocks, concatenated
}
```

`python scripts/assignment_parse.py --check-all` is a CI gate over every assignment in `modules/`, the same as `quiz_parse.py --check-all`.
