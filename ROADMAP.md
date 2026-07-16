# Coverage roadmap

The goal is a course for every competency in [`competencies.yaml`](competencies.yaml).
[`COVERAGE.md`](COVERAGE.md) shows the current state (auto-generated); this file is where we
**commit** to closing gaps. It's hand-edited.

## How this is used — the planning ritual

Once a month (or quarterly), per [`process/PROCESS.md`](process/PROCESS.md):

1. A maintainer runs the `coverage-strategist` agent for a ranked shortlist.
2. The team commits to **1–3** items — no more than we'll actually finish.
3. For each: open a [Course production tracker](.github/ISSUE_TEMPLATE/course-production.yml)
   issue, add it to the board (`Not started`, set Priority), and fill in its row below
   (planned course slug, issue #, target quarter).

Don't pre-open 22 tracker issues — empty rows are just board noise. Commit as you go.

## Gap competencies (22)

_As of the last COVERAGE.md generation. "Planned course" / "Tracker" / "Target" stay blank
until the team commits to one in a planning session._

### Technology Domain

| Gap competency | Planned course | Tracker | Target |
| --- | --- | --- | --- |
| Phonetic Tools | | | |
| Phonology Tools | | | |
| Recording Tools | | | |
| Language Documentation | | | |
| Lexical Import | | | |
| Software Development | | | |
| Digital Vitality | | | |
| Computational and Corpus Linguistics | | | |
| Artificial Intelligence (AI) Tools | | | |

### Core

| Gap competency | Planned course | Tracker | Target |
| --- | --- | --- | --- |
| Program Design and Engagement | | | |
| Professional Networking | | | |
| Technology for Consulting | | | |
| Language Use | | | |
| Scholarship and Documentation | | | |
| Interpersonal Skills | | | |

### Professional

| Gap competency | Planned course | Tracker | Target |
| --- | --- | --- | --- |
| English Language | | | |
| Community of Practice Involvement | | | |

### Education — needs a team scoping conversation first

The entire Education category is uncovered. Before authoring, the team should decide what
these courses even are (audience, scope, whether some merge) — this isn't a pick-one-and-go
like the others.

| Gap competency | Planned course | Tracker | Target |
| --- | --- | --- | --- |
| Translation | | | |
| Literacy or MLE | | | |
| Scripture Engagement | | | |
| General | | | |
| Linguistics | | | |

## Backlog — known follow-ups (not scheduled)

- **Backfill the legacy courses** — importing the ~23 Cypher-delivered courses into the repo
  as the source of truth. Tracked separately in [`BACKFILL.md`](BACKFILL.md).
- **Reconcile board status after the status expansion** — run the `migration-reconciler`
  agent so Cypher-delivered courses read `Online` instead of stale `Not started`. Do this
  *after* the [board status expansion](process/board-admin.md) so it reconciles into the new
  vocabulary.
- **Pilot the new pipeline on one existing course** — take a nearly-complete course (best
  candidate: `coretech-computer-hardware`) through all eight stages retroactively as the
  worked example.
- **Reduce permission prompts** — optionally add a `.claude/settings.json` with a read-only
  `gh` allowlist.
