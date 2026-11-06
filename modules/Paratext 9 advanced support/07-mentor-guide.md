# Mentor Guide

**Purpose:** Facilitator notes for grading the five scenarios in `06-scenario-bank.md` —
what a complete recovery/response looks like for each, and common wrong turns.

## What you're evaluating

1. **Reasoning** — does the learner think through *why* something happened before
   recommending an action, or jump straight to a fix?
2. **Procedural accuracy** — do they get the actual Paratext (and, for Scenario 4,
   TortoiseHG) steps right, in the right order?
3. **Practical judgment** — is the recommendation appropriate given the scenario's stakes
   (e.g., recognizing when *not* to Send/Receive immediately)?
4. **Communication** — could the learner's explanation actually be understood by the
   non-technical team member or leader in the scenario?

## Providing feedback

**Strong responses:** acknowledge what they did well; point out where their reasoning
correctly distinguished this case from a similar-looking one; suggest minor refinements.

**Adequate responses:** confirm what was correct; highlight any steps skipped, reordered,
or under-explained; ask probing questions to develop their reasoning further.

**Weak responses:** identify exactly where the reasoning or procedure went wrong; work
through the correct sequence together using the matching lesson; assign the lesson's
Challenge activity again before moving on.

## Answer notes by scenario

### Scenario 1: The Workshop Practice Text

**What a strong answer looks like:**

- Correctly explains that Paratext resources are always read-only regardless of the
  text's license — this is the root of the participant's confusion, not a bug or a
  mistake they made.
- Names ebible.org/find and/or open.bible/bibles as the place to get a USFM download.
- Gives the correct sequence: create a new project (main menu → New Project) → set
  language in project properties → set project type to standard translation → import
  books (Project menu → Manage books → Import books).
- For the eight-participant workshop, creates **one** project and shares it with all eight
  computers by Send/Receive from a flash drive or a local network drive — set up the day
  before, so it doesn't depend on the internet.

**Common wrong turn:** treating the BSB resource itself as something that can be "unlocked"
or converted to editable — missing that a *new, separate* project has to be created and
the text imported into it.

### Scenario 2: "Did We Lose That Edit?"

**What a strong answer looks like:**

- Correctly identifies this as the version-numbering illusion, not a real loss of work —
  and can explain, in the learner's own words, that Mercurial orders history by version
  number, not strict chronology, and that simultaneous edits from the same starting
  version get renumbered on merge.
- Reassures the team without simply asserting "it's fine" — explains *why* it's fine, in
  language a non-technical translator would find genuinely convincing, not just soothing.
- Checks the current text of the book, where the edit is still visible, rather than
  reassuring from general principle alone.

**Bonus:** recognizes this applies regardless of Paratext version (Paratext 7 onward), so
this isn't a version-specific bug the team should expect fixed later.

**Common wrong turn:** trying to explain this in terms of computer clocks or timestamps
alone, without grasping that Mercurial's version numbering — not the date shown — is what
actually determines the merge order. (The wrong-clock war story in Lesson 2 is one cause of
a chronology mismatch, not the whole explanation.)

### Scenario 3: The Suspicious Edit

**Task 1 (explaining the change) — what a strong answer looks like:**

- Correctly explains that Paratext's edit-access restrictions only apply while a user is
  working through Paratext itself — they cannot prevent a file from being altered by a
  crash, file-system corruption, or (for `.ptx` project files) a Windows System Restore
  that reverts the file because Microsoft treats `.ptx` as a system file extension.
- Can explain — to a non-technical team leader — how and why project history can show a
  mysterious edit credited to someone without editing permission, without implying that
  Paratext's permissions failed. This is
  the scenario's core "what good looks like" criterion: the learner should be able to give
  this explanation clearly and confidently, not just recite the list of possible causes.

