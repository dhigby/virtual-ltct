# Implementation Plan: Partner organisations, cohorts and profiles

**Branch**: `specs/moodle-requirements` | **Date**: 2026-10-01 | **Spec**: [spec.md](spec.md)

## Summary

Each partner organisation is declared once, in `moodle/site/organisations.yaml`, by a stable key and a display name. Spec 001's applier turns that list into the same shape for every organisation: a course category, an organisation cohort and a managers cohort. The cohorts are site-level and hidden, so the site team can enrol them into any course but no organisation manager can browse them. Three kinds of profile field are declared in `moodle/site/profile-fields.yaml`: organisation, role in the work, and one checkbox per area of expertise. Country is Moodle's own field, and `settings/groups.yaml` pins its visibility (FR-008). The free `tool_dynamic_cohorts` plugin fills each organisation cohort from the organisation field. No country cohorts are created (spec Clarifications). One `orgmanager` role lets a manager follow their own people inside a course.

Separation comes from groups, not categories, because Moodle categories separate courses and not people (spec Clarifications). In each course, the site team enrols an organisation's cohort and its managers cohort into one group named for the organisation. Courses default to separate groups, and `orgmanager` holds no `moodle/site:accessallgroups`, so a manager sees only their own group. Organisation managers never create accounts, enrol anyone or edit the organisation field. The site team does those, later with spec 008's tooling.

Groups cannot tell a former member from a current one, because core keeps a suspended enrolment and its group. Two pieces close that gap (Clarifications 2026-10-01). Cohort sync removes roles on leaving, so a removed manager loses `orgmanager`. A small `local_ltuse` callback on core's profile-view hook, `local_ltuse_control_view_profile()`, refuses an organisation manager the profile of anyone whose organisation field is not one they manage. It is the one piece of our own code in this spec. It goes through a supported extension point and only ever takes access away.

The applier gains four item types: course categories, cohorts, profile fields and cohort rules. They are applied with the same rules as spec 001: create only what is absent, never delete, and report anything undeclared as `extra`.

## Project Structure

```text
moodle/
├── site/
│   ├── organisations.yaml          # new: shared categories and organisations (#8, #15)
│   ├── profile-fields.yaml         # new: the profile field category and its fields (#18)
│   ├── site.yaml                   # changed: + tool_dynamic_cohorts (pinned), enrol_cohort enabled
│   ├── roles.yaml                  # changed: + orgmanager
│   ├── settings/
│   │   └── groups.yaml             # new: separate groups by default; cohort sync suspends and removes roles (#8, #15)
│   └── README.md                   # changed: how to add an organisation; how to make someone a manager
├── local_ltuse/
│   ├── classes/siteconfig/
│   │   ├── categories.php          # new: check and apply course categories, with adoption (R6)
│   │   ├── cohorts.php             # new: check and apply cohorts (R1)
│   │   ├── profilefields.php       # new: check and apply the field category and fields (R5)
│   │   ├── cohortrules.php         # new: check and apply rules through tool_dynamic_cohorts (R4)
│   │   ├── inspector.php           # changed: hands the four new payload arrays to those classes
│   │   ├── applier.php             # changed: applies them, in the contract's order
│   │   ├── drift.php               # changed: reports undeclared ltct: categories, cohorts, rules and fields as extra
│   │   └── report.php              # changed: the new kinds adopted, ambiguous, wrong-context and wrong-datatype
│   ├── classes/profile_access.php  # new: the pure decision behind the profile hook (R9)
│   ├── lib.php                     # new: local_ltuse_control_view_profile(), gathers the decision's inputs
│   ├── version.php                 # bumped
│   └── README.md                   # changed: lists the tool_dynamic_cohorts API and the profile hook
└── REQUIREMENTS.md                 # rows 8, 15, 18 status updated on delivery
scripts/
├── site_config.py                  # changed: validate, expand and render the two new files
└── publish_moodle.py               # changed: sets separate groups on course update, not only on create
tests/
├── test_site_config.py             # changed: validation cases for the new files
└── profile_access_harness.php      # new: tests the decision without Moodle, like report_harness.php
.github/workflows/
└── site-config.yml                 # changed: also runs when competencies.yaml changes, since validate reads it
.gitignore                          # changed: a backstop pattern for learner CSV uploads (R8)
```

