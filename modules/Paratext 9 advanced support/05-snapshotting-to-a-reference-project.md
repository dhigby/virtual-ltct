# Snapshotting an Earlier Stage into a Reference Project

**Estimated time:** 30 minutes

**Purpose:** Prepares you to configure a reference project that preserves ("snapshots") the
translation text as it existed at an earlier stage of the project — either from where the team
stands right now, or from a point further back in project history.

**Watch the video:** [Paratext 9 Advanced Support, part 2 (Lessons 4–5)](https://vimeo.com/1222710442)

## Learning objectives

- You will be able to configure a reference project that preserves ("snapshots") the translation
  text as it existed at an earlier stage of the project.

## Connect

**✏️ Reflection:** Think about a translation team that has reached a real milestone — a full
draft of a book, a completed round of checking — and wants to keep a copy of the text exactly as
it stood at that point, separate from the ongoing project where work keeps moving forward.
Have you ever had to help a team do that?

## Content

**Why teams want this**

Paratext's Compare Versions tool lets you compare the current text against a previous point in
history, and marking milestones with "Mark point in history" makes those stages easy to find
again later. That covers *comparing*. But some teams want something more: the text from an
earlier stage available as its own **separate project** — for example, so they can make
printouts of it, or add it to a text collection window alongside other resources. That's what
snapshotting into a reference project gives you.

**Two versions of this task**

**1. Snapshotting the current state** (the easy case): if the team is currently at the point they
want to preserve, you don't need to touch project history at all.

- Create a new project (main menu → New Project).
- Open the project menu, choose Manage Books → Import Books, and select the book files from the
  main project.

**2. Snapshotting an earlier point in history** (for a project administrator): if you need to
capture the text as it stood at some point *in the past* — not where the project is now — the
approach is to temporarily revert the books to that point, capture them into the new project,
and then restore the main project to its current state. Step by step:

1. Do a Send/Receive first, if there are any recent changes that haven't been sent yet.
2. Make sure all automatic Send/Receive options are turned **off** for the main project. (This
   matters — you're about to put the main project into a temporary, reverted state, and you
   don't want an automatic Send/Receive pushing that reverted state out to the rest of the
   team.) In the Send/Receive Projects dialog, click **Schedule…** and set the main project's
   **Send/Receive setting** to **Never**.

   ![The Schedule Send/Receive dialog, opened with the Schedule… button at the bottom of the Send/Receive Projects dialog. Its table lists projects with two columns of drop-downs: “Check for updates” and “Send/Receive setting”. Every project shown has its Send/Receive setting set to Never, so none will send or receive automatically. Check for updates is Never for all but the first project, which is set to On startup.](assets/ss-05-schedule-send-receive-never.png)

3. In project history, select the desired history point, use the "Revert Books" command, and
   select the books to revert.
4. Create the new project and import the reverted books into it, exactly as in the easy case
   above.
5. Delete the reverted copy of the main project: choose "Delete project" (from either the main
   menu or the project menu), and select the action **"Delete project from this computer only."**

   ![The Delete Project dialog, with four numbered callouts. 1: the Project drop-down, showing the project to delete. 2: the Action drop-down, set to “Delete project from this computer only”. 3: the note below it, saying a shared project will still be shared with all project members via internet Send/Receive, and project registration is unaffected. 4: the OK button. The Location table shows the effect: Project on this computer changes from a tick (Before) to a cross (After), while Project on Send/Receive Server and Registration on Registry website keep their ticks. “Also remove me from this project” is unchecked.](assets/ss-05-delete-project-this-computer-only.png)
6. Do a Send/Receive, and select the main project from your list of "New" projects to restore
   the current version. Once that's done, you can turn any automatic Send/Receive options back
   on, if you were using them.

![Diagram of the six steps as a vertical flow. Steps 1 (Send/Receive any unsent changes) and 2 (turn off automatic Send/Receive) sit above a red dashed danger zone. Inside the zone are steps 3 (Revert Books to the chosen history point — from here the main project on this computer holds old text), 4 (create the new project and import the reverted books) and 5 (delete the reverted main project with “Delete project from this computer only”). The zone's heading says no Send/Receive, automatic or manual, may happen inside it, because it would send the old text to the team; its footer says the reverted copy has been deleted and the old text never left this computer. Step 6 (Send/Receive to restore the main project, then re-enable automatic Send/Receive) sits below the zone.](assets/ss-05-snapshot-sequence.svg)

**WARNING:** The middle of this procedure leaves the main project locally reverted to an old
state. Steps 2 and 5 exist specifically to make sure that reverted state never gets sent to the
rest of the team and is cleanly discarded afterward — don't skip or reorder them.

**DANGER:** This is exactly why steps 2 and 5 matter, and why they happen in that order. If a
Send/Receive — automatic or manual — happens anywhere between reverting the books (step 3) and
deleting the reverted copy (step 5), it can push the older, reverted state out to the rest of the
team as if it were *new* work, overwriting everyone else's more recent edits with the
administrator's temporarily-reverted copy. Turning off automatic Send/Receive before the revert,
and deleting the reverted copy with "Delete project from this computer only" (not sending it anywhere)
before resyncing, are what keep that reverted state from ever leaving the administrator's machine.

**INFO:** This procedure applies to Paratext 9.

**What "good" looks like:** you can create a second project that contains the team's content from
an earlier stage of the main project's history.

### Key Takeaways

- Marking milestones with "Mark point in history" and using Compare Versions is enough if all
  the team needs is to *look back* at an earlier stage.
- To get an earlier stage as its *own project*, snapshot the current state directly (new
  project, import books) if the team is already at the point they want.
- To snapshot an *earlier* point, an administrator must temporarily revert the main project's
  books, import them into the new project, then discard the reverted copy locally and resync —
  turning automatic Send/Receive off beforehand and choosing "Delete project from this computer only"
  are the two steps that keep this safe.

## Challenge

**✏️ Try this:** A project administrator you support wants a snapshot of their team's translation
as it stood three months ago — not the current text — so they can archive it as a separate
reference project.

Write out, as a plan a mentor could review:

1. Which of the two procedures above applies, and why.
2. The full step-by-step sequence you would walk this administrator through, in order.
3. What could go wrong if step 2 (turning off automatic Send/Receive) or step 5 ("Delete from
   this computer only," not from the server) were skipped or done incorrectly.

## Change

**✏️ Self-check:** Could you explain, without notes, the difference between "marking a point in
history to compare against later" and "snapshotting an earlier point into its own reference
project" — and when a team would want each one?

**✏️ Reflection:** Think of a real team with a milestone (a completed drafting phase, a checking
round) worth preserving as a standalone reference. Would the simple "current state" procedure
work for them, or would you need the administrator-level revert procedure?

**Next:** The scenario bank brings all five topics together — five realistic project situations,
sequenced from the most straightforward to the most involved, for you to diagnose and resolve.
