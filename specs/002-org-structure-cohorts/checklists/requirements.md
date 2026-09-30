# Specification Quality Checklist: Partner organisations, cohorts and profiles

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-09-30
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [ ] No [NEEDS CLARIFICATION] markers remain
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

- Items marked incomplete require spec updates before `/speckit-clarify` or `/speckit-plan`
- Validated in two passes (first pass tightened FR-003 on shared vs organisation categories and added FR-012 so manager assignments never enter the repo).
- One [NEEDS CLARIFICATION] remains, in FR-013: whether an organisation manager may create accounts for their own learners, or account creation stays with the site team. INTENT lists partner onboarding ("whether a partner can enrol its own learners without us") as open, and the answer changes the manager role's scope at site level.
- Deliberately left: whether automatic cohort filling needs a plugin on 5.2 (plan, verified on the temp instance); bulk account/enrolment tooling (008); data-protection position (INTENT open question) — fields kept minimal meanwhile.
- SC-004 applies constitution X's real-user rule to organisation managers because the manager scope is admin UX.
