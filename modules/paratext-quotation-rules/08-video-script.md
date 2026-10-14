# Video script

Companion recording script for the course **Configuring Quotation Rules in Paratext**.
This is an orientation/overview video: a screen-capture walkthrough that shows learners
the shape of the tool and the workflow before they sit down and do the hands-on lessons.
It is **not** a full read-aloud of the lessons — the detailed, click-by-click exercise
work happens in the numbered lesson files, where learners can pause and work at their own
pace. The recording happens outside this repo; hosting the video externally and linking it
from the course in Moodle is a separate human step.

**Estimated runtime:** 13-15 minutes (overview companion to a multi-hour hands-on course).
**Companion lesson file(s):** `01-what-the-quotation-check-does.md` through
`05-scenario-bank.md` — one orientation segment per lesson.

## Overview / cold open

> Runtime target: ~60-90 seconds. Open on a live screen recording, not a title slide.

**On-screen:** Screen recording of the `TAMBA` project, freshly received and untouched, so
its Quotation Rules are still Paratext 9.5's defaults. The **Run basic checks** dialog is
open with **Quotations** ticked (and nothing else) and **Choose Books** set to all NT books
in the project. The presenter clicks **OK**, and the results panel lists five results, all
in John 17 — at 17:1, 17:6, 17:13, 17:20 and 17:24 — each reading *"Opening quote mark found
without matching closing quote mark: “"*. Hold on the list long enough for all five to
register. Do not open the verses. (This is the state captured in
`assets/ss-01-run-basic-checks.png` and `assets/ss-01-default-results.png` — record it live.)
Optional lower-third over the list: **Text: correct. Settings: Paratext's defaults.**

**Voiceover:** "Here's a message you'll get on a real project. The team lead says the
quotation check found five places in John 17 where they forgot to close a speech, and asks
whether they should add the closing marks. This is what they're looking at: five results,
all in John 17, each one saying a speech was opened and never closed. They look like five
real mistakes, but they aren't. The text is correct. What's wrong is the settings. This
project still has the Quotation Rules Paratext gives every new project. Nobody on the team
chose them, and they don't describe how this team writes. If the team adds five closing
marks, they've changed correct Scripture to satisfy settings nobody chose. Over the next
few minutes I'll show you the two settings that turn this check into a list you can trust.
Then you'll set them up yourself."

## Script

### Segment 1 — Why a check on the defaults can't be trusted yet (companion to Lesson 1)

> ~2 minutes. Concept framing. Keep it fast — this is the "why," the lesson carries the "how."

| On-screen | Voiceover / talking points |
| --- | --- |
| Reopen the **Run basic checks** dialog (it closed when OK was clicked in the cold open). Hover over the yellow information bar at the bottom of the dialog so its text is legible on screen. | "See this yellow bar? It says the accuracy of the check depends on the information you've given it. That's the whole story. The check is only as good as the settings it's reading." |
| Cancel the dialog. Open **☰ > Project settings > Quotation Rules**, **Quote marks** tab, on `TAMBA` without changing anything: `“ “ ”` at First level, `‘ ‘ ’` at Second level, `“ “ ”` at Third level, and **Continuer required at** reading `p p/q1 b/q1`. (State shown in `assets/ss-02-quote-marks-tab-defaults.png`.) Click **Cancel**. | "And here's the trap. This tab isn't empty. Every new project in Paratext 9.5 starts with these marks: curly double quotes, curly single quotes inside them, and a default list down here. So the check runs and reports results, and everything looks set up. But Paratext chose these settings, not the translation team. Until someone compares every cell with the team's own conventions, the check is measuring their text against somebody else's rules." |
| Cut to a simple two-box slide: **1. Quote marks tab — which characters are quote marks, and at what level.** **2. Quotation types tab — when marks are expected.** | "The check reads two inputs. Input one: which characters in your text actually are quotation marks, and at which nesting level. Input two: for each kind of speech, whether marks should be there, should never be there, or don't matter. Both have to describe the team's conventions before the check's results mean anything." |
| Three-line slide: **A few plausible results — TAMBA: 5** / **A flood — RUNDA: 1110** / **A false silence — MENDA: "No errors found"**. On the second line, cut briefly to the `RUNDA` results panel at the defaults showing the 1110 count (`assets/ss-02-runda-default-results.png`); on the third, cut briefly to a live run on `MENDA` at the defaults returning *"No errors found"*. Return to the slide. | "Defaults mislead you in one of three ways. You get a few plausible results, like the five you just saw. Or you get a flood: Runda, which you'll meet in Lesson 2, reports more than a thousand. Or you get a false silence: Menda's text is full of dialogue, but none of its marks are in the defaults, so the check doesn't recognize anything as a quote mark and says 'No errors found'. That isn't a clean bill of health. It means the check isn't recognizing anything. Five results, a thousand, or none, you can't trust any of them until the settings match the team's conventions. Once they do, anything that still points at the text goes back to the translation team as a question. Changing the text is their call, not yours." |

