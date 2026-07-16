# Recovering Lost Notes, Term Renderings, and Project Settings

**Estimated time:** 40 minutes

**Purpose:** Prepares you to recover a user's lost project notes, term renderings, or other
non-text project settings using TortoiseHG — going beyond what Paratext's own project history
view can show you — when a plain text rollback won't bring these back.

## Learning objectives

- You will be able to recover a previous version of project notes, term renderings, or other
  non-text project settings that are not restored by a plain text rollback.

## Connect

**✏️ Reflection:** Think about a time a user reported that their notes seemed to have
disappeared — maybe all of them, maybe just the older ones, with only the newest still visible.
Did you know, at the time, how to actually look for and recover them? This lesson walks through
exactly that recovery, step by step.

## Content

**How notes and settings get lost**

A user may ask for help because a batch of project notes has gone missing — sometimes every
single note a user submitted, sometimes just the older ones (with only the very latest notes
still visible). The usual cause is a computer mishap: Paratext crashing without shutting down
properly, or the computer itself crashing while Paratext was running. Either can delete a user's
notes file, the list of term renderings, or the project wordlist — and then, just like with the
Scripture text itself, that deletion gets propagated to every other project user the next time
someone does a Send/Receive.

**Why Paratext's own history view isn't enough here**

Mercurial keeps a detailed record of changes to the notes files and other project settings,
exactly as it does for the project text. But Paratext's own project history display does not
surface these details. To find and recover a deleted notes file, you need a way to connect to
the Mercurial repository more directly than Paratext's interface allows — either by downloading
the TortoiseHG tool, or by running Mercurial commands directly from a command prompt.

**Getting TortoiseHG**

Download it from [tortoisehg.bitbucket.io/download](https://tortoisehg.bitbucket.io/download/index.html)
— get the latest version for 64-bit Windows. (If you're on 32-bit Windows, download that version
instead. All Windows 11 installations are 64-bit; some Windows 10 or earlier installations were
32-bit. You can confirm which you have by checking the Windows version info in Windows
Settings.)

**Finding where a file was deleted**

1. Close Paratext.
2. Open the HG Workbench: in File Viewer, right-click the project folder, choose "Show more
   options," then pick HG Workbench under the TortoiseHG section.
3. If you know the file was recently deleted, inspect the recent history points to find it. For
   example, in this history, one user's note file was deleted:

   ![note file deleted](image-1.png)

4. If you can't find it that way, search for a file deletion directly: type `removes("*.*")` in
   the revision set query box.

   ![alt text](image-3.png)

   If the revision set query box isn't visible, click in the list of revisions and press
   `Ctrl-S`.

**Recovering the deleted file**

Go to the history point just *before* the file was deleted, right-click on the file, and choose
"Save at revision."

![alt text](image-2.png)

The save dialog will suggest a filename with `@` followed by a version number appended. To
restore the file under its original name, delete those extra characters before saving. Once
that's done, restart Paratext — you should now see the missing notes — and do a Send/Receive to
circulate them to the rest of the project's users.

![alt text](image-7.png)

**When only the older notes are missing (a merge, not a straight restore)**

Sometimes a user has some recent notes visible, but the older ones are gone. This usually means
the notes file was deleted a while ago, and the user has simply been creating new notes since
then in a fresh file. Recovering this case means merging the old notes back into the new file,
not just restoring an old version outright:

1. Close Paratext if it's open. Find the deleted file and save it from the previous revision as
   above — but this time, **keep** the `@NN` suffix on the filename. (If you strip it here, you
   would overwrite the current file and lose the new notes it contains.)
2. Open the *new* file in Notepad (or another plain text editor) and delete its first two lines:

   ```
   <?xml version="1.0" encoding="utf-8"?>
   <CommentList>
   ```

3. Open the *old* file and delete its last line:

   ```
   </CommentList>
   ```

4. Select all of the old file's remaining content, paste it above the contents of the new file,
   and save.
5. Start Paratext. It should now show all the notes — old and new.

**WARNING:** If Paratext reports that the notes file is corrupt after this, something went wrong
in the edit. Paratext will rename the bad file to `Notes_User Name.corrupt`. Open it and check,
in order:

1. The **first line** must be exactly `<?xml version="1.0" encoding="utf-8"?>`, and that line
   must not occur anywhere else in the file.
2. The **second line** must be exactly `<CommentList>`, and that must not occur anywhere else in
   the file either.
3. The **last line** must be exactly `</CommentList>` — note the `/` between `<` and
   `CommentList>`, which is what distinguishes it from the second line.

**INFO:** This procedure applies to Paratext 9, and also to Paratext 8.

### Key Takeaways

- Notes files, term renderings, and the project wordlist can be silently deleted by a Paratext
  or computer crash, and that deletion can then spread to the whole team via Send/Receive.
- Paratext's own project history view does not show enough detail to recover these files — you
  need TortoiseHG (or direct Mercurial commands) to see and restore them.
- To restore a fully-lost file: find the history point just before the deletion, "Save at
  revision," and strip the `@version` suffix to restore the original filename.
- To recover when only *older* notes are missing: save the old file *with* its `@NN` suffix,
  then manually merge its content into the current file by editing the XML structure with a
  plain text editor.
- A corrupt result almost always traces back to a mismatch in the three key XML lines — check
  those first.

## Challenge

**✏️ Try this:** A user reports that only their most recent handful of notes are visible — older
ones seem to have vanished entirely, but nothing looks broken otherwise.

Write out, as a plan you could hand a mentor to review:

1. Which of the two recovery procedures above applies to this case (a straight file restore, or
   a merge), and how you can tell.
2. The exact sequence of steps you would follow, including the specific `@NN` handling and the
   XML lines you would edit.
3. What you would check afterward to confirm the recovery worked, and what you would look for if
   Paratext instead reports the notes file as corrupt.

## Change

**✏️ Self-check:** Could you walk through the TortoiseHG steps — HG Workbench, finding a
deletion, "Save at revision," and the `@NN` handling — without this lesson open in front of you?

**✏️ Reflection:** Think of a real team whose computer setups make crashes or improper shutdowns
likely (unreliable power, older hardware). Would you know, right now, how to talk them through
this recovery over a phone call if you couldn't be there in person?

**Next:** Lesson 5 turns from *recovering* lost material to *deliberately preserving* it —
saving an earlier stage of the translation text into its own reference project before it
changes further.
