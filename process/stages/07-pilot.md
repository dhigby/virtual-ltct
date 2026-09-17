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
2. **Send them the learner view** — and *only* this URL:

   ```
   https://competencies.languagetechnology.org/learn/<course-slug>/
   ```

   It holds back the design document, the mentor guide and the quiz answer key, so the
   learner gets the course as a learner should meet it. It keeps working now that the PR
   is merged — it simply starts serving from `main` instead of the branch.

   > **Don't send the `/review/` URL to a pilot learner.** That one is the reviewer view
   > and contains the answer key and the mentor guide's scoring notes.

3. Have them work through the lessons, scenario bank, and quiz as a learner would.
4. **Capture their experience.** Ask:
   - Where did you get stuck or confused?
   - Did anything feel too fast, too slow, or too long?
   - Did the scenarios feel realistic?
   - Did the quiz test what the lessons taught?
   - What would have helped you most?
5. Record the feedback as a comment on the tracker issue.
6. The Author makes fixes; the fixes are merged.
7. The Pilot Coordinator confirms the issues are addressed.

## Exit criteria

- Pilot feedback is recorded on the tracker issue and resulting fixes are merged.
- The Pilot Coordinator has confirmed the course is ready to publish.

## Then

- ✅ Tick **"7. Piloted with one learner"** on the tracker issue.
- Go to [Stage 8 — Record & publish](08-publish.md).
