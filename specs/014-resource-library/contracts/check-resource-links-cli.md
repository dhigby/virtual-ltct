# Contract: `scripts/check_resource_links.py`

Checks every `url` in `resources.yaml` (R6). Standard library only (`urllib`).

## Invocation

```bash
python scripts/check_resource_links.py
```

No required arguments. Reads `resources.yaml` from the repo root.

## Behaviour

- One HEAD request per url; on a HEAD failure or 405, retry with GET.
- A url is broken when the final response is 4xx or 5xx, or the request fails
  (DNS, connection, timeout).
- Redirects are followed and are not broken.
- 401, 403, 418 and 429 are not broken: the server is up and refusing an automated
  request (a bot screen), which a browser gets past.

## Output (stdout)

One line per broken link, then a count:

```text
BROKEN <status or error>  <url>  (<title>)
N broken link(s) of M checked.
```

Nothing but the count line when all links pass. The workflow pastes this output into
the issue body.

## Exit codes

| Code | Meaning |
|---|---|
| 0 | every link answered |
| 1 | one or more links broken |
| 2 | `resources.yaml` missing or unreadable |

## Workflow

`.github/workflows/resource-links.yml` runs it weekly (SC-004). On exit 1 it opens, or
updates, a single open issue titled `Broken resource links`. It never opens a second one.
