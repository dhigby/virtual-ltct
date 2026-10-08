#!/usr/bin/env python3
"""Publish one course to Moodle. Build, verify, then push -- never push unverified.

The order is the point:

    1. build the payload          scripts/moodle_payload.py
    2. VERIFY it                  scripts/check_moodle_payload.py
    3. only then talk to Moodle   scripts/moodle_client.py

Step 2 is not a formality. Once a page is on a server learners can reach, a disclosure
failure has already happened; there is no equivalent of "rebuild the site". So this
refuses to push a payload that has not passed, and --force does not exist.

IDEMPOTENT. Every module carries an idnumber derived from its source file's number
("ltct:<slug>:01" for 01-lesson.md), so a republish updates what exists and creates only what is
new. The identity lives in Moodle, not in a repo state file -- see
moodle/local_ltuse/README.md. Running this twice must leave the course exactly as running
it once did, and the end-to-end check for that is simply to run it twice.

WHAT IT DOES NOT DO. It never reads learner data, never syncs Moodle back to the repo,
and never deletes anything: a module the course no longer has (its file renamed,
renumbered or removed) is HIDDEN, not removed, so a learner's completed attempt stays
readable and a mistaken publish is recoverable. Bringing the file back shows it again.

COMPLETION AND COMPETENCIES (spec 004). Every module carries the payload's one completion
rule, and the course's criteria are reconciled last, after hiding, by a plugin function
that diffs them and never clears them, so a recorded completion survives a republish. A
module whose completion was changed by hand is left alone and named. The course's
competencies and target level go to two course fields, and the competencies also to the
plugin's per-competency table; both are read back. Anything that needs a person is
listed after the summary and the exit code is 1, though the publish itself completed.

PLACEMENT (spec 002 R11, issue #108). Straight after the course is created or updated, and
on every publish, local_ltuse_place_course puts it in its category by idnumber, so a course
moved by hand goes back. A course listed in moodle/site/org-courses.yaml is only for one
organisation's people and goes to that organisation's category, ltct:org:<key>. A shared
course goes to LTC Pilots, ltct:pilots, until its delivery publish (course_stage.py at stage
8) moves it to LTC Published, ltct:published, where organisations can be enrolled. Nobody
names a category: the payload works it out, so a forgotten flag cannot misplace a course.

BADGE AND CERTIFICATE (spec 013). After completion, local_ltuse_set_course_recognition makes
or rewords the course's badge from the template site_config.py apply stored. Only a delivery
publish (course_stage.py at stage 8) activates the badge and makes the certificate activity;
a pilot issues nothing. The certificate's idnumber is never offered for retiring, because
retiring it is a step towards deleting it, which deletes every issued code.

PATHWAYS (spec 006). Straight after the competency map, local_ltuse_set_course_pathway gets
whether this publish is a delivery and the course's target level; the plugin works out which
pathways the course is on and announces what it joined or left. A delivered course with no
target level, or competency pathways that read back differently from the map, exits 1.

SECTION SUMMARIES (spec 007). Each section's summary is its lesson's estimated-time line, the
payload's `time_text`, so a learner sees how long a lesson takes before opening it. The plugin
writes a summary or a name only when it differs, so an unchanged republish writes no section.
A server whose local_ltuse predates spec 007 refuses the summary after the course, placement,
competency and pathway calls have written, so the plugin is deployed first; see
specs/007-learner-experience/contracts/update-sections.md.

Environment:
    MOODLE_URL, MOODLE_TOKEN    see scripts/moodle_client.py

Usage:
  python scripts/publish_moodle.py --slug <slug> [--dry-run]
"""
import argparse
import json
import pathlib
import re
import subprocess
import sys
import tempfile
from html import unescape

import yaml

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
from course_stage import branch_slug  # noqa: E402
from moodle_client import MoodleClient, MoodleError  # noqa: E402
from moodle_payload import MODULES, Payload, report_images, write_payload  # noqa: E402
from moodle_xml import quiz_xml  # noqa: E402

