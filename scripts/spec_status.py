#!/usr/bin/env python3
"""Show spec-kit feature progress (spec, plan, tasks) across every branch and worktree.

Specs move forward on feature branches, often in their own worktrees, so `main` alone
says little about where a feature stands. This reads `specs/<feature>/` from each
place it lives and collapses identical states into one row, so a spec that is the same
everywhere shows once and one that has moved on shows where.

A worktree is read from disk (uncommitted edits included); a branch without a worktree
is read from its last commit. Read-only: it never checks out, fetches or writes.

Usage:
  python scripts/spec_status.py               # every spec, every local branch + worktree
  python scripts/spec_status.py 003 011       # only specs whose folder starts with these
  python scripts/spec_status.py --best        # one row per spec: its most advanced state
  python scripts/spec_status.py --remotes     # also read origin/* branches
  python scripts/spec_status.py -v           # list every location, not the first few
  python scripts/spec_status.py --json

WHERE: `branch [worktree-folder]` is read from disk, a trailing `*` means uncommitted
changes under specs/; a bare branch name is read from its last commit.
"""
import argparse
import json
import pathlib
import re
import subprocess
import sys

REPO = pathlib.Path(__file__).resolve().parent.parent
TASK_RE = re.compile(r"^\s*[-*] \[( |x|X)\]", re.M)
SKIP_REFS = {"gh-pages", "origin/gh-pages", "origin/HEAD"}


def git(*args, cwd=REPO, input=None):
    return subprocess.run(["git", *args], cwd=cwd, input=input, capture_output=True,
                          text=True, encoding="utf-8", errors="replace").stdout


def worktrees():
    """[(path, branch, sha)] for every worktree, main checkout first."""
    out, cur = [], {}
    for line in git("worktree", "list", "--porcelain").splitlines() + [""]:
        if not line:
            if cur.get("worktree"):
                out.append((cur["worktree"], cur.get("branch", "(detached)"), cur.get("HEAD", "")))
            cur = {}
            continue
        key, _, val = line.partition(" ")
        cur[key] = val.removeprefix("refs/heads/") if key == "branch" else val
    return out


def branches(remotes):
    pattern = ["refs/heads"] + (["refs/remotes"] if remotes else [])
    out = git("for-each-ref", "--format=%(refname:short) %(objectname)", *pattern)
    return [tuple(l.split(" ", 1)) for l in out.splitlines() if l]


def parse_feature(files):
    """files: {name: text or None-if-absent}. Returns the feature's state."""
    tasks = files.get("tasks.md")
    done = total = 0
    if tasks is not None:
        marks = TASK_RE.findall(tasks)
        total, done = len(marks), sum(m in "xX" for m in marks)
    status = None
    if files.get("spec.md"):
        m = re.search(r"^\*\*Status\*\*:\s*(.+)$", files["spec.md"], re.M)
        status = m.group(1).strip() if m else None
    step = None
    if files.get(".spec-context.json"):
        try:
            ctx = json.loads(files[".spec-context.json"])
            step = f"{ctx.get('currentStep', '?')}/{ctx.get('status', '?')}"
        except ValueError:
            step = "unreadable"
    return {
        "spec": "spec.md" in files, "plan": "plan.md" in files,
        "tasks_file": tasks is not None, "done": done, "total": total,
        "status": status, "step": step, "archived": False,
    }


WANTED = ("spec.md", "plan.md", "tasks.md", ".spec-context.json")


def read_worktree(path):
    root = pathlib.Path(path) / "specs"
    feats = {}
    if not root.is_dir():
        return feats
    for d in sorted(p for p in root.iterdir() if p.is_dir()):
        if d.name == "_archive":
            for a in sorted(p for p in d.iterdir() if p.is_dir()):
                feats.setdefault(a.name, {"archived": True})
            continue
        files = {n: (d / n).read_text(encoding="utf-8", errors="replace")
                 for n in WANTED if (d / n).is_file()}
        feats[d.name] = parse_feature(files)
    return feats


def read_ref(ref):
    names = git("ls-tree", "-r", "--name-only", ref, "--", "specs").splitlines()
    by_feat, archived = {}, set()
    for n in names:
        parts = n.split("/")
        if len(parts) >= 3 and parts[1] == "_archive":
            archived.add(parts[2])
        elif len(parts) == 3 and parts[2] in WANTED:
            by_feat.setdefault(parts[1], []).append(parts[2])
        elif len(parts) >= 3:
            by_feat.setdefault(parts[1], [])
    # One cat-file process for every blob on this ref.
    wanted = [(f, n) for f, ns in by_feat.items() for n in ns]
    blobs = {}
    if wanted:
        raw = subprocess.run(["git", "cat-file", "--batch"], cwd=REPO, capture_output=True,
                             input="".join(f"{ref}:specs/{f}/{n}\n" for f, n in wanted).encode())
        buf, i = raw.stdout, 0
        for key in wanted:
            nl = buf.index(b"\n", i)
            header = buf[i:nl].split()
            size = int(header[2]) if len(header) == 3 else 0
            blobs[key] = buf[nl + 1:nl + 1 + size].decode("utf-8", "replace")
            i = nl + 1 + size + 1
    feats = {f: parse_feature({n: blobs[(f, n)] for n in ns}) for f, ns in sorted(by_feat.items())}
    for a in archived:
        feats.setdefault(a, {"archived": True})
    return feats


