#!/usr/bin/env python3
"""Site configuration as code: the repo's half of `moodle/site/` (spec 001).

Every setting, plugin and role the training system depends on is declared in YAML under
`moodle/site/`. This command is the only thing that reads that YAML. The server never
does: it receives the declaration as JSON on stdin, so it needs no YAML extension and no
repo checkout.

    site_config.py validate [--json]   check the declaration offline (CI runs this)
    site_config.py render   [--mode=apply|drift]
                                       print the JSON payload, every env: value unresolved
    site_config.py apply    [--json]   bring the server to the declaration
    site_config.py drift    [--json]   compare the server with the declaration; change nothing

Contracts: specs/001-site-config-as-code/contracts/ (declaration.md, site-config-cli.md,
output.md); every validation rule is in data-model.md. Spec 012 adds
moodle/site/course-discussions.yaml (which courses' discussions are shared across
organisations), read only by load_discussions(), which moodle_payload.py also uses
(specs/012-assignments-peer-review/contracts/site-declaration.md).

ENVIRONMENT (never a file; the repo is public)

    MOODLE_URL   the target site; must equal the server's $CFG->wwwroot (apply, drift)
    MOODLE_DIR   the Moodle code directory on the server (apply, drift)
    MOODLE_SSH   optional ssh destination; unset runs the PHP script locally, which is
                 what an on-server schedule uses
    any NAME     named by an `env:NAME` setting value

SECRETS. A setting value written `env:NAME` is resolved here, in memory, and travels only
inside the stdin stream. It never goes into argv, onto disk or into a log. `drift` never
resolves a secret (a secret is compared only as set or empty), and `render` resolves
nothing. A non-secret reference may carry a path after the name, `env:MOODLE_URL/local/...`,
which is how a URL is built from the target without hard-coding a host (FR-005).

EXIT CODES: 0 valid / applied / no differences; 1 invalid / a step failed / drift found;
2 usage or configuration error (including an invalid declaration at apply or drift time).
"""
import argparse
import decimal
import json
import os
import pathlib
import re
import shlex
import subprocess
import sys
import urllib.parse

try:
    import yaml
except ImportError:
    sys.exit("pyyaml required: pip install pyyaml")

REPO = pathlib.Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO / "scripts"))
from course_stage import NOT_A_COURSE, branch_slug  # noqa: E402

SITE_DIR = REPO / "moodle" / "site"
MODULES = REPO / "modules"
DISCUSSIONS_FILE = "course-discussions.yaml"
REQUIREMENTS = REPO / "moodle" / "REQUIREMENTS.md"
LTUSE_VERSION = REPO / "moodle" / "local_ltuse" / "version.php"
CLI_PATH = "public/local/ltuse/cli/site_config.php"   # under $MOODLE_DIR (research R1)

SECRET_TEXT = "<secret>"

# Moodle's standard plugins, from lib/plugins.json on MOODLE_502_STABLE (read 2026-10-01;
# core_plugin_manager::standard_plugins_list() reads the same file). A core plugin takes no
# `version` or `source` pin; anything else must carry both. Refresh this when moodle.requires
# moves to a new branch.
_STANDARD_PLUGINS = {
    'aiplacement': 'courseassist editor',
    'aiprovider': 'awsbedrock azureai deepseek gemini ollama openai',
    'antivirus': 'clamav',
    'assignfeedback': 'comments editpdf file offline',
    'assignsubmission': 'comments file onlinetext',
    'auth': 'db email ldap lti manual nologin none oauth2 shibboleth webservice',
    'availability': 'completion date grade group grouping profile',
    'block': (
        'accessreview activity_results admin_bookmarks badges blog_menu blog_recent '
        'blog_tags calendar_month calendar_upcoming comments completionstatus course_list '
        'course_summary feedback globalsearch glossary_random html login lp mentees '
        'myoverview myprofile navigation news_items online_users private_files '
        'recent_activity recentlyaccessedcourses recentlyaccesseditems rss_client '
        'search_forums selfcompletion settings site_main_menu social_activities '
        'starredcourses tag_flickr tag_youtube tags timeline'
    ),
    'booktool': 'exportimscp importhtml print',
    'cachelock': 'file',
    'cachestore': 'apcu file redis session static',
    'calendartype': 'gregorian',
    'communication': 'customlink matrix',
    'contenttype': 'h5p',
    'coursereport': '',
    'customfield': 'checkbox date number select text textarea',
    'datafield': (
        'checkbox date file latlong menu multimenu number picture radiobutton text '
        'textarea url'
    ),
    'dataformat': 'csv excel html json ods pdf',
    'datapreset': 'imagegallery journal proposals resources',
    'editor': 'textarea tiny',
    'enrol': (
        'category cohort database fee flatfile guest imsenterprise ldap lti manual meta '
        'paypal self'
    ),
    'factor': (
        'admin auth capability cohort email grace iprange nosetup role sms token totp '
        'webauthn'
    ),
    'fileconverter': 'googledrive unoconv',
    'filter': (
        'activitynames algebra codehighlighter data displayh5p emailprotect emoticon '
        'glossary mathjaxloader mediaplugin multilang tex urltolink'
    ),
    'format': 'singleactivity social topics weeks',
    'forumreport': 'summary',
    'gradeexport': 'ods txt xls xml',
    'gradeimport': 'csv direct xml',
    'gradepenalty': 'duedate',
    'gradereport': 'grader history outcomes overview singleview summary user',
    'gradingform': 'guide rubric',
    'h5plib': 'v128',
    'local': '',
    'logstore': 'database standard',
    'ltiservice': 'basicoutcomes gradebookservices memberships profile toolproxy toolsettings',
    'media': 'html5audio html5video videojs vimeo youtube',
    'message': 'airnotifier email popup sms',
    'mlbackend': 'php python',
    'mnetservice': '',
    'mod': (
        'assign bigbluebuttonbn book choice data feedback folder forum glossary '
        'h5pactivity imscp label lesson lti page qbank quiz resource scorm subsection url '
        'wiki workshop'
    ),
    'paygw': 'paypal',
    'plagiarism': '',
    'portfolio': 'download flickr googledocs',
    'profilefield': 'checkbox datetime menu social text textarea',
    'qbank': (
        'bulkmove columnsortorder comment customfields deletequestion editquestion '
        'exportquestions exporttoxml history importquestions managecategories '
        'previewquestion statistics tagquestion usage viewcreator viewquestionname '
        'viewquestiontext viewquestiontype'
    ),
    'qbehaviour': (
        'adaptive adaptivenopenalty deferredcbm deferredfeedback immediatecbm '
        'immediatefeedback informationitem interactive interactivecountback manualgraded '
        'missing'
    ),
    'qformat': 'aiken blackboard_six gift missingword multianswer xhtml xml',
    'qtype': (
        'calculated calculatedmulti calculatedsimple ddimageortext ddmarker ddwtos '
        'description essay gapselect match missingtype multianswer multichoice numerical '
        'ordering randomsamatch shortanswer truefalse'
    ),
    'quiz': 'grading overview responses statistics',
    'quizaccess': (
        'delaybetweenattempts ipaddress numattempts offlineattempts openclosedate '
        'password seb securewindow timelimit'
    ),
    'report': (
        'backups competency completion configlog courseoverview eventlist infectedfiles '
        'insights log loglive outline participation performance progress '
        'questioninstances security stats status themeusage usersessions'
    ),
    'repository': (
        'areafiles contentbank coursefiles dropbox equella filesystem flickr '
        'flickr_public googledocs local merlot nextcloud onedrive recent s3 upload url '
        'user webdav wikimedia youtube'
    ),
    'scormreport': 'basic graphs interactions objectives',
    'search': 'simpledb solr',
    'smsgateway': 'aws modica',
    'theme': 'boost classic',
    'tiny': (
        'accessibilitychecker aiplacement autosave equation h5p html link media '
        'noautolink premium recordrtc'
    ),
    'tool': (
        'admin_presets analytics availabilityconditions behat brickfield capability '
        'cohortroles componentlibrary customlang dataprivacy dbtransfer filetypes '
        'generator httpsreplace installaddon langimport licensemanager log lp lpimportcsv '
        'lpmigrate messageinbound mfa mobile monitor multilangupgrade oauth2 phpunit '
        'policy profiling recyclebin replace spamcleaner task templatelibrary unsuproles '
        'uploadcourse uploaduser usertours xmldb'
    ),
    'webservice': 'rest soap',
    'workshopallocation': 'manual random scheduled',
    'workshopeval': 'best',
    'workshopform': 'accumulative comments numerrors rubric',
}
STANDARD = {t: frozenset(names.split()) for t, names in _STANDARD_PLUGINS.items()}

