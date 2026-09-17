"""Build every in-flight course's review site into one output tree, for deployment.

Called by .github/workflows/pages.yml after the competency site has been built, so that
a single `ghp-import` publishes everything at once:

    site/                    the competency framework site (built first, and the gate)
    site/review/<slug>/      reviewer view -- the whole package
    site/learn/<slug>/       learner view  -- no design doc, mentor guide or answer key

Usage:
    python scripts/build_review_sites.py --out site [--only <slug>] [--no-fetch]
                                         [--source-out <dir>]

--source-out captures each course's source markdown from the ref it was built from, for
scripts/check_learner_view.py --sources. The gate has to compare the built pages against
the ref that produced them, and on the deploy that is NOT the checkout: the workflow is
pinned to main while each course is built from its own branch.

TWO PROPERTIES THIS FILE EXISTS TO GUARANTEE:

1. A broken draft must never take down the published competency site. Every course is
   built in isolation; a failure writes a placeholder page, logs a warning, and the
   script still exits 0. Only the competency build (in the workflow, before this runs)
   is allowed to fail the job.

2. A course must never silently vanish from a URL that worked yesterday. The deploy
   replaces the whole gh-pages tree, so "failed to build" and "deleted" would otherwise
   look identical to a reviewer holding a link. Hence the placeholder.

Ref selection is by merge-base rather than by open PR, which matters more than it looks:
an SME reviews at stage 5, BEFORE a PR exists, and stage 6 exits by MERGING the PR. A
merge-base test serves the branch while it has unmerged commits and flips to main the
moment it merges -- so one URL works across all three stages with no state stored
anywhere, and no dependency on the GitHub API. A squash or rebase merge leaves no such
ancestry, so ref_for() also flips when the branch proposes nothing further under
modules/; otherwise a shipped course would keep serving its stale draft forever.
"""
import argparse
import datetime
import html
import os
import pathlib
import shutil
import subprocess
import sys
import tempfile

import yaml

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from course_stage import (REPO, SITE, branch_slug, course_folders,  # noqa: E402
                          open_pr_for, review_url, stage_for)

VIEWS = (("reviewer", "review"), ("learner", "learn"))
BUILD_TIMEOUT = 300

for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", errors="replace")
    except (AttributeError, ValueError):
        pass


def git(*args, **kw):
    return subprocess.run(("git",) + args, cwd=kw.get("cwd", REPO),
                          capture_output=True, text=True)


def git_ok(*args):
    return git(*args).returncode == 0


def ref_for(url_slug):
    """origin/course/<slug> while it still proposes course content, else origin/main."""
    branch = "origin/course/%s" % url_slug
    if not git_ok("rev-parse", "-q", "--verify", branch):
        return "origin/main"
    if git_ok("merge-base", "--is-ancestor", branch, "origin/main"):
        return "origin/main"          # merged (or never diverged): main is the truth
    # A squash or rebase merge rewrites the commits, so the branch never becomes an
    # ancestor of main and the test above would keep serving it forever -- pointing a
    # pilot learner at a stale draft of a course that has already shipped. Content is
    # the honest question: if the branch proposes nothing further under modules/, main
    # already has everything it had.
    if git_ok("diff", "--quiet", "origin/main", branch, "--", "modules"):
        return "origin/main"
    return branch


def current_branch():
    """This checkout's branch, for the GitHub links a --local build renders."""
    out = git("rev-parse", "--abbrev-ref", "HEAD")
    name = out.stdout.strip() if out.returncode == 0 else ""
    return name if name and name != "HEAD" else "main"


def gh_ref(ref):
    """A ref as GitHub's /blob/ and /edit/ URLs spell it -- no remote-tracking prefix.

    `origin/course/x` is a perfectly good local ref and a 404 on github.com.
    """
    return ref[len("origin/"):] if ref.startswith("origin/") else ref


def course_title(src, fallback):
    """The course's own title, from its README frontmatter -- not its folder name."""
    readme = src / "README.md"
    try:
        text = readme.read_text(encoding="utf-8")
    except OSError:
        return fallback
    if text.startswith("---"):
        end = text.find("\n---", 3)
        if end != -1:
            try:
                fm = yaml.safe_load(text[3:end]) or {}
            except yaml.YAMLError:
                fm = {}
            if isinstance(fm, dict) and str(fm.get("title") or "").strip():
                return str(fm["title"]).strip()
    for line in text.split("\n"):
        if line.startswith("# "):
            return line[2:].strip() or fallback
    return fallback


