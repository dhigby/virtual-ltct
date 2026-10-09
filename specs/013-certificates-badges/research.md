# Research: Completion badges and certificates

**Plan**: [plan.md](plan.md). Every API below was looked up on 2026-10-02. Context7 was queried first (`/websites/moodledev_io_5_2_apis`, and `/moodle/moodle` for `UPGRADING.md`), then each claim was confirmed in upstream source:
- core: `MOODLE_502_STABLE`, `$release = '5.2.3+'`. In 5.1 and later core lives under `public/`; paths here leave that prefix off.
- `mod_customcert`: branch `MOODLE_502_STABLE`, head `73ec930`, which is byte-identical to tag `v5.2.9` once line endings are normalised.
- `availability_coursecompleted`: release `v5.5.3`.

"Inferred" marks a behaviour read from source but not run. Each **Verify** line has to be confirmed on the 5.2.3+ instance, with test accounts only, before the task that depends on it is closed (constitution X).

## R1. Badges are core, created by `local_ltuse`, one per course

**Decision**: Every published course gets one **course badge** (`BADGE_TYPE_COURSE`), created by our plugin through `\core_badges\badge::create_badge($data, $courseid)`. Its criteria are:
- an overall criterion with `BADGE_CRITERIA_AGGREGATION_ALL`;
- one course-completion criterion, `award_criteria_course`, with params `['course_<courseid>' => <courseid>]`.

There is no web service that creates a badge, updates one or sets its criteria. Core's only write services are `core_badges_enable_badges` and `core_badges_disable_badges`, so the plugin does the work through the `badge` and `award_criteria` classes.

**Identity**: the `badge` table has no `idnumber` and no other stable-identity column. Matching on name is unsafe, because a restore or a course copy creates a second badge with the same name. So `local_ltuse` keeps its own map, a table `local_ltuse_course_badge` (`courseid` unique, `badgeid`). It holds no user data. A badge in an `ltct:` course that is not in the map is reported as `extra` and left alone: this is what a restored or copied course produces. It is never adopted silently.

**Rationale**: FR-001 and FR-002, built on core first. The course-completion criterion is the only criterion used. A competency criterion would read as a competency awarded (spec Assumptions; Principle V).

**Alternatives considered**:
- A site badge per course with a `courseset` criterion. It would not show on the course and could not be backed up with it.
- Badges made by hand from a checklist. That fails FR-007 and SC-004.
- Identifying the badge by name, which a rename or a restore breaks.

**Confirmed in source**:
- **Creating a badge.** `badge::create_badge(stdClass $data, ?int $courseid)` is at `badges/classes/badge.php:965`. A non-null course id gives `BADGE_TYPE_COURSE` (:972).
  - It reads `name, version, language, description, imagecaption, issuername, issuerurl, issuercontact` and the optional expiry and `tags` fields without `isset` (:973–987, :1018), so the plugin must send every one of them.
  - It sets `messagesubject` and `message` from lang strings, `attachment = 1`, `notification = BADGE_MESSAGE_NEVER` and `status = INACTIVE`, and it sets `usercreated` from `$USER` (:981, :992–997).
- **Updating a badge.** `update(stdClass $data)` (:1030) also needs `expiry` and `tags` (:1040–1053). `update_message()` (:1064) reads `message_editor['text'], messagesubject, notification, attachment`. Both write through `save()` (:231), which writes every property and fires `badge_updated`.
- **The `badge` table** (`lib/db/install.xml:3185–3220`). `issuerurl` is NOT NULL, and so are `message` and `messagesubject`. The UI fills `issuerurl` from wwwroot's scheme and host (`badges/classes/form/badge.php:113–114`), and the plugin does the same from `$CFG->wwwroot`, never from a hard-coded host. The image-author fields were removed in 5.0 (MDL-83909).
- **Criteria constants** (`badges/criteria/award_criteria.php:33–87`): `OVERALL = 0` and `COURSE = 4`. `BADGE_CRITERIA_AGGREGATION_ALL = 1` (`lib/badgeslib.php:45–50`).
- **Building criteria.** `award_criteria::build(['criteriatype' => …, 'badgeid' => …])` is at :171. Core's UI saves the overall criterion first with `['agg' => ALL]`, then the type criterion (`badges/criteria_settings.php:110–117`).
- **Course-criterion params.** The course criterion's param is `course_<id>` (`award_criteria_course.php:39–45`), and the observer looks up exactly that key (`badges/classes/event/observer.php:161–164`).
- **One criterion per type.** `badge_criteria` has a unique index on (`badgeid`, `criteriatype`), so a badge holds at most one course criterion.
- **Page context.** `award_criteria::save()` uses `$PAGE->context` in its events (:426, :470). The web service sets the context with `validate_context()` before saving (inferred risk if it does not).
- **Reloading.** Criteria are loaded in the badge constructor (`badge.php:156`), so the badge is reloaded with `new badge($id)` after its criteria are added.

