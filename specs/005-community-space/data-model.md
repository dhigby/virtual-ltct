# Data Model: Community Space Beyond Courses

The repo holds structure only. Everything marked **Moodle data** lives only in Moodle: posts, memberships, cohort names and members, mentor records, subscriptions, the record of overrides sync wrote, engagement counts. It is never written to the repo (Principle III).

Decisions applied: Doug, 2026-10-07, both rounds (plan.md, "Decisions" and "Round 2").

## Cohort *(defined by spec 002, consumed here)*

Two kinds get a space (Q2).

| Kind | idnumber | Made by | Notes |
|---|---|---|---|
| Teaching cohort | the namespace 002 fixes; a **Moodle cohort**, which 005 requires 002 to choose (round 2) | an ALTC, through 002's scripted management action (002 FR-015, FR-019); teachers deferred to spec 017 | name and members are **Moodle data**; never the admin UI |
| Area cohort | `ltct:org:<key>` (002 FR-014, e.g. `sil-americas`, `silp-eurasia`) | site config from `organisations.yaml` | only entries declaring the one opt-in field `space: area` (round 2); any entry may, except `independent`, which validate refuses, and a 016 neutral entry, which review refuses (no neutral marker is ever written to the repo, Principle III) |

| Field | Source | Notes |
|---|---|---|
| key | derived at run time | Teaching: the numeric Moodle cohort id (round 2), not personal and never a name; never written to the repo. Area: the organisation key, already public in `organisations.yaml`. |
| name | read live | Teaching: the Moodle cohort's name (**Moodle data**). Area: the entry's `name` in `organisations.yaml`. |

## Cohort space

| Field | Value | Notes |
|---|---|---|
| course idnumber | `ltct:site:cohort:<key>` | Colon form; matches no `ltct:<slug>` rule (R7). ≤ 100 characters. Allowed by the MINOR constitution amendment (2.2.0) drafted in this PR (Q3). Never shown to users. |
| shape | `teaching` or `area` | From which selector matched (FR-006a). |
| fullname / shortname | both the cohort's name (above), read live (round 2) | What users see, in the course list, the landing page, breadcrumbs and forum email subjects (which carry the short name). Never "Cohort space: 42", an id or a code. Kept in step on apply and on `cohort_updated`; drift reports a mismatch without printing either name. If the name is already another course's short name, the space is `blocked: name_taken` and no code is added. A Course mentor lacks `moodle/course:update` and cannot rename it. |
| category | `ltct:cohort-spaces`, declared in `organisations.yaml`, **visible** | Q3. Courses created with `visible = 1`; isolation is by enrolment, not category visibility. |
| groupmode | 0, not forced | Never 1. |
| enrolment | one `enrol_cohort` instance: `customint1` = the cohort, `customint2` = 0 (no group chat, Q15), role `spacemember` (Q17) | Found before adding; `add_instance` does not de-duplicate. Created **disabled**; enabled by course-mentor sync when the space has a cohort-mentor row (Q13 gate, on the row alone, round 2); never disabled again by sync. The default manual instance is disabled and left empty. |
| space-mentor override | `teacher` allowed `moodle/course:manageactivities` in the space's course context | Round 2: lets the space mentor lock a discussion by hand. Set by apply through `assign_capability()`; drift reports it missing. |
| other | `newsitems` 0 (no Announcements forum), `showreports` 0 | `create_course()` side effects. |
| mentor enrolment | course-mentor sync, role `teacher`, from cohort-mentor rows (precedence 2 only) | R9, Q4. Its `enrol_self` instance is expected; no mentor groups in a space. |
| end state | none | Q7: a space stays open; apply never deletes or freezes it, and losing its last mentor does not close it (round 2). |

### Member role `spacemember` (Q6, Q17)

Archetype student. Keeps `moodle/course:viewparticipants`, `moodle/user:viewdetails` and messaging, so members see the participant list and message each other (Q6). Keeps `mod/forum:allowforcesubscribe`. Its only purpose beside student is that the progress, programme and pilots reports, which filter on role `student`, do not show spaces.

