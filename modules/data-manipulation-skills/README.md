---
title: Data manipulation skills
slug: data-manipulation-skills
notion_id: ce837409-3fb9-42bd-8a17-f8946606ef0e
notion_data_source: 7ebb2ef5-9f57-4723-8de0-9cc1ca47ff8e
target_outcome_level: Has knowledge
competencies:
  - Data Conversion
content_type: content
external_links:
  materials: TBD
last_exported: 2026-06-18
---

# Data manipulation skills

Just one lesson on RegEx (User menu for Paratext) for AILTW, but with Typesetters long list of RegEx
- userMenu.txt (copy to My Paratext x Projects
- the lesson is designed for trainer, with trainer notes scattered throughout.
- <span color="pink">Errors in video: change 4:36 -4:48 / to \\  </span>
[RegEx__A_Consultant_s_Guide](https://vimeo.com/1179288729)
<empty-block/>
# Lesson
## title: "Using the RegEx Pal User Menu"<br>course: Language Technology Consultant Development<br>session_number: 7<br>duration: 60<br>level: With Assistance<br>goal: Use the RegEx Pal User Menu with guidance to find, count, extract, and replace patterns in a Paratext project
# Using the RegEx Pal User Menu
**Approximate time:** Self-paced
**Level:** With Assistance
**Goal:** Use the RegEx Pal User Menu with guidance to find, count, extract, and replace patterns in a Paratext project
---
## Connect
**Approximate time:** 10 minutes
### Activity: What Would You Look For?
Think about a translation project you are currently working on or supporting. Before a book is sent for consultant checking, someone needs to make sure there are no small formatting problems hiding in the text.
Take a few minutes to think about these questions. You may find it helpful to jot down your answers before reading on.
- What kinds of formatting errors are hardest to spot just by reading through the text?
- Have you ever spent time looking for an error that turned out to be something very small — a missing space, a wrong character?
- What would help you find those problems more quickly?
> A translator had just finished checking a complete book of the Bible and was ready for consultant review. Their mentor noticed that several formatted spans of text — divine names, emphasis markers — were not rendering correctly in the typeset output. Tracking down the cause took the better part of a day: a space had crept in before several closing markers like `\nd*` and `\wj*`, invisible when reading the text but enough to break the formatting. Later they discovered that RegEx Pal could find every instance across the entire book in seconds.
**Key Point:** The User Menu in RegEx Pal is a collection of ready-made regex tools. The regex has already been written for you — your task is simply to learn how to run the tools and understand what the results mean.
---
## Content
**Approximate time:** 20 minutes
### What Is the User Menu?
The **User Menu** in Paratext RegEx Pal is a list of saved regex operations that you can run on your project with a single click. Someone has already done the work of writing the regex — all you need to do is select the right operation and run it.
To open it, click **User** in the menu bar. You will see a list of items. Your list may look different from your mentor's list or a colleague's list — that is completely normal. User menus can be customised over time.
The operations in the menu fall into four categories:
- **Find** — locate potential problems in the text
- **Count** — measure how many times a pattern appears
- **Extract** — pull specific content out for review
- **Replace** — automatically fix a known pattern
In this session you will try one operation from each category, with step-by-step guidance for each one.
**Key Point:** Before running any operation, always check which books are selected. By default, RegEx Pal works across your entire project. Use **Tools \> Choose Books** to limit the operation to one book at a time. This makes results much easier to understand, especially when you are just getting started.
---
### Category 1: Find
A Find operation searches your project and shows you lines or locations that match a pattern. It does not change anything in your text — it only shows you where to look.
### Find close codes preceded by a space
In USFM, a closing marker like `\f*` or `\nd*` must follow the text directly, with no space before the asterisk. A space before the asterisk is a very common typing mistake. It is invisible when you read the text, but it can cause formatting problems in the published output.
**What the error looks like:**
`this is a footnote \f*` ← the space before `\f*` is the problem
**How to run it:**
1. Open RegEx Pal and make sure your project is selected.
2. Use **Tools \> Choose Books** to select one book.
3. Click **User** in the menu bar.
4. Click **Find close codes preceded by a space**.
5. Look at the results. Each result shows a location where a space appears before a closing marker.
**What to do with the results:**
- If you get results, note the references and go to each location in Paratext to remove the extra space.
- If you get zero results, that is a good outcome — it means no errors of this type were found in the book you selected.
**Key Point:** Find operations are always safe. They never change your text. You can run them as many times as you like.
> **Check in with your mentor:** Share your results. How many did you find? Were any of them surprising? Your mentor can help you decide which results need attention.
---
### Category 2: Count
A Count operation tells you how many times a pattern appears in your project or selected book. It gives you a number — and sometimes a list of references — rather than taking you to a specific location.
### Count all cap words
This counts words written entirely in capital letters. All-cap words can appear for legitimate reasons — for example, some projects use them for divine names or section labels. But unexpected all-cap words can indicate a formatting or import error.
**How to run it:**
1. Use **Tools \> Choose Books** to select one book.
2. Click **User** in the menu bar.
3. Click **Count all cap words**.
4. Note the number returned.
**What to do with the result:**
- Think about whether the number seems right for the book you selected.
- If your project uses all-cap words for a specific purpose, does the count match what you would expect?
- If the number seems unexpectedly high, make a note to investigate further using a Find operation.
**Key Point:** A Count result on its own does not tell you whether something is right or wrong — you need to know your project's conventions first. If you are unsure how to interpret the number, discuss it with your mentor.
> **Check in with your mentor:** Share the number you got. Does it seem consistent with the project's conventions? Your mentor can help you decide whether it warrants further investigation.
---
### Category 3: Extract
An Extract operation copies matching content into a separate list so you can review it outside the main text. Nothing is deleted or changed in your project.
### Extract all footnotes
This pulls every footnote from the selected book into a list with references, so you can read through them without navigating the whole text.
Note that Paratext's own Checklist view is often more useful for detailed footnote review — it can display just the footnotes alongside a comparative text. The Extract operation is useful when you want a plain text list, or when the Checklist view is not available.
**How to run it:**
1. Use **Tools \> Choose Books** to select one book.
2. Click **User** in the menu bar.
3. Click **Extract all footnotes**.
4. Look through the list of results.
**What to do with the results:**
- Read through the extracted footnotes.
- Check whether they look consistent in style and format.
- If you notice anything that looks unusual, note the reference so you can check it in context.
**Key Point:** Extract operations are always safe. The original text is never changed — the operation only copies content into the results list.
> **Check in with your mentor:** Share anything you noticed in the extracted list. Your mentor can help you decide whether any of the footnotes need attention.
---
### Category 4: Replace
A Replace operation finds a pattern and changes it automatically. This is the most powerful type of operation in the User Menu — and the one that requires the most care.
**Important:** Always run a Find first before running a Replace. This lets you see exactly what will be changed before anything is modified. This is a good habit to build from the start.
### Replace missing space after \\v
In USFM, a verse marker must be followed by a space before the verse number: `\v 3`. If the space is missing — `\v3` — this operation adds it automatically. This is a common problem in projects where text has been imported or pasted from another source.
**How to run it:**
1. Use **Tools \> Choose Books** to select a test book — ideally not your main working book while you are learning.
2. Click **User** in the menu bar.
3. Click **Find close codes preceded by a space** first — wait, for this Replace the corresponding Find is **Replace missing space after \\v**. Instead, look at the results of running the Find for `\v` patterns first to see if any are missing spaces.
4. When you are ready, click **Replace missing space after \\v**.
5. Run the corresponding Find again — the result count should now be zero.
**The workflow to remember:**
**Find → Replace → Find again to confirm zero results**
**Key Point:** Replace operations change your text permanently. Always use **Tools \> Choose Books** to limit the scope, always run Find first, and if you are unsure — check with your mentor before proceeding.
> **Check in with your mentor:** Walk through this operation together if possible, especially the first time. Your mentor can confirm you are following the Find-Replace-verify sequence correctly.
### Key Takeaways
- The User Menu contains ready-made tools — you do not need to write regex to use them
- **Find** and **Count** operations are always safe — they never change your text
- **Extract** operations copy content for review without modifying the original
- **Replace** operations are powerful — always run Find first and check with your mentor if unsure
- Always use **Tools \> Choose Books** to limit the scope before running any operation
---
## Challenge
**Approximate time:** 20 minutes
### Activity: Try It On Your Own Project
Work through each step below using a Paratext project. Take your time — there is no rush. If you are unsure about a result at any point, make a note and discuss it with your mentor.
**Step 1 — Find**
Run **Find close codes preceded by a space** on one book of your project.
- How many results did you get?
- Can you see the space before the closing marker in each result?
- Note the references of any results you found.
- *If you got zero results: well done — this book has no errors of this type.*
**Step 2 — Count**
Run **Count all cap words** on the same book.
- What number did you get?
- Does that seem consistent with how your project uses all-cap words?
- Make a note of the number so you can discuss it with your mentor.
**Step 3 — Extract**
Run **Extract all footnotes** on the same book.
- Look through the list.
- Do the footnotes look consistent?
- Note anything that looks unusual.
**Step 4 — Replace**
Run **Replace missing space after \\v** on a test book.
- First, check whether there are any missing spaces by looking at the text.
- Run the Replace.
- Run **Find** to verify the results are now zero.
- If anything looks unexpected, stop and check with your mentor before continuing.
### Reflection Question
After working through these four steps, which operation felt most straightforward, and which felt least confident about? Note your answer — it is a useful starting point for your next conversation with your mentor.
---
## Change
**Approximate time:** 5 minutes
### Personal Action Plan
Choose one action you will take before your next check-in with your mentor:
### Option 1: Run the Find check on another book
Run **Find close codes preceded by a space** on a second book in your project. Note the results and bring them to your mentor.
### Option 2: Run the Count operation on two or three books
Run **Count all cap words** on two or three books and record the numbers. Are they consistent with each other? Bring your notes to your mentor to discuss.
### Option 3: Try the Replace workflow on a test project
If you have access to a test project, try the full Find-Replace-verify sequence for **Replace missing space after \\v**. Write down each step as you do it, and share what you noticed with your mentor.
### Looking Ahead
Once you are comfortable using the User Menu with guidance, the next step is to use these tools independently — selecting the right operation for a given situation, interpreting the results without assistance, and deciding what action to take.
The Level 3 session on the RegEx Pal User Menu builds directly on what you have learned here. It covers a wider range of operations across all four categories and develops your ability to use the tools confidently in your own work.
---
Share
**Content**
![](/api/f9ab80ba-bcf0-48f0-879f-d4c6f5682241/files/be39b4a4-7fa0-4784-b063-a9af8cae035e/preview)
![](/api/f9ab80ba-bcf0-48f0-879f-d4c6f5682241/files/4f75b171-c65a-421c-b463-3c5b6b955d7f/preview)
![](/api/f9ab80ba-bcf0-48f0-879f-d4c6f5682241/files/da32b648-05f8-409e-8c4d-f6e0a1a37890/preview)
![](/api/f9ab80ba-bcf0-48f0-879f-d4c6f5682241/files/43000006-019b-4d40-a263-1beca4b360c2/preview)
![](/api/f9ab80ba-bcf0-48f0-879f-d4c6f5682241/files/872c0e91-245b-427d-b38e-d033fd2e64ad/preview)
**userMenu - Phil.txt**<br>txt
![](/api/f9ab80ba-bcf0-48f0-879f-d4c6f5682241/files/c266ad58-fd37-493f-bfbd-9d940f17fe93/preview)
