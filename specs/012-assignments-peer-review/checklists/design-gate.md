# Design-gate checklist: Assignments and peer review

**Purpose**: Confirm [design.md](../design.md) answers what US1 asks before the maintainer decides
**Created**: 2026-10-01
**Feature**: [spec.md](../spec.md)

## Functional requirements

- [X] FR-001 (approval before any build): the Status line and the decision record; the CI guard in `check_course_package.py` (R8)
- [X] FR-002 (plain markdown authoring): D2, with the full grammar in [contracts/assignment-file.md](../contracts/assignment-file.md)
- [X] FR-003 (learner-visible vs mentor-only, mentor-only by default): D1, "What is learner-visible and what is mentor-only"
- [X] FR-004 (one disclosure definition, fails closed): D3, `## Mentor only` in `disclosure.py`, withheld whole on doubt
- [X] FR-005 (production stages): D4
- [X] FR-006 (identity in Moodle): D5
- [X] FR-007 (reviewer and learner views): D7
- [X] FR-008 (republish never touches learner work): D8, discussion posts included
- [X] FR-008a (optional or required, and the default): D2, default `optional`

## Acceptance scenarios

- [X] US1-1: D1 compares three options on disclosure safety, raw readability, contributor simplicity and publisher impact
- [X] US1-2: D1 names grading notes and model answers mentor-only by default, and lists every learner-visible part
- [X] US1-3: D3 adds the rules to the existing definition and withholds an unseparable assignment whole
- [X] US1-4: the guard fails CI on any `*-assignment.md` with "assignments wait on the spec 012 design approval" (T012)

## Independent test

- [X] A recommendation (A) and the rejected alternatives (B, C) with reasons
- [ ] The maintainer's recorded decision and date (T014)

## Notes

- Edge case "an assignment is removed from the source" is answered in D8: it is hidden, never deleted.
- Fixed while checking: the "What this design changes" list now names `site.yaml`, `04-alignment.md`, CLAUDE.md and the CI workflow, which the plan also changes.
