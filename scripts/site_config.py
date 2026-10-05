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
output.md); every validation rule is in data-model.md. Spec 012's
moodle/site/course-discussions.yaml is retired: shared courses are open across
organisations, so there is nothing to share or separate (spec 002 research R3, R14), and a
file left behind is refused. Its place is taken by moodle/site/org-courses.yaml (spec 002
R11): the courses only one organisation's people may join, read only by load_org_courses(),
which moodle_payload.py also uses.

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
import base64
import decimal
import hashlib
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

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent))
import cbc_wording  # noqa: E402

REPO = pathlib.Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO / "scripts"))
from course_stage import NOT_A_COURSE, branch_slug  # noqa: E402

SITE_DIR = REPO / "moodle" / "site"
MODULES = REPO / "modules"
RETIRED_FILES = {
    # Spec 002 amendment 2026-10-02 (research R14): no organisation groups, so no sharing.
    "course-discussions.yaml": "retired, spec 002 R14: shared courses are open across "
                               "organisations, so there is nothing to share; delete it",
}
ORG_COURSES_FILE = "org-courses.yaml"        # spec 002 R11
OFFICEHOURS_FILE = "office-hours.yaml"       # spec 011
PROTECTION_FILE = "protection.yaml"          # spec 016
DASHBOARD_FILE = "dashboard.yaml"            # spec 011
PATHWAYS_FILE = "pathways.yaml"              # spec 006
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
    "organisations.yaml": ({"rows", "purpose", "categories", "organisations", "mentors"},
                           set()),
    "profile-fields.yaml": ({"rows", "purpose", "category", "fields"}, set()),
    # Spec 002 amendment (R11): the organisation-only courses. Optional; validated by
    # load_org_courses(), not by the loop in validate(), because the publisher reads it too.
    ORG_COURSES_FILE: ({"rows", "org_only"}, set()),
    # Spec 004: report templates and the two course fields. Both optional, as above
    # (specs/004-progress-reporting/contracts/declaration.md).
    "reports.yaml": ({"rows", "purpose", "reports"}, set()),
    "course-fields.yaml": ({"rows", "category", "fields"}, {"purpose"}),
    # Spec 013: the badge template. Optional; certificate/template.yaml sits beside it in its
    # own folder (specs/013-certificates-badges/contracts/declaration.md).
    "badges.yaml": ({"rows", "image", "name", "description", "imagecaption", "message_subject",
                     "message", "version", "language", "why"}, set()),
    # Spec 011: the office-hours course and the default dashboard's blocks. Both optional
    # (specs/011-events-calendar/contracts/declaration.md).
    OFFICEHOURS_FILE: ({"rows", "course", "scheduler", "groups", "why"}, {"purpose"}),
    DASHBOARD_FILE: ({"rows", "default_blocks"}, {"purpose"}),
    # Spec 016: the protection levels and what each withholds. Optional; who is protected is
    # Moodle data and never declared, and no organisation has a minimum (Doug, 2026-10-05
    # (scope review)) (specs/016-identity-protection/contracts/declaration.md).
    PROTECTION_FILE: ({"rows", "levels", "withhold", "neutral_surname",
                       "reconcile_minutes", "why"}, {"purpose"}),
    # Spec 006: role pathways. Optional; missing is roles: [] (specs/006-learning-pathways/
    # contracts/declaration.md).
    PATHWAYS_FILE: ({"rows", "purpose", "roles"}, set()),
}

# --- spec 002: organisations, categories, cohorts and profile fields ---------------------

COMPETENCIES = REPO / "competencies.yaml"   # the expertise areas are its categories
KEY = re.compile(r"^[a-z][a-z0-9-]*$")     # valid in an idnumber and as a menu option
ORG_KEY_MAX = 30
IDNUMBER_MAX = 100                         # course_categories.idnumber, cohort.idnumber
CATEGORY_NAME_MAX = 255                    # course_categories.name
COHORT_NAME_MAX = 254                      # cohort.name
MENTORS_COHORT = "ltct:mentors"            # fixed, not declared (spec 002 R10)
GROUPMODE_SETTING = "moodlecourse/groupmode"
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
ORGMANAGER_CALENDAR = "moodle/calendar:manageentries"   # spec 011 R17: its only calendar capability

MENTOR = "mentor"
# Everything the user-context mentor role may hold (spec 003 research R2), as an allowlist:
# a mentor follows a learner and changes nothing (FR-006), and nothing here rates or
# reviews a competency (FR-013). Widening it is a reviewed change to this line. Spec 006
# may add moodle/competency:planview, read only, and never planreview, planmanage or
# usercompetencyrate (research R11).
MENTOR_ALLOW = frozenset({"moodle/user:viewdetails", "moodle/user:viewuseractivitiesreport",
                          "local/ltuse:viewmenteeprogress",
                          # Spec 016 (R7 path 2): a mentor sees their learner's real identity
                          # and the Protected marker. A reviewed widening (plan, cross-spec 003).
                          "local/ltuse:viewidentity"})
# The local_ltuse version that adds local/ltuse:viewmenteeprogress; from it on, roles.yaml
# must declare the mentor role (FR-001).
MENTOR_SINCE = 2026100301
# Spec 016 (R7, R8, R14): who may hold the protection capabilities, and what the course roles
# must never hold. Widening any of these is a reviewed change to this block.
VIEWIDENTITY = "local/ltuse:viewidentity"
PROTECTION_VIEW_ROLES = frozenset({"manager", MENTOR, "teacher"})
# ltctadmin (spec 008 research R5, agreed with 016 on 2026-10-04): intake grants a new account
# the protection its row asks for through service::set_protection(), and can_manage_protection()
# for an account with no organisation yet needs local/ltuse:manageprotection. The role is the
# site team's own administration account, held at system level, never a manager's.
PROTECTION_MANAGE_ROLES = frozenset({"manager", "ltctadmin"})
PROTECTION_MANAGE_CAPS = ("local/ltuse:manageprotection",)
REPORT_EDIT_CAPS = ("moodle/reportbuilder:edit", "moodle/reportbuilder:editall")
COURSE_LEADER_ROLES = ("editingteacher", "teacher")
COURSE_LEADER_PROHIBIT = ("moodle/backup:downloadfile",)   # R14: email and logs stay (scope review)
# Roles that must not let anyone assign roles: the follow-only roles (spec 003 contract).
NO_ALLOWASSIGN = frozenset({ORGMANAGER, MENTOR})

# Spec 008 (research R12): the site team's administration role, held by each site-team
# member's own account at system level. It is in spec 016's PROTECTION_MANAGE_ROLES above
# (008 task T015), because can_manage_protection for a new account needs
# local/ltuse:manageprotection (research R5).
ADMIN_ROLE = "ltctadmin"


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
            "course_field_category": None, "course_fields": [], "competencies": [],
            "reports": [], "org_courses": [],
            "badge_template": None, "certificate_template": None,
            "officehours": None, "dashboard": [],
            "levels": [], "role_pathways": [], "protection": None}
    if not site_dir.is_dir():
        problems.add(_rel(site_dir), "declaration directory not found")
        return decl, problems

    for path in sorted(site_dir.iterdir()):
        if path.is_file() and path.name in RETIRED_FILES:
            problems.add(_rel(path), RETIRED_FILES[path.name])
        elif path.is_file() and path.suffix in (".yaml", ".yml") and path.name not in TOP_FILES:
            problems.add(_rel(path), "unexpected file; the declaration is site.yaml, "
                         "ignore.yaml, roles.yaml, organisations.yaml, "
                         "profile-fields.yaml, %s, course-fields.yaml, reports.yaml, "
                         "badges.yaml, certificate/template.yaml, %s, %s, %s, %s and "
                         "settings/*.yaml" % (ORG_COURSES_FILE, OFFICEHOURS_FILE,
                                              DASHBOARD_FILE, PATHWAYS_FILE, PROTECTION_FILE))

    loaded = {}
    for name, (required, optional) in TOP_FILES.items():
        if name == ORG_COURSES_FILE:
            continue   # load_org_courses() below; the publisher reads it through the same loader
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
                                "capabilities", "allowassign"}, problems):
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
            # Spec 003 (research R6): roles this one may assign. Additive: apply adds a
            # missing pair, drift reports one, and pairs not declared are left alone.
            assign = role.get("allowassign", [])
            if not isinstance(assign, list) or not all(isinstance(a, str) for a in assign):
                problems.add(rwhere, "allowassign is a list of role shortnames")
                assign = []
            elif len(set(assign)) != len(assign):
                problems.add(rwhere, "allowassign names a role twice")
            if assign and short in NO_ALLOWASSIGN:
                problems.add(rwhere, "%s assigns no roles; it follows people and changes "
                             "nothing" % short)
            out["allowassign"] = list(assign)
            decl["roles"].append(out)
        for out in decl["roles"]:
            for target in out["allowassign"]:
                if target not in role_names:
                    problems.add("%s %s" % (where, out["shortname"]),
                                 "allowassign names role %r, which is neither core nor in "
                                 "roles.yaml" % target)

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
        elif role["shortname"] == MENTOR:
            _check_mentor(role, problems)
        elif role["shortname"] == ADMIN_ROLE:
            _check_admin_role(role, problems)
    # FR-001 (spec 003): once local_ltuse defines the mentor capability, the role that holds
    # it must be declared, or the Mentoring page has nobody to show.
    ltuse = next((p for p in decl["plugins"] if p.get("component") == "local_ltuse"), None)
    if ltuse and _is_int(ltuse.get("version")) and ltuse["version"] >= MENTOR_SINCE and \
            MENTOR not in {r["shortname"] for r in decl["roles"]}:
        problems.add("roles.yaml", "the mentor role is missing, but local_ltuse %d defines "
                     "local/ltuse:viewmenteeprogress (FR-001)" % ltuse["version"])
    # Spec 002 amendment (R3, FR-011): shared courses are open across organisations. A
    # course may still use groups for teaching, but never as the site default.
    for setting in decl["settings"]:
        if setting.get("name") == GROUPMODE_SETTING and str(setting.get("value")) != "0":
            problems.add(setting.get("file", "settings"),
                         "%s must be 0: shared courses are open across organisations, and "
                         "groups never separate organisations (spec 002 R3)" % GROUPMODE_SETTING)
    _expand(decl, orgs, fields)

    # org-courses.yaml (spec 002 R11): the organisation keys are the ones validated above.
    org_keys = {o["key"] for o in orgs["organisations"]} if orgs is not None else set()
    org_courses, course_problems = load_org_courses(site_dir, modules_dir, org_keys=org_keys)
    problems.items.extend(course_problems.items)
    decl["org_courses"] = [{"slug": c["slug"], "course_idnumber": "ltct:" + c["slug"],
                            "category_idnumber": "ltct:org:" + c["organisation"]}
                           for c in org_courses]

    # Spec 004: the competency list, the course fields, then the reports, which read both.
    decl["competencies"] = _competency_list(problems)
    # Spec 006: the four level labels, and the role pathways, which name competencies.
    decl["levels"] = _pathway_levels(problems)
    if PATHWAYS_FILE in loaded:
        path, data = loaded[PATHWAYS_FILE]
        decl["role_pathways"] = _validate_pathways(_rel(path), data, rows, decl, problems)
    if "course-fields.yaml" in loaded:
        path, data = loaded["course-fields.yaml"]
        cfields = _validate_course_fields(_rel(path), data, rows, problems)
        decl["course_field_category"] = cfields["category"]
        decl["course_fields"] = cfields["fields"]
    if "reports.yaml" in loaded:
        path, data = loaded["reports.yaml"]
        decl["reports"] = _validate_reports(
            _rel(path), data, rows, orgs, role_names, decl, problems)

    # Spec 013: the badge template and the certificate template, then what they need.
    if "badges.yaml" in loaded:
        path, data = loaded["badges.yaml"]
        decl["badge_template"] = _validate_badges(_rel(path), data, rows, site_dir, decl,
                                                  problems)
    cert = site_dir / CERT_DIR / CERT_FILE
    if cert.exists():
        data = _load(cert, problems)
        if data is None:
            problems.add(_rel(cert), "empty")
        else:
            decl["certificate_template"] = _validate_certificate(_rel(cert), data, rows,
                                                                 site_dir, decl, problems)
    if "badges.yaml" in loaded or cert.exists():
        _check_recognition_site(decl, problems)

    # Spec 011: the office-hours course, the dashboard's blocks, and the calendar rules.
    if OFFICEHOURS_FILE in loaded:
        path, data = loaded[OFFICEHOURS_FILE]
        decl["officehours"] = _validate_office_hours(_rel(path), data, rows, orgs, problems)
        _check_office_hours_site(decl, problems)
    if DASHBOARD_FILE in loaded:
        path, data = loaded[DASHBOARD_FILE]
        decl["dashboard"] = _validate_dashboard(_rel(path), data, rows, problems)
    _check_calendar_settings(decl, problems)

    # Spec 016: protection.yaml, then what it needs from the rest of the declaration.
    if PROTECTION_FILE in loaded:
        path, data = loaded[PROTECTION_FILE]
        decl["protection"] = _validate_protection(_rel(path), data, rows, decl, problems)
        _check_protection_site(decl, problems)
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


