"""The CBC wording rules: the only definition of each (constitution V).

Two rules, kept as two named functions and never merged, because they disagree on purpose:

    report_label_problems()  spec 004 FR-010: a report label names a course's target only as an
                             aim, and never shows a learner at a level. It refuses anything
                             starting "certif", so it would refuse "certificate".
    check_recognition()      spec 013 FR-004/FR-005: badge and certificate text says "training
                             completed", never "certified", and never a CBC level held. It allows
                             "certificate": FR-003 names the document a certificate of training
                             completed.

site_config.py validate and check_moodle_payload.py import this module, like disclosure.py. A
second copy of either rule anywhere is a defect. The plugin re-checks rendered badge text against
DENY_PATTERNS, which site_config.py apply sends it, so the PHP side never holds its own copy.

Not run directly.
"""
import pathlib
import re

import yaml

REPO = pathlib.Path(__file__).resolve().parent.parent
OUTCOME_LEVELS = REPO / "outcome-levels.yaml"

# The retired vocabulary (CLAUDE.md). "Learner" and "Expert" are left out: both are ordinary
# words, and "Expert" is also CBC level 4.
RETIRED = re.compile(r"\b(?:advanced\s+beginner|practitioner|trainer|proficient)\b", re.I)

# --- spec 004 FR-010: report labels (moved from site_config.py, behaviour unchanged) ---------

AIM_CERTIF = re.compile(r"certif", re.I)
AIM_NEAR = 3                               # "within three words of level"
# Keys are word stems: a plural "learners" or "levels" matches as "learner" or "level".
AIM_NEAR_LEVEL = {"learner": "places a learner at a level",
                  "reached": "says a level was reached", "achieved": "says a level was reached",
                  "attained": "says a level was reached"}
AIM_STRICT = re.compile(r"reached|achieved|attained|\bcompetent\b", re.I)


def _aim_stem(word):
    """A word lowercased, with a plural s dropped: "Learners" -> "learner"."""
    word = word.lower()
    return word[:-1] if word.endswith("s") and word[:-1] in ("learner", "level") else word


def report_label_problems(label, strict=False):
    """A report label's problems under FR-010, as messages. strict=True is competency-coverage."""
    if not isinstance(label, str):
        return []
    out = []
    if AIM_CERTIF.search(label):
        out.append("%r mentions certification; a report shows no CBC result (FR-010)" % label)
    m = RETIRED.search(label)
    if m:
        out.append("%r uses the retired level name %r; CBC vocabulary only (FR-010)"
                   % (label, m.group(0)))
    words = [_aim_stem(w) for w in re.findall(r"[A-Za-z]+", label)]
    levels = [i for i, w in enumerate(words) if w == "level"]
    for i, w in enumerate(words):
        if w in AIM_NEAR_LEVEL and any(abs(i - j) <= AIM_NEAR for j in levels):
            out.append("%r %s; label a target level as what the course aims at (FR-010)"
                       % (label, AIM_NEAR_LEVEL[w]))
            break
    if strict:
        m = AIM_STRICT.search(label)
        if m:
            out.append("%r says %r; competency coverage counts completions, not competence "
                       "(FR-010)" % (label, m.group(0)))
    return out


# --- spec 013 FR-004/FR-005: badge and certificate text ---------------------------------------

AIM_PHRASE = "designed to support progress towards"
COMPLETED = re.compile(r"training\s+completed|completed\s+the\s+course", re.I)
HELD_NEAR = 3
HELD_WORDS = frozenset({"reached", "achieved", "attained", "awarded", "holds", "held"})

# Refused anywhere in badge or certificate text. Plain PCRE-compatible patterns, matched
# case-insensitively, because the plugin applies the same strings with preg_match().
DENY_PATTERNS = [
    (r"\bcertif(?:y|ied|ies|ication|ications)\b", "says certified; this is training completed"),
    (r"\bcertificate\s+of\s+competenc", "says certificate of competency"),
    (r"accredit", "says accredited"),
    (RETIRED.pattern, "uses a retired level name; CBC vocabulary only"),
    (r"\blevel\s*[0-4]\b", "names a level by number; a level appears only as its CBC label"),
]
_DENY = [(re.compile(p, re.I), why) for p, why in DENY_PATTERNS]


def cbc_labels(path=OUTCOME_LEVELS):
    """The CBC labels, verbatim from outcome-levels.yaml: "0 - No Competency" ... "4 - Expert"."""
    with open(path, encoding="utf-8") as fh:
        data = yaml.safe_load(fh)
    return [lv["label"] for lv in data["levels"]]


def _words(text):
    return [(m.group(0).lower(), m.start(), m.end()) for m in re.finditer(r"[A-Za-z0-9]+", text)]


def check_recognition(text, require_completed=False, labels=None):
    """Badge or certificate text's problems under FR-004/FR-005, as messages.

    require_completed=True is for the badge name and the certificate's main text, which must
    say "training completed" or "completed the course".
    """
    if not isinstance(text, str):
        return []
    labels = cbc_labels() if labels is None else labels
    out = []
    for rx, why in _DENY:
        m = rx.search(text)
        if m:
            out.append("%r %s (FR-004)" % (text, why))
    if require_completed and not COMPLETED.search(text):
        out.append("%r must say \"training completed\" or \"completed the course\" (FR-004)"
                   % text)

    # Where each level appears: the word "level", and every CBC label (FR-005).
    words = _words(text)
    spans = []
    for label in labels:
        for m in re.finditer(re.escape(label), text, re.I):
            if m.group(0) != label:
                out.append("%r writes %r; a level is its CBC label exactly, %r (FR-005)"
                           % (text, m.group(0), label))
            before = text[:m.start()].rstrip()
            if not before.lower().endswith(AIM_PHRASE):
                out.append("%r names %r other than after \"%s\"; a level describes the "
                           "course, never the learner (FR-005)" % (text, label, AIM_PHRASE))
            spans.append((m.start(), m.end()))
    level_at = [i for i, (w, s, e) in enumerate(words)
                if w in ("level", "levels") or any(a <= s and e <= b for a, b in spans)]
    for i, (w, _, _) in enumerate(words):
        if w in HELD_WORDS and any(abs(i - j) <= HELD_NEAR for j in level_at):
            out.append("%r says a level was %s; no badge or certificate states a CBC level "
                       "as held (FR-005)" % (text, w))
            break
    return out