REPO = pathlib.Path(__file__).resolve().parent.parent
MODULE_TOKEN_RE = re.compile(r"@@MODULE:([^@]+)@@")
TAG_RE = re.compile(r"<[^>]+>")
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


SERVER_PREDATES_007 = ("the server's local_ltuse predates spec 007; deploy it before "
                       "publishing")


def ensure_sections(client, courseidnumber, count, sections):
    """Make sure the course has `count` numbered sections, named to match the lessons.

    One call, to local_ltuse. This was originally three calls to local_wsmanagesections,
    which covers sections over REST and which the plan deliberately did not duplicate --
    but it could not be installed on this Moodle, so the plugin grew an update_sections
    function over core's own course_create_sections_if_missing() and
    course_update_section(). The publisher now depends on nothing but Moodle core and our
    own plugin.

    Idempotent: sections that already exist are renamed, never appended to, and the plugin
    writes a name or a summary only when it differs from what is stored.

    Spec 007: each section's summary is its lesson's estimated-time line, the manifest's
    `time_text`, sent only when the section has one ("" clears a quiz's). A manifest from
    before spec 007 has none, sends no summary key, and the plugin leaves summaries alone.

    Returns (summaries sent, the plugin's reply). A plugin older than spec 007 refuses the
    unknown key as `invalidparameter`; the key's name is only in debuginfo, which Moodle
    sends only with debugging on, so the errorcode is the test. That exits 1 with a hint.
    """
    items = []
    for s in sections:
        item = {"number": s["number"], "name": s["name"]}
        if "time_text" in s:
            item["summary"] = s["time_text"]
        items.append(item)
    sent = sum(1 for item in items if "summary" in item)
    try:
        reply = client.call("local_ltuse_update_sections", courseidnumber=courseidnumber,
                            numsections=count, sections=items)
    except MoodleError as e:
        if (e.function == "local_ltuse_update_sections"
                and e.errorcode == "invalidparameter" and sent):
            print("\n%s:\n  %s" % (SERVER_PREDATES_007, e), file=sys.stderr)
            raise SystemExit(1)
        raise
    return sent, reply


# No groups: shared courses are open across organisations (spec 002 R3, amended
# 2026-10-02), and groups never separate organisations. Sent on update too, so a course
# published with separate groups opens on its next publish. groupmodeforce is never sent:
# a teacher may still use groups in one activity for teaching.
GROUPMODE_NONE = 0

# Completion is switched on for every course the repo publishes (spec 004, R4), on update
# too, so a course published before spec 004 gains it on its next publish. The site-level
# switch is declared in moodle/site/settings/completion.yaml.
COMPLETION_ENABLED = 1

# Hidden sections "Hide completely" (1), not "Show section names only" (0), so a learner
# never sees even the name of the Retired section that holds modules the repo no longer
# has. Sent on update too, since the site default only applies to new courses.
HIDE_SECTIONS_COMPLETELY = [{"name": "hiddensections", "value": "1"}]

# "Show activity reports" off. With it on, a mentor (spec 003, research R2) would see every
# assignment submission and the learner's logs through core's reports. The site default
# (moodle/site/settings/mentoring.yaml) covers only new courses and any editing teacher can
# turn a course's own setting on, so every publish resets it.
SHOW_REPORTS_OFF = 0

# The two course custom fields declared in moodle/site/course-fields.yaml (spec 004, R11).
FIELD_COMPETENCIES = "ltct_competencies"
FIELD_TARGET_LEVEL = "ltct_target_level"

# The competencies.yaml category that holds no real competency ("Uncategorized"): it gets
# no row in Moodle's per-competency table, so its names are never sent there.
COMPETENCIES_FILE = REPO / "competencies.yaml"
META_CATEGORY = "Meta"

