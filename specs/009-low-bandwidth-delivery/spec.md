# Feature Specification: Low-Bandwidth and Offline Delivery

**Feature Branch**: `specs/moodle-requirements`

**Created**: 2026-09-30

**Status**: Draft

**Input**: User description: "Deliver REQUIREMENTS.md row #5, Low bandwidth (Must, S–M): courses must reach consultants on Android devices, often offline and on slow or metered connections. The content is already light (text and screenshots, video linked). To add: the publisher sends lighter copies of lesson images while the committed screenshots stay the source of truth and the payload stays platform-neutral; server-side caching is switched on through configuration; and a published course is confirmed to download and work offline in the Moodle app."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Lessons load quickly on a slow connection (Priority: P1)

A consultant on a slow, metered mobile connection opens a lesson. Its screenshots arrive as lighter copies, sized for the screen they will be read on, so the lesson is readable within a reasonable wait and without using up their data. The screenshots are still clear enough to read every menu label and field they point at.

**Why this priority**: Screenshots are the heaviest thing a lesson carries, and every lesson carries at least one. Lightening them helps every learner on every course at once.

**Independent Test**: Publish one pipeline course with screenshots to the temporary instance, then compare the size of the images delivered with the committed originals, and open a lesson on a throttled connection.

**Acceptance Scenarios**:

1. **Given** a course with committed screenshots, **When** it is published, **Then** each image delivered to Moodle is no larger than its committed original, and larger ones are reduced substantially.
2. **Given** a published lesson, **When** a reviewer compares its screenshots with the committed originals, **Then** every label, menu item and field the lesson refers to is still legible.
3. **Given** a publish, **When** it finishes, **Then** the committed screenshots in the repository are byte-for-byte unchanged and no new image files exist in the working tree.

---

### User Story 2 - A course works with no connection at all (Priority: P1)

A consultant downloads a course in the Moodle app while they have a connection, then travels to where there is none. Every lesson opens with its text and screenshots; the quiz can be attempted and synchronises when they reconnect; a linked video shows plainly as needing a connection, and the lesson still teaches without it.

**Why this priority**: INTENT makes offline a requirement, not a preference. Equal to P1 because a light page that cannot be taken offline still fails the field.

**Independent Test**: On an Android device with a test account, download a published course, switch the device to airplane mode, work through every lesson and the quiz, then reconnect and confirm the attempt reaches Moodle.

**Acceptance Scenarios**:

1. **Given** a course downloaded in the app, **When** the device is offline, **Then** every lesson opens with all its text and images.
2. **Given** an offline learner, **When** they attempt the quiz, **Then** their answers are kept and synchronised on reconnection.
3. **Given** a lesson with a linked video, **When** it is opened offline, **Then** the video is shown as unavailable offline and the lesson's text and screenshots still carry its content.

---

### User Story 3 - The server serves repeat visits cheaply (Priority: P2)

Learners returning to the site, and many learners opening the same course, are served from cache rather than having every page rebuilt, so the site stays responsive on modest hosting and returning learners download less.

**Why this priority**: It helps everyone but depends on the host; on the shared build host it is a small gain, on the production server (015) it matters more.

**Independent Test**: With caching configured from the repo on the temporary instance, load the same lesson twice and confirm the second load transfers less and is faster; rebuild the setting from the repo and confirm it is identical.

**Acceptance Scenarios**:

1. **Given** caching applied from the repo, **When** a learner reloads a lesson, **Then** unchanged images and styles are not downloaded again.
2. **Given** a course republished with changed images, **When** a learner next opens it, **Then** they receive the new images, not stale cached ones.

---

### Edge Cases

- An image already smaller than any reduction would make it: the original is delivered unchanged.
- A diagram committed as SVG: delivered unchanged, since it is already small and scales without loss.
- A screenshot with fine text that becomes illegible at the default reduction: the author can mark it to be delivered at higher fidelity, and the publish reports which images were exempted.
- An image with transparency: transparency is preserved.
- A corrupt or unreadable committed image: the publish stops and names the file, rather than sending a broken image.
- A republish with no changed images: the delivered copies are identical to last time, so nothing is re-sent and the course is left exactly as it was (republishing already changes nothing today).
- An image referenced only by a withheld page or an excluded file (design doc, mentor guide, video script): not delivered at all, exactly as today.
- An author keeping the payload for inspection inside the repository folder: the lighter copies must not land in the working tree, where auto-commit would push them as large binaries.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Publishing MUST deliver a lighter copy of each raster image a learner page uses, reduced in dimensions to a sensible maximum display width and in file size, while keeping every element the lesson refers to legible.
- **FR-002**: The committed screenshots and diagrams MUST remain the source of truth: publishing MUST NOT modify, replace or add to anything under a course's folder, and the lighter copies MUST NOT be committed or written into the repository working tree.
- **FR-003**: A delivered image MUST never be larger than its committed original; where reduction does not help, the original is delivered.
- **FR-004**: Vector diagrams MUST be delivered unchanged.
- **FR-005**: Image reduction MUST be part of building the platform-neutral publish payload and MUST NOT depend on anything specific to Moodle, so that any other delivery target would receive the same lighter images.
- **FR-006**: Reduction MUST be deterministic: the same committed image and settings MUST always produce the same delivered copy, so an unchanged republish sends nothing new.
- **FR-007**: An author MUST be able to exempt a specific image from reduction, or deliver it at higher fidelity, by a marking visible in the course's own markdown or folder, without any tool; the exemption is reported at publish time.
- **FR-008**: Each delivered image MUST remain traceable to the committed file it came from, so the pre-publish disclosure check still verifies that no delivered asset derives from an excluded source.
- **FR-009**: The pre-publish disclosure check MUST still run on the payload with the lighter images and MUST pass before anything is sent; image reduction MUST NOT change which assets are included or withheld.
- **FR-010**: A publish MUST report, per course, the total image weight before and after reduction and any image exempted or delivered unchanged.
- **FR-011**: Alt text MUST be delivered unchanged with every image.
- **FR-012**: Server-side caching and the settings that let the Moodle app download courses for offline use MUST be applied from the repo's training-system configuration, using core Moodle capability where it exists, and MUST be rebuildable on a new server with no manual steps.
- **FR-013**: A republished course with changed images MUST reach learners without stale cached copies.
- **FR-014**: A published course MUST be usable offline in the Moodle Android app: all lessons with their images, and the quiz with answers synchronised on reconnection.
- **FR-015**: No lesson MAY depend on video to convey its content; a linked video that cannot load offline MUST leave the lesson usable.

