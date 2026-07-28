# Module 1b: The Three Culprits

**Estimated time:** 60 minutes

**Purpose:** To understand why broken text happens — encoding, fonts, combining characters, and rendering engines — so you can diagnose confidently rather than guess.

## Connect

In Module 1a, you learned to recognise three patterns of broken text — boxes, wrong marks, and mojibake. You can now see the problem. But to fix it, or help someone else fix it, you need to understand *why* it happens.

Here's the kind of question you'll encounter:

> "I changed the font and the boxes went away, but now the tone marks are in the wrong place. Did I break something?"

To answer that, you need to know what the font change actually did, why it didn't fix the tone marks, and whether these are two separate problems or one related issue.

**INFO** This module gives you the conceptual map to answer those questions — so you can diagnose confidently, not just follow steps by trial and error.

**✏️ Before we dive in, take 2 minutes to reflect:** If you've ever tried to fix broken text, what did you try first? Did it work? What do you wish you'd understood at the time? If you haven't encountered this yet — what would you want to know before you started changing things?

## Content

*Whether you're coming to this course after the Hardware or OS modules, or starting here, you'll recognise a familiar pattern: recognise the pattern first, understand why it's happening, then investigate systematically. If you've already worked through the Hardware or OS courses, this will feel familiar — the same mindset, applied to a different problem. If this is your first course, the approach you're learning here will transfer directly when you encounter hardware or OS issues later.*

**TIP** The diagnostic toolkit you're building in this module reflects the same approach you'll find throughout this program: recognise the pattern first, understand why it's happening, then investigate systematically. If you've already worked through the Hardware or OS courses, this will feel familiar. If this is your first course, the approach you're learning here will transfer directly when you encounter hardware or OS issues later.

### Culprit 1: Encoding (the map)

When you type a letter, the computer doesn't store "the letter a" — it stores a number. Encoding is the map that tells the computer which number represents which character.

You type: **café**. The computer stores: 99 97 102 233. The encoding says: 99 = c, 97 = a, 102 = f, 233 = é.

The problem is that different encodings use different maps. In Windows-1252, the number 233 means "é". In UTF-8, "é" is represented by two numbers: 195 and 169. So if a file saved in UTF-8 is opened with Windows-1252, the computer reads the wrong map — giving you "Ã©" instead of "é".

**NOTE** That's why mojibake happens. The data is fine — it's being read with the wrong decoder.

#### Legacy encodings vs. Unicode

For decades, every language and region had its own encoding — Windows-1252 for Western European languages, Windows-1251 for Cyrillic, ISO-8859-6 for Arabic, and dozens more. You couldn't mix languages in one document, and files broke when moved between systems.

Unicode solved this by creating one giant map that includes every character from every writing system. UTF-8 is the most common way to store Unicode text, and it's what most modern systems use. But a lot of language communities collected data before Unicode was standard. Those files might still be in legacy encodings — SIL's older encoding systems, custom community encodings, or older Windows code pages.

**TIP** Different regions had different Windows code pages — Windows-1251 for Cyrillic, Windows-1254 for Turkish, Windows-1256 for Arabic, Windows-1258 for Vietnamese. If you're working in a specific region and see mojibake, search for "Windows code page [your region]" to find out which encoding might have been used.

#### Activity: Spot the encoding problem (5 minutes)

Which of these are encoding problems?

- **Example A:** File displays: "The naïve café owner" / Should display: "The naïve café owner"
- **Example B:** File displays [ŋ] as a box / Should display: [ŋ] is a velar nasal
- **Example C:** File displays: "Tá»ng" (Vietnamese) / Should display: "Tổng"

**Answers:**

- **A — Encoding problem** (mojibake — UTF-8 read as Windows-1252)
- **B — Not encoding.** This is a font problem. The font doesn't have the ŋ glyph.
- **C — Encoding problem** (Vietnamese tone marks stored incorrectly or misread)

**TIP** If you see scrambled characters (mojibake), it's encoding. If you see missing characters (boxes), it's usually font. Keep that distinction clear and you'll diagnose faster.

### Culprit 2: Fonts (the kitchen)

A font is a collection of glyphs — the visual shapes that represent characters. When you install Arial, you're installing a file that contains the shape for A, the shape for B, the shape for é, and so on. But not all fonts contain all characters.

Arial has basic Latin letters and common European accents. It does not have IPA symbols like [ŋ], [ʈ], or [ɲ], most non-European scripts, or specialised linguistic characters.

**NOTE** When you see boxes, it usually means the computer knows which character to display (encoding is fine), but the font doesn't have a shape for it.

#### Why SIL fonts matter

SIL has developed fonts specifically for linguistic work, all free to download:

- **Charis SIL** — a serif font with comprehensive IPA and extended Latin coverage
- **Doulos SIL** — similar to Times New Roman in style, also with full IPA coverage
- **Andika** — a sans-serif font designed specifically for literacy materials
- **Gentium Plus** — an elegant serif with very broad character support

All of these include full IPA coverage, extended Latin characters, tone marks and diacritics, and characters for minority and endangered languages.

**TIP** When you see boxes in linguistic data, switching to a SIL font often solves it immediately. Keep Charis SIL or Doulos SIL installed as your default for linguistic work.

#### Activity: Font coverage check (8 minutes)

