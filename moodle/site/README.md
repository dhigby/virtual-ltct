# `moodle/site/`: what a correctly configured server looks like

Every Moodle setting, plugin and role the training system depends on is declared here. A
new server is built from this folder plus a data restore, with nothing clicked into the
admin interface. **A setting is not done until it is in this folder.** That holds for every
training-system spec. Each one adds its settings here rather than configuring by hand, and
the pull request that adds a setting is the record of why it exists.

| File | Holds |
|---|---|
| `site.yaml` | The minimum Moodle release, and each plugin with its pinned version and enabled state. |
| `roles.yaml` | Roles and their system-context permissions. |
| `settings/*.yaml` | Settings, one file per topic. Each cites the rows of [`../REQUIREMENTS.md`](../REQUIREMENTS.md) it serves. |
| `course-discussions.yaml` | Which courses' discussion forums are shared across organisations. Every published course has one, separated by organisation unless listed here (spec 012). |
| `ignore.yaml` | Undeclared settings that are allowed to differ from Moodle's default, each with its reason. |
| `organisations.yaml` | The partner organisations we host, and the shared course categories. Each organisation gets a category, a learner cohort and a managers cohort. |
| `profile-fields.yaml` | The profile fields every learner has: organisation, role in the work and areas of expertise. |
| `course-fields.yaml` | The two course fields the publisher fills from each course's frontmatter: the competencies it aims at and the level it aims at. Both are locked, so only the publisher and the site team can change them. |
| `reports.yaml` | The report builder reports: one learner-progress report per organisation, for its managers, with a weekly email; and three for the site team (completions per course, the competencies published courses aim at, and the pilots). |
| `settings/completion.yaml` | Completion switched on for the site and for new courses, with each lesson's completion conditions shown on the course page. |

The shapes are specified in
[`specs/001-site-config-as-code/contracts/declaration.md`](../../specs/001-site-config-as-code/contracts/declaration.md),
with spec 004's additions in
[`specs/004-progress-reporting/contracts/declaration.md`](../../specs/004-progress-reporting/contracts/declaration.md).
Apply also copies the competency list from the repo-root [`competencies.yaml`](../../competencies.yaml)
into the plugin, for the competencies report. A competency removed from that file is retired
there, never deleted.

## The three commands

You need `MOODLE_URL` (the site), `MOODLE_DIR` (the Moodle code directory on the server) and,
from your own machine, `MOODLE_SSH` (an `ssh` destination such as `ltuse`).

```powershell
python scripts/site_config.py validate   # offline; CI runs this on every change here
python scripts/site_config.py drift      # read-only: how does the server differ? exit 1 if it does
python scripts/site_config.py apply      # make the server match; a second run changes nothing
```

Every run names the server it is acting on first. A `MOODLE_URL` that does not match that
server is refused before anything happens. Add `--json` to keep the report as evidence for a
pull request. The output never contains a secret or any learner data.

## Changing something

1. Edit the right file, and give the entry a `why`: what breaks, or which requirement row
   needs it.
2. Run `validate`, then `drift` to see the difference, then `apply`.
3. Run `drift` again. It should say `No differences.`
4. Open a pull request with the change. Attach the apply output if a reviewer needs it.

**A setting** goes in the `settings/` file for its topic. Name it as Moodle's admin tree
does: `enabletrusttext` for a core setting, `scorm/scormstandard` for a plugin's. Write values
as strings or numbers. Use `1` and `0`, never `true`, `on` or `yes`: YAML quietly turns `off`
into `false`, and the review would not show it. Find a setting's name in Moodle's
`settings.php` for that component, on the branch the server runs.

**A plugin** goes in `site.yaml`. A core plugin takes only `enabled`. A third-party plugin is
pinned with `version` (its `$plugin->version`) and a `source` (`url` plus a quoted `sha256`).
Apply never installs, upgrades or downgrades plugin code: it refuses a mismatch, naming both
releases. To move a pin, verify the new release on the test instance, install it there, and
raise the pin in the same pull request.

**A role** goes in `roles.yaml`. A role with an `archetype`, such as `manager` or
`editingteacher`, is Moodle's own: only the capabilities you list are managed, and its other
permissions are left as the install made them. A role with `archetype: ""` is ours: every
capability it holds is managed, so one granted by hand shows up as drift.

## Secrets and per-server values

A value written `env:NAME` comes from the environment variable `NAME` on the machine you
run `apply` from. The repo never holds the value, because the repo is public. Add
`secret: true` to a password, key or token:

