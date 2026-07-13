# Wargame: bug hunt in the never-run course-production machinery

**For the executor (Claude Opus 4.8).** This is your route, fought on paper first. Every
finding below was either **live-fired** during the wargame (the fixture run and its exact
output are recorded — you will reproduce them) or **hand-traced** through the code with
file:line evidence. Follow the moves in order. Every move states what you should see; if
you see something else, take the listed counter-move. Do not ask questions — the forks
cover the decisions.

**Ground truth from recon (verified 2026-07-06):**

- `Glob modules/*/00-design.md` → exactly one hit: `modules/_template/00-design.md`. No
  real course has entered the pipeline. This is **expected state, not a bug**. It means
  every bug below is latent in never-run code; there is no real course content to audit
  and you must not go looking for any.
- Baseline verification (all pass, repo clean):
  - `python scripts/gen_coverage.py` → `covered=21 gaps=22`, exit 0, COVERAGE.md byte-identical after run.
  - `python scripts/check_course_package.py` → `Checked 0 opted-in course(s): 0 error(s), 0 warning(s).`, exit 0.
  - `python scripts/check_competency_descriptors.py` → `framework: 43 competencies (1 exempt) · descriptors: 42 · covered: 42` / `OK`, exit 0.
- `scripts/_issues.json` and `scripts/_export_manifest.json` both exist (the `/next-step`
  gh-fallback path is intact).
- Do **not** run `scripts/export_from_notion.py` or `scripts/bootstrap_github.py` at any
  point — they touch the live Notion export path and the live GitHub board. Findings F7–F9
  about them are trace-only, report-only.

---

## The findings board (ranked)

Severity: how badly the **first real course** gets hurt when this fires.

