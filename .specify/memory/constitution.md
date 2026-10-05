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
- Moodle identity MUST use `idnumber` (`ltct:<slug>`, `ltct:<slug>:<file number>`) stored
  in Moodle, never a repo state file, so that republishing updates rather than duplicates.
- Moodle configuration — plugins and their pinned versions, site settings, theme, roles and
  capabilities, course categories, cohort definitions, custom profile fields, badges and
  Report builder reports — MUST be applied by a script or declarative file under `moodle/`.
  A setting clicked into the admin UI and not captured there is not done. The test: a new
  server can be rebuilt from the repo plus a data restore.
- Learner data MUST be exportable.
- Configuration whose publication would identify at-risk people (the country-to-Area map) is
  held as Moodle data, not declared in the repo. It is recovered by the data restore, so
  backups MUST include it.
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
- An enrolment request is learner data from the moment it is made, even when the person has no
  account, and MUST NOT be written into the repo.
- The repo MUST NOT record which countries belong to an organisation's declared region (Area),
  which region any person belongs to, or which organisations, regions or people are at risk.
  This covers its history, test fixtures, logs, verification evidence, and GitHub issues, pull
  requests and comments. The names of an organisation's declared regions are not such a record.

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
  MUST be justified in the spec that introduces them; the default answer is no. The
  organisation-only course is not one: it is a uniform variant open to every organisation.
  Nor is declaring an organisation region by region (SIL by Area): each region is an ordinary
  organisation entry of the one shape. Another organisation could be declared the same way by a
  reviewed change.
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

### XI. Every Change to Moodle Survives an Upgrade

- Upstream Moodle security and feature releases MUST be applicable without re-doing our work.
  Every change we make to Moodle's behaviour MUST therefore take one of three forms, each of
  which an upgrade carries forward:
  1. **configuration** applied from `moodle/` (Principle II);
  2. **a maintained third-party plugin**, pinned to a verified release;
  3. **our own plugin**, built only on Moodle's supported extension points — a plugin type,
     hooks and callbacks, events, web services, scheduled tasks, or a child theme's templates
     and renderers.
- Vendored code MUST NOT be edited: Moodle core, its bundled plugins and themes (Boost
  included), and third-party plugins. No patch may be applied to them at install or deploy
  time. If one needs fixing, the fix goes upstream, or we override it through an extension
  point. A look-and-feel change is a child theme or Boost's own settings, captured as
  configuration.
- We MUST NOT create or maintain a fork of Moodle. The most we may do is adopt a Moodle
  distribution someone else maintains, such as IOMAD, and only as a platform decision
  recorded in `INTENT.md`. That distribution must track upstream security releases, be free
  or flat-cost (Principle IX), and our configuration and plugins must still install on it
  unchanged.
- Our plugins MUST call Moodle's public APIs rather than write to tables owned by core or by
  another component. A direct write, or a raw read of another component's internal schema,
  is allowed only where no API exists, and each one MUST be listed in the plugin's README
  with its reason, so an upgrade review knows where to look first. Reading stable core
  tables such as `course_modules` by indexed columns is not an exception.
- Our plugins MUST declare `$plugin->requires` (the oldest Moodle release verified) and
  `$plugin->supported` (the branches verified). They MUST NOT hold back security releases
  within a supported branch. `$plugin->incompatible` is set only for a branch where
  breakage has been observed, never as a precaution; the test-instance upgrade check
  (Principle X) is what catches the untested case.
- Supporting a new Moodle branch means re-verifying on the test instance, with a test
  publish, and then raising `supported` in the same change that records the result.
