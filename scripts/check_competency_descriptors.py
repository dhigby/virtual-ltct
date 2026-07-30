#!/usr/bin/env python3
"""Verify competency descriptors stay in sync with the framework.

`competencies.yaml` is the canonical name list; `competencies/*.md` are the hand-authored
descriptors. This check fails (exit 1) if they drift:

  * a framework competency has no descriptor (except exempt placeholders),
  * a descriptor names a competency that isn't in the framework,
  * a descriptor's frontmatter is malformed or missing a required key,
  * a descriptor self-reports `in_framework: false`,
  * a descriptor's `resources:` entries aren't `{title, url}` links,
  * or a descriptor's filename doesn't match its `slug`.

Run:  python scripts/check_competency_descriptors.py
Requires: pyyaml. Read-only — makes no changes.
"""
import sys
import pathlib

try:
    import yaml
except ImportError:
    sys.exit("pyyaml required: pip install pyyaml")

REPO = pathlib.Path(__file__).resolve().parent.parent
YAML = REPO / "competencies.yaml"
OUT = REPO / "competencies"

# Framework names that legitimately have no descriptor (no source content exists).
EXEMPT = {"Uncategorized"}


def parse_frontmatter(path):
    text = path.read_text(encoding="utf-8")
    if not text.startswith("---"):
        return None
    end = text.find("\n---", 3)
    if end == -1:
        return None
    return yaml.safe_load(text[3:end])


def main():
    framework = {n for names in yaml.safe_load(YAML.read_text(encoding="utf-8")).values()
                 for n in names}
    errors, seen = [], {}

    for md in sorted(OUT.glob("*.md")):
        if md.name == "README.md":
            continue
        try:
            fm = parse_frontmatter(md)
        except yaml.YAMLError as e:
            errors.append(f"{md.name}: frontmatter does not parse ({e})")
            continue
        if not fm or "name" not in fm:
            errors.append(f"{md.name}: missing frontmatter or `name`")
            continue
        # These files are hand-authored now, so guard the keys humans might drop.
        for key in ("name", "category", "slug"):
            if not fm.get(key):
                errors.append(f"{md.name}: missing required frontmatter key `{key}`")
        # `resources:` is a list of {title, url} links that scripts/gen_site.py renders as
        # each page's Further Information section. Hand-maintained, so guard the shape.
        resources = fm.get("resources")
        if resources is not None and not isinstance(resources, list):
            errors.append(f"{md.name}: `resources` must be a list of title/url entries")
        elif resources:
            for i, r in enumerate(resources, start=1):
                if not isinstance(r, dict):
                    errors.append(f"{md.name}: resources[{i}] must be a mapping with "
                                  "`title` and `url`")
                    continue
                if not str(r.get("title") or "").strip():
                    errors.append(f"{md.name}: resources[{i}] is missing `title`")
                url = str(r.get("url") or "").strip()
                if not url.startswith(("http://", "https://")):
                    errors.append(f"{md.name}: resources[{i}] `url` must be an http(s) URL "
                                  f"(got {url!r})")
        name = fm["name"]
        if name in seen:
            errors.append(f"{md.name}: duplicate descriptor for '{name}' (also {seen[name]})")
        seen[name] = md.name
        if fm.get("slug") and md.stem != fm["slug"]:
            errors.append(f"{md.name}: filename does not match slug '{fm['slug']}'")
        if name not in framework:
            errors.append(f"{md.name}: '{name}' is not in competencies.yaml")
        elif fm.get("in_framework") is False:
            errors.append(f"{md.name}: '{name}' self-reports in_framework: false")

    missing = sorted(framework - set(seen) - EXEMPT)
    for name in missing:
        errors.append(f"no descriptor for framework competency '{name}'")

    covered = len(framework & set(seen))
    print(f"framework: {len(framework)} competencies "
          f"({len(EXEMPT & framework)} exempt) · descriptors: {len(seen)} · "
          f"covered: {covered}")
    if errors:
        print(f"\n{len(errors)} problem(s):")
        for e in errors:
            print("  -", e)
        sys.exit(1)
    print("OK — descriptors and framework are in sync.")


if __name__ == "__main__":
    main()
