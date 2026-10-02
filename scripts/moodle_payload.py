#!/usr/bin/env python3
"""Render one course into a publish payload -- the platform boundary.

INTENT.md makes content portability a hard constraint:

    Nothing may bind course content to one platform's format: the markdown in this repo
    is the asset, and any renderer or LMS is replaceable. Prefer the reversible choice.

So the publisher splits in two, and this is the upper half. It knows about courses,
lessons, the disclosure boundary and the CBC package; it knows nothing about Moodle's
API. scripts/moodle_client.py is the lower half and knows only the reverse. If Moodle is
ever replaced, everything here survives.

The payload is also what makes the disclosure check possible BEFORE anything leaves the
machine -- scripts/check_moodle_payload.py reads it, exactly as check_learner_view.py
reads the built review site. Nothing is pushed that has not been read back and verified.

WHAT COMES OUT

    <out>/<slug>/manifest.json      the structure: sections, modules, quizzes
    <out>/<slug>/pages/<name>.html  one rendered page per included markdown file
    <out>/<slug>/assets/<name>      every asset a published page references, as the
                                    LIGHTER copy scripts/image_reduce.py makes of it --
                                    same name as the committed file, recorded in
                                    manifest["assets"] with both hashes so the gate can
                                    trace every delivered byte back to its source

The committed files are only read. A payload directory inside the repository is refused,
so a build can never leave reduced copies in the working tree (FR-002).

Two tokens survive into the HTML for the client to resolve, because neither can be known
until the push is under way:

    @@PLUGINFILE@@/<name>           an asset, once it is in the module's file area
    @@MODULE:<idnumber>@@           a sibling module, once it has a course-module id

Both are Moodle-shaped only in spelling; a different client would rewrite them its own
way. @@PLUGINFILE@@ is what Moodle itself stores in the database, so emitting it here
means the client hands Moodle what it already expects.

WHAT IS HELD BACK (learner view)

The design doc, mentor guide and video scripts never reach a learner course, and every
quiz answer key is stripped. The rules come from scripts/disclosure.py, shared with the
review site so the two views cannot drift. A quiz whose key cannot be cleanly separated
is WITHHELD WHOLE, never partially stripped.

Answer keys do reach Moodle, but only as the `correct` flag and feedback inside the
question data, where Moodle's own capabilities protect them. They must never appear in
page HTML -- that is the line check_moodle_payload.py enforces.

Usage:
  python scripts/moodle_payload.py --slug <slug> [--view learner|reviewer] --out <dir>
"""
import argparse
import hashlib
import json
import pathlib
import re
import shutil
import sys
from urllib.parse import unquote, urlsplit

import markdown
import yaml

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
import disclosure  # noqa: E402
import image_reduce  # noqa: E402
from course_stage import branch_slug, is_lesson  # noqa: E402
from quiz_parse import QuizError, parse_quiz_file  # noqa: E402

REPO = pathlib.Path(__file__).resolve().parent.parent
MODULES = REPO / "modules"

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

# Kept in step with mkdocs-review.yml so a page reads the same in Moodle as on the review
# site. The Material-specific emoji extension is dropped: it needs the theme installed and
# emits inline SVG that Moodle's editor filters would strip anyway.
MD_EXTENSIONS = [
    "admonition", "attr_list", "md_in_html", "tables", "def_list", "footnotes",
    "pymdownx.superfences", "pymdownx.highlight", "pymdownx.inlinehilite",
    "pymdownx.details", "pymdownx.tasklist", "toc",
]
MD_CONFIG = {
    "pymdownx.highlight": {"anchor_linenums": True},
    "pymdownx.tasklist": {"custom_checkbox": True},
}

TIME_RE = re.compile(r"^\*\*Estimated time:\*\*\s*(\d+)\s*minutes", re.M)
H1_RE = re.compile(r"^#\s+(.+)$", re.M)
VIDEO_RE = re.compile(r"\*\*Watch the video:\*\*\s*(.*)$", re.M)
PENDING_VIDEO_RE = re.compile(r"_To be recorded at stage \d+\._", re.I)
MODULE_NUMBER_RE = re.compile(r"^(\d+[a-z]?)-")
IDNUMBER_MAX = 100   # VARCHAR(100) in course, course_modules and question
SRC_RE = re.compile(r"""(?P<attr>\b(?:src|href)\s*=\s*)(?P<q>["'])(?P<url>[^"']*)(?P=q)""")