def short_sha(ref):
    out = git("rev-parse", "--short", ref)
    return out.stdout.strip() if out.returncode == 0 else "unknown"


def find_course_dir(modules_root, url_slug):
    """Match by branch_slug: a legacy folder has spaces and capitals in its name."""
    if not modules_root.is_dir():
        return None
    for f in sorted(modules_root.iterdir()):
        if f.is_dir() and branch_slug(f.name) == url_slug:
            return f
    return None


PLACEHOLDER = """<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{slug} - preview unavailable</title>
<style>
 body{{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:40rem;
      margin:4rem auto;padding:0 1.5rem;line-height:1.6;color:#1c1c1c}}
 code{{background:#f2f2f2;padding:.1em .35em;border-radius:3px}}
 a{{color:#0b6b5f}}
 @media (prefers-color-scheme:dark){{body{{background:#14181d;color:#e6e6e6}}
   code{{background:#25292e}} a{{color:#6fd3c2}}}}
</style></head><body>
<h1>This preview could not be built</h1>
<p><strong>{slug}</strong> could not be rendered from <code>{ref}</code>
 ({sha}) on {when}.</p>
<p>The course itself is fine on GitHub &mdash;
 <a href="{source}">read it there</a> &mdash; and the preview will come back
 automatically on the next successful build.</p>
<p>To see the error, run <code>python scripts/review_site.py --slug {slug}</code>
 locally.</p>
</body></html>
"""

INDEX = """<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Course {label}</title>
<style>
 body{{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:56rem;
      margin:3rem auto;padding:0 1.5rem;line-height:1.55;color:#1c1c1c}}
 table{{border-collapse:collapse;width:100%;margin-top:1.5rem}}
 th,td{{text-align:left;padding:.55rem .6rem;border-bottom:1px solid #e3e3e3;
        vertical-align:top;font-size:.94rem}}
 th{{font-weight:600;color:#555}} code{{font-size:.86em;color:#666}}
 .note{{background:#fff8e6;border-left:3px solid #e0a800;padding:.8rem 1rem;
        border-radius:0 4px 4px 0}}
 a{{color:#0b6b5f}}
 @media (prefers-color-scheme:dark){{body{{background:#14181d;color:#e6e6e6}}
   th,td{{border-color:#2c3239}} th{{color:#9aa4ae}} code{{color:#9aa4ae}}
   .note{{background:#2a2412;border-color:#8a6d1f}} a{{color:#6fd3c2}}}}
</style></head><body>
<h1>Course {label}</h1>
<p class="note"><strong>Drafts under review.</strong> These are courses still moving
 through the production pipeline. They are not published curriculum, and not part of the
 <a href="{site}/">LT Consultant competency framework</a>.{extra}</p>
<table><thead><tr><th>Course</th><th>Stage</th><th>Built from</th></tr></thead><tbody>
{rows}
</tbody></table>
<p style="margin-top:2rem;font-size:.85rem;color:#777">Rebuilt {when}.</p>
</body></html>
"""