# Where core_course_create_courses puts a new course: Moodle's install-time default category.
# It is only a waypoint for a course created hidden; ensure_placement() moves the course
# to the category its payload names before any content is sent (issue #108). Core finds a
# category by idnumber only for moodle/category:manage at system context, which the
# publisher does not hold, so the course cannot be created in its real category directly.
CREATE_CATEGORY = 1


def unique_competencies(manifest):
    """The frontmatter competencies, each once, in first-seen order.

    The plugin stores one row per competency (array_unique), so a name listed twice
    would otherwise read back once and look like a difference.
    """
    return list(dict.fromkeys(manifest.get("competencies") or []))


def split_meta(names):
    """(kept, skipped): the course's competencies without the Meta category's names."""
    cats = yaml.safe_load(COMPETENCIES_FILE.read_text(encoding="utf-8")) or {}
    meta = set(cats.get(META_CATEGORY) or [])
    return [n for n in names if n not in meta], [n for n in names if n in meta]


def course_fields(manifest):
    """The course custom field values, as Moodle's course web services take them.

    Each competency in brackets, in frontmatter order, so a "contains [Translation]"
    filter cannot also match "Translation Tools" (R11). The field mirrors the
    frontmatter, Meta names included; only the per-competency table drops them.
    """
    return {
        FIELD_COMPETENCIES: " ".join("[%s]" % n for n in unique_competencies(manifest)),
        FIELD_TARGET_LEVEL: manifest.get("target_outcome_level") or "",
    }


def ensure_course(client, manifest, problems=None):
    """Find the course by idnumber, or create it. Returns (course id, created).

    A new course is created in CREATE_CATEGORY and ensure_placement() moves it straight on,
    since only the plugin can resolve a category's idnumber for the publisher.

    Moodle drops a custom field it does not know, or one the caller may not edit, with no
    warning and no error, so the values are read back and any that did not land is
    appended to `problems`: the publish carries on, and exits 1 after its summary. A
    warning from core_course_update_courses is a failed update, not a note, and raises.
    """
    fields = course_fields(manifest)
    customfields = [{"shortname": k, "value": v} for k, v in fields.items()]
    existing = client.course_by_idnumber(manifest["idnumber"])
    if existing:
        result = client.call("core_course_update_courses", courses=[{
            "id": existing["id"],
            "fullname": manifest["title"],
            "summary": manifest["summary_html"],
            "summaryformat": 1,
            "groupmode": GROUPMODE_NONE,
            "showreports": SHOW_REPORTS_OFF,
            "enablecompletion": COMPLETION_ENABLED,
            "customfields": customfields,
            "courseformatoptions": HIDE_SECTIONS_COMPLETELY,
        }])
        # Unlike create, update has no transaction: a course it could not change comes
        # back as a warning, and the call itself "succeeds".
        warnings = result.get("warnings") if isinstance(result, dict) else None
        if warnings:
            raise MoodleError("core_course_update_courses", {"message": "; ".join(
                "%s (%s)" % (w.get("message", ""), w.get("warningcode", ""))
                for w in warnings)})
        courseid, created = int(existing["id"]), False
    else:
        result = client.call("core_course_create_courses", courses=[{
            "fullname": manifest["title"],
            "shortname": manifest["slug"],
            "idnumber": manifest["idnumber"],
            "categoryid": CREATE_CATEGORY,
            "summary": manifest["summary_html"],
            "summaryformat": 1,
            # Sections are the lessons, so a learner sees the course shape on one page.
            "format": "topics",
            "numsections": max(len(manifest["sections"]), 1),
            "visible": 0,          # created hidden; a human decides when learners see it
            "groupmode": GROUPMODE_NONE,
            "showreports": SHOW_REPORTS_OFF,
            "enablecompletion": COMPLETION_ENABLED,
            "courseformatoptions": HIDE_SECTIONS_COMPLETELY,
            "customfields": customfields,
        }])
        if client.dry_run:
            return 0, True
        courseid, created = int(result[0]["id"]), True

    if client.dry_run:
        return courseid, created
    back = client.course_by_idnumber(manifest["idnumber"]) or {}
    stored = {f.get("shortname"): f.get("valueraw", f.get("value"))
              for f in back.get("customfields") or []}
    for name, value in fields.items():
        if (stored.get(name) or "") != value:
            note = ("course field %s reads back as %r, not %r -- is it declared "
                    "(site_config.py apply) and may the publisher edit it?"
                    % (name, stored.get(name), value))
            if problems is None:
                print("  WARNING   %s" % note)
            else:
                problems.append(note)
    return courseid, created


