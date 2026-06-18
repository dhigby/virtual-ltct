#!/usr/bin/env python3
"""Export the Notion "LTC Training Modules" DB into the repo as markdown + frontmatter.

Idempotent and re-runnable. Writes:
  modules/<slug>/README.md         (frontmatter + body, or stub)
  modules/computer-hardware/NN-*.md (sub-pages of a hierarchical module)
  scripts/_export_manifest.json    (drives bootstrap_github.py)
  scripts/_media_report.csv        (per-file media decisions, manual follow-up)

CLI gotchas handled: call the notion CLI through `node <cli.js>` (the .cmd shim routes
through cmd.exe and mangles '&' in JSON); query via the data-source; subprocess uses
encoding='utf-8'; the libuv "Assertion failed" stderr line is harmless.
"""
import json, subprocess, re, datetime, pathlib, csv, sys

CLI = r"C:\Users\higby\AppData\Roaming\npm\node_modules\notion-cli-agent\dist\cli.js"
MOD_DS  = "7ebb2ef5-9f57-4723-8de0-9cc1ca47ff8e"
MOD_WRAP = "f3b2a8dc-fc8d-488a-b3fc-153b376d0408"
COMP_DS = "041da705-b860-42d3-9c4c-1541970d91f6"
COMP_WRAP = "12f18f3a-2031-4809-964e-a6a1f5a929e4"
REPO = pathlib.Path(__file__).resolve().parent.parent
MODULES = REPO / "modules"
TODAY = datetime.date.today().isoformat()


def n(*args, check=True):
    r = subprocess.run(["node", CLI, *args], capture_output=True,
                       encoding="utf-8", errors="replace")
    if check and r.returncode != 0:
        sys.exit(f"notion CLI failed: {args}\n{r.stderr}")
    return r.stdout or ""


def query(ds, wrap):
    return json.loads(n("--data-source-id", ds, "database", "query", wrap,
                        "--json", "--limit", "100"))


def plain(rt):
    return "".join(x.get("plain_text", "") for x in (rt or [])).strip()


def title_of(props):
    return plain(props.get("Course Title", {}).get("title", []))


def sel(props, key):
    v = (props.get(key) or {}).get("select")
    return v.get("name") if v else None


_slugs = set()
def slugify(title):
    s = re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")
    s = re.sub(r"-{2,}", "-", s) or "module"
    base, i = s, 2
    while s in _slugs:
        s = f"{base}-{i}"; i += 1
    _slugs.add(s)
    return s


def collect_links(props):
    links = {}
    if (props.get("Link to Source Materials") or {}).get("url"):
        links["materials"] = props["Link to Source Materials"]["url"]
    if (props.get("Video / Recording") or {}).get("url"):
        links["video"] = props["Video / Recording"]["url"]
    chipp = plain((props.get("Chipp.ai") or {}).get("rich_text", []))
    if chipp.startswith("http"):
        links["chipp_ai"] = chipp
    return links


def files_of(props, key):
    """Return [(name, url)] for a files-type property; external URLs and S3 alike."""
    out = []
    for f in (props.get(key) or {}).get("files", []):
        name = f.get("name", "")
        url = (f.get("external") or f.get("file") or {}).get("url", "")
        out.append((name, url))
    return out


def yaml_block(fm):
    """Minimal YAML emitter for our flat frontmatter (no external dep)."""
    lines = ["---"]
    for k, v in fm.items():
        if v in (None, "", [], {}):
            continue
        if isinstance(v, list):
            lines.append(f"{k}:")
            for item in v:
                lines.append(f"  - {item}")
        elif isinstance(v, dict):
            lines.append(f"{k}:")
            for kk, vv in v.items():
                lines.append(f"  {kk}: {vv}")
        else:
            s = str(v)
            if any(c in s for c in ":#") or s != s.strip():
                s = '"' + s.replace('"', '\\"') + '"'
            lines.append(f"{k}: {s}")
    lines.append("---")
    return "\n".join(lines)


def has_body(page_id):
    """True if the page has any block child that isn't a re-pointing child_database."""
    d = json.loads(n("api", "GET", f"blocks/{page_id}/children?page_size=50"))
    return any(b["type"] != "child_database" for b in d.get("results", []))


def child_pages(page_id):
    d = json.loads(n("api", "GET", f"blocks/{page_id}/children?page_size=100"))
    return [(b["id"], plain(b.get("child_page", {}).get("title")
                            and [{"plain_text": b["child_page"]["title"]}] or []))
            for b in d.get("results", []) if b["type"] == "child_page"]


