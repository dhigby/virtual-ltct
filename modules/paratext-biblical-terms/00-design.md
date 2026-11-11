# Course Design Document

## Course overview

| Item | Description |
| --- | --- |
| **Title** | Using the Biblical Terms Tool in Paratext 9 |
| **Competencies addressed** | Translation Tools |
| **Target outcome level** | 3 - Independent |
| **SME(s) consulted** | Doug Higby, SME and author of the source deck *Managing Biblical Terms in Paratext 9*, which he taught to 40 people on 2026-11-11. His answers to the stage-1 interview were relayed on 2026-11-12 and are recorded under "SME knowledge notes". The deck is built on John Nystrom's (SIL-PNG) *A practical 4-step process for Biblical Terms*, and the 4-step method, every screenshot and the worked example are his (see "Rights and credit"). |
| **Design status** | Draft |
<!-- On approval (stage 2), the Design Approver replaces the line above with:
     | **Design status** | Approved by <name> on <YYYY-MM-DD> | -->

### Who this course is for

"This course is for Language Technology Consultants — those who train Paratext users." (SME)
The source deck is what the LTC learns to **teach**. This course adds the consultant layer on
top of it: setting the tool up and protecting it, diagnosing what a team's Biblical Terms state
really shows, recovering from the common accidents, and preparing and leading a session with a
real team.

### Rights and credit

- The SME states: **"I have permission from John to use his slides and create this course."**
  That covers John Nystrom's method, screenshots and worked example as used in the deck.
- Every lesson that uses a Nystrom screenshot or the ἀδελφός example credits him. The deck
  captions each screenshot "Screenshots: John Nystrom, earlier Paratext version", and the
  lessons should use the same wording.
- See open item O6 on whether that permission extends to *public* publication. This repo, its
  review sites and Moodle are all public-facing.

### Source deck

*Managing Biblical Terms in Paratext 9*, Doug Higby, built on John Nystrom's 4-step method
(SIL-PNG). It has 45 slides with speaker notes and is designed as a 90-minute classroom session:
Biblical Terms first, the Glossary at the end. Slide numbers below refer to that deck. The deck
is **not** committed to this repo. Its tool facts, menu paths, worked example and exercise data
are SME-supplied and may be used in lessons. Where the deck's speaker notes said "VERIFY IN
PT9", the SME's verified paths (SME answer 4) replace them.

## Scope and positioning

**Positioning: a full-lifecycle course.** It runs from choosing and protecting a project's
Biblical Terms list, through drafting-time use of the 4-step process, to making the work a team
routine and leading a team session.

- [`supporting-pre-publishing-checks`](../supporting-pre-publishing-checks/README.md) lesson 02
  stays the condensed *pre-publication* view. This course does not re-teach its pre-publication
  field cases in depth. It cross-references them: field case 4 (Guess Renderings run too early,
  first-word renderings), field case 6 (the All Biblical Terms list added to the project list,
  slowing Send/Receive), field case 9 (blank renderings as the default state) and field case 10
  (stale or duplicate renderings).
- That course's lesson 07 (glossary *linking scope*) states that glossary *content* is out of
  scope there. This course covers creating and editing glossary entries **from renderings**, as
  the deck does. It partly fills that gap without taking over pre-publication linking scope,
  which stays in lesson 07 there.

**In scope.** Topics follow the deck, plus the consultant layer:
- What Biblical Terms are, and "it's not a check, it's a process" (slides 2–4).
- The Major Biblical Terms list vs a Project Biblical Terms list. This includes linking a list
  to the project, filling the project list by starring terms, adding custom terms, and
  permissions and Send/Receive (slides 5–11).
- The Biblical Terms Renderings window vs the full Biblical Terms tool, reading the table, and
  comparative texts (slides 12–14).
- The 4-step process — Find, Choose, Reject + document, Make consistent — with the ἀδελφός
  worked example and the 1 Timothy 1:2 exercise (slides 15–31, 37).
- The three kinds of Biblical Terms notes, saved book sets, and other moves in the tool
  (slides 32, 33, 36).
- Guess Renderings for names, approving guesses, Clear Guessed Renderings, and marking renderings
  as correctly spelled (slide 35).
- The Glossary from Biblical Terms: the GLO book, permissions, adding, finding and editing
  entries, relinking, and linking selected renderings to the glossary (slides 38–43).
- Making it routine: levels of detail, and the Project Plan / Assignments and Progress
  (slide 44).
- **Exporting the Biblical Terms list to HTML** for review. This is included by the SME, but the
  exact PT9 path is not yet given (open item O3).
- Training a team: the deck as the model session, naming the steps in the team's language
  (slide 45), and the **mandatory led team session** (see "Completion requirement").

