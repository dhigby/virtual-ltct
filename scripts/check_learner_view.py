"""Verify that a built learner-view review site leaks nothing a pilot learner shouldn't see.

The learner view (see scripts/gen_course_site.py) is a disclosure boundary: it hides the
design doc, the mentor guide, the video script, and every quiz answer key. This script is
the gate on that boundary. It runs against BUILT output, not source, because the only
thing that matters is what actually ships.

Three checks, all fail-closed:

  1. Excluded sources     -- no page or asset derived from an excluded file is present.
  2. Answer keys          -- for every quiz, the answer-key block is lifted from the
                             SOURCE markdown, and each distinctive answer line from it is
                             searched for in the built HTML and the search index. This is
                             far stricter than grepping for the words "answer key": it
                             catches a key that was published under a heading we failed
                             to recognise.
  3. Withheld pages       -- a quiz the generator could not strip must not be published
                             with content; it must be the placeholder. A withheld quiz is
                             also reported, because it leaves a pilot learner with no
                             quiz at all (--strict-withheld makes that a failure).

The source it compares against must be the same ref the pages were BUILT from. On the
deploy those differ: the checkout is pinned to main while each course is built from its
own branch, so pass --sources (written by build_review_sites.py --source-out) there.
Reading the checkout instead would both miss a leak on a branch and fail the publish over
a key that only main has.

Usage:
    python scripts/check_learner_view.py --built <dir>            # one course
    python scripts/check_learner_view.py --built <dir> --all      # <dir>/<slug>/ per course
    python scripts/check_learner_view.py --built <dir> --sources <dir>   # built from refs

Exit 1 on any leak, with the offending file and the leaked text.
"""
import argparse
import html
import json
import pathlib
import re
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from course_stage import branch_slug, course_folders  # noqa: E402

# The canonical marker, enforced at authoring time by check_course_package.py and
# stripped by gen_course_site.py. Deliberately kept in step with those two: if the marker
# changes, all three change together, and this check is what makes a mismatch loud.
KEY_RE = re.compile(r"^## Answer key\b.*$")
HEADING_RE = re.compile(r"^\s{0,3}(#{1,6})\s")
# The generator's withheld-quiz placeholder, as it reads once normalise() has run over
# the rendered page. Kept in step with gen_course_site.py's warning admonition.
WITHHELD_MARK = "quiz withheld from the learner view"
EXCLUDED_MD = ("-mentor-guide.md", "-video-script.md")
LMS_EXPORT_RE = re.compile(r"^(qti[_-]|cypher-)|\.imscc$", re.I)

# An answer-key line worth asserting on: long enough to be distinctive, and not a bare
# heading or separator that would appear innocently elsewhere.
MIN_SIGNIFICANT = 25


def answer_key_blocks(md):
    """Every answer-key block in a quiz source, as a list of line lists."""
    lines, blocks, i = md.split("\n"), [], 0
    while i < len(lines):
        if not KEY_RE.match(lines[i]):
            i += 1
            continue
        h = HEADING_RE.match(lines[i])
        level = len(h.group(1)) if h else None
        block, i = [lines[i]], i + 1
        while i < len(lines):
            nxt = HEADING_RE.match(lines[i])
            if nxt and (level is None or len(nxt.group(1)) <= level):
                break
            block.append(lines[i])
            i += 1
        blocks.append(block)
    return blocks


def strip_key_blocks(md):
    """The source with its answer-key blocks removed -- i.e. what may legitimately ship."""
    lines, out, i = md.split("\n"), [], 0
    while i < len(lines):
        if not KEY_RE.match(lines[i]):
            out.append(lines[i])
            i += 1
            continue
        i += 1
        while i < len(lines):
            nxt = HEADING_RE.match(lines[i])
            if nxt and len(nxt.group(1)) <= 2:
                break
            i += 1
    return "\n".join(out)


def normalise(text):
    """Collapse to comparable plain text: strip tags, unescape entities, squash space."""
    text = re.sub(r"<[^>]+>", " ", text)
    return re.sub(r"\s+", " ", html.unescape(text)).strip().lower()


def haystack_for(built):
    """All rendered text a learner could reach: every page, plus the search index."""
    parts = []
    for p in built.rglob("*.html"):
        parts.append(normalise(p.read_text(encoding="utf-8", errors="replace")))
    for p in built.rglob("search_index.json"):
        try:
            data = json.loads(p.read_text(encoding="utf-8", errors="replace"))
        except ValueError:
            continue
        for doc in data.get("docs", []):
            parts.append(normalise(doc.get("text", "") + " " + doc.get("title", "")))
    return "\n".join(parts)