CORE_ROLES = frozenset(
    "manager coursecreator editingteacher teacher student guest user frontpage".split())
ARCHETYPES = CORE_ROLES | {""}
CONTEXT_LEVELS = ("system", "coursecat", "course", "module", "user", "block")
PERMISSIONS = ("allow", "prevent", "prohibit", "inherit")

# Settings whose value is a list of role shortnames, checked against roles.yaml + core.
ROLE_SETTINGS = frozenset({"tool_dataprivacy/dporoles"})

# A setting whose name looks like this must be a secret env: reference (FR-006, SC-004)...
SECRET_NAME = re.compile(r"pass|secret|token|key", re.I)   # pass: smtppass, proxypassword
# ... unless it is one of these, which are policy switches, not secrets. Add to this list
# only in review, with the setting confirmed in Moodle source.
NON_SECRET_NAMES = frozenset({
    "passwordpolicy", "minpasswordlength", "minpassworddigits", "minpasswordlower",
    "minpasswordupper", "minpasswordnonalphanum", "passwordreuselimit",
    "passwordchangelogout", "passwordchangetokendeletion", "recaptchapublickey",
    "groupenrolmentkeypolicy",
})
# A literal that looks like a credential: long, unbroken, mixing letters and digits.
SECRET_VALUE = re.compile(r"^(?=.*[0-9])(?=.*[A-Za-z])[A-Za-z0-9+/=_\-]{32,}$")

SETTING_KEY = re.compile(r"^(?:([a-z][a-z0-9_]*)/)?([A-Za-z0-9_]+)$")
ENV_REF = re.compile(r"^env:([A-Z][A-Z0-9_]*)(/\S*)?$")
COMPONENT = re.compile(r"^([a-z][a-z0-9]*)_([a-z0-9_]+)$")
CAPABILITY = re.compile(r"^[a-z0-9_]+/[a-z0-9_]+:[a-z0-9_]+$")
VERSION_STAMP = re.compile(r"^\d{10}(\.\d{1,2})?$")
SETTINGS_FILE = re.compile(r"^[a-z0-9]+(-[a-z0-9]+)*\.yaml$")
SHA256 = re.compile(r"^[0-9a-f]{64}$")

TOP_FILES = {
    "site.yaml": ({"moodle"}, {"plugins"}),
    "ignore.yaml": ({"ignore"}, set()),
    "roles.yaml": ({"roles"}, set()),
    # Spec 002: the partner organisations and the profile fields. Both are optional, so a
    # site that hosts no organisations needs neither (specs/002-org-structure-cohorts/
    # contracts/declaration.md).
    "organisations.yaml": ({"rows", "purpose", "categories", "organisations"}, set()),
    "profile-fields.yaml": ({"rows", "purpose", "category", "fields"}, set()),
    # Spec 012: optional; validated by load_discussions(), not by the loop in validate().
    DISCUSSIONS_FILE: ({"rows", "shared"}, set()),
}

# --- spec 002: organisations, categories, cohorts and profile fields ---------------------

COMPETENCIES = REPO / "competencies.yaml"   # the expertise areas are its categories
KEY = re.compile(r"^[a-z][a-z0-9-]*$")     # valid in an idnumber and as a menu option
ORG_KEY_MAX = 30
IDNUMBER_MAX = 100                         # course_categories.idnumber, cohort.idnumber
CATEGORY_NAME_MAX = 255                    # course_categories.name
COHORT_NAME_MAX = 254                      # cohort.name
REQUIRED_CATEGORIES = ("published", "pilots", "organisations")
ORG_PARENT = "organisations"               # every organisation category sits in it
INDEPENDENT = "independent"                # consultants with no partner organisation
EMAIL = re.compile(r"[^@\s]+@[^@\s]+\.[^@\s]+")
FIELD_SHORTNAME = re.compile(r"^ltct_[a-z0-9_]+$")
EXPERTISE_PREFIX = "ltct_exp_"
ORG_FIELD = "ltct_org"
DATATYPES = ("menu", "checkbox")
# profile/lib.php PROFILE_VISIBLE_*: the payload carries the user_info_field column value.
VISIBILITY = {"all": 2, "teachers": 3, "private": 1, "none": 0}
OPTIONS_FROM = ("organisations",)
META_CATEGORY = "Meta"                     # holds only Uncategorized, so gets no checkbox
COHORT_RULE_CONDITION = "user_custom_profile"

ORGMANAGER = "orgmanager"
# Capabilities orgmanager must never carry, in any permission (contracts/declaration.md,
# data-model "Role declaration orgmanager"). The list of what it may hold is reviewed in
# roles.yaml; this guards against the dangerous additions.
ORGMANAGER_DENY = frozenset({"moodle/site:accessallgroups", "moodle/user:viewalldetails",
                             "moodle/course:managegroups", "moodle/course:viewsuspendedusers"})
ORGMANAGER_DENY_PREFIXES = ("moodle/cohort:", "moodle/role:", "enrol/")
ORGMANAGER_DENY_USER = re.compile(r"create|update|delete|edit|manage|loginas")


# ---------------------------------------------------------------------------------------
# A strict YAML loader. YAML 1.1 turns an unquoted `off` into False, which a reviewer
# reading the diff would never see, so booleans are kept with their spelling and each
# field decides what it accepts. Floats are kept exact. Duplicate keys are errors.

class Flag:
    """A YAML 1.1 boolean, remembered with the text it was written as."""
    __slots__ = ("value", "text")

    def __init__(self, value, text):
        self.value, self.text = value, text

    def __repr__(self):
        return self.text


