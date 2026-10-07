# Research: Community Space Beyond Courses

Rewritten 2026-10-06 for the maintainer's communication purposes (spec.md, "Communication purposes"). Findings from the first plan that still hold are kept and re-cited; anything the purposes changed was re-checked. Updated 2026-10-07 with Doug's decisions on the plan's questions (plan.md, "Decisions"): each decision below now states the chosen option and cites its Qn. Updated again the same day with Doug's round-2 decisions on the checker's findings (plan.md, "Round 2 (Doug, 2026-10-07)"), cited as "round 2".

Every Moodle API below was looked up on Context7 (`/websites/moodledev_io_5_2_apis`, `/moodle/moodle`) and then confirmed in upstream source on `MOODLE_502_STABLE` (5.x paths under `public/`). Context7 returned only the generic forum, enrol and message APIs for these questions, so the decisions rest on source. App findings are from `moodlehq/moodleapp` at tag `v5.2.1`. Repo line numbers refer to branch `005-community-space`. Workplace, MoodleCloud and paid-app-only features are not counted. docs.moodle.org refuses fetchers, so Doug's "Using Forum" list was checked feature by feature against source.

## Forum feature survey

Every feature in Doug's "Using Forum" list. "Control" is where it is set; "Purpose" is which of Doug's purposes it serves (spec.md).

