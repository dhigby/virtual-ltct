# Feature Specification: Multilingual training system

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver REQUIREMENTS.md row #6 (Multilingual, Must). The Moodle interface can be offered in several languages now, using core language support and a per-user language choice, applied from the repo like every other setting. Translated course content is different: it is a change to the course content model — course layout, `course_stage.py` and the publisher — so under constitution X its deliverable here is an approved design decision (sibling course folder, per-lesson variant, or another model), not a build. Translations come from humans; AI never translates, glosses or judges language data."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A learner uses Moodle in their own interface language (Priority: P1)

A partner learner whose working language is not English logs in and sees the Moodle interface — menus, dashboard, buttons, notifications, the Moodle app — in a language they read comfortably, and can change it themselves without help.

**Why this priority**: The learners work "across many organisations and many interface languages" (INTENT). An interface they cannot read breaks "no LMS orientation" before any course is opened. It is also the cheap half of row #6 and can ship without touching the content model.

**Independent Test**: On a rebuilt test instance, create a test learner, choose a non-English interface language, and confirm the web interface and the Moodle app both follow it — with no course content translated at all.

**Acceptance Scenarios**:

1. **Given** a site rebuilt from the repo, **When** an administrator lists the installed interface languages, **Then** exactly the languages declared in the repo's configuration are installed, and none other.
2. **Given** a test learner, **When** they pick a language from the language choice on the login page or their profile, **Then** every Moodle-generated screen they see switches to it, and the choice persists across sessions and devices.
3. **Given** a test learner with a chosen language, **When** they open the Moodle app on Android, **Then** the app interface follows the same language.
4. **Given** a published English course, **When** a learner with a non-English interface opens it, **Then** the course content remains in English, is clearly readable, and nothing suggests a translation exists when it does not.

---

### User Story 2 - A new learner lands in a sensible default language (Priority: P2)

When a partner organisation's learners are created or sign up, their interface starts in the language that organisation works in, so a learner who reads no English is never met by an English-only first screen.

**Why this priority**: The first screen is where "know what to do without orientation" is won or lost; it matters most to the least confident learners.

**Independent Test**: Create two test learners belonging to two test organisations with different working languages and confirm each first login appears in that organisation's language.

**Acceptance Scenarios**:

1. **Given** an organisation whose working language is set, **When** a learner is added to it by the normal enrolment route, **Then** their interface language defaults to that language.
2. **Given** a learner whose default was set this way, **When** they choose a different language, **Then** their own choice wins and is not reset by later administration.
3. **Given** no organisation language is set, **When** a learner is created, **Then** they get the site default language.

---

### User Story 3 - The maintainer approves how translated courses live in the repo (Priority: P1)

Before anyone translates a lesson, the maintainer is given a written design for how a translated course is stored, staged, reviewed, kept in step with its source and published, compares the options on one page, and approves one. Nothing on the content side is built until that approval is recorded.

**Why this priority**: INTENT lists "How do translated courses live in the repo?" as an open question, and constitution X forbids building a content-model change without an approved design. Every later translation depends on this decision, and a wrong layout is expensive to undo once courses exist in it.

**Independent Test**: The design document exists, covers every question in FR-010 to FR-018, names a recommended option with the rejected alternatives and why, and carries the maintainer's recorded approval (or rejection) and date.

**Acceptance Scenarios**:

1. **Given** the design, **When** the maintainer reads it, **Then** it compares at least the sibling-course-folder, per-lesson-variant and single-file-multilingual options against the same criteria (portability, raw-readable markdown, contributor simplicity, stage detection, disclosure, publisher impact, offline weight).
2. **Given** the design, **When** it describes the translation workflow, **Then** every translation is produced or checked by a named human, and no step has AI translate, gloss or normalise language data.
3. **Given** the design is not yet approved, **When** a contributor asks to translate a course, **Then** they are told translation waits on the approved design, and no translated lesson is committed.
4. **Given** the design is approved, **When** the decision is recorded, **Then** INTENT's open question is marked decided with its date and row #6's content half is marked "decided" in the same change.