**Structure Decision**: The organisation list and the profile fields get their own top-level files beside `roles.yaml`. They are not settings, and `validate` needs to cross-check them: the organisation menu's options are generated from the organisation keys. Each new item type gets its own class beside `inspector.php`. That file is already 768 lines, and separate classes let the four be written independently. `inspector.php`, `applier.php` and `drift.php` only hand each payload array to its class.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. Organisations, categories, cohort definitions, cohort rules, fields and the role are declared. Drift reports undeclared ones and never writes back. |
| II. Portability | PASS. Every category and cohort is found by `idnumber` (`ltct:org:<key>` and the like), so a new server gets the same structure from `apply`. Memberships move with the data restore. |
| III. Public repo (NON-NEGOTIABLE) | PASS. The repo holds organisation keys, names and field definitions only. Who manages which organisation is a cohort membership set in Moodle (FR-012). Drift and apply report cohorts by name and never list members. A member count is not reported either. |
| IV. Disclosure boundary (NON-NEGOTIABLE) | PASS. Untouched. |
| V. CBC fidelity | PASS. The expertise areas are the category names of `competencies.yaml` except `Meta`, checked verbatim by `validate`. No level is recorded. |
| VI. No LMS orientation | PASS for learners: joining cohorts takes them no action. For managers the role is follow-only, with nothing to configure. It is proven simple only by SC-004. |
| VII. One shape | PASS. One shape per organisation is generated from one list entry, so it cannot differ. There is one manager role and no per-partner override. |
| VIII. Language data | PASS. Not applicable. |
| IX. Flat cost | PASS. `tool_dynamic_cohorts` is free (GPL, Moodle plugins directory). |
| X. Traceable and verified | PASS, with gates. The spec cites #8, #15 and #18. `tool_dynamic_cohorts` does not declare 5.2 support yet, so verifying it on the 5.2.3+ instance is a blocking task, with a named fallback (research R4). Separation inside a shared course is verified there with test accounts (R3). SC-004 needs real managers before #15 is marked done. The new recurring operation is the site team's enrolling, and spec 008 owns reducing it. |
| XI. Survives an upgrade | PASS. Core APIs: `core_course_category::create()`, `cohort_add_cohort()`, `cohort_update_cohort()`, `profile_save_category()`, `profile_save_field()`. Plugin API: `tool_dynamic_cohorts`' `rule` and `condition` persistent classes. Our own code uses core's `control_view_profile` callback, a supported extension point, and reads through `profile_user_record()`, `has_coursecontact_role()` and `has_capability()`. No direct writes. There are raw reads of stable core tables: `course_categories` by `idnumber`, `cohort` by `idnumber`, `user_info_category` by `name`, `user_info_field` by `shortname`, and `cohort` joined to `cohort_members` for the hook. Most of those columns are not indexed in core, so the constitution's indexed-read exemption does not cover them. Each is listed in `moodle/local_ltuse/README.md` with its reason, which is what Principle XI asks for. The hook cannot use `cohort_get_user_cohorts()`, which skips hidden cohorts (R9). |
| Platform: core first, pin plugins | PASS. Core cohorts, categories, fields, groups and cohort sync. One third-party plugin, pinned by version and sha256. One small piece of our own code, the profile hook, where core and no plugin can close the gap (R7, R9). |

Re-checked after Phase 1 design, and again after the 2026-10-01 task review added the profile hook: no change.

## Cross-spec effects

- **Spec 001**: its contract gains two files and four item types. This plan's [contracts/declaration.md](contracts/declaration.md) is the addition; spec 001's contract links to it.
- **Spec 008**: owns the site team's recurring work: bulk account creation with the organisation field, adding an organisation's cohorts to a course with its group, and adding someone to a managers cohort. Until it ships, these are admin-interface steps on learner data, which is allowed: they are memberships, not configuration.
- **Spec 004**: progress reports per organisation build on the organisation cohorts and the `orgmanager` role. It adds the grade and report capabilities this spec leaves out.
- **Spec 003**: a mentor who is also an organisation manager holds both roles. `orgmanager` uses no `prohibit`, so it never takes a permission away from another role. The profile hook exempts anyone with `moodle/user:viewalldetails` in the viewed learner's context, which is how a mentor reaches a mentee in Moodle.
- **Publisher**: `--category` still takes a numeric id. Categories now carry an `idnumber`, so accepting `ltct:published` instead is a small follow-on. It is not needed here.
- **INTENT.md**: the open question "How are partner organisations onboarded?" gets a decision entry dated 2026-10-01 when this spec is delivered.
