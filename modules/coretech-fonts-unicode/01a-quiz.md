# Quiz 1a

[Backfilled from the Common Cartridge export `CoreTech__Fonts_Encoding_a_1785238471.imscc`
(saved 2026-07-28), `Modules/Module1/Quiz2.xml` — the LMS's "Quiz 1a" question bank. Questions,
wording, and correct answers are exactly as delivered, not authored fresh. The export declared
no pass threshold, so none is stated here.]

**Question 1 (True/False):** If a document shows boxes (□ or ▯) instead of certain IPA symbols, it usually means the font does not include the needed glyphs for those characters.
- A) True
- B) False

**Question 2 (True/False):** Mojibake (for example, seeing cafÃ© instead of café) usually happens because the file was saved in one encoding but opened as a different encoding.
- A) True
- B) False

**Question 3 (True/False):** Seeing an accent mark floating over the wrong place (like a tilde not attaching to the vowel) is often linked to how combining characters are stored or how normalisation is handled.
- A) True
- B) False

**Question 4 (True/False):** Even when the encoding and font are the same, two different programs (like Word and a PDF reader) can display the same text differently because they may use different rendering engines.
- A) True
- B) False

**Question 5 (True/False):** When a file seems to have multiple problems (like mojibake and boxes), the recommended order is to fix the font first and then fix the encoding.
- A) True
- B) False

**Question 6 (Select all that apply):** You open a file and see: cafÃ© instead of café. Which pattern(s) and likely cause(s) match this situation?
- A) Box pattern (missing glyphs)
- B) Wrong mark pattern (combining/normalisation issue)
- C) Mojibake (scrambled nonsense)
- D) Font problem (font lacks the character shapes)
- E) Encoding mismatch (saved in one encoding, opened as another)
- F) Rendering engine problem only

**Question 7 (Select all that apply):** Which of the following correctly matches each text display layer to its role?
- A) Encoding = provides glyph shapes to draw characters
- B) Encoding = tells the computer which character is which
- C) Font = provides glyph shapes to draw characters
- D) Font = decides which numbers represent characters
- E) Rendering engine = combines encoded characters and font glyphs to display text
- F) Rendering engine = the map from numbers to characters

**Question 8:** A colleague says: "In Word, my tone marks look correct, but after exporting to PDF, the marks look wrong." Based on the module, what is the best first suspicion?
- A) The font definitely does not include the needed characters
- B) A rendering engine difference (Word vs PDF export)
- C) The file must be corrupted and data is lost
- D) It is always Windows-1252 being read as UTF-8

**Question 9 (Select all that apply):** Unicode can store the same visual character in two valid ways. Which option describes that idea correctly?
- A) NFC stores é as one character (U+00E9)
- B) NFD stores é as e (U+0065) + combining acute accent (U+0301)
- C) Only NFC is valid Unicode; NFD is always wrong
- D) Both NFC and NFD are valid Unicode and should look the same, but sometimes display differs
- E) Mojibake is the same thing as NFD

**Question 10:** You receive a document with two problems at once: some characters are scrambled like Ã±, and some symbols show as boxes (▯). What is the recommended order to troubleshoot?
- A) Fix font issues first, then fix encoding
- B) Fix encoding first (to stop mojibake), then fix font issues (boxes), then address combining-mark issues if needed
- C) Only change the rendering engine; encoding and fonts don't matter
- D) Re-type the text manually because the data is lost

**Answer key:**

1. A | 2. A | 3. A | 4. A | 5. B | 6. C,E | 7. B,C,E | 8. B | 9. A,B,D | 10. B
