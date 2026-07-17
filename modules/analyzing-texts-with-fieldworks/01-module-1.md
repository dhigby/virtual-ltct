# Module 1: FLEx Fundamentals & Project Setup

**Estimated time:** 45 minutes

**Purpose:** Before you can help a translation team interlinearize or analyze their texts, you need to know your way around FLEx and be able to set up a project correctly — with the right vernacular and analysis writing systems — so their work is usable from the start.

## Learning objectives

By the end of this module you will be able to:

- Explain how FLEx's five work areas relate to grammatical analysis, and identify which two you'll use in this course.
- Distinguish a project's vernacular writing system from its analysis writing system.
- Create a new FLEx project with an appropriate vernacular/analysis writing-system pair.
- Back up, restore, and delete a FLEx project as routine project-management hygiene.

## Connect

**✏️ Reflection.** Think about a translation team you support — or imagine one — who has just received a laptop with FLEx installed for the first time. They've never opened it before.

- If they opened FLEx right now and started typing, what could go wrong before they even get to analyzing a single sentence?
- Have you ever used software where you had to tell it "this is Language A, and I'm describing it in Language B" before anything else worked correctly? What happened when that wasn't set up correctly?

Hold onto that scenario — this lesson is about making sure a project is set up correctly before real work begins, not fixing it after the fact.

## Content

FLEx — short for Language Explorer, though almost everyone just says "FLEx" — is one program inside a larger suite called FieldWorks, built to help language teams manage language and cultural data. It already understands linguistic data structures — lexical entries, morphemes, glosses, grammatical categories — so a team doesn't reinvent a database every time they start a new language project. (More at [www.sil.org/computing/fieldworks](https://www.sil.org/computing/fieldworks/).)

**INFO** FLEx is built around a dictionary and a grammar that grow together: every time you interlinearize a text, you feed information into the same lexicon and grammatical sketch that FLEx uses to suggest analyses for the *next* text. This course focuses on that grammar/interlinear side — reading and analyzing texts, not building out a full dictionary (that's a separate course in this program).

**The five work areas.** FLEx organizes everything into five areas, shown as icons down the left side of the workspace:

- **Lexicon** — the dictionary: entries, senses, definitions.
- **Texts & Words** — where texts are brought in and interlinearized: baseline text, glosses, word analyses, concordance.
- **Grammar** — the grammatical sketch: categories, features, and the rules the parser uses.
- **Notebook** — research notes and records not tied to a specific text.
- **Lists** — custom lists (semantic domains, reversal categories) that feed the other areas.

This course lives almost entirely in **Texts & Words** and **Grammar**. The other three areas exist — you'll notice them — but building out a Lexicon catalog, using Notebook, or editing Lists belongs to other training in this program.

**Writing systems.** Before FLEx can interlinearize or parse anything, it needs to know what language each piece of data is in. A **writing system** is a specific language paired with a specific script — for example, the same word might appear in a practical orthography ("cat") or IPA ("kæt"); each is a distinct writing system even though it's "the same language." A writing system also carries a font and, where needed, a keyboard configuration.

- Your **vernacular writing system** is the language you're researching — the language of the text being interlinearized.
- Your **analysis writing system** is the language you write glosses, descriptions, and notes in (often English, French, or another language of wider communication).

A project can have more than one of each. Whichever writing system is listed first becomes the project's default.

**NOTE** If you need a refresher on installing input languages and keyboards at the operating-system level, that's covered in the `coretech-os-basics` course. This course only covers how FLEx links a writing system to the font and keyboard you've already got set up.

**Key takeaways**

- FLEx's five work areas are Lexicon, Texts & Words, Grammar, Notebook, and Lists; this course lives in Texts & Words and Grammar.
- A writing system = a specific language + script, with a font and (if needed) a keyboard configuration attached.
- Vernacular = the language being researched. Analysis = the language used to describe it. A project can hold more than one of each.

## Challenge

### Exercise 1.1 — Set up a first project for a new team

**The situation.** A translation team has just received a laptop with FLEx installed, and they're documenting a language for the first time. They've asked you to get their first project ready so they can start interlinearizing texts. Before you hand it back to them, you need to set it up correctly and prove to yourself — and to them — that it's working.

**Create the project:**

1. Start FLEx. If no FLEx window is open, the **Language Explorer** picker dialog appears, offering to open your last project, open a different project, create a new project, import Standard Format data, or restore from a backup.
2. Choose to create a new project (from the picker, or **File → New FieldWorks Project** if you already have a project open).
3. Name it something clearly temporary, like "Test Project" — you'll delete it later in this exercise.
4. For the **Analysis** writing system, choose English (or whatever language you write your notes in).
5. For the **Vernacular** writing system, either pick a language you're documenting in the field, or use a placeholder like "Test Language."
6. Complete the writing-system properties you're prompted for and click OK.

**Result:** FLEx creates the project and opens it.

**TIP** The picker has a checkbox for "always open the last edited project automatically," off by default — so expect to see this picker whenever FLEx has no windows open.

**Verify the writing systems:**

1. Use **Tools → Configure → Set up Vernacular Writing Systems...** (or **Format → Set up Vernacular Writing Systems...**) — check the vernacular language, font, and keyboard.
2. Use **Set up Analysis Writing Systems...** — do the same for the analysis language.

**WARNING** There is no single shared "Writing Systems" tab in Project Properties anymore. Vernacular and analysis writing systems are configured through two distinct commands.

**Confirm it's working and back it up:**

1. Switch to the Lexicon area and add one quick sample entry (any word will do) to confirm both writing systems are producing correct output.
2. Back up the project: **File → Project Management → Back up this Project**, enter a comment (e.g. "Module 1 test backup"), click OK.
3. Confirm the backup exists: **File → Project Management → Restore a Project** — check your backup shows up in the list, then cancel out without actually restoring.

**DANGER** Restoring replaces the project's current contents and cannot be undone. Only confirm you can see the backup in this step — don't actually restore it.

**Clean up:**

1. Close the project (**File → Close**).
2. Go to **File → Project Management → Delete Project**, select it, choose Delete, confirm Yes, then Close.

**DANGER** Like restoring, deleting cannot be undone. Always be sure you have a backup of anything worth keeping before you delete — this is a throwaway test project, so it's safe to delete here.

**✏️ Produce this (a mentor will review it).** Write 2–3 sentences you would say to the team explaining: (a) what a vernacular vs. analysis writing system is, in plain terms, and (b) why backing up before real work begins matters, even though it feels like an extra step before "real" analysis starts.

## Change

**Self-assessment — can you explain it to a colleague?**

1. If you're documenting a language called Kiche and writing your grammatical notes in English, which is the vernacular writing system and which is the analysis writing system?
2. Where do you go in current FLEx to review or adjust vernacular and analysis writing systems after a project is created?
3. What happens if you close your last open FLEx window — does it return you to the picker dialog?

*You should be able to say:* (1) Kiche is vernacular; English is analysis. (2) Two separate commands — **Set up Vernacular Writing Systems...** and **Set up Analysis Writing Systems...** — reachable via Tools → Configure or the Format menu; there's no shared "Writing Systems" tab anymore. (3) No — closing your last window exits FLEx completely. The picker only appears when you relaunch FLEx with no windows open.

**✏️ Take it to your context.** Name one real project or team you support (or expect to). Do you know what its vernacular and analysis writing systems actually are? If not, that's worth finding out before you're asked to troubleshoot something in it.

**Next step.** In Module 2 you'll bring a real text into the project you just set up and interlinearize it — moving from the baseline text through glossing, creating new lexical entries as you go.
