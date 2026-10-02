# Quickstart: verifying identity protection

**Plan**: [plan.md](plan.md) · **Research**: [research.md](research.md)

## Prerequisites

- `MOODLE_URL` and `MOODLE_TOKEN` for the 5.2.3+ test instance, from the environment only.
- **Test accounts only** (constitution III). Name them `ltct-test-*`. Every "real identity" and pseudonym is an obviously fake value (`ltct-test-real-1`, `ltct-test-pseudo-1`). No real person's name, email or photo is used.
- **Evidence stays out of GitHub.** Screenshots, exports and notes go to the site team's private store. The delivering PR states only which checks passed and what failed.
- Spec 003 merged, and the open-courses change applied (no organisation groups in courses).
- Two test organisations, A and B. Accounts:
  - learners A1 (to be protected), A2 and B1;
  - A's manager and B's manager;
  - mentor M, from B, assigned to A1 (003), and also enrolled in the course as a learner;
  - course leader T (`editingteacher`) and course mentor N (`teacher`);
  - a site-team account.
- One open test course with A1, A2, B1 and M enrolled. It has a forum, a workshop, an assignment, a quiz, the 013 badge and certificate, and, once 011 lands, a scheduler with M's slots.
- Spec 009's V7 Android device, signed in as A2, and a second device signed in as M.

## Offline (no server)

```bash
python scripts/site_config.py validate
python -m pytest tests/test_site_config.py tests/test_protection_declaration.py
php tests/protection_harness.php    # effective level, withhold sets, pseudonym rules, member-cohort matching (managers cohort excluded)
```

## Checks

