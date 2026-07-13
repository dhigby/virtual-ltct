---
name: training-content
description: >-
  Pedagogy guide for authoring and revising Language Technology Consultant (LTC)
  training content in this repo, following the Learning That Lasts adult-learner
  framework (the 4Cs: Connect, Content, Challenge, Change). Invoke BEFORE drafting
  or revising any learner-facing lesson (01-*.md, 02-*.md, a module README body, or
  a scenario bank). Defines the instructional structure the whole curriculum follows
  so authors don't improvise a lecture-style info dump. Use whenever writing,
  converting a stub to content, or revising a lesson for a given competency and
  target outcome level.
---

# Training content: the Learning That Lasts 4Cs for LTC modules

This curriculum trains **Language Technology Consultants** — people who support Bible
translators and language workers in remote field settings (limited connectivity,
non-technical end users, resource constraints), *not* office IT staff. Every module must
teach toward what a consultant will actually have to **do in the field**.

Its instructional framework is **Learning That Lasts**, operationalized here as the
**4Cs: Connect → Content → Challenge → Change**. Author every lesson in that structure.
It is not a lecture that dumps information — it activates prior knowledge, teaches the
minimum needed, makes the learner apply it to a real scenario, then has them commit to
action. If you find yourself writing paragraphs of "here is everything about X" with no
Connect hook and no Challenge, stop and restructure into the 4Cs.

## Clarifying before writing

If the request is missing any of these, ask before writing:

- **Subject/topic**: What is being taught?
- **Target audience**: Who are the learners and what do they already know?
- **Learning objective**: What should learners be able to *do* after this lesson?

For pipeline courses all three come from the approved design doc (`00-design.md`) — read it
instead of asking. If the design doc is missing or unapproved, stop and say the design stage
(`course-designer`) must happen first.

## Ground everything in repo sources — never invent

Sources of truth, in order:

1. The course's `00-design.md` (the contract: which lessons, objectives, duration, SME
   field knowledge). If it's missing or unapproved, stop — the design step comes first.
2. The competency descriptor(s) under `competencies/<slug>.md` for every competency the
   module claims — rationale, target statement, observable criteria you must teach toward.
   See [`competencies/adult-education.md`](../../../competencies/adult-education.md).
3. The module's `README.md` frontmatter: `competencies`, `target_outcome_level`,
   `content_type`.
4. Worked examples to mirror (see **Exemplars** below).

Never invent field stories, tool facts, or learner struggles not present in the design doc,
the competency descriptor, or existing repo content.

## Output format (repo conventions)

- One markdown file per lesson, numbered in learner order: `01-<topic>.md`, `02-<topic>.md`,
  … Some short courses instead carry the whole thing in the module `README.md` body — either
  is fine; the 4Cs apply the same way.
- Numbered lesson files have **no YAML frontmatter** — module metadata lives in the module's
  `README.md` frontmatter (do not add/edit `status`, `priority`, `competencies`,
  `target_outcome_level`, or `content_type` while authoring a lesson).
- Every lesson and the scenario bank opens, right under the H1, with
  `**Estimated time:** X minutes`. No lesson exceeds **90 minutes** — this is enforced by CI
  (`scripts/check_course_package.py`) and the alignment reviewer. Default to ~60-minute
  lessons; split into another numbered lesson rather than exceeding 90.
- See [`references/markdown-styling.md`](references/markdown-styling.md) for formatting
  conventions.

## The 4Cs (author each lesson in this order)

Every lesson body follows **Connect → Content → Challenge → Change**, each as an `##` H2
section, in that order. `check_course_package.py` verifies these four H2 headings are present
and in order. The timings below are for a 60-minute lesson — scale them proportionally to the
lesson's `**Estimated time:**` header and make sure the phases add up to it.

- **Connect** *(~10 min)* — activate prior knowledge and surface the problem. A reflection
  prompt tied to the learner's own field context ("Think about the language you work with…").
  For reflection and activating experience *only* — do **not** put course overview, structure,
  or administrative content here (that belongs in an Introduction before the first `##
  Connect`).
- **Content** *(~25–30 min)* — deliver only the knowledge needed to *attempt* the task, then
  stop. Videos, concept explanations, worked distinctions, real-world examples. Vary modality
  (video, demonstration, reading) — adults have diverse styles. Close with a short **Key
  takeaways** list.
- **Challenge** *(~15–20 min)* — the learner applies the content to a **realistic field
  scenario** and produces something a mentor can review (a diagnosis, a plan, a 2–3 sentence
  recommendation). Scenarios use named people in real contexts with real constraints (power
  outages, humidity, connectivity). This is where learning becomes active; every lesson needs
  a genuine Challenge, not just recall questions.
