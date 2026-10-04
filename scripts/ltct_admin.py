#!/usr/bin/env python3
"""The site team's administration tool for the LTC Moodle (spec 008).

Brings learners on, enrols cohorts, suspends, moves and assigns mentors, through the
'LTC administration' web service (ltuse_admin) in local_ltuse, with the operator's OWN
token. Contract: specs/008-admin-tooling/contracts/cli.md.

    ltct_admin.py check                                   is the server ready, and am I?
    ltct_admin.py list organisations|cohorts|courses [--org K]
    ltct_admin.py template --kind KIND --out PATH         a blank file to fill in
    ltct_admin.py intake FILE [--apply --confirm CODE]
    ltct_admin.py enrol course --cohort I --course I [--apply --confirm CODE]
    ltct_admin.py enrol pathway --cohort I --pathway P [--apply --confirm CODE]
    ltct_admin.py enrol mirror --from K1 --to K2 [--apply --confirm CODE]
    ltct_admin.py unenrol --cohort I --course I [--apply --confirm CODE]
    ltct_admin.py suspend|reactivate (FILE | --email E) [--apply --confirm CODE]
    ltct_admin.py move FILE [--apply --confirm CODE]
    ltct_admin.py managers FILE [--apply --confirm CODE]
    ltct_admin.py mentors assign FILE | mentors end --mentor E [--apply --confirm CODE]
    ltct_admin.py course-mentors FILE [--remove] [--apply --confirm CODE]
    ltct_admin.py summary --org K [--out PATH]

TWO STEPS, ALWAYS. A command that changes Moodle first previews -- on the server, changing
nothing -- and prints a confirmation code. Run it again with `--apply --confirm CODE` to
make exactly those changes. The second run previews again and refuses if anything the code
covers has changed. Every change goes one row per call and is safe to repeat, so a run cut
off by a bad connection is finished by running the same command again (research R15).

LEARNER FILES NEVER GO IN A REPOSITORY. Every file argument is refused inside any git
working tree, and anywhere in this repo; keep them in ~/ltct-private/. The tool writes no
file unless asked (--out, template) and never a log. People are shown masked
(a***@example.org) unless you pass --show-people at your own terminal (research R14).

ENVIRONMENT (never a file; the repo is public)

    MOODLE_URL           the server
    MOODLE_ADMIN_TOKEN   your own 'LTC administration' token, made by
                         local_ltuse/cli/setup_admin_token.php. Never the publisher's
                         MOODLE_TOKEN: if the two are equal the tool refuses.

EXIT CODES: 0 done, already done, or preview shown; 1 refused, or some rows not applied;
2 usage or configuration error.
"""
import argparse
import csv
import os
import pathlib
import sys
from collections import Counter
from dataclasses import dataclass, field
from typing import Callable

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
import admin_files  # noqa: E402
from moodle_client import RETRY_DELAYS, MoodleClient, MoodleError  # noqa: E402

REPO = admin_files.REPO
SITE_DIR = REPO / "moodle" / "site"

EXIT_OK, EXIT_REFUSED, EXIT_CONFIG = 0, 1, 2

# Every ltuse_admin function the commands built so far call. Extended as each lands
# (contracts/admin-service.md); `check` reports any the token cannot call.
ADMIN_FUNCTIONS = [
    "local_ltuse_admin_check",
    "local_ltuse_admin_list",
    "core_webservice_get_site_info",
]

# Settings the tool refuses to run without (research R13). A wrong unenrolaction turns
# "remove" into data loss; realtime off leaves a new learner outside their cohort.
REQUIRED_SETTINGS = {
    "enrol_cohort/unenrolaction": ("3", "someone who leaves a cohort would be unenrolled and "
                                        "lose their grades (spec 002 groups.yaml)"),
    "tool_dynamic_cohorts/realtime": ("1", "a learner would not join their organisation's "
                                           "cohort until a scheduled refresh (spec 002 "
                                           "cohorts.yaml)"),
}
PROTECTION_CAPABILITY = "local/ltuse:manageprotection"

# 002 R13's production gate, printed on a shared-course enrolment while 016 is not ready.
PRODUCTION_GATE = (
    "Reminder (spec 002 R13): until identity protection (spec 016) is delivered, do not "
    "enrol into a shared course on production any organisation you have marked as possibly "
    "needing protection.")

# Outcomes for which there is nothing left to send: the apply step counts them as already
# done without a call.
NOTHING_TO_DO = frozenset({"unchanged", "already", "moved"})