### Segment 2 — The Quote marks tab (companion to Lesson 2)

> ~3.5 minutes. The heart of the video. Show the navigation and the grid; do NOT walk every cell for every level — show the pattern once.

| On-screen | Voiceover / talking points |
| --- | --- |
| Live navigation: click the project menu **☰ > Project settings > Quotation Rules**. (State shown in `assets/ss-02-project-menu-quotation-rules.png`.) Land on the **Quote marks** tab, still showing the defaults. | "Here's input one. Project menu, Project settings, Quotation Rules, and the Quote marks tab. This is where you tell Paratext the characters." |
| Slow pan across the three-by-three grid, still at the defaults. Highlight the three rows, then the three columns. (Layout in `assets/ss-02-quote-marks-tab-defaults.png`.) | "Three rows are your nesting levels: first-level speech, a quote inside a quote, and a quote inside that. Three columns: the Opening mark, the Quote Continuer at a new paragraph, and the Closing mark. The middle column answers one question: when a speech runs across paragraphs, does this language repeat a mark at the head of each new paragraph? Your job here isn't to fill in a blank grid. You compare every cell with the team's conventions and change the ones that don't match. For Tamba, the whole first level already matches. Tamba repeats its opening mark at each new paragraph, and so does the default. But Tamba repeats nothing at the second and third levels, and the defaults do, so those two continuer cells have to change." |
| Click the dropdown arrow (▼) on the Second level **Quote Continuer at new paragraph** cell so the list appears, and select *none*. Repeat for Third level. Then show the **Example** preview at the bottom reading `“…‘…’…”`. | "Always choose characters from the dropdown on each cell, never by typing. That's how you know you picked U+201C and not a straight keyboard quote. Choosing *none* counts as a change too. A cell you didn't look at is still Paratext's choice, not the team's. Each time, glance down at the Example preview. If the sample sentence shows the marks you expect, you got it right. It's your best safety check." |
| Click into the **Continuer required at** field and replace `p p/q1 b/q1` with `p p/q1 m/q1 s1/pi pi/pi b/q1`. Click **OK**. | "This small field matters far more than its size suggests. The continuer cell tells the check *which* character continues a speech. This list tells it *where* to expect that character, by naming the paragraph markers. You build the list by looking at where the project's text actually carries the continuer. Lesson 2 shows you how." |
| Re-run the Quotations check on `TAMBA`, all NT books with **Choose Books**. The panel reads *"No errors found"*. | "Same project, same text, nothing typed into Scripture, and the five John 17 results are gone. One more thing, though: a clean check on its own doesn't prove the settings are right. The check can only report what the current text happens to trip over. Some of the changes you just made never produced a result at all, so the check would have gone quiet with them still wrong. Set what the team's conventions say, and don't stop just because the list is empty." |
| Brief cut to the Runda Quote marks tab after correction, showing guillemets and a filled First level Continuer cell. (`assets/ss-02-runda-quote-marks.png`.) | "Different language, same grid. Runda uses French guillemets, and like Tamba it repeats its opening mark at each new paragraph, so its continuer cell holds « instead of “. None of those characters were in the defaults, which is why Runda came back with more than a thousand results. The tab doesn't change from language to language. Only the characters do." |
| Cut to Language Settings: **☰ > Project settings > Language Settings > Other Characters** tab, with `’` in the **Word-medial punctuation** field, warning icon visible. (`assets/ss-02-language-settings-other-chars.png`.) | "Here's a gotcha that traps almost everyone, and it doesn't play out the way you'd expect. If your closing single quote is U+2019, and the same character is also used as an apostrophe inside words, the check reads every apostrophe as a stray closing mark. This field, Word-medial punctuation under Language Settings, Other Characters, looks built for exactly this case. Add the character here, save, run a fresh check, and the apostrophes are still flagged. That's confirmed on Paratext 9.5: once a character is set as a quote mark, this setting doesn't override it. No setting fixes this one. The skill is recognizing it, confirming each one really is an apostrophe, and documenting it instead of hunting for a fix. A different apostrophe character would remove the collision, but that's an orthography decision for the translation team, not for you." |

