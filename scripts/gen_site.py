"""MkDocs build hook: generate the competency site from the authored source.

Run automatically by the `mkdocs-gen-files` plugin during `mkdocs build`/`serve`
(see mkdocs.yml). It reads the canonical sources — `competencies.yaml` (category order +
names), `outcome-levels.yaml` (the CBC scale), the hand-authored `competencies/*.md`, and
the generated `COVERAGE.md` — and emits, into the in-memory docs tree so nothing is
duplicated in git:

  * index.md                 — landing page: category cards, the level scale, coverage
  * <category>/index.md      — one overview page per category, cards for its competencies
  * <category>/<slug>.md     — one page per competency
  * all-competencies.md      — the whole framework as one filterable table
  * how-to-read-levels.md    — the CBC scale and the offset, explained once
  * coverage.md              — COVERAGE.md re-rendered with per-category progress bars
  * SUMMARY.md               — the nav tree (consumed by mkdocs-literate-nav)

A competency page is not a straight copy of its descriptor. The descriptor's level
ladders are markdown tables (authored that way because a table is the sane thing to
hand-edit); here each one is re-rendered as a stepped "ladder" component, which reads
top-to-bottom, survives a phone screen, and states the level -> next-level offset instead
of leaving it to be inferred from a third column. Styling lives in
`docs/stylesheets/extra.css`; every class it needs is prefixed `cx-`.

Each competency page's edit link points back to the real competencies/<slug>.md so the
site's edit button lands on the source of truth.
"""
import re
import pathlib

import yaml
import mkdocs_gen_files

ROOT = pathlib.Path(__file__).resolve().parent.parent
CATS = yaml.safe_load((ROOT / "competencies.yaml").read_text(encoding="utf-8"))
LEVELS = yaml.safe_load(
    (ROOT / "outcome-levels.yaml").read_text(encoding="utf-8"))["levels"]

# Per-category presentation: the icon and one-line framing shown on the home page and at
# the top of the category's own overview page. Keyed by the category names in
# competencies.yaml; a category missing from here still renders, just without an icon.
CATEGORY_META = {
    "Core Technical": (
        "material-laptop",
        "The machine itself — hardware, operating systems, fonts and encoding, "
        "keyboards, and keeping it all safe.",
    ),
    "Technology Domain": (
        "material-toolbox-outline",
        "The software of language work — translation, lexicography, phonology, literacy, "
        "recording, publishing, typesetting, and AI.",
    ),
    "Core": (
        "material-compass-outline",
        "The consulting craft — programme design, mentoring, adult education, and "
        "working well across cultures.",
    ),
    "Professional": (
        "material-account-group-outline",
        "How you work alongside others — language, problem solving, feedback, and taking "
        "part in a community of practice.",
    ),
    "Education": (
        "material-school-outline",
        "Formal grounding in the domains that language technology serves.",
    ),
    "Meta": ("material-shape-outline", "Framework bookkeeping."),
}


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


# --------------------------------------------------------------------------------------
# Coverage — parsed from the generated COVERAGE.md so it stays the single source of truth
# --------------------------------------------------------------------------------------
COV_ROW = re.compile(r"^\|\s*(.+?)\s*\|\s*(\d+)\s*\|\s*(.+?)\s*\|\s*$")


def load_coverage():
    """{competency name: module count} from COVERAGE.md, or {} if it isn't there."""
    path = ROOT / "COVERAGE.md"
    if not path.exists():
        return {}
    counts = {}
    for line in path.read_text(encoding="utf-8").splitlines():
        m = COV_ROW.match(line)
        if m and m.group(1) != "Competency":
            counts[m.group(1)] = int(m.group(2))
    return counts


COVERAGE = load_coverage()


def coverage_chip(name, depth=1):
    """A '2 training modules' / 'No module yet' chip linking to the coverage page."""
    if name not in COVERAGE:
        return ""
    n = COVERAGE[name]
    up = "../" * depth
    if n == 0:
        return (f'<a href="{up}coverage/" class="cx-chip cx-chip--gap" '
                f'title="No training module covers this yet">No module yet</a>')
    label = "1 training module" if n == 1 else f"{n} training modules"
    return (f'<a href="{up}coverage/" class="cx-chip cx-chip--cov" '
            f'title="Training modules covering this competency">{label}</a>')


