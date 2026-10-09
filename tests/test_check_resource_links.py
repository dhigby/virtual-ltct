"""The resource link check (spec 014, contracts/check-resource-links-cli.md).

Run: python -m pytest tests/. No network: urlopen is replaced.
"""
import io
import pathlib
import sys
import urllib.error

import pytest

REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import check_resource_links as c  # noqa: E402


class Response:
    def __init__(self, status=200):
        self.status = status

    def __enter__(self):
        return self

    def __exit__(self, *a):
        return False


def http_error(code):
    return urllib.error.HTTPError("u", code, "x", {}, io.BytesIO())


def fake(answers):
    """answers: {(method, url): status | exception}."""
    def urlopen(req, timeout):
        a = answers[(req.get_method(), req.full_url)]
        if isinstance(a, Exception):
            raise a
        if a >= 400:
            raise http_error(a)
        return Response(a)
    return urlopen


def test_working_url(monkeypatch):
    monkeypatch.setattr(c.urllib.request, "urlopen", fake({("HEAD", "https://a/"): 200}))
    assert c.check("https://a/") is None


def test_404_is_broken(monkeypatch):
    monkeypatch.setattr(c.urllib.request, "urlopen",
                        fake({("HEAD", "https://a/"): 404, ("GET", "https://a/"): 404}))
    assert c.check("https://a/") == "404"


def test_head_refused_then_get_works(monkeypatch):
    monkeypatch.setattr(c.urllib.request, "urlopen",
                        fake({("HEAD", "https://a/"): 405, ("GET", "https://a/"): 200}))
    assert c.check("https://a/") is None


def test_bot_screen_is_not_broken(monkeypatch):
    monkeypatch.setattr(c.urllib.request, "urlopen",
                        fake({("HEAD", "https://a/"): 403, ("GET", "https://a/"): 403}))
    assert c.check("https://a/") is None


def test_timeout_is_broken(monkeypatch):
    err = urllib.error.URLError(TimeoutError("timed out"))
    monkeypatch.setattr(c.urllib.request, "urlopen",
                        fake({("HEAD", "https://a/"): err, ("GET", "https://a/"): err}))
    assert "timed out" in c.check("https://a/")


def write(tmp_path, text):
    p = tmp_path / "resources.yaml"
    p.write_text(text, encoding="utf-8")
    return p


def test_exit_codes(monkeypatch, tmp_path, capsys):
    monkeypatch.setattr(c, "check", lambda url: None if url == "https://ok/" else "404")
    good = write(tmp_path, "- {title: A, url: 'https://ok/'}\n")
    assert c.main(good) == 0
    assert capsys.readouterr().out.strip() == "0 broken link(s) of 1 checked."

    bad = write(tmp_path, "- {title: A, url: 'https://ok/'}\n- {title: B, url: 'https://x/'}\n")
    assert c.main(bad) == 1
    out = capsys.readouterr().out
    assert "BROKEN 404  https://x/  (B)" in out and "1 broken link(s) of 2 checked." in out

    assert c.main(tmp_path / "missing.yaml") == 2