- Before writing, reviewing or upgrading code against a Moodle API, look the API up; don't
  write it from memory. Check current developer documentation first (Context7's Moodle 5.2
  API guides, plus core's `UPGRADING.md` for what changed), then confirm the signature in
  upstream source on the branch we run (`MOODLE_<branch>_STABLE`). The documentation alone
  is not enough: it indexes Moodle's `main` branch, and it missed APIs that the source shows.
  A plan's research tasks record which API was confirmed and where.
- We run **open-source Moodle LMS, self-hosted**. Documentation and search results mix in
  features that exist only in Moodle Workplace, MoodleCloud, or the paid Moodle app plans.
  A feature counts as available only if it is in core source on our branch, or in a free
  plugin from the Moodle plugins directory that has been verified on our instance. Watch
  for shared names: Workplace's paid "Programs" is not the free `tool_muprog` plugin.

*Rationale:* a public site holding people's data has to take security releases promptly, and
our team does not maintain Moodle. Every edit to vendored code, every private fork and every
undeclared write into Moodle's tables turns routine upgrades into a porting project, and that
is exactly the kind of work this team cannot absorb.

## Platform & Delivery Constraints

- **Moodle core first.** Reach for core Moodle, then a maintained free plugin, then our own
  code, then a bolt-on system — in that order. Every plugin is an upgrade liability; every
  bolt-on is another system to operate.
- **Never modify Moodle core.** Configure and extend it; do not fork it or rebuild a feature
  core or a maintained plugin already provides. Principle XI states what "extend" allows.
- **Pin every third-party plugin** to the release it was verified against. Our own plugins
  declare the Moodle branches they were verified on (Principle XI). A pin stops an unreviewed
  *plugin* update; it is never a reason to hold back a Moodle security release.
- **One instance serves every partner we host.** Today that is `ltuse.net`. Partners on it are
  identified and scoped by course category, cohort, role and profile field. An organisation,
  in these rules, is one entry in `moodle/site/organisations.yaml`; a partner may be declared as
  several entries (SIL by Area), and an organisation-only course then belongs to one of them; no spec may stand
  up a separate instance or a bespoke role set per organisation. A partner that runs its own
  Moodle is a second publish target, not a second instance of ours: nothing may hard-code
  `ltuse.net`, and the server always comes from `MOODLE_URL`.
  - Shared delivery courses MUST be open across organisations: no spec may separate
    organisations by groups inside a shared delivery course.
  - Organisation managers MUST be scoped by membership of their organisation's managers cohort,
    through reporting and our own management pages, never by course groups. An Area Language
    Technology Coordinator is such a manager: the person responsible for developing consultants
    for an entry, which is an Area for SIL and SIL partners. There is no second kind of scope.
  - The organisation-only course is the one mechanism for keeping a course to one
    organisation. The maintainer declares it in `moodle/site/`, never in course content, and it
    restricts enrolment, not content: the course stays in the public repo.
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

**Version**: 2.1.1 | **Ratified**: 2026-09-30 | **Last Amended**: 2026-10-05

*2.1.1 — wording only: Principle II's example of at-risk configuration no longer names
organisation protection minimums, which spec 016's scope review removed (Doug, 2026-10-05
(scope review), change 9). PATCH, because no rule changes. Doug confirmed the PATCH level on
2026-10-05.*

*2.1.0 — follows the parts of the 2026-10-03 `INTENT.md` decision "Learners ask; Area LT
Coordinators add them" that Doug decided: an Area is an organisation entry and replaces SIL as
the organisation of record, ALTCs are its managers, and the country-to-Area map stays out of the
repo. Principle III gains rules for enrolment requests and for at-risk geography; Principle II
holds such configuration as Moodle data; Platform & Delivery defines an organisation as one
declared entry, and Principle VII says a region-by-region declaration is not a special case.
MINOR, because the definition states what "organisation" now means rather than redefining a
principle. Doug confirmed the amendment and its version level on 2026-10-04.*

*2.0.0 — redefines how partners share the one instance, following the 2026-10-02 `INTENT.md`
decision "Courses are open across organisations": partners are identified and scoped, no
longer separated, by category, cohort, role and profile field; shared courses are open;
managers are scoped by their managers cohort; the organisation-only course is the one declared
exception (Platform & Delivery, Principle VII). Also records the maintainer's 2026-10-02 wording
change to Principle II's `idnumber` rule (`<source filename>` became `<file number>`).*

*1.1.0 — adds Principle XI (every change to Moodle survives an upgrade), following the
2026-09-30 `INTENT.md` decision "Upgrades are never a porting project". Also narrows "Pin every
plugin" to third-party plugins.*
