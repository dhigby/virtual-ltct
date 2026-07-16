# Quiz

## Assessment quiz (10 questions)

This quiz covers all five lessons of the Advanced Paratext Support module — installing
open-source texts as editable projects, why project history isn't always chronological,
why user-role edit restrictions aren't foolproof, recovering lost notes and settings, and
snapshotting an earlier project state into a reference project. You need **80% (8/10)** to
pass. Questions are a mix of recall, scenario-based application, and true/false. Everything
asked here is drawn directly from the lesson content — work them first. (The five-scenario
bank, graded separately by a mentor, is where hands-on execution of each recovery procedure
is verified; this quiz checks the conceptual and diagnostic knowledge behind it.)

---

### Section 1: Installing an Open-Source Text as an Editable Project (Questions 1-2)

**Question 1:** A translator downloads the Berean Standard Bible (BSB), aware that it's
public domain, and adds it to Paratext — but then finds she cannot type into it. What
explains this?
- A) The BSB is not actually public domain despite claims
- B) Paratext resources are always read-only, no matter what license the underlying text
  carries
- C) She needs administrator rights before she can edit any resource
- D) The USFM file was corrupted during download

**Question 2:** You're setting up a training workshop where ten participants each need an
editable copy of the same open-license text to practice on. Which sequence of steps is
correct?
- A) Add the text as a Paratext resource, then ask Paratext support to unlock editing for
  the workshop
- B) Download the text in USFM format from a site such as ebible.org/find or
  open.bible/bibles, create a new project, specify the language, set the project type to a
  standard translation project, then import the books
- C) Copy the resource's book files directly onto each participant's computer and rename
  the file extension
- D) Use the Quotation Rules dialog to change the resource's edit permissions

---

### Section 2: Why Project History Isn't Always Chronological (Questions 3-4)

**Question 3:** What does Paratext's project history view actually order changes by?
- A) The timestamp shown on each user's own computer clock
- B) Mercurial version number — assigned in the order Send/Receive resolves conflicting
  versions, which is not necessarily the true chronological order
- C) Alphabetical order of the book that was changed
- D) The order in which points were marked with "Mark point in history"

**Question 4:** Two users, both starting from the same version, each edit a different book
(Matthew and Mark) and Send/Receive around the same time. Afterward, running Compare
Versions across their two history points makes the Matthew edit look as though it was
undone by the Mark edit. True or False: this means the Matthew edit was actually lost and
needs to be redone.
- A) True
- B) False

---

### Section 3: User Roles Aren't a Guarantee (Questions 5-6)

**Question 5:** A user's computer crashes unexpectedly while Paratext is open, and
afterward some project data looks odd. What should a consultant tell this user to do
first?
- A) Do a Send/Receive immediately, so anything lost can be restored from the server
- B) Delete the project from that user's Paratext installation, then do a Send/Receive to
  pull down a clean copy from the server
- C) Ask every other team member to also delete and resync their own projects
- D) Reassign the affected books to a different user role to prevent further changes

**Question 6:** True or False: If a project observer's local text is altered (for example,
by file corruption), Paratext will still send that change out to the whole team on the
observer's next Send/Receive, exactly as it would for an editor.
- A) True
- B) False

---

### Section 4: Recovering Lost Notes, Term Renderings, and Settings (Questions 7-8)

**Question 7:** Why can't a lost notes file typically be recovered just by looking at
Paratext's own project history view?
- A) Paratext's history view only shows changes made in the last 24 hours
- B) Mercurial tracks detailed changes to notes files and other settings, but Paratext's
  own history display doesn't surface that detail — recovering them requires TortoiseHG
  or direct Mercurial commands
- C) Notes files aren't stored in the Mercurial repository at all
- D) Project history only tracks changes made by project administrators

**Question 8:** A user reports that their most recent notes are visible, but older notes
seem to have vanished entirely. What does this pattern most likely indicate, and what does
recovering it require?
- A) The notes file was deleted at some point and new notes have been created since —
  recovery requires saving the old file (keeping its `@NN` suffix) and merging its content
  into the current file by editing the XML structure
- B) The user's role was changed to Observer, which blocked older notes from syncing
- C) A straightforward "Save at revision" from the immediately preceding history point,
  discarding the `@NN` suffix, resolves this completely
- D) This always means the notes were never actually deleted, just hidden by a filter

---

### Section 5: Snapshotting an Earlier Stage into a Reference Project (Questions 9-10)

**Question 9:** A project administrator wants to preserve the team's translation exactly
as it stood three months ago, in a separate reference project — not the project's current
state. Which procedure is required?
- A) Simply create a new project and import the current book files
- B) Temporarily revert the main project's books to the desired history point, import the
  reverted books into the new project, then delete the reverted copy locally and resync
  the main project
- C) Rename the current project and mark it read-only
- D) Use Compare Versions to view the earlier text; no separate project is needed

**Question 10:** While carrying out the earlier-point snapshot procedure, an administrator
skips turning off automatic Send/Receive before reverting the books. Based on why that step
exists, what is the likely risk?
- A) No risk — automatic Send/Receive only affects resource projects, not standard
  translation projects
- B) An automatic Send/Receive could push the temporarily reverted (older) state of the
  main project's books out to the rest of the team before it's discarded
- C) It would only slow down the import step, with no effect on the team's copy
- D) It would prevent the new reference project from being created at all

---

## Answer key

1. B | 2. B | 3. B | 4. B | 5. B | 6. B | 7. B | 8. A | 9. B | 10. B