class _Loader(yaml.SafeLoader):
    def construct_mapping(self, node, deep=False):
        seen = set()
        self.flatten_mapping(node)
        for key_node, _ in node.value:
            key = self.construct_object(key_node, deep=deep)
            text = key.text if isinstance(key, Flag) else key
            if text in seen:
                raise yaml.constructor.ConstructorError(
                    None, None, "duplicate key %r" % (text,), key_node.start_mark)
            seen.add(text)
        return super().construct_mapping(node, deep)


_Loader.add_constructor(
    "tag:yaml.org,2002:bool",
    lambda loader, node: Flag(loader.construct_yaml_bool(node), node.value))
_Loader.add_constructor(
    "tag:yaml.org,2002:float",
    lambda loader, node: decimal.Decimal(node.value.replace("_", "")))


def _is_int(v):
    return isinstance(v, int) and not isinstance(v, bool)


def _text(v):
    return isinstance(v, str) and v.strip() != ""


# ---------------------------------------------------------------------------------------
# Validation. Every rule in data-model.md that can be checked offline.

class Problems:
    def __init__(self):
        self.items = []

    def add(self, where, message):
        self.items.append("%s: %s" % (where, message))

    def __bool__(self):
        return bool(self.items)


def _rel(path):
    try:
        return path.resolve().relative_to(REPO).as_posix()
    except ValueError:
        return path.as_posix()


def _load(path, problems):
    try:
        with open(path, encoding="utf-8") as fh:
            return yaml.load(fh, Loader=_Loader)
    except yaml.YAMLError as exc:
        problems.add(_rel(path), "not valid YAML: %s" % exc)
    except OSError as exc:
        problems.add(_rel(path), "cannot read: %s" % exc)
    return None


def _check_keys(where, data, required, optional, problems):
    if not isinstance(data, dict):
        problems.add(where, "must be a mapping")
        return False
    for key in data:
        if key not in required and key not in optional:
            problems.add(where, "unknown key %r" % (key,))
    for key in sorted(required):
        if key not in data:
            problems.add(where, "missing required key %r" % key)
    return True


def _requirement_rows():
    rows = set()
    try:
        for line in REQUIREMENTS.read_text(encoding="utf-8").splitlines():
            m = re.match(r"^\|\s*(\d+)\s*\|", line)
            if m:
                rows.add(int(m.group(1)))
    except OSError:
        pass
    return rows


def _php_stamp(path, field):
    try:
        text = path.read_text(encoding="utf-8")
    except OSError:
        return None
    m = re.search(r"\$plugin->%s\s*=\s*'?([0-9A-Za-z_.]+)'?\s*;" % field, text)
    return m.group(1) if m else None


def _walk_strings(node, path):
    if isinstance(node, str):
        yield path, node
    elif isinstance(node, dict):
        for k, v in node.items():
            yield from _walk_strings(v, path + (str(k),))
    elif isinstance(node, list):
        for i, v in enumerate(node):
            yield from _walk_strings(v, path + (str(i),))


def _check_hosts(where, data, problems, allowed=()):
    """No hard-coded host: an absolute URL is an error unless its path is allowed."""
    for path, value in _walk_strings(data, ()):
        if any(len(path) == len(a) and all(x in ("*", p) for x, p in zip(a, path))
               for a in allowed):
            continue
        parsed = urllib.parse.urlparse(value.strip())
        if parsed.netloc and (parsed.scheme or value.strip().startswith("//")):
            problems.add(where, "%s is a hard-coded host (%s); build it from env:MOODLE_URL"
                         % (".".join(path) or "value", value.strip()))


def _check_value(where, key, value, secret, problems):
    """Check one setting value; return the env: reference name, or None."""
    if isinstance(value, Flag):
        problems.add(where, "%s: YAML boolean %r; write 1 or 0" % (key, value.text))
        return None
    if isinstance(value, decimal.Decimal):
        problems.add(where, "%s: %s is a float; quote it or use an integer" % (key, value))
        return None
    if value is None:
        problems.add(where, "%s: no value; write \"\" for an empty setting" % key)
        return None
    if isinstance(value, str) and value.startswith("env:"):
        m = ENV_REF.match(value)
        if not m:
            problems.add(where, "%s: malformed environment reference %r (env:NAME, NAME is "
                         "[A-Z][A-Z0-9_]*)" % (key, value))
            return None
        if secret and m.group(2):
            problems.add(where, "%s: a secret reference is env:NAME alone" % key)
        return m.group(1)
    if secret:
        problems.add(where, "%s: secret: true needs an env: value; a secret is never a "
                     "literal (FR-006)" % key)
        return None
    items = value if isinstance(value, list) else [value]
    for item in items:
        if isinstance(item, (Flag, decimal.Decimal, list, dict)) or item is None or not (
                isinstance(item, str) or _is_int(item)):
            problems.add(where, "%s: values are strings, integers or a list of them" % key)
            return None
        if isinstance(item, str) and item.startswith("env:"):
            problems.add(where, "%s: an env: reference cannot sit inside a list" % key)
            return None
        if isinstance(item, str) and not item.startswith("/") and SECRET_VALUE.match(item):
            problems.add(where, "%s: value looks like a literal secret; use env:NAME with "
                         "secret: true" % key)
    return None


