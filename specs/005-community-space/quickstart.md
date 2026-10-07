# Quickstart: verifying spec 005

Run these on `$MOODLE_URL`. Use test accounts and test cohorts only. Record counts and outcomes, never names. Each scenario maps to a verification item in [research.md](research.md#instance-verification-v1v12). Decisions applied: Doug, 2026-10-07, both rounds (plan.md, "Decisions" and "Round 2").

## Where to run

On ltuse.net, now. Nobody uses the current system yet, so instance checks run there before the amendment merges (Doug, 2026-10-07, round 2: "Just do it."). Constitution X still applies: test accounts and test cohorts only.

- Test teaching cohorts are made through 002's scripted action, with names starting `test-` (Moodle data, not `organisations.yaml`). The space key is the numeric cohort id, so a test space is recognised through its cohort's name, not its key.
- The Area shape is tested by PHPUnit only for now (`siteconfig_cohortspaces_test.php`, `course_mentor_sync_test.php`; contract site-config-community, "Tests"), until a real Area mentor is recorded (Doug, 2026-10-07, round 2). No real Area entry is opted in for testing, no test account is put in a real Area cohort, and no Area space is opened or deleted by these checks. Never add a test organisation to `organisations.yaml`. The Area-shape instance check runs later, on a real space (see "Area shape, later").
- Apply never deletes. After the checks, follow the written removal procedure and record its result as counts: for each Moodle cohort whose name starts `test-`, `delete_course()` its space `ltct:site:cohort:<cohort id>`, then remove the cohort if it still exists; then, after one run of `\local_ltuse\task\course_mentor_reconcile`, confirm that `remove_orphan_records()` has dropped the test cohort-mentor rows (count only). There is no `ltct_admin.py` step: it names a row's cohort by idnumber, and a deleted cohort has none. Drift then reports no test space and no `no moderator` or `cohort deleted` space.
- SC-004 (rebuild) alone needs a freshly rebuilt server (015).

## Offline checks first

```bash
python scripts/site_config.py validate         # community.yaml and inbound-mail.yaml rules
python -m unittest tests.test_site_config tests.test_publish_moodle tests.test_engagement_review tests.test_ltct_admin
python scripts/site_config.py drift            # read-only
```

## Test accounts

You need these accounts:
- a mentor, M;
- two mentees, A1 and A2, in test teaching cohort A;
- B1, in test teaching cohort B;
- a second mentor, M2, recorded for cohort B only;
- one test learner at pseudonym protection level (spec 016).

Put A1 and A2 in one test course with M as their course mentor.

## Read tracking for existing accounts

1. Before applying `defaultpreference_trackforums: 1`, note how many accounts have read tracking off.
2. Apply, then run `cli/trackforums_existing.php` once on the server. It prints counts only. Run it again: it changes nothing (round 2).

## US1: the mentor route (SC-001; V1–V5)

Do these steps in order:

1. Confirm the Message handlers page lists three handlers, and that the forum handler is disabled. Then run apply and confirm it shows as enabled (V1). Change it by hand and confirm drift reports the change.
2. Confirm the host mailbox that spec 015 provisioned (Q8) accepts `<mailbox>+<code>@<domain>` and keeps the address's case. Confirm pickup runs every minute (V2). Only then set `messageinbound_enabled: 1`.
3. Leave M, A1 and A2 on the site digest default. After sync, confirm M, A1 and A2 each have the per-forum override 0 on the course forum, and that a learner with no mentor in the course has none (V4, Q9).
3a. Give a further test mentee, A3, their own digest setting on the course forum before M becomes their mentor. After sync, A3's setting is unchanged. Remove M as A3's mentor: A3's setting is still unchanged (V4, round 2).
4. A1 posts in the course forum from the app. After the editing delay, M gets one email with a reply footer. Record the time (V5).
5. M replies from their registered address. The reply appears in A1's app after sync, under M's full name. Record the time. A1 gets a push or popup for it without a digest (Q9). As a control, remove A1's override by hand: no push or popup arrives (R4).
6. M replies from another address. Confirm M receives a confirmation email, and that the post appears once M confirms (V2b).
7. Reply to an email that is 8 or more days old. The expected result is the same as step 6: the one-week expiry (Q20) asks for confirmation (V2c).
8. Reply to a digest. It has no Reply-To, so the reply cannot land (V4).
9. Send M a Moodle message, including through A1's landing-page mentor link (kept, Q22). Confirm its email has Reply-To = no-reply (V3).
10. A2 starts a discussion with a 1 MB attachment (repeat 10 times). Confirm M is subscribed to that discussion only, not to the whole forum, and receives the opening post every time. A2 then replies in a classmate's discussion: M is subscribed to it too (V4; Q10 as changed by Doug on 2026-10-07).
10a. Reply by email from Gmail web, Gmail Android and Outlook; record whether the signature and quoted header are posted. Reply with a 2 MB phone photo, and with an image in the signature (V2 d, e).
11. Run SC-001: three rounds between M (email only) and A1 (app only, one round written offline).

## US2: cohort spaces (SC-002; V6–V8)

These scenarios need spec 002's teaching cohort, as a Moodle cohort (for A and B). They run before the constitution amendment merges, with test cohorts only (round 2).

1. Create test cohorts A and B through 002's scripted action (names starting `test-`, see Where to run). Run apply twice; spaces appear for A and B, and the second apply reports `waiting: no_mentor` for each. Each space has exactly the two declared forums, no lock setting, no enabled manual instance, the space-mentor override, and a disabled cohort instance; A1 cannot reach space A yet (V6, Q13). Confirm `independent`, which has no `space: area`, has no space.
1a. Confirm each space is named after its cohort: the course list, landing page, breadcrumbs, a forum email subject and the app show the cohort's own name, and nothing a member sees shows `ltct:site:cohort:`, a numeric id or a code. Rename cohort A; after apply, space A has the new name. Confirm the apply and drift output show space A only by its numeric key, never its cohort's name (V6, round 2).
1b. Record M for A and M2 for B. After the first sync each cohort instance is enabled and members are enrolled; after the next sync each mentor is enrolled (V9, round 2).
2. A1 reaches space A in one step on web and in the app, and can post in both forums. A1 opens the participant list, sees A2 by full name and sends A2 a message (Q6).
3. From A1's account, try to reach space B by browsing, by search, by forum search, through recent activity, through a digest, by direct URL, and by app download. Every attempt should fail (V7).
4. Add a new person to cohort A. They are enrolled and subscribed to both forums at once (Auto). (The Area shape's Optional subscription is checked by PHPUnit; see Where to run.)
5. Remove A2 from cohort A. A2 is suspended, gets no more mail, and their posts stay; the next sync reports no exception and leaves A2's overrides as they were. Re-add A2: access and mail return, with Problems still per post (V7). Remove every member's courses: the space stays open for posting (Q7).
6. In the app, download space A and go offline. Read a discussion, then write a reply with a screenshot and reconnect. The reply and the screenshot sync. Confirm the app has no subscribe control (V8).
7. Confirm that inspector, drift, learner-home continue and identity path 4 ignore `ltct:site:cohort:*` (V6).

## US3: cohort mentors (V9)

1. After step 1b above, M is a teacher in space A and can post, pin, lock, move and remove. No `ltct:mentorgroup:` group exists in space A.
1a. M moderates (Q13, round 2): pins a guideline, locks a discussion by hand, moves a discussion between the two forums, removes a post. A1 reports a post by sending M its permalink. Confirm no discussion locks automatically (Q12), and that M has no lock control in the delivery course.
1b. As M, change a forum setting in space A (for example its attachment limit). Drift reports it; apply restores the declared value.
1c. Confirm M and A1 and A2 each have override 0 on Problems and 1 on Team in space A (Q9, Q10, round 2). (That an Area space sets no override is checked by PHPUnit; see Where to run.)
2. Confirm M2 is not in space A, and that a member's default mentor is not added.
3. Remove M's record, the last for space A. After sync, M is removed from space A, the overrides sync wrote for M are gone, and the sync reports no exception. A1 and A2 keep their override 0 on Problems and 1 on Team. The cohort instance stays enabled: A1 and A2 still reach and post in the space, and drift reports space A as `no moderator` (round 2). Record M again to clear it.
4. Once every other check on space B is done, delete test cohort B in Moodle (Doug, 2026-10-07, round 2). B1 no longer reaches space B. Space B and its posts are kept; M2 still reaches the space, reads and posts in both forums, and keeps their `teacher` role and overrides after the next two syncs and the reconcile task. Apply and drift flag space B as `cohort deleted`, by its numeric key only. Then remove space B by the written procedure (Where to run).

## Mail by shape (SC-006)

1. A1 posts in Problems in space A: after the editing delay, M and A2 each get one answerable email. A1 posts in Team: M and A2 get it only in the next digest.
2. M replies by email to the Problems post: A1 and A2 get a per-post email or push for it.
3. Area spaces: covered by PHPUnit for now (no override for anyone, Optional subscription); the instance check is "Area shape, later".

## Area shape, later

Run this once a real organisation entry has opted in with `space: area`, 002 has applied it, and the site team has recorded its first real mentor (round 2). It uses the real space as it is; it adds no test account to the real cohort, deletes nothing, and records counts only.

1. The space carries the entry's `name`, has exactly the two declared forums, both Optional subscription, and its cohort instance is enabled.
2. Neither the mentor nor any member has a per-forum digest override in the space.
3. A member added to the Area cohort is enrolled and subscribed to neither forum.

## US4: course forum (012)

1. With incoming mail on, a learner replies to a per-post email from `ltct:<slug>:discussion`. The reply lands there.
2. Confirm that nothing in the course module's settings changed.
3. As M (Course mentor), confirm that exporting the forum, a discussion, a post and their own post is refused, and that "Reply privately" is still offered (Q14, round 2).

## Identity (V10)

1. An unprotected test learner posts in space A. Posts, the participants page, the email's From name and the app show their full name.
2. The protected learner posts in space A. Check the raw email headers and body: they show only the pseudonym and the no-reply address.
3. Check the participants page and the app. They show the protected learner's pseudonym. No display name, post or email shows anyone's username field; the email address that course staff see through `moodle/site:viewuseridentity` is the accepted exception (Doug, 2026-10-05; round 2).

## Member role and reports (V11)

1. As a member, open the participants page. It lists the space's members by name and offers to message them (Q6).
2. Check the progress, programme and pilots reports. No space appears in them (Q17).

## US5: engagement review (SC-005; V12)

1. Run `python scripts/engagement_review.py --quarter <last ended> --asked 3 --moved-towards 1 --returned 0 --quarters-without-trigger 0`.
2. Check the output. The figures are suppressed and a verdict is printed. Posts made only by test mentors do not count as posters (round 2).
3. Check that no file was written; `--out` is refused as an unknown argument.
4. Record the run time.
5. Confirm `loglifetime` is 365 (Q21) and that the function runs on the `ltuse_admin` service with the operator's own token.

## Rebuild (SC-004)

On a freshly rebuilt test server (015), run apply. Spaces, forums, the space-mentor override, the handler row, the roles (`spacemember`, `teacher` export caps) and the site-wide settings (read tracking default, portfolios off, tags on, `maxeditingtime` 1800, `loglifetime` 365) should all match with no admin-UI step. Mentor rows are Moodle data, so re-create them through `ltct_admin.py`; until then, each space waits for a mentor.

## SC-003: real users

2–3 real partner learners and one real mentor use a cohort space and the mentor route without help. Record their findings without identifying them.

## Results

Pass/fail only; no names, addresses or live counts (Principle III).

| Check | Date | Result |
|---|---|---|
| T022 deploy (local_ltuse 2026101000, upgrade, caches purged) | 2026-10-07 | Pass. Server plugin matched `main` before the copy; backup kept on the server. |
| T022 apply | 2026-10-07 | Pass. `teacher` loses forum export, `spacemember` created, `logstore_standard/loglifetime` 365, `defaultpreference_trackforums` 1, forum reply handler row enabled (site-wide `messageinbound_enabled` stays 0). Drift after apply shows none of these. |
| T022 drift side effect | 2026-10-07 | Creating `spacemember` (archetype student) surfaced three unmanaged settings: `gradebookroles`, `profileroles`, `enrol_flatfile/map_<spacemember id>`. To be declared or ignored (follow-up). |
| Read tracking for existing accounts | 2026-10-07 | Pass. First run switched it on for every account that had it off; second run changed none. |
| T004 core probes (V3, V4 core part) | | Pending: run by the maintainer with test accounts. |
