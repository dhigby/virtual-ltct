# Implementation Plan: Simple Learner Experience

**Branch**: `007-learner-experience` | **Date**: 2026-10-05 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/007-learner-experience/spec.md` (row #13)

## Summary

A learner lands on a Dashboard that holds three things:
- one button into their course;
- their course list;
- upcoming events.

Inside a course, each lesson shows its time and its Done/To do state, and every lesson page
ends with a "Next" button. Most of this is configuration. Two small pieces of code fill the
gaps core leaves, both verified in our Moodle's source (research.md R3, R6).

1. **Declared configuration**, in `moodle/site/`:
   - Dashboard as the home page.
   - The navigation switches set live but never declared (R1).
   - A *complete* default dashboard: Timeline, the month calendar and Recently accessed
     items removed (R2).
   - My courses trimmed to All / In progress / Past.
   - Dashboard editing prevented for learners, with leftover personal dashboards reset (R4).
   - SIL Blue `#005CB9` as the brand colour, with translucent SIL Blue tints for the new
     block (R11).
   - Contact site support, addressed from the environment (R9).
2. **`block_ltuse`**, a new block plugin of our own (R5, R7). It has four modes:
   "Continue: <next lesson>" (core has no such link), "Start", a plain no-course message
   naming who to ask (FR-002), and "done". Beneath any mode it shows onward routes:
   the next pathway course, a message link to each mentor, and the community space once it
   exists. The same block renders in the app's Home tab through a `CoreBlockDelegate`
   handler, with two offline hints that appear only in the app.
3. **A next-lesson button** on every lesson page on the web, from a local_ltuse hook (R6).
   Boost's course index makes core hide its own previous/next links. The app has its own.
4. **Estimated time on the course page**: the publisher sends each lesson's own
   `**Estimated time:**` line as its section summary (R10).
5. **The pilot** (V9): 2–3 real partner learners, findings recorded de-identified, row #13
   marked done only then.

## Technical Context

**Language/Version**:
- PHP 8.2+ for `local_ltuse` and the new `block_ltuse`, on Moodle 5.2.3+ (`MOODLE_502_STABLE`).
- Python 3.12 (CI) and 3.11+ (laptops) for `site_config.py` and the publisher.

**Primary Dependencies**: Moodle core only:
- the block API;
- output hooks;
- `my/lib.php`;
- `completion_info`;
- `get_fast_modinfo`;
- `course_update_section`;
- the app's site-plugin `CoreBlockDelegate`.

No third-party plugin. Python: the existing `markdown` and `pyyaml`.

**Storage**: none new (data-model.md). Declarations are in `moodle/site/`. Learner state is
read at render time.

**Testing**:
- pytest in `tests/`: payload `time_text`, publisher summary send, `site_config.py` validate
  rules for the dashboard and the new settings file.
- PHP harnesses in `tests/*_harness.php`, the existing style, for:
  - the continue rule;
  - the next-`cm` rule;
  - the dashboard applier's complete and reset paths.
- The wording test is `tests/test_learner_wording.py` (pytest), not a PHP unit test: it holds
  every block_ltuse string and the spec 007 local_ltuse block to
  `cbc_wording.report_label_problems(strict=True)`.
- PHPUnit tests in `block_ltuse/tests/` for the block itself, run in CI.
- Live checks: [quickstart.md](quickstart.md) V1–V8. The pilot: V9.

**Target Platform**:
- Server: self-hosted Moodle 5.2.3+ (`ltuse.net` now, the VPS later, from `MOODLE_URL`).
- Learners: browsers and the Moodle Android app 5.2.x.

**Project Type**: Moodle plugins (one local, one block), declarative site configuration,
and a CLI publisher.

**Performance Goals**: The Dashboard adds one completion read for one course per load, the
continue course only. That stays within the page cost of today's `myoverview` progress bars.
The hook does nothing off module pages and nothing outside `ltct:*` courses.

**Constraints**:
- Every visible word is a lang string (FR-012).
- No learner data reaches the repo (III).
- No change to vendored code (XI).
- Every setting is declared (II, FR-009).
- Pages stay light, and nothing depends on video (IX).

**Scale/Scope**: one Dashboard, every published course, four declaration files, two plugins.
One instance serves every partner, with one experience for all (VII).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design: still passes.*

| Principle | How this plan complies |
|---|---|
| **I. Source of truth** | Nothing is edited in Moodle. The section summary is the lesson's own header, sent one-way. Nothing syncs back. |
| **II. Portability, config-as-code** | Every setting, block, capability and colour is in `moodle/site/`. R1 brings two hand-set live values under declaration. The payload stays platform-neutral: `time_text` is a string, and only the plugin knows it becomes a section summary. |
| **III. Public repo, private people** | `drift` reports personal dashboards as a count, never as people. Pilot findings are de-identified (data-model §5). Test accounts only. The support address comes from the environment. |
| **IV. Disclosure boundary** | Presentation only. The block links only to visible activities. `time_text` carries only the header line, and `check_moodle_payload.py` asserts it. The withheld-quiz placeholder is untouched. |
| **V. CBC fidelity** | The block names no level. A unit test holds its lang file to `cbc_wording.py`'s rules. |
| **VI. No LMS orientation** | This is the feature's purpose. It is measured by V9 with real learners. |
| **VII. One shape** | One Dashboard and one block for every partner. No per-organisation layout. |
| **IX. Flat cost, field-ready** | No paid plugin or theme. App parity and offline hints (R7, R8). V6 checks whether the block needs the Premium plan. |
| **X. Traceable, verified** | Row #13. Each Moodle behaviour relied on is either confirmed in source (R13) or carried as a V-check. The publisher change is judged not a content-model change (R10), and plan decision 3 asks the maintainer to confirm that. Done only after V9. No new recurring operation. |
| **XI. Survives an upgrade** | Configuration (form 1). Our own block plugin and a hook callback (form 3). No core or Boost edit, and no child theme. `blocks_delete_instance()` and `my_reset_page_for_all_users()` are public core APIs. The block declares `requires` and `supported`. |
| **Platform & Delivery** | Core first: a block of our own only where core has no equivalent (R3, R5). Nothing hard-codes `ltuse.net`. |

