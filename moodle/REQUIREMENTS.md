# Training-system requirements: Moodle 5.2 feasibility

**Status:** analysis, 2026-09-30. A working roadmap for the training-system half of this repo (see [`INTENT.md`](../INTENT.md)), not a commitment. Update a row when it is built, verified or decided.

## Context

Courses are published one way, repo → self-hosted Moodle 5.2.3+, through [`scripts/publish_moodle.py`](../scripts/publish_moodle.py) and the [`local_ltuse`](local_ltuse/README.md) plugin (verified end to end 2026-09-29). This page rates each of the 25 platform requirements by how much work it takes: **built in (config)**, **existing plugin**, **custom development**, or **bolt-on system**.

**Effort scale:** **S** = hours to a day of admin config · **M** = days (install/configure a plugin, or a small script) · **L** = weeks (custom build or a separate system) · **Ongoing** = recurring ops or cost.

## Verdict

**All 25 are achievable. None is blocked.** About 17 are built into core and need only configuration. About 5 need free community plugins. Only 3 areas are real work:

1. **Community that lives outside courses** (2 must-haves). This is Moodle's weakest area.
2. **Simple learner experience and simple administration.** Moodle can do both, but only after deliberate setup and some admin tooling.
3. **Multilingual course content.** The Moodle interface is easy to translate. Translated *course content* needs publisher work in this repo.

The hard parts are UX and operations, not missing features. Someone still has to own the server (an open question in `INTENT.md`).

## Requirement-by-requirement