| Feature | In 5.2 core? | Control | Decision | Purpose |
|---|---|---|---|---|
| Forum types: standard (general) | Yes | per forum `type` | **Use** for cohort friendly and problem forums | 3b, 3c |
| Each person posts one discussion (eachuser) | Yes | per forum | Not used in cohort spaces (a learner could never open a second topic, Q12); candidate for purpose 2 in 012 (a scenario-bank response forum, R17) | 2 (012) |
| Single simple discussion | Yes | per forum | Not used; too narrow | — |
| Q&A (post before view; waits `maxeditingtime`) | Yes | per forum; `addquestion` teacher-only | Not for peers (hides earlier help); a peer-review fallback is 012's | 2 (012) |
| Blog-like | Yes | per forum | Not needed | — |
| News / announcements | Yes | per forum, `addnews` | Only in the deferred site-wide space (Q1) | — |
| Inline reply | Yes | always on | Default | all |
| Star (favourite) discussions | Yes | `mod/forum:cantogglefavourite` (user archetype) | Default; helps a mentor track mentees | 1, 3c |
| Sort discussions | Yes | user pref `forum_discussionlistsortorder` | Default | all |
| Permalinks | Yes | always on | Default; also the report-a-post route (R11) | all |
| Forum preferences in user menu; site defaults in User default preferences | Yes | `defaultpreference_*` | **Declare** maildigest (existing, 1), autosubscribe 1, trackforums 1 (Q18), mailformat (R8) | all |
| Experimental nested view | Yes | per-user pref `forum_useexperimentalui` | Leave; not promoted | — |
| Subscribe per discussion (envelope) | Yes | web; **not in the app** | Default; app users manage subscriptions in the browser (R10) | 3b |
| Auto-subscribe on posting | Yes | `defaultpreference_autosubscribe` (core 1) | **Declare 1**: the mentee gets the mentor's reply | 1 |
| Whole-forum subscribe; forced / auto / optional / disabled | Yes | per forum `forcesubscribe` | Teaching-space forums **Auto**, Area-space forums Optional (at creation only, Q2); course forum stays 012's Optional | 3b, 3c |
| Read/unread tracking; per-forum Read tracking; last 14 days only; mark read | Yes | `defaultpreference_trackforums`, `forum_trackingtype`, `forum_oldpostdays` | Tracking optional per forum; default on for new accounts (Q18) | 1, 3b |
| Display modes flat / threaded / nested | Yes | `forum_displaymode`, user pref | Default (nested); the app has no threaded mode | — |
| Images inline as attachments; audio/video via TinyMCE | Yes | per forum `maxattachments`, `maxbytes` | **Use**: 3 attachments, 5 MB total (screenshots and one phone photo; an email reply's attachments count together, R8); video by link | 1, 3b |
| Post tags | Yes | `usetags` | **Declare 1** (kept on, Q19), with caveat (R13) | 3b |
| Post editing time limit (30 min) | Yes | `maxeditingtime`, Site security | **Declare 1800** (kept, Q11); delays every email | 1 |
| Next / previous discussion | Yes | always on; app swipe | Default | — |
| **Reply to posts via email** | Yes | `messageinbound_*` + forum `reply_handler` row | **Use**; the mentor route (R1–R4) | 1, 3c |
| Export to portfolio (exportdiscussion/ownpost/post) | Yes | `enableportfolios` (0) | **Declare 0** (Q14; learners lose `exportownpost`); `teacher` prevented `exportdiscussion`, `exportpost` and `exportownpost` too (round 2) | guard |
| Whole-forum export (`exportforum`) | Yes | capability, not portfolio-gated | **Prohibit for `teacher`** (Q14) | guard |
| Private replies (`postprivatereply`, seen by recipient and teachers) | Yes | capability | **Not the mentor route** (R5); `teacher` keeps it for one-way notes (Q14) | 1 |
| Pinned posts | Yes | `pindiscussions` (teacher) | **Use**: cohort mentor, the space's moderator (Q13), pins guidelines and prompts | 3c |
| Manual "Lock this discussion" | Yes | needs `moodle/course:manageactivities` | **Granted to space mentors only** (round 2): a declared course-context override gives `teacher` `moodle/course:manageactivities` in each space course, so the mentor locks by hand; not held by Course mentors in delivery courses, where the site team locks (FR-015) | 3c |
| "Send with no editing time delay" (mailnow) | Yes | `manageactivities`, or Q&A + `canmailnow` | Not available to Course mentors in a delivery course's standard forum; a space mentor sees it through the lock override (round 2); email replies never set it (R6) | 1 |
| Display period / timed posts (`viewhiddentimedposts`) | Yes | `forum_enabletimedposts` | Leave on; web-only to create (app cannot) | 3c |
| Automatic discussion locking (`lockdiscussionafter`, duration) | Yes | per forum | **Not used** (Q12); never on a course forum used for mentoring | 3b |
| Move discussions within the course | Yes | `movediscussions` (teacher) | Default; between the two cohort forums | 3c |
| Split discussions | Yes | `splitdiscussions` (teacher) | Default | 3c |
| Groups with forums | Yes | `groupmode` | **No groups anywhere**; cohort separation is by space enrolment (R7) | all |
| Whole-forum grading | Yes | per forum | Off; assessment is 012's | 2 (012) |
| RSS (not in Doug's list; checked) | Yes | `enablerssfeeds`, `forum_enablerssfeeds` | **Declare 0**: an RSS URL is a bearer key | guard |

## App survey (moodleapp v5.2.1)

Doug's app list predates forums; forum support below is from source.

| Feature | In the free app? | Notes | Purpose |
|---|---|---|---|
| Read forums, discussions, posts | Yes, native | `mod_forum_get_discussion_posts` | all |
| Reply inline, start a discussion | Yes | offline queue: `forum-offline.ts` `replyPost`, `addNewDiscussion` | 1, 3b |
| Attachments offline | Yes | failed upload stored on device, sent on sync (`post.ts` L441-479, `forum-helper.ts` L59-175) | 1, 3b |
| Edit a sent post offline | No | `updatePost` online only | — |
| Q&A post-before-view | Yes (server-enforced) | `qandanotify` shown | 2 |
| Star, sort, pin, lock | Yes | `discussion-options-menu` | 3c |
| Display mode | Flat oldest / newest, nested | threaded falls back to flat | — |
| Unread counts | Yes | opening marks the discussion read | 1 |
| Subscribe/unsubscribe a discussion or forum | **No** | only the toggle when starting a discussion | 3b |
| Private reply | Partly | checkbox only on a reply, not on the opening post | — |
| Timed posts, mailnow | No | web services hard-code `timestart=0`, `mailnow=0` | — |
| Export, move, split, grading | No | "Open in browser" | — |
| Notification preferences (processors per type) | Yes | no maildigest or autosubscribe editor | 1 |
| Push for forum posts | Yes, to per-post (digest 0) recipients only | airnotifier; a digest user gets no push or popup until the digest (R4). Plan: Premium, as `site.yaml` L42 records (Doug, round 2); 015 FR-010's wording is a follow-up | 1, 3 |
| Download course for offline | Yes | forum prefetch: 2 pages × 10 discussions | 3b |
| Messaging participants, group conversations | Yes | members may message each other (Q6); no group conversation (Q15); no email reply in core | 3b |
| QR / deep links to course, forum, discussion | Yes | link handlers | 3b |
| Course list ("My courses") | Yes | a cohort space appears as a course | 3b |

## R1. Reply by email is forum-only in core

**Decision**: The mentor route (purpose 1) is a forum route. Enable core incoming mail processing and the `\mod_forum\message\inbound\reply_handler`.

**Rationale**: Core ships exactly three inbound handlers: the forum reply handler, `private_files_handler`, and the tool's `invalid_recipient_handler`. Assignment feedback and Moodle messages have none. A message notification email's Reply-To is the no-reply address while `allowedemaildomains` is empty (it would be the learner's real address if that were ever set, which spec 016 forbids). The forum handler can only add a reply; it cannot start a discussion.

**Alternatives considered**: A custom inbound handler for messages in local_ltuse (public `\core\message\inbound\handler` API): allowed by Principle XI but new security surface; recorded as later work, not this build (Q19). One-way email for messages (spec 003 today): fails "reply by email".

**Evidence**: MOODLE_502_STABLE `public/lib/db/messageinbound_handlers.php`, `public/mod/forum/db/messageinbound_handlers.php`, `public/admin/tool/messageinbound/db/messageinbound_handlers.php` (only three; `mod/assign` and `message` have none); `public/mod/forum/classes/message/inbound/reply_handler.php` L146-153 (parent always set); `public/message/output/email/message_output_email.php` L86-105; `public/lib/moodlelib.php` `email_to_user` L5815-5864, `can_send_from_real_email_address` L6108-6124. Repo `moodle/site/settings/identity.yaml` (`allowedemaildomains: ""`).

**Instance verification**: V1 (handler list), V3 (message notification Reply-To is no-reply).

## R2. What incoming mail needs, and how it is declared

**Decision**: Declare in a new `moodle/site/settings/inbound-mail.yaml`: `messageinbound_enabled`, `messageinbound_mailbox` (≤15 characters), `messageinbound_domain`, `messageinbound_host`, `messageinbound_hostssl`, `messageinbound_hostuser`, and `messageinbound_hostpass` as `env:MOODLE_INBOUND_PASS` with `secret: true` (or `messageinbound_hostoauth` if an OAuth2 issuer is used). The forum handler's row (enabled, `defaultexpiration`, `validateaddress`) lives in the `messageinbound_handlers` table, not config, so a new local_ltuse site-config kind `inbound_handlers` applies and drift-checks it ([contract](contracts/inbound-mail.md)). Doug decided to plan on the mailbox and every-minute cron, recorded as a spec 015 operator task (Q8); `messageinbound_enabled` stays 0 until 015 provides it and V2 passes.

**Rationale**: `site_config.py` already keeps secrets out of the repo through `env:` + `secret: true` (as for `MOODLE_SMTP_PASS`). Core has no API to set a handler row; its own admin page does `$DB->update_record` after `manager::get_handler()`/`record_from_handler()`. Ours does the same and is listed in the local_ltuse README as a direct write (Principle XI). Addresses are `<mailbox>+<base64>@<domain>`: the mailbox must accept plus-addressing and keep the local part's case and `+ / =` characters. Pickup runs every minute (`pickup_task`) through the bundled `rcube_imap_generic`, so no PHP imap extension is needed; `cleanup_task` daily.

**Alternatives considered**: Enable the handler once in the admin UI: not config as code, invisible to drift. Put all incoming mail in 015: the host part is 015's (mailbox, credentials, cron), the declaration that makes it a forum route is here.

**Evidence**: `public/admin/tool/messageinbound/settings.php` L35-99; `db/tasks.php`; `classes/manager.php` L43 (`tobeconfirmed` folder), L127-141; `public/admin/tool/messageinbound/index.php` L56-79; `public/lib/classes/message/inbound/address_manager.php` `generate()` L236-282; `public/lib/classes/message/inbound/handler.php` L56-66, L164-186; `public/lib/classes/message/inbound/manager.php` L129-143, `is_enabled`. Repo `scripts/site_config.py` L31-34, L220-234, L487-522; `moodle/site/README.md` L83-98.

**Instance verification**: V1, V2 (host mailbox, cron every minute, round trip).

## R3. Reply addresses, expiry and confirmation

**Decision**: Keep `validateaddress` on and the forum handler's expiry at the core default of one week (kept, Q20). Tell mentors to reply from their registered address.

**Rationale**: Each address is per recipient and per post, carrying a hash of the user's `messageinbound_handler` key. A disabled handler, unknown user or bad format is rejected. An expired key, an invalid hash, or a From address that does not match the account (`VALIDATION_ADDRESS_MISMATCH`) is *recoverable*: the mail moves to `tobeconfirmed` and Moodle emails a confirmation to the registered address; on confirmation the post is made. So an old email still works after one extra step. `validateaddress` checks only the From header, which can be spoofed, so a reply address is a bearer credential until it expires; a longer expiry widens that window for little gain. Every successful reply sends a `messageprocessingsuccess` notice by default; the mentor can turn it off.

**Alternatives considered**: Expiry 0 (never): a forwarded email would post as the mentor forever. Four weeks: saves one confirmation on stale mail.

**Evidence**: `address_manager.php` L48-96, L380-417 (expiry L394); `tool/messageinbound/classes/manager.php` L469-530, `passes_key_validation` L937-951, `handle_verification_failure` L1139+, L1090-1137; `tool/messageinbound/db/messages.php`; `reply_handler.php` L92-218 (can_post, attachment limits, `messagetrust = false`).

**Instance verification**: V2 cases (a)–(c).

## R4. Digests break reply by email; the mentor needs per-post mail

**Decision**: Keep the site default `defaultpreference_maildigest: 1` (row #25, FR-016, slow connections). Give each mentor a per-forum digest override of 0 on every forum where they mentor, set by local_ltuse when course-mentor sync enrols them, through the public `forum_set_user_maildigest()` (Q9). The same sync gives each mentee an override of 0 on every forum where they have a mentor (Q9), so they are pushed the mentor's reply. Everyone else keeps the digest. In a teaching space the mentor's and every member's override is 0 on Problems and 1 on Team (Q10; members the same as the mentor, round 2); an Area space sets no override for anyone, mentors included (spec FR-006a). An override is written only where the person has no `forum_digests` row for that forum, so a personal choice is kept, and each write is recorded in `local_ltuse_digest_override`, so removal undoes only what sync set (round 2; the existing-row check is a raw read of `forum_digests`, listed in the README). A record whose `forum_digests` row has gone means the person reset the forum to their default; sync marks it released and never writes it again. Because `forum_set_user_maildigest()` requires `mod/forum:viewdiscussion`, sync checks it first: when core has already removed a leaver's role (`unenrolaction = 3`), the override and record stay, harmless while they are suspended, and nothing is retried. Teaching-space members' overrides follow membership, not whether the space has a mentor.

**Rationale**: A user with digest > 0 for a forum gets its posts only in the daily digest; `send_user_digests` creates no inbound address and sends from the no-reply user. Only per-post notifications (`send_user_notifications`) carry the reply address and an airnotifier push. So on today's default no mentor can reply by email. The same holds for the mentee: digest users receive no per-post popup or push either (cron_task.php L474-485), so the mentee is not told of the mentor's reply until the digest. So mentees get an override too (Q9). A per-forum override reconciles the two without reversing row #25. `defaultpreference_*` applies only to accounts created after it is set.

**Alternatives considered**: Mentor's account-wide maildigest 0: floods them with every other forum they follow. Site default 0: reverses row #25. Leaving it to mentors: the busiest people are least likely to find the setting.

**Evidence**: `public/mod/forum/classes/task/cron_task.php` L253-269, L474-485; `send_user_digests.php` L554-596 (no address manager, no-reply sender); `send_user_notifications.php` L113-115, L318-370, L523-551; `public/mod/forum/db/messages.php` L28-33 (`posts`: popup, email, airnotifier; `digests`: popup, email); `public/mod/forum/lib.php` `forum_set_user_maildigest` L5958 (calls `require_capability('mod/forum:viewdiscussion', …, $user->id)` before writing, L5973-5974, so it must run while the user still holds the role); `public/admin/settings/users.php` L64-95. Repo `moodle/site/settings/notifications.yaml`.

**Instance verification**: V4.

## R5. Private replies are not the mentor route

**Decision**: The mentor route uses open posts (FR-004). Leave private replies as a one-way web tool for course mentors: `teacher` keeps `mod/forum:postprivatereply` (Q14).

**Rationale**: Nobody can reply to a private reply (`can_reply_to_post` false; `forum_add_new_post` throws "It should not be possible to reply to a private reply"). The notification for a private reply still carries a reply address, so an email reply to it fails with an error email. Email replies can never be private (the handler sets no `privatereplyto`). Every holder of `readprivatereplies` in the course (every Course mentor, across organisations in a shared course) reads them. In the app the checkbox appears only on replies, not the opening post. Doug's openness rule matches open posts.

**Evidence**: `public/mod/forum/lib.php` L2887-2912; `classes/local/managers/capability.php` `can_reply_to_post`, L570-577; `db/access.php` L168-186; `send_user_notifications.php` L524-551; moodleapp `components/post/post.html` L82-84, L117, L145-148.

## R6. How quickly each side hears

**Decision**: Keep `maxeditingtime` at 1800 seconds (Q11), declared in `settings/forums.yaml` so drift reports a change.

**Rationale**: Every post is emailed only after `maxeditingtime` plus the next forum cron (every minute); the mentor's emailed reply also waits for the inbound pickup (every minute) and then the same delay before the mentee is notified. `mailnow` is shown only to holders of `moodle/course:manageactivities`, or in Q&A to `canmailnow`; learners and Course mentors in a standard forum do not see it, the app always sends `mailnow = 0`, and the inbound handler never sets it. The only lever is lowering the site-wide editing window (1, 5, 15 minutes are options), which also shortens everyone's chance to fix a post.

**Evidence**: `public/mod/forum/classes/post_form.php` L123-157; `cron_task.php` L113-116, L550; `public/mod/forum/db/tasks.php`; `public/admin/settings/security.php` L69; moodleapp / upstream `externallib.php` L1190-1194.

**Instance verification**: V5 (measured round-trip times).

## R7. The cohort space: one small course per cohort

**Decision**: One space course per cohort, declared through a new `local_ltuse\siteconfig\cohortspaces` class modelled on spec 011's `siteconfig/officehours.php` (create_course, `util::upsert_module`, its own enrolment instance), not on the publisher's `ensure_discussion`. Membership: one `enrol_cohort` instance from the cohort, `customint2 = 0` (no group chat, Q15), course `groupmode 0`, role `spacemember` (Q17), created disabled and enabled by sync once the space has a cohort-mentor row (Q13; gate on the row alone, never disabled again, round 2). Two shapes (Q2): teaching cohorts (spec 002, made by an ALTC as Moodle cohorts, which 005 requires 002 to choose) and the cohorts `ltct:org:<key>` of organisation entries declaring the one opt-in field `space: area` (round 2). Idnumber in a colon form, `ltct:site:cohort:<key>`, so no `ltct:<slug>` rule matches it; the key is the numeric Moodle cohort id for a teaching space and the organisation key for an Area space (round 2). The course's fullname and shortname are the cohort's own name, read live (the Moodle cohort name, or the entry's `name`), never the key: forum email subjects carry the course short name (`postmailsubject`, to confirm in V6), so a key there would show every member a code. Category: a visible category `ltct:cohort-spaces` declared in `organisations.yaml` (Q3); not hidden, because `create_course()` copies the category visibility when `visible` is not passed (course/lib.php L1823-1827) and `core_course_category::hide()` sets every course inside to hidden, so isolation comes from enrolment (V7).

**Rationale**: Forums live only in courses, and core has no cohort-scoped forum. Enrolment is the only core control that also hides a space from search, digests, recent activity and the app. `enrol_cohort::add_instance()` accepts any cohort; with `unenrolaction = 3` (pinned in `settings/groups.yaml`) a leaver is suspended, loses the role and stops getting mail (subscriptions and shared-course messaging both count only active enrolments), and their posts stay. `releasemembers = 0` stops an unmanaged rule silently emptying a space. The colon idnumber avoids at least eight `ltct:<slug>` rules (learner-home continue, course-mentor sync, identity path 4 `levels::course_counts`, `enrolment_rules::decide`, inspector groupmode and discussion checks, `admin_list`, `access::may_enrol_into`), and is out of the publisher's slug namespace. The price is that every tool we reuse must gain an explicit row for the new kind (R9).

**Two constitution points.** (1) The constitution names only `ltct:<slug>` and `ltct:<slug>:<n>` identities; a space course is an extension. (2) A per-cohort course is de facto kept to one Area, and the constitution makes the organisation-only course "the one mechanism" for that (L262). Doug chose an INTENT decision plus a MINOR amendment allowing site-declared cohort space courses (Q3, approved in principle 2026-10-07); both are drafted in this feature's PR.

**Alternatives considered**: (a) One community course with a separate-groups forum per cohort (office-hours precedent, drift-exempt by name): members-only groups and a non-editing teacher sees only their group, but it contradicts FR-019's "groups never wall people" and walls any open forum in that course. (b) A forum per cohort gated by `availability_profile` on `ltct_org`: organisation cohorts only, cannot admit mentors, fails open, stale until re-login. (c) Core Communication API (`communication_matrix` needs a Synapse server; `communication_customlink` is experimental and only stores a chat URL): see R12. (d) Separate groups in shared delivery courses: forbidden. (e) A slug-form idnumber: collides with the publisher and is treated as a published course everywhere.

**Evidence**: Repo `moodle/local_ltuse/classes/siteconfig/officehours.php` (create_course L210, `apply_enrolment` L287); `external/ensure_discussion.php` (publisher-only, `local/ltuse:publish`); `siteconfig/applier.php` header; `siteconfig/inspector.php` L1232-1256, L1290-1328; `admin/enrolment_rules.php` COURSE_PATTERN `'/^ltct:[^:\s]+$/D'`, `decide()`; `admin/course_mentor_sync.php` `is_ltct_course()` L92-100; `learner_home_rules.php` L32; `protection/levels.php` L347; `moodle/site/settings/groups.yaml`; `.specify/memory/constitution.md` L36-37, L246-264. Upstream `public/enrol/cohort/lib.php` `add_instance()` L111-125; `public/enrol/cohort/db/events.php`; `public/lib/enrollib.php` `enrol_get_shared_courses`; `public/mod/forum/classes/subscriptions.php` `fetch_subscribed_users`.

**Instance verification**: V6, V7.

## R8. Cohort space forums and site forum defaults

**Decision**: Two `general` forums per space, "Team and friendship" and "Problems and questions" (Q12): `forcesubscribe` set only at creation: `FORUM_INITIALSUBSCRIBE` in a teaching space, `FORUM_CHOOSESUBSCRIBE` (Optional) in an Area space (Q2); members can leave either. Tracking optional, `maxattachments 3`, `maxbytes` 5 MB, no `lockdiscussionafter` (Q12). Guidelines live in each forum's intro (applied by `upsert_module`, compared by drift); a short pinned pointer discussion is posted once with `forum_add_discussion()` and `forum_discussion_pin()` (lib.php L3062, L6147). Site-wide, declare `defaultpreference_autosubscribe: 1`, `defaultpreference_trackforums: 1` (Q18), `defaultpreference_mailformat: 1`, `enablerssfeeds: 0`, `forum_enablerssfeeds: 0`, `enableportfolios: 0` (Q14), `maxeditingtime: 1800` (Q11), `usetags: 1` (Q19), `loglifetime: 365` (Q21). Keep `forum_subscription` default Optional. Do not touch `ltct:<slug>:discussion` (012).

**Rationale**: Auto subscription subscribes each member as cohort sync assigns their role (`role_assigned` observer) and suits a small group; changing an existing forum to Auto later re-subscribes everyone, so it is set once. Forced or Auto must **not** be used on a course forum (spec 012's `ltct:<slug>:discussion`), or every learner gets every other's mentoring thread. In a cohort space, a mentee's thread with the cohort mentor is cohort-open by design (purpose 3c, Doug's openness rule), so Auto is acceptable there. The reply handler compares the **total** size of all attachments with `maxbytes` (reply_handler.php L196-215) and merges inline images (signature logos) into attachments for plain-text mail (L170-177) before the `maxattachments` check; at 1 MB a mentor replying from a phone with one photo would get a rejection email, hence 5 MB, at some app prefetch cost (IX). Mentor guidance also says large or several images are refused. The app has no subscription control, so the server default carries app-only users. An RSS URL carries a per-user key and is read without a session. Portfolios are off by default; `exportforum` is not gated by them, so `teacher` is prohibited it directly (Q14).

**Evidence**: `public/mod/forum/classes/observer.php` L63-99; `public/mod/forum/lib.php` L270-276; `subscriptions.php` L103-125, L617, L741, L867-890; `mod_form.php` L111-132, L170-175; `public/mod/forum/settings.php` L49-140; `public/admin/settings/subsystems.php` L8-18; `public/mod/forum/classes/local/managers/capability.php` L245-247, L586-590, L714-715; moodleapp `new-discussion.ts` L103.

**Instance verification**: V6, V8.

## R9. Mentors in spaces: reuse spec 008's course-mentor records

**Decision**: A cohort's mentors enter its space as `teacher` ("Course mentor"), through spec 008's existing cohort-mentor rows (`local_ltuse_course_mentor`: courseid = the space, cohortid = the cohort, mentorid), extended so `course_mentor_sync` and `course_mentor_records` accept the `ltct:site:cohort:` kind with precedence 2 (cohort rows) only: no one-course rows and no fallback to each member's default mentor (Q4). Rows are made by the site team through `ltct_admin.py`; ALTC self-service is deferred to spec 017 (round 2). Two code facts must be handled: (a) `course_mentor_rules::target()` counts a learner only if `is_active_student()` holds, which requires the enrol instance's roleid to equal Student (`course_mentor_rules.php` L141-148; `course_mentor_sync.php` L351 passes Student), so for an `ltct:site:cohort:*` course `facts()` passes the space's declared member role id (`community.yaml` `space.role`) as `studentroleid`; a test asserts that a `spacemember` enrolment counts in a space and never in an `ltct:<slug>` course; (b) `apply_course()` creates one group `ltct:mentorgroup:<mentor id>` per mentor and an `enrol_self` instance (`customchar1 ltct:coursementor`); in a space it skips creategroups/add/remove, and drift reports any `ltct:mentorgroup:` group in a space while expecting the enrol_self instance. Q17 chose a declared `spacemember` role, so (a) is needed. The sync sets the per-forum digest overrides for mentor and members by shape (R4), enables the space's cohort instance when a cohort-mentor row exists (Q13), and in delivery courses subscribes the mentor to their mentees' existing discussions (R10). **Gate order** (round 2): `is_active_student()` counts a learner only on an enabled instance (`course_mentor_rules.php` around L150), so while the instance is disabled no member counts and `target()` returns no mentor. The gate therefore rests on the row alone: sync enables the instance first, cohort sync enrols the members, and the next sync enrols the mentor. Sync never disables an instance it enabled: a space whose last row is removed stays open, and drift reports `no moderator` (Q7, round 2). `ltct:mentors` is never enrolled. The change is spec 008's code, made in this feature with 008's tests **[coordinate with 008]**.

**Rationale**: The repo's `mentor` role is user-context only and cannot post in any forum; a mentor reaches a forum only as a course-level role. The records and the ltct_admin preview/apply flow already exist; a second way to name mentors would drift. A course-less row is impossible (`courseid` NOT NULL, foreign key). Precedence 3 (default mentor) would admit every member's own mentor, wider than "open to the cohort". As teacher a mentor gets `pindiscussions`, `movediscussions`, `splitdiscussions`, `canoverridediscussionlock`, `postprivatereply`, and also `moodle/site:viewuseridentity` (sees members' email addresses) and `ignoreavailabilityrestrictions`. Doug accepted this for course mentors on 2026-10-05, and in round 2 (2026-10-07) confirmed that the acceptance covers space mentors, Area spaces included, where one mentor sees hundreds of members' addresses. In a space, `teacher` also gets `moodle/course:manageactivities` through the declared lock override (round 2). `local/ltuse:viewidentity` counts only in `ltct:<slug>` courses, so a space grants no real-identity path.

**Alternatives considered**: A `ltct:cohort:<key>:mentors` sub-cohort: a new cohort kind ltct_admin refuses. Manual enrolment: drifts. A narrower room-mentor role with forum caps only: one more declared role; not needed, since Doug's acceptance covers space mentors (round 2).

**Evidence**: Repo `moodle/site/roles.yaml` (mentor `contextlevels [user]`; teacher); `moodle/local_ltuse/db/install.xml` L177-191; `admin/course_mentor_rules.php` `target()`; `admin/course_mentor_sync.php` L97-130; `admin/course_mentor_records.php` L168; `specs/008-admin-tooling/plan.md` decisions 2, 8. Upstream `public/mod/forum/db/access.php` L168, L225-254, L400; `public/lib/db/access.php` L393-401.

**Instance verification**: V9.

## R10. Mentor subscription without a flood

**Decision** (Q10, changed by Doug on 2026-10-07 in round 2 from "start" to "start or post in"): one mechanism in delivery courses. A local_ltuse observer on `\mod_forum\event\discussion_created` and `\mod_forum\event\post_created`: when the author is a synced mentee of mentor M in that course, subscribe M to that discussion with the public `\mod_forum\subscriptions::subscribe_user_to_discussion()`. When sync adds a mentor, it also subscribes them to every existing discussion in the course's forums that their mentees started or posted in (a raw read of `forum_discussions` and `forum_posts`, listed in the README with the first post's `created` read). **Race fix**: `subscribe_user_to_discussion()` stores `preference = time()` (subscriptions.php L764-771), and cron drops any post created before the user's discussion subscription time (cron_task.php L460-470). `forum_add_discussion()` sets `created` before saving attachments (lib.php L3065, L3082, L3119, L3135) and the event fires afterwards, so the opening question would often be lost. After subscribing, if the stored preference is later than the first post's `created`, local_ltuse sets it to that `created`: a direct write to `forum_discussion_subs`, listed in `moodle/local_ltuse/README.md` (XI). In a teaching space the mentor is not a cohort member: they are subscribed to the Auto forums because sync's `role_assign` triggers mod_forum's `role_assigned` observer and Teacher holds `allowforcesubscribe`; with override 0 on Problems and 1 on Team they get Problems per post and Team in the digest (Q10). The observer does not run in spaces. In an Area space (Optional, no override for anyone) the mentor follows only what they subscribe to, in their own digest setting, and can reply by email there only if they change that setting (round 2).

**Rationale**: The course forum is Optional subscription (012), so a Course mentor gets nothing unless subscribed. Forced or whole-forum subscription in a shared course sends every learner's post, from every organisation, to every Course mentor. Core cannot limit mail to one's own mentees; a per-discussion subscription is the closest public API. A subscription is per-user data, not a change to 012's module settings, so FR-011 holds.

**Alternatives considered**: Whole-forum subscription for each Course mentor (`subscribe_user`): simple, floods. Whole-forum subscribe then `unsubscribe_user_from_discussion()` from every non-mentee discussion: public APIs only, but floods until the observer runs on each new discussion and cannot follow a mentee into another's discussion. Manual subscription by the mentor: fragile.

**Evidence**: `subscriptions.php` L617, L741, L764-771; `cron_task.php` L460-470; `lib.php` L3065-3135; `public/mod/forum/classes/event/discussion_created.php`, `post_created.php`; repo `external/ensure_discussion.php` L96-110 (CHOOSESUBSCRIBE, NOGROUPS).

**Instance verification**: V4, V9.

## R11. Identity: full names, a pseudonym only for the protected, never a username

**Decision**: No space setting. Everyone appears by their recognisable full name, as Moodle normally shows it; only a person protected under spec 016 shows a pseudonym (Doug, 2026-10-07, round 2: "Make the protected person suffer and not everybody else"). Users never see a username, a numeric id or a code; for spaces that means the course is named after the cohort (R7).

**Rationale**: Moodle's normal display is the full name, in posts, participant lists, forum email (FromName "<full name> (via site)") and the app. `showuseridentity` is `email` and never username; everyone logs in by email. Only for a protected person, at pseudonym level local_ltuse writes the pseudonym into firstname and a neutral lastname `·`, so every post, participants list, forum email (From the no-reply address because `allowedemaildomains` is empty) and app view shows the pseudonym for them alone, read live from the user record. The organisation (`ltct_org`) stays visible at every level. A mentor in a space sees a protected member's pseudonym, plus the checked email address through `viewuseridentity`. Permalinks are the report-a-post route; core has no flag feature.

**Email replies are posted as written.** The handler strips quoted text only from the first line beginning with `>` (`public/lib/classes/message/inbound/handler.php` `remove_quoted_text` L247-290). An automatic signature (real name, phone, organisation) is always posted, and Outlook-style replies, which quote with a From:/Sent:/To: block and no `>`, post the whole quoted thread including the mentor's real address. Decision: (a) mentor guidance, written with spec 003's mentor onboarding, says an email reply is posted as written, signature included, mirroring 016's rule on things a person writes (016 spec L139); (b) a protected mentor is told not to use the email route, or to reply without a signature.

**Evidence**: Repo `moodle/site/protection.yaml` L12-68; `moodle/site/settings/identity.yaml`; `specs/016-identity-protection/spec.md` L138, FR-001, FR-006, FR-016; `research.md` R1, L214; `moodle/local_ltuse/classes/protection/levels.php` L347.

**Instance verification**: V10 (016 V3 run in a space).

## R12. WhatsApp beside Moodle

**Decision**: Spaces sit beside WhatsApp; no link and no Moodle group chat (Q15).

**Rationale**: Doug's baseline is that groups already use WhatsApp. Moodle forums are not real-time chat. Core 5.2 has `communication_customlink`, but only behind the experimental `enablecommunicationsubsystem` setting, and it only stores a chat URL on a course; the conversation and members' phone numbers would then be outside Moodle and spec 016, and app support is unverified. The engagement trigger's off-platform signal is redefined to measure change against a recorded baseline, not presence (spec.md).

**Alternative, not chosen (Q15)**: one all-members group with `enablemessaging` per cohort space, filled by `enrol_cohort` `customint2`, gives an in-app group conversation (`public/group/lib.php` L316-330, `MESSAGE_CONVERSATION_TYPE_GROUP`, independent of course groupmode). `groupmode` stays 0 and nobody is walled off: it is one all-members group, not an organisation group in a shared course. Cost: no reply by email, and messages fall outside the forum's moderation and engagement figures. App support and email notification of group messages are to be verified (V13). Not chosen, so `customint2 = 0` stands and V13 is dropped.

**Evidence**: `public/communication/provider/customlink/classes/communication_feature.php`; `public/admin/settings/development.php`; `public/group/lib.php` L316-330.

## R13. Tags

**Decision**: Leave `usetags` on (Q19).

**Rationale**: Tags help find problem threads. The tag feed checks course access and `forum_user_can_see_post`, but tag names are free text and site-wide, and the tag query does not select `privatereplyto`, so a tagged private reply's subject can show on a tag page.

**Evidence**: `public/mod/forum/locallib.php` L602-684; `public/mod/forum/db/tag.php`.

## R14. Who is in a space, and what members see of each other

**Decision**: Members hold a declared `spacemember` role (Q17), archetype student, which keeps spaces out of the progress, programme and pilots reports. Per Q6 it does **not** prohibit `moodle/course:viewparticipants` or `moodle/user:viewdetails`: members see the participant list and may message each other. Spec 016 neutral entries never get a space. `spacemember` must keep `mod/forum:allowforcesubscribe` (allowed in the student archetype): mod_forum's `role_assigned` observer subscribes a member to Auto forums only when they hold it; validate refuses `spacemember` if it sets that capability to prevent or prohibit.

**Rationale**: Doug's "open to everyone in the cohort" covers conversation and, by Q6, the directory and messaging too. Any shared course makes members messageable to each other (`messagingallusers 0`, `MESSAGE_PRIVACY_COURSEMEMBER`); core has no capability to exclude one course from that. For a small teaching cohort that fits; for an Area cohort of hundreds it is a large widening, which Doug accepted (Q6), for Area spaces too (round 2). The report templates in `reports.yaml` filter by role `student` and enrol plugin with no course scope, so a student-role space would appear as a course row (first plan R15).

**Evidence**: `public/message/classes/api.php` L265-283, L1633-1657, L2084-2091, L2440-2494; `public/lib/db/access.php` L519-528, L1016-1026; repo `moodle/site/reports.yaml` L41-138; `moodle/site/roles.yaml`.

**Instance verification**: V7, V11.

## R15. Engagement figures (FR-018)

**Decision**: Kept from the first plan, re-scoped. A read-only external function `local_ltuse_community_engagement(quarter)` over one ended calendar quarter returns active learners (internal log reader; any learner with a logged event in the quarter, Q16, confirmed in round 2; a learner holds a Student or `spacemember` enrolment), distinct posters and post counts in cohort spaces and course forums, counting learners only (round 2): a post counts only if its author holds no `teacher` role in that course, so a mentor who also holds a Student or `spacemember` enrolment elsewhere never adds mentoring posts (the site-wide space is deferred, Q1), and the unanswered-after-7-days rate over cohort Problems forums (a mentor's reply counts as an answer, Q16; the only figure a mentor counts in). It runs on the existing `ltuse_admin` service (Q21). Minimum cell 5, site-wide only, no breakdown by cohort or Area. `scripts/engagement_review.py` prints to the terminal and records the coordinators' baseline answers (obstacle 2) as counts the operator types in; nothing is written to the repo but a decision in `INTENT.md` ([contracts](contracts/engagement-ws.md)).

**Rationale**: Report builder cannot tell a question from a reply and has no suppression; core forum WS return names and bodies. A quarter parameter, not a free range, stops differencing. Log retention must cover a quarter plus the time to review: `loglifetime` 365 days (Q21).

**Evidence**: `public/mod/forum/classes/reportbuilder/datasource/forums.php` L56-125; `public/lib/classes/log/sql_reader.php`; `public/admin/tool/log/store/standard/settings.php` L36-52; `public/mod/forum/db/install.xml`; repo `external/admin_summary.php`; `scripts/admin_files.py` L115.

**Instance verification**: V12.

## R16. The site-wide space (deferred, Q1)

**Decision**: Deferred (Doug, 2026-10-07): not in this build; revisited after the engagement reviews. If taken up, the first plan's design applies, built after the cohort spaces: `ltct:site:community`, a hidden site-wide cohort by `tool_dynamic_cohorts` rule (auth = manual AND username ≠ guest), one `enrol_cohort` instance, topic forums from `competencies.yaml` categories, a `news` forum, the `onward.community` slot in `block_ltuse`. It still carries the first plan's site-wide co-membership gate: every account becomes messageable by every other (first plan Q18).

**Rationale**: Cohort spaces do not need it (FR-009), so dropping it removes code, not capability.

**Evidence**: First plan R1–R6, R9, R16 (git history of this file); `public/message/classes/api.php` L2440-2494.

## R17. What purposes 2 and 3(a) hand to spec 012

**Decision**: No 005 build. Four re-plan inputs to 012 (spec.md Dependencies).

**Rationale**: 012 owns the course forum (built: `ensure_discussion`, NOGROUPS, Optional subscription) and peer review (core workshop, Phase C, unbuilt). The scenario bank is a read-only learner page today (`moodle_payload.py` L518). Core workshop allocation needs a submission (`add_allocation(stdclass $submission)`; manual refuses `MSG_NOSUBMISSION`; scheduled runs after `submissionend`), so peers fixed at enrolment need 012's allocator to store a peer set. If "identified" also means that peers know who they review and who reviewed them, it would reverse the student `mod/workshop:viewauthornames` override in `roles.yaml` and need `viewreviewernames`; that is a question for Doug in 012's re-plan, not an instruction. Input 4: a course forum of type "each person posts one discussion" carries scenario-bank responses for peers to reply to, with no submission and no allocation, open to the course, offline in the app (`addNewDiscussion`), and answerable by the mentor by email; it cannot fix named reviewers, so input 2 still applies if Doug requires assigned peers. Whatever 005 declares site-wide (incoming mail, defaults) reaches 012's forum without touching its module.

**Evidence**: `public/mod/workshop/locallib.php` L1537-1562; `allocation/manual/lib.php` L39, L82-85; `allocation/scheduled/lib.php` L134-188; `public/mod/workshop/db/access.php` L137-157; repo `moodle/site/roles.yaml` L148-156; `specs/012-assignments-peer-review/spec.md` FR-011, FR-012a, FR-015.

## Instance verification V1–V12

All on `$MOODLE_URL` (ltuse.net, 5.2.3+), which nobody uses yet, so they run there now (Doug, 2026-10-07, round 2: "Just do it."), with test accounts and test cohorts only (constitution X); record counts, never names. Each maps to a [quickstart](quickstart.md) scenario. The Area-shape parts of V6 and V9 are covered by PHPUnit only until a real Area mentor is recorded; they then run on that real Area space (Doug, 2026-10-07, round 2; quickstart, "Area shape, later").

| # | What | From |
|---|---|---|
| V1 | Message handlers page lists exactly three handlers; forum `reply_handler` row exists, disabled before apply, enabled after; drift reports a hand change | R1, R2 |
| V2 | Host gives an IMAP mailbox with case-preserving `+` subaddressing; pickup runs every minute; reply round trip (a) registered address, (b) other address → confirmation, (c) email older than 8 days → confirmation, (d) reply from Gmail web, Gmail Android and Outlook: record whether the signature and quoted header are posted, (e) a reply with a 2 MB phone photo, and one whose signature contains an image | R2, R3, R8, R11 |
| V3 | A message notification email to a mentor has Reply-To = no-reply | R1 |
| V4 | Mentor on the site digest default with a per-forum override 0 gets per-post, answerable mail from that forum and digests from the rest; a digest has no Reply-To; mentor subscribed only to mentees' discussions, including a mentee's reply in another's discussion (Q10 as changed in round 2) and discussions existing at assignment; a person with their own digest row on a forum keeps it, and removing the mentor leaves it; a mentee opens a discussion with a 1 MB attachment, 10 times, and the mentor receives the opening post every time; a mentee on digest 1 gets no push for the mentor's reply, with the override 0 sync sets (Q9) they do; a learner with no mentor in the course gets no override | R4, R10 |
| V5 | Measured times: learner post → mentor email; mentor email reply → learner sees it in the app | R6 |
| V6 | Apply creates one space per matching teaching cohort and per `space: area` entry, idempotent, none for an entry without it; each space is named after its cohort, and no page, email subject, breadcrumb or app view shows a member its key, idnumber or a code; renaming the cohort renames the space; the cohort instance stays disabled until a cohort-mentor row exists (Q13); teaching forums Auto, Area forums Optional; Auto subscription covers members added later by cohort sync; inspector, drift, learner-home continue and identity path 4 ignore the colon idnumber; a new space has exactly the two declared forums (no Announcements, no lock setting) and no enabled manual instance; a space appears within one sync of an ALTC making a matching cohort | R7, R8 |
| V7 | Two spaces: no cross-visibility by browse, search, forum search, recent activity, digest, direct URL, app download; a non-member finds no space in course search; leaver suspended, no mail, posts kept; rejoin restores | R7, R14 |
| V8 | App: space in My courses; read offline after download; offline reply and new discussion with a screenshot sync; no subscribe control | R8, app survey |
| V9 | Cohort-mentor row on a space enables the cohort instance, and the next sync enrols the mentor as teacher; they post, pin, lock, move and remove in the space (the declared override), and cannot lock in a delivery course; a hand edit of a forum setting is reported by drift and restored by apply; teaching space: mentor and members override 0 on Problems and 1 on Team; Area space: no overrides; removing the last row removes the mentor and only the overrides sync wrote, with no exception, and the space stays open with drift reporting `no moderator`, members keeping their overrides; deleting a test teaching cohort removes its members' access but keeps the space, its posts and its mentor, and drift flags `cohort deleted` (round 2); a row naming another cohort is refused; a member's default mentor is not added; a `spacemember` enrolment counts in the space; no `ltct:mentorgroup:` group is made in a space; delivery-course groups unchanged | R9 |
| V10 | An unprotected test learner appears by full name in posts, the participants page, email and the app; a protected test learner at pseudonym level shows only the pseudonym and the no-reply address; no display name, post, email or app view shows anyone's username field (the email address course staff see through `viewuseridentity` is the accepted exception, Doug 2026-10-05; round 2) | R11 |
| V11 | With `spacemember`: member reads and posts, sees the participants page and can message another member (Q6); spaces absent from progress, programme, pilots reports (Q17); `teacher` cannot export a forum, a discussion, a post or its own post, and can still reply privately (Q14, round 2) | R14, R5 |
| V12 | `logstore_standard` logging, `loglifetime` 365 (Q21), distinct-user count run time; app-only users counted as active | R15 |
