# Stage 8 — Record & publish

**Board status:** `Publishing` → `Online` · **Who:** Publisher · **Tool:** human, Cypher for Business

The final stage: record the course's video(s) from their scripts and publish the course into
Cypher for Business, the LMS where learners actually take it. Recording and upload happen
**outside this repo** — the repo just holds the scripts and, afterward, the published links.

## Entry criteria

- The course has been piloted and fixes merged ([stage 7](07-pilot.md)).
- Move the board status to `Publishing`.

## How

1. **Record** the overview video using `NN-video-script.md` as the script, plus any
   per-lesson videos from their `NN-lesson-<L>-video-script.md` scripts.
2. **Upload** each recorded video into Cypher for Business's video tooling and assemble the
   course there (Cypher is the delivery platform; this repo does not automate it).
3. **Fill in the lesson links.** Each video's lesson is waiting on it with
   `**Watch the video:** _To be recorded at stage 8._` — replace that with the real link,
   `**Watch the video:** [<title>](<url>)`. Don't commit the video file itself; large
   binaries stay out of git.
4. **Link it back.** Add the published Cypher course URL to the module's `README.md`
   frontmatter under `external_links:` — this is the one frontmatter edit the pipeline
   makes:

   ```yaml
   external_links:
     cypher: https://…            # the published course in Cypher for Business
   ```

5. Open a small PR with those changes and merge it.

## Exit criteria

- The course is live in Cypher for Business.
- The Cypher URL is recorded under `external_links:` in the course's `README.md`.
- No lesson still reads `**Watch the video:** _To be recorded at stage 8._`

## Then

- ✅ Tick **"8. Recorded & published to Cypher"** on the tracker issue.
- Move the board status to **`Online`**.
- **Close the tracker issue** — the course is done.
