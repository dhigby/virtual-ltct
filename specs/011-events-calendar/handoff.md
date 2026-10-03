# Spec 011 handoff: decisions needed before the plan

**For**: Doug (maintainer) · **From**: Matthew, with Claude Code · **Date**: 2026-10-02 · **Spec**: [spec.md](spec.md) · **Evidence**: [research.md](research.md) (calendar, booking) and [org-boundaries.md](org-boundaries.md) (open courses)

`/speckit-plan` for 011 stopped before writing the plan, for two reasons:

- Two of the spec's assumptions fail against what spec 002 built.
- You've since given a direction that changes 002 itself: competency courses run across organisations (most mentors will be SIL), with no organisational walls inside a course, because cohorts are small. An organisation can host a course only for its own people, as the exception, and organisation leaders can still manage their people in Moodle.

## Decision record

**Approved by Doug, 2026-10-02 (relayed by Matthew).** The recommendations below are accepted as written, with these clarifications from Doug:

- **Things are open by default.** Students see all their classmates. Course leaders see their students. Mentors see and interact with their mentees, across organisations.
- **Organisation managers can see *and manage* their users.** This changes **B5**: managers manage, rather than follow only. B3 (a user-context follow role) and D3 (managers posting events) are therefore no longer "wait for Phase B". What "manage" covers is scoped in the Part 1 change, against core's limit that enrolment selectors search every site user.
- **Identity protection gets its own spec.** Some users need extra protection of their identity because of where they work: a hidden email address, first name only, or a pseudonym. That must not block them taking part in Moodle. Open courses make this more urgent, so that spec runs alongside the Part 1 change.

That direction is bigger than events, so this handoff has two parts. Part 1 is the cross-cutting change. Part 2 is what's left to decide for 011 once Part 1 is settled. When you've answered, the 011 plan, data model, contracts and quickstart follow in this PR.

## Part 1. Open courses, with organisation-only courses as the exception

### What it takes, in brief ([org-boundaries.md](org-boundaries.md) has every file and line)

- **Shared courses default to no groups.** `moodlecourse/groupmode` goes from 1 to 0, and the publisher stops sending `groupmode: 1` on every publish. It does that today, so the wall comes back on each publish. Spec 003's branch carries the same code.
- **Managers stop being enrolled in shared courses.** Today, `orgmanager` sees only its own organisation because separate groups hide everyone else. With no groups, the same role would see every organisation's participants, completion and email addresses. Leaders follow their people instead through **spec 004's per-organisation reports**, which already filter by the `ltct_org` profile field rather than by group. Optionally, a **user-context follow role** on each of their learners would add per-learner detail. Core's `tool_cohortroles`, or our own observer, could keep that role in step with the managers cohort.
  - The cost: in shared courses, managers lose core's per-course progress and completion reports, which don't show per-lesson ticks. Spec 004 FR-006 would need relaxing.
- **The profile hook gets narrower.** It currently stops a manager who is also a learner from opening a classmate's profile in a shared course. That is a wall, so it would apply only to organisation-only courses and the manager relationship.
- **Spec 012 gets simpler.** Course discussions no longer need separating by organisation, `course-discussions.yaml` retires or inverts, and the planned organisation-aware workshop allocator is probably unnecessary. But cross-organisation peer review needs a rule for partner data in submissions.
- **Organisation-only courses already fit the design.** Such a course lives in the organisation's category `ltct:org:<key>`, and only that organisation's cohort is enrolled. It needs a declared host so the publisher puts it in the right category and keeps it there; the publisher currently takes a bare `--category` on create only. The course content stays in the public repo, so "only within their organisation" means enrolment, not confidentiality.
- **"Leaders manage their own folks"** works today as *following* through reports, plus Phase B items such as assigning mentors (003). Letting leaders *enrol* their own people needs our own page, because core's enrolment selectors search every user on the site.
- **The governing text changes.** That means a new 2026-10-02 INTENT decision superseding the in-course part of 2026-10-01, and a constitution amendment ("separated by" → "identified and scoped by", with org-only as one uniform variant). Spec 002 gets a clarification, 003/004/012/013 get amendments, and REQUIREMENTS rows #7, #8, #10, #11 and #15 go back to "re-verify".

### Decisions for Part 1

