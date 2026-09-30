# `local_ltuse` — the publish endpoint

A small Moodle local plugin exposing the few web service functions the LTC curriculum
publisher needs. Moodle has no core web service that writes a quiz; these four functions
close that gap, each a thin wrapper over the same internal APIs the web UI calls. After
installing it, [`scripts/publish_moodle.py`](../../scripts/publish_moodle.py) talks plain
REST, and the contract is defined around our content model rather than bent to fit
someone else's.

It lives in this repo rather than its own because it and the publisher are two halves of
one contract: a change to the page shape or the quiz model touches both, and one pull
request should carry both. [`INTENT.md`](../../INTENT.md) names it, with the publisher,
as the bridge between the repo's two products — the only code that knows both a course's
shape and Moodle's.

## What it adds

| Function | Type | Does |
|---|---|---|
| `local_ltuse_get_course_manifest` | read | Returns the repo→Moodle map for one course: its sections, and every module carrying an `ltct:` idnumber. |
| `local_ltuse_create_page` | write | Creates or updates one `mod_page`, addressed by course-module idnumber. |
| `local_ltuse_import_questions` | write | Imports Moodle XML into a category in the course question bank, creating the `mod_qbank` instance and category if needed. |
| `local_ltuse_create_quiz` | write | Creates or updates a `mod_quiz` and rebuilds its slots as references into that category. |

Course create/update stays on core (`core_course_create_courses`,
`core_course_update_courses`, `core_course_get_courses_by_field`) and section handling on
[`local_wsmanagesections`](https://moodle.org/plugins/local_wsmanagesections). Neither is
duplicated here.

`get_course_manifest` is the one function not in the original sketch, and the publish is
not idempotent without it: `core_course_get_contents` does not reliably return a module's
idnumber, so there is otherwise no way to ask Moodle which modules a previous publish
created. With it, republishing is a diff rather than a blind re-create.

## Identity, and why republishing does not duplicate

Every object the publisher creates carries an idnumber:

```
course         ltct:<slug>
course module  ltct:<slug>:<source filename>      e.g. ltct:bloom:01-what-bloom-is.md
```

That is the whole idempotency story, and it lives **in Moodle**, not in a state file in
the repo. Nothing new to keep honest, it survives someone else republishing, and moving
to a different Moodle server is a re-publish rather than a data move.

## Install

```bash
# On the Moodle server, from the Moodle root:
cp -r /path/to/virtual-ltct/moodle/local_ltuse local/ltuse
php admin/cli/upgrade.php
```

Then, in Site administration:

1. **Plugins → Web services → Overview** — enable web services and the REST protocol.
2. **Users → Permissions → Define roles** — create a role with `local/ltuse:publish`
   (and `moodle/question:add`, `moodle/course:manageactivities`) and assign it to the
   publishing account. The capability is deliberately in no archetype: this token
   rewrites course content wholesale.
3. **Plugins → Web services → External services** — the service *LTC curriculum
   publishing* is declared by the plugin. It is `restrictedusers`, so add the publishing
   account under **Authorised users**.
4. **Plugins → Web services → Manage tokens** — create a token for that account on that
   service. That is `MOODLE_TOKEN`.
5. **Appearance → Themes → Mobile appearance** — set `mobilecssurl` to
   `/local/ltuse/styles.css` so published callouts and screenshots render correctly in
   the Android app.

The repo is public, so the token goes in the environment and never in a file here.

## Verified against Moodle 5.2.3+ (2026-09-29)

Installed and exercised end to end on Moodle 5.2.3+ (Build 20260928), PHP 8.3, PostgreSQL
16. A full publish of `coretech-computer-hardware` creates 6 pages, 7 sections and a
27-question quiz, and republishing changes nothing. The four things the first draft asked
you to verify have now been settled, three of them by failing:

1. **`mod_qbank`** works as assumed. `util::ensure_qbank()` creates the instance in
   section 0 and `qformat_xml` imports into a category inside its context.
2. **`quiz_add_quiz_question()` still resolves** in 5.2 and adds slots correctly.
3. **`quiz_update_sumgrades()` is gone.** Grade calculation moved into
   `mod_quiz\grade_calculator`, reached via `quiz_settings::create($id)
   ->get_grade_calculator()->recompute_quiz_sumgrades()`. Without it the quiz reports a
   maximum grade of zero, which reads like a failed import rather than a grading bug.
4. **`questions_in_category()` must filter on `status = ready`, not `<> draft`.**
   `question_delete_question()` will not delete a question a quiz slot references -- it
   hides it, to keep attempt history readable -- so a republish leaves the retired copies
   behind. Counting those too gave a quiz with exactly twice the questions it should have.

Three further things this cost, worth knowing if you port it:

- **`add_moduleinfo()` needs `module`** (the numeric `modules.id`), not just
  `modulename`. `course_modules.module` is NOT NULL, and the web UI supplies it from a
  hidden form field.
- **`get_moduleinfo_data()` returns five values**, `[$cm, $context, $module, $data, $cw]`.
  Destructuring two hands back the context object and fails much later with an empty
  module name.
- **mod_quiz's password field is `quizpassword`** on the form; `quiz_add_instance()` does
  `$quiz->password = $quiz->quizpassword`, so setting `password` alone is discarded and
  the insert fails on a NOT NULL column you believe you set. `create_quiz::quiz_defaults()`
  fills every quiz column from the live column metadata for this reason.

`$plugin->requires` is pinned to `2026042000` (Moodle 5.2) -- the release this was
verified against. Raise it deliberately, not reflexively: the pin is what makes a
question-bank change fail at install time rather than mid-publish.

**`local_wsmanagesections` is no longer used.** It could not be installed here, so
`update_sections` wraps core's `course_create_sections_if_missing()` and
`course_update_section()` instead. The publisher now depends on nothing but Moodle core
and this plugin.

## What it deliberately does not do

- **No Moodle → repo sync.** One-way only. Editing in Moodle and syncing back would break
  the source-of-truth split the whole repo rests on. Content edited in Moodle is
  overwritten by the next publish; change the markdown instead.
- **No enrolment, grades or learner records.** This plugin publishes content and touches
  no learner data. Learner data is Moodle's alone (`INTENT.md`: *"Learner data lives in
  Moodle, never in this repo"*); admin tooling that works with it is a separate concern.
- **No direct table writes** for anything a Moodle API covers. Bypassing
  `add_moduleinfo()` / `update_moduleinfo()` would skip grade items, completion, events
  and the file API, and leave a course that looks right until one of those is needed.