### Forums in a space (Q12)

| Key | idnumber | type | forcesubscribe (teaching / area) | tracking | lock after | attachments |
|---|---|---|---|---|---|---|
| `team` | `<space>:team` | general | INITIALSUBSCRIBE / OPTIONAL (at creation only) | optional | none | 3, 5 MB total |
| `problems` | `<space>:problems` | general | INITIALSUBSCRIBE / OPTIONAL (at creation only) | optional | none | 3, 5 MB total |

Exactly these two; no automatic locking (Q12); the space mentor may lock a discussion by hand (round 2). Guidelines are each forum's intro (drift-compared); a short pinned pointer discussion is posted once at creation. Auto is acceptable in a teaching space because its threads are cohort-open by design (R8); an Area space is too wide for it. Posts are **Moodle data**. Because the space mentor holds `manageactivities`, drift reports any change to a forum's declared settings, and apply restores them (except `forcesubscribe`, which apply never changes after creation). Drift reads the `forum` row by its id (a raw read, listed in the README); apply restores through `util::upsert_module()`, which calls core `update_moduleinfo()`.

### State transitions

| Event | Result |
|---|---|
| A cohort matching a selector exists (apply, `cohort_created` observer or reconcile) | Space course (named from the cohort), forums, the mentor override and a disabled enrol instance created; later applies report `ok`, or `waiting: no_mentor` until a mentor is first recorded. |
| First cohort-mentor row recorded for the space | Sync enables the cohort instance (gate on the row alone); members are enrolled through cohort sync; the **next** sync, once members count as active, enrols the mentor and sets the overrides (round 2). |
| Person added to cohort | Enrolled as `spacemember`; in a teaching space, Auto subscription subscribes them to both forums, and sync sets their Problems 0 / Team 1 overrides. |
| Person removed from cohort | `unenrolaction = 3`: core suspends them and removes their role, no mail; posts and subscriptions kept. Sync cannot call `forum_set_user_maildigest()` for someone without `mod/forum:viewdiscussion`, so it leaves their overrides and its records in place and does not retry; an override is harmless while they are suspended. The same holds for a delivery-course mentee whose cohort enrolment is removed. |
| Rejoins | Active again; mail resumes; the kept overrides hold again, and sync writes nothing new. |
| Last cohort-mentor row removed | Sync removes the mentor and only the overrides it wrote for them. The cohort instance **stays enabled**: the space stays open (Q7); drift reports `no moderator` until a new mentor is recorded (round 2). Members keep their Problems 0 / Team 1 overrides: in a teaching space they follow membership, not whether the space has a mentor; people who join later get them too. |
| Cohort renamed in Moodle | Apply or the `cohort_updated` observer renames the space to match. |
| Cohort no longer matches a selector | Drift reports `extra`; the space stays open (Q7); apply never deletes a space (posts are learner data). |
| Cohort deleted in Moodle | The course and its posts are kept and the recorded mentor takes over (round 2). With `enrol_cohort/unenrolaction` 3, core removes the cohort instance's role assignments and disables it (it is not deleted; `customint1` still names the gone cohort, R18), so members lose access; sync never re-enables it and drift treats it as expected. The mentor's own enrolment is course-mentor sync's `enrol_self` instance, not the cohort's, so they keep the space and its posts. Sync keeps that mentor, their `teacher` role, their overrides and their cohort-mentor row (orphan-record removal skips a space's rows) and changes nothing else in the space. Drift flags `cohort deleted` for the site team, who decide its future; apply never deletes it. |

## Cohort mentor *(spec 008 record, extended)*

| Field | Value |
|---|---|
| table | `local_ltuse_course_mentor` (existing) |
| courseid | the space course |
| cohortid | the space's cohort |
| mentorid | the mentor (**Moodle data**) |
| made by | the site team, through `ltct_admin.py` (spec 008); ALTCs doing it themselves is deferred to spec 017 (round 2) |
| precedence | 2 (cohort) only for spaces; no one-course rows and no fallback to default mentors (Q4) |
| when the cohort is deleted | the row is kept, not removed as an orphan, and its mentor stays in the space (round 2; state transitions) |

On enrol by sync: role `teacher` ("Course mentor"), so the mentor can pin, lock, move and remove posts and moderates the space (Q13, round 2). Doug's 2026-10-05 acceptance of what a course mentor sees covers space mentors, Area spaces included (round 2). Digest overrides per shape (below). The sync counts members holding the space's member role, not Student (R9).

## Mail state *(Moodle data)*

| Item | Where | Set by |
|---|---|---|
| Mentor per-forum override 0 | `forum_digests` | `course_mentor_sync` via `forum_set_user_maildigest()` (with `core_user::get_user()`), on every sync run. Forums: every forum of a delivery course where the mentor holds the synced Teacher role; the Problems forum of a teaching space. Written only when the person has no `forum_digests` row for that forum (a raw read, listed in the README) **and** no `local_ltuse_digest_override` record for it, so a personal choice is kept (round 2); each write is recorded in `local_ltuse_digest_override`. A record with no `forum_digests` row means the person set the forum back to their default: sync marks its record `released` and never writes that override again (deleting the record would let the next run write it again). That holds only while core leaves the row alone: on a person's last unenrolment from a course, `mod_forum_observer::user_enrolment_deleted` deletes their `forum_digests` rows for its forums, so an observer on the same event deletes their records for the same forums, and the override is written afresh if they are enrolled again (review of T025). Removed (-1) in the removerole step, **before** `role_unassign()`, because the call requires `mod/forum:viewdiscussion`. Sync checks that capability first: where core has already removed the role (cohort sync, `unenrolaction = 3`), it leaves the override and the record in place and does not retry (state transitions). |
| Mentor per-forum override 1 | `forum_digests` | same sync, on the Team forum of a teaching space, so Team arrives in the digest even if the mentor's account default is 0 (Q10). Same rules. |
| Mentee per-forum override 0 / 1 | `forum_digests` | same sync (Q9, round 2): for each synced mentee of a mentor in a delivery course, 0 on that course's forums; for every active member of a teaching space, whether or not it currently has a mentor, 0 on Problems and 1 on Team, the same as the mentor. Same rules. A delivery-course mentee's override is removed when they no longer have a mentor there while still enrolled; a member who leaves keeps theirs (state transitions). |
| Override record | `local_ltuse_digest_override` (new table: userid, forumid, value, released, timecreated) | Written with each override sync sets. Removal sets -1 only where an unreleased record exists **and** the current `forum_digests` value still equals the recorded value; otherwise the person has changed it, so sync keeps their setting and deletes only its record (round 2). A `released` record is the person's own reset to their default; sync never writes over it. |
| Area space | none | No override for anyone, mentors included; everyone keeps their own setting (FR-006a). |
| Discussion subscription (delivery courses) | `forum_discussion_subs` | observer on `discussion_created` and `post_created`, and sync on mentor add (which reads `forum_discussions` and `forum_posts` raw to find their mentees' existing discussions): each mentor of the author in that course, via `subscribe_user_to_discussion()`, then `preference` set to the first post's `created` if later (direct write, R10, Q10 as changed in round 2). Not run in spaces: teaching spaces subscribe the mentor to whole forums through Auto; Area spaces are on each person's own setting. |
| Account maildigest | user preference | the site default (1) for new accounts; never changed by this feature |
| Account read tracking | `user.trackforums` | the site default (1) for new accounts (Q18); switched on once for existing accounts by `cli/trackforums_existing.php` (round 2), which prints counts only |

## Incoming mail declaration

| Setting | Value |
|---|---|
| `messageinbound_enabled` | 0 until spec 015 provisions the mailbox and V2 passes (Q8), then 1 |
| `messageinbound_mailbox` | ≤ 15 characters |
| `messageinbound_domain`, `_host`, `_hostssl`, `_hostuser` | from 015 provisioning |
| `messageinbound_hostpass` | `env:MOODLE_INBOUND_PASS`, `secret: true` |
| handler `\mod_forum\message\inbound\reply_handler` | enabled 1, `defaultexpiration` 604800 (one week, Q20), `validateaddress` 1 |

## Site-wide settings

| Setting | Value | File | Status |
|---|---|---|---|
| `defaultpreference_maildigest` | 1 | `settings/notifications.yaml` | existing (row #25), unchanged |
| `defaultpreference_autosubscribe` | 1 | `settings/notifications.yaml` | new declaration (core default) |
| `defaultpreference_trackforums` | 1 | `settings/notifications.yaml` | new declaration (Q18); new accounts; existing ones by the one-off script |
| `defaultpreference_mailformat` | 1 | `settings/notifications.yaml` | new declaration |
| `enablerssfeeds`, `forum_enablerssfeeds` | 0 | `settings/forums.yaml` | new declaration |
| `enableportfolios` | 0 | `settings/forums.yaml` | new declaration (Q14) |
| `maxeditingtime` | 1800 | `settings/forums.yaml` | new declaration, value unchanged (Q11) |
| `usetags` | 1 | `settings/forums.yaml` | new declaration, value unchanged (Q19) |
| `loglifetime` | 365 | `settings/logging.yaml` | new declaration (Q21) |

## Role changes (`roles.yaml`)

| Role | Change | Why |
|---|---|---|
| `teacher` | `mod/forum:exportforum` prohibit; `mod/forum:exportdiscussion`, `mod/forum:exportpost`, `mod/forum:exportownpost` prevent; `mod/forum:postprivatereply` unchanged | Q14, round 2: no export of learners' posts across organisations; private reply kept. Coordinated with 012 and 016. |
| `spacemember` | new (above) | Q17 |

The space-mentor `manageactivities` allow is a course-context override set by `cohortspaces` apply, not a `roles.yaml` change.

## Learner-home route

`learner_home` adds one onward line per active space enrolment: "Your cohort: <space fullname>", which is the cohort's own name, linking to the space course. A person in several cohorts (for example a teaching cohort and their Area) sees one line each. No line for a suspended enrolment or a disabled instance. A new `cohorts` list sits in `learner_home_rules::onward()`, `block_ltuse\output\home` and both templates (spec 007's mechanism). The mentor link (`onward.mentors`) is unchanged and still opens Moodle messaging (Q22).

## Site-wide space *(deferred, Q1; not in this build)*

Kept for reference, as in the first plan: course `ltct:site:community`, member cohort `ltct:site:community:members` by rule (auth = manual AND username ≠ guest), topic forums per competency category, a `news` forum, the `onward.community` slot. Revisited after the engagement reviews.

## Engagement review figures

| Figure | Definition (Q16, confirmed in round 2) |
|---|---|
| `active_learners` | distinct learners with any logged event in the quarter ("logged in", counting app token sessions, which log no `user_loggedin`); a learner is an account holding a Student or `spacemember` enrolment; excluding admin, publisher, guest |
| `posters` | distinct **learners**, under the same definition, who posted in a cohort space or course forum; a post counts only if its author holds no `teacher` role in that course, so mentoring posts never count, even from a mentor who is also a learner elsewhere (round 2) |
| `posts` | those counted posts, for the quarter and the two before |
| `unanswered_rate` | share of discussions opened in Problems forums with no reply by another user, a mentor included, within 7 days; this is the only figure where a mentor counts |
| `baseline_reports` | counts typed by the operator: coordinators asked, reporting "moved towards WhatsApp", "returned to WhatsApp" (self-reported baseline accepted) |

Values 1–4 return `"<5"`; rates with a small numerator or denominator return not-computed. Site-wide only. Logs are kept 365 days (Q21).

## Engagement review outcome

Only a decision is recorded, in `INTENT.md` under `## Decisions`. Figures stay in the terminal.