| # | Decision | Recommendation |
|---|---|---|
| B1 | Shared-course group mode: none (0), or visible groups (2) with organisation names kept as labels? | **0.** Labels in visible groups would let any course-context reader page through every organisation's data. |
| B2 | Managers cohorts no longer enrolled in shared courses; leaders follow through 004's per-organisation report. Accept losing per-lesson detail there? | **Yes.** |
| B3 | Add the user-context follow role now, or wait until leaders ask for per-learner detail? | **Wait.** The report covers following. Add the role in Phase B with manager self-service. **Answered 2026-10-02: no role; the profile hook and a "my organisation" page instead (spec 002 Clarifications 2026-10-02).** |
| B4 | Where is an organisation-only course declared: course frontmatter, or a maintainer-only `moodle/site/` file? Who approves one, and who enrols it? | **`moodle/site/`**. Who may enrol a partner's people is delivery policy, not content, as `course-discussions.yaml` already treats sharing. Approval rests with the maintainer, and the site team enrols. |
| B5 | Should leaders enrol their own people (our own org-scoped page), or keep following only, plus 003 Phase B? | **Follow only for now.** Reconsider after 2–3 real managers, as INTENT already says. **Changed by Doug, 2026-10-02: managers manage — enrol and unenrol, reset links, mentors, suspend and reactivate (spec 002 Clarifications 2026-10-02).** |
| B6 | How should this land? | **As its own change**, a spec 002 amendment plus INTENT and constitution, ahead of 011's plan. 011 then plans on open courses. Bundling it into 011 would bury a platform decision inside an events spec. |

## Part 2. Spec 011 under open courses

Open courses settle most of the 011 questions. A cross-organisation event is now just a **course event** in a shared course, or a **site event**. Neither group events nor spec 005's community course are needed to work around walls. An organisation-only course's events reach only that organisation, by enrolment.

### Calendar findings that still stand ([research.md](research.md))

1. **Avoid core category events.** They reach only people enrolled in a course in that category, and they leak: a learner's calendar export ("All") carries every visible category's events, and `calendar/view.php?category=<id>` shows them to anyone. Creating one needs `moodle/category:manage`. (R2)
2. **Core sends no notification** when an event is created, changed or cancelled. FR-006 needs a small `local_ltuse` observer and a message provider. Email is the route, because push stays off until 015. (R6)
3. **Core never shows the time zone** beside an event time, although times convert correctly. (R5)
4. **`mod_scheduler` v5.2-r1 supports 5.2, but booking is browser-only.** The app offers "Open in browser" for it. It also shows learners who else booked unless `roles.yaml` turns that off, and it knows only course teachers and groups, while 003's mentor is a user-context role. (R9)
5. **Export, subscribe, repeats and the app calendar are core and work.** Timeline never shows hand-made events, and an Upcoming events block is not on the default dashboard. (R7, R8)
6. **A meeting link goes in the event description.** The app turns `location` into a maps link. (R10)

A second, independent pass re-checked the 23 source claims these findings rest on. 21 held exactly, and 2 needed small corrections, which are now in research.md.

### Decisions for Part 2

| # | Decision | Recommendation |
|---|---|---|
| D1 | Amend US1-2, FR-001 and SC-001 from "never see another organisation's events" to "events are open across organisations; an organisation-only course's events reach only its organisation". | **Yes**, following Part 1. |
| D2 | **Organisation-wide announcements** (one organisation's people across all their courses) have no course home in open courses. Options: the organisation's room in 005's community space, a message to the organisation cohort, or not in 011. | **Not in 011 for now.** Use a message, and return to it with 005. |
| D3 | **Managers posting events** (US2, FR-005) conflicts with INTENT 2026-10-01. Phase A: the site team and course mentors post events. Phase B, after INTENT reopens: managers post in their own organisation-only courses. | **Phase B**, as 003 did. Matthew chose this on 2026-10-02, pending your confirmation. |
| D4 | **Do bookings stay private, even with open courses?** Who booked a mentor's slot is visible only to that mentor and entitled managers (FR-008). | **Yes.** It is learner data. The scheduler's default of showing co-bookers is switched off in `roles.yaml`. |
| D5 | **Showing the time zone** (FR-002): build it (a child-theme template or our own block), or relax FR-002 to "the learner's zone is set at first login and shown on their profile". | **Relax** for now, and revisit with 007's dashboard. |
| D6 | **Office hours**: accept browser-only booking. Without organisation groups, how does a learner see *their* mentor's slots? Options: a group per mentor, kept in step with 003's assignments; or open slots any learner in the course may book. | **Accept browser-only, with a group per mentor**, and design the sync in the plan. Open slots are simpler if a mentor's slots are meant for anyone in the course. Tell us which. |
| D7 | **The site's default time zone** (`$CFG->timezone`), used for learners who haven't set one. | **UTC.** |

These are not in dispute and go into the plan as proposed:

- the meeting link in the event description (R10);
- the notification observer (R6);
- unaided export (R7);
- BigBlueButton stays disabled (FR-013);
- `mod_attendance` is left out until a partner asks (R11).

## Cross-spec effects

- **002, 003, 004, 012, 013**: Part 1 amends each one. See org-boundaries.md §2. Spec 003's branch has to take the groupmode change before it merges.
- **005**: its per-organisation rooms stay. Removing groups from delivery courses doesn't remove them from the community space.
- **007**: FR-003 needs an Upcoming events block on the dashboard 007 trims, and no Timeline.
- **015**: push stays off, so email is the dependable route. The scheduler pin is one more plugin for the operator to keep current.

## How to reply

Comment on this PR with one line per decision, e.g. `B1 0 · B2 yes · ... · D6 group per mentor`. Matthew will then open the Part 1 change and rerun `/speckit-plan` for 011 here.