TEMPLATE_KINDS = tuple(admin_files.KINDS)


class ConfigError(Exception):
    """Usage or configuration; exit 2."""


# --- the connection ----------------------------------------------------------------------
def preflight(environ):
    """(url, token) from the environment, or ConfigError naming the variable, never a value."""
    url = (environ.get("MOODLE_URL") or "").strip()
    token = (environ.get("MOODLE_ADMIN_TOKEN") or "").strip()
    if not url:
        raise ConfigError("MOODLE_URL is not set. Point it at the Moodle site, for example\n"
                          "  $env:MOODLE_URL = 'https://moodle.example.org'")
    if not token:
        raise ConfigError("MOODLE_ADMIN_TOKEN is not set. Ask the site team for your own "
                          "'LTC administration' token (made with "
                          "local_ltuse/cli/setup_admin_token.php) and set it in this terminal.")
    if token == (environ.get("MOODLE_TOKEN") or "").strip():
        raise ConfigError("MOODLE_ADMIN_TOKEN is the same as MOODLE_TOKEN, the publisher's "
                          "token. The administration tool needs its own token, on your own "
                          "account (spec 008 FR-009).")
    return url, token


def default_client(environ):
    url, token = preflight(environ)
    return MoodleClient(url=url, token=token, retries=RETRY_DELAYS)


def declared_organisations(site_dir):
    """Organisation keys from moodle/site/organisations.yaml, through site_config.validate."""
    import site_config
    decl, problems = site_config.validate(pathlib.Path(site_dir))
    if problems:
        raise ConfigError("The site declaration in %s is invalid; run "
                          "\"python scripts/site_config.py validate\"." % site_dir)
    keys = []
    for cohort in decl.get("cohorts", []):
        idnumber = cohort["idnumber"]
        if idnumber.startswith("ltct:org:") and not idnumber.endswith(":managers"):
            keys.append(idnumber[len("ltct:org:"):])
    return keys


# --- preview -> confirm -> apply ---------------------------------------------------------
@dataclass
class Plan:
    """One changing command, as the driver sees it.

    `rows` are the inputs the code covers (a file's rows, or one synthetic row for a
    command such as `enrol course`); `preview(show_people)` returns the server's
    {refusal?, rows: [{row, key, outcome, reason, changes}]}; `apply(row, expected)` sends
    ONE row with its fresh outcome and returns {status, reason}.
    """
    title: str
    source: str
    rows: list
    preview: Callable[[bool], dict]
    apply: Callable[[dict, str], dict]
    command: list
    gate: bool = False
    notes: list = field(default_factory=list)


def _print_refusal(result, out):
    print("Refused before any change: %s" % result["refusal"], file=out)
    print("Nothing has been sent to Moodle. Fix the file or the name, then preview again.",
          file=out)


def _preview(plan, show_people, out):
    """Run the preview; return (code, {row number: preview row}) or None after a refusal."""
    result = plan.preview(show_people) or {}
    if result.get("refusal"):
        _print_refusal(result, out)
        return None
    by_row = {r["row"]: r for r in result.get("rows", [])}
    missing = [r["row"] for r in plan.rows if r["row"] not in by_row]
    if missing:
        raise MoodleError("preview", {"message": "the server returned no result for row(s) %s"
                                                 % ", ".join(map(str, missing))})
    outcomes = [by_row[r["row"]]["outcome"] for r in plan.rows]
    return admin_files.confirmation_code(plan.rows, outcomes), by_row


def _print_counts(plan, by_row, out):
    counts = Counter(by_row[r["row"]]["outcome"] for r in plan.rows)
    print("%s preview: %d row%s from %s" % (plan.title, len(plan.rows),
                                           "" if len(plan.rows) == 1 else "s", plan.source),
          file=out)
    for outcome, n in sorted(counts.items(), key=lambda kv: (-kv[1], kv[0])):
        print("  %-20s %4d" % (outcome, n), file=out)
    for r in plan.rows:
        p = by_row[r["row"]]
        if admin_files.outcome_class(p["outcome"]) != admin_files.PROCEEDS:
            print("    row %-4s %-24s %-20s %s" % (p["row"], p.get("key", ""), p["outcome"],
                                                  p.get("reason", "")), file=out)