def ensure_placement(client, manifest):
    """Put the course in the category its payload names (spec 002 R11, issue #108).

    An organisation-only course goes to ltct:org:<key>; a shared one to ltct:pilots, then to
    ltct:published from its delivery publish on. On every publish, after create and update
    alike, so the placement is re-asserted each time and a course moved by hand goes back.
    The plugin moves the course only when it is elsewhere, and says so; a refusal (a
    category that does not exist, or is not one a course may be placed in) is a MoodleError
    and stops the publish before any content is sent.
    """
    category = manifest["placement"]["category_idnumber"]
    result = client.call("local_ltuse_place_course", courseidnumber=manifest["idnumber"],
                         categoryidnumber=category)
    if client.dry_run:
        print("  placement dry-run: %s" % category)
    elif (result or {}).get("moved"):
        print("  placement moved to %s" % category)
    else:
        print("  placement %s (already there)" % category)


def ensure_competencies(client, manifest, courseid, problems):
    """Replace the course's rows in Moodle's per-competency table (spec 004, R15)."""
    kept, skipped = split_meta(unique_competencies(manifest))
    if skipped:
        print("  competencies  skipped %s: %s" % (META_CATEGORY, ", ".join(skipped)))
    result = client.call("local_ltuse_set_course_competencies",
                         courseid=courseid, competencies=kept)
    if client.dry_run:
        print("  competencies  dry-run: %d to set" % len(kept))
        return
    print("  competencies  %d added, %d removed"
          % (len(result.get("added") or []), len(result.get("removed") or [])))
    back = result.get("competencies") or []
    if set(back) != set(kept):
        problems.append("competencies read back differ from those sent:\n"
                        "              sent:      %s\n"
                        "              read back: %s"
                        % ("; ".join(kept) or "(none)", "; ".join(back) or "(none)"))


TARGET_LEVEL_RE = re.compile(r"^([1-4]) - ")


def target_level(manifest):
    """The manifest's target_outcome_level as the plugin takes it: 1-4, 0 when absent.

    check_moodle_payload.py has already proved a present label is a course_target_levels
    label verbatim, so its leading digit is the level (spec 006, contracts/publish.md).
    """
    m = TARGET_LEVEL_RE.match(str(manifest.get("target_outcome_level") or ""))
    return int(m.group(1)) if m else 0