## R2. The badge image comes from the repo

**Decision**: The badge design is one PNG committed at `moodle/site/badges/completion.png`, and every course badge uses it. `site_config.py apply` sends it in the payload as base64, as other assets travel. The plugin writes it to a temporary file, then calls `badges_process_badge_image($badge, $tmp)`.

**Confirmed in source**:
- `badges_process_badge_image()` (`lib/badgeslib.php:675`) calls `process_new_icon()`, then **deletes the file it was given** (:679–681). So the plugin hands it a temporary copy, never the stored original. It does nothing at all when `$CFG->gdversion` is empty, so the plugin checks for GD and fails loudly without it.
- `process_new_icon()` (`lib/gdlib.php:43`) accepts GIF, JPEG and PNG. It centre-crops to a square and writes sizes f1 (100px), f2 (35px) and f3 (512px) (:64–92, :123–125, :167–169, :214).
- The 256 KB limit is enforced only by the form (`badges/classes/form/badge.php:79`), so `validate` enforces it instead: the PNG must be square, at most 256 KB, and at least 512px.
- Badge images are served without login (`lib/filelib.php:4616–4625`). The design is public, which it is anyway in a public repo.

## R3. Issuing is automatic, from course completion, including offline completions

**Decision**: Nothing to build. While a badge is active, `\core\event\course_completed` is observed by `\core_badges\event\observer::course_criteria_review` (`lib/db/events.php:52–55`, `observer.php:152`). The observer is not marked `internal => false`, so it runs straight after commit, not on cron. `process_criteria_completion()` (:54–66) skips a learner who already holds the badge, or a badge that is not active. Otherwise it reviews the course criterion, then the overall one, and calls `issue()`.

**Offline**: An app completion syncs as a page view or a quiz attempt (spec 004, R5). `completion_regular_task` runs every minute and calls `aggregate_completions()`, which calls `mark_complete()`. That fires `course_completed` (`completion/completion_completion.php:176`), so the badge is issued within a minute or two of the sync. That meets SC-001's one hour, given a working cron (015 monitors it).

**Two conditions that silently stop an award**:
- `award_criteria_course::review()` returns false while the course's `startdate` is in the future (`award_criteria_course.php:185–187`). The publisher never sets a future start date. `check_moodle_payload` asserts this, and the plugin re-checks it and warns.
- `badges_cron_task` runs every 5 minutes (`lib/db/tasks.php:261`) and queues a `review_all_criteria()` for active course badges, but **only in visible courses that have started** (`badges_cron_task.php:57–64`). This is the safety net for a missed event. The event path itself does not check visibility.

**Verify** (blocks US1, SC-001): with a test learner, complete a course in the browser, and the badge is issued within 2 minutes. Repeat on Android with spec 009's V7 device: complete the course offline, reconnect, and the badge is issued after the sync. Record the times.

## R4. Activation is the delivery switch: pilots issue no badge

**Decision**: A badge is created **inactive** at every publish. The plugin activates it only on a **delivery** publish, that is, when `course_stage.py` reports the course at stage 8. The publisher asks `course_stage` and never works out the stage itself (Principle I). Once active, a badge is **never deactivated, archived or deleted** by our code.

A pilot (stage 7) course therefore holds an inactive badge, which issues nothing. The certificate activity (R6) is created only on a delivery publish too.

**The pilot learner at delivery**: Activating a badge does not by itself award anyone: `set_status()` saves and fires an event, nothing more (`badge.php:351`). But a pilot and the published course are one Moodle course (004 R10), so the pilot learner's completion record is still there. Within 5 minutes of activation, `badges_cron_task` reviews every enrolled learner with `moodle/badges:earnbadge` (`review_all_criteria()`, `badge.php:491`) and would award the pilot learner from their pilot-era completion.

