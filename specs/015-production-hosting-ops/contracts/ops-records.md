# Contract: Operations records

Both files live in `moodle/ops/` in the public repo. Neither may name a learner. Anything that names a learner goes in the private operations log outside the repo.

## register.md

### Operators

| Column | Content |
|---|---|
| Role | `Operator` or `Backup operator` |
| Person | A name. The operator is Doug Higby. |
| Since | Date the role was taken, as `YYYY-MM-DD` |
| Restore drill passed | Date of that person's first passed drill, or blank |

Go-live needs a `Backup operator` row with a `Restore drill passed` date.

### Recurring burdens

| Column | Content |
|---|---|
| Burden | Hosting, off-site backup storage, restore drills, upgrades, monitoring, learner and partner support, Moodle app plan, outbound email, or a bolt-on from spec 005 or 014 |
| Owner | A role from the Operators table |
| Frequency | How often it recurs |
| Yearly cost | Amount and currency, or `0` |
| Basis | `flat` or `tiered-flat`. Never per learner. |
| Next tier | What triggers the next flat step, or blank |

### Sizing

| Column | Content |
|---|---|
| Date | `YYYY-MM-DD` |
| Server | Provider and instance class |
| Concurrent learners assumed | A number |
| Step-up trigger | The condition for the next tier |

### App-plan reviews

| Column | Content |
|---|---|
| Month | `YYYY-MM` |
| Active devices | The count from the Moodle Apps portal |
| Plan | `Free`, `Pro` or `Premium` |
| Decision | Blank, or the decision taken after two months running at 40 or more devices |

### Upgrade decisions

| Column | Content |
|---|---|
| Date | `YYYY-MM-DD` |
| Component | Moodle, platform software, or a pinned plugin |
| From / to | Versions |
| Decision | `applied`, `held` or `replaced`, with a one-line reason |

## drills.md

| Column | Content |
|---|---|
| Date | `YYYY-MM-DD` |
| Trigger | `quarterly` or `major upgrade` |
| Run by | `Operator` or `Backup operator`. A role, never a name. |
| Snapshot | The restic snapshot id restored |
| Snapshot time | UTC time of the snapshot |
| Data-loss window | Hours between the snapshot and the drill's notional failure. It passes at 24 or less. |
| Duration | Elapsed time from empty server to verified site |
| Test accounts verified | `yes` or `no`. Yes means enrolment, completion and badge are present on the standing test accounts. |
| Result | `pass` or `fail` |
| Server destroyed | `yes` with the date, which is the drill date |