# --------------------------------------------------------------------------------------
# Ladder rendering — the level tables become a stepped component
# --------------------------------------------------------------------------------------
LADDER_ROW = re.compile(r"^\|\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+?)\s*\|\s*$")
COMPONENT_HEAD = re.compile(r"^###\s+(?:(\d+)\.0\s+—\s+)?(.*)$")


def split_level(label):
    """'2 - With Assistance' -> ('2', 'With Assistance')."""
    label = label.strip().strip("*").strip()
    parts = label.split(" - ", 1)
    if len(parts) == 2 and parts[0].strip().isdigit():
        return parts[0].strip(), parts[1].strip()
    return "", label


def render_ladder(rows):
    """Rows of (level label, activities, reaches) -> the stepped ladder markup.

    `markdown="span"` keeps inline markup inside an activity cell working (a few carry
    emphasis or a link) without letting the surrounding raw HTML be re-parsed as markdown.
    """
    out = ['<ol class="cx-ladder" markdown="block">']
    for level, activity, reaches in rows:
        num, level_name = split_level(level)
        out.append(f'<li class="cx-rung" data-level="{num}" markdown="block">')
        out.append(f'<p class="cx-rung__head">'
                   f'<span class="cx-rung__badge">{num}</span>'
                   f'<span class="cx-rung__level">{level_name}</span></p>')
        out.append(f'<div class="cx-rung__act" markdown="span">{activity.strip()}</div>')
        reach_num, reach_name = split_level(reaches)
        if reach_num:
            out.append(f'<p class="cx-rung__reach">Carries a learner to '
                       f'<strong>{reach_num} — {reach_name}</strong></p>')
        else:
            out.append('<p class="cx-rung__reach cx-rung__reach--top">'
                       'Top of the ladder</p>')
        out.append('</li>')
    out.append('</ol>')
    return "\n".join(out)


def transform_progression(section_body, competency_name):
    """Rewrite a 'Progression by component' section body.

    Each `### N.0 — Component` heading plus its markdown table becomes a heading (with the
    component number moved into a CSS-rendered badge) plus a ladder. A lone component whose
    name just repeats the competency — or is the placeholder `-` — loses its heading, since
    it names nothing the page title hasn't said already.
    """
    # Drop the authored caption; how-to-read-levels.md now carries that explanation.
    lines = [ln for ln in section_body.splitlines()
             if not ln.startswith("_Each row is a level")]

    blocks = []   # [number, title, [rows]]
    current = None
    for line in lines:
        head = COMPONENT_HEAD.match(line)
        if line.startswith("### ") and head:
            current = [head.group(1) or "", head.group(2).strip(), []]
            blocks.append(current)
            continue
        row = LADDER_ROW.match(line)
        if row and current is not None:
            cells = [row.group(1), row.group(2), row.group(3)]
            if cells[0].startswith("---") or cells[0] == "Current level":
                continue
            current[2].append(tuple(cells))
    if not blocks:
        return section_body

    single = len(blocks) == 1
    out = []
    for num, title, rows in blocks:
        bare = title.strip("-— ").strip()
        redundant = not bare or bare.lower() == competency_name.lower()
        if not (single and redundant):
            shown = bare or f"Component {num}"
            attrs = f'{{ .cx-component data-num="{num}" }}' if num else "{ .cx-component }"
            out.append(f"### {shown} {attrs}\n")
        out.append(render_ladder(rows) + "\n")
    return "\n".join(out)


def transform_subcompetencies(section_body):
    """Rewrite a 'Sub-competencies' section body.

    Each `### Name` becomes an `## Name` (so it lands in the table of contents at the top
    level, now that the redundant 'Sub-competencies' wrapper heading is gone), and the two
    bold pseudo-headings inside become labelled blocks the stylesheet can pick up.
    """
    body = re.sub(r"^### ", "## ", section_body, flags=re.M)
    body = body.replace(
        "**Observable criteria**",
        '<p class="cx-label cx-label--crit">Observable criteria</p>')
    body = body.replace(
        "**Why it matters**",
        '<p class="cx-label cx-label--why">Why it matters</p>')
    return body


def resources_section(resources):
    """Render a descriptor's frontmatter `resources:` as a Further Information section.

    Entries are `{title, url}` mappings. A bare URL string is also accepted — a hand-edit
    that drops the title still renders (labelled with the URL) rather than failing the
    build. Returns "" when there are no resources, so those pages are left unchanged.
    """
    items = []
    for r in resources or []:
        if isinstance(r, dict):
            url = str(r.get("url") or "").strip()
            label = str(r.get("title") or url).strip()
        else:
            url = label = str(r).strip()
        if url:
            label = label.replace("]", "\\]")  # a bracket in a title would break the link
            host = re.sub(r"^www\.", "", re.sub(r"^https?://([^/]+).*$", r"\1", url))
            items.append(f'- [{label}]({url})<span class="cx-link__host">{host}</span>')
    if not items:
        return ""
    return ('\n## Further Information\n\n<div class="cx-links" markdown>\n\n'
            + "\n".join(items) + "\n\n</div>\n")


