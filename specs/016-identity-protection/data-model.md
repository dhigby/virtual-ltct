# Data model: Identity protection

**Plan**: [plan.md](plan.md) · **Research**: [research.md](research.md)

There are two halves:

- **Declared** items are in the repo and applied by `site_config.py`.
- **Moodle data** never enters the repo (constitution III). That covers who is protected, their pseudonym, their real identity, every change record, and **which organisations have a minimum level**. A minimum declared in the public repo would publish which partners are at risk (R12).

## Declared (repo)

### Protection levels (repo: `moodle/site/protection.yaml`, new)

| Field | Rule |
|---|---|
| `rows` | `[26]` |
| `levels` | Exactly `none`, `email`, `firstname`, `pseudonym`, in that order. Each level includes everything the ones before it withhold. |
| `withhold.<level>` | The account fields that level blanks or replaces (research R6). Checked against the plugin's fixed set. `description` and interest tags are never allowed, because the learner's own words are theirs (R6). |
| `org_minimum_max` | `firstname`. An organisation minimum may not be `pseudonym` (R12). |
| `neutral_surname` | `""`, or one non-letter character if quickstart V8 forces the fallback (R4). |
| `reconcile_minutes` | How often `reconcile_protection` runs; default 60. |
| `why` | Required, as in every declaration. |

**Validation**:
- `levels` is the fixed list, in order.
- Each `withhold` list contains the previous level's list.
- `firstname` includes `ltct_role` and the five `ltct_exp_*` fields.
- `pseudonym` adds `firstname`.
- No `@` and no value other than a field name appears anywhere in the file.

### Profile fields (repo: `moodle/site/profile-fields.yaml`, changed)

| Field | Change |
|---|---|
| `ltct_certname` | New. `datatype: text` (`DATATYPES` in `site_config.py` and `profilefields.php` gains `text`), name "Name on certificate", `visible: private`, `locked: 1`. The real full name for the certificate (R10), owned by `local_ltuse` for every user. |
| `ltct_org` | `visible: all` → `private`, **only after** 004's report scope is a cohort condition (decision 2, R11). The validator refuses `private` while any report scopes by `user:profilefield_ltct_org`. |

### Settings (repo: `moodle/site/settings/identity.yaml`, new; `rows: [26]`)

| Setting | Value | Research |
|---|---|---|
| `showuseridentity` | `""` | R8 |
| `grade_export_userprofilefields` | without `email`, `institution`, `department` (exact list confirmed at T001) | R8 |
| `grade_export_customprofilefields` | `""` | R8 |
| `allowedemaildomains` | `""` | R8 |
| `enablegravatar` | `0` | R6 |
| `forceloginforprofileimage` | `1` | R6 |
| the `core_user` global search area | disabled (config name confirmed at T001) | R9 |
| `registerauth` | `""` | R3 |
| `authpreventaccountcreation` | `1` | R3 |
| `auth` | `manual` only, plus core's `nologin` and `webservice` | R3 |
| `auth_manual/field_lock_firstname`, `field_lock_lastname`, `field_lock_email` | `locked` | R3 |
| `protectusernames` | `1` | R9 |

### Roles (repo: `moodle/site/roles.yaml`, changed)

| Role | Change |
|---|---|
| `editingteacher`, `teacher` | `moodle/course:useremail`, `moodle/backup:downloadfile`, `report/log:view` and `report/loglive:view`, each `prohibit` (R8, R14) |
| `manager` | `local/ltuse:viewidentity` and `local/ltuse:manageprotection`, both `allow` (system context) |
| `mentor` (003) | `local/ltuse:viewidentity: allow`. `MENTOR_ALLOW` gains this one capability. |
| `teacher` ("Course mentor") | `local/ltuse:viewidentity: allow` (R7 path 4). Counted only in `ltct:<slug>` courses, never `ltct:officehours`. |

No organisation-manager role is added or changed. Own-organisation managers are recognised by managers-cohort membership (R7). `moodle/reportbuilder:edit` and `editall` stay with `manager` only.

### Certificate template (repo: `moodle/site/certificate/template.yaml`, changed)

- `{type: studentname}` becomes `{type: userfield, field: ltct_certname}`. `CERT_ONE_EACH` counts either kind as the one name element.
- The activity keeps `emailteachers: 0` and `emailothers: ""`, which spec 013's publisher already writes on every publish; nothing new is declared.

