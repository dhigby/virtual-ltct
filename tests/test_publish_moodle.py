"""Unit tests for scripts/publish_moodle.py. Run: python -m pytest tests/

Groups: course creation (spec 002), the per-page diff against the server (spec 009, US3),
completion (spec 004, US1) and course competencies (spec 004, US5). No network: fake
clients record every call; the diff tests answer local_ltuse_get_course_manifest from a
canned server state, with the payload written by hand into a temp dir.
"""
import contextlib, hashlib, io, json, pathlib, sys, tempfile, unittest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import publish_moodle as pm

COURSE = "ltct:demo"
URL = "https://moodle.example.org"


SHARED = {"org_only": False, "category_idnumber": None}
ORG_ONLY = {"org_only": True, "category_idnumber": "ltct:org:fixture-north"}


def mid(name):
    return "ltct:demo:%s" % name


class FakeClient:
    def __init__(self, server_modules, dry_run=False):
        self.dry_run = dry_run
        self.url = URL
        self.calls = []
        self.uploads = []
        self.server = {m["idnumber"]: m for m in server_modules}
        self.next_cmid = 900
        # What the course's custom fields read back as. `drop` names fields the server
        # silently ignores, as Moodle does for an unknown or locked one.
        self.customfields, self.drop = {}, set()
        self.update_warnings = []
        # The plugin's completion answer per module idnumber; by default 'set' on create.
        self.completion = {}
        self.course_completion = {"added": [], "removed": [], "aggregation": "unchanged",
                                  "reaggregated": 0, "othercriteria": False}
        self.competencies_back = None     # None: the server stores what it was sent
        self.recognition = {"badge": "unchanged", "status": "inactive", "certificate": "none",
                            "warnings": []}
        self.placed = {"moved": False}    # local_ltuse_place_course's answer (spec 002 R11)

    def manifest(self, idnumber):
        return self.call("local_ltuse_get_course_manifest", idnumber=idnumber)

    def course_by_idnumber(self, idnumber):
        # As MoodleClient does: a dry run's stub answer carries no course.
        result = self.call("core_course_get_courses_by_field", field="idnumber", value=idnumber)
        return None if self.dry_run else result

    def upload(self, path, itemid=0):
        self.calls.append(("upload.php", {"path": str(path), "itemid": itemid}))
        self.uploads.append(pathlib.Path(path).name)
        return itemid or 4242

    def call(self, function, **params):
        self.calls.append((function, params))
        if self.dry_run:
            return {"dry_run": True}
        if function == "core_course_get_courses_by_field":
            return {"id": 7, "customfields": [
                {"shortname": k, "value": v, "valueraw": v, "type": "text", "name": k}
                for k, v in self.customfields.items()]}
        if function in ("core_course_update_courses", "core_course_create_courses"):
            for f in params["courses"][0].get("customfields", []):
                if f["shortname"] not in self.drop:
                    self.customfields[f["shortname"]] = f["value"]
            if function == "core_course_create_courses":
                return [{"id": 7, "shortname": params["courses"][0]["shortname"]}]
            return {"warnings": self.update_warnings}
        if function == "local_ltuse_set_course_completion":
            return dict(self.course_completion)
        if function == "local_ltuse_set_course_recognition":
            return dict(self.recognition)
        if function == "local_ltuse_place_course":
            return dict(self.placed)
        if function == "local_ltuse_set_course_competencies":
            sent = list(params["competencies"])
            back = sent if self.competencies_back is None else self.competencies_back
            return {"added": sent, "removed": [], "competencies": back}
        if function == "local_ltuse_get_course_manifest":
            return {"modules": list(self.server.values())}
        if function == "local_ltuse_create_page":
            m = self.server.get(params["idnumber"])
            if m is None:
                self.next_cmid += 1
                self.server[params["idnumber"]] = m = {
                    "idnumber": params["idnumber"], "cmid": self.next_cmid,
                    "modname": "page", "files": []}
                return {"cmid": m["cmid"], "created": True, "outcome": "created",
                        "completion": self.completion.get(params["idnumber"], "set")}
            sends_files = params.get("syncfiles") or params.get("contentitemid")
            same = m.get("content") == params["content"] and not sends_files
            m["content"] = params["content"]
            return {"cmid": m["cmid"], "created": False,
                    "outcome": "unchanged" if same else "updated",
                    "completion": self.completion.get(params["idnumber"], "unchanged")}
        if function == "local_ltuse_hide_modules":
            out = []
            for idn in params["idnumbers"]:
                m = self.server[idn]
                done = m.get("retired") and not m.get("visible", 1)
                out.append({"idnumber": idn, "cmid": m["cmid"],
                            "outcome": "alreadyretired" if done else "retired"})
                m["visible"], m["retired"] = 0, True
            return {"modules": out}
        if function == "local_ltuse_ensure_discussion":
            created = params["idnumber"] not in self.server
            m = self.server.setdefault(params["idnumber"], {
                "idnumber": params["idnumber"], "cmid": 960, "modname": "forum", "files": []})
            return {"cmid": m["cmid"], "created": created, "groupmode": 0, "courseforced": False}
        if function == "local_ltuse_import_questions":
            return {"count": 1}
        if function == "local_ltuse_create_quiz":
            m = self.server.setdefault(params["idnumber"], {
                "idnumber": params["idnumber"], "cmid": 950, "modname": "quiz", "files": []})
            return {"cmid": m["cmid"],
                    "completion": self.completion.get(params["idnumber"], "set")}
        return {}

    def page_calls(self):
        return [p for f, p in self.calls if f == "local_ltuse_create_page"]

    def hide_calls(self):
        return [p for f, p in self.calls if f == "local_ltuse_hide_modules"]

    def calls_to(self, function):
        return [p for f, p in self.calls if f == function]

    def writes(self):
        return [f for f, _ in self.calls
                if f.startswith("local_ltuse_") and f != "local_ltuse_get_course_manifest"]


