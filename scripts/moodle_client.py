#!/usr/bin/env python3
"""A thin REST client for the Moodle web service -- the lower half of the publisher.

It knows Moodle's wire protocol and nothing about courses, lessons or pedagogy.
scripts/moodle_payload.py is the upper half and knows only the reverse. Replacing Moodle
means replacing this file, scripts/moodle_xml.py and the plugin, and nothing above them.

TWO THINGS ABOUT MOODLE'S API THAT BITE

1. It answers 200 OK on failure. An error comes back as a normal JSON body with an
   `exception` key, so a client that only checks the HTTP status reports success while
   nothing happened. `call()` raises on that key -- which is the single most important
   line in this file.

2. Its REST protocol is form-encoded, not JSON, and nested structures are flattened into
   PHP-style bracket names: courses[0][fullname]=... `_flatten` does that.

CREDENTIALS come from the environment and never from a file. INTENT.md makes this a hard
constraint: "The repo is public. No credentials, no learner PII."

    MOODLE_URL     e.g. https://moodle.example.org
    MOODLE_TOKEN   a token for the 'LTC curriculum publishing' service

Usage as a CLI, to check a server is reachable and the token works:
  python scripts/moodle_client.py --whoami
"""
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid

DEFAULT_TIMEOUT = 120

# Identify the client honestly -- and avoid the default one. Python's urllib sends
# "Python-urllib/3.x", which Cloudflare's managed bot rules reject outright with a bare
# HTTP 403. That failure is worth naming because it looks nothing like its cause: the
# token is fine, the service is fine, and Moodle never sees the request at all, so there
# is no Moodle error to read. Any ordinary User-Agent passes.
USER_AGENT = "ltct-publisher/1.0 (+https://github.com/dhigby/virtual-ltct)"


class MoodleError(RuntimeError):
    """A Moodle-side failure, carrying whatever detail the server was willing to give."""

    def __init__(self, function, payload):
        self.function = function
        self.errorcode = payload.get("errorcode", "")
        self.message = payload.get("message") or payload.get("exception") or str(payload)
        self.debuginfo = payload.get("debuginfo", "")
        detail = "%s: %s" % (function, self.message)
        if self.errorcode:
            detail += " [%s]" % self.errorcode
        if self.debuginfo:
            detail += "\n  debug: %s" % self.debuginfo
        super().__init__(detail)


def _flatten(value, prefix=""):
    """Moodle's REST protocol is form-encoded with PHP bracket names, not JSON."""
    items = []
    if isinstance(value, dict):
        for k, v in value.items():
            items += _flatten(v, "%s[%s]" % (prefix, k) if prefix else str(k))
    elif isinstance(value, (list, tuple)):
        for i, v in enumerate(value):
            items += _flatten(v, "%s[%d]" % (prefix, i))
    elif isinstance(value, bool):
        items.append((prefix, "1" if value else "0"))
    elif value is None:
        items.append((prefix, ""))
    else:
        items.append((prefix, str(value)))
    return items


