# Contract: site settings declared by this spec

One new file, `moodle/site/settings/caching.yaml`, and two additions to the existing
`moodle/site/settings/mobile.yaml`. Both follow the shape in `moodle/site/README.md`: a
`rows:` list, a `purpose:`, and a `why:` on every setting. They are applied with
`python scripts/site_config.py apply` and checked with `drift`. Names and defaults are
confirmed in research.md R5.

## `moodle/site/settings/caching.yaml` (new)

```yaml
# Row #5: pages stay light on repeat visits.
rows: [5]
purpose: >-
  Browser and server caching on. Every value is Moodle's default, declared so drift catches
  anyone who turns designer mode on or caching off -- each of which makes every page heavier
  for every learner on a slow connection.
settings:
  - {name: cachejs,           value: 1, why: "..."}
  - {name: yuicomboloading,   value: 1, why: "..."}
  - {name: cachetemplates,    value: 1, why: "..."}
  - {name: themedesignermode, value: 0, why: "..."}
  - {name: langstringcache,   value: 1, why: "..."}
  - {name: slasharguments,    value: 1, why: "..."}
```

## `moodle/site/settings/mobile.yaml` (rows becomes `[4, 5]`)

```yaml
  - {name: tool_mobile/autologout,  value: 0, why: "an offline learner is never logged out holding an unsynced quiz attempt"}
  - {name: tool_mobile/forcelogout, value: 0, why: "same"}
```

## Deliberately not declared

| Setting | Why not | Owner |
|---|---|---|
| `$CFG->filelifetime` | `config.php` only, with no admin setting | 015 provisioning |
| HTTP compression | web server or PHP, with no core setting for pages | 015 |
| cache stores (MUC/Redis) | host-dependent | 015 |
| `tool_mobile/minimumversion` | would lock out learners on older app versions | revisit after the device check |
| `quiz/allowofflineattempts` | does not exist; it is set per quiz by `local_ltuse` | [local_ltuse.md](local_ltuse.md) |
