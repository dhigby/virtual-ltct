# Data model: Simple administration tooling (spec 008)

Two places hold data, and the line between them is constitution III.

- **The operator's machine, outside every git working tree**: intake files, move files, mentor files and any saved summary. Never the repository (research R14).
- **Moodle**: accounts, cohort memberships, enrolments, mentor assignments and the one new table. Never copied back to the repo (constitution I).

The repository holds only code, the declared role, service and setting, and fictitious test fixtures (`example.org`, `fixture-*` keys).

---

## 1. Operator files (outside the repository)

UTF-8 CSV with a header row; `;` separates multiple values inside one cell. Column names are case-insensitive. An unknown column is refused, not ignored, so a misspelt column cannot silently drop data. `ltct_admin.py template --kind <kind> --out <path>` writes each one blank.

### Intake file (US1)

| Column | Required | Rule |
|---|---|---|
| `email` | yes | Valid address; unique within the file (case-insensitive). The match key (research R2). |
| `firstname` | yes | Non-empty. Used only when the account is new; never written to an existing account. |
| `lastname` | yes | As `firstname`. |
| `organisation` | yes | A key declared in `moodle/site/organisations.yaml` (`site_config.validate`). |
| `country` | no | ISO 3166-1 alpha-2, as Moodle's `country` field. New accounts only. |
| `protection` | no | `none` (default), `email`, `firstname`, `pseudonym` (016's levels). Optional, and blank for nearly everyone: set only for a person who has asked for their identity to be protected when they are added (plan decision 1). It alone is the row's target: an organisation has no minimum (Doug, 2026-10-05 (scope review)). |
| `pseudonym` | no | Required when `protection` is `pseudonym`; refused otherwise. At most 100 characters (refused offline). One 016 would refuse is `rejected` (`pseudonym_invalid`) before any account is made (research R5, step 0c). |
| `email_checked` | no | `yes` or empty; refused on a row with no protection. With protection, every row `waits` until this says someone confirmed the address identifies neither the person nor their organisation (016 FR-016): as `email_reveals` when the server's check (016's `levels::email_reveals()`) flags the address, else as `email_unchecked` (research R5, step 0b). |
| `courses` | no | `ltct:<slug>` idnumbers, `;`-separated, enrolled through the Organisation enrolment (research R7, plan decision 10). |

No username, password, role or cohort column: the username is the email, or generated (R2), passwords are emailed by Moodle, and a cohort comes from `organisation` (R3).

### Move file (FR-015)

`email`, `organisation` (the new key). One learner per row.

### Mentor file (FR-017)

`learner_email`, `mentor_email`. The mentor must be in `ltct:mentors`.

### Course-mentor file (FR-018, one-course and cohort mentors)

`course` (`ltct:<slug>`), `mentor_email`, and exactly one of `learner_email` or `cohort` (a cohort idnumber).

### Managers file (FR-016)

`email`, `cohort` (`ltct:org:<key>:managers` or `ltct:mentors`), `action` (`add` | `remove`).

### Suspension file (FR-006)

`email`. One person per row; the command (`suspend` or `reactivate`) says which.

**Validation, offline first**: `ltct_admin.py` checks every column rule it can without Moodle (syntax, duplicates, declared organisation keys, idnumber shapes) and prints every problem with its row number before contacting the server. A file with any offline problem is refused whole (edge case "malformed or duplicate email").

**Existence, server-side, before any row**: courses are not declared in the repo, and a declared organisation may not be applied yet. So every preview first resolves each distinct course idnumber, cohort idnumber and `ltct:org:<key>` cohort the file names. If any matches nothing in Moodle, the preview returns a **file-level refusal** naming it, with no row results and no confirmation code, and apply refuses the same way (edge case "a cohort or course named in the list that does not exist"). A course that exists but that this organisation may not be enrolled into is a per-row `rejected`.

---

## 2. Row preview (in transit and on screen, never stored)

Returned by each `preview_*` function, one per row, and hashed into the confirmation code (research R15).