So **stage 8 ends the pilot learner's manual enrolment (sets it to suspended) before the delivery publish**, as one added line in `process/stages/08-publish.md`. A pilot learner who later joins through their organisation's cohort then holds an active enrolment, and the cron review awards them from the completion they already have. That is the same one-completion-per-learner limit as spec 004's decision #6, and it is recorded as a pending decision (plan, decision 2).

**Verify** (blocks US1-3 and the pilot edge case):
- `review_all_criteria()` skips a learner whose only enrolment is suspended. Read `badge.php:491` and onward on the instance, and confirm whether it uses `get_enrolled_sql()` with `onlyactive`.
- A pilot completion, then suspending the enrolment, then a delivery publish: no badge is issued.
- That learner is then enrolled by cohort: the badge is issued within 5 minutes.

**Alternatives considered**:
- Activating through `core_badges_enable_badges`. It calls `review_all_criteria()` immediately (`badges/classes/external/enable_badges.php:116–118`, always for course badges at :178), so it gives the same result sooner. It also needs `moodle/badges:configurecriteria` on the token user. The plugin calls `set_status()` and leaves the review to cron.
- A cohort criterion ("is in some organisation's learner cohort"). Pilot learners are partner learners too, so it would not tell a pilot from delivery.
- Activating at stage 7 as well. That fails "pilot runs issue no badge".

## R5. Rewording a live badge is safe: update in place, never deactivate

**Decision**: When the declared wording changes, the plugin rewrites the badge's name, description, image caption and message through `badge->save()` and `update_message()`, with the badge **left active**. Every mapped badge is rewritten by `site_config.py apply` (US4-2), and a course's own badge by each publish.

**Confirmed in source**:
- The details form is frozen while a badge is active or locked (`badges/classes/form/badge.php:159–161`), but that is a UI rule only. `save()`, `update()` and `update_message()` have no status check (`badge.php:231, :1030, :1064`).
- Status constants (`lib/badgeslib.php:56–80`): `INACTIVE = 0`, `ACTIVE = 1`, `INACTIVE_LOCKED = 2`, `ACTIVE_LOCKED = 3`, `ARCHIVED = 4`. The comment at `install.xml:3205` swaps 2 and 3; use the constants. A badge becomes `ACTIVE_LOCKED` on its first `issue()` (`badge.php:463–465`).
- `badge_issued` rows are untouched by a rewording.
- The hosted assertion, the BadgeClass JSON and `badges/badge.php` all render the **current** badge row (`classes/output/issued_badge.php:152–153`).
- A **baked PNG** that a learner already downloaded keeps the wording from the moment it was baked. `badges_bake()` checks `file_exists` first (`lib/badgeslib.php:721`), and the image embeds the nested assertion (`local/backpack/ob/exporter_base.php:41`, `v2p0/assertion_exporter.php:67–73`). This is recorded as an accepted limit (plan, decision 6).
- **Never deactivate a badge.** A badge that is `INACTIVE_LOCKED` or `ARCHIVED` makes the BadgeClass JSON return 410 to existing earners (`json/badge.php:37–40`, `local/backpack/helper.php:74–83`). That would break external verification of badges already issued.

**Award message**: the plugin replaces core's default subject and body with the declared ones through `update_message()`. Core always sends the learner a message through the `moodle/badgerecipientnotice` provider, with `%badgename%`, `%username%` and `%badgelink%` substituted (`lib/badgeslib.php:171–209`). That gives US1-1's notification. The PNG is attached only when `allowattachments` and the badge's `attachment` are both set (:199–207). The plugin sets `attachment = 0`, which keeps the email light. The badge's `notification` field only controls notices to the badge's creator, so it stays `BADGE_MESSAGE_NEVER`.

## R6. The certificate is `mod_customcert` 5.2.9

**Decision**: Pin **`mod_customcert` 5.2.9**: version `2026042014`, sha256 `434b084faa646aa2478bf263a96366a77441237a930206df01ae392710d7f8b3`, from `https://marketplace.moodle.com/api/plugins/mod_customcert/versions/2026042014/download`. That is a zip of 3,526,373 bytes whose md5 matches the plugins directory's. It is tag `v5.2.9`, commit `4d97182a`. The plugin declares `requires = 2026042000` and is listed in the directory for 5.2. It is free (GPL-3.0) and maintained by Mark Nelson, with nine 5.2 releases between 2026-05-24 and 2026-10-01, several of them security fixes.

