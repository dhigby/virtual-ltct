# Contract: Pathway pages, navigation and the app

The routes, parameters and identifiers a learner, mentor, manager or test reaches pathways by.
Every page is built from one view model, so the browser and the Moodle app cannot differ
(R6, R10). Nothing here writes a pathway: the only write is a cohort assignment, through
[pathway-api.md](pathway-api.md).

## Page: Pathways (`/local/ltuse/pathways.php`)

`require_login()`; guests are refused. Read only.

| Parameters | Shows |
|---|---|
| none | the viewer's own pathways: every key `assignments::pathways_for_user($USER->id)` returns, each folded to a summary (title, "N of M courses completed", the next course), then a "Browse all pathways" link |
| `key=<pathway key>` | one pathway in full for the viewer |
| `browse=1` | every key `catalogue::all()` returns, as links; competency pathways grouped by category, then role pathways |
| `userid=<id>` | as "none", for that learner |
| `userid=<id>&key=<pathway key>` | one pathway in full, for that learner |

| Parameter | Type |
|---|---|
| `key` | `PARAM_RAW`, then matched against `catalogue::KEY_PATTERN`; an unmatched or unknown key is a "This pathway does not exist" notice, not an error page |
| `browse` | `PARAM_BOOL` |
| `userid` | `PARAM_INT`; absent or equal to `$USER->id` means the viewer |

**Access with `userid`.** `\local_ltuse\pathway\viewer::may_view($viewerid, $learnerid, $facts)`
must be true (R8): the viewer is the learner; or holds `local/ltuse:viewmenteeprogress` in the
learner's user context; or `organisation\access::is_org_member_of_manager()` holds; or has
`moodle/site:config`. Otherwise the page throws `required_capability_exception` for
`local/ltuse:viewmenteeprogress`, and shows nothing of the learner, not even their name (US4.2,
FR-012). The learner's name is shown with `fullname()` only.

### One pathway (`key`)

From `\local_ltuse\pathway\builder`:

- A **competency pathway** is four level rows, `1 - Has Knowledge` to `4 - Expert`, each headed
  by the label in `local_ltuse/pathwaylevel<n>` and nothing else (FR-003). Under each row, the
  delivered courses aiming there, by course full name, each linked to `/course/view.php?id=`,
  never locked (FR-008), each with one status: `completed`, `inprogress` or `notstarted`
  (`mentoring::progress_status()`, R7). A row with no course shows the `pathway:nocourseyet`
  string, linked to the competency's `url` (FR-004).
- The **next** course is the first, in that order, whose status is not `completed`. It is marked
  with the `pathway:next` string and the `cx-pathway-next` class (US1.2).
- When every listed course is `completed`, the pathway shows `pathway:done` and no next course
  (US1.4, FR-011).
- A **role pathway** shows its competencies in declared order, each with its competency pathway
  folded beneath it, and `pathway:roletotal`, counting a course once however many of the role's
  competencies it serves (R6).

### Strings (`lang/en/local_ltuse.php`, block "Spec 006: pathways")

| Identifier | English |
|---|---|
| `pathways` | `Pathways` |
| `pathway:mine` | `Your pathways` |
| `pathway:none` | `No pathway has been given to you yet. You can browse every pathway.` |
| `pathway:browse` | `Browse all pathways` |
| `pathway:aimsat` | `Aims at {$a}` |
| `pathway:nocourseyet` | `No course yet` |
| `pathway:nocourseyetlink` | `See what this competency involves` |
| `pathway:next` | `Next` |
| `pathway:completed` | `Completed` |
| `pathway:inprogress` | `In progress` |
| `pathway:notstarted` | `Not started` |
| `pathway:done` | `You have completed the training on this pathway.` |
| `pathway:roletotal` | `{$a->done} of {$a->total} courses completed` |
| `pathway:unknown` | `This pathway does not exist.` |
| `pathway:manage` | `Assign pathways` |
| `pathway:assign` | `Assign` |
| `pathway:unassign` | `Remove` |
| `pathway:cohortprogress` | `Pathway progress: {$a}` |

`pathway:nocourseyet` is FR-004's "no course yet". `pathway:done` is US1.4's "completed the
training on the pathway". No string names a level as anyone's: every one passes
`cbc_wording.report_label_problems(label, strict=True)`, checked by
`tests/test_pathway_wording.py` over the whole block (R13, SC-004).

