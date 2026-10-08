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
   re-publish. A lesson you rename, renumber or remove is **retired** on the next publish:
   hidden and moved to a hidden "Retired" section at the end of the course, never
   deleted, so a learner's past attempt stays readable. The publish lists each one. Once learners are enrolled, don't republish in the middle of a cohort:
   a republish rebuilds the quiz, and an offline attempt that hasn't synced yet may not
   survive it.

5. **Link it back.** Add the published Moodle course URL to the module's `README.md`
   frontmatter under `external_links:` — this is the one frontmatter edit the pipeline
   makes, and it is what tells `/next-step` the course is Online:

   ```yaml
   external_links:
     moodle: https://…            # the published course in Moodle
   ```

6. **Suspend the pilot learners' enrolments.** In the course's *Participants* page, set
   each pilot learner's **manual** enrolment to *Suspended*. Do not unenrol them: their
   completion stays, and they keep it.

   This has to come before the next step. The pilot and the published course are one
   Moodle course, so a pilot learner's completion is still on record, and once the badge
   is switched on Moodle's badge check would award it to them from their pilot run. A pilot
   learner who later joins through their organisation is enrolled again by cohort, and gets
   the badge then, from the completion they already have.

7. **Make the course visible, then publish again, as the delivery.** Moodle's badge check
   skips a hidden course, so the publish reports `course-hidden` and exits 1 until it is
   visible. With the Moodle link in the README, the course is at
   stage 8, and `/publish-to-moodle <course-slug>` now switches the course's **completion
   badge** on and adds its **certificate** activity. A pilot publish never does either: a
   pilot issues no badge and has no certificate. The delivery publish also moves the course
   out of **LTC Pilots** into **LTC Published**, where organisations can be enrolled (an
   organisation-only course stays in its organisation's category). The publish says what it
   did:

   ```text
     placement moved to ltct:published
     recognition  badge unchanged, activated; certificate created
   ```

   The badge says "training completed", and the certificate is a certificate of training
   completed, never a certification (spec 013). Learners who finish the course get the
   badge automatically and can download the certificate from the course page.

8. **Enrol the organisations.** This is the site team's job, done in Moodle. Learner data
   never goes in this repo. Never use groups to keep organisations apart. Which recipe
   depends on whether the course is listed in
   [`moodle/site/org-courses.yaml`](../../moodle/site/org-courses.yaml):

   - **A shared course** (not listed, which is most courses): for each organisation, add a
     **cohort sync** enrolment for its learner cohort, `ltct:org:<key>`, as **Student**, with
     no group. Never add a managers cohort: managers would see every organisation's people.
   - **An organisation-only course** (listed): the publish has already put it in that
     organisation's category. Add two **cohort sync** enrolments, both with no group: the
     organisation's learner cohort, `ltct:org:<key>`, as **Student**, and its managers
     cohort, `ltct:org:<key>:managers`, as **Organisation manager**. Enrol no other
     organisation.

   The site team adds each cohort with `python scripts/ltct_admin.py enrol course --cohort
   <cohort> --course ltct:<slug>` (or `/manage-learners`), which previews first and
   refuses any enrolment the rules above do not allow. Managers can also enrol their own
   learners one at a time, from their **My organisation** page. Making a course
   organisation-only is the maintainer's decision, and is done before the publish. The steps
   are in [`moodle/site/README.md`](../../moodle/site/README.md), under "The site team's
   administration tool".

9. Open a small PR with those changes and merge it.

## Exit criteria

- The course is live and visible in Moodle.
- The Moodle URL is recorded under `external_links:` in the course's `README.md`.
- The delivery publish ran after the link: the course's badge is active and its certificate
  activity exists.
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

## Retiring a course

**Hide it in Moodle; never delete it.** Deleting a course archives its badges, which breaks
verification for everyone who holds one, and deleting its certificate activity deletes every
certificate code already issued. A hidden course keeps both working: a badge's link and a
certificate's code still verify, and learners can still download their certificates.