**Rationale**, measured against the spec:
- **The date and the name are current.** The `date` element's `DATE_COMPLETION` (`-2`) reads `MAX(course_completions.timecompleted)` (`element/date/classes/element.php:71, :198–207`), and is distinct from the issue date. The PDF is regenerated on every download from `core_user::get_user()` (`classes/service/pdf_generation_service.php:90`). `studentname` prints `fullname($user)` (`element/studentname/classes/element.php:74`). So the name on the certificate is the account's name at download time (edge case). For a protected learner that is the account's protected display name; a certificate in the real name is issued by hand by the site team on request (spec 016 R10, Doug, 2026-10-05 (scope review); 016 plan: 013 unchanged).
- **The verification reference** is the `code` element, and an optional `qrcode` element links to the verification page (`element/qrcode/classes/element.php:180`).
- **It works in the app**: `db/mobile.php` declares a `CoreCourseModuleDelegate` handler.
- **The privacy provider** is a userlist provider (`classes/privacy/provider.php:47`), and backup includes issues when user data is included.

**Alternatives considered**: `tool_certificate` with `mod_coursecertificate` 5.0.10, Moodle HQ's Workplace pair, free on the directory. It shares one template by reference, which suits FR-012 better. It was rejected on three counts:
- the learner's name and the completion date are frozen as strings when the certificate is issued (`tool_certificate classes/template.php:735`; `mod_coursecertificate classes/helper.php:177–195`);
- its verification page gives an anonymous visitor the **whole PDF** by default, through `tool/certificate:verify`, granted to guest and user (`db/access.php:67–74`; the verify result template :47), and it does not show the course;
- it has no 5.2 branch: one 5.0 branch claims `supported = [500, 502]`.

## R7. One shared certificate design, applied into each course

**Decision**: The design is one `mod_customcert` **site template**, the only kind that lives in system context. It is declared in the repo as `moodle/site/certificate/template.yaml`: its pages, and its elements with their positions, fonts and text. Images sit beside it in `moodle/site/certificate/`.

`apply` builds or updates that site template, which is found by its exact name, through `mod_customcert`'s template, page and element classes. Every certificate activity is then brought into line with it through `\mod_customcert\service\template_load_service::replace($targetid, $sourceid)`. That runs once on each delivery publish, for the course's own activity, and on `apply`, for every mapped activity when the design has changed.

`replace()` deletes the target's pages and copies the source's, in one transaction (`classes/service/template_load_service.php:96–139`). It needs `mod/customcert:manage` on the target. A system-context source needs nothing (`classes/template.php:293–313`).

**Identity**:
- `customcert_templates` has only `name` and `contextid`, and no idnumber (`db/install.xml:35–41`). Its export and import always **insert** a new template (`classes/export/template.php:82`). So the site template is found by exact name, and two with that name is `ambiguous`, which stops the run.
- A course's activity is found by its course-module idnumber, `ltct:<slug>:certificate`. Our plugin owns that idnumber and the publisher writes it.

**Principle XI**: `template_load_service` and the element classes are `mod_customcert`'s own PHP classes, not a published API. They were refactored heavily during 5.2.x. Every call into them is listed in the `local_ltuse` README, and each `mod_customcert` re-pin re-runs V6 (quickstart). The plugin writes to `mod_customcert`'s tables only through those classes, never with raw SQL.

**Alternatives considered**:
- Committing the plugin's export zip. It is opaque to review, and its import always duplicates.
- Building each activity's template from scratch on every publish. That is more of our code, against the same unstable classes.

**Verify** (blocks US2): `replace()` called from `local_ltuse` under the publisher's web-service user. It needs the right capabilities, and the images must be copied into the module context (inferred from `copy_page`). A second apply with no change touches nothing.

## R8. The certificate unlocks on course completion: `availability_coursecompleted`

