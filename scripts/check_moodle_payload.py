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

Five disclosure checks, all fail-closed:

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

And six structural checks, on every view, because a publish without them is wrong in
Moodle even when it leaks nothing (spec 004 onwards):

  6. Completion        -- every module carries a completion rule (view, submit or pass)
                          and the course's is "all". A missing one would leave a module
                          out of the course's completion, so it is refused, never given a
                          default.
  7. Competencies      -- every manifest competency is a name in competencies.yaml,
                          verbatim. A near-miss would put the course against no competency
                          in Moodle's per-competency table.
  8. Recognition       -- spec 013: the course's rendered badge name and description pass
                          cbc_wording.check_recognition(), since a title is free text and
                          reaches the badge; and the certificate's idnumber fits Moodle's column and is no
                          lesson's.
  9. Placement         -- spec 002 R11: the course says whether it is organisation-only,
                          and an organisation-only course names its organisation's
                          category, ltct:org:<key>, and nothing else. A malformed one would
                          leave an organisation-only course in a shared category, open to
                          everyone, so it is refused. A shared course names ltct:pilots on a
                          pilot publish and ltct:published on a delivery (issue #108).
 10. Target level      -- spec 006: a target_outcome_level that is present is one of
                          outcome-levels.yaml's course_target_levels labels, verbatim. The
                          publisher sends its leading digit as the course's pathway level,
                          so a label it cannot read a digit from must never reach Moodle.
 11. Section summaries -- spec 007: every section carries time_text, and it is "" or the
                          lesson's **Estimated time:** line rendered on its own: one
                          paragraph, no newline, no markup but the header's. The publisher
                          sends it as the section summary, outside the page, so nothing else
                          from the lesson may ride along with it.

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

import yaml

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
import _levels  # noqa: E402
import cbc_wording  # noqa: E402
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

COMPETENCIES = REPO / "competencies.yaml"
MODULE_COMPLETION = ("view", "submit", "pass")     # moodle_payload.completion_for()
COURSE_COMPLETION = "all"
SITE = REPO / "moodle" / "site"
IDNUMBER_MAX = 100                                 # course_modules.idnumber
PLACEHOLDER = re.compile(r"\{([^{}]*)\}")
# An organisation's category (spec 002 R11); the key as site_config.KEY allows it.
ORG_CATEGORY = re.compile(r"^ltct:org:[a-z][a-z0-9-]*$")
# A section's time_text (spec 007), as moodle_payload.time_text_of() renders the header line:
# trailing plain text and entities allowed, a second element never. Its own pattern, because
# this gate imports neither moodle_payload nor markdown.
TIME_TEXT = re.compile(r"^<p><strong>Estimated time:</strong>\s*\d+\s*minutes[^<>\n]*</p>$")


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

    # --- 6 to 11. structural, on every view ----------------------------------------------
    problems += check_completion(slug, manifest)
    problems += check_competencies(slug, manifest)
    problems += check_recognition(slug, manifest)
    problems += check_placement(slug, manifest)
    problems += check_target_level(slug, manifest)
    problems += check_section_summaries(slug, manifest)

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


def check_placement(slug, manifest):
    """Check 9. {org_only, category_idnumber}, both present and consistent (spec 002 R11)."""
    placement = manifest.get("placement")
    if not isinstance(placement, dict) or set(placement) != {"org_only", "category_idnumber"}:
        return ["%s: placement must be exactly {org_only, category_idnumber}, not %r"
                % (slug, placement)]
    org_only, category = placement["org_only"], placement["category_idnumber"]
    if not isinstance(org_only, bool):
        return ["%s: placement.org_only must be true or false, not %r" % (slug, org_only)]
    if org_only and not (isinstance(category, str) and ORG_CATEGORY.match(category)):
        return ["%s: an organisation-only course's placement.category_idnumber must be "
                "ltct:org:<key>, not %r" % (slug, category)]
    if not org_only:
        # Pilots until the delivery publish, Published from it (issue #108), so a shared
        # course is never left in Pilots, where no organisation can be enrolled.
        delivery = (manifest.get("recognition") or {}).get("delivery") is True
        expected = "ltct:published" if delivery else "ltct:pilots"
        if category != expected:
            return ["%s: a shared course's placement.category_idnumber must be %s on a %s "
                    "publish, not %r" % (slug, expected, "delivery" if delivery else "pilot",
                                         category)]
    return []


def check_section_summaries(slug, manifest):
    """Check 11. Every section carries time_text: "" or the Estimated time line alone.

    A missing key is refused, never defaulted: a builder that forgot it would publish with
    every summary left as it was.
    """
    problems = []
    for section in manifest.get("sections") or []:
        text = section.get("time_text")
        # fullmatch, since $ also matches before a trailing newline; \s* could span one.
        ok = isinstance(text, str) and (text == "" or (
            "\n" not in text and "\r" not in text and TIME_TEXT.fullmatch(text)))
        if not ok:
            problems.append("%s: section %d time_text is not the Estimated time line"
                            % (slug, section.get("number", 0)))
    return problems


def check_completion(slug, manifest):
    """Check 6. Every module, and every quiz definition, names one of the three rules."""
    problems = []
    if manifest.get("completion") != COURSE_COMPLETION:
        problems.append("%s: course completion is %r; it must be %r"
                        % (slug, manifest.get("completion"), COURSE_COMPLETION))
    items = [m for s in manifest.get("sections", []) for m in s.get("modules", [])]
    items += manifest.get("quizzes", [])
    for item in items:
        value = item.get("completion")
        if value not in MODULE_COMPLETION:
            problems.append("%s: %s has completion %r; it must be one of %s"
                            % (slug, item.get("idnumber", "?"), value,
                               ", ".join(MODULE_COMPLETION)))
    return problems


def framework_names():
    """Every competency name in competencies.yaml, across all its categories."""
    cats = yaml.safe_load(COMPETENCIES.read_text(encoding="utf-8")) or {}
    return {n for names in cats.values() for n in (names or [])}


def check_competencies(slug, manifest):
    """Check 7. Every manifest competency is in the framework, exactly."""
    names = manifest.get("competencies") or []
    if not isinstance(names, list):
        return ["%s: competencies must be a list of names, not %r" % (slug, names)]
    known = framework_names()
    return ["%s: competency %r is not in competencies.yaml -- copy the name verbatim"
            % (slug, n) for n in names if n not in known]


# Delivered as the committed bytes, so they must BE the committed bytes.
UNREDUCED = ("full", "unchanged", "vector", "passthrough")


def badge_template(site=SITE):
    """The declared badge name and description with {programme} filled, or None."""
    path = site / "badges.yaml"
    if not path.exists():
        return None
    data = yaml.safe_load(path.read_text(encoding="utf-8")) or {}
    programme = ""
    settings = site / "settings" / "badges.yaml"
    if settings.exists():
        for entry in (yaml.safe_load(settings.read_text(encoding="utf-8")) or {}).get(
                "settings") or []:
            if entry.get("name") == "badges_defaultissuername":
                programme = str(entry.get("value") or "")
    return {k: str(data.get(k) or "").replace("{programme}", programme)
            for k in ("name", "description")}


def check_recognition(slug, manifest, site=SITE):
    """Check 8. The badge text this course would get, and the certificate's identity."""
    problems = []
    recognition = manifest.get("recognition")
    if not isinstance(recognition, dict) or not isinstance(recognition.get("delivery"), bool):
        return ["%s: recognition must say whether this publish is a delivery" % slug]
    template = badge_template(site)
    if template is not None:
        values = {"course": str(manifest.get("title") or ""),
                  "competencies": ", ".join(manifest.get("competencies") or []),
                  "target_level": str(manifest.get("target_outcome_level") or "")}
        aims = "{target_level}" in template["description"]
        labels = cbc_wording.cbc_labels()
        if aims and recognition["delivery"] and values["target_level"] not in labels:
            problems.append("%s: target_outcome_level %r is not a CBC label, and the badge "
                            "prints it (FR-005)" % (slug, values["target_level"]))
        for key, text in template.items():
            rendered = PLACEHOLDER.sub(lambda m: values.get(m.group(1), m.group(0)), text)
            for message in cbc_wording.check_recognition(rendered,
                                                         require_completed=(key == "name")):
                problems.append("%s: badge %s: %s" % (slug, key, message))

    certificate = recognition.get("certificate")
    if certificate is not None and not recognition["delivery"]:
        problems.append("%s: a pilot publish carries no certificate" % slug)
    if certificate is not None:
        cid = str(certificate.get("idnumber") or "")
        if cid != "ltct:%s:certificate" % slug:
            problems.append("%s: certificate idnumber %r is not ltct:%s:certificate"
                            % (slug, cid, slug))
        if len(cid) > IDNUMBER_MAX:
            problems.append("%s: certificate idnumber %s is %d characters; Moodle stores at "
                            "most %d" % (slug, cid, len(cid), IDNUMBER_MAX))
        for section in manifest.get("sections") or []:
            for module in section.get("modules") or []:
                if module.get("idnumber") == cid:
                    problems.append("%s: module %s uses the certificate's idnumber %s"
                                    % (slug, module.get("source"), cid))
    elif recognition["delivery"]:
        problems.append("%s: a delivery publish must carry the certificate's idnumber" % slug)
    return problems


def check_target_level(slug, manifest):
    """Check 10. A present target_outcome_level is a course_target_levels label, verbatim."""
    level = manifest.get("target_outcome_level")
    if level is None:
        return []
    _, targets, _ = _levels.load()
    if level in targets:
        return []
    return ["%s: target_outcome_level %r is not one of outcome-levels.yaml's "
            "course_target_levels (%s) -- copy the label verbatim"
            % (slug, level, "; ".join(targets))]


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
          "page, every question keyed, every module's completion set, every competency "
          "in the framework, every section summary its estimated time." % len(targets))
    return 0


if __name__ == "__main__":
    sys.exit(main())