# Spec 004 FR-010: a report never shows a learner at a CBC level. The rule lives in
# cbc_wording.py, with spec 013's badge and certificate rule beside it.
def _check_aim_label(where, label, problems, strict=False):
    """A report label (FR-010). strict=True is for competency-coverage."""
    for message in cbc_wording.report_label_problems(label, strict=strict):
        problems.add(where, message)


def _validate_organisations(where, data, rows, problems):
    """Check organisations.yaml. Returns {categories, organisations}: the valid entries."""
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    if not _text(data.get("purpose")):
        problems.add(where, "purpose must say what this file is for")
    out = {"categories": [], "organisations": [], "mentors": None}

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

    # The one mentors cohort (2026-10-02, research R10): mentors come from any organisation,
    # so it belongs to none, and its idnumber is fixed rather than declared.
    mentors = data.get("mentors")
    mwhere = "%s mentors" % where
    if _check_keys(mwhere, mentors, {"name", "why"}, set(), problems):
        if not _text(mentors.get("why")):
            problems.add(mwhere, "why must say which row or spec needs it")
        if _check_name(mwhere, "name", mentors.get("name"), COHORT_NAME_MAX, problems):
            out["mentors"] = {"name": mentors["name"]}
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
        # Spec 011 (D3, R17): a manager may post events in their organisation's own courses,
        # and nothing else in the calendar. manageentries in a course never reaches a site
        # event, and every other calendar capability would.
        if isinstance(cap, str) and cap.startswith("moodle/calendar:") and \
                cap != ORGMANAGER_CALENDAR:
            problems.add(where, "%s: orgmanager holds no calendar capability but %s (spec 011 "
                         "R17)" % (cap, ORGMANAGER_CALENDAR))


def _check_mentor(role, problems):
    """The user-context mentor role (spec 003: FR-002, FR-006, FR-013; research R2)."""
    where = "roles.yaml %s" % MENTOR
    if role.get("contextlevels") != ["user"]:
        problems.add(where, "contextlevels must be exactly [user]; a mentor relationship is "
                     "with a learner, not an enrolment (FR-002)")
    if role.get("archetype") != "":
        problems.add(where, 'archetype must be "", so every capability is managed and one '
                     "granted by hand shows as drift")
    for cap, perm in role.get("capabilities", {}).items():
        if perm == "prohibit":
            problems.add(where, "%s: mentor uses no prohibit, so a mentor who is also a "
                         "manager keeps both roles" % cap)
        if cap not in MENTOR_ALLOW:
            problems.add(where, "%s is not on the mentor allowlist: a mentor follows "
                         "progress and changes nothing (FR-006, FR-013)" % cap)


def _check_admin_role(role, problems):
    """The site team's administration role (spec 008, research R12)."""
    where = "roles.yaml %s" % ADMIN_ROLE
    if role.get("contextlevels") != ["system"]:
        problems.add(where, "contextlevels must be exactly [system]; each site-team member "
                     "holds it once, at system level (R12)")
    if role.get("archetype") != "":
        problems.add(where, 'archetype must be "", so it is never granted by default and a '
                     "capability added by hand shows as drift")


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
        if orgs["mentors"] is not None:
            # Filled by hand, so no rule (R10).
            decl["cohorts"].append({"idnumber": MENTORS_COHORT, "name": orgs["mentors"]["name"],
                                    "visible": 0})
    if fields is not None:
        for field in fields["fields"]:
            decl["profile_fields"].append(dict(field, category=fields["category"]))


# --- spec 004: the competency list, course fields and reports ---------------------------

COMPETENCY_NAME_MAX = 255                  # local_ltuse_competency.name and .category
COMPETENCY_BAD = re.compile(r"[\x00-\x1f\x7f\[\]]")   # [Name] [Name] is the field's format

COURSE_FIELD_NAME_MAX = 1333               # customfield_field.name, customfield_category.name
COURSE_FIELD_SHORTNAME_MAX = 100           # customfield_field.shortname
COURSE_FIELD_TYPES = ("text",)
REQUIRED_COURSE_FIELDS = ("ltct_competencies", "ltct_target_level")   # the publisher writes them
# core_course\customfield\course_handler VISIBLETOALL / VISIBLETOTEACHERS / NOTVISIBLE.
COURSE_VISIBILITY = {"everyone": 2, "teachers": 1, "nobody": 0}
COURSE_VISIBLE_TO_ALL = 2

# reportbuilder_report.area is PARAM_AREA: core_component::is_valid_plugin_name()'s pattern
# (lib/classes/component.php), in a char(100) column.
REPORT_AREA = re.compile(r"^[a-z](?:[a-z0-9_](?!__))*[a-z0-9]+$")
REPORT_AREA_MAX = 100
REPORT_TEXT_MAX = 255                      # report name, column heading, schedule name/subject
REPORT_IDENTIFIER = re.compile(r"^[a-z][a-z0-9_]*:[a-z][a-z0-9_]*$")   # entity:name
DATASOURCE = re.compile(r"^[a-z][a-z0-9_]*(?:\\[a-z][a-z0-9_]*)+$")
# reportbuilder/classes/local/aggregation/*.php on MOODLE_502_STABLE.
AGGREGATIONS = frozenset(
    "avg count countdistinct date groupconcat groupconcatdistinct max min percent sum".split())
# Which aggregations a column type allows: each class's compatible() in
# reportbuilder/classes/local/aggregation/ on MOODLE_502_STABLE (count and countdistinct
# take every type; groupconcatdistinct only on Postgres and MySQL, and we run Postgres).
AGGREGATIONS_BY_TYPE = {
    "text": frozenset("count countdistinct groupconcat groupconcatdistinct".split()),
    "timestamp": frozenset("count countdistinct date max min".split()),
    "float": frozenset("avg count countdistinct groupconcat groupconcatdistinct max min sum"
                       .split()),
}
# The type of each column a declaration may aggregate, from its entity's set_type() on
# MOODLE_502_STABLE (contracts/declaration.md "Aggregation"). None means the column calls
# set_disabled_aggregation_all(). validate refuses an aggregation on a column not listed
# here; add the column (with its source line) before aggregating it. apply's own check
# against aggregation::get_column_aggregations() stays the authority.
COLUMN_TYPES = {
    "user:fullnamewithlink": "text",           # reportbuilder/classes/local/entities/user.php
    "user:username": "text",                   # user.php get_user_field_type() default
    "group:name": "text",                      # group/classes/reportbuilder/local/entities/group.php
    "course:coursefullnamewithlink": "text",   # reportbuilder/classes/local/entities/course.php
    "enrolment:timecreated": "timestamp",      # course/classes/reportbuilder/local/entities/enrolment.php
    "completion:timestarted": "timestamp",     # course/classes/reportbuilder/local/entities/completion.php
    "completion:progresspercent": "text",
    "completion:grade": "float",
    "completion:timecompleted": "timestamp",
    "access:timeaccess": "timestamp",          # course/classes/reportbuilder/local/entities/access.php
}
# A menu profile field and our text course fields are text columns (user_profile_fields.php,
# custom_fields.php); our competency and coverage entities disable every aggregation.
COLUMN_TYPE_PATTERNS = (
    (re.compile(r"^user:profilefield_" + re.escape(ORG_FIELD) + r"$"), "text"),
    (re.compile(r"^course:customfield_ltct_[a-z0-9_]+$"), "text"),
    (re.compile(r"^(?:competency|coverage):[a-z0-9_]+$"), None),
)
ORG_PLACEHOLDER = "{org}"
PER_VALUES = ("organisation",)
# The three conditions every per-organisation report carries, verbatim (FR-005).
# The organisation is its member cohort, ltct:org:<key>, matched on cohort:idnumber (a text
# condition, filters\text IS_EQUAL_TO 3; the participants datasource joins the cohort entity
# through cohort_members, course/classes/reportbuilder/datasource/participants.php on
# MOODLE_502_STABLE). A cohort that does not exist matches nobody, so the scope fails closed,
# and it does not depend on ltct_org's visibility (spec 016 T034-T035, Doug, 2026-10-05 (scope
# review), decision 2 option a).
# A select stores the option key, so apply turns role:name's shortname into the role id.
# Delivery is any enrolment but a pilot's manual one (spec 002 R10, amending spec 004 R10):
# cohort sync, and a manager's enrolment through the organisation-enrolment instance
# (enrol_self). A select condition holds one value, so it is "not manual", never a list.
SCOPE_CONDITIONS = (
    ("cohort:idnumber", {"operator": "equal", "value": "ltct:org:" + ORG_PLACEHOLDER}),
    ("role:name", {"operator": "equal", "value": "student"}),
    ("enrol:plugin", {"operator": "not_equal", "value": "manual"}),
)
# Never a report condition: report builder offers a profile field's condition only while the
# field is visible (user_profile_fields.php L214) and silently skips an unavailable one
# (datasource.php L288-315), so a scope on ltct_org would widen the moment the field was
# hidden (spec 016 R11).
ORG_FIELD_CONDITION = "user:profilefield_" + ORG_FIELD
# Operator words the applier maps to filter constants: select::EQUAL_TO (1) and
# NOT_EQUAL_TO (2), public/reportbuilder/classes/local/filters/select.php on MOODLE_502_STABLE.
CONDITION_OPERATORS = ("equal", "not_equal")
MANAGERS_AUDIENCE = {"type": "cohortmember", "cohort": "ltct:org:%s:managers" % ORG_PLACEHOLDER}
AUDIENCE_KEYS = {"cohortmember": "cohort", "systemrole": "role"}
SORT_DIRECTIONS = ("asc", "desc")
CUSTOMFIELD_COLUMN = re.compile(r"^course:customfield_(\w+)$")

# competency-coverage (contracts/declaration.md "Validation rules for competency-coverage").
COVERAGE_KEY = "competency-coverage"
COVERAGE_SOURCE = "local_ltuse\\reportbuilder\\datasource\\competency_coverage"
COVERAGE_ENTITIES = ("competency", "coverage")
COVERAGE_AUDIENCES = [{"type": "systemrole", "role": "manager"}]

# The message schedule type (core_reportbuilder\local\models\schedule and
# reportbuilder\schedule\message on MOODLE_502_STABLE).
SCHEDULE_KEYS = {"recurrence", "format", "viewas", "send_when_empty", "start", "subject",
                 "message"}
RECURRENCE = {"none": 0, "daily": 1, "weekdays": 2, "weekly": 3, "monthly": 4, "annually": 5,
              "hourly": 6}
VIEWAS = {"recipient": -1}                 # REPORT_VIEWAS_RECIPIENT; never creator (R12)
SEND_WHEN_EMPTY = {0: 2}                   # 0 = don't send: REPORT_EMPTY_DONT_SEND is 2
SCHEDULE_START = re.compile(r"^(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday) "
                            r"(?:[01][0-9]|2[0-3]):[0-5][0-9]$")