class PublishBase(unittest.TestCase):
    """A two-page payload written by hand into a temp dir, and the helpers to publish it."""

    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.dir = pathlib.Path(self._tmp.name)
        (self.dir / "pages").mkdir()
        (self.dir / "assets").mkdir()
        self.images = {"a.png": b"image a", "b.png": b"image b", "c.png": b"image c"}
        for name, data in self.images.items():
            (self.dir / "assets" / name).write_bytes(data)
        self.pages = {
            "01-one.md": ('<p>One <img src="@@PLUGINFILE@@/a.png"> '
                          '<img src="@@PLUGINFILE@@/b.png"></p>'
                          '<a href="@@MODULE:%s@@">next</a>' % mid("02-two.md"), ["a.png", "b.png"]),
            "02-two.md": ('<p>Two <img src="@@PLUGINFILE@@/c.png"></p>', ["c.png"]),
        }
        self.write_manifest()

    def tearDown(self):
        self._tmp.cleanup()

    def write_manifest(self, quizzes=(), competencies=("Translation Tools",),
                       level="2 - With Assistance", delivery=False, placement=None):
        sections = []
        for n, (source, (html, assets)) in enumerate(sorted(self.pages.items()), start=1):
            stem = source[:-3]
            (self.dir / "pages" / ("%s.html" % stem)).write_text(html, encoding="utf-8")
            sections.append({"number": n, "name": stem, "modules": [{
                "kind": "page", "idnumber": mid(source), "name": stem, "source": source,
                "html_file": "pages/%s.html" % stem, "assets": assets,
                "completion": "view"}]})
        for q in quizzes:
            sections.append({"number": len(sections) + 1, "name": q["name"], "modules": [{
                "kind": "quiz", "idnumber": q["idnumber"], "name": q["name"],
                "source": q["source"], "completion": q["completion"]}]})
        manifest = {
            "slug": "demo", "idnumber": COURSE, "title": "Demo", "summary_html": "",
            "publishable": True, "blocked_reason": None, "sections": sections,
            "quizzes": list(quizzes), "withheld": [], "notes": [],
            "completion": "all", "competencies": list(competencies),
            "target_outcome_level": level,
            "discussion": {"idnumber": COURSE + ":discussion", "name": "Course discussion",
                           "intro_html": ""},
            "recognition": {"delivery": delivery, **(
                {"certificate": {"idnumber": "%s:certificate" % COURSE}} if delivery else {})},
            "assets": {n: {"sha1": hashlib.sha1(d).hexdigest(), "bytes": len(d)}
                       for n, d in self.images.items()},
            "placement": placement or SHARED,
        }
        (self.dir / "manifest.json").write_text(json.dumps(manifest), encoding="utf-8")

    def resolved(self, html, cmids):
        return pm.MODULE_TOKEN_RE.sub(
            lambda m: "%s/mod/page/view.php?id=%s" % (URL, cmids[m.group(1)])
            if m.group(1) in cmids else "#", html)

    def server_as_published(self, **overrides):
        """The server state after an earlier publish of exactly this payload."""
        cmids = {mid("01-one.md"): 101, mid("02-two.md"): 102}
        mods = []
        for source, (html, assets) in self.pages.items():
            files = {n: hashlib.sha1(self.images[n]).hexdigest() for n in assets}
            files.update(overrides.get(source, {}))
            mods.append({"idnumber": mid(source), "cmid": cmids[mid(source)],
                         "modname": "page", "content": self.resolved(html, cmids),
                         "files": [{"filename": n, "contenthash": h, "filesize": 1}
                                   for n, h in files.items() if h is not None]})
        return mods

    def publish(self, client):
        out = io.StringIO()
        with contextlib.redirect_stdout(out):
            pm.publish(client, self.dir, 1)
        return out.getvalue()