def build_one(course, view, segment, out_root, pipeline_slugs, placeholder=True):
    """Build one (course, view). Returns True on success.

    On failure: writes a placeholder page when `placeholder` (the deploy, which must
    survive a broken draft), or just reports when not (a PR check, where the author
    should fix it before merge).
    """
    dest = out_root / segment / course["url_slug"]
    env = dict(os.environ)
    env.update({
        "LTCT_COURSE_ROOT": str(course["src"]),
        "LTCT_COURSE_FOLDER": course["src"].name,
        "LTCT_VIEW": view,
        "LTCT_COURSE_REF": course["gh_ref"],
        "LTCT_COURSE_TITLE": course["title"],
        "LTCT_SITE_URL": "%s/%s/%s/" % (SITE, segment, course["url_slug"]),
        "LTCT_EDIT_URI": "edit/%s/" % course["gh_ref"],
        "LTCT_REVIEW_BASE": "%s/%s" % (SITE, segment),
        "LTCT_PIPELINE_SLUGS": pipeline_slugs,
        "LTCT_COURSE_PR": course["pr"] or "",
        "DISABLE_MKDOCS_2_WARNING": "true",
        "CI": "",                       # keep the social plugin off; it needs Cairo
    })
    try:
        subprocess.run([sys.executable, "-m", "mkdocs", "build",
                        "-f", "mkdocs-review.yml", "-d", str(dest)],
                       cwd=REPO, env=env, check=True, capture_output=True,
                       text=True, timeout=BUILD_TIMEOUT)
        return True
    except (subprocess.CalledProcessError, subprocess.TimeoutExpired) as exc:
        detail = (getattr(exc, "stderr", "") or "")[-1500:]
        print("::%s title=Review build failed::%s (%s) from %s"
              % ("warning" if placeholder else "error",
                 course["url_slug"], view, course["ref"]))
        print(detail, file=sys.stderr)
        if not placeholder:
            return False
        dest.mkdir(parents=True, exist_ok=True)
        (dest / "index.html").write_text(PLACEHOLDER.format(
            slug=html.escape(course["url_slug"]),
            ref=html.escape(course["gh_ref"]),
            sha=course["sha"],
            when=datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d %H:%M UTC"),
            source="https://github.com/dhigby/virtual-ltct/tree/%s/modules/%s"
                   % (course["gh_ref"], course["src"].name),
        ), encoding="utf-8")
        return False


def capture_sources(src, dest):
    """Copy a course's source markdown out of its worktree before the worktree dies.

    The learner-view gate lifts each answer key from source and asserts it is absent from
    the built pages. Reading that source from the checkout would be reading a DIFFERENT
    ref than the one the pages were built from -- which both misses a real leak on a
    branch and can fail the deploy on a key that only main has. So the sources travel
    with the build.

    Markdown is copied whole; everything else becomes an empty file, because the only
    thing the gate asks of a non-markdown source is whether its NAME got published.
    """
    if dest.exists():
        shutil.rmtree(dest, ignore_errors=True)
    dest.mkdir(parents=True, exist_ok=True)
    for p in sorted(src.iterdir()):
        if p.is_dir() or p.name.startswith("."):
            continue
        if p.suffix.lower() == ".md":
            shutil.copy2(p, dest / p.name)
        else:
            (dest / p.name).touch()