def drive(plan, apply, confirm, show_people, out=sys.stdout):
    """The shared two-step flow every changing command uses (research R15)."""
    if plan.gate:
        print(PRODUCTION_GATE, file=out)
    for note in plan.notes:
        print(note, file=out)
    previewed = _preview(plan, show_people, out)
    if previewed is None:
        return EXIT_REFUSED
    code, by_row = previewed
    _print_counts(plan, by_row, out)

    if not apply:
        print("Nothing has been sent to Moodle.", file=out)
        print("To apply exactly this:  python scripts/ltct_admin.py %s --apply --confirm %s"
              % (" ".join(_quote(a) for a in plan.command), code), file=out)
        return EXIT_OK

    if confirm != code:
        print("Refused: the confirmation code does not match what Moodle and the file say "
              "now (%s, not %s). Something changed since the preview -- a column in the file, "
              "or a row's state in Moodle. Nothing was applied. Read the preview above and, "
              "if it is right, apply with its code." % (code, confirm), file=out)
        return EXIT_REFUSED

    tally = Counter()
    problems = []
    for r in plan.rows:
        p = by_row[r["row"]]
        outcome = p["outcome"]
        if admin_files.outcome_class(outcome) != admin_files.PROCEEDS:
            tally["not applied"] += 1
            continue
        if outcome in NOTHING_TO_DO:
            tally["already done"] += 1
            continue
        try:
            answer = plan.apply(r, outcome) or {}
        except MoodleError as e:
            tally["failed"] += 1
            problems.append((p, e.message))
            continue
        status = answer.get("status")
        if status == "done":
            tally["done"] += 1
        elif status == "already_done":
            tally["already done"] += 1
        else:
            tally["refused"] += 1
            problems.append((p, answer.get("reason") or "refused"))

    print("%s applied:" % plan.title, file=out)
    for label in ("done", "already done", "refused", "failed", "not applied"):
        if tally[label]:
            print("  %-20s %4d" % (label, tally[label]), file=out)
    for p, reason in problems:
        print("    row %-4s %-24s %s" % (p["row"], p.get("key", ""), reason), file=out)
    if tally["not applied"]:
        print("Rows marked in the preview were left alone; their reasons are above.", file=out)
    if problems:
        print("Run the same preview again to see where the refused rows stand.", file=out)
    return EXIT_REFUSED if (tally["refused"] or tally["failed"] or tally["not applied"]) \
        else EXIT_OK


def _quote(arg):
    return '"%s"' % arg if (" " in arg or not arg) else arg


# --- commands ------------------------------------------------------------------------------
def cmd_check(args, client, out):
    info = client.site_info() or {}
    print("site      %s" % info.get("sitename"), file=out)
    print("url       %s" % info.get("siteurl"), file=out)
    print("release   %s" % info.get("release"), file=out)
    print("user      %s" % info.get("username"), file=out)
    available = {f["name"] for f in info.get("functions", [])}
    missing = [f for f in ADMIN_FUNCTIONS if f not in available]
    for f in missing:
        print("  missing function: %s" % f, file=out)
    if missing:
        print("This token cannot call every administration function. Check it is an 'LTC "
              "administration' token and that local_ltuse is up to date.", file=out)
        return EXIT_CONFIG

    state = client.call("local_ltuse_admin_check") or {}
    settings = {s["name"]: s for s in state.get("settings", [])}
    status = EXIT_OK
    print("\nsettings:", file=out)
    for s in state.get("settings", []):
        print("  %-36s %s" % (s["name"], s["value"] if s.get("isset") else "(not set)"),
              file=out)
    for name, (want, why) in REQUIRED_SETTINGS.items():
        s = settings.get(name)
        if not s or not s.get("isset") or str(s.get("value")) != want:
            print("Refused: %s must be %s, or %s. Run \"python scripts/site_config.py apply\" "
                  "on the server, then check again." % (name, want, why), file=out)
            status = EXIT_CONFIG
    sameemail = settings.get("allowaccountssameemail")
    if sameemail and str(sameemail.get("value")) not in ("0", ""):
        print("Note: allowaccountssameemail is on. This tool still refuses a second account "
              "for one email, but core's own forms would not.", file=out)

    lacking = state.get("missingcapabilities", [])
    if lacking:
        print("\nYour account lacks these capabilities (the ltctadmin role holds them):",
              file=out)
        for cap in lacking:
            print("  %s" % cap, file=out)
        if PROTECTION_CAPABILITY in lacking:
            print("Without %s, protected rows will be refused." % PROTECTION_CAPABILITY,
                  file=out)
        status = max(status, EXIT_REFUSED)

    print("\npathways (spec 006):            %s"
          % ("installed" if state.get("pathways") else "not installed"), file=out)
    print("identity protection (spec 016): %s"
          % ("installed" if state.get("protection") else "not installed"), file=out)
    for lv in state.get("levels", []):
        print("  protection level %-10s %s" % (lv["level"], "available" if lv["available"]
                                               else "not available yet"), file=out)
    if not protection_ready(state):
        print(PRODUCTION_GATE, file=out)
    if status == EXIT_OK:
        print("\nReady.", file=out)
    return status


