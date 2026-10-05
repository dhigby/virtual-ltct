# Contract: declaration changes (spec 016)

This extends spec 001's [declaration contract](../../001-site-config-as-code/contracts/declaration.md), as specs 002, 004 and 013 did. 001's contract gets one new paragraph, "Spec 016 extends it again", linking here.

**What is not declared, on purpose**: who is protected. That is Moodle data, set by people on the site (data model). `organisations.yaml` does not change. An organisation that asks not to be named publicly is added with a neutral key and name from its first commit (R12; Doug, 2026-10-05 (scope review), change 23). There are no organisation minimums to declare or to keep out (change 9).

## Files

| File | Status | Read by | Payload key |
|---|---|---|---|
| `moodle/site/protection.yaml` | new | `site_config.py` `_validate_protection()` | `protection` |
| `moodle/site/settings/identity.yaml` | new | the existing settings loader | `settings` (merged) |
| `moodle/site/roles.yaml` | changed (see [data-model.md](../data-model.md)) | `_validate_roles()`, `_check_protection_site()` | `roles` |
| `moodle/site/reports.yaml` | changed under decision 2, option a: cohort scope condition | `_validate_reports()` | `reports` |

~~`moodle/site/profile-fields.yaml` (`ltct_certname`; `ltct_org` private) and `moodle/site/certificate/template.yaml` (`userfield` element)~~ are unchanged from main (Doug, 2026-10-05 (scope review), changes 8 and 11).

`protection.yaml` is added to `TOP_FILES` (`scripts/site_config.py`).

## Payload (PHP side)

```text
protection: {
  levels: ["none", "email", "firstname", "pseudonym"],
  withhold: { email: [...], firstname: [...], pseudonym: [...] },
  neutral_surname: "·",
  reconcile_minutes: 60
}
```

~~`org_minimum_max`~~ and the computed ~~`orgscope_ready`~~ are removed (changes 9 and 11).

## Applier

`applier::run()` (`classes/siteconfig/applier.php`) gains one step, **protection**, after **structure** and before **reporting**. It stores `protection` in `local_ltuse` config. It never reads or writes any user's protection.

`drift` reports differences in the stored config. In addition, it reports **a count only**: users whose account differs from their protected state (a reconcile backlog). ~~Protected users waiting for a neutral username~~ is no longer counted: the service gives a neutral username automatically (change 15).

## Validation

**`protection.yaml`**
- The rules in [data-model.md](../data-model.md).

**`roles.yaml`**
- `local/ltuse:viewidentity` is allowed only on `manager`, `mentor` and `teacher` (R7), and `manager`, `teacher` and (when declared) `mentor` must allow it.
- `local/ltuse:manageprotection` is allowed only on `manager`.
- `moodle/reportbuilder:edit` and `editall` are allowed only on `manager`.
- `editingteacher` and `teacher` must prohibit `moodle/backup:downloadfile` (R14). ~~`moodle/course:useremail`, `report/log:view` and `report/loglive:view`~~ are no longer required (changes 1 and 5).

**Settings** (change 20)
- Once `protection.yaml` is declared, `enablegravatar` must be `0`, `forceloginforprofileimage` `1` and `allowedemaildomains` `""`. The other `identity.yaml` settings are declared for drift only. ~~`showuseridentity`, the grade export fields, the search area flag, the field locks, `auth`, `registerauth`, `authpreventaccountcreation` and `protectusernames`~~ are no longer required here, and no login method is refused (changes 1, 3, 4, 7).

**`reports.yaml`** (decision 2, option a)
- No condition on `user:profilefield_ltct_org`, on any report. A per-organisation report's scope is `cohort:idnumber` equal to `ltct:org:{org}`.

~~**`profile-fields.yaml`**: the `text` datatype; `ltct_certname` private and locked; `ltct_org` private only without an `ltct_org` report condition.~~ ~~**`certificate/template.yaml`**: one name element, `studentname` or `userfield` bound to `ltct_certname`.~~ Removed with `ltct_certname` and T036 (changes 8 and 11).

~~**Organisation cohort rules**: `bulkprocessing` stays 0, so membership changes fire cohort events (R2).~~ Spec 016 no longer observes cohort events (change 18); spec 002's `classes/siteconfig/cohortrules.php` still writes `bulkprocessing = 0`.