def validate(site_dir=SITE_DIR, modules_dir=None):
    """Load and check the declaration. Returns (declaration, Problems)."""
    problems = Problems()
    site_dir = pathlib.Path(site_dir)
    decl = {"moodle": None, "plugins": [], "ignore": [], "roles": [], "settings": [],
            "categories": [], "cohorts": [], "profile_fields": [], "cohort_rules": [],
            "discussions": {"shared": []}}
    if not site_dir.is_dir():
        problems.add(_rel(site_dir), "declaration directory not found")
        return decl, problems

    for path in sorted(site_dir.iterdir()):
        if path.is_file() and path.suffix in (".yaml", ".yml") and path.name not in TOP_FILES:
            problems.add(_rel(path), "unexpected file; the declaration is site.yaml, "
                         "ignore.yaml, roles.yaml, organisations.yaml, "
                         "profile-fields.yaml, %s and settings/*.yaml"
                         % DISCUSSIONS_FILE)

    loaded = {}
    for name, (required, optional) in TOP_FILES.items():
        if name == DISCUSSIONS_FILE:
            continue   # load_discussions() below; the publisher reads it through the same loader
        path = site_dir / name
        if not path.exists():
            if name == "site.yaml":
                problems.add(_rel(path), "missing; it declares the minimum Moodle release")
            continue
        before = len(problems.items)
        data = _load(path, problems)
        if data is None and len(problems.items) == before:
            problems.add(_rel(path), "empty")
        if data is not None and _check_keys(_rel(path), data, required, optional, problems):
            loaded[name] = (path, data)

    # site.yaml: the minimum release and the plugins.
    if "site.yaml" in loaded:
        path, data = loaded["site.yaml"]
        where = _rel(path)
        _check_hosts(where, data, problems, allowed=[("plugins", "*", "source", "url")])
        moodle = data.get("moodle")
        if _check_keys(where + " moodle", moodle, {"requires", "release"}, set(), problems):
            requires = moodle.get("requires")
            if not (_is_int(requires) or isinstance(requires, decimal.Decimal)) or \
                    not VERSION_STAMP.match(str(requires)):
                problems.add(where, "moodle.requires must be a YYYYMMDDXX.XX version stamp")
            else:
                own = _php_stamp(LTUSE_VERSION, "requires")
                if own and decimal.Decimal(str(requires)) < decimal.Decimal(own):
                    problems.add(where, "moodle.requires %s is lower than local_ltuse's own "
                                 "$plugin->requires %s" % (requires, own))
                if not _text(moodle.get("release")):
                    problems.add(where, "moodle.release must be text, e.g. \"5.2.3+ (Build: "
                                 "20260928)\"")
                decl["moodle"] = {"requires": requires, "release": moodle.get("release")}
        plugins = data.get("plugins") or []
        if not isinstance(plugins, list):
            problems.add(where, "plugins must be a list")
            plugins = []
        seen = set()
        for i, plugin in enumerate(plugins):
            pwhere = "%s plugins[%d]" % (where, i)
            if not _check_keys(pwhere, plugin, {"component", "why"},
                               {"enabled", "version", "source"}, problems):
                continue
            component = plugin.get("component")
            m = COMPONENT.match(component) if isinstance(component, str) else None
            if not m:
                problems.add(pwhere, "component %r is not a frankenstyle name" % (component,))
                continue
            pwhere = "%s %s" % (where, component)
            if component in seen:
                problems.add(pwhere, "declared twice")
            seen.add(component)
            if not _text(plugin.get("why")):
                problems.add(pwhere, "why must say which row or spec needs it")
            ptype, pname = m.groups()
            standard = pname in STANDARD.get(ptype, ())
            out = {"component": component}
            if "enabled" in plugin:
                enabled = plugin["enabled"]
                if ptype == "filter":
                    text = enabled.text.lower() if isinstance(enabled, Flag) else enabled
                    if text not in ("on", "off", "disabled"):
                        problems.add(pwhere, "a filter's enabled is on, off or disabled")
                    else:
                        out["enabled"] = text
                elif isinstance(enabled, Flag) and enabled.text.lower() in ("true", "false"):
                    out["enabled"] = int(enabled.value)
                elif _is_int(enabled) and enabled in (0, 1):
                    out["enabled"] = enabled
                else:
                    problems.add(pwhere, "enabled is 1 or 0 (on/off/disabled are for "
                                 "filter_* only)")
            if standard:
                for key in ("version", "source"):
                    if key in plugin:
                        problems.add(pwhere, "a core plugin takes no %s; its version is the "
                                     "Moodle release" % key)
            else:
                version, source = plugin.get("version"), plugin.get("source")
                if not (_is_int(version) and version > 0) or source is None:
                    problems.add(pwhere, "not a standard plugin, so it needs a version pin "
                                 "and a source")
                else:
                    out["version"] = version
                    _check_source(pwhere, component, version, source, problems)
            decl["plugins"].append(out)

    # ignore.yaml
    if "ignore.yaml" in loaded:
        path, data = loaded["ignore.yaml"]
        where = _rel(path)
        _check_hosts(where, data, problems)
        entries = data.get("ignore") or []
        if not isinstance(entries, list):
            problems.add(where, "ignore must be a list")
            entries = []
        for i, entry in enumerate(entries):
            ewhere = "%s ignore[%d]" % (where, i)
            if not _check_keys(ewhere, entry, {"setting", "reason"}, set(), problems):
                continue
            key = entry.get("setting")
            if not (isinstance(key, str) and SETTING_KEY.match(key)):
                problems.add(ewhere, "setting %r is not a setting key" % (key,))
                continue
            if not _text(entry.get("reason")):
                problems.add(ewhere, "%s: an ignore entry needs a reason" % key)
            decl["ignore"].append({"setting": key, "reason": entry.get("reason")})

    # roles.yaml
    role_names = set(CORE_ROLES)
    if "roles.yaml" in loaded:
        path, data = loaded["roles.yaml"]
        where = _rel(path)
        _check_hosts(where, data, problems)
        roles = data.get("roles") or []
        if not isinstance(roles, list):
            problems.add(where, "roles must be a list")
            roles = []
        seen = set()
        for i, role in enumerate(roles):
            rwhere = "%s roles[%d]" % (where, i)
            if not _check_keys(rwhere, role, {"shortname", "why"},
                               {"name", "description", "archetype", "contextlevels",
                                "capabilities"}, problems):
                continue
            short = role.get("shortname")
            if not (isinstance(short, str) and re.match(r"^[a-z0-9_]+$", short)):
                problems.add(rwhere, "shortname %r is not a role shortname" % (short,))
                continue
            rwhere = "%s %s" % (where, short)
            if short in seen:
                problems.add(rwhere, "declared twice")
            seen.add(short)
            role_names.add(short)
            out = {"shortname": short}
            if not _text(role.get("why")):
                problems.add(rwhere, "why must say which row or spec needs it")
            for key in ("name", "description"):
                if key in role:
                    if not isinstance(role[key], str):
                        problems.add(rwhere, "%s must be text" % key)
                    else:
                        out[key] = role[key]
            if "archetype" in role:
                arch = role["archetype"] if role["archetype"] is not None else ""
                if arch not in ARCHETYPES:
                    problems.add(rwhere, "archetype %r is not a Moodle archetype" % (arch,))
                out["archetype"] = arch
            if "contextlevels" in role:
                levels = role["contextlevels"]
                if not isinstance(levels, list) or any(lv not in CONTEXT_LEVELS
                                                       for lv in levels) \
                        or len(set(levels)) != len(levels):
                    problems.add(rwhere, "contextlevels is a list drawn from %s"
                                 % ", ".join(CONTEXT_LEVELS))
                else:
                    out["contextlevels"] = levels
            caps = role.get("capabilities") or {}
            if not isinstance(caps, dict):
                problems.add(rwhere, "capabilities must map capability -> permission")
                caps = {}
            for cap, perm in caps.items():
                if not (isinstance(cap, str) and CAPABILITY.match(cap)):
                    problems.add(rwhere, "%r is not a capability name" % (cap,))
                if perm not in PERMISSIONS:
                    problems.add(rwhere, "%s: permission %r is not one of %s"
                                 % (cap, perm, ", ".join(PERMISSIONS)))
            out["capabilities"] = dict(caps)
            decl["roles"].append(out)

    # settings/*.yaml
    settings_dir = site_dir / "settings"
    declared = {}
    rows = _requirement_rows()
    if settings_dir.is_dir():
        for path in sorted(settings_dir.iterdir()):
            where = _rel(path)
            if path.is_dir() or not SETTINGS_FILE.match(path.name):
                problems.add(where, "settings files are lowercase-hyphenated .yaml, one "
                             "topic per file")
                continue
            before = len(problems.items)
            data = _load(path, problems)
            if data is None:
                if len(problems.items) == before:
                    problems.add(where, "empty")
                continue
            if not _check_keys(where, data, {"rows", "purpose", "settings"}, set(), problems):
                continue
            _check_hosts(where, data, problems)
            cited = data.get("rows")
            if not isinstance(cited, list) or not all(_is_int(r) for r in cited):
                problems.add(where, "rows must be a list of moodle/REQUIREMENTS.md row numbers")
            else:
                for r in cited:
                    if r not in rows:
                        problems.add(where, "row %d is not in moodle/REQUIREMENTS.md" % r)
            if not _text(data.get("purpose")):
                problems.add(where, "purpose must say what this file is for")
            entries = data.get("settings")
            if not isinstance(entries, list):
                problems.add(where, "settings must be a list")
                continue
            for i, entry in enumerate(entries):
                swhere = "%s settings[%d]" % (where, i)
                if not _check_keys(swhere, entry, {"name", "value", "why"},
                                   {"secret", "verify"}, problems):
                    continue
                key = entry.get("name")
                m = SETTING_KEY.match(key) if isinstance(key, str) else None
                if not m:
                    problems.add(swhere, "name %r is not a setting key (name or plugin/name)"
                                 % (key,))
                    continue
                if key in declared:
                    problems.add(where, "%s is already declared in %s; a setting has one "
                                 "home (FR-011)" % (key, declared[key]))
                    continue
                declared[key] = where
                plugin, name = m.groups()
                secret = entry.get("secret", Flag(False, "false"))
                if not (isinstance(secret, Flag) and secret.text.lower() in ("true", "false")):
                    problems.add(where, "%s: secret is true or false" % key)
                    secret = False
                else:
                    secret = secret.value
                value = entry.get("value")
                env = _check_value(where, key, value, secret, problems)
                if SECRET_NAME.search(name) and name.lower() not in NON_SECRET_NAMES \
                        and not (env and secret):
                    problems.add(where, "%s looks like a secret, so its value must be env:NAME "
                                 "with secret: true (FR-006)" % key)
                if not _text(entry.get("why")):
                    problems.add(where, "%s: why must say what breaks without it" % key)
                if "verify" in entry and not isinstance(entry["verify"], str):
                    problems.add(where, "%s: verify must be text" % key)
                if plugin:
                    pm = COMPONENT.match(plugin)
                    if pm and pm.group(1) in STANDARD and \
                            pm.group(2) not in STANDARD[pm.group(1)] and \
                            plugin not in {p["component"] for p in decl["plugins"]}:
                        problems.add(where, "%s belongs to %s, which is neither core nor "
                                     "declared in site.yaml" % (key, plugin))
                decl["settings"].append({"name": key, "plugin": plugin, "setting": name,
                                         "value": value, "secret": bool(secret),
                                         "env": env, "file": where})

    for entry in decl["ignore"]:
        if entry["setting"] in declared:
            problems.add("ignore.yaml", "%s is declared in %s, so it cannot also be ignored"
                         % (entry["setting"], declared[entry["setting"]]))
    for s in decl["settings"]:
        if s["name"] in ROLE_SETTINGS and isinstance(s["value"], list):
            for short in s["value"]:
                if short not in role_names:
                    problems.add(s["file"], "%s names role %r, which is neither core nor in "
                                 "roles.yaml" % (s["name"], short))

    # Spec 002: organisations.yaml and profile-fields.yaml, then the four payload arrays.
    orgs = None
    if "organisations.yaml" in loaded:
        path, data = loaded["organisations.yaml"]
        orgs = _validate_organisations(_rel(path), data, rows, problems)
    fields = None
    if "profile-fields.yaml" in loaded:
        path, data = loaded["profile-fields.yaml"]
        fields = _validate_profile_fields(_rel(path), data, rows, orgs, problems)
    if orgs is not None and orgs["organisations"]:
        if fields is None:
            problems.add("organisations.yaml", "each organisation's cohort rule needs the "
                         "%s field, but profile-fields.yaml is missing" % ORG_FIELD)
        for role in decl["roles"]:
            for org in orgs["organisations"]:
                if org["key"] in role["shortname"]:
                    problems.add("roles.yaml", "role %s contains organisation key %r; there "
                                 "is one manager role for every partner (SC-005)"
                                 % (role["shortname"], org["key"]))
    for role in decl["roles"]:
        if role["shortname"] == ORGMANAGER:
            _check_orgmanager(role, problems)
    # course-discussions.yaml (spec 012)
    shared, discussion_problems = load_discussions(site_dir, modules_dir)
    problems.items.extend(discussion_problems.items)
    decl["discussions"] = {"shared": [e["slug"] for e in shared]}
    _expand(decl, orgs, fields)
    return decl, problems


