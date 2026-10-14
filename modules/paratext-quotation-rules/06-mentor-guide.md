# Mentor Guide — Facilitator Notes

> Facilitator-facing. Read this completely before the first session. It describes how to
> create and distribute the practice projects, how to stage the Tamba project (`TAMBA`, then
> `TAMBAB`) for each phase of the course, and what text content is required. The learner-facing lessons are
> [`01`](01-what-the-quotation-check-does.md)–[`05`](05-scenario-bank.md); the graded
> assessment is the [quiz](07-quiz.md).

## Overview

This course teaches configuration of Paratext 9.5's Quotation check using five practice
projects (`TAMBA`, `RUNDA`, `VELNA`, `MENDA`, `Waku`), plus `TAMBAB`, Tamba's Phase B copy.
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

**Starting state: Paratext's defaults, not blank.** `TAMBA`, `RUNDA`, `VELNA`, `MENDA` and
`Waku` all start with Paratext 9.5's default Quotation Rules, which is what a brand-new project
gets (verified: a project created in Paratext 9.5.110 saves these defaults on creation):

| Level | Opening | Quote Continuer at new paragraph | Closing |
|-------|---------|----------------------------------|---------|
| First level | `“` | `“` | `”` |
| Second level | `‘` | `‘` | `’` |
| Third level | `“` | `“` | `”` |

Continuer required at: `p p/q1 b/q1`. Quotation types tab: Use recommended settings, check off.
These defaults are Paratext's, not the translation team's choice, and that is the teaching
point: learners correct the defaults to each language's conventions rather than filling in an
empty grid, and learn that a clean result at the defaults proves nothing. `TAMBAB` is the
exception: it holds Tamba's configured settings and the five seeds.

The Tamba project is used in two staged versions (Phase A at the defaults as `TAMBA`, Phase B
configured and seeded as `TAMBAB`) across Lessons 1–4. `RUNDA` is a second project corrected
hands-on in Lesson 2, alongside `TAMBA`, to practice a different character set and the
apostrophe conflict. The remaining three (`VELNA`, `MENDA`, `Waku`) are corrected from the
defaults, independently, in the [scenario bank](05-scenario-bank.md) — `VELNA` deliberately reuses Runda's guillemet-and-apostrophe
convention under a new name and project, so Scenario A tests the same skill on a project the
learner has not already configured.

---

## Project Setup: `TAMBA`

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
4. Leave all quotation settings at Paratext's defaults (see Overview) — learners correct them in Lessons 2–3.

### Step 2 — Add the minimum required text

The project must contain text in at least the following passages. All other books and chapters can be empty or contain placeholder text.

