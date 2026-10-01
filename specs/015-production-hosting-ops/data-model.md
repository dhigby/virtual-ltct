# Data Model: Production Hosting and Operations

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Research**: [research.md](research.md)

This feature has no database of its own. Its entities live in two places: markdown files under `moodle/ops/` in the public repo, and state on the servers and backup storage. Nothing in the repo holds a credential, a backup or a learner's name (FR-005, FR-013).

| Entity | Where it lives | Public repo? |
|---|---|---|
| Production server | Hetzner Cloud VPS | No (described by the runbook) |
| Backup set | restic repository in Backblaze B2 | No |
| Restore drill record | row in `moodle/ops/drills.md` | Yes, no names |
| Operations register entry | row in `moodle/ops/register.md` | Yes |
| App plan review | monthly row in `moodle/ops/register.md` | Yes |
| Server environment | `/etc/ltct/ops.env` on each server; names in `moodle/ops/env.example` | Names only |

## Production server

The one dedicated host that runs the live training system.

### Fields

| Field | Type | Notes |
|---|---|---|
| `address` | URL | Read from `MOODLE_URL`; never written into a repo file (FR-002). |
| `provider` | text | Hetzner Cloud (R2). |
| `size` | text | vCPU / RAM / disk, e.g. 4 / 8 GB / 160 GB. |
| `sizing_assumption` | text | Expected concurrent learners plus headroom, recorded in the register (FR-011). |
| `os` | text | Ubuntu 24.04 LTS. |
| `moodle_release` | git tag | A tag on `MOODLE_502_STABLE`, 5.2.3 or later. |
| `platform` | text | PHP 8.3 FPM, nginx, PostgreSQL 16. |
| `pinned_plugins` | list | Each with its pinned version; reviewed at every upgrade (FR-007). |
| `state` | enum | See state transitions. |

### Relationships

- Has one **Server environment**.
- Produces many **Backup sets**, one per night.
- Is restored into by a **Restore drill**, which builds a separate throwaway server of the same shape.
- Each recurring burden it creates appears as an **Operations register entry**.

### Validation rules

- Learners are not enrolled until `state` is `live` (FR-001).
- `address` differs from the build host; the shared host never holds real learner data (FR-001).
- A rebuild uses only the repo, the environment and a restore; no admin-UI step is required (FR-002, SC-001).
- `sizing_assumption` is present before `live`, and states its review trigger: sustained CPU or memory above 70% (FR-011, R2).

### State transitions

```text
provisioned -> configured -> restored -> live
                                          |
                                          v
                                       upgrading -> live
```

| From | To | Gate |
|---|---|---|
| (none) | `provisioned` | `provision.sh` completed on an empty server meeting the documented minimum. |
| `provisioned` | `configured` | Site configuration from `moodle/` applied (spec 001, or the interim list in the runbook). |
| `configured` | `restored` | `restore.sh --production` completed and courses republished without duplicate identities. |
| `restored` | `live` | Every item on the go-live checklist is ticked (below). |
| `live` | `upgrading` | Change passed on `ltuse.net` with a test publish; provider snapshot and fresh backup taken (FR-007). |
| `upgrading` | `live` | Production checks pass; otherwise roll back to the snapshot. |

**Go-live checklist** (all required for `restored -> live`):

1. A backup operator is named in the register (FR-012).
2. A restore drill has passed, run by someone other than the operator (SC-001).
3. All monitors have been green for 7 days (FR-006).
4. `$CFG->debug` is off.
5. Outbound email delivery is proven (FR-010).
6. The app plan has been reviewed at least once (FR-010).

## Backup set (restic snapshot)

One nightly copy of the database and file store, held off the production server.

### Fields

| Field | Type | Notes |
|---|---|---|
| `snapshot_id` | restic ID | Assigned by restic. |
| `taken_at` | UTC timestamp | Nightly at 02:00 UTC. |
| `contents` | fixed | `pg_dump -Fc` file plus `moodledata`, excluding `cache`, `localcache`, `sessions`, `temp`. |
| `repository` | URL | Backblaze B2 bucket, from the environment. |
| `encrypted` | boolean | Always true; restic encrypts client-side. |
| `retention` | policy | `--keep-daily 30` until a data-protection policy sets otherwise. |
| `check_result` | pass / fail | From `restic check` after each run. |
| `heartbeat_sent` | boolean | Healthchecks.io ping on success. |

### Relationships

- Belongs to the **Production server**.
- Is the source for a **Restore drill record** or a production restore.

### Validation rules

- A new snapshot exists at least every 24 hours (FR-003, SC-002).
- The repository is at a different provider from the server (FR-003).
- No snapshot, dump or repository password is ever in the repo (FR-005).
- A missed heartbeat alerts the operator (FR-006).
- A snapshot never restored by a drill counts as untested.

### State transitions

```text
running -> stored -> checked -> expired
   |
   v
 failed
```

- `running -> stored`: dump and file backup complete.
- `stored -> checked`: `restic check` passes and the heartbeat is sent.
- `running -> failed`: any step fails; no heartbeat, so an alert fires.
- `checked -> expired`: retention prunes it after 30 days.

## Restore drill record

One row in `moodle/ops/drills.md` per drill.

### Fields

| Field | Type | Notes |
|---|---|---|
| `date` | date | Day the drill ran. |
| `trigger` | enum | `quarterly` or `post-major-upgrade` (FR-004). |
| `run_by` | role | `operator` or `backup operator`; a role, never a learner's name. |
| `snapshot_id` | restic ID | The snapshot restored. |
| `duration` | hours and minutes | From empty server to verified site. |
| `data_loss_window` | hours | Snapshot time to the simulated failure. |
| `test_accounts_verified` | yes / no | Enrolment, completion and badge present on standing test accounts. |
| `result` | enum | `passed` or `failed`. |
| `notes` | text | Problems found and runbook fixes; no learner names. |
| `server_destroyed` | date | Same day as `date`. |

