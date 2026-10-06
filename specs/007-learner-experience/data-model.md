# Data Model: Simple Learner Experience

**Spec**: [spec.md](spec.md) · **Research**: [research.md](research.md)

This feature adds no table. Everything it needs is either declared in `moodle/site/` or
computed at render time from data Moodle and local_ltuse already hold. Learner data is read
inside Moodle to render a page and is never written to the repo (Principle III).

## 1. Learner landing page (declared)

The default Dashboard, declared in `moodle/site/dashboard.yaml` and the settings it depends
on. Shape: [contracts/dashboard-declaration.md](contracts/dashboard-declaration.md).

| Field | Value | Source |
|---|---|---|
| `defaulthomepage` | `1` (Dashboard) | `settings/learner-experience.yaml` (R1) |
| `enabledashboard` / `enablemycourses` / `enablemyhome` | `1` / `0` / `0` | same (R1) |
| default-page blocks | `ltuse` (content 0), `myoverview` (content 1), `calendar_upcoming` (side-pre) | `dashboard.yaml` (R2) |
| `block_myoverview/displaygrouping*` | All, In progress and Past on; the rest off | `settings/learner-experience.yaml` (R2) |
| `moodle/my:manageblocks` for `user` | `prevent` | `roles.yaml` (R4) |
| `theme_boost/brandcolor` | `#005CB9` (SIL Blue) | `settings/learner-experience.yaml` (R11) |
| `supportavailability` / `supportemail` | `1` / `env:MOODLE_SUPPORT_EMAIL` | same (R9) |

**Validation** (`site_config.py validate`):
- `dashboard.yaml` names each block once.
- Each declared block is installed or declared in `site.yaml`.
- `ltuse` is declared at content weight 0.

**State**: `drift` reports
- a declared block missing from the default page;
- an undeclared block present on it;
- personal dashboards remaining (a count);
- any declared setting that differs.

`apply` adds what is missing, removes what is undeclared, and resets leftover personal
dashboards (R4). It never touches anything else.

## 2. Learner state (computed, per render)

What `block_ltuse` derives for the viewing user. It is never stored.

| Field | Derived from |
|---|---|
| `courses_active` | the user's active enrolments in visible courses (`enrol_get_my_courses`) |
| `continue_course` | the most recently accessed incomplete course with completion on (R3) |
| `continue_cm` | that course's first visible, trackable, not-complete `cm` in course order |
| `mode` | `continue` · `start` · `empty` · `done` (see below) |
| `next_pathway_courses[]` | `local_ltuse\pathway\view::next_course()` for each pathway the user holds (spec 006) |
| `mentors[]` | local_ltuse's mentoring relationship (spec 003): the id and display name of each mentor, with their message URL |
| `community_url` | the community space, once spec 005 declares one; otherwise absent |

**Modes** (one at a time):

```text
no active enrolment ............................... empty
an incomplete course, some cm complete ............ continue  (button: "Continue: <cm>")
an incomplete course, nothing complete yet ........ start     (button: "Start: <first cm>")
every course complete ............................. done      (no button; onward routes lead)
```

Onward routes (`next_pathway_courses`, `mentors`, `community_url`) show beneath any mode
when present. Each is absent, never empty or broken, when its source has nothing (spec
assumption: FR-008 degrades).

The block renders nothing for a user with `moodle/site:config` (R12).

## 3. Course view (published)

One course as the publisher already makes it (`format: topics`, one section per lesson),
plus one field.

| Field | Value | Source |
|---|---|---|
| section `name` | the lesson's H1 | `moodle_payload.py` (existing) |
| section `summary` | the lesson's own `**Estimated time:** N minutes` line, rendered | `moodle_payload.py` `time_text` (new, R10) |
| `cm` completion | page: on view; quiz: per spec 004 | existing |
| `showcompletionconditions` | `1` | `settings/completion.yaml` (existing) |

**Validation**: A lesson with no `**Estimated time:**` header already fails
`check_course_package.py`, so `time_text` is never empty for a pipeline course. For a
backfilled course without one, the summary is sent empty, which clears it. That is a
faithful copy of the source, never a made-up time.

**Identity**: unchanged. Sections are found by number within `ltct:<slug>`.

## 4. Next-lesson route (computed, per render)

Rendered by local_ltuse's `after_standard_main_region_html_generation` callback (R6).

| Field | Derived from |
|---|---|
| applies | page layout `incourse`, context is a module, and the course `idnumber` starts with `ltct:` |
| `next_cm` | the first `cm` after the current one in `get_fast_modinfo()->get_cms()` order that is `uservisible`, not stealth and has a URL (core's own rule) |
| link | `next_cm->url`, or the course page when there is none |

## 5. Pilot finding (recorded in the repo, de-identified)

One observation from a real partner learner. These are kept in
`specs/007-learner-experience/pilot-findings.md`, which is created at the first pilot.

| Field | Rule |
|---|---|
| `id` | `F<n>`, in order of recording |
| `when` | date of the session (no time of day) |
| `who` | role and context only: "partner learner, organisation A, first Moodle use". The organisation is a letter assigned in the file, never its key or name if that could identify the person, and never a region (Principle III, 016). |
| `device` | "web, laptop" · "app 5.2.x, Android phone" … |
| `tried` | what the learner set out to do |
| `happened` | where they went, where they stalled, in observable terms |
| `measure` | which success criterion it bears on (SC-001 … SC-004), and the observed value, e.g. "first lesson at 3 min" |
| `outcome` | `resolved` (with the commit or PR that fixed it) · `accepted` (with the reason and who accepted) · `open` |

**State transition**: `open` → `resolved` | `accepted`. Row #13 cannot be marked done while
any finding is `open`, or while fewer than two learners are recorded (FR-014, SC-006).

**Never recorded**: names, email addresses, usernames, account ids, screenshots showing a
person, or progress data beyond the one measured value.