| Field | Meaning |
|---|---|
| `row` | 1-based row number in the operator's file. |
| `key` | Masked email (`a***@example.org`) unless `--show-people`. |
| `outcome` | See the table below. |
| `reason` | Plain sentence for `rejected`, `waits`, `flagged` and `lost`. |
| `changes` | What apply will do: `create`, `set_protection:<level>`, `set_org:<key>`, `enrol:<course idnumber>` … (shown; not part of the confirmation code) |

**Intake outcomes** (`intake_rules`, research R4):

| Outcome | When | Apply does |
|---|---|---|
| `new` | No live account has the email. | create → protection → organisation → courses |
| `unchanged` | Account exists, same organisation, every listed course already active. | nothing |
| `will_set_org` | Account exists with no organisation: an interrupted run, including one stopped after creation but before protection settled (research R5). | protection to target → organisation → courses |
| `will_enrol` | Account exists, same organisation, protection at or above target, some listed course not yet active. | courses |
| `flagged_other_org` | Account exists under another organisation. | nothing; use `move` (spec edge case) |
| `flagged_suspended` | Account exists and is suspended. | nothing; reactivate deliberately (plan decision 9) |
| `flagged_protection` | Account exists with an organisation and its effective protection is below the row's target. | nothing; raise it on 016's page (research R5) |
| `waits` | Target protection above `none`, and 016 is absent or `level_available()` is false. | nothing; no account is created (INTENT 2026-10-03) |
| `rejected` | Two live accounts share the email; a listed course is not one the organisation may be enrolled into; and similar. | nothing |

**Progress paths** (research R15): `new → will_set_org → will_enrol → unchanged`. On apply, a row whose current state lies further along its previewed outcome's path is finished or reported `already done`. Any other change refuses that row.

**Move outcomes** (`move_rules`, per learner and course, research R8): `kept`, `gained`, `suspended_by_rule`, `lost`. A learner with any `lost` course is refused as a whole. Protection plays no part in a move: the per-learner `flagged_protection` outcome went with organisation minimums (Doug, 2026-10-05 (scope review)).

---

## 3. Moodle data the tooling writes

| What | Where in Moodle | Written by | Notes |
|---|---|---|---|
| Account | `user` | `user_create_user($user, false, false)` under a per-email lock, then `user_created` | `auth = manual`, `confirmed = 1`, `mnethostid = $CFG->mnet_localhost_id`, `password = ''`, username the lowercased email, or `ltc-` + 8 base32 chars for a `firstname` or `pseudonym` target or an email that cannot be a username; `auth_forcepasswordchange` set and the password emailed after commit (research R2). Only `suspended` and `ltct_org` are ever written on an existing account (FR-019). |
| Organisation of record | `user_info_data` for `ltct_org` | `profile_save_data()` with only that field, then `user_updated` | Membership of `ltct:org:<key>` follows through `tool_dynamic_cohorts` (research R3). |
| Protection | 016's tables | `\local_ltuse\protection\service::set_protection()` | 016's data; 008 only calls it (research R5). |
| Managers / mentors cohort membership | `cohort_members` | `cohort_add_member()` / `cohort_remove_member()` | Only `ltct:org:<key>:managers` and `ltct:mentors` (research R9). |
| Cohort enrolment | `enrol` (`enrol = 'cohort'`) | `enrol_cohort_plugin::add_instance()`, `update_status()` | Marker `customchar1 = 'ltct:008'`, plus `customchar2 = 'pathway'` when made through a pathway; disabled, never deleted (research R7). |
| Per-row course enrolment | `user_enrolments` on 002's Organisation enrolment instance (`customchar1 = 'ltct:orgenrol'`) | 002's `organisation\actions::do_enrol()` | The same path managers use (002 R10). |
| Mentor relationship | `role_assignments` in the learner's user context, `component ''` | `role_assign()` / `role_unassign()` | 003's manual row; 003's observer adds the contacts (research R9). |
| Course-mentor enrolment | `user_enrolments` on an `enrol_self` instance with `customchar1 = 'ltct:coursementor'`, `customint6 = 0`; `role_assignments` of `teacher` with `component = 'local_ltuse'`, `itemid` = that instance | `enrol_user($instance, $uid, null)` then `role_assign(…, 'local_ltuse', $instanceid)`; removal `role_unassign(…)` then `unenrol_user()` | Kept in step by the sync. The role is component-owned so removal takes it away even if the mentor is enrolled another way (research R10). |
| Mentor group | `groups` (idnumber `ltct:mentorgroup:<mentor id>`), `groups_members` | `groups_create_group()`, `groups_add_member()` | Named "Mentor group <n>", never after a person or organisation. |
| Pathway enrolment | 006's `local_ltuse_pathway_cohort.enrol = 1` | 006's `assignments::assign($key, $cohortid, true)` | 008 adds no table for this (research R11). |

