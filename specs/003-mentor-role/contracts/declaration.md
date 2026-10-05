# Contract: site declaration additions (spec 003)

This extends [spec 001's declaration contract](../../001-site-config-as-code/contracts/declaration.md)
and [spec 002's additions](../../002-org-structure-cohorts/contracts/declaration.md). Only what
is new is stated here. Spec 001's rules still apply to all of it: apply only what differs,
never delete, and report what is undeclared.

## `roles.yaml`: the `mentor` role

```yaml
  - shortname: mentor
    name: Mentor
    description: >-
      Follows the learners assigned to them, across every course they take, for as long as
      the relationship lasts. Changes nothing. Assigned in a learner's own profile
      (Preferences > Assign roles relative to this user), never in a course. Declared in
      moodle/site/roles.yaml and applied by scripts/site_config.py -- edit that file rather
      than this role.
    archetype: ""            # every capability is managed
    contextlevels: [user]    # a relationship is with a learner, not an enrolment (FR-002)
    capabilities:
      moodle/user:viewdetails: allow                 # open the learner's profile
      moodle/user:viewuseractivitiesreport: allow    # core Grades overview, read-only
      local/ltuse:viewmenteeprogress: allow          # the Mentoring page (research R3)
      local/ltuse:viewidentity: allow                # spec 016 R7 path 2 (added by 016)
    why: >-
      #11 (spec 003). ...
```

*(Amended 2026-10-05: spec 016 added `local/ltuse:viewidentity`, so an assigned mentor sees a
protected learner's real identity and the Protected marker while assigned (016 FR-006, R7 path
2). It is a reviewed widening of the allowlist below; `moodle/site/roles.yaml` (around
:198-202) and `MENTOR_ALLOW` in `scripts/site_config.py` (around :316-320) carry it with their
reason.)*

**Validation** (`site_config.py validate`, new `_check_mentor`). Each of these is a problem,
and `validate` exits 1:

| Check | Message names |
|---|---|
| `contextlevels` is not exactly `[user]` | FR-002 |
| `archetype` is not empty | research R2 |
| any capability is `prohibit` | roles combine; a mentor who is a manager keeps both |
| any capability outside the allowlist `{moodle/user:viewdetails, moodle/user:viewuseractivitiesreport, local/ltuse:viewmenteeprogress}`, plus `local/ltuse:viewidentity` since spec 016 (R7 path 2; a reviewed widening, `MENTOR_ALLOW` in `scripts/site_config.py`) | FR-006, FR-013, research R2 |
| the `mentor` role is missing while `local_ltuse` is pinned at or after the version that adds the capability | FR-001 |

**Inspector, applier and drift**: no change. They already handle a role with an empty
archetype and `contextlevels: [user]`. `local/ltuse:viewmenteeprogress` exists once the plugin
version that defines it is installed. `apply` already installs the pinned plugin before
writing roles (spec 001 order), so the capability exists by then.

## `roles.yaml`: the `allowassign` key (new, any role)

```yaml
  - shortname: manager
    archetype: manager
    allowassign: [mentor]      # the site team assigns mentors in a learner's profile (R6)
    capabilities:
      moodle/site:trustcontent: allow
```

| Rule | |
|---|---|
| Each entry names a role declared in `roles.yaml` or a core archetype shortname | validate |
| No duplicates | validate |
| `orgmanager` and `mentor` must not carry `allowassign` | validate (deny) |
| **Apply**: for each pair with no `role_allow_assign` row, call `core_role_set_assign_allowed($fromroleid, $targetroleid)` | applier |
| **Drift**: report a declared pair whose row is missing as `changed` on the role. Undeclared rows are not reported. | drift |

- **Payload**: each rendered role gains `allowassign: [<shortname>, …]`, an empty list when
  absent.
- **Report kind**: there is no new top-level kind. A missing pair is a property of the role, in
  the same way a capability difference is.

## `settings/mentoring.yaml` (new)

```yaml
# Row #11: what a mentor may see stays bounded.
rows: [11]
purpose: >-
  A mentor follows a learner through progress pages that core only opens to them when a
  course allows reports. Allowing reports would also show the mentor every assignment
  submission and the learner's logs, so it stays off.
settings:
  - name: moodlecourse/showreports
    value: 0
    why: >-
      Course default for "Show activity reports". With 1, a mentor holding
      moodle/user:viewuseractivitiesreport sees the Complete report (assignment submissions
      in full) and the logs (spec 003 research R2).
```

The setting is `moodlecourse/showreports` in `config_plugins` (research, Source results T003).
`drift` already reads `config_plugins`.

## Phase B only: `organisations.yaml` gains the mentors cohort

```yaml
mentors_cohort:
  idnumber: ltct:mentors
  name: Mentors
  visible: false
```

It is rendered with the same cohort item type as spec 002's cohorts, so no new applier code is
needed. Membership is Moodle data and is never declared.
