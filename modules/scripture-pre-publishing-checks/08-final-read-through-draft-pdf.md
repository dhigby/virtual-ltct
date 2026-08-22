title: "Final Read-Through: Producing the Draft PDF"

course: "Finalizing Your Translation for Publication"

chapter_number: 8

level: Experienced Translator

goal: Produce a final draft PDF in PTXprint and read the whole publication through, page by page, to catch the layout and flow problems the on-screen checks cannot show — before the files go to the typesetter

prerequisites: "All text checks (chapters 3–6) complete and clean; illustrations, front/back matter, and glossary linking done; PTXprint installed and your project opens in it"

# 8. Final Read-Through: Producing the Draft PDF

**Level:** Experienced Translator
**Goal:** Produce a final draft PDF in PTXprint and read the whole publication through, page by page, to catch the layout and flow problems the on-screen checks cannot show — before the files go to the typesetter

---

## Introduction

Every check so far has looked at the text on screen, one issue at a time. But a reader never meets your translation on screen — they meet it on a printed page, where columns, headings, footnotes, and pictures all compete for the same space. This final read-through is where you see the whole publication the way the reader will, and catch the layout and flow problems that no on-screen check can surface.

You produce a **draft PDF** with **PTXprint** — the tool that has replaced the old *Print Draft* — and read the book through from the beginning, one **spread** (a pair of facing pages) at a time.

> **NOTE** This is a *proofing* read-through of a *draft*. The typesetter still does the professional composition — but the cleaner your draft, the fewer surprises come back from them.

### What This Chapter Covers

- Producing a draft PDF of the whole publication in PTXprint
- Viewing facing pages side by side (**spreads**), the way a reader sees them
- Reading through systematically for layout and flow problems
- Resolving underfilled pages
- Comparing a new draft against a previous one (the Diff feature)
- Archiving your PTXprint settings with the PDF

### Prerequisites

- All the text checks (chapters 3–6) are complete and clean.
- Illustrations, front/back matter, and glossary linking (chapters 1, 2, 7) are done.
- PTXprint is installed and your project opens in it.

---

## Procedure Walkthrough

### Step 1 — Generate the draft PDF

1. Open your project in **Paratext**.
2. From the **Project** menu, choose **Export draft PDF (PTXprint)** (Ctrl+Shift+P) — this opens PTXprint for your project.
3. Under **What to Print**, select **Portions or Multiple Books**, then click **Select Multiple Books…**.
4. In the **Select Books To Export to PDF** dialog, choose the whole publication — use **New Testament** (or **All Books**), and add any **Extra Material** (front/back matter such as INT, GLO, and the indexes) — then click **OK**.
5. Click the **Print (Make PDF)** button to generate the PDF.
   - *After printing, PTXprint reports on any issues it finds that need attention — for example **underfilled pages**, which you resolve in Step 4 below.*

> **TIP** For a large project you can review **one book at a time** — choose **Single Book** and set the chapter range instead — but the *final* read-through should cover the whole publication as it will be published.

> **NOTE** These steps were verified against **PTXprint 3.0.38**. PTXprint is updated often, so menu labels and controls may move between versions — if something isn't where this describes, check **PTXprint's built-in Help**.

<!-- image: Paratext — Project menu > Export draft PDF (PTXprint) -->
<!-- image: PTXprint — What to Print > Select Books To Export to PDF dialog -->
<!-- image: PTXprint — Print (Make PDF) button -->


### Step 2 — View the pages as spreads (Book View)

- In the **PDF Preview** panel, tick **Book View** to show facing pages **side by side**, the way a reader meets the book.
- This is best practice for a typesetting review — problems that cross a page boundary only show up when you can see both pages at once.

<!-- image: PTXprint — PDF Preview panel with Book View ticked -->


### Step 3 — Read through systematically

Start at the beginning and work through the whole book **spread by spread**, looking for:

- **Gaps or blank lines** at the bottom of a page or column
- **Orphan words** — a single word stranded on its own line at the end of a paragraph
- **Footnote problems** — footnotes shifting and causing spacing issues as they move with their associated text
- **Heading placement** — a large heading forcing text onto the next page
- **Picture placement** — an image creating a gap or pushing text awkwardly
- **Unbalanced columns** (two-column layouts only) — the columns on a page should end level

