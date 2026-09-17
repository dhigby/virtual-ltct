"""MkDocs build hook: generate ONE course's review site from its markdown package.

Run by the `mkdocs-gen-files` plugin during a build that uses `mkdocs-review.yml`
(never the main `mkdocs.yml`, which builds the competency framework site). Driven
entirely by environment variables, because gen-files scripts take no arguments:

  LTCT_COURSE_ROOT    (required) abs path to the course folder -- the working tree,
                      or a git worktree when previewing another ref
  LTCT_VIEW           reviewer | learner          (default: reviewer)
  LTCT_COURSE_REF     git ref that GitHub links point at       (default: main)
  LTCT_COURSE_FOLDER  real folder name under modules/ (default: basename of ROOT)
  LTCT_REVIEW_BASE    e.g. https://.../review -- sibling-course links; empty locally
  LTCT_PIPELINE_SLUGS comma-joined branch_slugs that HAVE a review site
  LTCT_COURSE_PR      PR url, shown on how-to-review
  LTCT_COURSE_TITLE   overrides the course title

THE DOCS ROOT IS A FLAT MIRROR OF THE COURSE FOLDER. Only README.md is renamed (to
index.md). That is deliberate: every intra-course relative link an author wrote
(`](06-scenario-bank.md)`, `![](image-1.png)`) is then already correct, and MkDocs'
own relative-path processor resolves it. Any nested layout would force path surgery
on every link in the corpus.

Two views are generated from the same source:

  reviewer -- the whole package, for the SME (stage 5) and internal reviewer (stage 6)
  learner  -- what a pilot learner (stage 7) should see: no design doc, no mentor
              guide, no video script, and NO QUIZ ANSWER KEY

The learner view is a disclosure boundary, so it fails closed in two places:
`strip_answer_keys` withholds a quiz it cannot fully strip, and a link pointing at a
view-excluded file is rendered as plain text rather than falling through to GitHub
(which would hand a learner the mentor guide in one click).
"""
import datetime
import os
import pathlib
import posixpath
import re
import sys
from urllib.parse import quote, unquote, urlsplit

import yaml
import mkdocs_gen_files

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO_ROOT / "scripts"))
from course_stage import branch_slug, is_lesson  # noqa: E402

GH = "https://github.com/dhigby/virtual-ltct"
MATERIAL_SUFFIXES = ("-scenario-bank.md", "-mentor-guide.md", "-quiz.md", "-video-script.md")


def _env(name, default=""):
    return (os.environ.get(name) or default).strip()


COURSE_ROOT = _env("LTCT_COURSE_ROOT")
if not COURSE_ROOT:
    raise SystemExit(
        "gen_course_site: LTCT_COURSE_ROOT is not set. This script only runs under "
        "mkdocs-review.yml, via scripts/review_site.py or build_review_sites.py.")
COURSE = pathlib.Path(COURSE_ROOT).expanduser().resolve()
if not COURSE.is_dir():
    raise SystemExit("gen_course_site: LTCT_COURSE_ROOT is not a directory: %s" % COURSE)

VIEW = _env("LTCT_VIEW", "reviewer").lower()
if VIEW not in ("reviewer", "learner"):
    raise SystemExit("gen_course_site: LTCT_VIEW must be 'reviewer' or 'learner'")

FOLDER = _env("LTCT_COURSE_FOLDER") or COURSE.name
REF = _env("LTCT_COURSE_REF", "main")
REVIEW_BASE = _env("LTCT_REVIEW_BASE").rstrip("/")
PIPELINE = {s for s in (x.strip() for x in _env("LTCT_PIPELINE_SLUGS").split(",")) if s}
PR_URL = _env("LTCT_COURSE_PR")
try:
    MAX_ASSET = int(_env("LTCT_REVIEW_MAX_ASSET_BYTES") or 8 * 1024 * 1024)
except ValueError:
    MAX_ASSET = 8 * 1024 * 1024

REPO_PREFIX = "modules/%s" % FOLDER


# --------------------------------------------------------------------------------------
# Frontmatter -- same shape as scripts/gen_site.py's helper (copied, not imported:
# gen_site.py is a top-level script that runs the whole competency build on import).
# --------------------------------------------------------------------------------------
def split_frontmatter(text):
    if text.startswith("---"):
        end = text.find("\n---", 3)
        if end != -1:
            return yaml.safe_load(text[3:end]) or {}, text[end + 4:].lstrip("\n")
    return {}, text