class Publish(PublishBase):
    # (a)
    def test_unchanged_republish_sends_nothing(self):
        client = FakeClient(self.server_as_published())
        out = self.publish(client)
        self.assertEqual(client.uploads, [])
        for p in client.page_calls():
            self.assertEqual(p.get("contentitemid", 0), 0)
            self.assertNotIn("syncfiles", p)
            self.assertNotIn("keepfiles", p)
            self.assertNotIn("@@MODULE:", p["content"])
        self.assertIn("pages: 0 created, 0 updated, 2 unchanged", out)
        self.assertIn("images: 0 sent", out)

    # (b)
    def test_one_changed_image(self):
        client = FakeClient(self.server_as_published(**{"01-one.md": {"b.png": "0" * 40}}))
        out = self.publish(client)
        self.assertEqual(client.uploads, ["b.png"])
        one = [p for p in client.page_calls() if p["idnumber"] == mid("01-one.md")][0]
        self.assertTrue(one["syncfiles"])
        self.assertEqual(one["keepfiles"], ["a.png"])
        self.assertNotEqual(one["contentitemid"], 0)
        two = [p for p in client.page_calls() if p["idnumber"] == mid("02-two.md")][0]
        self.assertNotIn("syncfiles", two)
        self.assertIn("1 sent", out)

    # (c)
    def test_removed_image(self):
        self.pages["01-one.md"] = (self.pages["01-one.md"][0], ["a.png"])
        client = FakeClient(self.server_as_published(**{"01-one.md": {"b.png": "1" * 40}}))
        self.write_manifest()
        self.publish(client)
        self.assertEqual(client.uploads, [])
        one = [p for p in client.page_calls() if p["idnumber"] == mid("01-one.md")][0]
        self.assertTrue(one["syncfiles"])
        self.assertEqual(one.get("contentitemid", 0), 0)
        self.assertEqual(one["keepfiles"], ["a.png"])

    # (d)
    def test_links_to_existing_modules_resolved_in_pass_one(self):
        client = FakeClient(self.server_as_published())
        self.publish(client)
        calls = client.page_calls()
        self.assertEqual(len(calls), 2, "pass 2 must send nothing")
        self.assertIn("%s/mod/page/view.php?id=102" % URL, calls[0]["content"])

    # (e)
    def test_link_to_module_created_this_run_resolved_in_pass_two(self):
        client = FakeClient([])
        out = self.publish(client)
        calls = client.page_calls()
        self.assertEqual(len(calls), 3)
        self.assertIn("@@MODULE:", calls[0]["content"])
        self.assertNotIn("@@MODULE:", calls[2]["content"])
        self.assertIn("view.php?id=%d" % client.server[mid("02-two.md")]["cmid"],
                      calls[2]["content"])
        self.assertEqual(sorted(client.uploads), ["a.png", "b.png", "c.png"])
        self.assertIn("pages: 2 created, 0 updated, 0 unchanged", out)

    def test_link_to_module_never_published_becomes_dead_in_pass_one(self):
        html = self.pages["02-two.md"][0] + '<a href="@@MODULE:%s@@">gone</a>' % mid("99-x.md")
        self.pages["02-two.md"] = (html, ["c.png"])
        self.write_manifest()
        client = FakeClient(self.server_as_published())
        self.publish(client)
        two = [p for p in client.page_calls() if p["idnumber"] == mid("02-two.md")]
        self.assertEqual(len(two), 1)
        self.assertNotIn("@@MODULE:", two[0]["content"])

    # (f)
    def test_dry_run_reads_nothing(self):
        client = FakeClient(self.server_as_published(), dry_run=True)
        out = self.publish(client)
        self.assertNotIn("local_ltuse_get_course_manifest", [f for f, _ in client.calls])
        self.assertIn("dry-run", out)

    # Modules the repo no longer has: retired (hidden, in the Retired section), never deleted.
    def test_module_the_repo_no_longer_has_is_retired(self):
        mods = self.server_as_published()
        mods += [
            # The old filename identity of a lesson, as the pre-2026-10-02 scheme wrote it.
            {"idnumber": mid("01-one-old-name.md"), "cmid": 120, "modname": "page",
             "files": []},
            {"idnumber": mid("09-quiz.md"), "cmid": 121, "modname": "quiz", "files": []},
            # Not this course's content: the shared question bank, and another prefix.
            {"idnumber": "ltct:qbank", "cmid": 130, "modname": "qbank", "files": []},
            {"idnumber": "ltct:demo-two:01", "cmid": 131, "modname": "page", "files": []},
        ]
        client = FakeClient(mods)
        out = self.publish(client)
        calls = client.hide_calls()
        self.assertEqual(len(calls), 1)
        self.assertEqual(sorted(calls[0]["idnumbers"]),
                         [mid("01-one-old-name.md"), mid("09-quiz.md")])
        self.assertEqual(calls[0]["courseidnumber"], COURSE)
        self.assertIn("retired: 2 module(s)", out)
        # Retiring follows every content write: nothing is retired before its replacement
        # exists. Only the course completion criteria (spec 004) and then the badge (spec
        # 013) come after it.
        self.assertEqual(client.writes()[-3:], ["local_ltuse_hide_modules",
                                                "local_ltuse_set_course_completion",
                                                "local_ltuse_set_course_recognition"])

    def test_already_retired_module_is_not_sent_again(self):
        mods = self.server_as_published()
        mods.append({"idnumber": mid("03-gone.md"), "cmid": 120, "modname": "page",
                     "visible": 0, "retired": True, "files": []})
        client = FakeClient(mods)
        out = self.publish(client)
        self.assertEqual(client.hide_calls(), [])
        self.assertNotIn("retire", out)

    def test_hidden_in_place_by_an_older_plugin_is_moved(self):
        # Plugin 0.5.0 hid stale modules where they stood; they still need moving.
        mods = self.server_as_published()
        mods.append({"idnumber": mid("03-gone.md"), "cmid": 120, "modname": "page",
                     "visible": 0, "files": []})
        client = FakeClient(mods)
        self.publish(client)
        self.assertEqual(client.hide_calls()[0]["idnumbers"], [mid("03-gone.md")])

    def test_retired_but_shown_by_a_teacher_is_hidden_again(self):
        mods = self.server_as_published()
        mods.append({"idnumber": mid("03-gone.md"), "cmid": 120, "modname": "page",
                     "visible": 1, "retired": True, "files": []})
        client = FakeClient(mods)
        self.publish(client)
        self.assertEqual(client.hide_calls()[0]["idnumbers"], [mid("03-gone.md")])

    def test_nothing_removed_means_no_hide_call(self):
        client = FakeClient(self.server_as_published())
        out = self.publish(client)
        self.assertEqual(client.hide_calls(), [])
        self.assertNotIn("retire", out)

    def test_discussion_forum_is_never_hidden(self):
        # Spec 012: the forum's idnumber starts ltct:<slug>: like a module's, but no file
        # produces it, so the hide step must still count it as this run's.
        mods = self.server_as_published()
        mods.append({"idnumber": COURSE + ":discussion", "cmid": 960, "modname": "forum",
                     "files": []})
        client = FakeClient(mods)
        self.publish(client)
        self.assertEqual(client.hide_calls(), [])

    def test_dry_run_hides_nothing(self):
        mods = self.server_as_published()
        mods.append({"idnumber": mid("03-gone.md"), "cmid": 120, "modname": "page",
                     "files": []})
        client = FakeClient(mods, dry_run=True)
        self.publish(client)
        self.assertEqual(client.hide_calls(), [])

    def test_older_plugin_without_file_lists(self):
        mods = self.server_as_published()
        for m in mods:
            del m["files"]
        client = FakeClient(mods)
        self.publish(client)
        self.assertEqual(sorted(client.uploads), ["a.png", "b.png", "c.png"])
        for p in client.page_calls():
            self.assertNotIn("syncfiles", p)
            self.assertNotIn("keepfiles", p)


