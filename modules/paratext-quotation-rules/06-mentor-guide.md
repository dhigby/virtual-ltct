# Mentor Guide — Facilitator Notes

> Facilitator-facing. Read this completely before the first session. It describes how to
> create and distribute the practice projects, how to stage the `tamba` project for
> each phase of the course, and what text content is required. The learner-facing lessons are
> [`01`](01-what-the-quotation-check-does.md)–[`05`](05-scenario-bank.md); the graded
> assessment is the [quiz](07-quiz.md).

## Overview

This course teaches configuration of Paratext 9.5's Quotation check using five practice
projects (`tamba`, `runda`, `velna`, `menda`, `waku`), plus `TAMBAB`, Tamba's Phase B copy.
Learners never touch a live translation project.

The practice projects use **real languages, adapted for the course**. Each project holds real
Scripture text and keeps its real language setting in Paratext, but carries a course name, and
its quotation marks follow conventions defined by this course. The conventions tables in the
lessons are the course's conventions, not a claim about how that language community writes, so
don't present them to learners as facts about the language. Paratext's project lists show the
real language names:

| Course project | Language setting in Paratext |
|---|---|
| `TAMBA`, `TAMBAB` | Notsi (`ncf`) |
| `RUNDA` | English (`en-US`) |
| `VELNA` | Sursurunga (`sgz`) |
| `MENDA` | English (`en-US`) |
| `Waku` | Nalik (`nal`) |

 The
`tamba` project is used in two staged versions (Phase A blank, Phase B seeded) across Lessons
1–4. `runda` is a second project configured hands-on in Lesson 2, alongside `tamba`, to practice
a different character set and the apostrophe conflict. The remaining three (`velna`, `menda`,
`waku`) are configured from scratch, independently, in the [scenario bank](05-scenario-bank.md)
— `velna` deliberately reuses Runda's guillemet-and-apostrophe convention under a new name and
project, so Scenario A tests the same skill on a project the learner has not already configured.

---

## Project Setup: `tamba`

### Step 1 — Create the project in Paratext 9.5

1. In Paratext, open the main **Paratext** menu (☰ at the top-left of the application window) and click **New project...**
2. Set the following fields:

   | Field | Value |
   |-------|-------|
   | Full name | Tamba New Testament |
   | Short name | TAMBA |
   | Primary language | The real language whose text the project uses: Notsi (`ncf`) for Tamba. Don't create a private-use language code (see Overview). |
   | Versification | Original (or GNT if Original is unavailable) |
   | Project type | Standard |

3. Click **OK**. Paratext creates an empty project.
4. Leave all quotation settings at their defaults for now — learners will configure them in Lessons 2–3.

### Step 2 — Add the minimum required text

The project must contain text in at least the following passages. All other books and chapters can be empty or contain placeholder text.