## Page: Assign pathways (`/local/ltuse/pathways_manage.php`)

`require_login()`. Lists only the cohorts `assignments::may_assign($USER->id, $cohortid)` allows;
a viewer with none gets an empty page and no navigation item.

| Parameters | Effect |
|---|---|
| none | the cohorts the viewer may assign to, each with its assigned keys |
| `cohortid=<id>` | one cohort: its assigned keys, an "Assign" form over `catalogue::all()`, and the progress table |
| `cohortid=<id>&action=assign&key=<key>&sesskey=` | `require_sesskey()`, `may_assign()`, then `assignments::assign($key, $cohortid)`; redirects to `cohortid=<id>` |
| `cohortid=<id>&action=unassign&key=<key>&sesskey=` | as above, `assignments::unassign($key, $cohortid)` |

| Parameter | Type |
|---|---|
| `cohortid` | `PARAM_INT` |
| `action` | `PARAM_ALPHA`, `assign` or `unassign` |
| `key` | `PARAM_RAW`, matched against `catalogue::KEY_PATTERN` |

A `cohortid` the viewer may not assign to throws `required_capability_exception` for
`moodle/cohort:assign`, before anything about the cohort is read.

**Progress table** (`cohortid` given): one row per cohort member, each with the `pathway:roletotal`
figure for each assigned key and a link to `pathways.php?userid=<id>`. Members are read only for a
cohort `may_assign()` allows, so a manager sees only their own organisation's learners (US5.2,
FR-012). No level, no grade, no quiz answer.

## Navigation

- **Primary navigation**: a `pathways` item, linking to `/local/ltuse/pathways.php`, for every
  signed-in user who is not a guest, added by the existing
  `\local_ltuse\hook_callbacks::primary_extend` (FR-009). Node key `local_ltuse_pathways`.
- **Primary navigation**: a `pathway:manage` item, linking to `/local/ltuse/pathways_manage.php`,
  only when the viewer may assign to at least one cohort. Node key `local_ltuse_pathways_manage`.
- **Mentoring page**: each learner row in `mentoring.mustache` gains a `pathways` link to
  `/local/ltuse/pathways.php?userid=<id>` (US4.1).

## Moodle app (`db/mobile.php`)

```php
'pathways' => [
    'delegate'    => 'CoreMainMenuDelegate',
    'method'      => 'pathways_view',            // \local_ltuse\output\mobile::pathways_view
    'init'        => 'pathways_init',            // never disabled for a signed-in user
    'displaydata' => ['title' => 'pathways', 'icon' => 'fa-route'],
    'priority'    => 490,
],
```

Added beside 003's `mentoring` handler. `lang` gains `['pathways', 'local_ltuse']`,
`['pathway:nocourseyet', 'local_ltuse']` and `['pathway:done', 'local_ltuse']`.

`pathways_view($args)` takes `key` (optional, as the page) and renders
`mobile_pathways.mustache` from the same `builder` output as the page. With no `key` it lists the
user's pathways; with one it shows that pathway. It takes no `userid`: in the app, a person sees
only their own pathways (FR-015). It carries no images.

## Templates

| Template | Context |
|---|---|
| `local_ltuse/pathways` | the list views (none, `browse`, `userid`) |
| `local_ltuse/pathway` | one pathway |
| `local_ltuse/pathways_manage` | the assign page and its progress table |
| `local_ltuse/mobile_pathways` | the app view |

The context `builder` produces, and that every template reads:

```json
{
  "key": "competency:keyboards",
  "title": "Keyboards",
  "kind": "competency",
  "levels": [
    {"level": 1, "label": "1 - Has Knowledge",
     "courses": [{"courseid": 12, "fullname": "…", "url": "…/course/view.php?id=12",
                  "status": "completed", "next": false}],
     "nocourseyet": false, "competencyurl": null},
    {"level": 2, "label": "2 - With Assistance", "courses": [],
     "nocourseyet": true, "competencyurl": "https://competencies.languagetechnology.org/core-technical/keyboards/"}
  ],
  "done": false,
  "total": 1, "completed": 1
}
```

A role pathway has `"kind": "role"`, `"competencies": [<a competency context as above>]` in
declared order, and `total`/`completed` counted over distinct courses. The context never holds a
learner's level, and has no field a template could show one from.
