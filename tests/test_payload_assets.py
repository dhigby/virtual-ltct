"""Payload asset records, gate check 5, the output guard and the delivery-suffix check
(spec 009). Run: python -m pytest tests/

Builds a throwaway course in a temp dir with generated images: nothing here is committed
(SC-005), and the real modules/ tree is never touched.
"""
import contextlib, hashlib, io, json, pathlib, random, sys, tempfile, unittest
REPO = pathlib.Path(__file__).resolve().parents[1]
sys.path.insert(0, str(REPO / "scripts"))
import check_course_package as ccp
import check_moodle_payload as cmp
import disclosure
import moodle_payload as mp
from PIL import Image

SLUG = "test-course"
DESIGN = "# Design\n\n| **Design status** | Draft |\n|---|---|\n"
LESSON = """\
# Lesson one

**Estimated time:** 30 minutes

## Connect

![The find dialog with Regex ticked](assets/ss-01-a.png)

## Content

![A dense dialog that must stay sharp](assets/ss-01-b.full.png)

## Challenge

![A diagram of the workflow](assets/ss-01-c.svg)

## Change

Done.
"""


def noisy(path, size, seed):
    rnd = random.Random(seed)
    w, h = size
    Image.frombytes("RGB", size, bytes(rnd.randrange(256) for _ in range(w * h * 3))
                    ).save(path, format="PNG")


def sha256(p):
    return hashlib.sha256(pathlib.Path(p).read_bytes()).hexdigest()


class Course(unittest.TestCase):
    """A course under <tmp>/modules/<slug>, with the gate pointed at that modules root."""

    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.tmp = pathlib.Path(self._tmp.name)
        self.modules = self.tmp / "modules"
        self.folder = self.modules / SLUG
        (self.folder / "assets").mkdir(parents=True)
        (self.folder / "00-design.md").write_text(DESIGN, encoding="utf-8")
        (self.folder / "01-lesson.md").write_text(LESSON, encoding="utf-8")
        noisy(self.folder / "assets" / "ss-01-a.png", (1600, 900), 1)
        noisy(self.folder / "assets" / "ss-01-b.full.png", (900, 500), 2)
        (self.folder / "assets" / "ss-01-c.svg").write_text(
            '<svg xmlns="http://www.w3.org/2000/svg"/>', encoding="utf-8")
        self.out = self.tmp / "out"
        self._modules = cmp.MODULES
        cmp.MODULES = self.modules

    def tearDown(self):
        cmp.MODULES = self._modules
        self._tmp.cleanup()

    def build(self):
        manifest, pages, assets = mp.Payload(self.folder, "learner").build()
        return manifest, mp.write_payload(manifest, pages, assets, self.out)

    def manifest_of(self, payload):
        return json.loads((payload / "manifest.json").read_text(encoding="utf-8"))

    def save_manifest(self, payload, manifest):
        (payload / "manifest.json").write_text(json.dumps(manifest), encoding="utf-8")

    def gate(self, payload):
        problems, _ = cmp.check(payload)
        return problems


