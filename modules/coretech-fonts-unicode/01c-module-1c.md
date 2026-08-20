# Module 1c: Your Diagnostic Toolkit

**Estimated time:** 60 minutes

**Purpose:** To build the practical skills and resources for investigating font, encoding, and rendering problems systematically, rather than guessing.

## Connect

In Module 1a, you learned to recognise broken text patterns. In Module 1b, you learned to understand why they happen. Now it's time to build your diagnostic toolkit — the practical skills and resources to investigate problems systematically.

Here's a scenario you'll encounter: a colleague messages you:

> "Help! I have a wordlist file that looks completely broken. Can you fix it?"

With what you've learned so far, you might think: "Is it encoding? Font? Both?" But how do you actually check? How do you look under the hood to see what's really going on?

**INFO** This module is about moving from educated guesses to confident diagnosis. By the end, you'll have a toolkit you can apply consistently — and teach to others.

**✏️ Take 2 minutes to think:** What tools do you currently use when you encounter a file problem? Do you try different fonts randomly? Open files in different programs hoping one works? Do you have a systematic approach? Most people start with trial and error — that's fine for learning. But as a consultant, you need methods you can teach to others and apply consistently.

## Content

*Whether you're coming to this course after the Hardware or OS modules, or starting here, you'll recognise a familiar pattern: recognise the pattern first, understand why it's happening, then investigate systematically. If you've already worked through the Hardware or OS courses, this will feel familiar — the same mindset, applied to a different problem. If this is your first course, the approach you're learning here will transfer directly when you encounter hardware or OS issues later.*

**TIP** The diagnostic toolkit you're building in this module reflects the same approach you'll find throughout this program: recognise the pattern first, understand why it's happening, then investigate systematically. If you've already worked through the Hardware or OS courses, this will feel familiar. If this is your first course, the approach you're learning here will transfer directly when you encounter hardware or OS issues later.

### Tool 1: Checking file encoding

When you see mojibake, the first question is: what encoding is this file actually in? You can't fix an encoding problem if you're guessing.

#### Notepad++ (Windows)

Notepad++ is a free text editor that detects and displays file encoding.

1. Open the file in Notepad++
2. Look at the bottom right of the window — you'll see: UTF-8, ANSI, UTF-8-BOM, etc.
3. If the text looks broken, go to **Encoding → Convert to UTF-8**, or try **Encoding → Character sets** to see if a different encoding makes the text readable.

**WARNING** What you're seeing is Notepad++'s best detection — not always what the file should be. If the file is genuinely misencoded, you may need to try a few options.

#### Terminal (Mac/Linux)

In Terminal, run:

```
file -I filename.txt
```

This shows the detected encoding. Like Notepad++, it's an educated guess — not always correct if the file is misencoded.

#### Hex editor (advanced)

For advanced diagnosis, a hex editor shows the actual bytes stored in a file. This lets you see exactly what numbers are stored, which can confirm encoding issues definitively.

This is beyond the `1 - Has Knowledge` level, but it's good to know it exists for when you need to go deeper.

#### Activity: Encoding detective work (8 minutes)

If you have access to sample files:

1. Open a UTF-8 text file and a Windows-1252 text file in Notepad++
2. Check the encoding shown for each
3. Try deliberately changing the encoding (Encoding → Character sets) and watch what happens to the text

If you don't have sample files right now, work through this scenario mentally: you receive a file showing **cafÃ©** instead of **café**.

- What encoding was it probably saved in? (UTF-8.)
- What encoding is it being read as? (Windows-1252.)
- How would you fix it? (Open in an editor, explicitly select UTF-8.)

**NOTE** Encoding problems aren't about the file being wrong — they're about reading it with the wrong decoder.

### Tool 2: Checking font coverage

When you see boxes, the question is: does this font actually have these characters? You can't assume — you need to check.

#### SIL font documentation

Every SIL font has a documentation page listing character coverage.