| # | Finding | File:line | Severity | Proof status |
|---|---------|-----------|----------|--------------|
| F1 | `is_lesson()` excludes by **substring**: any lesson whose filename contains `design`, `quiz`, `scenario-bank`, `mentor-guide`, or `video-script` anywhere (e.g. `01-survey-design-basics.md`) is silently skipped by the duration/90-min check AND makes the "no numbered lesson files yet" warning fire falsely | `scripts/check_course_package.py:35-40` | **High** — the checker's core promise (90-min cap) silently disabled for plausible filenames | **Live-fired** (see Move 3: a 300-minute lesson produced zero errors) |
| F2 | `ANSWER_KEY_RE` rejects the **escaped-pipe** answer key of the one exemplar quiz (`1. B \| 2. C \| …`, `modules/language-technology-overview…/10-final-assessment-quiz.md:111`) that `quiz-writer.md:10-13` explicitly tells the agent to imitate ("matching the rigor and format of…") | `scripts/check_course_package.py:30` | **High** — the first machine-written quiz plausibly fails CI on a format the repo itself models | **Live-fired** (Move 3: exemplar-style key → `ERROR: no pipe-separated answer key found`) |
| F3 | `DESIGN_STATUS_RE` checks only that the row **exists**, not its value — `\| **Design status** \| Aprooved, someday, maybe \|` passes. Contradicts the script's own docstring (line 12: "a **malformed** `**Design status**` line" is an ERROR) and `process/stages/02-approve.md:44` ("this is what … the package check look[s] for") | `scripts/check_course_package.py:29,50-55` | **Medium** — the approval gate (stage 2, "the single most important addition to the process") has no machine teeth; a typo'd approval line blocks nothing | **Live-fired** (Move 4: garbage status → 0 errors, exit 0) |
| F4 | `THRESHOLD_RE` accepts the bare word `pass` anywhere: a quiz with **no** pass threshold but the sentence "Many learners pass over this section" passes the threshold check | `scripts/check_course_package.py:31` | **Medium** — quiz can reach internal review with no threshold, violating the stated ERROR contract (docstring line 11) | **Live-fired** (Move 4: thresholdless quiz → 0 errors) |
| F5 | `coverage.yml` has **no `pull_request` trigger** — `gen_coverage.py`'s exit-1 typo'd-competency check (CLAUDE.md hard rule #3) only runs on push to `main`. A PR adding `competencies: [Literacy Toolz]` merges green, then breaks main's Coverage job post-merge | `.github/workflows/coverage.yml:3-9` | **Medium-High** — the one CI gate for competency-name typos fires only after the damage is merged | **Trace + live-fired half**: typo fixture → `gen_coverage.py` exit 1 confirmed locally; trigger gap confirmed by reading all four workflows (`course-package.yml` runs on PR but does not check competencies) |
| F6 | `/next-step` fleet overview (`Glob modules/*/00-design.md`, step 1) has **no `_template` exclusion** — unlike `check_course_package.py:96-97`. Today `_template` is the *only* hit, so the very first fleet run reports the template as an in-flight course stuck at stage 2 ("Design status: Draft") instead of "no courses in the pipeline yet" | `.claude/commands/next-step.md:15-24` | **Medium** — user-facing wrong answer on every argument-less `/next-step` run | **Traced** (glob result confirmed; stage table walked by hand — `_template` satisfies row 1, fails row 2, so it reports "Stage 2 — Design approval" for a non-course) |
| F7 | `bootstrap_github.py`: `gh()` on failure prints and returns `None` **without exiting** (line 44-47, `parse=False` path); `cmd_issues` then records `url = ""` into `_issues.json` (lines 73-77). A transient `gh` failure permanently poisons the skip-list: re-runs skip the slug ("exists"), and `cmd_project` skips it too (`issues.get(slug)` falsy, line 101-103). The issue is silently never created | `scripts/bootstrap_github.py:41-48,73-77,101-103` | **Medium** — partial failure + re-run = permanently missing tracker issue; docstring claims idempotency | **Traced** (pure code path; do NOT live-test — hits the real repo) |
| F8 | `bootstrap_github.py cmd_project` re-run creates a **duplicate Project**: `gh project create` runs unconditionally (line 84) with no lookup-by-title, and `field-create` (line 92) duplicates fields. Docstring's "guarded against duplicates via … item lookups" is false for the project itself | `scripts/bootstrap_github.py:84-93` | **Medium** — a crash mid-`project` mode (rate limit at ~28 items × 4 field edits) plus re-run = two half-populated boards | **Traced** (no code path reads existing projects; report-only) |
| F9 | `export_from_notion.py write_guarded` only guards ONE direction (existing `content_type: content` vs incoming `content_type: stub`, lines 133-140). Hierarchical sub-page lesson files (line 191) and content-module READMEs contain no `content_type: stub` in the incoming text, so a re-run **unconditionally clobbers hand-edited lesson files** — contradicting the docstring "Idempotent" and CLAUDE.md's "won't overwrite content authored here" | `scripts/export_from_notion.py:133-140,184-196` | **Medium** (High impact × low likelihood — Notion is retired post-migration, but the script sits there claiming to be safe) | **Traced** (guard condition provably never true for sub-files; report-only, do NOT run) |
| F10 | Stage-3 doc claims "the agents will refuse" to draft without an approved design (`process/stages/03-draft.md:15-16`), but only `module-author.md:17-20` actually gates on approval. `quiz-writer.md:21-23` has an explicit **no-design fallback**, and `video-script-writer.md` never mentions `00-design.md` at all | `.claude/agents/quiz-writer.md:17-23`, `.claude/agents/video-script-writer.md` (whole file), vs `process/stages/03-draft.md:15` | **Low-Medium** — process promise with no enforcement in 2 of 3 drafting agents | **Traced** (text contract comparison) |
| F11 | `--course _template` prints the wrong reason: "has no 00-design.md — not opted into the pipeline" — it *has* one; it's excluded by name at line 96 before the `only` filter, so the fallback message at line 108 lies | `scripts/check_course_package.py:94-109` | **Low** | **Live-fired** (exact output recorded) |
| F12 | Duration-header **placement** never checked: `TIME_RE` is `re.MULTILINE` over the whole file, but the spec everywhere (`CLAUDE.md` rule 6, `03-draft.md:8-9`, alignment-reviewer §c) says "opens, **right under the H1**". A header buried mid-file passes CI while `/next-step` and alignment-reviewer say it "begins with" it | `scripts/check_course_package.py:28,60-65` | **Low** | **Traced** (regex semantics) |

**Non-findings — do not chase these** (checked and cleared during the wargame):
`_template/README.md` deliberately has no frontmatter, so `gen_coverage.py` correctly
ignores it (its own header comment says so); the template's own `04-quiz.md` skeleton
(`1. _ | 2. _ | …` unescaped) DOES match `ANSWER_KEY_RE`; the template's
`| **Design status** | Draft |` row DOES match `DESIGN_STATUS_RE`; the freshly-copied
template failing the time check on `[X] minutes` placeholders is **correct behavior**, not
a bug; `stage 08`'s `external_links: cypher:` key matches `/next-step` row 8 exactly;
`\d{2}-` correctly rejects 3-digit prefixes; `descriptors` check is clean (no bugs found
in `check_competency_descriptors.py` — its YAML-error, duplicate, slug-mismatch and
exemption paths all read correct).

---

## The route

### Move 0 — Re-verify ground truth (read-only)

Do: read `CLAUDE.md`, `process/PROCESS.md`, all eight `process/stages/*.md`, the five
pipeline agent files, `.claude/commands/next-step.md`, the three `scripts/check_*` /
`gen_coverage` scripts, and `modules/_template/*`. Then run:

```
git status --short                       # expect: empty
python scripts/gen_coverage.py           # expect: covered=21 gaps=22, exit 0
git diff --stat -- COVERAGE.md           # expect: no diff (or date-line only → git checkout -- COVERAGE.md)
python scripts/check_course_package.py   # expect: "Checked 0 opted-in course(s): 0 error(s), 0 warning(s).", exit 0
python scripts/check_competency_descriptors.py   # expect: "OK — …in sync.", exit 0
```

Also: `Glob modules/*/00-design.md` — expect **exactly** `modules/_template/00-design.md`.

- **Likely failure:** a second `00-design.md` hit, or nonzero baseline exit codes.
  **Cause:** a real course entered the pipeline since 2026-07-06 — the "never-run" premise
  is dead. **Counter-move:** ABORT this plan's fix phase; re-run Moves 1–4 to see which
  findings still reproduce, and re-rank with the real course as the primary evidence.
  F1–F4 fixes may now change CI results for real content — check that course by hand first.
- **Likely failure:** `git status` not clean. **Counter-move:** stop; do not create
  fixtures on top of uncommitted work. Report the dirty state.
- **Fork:** if `gen_coverage.py` output differs only in the covered/gaps counts (new
  modules landed) → proceed; the counts are not load-bearing, the exit code is.

### Move 1 — Build the scratch fixture (never touch `_template` or any real module)

Create `modules/zz-wargame-scratch/` with these five files (do NOT copy `_template`
verbatim — the placeholders `[X] minutes` would add noise errors; write these exact
contents):

1. `00-design.md` — a design doc whose overview table contains the row
   `| **Design status** | Draft |`
2. `01-survey-design-basics.md` — H1, then `**Estimated time:** 300 minutes` (probes F1:
   filename contains substring `design`, duration 3.3× the cap)
3. `02-intro.md` — H1, then `**Estimated time:** 91 minutes` (positive control: one over cap)
4. `03-scenario-bank.md` — H1, then `**Estimated time:** 45 minutes` (valid)
5. `04-quiz.md` — body contains `You need 80% (3/3) to pass.` and ends with an
   **escaped-pipe** answer key, exactly the exemplar's style:
   `1. B \| 2. C \| 3. A` (probes F2)

No mentor guide, no video script (probes the warning path). No `README.md` yet.

- Expected observation: five files exist; `git status` shows only `modules/zz-wargame-scratch/`.
- **Likely failure:** editor/tooling normalizes `\|` to `|` when writing the quiz.
  **Cause:** markdown auto-formatting. **Counter-move:** verify with a raw read that the
  backslash survived; if not, write the file via a heredoc/python one-liner.

### Move 2 — First fire, expected verbatim output

Run: `python scripts/check_course_package.py --course zz-wargame-scratch`

Expected (recorded verbatim from the wargame):

```
WARNING: zz-wargame-scratch: no mentor guide yet
WARNING: zz-wargame-scratch: no video script yet
ERROR: zz-wargame-scratch/02-intro.md: 91 minutes exceeds the 90-minute cap
ERROR: zz-wargame-scratch/04-quiz.md: no pipe-separated answer key found

Checked 1 opted-in course(s): 2 error(s), 2 warning(s).
```
Exit 1. This **proves F1 and F2 in one shot**: note what is *absent* — no error for the
300-minute `01-survey-design-basics.md` (F1), and the answer-key error fires on the
exemplar's own format (F2). Also run
`python scripts/check_course_package.py --course _template` — expect the lying message
`modules/_template has no 00-design.md — not opted into the pipeline; nothing to check.`
(exit 0) → proves F11.

- **Likely failure:** the 91-minute error missing too. **Cause:** you wrote the time
  header in a variant format (`**Estimated time**: 91`) — the regex needs the colon
  inside the bold. **Counter-move:** fix the fixture, not the conclusion; re-run.
- **Fork:** if `01-survey-design-basics.md` DOES produce an error → F1 was fixed since the
  wargame; check `git log -1 -- scripts/check_course_package.py`, drop F1 from the fix
  list, promote F7 into the top six as report-only-plus-guard (see fix list note).

### Move 3 — Second fire: the false-negative bundle

Mutate the fixture: (a) change the design row to
`| **Design status** | Aprooved, someday, maybe |`; (b) replace the threshold sentence
with `Many learners pass over this section too quickly.`; (c) unescape the answer key to
`1. B | 2. C | 3. A`; (d) delete `02-intro.md` so the substring-excluded file is the
**only** lesson.

Run the same `--course` check. Expected (recorded verbatim):

```
WARNING: zz-wargame-scratch: no numbered lesson files yet
WARNING: zz-wargame-scratch: no mentor guide yet
WARNING: zz-wargame-scratch: no video script yet

Checked 1 opted-in course(s): 0 error(s), 3 warning(s).
```
Exit **0**. Read that again: a course with a 300-minute lesson, a garbage approval line,
and a quiz with no pass threshold **passes CI clean**, while falsely warning that no
lessons exist. This is F1 (warning side) + F3 + F4 in one run.

- **Likely failure:** an error appears for the design status. **Cause:** F3 already fixed,
  or your garbage line broke the table `|`-structure so the regex missed the row entirely
  (that's the *missing line* error, a different message). **Counter-move:** check which
  message; `no '**Design status**' line found` means your fixture is malformed at the
  table level — restore the `| … | … |` shape.

### Move 4 — gen_coverage typo trap + CI trigger gap

Add `modules/zz-wargame-scratch/README.md` with frontmatter
`competencies: [Literacy Toolz]` (deliberate typo). Run `python scripts/gen_coverage.py`.

Expected: `covered=21 gaps=22` + `WARNING: zz-wargame-scratch: competency 'Literacy Toolz'
not in competencies.yaml`, **exit 1**. Then immediately `git checkout -- COVERAGE.md`
(the script writes the file before exiting 1 — that's by design for the main-branch CI
commit job, but you must not leave the mutation).

Now the F5 half that can't be run locally: read `.github/workflows/coverage.yml` — its
`on:` block has `push: branches: [main]` and **no `pull_request`**. Read
`course-package.yml` — it *does* run on `pull_request` for `modules/**`, but
`check_course_package.py` never validates competency names. Conclusion: the only check
that would have caught `Literacy Toolz` cannot run before merge. That is F5, proven by
the exit-1 local run + the trigger diff.

- RECON NEEDED (5 min, settles a scope edge): read `.github/workflows/pages.yml` and
  confirm it does not trigger on `modules/**` (expected: it's the competency-site build,
  `competencies/**` only). If it *does* watch `modules/**`, check whether a broken module
  file can fail the Pages deploy — if so, add it to the report (do not fix).

### Move 5 — Hand-trace `/next-step` stage detection (no execution needed)

Three synthetic states, walked through the table at `next-step.md:53-74` ("current stage =
first row whose done signal is NOT satisfied"):

1. **`_template` itself** (fleet overview, F6): row 1 ✓ (`00-design.md` exists), row 2 ✗
   (status line reads `Draft`) → reports "Stage 2 — Design approval" for the template.
   A human would say "that's not a course." → F6 confirmed.
2. **Lessons present, design status misspelled** (`Aproved by Jo on 2026-07-01`): row 2's
   signal is `design status line = 'Approved by …'` — a literal read says NOT satisfied →
   `/next-step` reports stage 2 even though drafting visibly happened. That is actually
   the *correct* conservative answer (the gate really isn't signed), but nothing tells the
   user the line is merely typo'd — and after the F3 fix, CI will say so. Consistent; no
   separate fix needed. Record as covered-by-F3.
3. **Quiz file present but malformed** (no threshold, no key): row 3c's signal is
   "`*-quiz.md` **exists**" → satisfied → `/next-step` advances to 3d/4 while
   (post-fix) CI is red on the quiz. Mismatch is tolerable — stage 4's alignment-reviewer
   catches it — but note in the report that `/next-step`'s 3a signal checks *content*
   (`**Estimated time:**` present) while 3c/3d check *existence only*; asymmetry, not a
   top-six fix.

### Move 6 — Apply the top-6 fixes (smallest change that works)

Order matters: F1–F4 are one file; verify together. Keep the fixture from Moves 1–4
around — it becomes the regression harness.

**Fix F1** — `check_course_package.py:35-40`, replace the substring test:

```python
def is_lesson(name):
    # 01-*.md, 02-*.md, ... but not the package's own non-lesson files
    if not re.match(r"^\d{2}-", name):
        return False
    if re.fullmatch(r"\d{2}-design\.md", name):
        return False
    return not name.endswith(("-scenario-bank.md", "-mentor-guide.md",
                              "-quiz.md", "-video-script.md"))
```
Known accepted edge: a lesson literally named `NN-pop-quiz.md` would still be excluded
(suffix match) — that's the package's reserved suffix; document, don't engineer around.

**Fix F2** — line 30, tolerate the exemplar's markdown-escaped pipes:

```python
ANSWER_KEY_RE = re.compile(r"^\s*1\.\s*\S+(\s*\\?\|\s*\d+\.\s*\S+)+", re.MULTILINE)
```

**Fix F3** — after the existing `if not m:` branch in `check_course`, validate the value:

```python
        else:
            status = m.group(1).strip()
            if status != "Draft" and not re.fullmatch(
                    r"Approved by .+ on \d{4}-\d{2}-\d{2}", status):
                errors.append(f"{slug}/00-design.md: malformed design status '{status}' "
                              f"(expected 'Draft' or 'Approved by <name> on <YYYY-MM-DD>')")
```
This encodes exactly the two shapes `process/stages/02-approve.md:33-35` prescribes.

**Fix F4** — line 31, drop the bare-word `pass` alternative:

```python
THRESHOLD_RE = re.compile(r"\b\d{1,3}\s*%|\bto pass\b", re.IGNORECASE)
```
(Template says "You need **80% (X/N)** to pass"; exemplar says "You need 80% (16/20) to
pass"; quiz-writer.md mandates stating a % — both alternatives stay satisfiable.)

**Fix F5** — `coverage.yml`: add a `pull_request` trigger with the same three paths, and
guard the commit step with `if: github.event_name == 'push'`. Also widen the concurrency
group to `coverage-${{ github.ref }}` so a PR run can't cancel the main-branch commit run.
Verify the YAML still parses: `python -c "import yaml,io; yaml.safe_load(open('.github/workflows/coverage.yml'))"`.

**Fix F6** — `next-step.md` fleet-overview step 1, one sentence: after
"`Glob` for `modules/*/00-design.md`", append: "**Skip `modules/_template/`** — it is the
package skeleton, not a course (`check_course_package.py` excludes it for the same
reason). If nothing else matches, there are no courses in the pipeline yet."

**Not fixed, report-only** (state this explicitly in your report): F7/F8
(`bootstrap_github.py`) and F9 (`export_from_notion.py`) mutate live external state and
their "smallest fix" is a design decision (skip-on-exists vs. lookup-by-title) the
maintainer should make — flag with the exact lines above; F10 is a two-line addition to
two agent prompts if the maintainer wants the gate enforced (`quiz-writer.md`,
`video-script-writer.md`: "If `00-design.md` is missing or its Design status is not
`Approved by …`, stop and say stage 1/2 must happen first"); F11/F12 are below the
top-six cut.

### Move 7 — Post-fix verification (the regression matrix)

Rebuild the fixture in its **Move-2 configuration** (escaped-pipe quiz + threshold
sentence restored + `02-intro.md` restored + design status back to `Draft`), then:

| Run | Expected after fixes | Which fix it proves |
|---|---|---|
| `check_course_package.py --course zz-wargame-scratch` (Move-2 config) | ERROR `01-survey-design-basics.md: 300 minutes exceeds…` **now present** (F1); ERROR for `02-intro.md` 91 min still present; answer-key error **gone** (F2); same 2 warnings; exit 1 | F1, F2 |
| Same, Move-3 config (garbage status, "pass over" quiz, `02-intro.md` deleted) | ERROR malformed design status (F3); ERROR no pass threshold (F4); ERROR 300-min lesson (F1); "no numbered lesson files yet" warning **gone** (F1); exit 1 | F1, F3, F4 |
| **Positive control** — mutate fixture to fully valid: status `Approved by Wargame Tester on 2026-07-06`, lesson at 90 min exactly, quiz with `You need 80% (12/15) to pass.` + unescaped key, add `05-mentor-guide.md` + `06-video-script.md` | **0 errors, 0 warnings, exit 0** | fixes reject bad packages without rejecting good ones — if this fails, revert the offending fix and report instead |
| `python scripts/check_course_package.py` (repo-wide, after fixture deleted) | `Checked 0 opted-in course(s): 0 error(s), 0 warning(s).`, exit 0 | no collateral |
| `python scripts/gen_coverage.py` then `git diff -- COVERAGE.md` | `covered=21 gaps=22`, exit 0, no diff | no collateral |
| `python scripts/check_competency_descriptors.py` | `OK — descriptors and framework are in sync.`, exit 0 | untouched, still green |

The report must show the **before** outputs (Moves 0/2/3/4, recorded above — reproduce
them, don't cite this document as the evidence) and these **after** outputs, with exit
codes for all.

### Move 8 — Cleanup

`Remove-Item -Recurse -Force modules/zz-wargame-scratch` (and any other fixture), then
`git checkout -- COVERAGE.md` if the date line churned, then `git status --short` —
expected: **only** the intended fix files:
`scripts/check_course_package.py`, `.github/workflows/coverage.yml`,
`.claude/commands/next-step.md` (and `07-bugs.md` if you update it). Anything else listed
→ you leaked a fixture; delete it before finishing.

---

## Abort conditions

1. **A real course has opted in** (second `00-design.md` hit at Move 0) → stop the fix
   phase; the never-run premise is void; re-validate F1–F4 against the real course's
   actual files before changing any regex it is subject to.
2. **Baseline runs (Move 0) don't match the recorded outputs** in a way that isn't
   explained by new modules (exit codes differ, or check_course_package finds >0 courses)
   → stop and re-recon; this plan's expected observations are stale.
3. **The positive-control fixture fails after fixes** and you cannot make it pass by
   correcting the *fixture* (i.e., the fix genuinely rejects a valid package) → revert
   that fix, keep the finding as report-only.
4. **Any step requires running `export_from_notion.py`, `bootstrap_github.py`, or a `gh`
   write command** → it doesn't; if you think it does, you've drifted — those are
   report-only findings. Never test them live.
5. **You cannot restore `git status` to fixes-only at Move 8** → do not finish/commit;
   report the residue.

## RECON NEEDED (each with the exact settling check)

- **pages.yml scope** — `Read .github/workflows/pages.yml`; settle: does its `paths:`
  include `modules/**`? Expected no; if yes, add a finding (broken module markdown could
  block the competency-site deploy) — report, don't fix.
- **`gh project item-add` idempotency** (bears on F8's blast radius) — cannot be settled
  offline and must NOT be tested against the live board. Settle by docs only:
  `gh project item-add --help` locally; if ambiguous, leave F8's wording as "project +
  fields duplicate; items unverified".
- **Whether the alignment-reviewer agent (prose contract) treats `\|` as pipe-separated**
  — unsettleable statically (it's a judgment the agent makes at runtime); mitigated
  structurally by F2 making CI and the exemplar agree. Note it; move on.
- **COVERAGE.md date churn** — `gen_coverage.py` stamps today's date; if the last
  regeneration date differs from your run date, `git diff -- COVERAGE.md` shows exactly
  one changed line. Settle per run: date-line-only diff → `git checkout -- COVERAGE.md`;
  any other diff → a module's frontmatter changed underneath you, investigate before
  proceeding.

## What "done" looks like

A report containing: the findings table (F1–F12) with file:line and severity; before/after
outputs with exit codes for all four verification commands plus the three fixture
configurations; the six applied diffs; the report-only findings (F7–F10) with their exact
lines quoted; `git status` showing fixes only, no fixture folders; and zero edits to
`modules/_template/`, any real module, `COVERAGE.md`, or anything under `competencies/`.
