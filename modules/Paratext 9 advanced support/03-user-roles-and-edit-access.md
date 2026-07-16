# User Roles Aren't a Guarantee: Responding to Possible File Corruption

**Estimated time:** 30 minutes

**Purpose:** Prepares you to explain why Paratext's per-book edit-role restrictions are not an
absolute guarantee against unauthorized-looking changes, and to apply the correct, safe response
when a user's local project files may be corrupted — instead of the instinctive but risky move
of an immediate Send/Receive.

## Learning objectives

- You will be able to explain why Paratext's per-book user-role edit restrictions are not an
  absolute guarantee — that a book file can be altered outside Paratext and that change will
  propagate to the whole team via Send/Receive — and apply the correct response (delete the
  project and resync, not an immediate Send/Receive) when a user's local files may be corrupted.

## Connect

**✏️ Reflection:** Think about how you've explained Paratext's user roles and per-book edit
permissions to a team before — as a way of protecting who can change what. Have you ever had to
account for a change showing up in project history that was credited to someone who, as far as
you knew, didn't have editing access to that book? Hold that puzzle in mind.

## Content

**The core idea: Paratext enforces edit access — but only while you're using Paratext**

Paratext itself will never let a user edit a book they don't have permission to edit — that
restriction is real and reliable *inside the program*. But project history can still sometimes
show a change credited to a user without editing access to that book. Users are often baffled by
this. The explanation is that the restriction only holds while someone is working *through*
Paratext. Some computer mishaps, or even manual editing of a file outside Paratext entirely, can
change a book file in ways that get credited to whoever's project it happened in — regardless of
their assigned role.

**What can cause this:**

- Hard drive corruption
- File system errors
- Paratext crashing without closing normally
- The computer crashing without closing normally
- A Windows "restore from history" procedure — if a project uses the `.ptx` file extension for
  its book files, a Windows System Restore run to fix an unrelated computer problem will revert
  those book files to the date of that restore point. Windows System Restore isn't supposed to
  touch user data, only system files — but Microsoft treats the `.ptx` extension as a system
  file, so it gets swept up anyway.

**If a book file changes outside Paratext, Paratext will still send the changed version to the
repository the next time that user does a Send/Receive** — the corruption or reversion looks,
to the rest of the team, exactly like a normal edit. **The one exception is project observers:**
if an observer's local text changes, Paratext does not send those changes out, because observers
have no editing role to attribute the change to in the first place.

**DANGER:** If a user experiences a computer crash, or file system errors that make project
files disappear or behave strangely, they should **not** do a Send/Receive right away. Doing so
may send their corrupted files out to every other user on the project. Instead, the correct
response is:

1. Delete the project from that user's Paratext installation ("Delete project").
2. Do a Send/Receive to pull down a clean copy of the project from the server.

This gets the affected user back to a good copy without ever pushing their corrupted local files
out to the team.

**INFO:** This applies to Paratext 9, and to Paratext 8 and 7 as well — the underlying behavior
(edit-role checks live inside Paratext, not in the shared repository) hasn't changed across
these versions.

**What "good" looks like:** you can explain, to a non-technical team member, how and why project
history can show a mysterious edit credited to someone who does not have editing access to that
book — without that explanation ever needing to invoke anyone actually bypassing Paratext's
permissions.

### Key Takeaways

- Paratext's user-role edit restrictions only prevent unauthorized edits made *through*
  Paratext. They cannot prevent a book file from being altered outside the program.
- Hard drive corruption, file system errors, an abnormal Paratext or computer shutdown, or a
  Windows System Restore reverting `.ptx` files can all change project files outside Paratext's
  control.
- Any such change gets sent to the whole team on the next Send/Receive — except for project
  observers, whose local changes are never sent.
- If a user's files may be corrupted, the safe response is: delete the project locally, then
  Send/Receive to get a clean copy — never an immediate Send/Receive on the possibly-corrupted
  files.
- This behavior applies across Paratext 9, 8, and 7.

## Challenge

**✏️ Try this:** A user on a team you support tells you their computer crashed unexpectedly
while Paratext was open, and now some odd things are appearing in the project. They ask, "Should
I just do a Send/Receive to get everything back in sync?"

Write out:

1. What you would tell this user to do instead, and why.
2. How you would explain — to this non-technical user — why a change could show up in project
   history under a name that shouldn't have had editing access to that book.
3. What you would check afterward, once their project has been deleted and re-synced, to confirm
   things are back to normal.

## Change

**✏️ Self-check:** Could you explain to a team leader, in plain language, why "only certain
people can edit this book" doesn't fully protect them from a corrupted file spreading to
everyone? And could you state the correct recovery response without hesitating?

**✏️ Reflection:** Think of a real team whose computers are older, share a poor power supply, or
are otherwise prone to crashes or improper shutdowns. How would you introduce this "don't
Send/Receive right away" guidance to them *before* they need it, rather than after?

**Next:** Lesson 4 covers a related but different recovery case — when it isn't the Scripture
text that's been lost, but a user's notes, term renderings, or other project settings.