**Out of scope (SME):** the **NT Key Biblical Terms** list, and the **interlinear**. What a
term means in Greek or Hebrew is also out; the deck points to an Enhanced Resource for that
(slide 14 notes).

**Not covered until the SME answers.** These are open items, not omissions:
- recovering renderings lost after a restore or a Send/Receive conflict (O1);
- the hands-on method for shrinking an oversized project list (O2). The *diagnosis* is
  cross-referenced from `supporting-pre-publishing-checks` field case 6.

## Learning objectives

All objectives are anchored, per the ladder-offset rule, to the rows labelled
**`2 - With Assistance`** in [`competencies/translation-tools.md`](../../competencies/translation-tools.md),
whose `Reaches` column is `3 - Independent`:

- **2.0 Translation Tools, row `2 - With Assistance`:** "Assist in training others on the use
  of translation tools."
- **5.0 Scripture Collaboration, row `2 - With Assistance`:** "Advise users in best practices
  for collaboration and data safety (within the team and remotely), such as the use of
  Send/Receive. Assist users to configure plans and tasks in a way that helps them."

Both are components of the single framework competency **Translation Tools**, so the README
frontmatter needs no change.

**The boundary every objective keeps.** "Yes, the team makes the choices, the consultant just
teaches them the process." (SME)
- No objective, quiz item or scenario may depend on the learner judging whether a rendering,
  back translation, History note or glossary definition is *right*.
- The learner diagnoses the tool, the data and the workflow, and teaches the process.

| # | Objective | Source | Assessed by |
| --- | --- | --- | --- |
| 1 | Learner can decide, for any Biblical Terms question a team raises, whether it is a tool/process question the consultant answers, or a rendering decision that belongs to the team (or its Translation Consultant). They route it accordingly without making the rendering choice themselves. | Translation Tools 2.0, row `2 - With Assistance` — "Assist in training others on the use of translation tools"; SME answer 7 | Quiz §1; Scenario 6; Practicum |
| 2 | Learner can set up a project's Biblical Terms list unaided. That means deciding with the project administrator between the Major Biblical Terms list and a Project Biblical Terms list (deck recommendation: a Project list filled from Major), and linking it via **Project properties > Associations tab > Select the Biblical terms list**. It also means filling the project list by starring terms in the open Major list (shift-click a star to add a run of terms) and adding the team's own terms or phrases with **Add biblical term…** while the Project list is open. | Translation Tools 2.0, row `2 - With Assistance`; deck slides 6–10; SME answer 4 | Quiz §2; Practicum (session plan) |
| 3 | Learner can protect a project's Biblical Terms list and recover it. They remove permission to edit the project terms list from users who should not change it (**User Permissions > Other permissions…**), so terms are not deleted by an accidental star click. They restore a term that was deleted that way by switching back to the Major Biblical Terms list and restoring it to the project list. They explain to a team that teammates receive the list, and terms added to it, through Send/Receive. | Translation Tools 5.0 (Scripture Collaboration), row `2 - With Assistance` — "Advise users in best practices for collaboration and data safety … such as the use of Send/Receive"; deck slide 11; SME answer 5 | Quiz §2; Scenario 1 |
| 4 | Learner can diagnose a team's Biblical Terms state from what the tool shows: orange renderings cells, the Found symbols (✓, ?, blank, ✓ˣ), the count (e.g. 14/17), the Missing Renderings filter, and highlighting in the text. This includes recognising a full tick that only means every verse holds *some* added rendering, including ones the team may not want. They also diagnose the stumbles teams commonly hit: double-clicking the renderings cell instead of the term, leaving the verse filter off Current book, and skipping the History note. | Translation Tools 2.0, row `2 - With Assistance`; deck slides 13, 21, 26, 29–31, 37 notes | Quiz §3; Scenario 2 |
| 5 | Learner can teach a team the 4-step process and coach them through it on their own project: **Find** (open the term from the Renderings window, filter Current book, Ctrl+A each rendering, optionally add comparative texts); **Choose** (Edit Renderings, `*` to combine forms, back translation in `( )`); **Reject + document** (delete, then History > top line > Why Changed?); **Make consistent** (Missing Renderings, Edit or the verse reference, the red X for a permitted omission, a rendering discussion note). They model each step in the practice project while leaving every rendering choice to the team. | Translation Tools 2.0, row `2 - With Assistance`; deck slides 14–33, 36–37 | Quiz §3; Scenario 2; Practicum |
| 6 | Learner can decide when Guess Renderings will help. In the deck it is used for proper names (category Name). The SME's threshold is "at least a quarter of the New Testament … The more text, the better." They run it, get a team member to approve or correct every guess (tick one, or select rows and Ctrl+G), and mark approved names as correctly spelled. They recover from a premature run with **Clear Guessed Renderings**, recognising the first-word pattern (`supporting-pre-publishing-checks` field case 4) as its likely sign. | Translation Tools 2.0, row `2 - With Assistance`; deck slide 35; SME answers 5–6 | Quiz §4; Scenario 3 |
| 7 | Learner can support a team in building glossary entries from Biblical Terms renderings. They diagnose a "Not Editable" Edit Renderings title bar as a missing GLO-book permission. They add an entry from the Glossary tab and check whether a term already has one (Glossary Entries filter, the asterisk on the Glossary tab, the GLO book). They relink renderings after a citation form is edited in the GLO book, and link glossary words in the text via **Biblical terms Menu > Link selected renderings to glossary**. Entry wording stays with the team. | Translation Tools 2.0, row `2 - With Assistance`; deck slides 38–43; SME answers 2, 4 | Quiz §5; Scenarios 4, 5 |
| 8 | Learner can help a team make Biblical Terms a routine. They pick a realistic level of detail (deck slide 44), save a named book set in the verse filter that teammates receive by Send/Receive, and put the "Biblical Terms Renderings Found" check early in the Project Plan so it shows in Assignments and Progress. They export the Biblical Terms list to HTML for team or Translation Consultant review (path: open item O3). | Translation Tools 5.0, row `2 - With Assistance` — "Assist users to configure plans and tasks in a way that helps them"; Translation Tools 2.0, row `2 - With Assistance`; deck slides 33, 44; SME answer 9 | Quiz §6; Scenario 7 |
| 9 | Learner can prepare and lead a real Biblical Terms working session with a translation team on that team's own project. Team members drive the keyboard and make every rendering decision. The learner then debriefs it with their mentor. | Translation Tools 2.0, row `2 - With Assistance` — "Assist in training others on the use of translation tools"; SME answer 8 | **Practicum (mandatory, mentor-verified)** |

