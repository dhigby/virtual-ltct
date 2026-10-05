"""Unit tests for scripts/ltct_admin.py and scripts/admin_files.py (spec 008). Run: python -m pytest tests/

No network and no file of real people: Moodle is a FakeClient (the pattern of
tests/test_publish_moodle.py), every address is @example.org, every organisation key is
fixture-*, and rows are built in memory. No CSV fixture is ever committed (constitution III);
a test that needs a file on disk writes it under pytest's tmp_path, outside the repository.
"""
import hashlib
import http.client
import io
import pathlib
import shutil
import sys
import urllib.error

import pytest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import admin_files as af  # noqa: E402
import ltct_admin as la  # noqa: E402
import moodle_client as mc  # noqa: E402

URL = "https://moodle.example.org"
ORGS = ["fixture-a", "fixture-b"]
ENV = {"MOODLE_URL": URL, "MOODLE_ADMIN_TOKEN": "fixture-admin-token-0001",
       "MOODLE_TOKEN": "fixture-publish-token-0002"}


# --- helpers -------------------------------------------------------------------------------
def intake_row(n, **over):
    """One intake row, as read_csv() would return it; row numbers start at 2 (header is 1)."""
    row = {"row": n, "email": "learner%d@example.org" % n, "firstname": "Fixture",
           "lastname": "Learner%d" % n, "organisation": "fixture-a", "country": "",
           "protection": "", "pseudonym": "", "email_checked": "", "courses": ""}
    row.update(over)
    return row


def intake_rows(count=3):
    return [intake_row(n) for n in range(2, 2 + count)]


class FakeClient:
    """Answers the ltuse_admin functions from canned state and records every call."""

    def __init__(self, functions=None, check=None, items=None):
        self.url = URL
        self.calls = []
        self.functions = la.ADMIN_FUNCTIONS if functions is None else functions
        self.check = check if check is not None else good_check()
        self.items = items or []

    def site_info(self):
        return self.call("core_webservice_get_site_info")

    def call(self, function, **params):
        self.calls.append((function, params))
        if function == "core_webservice_get_site_info":
            return {"sitename": "Fixture site", "siteurl": URL, "release": "5.2.3+",
                    "username": "ltc-fixture", "functions": [{"name": f} for f in self.functions]}
        if function == "local_ltuse_admin_check":
            return self.check
        if function == "local_ltuse_admin_list":
            return {"items": self.items}
        raise AssertionError("unexpected call %s" % function)


def good_check(**over):
    state = {
        "settings": [
            {"name": "enrol_cohort/unenrolaction", "value": "3", "isset": True},
            {"name": "tool_dynamic_cohorts/realtime", "value": "1", "isset": True},
            {"name": "allowaccountssameemail", "value": "0", "isset": True},
            {"name": "local_ltuse/coursementorsync", "value": "0", "isset": True},
        ],
        "missingcapabilities": [], "pathways": False, "protection": False, "levels": [],
    }
    state.update(over)
    return state


def run(argv, environ=None, client=None):
    out = io.StringIO()
    code = la.main(argv, environ=ENV if environ is None else environ,
                   client_factory=(lambda: client) if client else None, out=out)
    return code, out.getvalue()


def plan(rows, outcomes, applied=None, refusal=None, command=("intake", "f.csv")):
    """A Plan whose server answers previews with `outcomes` and applies into `applied`."""
    applied = [] if applied is None else applied

    def preview(show_people):
        if refusal:
            return {"refusal": refusal}
        return {"rows": [{"row": r["row"], "outcome": o, "reason": "",
                          "key": r["email"] if show_people else af.mask_email(r["email"]),
                          "changes": []} for r, o in zip(rows, outcomes())]}

    def apply(row, expected):
        applied.append((row["row"], expected))
        return {"status": "done"}

    return la.Plan(title="Intake", source="f.csv", rows=rows, preview=preview, apply=apply,
                   command=list(command))


# --- T007: the path guard ------------------------------------------------------------------
def test_guard_accepts_a_plain_temp_dir(tmp_path):
    target = tmp_path / "ltct-private" / "intake.csv"
    assert af.guard_path(target, REPO) == target.resolve()


def test_guard_refuses_a_folder_with_a_git_directory(tmp_path):
    (tmp_path / "proj" / ".git").mkdir(parents=True)
    with pytest.raises(af.PathRefused, match="git working tree"):
        af.guard_path(tmp_path / "proj" / "people.csv", REPO)


def test_guard_refuses_a_worktree_git_file(tmp_path):
    wt = tmp_path / "wt"
    wt.mkdir()
    (wt / ".git").write_text("gitdir: elsewhere\n")
    with pytest.raises(af.PathRefused):
        af.guard_path(wt / "deep" / "people.csv", REPO)


def test_guard_refuses_a_missing_subfolder_of_a_git_tree(tmp_path):
    (tmp_path / "proj" / ".git").mkdir(parents=True)
    with pytest.raises(af.PathRefused):
        af.guard_path(tmp_path / "proj" / "new" / "folder" / "out.csv", REPO)


def test_guard_refuses_anything_under_the_repo_root(tmp_path):
    with pytest.raises(af.PathRefused, match="inside this repository"):
        af.guard_path(REPO / "not-yet" / "intake.csv", REPO)
    # Even with no .git anywhere: a "repo root" in a plain temp folder still counts.
    fake_root = tmp_path / "root"
    fake_root.mkdir()
    with pytest.raises(af.PathRefused):
        af.guard_path(fake_root / "x.csv", fake_root)


def test_guard_needs_no_git_executable(tmp_path, monkeypatch):
    monkeypatch.setattr(shutil, "which", lambda *a, **k: None)
    (tmp_path / "proj" / ".git").mkdir(parents=True)
    with pytest.raises(af.PathRefused):
        af.guard_path(tmp_path / "proj" / "people.csv", REPO)
    with pytest.raises(af.PathRefused):
        af.guard_path(REPO / "scripts" / "admin_files.py", REPO)


def test_guard_fails_closed_when_an_ancestor_cannot_be_read(tmp_path, monkeypatch):
    locked = tmp_path / "locked"
    locked.mkdir()
    real = af.os.lstat

    def lstat(path, *a, **k):
        if pathlib.Path(path) == locked / ".git":
            raise PermissionError(13, "Permission denied")
        return real(path, *a, **k)

    monkeypatch.setattr(af.os, "lstat", lstat)
    with pytest.raises(af.PathRefused, match="Cannot check"):
        af.guard_path(locked / "people.csv", REPO)


def test_template_is_written_only_outside_git(tmp_path):
    out = tmp_path / "ltct-private" / "blank.csv"
    code, text = run(["template", "--kind", "intake", "--out", str(out)])
    assert code == 0
    assert out.read_text(encoding="utf-8-sig").strip() == \
        "email,firstname,lastname,organisation,country,protection,pseudonym,email_checked,courses"
    code, text = run(["template", "--kind", "intake", "--out", str(out)])
    assert code == 1 and "already exists" in text          # never overwrites a filled list
    code, text = run(["template", "--kind", "move", "--out", str(REPO / "blank.csv")])
    assert code == 1 and "inside this repository" in text
    assert not (REPO / "blank.csv").exists()


# --- T008: masking and the confirmation code ------------------------------------------------
def test_mask_email():
    assert af.mask_email("alice@example.org") == "a***@example.org"
    assert af.mask_email("  Alice@Example.ORG ") == "a***@example.org"
    assert af.mask_email("not-an-address") == "***"
    assert af.mask_email("") == "***"


def test_code_is_stable_across_runs():
    rows = intake_rows()
    outcomes = ["new", "new", "unchanged"]
    assert af.confirmation_code(rows, outcomes) == af.confirmation_code(intake_rows(), outcomes)
    assert len(af.confirmation_code(rows, outcomes)) == 10


@pytest.mark.parametrize("column,value", [
    ("email", "someone.else@example.org"), ("firstname", "Other"), ("lastname", "Other"),
    ("organisation", "fixture-b"), ("country", "KE"), ("protection", "email"),
    ("courses", "ltct:fixture-course"), ("pseudonym", "fixture-alias"), ("email_checked", "yes"),
])
def test_code_changes_with_any_input_column(column, value):
    outcomes = ["new", "new", "new"]
    base = af.confirmation_code(intake_rows(), outcomes)
    rows = intake_rows()
    rows[1][column] = value
    assert af.confirmation_code(rows, outcomes) != base


def test_code_ignores_case_and_order_that_do_not_change_the_input():
    rows = intake_rows(1)
    rows[0]["courses"] = "ltct:b;ltct:a"
    other = intake_rows(1)
    other[0]["courses"] = " ltct:a ; ltct:b"
    other[0]["email"] = other[0]["email"].upper()
    assert af.confirmation_code(rows, ["new"]) == af.confirmation_code(other, ["new"])


