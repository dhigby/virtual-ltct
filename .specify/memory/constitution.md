# LTC Curriculum and Training System Constitution

This constitution distils the hard constraints of [`INTENT.md`](../../INTENT.md) into testable
rules for spec-driven work in this repository. `INTENT.md` states the *why*; `CLAUDE.md` states
the day-to-day *how*; this document states what a spec, plan or task MUST NOT violate. The repo
holds two products — the **curriculum** (markdown courses and competency descriptors) and the
**training system** (the Moodle publisher, `moodle/`, and platform configuration) — and every
principle below applies to both unless it says otherwise.

## Core Principles

### I. Repository Is the Source of Truth

- Course content, competency descriptors and training-system configuration MUST live in this
  repository as the authoritative copy.
- Publishing to Moodle MUST be one-way. No feature may sync content or learner data from
  Moodle back into the repo.
- Generated artefacts — `COVERAGE.md`, the published competency site, the review sites, the
  published Moodle courses — MUST NOT be hand-edited; change the source and regenerate.
- `scripts/course_stage.py` MUST remain the only implementation of stage detection;
  `scripts/disclosure.py` MUST remain the only definition of the answer-key rules. A second
  implementation of either is a defect.
- Content facts live in module frontmatter; workflow state (status, priority, assignee) lives
  on the GitHub Project board. Neither may be duplicated into the other.

*Rationale:* content locked in an LMS cannot be diffed, reviewed or corrected; a generator whose
output is hand-edited becomes a liar.

### II. Portability of Content and Platform

- Course content MUST stay plain markdown, readable and editable raw without any tool.
- Nothing may bind course content to Moodle's format. The publisher MUST keep its split at a
  platform-neutral payload: `moodle_payload.py` knows courses and nothing of Moodle's API;
  `moodle_client.py`, `moodle_xml.py` and `moodle/local_ltuse/` know Moodle and nothing of
  pedagogy. Only the publisher and `local_ltuse` may know both.
- Moodle identity MUST use `idnumber` (`ltct:<slug>`, `ltct:<slug>:<source filename>`) stored
  in Moodle, never a repo state file, so that republishing updates rather than duplicates.
- Moodle configuration — plugins and their pinned versions, site settings, theme, roles and
  capabilities, course categories, cohort definitions, custom profile fields, badges and
  Report builder reports — MUST be applied by a script or declarative file under `moodle/`.
  A setting clicked into the admin UI and not captured there is not done. The test: a new
  server can be rebuilt from the repo plus a data restore.
- Learner data MUST be exportable.
- Prefer the reversible choice when two designs are otherwise equal.

*Rationale:* the markdown is the asset; any renderer or LMS is replaceable, and leaving a
platform must never mean leaving content or learner history behind.

### III. Public Repo, Private People (NON-NEGOTIABLE)

- The repository is public. It MUST NOT contain learner names, emails, enrolments, grades,
  report exports, database dumps or site backups — not even as test fixtures.
- It MUST NOT contain credentials. `MOODLE_URL`, `MOODLE_TOKEN` and similar come from the
  environment only. Because auto-commit tooling may push the working tree, secrets MUST NOT be
  written into the tree even temporarily.
- Admin tooling that handles learner data (cohort creation, bulk enrolment) runs against Moodle
  and MUST NOT write learner data into git.

*Rationale:* once committed to a public repo, personal data or a token is published and cannot
be recalled.

### IV. Disclosure Boundary Fails Closed (NON-NEGOTIABLE)

- The learner view (the `/learn/` site and every Moodle page) MUST NOT expose an answer key,
  design doc, mentor guide or video script.
- A quiz whose answer key cannot be cleanly separated MUST be withheld entirely, never partially
  stripped. No feature may optimise this into "strip what we can."
- Answer keys use exactly one marker — a `## Answer key` H2, optionally qualified and
  repeatable — and reach Moodle only inside question data, never in page HTML.
- The payload MUST be verified (`check_moodle_payload.py`) before anything leaves the machine;
  there is no `--force`. The learner-view gate (`check_learner_view.py`) MUST fail the deploy
  on a leak.
- Review sites are *unlisted, not secret* and MUST NOT be described as secret. A pilot learner
  MUST never be sent a `/review/` URL.