class AssetRecords(Course):
    def test_one_record_per_delivered_file(self):
        manifest, payload = self.build()
        files = sorted(p.name for p in (payload / "assets").iterdir())
        self.assertEqual(sorted(manifest["assets"]), files)
        for name, rec in manifest["assets"].items():
            self.assertEqual(set(rec), {"source", "source_sha256", "source_bytes",
                                        "sha256", "sha1", "bytes", "treatment"})
            self.assertEqual(rec["source"], "assets/" + name)
            self.assertLessEqual(rec["bytes"], rec["source_bytes"])
            data = (payload / "assets" / name).read_bytes()
            self.assertEqual(rec["sha1"], hashlib.sha1(data).hexdigest())
            self.assertEqual(rec["sha256"], hashlib.sha256(data).hexdigest())
        self.assertEqual(manifest["assets"]["ss-01-a.png"]["treatment"], "standard")
        self.assertEqual(manifest["assets"]["ss-01-b.full.png"]["treatment"], "full")
        self.assertEqual(manifest["assets"]["ss-01-c.svg"]["treatment"], "vector")

    def test_weight_report(self):
        manifest, _ = self.build()
        w = manifest["image_weight"]
        self.assertEqual(set(w), {"source_bytes", "delivered_bytes", "by_treatment"})
        self.assertLess(w["delivered_bytes"], w["source_bytes"])
        self.assertEqual(w["by_treatment"], {"standard": 1, "full": 1, "vector": 1})

    def test_names_links_and_alt_text_unchanged(self):
        _, payload = self.build()
        html = (payload / "pages" / "01-lesson.html").read_text(encoding="utf-8")
        for name in ("ss-01-a.png", "ss-01-b.full.png", "ss-01-c.svg"):
            self.assertIn('src="@@PLUGINFILE@@/%s"' % name, html)
        self.assertIn('alt="The find dialog with Regex ticked"', html)

    def test_report_lines(self):
        manifest, _ = self.build()
        buf = io.StringIO()
        with contextlib.redirect_stdout(buf):
            mp.report_images(manifest)
        out = buf.getvalue()
        self.assertRegex(out, r"images\s+3: \d+ KB -> \d+ KB \(\d+% lighter\)")
        self.assertRegex(out, r"full\s+ss-01-b\.full\.png")

    def test_repo_folder_untouched(self):
        before = {p: p.read_bytes() for p in self.folder.rglob("*") if p.is_file()}
        self.build()
        after = {p: p.read_bytes() for p in self.folder.rglob("*") if p.is_file()}
        self.assertEqual(before, after)

    def test_corrupt_image_stops_the_build(self):
        (self.folder / "assets" / "ss-01-a.png").write_bytes(
            bytes(random.Random(9).randrange(256) for _ in range(4096)))
        with self.assertRaises(SystemExit) as cm:
            mp.Payload(self.folder, "learner").build()
        self.assertIn("cannot read image %s/assets/ss-01-a.png:" % SLUG, str(cm.exception.code))
        self.assertFalse(self.out.exists())

    def test_unrecognised_suffix_is_a_note(self):
        (self.folder / "assets" / "ss-01-a.png").rename(self.folder / "assets" / "ss-01-a.ful.png")
        (self.folder / "01-lesson.md").write_text(
            LESSON.replace("ss-01-a.png", "ss-01-a.ful.png"), encoding="utf-8")
        manifest, _ = self.build()
        self.assertEqual(manifest["assets"]["ss-01-a.ful.png"]["treatment"], "standard")
        self.assertIn("ss-01-a.ful.png: unrecognised delivery suffix '.ful' -- delivered "
                      "with the standard reduction", manifest["notes"])

    def test_legacy_folder_dots_are_not_suffixes(self):
        (self.folder / "images").mkdir()
        noisy(self.folder / "images" / "L2-1.Blank-BT-Row.png", (1600, 900), 12)
        (self.folder / "01-lesson.md").write_text(
            LESSON + "\n![Blank row](images/L2-1.Blank-BT-Row.png)\n", encoding="utf-8")
        manifest, payload = self.build()
        rec = manifest["assets"]["L2-1.Blank-BT-Row.png"]
        self.assertEqual((rec["treatment"], rec["source"]),
                         ("standard", "images/L2-1.Blank-BT-Row.png"))
        self.assertFalse([n for n in manifest["notes"] if "delivery suffix" in n])
        self.assertEqual(self.gate(payload), [])