def _check_rows(where, data, rows, problems):
    cited = data.get("rows")
    if not isinstance(cited, list) or not cited or not all(_is_int(r) for r in cited):
        problems.add(where, "rows must be a list of moodle/REQUIREMENTS.md row numbers")
        return
    for r in cited:
        if r not in rows:
            problems.add(where, "row %d is not in moodle/REQUIREMENTS.md" % r)


def _check_name(where, label, value, limit, problems):
    """A display name: non-empty text on one line, within its column, naming no one."""
    if not _text(value):
        problems.add(where, "%s must be text" % label)
        return False
    if "\n" in value or "\r" in value:
        problems.add(where, "%s must be on one line" % label)
        return False
    if len(value) > limit:
        problems.add(where, "%s is %d characters; the column holds %d"
                     % (label, len(value), limit))
    if EMAIL.search(value):
        problems.add(where, "%s holds an email address; nothing here names a person "
                     "(constitution III)" % label)
    return True


def _validate_organisations(where, data, rows, problems):
    """Check organisations.yaml. Returns {categories, organisations}: the valid entries."""
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    if not _text(data.get("purpose")):
        problems.add(where, "purpose must say what this file is for")
    out = {"categories": [], "organisations": []}

    cats = data.get("categories")
    if not isinstance(cats, list):
        problems.add(where, "categories must be a list")
        cats = []
    keys, names = set(), set()
    for i, cat in enumerate(cats):
        cwhere = "%s categories[%d]" % (where, i)
        if not _check_keys(cwhere, cat, {"key", "name", "why"}, {"parent"}, problems):
            continue
        key = cat.get("key")
        if not (isinstance(key, str) and KEY.match(key)):
            problems.add(cwhere, "key %r must match %s" % (key, KEY.pattern))
            continue
        cwhere = "%s category %s" % (where, key)
        if key in keys:
            problems.add(cwhere, "declared twice")
            continue
        if key == "org":
            problems.add(cwhere, "a shared category key cannot be 'org', so ltct:<key> never "
                         "collides with ltct:org:<organisation>")
            continue
        if len("ltct:" + key) > IDNUMBER_MAX:
            problems.add(cwhere, "key is too long for an idnumber (%d characters)"
                         % IDNUMBER_MAX)
        if not _text(cat.get("why")):
            problems.add(cwhere, "why must say which row or spec needs it")
        name = cat.get("name")
        _check_name(cwhere, "name", name, CATEGORY_NAME_MAX, problems)
        parent = cat.get("parent")
        if "parent" in cat and parent not in keys:
            # Earlier in the list, so parents are created first and there is no cycle.
            problems.add(cwhere, "parent %r is not a category declared earlier in the list"
                         % (parent,))
            parent = None
        if isinstance(name, str):
            if (parent, name) in names:
                problems.add(cwhere, "another category under the same parent is named %r"
                             % name)
            names.add((parent, name))
        keys.add(key)
        out["categories"].append({"key": key, "name": name, "parent": parent})
    for key in REQUIRED_CATEGORIES:
        if key not in keys:
            problems.add(where, "categories must include %r" % key)

    orgs = data.get("organisations")
    if not isinstance(orgs, list):
        problems.add(where, "organisations must be a list")
        orgs = []
    seen, orgnames = set(), set()
    for i, org in enumerate(orgs):
        owhere = "%s organisations[%d]" % (where, i)
        # Only key and name: no branding, role or setting per organisation (constitution
        # VII), and nothing that could hold a person (constitution III).
        if not _check_keys(owhere, org, {"key", "name"}, set(), problems):
            continue
        key = org.get("key")
        if not (isinstance(key, str) and KEY.match(key)):
            problems.add(owhere, "key %r must match %s" % (key, KEY.pattern))
            continue
        owhere = "%s organisation %s" % (where, key)
        if len(key) > ORG_KEY_MAX:
            problems.add(owhere, "key is %d characters; at most %d" % (len(key), ORG_KEY_MAX))
        if key in seen:
            problems.add(owhere, "declared twice")
            continue
        seen.add(key)
        name = org.get("name")
        # "<name> managers" is the longest name derived from it, and must fit cohort.name.
        if _check_name(owhere, "name", name, COHORT_NAME_MAX - len(" managers"), problems):
            if name in orgnames:
                problems.add(owhere, "another organisation is named %r" % name)
            elif (ORG_PARENT, name) in names:
                problems.add(owhere, "a shared category under %r is already named %r"
                             % (ORG_PARENT, name))
            orgnames.add(name)
        out["organisations"].append({"key": key, "name": name})
    if INDEPENDENT not in seen:
        problems.add(where, "organisations must include %r, for consultants with no partner "
                     "organisation" % INDEPENDENT)
    return out


