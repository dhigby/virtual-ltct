"""Build or serve a browsable review site for ONE course.

Course content lives as markdown under modules/<slug>/. Reviewers (SME at stage 5,
internal reviewer at stage 6) and pilot learners (stage 7) should not have to read that
as a raw diff, so this renders it as a real site.

    python scripts/review_site.py --slug bloom                  # serve the reviewer view
    python scripts/review_site.py --slug bloom --view learner   # what a learner sees
    python scripts/review_site.py --slug bloom --build -d out   # build, don't serve
    python scripts/review_site.py --slug bloom --ref course/bloom   # a branch, not your files

By default the source is your WORKING TREE, so a course you are editing previews live --
that is what an author wants nearly every time. `--ref` instead checks the course out
into a throwaway git worktree under the system temp dir; your own files are never
touched or moved.

All the real rendering happens in scripts/gen_course_site.py, driven through
mkdocs-review.yml. This script only resolves the course, decides where the content
comes from, and sets the environment that config reads.
"""
import argparse
import os
import pathlib
import shutil
import subprocess
import sys
import tempfile
import webbrowser

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from course_stage import (REPO, branch_slug, course_folders,  # noqa: E402
                          git, resolve, stage_for)

# Windows consoles default to cp1252, which cannot encode the status glyphs below.
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, ValueError):
        pass

DEFAULT_PORT = 8001          # 8000 is where a competency-site `mkdocs serve` lands
CONFIG = "mkdocs-review.yml"


def die(msg, code=1):
    print(msg, file=sys.stderr)
    sys.exit(code)


def resolve_course(text):
    slug = resolve(text) if text else None
    if slug:
        return slug
    print("Unknown course: %r\n\nCourses in the pipeline:" % (text or ""), file=sys.stderr)
    for f in course_folders():
        print("  %-55s %s" % (branch_slug(f.name), f.name), file=sys.stderr)
    sys.exit(1)


def make_worktree(ref, slug):
    """Check `ref` out somewhere disposable. Returns (path, cleanup)."""
    if git("rev-parse", "--verify", "--quiet", ref) is None:
        die("No such git ref: %s\n"
            "Try `git fetch origin` first, or drop --ref to preview your own files." % ref)
    root = pathlib.Path(tempfile.gettempdir()) / "ltct-review" / slug
    if root.exists():
        subprocess.run(["git", "worktree", "remove", "--force", str(root)],
                       cwd=REPO, capture_output=True, text=True)
        shutil.rmtree(root, ignore_errors=True)
    root.parent.mkdir(parents=True, exist_ok=True)
    out = subprocess.run(["git", "worktree", "add", "--detach", str(root), ref],
                         cwd=REPO, capture_output=True, text=True)
    if out.returncode != 0:
        die("Could not check out %s:\n%s" % (ref, out.stderr.strip()))

    def cleanup():
        subprocess.run(["git", "worktree", "remove", "--force", str(root)],
                       cwd=REPO, capture_output=True, text=True)
        shutil.rmtree(root, ignore_errors=True)

    return root, cleanup


def find_course_dir(modules_root, slug):
    """Match by branch_slug, not by name: legacy folders have spaces and capitals."""
    if not modules_root.is_dir():
        return None
    want = branch_slug(slug)
    for f in sorted(modules_root.iterdir()):
        if f.is_dir() and branch_slug(f.name) == want:
            return f
    return None


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--slug", "--course", dest="slug", help="course slug or folder name")
    ap.add_argument("--view", choices=("reviewer", "learner"), default="reviewer",
                    help="reviewer = whole package (default); learner = no design doc, "
                         "mentor guide, video script or quiz answers")
    ap.add_argument("--ref", help="git ref to preview instead of your working tree")
    ap.add_argument("--build", action="store_true", help="build instead of serving")
    ap.add_argument("-d", "--dest", help="output directory for --build")
    ap.add_argument("--port", type=int, default=DEFAULT_PORT)
    ap.add_argument("--offline", action="store_true",
                    help="with --build: usable from file:// (no directory URLs)")
    ap.add_argument("--open", dest="open_browser", action="store_true")
    args = ap.parse_args()

    slug = resolve_course(args.slug or (git("rev-parse", "--abbrev-ref", "HEAD") or ""))
    folder = REPO / "modules" / slug

    cleanup = None
    if args.ref:
        wt, cleanup = make_worktree(args.ref, branch_slug(slug))
        src = find_course_dir(wt / "modules", slug)
        if src is None:
            cleanup()
            die("modules/%s does not exist on %s." % (slug, args.ref))
        ref_label = args.ref
    else:
        if not folder.is_dir():
            die("modules/%s not found." % slug)
        src = folder
        ref_label = "your working tree"

    env = dict(os.environ)
    env.update({
        "LTCT_COURSE_ROOT": str(src),
        "LTCT_COURSE_FOLDER": src.name,
        "LTCT_VIEW": args.view,
        "LTCT_COURSE_REF": args.ref or "main",
        "LTCT_EDIT_URI": "edit/%s/" % (args.ref or "main"),
        # No LTCT_REVIEW_BASE locally: sibling-course links then fall back to GitHub,
        # which works offline and never points at a URL that isn't deployed yet.
        "DISABLE_MKDOCS_2_WARNING": "true",
    })

    if args.build:
        dest = pathlib.Path(args.dest or (REPO / "site-review")).resolve()
        cmd = [sys.executable, "-m", "mkdocs", "build", "-f", CONFIG, "-d", str(dest)]
        if args.offline:
            cmd.append("--no-directory-urls")
    else:
        cmd = [sys.executable, "-m", "mkdocs", "serve", "-f", CONFIG,
               "-a", "127.0.0.1:%d" % args.port,
               # Essential: docs_dir is an empty placeholder, so without these the
               # server would watch nothing and editing a lesson would never reload.
               "--watch", str(src),
               "--watch", str(REPO / "scripts" / "gen_course_site.py")]

    try:
        info = stage_for(folder, use_gh=False) if folder.is_dir() else {}
    except Exception:
        info = {}

    print("\n\U0001F50E %s -- %s view" % (slug, args.view))
    print("   Source: %s" % ref_label)
    if info.get("stage_name"):
        print("   Stage:  %s" % info["stage_name"])
    if args.ref:
        print("   Note:   I made a temporary copy of the course as it exists on '%s'.\n"
              "           Your own files have not moved and have not changed." % args.ref)
    if not args.build:
        print("   Open:   http://127.0.0.1:%d/   (Ctrl-C to stop)\n" % args.port)
        if args.open_browser:
            webbrowser.open("http://127.0.0.1:%d/" % args.port)
    else:
        print("")

    try:
        return subprocess.call(cmd, cwd=REPO, env=env)
    except KeyboardInterrupt:
        return 0
    finally:
        if cleanup:
            cleanup()


if __name__ == "__main__":
    sys.exit(main())