# --------------------------------------------------------------------------------------
# Answer-key stripping -- the learner view's disclosure boundary
#
# Keys are NOT consistently marked across the corpus. Three shapes exist today:
#   "## Answer Key" / "## Answer key"      (an H2 -- most courses)
#   "**Answer key:**"                      (a bold paragraph, no heading)
#   "**Answer key (Section 1):**" twice    (interleaved mid-document)
# so truncating at the first "## Answer" heading would leak answers in real files.
# Strip every block, then re-scan; anything left means we did not understand the file
# and the caller must withhold the page entirely.
# --------------------------------------------------------------------------------------
KEY_RE = re.compile(r"^\s{0,3}(#{1,6}\s*answer\s*key|\*{1,2}answer\s*key)", re.I | re.M)
HEADING_RE = re.compile(r"^\s{0,3}(#{1,6})\s")
# Conservative extras: inline per-option answer marking that KEY_RE would not catch.
RESIDUAL_RE = re.compile(r"\(\s*correct\s*\)|^\s{0,3}\*{1,2}\s*correct answer", re.I | re.M)


def strip_answer_keys(md):
    """Remove every answer-key block. Returns (text, ok); ok=False => withhold page."""
    lines = md.split("\n")
    out, i = [], 0
    while i < len(lines):
        line = lines[i]
        if not KEY_RE.match(line):
            out.append(line)
            i += 1
            continue
        # Found a marker. Decide what terminates its block.
        h = HEADING_RE.match(line)
        level = len(h.group(1)) if h else None
        i += 1
        while i < len(lines):
            nxt = HEADING_RE.match(lines[i])
            if nxt and (level is None or len(nxt.group(1)) <= level):
                break          # next heading of same-or-higher rank ends the block
            i += 1
    text = "\n".join(out).rstrip() + "\n"
    ok = KEY_RE.search(text) is None and RESIDUAL_RE.search(text) is None
    return text, ok


# --------------------------------------------------------------------------------------
# Inventory
# --------------------------------------------------------------------------------------
def excluded_md(name):
    """Markdown a pilot learner must not be shown. (A quiz is stripped, not dropped.)"""
    if VIEW != "learner":
        return False
    if name == "00-design.md":
        return True
    return name.endswith(("-mentor-guide.md", "-video-script.md"))


# Non-markdown companions of the excluded files, plus the LMS export artifacts. These
# are a real disclosure route, not a tidiness question: `09-video-script.pptx` sits
# beside its excluded .md, and the `qti_*.zip` quiz exports carry the correct answers
# as plain XML (<varequal>T</varequal>), so publishing them to a learner hands over the
# answer key a download at a time.
LMS_EXPORT_RE = re.compile(r"^(qti[_-]|cypher-)|\.imscc$", re.I)


def excluded_asset(name):
    if VIEW != "learner":
        return False
    stem = name.rsplit(".", 1)[0].lower()
    if stem == "00-design" or stem.endswith(("-mentor-guide", "-video-script", "-quiz")):
        return True
    return bool(LMS_EXPORT_RE.search(name))


def safe_asset(name):
    stem, ext = posixpath.splitext(name)
    s = re.sub(r"[^A-Za-z0-9._-]+", "-", stem).strip("-.") or "asset"
    return s + ext.lower()


md_files, asset_files = [], []
for _p in sorted(COURSE.iterdir()):
    if _p.is_dir() or _p.name.startswith("."):
        continue
    (md_files if _p.suffix.lower() == ".md" else asset_files).append(_p)

EXCLUDED = {p.name for p in md_files if excluded_md(p.name)}
EXCLUDED |= {p.name for p in asset_files if excluded_asset(p.name)}
INCLUDED = [p for p in md_files if p.name not in EXCLUDED]
ASSETS = [p for p in asset_files if p.name not in EXCLUDED]
MD_PAGES = {p.name: ("index.md" if p.name == "README.md" else p.name) for p in INCLUDED}

ASSET_MAP, BIG, _seen = {}, {}, set()
for _p in ASSETS:
    dest = safe_asset(_p.name)
    if dest in _seen:
        stem, ext = posixpath.splitext(dest)
        n = 2
        while "%s-%d%s" % (stem, n, ext) in _seen:
            n += 1
        dest = "%s-%d%s" % (stem, n, ext)
    _seen.add(dest)
    ASSET_MAP[_p.name] = dest
    try:
        if _p.stat().st_size > MAX_ASSET:
            BIG[_p.name] = _p.stat().st_size
    except OSError:
        pass

UNRESOLVED = []
WITHHELD = []