No violation, so Complexity Tracking is empty.

## Decisions to confirm with the maintainer

The plan proceeds on these defaults. Each is reversible and is recorded in research.md.

1. **Build "Continue" now** (R3). Spec 004 R6 proposed waiting for the pilot to show the
   need. Spec 007 US1-2 asks for it, and the block exists anyway for FR-002 and FR-008.
2. **Prevent dashboard editing for learners, and reset the personal dashboards that exist
   on the server today** (R4). The reset is irreversible for them. On today's site they
   predate any learner; `drift` reports how many, as a count, never as people.
3. **The section-summary change is a publisher change, not a content-model change** (R10).
   If you read constitution X as covering it, US2's time display waits for your approval of
   the design in contracts/update-sections.md.
4. ~~The brand colour~~ **Decided** (Doug, 2026-10-05): SIL Blue `#005CB9` as Boost's brand
   colour. The complementing colours are translucent SIL Blue tints, used only for this
   feature's block and button (R11).
5. **`MOODLE_SUPPORT_EMAIL`** (R9): the address learners with no course reach. Today's live
   `supportemail` is a personal address. A shared site-team address is recommended, and
   spec 015 makes it a provisioning value.

## Project Structure

### Documentation (this feature)

```text
specs/007-learner-experience/
├── plan.md                          # this file
├── research.md                      # R1–R13
├── data-model.md                    # landing page, learner state, course view, pilot finding
├── quickstart.md                    # V1–V9
├── contracts/
│   ├── dashboard-declaration.md     # amends 011's dashboard contract: complete, weight, reset
│   ├── learner-ui.md                # block_ltuse modes and strings; next-lesson button
│   └── update-sections.md           # section summary: manifest key, web service, publisher
├── pilot-findings.md                # created at the first pilot (V9)
└── tasks.md                         # /speckit-tasks
```

### Source code (repository root)

```text
moodle/
├── block_ltuse/                     # NEW block plugin
│   ├── block_ltuse.php              # get_content(), applicable_formats(), hide_header()
│   ├── version.php                  # requires/supported as local_ltuse; depends on local_ltuse
│   ├── db/mobile.php                # CoreBlockDelegate handler (R7)
│   ├── classes/output/mobile.php    # mobile_block_view
│   ├── templates/                   # block.mustache, mobile_block.mustache
│   ├── lang/en/block_ltuse.php      # every visible string
│   ├── tests/                       # the block's PHPUnit tests (the wording test is pytest)
│   └── README.md
├── local_ltuse/
│   ├── classes/learner_home.php     # NEW: the learner state of data-model §2 (one class, both views)
│   ├── classes/hook_callbacks.php   # + before_footer (next-lesson button, R6)
│   ├── db/hooks.php                 # + registration
│   ├── classes/external/update_sections.php   # + optional summary (contract)
│   ├── classes/siteconfig/dashboard.php       # + complete, weight, reset (contract)
│   ├── lang/en/local_ltuse.php      # + nextlesson, backtocourse
│   ├── styles.css                   # + .ltuse-next alignment; bump mobilecssurl ?v=
│   └── version.php                  # bump
├── site/
│   ├── dashboard.yaml               # complete list, rows [13, 21]
│   ├── roles.yaml                   # user: moodle/my:manageblocks prevent
│   ├── site.yaml                    # pin block_ltuse; bump local_ltuse
│   ├── settings/learner-experience.yaml   # NEW: home page, nav switches, myoverview groupings, brand, support
│   ├── settings/mobile.yaml         # mobilecssurl ?v= bump
│   └── README.md                    # the learner experience section
└── REQUIREMENTS.md                  # row #13 status, on delivery
scripts/
├── moodle_payload.py                # + time_text per section
├── check_moodle_payload.py          # + time_text is the header line or empty
├── publish_moodle.py                # + send summary; print count
└── site_config.py                   # + validate rules for dashboard complete/reset and the new file
specs/011-events-calendar/contracts/declaration.md   # pointer to the amendment
tests/
├── test_payload_time_text.py        # NEW
├── test_publish_moodle.py           # + summary send
├── test_site_config.py              # + dashboard and settings validation
├── test_learner_wording.py          # NEW: block_ltuse and local_ltuse strings vs cbc_wording
└── learner_home_harness.php         # NEW: continue rule, next-cm rule, modes
```

**Structure Decision**: The data logic lives once, in `local_ltuse\learner_home`. That is
where pathway and mentoring data already live, and the hook needs the next-`cm` rule too.
`block_ltuse` is only the two views, web and app, because a block must be its own plugin.
The publisher change stays on each side of the payload split.

## Delivery order

The user stories are independent enough to ship in this order. Each one ends at its
V-check.

1. **US1 + configuration** (P1): `learner-experience.yaml`, `roles.yaml`, the dashboard
   contract, `learner_home`, and `block_ltuse` (web). V1–V3.
2. **US2** (P1): the next-lesson hook and the section summaries. V4.
3. **US3** (P2): the block's app handler and the offline hints. V5–V7.
4. **US4** (P3): the onward routes in `learner_home`. V8.
5. **Pilot** (FR-014): V9, findings, row #13.

## Complexity Tracking

None. No constitution violation needs justifying.