def ensure_pathway(client, manifest, courseid, problems):
    """Tell the plugin the two facts a pathway needs (spec 006, contracts/publish.md).

    Straight after ensure_competencies, so no pathway is computed from a stale map. The
    plugin decides what the course is on; this only reports it and compares the
    competency pathways read back with the map it just sent. A refusal is a MoodleError
    and stops the publish; a difference goes into `problems`, so the publish completes and
    exits 1.
    """
    delivery = 1 if manifest["recognition"]["delivery"] else 0
    level = target_level(manifest)
    if delivery and not level:
        # The plugin refuses this pair, so it is not sent: the course stays as it was.
        print("  pathways  not sent: delivered with no target_outcome_level")
        problems.append("delivered but declares no target_outcome_level, so it is in no "
                        "pathway while COVERAGE.md lists it")
        return
    result = client.call("local_ltuse_set_course_pathway",
                         courseid=courseid, delivery=delivery, targetlevel=level)
    if client.dry_run:
        print("  pathways  dry-run: delivery %d, target level %d" % (delivery, level))
        return
    result = result or {}
    kept, _ = split_meta(unique_competencies(manifest))
    stored = (result.get("delivery"), result.get("targetlevel"))
    if stored != (delivery, level):
        problems.append("pathway row reads back as delivery %s, target level %s; sent "
                        "delivery %d, target level %d" % (stored + (delivery, level)))
    if not delivery:
        print("  pathways  pilot: in no pathway until stage 8")
        return
    if not result.get("visible"):
        print("  pathways  hidden: joins %d pathways when the course is shown" % len(kept))
        return
    line = "  pathways  in %d" % len(result.get("pathways") or [])
    if result.get("added"):
        line += "; joined: %s" % ", ".join(result["added"])
    if result.get("removed"):
        line += "; left: %s" % ", ".join(result["removed"])
    print(line)
    back = [p.get("competency") for p in result.get("pathways") or []]
    if set(back) != set(kept):
        problems.append("competency pathways read back differ from the competencies sent:\n"
                        "              sent:      %s\n"
                        "              read back: %s"
                        % ("; ".join(kept) or "(none)", "; ".join(back) or "(none)"))


def note_completion(results, idnumber, source, result):
    """Remember the plugin's completion answer for one module call."""
    value = (result or {}).get("completion", "")
    if results.get(idnumber, (None, ""))[1] != "differs":   # pass 2 never hides one
        results[idnumber] = (source, value)
    return value


def ensure_recognition(client, manifest, problems):
    """The course's badge, and on delivery its certificate (spec 013, contracts/publish.md).

    Straight after the completion criteria, so both see the final ones. A refusal (wording,
    templates not applied) is a MoodleError and stops the publish; a warning goes into
    `problems`, so the publish completes and exits 1.
    """
    recognition = manifest["recognition"]
    params = {"courseidnumber": manifest["idnumber"], "delivery": recognition["delivery"]}
    if recognition.get("certificate"):
        params["certificateidnumber"] = recognition["certificate"]["idnumber"]
    result = client.call("local_ltuse_set_course_recognition", **params)
    pilot = "" if recognition["delivery"] else " (pilot: no badge is issued until stage 8)"
    if client.dry_run:
        print("  recognition  dry-run: badge%s%s" % (
            ", activated; certificate" if recognition["delivery"] else "", pilot))
        return
    result = result or {}
    line = "  recognition  badge %s, %s" % (result.get("badge"), result.get("status"))
    if recognition["delivery"]:
        line += "; certificate %s" % result.get("certificate")
    print(line + pilot)
    for w in result.get("warnings") or []:
        problems.append("recognition %s: %s" % (w.get("code"), w.get("message")))


