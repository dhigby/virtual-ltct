# MODULE 1: FLEX FUNDAMENTALS & PROJECT SETUP

**Duration:** 45 minutes  
**Format:** Asynchronous self-paced learning

## Learning Objectives

By the end of this module, you will be able to:
- Explain how FLEx's work areas relate to the tasks of grammatical analysis, with a focus on Texts & Words and Grammar
- Open, close, and navigate between FLEx projects
- Create a new FieldWorks project and configure an appropriate vernacular/analysis writing-system pair
- Back up, restore, and delete a FLEx project as routine project-management hygiene

## Section 1.1: What Is FLEx, and How Is It Organized?

_Time: 10 minutes_

FLEx — short for Language Explorer, though almost everyone just says "FLEx" — is one program inside a larger suite called FieldWorks, built to help language teams manage language and cultural data. What sets FieldWorks apart from general-purpose word processors or spreadsheets is that it already knows what linguistic data looks like: it has built-in structures for lexical entries, morphemes, glosses, and grammatical categories, so you're not reinventing a database every time you start a new language project. (You can read more about the FieldWorks suite at [www.sil.org/computing/fieldworks](https://www.sil.org/computing/fieldworks/).)

:::note
FLEx is built around a dictionary and a grammar that grow together: every time you interlinearize a text — breaking it into words, morphemes, and glosses — you're feeding information into the same lexicon and grammatical sketch that FLEx uses to suggest analyses for the *next* text. This course focuses on that grammar/interlinear side of FLEx: reading and analyzing texts, not building out a full dictionary (that's a separate course in this program).
:::

**The five work areas.** FLEx organizes everything into five areas, shown as icons down the left side of the workspace:

- **Lexicon** — the dictionary: entries, senses, definitions.
- **Texts & Words** — where you bring texts in and interlinearize them: baseline text, glosses, word analyses, concordance.
- **Grammar** — where FLEx keeps and displays your grammatical sketch: categories, features, and the rules the parser uses.
- **Notebook** — a place for research notes and records not tied to a specific text.
- **Lists** — custom lists (semantic domains, reversal categories, and so on) that feed the other areas.

This course lives almost entirely in **Texts & Words** and **Grammar** — that's where interlinearizing, parsing, and text charting happen. You'll see the other three areas exist and get a general sense of what they're for, but building out a Lexicon entry catalog, using Notebook, or editing Lists in depth belongs to other training in this program, not this one.

Clicking an area's icon switches the whole left-hand panel to a new set of views specific to that area — so the Texts & Words panel looks completely different from the Grammar panel, even though you're still in the same project.

**Opening FLEx.**

1. Start FLEx from its icon.
2. If no FLEx window is currently open, the **Language Explorer** picker dialog appears.

The picker dialog offers these choices:
- Open your last project (shown by name)
- Open a different existing project
- Create a new project
- Import Standard Format data into a new project
- Restore a project from a backup file

:::tip
The picker also has a checkbox for "always open the last edited project automatically." It's off by default, so unless you've turned it on, expect to see this picker every time FLEx has no windows open.
:::

**Closing a window.** FLEx supports multiple windows open at once — even two different projects side by side, which is handy for comparing data.

1. Use **File → Close** on the window you're currently in.

Results:
- If you still have another FLEx window open, that window stays open and nothing else happens.
- If the window you just closed was your last one, FLEx exits completely.

:::warning
Closing your last window does **not** return you to the picker dialog — it exits FLEx entirely. You'll only see the picker again the next time you relaunch FLEx with no windows open.
:::

### Activity 1.1a: Tour Your Own FLEx Installation

_Time: 6 minutes_

