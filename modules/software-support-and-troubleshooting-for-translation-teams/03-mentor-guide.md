# Mentor guide

**Purpose:** Guide for mentors assessing learner responses to the [scenario bank](02-scenario-bank.md).

This is a **Has Knowledge** course. The scenarios ask learners to *explain and self-assess*
a troubleshooting approach, not to perform a repair. Assess the **quality of their
reasoning** — whether they think systematically and reach for the right questions — rather
than whether they name a specific fix. All three scenarios are built on the lesson's 5-step
framework: **Clarify the Problem → Understand the Context → Reproduce the Problem → Test
Systematically → Know When to Escalate.**

**INFO — realism of the scenarios.** F1 (Panicked Translator) and I1 (Unclear Error) are
confirmed real field cases from the course SME. C1 (Recurring Problem) is illustrative and
plausible but is **not** a confirmed field case; don't present it to learners as more
verified than the other two.

## What you're evaluating

Across all three scenarios, watch for the four things the lesson trains toward:

1. **Reasoning** — do they think systematically, or jump to a fix before understanding the
   problem? Jumping to solutions is the single most common new-consultant mistake.
2. **Clarifying before acting** — do they ask questions to turn a vague report into a
   specific, diagnosable issue before proposing anything?
3. **Context awareness** — do they consider the translator's workflow, project setup, and
   what recently changed, so they can judge what is normal versus abnormal? Not knowing the
   translation workflow well enough to interpret "normal" is a distinct, confirmed failure
   mode.
4. **Escalation judgment** — do they recognise the limits of what they can resolve alone
   and identify an appropriate next step? Misjudging when to escalate is a common weak spot.

Also credit **communication**: acknowledging the translator's stress, explaining the
process, and being honest about what they do and don't know.

## Providing feedback

**Strong responses:** acknowledge what they did well; point out excellent reasoning (e.g.
questions that would efficiently separate causes); suggest minor refinements.

**Adequate responses:** confirm what was correct; highlight what they missed — most often a
missing clarifying question or an absent escalation trigger; ask probing questions to
develop their thinking ("What would you check before assuming the work is truly lost?").

**Weak responses:** if the learner jumps straight to a solution, name it directly and work
back through the framework together, starting from "What would you clarify first?" Assign
another scenario for practice in the specific area of weakness.

Remember there is usually more than one defensible approach — assess the process, not
whether it matches a single expected answer.

## Answer notes by scenario

### Scenario F1: The Panicked Translator (objective 1)

**What a strong answer looks like.** The learner slows down and **clarifies before acting**.
Their three questions map onto the clarifying prompts from the lesson — for example: *What
were you trying to do / what did you expect to see?*, *What exactly is showing (or not
showing) now?*, *When did you last see your work, and has anything changed since — an
update, a different location or device, a Send/Receive?* Before proposing a solution they
say they'd want to know how the project is set up and whether the work might simply be
looking in the wrong place or not yet synced, rather than genuinely lost. Good answers also
acknowledge the translator's stress explicitly ("I can hear this is really frustrating —
let's work through it together") and avoid promising a fix before they understand the
problem. Strong answers note that "all my work is gone" is a *starting point*, not a
diagnosis, and that data is often recoverable.

**Most common wrong turn.** Treating the message at face value and jumping to a recovery or
"restore from backup" action before asking a single question — or, at the other extreme,
offering false reassurance ("don't worry, it's definitely fine") without gathering
information. Either way the learner has skipped the Clarify step.

### Scenario I1: The Unclear Error (objectives 3)

**What a strong answer looks like.** The learner recognises that they cannot rely on the
translator's verbal description or a hard-to-read screenshot alone, and that the most
reliable move is to **see the problem happen** — a screen-share or working side-by-side, or
at minimum having the translator reproduce the error step by step while describing exactly
what they do. A good ordered approach: (1) gather the exact error and the steps that trigger
it (screenshot in full, note what they were doing); (2) reproduce or directly observe it
rather than guessing from the description; (3) test from the simplest, most likely cause.
They can explain *why* direct observation beats description — "it's not working" often turns
out to be clicking the wrong button or looking in the wrong place, which only becomes
visible when you watch. Credit answers that address the language barrier practically
(translating the error text, matching it to documentation, or watching the behaviour rather
than the words).

**Most common wrong turn.** Trying to diagnose the error purely from the screenshot and
their own guesswork — proposing a fix for a specific error they've assumed, without ever
observing or reproducing what the translator actually does. This is exactly the reliance on
description that objective 3 warns against.

### Scenario C1: The Recurring Problem (objectives 2, 4, 5)

*(Illustrative scenario — not a confirmed field case. Assess the reasoning; there is more
than one defensible approach.)*

**What a strong answer looks like.** The learner treats the *intermittent* nature as the
central challenge: because it only appears every few days, you can't catch it on demand, so
you need pattern data and context. Strong answers ask about **workflow and context first** —
how the team works, whether it's a shared/team project or individual files, what they were
doing when it stuck, and what recently changed — so they can judge what is normal versus
abnormal for this team rather than assuming. They describe **systematic, one-variable-at-a-
time** testing and gathering evidence over time (noting when it happens, what precedes it,
whether it correlates with a particular action, project size, or connectivity) instead of
trying random fixes. They set honest expectations with the translator ("I want to find the
pattern before I promise a permanent fix") and identify a clear **escalation trigger** — if
they can't reproduce or isolate it, or it points beyond their knowledge, the next step is to
consult someone more experienced, contact vendor support, or agree an interim workaround.

**Most common wrong turn.** Promising a permanent fix immediately, or proposing one specific
cause and cure without any plan to observe the pattern — random troubleshooting dressed up as
a solution. A secondary miss is ignoring context entirely (not asking what's normal for the
team) and therefore having no basis to tell a real fault from ordinary workflow behaviour.