FORMAT_HTML = 1


def _competency_list(problems):
    """The payload's competencies: competencies.yaml without Meta, in file order."""
    where = _rel(COMPETENCIES)
    try:
        with open(COMPETENCIES, encoding="utf-8") as fh:
            data = yaml.safe_load(fh)
    except (OSError, yaml.YAMLError) as exc:
        problems.add(where, "cannot read the competency list: %s" % exc)
        return []
    if not isinstance(data, dict):
        problems.add(where, "must map each category to its competencies")
        return []
    slugs = _descriptor_slugs(problems)
    base = _site_url(problems)
    out, seen, slugs_seen = [], set(), {}
    for category, names in data.items():
        if not isinstance(names, list):
            problems.add(where, "%s must be a list of competency names" % (category,))
            continue
        for name in names:
            if not _text(name):
                problems.add(where, "%s: a competency name must be text, not %r"
                             % (category, name))
                continue
            if name in seen:
                problems.add(where, "%r is listed twice; a competency has one row" % name)
                continue
            seen.add(name)
            if category == META_CATEGORY:
                continue   # Uncategorized is not a competency a course aims at
            ok = True
            for label, value in (("competency", name), ("category", str(category))):
                if len(value) > COMPETENCY_NAME_MAX:
                    problems.add(where, "%s %r is %d characters; the column holds %d"
                                 % (label, value, len(value), COMPETENCY_NAME_MAX))
                    ok = False
                if COMPETENCY_BAD.search(value):
                    problems.add(where, "%s %r holds a control character, [ or ]; the course "
                                 "field writes names as [Name] [Name]" % (label, value))
                    ok = False
            # Spec 006 (R3, R4): the descriptor's slug, and the page gen_site.py makes of it.
            slug = slugs.get(name) if slugs is not None else None
            if slugs is not None and slug is None:
                problems.add(where, "%r has no descriptor in competencies/ whose name matches "
                             "it exactly; a competency pathway links to that page" % name)
                ok = False
            elif slug is not None:
                if not isinstance(slug, str) or not COMPETENCY_SLUG.match(slug) or \
                        len(slug) > COMPETENCY_SLUG_MAX:
                    problems.add(where, "%r: descriptor slug %r must match %s and be at most "
                                 "%d characters, so competency:<slug> fits a pathway key"
                                 % (name, slug, COMPETENCY_SLUG.pattern, COMPETENCY_SLUG_MAX))
                    ok = False
                elif slug in slugs_seen:
                    problems.add(where, "%r and %r share the slug %r; a slug names one "
                                 "competency pathway" % (slugs_seen[slug], name, slug))
                    ok = False
                else:
                    slugs_seen[slug] = name
            before = len(problems.items)
            _check_aim_label(where, name, problems)
            if ok and slug is not None and base is not None and len(problems.items) == before:
                out.append({"name": name, "category": str(category),
                            "sortorder": len(out) + 1, "slug": slug,
                            "url": "%s%s/%s/" % (base, site_slugify(str(category)), slug)})
    return out


# --- spec 006: learning pathways ----------------------------------------------------------

DESCRIPTORS = REPO / "competencies"        # one descriptor per competency, frontmatter slug
MKDOCS = REPO / "mkdocs.yml"                # its site_url is the competency site's host
OUTCOME_LEVELS = REPO / "outcome-levels.yaml"
COMPETENCY_SLUG = re.compile(r"^[a-z0-9][a-z0-9-]*$")
COMPETENCY_SLUG_MAX = 94                    # competency:<slug> fits the 100-character key
ROLE_KEY_MAX = 95                           # role:<key> fits the 100-character key
ROLE_NAME_MAX = 255                         # local_ltuse_role_pathway.name
PATHWAY_LEVELS = [1, 2, 3, 4]               # course_target_levels, exactly
LEVEL_NUMBER = re.compile(r"\blevel\s*[0-4]\b", re.I)


def site_slugify(name):
    """gen_site.slugify(), copied: gen_site imports mkdocs_gen_files, which validate needs
    not have. The two must give the same answer, or a competency's url misses its page."""
    s = name.lower().replace("&", "and")
    s = re.sub(r"[()]", "", s)
    return re.sub(r"[^a-z0-9]+", "-", s).strip("-")


def _descriptor_slugs(problems):
    """{name: slug} from competencies/*.md frontmatter, read as gen_site.py reads it (a
    missing slug is the file's stem), or None when the folder cannot be read."""
    if not DESCRIPTORS.is_dir():
        problems.add(_rel(DESCRIPTORS), "not found; a competency's slug comes from its "
                     "descriptor")
        return None
    out = {}
    for path in sorted(DESCRIPTORS.glob("*.md")):
        try:
            text = path.read_text(encoding="utf-8")
        except OSError as exc:
            problems.add(_rel(path), "cannot read: %s" % exc)
            continue
        if not text.startswith("---"):
            continue
        end = text.find("\n---", 3)
        if end == -1:
            continue
        try:
            fm = yaml.safe_load(text[3:end]) or {}
        except yaml.YAMLError as exc:
            problems.add(_rel(path), "frontmatter is not YAML: %s" % exc)
            continue
        if isinstance(fm, dict) and isinstance(fm.get("name"), str):
            out[fm["name"]] = fm.get("slug", path.stem)
    return out


class _MkdocsLoader(yaml.SafeLoader):
    """safe_load for mkdocs.yml, which carries !ENV and !!python/name tags: read as None."""


_MkdocsLoader.add_multi_constructor("", lambda loader, suffix, node: None)


def _site_url(problems):
    """mkdocs.yml's site_url with one trailing /, or None (and a problem) if not https."""
    where = _rel(MKDOCS)
    try:
        with open(MKDOCS, encoding="utf-8") as fh:
            data = yaml.load(fh, Loader=_MkdocsLoader)
    except (OSError, yaml.YAMLError) as exc:
        problems.add(where, "cannot read site_url: %s" % exc)
        return None
    url = data.get("site_url") if isinstance(data, dict) else None
    if not _text(url) or not url.startswith("https://") or \
            not urllib.parse.urlsplit(url).netloc:
        problems.add(where, "site_url must be an https URL; a competency's url is built "
                     "from it")
        return None
    return url.rstrip("/") + "/"


def _pathway_levels(problems):
    """The payload's levels: course_target_levels with their labels, verbatim."""
    where = _rel(OUTCOME_LEVELS)
    try:
        with open(OUTCOME_LEVELS, encoding="utf-8") as fh:
            data = yaml.safe_load(fh)
        known = cbc_wording.cbc_labels(OUTCOME_LEVELS)
    except (OSError, yaml.YAMLError, KeyError, TypeError) as exc:
        problems.add(where, "cannot read the level labels: %s" % exc)
        return []
    targets = data.get("course_target_levels") if isinstance(data, dict) else None
    if targets != PATHWAY_LEVELS:
        problems.add(where, "course_target_levels must be exactly %s; a pathway has a row "
                     "for each" % PATHWAY_LEVELS)
        return []
    labels = {lv.get("id"): lv.get("label") for lv in data.get("levels") or []
              if isinstance(lv, dict)}
    out = []
    for level in targets:
        label = labels.get(level)
        if not _text(label) or label not in known:
            problems.add(where, "level %d has no CBC label" % level)
            return []
        out.append({"level": level, "label": label})
    return out


def _check_role_text(where, label, value, problems):
    """A role's name or description: CBC wording, and never a level a learner holds."""
    _check_aim_label(where, value, problems, strict=True)
    lowered = value.lower()
    try:
        labels = cbc_wording.cbc_labels(OUTCOME_LEVELS)
    except (OSError, yaml.YAMLError, KeyError, TypeError):
        labels = []
    for level_label in labels:
        if level_label.lower() in lowered:
            problems.add(where, "%s names the CBC level %r; a role names work, never a "
                         "level a learner holds" % (label, level_label))
    if LEVEL_NUMBER.search(value):
        problems.add(where, "%s names a level by number; a role names work, never a level "
                     "a learner holds" % label)


def _validate_pathways(where, data, rows, decl, problems):
    """Check pathways.yaml. Returns the payload's role_pathways array."""
    _check_rows(where, data, rows, problems)
    if not _text(data.get("purpose")):
        problems.add(where, "purpose must be text")
    roles = data.get("roles")
    if not isinstance(roles, list):
        problems.add(where, "roles must be a list, [] when no role is declared")
        return []
    live = {c["name"] for c in decl["competencies"]}
    meta = set()
    try:
        with open(COMPETENCIES, encoding="utf-8") as fh:
            framework = yaml.safe_load(fh)
        if isinstance(framework, dict) and isinstance(framework.get(META_CATEGORY), list):
            meta = {str(n) for n in framework[META_CATEGORY]}
    except (OSError, yaml.YAMLError):
        pass   # _competency_list has already said so
    out, keys = [], set()
    for i, role in enumerate(roles):
        rwhere = "%s roles[%d]" % (where, i)
        if not _check_keys(rwhere, role, {"key", "name", "competencies", "why"},
                           {"description"}, problems):
            continue
        ok = True
        key = role.get("key")
        if not isinstance(key, str) or not KEY.match(key) or len(key) > ROLE_KEY_MAX:
            problems.add(rwhere, "key %r must match %s and be at most %d characters, so "
                         "role:<key> fits a pathway key" % (key, KEY.pattern, ROLE_KEY_MAX))
            ok = False
        else:
            rwhere = "%s role %s" % (where, key)
            if key in keys:
                problems.add(rwhere, "declared twice")
                ok = False
            keys.add(key)
        name = role.get("name")
        if _check_name(rwhere, "name", name, ROLE_NAME_MAX, problems):
            _check_role_text(rwhere, "name", name, problems)
        else:
            ok = False
        description = role.get("description")
        if description is None:
            description = ""
        elif not isinstance(description, str):
            problems.add(rwhere, "description must be text")
            ok = False
        elif description:
            _check_role_text(rwhere, "description", description, problems)
        if not _text(role.get("why")):
            problems.add(rwhere, "why must say who supplied the role")
            ok = False
        comps = role.get("competencies")
        if not isinstance(comps, list) or not comps:
            problems.add(rwhere, "competencies must be a non-empty list of names from "
                         "competencies.yaml")
            ok = False
            comps = []
        seen = set()
        for comp in comps:
            if not isinstance(comp, str):
                problems.add(rwhere, "competency %r must be text" % (comp,))
                ok = False
                continue
            if comp in seen:
                problems.add(rwhere, "lists %r twice" % comp)
                ok = False
            elif comp in meta:
                problems.add(rwhere, "%r is in %s, which is not a competency a pathway "
                             "covers" % (comp, META_CATEGORY))
                ok = False
            elif comp not in live:
                problems.add(rwhere, "%r is not in competencies.yaml (names compare exactly, "
                             "case, & and spacing included)" % comp)
                ok = False
            seen.add(comp)
        if ok:
            out.append({"key": key, "name": name, "description": description,
                        "sortorder": i, "competencies": list(comps)})
    return out


