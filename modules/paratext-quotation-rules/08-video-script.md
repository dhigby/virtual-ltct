# Video script

Companion recording script for the course **Configuring Quotation Rules in Paratext**.
This is an orientation/overview video: a screen-capture walkthrough that shows learners
the shape of the tool and the workflow before they sit down and do the hands-on lessons.
It is **not** a full read-aloud of the lessons — the detailed, click-by-click exercise
work happens in the numbered lesson files, where learners can pause and work at their own
pace. The recording happens outside this repo; upload to Cypher for Business is a separate
human step.

**Estimated runtime:** 12-14 minutes (overview companion to a multi-hour hands-on course).
**Companion lesson file(s):** `01-what-the-quotation-check-does.md` through
`05-scenario-bank.md` — one orientation segment per lesson.

## Overview / cold open

> Runtime target: ~60-90 seconds. Open on a live screen recording, not a title slide.

**On-screen:** Screen recording of the `tamba` project with the **Run basic checks**
dialog open, **Quotations** ticked, and **Choose Books** set to the whole New Testament.
The presenter clicks **OK** and the results panel fills with a long, scrolling list of
results across many books. (This is the state captured in `ss-L108-run-basic-checks.png`
and `ss-L113-unconfigured-results.png` — record it live so the scroll and the sheer
count land.)

**Voiceover:** "This is what the Quotation check looks like on a project nobody has
configured yet. Hundreds of results. Scroll down — they never stop. And here's the trap:
almost none of them are real. If you start opening verses and 'fixing' them one at a time,
you'll spend a week editing correct text and end up with a translation that's worse, not
better. The check isn't broken. It just doesn't know what a quotation mark looks like in
your language yet. In the next few minutes I'll show you the two settings that turn this
wall of noise into a short, trustworthy list — and then you'll go build that configuration
yourself."

## Script

### Segment 1 — Why an unconfigured check is noise (companion to Lesson 1)

> ~2 minutes. Concept framing. Keep it fast — this is the "why," the lesson carries the "how."

| On-screen | Voiceover / talking points |
| --- | --- |
| Stay in the results panel from the cold open. Hover over the yellow information bar at the bottom of the Run basic checks dialog so its text is legible on screen. | "See this yellow bar? It says the accuracy of the check depends on the information you've given it. That's the whole story. The check needs two inputs before it can say anything trustworthy." |
| Cut to a simple two-box slide: **1. Quote marks tab — which characters are quote marks, and at what level.** **2. Quotation types tab — when marks are expected.** | "Input one: which characters in your text actually are quotation marks, and at which nesting level. Input two: for each kind of speech, whether marks should be there, should never be there, or don't matter. Give it both, and the check goes from guessing to checking." |
| Back to the results panel. Point out results scattered across Matthew, Luke, John, Acts. | "One more habit before we start: don't ever try to interpret a full-New-Testament result list. Zero results on an unconfigured project doesn't mean the text is clean — it means Paratext isn't looking for anything. We fix that one book at a time, starting with Matthew, because Matthew has heavy, representative dialogue." |

### Segment 2 — The Quote marks tab (companion to Lesson 2)

> ~3 minutes. The heart of the video. Show the navigation and the grid; do NOT walk every cell for every level — show the pattern once.

| On-screen | Voiceover / talking points |
| --- | --- |
| Live navigation: click the project menu **☰ > Project settings > Quotation Rules**. (State shown in `ss-L152-project-menu-quotation-rules.png`.) Land on the **Quote marks** tab. | "Here's input one. Project menu, Project settings, Quotation Rules, and the Quote marks tab. This is where you tell Paratext the characters." |
| Slow pan across the three-by-three grid. Highlight the three rows, then the three columns. (Layout in `ss-L172-quote-marks-tab-layout.png`.) | "Three rows are your nesting levels: first-level speech, a quote inside a quote, and a quote inside that. Three columns: the Opening mark, the Quote Continuer at a new paragraph, and the Closing mark. Most languages leave that middle column blank — they just close and reopen at each paragraph." |
| Click the dropdown arrow (▼) on the First-level Opening cell so the character list appears. Select the curly double quote. Then show the **Example** preview at the bottom updating to `“…‘…’…”`. | "Enter characters with the dropdown on each cell, never by typing — that's how you're sure you picked U+201C and not a straight keyboard quote. And every time, glance down at the Example preview. If the sample sentence shows the marks you expect, you got it right. This is your single best safety check." |
| Brief cut to the Runda Quote marks tab showing guillemets and a filled Continuer cell. (`ss-L227-runda-quote-marks.png`.) | "Different language, same grid. Here's a language that uses French guillemets and does repeat its opening mark at each new paragraph — so its Continuer column is filled. The tab doesn't change; only the characters do." |
| Cut to Language Settings: **☰ > Project settings > Language Settings > Other Characters** tab, with `’` in the **Word-medial punctuation** field. (`ss-L241-language-settings-other-chars.png`.) | "One gotcha that traps almost everyone. If your closing single-quote is U+2019 and that same character is also an apostrophe inside words, the check reads every apostrophe as an unclosed quote. The fix is NOT in the Quotation Rules dialog — it's over here in Language Settings, Other Characters, Word-medial punctuation. Add the character here and the checker treats it as part of the word. Remember where this lives; you'll come back to it." |

### Segment 3 — The Quotation types tab (companion to Lesson 3)

> ~2 minutes. Concept + the one custom change. Don't recite all seven types on camera.