---

### User Story 4 - A learner can find the course in their language once translations exist (Priority: P3)

After the design is approved and built (in a later spec), a learner who reads French, say, can find and take the French edition of a course, and knows which edition they are in.

**Why this priority**: This is the eventual value of the content half, but it is gated behind US3 and a separate build; it is recorded here so the design is judged against it.

**Independent Test**: Not testable under this spec; it becomes the acceptance test of the build spec that follows an approved design.

**Acceptance Scenarios**:

1. **Given** an approved design and a translated course published under it, **When** a learner browses courses, **Then** they can tell which language each edition is in and reach the one they want without orientation.

---

### Edge Cases

- A learner chooses an interface language whose language pack is only partly translated: untranslated strings fall back to English rather than showing blanks or string identifiers.
- A language pack update changes wording a learner relies on: packs are pinned and updated deliberately, as plugins are.
- A right-to-left interface language is requested: the design and the UI configuration either support it on web and app or say plainly that it is not yet offered.
- A lesson contains real minority-language example data: in any translation it is carried across unchanged, never translated, glossed or "corrected", and the translator is told so.
- The English source of a translated lesson changes after translation: the design must say how the translation is marked out of date and what a learner sees meanwhile.
- A translated quiz: its answer key must still be recognised by the one disclosure definition, or the quiz is withheld whole.
- A translated mentor guide, design doc or video script: it is held back from learners exactly as its source is.

## Requirements *(mandatory)*

### Functional Requirements

**Interface languages (build now)**

- **FR-001**: The set of installed interface languages, the site default language and whether learners may choose their own MUST be declared in the repo's Moodle configuration and applied from it, using core Moodle capability.
- **FR-002**: Learners MUST be able to choose their interface language themselves, from the login page and from their profile, without administrator help.
- **FR-003**: A learner's language choice MUST apply in the Moodle app as well as the web interface.
- **FR-004**: Installed language packs MUST be pinned to a known version and updated deliberately, so a rebuild reproduces the same interface text.
- **FR-005**: It MUST be possible to give a partner organisation a default interface language that new learners in it receive, without a per-partner role or a separate site.
- **FR-006**: A learner's own language choice MUST override any organisation default and survive later administration.
- **FR-007**: Custom text the training system itself adds to the interface (for example a dashboard welcome or a support contact) MUST be able to carry per-language versions, supplied by humans.
- **FR-008**: The interface MUST never imply course content is available in a language it is not.
- **FR-009**: Only the languages declared in configuration are offered; adding one is a repo change, not a click in the admin interface.

**Translated course content (design only)**

- **FR-010**: A design for translated course content MUST be written and approved by the maintainer before any translated lesson is committed or any course-layout, stage-detection or publisher change for translation is built.
- **FR-011**: The design MUST compare the candidate storage models (at least: a sibling course folder per language, per-lesson language variants inside one course, and one multilingual file) against content portability, raw-readable markdown, contributor simplicity (no git knowledge), the one stage-detection implementation, the disclosure boundary, publisher impact and offline page weight, and recommend one.
- **FR-012**: The design MUST state how a translation is identified in Moodle so republishing updates rather than duplicates, consistent with the existing identity scheme.
- **FR-013**: The design MUST state how a translated course moves through the production stages — which gates a translation repeats (at minimum an independent human check of the translation) and which it inherits from its source.
- **FR-014**: The design MUST state that every translation is made or checked by a named human; AI may not translate, gloss, judge or normalise language data, and real minority-language example data is carried across unchanged.
- **FR-015**: The design MUST state how a translation is marked out of date when its source changes, and what a learner sees meanwhile.
- **FR-016**: The design MUST keep the disclosure rules defined once: translated design docs, mentor guides, video scripts and answer keys are held back by the same single definition, and a translated quiz whose key cannot be cleanly separated is withheld whole.
- **FR-017**: The design MUST state how competency coverage treats a translated course, so a translation neither double-counts nor hides coverage.
- **FR-018**: The design MUST state how a learner finds and recognises the edition in their language (US4) without LMS orientation.

