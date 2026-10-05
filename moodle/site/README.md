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
| `ignore.yaml` | Undeclared settings that are allowed to differ from Moodle's default, each with its reason. |
| `organisations.yaml` | The partner organisations we host, the shared course categories, and the mentors cohort. Each organisation gets a category, a learner cohort and a managers cohort. |
| `org-courses.yaml` | The courses only one organisation's people may join. Every other course is shared and open to every organisation. Only the maintainer edits it (spec 002). |
| `profile-fields.yaml` | The profile fields every learner has: organisation, role in the work and areas of expertise. |
| `course-fields.yaml` | The two course fields the publisher fills from each course's frontmatter: the competencies it aims at and the level it aims at. Both are locked, so only the publisher and the site team can change them. |
| `reports.yaml` | The report builder reports: one learner-progress report per organisation, for its managers, with a weekly email; and three for the site team (completions per course, the competencies published courses aim at, and the pilots). |
| `settings/completion.yaml` | Completion switched on for the site and for new courses, with each lesson's completion conditions shown on the course page. |
| `office-hours.yaml` | The one course where mentors offer office hours and learners book them, and its booking activity (spec 011). |
| `dashboard.yaml` | Blocks every learner's default dashboard carries: Upcoming events (spec 011). |
| `settings/calendar.yaml` | Calendar export, and the site's default time zone, UTC (spec 011). |
| `pathways.yaml` | Role pathways: a named set of competencies a role needs (spec 006). Empty until a role is supplied. |

