# Data Model: Assignments and peer review in courses

Two kinds of data. The **authored** data lives in the repo and is published one way. The **learner** data lives only in Moodle and is never read by any script here (Principles I and III). Field-level shapes are in the contracts; this file names the entities, how they relate and the rules that bind them.

```text
Course package (modules/<slug>/)
 ├── Assignment file 0..n ──parse──► Assignment ──publish──► Assign | Workshop  (Moodle)
 │                                     ├── Criterion 1..12 ──► guide criterion | workshop aspect
 │                                     └── Mentor-only text  ──► guide marker notes + Mentor-notes page (hidden)
 └── (every course) ──────────────────────────────publish──► Course discussion (forum)
moodle/site/course-discussions.yaml ──► Discussion sharing ──► forum group mode   [superseded 2026-10-02: file retired by spec 002 R14; NOGROUPS in every course]

Moodle only:  Submission · Feedback (guide grade + comments) · Peer allocation · Peer assessment · Post
```

## Assignment file (authored)

One `NN-<topic>-assignment.md` in a course folder. Grammar: [contracts/assignment-file.md](contracts/assignment-file.md).

- **Rules**:
  - None may exist before the design decision is recorded (R8).
  - Exactly one H1 `# Assignment: …`.
  - `**Estimated time:**` is 1–90.
  - It passes `assignment_parse.py`, or it is withheld whole.
- **Relationships**: it belongs to one course; it may serve one or more design objectives (checked at stage 4); its images are under the course's `assets/`.

## Assignment (parsed)

The parse result: title, minutes, completion, review, submit, offline, peers, brief, criteria, model answer, extra mentor text.

- **Rules**:
  - `completion` defaults to `optional` (FR-008a).
  - `review = peer` requires `peers` 1–5.
  - `submit.file` requires `max_mb`.
  - Criterion names are unique.
- **States**:
  - **parsed**: it publishes.
  - **withheld**: the parse raised, or the strip returned `ok = False`. It publishes only a placeholder, and the publish refuses without `--allow-withheld`.

## Criterion (authored)

A name, points (1–100), learner wording and optional grading notes.

- **Rules**:
  - The name is the Moodle shortname and the identity key across republishes (R7).
  - Grading notes must match a criterion by exact name.
  - No criterion may require judging minority-language text (FR-014, checked at stage 4).

## Mentor-only text (authored)

Every block under `## Mentor only…`, which holds grading notes, the model answer and extra notes.

- **Rules**:
  - It is defined by `disclosure.MENTOR_ONLY_RE` alone.
  - It may reach Moodle only as guide marker notes or a hidden mentor-notes page.
  - Each distinctive line is asserted absent from every learner-visible field before publish (payload check 5).

## Course discussion (published)

One forum per published course. Identity: `ltct:<slug>:discussion`.

- **Rules**:
  - Every publishable course has one, backfilled courses included (FR-015).
  - Its name and intro are set on create only.
  - Its posts are never read or written by the publisher or `site_config.py`.
- **Group mode**: ~~`SEPARATEGROUPS` unless the course is listed `shared` in `course-discussions.yaml` (then `VISIBLEGROUPS`). `groupingid = 0` relies on spec 002's invariant (below).~~ *(Since 2026-10-02: `NOGROUPS` in every course, `groupingid = 0` (spec 002 R14; `ensure_discussion::wanted_groupmode()`).)*

## Discussion sharing (declared)

*(Retired 2026-10-02 by spec 002 R14: there is nothing to share or separate, and `scripts/site_config.py` refuses `course-discussions.yaml`. Re-plan input 5.)*

An entry `{slug, why}` in `moodle/site/course-discussions.yaml`.

- **Rules**: only the maintainer edits it; the default is absent, meaning separated. Drift reports a live mismatch.

## Organisation group and cohort group (from spec 002; consumed here)

*(Withdrawn by spec 002 on 2026-10-02: shared courses have no organisation or cohort groups and stay in group mode 0 (002 FR-011). The re-plan adopts spec 008's mentor groups, `ltct:mentorgroup:<mentor id>`, as the assessor scope (008 plan decision 3) and drops the invariant below. Re-plan inputs 1 and 4.)*

Groups inside a course. Group idnumbers are `ltct:org:<key>` and `ltct:cohort:<key>`. Each cohort group belongs to exactly one organisation.

- **Invariant this spec depends on**: every group in a course is within exactly one organisation. Spec 002's plan must guarantee it, because the forum's `groupingid = 0` and the allocator's "never across organisations" both rest on it. If spec 002 settles on other idnumbers, the allocator and this file change with it.

## Course mentor (Moodle role assignment; consumed here)

The core `teacher` role, enrolled in the course and placed in ~~the learner's cohort group (R3)~~ *(2026-10-05: their own mentor group, "Mentor group `<n>`", idnumber `ltct:mentorgroup:<mentor id>`, holding them and the learners they assess; enrolled, grouped and removed by spec 008's course-mentor sync (008 research R10, plan decisions 2 and 3), on since 2026-10-05)*. It can grade only its groups in an activity set to separate groups; in the course itself, which stays in group mode 0, its defaults reach every learner (accepted, Doug, 2026-10-05; re-plan input 3). It is not the spec 003 user-context mentor relationship, which it complements. Who holds it is recorded only in Moodle.

## Learner data (Moodle only, never in the repo)

| Entity | Created by | Never |
|---|---|---|
| Submission (text and/or files) | learner, web or app, offline allowed | read, exported or overwritten by any script here |
| Feedback: guide fillings, grade, comments | Course mentor, web | rescaled or re-keyed by a republish (R7) |
| Peer allocation | `workshopallocation_orgcohort`, or a mentor's "Assess" | made across organisations |
| Peer assessment | learner, anonymous to peers (FR-012a) | shown with reviewer names to learners |
| Discussion post | learner or mentor | touched by a publish |

All of it is exportable through core privacy and export (spec 001's #16 baseline, FR-018).

## State transitions

**Published assignment (assign)**, on each publish:

```text
absent ──publish──► created (visible to course, which is itself created hidden)
created ──republish, brief/text changed──► updated in place (submissions, grades intact)
created ──republish, criteria structure changed, has grades──► REFUSED (unless --allow-criteria-change)
created ──republish, completion changed──► updated + completion recalculated (completionunlocked)
created ──source removed──► hidden (never deleted)
```

**Published workshop**, phase by phase. Phases are moved only by the mentor and never by a publish:

```text
setup ─mentor─► submission ─mentor or submissionend─► assessment ─► evaluation ─mentor─► closed
                                   │                                                    │
                                   └── orgcohort allocates on entry (autoallocate)      └── grades → gradebook → completion
```