MANIFEST = {
    "idnumber": "ltct:fixture-course",
    "slug": "fixture-course",
    "title": "Fixture course",
    "summary_html": "<p>Fixture</p>",
    "sections": [{"number": 1, "name": "One"}, {"number": 2, "name": "Two"}],
}


class StubClient:
    """Records every call. dry_run is False, so ensure_course reaches the update path.

    The course's custom fields read back as what was sent, less any named in `drop`
    (Moodle drops an unknown or locked field silently). `warnings` is what
    core_course_update_courses answers.
    """
    dry_run = False

    def __init__(self, existing, warnings=(), drop=()):
        self.existing = existing
        self.calls = []
        self.warnings = list(warnings)
        self.drop = set(drop)
        self.fields = {}

    def course_by_idnumber(self, idnumber):
        self.calls.append(("course_by_idnumber", {"idnumber": idnumber}))
        created = any(n == "core_course_create_courses" for n, _ in self.calls)
        if self.existing is None and not created:
            return None
        course = dict(self.existing or {"id": 9})
        course["customfields"] = [{"shortname": k, "value": v, "valueraw": v}
                                  for k, v in self.fields.items()]
        return course

    def call(self, function, **params):
        self.calls.append((function, params))
        for course in params.get("courses", []):
            for f in course.get("customfields", []):
                if f["shortname"] not in self.drop:
                    self.fields[f["shortname"]] = f["value"]
        if function == "core_course_create_courses":
            return [{"id": 9, "shortname": params["courses"][0]["shortname"]}]
        return {"warnings": self.warnings}


def course_payload(client, function):
    calls = [params for name, params in client.calls if name == function]
    assert len(calls) == 1, "expected one %s call, got %d" % (function, len(calls))
    courses = calls[0]["courses"]
    assert len(courses) == 1
    return courses[0]


