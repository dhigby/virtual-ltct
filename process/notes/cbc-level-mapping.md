# How Our Self-Study Courses Map to the CBC Levels

*Shared reference for consistent course design — **draft for committee review**.*

## Why this exists

The CBC (competency framework) is **fixed** — we map our courses *to* it; we don't change it.
But we currently use **two different level vocabularies** that were never explicitly reconciled,
which is why courses disagree with themselves (a module's frontmatter and its content stating
different levels). This page defines the terms and proposes one consistent rule. **It requires no
change to the CBC.**

## The two vocabularies

| Where it lives | The scale |
| --- | --- |
| **CBC descriptors** (`competencies/*.md`, `outcome_levels`) | Learner → Advanced Beginner → Practitioner → Trainer/Proficient → Expert |
| **Course frontmatter** (`target_outcome_level`) | Has Knowledge · With Assistance |

The course scale is a *simplification* of the CBC ladder — so it needs an explicit correspondence
to the framework.

## Proposed correspondence

| Course level | CBC rung | What it means (observable) |
| --- | --- | --- |
| **Has Knowledge** | **Learner** | *Recognise and explain* — conceptual awareness. Assessed by explanation / self-assessment, not performance. |
| **With Assistance** | **Advanced Beginner** | *Attempt the task in the field with mentor support.* Assessed by doing it with help, mentor-reviewed. |
| *(not a self-study target)* | Practitioner / Trainer / Expert | Earned through real field experience + mentoring over time. |

## The mapping policy — the key rule

> **A self-study module, on its own, delivers *Has Knowledge / Learner*.** The higher rungs are, by
> the CBC's own definition, about *doing* — a module can scaffold that, but the rung is **earned
> through mentor-supported practice**, not by finishing the module.

In practice:

- **Default a self-study course to Has Knowledge** unless it builds in genuine mentored practice.
- A course claiming **With Assistance** must include the mentored-practice component (a real task
  done with a mentor who reviews it) — reading + a quiz alone doesn't get a learner there.
- Stop conflating *"the module's level"* with *"the learner's achieved rung"*: **the module
  teaches; the mentor certifies the doing.**

## Working glossary (so we mean the same thing)

- **Has Knowledge / With Assistance** — the two *course* levels (module frontmatter).
- **Learner / Advanced Beginner / …** — the CBC descriptor rungs (the framework's own progression).
- **Connect · Content · Challenge · Change** — the four lesson phases (Learning That Lasts):
  Connect activates prior knowledge; Content teaches the minimum needed; Challenge applies it to a
  real scenario; Change commits to action.
- **Assessment** — for Has-Knowledge courses, mentor-reviewed *reasoning* (plus quizzes), not a
  graded performance.
- **Mentor** — every trainee has one; mentors deliver the rungs a module can't reach on its own.

## For the committee to confirm

1. Is **Has Knowledge ↔ Learner / With Assistance ↔ Advanced Beginner** the intended correspondence,
   or does the CBC intend a different mapping?
2. Do we adopt the policy that **self-study delivers Has Knowledge; mentored practice delivers the
   higher rungs**?
3. If yes, existing courses get their `target_outcome_level` reconciled to match (fixing the current
   mismatches, e.g. CoreTech: Malware) — **no CBC change required.**

---

*Status: draft, prepared 2026-08-12 to support the outcome-levels committee. Refine the pedagogical
wording as needed; item 1 above is the open question the committee should settle.*
