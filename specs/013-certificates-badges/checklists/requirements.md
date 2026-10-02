# Specification Quality Checklist: Completion Badges and Certificates

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-09-30
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

- Validated in one pass. No [NEEDS CLARIFICATION] markers: defaults recorded in Assumptions (per-course badges only; course-completion criteria only, no competency-based badge criteria; pilots issue no badges; badges private by default).
- The certificate plugin is deliberately not named; core has badges but no certificates, so the plan chooses and pins one after checking Moodle 5.2 support (constitution X).
- The issuing programme's exact name and logo are left for the maintainer at plan time.
- SC-003 is a "simple" criterion: needs 2–3 real partner learners before marked done.
- Items marked incomplete require spec updates before `/speckit-clarify` or `/speckit-plan`
