# Data model: Identity protection

**Plan**: [plan.md](plan.md) · **Research**: [research.md](research.md)

There are two halves:

- **Declared** items are in the repo and applied by `site_config.py`.
- **Moodle data** never enters the repo (constitution III). That covers who is protected, their pseudonym, their real identity, and every change record.

Amended by the scope review (Doug, 2026-10-05 (scope review)): there are no organisation minimums or organisation settings (changes 9, 10), no `ltct_certname` (change 8), no field locks (change 3), and `ltct_org` stays visible (decision 2, option a).

## Declared (repo)

### Protection levels (repo: `moodle/site/protection.yaml`, new)

| Field | Rule |
|---|---|
| `rows` | `[26]` |
| `levels` | Exactly `none`, `email`, `firstname`, `pseudonym`, in that order. Each level includes everything the ones before it withhold. |
| `withhold.<level>` | The account fields that level blanks or replaces (research R6). Checked against the plugin's fixed set. `ltct_org`, `description` and interest tags are never allowed: the first would drop the learner from their cohort and courses (R11), and the others are the learner's own words (R6). |
| ~~`org_minimum_max`~~ | Removed with organisation minimums (Doug, 2026-10-05 (scope review), change 9). |
| `neutral_surname` | One non-letter character, `·`. Mandatory: names are not locked, and core's edit form requires a surname (R4, change 3). |
| `reconcile_minutes` | How often `reconcile_protection` runs: 60, matching `db/tasks.php`. |
| `why` | Required, as in every declaration. |

**Validation**:
- `levels` is the fixed list, in order.
- Each `withhold` list contains the previous level's list.
- `email` withholds `maildisplay`.
- `firstname` includes `lastname`, `picture`, the alternate names, the R6 core fields, `ltct_role` and the five `ltct_exp_*` fields, and not `firstname`.
- `pseudonym` adds `firstname`.
- `neutral_surname` is one non-letter character.
- No `@` appears anywhere in the file.

### Profile fields (repo: `moodle/site/profile-fields.yaml`, unchanged)

| Field | Change |
|---|---|
| ~~`ltct_certname`~~ | Removed (Doug, 2026-10-05 (scope review), change 8). The certificate prints the account's name (R10). |
| `ltct_org` | Unchanged: `visible: all` (decision 2, option a). It is not made private (T036 not shipped). |

### Settings (repo: `moodle/site/settings/identity.yaml`, new; `rows: [26]`)

Only settings that cost nobody anything (change 20). All but `forceloginforprofileimage` are core's defaults, declared so drift catches one being changed.

| Setting | Value | Research | `validate` requires it |
|---|---|---|---|
| `showuseridentity` | `email` (core default; never `username`) | R8 | no |
| `grade_export_userprofilefields` | `firstname,lastname,idnumber,institution,department,email` (core default) | R8 | no |
| `grade_export_customprofilefields` | `""` (core default) | R8 | no |
| `allowedemaildomains` | `""` (core default) | R8 | yes |
| `enablegravatar` | `0` (core default) | R6 | yes |
| `forceloginforprofileimage` | `1` | R6 | yes |

Removed from this file (Doug, 2026-10-05 (scope review)): `showuseridentity = ""` and the trimmed grade-export list (change 1); the `core_user` global search area flag (change 7); `auth_manual/field_lock_firstname`, `field_lock_lastname`, `field_lock_email` (change 3). `auth`, `registerauth`, `authpreventaccountcreation` and `protectusernames` are general account rules in spec 008's `moodle/site/settings/admin.yaml` (change 4).

### Roles (repo: `moodle/site/roles.yaml`, changed)

| Role | Change |
|---|---|
| `editingteacher`, `teacher` | `moodle/backup:downloadfile: prohibit` (R14). ~~`moodle/course:useremail`, `report/log:view` and `report/loglive:view`, each `prohibit`~~ (removed, changes 1 and 5). |
| `manager` | `local/ltuse:viewidentity` and `local/ltuse:manageprotection`, both `allow` (system context) |
| `mentor` (003) | `local/ltuse:viewidentity: allow`. `MENTOR_ALLOW` gains this one capability. |
| `teacher` ("Course mentor") | `local/ltuse:viewidentity: allow` (R7 path 4). Counted only in `ltct:<slug>` courses, never `ltct:officehours`, and only for learners in the mentor's own group `ltct:mentorgroup:<mentor id>` there (change 22). |

No organisation-manager role is added or changed. Own-organisation managers are recognised by managers-cohort membership (R7). `moodle/reportbuilder:edit` and `editall` stay with `manager` only.

### Certificate template (repo: `moodle/site/certificate/template.yaml`, unchanged)

- It keeps `{type: studentname}`, which prints the account's name: the protected display for a protected learner (R10, change 8).
- The activity keeps `emailteachers: 0` and `emailothers: ""`, which spec 013's publisher already writes on every publish; nothing new is declared.

### Spec 004 report scope (repo: `moodle/site/reports.yaml`, changed under decision 2, option a)