## Module breakdown

Each lesson follows Learning That Lasts (Connect → Content → Challenge → Change), opens with
`**Estimated time:**`, and carries at least one visual.

- **Practice data.** Learners get a zipped practice project and restore it with Paratext's
  "Restore from backup" (SME answer 9). [`Paratext 9 advanced support`](../Paratext%209%20advanced%20support/README.md)
  lesson 03 covers restoring as a new project and may be cross-referenced. All hands-on work uses
  that project and only the Pidgin data recorded in "Real language data" below.
- **Screenshots.** Nystrom's screenshots from the deck are kept. They are exported from the deck
  into `assets/` at stage **3e**, named `ss-<lesson>-<what-it-shows>.png`, and captioned
  "Screenshot: John Nystrom, earlier Paratext version".
- **Shots the deck lacks** are written by `module-author` as image links with alt text, and a
  human captures them in PT9 at 3e. The deck has no image for the Associations tab, User
  Permissions > Other permissions, the Project Plan or the HTML export.

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-biblical-terms-and-your-role.md` | What Biblical Terms are; "it's not a check, it's a process" (right renderings, used consistently, decisions recorded); why teams skip it and where to start (slides 2–4). **The consultant's role:** the team chooses, the consultant teaches the process. Restoring the practice project. The two tools: Biblical Terms Renderings window vs the full Biblical Terms tool, and the right-click shortcut (slide 12). Reading the table: orange cells, Found symbols, Transliterate terms (slide 13). Comparative texts (slide 14). Visual: Nystrom's two-tools screenshots. | 1, 4 | 50 |
| `02-setting-up-the-project-list.md` | Major vs Project Biblical Terms list (slide 6). Linking the list: Project properties > Associations tab > Select the Biblical terms list (SME-verified). Filling the project list from Major by starring, with shift-click for many; adding custom terms (slides 7–10). Who sets the list, who receives it via Send/Receive. Removing edit permission so a stray star click can't delete terms, and **recovery** by switching back to the Major list (slide 11, SME answer 5). List size is cross-referenced to pre-pub field case 6; how to shrink a list is open item O2. Visuals: Nystrom's Open-list, star and Add-term shots; PT9 Associations-tab and permissions shots at 3e. | 2, 3 | 50 |
| `03-the-four-step-process.md` | The limerick and the four steps (slides 16–17). The ἀδελφός-in-James worked example end to end, using only the deck's data: Find (1A–1D), Choose (2A–2C: Edit Renderings, `*`, back translation in `( )`), Reject + document (3A–3B: History > Why Changed?), Make consistent (4A–4H: 14/17 → Missing Renderings → three verses → Edit / reference → 17/17 → Renderings window tick → highlighting). **Challenge:** the 1 Timothy 1:2 exercise (χάρις, ἔλεος, εἰρήνη) in the practice project. It is "done when the Found column shows a tick" and a History note exists, judged on process, never on which rendering was chosen. **Flag for `module-author`:** 80 minutes leaves 10 minutes of headroom; if it runs over, move highlighting (4H) to lesson 04 rather than exceed 90. | 4, 5 | 80 |
| `04-guesses-notes-and-other-moves.md` | Guess Renderings for names: Name-category filter, Tools > Guess Renderings, approve (tick / Ctrl+G), Tools > Mark Renderings as Correctly Spelled. **Clear Guessed Renderings**, which sits right below Guess Renderings, and the SME's quarter-of-the-NT threshold, cross-referenced to pre-pub field case 4 (slide 35). Other moves: typo fix via Edit, red X permitted omission, discussion note, adding another valid rendering, changing a synonym (slide 36). The three kinds of notes (slide 32). Saved book sets via Verse filter > Choose (slide 33). | 4, 5, 6 | 60 |
| `05-glossary-entries-from-renderings.md` | What a glossary is; the GLO book; "Not Editable" = GLO permission (slide 39). Adding an entry from the Glossary tab (slide 40). Is it already there: Glossary Entries filter, asterisk, GLO book (slide 41). Editing definitions vs citation forms, relinking, and keeping GLO alphabetical (slide 42). Linking in the text: Biblical terms Menu > Link selected renderings to glossary (SME-verified), cross-referencing pre-pub lesson 07 for linking *scope* at the final turn. **Challenge:** the glossary entry for grace (slide 43); the definition's wording is not assessed. | 7 | 50 |
| `06-making-it-routine-and-troubleshooting.md` | Levels of detail; putting "Biblical Terms Renderings Found" early in the Project Plan so it shows in Assignments and Progress; "fix it now or write a note" (slide 44). Exporting the list to HTML for review (path: open item O3; `module-author` leaves a marked placeholder). A consultant's troubleshooting sheet built only from confirmed stumbles: slide 37's three, the star-click deletion, Not Editable, broken glossary links after a citation-form edit, premature guesses, and the false-complete tick. Visual: PT9 Project Plan / Assignments and Progress shot at 3e. | 4, 8 | 50 |
| `07-preparing-your-team-session.md` | The deck as a model session and how to adapt it to one team's project and current chapter. Confirming the setup first (list linked, permissions). Naming the steps in the team's language (slide 45: Nystrom's Tok Pisin names; the team supplies its own). Keeping the keyboard with the team and routing rendering questions back. What the practicum evidence is and how the mentor verifies it. **Challenge:** draft the session plan the mentor will check. **Change:** book the session. This lesson's seat time covers preparation only; the field session itself is not counted. | 1, 9 | 45 |
| `08-scenario-bank.md` | Seven mentor-reviewed applied scenarios (see "Assessment plan") | 1–8 | 60 |
| `09-mentor-guide.md` | Facilitator notes: per-scenario watch-fors, the practicum evidence checklist and sign-off rubric | — | — |
| `10-quiz.md` | 15-question quiz | 1–8 | — |
| `11-video-script.md` | Overview video script. The video goes in the course `README.md` above its first `##` heading. | — | — |
| **Total learner seat time** | | | **445** |

