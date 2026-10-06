# Contract: what the learner sees

The two surfaces this feature adds: `block_ltuse` on the Dashboard, on the web and in the
app's Home tab, and the next-lesson button on a lesson page on the web. Every visible word
is a lang string. The English strings below are the reference; translators work from
`lang/en/` (FR-012, spec 010).

## `block_ltuse` (web and app)

Plugin: `moodle/block_ltuse/`.
- `$plugin->requires` and `$plugin->supported` are as local_ltuse's (`[502, 502]`).
- `$plugin->dependencies = ['local_ltuse' => <the version that adds the data class>]`.
- Pinned in `moodle/site/site.yaml` with `source: path: moodle/block_ltuse`.
- Block title: none (`hide_header()` returns true). The content is the heading.
- Shown only on the Dashboard (`applicable_formats()`: `my => true`, everything else
  false). One instance (`instance_allow_multiple()` false).
- `version.php` also sets `maturity` and `release`.
- `db/access.php` defines two capabilities, each with archetypes `['manager' => CAP_ALLOW]`
  and no `clonepermissionsfrom`:

  | Capability | `riskbitmask` | `captype` | `contextlevel` |
  |---|---|---|---|
  | `block/ltuse:myaddinstance` | none | `write` | `CONTEXT_SYSTEM` |
  | `block/ltuse:addinstance` | `RISK_SPAM \| RISK_XSS` | `write` | `CONTEXT_BLOCK` |

  Cloning from `moodle/my:manageblocks` would grant `user`, whose archetype allows it until
  apply prevents it. The declaration places the block, and no learner or teacher adds it
  (R4).
- `classes/privacy/provider.php` is a `null_provider` returning `privacy:metadata`.
- Lang ids beyond the modes below: `pluginname`, `ltuse:addinstance`, `ltuse:myaddinstance`,
  `privacy:metadata`.

**Published course**: one whose `idnumber` matches `^ltct:[^:]+$` and is not
`local_ltuse\officehours::COURSE` (`ltct:officehours`). Both the block's continue choice and
the next-lesson hook use this one rule, `learner_home_rules::is_published_course()`.

| Mode | Web content | Strings |
|---|---|---|
| `continue` | Course name (small). Primary button: "Continue: {lesson}" → the lesson. | `continue`, `coursename` |
| `start` | Course name (small). Primary button: "Start: {lesson}" → the lesson. | `start` |
| `empty` | "No course has been assigned to you yet." / "Ask your organisation's language technology coordinator, or contact the site team." Link "Contact the site team" → `/user/contactsitesupport.php`. | `empty`, `empty:who`, `contactsupport` |
| `done` | "You have finished your courses." | `done` |

Beneath any mode, when present, under the heading "Where next" (`onward`):
- "Next on your pathway: {course}" → that course's page (one line per pathway) (`onward:pathway`)
- "Message {mentor}" → core messaging with that mentor (`onward:mentor`)
- "Community: {name}" → the community space (spec 005, absent until declared) (`onward:community`)

The pathway lines are absent when `pathway\view::levels() === null`, and any
`moodle_exception` from `pathway\view::summaries()` is caught and leaves them absent. The
mentor lines read `mentoring::mentors()`, which becomes public, so the block does not call
`mentoring::for_user()`, which builds every mentee's courses.

**App only**, beneath the rest, in muted text (`offline:course`, `offline:quiz`):
- "To use a course without a connection: open it, tap ⋮, then Download course."
- "Open the quiz once while you are online; then you can finish it offline."

These hints appear in the block's app view only. R8's "and on the course page's top
section" has no mechanism in this contract, so it is dropped. FR-007 inside a course is met
by the app's native course-menu Download course, which spec 009 keeps enabled
(`tool_mobile/disabledfeatures` empty) and V6 step 3 verifies. The Home-tab hint tells the
learner where it is.

**Never shown**:
- a completion percentage beside a level;
- any CBC level as held;
- the word "certified" (FR-011; `cbc_wording.py` lists the forbidden forms, and
  `tests/test_learner_wording.py` (pytest) holds every block_ltuse string and the spec 007
  local_ltuse block to `cbc_wording.report_label_problems(strict=True)`);
- a link to anything in a hidden or Retired section;
- any other user's name except the viewer's own mentors.

**Look**:
- The panel is `div.ltuse-home` in `local_ltuse/styles.css`, so it reaches the web and the app
  (through `mobilecssurl`) from one file.
