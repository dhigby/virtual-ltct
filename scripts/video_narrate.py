#!/usr/bin/env python3
"""Make one narration file per scene with ElevenLabs -- the second step of a course video.

Reads `scenes.json` (written by video_scenes.py) from the course's working folder, outside
every git tree, and writes `audio/<scene id>.mp3` beside it plus `narration.json`: each
scene's audio length, which the Motion film is timed from and video_assemble.py lays out.

A scene is only re-voiced when its text, voice or model changed (or with --force), so a
one-line script edit costs one line of credits. Each request passes the neighbouring
scenes' text, so the joined narration flows as one read rather than nine.

A scene flagged `needs_human` (possible language data) is never sent: a person records
it and saves it as audio/<scene id>.mp3 themselves (CLAUDE.md, "Who an LTC is").

The key comes from ELEVENLABS_API_KEY and is never printed or written: the repo is public.

Usage:
  python scripts/video_narrate.py --slug <slug> --list-voices
  python scripts/video_narrate.py --slug <slug> --voice <voice id> [--only s03,s05] [--force]
"""
import argparse
import hashlib
import json
import os
import pathlib
import subprocess
import sys

import requests

import admin_files
import course_stage
import video_scenes

API = "https://api.elevenlabs.io"
DEFAULT_MODEL = "eleven_multilingual_v2"
OUTPUT_FORMAT = "mp3_44100_128"


def api_key():
    key = os.environ.get("ELEVENLABS_API_KEY", "").strip()
    if not key:
        sys.exit("ELEVENLABS_API_KEY is not set. In PowerShell:\n"
                 "  [Environment]::SetEnvironmentVariable('ELEVENLABS_API_KEY', '<key>', 'User')\n"
                 "then restart VS Code.")
    return key


def call(method, path, key, **kw):
    r = requests.request(method, API + path, headers={"xi-api-key": key}, timeout=120, **kw)
    if r.status_code != 200:
        detail = r.text[:300].replace(key, "<key>")
        sys.exit("ElevenLabs %s %s failed (%d): %s" % (method, path, r.status_code, detail))
    return r


def list_voices(key):
    voices = call("GET", "/v1/voices", key).json().get("voices", [])
    for v in sorted(voices, key=lambda v: (v.get("category") or "", v.get("name") or "")):
        labels = ", ".join("%s" % x for x in (v.get("labels") or {}).values() if x)
        print("%-24s %-22s %-12s %s" % (v.get("voice_id"), (v.get("name") or "")[:22],
                                        v.get("category") or "", labels))


def seconds(path):
    out = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration",
                          "-of", "default=nw=1:nk=1", str(path)],
                         capture_output=True, text=True, check=True)
    return round(float(out.stdout.strip()), 2)


def fingerprint(text, voice, model):
    return hashlib.sha256(("%s\0%s\0%s" % (voice, model, text)).encode("utf-8")).hexdigest()


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    ap.add_argument("--slug", required=True)
    ap.add_argument("--voice", help="ElevenLabs voice id (see --list-voices)")
    ap.add_argument("--model", default=DEFAULT_MODEL)
    ap.add_argument("--only", help="comma-separated scene ids, e.g. s03,s05")
    ap.add_argument("--force", action="store_true", help="re-voice even if unchanged")
    ap.add_argument("--list-voices", action="store_true")
    ap.add_argument("--out", help="working folder (default ~/ltct-video/<slug>/)")
    args = ap.parse_args()

    key = api_key()
    if args.list_voices:
        list_voices(key)
        return 0
    if not args.voice:
        sys.exit("Give --voice <voice id>; --list-voices shows them.")

    slug = course_stage.resolve(args.slug)
    if not slug:
        sys.exit("No course matches %r." % args.slug)
    try:
        work = admin_files.guard_path(args.out or video_scenes.default_out(slug))
    except admin_files.PathRefused:
        sys.exit("%s is inside a git tree, and audio never goes in git." % args.out)
    scenes_file = work / "scenes.json"
    if not scenes_file.is_file():
        sys.exit("No %s -- run video_scenes.py first." % scenes_file)
    doc = json.loads(scenes_file.read_text(encoding="utf-8"))
    script = course_stage.MODULES / slug / doc["script"]
    if hashlib.sha256(script.read_bytes()).hexdigest() != doc["script_sha256"]:
        sys.exit("%s changed since scenes.json was made -- re-run video_scenes.py first."
                 % doc["script"])

    scenes = doc["scenes"]
    only = set(args.only.split(",")) if args.only else None
    narration_file = work / "narration.json"
    narration = (json.loads(narration_file.read_text(encoding="utf-8"))
                 if narration_file.is_file() else {})
    audio = work / "audio"
    audio.mkdir(exist_ok=True)

    chars = 0
    for i, s in enumerate(scenes):
        sid, text = s["id"], s["voiceover"]
        target = audio / (sid + ".mp3")
        if s["needs_human"]:
            if target.is_file():
                narration[sid] = {"file": target.name, "seconds": seconds(target),
                                  "voice": "human"}
            print("%s  skipped: needs a human (%s)%s" % (
                sid, "; ".join(s["needs_human"]),
                "" if target.is_file() else " -- save their recording as " + str(target)))
            continue
        if only and sid not in only:
            continue
        fp = fingerprint(text, args.voice, args.model)
        if not args.force and target.is_file() and narration.get(sid, {}).get("fingerprint") == fp:
            print("%s  unchanged" % sid)
            continue
        body = {"text": text, "model_id": args.model}
        if i > 0:
            body["previous_text"] = scenes[i - 1]["voiceover"]
        if i + 1 < len(scenes):
            body["next_text"] = scenes[i + 1]["voiceover"]
        r = call("POST", "/v1/text-to-speech/%s" % args.voice, key,
                 params={"output_format": OUTPUT_FORMAT}, json=body)
        target.write_bytes(r.content)
        chars += len(text)
        narration[sid] = {"file": target.name, "seconds": seconds(target), "voice": args.voice,
                          "model": args.model, "fingerprint": fp,
                          "request_id": r.headers.get("request-id")}
        print("%s  %5.1fs  voiced" % (sid, narration[sid]["seconds"]))

    narration_file.write_text(json.dumps(narration, indent=2) + "\n", encoding="utf-8")
    missing = [s["id"] for s in scenes if s["id"] not in narration]
    total = sum(narration[s["id"]]["seconds"] for s in scenes if s["id"] in narration)
    print("\n%d characters sent. Narration so far: %d:%02d across %d of %d scenes -> %s"
          % (chars, total // 60, total % 60, len(scenes) - len(missing), len(scenes), audio))
    if missing:
        print("Still without audio: %s" % ", ".join(missing))
    return 1 if missing else 0


if __name__ == "__main__":
    sys.exit(main())