# Every page is wrapped in this, so moodle/local_ltuse/styles.css can style published
# content without reaching any other page on the Moodle site.
PAGE_CLASS = "local-ltuse-page"

# SC-002: a lesson, its HTML plus every image on it, should load in one sitting on a slow
# link. Over this the build says so; it does not refuse.
PAGE_BUDGET = 1024 * 1024

WITHHELD_HTML = (
    '<div class="admonition warning">\n'
    '<p class="admonition-title">Quiz withheld</p>\n'
    '<p>This quiz could not be published because its answer key could not be cleanly '
    'separated from the questions. Ask your facilitator for a copy.</p>\n'
    '</div>\n')


def completion_for(kind, threshold_pct):
    """What a learner must do for a module to count as complete: the one rule (spec 004, R1).

    Every page, the withheld-quiz placeholder included, is complete once it is viewed. A
    quiz with a pass mark is complete once it is passed; one without is complete once it
    is submitted, because a quiz that has nothing to pass cannot demand a pass. The course
    is complete when ALL of its modules are (the manifest's "completion": "all").

    Plain words, not Moodle fields (Principle II): only local_ltuse's completion_rule
    class gives them a meaning in Moodle.
    """
    if kind == "quiz":
        positive = isinstance(threshold_pct, int) and not isinstance(threshold_pct, bool) \
            and threshold_pct > 0
        return "pass" if positive else "submit"
    return "view"


def wrap(html):
    """Scope a page body so the plugin stylesheet can reach it and nothing else."""
    return '<div class="%s">\n%s\n</div>\n' % (PAGE_CLASS, html.rstrip())


def split_frontmatter(text):
    if text.startswith("---"):
        end = text.find("\n---", 3)
        if end != -1:
            return yaml.safe_load(text[3:end]) or {}, text[end + 4:].lstrip("\n")
    return {}, text


def render(md_text):
    return markdown.Markdown(extensions=MD_EXTENSIONS,
                             extension_configs=MD_CONFIG).convert(md_text)


def title_of(md_text, fallback):
    m = H1_RE.search(md_text)
    return m.group(1).strip() if m else fallback


def minutes_of(md_text):
    m = TIME_RE.search(md_text)
    return int(m.group(1)) if m else None


def module_key(filename):
    """The part of a module's identity that comes from its file: its number.

    `03-checking-the-wordlist.md` is `03`, `01a-quiz.md` is `01a`; a file with no number
    keeps its stem. Not the whole filename, because Moodle stores an idnumber in a
    100-character column and a long course slug plus a long filename overflows it (a
    publish then fails outright). A course's file numbers are unique by convention;
    `Payload.build()` refuses a course where two published files share one.
    """
    m = MODULE_NUMBER_RE.match(filename)
    return m.group(1) if m else filename.rsplit(".", 1)[0]


def idnumber(slug, key):
    """Stable identity for one published module, and the whole idempotency story.

    It lives in Moodle on the course module, not in a repo state file: nothing new to
    keep honest, it survives someone else republishing, and it makes migrating to a
    different Moodle server a re-publish rather than a data move. `key` is
    `module_key()` of the source file, plus a suffix for a quiz split or a question.
    """
    return "ltct:%s:%s" % (branch_slug(slug), key)