- The styling scopes are `div.ltuse-home` and `div.ltuse-next` (below), and the `styles.css`
  header names both.
- Background `rgba(0, 92, 185, 0.08)`, left rule `#005CB9`, and an `rgba(0, 92, 185, 0.20)`
  rule above "Where next".
- No text colour, and no `prefers-color-scheme` (R11).
- The buttons are Boost's `btn-primary`, so they are SIL Blue.
- `mobilecssurl`'s `?v=` is raised in the same change.

**Not for**: users with `moodle/site:config`. The block renders nothing for them (R12).

### App handler (`block_ltuse/db/mobile.php`)

```php
'block_ltuse' => [
  'handlers' => [
    'ltuse' => [
      'delegate'    => 'CoreBlockDelegate',
      'method'      => 'mobile_block_view',
      'displaydata' => ['title' => 'pluginname'],
    ],
  ],
  'lang' => [ /* pluginname, and every string above */ ],
],
```

The `displaydata` keys were confirmed at T049 against the Moodle app's source (tag `v5.2.1`)
and the devdocs API reference:
- `CoreSitePluginsBlockHandlerData.displaydata` is `{title?, class?, type?}`
  (`src/core/features/siteplugins/services/siteplugins.ts:961-968`).
- `title` is a lang id of the plugin, defaulting to `pluginname`
  (`siteplugins-init.ts:689-690`, `getPrefixedString(addon, key = 'pluginname')` at `:316`);
  the docs mark it required, so it is set, and `pluginname` is in `lang`. The app shows no
  heading for a block rendered by `method` (`core-block.html`), as `hide_header()` does on the web.
- `class` defaults to `block_ltuse` (`block-handler.ts:52`), so it is left out.
- `type` is left out: `title` would show only a button, and `prerendered` the web HTML. Without
  it, the block renders through `method` (`block-handler.ts:83-86`).

`mobile_block_view` returns the same modes from the same data class as `get_content()`.
- `$args`: the app sends `contextlevel`, `instanceid` and `blockid`
  (`components/block/block.ts:65-69`) plus its defaults, including a `userid`
  (`siteplugins.ts:96-106`). None is read: the method answers for `$USER`, which
  `tool_mobile_get_content` runs as the token's user (`admin/tool/mobile/classes/external.php:406-428`).
- Return: `templates` (the first renders the block), `javascript`, `otherdata`
  (`external.php:430-448`).
- The block reaches the app only when its web content is non-empty:
  `core_block_get_dashboard_blocks` (`blocks/classes/external.php:248-304`) lists blocks from
  `get_content_for_all_regions()`, which skips a block whose `get_content_for_output()` is null
  (`lib/blocklib.php:1263-1266`), and that is null when `is_empty()` (`blocks/moodleblock.class.php:250-253`).
  So the site team never gets the block in the app, and the method's empty template for them is
  a second guard.

Links are `core-link` with `capture="true"`, so the app opens a lesson in its own module
page and not the browser. `capture` is an `@Input({transform: toBoolean})`, so the plain
attribute works (`src/core/directives/link.ts:46`, used at `:114`), and the directive's
selector is `[core-link]` (`:38`), so it sits on the `ion-button` itself. Fallback if V6
shows the app does not render it: the same template as a `CoreMainMenuDelegate` "Start
here" item (R7).

## Next-lesson button (web)

Callback: `local_ltuse\hook_callbacks::before_footer`, registered in
`local_ltuse/db/hooks.php` for `core\hook\output\before_footer_html_generation`. It is
dispatched in `core_renderer::footer()` (`:965-967`) before the content containers close, so
the button renders inside `div[role=main]`, directly beneath the lesson content and in its
column. (`after_standard_main_region_html_generation` is printed by Boost after the page
footer, outside `#page`, so it is not beneath the content.)

| Condition | Output |
|---|---|
| not logged in, guest, during install | nothing |
| `$PAGE->pagetype` does not match `^mod-[a-z0-9]+-view$`, or context is not a module | nothing |
| the course is not a published course (`learner_home_rules::is_published_course()`) | nothing |
| a next `cm` exists (R6 order) | one primary button: "Next: {name}" (`nextlesson`) |
| no next `cm` | one secondary button: "Back to the course" (`backtocourse`) |

Acting on the module's view page only, not on every `incourse` page, means there is no Next
in a quiz attempt, a quiz review or a forum discussion.

It is placed in a `div.ltuse-next` so `styles.css` can align it. Nothing in it is
app-specific, because the app does not render the hook (it has its own module navigation,
R6).