*Rationale:* once a page is on a server learners can reach, a disclosure failure has already
happened; enforcement removes the guessing, not the verification.

### V. CBC Framework Fidelity

- The 42 competencies in `competencies.yaml` and the five levels in `outcome-levels.yaml` are
  external givens and MUST NOT be altered by this repo.
- Competency names MUST match `competencies.yaml` verbatim; a mismatch MUST be a hard CI
  failure.
- Only CBC level vocabulary (`0 - No Competency` … `4 - Expert`) may be used — in the repo, in
  Moodle competency frameworks and in badges. The retired legacy names MUST NOT return. A
  course's `target_outcome_level` is where the learner lands (1–4, never 0), and objectives
  for level N are designed from the ladder row labelled N-1.
- Published competency URLs are citations. A change that moves a page MUST add the old path to
  `redirect_maps` in `mkdocs.yml` in the same change.
- The training system records training evidence only. It MUST NEVER award or record a CBC
  level, and no badge or certificate it issues may say "certified".

*Rationale:* the framework is not ours; drift in names, levels or URLs silently breaks coverage
and the references the CBC program relies on.

### VI. No Git, No LMS Orientation

- No contributor step may require knowledge of branches, rebases, pull requests or merge
  conflicts. Claude Code and `/work-on` do the git; after any branch operation the assistant
  says in one plain sentence what changed and where the files are.
- No learner step may require Moodle orientation. A partner learner MUST be able to log in and
  know what to do; a small team MUST be able to run cohorts and enrolment without a dedicated
  LMS administrator.
- A change that is technically better but pushes git or LMS mechanics onto a person is wrong.

*Rationale:* the people best placed to write courses are practising consultants, not software
people, and the learners are spread across many organisations and interface languages.

### VII. One Shape, Gated Stages

- Every new content course MUST follow the eight-stage pipeline in `process/PROCESS.md`
  (Design → approve → draft → alignment check → SME fact-check → internal review → pilot →
  publish). Stages are gates: no drafting before design approval, no publishing before pilot.
- Every lesson MUST follow Learning That Lasts (Connect → Content → Challenge → Change), open
  with an `**Estimated time:**` header, not exceed 90 minutes, and carry a visual.
- Review MUST be independent: at least two humans, and neither the approver nor the internal
  reviewer may be the author.
- One course per session. Per-course or per-partner special cases erode standardisation and
  MUST be justified in the spec that introduces them; the default answer is no.
- Authoring is routed through the stage's agent and how-to so AI-assisted output is consistent
  rather than personal.

*Rationale:* quality has to come from the process rather than from who happened to write the
course.

### VIII. Humility About Language Data

- Content MUST NOT depend on the consultant (or the AI) judging whether minority-language text
  is correct. The competence taught is diagnosing the tool, the data and the workflow.
- Examples MUST NOT assume an existing orthography, dictionary, corpus, font or keyboard.
- AI MUST NOT gloss, translate, judge, normalise or state facts about a named language's
  script, tone marking or character set. Real examples come from a human; where one is
  missing, a marked placeholder states what the example must show.
- Real project data MUST NOT be replaced with invented languages.

*Rationale:* a plausible-looking answer about text nobody present can read lands in front of a
learner with no way to check it.

### IX. Flat Cost, Field-Ready Delivery

- Delivery MUST NOT cost per learner, and total cost MUST stay flat as learners grow. Paid
  hosting, app plans, plugins or bolt-ons are acceptable only at a flat rate.
- Every course MUST work in the Moodle Android app and offline; pages stay light; video is
  never the only route to the content.
- Large binaries (video) MUST NOT be committed; link them from frontmatter. Screenshots and
  diagrams are committed under `modules/<slug>/assets/`, never hotlinked.

*Rationale:* the learners are a worldwide consultant body on Android devices, often offline
and on low bandwidth.

### X. Training-System Work Is Traceable and Verified

- Every training-system spec MUST cite the row(s) of [`moodle/REQUIREMENTS.md`](../../moodle/REQUIREMENTS.md)
  it delivers, and the same pull request MUST update those rows' status (built, verified or
  decided). Work that serves no row either adds one first or is out of scope.
