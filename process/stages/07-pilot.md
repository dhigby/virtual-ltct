# Stage 7 — Pilot

**Board status:** `Pilot` · **Who:** Pilot Coordinator · **Tool:** human, one real learner

Before a course is recorded and published, run it with **one** real learner. Piloting
catches the problems no reviewer sees: pacing that drags, instructions that don't land,
assumed knowledge the learner doesn't have.

## Entry criteria

- The course PR is merged ([stage 6](06-internal-review.md)).
- Move the board status to `Pilot`.

## How

1. **Recruit one learner** at roughly the target audience's level — ideally a consultant
   or trainee who hasn't seen the material.
2. **Publish the course to Moodle** and send them the Moodle course URL:

   ```bash
   /publish-to-moodle <course-slug>
   ```

   Moodle is where learners take these courses, so the pilot should happen where the real
   thing will. It is also the only way to pilot the parts a static page cannot show: the
   quiz as an actual quiz, and the course in the **Android app**, which is how a
   consultant in the field will meet it.

   The command dry-runs first, refuses to publish anything that fails the disclosure
   check, and creates the course **hidden** — make it visible when your learner is ready.
   The design document, mentor guide, video scripts and every answer key are held back;
   the correct answers reach Moodle only inside the quiz, where Moodle protects them.

   **Enrol the pilot learner with the course's manual enrolment method, never through a
   cohort**: how a learner was enrolled is what keeps pilot results out of the delivery
   reports.

   > **Don't send the `/review/` URL to a pilot learner.** That one is the reviewer view
   > and contains the answer key and the mentor guide's scoring notes.

   **Fallback while Moodle is still being set up:** the learner view still works and is
   still safe to send —
   `https://competencies.languagetechnology.org/learn/<course-slug>/`. It holds back the
   same material, but gives no working quiz and no app. Use it only if Moodle is not
   available yet; it will be retired once a pilot has run cleanly on Moodle.

3. Have them work through the lessons, scenario bank, and quiz as a learner would —
   including on a phone, if that is how their colleagues will take it.
4. **Capture their experience.** Ask:
   - Where did you get stuck or confused?
   - Did anything feel too fast, too slow, or too long?
   - Did the scenarios feel realistic?
   - Did the quiz test what the lessons taught?
   - What would have helped you most?
   - Did anything fail to work on your device — images not loading, a page that wouldn't
     open offline, a quiz that wouldn't submit?
5. Record the feedback as a comment on the tracker issue.
6. The Author makes fixes; the fixes are merged. Re-run `/publish-to-moodle <slug>` to put
   them in front of the learner — the publish is idempotent, so it updates the course
   rather than creating a second one. **Don't republish while a learner is part-way through
   the quiz:** a republish rebuilds the quiz, and an attempt they answered offline and
   haven't synced yet may not survive it. Agree a moment with the learners first.
7. The Pilot Coordinator confirms the issues are addressed.

## Exit criteria

- Pilot feedback is recorded on the tracker issue and resulting fixes are merged.
- The Pilot Coordinator has confirmed the course is ready to publish.

## Then

- ✅ Tick **"7. Piloted with one learner"** on the tracker issue.
- Go to [Stage 8 — Record & publish](08-publish.md).
