# Contract: Site declaration additions

These additions extend spec 001's declaration. They are validated, applied and drift-checked by `scripts/site_config.py`, and they follow [spec 001's declaration contract](../../001-site-config-as-code/contracts/declaration.md).

## `moodle/site/course-discussions.yaml` (new)

*(Retired 2026-10-02 by spec 002 R14: every course forum runs with no groups, `load_discussions()` is removed, and `scripts/site_config.py` refuses the file. Drift still reports a forum whose group mode is not "no groups" (`differs`) and a course without one (`missing`). The `allparticipants` warning below was removed with it (002 R14); `forced` stays as a warning. Kept as the record of the Phase A contract; spec 012 is parked for re-plan (Doug, 2026-10-05).)*

```yaml
# Row #10: which courses' discussion spaces are shared across organisations.
# Every published course has one; it is separated by organisation unless listed here.
# Only the maintainer edits this file (FR-015). Never set this in course frontmatter.
rows: [10]
shared:
  - slug: coretech-computer-hardware
    why: Generic hardware Q&A with no partner data; partners asked to pool answers (2026-..).
```

Validation (`site_config.py validate`, in CI):
- Each `slug` exists under `modules/` (compared with `course_stage.branch_slug()`).
- `why` is required.
- No duplicate slugs.
- An empty list is valid and is the default.

`moodle_payload.py` reads this file through the same loader (`site_config.load_discussions()`), so the payload's `discussion.shared` and the drift check can't disagree.

Drift (`site_config.py drift`) reads, for every course with an `ltct:` idnumber, the group mode of its `ltct:<slug>:discussion` forum. It reports:

| Kind | Meaning |
|---|---|
| `differs` | the live group mode is not what the declaration implies (US4 scenario 3) |
| `missing` | the course has no discussion forum (it has not been republished since this spec) |
| `forced` | course-level `groupmodeforce` overrides the forum (warning) |
| `allparticipants` | a separated forum contains a discussion with `groupid = -1` (warning; only the count is reported, never its content, R5) |

`apply` corrects `differs` through `local_ltuse_ensure_discussion`'s code path. It never creates a missing forum; that is the publisher's job.

## `moodle/site/roles.yaml` (additions)

```yaml
  - shortname: student
    archetype: student
    capabilities:
      mod/workshop:viewauthornames: inherit   # FR-012a: peers never see who wrote what (R4)
    why: >-
      #22: peer review is anonymous between learners. The student archetype allows author
      names by default; removing it at system context hides them everywhere.

  - shortname: teacher          # "Non-editing teacher"; called "Course mentor" in our docs, not renamed in Moodle
    archetype: teacher
    capabilities:
      moodle/site:accessallgroups: inherit   # the archetype default; listed so drift manages it.
                        # Defaults already give assign:grade, workshop allocate/switchphase/overridegrades
                        # and viewhiddenactivities (R3). Only listed capabilities are drift-checked.
    # Superseded 2026-10-05, this why: see the note below this block.
    why: >-
      #22: the course-level role that assesses work. Its archetype defaults are exactly what
      FR-010/FR-013 need, and lacking accessallgroups is what keeps a mentor inside their
      organisation's groups. Declared so drift catches a hand-added accessallgroups.

  - shortname: ltcpublisher
    capabilities:
      moodle/grade:managegradingforms: allow  # upsert_assignment writes the marking guide (R1)
      moodle/role:safeoverride: allow         # create_page prohibits mod/page:view on mentor notes
      moodle/course:managegroups: allow       # read-only use: drift reads group ids; no group is written
```

*(2026-10-05: the `teacher` block's `why` is superseded. There are no organisation groups (spec 002, 2026-10-02), so lacking `accessallgroups` keeps a mentor inside nothing course-wide. Separate groups on an assessed activity limit grading there to spec 008's mentor groups, and the course itself is open to a course mentor: Teacher's Moodle 5.2 defaults reach every learner in a group-mode-0 course. Accepted (Doug, 2026-10-05): we trust people who are in the system. Re-plan inputs 1 and 3. The live declaration, with its current `why`, is the `teacher` entry in `moodle/site/roles.yaml` (:154).)*

The `ltcpublisher` additions are reviewed against least privilege when this is implemented. `moodle/role:safeoverride` is granted, not `moodle/role:override`: it lets the account override only capabilities with no risk bit, and `mod/page:view` has none (MOODLE_502_STABLE `mod/page/db/access.php`). The rejected alternative to granting it is relying on `visible = 0` alone. Both locks are kept, because the cost of one capability is small next to a model answer reaching learners.

## `moodle/site/site.yaml` (addition)

`workshopallocation_orgcohort` is listed as **our own plugin** (no third-party pin), with the version its `version.php` declares. `site_config.py` verifies that it is installed at that version.