class EnsureCourseGroupMode(unittest.TestCase):
    def test_update_sends_no_groups(self):
        client = StubClient({"id": 5})
        courseid, created = pm.ensure_course(client, MANIFEST, 3)
        self.assertEqual((courseid, created), (5, False))
        course = course_payload(client, "core_course_update_courses")
        self.assertEqual(course["id"], 5)
        self.assertEqual(course["groupmode"], 0)
        self.assertNotIn("core_course_create_courses", [n for n, _ in client.calls])

    def test_hidden_sections_hidden_completely_on_create_and_update(self):
        for existing, function in (({"id": 5}, "core_course_update_courses"),
                                   (None, "core_course_create_courses")):
            with self.subTest(function=function):
                client = StubClient(existing)
                pm.ensure_course(client, MANIFEST, 3)
                course = course_payload(client, function)
                self.assertEqual(course["courseformatoptions"],
                                 [{"name": "hiddensections", "value": "1"}])

    def test_create_sends_no_groups(self):
        client = StubClient(None)
        courseid, created = pm.ensure_course(client, MANIFEST, 3)
        self.assertEqual((courseid, created), (9, True))
        course = course_payload(client, "core_course_create_courses")
        self.assertEqual(course["groupmode"], 0)
        self.assertEqual(course["idnumber"], "ltct:fixture-course")
        self.assertEqual(course["categoryid"], 3)
        self.assertNotIn("core_course_update_courses", [n for n, _ in client.calls])

    def test_activity_reports_off_on_create_and_update(self):
        # Spec 003 (R2): reports on would show a mentor submissions and logs, and the site
        # default covers new courses only, so every publish resets each course's own setting.
        for existing, function in (({"id": 5}, "core_course_update_courses"),
                                   (None, "core_course_create_courses")):
            with self.subTest(function=function):
                client = StubClient(existing)
                pm.ensure_course(client, MANIFEST, 3)
                self.assertEqual(course_payload(client, function)["showreports"], 0)

    def test_groupmode_is_not_forced(self):
        # Shared courses are open (spec 002 R3), so nothing is forced: a teacher may still
        # use groups for teaching in one activity, and a forced mode would wall the forum.
        for existing in ({"id": 5}, None):
            client = StubClient(existing)
            pm.ensure_course(client, MANIFEST, 3)
            for name, params in client.calls:
                for course in params.get("courses", []):
                    self.assertNotIn("groupmodeforce", course)


# --- spec 004 ------------------------------------------------------------------------------

QUIZ = {"idnumber": mid("03"), "name": "Quiz", "source": "03-quiz.md", "section": 3,
        "category": "demo / 03-quiz", "threshold_pct": 80, "completion": "pass",
        "questions": [{"idnumber": mid("03:q1.1"), "name": "Q1", "text_html": "<p>Q</p>",
                       "single": True, "feedback_html": "", "section": None,
                       "answers": [{"letter": "A", "text_html": "A", "correct": True},
                                   {"letter": "B", "text_html": "B", "correct": False}]}]}


class FakePayload:
    """Stands in for moodle_payload.Payload: the manifest is the one already on disk."""
    dir = None

    def __init__(self, folder, view):
        pass

    def build(self):
        manifest = json.loads((self.dir / "manifest.json").read_text(encoding="utf-8"))
        return manifest, {}, {}


class Main(PublishBase):
    """Drives main() end to end, with the build, the gate and the client replaced."""

    def run_main(self, client, *argv):
        FakePayload.dir = self.dir
        saved = {n: getattr(pm, n) for n in
                 ("resolve_folder", "Payload", "write_payload", "verify",
                  "report_images", "MoodleClient")}
        pm.resolve_folder = lambda slug: self.dir
        pm.Payload = FakePayload
        pm.write_payload = lambda manifest, pages, assets, out: self.dir
        pm.verify = lambda out, slug: True
        pm.report_images = lambda manifest: None
        pm.MoodleClient = lambda dry_run=False: client
        old_argv = sys.argv
        sys.argv = ["publish_moodle.py", "--slug", "demo",
                    "--keep-payload", str(self.dir), *argv]
        out = io.StringIO()
        try:
            with contextlib.redirect_stdout(out), contextlib.redirect_stderr(out):
                rc = pm.main()
        finally:
            sys.argv = old_argv
            for n, v in saved.items():
                setattr(pm, n, v)
        return rc, out.getvalue()