def protection_ready(state):
    """016 installed and every level available: when 002 R13's gate no longer applies."""
    levels = state.get("levels", [])
    return bool(state.get("protection")) and bool(levels) and all(lv["available"]
                                                                  for lv in levels)


def cmd_list(args, client_factory, out):
    if args.what == "organisations":
        for key in declared_organisations(args.site_dir):
            print(key, file=out)
        return EXIT_OK
    client = client_factory()
    params = {"what": args.what}
    if args.org:
        if args.org not in declared_organisations(args.site_dir):
            print("Refused: %r is not a declared organisation; run \"ltct_admin.py list "
                  "organisations\"." % args.org, file=out)
            return EXIT_REFUSED
        params["orgkey"] = args.org
    result = client.call("local_ltuse_admin_list", **params) or {}
    items = result.get("items", [])
    for item in items:
        extra = ("  [%s]" % item["category"]) if item.get("category") else ""
        print("%-48s %s%s" % (item["idnumber"], item.get("name", ""), extra), file=out)
    if not items:
        print("(none)", file=out)
    return EXIT_OK


def cmd_template(args, out):
    path = admin_files.guard_path(args.out)
    if path.exists():
        print("Refused: %s already exists. Choose a new file name, so a filled-in list is "
              "never overwritten." % path, file=out)
        return EXIT_REFUSED
    path.parent.mkdir(parents=True, exist_ok=True)
    # utf-8-sig: the BOM tells a spreadsheet the file is UTF-8, so accented names survive.
    with open(path, "w", newline="", encoding="utf-8-sig") as fh:
        csv.writer(fh).writerow(admin_files.template_header(args.kind))
    print("Wrote a blank %s file to %s: the header row only. Fill in one person per row."
          % (args.kind, path), file=out)
    return EXIT_OK


def not_built(name, task):
    def run(*_args, **_kwargs):
        print("\"%s\" is not built yet (spec 008, task %s). Nothing was sent." % (name, task),
              file=_kwargs.get("out", sys.stdout))
        return EXIT_CONFIG
    return run


# Commands whose implementation lands with its user story (specs/008-admin-tooling/tasks.md).
PENDING = {
    "intake": "T037", "enrol course": "T044", "enrol pathway": "T045",
    "enrol mirror": "T053", "unenrol": "T044", "suspend": "T050", "reactivate": "T050",
    "move": "T052", "managers": "T054", "mentors assign": "T061", "mentors end": "T061",
    "course-mentors": "T066", "summary": "T055",
}