def write_index(out_root, segment, label, courses, extra=""):
    rows = []
    for c in sorted(courses, key=lambda c: c["url_slug"]):
        rows.append(
            "<tr><td><a href=\"%s/\">%s</a>%s</td><td>%s</td>"
            "<td><code>%s</code> %s</td></tr>"
            % (html.escape(c["url_slug"]), html.escape(c["title"]),
               "" if c["ok"][segment] else
               " <span title=\"preview failed\">&#9888;</span>",
               html.escape(c["stage"]), html.escape(c["gh_ref"]),
               c["sha"]))
    (out_root / segment).mkdir(parents=True, exist_ok=True)
    (out_root / segment / "index.html").write_text(INDEX.format(
        label=label, site=SITE, extra=extra, rows="\n".join(rows),
        when=datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d %H:%M UTC"),
    ), encoding="utf-8")


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--out", required=True, help="output root (the built competency site)")
    ap.add_argument("--only", help="build just this course")
    ap.add_argument("--no-fetch", action="store_true")
    ap.add_argument("--local", action="store_true",
                    help="build from this checkout rather than from git refs -- what a PR "
                         "check wants, since the point is to validate the proposed content")
    ap.add_argument("--fail-on-error", action="store_true",
                    help="exit 1 if any course fails to build, instead of writing a "
                         "placeholder. For PR checks: an author should fix a broken course "
                         "before merge, whereas the deploy must survive one.")
    ap.add_argument("--source-out",
                    help="capture each course's source markdown here, from the SAME ref "
                         "it was built from, for scripts/check_learner_view.py --sources. "
                         "Must be outside --out: these files include the answer keys.")
    args = ap.parse_args()

    out_root = pathlib.Path(args.out).resolve()
    out_root.mkdir(parents=True, exist_ok=True)

    if not args.no_fetch and not args.local:
        git("fetch", "--no-tags", "--prune", "origin",
            "+refs/heads/*:refs/remotes/origin/*")

    folders = course_folders()
    if args.only:
        want = branch_slug(args.only)
        folders = [f for f in folders if branch_slug(f.name) == want]
        if not folders:
            print("No such course: %s" % args.only, file=sys.stderr)
            return 2

    source_out = pathlib.Path(args.source_out).resolve() if args.source_out else None
    if source_out and (source_out == out_root or out_root in source_out.parents):
        print("--source-out must not be inside --out: it holds the answer keys.",
              file=sys.stderr)
        return 2

    pipeline_slugs = ",".join(branch_slug(f.name) for f in course_folders())
    worktrees = pathlib.Path(os.environ.get("RUNNER_TEMP") or tempfile.gettempdir())
    worktrees = worktrees / "ltct-review-wt"
    courses, failures = [], 0

    for folder in folders:
        url_slug = branch_slug(folder.name)
        wt = None
        if args.local:
            # Validate the content in front of us, not what happens to be on origin.
            src, ref = folder, (os.environ.get("GITHUB_HEAD_REF") or "this checkout")
            link_ref = os.environ.get("GITHUB_HEAD_REF") or current_branch()
        else:
            ref = ref_for(url_slug)
            link_ref = gh_ref(ref)
            wt = worktrees / url_slug
            if wt.exists():
                git("worktree", "remove", "--force", str(wt))
                shutil.rmtree(wt, ignore_errors=True)
            wt.parent.mkdir(parents=True, exist_ok=True)
            added = git("worktree", "add", "--detach", str(wt), ref)
            src = find_course_dir(wt / "modules", url_slug) if added.returncode == 0 else None
            if src is None:
                # The folder may not exist on that ref (renamed, or not yet pushed).
                # Fall back to the working tree and say so, rather than build nothing.
                src, ref, link_ref = folder, "origin/main", "main"
        try:
            try:
                info = stage_for(src, use_gh=False)
                stage = info.get("stage_name", "")
            except Exception:
                stage = ""
            try:
                pr = open_pr_for("course/%s" % url_slug) or {}
            except Exception:
                pr = {}

            course = {
                "url_slug": url_slug, "src": src, "ref": ref, "gh_ref": link_ref,
                "sha": "-" if args.local else short_sha(ref),
                "title": course_title(src, folder.name),
                "stage": stage, "pr": pr.get("url", ""),
                "ok": {},
            }
            if source_out:
                capture_sources(src, source_out / url_slug)
            for view, segment in VIEWS:
                good = build_one(course, view, segment, out_root, pipeline_slugs,
                                 placeholder=not args.fail_on_error)
                course["ok"][segment] = good
                if not good:
                    failures += 1
            courses.append(course)
            print("  %-55s %-14s %s" % (url_slug, gh_ref(ref),
                                        "ok" if all(course["ok"].values()) else "FAILED"))
        finally:
            if wt is not None:
                git("worktree", "remove", "--force", str(wt))
                shutil.rmtree(wt, ignore_errors=True)

    write_index(out_root, "review", "review", courses,
                extra=" This is the <strong>reviewer</strong> view: it includes design "
                      "documents, mentor guides and quiz answer keys.")
    write_index(out_root, "learn", "preview", courses,
                extra=" This is the <strong>learner</strong> view: design documents, "
                      "mentor guides and quiz answers are not included.")

    if args.fail_on_error:
        print("\n%d course(s), %d view build(s) failed." % (len(courses), failures))
        if failures:
            print("A course that cannot be rendered cannot be reviewed. Reproduce it with "
                  "`python scripts/review_site.py --slug <slug>`.", file=sys.stderr)
        return 1 if failures else 0

    print("\n%d course(s), %d view build(s) fell back to a placeholder."
          % (len(courses), failures))
    if courses:
        print("Reviewer index: %s/review/" % SITE)
        print("Example:        %s" % review_url(courses[0]["url_slug"]))
    # Otherwise always 0: a broken draft must not fail the deploy of everything else.
    return 0


if __name__ == "__main__":
    sys.exit(main())