The `progress` report's scope condition `user:profilefield_ltct_org = {org}` becomes `cohort:idnumber = ltct:org:{org}`, the organisation's member cohort. Its `Organisation` column is dropped, since every row showed the same value. It had no `ltct_org` filter to drop; the `programme` report keeps its `ltct_org` filter, because the field stays visible. `validate` refuses a `user:profilefield_ltct_org` condition on any report.

## Moodle data (never in the repo)

### `local_ltuse_protection`

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `userid` | int, unique | FK `user.id` |
| `ownlevel` | char | `none`, `email`, `firstname` or `pseudonym`: the level set for the user |
| `effectivelevel` | char | The level applied to the account. The same as `ownlevel`, since no organisation sets a minimum (change 9). |
| ~~`source`~~ | — | Removed (change 9): it said whether the level came from the person, an organisation, or was kept after leaving one. |
| `pseudonym` | char(100) | Required at `pseudonym` |
| `realfirstname`, `reallastname` | char(100) | Correctable on the granting page by the site team (R12) |
| `realfields` | text (JSON) | `{field: {value, taken}}`, one entry per withheld field, taken when that field first became withheld (R5). There is no picture: a deleted picture is not restored (R6). |
| `timecreated`, `timemodified`, `usermodified` | int | |

**Validation**:
- One row per user whose level is above `none`. The row is deleted when the user is lowered to `none`.
- The pseudonym is unique among protected users after case-folding and NFC normalisation.
- It must not equal `realfirstname`, and must not contain `reallastname` when the surname has 3 or more characters. Both comparisons are normalised.
- A held value is never overwritten with an empty one.

### ~~`local_ltuse_org_protection`~~

Removed (Doug, 2026-10-05 (scope review), changes 9 and 10). It held each organisation's minimum level and its `managers_see_identity` setting. The 2026100900 upgrade step drops it where an earlier 016 build made it.

### `local_ltuse_protection_log`

| Field | Type | Notes |
|---|---|---|
| `id`, `userid`, `timecreated` | int | |
| `actorid` | int | The person who made the change; 0 once that person is deleted. |
| `fromlevel`, `tolevel` | char | |
| `source` | char | `own` (a change of level) or `correction` (same level, but a corrected real value, pseudonym, or a username replaced). ~~`organisation`, `organisation-kept`, `orgminimum`~~ removed (change 9). |
| `requested` | int (0/1) | 1 when the person asked for this change; required for a raise (change 13). |
| `emailchecked` | int (0/1) | 1 when the granter confirmed the account's email identifies neither the person nor their organisation; required for a raise (change 2). |

The reconcile task writes no log rows. It reports counts only.

### The account (Moodle: `user`) while protected

| Field | `email` | `firstname` | `pseudonym` |
|---|---|---|---|
| `maildisplay` | 0 | 0 | 0 |
| `email` | checked not to identify the person (FR-016) | as `email` | as `email` |
| `firstname` | real | real | pseudonym |
| `lastname` | real | `·` | `·` |
| alternate names | unchanged | blank | blank |
| R6 fields, `ltct_role`, `ltct_exp_*` | unchanged | blank | blank |
| picture | unchanged | deleted | deleted |
| `description`, interests | unchanged | unchanged (learner told) | unchanged (learner told) |
| username | unchanged | replaced with a neutral one if it holds the real name (R13) | as `firstname` |
| `auth` | `manual`, with no OAuth2 linked login (R3, T042, not built) | as `email` | as `email` |
| `ltct_org` | unchanged, visible | unchanged, visible (decision 2, option a) | as `firstname` |

### State transitions

```text
none ──grant──▶ email | firstname | pseudonym     (requested and emailchecked; a manager only before any activity; R12)
any  ──raise──▶ higher                             (requested and emailchecked; acknowledgement if activity; site team after activity)
any  ──lower──▶ lower | none                       (site team only; never automatic; acknowledgement if activity; picture not restored)
any  ──correct─▶ same level, corrected values      (site team only)
```

~~org minimum raised; member joins a protected org; member leaves a protected org~~ (removed with organisation minimums, change 9).

Every applied transition:
1. takes the per-field snapshot of newly withheld fields;
2. rewrites the account with the user in the service's bypass set, under a lock and a transaction, replacing a username that holds the real name at `firstname` and above;
3. deletes the picture only when entering `firstname` or `pseudonym`;
4. writes a log row;
5. purges `core/coursecontacts` and re-saves the user's scheduler slots;
6. sends `protectionchanged` to the learner.

A repair (the observer or the reconcile task) re-applies the level already set, changes no level and writes no log row.

## Not modelled

- A relay email address (R8, left unbuilt).
- A per-user auth plugin (R2, held back).
- Organisation-named groups (removed by the open-courses change).
- Organisation minimums and an organisation's withholding setting (removed 2026-10-05, changes 9 and 10).
- A per-course block of course logs for a protected person who asks (R14, not built).
