# Quiz

## Assessment quiz (11 questions)

This quiz covers all five lessons of the Advanced Paratext Support module — installing
open-source texts as editable projects, why project history isn't always chronological,
why user-role edit restrictions aren't foolproof (and what to check before undoing a
corrupted change), recovering lost notes, wordlist and term renderings, and snapshotting an earlier project
state into a reference project. You need **9/11 (about 80%)** to pass. Questions are a mix
of recall, scenario-based application, and true/false. Everything asked here is drawn directly from the lesson content — work them first. (The five-scenario
bank, graded separately by a mentor, is where your written plan for each recovery procedure
is checked; this quiz checks the conceptual and diagnostic knowledge behind it.)

---

### Section 1: Installing an Open-Source Text as an Editable Project (Questions 1-2)

**Question 1:** A translator downloads the Berean Standard Bible (BSB), aware that it's
public domain, and adds it to Paratext — but then finds she cannot type into it. What
explains this?
- A) She has to tick "Editing Enabled" in the resource's Project properties first; Paratext
  leaves every new resource locked until someone does
- B) She needs administrator rights before she can edit any resource
- C) Paratext resources are always read-only, no matter what license the underlying text
  carries
- D) The USFM file was corrupted during download

**Question 2:** You're setting up a training workshop where ten participants each need an
editable copy of the same open-license text to practice on. Which sequence of steps is
correct?
- A) Download the text in USFM format from a site such as ebible.org/find or
  open.bible/bibles, create a new project, specify the language, set the project type to a
  standard translation project, then import the books
- B) Add the text as a Paratext resource, then give each participant the Translator role on
  it so they can type into it
- C) Copy the resource's book files directly onto each participant's computer and rename
  the file extension
- D) Add the text as a Paratext resource on one computer, and let the participants take
  turns editing it

---

### Section 2: Why Project History Isn't Always Chronological (Questions 3-4)

**Question 3:** What does Paratext's project history view actually order changes by?
- A) The date and time each change was saved, taken from the clock on that user's computer
- B) The order in which points were marked with "Mark point in history"
- C) The order in which users opened the project that day
- D) Mercurial version number — assigned in the order Send/Receive resolves conflicting
  versions, which is not necessarily the true chronological order

**Question 4:** Two users, both starting from the same version, each edit a different book
(Matthew and Mark) and Send/Receive around the same time. Afterward, running Compare
Versions across their two history points makes the Matthew edit look as though it was
undone by the Mark edit. True or False: this means the Matthew edit was actually lost and
needs to be redone.
- A) True
- B) False

---

### Section 3: User Roles Aren't a Guarantee (Questions 5-7)

**Question 5:** A user's computer crashes unexpectedly while Paratext is open, and
afterward some project data looks odd. What should a consultant tell this user to do
first?
- A) Do a Send/Receive immediately, so anything lost can be restored from the server
- B) Ask every other team member to also delete and resync their own projects
- C) Delete the project from that user's Paratext installation ("Delete project from this
  computer only"), then do a Send/Receive to pull down a clean copy from the server
- D) Reassign the affected books to a different user role to prevent further changes

**Question 6:** True or False: If a project observer's local text is altered (for example,
by file corruption), Paratext will still send that change out to the whole team on the
observer's next Send/Receive, exactly as it would for an editor.
- A) True
- B) False

**Question 7:** After a computer crash, a team member did a Send/Receive straight away, and
the corrupted book files have now reached the rest of the team. The project administrator
finds the history point where the corruption came in. Before choosing "Undo Changes" on that
point, what should the administrator do, and why?
- A) Nothing extra — "Undo Changes" on that point puts back the text the team had just
  before the corruption arrived
- B) Nothing can be done in project history now; ask the team to restore the book from
  their own backups
- C) Check the affected user's role — if they had no edit access to the book, Paratext will
  have refused the change and there is nothing to undo
- D) Click the changed book's link to open Compare Versions; if the right-hand pane isn't the
  latest version, use "Revert Books" from the history point that holds the latest version

---

### Section 4: Recovering Lost Notes, Wordlist and Term Renderings (Questions 8-9)

**Question 8:** Why can't a lost notes file typically be recovered just by looking at
Paratext's own project history view?
- A) Mercurial tracks detailed changes to notes files and other settings, but Paratext's
  own history display doesn't surface that detail — recovering them requires TortoiseHG
  or direct Mercurial commands
- B) Notes files aren't stored in the Mercurial repository at all
- C) Notes files are in the repository, but Mercurial keeps only the latest copy of them, so
  older versions are gone
- D) Project history only tracks changes made by project administrators

**Question 9:** A user reports that their most recent notes are visible, but older notes
seem to have vanished entirely. What does this pattern most likely indicate, and what does
recovering it require?
- A) The notes file was deleted at some point and new notes have been created since —
  recovery requires saving the old file (keeping its `@NN` suffix) and merging its content
  into the current file by editing the XML structure
- B) The older notes were never sent to the server, so they can't be recovered; ask the user
  to type them in again
- C) Restore the old notes file over the current one with "Save at revision", so all the
  notes come back
- D) The user's role was changed to Observer, which blocked older notes from syncing

---

### Section 5: Snapshotting an Earlier Stage into a Reference Project (Questions 10-11)

**Question 10:** A project administrator wants to preserve the team's translation exactly
as it stood three months ago, in a separate reference project — not the project's current
state. Which procedure is required?
- A) Create a new project and import the main project's current book files into it
- B) Rename the current project and mark it read-only
- C) Temporarily revert the main project's books to the desired history point, import the
  reverted books into the new project, then delete the reverted copy locally and resync
  the main project
- D) Use Compare Versions to view the earlier text; no separate project is needed

**Question 11:** While carrying out the earlier-point snapshot procedure, an administrator
skips turning off automatic Send/Receive before reverting the books. Based on why that step
exists, what is the likely risk?
- A) No risk — reverting the books only changes the administrator's own computer
- B) An automatic Send/Receive could push the temporarily reverted (older) state of the
  main project's books out to the rest of the team before it's discarded
- C) It would only slow down the import step, with no effect on the team's copy
- D) It would prevent the new reference project from being created at all

---

## Answer key

1. C | 2. A | 3. D | 4. B | 5. C | 6. B | 7. D | 8. A | 9. A | 10. C | 11. B
