---
name: training-content
description: Adult-learner training/workshop content in the Learning That Lasts framework, following this repo's lesson conventions.
---

# Training Content Creation (Learning That Lasts)

Create lesson content for the LTC training curriculum following adult learning
principles and the **Learning That Lasts** instructional framework. This is the house
pedagogy for every content module in this repo — don't improvise a different
instructional style.

## Clarifying before writing

If the request is missing any of these, ask before writing:

- **Subject/topic**: What is being taught?
- **Target audience**: Who are the learners and what do they already know?
- **Learning objective**: What should learners be able to do after this lesson?

For pipeline courses all three come from the approved design doc (`00-design.md`) —
read it instead of asking. If the design doc is missing or unapproved, stop and say the
design stage must happen first.

## Output format (repo conventions)

- One markdown file per lesson, numbered in learner order: `01-<topic>.md`,
  `02-<topic>.md`, …
- Lesson files have **no YAML frontmatter** — module metadata lives in the module's
  `README.md` frontmatter.
- Every lesson opens, right under the H1, with `**Estimated time:** X minutes`. No
  lesson exceeds **90 minutes** — this is checked by CI
  (`scripts/check_course_package.py`) and the alignment reviewer. Default to ~60-minute
  lessons; split content into another numbered lesson rather than exceeding 90.
- Start from `modules/_template/01-content.md`; the fullest real examples are under
  `modules/coretech-computer-hardware/` and `modules/mastering-lt-resources/`.
- See `references/markdown-styling.md` for formatting conventions.

## The four-phase lesson structure (the "4 Cs")

Every lesson body follows **Connect → Content → Challenge → Change**, each as an `##`
H2 section, in that order. The phase timings below are for a 60-minute lesson — scale
them proportionally to the lesson's `**Estimated time:**` and make sure the phases add
up to it.

A short preamble may come before Connect: a `**Purpose:**` line (the real-world task
this lesson prepares the learner for) and a `## Learning objectives` list of observable
outcomes tracing to the design doc and the competency descriptor.

### 1. Connect (~10 minutes)

Activate prior knowledge and connect to learner experience. Include:

- Opening activity or scenario
- Reflection questions
- Links to previous learning or real-world context

### 2. Content (~25–30 minutes)

Core instructional material. Include:

- Clear explanations of key concepts
- Real-world examples
- Visual descriptions when helpful
- **Key Takeaways** section at the end

### 3. Challenge (~15–20 minutes)

Hands-on practice and application. Include:

- Structured activities
- Practice scenarios
- Guided questions
- Clear instructions

### 4. Change (~5–10 minutes)

Transfer learning to real situations. Include:

- Application strategies
- Next steps
- Preview of upcoming content

## Mentor guidance goes in its own file

Do **not** put a "For Mentors" section inside lesson files. In this repo, facilitator
and assessment guidance (observable success indicators, common misconceptions,
differentiation strategies) lives in the course's separate mentor guide
(`NN-mentor-guide.md`, see `modules/_template/03-mentor-guide.md`), and extended
practice beyond the in-lesson Challenge lives in the scenario bank
(`NN-scenario-bank.md`).

## Outcome levels

Match the module's `target_outcome_level` frontmatter — it comes from the competency
framework, and it calibrates depth:

| Level | Meaning |
|---|---|
| `Has knowledge` | Conceptual awareness — the learner can explain the topic but hasn't applied it |
| `With Assistance` | The learner can attempt the task in the field with support from a mentor |

State the observable outcome a lesson prepares the learner for *before* teaching the
content, matched to this level.

## Multi-lesson courses

1. **Number lessons sequentially** in the order a learner works through them.
2. **Create clear progression**: each lesson builds on previous ones.
3. **Link lessons**: reference previous content in Connect, preview upcoming material in
   Change.
4. **One file per lesson**, named descriptively (e.g., `01-lingtran.md`).
5. **Maintain consistent structure and formatting** across all lessons.

## Content quality standards

Ensure all content:

1. **Respects adult learners**: assumes competence, provides context, explains "why"
2. **Uses clear language**: avoids unnecessary jargon, defines technical terms
3. **Includes concrete examples**: real consultant field scenarios (remote locations,
   limited connectivity, non-technical end users) — this curriculum trains people who
   support Bible translators and language workers in the field
4. **Provides actionable guidance**: clear steps and practical application
5. **Matches the learner level**: appropriate depth for the module's
   `target_outcome_level`
6. **Follows accessibility principles**: clear structure, descriptive headings, logical
   flow

Do not leave `[bracketed]` placeholder text in the final output.