# --------------------------------------------------------------------------------------
# Competency page assembly
# --------------------------------------------------------------------------------------
def sectionise(body):
    """Split a descriptor body into (preamble, [(h2 title, section body), ...])."""
    parts = re.split(r"^## (.+)$", body, flags=re.M)
    preamble = parts[0]
    sections = [(parts[i].strip(), parts[i + 1]) for i in range(1, len(parts), 2)]
    return preamble, sections


def render_competency(name, fm, body):
    """Build the full markdown for one competency page."""
    preamble, sections = sectionise(body)
    by_title = dict(sections)

    # The preamble is `# Name`, a `**Category:** …` meta line we replace with chips, and
    # sometimes a blockquote note about a shared workbook ladder — that note is worth
    # keeping, so pull it out before discarding the rest.
    note = ""
    for line in preamble.splitlines():
        if line.startswith(">"):
            note += line.lstrip("> ").rstrip() + " "

    cat = fm.get("category", "")
    out = [f"# {name}", ""]

    chips = [f'<a href="../" class="cx-chip cx-chip--cat">{cat}</a>']
    chip = coverage_chip(name)
    if chip:
        chips.append(chip)
    if fm.get("source_label") and fm["source_label"] != name:
        chips.append(f'<span class="cx-chip cx-chip--src">{fm["source_label"]}</span>')
    out += ['<div class="cx-chips">', "\n".join(chips), "</div>", ""]

    # The target statement is the point of the page, so it leads rather than sitting in a
    # section below "Why it matters".
    target = (fm.get("target_statement") or "").strip()
    if target:
        out += ['<div class="cx-target" markdown="span">',
                f'<span class="cx-target__label">Target competency</span>{target}',
                "</div>", ""]

    if note.strip():
        out += ['!!! note "About this descriptor"', f"    {note.strip()}", ""]

    if "Why it matters" in by_title:
        out += ["## Why it matters", by_title["Why it matters"].strip(), ""]

    if "Progression by component" in by_title:
        out += ["## Progression", "",
                '<p class="cx-hint">Each rung is a level a learner is <strong>at</strong>;'
                ' its activities are what carries them to the next one. '
                '<a href="../how-to-read-levels/">How to read these levels</a></p>', "",
                transform_progression(by_title["Progression by component"], name), ""]

    if "Sub-competencies" in by_title:
        out += [transform_subcompetencies(by_title["Sub-competencies"]).strip(), ""]

    # Anything the descriptor grows that this function doesn't know about still renders,
    # rather than silently vanishing from the site.
    known = {"Why it matters", "Target competency", "Progression by component",
             "Sub-competencies"}
    for title, section in sections:
        if title not in known:
            out += [f"## {title}", section.strip(), ""]

    out.append(resources_section(fm.get("resources")))

    updated = fm.get("last_updated")
    if updated:
        out += ["", f'<p class="cx-meta">Descriptor last updated {updated}.</p>']
    return "\n".join(out) + "\n"


def scale_markup(levels):
    """The 0-4 scale as a row of steps, used on the home and how-to-read pages."""
    out = ['<div class="cx-scale">']
    for lv in levels:
        num = str(lv["id"])
        label = lv["label"].split(" - ", 1)[-1]
        out.append(f'<div class="cx-scale__step" data-level="{num}">'
                   f'<span class="cx-rung__badge">{num}</span>'
                   f'<span class="cx-scale__name">{label}</span>'
                   f'<span class="cx-scale__desc">{lv["description"]}</span></div>')
    out.append('</div>')
    return out


def bars_markup(coverage, cats, extra_class=""):
    """Per-category 'n of m covered' progress bars."""
    out = [f'<div class="cx-bars {extra_class}">'.strip()]
    for category in cats:
        known = [n for n in cats[category] if n in coverage]
        if not known:
            continue
        done = sum(1 for n in known if coverage[n] > 0)
        pct = round(100 * done / len(known))
        out.append(f'<div class="cx-bar"><span class="cx-bar__label">{category}</span>'
                   f'<span class="cx-bar__track"><span class="cx-bar__fill" '
                   f'style="width:{pct}%"></span></span>'
                   f'<span class="cx-bar__value">{done}/{len(known)}</span></div>')
    out.append('</div>')
    return out


