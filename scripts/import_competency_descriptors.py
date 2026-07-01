#!/usr/bin/env python3
"""Generate competency *descriptor* files from two sources.

The framework's `competencies.yaml` is a thin controlled vocabulary; descriptors add
the teachable detail it omits. Two sources feed them:

  1. `Lang Tech Competencies.xlsx` — the technical/education/professional competencies,
     each with a rationale, a target statement, and a ladder of suggested activities at
     each outcome level (Learner … Expert).
  2. `CBC Guide for Non-technical Competencies in Language Technology.md` — the Core
     (non-technical) competencies, each a group of sub-competencies with a description,
     observable criteria, and a rationale. These have no level ladder.

Both write one markdown descriptor per framework competency to `competencies/<slug>.md`:
structured facts in frontmatter, prose in the body. It mirrors the source-of-truth
split — descriptors are teaching content (versioned here), workflow state stays on the
GitHub Project.

Run:  python scripts/import_competency_descriptors.py
Requires: pyyaml, openpyxl.

Idempotent: it wipes and rewrites all `competencies/*.md` (they carry a generated
banner). Do not hand-edit the output; edit a source or this script and re-run.
"""
import re
import sys
import pathlib
import datetime

try:
    import yaml
    import openpyxl
except ImportError as e:
    sys.exit(f"missing dependency ({e.name}): pip install pyyaml openpyxl")

REPO = pathlib.Path(__file__).resolve().parent.parent
XLSX = REPO / "Lang Tech Competencies.xlsx"
CBC = REPO / "CBC Guide for Non-technical Competencies in Language Technology.md"
OUT = REPO / "competencies"

# Content sheets in the workbook, tagged with the framework category they belong to.
# "Milestone Scoring" and the summary tab are per-person tracking, not descriptors.
SHEETS = {
    "Core Tech": "Core Technical",
    "LT Domain": "Technology Domain",
    "Professional": "Professional",
    "Education": "Education",
}

# Spreadsheet label -> exact competencies.yaml name, where they differ.
# A label absent here is assumed to match competencies.yaml verbatim.
LABEL_TO_CANONICAL = {
    "Phonetics Tools": "Phonetic Tools",
    "Archiving & Copyright": "Archiving and Copyright Tools",
    "CoP Involvement": "Community of Practice Involvement",
}

# One workbook row that maps to several framework competencies. The workbook's single
# "Domain-specific education" ladder is generic ("a semester of courses in one of these
# domains"), so it applies verbatim to each domain the framework calls out separately.
EXPANSION = {
    "Domain-specific education": [
        "Translation", "Literacy or MLE", "Scripture Engagement", "General", "Linguistics",
    ],
}

# CBC guide `#` group heading -> exact competencies.yaml name (all Core category).
# The guide's "Professional Competencies" group is intentionally absent: those four
# competencies are already sourced from the workbook with richer level ladders.
GROUP_TO_CANONICAL = {
    "Interpersonal Competencies": "Interpersonal Skills",
    "Language and Culture": "Language Use",
    "Skills Required for a Consulting Session": "Consulting Process Skills",
    "Program Design and Mobilization": "Program Design and Engagement",
    "Relationships with Other Organizations": "Professional Networking",
    "Research and Documentation": "Scholarship and Documentation",
    "Technologies Used for Consulting": "Technology for Consulting",
    "Adult Education": "Adult Education",
    "Mentoring": "Mentoring",
    "Working in a Multicultural Environment": "Working in a Multicultural Environment",
}

# Common mojibake from the source workbook -> intended character.
MOJIBAKE = {"�": "—"}


def clean(v):
    if v is None:
        return ""
    s = str(v).strip()
    for bad, good in MOJIBAKE.items():
        s = s.replace(bad, good)
    return re.sub(r"[ \t]+", " ", s)


def slugify(name):
    s = name.lower().replace("&", "and")
    s = re.sub(r"[()]", "", s)
    s = re.sub(r"[^a-z0-9]+", "-", s).strip("-")
    return s


def find_layout(rows):
    """Locate the header row and the level/link columns (0-indexed)."""
    header_i = next(i for i, r in enumerate(rows)
                    if clean(r[0]) == "Label for competency")
    names_i = next(i for i in range(header_i + 1)
                   if any(clean(c) == "Learner" for c in rows[i]))
    names = rows[names_i]
    link_col = next((j for j, c in enumerate(names) if clean(c) == "Link"), None)
    last_level = (link_col if link_col is not None else 8)  # E..H default (cols 4..7)
    level_cols = list(range(4, last_level))  # column D is index 3; levels start at E (4)
    levels = [clean(names[c]) for c in level_cols]
    return header_i, level_cols, levels, link_col


def first_link(row):
    for c in row:
        s = clean(c)
        if s.startswith("http"):
            return s
    return ""