| Book | Passage | Required because |
|------|---------|------------------|
| Matthew | 5:1–7:29 (the Sermon on the Mount) | Exercise 4.1 seed #1 — multi-verse speech. This is one continuous First level quotation from 5:3 to 7:28 (Jesus's teaching does not stop at the Beatitudes; the whole sermon must be built and correctly continuer-marked through chapter 7, since that is where the check will trace an unclosed quote to) |
| Matthew | 26:1–75 (arrest and trial) | Lesson 3 check verification — heavy dialogue |
| Luke | 4:14–21 | Exercise 4.1 seed #2 — Isaiah citation |
| John | 3:14–17 | Exercise 4.1 seed #3 — embedded Second level quote |
| Acts | 2:22–28 (Peter's Pentecost speech) | Exercise 4.1 seed #4 — Psalm 16 citation |
| Romans | 1:1–7 | Exercise 4.1 seed #5 — apostrophe-conflict scenario |

For all other books, inserting one or two placeholder verses is sufficient. Note that the unconfigured check in Exercise 1.1 produces no results regardless of text volume — confirmed on a real Paratext 9.5 build, it reports only *"Quotation punctuation is not used in project TAMBA"* until the Quote marks tab is configured; the lesson teaches that notice as the finding. The text volume matters from Lesson 2 onward, when the configured check runs against real dialogue — five or more books with at least a few verses each is sufficient.

### Step 3 — Apply correct Tamba quotation marks throughout

All dialogue in the required passages must use the correct Tamba quotation characters:

| Level | Opening | Quote Continuer at new paragraph | Closing |
|-------|---------|-----------------------------------|----------|
| First level (primary speech) | `“` U+201C | `“` U+201C (same as opening) | `”` U+201D |
| Second level (embedded speech) | `‘` U+2018 | *(blank — no continuer)* | `’` U+2019 |
| Third level (tertiary, rare) | `“` U+201C | *(blank — no continuer)* | `”` U+201D |

**Apostrophes:** Phase A text should contain **no contractions or possessives** — Tamba’s course conventions do not include apostrophes in Phase A, consistent with Exercise 2.3 (which uses Runda as the apostrophe-conflict example, not Tamba). For Phase B, in the text near Romans 1:1 include a word with `’` (U+2019) as an apostrophe — the same character as Tamba’s Second level closing mark. This seeds the configuration-problem scenario in Exercise 4.1, item 5.

**Paragraph-spanning speech:** Matthew 5:3–7:28 (the whole Sermon on the Mount) is the key example — not just the Beatitudes. Each verse is its own `\p` paragraph. The speech opens with `“` (U+201C) at verse 5:3. Because Tamba's First level uses a Quote Continuer, every following paragraph through 7:27 also opens with `“` (U+201C) — the same character, repeated to signal the speech continues from the previous paragraph. Only verse 7:28, the narrator's aside ("when Jesus had finished saying these things...") that ends the sermon, also carries the closing mark `”` (U+201D) at its end. This is what the learner configures in Exercise 2.1 (Quote marks tab — the continuer character) and Exercise 3.2 (Quotation types tab — Continued quotation = Use quote marks). Lesson 3's discovery exercise (Exercise 3.2, Step "Check your work") only asks the learner to confirm 5:4–5:11 aren't falsely flagged — it does not require the whole sermon to be built yet — but the full 5:3–7:28 span must exist correctly-marked in the Phase A/B baseline before Lesson 4, since Exercise 4.1 seed #1 depends on 7:28 being the true close.

**SME verification — confirmed:** a Paratext 9.5 run on a built `tamba` project confirmed the Quotation types "Continued quotation" setting does govern whether Paratext expects a configured continuer character at a paragraph break, independent of Second/Third level's close-and-reopen behavior. It also surfaced two behaviors the original design hadn't accounted for, both now reflected in the Lesson 4 Seeding table and Exercise 4.1 below: (1) an unclosed First level quotation is reported at its opening verse with wording that traces forward to wherever the next real closing mark happens to fall, rather than a fixed nearby verse; (2) a single corrupted Second level mark can cascade into multiple linked results on either side of the break rather than one isolated result. Re-verify both if the seeded text is rebuilt from scratch, since exact locations depend on the specific project build.

Do **not** configure the Quote marks tab or Rules at this stage. The project should arrive at learners with a blank quotation configuration.

### Step 4 — Stage two versions of the project

The course requires two states of the `tamba` project:

**Phase A — Lessons 1–3 (blank configuration)**
- Quote marks tab: empty
- Quotation Rules: default (unconfigured)
- Text: correct marks, no deliberate errors

Make Phase A available before learners begin Lesson 1 (Step 5). Learners configure the inventory and rules themselves during Lessons 2–3.

**Phase B — Lesson 4 (configured + seeded errors)**
- Quote marks tab: fully configured per the Tamba settings above (Exercise 2.1 values)
- Quotation types tab: recommended defaults with three customizations, confirmed against a real Paratext 9.5 build — Quotation from another source = **Quote marks are optional**, Continued quotation = **Use quote marks**, Indirect = **Never use quote marks** (the Exercise 3.2 result). Self quote already defaults to Use quote marks, which matches Tamba's requirement, so it is left unchanged.
- Text: same as Phase A, plus the five deliberate errors from the Lesson 4 Seeding table below

Phase A and Phase B are two **separate** registered projects: `TAMBA` (Phase A) and `TAMBAB` (Phase B, full name also *Tamba New Testament*). Learners can't see `TAMBAB` until you add them to it, which you do just before Exercise 4.1 (Step 5). Keep `TAMBA` at Phase A at all times: if Phase B content ever reaches `TAMBA`, every learner who receives it for Lessons 1–3 gets the answers.

### Step 5 — Make the projects available through Send/Receive

Learners get every practice project through Paratext **Send/Receive**. You, the mentor, keep the set of backup files and use them to put each project into the state the next lesson needs. Learners never need the backup files themselves.

**The backup set.** These are prebuilt and verified. Keep them somewhere only mentors can reach: the Phase B backup contains the Lesson 2–3 answers and the configured state of every seed.

| Backup file | Project | State it restores | Needed for |
|---|---|---|---|
| `Tamba New Testament 2026-10-07.zip` | `TAMBA` | **Phase A**: quotation settings blank, Quotation types check off, clean text | Lessons 1–3 |
| `TAMBAB Phase B 2026-10-09.zip` | `TAMBAB` | **Phase B**: Lesson 2–3 settings entered, Quotation types check on, the five seeds in the text | Lesson 4 |
| `Runda New Testament 2026-10-07.zip` | `RUNDA` | Blank | Lesson 2 |
| `Velna New Testament 2026-10-07.zip` | `VELNA` | Blank (Word-medial punctuation empty) | Scenario A |
| `Menda New Testament 2026-10-09.zip` | `MENDA` | Blank | Scenario B |
| `Waku New Testament 2026-10-07.zip` | `Waku` | Blank | Scenario C |

The Phase A and Phase B projects both have the full name *Tamba New Testament*, so Paratext's default backup names for them differ only by date. Keep the Phase B file named `TAMBAB Phase B …`, so it can't be mistaken for the Phase A backup. Restoring the wrong one over `TAMBA` gives every Lesson 1–3 learner the answers.

Each backup already carries the project's Paratext Registry registration (visibility: Test), so restoring one does not create a new project on the Registry.

**One-time setup, in your own Paratext**

1. Restore each project: **Paratext menu > Advanced > Restore project from file...**
2. Add the learner to each project. In the project's window, open **☰ > Project settings > User permissions...**, then:
   1. Click **Add User...** and add the learner.
   2. Click **Change Role...** next to the learner's name and choose **Administrator**.
   3. Click **Book Permissions...** and give them **all books**. A new user starts with *Editable Books: None*, even as an Administrator, so don't skip this step.
   4. Click **OK**.

   The learner needs Administrator because Lesson 3 has them tick *Enable the Quotation types check*, and Lesson 2 and the scenarios have them change Language Settings. Those are administrator-only settings.
3. Send the changes to the server: **☰ > Send/Receive this project**. This is how the learner's access and the starting state reach the server.

![The User permissions dialog for a practice project, listing two users with the Administrator role. One shows Editable Books: None and the other All books in project. Across the bottom, numbered callouts mark the order of use: 1 Add User..., 2 Book Permissions..., 3 Other Permissions... and 4 OK.](assets/ss-06-user-permissions.png)

**The learner receives a project** from the main Paratext menu (top left of the Paratext window, not a project window's ☰ menu):

1. **Paratext > Send/Receive projects...**
2. Under **Send/Receive With**, leave **Internet server** selected.
3. The list shows every project the learner has been given access to. A project they don't have on their computer yet is counted under the **New** button above the list. Tick the course project(s) they need now, and click **Send/Receive**. Paratext downloads them.

You add the learner by the user name their copy of Paratext is registered under (**Help > Registration information...**). Get that name from them before you set them up.

![The Send/Receive Projects dialog with Internet server selected under Send/Receive With. The project list shows the five course projects, MENDA, RUNDA, TAMBA, VELNA and Waku, each ticked, with its full name, last Send/Receive time and language. Above the list, filter buttons read All, None, Edited 2 and New 8.](assets/ss-06-send-receive-projects.png)

**When to make each project available**

| Before… | Project | Mentor | Learner |
|---|---|---|---|
| Lesson 1 | `TAMBA` (Phase A) | Set up as above | Receives `TAMBA` |
| Lesson 2 | `RUNDA` | Set up as above | Receives `RUNDA` |
| Lesson 4 | `TAMBAB` (Phase B) | Add the learner to `TAMBAB` (User permissions, as above), then Send/Receive `TAMBAB` | Receives `TAMBAB` and works in it, not in `TAMBA`, for Lesson 4 |
| Scenario bank | `VELNA`, `MENDA`, `Waku` | Set up as above | Receives all three |

`TAMBAB` arrives with the reference Lesson 2–3 configuration already entered, so Lesson 4 starts from a known-good setup whatever the learner entered in `TAMBA`. Review their Lesson 2–3 settings in `TAMBA` before they move on: that's where their own configuration work is.

**One learner per project at a time.** Quotation Rules and Language Settings apply to the whole project. Everyone sharing a project sees the same values, and Send/Receive carries any change to all of them. Two learners configuring the same `TAMBA` would overwrite each other's work, and two learners triaging the same `TAMBAB` would fix each other's seeds. Run one learner per copy of each project. To run several learners at the same time, each needs their own copy.

> **[Placeholder — Kevin/Jenni:** how to make a separate copy of each project per learner (for example, restoring under a different short name and registering each copy). Decide and verify this before running a group.**]**

**Resetting for the next learner.** When a learner finishes:
1. Remove them from each project's user list, including `TAMBAB`.
2. Restore the starting backups (Tamba Phase A, `TAMBAB`, Runda, Velna, Menda, Waku), so the projects are back to where a new learner begins.
3. Send/Receive each one.

**When Send/Receive isn't an option.** If a learner has no reliable internet connection, give them the matching backup file to restore on their own computer instead (**Paratext menu > Advanced > Restore project from file...**). Don't hand out the `TAMBAB` backup until they reach Lesson 4.

---

## Project Setup: `runda` (Lesson 2)

`runda` is a second practice project, alongside `tamba`, used hands-on in Lesson 2
(Exercises 2.2–2.3) to practice a different character set (guillemets) and to work through the
word-medial apostrophe conflict — including discovering, on a real Paratext 9.5 check, that
Word-medial punctuation does not actually clear the resulting flag. It must be installed before
learners start Lesson 2, not just before the scenario bank.

| Field | Value |
|-------|-------|
| Quotation style | Guillemet outer (`«` / `»`), curly single inner (`‘` / `’`) |
| Minimum books suggested | Matthew, Luke, John (dialogue-heavy) |

Include words using `’` (U+2019) as an apostrophe somewhere in the text, so Exercise 2.3's
word-medial punctuation conflict has real examples to work through — the exercise now expects
learners to confirm the check keeps flagging these even after configuring Word-medial
punctuation, not to reach zero results. Leave the Quote marks tab and
Quotation Rules blank — learners configure both during Lesson 2. The prebuilt backup is in the
mentor's backup set; make it available through Send/Receive as described in Step 5 above.

---

## Project Setup: `velna`, `menda`, and `waku` (Scenario bank)

Each scenario-bank project needs enough text to produce meaningful check results, but no deliberate errors need to be seeded — learners configure these projects from scratch, independently, having never seen them before.

| Project | Quotation style | Minimum books suggested |
|---------|----------------|-------------------------|
| `velna` | Guillemet outer (`«` / `»`), curly single inner (`‘` / `’`) — Guillemet style (Scenario A). Include words with `’` (U+2019) as apostrophes so learners encounter the apostrophe conflict. | Matthew, Luke, John (dialogue-heavy) |
| `menda` | Double guillemets outer, reversed single guillemets inner: `«...›...‹...»`; Third level returns to `«...»` — include at least one third-level quote (John 19:21) (Scenario B) | John (dialogue-heavy, contains the 19:21 third-level example) |
| `waku` | Em dash as both opener and closer: `—...—`, with em dash continuation mark; Second level `“...”`; Third level `‘...’` (Scenario C) | Matthew, Mark, Luke, John, Acts (stretch exercise; Acts is required — the scenario's check steps use its extended multi-paragraph speeches; Mark 13 carries most of the parenthetical em dashes and John 17 the same-character ambiguity that the scenario's seven residual results depend on) |

Apply the correct quotation characters for each language throughout the text. Leave all Quote marks tab and Rules settings blank — learners configure them as part of the scenario.

Prebuilt backups of all three scenario-bank projects are in the mentor's backup set and are made
available through Send/Receive (see Step 5 under the `tamba` setup). The tables above are reference
for anyone rebuilding a project from scratch, not a required setup step. If you do rebuild one: blank the Quote marks tab and Quotation Rules
settings used during verification before backing up — learners must start from empty fields.
`velna` is new as of this revision (it replaces `runda`'s prior role in Scenario A, so the two
must be distinct projects — do not reuse the `runda` backup for `velna`).

---

## Lesson 4 Seeding

The five underlying issues in Exercise 4.1 must be manually introduced into the Phase B `tamba` project text. Seed each error as follows. Note that seed #3 produces **three** separate check results (at 3:10, 3:16, and 3:21) from one broken mark — confirmed in a Paratext 9.5 run — so the project will show **seven** total results from these five seeds, not five.

| # | Location | What to do in the text |
|---|----------|------------------------|
| 1 | Matthew 5:3–7:28 | Delete the closing `”` (U+201D) at the end of verse 7:28 only — leave the First level continuer `“` (U+201C) in place at the head of every paragraph from 5:4 through 7:27. The speech runs from 5:3 through 7:28 (the whole Sermon on the Mount), linked paragraph to paragraph by the continuer; removing only the final closing mark creates an unclosed-quote result reported back at Matthew 5:3. In practice the check reports this as "Closing quote mark is possibly missing before verse [X]," where `[X]` is wherever the next real closing mark in the project happens to fall — confirmed in a Paratext 9.5 run to trace all the way to 7:28. |
| 2 | Luke 4:18 | Insert a stray `“` (U+201C) immediately before the first word of the Isaiah citation. Tamba does not mark narrator scripture citations; the stray mark mimics a translator adding a dialogue opener by mistake. |
| 3 | John 3:16 | Replace the Second level opening mark `‘` (U+2018) with a straight `"` (U+0022) at the start of Jesus's embedded statement within his speech to Nicodemus. This one corrupted character breaks quotation tracking on both sides of it: confirmed in a Paratext 9.5 run to produce three linked results — "Quote opened; see following message for error" at 3:10 (the quotation's true start), "Expected continuers [“] are missing OR quote not closed; see preceding message" at 3:16 (the corrupted mark itself), and "Closing quote mark [”] found without matching opening" at 3:21 (the orphaned close). Fixing the single character at 3:16 and re-running clears all three. |
| 4 | Acts 2:25–28 | Mark Peter's Psalm 16 citation as a single continuous Second level block: add `‘` (U+2018) at the start of verse 2:25 and `’` (U+2019) at the end of verse 2:28. Do **not** close and reopen at the intermediate paragraph breaks (make sure the text has at least one \p break inside 2:25–28 — the seed depends on it). Tamba restarts marks at every paragraph, so the check reports the span as unclosed; learners identify it as a real error and add the close/reopen pairs. |
| 5 | Romans 1:1 | In a possessive or contraction in the verse text (e.g., *God’s word*), use `’` (U+2019) as the apostrophe. Phase A text has no apostrophes; this one creates the conflict between the Second level closing mark (U+2019) and a word-medial apostrophe, generating a spurious quotation result. Confirmed on a real Paratext 9.5 build: this result is permanent — adding `’` to Word-medial punctuation does not clear it. Do not seed this expecting learners to reach zero results in Romans; Exercise 4.2's completion criteria account for this one exception. |

After seeding, run the Quotation check and confirm all seven results (five seeds, with #3 producing three) appear before distributing the project to learners. The Exercise 4.1 table already reflects verified Paratext 9.5 message wording for this build; if you rebuild the project from scratch and the wording or locations differ, update that table to match before distributing the course.

---

## General Facilitation Notes

- **Discovery-first ordering:** Each lesson shows the answer key *after* the discovery prompts, not before. Encourage learners to write down their prediction before scrolling to the expected configuration.
- **Scenario C (Waku):** The em-dash scenario is the hardest. It is appropriate as a stretch exercise or for learners who have completed Lessons 1–4 confidently and want a challenge.