# --------------------------------------------------------------------------------------
# Link rewriting
# --------------------------------------------------------------------------------------
INLINE = re.compile(
    r'(?P<bang>!?)\[(?P<text>(?:[^\[\]]|\[[^\]]*\])*)\]'
    r'\(\s*(?P<target><[^<>]*>|[^()\s]*(?:\([^()]*\)[^()\s]*)*)'
    r'(?P<title>\s+(?:"[^"]*"|\'[^\']*\'))?\s*\)')
REFDEF = re.compile(r'^(?P<i>[ ]{0,3})\[(?P<label>[^\]^][^\]]*)\]:\s*'
                    r'(?P<target><[^<>]*>|\S+)(?P<rest>.*)$')
HTMLATT = re.compile(r'(?P<pre>\b(?:src|href)\s*=\s*)(?P<q>["\'])(?P<target>[^"\']*)(?P=q)')
CODE_SPAN = re.compile(r'(?<!`)(`+)(?!`)(.+?)(?<!`)\1(?!`)')
FENCE = re.compile(r'^\s{0,3}(`{3,}|~{3,})')


def blob_url(repo_rel):
    return "%s/blob/%s/%s" % (GH, REF, quote(repo_rel))


def raw_url(repo_rel):
    return "%s/raw/%s/%s" % (GH, REF, quote(repo_rel))


def _escaping(path, tail):
    """A link that leaves the course folder."""
    norm = posixpath.normpath(posixpath.join(REPO_PREFIX, path))
    m = re.match(r"^modules/([^/]+)/(.*)$", norm)
    if m and m.group(1) != FOLDER:
        other = branch_slug(m.group(1))
        if REVIEW_BASE and other in PIPELINE:
            leaf = m.group(2)
            if leaf in ("", "README.md"):
                page = ""
            elif leaf.endswith(".md"):
                page = posixpath.splitext(leaf)[0] + "/"
            else:
                page = safe_asset(posixpath.basename(leaf))
            return "%s/%s/%s%s" % (REVIEW_BASE, other, page, tail)
    return blob_url(norm) + tail


def classify(raw, source_name):
    """Return (new_target, drop). drop=True => render the link as plain text."""
    t = raw.strip()
    angled = t.startswith("<") and t.endswith(">")
    bare = t[1:-1] if angled else t
    if not bare or bare.startswith("#") or bare.startswith("//"):
        return raw, False
    sp = urlsplit(bare)
    if sp.scheme or sp.netloc or not sp.path:
        return raw, False                                    # remote / mailto / bare #frag
    tail = ("?" + sp.query if sp.query else "") + ("#" + sp.fragment if sp.fragment else "")
    norm = posixpath.normpath(unquote(sp.path))

    if norm.startswith(".."):
        new = _escaping(unquote(sp.path), tail)
    elif norm in EXCLUDED:
        return None, True            # view-excluded: never link out, never to GitHub
    elif norm in MD_PAGES:
        new = quote(MD_PAGES[norm]) + tail
    elif norm in BIG:
        new = raw_url("%s/%s" % (REPO_PREFIX, norm)) + tail
    elif norm in ASSET_MAP:
        new = quote(ASSET_MAP[norm]) + tail
    else:
        UNRESOLVED.append((source_name, bare))
        new = blob_url("%s/%s" % (REPO_PREFIX, norm)) + tail
    return ("<%s>" % new if angled else new), False


def _sub_inline(m, source_name):
    new, drop = classify(m.group("target"), source_name)
    if drop:
        # Keep the words, lose the link. An image loses its alt text entirely.
        return "" if m.group("bang") else m.group("text")
    return "%s[%s](%s%s)" % (m.group("bang"), m.group("text"), new, m.group("title") or "")


def _sub_refdef(m, source_name):
    new, drop = classify(m.group("target"), source_name)
    if drop:
        return "%s[%s]: %s" % (m.group("i"), m.group("label"), blob_url(REPO_PREFIX))
    return "%s[%s]: %s%s" % (m.group("i"), m.group("label"), new, m.group("rest"))


def _sub_htmlatt(m, source_name):
    new, drop = classify(m.group("target"), source_name)
    if drop:
        new = blob_url(REPO_PREFIX)
    return "%s%s%s%s" % (m.group("pre"), m.group("q"), new, m.group("q"))


def rewrite_line(line, source_name):
    spans = []

    def stash(m):
        spans.append(m.group(0))
        return "\x00%d\x00" % (len(spans) - 1)

    tmp = CODE_SPAN.sub(stash, line)
    tmp = INLINE.sub(lambda m: _sub_inline(m, source_name), tmp)
    tmp = REFDEF.sub(lambda m: _sub_refdef(m, source_name), tmp)
    tmp = HTMLATT.sub(lambda m: _sub_htmlatt(m, source_name), tmp)
    return re.sub(r"\x00(\d+)\x00", lambda m: spans[int(m.group(1))], tmp)