### Spec 004 report scope (repo: `moodle/site/reports.yaml`, changed under decision 2)

The `progress` report's scope condition `user:profilefield_ltct_org = {org}` becomes a cohort-membership condition on the organisation's member cohort. The `Organisation` column and the `ltct_org` filter are dropped, because the field becomes private.

## Moodle data (never in the repo)

### `local_ltuse_protection`

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `userid` | int, unique | FK `user.id` |
| `ownlevel` | char | `none`, `email`, `firstname` or `pseudonym` |
| `effectivelevel` | char | The stricter of `ownlevel` and the organisation minimum. This is what is applied. |
| `source` | char | `own`, `organisation`, or `organisation-kept` (kept after leaving a protected organisation; R13) |
| `pseudonym` | char(100) | Required at `pseudonym` |
| `realfirstname`, `reallastname` | char(100) | Correctable on the granting page (R12) |
| `realfields` | text (JSON) | `{field: {value, taken}}`, one entry per withheld field, taken when that field first became withheld (R5). There is no picture: a deleted picture is not restored (R6). |
| `timecreated`, `timemodified`, `usermodified` | int | |

**Validation**:
- One row per user whose `effectivelevel` is above `none`, or whose level is `organisation-kept`.
- The pseudonym is unique among protected users after case-folding and NFC normalisation.
- It must not equal `realfirstname`, and must not contain `reallastname` when the surname has 3 or more characters. Both comparisons are normalised.
- A warning is given when the pseudonym equals another account's `firstname`.
- A held value is never overwritten with an empty one.

### `local_ltuse_org_protection`

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `orgkey` | char, unique | A key declared in `organisations.yaml` |
| `minlevel` | char | `none`, `email` or `firstname` |
| `managers_see_identity` | int (0/1) | Default 1 |
| `timemodified`, `usermodified` | int | `usermodified` is the site-team member who set it |

This table holds no learner data, but it lives only in Moodle, because which partners are at risk must not be published.

### `local_ltuse_protection_log`

| Field | Type | Notes |
|---|---|---|
| `id`, `userid`, `timecreated` | int | |
| `actorid` | int | The person who made the change. For an organisation-minimum change it is the site-team member who set the minimum, never 0. |
| `fromlevel`, `tolevel` | char | |
| `source` | char | `own`, `organisation`, `organisation-kept`, `correction` (same level, but a corrected real value, pseudonym or username), or `orgminimum` (an organisation's minimum changed: `userid` is 0, and the organisation is not named; its own row holds the current setting) |

The reconcile task writes no log rows. It reports a count only (V7).

### The account (Moodle: `user`) while protected

| Field | `email` | `firstname` | `pseudonym` |
|---|---|---|---|
| `maildisplay` | 0 | 0 | 0 |
| `firstname` | real | real | pseudonym |
| `lastname` | real | neutral | neutral |
| alternate names | unchanged | blank | blank |
| R6 fields, `ltct_role`, `ltct_exp_*` | unchanged | blank | blank |
| picture | unchanged | deleted | deleted |
| `description`, interests | unchanged | unchanged (learner told) | unchanged (learner told) |
| username | unchanged | neutral required (R13) | neutral required (R13) |
| `ltct_org` | unchanged | unchanged, private for everyone (R11) | as `firstname` |
| `ltct_certname` (every user) | real full name | real full name | real full name |

### State transitions

```text
none ──grant──▶ email | firstname | pseudonym     (raise: acknowledgement if the user has activity, R13)
any  ──raise──▶ higher                             (acknowledgement if activity)
any  ──lower──▶ lower | none                       (manual only; acknowledgement if activity; picture not restored)
org minimum raised (site team) ─▶ members' effectivelevel recomputed by apply_protection (acknowledgement with an activity count)
member joins a protected org   ─▶ effectivelevel = max(own, org), applied in the cohort_member_added observer (synchronous)
member leaves a protected org  ─▶ effectivelevel kept, source = organisation-kept, until an entitled person lowers it
```

Every applied transition:
1. takes the per-field snapshot of newly withheld fields;
2. rewrites the account with the user in the service's bypass set, under a lock and a transaction;
3. deletes the picture only when entering `firstname` or `pseudonym`;
4. writes a log row;
5. purges `core/coursecontacts`;
6. sends `protectionchanged` to the learner.

## Not modelled

- A relay email address (R8, held for later).
- A per-user auth plugin (R2, held back).
- Organisation-named groups (removed by the open-courses change).