def test_code_does_not_depend_on_show_people():
    rows = intake_rows()
    p = plan(rows, lambda: ["new", "new", "new"])
    codes = []
    for show in (False, True):
        out = io.StringIO()
        assert la.drive(p, apply=False, confirm=None, show_people=show, out=out) == 0
        codes.append(out.getvalue().rsplit("--confirm ", 1)[1].strip())
    assert codes[0] == codes[1] == af.confirmation_code(rows, ["new"] * 3)


def test_proceeding_outcomes_share_a_class():
    rows = intake_rows(1)
    codes = {af.confirmation_code(rows, [o]) for o in
             ("new", "will_set_org", "will_enrol", "unchanged")}
    assert len(codes) == 1


@pytest.mark.parametrize("outcome", ["flagged_other_org", "flagged_suspended",
                                     "flagged_protection", "rejected", "waits"])
def test_every_stopping_outcome_has_its_own_class(outcome):
    rows = intake_rows(1)
    proceeds = af.confirmation_code(rows, ["new"])
    assert af.confirmation_code(rows, [outcome]) != proceeds
    others = {af.confirmation_code(rows, [o]) for o in
              ("flagged_other_org", "flagged_suspended", "flagged_protection", "rejected",
               "waits")}
    assert len(others) == 5


# --- T009: the preflight --------------------------------------------------------------------
@pytest.mark.parametrize("missing", ["MOODLE_URL", "MOODLE_ADMIN_TOKEN"])
def test_missing_variable_exits_2_naming_it(missing):
    env = dict(ENV)
    del env[missing]
    code, text = run(["check"], environ=env, client=FakeClient())
    assert code == 2
    assert missing in text
    for value in env.values():
        assert value not in text


def test_admin_token_equal_to_publish_token_is_refused():
    env = dict(ENV, MOODLE_TOKEN=ENV["MOODLE_ADMIN_TOKEN"])
    client = FakeClient()
    code, text = run(["check"], environ=env, client=client)
    assert code == 2 and "MOODLE_TOKEN" in text
    assert ENV["MOODLE_ADMIN_TOKEN"] not in text
    assert client.calls == []


def test_file_level_refusal_prints_no_code():
    p = plan(intake_rows(), lambda: [], refusal="the course ltct:fixture-missing does not exist")
    out = io.StringIO()
    assert la.drive(p, apply=False, confirm=None, show_people=False, out=out) == 1
    assert "--confirm" not in out.getvalue()
    assert "ltct:fixture-missing" in out.getvalue()


def test_apply_without_confirm_is_a_usage_error():
    code, text = run(["enrol", "course", "--cohort", "ltct:org:fixture-a", "--course",
                      "ltct:fixture-course", "--apply"])
    assert code == 2


# --- check and list -------------------------------------------------------------------------
def test_check_passes_on_a_ready_server():
    code, text = run(["check"], client=FakeClient())
    assert code == 0 and "Ready." in text
    assert ENV["MOODLE_ADMIN_TOKEN"] not in text


@pytest.mark.parametrize("name,value", [("enrol_cohort/unenrolaction", "0"),
                                        ("tool_dynamic_cohorts/realtime", "0")])
def test_check_refuses_a_wrong_setting_naming_it(name, value):
    state = good_check()
    for s in state["settings"]:
        if s["name"] == name:
            s["value"] = value
    code, text = run(["check"], client=FakeClient(check=state))
    assert code == 2 and name in text


def test_check_names_missing_capabilities():
    state = good_check(missingcapabilities=["moodle/user:create", "local/ltuse:manageprotection"])
    code, text = run(["check"], client=FakeClient(check=state))
    assert code == 1
    assert "moodle/user:create" in text and "protected rows will be refused" in text


def test_check_reports_a_missing_function():
    code, text = run(["check"], client=FakeClient(functions=["core_webservice_get_site_info"]))
    assert code == 2 and "local_ltuse_admin_check" in text


def test_list_organisations_is_offline():
    code, text = run(["list", "organisations"], environ={})
    assert code == 0 and "independent" in text


def test_list_courses_refuses_an_undeclared_organisation():
    client = FakeClient()
    code, text = run(["list", "courses", "--org", "fixture-nowhere"], client=client)
    assert code == 1
    assert not [c for c in client.calls if c[0] == "local_ltuse_admin_list"]


# --- T023: the driver -----------------------------------------------------------------------
def _code_of(p):
    out = io.StringIO()
    la.drive(p, apply=False, confirm=None, show_people=False, out=out)
    return out.getvalue().rsplit("--confirm ", 1)[1].strip()


def test_preview_masks_people_and_lists_stopping_rows():
    rows = intake_rows()
    p = plan(rows, lambda: ["new", "flagged_other_org", "new"])
    out = io.StringIO()
    la.drive(p, apply=False, confirm=None, show_people=False, out=out)
    text = out.getvalue()
    assert "l***@example.org" in text and "learner3@example.org" not in text
    assert "Nothing has been sent to Moodle." in text


def test_apply_with_the_code_sends_one_row_per_call():
    rows = intake_rows()
    applied = []
    p = plan(rows, lambda: ["new", "unchanged", "will_enrol"], applied)
    code = _code_of(p)
    out = io.StringIO()
    assert la.drive(p, apply=True, confirm=code, show_people=False, out=out) == 0
    assert applied == [(2, "new"), (4, "will_enrol")]       # unchanged needs no call


def test_apply_with_a_stale_code_is_refused():
    rows = intake_rows()
    applied = []
    p = plan(rows, lambda: ["new"] * 3, applied)
    code = _code_of(p)
    rows[0]["firstname"] = "Edited"
    out = io.StringIO()
    assert la.drive(p, apply=True, confirm=code, show_people=False, out=out) == 1
    assert applied == [] and "does not match" in out.getvalue()


def test_apply_refused_when_a_row_became_flagged():
    rows = intake_rows()
    state = {"outcomes": ["new"] * 3}
    applied = []
    p = plan(rows, lambda: state["outcomes"], applied)
    code = _code_of(p)
    state["outcomes"] = ["new", "flagged_other_org", "new"]
    assert la.drive(p, apply=True, confirm=code, show_people=False, out=io.StringIO()) == 1
    assert applied == []


def test_progress_keeps_the_code_and_already_done_is_success():
    rows = intake_rows()
    state = {"outcomes": ["new"] * 3}
    p = plan(rows, lambda: state["outcomes"])
    code = _code_of(p)
    # A partial apply moved two rows along their path; the same code still applies.
    state["outcomes"] = ["unchanged", "will_set_org", "new"]
    p.apply = lambda row, expected: {"status": "already_done" if row["row"] == 3 else "done"}
    out = io.StringIO()
    assert la.drive(p, apply=True, confirm=code, show_people=False, out=out) == 0
    assert "already done" in out.getvalue()


def test_a_refused_or_failed_row_exits_1_and_the_rest_still_apply():
    rows = intake_rows()
    p = plan(rows, lambda: ["new"] * 3)
    code = _code_of(p)
    sent = []

    def apply(row, expected):
        sent.append(row["row"])
        if row["row"] == 2:
            return {"status": "refused", "reason": "changed since the preview; preview again"}
        if row["row"] == 3:
            raise mc.MoodleError("local_ltuse_admin_apply_intake_row", {"message": "boom"})
        return {"status": "done"}

    p.apply = apply
    out = io.StringIO()
    assert la.drive(p, apply=True, confirm=code, show_people=False, out=out) == 1
    assert sent == [2, 3, 4]
    assert "Traceback" not in out.getvalue()


def test_previewed_stopping_rows_are_not_applied_and_exit_1():
    rows = intake_rows()
    applied = []
    p = plan(rows, lambda: ["new", "waits", "new"], applied)
    code = _code_of(p)
    assert la.drive(p, apply=True, confirm=code, show_people=False, out=io.StringIO()) == 1
    assert applied == [(2, "new"), (4, "new")]


# --- read_csv and validate_file ----------------------------------------------------------------
def test_read_csv_numbers_rows_as_a_spreadsheet_does(tmp_path):
    path = tmp_path / "in.csv"
    path.write_text("Email,FirstName,LastName,Organisation\n"
                    "a@example.org,A,One,fixture-a\n\n"
                    "b@example.org,B,Two,fixture-b\n", encoding="utf-8-sig")
    rows = af.read_csv(path, "intake")
    assert [r["row"] for r in rows] == [2, 4]
    assert rows[0]["email"] == "a@example.org"