def _validate_course_fields(where, data, rows, problems):
    """Check course-fields.yaml. Returns {category, fields}: the valid entries."""
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    out = {"category": None, "fields": []}
    category = data.get("category")
    if _check_name(where, "category", category, COURSE_FIELD_NAME_MAX, problems):
        _check_aim_label(where + " category", category, problems)
        out["category"] = category
    fields = data.get("fields")
    if not isinstance(fields, list):
        problems.add(where, "fields must be a list")
        fields = []
    seen = set()
    for i, field in enumerate(fields):
        fwhere = "%s fields[%d]" % (where, i)
        if not _check_keys(fwhere, field, {"shortname", "name", "type", "locked", "visibility",
                                           "why"}, set(), problems):
            continue
        short = field.get("shortname")
        if not (isinstance(short, str) and FIELD_SHORTNAME.match(short)) or \
                len(short) > COURSE_FIELD_SHORTNAME_MAX:
            problems.add(fwhere, "shortname %r must match %s, in at most %d characters"
                         % (short, FIELD_SHORTNAME.pattern, COURSE_FIELD_SHORTNAME_MAX))
            continue
        fwhere = "%s %s" % (where, short)
        if short in seen:
            problems.add(fwhere, "declared twice")
            continue
        seen.add(short)
        ok = _check_name(fwhere, "name", field.get("name"), COURSE_FIELD_NAME_MAX, problems)
        before = len(problems.items)
        _check_aim_label(fwhere + " name", field.get("name"), problems)
        ok &= len(problems.items) == before
        if field.get("type") not in COURSE_FIELD_TYPES:
            problems.add(fwhere, "type must be %s" % " or ".join(COURSE_FIELD_TYPES))
            ok = False
        locked = field.get("locked")
        if isinstance(locked, Flag):
            problems.add(fwhere, "locked: YAML boolean %r; write 1 or 0" % locked.text)
            ok = False
        elif not (_is_int(locked) and locked in (0, 1)):
            problems.add(fwhere, "locked must be 1 or 0")
            ok = False
        visibility = field.get("visibility")
        if not isinstance(visibility, str) or visibility not in COURSE_VISIBILITY:
            problems.add(fwhere, "visibility must be one of %s" % ", ".join(COURSE_VISIBILITY))
            ok = False
        if not _text(field.get("why")):
            problems.add(fwhere, "why must say which row or spec needs it")
        if ok:
            out["fields"].append({"shortname": short, "name": field["name"], "type": "text",
                                  "locked": locked,
                                  "visibility": COURSE_VISIBILITY[visibility]})
    for short in REQUIRED_COURSE_FIELDS:
        if short not in seen:
            problems.add(where, "%s must be declared: the publisher writes it (R11)" % short)
    return out


def _report_area(key, org_key=None):
    """The encoded report area: PARAM_AREA has no hyphen or colon."""
    area = key if org_key is None else "org_%s_%s" % (org_key, key)
    return area.replace("-", "_")


def _substitute(node, value):
    """node with every {org} replaced by value (strings, lists and dicts)."""
    if isinstance(node, str):
        return node.replace(ORG_PLACEHOLDER, value)
    if isinstance(node, list):
        return [_substitute(v, value) for v in node]
    if isinstance(node, dict):
        return {k: _substitute(v, value) for k, v in node.items()}
    return node


def _validate_reports(where, data, rows, orgs, role_names, decl, problems):
    """Check reports.yaml and expand it. Returns the payload's reports array."""
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    if not _text(data.get("purpose")):
        problems.add(where, "purpose must say what this file is for")
    templates = data.get("reports")
    if not isinstance(templates, list):
        problems.add(where, "reports must be a list")
        templates = []
    fields = {f["shortname"]: f for f in decl["course_fields"]}
    cohorts = {c["idnumber"] for c in decl["cohorts"]}
    organisations = orgs["organisations"] if orgs is not None else []
    out, keys, areas = [], set(), {}
    for i, template in enumerate(templates):
        rwhere = "%s reports[%d]" % (where, i)
        if not _check_keys(rwhere, template, {"key", "name", "source", "uniquerows", "columns",
                                              "conditions", "filters", "audiences", "why"},
                           {"per", "sorting", "schedule"}, problems):
            continue
        key = template.get("key")
        if not (isinstance(key, str) and KEY.match(key)):
            problems.add(rwhere, "key %r must match %s" % (key, KEY.pattern))
            continue
        rwhere = "%s report %s" % (where, key)
        if key in keys:
            problems.add(rwhere, "declared twice")
            continue
        keys.add(key)
        before = len(problems.items)
        report = _check_report(rwhere, key, template, role_names, fields, cohorts, problems)
        if report is None or len(problems.items) != before:
            continue
        per = template.get("per")
        if per is None:
            expansions = [(None, None)]
        elif not organisations:
            problems.add(rwhere, "per: organisation, but organisations.yaml is missing or "
                         "declares no organisations")
            continue
        else:
            expansions = [(o["key"], o["name"]) for o in organisations]
        for org_key, org_name in expansions:
            area = _report_area(key, org_key)
            awhere = "%s report %s" % (where, area)
            if not REPORT_AREA.match(area):
                problems.add(awhere, "area %r is not a valid PARAM_AREA (%s): no doubled, "
                             "leading or trailing hyphen" % (area, REPORT_AREA.pattern))
                continue
            if len(area) > REPORT_AREA_MAX:
                problems.add(awhere, "area is %d characters; reportbuilder_report.area holds "
                             "%d" % (len(area), REPORT_AREA_MAX))
                continue
            if area in areas:
                problems.add(awhere, "area %r is already taken by %s; areas must be unique "
                             "once encoded" % (area, areas[area]))
                continue
            areas[area] = "report %s" % key + (" for %s" % org_key if org_key else "")
            if org_key is None:
                expanded = dict(report)
            else:
                expanded = {k: v for k, v in report.items() if k != "schedule"}
                # Names and message text take the organisation's name; condition values and
                # the audience cohort take its key.
                expanded["name"] = report["name"].replace(ORG_PLACEHOLDER, org_name)
                expanded["conditions"] = _substitute(report["conditions"], org_key)
                expanded["audiences"] = _substitute(report["audiences"], org_key)
                expanded["schedule"] = _substitute(report["schedule"], org_name)
            strict = key == COVERAGE_KEY
            labels = [("name", expanded["name"])] + [
                ("heading of %s" % c["column"], c["heading"]) for c in expanded["columns"]
                if c["heading"] is not None]
            if expanded["schedule"] is not None:
                configdata = expanded["schedule"]["configdata"]
                labels.append(("schedule subject", configdata["subject"]))
                # The weekly email's body goes to every organisation manager (FR-010). It
                # is HTML, so tags are dropped before the words are read.
                labels.append(("schedule message",
                               re.sub(r"<[^>]*>", " ", configdata["message"]["text"])))
            before = len(problems.items)
            for label, value in labels:
                if org_key is not None and label in ("name", "schedule subject"):
                    _check_name(awhere, label, value, REPORT_TEXT_MAX, problems)
                _check_aim_label("%s %s" % (awhere, label), value, problems, strict=strict)
            if len(problems.items) == before:
                out.append(dict(expanded, area=area))
    return [{k: r[k] for k in ("area", "name", "source", "uniquerows", "columns",
                               "conditions", "filters", "sorting", "audiences", "schedule")}
            for r in out]


def _check_report(where, key, template, role_names, fields, cohorts, problems):
    """One template, before expansion. Returns its payload entry with {org} unexpanded."""
    per = template.get("per")
    if "per" in template and per not in PER_VALUES:
        problems.add(where, "per must be %s, or absent" % " or ".join(PER_VALUES))
        return None
    coverage = key == COVERAGE_KEY
    if coverage and per is not None:
        problems.add(where, "%s is one report for the site; it takes no per" % COVERAGE_KEY)
    if not _text(template.get("why")):
        problems.add(where, "why must say which row or spec needs it")
    if per is None:
        for path, value in _walk_strings(template, ()):
            if ORG_PLACEHOLDER in value:
                problems.add(where, "%s uses %s, but the report has no per: organisation"
                             % (".".join(path), ORG_PLACEHOLDER))
    name = template.get("name")
    _check_name(where, "name", name, REPORT_TEXT_MAX, problems)
    source = template.get("source")
    if not (isinstance(source, str) and DATASOURCE.match(source)):
        problems.add(where, "source %r is not a report builder datasource class" % (source,))
    elif coverage and source != COVERAGE_SOURCE:
        problems.add(where, "%s's source is %s" % (COVERAGE_KEY, COVERAGE_SOURCE))
    uniquerows = template.get("uniquerows")
    if not (_is_int(uniquerows) and uniquerows in (0, 1)):
        problems.add(where, "uniquerows must be 1 or 0")

    columns = _check_columns(where, template.get("columns"), coverage, fields, problems)
    conditions = _check_conditions(where, template.get("conditions"), per, role_names, problems)
    if coverage and template.get("conditions"):
        problems.add(where, "%s takes no condition: a condition only drops rows, and its "
                     "zero rows are the point (FR-013)" % COVERAGE_KEY)
    filters = _check_identifiers(where, "filters", template.get("filters"), problems)
    sorting = _check_sorting(where, template.get("sorting", []),
                             [c["column"] for c in columns], problems)
    audiences = _check_audiences(where, template.get("audiences"), per, role_names, cohorts,
                                 problems)
    if coverage and audiences != COVERAGE_AUDIENCES:
        problems.add(where, "%s's only audience is systemrole manager" % COVERAGE_KEY)
    schedule = None
    if "schedule" in template:
        schedule = _check_schedule(where, template["schedule"], problems)
        if not template.get("audiences"):
            problems.add(where, "a schedule is sent to the report's audiences, and it has none")
    return {"name": name, "source": source, "uniquerows": uniquerows, "columns": columns,
            "conditions": conditions, "filters": filters, "sorting": sorting,
            "audiences": audiences, "schedule": schedule}


def _check_identifiers(where, label, items, problems):
    if not isinstance(items, list):
        problems.add(where, "%s must be a list of entity:name identifiers" % label)
        return []
    out = []
    for item in items:
        if not (isinstance(item, str) and REPORT_IDENTIFIER.match(item)):
            problems.add(where, "%s: %r is not an entity:name identifier" % (label, item))
        elif item in out:
            problems.add(where, "%s: %s is listed twice" % (label, item))
        else:
            out.append(item)
    return out


def _check_columns(where, columns, coverage, fields, problems):
    if not isinstance(columns, list) or not columns:
        problems.add(where, "columns must be a non-empty list")
        return []
    out, seen = [], set()
    for i, col in enumerate(columns):
        cwhere = "%s columns[%d]" % (where, i)
        if not _check_keys(cwhere, col, {"column"}, {"heading", "aggregation"}, problems):
            continue
        ident = col.get("column")
        if not (isinstance(ident, str) and REPORT_IDENTIFIER.match(ident)):
            problems.add(cwhere, "column %r is not an entity:name identifier" % (ident,))
            continue
        if ident in seen:
            problems.add(cwhere, "%s is listed twice" % ident)
            continue
        seen.add(ident)
        heading = col.get("heading")
        if "heading" in col:
            _check_name(cwhere, "heading", heading, REPORT_TEXT_MAX, problems)
        aggregation = col.get("aggregation")
        if "aggregation" in col and aggregation not in AGGREGATIONS:
            problems.add(cwhere, "aggregation %r is not one of %s"
                         % (aggregation, ", ".join(sorted(AGGREGATIONS))))
        elif "aggregation" in col:
            _check_column_aggregation(cwhere, ident, aggregation, problems)
        entity = ident.split(":")[0]
        if coverage and entity not in COVERAGE_ENTITIES:
            problems.add(cwhere, "%s's columns come only from the %s entities, not %s"
                         % (COVERAGE_KEY, " and ".join(COVERAGE_ENTITIES), ident))
        if coverage and ident == "competency:name" and isinstance(heading, str) and \
                "aim at" not in heading.lower():
            problems.add(cwhere, "the competency heading must say what courses aim at")
        m = CUSTOMFIELD_COLUMN.match(ident)
        if m and m.group(1).startswith("ltct_"):
            field = fields.get(m.group(1))
            if field is None:
                problems.add(cwhere, "%s reads course field %s, which course-fields.yaml does "
                             "not declare" % (ident, m.group(1)))
            elif field["visibility"] != COURSE_VISIBLE_TO_ALL:
                problems.add(cwhere, "%s exists in report builder only if the field is "
                             "visible to everyone" % ident)
            if isinstance(heading, str) and "aims at" not in heading.lower():
                problems.add(cwhere, "a course-level heading names the target as what the "
                             "course aims at (FR-010)")
        out.append({"column": ident, "heading": heading, "aggregation": aggregation})
    return out