def parse_sheet(ws):
    rows = list(ws.iter_rows(values_only=True))
    header_i, level_cols, levels, link_col = find_layout(rows)
    comps, cur = [], None
    for r in rows[header_i + 1:]:
        label, rationale, c_col, d_col = (clean(r[0]), clean(r[1]),
                                          clean(r[2]), clean(r[3]))
        if label and not label.lower().startswith("please add"):
            cur = {"label": label, "rationale": rationale,
                   "statement": c_col, "components": [], "resources": []}
            comps.append(cur)
            continue
        if cur is None:
            continue
        if c_col or d_col:  # a component row
            acts = [clean(r[j]) if j < len(r) else "" for j in level_cols]
            link = first_link(r)
            if link and link not in cur["resources"]:
                cur["resources"].append(link)
            cur["components"].append({
                "num": c_col, "name": d_col, "acts": acts,
                "note": rationale,  # rationale col occasionally carries a component note
            })
    return comps, levels


def parse_guide(text):
    """Parse the CBC markdown into {group -> [sub-competency dicts]}.

    `#` = group heading (a framework Core competency), `##` = sub-competency, each with
    **Competency Description**, **Components** (bullets), **Instructions** (bullets).
    """
    groups, group, sub, field = {}, None, None, None
    for raw in text.splitlines():
        line = raw.rstrip()
        stripped = line.strip()
        if line.startswith("## "):
            if group is not None:
                sub = {"title": line[3:].strip(), "description": "",
                       "criteria": [], "rationale": []}
                groups[group].append(sub)
                field = "description"
            continue
        if line.startswith("# "):
            head = line[2:].strip()
            group = head if head in GROUP_TO_CANONICAL else None
            if group is not None:
                groups.setdefault(group, [])
            sub = field = None
            continue
        if sub is None:
            continue
        low = stripped.lower().strip("*").strip()
        if low == "components":
            field = "criteria"
        elif low == "instructions":
            field = "rationale"
        elif low.startswith("competency description"):
            field = "description"
        elif stripped.startswith("- "):
            item = stripped[2:].strip()
            if item and field in ("criteria", "rationale"):
                sub[field].append(item)
        elif stripped and field == "description" and not stripped.startswith("**"):
            sub["description"] = (sub["description"] + " " + stripped).strip()
    return groups


def scalar(s):
    """Emit a YAML-safe single-line scalar, quoting only when necessary."""
    if s == "" or re.search(r'''[:#\[\]{}&*!|>'"%@`]|^[\s-]|\s$''', s):
        return '"' + s.replace("\\", "\\\\").replace('"', '\\"') + '"'
    return s


def frontmatter(d):
    # Hand-format so multi-line strings stay readable and key order is stable.
    lines = ["---", f"name: {scalar(d['name'])}",
             f"category: {d['category']}", f"slug: {d['slug']}"]
    if d["source_label"] != d["name"]:
        lines.append(f"source_label: {scalar(d['source_label'])}")
    lines.append(f"in_framework: {'true' if d['in_framework'] else 'false'}")
    if d["kind"] == "leveled":
        lines.append("target_statement: " + scalar(d["statement"]))
        lines.append("outcome_levels:")
        lines += [f"  - {lv}" for lv in d["levels"]]
    lines.append("resources:")
    lines += [f"  - {u}" for u in d["resources"]] or ["  []"]
    lines += [f"source: {scalar(d['source'])}", f"last_imported: {d['today']}", "---"]
    return "\n".join(lines)


def body(d):
    out = ["<!-- Generated by scripts/import_competency_descriptors.py from "
           f"{d['source']} — do not hand-edit. -->", "", f"# {d['name']}", ""]
    meta = f"**Category:** {d['category']}"
    if d["source_label"] != d["name"]:
        tag = "Workbook label" if d["kind"] == "leveled" else "Guide section"
        meta += f" · **{tag}:** {d['source_label']}"
    out += [meta, ""]
    if d.get("expanded"):
        out += [f"> This applies the workbook's single **{d['source_label']}** ladder to the "
                f"**{d['name']}** domain; the framework calls out each domain separately, "
                "so the rationale and activities below are shared across them.", ""]
    if not d["in_framework"]:
        out += ["> ⚠️ **Not yet in `competencies.yaml`.** This competency exists in the "
                "source but not the framework list. Add it to `competencies.yaml` before "
                "any module claims it, or coverage tracking will reject the name.", ""]
    if d["kind"] == "leveled":
        out += _leveled_body(d)
    else:
        out += _guide_body(d)
    return "\n".join(out)