### Segment 3 — The Quotation types tab (companion to Lesson 3)

> ~2.5 minutes. Concept + the three custom changes. Don't recite all seven types on camera.

| On-screen | Voiceover / talking points |
| --- | --- |
| Click the **Quotation types** tab. Show the seven type rows and the Recommended/Custom controls at the top. (`assets/ss-03-quotation-types-tab.png`.) | "Input two is on the second tab. This one isn't about characters at all, it's about meaning. For each of seven kinds of speech, you tell Paratext to expect marks, never expect marks, or not care." |
| Point to the **Use recommended settings** control, then to the enable checkbox at the top. | "A new project starts on Use recommended settings, with the check itself switched off. For many projects the recommended settings are a fair baseline, but they're still Paratext's choice, so check them. Note the enable checkbox at the top: only a project administrator can tick it. You can configure everything else. If you're not an admin, set it up and then ask an admin to tick that box." |
| Switch to **Custom settings** and change three rows — **Quotation from another source** to *optional*, **Continued quotation** to **Use quote marks**, and **Indirect** to **Never use quote marks**. Point out that **Self quote** is left alone because it already defaults to Use quote marks, which is what Tamba needs. | "You only switch to Custom settings when the language does something different, and you check every row, because the defaults don't always match what you'd guess. In our Tamba project, three types need to change. Self quote turns out to be right already, so we leave it. That's the pattern: start from the recommended settings, and use custom settings for the differences the team has confirmed. Check against the real project, don't assume." |

### Segment 4 — Reading and clearing results (companion to Lesson 4)

> ~2.5 minutes. Teach the mental model: real error vs. configuration problem. This is the payoff.

| On-screen | Voiceover / talking points |
| --- | --- |
| Switch to `TAMBAB`, the Phase B copy of Tamba. Run the check across the whole New Testament. Show the short results panel with one result highlighted and the verse open alongside it. (`assets/ss-04-results-with-highlight.png`.) | "For the last lesson you switch to a copy of Tamba that's already configured, with a few deliberate errors added to the text. Run the check and look at the difference. It's a short list now, and every result means something. This is a list you can actually work through." |
| Slide: **Real error → fix the text.** / **Configuration problem → fix the settings.** | "Every result is one of two things. A real error means the text really does have a missing, extra or wrong mark, and the verse gets corrected. On a live project, that correction goes through the translation team. A configuration problem means the check has exposed a gap in your settings. You fix the Quote marks tab or the types tab, and you do NOT edit correct text to make the warning go away." |
| Screen: point to a message that repeats across many verses, versus a one-off message. | "A quick tell: the same message on lots of verses usually means a configuration gap. A one-off usually means a real error in that one verse. Work from the top down, run the check fresh after each batch, and watch the count fall." |
| Show the final results panel: one result left, the Romans 1:1 apostrophe. (`assets/ss-04-final-results.png`.) | "The goal is zero results you still need to act on. Get Matthew clean first, then expand one book at a time. Zero doesn't mean the text is perfect. It means every result has been looked at and dealt with on purpose, and any that can't clear, like that apostrophe, are verified and documented. That's what a consultant is trusting when they pick up the book." |

### Segment 5 — Applying it to any language (companion to the scenario bank)

> ~1.5 minutes. Motivate the scenario bank without solving it. Show one striking edge case.

| On-screen | Voiceover / talking points |
| --- | --- |
| Cut to the Menda Quote marks tab after correction, Second level showing `›` opening and `‹` closing, with the Example preview confirming the nested order. (`assets/ss-05-menda-quote-marks.png`.) | "Once you know the workflow, it's the same four steps for any language, however unusual its marks. Here's Menda, the project that said 'No errors found' at the defaults. Its second level opens with a right-pointing single guillemet and closes with a left-pointing one, the reverse of what your eye expects. Get them backwards and the check fires on every embedded quote. The Example preview at the bottom is how you catch that before you click OK." |
| Slide listing the three scenario languages: **Velna (guillemets), Menda (reversed nesting), Waku (em-dash — stretch).** At "That last one won't fully clear by configuration alone," briefly cut to the Waku results panel showing seven remaining results on a correctly configured project (`assets/ss-05-waku-residual-results.png`), then return to the slide. | "In the scenario bank you'll do three of these on your own. Each project starts on Paratext's defaults, so run the check first and see which kind of misleading result you get. Then compare, correct, check and triage. There's guillemets, that reversed nesting, and, if you want a stretch, a language that uses the em dash to both open and close. That last one won't fully clear through configuration alone. You'll document what's left in a Project Note so the consultant knows each result was reviewed on purpose, and you'll take the punctuation question to the translation team." |