class Completion(Main):
    """US1: the one completion rule reaches every module and the course criteria."""

    def test_ensure_course_switches_completion_on(self):
        for existing in ({"id": 5}, None):
            client = StubClient(existing)
            pm.ensure_course(client, MANIFEST, 3)
            fn = "core_course_update_courses" if existing else "core_course_create_courses"
            self.assertEqual(course_payload(client, fn)["enablecompletion"], 1)

    def test_every_module_call_carries_its_completion(self):
        self.write_manifest(quizzes=[QUIZ])
        client = FakeClient([])
        self.publish(client)
        pages = client.page_calls()
        self.assertEqual(len(pages), 3, "pass 1 twice, pass 2 once")
        for p in pages:
            self.assertEqual(p["completion"], "view")
        quiz = client.calls_to("local_ltuse_create_quiz")
        self.assertEqual([q["completion"] for q in quiz], ["pass"])

    def test_course_completion_is_set_once_after_hiding_and_last(self):
        mods = self.server_as_published()
        mods.append({"idnumber": mid("09"), "cmid": 121, "modname": "quiz", "files": []})
        client = FakeClient(mods)
        self.publish(client)
        calls = client.calls_to("local_ltuse_set_course_completion")
        self.assertEqual(calls, [{"courseidnumber": COURSE}])
        writes = client.writes()
        self.assertEqual(writes[-2:], ["local_ltuse_set_course_completion",
                                       "local_ltuse_set_course_recognition"])
        self.assertLess(writes.index("local_ltuse_hide_modules"),
                        writes.index("local_ltuse_set_course_completion"))

    def test_course_completion_is_last_with_nothing_to_hide(self):
        # Last but for the badge, which must see the final criteria (spec 013).
        client = FakeClient(self.server_as_published())
        self.publish(client)
        self.assertEqual(client.writes()[-2:], ["local_ltuse_set_course_completion",
                                                "local_ltuse_set_course_recognition"])

    def test_output_counts_added_and_removed_criteria(self):
        client = FakeClient(self.server_as_published())
        client.course_completion.update(added=[101, 102], removed=[120], reaggregated=3)
        out = self.publish(client)
        self.assertIn("completion  2 added, 1 removed", out)

    def test_differs_names_the_module_and_exits_1_after_the_summary(self):
        client = FakeClient(self.server_as_published())
        client.completion[mid("02-two.md")] = "differs"
        rc, out = self.run_main(client)
        self.assertEqual(rc, 1)
        self.assertIn(mid("02-two.md"), out.split("published. Course URL")[-1])
        self.assertIn("differs", out)
        # The publish itself completed: the criteria were still written.
        self.assertEqual(len(client.calls_to("local_ltuse_set_course_completion")), 1)
        self.assertLess(out.index("pages:"), out.rindex(mid("02-two.md")))

    def test_no_differs_exits_0(self):
        rc, out = self.run_main(FakeClient(self.server_as_published()))
        self.assertEqual(rc, 0, out)

    def test_dry_run_lists_course_completion_and_sends_nothing(self):
        import urllib.request
        client = pm.MoodleClient(dry_run=True)

        def refuse(*a, **k):
            raise AssertionError("a dry run must send nothing")
        saved, urllib.request.urlopen = urllib.request.urlopen, refuse
        try:
            rc, out = self.run_main(client, "--dry-run")
        finally:
            urllib.request.urlopen = saved
        self.assertEqual(rc, 0, out)
        names = [f for f, _ in client.calls]
        self.assertIn("local_ltuse_set_course_completion", names)
        self.assertIn("local_ltuse_set_course_competencies", names)
        self.assertIn("nothing was sent", out)
        self.assertIn("completion view", out)
        self.assertNotIn("not applied", out)

    def test_a_module_completion_not_applied_is_said(self):
        # '' is the plugin's answer when completion is off for the site or the course.
        client = FakeClient(self.server_as_published())
        client.completion[mid("01-one.md")] = ""
        out = self.publish(client)
        self.assertIn("not applied to 1 module(s)", out)


class Recognition(Main):
    """Spec 013: the badge after completion, the certificate on delivery only."""

    def test_pilot_sends_no_certificate_and_says_so(self):
        client = FakeClient(self.server_as_published())
        out = self.publish(client)
        self.assertEqual(client.calls_to("local_ltuse_set_course_recognition"),
                         [{"courseidnumber": COURSE, "delivery": False}])
        self.assertIn("recognition  badge unchanged, inactive (pilot: no badge is issued "
                      "until stage 8)", out)

    def test_delivery_sends_the_certificate_idnumber(self):
        self.write_manifest(delivery=True)
        client = FakeClient(self.server_as_published())
        client.recognition.update(badge="updated", status="active", certificate="unchanged")
        out = self.publish(client)
        self.assertEqual(client.calls_to("local_ltuse_set_course_recognition"),
                         [{"courseidnumber": COURSE, "delivery": True,
                           "certificateidnumber": COURSE + ":certificate"}])
        self.assertIn("recognition  badge updated, active; certificate unchanged", out)
        self.assertNotIn("pilot", out)

    def test_the_certificate_is_never_retired(self):
        # On the server, not a module of the payload, on a delivery or a pilot publish (R14).
        for delivery in (True, False):
            self.write_manifest(delivery=delivery)
            mods = self.server_as_published()
            mods.append({"idnumber": COURSE + ":certificate", "cmid": 140,
                         "modname": "customcert", "files": []})
            client = FakeClient(mods)
            self.publish(client)
            self.assertEqual(client.hide_calls(), [], delivery)

    def test_a_warning_is_a_problem_and_exits_1(self):
        client = FakeClient(self.server_as_published())
        client.recognition["warnings"] = [{"code": "active-not-delivery",
                                           "message": "left active"}]
        rc, out = self.run_main(client)
        self.assertEqual(rc, 1)
        tail = out.split("NEEDS A DECISION")[-1]
        self.assertIn("recognition active-not-delivery: left active", tail)

    def test_a_refusal_stops_the_publish(self):
        client = FakeClient(self.server_as_published())

        def refuse(function, **params):
            if function == "local_ltuse_set_course_recognition":
                raise pm.MoodleError(function, {"message": "recognition-not-applied"})
            return FakeClient.call(client, function, **params)
        client.call = refuse
        rc, out = self.run_main(client)
        self.assertEqual(rc, 1)
        self.assertIn("recognition-not-applied", out)

    def test_dry_run_names_the_call_and_sends_nothing(self):
        client = FakeClient(self.server_as_published(), dry_run=True)
        out = self.publish(client)
        self.assertIn("local_ltuse_set_course_recognition", [f for f, _ in client.calls])
        self.assertIn("recognition  dry-run: badge (pilot", out)


