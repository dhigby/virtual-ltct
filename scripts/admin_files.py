#!/usr/bin/env python3
"""The operator's files, kept out of every repository -- the pure half of ltct_admin.py.

Everything here runs offline and calls no server, so tests/test_ltct_admin.py tests it with
rows built in memory. scripts/ltct_admin.py is the half that talks to Moodle.

WHAT THIS FILE IS FOR (specs/008-admin-tooling/research.md R14, R15)

1. The path guard. An intake list names real people, and this repository is public
   (constitution III). So a file argument is refused if it, or its nearest existing
   ancestor, sits inside ANY git working tree -- a `.git` directory, or the `.git` FILE a
   worktree has -- or anywhere under this repo's root. The walk is pure Python and needs no
   `git` executable, and it fails closed: an ancestor it cannot read is a refusal.

2. Masking. What the tool prints may reach an AI assistant's context through the guided
   command, so people are shown as `a***@example.org` unless the operator asks otherwise.

3. The confirmation code. A preview prints a code; applying needs it back. The code is the
   first 10 hex characters of a SHA-256 over, per row in order: the row number, a SHA-256
   of the normalised input row, and the row's OUTCOME CLASS. The class absorbs progress
   (`new`, `will_set_org`, `will_enrol` and `unchanged` are one class, "proceeds"), so a
   re-run after an interrupted apply recomputes the same code and simply finishes. It is
   never computed over the masked display or the planned changes, so `--show-people`
   cannot change it.

4. The file kinds (data-model section 1): their columns, and every rule that can be checked
   without Moodle. A file with any offline problem is refused whole, with row numbers,
   before any call.

Row numbers are the ones a spreadsheet shows: the header is row 1, so the first person is
row 2.
"""
import csv
import hashlib
import json
import os
import pathlib
import re
import shutil
import subprocess

REPO = pathlib.Path(__file__).resolve().parent.parent

# Where an operator's files should live instead: outside every repository (research R14).
SUGGESTED_HOME = "~/ltct-private/ (Windows: %USERPROFILE%\\ltct-private\\)"

# Multiple values inside one cell are separated by this (data-model section 1).
MULTI_SEP = ";"

# --- the file kinds ----------------------------------------------------------------------
# kind -> (required columns, optional columns). Column names are case-insensitive. An
# unknown column is refused, never ignored, so a misspelt one cannot silently drop data.
KINDS = {
    "intake": (("email", "firstname", "lastname", "organisation"),
               ("country", "protection", "pseudonym", "email_checked", "courses")),
    "move": (("email", "organisation"), ()),
    "mentors": (("learner_email", "mentor_email"), ()),
    "course-mentors": (("course", "mentor_email"), ("learner_email", "cohort")),
    "managers": (("email", "cohort", "action"), ()),
    "suspension": (("email",), ()),
}

# Columns holding an email address: lowercased when normalised, masked when shown.
EMAIL_COLUMNS = frozenset({"email", "learner_email", "mentor_email"})
# Columns holding several values: split, trimmed and sorted when normalised.
MULTI_COLUMNS = frozenset({"courses"})

EMAIL = re.compile(r"[^@\s;,]+@[^@\s;,]+\.[^@\s;,]+")   # as site_config.EMAIL, minus separators
COURSE = re.compile(r"ltct:[^:\s;]+")                  # a course this repo publishes, not a module
COUNTRY = re.compile(r"[A-Za-z]{2}")                    # ISO 3166-1 alpha-2, Moodle's country field
IDNUMBER = re.compile(r"\S{1,100}")                     # cohort.idnumber is 100 characters
PROTECTION_LEVELS = ("none", "email", "firstname", "pseudonym")   # spec 016's level keys
MANAGER_ACTIONS = ("add", "remove")
MENTORS_COHORT = "ltct:mentors"                         # as site_config.MENTORS_COHORT

# --- outcome classes (research R15) ------------------------------------------------------
# Outcomes on one path share a class, so finishing an interrupted run keeps the code. Every
# other outcome -- each flagged_*, rejected, waits, lost, refused -- is a class of its own.
PROCEEDS = "proceeds"
PROCEEDING_OUTCOMES = frozenset({
    "new", "will_set_org", "will_enrol", "unchanged",          # intake
    "would_change",                                            # membership, mentor, suspension
    "would_move", "moved",                                     # move
    "would_add", "would_enable", "would_disable", "already",   # cohort enrolment
})