Open FLEx on your own machine (if you don't have it installed yet, install it before continuing — the rest of this course assumes a working installation).

1. Once FLEx is open, look at the icons down the left side of the workspace and find all five areas: Lexicon, Texts & Words, Grammar, Notebook, Lists.
2. Click into **Texts & Words** and then **Grammar** and notice how the left-hand list of views changes completely between them.
3. Click into **Lexicon**, **Notebook**, and **Lists** just long enough to see what each looks like — you won't work in these areas in this course, but it's worth knowing they're there.
4. Close all your FLEx windows using **File → Close** until FLEx exits completely, then relaunch it.
5. In the picker dialog, check whether "always open the last edited project automatically" is ticked, and note what would change about this dialog if you turned it on.

In your notes, jot down: which two areas will you be spending most of your time in during this course, and what's one thing you noticed inside each of them?

### Key Takeaways: Section 1.1

- FLEx is the grammar/dictionary-building program inside the FieldWorks suite; it already understands linguistic data structures like lexical entries, morphemes, and glosses.
- FLEx has five work areas — Lexicon, Texts & Words, Grammar, Notebook, Lists — accessed by icons on the left panel.
- This course focuses on **Texts & Words** (interlinearizing) and **Grammar** (the grammatical sketch the parser draws on); the other three areas exist but are out of scope here.
- The picker dialog appears whenever FLEx has no windows open. **File → Close** only closes your current window — if it was your last one, FLEx exits completely rather than returning to the picker.

## Section 1.2: Writing Systems — Vernacular vs. Analysis

_Time: 10 minutes_

Before you can interlinearize or parse anything, FLEx needs to know *what language* each piece of your data is in — otherwise it can't tell an English gloss from a Portuguese one, or know that the word you just typed is in the language you're documenting rather than the language you're describing it in.

**A writing system, in FLEx's terms, is a specific language paired with a specific script.** That pairing matters because a single language can be written more than one way — for example, the same word might appear in a practical orthography ("cat") or in IPA transcription ("kæt"). Each of those is a distinct writing system, even though it's "the same language." A writing system also carries a font (so the right characters actually display) and, where needed, a keyboard configuration (so you can type those characters without a specialty physical keyboard).

:::note
If you need a refresher on installing input languages and keyboards at the operating-system level, that's covered in the `coretech-os-basics` course — this course only covers how FLEx links a writing system to the font and keyboard you've already got set up.
:::

**Vernacular vs. analysis** is the distinction that matters most for this course:

- Your **vernacular writing system** is the language you're researching — the language of the text you're interlinearizing.
- Your **analysis writing system** is the language you write your glosses, descriptions, and notes in (often English, French, or another language of wider communication).

A project can have more than one of each — for instance, two vernacular writing systems if you're comparing dialects, or two analysis writing systems if your team writes glosses in more than one language. Whichever writing system is first in the list becomes the project's default.

### Check Your Understanding

Before moving on, make sure you can answer this without looking back: if you're documenting a language called Kiche and writing your grammatical notes in English, which is the vernacular writing system and which is the analysis writing system? (Kiche is vernacular; English is analysis.) If your team also needs to gloss some entries in Spanish, what would you add — a new vernacular writing system, or a new analysis writing system? (A new analysis writing system — the language you're researching hasn't changed, only the language you're describing it in.)

### Key Takeaways: Section 1.2

- A writing system = a specific language + script; it also carries a font and, if needed, a keyboard configuration.
- One language can have multiple writing systems (e.g., practical orthography vs. IPA).
- **Vernacular** = the language you're researching. **Analysis** = the language(s) you write glosses and descriptions in.
- A project can hold multiple vernacular and multiple analysis writing systems; the first one listed is the default.

## Section 1.3: Creating a New Project & Setting Up Writing Systems

_Time: 15 minutes_

Now you'll put those concepts to work by creating a project of your own.

:::note
FLEx's exact menu wording can shift slightly between versions. If something on your screen doesn't match exactly, look for the closest equivalent — the steps below should still apply.
:::

**Creating a new project**

1. From the FLEx picker dialog (or **File → New FieldWorks Project** if you already have a project open), choose to create a new project.
2. Name your new project.
3. Choose an **Analysis** writing system (e.g., English).
4. Define a **New** vernacular writing system for the language you're documenting, completing the writing-system properties you're prompted for.
5. Click OK.

Result:
- FLEx creates the project and opens it.

**Confirming or adjusting writing systems after creation**

In current FLEx, writing-system setup is handled by two separate commands rather than a single shared tab in Project Properties:

1. To review or adjust the language you're researching, use **Tools → Configure → Set up Vernacular Writing Systems...** (or **Format → Set up Vernacular Writing Systems...**).
2. To review or adjust the language(s) you gloss in, use **Tools → Configure → Set up Analysis Writing Systems...** (or the equivalent **Format** menu command).

:::warning
There is no single shared "Writing Systems" tab in Project Properties anymore. Vernacular and analysis writing systems are configured through two distinct commands — you'll switch between them if you need to touch both.
:::

### Activity 1.3a: Create a Test Project

_Time: 12 minutes_

1. From FLEx, start a new project (**File → New FieldWorks Project**, or from the project picker).
2. Name it something clearly temporary, like "Test Project" — you'll delete it later in this module.
3. For the **Analysis** writing system, choose English (or whatever language you write your notes in).
4. For the **Vernacular** writing system, either pick a language you're documenting in the field, or — if you don't have one yet — use a placeholder like "Test Language" so you have something to practice with.
5. Once the project opens, open **Tools → Configure → Set up Vernacular Writing Systems...** and check that your vernacular language, font, and keyboard look right.
6. Open **Set up Analysis Writing Systems...** and do the same for your analysis language.
7. Switch to the Lexicon area and add one quick sample entry (any word will do).