# --------------------------------------------------------------------------------------
# Load every hand-authored descriptor, keyed by its framework name.
# --------------------------------------------------------------------------------------
by_name = {}
for p in sorted((ROOT / "competencies").glob("*.md")):
    if p.name == "README.md":
        continue
    fm, body = split_frontmatter(p.read_text(encoding="utf-8"))
    if fm.get("name"):
        by_name[fm["name"]] = {"fm": fm, "body": body, "slug": fm.get("slug", p.stem)}


def teaser(name, entry, limit=190):
    """The one-line summary of a competency used on cards and in the big table."""
    text = (entry["fm"].get("target_statement") or "").strip()
    if not text:
        # Sub-competency descriptors carry no target statement; lead with the names of the
        # sub-competencies instead, which is what those pages are actually made of.
        subs = re.findall(r"^### (.+)$", entry["body"], flags=re.M)
        text = ("Sub-competencies: " + ", ".join(subs) + ".") if subs else "—"
    if len(text) > limit:
        text = text[:limit - 3].rsplit(" ", 1)[0] + "…"
    return text


nav_lines = ["* [Home](index.md)"]
counts = {}
page_by_name = {}   # framework name -> page path, relative to the docs root

for category, names in CATS.items():
    present = [n for n in names if n in by_name]
    counts[category] = len(present)
    if not present:  # e.g. Meta / Uncategorized has no descriptor — skip empty sections
        continue
    cat_dir = slugify(category)
    icon, blurb = CATEGORY_META.get(category, ("", ""))

    nav_lines.append(f"* {category}")
    nav_lines.append(f"    * [{category}]({cat_dir}/index.md)")

    # ---- the category overview page ----
    covered = sum(1 for n in present if COVERAGE.get(n, 0) > 0)
    page = [f"# {category}", ""]
    if blurb:
        # markdown="span" so the icon shortcode and its attr_list class are processed —
        # neither runs inside a plain raw-HTML block.
        icon_md = f":{icon}:{{ .cx-cat-icon }} " if icon else ""
        page += [f'<p class="cx-lede" markdown="span">{icon_md}{blurb}</p>', ""]
    page += [f'<p class="cx-count"><strong>{len(present)}</strong> '
             f'{"competency" if len(present) == 1 else "competencies"} · '
             f'<strong>{covered}</strong> with training material</p>', "",
             '<div class="grid cards cx-cards" markdown>', ""]
    for n in present:
        d = by_name[n]
        page += [f"-   __[{n}]({d['slug']}.md)__", "", "    ---", "",
                 f"    {teaser(n, d)}", "", f"    {coverage_chip(n, depth=1)}", ""]
    page += ["</div>", ""]
    with mkdocs_gen_files.open(f"{cat_dir}/index.md", "w") as f:
        f.write("\n".join(page))

    # ---- one page per competency ----
    for name in present:
        d = by_name[name]
        rel = f"{cat_dir}/{d['slug']}.md"
        page_by_name[name] = rel
        with mkdocs_gen_files.open(rel, "w") as f:
            f.write(render_competency(name, d["fm"], d["body"]))
        # Edit button jumps to the real source file, not the generated page.
        mkdocs_gen_files.set_edit_path(rel, f"competencies/{d['slug']}.md")
        nav_lines.append(f"    * [{name}]({rel})")

total = sum(counts.values())


