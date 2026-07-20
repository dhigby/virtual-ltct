# Module 3: Parsing, Concordance & Text Charting

**Estimated time:** 60 minutes

**Purpose:** Once you've interlinearized a few sentences by hand, FLEx can take on some of that work for you — and once a text is glossed or interlinearized, you need fast ways to search across it and see its bigger discourse structure. This module prepares you to use FLEx's parser, its search tools, and text charting so you can work efficiently across a whole text instead of one word at a time.

## Learning objectives

By the end of this module you will be able to:

- Use FLEx's parser to generate morphological analyses for new text, and test it against individual words.
- Explain what the Word Analyses view is for, and how it differs from correcting one word at a time.
- Choose between Concordance and Complex Concordance to find specific words, glosses, or patterns across a text or project.
- Generate a text chart, following the correct workflow order, to reveal discourse-level patterns in a text.

## Connect

**✏️ Reflection.** Think of a time you needed to review a whole body of work for a pattern or an inconsistency — not one item at a time. Searching a long document, sorting a spreadsheet, proofreading a full translation draft for one recurring mistake.

- What tool did you reach for?
- What would it have looked like to do that same review one word, one line, one entry at a time instead?

That's the shift this module makes: from working sentence-by-sentence, which is what Module 2 taught, to working across a whole text at once.

## Content

**The parser.** Once your lexicon has a few entries in it, FLEx's parser can suggest morpheme breakdowns for new words based on those existing entries, rather than you starting from scratch every time.

- **Try a Word** (Parser menu) tests the parser against a single word in isolation, without committing anything to a text — useful for a quick check before deciding whether a word belongs in your lexicon at all.
- **Parse Words in Text** (Parser menu, from the Interlinear Texts view) runs the parser across an entire text at once, suggesting analyses for every word it can.

**NOTE** Parser suggestions are a starting point, not a final answer. You still review and approve or correct each one — the parser saves you typing, not judgment.

**Word Analyses.** This view lists every word in a text along with its occurrence count and candidate analyses, all in one place — so instead of hunting through a text sentence by sentence to check whether you've glossed a word consistently, you can see every occurrence at once. Exploring this view hands-on is part of this module's Challenge.

**Searching and reviewing across a text.** You already used **Word List Concordance** in Module 2 to see every wordform and gloss in a text. Two related views give you more targeted ways to search:

- **Concordance** searches one specific line — Baseline, Word, Morphemes, Lex. Entry, Lex. Gloss, Lex. Gram. Info., Word Gloss, Word Cat., Free Translation, Literal Translation, Note, or Tagging — for a string you enter, with match options (case, diacritics, anywhere/whole item/at start/at end, or regular expressions). Which text(s) get searched is controlled by **Choose Texts** — only the currently open text by default, but you can check others to search across multiple texts or the whole project at once.
- **Complex Concordance** builds a compound query rather than a single-string search — combine multiple criteria (for example, a word form *and* a grammatical category) using its Morph/Word/Tag/OR building blocks, for more precise searches than Concordance alone can do.

**Key takeaways**

- The parser suggests analyses based on your existing lexicon; you still approve or correct every suggestion.
- Word Analyses lets you review a word's consistency across every occurrence in a text at once, rather than sentence by sentence.
- Word List Concordance = unfiltered list. Concordance = search one specific line for a string. Complex Concordance = build a compound query across multiple criteria.

**Text charting.** The Text Chart lays a text out with one clause per row and its phrases spread across columns, so discourse-level patterns — a particle that always shows up in the same clause position, for instance — become visible at a glance. It's based on the Stephen H. Levinsohn discourse analysis model, and it's deliberately designed to be usable without deep morphemic knowledge: **glossing a text is enough to start charting it** — you don't need full morpheme-level analysis first.

The basic workflow:

