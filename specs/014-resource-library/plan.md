# Implementation Plan: Searchable Resource Library

**Branch**: `014-resource-library` | **Date**: 2026-10-06 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/014-resource-library/spec.md` (row #24)

## Summary

Consultants get one place to find reference material outside any course, on the public competency site (FR-009, decided 2026-10-06). Every resource becomes one entry in a new `resources.yaml` at the repo root, carrying its title, link, one-line description, type, competencies and language. The seed lists move there from the descriptors' `resources:` field.

`gen_site.py` builds two things from that file:
- a library page, one heading per resource, which the site's built-in search indexes;
- each competency page's Further Information section, filtered to that competency.

The existing descriptor check fails the build on an unknown competency name. A weekly workflow reports broken links in a GitHub issue. Moodle gets a "Library" menu item in the browser and the app, declared in `moodle/site/`.

## Project Structure

```text
resources.yaml                              # new: the library's source of truth
competencies/*.md                           # resources: lists removed (moved, not copied)
scripts/gen_site.py                         # library page + Further Information from resources.yaml
scripts/check_competency_descriptors.py     # validates resources.yaml
scripts/check_resource_links.py             # new: link check, stdlib only
tests/                                      # tests for the two script changes
.github/workflows/competency-descriptors.yml  # trigger gains resources.yaml
.github/workflows/resource-links.yml        # new: weekly link check, opens one issue
moodle/site/settings/learner-experience.yaml  # "Library" menu item (browser + app)
moodle/REQUIREMENTS.md                      # row #24 status (constitution X)
CLAUDE.md, CONTRIBUTING.md                  # where resources live now
```

**Structure Decision**: Everything extends the existing site build and its check. The only new files are the resource list, the link checker and its workflow.

## Constitution Check

| Principle | Assessment |
|---|---|
| I. Source of truth | PASS. `resources.yaml` is the only copy; the site is generated from it, one-way (FR-010). |
| II. Portability | PASS. Plain YAML; the Moodle link is a declared setting. |
| III. Public repo, private people | PASS. No learner data; restricted resources out of scope. |
| IV. Disclosure boundary | PASS. The library holds external references only, never `modules/` files (FR-012). |
| V. CBC fidelity | PASS. Names checked against `competencies.yaml`; competency page URLs don't move (FR-011). |
| VI. No git, no LMS orientation | PASS. Contributors add an entry through Claude Code; one tap from Moodle. |
| VII. One shape, gated stages | PASS. No course content touched. |
| VIII. Language data | PASS. Descriptions of a language's script or orthography come from a human; seed descriptions for those are left as marked placeholders. |
| IX. Flat cost, field-ready | PASS. No new service; text-only page (R8). |
| X. Traceable and verified | PASS, with work: row #24 updated in the same PR. SC-001 needs 2–3 real partner users before done. |
| XI. Survives an upgrade | PASS, with a gate: both menu settings are confirmed in `MOODLE_502_STABLE` source before use (R7). |

Re-checked after Phase 1 design: unchanged.

## Key risks

- **Seed descriptions.** The roughly 100 existing links have no description. Writing them is real work, and a wrong one misleads. Each is drafted from the linked page's own summary and reviewed by the maintainer.
- **App menu.** If `tool_mobile` has no custom menu setting on 5.2, the fallback is a route in `block_ltuse` (R7), which is plugin code with tests.

## Contract details settled in Phase 1

The contracts fix four details research left open:
- the link checker exits 0 when every link works, 1 on broken links and 2 when `resources.yaml` is missing; it keeps one issue open, titled "Broken resource links";
- library page headings run category `##`, competency `###`, resource `####`;
- a resource listed under several competencies keeps its plain anchor at its first appearance;
- a renamed resource title keeps its old anchor working, as FR-011 does for moved pages.
