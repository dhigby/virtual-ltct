# Course Design Document

## Course overview

| Item | Description |
| --- | --- |
| **Title** | CoreTech: Why Keyboards Matter |
| **Competencies addressed** | Keyboards |
| **Target outcome level** | Has knowledge |
| **SME(s) consulted** | Jenni Beadle (SIL) — verifying Claude.ai-chat-drafted content against live Windows 11 + Keyman behavior |
| **Design status** | Approved by Jenni Beadle on 2026-07-22 |

## Background: how this course's scope changed

This course was originally being treated as a **backfill** (faithful import of the Cypher draft — developed and drafted there, but not yet delivered to any learners) — see the `Cypher-module_N.html` files, `qti_va_*.zip` quiz exports, and the PDF quick guide already saved in this folder, and the faithful `01-module-1.md` through `05-quiz.md` conversion done from them.

The SME has since decided that a **separately-produced Claude.ai-chat draft** (four modules, already structured in proper `## Connect/Content/Challenge/Change` H2 headings) should become the actual source of truth going forward, rather than the faithful Cypher backfill. This moves the course out of the backfill workstream (`process/backfill.md`, fidelity-only review) and into the real new-authoring pipeline (`process/PROCESS.md`) — every specific technical claim in the Claude-chat draft needs live SME verification before it's trusted, the same way FLEx procedures were verified against live FLEx 9.3.9.

**The faithful Cypher backfill content (`01-module-1.md` through `05-quiz.md`, as they exist today) is being superseded by this revision** — not deleted outright, but not the target for further editing. Once the Claude-chat draft is fully verified and adopted, those files will be overwritten with the verified version.

## Learning objectives

Carried over from the original Cypher course (see the existing `01-module-1.md`'s Learning Objectives for the full list) — to be reconfirmed once the new module breakdown is finalized.

## Module breakdown

One row per planned numbered lesson file, per the Claude.ai-chat draft:

| File | Topic | Stated duration in draft | Notes |
| --- | --- | --- | --- |
| `01-module-1.md` | Why Virtual Keyboards Matter | ~30 min | Close to original course's 20-30 min pace |
| `02-module-2.md` | Installing Keyman and Keyboards | ~90 min | At the repo's 90-min hard cap; well above original course's 20-30 min pace — confirm this expanded scope is intentional |
| `03-module-3.md` | Configuring System Settings | ~60 min | Above original course's pace — confirm intentional |
| `04-module-4.md` | Troubleshooting Common Issues | ~60 min | Above original course's pace — confirm intentional |
| **Total learner seat time** | | **~240 min** | vs. original Cypher course's ~4×25 min ≈ 100 min |

**Open question:** the Claude-chat draft roughly doubles-to-triples the original course's stated pacing. Confirm with the SME whether this expanded scope/depth is wanted, or whether the draft should be tightened back toward the original course's lighter pace before being adopted.

## Assessment plan

The original Cypher course's two quizzes (Module 1 check-in + Final Assessment, both already faithfully backfilled into `05-quiz.md`) may or may not still map cleanly onto the new module content once it changes — recheck once the new modules are finalized.

## SME knowledge notes

**Source:** four Claude.ai-chat-drafted modules (`Module_1_Why_Virtual_Keyboards_Matter.md`, `Module_2_Installing_Keyman_and_Keyboards.md`, `Module_3_Configuring_System_Settings.md`, `Module_4_Troubleshooting_Common_Issues.md`), supplied by the SME. The SME reports these were "verified as we went" during the original chat session, but specific claims are being independently re-checked here against live Windows 11 + Keyman, since AI-drafted procedural claims have already proven unreliable once in this repo (FLEx's writing-systems tab, sense-disambiguation right-click).

**Confirmed against live Windows 11 + Keyman by the SME:**
- `Shift + Alt + K` opens the on-screen keyboard pane — **confirmed**. This is a **Keyman-configurable hotkey** (Keyman Configuration → Hotkeys → "Show On Screen Keyboard Pane"), not a generic Windows shortcut — the draft's framing of it as a plain keyboard shortcut should be corrected to reflect that it's a Keyman hotkey setting.
- `Win + Space` cycles installed keyboards — **confirmed**.
- `Alt + Shift` (specifically **Left Alt+Shift**, per the Keyman Hotkeys screenshot, where it's bound by default to Keyman's own "Open Language Switcher") does cycle through installed keyboards on Windows 11 — **confirmed, but with a real caveat the draft got wrong**: it always applies on **Screen 1**, even if the active program/window is on Screen 2 (a multi-monitor bug). The draft's vague framing ("inconsistent across applications and Windows versions") should be replaced with this specific, accurate behavior.
- The Windows Settings path for adding a language is **`Time & Language → Language & Region → Add a language`** — the draft said `Time & Language → Language` (missing "& Region"). **Correct this exact path before it goes into lesson content.**
- The Keyman Configuration → Hotkeys panel (see screenshot on file) shows several configurable hotkeys beyond what the draft mentions: "Open Language Switcher" (default: Left Alt+Shift), "Show On Screen Keyboard Pane" (default: Shift+Alt+K), "Show Font Helper Pane," "Show Character Map," "Open Text Editor" (default: Ctrl+K), "Open Configuration" — most default to "(no hotkey)." A per-keyboard-layout "Change Hotkey" dialog offers No Hotkey / Left Alt+Shift / Ctrl+Shift / Custom (type your own combination). This is richer and more accurate than the draft's brief "look for a Hotkey option" description.

**Second round of confirmations (SME, live check + memory):**
- **Language count is wrong in the draft — correct to "more than 2,500 languages."** Confirmed via the keyman.com badge graphic ("More than 2500 languages supported"), not the draft's "over 2,000."
- **keyman.com is now a multilingual site with locale-prefixed URLs.** The draft's bare `keyman.com/windows` should be `https://keyman.com/en/windows/` (confirmed via the site's language switcher, which lists English/Deutsch/Español/Français/ Khmer). Apply the same `/en/` pattern to `keyman.com/keyboards` and `keyman.com/support` unless separately confirmed otherwise.
- `community.software.sil.org` → confirmed, resolves to the "Language Software Community" site; **the Keyman-specific area requires scrolling down the page**, it's not immediately visible at the top — worth a note in the lesson so learners don't think the link is wrong.
- `keyman.com/support` → confirmed, goes to Keyman Support.
- `scripts.sil.org` → confirmed reachable, but **it's an archive of the original site**, not an actively-maintained current resource — the lesson should frame it as an archive, not a live go-to support page.
- Windows UAC "install permission prompt, even if you're an administrator" (Module 2) — confirmed **from memory** (not a fresh live re-test), lower confidence than the screenshot-verified items above but accepted.
- The video referenced in the Module 1 draft ("Why Keyboarding Matters in Language Work") **is the same asset as the real Cypher course's `why.mp4`** — just referenced by title in the draft rather than filename. The full transcript already captured in the faithfully-backfilled `01-module-1.md` is valid and should carry over into the revised Module 1.
- Expanded duration (Module 2 ~90 min, Modules 3-4 ~60 min each, vs. the original course's ~20-30 min/module pace) — **confirmed intentional**, with the caveat that self-paced learners may move faster than the stated estimate.

All items from the design doc's original open-question list are now resolved. Next step: rewrite `01-module-1.md` through `04-module-4.md` using the Claude-chat drafts as the base, applying every correction above, and reconcile `05-quiz.md` against the revised content once that's done.
