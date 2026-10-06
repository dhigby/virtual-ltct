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

**App only**, beneath the rest, in muted text (`offline:course`, `offline:quiz`):
- "To use a course without a connection: open it, tap ⋮, then Download course."
- "Open the quiz once while you are online; then you can finish it offline."

**Never shown**:
- a completion percentage beside a level;
- any CBC level as held;
- the word "certified" (FR-011; `cbc_wording.py` lists the forbidden forms, and a PHP unit
  test asserts the lang file contains none of them);
- a link to anything in a hidden or Retired section;
- any other user's name except the viewer's own mentors.

**Look**:
- The panel is `div.ltuse-home` in `local_ltuse/styles.css`, so it reaches the web and the app
  (through `mobilecssurl`) from one file.
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
      'displaydata' => ['type' => 'template'],
    ],
  ],
  'lang' => [ /* every string above */ ],
],
```

`mobile_block_view` returns the same modes from the same data class as `get_content()`.
Links are `core-link` with `capture="true"`, so the app opens a lesson in its own module
page and not the browser. Fallback if V6 shows the app does not render it: the same
template as a `CoreMainMenuDelegate` "Start here" item (R7).

## Next-lesson button (web)

Callback: `local_ltuse\hook_callbacks::after_main_region`, registered in
`local_ltuse/db/hooks.php` for `core\hook\output\after_standard_main_region_html_generation`.

| Condition | Output |
|---|---|
| not logged in, guest, during install | nothing |
| page layout is not `incourse`, or context is not a module | nothing |
| course `idnumber` does not start with `ltct:` | nothing |
| a next `cm` exists (R6 order) | one primary button: "Next: {name}" (`nextlesson`) |
| no next `cm` | one secondary button: "Back to the course" (`backtocourse`) |

It is placed in a `div.ltuse-next` so `styles.css` can align it. Nothing in it is
app-specific, because the app does not render the hook (it has its own module navigation,
R6).