# --------------------------------------------------------------------------------------
# Home page
# --------------------------------------------------------------------------------------
home = [
    "---", "hide:", "  - navigation", "  - toc", "---", "",
    # The H1 stays outside the hero wrapper so MkDocs still reads the page title off it.
    "# The LT Consultant competency framework { .cx-hero__title }", "",
    '<div class="cx-hero" markdown>', "",
    # No category count in the lede: Meta/Uncategorized is one of the framework's six
    # categories but carries no descriptor, so only five are browsable here and a stated
    # number would contradict either the cards below or the framework.
    f'<p class="cx-hero__lede">What a Language Technology Consultant needs to be able to '
    f'do — {total} competencies, each with its rationale, the standard to aim at, and '
    f'the activities that move a person up.</p>',
    "",
    "[Browse by category](#browse-by-category){ .md-button .md-button--primary }",
    "[Look up a competency](all-competencies.md){ .md-button }", "",
    "</div>", "",
    "## Browse by category", "",
    '<div class="grid cards cx-cards" markdown>', "",
]
for category in CATS:
    if not counts.get(category):
        continue
    icon, blurb = CATEGORY_META.get(category, ("", ""))
    n = counts[category]
    covered = sum(1 for x in CATS[category] if COVERAGE.get(x, 0) > 0)
    icon_md = f":{icon}:{{ .lg .middle }} " if icon else ""
    home += [f"-   {icon_md}__[{category}]({slugify(category)}/index.md)__", "",
             "    ---", "",
             f"    {blurb}", "",
             f'    <span class="cx-chip cx-chip--count">{n} competencies</span>'
             f'<span class="cx-chip cx-chip--cov">{covered} with material</span>', ""]
home += ["</div>", "", "## The five levels", "",
         '<p class="cx-lede">Every competency is described on the same '
         'Competency-Based Certification scale. A level names where a learner '
         '<strong>is</strong>; the activities listed against it are what carry them to '
         'the next one.</p>', ""]
home += scale_markup(LEVELS)
home += ["", "[How to read the levels](how-to-read-levels.md){ .md-button }", ""]

if COVERAGE:
    covered_total = sum(1 for v in COVERAGE.values() if v > 0)
    home += ["## Training coverage", "",
             f'<p class="cx-lede"><strong>{covered_total} of {len(COVERAGE)}</strong> '
             f'competencies have at least one training module. The rest are gaps the '
             f'curriculum is working through.</p>', ""]
    home += bars_markup(COVERAGE, CATS)
    home += ["", "[Full coverage table](coverage.md){ .md-button }", ""]

home += ["", '<p class="cx-meta">Every page on this site is generated from the '
         'hand-authored descriptors in '
         '<a href="https://github.com/dhigby/virtual-ltct/tree/main/competencies">'
         '<code>competencies/</code></a> — edit a descriptor there to change what '
         'appears here.</p>']
with mkdocs_gen_files.open("index.md", "w") as f:
    f.write("\n".join(home) + "\n")


# --------------------------------------------------------------------------------------
# How to read the levels
# --------------------------------------------------------------------------------------
howto = [
    "# How to read the levels", "",
    '<p class="cx-lede">Every competency in this framework is described on one scale, the '
    'Competency-Based Certification (CBC) scale. It has one feature that catches everyone '
    'out the first time, so it is worth a page of its own.</p>', "",
    "## The scale", "",
]
howto += scale_markup(LEVELS)
howto += ["", "## A level names where a learner is — not what they are doing", "",
          "This is the offset. **A level says where a learner *is*. The activities listed "
          "against that level are what they do to reach the *next* one.**", "",
          "So on a competency page, the rung labelled **1 — Has Knowledge** holds the "
          "activities that carry a learner from *Has Knowledge* up to **2 — With "
          "Assistance**. Every rung on this site spells that out on its last line, so you "
          "never have to hold the offset in your head while reading.", "",
          '!!! warning "Retired vocabulary"', "",
          "    An older scale — *Learner · Advanced Beginner · Practitioner · "
          "Trainer/Proficient · Expert* — is still in circulation on some documents. It "
          "came from a spreadsheet whose header had two rows, one naming the destination "
          "and one naming the performer; the import kept only the second, which shifted "
          "every rung by one. Read those documents with care, and use the CBC scale "
          "above.", "",
          "## Where a course lands you", "",
          "A training course states the level a learner **stands at once they finish**, so "
          "a course target is always 1–4 — never 0, since no course leaves someone at No "
          "Competency.", "",
          '<p class="cx-meta">The scale is defined once, in '
          '<a href="https://github.com/dhigby/virtual-ltct/blob/main/outcome-levels.yaml">'
          '<code>outcome-levels.yaml</code></a>, and this page is generated from it.</p>']
with mkdocs_gen_files.open("how-to-read-levels.md", "w") as f:
    f.write("\n".join(howto) + "\n")