def _column_type(ident):
    """(known, type) for a column: type is a key of AGGREGATIONS_BY_TYPE, or None when the
    column allows no aggregation."""
    if ident in COLUMN_TYPES:
        return True, COLUMN_TYPES[ident]
    for pattern, ctype in COLUMN_TYPE_PATTERNS:
        if pattern.match(ident):
            return True, ctype
    return False, None


def _check_column_aggregation(where, ident, aggregation, problems):
    """contracts/declaration.md: every column's aggregation is one that column allows."""
    known, ctype = _column_type(ident)
    if not known:
        problems.add(where, "%s's type is not recorded, so validate cannot tell whether it "
                     "allows %s; add it to COLUMN_TYPES from its entity's source"
                     % (ident, aggregation))
    elif ctype is None:
        problems.add(where, "%s allows no aggregation (it disables them all)" % ident)
    elif aggregation not in AGGREGATIONS_BY_TYPE[ctype]:
        problems.add(where, "%s is a %s column, which allows %s, not %s"
                     % (ident, ctype, ", ".join(sorted(AGGREGATIONS_BY_TYPE[ctype])),
                        aggregation))


def _check_conditions(where, conditions, per, role_names, problems):
    if not isinstance(conditions, list):
        problems.add(where, "conditions must be a list")
        return []
    out, seen = [], set()
    for i, cond in enumerate(conditions):
        cwhere = "%s conditions[%d]" % (where, i)
        if not _check_keys(cwhere, cond, {"condition", "values"}, set(), problems):
            continue
        ident = cond.get("condition")
        if not (isinstance(ident, str) and REPORT_IDENTIFIER.match(ident)):
            problems.add(cwhere, "condition %r is not an entity:name identifier" % (ident,))
            continue
        if ident in seen:
            problems.add(cwhere, "%s is listed twice" % ident)
            continue
        seen.add(ident)
        values = cond.get("values")
        if not _check_keys(cwhere + " values", values, {"operator", "value"}, set(), problems):
            continue
        operator, value = values.get("operator"), values.get("value")
        if operator not in CONDITION_OPERATORS:
            problems.add(cwhere, "operator %r is not one of %s"
                         % (operator, ", ".join(CONDITION_OPERATORS)))
        if not (isinstance(value, str) or _is_int(value)):
            problems.add(cwhere, "value must be a string or an integer")
            continue
        # A select condition whose value is not among its options is silently skipped
        # (MDL-84213), which widens the report, so the value is checked here too.
        if ident == "role:name" and value not in role_names:
            problems.add(cwhere, "role %r is neither core nor in roles.yaml" % (value,))
        elif ident == "enrol:plugin" and value not in STANDARD["enrol"]:
            problems.add(cwhere, "enrol plugin %r is not a core enrolment method" % (value,))
        elif ident == ORG_FIELD_CONDITION:
            problems.add(cwhere, "%s is never a condition: report builder drops a profile "
                         "field's condition once the field is hidden, and the report widens; "
                         "scope by the organisation's cohort, %s (spec 016 R11)"
                         % (ident, SCOPE_CONDITIONS[0][0]))
        out.append({"condition": ident, "values": {"operator": operator, "value": value}})
    if per is not None:
        declared = {c["condition"]: c["values"] for c in out}
        for ident, values in SCOPE_CONDITIONS:
            if declared.get(ident) != values:
                problems.add(where, "a per: organisation report is scoped by %s = %s, "
                             "verbatim; without it a manager sees other organisations' "
                             "learners (FR-005)" % (ident, values["value"]))
    return out


def _check_sorting(where, sorting, columns, problems):
    if not isinstance(sorting, list):
        problems.add(where, "sorting must be a list of {column, direction}")
        return []
    out, seen = [], set()
    for i, entry in enumerate(sorting):
        swhere = "%s sorting[%d]" % (where, i)
        if not _check_keys(swhere, entry, {"column", "direction"}, set(), problems):
            continue
        ident, direction = entry.get("column"), entry.get("direction")
        if ident not in columns:
            problems.add(swhere, "%r is not one of the report's columns" % (ident,))
            continue
        if ident in seen:
            problems.add(swhere, "%s is sorted twice" % ident)
            continue
        seen.add(ident)
        if direction not in SORT_DIRECTIONS:
            problems.add(swhere, "direction must be asc or desc")
            continue
        out.append({"column": ident, "direction": direction})
    return out


def _check_audiences(where, audiences, per, role_names, cohorts, problems):
    if not isinstance(audiences, list):
        problems.add(where, "audiences must be a list")
        return []
    out = []
    for i, audience in enumerate(audiences):
        awhere = "%s audiences[%d]" % (where, i)
        kind = audience.get("type") if isinstance(audience, dict) else None
        if kind == "allusers":
            problems.add(awhere, "no report has an allusers audience: it would show every "
                         "learner to every account (FR-005)")
            continue
        if kind not in AUDIENCE_KEYS:
            problems.add(awhere, "type must be one of %s" % ", ".join(AUDIENCE_KEYS))
            continue
        field = AUDIENCE_KEYS[kind]
        if not _check_keys(awhere, audience, {"type", field}, set(), problems):
            continue
        value = audience.get(field)
        if not _text(value):
            problems.add(awhere, "%s must be text" % field)
            continue
        if kind == "systemrole" and value not in role_names:
            problems.add(awhere, "role %r is neither core nor in roles.yaml" % value)
        elif kind == "cohortmember" and per is None and value not in cohorts:
            problems.add(awhere, "cohort %r is not a cohort organisations.yaml declares"
                         % value)
        entry = {"type": kind, field: value}
        if entry in out:
            problems.add(awhere, "listed twice")
            continue
        out.append(entry)
    if per is not None and out != [MANAGERS_AUDIENCE]:
        problems.add(where, "a per: organisation report has exactly one audience, cohortmember "
                     "%s (FR-005)" % MANAGERS_AUDIENCE["cohort"])
    return out


def _check_schedule(where, schedule, problems):
    swhere = where + " schedule"
    if not _check_keys(swhere, schedule, SCHEDULE_KEYS, set(), problems):
        return None
    ok = True
    recurrence = schedule.get("recurrence")
    if not isinstance(recurrence, str) or recurrence not in RECURRENCE:
        problems.add(swhere, "recurrence must be one of %s" % ", ".join(RECURRENCE))
        ok = False
    fmt = schedule.get("format")
    if not isinstance(fmt, str) or fmt not in STANDARD["dataformat"]:
        problems.add(swhere, "format must be a core dataformat: %s"
                     % ", ".join(sorted(STANDARD["dataformat"])))
        ok = False
    viewas = schedule.get("viewas")
    if not isinstance(viewas, str) or viewas not in VIEWAS:
        problems.add(swhere, "viewas must be recipient, so no one receives more than they "
                     "could open (R12)")
        ok = False
    empty = schedule.get("send_when_empty")
    if not (_is_int(empty) and empty in SEND_WHEN_EMPTY):
        problems.add(swhere, "send_when_empty must be 0: nothing is sent when there are no "
                     "rows")
        ok = False
    start = schedule.get("start")
    if not (isinstance(start, str) and SCHEDULE_START.match(start)):
        problems.add(swhere, "start must be a weekday and a 24-hour time in site time, e.g. "
                     "\"monday 07:00\"")
        ok = False
    subject = schedule.get("subject")
    ok &= _check_name(swhere, "subject", subject, REPORT_TEXT_MAX, problems)
    message = schedule.get("message")
    if not _text(message):
        problems.add(swhere, "message must be text")
        ok = False
    if not ok:
        return None
    return {"name": subject, "recurrence": RECURRENCE[recurrence], "format": fmt,
            "userviewas": VIEWAS[viewas], "start": start,
            "configdata": {"subject": subject,
                           "message": {"text": message, "format": FORMAT_HTML},
                           "reportempty": SEND_WHEN_EMPTY[empty]}}


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


def _declared_org_keys(site_dir):
    """The organisation keys organisations.yaml declares, read leniently.

    Only for load_org_courses() called on its own, by the publisher: validate() checks the
    file itself and passes the keys it accepted.
    """
    path = site_dir / "organisations.yaml"
    if not path.exists():
        return set()
    data = _load(path, Problems())
    orgs = data.get("organisations") if isinstance(data, dict) else None
    return {o["key"] for o in orgs or [] if isinstance(o, dict)
            and isinstance(o.get("key"), str) and KEY.match(o["key"])}


def load_org_courses(site_dir=None, modules_dir=None, org_keys=None):
    """Read moodle/site/org-courses.yaml (spec 002 R11, contracts/declaration.md).

    Returns (courses, Problems): `courses` is a list of {slug, organisation, why}, one per
    course that only its host organisation's people may join. Every other course is
    shared, which is also what an absent file or an empty list means.

    This is the only reader of the file. scripts/moodle_payload.py calls it for each
    course's `placement`, and validate() for the drift payload's `org_courses`, so the
    publisher and the drift check cannot disagree.

    Rules (the retired course-discussions.yaml's, plus one): rows cite
    moodle/REQUIREMENTS.md; each slug is a course under modules/, compared with
    course_stage.branch_slug(); no slug twice; `why` is required; and `organisation` is a
    key declared in organisations.yaml. An invalid entry is left out of `courses`.
    """
    site_dir = pathlib.Path(site_dir) if site_dir is not None else SITE_DIR
    modules_dir = pathlib.Path(modules_dir) if modules_dir is not None else MODULES
    problems = Problems()
    path = site_dir / ORG_COURSES_FILE
    if not path.exists():
        return [], problems
    where = _rel(path)
    before = len(problems.items)
    data = _load(path, problems)
    if data is None:
        if len(problems.items) == before:
            problems.add(where, "empty; write `rows: [8, 15]` and `org_only: []`")
        return [], problems
    required, optional = TOP_FILES[ORG_COURSES_FILE]
    if not _check_keys(where, data, required, optional, problems):
        return [], problems
    _check_hosts(where, data, problems)
    _check_rows(where, data, _requirement_rows(), problems)

    entries = data.get("org_only")
    if entries is None:
        entries = []                     # `org_only:` with nothing under it: none
    if not isinstance(entries, list):
        problems.add(where, "org_only must be a list of {slug, organisation, why}")
        return [], problems
    if org_keys is None:
        org_keys = _declared_org_keys(site_dir)

    courses = _course_slugs(modules_dir)
    out, seen = [], set()
    for i, entry in enumerate(entries):
        ewhere = "%s org_only[%d]" % (where, i)
        if not _check_keys(ewhere, entry, {"slug", "organisation", "why"}, set(), problems):
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
            problems.add(ewhere, "%s is declared twice; a course has at most one host "
                         "organisation" % slug)
            continue
        seen.add(slug)
        org = entry.get("organisation")
        if org not in org_keys:
            problems.add(ewhere, "%s: organisation %r is not a key in organisations.yaml"
                         % (slug, org))
            continue
        if not _text(entry.get("why")):
            problems.add(ewhere, "%s: why must record the approval (\"approved by the "
                         "maintainer, issue #N\"), never the organisation's circumstances"
                         % slug)
            continue
        out.append({"slug": slug, "organisation": org, "why": entry["why"]})
    return out, problems


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
# Spec 013: the badge template and the certificate template (specs/013-certificates-badges/
# data-model.md). Every text passes cbc_wording.check_recognition(), rendered against every
# course in modules/, because a course title is free text and reaches the badge.

MODULES = REPO / "modules"
CERT_DIR = "certificate"
CERT_FILE = "template.yaml"
PLACEHOLDER = re.compile(r"\{([^{}]*)\}")
BADGE_PLACEHOLDERS = ("course", "competencies", "target_level", "programme")
CERT_PLACEHOLDERS = ("programme",)      # the rest of a certificate is elements, not text
PROGRAMME_SETTING = "badges_defaultissuername"
BADGE_SETTINGS = ("enablebadges", "badges_allowcoursebadges", "badges_allowexternalbackpack",
                  PROGRAMME_SETTING, "badges_defaultissuercontact",
                  "customcert/verifyallcertificates")