def _leveled_body(d):
    out = []
    if d["rationale"]:
        out += ["## Why it matters", "", d["rationale"], ""]
    if d["statement"]:
        out += ["## Target competency", "", f"> {d['statement']}", ""]
    out += ["## Progression by component", "",
            "_Suggested activities to reach each outcome level._", ""]
    for comp in d["components"]:
        head = " — ".join(p for p in (comp["num"], comp["name"]) if p) or "General"
        out += [f"### {head}", ""]
        if comp["note"]:
            out += [f"_{comp['note']}_", ""]
        out += ["| Level | Suggested activities |", "| --- | --- |"]
        for lv, act in zip(d["levels"], comp["acts"]):
            out.append(f"| **{lv}** | {act.replace(chr(124), '/') or '—'} |")
        out.append("")
    return out


def _guide_body(d):
    out = ["## Sub-competencies", ""]
    for s in d["subs"]:
        out += [f"### {s['title']}", ""]
        if s["description"]:
            out += [s["description"], ""]
        if s["criteria"]:
            out += ["**Observable criteria**", ""]
            out += [f"- {c}" for c in s["criteria"]] + [""]
        if s["rationale"]:
            out += ["**Why it matters**", ""]
            out += [f"- {r}" for r in s["rationale"]] + [""]
    return out


def main():
    if not XLSX.exists():
        sys.exit(f"workbook not found: {XLSX}")
    OUT.mkdir(exist_ok=True)
    wb = openpyxl.load_workbook(XLSX, data_only=True)
    known = {n for names in yaml.safe_load(
        (REPO / "competencies.yaml").read_text(encoding="utf-8")).values() for n in names}
    today = datetime.date.today().isoformat()

    # Remove previously generated descriptors so renames/expansions don't leave orphans.
    for stale in OUT.glob("*.md"):
        stale.unlink()

    written, flags, by_cat = [], [], {}

    def emit(d):
        path = OUT / f"{d['slug']}.md"
        path.write_text(frontmatter(d) + "\n\n" + body(d) + "\n",
                        encoding="utf-8", newline="\n")
        written.append(path.name)
        by_cat.setdefault(d["category"], []).append(d)
        if not d["in_framework"]:
            flags.append(f"{d['name']!r} (from '{d['source_label']}') not in competencies.yaml")

    # Source 1: the workbook — leveled descriptors.
    for sheet, category in SHEETS.items():
        comps, levels = parse_sheet(wb[sheet])
        for c in comps:
            # A workbook row maps to one canonical name, or fans out to several.
            targets = EXPANSION.get(c["label"],
                                    [LABEL_TO_CANONICAL.get(c["label"], c["label"])])
            for name in targets:
                emit({"kind": "leveled", "name": name, "source_label": c["label"],
                      "category": category, "slug": slugify(name),
                      "in_framework": name in known, "expanded": c["label"] in EXPANSION,
                      "statement": c["statement"], "rationale": c["rationale"],
                      "levels": levels, "components": c["components"],
                      "resources": c["resources"], "source": XLSX.name, "today": today})

    # Source 2: the CBC guide — Core (non-technical) descriptors, no level ladder.
    if CBC.exists():
        for group, subs in parse_guide(CBC.read_text(encoding="utf-8")).items():
            name = GROUP_TO_CANONICAL[group]
            emit({"kind": "guide", "name": name, "source_label": group,
                  "category": "Core", "slug": slugify(name), "in_framework": name in known,
                  "subs": subs, "resources": [], "source": CBC.name, "today": today})
    else:
        print(f"NOTE: {CBC.name} not found — Core competencies not generated.")

    # A browsable index, regenerated alongside the descriptors.
    idx = ["<!-- Generated by scripts/import_competency_descriptors.py — do not hand-edit. -->",
           "# Competency Descriptors", "",
           f"_Generated {today} from `{XLSX.name}` (leveled) and `{CBC.name}` (Core). Each "
           "descriptor carries the rationale and progression detail that `competencies.yaml` "
           "omits. The framework YAML remains the canonical name list; workflow state stays "
           "on the GitHub Project._", ""]
    for category, items in by_cat.items():
        idx += [f"## {category}", "", "| Competency | Detail | File |", "| --- | --- | --- |"]
        for d in sorted(items, key=lambda x: x["name"]):
            flag = " ⚠️ not in framework" if not d["in_framework"] else ""
            detail = (f"{len(d['levels'])}-level ladder" if d["kind"] == "leveled"
                      else f"{len(d['subs'])} sub-competencies")
            idx.append(f"| {d['name']}{flag} | {detail} | "
                       f"[{d['slug']}.md]({d['slug']}.md) |")
        idx.append("")
    (OUT / "README.md").write_text("\n".join(idx), encoding="utf-8", newline="\n")

    print(f"wrote {len(written)} descriptors + README.md to {OUT.relative_to(REPO)}/")
    for f in flags:
        print("FLAG:", f)


if __name__ == "__main__":
    main()
