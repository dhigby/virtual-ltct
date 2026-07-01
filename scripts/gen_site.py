"""MkDocs build hook: generate the competency site from the authored source.

Run automatically by the `mkdocs-gen-files` plugin during `mkdocs build`/`serve`
(see mkdocs.yml). It reads `competencies.yaml` (canonical category order + names) and the
hand-authored `competencies/*.md`, and emits — into the in-memory docs tree, so nothing is
duplicated in git:

  * index.md      — framework overview with per-category counts
  * <category>/<slug>.md  — one page per competency (descriptor body, frontmatter stripped)
  * coverage.md   — the committed COVERAGE.md, if present
  * SUMMARY.md    — the nav tree (consumed by mkdocs-literate-nav), grouped by category

Each competency page's edit link points back to the real competencies/<slug>.md so the
site's ✏️ button lands on the source of truth.
"""
import re
import pathlib

import yaml
import mkdocs_gen_files

ROOT = pathlib.Path(__file__).resolve().parent.parent
CATS = yaml.safe_load((ROOT / "competencies.yaml").read_text(encoding="utf-8"))


def slugify(name):
    s = name.lower().replace("&", "and")
    s = re.sub(r"[()]", "", s)
    return re.sub(r"[^a-z0-9]+", "-", s).strip("-")


def split_frontmatter(text):
    if text.startswith("---"):
        end = text.find("\n---", 3)
        if end != -1:
            return yaml.safe_load(text[3:end]) or {}, text[end + 4:].lstrip("\n")
    return {}, text


# Load every hand-authored descriptor, keyed by its framework name.
by_name = {}
for p in sorted((ROOT / "competencies").glob("*.md")):
    if p.name == "README.md":
        continue
    fm, body = split_frontmatter(p.read_text(encoding="utf-8"))
    if fm.get("name"):
        by_name[fm["name"]] = {"fm": fm, "body": body, "slug": fm.get("slug", p.stem)}

# Per-competency pages + nav, grouped by category in canonical competencies.yaml order.
nav_lines = ["* [Home](index.md)"]
counts = {}
page_by_name = {}  # framework name -> its page path (relative to the docs root)
for category, names in CATS.items():
    present = [n for n in names if n in by_name]
    counts[category] = len(present)
    if not present:  # e.g. Meta / Uncategorized has no descriptor — skip empty sections
        continue
    cat_dir = slugify(category)
    nav_lines.append(f"* {category}")
    for name in present:
        d = by_name[name]
        page = f"{cat_dir}/{d['slug']}.md"
        page_by_name[name] = page
        with mkdocs_gen_files.open(page, "w") as f:
            f.write(d["body"])
        # ✏️ edit button jumps to the real source file, not the generated page.
        mkdocs_gen_files.set_edit_path(page, f"competencies/{d['slug']}.md")
        nav_lines.append(f"    * [{name}]({page})")

# Overview page.
total = sum(counts.values())
n_categories = sum(1 for c in counts if counts[c])
overview = [
    "# LT Consultant Competencies", "",
    "The competency framework for Language Technology Consultants. "
    f"**{total} competencies** across {n_categories} categories, "
    "each with its rationale, target outcome, and a progression of suggested activities.",
    "",
    "Use the navigation (or search) to browse by category. Every page here is generated "
    "from the hand-authored source in "
    "[`competencies/`](https://github.com/dhigby/virtual-ltct/tree/main/competencies) — "
    "edit a descriptor there to change what appears on the site.", "",
    "| Category | Competencies |", "| --- | --- |",
]
for category in CATS:
    if counts.get(category):
        overview.append(f"| {category} | {counts[category]} |")
overview += ["", "See the [Coverage](coverage.md) page for which competencies have "
             "training modules and which are still gaps."]
with mkdocs_gen_files.open("index.md", "w") as f:
    f.write("\n".join(overview) + "\n")

# Coverage page — reuse the committed COVERAGE.md (single source), minus its edit banner,
# with every competency name linked to its page (both table rows and the gaps list).
def linkify_coverage(text):
    out = []
    for line in text.splitlines():
        row = re.match(r"\| (.+?) \| (.+)$", line)
        if row and row.group(1) not in ("Competency", "---"):
            name = row.group(1)
            if name in page_by_name:
                line = f"| [{name}]({page_by_name[name]}) | {row.group(2)}"
        elif line.startswith("- "):
            name = line[2:].strip()
            if name in page_by_name:
                line = f"- [{name}]({page_by_name[name]})"
        out.append(line)
    return "\n".join(out)


cov = ROOT / "COVERAGE.md"
if cov.exists():
    text = re.sub(r"<!--.*?-->\n?", "", cov.read_text(encoding="utf-8"), count=1)
    with mkdocs_gen_files.open("coverage.md", "w") as f:
        f.write(linkify_coverage(text))
    nav_lines.append("* [Coverage](coverage.md)")

with mkdocs_gen_files.open("SUMMARY.md", "w") as f:
    f.write("\n".join(nav_lines) + "\n")
