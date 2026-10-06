# Course Design Document

> **Before proceeding:** Content drafting must not begin until this document is approved by a human reviewer. This is stage 1 of the [production pipeline](../../process/PROCESS.md) — see [`process/stages/01-design.md`](../../process/stages/01-design.md) and [`02-approve.md`](../../process/stages/02-approve.md).

## Course overview

| Item | Description |
| --- | --- |
| **Title** | Effective Use of AI in LT Consulting |
| **Competencies addressed** | Consulting Process Skills<br>Artificial Intelligence (AI) Tools *(proposed — see Decision 1)* |
| **Target outcome level** | 2 - With Assistance |
| **SME(s) consulted** | _Not yet interviewed — see SME knowledge notes_ |
| **Design status** | Draft |
<!-- On approval (stage 2), the Design Approver replaces the line above with:
     | **Design status** | Approved by <name> on <YYYY-MM-DD> | -->

### Decisions for the Design Approver

1. **Add `Artificial Intelligence (AI) Tools` to the frontmatter `competencies:` list?** The requested name "AI Tools" does not exist in `competencies.yaml`; the verbatim framework name is `Artificial Intelligence (AI) Tools`. Most objectives below derive from its descriptor, so this design recommends adding it. `README.md` frontmatter has **not** been edited — the approver (or the human on this branch) should add it and run `python scripts/gen_coverage.py`.
2. **Target outcome level.** `README.md` frontmatter has no `target_outcome_level`. This design proposes `2 - With Assistance` (objectives lifted from the ladder rows labelled `1 - Has Knowledge`, which reach level 2). Approve and add to frontmatter, or choose another level and return this design for revision.
3. **Consulting Process Skills has no level ladder** (it is a sub-competency/observable-criteria descriptor), so objective 8 draws on its criteria directly at a "with assistance" depth. Confirm this competency should stay, or treat it as secondary.
4. **SME interview outstanding.** No SME has been interviewed yet; field stories, tool choices and organisational AI policy must be supplied before stage 3 (see SME knowledge notes).

## Learning objectives

Objectives target `2 - With Assistance`; for `Artificial Intelligence (AI) Tools` they are lifted from the rows labelled **1 - Has Knowledge** (which reach level 2) in `competencies/artificial-intelligence-ai-tools.md`. All objectives concern the consultant's **own technical work** — none asks the learner (or AI) to judge minority-language text.

| # | Objective | Source | Assessed by |
| --- | --- | --- | --- |
| 1 | Learner can write an effective prompt for a technical consulting task (troubleshooting a Paratext/keyboard/font problem, explaining a tool setting) using context-setting, role assignment, examples and a requested output structure. | Artificial Intelligence (AI) Tools, 1.0 General AI Tools, row `1 - Has Knowledge` | Quiz §A; Scenario 1 |
| 2 | Learner can choose between available general AI assistants for a given consulting need (e.g. drafting, scripting, document analysis) and explain the choice. | Artificial Intelligence (AI) Tools, 1.0, row `1 - Has Knowledge` ("compare and choose among major tools") | Quiz §A |
| 3 | Learner can use AI with guidance to draft a small data-conversion or file-processing script (e.g. regex, Python, batch rename) and test it on a copy of the data before use. | Artificial Intelligence (AI) Tools, 5.0 Workflow Integration, row `1 - Has Knowledge` | Scenario 2 |
| 4 | Learner can verify AI output about software (menu paths, settings, version-specific features, script behaviour) against authoritative sources — help files, release notes, a test run — before passing it to a team. | Artificial Intelligence (AI) Tools, 3.0 Critical Evaluation, row `1 - Has Knowledge` ("routinely verifies… against authoritative sources") | Quiz §B; Scenario 1 |
| 5 | Learner can identify common AI failure modes (hallucinated features or citations, fluent-but-wrong answers, outdated version info) and explain why AI judgements about minority-language text — correctness, glossing, spelling, normalisation — must never be relied on and belong with the translation team. | Artificial Intelligence (AI) Tools, 3.0, row `1 - Has Knowledge` (failure modes); row `0 - No Competency` heightened low-resource risk | Quiz §B; Scenario 3 |
| 6 | Learner can apply basic data-handling practice before using AI: redact or withhold unpublished translation and community language data, choose tools with suitable privacy/retention settings, respect community ownership, and disclose AI-assisted work. | Artificial Intelligence (AI) Tools, 4.0 Ethical, Responsible and Secure Use, row `1 - Has Knowledge` | Quiz §C; Scenario 4 |
| 7 | Learner can use a pre-built AI integration (configured Project/Custom GPT with reference files, AI in Office or VS Code) to draft consulting documentation — trip reports, how-to notes, emails to teams. | Artificial Intelligence (AI) Tools, 5.0, row `1 - Has Knowledge`; 1.0 row `1 - Has Knowledge` | Scenario 5 |
| 8 | Learner can, with support, fit AI into a consulting session — defining with the team what AI will and won't be used for, and checking AI-assisted results with them afterwards. | Consulting Process Skills, Establishing a Procedure ("defines objectives, roles, and responsibilities"); Implementation and Evaluation ("checks whether results meet expectations and discusses them with the team") | Scenario 4; Quiz §C |

