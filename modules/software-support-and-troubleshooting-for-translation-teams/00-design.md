# Course Design Document

## Course overview

| Item | Description |
| --- | --- |
| **Title** | Software Support and Troubleshooting for Translation Teams |
| **Competencies addressed** | Translation Tools |
| **Target outcome level** | 1 - Has Knowledge |
| **SME(s) consulted** | Jenni Beadle — interviewed 2026-07-30, answers recorded below. Original lesson content (troubleshooting framework, common-issue categories, toolkit list, three Challenge scenarios) was first drafted via a Claude.ai chat session and is being retained where the SME confirmed it. Steve White — SME review 2026-08-04; approved the design, and his lesson-content notes were folded in (see commit `e81e3b0`). |
| **Design status** | Approved by Steve White on 2026-08-04 |

## Why this course is entering the pipeline now

This course exists today as a single faithful backfill lesson,
`01-software-support-and-troubleshooting.md` (~60 minutes), teaching a 5-step
troubleshooting framework — Clarify the Problem → Understand the Context → Reproduce the
Problem → Test Systematically → Know When to Escalate — plus guidance on supporting
stressed translators and three challenge scenarios. It was never run through stage 1
design. This doc formally enters it into the 8-stage pipeline, mining the existing draft
as source material per the course owner's (Jenni's) request rather than starting from
scratch. An SME interview (recorded below) has since resolved every open question from
the previous draft of this document.

**No quiz file exists yet** (`04-quiz.md` — see revised module breakdown below).

## Outcome-level re-level (resolved)

The original draft of this document flagged a conflict: the `README.md` frontmatter said
`With Assistance` while the lesson body itself said `**Level:** Has Knowledge`, and the
content is tool-agnostic throughout (never names a specific translation tool). **Jenni's
decision: re-level to `1 - Has Knowledge`.** `README.md` frontmatter has been updated to match.