class Competencies(Main):
    """US5: the course's competencies and target level reach Moodle, and read back."""

    def test_course_fields_on_update_and_create(self):
        manifest = dict(MANIFEST, competencies=["Translation Tools", "Fonts & Encoding"],
                        target_outcome_level="2 - With Assistance")
        for existing in ({"id": 5}, None):
            client = StubClient(existing)
            problems = []
            pm.ensure_course(client, manifest, 3, problems=problems)
            fn = "core_course_update_courses" if existing else "core_course_create_courses"
            fields = {f["shortname"]: f["value"]
                      for f in course_payload(client, fn)["customfields"]}
            self.assertEqual(fields, {
                "ltct_competencies": "[Translation Tools] [Fonts & Encoding]",
                "ltct_target_level": "2 - With Assistance"})
            self.assertEqual(problems, [])

    def test_absent_level_is_sent_empty(self):
        client = StubClient({"id": 5})
        pm.ensure_course(client, dict(MANIFEST, target_outcome_level=None), 3)
        fields = {f["shortname"]: f["value"]
                  for f in course_payload(client, "core_course_update_courses")["customfields"]}
        self.assertEqual(fields["ltct_target_level"], "")
        self.assertEqual(fields["ltct_competencies"], "")

    def test_update_warnings_fail_the_publish(self):
        client = StubClient({"id": 5}, warnings=[{"item": "course", "itemid": 5,
                                                   "warningcode": "1", "message": "nope"}])
        with self.assertRaises(pm.MoodleError) as e:
            pm.ensure_course(client, MANIFEST, 3)
        self.assertIn("nope", str(e.exception))

    def test_a_silently_dropped_field_is_a_problem(self):
        client = StubClient({"id": 5}, drop={"ltct_target_level"})
        problems = []
        pm.ensure_course(client, dict(MANIFEST, target_outcome_level="3 - Independent"), 3,
                         problems=problems)
        self.assertEqual(len(problems), 1)
        self.assertIn("ltct_target_level", problems[0])

    def test_meta_is_dropped_and_said(self):
        self.write_manifest(competencies=["Translation Tools", "Uncategorized"])
        client = FakeClient(self.server_as_published())
        out = self.publish(client)
        self.assertIn("competencies  skipped Meta: Uncategorized", out)
        calls = client.calls_to("local_ltuse_set_course_competencies")
        self.assertEqual(calls, [{"courseid": 7, "competencies": ["Translation Tools"]}])
        update = client.calls_to("core_course_update_courses")[0]["courses"][0]
        fields = {f["shortname"]: f["value"] for f in update["customfields"]}
        # The field mirrors the frontmatter (data-model R11); only the per-competency
        # table drops Meta names (contracts/publish.md step 2).
        self.assertEqual(fields["ltct_competencies"], "[Translation Tools] [Uncategorized]")

    def test_duplicate_competency_is_sent_once_and_not_a_difference(self):
        self.write_manifest(competencies=["Translation Tools", "Keyboards",
                                          "Translation Tools"])
        client = FakeClient(self.server_as_published())
        # The plugin keeps each name once (array_unique), as the real one does.
        client.competencies_back = ["Translation Tools", "Keyboards"]
        rc, out = self.run_main(client)
        self.assertEqual(rc, 0, out)
        calls = client.calls_to("local_ltuse_set_course_competencies")
        self.assertEqual(calls[0]["competencies"], ["Translation Tools", "Keyboards"])
        update = client.calls_to("core_course_update_courses")[0]["courses"][0]
        fields = {f["shortname"]: f["value"] for f in update["customfields"]}
        self.assertEqual(fields["ltct_competencies"], "[Translation Tools] [Keyboards]")

    def test_competencies_follow_ensure_course_and_precede_content(self):
        client = FakeClient(self.server_as_published())
        out = self.publish(client)
        names = [f for f, _ in client.calls]
        self.assertLess(names.index("core_course_update_courses"),
                        names.index("local_ltuse_set_course_competencies"))
        self.assertLess(names.index("local_ltuse_set_course_competencies"),
                        names.index("local_ltuse_create_page"))
        self.assertIn("competencies  1 added, 0 removed", out)
        self.assertNotIn("skipped Meta", out)

    def test_read_back_that_differs_prints_both_and_exits_1(self):
        client = FakeClient(self.server_as_published())
        client.competencies_back = ["Keyboards"]
        rc, out = self.run_main(client)
        self.assertEqual(rc, 1)
        tail = out.split("published. Course URL")[-1]
        self.assertIn("Translation Tools", tail)
        self.assertIn("Keyboards", tail)
        # Still a complete publish: the content and the criteria were written.
        self.assertEqual(len(client.calls_to("local_ltuse_set_course_completion")), 1)

    def test_read_back_in_another_order_is_not_a_difference(self):
        self.write_manifest(competencies=["Translation Tools", "Keyboards"])
        client = FakeClient(self.server_as_published())
        client.competencies_back = ["Keyboards", "Translation Tools"]
        rc, out = self.run_main(client)
        self.assertEqual(rc, 0, out)


