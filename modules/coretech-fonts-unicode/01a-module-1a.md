# Module 1a: Why Does Text Break?

**Estimated time:** 60 minutes

**Purpose:** To recognise the three common patterns of broken text — boxes, wrong marks, and mojibake — as the foundation for diagnosing display problems.

## Learning objectives

By the end of this module, you will be able to:

- Recognise the three main patterns of broken text — boxes, wrong marks, and mojibake
- Understand how fonts, encodings, and rendering engines work together
- Begin diagnosing text display problems systematically

## Connect

Maria is a language consultant working with a community in Papua New Guinea. The community has just finished their first phonetic lexicon — 500 entries, all carefully transcribed in IPA. It's a big deal. And then she opens the file on her computer and sees this:

```
[ᵐb̪ɔ̃ː] → [▯b̪▯▯]
[ʈʂʰɤ] → [▯▯▯▯]
[ɲɟa] → [▯▯a]
```

Half the symbols have turned into boxes. She can't send this to the print shop. She can't share it with the community. The work isn't wrong — but it's invisible.

**INFO** This happens all the time in language technology work. By the end of this course, you'll know exactly what causes it — and how to fix it.

**✏️ Before you go on:** Have you ever opened a document and seen boxes where letters should be? Or received a file with accent marks in the wrong place? Or seen text that looked like scrambled nonsense — "Ã©tÃ©" instead of "été"? Jot down what you've seen, or what you'd worry about encountering. If you're learning with others, take 2 minutes to share examples.

## Content

*Whether you're coming to this course after the Hardware or OS modules, or starting here, you'll recognise a familiar pattern: recognise the pattern first, understand why it's happening, then investigate systematically. If you've already worked through the Hardware or OS courses, this will feel familiar — the same mindset, applied to a different problem. If this is your first course, the approach you're learning here will transfer directly when you encounter hardware or OS issues later.*

### The three layers of text display

When text appears on screen, three things have to work together:

- The **encoding** tells the computer which character is which
- The **font** provides the shapes (called glyphs) to draw those characters
- The **rendering engine** puts it all together and displays it

When text breaks, one of these three layers has failed. Your job as a language technologist is to figure out which one.

**NOTE** Think of it like a restaurant. The encoding is the menu (what's available). The font is the kitchen (the ingredients). The rendering engine is the chef (who puts it together). If the menu lists something the kitchen can't make — or the chef doesn't know the recipe — the dish doesn't arrive correctly.

### The three patterns

#### Pattern 1: The box (□ or ▯)

**What you see:** boxes, rectangles, or question marks where characters should be.

**What it usually means:** the font doesn't have the shape for that character.

In Maria's case, her IPA symbols are turning into boxes because her default font — probably Arial or Times New Roman — doesn't include characters like [ᵐ], [ʈ], or [ɲ]. The computer knows which character it's supposed to show (the encoding is working fine), but the font simply can't draw it.

**TIP** The data isn't lost. The font just doesn't have the right shapes. This is usually the easiest problem to fix.

#### Pattern 2: The wrong mark in the wrong place

**What you see:** accent marks floating above the wrong letter, diacritics on the wrong character, or marks that look stacked incorrectly.

**Example:** you intended bɔ̃ː but what displays is bɔ ̃ː — with the tilde floating separately from the vowel.

**What it usually means:** this is often an encoding issue, specifically a problem with how combining characters are stored or how normalisation is handled. The font might be perfectly fine. The way the characters are encoded in the file is the problem.

#### Pattern 3: Mojibake (scrambled nonsense)

**What you see:** text that looks like random gibberish, often with strange accented characters where normal letters should be.

**Example:** "été" displays as "Ã©tÃ©".

**What it usually means:** the file was saved in one encoding (say, UTF-8) but opened in another (say, Windows-1252). The computer is reading the data with the wrong decoder. Like trying to read a French book using a Spanish dictionary — the data is fine, it's just being misread.

**TIP** Mojibake isn't data corruption. It's a mismatch between how the file was saved and how it's being read. Your data is safe — it just needs to be opened with the right decoder.

### Activity: Pattern recognition practice (10 minutes)

Look at these six broken text samples. For each one, identify the pattern: Box, Wrong mark, or Mojibake.

1. The café has good cof▯ee
2. The café has good coffee (but the é has a floating accent)
3. The cafÃ© has good coffee
4. [ŋ] → [▯]
5. naÃ¯ve
6. n̪aː (where the diacritic under the n is floating)

Try to identify each pattern before checking the answers.

**Answers:**

1. **Box** — font doesn't have that glyph
2. **Wrong mark** — combining character issue
3. **Mojibake** — UTF-8 read as Windows-1252
4. **Box** — IPA character missing from font
5. **Mojibake** — UTF-8 read as Windows-1252 ("naïve")
6. **Wrong mark** — combining diacritic not attached correctly

If you got 4 out of 6, you're building good pattern recognition. If you got fewer, that's fine — this skill develops with practice.

## Challenge

Let's return to Maria's lexicon. Here are three entries from her file:

```
Entry 1: [ᵐb̪ɔ̃ː] → displays as [▯b̪▯▯]
Entry 2: [ʈʂʰɤ] → displays as [▯▯▯▯]
Entry 3: [ɲɟa] → displays as [▯▯a]
```

**✏️ Work through these three questions.** Take 5 minutes — write down your reasoning, or discuss with others if you're not working alone.

- Question 1: Which pattern is this — Box, Wrong mark, or Mojibake?
- Question 2: Which layer is most likely the problem — encoding, font, or rendering?
- Question 3: What would you tell Maria to try first?

**Answers:**

1. **Box** — characters are showing as rectangles, not scrambled or floating.
2. **Font** — the computer knows which characters to show (encoding is fine), but the font can't draw them.
3. Tell Maria to change to a font with IPA coverage. Charis SIL and Doulos SIL (both free from [software.sil.org](https://software.sil.org)) are designed for exactly this kind of work.

**Sample response to Maria:**

> "Good news — your data isn't corrupted. The computer knows which characters you typed. The problem is your font doesn't have those shapes. Try switching to Charis SIL or Doulos SIL, which are designed for phonetic transcription. You can download them free from [software.sil.org/charis/](https://software.sil.org/charis/)"

## Change

You've just learned to recognise three common patterns of broken text. This is your diagnostic foundation. Without it, fixing problems is just guesswork.

**Next time you encounter broken text, do this before you try anything:**

1. Don't panic. Look for the pattern.
2. Ask yourself: is this boxes (font)? Wrong marks (encoding)? Mojibake (encoding mismatch)?
3. Start your diagnosis from there.

**TIP** This single skill — recognising the pattern — will save you hours of random troubleshooting. Every fix starts here.

**✏️ Before moving to Module 1b:** Bookmark [software.sil.org/fonts/](https://software.sil.org/fonts/) — you'll use it in the next module.

Optional: if you have files with IPA or other special characters, open them and see if you can identify which pattern you're looking at.

**✏️ Reflection:** What's one situation in your current work where this pattern recognition would be helpful? You don't need to answer now — just keep it in mind as you move forward.