The 445 minutes (about 7.4 hours) is lessons plus scenario bank. It excludes the mentor guide,
the quiz and the field session itself. It is longer than the deck's 90 minutes because the deck
is the content a team receives, and this course teaches the consultant to deliver it, diagnose
it and recover from failures. **The Design Approver may want to weigh whether 445 is right**
(see open item O8).

## Completion requirement: the led team session (practicum)

"The learner should have to lead a real team session in order to check this course off as
complete." (SME answer 8)

The course is **not complete** until a mentor verifies this, *in addition to* passing the quiz
and the mentor-reviewed scenario bank. It is the direct evidence for objective 9 and for the
descriptor activity "Assist in training others on the use of translation tools".

**What the learner does.** The learner leads one working session with a real translation team,
on the team's own project, covering at least the 4-step process for at least one term in the
chapter the team is working on.

**Evidence the mentor checks.** All of it is submitted in Moodle, never committed to this repo.
Project data and screenshots of a team's work are learner data, and require the team's
permission.

1. **Session plan, before the session.** It records:
   - the team and project;
   - which Biblical Terms list the project uses, and who set it;
   - that permissions were checked (project terms list editing; GLO book if the glossary is
     covered);
   - which chapter and terms, which steps, and the planned length;
   - how the steps will be named for this team.