**Tasks 2–3 (recovering the team's copy) — what a strong answer looks like:**

- Recognises that the change is already in project history, so the corrupted files have been
  sent: deleting and resyncing is no longer enough, and the administrator recovers the
  uncorrupted book files from project history.
- Before "Undo Changes", has the administrator click the link for the changed book in the
  history point that introduced the corruption, to open the Compare Versions dialog.
- Recognises that the right-hand pane may not be the latest version: "Undo Changes" restores
  whatever that user had before the corruption, and if they didn't have the latest book files,
  that is an out-of-date copy.
- If the right-hand pane isn't the latest, has the administrator look through the other
  history points for the one holding the latest version of that book and use "Revert Books"
  to restore it.
- Finishes the recovery with a Send/Receive by the administrator, which replaces the
  corrupted version of the book for all users. Knows that the user whose computer crashed
  does not then need to delete their copy of the project and receive a new copy.
- Knows that finding the best version may be easier in TortoiseHG, examining the Mercurial
  repository directly (Lesson 4).
- Leaves the choice of version to the administrator, who can read the text — the learner
  points them to the project history and Compare Versions rather than judging the text
  themselves. Bonus if they recall from Lesson 2 that history isn't always in strict
  chronological order.
- Demonstrates the "what good looks like" criterion: can explain the pitfall of using "Undo
  Changes" to recover after corrupt files have reached the Send/Receive server.

**Bonus:** recognizes that task 1's behavior applies across Paratext 9, 8 and 7 — not a
version-9-only quirk that upgrading or downgrading would fix — while the recovery in tasks 2–3
applies to Paratext 9 and 8, not 7.

**Follow-on twist (task 4) — what a strong answer looks like:**

- Correctly recommends that the second team member should **not** Send/Receive immediately,
  and instead should delete the project from their own installation and then Send/Receive to
  pull a clean copy from the server.
- Notes that project observers are the one exception — an observer's local text changes
  are never sent out by Paratext.

**Common wrong turns:**

- Clicking "Undo Changes" on the corrupting history point straight away and assuming the team
  is back to the latest text. The corruption is gone, but if the user didn't have the latest
  version before the crash, the team is now on that user's out-of-date copy, and newer work is
  missing without anything looking wrong.
- In the follow-on twist, recommending an immediate Send/Receive "to get back in sync," which
  is the exact action the source material warns against — it risks propagating the
  crash-corrupted files to the rest of the team.

### Scenario 4: The Vanishing Notes

**What a strong answer looks like:**

- Correctly diagnoses this as the merge case (old notes lost, new notes still
  accumulating) rather than a straight restore, based on the detail that some recent notes
  are still visible.
- Includes downloading TortoiseHG (64-bit for any Windows 11 machine, or matching the
  user's actual Windows bitness), closing Paratext, opening the HG Workbench, and finding
  the deletion point (using the `removes("*.*")` revision-set query if it isn't obvious
  from recent history).
- Gets the merge mechanics right: work in a separate folder outside the project, with a copy
  of the current notes file; save the old file there **keeping** its `@NN` suffix; strip the
  first two lines (`<?xml version...?>` and `<CommentList>`) from the *recovered* file; strip
  the last line (`</CommentList>`) from the *current* file; paste the recovered content after
  the current file's content; save; copy the merged current file (not the `@NN` file) back
  into the project folder; start Paratext; Send/Receive.
- Knows to check the three specific XML lines (first line, second line, last line) if
  Paratext reports the merged file as corrupt afterward.

**Bonus:** notes this procedure applies to both Paratext 9 and Paratext 8.

**Common wrong turn:** stripping the `@NN` suffix when saving the old file (as in the
straight-restore case), which overwrites the current notes file and loses the very notes
the learner is trying to preserve, rather than setting up the merge correctly.

### Scenario 5: The Archived Draft

**What a strong answer looks like:**

- Correctly identifies that snapshotting an *earlier* point in history (not the current
  state) requires the administrator-level revert procedure, not the simple "new project +
  import" path.
- Gives the full sequence in order: Send/Receive any pending changes → turn off automatic
  Send/Receive for the main project → revert the desired books to the chosen history point
  → create the new project and import the reverted books → delete the reverted main
  project ("Delete project from this computer only") → Send/Receive to restore the current main
  project from the server → re-enable automatic Send/Receive if it was on.
- Correctly identifies the specific failure point: a Send/Receive — automatic or manual —
  happening anywhere between reverting the books and deleting the reverted copy could push
  the older, reverted state out to the rest of the team as if it were *new* work,
  overwriting other team members' more recent edits. This is exactly why the procedure
  turns off automatic Send/Receive before the revert and deletes the reverted copy with
  "Delete project from this computer only" before resyncing, and why those two steps have to happen
  in that order.
- Demonstrates the scenario's "what good looks like" criterion: the learner's plan would
  produce a second project containing content from an earlier stage of the main project's
  history, with every step in the right order and nothing a Send/Receive could push to the team.

**Bonus:** recognizes this procedure applies to Paratext 9.

**Common wrong turn:** deleting the reverted main project and expecting Send/Receive to
"just work" without first confirming automatic Send/Receive was off during the reverted
period, or choosing the wrong delete option and affecting the whole team's copy — either
mistake risks the exact "reintroduced as new work" failure described above.
