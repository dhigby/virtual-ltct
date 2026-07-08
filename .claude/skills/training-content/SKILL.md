---
name: training-content
description: >-
  Pedagogy guide for authoring and revising Language Technology Consultant (LTC)
  training modules in this repo, following the Learning That Lasts adult-learner
  framework (the 4Cs: Connect, Content, Challenge, Change). Invoke BEFORE drafting
  or revising any learner-facing lesson content (01-*.md, 02-*.md, a module README
  body, or a scenario bank). Defines the instructional structure the whole
  curriculum follows so authors don't improvise a lecture-style info dump. Use
  whenever writing, converting a stub to content, or revising a lesson for a given
  competency and target outcome level.
---

# Training content: the Learning That Lasts 4Cs for LTC modules

This curriculum trains **Language Technology Consultants** — people who support Bible
translators and language workers in remote field settings (limited connectivity,
non-technical end users, resource constraints), *not* office IT staff. Every module
must teach toward what a consultant will actually have to **do in the field**.

Its instructional framework is **Learning That Lasts**, operationalized here as the
**4Cs: Connect → Content → Challenge → Change**. Author every lesson in that structure.
It is not a lecture that dumps information — it activates prior knowledge, teaches the
minimum needed, makes the learner apply it to a real scenario, then has them commit to
action. If you find yourself writing paragraphs of "here is everything about X" with no
Connect hook and no Challenge, stop and restructure into the 4Cs.

This skill is the **first step of a pipeline**: the markdown you author here is later run
through the `cypher-html-conversion` skill to produce CYPHER LMS HTML. Author
conversion-friendly markdown — the 4Cs headings, metadata block, callout labels, and
bridging note below map 1:1 onto what that skill consumes, so a clean draft converts
without rework.

## Ground everything in repo sources — never invent

Sources of truth, in order:

1. The course's `00-design.md` (the contract: which modules, objectives, duration, SME
   field knowledge). If it's missing or unapproved, stop — the `course-designer` step
   comes first.
2. The competency descriptor(s) under `competencies/<slug>.md` for every competency the
   module claims — rationale, target statement, observable criteria you must teach toward.
   See [`competencies/adult-education.md`](../../../competencies/adult-education.md).
3. The module's `README.md` frontmatter: `competencies`, `target_outcome_level`,
   `content_type`.
4. Worked examples to mirror:
   [`coretech-why-keboards-matter/README.md`](../../../modules/coretech-why-keboards-matter/README.md)
   (cleanest single-module 4Cs) and the nine modules under
   [`language-technology-overview-…`](../../../modules/language-technology-overview-which-tools-for-what-tasks-and-when/)
   (4Cs with time budgets).

Never invent field stories, tool facts, or learner struggles not present in the design
doc, the competency descriptor, or existing repo content.

## The 4Cs (author each lesson in this order, with per-section time budgets)

- **Connect** *(short, ~5 min)* — activate prior knowledge and surface the problem. A
  reflection prompt tied to the learner's own field context ("Think about the language you
  work with… try to type a word from it on a standard keyboard"). This is for reflection
  and activating experience *only* — do **not** put course overview, structure, or
  administrative content here (that goes in an Introduction before the first divider).
- **Content** *(the teaching core)* — deliver only the knowledge needed to *attempt* the
  task, then stop. Videos, concept explanations, worked distinctions. Begin the Content
  section with a short *italic bridging note* connecting this module to the broader LTC
  program. Vary modality (video, demonstration, reading) — adults have diverse styles.
- **Challenge** *(~10–30 min)* — the learner applies the content to a **realistic field
  scenario** and produces something a mentor can review (a diagnosis, a plan, a 2–3
  sentence recommendation). Scenarios use named people in real contexts with real
  constraints (power outages, humidity, connectivity). This is where learning becomes
  active; every module needs a genuine Challenge, not just recall questions.
- **Change** *(~5–10 min)* — the learner consolidates and commits to action: a
  self-assessment ("Can you explain… to a colleague?"), a reflection on one real person in
  their context, and a concrete next step. Point forward to what comes next.