2. **Process evidence from the session.**
   - (a) For each term worked: the count or Found state before and after Make consistent, or the
     Missing Renderings filter empty for the range. This could be a screenshot or a written
     record.
   - (b) At least one History "Why Changed?" entry written by a team member.

   The mentor checks that these **exist and follow the process**: who / what / why recorded,
   filter set to the range. The mentor does **not** assess whether the renderings or the note's
   content are right; that is the team's decision, often in a language neither mentor nor
   learner reads.
3. **Keyboard and role.** The learner states where, if anywhere, they took the keyboard, and
   which rendering questions they routed to the team or Translation Consultant.
4. **Debrief with the mentor.** Live or remote, after the session: what the team stumbled on,
   what the learner would change, and what follow-up was agreed (e.g. Project Plan placement).
5. **Mentor sign-off** in Moodle against the rubric in `09-mentor-guide.md`.

Open decisions for the approver are O4 and O5 (whether the mentor must attend, what counts as a
session, and the alternative for a learner without team access).

## Assessment plan

**Three parts, all required.**

- **Quiz (`10-quiz.md`).** 15 questions; 80% to pass (12/15). Covers objectives 1–8 in six
  sections:
  - §1 role, objective 1: 2 questions;
  - §2 setup and protection, objectives 2–3: 3 questions;
  - §3 reading the tool and the four steps, objectives 4–5: 4 questions;
  - §4 Guess Renderings, objective 6: 2 questions;
  - §5 glossary, objective 7: 2 questions;
  - §6 routine, objective 8: 2 questions.

  Items are diagnosis or recognition ("the tool shows X; what happened and what do you do
  next"). No item may hinge on which rendering is correct.
- **Scenario bank (`08-scenario-bank.md`).** Seven mentor-reviewed scenarios. Every one is built
  only from an SME-confirmed case (see "SME knowledge notes"):
  - (1) terms vanished from the project list after a star click — objective 3;
  - (2) a team says a term is "done" because the tick shows, but unwanted renderings are still
    listed and there is no History note, plus slide 37's stumbles — objectives 4–5;
  - (3) Guess Renderings run on a project with little text, producing first-word renderings —
    objective 6;
  - (4) a team member can't type a glossary entry: Not Editable — objective 7;
  - (5) renderings no longer link to a glossary entry after its citation form was edited in
    GLO — objective 7;
  - (6) a team asks the consultant to pick between two renderings — objective 1. It uses no
    language data beyond the deck; otherwise it uses a marked placeholder;
  - (7) a team with no Biblical Terms routine, whose Assignments and Progress shows nothing —
    objective 8.

  Each is scored by a mentor against `09-mentor-guide.md` on reasoning, routing and coaching,
  not on a single fix.
- **Practicum.** The mentor-verified led team session (objective 9), as specified above.

Together these earn the `3 - Independent` claim. The quiz alone evidences recognition only,
following the precedent set for `supporting-pre-publishing-checks` (Kevin Nicholas,
2026-08-27). This course goes further, since the SME requires a real session.

## SME knowledge notes

Recorded from Doug Higby's answers (relayed 2026-11-12) and from his deck. Nothing here is
invented. Anything the SME did not supply is listed under "Open items".

### SME answers, as given

1. **Audience and data.** "This course is for Language Technology Consultants — those who train
   Paratext users." "We use the Hawaiian Pidgin language for the exercises because an English
   LTC can understand it."
2. **Glossary.** Include it, as in the deck.
3. **Rights.** "I have permission from John to use his slides and create this course."
4. **Verified PT9 paths.**
   - Choosing the list: "Project properties > Associations tab > Select the Biblical terms list".
   - Linking glossary words in text: "Biblical terms Menu > Link selected renderings to
     glossary."
5. **Recovery.**
   - "When a term is accidentally deleted with a star click, you need to switch back to the
     major Biblical terms list to restore it to the project BT list."
   - Undoing guesses: "You have the option to 'Clear guessed renderings' right below the option
     to Guess renderings."
6. **Guess Renderings.** "You would need at least a quarter of the New Testament for the
   guessing to work well. The more text, the better." This is consistent with
   `supporting-pre-publishing-checks` field case 4: first-word renderings, likely from Guess
   Renderings run before the project had enough text, per a support.bible programmer,
   2026-09-10.
7. **Role boundary.** "Yes, the team makes the choices, the consultant just teaches them the
   process."
8. **Assessment.** "The learner should have to lead a real team session in order to check this
   course off as complete."
9. **Practicalities.**
   - Learners get a zipped practice project they restore via Paratext "Restore from backup".
   - Keep the existing Nystrom screenshots from the deck.
   - Include exporting the Biblical Terms list to HTML (path not yet given).
   - Exclude the NT Key Biblical Terms list and the interlinear.
   - On the 2026-11-11 session: "All went OK today". There are no further stumbles beyond those
     in slide 37's notes.

### Field cases

- **The 2026-11-11 classroom session.** The SME taught the deck to 40 people in 90 minutes, and
  "All went OK today."
- **Common stumbles during the 1 Timothy 1:2 exercise** (slide 37 notes, verbatim):
  "double-clicking the renderings cell instead of the term in 1A; forgetting to set the verse
  filter to Current book; not writing the History note."
- **Accidental deletion by star click** (slide 11, SME answer 5). "Terms or words will
  accidentally be deleted if the star is clicked." This is prevented by removing permission to
  edit the project terms list, and recovered by switching back to the Major list to restore the
  term.
- **"Not Editable"** (slide 39 notes). "if the Edit Renderings title bar says Not Editable and
  you can't type in the citation form, you lack the task assignment or permission for the GLO
  book. Ask your administrator."
- **Cross-referenced from `supporting-pre-publishing-checks`, not re-recorded here:**
  - field case 4: first-word renderings from Guess Renderings run too early;
  - field case 6: the All Biblical Terms list added to the project list, slowing Send/Receive;
    "hard to convince the team of the actual cause";
  - field case 9: blank renderings as the default state;
  - field case 10: stale or duplicate renderings.

### Common mistakes (from the deck)

- **Treating Biblical Terms as a check to clear.** "We want more than Paratext saying 'no
  issues'." The goal is the right renderings, used consistently, with decisions recorded
  (slide 3).
- **Starting too late.** "If you wait until the consultant check, the job is too big and the
  team has forgotten why it chose what it chose" (slide 3 notes).
- **No routine.** "most teams have no Biblical Terms routine. The job looks enormous and the
  tool has a lot of menus" (slide 4 notes).
- **Trusting a tick.** "Count now reads 17/17 with a tick, but that only means every verse has
  one of the renderings you added, including the ones you may not want" (slide 21 notes).
- **Not recording the decision.** "Those who don't record their Biblical Terms history are
  doomed to repeat it." (Nystrom p50, slide 25 notes)
- **Leaving guesses unapproved.** "Every guess needs a person on the team to approve or correct
  it" (slide 35).
- **Editing a glossary citation form without relinking** (slide 42).

### What "good" looks like at 3 - Independent

The SME's own statements:
- the team makes the choices, and the consultant teaches the process (answer 7);
- the learner leads a real team session (answer 8).

The SME has **not** yet described competent performance more fully. Draft watch-fors for
`09-mentor-guide.md`, **designer-proposed and needing SME confirmation (open item O7)**, all
drawn from the deck:
- the consultant confirms setup and permissions before the session;
- team members drive the keyboard;
- every rendering choice is the team's, and the consultant routes questions rather than
  answering them;
- the consultant does not accept a tick as "done" without the History note and the Missing
  Renderings check;
- the consultant can recover from a star-click deletion and a premature guess run unaided;
- the team leaves with a routine (level of detail, Project Plan placement).

### Tool facts from the deck (SME-supplied)

Use these as given. The SME-verified paths in answer 4 supersede the deck's "VERIFY IN PT9"
notes.

- **Opening the tools** (slide 12):
  - Renderings window: project menu > Tools > Biblical Terms Renderings.
  - Full tool: project menu > expand > Tools > Biblical Terms.
  - Shortcut: right-click in the text > View Biblical Terms for Current Verse / Chapter / Book.
- **Lists** (slides 6–11):
  - The list is linked via Project properties > Associations tab > Select the Biblical terms
    list (SME-verified). Only the administrator links the list. Everyone else receives it, and
    terms added to it, through Send/Receive.
  - To fill the project list: Biblical Terms menu > Open Biblical Terms List… > Major Biblical
    Terms; star a term to add it, unstar to remove it. To add many, click the first term, scroll
    to the last and shift-click its star.
  - To add custom terms: Open Biblical Terms List… > Project Biblical Terms > Add biblical term….
  - Permission to edit the project terms list is under User Permissions > Other permissions….
- **The table** (slide 13):
  - Orange = no rendering yet, or a guess waiting for approval.
  - Found: ✓ = a confirmed rendering in every verse in range; ? = only guessed renderings found;
    blank = at least one verse has none; ✓ˣ = all found, some are permitted omissions.
  - Window menu > View > Transliterate terms.
- **Comparative texts** (slide 14): toolbar > Comparative Texts > pick a resource > move-right
  arrow > reorder with up/down > OK.
- **The four steps** (slides 17–31):
  - Find: double-click the *term* in the Renderings window; verse filter Current book; select
    and Ctrl+A (Add Rendering). Other ways to add: type or paste into Edit Renderings, or drag
    and drop after enabling it in Paratext Settings.
  - Choose: double-click the renderings field to open Edit Renderings. `*` combines forms and
    can go at the start or end. A back translation goes in `( )`, which Paratext ignores when
    matching. The Guide button explains these. Optionally, Tools > Word prefixes and suffixes.
  - Reject + document: deleting a rendering shows a warning beside History; then History >
    click the top line > Why Changed?.
  - Make consistent: count 14/17; term filter Missing Renderings; fix via Edit (quick-edit
    window) or the blue reference (opens the verse); switch back to All terms, 17/17.
  - Highlighting: project menu (≡) > View > Highlight Biblical Term Renderings. Approved
    renderings are shaded grey; orange marks a term still unrendered or guessed.
- **Notes** (slide 32):
  - rendering discussion notes (far-left column; while deciding);
  - rendering description (Edit Renderings > Description; once decided);
  - history notes (Edit Renderings > History; when renderings change).

  Filters exist for Rendering Description and for Unresolved / Unread Rendering Discussion Notes.
- **Saved book sets** (slide 33): Verse filter > Choose > pick books > name > save. A saved set
  works in Biblical Terms, Wordlist, Notes and Parallel Passages, and is shared by Send/Receive.
- **Guess Renderings** (slide 35):
  - Current Biblical Terms filter > New Filter > category Name > save.
  - Biblical Terms window menu > Tools > Guess Renderings (Name preselected; a model text is
    optional). Guesses appear orange.
  - Approve one by ticking its box, or many by selecting rows and pressing Ctrl+G.
  - Clear Guessed Renderings removes them all; it sits right below Guess Renderings (SME).
  - Tools > Mark Renderings as Correctly Spelled.
  - Threshold: at least a quarter of the NT (SME).
- **Other moves** (slide 36):
  - Typo: Edit > fix > OK.
  - Right wording with no rendering: red X, a permitted omission.
  - To discuss: double-click the first column to add a note.
  - Another valid rendering: select > Add Rendering.
  - A synonym that should change: Edit > change > OK.
- **Glossary** (slides 39–42):
  - In Paratext the glossary is the GLO book.
  - To add an entry: double-click the rendering > Glossary tab > citation form + definition >
    OK. Entries are kept alphabetical.
  - To check for an entry: Term filter > Glossary Entries; an asterisk on the Glossary tab means
    already linked; or open the GLO book.
  - Edit the citation form only in GLO, then relink: Verse filter All books > Biblical Terms
    filter > New Filter > Text filter > … > Glossary tab > pick the entry.
  - Linking in the text: Biblical terms Menu > Link selected renderings to glossary
    (SME-verified). This replaces slide 42's unverified note.
- **Routine** (slide 44): put the "Biblical Terms Renderings Found" check early in the Project
  Plan so it appears in Assignments and Progress.

### Tool-version specifics

- The deck targets **Paratext 9**. All screenshots are Nystrom's from an earlier Paratext version
  ("very little has changed in PT9", slide 1 notes).
- Slide 31's menu screenshot shows the older menu bar; the deck gives the PT9 path in text.
- Paratext 10 is not addressed by the SME. The course is PT9-only unless the approver directs
  otherwise.

### Real language data (SME-supplied; use verbatim, add nothing)

The SME chose Hawaii Pidgin so an English-speaking LTC can follow the exercises. This is a
deliberate exception to the usual rule that the consultant cannot read the project language.
**The exercises are still judged on process, never on the rendering chosen.**

`module-author` and `quiz-writer` may use **only** the following, exactly as written. Anything
further is a marked placeholder for the SME (Doug Higby) to supply.

- **Worked example:** ἀδελφός (brother) in James, Hawaii Pidgin project **MP6**.
  - Renderings found: **bruddas an sistas**, **brudda**, **bruddas**, **frenz**.
  - Combined form: **brudda\***.
  - Back-translation example: **(brothers and sisters)**.
  - Counts: 17/17 after Find; 14/17 after rejecting "frenz"; then 17/17.
  - The three verses left: James 1:16, 1:19 and 2:5, all with "frenz".
  - Example History note: "Added back translations. Deleted 'frenz' because it does not include
    the idea of family relationship, which is a theme throughout the Bible."

  The decision to delete "frenz" and its reason belong to the **example team** (Nystrom's
  example). Lessons present them as that team's recorded decision, not as a judgment the
  learner or the consultant makes.
- **Highlighting example:** "trus" shown with orange shading as still unrendered or guessed
  (slide 31 notes).
- **Exercise:** 1 Timothy 1:2: χάρις (grace), ἔλεος (mercy), εἰρήνη (peace). The deck gives **no**
  Pidgin renderings for these; learners find them in the practice project. Lessons must not
  supply them.
- **Glossary exercise:** an entry for grace (χάρις), extra for mercy (ἔλεος). The definition the
  learner writes is not assessed, and no model definition is supplied.
- **Step names in Tok Pisin** (slide 45, John Nystrom's teaching in Papua New Guinea): Painim
  (find), Skelim (evaluate), Rausim (remove), Raitim (write notes), Stretim (make consistent).
- **Placeholder:** whether the deck's MP6 project and the zipped practice project are the same
  project (open item O9).

## Open items (for the SME and Design Approver)

| # | Item | Owner |
| --- | --- | --- |
| O1 | **Recovering renderings lost after a restore or a Send/Receive conflict.** Unanswered. No objective or scenario covers it until the SME supplies the recovery. | SME |
| O2 | **How to shrink an oversized project list.** Only the diagnosis is cross-referenced (pre-pub field case 6). Is it unstarring in bulk? Are renderings kept when a term is unstarred and restarred? Unanswered. | SME |
| O3 | **Exact PT9 path for exporting the Biblical Terms list to HTML.** In scope, but `module-author` leaves a marked placeholder in lesson 06 until it is given. | SME |
| O4 | **Practicum format.** Must the mentor attend (live or remote), or is the evidence pack plus debrief enough? Does co-leading a team session at a workshop count? | Design Approver |
| O5 | **Learners without access to a team.** Is there any alternative, or does the course stay incomplete until a session is possible? | Design Approver / SME |
| O6 | **Public publication of Nystrom's screenshots.** The permission recorded is to "use his slides and create this course". The repo, review sites and Moodle are public-facing. Does the permission cover that? | SME |
| O7 | **Confirm the "what good looks like" watch-fors** proposed above for the mentor guide. | SME |
| O8 | **Seat time.** 445 minutes against a 90-minute source deck. Accept, or trim (e.g. merge lessons 06 and 07)? | Design Approver |
| O9 | Is the deck's Hawaii Pidgin project **MP6** the same as the zipped practice project? Who hosts the zip, and where? A large binary should not be committed; the same rule as for video applies. | SME |
| O10 | **Column naming.** The deck calls the n/m figure the **Count** column and uses **Found** for the tick symbols. `supporting-pre-publishing-checks` lesson 02 refers to the "Found column/count, e.g. 2/2" and a "Found count of only 2/11". Which label does PT9 show for the n/m figure? The two courses should agree. | SME |
| O11 | **Practicum vs the next ladder row.** The `3 - Independent` row of 2.0, which *reaches* `4 - Expert`, is "Prepare and lead a translation tools training event." This design treats one mentor-verified working session with one team on its own project as *assisting in training*, the row-2 activity. A workshop or classroom event like the deck's 40-person session would belong to the next rung. Confirm the line is drawn there. | Design Approver |

## Follow-ups outside this course (not done here)

- `supporting-pre-publishing-checks` lesson 02 should, once this course is published, link here
  for the full Biblical Terms lifecycle. Its lesson 07's "a separate course will cover glossary
  content" could point here for creating entries from renderings. **Do not edit that course
  from this branch.**
- Legacy [`scripture-pre-publishing-checks/04`](../scripture-pre-publishing-checks/04-proper-names-and-biblical-terms.md)
  has a "Pro-Tip" saying a blue arrow / **Ctrl+R** "appl[ies] the rendering to the verse". The
  deck gives Ctrl+A as Add Rendering and describes no such apply action. That legacy claim is
  unverified. This course follows the deck and does not repeat it.

---

### Summary and handoff

- **9 learning objectives**, all anchored to the `2 - With Assistance` rows of Translation
  Tools 2.0 and 5.0, which reach `3 - Independent`.
- **7 numbered lessons** plus scenario bank, mentor guide, quiz and overview video script: 11
  planned files.
- **445 minutes** of learner seat time (lessons plus scenario bank). Every lesson is ≤ 90
  minutes; lesson 03 is at 80, with a split rule stated.
- Assessment: a 15-question quiz (80%), seven mentor-reviewed scenarios, and a **mandatory,
  mentor-verified led team session**.
- Every objective traces to a descriptor row and to an assessment.
- Every tool fact is taken from the SME's deck or answers. Every piece of language data is the
  deck's own, and nothing beyond it may be added.

**This document is now the contract** for `module-author` and `quiz-writer`. They draft only
the lessons and objectives specified here: no additional topics, no competencies beyond
Translation Tools, no language data beyond the inventory above, and no assessment item that
asks the learner to judge a rendering.

**Human approval is required before drafting begins.** I'm requesting review from the Design
Approver per stage 2. Open items O4, O5, O8 and O11 need an approver ruling. O1–O3, O6, O7, O9
and O10 need SME answers; those can follow approval as long as `module-author` leaves marked
placeholders where they apply.