def _competency_categories(problems):
    """The top-level category names of competencies.yaml, or None if it cannot be read."""
    try:
        with open(COMPETENCIES, encoding="utf-8") as fh:
            data = yaml.safe_load(fh)
    except (OSError, yaml.YAMLError) as exc:
        problems.add(_rel(COMPETENCIES), "cannot read the expertise areas: %s" % exc)
        return None
    if not isinstance(data, dict):
        problems.add(_rel(COMPETENCIES), "must map each category to its competencies")
        return None
    return [str(k) for k in data]


def _validate_profile_fields(where, data, rows, orgs, problems):
    """Check profile-fields.yaml. Returns {category, fields}: the valid entries, expanded."""
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    if not _text(data.get("purpose")):
        problems.add(where, "purpose must say what this file is for")
    out = {"category": None, "fields": []}
    cat = data.get("category")
    if _check_keys(where + " category", cat, {"name"}, set(), problems):
        if _check_name(where + " category", "name", cat.get("name"), CATEGORY_NAME_MAX,
                       problems):
            out["category"] = cat["name"]

    areas = _competency_categories(problems)
    wanted = [a for a in (areas or []) if a != META_CATEGORY]
    covered = {}
    fields = data.get("fields")
    if not isinstance(fields, list):
        problems.add(where, "fields must be a list")
        fields = []
    seen, from_orgs = set(), []
    for i, field in enumerate(fields):
        fwhere = "%s fields[%d]" % (where, i)
        # No description and no default: an unknown key is an error, so neither can be
        # declared (a default would write one value onto every learner).
        if not _check_keys(fwhere, field, {"shortname", "datatype", "name", "visible",
                                           "locked", "why"},
                           {"required", "options", "options_from", "area"}, problems):
            continue
        short = field.get("shortname")
        if not (isinstance(short, str) and FIELD_SHORTNAME.match(short)):
            problems.add(fwhere, "shortname %r must match %s" % (short, FIELD_SHORTNAME.pattern))
            continue
        fwhere = "%s %s" % (where, short)
        if short in seen:
            problems.add(fwhere, "declared twice")
            continue
        seen.add(short)
        ok = True
        datatype = field.get("datatype")
        if datatype not in DATATYPES:
            problems.add(fwhere, "datatype must be one of %s" % ", ".join(DATATYPES))
            ok = False
        ok &= _check_name(fwhere, "name", field.get("name"), CATEGORY_NAME_MAX, problems)
        visible = field.get("visible")
        if not isinstance(visible, str) or visible not in VISIBILITY:
            problems.add(fwhere, "visible must be one of %s" % ", ".join(VISIBILITY))
            ok = False
        flags = {}
        for key, allowed in (("locked", (0, 1)), ("required", (0,))):
            value = field.get(key, 0)
            if isinstance(value, Flag):
                problems.add(fwhere, "%s: YAML boolean %r; write 1 or 0" % (key, value.text))
                ok = False
            elif not (_is_int(value) and value in allowed):
                problems.add(fwhere, "%s must be %s" % (key, " or ".join(map(str, allowed))))
                ok = False
            else:
                flags[key] = value
        if not _text(field.get("why")):
            problems.add(fwhere, "why must say which row or acceptance scenario needs it")

        options = None
        has_options, has_from = "options" in field, "options_from" in field
        if datatype == "menu":
            if has_options == has_from:
                problems.add(fwhere, "a menu has exactly one of options and options_from")
                ok = False
            elif has_options:
                options = _check_options(fwhere, field["options"], problems)
                ok &= options is not None
            elif field["options_from"] not in OPTIONS_FROM:
                problems.add(fwhere, "options_from must be one of %s" % ", ".join(OPTIONS_FROM))
                ok = False
            else:
                from_orgs.append(short)
                if orgs is None or not orgs["organisations"]:
                    problems.add(fwhere, "options_from: organisations, but organisations.yaml "
                                 "is missing or declares no organisations")
                    ok = False
                else:
                    options = [o["key"] for o in orgs["organisations"]]
        elif datatype == "checkbox" and (has_options or has_from):
            problems.add(fwhere, "only a menu has options")
            ok = False

        expertise = short.startswith(EXPERTISE_PREFIX)
        if expertise and datatype != "checkbox":
            problems.add(fwhere, "an %s* field is a checkbox, one per area" % EXPERTISE_PREFIX)
            ok = False
        if "area" in field and not expertise:
            problems.add(fwhere, "only an %s* field has an area" % EXPERTISE_PREFIX)
            ok = False
        elif expertise:
            area = field.get("area")
            if not isinstance(area, str):
                problems.add(fwhere, "an %s* field needs, in area, the competencies.yaml "
                             "category it stands for" % EXPERTISE_PREFIX)
                ok = False
            elif area == META_CATEGORY:
                problems.add(fwhere, "%s holds only Uncategorized, so it has no expertise "
                             "checkbox" % META_CATEGORY)
                ok = False
            elif areas is not None and area not in wanted:
                problems.add(fwhere, "area %r is not a competencies.yaml category; copy it "
                             "verbatim" % area)
                ok = False
            elif area in covered:
                problems.add(fwhere, "area %r already has a checkbox, %s"
                             % (area, covered[area]))
                ok = False
            else:
                covered[area] = short
        if ok:
            out["fields"].append({
                "shortname": short, "name": field["name"], "datatype": datatype,
                "visible": VISIBILITY[visible], "locked": flags["locked"],
                "required": flags["required"], "options": options or []})

    org_field = next((f for f in fields if isinstance(f, dict)
                      and f.get("shortname") == ORG_FIELD), None)
    if org_field is None:
        problems.add(where, "%s must be declared: a locked menu with options_from: "
                     "organisations (FR-009)" % ORG_FIELD)
    else:
        if org_field.get("datatype") != "menu" or org_field.get("options_from") != \
                "organisations" or "options" in org_field:
            problems.add(where, "%s is a menu with options_from: organisations and no options"
                         % ORG_FIELD)
        locked = org_field.get("locked")
        if not (_is_int(locked) and locked == 1):
            problems.add(where, "%s must be locked: 1, so only the site team can change it "
                         "(FR-009)" % ORG_FIELD)
    for short in from_orgs:
        if short != ORG_FIELD:
            problems.add(where, "only %s takes options_from: organisations, not %s"
                         % (ORG_FIELD, short))
    for area in wanted:
        if area not in covered:
            problems.add(where, "competencies.yaml category %r has no %s* checkbox"
                         % (area, EXPERTISE_PREFIX))
    return out


