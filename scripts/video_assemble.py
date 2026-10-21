#!/usr/bin/env python3
"""Lay the narration onto the exported film and write captions -- the last step of a course video.

Reads, from the course's working folder (outside every git tree):
  scenes.json       the scenes and their spoken text (video_scenes.py)
  narration.json    each scene's audio file and length (video_narrate.py)
  film-timing.json  each scene's length in the Motion film: {"lead": 0.3, "scenes": [{"id", "dur"}]}
  <film>.mp4        the film, exported from its Motion page (silent)
and writes <slug>.mp4 (film + narration) and <slug>.srt (captions) beside them, plus the
video's recipe into the course folder (video_scenes.recipe_path), the one output that is
committed. On a machine with no film-timing.json, the recipe's timing is used.

Scene i's audio starts `lead` seconds after scene i starts in the film, so sync holds as
long as film-timing.json matches the film's scene lengths. Captions split each scene's
text at sentence ends, timed in proportion to length, so no caption runs past two lines.
Captions matter beyond accessibility: INTENT.md says video is never the only route to
the content, and a learner on poor bandwidth may read rather than stream.

Usage:
  python scripts/video_assemble.py --slug <slug> --film "<exported>.mp4" [--link vimeo=<url>]
"""
import argparse
import json
import re
import subprocess
import sys

import admin_files
import course_stage
import video_scenes

CAPTION_CHARS = 84          # about two lines of a video caption


