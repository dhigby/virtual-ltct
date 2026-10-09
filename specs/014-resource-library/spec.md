# Feature Specification: Searchable Resource Library

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

## Clarifications

### Session 2026-10-06

- Q: Where does the library live (FR-009)? → A: On the public competency site (option A). Restricted resources are out of scope.

**Input**: User description: "Deliver row #24 of moodle/REQUIREMENTS.md (Searchable resource library, Pref): a place where consultants find reference material — guides, how-tos, external links, tool documentation — outside any one course, by searching or browsing by competency and topic. The spec states the needs; two ways to meet them are open: a library inside Moodle, or the public GitHub Pages competency site (already searchable, already carrying per-competency Further Information links) hosting it with Moodle linking to it."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultant finds a resource by searching (Priority: P1)

A consultant in the field needs the keyboard installation guide they remember seeing. They type a few words and get a short list of matching resources with titles and a line saying what each is, and open the right one.

**Why this priority**: search is the requirement's core; without it the library is a folder nobody can use.

**Independent Test**: seed the library with at least 30 resources, give test users 10 look-up tasks phrased the way a consultant would ask, and measure how many are found in the first five results.

**Acceptance Scenarios**:

1. **Given** the library holds a resource titled with the searched words, **When** a user searches, **Then** it appears in the first five results with its title and one-line description.
2. **Given** a search matches nothing, **When** results show, **Then** the user is told so and offered browsing by competency.
3. **Given** a user on an Android phone on a slow connection, **When** they search, **Then** results appear without loading heavy pages.

---

### User Story 2 - Consultant browses resources by competency (Priority: P1)

A consultant working towards a competency opens that competency and sees the resources that support it, alongside the courses that teach it.

**Why this priority**: INTENT makes the competency the organising unit of the curriculum and the public site the CBC reference; resources grouped by competency serve CBC students pointed there.

**Independent Test**: pick five competencies; confirm each shows its resources and courses, and that every resource links to at least one competency named exactly as in the framework.

**Acceptance Scenarios**:

1. **Given** resources tagged with a competency, **When** a user opens that competency, **Then** they see those resources, each with title, description and type (guide, video, external site, document).
2. **Given** a resource is tagged with a name not in the competency framework, **When** the library is built, **Then** the build fails, as coverage does.

---

### User Story 3 - Learner reaches the library from Moodle (Priority: P2)

A learner in a Moodle course, or on their dashboard, sees a clear "Library" link and reaches the library in one step, without a second log-in for public material.

**Why this priority**: learners live in Moodle; a library they cannot find from there is unused. It depends on Stories 1–2 existing.

**Independent Test**: from a test learner's dashboard and from a lesson, reach a named resource in two taps or fewer.

**Acceptance Scenarios**:

1. **Given** a learner on their dashboard, **When** they tap "Library", **Then** the library opens at its search.
2. **Given** a lesson that cites a library resource, **When** the learner follows the link, **Then** it opens the resource, in the app as well as the browser.

---

### User Story 4 - Contributor adds or corrects a resource (Priority: P2)

A contributor who has found a useful guide adds it to the library, or fixes a broken link, without touching git and without it being lost in the next rebuild.

**Why this priority**: a library that only the maintainer can change goes stale; but the library is usable before this is smooth.

**Independent Test**: a non-technical contributor, working through Claude Code, adds a resource; it appears in search after the next publish.

**Acceptance Scenarios**:

1. **Given** a contributor describes a new resource (title, link, description, competencies), **When** it is added through the normal repo workflow, **Then** it appears in the library after the next publish, with no git knowledge asked of them.
2. **Given** an external link has stopped working, **When** the scheduled link check runs, **Then** the broken link is reported to the maintainer.

### Edge Cases

