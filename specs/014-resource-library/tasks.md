# Tasks: Searchable Resource Library

**Input**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/` in `specs/014-resource-library/`

Line format: `- [ ] **T###** [P?] [US#] Description · file`

Stories 1 and 2 share the resource file, its check and the site generator, so those are built once in Phase 2. Phases 3 and 4 then prove each story against its acceptance scenarios.

## Phase 1: Setup

- [x] **T001** Confirm in Moodle core source on `MOODLE_502_STABLE` the name, format and app support of the browser custom menu setting (`custommenuitems`) and the app main-menu setting (`tool_mobile` `custommenuitems`). Context7 first, then upstream source (Principle XI, research R7). Write the finding, and whether the `block_ltuse` fallback is needed, into research.md R7 · specs/014-resource-library/research.md

## Phase 2: Foundational (blocks every story)

**Wave 1, independent (different files):**

- [x] **T002** [P] Write failing tests for `resources.yaml` validation: unknown competency name, missing field, bad `type`, non-http url, and a valid file passing · tests/test_resources_yaml.py
- [x] **T003** [P] Write failing tests for the library page and Further Information rendering: grouping by category then competency, `####` resource headings with description, type and language, first-occurrence anchors, and a competency with no resources leaving its page unchanged · tests/test_gen_site_library.py

**⟶ Wait for Wave 1 to finish, then:**

**Wave 2, independent (different files):**

- [x] **T004** [P] Validate `resources.yaml` against `contracts/resources-yaml.md` and `competencies.yaml`. Drop the descriptor `resources:` check and reject a descriptor that still carries `resources:`, so the list cannot creep back · scripts/check_competency_descriptors.py
- [x] **T005** [P] Build `library.md` from `resources.yaml` (nav entry, headings per `contracts/site-routes.md`, text only, R8). Rebuild `resources_section()` to read the entries naming that competency and show type and description. Competency page URLs stay unchanged (FR-011) · scripts/gen_site.py

**⟶ Wait for Wave 2 to finish, then:**

- [x] **T006** Move every `resources:` list out of `competencies/*.md` into `resources.yaml`, merging one url used by several competencies into one entry. Draft each description from the linked page's own summary. Where a description would state a named language's script or orthography, leave a marked placeholder for a human (constitution VIII). Language defaults to `en` only where the page is English · resources.yaml, competencies/*.md
- [x] **T007** Add `resources.yaml` and `scripts/gen_site.py` to the descriptor check's triggers, and `resources.yaml` to the Pages build trigger if it filters by path · .github/workflows/competency-descriptors.yml, .github/workflows/pages.yml

**Checkpoint**: `python scripts/check_competency_descriptors.py` passes, tests T002–T003 pass, and `mkdocs build` (strict) produces `/library/`.

## Phase 3: User Story 1, find a resource by searching (P1)

Files: specs/014-resource-library/quickstart.md

- [x] **T008** [US1] Write the search check: 10 look-up tasks phrased as a consultant would ask, with the resource each should find, the no-match case (scenario 2), and the 256 kbit/s load check (SC-005, throttled browser). Run it against a local `mkdocs serve` and record the results · specs/014-resource-library/quickstart.md

**Checkpoint**: at least 8 of 10 tasks find their resource in the first five results locally. The real-user run is T015.

## Phase 4: User Story 2, browse by competency (P1)

Files: none of its own. It is proven by T003 and the quickstart section below.

- [x] **T009** [US2] Add the browse check to the quickstart: five competencies each show their resources with title, description and type; a made-up competency name in `resources.yaml` fails the check (scenario 2); every resource is reachable from at least one competency (SC-002) · specs/014-resource-library/quickstart.md

**Checkpoint**: Stories 1 and 2 work on the built site with no Moodle change.

## Phase 5: User Story 3, reach the library from Moodle (P2)

Files: moodle/site/settings/learner-experience.yaml (or, if T001 says so, block_ltuse's onward route and its tests)

- [x] **T010** [US3] Declare the "Library" menu item for the browser and the app, pointing at `https://competencies.languagetechnology.org/library/`. Run `site_config.py validate` and `drift` · moodle/site/settings/learner-experience.yaml
- [ ] **T011** [US3] On ltuse.net, apply and verify: from a test learner's dashboard reach a named resource in two taps or fewer, in the browser and the Moodle app (SC-003); a lesson link to `/library/#<anchor>` opens in the app (scenario 2). Record in the quickstart · specs/014-resource-library/quickstart.md

**Checkpoint**: learners reach the library from Moodle.

## Phase 6: User Story 4, contributor adds or corrects a resource (P2)

Files: scripts/check_resource_links.py, tests/test_check_resource_links.py, .github/workflows/resource-links.yml, CONTRIBUTING.md

**Wave 1, independent (different files):**

- [x] **T012** [P] [US4] Write failing tests for the link checker: a working url, a 404, a HEAD refused then GET working, a timeout, and exit codes 0, 1 and 2 per `contracts/check-resource-links-cli.md` · tests/test_check_resource_links.py
- [x] **T013** [P] [US4] Add "Add or fix a library resource" to the contributor guide: tell Claude Code the title, link, one-line description and competencies, and it opens the change for you; no git asked of you (FR-008) · CONTRIBUTING.md

**⟶ Wait for Wave 1 to finish, then:**

- [x] **T014** [US4] Write the link checker with the standard library only, then the weekly workflow that runs it and opens or updates the one `Broken resource links` issue (FR-007, SC-004) · scripts/check_resource_links.py, .github/workflows/resource-links.yml

**Checkpoint**: a broken url in a test copy produces exit 1 and the issue body. A contributor's added resource appears after the next publish.

## Phase 7: Polish

**Wave 1, independent (different files):**

- [ ] **T015** [P] Run SC-001 with 2–3 real partner users and record the score in the quickstart. The spec is not done until this passes (constitution X) · specs/014-resource-library/quickstart.md
- [x] **T016** [P] Update the docs to say resources live in `resources.yaml`: the descriptor section's `resources:` note, the scripts list (`check_resource_links.py`) and the publishing section (library page) · CLAUDE.md
- [x] **T017** [P] Update row #24's status (constitution X) · moodle/REQUIREMENTS.md

**⟶ Wait for Wave 1 to finish, then:**

- [x] **T018** Validate against the Success Criteria: run `python -m pytest tests/test_resources_yaml.py tests/test_gen_site_library.py tests/test_check_resource_links.py`, `python scripts/check_competency_descriptors.py`, `python scripts/gen_coverage.py` and `mkdocs build`. Confirm the library page carries no `modules/` content (FR-012)

## Dependencies & Execution Order

- Setup (T001) only gates Phase 5. Phase 2 can start at once.
- Phase 2 blocks every story. Wave 1 (tests) → Wave 2 (check, generator) → T006 (migration) → T007.
- Phases 3 and 4 need Phase 2. Phase 5 needs T001 and Phase 2. Phase 6 needs only Phase 2. Phases 3–6 own disjoint files apart from the quickstart, which T008, T009 and T011 append to in order.
- Polish: T015–T017 together, then T018.
- T015 needs real users and T011 needs the live server, so both are maintainer-run.