def srt_time(s):
    ms = int(round(s * 1000))
    return "%02d:%02d:%02d,%03d" % (ms // 3600000, ms // 60000 % 60, ms // 1000 % 60, ms % 1000)


def chunks(text):
    """Caption-sized pieces: sentences, and long sentences at commas."""
    out = []
    for sentence in re.split(r"(?<=[.?!])\s+", text):
        piece = ""
        for part in re.split(r"(?<=,)\s+", sentence):
            if piece and len(piece) + 1 + len(part) > CAPTION_CHARS:
                out.append(piece)
                piece = part
            else:
                piece = (piece + " " + part).strip()
        if piece:
            out.append(piece)
    # A piece still too long (no comma to split at): halve it at the nearest word gap.
    fitted = []
    for p in out:
        while len(p) > CAPTION_CHARS:
            cut = p.rfind(" ", 0, len(p) // 2 + 10)
            if cut <= 0:
                break
            fitted.append(p[:cut])
            p = p[cut + 1:]
        fitted.append(p)
    return fitted


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    ap.add_argument("--slug", required=True)
    ap.add_argument("--film", required=True, help="the MP4 exported from the Motion page")
    ap.add_argument("--out", help="working folder (default: ltct-video/<slug>/ beside the repo)")
    ap.add_argument("--link", action="append", metavar="NAME=URL",
                    help="record a link in the recipe, e.g. vimeo=https://vimeo.com/123")
    args = ap.parse_args()

    slug = course_stage.resolve(args.slug)
    if not slug:
        sys.exit("No course matches %r." % args.slug)
    try:
        work = admin_files.guard_path(args.out or video_scenes.default_out(slug))
        film = admin_files.guard_path(args.film)
    except admin_files.PathRefused:
        sys.exit("Video files stay outside every git tree; move the film out of the repo.")
    if not film.is_file():
        sys.exit("No film at %s." % film)

    doc = json.loads((work / "scenes.json").read_text(encoding="utf-8"))
    scenes = doc["scenes"]
    narration = json.loads((work / "narration.json").read_text(encoding="utf-8"))
    recipe = video_scenes.load_recipe(slug, doc["script"])
    timing_file = work / "film-timing.json"
    if timing_file.is_file():
        timing = json.loads(timing_file.read_text(encoding="utf-8"))
    elif recipe.get("scenes"):
        timing = recipe          # a fresh machine: the committed recipe has the film's timing
    else:
        sys.exit("No film-timing.json in %s and no recipe in the course folder." % work)
    lead = timing.get("lead", 0.3)
    durs = {s["id"]: s["dur"] for s in timing["scenes"]}
    if [s["id"] for s in scenes] != [s["id"] for s in timing["scenes"]]:
        sys.exit("film-timing.json and scenes.json list different scenes; rebuild the timing.")
    missing = [s["id"] for s in scenes if s["id"] not in narration]
    if missing:
        sys.exit("No narration yet for %s; run video_narrate.py." % ", ".join(missing))

    inputs, filters, captions, start = ["-i", str(film)], [], [], 0.0
    for i, s in enumerate(scenes, 1):
        n = narration[s["id"]]
        if n["seconds"] > durs[s["id"]] - lead:
            print("warning: %s narration (%.1fs) runs past its scene (%.1fs)"
                  % (s["id"], n["seconds"], durs[s["id"]]))
        begin = start + lead
        inputs += ["-i", str(work / "audio" / n["file"])]
        filters.append("[%d:a]adelay=%d:all=1[a%d]" % (i, round(begin * 1000), i))
        parts = chunks(s["voiceover"])
        total = sum(len(p) for p in parts)
        t = begin
        for p in parts:
            span = n["seconds"] * len(p) / total
            captions.append((t, t + span, p))
            t += span
        start += durs[s["id"]]
    mix = "".join("[a%d]" % i for i in range(1, len(scenes) + 1))
    filters.append("%samix=inputs=%d:normalize=0:duration=longest[voice]" % (mix, len(scenes)))

    base = course_stage.branch_slug(slug)
    out_mp4, out_srt = work / (base + ".mp4"), work / (base + ".srt")
    cmd = ["ffmpeg", "-y", "-loglevel", "error", *inputs,
           "-filter_complex", ";".join(filters),
           "-map", "0:v", "-map", "[voice]", "-c:v", "copy", "-c:a", "aac", "-b:a", "128k",
           "-t", "%.3f" % start, str(out_mp4)]
    subprocess.run(cmd, check=True)
    out_srt.write_text("".join("%d\n%s --> %s\n%s\n\n" % (k, srt_time(a), srt_time(b), p)
                               for k, (a, b, p) in enumerate(captions, 1)), encoding="utf-8")
    print("Film length %d:%02d. Wrote:\n  %s\n  %s" % (start // 60, start % 60, out_mp4, out_srt))

    # The recipe: everything but the media, committed with the course so the video can be
    # rebuilt or fixed from git. Links (Motion films, Vimeo) carry over from the timing
    # file, the old recipe and --link, newest winning.
    links = dict(recipe.get("links", {}))
    links.update({k: v for k, v in timing.items() if k.startswith("film") and isinstance(v, str)})
    links.update(timing.get("links", {}))
    for pair in args.link or []:
        name, _, url = pair.partition("=")
        links[name.strip()] = url.strip()
    voiced = [n for n in narration.values() if n.get("voice") not in (None, "human")]
    new = {
        "about": "How this course video was made: process/video-production.md. "
                 "The audio and video are not in git; the finished video is at links.vimeo.",
        "script": doc["script"],
        "script_sha256": doc["script_sha256"],
        "voice": voiced[0]["voice"] if voiced else recipe.get("voice"),
        "model": voiced[0].get("model") if voiced else recipe.get("model"),
        "lead": lead,
        "scenes": [{"id": s["id"], "dur": durs[s["id"]],
                    "narration_seconds": narration[s["id"]]["seconds"],
                    "voice": "human" if narration[s["id"]].get("voice") == "human" else "tts"}
                   for s in scenes],
        "length_seconds": round(start, 2),
        "links": links,
    }
    path = video_scenes.recipe_path(slug, doc["script"])
    path.write_text(json.dumps(new, indent=2) + "\n", encoding="utf-8")
    print("  %s  (commit this with the course)" % path.relative_to(course_stage.REPO))
    return 0


if __name__ == "__main__":
    sys.exit(main())
