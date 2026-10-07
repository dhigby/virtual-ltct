# Contract: incoming mail (`moodle/site/settings/inbound-mail.yaml` and `moodle/site/inbound-mail.yaml`)

Makes forum post emails answerable by email (R1–R3). Doug decided (2026-10-07, Q8) to plan on one IMAP mailbox with case-preserving `+` subaddressing and cron every minute, provisioned as a spec 015 operator task. `messageinbound_enabled` stays 0 until 015 has provisioned it and instance check V2 passes. Reply addresses expire after one week (Q20). A custom handler for answering Moodle messages by email is later work, not this contract (Q19). Both files trace to rows #11 (the mentor route) and #25 (manageable notifications), as the spec's Requirements Traceability does (round 2).

## Shape

Two files, because `site_config.py` accepts a settings file only with the keys `{rows, purpose, settings}` and a `why` on every entry.

`moodle/site/settings/inbound-mail.yaml` (config settings; committed with `messageinbound_enabled: 0` and the 015 values left out until the mailbox exists):

```yaml
rows: [11, 25]
purpose: >-
  Let a mentor reply by email to a forum post email (spec 005, purpose 1).
settings:
  - name: messageinbound_enabled
    value: 0                         # 1 once 015 provisions the mailbox and V2 passes
    why: Off until the host provides the mailbox (Q8, spec 015 operator task) and V2 passes.
  - name: messageinbound_mailbox      # ≤ 15 characters
    value: (from 015)
    why: ...
  - name: messageinbound_domain
    value: (from 015)
    why: ...
  - name: messageinbound_host
    value: (from 015)
    why: ...
  - name: messageinbound_hostssl
    value: ssl
    why: ...
  - name: messageinbound_hostuser
    value: env:MOODLE_INBOUND_USER   # not secret, but kept out of the public repo
    why: ...
  - name: messageinbound_hostpass
    value: env:MOODLE_INBOUND_PASS
    secret: true
    why: ...
```

`moodle/site/inbound-mail.yaml` (new top file in `TOP_FILES`, keys `{rows, purpose, handlers, why}`):

```yaml
rows: [11, 25]
purpose: >-
  Switch on the forum reply handler (core keeps it in a table, not config).
handlers:
  - classname: '\mod_forum\message\inbound\reply_handler'   # single-quoted
    enabled: 1
    defaultexpiration: 604800        # one week, kept (Q20)
    validateaddress: 1
why: ...
```

Values for mailbox, domain and host come from spec 015 provisioning; they are filled only once the host exists.

## Rules (`validate` fails on any)

- `messageinbound_hostpass` must be `env:` with `secret: true`; a literal value fails.
- `messageinbound_mailbox` ≤ 15 characters.
- Any value matching `^<.*>$` or `(from 015)` is refused (placeholders are never applied).
- Classnames are compared with the leading backslash, as `record_from_handler()` stores them; double-quoted classnames are refused (a `\m` is a YAML escape).
- `validateaddress` must be 1. `defaultexpiration` must be > 0 (0 means never expires), except that `private_files_handler`'s is fixed at 0 by core (`lib/db/messageinbound_handlers.php`; its `can_change_defaultexpiration()` is false, R18), so when declared it must be declared as 0 and any other value is refused.
- Only handler classnames that exist in core (`\mod_forum\message\inbound\reply_handler`, `\core\message\inbound\private_files_handler`) are accepted; `private_files_handler` stays disabled.
- `identity.yaml` `allowedemaildomains` must be empty while this file is enabled.
- `messageinbound_enabled: 1` is refused while any of mailbox, domain, host or hostuser is missing.

## Apply (`local_ltuse\siteconfig\inbound`)

Config settings go through the existing `set_config` path. For each handler: `\core\message\inbound\manager::get_handler()` → `record_from_handler()` → for each of the three fields, write it only if the handler's `can_change_enabled()` / `can_change_defaultexpiration()` / `can_change_validateaddress()` is true, otherwise report `blocked` → `$DB->update_record('messageinbound_handlers', …)`, as core's own admin page does. Listed in `moodle/local_ltuse/README.md` as a direct write with no core API (Principle XI). Returns `ok | changed`.

## Drift

Reads the handler row and reports any difference in `enabled`, `defaultexpiration`, `validateaddress`, and a missing row.

## Tests

`test_site_config.py`: literal password refused; long mailbox refused; `allowedemaildomains` conflict refused. PHPUnit `siteconfig_inbound_test.php`: apply enables the handler, second apply `ok`, drift on a hand change.
