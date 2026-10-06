# Contract: the default dashboard declaration

**Amends**: [specs/011-events-calendar/contracts/declaration.md](../../011-events-calendar/contracts/declaration.md),
its `dashboard` section only. Everything else in that contract stands.

**Read by**: `scripts/site_config.py` (`validate`, `drift`, `apply`) and
`moodle/local_ltuse/classes/siteconfig/dashboard.php`.

## File: `moodle/site/dashboard.yaml`

```yaml
rows: [13, 21]
purpose: >-
  What a learner sees on their dashboard: one way into their course, their course list,
  and upcoming events. Nothing else.
complete: true            # new: the list below is the whole default page
default_blocks:
  - block: ltuse
    region: content
    weight: 0
    why: ...
  - block: myoverview
    region: content
    weight: 1
    why: ...
  - block: calendar_upcoming
    region: side-pre
    why: ...              # 011's entry, unchanged
personal_dashboards: reset   # new: reset | keep
```

| Key | Type | Rule |
|---|---|---|
| `complete` | bool, default `false` | `true`: apply removes from the default page every block not listed. `false`: 011's additive rule. |
| `default_blocks[].weight` | int, optional | Order within the region. When it is given, drift reports a block at another weight. Without it, position is not checked (011's behaviour). |
| `personal_dashboards` | `reset` · `keep`, default `keep` | `reset`: apply calls `my_reset_page_for_all_users(MY_PAGE_PRIVATE, 'my-index')` when any personal dashboard exists. Allowed only while `roles.yaml` prevents `moodle/my:manageblocks` for `user`; `validate` refuses it otherwise. |

## Behaviour

**validate** refuses:
- a block named twice;
- a block that is neither a core block nor a plugin declared in `site.yaml`;
- `personal_dashboards: reset` without the `manageblocks` prevent (R4).

**drift** reports each of these, one line each:
- a declared block absent from the default page;
- *(complete only)* a block present on the default page and not declared;
- a declared `weight` that differs;
- `personal dashboards: N` when N > 0 and `reset` is declared.

It never names a user.

**apply**, in this order:
1. adds missing blocks (011's `add_region()` then `add_block()`);
2. *(complete only)* deletes undeclared default-page block instances with core's
   `blocks_delete_instance()`;
3. sets declared weights;
4. *(reset only)* resets personal dashboards.

Each step is idempotent. A second apply reports nothing to do.

**Never**: touches a block on any page but the default `my-index` page; edits a block's
configuration; resets dashboards while editing is allowed.
