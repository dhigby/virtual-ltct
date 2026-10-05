# Research: Identity protection for at-risk users

**Plan**: [plan.md](plan.md). Every API below was looked up on 2026-10-02. Context7 was queried first (`/websites/moodledev_io_5_2_apis`, and `/moodle/moodle` for `UPGRADING.md`), then each claim was confirmed in upstream source:
- core: `MOODLE_502_STABLE`. In 5.1 and later core lives under `public/`; paths here leave that prefix off.
- the Moodle app: `moodlehq/moodleapp`, branch `main`.
- `mod_customcert`: branch `MOODLE_502_STABLE` (the pin is `v5.2.9`, `2026042014`).
- repo: `main` at `de31ede`, and the `003-mentor-role` branch at `0ae5942` (cited as `003:`).

Eight research tracks ran in parallel (names, email, profile fields, search, the app and authentication, outbound artefacts, repo dependencies, existing plugins). An adversarial reviewer was then asked to break the resulting design; its findings are folded in below and marked **(review H1)**, **(review M3)** and so on. "Inferred" marks a behaviour read from source but not run. A second review, with three lenses (spec coverage, constitution, technical soundness), returned about 50 findings on the first draft, many overlapping; this version fixes them.  Each **Verify** line must be confirmed on the 5.2.3+ instance, with test accounts only, before the task that depends on it is closed (constitution X). Quickstart numbers the checks.

**Scope review, 2026-10-05.** Doug ruled that protection is per person and opt-in, and accepted every recommendation of the scope review (spec Clarifications, Session 2026-10-05). The entries below are amended where it changed them, each marked "Doug, 2026-10-05 (scope review)" with the review's change number; superseded designs are kept under **Rejected** or **Superseded** so the reasoning stays readable. Sources newly relied on were looked up the same day on `MOODLE_502_STABLE` and are cited with file and line.

## R1. Protect names by rewriting the account, not by filtering views

**Decision**: For a protected user, our plugin writes the protected display into the account's own `firstname` and `lastname`:
- *first name only*: `firstname` = real first name, `lastname` = the neutral surname (R4);
- *pseudonym*: `firstname` = the pseudonym, `lastname` = the neutral surname.

It also blanks the four alternate-name fields (`firstnamephonetic`, `lastnamephonetic`, `middlename`, `alternatename`). The real names move to our own table (R5). Every view that shows a name then shows the protected display, because core renders names live from the user record.