| # | Check | How | Expected |
|---|---|---|---|
| V1 | Config applies, is idempotent and rebuilds (US3, SC-006) | `validate`, `apply`, `drift` on the test instance. Then `apply` to a **fresh** instance plus a data restore | Drift says no differences, and it reports counts only. The fresh instance has the same levels, settings, roles and certificate. Organisation minimums come back with the data restore, not from the repo. |
| V2 | Each level holds in every view, raising and lowering (US1, US3-2, SC-001, SC-005) | As the site team, take A1 through `email` → `firstname` → `pseudonym` → `firstname` → `none`, and time each grant. After each step, check as A2, B1, T and N: participants, A1's profile (logged in, and the picture URL logged out), `ltct_role`, the organisation, forum posts, messages, workshop, quiz and gradebook (as T and N), user search, and the app after a refresh. Then check a protected user who is a mentor, on their mentee's Mentoring page | Each step shows only what that level allows. On lowering, the real fields return exactly; the picture does not, and the learner is told. A log row is written per step. Each grant takes under 2 minutes, and the web shows the change within 5 minutes. |
| V3 | No address or real name in mail (US1-5, FR-005, SC-004, R8) | As T, N and A's manager: participants and its CSV, grade export, quiz and completion downloads, A1's profile, and the app. Then trigger every provider that names A1 to someone else: forum post, private message, workshop and assignment submission notices, the scheduler booking and cancellation, completion, the badge. Inspect each raw message | No address of A1's anywhere. `From` is noreply, and `FromName` and the body carry only the protected display. |
| V4 | Entitled people see the real identity, others don't (US2, R7) | As M (web and app), A's manager, B's manager, A2 and the site team: the profile node, the Mentoring page (web and app), "People I support" and its CSV, the granting page. Then: set A's `managers_see_identity` to 0; end M's assignment; remove A's manager from the managers cohort. M is also a classmate: check what M sees in the forum | The real identity and the marker appear for M, A's manager and the site team only, and the CSV rows carry the marker. Non-entitled viewers never see a marker. With `managers_see_identity` at 0, A's manager sees only the display, including on the granting page. After the assignment ends, or after leaving the cohort, the real identity is gone. In the forum, M sees the display. |
| V5 | Search by real identity finds nothing (SC-003, R9, R13) | As A2, B1, N and T, at each level: search A1's real first name, surname, username and email in participants, selectors, messaging, report filters and `core_user_get_users`. Then try raising a user whose username contains their real surname | At `firstname`, the real surname, username and email return 0. At `pseudonym`, so does the real first name. At `email`, the email and username return 0. The raise is refused until a neutral username is given, and the learner is told their new login. |
| V6 | Certificates and badges (US4, R10) | A1 (protected), A2 (unprotected), a learner created after the upgrade, and an unprotected learner the site team renames all complete the course. Each downloads their certificate. Then T, and the site team, download A1's. Check the cron email to A1, the verify page and the badge page logged out | Every learner's own PDF has their real, current name. T's copy shows "Name on certificate". The site team's copy shows the real name. The cron email to A1 shows the real name, and no email goes to teachers. The verify and badge pages show the display. No filename carries a real name. |
| V7 | The record can't be undone (R2) | As A1: try to edit the name, email display and picture, on the web and from the app. As the site team: CSV upload "override", and `core_user_update_users`, both with real names. Disable a test organisation's cohort rule (its members go with no event) | Names are frozen in the form. The hook paths revert at once; picture and profile data revert on the next reconcile, whose task log shows a non-zero repair count. After the rule is disabled, reconcile recomputes the affected members. |
| V8 | The neutral surname (R3, R4) | With the locks set, A1 at `firstname` saves an unrelated profile change. The site team edits a locked name in `editadvanced.php`. Check the Mentoring page sort | The save succeeds with `lastname` empty, or the placeholder is adopted and V2 is re-run. The site team can edit. The sort is sensible. |
| V9 | Pictures and Gravatar (R6) | A1 at `email` with a test picture; then at `firstname`. Fetch the old `pluginfile.php` URL logged in and logged out | At `email` the picture stays. At `firstname` it is gone (404 or default). No Gravatar request. |
| V10 | Organisation scope without the profile field (R11, decision 2) | Switch the 004 report to the cohort condition, then set `ltct_org` to private. Run it as A's manager. Rename A's cohort idnumber temporarily. As A2, view A1's profile, participants and app | Only A's learners show. With the cohort missing, no rows show. A2 sees no organisation for anyone. Before the switch, the service refuses `firstname` and `pseudonym`. |
| V11 | Organisation minimum (FR-001a, US3-3, US3-4) | As the site team, set A to `firstname`: first without the acknowledgement, then with it. Add a new learner to A, and time how long until A2 sees their protected display. Try `pseudonym` as a minimum. Set an A member's own level to `email` through the service. Move A1 from A to B | Without the acknowledgement, refused with a count. With it, every A member is at `firstname`. The new learner is protected within 5 minutes and before any course listing shows their real name. `pseudonym` is refused. The looser own level is refused. After the move, A1 stays at `firstname` (`organisation-kept`) until an entitled person lowers it. |
| V12 | History and acknowledgement (R13) | A2 posts, then raise A2 to `pseudonym`, then lower them to `none` | Both the raise and the lowering ask for an acknowledgement. The service returns the "cannot recall" warning. A user with no activity is not asked. |
| V13 | Course leaders' backups and logs (R14) | As T: download a course backup with users; open the course log | Both refused. |
| V14 | Participation is untouched (FR-003, SC-002) | As A1 at `pseudonym`: post, reply, submit and review in the workshop, submit the assignment, message M, book M's slot, join an event, complete the course | Each succeeds with the same steps as A2. |
| V15 | Data export and deletion (FR-013) | Export A1's data, and A's manager's (as an actor). Delete a throwaway protected user, and a throwaway actor | A1's export holds the level, pseudonym, real values and log rows. The actor's export lists the changes made, by count and date. After deletion, the user's rows are gone, and the actor's `actorid` and `usermodified` are 0. |
| V16 | The learner is told, and only the entitled can grant (US3-1, US3-5, US3-6, FR-008, FR-012) | After V2's first grant, read A1's notifications. Compare A1's preview with A2's actual view. As A2, T, N and B's manager, open `/local/ltuse/protection.php?id=<A1>` and call `local_ltuse_set_protection` | A1 has a `protectionchanged` notice naming the level and what others see, with no real name. The preview matches A2's view, and the "how to ask" route shows. All four are refused on the page and in the web service. |
| V17 | Calendar feeds and scheduler (FR-011, US4-2, R15) | A1 books M's slot. Then protect A1. As M and as A2, export the calendar as ICS and fetch the feed URL | M's feed and the booking event show only the protected display after the re-save. A2's feed has nothing about A1. Re-run this check on every scheduler re-pin. |

## Real users (SC-007)

2–3 real protected users, or people acting for them, compare what others see with what they expected. Their findings are recorded in the site team's private store. **The PR records only the count who confirmed (for example "2 of 3") and the issues found.** It carries no pseudonyms, organisations, locations, screenshots or quotes.
