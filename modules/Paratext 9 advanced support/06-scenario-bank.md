# Scenario Bank

**Estimated time:** 80 minutes

**Purpose:** Applied practice covering all five recovery/support topics from this course.
**Format:** Sequenced from most straightforward to most involved. Scenario 1 is a simple
setup task; Scenario 4 (last) is the most complex, since it requires an external tool
(TortoiseHG) and manual file editing rather than anything done inside Paratext alone.

## How to use these scenarios

### For learners

- Read each scenario carefully.
- Apply the procedure and reasoning taught in the matching lesson (linked below each
  scenario).
- Write a detailed response — a diagnosis, a step-by-step plan, or both, depending on the
  scenario.
- Submit your response to your mentor for feedback.

### For mentors

- Use the accompanying mentor guide (`07-mentor-guide.md`) for assessment criteria.
- Look for systematic thinking, not just a "correct" answer.
- Provide constructive feedback.

---

## Scenario 1: The Workshop Practice Text

**Time budget:** 12 minutes | **Topic:** Installing an open-source text (Lesson 1)

**Situation:** You are preparing a five-day Paratext training workshop for eight
participants who have never used the software. You want every participant to be able to
practice editing the *same* Scripture text — without any risk to a real translation
project — before they start working with their own team's draft.

**Context:** The workshop venue has unreliable internet, so you need to plan the download
and setup the day before, not live during the session. One participant, during setup,
tells you they tried opening the Berean Standard Bible as a resource and were confused that
they couldn't type into it, since they'd read it was public domain.

**Your task:**

1. Explain to this participant why the BSB resource they opened is not editable, even
   though the text itself is public domain.
2. Write out the exact steps you would take, the day before the workshop, to get an
   editable copy of an open-license text ready for participants to practice on.
3. Recommend where you would look to download the text in the format Paratext needs.

---

## Scenario 2: "Did We Lose That Edit?"

**Time budget:** 14 minutes | **Topic:** Project history accuracy (Lesson 2)

**Situation:** Two translators on a team you support have been working in different books
at the same time — one in Matthew, one in Mark — and both did a Send/Receive shortly
afterward. A few days later, one of them runs Compare Versions to check her edit to
Matthew against the point where her colleague's Mark edit was merged in, and is alarmed:
it looks like her Matthew edit was undone.

**Context:** She has already messaged the rest of the team saying "I think we lost an
edit" and is asking you, urgently, to confirm whether the work is really gone before
anyone starts re-typing it from memory.

**Your task:**

1. Determine — and explain your reasoning — whether the edit is actually lost, or whether
   this is the kind of history-ordering illusion covered in Lesson 2.
2. Write the explanation you would send back to the team, in plain, non-technical
   language, addressing both "is the work safe?" and "why did it look like this?"
3. Describe what you would check to confirm your answer with certainty, rather than just
   reassuring her based on general principle.

---

## Scenario 3: The Suspicious Edit

**Time budget:** 16 minutes | **Topic:** User roles and edit access (Lesson 3)

**Situation:** A team leader contacts you confused: project history shows a change to a
book credited to a team member who, according to the project's user roles, does not have
editing access to that book. The team leader wants to know if someone has bypassed the
permission settings.

**Context:** That same team member mentions, almost in passing, that their laptop crashed
unexpectedly a few days ago while Paratext happened to be open, and that they haven't done
a Send/Receive since restarting it.

**Your task:**

1. Explain to the team leader why this change could appear under that user's name without
   them having actually used Paratext to make an unauthorized edit.
2. Recommend the correct next step for the team member whose laptop crashed — and
   specifically what they should *not* do first, and why.

---

## Scenario 4: The Archived Draft

**Time budget:** 18 minutes | **Topic:** Snapshotting to a reference project (Lesson 5)

**Situation:** A project administrator wants to preserve the translation exactly as it
stood three months ago — after a completed drafting phase — as its own standalone
reference project, separate from the ongoing work, so it can be archived and referenced
later without changing as the team keeps working.

**Context:** The administrator does not currently have any automatic Send/Receive options
turned on for the main project, but is nervous about doing anything that could disrupt the
team's ongoing work, since several translators are actively editing other books right now.

**Your task:**

1. Identify which of the two snapshotting procedures from Lesson 5 applies here, and why.
2. Write out the full step-by-step plan you would give this administrator, in the correct
   order.
3. Identify the two steps in the procedure that specifically exist to protect the team's
   ongoing work from being disrupted, and explain what could go wrong if either were
   skipped.

---

## Scenario 5: The Vanishing Notes

**Time budget:** 20 minutes | **Topic:** Recovering notes and settings (Lesson 4)

**Situation:** A translator contacts you: most of their project notes seem to have
disappeared. Looking more closely, a handful of very recent notes are still there — it's
only the older ones that are gone.

**Context:** The translator remembers that their computer crashed while Paratext was
running about three weeks ago, but they didn't think much of it at the time since Paratext
seemed to restart fine and they kept taking new notes afterward. They don't have TortoiseHG
installed yet and have never used it.

**Your task:**

1. Diagnose which of the two recovery cases from Lesson 4 this is (a full restore, or a
   merge of an old and new notes file) — and explain what in the situation tells you that.
2. Write the full step-by-step recovery plan, including getting TortoiseHG installed, the
   specific find-and-recover steps in the HG Workbench, and (since this is the merge case)
   the exact XML edits required.
3. Describe what you would check afterward to confirm the recovery succeeded, and what a
   corrupt-file result would tell you about where the edit went wrong.