def rewrite_body(text, source_name):
    """Rewrite links outside fenced code blocks and inline code spans."""
    out, fence_char, fence_len = [], None, 0
    for line in text.split("\n"):
        m = FENCE.match(line)
        if fence_char is None:
            if m:
                fence_char, fence_len = m.group(1)[0], len(m.group(1))
                out.append(line)
                continue
            out.append(rewrite_line(line, source_name))
        else:
            if m and m.group(1)[0] == fence_char and len(m.group(1)) >= fence_len:
                fence_char = None
            out.append(line)
    return "\n".join(out)


# --------------------------------------------------------------------------------------
# Page assembly
# --------------------------------------------------------------------------------------
H1_RE = re.compile(r"^#\s+(.+?)\s*$", re.M)
TIME_RE = re.compile(r"^\*\*Estimated time:\*\*\s*(\d+)\s*minutes", re.M)


def title_of(name, body):
    m = H1_RE.search(body)
    if m:
        return m.group(1).strip()
    stem = re.sub(r"^\d{2}[a-z]?-", "", pathlib.Path(name).stem)
    return stem.replace("-", " ").strip().capitalize() or name


def drop_first_h1(body):
    m = H1_RE.search(body)
    if not m or body[:m.start()].strip():
        return body
    return body[m.end():].lstrip("\n")


def group_of(name):
    if name == "README.md":
        return 0
    if is_lesson(name):
        return 1
    if name.endswith(MATERIAL_SUFFIXES):
        return 2
    return 3


def material_rank(name):
    for i, suf in enumerate(MATERIAL_SUFFIXES):
        if name.endswith(suf):
            return i
    return len(MATERIAL_SUFFIXES)


sources = {p.name: p.read_text(encoding="utf-8") for p in INCLUDED}
readme_fm, readme_body = split_frontmatter(sources.get("README.md", ""))
if not isinstance(readme_fm, dict):
    readme_fm = {}
COURSE_TITLE = (_env("LTCT_COURSE_TITLE")
                or readme_fm.get("title")
                or title_of("README.md", readme_body)
                or FOLDER)

lessons, materials, reference = [], [], []
total_minutes = 0

for _p in INCLUDED:
    name = _p.name
    if name == "README.md":
        continue
    body = sources[name]
    if is_lesson(name) or name.endswith("-scenario-bank.md"):
        for _m in TIME_RE.finditer(body):
            total_minutes += int(_m.group(1))

    withheld = False
    if VIEW == "learner" and name.endswith("-quiz.md"):
        body, ok = strip_answer_keys(body)
        if not ok:
            WITHHELD.append(name)
            withheld = True

    title = title_of(name, sources[name])
    if withheld:
        page = ('# %s\n\n!!! warning "Quiz withheld from the learner view"\n'
                "    This quiz's solutions could not be separated automatically, so the\n"
                "    whole page is held back rather than risk showing you the answers.\n"
                "    Ask your facilitator for a copy.\n" % title)
    else:
        page = rewrite_body(body, name)

    out_name = MD_PAGES[name]
    with mkdocs_gen_files.open(out_name, "w") as f:
        f.write(page)
    mkdocs_gen_files.set_edit_path(out_name, "%s/%s" % (REPO_PREFIX, name))

    entry = (out_name, title, name)
    g = group_of(name)
    if g == 1:
        lessons.append(entry)
    elif g == 2:
        materials.append(entry)
    else:
        reference.append(entry)

lessons.sort(key=lambda e: e[2])
materials.sort(key=lambda e: (material_rank(e[2]), e[2]))
reference.sort(key=lambda e: (e[2] != "00-design.md", e[2]))

# ---- index.md -------------------------------------------------------------------------
facts = []
if readme_fm.get("target_outcome_level"):
    facts.append("**Outcome level:** %s" % readme_fm["target_outcome_level"])
if readme_fm.get("competencies"):
    facts.append("**Competencies:** %s" % ", ".join(readme_fm["competencies"]))
if readme_fm.get("content_type"):
    facts.append("**Content type:** %s" % readme_fm["content_type"])
if lessons:
    facts.append("**%d lesson%s** &middot; about %s" % (
        len(lessons), "" if len(lessons) == 1 else "s",
        ("%.1f hours" % (total_minutes / 60.0)) if total_minutes >= 60
        else ("%d minutes" % total_minutes)))
facts.append("**Built from:** `%s`" % REF)
if PR_URL:
    facts.append("**Pull request:** [%s](%s)" % (PR_URL.rsplit("/", 1)[-1], PR_URL))

