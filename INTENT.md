# Intent: the LTC curriculum and training system

**Author:** Doug Higby (maintainer) · **Status:** current — drafted with Claude and corrected by Doug, 2026-09-17; revised 2026-09-30 when the repo took on the training system. Lines marked _(assumption)_ are still unconfirmed.

> **What this file is.** The _why_ behind this repo — the problem it exists to solve, who it serves, what constrains it, and what is still undecided. `README.md` says what's here and `CLAUDE.md` says how to work here; this says why both are shaped the way they are. When a rule doesn't cover the case in front of you — a new feature, a script, a process change, a Moodle setting — decide from this file rather than guessing. If a decision contradicts something here, the contradiction is the thing to raise, not to route around. This is intent for **the repository and its two products** — the curriculum and the training system that delivers it — not for any one course; a course's own intent lives in its `00-design.md`.

## Problem

We are building training for language technology consultants, and we need it somewhere people can take it from anywhere in the world, working alongside a mentor who helps them succeed. Several things were blocking that:

- **Everyone built courses differently.** Each person creating content worked in their own way — different structure, different depth, different notion of what "finished" meant. Nothing made two courses resemble each other, and nothing made a course reviewable by someone who hadn't written it.
- **Everyone was reaching for AI, and nobody was getting consistent results from it.** The problem was never access to AI. It was that each author improvised their own way of using it, so output quality swung wildly from person to person and course to course. Nothing captured how to use it well for _this_ kind of content, repeatably.
- **Coverage was invisible.** The CBC framework defines 42 competencies an LTC is certified against. Nothing could answer "which of those do we actually train?" — so gaps surfaced anecdotally rather than systematically, and there was no basis for deciding what to build next. (At migration: 21 covered, 22 uncovered, with the entire Education category empty.)
- **There was no delivery platform we could live with.** Cypher for Business was the only option, and it charges per learner: every person taking a course must either pay to enroll or have us pay on their behalf. For a consultant body spread worldwide, that is not workable. _(Resolved 2026-09-22 — see **Decisions**.)_
- **Content locked in an LMS or a PDF can't be improved.** It can't be diffed, reviewed or incrementally corrected. Fixing a factual error meant finding whoever owned the file.
- **The people best placed to write courses are not software people.** The subject-matter expertise lives with practising consultants. Any process that demands fluency in git, branches or pull requests excludes exactly the people it needs.
- **A course alone doesn't make a consultant.** Learners need to stay connected after a course ends, reach a mentor over months rather than minutes, follow a pathway built around their role or the competency they are working toward, and be organised by partner organisation, country or cohort. No platform we could afford offered that, and none we could afford would let us shape it.
- **Our learners are hard to reach.** They work on Android phones and tablets, often offline and on low bandwidth, across many organisations and many interface languages. A system designed for a desktop on office broadband fails them quietly.

## Proposed outcome

A single repository that is the **source of truth** for two products: the **curriculum**, and the **training system** that delivers it.

### A. The curriculum

1. **Every course has the same shape.** One package, one set of stages, one definition of done — so quality comes from the process rather than from who happened to write it, and anyone on the team can pick up anyone else's course.
2. **AI-assisted authoring produces consistent results.** The agents, the stage how-tos and the `training-content` skill exist to make good AI output repeatable instead of personal. This repo is the harness that turns "everyone is using AI somehow" into "everyone gets the same standard of draft."
3. **Coverage is a fact, not a claim — and it drives prioritisation.** Every course declares its competencies in frontmatter; `COVERAGE.md` is generated from that, never asserted by hand. It is what tells us which competencies remain gaps and therefore what to build next, until there is a course for every competency in `competencies.yaml`.
4. **The published competency site is the reference for the CBC program.** Students being evaluated on a competency are pointed here from the CBC modules themselves, to see what the competency means, what moving up a level takes, and where they can go to learn it. This is a primary purpose of the site, not a by-product of authoring.
5. **Content stays portable, and so does learner data.** Courses are plain markdown that can be published anywhere; whatever platform we deliver on, the content does not have to be rewritten to move. Learner, enrolment, completion and participation data must likewise be exportable, so leaving a platform never means leaving our learners' history behind.
6. **Learners are supported by a mentor, not left alone with a video.** The mentor guide and scenario bank are part of every package for that reason.
7. **A non-technical contributor can do the whole job.** Claude Code drives the pipeline; `/work-on` handles branches so no contributor types a git command or wonders where their files went. Anything that pushes git mechanics onto a contributor is a defect.
8. **Legacy content comes home.** Courses delivered in Cypher are backfilled into the repo so that the repo — not the LMS — is where content lives and improves.

