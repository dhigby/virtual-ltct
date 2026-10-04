# Research: Identity protection for at-risk users

**Plan**: [plan.md](plan.md). Every API below was looked up on 2026-10-02. Context7 was queried first (`/websites/moodledev_io_5_2_apis`, and `/moodle/moodle` for `UPGRADING.md`), then each claim was confirmed in upstream source:
- core: `MOODLE_502_STABLE`. In 5.1 and later core lives under `public/`; paths here leave that prefix off.
- the Moodle app: `moodlehq/moodleapp`, branch `main`.
- `mod_customcert`: branch `MOODLE_502_STABLE` (the pin is `v5.2.9`, `2026042014`).
- repo: `main` at `de31ede`, and the `003-mentor-role` branch at `0ae5942` (cited as `003:`).

Eight research tracks ran in parallel (names, email, profile fields, search, the app and authentication, outbound artefacts, repo dependencies, existing plugins). An adversarial reviewer was then asked to break the resulting design; its findings are folded in below and marked **(review H1)**, **(review M3)** and so on. "Inferred" marks a behaviour read from source but not run. A second review, with three lenses (spec coverage, constitution, technical soundness), returned about 50 findings on the first draft, many overlapping; this version fixes them.  Each **Verify** line must be confirmed on the 5.2.3+ instance, with test accounts only, before the task that depends on it is closed (constitution X). Quickstart numbers the checks.

## R1. Protect names by rewriting the account, not by filtering views

**Decision**: For a protected user, our plugin writes the protected display into the account's own `firstname` and `lastname`:
- *first name only*: `firstname` = real first name, `lastname` = the neutral surname (R4);
- *pseudonym*: `firstname` = the pseudonym, `lastname` = the neutral surname.

It also blanks the four alternate-name fields (`firstnamephonetic`, `lastnamephonetic`, `middlename`, `alternatename`). The real names move to our own table (R5). Every view that shows a name then shows the protected display, because core renders names live from the user record.

