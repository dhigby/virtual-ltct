"""Unit tests for scripts/image_reduce.py (spec 009). Run: python -m pytest tests/

Every fixture is generated with Pillow into a temp dir: no test image is committed (SC-005).
"""
import io, pathlib, random, sys, tempfile, unittest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import image_reduce as ir
from PIL import Image


def noisy(path, size, mode="RGB", seed=1):
    """A screenshot-sized image with enough colour that reduction has work to do."""
    rnd = random.Random(seed)
    w, h = size
    bands = len(mode)
    data = bytes(rnd.randrange(256) for _ in range(w * h * bands))
    Image.frombytes(mode, size, data).save(path, format="PNG")
    return path


def flat(path, size=(16, 16)):
    Image.new("RGB", size, (255, 255, 255)).save(path, format="PNG", optimize=True)
    return path


class TreatmentFor(unittest.TestCase):
    def test_names(self):
        self.assertEqual(ir.treatment_for("ss-01-x.png"), "standard")
        self.assertEqual(ir.treatment_for("ss-01-x.full.png"), "full")
        self.assertEqual(ir.treatment_for("ss-01-x.small.png"), "small")
        self.assertEqual(ir.treatment_for("ss-01-x.svg"), "standard")
        self.assertIsNone(ir.treatment_for("ss-01-x.ful.png"))
        self.assertIsNone(ir.treatment_for("ss-01-x.full.small.png"))

    def test_does_not_need_pillow(self):
        # The package check imports treatment_for() on machines without Pillow.
        src = (REPO / "scripts" / "image_reduce.py").read_text(encoding="utf-8")
        top = src.split("\ndef ", 1)[0]
        self.assertNotIn("import PIL", top)
        self.assertNotIn("from PIL", top)


class Deliver(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.tmp = pathlib.Path(self._tmp.name)

    def tearDown(self):
        self._tmp.cleanup()

    def open(self, data):
        return Image.open(io.BytesIO(data))

    def test_standard_fits_to_1280_and_is_lighter(self):
        src = noisy(self.tmp / "ss-01-a.png", (1600, 900))
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "standard")
        self.assertEqual(self.open(d.data).size[0], 1280)
        self.assertLess(len(d.data), src.stat().st_size)
        self.assertIsNone(d.note)

    def test_small_fits_to_800(self):
        src = noisy(self.tmp / "ss-01-a.small.png", (1600, 900))
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "small")
        self.assertEqual(self.open(d.data).size, (800, 450))

    def test_never_enlarged(self):
        src = noisy(self.tmp / "ss-01-a.png", (400, 300))
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "standard")
        self.assertEqual(self.open(d.data).size, (400, 300))

    def test_deterministic(self):
        src = noisy(self.tmp / "ss-01-a.png", (900, 500))
        self.assertEqual(ir.deliver(src).data, ir.deliver(src).data)

    def test_already_small_is_unchanged(self):
        src = flat(self.tmp / "ss-01-a.png")
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "unchanged")
        self.assertEqual(d.data, src.read_bytes())

    def test_full_is_committed_bytes(self):
        src = noisy(self.tmp / "ss-01-a.full.png", (1600, 900))
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "full")
        self.assertEqual(d.data, src.read_bytes())

    def test_svg_is_vector(self):
        src = self.tmp / "ss-01-a.svg"
        src.write_text('<svg xmlns="http://www.w3.org/2000/svg"/>', encoding="utf-8")
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "vector")
        self.assertEqual(d.data, src.read_bytes())

    def test_other_extension_passes_through(self):
        src = self.tmp / "notes.txt"
        src.write_bytes(b"plain text")
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "passthrough")
        self.assertEqual(d.data, b"plain text")

    def test_alpha_survives(self):
        src = noisy(self.tmp / "ss-01-a.png", (600, 300), mode="RGBA")
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "standard")
        im = self.open(d.data)
        self.assertTrue(im.mode in ("RGBA", "LA") or "transparency" in im.info,
                        "alpha lost: mode %s" % im.mode)

    def test_undecodable_raises_naming_the_path(self):
        src = self.tmp / "ss-01-a.png"
        src.write_bytes(bytes(random.Random(3).randrange(256) for _ in range(2048)))
        with self.assertRaises(ir.ImageError) as cm:
            ir.deliver(src)
        self.assertEqual(cm.exception.path, src)
        self.assertIn("ss-01-a.png", str(cm.exception))

    def test_no_text_or_time_chunks(self):
        src = self.tmp / "ss-01-a.png"
        from PIL import PngImagePlugin
        info = PngImagePlugin.PngInfo()
        info.add_text("Comment", "captured by someone")
        rnd = random.Random(5)
        Image.frombytes("RGB", (700, 400),
                        bytes(rnd.randrange(256) for _ in range(700 * 400 * 3))
                        ).save(src, format="PNG", pnginfo=info)
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "standard")
        for chunk in (b"tIME", b"tEXt", b"zTXt", b"iTXt"):
            self.assertNotIn(chunk, d.data)

    def test_unrecognised_suffix_reduces_with_standard_and_notes(self):
        src = noisy(self.tmp / "ss-01-a.ful.png", (1600, 900))
        d = ir.deliver(src)
        self.assertEqual(d.treatment, "standard")
        self.assertEqual(self.open(d.data).size[0], 1280)
        self.assertEqual(d.note, "ss-01-a.ful.png: unrecognised delivery suffix '.ful' "
                                 "-- delivered with the standard reduction")


if __name__ == "__main__":
    unittest.main()