class FileRefused(Exception):
    """A whole file refused before any call; `problems` lists each one with its row."""

    def __init__(self, problems):
        self.problems = list(problems)
        super().__init__("\n".join(self.problems))


class PathRefused(Exception):
    """A file argument the tool will not read or write; the message says why and where to go."""


# --- the path guard ------------------------------------------------------------------------
def _exists(path):
    """lstat-based existence that fails closed: only "not there" is False."""
    try:
        os.lstat(path)
        return True
    except (FileNotFoundError, NotADirectoryError):
        return False
    except OSError as e:
        raise PathRefused("Cannot check %s (%s), so the file is refused. Keep operator files "
                          "in a folder you own, such as %s."
                          % (path, e.strerror or e.__class__.__name__, SUGGESTED_HOME))


def guard_path(path, repo_root=REPO):
    """Return `path` resolved, or raise PathRefused if it is in any git tree or this repo.

    Walks up from the path, or from its nearest existing ancestor when it does not exist yet
    (a new --out file in a new folder), and refuses if any ancestor holds `.git` -- a
    directory in a normal checkout, a file in a worktree. Also refuses anything under
    `repo_root`, whatever git says. Needs no `git` executable. Refuses when a check cannot
    run (research R14).
    """
    try:
        resolved = pathlib.Path(path).expanduser().resolve()
        root = pathlib.Path(repo_root).resolve()
    except (OSError, RuntimeError) as e:
        raise PathRefused("Cannot resolve %s (%s), so it is refused." % (path, e))
    if resolved == root or resolved.is_relative_to(root):
        note = git_history_note(resolved, root) if _exists(resolved) else ""
        raise PathRefused(
            "%s is inside this repository, which is public. Learner files never go here: "
            "keep them in %s.%s" % (resolved, SUGGESTED_HOME, note))
    anchor = resolved
    while not _exists(anchor):
        if anchor.parent == anchor:
            break
        anchor = anchor.parent
    for folder in (anchor, *anchor.parents):
        if _exists(folder / ".git"):
            raise PathRefused(
                "%s is inside a git working tree (%s), and anything there can be committed "
                "and published. Keep learner files in %s." % (resolved, folder, SUGGESTED_HOME))
    return resolved


def git_history_note(path, repo_root=REPO):
    """For a refused input inside this repo: say whether git already has it (research R14).

    GitDoc commits a saved file within minutes, and refusing to read it does not un-publish
    it. Returns "" when git is not installed or cannot answer; the refusal stands either way.
    """
    git = shutil.which("git")
    if not git:
        return ""
    try:
        done = subprocess.run([git, "-C", str(repo_root), "log", "--all", "--oneline", "--",
                               str(path)], capture_output=True, text=True, timeout=30)
    except (OSError, subprocess.SubprocessError):
        return ""
    if done.returncode != 0:
        return ""
    if done.stdout.strip():
        return ("\nGit ALREADY HAS this file in its history. Tell the site team now: it may "
                "have been published, and removing it from history is their job.")
    return "\nGit has no record of this file. Move it out before anything commits it."


# --- masking -------------------------------------------------------------------------------
def mask_email(email):
    """`alice@example.org` -> `a***@example.org`. classes/admin/masking.php does the same."""
    text = (email or "").strip().lower()
    local, at, domain = text.partition("@")
    if not at or not local or not domain:
        return "***"
    return "%s***@%s" % (local[0], domain)


# --- the confirmation code -------------------------------------------------------------------
def outcome_class(outcome):
    """The class an outcome contributes to the code: "proceeds", or the outcome itself."""
    return PROCEEDS if outcome in PROCEEDING_OUTCOMES else str(outcome)


def _normalise(row):
    """The input columns of one row, normalised, as canonical JSON. Never the row number."""
    out = {}
    for column, value in row.items():
        if column == "row":
            continue
        text = "" if value is None else str(value).strip()
        if column in EMAIL_COLUMNS:
            text = text.lower()
        elif column in MULTI_COLUMNS:
            text = MULTI_SEP.join(sorted(v.strip() for v in text.split(MULTI_SEP) if v.strip()))
        elif column in ("protection", "action"):
            text = text.lower()
        elif column == "country":
            text = text.upper()
        out[column] = text
    return json.dumps(out, sort_keys=True, ensure_ascii=False, separators=(",", ":"))


