# Research: Simple Learner Experience

**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md) · **Date**: 2026-10-05

Each entry gives the decision, why, what else was weighed, and how it is verified on the
temporary 5.2.3+ instance (constitution X). "Confirmed" means read in the Moodle source on
`ltuse.net` (`~/moodle`, release 5.2.3+, Build 20260928) on 2026-10-05; the live values quoted
were read there the same day with `admin/cli/cfg.php` and one read-only CLI query of the
default dashboard's blocks. No learner data was read.

## R1. What a learner lands on today

**Found** (live, 2026-10-05):

- `defaulthomepage = 1` (`HOMEPAGE_MY`, the Dashboard). `enabledashboard = 1`.
- `enablemycourses = 0` and `enablemyhome = 0`. So the primary navigation shows neither
  "Home" nor "My courses", only "Dashboard" plus local_ltuse's "Pathways" and, for some,
  "Mentoring" (`lib/classes/navigation/views/primary.php:43-71`). **Neither value is
  declared in `moodle/site/`**: someone set them in the admin UI. That is drift by
  constitution II, and this spec owns these settings.
- The default dashboard (`my_pages` `__default`, id 2) carries, in this order:
  `myoverview` (content, 0), `timeline` (content, 1), `calendar_month` (side-post, 0),
  `recentlyaccesseditems` (side-post, 1), `calendar_upcoming` (side-pre, 0; spec 011).
- Some users have a dashboard of their own (`my_pages` rows with a `userid`). The query
  counted them and read nothing else. Neither how many nor who is recorded here
  (Principle III); `drift` reports the count.
  On a site with no learners yet they are presumably staff and test accounts; R4 says what
  happens to them.
- `moodle/my:manageblocks` is allowed to the `user` archetype (`lib/db/access.php:1817`), so
  any learner can edit their dashboard and so detach from the default.

**Decision**: Keep the Dashboard as the landing page, and declare all four navigation
settings above at their current live values. The Dashboard is the only page that can carry
the "continue" route and the onward routes beside the course list, and it is what the app
shows on its Home tab (R7). "My courses" stays off: on this site it would be a second list
of the same courses.

**Alternatives**: Land on My courses (`HOMEPAGE_MYCOURSES`). It is cleaner, but holds only
the course list, so FR-001's continue route, FR-002's message and FR-008's onward routes
would have no place there. `HOMEPAGE_URL` pointing at a page of our own: that adds a page
and loses the app's Home tab parity.

**Verify**: V1 (a new learner's first page names their course and offers one way in).

## R2. Which dashboard blocks stay

**Decision**: The default dashboard holds three blocks:

| Region | Block | Why |
|---|---|---|
| content, 0 | `ltuse` (R5, new) | The one "continue" route, the empty-state message, the onward routes. |
| content, 1 | `myoverview` | The clean course list, with progress bars (spec 004 R6). |
| side-pre | `calendar_upcoming` | Spec 011's upcoming events (unchanged). |

Removed from the default dashboard: `timeline` (the spec names it: activity due dates
unrelated to a self-paced course, and it never shows hand-made events, 011 R8);
`calendar_month` (a second calendar beside Upcoming events); `recentlyaccesseditems`
(replaced by the continue route, R5).

`dashboard.yaml` becomes the complete list for the default page, not an additive one: apply
removes from the default page any block it does not declare. This reverses 011's "never
removes" rule for the default page only. 011's contract said 007 owns the rest of the
layout, so the change goes to [contracts/dashboard-declaration.md](contracts/dashboard-declaration.md),
and the 011 contract gets a pointer to it. The change still never touches a user's own
dashboard (R4 handles those).

**myoverview groupings**: The live state offers All, In progress, Future, Past, Starred and
Removed from view. A learner needs only the default view. The decision covers **every**
`block_myoverview/displaygrouping*` setting. Declared:
`block_myoverview/displaygroupingall = 1`, `displaygroupinginprogress = 1`,
`displaygroupingpast = 1`, and `displaygroupingfuture = 0`, `displaygroupingfavourites = 0`,
`displaygroupinghidden = 0`, `displaygroupingallincludinghidden = 0`,
`displaygroupingcustomfield = 0`. Future is empty here, because published courses have no start
date in the future. Starred and Removed-from-view are list-management features that a
learner on two courses does not need. `layouts` stays `card,list,summary`, the core default.
Spec 004 R6 left "which grouping comes first" to this spec. The first-view grouping is a
per-user preference whose default is All, which on this site is the same set as In progress
plus Past. So this spec does not change it.

**Verify**: V1, V2 (the dashboard shows the three blocks and nothing else; the course list
offers All, In progress and Past only).

## R3. "Continue where you left off" has no core implementation

**Confirmed**: Core has no block or page that links to a learner's next incomplete activity.
`block_recentlyaccesseditems` lists the last items *opened*, which after finishing lesson 2
is lesson 2, not lesson 3. A `block_myoverview` card links to the course page's top. Spec
004 R6 recorded the same gap and handed it to this spec.

**Decision**: Build it, in the block of R5. The rule:

1. Among the learner's active enrolments, take the courses with completion tracking that
   are not complete, ordered by the learner's last access to the course (core's
   `user_lastaccess`, which `course_get_recent_courses()` reads), most recent first.