### Key Entities

- **Interface language**: a language the Moodle interface is offered in; declared in configuration, pinned, installed on rebuild.
- **Organisation language default**: the interface language a partner organisation's new learners start in.
- **Translation design decision**: the approved record of how translated courses are stored, staged, reviewed, identified and published; its approval date closes INTENT's open question.
- **Course edition** (future, per the design): one language version of a course, tied to the source it was translated from.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A server rebuilt from the repo offers exactly the declared interface languages, with no manual step, on the first attempt.
- **SC-002**: At least 2 of 3 test partner learners with a non-English working language switch the interface to their language unaided within 2 minutes of first login.
- **SC-003**: 100% of test learners created in an organisation with a language default see their first screen in that language.
- **SC-004**: The translation design is approved or rejected by the maintainer, with the decision recorded, before any translated lesson exists in the repo — zero translated lessons committed before that date.
- **SC-005**: The design answers every question in FR-011 to FR-018; a reviewer finds no unanswered item.

## Assumptions

- The initial interface languages are English plus French, Spanish and Portuguese _(assumption — the list is a single configuration value the maintainer confirms; more can be added later)_.
- Organisation language defaults ride on the organisation structure from spec 002; no language-specific role is created.
- Core Moodle language packs and per-user language choice are sufficient for the interface half; a content-language filter is a candidate only inside the design, not a decision.
- Translated course content is out of scope for building under this spec; its build is a later spec that starts from the approved design.
- Translators are humans from the department, a partner or a contracted service; who they are and what they cost is part of the design, not assumed free.
- Existing English courses and their stages are not changed by this spec.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 6 | Multilingual | Must | UI half: built and verified (declared languages, per-user choice, organisation defaults, app). Content half: an approved design decision only; build follows in a later spec. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: Language set and defaults live in `moodle/`. Any translation, once designed, is authored in the repo and published one way. Nothing is translated in Moodle and synced back. Stage detection and the disclosure rules keep a single implementation each (FR-016).
- **II. Portability**: Every language setting is applied from `moodle/` and rebuildable. The design must keep translations as plain, raw-readable markdown and keep the publisher's platform-neutral split (FR-011).
- **III. Public repo, private people**: A learner's language choice is learner data and stays in Moodle. Only the organisation-level defaults, which are configuration, are in git.
- **IV. Disclosure**: Translated held-back files and answer keys fall under the same fail-closed rules (FR-016).
- **V. CBC fidelity**: Competency and level names stay verbatim in every language. Any translated display of a level name is shown alongside the canonical CBC name, never in place of it.
- **VI. No git, no LMS orientation**: Learners switch language unaided (SC-002). The design is judged on whether a translator can work without git (FR-011).
- **VII. One shape, gated stages**: A translation runs through defined gates with independent human review (FR-013). No per-language special case.
- **VIII. Humility about language data**: AI never translates, glosses or normalises language data. Real minority-language examples are carried across unchanged (FR-014).
- **IX. Flat cost, field-ready**: Language packs are free. Translator effort is a named cost in the design. Translated editions must stay as light and offline-ready as their source.
- **X. Traceable and verified**: Row #6 is cited. Interface behaviour, the app's language support and any language filter are verified on the temporary 5.2.3+ instance before planning depends on them. SC-002 needs 2–3 real partner learners. The content half is design-gated as X requires. Keeping language packs updated is recurring operations that fall to the Moodle operator. That role is undecided (spec 015), so this spec does not claim those operations are covered.
- **Platform & delivery**: Core first. One instance, one role set. The server comes from `MOODLE_URL`.

## Dependencies

- **001-site-config-as-code**: the declarative configuration the language settings are applied through.
- **002-org-structure-cohorts**: organisations, and the profile fields that carry an organisation's language default.
- **007-learner-experience**: the login page, dashboard and "My courses" where language choice and course editions appear.
- **015-production-hosting-ops**: who keeps language packs updated on production.