This also changes which rung of the `competencies/translation-tools.md` component 2.0
ladder anchors the objectives. Reading the ladder correctly — each row's activities carry a
learner to the level in its `Reaches` column — the row that reaches `2 - With Assistance` is
`1 - Has Knowledge` ("Can use translation tools and troubleshoot issues that arise"), which is
one level too high here. The anchor for a course landing at `1 - Has Knowledge` is the row
labelled **`0 - No Competency`** ("Demonstrates basic knowledge of translation tools").
Objective verbs below have been rewritten to recognize/explain phrasing accordingly. The existing
Challenge scenarios ("write out the questions you would ask," "describe your
troubleshooting approach") already fit `1 - Has Knowledge` well — they're explanation/
self-assessment tasks, not live-practice-with-mentor tasks — so this re-level is
content-consistent and required no rewrite of the scenarios themselves.

## Learning objectives

| # | Objective | Source | Assessed by |
| --- | --- | --- | --- |
| 1 | Learner can explain what clarifying questions to ask (goal, actual behavior, timeline, recent changes, reproducible evidence) to turn a vague translator problem report ("nothing is working") into a specific, diagnosable issue | Translation Tools 2.0, `0 - No Competency` — "Demonstrates basic knowledge of translation tools" | Quiz + Challenge Scenario 1 (The Panicked Translator) |
| 2 | Learner can identify project- and workflow-context factors (project structure, collaboration setup, work environment, recent changes) that affect what's normal vs. abnormal for a translation team, and explain why context matters for diagnosis | Translation Process 1.0, `0 - No Competency` — "Get to know a translation team and their daily activities" | Quiz + Challenge Scenario 3 (The Recurring Problem) |
| 3 | Learner can explain why reproducing or directly observing a reported problem (screen-share or side-by-side) is more reliable than relying solely on a translator's verbal description | Translation Tools 2.0, `0 - No Competency` | Quiz + Challenge Scenario 2 (The Unclear Error) |
| 4 | Learner can describe a systematic, one-variable-at-a-time testing method for isolating the cause of a translation-software fault | Translation Tools 2.0, `0 - No Competency` | Quiz + Challenge Scenario 3 |
| 5 | Learner can recognize signs that a problem exceeds what they can resolve alone and identify the appropriate next step (escalate to a more experienced colleague, contact vendor support, restore from backup, or find a workaround) | Translation Tools 2.0, `0 - No Competency` (foundational recognition of escalation triggers, ahead of the `1 - Has Knowledge` rung's actual "troubleshoot issues that arise") | Quiz + Challenge Scenario 3 |
| 6 | Learner can recognize common categories of translation-software problems (synchronization failures, missing/lost work, installation & compatibility issues, performance problems, UI/feature-location confusion) from a translator's description of symptoms | Translation Tools 2.0, `0 - No Competency` | Quiz |

**Source-column note.** The "X.0" labels in the table above (e.g. "Translation Tools 2.0",
"Translation Process 1.0") refer to numbered **components within** the single
`Translation Tools` competency descriptor (`competencies/translation-tools.md`), not to
separate framework competencies. The only framework competency this course maps to is
**Translation Tools**.

**Supplementary content — not competency-mapped.** The lesson also teaches two adjacent
professional skills the SME chose to keep as bundled content, per her Q6 answer:

- Communicating with a stressed or frustrated translator during a support interaction
  (acknowledging stress, explaining the process, being honest about the limits of one's
  knowledge, managing expectations about response time).
- Documenting a resolved technical issue (problem / symptoms / solution) as a reusable
  team resource.

Neither maps to an observable criterion in `competencies/translation-tools.md`. They are
taught in the lesson and may appear in the quiz/scenarios for completeness, but they are
**not asserted as Translation Tools competency evidence** and should not be counted
toward that competency's coverage.

## Module breakdown

Per the SME's Q7 answer, the three Challenge scenarios and "For Mentors" notes are to be
split out of the single lesson file into standalone files matching this repo's
`modules/_template/` convention. This design doc specifies the target file list; the
actual split of existing content is a stage-3 drafting task for `module-author`, not
performed here.

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-software-support-and-troubleshooting.md` | Systematic troubleshooting framework (Clarify → Understand Context → Reproduce → Test Systematically → Escalate), common issue categories, supporting stressed translators, toolkit-building | 1–6 (+ supplementary content) | 55 (Connect 10 / Content 30 / Challenge 10 / Change 5) |
| `02-scenario-bank.md` | The three existing Challenge scenarios (Panicked Translator, Unclear Error, Recurring Problem), reframed as explanation/self-assessment tasks consistent with `1 - Has Knowledge` | 1, 2, 3, 4, 5 | 15 |
| `03-mentor-guide.md` | The existing "For Mentors" watch-for notes, expanded as needed for a standalone facilitator file | — | — |
| `04-quiz.md` | Assessment (12 questions, 80% to pass) | 1–6 | 15 |
| **Total learner seat time** | | | **~85** |

## Assessment plan

A 12-question multiple-choice quiz (`04-quiz.md`) at 80% (10/12) to pass, covering
objectives 1–6. The three Challenge scenarios in
`02-scenario-bank.md` (written explanation/self-assessment, mentor-reviewed per
`03-mentor-guide.md`'s watch-for list — jumping to solutions, asking clarifying questions,
considering the translator's stress, systematic vs. random troubleshooting) exercise
objectives 1–5 in combination. The supplementary communication/documentation content may
be touched on in the scenarios but is not counted as evidence for the Translation Tools
competency.

## SME knowledge notes

Recorded from Jenni Beadle's interview, 2026-07-30.

**Real field cases (confirms Q2):**
- **Challenge Scenario 1 ("Everything is gone! ... all my work from the past week has
  disappeared")** — confirmed as a common, real situation Jenni has seen.
- **Challenge Scenario 2 (a translator sends a screenshot of an error message in a
  language the consultant doesn't speak well)** — confirmed as a common, real situation
  Jenni has seen.
- **Challenge Scenario 3 (recurring problem requiring a program restart every few days)**
  — **illustrative and plausible, but NOT a confirmed field case.** It should not be
  presented in course content as equally verified alongside Scenarios 1 and 2.

**Common learner mistakes (Q3, recorded verbatim from the SME's answer):** new
consultants most often (a) jump to a fix before clarifying the problem, (b) misjudge when
to escalate, and (c) don't know the translation process/workflow context well enough to
interpret what's normal vs. abnormal. Point (c) is the reason objective 2 ("Understand
the Context") is anchored to the Translation Process competency component rather than
folded into Translation Tools — it's a distinct, confirmed failure mode in its own right.

**Framework validity (Q4):** the SME confirmed the current 5-step framework (Clarify →
Understand Context → Reproduce → Test Systematically → Escalate) is "certainly the goal"
and is good for learners to see even at `1 - Has Knowledge` level — they need to recognize and
explain the target framework now, and will apply it with assistance later in their
development (a likely future With-Assistance-level course, not in scope here). No changes
to the framework's steps are needed.

**Tool-version specifics (Q5):** moot at this outcome level — the course stays
tool-agnostic; no tool-specific module is needed at `1 - Has Knowledge`.

## Resolved open questions (for reference)

All seven open questions from the prior draft of this document have been answered by the
SME interview above:

1. Outcome level → re-leveled to `1 - Has Knowledge` (see "Outcome-level re-level" section).
2. Real field cases → Scenarios 1 and 2 confirmed real; Scenario 3 flagged as illustrative
   only.
3. Common learner mistakes → recorded verbatim above.
4. Framework validity → confirmed as-is, no changes.
5. Tool-version specifics → moot at this level.
6. Objectives 7/8 fate → kept as supplementary, non-competency-mapped content.
7. File structure → scenario bank and mentor guide to be split into standalone files
   (see Module breakdown).
