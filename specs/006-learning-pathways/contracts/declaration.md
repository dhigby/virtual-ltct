# Contract: Role pathways, competency pages and level labels (`site_config.py`)

Extends [spec 001's declaration contract](../../001-site-config-as-code/contracts/declaration.md),
[spec 002's](../../002-org-structure-cohorts/contracts/declaration.md) and
[spec 004's](../../004-progress-reporting/contracts/declaration.md). Everything in them still
holds. In particular, `apply` never deletes, and `drift` never prints learner data: no
assignment, no cohort member, no completion, no count of either.

## Files

| File | Holds |
|---|---|
| `moodle/site/pathways.yaml` | New, optional. Role pathways (FR-007, R5). Ships with `roles: []`. |
| `competencies.yaml` (repo root) | Unchanged. Still the competency list (004). Role pathway competency names are checked against it verbatim. |
| `outcome-levels.yaml` (repo root) | Unchanged. Read for the four level labels a pathway shows (FR-003, R6). |
| `competencies/*.md` | Unchanged. Each descriptor's frontmatter `name` and `slug` are read to give a competency its `slug` (R3, R4). |
| `mkdocs.yml` | Unchanged. `site_url` is read to build each competency's `url` (R4). The host is never written in PHP or in `moodle/site/`. |
| `moodle/site/site.yaml` | Changed. `local_ltuse` is pinned at `2026100600`, the version that adds the pathway tables and the two competency columns. |

## `pathways.yaml`

```yaml
rows: [12]
purpose: >-
  Role pathways: a named set of competencies a role needs. Each competency carries its
  competency pathway, which is generated from published courses and never declared here.
roles: []
# A role is added only when a human (the maintainer with the department or the CBC
# programme) supplies it. The shape, for reference:
#
#  - key: translation-support          # ^[a-z][a-z0-9-]*$, at most 95 characters
#    name: Translation team support consultant
#    description: >-
#      Supports a translation team's tools day to day.
#    competencies:                     # verbatim from competencies.yaml, in the order shown
#      - Translation Tools
#      - Keyboards
#    why: "Supplied by <who>, <date>."
```

| Key | Required | Rule |
|---|---|---|
| `rows` | yes | `[12]` |
| `purpose` | yes | text |
| `roles` | yes | a list, possibly empty |
| `roles[].key` | yes | matches `^[a-z][a-z0-9-]*$`; at most 95 characters, so `role:<key>` fits the 100-character pathway key (R3); unique in the file |
| `roles[].name` | yes | text, at most 255 characters |
| `roles[].description` | no | text |
| `roles[].competencies` | yes | a non-empty list of names, each exactly as in `competencies.yaml`; no name twice |
| `roles[].why` | yes | text: who supplied the role, as `rows`/`why` do elsewhere in `moodle/site/` |

A missing `pathways.yaml` is the same as `roles: []`.

### `validate` refuses

- a key that does not match the pattern, is longer than 95 characters, or is declared twice;
- a competency name not in `competencies.yaml`, compared exactly (case, `&` and spacing
  included), or one in the `Meta` category (R12);
- a competency listed twice in one role;
- a role with no competencies;
- a `name` or `description` that fails `cbc_wording.report_label_problems(label, strict=True)`
  (FR-011, R13);
- a `name` or `description` containing any CBC level label from `outcome-levels.yaml`, or a
  level by number (`level 0` to `level 4`): a role names work, never a level a learner holds.

## Competency `slug` and `url`

Each entry of the payload's `competencies` array (004, data-model "Competency list") gains two
fields:

```json
{"name": "Keyboards", "category": "Core Technical", "sortorder": 3,
 "slug": "keyboards",
 "url": "https://competencies.languagetechnology.org/core-technical/keyboards/"}
```

| Field | Source |
|---|---|
| `slug` | the frontmatter `slug` of the descriptor in `competencies/` whose `name` equals the competency's name |
| `url` | `mkdocs.yml` `site_url`, with one trailing `/`, then `gen_site.slugify(category)`, `/`, `slug`, `/` — the page `gen_site.py` generates |

`validate` refuses a competency with no descriptor, a `slug` that is not
`^[a-z0-9][a-z0-9-]*$` or is longer than 94 characters (so `competency:<slug>` fits 100), two
competencies with one `slug`, and a missing or non-`https` `site_url`. `Meta` is still left out
of the array, so `Uncategorized` needs no descriptor (R12).

The plugin stores both in `local_ltuse_competency.slug` and `local_ltuse_competency.url`.
`competencies::differences()` compares them with `category` and `sortorder`, so a hand edit is
`changed` and `apply` sets it back.

## Level labels

A new payload array, `levels`, from `outcome-levels.yaml`: one entry per id in
`course_target_levels`, in that order.

```json
"levels": [
  {"level": 1, "label": "1 - Has Knowledge"},
  {"level": 2, "label": "2 - With Assistance"},
  {"level": 3, "label": "3 - Independent"},
  {"level": 4, "label": "4 - Expert"}
]
```

`validate` refuses a `course_target_levels` that is not exactly `[1, 2, 3, 4]`, and a label
that is not in `cbc_wording.cbc_labels()`. The labels are copied verbatim, never re-typed.

`apply` stores each in the plugin config `local_ltuse/pathwaylevel1` … `local_ltuse/pathwaylevel4`.
The pathway pages read the heading for a level from there, and nowhere else.

## Payload arrays

`build_payload()` adds two arrays, after `competencies` and before `reports`, so a role pathway
is applied against the competency rows the same run has just set:

| Array | Shape |
|---|---|
| `levels` | as above |
| `role_pathways` | `[{key, name, description, sortorder, competencies: [name]}]`, in file order; `sortorder` is the role's position, from 0; `description` is `""` when absent; `why` is not sent |

Both are always present. `role_pathways` is `[]` when no role is declared.

## What `apply` does

| Declared thing | Table or config | Lifecycle |
|---|---|---|
| a level label | `local_ltuse/pathwaylevel<n>` | set when it differs |
| a role | `local_ltuse_role_pathway` (`key` unique) | inserted; `name`, `description`, `sortorder` set back; un-retired if declared again; a live row no longer declared is set `retired = 1`, never deleted, so its cohort assignments still point at something (R5) |
| a role's competencies | `local_ltuse_role_pathway_comp` (role, competency, `sortorder`) | replaced as a set for each declared role; a retired role's rows are kept |

A role competency is resolved against non-retired `local_ltuse_competency` rows by exact name in
PHP, as `set_course_competencies` does. An unresolved name is `unknown` and blocks the run in
preflight; nothing is written.

When `apply` changes a live role's competency set, or retires or un-retires a role, it fires
`\local_ltuse\event\pathway_courses_changed` for `role:<key>` with the courses that joined or
left ([pathway-api.md](pathway-api.md)). A level label or a name change fires nothing.

## Output additions

| Kind | Item | Means |
|---|---|---|
| `changed` | `competency <name>` | as 004, and the message names `slug` or `url` when either differed |
| `missing` (created), `changed` | `pathway level <n>` | the label was absent, or differed and is set back |
| `missing` (created), `changed` | `role pathway <key>` | inserted, or `name`, `description`, `sortorder`, `competencies` or `retired` set back; the message names which |
| `extra` | `role pathway <key>` | live but no longer declared; `apply` retires it and keeps its cohort assignments |
| `unknown` | `role pathway <key>: <name>` | the competency is not a live row on this site. Run `apply` with the competency list first, or correct the name. Blocks the run. |
| `unknown` | `pathway tables` | the installed `local_ltuse` is older than `2026100600`. Run the plugin upgrade first. Blocks the pathway items, not the run. |

A creation follows spec 002's convention: status `changed`, kind `missing`, message `created`.
An item names a role, a competency, a level or a label only. It never names a cohort, a course
or a user, and never carries a count of any of them (constitution III).