**Rationale**:
- `core_user::get_fullname()` (`lib/classes/user.php`) reads the name fields from the user object it is given and calls no hook or plugin callback. `fullname()` (`lib/moodlelib.php`) only wraps it. No 5.2 hook overrides a display name: `core_user\hook\` holds `after_login_completed`, `before_user_deleted`, `before_user_updated`, `extend_bulk_user_actions`, `extend_default_homepage` and `extend_user_menu`, and `lib/classes/hook/output/*` changes page HTML only.
- So filtering every view is not possible without editing core (constitution XI), and it would still leak initials and picture alt text, which core builds from the record (`core_user::get_initials()`; the app's `user-avatar.ts`).
- Read live from the record, and therefore protected at once by the rewrite: forum posts and their emails (`FromName` and body), messaging, participants, gradebook, report builder name columns, badge pages (`badges/classes/output/issued_badge.php`), the customcert verify page, user search and selectors, and the web services the app uses (`user_get_user_details()` in `user/lib.php`).
- No free, maintained 5.0–5.2 plugin offers pseudonyms, display-name overrides or per-user identity hiding. `local_anonymise` exists, but it destroys data and is not this.

**Alternatives considered**:
- `alternativefullnameformat` with the real name in `alternatename`, shown to `moodle/site:viewfullnames` holders. Rejected. The format is site-wide. `viewfullnames` is a module-level capability checked in course and module contexts, so every teacher in a shared course would get it and a user-context mentor would not. Many views never pass the override. And global search indexes all six name fields in the document title (`user/classes/search/user.php`), so anyone could find the real name.
- Filtering through output hooks or a theme. Rejected for the reasons above.
- Two accounts per person. Rejected: it splits completion, grades, badges and certificates.

**Verify** (blocks US1, SC-001): rename a test learner to each level, then as a classmate check every view in quickstart V2 on the web and in the app.

## R2. Enforce the protected record against every writer

**Decision**: Three layers, none trusting the others:
1. A `core_user\hook\before_user_updated` callback in `local_ltuse`. It re-applies the protected values (names, alternate names, `maildisplay`, and the fields R6 withholds) on every `user_update_user()` call, unless the service is writing that same user (see "Bypass and concurrency" below).
2. Observers on these events, each returning early for a user the service is already writing:
   - `\core\event\user_created`
   - `user_updated`
   - `cohort_member_added` and `cohort_member_removed` for organisation **member** cohorts. A member cohort is resolved from the stored organisation keys, never by an `ltct:org:*` pattern, which would also match `ltct:org:<key>:managers`.
3. A scheduled task, `reconcile_protection`, every `reconcile_minutes` (60). It recomputes the effective level for:
   - every user with a protection row;
   - **every member of every organisation whose minimum is above `none`**;
   - every user's `ltct_certname` (R10).

   It re-applies each record and deletes any picture where the level requires it.

**Rationale**:
- `before_user_updated` is dispatched at the top of `user_update_user()` (`user/lib.php`, around L156–171) with the `$user` object. That covers `user/edit.php`, `editadvanced.php`, `core_user_update_users` and the auth sync path (`authlib.php` `update_user_record()`).
- **(review M2, M3)** It does not cover every writer:
  - `profile_save_data()` writes custom fields directly.
  - `core_user::update_picture()`, and the app's `core_user_update_picture`, fire no hook. The app path fires no event.
  - Account creation has no "before create" hook.
  - `tool_uploaduser` saves profile data only after `user_created` has fired (`process.php` L1059).
- **Membership changes can be silent.** `tool_dynamic_cohorts` fires cohort events only when bulk processing is off, and it deletes all members of a disabled or deleted rule with no events (`cohort_manager.php` L88). Our organisation rules set `bulkprocessing = 0` (`classes/siteconfig/cohortrules.php`); the validator keeps that. The reconcile task's organisation sweep is what catches the silent deletion.
- The login-time write in `lib/moodlelib.php` (around L2995–3020) touches access times and IP only, so login never reverts a name.

**Bypass and concurrency**:
- The service holds a private static **set of user IDs** it is writing. It adds an ID immediately before one `user_update_user($user, false, true)` call and removes it in a `finally` block. `triggerevent = true`, so other plugins still see the change.
- The hook and the observers skip only a user ID in that set. Nothing else can reach the set, and an exception cannot leave it open.
- Each application runs under a per-user `\core\lock\lock_config` lock and a delegated transaction, so the page and the reconcile task never interleave on one user.
- A cohort event for a user already in progress is deferred to the adhoc task `apply_protection`, never nested.
- A held real value is never overwritten with an empty one.

**Upgrade risk (Principle XI, listed in the plugin README)**: the hook is a notification, and our change persists because PHP passes the object by handle. A PHPUnit test fails if a mutation made in the callback stops being stored by `user_update_user()`, and V7 is re-run on every core upgrade.

**Alternatives considered**:
- A dedicated auth plugin for protected accounts, with locked fields and `can_edit_profile() = false`. Held back, and used only if V7 shows leaks.
- Per-user capability overrides. Rejected: `moodle/user:editownprofile` is checked at system context.

**Verify** (blocks US1, US3): V7. Edit a protected account through `user/edit.php`, `editadvanced.php`, `core_user_update_users` and both uploaduser override modes, and upload a picture from the app. The record is restored immediately on the hook paths, and by the next reconcile run on the picture and profile-data paths. Then disable a test organisation's cohort rule (members removed with no event), and the next reconcile run recomputes those members.

## R3. Lock name and email fields for manual accounts

**Decision**: Declare these settings: `auth_manual/field_lock_firstname`, `field_lock_lastname` and `field_lock_email` = `locked`; `registerauth = ''`; `authpreventaccountcreation = 1`; and `auth` = `manual` only. Every learner's name then becomes something the site team changes in core's admin user editor. For a protected user, an entitled person changes the real name on the granting page (R12). Learners no longer change it themselves (decision 3). This is recurring work for the site team, named in the plan (Principle X).

**Rationale**:
- `user/edit_form.php` (L150–181) hard-freezes locked fields, so a protected learner never sees their name fields editable. Locked (not `unlockedifempty`) is required, because the latter leaves an empty field editable.
- Accounts on this site are created by the site team (spec 002), so self-registration has never been the route. Declaring it off means drift catches anyone turning it on.
- **(app track)** If OAuth2 (Google, Microsoft) were ever enabled, its default mappings overwrite `firstname`, `lastname` and `email` on every login, and Microsoft's `displayName` overwrites `alternatename`. That would wipe a pseudonym. Declaring `auth` means enabling OAuth2 shows up as drift.
- Without the locks, the R2 hook still restores the record. But `lastname` is a required field in `useredit_shared_definition()` (`useredit_get_required_name_fields()`), so a learner whose surname is blank could not save their profile at all.

**Alternatives considered**: lock fields only for protected accounts, which core can't do per user (field locks are per auth plugin); or rely on the hook alone, which leaves the form confusing.

**Verify** (blocks R4): V8, and that the site team can still edit a locked name in `editadvanced.php`. With the locks set, a protected learner opens their profile, the name fields are frozen, and the form saves other changes. If a frozen `required` empty `lastname` still blocks saving, R4 falls back to its placeholder.

## R4. The neutral surname

**Decision**: The protected `lastname` is an empty string. If V8 shows that the edit form refuses to save with an empty frozen surname, it is the declared placeholder `protection.yaml: neutral_surname`, one character, proposed `·`.

**Rationale**:
- `core_user::validate()` checks `lastname` as `PARAM_NOTAGS`, `NULL_NOT_ALLOWED`. `\core\param::validate_param` rejects only null, so `''` is valid through the API and `user_update_user()` stores it.
- Template-mode `fullnamedisplay` drops empty fields, so the name renders as the first name alone. Initials become one letter on the web and in the app.
- A placeholder is the fallback because it shows in every name (`Ana ·`).

**Verify**: V8, and that the 003 Mentoring page, which sorts by `lastname`, still sorts sensibly.

## R5. Real identity lives in our own table; one PRIVATE field feeds the certificate

**Decision**: `local_ltuse_protection` holds one row per protected user:
- the user's own level, the effective level, and where the effective level comes from;
- the pseudonym;
- the real first name and surname;
- a **per-field** snapshot of every value R6 withholds, recording when each field was taken.

`local_ltuse_protection_log` records every change (FR-008). Both tables have a privacy provider, so the learner's data export includes them (FR-013) and deletion purges them.

One custom profile field, `ltct_certname` (text, `visible: private`, `locked: 1`, label "Name on certificate"), holds the real full name for **every** learner. The service owns it (R10).

The real name and the snapshot can be **corrected while the user is protected**, on the granting page or through the web service (R12), so a legal name change still reaches the certificate.

**Rationale**:
- A custom profile field is visible per field, never per user (`profile_field_base::is_visible()`, `user/profile/lib.php` L450–489). PRIVATE is visible to the user and to holders of `moodle/user:viewalldetails` in the user context; NONE only at system context.
- Mentors and organisation managers do not hold `viewalldetails`, and must not:
  - 003's `MENTOR_ALLOW` (`003:scripts/site_config.py` L269–276, 003 research R2) refuses it;
  - `ORGMANAGER_DENY` (`scripts/site_config.py` L275) refuses it;
  - it would also expose `ltct_role`, the username and the last IP.
- A table we own is shown exactly to the people R7 names.
- The certificate is the one exception: `mod_customcert`'s `userfield` element can print only a profile field.
- **Snapshot per field (review)**: a row-level "already held" check would skip a field the previous level did not withhold. Raising from `email` to `firstname` would then miss the current city or country, and a later restore would write stale values. Each field is captured the moment it first becomes withheld.
- **Backups**: our tables are not in course backups. `ltct_certname` is a `user_info_data` row, so a course backup with users carries it. Course leaders cannot download backups (R14), and automated backups are site-team only. Listed in Known gaps.

**Alternatives considered**:
- PRIVATE profile fields for every real value, with `viewalldetails` granted in user context. Rejected: it reverses a recorded 003 decision and over-exposes.
- The core alternate-name fields. Rejected (R1).

## R6. What each level withholds, and how

| Level | On the account | Settings it relies on |
|---|---|---|
| Email hidden | `maildisplay = 0` (hidden) | R8 |
| First name only | Email hidden, plus: `lastname` neutral; alternate names blank; blank `country`, `city`, `url`, `institution`, `department`, `phone1`, `phone2`, `address`, `idnumber`, `ltct_role` and the five `ltct_exp_*`; picture deleted | R8, R9, R11, `forceloginforprofileimage = 1`, `enablegravatar = 0` |
| Pseudonym | As first name only, with `firstname` = pseudonym | As above |

The service takes the per-field snapshot (R5) before blanking anything, and restores the fields on a downgrade, **except the picture**:
- `core_user::update_picture()` with `deletepicture = 1` calls `delete_area_files` (`lib/classes/user.php` L657–660), so the file is gone.
- The picture is deleted only when the effective level becomes `firstname` or `pseudonym`, never at `email`.
- The learner is told that a picture removed by protection must be uploaded again after a downgrade.

**Rationale**:
- `hiddenuserfields` is site-wide, so per-user hiding means not holding the value on the account (profile track).
- Pictures:
  - There is no per-user picture lock. `pluginfile.php` serves the stored file, so `picture = 0` alone is not enough.
  - `forceloginforprofileimage = 1` stops the public route.
  - `enablegravatar = 0` matters, because Gravatar would publish an unsalted MD5 of the real email as the default picture.
- **(review L3)** The fields beyond the spec's list identify a person as much as a surname does.
- **`ltct_role` (review)**: its values ("Translator" and others) connect a person to Bible translation, which is exactly the risk the spec describes. It is `visible: teachers`, so every course leader sees it.

**What the site does not rewrite**: the learner's own words stay on the account, in line with the spec ("Things the learner writes", Assumptions): their profile `description`, interest tags, posts and submissions. The granting page and the `protectionchanged` notification tell the learner to review them, together with file author metadata and copies already sent (R13).

**Organisation**: see R11. `ltct_org` is never blanked on the account.

**Verify** (blocks US1): V2 and V9. A classmate and a course leader see none of the withheld fields and no picture, logged in or out, and no Gravatar. At `email` level the picture stays.

## R7. Who sees the real identity, and where

**Decision**: There is one function, `\local_ltuse\protection\entitlement::can_view_identity($viewer, $user)`. It is true for:
1. **the site team**: holders of `local/ltuse:viewidentity` at system context (the `manager` archetype);
2. **an assigned mentor**: holders of `local/ltuse:viewidentity` in the learner's user context, which the 003 `mentor` role gains (`MENTOR_ALLOW` gets this one entry, a reviewed change). The grant ends with the assignment;
3. **a manager of the learner's own organisation**: a member of `ltct:org:<key>:managers`, where `<key>` is the learner's `ltct_org`, **unless** that organisation's stored setting `managers_see_identity` is false (R12).

`can_manage_protection($viewer, $user)` is the same, with `local/ltuse:manageprotection` and without the mentor path.

Every surface calls these functions, never `has_capability` alone.

**Organisation managers** are recognised by managers-cohort membership, the structure 002 already builds and `local_ltuse_managed_organisation_keys()` (`lib.php` L76–112) already reads. So this needs no new role and no change to 002's `orgmanager` or `ORGMANAGER_DENY`. Membership of a managers cohort is set only by the site team (002 FR-012), and leaving it ends the entitlement at once. If the open-courses change later introduces a user-context manager role, it can call the same function.

**Surfaces** where the real identity appears, with the **Protected** marker, for an entitled viewer only:
1. the profile-page node (`local_ltuse_myprofile_navigation()`), on the web;
2. the 003 Mentoring page **and its app handler** (`db/mobile.php`), the surface that works in the app;
3. a **"People I support"** page in `local_ltuse`, listing the protected people the viewer is entitled to, with a CSV download that carries the marker on each row;
4. the granting page (R12).

Everywhere else, an entitled viewer sees the protected display. A **non-entitled viewer never sees the marker**, because the marker itself says "this person is at risk". US2-1 and FR-007 are amended (decision 7).

**Rationale**:
- Core cannot show a mentor or manager the real name in a forum or gradebook without showing it to everyone, because it renders from the record (R1, review H5).
- **Reports**: a plugin cannot add an entity to a core report-builder datasource. Entities are added only in the datasource's own `initialise()`, and `reportbuilder/classes/` has no hook directory in 5.2. Spec 004's reports use `core_course\reportbuilder\datasource\participants`, so the real identity and the marker cannot be put on them. Our own page (surface 3) replaces that. Rebuilding the participants datasource inside `local_ltuse` was rejected as too much core logic to copy and maintain.
- The profile node is web-only. In the app, the Mentoring handler is the entitled surface.

**Alternatives considered**: `viewalldetails` (rejected in R5); a site-wide alternative name format (rejected in R1); two organisation-manager roles chosen per organisation (rejected, since a role per organisation setting goes against VII).

**Verify** (blocks US2): V4. As the assigned mentor, A's manager, B's manager, a classmate and the site team: the four surfaces show the real identity to the mentor, A's manager and the site team only, including the Mentoring page in the app. After the mentor assignment ends, or A's manager leaves the managers cohort, the real identity is gone. With A's `managers_see_identity` false, A's manager sees only the display, including on the granting page. The CSV from surface 3 carries the marker.

## R8. Email: hidden by account, and out of course leaders' views for everyone

**Decision**:
- The *email hidden* level sets `maildisplay = 0`.
- Separately, and for every user (decision 1), the site declares:
  - `showuseridentity = ''`;
  - `grade_export_userprofilefields` without email, institution and department;
  - `grade_export_customprofilefields = ''`;
  - `allowedemaildomains = ''`;
  - `enablegravatar = 0`.
- It prohibits `moodle/course:useremail` for `editingteacher` and `teacher`.
- It restricts `moodle/reportbuilder:edit` to the site team.

**Rationale**:
- `maildisplay` stops classmates (profile, web services, email search).
- **(review H1)** `maildisplay` is ignored by:
  - identity fields (`user/classes/fields.php` `get_identity_fields()` L363–410), shown to `moodle/site:viewuseridentity` holders, which the `teacher` and `editingteacher` archetypes have, across participants, the bulk download, selectors and activity reports;
  - `moodle/course:useremail` (`lib/myprofilelib.php` L118–165, `user_get_user_details()` L374–386, which the app uses);
  - the gradebook export (`grade/lib.php` `grade_helper::get_user_profile_fields()`);
  - the report-builder email column.
- Course leaders are not entitled under FR-006, so either email leaves those views or a protected learner's address is exposed.
- Learners and course leaders can still reach each other through Moodle messaging, which hides no one (FR-003).
- Outbound mail is already safe:
  - `email_to_user()` uses the sender's real address only if `can_send_from_real_email_address()` passes, which needs a non-empty `allowedemaildomains`. Otherwise `From` is `noreply` and `FromName` is `fullname()` "(via site)".
  - The forum `Reply-To` is a recipient-keyed inbound address (`mod_forum\task\send_user_notifications`).
  - `emailonlyfromnoreplyaddress` no longer exists in 5.2.

**Alternatives considered**: a relay address in `user.email` for protected users, with the real one in our table. This also closes the badge-assertion hash (R10), but needs a mail relay that someone has to operate. Held for later. A known gap instead (FR-015) was rejected, since course leaders are many.

**Verify** (blocks US1, SC-001): V3. As teacher, editingteacher and organisation manager, no protected learner's email appears on participants, CSV download, grade export, quiz and completion downloads, or reports. The raw headers of a forum post email and a message email from the protected learner carry no real address or name.

## R9. Search: real names cannot be found

**Decision**: With R1, R5 and R6 in place, search needs only three more things:
- **Disable the `core_user` global search area** (not the whole engine), so spec 014 can still use global search for the library (row #24). The exact config name for one area's enabled flag is confirmed at T001 from `core_search\manager` on `MOODLE_502_STABLE`, and declared in `settings/identity.yaml`.
- Keep the alternate-name fields blank (R2 enforces this).
- Give protected accounts a username that does not come from the real name (R13).

**Rationale**:
- Core search matches only `firstname`, `lastname`, the four alternate-name columns, and the identity fields the viewer may see. That covers:
  - `users_search_sql` and the user selectors;
  - `core_message\api::message_search_users`;
  - participants keywords (`participants_search.php` L949–1120);
  - report-builder filters;
  - `core_user_get_users`.
- A real name held only in our table, or in the PRIVATE `ltct_certname` (not an identity field), matches for nobody but the site team.
- The `core_user` search area indexes all six name fields, and an old document stays live until the next index run **(review M5)**.
- At `email` level the name is not withheld, so real-name search applies only at `firstname` and above. The email and username are withheld at every level: R8 takes email out of identity fields, and usernames are not shown to peers.

**Verify** (blocks SC-003): V5, at all three levels.

## R10. Badges and certificates

**Decision**:
- **Badges** need nothing: the public `badges/badge.php?hash=` page shows the live `fullname()`.
- **Certificates**: spec 013's template prints the name through `userfield` bound to `ltct_certname`, in place of `studentname`. `CERT_ELEMENTS` gains `userfield`, and `CERT_ONE_EACH` accepts exactly one name element of either kind. The certificate activity is declared with `emailteachers = 0` and `emailothers = ''`.
- **`ltct_certname` for every learner**: the service owns this field for all users, protected or not:
  - an upgrade step in `db/upgrade.php` back-fills it from `firstname` and `lastname` for every existing user;
  - the adhoc task `apply_protection`, queued on `user_created`, sets it once profile data is saved;
  - the `user_updated` observer keeps it in step for users with no protection row;
  - for protected users it is set from `realfirstname` and `reallastname`;
  - `reconcile_protection` repairs any empty or stale value, for every user.

**Rationale**:
- `studentname` prints `fullname($user)`, which would be the pseudonym on the learner's own PDF (FR-010).
- How `userfield` decides (`element/userfield/classes/element.php` L143–209): it prints a profile field only when `is_visible()` passes, checked in the **template's context**. So for the PRIVATE `ltct_certname`, the printed value depends on who renders the PDF:
  - the learner sees their real name;
  - a downloader with `moodle/user:viewalldetails` in the module or system context, which is the site team, sees it;
  - anyone else sees the label "Name on certificate";
  - a certificate emailed to the learner by cron is rendered as the cron user, so it shows the real name. That is correct, because it goes to the learner.
- The verify page prints only `fullname()`, which is the protected display.
- **(review M4)** `emailteachers` and `emailothers` attach the PDF as generated for the learner, so they would send the real name to teachers and other addresses.
- **(review)** Without the back-fill and the observers, every unprotected learner's certificate would print the label instead of a name.
- **Known gap**: the badge assertion JSON (`badges/json/assertion.php`) embeds `sha256(email + salt)`, and the salt is published. Someone who already knows the email can confirm it (decision 6). Only a relay address would close this (R8).

**Verify** (blocks US4, and blocks 013's certificate from changing):
- V6 for both a protected learner (A1) and an unprotected one (A2), including an account created after the upgrade, and an unprotected learner whose name the site team corrects.
- A site-team download shows the real name.
- The cron email to the learner shows the real name.
- A course leader's download shows the label.
- The verify page and the badge page show the protected display.
- The PDF filename has no real name.

## R11. The organisation is hidden by making `ltct_org` private, after 004's scope moves

**Decision** (decision 2, a **precondition** for the `firstname` and `pseudonym` levels):
1. Move spec 004's per-organisation report scope from the `user:profilefield_ltct_org` condition to a **cohort-membership** condition on the organisation's member cohort.
2. Then set `ltct_org` to `visible: private` for everyone.

Until both are applied, the service **refuses** `firstname` and `pseudonym`, and so do organisation minimums at those levels. It allows `email`. The validator refuses a `user:profilefield_ltct_org` scope condition once step 1 lands.

**Rationale**:
- `ltct_org` cannot be blanked on the account. **(review H4)** Blanking it would:
  - drop the user from their dynamic cohort;
  - suspend them in every cohort-synced course (`enrol_cohort/unenrolaction = 3`), which breaks FR-003;
  - remove their organisation manager's entitlement.
- `ltct_org` cannot be made private while 004 scopes by it either. Report builder makes a profile-field condition available only if `is_visible(system)` (`reportbuilder/classes/local/helpers/user_profile_fields.php` L214). `datasource::get_active_conditions()` **silently skips** an unavailable condition (`reportbuilder/classes/datasource.php` L288–315). Every manager would then see every organisation.
- A cohort condition is fail-closed by construction: a missing cohort matches nobody.
- Once private, classmates no longer see anyone's organisation. In open courses they do not need it to take part.
- This covers both members of a protected organisation and individually protected people **(review)**. Because it is a precondition, the levels that promise to hide the organisation (FR-001) never ship without it.
- **(review H3)** Organisation-named course groups would also show the organisation. The open-courses change (011 handoff B1, no groups) removes them, so it lands first.

**Alternatives considered**:
- Our own profile-field datatype whose `is_visible()` decides per user. Rejected: `@internal` in core (in tension with XI), and a datatype change is a blocking `wrong-datatype` in our applier (`classes/siteconfig/profilefields.php` L33–34).
- Shipping `firstname` and `pseudonym` with the organisation visible. Rejected, because it contradicts FR-001.

**Verify** (blocks decision 2 and both levels):
- V10. The cohort-condition report shows exactly A's learners to A's manager, and nothing when the cohort is missing.
- With `ltct_org` private, a classmate does not see A1's organisation on the profile, the participants page or in the app.

## R12. Granting protection, for a person and for an organisation

**Decision**:

**For a person**:
- A page in `local_ltuse`, `/local/ltuse/protection.php?id=<userid>`, reached from the profile node (R7), lets an entitled person:
  - set, change or remove the user's own level;
  - set the pseudonym;
  - **correct the real name and the snapshot values** while the user is protected.
- It needs `can_manage_protection()` (R7): the site team, or a manager of the user's own organisation.
- The same logic is the web service `local_ltuse_set_protection`, used by the site team's scripts and spec 008's bulk tooling.

**For an organisation**:
- An organisation's minimum level and its `managers_see_identity` are **Moodle data**, stored by `local_ltuse` and set by the site team only, on `/local/ltuse/orgprotection.php` or through `local_ltuse_set_org_protection`.
- **They are not declared in the repo.** **(review, Principle III)** A `protection:` key in the public `organisations.yaml` would publish which partners are at risk, and git history would keep it even after removal.
- An organisation that needs protection is added to `organisations.yaml` with a **neutral key and name from its first commit**. The key cannot be changed later: it is the `ltct_org` option and the cohort and category idnumber.
- **Exception (2026-10-04):** SIL's ten Area entries (`sil-*`, `silp-*`) are deliberately not neutral. They name SIL's own published structure and mark no one as at risk, and no Area entry carries a minimum. A group within an Area that needs organisation-wide protection gets its own neutral entry (spec Clarifications 2026-10-04).
- FR-014 is amended (decision 8).
- An organisation minimum may be `email` or `firstname`, **never `pseudonym`**, because a pseudonym is chosen per person.
- Raising an organisation's minimum shows the count of members who already have activity (counts only), and needs an acknowledgement before it is applied (R13).

**The learner**: their profile node shows their level, a preview of what others see (FR-012), and how to ask for protection.

**Rationale**:
- FR-008 says the learner's manager or the site team decides, and the learner can always ask.
- Core has nothing to reuse.
- Entitlement through managers-cohort membership limits a manager to their own people (R7).

**Verify**:
- V1, V11 and V16.
- An unentitled person (a classmate, a course leader, another organisation's manager) is refused on both pages and both web services.

## R13. Protection is set before the learner's first activity, and is never lowered automatically

**Decision**:
- **Protect early.** Protection is meant to be set when the account is created, or when the learner joins a protected organisation, before any enrolment. Then nothing has ever been shown under the real identity.
- **Neutral usernames.** The account's username is chosen neutral at creation. When a level is raised to `firstname` or `pseudonym`, the service compares the username with the real first name and surname, after case-folding and Unicode NFC normalisation. If either name appears in it, the service refuses until the username is changed. The granting page offers a generated neutral username, applies it through `user_update_user()`, and tells the learner their new login.
- **Late raises need an acknowledgement.** A raise for a user who already has activity, whether for one person or for an organisation, needs an explicit acknowledgement on the page, or `acknowledgehistory = true` through the web service. The page recommends a fresh account instead.
- **No automatic lowering.** A downgrade is never automatic.
  - When a member leaves a protected organisation, their effective level is kept as their own level (`source = organisation-kept`) until an entitled person lowers it.
  - Every lowering for a user with activity needs the same acknowledgement, because it reveals the link in reverse.
- **"Has activity"** means either of these, read by indexed columns, and listed in the plugin README:
  - any `logstore_standard_log` row for the user with `crud` in (`c`, `u`), other than login and profile-view events;
  - any row in `messages` with `useridfrom` = the user.
- The spec's "History" and "moves between organisations" edge cases are amended (decision 4).

**Rationale**:
- **(review H6)** A rename relabels the learner's past posts, messages and reviews. Anyone who saw them under the real name can then link the pseudonym to the person, and every future pseudonymous post becomes attributable.
- Lowering reveals the same link in reverse **(review)**. An automatic downgrade on leaving an organisation would do exactly what the spec's edge case forbids.
- Things that keep the old name:
  - stored notification text (`notifications` and `messages`: `subject`, `fullmessage`, `smallmessage`);
  - emails already sent;
  - `files.author` (`repository/lib.php` L3124);
  - Office document metadata;
  - app caches.
- A username is visible to the site team and in `loginas` log entries, and it is the learner's login.

**Verify**:
- V12: an activity check, a raise acknowledgement and a lowering acknowledgement.
- V11: leaving a protected organisation keeps the level.
- V5: refusal for a real-name username.

## R14. Course leaders' backups and logs

**Decision**: Prohibit `moodle/backup:downloadfile` for `editingteacher` and `teacher`. Prohibit `report/log:view` and `report/loglive:view` for both as well (decision 5).

**Rationale**:
- **(review H2)** A site-team course backup with users contains `username`, `email`, every name field, city, country and every `user_info_data` row (`backup_users_structure_step`, `backup/moodle2/backup_stepslib.php` around L1586–1720). `file_pluginfile()` serves the course backup area with `backup:downloadfile` alone, which editing teachers hold by default.
- Courses come from the repo, so course leaders do not need backups.
- **(review M1)** Course logs show IP addresses linked to `/iplookup/` (`report/log/classes/table_log.php` `col_ip()` L322–332), which gives away country and city.

## R15. Caches and stored text to purge or resync on every change

When a level changes, the service:
- purges the `core/coursecontacts` cache, whose name fields are invalidated only on role or enrolment change **(review M7)**;
- bumps `timemodified`;
- re-saves the user's `mod_scheduler` slots once spec 011 installs it, since `slot::update_calendar()` writes `fullname()` into `{event}.name` **(review M8)**. That calls the scheduler's own `slot` class, a third-party internal, so the plugin README lists it and V17 is re-run on every scheduler re-pin (Principle XI).

The app's cache cannot be purged from the server. Responses are cached for 12 h (18 h on mobile data), and indefinitely while offline (`moodleapp` `authenticated-site.ts`, `user.ts`), so SC-005 is amended for the app (decision 6).

## Known gaps (FR-015)

| Gap | Why it stays | Mitigation |
|---|---|---|
| The badge assertion's email hash can confirm a guessed address | No core setting disables `json/assertion.php` without turning off badges altogether | A relay address (R8, later). Protected learners use an address that does not name them. |
| App caches show an old name for up to 12–18 h, or until the device is back online | The server cannot purge the app | Protect before first activity (R13). SC-005 amended for the app (decision 6). |
| Copies already sent: emails, notifications, exports, synced calendar feeds, backpack assertions | They cannot be recalled | Protect before first activity. The granting page and the notification say so. |
| `files.author` and document metadata keep the uploader's name | Frozen at upload, and inside the file | The learner is told when protection is set. |
| `loginas` writes a name snapshot into the log | Core logs `fullname()` at that moment | Only the site team can log in as a user, and logs are site-team only (R14). |
| Course backups with users carry `ltct_certname` and the account fields | Core backs up every `user_info_data` row | Course leaders cannot download backups (R14), and automated backups are site-team only. |
| Core exports seen by entitled people (participants download, grade export, 004 reports) carry no marker | A plugin cannot add a column to a core datasource (R7) | The "People I support" page and its CSV carry the marker (decision 7). |
| Two protected learners at `firstname` level with the same first name look identical | The surname is withheld | Pseudonyms are unique among protected users (contract). Course leaders are told to check the profile node, and entitled people see the real name there. |
| Learners can no longer change their own name or email | Name fields are locked for every manual account (R3, decision 3) | The site team changes them. For protected users, the granting page does. |
