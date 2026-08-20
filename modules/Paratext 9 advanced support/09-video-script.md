# Video script

Companion recording script for the course **Advanced Paratext Support**. This is a
concept-and-procedure walkthrough for a working consultant, not a full read-aloud of the
lesson files — it hits the diagnostic framework and the exact ordered steps for each of
the five support/recovery topics, in spoken form, and sends the learner to the numbered
lessons and the scenario bank for hands-on practice and the fine detail (e.g., the precise
XML edits in Lesson 4). The recording happens outside this repo; upload to Cypher for
Business is a separate human step.

**Estimated runtime:** 18-20 minutes.
**Companion lesson file(s):** `01-installing-open-source-texts.md` through
`05-snapshotting-to-a-reference-project.md` — one segment per lesson — plus a short
orientation to `06-scenario-bank.md` at the close.

## Overview / cold open

> Runtime target: ~60-75 seconds. Open on a talking-head or a simple title card — save the
> screen recording for the first demo cue in Segment 1.

**Voiceover:** "Let's look at some advanced topics for Paratext supporters. In the first one, a team may wonder how to do something that seems like it ought to be simple, but they don't know how. And the answer is not immediately obvious.
In the other topics, a team you support may call you in a state of
alarm. Maybe their project history looks like someone's edit just got undone. Maybe a
team leader swears a change appeared under a name that shouldn't have had access to that
book. Maybe a translator tells you their notes — weeks of them — have simply vanished.
None of those are hypothetical. They're the exact five support calls a Paratext-using
consultant fielded, and they're the five situations this course prepares you to walk
into calmly, diagnose correctly, and fix without making things worse. That last part
matters: in a couple of these scenarios, the wrong first move — an instinctive
Send/Receive — is exactly what turns a recoverable problem into a real one. Let's go
through what you're actually looking at in each case, and what you do about it."

## Script

### Segment 1 — An editable copy of an open text (companion to Lesson 1)

> ~2.5 minutes.

| On-screen                                                                                                                                                                                                                                  | Voiceover / talking points                                                                                                                                                                                                                                                                 |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Screen recording: open a Paratext resource (e.g., a public-domain text like the Berean Standard Bible) and try clicking into the text to type. Nothing happens — it stays locked.                                                         | "Here's the moment that confuses almost everyone the first time. This text is public domain. Surely you can edit it? You can't — not like this. A Paratext**resource** is read-only no matter what license the underlying text carries. That's not a bug; it's what a resource is." |
| Cut to a simple title card:**The fix: bring it in as a project, not a resource.**                                                                                                                                                    | "If a team or a workshop needs an editable copy of an open text — to practice on, without any risk to their real translation draft — you don't unlock the resource. You get the text in USFM format from outside Paratext and bring it in as its own project."                           |
| Screen recording, live: main menu →**New Project** → set language in project properties → set project type to standard translation → Project menu → **Manage Books → Import Books** → select the downloaded USFM files. | "Four moves: new project, set the language, set the type to standard translation — not a resource — and import the books. Two good places to find USFM downloads: ebible.org/find, and open.bible/bibles."                                                                               |
| Title card:**Same steps for Paratext 9 and earlier versions.**                                                                                                                                                                       | "This distinction — locked resource versus editable project — hasn't changed across Paratext versions. If you've done this before in an older Paratext, nothing here is different."                                                                                                      |

### Segment 2 — Why project history can look "undone" (companion to Lesson 2)

> ~3.5 minutes. This is the most conceptually tricky segment — don't rush the version-number
> explanation.

| On-screen                                                                                         | Voiceover / talking points                                                                                                                                                                                                                                                           |
| ------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Title card:**Mercurial orders history by version number, not by clock time.**               | "Underneath Paratext, the Mercurial engine that manages Send/Receive numbers every change: version 1, version 2, version 3. That's the whole mechanism behind what you're about to see."                                                                                             |
| `image-4.png` — the edit made at the start of Matthew, with a point marked in project history. | "Picture two translators, both starting from the same version — call it 18. One of them edits Matthew and marks a point in history."                                                                                                                                                |
| `image-5.png` — the edit made at the start of Mark, also marked in project history.            | "At the same time, the other edits Mark, from that same starting version, and also marks a point."                                                                                                                                                                                   |
| `image-6.png` — the merged project history after both users Send/Receive.                      | "Both edits were meant to become 'version 19.' Mercurial can't allow two version 19s, so it picks one, renumbers the other to 20, and the merge becomes 21. The numbering is completely consistent — it's just not necessarily the order the edits actually happened in real time." |
| `image.png` — Compare Versions showing the Matthew edit appearing to be undone.                | "Now someone runs Compare Versions, checking the Matthew edit against the point where Mark was edited — and it looks like the Matthew edit vanished. It didn't. This is exactly the illusion the version-numbering mismatch creates. Nothing was lost."                             |
| Title card:**A wrong system clock doesn't fool Mercurial.**                                 | "One real case: a user's computer clock was months in the future. The timestamp looked newest, but Mercurial tracked the actual version sequence and knew that user's work wasn't really the most recent. It goes by version number, not the clock."                                 |
| Title card:**This has applied since Paratext 7.**                                           | "None of this is new in Paratext 9 — it's been true of every Mercurial-based version, back to 7."                                                                                                                                                                                   |

