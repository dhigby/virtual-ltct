#!/usr/bin/env python3
"""Publish one course to Moodle. Build, verify, then push -- never push unverified.

The order is the point:

    1. build the payload          scripts/moodle_payload.py
    2. VERIFY it                  scripts/check_moodle_payload.py
    3. only then talk to Moodle   scripts/moodle_client.py

Step 2 is not a formality. Once a page is on a server learners can reach, a disclosure
failure has already happened; there is no equivalent of "rebuild the site". So this
refuses to push a payload that has not passed, and --force does not exist.

IDEMPOTENT. Every module carries an idnumber derived from its source filename
("ltct:<slug>:01-lesson.md"), so a republish updates what exists and creates only what is
new. The identity lives in Moodle, not in a repo state file -- see
moodle/local_ltuse/README.md. Running this twice must leave the course exactly as running
it once did, and the end-to-end check for that is simply to run it twice.

WHAT IT DOES NOT DO. It never reads learner data, never syncs Moodle back to the repo,
and never deletes anything: a module the course no longer has is HIDDEN, not removed, so
a learner's completed attempt stays readable and a mistaken publish is recoverable.

Environment:
    MOODLE_URL, MOODLE_TOKEN    see scripts/moodle_client.py

Usage:
  python scripts/publish_moodle.py --slug <slug> [--category <name>] [--dry-run]
"""
import argparse
import json
import pathlib
import re
import subprocess
import sys
import tempfile

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from course_stage import branch_slug  # noqa: E402
from moodle_client import MoodleClient, MoodleError  # noqa: E402
from moodle_payload import MODULES, Payload, write_payload  # noqa: E402
from moodle_xml import quiz_xml  # noqa: E402

REPO = pathlib.Path(__file__).resolve().parent.parent
MODULE_TOKEN_RE = re.compile(r"@@MODULE:([^@]+)@@")
WITHHELD_TOKEN = "#@@WITHHELD@@"

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")


def resolve_folder(slug):
    folder = MODULES / slug
    if folder.is_dir():
        return folder
    for d in sorted(MODULES.iterdir()):
        if d.is_dir() and branch_slug(d.name) == slug:
            return d
    sys.exit("no such course: %s" % slug)


def verify(payload_root, slug):
    """Run the disclosure gate as a subprocess, so its exit code is the whole verdict."""
    result = subprocess.run(
        [sys.executable, str(REPO / "scripts" / "check_moodle_payload.py"),
         "--payload", str(payload_root), "--slug", slug],
        capture_output=True, encoding="utf-8", errors="replace")
    sys.stdout.write(result.stdout)
    if result.stderr.strip():
        sys.stderr.write(result.stderr)
    return result.returncode == 0


def ensure_sections(client, courseidnumber, count, names):
    """Make sure the course has `count` numbered sections, named to match the lessons.

    One call, to local_ltuse. This was originally three calls to local_wsmanagesections,
    which covers sections over REST and which the plan deliberately did not duplicate --
    but it could not be installed on this Moodle, so the plugin grew an update_sections
    function over core's own course_create_sections_if_missing() and
    course_update_section(). The publisher now depends on nothing but Moodle core and our
    own plugin.

    Idempotent: sections that already exist are renamed, never appended to.
    """
    return client.call(
        "local_ltuse_update_sections",
        courseidnumber=courseidnumber,
        numsections=count,
        sections=[{"number": n, "name": names.get(n, "")}
                  for n in sorted(names) if names.get(n)])


# Separate groups: each partner organisation is a group in a shared course, so a manager
# sees only their own people (spec 002, R3). Sent on update too, so a course published
# before spec 002 changes on its next publish. Not forced (groupmodeforce stays 0), so a
# forum can still run across organisations.
GROUPMODE_SEPARATE = 1


def ensure_course(client, manifest, category_id):
    """Find the course by idnumber, or create it. Returns the course id."""
    existing = client.course_by_idnumber(manifest["idnumber"])
    if existing:
        client.call("core_course_update_courses", courses=[{
            "id": existing["id"],
            "fullname": manifest["title"],
            "summary": manifest["summary_html"],
            "summaryformat": 1,
            "groupmode": GROUPMODE_SEPARATE,
        }])
        return int(existing["id"]), False

    created = client.call("core_course_create_courses", courses=[{
        "fullname": manifest["title"],
        "shortname": manifest["slug"],
        "idnumber": manifest["idnumber"],
        "categoryid": category_id,
        "summary": manifest["summary_html"],
        "summaryformat": 1,
        # Sections are the lessons, so a learner sees the course shape on one page.
        "format": "topics",
        "numsections": max(len(manifest["sections"]), 1),
        "visible": 0,          # created hidden; a human decides when learners see it
        "groupmode": GROUPMODE_SEPARATE,
    }])
    if client.dry_run:
        return 0, True
    return int(created[0]["id"]), True


