# Stage 8 — Record & publish

**Board status:** `Publishing` → `Online` · **Who:** Publisher · **Tool:** `/publish-to-moodle`, plus a human with a microphone

The final stage: record the course's video(s) from their scripts, then publish the course
into **Moodle**, where learners actually take it. Recording happens outside this repo —
the repo holds the scripts and, afterward, the published links. Publishing the course
itself is automated: the markdown here is the source, and Moodle is a render of it.

> **Why Moodle.** It costs nothing per learner, which Cypher for Business could not offer
> ([`INTENT.md`](../../INTENT.md)), and its Android app lets a consultant take a course
> offline in the field. Courses already delivered in Cypher keep their `cypher:` link and
> stay `Online`; nothing new goes there.

## Entry criteria

- The course has been piloted and fixes merged ([stage 7](07-pilot.md)).
- Move the board status to `Publishing`.

## How

1. **Record** the overview video using `NN-video-script.md` as the script, plus any
   per-lesson videos from their `NN-lesson-<L>-video-script.md` scripts.
2. **Upload** each video to Vimeo or Drive. Don't commit the video file itself; large
   binaries stay out of git.
3. **Fill in the lesson links.** Each video's lesson is waiting on it with
   `**Watch the video:** _To be recorded at stage 8._` — replace that with the real link,
   `**Watch the video:** [<title>](<url>)`.
4. **Publish to Moodle.**

   ```bash
   /publish-to-moodle <course-slug>
   ```

   It builds the course, verifies that nothing a learner shouldn't see is in it, and only
   then pushes. The course is created **hidden**; make it visible in Moodle when you are
   ready for learners.

   The publish is idempotent — every page and quiz is addressed by an identifier derived
   from its source file's number — so re-running it after a correction updates the course
   rather than creating a second one. Fixing a typo is: edit the markdown, merge,
   re-publish. Once learners are enrolled, don't republish in the middle of a cohort:
   a republish rebuilds the quiz, and an offline attempt that hasn't synced yet may not
   survive it.

5. **Link it back.** Add the published Moodle course URL to the module's `README.md`
   frontmatter under `external_links:` — this is the one frontmatter edit the pipeline
   makes, and it is what tells `/next-step` the course is Online:

   ```yaml
   external_links:
     moodle: https://…            # the published course in Moodle
   ```

6. Open a small PR with those changes and merge it.

## Exit criteria

- The course is live and visible in Moodle.
- The Moodle URL is recorded under `external_links:` in the course's `README.md`.
- No lesson still reads `**Watch the video:** _To be recorded at stage 8._`

## Then

- ✅ Tick **"8. Recorded & published"** on the tracker issue.
- Move the board status to **`Online`**.
- **Close the tracker issue** — the course is done.

## If the publish is refused

Two refusals are normal, and neither should be worked around:

- **"NOT PUBLISHABLE: no lesson files"** — the course's content is still only its README.
  It is a backfill placeholder; see [`BACKFILL.md`](../../BACKFILL.md).
- **"WITHHELD"** — a quiz whose answer key could not be cleanly separated from its
  questions, so publishing it would either leak the key or hand learners a broken quiz.
  Fix the `## Answer key` marker in the quiz file; it must be exactly that, an H2,
  optionally qualified (`## Answer key (Section 1)`).

Anything reported as a **LEAK** means a page contains answer-key text. Nothing is
published until that is fixed — there is no override, because once a page is on a server
learners can reach, the disclosure has already happened.