Result:
- You can confirm both writing systems are producing correct output before you invest time in real data. (You're not learning lexicon editing here — this step is only to sanity-check your setup.)

### Key Takeaways: Section 1.3

- New projects are created from **File → New FieldWorks Project**, where you set your initial analysis and vernacular writing systems.
- Reviewing or adjusting writing systems afterward is a two-command job in current FLEx: **Set up Vernacular Writing Systems...** and **Set up Analysis Writing Systems...**, both reachable via Tools → Configure or the Format menu — there is no single shared "Writing Systems" tab anymore.
- A quick sample entry is a fast way to confirm your vernacular and analysis writing systems are both displaying correctly before you invest time in real data.

## Section 1.4: Keeping Your Project Safe — Backup, Restore & Delete

_Time: 10 minutes_

:::info
Field conditions — unreliable power, aging hardware, limited or no IT support nearby — make routine project-management hygiene more important than it might seem from an office. A corrupted or lost project can mean months of interlinearization work gone. Backing up is not optional housekeeping; it's part of the job.
:::

**Backing up a project**

1. Go to **File → Project Management → Back up this Project**.
2. Enter a short comment describing the state of the project (so future-you can tell backups apart).
3. Click OK.

Result:
- FLEx saves a backup, labeled with your comment, that you can restore later.

**Restoring a project**

1. Go to **File → Project Management → Restore a Project**.
2. Select the project and the specific backed-up version you want.
3. Click OK.
4. Confirm that you want to replace the existing project when prompted.

Result:
- The selected backup replaces the project's current contents.

:::danger
Restoring is destructive to whatever's currently in that project slot — make sure you're restoring the version you actually intend to.
:::

**Deleting a project**

1. Close the project first (**File → Close**).
2. Go to **File → Project Management → Delete Project**.
3. Select the project and choose Delete.
4. Confirm Yes, then Close.

Result:
- The project is permanently removed.

:::danger
Like restoring, deleting cannot be undone. Always be sure you have a backup of anything worth keeping before you delete.
:::

### Activity 1.4a: Back Up Your Test Project

_Time: 5 minutes_

1. With your test project from Activity 1.3a open, go to **File → Project Management → Back up this Project**.
2. Enter a comment like "Module 1 test backup" and click OK.
3. Go to **File → Project Management → Restore a Project** to confirm your backup shows up in the list (you don't have to actually restore it — just verify it's there, then cancel out).
4. Close the test project and delete it using the Delete Project steps above.

:::tip[Why this matters]
In a remote setting, "I'll back it up later" often means never. Building the backup habit now, on a throwaway test project, makes it automatic once you're working with real field data.
:::

### Key Takeaways: Section 1.4

- Back up with **File → Project Management → Back up this Project**, including a comment that will help you tell versions apart later.
- Restore with **File → Project Management → Restore a Project**, choosing the specific version you need — this replaces the existing project, so confirm before you proceed.
- Delete a closed project with **File → Project Management → Delete Project** — like restoring, this is not reversible.
- In field conditions with unreliable power and little local IT support, routine backups are basic risk management, not optional extra work.

---

## Module 1 Summary

### You've Learned

- **What FLEx is and how it's organized:** FLEx is the grammar/dictionary side of the FieldWorks suite, built around five work areas. This course concentrates on **Texts & Words** and **Grammar**, where interlinearizing and parsing happen.
- **Writing systems:** a writing system pairs a language with a script (plus font and keyboard); your **vernacular** writing system is the language you're researching, your **analysis** writing system is the language you gloss and describe it in.
- **Project setup:** you can create a new project and set its initial writing systems, and you know how to review or adjust vernacular and analysis writing systems afterward using FLEx's current two-command setup.
- **Basic project hygiene:** backing up, restoring, and deleting projects — small habits that protect real field work from real field risks.

### Key Takeaways

- FLEx organizes language and cultural data into five areas; this course lives in Texts & Words and Grammar.
- Vernacular = the language you're documenting; analysis = the language you describe it in — get this pairing right before you start interlinearizing.
- Writing-system setup in current FLEx is two separate commands (vernacular, analysis), not one shared tab — don't go looking for the old Project Properties "Writing Systems" tab.
- Back up early and often; restoring and deleting are both irreversible, so treat them with care.

### Before Module 2

You now have a project with a working vernacular/analysis writing-system pair — exactly what you need to start bringing in real text. Module 2 picks up right where this leaves off: inserting a text into your project and interlinearizing it, baseline through gloss, including creating new lexical entries on the fly as you go.
