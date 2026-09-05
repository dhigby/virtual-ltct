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
- A team **denied errors they didn't understand**, rather than resolving them, simply
  clearing the check's flags instead of dealing with what was underneath.

In both cases, the check tool *looked* clean. Nothing in the display told you the team
had actually engaged with it. Your job is to **re-run the check to surface what was
hidden** — the denied errors, the wholesale approvals — and walk the team through *why*
each flagged item was raised, especially incorrectly split or joined words, which are
common in a mass-approved list. Once the cause is explained, **reset the relevant
entries to unknown** and have the team re-review them properly. You explain and coach;
the translator does the actual review and correction.

> **WARNING — watch for a false-clean result here too:** Before you accept that "the
> wordlist is done," check whether entries were reviewed one at a time or approved in
> bulk, and whether any errors were denied rather than resolved. A clean-looking status
> bar tells you nothing about which of those happened.

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

> **NOTE:** A related pattern was reported once in the SME interview behind this
> course — a rendering that looked like it might have been auto-filled with a verse's
> first word when nothing was selected in the text. It's only been seen the one time, in
> one project, has never been reproduced since, and hasn't been confirmed as an actual
> Paratext behavior (a query is open with support.bible). Worth being aware of if you
> ever see something that looks like it, but it's not yet an established field pattern —
> don't teach it as "the" cause of an implausible rendering. The confirmed, reproducible
> patterns above (blank rows, stale duplicates) are what you should actually expect to
> encounter and check for.

**Biblical Terms: Project list bloat and performance.** A separate problem with the
same tool: a consultant **added the entire "All Biblical Terms" list into the
Project's Biblical Terms list** — not merely selecting it for viewing, but adding every
term into the project itself. The slowdown that followed (in Send/Receive and general
performance) came specifically from that addition to the *project* list, not from
viewing a large list. The SME interview noted this cause was **hard to convince the
team of** — the connection between "we added a big list of terms" and "Send/Receive is
now slow" is not obvious to a non-technical team.

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
- A clean wordlist status can hide mass-approval or denied errors — re-run the check to
  find out, and reset entries the team hasn't genuinely reviewed.
- Blank renderings are Biblical Terms' normal starting state, not a bug — check the
  Found column/count to confirm a list is genuinely complete, not just look clean.
- Stale or duplicate renderings can pile up on a term over time; coach the team to
  review and remove the ones no longer found in the text.
- Project Biblical Terms bloat causes real Send/Receive slowdowns, and the cause is
  often hard for a team to accept without a clear explanation.

## Challenge

**✏️ Try this:** A team tells you their wordlist check is finished — the status shows
no outstanding errors. When you look closer, you notice thousands of entries were
marked correct with none flagged as suspect, and several flagged "possible misspelling"
entries are marked denied.

1. What does this pattern suggest happened, and how would you confirm it?
2. What would you say to the team to explain why this needs to be redone, in terms that
   don't sound like an accusation?
3. Separately: a team reports Send/Receive has become noticeably slower since last
   month, and mentions in passing that they added the full Biblical Terms reference
   list to make consulting terms easier. What's your diagnosis, and how would you
   explain the cause to a team that's skeptical the two things are connected?

## Change

**✏️ Reflection:** Which of these two check areas — the wordlist or Biblical Terms —
is more likely to come up with a team you currently support, and why?

**Next step:** Next time you review a wordlist or Biblical Terms status with a team,
ask specifically how the approvals happened, not just what the current status shows.

**Coming up:** Lesson 3 moves to parallel passages and numbers/weights/measures — two
check areas where your job is to confirm the check ran against the team's own agreed
approach, and route what it surfaces back to them, rather than judging the language
yourself.
