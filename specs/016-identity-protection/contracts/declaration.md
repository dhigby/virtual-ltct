# Contract: declaration changes (spec 016)

This extends spec 001's [declaration contract](../../001-site-config-as-code/contracts/declaration.md), as specs 002, 004 and 013 did. 001's contract gets one new paragraph, "Spec 016 extends it again", linking here.

**What is not declared, on purpose**: who is protected, and **which organisations have a minimum level**. Both are Moodle data, set by people on the site (data model). `organisations.yaml` does not change. An organisation that may need protection is added with a neutral key and name from its first commit (R12).

## Files

| File | Status | Read by | Payload key |
|---|---|---|---|
| `moodle/site/protection.yaml` | new | `site_config.py` `_validate_protection()` | `protection` |
| `moodle/site/settings/identity.yaml` | new | the existing settings loader | `settings` (merged) |
| `moodle/site/profile-fields.yaml` | changed: `ltct_certname`; `ltct_org` becomes private under decision 2 | `_validate_profile_fields()` | `profile_fields` |
| `moodle/site/roles.yaml` | changed (see [data-model.md](../data-model.md)) | `_validate_roles()` | `roles` |
| `moodle/site/certificate/template.yaml` | changed: `userfield` element | `_validate_certificate()` | `certificate_template` |
| `moodle/site/reports.yaml` | changed under decision 2: cohort scope condition | `_validate_reports()` | `reports` |

`protection.yaml` is added to `TOP_FILES` (`scripts/site_config.py` L230–248).

## Payload (PHP side)

```text
protection: {
  levels: ["none", "email", "firstname", "pseudonym"],
  withhold: { email: [...], firstname: [...], pseudonym: [...] },
  org_minimum_max: "firstname",
  neutral_surname: "",
  reconcile_minutes: 60,
  orgscope_ready: true|false     # computed: true once no report scopes by user:profilefield_ltct_org and ltct_org is private
}
```

## Applier

`applier::run()` (`classes/siteconfig/applier.php` L80–104) gains one step, **protection**, after **structure** and before **reporting**. It stores `protection` in `local_ltuse` config. It never reads or writes any user's protection or any organisation's minimum.

`drift` reports differences in the stored config. In addition, it reports **counts only**:

- users whose account differs from their protected state (a reconcile backlog);
- protected users waiting for a neutral username.

## Validation

**`protection.yaml`**
- The rules in [data-model.md](../data-model.md).

**`profile-fields.yaml`**
- `text` is a valid datatype.
- `ltct_certname` must be `visible: private` and `locked: 1`.
- `ltct_org` may be `private` only when no report's conditions include `user:profilefield_ltct_org` (R11).

**`roles.yaml`**
- `local/ltuse:viewidentity` is allowed only on `manager` and `mentor`.
- `local/ltuse:manageprotection` is allowed only on `manager`.
- `moodle/reportbuilder:edit` and `editall` are allowed only on `manager`.
- `editingteacher` and `teacher` must prohibit `moodle/course:useremail`, `moodle/backup:downloadfile`, `report/log:view` and `report/loglive:view`.

**`certificate/template.yaml`**
- Exactly one name element: `studentname`, or `userfield` with `field: ltct_certname`.
- A `userfield` must name a declared `private` field.
- `emailteachers` must be 0 and `emailothers` empty.

**Organisation cohort rules**
- `bulkprocessing` stays 0, so membership changes fire cohort events (R2). `cohortrules.php` already sets it, and the validator keeps it.

**`reports.yaml`** (from decision 2)
- No scope condition on `user:profilefield_ltct_org`.