class MoodleClient:
    def __init__(self, url=None, token=None, timeout=DEFAULT_TIMEOUT, dry_run=False):
        self.url = (url or os.environ.get("MOODLE_URL") or "").rstrip("/")
        self.token = (token or os.environ.get("MOODLE_TOKEN") or "").strip()
        self.timeout = timeout
        self.dry_run = dry_run
        self.calls = []            # every write attempted, for --dry-run reporting
        if not dry_run:
            if not self.url:
                raise SystemExit(
                    "MOODLE_URL is not set. Point it at your Moodle site, e.g.\n"
                    "  $env:MOODLE_URL = 'https://moodle.example.org'")
            if not self.token:
                raise SystemExit(
                    "MOODLE_TOKEN is not set. Create a token for the 'LTC curriculum "
                    "publishing' service under\n"
                    "  Site administration > Server > Web services > Manage tokens")

    # -- core ----------------------------------------------------------------------------
    def call(self, function, **params):
        """Invoke one web service function. Raises MoodleError on a Moodle-side failure."""
        self.calls.append((function, params))
        if self.dry_run:
            return {"dry_run": True, "function": function}

        body = dict(_flatten(params))
        body.update({
            "wstoken": self.token,
            "wsfunction": function,
            "moodlewsrestformat": "json",
        })
        data = urllib.parse.urlencode(body, encoding="utf-8").encode("utf-8")
        req = urllib.request.Request(
            self.url + "/webservice/rest/server.php", data=data,
            headers={"Content-Type": "application/x-www-form-urlencoded; charset=utf-8",
                     "User-Agent": USER_AGENT})
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                raw = resp.read().decode("utf-8", "replace")
        except urllib.error.HTTPError as e:
            raise MoodleError(function, {"message": "HTTP %s from %s" % (e.code, self.url)})
        except urllib.error.URLError as e:
            raise MoodleError(function, {"message": "cannot reach %s: %s"
                                                    % (self.url, e.reason)})

        if not raw.strip():
            return None
        try:
            payload = json.loads(raw)
        except ValueError:
            raise MoodleError(function, {"message": "non-JSON response", "debuginfo": raw[:400]})

        # Moodle answers 200 OK on failure; the error is in the body. Checking the HTTP
        # status alone would report success while nothing happened.
        if isinstance(payload, dict) and "exception" in payload:
            raise MoodleError(function, payload)
        return payload

    # -- files ---------------------------------------------------------------------------
    def upload(self, path, itemid=0, filearea="draft"):
        """Upload one file to the caller's draft area; returns the itemid to attach it by.

        Moodle's file API is a two-step: a file lands in the user's draft area first, and
        the activity's own add/update handler then moves it into the module's file area.
        That second step is what makes @@PLUGINFILE@@ links resolve -- and what makes
        images render offline in the Android app rather than silently failing in the
        field the way a hotlink would.
        """
        self.calls.append(("upload.php", {"path": str(path), "itemid": itemid}))
        if self.dry_run:
            return itemid or 1

        path = str(path)
        name = os.path.basename(path)
        with open(path, "rb") as fh:
            content = fh.read()

        boundary = "----ltct%s" % uuid.uuid4().hex
        parts = []
        for field, value in (("token", self.token), ("filearea", filearea),
                             ("itemid", str(itemid))):
            parts.append(("--%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n%s\r\n"
                          % (boundary, field, value)).encode("utf-8"))
        parts.append(("--%s\r\nContent-Disposition: form-data; name=\"file_1\"; "
                      "filename=\"%s\"\r\nContent-Type: application/octet-stream\r\n\r\n"
                      % (boundary, name)).encode("utf-8"))
        parts.append(content)
        parts.append(("\r\n--%s--\r\n" % boundary).encode("utf-8"))

        req = urllib.request.Request(
            self.url + "/webservice/upload.php", data=b"".join(parts),
            headers={"Content-Type": "multipart/form-data; boundary=%s" % boundary,
                     "User-Agent": USER_AGENT})
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                payload = json.loads(resp.read().decode("utf-8", "replace"))
        except urllib.error.URLError as e:
            raise MoodleError("upload.php", {"message": "cannot reach %s: %s"
                                                        % (self.url, e.reason)})

        if isinstance(payload, dict) and "error" in payload:
            raise MoodleError("upload.php", {"message": payload["error"]})
        if not payload:
            raise MoodleError("upload.php", {"message": "no file record returned"})
        return int(payload[0]["itemid"])

    # -- convenience ---------------------------------------------------------------------
    def site_info(self):
        return self.call("core_webservice_get_site_info")

    def course_by_idnumber(self, idnumber):
        result = self.call("core_course_get_courses_by_field",
                           field="idnumber", value=idnumber)
        courses = (result or {}).get("courses") or []
        return courses[0] if courses else None

    def manifest(self, idnumber):
        return self.call("local_ltuse_get_course_manifest", idnumber=idnumber)


def main():
    import argparse
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--whoami", action="store_true",
                    help="check the server is reachable and the token works")
    args = ap.parse_args()

    client = MoodleClient()
    if args.whoami:
        info = client.site_info()
        print("site      %s" % info.get("sitename"))
        print("url       %s" % info.get("siteurl"))
        print("release   %s" % info.get("release"))
        print("user      %s (%s)" % (info.get("fullname"), info.get("username")))
        available = {f["name"] for f in info.get("functions", [])}
        # Every function scripts/publish_moodle.py calls. Spec 009 added parameters to two
        # of them but no new names; spec 004 added the last two local_ltuse ones.
        needed = ["local_ltuse_get_course_manifest", "local_ltuse_create_page",
                  "local_ltuse_update_sections",
                  "local_ltuse_import_questions", "local_ltuse_create_quiz",
                  "local_ltuse_hide_modules", "local_ltuse_set_course_completion",
                  "local_ltuse_set_course_competencies", "local_ltuse_ensure_discussion",
                  "core_course_create_courses", "core_course_update_courses",
                  "core_course_get_courses_by_field"]
        print("\nfunctions this token can call:")
        missing = False
        for f in needed:
            ok = f in available
            missing = missing or not ok
            print("  %s %s" % ("OK " if ok else "-- ", f))
        if missing:
            print("\nSomething is missing. Check the token is on the 'LTC curriculum "
                  "publishing' service\nand that local_ltuse is installed: see "
                  "moodle/local_ltuse/README.md")
            return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