- Any Moodle behaviour, setting or plugin a plan relies on MUST be verified on the temporary
  5.2.3+ instance before the plan depends on it — plugin compatibility with 5.2 above all. An
  unverified dependency is recorded as a research task, not assumed.
- Learner- and admin-experience work (rows #13 and #14, and any spec whose success criterion
  is "simple") MUST NOT be marked done until 2–3 real partner users — learners for learner
  UX, organisation or cohort managers for admin UX — have used it, for example at a stage-7
  pilot, and their findings are recorded.
- A change to the course content model (translated courses, row #6, or anything else that
  touches course layout, `course_stage.py` or the publisher) MUST have an approved design
  before any build.
- A spec that adds recurring operations or cost (hosting, backups, app plan, a bolt-on
  service) MUST name that burden and who carries it. While the Moodle operator is undecided,
  such a spec cannot claim its operations are covered.
- Verification against a Moodle instance MUST use test accounts only. Tests, fixtures and
  logs committed to the repo MUST NOT contain real learner data (Principle III).

*Rationale:* `REQUIREMENTS.md` is an analysis, not a commitment; "achievable in Moodle" only
becomes true for us once it is built, verified on our version and proven simple by the people
it is for.

## Platform & Delivery Constraints

- **Moodle core first.** Reach for core Moodle, then a maintained free plugin, then our own
  code, then a bolt-on system — in that order. Every plugin is an upgrade liability; every
  bolt-on is another system to operate.
- **Never modify Moodle core.** Configure and extend it; do not fork it or rebuild a feature
  core or a maintained plugin already provides.
- **Pin every plugin** to the release it was verified against, as `local_ltuse` does.
- **One instance serves every partner we host.** Today that is `ltuse.net`. Partners on it are
  separated by course category, cohort, role and profile field; no spec may stand up a
  separate instance or a bespoke role set per organisation. A partner that runs its own Moodle
  is a second publish target, not a second instance of ours: nothing may hard-code
  `ltuse.net`, and the server always comes from `MOODLE_URL`.
- **Never run `mkdocs gh-deploy` locally.** Deploying the Pages site is CI's job.
- The competency site builds `strict: true`; a broken internal link fails the build.

## Development Workflow & Quality Gates

- **Branching.** Course work happens on `course/<slug>`, derived from the slug via `/work-on`,
  never invented. `modules/` is never edited on `main`. Training-system work uses its own
  feature branch.
- **CI gates that MUST pass before merge:** `gen_coverage.py` (unknown competency names),
  `check_competency_descriptors.py`, `check_course_package.py` for pipeline courses,
  `quiz_parse.py --check-all`, `check_learner_view.py`, and a successful review-site build for
  any changed course.
- **Coverage changes** are made in frontmatter `competencies:` and committed together with the
  regenerated `COVERAGE.md`.
- **Specs and plans** produced by Spec Kit MUST include a Constitution Check that names each
  principle the feature touches and states how it complies. A plan that needs an exception
  records it in its Complexity Tracking with the simpler alternative it rejected.
- **Backfilled legacy courses** follow `process/backfill.md`: faithful import, grandfathered
  from the full package, and never published to Moodle as an unstructured README.

## Governance

- This constitution governs Spec Kit work (`/speckit-specify`, `/speckit-plan`,
  `/speckit-tasks`, `/speckit-implement`) and every review of it. `INTENT.md` remains the
  statement of purpose; `CLAUDE.md` remains the runtime guidance. If this document and
  `INTENT.md` disagree, the disagreement is raised with the maintainer and both are amended in
  the same pull request — never routed around.
- **Amendments** are made by pull request, approved by the maintainer, and state which
  principles change and why. An amendment that follows from a new `INTENT.md` decision cites
  that decision's date.
- **Versioning** follows semantic versioning: MAJOR for removing or redefining a principle;
  MINOR for adding a principle or section, or materially expanding guidance; PATCH for
  clarifications and wording.
- **Compliance review.** Every pull request reviewer checks the change against the principles
  it touches; NON-NEGOTIABLE principles (III, IV) admit no exception. Complexity or a special
  case must be justified in writing, in the spec or the PR.

**Version**: 1.0.0 | **Ratified**: 2026-09-30 | **Last Amended**: 2026-09-30