### Relationships

- Restores one **Backup set** onto a throwaway server.
- Fulfils the "restore drills" **Operations register entry**.

### Validation rules

- `result` is `passed` only if `duration` is recorded, `data_loss_window` is 24 hours or less, and `test_accounts_verified` is yes (FR-004, SC-002).
- At least one row per calendar quarter, and one after each major upgrade (FR-004, SC-003).
- The drill server has `$CFG->noemailever = true` and a non-production `wwwroot` (R5).
- `server_destroyed` equals `date` (R5).
- The row holds no learner names or account identifiers; those go in the private ops log (FR-013).
- Before go-live, at least one `passed` row has `run_by` = `backup operator` (FR-012, SC-001).

### State transitions

```text
scheduled -> run -> passed
                 -> failed -> scheduled
```

- `scheduled -> run`: throwaway server created and `restore.sh` started.
- `run -> passed`: all validation rules above hold.
- `run -> failed`: any rule fails; the cause is fixed and a new drill is scheduled.

## Operations register entry

One row in `moodle/ops/register.md` per recurring burden.

### Fields

| Field | Type | Notes |
|---|---|---|
| `burden` | text | E.g. hosting, off-site backup storage, restore drills, upgrades, monitoring, learner and partner support, Moodle app plan, outbound email, any bolt-on from spec 005 or 014. |
| `owner` | name or role | Doug Higby (operator) unless stated otherwise. |
| `backup_owner` | name or role | "To be named" until the backup operator is appointed. |
| `frequency` | text | E.g. nightly, monthly, quarterly, per release. |
| `yearly_cost` | currency | Estimate, confirmed at purchase. |
| `cost_model` | enum | `flat`, `tiered-flat` or `per-GB-storage`. |
| `review_trigger` | text | The measurable point at which the line is revisited, e.g. sustained CPU above 70%, 500 GB stored, 40 active devices for two months. |

### Relationships

- Each **Production server** burden, **Backup set** storage line and **App plan review** maps to one entry.
- Decisions about held or replaced plugins are logged beside the upgrades entry (FR-007).

### Validation rules

- Every burden listed in FR-008 has a row.
- Every row has an `owner` (FR-008, SC-006).
- Every row has a `backup_owner` before go-live; "To be named" blocks go-live (FR-012).
- `cost_model` is never per-learner; only the three values above are allowed (FR-009).
- A `per-GB-storage` row states why storage tracks content size, not learner count, and carries a `review_trigger` (Plan, IX).
- The total yearly cost is the same at 100, 1,000 and 5,000 learners, apart from flat tier steps (SC-006).
- No row names anyone other than Doug Higby (operator) or the backup operator.

### State transitions

```text
proposed -> owned -> covered
```

- `proposed -> owned`: `owner`, `frequency`, `yearly_cost` and `cost_model` filled in.
- `owned -> covered`: `backup_owner` named. Constitution X counts operations as covered only at this state.

## App plan review

One monthly row in the app-plan section of `moodle/ops/register.md`.

### Fields

| Field | Type | Notes |
|---|---|---|
| `month` | YYYY-MM | Read on the first of the month. |
| `active_devices` | integer | From the Moodle Apps portal. |
| `plan` | enum | `free` (50), `pro` (500) or `premium` (unlimited). |
| `email_route_working` | yes / no | Email notifications confirmed delivering. |
| `decision` | text | Empty unless the threshold is reached. |

### Relationships

- Feeds the "Moodle app plan" **Operations register entry**.

### Validation rules

- One row every month once the site is live (FR-010).
- `email_route_working` is yes whatever the plan (FR-010).
- At 40 or more `active_devices` for two months running, `decision` records Pro or email-only (R10).
- If `active_devices` exceeds the plan limit, `decision` is filled in that month (spec Story 5).

### State transitions

```text
under-threshold -> watch -> decided
```

- `under-threshold -> watch`: one month at 40 or more devices.
- `watch -> under-threshold`: next month back below 40.
- `watch -> decided`: second month at 40 or more; the decision is recorded and the plan updated.

## Server environment

The file `/etc/ltct/ops.env` on each server, readable by root only. `moodle/ops/env.example` lists the same names with empty values.

### Variables

| Name | Purpose |
|---|---|
| `MOODLE_URL` | Public address of this server's site (FR-002). |
| `MOODLE_DIR` | Path to the Moodle code checkout. |
| `MOODLE_DATA` | Path to `moodledata`. |
| `PGDATABASE`, `PGUSER`, `PGPASSWORD` | Database connection for `pg_dump` and restore. |
| `RESTIC_REPOSITORY` | B2 bucket path. |
| `RESTIC_PASSWORD` | restic encryption key. |
| `B2_ACCOUNT_ID`, `B2_ACCOUNT_KEY` | B2 credentials. |
| `HC_BACKUP_URL` | Healthchecks.io ping URL for the backup. |
| `HC_CHECK_URL` | Healthchecks.io ping URL for `check.sh`. |
| `SMTP_PASSWORD` | Outbound relay secret (R9). |
| `LTCT_ROLE` | `production` or `drill`; controls `wwwroot` and `noemailever` in `restore.sh`. |

### Relationships

- Belongs to one server: production, the test instance or a drill server.
- Every script in `moodle/ops/` reads it; none takes a secret as an argument.

### Validation rules

- No value from this file ever appears in a repo file, commit or log (FR-005).
- `env.example` lists every name a script reads and holds no values.
- A drill server's `LTCT_ROLE` is `drill`, so it cannot send email or claim the production address (R5).
