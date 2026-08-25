# Doug's Demo Script — "Our GitHub Workflow, in 20 Minutes"

A presenter's script for a live, screen-shared walkthrough that gets the team comfortable with
the GitHub side of our workflow. Built around a **real** course in flight so nothing is abstract.

- **Audience:** team members new to GitHub Projects (SMEs, authors) — most will only ever *review*.
- **Presenter:** Doug (or whoever is git-fluent), screen-shared.
- **Length:** ~20 minutes. Section 3 is deliberately skippable for review-only folks.

> **Keep it current.** This script names a specific PR and issue that were in flight when it was
> written (Software Support: **PR #37**, tracker **issue #15**). Once that PR merges, swap in
> whatever course is mid-flight on the day of the demo — the *steps* stay the same, only the
> numbers change.

**Open with this promise:** *"By the end of this, reviewing a course will feel as easy as reading
a web page — because that's all it is. Most of you will never need to touch git."*

---

## 0. Framing (2 min) — *say this, no screen needed*

- Three things live in three places: **the files** (the course content, in GitHub), **the board**
  (who's doing what, what stage each course is at), and **PRs** (how a change gets looked at before
  it becomes official).
- Everyone here is one of two roles:
  - **Reviewers / SMEs** — you check content and comment, entirely in the browser.
  - **Authors** — they draft with Claude Code and use git.
- *"If you're mostly reviewing, the git stuff in the second half is optional — relax."*

## 1. The board (4 min) — *share screen: open the "LTC Training Modules" Project*

- **Do:** Open the board. Point to the columns.
- **Say:** "Each card is a course. This is our to-do list."
- **Do:** Click the **Software Support** card → open its **tracker issue (#15)**. Show the checklist
  (Design approved ✓, Content drafted ✓, Scenario bank ✓, Quiz ✓ …).
- **Point out — the gotcha:** "There are *two* status fields, and this trips everyone up.
  **Status** (Todo / In Progress / Done) is what the columns use. **Module Status** is our real
  8-stage pipeline — Design, Drafting, SME Check, and so on. A card can look stuck in one and have
  moved in the other. When you want to know where a course *really* is, look at **Module Status**."
  *(Show software-support: In Progress / SME Check.)*

## 2. Reviewing a PR — the part that matters most (7 min) — *the browser path*

- **Say:** "A 'PR' — pull request — is just a proposed set of changes, waiting for a look before
  it's accepted. Reviewing one is all browser, no downloading."
- **Do:** Open **PR #37**. Walk the two tabs slowly:
  - **Conversation** — the summary of what changed and any discussion. *(Show the SME fact-check
    comment already there.)*
  - **Files changed** — "This is the whole point. Red is removed, green is added. New files show in
    full." Scroll the scenario bank / quiz so they see content appear with **no pulling, no
    branches**.
- **Do:** Hover a line → click the blue **+** → type a comment → "Start a review." Then show
  **Finish your review → Comment / Approve / Request changes.**
- **Say:** "That's the entire SME job: read Files changed, drop comments, finish the review.
  That's it."
- **Relatable callback:** "Last week the files looked missing — only `01` and a README showed up
  on someone's disk. That's because the new files were *on this PR's branch, not the main copy*.
  The fix wasn't more git — it was just opening this page. The browser always shows you everything."

## 3. The author path — optional, for those who draft (5 min) — *only if the group wants it*

- **Say:** "If you're going to *create* content, here's the fuller loop. Reviewers can tune out."
- **Do, briefly:** In Claude Code run `/work-on <slug>` → it puts you on that course's branch
  and names the next step. Show: make changes → they're on the course's **branch**
  (`course/<slug>`) → **open a PR** → the team reviews it (the page we just saw) → it gets
  merged into `main`.
- **Say the reassuring part out loud:** *"You never create a branch or type a branch name.
  `/work-on` does it. There's exactly one branch per course, so you can't accidentally make a
  second one — and if someone else is already working that course, it tells you."*
- **Point out — the two things that confuse people:**
  - **"Getting the latest" isn't automatic.** Clicking the Source Control icon shows *your* changes;
    it doesn't check the server. You have to **Fetch, then Pull** ("Sync Changes"). *(Demo the Sync
    button in the status bar.)* `/work-on` also pulls for you when it starts.
  - **"Where did my files go?"** = you're on `main` but the work is on a branch (or vice-versa).
    **The fix is to run `/work-on <slug>` again** — it tells you which branch you're on and puts
    you back on the right one. (The branch name is also bottom-left in VS Code.) If you try to
    edit course content while on `main`, Claude Code will stop you and say the same thing.

## 4. Close (2 min)

- **The one-sentence takeaway:** *"Reviewers: bookmark the PR link, click Files changed, comment.
  Authors: `/work-on <slug>` tells you what to do, and a PR is how it gets checked."*
- **Where to get help:** the team channel, or ask Claude Code / Doug. "Confusion is a doc bug —
  tell us and we'll fix the guide."

---

*Companion references:* [`ONBOARDING.md`](../../ONBOARDING.md) (the method and the daily loop),
[`CONTRIBUTING.md`](../../CONTRIBUTING.md) (browser editing for small fixes),
[`process/PROCESS.md`](../PROCESS.md) (the full 8-stage pipeline).