> **NOTE** Whether your publication is **single- or two-column** is a **community-expectation** decision — what readers are used to seeing — not just a matter of word length. Long-word languages often use a single column, while two-column layouts usually need **hyphenation** to balance well (a hyphenation file to set up with your LTC). Check what the community expects; the column check above applies only if you are using two columns.

> **CAUTION** You can *see* these layout problems in the PDF, but the PDF is not the thing you edit. Corrections go back into the project (text, spacing, hyphenation) or into your PTXprint settings; then you re-generate the PDF and look again. Some layout decisions are the typesetter's to finalise — note those rather than forcing them here.

> **TIP** In newer versions of PTXprint, when you spot a problem you can **right-click** it in the PDF and choose **Jump to Ref in Paratext** — PTXprint takes you straight to that reference in Paratext, so the fix can be made at source. (The same right-click menu also offers per-paragraph **Shrink / Expand** adjustments for fine layout tweaks that belong in PTXprint rather than in the text.)

<!-- image: PTXprint — right-click menu > Jump to Ref in Paratext (with Shrink/Expand adjustments) -->


### Step 4 — Resolve underfilled pages

- PTXprint lists the **underfilled pages** (pages with too little content, leaving a gap at the bottom) after you click **Print**. Work through them **systematically until none remain**.
- In recent versions (**v3.0.19+**), the **(Auto) Fill Page** feature in the PDF Viewer can address underfilled pages automatically.

### Step 5 — Compare against a previous draft (optional)

When you have made corrections and want to confirm exactly what changed between drafts:

1. If PTXprint is showing the **PDF preview**, click the **settings (gear) icon** to switch to the settings view, then open the **Finishing** tab.
2. Enable **Create Diff** (**Ctrl-R**) to generate a PDF that highlights the differences between this version and a previous one.
3. Tick **"Only Show Pages With Differences"** to keep the diff PDF a manageable size.

> **NOTE** Comparing only works if PTXprint has kept an earlier draft — check that **"Number of Previous PDFs to be Kept"** (under **PDF Details**) is set to **1 or more**.

<!-- image: PTXprint — Finishing tab > PDF Details (Create Diff, Number of Previous PDFs to be Kept) -->


### Step 6 — Archive your settings

- On the **Finishing** tab (if needed, click the **gear icon** to switch from the PDF preview to the settings), confirm **"Include Config Settings Within PDF"** is ticked — it is **on by default** in current versions — so your PTXprint configuration is embedded in the PDF.
- This makes it easy to reproduce, reference, or share the exact settings later — useful when you hand the draft to the typesetter or come back to it after a break.

<!-- image: PTXprint — Finishing tab > PDF Details > Include Config Settings Within PDF (on by default) -->


---

## Verification Checklist

**Generate and view:**

- [ ] Draft PDF of the whole publication generated in PTXprint
- [ ] Pages reviewed in **Book View** (facing pages side by side)

**Read-through (from the beginning, spread by spread):**

- [ ] No gaps or blank lines at the bottom of pages or columns
- [ ] No stranded orphan words
- [ ] Footnotes sit correctly with their text
- [ ] No headings pushing text awkwardly onto the next page
- [ ] Images placed without creating gaps or awkward pushes
- [ ] Columns balanced (two-column layouts)
- [ ] No underfilled pages remaining

**Finish:**

- [ ] (If comparing drafts) Diff PDF reviewed against the previous version
- [ ] PTXprint settings archived in the PDF

---

## Summary and Next Steps

You have produced a draft PDF, viewed it the way the reader will, and read the whole publication through — resolving layout and flow problems and underfilled pages before handoff. Your publication-ready USFM and its verified draft are ready for the typesetter.

**Congratulations!** You've completed all the finalizing-for-publication chapters. Your next steps:

1. **Final comprehensive review:**
   - Review all chapters' verification checklists
   - Confirm all tasks are complete
   - Address any remaining items
2. **Consultant approval:**
   - Submit the complete project to your Translation Consultant
   - Address any final feedback
   - Obtain sign-off for publication
3. **Typesetter coordination:**
   - Provide all necessary files and documentation (including the draft PDF)
   - Communicate any special requirements
   - Typically the team travels with the files, so any changes needed during typesetting can be made with — or by — the team on-site
   - Review typeset samples
4. **Publication process:**
   - Follow your organization's publication workflow
   - Maintain archival copies
   - **Celebrate!** Completing the pre-publishing checks is a real milestone — many teams mark it at the branch before the files go to the typesetter, with more celebration once typesetting is done.
