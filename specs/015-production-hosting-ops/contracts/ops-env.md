# Contract: Operations environment

## Files

- `/etc/ltct/ops.env` holds the values on each server. It is owned by root, has mode `0600`, and never enters the repo.
- `moodle/ops/env.example` in the repo lists the same names with empty values. It never holds a value.
- A drill server has its own `/etc/ltct/ops.env`, with its own `MOODLE_URL`.

## Variables

| Name | Secret | Used by | Meaning |
|---|---|---|---|
| `MOODLE_URL` | no | provision, restore, check | The site's public address. It becomes `$CFG->wwwroot`. It is never hard-coded. |
| `MOODLE_DIR` | no | provision, restore, check, cron | The Moodle code directory, a git checkout on `MOODLE_502_STABLE`. |
| `MOODLEDATA_DIR` | no | provision, backup, restore, check | The Moodle file store. |
| `PGDATABASE` | no | provision, backup, restore | The PostgreSQL database name. |
| `RESTIC_REPOSITORY` | no | backup, restore | The restic repository in the Backblaze B2 bucket. |
| `RESTIC_PASSWORD` | yes | backup, restore | The restic encryption key. Losing it loses every backup. |
| `B2_ACCOUNT_ID` | yes | backup, restore | The Backblaze B2 application key id. |
| `B2_ACCOUNT_KEY` | yes | backup, restore | The Backblaze B2 application key. |
| `HC_PING_BACKUP` | yes | backup | The Healthchecks.io ping URL for the nightly backup. |
| `HC_PING_CHECK` | yes | check | The Healthchecks.io ping URL for the 10-minute health check. |

## Rules

- Names are spelled exactly as above. `MOODLE_URL` is the same name the publisher reads.
- The publisher's `MOODLE_TOKEN` is not in this file. It lives on the operator's machine, not on the server.
- A secret is never echoed, logged, passed on a command line or written outside `/etc/ltct/ops.env`.
- The operator and the backup operator each hold `RESTIC_PASSWORD` and the B2 key off the server, so a restore works when the server is gone.