### B. The training system

1. **One instance serves every partner we host.** A single Moodle site — today, `ltuse.net` — holds every partner organisation whose learners we host, organised by course category, cohort and profile field. We do not stand up a separate instance for each organisation. A partner may one day choose to run its own Moodle (see **Open questions**); that is their platform, not a second one of ours.
2. **Learning continues beyond a course.** Community spaces, peer discussion and mentor relationships persist after a course ends and are not tied to any one enrolment.
3. **Courses add up to pathways.** Courses are strung into role- and skill-based pathways mapped to the CBC framework, so a learner can see where they are and what comes next.
4. **It is simple on both sides.** A partner learner can log in and know what to do without orientation, and a small team can create cohorts, enrol learners and run programs without a dedicated LMS administrator.
5. **The platform is rebuildable from the repo.** Plugins, settings, theme and roles are applied by scripts or declarative files under `moodle/`, so a new server can be stood up from the repo plus a data restore — the platform as portable as the content. A setting that exists only because someone clicked it into the admin UI is not done.

## Affected users and systems

**People** fall into two groups. Roles are hats, not headcount.

**Contributors** — around ten, across our department and one partner organisation:

| Who | What they need from this repo |
| --- | --- |
| **Department staff (language technology use)** | To author, revise and test courses in a range of roles, without learning git. Practising consultants, not developers. |
| **Seed Company (external partner)** | To review existing courses, possibly to author their own content here, and to enrol, follow and manage their own learners in the training system. An outside organisation working in the same repo and on the same platform. |
| **Design approver, internal reviewer, pilot coordinator, publisher** | An unambiguous "it's your turn, here's what to check." |
| **Maintainer (currently Doug)** | Board admin, merge rights, and tooling that doesn't need babysitting. |

**Learners and the people who support them** — hundreds now, thousands in time:

| Who | What they need from the training system |
| --- | --- |
| **Language technology consultants worldwide** | Courses and pathways that move them up the CBC ladder — whether their work advances Bible translation or linguistics and literacy in minority languages. The reason the whole thing exists. |
| **Partner organisations' learners** | To find a course, ask to take it, be added by the coordinator responsible for them, learn alongside consultants from every organisation, and find what to do next without help. |
| **Area Language Technology Coordinators (ALTCs)** | SIL's coordinators for developing consultants in each Area; other organisations use other titles for the same job, and this one is used for all of them. To receive the course requests of people in their Area, add those people as learners, put them together into cohorts, and keep a learner's identity secret when asked, with no LMS administration knowledge. In the training system an ALTC is the organisation manager of the entry they are responsible for: an Area for SIL and SIL partners, the whole organisation elsewhere (2026-10-03). |
| **Translation teams** | Training on the tools they use every day, at their level, on the devices and connections they actually have. |
| **Mentors** | Material that supports guiding a learner, and — as Moodle users — a way to see their learners' progress and stay in contact with them over time. |
| **Organisation and cohort managers** | To enrol their own people, manage them and follow their progress, without seeing or managing anyone else's people. The limit is on what managers can reach; inside a shared course, learners see each other. |
| **Pilot learners** | To take a course before it is published — on Moodle, without the answer key. |
| **CBC students** | A public competency site they are pointed to from the CBC modules, showing what each competency means and where to go learn it. |
| **The Moodle operator** | Upgrades, backups, monitoring and support for a live system. _Nobody holds this role yet — see **Open questions**._ |