## Module breakdown

All lessons follow Learning That Lasts (Connect → Content → Challenge → Change). Lesson 1 is the course overview; its visual is the overview video. No Challenge activity may have an answer that depends on reading language data.

| File | Topic | Objectives covered | Estimated minutes |
| --- | --- | --- | --- |
| `01-overview-ai-in-lt-consulting.md` | Course overview: what AI is good for in an LTC's own technical work, and the hard limits (cannot read minority languages; project data is not ours) | 2, 5 | 30 |
| `02-prompting-for-technical-work.md` | Prompting and tool choice for troubleshooting, explaining tools and drafting docs/emails; using Projects/custom instructions | 1, 2, 7 | 75 |
| `03-ai-for-scripts-and-data-conversion.md` | AI-assisted scripting and data conversion: draft, test on a copy, verify; checking AI claims about software | 3, 4 | 75 |
| `04-limits-privacy-and-the-consulting-process.md` | Failure modes; why AI judgements on language text are off-limits; data privacy, consent and disclosure; agreeing AI use with a team | 5, 6, 8 | 60 |
| `05-scenario-bank.md` | Applied practice (5 scenarios) | 1, 3, 4, 5, 6, 7, 8 | 60 |
| `06-mentor-guide.md` | Facilitator notes | — | — |
| `07-quiz.md` | Assessment | 1, 2, 4, 5, 6, 8 | — |
| **Total learner seat time** | | | **300** |

Planned scenarios (module-author drafts; each answer must turn on the tool, data handling or workflow, never on the meaning or correctness of language text):

1. AI gives a confident Paratext fix that names a menu/setting; learner must verify it before advising the team. *(Needs SME: a real case where AI was wrong about a tool.)*
2. Team needs a file converted (e.g. legacy-encoded text or SFM marker cleanup); learner prompts for a script and tests it on a copy. **Placeholder:** `[SME-SUPPLIED SAMPLE: a short real file excerpt showing the structural problem — markers/encoding — to be converted. Learner works on structure only; language content must not be interpreted.]`
3. A team member asks the LTC to "have AI check our spelling/translation." Learner explains why that is not the consultant's or AI's call and redirects to the translation team.
4. Learner is about to paste an unpublished project file into a public AI tool; decide what to redact, which tool settings apply, and how to agree AI use with the team. *(Needs SME/organisation: current SIL/Wycliffe AI guidance.)*
5. Draft a trip report/email from session notes using a configured Project; learner reviews it for factual accuracy about the software and removes any project data that shouldn't leave the team.

## Assessment plan

A 15-question multiple-choice quiz (80% = 12/15 to pass) in three sections — §A prompting and tool choice (objectives 1–2), §B verification and failure modes (4–5), §C data handling and agreeing AI use (6, 8). The five-scenario bank, graded holistically by the mentor with a rubric in the mentor guide, is the main evidence for the "perform with assistance" objectives 1, 3, 4, 5, 6, 7 and 8. No quiz item or scenario may require the learner to read, gloss, translate or judge minority-language text.

## SME knowledge notes

**No SME interview has taken place yet.** Nothing below may be invented; the following must be collected (record answers verbatim) before stage 3 drafting:

- **Real field cases (1–3):** situations where an LTC used AI helpfully for technical work, and where AI misled them (especially wrong claims about Paratext, keyboards, fonts, FieldWorks, Bloom). — *Gap; supplier: SME.*
- **Common learner mistakes:** e.g. over-trusting AI, pasting project data, asking AI about language correctness. — *Gap; to be confirmed by SME, not assumed.*
- **What "good" looks like at 2 - With Assistance.** — *Gap.*
- **Tool specifics:** which AI assistants are approved/available to LTCs, account types and privacy/retention settings, any SIL-internal tools. — *Gap.*
- **Policy:** current SIL / Wycliffe AI-use and data guidelines to cite in lesson 4. — *Gap; supplier: SME or organisation.*
- **Real data for scenario 2:** a short real file excerpt illustrating a structural/encoding conversion problem, supplied verbatim with permission from the project. — *Gap; module-author leaves the marked placeholder above until supplied.*