**Rationale**:
- `core_user::get_fullname()` (`lib/classes/user.php`) reads the name fields from the user object it is given and calls no hook or plugin callback. `fullname()` (`lib/moodlelib.php`) only wraps it. No 5.2 hook overrides a display name: `core_user\hook\` holds `after_login_completed`, `before_user_deleted`, `before_user_updated`, `extend_bulk_user_actions`, `extend_default_homepage` and `extend_user_menu`, and `lib/classes/hook/output/*` changes page HTML only.
- So filtering every view is not possible without editing core (constitution XI), and it would still leak initials and picture alt text, which core builds from the record (`core_user::get_initials()`; the app's `user-avatar.ts`).
- Read live from the record, and therefore protected at once by the rewrite: forum posts and their emails (`FromName` and body), messaging, participants, gradebook, report builder name columns, badge pages (`badges/classes/output/issued_badge.php`), the customcert verify page and its `studentname` element, user search and selectors, and the web services the app uses (`user_get_user_details()` in `user/lib.php`).
- No free, maintained 5.0–5.2 plugin offers pseudonyms, display-name overrides or per-user identity hiding. `local_anonymise` exists, but it destroys data and is not this.

**Alternatives considered**:
- `alternativefullnameformat` with the real name in `alternatename`, shown to `moodle/site:viewfullnames` holders. Rejected. The format is site-wide. `viewfullnames` is a module-level capability checked in course and module contexts, so every teacher in a shared course would get it and a user-context mentor would not. Many views never pass the override. And global search indexes all six name fields in the document title (`user/classes/search/user.php`), so anyone could find the real name.
- Filtering through output hooks or a theme. Rejected for the reasons above.
- Two accounts per person. Rejected: it splits completion, grades, badges and certificates.

**Verify** (blocks US1, SC-001): rename a test learner to each level, then as a classmate check every view in quickstart V2 on the web and in the app.

## R2. Enforce the protected record against every writer

**Decision** (amended by Doug, 2026-10-05 (scope review), changes 17–19): three layers, none trusting the others, and none doing any work for a person who is not protected:
1. A `core_user\hook\before_user_updated` callback in `local_ltuse`. It re-applies the protected values (names, alternate names, `maildisplay`, and the fields R6 withholds) on every `user_update_user()` call for a user with a protection row, unless the service is writing that same user (see "Bypass and concurrency" below). It never throws: it is wrapped in `try`/`catch (\Throwable)` and reports a failure through `debugging()`, so a fault in it cannot block an account edit.
2. The `\core\event\user_updated` observer. It returns at once when the user has no protection row, or is at `none`, and otherwise re-applies a drifted account (or queues the adhoc task `apply_protection` when the caller is inside a transaction of its own, or the user is busy). `\core\event\user_deleted` runs the shared deletion routine (contract, Privacy).
3. A scheduled task, `reconcile_protection`, every `reconcile_minutes` (60). It reads only the protection rows above `none`, does nothing when there are none, and re-applies any account that differs from its protected state, including a picture uploaded from the app. When any repair fails it throws, so core records the run as failed and its failed-task handling raises the alert.

**Superseded (2026-10-05)**: the `user_created` observer, the `cohort_member_added` and `cohort_member_removed` observers on organisation member cohorts, and reconcile's sweep of every member of an organisation with a minimum and of every user's `ltct_certname`. They existed for organisation minimums (cut, R12) and the certificate-name field (cut, R10), and they touched every account (changes 8, 9, 18, 19).

**Rationale**:
- `before_user_updated` is dispatched at the top of `user_update_user()` (`user/lib.php`, around L156–171) with the `$user` object. That covers `user/edit.php`, `editadvanced.php`, `core_user_update_users` and the auth sync path (`authlib.php` `update_user_record()`).
- **(review M2, M3)** It does not cover every writer:
  - `profile_save_data()` writes custom fields directly.
  - `core_user::update_picture()`, and the app's `core_user_update_picture`, fire no hook. The app path fires no event.
  - `tool_uploaduser` saves profile data only after `user_created` has fired (`process.php` L1059).

  The reconcile task repairs these within the hour, for protected accounts only. Since protection is granted to a person who already has an account (at intake, R13), account creation needs no observer.
- The login-time write in `lib/moodlelib.php` (around L2995–3020) touches access times and IP only, so login never reverts a name.
- `user_update_user()` is deprecated for 5.3 (MDL-82650). The hook must be re-checked on its 5.3 replacement before `$plugin->supported` is widened (plugin README).

**Bypass and concurrency**:
- The service holds a private static **set of user IDs** it is writing. It adds an ID immediately before one `user_update_user($user, false, true)` call and removes it in a `finally` block. `triggerevent = true`, so other plugins still see the change.
- The hook and the observer skip only a user ID in that set. Nothing else can reach the set, and an exception cannot leave it open.
- Each application runs under a per-user `\core\lock\lock_config` lock and a delegated transaction, so the page and the reconcile task never interleave on one user.
- A held real value is never overwritten with an empty one.

**Upgrade risk (Principle XI, listed in the plugin README)**: the hook is a notification, and our change persists because PHP passes the object by handle. A PHPUnit test fails if a mutation made in the callback stops being stored by `user_update_user()`, and V7 is re-run on every core upgrade **while anyone is protected** (change 17).

**Alternatives considered**:
- A dedicated auth plugin for protected accounts, with locked fields and `can_edit_profile() = false`. Held back, and used only if V7 shows leaks.
- Per-user capability overrides. Rejected: `moodle/user:editownprofile` is checked at system context.

**Verify** (blocks US1, US3): V7. Edit a protected account through `user/edit.php`, `editadvanced.php`, `core_user_update_users` and both uploaduser override modes, and upload a picture from the app. The record is restored immediately on the hook paths, and by the next reconcile run on the picture and profile-data paths.

## R3. Names and email are not locked; login methods are general account rules

**Decision** (Doug, 2026-10-05 (scope review), changes 3 and 4; plan decision 3 **rejected**):
- No `auth_manual/field_lock_*` setting is declared. Learners keep editing their own name and email. For a protected learner, the R2 hook puts the protected name back on every edit, and the neutral surname is a mandatory one-character placeholder, so their own profile form always saves (R4).
- `auth` (manual, plus core's `nologin` and `webservice`, which the publisher's token uses), `registerauth = ''` and `authpreventaccountcreation = 1` are general account rules, declared in spec 008's `moodle/site/settings/admin.yaml` with `authloginviaemail` and `protectusernames`. They are no longer part of this spec, and `validate` no longer refuses another login method (`AUTH_ALLOWED` is gone).
- **A per-account guard instead (not built, tasks T042):** a protected account must have `auth = manual` and no row in `auth_oauth2_linked_login`. The service would check, or set, both when it applies a level.

**Rationale**:
- Email is everyone's login (spec 008, `authloginviaemail`), so `field_lock_email` would have made every login change a site-team job, and the name locks every name change. That burdens everyone to protect the few (spec Clarifications 2026-10-05).
- Accounts on this site are created by the site team (spec 002, spec 008), so self-registration has never been the route; declaring it off in `admin.yaml` means drift catches anyone turning it on.
- **OAuth2, if it is ever enabled** (`auth/oauth2`, `MOODLE_502_STABLE`):
  - A linked login signs a user in whatever their own `auth` is: `complete_login()` maps the issuer's username through `api::match_username_to_user()` and completes the login for the mapped user (`auth/oauth2/classes/auth.php` L464–497, `classes/api.php` L92–115; table `auth_oauth2_linked_login`, `classes/linked_login.php` L41).
  - It overwrites profile fields only on an account whose `auth` is `oauth2`: `update_user()` returns the record unchanged otherwise (`auth/oauth2/classes/auth.php` L303–312).
  - So a protected account on `manual` keeps its protected name even if OAuth2 arrives, and removing its linked logins keeps an outside identity from signing into it. That is what the per-account guard holds, with no site-wide refusal.

**Superseded (2026-10-04 design)**: lock `firstname`, `lastname` and `email` (`locked`) for every manual account, so learners could not change their own; declare `auth` = `manual` only and refuse anything else in `validate`. `user/edit_form.php` (L150–181) hard-freezes locked fields, which is why the design worked; it was rejected for its cost to everyone.

**Verify** (blocks R4): V8. A protected learner at `firstname` saves an unrelated change on their own profile; editing their own name there is reverted by the hook.

## R4. The neutral surname

**Decision** (Doug, 2026-10-05 (scope review), change 3): the protected `lastname` is the declared placeholder `protection.yaml: neutral_surname`, `·` (one non-letter character). It is mandatory: `validate` refuses an empty value.

**Rationale**:
- `core_user::validate()` checks `lastname` as `PARAM_NOTAGS`, `NULL_NOT_ALLOWED`. `\core\param::validate_param` rejects only null, so `''` would be valid through the API.
- But `lastname` is a required field in `useredit_shared_definition()` (`useredit_get_required_name_fields()`). Names are not locked (R3), so a protected learner sees their own name fields; with an empty surname they could not save their profile at all. The placeholder avoids that.
- The placeholder shows in every name (`Ana ·`), and carries nothing of the real surname.

**Superseded**: an empty surname, with the placeholder only as a fallback if V8 failed. It depended on the field locks (R3).

**Verify**: V8, and that the 003 Mentoring page, which sorts by `lastname`, still sorts sensibly.

## R5. Real identity lives in our own table

**Decision**: `local_ltuse_protection` holds one row per protected user:
- the user's level (`ownlevel`, and `effectivelevel`, the level applied, which is the same since no organisation sets a minimum);
- the pseudonym;
- the real first name and surname;
- a **per-field** snapshot of every value R6 withholds, recording when each field was taken.

The row is deleted once the user is back at `none`. `local_ltuse_protection_log` records every change (FR-008), including whether the person asked (`requested`) and whether their email was checked (`emailchecked`). Both tables have a privacy provider, so the learner's data export includes them (FR-013) and deletion purges them.

The real name and the snapshot can be **corrected while the user is protected**, by the site team, on the granting page or through the web service (R12).

**Superseded (2026-10-05, change 8)**: one custom profile field, `ltct_certname` (private, locked, "Name on certificate"), holding the real full name of **every** learner for the certificate, owned by the service for all users. It touched every account and needed an hourly back-fill. The certificate now prints the account's name (R10).

**Rationale**:
- A custom profile field is visible per field, never per user (`profile_field_base::is_visible()`, `user/profile/lib.php` L450–489). PRIVATE is visible to the user and to holders of `moodle/user:viewalldetails` in the user context; NONE only at system context.
- Mentors and organisation managers do not hold `viewalldetails`, and must not:
  - 003's `MENTOR_ALLOW` (`003:scripts/site_config.py` L269–276, 003 research R2) refuses it;
  - `ORGMANAGER_DENY` (`scripts/site_config.py` L275) refuses it;
  - it would also expose `ltct_role`, the username and the last IP.
- A table we own is shown exactly to the people R7 names.
- **Snapshot per field (review)**: a row-level "already held" check would skip a field the previous level did not withhold. Raising from `email` to `firstname` would then miss the current city or country, and a later restore would write stale values. Each field is captured the moment it first becomes withheld.
- **Backups**: our tables are not in course backups. A course backup with users carries the account's own fields, which for a protected user are the protected values. Course leaders cannot download backups (R14), and automated backups are site-team only.

**Alternatives considered**:
- PRIVATE profile fields for every real value, with `viewalldetails` granted in user context. Rejected: it reverses a recorded 003 decision and over-exposes.
- The core alternate-name fields. Rejected (R1).

## R6. What each level withholds, and how

| Level | On the account | Settings it relies on |
|---|---|---|
| Email hidden | `maildisplay = 0` (hidden) | R8 |
| First name only | Email hidden, plus: `lastname` neutral; alternate names blank; blank `country`, `city`, `url`, `institution`, `department`, `phone1`, `phone2`, `address`, `idnumber`, `ltct_role` and the five `ltct_exp_*`; picture deleted | `forceloginforprofileimage = 1`, `enablegravatar = 0` |
| Pseudonym | As first name only, with `firstname` = pseudonym | As above |

The service takes the per-field snapshot (R5) before blanking anything, and restores the fields on a downgrade, **except the picture**:
- `core_user::update_picture()` with `deletepicture = 1` calls `delete_area_files` (`lib/classes/user.php` L657–660), so the file is gone.
- The picture is deleted only when the level becomes `firstname` or `pseudonym`, never at `email`. The scope review asks for a warning on the granting page before the picture is deleted (T045, not built).
- The learner is told that a picture removed by protection must be uploaded again after a downgrade.

**Rationale**:
- `hiddenuserfields` is site-wide, so per-user hiding means not holding the value on the account (profile track).
- Pictures:
  - There is no per-user picture lock. `pluginfile.php` serves the stored file, so `picture = 0` alone is not enough.
  - `forceloginforprofileimage = 1` stops the public route. It is kept (Doug, 2026-10-05 (scope review)): it covers the hour before reconcile deletes a picture uploaded from the app, and the `email` level keeps its picture.
  - `enablegravatar = 0` matters, because Gravatar would publish an unsalted MD5 of the email as the default picture.
- **(review L3)** The fields beyond the spec's list identify a person as much as a surname does.
- **`ltct_role` (review)**: its values ("Translator" and others) connect a person to Bible translation, which is exactly the risk the spec describes. It is `visible: teachers`, so every course leader sees it.

**What the site does not rewrite**: the learner's own words stay on the account, in line with the spec ("Things the learner writes", Assumptions): their profile `description`, interest tags, posts and submissions. The granting page and the `protectionchanged` notification tell the learner to review them, together with file author metadata and copies already sent (R13).

**Organisation**: `ltct_org` is never blanked on the account, and stays visible at every level (R11).

**Verify** (blocks US1): V2 and V9. A classmate and a course leader see none of the withheld fields and no picture, logged in or out, and no Gravatar. At `email` level the picture stays.

## R7. Who sees the real identity, and where

**Decision**: There is one function, `\local_ltuse\protection\entitlement::can_view_identity($viewer, $user)`. It is true for:
1. **the site team**: holders of `local/ltuse:viewidentity` at system context (the `manager` archetype);
2. **an assigned mentor**: holders of `local/ltuse:viewidentity` in the learner's user context, which the 003 `mentor` role gains (`MENTOR_ALLOW` gets this one entry, a reviewed change). The grant ends with the assignment;
3. **a manager of the learner's own organisation**: a member of `ltct:org:<key>:managers`, where `<key>` is the learner's `ltct_org`, and the learner is in that key's member cohort, the field-and-cohort agreement `organisation\access::is_org_member_of_manager()` already requires (002 R10). This path is **unconditional** (Doug, 2026-10-05 (scope review), change 10): the organisation setting `managers_see_identity` that could switch it off is removed;
4. **the course mentor who assesses the learner in a course they take** (FR-006, amended 2026-10-04, narrowed 2026-10-05 by change 22): the learner is actively enrolled in a course whose idnumber is `ltct:<slug>` (`^ltct:[^:]+$`, one published or pilot course) and is not `ltct:officehours`; the viewer holds `local/ltuse:viewidentity` in that course's context; **and** the learner is a member of the viewer's own group in that course, idnumber `ltct:mentorgroup:<viewer id>`, which spec 008's course-mentor sync creates. Looked up with `groups_get_group_by_idnumber()` and `groups_is_member()` (`lib/grouplib.php` L150 and L571, `MOODLE_502_STABLE`). The declared `teacher` role ("Course mentor") gains the capability through `roles.yaml`. The grant ends when the viewer's role, the learner's enrolment or their group membership ends.

`can_manage_protection($viewer, $user)` is true for the site team (`local/ltuse:manageprotection`) and for a manager of the user's own organisation (path 3), never for oneself. What a manager may then change is limited to intake (R12). `is_site_team()` answers that question; `may_be_entitled()` is a cheap test of whether the viewer could be entitled to anyone, asked before the per-person checks on a learner's own profile (change 12).

Every surface calls these functions, never `has_capability` alone.

**Organisation managers** are recognised by managers-cohort membership, the structure 002 already builds and `local_ltuse_managed_organisation_keys()` (`lib.php` L76–112) already reads. So this needs no new role and no change to 002's `orgmanager` or `ORGMANAGER_DENY`. Membership of a managers cohort is set only by the site team (002 FR-012), and leaving it ends the entitlement at once. A person who does not want their organisation's managers to see their identity is placed by the site team under a neutral organisation entry with no managers (change 10).

**Surfaces** where the real identity appears, with the **Protected** marker, for an entitled viewer only:
1. the profile-page node (`local_ltuse_myprofile_navigation()`), on the web. It appears only on a protected person's profile; on one's own profile only when one is protected or supports someone who is (change 12);
2. the 003 Mentoring page **and its app handler** (`db/mobile.php`), the surface that works in the app;
3. a **"People I support"** page in `local_ltuse`, listing the protected people the viewer is entitled to. ~~With a CSV download that carries the marker on each row.~~ It has no download (Doug, 2026-10-05 (scope review), change 14): a file of protected people is the riskiest copy there is;
4. the granting page (R12), linked only from a protected member's profile (change 12).

Everywhere else, an entitled viewer sees the protected display. A **non-entitled viewer never sees the marker**, because the marker itself says "this person is at risk". US2-1, US2-3 and FR-007 are amended (decision 7, change 14).

**Rationale**:
- Core cannot show a mentor or manager the real name in a forum or gradebook without showing it to everyone, because it renders from the record (R1, review H5).
- **Reports**: a plugin cannot add an entity to a core report-builder datasource. Entities are added only in the datasource's own `initialise()`, and `reportbuilder/classes/` has no hook directory in 5.2. Spec 004's reports use `core_course\reportbuilder\datasource\participants`, so the real identity and the marker cannot be put on them. Our own page (surface 3) replaces that. Rebuilding the participants datasource inside `local_ltuse` was rejected as too much core logic to copy and maintain.
- The profile node is web-only. In the app, the Mentoring handler is the entitled surface.
- **Path 4's exclusions**: the office-hours course enrols every mentor as `teacher` and every mentee as `student` in one course (spec 011 R16), so counting it would entitle every mentor to every mentee. A course without an `ltct:` idnumber is not counted, so the rule fails closed. Before change 22, every `teacher` in a course was entitled to every learner in it, which reached mentors from other organisations; the mentor-group condition limits it to the learners the mentor assesses, and is what lets spec 008 switch its course-mentor sync on. `editingteacher` (course leaders) does not get the capability: FR-006 names course mentors, not course leaders. The capability is checked in a course context, which a user context does not inherit from, so a course mentor gains nothing in paths 1 and 2.
- **Known limit of path 4 (checked 2026-10-05)**: by core's defaults an `editingteacher` may assign `teacher` in their course (`get_default_role_archetype_allows()`, `lib/accesslib.php` L2202–2206, and `moodle/role:assign`, `lib/db/access.php` L651–660), may manage groups (`moodle/course:managegroups`, L1182–1190) and may set a group's idnumber (`moodle/course:changeidnumber`, L1095–1104; `group/group_form.php` L58–62). A course leader could therefore make themselves a course mentor with a mentor group holding a learner. Nobody is given `editingteacher` in normal use (courses come from the repo), so this is listed as a known gap; tasks T046 asks the maintainer whether to narrow `editingteacher`'s assignable roles.

**Alternatives considered**: `viewalldetails` (rejected in R5); a site-wide alternative name format (rejected in R1); two organisation-manager roles chosen per organisation (rejected, since a role per organisation setting goes against VII); an organisation setting that withholds identities from its own managers (`managers_see_identity`, built 2026-10-04, **rejected** 2026-10-05 by change 10, reversing Doug's 2026-10-02 answer).

**Verify** (blocks US2): V4. As the assigned mentor, the course mentor whose group holds A1, another course mentor of the same course, A's manager, B's manager, a classmate and the site team: the four surfaces show the real identity to the mentor, the first course mentor, A's manager and the site team only, including the Mentoring page in the app. After the mentor assignment ends, A1 leaves the mentor group, or A's manager leaves the managers cohort, the real identity is gone.

## R8. Email: hidden from classmates, and checked person by person

**Decision** (Doug, 2026-10-05 (scope review), changes 1 and 2; plan decision 1 **rejected**):
- The *email hidden* level sets `maildisplay = 0`, which hides the address from classmates.
- Course staff keep seeing email, as core does by default. Instead, **when any level is granted**, the granter confirms that the account's email address reveals neither the person's name nor their organisation (`emailchecked`, recorded on the log row). The granting page warns when the part before the @ contains the real first name or surname, or the domain contains the organisation's key (`levels::email_reveals()`, `service::email_warnings()`). An address that identifies the person is replaced with a pseudonymous one before the level is saved (FR-016). Spec 008's intake makes the same check when it creates a protected account.
- The site declares, for everyone, only core's defaults and the settings that cost nobody anything:
  - `showuseridentity = email` (core's default, `admin/settings/users.php` L229–231). It must **never include `username`**: for most accounts the username is the email the person had at intake (spec 008), which for a protected person may be the identifying address they changed away from;
  - `grade_export_userprofilefields` = core's default (`firstname,lastname,idnumber,institution,department,email`, `admin/settings/grades.php` L66–69). With email as everyone's login and `idnumber` empty, email is the only column an export can be matched on;
  - `grade_export_customprofilefields = ''` (core's default, L73–75), so no withheld `ltct_` field leaves through an export;
  - `allowedemaildomains = ''` (core's default);
  - `enablegravatar = 0` (core's default).
- It restricts `moodle/reportbuilder:edit` and `editall` to the site team, as general site policy.

**Rationale**:
- `maildisplay` stops classmates (profile, web services, email search).
- **(review H1)** `maildisplay` is ignored by:
  - identity fields (`user/classes/fields.php` `get_identity_fields()` L363–410), shown to `moodle/site:viewuseridentity` holders, which the `teacher` and `editingteacher` archetypes have, across participants, the bulk download, selectors and activity reports;
  - `moodle/course:useremail` (`lib/myprofilelib.php` L118–165, `user_get_user_details()` L374–386, which the app uses);
  - the gradebook export (`grade/lib.php` `grade_helper::get_user_profile_fields()`);
  - the report-builder email column.
- So course staff see a protected person's address. Hiding it from them meant hiding every learner's address from every course leader, course mentor and organisation manager, and cancelling the organisation manager's email grant (2026-10-02); people who ask for protection usually already have a pseudonymous address (Doug). The per-person check costs only the people who ask. A protected person's (pseudonymous) address visible to course staff is a known gap (FR-015).
- Learners and course leaders can still reach each other through Moodle messaging, which hides no one (FR-003).
- Outbound mail is already safe:
  - `email_to_user()` uses the sender's real address only if `can_send_from_real_email_address()` passes, which needs a non-empty `allowedemaildomains`. Otherwise `From` is `noreply` and `FromName` is `fullname()` "(via site)".
  - The forum `Reply-To` is a recipient-keyed inbound address (`mod_forum\task\send_user_notifications`).
  - `emailonlyfromnoreplyaddress` no longer exists in 5.2.

**Rejected (2026-10-05, decision 1)**: `showuseridentity = ''`, `grade_export_userprofilefields` without email, institution and department, and `moodle/course:useremail` prohibited for `editingteacher` and `teacher`, for everyone. It was justified by course leaders not being entitled under FR-006; but FR-006 limits who sees the real *identity*, and a checked, non-identifying address is not that (change 26).

**Alternatives considered**: a relay address in `user.email` for protected users, with the real one in our table. This also closes the badge-assertion hash (R10), but needs a mail relay that someone has to operate. Left unbuilt.

**Verify** (blocks US1, SC-001): V3. The granting page warns on an identifying test address and refuses a raise without the confirmation. As a classmate, no protected learner's email appears anywhere. As teacher, editingteacher and organisation manager, the address shown is the checked one. The raw headers of a forum post email and a message email from the protected learner carry no real address or name.

## R9. Search: real names cannot be found

**Decision** (amended by Doug, 2026-10-05 (scope review), changes 7 and 15): with R1, R5 and R6 in place, search needs only two more things:
- Keep the alternate-name fields blank (R2 enforces this).
- At `firstname` and `pseudonym`, a username that contains the real first name or surname is replaced with a neutral one automatically (R13).

**Superseded**: disabling the `core_user` global search area (`core_search/core_user_user_enabled`). Global search is not switched on on this site; the spec that switches it on (014, row #24) handles a protected person granted after their account was indexed (change 7).

**Rationale**:
- Core search matches only `firstname`, `lastname`, the four alternate-name columns, and the identity fields the viewer may see. That covers:
  - `users_search_sql` and the user selectors;
  - `core_message\api::message_search_users`;
  - participants keywords (`participants_search.php` L949–1120);
  - report-builder filters;
  - `core_user_get_users`.
- A real name held only in our table matches for nobody.
- The `core_user` search area indexes all six name fields, and an old document stays live until the next index run **(review M5)**. That matters only once global search is on.
- At `email` level the name is not withheld, so real-name search applies only at `firstname` and above. Classmates cannot search by email at any level; course staff see the email as an identity field and can search by it, which is why it is checked (R8). Usernames are not shown to peers.

**Verify** (blocks SC-003): V5, at all three levels.

## R10. Badges and certificates

**Decision** (Doug, 2026-10-05 (scope review), change 8):
- **Badges** need nothing: the public `badges/badge.php?hash=` page shows the live `fullname()`.
- **Certificates**: spec 013's template keeps `studentname`, which prints `fullname($user)`: for a protected learner, the protected display, on their own PDF, on any copy and on the verify page. A certificate in the real name is issued by the site team on request, by hand. The certificate activity keeps `emailteachers = 0` and `emailothers = ''`, which spec 013's publisher already writes on every publish.

**Superseded (2026-10-04 design)**: print the name through `mod_customcert`'s `userfield` element bound to a PRIVATE profile field `ltct_certname` holding every learner's real name, back-filled by an upgrade step and kept in step by observers and the reconcile task for every user. It touched every account, made staff downloads show a label instead of a name, and produced a real-name PDF on every download, which is itself the riskiest copy of the link between the two names.

**Rationale**:
- `studentname` prints `fullname($user)` (R1), so it follows the account.
- **(review M4)** `emailteachers` and `emailothers` attach the PDF as generated for the learner; with them off, no copy goes to anyone else.
- **Known gap**: the badge assertion JSON (`badges/json/assertion.php`) embeds `sha256(email + salt)`, and the salt is published. Someone who already knows the email can confirm it (decision 6). The per-person email check (R8) means the address is not an identifying one; only a relay address would close this.

**Verify** (blocks US4): V6.
- A1's (protected) and A2's (unprotected) own PDFs show the name on their accounts.
- No certificate email goes to teachers or other addresses.
- The verify page and the badge page show the protected display.
- The PDF filename has no real name.

## R11. The organisation stays visible; 004's report no longer scopes by it

**Decision** (Doug, 2026-10-05 (scope review), change 11; plan decision 2, **option (a)**):
1. Spec 004's per-organisation report scope moves from the `user:profilefield_ltct_org` condition to a **cohort-membership** condition on the organisation's member cohort: `cohort:idnumber` equal to `ltct:org:<key>`. The report's Organisation column, which showed the same value on every row, is dropped. `validate` refuses a `user:profilefield_ltct_org` condition on any report (tasks T034–T035, landed).
2. `ltct_org` stays `visible: all`. It is **not** made private (T036 not shipped). So at `firstname` and `pseudonym`, others still see the learner's organisation, for example an SIL Area. FR-001 no longer promises to hide it; the learner's preview (FR-012) and the known gaps (FR-015) say so.
3. There is no gate: every level is available once `protection.yaml` is stored. The service's `orgscope` check (`levels::NEED_ORGSCOPE`, `service::orgscope_ready()`, the stored `orgscope_ready`, `protection:err:notready`) is removed.

If one protected person cannot accept their organisation showing, the maintainer decides then: place them under a neutral organisation entry, or make `ltct_org` private for everyone (option (b), below).

**Rationale**:
- `ltct_org` cannot be blanked on the account. **(review H4)** Blanking it would:
  - drop the user from their dynamic cohort;
  - suspend them in every cohort-synced course (`enrol_cohort/unenrolaction = 3`), which breaks FR-003;
  - remove their organisation manager's entitlement.
- A report scope must never rest on a profile-field condition. Report builder makes a profile-field condition available only if `is_visible(system)` (`reportbuilder/classes/local/helpers/user_profile_fields.php` L214), and `datasource::get_active_conditions()` **silently skips** an unavailable condition (`reportbuilder/classes/datasource.php` L288–315), so a scope on `ltct_org` would widen to every organisation the moment the field was hidden. The participants datasource joins the cohort entity through `cohort_members` (`course/classes/reportbuilder/datasource/participants.php`, `MOODLE_502_STABLE`), and its `cohort:idnumber` condition is a text filter. A cohort condition is fail-closed by construction: a missing cohort matches nobody. So the scope change is worth landing whatever happens to the field.
- Making the field private would hide every learner's organisation from every classmate, to cover a few people who may not need it (spec Clarifications 2026-10-05).
- **(review H3)** Organisation-named course groups would also show the organisation. The open-courses change (011 handoff B1, no groups) removes them, so it lands first.

**Rejected or not taken**:
- Option (b): keep the gate, and make `ltct_org` private for everyone when someone first asks. Not taken (2026-10-05).
- Our own profile-field datatype whose `is_visible()` decides per user. Rejected: `@internal` in core (in tension with XI), and a datatype change is a blocking `wrong-datatype` in our applier (`classes/siteconfig/profilefields.php` L33–34).

**Verify**: V10. The cohort-condition report shows exactly A's learners to A's manager, and nothing when the cohort is missing. As a classmate, A1's organisation shows at every level, and A1's preview says so.

## R12. Granting protection

**Decision** (amended by Doug, 2026-10-05 (scope review), changes 9, 10, 13, 14, 23):

**For a person who asks**:
- A page in `local_ltuse`, `/local/ltuse/protection.php?id=<userid>`, lets an entitled person:
  - set, change or remove the user's level;
  - set the pseudonym;
  - **correct the real name and the snapshot values** while the user is protected (site team only).
- It needs `can_manage_protection()` (R7): the site team, or a manager of the user's own organisation.
- **A raise records two facts** (changes 13, 2): `requested`, the person asked for it, and `emailchecked`, the address identifies neither them nor their organisation (R8). Both are required, both go on the log row, and a raise without them is refused (`protection:err:notrequested`, `protection:err:emailnotchecked`). There is no free-text reason field.
- **Managers grant at intake only** (change 14): anyone but the site team may only raise a level, for someone with no activity yet (R13), with no correction to the real name or a held value (`levels::manager_may()`). Corrections, raises after activity, lowering and removal are the site team's (`protection:err:siteteam`). The check is in `service::set_protection()`, so the page, the web service and spec 008's intake agree.
- The same logic is the web service `local_ltuse_set_protection`, used by the site team's scripts and spec 008's intake.

**The learner**: if protected, their profile node shows their level and a preview of what others see (FR-012), including that their organisation and, to course staff, their email address stay visible. The way to ask is offered at intake, in the welcome message and in site help, not on every profile (change 12); the node itself says to contact the site team.

**Organisations: nothing to grant** (changes 9, 10).
- There are no organisation minimums and no `managers_see_identity` setting. The organisation page `/local/ltuse/orgprotection.php`, the web service `local_ltuse_set_org_protection`, the capability `local/ltuse:manageorgprotection` and the table `local_ltuse_org_protection` are removed.
- **Neutral organisation names** (change 23): an organisation that asks not to be named publicly gets a neutral key and display name in `organisations.yaml` from its first commit, because the key is the `ltct_org` option and the cohort and category idnumber, can never change, and git keeps history. No other organisation needs one. SIL's Area entries (`sil-*`, `silp-*`) name SIL's own published structure.

**Superseded (2026-10-04 design, plan decision 8)**: an organisation minimum (`email` or `firstname`, never `pseudonym`) and a `managers_see_identity` setting per organisation, as Moodle data set by the site team; an organisation that "may need protection" given a neutral key. Superseded because it protected and constrained people who did not ask: their surname, details and picture went whether or not they wanted it, and their managers could be shut out (change 9 supersedes Matthew's 2026-10-02 line; change 10 reverses Doug's 2026-10-02 answer).

**Rationale**:
- FR-008: protection is for the person who asks, granted by their manager at intake or by the site team.
- Core has nothing to reuse.
- Entitlement through managers-cohort membership limits a manager to their own people (R7).
- Limiting managers to intake keeps the riskiest changes (renaming someone already seen, lowering) with the site team, who see the whole history.

**Verify**:
- V11 and V16.
- An unentitled person (a classmate, a course leader, another organisation's manager) is refused on the page and in the web service.

## R13. Protection is set before the learner's first activity, and is never lowered automatically

**Decision** (amended by Doug, 2026-10-05 (scope review), changes 15 and 16):
- **Protect early.** Protection is meant to be set when the account is created, at intake, before any enrolment. Then nothing has ever been shown under the real identity.
- **Usernames.** Everyone's username is their email at intake, and everyone signs in with their email (spec 008, `authloginviaemail`). Only for a person protected at `firstname` or `pseudonym`: when the username contains the real first name or surname, compared after case-folding and Unicode NFC normalisation, the service replaces it with a neutral one automatically, in spec 008's format (`ltc-` and 8 lowercase base32 characters, `service::neutral_username()`). The granter has no username field, and the learner gets no "new login" notice: they keep signing in with their email.
- **Late changes need an acknowledgement.** A raise or a lowering for a user who already has activity needs an explicit acknowledgement on the page, or `acknowledgehistory = true` through the web service. A fresh account is one option to discuss with the person, not the default advice.
- **No automatic lowering.** A downgrade is never automatic; only the site team lowers or removes a level.
- **"Has activity"** means the user has logged in (`user.firstaccess > 0`) or is enrolled in any course, active or not. It reads no log and no message table, so it is cheap and errs towards asking.
- The spec's "History" and "moves between organisations" edge cases are amended (decision 4).

**Superseded**: the granter choosing a neutral username, the service refusing until one was given, the "new login" notice, and drift counting protected users waiting for one (change 15); the neutral username for everyone at creation; `source = organisation-kept` for someone leaving a protected organisation (change 9); "has activity" as indexed reads of `logstore_standard_log` (`crud` in `c`, `u`) and `messages` (`useridfrom`), a Principle XI exception (change 16).

**Rationale**:
- **(review H6)** A rename relabels the learner's past posts, messages and reviews. Anyone who saw them under the real name can then link the pseudonym to the person, and every future pseudonymous post becomes attributable.
- Lowering reveals the same link in reverse **(review)**.
- Things that keep the old name:
  - stored notification text (`notifications` and `messages`: `subject`, `fullmessage`, `smallmessage`);
  - emails already sent;
  - `files.author` (`repository/lib.php` L3124);
  - Office document metadata;
  - app caches.
- A username is visible to the site team and in `loginas` log entries. With email as everyone's login it is not something the person types, so changing it costs them nothing.

**Verify**:
- V12: an activity check, a raise acknowledgement and a lowering acknowledgement.
- V5: a real-name username is replaced at `firstname`, and the learner still signs in with their email.

## R14. Course leaders' backups, and course logs

**Decision** (Doug, 2026-10-05 (scope review), changes 5 and 6; plan decision 5 split):
- **Backups (kept)**: prohibit `moodle/backup:downloadfile` for `editingteacher` and `teacher`. The site team never leaves a course backup with users in a course's backup area (`moodle/site/README.md`).
- **Logs (site-wide block rejected)**: course staff keep `report/log:view` and `report/loglive:view`. If a protected person asks for their location to be hidden, `editingteacher` and `teacher` get a course-level prohibit in the courses they take, of `report/log:view`, `report/log:viewtoday` and `report/loglive:view`. **Not built** (tasks T027, reopened): to be written with `assign_capability()` (`lib/accesslib.php` L1411, `($capability, $permission, $roleid, $contextid, $overwrite = false, ...)`) in each course context. All three capabilities are `CONTEXT_COURSE` and allowed to `teacher`, `editingteacher` and `manager` by default (`report/log/db/access.php` L31–55, `report/loglive/db/access.php` L31–39). Until it is built, the site team can set the same override by hand on the course's **Permissions** page (`admin/roles/permissions.php?contextid=`).

**Rationale**:
- **(review H2)** A site-team course backup with users contains `username`, `email`, every name field, city, country and every `user_info_data` row (`backup_users_structure_step`, `backup/moodle2/backup_stepslib.php` around L1586–1720). `file_pluginfile()` serves the course backup area with `backup:downloadfile` alone, which editing teachers hold by default (`lib/db/access.php`, checked again 2026-10-05; the `teacher` archetype never held it). Courses come from the repo, so course leaders do not need backups. It costs nobody anything, so it stays.
- **(review M1)** Course logs show IP addresses linked to `/iplookup/` (`report/log/classes/table_log.php` `col_ip()` L322–332), which gives away country and city. That matters only to someone who asks for it to be hidden; course mentors already see their identity. The 2026-10-04 design also missed `report/log:viewtoday`.

**Superseded (2026-10-04)**: prohibiting `report/log:view` and `report/loglive:view` for `editingteacher` and `teacher` site-wide, for every course and every learner.

## R15. Caches and stored text to purge or resync on every change

When a level changes, the service:
- purges the `core/coursecontacts` cache, whose name fields are invalidated only on role or enrolment change **(review M7)**;
- bumps `timemodified`;
- re-saves the user's `mod_scheduler` slots once spec 011 installs it, since `slot::update_calendar()` writes `fullname()` into `{event}.name` **(review M8)**. That calls the scheduler's own `slot` class, a third-party internal, so the plugin README lists it and V17 is re-run on every scheduler re-pin (Principle XI).

The app's cache cannot be purged from the server. Responses are cached for 12 h (18 h on mobile data), and indefinitely while offline (`moodleapp` `authenticated-site.ts`, `user.ts`), so SC-005 is amended for the app (decision 6).

## Known gaps (FR-015)

| Gap | Why it stays | Mitigation |
|---|---|---|
| A protected person's email address is visible to course staff (course leaders, course mentors, organisation managers) | Email stays in staff views for everyone (decision 1 rejected, 2026-10-05) | The address is checked at every grant, and an identifying one is replaced first (R8, FR-016). |
| The organisation shows at every level | `ltct_org` stays visible (decision 2, option a, 2026-10-05) | The person is told when they are protected. If they cannot accept it, the maintainer decides: a neutral organisation entry, or the field private for everyone (R11). |
| Course staff see a protected learner's IP-derived location in course logs | The site-wide block was rejected (2026-10-05) | On request, a block in the courses they take (R14, T027). |
| The badge assertion's email hash can confirm a guessed address | No core setting disables `json/assertion.php` without turning off badges altogether | The address is checked not to identify the person (R8). A relay address would close it, unbuilt. |
| App caches show an old name for up to 12–18 h, or until the device is back online | The server cannot purge the app | Protect before first activity (R13). SC-005 amended for the app (decision 6). |
| Copies already sent: emails, notifications, exports, synced calendar feeds, backpack assertions | They cannot be recalled | Protect before first activity. The granting page and the notification say so. |
| `files.author` and document metadata keep the uploader's name | Frozen at upload, and inside the file | The learner is told when protection is set. |
| `loginas` writes a name snapshot into the log | Core logs `fullname()` at that moment | Only the site team can log in as a user. |
| Course backups with users carry the account fields | Core backs up every user field and `user_info_data` row | Course leaders cannot download backups (R14), automated backups are site-team only, and the site team leaves no backup with users in a course. |
| Core exports seen by entitled people (participants download, grade export, 004 reports) carry no marker | A plugin cannot add a column to a core datasource (R7) | The "People I support" page carries the marker (decision 7). It has no download (change 14). |
| Two protected learners at `firstname` level with the same first name look identical | The surname is withheld | Pseudonyms are unique among protected users (contract). Entitled people see the real name on the profile. A warning on the granting page when another protected person has the same first name is planned (T044, not built). |
| A course leader could make themselves a learner's course mentor (path 4) | `editingteacher` may assign `teacher`, manage groups and set group idnumbers by core's defaults (R7) | Nobody is given `editingteacher` in normal use; the maintainer decides whether to narrow it (T046). |
