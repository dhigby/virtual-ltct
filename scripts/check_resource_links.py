#!/usr/bin/env python3
"""Report broken links in resources.yaml (spec 014, FR-007).

One HEAD request per url, retried as GET when HEAD fails (many servers refuse HEAD).
Redirects are followed. A url is broken when the last answer is 4xx/5xx or the request
fails, except the answers in REFUSED, which mean a bot screen, not a missing page.
Prints one `BROKEN` line per broken link, then a count, which the weekly workflow pastes
into the `Broken resource links` issue.

Run:  python scripts/check_resource_links.py [path/to/resources.yaml]
Exit: 0 every link answered · 1 one or more broken · 2 resources.yaml missing or unreadable.
Requires: pyyaml. Read-only.
"""
import pathlib
import sys
import urllib.error
import urllib.request

import yaml

REPO = pathlib.Path(__file__).resolve().parent.parent
# Some sites refuse Python's default agent outright, which would read as a broken link.
# A server answering one of these is up but refusing an automated request (Cloudflare and
# similar bot screens). Counting them as broken would report the same live pages weekly.
# simplified: a page that really is gone behind a bot screen goes unreported until a person
# opens it. If that starts to bite, print those urls as a separate "refused" list.
REFUSED = {"401", "403", "418", "429"}
HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; ltct-link-check; "
                         "+https://github.com/dhigby/virtual-ltct)"}


def _ask(url, method):
    req = urllib.request.Request(url, method=method, headers=HEADERS)
    try:
        with urllib.request.urlopen(req, timeout=20):
            return None
    except urllib.error.HTTPError as e:
        return str(e.code)
    except (urllib.error.URLError, OSError) as e:
        return str(getattr(e, "reason", e))


def check(url):
    """None when the url answers, else the status code or error that broke it."""
    problem = _ask(url, "HEAD") and _ask(url, "GET")
    return None if problem in REFUSED else problem


def main(path=REPO / "resources.yaml"):
    try:
        entries = yaml.safe_load(pathlib.Path(path).read_text(encoding="utf-8")) or []
    except (OSError, yaml.YAMLError) as e:
        print(f"cannot read {path}: {e}")
        return 2
    broken = 0
    for r in entries:
        problem = check(r["url"])
        if problem:
            broken += 1
            print(f"BROKEN {problem}  {r['url']}  ({r.get('title', '')})")
    print(f"{broken} broken link(s) of {len(entries)} checked.")
    return 1 if broken else 0


if __name__ == "__main__":
    sys.exit(main(*sys.argv[1:2]))
