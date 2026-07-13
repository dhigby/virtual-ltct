# Stage 3 — Draft the package

**Board status:** `Drafting` · **Who:** Author · **Tools:** `module-author`, `quiz-writer`, `video-script-writer` agents

With the design approved, you build the four content artifacts. Each is its own checkbox
on the tracker. Do them in order — later artifacts depend on earlier ones being stable.

> **The 90-minute rule.** Every lesson file and the scenario bank must open, right under
> the H1, with `**Estimated time:** X minutes`. No lesson may exceed 90 minutes — if
> content doesn't fit, split it into another numbered file. The alignment check (stage 4)
> and CI both verify this.

> **The lesson shape (the "4 Cs").** Every lesson body follows the *Learning That Lasts*
> four-phase structure, as `##` sections in order: **Connect** (activate prior
> knowledge), **Content** (core instruction, ending in Key Takeaways), **Challenge**
> (hands-on practice), **Change** (transfer to real work). For a 60-minute lesson budget
> roughly 10 / 25–30 / 15–20 / 5–10 minutes across the phases, scaled to the lesson's
> estimated time. The [`training-content` skill](../../.claude/skills/training-content/SKILL.md)
> defines the methodology; `modules/_template/01-content.md` shows the shape. The
> alignment check (stage 4) verifies the four sections are present.

## Entry criteria

- `00-design.md` is **approved** (stage 2). If it isn't, stop — the agents will refuse and
  `/next-step` will send you back.
- Move the board status to `Drafting`.

## 3a — Lessons (`module-author`)

> Use the **module-author** agent to draft the numbered lessons for `modules/<slug>/`
> according to its approved `00-design.md`.

It follows the *Learning That Lasts* framework (via the `training-content` skill), grounds
content in the design doc's SME notes, and writes one file per lesson.
✅ Tick **"3a. Lessons drafted"**.

## 3b — Scenario bank + mentor guide (`module-author`)

> Use the **module-author** agent to draft the scenario bank (`NN-scenario-bank.md`) and
> mentor guide (`NN-mentor-guide.md`) for `modules/<slug>/` from the lessons and the design
> doc's SME knowledge notes.

Scenarios sequence foundational → complex; the mentor guide gives facilitators answer
guidance. ✅ Tick **"3b. Scenario bank + mentor guide drafted"**.

## 3c — Quiz (`quiz-writer`)

> Use the **quiz-writer** agent to write the assessment quiz (`NN-quiz.md`) for
> `modules/<slug>/` from its finished lessons and its design doc's assessment plan.

15–20 questions in labeled sections, an explicit pass threshold in the body, a
pipe-separated answer key. ✅ Tick **"3c. Quiz written"**.

## 3d — Video script (`video-script-writer`)

> Use the **video-script-writer** agent to draft the video script (`NN-video-script.md`)
> for `modules/<slug>/` from its finished lessons.

The script for the recording step — cold open, on-screen/voiceover pairing, runtime
estimate. ✅ Tick **"3d. Video script drafted"**.

## Exit criteria

- All package files present: numbered lessons, `NN-scenario-bank.md`, `NN-mentor-guide.md`,
  `NN-quiz.md`, `NN-video-script.md`.
- Every lesson and the scenario bank state `**Estimated time:** X minutes`, none over 90.
- Every lesson contains the four *Learning That Lasts* phase sections, in order:
  `## Connect`, `## Content`, `## Challenge`, `## Change`.

## Then

- Board status stays `Drafting`.
- Go to [Stage 4 — Alignment check](04-alignment.md).