def _check_options(where, options, problems):
    """A menu's declared options: unique, non-empty text, one line each."""
    if not isinstance(options, list) or not options:
        problems.add(where, "options must be a non-empty list")
        return None
    out = []
    for option in options:
        if not _text(option) or "\n" in option or "\r" in option:
            problems.add(where, "each option is non-empty text on one line, not %r"
                         % (getattr(option, "text", option),))
            return None
        if option in out:
            problems.add(where, "option %r is listed twice" % option)
            return None
        out.append(option)
    return out


def _orgmanager_denied(cap):
    if cap in ORGMANAGER_DENY or cap.startswith(ORGMANAGER_DENY_PREFIXES):
        return True
    component, _, name = cap.partition(":")
    if "enrol" in name:
        return True
    return component == "moodle/user" and bool(ORGMANAGER_DENY_USER.search(name))


def _check_orgmanager(role, problems):
    """The follow-only manager role (FR-005 to FR-007, FR-013)."""
    where = "roles.yaml %s" % ORGMANAGER
    if role.get("contextlevels") != ["course"]:
        problems.add(where, "contextlevels must be exactly [course]; it is assigned only "
                     "through cohort sync in a course")
    for cap, perm in role.get("capabilities", {}).items():
        if perm == "prohibit":
            problems.add(where, "%s: orgmanager uses no prohibit, so it never takes a "
                         "permission away from another role" % cap)
        if isinstance(cap, str) and _orgmanager_denied(cap):
            problems.add(where, "%s is on orgmanager's deny list: a manager follows their "
                         "people and changes nothing (FR-007, FR-013)" % cap)


def _expand(decl, orgs, fields):
    """Expand the two files into the payload arrays (data-model "Rendered payload").

    One organisation becomes one category, two cohorts and one rule, so no two can differ
    (constitution VII). Categories come parents first: the shared ones in declaration order
    (each parent is declared before its children), then the organisations' categories.
    """
    if orgs is not None:
        for cat in orgs["categories"]:
            decl["categories"].append({
                "idnumber": "ltct:" + cat["key"], "name": cat["name"],
                "parent_idnumber": ("ltct:" + cat["parent"]) if cat["parent"] else None})
        for org in orgs["organisations"]:
            idnumber = "ltct:org:" + org["key"]
            decl["categories"].append({"idnumber": idnumber, "name": org["name"],
                                       "parent_idnumber": "ltct:" + ORG_PARENT})
            decl["cohorts"].append({"idnumber": idnumber, "name": org["name"], "visible": 0})
            decl["cohorts"].append({"idnumber": idnumber + ":managers",
                                    "name": "%s managers" % org["name"], "visible": 0})
            decl["cohort_rules"].append({
                "cohort_idnumber": idnumber, "name": "ltct: " + idnumber,
                "condition": COHORT_RULE_CONDITION, "field": ORG_FIELD, "value": org["key"]})
    if fields is not None:
        for field in fields["fields"]:
            decl["profile_fields"].append(dict(field, category=fields["category"]))


def _course_slugs(modules_dir):
    """Every course's branch_slug(), keyed to its folder name. _template is not a course."""
    slugs = {}
    try:
        for d in sorted(pathlib.Path(modules_dir).iterdir()):
            if d.is_dir() and d.name not in NOT_A_COURSE and not d.name.startswith("."):
                slugs[branch_slug(d.name)] = d.name
    except OSError:
        pass
    return slugs


def load_discussions(site_dir=None, modules_dir=None):
    """Read moodle/site/course-discussions.yaml (spec 012, FR-015).

    Returns (shared, Problems): `shared` is a list of {slug, why}, one per course whose
    discussion is shared across organisations. Every other course is separated, which is
    also what an absent file or an empty list means.

    This is the only reader of the file. scripts/moodle_payload.py calls it for each
    course's `discussion.shared`, and validate() for the drift/apply payload, so the
    publisher and the drift check cannot disagree (contracts/site-declaration.md).

    Rules: rows cite moodle/REQUIREMENTS.md; each slug is a course under modules/,
    compared with course_stage.branch_slug(); `why` is required; no slug twice.
    """
    site_dir = pathlib.Path(site_dir) if site_dir is not None else SITE_DIR
    modules_dir = pathlib.Path(modules_dir) if modules_dir is not None else MODULES
    problems = Problems()
    path = site_dir / DISCUSSIONS_FILE
    if not path.exists():
        return [], problems
    where = _rel(path)
    before = len(problems.items)
    data = _load(path, problems)
    if data is None:
        if len(problems.items) == before:
            problems.add(where, "empty; write `rows: [10]` and `shared: []`")
        return [], problems
    required, optional = TOP_FILES[DISCUSSIONS_FILE]
    if not _check_keys(where, data, required, optional, problems):
        return [], problems
    _check_hosts(where, data, problems)

    cited = data.get("rows")
    if not isinstance(cited, list) or not all(_is_int(r) for r in cited):
        problems.add(where, "rows must be a list of moodle/REQUIREMENTS.md row numbers")
    else:
        rows = _requirement_rows()
        for r in cited:
            if r not in rows:
                problems.add(where, "row %d is not in moodle/REQUIREMENTS.md" % r)

    entries = data.get("shared")
    if entries is None:
        entries = []                     # `shared:` with nothing under it: none shared
    if not isinstance(entries, list):
        problems.add(where, "shared must be a list of {slug, why}")
        return [], problems

    courses = _course_slugs(modules_dir)
    shared, seen = [], set()
    for i, entry in enumerate(entries):
        ewhere = "%s shared[%d]" % (where, i)
        if not _check_keys(ewhere, entry, {"slug", "why"}, set(), problems):
            continue
        slug = entry.get("slug")
        if not _text(slug):
            problems.add(ewhere, "slug must be a course's branch_slug()")
            continue
        if slug not in courses:
            canonical = branch_slug(slug)
            if canonical in courses:
                problems.add(ewhere, "%s: write it as %s, the course's branch_slug()"
                             % (slug, canonical))
            else:
                problems.add(ewhere, "%s is not a course under modules/" % slug)
            continue
        if slug in seen:
            problems.add(ewhere, "%s is declared twice" % slug)
            continue
        seen.add(slug)
        if not _text(entry.get("why")):
            problems.add(ewhere, "%s: why must say who agreed to share and why it is safe"
                         % slug)
            continue
        shared.append({"slug": slug, "why": entry["why"]})
    return shared, problems


