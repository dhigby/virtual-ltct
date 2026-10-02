# Implementation Plan: Production Hosting and Operations

**Branch**: `specs/moodle-requirements` | **Date**: 2026-10-01 | **Spec**: [spec.md](spec.md)

## Summary

Stand up a dedicated production VPS that is built from the repo, backed up nightly off-site, watched by external monitors and upgraded on a schedule, with Doug as operator and a named backup operator as a go-live gate. The work is a small set of idempotent shell scripts and systemd units under `moodle/ops/` (provision, backup, restore, health check), a runbook a second person can follow, and an operations register that lists every recurring burden with its owner and flat yearly cost. Site configuration itself is applied by spec 001's declaration; this spec provides the server it is applied to.

Stack choices that are new to the repo: Ubuntu 24.04 LTS on a Hetzner Cloud VPS, Moodle installed from git on `MOODLE_502_STABLE`, `restic` for encrypted off-site backups to Backblaze B2, and two free-tier monitors (UptimeRobot for outside reachability, Healthchecks.io as a dead-man switch for cron, backups and on-server checks). [research.md](research.md) records why each was chosen.

## Project Structure

```text
moodle/
├── REQUIREMENTS.md            # rows 9, 14, 16, 17, 20: status updated in the delivering PR
└── ops/
    ├── README.md              # runbook: provision, rebuild, restore, drill, upgrade, incident, go-live checklist
    ├── register.md            # operations register: burden, owner, frequency, yearly cost; sizing; app-plan reviews
    ├── drills.md              # restore-drill record: date, duration, data-loss window, result (no names)
    ├── env.example            # variable names only, never values
    ├── provision.sh           # idempotent server setup on Ubuntu 24.04
    ├── backup.sh              # nightly pg_dump + moodledata to restic, then heartbeat
    ├── restore.sh             # restore a snapshot into an empty provisioned server
    ├── check.sh               # every 10 min: Moodle status checks, disk, origin cert, heartbeat
    └── systemd/
        ├── moodle-cron.service / .timer
        ├── ltct-backup.service / .timer
        └── ltct-check.service  / .timer
INTENT.md                      # Decisions: operator decided 2026-10-01; open question narrowed
```

**Structure Decision**: everything lives in one new `moodle/ops/` folder beside `local_ltuse/`, because it is operator material for the training-system half and touches no curriculum script. Nothing under `scripts/` changes: the publisher already reads the server from `MOODLE_URL`, and a grep confirms no file hard-codes `ltuse.net`.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. Runbook, scripts and register live in the repo. Restore brings learner data back from backups, never from the repo. No Moodle-to-repo sync. |
| II. Portability, config as code | PASS. `provision.sh` plus spec 001's apply plus `restore.sh` is the whole rebuild; moving provider is the same run on a new host. Moodle from git makes the release explicit. |
| III. Public repo, private people | PASS. Scripts read secrets from `/etc/ltct/ops.env` on the server, which is never in the tree; `env.example` holds names only. Backups go off-site only. `drills.md` records times and results with no names; anything naming learners goes in the private ops log. |
| IV. Disclosure boundary | Not touched. |
| V. CBC fidelity | Not touched. |
| VI. No LMS orientation | PASS. Learners see none of this. The runbook is written for an operator who is not a sysadmin by trade. |
| VII. One shape, gated stages | Not touched. |
| VIII. Language data | Not touched. |
| IX. Flat cost | PASS with a note. Every line is flat-rate or a flat tier. Off-site storage is billed per stored GB, which tracks course media and database size, not learner count; the register states the estimate and the tier at which it is reviewed. |
| X. Traceable and verified | PASS. Cites rows 17, 16, 14, 9/20; the delivering PR updates them. The operator is named (Doug) and the burden is listed; operations are claimed covered only once the backup operator is named. Upgrades go through `ltuse.net` with a test publish first. Restore drills verify standing test accounts, not real learners. |
| XI. Survives an upgrade | PASS. No vendored code is edited. Moodle tracks the upstream stable branch by tag; `admin/cli/checks.php` and `tool_task\check\cronrunning` were confirmed present on our 5.2.3+ source (2026-10-01). |
| Platform: one instance, `MOODLE_URL` | PASS. One production instance; `ltuse.net` becomes the test instance for upgrades and pilots, holding no real learner data. |

Re-checked after Phase 1 design: no change.

## Dependencies and gates

- **Spec 001 (site config as code)** must exist before a rebuild is complete. Until it ships, the runbook's "apply configuration" step is `setup_publishing.php` plus a short, explicit list of settings, each marked as moving into 001's declaration.
- **Go-live gate** (runbook checklist): backup operator named in the register; first restore drill passed by someone other than Doug; all monitors green for 7 days; `$CFG->debug` off; outbound email proven; app plan reviewed.
- **INTENT.md** gets a 2026-10-01 decision ("Doug operates the production server on a self-managed VPS; a named backup operator is a go-live gate"), and the open question narrows to the remaining choices. Constitution X's "while the operator is undecided" clause then no longer binds; no amendment is needed.
