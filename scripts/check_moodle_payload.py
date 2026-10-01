#!/usr/bin/env python3
"""Prove a publish payload leaks nothing a learner shouldn't see, before it is pushed.

This is the Moodle half of the disclosure boundary, and it is deliberately the same
design as scripts/check_learner_view.py: read back what is actually about to ship and
verify it, rather than trusting that the builder did the right thing. The difference is
only where "what ships" lives -- there it is built HTML on disk, here it is the payload
scripts/moodle_payload.py wrote. scripts/publish_moodle.py refuses to push a payload that
has not passed this.

Checking the payload rather than Moodle afterwards is the point: once a page is on a
server that learners can reach, a leak has already happened. There is no equivalent of
"rebuild the site" for disclosure.

Five checks, all fail-closed:

  1. Excluded sources  -- no page derives from the design doc, a mentor guide or a video
                          script, and no asset derives from one either.
  2. Answer keys       -- for every quiz, the key block is lifted from the SOURCE markdown
                          and each distinctive line is searched for in every page's HTML.
                          Far stricter than grepping for the words "answer key": a lesson
                          may legitimately say "Expected resolution (answer key)" about
                          its own worked exercise, and a real key may be published under a
                          heading we failed to recognise. Only the key's own text decides.
  3. Answer placement  -- correct answers exist ONLY in question data, never in page HTML.
                          This is the one real difference from the static learner view:
                          keys do reach Moodle, but as the `correct` flag inside a
                          question, where Moodle's capabilities protect them.
  4. Withheld pages    -- anything the builder withheld must be the placeholder and
                          nothing else, and is reported, because a withheld quiz leaves a
                          learner with no assessment at all.
  5. Asset provenance  -- every delivered image traces to a committed, non-excluded source.
                          The payload carries the LIGHTER copy scripts/image_reduce.py
                          makes, not the committed file, so "it was copied from the repo"
                          no longer proves anything. Each file has a record and each record
                          a file; both sides are re-hashed against the record; nothing is
                          heavier than its source; and a file delivered unreduced must be
                          byte-identical to what is committed.

Usage:
  python scripts/check_moodle_payload.py --payload <dir> --slug <slug>
  python scripts/check_moodle_payload.py --payload <dir> --all

Exit 1 on any leak, naming the file and the leaked text. Exit 2 on misuse.
"""
import argparse
import hashlib
import html
import json
import pathlib
import re
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
import disclosure  # noqa: E402
from course_stage import branch_slug  # noqa: E402

REPO = pathlib.Path(__file__).resolve().parent.parent
MODULES = REPO / "modules"

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

# An answer-key line worth asserting on: long enough to be distinctive, and not a bare
# heading or separator that would appear innocently elsewhere. Matches the threshold
# check_learner_view.py uses, for the same reason.
MIN_SIGNIFICANT = 25
WITHHELD_MARK = "quiz withheld"


def normalise(text):
    """Collapse to comparable plain text: strip tags, unescape entities, squash space."""
    text = re.sub(r"<[^>]+>", " ", text)
    return re.sub(r"\s+", " ", html.unescape(text)).strip().lower()


def source_folder(manifest):
    folder = MODULES / manifest["folder"]
    if folder.is_dir():
        return folder
    for d in MODULES.iterdir():
        if d.is_dir() and branch_slug(d.name) == manifest["slug"]:
            return d
    return None


def check(payload_dir):
    """Returns (problems, warnings) for one course payload."""
    payload_dir = pathlib.Path(payload_dir)
    manifest = json.loads((payload_dir / "manifest.json")
                          .read_text(encoding="utf-8", errors="replace"))
    problems, warnings = [], []
    slug = manifest["slug"]

    if manifest["view"] != "learner":
        warnings.append("%s: payload is the '%s' view -- this check only certifies the "
                        "learner view" % (slug, manifest["view"]))
        return problems, warnings

    folder = source_folder(manifest)
    if folder is None:
        problems.append("%s: cannot find the source course folder" % slug)
        return problems, warnings

    pages = sorted((payload_dir / "pages").glob("*.html")) \
        if (payload_dir / "pages").is_dir() else []
    page_text = {p.name: p.read_text(encoding="utf-8", errors="replace") for p in pages}
    haystack = {name: normalise(text) for name, text in page_text.items()}

    # --- 1. excluded sources ------------------------------------------------------------
    published_sources = {m["source"] for s in manifest["sections"] for m in s["modules"]}
    for name in sorted(published_sources):
        if disclosure.excluded_md(name, "learner"):
            problems.append("%s: published a module from excluded source %s" % (slug, name))
    assets_dir = payload_dir / "assets"
    if assets_dir.is_dir():
        for a in sorted(assets_dir.iterdir()):
            if disclosure.excluded_asset(a.name, "learner"):
                problems.append("%s: published excluded asset %s" % (slug, a.name))

    # --- 2. answer-key text in any page -------------------------------------------------
    # Assert only on lines the key does NOT share with legitimate learner content. A
    # course may well repeat a sentence in its scenario bank and again in a key rationale
    # ("zero configuration errors does not always mean zero results"); finding it in a
    # published page then proves nothing, and asserting on it would train people to
    # ignore this check. Built from every publishable source, key blocks removed --
    # the same construction check_learner_view.py uses, for the same reason.
    legit = normalise("\n".join(
        disclosure.strip_key_blocks(p.read_text(encoding="utf-8", errors="replace"))
        for p in sorted(folder.glob("*.md"))
        if not disclosure.excluded_md(p.name, "learner")))

    for quiz_src in sorted(p for p in folder.glob("*.md") if "quiz" in p.name.lower()):
        md = quiz_src.read_text(encoding="utf-8", errors="replace")
        for block in disclosure.answer_key_blocks(md):
            for line in block:
                needle = normalise(line)
                if len(needle) < MIN_SIGNIFICANT or needle in legit:
                    continue
                for name, hay in haystack.items():
                    if needle in hay:
                        problems.append("%s: answer key from %s leaked into pages/%s: %r"
                                        % (slug, quiz_src.name, name, line.strip()[:80]))

    # --- 3. correct answers live only in question data ----------------------------------
    for quiz in manifest["quizzes"]:
        for q in quiz["questions"]:
            correct = [a for a in q["answers"] if a["correct"]]
            if not correct:
                problems.append("%s: %s %s has no correct answer"
                                % (slug, quiz["name"], q["name"]))
            if q["single"] and len(correct) != 1:
                problems.append("%s: %s %s is single-answer but has %d correct"
                                % (slug, quiz["name"], q["name"], len(correct)))
            # No check on the answer TEXT: it is usually a phrase the lesson taught, so it
            # appears in a page legitimately. What matters is that the `correct` flag
            # lives only here, in question data Moodle protects by capability -- which
            # check 2 establishes by proving the key's own text is absent from the pages.

    # --- 4. withheld -------------------------------------------------------------------
    for name in manifest["withheld"]:
        stem = pathlib.Path(name).stem
        page = page_text.get("%s.html" % stem)
        if page is not None and WITHHELD_MARK not in page.lower():
            problems.append("%s: %s was withheld but its page is not the placeholder"
                            % (slug, name))
        warnings.append("%s: %s was WITHHELD -- learners get no assessment from it"
                        % (slug, name))

    # --- 5. asset provenance --------------------------------------------------------------
    problems += check_assets(slug, folder, payload_dir, manifest)

    if not manifest["publishable"]:
        warnings.append("%s: not publishable -- %s" % (slug, manifest["blocked_reason"]))

    return problems, warnings