```yaml
  - name: smtppass
    value: env:MOODLE_SMTP_PASS
    secret: true
    why: "Outgoing mail (spec 015)."
```

The value travels only inside the `ssh` stream. It is never written to disk, put on a
command line or logged. If the variable is not set, apply skips that one setting, writes
nothing for it, and exits 1. Drift needs no secret: it checks a secret only for being set.

`env:MOODLE_URL/local/ltuse/styles.css` builds a URL from the target site. A plain
`https://…` value is refused, because a hard-coded host would tie the declaration to one
server.

Settings that `config.php` sets, such as `wwwroot` and `debug`, are not declared here.
Drift skips them, and apply refuses to write them. They belong to whoever writes
`config.php` when the server is provisioned (spec 015).

## Reading a drift report

| Kind | Means | What to do |
|---|---|---|
| `changed` | A declared value differs on the server. | Run `apply`, or change the declaration if the server is right. |
| `missing` | A declared plugin, role, course field, report or competency is not on the server. | Install the plugin at its pin. Apply creates the others. |
| `wrong-release` | A plugin is not at its pinned version. | Install the pinned release, or raise the pin in a reviewed change. |
| `pending-upgrade` | Plugin code is newer than the database. | Run `admin/cli/upgrade.php`. |
| `unknown` | A declared setting or capability does not exist on the server. | Its plugin is missing, or an upgrade renamed it. Fix the declaration. |
| `forced` | A declared setting is set in `config.php`. | Remove it from the declaration. |
| `extra` | A plugin is installed but not declared, or an `ltct:` category, cohort or rule, an `ltct_` field, a menu option, a report of ours or a competency is no longer declared. | Declare it again, or retire it by hand. Apply never deletes it. (A competency no longer declared is retired by apply, and leaves the competencies report.) |
| `adopted` | Apply gave an existing category its `ltct:` idnumber instead of creating a duplicate. | Nothing. |
| `ambiguous`, `wrong-context`, `wrong-datatype` | Two candidates match one declared item, a cohort sits outside system context, or a field has another type. Apply stops before writing anything. | Fix it by hand on the server, then run `apply` again. |
| `unmanaged` | An undeclared setting differs from Moodle's default. | Someone changed it by hand. Declare it, revert it, or add it to `ignore.yaml` with a reason. |

## Partner organisations

Each partner organisation is one entry in `organisations.yaml`, with a `key` and a `name`:

```yaml
organisations:
  - key: seed-company      # lowercase, hyphens; never changes once applied
    name: Seed Company      # the display name; safe to change
```

**To add an organisation:**

1. Add the entry.
2. Run `validate`, then `apply`. This creates the organisation's category, its learner cohort, its managers cohort, its cohort rule and its option in the organisation field.
3. Run `drift`. It should say `No differences.`

The `key` is what a learner's organisation field holds, so never change it. Rename an organisation by changing `name` only.

Removing an entry deletes nothing. Drift then reports the organisation's items as `extra`. Retire them by hand once its learners' records are dealt with.

The profile field category, "About your work", is found by its name, because Moodle gives it no other identity. To rename it, rename it once by hand in Moodle and change `profile-fields.yaml` in the same pull request. The same holds for the course field category in `course-fields.yaml`.

Each organisation also gets its own learner-progress report and weekly schedule, made from the `per: organisation` entry in `reports.yaml`. Its managers cohort is the report's only audience, and the report shows only that organisation's learners. Apply sets back a report that was edited by hand. It never runs a report, so its output holds no learner names or row counts.

## Badges and the certificate

Row #23 (spec 013). These are training evidence only. Every text says "training completed",
never "certified", and never puts a learner at a CBC level. `validate` checks every text with
`scripts/cbc_wording.py`, rendered against every course title in `modules/`, so it fails before
anything is applied.

- **`badges.yaml`** is the one badge template. Every course delivered at stage 8 gets a badge made from it, with its image at `badges/completion.png`. Changing the wording and running `apply` rewords every badge already issued, in place, without a republish. A badge is never switched off.
- **`certificate/template.yaml`** is the one certificate design, with its images beside it. `apply` builds it as a `mod_customcert` site template and copies it into every course's certificate activity.
- **`settings/badges.yaml`** names the issuing programme once, for both. The issuer contact comes from `MOODLE_BADGE_CONTACT`, so no address is committed.

The badge design, the logo, the issuer's name and the certificate layout are placeholders until
the maintainer supplies them (spec 013 plan, decision 4).

