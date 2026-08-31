# Lesson 2: Wordlist and Biblical Terms

**Estimated time:** 40 minutes

**Purpose:** Support a team through two related but distinct check areas — the
high-volume wordlist/spell-checking pass and the Biblical Terms tool — recognizing the
specific ways each one produces false confidence, and coaching the team to a genuine
fix without touching their keyboard.

## Learning objectives

- You will be able to recognize a wordlist that has been blanket-approved rather than
  genuinely reviewed, and coach a team to reset it and re-run the check honestly.
- You will be able to diagnose the no-selection rendering error in Biblical Terms and
  coach the team to correct it.
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

**Biblical Terms: the no-selection rendering error.** A narrower, more mechanical
problem, but a real one: a team can add a term's rendering in the Biblical Terms tool
without first **selecting** the correct word or phrase in the text. When nothing is
selected, Paratext defaults to grabbing the **first word of the verse** as the
rendering — which is very often wrong, and easy for a team to miss because the tool
still records *something* as the rendering.

To diagnose it: look for renderings that don't plausibly match the term's meaning, and
check whether they happen to be a verse's opening word. To coach the fix: have the
team **delete the bad rendering**, then **select the correct text in the verse before**
adding it — the team does this themselves, at their own keyboard.

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
rather than the full reference list. Note also that Biblical Terms list names vary by
Paratext version and project configuration: current versions typically show "Measures
and Money" and "Numbers," while older projects may still show separate "Weights,"
"Measures," "Money," or "Currency" lists — don't assume every project's terms lists
look identical.

**Key takeaways**
- A clean wordlist status can hide mass-approval or denied errors — re-run the check to
  find out, and reset entries the team hasn't genuinely reviewed.
- The no-selection rendering error comes from adding a rendering without selecting the
  correct text first; the fix is delete, select correctly, re-add.
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
