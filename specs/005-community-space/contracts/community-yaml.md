# Contract: `moodle/site/community.yaml`

Optional site file, registered in `TOP_FILES`. It declares the cohort space template, its two shapes, and the selectors for which Moodle cohorts get a space (Q2). It never names a person or a member, and names no teaching cohort; Area spaces are selected by the organisation entries' public keys.

Decisions applied: Doug, 2026-10-07, both rounds (plan.md, "Decisions" and "Round 2").

## Shape

```yaml
purpose: >-
  One standing space per teaching cohort and per opted-in Area entry, outside every
  delivery course (spec 005, purposes 3b and 3c).
space:
  idnumber_prefix: "ltct:site:cohort:"
  name: cohort                       # fullname and shortname are the cohort's own name, read live; never the key (round 2)
  role: spacemember                  # Q17; must exist in roles.yaml
  mentor_overrides:                  # course-context overrides for teacher in each space (round 2)
    - capability: moodle/course:manageactivities
      permission: allow
      why: The space mentor locks discussions by hand; drift reports forum-setting edits, apply restores them.
  guidelines: |
    Plain-language guidelines, set as each forum's intro (drift-compared), with a short
    pinned pointer. Do not judge, translate or correct anyone's language data. Remove
    posted quiz answers. To report a post, send its link to your mentor. An email reply
    is posted as written, signature included.
  forums:                            # exactly two (Q12); no lockdiscussionafter
    - key: team
      name: Team and friendship
      type: general
      trackingtype: optional
      maxattachments: 3
      maxbytes: 5242880              # total per post; R8
    - key: problems
      name: Problems and questions
      type: general
      trackingtype: optional
      maxattachments: 3
      maxbytes: 5242880
shapes:
  teaching:
    cohorts:
      match: <the teaching-cohort idnumber namespace 002 fixes>
    key: cohort_id                   # numeric Moodle cohort id (round 2)
    forcesubscribe: initial          # Auto, set at creation only
    mentor_digest:                   # per-forum override for recorded mentors (Q10)
      problems: 0                    # per post, answerable
      team: 1                        # daily digest
    member_digest:                   # members the same as the mentor (Q9, round 2)
      problems: 0
      team: 1
  area:
    cohorts:
      from_organisations: area       # entries in organisations.yaml declaring `space: area`
    key: organisation_key
    forcesubscribe: optional
    mentor_digest: none              # no override for anyone (Q2, round 2)
    member_digest: none
```

The site-wide space is deferred (Q1): there is no `site_wide` key, and validate refuses one.

## Opting in (`organisations.yaml`)

An organisation entry opts in to an Area space with one declared field, `space: area` (round 2). Without it, the entry has no space. Any entry may declare it, the same way (constitution VII: a uniform variant, not a per-partner case). In practice the entries that do are SIL's and SIL partners' Area entries (002 FR-014); `sil` and `sil-partner`, the holding entries, do not. Validate refuses `space: area` on `independent`. A spec 016 neutral entry must never declare it either (016 places protected people under such entries, and a space would put them in a participant list), but the repo never marks an entry neutral: a public marker would tell anyone which entry holds protected people, defeating the neutral-key rule (Principle III). So validate cannot check it. It is a review check (Doug, 2026-10-07, round 2): opting in is a reviewed one-line change, the reviewer confirms that the entry is not a neutral entry, and 016's procedure for a neutral entry says never to declare `space: area` on it (plan, follow-ups).

## Reserved names

The one list, cited from the plan's Constitution Check (I) and enforced by `site_config.py validate`, `check_course_package.py` and `tests/test_publish_moodle.py`:

| Kind | Reserved | Why |
|---|---|---|
| Course slug | `site` | `ltct:site:…` is the site-declared namespace; a course with this slug would collide with it |
| Course slug | `cohort-spaces` | the category idnumber `ltct:cohort-spaces` has the published-course form `ltct:<slug>` |
| Category key | `org` | existing: the prefix of every organisation category (spec 002) |
| Category key | `cohort-spaces` | the cohort spaces' category (Q3); declared once in `organisations.yaml`, and no other entry may take it |

## Rules (`validate` fails on any)

- The category is declared in `organisations.yaml` (`key: cohort-spaces`, idnumber `ltct:cohort-spaces`, visible), not here. The reserved names are those in the table above.
- Every derived space idnumber starts `ltct:site:cohort:`, contains a second colon, and is ≤ 100 characters.
- No derived idnumber matches `^ltct:[^:]+$` (published-course form) or ends `:discussion`.
- `shapes` has exactly the keys `teaching` and `area`; `teaching.key` is `cohort_id` and `area.key` is `organisation_key`.
- `teaching.cohorts.match` selects only 002's teaching-cohort namespace; never `ltct:mentors`, a managers cohort, or an `ltct:org:` cohort.
- `area` selects only entries declaring `space: area` in `organisations.yaml`. `space` takes only the value `area`. `independent` must not declare it; neutral entries are refused by the reviewer, not by validate (Opting in). Declared, not inferred. `_validate_organisations()` accepts `space` as the one optional key beside `key` and `name`.
- `space.name` is `cohort`: no template may put a key, id or code in a name users see (spec Identity).
- `forcesubscribe` is `initial` or `optional`; never `forced`. `initial` only in the teaching shape. Never forced or initial on a course forum.
- `mentor_digest` and `member_digest` values are `0`, `1` or `none`, keyed by declared forum keys only; the area shape's are `none`.
- `space.forums` has exactly the keys `team` and `problems`; no forum sets `lockdiscussionafter` (Q12).
- `space.mentor_overrides` grants only `moodle/course:manageactivities`, only `allow`, and only in space course contexts.
- `space.role` (`spacemember`) must exist in `roles.yaml` with `contextlevels` including course; it must not set `mod/forum:allowforcesubscribe`, `moodle/course:viewparticipants`, `moodle/user:viewdetails` or `moodle/site:sendmessage` to prevent or prohibit (Q6).
- No `groupmode` key exists; spaces are always group mode 0.
- No `site_wide` key (Q1, deferred).

## Derived, not declared

- `key` from the matching Moodle cohort at run time: teaching, the numeric cohort id; area, the organisation key. Space course idnumber `<idnumber_prefix><key>`; forum idnumbers `<space>:<forum key>`. A key is never shown to users.
- The space's fullname and shortname: the Moodle cohort's name (teaching) or the entry's `name` (area), read live and kept in step; never written to the repo. A name already used as another course's short name blocks the space (`blocked: name_taken`).
- The enrol instance from the cohort; mentors from 008 cohort-mentor records, made by the site team through `ltct_admin.py` (Q4, round 2), never from this file.
