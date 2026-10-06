# Quickstart: verifying badges and certificates

Run these checks on the temporary 5.2.3+ instance, with **test accounts only** (constitution X). Keep every screenshot, export, PDF and downloaded badge **outside this repository folder**: they show test learners' names. Record each result, with its date, the device and the app version, in the PR that closes the task, never as a committed file.

## Prerequisites

- `MOODLE_URL`, `MOODLE_TOKEN`, `MOODLE_DIR` and `MOODLE_SSH` are set (see the [site README](../../moodle/site/README.md)). `MOODLE_BADGE_CONTACT` is also set.
- `mod_customcert` and `availability_coursecompleted` are installed at their pins, `local_ltuse` is upgraded, and cron runs every minute.
- Spec 002's two test organisations exist, A and B, each with test learners.
- There is one small test course at stage 7 (a pilot) and one at stage 8.

## Checks

| # | Check | How | Expected |
|---|---|---|---|
| V1 | Config applies and is idempotent (US4) | `python scripts/site_config.py validate`, then `apply`, then `drift` | `apply` creates the template, the site certificate template and the settings. `drift` reports no differences other than the expected ones (below). A second `apply` changes nothing. |
| V2 | The wording gate fails closed (FR-006, SC-002) | Put `Certified` in `badges.yaml`'s name, then `Level 3 - Independent achieved` in the description, then a course title of `Certification prep`; run `validate` and `check_moodle_payload` | Each one fails before anything is sent. Revert. |
| V3 | A pilot issues nothing (edge case) | Publish the stage-7 course. A test learner, enrolled manually, completes it. | The badge is INACTIVE, and there is no certificate activity. After 10 minutes, no badge has been issued. |
| V4 | Delivery and automatic issue (US1, SC-001) | Suspend the V3 learner's manual enrolment. Publish the course at stage 8. Learner A1, enrolled through the cohort, completes it in the browser. | The badge is ACTIVE. A1 holds it within 2 minutes and gets a notification. The V3 learner does not hold it (R4). Time it. |
| V5 | Partial completion (US1-3) | A2 completes all but one lesson | No badge. The course page shows what remains, and the certificate shows "Not available unless: You completed this course." |
| V6 | The certificate (US2, FR-003) | A1 opens the certificate activity and downloads it | The PDF shows A1's name, the course title, the **completion** date, the issuing programme, a code and "training completed". It contains no "certified". Its size is recorded. |
| V7 | App and offline (FR-010, R3, R11) | On the 009 V7 device, A3 downloads the course, completes it offline, then reconnects | The badge appears in the app's profile within 5 minutes of the sync. The certificate downloads in the app in under 30 s at 256 kbit/s. A test account with a non-Latin name renders correctly. |
| V8 | Verification (US2-3, FR-009) | In a logged-out browser, open A1's certificate code at `mod/customcert/verify_certificate.php`, and A1's badge `badges/badge.php?hash=…`. Repeat with `forcelogin` on. | Only the name, the course, the date and the badge or activity name. No email and no other course. The profile and course links lead to a login page. |
| V9 | Who sees badges (US3, FR-011, R10) | Log in as learner B1 and as manager A, and open A1's profile. Call `core_badges_get_user_badges` with each one's token. | B1 cannot see A1's badges. Manager A is refused the profile, or sees no badges. The site team sees them. Once spec 003 exists, A1's mentor sees them and another mentor does not. *(Note 2026-10-05: spec 002 R9, as amended 2026-10-02, lets a manager open the profile of anyone in the organisation they manage (`VIEWPROFILE_FORCE_ALLOW`, `classes/profile_access.php`), so "refused the profile" no longer applies: Manager A opens A1's profile and sees no badges there, because `orgmanager` is a course role and holds no `moodle/badges:viewotherbadges` in A1's user context (R10). Spec 003's `mentor` role is now declared in `moodle/site/roles.yaml` without `moodle/badges:viewotherbadges`, so the mentor half fails until roles.yaml grants it and `MENTOR_ALLOW` in `scripts/site_config.py` is widened to allow it (a reviewed change) (R10, plan "Cross-spec effects").)* |
| V10 | Rewording without a duplicate (US4-2, R5) | Change the description in `badges.yaml`, then `apply` | That course's badge is `changed`, its id is unchanged, it is still ACTIVE, and A1 still holds it. The hash page shows the new text. |
| V11 | Republish after adding a lesson (edge case, R8) | Add a lesson, then republish | A1 keeps the badge and keeps access to the certificate. A2 still has one more lesson to do. |
| V12 | Retirement (edge case, R14) | Hide the course | A1's badge hash and certificate code both still verify, and A1 can still download from My certificates. |
| V13 | Rebuild (SC-004) | Apply to an empty test instance, then republish both test courses | Every badge and the certificate template exist, with identical wording (compare `drift --json` output). |
| V14 | Export (FR-013) | A1 downloads their badges and requests a privacy export | A baked PNG, the zip, and an export that contains the badge and the certificate. |

**Expected drift on ltuse.net (2026-10-05).** After `site_config.py apply` on 2026-10-05, `drift` reported "16 differences, 250 ok", all expected, none a group-mode or forum-group item:

- 12 leftovers of the test-a/test-b fixtures, kept on purpose for spec 002 T081 and for this spec's open checks: 2 `ltct_org` menu options, 2 categories, 4 cohorts (learner and managers for each), 2 dynamic-cohort rules and 2 organisation progress reports;
- 3 discussion forums missing, in courses published before spec 002's open-courses change (`coretech-computer-hardware`, `paratext-quotation-rules`, `software-support-and-troubleshooting-for-translation-teams`). Republishing a course creates its forum; `apply` never does;
- 1 env-missing, `badges_defaultissuercontact`, because `MOODLE_BADGE_CONTACT` was not set in the shell that ran drift. Set it (Prerequisites) before V1, and this one goes.

V1 passes when `drift` shows only these, or fewer. None of V1–V14 has been run yet.

## The criterion for real users

SC-003 is a "simple" criterion. US2 is not marked done until 2–3 real partner learners, for example at a stage-7 pilot followed by delivery, have found and downloaded their certificate without instructions, and their findings are recorded (constitution X).
