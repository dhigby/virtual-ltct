# Contract: `scripts/ltct_admin.py`

The site team's command. Python 3.12, standard library plus PyYAML (already in `publish-requirements.txt`). It talks to Moodle only through the `ltuse_admin` service (contracts/admin-service.md).

## Environment

| Variable | Required | Use |
|---|---|---|
| `MOODLE_URL` | yes | The server. Never hard-coded (constitution, Platform). |
| `MOODLE_ADMIN_TOKEN` | yes | The operator's own `ltuse_admin` token (research R12). Distinct from `MOODLE_TOKEN`; if the two are equal the tool refuses. |

A missing variable stops the tool before any call, naming the variable, never printing a value.

## Global rules

- **Paths.** Every file argument (`<file>`, `--out`) is refused if it, or its nearest existing ancestor, is inside any git working tree (a `.git` directory or file in any ancestor) or under the repo root. The check is pure Python, needs no `git`, and refuses if it cannot run (research R14). The refusal suggests `~/ltct-private/` and, for an input inside this repository, says whether git already has it.
- **Two steps.** A changing command without `--apply` previews and prints a confirmation code computed over each row's normalised inputs and outcome class, never the masked display (research R15). `--apply --confirm <code>` re-reads the file, previews again, recomputes the code, and refuses on a mismatch. It then applies row by row, sending each row's fresh outcome. A row already applied reports `already done`; a row whose outcome moved off its path is refused. Nothing is stored between the two runs.
- **Production gate.** On a shared-course enrolment, or an intake with courses, while `check` reports 016 absent or not ready, the tool prints 002 R13's reminder (research R16).
- **`--site-dir <path>`** (hidden, as in `site_config.py`): read organisation keys from a copy of `moodle/site/` outside the repo, for instance checks with fixture organisations. The repo's own declaration is never edited for a check.
- **People are masked** unless `--show-people` (research R14).
- **No files are written** except by `--out` and `template`; never a log.
- **Exit codes**: 0 done, already done, or preview shown; 1 refused or some rows not applied; 2 usage or configuration error.

## Commands

| Command | Changes Moodle | What it does | FR |
|---|---|---|---|
| `check` | no | Connects, names the site, release and user, confirms the service's functions are present and the settings in research R13 are right. | FR-009, FR-011 |
| `list organisations \| cohorts \| courses [--org K]` | no | Lists declared organisation keys (from the repo) and the cohorts and `ltct:` courses in Moodle the operator may name. | FR-011 |
| `template --kind intake\|move\|mentors\|course-mentors\|managers\|suspension --out <path>` | no | Writes a blank file with headers, outside the repo. | US4, FR-011 |
| `intake <file> [--apply --confirm C]` | yes | Brings on new learners (data-model §1). | FR-001–FR-004, FR-019 |
| `enrol course --cohort I --course I [--apply --confirm C]` | yes | Cohort sync into one course (research R7). | FR-005 |
| `enrol pathway --cohort I --pathway P [--apply --confirm C]` | yes | Cohort sync into every course of a pathway, kept in step (research R11). | FR-005 |
| `enrol mirror --from K1 --to K2 [--apply --confirm C]` | yes | Enrols K2's cohort in every shared course K1's is in (research R8). | FR-015 |
| `unenrol --cohort I --course I [--apply --confirm C]` | yes | Disables that cohort's instance; history kept. | FR-005, FR-006 |
| `suspend <file or --email E> [--apply --confirm C]` / `reactivate …` | yes | research R6. | FR-006 |
| `move <file> [--apply --confirm C]` | yes | Counted dry run, then organisation change (research R8). | FR-006, FR-015 |
| `managers <file> [--apply --confirm C]` | yes | Managers and mentors cohort membership (research R9). | FR-016 |
| `mentors assign <file> [--apply --confirm C]` | yes | Bulk mentor relationships. | FR-017 |
| `mentors end --mentor E [--apply --confirm C]` | yes | Ends all of one mentor's relationships. | FR-017 |
| `course-mentors <file> [--remove] [--apply --confirm C]` | yes | Records or removes one-course and cohort mentors; the sync does the enrolling (research R10). | FR-018 |
| `summary --org K [--out <path>]` | no | Cohort membership and enrolments for one organisation: counts, and masked rows unless `--show-people`. | FR-007 |

`--org` takes a declared key; `--cohort` and `--course` take idnumbers (`ltct:org:<key>`, `ltct:<slug>`). The tool never accepts a Moodle database id from the operator.

## Output shape (preview)

```
Intake preview: 30 rows from intake-2026-10.csv
  new                 27
  unchanged            2
  flagged_other_org    1   row 14  a***@example.org  is already in fixture-north; use "move" if this is right
Nothing has been sent to Moodle.
To apply exactly this:  python scripts/ltct_admin.py intake <file> --apply --confirm 3f9a0c21be
```

Applied output repeats the counts with `done` / `failed` per outcome and, for any failed row, the row number and Moodle's reason, never a stack trace.

## Tests (`tests/test_ltct_admin.py`, pytest)

Offline validation of every file kind. The path guard:
- a folder holding a `.git` directory is refused, and so is one holding a `.git` file (a worktree);
- a missing subfolder of a git tree is refused;
- with `git` absent from PATH the guard still refuses;
- a plain temp dir is accepted.

Masking. The confirmation code:
- it is stable across runs;
- it changes when any input column changes, including one that does not change the outcome;
- it does not change with `--show-people`.

Resume:
- after a simulated partial apply, re-running with the same code finishes the remaining rows and reports `already done` for the rest, with exit 0;
- a lost-response retry reports `already done`;
- a row that became `flagged_*` is refused.

Also: `--apply` with a stale code is refused; equal tokens are refused; a preview's file-level refusal prints no code; exit codes. Moodle is a `FakeClient` as in `test_publish_moodle.py`. Every address is `@example.org`, every key `fixture-*`.