**Decision**: The certificate activity's availability is the single condition `{"type": "coursecompleted", "id": "1"}`, from **`availability_coursecompleted` v5.5.3**: version `2026070100`, `requires = 2025100600`, `supported = [501, 502]`, GPL-3.0, md5 `7176ce71f33e577ae80b634b63680d8d`. Its sha256 is computed when it is pinned (T-task). It checks `completion_completion::fetch(...)->timecompleted` (`classes/condition.php:97–98`). Before completion, the learner sees core's restriction text, the plugin's "Not available unless: You completed this course." That is US2-2.

**Why not core alone**: core has no course-completion availability condition. On 502, `availability/condition/` holds completion, date, grade, group, grouping and profile, and `availability_completion` works on activities, through a `cmid` (`condition.php:47, :50`). The core-only way is an AND of one activity condition per lesson, plus the quiz passed. That fails the spec's first edge case: when a republish adds a lesson, a learner who has already completed the course keeps their completion (004 R3), but the AND now needs the new lesson, so their certificate locks again. A condition on the course completion record is the only kind that keeps "their badge stands" true of the certificate as well.

**The cost**: a second third-party plugin, where the spec's Constitution Check says "one plugin at most". It was accepted on 2026-10-02, the spec's Constitution Check was amended to allow two pinned plugins, and the exception is recorded in Complexity Tracking (plan, decision 1).

**Gotchas**:
- The certificate activity is **never a course completion criterion**. If it were, the course could never complete. Its completion tracking is off, and `set_course_completion` leaves out the idnumber `ltct:<slug>:certificate` from its wanted set.
- Course completion is aggregated every minute, so the certificate unlocks at the next cron, not instantly. That is within SC-003's one minute on a healthy cron. The Verify step times it.

**Verify**: lock and unlock in the browser and in the Android app; the restriction text both show; a learner who completed the course before a lesson was added keeps access after the republish.

## R9. Verification by reference

**Badges**: `badges/badge.php?hash=` needs no login and runs in system context (`badges/badge.php:31–52`). It shows the recipient's full name (`issued_badge.php:163`), the badge's current name and description, the course's full name, the issuer and the criteria. Email is never rendered.
- The JSON assertion (`badges/json/assertion.php`, `NO_MOODLE_COOKIES`) identifies the recipient only as `sha256$` of email plus `badges_badgesalt` (`v2p0/recipient_exporter.php:48–53`).
  - *Known gap (2026-10-05, spec 016 research R10 and "Known gaps"):* the salt is published, so anyone who already knows an address can confirm it against the hash. No core setting disables `json/assertion.php` short of turning badges off. Protected people use an email address that does not name them (016 R8): secret identity is permitted through a pseudonym and an email address that does not indicate the person's real name (Doug, 2026-10-05). Only a relay address would close the gap, and none is built.
- Changing `badges_badgesalt` would break every assertion already issued (inferred), so `settings/badges.yaml` leaves it out and `ignore.yaml` lists it as per-site.

**Certificates**: `mod/customcert/verify_certificate.php` has no `require_login` by design (file comment, :25). Anonymous verification by code needs two switches: the site setting `customcert/verifyallcertificates = 1` (`settings.php:34–37, :171`), and `verifyany = 1` on the activity (`install.xml:16`; `verify_certificate.php:82–84, :115–116`). The publisher sets `verifyany`. The result (`templates/verify_certificate_result.mustache:47–53`) shows:
- the full name, linked to the profile;
- the course's full name, linked to the course;
- the activity's name;
- "Awarded on", which is the **issue date**, meaning the learner's first download, not the completion date.

The links lead an anonymous visitor to a login page and show nothing more. So FR-009, "only the learner name, course, and issue date", holds with two small differences, recorded in plan decision 5: the activity name is shown too, and the date is the issue date. Overriding the template in a child theme would remove them, at the cost of a theme this project does not otherwise need.

**Verify** (blocks US2-3): an anonymous browser, with `forcelogin` both on and off, shows exactly the fields above for a test certificate code and a test badge hash. A hidden course still verifies both.

## R10. Who sees a learner's badges

**Decision**: Badges stay visible on the learner's own profile and to their assigned mentor. They are not visible to other learners.

