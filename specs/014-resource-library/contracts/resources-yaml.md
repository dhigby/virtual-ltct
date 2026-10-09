# Contract: `resources.yaml`

The library's only source of truth, at the repo root next to `competencies.yaml` (R2).
Read by `scripts/gen_site.py`, `scripts/check_competency_descriptors.py` and
`scripts/check_resource_links.py`.

## Shape

A top-level YAML list. One mapping per resource:

```yaml
- title: Installing a Keyman keyboard        # required, non-empty string
  url: https://help.keyman.com/...           # required, http:// or https://
  description: One line saying what it covers. # required, non-empty string
  type: guide                                # required, one of: guide, video, site, document
  competencies:                              # required, non-empty list
    - Keyboards                              # each must match competencies.yaml exactly
  language: English                          # required, interface language of the resource
```

## Rules (enforced by `check_competency_descriptors.py`, exit 1 on any break)

- Every field above is present and non-empty.
- `type` is exactly one of `guide`, `video`, `site`, `document`.
- `url` starts with `http://` or `https://`. No `modules/` paths, no repo files (FR-012).
- Every `competencies` entry is a name in `competencies.yaml`, copied verbatim,
  including `&` and capitalization (FR-002).
- A description about a named language's script or orthography comes from a human;
  until then it is a marked placeholder (Principle VIII).

## Removed

`competencies/*.md` frontmatter no longer carries `resources:`. The check reports a
descriptor that still has one, so the lists cannot drift back in.
