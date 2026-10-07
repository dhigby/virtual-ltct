# Implementation Plan: Community Space Beyond Courses

**Branch**: `005-community-space` | **Date**: 2026-10-06, decisions applied 2026-10-07 (two rounds) | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/005-community-space/spec.md` (rows #9, #10, #11, #20, #25; #19 recorded; #26 relied on), revised for the maintainer's communication purposes of 2026-10-06 and his decisions of 2026-10-07

## Summary

Doug named three purposes (spec.md, "Communication purposes"). This plan builds the two that have no home yet, and the email plumbing all three share.

1. **The mentor route (purpose 1).** In core Moodle only a forum post can be answered by email (R1). So the mentor works from forum email:
   - Incoming mail processing is declared in `moodle/site/settings/inbound-mail.yaml`, password from the environment. The forum reply handler is switched on by a new site-config kind, because core keeps that switch in a table, not in config (R2). The host gives one IMAP mailbox with `+` subaddressing and cron every minute, recorded as a spec 015 operator task (Q8).
   - local_ltuse sets a per-forum "no digest" override (0) automatically: for each mentor on the delivery-course forums they mentor in and on their teaching spaces' Problems forums, and for each mentee on the delivery-course forums where they have a mentor and on their teaching space's Problems forum (Q9; members as the mentor, round 2). Team in a teaching space is set to the digest. An Area space gets no override. An override never replaces a person's own choice, and sync records which overrides it wrote, so removal never erases a personal choice (round 2). A new observer subscribes a mentor only to the discussions their own mentees start or post in (R4, R10; Q10 as changed in round 2).
   - The mentee stays in the course or cohort space, in the browser or the app, offline included (app survey). Mentoring threads are open to everyone in that course or space, accepted as the price of reply by email (Q23).
2. **Cohort spaces (purposes 3b, 3c).** One small course per cohort, idnumber `ltct:site:cohort:<key>`, in the visible category `ltct:cohort-spaces`, built by a new `siteconfig\cohortspaces` class on spec 011's office-hours model (R7). The course is named after the cohort itself, never an id (round 2). Two declared shapes (Q2):
   - **teaching**: one per teaching cohort, which spec 002 defines as a Moodle cohort and an ALTC creates and fills through 002's scripted action (teachers making cohorts is deferred to spec 017). Key: the numeric Moodle cohort id. Auto subscription; mentor and members get per-post mail on Problems and the digest on Team (Q10, round 2).
   - **area**: one per organisation entry declaring `space: area`, the one opt-in field (round 2). Any entry may opt in except `independent` (validate refuses it) and spec 016 neutral entries (a review check refuses them, round 2; the repo never marks an entry neutral). Key: the organisation key. Optional subscription and no override for anyone.
   Members come from the cohort through `enrol_cohort`. A space's members are its cohort plus the mentors the site team records for it through `ltct_admin.py` (spec 008 cohort-mentor rows), never members' default mentors (Q4). The recorded mentor moderates the space, including locking by hand through a declared override (Q13, round 2). Each space has two forums, "Team and friendship" and "Problems and questions", with no automatic locking (Q12). Members see the participant list, by name, and may message each other (Q6). A space opens when it first has a mentor and then stays open, even without one; it has no end state (Q7, round 2). If a teaching cohort is deleted in Moodle, its space and posts are kept and the recorded mentor takes over: core removes the members' cohort enrolment, not the mentor's own, and drift flags `cohort deleted` for the site team (round 2). Each space stands alone.
3. **Site-wide forum behaviour.** Defaults every forum inherits, including spec 012's course forum, without touching its module: subscribe on posting, read tracking on for new accounts (Q18) and, by a one-off script, for existing ones (round 2), RSS and portfolios off (Q14), tags on (Q19), `maxeditingtime` 30 minutes (Q11), `loglifetime` 365 days (Q21). The `teacher` role loses all four forum export capabilities and keeps private reply (Q14, round 2).
4. **Engagement review.** Re-scoped to cohort spaces and course forums, run on the existing `ltuse_admin` service (Q21), with the definitions of Q16 as confirmed in round 2. The off-platform signal measures change against a WhatsApp baseline that coordinators report (R15).

Purposes 2 and 3(a) belong to spec 012 and get four re-plan inputs (R17). "Fellow students in the same course" is everyone in the course, through 012's course forum (Q5). The site-wide all-accounts space is **deferred** (Q1): it is out of this build, its spec text is kept and marked deferred, and it is revisited after the engagement reviews (R16).

**Constitution amendment in the same PR.** Doug approved (2026-10-07, Q3) an `INTENT.md` decision and a MINOR constitution amendment, 2.1.1 to 2.2.0 (level confirmed in round 2), allowing site-declared cohort space courses. This feature's PR drafts both, citing 2026-10-07. They are not edited before then. The bullets to draft:
- **II**: add `ltct:site:cohort:<key>` (a site-declared cohort space course) to the idnumber forms beside `ltct:<slug>` and `ltct:<slug>:<file number>`.
- **Platform & Delivery**: the organisation-only course stays the one mechanism for keeping a *delivery* course to one organisation; a site-declared cohort space course, which carries no course content, is the one mechanism for keeping a community space to one cohort.
- **VII**: one sentence beside the organisation-only course: a cohort space is not a special case either; it is a uniform variant made from one template, open to every teaching cohort and to any organisation entry that opts in.

**What cannot start yet.** Teaching-cohort spaces wait on spec 002 choosing a **Moodle cohort** as the mechanism for teaching groups (002 leaves "a group inside one course, or a cohort across several" open; 005 needs the cohort) and fixing its idnumber namespace. Area spaces wait on 002 applying its Area entries (FR-014); today `sil` holds all of SIL and does not opt in. The mentor route's reply by email waits on spec 015 providing the mailbox and on V2 passing. The site-wide defaults, the read-tracking script, the `teacher` role change, the mentor and mentee digest code and the mentor subscription observer can be built now.

## Technical Context

**Language/Version**: Python 3.11 (`scripts/site_config.py`, `scripts/engagement_review.py`); PHP 8.2+ (local_ltuse) on Moodle LMS 5.2 (`MOODLE_502_STABLE`, instance 5.2.3+); moodleapp v5.2.1 (no app code)

**Primary Dependencies**: core `mod_forum` (subscriptions, `forum_set_user_maildigest`, inbound `reply_handler`), `tool_messageinbound`, `enrol_cohort`, accesslib `assign_capability()` for the space-mentor override; local_ltuse siteconfig applier and inspector, `admin\course_mentor_sync`, `admin\course_mentor_records`; block_ltuse

**Storage**: Moodle database only. Posts, memberships, cohort names, mentor records, the record of overrides sync wrote, and engagement data never leave Moodle (Principle III). The repo holds structure. The mailbox secret is held only in the environment.

**Testing**: Python `unittest` (`tests/test_site_config.py`, `tests/test_ltct_admin.py`, new `tests/test_engagement_review.py`); Moodle-free PHP harnesses (`tests/*_harness.php`); PHPUnit in `moodle/local_ltuse/tests/`; instance checks V1–V12 on `$MOODLE_URL` (ltuse.net) with test accounts and test cohorts ([quickstart.md](quickstart.md))

**Target Platform**: self-hosted Moodle 5.2 (web, email) and the Moodle app 5.2.x

**Project Type**: site configuration plus Moodle plugin changes

**Performance Goals**: a mentor's emailed reply visible to the mentee in about `maxeditingtime` (30 minutes) + 2 minutes (R6, V5); engagement review under one hour (SC-005)

**Constraints**: never `groupmode: 1`, no groups to separate people (002 R3, R10); public APIs only, direct writes and raw reads listed (XI); `allowedemaildomains` stays empty (016); no learner data or token in the repo; GitDoc pushes the branch within minutes, so no secret ever touches the tree; users never see a username, numeric id or code (spec Identity)

**Scale/Scope**: one space per teaching cohort (tens at most at first) and one per opted-in Area entry (ten when 002's Areas are applied), two forums each; one mailbox; every mentor

No [NEEDS CLARIFICATION] or [NEEDS DOUG] markers remain. Doug's answers are under [Decisions](#decisions-doug-2026-10-07) and [Round 2](#round-2-doug-2026-10-07); no [remaining question](#remaining-questions) is open; items only the instance can settle are V1–V12.

## Constitution Check

*GATE: checked before Phase 0, re-checked after Phase 1.*

| Principle | Pre-design | Post-design |
|---|---|---|
| I. Source of truth | PASS. Spaces are declared in `moodle/site/community.yaml`; the publisher never addresses `ltct:site:` (FR-017). | PASS. Validate, `check_course_package.py` and a publisher test refuse the reserved names listed once in the community-yaml contract ("Reserved names"). |
| II. Config as code | PASS (Q2). Area spaces follow entries declared `space: area` in `organisations.yaml`. Teaching cohorts are made at run time only by 002's scripted action (ALTC), so a teaching space follows a selector, not a hand-kept list. Inbound settings and the handler row are declared. | PASS once the Q3 amendment merges, which adds `ltct:site:cohort:<key>` to II's idnumber forms, and once 002 defines the teaching cohort as a Moodle cohort. The handler row has its own declaration kind with drift (contract inbound-mail). |
| III. Public repo, private people (NON-NEGOTIABLE) | PASS. Mailbox password `env:` + `secret: true`. Engagement output aggregate, suppressed (<5), site-wide only, terminal only. | PASS. The space declaration names no cohort or member. A teaching space is keyed by the numeric Moodle cohort id, which is not personal; apply, drift and summary output print only that key, never a teaching cohort's name or idnumber (a test asserts it). Area spaces are keyed by the public organisation key. A space's name, the cohort name, is read live from Moodle and never written to the repo. |
| IV. Disclosure | PASS. Spaces carry no course material. | PASS. |
| V. CBC fidelity | PASS. Forum names say nothing about levels. | PASS. |
| VI. No LMS orientation | PASS. Membership follows the cohort; the mentor works from email; per-post mail is set for mentors and mentees, not left to them (Q9). | PASS. Learner-home shows a "Your cohort" route under the cohort's own name (data-model). SC-003 needs real users. |
| VII. Standardisation | PASS. One template; two declared shapes, teaching and area, differing only in subscription mode and digest overrides (Q2); no per-cohort roles. | PASS. The area shape is justified in spec FR-006a. Opting in is one declared field open to any organisation entry (round 2), so it is a uniform variant, not a per-partner case. The amendment adds a matching VII sentence. |
| VIII. Language humility | PASS. No automated moderation; guidelines say not to judge minority-language text; the mentor moderates by pin, lock, move and remove. | PASS. |
| IX. Flat cost, field-ready | PASS with host dependencies, all spec 015 operator tasks: one IMAP mailbox with plus-addressing and cron every minute (Q8), on the existing host plan or a flat-rate mail service named with its price in 015; outgoing mail volume under the host's send limits; mailbox retention (it holds learner text, covered by 015's backups); log growth at `loglifetime` 365 days. App plan: Premium, as `site.yaml` L42 records (Doug, round 2); spec 015 FR-010's wording is a follow-up for 015. | PASS. App offline post and attachment confirmed in source. |
| X. Traceable and verified | PASS with open items. Rows #9, #10, #11, #19, #20 and #25 updated in the same PR; #26 relied on. Burdens named below; while the operator is undecided (`INTENT.md` L68) the operator burdens are named, not covered. | GATED: V1–V12, plus the API behaviours in R4, R10, R11 and the `create_course()` side effects (contract site-config-community), must pass before any task depending on them; tasks.md orders the verification tasks first. They run on ltuse.net now, with test accounts and test cohorts only (Doug, round 2). A space admits members only once it has first had a recorded mentor, its moderator (Q13). |
| XI. Survives an upgrade | PASS. Every API checked in `MOODLE_502_STABLE`. Direct writes (`messageinbound_handlers`; `forum_discussion_subs.preference`, R10) and raw reads (`forum_digests` for the existing-choice check; `forum` by id for comparing a space forum's declared settings, restored through `util::upsert_module()` / core `update_moduleinfo()`; `forum_discussions` and `forum_posts` for the existing-discussion subscription step and the first post's `created`; the engagement reads) are listed in `moodle/local_ltuse/README.md`. | GATED, as X: behaviour, not only signatures, must be confirmed on the instance. |
| Platform & Delivery | PASS on condition (Q3). A per-cohort course is a second way to keep a course to one group of people, beside the declared organisation-only course. Doug approved on 2026-10-07 an `INTENT.md` decision and a MINOR amendment (2.1.1 to 2.2.0) allowing site-declared cohort space courses (`ltct:site:cohort:<key>`, visible category `ltct:cohort-spaces`); both are drafted in this PR, with the bullets listed in the Summary. Shared delivery courses are unchanged: open, NOGROUPS. Managers are not given a role in spaces. | PASS once the amendment and the INTENT decision merge with this PR. Instance checks may run on ltuse.net before then, with test cohorts only (Doug, round 2: nobody uses the current system). |

## Project Structure

### Documentation (this feature)

```text
specs/005-community-space/
├── plan.md              # this file
├── research.md          # forum and app surveys, R1–R17, V1–V12
├── data-model.md        # Phase 1
├── quickstart.md        # Phase 1: instance scenarios
├── contracts/
│   ├── community-yaml.md          # moodle/site/community.yaml: cohort space template, teaching and area shapes, reserved names
│   ├── site-config-community.md   # validate / apply / drift for spaces, mentor and mentee sync
│   ├── inbound-mail.md            # settings/inbound-mail.yaml and the handler kind
│   ├── engagement-ws.md           # local_ltuse_community_engagement
│   └── engagement-review-cli.md   # scripts/engagement_review.py
└── tasks.md             # Phase 2 (/speckit.tasks, not created here)
```

### Source Code (repository root)

```text
moodle/site/community.yaml                      # new: cohort space template (names from the cohort), shapes teaching and area, forums, space-mentor override; no site-wide block (deferred, Q1)
moodle/site/settings/inbound-mail.yaml          # new: messageinbound_* config (rows [11, 25], purpose, why per entry; hostpass env:MOODLE_INBOUND_PASS, secret); messageinbound_enabled 0 until 015 provisions the mailbox and V2 passes (Q8)
moodle/site/inbound-mail.yaml                   # new top file (TOP_FILES): {rows [11, 25], purpose, handlers, why}, the forum handler row, expiry one week (Q20)
moodle/site/settings/notifications.yaml         # + defaultpreference_autosubscribe 1, _trackforums 1 (Q18), _mailformat 1; maildigest 1 unchanged
moodle/site/settings/forums.yaml                # new: enablerssfeeds 0, forum_enablerssfeeds 0, enableportfolios 0 (Q14), maxeditingtime 1800 (Q11), usetags 1 (Q19)
moodle/site/settings/logging.yaml               # new: loglifetime 365 (Q21)
moodle/site/roles.yaml                          # new spacemember (Q17, keeps participants and messaging per Q6); teacher prohibits mod/forum:exportforum, prevents exportdiscussion, exportpost and exportownpost, keeps postprivatereply (Q14, round 2)
moodle/site/organisations.yaml                  # category key cohort-spaces (idnumber ltct:cohort-spaces, visible) (Q3); the one opt-in field `space: area` on entries that want a space (round 2); no neutral marker (Principle III: it would point at protected people)
scripts/site_config.py                          # TOP_FILES + community.yaml; _validate_community(); _validate_organisations() accepts the optional `space` key (value `area` only, refused on `independent`), its "only key and name" comment updated to the VII amendment bullet; enableportfolios 1 refused; inbound handler kind; payload; _summary(); reserved names (community-yaml contract)
scripts/check_course_package.py                 # refuse the reserved course slugs (community-yaml contract, "Reserved names")
scripts/admin_files.py                          # _check_course_mentors also accepts ^ltct:site:cohort:(?:[1-9][0-9]*|[a-z][a-z0-9-]{0,29})$ (numeric teaching key, single-digit ids included, or an organisation key) only with a cohort value (refused with learner_email), and only with the space's own cohort (teaching: the cohort id equals the key; area: `ltct:org:<key>`); the preview prints the expected space key, never a cohort name; intake COURSE unchanged
tests/test_ltct_admin.py                        # space row with its own cohort accepted (ids 7 and 42, and an Area key); another cohort refused; with learner_email refused; intake space course refused
scripts/engagement_review.py                    # new: quarterly review CLI (terminal only; no --out)
moodle/local_ltuse/classes/siteconfig/cohortspaces.php     # new: space course named from the cohort, forums, enrol_cohort instance per shape, the space-mentor override; instance kept disabled until a cohort-mentor row first exists (Q13)
moodle/local_ltuse/classes/siteconfig/inbound.php          # new: messageinbound_handlers row apply + drift
moodle/local_ltuse/classes/siteconfig/applier.php          # call cohortspaces after apply_structure(); inbound
moodle/local_ltuse/classes/siteconfig/inspector.php        # drift for spaces (incl. "no moderator", forum-setting edits) and the handler row
moodle/local_ltuse/classes/admin/course_mentor_sync.php    # accept ltct:site:cohort:*, precedence 2 only; space member role as studentroleid; no mentor groups in spaces; mentor and mentee digest overrides per shape, recorded, never over a personal choice (Q9, Q10, round 2); enables a space's cohort instance when a cohort-mentor row exists, never disables it; in a space whose cohort is deleted, keeps the mentor, their role, their overrides and their row (orphan-record removal skips it) and changes nothing else (round 2); subscriptions
moodle/local_ltuse/classes/admin/course_mentor_rules.php   # test: spacemember counts in a space, never in ltct:<slug>
moodle/local_ltuse/classes/admin/course_mentor_records.php # accept a space course for cohort-mentor rows naming the space's own cohort only
moodle/local_ltuse/classes/admin/enrolment_rules.php       # explicit refusal of ltct:site:* for ltct_admin cohort enrolment
moodle/local_ltuse/classes/observer.php, db/events.php     # existing file (already has cohort_deleted): discussion_created + post_created → subscribe the author's mentors in delivery courses (R10, Q10); the cohort-space callbacks also go here: cohort_created / cohort_updated → space for a matching teaching cohort, name kept in step; cohort_deleted → space, posts and mentor kept (round 2) (or reconcile task)
moodle/local_ltuse/classes/mentor_subscriptions.php        # new helper: subscribe a mentor to their mentees' discussions, with the first-post `created` race fix (R10)
moodle/local_ltuse/classes/trackforums.php                 # new helper: enable_existing(), read tracking on for existing accounts through user_update_user(), counts only
moodle/local_ltuse/classes/admin/digest_overrides.php      # new helper: write, record and release the per-forum digest overrides (R4, round 2)
moodle/local_ltuse/classes/util.php                        # existing: util::upsert_module() (L167), reused by cohortspaces to create and restore space forums
moodle/local_ltuse/classes/privacy/provider.php            # existing: declare local_ltuse_digest_override (T019)
moodle/local_ltuse/classes/task/course_mentor_reconcile.php # existing: orphan-record removal keeps a space's row while its course exists (T062)
moodle/local_ltuse/classes/external/admin_preview_course_mentors.php # existing: space rows in the preview (T057)
moodle/local_ltuse/classes/siteconfig/drift.php            # existing: drift exclusion for spaces and teaching cohorts (T061)
moodle/local_ltuse/db/install.xml, db/upgrade.php           # new table local_ltuse_digest_override (userid, forumid, value, released, timecreated): the overrides sync wrote, and the ones a person reset (round 2)
moodle/local_ltuse/cli/trackforums_existing.php            # new one-off: read tracking on for existing accounts (round 2); run on the server by the site team; prints counts only
moodle/local_ltuse/classes/learner_home.php                # "Your cohort" onward route [spec 007 deliverable: add the slot through 007's onward-route mechanism; 007's harness tests must pass unchanged]; mentor link unchanged (Q22)
moodle/local_ltuse/classes/learner_home_rules.php          # new `cohorts` list
moodle/block_ltuse/ (block_ltuse): classes/output/home.php, templates/block.mustache and mobile_block.mustache doc comments, lang string onward:cohort, tests/block_test.php
moodle/local_ltuse/classes/external/community_engagement.php  # new: aggregate-only WS on ltuse_admin (Q21)
moodle/local_ltuse/db/services.php, db/access.php, version.php, lang/en/local_ltuse.php
moodle/local_ltuse/README.md                   # direct writes: messageinbound_handlers, forum_discussion_subs.preference; raw reads: forum_digests, forum_discussions, forum_posts (sync and observer), forum (space forum settings, apply and drift) and the engagement reads
tests/test_site_config.py                      # community.yaml, inbound kind, secret path, roles changes, reserved names, `space: area` accepted on an organisation entry and refused on independent, enableportfolios 1 refused
tests/test_publish_moodle.py                   # reserved slugs refused; publisher never reaches ltct:site:
tests/test_engagement_review.py                # new
tests/learner_home_harness.php                 # cohort route
moodle/local_ltuse/tests/siteconfig_cohortspaces_test.php  # new
moodle/local_ltuse/tests/siteconfig_inbound_test.php       # new
moodle/local_ltuse/tests/course_mentor_sync_test.php       # new (spec 008's sync tests stay in admin_test.php, unchanged): spaces, shapes, mentor and mentee overrides, personal choice kept, a reset kept, a leaver's override kept with no exception, members' overrides after the last mentor goes, a deleted teaching cohort's mentor kept with access (round 2), subscriptions, mentor gate from disabled to open
moodle/local_ltuse/tests/community_engagement_test.php     # new
moodle/local_ltuse/tests/mentor_subscriptions_test.php     # new
moodle/local_ltuse/tests/trackforums_test.php              # new
moodle/local_ltuse/tests/admin_test.php                    # existing: spec 008's sync tests, unchanged
scripts/ltct_admin.py                                      # existing: space rows for course mentors (T055)
scripts/publish_moodle.py                                  # existing: reserved slugs refused, if needed (T054)
moodle/site/README.md                                      # existing: removing a space's mentor after its cohort is deleted (T079); operator notes (T094)
.github/workflows/site-config.yml                          # existing: wire the new Python tests (T087)
moodle/REQUIREMENTS.md                         # rows #9, #10, #11, #19, #20, #25
INTENT.md                                      # Decisions: "Community starts inside Moodle, per cohort" and the cohort-space course ruling, citing Doug 2026-10-07 (Q3); site-wide space deferred (Q1)
.specify/memory/constitution.md                # MINOR amendment 2.1.1 → 2.2.0, bullets in II, Platform & Delivery and VII (Summary), citing 2026-10-07; drafted in this PR
CLAUDE.md                                      # Moodle section: cohort spaces, reply by email
```

**Structure Decision**: Extend site config with one new siteconfig class on the office-hours model and one new declaration kind. Extend spec 008's mentor sync rather than add a second mentor mechanism. The publisher gains only tests proving it cannot reach a space.

**Build order**: (1) site-wide defaults, `teacher` and `spacemember` roles, `loglifetime`, and the one-off read-tracking script for existing accounts; (2) mentor and mentee digest overrides with their record table, and the mentor subscription observer in delivery courses; (3) incoming mail declaration and handler kind, enabled once 015 provides the mailbox and V2 passes; (4) the constitution amendment and INTENT decision, then cohort spaces (area shape once 002 applies its Areas, teaching shape once 002 defines the teaching cohort as a Moodle cohort); (5) learner-home route; (6) engagement review. The site-wide space is not in the build order (Q1, deferred).

## Resolved by the purposes

The first plan's questions that Doug's purposes answered or removed:

- **Organisation rooms (old Q2/Q3)**: replaced by cohort spaces. The constitution question became Q3.
- **"Which reply helped" ratings (old Q8)**: dropped; the purposes ask for conversation, not marked answers.
- **Topic forums per competency category (old Q11)**: only in the site-wide space, deferred (Q1).
- **Site-wide co-membership (old Q18)**: arises only if the deferred site-wide space is taken up (Q1).
- **Who counts as a sender's name**: decided in round 2: everyone's recognisable full name; a pseudonym only for spec 016 protected people; never a username, id or code.
- **Offline**: Doug accepts what the app does; the app survey records it.
- **Push device cap**: no longer a design reason; ltuse.net is on the Premium plan (round 2).

## Decisions (Doug, 2026-10-07)

1. **Q1 — Site-wide space**: deferred, out of this build; revisited after the engagement reviews. US6 and FR-012..FR-014 stay in the spec, marked deferred; nothing in the build order, contracts or tasks builds it.
2. **Q2 — Cohort**: both kinds. Teaching-cohort spaces (spec 002 must define the teaching cohort; an ALTC creates and fills it through 002's scripted action for now; teachers deferred to spec 017) and Area spaces as a second declared shape. Area shape: Optional subscription, no mentor override. Consequence: `community.yaml` declares two shapes. *Round 2*: any organisation entry opts in with the one field `space: area`; `independent` and 016 neutral entries are refused; no override for anyone in an Area space.
3. **Q3 — Space courses and the constitution**: amend. An `INTENT.md` decision and a MINOR constitution amendment allow site-declared cohort space courses (`ltct:site:cohort:<key>`, visible category `ltct:cohort-spaces`). Drafting both is a task in this feature's PR, citing 2026-10-07. Neither file is edited now. *Round 2*: MINOR confirmed, 2.1.1 to 2.2.0; bullets in the Summary.
4. **Q4 — A cohort's mentors**: recorded per cohort through `ltct_admin.py` as spec 008 cohort-mentor rows on the space. Members' default mentors are not let in. A space is its cohort plus its recorded mentors. *Round 2*: recorded by the site team; ALTC self-service deferred to spec 017.
5. **Q5 — Fellow students**: everyone in the course, through spec 012's course forum. No cohort-only conversation inside a shared course.
6. **Q6 — Seeing and messaging**: members see the space's participant list and may message each other. Spec 016 neutral entries still never get a space. *Round 2*: accepted for Area spaces too, where hundreds of people see and can message each other; not re-asked.
7. **Q7 — When a cohort ends**: spaces stay open; no read-only or end state. Apply never deletes a space. *Round 2*: this includes a space whose last mentor is removed.
8. **Q8 — The mailbox**: yes, plan on one IMAP mailbox with case-preserving `+` subaddressing and cron every minute, recorded as a spec 015 operator task. V2 on the instance is still required before reply by email is enabled.
9. **Q9 — Email cadence**: automatic per-post mail. local_ltuse sets a per-forum digest override of 0 for mentors on the forums they mentor in, and for mentees on the forums where they have a mentor. Everyone else keeps the daily digest. FR-002 and FR-005 are decided. *Round 2*: teaching-space members get per-post on Problems only and the digest on Team, the same as the mentor; overrides yield to a personal choice.
10. **Q10 — What a mentor is sent**: in delivery courses, only the discussions their own mentees start (new observer code). In a teaching-cohort space, per-post on Problems and the digest on Team. *Changed 2026-10-07 (Doug, round 2)*: "start **or post in**". A mentee's reply in a classmate's discussion also subscribes their mentor; the `post_created` observer and its test stay.
11. **Q11 — The 30-minute delay**: keep `maxeditingtime` at 30 minutes; declared so drift reports a change.
12. **Q12 — Forums in a space**: two, "Team and friendship" and "Problems and questions"; no automatic locking.
13. **Q13 — Moderation**: the space's recorded mentor moderates, as Course mentor (pin, move, remove). The mentor also handles reports sent as post permalinks. Consequence: the gate "no real learners until a moderator is named" is met per cohort by a recorded mentor; a space's cohort enrolment stays disabled until it first has one. *Round 2*: space mentors may also lock by hand.
14. **Q14 — Private reply, export, portfolios**: `teacher` keeps `mod/forum:postprivatereply`. *Round 2, exact set*: `teacher` loses `mod/forum:exportforum` (prohibit), and `mod/forum:exportdiscussion`, `mod/forum:exportpost` and `mod/forum:exportownpost` (prevent). `enableportfolios` stays 0 site-wide. Coordinate with 012 and 016, whose roles this touches.
15. **Q15 — WhatsApp**: spaces sit beside WhatsApp; no link and no Moodle group chat. `customint2` stays 0; V13 is dropped.
16. **Q16 — Engagement definitions**: coordinator self-reported baseline accepted; an active learner is any learner who logged in during the quarter; mentors' replies count as answers; counts below 5 suppressed, figures site-wide only.
17. **Q17 — Member role**: a declared `spacemember` role. Per Q6 it does not prohibit viewing participants or messaging; it exists to keep spaces out of the progress, programme and pilots reports.
18. **Q18 — Read tracking**: on by default for new accounts (`defaultpreference_trackforums: 1`). *Round 2*: also for existing accounts, by a one-off script.
19. **Q19 — Tags and a message handler**: keep forum tags on. A custom inbound handler so Moodle messages can be answered by email is recorded as later work, not this build.
20. **Q20 — Reply expiry**: keep one week.
21. **Q21 — Log retention and the review**: `loglifetime` 365 days; the engagement function runs on the existing `ltuse_admin` service under `local/ltuse:administer`.
22. **Q22 — Spec 003's messaging**: keep the landing-page mentor link as it is (opens Moodle messaging). *Round 2*: kept; spec 003's mentor onboarding gains a note telling mentors to use the forum for anything they want to answer by email (Round 2, item 25).
23. **Q23 — Openness of mentoring threads**: accepted. Mentoring threads in shared course forums are readable by everyone in the course, the price of reply by email.

## Round 2 (Doug, 2026-10-07)

Doug's answers to the checker's findings, applied across every artifact.

1. **Q10 widened**: mentors are subscribed to discussions their mentees start or post in; a dated change to Q10 (item 10 above). The `post_created` observer and its test stay.
2. **Teaching-space members**: per-post (answerable) on Problems only, digest on Team, the same as the mentor. Supersedes the literal Q9 reading for members.
3. **Area spaces**: Optional subscription, digest, no per-post override for anyone. A mentor there replies by email only if they change their own setting (and subscribe).
4. **Last mentor removed**: the space stays open. The mentor gate applies only to a space's first opening; drift reports "no moderator". Sync never disables a cohort instance it has enabled.
5. **Engagement**: posters are learners only; mentors count only in the "answered" figure. Confirmed: "logged in" is any logged event in the quarter; a learner is an account with a Student or `spacemember` enrolment.
6. **Personal digest choices**: overrides never replace a person's own choice; sync records which overrides it wrote (`local_ltuse_digest_override`), so removal never erases a personal choice.
7. **Forum export**: `teacher` loses `exportdiscussion`, `exportpost`, `exportownpost` and `exportforum`; portfolios off (Decisions 14).
8. **Large Area spaces**: not re-asked; accepted for Area spaces too (Decisions 6, Key risks).
9. **Mentor-gate deadlock**: the gate rests on the cohort-mentor row alone. Sync enables the instance first; the next sync enrols the mentor, once members count as active. A test starts disabled and ends with mentor and members enrolled.
10. **Teaching-space key**: the numeric Moodle cohort id. `admin_files.py`'s pattern accepts single-digit ids; test cohorts are recognised by a `test-` prefix on their Moodle cohort name, not on the key. The course name shown to users is the cohort's own human-readable name, never "Cohort space: 42" or any id.
11. **Who records cohort mentors**: the site team, through `ltct_admin.py`. ALTC self-service is deferred to spec 017.
12. **Space mentors' role**: `teacher` (Course mentor). Doug's 2026-10-05 acceptance of what a course mentor sees (members' email addresses through `moodle/site:viewuseridentity`) covers space mentors, Area spaces included.
13. **Opting in**: any organisation entry may opt in to an Area space with one declared field, `space: area` (replacing `space: area` plus `no_space`). Validate refuses it on `independent`; spec 016 neutral entries are refused in review, because a public `neutral: true` marker would point at the people 016 protects (Principle III), so validate cannot check them (confirmed in item 22). FR-006 is opt-in, not a MUST for every Area.
14. **Amendment level**: MINOR, 2.1.1 to 2.2.0, with bullets in II (idnumber forms), Platform & Delivery and VII (Summary). II is "PASS once the amendment merges". Cites 2026-10-07.
15. **002 dependency**: 005 requires spec 002 to choose a Moodle cohort for teaching groups (spec Dependencies; "What cannot start yet").
16. **Where verification runs**: on ltuse.net now. Doug: nobody is using the current system, "Just do it." The gate "no space on ltuse.net before the amendment merges" and the disposable-test-server requirement are removed. Test accounts and test cohorts only (constitution X).
17. **Smaller fixes** (checker items 17–24): the IX row's app-plan reference is resolved (no marker remains) and the NEEDS CLARIFICATION sentence now names both marker kinds; data-model cites 002's FR-015/FR-019 for the scripted action; one reserved-names list, in the community-yaml contract, cited from the I row; the engagement CLI is terminal only (no `--out`) and takes `--quarters-without-trigger N`; `usetags: 1` is declared in `forums.yaml` and both tables; the inbound-mail files carry `rows: [11, 25]`; the spec's Constitution Check has an XI line, lists every host burden under IX, and says under X that burdens are not covered while the operator is undecided; every mod_forum table read is listed in the README's direct-access list.
18. **Locking**: space mentors may lock by hand. `community.yaml` declares an override granting `moodle/course:manageactivities` to `teacher` in space course contexts only; drift reports forum-setting edits and apply restores them.
19. **Read tracking for existing accounts**: switched on by a one-off script (`moodle/local_ltuse/cli/trackforums_existing.php`, build step 1). It runs against Moodle on the server, prints counts only and writes no learner data to git.
20. **App plan**: ltuse.net is on the Premium plan; `site.yaml` is right. Spec 015's FR-010 wording needs fixing (follow-up below; 015's files are not edited here).
21. **Names**: "It better not be cryptic so nobody knows who anybody is. The protected one is the exception, not the rule. Make the protected person suffer and not everybody else." Everyone appears by their recognisable full name in posts, participant lists and emails; only spec 016 protected people show a pseudonym. Users are never shown login usernames, numeric ids or codes.

Doug's answers to the plan's last four questions, the same day:

22. **Neutral entries**: enforced by a review check. The reviewer of any change to `organisations.yaml` confirms that no spec 016 neutral entry declares `space: area`, and 016's procedure for a neutral entry says never to declare it. No public neutral marker is written to the repo (Principle III), so validate does not check it (follow-up for 016 below).
23. **Area-shape checks**: PHPUnit only for now (`siteconfig_cohortspaces_test.php`, `course_mentor_sync_test.php`), until a real Area mentor is recorded. No real Area entry is opted in for testing and no Area space is deleted after the checks. Once a real Area entry has opted in and its first real mentor is recorded, the Area-shape instance check runs on that space (quickstart, "Area shape, later").
24. **Teaching cohort deleted in Moodle**: the course and its posts are kept and the recorded mentor takes over. Core deletes the cohort's enrol instance, so the members lose access; the mentor's own enrolment, through course-mentor sync's `enrol_self` instance, is not removed with it, so they keep the space and its posts. Sync keeps that mentor, their role, their overrides and their cohort-mentor row (orphan-record removal skips a space's rows) and changes nothing else there. Drift flags `cohort deleted` for the site team, who decide the space's future. A test covers it.
25. **Landing-page mentor link**: stays as it is (Q22). Spec 003's mentor onboarding gains a note telling mentors to use the forum for anything they want to answer by email (follow-up for 003 below).

**Follow-ups outside this spec** (not edited here): spec 015 FR-010 to record the Premium plan as chosen; spec 016's procedure for placing someone under a neutral entry to say that the entry never declares `space: area` (no neutral marker goes in the repo; the review check of item 22 rests on it); spec 003's mentor onboarding to tell mentors to use the forum for anything they want to answer by email, since the landing-page mentor link opens Moodle messaging (item 25); spec 002's `contracts/declaration.md` organisation entry shape to gain the optional `space` field (the code comment in `_validate_organisations()` changes with it, in this PR); spec 002's plan step to take "teaching groups are Moodle cohorts" as a constraint; spec 017 to cover ALTCs recording their own cohorts' mentors.

## Remaining questions

None. Doug decided the last four on 2026-10-07 (Round 2, items 22–25).

Spec 012's re-plan input 3 (whether peers know whom they review) is 012's question, not this spec's; Doug's round-2 names rule is input to it.

## Key risks

- **Teaching cohorts don't exist yet.** Teaching spaces wait on 002 choosing a Moodle cohort for teaching groups and defining it; Area spaces wait on 002 applying its Areas.
- **Mailbox on a shared host.** If ltuse.net cannot give a mailbox with plus-addressing, purpose 1's core promise (reply by email) is unavailable until 015 provides one (Q8). This plan says so instead of claiming it.
- **Digest default silently defeats reply by email.** If the overrides (R4, Q9) are not built or not applied, every mentor gets digests and nothing they reply to lands. A person who already chose a digest for a forum keeps it (round 2), so for them too the route stays one-way until they change it.
- **A reply address works as a credential.** Validation checks only the spoofable From header. The one-week expiry (Q20) and the confirmation step limit the damage (R3).
- **Mentor flood.** Per-discussion subscription in delivery courses is new observer code that must be tested against cohort sync and mentor changes (R10).
- **Space mentors hold `manageactivities`.** The lock override also lets a space mentor change forum settings, add activities or delete a forum. Drift reports it and apply restores the declared settings, but a deleted forum's posts cannot be restored; mentor guidance says so.
- **Space names come from cohort names.** A cohort name is free text an ALTC types; two cohorts with one name, or a name equal to a course's short name, block the space rather than adding a code (FR-006). Forum email subjects carry the course short name, which is why it is the cohort name too (V6).
- **The `ltct:` rule is copied in many places.** Every reused tool must explicitly accept or refuse `ltct:site:`. Tests assert each one (R7).
- **Spec 008's sync is shared code.** In delivery courses, the sync's enrolment, role and group outcomes are unchanged and 008's existing tests pass unchanged. The sync newly sets mentor and mentee digest overrides on the course forum, and a new observer subscribes mentors to their mentees' discussions; new tests cover both.
- **The `teacher` role is shared.** Removing forum export touches 012's and 016's role; their tests run with this change.
- **Purpose 1 is met for replies only.** A mentor starts a conversation, posts a prompt or pins only after logging in (R1).
- **Wide Area spaces.** Hundreds of members see and can message each other (Q6, accepted for Area spaces too, round 2), and the space mentor sees their email addresses (Doug's 2026-10-05 acceptance, extended to space mentors in round 2). Optional subscription and digest mail keep the mail volume down.
- **An email reply posts the whole body**, signature and Outlook-style quoted headers included (R11).
- **The landing page's mentor link leads to messaging**, the one channel a mentor cannot answer by email (Q22, kept). Spec 003's mentor onboarding tells mentors to use the forum for anything they want to answer by email (round 2, follow-up).
- **Neutral entries rest on review.** Nothing in validate stops a neutral entry declaring `space: area`; the reviewer of each `organisations.yaml` change and 016's procedure do (round 2, item 22).
- **A deleted teaching cohort leaves a one-person space.** Its members lose access with the cohort's enrol instance; the mentor keeps the space and its posts, and drift flags `cohort deleted` until the site team decides what happens to it (round 2, item 24).
- **Missed opening post.** A discussion subscription made after the post was created drops that post (R10); the preference-time fix must be tested.
- **Verification on the live server.** Instance checks run on ltuse.net with test cohorts (round 2). Apply never deletes, so test spaces stay until removed by the written procedure (quickstart); a left-over test space shows as drift. The Area shape is tested by PHPUnit only until a real Area mentor is recorded, so no real Area space is opened for testing (round 2, item 23).

## Operations burden

| Burden | Carrier |
|---|---|
| The incoming mailbox: provisioning with `+` subaddressing, password rotation, keeping `tobeconfirmed` clear, watching pickup failures | The Moodle operator, as a spec 015 operator task (Q8; operator still undecided, `INTENT.md` L68) |
| Cron every minute for pickup and forum mail on the shared host | The operator (015, Q8) |
| Outgoing mail volume and host send limits (per-post mentor and mentee mail, confirmations, `messageprocessingsuccess` notices) | The operator (015) |
| Mailbox content retention (it holds learner text and the `tobeconfirmed` folder) | The operator (015) |
| Log table growth at `loglifetime` 365 days | The operator (015) |
| Mentor guidance: an email reply is posted as written, signature included; mentors moderate and lock in their space, handle reported permalinks, and must not delete a forum | The maintainer, with spec 003's mentor onboarding |
| Moderation of each cohort space and triage of reports | Its recorded mentor (Q13, locking included); a space with no moderator, or whose cohort was deleted (`cohort deleted`), is reported by drift to the site team |
| Checking in review that no spec 016 neutral entry declares `space: area` | The reviewer of each `organisations.yaml` change (round 2, item 22) |
| The Area-shape instance check, once a real Area entry has its first real mentor | The site team (round 2, item 23) |
| Recording each cohort's mentors | The site team through `ltct_admin.py` (spec 008, Q4; ALTC self-service deferred to 017) |
| Running the one-off read-tracking script for existing accounts | The site team, once (round 2) |
| Removing test spaces and test cohorts after instance checks on ltuse.net | The site team, by the quickstart's written procedure |
| Quarterly engagement review, including asking coordinators about the WhatsApp baseline | The maintainer (Doug), about one hour a quarter |
| Guidelines text | The maintainer, through `community.yaml` |

## Complexity Tracking

| Item | Why needed | Simpler alternative rejected because |
|---|---|---|
| `inbound_handlers` declaration kind (direct write to `messageinbound_handlers`) | Core keeps the forum handler switch in a table and has no API for it; without it reply by email is off | Admin UI click: not config as code, invisible to drift |
| Per-discussion mentor subscription observer (`discussion_created`, `post_created`) plus a direct write setting `forum_discussion_subs.preference` to the first post's `created` | Only way to send a mentor their mentees' posts without every learner's (Q10); core drops posts created before the subscription time, which would lose the opening question | Whole-forum subscription floods; whole-forum subscribe then unsubscribe from non-mentee discussions avoids the write but floods until the observer runs and cannot follow a mentee into another's discussion; manual subscription is not reliable |
| A record of the overrides sync wrote (`local_ltuse_digest_override`) | Removal must undo only what sync set, never a personal choice (round 2) | Comparing values alone cannot tell a sync-written 0 from a person's own 0 |
| Two space shapes (teaching, area) | Doug wants both (Q2); an Area's membership is too wide for Auto subscription and per-post mail | One shape: either floods Area spaces or under-serves teaching cohorts |
| Cohort selector instead of a per-cohort list for teaching spaces | Teaching cohorts are made at run time by an ALTC; a list would need a maintainer PR per cohort and put cohort keys in the public repo | Hand-kept list: breaks VI and US2 |
| One course per cohort (Q3, amendment) | Enrolment is the only core control that hides a space from search, digests and the app | Groups per cohort in one course: walls people, against FR-019; availability rules fail open |
| Space-mentor override of `moodle/course:manageactivities` | The only core capability that gives a manual lock (round 2) | A narrower lock capability does not exist in core |
| Custom engagement WS | Only route to the four figures with server-side suppression | Report builder has no question/reply split and no suppression |