class Payload:
    def __init__(self, folder, view):
        self.folder = folder
        self.view = view
        self.slug = folder.name
        self.url_slug = branch_slug(folder.name)
        self.notes = []
        self.withheld = []

    # -- inventory ---------------------------------------------------------------------
    def inventory(self):
        md_files, assets = [], []
        for p in sorted(self.folder.iterdir()):
            if p.is_dir() or p.name.startswith("."):
                continue
            (md_files if p.suffix.lower() == ".md" else assets).append(p)
        for p in sorted(self.folder.rglob("*")):
            if (p.is_dir() or p.parent == self.folder or p.suffix.lower() == ".md"
                    or any(part.startswith(".") for part in
                           p.relative_to(self.folder).parts)):
                continue
            assets.append(p)

        included = [p for p in md_files
                    if p.name != "README.md" and not disclosure.excluded_md(p.name, self.view)]
        assets = [p for p in assets if not disclosure.excluded_asset(p.name, self.view)]
        return included, assets

    # -- link rewriting ----------------------------------------------------------------
    def rewrite(self, html, page_assets, module_ids):
        """Point every intra-course reference at a token the client can resolve."""
        def sub(m):
            url = m.group("url")
            split = urlsplit(url)
            if split.scheme or split.netloc or url.startswith("#"):
                return m.group(0)          # external, or an in-page anchor
            target = unquote(split.path)
            anchor = ("#" + split.fragment) if split.fragment else ""
            name = target.rsplit("/", 1)[-1]

            if target in module_ids or name in module_ids:
                key = target if target in module_ids else name
                return "%s%s@@MODULE:%s@@%s%s" % (m.group("attr"), m.group("q"),
                                                  module_ids[key], anchor, m.group("q"))
            if target in page_assets:
                return "%s%s@@PLUGINFILE@@/%s%s" % (m.group("attr"), m.group("q"),
                                                    page_assets[target], m.group("q"))
            return m.group(0)
        return SRC_RE.sub(sub, html)

    # -- build -------------------------------------------------------------------------
    def build(self):
        included, all_assets = self.inventory()
        asset_by_rel = {p.relative_to(self.folder).as_posix(): p for p in all_assets}

        # Excluded markdown must not be linkable either: a live link to the mentor guide
        # would hand a learner the scoring notes in one click. Rendered as plain text.
        excluded_names = {p.name for p in sorted(self.folder.glob("*.md"))
                          if disclosure.excluded_md(p.name, self.view)}

        module_ids, owners = {}, {}
        for p in included:
            key = module_key(p.name)
            if key in owners:
                raise SystemExit(
                    "%s: %s and %s would share the Moodle identity %s -- give one of "
                    "them its own number" % (self.slug, owners[key], p.name,
                                             idnumber(self.slug, key)))
            owners[key] = p.name
            module_ids[p.name] = idnumber(self.slug, key)

        lessons = [p for p in included if is_lesson(p.name)]
        others = [p for p in included if not is_lesson(p.name)]

        sections, pages, quizzes, used_assets = [], {}, [], {}
        for n, p in enumerate(lessons, start=1):
            mod, html = self._page(p, asset_by_rel, module_ids, excluded_names, used_assets)
            raw = p.read_text(encoding="utf-8", errors="replace")
            sections.append({
                "number": n,
                "name": title_of(raw, p.stem),
                "minutes": minutes_of(raw),
                "modules": [mod],
            })
            pages[mod["html_file"]] = html

        n = len(lessons)
        for p in others:
            n += 1
            raw = p.read_text(encoding="utf-8", errors="replace")
            if p.name.endswith("-quiz.md"):
                quiz_mods, quiz_pages, quiz_defs = self._quiz(p, raw, n)
                sections.append({"number": n, "name": title_of(raw, p.stem),
                                 "minutes": minutes_of(raw), "modules": quiz_mods})
                pages.update(quiz_pages)
                quizzes.extend(quiz_defs)
                continue
            mod, html = self._page(p, asset_by_rel, module_ids, excluded_names, used_assets)
            sections.append({"number": n, "name": title_of(raw, p.stem),
                             "minutes": minutes_of(raw), "modules": [mod]})
            pages[mod["html_file"]] = html

        readme = self.folder / "README.md"
        meta, summary_md = ({}, "")
        if readme.exists():
            meta, body = split_frontmatter(readme.read_text(encoding="utf-8",
                                                            errors="replace"))
            summary_md = body.split("\n## ", 1)[0]
            summary_md = H1_RE.sub("", summary_md, count=1).strip()

        # A course with no lesson files is a backfill placeholder, not content: its whole
        # body is a single run-on README paragraph imported from Notion, with hotlinked
        # images that will rot. Publishing that to Moodle would ship garbage under a real
        # course name. Refuse, and point at the workstream that fixes it -- 16 of the 31
        # courses are in this state today. See BACKFILL.md and process/backfill.md.
        blocked = None
        if not lessons:
            blocked = ("no lesson files (NN-*.md) -- this course's content is still only "
                       "its README. Backfill it first: see BACKFILL.md")

        delivered, records = self._deliver(used_assets)
        weight = {"source_bytes": sum(r["source_bytes"] for r in records.values()),
                  "delivered_bytes": sum(r["bytes"] for r in records.values()),
                  "by_treatment": {}}
        for r in records.values():
            weight["by_treatment"][r["treatment"]] =                 weight["by_treatment"].get(r["treatment"], 0) + 1
        self._page_budget(sections, pages, records)
        self._check_identities(sections, quizzes)

        manifest = {
            "slug": self.url_slug,
            "folder": self.slug,
            "view": self.view,
            "publishable": blocked is None,
            "blocked_reason": blocked,
            "idnumber": "ltct:%s" % self.url_slug,
            "title": meta.get("title") or self.slug,
            "summary_html": render(summary_md) if summary_md else "",
            # Course metadata, verbatim from the README frontmatter (spec 004, R11): the
            # publisher writes both to course fields and the competencies to the
            # per-competency table. check_moodle_payload.py refuses a name that is not in
            # competencies.yaml.
            "target_outcome_level": meta.get("target_outcome_level"),
            "competencies": list(meta.get("competencies") or []),
            # The course is complete when every module is (completion_for()).
            "completion": "all",
            "content_type": meta.get("content_type"),
            "sections": sections,
            "quizzes": quizzes,
            "withheld": self.withheld,
            "notes": self.notes,
            "assets": records,
            "image_weight": weight,
        }
        return manifest, pages, delivered

    def _check_identities(self, sections, quizzes):
        """Every idnumber must fit the column Moodle stores it in.

        course, course_modules and question all hold idnumber in a VARCHAR(100). An
        overlong one is not truncated: the insert fails, part-way through a publish. Refuse
        here instead, before anything is sent.
        """
        ids = ["ltct:%s" % self.url_slug]
        ids += [m["idnumber"] for s in sections for m in s["modules"]]
        ids += [q["idnumber"] for quiz in quizzes for q in quiz["questions"]]
        for i in ids:
            if len(i) > IDNUMBER_MAX:
                raise SystemExit("%s: Moodle identity %s is %d characters; Moodle stores at "
                                 "most %d" % (self.slug, i, len(i), IDNUMBER_MAX))

    def _deliver(self, used_assets):
        """The lighter copy of every used asset, and the record that traces it.

        Runs only on the set the inventory already selected, so reduction can never change
        what is published or withheld (FR-009). Every image is decoded before anything is
        written: a corrupt one stops the build here, naming the file.
        """
        delivered, records = {}, {}
        for name, src in sorted(used_assets.items()):
            committed = src.read_bytes()
            rel = src.relative_to(self.folder)
            try:
                d = image_reduce.deliver(src, suffixes=rel.parts[0] == "assets")
            except image_reduce.ImageError as e:
                raise SystemExit("cannot read image %s/%s: %s"
                                 % (self.slug, rel.as_posix(), e.reason))
            if d.note:
                self.notes.append(d.note)
            delivered[name] = d.data
            records[name] = {
                "source": rel.as_posix(),
                "source_sha256": hashlib.sha256(committed).hexdigest(),
                "source_bytes": len(committed),
                "sha256": hashlib.sha256(d.data).hexdigest(),
                # Equal to Moodle's contenthash, which is what lets a republish skip an
                # unchanged image. Plain data here: nothing else about it is Moodle's.
                "sha1": hashlib.sha1(d.data).hexdigest(),
                "bytes": len(d.data),
                "treatment": d.treatment,
            }
        return delivered, records

    def _page_budget(self, sections, pages, records):
        for section in sections:
            for mod in section["modules"]:
                if mod["kind"] != "page":
                    continue
                total = len(pages[mod["html_file"]].encode("utf-8")) + sum(
                    records[a]["bytes"] for a in mod["assets"] if a in records)
                if total > PAGE_BUDGET:
                    self.notes.append("%s: %d KB with images exceeds the 1 MB page budget"
                                      % (mod["source"], round(total / 1024)))

    def _page(self, path, asset_by_rel, module_ids, excluded_names, used_assets):
        raw = path.read_text(encoding="utf-8", errors="replace")
        _, body = split_frontmatter(raw)

        # Every learner page is strip-checked, not only the quizzes. A scenario bank or a
        # job aid can grow an answer key without anyone reclassifying the file, and the
        # cost of checking is nil. Fails closed, the same way the learner view does.
        if self.view == "learner":
            body, ok = disclosure.strip_answer_keys(body)
            if not ok:
                self.withheld.append(path.name)
                self.notes.append(
                    "%s: withheld -- an answer key could not be cleanly separated"
                    % path.name)
                return {
                    "kind": "page",
                    "idnumber": idnumber(self.slug, module_key(path.name)),
                    "name": title_of(raw, path.stem),
                    "source": path.name,
                    "html_file": "pages/%s.html" % path.stem,
                    "assets": [],
                    "completion": completion_for("page", None),
                }, wrap(WITHHELD_HTML)

        for m in VIDEO_RE.finditer(body):
            if PENDING_VIDEO_RE.search(m.group(1)):
                self.notes.append("%s: video not recorded yet" % path.name)

        html = render(body)
        page_assets = {}
        for rel, src in asset_by_rel.items():
            if rel in body or src.name in body:
                page_assets[rel] = src.name
                used_assets.setdefault(src.name, src)
        html = self.rewrite(html, page_assets, module_ids)
        html = self._deaden(html, excluded_names)

        return {
            "kind": "page",
            "idnumber": idnumber(self.slug, module_key(path.name)),
            "name": title_of(raw, path.stem),
            "source": path.name,
            "html_file": "pages/%s.html" % path.stem,
            "assets": sorted(page_assets.values()),
            "completion": completion_for("page", None),
        }, wrap(html)

    def _deaden(self, html, excluded_names):
        """A link to a view-excluded file becomes plain text, never a live link."""
        if not excluded_names:
            return html

        def sub(m):
            name = unquote(urlsplit(m.group("url")).path).rsplit("/", 1)[-1]
            if name in excluded_names:
                return "%s%s#@@WITHHELD@@%s" % (m.group("attr"), m.group("q"), m.group("q"))
            return m.group(0)
        return SRC_RE.sub(sub, html)

    def _quiz(self, path, raw, section):
        """A quiz becomes question data plus, for the reviewer view, a readable page."""
        mods, pages, defs = [], {}, []
        try:
            parsed = parse_quiz_file(path)
        except QuizError as e:
            # Fail closed, exactly as the learner view does: no guessed quiz.
            self.withheld.append(path.name)
            self.notes.append("quiz withheld: %s" % e)
            mods.append({"kind": "page", "idnumber": idnumber(self.slug, module_key(path.name)),
                         "name": title_of(raw, path.stem), "source": path.name,
                         "html_file": "pages/%s.html" % path.stem, "assets": [],
                         "completion": completion_for("page", None)})
            pages["pages/%s.html" % path.stem] = wrap(WITHHELD_HTML)
            return mods, pages, defs

        for i, quiz in enumerate(parsed, start=1):
            suffix = ("-%d" % i) if len(parsed) > 1 else ""
            name = quiz["title"]
            if quiz["qualifier"]:
                name = "%s %s" % (name, quiz["qualifier"])
            completion = completion_for("quiz", quiz["threshold_pct"])
            defs.append({
                "idnumber": idnumber(self.slug, module_key(path.name) + suffix),
                "name": name,
                "source": path.name,
                "section": section,
                "threshold_pct": quiz["threshold_pct"],
                "completion": completion,
                "category": "%s / %s" % (self.url_slug, path.stem + suffix),
                "questions": [self._question(q, path.name, i, n)
                              for n, q in enumerate(quiz["questions"], start=1)],
            })
            mods.append({"kind": "quiz",
                         "idnumber": idnumber(self.slug, module_key(path.name) + suffix),
                         "name": name, "source": path.name, "completion": completion})
        return mods, pages, defs

    def _question(self, q, source, quiz_index, ordinal):
        return {
            "idnumber": idnumber(self.slug,
                                 "%s:q%d.%d" % (module_key(source), quiz_index, ordinal)),
            "name": "Q%d" % q["number"],
            "text_html": render(q["text"]),
            "single": q["single"],
            "feedback_html": render(q["feedback"]) if q["feedback"] else "",
            "section": q["section"],
            "answers": [{"letter": o["letter"], "text_html": render(o["text"]),
                         "correct": o["correct"]} for o in q["options"]],
        }


