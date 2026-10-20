# Video script

Companion recording script for the course **Advanced Paratext Support**. This is the
**course-introduction (overview) video**: a short, trailer-style orientation that sits on
Lesson 1. Its job is to make a working consultant want to take the course and to show them
the arc before they start the hands-on lessons. It is not a walkthrough of the procedures;
the lessons and the scenario bank teach those. (The longer concept-and-procedure
walkthrough lives separately in `09-video-script.md`.) The recording happens outside this
repo; hosting the video and linking it from the course is a separate human step.

**Estimated runtime:** about 3 minutes (roughly 470 spoken words, including the cold open and
close; trim a sentence or two from the lesson beats if it runs over).
**Companion lesson file(s):** `01-installing-open-source-texts.md` (the video sits on Lesson 1
as the course overview). It previews `01` through `05`, one beat per lesson, and closes on
`06-scenario-bank.md`.

## Overview / cold open

> Runtime target: ~30 seconds. Open on a talking head or a plain dark card, not a title
> slide and not a list of topics. Say the first line before anything appears on screen.

**On-screen:** Presenter to camera. Then, as each situation is named, a short text card
appears and clears: **History that looks like an edit was undone.** / **A change under a
name that shouldn't have had access.** / **Weeks of notes, gone.**

**Voiceover:** "The call comes in, and the team on the other end is alarmed. Their project
history looks like someone's edit just got undone. Or a team leader is certain a change
appeared under a name that never had access to that book. Or a translator tells you their
notes, weeks of them, have simply vanished. You can't read their language, and you don't
have to. You need to know what the tool is doing, what's safe to do first, and which move to
avoid."

## Script

| On-screen | Voiceover / talking points |
| --- | --- |
| Presenter to camera. Lower-third: **Advanced Paratext Support**. | "This course is about walking into those calls calmly, diagnosing correctly, and not making it worse. In some of these situations, the instinctive first move, a quick Send/Receive to get back in sync, is exactly what turns a recoverable problem into a real one. By the end, you'll be able to explain what you're seeing, say what's safe to do first, and carry out the recovery, with your mentor alongside you." |
| Title card: **1. An editable copy of an open text.** (No screenshot; Lesson 1 has no asset. A static card is fine.) | "It starts with something that sounds simple. A team, or a workshop, wants a text they can freely edit and practice on. But public domain doesn't make a Paratext resource editable. You'll learn how to bring the text in as its own project." |
| `assets/ss-02-project-history-two-points.png`, then a quick cut to `assets/ss-02-compare-versions-apparent-undo.png`. Title card: **2. History that looks undone.** | "Then the alarm. History that looks as if an edit was undone. It wasn't. You'll learn why Paratext's history can look out of order, and how to work out the real sequence before anyone starts retyping work." |
| `assets/ss-03-history-undo-changes-compare.png`. Title card: **3. A change under the wrong name.** | "Next, a change under a name that shouldn't have had access. Edit roles only hold inside Paratext, so a crash can slip past them. You'll learn the safe response, and, if corrupted files have already been sent, why the obvious Undo Changes can bring back out-of-date text, and what to check first." |
| `assets/ss-04-hg-deleted-note-file.png`, then `assets/ss-04-hg-save-at-revision.png`. Title card: **4. Notes that vanished.** | "Then the vanishing notes. Paratext's own history won't show you these, so you'll go one level down with TortoiseHG, find the deleted file, and bring it back." |
| Title card: **5. Preserving an earlier stage.** (No screenshot; Lesson 5 has no asset.) | "And finally, the other direction. Saving an earlier stage of the team's text as its own reference project, in an order of steps that keeps a temporary revert from ever reaching the team." |
| Closing course-map slide: **Lessons 1-5 → Scenario bank → Mentor review → Quiz.** Small text under it: **about 4.5 hours, at your own pace.** | "Here's how it works. Five lessons, about 35 to 40 minutes each, each opening with a situation from the field. Then a scenario bank of five realistic cases: you write up your plan and send it to your mentor. Then an eleven-question quiz. Have the two earlier Paratext courses behind you, and if your connectivity is limited, download TortoiseHG ahead of Lesson 4." |

## Call to action / close

> ~20 seconds. End on the presenter to camera, then the Lesson 1 title.

**On-screen:** Presenter to camera. Final card: **Open Lesson 1: An editable copy of an open
text.**

**Voiceover:** "So let's get started. Open Lesson 1 and work through it. Then keep going, in
order, and the next time that alarmed call comes in, you'll already know what to check first,
and what not to touch."

## Notes for the presenter

- **This is a trailer, not a tutorial.** Don't demonstrate any procedure on camera. If it runs
  past about three minutes, cut words from the lesson beats, not the cold open or the
  "not making it worse" promise.
- **Keep the promise accurate.** The "wrong first move" is the immediate Send/Receive after a
  crash or suspected file corruption (Lesson 3), and Send/Receive between the revert and the
  delete in the Lesson 5 procedure. Say "can turn a recoverable problem into a real one", not
  "will". Don't claim the course makes every recovery safe or automatic; the learner performs
  recoveries with mentor support.
- **Never read or judge text.** The screenshots in `assets/` are from a test project and
  contain example wording and user names. Show them only as brief visuals of what the *tool*
  is displaying; don't read the text aloud, and don't say which wording is right. Say
  nothing about any real language.
- **Screenshot use.** Cue the screenshots by filename only. No new assets are needed. Lessons
  1 and 5 have no screenshot, so use plain title cards there. If a recorded clip is wanted for
  either later, flag it to the lesson author.
- **Don't quote numbers you haven't checked.** Times (35, 40, 35, 40, 30 minutes; about 4.5
  hours total with the scenario bank) and the 11-question quiz come from `README.md` and
  `00-design.md`. If those change, update the closing slide and voiceover to match.
- **"Alongside your mentor" is accurate.** README says recoveries are performed "with mentor
  support" and the scenario bank sends write-ups to a mentor. Don't promise live,
  hands-on help during the lessons themselves.
- **Terms to define on screen, not just say:** *resource* vs. *project* (Lesson 1 beat), and
  *Send/Receive* in the promise line, for anyone who has only used it automatically.
- **Pronunciation:** say TortoiseHG as "tortoise H-G". No language data is spoken.
- **Confirm before recording:** menu names are not used in this script, so there is no menu-drift
  risk here, but the course map slide should be checked against the published course in case the
  lesson titles or timings have changed.
- **Gaps flagged (not filled here):** (1) Lesson 1 has no `**Watch the video:**` line yet; it
  needs `**Watch the video:** _To be recorded at stage 8._` from `module-author`. (2) `README.md`'s
  course package table lists `09-video-script.md` but not this file.