1. Go to [software.sil.org/charis/](https://software.sil.org/charis/) (or the relevant font page)
2. Click **Character Set Support** or **About**
3. Look for which Unicode blocks are included — IPA Extensions, Latin Extended-A/B/C/D, specific scripts

#### Type and see

The simplest test:

1. Open a text editor or word processor
2. Type or paste the problem characters
3. Change fonts and see which ones display them correctly

Test systematically: try Arial (limited coverage), Times New Roman (slightly better), then Charis SIL or Doulos SIL (comprehensive linguistic coverage), then Gentium Plus (excellent for extended Latin).

#### Character map utilities

These tools let you browse every character a font contains, and search by Unicode value.

- **Windows:** Character Map utility (search in the Start menu)
- **Mac:** Font Book
- **Linux:** GNOME Character Map

#### Activity: Font coverage comparison (10 minutes)

Go to [software.sil.org/fonts/](https://software.sil.org/fonts/) and explore three fonts: Charis SIL, Doulos SIL, and Andika. For each one, look at the Character Set Support section and answer:

- Do all three include full IPA coverage?
- Which one is designed specifically for beginning readers?
- Which would you recommend for academic publications, literacy materials, and screen display?

If you have these fonts installed, paste this string and compare how each font displays it: [ŋ ʈ ɲ ɔ̃ː ṵ̂]

**Answers:**

- Yes — all three include full IPA coverage
- Andika is designed for literacy contexts (clarity for beginning readers)
- Charis or Doulos for academic work; Andika for literacy materials; any work well on screen

**TIP** It's not just "can the font display the characters?" — it's also "is it appropriate for this use case?" The same IPA coverage can appear in a scholarly article (Charis SIL) or a literacy primer (Andika). The right font depends on your audience.

### Tool 3: Identifying Unicode values

Sometimes you need to know exactly which character you're looking at — especially when two characters look identical but behave differently, or when you need to verify NFC vs NFD.

Every character has a unique code point written as U+XXXX. For example:

- a = U+0061
- é (precomposed) = U+00E9
- e + combining acute = U+0065 + U+0301
- ŋ (eng) = U+014B

#### Unicode website

Go to [unicode.org/charts/](https://unicode.org/charts/), find your character, and note its code point. Or search: "unicode latin small letter eng" → U+014B.

#### Unibook (free SIL tool)

Paste text into Unibook and it shows you the exact code points for every character. This is the most reliable method for verifying NFC vs NFD.

Download at: [scripts.sil.org/unibook](https://scripts.sil.org/unibook)

#### Your text editor

Some editors (like Notepad++) show Unicode values in the status bar when your cursor is positioned on a character. This is a quick check without needing a separate tool.

#### Activity: Character identity check (5 minutes)

A colleague shows you two files. In both, you see what looks like "ã" (a with tilde). But in File 1 it's one character (precomposed). In File 2 it's two characters (base + combining mark). How would you verify this?

**Answer:**

1. Copy-paste the character into a text editor
2. Use arrow keys to move the cursor — does it take one keystroke or two to move past it?
3. Use Unibook or a Unicode identifier to see the code points

File 1: U+00E3 (one code point = precomposed). File 2: U+0061 + U+0303 (two code points = decomposed). Both display the same — but they behave differently when searching, sorting, or converting between programs.

**NOTE** Visual appearance doesn't tell you everything. Sometimes you need to see the actual Unicode values to understand why two files that look the same behave differently.

### Tool 4: Knowing what to know vs. what to look up

As a language technology consultant, you won't memorise everything. What matters is knowing what to keep in your head and what to look up.

**Keep in your head:**

- The three main culprits — encoding, font, rendering
- The three patterns — boxes, wrong marks, mojibake
- Where to find SIL fonts
- The basic troubleshooting sequence

**Know where to look up:**

- Specific Unicode values
- Whether a particular font supports a specific character
- How to convert between specific encodings
- Technical details about normalisation forms

**You don't need to memorise:**

- Every Unicode block
- Every encoding standard
- Every font's complete character set
- Exact conversion procedures (that's what Course 2 covers)

#### Activity: Build your quick reference (5 minutes)

Create a simple reference note with these three sections:

- **Most common problems I'll encounter** — based on your language context: IPA? Specific scripts? Tone marks?
- **Go-to fonts:** my default for linguistic work / for literacy materials / for academic publications
- **When I'm stuck:** where I check encoding / where I check font coverage / where I identify characters

This becomes your personal toolkit that you'll expand as you gain experience.

## Challenge

Let's put everything together with a realistic scenario.

> "Hi! I'm working on a lexicon for a language in Indonesia. The community gave me data in a Word file. When I open it: some IPA symbols show as boxes, the word 'ñ' shows as 'Ã±', and tone marks like ǎ look fine in Word but break when I save as PDF. Can you help me figure out what's wrong?"

**✏️ Work through this systematically before reading the answers.** What patterns do you see? What would you check first? What would you check next? What would you tell the colleague?

**Step-by-step answer:**

**Step 1: Identify the patterns**

- Boxes → font problem
- Ã± → mojibake (encoding problem)
- PDF break → rendering problem

This file has all three culprits at once.

**Step 2: Fix encoding first**

1. Open the file in Notepad++ or another editor that shows encoding
2. Check: is it UTF-8? Windows-1252? Something else?
3. If it shows Ã±, it's probably UTF-8 being read as Windows-1252
4. Convert to UTF-8 and save

**Step 3: Fix the font**

1. What font is the document using?
2. Does that font have IPA coverage?
3. Switch to Charis SIL or Doulos SIL and check whether the boxes become proper characters

**Step 4: Investigate the PDF problem**

This is a rendering issue — Word's engine handles tone marks correctly, but the PDF export doesn't. Check whether the tone marks are precomposed or decomposed. Try normalising to NFC before exporting, or explore a different PDF export method.

**Sample response to your colleague:**

> "Good news — your data isn't lost! You have three separate issues, and we can work through them in order.
>
> First, the scrambled characters (Ã±): your file is UTF-8 but being opened as Windows-1252. Open it in Notepad++ and explicitly choose UTF-8 encoding. This should fix the scrambled text.
>
> Second, the boxes: your current font doesn't include IPA symbols. Download Charis SIL from software.sil.org/charis/ and change your document to use it. The boxes should become proper characters.
>
> Third, the PDF issue: this is trickier and depends on the first two being fixed. Once you've sorted encoding and font, let me know what you see in the PDF and we'll investigate from there."

## Change

### Your diagnostic competency

You now have a complete diagnostic toolkit across all three modules:

- **Recognition (Module 1a):** boxes, wrong marks, mojibake
- **Understanding (Module 1b):** encoding, fonts, rendering and combining
- **Investigation (Module 1c):** check encoding, check font coverage, identify characters, consult resources

At the `1 - Has Knowledge` level, you can identify what's wrong, understand why it's happening, investigate systematically, explain problems to others, and know where to find help.

### What comes next: Course 2

Course 2 (Troubleshooting & Conversion) moves from diagnosis to fixing: converting files between encodings, repairing damaged text, setting up systems to prevent problems, and training others.

**Before then, start practising with this approach:**

When you encounter broken text:

1. Stop and diagnose before fixing
2. Ask: which pattern? Which culprit?
3. Investigate systematically using your tools
4. Document what you find

When someone asks for help:

1. Teach them to recognise the patterns
2. Show them how to investigate
3. Point them to resources
4. Build their diagnostic skills, not just fix it for them

**TIP** This is the shift from helper to consultant — you're building capacity, not just solving immediate problems.

### Immediate next steps

- Bookmark the essential resources (SIL fonts, Unicode charts — see below)
- Install Notepad++ or another editor that shows encoding
- Download Charis SIL and install it
- Create your personal quick-reference guide from the activity above
- Look for broken text in your current work and practise diagnosing before fixing
- Help a colleague understand a font or encoding problem — explaining it is one of the best ways to consolidate what you've learned

**✏️ Final reflection:** What font or encoding problem in your work context would you most like to be able to solve confidently? Keep that in mind as you move to Course 2. Your diagnostic skills are the foundation — now it's time to build the fixing skills on top of them.

### Essential resources

- [SIL Fonts](https://software.sil.org/fonts/) — software.sil.org/fonts/
- [SIL Scripts and Unicode](https://scripts.sil.org/) — scripts.sil.org/
- [Unicode Charts](https://unicode.org/charts/) — unicode.org/charts/
- [Unicode Standard](https://unicode.org/standard/standard.html) — unicode.org/standard/standard.html
- [SIL Language Technology Tools](https://software.sil.org/) — software.sil.org/
- [Unibook (character inspection tool)](https://scripts.sil.org/unibook) — scripts.sil.org/unibook
- [Charis SIL font](https://software.sil.org/charis/) — software.sil.org/charis/
