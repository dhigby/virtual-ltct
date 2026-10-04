"""Unit tests for scripts/ltct_admin.py and scripts/admin_files.py (spec 008). Run: python -m pytest tests/

No network and no file of real people: Moodle is a FakeClient (the pattern of
tests/test_publish_moodle.py), every address is @example.org, every organisation key is
fixture-*, and rows are built in memory. No CSV fixture is ever committed (constitution III);
a test that needs a file on disk writes it under pytest's tmp_path, outside the repository.
"""
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
           "protection": "", "pseudonym": "", "courses": ""}
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
        "email,firstname,lastname,organisation,country,protection,pseudonym,courses"
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
    ("courses", "ltct:fixture-course"), ("pseudonym", "fixture-alias"),
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