- `roles.yaml` sets `moodle/badges:viewotherbadges` to `inherit` for the `user` role (authenticated user). Its archetype default is allow (`lib/db/access.php:2080–2087`, `CONTEXT_USER`, `RISK_PERSONAL`).
- The capability is granted to spec 003's mentor role, which is assigned in the learner's **user** context. So a mentor sees exactly their assigned learners' badges (US3, FR-011), with no code of ours. *(2026-10-05: spec 003 built the role without it, so mentors saw no badges. `moodle/site/roles.yaml` now grants it on `mentor`, and `MENTOR_ALLOW` in `scripts/site_config.py` is widened to allow it, a reviewed change (003 research R2).)*
- `manager` keeps it through its archetype, for the site team.

**Why a capability, not the privacy flag**:
- `badge_issued.visible` is copied at issue from the user preference `badgeprivacysetting`, which defaults to 1, public (`badge.php:448`; `badges/classes/form/preferences.php:44–46`). There is no site setting for it.
- The profile always passes `onlypublic = true` (`badges/lib.php:51–52`; `lib/badgeslib.php:377–378`), and so does `core_badges_get_user_badges` for anyone else (`badges/classes/external.php:117–120`). So a learner who marks a badge private hides it from their mentor too.

Keeping the default public, and narrowing who may look, gives "private to the learner by default" without hiding the evidence from the mentor. A learner who wants a badge seen by nobody can still mark it private.

**Organisation managers** get no badge view. They follow completion through spec 004's reports. `viewotherbadges` is a user-context capability, which a course-context role such as `orgmanager` cannot reach. FR-011 is a limit, not a feature, so this meets it.

**The hash page is not covered by this.** Anyone holding a badge's hash URL sees it, whatever `visible` says (R9). The learner decides who gets the URL.

**Verify** (blocks US3): log in as a second test learner, who is refused the first learner's badges on the profile and through `core_badges_get_user_badges`. The site team sees them. Once spec 003's role exists, the assigned mentor sees them and an unassigned one does not.

## R11. Badges and certificates in the Moodle app

**Decision**: Nothing to build.
- The app's `src/addons/badges/` calls `core_badges_get_user_badges`, `core_badges_get_user_badge_by_hash` and `core_badges_get_badge` (moodleapp `main`, `src/addons/badges/services/badges.ts:77, :141, :197`). The addon is gated on `enablebadges`.
- All three functions are already in `MOODLE_OFFICIAL_MOBILE_SERVICE` (`lib/db/services.php:134–153`).
- `mod_customcert`'s `db/mobile.php` gives the activity page and the PDF download in the app.

**PDF size and script coverage**: `mod_customcert`'s default font is `times`, a TCPDF core font that is not embedded and covers Latin-1 only (`classes/element_helper.php:170`; Latin-1 coverage inferred from TCPDF). A learner name in another script would print wrongly. The template therefore uses `freesans`, which TCPDF embeds as a subset. The logo is a small PNG. `validate` caps the template's images at 100 KB in total, and SC-005 is checked on the instance.

**Verify** (blocks SC-005, FR-010): on the 009 device, throttled to 256 kbit/s, the PDF downloads in under 30 seconds, and its size is recorded. A test account with a non-Latin name renders correctly.

## R12. Site settings

