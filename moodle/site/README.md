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

## The site team's steps

These steps change learner data, not configuration, so they are done in Moodle and never recorded in this repo. Spec 008 will script them. Until then, the site team does them by hand.

**Create accounts.** Use **Site administration > Users > Upload users** with a CSV file. Put each learner's organisation key in a `profile_field_ltct_org` column, for example `seed-company`. Their cohort and the courses it is enrolled in follow automatically.

The CSV holds real people, so make it and keep it **outside this repository folder**, then delete it once the upload is done. GitDoc pushes anything left in this folder to the public repo. `.gitignore` refuses `*.csv` only as a backstop.

**Enrol an organisation in a course.** There are two recipes. Never use groups to keep organisations apart: a course may use groups for its own teaching, but never for that.

- **A shared course** (any course not in `org-courses.yaml`): add one **cohort sync** enrolment method for the organisation's learner cohort, `ltct:org:<key>`, as **Student**, with no group. Do this once for each organisation. **Never** add a managers cohort to a shared course: managers would see every organisation's people in it. Drift fails if one is there.
- **An organisation-only course** (listed in `org-courses.yaml`): add two **cohort sync** enrolment methods, both with no group:
  - the organisation's learner cohort, `ltct:org:<key>`, as **Student**;
  - its managers cohort, `ltct:org:<key>:managers`, as **Organisation manager**.

  Enrol only the organisation the course is listed for. Nothing stops you enrolling another, so check the key.

**Make someone an organisation manager.** Add them to that organisation's managers cohort (**Site administration > Users > Cohorts**). They get the managers' page at once, and become Organisation manager in their organisation's own courses. Remove them from the cohort and all of it ends at once.

**Move a learner to another organisation** by changing their organisation field. Their cohorts follow. Their enrolments in shared courses stay. Their enrolments in the old organisation's own courses are suspended, with their history kept.

**Enrol course leaders** (Course mentor, `teacher`) in each course they lead, by hand, with no group.

**Fill the mentors cohort.** Add each person who may mentor to the **Mentors** cohort, `ltct:mentors`. A mentor may come from any organisation. Managers pick their learners' mentors only from this cohort.

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

**Feedback on a learner's work** is not this role's job. Where a course asks for work, enrol the mentor as **Course mentor** (`teacher`) in that course, with no group (spec 012). Assigning many mentors at once, and enrolling mentors into their learners' courses automatically, are spec 008's.

## Protecting a person or an organisation

Some learners work where being identifiable puts them, or the people they work with, at risk. Identity protection lets them take part exactly as before under less of their identity (spec 016). **Who is protected, their pseudonym and their real name live only in Moodle. Never write them here, in an issue, a PR or a screenshot.**

| Level | Others see |
|---|---|
| Email hidden | The name and profile; classmates do not see the email address |
| First name only | The first name and organisation only: no surname, picture, location, role or expertise |
| Pseudonym | A chosen name and the organisation only |

Each level includes the ones before it. The organisation shows at every level (decision 2, option a): tell the person so when they are protected. The people who run their courses and their organisation's managers still see the email address, which is why it is checked (below). The site team, the learner's mentors, the course mentor who assesses them in a course, and their own organisation's managers still see the real identity, with a **Protected** marker, on the learner's profile, the Mentoring page and **People I support** (`/local/ltuse/protected.php`). Nobody else ever sees the marker.

**Only for someone who asks** (Doug, 2026-10-05 (scope review)). Protection is offered when the person is added, in the welcome message and in site help. Saving a new or higher level records that the person asked, and that you checked their email address identifies neither them nor their organisation. The page warns when the part before the @ looks like their name, or the domain like their organisation's; have the address changed to one that does not identify them before you save.

**To protect a person**: set it when the account is made, before they start. An organisation's manager can do this for their own people then. After that, raising it, lowering or removing it, and correcting the real name are the site team's: open `/local/ltuse/protection.php?id=<user id>` (an unprotected profile has no link), pick the level, and save. The learner gets a notice saying what others now see.

**Protect early.** Later, a rename links what they already did to the new name, so the page asks you to acknowledge that first. A fresh account is one option to talk through with the person, not the default.

**Usernames.** Everyone signs in with their email. At First name only or Pseudonym, a username that contains the real first name or surname is replaced with a neutral one automatically; the person keeps signing in with their email.

**There is no organisation-wide protection** (Doug, 2026-10-05 (scope review)): each person is protected only when they ask, and an organisation's own managers always see their people's real identity. A person who does not want their organisation's managers to see it is placed by the site team under a neutral organisation entry with no managers. An organisation that may need protection gets a **neutral key and name from its first commit**, because the key can never change and `organisations.yaml` is public. SIL's Area entries are the one deliberate exception.

**Lowering.** Nothing is lowered automatically. Someone who leaves a protected organisation keeps their level until an entitled person lowers it. A picture removed by protection does not come back; the learner uploads it again.

**Names are locked for everyone** (`settings/identity.yaml`, pending decision 3): learners no longer change their own name or email. The site team changes them in the admin user editor; for a protected learner, use their **Identity protection** page.

What protection cannot do, and what to tell the learner, is listed as known gaps in [spec 016's research](../../specs/016-identity-protection/research.md#known-gaps-fr-015): copies already emailed or downloaded, the app's cache for up to 18 hours, and file author names inside uploaded documents.

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