**Systems:**

- **This GitHub repo** — source of truth for course content, competency descriptors, and the training system's code and configuration. Never for learner data.
- **The "LTC Training Modules" Project board** — source of truth for _workflow state_ only. The split is deliberate: content facts in frontmatter, live state on the board, so the two cannot drift into disagreeing.
- **GitHub Pages** — the competency site (`competencies.languagetechnology.org`) plus the per-course reviewer and learner views.
- **Moodle (self-hosted, 5.2)** — the training system, and the only place learner data lives. Courses are published there from this repo by `/publish-to-moodle`, one-way: the repo stays the source of truth for content, and anything edited in Moodle is overwritten by the next publish. Around the published courses it carries the plugins, theme, roles, cohorts, community spaces and pathways that make it a training system rather than a course shelf; [`moodle/REQUIREMENTS.md`](moodle/REQUIREMENTS.md) rates each requirement it must meet. It costs nothing per learner, and its Android app lets a consultant take a course offline in the field. A temporary instance comes first, then a host that can serve many learners at once; the `idnumber` scheme in [`moodle/local_ltuse/`](moodle/local_ltuse/README.md) makes moving the _content_ a re-publish, but learner data moves as a database restore.
- **The Moodle app** — the Android/iOS client and the route to offline learning. Its push notifications run through Moodle HQ's app plans; the free plan covers 50 active devices a month, so notification reach at scale is a cost decision.
- **Community platform** — undecided: a standing community course inside Moodle, or a bolt-on such as Discourse or Matrix signed in through Moodle. See **Open questions**.
- **Cypher for Business** — where the ~23 legacy courses were delivered, and now winding down. Nothing new is published there. Those courses keep their `cypher:` link and stay `Online`; bringing their content home is the [backfill](process/backfill.md) workstream.
- **Claude Code** — the working environment: the commands, session hooks and seven agents.
- **Notion** — retired as of 2026-06-18. Not a live dependency.

## Constraints

Hard, in roughly descending order of "breaking this breaks the point of the repo":