def publish(client, payload_dir):
    manifest = json.loads((payload_dir / "manifest.json").read_text(encoding="utf-8"))
    if not manifest["publishable"]:
        raise SystemExit("refusing to publish %s: %s"
                         % (manifest["slug"], manifest["blocked_reason"]))

    # Anything that needs a person to decide, though the publish itself completed: each
    # is printed after the summary, and main() then exits 1.
    problems = []
    courseid, created = ensure_course(client, manifest, problems=problems)
    print("  course    %s (%s)" % (manifest["idnumber"],
                                   "created, hidden" if created else "updated"))
    ensure_placement(client, manifest)
    ensure_competencies(client, manifest, courseid, problems)
    ensure_pathway(client, manifest, courseid, problems)

    sent_summaries, reply = ensure_sections(client, manifest["idnumber"],
                                            len(manifest["sections"]), manifest["sections"])
    print("  sections  %d" % len(manifest["sections"]))
    # A dry run counts what it would send; a publish, what the plugin actually wrote.
    print("  summaries %d" % (sent_summaries if client.dry_run
                              else (reply or {}).get("summaries", sent_summaries)))
    if client.dry_run:
        for s in manifest["sections"]:
            if "time_text" in s:
                text = unescape(TAG_RE.sub("", s["time_text"])).strip()
                print("    summary %d  %s" % (s["number"], text or "(none: cleared)"))

    # --- what is already on the server ---------------------------------------------------
    # Existing course-module ids and each page's files, from one read call. This is what
    # lets a republish send only what changed (FR-016): an unchanged page arrives
    # byte-identical to what is stored, so the plugin writes nothing, and an unchanged
    # image is never re-sent, so the Moodle app never re-downloads it. A dry run makes no
    # read calls either, and behaves as a first publish.
    server = {} if client.dry_run else {
        m["idnumber"]: m for m in (client.manifest(manifest["idnumber"]) or {})
        .get("modules", [])}
    cmids = {k: m["cmid"] for k, m in server.items()}
    this_run = {mod["idnumber"] for s in manifest["sections"] for mod in s["modules"]}
    this_run |= {q["idnumber"] for q in manifest["quizzes"]}
    records = manifest.get("assets") or {}

    def module_url(cmid):
        return "%s/mod/page/view.php?id=%s" % (client.url, cmid)

    # --- pass 1: every page and quiz, with every link it can already resolve ----------------
    # A sibling that already exists on the server is resolved now, so the page's first
    # send is its final HTML. Leaving it tokenised would never match the stored, resolved
    # HTML, and every page with a link would be rewritten twice on every publish. Only a
    # link to a module created in this run waits for pass 2; one to a module this course
    # no longer has is dead text now, as pass 2 would have made it.
    def resolve_known(html):
        def sub(m):
            target = m.group(1)
            if target in cmids:
                return module_url(cmids[target])
            return m.group(0) if target in this_run else "#"
        return MODULE_TOKEN_RE.sub(sub, html)

    page_bodies, counts = {}, {"created": 0, "updated": 0, "unchanged": 0}
    sent = kept = sent_bytes = 0
    # idnumber -> (source, the plugin's answer: set | unchanged | differs | '')
    completion = {}

    def completion_note(rule, answer):
        return rule if client.dry_run else "%s %s" % (rule, answer or "not applied")
    for section in manifest["sections"]:
        for mod in section["modules"]:
            if mod["kind"] != "page":
                continue
            html = (payload_dir / mod["html_file"]).read_text(encoding="utf-8")
            if not client.dry_run:
                html = resolve_known(html)
            page_bodies[mod["idnumber"]] = (mod, section["number"], html)

            # Split the page's images three ways against the server: unchanged (same name
            # and hash: keep), new or changed (upload), gone from the payload (send
            # nothing; the save drops it). With no server file list -- a new page, an
            # older plugin, or a dry run -- every image is sent, as before.
            on_server = server.get(mod["idnumber"], {}).get("files")
            ours = {name: (records.get(name) or {}).get("sha1") for name in mod["assets"]}
            if on_server is None or mod["idnumber"] not in server:
                to_send, to_keep, removed = list(mod["assets"]), [], []
            else:
                theirs = {f["filename"]: f["contenthash"] for f in on_server}
                to_keep = [n for n in mod["assets"] if ours[n] and theirs.get(n) == ours[n]]
                to_send = [n for n in mod["assets"] if n not in to_keep]
                removed = [n for n in theirs if n not in ours]

            itemid = 0
            for asset in to_send:
                itemid = client.upload(payload_dir / "assets" / asset, itemid=itemid)
                sent_bytes += (payload_dir / "assets" / asset).stat().st_size
            sent += len(to_send)
            kept += len(to_keep)

            params = dict(courseidnumber=manifest["idnumber"], idnumber=mod["idnumber"],
                          name=mod["name"], content=html, section=section["number"],
                          contentitemid=itemid, completion=mod["completion"])
            # Omitting both when no file changes is also what keeps an older plugin,
            # which knows neither parameter, working.
            if mod["idnumber"] in server and on_server is not None and (to_send or removed):
                params.update(syncfiles=True, keepfiles=to_keep)
            result = client.call("local_ltuse_create_page", **params)

            if client.dry_run:
                outcome = "dry-run"
            else:
                cmids[mod["idnumber"]] = result["cmid"]
                outcome = result.get("outcome") or \
                    ("created" if result["created"] else "updated")
                counts[outcome] = counts.get(outcome, 0) + 1
            answer = "" if client.dry_run else \
                note_completion(completion, mod["idnumber"], mod["source"], result)
            print("    page    %-46s %-9s %d sent, %d kept; completion %s"
                  % (mod["source"], outcome, len(to_send), len(to_keep),
                     completion_note(mod["completion"], answer)))

    for quiz in manifest["quizzes"]:
        xml = quiz_xml(quiz)
        imported = client.call(
            "local_ltuse_import_questions",
            courseidnumber=manifest["idnumber"], category=quiz["category"], xml=xml)
        result = client.call(
            "local_ltuse_create_quiz",
            courseidnumber=manifest["idnumber"], idnumber=quiz["idnumber"],
            name=quiz["name"], category=quiz["category"], section=quiz["section"],
            thresholdpct=quiz["threshold_pct"] or 0, completion=quiz["completion"])
        answer = ""
        if not client.dry_run:
            cmids[quiz["idnumber"]] = result["cmid"]
            answer = note_completion(completion, quiz["idnumber"], quiz["source"], result)
        print("    quiz    %-46s %s question(s); completion %s" % (
            quiz["source"],
            len(quiz["questions"]) if client.dry_run else imported["count"],
            completion_note(quiz["completion"], answer)))

    # --- pass 2: links to modules created in this run, now that they have a cmid ---------
    # Two passes because a lesson may link forward to one that did not exist yet. Pass 1
    # resolved everything else, so on a republish this usually sends nothing.
    rewritten = 0
    for idnumber, (mod, sectionnum, html) in page_bodies.items():
        if "@@MODULE:" not in html:
            continue
        resolved = MODULE_TOKEN_RE.sub(
            lambda m: module_url(cmids[m.group(1)]) if m.group(1) in cmids else "#", html)
        if resolved == html:
            continue
        # The rule is sent again, so this call can never be read as "leave it untracked".
        result = client.call("local_ltuse_create_page",
                             courseidnumber=manifest["idnumber"], idnumber=idnumber,
                             name=mod["name"], content=resolved, section=sectionnum,
                             completion=mod["completion"])
        if not client.dry_run:
            note_completion(completion, idnumber, mod["source"], result)
        rewritten += 1
    if rewritten:
        print("  links     %s in %d page(s)"
              % ("would be resolved" if client.dry_run else "resolved", rewritten))

    # --- the course discussion (spec 012, FR-015) -----------------------------------------
    # Created if absent; otherwise only its group mode is set (no groups, spec 002 R3), so
    # a hand change is undone on every publish. Its posts are never written. Its idnumber starts ltct:<slug>: like
    # a module's, so the retire step below must be told it belongs to this run.
    discussion = manifest["discussion"]
    result = client.call(
        "local_ltuse_ensure_discussion",
        courseidnumber=manifest["idnumber"], idnumber=discussion["idnumber"],
        name=discussion["name"], intro=discussion["intro_html"])
    print("    forum   %-46s %s" % (
        discussion["idnumber"],
        "dry-run" if client.dry_run else ("created" if result["created"] else "updated")))
    if not client.dry_run and result.get("courseforced"):
        print("    WARNING the course forces a group mode, which would wall the course forum."
              "\n            Turn off Course settings > Groups > Force group mode.")

    # --- last: retire what the course no longer has -------------------------------------
    # A lesson renamed, renumbered or removed in the repo leaves its old module in Moodle
    # under an idnumber this run did not produce. Retired, never deleted: hidden and moved
    # into one hidden "Retired" section at the end, because a learner's attempt and grade
    # live on it and a mistaken publish must stay recoverable, and because hidden in place
    # it would sit between lessons for anyone walking the course as a teacher. Done after
    # everything else, so a publish that fails part-way never retires the old version
    # before its replacement exists. Only this course's modules (the "ltct:<slug>:"
    # prefix); the question bank and anything made by hand in Moodle are left alone. One
    # retired by an earlier publish is not sent again.
    prefix = manifest["idnumber"] + ":"     # every module is ltct:<slug>:<key>
    # The certificate is never retired, on a pilot publish either: retiring is a step
    # towards deleting it, which deletes every issued code (spec 013 R14).
    produced = this_run | {discussion["idnumber"], manifest["idnumber"] + ":certificate"}
    stale = [k for k in server if k.startswith(prefix) and k not in produced]
    to_retire = [k for k in stale
                 if not (server[k].get("retired") and not server[k].get("visible", 1))]
    if to_retire:
        result = client.call("local_ltuse_hide_modules",
                             courseidnumber=manifest["idnumber"], idnumbers=to_retire)
        for r in result["modules"]:
            print("  retire    %-46s %s" % (r["idnumber"], server[r["idnumber"]]["modname"]))

    # --- then: the course's completion criteria, one per visible module ---------------------
    # After hiding, so a module the course no longer has is never left as a criterion. The
    # plugin diffs the criteria against the visible modules and never clears them, so a
    # learner's recorded course completion survives a republish (spec 004, R3).
    criteria = client.call("local_ltuse_set_course_completion",
                           courseidnumber=manifest["idnumber"])
    if client.dry_run:
        print("  completion  dry-run: every visible module, all required")
    else:
        criteria = criteria or {}
        line = "  completion  %d added, %d removed" % (
            len(criteria.get("added") or []), len(criteria.get("removed") or []))
        if criteria.get("aggregation") == "set":
            line += "; set to require all"
        if criteria.get("reaggregated"):
            line += "; %d incomplete learner record(s) rechecked" % criteria["reaggregated"]
        print(line)
        if criteria.get("othercriteria"):
            print("  completion  NOTE: the course also has criteria set by hand in Moodle; "
                  "they were left alone")
    ensure_recognition(client, manifest, problems)
    not_applied = sorted(k for k, (_, v) in completion.items() if v == "")
    if not_applied:
        print("  completion  NOTE: not applied to %d module(s) -- is completion on for the "
              "site and the course?" % len(not_applied))
    for idn, (source, _) in sorted(completion.items()):
        if completion[idn][1] == "differs":
            problems.append("completion of %s (%s) differs from the rule, and was left "
                            "as it is: a person must decide whether to change it in "
                            "Moodle" % (idn, source))

    if client.dry_run:
        print("  pages: dry-run; images: %d would be sent (%d KB)"
              % (sent, round(sent_bytes / 1024)))
    else:
        print("  pages: %d created, %d updated, %d unchanged; images: %d sent (%d KB), %d kept"
              % (counts["created"], counts["updated"], counts["unchanged"],
                 sent, round(sent_bytes / 1024), kept))
        if to_retire:
            print("  retired: %d module(s) no longer in the repo, moved to the hidden "
                  "Retired section" % len(to_retire))

    return manifest, cmids, problems


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--slug", required=True, help="course folder name under modules/")
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
    report_images(manifest)

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
        published, cmids, problems = publish(client, payload_dir)
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

    # The publish completed; these need a person. Exit 1 so a script or CI notices.
    if problems:
        print("\nNEEDS A DECISION (%d):" % len(problems))
        for p in problems:
            print("  - %s" % p)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