BADGE_SALT = "badges_badgesalt"         # per site; changing it breaks every issued badge (R9)
RECOGNITION_PLUGINS = ("mod_customcert", "availability_coursecompleted")
BADGE_NAME_MAX = 1333                   # badge.name is a char(1333) (lib/db/install.xml)
BADGE_IMAGE_MAX = 256 * 1024            # the badge form's limit, which only the form enforces (R2)
BADGE_IMAGE_MIN = 512                   # process_new_icon() writes a 512px size, f3
CERT_IMAGES_MAX = 100 * 1024            # SC-005: the PDF downloads in 30 s at 256 kbit/s
CERT_ELEMENTS = {                       # type -> (required keys, optional keys)
    "text": ({"text", "x", "y"}, {"size", "align", "width"}),
    "studentname": ({"x", "y"}, {"size", "align", "width"}),
    "coursename": ({"x", "y"}, {"size", "align", "width"}),
    "date": ({"date", "format", "x", "y"}, {"size", "align", "width"}),
    "code": ({"x", "y"}, {"size", "align", "width"}),
    "qrcode": ({"x", "y", "width"}, {"height"}),
    "image": ({"file", "x", "y", "width"}, {"height"}),
    "bgimage": ({"file"}, set()),
}
CERT_ONE_EACH = ("studentname", "coursename", "date", "code")   # FR-003
CERT_NAME_MAX = 255                     # customcert_templates.name and customcert.name
DATE_ITEMS = {"completion": -2}         # element_date DATE_COMPLETION; never the issue date (R6)
DATE_FORMAT = re.compile(r"^(?:[1-5]|strftime[a-z]+)$")   # element_helper::get_date_format_string
ALIGN = ("L", "C", "R")
FONTS = ("freesans", "freeserif", "dejavusans")   # embedded Unicode TCPDF families (R11)


def _courses():
    """(title, competencies, target) for every course in modules/, from README frontmatter."""
    out = []
    for readme in sorted(MODULES.glob("*/README.md")):
        text = readme.read_text(encoding="utf-8", errors="replace")
        m = re.match(r"^---\s*\n(.*?)\n---", text, re.S)
        try:
            meta = yaml.safe_load(m.group(1)) if m else {}
        except yaml.YAMLError:
            meta = {}
        meta = meta if isinstance(meta, dict) else {}
        names = meta.get("competencies") or []
        out.append((str(meta.get("title") or readme.parent.name),
                    ", ".join(str(n) for n in names) if isinstance(names, list) else "",
                    str(meta.get("target_outcome_level") or "")))
    return out


def _png_size(path):
    """(width, height) of a PNG, or None if the file is not one."""
    head = path.read_bytes()[:24]
    if head[:8] != b"\x89PNG\r\n\x1a\n" or head[12:16] != b"IHDR":
        return None
    return int.from_bytes(head[16:20], "big"), int.from_bytes(head[20:24], "big")


def _image_file(where, base, rel, problems):
    """A declared image path, inside its folder and present; or None."""
    if not isinstance(rel, str) or not rel or pathlib.PurePosixPath(rel).is_absolute():
        problems.add(where, "image %r must be a path relative to %s" % (rel, _rel(base)))
        return None
    path = (base / rel).resolve()
    if base.resolve() not in path.parents:
        problems.add(where, "image %s is outside %s" % (rel, _rel(base)))
        return None
    if not path.is_file():
        problems.add(where, "image %s does not exist" % rel)
        return None
    return path


def _image_record(path):
    data = path.read_bytes()
    return {"filename": path.name, "sha256": hashlib.sha256(data).hexdigest(),
            "path": str(path)}


def _check_placeholders(where, label, text, allowed, problems):
    for name in PLACEHOLDER.findall(text):
        if name not in allowed:
            problems.add(where, "%s uses {%s}; the placeholders are %s" % (
                label, name, ", ".join("{%s}" % a for a in allowed)))
    for m in re.finditer(r"\{target_level\}", text):
        if not text[:m.start()].rstrip().lower().endswith(cbc_wording.AIM_PHRASE):
            problems.add(where, "%s uses {target_level} other than straight after \"%s\" "
                         "(FR-005)" % (label, cbc_wording.AIM_PHRASE))


def _render(text, values):
    return PLACEHOLDER.sub(lambda m: values.get(m.group(1), m.group(0)), text)


def _programme(decl):
    for s in decl["settings"]:
        if s["name"] == PROGRAMME_SETTING and not s["env"] and isinstance(s["value"], str):
            return s["value"]
    return ""