### New table: `local_ltuse_course_mentor`

Holds the course mentors that are **not** a learner's default mentor: one-course mentors and the mentors of a cohort in a course (research R10). Default mentors are read from `role_assignments` every time and never copied here.

| Field | Type | Rule |
|---|---|---|
| `id` | int(10), PK | |
| `courseid` | int(10), FK `course.id` | Course idnumber `ltct:<slug>`, not `ltct:officehours`. |
| `mentorid` | int(10), FK `user.id` | Must be in `ltct:mentors`. |
| `learnerid` | int(10), NOT NULL, default 0 | The learner's `user.id` for a one-course mentor; 0 when unused. |
| `cohortid` | int(10), NOT NULL, default 0 | The `cohort.id` for a cohort mentor; 0 when unused. Exactly one of `learnerid`, `cohortid` is non-zero. |
| `usermodified` | int(10) | Who recorded it (core persistent convention). |
| `timecreated`, `timemodified` | int(10) | |

Indexes: unique `(courseid, mentorid, learnerid, cohortid)`, which holds because neither column is ever NULL (a NULL would make every row distinct); `(courseid)`; `(learnerid)`; `(cohortid)`; `(mentorid)`. Inserts are get-or-create, and a duplicate-key error on a retry counts as success.

Learner data under constitution III: it lives only in Moodle and is never exported to the repo. It is covered by the privacy provider (`classes/privacy/provider.php` gains the table: export for the learner and the mentor, delete on user deletion), and by `user_deleted` (rows for that user removed). A restore of the database restores it (constitution II: backups must include `local_ltuse` tables).

**State**: a row exists → the sync enrols its mentor as Course mentor while its learner (or, for a cohort row, any member enrolled through that cohort) is actively enrolled as a Student; deleting the row → the sync removes the mentor once no other reason holds (research R10).

---

## 4. Course-mentor desired state (computed, not stored)

For course C and learner L with an active Student enrolment in C through cohort sync or the Organisation enrolment (manual pilot enrolments excluded). **Active**: `user_enrolments.status` active, `enrol.status` enabled, now within `timestart`/`timeend`, account not suspended. **Student**: the instance's `roleid`, not L's role assignments at event time (research R10).

```
mentors(L, C) =
    one-course rows (L, C)                                  if any
    else  ∪ cohort rows (K, C) for each cohort K that enrols L in C   if any
    else  default mentors of L (mentor role in L's user context)
```

The sync's target for C is the set of pairs `(mentor, L)`. A mentor is enrolled in C while they have at least one such L, and is in "Mentor group <n>" with exactly those learners. Everything else that carries the `ltct:coursementor` marker is removed. The rules are in `course_mentor_rules` (pure, harness-tested).

---

## 5. Repository artefacts (no learner data)

| File | What |
|---|---|
| `moodle/local_ltuse/db/services.php` | + service `ltuse_admin` and the `local_ltuse_admin_*` functions (contracts/admin-service.md). |
| `moodle/local_ltuse/db/access.php` | + `local/ltuse:administer`. |
| `moodle/site/roles.yaml` | + role `ltctadmin` (system) with exactly the capabilities in research R12, each with its `why`, and `allowassign: [mentor, teacher]`. |
| `scripts/site_config.py` | `PROTECTION_MANAGE_ROLES` (016's allowlist) gains `ltctadmin`, with its reason (research R5). |
| `moodle/site/settings/admin.yaml` | `allowaccountssameemail: 0`; `local_ltuse/coursementorsync: 0` until 016 narrows its course-mentor path to a shared mentor group (plan decision 11); `rows: [14, 11]`. |
| `tests/fixtures/admin/*.csv`? | **No.** `.gitignore` ignores `*.csv`; fixtures are built in the test from `example.org` strings, never committed as files. |