### Segment 3 — User roles are not a guarantee (companion to Lesson 3)

> ~3.5 minutes. This is a "here's the danger, here's the exact safe response" segment —
> narrate the do-not-do step just as clearly as the do-this step.

| On-screen                                                                                                                                                                        | Voiceover / talking points                                                                                                                                                                                                                                                                                           |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Title card:**Paratext enforces edit access — only while you're inside Paratext.**                                                                                         | "A team leader once asked me: project history shows a change to a book, credited to someone who doesn't have editing rights to that book. Did they bypass the permissions? No. Paratext will never let someone edit through the program without the right role. But that protection only exists inside the program." |
| Title card, bulleted: hard drive corruption / file-system errors / Paratext crashing abnormally / computer crashing abnormally / Windows System Restore reverting`.ptx` files. | "A book file can change outside Paratext entirely — corruption, a bad crash, or, if the project uses`.ptx` files, a Windows System Restore. Windows isn't supposed to touch user data, but Microsoft treats `.ptx` as a system file, so a restore can revert it anyway."                                        |
| Title card:**That change still goes out on the next Send/Receive — except for observers.**                                                                                | "Whatever caused the change, Paratext still sends it to the rest of the team on the next Send/Receive, as if it were a normal edit. The one exception: an observer's local text changes are never sent, because they have no editing role to attribute a change to in the first place."                              |
| **DANGER** title card, red or high-contrast: **Do NOT Send/Receive right away.**                                                                                     | "Here's the part to say slowly and get right: if a user's computer or Paratext just crashed, or files are behaving strangely, the instinct to 'get back in sync' with an immediate Send/Receive is exactly the wrong move — it can push their corrupted files out to everyone else."                                |
| Screen recording, live:**Delete project** from that user's installation, then Send/Receive to pull a clean copy.                                                           | "The safe response is two steps: delete the project from that user's Paratext, then Send/Receive to bring down a clean copy from the server. That gets them back to good without ever pushing the bad files out."                                                                                                    |
| Title card:**Applies to Paratext 9, 8, and 7.**                                                                                                                            | "And this isn't version-specific — it's how the edit-role check has always worked, across 9, 8, and 7."                                                                                                                                                                                                             |

### Segment 4 — Recovering notes and settings with TortoiseHG (companion to Lesson 4)

> ~5 minutes. The longest and most procedural segment — this is the course's hardest topic.
> Keep the two cases (full restore vs. merge) visually distinct on screen.