The shapes are specified in
[`specs/001-site-config-as-code/contracts/declaration.md`](../../specs/001-site-config-as-code/contracts/declaration.md),
with spec 004's additions in
[`specs/004-progress-reporting/contracts/declaration.md`](../../specs/004-progress-reporting/contracts/declaration.md)
and spec 011's in
[`specs/011-events-calendar/contracts/declaration.md`](../../specs/011-events-calendar/contracts/declaration.md).
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
| `extra` | A plugin is installed but not declared (a subplugin of a declared plugin, such as `customcertelement_*`, is covered by its parent's pin), or an `ltct:` category, cohort or rule, an `ltct_` field, a menu option, a report of ours or a competency is no longer declared. | Declare it again, or retire it by hand. Apply never deletes it. (A competency no longer declared is retired by apply, and leaves the competencies report.) |
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

The reports count a learner as delivery however they were enrolled, except manually. Manual enrolment is kept for stage-7 pilots, and the pilots report shows those learners on their own.

## Organisation-only courses

Courses are shared. Everyone enrolled in a course sees everyone else in it, whatever their organisation. A course only for one organisation's people is listed in `org-courses.yaml`:

```yaml
org_only:
  - slug: <course slug>         # the course's folder name under modules/, in its branch form
    organisation: seed-company  # a key in organisations.yaml
    why: approved by the maintainer, issue #N
```

**Only the maintainer adds an entry.** The `why` records the approval and nothing else. Never write the organisation's reasons or circumstances in it: this repo is public. Keep the reason privately.

Organisation-only means only that organisation's people are enrolled. It does not hide the content: the course stays in this public repo, and its name shows in the organisation's category in Moodle.

**To make a course organisation-only:**

1. Add the entry, and run `validate`.
2. Publish the course. The publisher puts it in the organisation's category, `ltct:org:<key>`, and puts it back there on every publish if someone moves it.
3. Enrol the organisation as in "An organisation-only course" below.

Drift reports a listed course that is outside its organisation's category as `changed`, and an `ltct:` course inside an organisation's category that is not listed as `extra`. Apply never moves a course, because a move changes which category roles the course inherits. Publish it again instead, or move it by hand.

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

## Pathways

Row #12 (spec 006). A **competency pathway** is never declared or built by hand. Moodle works
it out when someone opens it, from what the publisher records on every publish: which
competencies a course aims at, the level it aims at, and whether it is delivered (stage 8). A
republish with a changed `competencies:` list or `target_outcome_level` moves the course
between pathways with no other step. Pilots never appear.

`apply` also copies two things in for the pathway pages: the four level labels from
[`outcome-levels.yaml`](../../outcome-levels.yaml), and each competency's page on the
competency site, built from the descriptor's `slug` and `mkdocs.yml`'s `site_url`.

**Adding a role pathway.** A role is added only when a person supplies it: the maintainer with
the department or the CBC programme. Add an entry to `pathways.yaml` with a `key`, a `name`,
the role's `competencies` (each copied exactly from `competencies.yaml`) and a `why` naming who
supplied it, then run `validate` and `apply`. A role's name never names a CBC level. A role
removed from the file is retired by `apply`, never deleted, so cohorts it was given to keep it
once it is declared again.

**Giving a pathway to a cohort.** The site team, or an organisation manager for their own
organisation's cohorts, does this on the **Assign pathways** page. It changes what the cohort's
members see; it enrols nobody. Enrolling a cohort into a pathway's courses is spec 008's
tooling.

**Taking a course out of pathways.** Hide it. A hidden course leaves every pathway at once and
its learners keep their completions. Never delete it (see badges above).

## Report downloads and emailed reports

Reports hold real people. So does the weekly email's attachment. Save every report download and every emailed attachment **outside this repository folder**, as you would the upload CSV below, and delete it once you are done with it. GitDoc pushes anything left in this folder to the public repo. `.gitignore` refuses `*.csv`, `*.xlsx`, `*.xls` and `*.ods` only as a backstop.

## The site team's administration tool

These steps change learner data, not configuration, so they are done in Moodle and never recorded in this repo. They are done with `scripts/ltct_admin.py` (spec 008), or with the `/manage-learners` command, which runs the same tool for you. Its contract is [`specs/008-admin-tooling/contracts/cli.md`](../../specs/008-admin-tooling/contracts/cli.md).

**Before the first run.**

- Each member of the site team has their **own** token, on their own account holding the `ltctadmin` role (`roles.yaml`). It is made on the server with `php public/local/ltuse/cli/setup_admin_token.php --username=<you> --token-file=<a file outside the repo>`, which writes the token to that file and never prints it. There is no shared admin account, so Moodle's logs show who made each change.
- Set `MOODLE_URL`, and `MOODLE_ADMIN_TOKEN` to that token, in your terminal. It is never the publisher's `MOODLE_TOKEN`; the tool refuses if the two are the same.
- Run `python scripts/ltct_admin.py check`. It names the site and your account, and refuses to go on if a function, a capability or a setting the tool relies on is missing, naming it.

**Every changing command is two steps.** Run it once: Moodle works out what each row would do and changes nothing, and the tool prints counts and a confirmation code. Run the line it prints, with `--apply --confirm <code>`, to make exactly those changes. If anything changed in between, the second run refuses and you preview again. A run cut off by a bad connection is finished by running the same line again; rows already done say `already done`.

**Files.** Every file the tool reads or writes holds real people, so it must be **outside every repository folder**. The tool refuses a path inside any git working tree, this repo's sibling worktrees included, and suggests `~/ltct-private/`. Delete each file once you are done with it. `python scripts/ltct_admin.py template --kind <intake|move|mentors|course-mentors|managers|suspension> --out ~/ltct-private/<name>.csv` writes a blank one with its column names. People are shown masked (`a***@example.org`) unless you add `--show-people` in your own terminal.

**Names.** `--org` takes an organisation key; `--cohort` and `--course` take idnumbers (`ltct:org:<key>`, `ltct:<slug>`). `python scripts/ltct_admin.py list organisations|cohorts|courses [--org <key>]` prints the ones you may use.

A row that `waits` asked for identity protection, and joins once identity protection is ready for that person.

### Bring learners on

```bash
python scripts/ltct_admin.py intake ~/ltct-private/intake.csv
python scripts/ltct_admin.py intake ~/ltct-private/intake.csv --apply --confirm <code>
```

One row per person, in the form managers send (see [Asking for new accounts](#asking-for-new-accounts)). A new address gets an account, an emailed password and their organisation. They sign in with their email address. Their organisation's cohort, and the courses it is enrolled in, follow in the same request. An address that already exists is matched, never duplicated. Someone already in another organisation, or suspended, is flagged and left alone: run `move` or `reactivate` deliberately. A row's `courses` column enrols them through the organisation's own enrolment, as a manager would.

### Enrol an organisation in a course

```bash
python scripts/ltct_admin.py enrol course --cohort ltct:org:<key> --course ltct:<slug>
```

This adds, or turns back on, one cohort sync into the course, so every current and future member is enrolled as Student. The tool decides who may go where:

- an organisation's cohort, into a published course or its own organisation-only course;
- its managers cohort, as Organisation manager, into its own organisation-only course only;
- anything else, including any course in Pilots, is refused.

`enrol pathway --cohort ltct:org:<key> --pathway <key>` does the same for every course in a pathway (spec 006), and keeps it in step as courses join the pathway.

To take a cohort out of a course, run `unenrol --cohort ltct:org:<key> --course ltct:<slug>`. It disables the cohort sync, never deletes it, so learners keep their grades and completion. `enrol course` turns it back on.

### Move learners to another organisation: mirror, then move

```bash
python scripts/ltct_admin.py enrol mirror --from <old key> --to <new key>
python scripts/ltct_admin.py move ~/ltct-private/move.csv
```

1. `enrol mirror` enrols the new organisation's cohort in every shared course the old one is in.
2. `move` (columns `email`, `organisation`) previews, per learner and course, what is **kept**, **gained**, **lost** and **suspended by rule** (an organisation-only course of the old organisation). A learner who would lose a shared course is refused, which is why the mirror comes first.

On apply, the learner's organisation field changes. Their cohorts follow, and the old cohort-sync enrolment is suspended with its history kept. There is nothing to unenrol by hand afterwards.

### Managers and the mentors cohort

```bash
python scripts/ltct_admin.py managers ~/ltct-private/managers.csv
```

Columns `email`, `cohort` and `action` (`add` or `remove`). The cohort is an organisation's managers cohort (`ltct:org:<key>:managers`) or `ltct:mentors`. An organisation's learner cohort is refused, because its members follow the organisation field. A manager becomes Organisation manager in every course their organisation is enrolled in, and removing them takes the role away. An ALTC covering several organisations is one row per organisation.

**Course leaders** (Course mentor, `teacher`) are enrolled by hand in each course they lead, with no group. The tool does not do this.

### Mentors

```bash
python scripts/ltct_admin.py mentors assign ~/ltct-private/mentors.csv
python scripts/ltct_admin.py mentors end --mentor <address>
python scripts/ltct_admin.py course-mentors ~/ltct-private/course-mentors.csv [--remove]
```

- `mentors assign` (columns `learner_email`, `mentor_email`) gives each learner their mentor, as the page in [Mentors](#mentors-assigning-and-ending-a-relationship) does one at a time. Each mentor must be in `ltct:mentors`.
- `mentors end` ends all of one mentor's relationships.
- `course-mentors` (columns `course`, `mentor_email`, and `learner_email` or `cohort`) records who assesses a learner, or a cohort, in one course, in place of their usual mentor (spec 008 plan decision 2). The automatic course-mentor sync does the enrolling once it is turned on, which waits on spec 016 (decision 11).

### Suspend and reactivate

```bash
python scripts/ltct_admin.py suspend ~/ltct-private/suspension.csv
python scripts/ltct_admin.py reactivate --email <address>
```

A file (column `email`), or one `--email`. Suspending ends the person's sessions and refuses their login. Their enrolments, grades and completion stay, and `reactivate` gives them back. This works for anyone but a site administrator, including staff, mentors and managers, whom an organisation manager cannot act on.

### See an organisation

`python scripts/ltct_admin.py summary --org <key>` prints its cohort membership and enrolments, masked. Add `--out ~/ltct-private/<name>.csv` to write it to a file instead.

An organisation manager only follows their own people. They cannot create accounts or change anyone's organisation. They enrol, suspend and reactivate their own people on their organisation page (spec 002).

Core's **Site administration > Users > Upload users** stays a fallback for when the tool cannot be used. It enrols through the manual method, which counts as a pilot, and saves the organisation field after the account exists, so use it only for accounts with no courses and no protection.

## Asking for new accounts

*For organisation managers. Spec 008, user story 4.*

Only the site team creates accounts. To ask for some, send the site team a list in the form
below. They preview it, check it with you if anything is unclear, and apply it unchanged.

**1. Get a blank file.** Ask the site team for an intake file. They make it with
`python scripts/ltct_admin.py template --kind intake --out <a folder outside the repo>/intake.csv`
and send it to you. It holds the column names and nothing else.

**2. Fill in one person per row.** Open it in a spreadsheet and keep the first row as it is.

| Column | Fill in | Must you? |
|---|---|---|
| `email` | The person's own email address. Each address once only. Moodle sends their password to it. | Yes |
| `firstname` | Their first name, as they want it shown. | Yes |
| `lastname` | Their last name. | Yes |
| `organisation` | Your organisation's key, exactly as the site team gave it to you (for example `seed-company`), not its full name. | Yes |
| `country` | Their country as two letters, for example `KE` or `PG`. | No |
| `protection` | Optional, and blank for nearly everyone. Fill it in only for a person who asked, when you added them, for their identity to be protected. Then write `email`, `firstname` or `pseudonym`, and talk to the site team first. | No |
| `pseudonym` | The name to show instead of theirs. Only with `protection` set to `pseudonym`. | No |
| `email_checked` | Only for a protected person, and needed for every one of them. Others in a course still see their email address, so it must not give them away. Check with the person that it names neither them nor your organisation, then write `yes` here, or give them another address. Until it says `yes` the row waits; nothing is made. | No |
| `courses` | Courses to start them in, by the course code the site team gave you (`ltct:<name>`), separated by `;`. Leave empty if your organisation's courses are enough. | No |

There is no column for a username, a password or a role. Everyone signs in with their email
address and the password Moodle emails them; their organisation decides their cohort and
courses. A column the form does not have is refused, so do not add any.

**3. Keep it private.** The list names real people. Save it in a folder only you can open, and
**never inside a repository folder or a folder that syncs to one**: anything there can be
published. Send it to the site team the way you would send any personal data, and delete your
copy once they confirm the accounts exist.

**What you do yourself.** Once the accounts exist, everything else about your own people is on
your **organisation page** (spec 002): enrolling them in a course, unenrolling them, suspending
and reactivating their accounts, sending a password reset link, and assigning their mentors.
You never need the site team for those, and you cannot see or change anyone outside your
organisation. Ask the site team only for new accounts, or to move someone to another
organisation.

## The managers' page

Each organisation manager has a **My organisation** link in their user menu. The page lists their organisation's people, with each person's courses and progress, and their email. For their own learners they can:

- enrol them in a published course, or in their organisation's own course, as Student;
- unenrol them from a course they enrolled them in;
- send them a password reset link, which goes only to the learner's own email;
- suspend their account, which ends their sessions and applies to the whole site, and reactivate it;
- assign and end their mentors, chosen from the Mentors cohort.

A manager never acts on staff, mentors or other managers, and never on another organisation's people. Those stay with the site team. A manager cannot create accounts or change anyone's organisation. Moodle's log records the manager as the person who made each change.

A manager's enrolments count as delivery in the reports, as cohort sync does. They go through a separate enrolment method in each course, a self enrolment named **Organisation enrolment**, made the first time a manager enrols someone there. Leave it enabled, and leave its new enrolments switched off: learners cannot use it to enrol themselves, and disabling it makes its enrolments inactive.

## Mentors: assigning and ending a relationship

A mentor follows the learners assigned to them across every course those learners take, for as long as the relationship lasts (spec 003). The relationship is the `mentor` role, held by the mentor **in the learner's own profile**, never in a course. Who mentors whom is learner data, so it lives only in Moodle and is never written in this repo.

An organisation manager can also assign and end mentors for their own learners, from the managers' page above. The steps below are the site team's.

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

**Feedback on a learner's work** is not this role's job, but a learner's mentors are also their **course mentors** by default: `local_ltuse` enrols them as Course mentor (`teacher`) in each course the learner takes, in a "Mentor group" with the learners they assess there, and removes them as soon as the reason ends (spec 008 research R10). A one-course or cohort mentor recorded with `ltct_admin.py course-mentors` takes the default mentor's place in that course (spec 012; spec 008 plan decision 2). To assign many mentors at once, use `ltct_admin.py mentors assign`; to end all of one mentor's relationships, `ltct_admin.py mentors end`. The automatic sync is on (`local_ltuse/coursementorsync: 1` in `settings/admin.yaml`) since 2026-10-05, when spec 016 limited what a course mentor sees of protected learners to their own mentor group (spec 008 plan decision 11). Course mentors are no longer enrolled by hand.

## Protecting a person

Some learners work where being identifiable puts them, or the people they work with, at risk. Identity protection lets them take part exactly as before under less of their identity (spec 016). **Who is protected, their pseudonym and their real name live only in Moodle. Never write them here, in an issue, a PR or a screenshot.** Screenshots of `ltct-test-*` accounts only are fine in a PR.

| Level | Others see |
|---|---|
| Email hidden | The name and profile; classmates do not see the email address |
| First name only | The first name and organisation only: no surname, picture, location, role or expertise |
| Pseudonym | A chosen name and the organisation only |

Each level includes the ones before it. The organisation shows at every level: tell the person so when they are protected. The people who run their courses and their organisation's managers still see the email address, which is why it is checked (below). The site team, the learner's mentors, the course mentor who assesses them in a course (whose own mentor group holds them), and their own organisation's managers still see the real identity, with a **Protected** marker, on the learner's profile, the Mentoring page and **People I support** (`/local/ltuse/protected.php`). Nobody else ever sees the marker, and there is no download of protected people.

**Only for someone who asks** (Doug, 2026-10-05 (scope review)). Protection is to be offered when the person is added, in the welcome message and in site help; none of those says so yet (spec 016 task T047, on spec 008's intake and welcome message). Until it does, tell each person you add that they can ask, and who would still see their real identity. Saving a new or higher level records that the person asked, and that you checked their email address identifies neither them nor their organisation. The page warns when the part before the @ looks like their name, or the domain contains part of their organisation entry's key. That second warning is only a hint: it cannot recognise the employer of a SIL partner or an independent learner, so look at the address yourself; your confirmation is what counts. Have the address changed to one that does not identify them before you save. Nothing changes for anyone who did not ask.

**To protect a person**: set it when the account is made, before they start. An organisation's manager can do this for their own people then. After that, raising it, lowering or removing it, and correcting the real name are the site team's: open `/local/ltuse/protection.php?id=<user id>` (an unprotected profile has no link), pick the level, and save. The learner gets a notice saying what others now see.

**Protect early.** Later, a rename links what they already did to the new name, so the page asks you to acknowledge that first. A fresh account is one option to talk through with the person, not the default.

**Usernames.** Everyone signs in with their email. At First name only or Pseudonym, a username that contains the real first name or surname is replaced with a neutral one automatically; the person keeps signing in with their email.

**Names and email are not locked.** Learners change their own. For a protected learner, the plugin puts the protected name back on any edit, and their surname shows as `·`. To correct a protected learner's real name, use their **Identity protection** page (site team).

**There is no organisation-wide protection** (Doug, 2026-10-05 (scope review)): each person is protected only when they ask, and an organisation's own managers always see their people's real identity. A person who does not want their organisation's managers to see it is placed by the site team under a neutral organisation entry with no managers. **Neutral organisation names**: an organisation that asks not to be named publicly gets a neutral key and name from its first commit, because the key can never change and `organisations.yaml` is public. No other organisation needs one.

**Lowering.** Nothing is lowered automatically, and moving to another organisation keeps the person's level. A picture removed by protection does not come back; the learner uploads it again.

**Course logs.** Course staff see each learner's IP address in the course logs, which gives away a rough location. If a protected person asks for that to be hidden, tick **block course logs** on their **Identity protection** page: course staff then lose the course log, today's log and the live log in every course they take, for every learner there, which is the cost. A course they join later is covered within the hour. The plugin owns those course-level prohibits, so do not set them by hand.

**Course backups.** Course leaders cannot download backups. Never leave a course backup that includes users in a course's backup area: it carries every enrolled account's details.

**When the hourly task fails.** **Reconcile protection** (`\local_ltuse\task\reconcile_protection`) repairs protected accounts that something else changed. If a repair fails, the run is recorded as failed and core's failed-task alert fires: open the task log, fix the cause, and let the next run repair it. With nobody protected it does nothing. On a core upgrade, while anyone is protected, re-run spec 016's quickstart V7.

What protection cannot do, and what to tell the learner, is listed as known gaps in [spec 016's research](../../specs/016-identity-protection/research.md#known-gaps-fr-015): copies already emailed or downloaded, the app's cache for up to 18 hours, file author names inside uploaded documents, the email address course staff see, and the organisation, which shows at every level.

## Events and live sessions

Row #21 (spec 011). Events are open across organisations: a course's events reach everyone in
that course, and an organisation-only course's events reach only that organisation, because only
its people are enrolled. Every learner sees each event time in their own time zone. Changes and
cancellations are announced automatically, by email and in the app; new events are not.

- **A site-wide event** (site team): **Calendar > New event**, type **Site**. Everyone sees it.
- **A course event** (course mentors, in their courses): open the course, then **Calendar > New
  event**, type **Course**. To repeat it, tick **Repeat this event** and give the number of weeks.
  An organisation manager does the same in their organisation's own course, once spec 002's
  open-courses change has landed. Managers cannot post site events, or events in a shared
  course.
- **A live session**: a course or site event whose **description** holds the meeting link. Never
  put the link in **Location**, because the Moodle app turns Location into a maps search. After
  the session, add the notes or the recording link to the same event's description. A live
  session is never a course's completion condition, because learners who could not attend must
  still be able to finish (FR-010). BigBlueButton stays disabled: hosts use the meeting tool
  they already have.
- **A message to one organisation's people** (there is no organisation-wide event): **Site
  administration > Users > Bulk user actions**, filter by the cohort `ltct:org:<key>`, select
  all, then **Send a message**.
- **A repeating event across a daylight-saving change** keeps its creator's local time. Someone
  in a zone whose clocks change on a different date sees it an hour earlier or later for those
  weeks. That is correct, not a fault.

Learners subscribe to or export their own calendar from **Calendar > Import or export calendars**
without help. Anything already exported stays in their own calendar tool and cannot be recalled.

## Office hours

The course **Mentor office hours** (`office-hours.yaml`) holds one booking activity for every
mentor. `apply` creates it, and `local_ltuse` keeps it filled.

- **Groups are automatic.** Each mentor has one group, holding them and the learners they
  mentor. It changes as soon as a mentor relationship starts or ends on the learner's profile
  (see Mentors above), and an hourly task repairs anything missed. Never add or remove a member
  by hand: the page refuses it, and the task would undo it anyway. Group names carry no one's
  name.
- **A learner sees only their own mentors' free times**, and never who else booked. Mentees of
  one mentor cannot see one another.
- **Booking happens in the browser.** In the Moodle app, the course opens the booking page in
  the browser, and the app's calendar shows the booking.
- **Both sides are emailed** for every booking, change of time and cancellation, in their own
  time zone. Whoever acted gets a confirmation, and the other gets a notice.
- **No booking or cancelling in the last 12 hours** before a slot. Inside that window, a learner
  messages their mentor instead.
- **The booking page names the learner's time zone**, with a link to change it on their profile.
- **A known gap** (spec 011 plan, decision 4): someone who crafts the booking request by hand
  can book another mentor's slot. That mentor sees the unexpected booking and can remove it,
  which tells the learner.

A learner or mentor who leaves is suspended in the course, never removed, so their past
appointments stay. Who booked what is learner data, so it stays in Moodle and its privacy
export.
