"""The resource library's rules, defined once (spec 014).

`resources.yaml` at the repo root is the library's only source of truth. This module
loads it, validates it against `competencies.yaml`, and renders the library page and each
competency page's Further Information section. Imported by `check_competency_descriptors.py`
(the CI gate) and `gen_site.py` (the site build). Not run directly.
"""
import re

import yaml

FIELDS = ("title", "url", "description", "type", "competencies", "language")
TYPES = ("guide", "video", "site", "document")


def load(path):
    return yaml.safe_load(path.read_text(encoding="utf-8")) or []


def framework_names(path):
    return {n for names in yaml.safe_load(path.read_text(encoding="utf-8")).values()
            for n in names}


def validate(entries, framework):
    """Return a list of problems; empty means the file is valid (contracts/resources-yaml.md)."""
    if not isinstance(entries, list):
        return ["resources.yaml must be a list of resources"]
    errors = []
    for i, r in enumerate(entries, start=1):
        where = f"resources.yaml entry {i}"
        if not isinstance(r, dict):
            errors.append(f"{where}: must be a mapping with {', '.join(FIELDS)}")
            continue
        where = f"{where} ({r.get('title') or 'untitled'})"
        for key in FIELDS:
            value = r.get(key)
            if not value or (isinstance(value, str) and not value.strip()):
                errors.append(f"{where}: missing `{key}`")
        if r.get("type") and r["type"] not in TYPES:
            errors.append(f"{where}: `type` must be one of {', '.join(TYPES)} "
                          f"(got {r['type']!r})")
        url = str(r.get("url") or "").strip()
        if url and not url.startswith(("http://", "https://")):
            errors.append(f"{where}: `url` must be an http(s) URL (got {url!r})")
        comps = r.get("competencies")
        if comps is not None and not isinstance(comps, list):
            errors.append(f"{where}: `competencies` must be a list")
        else:
            for name in comps or []:
                if name not in framework:
                    errors.append(f"{where}: '{name}' is not in competencies.yaml")
    return errors


def _line(r):
    # A bracket in a title would close the link early.
    return f"[{str(r['title']).replace(']', chr(92) + ']')}]({r['url']})"


def _host(url):
    return re.sub(r"^www\.", "", re.sub(r"^https?://([^/]+).*$", r"\1", url))


def library_page(entries, cats, page_by_name):
    """The /library/ page: category `##`, competency `###`, one `####` per resource.

    A resource under several competencies is listed under each. The toc extension gives
    the first heading the plain anchor and suffixes the rest, so no explicit ids are set.
    Text only (R8): nothing here loads an image or an embed.
    """
    out = ["# Resource library", "",
           '<p class="cx-lede">Guides, how-tos, tool documentation and other reference '
           'material, grouped by competency. Search for one by name, or browse the headings '
           'below, which follow the competency framework.</p>',
           ""]
    for category, names in cats.items():
        groups = [(n, [r for r in entries if n in r["competencies"]]) for n in names]
        groups = [(n, rs) for n, rs in groups if rs]
        if not groups:
            continue
        out += [f"## {category}", ""]
        for name, rs in groups:
            out += [f"### {name}", ""]
            for r in rs:
                comps = ", ".join(f"[{c}]({page_by_name[c]})" if c in page_by_name else c
                                  for c in r["competencies"])
                out += [f"#### {r['title']}", "",
                        f"{r['description']}", "",
                        f"{_line(r)} · {r['type'].capitalize()} · {r['language']} · "
                        f"{_host(r['url'])}", "",
                        f'<p class="cx-meta">Competencies: {comps}</p>', ""]
    return "\n".join(out) + "\n"


def further_information(entries, name):
    """A competency page's Further Information section, or "" when it has no resources."""
    items = [f"- {_line(r)}<span class=\"cx-link__host\">{r['type'].capitalize()} · "
             f"{_host(r['url'])}</span><span class=\"cx-link__desc\">{r['description']}"
             f"</span>" for r in entries if name in r["competencies"]]
    if not items:
        return ""
    return ('\n## Further Information\n\n<div class="cx-links" markdown>\n\n'
            + "\n".join(items) + '\n\n</div>\n\n<p class="cx-hint">More in the '
            '<a href="../../library/">resource library</a>.</p>\n')