| # | Requirement | Pri | How Moodle meets it | Type | Effort |
|---|---|---|---|---|---|
| 1 | External HTML content delivery | Must | **Done already.** `publish_moodle.py` and `local_ltuse` render repo markdown into `mod_page` and quizzes. | Custom (built) | Done |
| 2 | Easy content updating | Must | **Done already.** Republishing uses `ltct:` idnumbers, so it updates instead of duplicating (verified). | Custom (built) | Done |
| 3 | SCORM and/or robust embeds | Must | **Configured (spec 001), 2026-10-01.** Declared in [`site/`](site/README.md) and applied to the build host; the learner-facing checks with test accounts and the Android app are still to run. Core SCORM 1.2 is solid. SCORM 2004 runs content, but sequencing is only partly supported. Core also has H5P, IMS CP, LTI 1.3, URL and iframes (iframes need the trusted-content permission). | Built in | S |
| 4 | Mobile-friendly | Must | **Configured (spec 001), 2026-10-01.** Declared in [`site/`](site/README.md) and applied to the build host; the learner-facing checks with test accounts and the Android app are still to run. Official Moodle app (Android/iOS) plus responsive Boost theme. `mobilecssurl` is declared in `site/settings/mobile.yaml`. | Built in | S |
| 5 | Low bandwidth | Must | App offline download and sync. The content is already light (text and screenshots, video linked). To add: compress images in the publisher and turn on server caching. | Built in + small repo task | S–M |
| 6 | Multilingual | Must | UI: core language packs plus a per-user language setting (S). Content: the `multilang2` filter, or one course per language. **The repo has no translation model yet.** The publisher and course layout would need a per-language variant. | Built in + repo dev | UI S / content **L** |
| 7 | Progress tracking | Must | Core activity and course completion, gradebook, logs, and the **Report builder** (custom reports, CSV export, scheduled email). | Built in | S |
| 8 | Cohorts / groups | Must | Core site and category cohorts, groups and groupings, cohort-sync enrolment. Add a plugin that auto-fills cohorts from a profile field (e.g. org/country). | Built in + plugin | S–M |
| 9 | Community beyond courses | Must | **Weak point.** Quick option: a standing "Community" course (self-enrol, open to all) with forums per topic. It's indefinite and needs no plugins, but it feels like a course. Better option: Discourse or Matrix bolted on, with Moodle as the single sign-on (SSO) identity. | Workaround (S) or bolt-on (**L**, Ongoing) | S → L |
| 10 | Peer-to-peer interaction | Must | Core forums (Q&A type, ratings, attachments), Database activity, Glossary, Wiki. | Built in | S |
| 11 | Mentor / trainer interaction | Must | Core **user-context role** ("mentor" can see assigned learners' progress), groups with a non-editing teacher, messaging, assignment feedback, competency learning-plan reviewers. Fits the mentor model in `INTENT.md`. | Built in | S–M |
| 12 | Learning pathways | Must | Core: competency frameworks plus **learning-plan templates**, and course-completion access restrictions. Richer option: **Programs** (`tool_muprog`, free, Petr Škoda). **Its listed builds cover Moodle 5.0–5.1, so check 5.2 support before relying on it.** | Built in / plugin | M |
| 13 | Simple learner experience | Must | Doable, but not the default. Needs: a trimmed dashboard, a course format (Tiles/Topics), a theme tweak, and a clean "My courses". Pilot-test it with real partner learners. | Config + light theme | M |
| 14 | Simple administration | Must | Core: CSV bulk user upload (with cohort and course enrolment), course copy, backup/restore, category managers. Still a learning curve for a small team. Scripted helpers would help; after the 2026-09-30 INTENT revision they belong in the training-system half of this repo and must never write learner data into git. | Built in + training (+ optional dev) | M, Ongoing |
| 15 | Scales across orgs | Must | One site, with per-org **course categories**, category-scoped manager roles, cohorts and profile fields. Real multi-tenancy (per-org branding/isolation) is Moodle Workplace (paid) or the IOMAD fork. Probably not needed. | Built in | S–M |
| 16 | Data export / ownership | Must | **Configured (spec 001), 2026-10-01.** Declared in [`site/`](site/README.md) and applied to the build host; the learner-facing checks with test accounts and the Android app are still to run. Self-hosted Postgres means you own the whole database. Also: Report builder CSV, Privacy API export per user, course backups. | Built in | S |
| 17 | Pricing at scale | Must | GPL, no licence fee, no per-user cost. Real costs: hosting (a modest VM scales to thousands of learners), backups, admin time, and app push notifications (**free tier = 50 active devices/mo; Pro = 500; Premium = unlimited**). | Ongoing | Ongoing |
| 18 | Persistent profiles | Pref | Core custom user profile fields (role, org, region, expertise), with visibility controls. | Built in | S |
| 19 | Direct messaging | Pref | **Configured (spec 001), 2026-10-01.** Declared in [`site/`](site/README.md) and applied to the build host; the learner-facing checks with test accounts and the Android app are still to run. Core messaging (1:1, group conversations), in the app too. | Built in | S |
| 20 | Community channels / topic groups | Must | Same answer as #9. Forums in a community course, or bolt-on Discourse/Matrix. Core's Communication API (Matrix) gives course/group rooms, but needs a Matrix server. | Workaround or bolt-on | S → L |
| 21 | Events / calendar | Pref | Core calendar (site/course/group events, repeats), core BigBlueButton or linked Zoom/Teams, `mod_scheduler` plugin for office-hour slots, `mod_attendance` for tracking attendance. | Built in + plugins | S–M |
| 22 | Assignments + peer review | Pref | Core Assignment (feedback, rubrics) and **Workshop** (structured peer assessment). | Built in | S |
| 23 | Certificates / badges | Pref | Core Open Badges (course/site/competency criteria). Certificates via `mod_customcert` (free). **Caution:** keep this "training completed", never "CBC certified". `INTENT.md` says the platform is not the certification record. | Built in + plugin | S–M |
| 24 | Searchable resource library | Pref | Core Global search (use Solr for decent results) plus a "Library" course (Database activity/folders). **Alternative:** the GitHub Pages competency site already has search, so resources could live there and be linked. | Built in + config | M |
| 25 | Manageable notifications | Pref | **Configured (spec 001), 2026-10-01.** Declared in [`site/`](site/README.md) and applied to the build host; the learner-facing checks with test accounts and the Android app are still to run. Core per-user notification preferences, admin defaults, forum digests, email. Mobile push is subject to the app-plan limit in #17. | Built in | S |

## Recommended sequence (if this becomes work)

1. **Config sprint (S items):** profile fields, cohorts, categories per org, mentor role, badges, calendar, report-builder reports. Roughly 1–2 weeks of admin time.
2. **Plugins (M):** cohort-from-profile-field, `mod_customcert`, `mod_scheduler`, and Programs if 5.2 support is confirmed.
3. **UX pass (M):** learner dashboard and theme, then test with 2–3 partner learners at the stage-7 pilot.
4. **Community decision:** start with a community course. Only move to Discourse/Matrix if engagement shows the need.
5. **Multilingual content (L):** design how translated lessons live in the repo *before* building anything. It touches course layout, `course_stage.py` and the publisher.
6. **Ops:** production host, backups and an owner. This is `INTENT.md`'s open question, and every "Ongoing" row depends on it.

## Specs

Every row still to build has a Spec Kit feature spec under [`specs/`](../specs/) (drafted 2026-09-30). A spec is not a commitment and does not change a row's status. The PR that delivers a spec updates its rows (constitution X).

| Spec | Rows |
|---|---|
| [001 Site configuration as code](../specs/001-site-config-as-code/spec.md) | #3, #4, #16, #19, #25 (and the rebuild rule every other spec relies on) |
| [002 Organisations, cohorts and profiles](../specs/002-org-structure-cohorts/spec.md) | #8, #15, #18 |
| [003 Mentor role](../specs/003-mentor-role/spec.md) | #11 |
| [004 Progress tracking and reporting](../specs/004-progress-reporting/spec.md) | #7, #16 (report exports) |
| [005 Community space](../specs/005-community-space/spec.md) | #9, #20, #10 |
| [006 Learning pathways](../specs/006-learning-pathways/spec.md) | #12 |
| [007 Simple learner experience](../specs/007-learner-experience/spec.md) | #13 |
| [008 Admin tooling](../specs/008-admin-tooling/spec.md) | #14 |
| [009 Low-bandwidth delivery](../specs/009-low-bandwidth-delivery/spec.md) | #5 |
| [010 Multilingual](../specs/010-multilingual/spec.md) | #6 |
| [011 Events and calendar](../specs/011-events-calendar/spec.md) | #21 |
| [012 Assignments and peer review](../specs/012-assignments-peer-review/spec.md) | #22, #10 (in-course discussion) |
| [013 Completion badges and certificates](../specs/013-certificates-badges/spec.md) | #23 |
| [014 Resource library](../specs/014-resource-library/spec.md) | #24 |
| [015 Production hosting and operations](../specs/015-production-hosting-ops/spec.md) | #17, and the operations behind every Ongoing row |

## Items that touch the repo's own rules

- Admin tooling (#14): allowed in this repo since the 2026-09-30 INTENT revision, but never writes learner data into git.
- Moodle competencies and badges (#12, #23) are training evidence and must never become a CBC certification record.
- Multilingual content (#6) is a new content-model decision for the repo.

## Verification

This is an analysis, so nothing is executed. Before committing to any row, check it on the temp 5.2.3+ instance, especially #12 (Programs on 5.2), #9/#20 (community UX) and #13 (learner UX with real partners).

Sources: [Moodle app plans (5.2 docs)](https://docs.moodle.org/502/en/Moodle_app_plans) · [Programs plugin](https://moodle.org/plugins/tool_muprog/versions) · [Improve SCORM 2004 support](https://docs.moodle.org:443/dev/Improve_SCORM_2004_Support)