if __name__ == "__main__":
    unittest.main()


class Placement(Main):
    """Spec 002 R11: an organisation-only course is placed in its category on every publish."""

    PLACE = "local_ltuse_place_course"

    def test_org_only_course_is_placed_after_update(self):
        self.write_manifest(placement=ORG_ONLY)
        client = FakeClient(self.server_as_published())
        self.publish(client)
        self.assertEqual(client.calls_to(self.PLACE), [
            {"courseidnumber": COURSE, "categoryidnumber": "ltct:org:fixture-north"}])
        names = [f for f, _ in client.calls]
        self.assertLess(names.index("core_course_update_courses"), names.index(self.PLACE))

    def test_org_only_course_is_placed_after_create(self):
        self.write_manifest(placement=ORG_ONLY)
        client = FakeClient([])
        real = client.call

        def first_publish(function, **params):
            if function == "core_course_get_courses_by_field" and not any(
                    f == "core_course_create_courses" for f, _ in client.calls):
                client.calls.append((function, params))
                return None
            return real(function, **params)
        client.call = first_publish
        self.publish(client)
        names = [f for f, _ in client.calls]
        self.assertIn("core_course_create_courses", names)
        self.assertNotIn("core_course_update_courses", names)
        self.assertEqual(client.calls_to(self.PLACE), [
            {"courseidnumber": COURSE, "categoryidnumber": "ltct:org:fixture-north"}])
        self.assertLess(names.index("core_course_create_courses"), names.index(self.PLACE))

    def test_placed_before_content(self):
        self.write_manifest(placement=ORG_ONLY)
        client = FakeClient(self.server_as_published())
        self.publish(client)
        writes = client.writes()
        self.assertLess(writes.index(self.PLACE), writes.index("local_ltuse_create_page"))

    def test_shared_course_is_never_placed(self):
        client = FakeClient(self.server_as_published())
        self.publish(client)
        self.assertEqual(client.calls_to(self.PLACE), [])

    def test_a_move_is_reported(self):
        self.write_manifest(placement=ORG_ONLY)
        client = FakeClient(self.server_as_published())
        client.placed = {"moved": True}
        out = self.publish(client)
        self.assertIn("placement moved to ltct:org:fixture-north", out)

    def test_no_move_is_said_quietly(self):
        self.write_manifest(placement=ORG_ONLY)
        out = self.publish(FakeClient(self.server_as_published()))
        self.assertIn("placement ltct:org:fixture-north (already there)", out)

    def test_a_refusal_stops_the_publish(self):
        self.write_manifest(placement=ORG_ONLY)
        client = FakeClient(self.server_as_published())

        def refuse(function, **params):
            if function == self.PLACE:
                raise pm.MoodleError(function, {"message": "invalidcategory"})
            return FakeClient.call(client, function, **params)
        client.call = refuse
        rc, out = self.run_main(client)
        self.assertEqual(rc, 1)
        self.assertIn("invalidcategory", out)
        self.assertEqual(client.calls_to("local_ltuse_create_page"), [])

    def test_dry_run_names_the_call(self):
        self.write_manifest(placement=ORG_ONLY)
        client = FakeClient(self.server_as_published(), dry_run=True)
        out = self.publish(client)
        self.assertEqual(len(client.calls_to(self.PLACE)), 1)
        self.assertIn("placement dry-run: ltct:org:fixture-north", out)

    def test_client_requires_the_function(self):
        import moodle_client
        self.assertIn(self.PLACE, moodle_client.REQUIRED_FUNCTIONS)