- **Delivery must not cost per learner, and its total cost must stay flat as learners grow.** Any platform that requires each person to be enrolled at a price, or paid for individually, is unworkable for a worldwide consultant body — the specific reason Cypher for Business could not be the long-term answer. The same test now applies to everything around Moodle: hosting, the app plan, paid plugins and bolt-on services. A paid tier is acceptable only if it is flat-rate.
- **Content must stay portable.** Moodle is the delivery platform now, but nothing may bind course content to its format: the markdown in this repo is the asset, and any renderer or LMS is replaceable. Prefer the reversible choice. In practice this is what the publisher's split at a platform-neutral payload is for — `moodle_payload.py` knows courses and nothing about Moodle's API; `moodle_client.py`, `moodle_xml.py` and the plugin know Moodle and nothing about pedagogy. Replacing the platform means replacing the lower half only.
- **Learner data lives in Moodle, never in this repo.** The repo is public. It holds code and configuration; Moodle holds people. No learner names, emails, enrolments, grades, report exports, database dumps or site backups are ever committed — not even as test fixtures. No credentials either: `MOODLE_TOKEN` and its kind come from the environment.
- **The CBC framework is not ours to change.** The 42 competencies and the five outcome levels are external givens. Competency names are matched verbatim; a mismatch is a silent coverage miss, which is why it's a hard CI failure rather than a warning.
- **CBC vocabulary only.** The legacy level names, and the off-by-one level offset they came with, caused real confusion once. They stay retired — in Moodle's competency frameworks and badges as much as in the repo.
- **Published competency URLs are citations, not implementation detail.** The CBC program points people at `/<category>/<competency>/` on the published site, so a page that moves breaks a reference someone else is relying on. That URL is built from the category key in `competencies.yaml` and the descriptor's filename — so renaming a file, moving a competency between categories, or renaming a category key all move pages, and the last moves every page beneath it at once. Moving a page is allowed; moving one silently is not. Record the old path in `redirect_maps` in `mkdocs.yml` in the same change.
- **No step may require git knowledge.** This constrains every feature: if a change means a contributor must understand branches, rebases or merge conflicts, the change is wrong even when it is technically better.
- **No step may require LMS orientation.** The learner-side twin of the rule above. If a partner learner has to be taught Moodle before they can take a course, the setup is wrong. It gets broken the same accidental way: by accepting a default that makes sense to an administrator.
- **Built for Android, offline and low bandwidth.** These are requirements, not preferences. A course must work in the Moodle app and offline; pages stay light; video is never the only route to the content.
- **Moodle core first.** Reach for core Moodle before a maintained free plugin, a plugin before our own code, and our own code before a bolt-on system. Every plugin is an upgrade liability and every bolt-on is another thing to operate. Never modify Moodle core, and pin every plugin to the release it was verified against, as `local_ltuse` does.
- **Every change to Moodle must survive an upgrade.** Upstream security and feature releases have to apply cleanly, so every change we make to Moodle's behaviour is configuration, a maintained plugin, or a plugin of our own built on Moodle's extension points. We never edit vendored code, whether that is core, a bundled theme or a third-party plugin, and we never patch it at deploy time.
- **Standardisation is the point — resist per-course special cases.** A course that needs its own bespoke structure, or its own exception to the process, erodes the very thing this repo exists to provide. The same goes for partner organisations: one set of roles and one structure, not a custom setup per partner. An organisation-only course is one standard variant any organisation may have, not a special case.
- **Generated artefacts are never hand-edited** (`COVERAGE.md`, the published site, the published Moodle courses). Hand-editing a generated file makes the generator a liar.
- **Review must be independent.** A course needs at least two humans; the approver and internal reviewer cannot be the author.
- **One course at a time.** An ordered queue, not parallel commitments — half-finished courses help nobody. _(assumption: worth revisiting now that up to ten people may be contributing.)_
- **Unlisted is not secret.** The review sites are _unlisted_, which is not the same as secret, and must never be described as secret.
- **The learner view is a disclosure boundary and fails closed.** A quiz whose answer key can't be cleanly separated is withheld entirely, not partially stripped. Never optimise this into "strip what we can." On Moodle, answer keys travel only inside question data, never in page HTML.
- **Content is markdown a human can read raw.** Tooling may render it; nothing may make the source unreadable or require a tool to edit it.
- **No large binaries in git.** Video lives in Vimeo or Drive, linked from frontmatter.

## Out of scope

- **Training evidence, not certification.** Moodle may record course completions, badges and competency evidence, and CBC assessors may consult them. But who has reached which CBC level is assessed and decided by the CBC program, not here. The training system never awards or records a CBC level, and no badge or certificate it issues says "certified".
- **Two products, one boundary.** The training system _is_ an application: it has users, data, and an uptime and backup obligation, and somebody must operate it. The curriculum half is still not one — its scripts serve authors and CI, and that is the whole job. The publisher and [`moodle/local_ltuse/`](moodle/local_ltuse/README.md) are the bridge between them, and the only code that should know both a course's shape and Moodle's.
- **Not a fork of Moodle, and not an LMS of our own.** We configure and extend Moodle; we do not modify its core or rebuild a feature that core or a maintained plugin already provides. We are not maintaining Moodle: if we ever needed a variant, it would be a distribution someone else maintains (such as IOMAD), never a fork of our own.
- **We don't run a Moodle instance for each organisation.** Every partner we host shares one site. Inside it each organisation is identified by category, cohort and profile field, and its managers are scoped by cohort; the courses themselves are shared. SIL, managed Area by Area, is declared as one organisation entry per Area, each with the same shape (2026-10-03). A partner running its own Moodle is a different case, covered under **Open questions**.
- **No Moodle → repo sync.** Not for content, which would break the source-of-truth split, and not for learner data, which would break the public-repo rule.
- **Not a place to re-derive stage state by hand.** `scripts/course_stage.py` is the single implementation; a second one is a bug, not a feature.