2. In the first such course, the next activity is the first visible, trackable `cm` in
   course order that the learner has not completed, read from `get_fast_modinfo()` and
   `completion_info::get_data()`.
3. The block shows one primary button: "Continue: <lesson name>" (course name beneath).
   A learner who has not yet started anything gets "Start: <first lesson>" for the course
   they were most recently enrolled in.

So SC-002 is one tap from the landing page, on the web and in the app.

**Alternatives**: The pilot-first route that 004 R6 proposed: try the core route
(course card, then course page) with real learners and build the link only if they stall.
That is two taps, which meets SC-002's "no more than 2". It fails US1-2 as written ("offers
to take them to where they left off"), though, and the block exists anyway for FR-002 and
FR-008, so the link costs one query. **Recorded for confirmation** as plan decision 1.

**Verify**: V3 (after lesson 2, the dashboard offers lesson 3, in one tap, web and app).

## R4. Personal dashboards and editing

**Decision**: The `user` role's entry in `roles.yaml` sets `moodle/my:manageblocks: prevent`.
That removes "Customise this page" for everyone who has only the authenticated-user role.
The site team edits the default Dashboard through `moodle/my:configsyspages` (the manager
archetype's, `lib/db/access.php:640-648`; `my/indexsys.php:49`), which this does not touch.
Only the user archetype grants `manageblocks` (`lib/db/access.php:1817-1822`), so a non-admin
manager's own Dashboard is locked too. `prohibit` was rejected because nothing can override
it, where a role that explicitly allows the capability can still give editing back over a
`prevent`. (Corrected 2026-10-06 in review: this said managers keep editing through their
archetype, which no archetype grants.) `inherit` was rejected because it only removes the system-context
permission, which leaves the archetype's allow in place.

With editing gone, a personal dashboard can only be a leftover from before. When it applies
a changed dashboard declaration, apply resets every leftover with core's
`my_reset_page_for_all_users(MY_PAGE_PRIVATE, 'my-index')` (`my/lib.php:232`). Drift reports how many
there are, as a count, never as people.

The reset is irreversible for the personal dashboards that exist on the server today. On
today's site they predate any learner. **Recorded for confirmation** as plan decision 2.

**Alternatives**:
- Leave editing on and reset nothing. Every learner who moves a block then detaches from
  every future change, and FR-009's "a rebuilt server presents the same experience" stops
  holding for them.
- `forcedefaultmymoodle`. It is not a setting on 5.2 (`cfg.php` reports no such variable).

**Verify**: V2 (a learner sees no "Customise this page"; after apply, drift shows no
personal dashboards line).

## R5. One small block of our own: `block_ltuse`

**Decision**: A new block plugin, `moodle/block_ltuse/`, built only on core's block plugin
type (constitution XI form 3) and depending on `local_ltuse` for its data. It renders one of
three states, all from lang strings ([contracts/learner-ui.md](contracts/learner-ui.md)):

- **Enrolled, something left to do**: the continue button (R3).
- **No active enrolment**: "No course has been assigned to you yet." Then "Ask your
  organisation's language technology coordinator, or contact the site team", with a link
  to core's Contact site support form (R9). Not myoverview's own empty state, which names
  no one to ask (FR-002).
- **Onward routes**, beneath either state when they exist for this learner (FR-008, US4):
  - the next course of each pathway they hold, from `local_ltuse\pathway\view::next_course()`
    (spec 006);
  - "Message your mentor" for each mentor, from local_ltuse's mentoring relationship (spec
    003), linking to core messaging;
  - the community space, once spec 005 declares one; until then the route is absent, not
    broken.