### Key Entities

- **Committed image**: a screenshot or diagram under `modules/<slug>/assets/`; the source of truth, never altered by publishing.
- **Delivered image**: the lighter copy that travels in the publish payload, derived from one committed image and traceable to it; never stored in the repository.
- **Reduction exemption**: an author's marking that one image is delivered at higher fidelity or unchanged.
- **Weight report**: the per-course before-and-after image totals printed at publish time.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For the current screenshot-bearing courses, the total image weight delivered is at least 50% below the committed originals.
- **SC-002**: No published lesson page, with all its images, exceeds 1 MB on first load.
- **SC-003**: On a connection throttled to 256 kbit/s, a typical published lesson is readable (text and first screenshot shown) within 15 seconds.
- **SC-004**: A reviewer comparing delivered with committed screenshots finds zero cases where a label or field the lesson names is illegible.
- **SC-005**: After any publish, the repository working tree shows zero changed or added files.
- **SC-006**: A course downloaded in the app is completed offline on an Android device — every lesson and the quiz — with zero failures, and the quiz attempt appears in Moodle after reconnecting.
- **SC-007**: An unchanged republish re-sends zero images.

## Assumptions

- Committed screenshots are PNG captures of software screens and diagrams are SVG, as the course package rules require; photographs are rare.
- A maximum display width suited to phone and tablet screens, with a moderate quality setting, is legible for software screenshots; the exact values are chosen and verified in planning against real screenshots.
- Offline download and quiz synchronisation are provided by the Moodle app itself; this spec configures and verifies them rather than building them.
- Caching settings are applied and verified on the temporary instance; how much they help at scale depends on the production host (015).
- The payload continues to be written to a temporary location outside the repository by default.

## Requirements Traceability

| # | Requirement | Pri | What this spec delivers |
|---|---|---|---|
| 5 | Low bandwidth | Must | Lighter images in the publisher with committed originals untouched; server caching and offline-download settings as configuration; verified offline use in the Android app. |

On delivery, the same PR updates these rows' status in moodle/REQUIREMENTS.md (constitution X).

## Constitution Check

- **I. Source of truth**: Committed screenshots are never altered by publishing (FR-002); delivered copies are generated artefacts, never hand-edited or stored. Publishing stays one-way.
- **II. Portability / config-as-code**: Reduction lives in the platform-neutral payload, knowing nothing of Moodle (FR-005); caching and offline settings are applied from `moodle/` (FR-012). Markdown stays readable raw; an exemption is a visible marking needing no tool (FR-007).
- **III. Public repo, private people**: No learner data involved; offline verification uses test accounts only.
- **IV. Disclosure boundary**: Reduction runs before the pre-publish check, keeps each asset traceable, and never changes what is included or withheld (FR-008, FR-009).
- **VI. No LMS orientation**: Offline download is verified as part of 007's learner experience; nothing extra to learn.
- **IX. Flat cost, field-ready**: The feature exists for this principle; no paid service; no new binaries committed (FR-002, SC-005); video never the only route (FR-015).
- **X. Traceable and verified**: Cites row #5; caching and offline behaviour are verified on the temporary 5.2.3+ instance and on a real Android device before the plan depends on them. It adds no recurring cost of its own; any cache service the production host needs is an operational burden for the undecided operator, carried in 015, not claimed as covered here.
- **Platform & Delivery**: Core first (FR-012); Moodle core never modified; server always from `MOODLE_URL`.

## Dependencies

- **001-site-config-as-code** — applies the caching and mobile/offline settings (#4 baseline).
- **007-learner-experience** — the app experience in which offline download must be discoverable.
- **015-production-hosting-ops** — caching at scale and any cache service on the production server.