| Book | Passage | Required because |
|------|---------|------------------|
| Matthew | 5:1–7:29 (the Sermon on the Mount) | Exercise 4.1 seed #1 — multi-paragraph speech. Jesus's First level speech opens with `“` at 5:3, closes with `”` in 5:12, reopens with `“` at the start of 5:13 and closes again with `”` in 7:27; 7:28 (narration) has no quote marks. The whole sermon must be built and correctly continuer-marked through chapter 7, since the check traces the seeded unclosed quote to 7:28 |
| Matthew | 26:1–75 (arrest and trial) | Lesson 3 check verification — heavy dialogue |
| Luke | 4:14–21 | Exercise 4.1 seed #2 — Isaiah citation |
| John | 3:10–21 | Exercise 4.1 seed #3 — First level continuer at the start of 3:16 (its three linked results fall at 3:10, 3:16 and 3:21) |
| John | 17 | Exercise 1.1 — at the defaults the check reports five results here (17:1, 17:6, 17:13, 17:20, 17:24), which clear once `pi/pi` is in the Continuer required at list (Exercise 2.1) |
| Acts | 2:22–28 (Peter's Pentecost speech) | Exercise 4.1 seed #4 — Psalm 16 citation |
| Romans | 1:1–7 | Exercise 4.1 seed #5 — apostrophe-conflict scenario |

For all other books, inserting one or two placeholder verses is sufficient. At Paratext's defaults, Exercise 1.1 (all books) gives exactly five results, all in John 17, each "Opening quote mark found without matching closing quote mark: “"; the lesson teaches that these come from the defaults, not from errors in the text. The default Second and Third level continuers produce no results in Tamba's text. Five or more books with at least a few verses each is sufficient.

### Step 3 — Apply correct Tamba quotation marks throughout

All dialogue in the required passages must use the correct Tamba quotation characters:

| Level | Opening | Quote Continuer at new paragraph | Closing |
|-------|---------|-----------------------------------|----------|
| First level (primary speech) | `“` U+201C | `“` U+201C (same as opening) | `”` U+201D |
| Second level (embedded speech) | `‘` U+2018 | *none — no continuer* | `’` U+2019 |
| Third level (tertiary, rare) | `“` U+201C | *none — no continuer* | `”` U+201D |

**Apostrophes:** Phase A text should contain **no contractions or possessives** — Tamba’s course conventions do not include apostrophes in Phase A, consistent with Exercise 2.3 (which uses Runda as the apostrophe-conflict example, not Tamba). For Phase B, in the text near Romans 1:1 include a word with `’` (U+2019) as an apostrophe — the same character as Tamba’s Second level closing mark. This seeds Exercise 4.1, item 5, which Lesson 4 classifies as **Neither** — a quote-mark/apostrophe collision that neither a text fix nor a configuration change clears.

**Paragraph-spanning speech:** Matthew 5:3–7:27 (the whole Sermon on the Mount) is the key example — not just the Beatitudes. Each verse is its own `\p` paragraph. Jesus's speech opens with `“` (U+201C) at verse 5:3. Because Tamba's First level uses a Quote Continuer, each following paragraph also opens with `“` (U+201C) — the same character, repeated to signal the speech continues from the previous paragraph. The speech closes with `”` (U+201D) in 5:12 and reopens with `“` at the start of 5:13, then closes again with `”` in 7:27. Verse 7:28, the narrator's aside that ends the sermon, carries no quote marks. This is what the learner configures in Exercise 2.1 (Quote marks tab — the continuer character) and Exercise 3.2 (Quotation types tab — Continued quotation = Use quote marks). Lesson 3's Exercise 3.2 ("Check your work") asks the learner to confirm Matthew 5:4–7:27 is not falsely flagged, so the whole span must exist correctly marked in the Phase A baseline from Lesson 3 on. Exercise 4.1 seed #1 also depends on both closing marks (in 5:12 and in 7:27) being present in Phase A, since Phase B deletes both.

**SME verification — confirmed:** a Paratext 9.5 run on a built `TAMBA` project confirmed the Quotation types "Continued quotation" setting does govern whether Paratext expects a configured continuer character at a paragraph break, independent of the Second and Third level settings (which have no continuer in Tamba). It also surfaced two behaviors the original design hadn't accounted for, both now reflected in the Lesson 4 Seeding table and Exercise 4.1 below: (1) an unclosed First level quotation is reported at its opening verse with wording that traces forward to wherever the next real closing mark happens to fall, rather than a fixed nearby verse; (2) a single corrupted mark — in seed #3, the First level continuer at a paragraph break — can cascade into multiple linked results on either side of the break rather than one isolated result. Re-verify both if the seeded text is rebuilt from scratch, since exact locations depend on the specific project build.

Do **not** enter Tamba's conventions in Quotation Rules at this stage. The project should arrive at learners with Paratext's defaults, exactly as a new project saves them.

### Step 4 — Stage two versions of the project

The course requires two states of the Tamba project:

**Phase A — Lessons 1–3 (Paratext defaults)**
- Quote marks tab: Paratext's defaults (`“ “ ”` / `‘ ‘ ’` / `“ “ ”`, Continuer required at `p p/q1 b/q1`)
- Quotation types tab: Use recommended settings, check off
- Text: correct marks, no deliberate errors

Make Phase A available before learners begin Lesson 1 (Step 5). Learners correct the defaults themselves during Lessons 2–3.

**Phase B — Lesson 4 (configured + seeded errors)**
- Quote marks tab: fully configured per the Tamba settings above (Exercise 2.1 values)
- Quotation types tab: recommended defaults with three customizations, confirmed against a real Paratext 9.5 build — Quotation from another source = **Quote marks are optional**, Continued quotation = **Use quote marks**, Indirect = **Never use quote marks** (the Exercise 3.2 result). Self quote already defaults to Use quote marks, which matches Tamba's requirement, so it is left unchanged.
- Text: same as Phase A, plus the five deliberate errors from the Lesson 4 Seeding table below

Phase A and Phase B are two **separate** registered projects: `TAMBA` (Phase A) and `TAMBAB` (Phase B, full name also *Tamba New Testament*). Learners can't see `TAMBAB` until you add them to it, which you do just before Exercise 4.1 (Step 5). Keep `TAMBA` at Phase A at all times: if Phase B content ever reaches `TAMBA`, every learner who receives it for Lessons 1–3 gets the answers.

### Step 5 — Make the projects available through Send/Receive

Learners use Paratext **Send/Receive** only to get the projects. Each learner receives each project once, then works in their own copy on their own computer and never sends. Because nobody sends, the server copy stays at its starting state: any number of learners can work at the same time without overwriting each other, and there is nothing to reset between learners.

You, the mentor, keep the set of backup files and use them once, to set the projects up on the server. Learners only need a backup file if they have no internet connection (see **When Send/Receive isn't an option** below).

**The backup set.** These are prebuilt and verified. Keep them somewhere only mentors can reach: the Phase B backup contains the Lesson 2–3 answers and the configured state of every seed. Keep them **outside the course repository** too — never in the course folder, because everything in a course folder is published on the review sites.

> **[Placeholder — Kevin/Jenni:** where mentors get the backup set (location and who grants access).**]**

"Paratext defaults" below means the default Quotation Rules in the Overview table: `“ “ ”` / `‘ ‘ ’` / `“ “ ”`, Continuer required at `p p/q1 b/q1`, Quotation types on Use recommended settings with the check off.

| Backup file | Project | State it restores | Needed for |
|---|---|---|---|
| `Tamba New Testament 2026-10-12.zip` | `TAMBA` | **Phase A**: Paratext defaults, clean text | Lessons 1–3 |
| `TAMBAB Phase B 2026-10-09.zip` | `TAMBAB` | **Phase B**: Lesson 2–3 settings entered, Quotation types check on, the five seeds in the text | Lesson 4 |
| `Runda New Testament 2026-10-13.zip` | `RUNDA` | Paratext defaults | Lesson 2 |
| `Velna New Testament 2026-10-12.zip` | `VELNA` | Paratext defaults (Word-medial punctuation empty) | Scenario A |
| `Menda New Testament 2026-10-12.zip` | `MENDA` | Paratext defaults | Scenario B |
| `Waku New Testament 2026-10-12.zip` | `Waku` | Paratext defaults | Scenario C |

The Phase A and Phase B projects both have the full name *Tamba New Testament*, so Paratext's default backup names for them differ only by date. Keep the Phase B file named `TAMBAB Phase B …`, so it can't be mistaken for the Phase A backup. Restoring the wrong one over `TAMBA` gives every Lesson 1–3 learner the answers.

Each backup already carries the project's Paratext Registry registration (visibility: Test), so restoring one does not create a new project on the Registry.

**One-time setup, in your own Paratext**

1. Restore each project: **Paratext menu > Advanced > Restore project from file...**
2. Send it to the server: **☰ > Send/Receive this project**. This is how the starting state reaches the server.
3. Mark the starting state in its project history (see **Mark the starting state** below).

If you set a project's starting state by hand instead of restoring the backup, note that **Copy quote mark settings...** (bottom left of Quotation Rules) copies the quote marks from another project but **not** the **Continuer required at** list. Type the list yourself: `p p/q1 b/q1` for the Paratext defaults.

**For each learner, in your own Paratext**

1. Add the learner to each project they need now. In the project's window, open **☰ > Project settings > User permissions...**, then:
   1. Click **Add User...** and add the learner.
   2. Click **Change Role...** next to the learner's name and choose **Administrator**.
   3. Click **Book Permissions...** and give them **all books**. A new user starts with *Editable Books: None*, even as an Administrator, so don't skip this step. Do it before the learner receives the project.
   4. Click **OK**.

   The learner needs Administrator because Lesson 3 has them tick *Enable the Quotation types check*, and Lesson 2 and the scenarios have them change Language Settings. Those are administrator-only settings.
2. Send the changes to the server: **☰ > Send/Receive this project**. This is how the learner's access reaches the server.

![The User permissions dialog for a practice project, listing two users with the Administrator role. One shows Editable Books: None and the other All books in project. Across the bottom, numbered callouts mark the order of use: 1 Add User..., 2 Book Permissions..., 3 Other Permissions... and 4 OK.](assets/ss-06-user-permissions.png)

**The learner receives a project** from the main Paratext menu (top left of the Paratext window, not a project window's ☰ menu):

1. **Paratext > Send/Receive projects...**
2. Under **Send/Receive With**, leave **Internet server** selected.
3. The list shows every project the learner has been given access to. A project they don't have on their computer yet is counted under the **New** button above the list. Tick the course project(s) they need now, and click **Send/Receive**. Paratext downloads them.

> **WARNING:** Tell the learner: receive each project once. After that, never Send/Receive it, because their changes would go to every other learner. They save their work instead. The check runs on their own copy, so their work counts without sending it. If Paratext offers to Send/Receive (for example, after a permission change), they cancel it. In testing, when Book Permissions were granted, Paratext offered a Send/Receive; cancelling it was fine and the permission still worked locally.

You add the learner by the user name their copy of Paratext is registered under (**Help > Registration information...**). Get that name from them before you set them up.

![The Send/Receive Projects dialog with Internet server selected under Send/Receive With. The project list shows the five course projects, MENDA, RUNDA, TAMBA, VELNA and Waku, each ticked, with its full name, last Send/Receive time and language. Above the list, filter buttons read All, None, Edited 2 and New 8.](assets/ss-06-send-receive-projects.png)

**When to make each project available**

| Before… | Project | Mentor | Learner |
|---|---|---|---|
| Lesson 1 | `TAMBA` (Phase A) | Add the learner (as above), then Send/Receive `TAMBA` | Receives `TAMBA` once |
| Lesson 2 | `RUNDA` | Add the learner (as above), then Send/Receive `RUNDA` | Receives `RUNDA` once |
| Lesson 4 | `TAMBAB` (Phase B) | Add the learner to `TAMBAB` (User permissions, as above), then Send/Receive `TAMBAB` | Receives `TAMBAB` once, and works in it, not in `TAMBA`, for Lesson 4 |
| Scenario bank | `VELNA`, `MENDA`, `Waku` | Add the learner (as above), then Send/Receive each | Receives all three once |

`TAMBAB` arrives with the reference Lesson 2–3 configuration already entered, so Lesson 4 starts from a known-good setup whatever the learner entered in `TAMBA`.

**Reviewing Lesson 2–3 work.** Review the learner's Lesson 2–3 settings before they move on to Lesson 4: that's where their own configuration work is. Learners don't send, so you can't see their settings through Send/Receive. Instead, with each lesson's **✏️ Produce this** task they submit screenshots: the **Quote marks** tab for `TAMBA` and `RUNDA` (Lesson 2), and the **Quotation types** tab for `TAMBA` (Lesson 3). Check those against the README convention tables and the Exercise 3.2 settings table.

For `TAMBA`, a clean check is not enough evidence. At the defaults the check gives five results, all in John 17 (Lesson 1); after Exercise 2.1 it gives "No errors found". But adding only `pi/pi` to the list also clears all five, and the default Second and Third level continuers produce no results in Tamba's text. So look at the screenshot itself: Second and Third level continuers at *none*, and the list reading `p p/q1 m/q1 s1/pi pi/pi b/q1`. A learner who stopped when the result list emptied has missed the lesson's point.

**Mark the starting state.** Quotation Rules and Language Settings apply to the whole project, and Send/Receive carries any change to everyone who receives afterwards. If a learner sends by mistake, the marked point is how you put the server copy's text back; the settings you reset by hand (see below). You can't restore a backup over the project instead: Paratext refuses to load it (*"Waku - Waku New Testament has the same ID as Waku - Waku New Testament and was not loaded."*), and Send/Receive would bring the learner's changes back from the server anyway. So mark the starting state once, as the point to go back to.

**Before the first learner starts**, once each project is set up, do this on all six projects (`TAMBA`, `TAMBAB`, `RUNDA`, `VELNA`, `MENDA`, `Waku`):

1. In the project window, open **☰ > Project > Mark point in history...**
2. Type the comment `## Course starting state (defaults)` and click **OK**. Use the same comment on all six, `TAMBAB` included, so every project's reset point is found the same way.
3. Send/Receive the project.

> **TIP:** Paratext adds many history entries of its own. Starting your comment with `##` makes your marked point easy to spot in the list later.

![The Project section of a project window's ☰ menu, with Mark point in history... highlighted between Send/Receive this project and Export draft PDF (PTXprint)....](assets/ss-06-mark-point-in-history.png)

![The Mark Point in Project History dialog for TAMBA, with "## Course starting state (defaults)" typed in the Comment box. The help text below reads that all changes will be saved and the current state recorded, so the project can later be compared or restored to exactly how it is now. OK and Cancel are at the bottom.](assets/ss-06-mark-point-comment.png)

**If a learner sends by mistake.** This is a recovery step, not routine: the server copy only leaves its starting state if someone sends. If a learner does Send/Receive a project after working in it, you or the learner put it back to its starting state. In that project:

> **NOTE:** Reverting puts back the text only. Paratext's help describes no way to roll back settings, so step 2 is not optional.

1. **Put back the text.** Open **☰ > Project > Project history...**, click your `## Course starting state (defaults)` entry (if there's more than one, use the newest), then click **Revert Books...**. In **Select which books to restore**, click **All Books**, then **OK**. This undoes the learner's text edits (Lesson 4 fixes, scenario edits).
2. **Set the Quotation Rules back by hand.** Open **☰ > Project settings > Quotation Rules** and set it to that project's starting state:
   - **`TAMBA`, `RUNDA`, `VELNA`, `MENDA`, `Waku`:** the Paratext defaults (see the table in the Overview). Quote marks `“ “ ”` / `‘ ‘ ’` / `“ “ ”`, Continuer required at `p p/q1 b/q1`. Quotation types tab: **Use recommended settings**, with the check box off. You can use **Copy quote mark settings...** from another project still at the defaults, but it doesn't copy the Continuer required at list, so type that by hand.
   - **`TAMBAB`:** Tamba's configured settings (the README's Tamba table and the Phase B description in Step 4). Quote marks `“ “ ”` / `‘` *none* `’` / `“` *none* `”`, Continuer required at `p p/q1 m/q1 s1/pi pi/pi b/q1`. Quotation types tab: **Custom settings**, check box on, with the Exercise 3.2 settings.
3. **Check Language Settings where the course has learners change them.** Open **☰ > Project settings > Language Settings > Other Characters** and make sure **Word-medial punctuation** is back to its starting state (empty) on the projects where learners add `’`: `RUNDA` (Exercise 2.3), `VELNA` (Scenario A) and `TAMBAB` (Exercise 4.1, item 5).
4. Click **OK**, then Send/Receive the project.

> **WARNING:** Reverting has costs. It also wipes the learner's own work since the marked point, so they redo it. And anyone who received the project between the send and the revert keeps the leaked settings.

> **[Placeholder — Kevin/Jenni:** what a learner who received the leaked settings should do (for example, whether they delete their copy and receive again after the revert). Decide and verify this.**]**

Reverting doesn't remove users. That doesn't matter here, because the learner keeps working; if you also want to take them off the project, remove them yourself: **☰ > Project settings > User permissions...**, then **Remove** next to their name.

![The Project section of the ☰ menu with Project history... highlighted, among Project health report..., Manage books, Delete project..., Recent changes... and Mark point in history....](assets/ss-06-project-history-menu.png)

![The Project History window for TAMBA, titled Project History (13 Versions). The toolbar holds the All versions and All books filters, a Search project history box and, at its right end, the Revert Books... button. The selected entry, outlined, is dated 2026-10-12 by Kevin Nicholas and reads "## Course starting state (defaults)". Above it is a later 2026-10-13 entry by Kevin Nicholas; below it are older entries, including Jenni Beadle's 2026-10-10 "## Course starting state. 2026".](assets/ss-06-project-history-revert-books-tool.png)

![The Select which books to restore dialog. Buttons on the left read All Books, Old Testament, New Testament, Deuterocanon, Extra material and Deselect All; the list on the right shows Matthew, Luke, John, Acts and Romans.](assets/ss-06-project-history-revert-books-select-books.png)

**When Send/Receive isn't an option.** If a learner has no reliable internet connection, give them the matching backup file to restore on their own computer instead (**Paratext menu > Advanced > Restore project from file...**). Don't hand out the `TAMBAB` backup until they reach Lesson 4.

---

## Project Setup: `RUNDA` (Lesson 2)

`RUNDA` is a second practice project, alongside `TAMBA`, used hands-on in Lesson 2
(Exercises 2.2–2.3) to practice correcting the defaults to a different character set (guillemets) and to work through the
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
punctuation, not to reach zero results. Leave Quotation Rules at Paratext's defaults — learners
correct them during Lesson 2. The prebuilt backup is in the mentor's backup set; make it
available through Send/Receive as described in Step 5 above.

**Expected results (Quotations check):**

| State | All books | Matthew | What the learner should notice |
|---|---|---|---|
| Paratext defaults (start of Exercise 2.2) | 1110 | — | Mostly "Closing quote [ ’ U+2019 ] found as a word medial character…", plus "Second-level quote mark found without a preceding first-level quote: ‘" (e.g. Matthew 2:6, 4:4, 4:6) and "Closing quote [ ’ U+2019 ] found without matching opening…" (Matthew 5:10). Guillemets aren't in the defaults. |
| Exercise 2.2 settings (First level `« « »`, Second level `‘` *none* `’`, Third level *none*, list `p`) | 847 | 252 | The Second-level-without-first results are gone. Almost all that remains is the word-medial `’` (Exercise 2.3's subject), plus the 5:3 "Opening quote mark found without matching closing quote mark: «". |

The point to listen for when you review: 1110 → 847 looks like little progress, but almost all of
the 847 are one apostrophe clash. The learner should be reading the messages, not the count.

---

## Project Setup: `VELNA`, `MENDA`, and `Waku` (Scenario bank)

Each scenario-bank project needs enough text to produce meaningful check results, but no deliberate errors need to be seeded — learners correct these projects from Paratext's defaults, independently, having never seen them before.

| Project | Quotation style | Minimum books suggested |
|---------|----------------|-------------------------|
| `VELNA` | Guillemet outer (`«` / `»`), curly single inner (`‘` / `’`) — Guillemet style (Scenario A). Include words with `’` (U+2019) as apostrophes so learners encounter the apostrophe conflict. | Matthew, Luke, John (dialogue-heavy) |
| `MENDA` | Double guillemets outer, reversed single guillemets inner: `«...›...‹...»`; Third level returns to `«...»` — include at least one third-level quote (John 19:21) (Scenario B) | John (dialogue-heavy, contains the 19:21 third-level example) |
| `Waku` | Em dash as both opener and closer: `—...—`, with em dash continuation mark; Second level `“...”`; Third level `‘...’` (Scenario C) | Matthew, Mark, Luke, John, Acts (stretch exercise; Acts is required — the scenario's check steps use its extended multi-paragraph speeches; Mark 13 carries most of the parenthetical em dashes and John 17 the same-character ambiguity that the scenario's seven residual results depend on) |

Apply the correct quotation characters for each language throughout the text. Leave Quotation Rules at Paratext's defaults — learners correct them as part of the scenario.

**Expected results at the defaults, and after correction (Quotations check, all books):**

| Project | At Paratext's defaults | After the scenario's configuration | Note for review |
|---|---|---|---|
| `VELNA` | 264 — mostly "Second-level quote mark found without a preceding first-level quote: ‘", plus word-medial `’` (e.g. Matthew 1:1) | 3: word-medial `’` at Matthew 1:1, Luke 1:1 and John 1:1 | The `’` apostrophe results remain after correction (expected; see Scenario A). 264 → 3. |
| `MENDA` | "No errors found." | "No errors found." | At the defaults this is a false clean result. Menda's text does use guillemets (confirmed in the Punctuation inventory), but none of them are in the defaults, so the check recognizes no quote marks at all. Ask the learner why that isn't good news. Because the configured run gives the same message, the result alone can't tell you the learner configured Menda: look at their Quote marks tab. |
| `Waku` | "No errors found." | 7 (Scenario C's residuals, including John 17:26 "Closing quote mark is possibly missing before verse 18.1: —") | If the learner changed only the marks and left the Second and Third level continuers filled in (as the defaults have them) and the list `p p/q1 b/q1`, they get an **8th** result: Matthew 13:14 "Second-level quote mark found without a preceding first-level quote: “". Eight instead of seven means the defaults weren't fully corrected. |

**Reviewing Scenario B (`MENDA`).** Menda holds only John, so a run on John and a run on all
books are the same run. Verified in Paratext 9.5:

- **Full configuration.** First level `«` *none* `»`, Second level `›` *none* `‹`, Third level
  `«` *none* `»`, with the Quotation types check enabled at Use recommended settings: "No errors
  found." The result is the same whether the Continuer required at list is `p p/q1 b/q1` or
  empty: once all three continuers are *none*, clearing the list changes nothing in the check.
  The scenario still has learners clear it, because Menda's conventions have no continuers and
  the course teaches setting what the conventions say. Check the list on their Quote marks tab.
- **The John 19:21 discovery prompt.** Menda's Third level uses `«` and `»`, the same characters
  as First level. With First and Second level correct but Third level left at the defaults
  (`“ “ ”`), the check gives exactly **4 results, all at John 19:21**, and the same 4 whether the
  Third level continuer is `“` or *none*:
  - "Opening quote mark found without matching closing quote mark: «"
  - "Opening quote mark found without matching closing quote mark: ›"
  - "Closing quote [ ‹ U+2039 ] found without matching opening. Caused by a missing opening quote, a missing continuer, or incorrectly defined continuer rules: ‹"
  - "Closing quote [ » U+00BB ] found without matching opening. Caused by a missing opening quote, a missing continuer, or incorrectly defined continuer rules: »"

  The diagnostic to listen for: results only at 19:21 mean Third level wasn't changed; results
  throughout John point to First or Second level.
- **Second level reversed** (`‹` in Opening, `›` in Closing): **125 results** throughout John,
  in pairs per verse: "Closing quote [ › U+203A ] found without matching opening. Caused by a
  missing opening quote, a missing continuer, or incorrectly defined continuer rules: ›" and
  "Opening quote mark found without matching closing quote mark: ‹".
- **`«` typed instead of `»`** in the First and Third level Closing cells: **224 results** in
  John, nearly all naming `«` ("Closing quote mark is possibly missing in this verse: «",
  "Opening quote mark found without matching closing quote mark: «"). The Example box on the
  Quote marks tab gives it away: it shows `«` where the closing mark should be (for example
  "upstairs.«"). Send the learner to the Example box.
- **OK is disabled, with a red ! beside Continuer required at.** The tooltip reads "Cannot be
  blank if any Quote Continuer is specified." The learner cleared the list while a continuer was
  still set. Set all three continuers to *none* first, then clear the list.

Prebuilt backups of all three scenario-bank projects are in the mentor's backup set and are made
available through Send/Receive (see Step 5 under the `TAMBA` setup). The tables above are reference
for anyone rebuilding a project from scratch, not a required setup step. If you do rebuild one:
put Quotation Rules back to Paratext's defaults (see Overview) before backing up — or create
the project fresh in Paratext 9.5, which saves the defaults on creation. Learners must start
from the defaults, not from the verified configuration.
`VELNA` is new as of this revision (it replaces `RUNDA`'s prior role in Scenario A, so the two
must be distinct projects — do not reuse the `RUNDA` backup for `VELNA`).

---

## Lesson 4 Seeding

The five underlying issues in Exercise 4.1 must be manually introduced into the Phase B `TAMBAB` project text. Seed each error as follows. Note that seed #3 produces **three** separate check results (at 3:10, 3:16, and 3:21) from one broken mark — confirmed in a Paratext 9.5 run — so the project will show **seven** total results from these five seeds, not five.

| # | Location | What to do in the text |
|---|----------|------------------------|
| 1 | Matthew 5:12, 7:27 | Delete the closing `”` (U+201D) in 5:12 and the closing `”` (U+201D) in 7:27 — leave every opening `“` (U+201C) in place, including the continuers and the `“` that reopens the speech at the start of 5:13. With both closing marks gone, Jesus's speech that opens at 5:3 is never closed, which creates an unclosed-quote result reported back at Matthew 5:3. In practice the check reports this as "Closing quote mark is possibly missing before verse [X]," where `[X]` is wherever the next real closing mark in the project happens to fall — confirmed in a Paratext 9.5 run to trace all the way to 7:28. |
| 2 | Luke 4:18 | Insert a stray `“` (U+201C) immediately before the first word of the Isaiah citation. Tamba does not mark narrator scripture citations; the stray mark mimics a translator adding a dialogue opener by mistake. |
| 3 | John 3:16 | Verse 3:16 begins a new `\p` paragraph inside Jesus's speech to Nicodemus, so in Phase A it opens with the First level Quote Continuer `“` (U+201C). Replace that `“` at the start of 3:16 with a straight `"` (U+0022). This one corrupted character breaks quotation tracking on both sides of it: confirmed in a Paratext 9.5 run to produce three linked results — "Quote opened; see following message for error" at 3:10 (the quotation's true start), "Expected continuers [“] are missing OR quote not closed; see preceding message" at 3:16 (the corrupted mark itself), and "Closing quote [” U+201D] found without matching opening" at 3:21 (the orphaned close). Replacing the `"` at the start of 3:16 with `“` (U+201C) and re-running clears all three (verified in Paratext 9.5; replacing it with `‘` is not the fix). |
| 4 | Acts 2:28 | Delete the closing `’` (U+2019) in 2:28 that ends Peter's Psalm 16 citation; leave the opening `‘` (U+2018) in 2:25 in place. In Phase A the citation is one Second level span across several paragraph breaks, with no close/reopen at the breaks, and it passes the check clean — Tamba's Second level has no continuer, so the span is valid. Removing only the close leaves the citation unclosed, reported at Acts 2:25; learners identify it as a real error and restore the `’` in 2:28. |
| 5 | Romans 1:1 | In a possessive or contraction in the verse text (e.g., *God’s word*), use `’` (U+2019) as the apostrophe. Phase A text has no apostrophes; this one creates the conflict between the Second level closing mark (U+2019) and a word-medial apostrophe, generating a spurious quotation result. Confirmed on a real Paratext 9.5 build: this result is permanent — adding `’` to Word-medial punctuation does not clear it. Do not seed this expecting learners to reach zero results in Romans; Exercise 4.2's completion criteria account for this one exception. |

After seeding, run the Quotation check and confirm all seven results (five seeds, with #3 producing three) appear before distributing the project to learners. The Exercise 4.1 table already reflects verified Paratext 9.5 message wording for this build; if you rebuild the project from scratch and the wording or locations differ, update that table to match before distributing the course.

---

## General Facilitation Notes

- **Discovery-first ordering:** Each lesson shows the answer key *after* the discovery prompts, not before. Encourage learners to write down their prediction before scrolling to the expected configuration.
- **Scenario C (Waku):** The em-dash scenario is the hardest. It is appropriate as a stretch exercise or for learners who have completed Lessons 1–4 confidently and want a challenge.