The block shows no completion level, badge or CBC level. Where it names a course's aim it
uses the `ltct_target_level` field's CBC wording through `cbc_wording`'s rule ("aims at
2 - With Assistance"), never "you reached" (FR-011). As planned, it names no aim at all, so
the rule is a guard rather than a feature.

**Why a block and not a hook**: A dashboard block is the one surface that both the web
Dashboard and the app's Home tab render from the same default-page declaration. A
`before_standard_top_of_body_html_generation` banner would reach the web only. Core has
nothing that does any of the three states (R3; myoverview's empty state has no contact).

**Why not inside `local_ltuse`**: A local plugin cannot provide a block. Blocks are their own
plugin type.

**Alternatives**: `block_html` with fixed text. It cannot compute "continue" or the onward
routes, and its text is one language unless the multilang filter is turned on site-wide for
every course, which is 010's decision. A third-party "course progress" block: none was found
that covers the empty state and our own pathway and mentoring data, and each would be an
upgrade liability for a fraction of the job.

**Verify**: V1 (empty state), V3 (continue), V8 (onward routes).

## R6. Next-lesson route on a lesson page

**Confirmed**: Core's previous/next activity links (`core_renderer::activity_navigation()`,
`lib/classes/output/core_renderer.php:469`) return nothing when the theme uses the course
index and the format supports it (`:488-493`). Boost sets `$THEME->usescourseindex = true`
(`theme/boost/config.php:187`) and topics uses it. So on our site a lesson page has no
on-page "next" at all. The only route is the course index drawer, which is Moodle
navigation (FR-004 forbids relying on it).

**Decision**: local_ltuse adds a callback for `core\hook\output\before_footer_html_generation`
(built and dispatched in `core_renderer::footer()`, `:965-967`, before `container_end_all()`, so
its HTML lands inside `div[role=main]`). **Changed 2026-10-06** (review):
`after_standard_main_region_html_generation` was planned, but Boost prints it after the page
footer and outside `#page` (`theme/boost/templates/drawers.mustache:181`), not beneath the
content. On an
`incourse` page of a module in a published course (course `idnumber` `ltct:*`), it renders
one button beneath the content:
- "Next: <name>" for the next visible, available `cm` in course order;
- "Back to the course" on the last one.

It uses the same ordering as core's own `activity_navigation()` (`get_fast_modinfo()->get_cms()`,
user-visible, not stealth, with a URL). The text comes from lang strings. In a course that is
not ours, and on any page that is not a module, it does nothing.

**Alternatives**:
- A child theme of Boost with `usescourseindex = false`. Core's own previous/next links
  return, but the course index goes for everyone, and we take on a theme to maintain. XI
  allows a child theme. It is rejected because the hook is smaller and keeps the index.
- Baking a "Next" link into each page's HTML at publish time. It would put English text in
  content (FR-012), and the content model would then know Moodle's course order (constitution
  II, X: a content-model change needs an approved design).

**App**: The Moodle app has its own previous/next module navigation at the foot of a module
page (moodleapp `core-course-module-navigation`). The hook is web-only, and the app needs
nothing from us. **Unverified on our device**: V5.

**Verify**: V4 (web: two lessons in a row with no menu), V5 (app).

## R7. The app's Home tab and the block

**Found**: The app's Home tab renders the site Dashboard's blocks, fetched with
`core_block_get_dashboard_blocks`. Core blocks it supports natively include `myoverview`
and `calendar_upcoming`. A block plugin gains app support by declaring a `CoreBlockDelegate`
handler in its own `db/mobile.php` (moodledev.io, "Moodle App Plugins Development Guide";
the Marketplace News block is the cited example). local_ltuse already serves two
`CoreMainMenuDelegate` handlers this way (spec 003, spec 006), so the mobile output pattern
is in the repo.