view_line = ("You are reading the **reviewer view** -- the complete package, including the "
             "design document, mentor guide and the quiz answer key."
             if VIEW == "reviewer" else
             "You are reading the **learner view** -- the course as a learner sees it. The "
             "design document, mentor guide and quiz solutions are not included.")

index = ["# %s\n\n" % COURSE_TITLE, '!!! info "Course package"\n\n']
index += ["    %s<br>\n" % f for f in facts]
index += ["\n    %s\n\n" % view_line,
          rewrite_body(drop_first_h1(readme_body), "README.md")]
with mkdocs_gen_files.open("index.md", "w") as f:
    f.write("".join(index))
mkdocs_gen_files.set_edit_path("index.md", "%s/README.md" % REPO_PREFIX)

# ---- assets ---------------------------------------------------------------------------
for _p in ASSETS:
    if _p.name in BIG:
        continue
    try:
        _data = _p.read_bytes()
    except OSError:
        continue
    with mkdocs_gen_files.open(ASSET_MAP[_p.name], "wb") as f:
        f.write(_data)

# Theme statics live in the MAIN checkout's docs/ (single source of truth); the review
# config's docs_dir is deliberately empty, so copy them into the virtual tree.
for _rel in ("stylesheets/extra.css", "assets/logo.svg", "assets/favicon.svg"):
    _src = REPO_ROOT / "docs" / _rel
    if _src.exists():
        with mkdocs_gen_files.open(_rel, "wb") as f:
            f.write(_src.read_bytes())

# ---- how-to-review.md -----------------------------------------------------------------
who = {
    "reviewer": ("Read the whole package. Check facts, tool names, menu paths and versions "
                 "against reality (stage 5), and the teaching, tone and ordering as if you "
                 "were the learner (stage 6)."),
    "learner": ("Work through the lessons in order, then the scenario bank, then the quiz, "
                "as a learner would. Note anything confusing or out of order."),
}[VIEW]

howto = ["# How to review this\n\n", "%s\n\n" % who,
         "**Leave feedback** as a comment on the course's pull request (line by line on the "
         "*Files changed* tab) or on its tracker issue. You do not need to edit anything "
         "yourself.\n\n",
         "| | |\n|---|---|\n",
         "| Course | `%s` |\n" % FOLDER,
         "| View | %s |\n" % VIEW,
         "| Built from | `%s` |\n" % REF,
         "| Built at | %s |\n" % datetime.datetime.now(
             datetime.timezone.utc).strftime("%Y-%m-%d %H:%M UTC"),
         "| Source | [%s on GitHub](%s/tree/%s/%s) |\n\n" % (
             FOLDER, GH, REF, quote(REPO_PREFIX))]

if PR_URL:
    howto.append("Pull request: [%s](%s)\n\n" % (PR_URL, PR_URL))
if WITHHELD:
    howto.append("## Pages withheld from this view\n\n")
    howto += ["- `%s` -- its solutions could not be separated automatically.\n" % n
              for n in WITHHELD]
    howto.append("\n")
if BIG:
    howto.append("## Large files, linked rather than included\n\n")
    howto += ["- [%s](%s) -- %.1f MB\n" % (n, raw_url("%s/%s" % (REPO_PREFIX, n)),
                                           s / 1048576.0) for n, s in sorted(BIG.items())]
    howto.append("\n")
if UNRESOLVED:
    howto.append("## Links that could not be resolved\n\n")
    howto.append("These point at files that are not in the course folder; they fall back "
                 "to GitHub and may 404.\n\n")
    howto += ["- `%s` -> `%s`\n" % (src, tgt) for src, tgt in sorted(set(UNRESOLVED))]

with mkdocs_gen_files.open("how-to-review.md", "w") as f:
    f.write("".join(howto))

# ---- nav ------------------------------------------------------------------------------
nav = ["* [%s](index.md)" % COURSE_TITLE]
if lessons:
    nav.append("* Lessons")
    nav += ["    * [%s](%s)" % (t, n) for n, t, _ in lessons]
if materials:
    nav.append("* Facilitator materials" if VIEW == "reviewer"
               else "* Practice and assessment")
    nav += ["    * [%s](%s)" % (t, n) for n, t, _ in materials]
if reference:
    nav.append("* Reference")
    nav += ["    * [%s](%s)" % (t, n) for n, t, _ in reference]
nav.append("* [How to review this](how-to-review.md)")

with mkdocs_gen_files.open("SUMMARY.md", "w") as f:
    f.write("\n".join(nav) + "\n")
