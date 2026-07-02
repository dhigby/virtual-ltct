# Module content package template

This folder is **not a real module** — `gen_coverage.py` only scans `modules/*/README.md`
files that start with real YAML frontmatter, and this file deliberately doesn't, so it's
invisible to `COVERAGE.md` and the coverage CI check. Copy the files below into a new
`modules/<slug>/` folder when starting a module and rename them to fit that module's
actual numbering.

## Why this exists

One module (`coretech-computer-hardware`) and one quiz
(`language-technology-overview-…/10-final-assessment-quiz.md`) ended up with a richer
structure than the rest of the repo — numbered lessons, a scenario bank, a mentor guide,
a quiz. This template turns that into the standard shape for every **content** module,
plus one artifact that didn't exist anywhere yet: a video script for the pre-recording
step before content gets uploaded into Cypher for Business.

## The package

| File | Purpose |
| --- | --- |
| `00-design.md` | Course design doc — learning objectives, module breakdown, assessment plan. Must be approved before content drafting. |
| `README.md` | Frontmatter (source of truth for competencies/outcome level/links) + learner-facing intro and a linked table of contents to the numbered files below. |
| `01-*.md`, `02-*.md`, … | Numbered lesson content, one file per lesson/topic (each ≤ 90 minutes, with estimated duration stated). |
| `NN-scenario-bank.md` | Applied practice scenarios for learners, sequenced foundational → complex. |
| `NN-mentor-guide.md` | Facilitator notes: what to look for in learner responses, how to give feedback, answer guidance for the scenario bank. |
| `NN-quiz.md` | Assessment questions with an explicit pass threshold and an answer key, in the body — not frontmatter. |
| `NN-video-script.md` | Script for the video-recording step (talking points, on-screen/slide cues, runtime estimate) before the module is transcribed into Cypher. |

`NN` continues the module's own numbering — keep files in the order a learner would
use them (design doc first, then content, scenario bank, mentor guide, quiz, video script), matching
the existing convention in `coretech-computer-hardware`.

## What a real module's `README.md` frontmatter looks like

```yaml
---
title: <Module title>
slug: <module-slug>
target_outcome_level: With Assistance      # or "Has knowledge" — outcome level, not workflow status
competencies:
  - <Competency Name>                       # must match competencies.yaml exactly
content_type: content                       # stub | content
external_links:
  materials: https://…                      # optional; e.g. a source Vimeo/Google Sites link
last_exported: 2026-06-18
---
```

None of the sub-files (`01-*.md`, `NN-scenario-bank.md`, etc.) carry their own
frontmatter — they inherit context from the module's `README.md`, matching the existing
pattern in `coretech-computer-hardware`. Don't add per-file frontmatter; it isn't read by
any tooling and would just be sprawl.

## Sub-modules

- [Design doc](00-design.md)
- [Lesson 1](01-content.md)
- [Scenario bank](02-scenario-bank.md)
- [Mentor guide](03-mentor-guide.md)
- [Quiz](04-quiz.md)
- [Video script](05-video-script.md)