def confirmation_code(rows, outcomes):
    """First 10 hex of SHA-256 over (row number, hash of normalised row, outcome class).

    `rows` are the file's rows in order, each a dict with "row" and its columns; `outcomes`
    are their preview outcomes, in the same order. The masked key and the planned changes
    are deliberately not inputs (research R15).
    """
    rows, outcomes = list(rows), list(outcomes)
    if len(rows) != len(outcomes):
        raise ValueError("one outcome per row")
    digest = hashlib.sha256()
    for row, outcome in zip(rows, outcomes):
        inner = hashlib.sha256(_normalise(row).encode("utf-8")).hexdigest()
        digest.update(("%s\x1f%s\x1f%s\n" % (row.get("row"), inner, outcome_class(outcome)))
                      .encode("utf-8"))
    return digest.hexdigest()[:10]


# --- reading a file ------------------------------------------------------------------------
def read_csv(path, kind=None):
    """Read an operator's CSV into rows: dicts of lowercased column -> trimmed text, plus "row".

    UTF-8, with or without the BOM a spreadsheet adds. Blank lines are skipped but still
    counted, so row numbers match the spreadsheet. With `kind`, an unknown or missing column
    refuses the whole file. Call guard_path() first: this function does not.
    """
    problems = []
    rows = []
    try:
        with open(path, newline="", encoding="utf-8-sig") as fh:
            reader = csv.reader(fh)
            header = next(reader, None)
            if header is None:
                raise FileRefused(["The file is empty: it needs a header row."])
            columns = [h.strip().lower() for h in header]
            if len(set(columns)) != len(columns):
                problems.append("row 1: a column is named twice")
            if "" in columns:
                problems.append("row 1: a column has no name")
            if kind is not None:
                problems += _column_problems(kind, columns)
            for number, cells in enumerate(reader, start=2):
                if not any(c.strip() for c in cells):
                    continue
                if len(cells) > len(columns):
                    problems.append("row %d: more cells than the header has columns" % number)
                    continue
                cells = cells + [""] * (len(columns) - len(cells))
                row = {"row": number}
                row.update({c: v.strip() for c, v in zip(columns, cells)})
                rows.append(row)
    except UnicodeDecodeError:
        raise FileRefused(["The file is not UTF-8. Save it as \"CSV UTF-8\" and try again."])
    except csv.Error as e:
        raise FileRefused(["The file is not a readable CSV: %s" % e])
    if problems:
        raise FileRefused(problems)
    return rows


def _column_problems(kind, columns):
    required, optional = KINDS[kind]
    problems = []
    for missing in [c for c in required if c not in columns]:
        problems.append("row 1: the required column %r is missing" % missing)
    for unknown in [c for c in columns if c and c not in required and c not in optional]:
        problems.append("row 1: unknown column %r; a %s file has only %s"
                        % (unknown, kind, ", ".join(required + optional)))
    return problems


def template_header(kind):
    """The header row a blank file of this kind starts with (`ltct_admin.py template`)."""
    required, optional = KINDS[kind]
    return list(required + optional)


# --- offline validation ------------------------------------------------------------------
def validate_file(kind, rows, declared_orgs):
    """Every problem in a file of `kind` that can be found without Moodle, with row numbers.

    `rows` are dicts with "row" and the file's columns (read_csv's output, or built in a
    test); `declared_orgs` are the organisation keys in moodle/site/organisations.yaml.
    Returns a list of plain sentences; empty means the file may be previewed. Whether a
    course or cohort exists is the server's to say, before any row (data-model section 1).
    """
    if kind not in KINDS:
        raise ValueError("unknown file kind %r" % kind)
    rows = list(rows)
    if not rows:
        return ["The file has no rows below its header."]
    columns = []
    for row in rows:
        for c in row:
            if c != "row" and c not in columns:
                columns.append(c)
    problems = _column_problems(kind, columns)
    if problems:
        return problems
    orgs = set(declared_orgs)
    seen = {}
    check = _CHECKS[kind]
    for row in rows:
        n = row.get("row")
        get = lambda c: str(row.get(c) or "").strip()   # noqa: E731
        for c in KINDS[kind][0]:
            if not get(c):
                problems.append("row %s: %s is empty" % (n, c))
        for c in EMAIL_COLUMNS & set(row):
            if get(c) and not EMAIL.fullmatch(get(c)):
                problems.append("row %s: %s is not an email address" % (n, c))
        problems += ["row %s: %s" % (n, p) for p in check(get, orgs)]
        key = _DUPLICATE_KEY[kind](get)
        if all(key):
            if key in seen:
                problems.append("row %s: the same as row %s" % (n, seen[key]))
            else:
                seen[key] = n
    return problems