`settings/badges.yaml` (#23) declares:
- `enablebadges = 1` (`admin/settings/subsystems.php:42`);
- `badges_allowcoursebadges = 1`. With it at 0, course badges disappear from lists and cannot be enabled (`lib/badgeslib.php:381`);
- `badges_defaultissuername`, the issuing programme's name, given by the maintainer (plan, decision 4);
- `badges_defaultissuercontact`, from `env:MOODLE_BADGE_CONTACT`, so no address is committed to the public repo;
- `badges_allowexternalbackpack = 1`, for FR-013;
- `customcert/verifyallcertificates = 1`.

Those two issuer entries move from `ignore.yaml` into the declaration, and `ignore.yaml` gains `badges_badgesalt` (R9). The plugin also writes the issuer name onto each badge, because `create_badge()` reads `issuername` from its data and does not fall back to the setting (R1).

Source: `admin/settings/badges.php`, with defaults:
- `badges_defaultissuername`: the site's full name (:45–48);
- `badges_defaultissuercontact`: `supportemail` (:50–53);
- `badges_badgesalt`: `'badges' . $SITE->timecreated` (:55–58);
- `badges_allowcoursebadges`: 1 (:60–62);
- `badges_allowexternalbackpack`: 1 (:64–66).

## R13. Export and portability

**Decision**: Nothing to build (FR-013).
- A learner downloads one baked PNG from `badge.php?hash=&bake=1` or `mybadges.php?download=` (`badge.php:43–49`, `mybadges.php:71–76`).
- `badges_download()` gives a zip of all of them (`lib/badgeslib.php:831`).
- Backpacks support Open Badges 2.0 and 2.1 (`lib/badgeslib.php:1218–1222`).
- Core's privacy provider exports issued badges with their files (`badges/classes/privacy/provider.php:266, :573–574`), and `mod_customcert`'s provider exports issued certificates.

## R14. Retirement, deletion, backup and reset

- **A retired course is hidden, never deleted.** Hiding changes nothing for verification: none of the badge paths and none of `verify_certificate.php`'s SQL check visibility (`verify_certificate.php:101–111`; inferred for the badge paths).
- **Deleting a course archives its badges.** `badges_handle_course_deletion()` (`lib/moodlelib.php:4798`, `lib/badgeslib.php:878–900`) makes them site badges with status ARCHIVED. Issued rows survive, but the BadgeClass JSON returns 410.
- **Deleting a certificate activity deletes its issued codes** (`lib.php:91–118`, `delete_by_certificate`).
- So the publisher **never deletes** the certificate activity. `hide_modules` would only hide it, and the publisher never even offers it for hiding: the certificate's idnumber is in the payload of every delivery publish, so it is never stale. Stage 8's how-to and the site README say "retire by hiding".
- **Backup** includes course badges but not `badge_issued` (`backup/moodle2/backup_stepslib.php:981–1076, :1047–1051`). **Restore** always creates badges INACTIVE (`restore_stepslib.php:2893`). Neither backup nor restore is how this project rebuilds a server: 015 restores the database, which carries every award (SC-004 and the 015 drill).
- **Course reset** never touches badges (`lib/moodlelib.php:5102`). A reset of completions leaves the certificate's date element blank for that learner (`element/date/classes/element.php:263`). Reset is never part of our process.

## R15. One wording rule, enforced before apply and before publish

**Decision**: The wording rules move into one module, **`scripts/cbc_wording.py`**. It holds 004's FR-010 report rules, moved out of `site_config.py` with their behaviour unchanged, and 013's FR-004 and FR-005 rules. Both `site_config.py validate` and `check_moodle_payload.py` import it. Like `disclosure.py`, it is the only definition, and a second copy is a defect.

**013's rule for badge and certificate text**:
- **Refuses** `certif(y|ied|ies|ication|ications)`, `certificate of competenc`, `accredit`, any retired level name as a word, and any phrase that puts the learner at a level (`reached`, `achieved`, `attained`, `awarded` or `holds`, near `level` or a CBC level label).
- **Requires** "training completed" or "completed the course" in the badge name and the certificate's main text.
- **Allows** "certificate". FR-003 names the document a "certificate of training completed", and 004's blunter `certif` rule would refuse it. The two rules are kept as two named functions, never merged by accident.
- A level may appear only as a CBC label matching `outcome-levels.yaml` exactly, and only after "designed to support progress towards" (FR-005).

**What it checks**:
- `badges.yaml` and `certificate/template.yaml`, by `validate`. CI runs that on every change under `moodle/site/`, so a failing text never reaches `apply` (FR-006).
- **Each course's rendered badge name and description**, by `check_moodle_payload.py` before a delivery publish. A course title is free text in frontmatter, so "Certification prep" in a title would otherwise reach a badge.
- **Inside the plugin**, the per-course text is re-checked against a deny list. `apply` sends the plugin the same patterns, so it never needs its own copy, and a failure refuses to write that badge. That is the backstop if the PHP side ever renders something Python did not see.

## R16. Where the per-course text comes from

**Decision**: `badges.yaml` holds templates with four placeholders: `{course}`, `{competencies}`, `{target_level}` and `{programme}`. The plugin fills them from the course's full name and spec 004's two locked course fields, `ltct_competencies` and `ltct_target_level`, which the publisher already writes. So the badge's description says which competencies the course addresses as a description of the course (FR-005, US3-1), and no badge text is ever composed from learner data. In the field the competencies are bracketed; the plugin turns `[A] [B]` into `A, B`.

**Rationale**: the frontmatter stays the source of truth through a path that already exists (004 R11). `apply` can reword every badge without a republish, because the data it needs is on the server.