def check_course(folder, built, problems, slug=None, strict_withheld=False):
    slug = slug or branch_slug(folder.name)
    if not built.is_dir():
        problems.append("%s: built output not found at %s" % (slug, built))
        return

    hay = haystack_for(built)
    present = {p.name.lower() for p in built.rglob("*")}

    # 1. excluded sources must not have been published at all
    for src in sorted(folder.iterdir()):
        if src.is_dir():
            continue
        n = src.name
        stem = n.rsplit(".", 1)[0].lower()
        excluded = (n == "00-design.md" or n.endswith(EXCLUDED_MD) or stem == "00-design"
                    or stem.endswith(("-mentor-guide", "-video-script", "-quiz"))
                    and not n.lower().endswith(".md")
                    or LMS_EXPORT_RE.search(n))
        if not excluded:
            continue
        page = re.sub(r"\.md$", "", n).lower()
        if n.lower() in present:
            problems.append("%s: excluded file published as an asset: %s" % (slug, n))
        elif n.lower().endswith(".md") and page in {d.name.lower()
                                                    for d in built.rglob("*") if d.is_dir()}:
            problems.append("%s: excluded file published as a page: %s" % (slug, n))

    # 2. no answer-key line may appear in the built output -- but only assert on lines
    #    the key does not SHARE with legitimate learner content. A course may well
    #    repeat a sentence in its scenario bank and again in the key ("zero
    #    configuration errors does not always mean zero results"); finding it in the
    #    built site then proves nothing, and asserting on it would train people to
    #    ignore this check.
    legit = normalise("\n".join(
        strip_key_blocks(p.read_text(encoding="utf-8", errors="replace"))
        for p in sorted(folder.glob("*.md"))
        if not (p.name == "00-design.md" or p.name.endswith(EXCLUDED_MD))))

    for quiz in sorted(folder.glob("*-quiz.md")):
        md = quiz.read_text(encoding="utf-8", errors="replace")
        for block in answer_key_blocks(md):
            for line in block:
                probe = normalise(line)
                if len(probe) < MIN_SIGNIFICANT or probe in legit:
                    continue
                if probe in hay:
                    problems.append("%s: ANSWER KEY LEAK from %s\n      leaked: %.120s"
                                    % (slug, quiz.name, probe))
                    break

    # 3. a quiz the generator could not strip must be the placeholder and nothing else.
    #    Withholding is the safe outcome but not a good one: the pilot learner is handed
    #    a course with no quiz. It happens when a quiz uses a marker other than the
    #    canonical "## Answer key" -- which check_course_package.py enforces on the repo,
    #    while the review site renders whatever ref it is pointed at. So this reports it,
    #    and --strict-withheld makes it fail, for the PR check where the author who wrote
    #    the marker is right there to fix it.
    for quiz in sorted(folder.glob("*-quiz.md")):
        page = built / re.sub(r"\.md$", "", quiz.name) / "index.html"
        if not page.is_file():
            continue
        rendered = normalise(page.read_text(encoding="utf-8", errors="replace"))
        if WITHHELD_MARK not in rendered:
            continue
        md = quiz.read_text(encoding="utf-8", errors="replace")
        leaked = [ln for ln in (normalise(x) for x in strip_key_blocks(md).split("\n"))
                  if len(ln) >= MIN_SIGNIFICANT and ln in rendered]
        if leaked:
            problems.append("%s: %s was withheld but its content was published anyway"
                            "\n      published: %.120s" % (slug, quiz.name, leaked[0]))
        elif strict_withheld:
            problems.append(
                "%s: %s is WITHHELD from the learner view -- its answer key does not use "
                "the canonical '## Answer key' marker, so a pilot learner gets no quiz."
                % (slug, quiz.name))
        else:
            print("::warning title=Quiz withheld::%s/%s is withheld from the learner view "
                  "(non-canonical answer-key marker); a pilot learner gets no quiz."
                  % (slug, quiz.name))


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--built", required=True,
                    help="built learner site (or the parent of per-course dirs with --all)")
    ap.add_argument("--all", action="store_true",
                    help="treat --built as a parent containing <slug>/ per course")
    ap.add_argument("--course", help="folder name under modules/ (single-course mode)")
    ap.add_argument("--sources",
                    help="read each course's source from <dir>/<slug>/ rather than from "
                         "modules/ in this checkout. Written by build_review_sites.py "
                         "--source-out, and REQUIRED whenever the sites were built from "
                         "git refs: the deploy builds each course from its own branch "
                         "while the checkout is pinned to main, so checking against the "
                         "checkout would check a different ref than the one that shipped.")
    ap.add_argument("--strict-withheld", action="store_true",
                    help="fail when a quiz is withheld from the learner view, rather than "
                         "warning. For the PR check: withholding is safe but leaves a "
                         "pilot learner with no quiz, and the author can fix the marker.")
    args = ap.parse_args()

    built = pathlib.Path(args.built).resolve()
    problems = []

    if args.sources:
        sources = pathlib.Path(args.sources).resolve()
        captured = sorted(d for d in sources.iterdir() if d.is_dir()) \
            if sources.is_dir() else []
        if not captured:
            print("check_learner_view: no captured sources under %s. Run "
                  "build_review_sites.py with --source-out." % sources, file=sys.stderr)
            return 2
        for folder in captured:
            check_course(folder, built / folder.name, problems, slug=folder.name,
                         strict_withheld=args.strict_withheld)
    elif args.all:
        for folder in course_folders():
            check_course(folder, built / branch_slug(folder.name), problems,
                         strict_withheld=args.strict_withheld)
    else:
        folders = [f for f in course_folders()
                   if not args.course or f.name == args.course
                   or branch_slug(f.name) == branch_slug(args.course)]
        if len(folders) != 1:
            print("check_learner_view: name exactly one course with --course", file=sys.stderr)
            return 2
        check_course(folders[0], built, problems,
                     strict_withheld=args.strict_withheld)

    if problems:
        print("LEARNER VIEW FAILED -- %d problem(s):\n" % len(problems), file=sys.stderr)
        for p in problems:
            print("  - %s" % p, file=sys.stderr)
        print("\nThe learner view is what a pilot learner is handed. A leak or a published "
              "exclusion is a bug in scripts/gen_course_site.py; a WITHHELD quiz is a "
              "non-canonical answer-key marker in the quiz itself -- make it "
              "'## Answer key', as check_course_package.py requires.", file=sys.stderr)
        return 1

    print("Learner view clean: no excluded sources, no answer-key text in any built page "
          "or search index.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