| On-screen                                                                                                                                                                                                                                    | Voiceover / talking points                                                                                                                                                                                                                                                                                                                                                                                  |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Title card:**Two ways notes go missing: all of them, or just the older ones.**                                                                                                                                                         | "A user's notes, term renderings, or wordlist can vanish because Paratext or their computer crashed — and just like a corrupted book file, that loss propagates to the rest of the team on the next Send/Receive. There are two versions of this call: every note is gone, or only the older ones are, with recent notes still showing."                                                                   |
| Title card:**Paratext's own project history doesn't show this — you need TortoiseHG.**                                                                                                                                                | "Mercurial tracks every change to notes and settings files in just as much detail as it tracks the Scripture text. But Paratext's history view won't surface it. To find and recover a deleted notes file, you go around Paratext, into the Mercurial repository directly, using TortoiseHG."                                                                                                               |
| Screen recording, live: close Paratext, right-click the project folder,**Show more options → HG Workbench.**                                                                                                                          | "First, close Paratext. Then open the HG Workbench from the project folder's right-click menu."                                                                                                                                                                                                                                                                                                             |
| `image-1.png` — a note file deletion visible in recent history.                                                                                                                                                                           | "If the deletion was recent, you can usually spot it directly in the history list, like this."                                                                                                                                                                                                                                                                                                              |
| `image-3.png` — the revision-set query box with `removes(\"*.*\")` entered.                                                                                                                                                             | "If you can't spot it, search directly: type`removes(\"*.*\")` in the revision set query box — press Ctrl-S if that box isn't showing — and it will find the deletion for you."                                                                                                                                                                                                                         |
| `image-2.png` — right-click the file at the revision before the deletion, choose **Save at revision.**                                                                                                                              | "Go to the history point just*before* the deletion, right-click the file, and choose Save at revision. Paratext will suggest a filename with an `@` and a version number tacked on."                                                                                                                                                                                                                    |
| Title card, two columns side by side:**Full restore → strip the @NN, keep the original filename.** / **Merge case → keep the @NN — don't overwrite the current file.**                                                        | "This is the one decision point that matters most. If every note is gone, strip that`@NN` suffix so the file goes back to its original name. If only the *older* notes are gone and recent ones are still showing, you must keep the `@NN` — stripping it here would overwrite the current file and destroy the recent notes you're trying to protect."                                              |
| Title card, numbered: 1) delete first two lines of the new file (`<?xml …?>` and `<CommentList>`) 2) delete the last line of the old file (`</CommentList>`) 3) paste the old content above the new file's remaining content 4) save. | "For the merge case specifically: open the new file in a plain text editor and remove its first two lines. Open the old file and remove its last line. Paste the old file's remaining content above what's left of the new file, and save. That stitches the two note histories into one valid file."                                                                                                       |
| `image-7.png` — Paratext restarted, showing the recovered notes.                                                                                                                                                                          | "Restart Paratext, confirm the notes are back, and do a Send/Receive to circulate them to the rest of the team."                                                                                                                                                                                                                                                                                            |
| **WARNING** title card: **If Paratext calls the file corrupt, check three exact lines.**                                                                                                                                         | "If Paratext instead renames the file`.corrupt`, something went wrong in that edit. Check, in order: the first line must be exactly the XML declaration and appear nowhere else; the second line must be exactly `<CommentList>` and appear nowhere else; the last line must be exactly `</CommentList>` — note the closing slash, which is the only thing distinguishing it from that second line." |
| Title card:**Applies to Paratext 9 and 8.**                                                                                                                                                                                            | "This procedure covers Paratext 9 and 8."                                                                                                                                                                                                                                                                                                                                                                   |

### Segment 5 — Snapshotting an earlier stage into a reference project (companion to Lesson 5)

> ~3.5 minutes. Two cases again — keep "easy" and "administrator" visually separate, and slow
> down for the ordering warning.

| On-screen                                                                                                                                                                                                                                                                                                                                                                                               | Voiceover / talking points                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Title card:**Compare Versions lets you look back. Snapshotting gives you a separate project.**                                                                                                                                                                                                                                                                                                    | "Marking milestones and using Compare Versions is fine if all a team wants is to look back at an earlier stage. But some teams want that earlier text as its own standalone project — to print, or to add to a text collection. That's snapshotting."                                                                                                                                                                                                                                                                              |
| Screen recording, live:**New Project → Manage Books → Import Books**, selecting books from the main project.                                                                                                                                                                                                                                                                                    | "If the team is already at the point they want to preserve, this is the easy case: create a new project, and import the current books straight into it. Nothing else to it."                                                                                                                                                                                                                                                                                                                                                        |
| Title card, numbered, matching the six-step lesson procedure: 1) Send/Receive first 2) turn OFF automatic Send/Receive on the main project 3) Revert books to the chosen history point 4) create the new project and import the reverted books 5) Delete project →**Delete from this computer only** 6) Send/Receive to restore the current main project, then re-enable automatic Send/Receive. | "If you need an*earlier* point — not where the project is now — an administrator does this instead: Send/Receive any pending work, turn off automatic Send/Receive, revert the books to the history point you want, import them into the new project, delete the reverted copy of the main project — **from this computer only** — and then Send/Receive to bring the main project back to its current state."                                                                                                          |
| **DANGER** title card: **Never Send/Receive between the revert and the delete.**                                                                                                                                                                                                                                                                                                            | "Say this part precisely: between step 3, reverting the books, and step 5, deleting that reverted copy, the main project sits locally reverted to an old state. If a Send/Receive — automatic or manual — happens anywhere in that window, it pushes the old, reverted text out to the whole team as if it were new work, overwriting everyone else's more recent edits. That's exactly why automatic Send/Receive gets turned off first, and why the reverted copy gets deleted with 'this computer only,' never sent anywhere." |
| Title card:**Applies to Paratext 9.**                                                                                                                                                                                                                                                                                                                                                             | "This procedure is scoped to Paratext 9."                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |

## Call to action / close

> ~60 seconds.

**On-screen:** Simple closing slide listing the five topics in scenario-bank order:
**Open-source text → History illusion → Suspicious edit → Archived draft → Vanishing
notes.**

**Voiceover:** "Five different support calls, five different fixes — but the same
underlying discipline: figure out what actually happened before you act, and know which
of these situations has a wrong first move that makes things worse. Now it's your turn.
Open the scenario bank and work through all five scenarios — they're sequenced from
straightforward to most involved, and the last one will have you actually running
TortoiseHG yourself. Write out your diagnosis and your step-by-step plan for each one,
and submit your answers to your mentor for feedback before you take the quiz."

## Notes for the presenter

- **Two segments carry real destructive-procedure risk — narrate them precisely, don't
  paraphrase from memory.** Segment 3's "do not Send/Receive, delete and resync instead"
  and Segment 5's revert-then-delete ordering are the two places in this course where
  getting the sequence wrong on camera would teach the *opposite* of the safe procedure.
  Read from the lesson text for those two step lists rather than ad-libbing the order.
- **On production status of this content:** `00-design.md`'s SME knowledge notes record
  that the procedural gaps originally flagged for Scenario 3 (user roles) and Scenario 5
  (reference-project snapshotting) — missing version scope and "what good looks like"
  criteria — were filled by the SME before the lesson content was drafted, and both
  lessons now carry full version scope and success criteria. No hedging language is
  needed in the delivery for those two topics on that basis. Do note, separately, that
  the design doc's SME-consulted field still reads "TBD" at the top of the document even
  though the detailed interview notes further down are complete — that looks like a
  stale summary line in `00-design.md` rather than an open procedural gap; flag it to the
  course lead if it should be corrected, but it isn't a reason to soften this script.
- **Terms to define on screen, not just say:** "resource" vs. "project" (Segment 1),
  "version number" vs. "chronological order" (Segment 2), "observer" role (Segment 3),
  and the `@NN` filename suffix (Segment 4) — these are the exact words learners will
  need to use correctly when they explain these situations to non-technical users.
  Segment 4's two-column "strip the suffix / keep the suffix" card is the single most
  important visual in the whole video — hold it on screen a beat longer than feels
  natural.
- **Live-demo caution — Segment 4 and Segment 5.** If recording any of the delete-project
  or revert-books actions live rather than on a title card, do it against a disposable
  test project, never a real team's project. The lesson's own screenshots
  (`image-1.png` through `image-7.png` in this module folder) were captured from a test
  project built for this purpose — reuse them as static cues rather than re-recording the
  TortoiseHG steps live unless a fresh recording is specifically wanted.
- **Confirm menu paths against the current Paratext 9 build before recording.** The
  lessons name exact menu sequences (e.g., "Manage Books → Import Books," "Delete
  project" with a "Delete from this computer only" option). These are accurate as of the
  SME's account behind the lesson content, but menu wording can drift across minor
  Paratext 9 point releases — do a quick pass in the actual installed version being
  recorded and flag any mismatch to the lesson author rather than quietly changing the
  script's wording on the fly.
- **Pacing.** This script runs long for a support-procedure video because two of the five
  topics (Segments 3 and 5) hinge on getting a sequence of steps exactly right, and one
  (Segment 4) has real sub-branches. Resist compressing Segments 3, 4, or 5 to make the
  runtime shorter — if the video needs to be shorter, cut Segment 1 (the lowest-stakes
  topic) down to its title cards only, not the higher-risk segments.
- **Do not invent additional detail live.** Everything in this script is drawn directly
  from Lessons 1-5 and the scenario bank; if a presenter's question comes up during
  recording that isn't answered in the lesson content (e.g., a Paratext version not
  covered, or a step for a scenario this course doesn't include), take it to the course
  lead rather than answering it on camera.

I tried to create the video by having Claude make a powerpoint, then converted that power point to Video with Google vids. I am not impressed with the results. Video link:
https://docs.google.com/videos/d/1etBXbfO0Pp7PZw_zgyNIpX5IHoUBD2aNM_o_MSRk6ak/play?usp=sharing