Note what is deliberately **not** on this list. The published competency site is meant to be the go-to public reference for what an LTC competency is and where to go learn it, so "that's documentation rather than curriculum" is not a reason to leave something off it.

## Decisions

- **2026-09-22 — Delivery is self-hosted Moodle.** It meets the per-learner-cost constraint, and its Android app reaches consultants working offline. The portability constraint still binds — this is a decision about where courses are delivered, not about what they are written in. Stage 7 pilots and stage 8 publishing both go to Moodle; the reviewer site keeps serving stages 5–6, so reviewers need no Moodle account.
- **2026-09-30 — This repo builds the training system too.** Moodle configuration, plugins, theme and admin tooling live here alongside the curriculum, rather than in a sibling repo, so a change that touches both halves is one pull request.
- **2026-09-30 — Learner records live in Moodle only.** The training system holds enrolments, completions, progress and profiles; the public repo holds none of it.
- **2026-09-30 — Training evidence, not certification.** See **Out of scope**.
- **2026-09-30 — Who the training system serves:** language technology consultants worldwide, partner organisations' learners, and translation teams.
- **2026-09-30 — Production is a dedicated VPS, not the shared host.** `ltuse.net` on the shared host is for building and piloting only; learners are not taken live until Moodle has moved to a dedicated VPS that can serve many at once. Scripted configuration (below) is what makes that move a rebuild plus a data restore.
- **2026-09-30 — Moodle configuration is code.** Every setting, plugin, role and structure the training system depends on is applied from `moodle/`, never only by hand, so rebuildability (outcome B.5) is a rule rather than an assumption. Recorded as a binding rule in [`.specify/memory/constitution.md`](.specify/memory/constitution.md), along with testing the learner- and admin-experience work with real partner users before it counts as done.
- **2026-09-30 — Upgrades are never a porting project.** Every change to Moodle's behaviour must carry forward through upstream security and feature upgrades: configuration, maintained plugins, or our own plugins on supported extension points, with no edits to vendored code. A fork is out; the furthest we could go is a distribution someone else maintains, adopted as a recorded decision. Recorded as Principle XI of the constitution.
- **2026-10-01 — How partner organisations are onboarded.** Each partner is one entry in `moodle/site/organisations.yaml`, which gives it a category, a learner cohort and a managers cohort, the same shape for everyone (spec 002).
  - **Site team.** The site team creates accounts, sets each learner's organisation, and enrols an organisation into a course with one group per organisation.
  - **Managers.** An organisation manager follows their own people inside those courses and does nothing else. Core Moodle cannot limit enrolling or account creation to one organisation.
  - **People who leave.** A small `local_ltuse` profile hook keeps someone who leaves an organisation out of their old manager's view. In a course both organisations share, the site team also removes the old enrolment by hand.
  - **What stays public.** Category pages and course names, because they hold no personal data.
  - **Country cohorts.** None are created in advance.
- **2026-10-02 — Courses are open across organisations.** Supersedes the in-course parts of the 2026-10-01 decision: one group per organisation, managers who only follow, and the hand clean-up after a learner moves. The rest of it stands, including the site team creating accounts and the profile hook (now narrowed). Competency courses are shared by every organisation, because most mentors will be SIL and cohorts are small, so a wall inside a course costs more than it protects (Doug; spec 002 Clarifications 2026-10-02, spec 011 handoff B1–B6).
  - **No walls inside a course.** Shared courses have no organisation groups. Learners see all their classmates, course leaders see their students, and mentors see and work with their mentees across organisations, each as the identity that person's protection level allows (spec 016).
  - **Organisations are for identity and management, not separation.** A manager is scoped by membership of their organisation's managers cohort. They see and manage their own people (enrol and unenrol, suspend and reactivate, send a password-reset link, assign and end mentors) through our own pages, because core cannot limit any of those to one organisation, and each manager and their people become message contacts. They are no longer enrolled in shared courses.
  - **The exception.** An organisation may have a course only for its own people. The maintainer declares it in `moodle/site/`, not in the course; the site team enrols the organisation, and its managers may enrol their own people. Its content is still public; only enrolment is restricted.