| On-screen | Voiceover / talking points |
| --- | --- |
| Click the **Quotation types** tab. Show the seven type rows and the Recommended/Custom controls at the top. (`ss-L289-quotation-types-tab.png`.) | "Input two lives on the second tab. This one isn't about characters at all — it's about meaning. For each of seven kinds of speech, you tell Paratext: expect marks, never expect marks, or don't care." |
| Point to the **Use recommended settings** control, then to the enable checkbox at the top. | "Start with Use recommended settings — for most projects that's most of the work done. Note the enable checkbox at the top: only a project administrator can tick it. You can configure everything else; if you're not an admin, set it up and then ask them to flip that switch." |
| Switch to **Custom settings** and change one row — **Self quote** — from *optional* to **Use quote marks**. | "You only go to Custom settings when your language diverges. In our Tamba project, self-quotes have to be marked like any other speech, so we change that one row. That's the pattern: recommended as your baseline, custom for the exceptions your team has confirmed." |

### Segment 4 — Reading and clearing results (companion to Lesson 4)

> ~2.5 minutes. Teach the mental model: real error vs. configuration problem. This is the payoff.

| On-screen | Voiceover / talking points |
| --- | --- |
| Re-run the check on **Current Book** (Matthew). Show the now-short results panel with one result highlighted and the verse open alongside it. (`ss-L429-results-with-highlight.png`.) | "Now re-run the check — Current Book, Matthew — and look at the difference. A handful of results instead of hundreds. This is a list you can actually work." |
| Slide: **Real error → fix the text.** / **Configuration problem → fix the settings.** | "Every result is one of two things. A real error means the text genuinely has a missing, extra, or wrong mark — you fix the verse. A configuration problem means the check is revealing a gap in your settings — you fix the Quote marks tab or the types tab, and you do NOT edit correct text to make the warning go away." |
| Screen: point to a message that repeats across many verses, versus a one-off message. | "Quick tell: the same message on lots of verses usually means a configuration gap. A one-off usually means a real error in that one verse. Work top to bottom, re-run after each batch, and watch the count fall." |
| Show the results panel at zero. (`ss-L477-zero-results.png`.) | "The goal for each book is zero — Matthew clean first, then expand one book at a time. Zero doesn't mean the text is perfect; it means every result has been looked at and resolved deliberately. That's what a consultant is trusting when they pick up the book." |

### Segment 5 — Applying it to any language (companion to the scenario bank)

> ~1.5 minutes. Motivate the scenario bank without solving it. Show one striking edge case.

| On-screen | Voiceover / talking points |
| --- | --- |
| Cut to the Menda Quote marks tab, Second level showing `›` opening and `‹` closing, with the Example preview confirming the nested order. (`ss-L560-menda-quote-marks.png`.) | "Once you know the workflow, it's the same four steps for any language, however strange the marks. Here's Menda, where the second level opens with a right-pointing single guillemet and closes with a left-pointing one — reversed from what your eye expects. Get them backwards and the check fires on every embedded quote. The Example preview at the bottom is exactly how you catch it before you click OK." |
| Slide listing the three scenario languages: **Runda (guillemets), Menda (reversed nesting), Waku (em-dash — stretch).** | "In the scenario bank you'll do three of these on your own: guillemets, that reversed nesting, and — if you want the stretch — a language that uses the em dash for both opening and closing. That last one won't fully clear by configuration alone; you'll document the leftovers in a Project Note so the consultant knows they were reviewed on purpose." |

## Call to action / close

> ~45 seconds.

**On-screen:** Return to the clean, zero-results panel from Segment 4. End on a simple
closing slide: **Lessons 1-4 → Scenario Bank → submit to your mentor.**

**Voiceover:** "That's the whole arc: inventory the marks, set the rules, run the check,
and triage to zero. You've seen it — now build it. Open Lesson 1 and work through the
four hands-on lessons on the Tamba project at your own pace; each one has discovery
prompts and an answer key to check yourself against. Then take on the scenario bank. When
you've configured all three languages, submit your configurations and your answers to your
mentor for review. See you in Paratext."

## Notes for the presenter

- **This is an orientation, not a tutorial.** Resist the urge to complete every exercise
  on camera. The lessons are where learners do the clicking; the video's job is to make
  the tool and the workflow familiar so they aren't lost when they start. If any segment
  runs long, cut demo depth, not the concept framing.
- **Per-lesson clips.** If a full click-by-click walkthrough of a lesson is wanted later,
  record it as a separate short clip per lesson rather than bloating this overview past
  ~14 minutes.
- **Terms to show on screen (not just say):** define *real error* vs. *configuration
  problem* with the Segment 4 slide, and put the Unicode code points (U+201C, U+2019,
  U+00AB, U+203A, U+2039, U+2014) on screen wherever you name a character — the visual
  glyphs are easy to confuse and the code point is the ground truth.
- **Live-demo caution:** always drive character entry with the dropdown (▼), never the
  keyboard, on camera — it models the habit the course insists on and avoids accidentally
  entering a straight quote.
- **Pronunciation / naming:** the project languages (Tamba, Runda, Menda, Waku) are
  fictional; say them plainly. Do not imply they are real languages.
- **Screen legibility:** the yellow information bar (Segment 1), the Example preview
  (Segment 2), and the reversed guillemets (Segment 5) are small UI details — zoom or
  crop the recording so they read clearly at video resolution.
- **Do not present the recommended-settings values as authoritative.** Say "your Paratext
  build shows the recommended defaults" rather than reading a fixed table — the exact
  defaults should be confirmed against the learner's actual 9.5 build.
- **Gaps flagged (not filled here):** this script assumes the screenshots
  `ss-*.png` in the module folder reflect current Paratext 9.5 UI. If the recording
  reveals the UI has drifted, flag it to the lesson author rather than re-editing lesson
  content from the recording.