# --- argument parsing ------------------------------------------------------------------------
def build_parser():
    common = argparse.ArgumentParser(add_help=False)
    common.add_argument("--show-people", action="store_true",
                        help="print names and emails instead of masked rows (your own "
                             "terminal only; never in a guided session)")
    common.add_argument("--apply", action="store_true",
                        help="make the changes the preview showed (needs --confirm)")
    common.add_argument("--confirm", metavar="CODE",
                        help="the confirmation code the preview printed")
    common.add_argument("--site-dir", default=str(SITE_DIR), help=argparse.SUPPRESS)

    # The shared options go on the leaf commands only, so they are written after the
    # command, as contracts/cli.md shows them. On a parent parser too, argparse would let a
    # leaf's defaults silently overwrite a value given before the command.
    parser = argparse.ArgumentParser(prog="ltct_admin.py", description=__doc__.split("\n")[0])
    sub = parser.add_subparsers(dest="command", metavar="COMMAND")
    sub.required = True

    def add(name, helptext, parent=sub, leaf=True):
        return parent.add_parser(name, help=helptext, parents=[common] if leaf else [])

    add("check", "is the server ready, and is your token?")
    p = add("list", "names you may use: organisations, cohorts, courses")
    p.add_argument("what", choices=["organisations", "cohorts", "courses"])
    p.add_argument("--org", metavar="K", help="only this organisation's")
    p = add("template", "write a blank file with the right columns")
    p.add_argument("--kind", required=True, choices=TEMPLATE_KINDS)
    p.add_argument("--out", required=True, metavar="PATH")
    p = add("intake", "bring new learners on")
    p.add_argument("file")

    p = add("enrol", "enrol a cohort", leaf=False)
    enrol = p.add_subparsers(dest="target", metavar="course|pathway|mirror")
    enrol.required = True
    q = add("course", "a cohort into one course", enrol)
    q.add_argument("--cohort", required=True, metavar="IDNUMBER")
    q.add_argument("--course", required=True, metavar="IDNUMBER")
    q = add("pathway", "a cohort into every course of a pathway", enrol)
    q.add_argument("--cohort", required=True, metavar="IDNUMBER")
    q.add_argument("--pathway", required=True, metavar="KEY")
    q = add("mirror", "an organisation into every shared course another is in", enrol)
    q.add_argument("--from", dest="from_org", required=True, metavar="K1")
    q.add_argument("--to", dest="to_org", required=True, metavar="K2")

    p = add("unenrol", "disable a cohort's enrolment in a course; history is kept")
    p.add_argument("--cohort", required=True, metavar="IDNUMBER")
    p.add_argument("--course", required=True, metavar="IDNUMBER")
    for name in ("suspend", "reactivate"):
        p = add(name, "%s accounts" % name)
        who = p.add_mutually_exclusive_group(required=True)
        who.add_argument("file", nargs="?")
        who.add_argument("--email", metavar="E")
    p = add("move", "move learners to another organisation, after a counted dry run")
    p.add_argument("file")
    p = add("managers", "managers cohorts and the mentors cohort")
    p.add_argument("file")
    p = add("mentors", "mentor relationships", leaf=False)
    mentors = p.add_subparsers(dest="action", metavar="assign|end")
    mentors.required = True
    q = add("assign", "assign mentors from a file", mentors)
    q.add_argument("file")
    q = add("end", "end all of one mentor's relationships", mentors)
    q.add_argument("--mentor", required=True, metavar="E")
    p = add("course-mentors", "record or remove one-course and cohort mentors")
    p.add_argument("file")
    p.add_argument("--remove", action="store_true")
    p = add("summary", "one organisation's cohorts and enrolments")
    p.add_argument("--org", required=True, metavar="K")
    p.add_argument("--out", metavar="PATH")
    return parser


def _command_name(args):
    if args.command == "enrol":
        return "enrol " + args.target
    if args.command == "mentors":
        return "mentors " + args.action
    return args.command


def main(argv=None, environ=None, client_factory=None, out=sys.stdout):
    environ = os.environ if environ is None else environ
    parser = build_parser()
    try:
        args = parser.parse_args(argv)
    except SystemExit as e:
        return EXIT_CONFIG if e.code else EXIT_OK
    if args.apply != bool(args.confirm):
        print("Use --apply and --confirm together: preview first, then apply with the code "
              "the preview printed.", file=out)
        return EXIT_CONFIG
    factory = client_factory or (lambda: default_client(environ))
    name = _command_name(args)
    try:
        if name == "template":
            return cmd_template(args, out)
        if name == "list":
            return cmd_list(args, lambda: _connect(factory, environ), out)
        if name in PENDING:
            return not_built(name, PENDING[name])(out=out)
        client = _connect(factory, environ)
        if name == "check":
            return cmd_check(args, client, out)
        raise ConfigError("unknown command %r" % name)
    except ConfigError as e:
        print(str(e), file=out)
        return EXIT_CONFIG
    except admin_files.PathRefused as e:
        print("Refused: %s" % e, file=out)
        return EXIT_REFUSED
    except admin_files.FileRefused as e:
        print("Refused: the file has problems, so nothing was sent to Moodle:", file=out)
        for problem in e.problems:
            print("  %s" % problem, file=out)
        return EXIT_REFUSED
    except MoodleError as e:
        # A plain sentence, never a stack trace (FR-011). The message names the fault and the
        # function; it never carries the token.
        print("Moodle refused: %s" % e.message, file=out)
        if e.errorcode:
            print("  (error code: %s)" % e.errorcode, file=out)
        return EXIT_CONFIG if name == "check" else EXIT_REFUSED


def _connect(factory, environ):
    # The environment is checked even when a test supplies the client, so the preflight's
    # refusals are the same in both.
    preflight(environ)
    return factory()


if __name__ == "__main__":
    sys.exit(main())