# --------------------------------------------------------------------------------------
# All competencies — one filterable table
# --------------------------------------------------------------------------------------
rows = []
for category, names in CATS.items():
    for n in names:
        if n not in by_name:
            continue
        d = by_name[n]
        cov = COVERAGE.get(n, 0)
        cov_cell = ('<span class="cx-pill cx-pill--gap">gap</span>' if cov == 0
                    else f'<span class="cx-pill">{cov}</span>')
        rows.append(f'<tr data-category="{category}" data-name="{n.lower()}">'
                    f'<td><a href="{slugify(category)}/{d["slug"]}/">{n}</a></td>'
                    f'<td class="cx-td-cat">{category}</td>'
                    f'<td class="cx-td-target">{teaser(n, d, limit=220)}</td>'
                    f'<td class="cx-td-cov">{cov_cell}</td></tr>')

options = "\n".join(f'<option value="{c}">{c}</option>' for c in CATS if counts.get(c))
allpage = [
    "---", "hide:", "  - toc", "---", "",
    "# All competencies", "",
    f'<p class="cx-lede">The whole framework — {total} competencies — in one place. '
    f'Filter by name or category to find the one you need.</p>', "",
    '<div class="cx-filter" data-cx-filter>',
    '<input type="search" class="cx-filter__q" placeholder="Filter by name…" '
    'aria-label="Filter competencies by name">',
    '<select class="cx-filter__cat" aria-label="Filter competencies by category">',
    '<option value="">All categories</option>', options, "</select>",
    '<span class="cx-filter__count" aria-live="polite"></span>',
    "</div>", "",
    '<div class="cx-table-wrap">',
    '<table class="cx-table">',
    "<thead><tr><th>Competency</th><th>Category</th><th>Target competency</th>"
    "<th>Modules</th></tr></thead>", "<tbody>",
    "\n".join(rows),
    "</tbody></table>",
    '<p class="cx-filter__empty" hidden>No competency matches that filter.</p>',
    "</div>", "",
]
with mkdocs_gen_files.open("all-competencies.md", "w") as f:
    f.write("\n".join(allpage) + "\n")


# --------------------------------------------------------------------------------------
# Coverage — re-rendered from COVERAGE.md, which stays the single source
# --------------------------------------------------------------------------------------
def coverage_page():
    """Build the coverage page from COVERAGE.md.

    Falls back to the committed text, with competency names linked, if the file's shape
    ever drifts from what the parser above expects — a plain-looking page beats no page.
    """
    src = ROOT / "COVERAGE.md"
    if not src.exists():
        return None
    text = re.sub(r"<!--.*?-->\n?", "", src.read_text(encoding="utf-8"), count=1)
    if not COVERAGE:
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

    m = re.search(r"_Last generated: (.+?)\._", text)
    generated = m.group(1) if m else ""

    covered_total = sum(1 for v in COVERAGE.values() if v > 0)
    gaps = sorted(n for n, v in COVERAGE.items() if v == 0)
    page = ["# Training coverage", "",
            f'<p class="cx-lede">Which competencies have training material and which are '
            f'still gaps. <strong>{covered_total} of {len(COVERAGE)}</strong> '
            f'competencies have at least one module; <strong>{len(gaps)}</strong> have '
            f'none yet.</p>', ""]
    page += bars_markup(COVERAGE, CATS, extra_class="cx-bars--lg")
    page += [""]

    for category in CATS:
        known = [n for n in CATS[category] if n in COVERAGE]
        if not known:
            continue
        page += [f"## {category}", "", "| Competency | Modules | Status |",
                 "| --- | --- | --- |"]
        for n in known:
            link = f"[{n}]({page_by_name[n]})" if n in page_by_name else n
            cnt = COVERAGE[n]
            page.append(f"| {link} | {cnt} | {'✅' if cnt else '⛔ gap'} |")
        page.append("")

    if gaps:
        page += ["## Gaps — competencies with no module yet", "",
                 '<div class="cx-gaps" markdown>', ""]
        for n in gaps:
            page.append(f"- [{n}]({page_by_name[n]})" if n in page_by_name else f"- {n}")
        page += ["", "</div>", ""]

    if generated:
        page.append(f'<p class="cx-meta">Generated from <code>COVERAGE.md</code>, last '
                    f'regenerated {generated}.</p>')
    return "\n".join(page) + "\n"


cov_md = coverage_page()

nav_lines.append("* Framework")
nav_lines.append("    * [All competencies](all-competencies.md)")
if cov_md:
    with mkdocs_gen_files.open("coverage.md", "w") as f:
        f.write(cov_md)
    nav_lines.append("    * [Training coverage](coverage.md)")
nav_lines.append("    * [How to read the levels](how-to-read-levels.md)")

with mkdocs_gen_files.open("SUMMARY.md", "w") as f:
    f.write("\n".join(nav_lines) + "\n")