def test_read_csv_refuses_an_unknown_column(tmp_path):
    path = tmp_path / "in.csv"
    path.write_text("email,firstname,lastname,organisation,role\n", encoding="utf-8")
    with pytest.raises(af.FileRefused, match="unknown column 'role'"):
        af.read_csv(path, "intake")


def test_validate_file_accepts_a_good_intake():
    assert af.validate_file("intake", intake_rows(), ORGS) == []


# --- T011: bounded retries in the Moodle client -------------------------------------------------
class _Resp:
    def __init__(self, body):
        self.body = body.encode("utf-8")

    def read(self):
        return self.body

    def __enter__(self):
        return self

    def __exit__(self, *a):
        return False


def _client_with(monkeypatch, answers, retries=mc.RETRY_DELAYS):
    sleeps, seen = [], []

    def urlopen(req, timeout=None):
        seen.append(req)
        answer = answers.pop(0)
        if isinstance(answer, Exception):
            raise answer
        return _Resp(answer)

    monkeypatch.setattr(mc.urllib.request, "urlopen", urlopen)
    client = mc.MoodleClient(url=URL, token="fixture-token", retries=retries,
                             sleep=sleeps.append)
    return client, sleeps, seen


def _http(code):
    return urllib.error.HTTPError(URL, code, "status %d" % code, {}, None)


def test_a_503_is_retried(monkeypatch):
    client, sleeps, seen = _client_with(monkeypatch, [_http(503), _http(502), '{"ok": 1}'])
    assert client.call("local_ltuse_admin_check") == {"ok": 1}
    assert len(seen) == 3 and sleeps == [2, 4]


def test_retries_are_bounded(monkeypatch):
    client, sleeps, seen = _client_with(monkeypatch, [_http(503)] * 5)
    with pytest.raises(mc.MoodleError, match="HTTP 503"):
        client.call("local_ltuse_admin_check")
    assert len(seen) == 4 and sleeps == [2, 4, 8]


def test_a_connection_error_is_retried(monkeypatch):
    client, sleeps, seen = _client_with(
        monkeypatch, [urllib.error.URLError("unreachable"), '{"ok": 1}'])
    assert client.call("local_ltuse_admin_check") == {"ok": 1}
    assert sleeps == [2]


def test_a_response_lost_mid_read_is_retried(monkeypatch):
    # urllib does not wrap a connection dropped while the answer is read: the lost response.
    client, sleeps, seen = _client_with(
        monkeypatch, [http.client.RemoteDisconnected("closed"), '{"ok": 1}'])
    assert client.call("local_ltuse_admin_check") == {"ok": 1}
    assert sleeps == [2]


def test_a_moodle_exception_is_not_retried(monkeypatch):
    body = '{"exception": "required_capability_exception", "errorcode": "nopermissions", ' \
           '"message": "No permission"}'
    client, sleeps, seen = _client_with(monkeypatch, [body, '{"ok": 1}'])
    with pytest.raises(mc.MoodleError, match="No permission"):
        client.call("local_ltuse_admin_check")
    assert len(seen) == 1 and sleeps == []


def test_a_4xx_is_not_retried(monkeypatch):
    client, sleeps, seen = _client_with(monkeypatch, [_http(403), '{"ok": 1}'])
    with pytest.raises(mc.MoodleError, match="HTTP 403"):
        client.call("local_ltuse_admin_check")
    assert len(seen) == 1


def test_the_publisher_still_gets_one_attempt(monkeypatch):
    client, sleeps, seen = _client_with(monkeypatch, [_http(503), '{"ok": 1}'], retries=())
    with pytest.raises(mc.MoodleError):
        client.call("local_ltuse_get_course_manifest")
    assert len(seen) == 1


# --- T028: intake offline validation ------------------------------------------------------------
INTAKE_HEADER = ["email", "firstname", "lastname", "organisation", "country", "protection",
                 "pseudonym", "email_checked", "courses"]


def write_csv(path, rows, header=INTAKE_HEADER):
    """Write rows to a CSV under pytest's tmp_path: outside the repository, never committed."""
    import csv
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, "w", newline="", encoding="utf-8") as fh:
        writer = csv.writer(fh)
        writer.writerow(header)
        for row in rows:
            writer.writerow([row.get(c, "") for c in header])
    return path


@pytest.fixture
def fixture_orgs(monkeypatch):
    """Declared organisations are fixture-a and fixture-b, whatever the repo declares."""
    monkeypatch.setattr(la, "declared_organisations", lambda site_dir: list(ORGS))


@pytest.mark.parametrize("change,expect", [
    ({"email": "not-an-address"}, "email is not an email address"),
    ({"organisation": "fixture-nowhere"}, "is not declared"),
    ({"protection": "secret"}, "protection 'secret' is not one of"),
    ({"protection": "pseudonym"}, "the pseudonym column is needed"),
    ({"pseudonym": "fixture-alias"}, "protection is not pseudonym"),
    ({"protection": "email", "email_checked": "maybe"}, "email_checked 'maybe' must be yes or empty"),
    ({"email_checked": "yes"}, "email_checked is only for a row that asks for protection"),
    ({"courses": "ltct:fixture-course;fixture-course"}, "is not a course idnumber"),
    ({"courses": "ltct:fixture-course:03"}, "is not a course idnumber"),
    ({"firstname": ""}, "firstname is empty"),
    ({"country": "KEN"}, "not a two-letter country code"),
])
def test_intake_offline_problems_refuse_the_whole_file(tmp_path, fixture_orgs, change, expect):
    rows = [intake_row(n) for n in range(2, 5)]
    rows[1].update(change)
    path = write_csv(tmp_path / "ltct-private" / "intake.csv", rows)
    client = FakeClient()
    code, text = run(["intake", str(path)], client=client)
    assert code == 1
    assert "row 3:" in text and expect in text
    assert client.calls == []                    # refused before any call


def test_intake_duplicate_email_is_refused_case_insensitively(tmp_path, fixture_orgs):
    rows = [intake_row(2), intake_row(3, email="LEARNER2@example.org")]
    path = write_csv(tmp_path / "intake.csv", rows)
    client = FakeClient()
    code, text = run(["intake", str(path)], client=client)
    assert code == 1 and "row 3: the same as row 2" in text
    assert client.calls == []


def test_intake_missing_or_unknown_column_is_refused(tmp_path, fixture_orgs):
    missing = write_csv(tmp_path / "a.csv", [intake_row(2)],
                        header=["email", "firstname", "lastname"])
    code, text = run(["intake", str(missing)], client=FakeClient())
    assert code == 1 and "required column 'organisation' is missing" in text
    unknown = write_csv(tmp_path / "b.csv", [intake_row(2)], header=INTAKE_HEADER + ["password"])
    code, text = run(["intake", str(unknown)], client=FakeClient())
    assert code == 1 and "unknown column 'password'" in text


def test_intake_file_inside_a_git_tree_is_never_opened(tmp_path, fixture_orgs):
    (tmp_path / "proj" / ".git").mkdir(parents=True)
    path = write_csv(tmp_path / "proj" / "intake.csv", [intake_row(2)])
    client = FakeClient()
    code, text = run(["intake", str(path)], client=client)
    assert code == 1 and "git working tree" in text
    assert client.calls == []


# --- T029: intake resume, against a fake server ---------------------------------------------------
PATH = ["new", "will_set_org", "will_enrol", "unchanged"]


class IntakeServer(FakeClient):
    """A Moodle that remembers which rows are done, with intake_rules' "further along" rule.

    `state[email]` is absent (new), "half" (created, no organisation: will_set_org), "done"
    (unchanged) or "other" (flagged_other_org). `lose` holds row numbers whose apply succeeds
    on the server but whose answer is lost on the way back; `before_apply` runs once, just
    before the first apply call, to change the server under a run.
    """

    def __init__(self, **kw):
        super().__init__(**kw)
        self.state = {}
        self.lose = set()
        self.before_apply = None

    def outcome(self, email):
        return {None: "new", "half": "will_set_org", "done": "unchanged",
                "other": "flagged_other_org"}[self.state.get(email.lower())]

    def call(self, function, **params):
        if function == "local_ltuse_admin_preview_intake":
            self.calls.append((function, params))
            return {"refusal": "", "rows": [
                {"row": r["row"], "outcome": self.outcome(r["email"]), "reason": "",
                 "key": r["email"] if params["showpeople"] else af.mask_email(r["email"]),
                 "changes": []} for r in params["rows"]]}
        if function == "local_ltuse_admin_apply_intake_row":
            self.calls.append((function, params))
            if self.before_apply:
                self.before_apply(self)
                self.before_apply = None
            row, expected = params["row"], params["expectedoutcome"]
            email = row["email"].lower()
            current = self.outcome(email)
            if expected == current:
                status = "done"
            elif current in PATH and PATH.index(current) > PATH.index(expected):
                status = "already_done" if current == "unchanged" else "done"
            else:
                return {"row": row["row"], "outcome": current, "status": "refused",
                        "reason": "changed since the preview; preview again"}
            self.state[email] = "done"
            if row["row"] in self.lose:
                self.lose.discard(row["row"])
                raise mc.MoodleError("local_ltuse_admin_apply_intake_row",
                                     {"message": "HTTP 503 Service Unavailable"})
            return {"row": row["row"], "outcome": current, "status": status, "reason": ""}
        return super().call(function, **params)


