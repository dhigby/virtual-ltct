# Stage 3 — Draft the package

**Board status:** `Drafting` · **Who:** Author · **Tools:** `module-author`, `quiz-writer`, `video-script-writer` agents

With the design approved, you build the content artifacts. Each is its own checkbox on the
tracker. Do them in order — later artifacts depend on earlier ones being stable.

> **The 90-minute rule.** Every lesson file and the scenario bank must open, right under
> the H1, with `**Estimated time:** X minutes`. No lesson may exceed 90 minutes — if
> content doesn't fit, split it into another numbered file. The alignment check (stage 4)
> and CI both verify this.

> **Every lesson has a visual.** These courses teach software, and a lesson of unbroken
> prose asks the learner to picture a screen they have never seen. So every numbered lesson
> carries at least one — a **screenshot**, a **diagram**, an **image**, or a **video** —
> and you choose which as you draft it (3a). **Lesson 1 is the course overview, so its
> visual is the overview video:** it carries a `**Watch the video:**` line, and the script
> for it is 3d. Pick by what the learner needs: a screenshot for anything they must find
> on screen, a diagram for how parts relate or a decision flow, a video for orientation and
> the shape of a workflow. One that merely decorates is worse than none — it costs reading
> time and teaches nothing.
>
> **Screenshots.** The agent writes the image *link* and its alt text where the shot
> belongs; a human captures the picture. Files go in `modules/<slug>/assets/`, named
> `ss-<lesson number>-<what-it-shows>.png` — lowercase, hyphens, no spaces. The alt text
> is not decoration: it is simultaneously the screen-reader text, the reviewer's check,
> and the brief telling whoever holds the mouse *which state to capture*, so
> `![alt text](…)` is a defect and CI rejects it. Never hotlink an image from Google
> Drive, Notion or a site — a remote image rots and takes the published page's picture
> with it. Step **3e** below is where the capturing happens.
>
> **Diagrams** are committed image files too — an `.svg` under `assets/`, same naming and
> the same alt-text rule. Don't reach for a ` ```mermaid ` fence: no mkdocs config in this
> repo registers one, so it would publish as a block of code. And a diagram must explain
> the **tool, the workflow or the concept** — never the shape or meaning of language data,
> which you cannot read (see [Who an LTC is](../../CLAUDE.md#who-an-ltc-is--and-what-that-changes-about-the-content)).
>
> **Videos** are referenced, never committed — `**Watch the video:** [title](url)`, with
> the URL added once it is recorded at stage 8. Until then write
> `**Watch the video:** _To be recorded at stage 8._` so the lesson's visual is already
> chosen and the recording list is visible.

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
content in the design doc's SME notes, and writes one file per lesson. **This is where each
lesson's visual is chosen** — the agent writes the screenshot link, the diagram, or the
`**Watch the video:**` line into the lesson as it drafts, and tells you how many shots it
has left for a human. ✅ Tick **"3a. Lessons drafted"**.

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

## 3d — Overview video script (`video-script-writer`)

> Use the **video-script-writer** agent to draft the overview video script
> (`NN-video-script.md`) for `modules/<slug>/` from its finished lessons.

The script for the recording step — cold open, on-screen/voiceover pairing, runtime
estimate. `NN-video-script.md` is the **overview video**, the companion to lesson 1: it
orients a learner to the shape of the tool and the workflow before they do the hands-on
lessons. It is not a read-aloud of the course.

If a later lesson also warrants its own video, that script is **optional** and is named
`NN-lesson-<L>-video-script.md` (e.g. `09-lesson-03-video-script.md`). Keep
`-video-script.md` at the end of the name — the tooling identifies a script by that ending
in five places, and a name like `09-video-script-03.md` would be treated as a lesson *and*
published to pilot learners.

✅ Tick **"3d. Overview video script drafted"**.

## 3e — Finish the visuals (human, with the tool open)

The part no agent can finish for you. `/next-step` lists what is outstanding — any lesson
still without a visual, and every shot still to be captured with the alt text saying what
to capture:

```bash
python scripts/course_stage.py --slug <slug>
```

For each one: put the tool in that state, capture, save it into `modules/<slug>/assets/`
under exactly the filename the link expects, and commit. Use a real project the learner
would recognise, and check the shot for anything that shouldn't be public — this repo is
public, so no unpublished draft text, no personal details, no credentials on screen.

Note the tool version you captured against in the design doc's SME notes. Screenshots age
when the tool ships a new release, and the next person needs to know what they're looking
at.

If a lesson is listed as having **no visual at all**, that is a drafting gap, not a capture
one: decide what it needs and send it back through `module-author` to write the link,
diagram or video line in. ✅ Tick **"3e. Visuals in place"**.

## Exit criteria

- All package files present: numbered lessons, `NN-scenario-bank.md`, `NN-mentor-guide.md`,
  `NN-quiz.md`, `NN-video-script.md` (the overview script; any per-lesson scripts are
  optional).
- Every lesson and the scenario bank state `**Estimated time:** X minutes`, none over 90.
- Every lesson contains the four *Learning That Lasts* phase sections, in order:
  `## Connect`, `## Content`, `## Challenge`, `## Change`.
- Every image link resolves to a committed file under `assets/`, with alt text that
  describes the state shown.
- **Every lesson carries a visual** — screenshot, diagram, image or video — and lesson 1
  carries the overview video. Stage 4 blocks on this.

## Then

- Board status stays `Drafting`.
- Go to [Stage 4 — Alignment check](04-alignment.md).
