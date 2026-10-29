---
title: Advanced Paratext Support
slug: paratext-9-advanced-support
target_outcome_level: "2 - With Assistance"
competencies:
  - Translation Tools
content_type: content
---

# Advanced Paratext Support

**Watch the video:** [Advanced Paratext Support: course introduction](https://vimeo.com/1238012314)

## About this course

**Audience:** Language Technology Consultants who already support translation teams using
Paratext in the field — often remote, sometimes with limited connectivity, and typically
supporting non-technical end users — and who are ready to move from routine navigation and
configuration into real support and recovery situations.

**Goal:** By the end of this course you will be able to install an editable copy of an
open-license Scripture text as its own project, explain why Paratext's project history view
can look chronologically wrong without anything actually being lost, respond correctly when
a user's project files may have been altered outside Paratext (including after the damage
has already been sent to the team), recover project notes and
settings that a plain rollback won't restore, and configure a reference project that
preserves an earlier stage of a team's translation text.

**Why this sits above the other two Paratext courses:** this course builds on
`paratext-9-basic-training-course` (basic navigation) and `paratext-quotation-rules`
(intermediate configuration) — both real prerequisites. Where those two teach routine setup
and configuration, this course covers **support and recovery**: situations where something
has already gone wrong, or where a consultant needs to reason about what Paratext's
interface is (and isn't) showing them. You'll plan real recovery procedures here, step by step,
with mentor support — this course does not yet expect you to train other consultants to do
the same.

**Format:** Each numbered lesson opens with the field situation it prepares you for, teaches
the minimum procedure you need, and gives you a scenario to work through. The scenario bank
(file 6) then brings all five topics together as realistic "diagnose and fix it" cases,
sequenced from most straightforward to most involved.

---

## Course package

Work through the numbered files in order:

| File | What it is | Estimated time |
| --- | --- | --- |
| [`01-installing-open-source-texts.md`](01-installing-open-source-texts.md) | Lesson 1 — installing an open-source/openly-licensed text as an editable project | 35 min |
| [`02-project-history-accuracy.md`](02-project-history-accuracy.md) | Lesson 2 — why Paratext's chronological project-history view isn't always accurate | 40 min |
| [`03-user-roles-and-edit-access.md`](03-user-roles-and-edit-access.md) | Lesson 3 — why per-book edit-role restrictions aren't foolproof, the safe recovery response, and the "Undo Changes" pitfall once bad files are sent | 35 min |
| [`04-recovering-notes-and-settings.md`](04-recovering-notes-and-settings.md) | Lesson 4 — recovering lost notes, term renderings, and other non-text project settings with TortoiseHG | 40 min |
| [`05-snapshotting-to-a-reference-project.md`](05-snapshotting-to-a-reference-project.md) | Lesson 5 — saving an earlier stage of the text into a reference project | 30 min |
| [`06-scenario-bank.md`](06-scenario-bank.md) | Applied practice: five recovery scenarios, one per topic, simplest → most complex | 84 min |
| [`07-mentor-guide.md`](07-mentor-guide.md) | Facilitator notes: rubric for each scenario, common wrong turns | — (facilitator use) |
| `08-quiz.md` | Assessment quiz covering all five topics | 20 min (not counted toward seat time) |
| [`09-video-script.md`](09-video-script.md) | Walkthrough video script, recorded as two videos: [part 1](https://vimeo.com/1222691505) (on Lessons 1–3) and [part 2](https://vimeo.com/1222710442) (on Lessons 4–5) | — (facilitator/recording use) |
| [`10-video-script.md`](10-video-script.md) | Course-introduction (overview) video script — the video that sits at the top of this page | — (facilitator/recording use) |

**Total learner seat time:** 264 minutes (lessons 180 min + scenario bank 84 min). The
mentor guide and quiz are excluded from this total by convention — see this course's
`00-design.md` for the full breakdown.

---

## Prerequisites

**Software**

- Paratext 9 installed and licensed on your machine.
- A user account with administrator or translator-level access to at least one project (some
  procedures in this course, such as the Lesson 5 revert-and-snapshot procedure, are
  administrator-level tasks).
- [TortoiseHG](https://tortoisehg.bitbucket.io/download/index.html) — not required until
  Lesson 4, but worth installing ahead of time if your connectivity is limited.

**Prior courses**

- `paratext-9-basic-training-course` — basic Paratext navigation.
- `paratext-quotation-rules` — intermediate project configuration.

**Skills**

- Comfortable navigating Paratext's main menu, project menu, and project history view.
- Familiar with Send/Receive as the mechanism for sharing changes across a team.
- Familiar with the idea that a Paratext project is backed by a Mercurial repository — you
  do not need deep Mercurial knowledge going in; this course explains what you need as it
  comes up.