Adults learn by doing (per the Adult Education competency: "Uses participatory, hands-on
approaches"). Connect and Change bracket the lesson with the learner's own experience;
Challenge is the hands-on core. Assessment is a **mentor reviewing the learner's
reasoning** for systematic thinking, not an auto-graded "correct answer."

## Canonical lesson shape

Open every module with this exact metadata block and objectives, then the 4Cs separated
by `---` dividers (mirrors [coretech-why-keboards-matter](../../../modules/coretech-why-keboards-matter/README.md)):

```markdown
# Module N: <Title>

**Target Audience:** Trainee Language Technology Consultants
**Duration:** X minutes
**Level:** Has Knowledge                 ← must match the module's target_outcome_level
**Framework:** Learning That Lasts (Connect, Content, Challenge, Change)

### Learning Objectives
By the end of this module, you will be able to:
- <observable outcome — a verb the learner can be seen doing>
- <observable outcome>

---
### Connect
<reflection prompt tied to the learner's field context>

---
### Content
*<italic bridging note connecting this module to the broader LTC program>*
<minimum teaching needed; videos, concept explanations>

---
### Challenge
<realistic field scenario + a task that produces something a mentor can review>

---
### Change
<self-assessment, reflection on a real person, concrete next step>

---
### What's Next
*<pointer to the quiz and following modules>*
```

- Per-section time budgets must sum to the declared `Duration`, and the module must fit the
  duration cap in the design doc (typically 90 min). If it can't, recommend **splitting into
  another module** rather than overflowing.
- Practice at scale (a **scenario bank**) and mentor assessment criteria (a **mentor
  guide**) live in separate files owned by other agents or a human — reference them, don't
  absorb them into the lesson. The quiz is separate too.

## Callout labels and activity marker (keep them CYPHER-ready)

Use these bold inline labels so the `cypher-html-conversion` step maps them to the right
callout CSS — start the callout with the bold label, give it 2–3 sentences or a short list:

- **TIP** — practical advice, mentor sharing prompts, positive guidance
- **INFO** — download links, neutral background, competency notes
- **WARNING** — caution that doesn't require stopping
- **DANGER** — must-not-do instructions, critical safety
- **NOTE** — neutral observations, definitional notes, video transcripts

Mark learner activities and reflection prompts with a leading **✏️** (e.g.
`**✏️ Try this:**`, `**✏️ Reflection:**`), as the existing modules do.

## Target outcome levels (write to the declared level, don't drift)

The frontmatter `target_outcome_level` (echoed in the metadata **Level** line) sets how far
the content goes:

- **"Has knowledge"** — conceptual. The learner should explain and recognize. Challenge and
  Change use explanation and self-assessment, not performance.
- **"With Assistance"** — the learner should *attempt the task with support*. Challenge
  requires applying a framework to a scenario and producing a plan a mentor reviews; build
  in reaching for help.

If the content can't fit the declared level, say so and ask — do **not** silently change
the frontmatter to match (that's a human decision).

## Competency names

Any competency you name or discuss must match [`competencies.yaml`](../../../competencies.yaml)
**exactly** — including `&` and capitalization. Copy verbatim; never paraphrase. A mismatch
is a silent coverage miss and a hard CI failure.

## Anti-patterns (reject these)

- A wall of explanatory prose with no Connect hook and no Challenge — the default failure
  mode. Restructure into the 4Cs.
- "Understand / know / be aware of" objectives — reframe as observable actions.
- Administrative/overview content inside **Connect** — that belongs in an Introduction
  before the first divider; Connect is reflection only.
- Invented field stories, tool specifics, or learner struggles not grounded in the design
  doc, competency descriptor, or existing repo content.
- Office/enterprise-IT framing — this audience supports non-technical language workers in
  the field.
- Editing `competencies:`, `target_outcome_level`, or `content_type` frontmatter, or
  hand-editing `COVERAGE.md`, to make content "fit." Out of scope for authoring.

## Note on the older exemplar

[`modules/coretech-computer-hardware/`](../../../modules/coretech-computer-hardware/) is
cited by the `module-author` agent as the fullest worked example, and it is a rich source
for realistic field scenarios and a systematic-diagnosis framework. But it predates the 4Cs
convention and uses a "Section 1.1 / Section 1.2" structure. Mine it for scenario quality
and field grounding — but structure new content in the **4Cs**, matching the newer modules.