**Decision**: `block_ltuse/db/mobile.php` declares a `CoreBlockDelegate` handler whose
method returns the same three states as an Ionic template, built from the same data class
as the web block. In the app, the continue button opens the lesson in the app (a
`core-link` to the module's URL, which the app resolves to its own module page). The app
template adds one line the web does not need: how to keep a course for offline use (R8).

**Unverified**, so recorded as a research task, not assumed (constitution X):
1. that the app renders a site-plugin block on the Home tab, at its declared position,
   on the Moodle app 5.2.x;
2. that the free plan, not only Premium, allows it. The app's site-plugin support is not a
   paid feature as far as the docs say, but the `mobilecssurl` lesson (Premium only) is the
   reason to check.

If either fails, the fallback is a `CoreMainMenuDelegate` "Start here" item, as Mentoring
and Pathways already have. It costs a tap, which still meets SC-002.

**Verify**: V6.

## R8. Making a course available offline: where the learner learns how

**Found**: The app offers "Download course" in the course page's options menu, plus
per-section download icons. Spec 009 keeps these enabled (`tool_mobile/disabledfeatures`
empty). 009 R4 and this spec's assumptions add one learner-facing fact: open a quiz once
while online, because downloading it starts the attempt.

**Decision**: Two lang strings, shown only in the app view of `block_ltuse` (R7):
- "To use this course without a connection: open it, tap ⋮, then Download course."
- "Open the quiz once while you are online, then you can finish it offline."

The course-page hint first planned here is dropped ([contracts/learner-ui.md](contracts/learner-ui.md)
(g)): the contract has no mechanism for it. FR-007 inside a course is met by the app's native
course-menu Download course, which spec 009 keeps enabled (`tool_mobile/disabledfeatures`
empty) and V6 step 3 verifies. The Home-tab hint tells the learner where it is.

Spec 009's V7 confirmed the sync behaviour: syncing by hand is the fallback, not the main
path. So the strings do not mention it. The exact menu wording ("⋮", "Download course") is
taken from the app version verified at V6 and re-checked at each app release the pilot uses.

**Alternatives**: Put the hint in every course's summary at publish time. That puts English
interface text into content (FR-012). It would also show on the web, where it is wrong.

**Verify**: V6, V7 (offline lesson and quiz with only the in-app hints).

## R9. "Who to contact" for a learner with no course

**Found**: Core's Contact site support form (`user/contactsitesupport.php`) is reachable
because `supportavailability = 1`. It sends to `supportemail`, which is live but undeclared.

**Decision**: The empty state points to the learner's coordinator by role, not by name, and
links to Contact site support. Naming the coordinator would mean reading their managers
cohort and showing the name. Under spec 016 a protected manager's name may not be shown to
a stranger, and a learner with no course is not yet in a course with them. Declared in
`settings/learner-experience.yaml`: `supportavailability = 1` (logged-in users only, so the
form is never an anonymous spam route) and `supportemail = env:MOODLE_SUPPORT_EMAIL`. That
address is a setting each server is given at provisioning, as `mobilecssurl`'s host is (015),
not a person's address committed to the public repo.

**Verify**: V1 (the form opens from the empty state and its recipient is the provisioned
address).

## R10. A course reads as its lessons, with times

**Found**: The publisher already makes a `topics` course with one section per lesson, named
after the lesson (`ensure_sections`, `publish_moodle.py:102-119`, called at `:406-409`). It hides the Retired
section completely. Each page completes on view (spec 004). With `showcompletionconditions =
1`, each lesson shows Done or To do. The payload already computes each lesson's minutes
(`moodle_payload.py:96, 300`), but nothing sends them, so a learner never sees the time
before opening a lesson.

**Decision**: The payload adds `time_text` to each section: the lesson's own
`**Estimated time:** N minutes` line, rendered as written. The publisher sends it as the
section summary through `local_ltuse_update_sections`, which gains an optional `summary` per
section ([contracts/update-sections.md](contracts/update-sections.md)). This is content, not
interface text: it is the lesson's own words in the lesson's language. It changes when the
lesson changes and is translated with it (row #6), so FR-012 is met without a lang string.
The payload stays platform-neutral: it sends a string, and only the plugin knows it becomes a
section summary.

**The content-model question (constitution X)**: This adds a field to the manifest, and
touches the publisher. It does not change course layout, `course_stage.py`, or what a course
author writes. The plan treats it as a publisher change, and its tests pin it. If the
maintainer reads X as covering any manifest field, this item waits for that approval
(plan decision 3).

**Section 0**: On a topics course, section 0 is "General". The publisher puts the course
discussion there (spec 005 or 012, `DISCUSSION_NAME`), and core adds an Announcements forum
when `newsitems > 0`. Whether section 0 reads as clutter is a pilot question. This plan
changes nothing there until a finding says so.

**Verify**: V4 (a published course shows each lesson's time, Done/To do and the quiz in
place).

## R11. A light theme adjustment

**Found**: `theme = boost`, preset `default.scss`, and `brandcolor`, `scss` and `scsspre`
are all empty.

**Decision** (plan decision 4, Doug, 2026-10-05: "just use SIL blue and any complementing
colors needed"): Declare `theme_boost/brandcolor = #005CB9`, which is **SIL Blue** in the SIL
Brand Manual v1.1 palette. Boost makes it `$primary`, so buttons, links and the active
navigation item take it, and Boost derives the hover and pressed shades itself. White text on
`#005CB9` has a contrast of about 6.5:1, which passes WCAG AA for normal text.

The complementing colours stay within the manual's 3-colour rule. They use one hue, SIL Blue
and its tints, because grays and black are structural and do not count. They are used only
in `local_ltuse/styles.css`, for the two surfaces this feature adds (`block_ltuse` and
`.ltuse-next`):

| Use | Value | Brand source |
|---|---|---|
| Buttons, links (via Boost) | `#005CB9` | SIL Blue |
| `block_ltuse` panel background | `rgba(0, 92, 185, 0.08)` | SIL Blue, as a ~10% tint (`#E5EEF8` on white) |
| `block_ltuse` panel left rule | `#005CB9` | SIL Blue |
| Separator between the main route and "Where next" | `rgba(0, 92, 185, 0.20)` | SIL Blue, as a ~20% tint (`#CCDEF1` on white) |

The tints are translucent, and no text colour is set. That follows the rule `styles.css` already
states for callouts: the Moodle app follows its own colour scheme rather than the phone's, and a
solid tint or a forced text colour broke dark mode there (2026-10-02). For the app's muted
offline hints, the template uses Ionic's own `color="medium"` rather than a CSS text colour
(R8). Moodle's status colours (green for Done, red for errors, yellow for warnings) are left as
Boost's defaults. They are signals, not brand colour, and changing them needs SCSS.

Rejected:
- the brand fonts (Playfair, Lora, Source Sans 3): Google Fonts weight on low bandwidth (IX), and the app ignores them;
- the SIL logo: it must be Brand Help Desk artwork, and whether a multi-partner site carries SIL's brand is a Global Marketing and Communications decision. A file setting also needs `site_config.py` support it lacks;
- SIL Light Blue, Green and Yellow as text colours: each fails AA on white.

No custom SCSS, and no child theme. The app does not read Boost's brand colour. Its chrome
comes from the app itself, and a branded app is a separate paid product, outside IX. Callouts
come from `styles.css`. So parity rests on R7, not on the theme.

**Verify**: V2 (the navigation bar and buttons show `#005CB9`; drift reports a hand change).
V6 (the block's panel reads in the app in both its light and dark scheme).

## R12. A learner who is also a mentor or a manager

**Decision**: Nothing is removed from those roles' surfaces. Mentoring and Pathways stay in
the primary navigation. "My organisation" stays in the user menu. `block_ltuse` shows the
learner's own continue route only. A manager who is on no course sees the empty state,
which is true for them. The block hides itself for a user who can manage the site
(`moodle/site:config`), so the site team's dashboard is not a learner's. (Edge case 4 in the
spec.)

**Verify**: V8.

## R13. APIs to confirm in `MOODLE_502_STABLE` before each task closes

Every API this plan depends on is now confirmed in the server's read-only `MOODLE_502_STABLE`
(5.2.3+) source, paths under `public/`. Nothing is left to confirm. Where the source differed
from what the plan assumed, the item says what changed.

**Confirmed 2026-10-05** (planning): `after_standard_main_region_html_generation`
(`lib/classes/hook/output/`; since replaced by `before_footer_html_generation`, T045
below); `activity_navigation()`'s course-index rule (`core_renderer.php:488-494`); `my_reset_page_for_all_users()` (`my/lib.php:232`);
`moodle/my:manageblocks` default (`lib/db/access.php:1817`); the primary navigation's three
switches (`views/primary.php:43-71`).

**Confirmed 2026-10-06** (implementation, by task):

- **The block (T007)**: `block_base` at `blocks/moodleblock.class.php:46`. It has no `init()`
  of its own; the constructor calls `$this->init()` (`:115-116`). `$content` is `:76`,
  `get_content()` `:146` (NULL by default), `is_empty()` `:183` (empty text and footer),
  `has_config()` `:386`, `applicable_formats()` `:401`, `hide_header()` `:411`,
  `instance_allow_multiple()` `:506`, `get_content_for_external()` `:286`. The content shape
  is `->text` and `->footer`.
- **Published courses (T011)**: `enrol_get_all_users_courses($userid, $onlyactive = false,
  $fields = null, $sort = null)`, `lib/enrollib.php:1061`. `$onlyactive` filters only the
  enrolment (`:1112-1113`: active user enrolment, enabled instance, started, not ended).
  **It does not filter hidden courses.** `enrol_get_users_courses()` (`:949`) does, at
  `:955-967`, and `enrol_get_my_courses()` (`:598`) does for `$USER` only, so
  `published_courses()` uses the first and copies the second's hidden-course test.
  `has_capability()` `lib/accesslib.php:432`; `context_helper::preload_from_record()`
  `lib/classes/context_helper.php:392`.
- **Settings (T020)**: `HOMEPAGE_MY` = 1 (`lib/moodlelib.php:530`);
  `CONTACT_SUPPORT_AUTHENTICATED` = 1 (`:606`); `supportemail` and `supportavailability`
  (`admin/settings/server.php:62`, `:68`); `theme_boost/brandcolor`
  (`theme/boost/settings.php:84`); `defaulthomepage` and `enablemyhome` / `enabledashboard` /
  `enablemycourses` (`admin/settings/appearance.php:182`, `:145-161`). There are eight
  `block_myoverview/displaygrouping*` settings (`blocks/myoverview/settings.php:60-119`), and
  all eight are declared.
- **Dashboard apply (T026)**: `blocks_delete_instance($instance, $nolongerused = false,
  $skipblockstables = false)`, `lib/blocklib.php:2545`, deletes the block context (`:2560`)
  and its `block_positions` and `block_instances` rows (`:2563-2564`).
  `my_reset_page_for_all_users(int $private, string $pagetype, ?progress_bar $progressbar,
  string $pagename)`: the page name is the **4th** argument, not a 3rd, and it deletes every
  private page of the users it selects (`my/lib.php:245`, `:287`); the `__courses` page
  survives because it is public (`MY_PAGE_*` at `my/lib.php:30-33`). A block's effective
  weight is `COALESCE(bp.weight, bs.weight, bi.defaultweight)` (`lib/blocklib.php:760-761`),
  so a `block_positions` row overrides `defaultweight`. **Changed**: `reposition_block()`
  (`:990`) is not used. It needs the page's blocks loaded, which starts the theme and output
  in a CLI run (`:673-676`), so `set_weight()` makes the same two writes it makes
  (`:1000-1013`): `defaultweight` and, when one exists, the default page's
  `block_positions.weight`. `assign_capability()` (`lib/accesslib.php:1411`) keeps an
  existing row unless `$overwrite` is true (`:1437`), so the T018 fixture passes true.
- **Learner state (T029)**: `course_get_recent_courses()` (`course/lib.php:3880`) joins
  `user_lastaccess` but keeps only `c.visible = 1` (`:3942`, `:3947`). **Changed**: because
  `published_courses()` also keeps a hidden course the learner may see, `last_access()` reads
  `user_lastaccess` by userid directly, and `enrol_times()` reads `user_enrolments` and
  `enrol` with `enrol_get_all_users_courses`' active conditions. `user_lastaccess` is written
  by `user_accesstime_log()` (`lib/datalib.php:1588`). `completion_info`: constructor
  `lib/completionlib.php:270`, `is_enabled()` `:296`, `is_course_complete($user_id)` `:521`,
  `get_data($cm, $wholecourse = false, $userid = 0, $unused = null)` `:1008`. Only
  `COMPLETION_COMPLETE` and `COMPLETION_COMPLETE_PASS` count
  (`completion/criteria/completion_criteria_activity.php:159`), so a failed quiz is offered
  again. `cm_info` (`course/classes/cm_info.php`): `$uservisible` `:162`, `$url` `:170`,
  `get_url()` `:740`, `is_stealth()` `:1583`. `modinfo::get_cms()`
  `course/classes/modinfo.php:248`; `get_fast_modinfo()` `lib/modinfolib.php:58`.
  `render_from_template()` `lib/classes/output/renderer_base.php:164`. **Changed**: the
  `{{#str}}` helper returns `get_string()` unescaped
  (`lib/classes/output/mustache_string_helper.php:47-67`), so the block builds its strings in
  `home::context()` and `{{ }}` escapes them.
- **Section summaries (T041)**: `course_update_section($courseorid, $section, $data): void`,
  `course/lib.php:1049`, hands `$data` to `sectionactions::update(section_info $sectioninfo,
  array|stdClass $fields)` (`course/format/classes/local/sectionactions.php:371`), which takes
  any field but id, course, section and sequence (`:377`), `summary` and `summaryformat`
  included. `external_api::validate_parameters()`
  (`lib/external/classes/external_api.php:316`) leaves an absent `VALUE_OPTIONAL` key absent
  (`:342-355`) and refuses an unknown key as "Unexpected keys" (`:366-369`), which is how a
  pre-007 plugin refuses `summary`.
- **The deploy-order hint (T042)**: `webservice/rest/locallib.php:182-184` sends `debuginfo`
  only under `debugging()`, and `invalid_parameter_exception`'s errorcode is
  `invalidparameter` (`lib/classes/exception/invalid_parameter_exception.php:37`), so the
  publisher tests the errorcode, as planned.
- **Next lesson (T045)**: **Changed** (review, 2026-10-06): the hook is
  `lib/classes/hook/output/before_footer_html_generation.php:30`, with `renderer` and
  `add_html()`; `core_renderer::footer()` builds it at `:965` and dispatches it at `:967`,
  before `container_end_all(true)` (`:968`), so the button lands inside `div[role=main]`.
  `after_standard_main_region_html_generation` (`:586`/`:594`), the first choice, is printed
  by Boost after `theme_boost/footer`, outside `#page` (`drawers.mustache:179-181`).
  `activity_navigation()` is `:469`; its filter (`:505`) also skips a module type that never
  displays (`cm_info::is_of_type_that_can_display()`, `cm_info.php:1569`; mod_qbank), so the
  hook mirrors that too. Boost sets `usescourseindex` (`theme/boost/config.php:187`).
- **The app (T049)**: `tool_mobile` `external::get_content()` runs as the token's user and
  calls the method (`admin/tool/mobile/classes/external.php:428`), returning `templates`,
  `javascript`, `otherdata`, `files`, `restrict`, `disabled` (`:430-448`); `db/mobile.php`
  is read and its lang ids resolved at `admin/tool/mobile/classes/api.php:113-133`.
  `core_block_get_dashboard_blocks` (`lib/db/services.php:2857`;
  `blocks/classes/external.php:248`, structure `:58-89`) lists blocks through
  `get_content_for_all_regions()` (`:112`), which skips a block whose content is empty
  (`lib/blocklib.php:1263-1266`), so a block empty on the web never reaches the app. From the
  Moodle app source, tag `v5.2.1`: `displaydata` is `{title?, class?, type?}`
  (`src/core/features/siteplugins/services/siteplugins.ts:961-968`); the method receives
  `{contextlevel, instanceid, blockid}` plus the default args, `userid` among them
  (`src/core/features/siteplugins/components/block/block.ts:65-69`,
  `siteplugins.ts:95-106`); `[core-link]` takes `capture` as a boolean input (`src/core/directives/link.ts:38`, `:46`). **Changed**:
  `displaydata` carries `title: 'pluginname'`, which the documentation marks required.
- **Onward routes (T058)**: `/message/index.php?id=` is still the one-to-one route
  (`message/index.php:38`, conversation at `:50-51`), kept unchanged. **Changed**:
  `get_role_users()` (`lib/accesslib.php:4062`) matches every role when the role id is empty
  (`:4103`), so the block reads mentors only when the mentor role exists.
