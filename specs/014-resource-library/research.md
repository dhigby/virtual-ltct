# Research: Searchable Resource Library

## R1. Host

**Decision**: The public competency site (GitHub Pages, MkDocs Material). Moodle links to it.

**Rationale**: The maintainer chose it on 2026-10-06. The site already has search and per-competency Further Information lists. It costs nothing new to run, and CBC students can read it without a Moodle log-in (FR-005).

**Alternatives considered**: A Moodle library space. It would add a search service with no named operator (spec 015) and a second copy of the metadata. Restricted resources, the only thing Moodle would add, are out of scope.

## R2. Where a resource record lives

**Decision**: One file, `resources.yaml`, at the repo root next to `competencies.yaml`. One entry per resource: `title`, `url`, `description`, `type`, `competencies` (a list), `language`. The `resources:` lists in `competencies/*.md` are moved into it and removed from the descriptors.

**Rationale**: One resource often supports several competencies. In the descriptors it would be copied into each, and the copies drift apart. The spec's assumption says the seed lists are "not duplicated elsewhere", so they move rather than being copied. A single file is also the easiest thing for a contributor working through Claude Code to add to (FR-008).

**Alternatives considered**: Extending each descriptor's `resources:` entries with the new fields. Rejected because of the duplication. One file per resource was also considered: it means hundreds of tiny files for little gain.

## R3. Search

**Decision**: Use the site's built-in search (the Material `search` plugin). `gen_site.py` generates one library page with a `####` heading per resource, under category `##` and competency `###` headings. Its description, type, language and competency names sit under the heading.

**Rationale**: The plugin indexes each heading as its own entry, so a search for "keyboard install" lands on that resource's section with its title and description shown. Nothing new to run. SC-001 is measured with real users before the spec is done.

**Alternatives considered**: A separate search index or a JavaScript filter like `competency-filter.js`. A filter can be added later if SC-001 fails. It isn't built up front.

## R4. Browsing by competency

**Decision**: Each competency page keeps its Further Information section, now built from `resources.yaml` entries that name that competency, showing type and description. The library page groups resources by framework category, then by competency.

**Rationale**: This reuses `resources_section()` and the existing page layout. The citation URLs of competency pages don't move, so FR-011 needs no redirect.

## R5. Validation

**Decision**: `check_competency_descriptors.py` also validates `resources.yaml`. Every competency name must be in `competencies.yaml`, and the required fields must be present. `type` must be one of `guide`, `video`, `site`, `document`. The url must be http(s). The workflow trigger gains `resources.yaml`.

**Rationale**: The same check already guards resource shape and framework names, and CI already runs it (FR-002, US2 scenario 2).

## R6. Link check

**Decision**: A new `scripts/check_resource_links.py` that uses the standard library (`urllib`) to fetch each url with a HEAD request, falling back to GET. A weekly scheduled workflow runs it and opens or updates a single GitHub issue listing broken links (FR-007, SC-004).

**Rationale**: No new dependency. Weekly meets the 7-day target. An issue is how the maintainer already receives work.

**Alternatives considered**: `lychee-action`. It adds a third-party action for something about 40 lines of Python already does.

## R7. Moodle entry point

**Decision**: Add a "Library" item to the site's custom menu (`custommenuitems`) and to the Moodle app's main menu (`tool_mobile` `custommenuitems`). Both are declared in `moodle/site/settings/learner-experience.yaml` and applied by `site_config.py`.

**Rationale**: It is configuration only, and one tap from any page, including the dashboard (SC-003). Lessons link to resources with the plain URL of their library section (`/library/#<anchor>`).

**Confirmed in core source (Principle XI, T001, 2026-10-06)**: Context7 (`/moodle/moodle`) returned nothing for either setting, so both were confirmed in upstream source on `MOODLE_502_STABLE`.

- Browser: `custommenuitems`, a core setting (no plugin prefix), declared with `admin_setting_configtextarea('custommenuitems', ...)` in [public/admin/settings/appearance.php](https://raw.githubusercontent.com/moodle/moodle/MOODLE_502_STABLE/public/admin/settings/appearance.php). One item per line: `text|URL|tooltip|langs`, where tooltip and langs are optional; a leading `-` nests a line under the previous top item ([public/lang/en/admin.php](https://raw.githubusercontent.com/moodle/moodle/MOODLE_502_STABLE/public/lang/en/admin.php), `configcustommenuitems`). It still renders in 5.2: Boost's [layout/drawers.php](https://raw.githubusercontent.com/moodle/moodle/MOODLE_502_STABLE/public/theme/boost/layout/drawers.php) builds the menu from `core\navigation\output\primary`, whose `get_custom_menu()` reads `$CFG->custommenuitems` and merges it into both the desktop and mobile primary navigation ([public/lib/classes/navigation/output/primary.php](https://raw.githubusercontent.com/moodle/moodle/MOODLE_502_STABLE/public/lib/classes/navigation/output/primary.php)). Declare it as `name: custommenuitems`, value `Library|https://competencies.languagetechnology.org/library/`.
- App: `tool_mobile/custommenuitems`, a textarea in [public/admin/tool/mobile/settings.php](https://raw.githubusercontent.com/moodle/moodle/MOODLE_502_STABLE/public/admin/tool/mobile/settings.php). One item per line: `text|URL|method|lang`, where method is `app`, `inappbrowser`, `browser` or `embedded`, and lang is optional ([tool_mobile.php lang file](https://raw.githubusercontent.com/moodle/moodle/MOODLE_502_STABLE/public/admin/tool/mobile/lang/en/tool_mobile.php), `custommenuitems_desc`). Declare it as `name: tool_mobile/custommenuitems`, the same `plugin/name` form `mobile.yaml` and `learner-experience.yaml` already use for plugin settings, value `Library|https://competencies.languagetechnology.org/library/|inappbrowser` (the library is a static site, so it opens in the in-app browser).
- App plan: the setting is in free core. On a non-premium plan the settings page shows a subscription notice when the plan limits the number of `custommenuitems`; the limit comes from the app subscription data. ltuse.net has the Premium plan (2026-10-02), so one item is well within it either way.

Both settings exist, so the `block_ltuse` onward-route fallback is not needed. Both are declared in `learner-experience.yaml`; the URL is the competency site's fixed address, not `MOODLE_URL`, because the library is not on the Moodle server.

## R8. Page weight

**Decision**: The library page is text only, with no images or embeds. A video resource is listed as a link, with its description saying what it covers (FR-006).

**Rationale**: A few hundred entries of text stay well under SC-005's budget at 256 kbit/s. The search index is loaded only when a user searches.