**Who sees a learner's badges.** Not other learners: `roles.yaml` takes
`moodle/badges:viewotherbadges` from the authenticated-user role. The site team keeps it.
**Spec 003's mentor role must grant `moodle/badges:viewotherbadges`, assigned in the learner's
user context**, so a mentor sees their own assigned learners' badges and nobody else's.
Organisation managers follow completion through their reports, not through badges.

**Retire a course by hiding it, never by deleting it.** Deleting a course archives its badges,
which breaks verification for everyone who holds one, and deleting its certificate activity
deletes every certificate code already issued. A hidden course keeps both working.

## Report downloads and emailed reports

Reports hold real people. So does the weekly email's attachment. Save every report download and every emailed attachment **outside this repository folder**, as you would the upload CSV below, and delete it once you are done with it. GitDoc pushes anything left in this folder to the public repo. `.gitignore` refuses `*.csv`, `*.xlsx`, `*.xls` and `*.ods` only as a backstop.

## The site team's four steps

These steps change learner data, not configuration, so they are done in Moodle and never recorded in this repo. Spec 008 will script them. Until then, the site team does them by hand:

1. **Create accounts.** Use **Site administration > Users > Upload users** with a CSV file. Put each learner's organisation key in a `profile_field_ltct_org` column, for example `seed-company`. Their cohort and the courses it is enrolled in follow automatically.

   The CSV holds real people, so make it and keep it **outside this repository folder**, then delete it once the upload is done. GitDoc pushes anything left in this folder to the public repo. `.gitignore` refuses `*.csv` only as a backstop.

2. **Enrol an organisation into a course.**
   1. Check the course is in **separate groups** (Course settings > Groups). New courses are, but an older or hand-made course may not be.
   2. Create a group named for the organisation.
   3. Add two **cohort sync** enrolment methods, both into that group:
      - the organisation's learner cohort, as Student;
      - its managers cohort, as Organisation manager.

3. **Make someone an organisation manager.** Add them to that organisation's managers cohort (**Site administration > Users > Cohorts**). They become a manager in every course their organisation is enrolled in. Remove them from the cohort and the role goes.

4. **After moving a learner to another organisation,** change their organisation field. Their cohorts follow, and their old enrolment is suspended with its history kept. Then, in each course **both** organisations are enrolled in, unenrol the learner's old, suspended cohort-sync enrolment (Participants > the learner's enrolment > Unenrol). Without this, the old organisation's manager still sees them on that course's participants list. Their grades and completion stay, because they are still enrolled through the new organisation.

An organisation manager only follows their own people. They cannot create accounts, enrol anyone or change anyone's organisation (spec 002).

## Mentors: assigning and ending a relationship

A mentor follows the learners assigned to them across every course those learners take, for as long as the relationship lasts (spec 003). The relationship is the `mentor` role, held by the mentor **in the learner's own profile**, never in a course. Who mentors whom is learner data, so it lives only in Moodle and is never written in this repo.

**To assign a mentor** (site team, or an admin):

1. Open the learner's profile.
2. Go to **Preferences**.
3. Under **Roles**, choose **Assign roles relative to this user**.
4. Choose **Mentor**.
5. Search for the mentor, select them and click **Add**.

The mentor sees the learner on their **Mentoring** page at once, in the browser and in the Moodle app, with every course the learner takes now or later. The two also become message contacts, so they can message each other with no course in common.

**To end a relationship**, go to the same page, select the mentor on the right and click **Remove**. The mentor loses the view on their next page load, the plugin's message contact goes, and the learner's enrolments, completions and grades are untouched. **To reassign**, remove one mentor and add the other.

**Rules.**
- A mentor may be from any organisation. The assignment, not the organisation, is what grants the view.
- A learner may have more than one mentor. Each sees only their own learners.
- `roles.yaml` lets `manager` assign `mentor` (`allowassign`). That is what offers Mentor on the page above.

**When a mentor leaves the program**, end all their relationships at once on the server:

```bash
php public/local/ltuse/cli/mentor_contacts.php --end-all --mentor=<username>
```

It asks first, prints counts only, and leaves every learner's records as they are. After the upgrade that adds mentor contacts, run `php public/local/ltuse/cli/mentor_contacts.php --sync` once, so mentors assigned earlier get their contacts too.

**Feedback on a learner's work** is not this role's job. Where a course asks for work, enrol the mentor as **Course mentor** (`teacher`) with the organisation's group, as in step 2 above (spec 012). Assigning many mentors at once, and enrolling mentors into their learners' courses automatically, are spec 008's.