class GateCheck5(Course):
    def test_clean(self):
        _, payload = self.build()
        self.assertEqual(self.gate(payload), [])

    def test_replaced_png(self):
        _, payload = self.build()
        noisy(payload / "assets" / "ss-01-a.png", (300, 200), 7)
        self.assertTrue(self.gate(payload))

    def test_deleted_record(self):
        _, payload = self.build()
        m = self.manifest_of(payload)
        del m["assets"]["ss-01-a.png"]
        self.save_manifest(payload, m)
        self.assertTrue(any("ss-01-a.png" in p for p in self.gate(payload)))

    def test_unrecorded_file(self):
        _, payload = self.build()
        (payload / "assets" / "stray.png").write_bytes(b"x")
        self.assertTrue(any("stray.png" in p for p in self.gate(payload)))

    def test_excluded_source(self):
        _, payload = self.build()
        noisy(self.folder / "assets" / "ss-09-quiz.png", (50, 50), 4)
        m = self.manifest_of(payload)
        m["assets"]["ss-01-a.png"]["source"] = "assets/ss-09-quiz.png"
        m["assets"]["ss-01-a.png"]["source_sha256"] = sha256(self.folder / "assets" / "ss-09-quiz.png")
        self.save_manifest(payload, m)
        self.assertTrue(any("excluded" in p for p in self.gate(payload)))

    def test_full_must_be_committed_bytes(self):
        _, payload = self.build()
        target = payload / "assets" / "ss-01-b.full.png"
        noisy(target, (100, 100), 8)
        m = self.manifest_of(payload)
        rec = m["assets"]["ss-01-b.full.png"]
        data = target.read_bytes()
        rec.update(sha256=hashlib.sha256(data).hexdigest(),
                   sha1=hashlib.sha1(data).hexdigest(), bytes=len(data))
        self.save_manifest(payload, m)
        self.assertTrue(any("ss-01-b.full.png" in p for p in self.gate(payload)))

    def test_source_changed_after_build(self):
        _, payload = self.build()
        noisy(self.folder / "assets" / "ss-01-a.png", (1600, 900), 11)
        self.assertTrue(any("ss-01-a.png" in p for p in self.gate(payload)))


class OutputGuard(unittest.TestCase):
    def guarded(self, out):
        err = io.StringIO()
        with contextlib.redirect_stderr(err), self.assertRaises(SystemExit) as cm:
            mp.write_payload({"slug": "x"}, {}, {}, out)
        self.assertEqual(cm.exception.code, 2)
        self.assertIn("refusing to write the payload inside the repository", err.getvalue())
        self.assertIn("omit --keep-payload to use a temp folder", err.getvalue())

    def test_inside_repo(self):
        target = REPO / "payload"
        existed = target.exists()
        self.guarded(target)
        self.assertEqual(target.exists(), existed)

    def test_dotdot_inside_repo(self):
        self.guarded(REPO / "scripts" / ".." / "payload-dotdot")
        self.assertFalse((REPO / "payload-dotdot").exists())

    def test_relative_inside_repo(self):
        import os
        cwd = os.getcwd()
        os.chdir(REPO)
        try:
            self.guarded("./payload-relative")
        finally:
            os.chdir(cwd)
        self.assertFalse((REPO / "payload-relative").exists())


class SuffixPackageCheck(Course):
    def suffix_errors(self):
        errors, _ = ccp.check_course(self.folder)
        return [e for e in errors if "delivery suffix" in e]

    def test_accepts_full_small_and_none(self):
        noisy(self.folder / "assets" / "ss-01-d.small.png", (50, 50), 5)
        self.assertEqual(self.suffix_errors(), [])

    def test_rejects_typos(self):
        for bad in ("ss-01-x.ful.png", "ss-01-x.full.small.png"):
            p = self.folder / "assets" / bad
            noisy(p, (50, 50), 6)
            errors = self.suffix_errors()
            self.assertTrue(any("assets/%s: unrecognised delivery suffix -- use "
                                "ss-01-x.full.png (send the original) or "
                                "ss-01-x.small.png (smaller), or no suffix" % bad in e
                                for e in errors), errors)
            p.unlink()


class SuffixDoesNotEscapeDisclosure(unittest.TestCase):
    def test_suffixed_companion_still_excluded(self):
        for name in ("ss-09-quiz.full.png", "09-mentor-guide.small.png",
                     "00-design.full.svg"):
            self.assertTrue(disclosure.excluded_asset(name, "learner"), name)
        self.assertFalse(disclosure.excluded_asset("ss-01-a.full.png", "learner"))


if __name__ == "__main__":
    unittest.main()