- **2026-10-03 — Learners ask; Area LT Coordinators add them.** SIL develops its consultants Area by Area, through its Area Language Technology Coordinators (ALTCs), and the training system follows the same line. It builds on the 2026-10-02 decision and changes none of it except where stated. The design follows in spec 002 (Clarifications 2026-10-03) and a later spec 017.
  - **Decided by Doug (2026-10-03):**
    - **ALTCs add learners and put cohorts together.** An ALTC is the person responsible for developing consultants. For SIL and SIL partners that is per Area; another organisation may call the role something else, and this title is used for all of them. In the training system, an ALTC is the organisation manager of the entry they are responsible for.
    - **An Area is an organisation of the one shape, and replaces SIL as a learner's organisation of record.** SIL's five Areas are Americas, Anglo-Lusophone Africa, Francophone Africa, EurAsia (Eurasia and Asia) and Asia Pacific. Each is declared as an organisation entry, and so is the matching entry for SIL partners. "SIL" becomes the family name the Area entries share. The keys are `sil-americas`, `sil-al-africa`, `sil-fr-africa`, `sil-eurasia`, `sil-asia-pacific`, and the same five with `silp-` for SIL partners.
    - **SIL partners' learners come under the ALTCs, with full manager rights,** protected identities included.
    - **Learners ask, and the request reaches their coordinator.** A person looks at the site and all of the courses and asks for one, giving their name and country. The request goes to the ALTC of that country's Area, who adds them and, if asked, keeps their identity secret (spec 016).
    - **Teachers should be able to add learners too,** especially a teacher who advertises a course for a group to take as a cohort. If that adds too much complexity, ALTCs adding people is acceptable.
    - **Cohorts are within an Area, not a country.** The 2026-10-01 rule against country cohorts stands.
    - **A course is taken by a cohort, or by a student with their mentor (2026-10-04).** In a pair, the student takes the course and the mentor works with them to assess and grade it. A cohort has one or more mentors for the course, and they do the grading. A learner may have a default mentor, and someone may also mentor them for just one course. Grades are course training evidence, never a CBC level.
    - **The country-to-Area map is not in the repo.** Which countries belong to which Area is kept in Moodle by the site team. Some are places where being identified with this work is a risk, and the public repo and its history are permanent.
  - **Recommended, and confirmed by Doug (2026-10-04):**
    - The public competency site is the course catalogue. Whether Moodle then forces login, and how that affects the public certificate and badge verification pages, is for spec 017's research.
    - The request flow is its own spec, 017. The data-protection position (see **Open questions**) is settled before a public page collects personal data from people who have no account.
    - An account made from a request is created with the protection it asked for already set, before any enrolment. Until spec 016 is live, a request that asks for any protection waits.
    - Once spec 017 exists, an ALTC may create an account only by approving a request for their own Area. Until then an ALTC enrols only people who already have an account, and the site team creates every account.
    - Teacher enrolment adds too much complexity for now: core's teacher enrolment searches every user on the site, counts as a pilot enrolment, and can change the managers' enrolment method. So ALTCs alone add learners, and the course-editing teacher loses core's enrolment rights once the stage-7 pilot enrolment is shown to work without them. Teachers adding learners stays the goal for later.
    - The existing `sil` and `sil-partner` entries stay, as holding entries for people whose Area is not yet known, managed by the site team only.
    - Another organisation could be declared by region the same way, by a reviewed change. A region whose name could mark people at risk takes a neutral key and name from its first commit (spec 016 R12).

## Open questions

