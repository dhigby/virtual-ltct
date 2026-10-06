# `block_ltuse` — the learner home block

One block on the default Dashboard that tells a learner where to go next (spec 007, row #13;
[contracts/learner-ui.md](../../specs/007-learner-experience/contracts/learner-ui.md)). Core
has nothing that does this (research R3): its course list shows progress, but no "continue
here", no message for a learner with no course yet, and no route on from a finished one.

Built 2026-10-06 and **not yet verified on the instance** (constitution X); spec 007's
quickstart V1-V9 are the live checks.

## What it shows

Exactly one of four modes, for the person viewing, with no header (the content is its own
heading):

| Mode | When | Shows |
|---|---|---|
| continue | A published course they have not finished, with a lesson in it complete | "Course: <name>" and one button, "Continue: <lesson>", to the first lesson they can open and have not completed |
| start | The course chosen has no lesson complete yet | The same, with "Start: <lesson>" |
| empty | No published course is assigned to them | "No course has been assigned to you yet.", who to ask, and a link to core's Contact site support form |
| done | They have published courses, and none has a lesson left to offer | "You have finished your courses." |

The course is the one they opened most recently that is not complete, or, if they have
opened none, the one they were given last.

Beneath any mode, under **Where next**, the onward routes when there are any: the next course
on each pathway they hold (spec 006), a message link to each of their own mentors (spec 003),
and the community space once spec 005 declares one. A route with nothing behind it is left
out, and the heading never shows alone.

**In the Moodle app** the block appears on the Home tab, with the same mode, lesson and
words, in Ionic markup. A lesson opens in the app's own module page, not the browser, and two
hints say how to take a course and a quiz offline (R7, R8). Two things are still unverified
(R7, quickstart V6): that the app 5.2.x renders a site-plugin block on the Home tab, and that
the free app plan allows it. The block's styles reach the app only through `mobilecssurl`,
which the app applies on the Premium plan alone, so on the free plan it shows unstyled. If
the handler fails, the fallback is a `CoreMainMenuDelegate` item, as Mentoring has.

## Components

| File | Does |
|---|---|
| `block_ltuse.php` | The block (`block_base`). Dashboard only (`applicable_formats()`), one instance, no header, no settings. `get_content()` renders `block_ltuse/block` from `home::context()`, and is empty for a guest and for the site team. |
| `classes/output/home.php` | The shared context builder: turns `local_ltuse\learner_home::state()` into the template context, with every visible word from `get_string()`. The web block and the app handler both call it, so they cannot differ. Autoloaded, because `block_ltuse.php` is not and an app request could not reach it. |
| `classes/output/mobile.php` | `mobile_block_view()`, the app handler. Answers for the signed-in app user (`$USER`, the token's user), never for a userid the app sends, and renders `block_ltuse/mobile_block` from the same context. |
| `db/mobile.php` | Registers the handler with the app's `CoreBlockDelegate`, rendered through `method`, and lists the strings the app needs. |
| `templates/block.mustache`, `templates/mobile_block.mustache` | The web markup (Bootstrap) and the app markup (Ionic). Both are styled by `div.ltuse-home` in `local_ltuse/styles.css`, which the app reads through `mobilecssurl`. |
| `db/access.php` | `block/ltuse:myaddinstance` and `block/ltuse:addinstance`, granted to `manager` alone and cloned from nothing, so no learner or teacher can add the block (R4). |
| `lang/en/block_ltuse.php` | Every word the block shows. Held to `scripts/cbc_wording.py`'s strict rule by `tests/test_learner_wording.py`. |
| `classes/privacy/provider.php` | A null provider: the block stores no personal data. |
| `tests/block_test.php` | PHPUnit, run in plugin CI. |

## It holds no data, and depends on local_ltuse

The block has no tables, no settings and no configuration per instance. Everything it shows
is derived when it is shown, by `local_ltuse\learner_home` (which courses, which lesson,
which mode, which routes), and nothing is stored. That is why `version.php` declares a
dependency on `local_ltuse`: the block cannot install without the version that has
`learner_home`. Which courses count, and the rules for the lesson to offer, are described in
[local_ltuse's README](../local_ltuse/README.md#the-learner-experience-spec-007).

## Install

Never by hand. The block is pinned in [`moodle/site/site.yaml`](../site/site.yaml) by path
(`moodle/block_ltuse`, at its `version.php` stamp; `validate` fails if they differ), copied to
`public/blocks/ltuse` after `local_ltuse`, and placed on the default Dashboard by
[`moodle/site/dashboard.yaml`](../site/dashboard.yaml) when `site_config.py apply` runs. The
deploy order and the archive command are in
[local_ltuse's Install section](../local_ltuse/README.md#install). Adding it to a page in the
admin UI is not done: `drift` reports the default page as it is, and `apply` puts it back.

## What it relies on (Principle XI)

Checked against `MOODLE_502_STABLE` source, Moodle 5.2.3+; `version.php` pins `requires` and
`supported` to 5.2, so a major upgrade stops at the plugin check until it is re-verified.

- `block_base` (`blocks/moodleblock.class.php:46`): `get_content()`, `applicable_formats()`,
  `instance_allow_multiple()`, `hide_header()` and `has_config()`;
- the Moodle app's `CoreBlockDelegate` and site-plugin `method` rendering (moodleapp v5.2.1),
  served by `tool_mobile_get_content`;
- `core_block_get_dashboard_blocks` (`blocks/classes/external.php:248`), through which the
  app lists the Dashboard's blocks. It reads them with `block_manager::get_content_for_all_regions()`
  (`:112`; `lib/blocklib.php:332`), which leaves out a block whose web content is empty, so the
  site team sees no block in the app either;
- through local_ltuse, the APIs listed under its
  [What the plugin relies on](../local_ltuse/README.md#what-the-plugin-relies-on-and-why-principle-xi).

## What it deliberately does not do

- **No level.** It never says which CBC level a learner holds; a course is named, not graded.
- **Never "certified".** Finishing says the training is finished, nothing more
  (`scripts/cbc_wording.py`).
- **No link into a hidden section.** A lesson that is hidden, or in the Retired section, is
  never offered, because the rule follows what core lets the learner open.
- **Nothing for site admins.** The site team's Dashboard is not a learner's; the block is
  empty for anyone who can configure the site.
- **No other person's data.** The only other people it names are the learner's own mentors.

## Licence

The plugin's code is © 2026 SIL Global and licensed GNU GPL v3 or later, the header every
PHP file carries. Moodle requires that for a plugin, which builds on Moodle's GPL code, and its
code checker accepts no other header. The curriculum this repository publishes is a different
work, under CC BY-SA 4.0 (Doug, 2026-10-06).
