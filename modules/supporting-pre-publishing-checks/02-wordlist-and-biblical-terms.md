# Lesson 2: Wordlist and Biblical Terms

**Estimated time:** 40 minutes

**Purpose:** Support a team through two related but distinct check areas — the
high-volume wordlist/spell-checking pass and the Biblical Terms tool — recognizing the
specific ways each one produces false confidence, and coaching the team to a genuine
fix without touching their keyboard.

## Learning objectives

- You will be able to recognize a wordlist that has been blanket-approved rather than
  genuinely reviewed, and coach a team to reset it and re-run the check honestly.
- You will be able to recognize incomplete or stale Biblical Terms coverage — blank
  renderings presented as done, and stale/duplicate renderings left uncleaned — and coach
  the team to genuinely complete and clean up the list.
- You will be able to diagnose a Project Biblical Terms list that has been over-added-to
  and is causing Send/Receive or performance slowdowns, and advise the team on
  right-sizing it.

## Connect

**✏️ Reflection:** A wordlist check in Paratext can cover tens of thousands of words in
a single project. If you were the translator facing that list after weeks of drafting,
what would tempt you to just mark everything "correct" and move on? Hold onto that
honestly — it's not laziness, it's fatigue, and it's exactly the condition this lesson
prepares you to spot and address with the team.

## Content

**Wordlist / spelling: a false-clean result, confirmed in the field.** This is the
check area where "false-clean" isn't theoretical — it's a documented pattern from the
SME interview behind this course:

- A wordlist of **thousands of words was blanket-approved** — every entry marked
  correct without genuine review.
- Entries can just as easily sit **marked "Undecided" and never followed up on**, which
  leaves the same false impression of a resolved list.

In both cases, the check tool *looked* clean. Nothing in the display told you the team
had actually engaged with it. Your job is to **re-run the check to surface what was
hidden** — the wholesale approvals, the forgotten Undecided entries — and walk the team
through *why* each flagged item was raised, especially incorrectly split or joined
words, which are common in a mass-approved list. Once the cause is explained, **reset
the relevant entries to unknown** and have the team re-review them properly. You explain
and coach; the translator does the actual review and correction.

> **WARNING — watch for a false-clean result here too:** Before you accept that "the
> wordlist is done," check whether entries were reviewed one at a time or approved in
> bulk, and whether any entries are actually sitting at Undecided rather than genuinely
> resolved. A clean-looking status bar tells you nothing about which of those happened.
> (Note: a word's spelling status is only ever Correct, Incorrect, or Undecided — there's
> no "deny" action here. That's a Basic Checks concept, covered in Lesson 4.)

**Biblical Terms: blank renderings are the default starting state.** Every term in
Paratext's Biblical Terms tool starts out with a gloss (in English, or the source
language for a localized instance) and a **blank rendering** — shown highlighted (e.g.
orange in the UI) with placeholder text like "Double click to enter rendering(s) from
project text." This isn't a bug or a trap the tool is setting for the team; it's simply
the tool's normal starting state, and the task is straightforward: work through the
list and add the appropriate rendering for each term from the project's own text.

Where this becomes a false-clean risk is the same pattern as the wordlist above: a team
can present a partially-completed Biblical Terms list as "done" without having actually
gone through and filled in every term. The check that catches this isn't eyeballing the
list — it's the **Found** column/count on each row (e.g. "2/2" found vs. a row that's
still blank or unfilled). To diagnose it: open the list and check the Found count row by
row rather than trusting a general "we did the Biblical Terms" claim. To coach the fix:
have the team work through the remaining blank rows themselves, entering renderings from
their own project text.

**Biblical Terms: stale or duplicate renderings piling up.** A separate, related
problem: a term can accumulate a long list of multiple candidate renderings, most of
which are no longer actually found in the current text — for example, an old (and
possibly wrong) rendering left in place after a correct one was added later, without
ever deleting the original. This clutters the list and can make it hard to tell which
rendering is the team's current, intended one.

To diagnose it: look for terms carrying several renderings and check which ones the
Found count shows as still present in the text versus which are stale leftovers. To
coach the fix: have the team review the full rendering list for a term, confirm which
rendering(s) are current and correct, and remove the stale ones themselves — you're
coaching this, not doing it at their keyboard.

> **NOTE:** A related, less common pattern: a rendering that's just the verse's first
> word, repeated across a term's occurrences, rather than an actual translation. Per a
> support.bible programmer's reply to the SME interview behind this course, the likely
> cause is running Paratext's **Guess Renderings** feature before the project had enough
> translated data for it to work correctly — with too little data to draw on, it can
> default to guessing the verse's first word as the rendering. This is a background,
> automated side effect of running that feature, not something a translator did
> deliberately or would necessarily notice happened. It's a secondary pattern to the two
> confirmed ones above (blank rows, stale duplicates) — don't lead your check with it,
> but if you see a rendering that looks like a repeated first word, this is the likely
> explanation, and it's worth checking for.