- **Who operates the Moodle server?** Now the most important open question: the training system depends on the answer. The first instance is temporary: `ltuse.net`, on a shared host on Doug's hardware that also carries about 15 other live sites, for building against. It cannot serve many learners at once, so it moves to a dedicated VPS before go-live (see **Decisions**). Which VPS, who administers it, how it is monitored, and what the backup and restore story is are not yet decided. The `idnumber` scheme makes moving content a re-publish, but learner data only survives a move if it has been backed up.
- **Community inside Moodle, or bolted on?** A standing community course with topic forums costs nothing new to run but feels like a course. A bolt-on (Discourse, Matrix) is a better community but a second system to operate and pay for. Start inside Moodle and move only if engagement shows the need _(assumption)_.
- **How do translated courses live in the repo?** The Moodle interface is easy to localise; course content is not. Whether a translation is a sibling course folder, a per-lesson variant, or something else touches the course layout, `course_stage.py` and the publisher, and should be designed before any is built.
- **What is our data-protection position?** A worldwide learner base means a privacy notice, a retention rule, consent at sign-up, and an answer to "delete my data". None exists yet. Doug owns it for now (2026-10-03). It is settled before the public request page (spec 017) goes live, since that page collects personal data from people who have no account (confirmed by Doug, 2026-10-04).
- **Who approves and enrols an organisation-only course, beyond the first ones?** Decided for now (2026-10-02): the maintainer approves and the site team enrols. Revisit once several organisations have asked for one. Partner managers enrolling their own learners was answered the same day: they do, for their own people (see **Decisions**).
- **Where does admin tooling go?** Scripted cohort creation and bulk enrolment would make "a small team can run it" true, but they handle learner data. They belong in the training-system half, run against Moodle, and must never write learner data into git.
- **Which Moodle app plan, if any?** The free plan's 50-device push limit will be outgrown; whether push notifications are worth a flat-rate plan, or email is enough, is undecided.
- **Which pathway mechanism?** Core competency learning plans, or the free Programs plugin (`tool_muprog`) — whose listed releases stop at Moodle 5.1, so 5.2 support needs confirming first.
- **When does the `/learn/` view retire?** It stays through the transition as a fallback and as the reference implementation of the disclosure boundary. Retire it once a pilot has run cleanly on Moodle end to end — not before, because it is the thing to fall back to if the Moodle publish turns out to have a gap.
- **Does the Cypher wind-down make backfill urgent?** Yes, and more so now there is somewhere to put the content. **16 of the 31 courses have no lesson files at all** — their whole body is a single run-on README paragraph imported from Notion, with hotlinked Google images that will rot. `publish_moodle.py` refuses them by design rather than shipping that under a real course name. Until they are backfilled they cannot move to Moodle, and when Cypher ends they have no home.
- **How do ten contributors and "one course at a time" coexist?** The queue rule was written for a much smaller working group. With department staff and Seed Company collaborators both active, it may need to become one course per person or per pair rather than one course repo-wide.
- **How do we rank the remaining 22 gap competencies?** `coverage-strategist` recommends a next course, but the ranking principle — learner demand, certification bottleneck, ease of authoring — isn't written down anywhere.
- **What if Seed Company wants its own Moodle?** Not needed now — everything goes on `ltuse.net` — but they may want their own platform later. The content side is cheap: the platform-neutral payload and `MOODLE_URL` from the environment already let the publisher target a second server. The questions are elsewhere: whether their instance is built from the same `moodle/` configuration or their own, whether courses published there stay one-way from this repo, and what happens to learners who move between the two sites, since learner data never passes through here.
- **What does Seed Company collaboration look like mechanically?** Reviewing is straightforward; authoring from an outside organisation raises questions about repo access, review independence, and who approves their designs.

## What delay costs

There is no external deadline. But every month without published courses makes it harder to train consultants who are already in the field and already being assessed against these competencies — the CBC program points them at a competency site whose gaps are plainly visible. And until the training system exists, partner learners and their mentors have no shared place to keep learning once a course is over. The pressure is real even though the date isn't.
