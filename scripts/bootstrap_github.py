#!/usr/bin/env python3
"""Bootstrap the GitHub side from scripts/_export_manifest.json.

Modes:
  python scripts/bootstrap_github.py labels      # create 42 competency labels (needs: repo scope)
  python scripts/bootstrap_github.py issues      # create 28 issues + write scripts/_issues.json (repo scope)
  python scripts/bootstrap_github.py project      # create Project, fields, add items, set values (needs: project scope)

Run order: labels -> issues -> (gh auth refresh -s project) -> project.
All steps are idempotent where GitHub allows (labels use --force; issues/project are
guarded against duplicates via _issues.json / item lookups).
"""
import json, subprocess, sys, time, pathlib

REPO_DIR = pathlib.Path(__file__).resolve().parent.parent
REPO = "dhigby/virtual-ltct"
OWNER = "dhigby"
PROJECT_TITLE = "LTC Training Modules"
MANIFEST = json.loads((REPO_DIR / "scripts" / "_export_manifest.json").read_text(encoding="utf-8"))
ISSUES_FILE = REPO_DIR / "scripts" / "_issues.json"

import yaml
CATS = yaml.safe_load((REPO_DIR / "competencies.yaml").read_text(encoding="utf-8"))
CAT_COLOR = {"Core Technical": "1f77b4", "Technology Domain": "2ca02c", "Core": "ff7f0e",
             "Professional": "9467bd", "Education": "e6c200", "Meta": "7f7f7f"}

# Project single-select fields and the manifest key each reads from
FIELDS = {
    "Module Status": ("status", ["Not started", "In progress", "Internal Review", "Online"]),
    "Priority": ("priority", ["Low", "Medium", "High"]),
    "Consultant Tier": ("consultant_tier", ["Practitioner", "Trainer", "Expert"]),
    "Target Outcome Level": ("target_outcome_level", ["Has knowledge", "With Assistance"]),
}


def gh(*args, check=True, parse=False):
    r = subprocess.run(["gh", *args], capture_output=True, encoding="utf-8", errors="replace")
    if check and r.returncode != 0:
        print(f"  gh failed: {' '.join(args)}\n  {r.stderr.strip()}", file=sys.stderr)
        if not parse:
            return None
        sys.exit(1)
    return json.loads(r.stdout) if parse and r.stdout.strip() else r.stdout


def cmd_labels():
    for cat, names in CATS.items():
        for name in names:
            label = name if len(name) <= 50 else name[:50]
            gh("label", "create", label, "--repo", REPO, "--color", CAT_COLOR[cat],
               "--description", f"Competency: {cat}", "--force")
            print(f"  label: {label}")


def cmd_issues():
    existing = json.loads(ISSUES_FILE.read_text(encoding="utf-8")) if ISSUES_FILE.exists() else {}
    for m in MANIFEST:
        if m["slug"] in existing:
            print(f"  skip (exists): {m['slug']}"); continue
        persons = ", ".join(m.get("persons") or []) or "—"
        body = (f"**Module:** `modules/{m['slug']}/README.md`\n\n"
                f"**Content state:** {m['content_type']}\n"
                f"**Was assigned in Notion to:** {persons}\n\n"
                f"Competencies: {', '.join(m['competencies']) or '—'}")
        args = ["issue", "create", "--repo", REPO, "--title", m["title"], "--body", body]
        for c in m["competencies"]:
            args += ["--label", c if len(c) <= 50 else c[:50]]
        url = (gh(*args) or "").strip()
        existing[m["slug"]] = url
        print(f"  issue: {m['title']} -> {url}")
        time.sleep(0.4)
    ISSUES_FILE.write_text(json.dumps(existing, indent=2), encoding="utf-8")


def cmd_project():
    if not ISSUES_FILE.exists():
        sys.exit("Run 'issues' mode first (need scripts/_issues.json).")
    issues = json.loads(ISSUES_FILE.read_text(encoding="utf-8"))
    proj = gh("project", "create", "--owner", OWNER, "--title", PROJECT_TITLE, "--format", "json", parse=True)
    if not proj:
        sys.exit("project create failed — did you run `gh auth refresh -s project`?")
    pnum, pid = str(proj["number"]), proj["id"]
    print(f"  project #{pnum} ({pid})")
    gh("project", "link", pnum, "--owner", OWNER, "--repo", REPO)

    for fname, (_, opts) in FIELDS.items():
        gh("project", "field-create", pnum, "--owner", OWNER, "--name", fname,
           "--data-type", "SINGLE_SELECT", "--single-select-options", ",".join(opts))
    fields = gh("project", "field-list", pnum, "--owner", OWNER, "--format", "json", parse=True)["fields"]
    fmap = {}  # field name -> (field_id, {option_name: option_id})
    for f in fields:
        if f.get("name") in FIELDS and f.get("options") is not None:
            fmap[f["name"]] = (f["id"], {o["name"]: o["id"] for o in f["options"]})

    for m in MANIFEST:
        url = issues.get(m["slug"])
        if not url:
            print(f"  no issue for {m['slug']}, skipping"); continue
        item = gh("project", "item-add", pnum, "--owner", OWNER, "--url", url, "--format", "json", parse=True)
        iid = item["id"]
        for fname, (mkey, _) in FIELDS.items():
            val = m.get(mkey)
            if not val or fname not in fmap:
                continue
            fid, omap = fmap[fname]
            oid = omap.get(val)
            if oid:
                gh("project", "item-edit", "--id", iid, "--project-id", pid,
                   "--field-id", fid, "--single-select-option-id", oid)
        print(f"  board: {m['slug']}")
        time.sleep(0.3)
    print(f"\nProject ready: https://github.com/users/{OWNER}/projects/{pnum}")


if __name__ == "__main__":
    mode = sys.argv[1] if len(sys.argv) > 1 else ""
    {"labels": cmd_labels, "issues": cmd_issues, "project": cmd_project}.get(
        mode, lambda: sys.exit(__doc__))()