> **TIP:** To spot this quickly, open a term's occurrences and step through them with
> the **down arrow** rather than reading each one closely. A rendering that's just the
> verse's first word stands out visually against real translations once you're moving
> through the list at that pace — you don't need to read every entry carefully to catch
> it.

**Biblical Terms: Project list bloat and performance.** A separate problem with the
same tool: a consultant **added the entire "All Biblical Terms" list into the
Project's Biblical Terms list** — not merely selecting it for viewing, but adding every
term into the project itself. To see why that's a much bigger addition than it sounds,
it helps to know what's actually in each list, per Paratext's own help documentation:
"All Biblical Terms" lists every Greek, Hebrew, and Aramaic term occurring 500 times or
less in the original text — potentially tens of thousands of entries depending on the
project's scope (the SME has observed a whole-Bible project where this list ran to
around 20,000 entries). "Major Biblical Terms," by contrast, is a curated subset of
just over 8,500 terms, organized into semantic domains. Adding the *entire* "All
Biblical Terms" list into a project, rather than working from the curated "Major"
list, means loading tens of thousands of entries instead of a few thousand — and that
scale difference is exactly what turns into a Send/Receive and performance problem. The
slowdown that followed came specifically from that addition to the *project* list, not
from viewing a large list. The SME interview noted this cause was **hard to convince the
team of** — the connection between "we added a big list of terms" and "Send/Receive is
now slow" is not obvious to a non-technical team, especially when the sheer scale (tens
of thousands of entries) isn't visible or intuitive from inside the tool.

Diagnosing this means checking whether the Project Biblical Terms list has been
expanded well beyond the terms the project actually uses, and advising the team to
**right-size it** — keep the project list to the terms relevant to their translation,
rather than the full reference list. Note also that the Open Biblical Terms List dialog holds
several separate lists side by side (Major Biblical Terms, All Biblical Terms, NT Key
Biblical Terms, Inclusive/Exclusive Pronouns, Younger/Older Siblings, and others),
and — as covered in Lesson 3 — **Numbers** and **Measures** are two more separate
entries there, not a single combined "Measures and Money and Numbers" list: Numbers is
a released check with limited scope, while Measures is still under development and not
yet reliably available. Don't assume every project's terms lists look identical, or
that a list you've seen behave one way in one project will exist or behave the same
way in another.

**Key takeaways**
- A clean wordlist status can hide mass-approval or forgotten Undecided entries —
  re-run the check to find out, and reset entries the team hasn't genuinely reviewed.
- Blank renderings are Biblical Terms' normal starting state, not a bug — check the
  Found column/count to confirm a list is genuinely complete, not just look clean.
- Stale or duplicate renderings can pile up on a term over time; coach the team to
  review and remove the ones no longer found in the text.
- Project Biblical Terms bloat causes real Send/Receive slowdowns, and the cause is
  often hard for a team to accept without a clear explanation.

## Challenge

**✏️ Try this:** Four short exercises on the two tools in this lesson. Write each
answer first, then verify it against the Content section above.

1. **Name the only three statuses** a word's spelling can carry in Paratext's
   wordlist. Then say in one sentence why "denied" is not one of them, and which
   check area that word actually belongs to. If you wrote down four statuses, re-read
   the warning box in the wordlist section.
2. **Two rows, two different problems.** Row A shows a highlighted rendering carrying
   the placeholder text "Double click to enter rendering(s) from project text." Row B
   shows five renderings on one term, most of them no longer found in the current
   text. For each row write one line: what the row is telling you, and what you look
   at to confirm it. Then say which of the two is the tool's normal starting state
   rather than something that has gone wrong.
3. **Open the Biblical Terms list** in a project you already support and pick any one
   row. Read its **Found** column/count and its rendering(s), and write down which of
   three states that row is in: blank default, one current rendering, or several
   renderings needing review. One row only — this is a find-the-column exercise, not a
   list review.
4. **Selecting versus adding.** In one sentence each, say what happens when a team
   *selects* "All Biblical Terms" in order to view it, and what happens when they
   *add* its contents into the Project Biblical Terms list — then name which of the
   two can slow Send/Receive, and why. Include the rough scale this lesson gives for
   "All Biblical Terms" against "Major Biblical Terms." That size difference is the
   part a team finds hardest to believe, so you want the numbers at your fingertips
   before you're in front of one.

## Change

**✏️ Reflection:** Which of these two check areas — the wordlist or Biblical Terms —
is more likely to come up with a team you currently support, and why?

**Next step:** Next time you review a wordlist or Biblical Terms status with a team,
ask specifically how the approvals happened, not just what the current status shows.

**Coming up:** Lesson 3 moves to parallel passages and numbers/weights/measures — two
check areas where your job is to confirm the check ran against the team's own agreed
approach, and route what it surfaces back to them, rather than judging the language
yourself.