def _intake_file(tmp_path, count=3):
    return write_csv(tmp_path / "ltct-private" / "intake.csv",
                     [intake_row(n) for n in range(2, 2 + count)])


def _preview_code(text):
    return text.rsplit("--confirm ", 1)[1].strip()


def test_intake_preview_then_apply_then_reapply_is_a_no_op(tmp_path, fixture_orgs):
    path = _intake_file(tmp_path)
    server = IntakeServer()
    code, text = run(["intake", str(path)], client=server)
    assert code == 0 and "new" in text
    assert not _calls(server, "local_ltuse_admin_apply_intake_row")
    confirm = _preview_code(text)
    code, text = run(["intake", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0 and "done 3" in text
    # The same command again: every row is unchanged, the code is the same, nothing is sent.
    sent = len(server.calls)
    code, text = run(["intake", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0 and "already done 3" in text
    assert not [c for c in server.calls[sent:] if c[0] == "local_ltuse_admin_apply_intake_row"]


def test_intake_resumes_after_a_partial_apply(tmp_path, fixture_orgs):
    path = _intake_file(tmp_path)
    server = IntakeServer()
    confirm = _preview_code(run(["intake", str(path)], client=server)[1])
    # A run cut off part-way: row 2 finished, row 3 created but not finished.
    server.state["learner2@example.org"] = "done"
    server.state["learner3@example.org"] = "half"
    code, text = run(["intake", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0
    assert "already done" in text
    applied = [c["row"]["row"] for c in _calls(server, "local_ltuse_admin_apply_intake_row")]
    assert applied == [3, 4]                      # row 2 needed no call
    assert all(v == "done" for v in server.state.values())


def test_a_lost_response_then_a_rerun_reports_already_done(tmp_path, fixture_orgs):
    path = _intake_file(tmp_path)
    server = IntakeServer()
    confirm = _preview_code(run(["intake", str(path)], client=server)[1])
    server.lose = {3}
    code, text = run(["intake", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 1 and "failed 1" in text       # the answer for row 3 never arrived
    code, text = run(["intake", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0 and "already done 3" in text


def test_a_retry_racing_the_first_attempt_is_already_done(tmp_path, fixture_orgs):
    path = _intake_file(tmp_path, count=1)
    server = IntakeServer()
    confirm = _preview_code(run(["intake", str(path)], client=server)[1])
    # Between the apply run's preview and its call, another run finished the row.
    server.before_apply = lambda s: s.state.update({"learner2@example.org": "done"})
    code, text = run(["intake", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0 and "already done 1" in text


def test_a_row_that_became_flagged_is_refused(tmp_path, fixture_orgs):
    path = _intake_file(tmp_path)
    server = IntakeServer()
    confirm = _preview_code(run(["intake", str(path)], client=server)[1])
    server.before_apply = lambda s: s.state.update({"learner4@example.org": "other"})
    code, text = run(["intake", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 1
    assert "refused 1" in text and "changed since the preview" in text
    assert server.state.get("learner4@example.org") == "other"


def test_intake_with_courses_sends_them_and_prints_no_gate(tmp_path, fixture_orgs):
    # 002 R13's production gate is gone (Doug, 2026-10-05, scope review): a person who asked
    # for protection waits on the server, row by row, and nobody else is held back.
    path = write_csv(tmp_path / "intake.csv", [intake_row(2, courses="ltct:fixture-course")])
    server = IntakeServer()
    code, text = run(["intake", str(path)], client=server)
    assert code == 0 and "R13" not in text
    sent = _calls(server, "local_ltuse_admin_preview_intake")
    assert sent[0]["rows"][0]["courses"] == ["ltct:fixture-course"]
    assert not _calls(server, "local_ltuse_admin_check")


def test_intake_output_masks_people_unless_asked(tmp_path, fixture_orgs):
    path = _intake_file(tmp_path)
    server = IntakeServer()
    server.state["learner3@example.org"] = "other"
    code, text = run(["intake", str(path)], client=server)
    assert "l***@example.org" in text and "learner3@example.org" not in text
    code, text = run(["intake", str(path), "--show-people"], client=server)
    assert "learner3@example.org" in text


# --- T044/T045: enrol course, unenrol, enrol pathway ----------------------------------------------
class EnrolServer(FakeClient):
    """Cohort sync as the server sees it: course idnumber -> instance state."""

    def __init__(self, **kw):
        super().__init__(items=[{"idnumber": "ltct:fixture-course", "name": "Fixture",
                                 "category": "ltct:published"}], **kw)
        self.instances = {}                     # course -> "enabled" | "disabled"
        self.refuse = set()                     # courses the rules refuse
        self.pathway = None                     # (key, [courses]) once 006 is "installed"
        self.assigned = False

    def pair(self, course, action):
        state = self.instances.get(course)
        if action == "remove":
            return "would_disable" if state == "enabled" else "already"
        if course in self.refuse:
            return "refused"
        return {None: "would_add", "disabled": "would_enable", "enabled": "already"}[state]

    def call(self, function, **params):
        if function == "local_ltuse_admin_preview_cohort_enrolment":
            self.calls.append((function, params))
            if params.get("pathwaykey"):
                if not self.pathway:
                    return {"refusal": "learning pathways are not installed on this site"}
                return {"refusal": "", "members": 3,
                        "assignment": "already" if self.assigned else "would_add",
                        "courses": [{"course": c, "outcome": self.pair(c, "ensure"), "reason": ""}
                                    for c in self.pathway[1]]}
            course = params["courseidnumber"]
            return {"refusal": "", "members": 3, "assignment": "",
                    "courses": [{"course": course, "outcome": self.pair(course, params["action"]),
                                 "reason": ""}]}
        if function == "local_ltuse_admin_apply_cohort_enrolment":
            self.calls.append((function, params))
            course = params["courseidnumber"]
            current = self.pair(course, params["action"])
            if current == "already":
                return {"outcome": "already", "status": "already_done", "reason": ""}
            if current != params["expectedoutcome"]:
                return {"outcome": current, "status": "refused", "reason": "changed"}
            self.instances[course] = "disabled" if params["action"] == "remove" else "enabled"
            return {"outcome": current, "status": "done", "reason": ""}
        if function == "local_ltuse_admin_apply_pathway_assignment":
            self.calls.append((function, params))
            self.assigned = True
            return {"outcome": "would_add", "status": "done", "reason": ""}
        return super().call(function, **params)


def _calls(server, function):
    return [c[1] for c in server.calls if c[0] == function]


def test_enrol_course_previews_then_applies_once():
    server = EnrolServer()
    argv = ["enrol", "course", "--cohort", "ltct:org:fixture-a", "--course", "ltct:fixture-course"]
    code, text = run(argv, client=server)
    assert code == 0 and "would_add" in text and "holds 3 members" in text
    assert "R13" not in text                      # no production gate (2026-10-05)
    assert not _calls(server, "local_ltuse_admin_apply_cohort_enrolment")
    code, text = run(argv + ["--apply", "--confirm", _preview_code(text)], client=server)
    assert code == 0
    assert _calls(server, "local_ltuse_admin_apply_cohort_enrolment") == [
        {"cohortidnumber": "ltct:org:fixture-a", "courseidnumber": "ltct:fixture-course",
         "action": "ensure", "expectedoutcome": "would_add"}]
    code, text = run(argv, client=server)
    assert "already" in text


def test_unenrol_disables_and_has_its_own_code():
    server = EnrolServer()
    server.instances["ltct:fixture-course"] = "enabled"
    enrol = run(["enrol", "course", "--cohort", "ltct:org:fixture-a", "--course",
                 "ltct:fixture-course"], client=server)[1]
    argv = ["unenrol", "--cohort", "ltct:org:fixture-a", "--course", "ltct:fixture-course"]
    code, text = run(argv, client=server)
    assert code == 0 and "would_disable" in text and "grades" in text
    assert _preview_code(text) != _preview_code(enrol)
    code, text = run(argv + ["--apply", "--confirm", _preview_code(text)], client=server)
    assert code == 0 and server.instances["ltct:fixture-course"] == "disabled"


def test_enrol_course_refused_by_the_rules_exits_1():
    server = EnrolServer()
    server.refuse.add("ltct:fixture-course")
    argv = ["enrol", "course", "--cohort", "ltct:mentors", "--course", "ltct:fixture-course"]
    code, text = run(argv, client=server)
    code, text = run(argv + ["--apply", "--confirm", _preview_code(text)], client=server)
    assert code == 1
    assert not _calls(server, "local_ltuse_admin_apply_cohort_enrolment")


def test_enrol_pathway_without_spec_006_is_refused():
    server = EnrolServer()
    code, text = run(["enrol", "pathway", "--cohort", "ltct:org:fixture-a", "--pathway",
                      "competency:fixture"], client=server)
    assert code == 1 and "not installed" in text and "--confirm" not in text


def test_enrol_pathway_assigns_once_then_enrols_each_course():
    server = EnrolServer()
    server.pathway = ("competency:fixture", ["ltct:fixture-course", "ltct:fixture-two"])
    argv = ["enrol", "pathway", "--cohort", "ltct:org:fixture-a", "--pathway",
            "competency:fixture"]
    code, text = run(argv, client=server)
    assert code == 0
    code, text = run(argv + ["--apply", "--confirm", _preview_code(text)], client=server)
    assert code == 0
    assert len(_calls(server, "local_ltuse_admin_apply_pathway_assignment")) == 1
    applied = _calls(server, "local_ltuse_admin_apply_cohort_enrolment")
    assert [a["courseidnumber"] for a in applied] == ["ltct:fixture-course", "ltct:fixture-two"]
    assert all(a["pathwaykey"] == "competency:fixture" for a in applied)
    # Again: the assignment is held and both courses are enrolled; nothing more is assigned.
    code, text = run(argv, client=server)
    assert code == 0
    code, text = run(argv + ["--apply", "--confirm", _preview_code(text)], client=server)
    assert code == 0 and len(_calls(server, "local_ltuse_admin_apply_pathway_assignment")) == 1


def test_enrol_pathway_with_a_refused_course_touches_nothing():
    server = EnrolServer()
    server.pathway = ("competency:fixture", ["ltct:fixture-course", "ltct:fixture-two"])
    server.refuse.add("ltct:fixture-two")
    code, text = run(["enrol", "pathway", "--cohort", "ltct:org:fixture-a", "--pathway",
                      "competency:fixture"], client=server)
    assert code == 1 and "ltct:fixture-two" in text and "--confirm" not in text
    assert not _calls(server, "local_ltuse_admin_apply_pathway_assignment")


# --- T049: suspension, move and managers files, and their resume behaviour ------------------------
class PeopleServer(FakeClient):
    """Accounts as the server sees them, for suspend/reactivate, move and managers.

    `people[email]` is {"suspended": bool, "org": key, "cohorts": set of idnumbers}. Every
    apply follows the server's rule: the previewed outcome is applied; one further along
    (unchanged, moved) is already_done; anything else is refused. `lose` holds row numbers whose
    apply succeeds but whose answer is lost; `lost[email]` lists courses a move would lose.
    """

    def __init__(self, **kw):
        super().__init__(**kw)
        self.people = {}
        self.lose = set()
        self.lost = {}

    def add(self, n, **over):
        person = {"suspended": False, "org": "fixture-a", "cohorts": set()}
        person.update(over)
        self.people["learner%d@example.org" % n] = person

    def key(self, email, show):
        return email if show else af.mask_email(email)

    # Each kind's classification, as the server's services make it.
    def suspension(self, row, suspend):
        person = self.people.get(row["email"].lower())
        if person is None:
            return "rejected", "no account has this email"
        return ("unchanged" if person["suspended"] == suspend else "would_change"), ""

    def members(self, row):
        person = self.people.get(row["email"].lower())
        if person is None:
            return "rejected", "no account has this email"
        member = row["cohortidnumber"] in person["cohorts"]
        changes = (not member) if row["action"] == "add" else member
        note = ""
        if changes and row["action"] == "add" and \
                row["cohortidnumber"] == "ltct:org:%s:managers" % person["org"]:
            note = "note: this person is in %s, the organisation they will manage" % person["org"]
        return ("would_change" if changes else "unchanged"), note

    def move(self, row):
        email = row["email"].lower()
        person = self.people.get(email)
        if person is None:
            return "rejected", "no account has this email", []
        if person["org"] == row["organisation"]:
            return "moved", "", []
        if email in self.lost:
            return "lost", "the move would leave them without access to %s" % \
                ", ".join(self.lost[email]), [{"course": c, "outcome": "lost"} for c in self.lost[email]]
        return "would_move", "", [{"course": "ltct:fixture-shared", "outcome": "kept"},
                                  {"course": "ltct:fixture-a-only", "outcome": "suspended_by_rule"}]

    def _answer(self, row, expected, current, path, write):
        if expected in path and current == expected == path[0]:
            write()
            status = "done"
        elif expected in path and current in path and path.index(current) > path.index(expected):
            status = "already_done"
        else:
            return {"row": row["row"], "outcome": current, "status": "refused",
                    "reason": "changed since the preview; preview again"}
        if row["row"] in self.lose:
            self.lose.discard(row["row"])
            raise mc.MoodleError("apply", {"message": "HTTP 503 Service Unavailable"})
        return {"row": row["row"], "outcome": current, "status": status, "reason": ""}

    def call(self, function, **params):
        show = params.get("showpeople", False)
        if function == "local_ltuse_admin_preview_suspension":
            self.calls.append((function, params))
            rows = []
            for r in params["rows"]:
                outcome, reason = self.suspension(r, params["suspend"])
                rows.append({"row": r["row"], "key": self.key(r["email"], show), "outcome": outcome,
                             "reason": reason, "changes": []})
            return {"refusal": "", "rows": rows}
        if function == "local_ltuse_admin_apply_suspension":
            self.calls.append((function, params))
            row, suspend = params["row"], params["suspend"]
            current, _ = self.suspension(row, suspend)
            return self._answer(row, params["expectedoutcome"], current,
                                ["would_change", "unchanged"],
                                lambda: self.people[row["email"].lower()].update(suspended=suspend))
        if function == "local_ltuse_admin_preview_cohort_members":
            self.calls.append((function, params))
            rows = []
            for r in params["rows"]:
                outcome, reason = self.members(r)
                rows.append({"row": r["row"], "key": self.key(r["email"], show), "outcome": outcome,
                             "reason": reason, "changes": []})
            return {"refusal": "", "rows": rows}
        if function == "local_ltuse_admin_apply_cohort_members":
            self.calls.append((function, params))
            row = params["row"]
            current, _ = self.members(row)
            cohorts = self.people.get(row["email"].lower(), {}).get("cohorts", set())
            write = (lambda: cohorts.add(row["cohortidnumber"])) if row["action"] == "add" \
                else (lambda: cohorts.discard(row["cohortidnumber"]))
            return self._answer(row, params["expectedoutcome"], current,
                                ["would_change", "unchanged"], write)
        if function == "local_ltuse_admin_preview_move":
            self.calls.append((function, params))
            rows = []
            for r in params["rows"]:
                outcome, reason, courses = self.move(r)
                rows.append({"row": r["row"], "key": self.key(r["email"], show), "outcome": outcome,
                             "reason": reason, "changes": [], "courses": courses})
            return {"refusal": "", "rows": rows}
        if function == "local_ltuse_admin_apply_move":
            self.calls.append((function, params))
            row = params["row"]
            current, _, _ = self.move(row)
            return self._answer(row, params["expectedoutcome"], current, ["would_move", "moved"],
                                lambda: self.people[row["email"].lower()].update(
                                    org=row["organisation"]))
        return super().call(function, **params)


def _file(tmp_path, name, header, rows):
    return write_csv(tmp_path / "ltct-private" / name, rows, header=header)


def _suspension_file(tmp_path, count=3):
    return _file(tmp_path, "leavers.csv", ["email"],
                 [{"email": "learner%d@example.org" % n} for n in range(2, 2 + count)])


@pytest.mark.parametrize("kind,header,rows,expect", [
    ("suspend", ["email"], [{"email": "not-an-address"}], "row 2: email is not an email address"),
    ("suspend", ["email"], [{"email": "a@example.org"}, {"email": "A@example.org"}],
     "row 3: the same as row 2"),
    ("suspend", ["email", "organisation"], [{"email": "a@example.org"}], "unknown column"),
    ("move", ["email", "organisation"], [{"email": "a@example.org", "organisation": "fixture-nowhere"}],
     "row 2: organisation 'fixture-nowhere' is not declared"),
    ("move", ["email", "organisation"], [{"email": "a@example.org", "organisation": "fixture-b"},
                                         {"email": "A@EXAMPLE.org", "organisation": "fixture-a"}],
     "row 3: the same as row 2"),
    ("move", ["email"], [{"email": "a@example.org"}], "required column 'organisation' is missing"),
    ("managers", ["email", "cohort", "action"],
     [{"email": "a@example.org", "cohort": "ltct:org:fixture-a", "action": "add"}],
     "must be ltct:org:<key>:managers or ltct:mentors"),
    ("managers", ["email", "cohort", "action"],
     [{"email": "a@example.org", "cohort": "ltct:org:fixture-nowhere:managers", "action": "add"}],
     "is not declared"),
    ("managers", ["email", "cohort", "action"],
     [{"email": "a@example.org", "cohort": "ltct:mentors", "action": "promote"}],
     "action 'promote' is not add or remove"),
    ("managers", ["email", "cohort", "action"],
     [{"email": "a@example.org", "cohort": "ltct:mentors", "action": "add"},
      {"email": "a@example.org", "cohort": "ltct:mentors", "action": "remove"}],
     "row 3: the same as row 2"),
])
def test_routine_files_are_checked_offline(tmp_path, fixture_orgs, kind, header, rows, expect):
    path = _file(tmp_path, "people.csv", header, rows)
    server = PeopleServer()
    code, text = run([kind, str(path)], client=server)
    assert code == 1 and expect in text
    assert server.calls == []                    # refused before any call


def test_suspend_previews_applies_and_reruns_as_already_done(tmp_path, fixture_orgs):
    server = PeopleServer()
    server.add(2)
    server.add(3, suspended=True)
    server.add(4)
    path = _suspension_file(tmp_path)
    code, text = run(["suspend", str(path)], client=server)
    assert code == 0 and "would_change" in text and "grades" in text
    assert "learner2@example.org" not in text
    assert not _calls(server, "local_ltuse_admin_apply_suspension")
    confirm = _preview_code(text)
    code, text = run(["suspend", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0
    applied = _calls(server, "local_ltuse_admin_apply_suspension")
    assert [(a["row"]["row"], a["suspend"], a["expectedoutcome"]) for a in applied] == \
        [(2, True, "would_change"), (4, True, "would_change")]       # one row per call
    assert all(p["suspended"] for p in server.people.values())
    # The same command again: every row is unchanged, the code still matches, nothing is sent.
    sent = len(server.calls)
    code, text = run(["suspend", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0 and "already done 3" in text
    assert not [c for c in server.calls[sent:] if c[0] == "local_ltuse_admin_apply_suspension"]


def test_suspend_code_never_confirms_a_reactivate(tmp_path, fixture_orgs):
    server = PeopleServer()
    server.add(2, suspended=True)
    path = _suspension_file(tmp_path)
    code, text = run(["suspend", str(path)], client=server)
    assert code == 0
    confirm = _preview_code(text)
    code, text = run(["reactivate", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 1
    assert not _calls(server, "local_ltuse_admin_apply_suspension")
    assert server.people["learner2@example.org"]["suspended"]


def test_suspend_lost_response_then_rerun_reports_already_done(tmp_path, fixture_orgs):
    server = PeopleServer()
    for n in (2, 3, 4):
        server.add(n)
    path = _suspension_file(tmp_path)
    confirm = _preview_code(run(["suspend", str(path)], client=server)[1])
    server.lose = {3}
    code, text = run(["suspend", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 1 and "failed 1" in text
    code, text = run(["suspend", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0 and "already done 3" in text


def test_suspend_an_unknown_account_is_not_applied(tmp_path, fixture_orgs):
    server = PeopleServer()
    server.add(2)
    path = _suspension_file(tmp_path, count=2)
    code, text = run(["suspend", str(path)], client=server)
    assert "no account has this email" in text
    code, text = run(["suspend", str(path), "--apply", "--confirm", _preview_code(text)],
                     client=server)
    assert code == 1
    assert [a["row"]["row"] for a in _calls(server, "local_ltuse_admin_apply_suspension")] == [2]


def test_reactivate_one_person_by_email(fixture_orgs):
    server = PeopleServer()
    server.add(2, suspended=True)
    argv = ["reactivate", "--email", "learner2@example.org"]
    code, text = run(argv, client=server)
    assert code == 0 and "would_change" in text
    preview = _calls(server, "local_ltuse_admin_preview_suspension")[0]
    assert preview["suspend"] is False and preview["rows"] == [{"row": 1, "email": "learner2@example.org"}]
    code, text = run(argv + ["--apply", "--confirm", _preview_code(text)], client=server)
    assert code == 0 and server.people["learner2@example.org"]["suspended"] is False


def test_reactivate_a_malformed_email_is_refused_offline(fixture_orgs):
    server = PeopleServer()
    code, text = run(["reactivate", "--email", "not-an-address"], client=server)
    assert code == 1 and "--email: email is not an email address" in text
    assert server.calls == []


def _move_file(tmp_path, rows):
    return _file(tmp_path, "move.csv", ["email", "organisation"], rows)


def test_move_previews_per_course_then_applies_with_the_courses_it_showed(tmp_path, fixture_orgs):
    server = PeopleServer()
    server.add(2)
    server.add(3)
    path = _move_file(tmp_path, [{"email": "learner2@example.org", "organisation": "fixture-b"},
                                 {"email": "learner3@example.org", "organisation": "fixture-b"}])
    code, text = run(["move", str(path)], client=server)
    assert code == 0 and "would_move" in text
    assert "Per course:" in text and "ltct:fixture-a-only" in text and "suspended_by_rule 2" in text
    confirm = _preview_code(text)
    code, text = run(["move", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0
    applied = _calls(server, "local_ltuse_admin_apply_move")
    assert [a["row"]["row"] for a in applied] == [2, 3]
    assert applied[0]["expectedcourses"] == [
        {"course": "ltct:fixture-shared", "outcome": "kept"},
        {"course": "ltct:fixture-a-only", "outcome": "suspended_by_rule"}]
    assert all(p["org"] == "fixture-b" for p in server.people.values())
    # Again: both are moved; the code is the same (would_move and moved share a class).
    sent = len(server.calls)
    code, text = run(["move", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0 and "already done 2" in text
    assert not [c for c in server.calls[sent:] if c[0] == "local_ltuse_admin_apply_move"]


def test_move_with_a_lost_course_refuses_that_learner_only(tmp_path, fixture_orgs):
    server = PeopleServer()
    server.add(2)
    server.add(3)
    server.lost["learner3@example.org"] = ["ltct:fixture-shared"]
    path = _move_file(tmp_path, [{"email": "learner2@example.org", "organisation": "fixture-b"},
                                 {"email": "learner3@example.org", "organisation": "fixture-b"}])
    code, text = run(["move", str(path)], client=server)
    assert "lost" in text and "without access to ltct:fixture-shared" in text
    code, text = run(["move", str(path), "--apply", "--confirm", _preview_code(text)],
                     client=server)
    assert code == 1
    assert [a["row"]["row"] for a in _calls(server, "local_ltuse_admin_apply_move")] == [2]
    assert server.people["learner3@example.org"]["org"] == "fixture-a"


def test_move_that_became_lost_since_the_preview_is_refused(tmp_path, fixture_orgs):
    server = PeopleServer()
    server.add(2)
    path = _move_file(tmp_path, [{"email": "learner2@example.org", "organisation": "fixture-b"}])
    confirm = _preview_code(run(["move", str(path)], client=server)[1])
    server.lost["learner2@example.org"] = ["ltct:fixture-shared"]
    code, text = run(["move", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 1 and "does not match" in text
    assert not _calls(server, "local_ltuse_admin_apply_move")


def _managers_file(tmp_path, rows):
    return _file(tmp_path, "managers.csv", ["email", "cohort", "action"], rows)


def test_managers_note_apply_and_rerun(tmp_path, fixture_orgs):
    server = PeopleServer()
    server.add(2, org="fixture-a")
    server.add(3, org="fixture-b", cohorts={"ltct:mentors"})
    path = _managers_file(tmp_path, [
        {"email": "learner2@example.org", "cohort": "ltct:org:fixture-a:managers", "action": "add"},
        {"email": "learner2@example.org", "cohort": "ltct:org:fixture-b:managers", "action": "add"},
        {"email": "learner3@example.org", "cohort": "ltct:mentors", "action": "remove"},
    ])
    code, text = run(["managers", str(path)], client=server)
    assert code == 0 and "would_change" in text
    assert "the organisation they will manage" in text          # the own-organisation note
    assert text.count("will manage") == 1
    confirm = _preview_code(text)
    code, text = run(["managers", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0
    assert len(_calls(server, "local_ltuse_admin_apply_cohort_members")) == 3
    assert server.people["learner2@example.org"]["cohorts"] == {"ltct:org:fixture-a:managers",
                                                                "ltct:org:fixture-b:managers"}
    assert server.people["learner3@example.org"]["cohorts"] == set()
    sent = len(server.calls)
    code, text = run(["managers", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 0 and "already done 3" in text
    assert not [c for c in server.calls[sent:] if c[0] == "local_ltuse_admin_apply_cohort_members"]


def test_managers_removing_a_non_member_sends_nothing(tmp_path, fixture_orgs):
    server = PeopleServer()
    server.add(2)
    path = _managers_file(tmp_path, [
        {"email": "learner2@example.org", "cohort": "ltct:mentors", "action": "remove"}])
    code, text = run(["managers", str(path)], client=server)
    assert code == 0 and "unchanged" in text
    code, text = run(["managers", str(path), "--apply", "--confirm", _preview_code(text)],
                     client=server)
    assert code == 0 and not _calls(server, "local_ltuse_admin_apply_cohort_members")


# --- T053: enrol mirror ---------------------------------------------------------------------------
class MirrorServer(EnrolServer):
    def __init__(self, **kw):
        super().__init__(**kw)
        self.mirror = {"fixture-a": ["ltct:fixture-course", "ltct:fixture-two"]}

    def call(self, function, **params):
        if function == "local_ltuse_admin_preview_cohort_enrolment" and params.get("mirrorfrom"):
            self.calls.append((function, params))
            return {"refusal": "", "members": 3, "assignment": "",
                    "courses": [{"course": c, "outcome": self.pair(c, "ensure"), "reason": ""}
                                for c in self.mirror.get(params["mirrorfrom"], [])]}
        return super().call(function, **params)


def test_enrol_mirror_enrols_each_shared_course_once(fixture_orgs):
    server = MirrorServer()
    server.instances["ltct:fixture-two"] = "disabled"
    argv = ["enrol", "mirror", "--from", "fixture-a", "--to", "fixture-b"]
    code, text = run(argv, client=server)
    assert code == 0 and "would_add" in text and "would_enable" in text
    assert "R13" not in text
    preview = _calls(server, "local_ltuse_admin_preview_cohort_enrolment")[0]
    assert preview == {"cohortidnumber": "ltct:org:fixture-b", "mirrorfrom": "fixture-a"}
    code, text = run(argv + ["--apply", "--confirm", _preview_code(text)], client=server)
    assert code == 0
    applied = _calls(server, "local_ltuse_admin_apply_cohort_enrolment")
    assert [(a["courseidnumber"], a["expectedoutcome"]) for a in applied] == [
        ("ltct:fixture-course", "would_add"), ("ltct:fixture-two", "would_enable")]
    assert all(a["cohortidnumber"] == "ltct:org:fixture-b" and a["action"] == "ensure"
               for a in applied)


def test_enrol_mirror_with_nothing_to_mirror_sends_nothing(fixture_orgs):
    server = MirrorServer()
    server.mirror = {}
    code, text = run(["enrol", "mirror", "--from", "fixture-a", "--to", "fixture-b"], client=server)
    assert code == 0 and "nothing to mirror" in text and "--confirm" not in text


@pytest.mark.parametrize("frm,to", [("fixture-a", "fixture-a"), ("fixture-nowhere", "fixture-b"),
                                    ("fixture-a", "fixture-nowhere")])
def test_enrol_mirror_refuses_a_bad_pair_offline(fixture_orgs, frm, to):
    server = MirrorServer()
    code, text = run(["enrol", "mirror", "--from", frm, "--to", to], client=server)
    assert code == 1
    assert not _calls(server, "local_ltuse_admin_preview_cohort_enrolment")


# --- T055: summary --------------------------------------------------------------------------------
class SummaryServer(FakeClient):
    def call(self, function, **params):
        if function == "local_ltuse_admin_summary":
            self.calls.append((function, params))
            show = params["showpeople"]
            people = ["learner2@example.org", "learner3@example.org"]
            return {
                "refusal": "", "pathways": False,
                "cohorts": [{"idnumber": "ltct:org:fixture-a", "members": 2, "suspended": 1},
                            {"idnumber": "ltct:org:fixture-a:managers", "members": 0, "suspended": 0}],
                "people": [{"cohort": "ltct:org:fixture-a",
                            "key": e if show else af.mask_email(e), "suspended": e == people[1]}
                           for e in people],
                "courses": [{"cohort": "ltct:org:fixture-a", "course": "ltct:fixture-course",
                             "category": "ltct:published", "enabled": True, "enrolled": 2,
                             "viapathway": True, "notonpathway": True}],
            }
        return super().call(function, **params)


def test_summary_on_screen_is_masked(fixture_orgs):
    code, text = run(["summary", "--org", "fixture-a"], client=SummaryServer())
    assert code == 0
    assert "2 members, 1 suspended" in text and "l***@example.org" in text
    assert "learner2@example.org" not in text
    assert "ON NO PATHWAY" in text


def test_summary_out_is_refused_in_the_repo_and_written_outside(tmp_path, fixture_orgs):
    server = SummaryServer()
    code, text = run(["summary", "--org", "fixture-a", "--out", str(REPO / "summary.txt")],
                     client=server)
    assert code == 1 and "inside this repository" in text
    assert server.calls == [] and not (REPO / "summary.txt").exists()
    out = tmp_path / "ltct-private" / "summary.txt"
    code, text = run(["summary", "--org", "fixture-a", "--out", str(out)], client=server)
    assert code == 0 and out.exists()
    assert "l***@example.org" in out.read_text(encoding="utf-8")
    assert "l***@example.org" not in text and "2 members" in text   # people only in the file
    code, text = run(["summary", "--org", "fixture-a", "--out", str(out)], client=server)
    assert code == 1 and "already exists" in text


def test_summary_refuses_an_undeclared_organisation(fixture_orgs):
    server = SummaryServer()
    code, text = run(["summary", "--org", "fixture-nowhere"], client=server)
    assert code == 1 and server.calls == []


# --- T061, T066: mentors and course mentors -------------------------------------------------------
class MentorServer(FakeClient):
    """Mentor relationships and course-mentor records as the server keeps them.

    `pairs` holds (learner email, mentor email); `records` holds (course, mentor email, learner
    email or cohort). Each apply follows the server's rule: would_change is applied; unchanged,
    after a would_change preview, is already_done; anything else is refused.
    """

    def __init__(self, **kw):
        super().__init__(**kw)
        self.pairs = set()
        self.records = set()
        self.mentors = {"mentor1@example.org", "mentor2@example.org"}

    @staticmethod
    def ref(learner):
        return hashlib.sha256(("fixture:" + learner).encode("utf-8")).hexdigest()[:16]

    def assign_state(self, row):
        if row["mentoremail"].lower() not in self.mentors:
            return "rejected", "the mentor is not in ltct:mentors"
        pair = (row["learneremail"].lower(), row["mentoremail"].lower())
        return ("unchanged" if pair in self.pairs else "would_change"), ""

    def learners_of(self, mentor):
        return sorted((self.ref(learner), learner) for learner, m in self.pairs if m == mentor)

    @staticmethod
    def record_key(row):
        return (row["courseidnumber"], row["mentoremail"].lower(),
                row["learneremail"].lower() or row["cohortidnumber"])

    def call(self, function, **params):
        show = params.get("showpeople", False)
        if function == "local_ltuse_admin_preview_mentors":
            self.calls.append((function, params))
            if params.get("endmentoremail"):
                mentor = params["endmentoremail"].lower()
                return {"refusal": "", "rows": [
                    {"row": n, "key": learner if show else af.mask_email(learner),
                     "outcome": "would_change", "reason": "", "changes": ["end:mentor"], "ref": ref}
                    for n, (ref, learner) in enumerate(self.learners_of(mentor), start=1)]}
            rows = []
            for r in params["rows"]:
                outcome, reason = self.assign_state(r)
                rows.append({"row": r["row"], "key": af.mask_email(r["learneremail"]),
                             "outcome": outcome, "reason": reason, "changes": [], "ref": ""})
            return {"refusal": "", "rows": rows}
        if function == "local_ltuse_admin_apply_mentors":
            self.calls.append((function, params))
            row, expected = params["row"], params["expectedoutcome"]
            if params.get("endmentoremail"):
                mentor = params["endmentoremail"].lower()
                learner = dict(self.learners_of(mentor)).get(params["ref"])
                if learner is None:
                    return {"row": row["row"], "outcome": "unchanged", "status": "already_done",
                            "reason": ""}
                self.pairs.discard((learner, mentor))
                return {"row": row["row"], "outcome": "would_change", "status": "done", "reason": ""}
            current, _ = self.assign_state(row)
            if current == "unchanged":
                return {"row": row["row"], "outcome": current, "status": "already_done", "reason": ""}
            if current != expected:
                return {"row": row["row"], "outcome": current, "status": "refused",
                        "reason": "changed since the preview; preview again"}
            self.pairs.add((row["learneremail"].lower(), row["mentoremail"].lower()))
            return {"row": row["row"], "outcome": current, "status": "done", "reason": ""}
        if function == "local_ltuse_admin_preview_course_mentors":
            self.calls.append((function, params))
            rows = []
            for r in params["rows"]:
                exists = self.record_key(r) in self.records
                changes = exists if params["remove"] else not exists
                rows.append({"row": r["row"], "key": af.mask_email(r["mentoremail"]),
                             "outcome": "would_change" if changes else "unchanged", "reason": "",
                             "changes": []})
            return {"refusal": "", "rows": rows}
        if function == "local_ltuse_admin_apply_course_mentors":
            self.calls.append((function, params))
            row, key = params["row"], self.record_key(params["row"])
            if params["remove"]:
                self.records.discard(key)
            else:
                self.records.add(key)
            return {"row": row["row"], "outcome": params["expectedoutcome"], "status": "done",
                    "reason": ""}
        return super().call(function, **params)


def _mentors_file(tmp_path, rows):
    return _file(tmp_path, "mentors.csv", ["learner_email", "mentor_email"], rows)


def _course_mentors_file(tmp_path, rows):
    return _file(tmp_path, "course-mentors.csv",
                 ["course", "mentor_email", "learner_email", "cohort"], rows)


def test_mentors_assign_previews_applies_and_reruns_as_already_done(tmp_path, fixture_orgs):
    server = MentorServer()
    path = _mentors_file(tmp_path, [
        {"learner_email": "learner2@example.org", "mentor_email": "mentor1@example.org"},
        {"learner_email": "learner3@example.org", "mentor_email": "mentor1@example.org"},
        {"learner_email": "learner4@example.org", "mentor_email": "fixture-staff@example.org"},
    ])
    code, text = run(["mentors", "assign", str(path)], client=server)
    assert code == 0 and "would_change" in text and "not in ltct:mentors" in text
    assert "learner2@example.org" not in text                    # masked
    assert not _calls(server, "local_ltuse_admin_apply_mentors")
    confirm = _preview_code(text)
    code, text = run(["mentors", "assign", str(path), "--apply", "--confirm", confirm], client=server)
    assert code == 1                                             # the rejected row is not applied
    sent = _calls(server, "local_ltuse_admin_apply_mentors")
    assert [c["row"]["row"] for c in sent] == [2, 3]             # one row per call
    assert server.pairs == {("learner2@example.org", "mentor1@example.org"),
                            ("learner3@example.org", "mentor1@example.org")}
    code, text = run(["mentors", "assign", str(path)], client=server)
    assert "unchanged" in text and _preview_code(text) == confirm   # progress keeps the code


@pytest.mark.parametrize("rows,expect", [
    ([{"learner_email": "a@example.org", "mentor_email": "A@example.org"}],
     "a learner cannot be their own mentor"),
    ([{"learner_email": "a@example.org", "mentor_email": "b@example.org"},
      {"learner_email": "A@example.org", "mentor_email": "b@example.org"}], "row 3: the same as row 2"),
    ([{"learner_email": "a@example.org", "mentor_email": "not-an-address"}],
     "mentor_email is not an email address"),
])
def test_mentors_file_is_checked_offline(tmp_path, fixture_orgs, rows, expect):
    server = MentorServer()
    code, text = run(["mentors", "assign", str(_mentors_file(tmp_path, rows))], client=server)
    assert code == 1 and expect in text and server.calls == []


def test_mentors_end_lists_learners_and_sends_each_reference_back(fixture_orgs):
    server = MentorServer()
    server.pairs = {("learner2@example.org", "mentor1@example.org"),
                    ("learner3@example.org", "mentor1@example.org"),
                    ("learner4@example.org", "mentor2@example.org")}
    code, text = run(["mentors", "end", "--mentor", "mentor1@example.org"], client=server)
    assert code == 0 and "2 rows from --mentor" in text and "would_change" in text
    confirm = _preview_code(text)
    code, text = run(["mentors", "end", "--mentor", "mentor1@example.org", "--apply", "--confirm",
                      confirm], client=server)
    assert code == 0 and "done 2" in text
    sent = _calls(server, "local_ltuse_admin_apply_mentors")
    assert len(sent) == 2 and all(c["ref"] and c["endmentoremail"] == "mentor1@example.org"
                                  for c in sent)
    assert server.pairs == {("learner4@example.org", "mentor2@example.org")}


def test_mentors_end_code_changes_when_the_learners_change(fixture_orgs):
    server = MentorServer()
    server.pairs = {("learner2@example.org", "mentor1@example.org")}
    code, text = run(["mentors", "end", "--mentor", "mentor1@example.org"], client=server)
    confirm = _preview_code(text)
    server.pairs.add(("learner3@example.org", "mentor1@example.org"))   # a new mentee since
    code, text = run(["mentors", "end", "--mentor", "mentor1@example.org", "--apply", "--confirm",
                      confirm], client=server)
    assert code == 1 and "does not match" in text
    assert not _calls(server, "local_ltuse_admin_apply_mentors")


def test_mentors_end_refuses_a_malformed_email_offline(fixture_orgs):
    server = MentorServer()
    code, text = run(["mentors", "end", "--mentor", "not-an-address"], client=server)
    assert code == 1 and "not an email address" in text and server.calls == []


def test_course_mentors_record_then_remove_need_their_own_codes(tmp_path, fixture_orgs):
    server = MentorServer()
    path = _course_mentors_file(tmp_path, [
        {"course": "ltct:fixture-shared", "mentor_email": "mentor1@example.org",
         "learner_email": "learner2@example.org"},
        {"course": "ltct:fixture-shared", "mentor_email": "mentor2@example.org",
         "cohort": "ltct:org:fixture-a"},
    ])
    code, text = run(["course-mentors", str(path)], client=server)
    assert code == 0
    code, text = run(["course-mentors", str(path), "--apply", "--confirm", _preview_code(text)],
                     client=server)
    assert code == 0 and len(server.records) == 2
    sent = _calls(server, "local_ltuse_admin_apply_course_mentors")
    assert [c["row"]["cohortidnumber"] for c in sent] == ["", "ltct:org:fixture-a"]
    assert all(c["remove"] is False for c in sent)
    # Every record is there now, so an add preview says unchanged. Its code must not confirm
    # a removal.
    code, text = run(["course-mentors", str(path)], client=server)
    unchanged_code = _preview_code(text)
    code, text = run(["course-mentors", str(path), "--remove", "--apply", "--confirm",
                      unchanged_code], client=server)
    assert code == 1 and "does not match" in text and len(server.records) == 2
    code, text = run(["course-mentors", str(path), "--remove"], client=server)
    assert "--remove --apply --confirm" in text
    code, text = run(["course-mentors", str(path), "--remove", "--apply", "--confirm",
                      _preview_code(text)], client=server)
    assert code == 0 and server.records == set()


@pytest.mark.parametrize("row,expect", [
    ({"course": "ltct:fixture-shared", "mentor_email": "m@example.org"},
     "give exactly one of learner_email or cohort"),
    ({"course": "ltct:fixture-shared", "mentor_email": "m@example.org",
      "learner_email": "l@example.org", "cohort": "ltct:org:fixture-a"},
     "give exactly one of learner_email or cohort"),
    ({"course": "fixture-shared", "mentor_email": "m@example.org", "learner_email": "l@example.org"},
     "is not a course idnumber"),
])
def test_course_mentors_file_is_checked_offline(tmp_path, fixture_orgs, row, expect):
    server = MentorServer()
    code, text = run(["course-mentors", str(_course_mentors_file(tmp_path, [row]))], client=server)
    assert code == 1 and expect in text and server.calls == []


def test_intake_payload_carries_email_checked():
    assert la.intake_payload(intake_row(2, protection="email", email_checked="Yes"))["emailchecked"] is True
    assert la.intake_payload(intake_row(2, protection="email"))["emailchecked"] is False