def stub_body(fm):
    lines = [f"# {fm['title']}", "",
             "> **This module is a stub.** Its content currently lives outside the repo.",
             "> Replace this section with content authored here. See CONTRIBUTING.md.", ""]
    el = fm.get("external_links", {})
    if el:
        lines.append("**External materials:**")
        for k, v in el.items():
            lines.append(f"- {k}: {v}")
    return "\n".join(lines) + "\n"


def write_guarded(path, content):
    """Don't clobber a file that already holds real authored content over a stub."""
    if path.exists():
        existing = path.read_text(encoding="utf-8")
        if "content_type: content" in existing and "content_type: stub" in content:
            print(f"  SKIP (guard) {path.relative_to(REPO)} — existing content richer than stub")
            return
    path.write_text(content, encoding="utf-8", newline="\n")


def main():
    MODULES.mkdir(parents=True, exist_ok=True)
    comp_rows = query(COMP_DS, COMP_WRAP)["results"]
    comp_name = {r["id"]: title_of_comp(r) for r in comp_rows}

    mod_rows = query(MOD_DS, MOD_WRAP)["results"]
    # first pass: id -> slug (needed to resolve parent_module)
    id_slug, parsed = {}, []
    for row in mod_rows:
        p = row["properties"]
        t = title_of(p) or "untitled"
        slug = slugify(t)
        id_slug[row["id"]] = slug
        parsed.append((row, t, slug))

    manifest, media = [], []
    for row, title, slug in parsed:
        p = row["properties"]
        comps = [comp_name[r["id"]] for r in (p.get("Competencies") or {}).get("relation", [])
                 if r["id"] in comp_name]
        parent_rel = (p.get("Parent item") or {}).get("relation", [])
        parent_slug = id_slug.get(parent_rel[0]["id"]) if parent_rel else None
        links = collect_links(p)
        content = has_body(row["id"])
        fm = {
            "title": title, "slug": slug, "notion_id": row["id"],
            "notion_data_source": MOD_DS,
            "consultant_tier": sel(p, "Consultant Tier"),
            "target_outcome_level": sel(p, "Target Outcome Level"),
            "competencies": comps,
            "content_type": "content" if content else "stub",
            "external_links": links,
            "access_code": plain((p.get("Access Code") or {}).get("rich_text", [])),
            "parent_module": parent_slug,
            "last_exported": TODAY,
        }
        folder = MODULES / slug
        folder.mkdir(parents=True, exist_ok=True)
        if content:
            body = n("export", "page", row["id"], "--no-frontmatter")
            # hierarchical sub-pages (e.g. Computer Hardware)
            subs = child_pages(row["id"])
            if subs:
                links_md = ["", "## Sub-modules", ""]
                for i, (cid, ctitle) in enumerate(subs, 1):
                    csl = slugify(ctitle or f"section-{i}")
                    cfile = folder / f"{i:02d}-{csl}.md"
                    cbody = n("export", "page", cid, "--no-frontmatter")
                    write_guarded(cfile, f"# {ctitle}\n\n{cbody}\n")
                    links_md.append(f"- [{ctitle}]({cfile.name})")
                body += "\n" + "\n".join(links_md) + "\n"
        else:
            body = stub_body(fm)
        write_guarded(folder / "README.md", yaml_block(fm) + "\n\n" + body.strip() + "\n")

        # manifest carries volatile workflow state for the Project bootstrap
        manifest.append({
            "slug": slug, "title": title, "notion_id": row["id"],
            "status": (p.get("Status") or {}).get("status", {}).get("name"),
            "priority": sel(p, "Priority"),
            "consultant_tier": fm["consultant_tier"],
            "target_outcome_level": fm["target_outcome_level"],
            "competencies": comps,
            "persons": [pe.get("name") for pe in (p.get("Person") or {}).get("people", [])],
            "content_type": fm["content_type"],
        })
        # media report
        for key in ("NotebookLM", "Files & media"):
            for name, url in files_of(p, key):
                host = re.sub(r"^https?://([^/]+)/.*", r"\1", url)
                notion_hosted = "prod-files-secure" in url or "secure.notion-static" in url
                media.append({"module": slug, "field": key, "file": name,
                              "host": host, "notion_hosted": notion_hosted,
                              "action": "download+rehome (URL expires)" if notion_hosted else "keep link",
                              "url": url})
        print(f"  {fm['content_type']:7} {slug}  [{', '.join(comps) or 'no competency'}]")

    (REPO / "scripts" / "_export_manifest.json").write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8")
    with open(REPO / "scripts" / "_media_report.csv", "w", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=["module", "field", "file", "host",
                                           "notion_hosted", "action", "url"])
        w.writeheader(); w.writerows(media)
    print(f"\nExported {len(manifest)} modules. Media items: {len(media)}.")


def title_of_comp(row):
    return plain(row["properties"]["Name"]["title"])


if __name__ == "__main__":
    main()