def refuse_inside_repo(out_dir):
    """Exit 2 if `out_dir` resolves inside the repository, before anything is written.

    Covers `--out` here and `--keep-payload` in publish_moodle.py, which both come through
    write_payload(). resolve() handles `..`, symlinks and a drive-letter case mismatch.
    """
    resolved = pathlib.Path(out_dir).resolve()
    if resolved.is_relative_to(REPO.resolve()):
        print("refusing to write the payload inside the repository (%s); choose a folder "
              "outside it, or omit --keep-payload to use a temp folder" % resolved,
              file=sys.stderr)
        raise SystemExit(2)


def write_payload(manifest, pages, assets, out_dir):
    """Write the payload. `assets` maps each delivered name to its delivered bytes."""
    refuse_inside_repo(out_dir)
    out = pathlib.Path(out_dir) / manifest["slug"]
    if out.exists():
        shutil.rmtree(out)
    (out / "pages").mkdir(parents=True)
    for rel, html in pages.items():
        (out / rel).write_text(html, encoding="utf-8", newline="\n")
    if assets:
        (out / "assets").mkdir(exist_ok=True)
        for name, data in assets.items():
            (out / "assets" / name).write_bytes(data)
    (out / "manifest.json").write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8", newline="\n")
    return out


def report_images(manifest):
    """The weight report (FR-010): totals, then every image not given the standard cut."""
    w = manifest.get("image_weight")
    if not w or not manifest.get("assets"):
        return
    src_kb, out_kb = round(w["source_bytes"] / 1024), round(w["delivered_bytes"] / 1024)
    saved = 100 - round(100 * w["delivered_bytes"] / w["source_bytes"])         if w["source_bytes"] else 0
    print("  images    %d: %d KB -> %d KB (%d%% lighter)"
          % (len(manifest["assets"]), src_kb, out_kb, saved))
    for name, rec in sorted(manifest["assets"].items()):
        if rec["treatment"] in ("full", "small", "unchanged"):
            print("            %-10s %s%s" % (
                rec["treatment"], name,
                " (already smaller than any reduction)"
                if rec["treatment"] == "unchanged" else ""))


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--slug", required=True, help="course folder name under modules/")
    ap.add_argument("--view", default="learner", choices=("learner", "reviewer"),
                    help="learner holds back the design doc, mentor guide, video "
                         "scripts and every answer key (default)")
    ap.add_argument("--out", required=True, help="payload directory to write")
    args = ap.parse_args()

    folder = MODULES / args.slug
    if not folder.is_dir():
        matches = [d for d in MODULES.iterdir()
                   if d.is_dir() and branch_slug(d.name) == args.slug]
        if not matches:
            sys.exit("no such course: %s" % args.slug)
        folder = matches[0]

    manifest, pages, assets = Payload(folder, args.view).build()
    out = write_payload(manifest, pages, assets, args.out)

    n_mods = sum(len(s["modules"]) for s in manifest["sections"])
    n_q = sum(len(q["questions"]) for q in manifest["quizzes"])
    print("%s -> %s" % (folder.name, out))
    print("  view      %s" % manifest["view"])
    print("  sections  %d (%d module(s))" % (len(manifest["sections"]), n_mods))
    print("  quizzes   %d (%d question(s))" % (len(manifest["quizzes"]), n_q))
    print("  assets    %d" % len(assets))
    report_images(manifest)
    for note in manifest["notes"]:
        print("  note      %s" % note)
    if manifest["withheld"]:
        print("  WITHHELD  %s" % ", ".join(manifest["withheld"]))
    if not manifest["publishable"]:
        print("  NOT PUBLISHABLE: %s" % manifest["blocked_reason"])
    return 0


if __name__ == "__main__":
    sys.exit(main())
