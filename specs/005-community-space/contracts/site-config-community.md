# Contract: site config for cohort spaces and mentor sync

Decisions applied: Doug, 2026-10-07, both rounds (plan.md, "Decisions" and "Round 2").

## Payload

`build_payload()` adds `community: {space, shapes}`. Teaching cohorts are matched at run time; no teaching cohort key, name or member is in the repo or the payload. Area spaces carry only the public organisation keys already in `organisations.yaml`, for entries declaring `space: area`.

## Apply (`local_ltuse\siteconfig\cohortspaces::apply()`)

Called from `applier::run()` after `apply_structure()`, so categories and organisation cohorts exist.

For each Moodle cohort matching a shape's selector (on apply, from an observer on `\core\event\cohort_created` / `cohort_updated` for teaching cohorts, or the existing reconcile task), in order:
1. Find the course by idnumber (`ltct:site:cohort:<key>`; teaching key the numeric cohort id, area key the organisation key); create with `create_course()` if absent: `newsitems = 0` (else mod_forum's `course_created` observer adds an Announcements forum, observer.php L123-135), `visible = 1`, groupmode 0, groupmodeforce 0, enablecompletion 0, `showreports = 0`, fullname and shortname both the cohort's own name (the Moodle cohort name, or the entry's `name`), in category `ltct:cohort-spaces`. If the name is already another course's short name, report `blocked: name_taken` and create nothing; never add a key, id or code to the name (round 2). On later applies, rename the course if its cohort's name has changed.
1a. Disable the default manual instance (`enrol_manual` defaultenrol) with `enrol_plugin::update_status(ENROL_INSTANCE_DISABLED)` and leave it empty.
1b. Set the declared space-mentor override: `assign_capability('moodle/course:manageactivities', CAP_ALLOW, <teacher role id>, <course context id>, true)` (accesslib, as `protection\service` already uses it), so the space mentor can lock by hand (round 2).
2. For each of the two forums: find by idnumber; create with `util::upsert_module()` with the declared settings and the shape's `forcesubscribe` (teaching `FORUM_INITIALSUBSCRIBE`, area `FORUM_CHOOSESUBSCRIBE`). `forcesubscribe` is set **only at creation**; later applies never change it (changing to initial re-subscribes everyone). Every other declared setting is restored on each apply, since the space mentor can now edit it (round 2): apply compares the `forum` row, read by the course module's instance id (a raw read, listed in `moodle/local_ltuse/README.md`), and restores through `util::upsert_module()`, which calls core `update_moduleinfo()`; it never writes the `forum` table directly. No `lockdiscussionafter` (Q12).
3. Guidelines are each forum's intro (step 2). Post a short pointer discussion once, at creation, with `forum_add_discussion()` and pin it with `forum_discussion_pin()`.
4. Find an `enrol_cohort` instance with `customint1` = the cohort; add one if absent (`customint2` = 0, no group chat (Q15), role `spacemember`), with status **disabled**. Apply never enables it: course-mentor sync enables it once the space has a cohort-mentor row (Q13), and apply leaves an enabled instance enabled.
5. Return `created | ok | changed | waiting: no_mentor | blocked: name_taken` per item. Never delete a course, forum, post or enrolment, and never make a space read-only (Q7).

If the cohort does not exist, the space is reported `blocked: cohort_missing` and nothing is created. An Area entry not yet applied by 002 has no cohort and is `blocked` the same way.

If a teaching cohort is deleted after its space exists (`\core\event\cohort_deleted`, or apply finding a space course whose cohort id no longer exists), apply keeps the course, its forums and its posts and reports `cohort deleted`; it creates nothing and deletes nothing (round 2).

Output (apply, drift and the summary line) names a teaching space only by its numeric key, never by its cohort's name or idnumber; a name mismatch is reported as `name differs`, printing neither name (Principle III).

## Drift (inspector)

Reports per space: missing course, missing forum, a third forum or any other added activity (extra), a forum setting or intro that differs from the declaration (read from the `forum` row, the same raw read as apply; including a hand change of `forcesubscribe`, which apply reports but does not revert), a `lockdiscussionafter` set by hand, fullname or shortname differs from the cohort's name, the space-mentor override missing, enrol instance missing or with a different role or group, `groupmode` ≠ 0, an enabled enrolment instance other than the cohort instance and the course-mentor `enrol_self` instance (fail; the latter is expected, never extra), an enabled cohort instance in a space with no cohort-mentor row (`no moderator`: the space stays open, round 2), any `ltct:mentorgroup:` group (fail), a space course whose cohort no longer matches (extra; the space stays), a space course whose cohort was deleted (`cohort deleted`, for the site team: the space, its posts and its mentor stay, round 2).

## Mentor sync (`admin\course_mentor_sync`, spec 008 code)

- `is_ltct_course()` gains an explicit second kind, `ltct:site:cohort:*`. `course_mentor_records` accepts it for cohort-mentor rows only, made by the site team through `ltct_admin.py` (ALTC self-service deferred to spec 017, round 2).
- **A space row names the space's own cohort.** `course_mentor_records` and `ltct_admin.py` (`admin_files.py`) refuse a row on `ltct:site:cohort:<key>` whose cohort is not that space's: for a teaching space the cohort's numeric id must equal the key; for an Area space the cohort must be `ltct:org:<key>`. Otherwise the row would open the space (the gate rests on the row) while `target()` finds no member of that cohort, so no mentor would ever be enrolled. The ltct_admin preview prints the space key it expects for the cohort given, so the site team can check the key there; it never prints the cohort's name.
- For a space, precedence 2 (cohort rows) only; no one-course rows and no default-mentor fallback (Q4).
- For an `ltct:site:cohort:*` course, `facts()` passes the space member role's id (`community.yaml` `space.role`) as `studentroleid`, not Student; otherwise no member counts and no mentor is enrolled.
- In a space, sync creates no mentor groups (skips creategroups/add/remove).
- **Mentor gate (Q13, round 2)**: the gate rests on the cohort-mentor row alone. When a space has at least one cohort-mentor row and its cohort instance is disabled, sync enables the instance first. `course_mentor_rules::is_active_student()` counts a member only on an enabled instance, so the mentor is enrolled on the **next** sync, once cohort sync has enrolled the members. Sync never disables a cohort instance: when a space's last row is removed, the mentor is removed and the space stays open (Q7), and drift reports `no moderator`.
- **Digest overrides (Q9, Q10, round 2)**: `require mod/forum/lib.php`; pass `core_user::get_user($id)`. On every sync run, for each target (user, forum, value):
  - delivery course: mentor 0 on each forum where they hold the synced Teacher role; each synced mentee of a mentor 0 on the same forums;
  - teaching space: mentor and each active member 0 on Problems and 1 on Team (shapes `mentor_digest`, `member_digest`); members' overrides follow membership, whether or not the space currently has a mentor;
  - area space: none.
  Write with `forum_set_user_maildigest()` only when the person has no `forum_digests` row for that forum (a raw read, listed in the README) and no `local_ltuse_digest_override` record for it, so a personal choice is kept, and insert a record (userid, forumid, value, released 0) of the write.
  **A reset by the person**: where an unreleased record exists but there is no `forum_digests` row, the person set the forum back to their default themselves. Sync marks the record `released` and never writes that override again (FR-005a); deleting it instead would let the next run write it again.
  To remove, in the removerole step, before `role_unassign()`, inside the same per-course lock (a delivery-course mentee's when they no longer have a mentor there while still enrolled): only where a record exists. If it is unreleased and the current `forum_digests` value still equals the recorded value, set -1; if not, the person has changed it, so keep it. Delete the record either way.
  **When core has removed the role**: before calling, sync checks `has_capability('mod/forum:viewdiscussion', <forum context>, <user>)`. Where core has already removed the person's role (a member leaving a cohort, or a mentee whose cohort enrolment is removed, both through `unenrolaction = 3`), it leaves the override and the record in place and does not retry: the override is harmless while they are suspended and holds again if they rejoin, and sync writes nothing new then. No `required_capability_exception` is raised, so no reconcile loops on it.
- **Cohort deleted (round 2)**: with `enrol_cohort/unenrolaction` pinned to 3 (`settings/groups.yaml`), core does not delete the cohort's `enrol_cohort` instance: `enrol_cohort_handler::deleted()` (`enrol/cohort/locallib.php` L132-157, R18 difference 1) removes its role assignments and disables it, leaving `customint1` naming the deleted cohort, so members lose access. Sync must never re-enable such an instance (it checks the cohort exists before the enable step); drift detects `cohort deleted` as a cohort instance whose `customint1` names no cohort, and treats that left-over disabled instance as expected, not `extra`. The mentor's enrolment is sync's own `enrol_self` instance and is not touched. For a space whose cohort no longer exists, sync makes no change: it keeps the mentor enrolled with the `teacher` role and their overrides, even though no member counts as active, and does not remove it in the removerole step or as a stray assignment in the reconcile task. `remove_orphan_records()` skips rows on `ltct:site:cohort:*` courses, so the row still names the mentor. The recorded mentor takes over the space; drift flags `cohort deleted`, and the site team decides its future. To remove the mentor's row, the site team keeps the mentor, or removes the whole space by the written procedure, `delete_course()` on the space, after which the next `\local_ltuse\task\course_mentor_reconcile` run's `remove_orphan_records()` deletes the row because its course is gone; `ltct_admin.py` cannot name the row, because it names a row's cohort by idnumber (`scripts/admin_files.py` `_check_course_mentors`, `course_mentor_records::resolve()`) and a deleted cohort has none, and no tool route is built (a `cohort: deleted` remove mode is later work).
- When sync adds a mentor in a delivery course, subscribe them to every existing discussion in the course's forums that their mentees started or posted in (Observer, same race fix). The search reads `forum_discussions` and `forum_posts` raw, listed in the README. Not in spaces.
- `enrolment_rules::decide()` explicitly refuses `ltct:site:*` for ltct_admin cohort enrolment (`course_not_ltct` stays the reason).

## Observer

`\mod_forum\event\discussion_created` and `\mod_forum\event\post_created` in an `ltct:<slug>` course → when the author is a synced mentee of mentor M in that course, `subscriptions::subscribe_user_to_discussion()` for M (Q10, "start or post in" as changed by Doug on 2026-10-07); then, if the stored `forum_discussion_subs.preference` is later than the discussion's first post `created` (read from `forum_posts`, a raw read), set it to that `created` (direct write, listed in `moodle/local_ltuse/README.md`, R10). In a teaching space the Auto subscription already covers mentors through the `role_assigned` observer; in an Area space nobody's mail is overridden. The observer returns early for `ltct:site:` courses.

## Roles (`roles.yaml`, applied by the existing roles kind)

- `spacemember`: new, archetype student, course context; keeps participants, user details and messaging (Q6, Q17).
- `teacher`: `mod/forum:exportforum` prohibit; `mod/forum:exportdiscussion`, `mod/forum:exportpost` and `mod/forum:exportownpost` prevent; `mod/forum:postprivatereply` unchanged (Q14, round 2). 012's and 016's role tests run with this change.
- The space-mentor `moodle/course:manageactivities` allow is a course-context override set by `cohortspaces` apply (step 1b), not a system-level `roles.yaml` change.

## Site-wide settings (existing settings kind; each entry carries `why`)

| File | Setting | Value | Decision |
|---|---|---|---|
| `settings/notifications.yaml` | `defaultpreference_maildigest` | 1 | unchanged (row #25) |
| `settings/notifications.yaml` | `defaultpreference_autosubscribe` | 1 | R8 |
| `settings/notifications.yaml` | `defaultpreference_trackforums` | 1 | Q18 (new accounts; existing ones by the one-off script, round 2) |
| `settings/notifications.yaml` | `defaultpreference_mailformat` | 1 | R8 |
| `settings/forums.yaml` (new) | `enablerssfeeds`, `forum_enablerssfeeds` | 0 | R8 |
| `settings/forums.yaml` | `enableportfolios` | 0 | Q14 |
| `settings/forums.yaml` | `maxeditingtime` | 1800 | Q11, kept at 30 minutes |
| `settings/forums.yaml` | `usetags` | 1 | Q19, kept on |
| `settings/logging.yaml` (new) | `loglifetime` | 365 | Q21 |
| `settings/inbound-mail.yaml` (new) | `messageinbound_*` | see the inbound-mail contract | Q8, Q20 |

Validate refuses `loglifetime` below 120 days (one quarter plus a month to run the review) and refuses `enableportfolios: 1` outright (FR-011): learners' `student` role still holds `mod/forum:exportownpost`, which portfolios would expose.

## Read tracking for existing accounts (one-off, round 2)

`moodle/local_ltuse/cli/trackforums_existing.php`, run once on the server by the site team after `defaultpreference_trackforums: 1` is applied. For every non-deleted, non-guest account with `trackforums = 0` it sets 1 through `user_update_user()` (confirm the signature in `MOODLE_502_STABLE` before use, Principle XI). It prints counts only (accounts seen, changed), writes no file, and is idempotent. Its result is recorded as a count in the PR, never as names.

## Exemptions asserted by tests

Learner-home continue, identity path 4 (`levels::course_counts`), inspector groupmode and discussion checks, `admin_list`, `access::may_enrol_into`, pathway catalogue, the mentee-discussion observer: each ignores `ltct:site:`.

## Tests

The Area shape is tested by these PHPUnit tests only until a real Area mentor is recorded; then the Area-shape instance check runs (quickstart, "Area shape, later"; round 2).

PHPUnit `siteconfig_cohortspaces_test.php` (idempotent apply, both shapes, blocked on missing cohort, `blocked: name_taken` with no code added, fullname and shortname equal the cohort's name and follow a rename, forcesubscribe unchanged on re-apply, a hand-edited forum setting restored, Auto in teaching and Optional in area, no groups, exactly two forums and no lock, the space-mentor override present, cohort instance created disabled and left enabled by apply once enabled, manual instance disabled, no space for an entry without `space: area`; a deleted teaching cohort's space kept with its posts, reported `cohort deleted` by apply and drift; apply and drift output contain neither a teaching cohort's name nor its idnumber); extended `course_mentor_sync_test.php` (a space starting with its cohort instance disabled and one cohort-mentor row ends, after two sync runs, with the instance enabled and both the mentor and the members enrolled; removing the last row removes the mentor and leaves the instance enabled; no fallback, no one-course rows, no mentor groups in a space; mentor and members 0 on Problems and 1 on Team in a teaching space; no overrides in an area space; mentee override in a delivery course; an override set on a forum added after enrolment; a person's own digest row kept and never recorded; a sync-written override the person later changed is kept on removal; removing a row removes only recorded overrides with no exception; members of a teaching space keep their overrides when its last mentor is removed, and a member added after that gets them; a member removed from the cohort raises no exception, keeps the override and its record, and on rejoin nothing new is written; a mentee whose delivery-course cohort enrolment is removed, the same; a sync-written override the person resets to their default is marked released and not written again on the next two runs; a teaching cohort deleted after its space opened: the members lose access, the mentor stays enrolled as `teacher` with their overrides and can read and post in both forums, the cohort-mentor row survives the reconcile task and its orphan-record removal, and the posts are kept, across two sync runs (round 2); delivery-course enrolments, roles and groups unchanged); observer test (clock set so the subscription lands one second after the post; cron queues the opening post for the mentor; a mentee's reply in another's discussion subscribes the mentor; no-op in a space); `course_mentor_rules` test (spacemember counts in a space, never in `ltct:<slug>`); `test_site_config.py` (validate rules in the community-yaml contract, including the reserved names, `space: area` accepted on an organisation entry and refused on `independent`, any other `space` value refused, and `enableportfolios: 1` refused; `teacher` export caps; `spacemember` caps); `test_ltct_admin.py` (space course ids `ltct:site:cohort:7`, `ltct:site:cohort:42` and `ltct:site:cohort:sil-americas` accepted with their own cohort; a row on `ltct:site:cohort:42` naming another cohort refused, and `ltct:site:cohort:sil-americas` with a cohort other than `ltct:org:sil-americas` refused); `course_mentor_records` PHPUnit test (the same two refusals).

## Summary line

`community: N spaces (teaching t, area a; created c, ok o, waiting w, blocked b, no moderator m, cohort deleted d), forums f`.
