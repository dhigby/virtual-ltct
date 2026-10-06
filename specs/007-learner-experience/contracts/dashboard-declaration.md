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

## Payload

`validate` turns the file into three sibling keys of the payload:

| Key | Shape | Default |
|---|---|---|
| `dashboard` | list of `{block, region, weight?}`; `weight` is present only when declared | `[]` |
| `dashboard_complete` | bool | `false` |
| `dashboard_personal` | `"reset"` · `"keep"` | `"keep"` |

`dashboard` keeps 011's list shape, so `inspector::entries()` still reads it unchanged.

## Terms

- **Default page**: the `my_pages` row with `userid` null, `name = '__default'` and
  `private = 1`.
- **On the default page**: a `block_instances` row with `parentcontextid` = the system
  context, `pagetypepattern = 'my-index'` and `subpagepattern` = the default page's id, in
  any region. This is the only set of instances the dashboard step reads or changes.
- **Personal dashboard**: a `my_pages` row with `userid IS NOT NULL`, `name = '__default'`
  and `private = 1`. The per-user `__courses` rows are not personal dashboards and are not
  counted.

## Behaviour

**validate** refuses:
- a block named twice;
- a block that is neither a core block (`STANDARD['block']`) nor a plugin pinned in
  `site.yaml` as `block_<name>`;
- `ltuse` declared anywhere but region `content`, weight `0` (data-model §1);
- `personal_dashboards: reset` unless the `user` entry in `roles.yaml` declares
  `moodle/my:manageblocks` as exactly `prevent`. `prohibit` is refused too, because no role
  could ever allow it back (R4).

**drift** reports each of these, one line each, under these item names:

| Item | Result | When |
|---|---|---|
| `dashboard <block>` | missing | a declared block is not on the default page |
| `dashboard <block>` | extra | *(complete only)* a block on the default page is not declared |
| `dashboard <block> weight` | changed | a declared `weight` differs from the live one |
| `dashboard personal dashboards` | changed (declared `reset`, live `N`) | `reset` is declared and N > 0 |

The region of a live instance is not compared (011's `present()`): a declared block live in
another region is neither added, deleted nor reweighted. `reset` with a count of 0 reports no
line, whether or not editing is prevented. Drift never names a user.

**apply**, in this order:
1. adds missing blocks (011's `add_region()` then `add_block()`), each at its declared
   weight, or `0` when none is declared, so a second apply finds nothing to do;
2. *(complete only)* deletes every undeclared instance on the default page with core's
   `blocks_delete_instance()`. It is not limited by region, so a block in `side-post` is
   removed too;
3. sets declared weights;
4. *(reset only, and only when the count is above 0)* reads the **live** permission of
   `moodle/my:manageblocks` for the `user` role at system context, and refuses the reset
   with a `fail` line unless it is `CAP_PREVENT`. Otherwise it resets personal dashboards.
   Roles are applied earlier in the same run and the dashboard step re-plans after them, so
   a first apply passes.

Each step is idempotent. A second apply reports nothing to do.

**Never**: touches a block on any page but the default `my-index` page; edits a block's
configuration; resets dashboards while editing is allowed.