def locations(remotes):
    """[(label, feats)] -- worktrees from disk, then branches no worktree holds."""
    locs, seen_sha = [], {}
    wts = worktrees()
    held = {b for _, b, _ in wts}
    for path, branch, sha in wts:
        dirty = bool(git("status", "--porcelain", "--", "specs", cwd=path).strip())
        name = pathlib.Path(path).name
        label = f"{branch} [{name}]" + ("*" if dirty else "")
        locs.append((label, read_worktree(path)))
        if not dirty:
            seen_sha[sha] = label
    local = {b for b, _ in branches(False)}
    for ref, sha in branches(remotes):
        if ref in held or ref in SKIP_REFS:
            continue
        if ref.startswith("origin/") and ref.removeprefix("origin/") in local | held \
                and git("rev-parse", ref.removeprefix("origin/")).strip() == sha:
            continue  # remote is identical to the local branch
        if sha in seen_sha:
            continue  # same commit already read
        seen_sha[sha] = ref
        feats = read_ref(ref)
        if feats:
            locs.append((ref, feats))
    return locs


def rank(st):
    """Higher = further along. Tasks done, not percent: a tasks.md that has since grown
    new tasks is newer even though its percentage dropped."""
    if st.get("archived"):
        return (9, 0, 0)
    stage = 3 if st["tasks_file"] else 2 if st["plan"] else 1 if st["spec"] else 0
    return (stage, st["done"], st["total"])


def fmt_tasks(st):
    if st.get("archived"):
        return "archived"
    if not st["tasks_file"]:
        return "-"
    if not st["total"]:
        return "0 tasks"
    pct = 100 * st["done"] // st["total"]
    bar = "#" * (pct // 10) + "." * (10 - pct // 10)
    return f"[{bar}] {st['done']:>3}/{st['total']:<3} {pct:>3}%"


def where(labels, verbose, keep=3):
    if verbose or len(labels) <= keep:
        return ", ".join(labels)
    short = [l.split(" [")[0] + ("*" if l.endswith("*") else "") for l in labels]
    return ", ".join(short[:keep]) + f" +{len(short) - keep} more"


def yn(v):
    return "Y" if v else "-"


def main():
    ap = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    ap.add_argument("filter", nargs="*", help="spec folder prefixes, e.g. 003 011")
    ap.add_argument("--best", action="store_true", help="one row per spec: its most advanced state")
    ap.add_argument("--remotes", action="store_true", help="also read origin/* branches")
    ap.add_argument("-v", "--verbose", action="store_true", help="list every location")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()

    # spec -> state-key -> {"state": .., "where": [labels]}
    table = {}
    for label, feats in locations(args.remotes):
        for feat, st in feats.items():
            if args.filter and not any(feat.startswith(f) for f in args.filter):
                continue
            key = json.dumps(st, sort_keys=True)
            table.setdefault(feat, {}).setdefault(key, {"state": st, "where": []})["where"].append(label)

    rows = []
    for feat in sorted(table):
        variants = sorted(table[feat].values(), key=lambda v: rank(v["state"]), reverse=True)
        if args.best:
            variants = variants[:1]
        for v in variants:
            rows.append({"feature": feat, **v["state"], "where": v["where"]})

    if args.json:
        print(json.dumps(rows, indent=2))
        return 0
    if not rows:
        print("No specs found.")
        return 0

    head = ("FEATURE", "SPEC", "PLAN", "TASKS", "STEP/STATUS", "WHERE")
    out = []
    prev = None
    for r in rows:
        arch = r.get("archived")
        out.append((
            r["feature"] if r["feature"] != prev else "  \"",
            "" if arch else yn(r["spec"]), "" if arch else yn(r["plan"]),
            fmt_tasks(r), "" if arch else (r["step"] or "-"),
            where(r["where"], args.verbose),
        ))
        prev = r["feature"]
    widths = [max(len(head[i]), *(len(o[i]) for o in out)) for i in range(5)]
    line = lambda cells: "  ".join(c.ljust(w) for c, w in zip(cells, widths)) + "  " + cells[5]
    print(line(head))
    print(line(tuple("-" * w for w in widths) + ("-" * 5,)))
    for o in out:
        print(line(o))
    return 0


if __name__ == "__main__":
    sys.exit(main())