- A resource is a large file (video, big PDF): it is linked from where it is hosted, never committed.
- A resource is only for partner staff (licensed or internal): public hosting cannot carry it; see FR-009.
- A hotlinked image or a Google Sites page vanishes: the link check reports it; the library entry is not left silently dead.
- Two resources cover the same topic in different interface languages: both are listed, each labelled with its language.
- A resource concerns a named minority language's script or orthography: the description is written by a human source, not inferred (constitution VIII).
- Search is used offline in the app: the library says it needs a connection; cached pages already opened stay readable where the platform allows.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Users MUST be able to search the library by words in a resource's title, description and tags, and see results with title, one-line description, type and competencies.
- **FR-002**: Users MUST be able to browse resources by competency and by the framework's categories; competency names MUST match `competencies.yaml` verbatim, and an unknown name MUST fail the build.
- **FR-003**: Each resource MUST record title, link or location, one-line description, type, competencies, and interface language; the record MUST be plain, human-readable content in the repo, which is the library's source of truth.
- **FR-004**: The library MUST be reachable from the Moodle dashboard and linkable from any lesson, and MUST open in the Moodle app.
- **FR-005**: Public library content MUST be readable without a Moodle log-in, so CBC students pointed to the competency site can use it.
- **FR-006**: Library pages MUST be light enough for low-bandwidth Android use; no resource may be available only as video.
- **FR-007**: External links MUST be checked on a schedule and broken ones reported; committed images MUST NOT be hotlinked.
- **FR-008**: Adding or correcting a resource MUST NOT require git knowledge from the contributor.
- **FR-009**: The library MUST be hosted on the public competency site, extending its per-competency Further Information lists and its site search; Moodle links to it. Restricted (partner-only) resources are out of scope for this spec. (Decided by the maintainer, 2026-10-06.)
- **FR-010**: Whichever host is chosen, publishing MUST be one-way from the repo; resources edited on the host are overwritten by the next publish.
- **FR-011**: Moving a published library page MUST follow the citation rule: its old path is redirected in the same change.
- **FR-012**: The library MUST NOT carry answer keys, mentor guides, design docs or video scripts.

### Key Entities

- **Resource**: a reference item — title, location, description, type, competencies, language; source of truth in the repo.
- **Competency**: from `competencies.yaml`; groups resources and links them to courses.
- **Library entry point**: the link from Moodle (dashboard, lessons) to the library.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In a test with 2–3 real partner users, at least 8 of 10 look-up tasks find the intended resource in the first five results, within 1 minute each.
- **SC-002**: 100% of resources are reachable by browsing at least one competency.
- **SC-003**: A learner reaches the library from their Moodle dashboard in two taps or fewer.
- **SC-004**: Broken external links are reported within 7 days of breaking.
- **SC-005**: A library page loads in under 5 seconds on a 256 kbit/s connection.
- **SC-006**: A contributor with no git knowledge adds a resource that appears after the next publish, with no maintainer intervention.

## Assumptions

- The existing `resources:` Further Information lists in competency descriptors are the seed of the library; they are not duplicated elsewhere.
- Most resources are public (vendor documentation, SIL/partner guides, public videos); restricted material is the exception.
- The library holds references, not course content; courses stay under `modules/`.
- Search quality means "the right item in the first five for plain-words queries"; advanced search features are out of scope.
- Content translation of resources is out of scope; resources in other interface languages are listed as found (spec 010 governs translation).

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 24 | Searchable resource library | Pref | Resource records held in the repo, search and competency browsing, an entry point from Moodle, link checking, and the host decision (FR-009). |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: resources are records in the repo, published one-way (FR-003, FR-010); no second copy maintained by hand.
- **II. Portability / config-as-code**: resource records are plain and platform-neutral; if hosted in Moodle, the library space is applied from `moodle/` and rebuildable.
- **III. Public repo, private people**: no learner data involved; restricted resources carry no credentials in the repo.
- **IV. Disclosure boundary**: nothing withheld from learners may appear in the library (FR-012).
- **V. CBC fidelity**: competency names verbatim, build fails on mismatch (FR-002); moved public pages redirected (FR-011).
- **VI. No git, no LMS orientation**: contributors add resources without git (FR-008); learners reach the library from the dashboard (SC-003).
- **VIII. Language data**: descriptions about a named language come from a human source.
- **IX. Flat cost, field-ready**: option A costs nothing new; option B must not add a per-learner cost; pages light, no video-only resources (FR-006).
- **X. Traceable and verified**: cites row #24; if option B, Moodle search behaviour on 5.2.3+ is verified before relied on. SC-001 is a "simple" criterion, so 2–3 real partner users must test it before done. Option B adds a search service to operate, a recurring burden with no named operator yet (spec 015).
- **Platform & Delivery**: core Moodle first if hosted in Moodle; never `mkdocs gh-deploy` locally if hosted on Pages; the Pages build stays `strict`.

## Dependencies

- **001-site-config-as-code**: applying any Moodle-side library space and dashboard link.
- **007-learner-experience**: placement of the library entry point on the dashboard.
- **009-low-bandwidth-delivery**: page weight and offline behaviour.
- **015-production-hosting-ops**: only if option B adds a search service to operate.
