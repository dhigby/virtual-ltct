# Stage 8 — Record & publish

**Board status:** `Publishing` → `Online` · **Who:** Publisher · **Tool:** human, Cypher for Business

The final stage: record the video from the script and publish the course into Cypher for
Business, the LMS where learners actually take it. Recording and upload happen **outside
this repo** — the repo just holds the script and, afterward, the published link.

## Entry criteria

- The course has been piloted and fixes merged ([stage 7](07-pilot.md)).
- Move the board status to `Publishing`.

## How

1. **Record** the video using `NN-video-script.md` as the script.
2. **Upload** the recorded video into Cypher for Business's video tooling and assemble the
   course there (Cypher is the delivery platform; this repo does not automate it).
3. **Link it back.** Add the published Cypher course URL to the module's `README.md`
   frontmatter under `external_links:` — this is the one frontmatter edit the pipeline
   makes:

   ```yaml
   external_links:
     cypher: https://…            # the published course in Cypher for Business
   ```

4. Open a small PR with that frontmatter change and merge it.

## Exit criteria

- The course is live in Cypher for Business.
- The Cypher URL is recorded under `external_links:` in the course's `README.md`.

## Then

- ✅ Tick **"8. Recorded & published to Cypher"** on the tracker issue.
- Move the board status to **`Online`**.
- **Close the tracker issue** — the course is done.
