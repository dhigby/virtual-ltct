# Contract: Operations scripts

The scripts live in `moodle/ops/`. Each runs as root on an Ubuntu 24.04 LTS server over `ssh`.

## Rules for every script

- Each script reads its settings from `/etc/ltct/ops.env` and from nothing else. See [ops-env.md](ops-env.md).
- A missing or empty required variable stops the script with exit code 2 before it changes anything.
- No script prints a secret value, a ping URL, a database row or any learner data.
- Output names steps and outcomes only, one line per step, prefixed `[ok]`, `[skip]` or `[fail]`.
- Exit code 0 means success. Exit code 1 means a step failed. Exit code 2 means a usage or configuration error.
- No script writes into the repo working tree.
- No script edits vendored Moodle code (constitution XI).

## provision.sh

- **Usage**: `provision.sh` with no arguments.
- **Does**: installs PHP 8.3 FPM, nginx, PostgreSQL 16, `restic` and `certbot`. Clones Moodle from git and checks out a release tag on `MOODLE_502_STABLE` into `MOODLE_DIR`. Creates `MOODLEDATA_DIR` and the database `PGDATABASE`. Installs the systemd units from `moodle/ops/systemd/` and enables `unattended-upgrades`.
- **Idempotent**: yes. A second run on a provisioned server changes nothing and prints `[skip]` for each step already in place.
- **Does not**: apply site configuration (spec 001 does that) or restore data (`restore.sh` does that).
- **Prints**: each step and its outcome, then the Moodle release now checked out.

## backup.sh

- **Usage**: `backup.sh` with no arguments. `ltct-backup.timer` runs it nightly at 02:00 UTC.
- **Does**, in order:
  1. `pg_dump -Fc` of `PGDATABASE`.
  2. `restic backup` of the dump plus `MOODLEDATA_DIR`, excluding `cache`, `localcache`, `sessions` and `temp`.
  3. `restic forget --keep-daily 30 --prune`.
  4. `restic check`.
  5. Pings `HC_PING_BACKUP` on success, or `HC_PING_BACKUP` with `/fail` appended on any failure.
- **Idempotent**: yes. A rerun adds a new snapshot and applies the same retention.
- **Prints**: each step, the new snapshot id and the snapshot time. It never prints file names from `MOODLEDATA_DIR`.
- **Leaves**: no local dump. The dump is deleted after the snapshot step, whatever the outcome.

## restore.sh

- **Usage**: `restore.sh --snapshot <id|latest> [--production]`.
- **Arguments**:
  - `--snapshot <id|latest>`: required. A restic snapshot id, or `latest`.
  - `--production`: optional. Without it the restore is a drill.
- **Preconditions**: `provision.sh` has run on the server. The database `PGDATABASE` is empty and `MOODLEDATA_DIR` holds no files. If either holds data, the script stops with exit code 2 and changes nothing.
- **Does**: restores the snapshot, loads the dump into `PGDATABASE`, places the file store in `MOODLEDATA_DIR`, and sets `$CFG->wwwroot` from `MOODLE_URL`.
- **Without `--production`**: also writes `$CFG->noemailever = true`, so a drill never messages a learner.
- **With `--production`**: leaves email enabled.
- **Idempotent**: no. It refuses a non-empty target instead of overwriting it.
- **Prints**: the snapshot id and time restored, the elapsed time, and whether email is disabled. These are the values `drills.md` records.

## check.sh

- **Usage**: `check.sh` with no arguments. `ltct-check.timer` runs it every 10 minutes.
- **Checks**:
  - `php admin/cli/checks.php --type=status`, which includes `tool_task\check\cronrunning`.
  - Disk use on the volumes holding `MOODLEDATA_DIR` and the database. It fails above 85%.
  - The origin certificate for `MOODLE_URL`. It fails when expiry is under 14 days away.
- **Reports**: pings `HC_PING_CHECK` when every check passes, or `HC_PING_CHECK` with `/fail` appended when any check fails. A missed ping alerts on its own.
- **Idempotent**: yes. It changes nothing on the server.
- **Prints**: one line per check with its result. It never prints the body of a Moodle check that could name a user.

## systemd units

| Unit | Runs | Schedule |
|---|---|---|
| `moodle-cron.service` / `moodle-cron.timer` | `php admin/cli/cron.php` as the web user | every minute |
| `ltct-backup.service` / `ltct-backup.timer` | `backup.sh` | daily at 02:00 UTC |
| `ltct-check.service` / `ltct-check.timer` | `check.sh` | every 10 minutes |

Each service loads `/etc/ltct/ops.env` as its `EnvironmentFile`.