def publish(client, payload_dir, category_id):
    manifest = json.loads((payload_dir / "manifest.json").read_text(encoding="utf-8"))
    if not manifest["publishable"]:
        raise SystemExit("refusing to publish %s: %s"
                         % (manifest["slug"], manifest["blocked_reason"]))

    courseid, created = ensure_course(client, manifest, category_id)
    print("  course    %s (%s)" % (manifest["idnumber"],
                                   "created, hidden" if created else "updated"))

    names = {s["number"]: s["name"] for s in manifest["sections"]}
    ensure_sections(client, manifest["idnumber"], len(manifest["sections"]),
                    names)
    print("  sections  %d" % len(manifest["sections"]))

    # --- pass 1: every page and quiz, with sibling links still tokenised ----------------
    cmids, page_bodies = {}, {}
    for section in manifest["sections"]:
        for mod in section["modules"]:
            if mod["kind"] != "page":
                continue
            html = (payload_dir / mod["html_file"]).read_text(encoding="utf-8")
            page_bodies[mod["idnumber"]] = (mod, section["number"], html)

            itemid = 0
            for asset in mod["assets"]:
                itemid = client.upload(payload_dir / "assets" / asset, itemid=itemid)

            result = client.call(
                "local_ltuse_create_page",
                courseidnumber=manifest["idnumber"], idnumber=mod["idnumber"],
                name=mod["name"], content=html, section=section["number"],
                contentitemid=itemid)
            if not client.dry_run:
                cmids[mod["idnumber"]] = result["cmid"]
            print("    page    %-46s %s" % (
                mod["source"],
                "dry-run" if client.dry_run
                else ("created" if result["created"] else "updated")))

    for quiz in manifest["quizzes"]:
        xml = quiz_xml(quiz)
        imported = client.call(
            "local_ltuse_import_questions",
            courseidnumber=manifest["idnumber"], category=quiz["category"], xml=xml)
        result = client.call(
            "local_ltuse_create_quiz",
            courseidnumber=manifest["idnumber"], idnumber=quiz["idnumber"],
            name=quiz["name"], category=quiz["category"], section=quiz["section"],
            thresholdpct=quiz["threshold_pct"] or 0)
        if not client.dry_run:
            cmids[quiz["idnumber"]] = result["cmid"]
        print("    quiz    %-46s %s question(s)" % (
            quiz["source"],
            len(quiz["questions"]) if client.dry_run else imported["count"]))

    # --- pass 2: resolve sibling links, now that every module has a cmid -----------------
    # Two passes because a lesson may link forward to one that does not exist yet. Only
    # pages that actually carry a link are rewritten, so on a course with none this costs
    # nothing.
    rewritten = 0
    for idnumber, (mod, sectionnum, html) in page_bodies.items():
        if "@@MODULE:" not in html:
            continue
        resolved = MODULE_TOKEN_RE.sub(
            lambda m: ("%s/mod/page/view.php?id=%s" % (client.url, cmids[m.group(1)]))
            if m.group(1) in cmids else "#", html)
        if resolved == html:
            continue
        client.call("local_ltuse_create_page",
                    courseidnumber=manifest["idnumber"], idnumber=idnumber,
                    name=mod["name"], content=resolved, section=sectionnum)
        rewritten += 1
    if rewritten:
        print("  links     %s in %d page(s)"
              % ("would be resolved" if client.dry_run else "resolved", rewritten))

    return manifest, cmids


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--slug", required=True, help="course folder name under modules/")
    ap.add_argument("--category", type=int, default=1,
                    help="Moodle course category id to create into (default 1, 'Misc')")
    ap.add_argument("--view", default="learner", choices=("learner", "reviewer"),
                    help="learner (default) holds back the design doc, mentor guide, "
                         "video scripts and every answer key")
    ap.add_argument("--dry-run", action="store_true",
                    help="build and verify, print what would be pushed, send nothing. "
                         "Every other script here is read-only or writes into the repo, "
                         "where git is the undo; this one writes to a server that has "
                         "none, so it gets a dry run.")
    ap.add_argument("--allow-withheld", action="store_true",
                    help="publish even though a quiz was withheld (learners get no "
                         "assessment from it)")
    ap.add_argument("--keep-payload", help="write the payload here instead of a temp dir")
    args = ap.parse_args()

    folder = resolve_folder(args.slug)
    slug = branch_slug(folder.name)

    out = pathlib.Path(args.keep_payload) if args.keep_payload \
        else pathlib.Path(tempfile.mkdtemp(prefix="ltct-payload-"))

    print("%s" % folder.name)
    manifest, pages, assets = Payload(folder, args.view).build()
    payload_dir = write_payload(manifest, pages, assets, out)
    print("  payload   %d page(s), %d asset(s), %d quiz(zes)"
          % (len(pages), len(assets), len(manifest["quizzes"])))

    if not verify(out, slug):
        return 1
    if not manifest["publishable"]:
        print("  NOT PUBLISHABLE: %s" % manifest["blocked_reason"])
        return 1

    # Withholding is the safe outcome, not a good one: it leaves the learner with no
    # assessment at all. check_learner_view.py treats it the same way under
    # --strict-withheld, and at stage 7 or 8 the author is right there to fix the marker.
    if manifest["withheld"] and not args.allow_withheld:
        print("  WITHHELD: %s" % ", ".join(manifest["withheld"]))
        print("\n  Refusing to publish a course whose quiz was withheld -- learners would\n"
              "  get no assessment. Fix the '## Answer key' marker (see\n"
              "  scripts/disclosure.py), or pass --allow-withheld if that is deliberate.")
        return 1

    client = MoodleClient(dry_run=args.dry_run)
    try:
        published, cmids = publish(client, payload_dir, args.category)
    except MoodleError as e:
        print("\nMoodle rejected the publish:\n  %s" % e, file=sys.stderr)
        return 1

    if args.dry_run:
        print("\nDRY RUN -- %d call(s) would have been made, nothing was sent."
              % len(client.calls))
        return 0

    print("\n%s published. Course URL:\n  %s/course/view.php?id=%s"
          % (published["title"], client.url,
             client.course_by_idnumber(published["idnumber"])["id"]))
    print("\nThe course is created HIDDEN. Make it visible in Moodle when you are ready "
          "for learners,\nthen record the URL in modules/%s/README.md under "
          "external_links: moodle:" % folder.name)
    return 0


if __name__ == "__main__":
    sys.exit(main())