# Delivered as the committed bytes, so they must BE the committed bytes.
UNREDUCED = ("full", "unchanged", "vector", "passthrough")


def check_assets(slug, folder, payload_dir, manifest):
    """Check 5. Every byte in assets/ traces to a committed file a learner may see."""
    problems = []
    records = manifest.get("assets") or {}
    assets_dir = payload_dir / "assets"
    files = {a.name: a for a in assets_dir.iterdir()} if assets_dir.is_dir() else {}

    for name in sorted(set(files) - set(records)):
        problems.append("%s: assets/%s has no record in manifest.json -- its origin cannot "
                        "be traced" % (slug, name))
    for name in sorted(set(records) - set(files)):
        problems.append("%s: manifest.json records assets/%s but the file is missing"
                        % (slug, name))

    root = folder.resolve()
    for name in sorted(set(records) & set(files)):
        rec = records[name]
        where = "%s: assets/%s" % (slug, name)
        source = (folder / rec.get("source", "")).resolve()
        if not source.is_relative_to(root) or not source.is_file():
            problems.append("%s: source %r is not a file in the course folder"
                            % (where, rec.get("source")))
            continue
        for n in {source.name, name}:
            if disclosure.excluded_asset(n, "learner"):
                problems.append("%s: derives from excluded asset %s" % (where, n))

        committed = source.read_bytes()
        data = files[name].read_bytes()
        if hashlib.sha256(committed).hexdigest() != rec.get("source_sha256"):
            problems.append("%s: committed source %s has changed since the payload was "
                            "built -- rebuild it" % (where, rec["source"]))
        if hashlib.sha256(data).hexdigest() != rec.get("sha256")                 or len(data) != rec.get("bytes"):
            problems.append("%s: delivered file does not match its record" % where)
        if not isinstance(rec.get("bytes"), int)                 or rec["bytes"] > rec.get("source_bytes", -1):
            problems.append("%s: delivered copy is heavier than its source" % where)
        if rec.get("treatment") in UNREDUCED and data != committed:
            problems.append("%s: treatment %r must deliver the committed bytes unchanged"
                            % (where, rec["treatment"]))
    return problems


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--payload", required=True, help="payload directory")
    g = ap.add_mutually_exclusive_group(required=True)
    g.add_argument("--slug", help="one course payload under <payload>/<slug>/")
    g.add_argument("--all", action="store_true", help="every course payload in <payload>")
    args = ap.parse_args()

    root = pathlib.Path(args.payload)
    if not root.is_dir():
        print("no such payload directory: %s" % root, file=sys.stderr)
        return 2
    if args.all:
        targets = sorted(d for d in root.iterdir() if (d / "manifest.json").exists())
    else:
        targets = [root / args.slug]
        if not (targets[0] / "manifest.json").exists():
            targets = [root] if (root / "manifest.json").exists() else []
        if not targets:
            print("no manifest.json under %s" % root, file=sys.stderr)
            return 2

    problems, warnings = [], []
    for t in targets:
        p, w = check(t)
        problems += p
        warnings += w

    for w in warnings:
        print("WARNING %s" % w)
    for p in problems:
        print("LEAK    %s" % p)

    if problems:
        print("\n%d course(s) checked: %d problem(s). Nothing may be published."
              % (len(targets), len(problems)))
        return 1
    print("\n%d course payload(s) clean: no excluded sources, no answer-key text in any "
          "page, every question keyed." % len(targets))
    return 0


if __name__ == "__main__":
    sys.exit(main())