1. Go to [software.sil.org/charis/](https://software.sil.org/charis/) and scroll to the Character Set Support section. Notice which Unicode blocks are listed.
2. Open a text editor on your computer and type (or paste): [ŋ ʈ ɲ ɔ̃]
3. Change the font to Arial. Then Times New Roman. Then Charis SIL (if you have it installed). What do you see?

In Arial and Times New Roman, most of these will show as boxes. In Charis SIL, all characters display correctly. Same text, same encoding — different font coverage.

### Culprit 3: Combining characters and normalisation

Sometimes you see the base letter correctly but the accent mark is floating or misplaced. This usually isn't a font problem — it's an encoding issue related to how diacritics are stored.

Unicode allows two different ways to store the same visual character. Take é as an example:

- **NFC (precomposed):** é is stored as a single character — U+00E9
- **NFD (decomposed):** é is stored as e (U+0065) + a combining acute accent (U+0301)

Both are valid Unicode. Both should look the same on screen. But sometimes they don't — especially when the font doesn't handle combining characters well, when software expects one form and receives the other, or when combining marks are applied in the wrong order.

**NOTE** This is particularly common in tone languages. A word like ṵ̂ (u with macron below and circumflex above) requires stacking two diacritics — and if the combining characters are in the wrong order, or the font doesn't support complex mark positioning, the marks appear separately or stack incorrectly.

#### Activity: Spotting combining character issues (5 minutes)

Which is a combining character problem?

- **Example A:** café displays as caf▯
- **Example B:** café displays with the accent floating above the space after the e
- **Example C:** café displays as cafÃ©

**Answers:**

- **A — Font problem** (missing glyph for é)
- **B — Combining character problem** (decomposed é with wrong attachment)
- **C — Encoding problem** (mojibake)

### Culprit 4: Rendering engines (the chef)

The rendering engine is the software that takes encoded characters and font glyphs and puts them together on screen. Different programs do this differently — web browsers, Word, LibreOffice, PDF readers, and text editors all have their own rendering engines.

Even with the same font and encoding, different programs can display text differently. This is especially visible with complex scripts (Arabic, Devanagari, Thai), heavy use of combining characters, right-to-left text mixed with left-to-right, and ligatures.

For IPA and most Latin-based linguistic data, rendering problems are less common than font or encoding issues. But they do happen — particularly when exporting to PDF.

**WARNING** You might create a document in Word with perfect IPA display, then export to PDF and see problems. Same data, same font — but different rendering engine, different result. If this happens, investigate the PDF export method before assuming the data is wrong.

## Challenge

Work through each scenario. For each one: What pattern is this? Which culprit is most likely? What would you check or try?

### Scenario 1: The disappearing glottals

A colleague sends you a file with words from a language in Ethiopia. The glottal stop [ʔ] and the palatal fricative [ʃ] both display as boxes. The rest of the text looks fine.

**✏️ What pattern? Which culprit? What would you check first?**

**Answer:** Box pattern. Font problem — the font almost certainly doesn't have these IPA characters. Suggest switching to Charis SIL or Doulos SIL.

### Scenario 2: The wandering tone marks

A lexicographer shows you a tonal language entry. They typed tɔ̃́ː (with nasalisation and high tone), but it displays with the marks floating separately.

**✏️ What pattern? Which culprit? What would you investigate?**

**Answer:** Wrong mark pattern. Could be a combining character issue (NFD vs NFC), or a font rendering problem. Check: Is the font designed for complex diacritics? Are the combining marks in the right order in the file? Try Charis SIL, which has good mark positioning support.

### Scenario 3: The email that broke

Someone forwards you an email. On their end it looked fine. On yours, you see:

```
The linguistâ€™s data shows interesting patterns
```

It should read: *The linguist's data shows interesting patterns*

**✏️ What pattern? Which culprit? What happened?**

**Answer:** Mojibake. Encoding mismatch. The typographic apostrophe (’) was composed in UTF-8 but read as Windows-1252. The character got mangled in transit.

### When you have multiple problems

Sometimes a file has more than one problem at once — encoding issues and missing font glyphs together, for example. Fix them in order:

1. Start with **encoding** (mojibake)
2. Then fix **font** issues (boxes)
3. Then address **combining character** problems (wrong marks)

**WARNING** You can't diagnose font problems if the encoding is still wrong. Get the data readable first, then make it display correctly.

## Change

You now understand the three culprits behind broken text:

1. **Encoding** — the map from numbers to characters (mojibake when wrong)
2. **Fonts** — the shapes to draw characters (boxes when glyphs are missing)
3. **Rendering and combining** — putting it all together (wrong marks when broken)

Next time you encounter broken text, you won't just see the problem — you'll understand why it's happening. That understanding is what separates someone who can follow a recipe ("try this font") from someone who can diagnose and solve new problems they've never seen before.

**TIP** That's transfer. That's what makes you valuable as a language technologist — not memorising solutions, but understanding the problem well enough to diagnose new ones.

**✏️ Before Module 1c:** Bookmark [software.sil.org/fonts/](https://software.sil.org/fonts/) and [unicode.org/charts/](https://unicode.org/charts/).

Optional practice: find a file with broken text (or create one by changing encoding in Notepad++). Try to diagnose it — which culprit is it? Don't fix it yet. Just practise identifying the problem.