1. Make sure the text is glossed (every word needs at least a Word Gloss).
2. Open the Text Chart tab and confirm the column template shown is the one you want (for example, the Default template's Pre-nuclear/Nucleus/Post-nuclear/Notes columns).
3. Select a word or phrase in the word row, then click that column's insert button to place it into the chart.

**DANGER** Editing the Baseline tab *after* words are already charted removes those words from the chart, and you'd have to reinsert them. Get the baseline spelling right before you start charting, not after.

**WARNING** A chart's template (its column names and order) shouldn't be changed once it's been used in a chart — verify you have the right template before you begin, not partway through.

**NOTE** Text charting has many advanced features beyond this basic workflow — marking dependent/speech/song clauses, preposed or postposed elements, grammatical information, and more. Those go beyond what this module covers; this is enough to get a first chart started and see what it reveals.

## Challenge

### Exercise 3.1 — Work across a whole text, not one sentence at a time

**The situation.** A team member has glossed (or partly interlinearized) a few sentences using the skills from Module 2, and has asked for your help working with FLEx's tools across the whole text rather than one sentence at a time.

**Try the parser:**

1. Add one more short sentence to your text — or start a new one — using at least one word that's already in your lexicon.
2. Use **Parser → Parse Words in Text** to let FLEx suggest an analysis.
3. On the Analyze tab, approve what's correct and fix what isn't.

**Test the parser on a single word:**

1. Pick a word that isn't in your project yet.
2. Use **Parser → Try a Word** to see what FLEx suggests — or confirm it has nothing to suggest, since the word isn't in your lexicon yet.

**Explore Word Analyses:**

1. Open **View → Texts & Words → Word Analyses** for your text.
2. Select a word that occurs more than once and see what candidate analyses are shown.
3. Note whether every occurrence has the same analysis, or whether you spot an inconsistency worth fixing.

**Search your text:**

1. Open **View → Texts & Words → Concordance**.
2. Search for a word or gloss you've used, then check **Choose Texts** to confirm whether you're searching just this text or more than one.
3. Optionally, open **Complex Concordance** and build a query combining two criteria — for example, a word form and its grammatical category.

**Chart part of your text:**

1. Confirm the sentence(s) you want to chart are fully glossed.
2. Open the **Text Chart** tab and confirm the column template shown is the one you want — don't change it later once you start.
3. Select a word or phrase in the word row, then click the matching column's insert button (Subject, Verb, etc.) to place it. Repeat for each word or phrase in one sentence.

**✏️ Produce this (a mentor will review it).** Write 2–3 sentences describing one thing you noticed while charting your sentence — a word-order pattern, or something that didn't fit neatly into the template's columns — *or* one thing your concordance search revealed about how consistently you've glossed a particular word.

## Change

**Self-assessment — can you explain it to a colleague?**

1. What's the difference between using Parse Words in Text and Try a Word?
2. Why would you use Word Analyses instead of scrolling through a text word by word to check a gloss's consistency?
3. When would Complex Concordance be worth using instead of plain Concordance?
4. What's the one Baseline-editing habit that can cost you your chart work?

*You should be able to say:* (1) Parse Words in Text runs the parser across a whole text at once; Try a Word tests it against a single word in isolation, useful before deciding whether to add that word to a real text. (2) Word Analyses shows every occurrence of every word across a whole text at once, so you can catch and fix an inconsistent gloss everywhere it occurs instead of one sentence at a time. (3) When a single-string search isn't precise enough — you need to match on more than one property at once, like a specific word form *and* category. (4) Editing the Baseline tab after words are already charted removes those words from the chart — get the baseline spelling right before you start charting, not after.

**✏️ Take it to your context.** Name one real text you support, or plan to. Which of today's tools — the parser, a concordance search, or text charting — do you expect to reach for first, and why?

**Course wrap-up.** Across these three modules you've set up a FLEx project with the right writing systems, interlinearized a text by hand, and learned to work efficiently across a whole text with the parser, search tools, and text charting. Bring your test project, your interlinearized passage, and your chart to your mentor for review before moving on to this course's assessment quiz.
