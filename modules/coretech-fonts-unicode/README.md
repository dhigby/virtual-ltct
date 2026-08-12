---
title: "CoreTech: Fonts & Unicode"
slug: coretech-fonts-unicode
notion_id: 312598a5-fd40-80dc-a003-e18cf6d3022d
notion_data_source: 7ebb2ef5-9f57-4723-8de0-9cc1ca47ff8e
target_outcome_level: "2 - With Assistance"
competencies:
  - Fonts & Encoding
content_type: content
external_links:
  video: https://vimeo.com/1178428863
last_exported: 2026-06-18
---

# CoreTech: Fonts & Unicode

**Course 1: The Basics of Unicode** — a short course backfilled from courses.sil.org (source
saved 2026-07-28). It teaches trainee Language Technology Consultants to recognise, understand,
and systematically diagnose broken text (boxes, wrong marks, mojibake) caused by mismatched
encoding, missing font glyphs, or rendering problems.

> **Note:** the source content states `Level: Has Knowledge` (i.e. `1 - Has Knowledge`), but
> this README's `target_outcome_level` frontmatter says `2 - With Assistance`. Left as-is per
> the training-content skill (frontmatter changes are a human decision) — resolve before this
> course leaves backfill.

**Watch the video:** [The Case of the Broken Text](https://vimeo.com/1178428863)

<details>
<summary>Video transcript</summary>

Welcome to The Explainer. Today, we're going to crack a case that I know you've seen. It's a classic digital mystery. Why does text on our screens sometimes just break? Turn into complete and utter nonsense. Yep. If you've ever seen that on your screen, that digital garbage with scrambled letters, weird symbols, random boxes, well, you've stumbled onto a crime scene, a text crime scene, to be exact.

So in the next few minutes, we're going to turn you into a full-on text detective. The goal? To give you the skills to look at any broken text and know exactly what went wrong every single time. All right. So when text breaks, it's not random chaos. Not at all. There are always three main suspects at the scene, three culprits. And if we can get to know them, we can solve the case.

Every single character you see on your screen is a team effort, a perfect partnership between three things: encoding, font, and rendering. If just one of them messes up, the whole thing falls apart. To make sense of this, let's use a really simple analogy.

Okay, check this out. It's like a restaurant. First, you've got encoding. That's the menu. It just tells the computer what dishes or characters are even available. Then you have the font. That's the kitchen. It has all the ingredients, the actual shapes to draw those characters. And finally, there's rendering. That's your chef, the one who actually puts it all together and serves it up on the screen. So you can see, right? If the menu's wrong or the kitchen is missing ingredients or the chef messes up, yeah, your dish is going to come out looking pretty broken. Right.

So every good detective knows you have to look for patterns. And lucky for us, broken text leaves behind some super obvious evidence. There are three key clues you need to learn how to spot.

Clue number one, the dreaded box. You've all seen this, right? A little box, maybe a question mark, sitting where a letter should be. This is a classic font problem. It means the computer knows exactly what to show you. The menu is correct, but the font, our kitchen, just doesn't have the ingredient, the right shape, to actually draw it.

And then there's this mess. This scrambled nonsense actually has a name. It's called mojibake. This happens when there's an encoding mismatch. Basically, the text was saved in one language, like the modern standard UTF-8, but your computer is trying to read it using a completely different, maybe older language. It's exactly like trying to read a French menu with a Spanish dictionary. The words are there, but you're just using the wrong guide to understand them.

Okay, clue number three is a bit more subtle. Have you ever seen an accent mark just floating over a space instead of on top of its letter? This points to a combining character issue. You see, sometimes characters like é are stored as two separate pieces, the e and the accent mark, acute. It's the job of our chef, the rendering engine, to put them together perfectly. When it messes up, you get this awkward separation.

All right. Now that you can spot the clues, it's time to build your detective's toolkit, because a great detective never just guesses, right? They follow a process, a system.

So what's the plan? It's a simple three-step process. First, you identify the pattern. Is it boxes? Is it mojibake? What's the evidence telling you? Second, you form a hypothesis. Based on that pattern, which of our suspects is the culprit? Is it the font or the encoding? And third, you use your tools. You take that first smart step to actually fix it.

Following these steps means no more random clicking around hoping for the best. It gets you to the solution way faster.

So let's try it out. Imagine you're seeing those empty boxes, especially when you're working with, say, special linguistic symbols. Step one, identify the pattern. It's boxes. Step two, form a hypothesis. Boxes mean a font problem. So step three, use your tools. The targeted action is simple. Change the font. If you switch to a font with way better character support, something like Charis SIL, boom, case closed. The problem is often solved instantly. So let's bring this all home.

This is all about empowering you, because from now on, you are no longer a victim of broken text. No, you are a diagnostician. And this right here is the mindset you need. First rule, don't panic. Just look for the pattern. And here's the absolute golden rule. Always fix the data first, which is the encoding, before you fix the display, which is the font. Think about it. If you've got that scrambled mojibake mess, changing your font is going to do absolutely nothing. You have to fix the underlying data first.

So what text mystery are you going to solve first? Because the next time you see scrambled letters or weird boxes on your screen, you're not going to see an error. You're going to see a puzzle, a case that's just waiting to be solved. And now you've got the detective's toolkit to crack it.

</details>

## Sub-modules

- [Module 1a: Why Does Text Break?](01a-module-1a.md)
- [Module 1b: The Three Culprits](01b-module-1b.md)
- [Module 1c: Your Diagnostic Toolkit](01c-module-1c.md)
- Quiz 1a — not yet supplied

**Deployment note:** the courses.sil.org source places four decorative section-divider icons
(`connect.png`, `content.png`, `challenge.png`, `change.png`) at the top of each Connect/
Content/Challenge/Change section. These are dropped from this repo's markdown (replaced with
plain `##` section headings) since they're presentation, not content — the same icon set
[coretech-why-keyboards-matter](../coretech-why-keyboards-matter/README.md) documents from its
own Cypher export. Whoever re-publishes this course should apply that same standard icon set,
rather than treating it as missing content to recreate.

The course structure also lists a **"Revision with chipp.ai – Unicode"** item after Module 1c —
an interactive AI tutor tool, not static lesson content. Per the same precedent in
coretech-why-keyboards-matter, this is out of scope for backfill and not reproduced here.