def _check_source(where, component, version, source, problems):
    if not isinstance(source, dict):
        problems.add(where, "source is {url, sha256} or {path}")
        return
    keys = set(source)
    if keys == {"url", "sha256"}:
        url = urllib.parse.urlparse(str(source["url"]))
        if url.scheme != "https" or not url.netloc:
            problems.add(where, "source.url must be an https release archive")
        if not (isinstance(source["sha256"], str) and SHA256.match(source["sha256"])):
            problems.add(where, "source.sha256 must be 64 lowercase hex characters, in quotes "
                                "(YAML reads an all-digit checksum as a number)")
    elif keys == {"path"}:
        rel = source["path"]
        plugin_dir = (REPO / str(rel)).resolve()
        if not isinstance(rel, str) or pathlib.PurePosixPath(rel).is_absolute() or \
                REPO not in plugin_dir.parents:
            problems.add(where, "source.path must be a path inside this repo")
            return
        stamp = _php_stamp(plugin_dir / "version.php", "version")
        if stamp is None:
            problems.add(where, "source.path %s has no version.php with $plugin->version" % rel)
        elif stamp != str(version):
            problems.add(where, "pinned to %s but %s/version.php says %s; the pin cannot "
                         "drift from the code" % (version, rel, stamp))
        own = _php_stamp(plugin_dir / "version.php", "component")
        if own and own != component:
            problems.add(where, "%s/version.php is %s, not %s" % (rel, own, component))
    else:
        problems.add(where, "source is either {url, sha256} or {path}")


# ---------------------------------------------------------------------------------------
# The payload: the declaration as the JSON site_config.php reads (data-model "Rendered
# payload"). Built in memory; the resolved form is only ever written to the child's stdin.

def _literal(value):
    if isinstance(value, list):
        return [str(v) for v in value]
    return str(value)


def build_payload(decl, mode, environ, redact=False):
    """mode is apply or drift. redact=True resolves nothing (render)."""
    target = environ.get("MOODLE_URL") or ""
    payload = {
        "mode": mode,
        "target_url": target if target else ("env:MOODLE_URL" if redact else ""),
        "moodle": {"requires": float(decl["moodle"]["requires"]),
                   "release": decl["moodle"]["release"]},
        "plugins": decl["plugins"],
        "ignore": decl["ignore"],
        "roles": decl["roles"],
        "settings": [],
        "failed_env": [],
        # Spec 002, already expanded: PHP never sees an "organisation".
        "categories": decl["categories"],
        "cohorts": decl["cohorts"],
        "profile_fields": decl["profile_fields"],
        "cohort_rules": decl["cohort_rules"],
        # Spec 012: slugs only. The `why` stays in the repo; the server needs only the list.
        "discussions": {"shared": list(decl.get("discussions", {}).get("shared", []))},
    }
    for s in decl["settings"]:
        out = {"name": s["name"], "plugin": s["plugin"], "setting": s["setting"],
               "secret": s["secret"]}
        if not s["env"]:
            out["value"] = _literal(s["value"])
        elif redact:
            out["value"] = SECRET_TEXT if s["secret"] else s["value"]
        elif s["secret"] and mode == "drift":
            pass   # drift compares a secret only as set or empty; it never sees the value
        else:
            name, suffix = ENV_REF.match(s["value"]).groups()
            resolved = environ.get(name)
            if resolved is None or resolved == "":
                # Never write an empty value over a working one: the server reports it
                # [fail] env-missing in its place in the run, and writes nothing.
                out["env_missing"] = name
                payload["failed_env"].append({"name": s["name"], "env": name})
            else:
                out["value"] = (resolved.rstrip("/") + suffix) if suffix else resolved
        payload["settings"].append(out)
    return payload


# ---------------------------------------------------------------------------------------
# Transport: ssh to the server (or run locally), JSON on stdin, output relayed untouched.

def remote_command(mode, environ, as_json=False):
    script = environ["MOODLE_DIR"].rstrip("/") + "/" + CLI_PATH
    args = ["--mode=" + mode] + (["--json"] if as_json else [])
    ssh = environ.get("MOODLE_SSH")
    if ssh:
        return ["ssh", ssh, " ".join(["php", shlex.quote(script)] + args)]
    return ["php", script] + args


def run_remote(mode, decl, environ, as_json=False, runner=subprocess.run):
    for name in ("MOODLE_URL", "MOODLE_DIR"):
        if not environ.get(name):
            print("[fail] %s is not set" % name, file=sys.stderr)
            return 2
    payload = build_payload(decl, mode, environ)
    env_failed = bool(payload["failed_env"])
    data = json.dumps(payload).encode("utf-8")
    del payload
    cmd = remote_command(mode, environ, as_json)
    try:
        # stdout and stderr are inherited: the report goes straight to the operator and
        # is never captured, stored or logged here.
        rc = runner(cmd, input=data, check=False).returncode
    except FileNotFoundError:
        print("[fail] cannot run %s; is it installed and on PATH?" % cmd[0], file=sys.stderr)
        return 2
    finally:
        del data
    if rc not in (0, 1, 2):
        print("[fail] %s exited %d before a report (connection or PHP error)" % (cmd[0], rc),
              file=sys.stderr)
        return 2
    if rc == 0 and env_failed:
        return 1   # a skipped setting is a failed step even if the server forgot to say so
    return rc


# ---------------------------------------------------------------------------------------

def _summary(decl):
    files = len({s["file"] for s in decl["settings"]})
    return ("%d settings in %d files, %d plugins, %d roles, %d ignore entries, "
            "%d categories, %d cohorts, %d profile fields, %d cohort rules, "
            "%d shared course discussions"
            % (len(decl["settings"]), files, len(decl["plugins"]), len(decl["roles"]),
               len(decl["ignore"]), len(decl["categories"]), len(decl["cohorts"]),
               len(decl["profile_fields"]), len(decl["cohort_rules"]),
               len(decl["discussions"]["shared"])))


def main(argv=None, environ=None):
    environ = os.environ if environ is None else environ
    parser = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    parser.add_argument("command", choices=["validate", "render", "apply", "drift"])
    parser.add_argument("--json", action="store_true", help="report as JSON")
    parser.add_argument("--mode", choices=["apply", "drift"], default="apply",
                        help="render only: which payload to show (default apply)")
    parser.add_argument("--site-dir", default=str(SITE_DIR), help=argparse.SUPPRESS)
    args = parser.parse_args(argv)

    decl, problems = validate(args.site_dir)
    if args.command == "validate":
        if args.json:
            print(json.dumps({"valid": not problems, "errors": problems.items}, indent=2))
        else:
            for item in problems.items:
                print("[fail] " + item)
            print(("[fail] declaration invalid: %d problems" % len(problems.items))
                  if problems else "[ok] declaration valid: " + _summary(decl))
        return 1 if problems else 0

    if problems:
        for item in problems.items:
            print("[fail] " + item, file=sys.stderr)
        print("[fail] declaration invalid; run site_config.py validate", file=sys.stderr)
        return 2

    if args.command == "render":
        print(json.dumps(build_payload(decl, args.mode, environ, redact=True), indent=2))
        return 0
    return run_remote(args.command, decl, environ, as_json=args.json)


if __name__ == "__main__":
    sys.exit(main())