- **Change** *(~5–10 min)* — the learner consolidates and commits: a self-assessment ("Can
  you explain… to a colleague?"), a reflection on one real person in their context, a concrete
  next step, and a preview of what comes next.

Adults learn by doing (per the Adult Education competency: "Uses participatory, hands-on
approaches"). Connect and Change bracket the lesson with the learner's own experience;
Challenge is the hands-on core. Assessment is a **mentor reviewing the learner's reasoning**
for systematic thinking, not an auto-graded "correct answer."

## Canonical lesson shape

Open each lesson with the H1, the duration header, an optional purpose/objectives preamble,
then the 4Cs as `##` sections — mirroring
[`modules/mastering-lt-resources/01-lingtran.md`](../../../modules/mastering-lt-resources/01-lingtran.md):

```markdown
# <Lesson Title>

**Estimated time:** X minutes

**Purpose:** <the real-world field task this lesson prepares the learner for>

## Learning objectives

- <observable outcome — a verb the learner can be seen doing, tracing to the design doc>
- <observable outcome>

## Connect
<reflection prompt tied to the learner's field context>

## Content
<minimum teaching needed; videos, concept explanations; end with Key takeaways>

## Challenge
<realistic field scenario + a task that produces something a mentor can review>

## Change
<self-assessment, reflection on a real person, concrete next step, preview of what's next>
```

Per-section time budgets must sum to the declared `**Estimated time:**`, and the lesson must
fit the duration cap in the design doc (≤90 min). If it can't, **split into another numbered
lesson** rather than overflowing.

## Callout labels and activity marker

Use these bold inline labels for callouts (start the callout with the bold label, give it 2–3
sentences or a short list) — they map cleanly onto LMS callout styles and read well as-is:

- **TIP** — practical advice, mentor sharing prompts, positive guidance
- **INFO** — download links, neutral background, competency notes
- **WARNING** — caution that doesn't require stopping
- **DANGER** — must-not-do instructions, critical safety
- **NOTE** — neutral observations, definitional notes, video transcripts

Mark learner activities and reflection prompts with a leading **✏️** (e.g. `**✏️ Try this:**`,
`**✏️ Reflection:**`), as the existing modules do.

## Mentor guidance and practice go in their own files

Do **not** put a "For Mentors" section inside lesson files. Facilitator and assessment
guidance (observable success indicators, common misconceptions, differentiation) lives in the
course's separate mentor guide (`NN-mentor-guide.md`, see
`modules/_template/03-mentor-guide.md`). Extended practice beyond the in-lesson Challenge
lives in the scenario bank (`NN-scenario-bank.md`). The quiz (`NN-quiz.md`) is separate too.
Reference these files; don't absorb them into the lesson.

## Target outcome levels (write to the declared level, don't drift)

Match the module's `target_outcome_level` frontmatter — it comes from the competency framework
and calibrates depth:

| Level | Meaning |
|---|---|
| `Has knowledge` | Conceptual awareness — explain and recognize; Challenge and Change use explanation and self-assessment, not performance. |
| `With Assistance` | The learner *attempts the task in the field with mentor support*; Challenge requires applying a framework to a scenario and producing a plan a mentor reviews. Build in reaching for help. |

State the observable outcome a lesson prepares the learner for *before* teaching the content,
matched to this level. If the content can't fit the declared level, say so and ask — do **not**
silently change the frontmatter to match (that's a human decision).

## Multi-lesson courses

1. **Number lessons sequentially** in the order a learner works through them.
2. **Create clear progression**: each lesson builds on previous ones.
3. **Link lessons**: reference previous content in Connect, preview upcoming material in Change.
4. **One file per lesson**, named descriptively (e.g., `01-lingtran.md`).
5. **Maintain consistent structure and formatting** across all lessons.

## Competency names

Any competency you name or discuss must match
[`competencies.yaml`](../../../competencies.yaml) **exactly** — including `&` and
capitalization. Copy verbatim; never paraphrase. A mismatch is a silent coverage miss and a
hard CI failure.

## Content quality standards

Ensure all content: respects adult learners (assumes competence, explains "why"); uses clear
language (defines technical terms); includes concrete field scenarios (remote locations,
limited connectivity, non-technical end users); provides actionable guidance; matches the
module's `target_outcome_level`; and follows accessibility principles (clear structure,
descriptive headings, logical flow). Do not leave `[bracketed]` placeholder text in the final
output.

## Anti-patterns (reject these)

- A wall of explanatory prose with no Connect hook and no Challenge — the default failure mode.
  Restructure into the 4Cs.
- "Understand / know / be aware of" objectives — reframe as observable actions.
- Administrative/overview content inside **Connect** — that belongs in an Introduction before
  the first `## Connect`; Connect is reflection only.
- Invented field stories, tool specifics, or learner struggles not grounded in the design doc,
  competency descriptor, or existing repo content.
- Office/enterprise-IT framing — this audience supports non-technical language workers in the
  field.
- Editing `competencies:`, `target_outcome_level`, or `content_type` frontmatter, or
  hand-editing `COVERAGE.md`, to make content "fit." Out of scope for authoring.

## Exemplars

- **Cleanest H2 4Cs numbered-lesson course to mirror:**
  [`modules/mastering-lt-resources/`](../../../modules/mastering-lt-resources/) — H1 +
  `**Estimated time:**` + `## Connect/Content/Challenge/Change`.
- [`modules/language-technology-overview-which-tools-for-what-tasks-and-when/`](../../../modules/language-technology-overview-which-tools-for-what-tasks-and-when/)
  — a nine-lesson course with time budgets. Its earlier lessons use an older `###` H3 form for
  the 4Cs; **author new content with `##` H2 headings** (what CI checks).
- [`modules/coretech-computer-hardware/`](../../../modules/coretech-computer-hardware/)
  predates the 4Cs and uses a "Section 1.1 / 1.2" structure. Mine it for scenario quality and
  field grounding, but structure new content in the 4Cs.

## Backfilled / retro-fit courses are grandfathered

Faithful imports of already-delivered (Cypher/Google Sites/PDF) courses — see
[`process/backfill.md`](../../../process/backfill.md) — are **grandfathered**: reproduce the
delivered structure as-is, do **not** restructure it into the 4Cs. A retro-fit course whose
`00-design.md` records a retro-fit design status is exempt from the 4Cs phase-heading check
(it still needs valid `**Estimated time:**` headers and, if present, a well-formed quiz). Only
when such a course is *substantially revised* does it adopt the 4Cs. Use the
`content-backfiller` agent for imports, not this authoring guide.