def _org(get, orgs, column="organisation"):
    value = get(column)
    if value and value not in orgs:
        return ["%s %r is not declared in moodle/site/organisations.yaml (run "
                "\"ltct_admin.py list organisations\")" % (column, value)]
    return []


def _check_intake(get, orgs):
    problems = _org(get, orgs)
    if get("country") and not COUNTRY.fullmatch(get("country")):
        problems.append("country %r is not a two-letter country code" % get("country"))
    level = get("protection").lower() or "none"
    if level not in PROTECTION_LEVELS:
        problems.append("protection %r is not one of %s" % (get("protection"),
                                                            ", ".join(PROTECTION_LEVELS)))
    if level == "pseudonym" and not get("pseudonym"):
        problems.append("protection is pseudonym, so the pseudonym column is needed")
    if get("pseudonym") and level != "pseudonym":
        problems.append("a pseudonym is given, but protection is not pseudonym")
    checked = get("email_checked").lower()
    if checked not in ("", "yes"):
        problems.append("email_checked %r must be yes or empty" % get("email_checked"))
    elif checked and level == "none":
        problems.append("email_checked is only for a row that asks for protection")
    for course in [c.strip() for c in get("courses").split(MULTI_SEP) if c.strip()]:
        if not COURSE.fullmatch(course):
            problems.append("course %r is not a course idnumber like ltct:<slug>" % course)
    return problems


def _check_move(get, orgs):
    return _org(get, orgs)


def _check_mentors(get, orgs):
    if get("learner_email") and get("learner_email").lower() == get("mentor_email").lower():
        return ["a learner cannot be their own mentor"]
    return []


def _check_course_mentors(get, orgs):
    problems = []
    if get("course") and not COURSE.fullmatch(get("course")):
        problems.append("course %r is not a course idnumber like ltct:<slug>" % get("course"))
    if bool(get("learner_email")) == bool(get("cohort")):
        problems.append("give exactly one of learner_email or cohort")
    if get("cohort") and not IDNUMBER.fullmatch(get("cohort")):
        problems.append("cohort %r is not a cohort idnumber" % get("cohort"))
    return problems


def _check_managers(get, orgs):
    problems = []
    cohort = get("cohort")
    if cohort:
        m = re.fullmatch(r"ltct:org:([a-z][a-z0-9-]*):managers", cohort)
        if cohort != MENTORS_COHORT and not m:
            problems.append("cohort %r must be ltct:org:<key>:managers or %s"
                            % (cohort, MENTORS_COHORT))
        elif m and m.group(1) not in orgs:
            problems.append("organisation %r in %r is not declared in "
                            "moodle/site/organisations.yaml" % (m.group(1), cohort))
    if get("action") and get("action").lower() not in MANAGER_ACTIONS:
        problems.append("action %r is not add or remove" % get("action"))
    return problems


def _check_suspension(get, orgs):
    return []


_CHECKS = {"intake": _check_intake, "move": _check_move, "mentors": _check_mentors,
           "course-mentors": _check_course_mentors, "managers": _check_managers,
           "suspension": _check_suspension}

# What makes two rows "the same" in each kind (case-insensitive emails).
_DUPLICATE_KEY = {
    "intake": lambda g: (g("email").lower(),),
    "move": lambda g: (g("email").lower(),),
    "suspension": lambda g: (g("email").lower(),),
    "mentors": lambda g: (g("learner_email").lower(), g("mentor_email").lower()),
    "managers": lambda g: (g("email").lower(), g("cohort")),
    "course-mentors": lambda g: (g("course"), g("mentor_email").lower(),
                                 g("learner_email").lower() or g("cohort")),
}
