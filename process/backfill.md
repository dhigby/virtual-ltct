# Backfilling legacy content from Cypher

Many LTC courses were already built and delivered in **Cypher for Business** (the LMS) or on
Google Sites, and only landed here as stubs or rough README imports during the Notion
migration. **Backfill** brings that delivered content into the repo as real markdown so this
repo becomes the true source of truth. Progress is tracked in [`BACKFILL.md`](../BACKFILL.md).

This is separate from the [new-course pipeline](PROCESS.md). Backfilled courses are a
**faithful import** — they are grandfathered and do **not** need the full 7-file package
(no design doc, quiz, scenario bank, mentor guide, or video script). Only if a course is
later substantially revised does it enter the pipeline.

## Who does this

Any team member, plus someone with Cypher access to save the source content. Backfill PRs are
reviewed for **fidelity, not pedagogy** — the goal is "same course, now in the repo," not a
rewrite.

## Procedure

1. **Pick a course** from [`BACKFILL.md`](../BACKFILL.md) (start with the README-only
   "content" courses — the fix is most visible there).
2. **Get the source content.** Open the course in Cypher for Business (or its Google Site /
   PDF, per the source column in `BACKFILL.md`) and save the page(s): "Save As → Web Page"
   or copy the HTML. There's no Cypher API here — a human does this step and drops the file
   somewhere Claude Code can read it.
3. **Convert it.** In Claude Code:

   > Use the **content-backfiller** agent to convert `<path-to-saved.html>` into faithful
   > markdown lessons for `modules/<slug>/`.

   It reproduces the source's structure as numbered files, cleans up import artifacts, keeps
   videos as links, and invents nothing.
4. **Review the diff for fidelity.** Does the markdown say what the source said? Are the
   `<!-- TODO -->` markers (missing images, un-supplied sections) acceptable or do you need
   to supply more source?
5. **Update frontmatter** in `README.md`: set `content_type: content` and add the source URL
   under `external_links:` (e.g. `cypher:` or `materials:`).
6. **Regenerate coverage:** `python scripts/gen_coverage.py`, and commit it with the change.
7. **Open a PR** labelled `backfill`, and flip the course's row in `BACKFILL.md` to
   `In repo` (then `Verified` once a second person confirms fidelity).

## Board note

Backfill does **not** change a course's delivery status — a course already delivered in
Cypher is `Online` on the board regardless of whether its content lives here yet. Track
backfill progress in `BACKFILL.md`, not with a board status.