## Call to action / close

> ~45 seconds.

**On-screen:** Return to the clean, zero-results panel from Segment 4. End on a simple
closing slide: **Lessons 1-4 → Scenario Bank → submit to your mentor.**

**Voiceover:** "That's the whole process. Compare the defaults with the team's conventions,
correct the marks, set the rules, run the check, and triage every result. Clear it to zero
where the language allows, and document what's left in a Project Note where it doesn't.
You've seen it, now build it. Open Lesson 1 and work through the four hands-on lessons on
the practice projects at your own pace. Each one has discovery prompts and an answer key to
check yourself against. Then take on the scenario bank. When you've configured all three
languages, send your configurations and answers to your mentor for review. See you in
Paratext."

## Notes for the presenter

- **This is an orientation, not a tutorial.** Resist the urge to complete every exercise
  on camera. The lessons are where learners do the clicking; the video's job is to make
  the tool and the workflow familiar so they aren't lost when they start. If any segment
  runs long, cut demo depth, not the concept framing.
- **Recording order and project state.** Every practice project arrives at Paratext 9.5's
  default Quotation Rules. Record the cold open, the Segment 1 Quote marks tab pan, and
  the first two rows of Segment 2 on a freshly received, unchanged `TAMBA`, *before*
  making any of the Lesson 2 changes. The RUNDA (1110) and MENDA ("No errors found") cuts
  in Segment 1 also need those projects unchanged. The Segment 2 "No errors found" run on
  `TAMBA`, and the corrected Runda and Menda tabs, come only after following Exercise 2.1
  (and Exercise 2.2 / Scenario B) exactly. Segment 4 uses `TAMBAB`, not `TAMBA`.
- **Never imply the text is wrong in the cold open.** The five John 17 results come from
  the default settings, and Tamba's text there is correct. Don't add, delete or retype any
  mark in the text on camera to make a result go away, and don't open the John 17 verses
  or describe their content. The point is what the *tool* is reading, not the language.
- **Say "Paratext's defaults", not "the team's settings".** The defaults are what every new
  project gets. Never describe them as a choice the translation team made, or as a
  "configured" project.
- **Per-lesson clips.** If a full click-by-click walkthrough of a lesson is wanted later,
  record it as a separate short clip per lesson rather than bloating this overview past
  ~15 minutes.
- **Terms to show on screen (not just say):** define *real error* vs. *configuration
  problem* with the Segment 4 slide, and put the Unicode code points (U+201C, U+2019,
  U+00AB, U+203A, U+2039, U+2014) on screen wherever you name a character — the visual
  glyphs are easy to confuse and the code point is the ground truth.
- **Live-demo caution:** always drive character entry with the dropdown (▼), never the
  keyboard, on camera — it models the habit the course insists on and avoids accidentally
  entering a straight quote. The one thing that is typed is the **Continuer required at**
  list; type it exactly as given, with single spaces.
- **Fresh runs only.** To show a changed result, close the results panel and run the check
  again from **Run basic checks**. **Rerun** on an open panel keeps showing the old results.
- **Pronunciation / naming:** Tamba, Runda, Velna, Menda and Waku are course names for
  practice projects built from real languages' text; say them plainly. Don't name, describe or
  make claims about the real languages behind them, and don't read any of the text aloud.
- **Screen legibility:** the yellow information bar (Segment 1), the five John 17 result
  rows (cold open), the Example preview and the Continuer required at field (Segment 2),
  and the reversed guillemets (Segment 5) are small UI details — zoom or crop the
  recording so they read clearly at video resolution.
- **Do not present the recommended-settings values as authoritative.** Say "your Paratext
  build shows the recommended defaults" rather than reading a fixed table — the exact
  defaults should be confirmed against the learner's actual 9.5 build.
- **Gaps flagged (not filled here):** this script assumes the screenshots
  `ss-*.png` in the module folder reflect current Paratext 9.5 UI. If the recording
  reveals the UI has drifted, flag it to the lesson author rather than re-editing lesson
  content from the recording.
