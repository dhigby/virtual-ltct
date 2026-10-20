#!/usr/bin/env python3
"""Split a course's video script into scenes -- the first step of producing its video.

A video script (NN-video-script.md, written by the video-script-writer agent) pairs what
is on screen with what is said. Each pairing is one scene: the cold open, each row of the
on-screen/voiceover tables, and the close. This writes them to `scenes.json`, which the
narration step reads (one audio file per scene) and the Motion film is timed from.

The working folder is OUTSIDE every git tree (default %USERPROFILE%\\ltct-video\\<slug>\\):
narration and video are large binaries, which never go in git (INTENT.md), and GitDoc
would push anything left in the repo. The path guard is admin_files.guard_path.

Text-to-speech must never voice local-language data (CLAUDE.md, "Who an LTC is"). A
scene whose voiceover holds non-ASCII letters or a code span is flagged `needs_human`,
so a person decides how it is said before any audio is made.

Usage:
  python scripts/video_scenes.py --slug <slug> [--script 10-video-script.md] [--out DIR]
"""
import argparse
import datetime
import hashlib
import json
import pathlib
import re
import sys
import unicodedata

import admin_files
import course_stage

WORDS_PER_MINUTE = 150      # a steady narration pace, used only for the estimate
NOTES_HEADING = re.compile(r"^##\s+notes for the presenter", re.IGNORECASE)
CUE = re.compile(r"^\*\*(On-screen|Voiceover):\*\*\s*(.*)$")
ASSET = re.compile(r"assets/[A-Za-z0-9._-]+\.(?:png|svg|jpe?g|gif|webp)")
SEPARATOR_ROW = re.compile(r"^\|[\s:|-]+\|$")


def default_out(slug):
    return pathlib.Path.home() / "ltct-video" / course_stage.branch_slug(slug)


def pick_script(folder, name):
    """The named script, or the course's only overview script."""
    if name:
        path = folder / name
        if not path.is_file():
            sys.exit("No %s in %s." % (name, folder))
        return path
    overviews = sorted(p for p in folder.glob("[0-9][0-9]-video-script.md"))
    if len(overviews) != 1:
        found = ", ".join(p.name for p in overviews) or "none"
        sys.exit("Say which script with --script (overview scripts found: %s)." % found)
    return overviews[0]


def cells(row):
    """Cells of a markdown table row, splitting on pipes that are not escaped."""
    parts = re.split(r"(?<!\\)\|", row.strip().strip("|"))
    return [p.strip().replace("\\|", "|") for p in parts]


def clean(text):
    """Spoken text from markdown: no emphasis or code marks, no wrapping quotes."""
    text = text.replace("**", "").replace("`", "")
    text = re.sub(r"\s+", " ", text).strip()
    if len(text) >= 2 and text[0] in "\"“" and text[-1] in "\"”":
        text = text[1:-1].strip()
    return text


def flags_for(raw):
    flags = []
    if "`" in raw:
        flags.append("code span in voiceover -- confirm how it is said")
    if any(ord(c) > 127 and unicodedata.category(c).startswith("L") for c in raw):
        flags.append("non-ASCII letters -- possible language data; a human says it, not TTS")
    return flags


def parse(script):
    """Return scenes in film order, as dicts."""
    scenes, section, segment, on_screen, para = [], "", "", "", None

    def add(screen, voice):
        if not clean(voice):
            return
        scenes.append({"section": section, "segment": segment,
                       "on_screen": clean(screen), "raw_voiceover": voice.strip()})

    def close_para():
        nonlocal on_screen, para
        if para is None:
            return
        kind, text = para
        if kind == "On-screen":
            on_screen = text
        else:
            add(on_screen, text)
            on_screen = ""
        para = None

    for line in script.read_text(encoding="utf-8").splitlines():
        stripped = line.strip()
        if NOTES_HEADING.match(stripped):
            break
        if stripped.startswith("## "):
            close_para()
            section, segment, on_screen = stripped[3:].strip(), "", ""
            continue
        if stripped.startswith("### "):
            close_para()
            segment = stripped[4:].strip()
            continue
        cue = CUE.match(stripped)
        if cue:
            close_para()
            para = (cue.group(1), cue.group(2))
            continue
        if stripped.startswith("|"):
            close_para()
            if SEPARATOR_ROW.match(stripped):
                continue
            row = cells(stripped)
            if len(row) < 2 or row[0].lower().startswith("on-screen"):
                continue
            add(row[0], row[-1])
            continue
        if not stripped:
            close_para()
        elif para is not None:
            para = (para[0], para[1] + " " + stripped)
    close_para()
    return scenes


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    ap.add_argument("--slug", required=True)
    ap.add_argument("--script", help="script filename in the course folder")
    ap.add_argument("--out", help="working folder (default ~/ltct-video/<slug>/)")
    args = ap.parse_args()

    slug = course_stage.resolve(args.slug)
    if not slug:
        sys.exit("No course matches %r." % args.slug)
    folder = course_stage.MODULES / slug
    script = pick_script(folder, args.script)
    try:
        out = admin_files.guard_path(args.out or default_out(slug))
    except admin_files.PathRefused:
        sys.exit("%s is inside a git tree, and audio and video never go in git. Use a folder "
                 "outside every repo, such as %s." % (args.out, default_out(slug)))

    scenes = parse(script)
    if not scenes:
        sys.exit("No scenes found in %s." % script.name)

    problems = 0
    for n, s in enumerate(scenes, 1):
        raw = s.pop("raw_voiceover")
        s["id"] = "s%02d" % n
        s["voiceover"] = clean(raw)
        s["words"] = len(s["voiceover"].split())
        s["est_seconds"] = round(s["words"] * 60 / WORDS_PER_MINUTE, 1)
        s["assets"] = sorted(set(ASSET.findall(s["on_screen"])))
        s["missing_assets"] = [a for a in s["assets"] if not (folder / a).is_file()]
        s["needs_human"] = flags_for(raw)
        problems += len(s["missing_assets"]) + len(s["needs_human"])

    out.mkdir(parents=True, exist_ok=True)
    doc = {
        "slug": slug,
        "script": script.name,
        "script_sha256": hashlib.sha256(script.read_bytes()).hexdigest(),
        "generated": datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds"),
        "words_per_minute": WORDS_PER_MINUTE,
        "scenes": [{k: s[k] for k in ("id", "section", "segment", "on_screen", "voiceover",
                                      "words", "est_seconds", "assets", "missing_assets",
                                      "needs_human")} for s in scenes],
    }
    target = out / "scenes.json"
    target.write_text(json.dumps(doc, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    total = sum(s["est_seconds"] for s in scenes)
    for s in scenes:
        mark = "  !" if s["missing_assets"] or s["needs_human"] else ""
        print("%s %5.1fs %3d words  %s%s" % (s["id"], s["est_seconds"], s["words"],
                                            s["voiceover"][:60], mark))
        for a in s["missing_assets"]:
            print("      missing asset: %s" % a)
        for f in s["needs_human"]:
            print("      needs a human: %s" % f)
    print("\n%d scenes, about %d:%02d of narration -> %s"
          % (len(scenes), total // 60, total % 60, target))
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
