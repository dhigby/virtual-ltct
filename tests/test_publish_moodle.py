"""Unit tests for scripts/publish_moodle.py. Run: python -m pytest tests/

Two groups: course creation (spec 002) and the per-page diff against the server (spec 009, US3).
No network: fake clients record every call; the diff tests answer
local_ltuse_get_course_manifest from a canned server state, with the payload written by hand
into a temp dir.
"""
import contextlib, hashlib, io, json, pathlib, sys, tempfile, unittest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import publish_moodle as pm

COURSE = "ltct:demo"
URL = "https://moodle.example.org"


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
            return {"id": 7}
        if function == "local_ltuse_get_course_manifest":
            return {"modules": list(self.server.values())}
        if function == "local_ltuse_create_page":
            m = self.server.get(params["idnumber"])
            if m is None:
                self.next_cmid += 1
                self.server[params["idnumber"]] = m = {
                    "idnumber": params["idnumber"], "cmid": self.next_cmid,
                    "modname": "page", "files": []}
                return {"cmid": m["cmid"], "created": True, "outcome": "created"}
            sends_files = params.get("syncfiles") or params.get("contentitemid")
            same = m.get("content") == params["content"] and not sends_files
            m["content"] = params["content"]
            return {"cmid": m["cmid"], "created": False,
                    "outcome": "unchanged" if same else "updated"}
        if function == "local_ltuse_hide_modules":
            out = []
            for idn in params["idnumbers"]:
                m = self.server[idn]
                done = m.get("retired") and not m.get("visible", 1)
                out.append({"idnumber": idn, "cmid": m["cmid"],
                            "outcome": "alreadyretired" if done else "retired"})
                m["visible"], m["retired"] = 0, True
            return {"modules": out}
        if function == "local_ltuse_import_questions":
            return {"count": 1}
        if function == "local_ltuse_create_quiz":
            m = self.server.setdefault(params["idnumber"], {
                "idnumber": params["idnumber"], "cmid": 950, "modname": "quiz", "files": []})
            return {"cmid": m["cmid"]}
        return {}

    def page_calls(self):
        return [p for f, p in self.calls if f == "local_ltuse_create_page"]

    def hide_calls(self):
        return [p for f, p in self.calls if f == "local_ltuse_hide_modules"]


class Publish(unittest.TestCase):
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

    def write_manifest(self, quizzes=()):
        sections = []
        for n, (source, (html, assets)) in enumerate(sorted(self.pages.items()), start=1):
            stem = source[:-3]
            (self.dir / "pages" / ("%s.html" % stem)).write_text(html, encoding="utf-8")
            sections.append({"number": n, "name": stem, "modules": [{
                "kind": "page", "idnumber": mid(source), "name": stem, "source": source,
                "html_file": "pages/%s.html" % stem, "assets": assets}]})
        manifest = {
            "slug": "demo", "idnumber": COURSE, "title": "Demo", "summary_html": "",
            "publishable": True, "blocked_reason": None, "sections": sections,
            "quizzes": list(quizzes), "withheld": [], "notes": [],
            "assets": {n: {"sha1": hashlib.sha1(d).hexdigest(), "bytes": len(d)}
                       for n, d in self.images.items()},
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
        # Retiring is the last write: nothing is retired before its replacement exists.
        writes = [f for f, _ in client.calls if f.startswith("local_ltuse_")
                  and f != "local_ltuse_get_course_manifest"]
        self.assertEqual(writes[-1], "local_ltuse_hide_modules")

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
    """Records every call. dry_run is False, so ensure_course reaches the update path."""
    dry_run = False

    def __init__(self, existing):
        self.existing = existing
        self.calls = []

    def course_by_idnumber(self, idnumber):
        self.calls.append(("course_by_idnumber", {"idnumber": idnumber}))
        return self.existing

    def call(self, function, **params):
        self.calls.append((function, params))
        if function == "core_course_create_courses":
            return [{"id": 9, "shortname": params["courses"][0]["shortname"]}]
        return {"warnings": []}


def course_payload(client, function):
    calls = [params for name, params in client.calls if name == function]
    assert len(calls) == 1, "expected one %s call, got %d" % (function, len(calls))
    courses = calls[0]["courses"]
    assert len(courses) == 1
    return courses[0]


class EnsureCourseGroupMode(unittest.TestCase):
    def test_update_sends_separate_groups(self):
        client = StubClient({"id": 5})
        courseid, created = pm.ensure_course(client, MANIFEST, 3)
        self.assertEqual((courseid, created), (5, False))
        course = course_payload(client, "core_course_update_courses")
        self.assertEqual(course["id"], 5)
        self.assertEqual(course["groupmode"], 1)
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

    def test_create_sends_separate_groups(self):
        client = StubClient(None)
        courseid, created = pm.ensure_course(client, MANIFEST, 3)
        self.assertEqual((courseid, created), (9, True))
        course = course_payload(client, "core_course_create_courses")
        self.assertEqual(course["groupmode"], 1)
        self.assertEqual(course["idnumber"], "ltct:fixture-course")
        self.assertEqual(course["categoryid"], 3)
        self.assertNotIn("core_course_update_courses", [n for n, _ in client.calls])

    def test_groupmode_is_not_forced(self):
        # Not forced, so a forum can still run across organisations (spec 005, R3).
        for existing in ({"id": 5}, None):
            client = StubClient(existing)
            pm.ensure_course(client, MANIFEST, 3)
            for name, params in client.calls:
                for course in params.get("courses", []):
                    self.assertNotIn("groupmodeforce", course)


if __name__ == "__main__":
    unittest.main()
