# Research: Production Hosting and Operations

Prices below are estimates from memory, to be confirmed at purchase and recorded in `moodle/ops/register.md`. None of them changes a decision.

## R1. Operator

- **Decision**: Doug operates; a second person, named in the register, is backup operator and must pass a restore drill from the runbook alone before go-live.
- **Rationale**: decided by the maintainer 2026-10-01 (resolves spec FR-012). A self-managed VPS keeps cost flat and keeps config-as-code meaningful.
- **Alternatives considered**: a managed Moodle host (flat fee, but most hosts restrict plugins and CLI access, which `local_ltuse` and spec 001 need); a partner operator (no partner has offered).

## R2. VPS provider and size

- **Decision**: Hetzner Cloud, a 4 vCPU / 8 GB / 160 GB shared-vCPU instance (CPX31 class, about €15 a month), Ubuntu 24.04 LTS. Sizing assumption: up to about 100 learners active at once, with a cohort-launch spike handled by headroom. The next step up is a flat tier (8 vCPU / 16 GB) taken when sustained CPU or memory passes 70%.
- **Rationale**: flat monthly price, hourly billing (which makes throwaway drill servers cost cents), provider snapshots for rollback points, and both EU and US regions.
- **Alternatives considered**: DigitalOcean and Linode (same model, roughly twice the price for the same size); staying on the shared host (ruled out by INTENT, 2026-09-30).

## R3. Platform software

- **Decision**: PHP 8.3 FPM behind nginx, PostgreSQL 16, Moodle cloned from git and checked out at a release tag on `MOODLE_502_STABLE`, cron as a systemd timer every minute.
- **Rationale**: Moodle 5.2's `environment.xml` requires PHP 8.3.0 and PostgreSQL 16 (confirmed in our source, 2026-10-01). PostgreSQL matches the build host, so a dump moves between them. Git makes "which release is running" a single command, and an upgrade a tag change.
- **Alternatives considered**: tarball install as on `ltuse.net` (upgrade means unpacking over the tree by hand); MySQL 8.4 (no benefit, and the dumps would stop matching the test instance); Ansible (a second tool for one server and an operator on Windows; an idempotent shell script run over `ssh` is enough).

## R4. Backups

- **Decision**: `backup.sh` runs nightly at 02:00 UTC: `pg_dump -Fc`, then `restic backup` of the dump plus `moodledata` (excluding `cache`, `localcache`, `sessions`, `temp`), to a Backblaze B2 bucket. Restic encrypts client-side. Retention `--keep-daily 30`, then `restic check`. Success pings Healthchecks.io; a missing ping alerts.
- **Rationale**: dump first, files second. A file created after the dump is harmless extra; a file deleted in between survives in Moodle's trash directory for days. Restic gives encryption, deduplication and retention in one tool, and B2 is a different provider from the VPS, which covers the "provider fails" edge case. 30 days is the spec's default until a data-protection policy sets retention.
- **Alternatives considered**: a Hetzner Storage Box (flat-rate, but the same provider as the server); Moodle course backups (they omit users, site config and cross-course data, so they cannot restore a site); provider snapshots alone (same provider, not encrypted by us, coarse retention). Snapshots are still taken as upgrade rollback points.
- **Cost note**: B2 bills per stored GB (about $6/TB a month). Expected size is under 100 GB, well under $10 a year. It tracks course media and database size, not learner count; the register sets a review point at 500 GB.

## R5. Restore drills

- **Decision**: quarterly and after each major upgrade. Create an hourly-billed VPS, run `provision.sh`, then `restore.sh --snapshot latest`, then verify on standing test accounts (a test learner with an enrolment, a completion and a badge kept in production for this purpose), then record the result in `drills.md` and destroy the server the same day. `restore.sh` writes `$CFG->noemailever = true` and a non-production `wwwroot` unless `--production` is given.
- **Rationale**: a drill restores real learner data onto a second machine. Disabling email stops it messaging learners, and same-day destruction keeps the copy short-lived. Checking test accounts means nobody inspects a real person's record to prove a backup.
- **Alternatives considered**: drilling onto `ltuse.net` (would put real learner data on a shared host with 15 other sites and 76% disk use).

## R6. Monitoring

- **Decision**: UptimeRobot free tier checks the public URL every 5 minutes and alerts after 2 failures (within the spec's 10 minutes); it also watches the edge certificate and is the SC-004 availability record. `check.sh` runs every 10 minutes on the server: `php admin/cli/checks.php --type=status` (includes `tool_task\check\cronrunning`), disk above 85%, origin certificate under 14 days, then pings Healthchecks.io with pass or fail. A missed ping alerts too, so a dead server is caught twice. Alerts go to Doug's email and phone app, then to the backup operator.
- **Rationale**: an outside monitor sees what learners see; a dead-man switch catches silent failures (cron stopped, backup not run) that no outside check can see. Both free tiers fit one site with a dozen checks.
- **Alternatives considered**: self-hosted Uptime Kuma (a second server to operate); Prometheus and Grafana (far beyond one server's needs).

## R7. TLS and the edge

- **Decision**: keep Cloudflare proxying the site, in Full (strict) mode, with a Let's Encrypt origin certificate from `certbot` that renews automatically. SSH goes to the server's IP, as on the build host.
- **Rationale**: matches the current setup, so `MOODLE_URL` and DNS are the only changes at cutover. Full (strict) refuses an expired origin certificate, which is why `check.sh` watches it.
- **Alternatives considered**: a Cloudflare origin certificate (long-lived, but ties the server to Cloudflare); no proxy (works, but loses free DDoS shielding).

## R8. Upgrades

- **Decision**: security releases reach production within 14 days; minor releases go in a monthly window; OS security patches install automatically through `unattended-upgrades`. Each Moodle or plugin change runs first on `ltuse.net` with a test publish of two courses, then on production after a provider snapshot and a fresh `backup.sh` run. A pinned plugin without support for a new branch holds the upgrade or is replaced, and the decision is logged in `register.md`.
- **Rationale**: `ltuse.net` already exists, holds no real learner data, and is where the publisher is verified. Two rollback points (snapshot for speed, restic for independence) make a bad upgrade a short outage.
- **Alternatives considered**: a third staging server (more cost and drift for no extra coverage).

## R9. Outbound email

- **Decision**: send through an SMTP relay on a free or flat tier (candidates: an SIL-provided relay, or Brevo's free 300 a day), configured through spec 001 with the password as an environment-variable secret. Prove delivery as a go-live gate.
- **Rationale**: email is the route notifications must always have (FR-010), and new VPS addresses usually have port 25 blocked or land in spam.
- **Alternatives considered**: sending direct from the VPS (poor deliverability); Amazon SES (bills per message, so it grows with learners).
- **Open**: which relay. This is a research task before go-live, not a blocker for the plan.

## R10. Moodle app plan

- **Decision**: stay on the free plan (50 active devices a month). On the first of each month the operator reads active devices from the Moodle Apps portal and records the figure in `register.md`. At 40 or more for two months running, decide between Pro (500 devices, flat) and email only, and record the decision.
- **Rationale**: spec FR-010 asks for a deliberate choice with email kept working; an early-warning threshold leaves time to decide before learners lose push.
