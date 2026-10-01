#!/usr/bin/env python3
"""Make the lighter copy of a committed image that goes into a publish payload.

A consultant opening a lesson on a slow, metered connection pays for every byte of every
screenshot. The committed files are full-size captures, and their weight is colour depth,
not dimensions: most are already under 1000 px wide, but 24-bit or RGBA. So the payload
carries a reduced copy -- fitted to a maximum width and saved as a palette PNG -- and the
committed file is only ever READ (FR-002). Nothing here writes to the repo.

Platform-neutral, like scripts/moodle_payload.py: it knows images and nothing of Moodle.
Any delivery target can call deliver().

THE AUTHOR'S OVERRIDE is a suffix in the file name, so it travels with the file through
renames, copies and review, and no list has to be kept in step with it:

    ss-03-dense-dialog.png          standard: fit to 1280 px, 256 colours
    ss-03-dense-dialog.full.png     full:     the committed bytes, unchanged
    ss-03-dense-dialog.small.png    small:    fit to 800 px, 64 colours

The delivered file keeps the committed name, suffix and all, so every link, alt text and
disclosure rule applies to it unchanged.

Pillow is imported lazily, inside deliver(). scripts/check_course_package.py imports
treatment_for() on machines that never build a payload, and must not need Pillow for it.
"""
import io
import pathlib
from typing import NamedTuple, Optional

# Changing a value here changes every delivered image, which costs one re-upload of each
# and changes what learners see -- so it is a reviewed change, not a tweak. The evidence
# for these values (67% lighter across the committed screenshots, every label legible)
# is specs/009-low-bandwidth-delivery/research.md R1.
PROFILES = {
    "standard": {"max_width": 1280, "colours": 256},
    "small": {"max_width": 800, "colours": 64},
}

SUFFIXES = ("full", "small")
RASTER_EXTS = (".png", ".jpg", ".jpeg")
VECTOR_EXTS = (".svg",)

# JPEG is tolerated, not expected (the package rules want PNG or SVG). A palette makes no
# sense for a photo, and writing PNG bytes under a .jpg name would mislabel the file, so a
# JPEG is fitted to width and re-encoded as JPEG.
JPEG_QUALITY = 85


class ImageError(Exception):
    """A committed raster that cannot be decoded. The build stops; nothing is written."""

    def __init__(self, path, reason):
        self.path = path
        self.reason = reason
        super().__init__("%s: %s" % (path, reason))


class Delivered(NamedTuple):
    data: bytes
    treatment: str          # standard, small, full, unchanged, vector, passthrough
    note: Optional[str]


def _split(name):
    """('ss-01-x', 'full', '.png') for 'ss-01-x.full.png'; suffix '' when there is none."""
    path = pathlib.PurePosixPath(name)
    ext = path.suffix
    base = name[:-len(ext)] if ext else name
    stem, _, suffix = base.partition(".")
    return stem, suffix, ext


def treatment_for(name):
    """'standard', 'small' or 'full' from the file name; None for an unrecognised suffix.

    The stem may hold at most one '.', and what follows it must be exactly 'full' or
    'small'. So 'x.ful.png' and 'x.full.small.png' are both None: a typo that silently
    meant "standard" would defeat the point of asking for '.full'.
    """
    _, suffix, _ = _split(name)
    if not suffix:
        return "standard"
    return suffix if suffix in SUFFIXES else None


def deliver(path, suffixes=True):
    """The bytes to deliver for one committed file, and why. Reads `path`, writes nothing.

    `suffixes=False` ignores any '.full'/'.small' in the name. The suffix is part of the
    `assets/` naming convention (CLAUDE.md rule 6); a legacy folder such as a backfilled
    course's `images/` predates it and has names like `L2-1.Blank-BT-Row.png`, whose dot
    means nothing, so those get the standard reduction and no note.

    In order:
      1. SVG -> 'vector', any other non-raster -> 'passthrough': the committed bytes.
      2. '.full' -> 'full': the committed bytes.
      3. Reduce with the profile. If that is not strictly smaller than the committed file
         -> 'unchanged': the committed bytes (FR-003). Never deliver something heavier.
      4. An undecodable raster raises ImageError.

    An unrecognised suffix is reduced with 'standard', and says so in `note`, so a legacy
    course the package check never saw still publishes.
    """
    path = pathlib.Path(path)
    committed = path.read_bytes()
    ext = path.suffix.lower()
    if ext in VECTOR_EXTS:
        return Delivered(committed, "vector", None)
    if ext not in RASTER_EXTS:
        return Delivered(committed, "passthrough", None)

    treatment = treatment_for(path.name) if suffixes else "standard"
    note = None
    if treatment is None:
        note = ("%s: unrecognised delivery suffix '.%s' -- delivered with the standard "
                "reduction" % (path.name, _split(path.name)[1]))
        treatment = "standard"
    if treatment == "full":
        return Delivered(committed, "full", None)

    data = _reduce(path, committed, PROFILES[treatment], jpeg=ext != ".png")
    if len(data) >= len(committed):
        return Delivered(committed, "unchanged", note)
    return Delivered(data, treatment, note)


def _reduce(path, committed, profile, jpeg):
    from PIL import Image

    try:
        im = Image.open(io.BytesIO(committed))
        im.load()
    except (OSError, ValueError, SyntaxError, Image.DecompressionBombError) as e:
        raise ImageError(path, str(e) or type(e).__name__) from None

    alpha = _has_alpha(im)
    im = im.convert("RGBA" if alpha else "RGB")

    # Fit to width, keeping the aspect ratio. Never enlarge: a 400 px image stays 400 px.
    width, height = im.size
    if width > profile["max_width"]:
        new_h = max(1, round(height * profile["max_width"] / width))
        im = im.resize((profile["max_width"], new_h), Image.Resampling.LANCZOS)

    buf = io.BytesIO()
    if jpeg:
        im.convert("RGB").save(buf, format="JPEG", quality=JPEG_QUALITY, optimize=True)
        return buf.getvalue()

    # Median cut for opaque images; fast octree is the quantiser that keeps an alpha
    # channel. No dithering: Pillow's quantize() does not dither unless it is mapping onto
    # a fixed palette, and the measurements and legibility review in research.md R1 were
    # made on this undithered output. Dithering was tried and costs ~6 points of the
    # saving (64% -> 59% on the committed screenshots) by speckling flat UI backgrounds.
    method = Image.Quantize.FASTOCTREE if alpha else Image.Quantize.MEDIANCUT
    out = im.quantize(colors=profile["colours"], method=method)

    # No text, time, ICC or EXIF chunks: only pixels. That keeps the output identical for
    # identical input (FR-006) and carries nothing about whoever captured the shot.
    keep = {k: out.info[k] for k in ("transparency",) if k in out.info}
    out.info = {}
    out.save(buf, format="PNG", optimize=True, **keep)
    return buf.getvalue()


def _has_alpha(im):
    """True only for transparency that is actually used.

    Many screenshots are saved RGBA with every pixel opaque. Treating those as opaque lets
    them use the better median-cut palette and dithering.
    """
    if im.mode in ("RGBA", "LA", "PA"):
        return im.getchannel("A").getextrema()[0] < 255
    if "transparency" in im.info:
        return im.convert("RGBA").getchannel("A").getextrema()[0] < 255
    return False
