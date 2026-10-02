"""Unit tests for scripts/publish_moodle.py (spec 002). Run: python -m pytest tests/"""
import pathlib, sys, unittest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import publish_moodle as pm

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