def _validate_badges(where, data, rows, site_dir, decl, problems):
    """Check badges.yaml. Returns the badge template for the payload, or None."""
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    if not _text(data.get("why")):
        problems.add(where, "why must say which row or spec needs it")
    ok = True
    texts = {}
    for key in ("name", "description", "imagecaption", "message_subject", "message"):
        value = data.get(key)
        if not _text(value):
            problems.add(where, "%s must be text" % key)
            ok = False
            continue
        if EMAIL.search(value):
            problems.add(where, "%s holds an email address; nothing here names a person "
                         "(constitution III)" % key)
        _check_placeholders(where, key, value, BADGE_PLACEHOLDERS, problems)
        texts[key] = value.strip()
    for key in ("version", "language"):
        if not _text(data.get(key)):
            problems.add(where, "%s must be text, in quotes" % key)
            ok = False
    if ok and "{course}" not in texts["name"]:
        problems.add(where, "name must contain {course}, so each course's badge names it")
        ok = False
    if ok and "%badgelink%" not in texts["message"]:
        problems.add(where, "message must contain %badgelink%, so the learner can find the badge")
        ok = False

    image = _image_file(where, site_dir, data.get("image"), problems)
    if image is not None:
        size = _png_size(image)
        if size is None:
            problems.add(where, "image %s is not a PNG" % data["image"])
            image = None
        else:
            if size[0] != size[1] or size[0] < BADGE_IMAGE_MIN:
                problems.add(where, "image %s is %dx%d; it must be square and at least %dpx "
                             "(R2)" % (data["image"], size[0], size[1], BADGE_IMAGE_MIN))
            if image.stat().st_size > BADGE_IMAGE_MAX:
                problems.add(where, "image %s is %d KB; at most %d KB (R2)" % (
                    data["image"], image.stat().st_size // 1024, BADGE_IMAGE_MAX // 1024))
    if not ok or image is None:
        return None

    # {programme} is the declared setting, filled in here; the rest the server fills per
    # course (R16). Rendered against every course, so a title cannot slip "certified" past.
    programme = _programme(decl)
    texts = {k: _render(v, {"programme": programme}) for k, v in texts.items()}
    before = len(problems.items)
    labels = cbc_wording.cbc_labels()
    uses_target = any("{target_level}" in v for v in texts.values())
    for title, competencies, target in _courses() or [("A course", "", "")]:
        if uses_target and target and target not in labels:
            problems.add(where, "course %r has target_outcome_level %r, which is not a CBC "
                         "label; the badge would print it (FR-005)" % (title, target))
        values = {"course": title, "competencies": competencies, "target_level": target}
        for key, value in texts.items():
            rendered = _render(value, values)
            for message in cbc_wording.check_recognition(rendered,
                                                         require_completed=(key == "name")):
                problems.add("%s %s (course %r)" % (where, key, title), message)
            if key == "name" and len(rendered) > BADGE_NAME_MAX:
                problems.add(where, "name for course %r is %d characters; badge.name holds %d"
                             % (title, len(rendered), BADGE_NAME_MAX))
        if len(problems.items) > before:
            break   # one course's failures say it; the template is what to fix
    if len(problems.items) > before:
        return None
    out = dict(texts, version=str(data["version"]), language=str(data["language"]),
               image=_image_record(image))
    return out


def _validate_certificate(where, data, rows, site_dir, decl, problems):
    """Check certificate/template.yaml. Returns the template for the payload, or None."""
    if not _check_keys(where, data, {"rows", "name", "activity_name", "intro", "font", "pages",
                                     "why"}, set(), problems):
        return None
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    if not _text(data.get("why")):
        problems.add(where, "why must say which row or spec needs it")
    before = len(problems.items)
    base = site_dir / CERT_DIR
    programme = _programme(decl)
    for key in ("name", "activity_name", "intro"):
        if _check_name(where, key, data.get(key), CERT_NAME_MAX, problems):
            _check_placeholders(where, key, data[key], (), problems)
            for message in cbc_wording.check_recognition(data[key]):
                problems.add("%s %s" % (where, key), message)
    if data.get("font") not in FONTS:
        problems.add(where, "font must be one of %s, which embed and cover any script (R11)"
                     % ", ".join(FONTS))
    pages = data.get("pages")
    if not isinstance(pages, list) or not pages:
        problems.add(where, "pages must be a list of at least one page")
        return None

    counts = {t: 0 for t in CERT_ONE_EACH}
    texts, images, out_pages = [], [], []
    for p, page in enumerate(pages):
        pwhere = "%s pages[%d]" % (where, p)
        if not _check_keys(pwhere, page, {"width", "height", "elements"}, {"margins"},
                           problems):
            continue
        out_page = {"elements": []}
        for key in ("width", "height"):
            if not (_is_int(page.get(key)) and page[key] > 0):
                problems.add(pwhere, "%s must be a whole number of millimetres" % key)
            out_page[key] = page.get(key)
        margins = page.get("margins") or {}
        if not isinstance(margins, dict) or set(margins) - {"left", "right"} or \
                not all(_is_int(v) and v >= 0 for v in margins.values()):
            problems.add(pwhere, "margins is {left, right}, whole millimetres")
            margins = {}
        out_page["leftmargin"] = margins.get("left", 0)
        out_page["rightmargin"] = margins.get("right", 0)
        elements = page.get("elements")
        if not isinstance(elements, list):
            problems.add(pwhere, "elements must be a list")
            continue
        for e, element in enumerate(elements):
            ewhere = "%s elements[%d]" % (pwhere, e)
            kind = element.get("type") if isinstance(element, dict) else None
            if kind not in CERT_ELEMENTS:
                problems.add(ewhere, "type must be one of %s" % ", ".join(CERT_ELEMENTS))
                continue
            required, optional = CERT_ELEMENTS[kind]
            if not _check_keys(ewhere, element, required | {"type"}, optional, problems):
                continue
            for key in ("x", "y", "size", "width", "height"):
                if key in element and not (_is_int(element[key]) and element[key] >= 0):
                    problems.add(ewhere, "%s must be a whole number" % key)
            if "align" in element and element["align"] not in ALIGN:
                problems.add(ewhere, "align is one of %s" % ", ".join(ALIGN))
            out = {k: element[k] for k in element if k not in ("date", "format", "file")}
            if kind in counts:
                counts[kind] += 1
            if kind == "text":
                text = element["text"]
                if not _text(text):
                    problems.add(ewhere, "text must be text")
                    continue
                _check_placeholders(ewhere, "text", text, CERT_PLACEHOLDERS, problems)
                out["text"] = _render(text, {"programme": programme})
                texts.append(out["text"])
            elif kind == "date":
                if element["date"] not in DATE_ITEMS:
                    problems.add(ewhere, "date must be completion: the issue date is the "
                                 "first download, not when the course was completed (R6)")
                else:
                    out["dateitem"] = DATE_ITEMS[element["date"]]
                if not (isinstance(element["format"], (str, int)) and
                        DATE_FORMAT.match(str(element["format"]))):
                    problems.add(ewhere, "format is 1-5 or a langconfig key such as "
                                 "strftimedate (element_helper::get_date_format_string)")
                out["dateformat"] = str(element["format"])
            elif kind in ("image", "bgimage"):
                path = _image_file(ewhere, base, element["file"], problems)
                if path is not None:
                    if _png_size(path) is None and path.read_bytes()[:3] != b"\xff\xd8\xff":
                        problems.add(ewhere, "%s is neither a PNG nor a JPEG" % element["file"])
                    out["image"] = _image_record(path)
                    images.append(path)
            out_page["elements"].append(out)
        out_pages.append(out_page)

    for kind, n in counts.items():
        if n != 1:
            problems.add(where, "needs exactly one %s element, not %d (FR-003)" % (kind, n))
    for text in texts:
        for message in cbc_wording.check_recognition(text):
            problems.add("%s text" % where, message)
    if not any(cbc_wording.COMPLETED.search(t) for t in texts):
        problems.add(where, "no text element says \"training completed\" or \"completed the "
                     "course\" (FR-004)")
    total = sum(p.stat().st_size for p in set(images))
    if total > CERT_IMAGES_MAX:
        problems.add(where, "images total %d KB; at most %d KB, so the PDF downloads on a slow "
                     "link (SC-005)" % (total // 1024, CERT_IMAGES_MAX // 1024))
    if len(problems.items) > before:
        return None
    return {"name": data["name"], "activity_name": data["activity_name"],
            "intro": data["intro"], "font": data["font"], "pages": out_pages}


def _check_recognition_site(decl, problems):
    """What badges and the certificate need from the rest of the declaration."""
    pinned = {p["component"] for p in decl["plugins"] if "version" in p}
    for component in RECOGNITION_PLUGINS:
        if component not in pinned:
            problems.add("site.yaml", "%s must be pinned: the certificate needs it (row 23)"
                         % component)
    declared = {s["name"]: s for s in decl["settings"]}
    for name in BADGE_SETTINGS:
        if name not in declared:
            problems.add("settings/badges.yaml", "%s must be declared (spec 013 R12)" % name)
    if BADGE_SALT in declared:
        problems.add(declared[BADGE_SALT]["file"], "%s is per site, and changing it breaks "
                     "every badge already issued; list it in ignore.yaml (R9)" % BADGE_SALT)
    programme = declared.get(PROGRAMME_SETTING)
    if programme and isinstance(programme["value"], str):
        for message in cbc_wording.check_recognition(programme["value"]):
            problems.add(programme["file"], message)


# --- spec 011: office hours, the dashboard and the calendar ------------------------------
# specs/011-events-calendar/data-model.md "Declared (repo)" and contracts/declaration.md.

OFFICEHOURS_COURSE = "ltct:officehours"
OFFICEHOURS_SCHEDULER = "ltct:officehours:scheduler"
SCHEDULER = "mod_scheduler"
SCHEDULER_MODES = ("onetime", "oneonly")
COURSE_FULLNAME_MAX = 254                  # course.fullname
COURSE_SHORTNAME_MAX = 100                 # course.shortname
GROUP_NAME_PLACEHOLDER = "{n}"
# A group's name is shown to its members; a mentor's name may be a protected identity
# (spec 016), so a template may hold no name of anyone.
GROUP_NAME_FORBIDDEN = ("{name}", "{firstname}", "{lastname}", "{fullname}")
# Boost's mydashboard layout has one block region, side-pre (theme/boost/config.php,
# MOODLE_502_STABLE), and my/index.php adds content. side-post is not a region there:
# apply on ltuse.net was refused with "unknown block region side-post" (2026-10-05).
DASHBOARD_REGIONS = ("side-pre", "content")
CALENDAR_SETTINGS = ("enablecalendarexport", "calendar_customexport", "calendar_adminseesall",
                     "timezone", "forcetimezone")
NO_FORCED_TIMEZONE = "99"                  # forcetimezone: each learner's own zone wins (R5)
# The IANA areas PHP's DateTimeZone::listIdentifiers() returns, for when Python has no tz
# database (Windows without tzdata). CI's runner has one, so there the full list is used.
TZ_AREAS = ("Africa", "America", "Antarctica", "Arctic", "Asia", "Atlantic", "Australia",
            "Europe", "Indian", "Pacific")
STUDENT_BOOKINGS = "mod/scheduler:seeotherstudentsbooking"


def _valid_timezone(zone):
    """Whether `zone` is a zone identifier Moodle's time zone menu offers."""
    if not isinstance(zone, str):
        return False
    if zone == "UTC":
        return True
    try:
        import zoneinfo
        zones = zoneinfo.available_timezones()
    except ImportError:
        zones = set()
    if zones:
        return zone in zones
    area, _, rest = zone.partition("/")
    return area in TZ_AREAS and bool(re.match(r"^[A-Za-z0-9_+\-]+(?:/[A-Za-z0-9_+\-]+)*$", rest))


def _int_in(where, label, value, low, high, problems):
    if not (_is_int(value) and low <= value <= high):
        problems.add(where, "%s must be a whole number from %d to %d" % (label, low, high))
        return False
    return True


def _exactly(where, label, value, expected, why, problems):
    if not (_is_int(value) and value == expected):
        problems.add(where, "%s must be %d: %s" % (label, expected, why))


def _validate_office_hours(where, data, rows, orgs, problems):
    """Check office-hours.yaml. Returns the payload's `officehours`, or None if it is broken."""
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    if not _text(data.get("why")):
        problems.add(where, "why must say which row or spec needs it")
    course, scheduler, groups = data.get("course"), data.get("scheduler"), data.get("groups")
    ok = _check_keys(where + " course", course,
                     {"idnumber", "fullname", "shortname", "category", "summary", "groupmode",
                      "groupmodeforce"}, set(), problems)
    ok = _check_keys(where + " scheduler", scheduler,
                     {"idnumber", "name", "intro", "groupmode", "maxbookings", "schedulermode",
                      "guardtime_hours", "allownotifications", "defaultslotduration",
                      "usebookingform", "grade"}, set(), problems) and ok
    ok = _check_keys(where + " groups", groups, {"name_template"}, set(), problems) and ok
    if not ok:
        return None

    cwhere = where + " course"
    if course["idnumber"] != OFFICEHOURS_COURSE:
        problems.add(cwhere, "idnumber must be %s; local_ltuse finds the course by it"
                     % OFFICEHOURS_COURSE)
    for key, limit in (("fullname", COURSE_FULLNAME_MAX), ("shortname", COURSE_SHORTNAME_MAX)):
        if _check_name(cwhere, key, course[key], limit, problems):
            for message in cbc_wording.check_recognition(course[key]):
                problems.add(cwhere, message)
    if not _text(course["summary"]):
        problems.add(cwhere, "summary must say, in plain words, what the course is for")
    else:
        for message in cbc_wording.check_recognition(course["summary"]):
            problems.add(cwhere, message)
    keys = {c["key"] for c in orgs["categories"]} if orgs else set()
    if course["category"] not in keys:
        problems.add(cwhere, "category %r must be a key in organisations.yaml categories"
                     % (course["category"],))
    _exactly(cwhere, "groupmode", course["groupmode"], 1,
             "separate groups, so a learner sees only their own mentor's slots (R16)", problems)
    _exactly(cwhere, "groupmodeforce", course["groupmodeforce"], 1,
             "forced, so the scheduler is never switched to open slots by mistake", problems)

    swhere = where + " scheduler"
    if scheduler["idnumber"] != OFFICEHOURS_SCHEDULER:
        problems.add(swhere, "idnumber must be %s" % OFFICEHOURS_SCHEDULER)
    if _check_name(swhere, "name", scheduler["name"], COURSE_FULLNAME_MAX, problems):
        for message in cbc_wording.check_recognition(scheduler["name"]):
            problems.add(swhere, message)
    if not _text(scheduler["intro"]):
        problems.add(swhere, "intro must tell a learner what to do on the page")
    _exactly(swhere, "groupmode", scheduler["groupmode"], 1,
             "the scheduler filters slots by group only in a group mode (R16)", problems)
    _int_in(swhere, "maxbookings", scheduler["maxbookings"], 1, 5, problems)
    if scheduler["schedulermode"] not in SCHEDULER_MODES:
        problems.add(swhere, "schedulermode is %s" % " or ".join(SCHEDULER_MODES))
    _int_in(swhere, "guardtime_hours", scheduler["guardtime_hours"], 0, 168, problems)
    _exactly(swhere, "allownotifications", scheduler["allownotifications"], 0,
             "local_ltuse sends every booking message, so the scheduler must send none (R20)",
             problems)
    _int_in(swhere, "defaultslotduration", scheduler["defaultslotduration"], 5, 240, problems)
    _exactly(swhere, "usebookingform", scheduler["usebookingform"], 0,
             "office hours ask nothing of a learner before booking", problems)
    _exactly(swhere, "grade", scheduler["grade"], 0,
             "office hours are never graded (constitution V)", problems)

    template = groups["name_template"]
    gwhere = where + " groups"
    if not _text(template) or GROUP_NAME_PLACEHOLDER not in template:
        problems.add(gwhere, "name_template must contain %s, the group's number"
                     % GROUP_NAME_PLACEHOLDER)
    elif any(p in template for p in GROUP_NAME_FORBIDDEN):
        problems.add(gwhere, "name_template must not name anyone: a mentor's name may be a "
                     "protected identity (spec 016)")
    elif len(template.replace(GROUP_NAME_PLACEHOLDER, "9" * 10)) > CATEGORY_NAME_MAX:
        problems.add(gwhere, "name_template is too long for a group name")

    guardtime = scheduler["guardtime_hours"] * 3600 if _is_int(scheduler["guardtime_hours"]) else 0
    return {
        "course": {"idnumber": course["idnumber"], "fullname": course["fullname"],
                   "shortname": course["shortname"],
                   "category_idnumber": "ltct:" + str(course["category"]),
                   "summary": course["summary"], "groupmode": course["groupmode"],
                   "groupmodeforce": course["groupmodeforce"]},
        "scheduler": {"idnumber": scheduler["idnumber"], "name": scheduler["name"],
                      "intro": scheduler["intro"], "groupmode": scheduler["groupmode"],
                      "maxbookings": scheduler["maxbookings"],
                      "schedulermode": scheduler["schedulermode"], "guardtime": guardtime,
                      "allownotifications": scheduler["allownotifications"],
                      "defaultslotduration": scheduler["defaultslotduration"],
                      "usebookingform": scheduler["usebookingform"],
                      "grade": scheduler["grade"]},
        "groups": {"name_template": template},
    }


def _check_office_hours_site(decl, problems):
    """What the office-hours course needs from the rest of the declaration."""
    pinned = {p["component"] for p in decl["plugins"] if "version" in p}
    if SCHEDULER not in pinned:
        problems.add("site.yaml", "%s must be pinned: office-hours.yaml needs it (row 21)"
                     % SCHEDULER)
    student = next((r for r in decl["roles"] if r["shortname"] == "student"), None)
    perm = (student or {}).get("capabilities", {}).get(STUDENT_BOOKINGS)
    if perm in (None, "allow"):
        problems.add("roles.yaml", "student must declare %s as inherit (or stricter): the "
                     "student archetype allows it, and it names everyone who booked a slot "
                     "(spec 011 D4, FR-008)" % STUDENT_BOOKINGS)


def _validate_dashboard(where, data, rows, problems):
    """Check dashboard.yaml. Returns the payload's `dashboard`: [{block, region}]."""
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    blocks = data.get("default_blocks")
    if not isinstance(blocks, list) or not blocks:
        problems.add(where, "default_blocks must be a non-empty list")
        return []
    out, seen = [], set()
    for i, entry in enumerate(blocks):
        bwhere = "%s default_blocks[%d]" % (where, i)
        if not _check_keys(bwhere, entry, {"block", "region", "why"}, set(), problems):
            continue
        block, region = entry["block"], entry["region"]
        if block not in STANDARD["block"]:
            problems.add(bwhere, "block %r is not a core block" % (block,))
            continue
        if block in seen:
            problems.add(bwhere, "%s names no block twice; it is listed already" % block)
            continue
        seen.add(block)
        if region not in DASHBOARD_REGIONS:
            problems.add(bwhere, "region is one of %s" % ", ".join(DASHBOARD_REGIONS))
            continue
        if not _text(entry["why"]):
            problems.add(bwhere, "why must say what the block is for")
        out.append({"block": block, "region": region})
    return out


def _check_calendar_settings(decl, problems):
    """Spec 011's rules on settings declared anywhere: the time zone and calendar export."""
    declared = {s["name"]: s for s in decl["settings"]}
    present = [n for n in CALENDAR_SETTINGS if n in declared]
    if present:
        for name in CALENDAR_SETTINGS:
            if name not in declared:
                problems.add("settings/calendar.yaml", "%s must be declared with the other "
                             "calendar settings (spec 011 R12)" % name)
    zone = declared.get("timezone")
    if zone and not zone["env"] and not _valid_timezone(zone["value"]):
        problems.add(zone["file"], "timezone %r is not a time zone identifier, such as UTC or "
                     "Africa/Nairobi (D7)" % (zone["value"],))
    force = declared.get("forcetimezone")
    if force and str(force["value"]) != NO_FORCED_TIMEZONE:
        problems.add(force["file"], "forcetimezone must be 99, so each learner's own zone wins "
                     "(spec 011 R5)")
    hidden = declared.get("hiddenuserfields")
    if hidden and isinstance(hidden["value"], str) and \
            "timezone" in [f.strip() for f in hidden["value"].split(",")]:
        problems.add(hidden["file"], "hiddenuserfields must not hide timezone: the profile is "
                     "where a learner sees and changes their zone (spec 011 R14)")


# ---------------------------------------------------------------------------------------
# The payload: the declaration as the JSON site_config.php reads (data-model "Rendered
# payload"). Built in memory; the resolved form is only ever written to the child's stdin.

# --- spec 016: identity protection -------------------------------------------------------
# specs/016-identity-protection/data-model.md "Declared (repo)" and contracts/declaration.md.

PROTECTION_LEVELS = ["none", "email", "firstname", "pseudonym"]
RECONCILE_MINUTES = 60                     # db/tasks.php runs reconcile_protection hourly
ALTNAME_FIELDS = ("firstnamephonetic", "lastnamephonetic", "middlename", "alternatename")
CORE_WITHHOLD = ("country", "city", "url", "institution", "department", "phone1", "phone2",
                 "address", "idnumber")
SPECIAL_WITHHOLD = ("maildisplay", "picture", "firstname", "lastname")
NEVER_WITHHELD = (ORG_FIELD, "description", "interests")
NEUTRAL_SURNAME = re.compile(r"^[^\w\s]$", re.UNICODE)   # one non-letter character (R4)
# The site-wide settings spec 016 requires, with the value each must have: only those that
# cost nobody anything (Doug, 2026-10-05 (scope review), change 20). protectusernames,
# registerauth and authpreventaccountcreation are general account rules in spec 008's admin.yaml,
# and the login methods are its site.yaml plugin entries.
PROTECTION_SETTINGS = {
    "allowedemaildomains": "",                                # R8, core default
    "enablegravatar": 0,                                      # R6, core default
    "forceloginforprofileimage": 1,                           # R6
}


def _withhold_allowed(decl):
    """The fields a level may withhold: the fixed set, plus our declared profile fields."""
    ours = {f["shortname"] for f in decl["profile_fields"]} - set(NEVER_WITHHELD)
    return set(SPECIAL_WITHHOLD) | set(ALTNAME_FIELDS) | set(CORE_WITHHOLD) | ours


def _validate_protection(where, data, rows, decl, problems):
    """Check protection.yaml. Returns the payload's protection object, or None."""
    _check_hosts(where, data, problems)
    _check_rows(where, data, rows, problems)
    if not _text(data.get("why")):
        problems.add(where, "why must say which row or spec needs it")
    before = len(problems.items)
    for path, value in _walk_strings(data, ()):
        if "@" in value:
            problems.add(where, "%s holds an @; nothing here names a person or an address "
                         "(constitution III)" % (".".join(path) or "value"))
    if data.get("levels") != PROTECTION_LEVELS:
        problems.add(where, "levels must be exactly %s, in that order (FR-001)"
                     % ", ".join(PROTECTION_LEVELS))
    withhold = data.get("withhold")
    allowed = _withhold_allowed(decl)
    ours = sorted(f["shortname"] for f in decl["profile_fields"]
                  if f["shortname"] not in NEVER_WITHHELD)
    out_withhold = {}
    if not _check_keys(where + " withhold", withhold, set(PROTECTION_LEVELS[1:]), set(),
                       problems):
        withhold = {}
    previous = []
    for level in PROTECTION_LEVELS[1:]:
        fields = withhold.get(level)
        lwhere = "%s withhold.%s" % (where, level)
        if not isinstance(fields, list) or not all(isinstance(f, str) for f in fields):
            problems.add(lwhere, "must be a list of field names")
            continue
        if len(set(fields)) != len(fields):
            problems.add(lwhere, "names a field twice")
        for field in fields:
            if field in NEVER_WITHHELD:
                problems.add(lwhere, "%s is never withheld: %s" % (field, {
                    ORG_FIELD: "blanking it drops the learner from their cohort and courses (R11)",
                }.get(field, "the learner's own words are theirs (R6)")))
            elif field not in allowed:
                problems.add(lwhere, "%s is not a field the plugin can withhold" % field)
        missing = [f for f in previous if f not in fields]
        if missing:
            problems.add(lwhere, "each level includes the one before it; missing %s"
                         % ", ".join(missing))
        previous = list(fields)
        out_withhold[level] = list(fields)
    email = out_withhold.get("email", [])
    if email and "maildisplay" not in email:
        problems.add(where + " withhold.email", "must withhold maildisplay (FR-001)")
    first = out_withhold.get("firstname", [])
    if first:
        need = ["lastname", "picture"] + list(ALTNAME_FIELDS) + list(CORE_WITHHOLD) + ours
        lacking = [f for f in need if f not in first]
        if lacking:
            problems.add(where + " withhold.firstname", "must also withhold %s (R6)"
                         % ", ".join(lacking))
        if "firstname" in first:
            problems.add(where + " withhold.firstname", "keeps the first name; only "
                         "pseudonym withholds it")
    pseudo = out_withhold.get("pseudonym", [])
    if pseudo and "firstname" not in pseudo:
        problems.add(where + " withhold.pseudonym", "must withhold firstname: the pseudonym "
                     "replaces it")
    neutral = data.get("neutral_surname")
    if not (isinstance(neutral, str) and NEUTRAL_SURNAME.match(neutral)):
        problems.add(where, "neutral_surname is one non-letter character, such as \"\u00b7\": "
                     "names are not locked, so a protected learner's own profile form must "
                     "save, and core requires a surname there (R4)")
    if data.get("reconcile_minutes") != RECONCILE_MINUTES:
        problems.add(where, "reconcile_minutes is %d: db/tasks.php runs reconcile_protection "
                     "hourly" % RECONCILE_MINUTES)
    if len(problems.items) > before:
        return None
    return {"levels": list(PROTECTION_LEVELS), "withhold": out_withhold,
            "neutral_surname": neutral,
            "reconcile_minutes": RECONCILE_MINUTES}


def _check_protection_site(decl, problems):
    """What protection needs from roles, settings, profile fields and reports."""
    roles = {r["shortname"]: r for r in decl["roles"]}
    for short, role in roles.items():
        caps = role.get("capabilities", {})
        if caps.get(VIEWIDENTITY) == "allow" and short not in PROTECTION_VIEW_ROLES:
            problems.add("roles.yaml %s" % short, "%s is held only by %s (spec 016 R7)"
                         % (VIEWIDENTITY, ", ".join(sorted(PROTECTION_VIEW_ROLES))))
        for cap in PROTECTION_MANAGE_CAPS:
            if caps.get(cap) == "allow" and short not in PROTECTION_MANAGE_ROLES:
                problems.add("roles.yaml %s" % short, "%s is the site team's only (spec 016 "
                             "R12)" % cap)
        for cap in REPORT_EDIT_CAPS:
            if caps.get(cap) == "allow" and short != "manager":
                problems.add("roles.yaml %s" % short, "%s stays with manager: a report someone "
                             "else builds could show any learner's email (spec 016 R8)" % cap)
    for short in ("manager", "teacher"):
        if roles.get(short, {}).get("capabilities", {}).get(VIEWIDENTITY) != "allow":
            problems.add("roles.yaml %s" % short, "must allow %s (spec 016 R7)"
                         % VIEWIDENTITY)
    if MENTOR in roles and roles[MENTOR].get("capabilities", {}).get(VIEWIDENTITY) != "allow":
        problems.add("roles.yaml %s" % MENTOR, "must allow %s: a mentor sees their "
                     "learner's real identity (FR-006)" % VIEWIDENTITY)
    for short in COURSE_LEADER_ROLES:
        caps = roles.get(short, {}).get("capabilities", {})
        for cap in COURSE_LEADER_PROHIBIT:
            if caps.get(cap) != "prohibit":
                problems.add("roles.yaml %s" % short, "must prohibit %s (spec 016 R14)" % cap)

    declared = {s["name"]: s for s in decl["settings"]}
    for name, want in PROTECTION_SETTINGS.items():
        setting = declared.get(name)
        if setting is None:
            problems.add("settings", "%s must be declared as %r (spec 016)" % (name, want))
        elif str(setting["value"]) != str(want):
            problems.add(setting["file"], "%s must be %r (spec 016)" % (name, want))


def _literal(value):
    if isinstance(value, list):
        return [str(v) for v in value]
    return str(value)


def _payload_image(record):
    data = pathlib.Path(record["path"]).read_bytes()
    return {"filename": record["filename"], "sha256": record["sha256"],
            "content": base64.b64encode(data).decode("ascii")}


def _payload_badge(template):
    if template is None:
        return None
    out = {k: v for k, v in template.items() if k != "image"}
    out["image"] = _payload_image(template["image"])
    out["deny"] = [{"pattern": p, "why": why} for p, why in cbc_wording.DENY_PATTERNS]
    return out


def _payload_certificate(template):
    if template is None:
        return None
    pages = []
    for page in template["pages"]:
        elements = []
        for element in page["elements"]:
            element = dict(element)
            if "image" in element:
                element["image"] = _payload_image(element["image"])
            elements.append(element)
        pages.append(dict(page, elements=elements))
    return dict(template, pages=pages)


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
        # Spec 002 R11: the organisation-only courses, for placement drift. The `why` stays
        # in the repo; the server needs only where each course belongs.
        "org_courses": decl["org_courses"],
        # Spec 004, in application order; reports last (data-model "Rendered payload
        # additions"). Every {org} is already expanded.
        "course_field_category": decl["course_field_category"],
        "course_fields": decl["course_fields"],
        "competencies": decl["competencies"],
        # Spec 006, after competencies and before reports, so a role pathway is applied
        # against the competency rows this run has just set (contracts/declaration.md).
        "levels": decl["levels"],
        "role_pathways": decl["role_pathways"],
        "reports": decl["reports"],
        # Spec 013, after reports (contracts/declaration.md "Payload arrays"). Images travel
        # as base64, and the badge template carries cbc_wording's deny patterns, so the
        # plugin re-checks each course's rendered text without a copy of its own (R15).
        "badge_template": _payload_badge(decl["badge_template"]),
        "certificate_template": _payload_certificate(decl["certificate_template"]),
        # Spec 011, after spec 013's (contracts/declaration.md "Payload arrays"): the
        # office-hours course and activity, then the default dashboard's blocks.
        "officehours": decl["officehours"],
        "dashboard": decl["dashboard"],
        # Spec 016, last: the levels and what each withholds. Never who is protected.
        "protection": decl["protection"],
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
            "%d organisation-only courses, %d course fields, %d competencies, %d reports, "
            "%d badge template, %d certificate template, %d office-hours course, "
            "%d dashboard blocks, %d protection levels"
            % (len(decl["settings"]), files, len(decl["plugins"]), len(decl["roles"]),
               len(decl["ignore"]), len(decl["categories"]), len(decl["cohorts"]),
               len(decl["profile_fields"]), len(decl["cohort_rules"]),
               len(decl["org_courses"]), len(decl["course_fields"]), len(decl["competencies"]), len(decl["reports"]),
               decl["badge_template"] is not None, decl["certificate_template"] is not None,
               decl["officehours"] is not None, len(decl["dashboard"]),
               len(decl["protection"]["levels"]) if decl["protection"] else 0))


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
